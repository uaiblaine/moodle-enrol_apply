<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for the enrolment instance edit form.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass target below resolves when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/edit_form.php');

/**
 * Tests for the enrolment instance edit form.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_edit_form::class)]
#[CoversClass(\enrol_apply\local\notifyrecipients::class)]
#[CoversClass(\enrol_apply\local\preset::class)]
final class edit_form_test extends \advanced_testcase {
    /** @var \stdClass Course the apply instance belongs to. */
    private $course;

    /** @var \stdClass The enrol_apply instance record. */
    private $instance;

    /** @var \enrol_apply_plugin The plugin instance. */
    private $plugin;

    /**
     * Create a course carrying a single enabled apply enrolment instance.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB, $PAGE;

        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $instanceid = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        $PAGE->set_url(new \moodle_url('/enrol/apply/edit.php', ['courseid' => $this->course->id]));

        $this->setAdminUser();
    }

    /**
     * The two capacity fields, with the limit each one sets.
     *
     * @return array Element name, keyed by a readable case name.
     */
    public static function limit_provider(): array {
        return [
            'applicant limit' => ['customint3'],
            'places' => ['customint4'],
        ];
    }

    /**
     * A negative limit is refused, on that field alone, while 0 and a positive number pass.
     *
     * capacity::applicant_limit() and capacity::places() read anything below 1 as no limit, so a
     * stored -1 would be a number the instance does not honour as typed.
     *
     * @param string $field The capacity element under test.
     * @return void
     */
    #[DataProvider('limit_provider')]
    public function test_a_negative_limit_is_refused(string $field): void {
        $other = $field === 'customint3' ? 'customint4' : 'customint3';
        $form = $this->form();

        $errors = $form->validation($this->submission([$field => -1, $other => 0]), []);
        $this->assertArrayHasKey($field, $errors);
        $this->assertSame(get_string('limitnegative', 'enrol_apply'), $errors[$field]);
        // The refusal is scoped to the field that holds the negative.
        $this->assertArrayNotHasKey($other, $errors);

        // The controls: 0, which means no limit, and a positive limit are both accepted.
        foreach ([0, 5] as $value) {
            $errors = $form->validation($this->submission([$field => $value, $other => 0]), []);
            $this->assertArrayNotHasKey($field, $errors, "a limit of {$value} should be accepted");
        }
    }

    /**
     * A real submission carrying a negative limit is not accepted, and one carrying 0 is.
     *
     * The direct calls above pin validation(); this pins the whole path a teacher takes, through
     * the PARAM_INT cleaning that runs before validation().
     *
     * @param string $field The capacity element under test.
     * @return void
     */
    #[DataProvider('limit_provider')]
    public function test_a_submitted_negative_limit_is_not_saved(string $field): void {
        $this->assertNull($this->submit([$field => '-1']));

        // The control: the same submission with 0 goes through and carries the value.
        $data = $this->submit([$field => '0']);
        $this->assertNotNull($data, 'the control submission should be accepted');
        $this->assertSame(0, (int) $data->{$field});
    }

    /**
     * Build the edit form for the instance.
     *
     * @return \enrol_apply_edit_form The form.
     */
    private function form(): \enrol_apply_edit_form {
        $context = \context_course::instance($this->course->id);

        return new \enrol_apply_edit_form(null, [$this->instance, $this->plugin, $context]);
    }

    /**
     * Validation data that passes every other check, with the given values on top.
     *
     * @param array $values Element values to set, keyed by element name.
     * @return array The data validation() receives.
     */
    private function submission(array $values): array {
        return array_merge([
            'status' => ENROL_INSTANCE_ENABLED,
            'enrolstartdate' => 0,
            'enrolenddate' => 0,
            'customint5' => 0,
        ], $values);
    }

    /**
     * Drive a real submission of the edit form and return what it exports.
     *
     * @param array $values Element values to submit on top of a valid submission.
     * @param string[] $omit Element names to leave out of the submission, as a browser leaves out what it does not post.
     * @param \stdClass|null $instance The instance the form edits, the saved one by default.
     * @param string $preset The scenario edit.php hands the form, as requested.
     * @return \stdClass|null The exported data, or null when the form refused the submission.
     */
    private function submit(array $values, array $omit = [], ?\stdClass $instance = null, string $preset = ''): ?\stdClass {
        $instance = $instance ?? $this->instance;
        /* The form's constructor checks the sesskey through confirm_sesskey(), which reads the
           request, and the PHPUnit harness does not reset $_POST between tests, so it is put
           back afterwards. */
        $hadsesskey = array_key_exists('sesskey', $_POST);
        $previoussesskey = $_POST['sesskey'] ?? null;
        $_POST['sesskey'] = sesskey();

        $submitted = array_merge([
            '_qf__enrol_apply_edit_form' => 1,
            'sesskey' => sesskey(),
            'name' => '',
            'status' => ENROL_INSTANCE_ENABLED,
            'customint5' => 0,
            'customint6' => 1,
            'roleid' => $this->instance->roleid,
            'preset' => $preset,
            'notify' => ['$@NONE@$'],
            'customint3' => '0',
            'customint4' => '0',
            'id' => (int) $instance->id,
            'courseid' => $this->course->id,
            'groupdecided' => \enrol_apply\local\groupquestion::DECIDER,
            'deciders' => '0',
        ], $values);
        $submitted = array_diff_key($submitted, array_flip($omit));

        try {
            $context = \context_course::instance($this->course->id);
            $form = new \enrol_apply_edit_form(
                null,
                [$instance, $this->plugin, $context, $preset],
                'post',
                '',
                null,
                true,
                $submitted
            );
            $data = $form->get_data();
        } finally {
            if ($hadsesskey) {
                $_POST['sesskey'] = $previoussesskey;
            } else {
                unset($_POST['sesskey']);
            }
        }

        return $data;
    }

    /**
     * The group key mode accepts the three known values and nothing else.
     *
     * @return void
     */
    public function test_the_group_key_mode_is_validated(): void {
        $form = $this->form();

        foreach (['', 'any', 'required', 'review'] as $mode) {
            $errors = $form->validation($this->submission(['customchar1' => $mode]), []);
            $this->assertArrayNotHasKey('customchar1', $errors, "mode '{$mode}' should be accepted");
        }
        foreach (['ANY', 'listed', 'Required', 'REVIEW'] as $mode) {
            $errors = $form->validation($this->submission(['customchar1' => $mode]), []);
            $this->assertArrayHasKey('customchar1', $errors, "mode '{$mode}' should be refused");
        }
    }

    /**
     * The admission notice switch accepts "no" and "yes" and nothing else, and a real submission
     * stores what was chosen.
     *
     * @return void
     */
    public function test_the_admission_notice_switch_is_validated_and_saved(): void {
        $form = $this->form();

        foreach (['', '1'] as $value) {
            $errors = $form->validation($this->submission(['customchar2' => $value]), []);
            $this->assertArrayNotHasKey('customchar2', $errors, "'{$value}' should be accepted");
        }
        foreach (['0', 'yes', '2'] as $value) {
            $errors = $form->validation($this->submission(['customchar2' => $value]), []);
            $this->assertArrayHasKey('customchar2', $errors, "'{$value}' should be refused");
        }

        $data = $this->submit([
            'groupdecided' => \enrol_apply\local\groupquestion::KEY,
            'customchar1' => 'required',
            'customchar2' => '1',
        ]);
        $this->assertNotNull($data);
        $this->assertSame('required', $data->customchar1);
        $this->assertSame('1', $data->customchar2);
    }

    /**
     * The key-only warnings, read on their own.
     *
     * @param bool $review Whether to read them as the reviewed key-only mode would.
     * @return string Their HTML, or '' when there are none.
     */
    private function key_only_warnings(bool $review = false): string {
        $method = new \ReflectionMethod(\enrol_apply_edit_form::class, 'key_only_warnings');
        return $method->invoke($this->form(), $this->instance, $review);
    }

    /**
     * Key-only mode is warned about a course whose groups carry no key, and about applications
     * still waiting on the instance, each only while it holds.
     *
     * Changes that must make it fail: warning whatever the groups, or not counting the waiting
     * applications.
     *
     * @return void
     */
    public function test_key_only_mode_is_warned_about_no_keys_and_waiting_applications(): void {
        $nokeys = s(get_string('keyonlynokeys', 'enrol_apply'));
        $this->assertStringContainsString($nokeys, $this->key_only_warnings());

        $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'A', 'enrolmentkey' => 'Sekret-A1']);
        $this->assertSame('', $this->key_only_warnings(), 'the control: a keyed group, nothing waiting');

        $this->plugin->enrol_user($this->instance, $this->getDataGenerator()->create_user()->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $this->plugin->enrol_user($this->instance, $this->getDataGenerator()->create_user()->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $html = $this->key_only_warnings();
        $this->assertStringContainsString(s(get_string('keyonlypending', 'enrol_apply', 2)), $html);
        $this->assertStringNotContainsString($nokeys, $html);
    }

    /**
     * The reviewed mode is warned about no keys, worded for applying, and counts only the waiting
     * applications that have no key.
     *
     * Changes that must make it fail: the "enrol" wording kept for this mode, or the keyed
     * applications counted among the ones the mode does not decide.
     *
     * @return void
     */
    public function test_reviewed_key_only_mode_is_warned_in_words_of_applying(): void {
        global $DB;

        $nokeys = s(get_string('keyreviewnokeys', 'enrol_apply'));
        $html = $this->key_only_warnings(true);
        $this->assertStringContainsString($nokeys, $html);
        $this->assertStringNotContainsString(s(get_string('keyonlynokeys', 'enrol_apply')), $html);

        $group = $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'A',
            'enrolmentkey' => 'Sekret-A1',
        ]);
        $this->assertSame('', $this->key_only_warnings(true), 'the control: a keyed group, nothing waiting');

        $keyless = $this->getDataGenerator()->create_user();
        $keyed = $this->getDataGenerator()->create_user();
        foreach ([$keyless, $keyed] as $user) {
            $this->plugin->enrol_user($this->instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
        }
        \enrol_apply\local\submission::create(
            $this->instance,
            (int) $keyed->id,
            (int) $DB->get_field(
                'user_enrolments',
                'id',
                ['enrolid' => $this->instance->id, 'userid' => $keyed->id],
                MUST_EXIST
            ),
            (object) [],
            (int) $group->id,
            \enrol_apply\local\submission::KEY_FOR_REVIEW
        );

        $html = $this->key_only_warnings(true);
        $this->assertStringContainsString(s(get_string('keyreviewpending', 'enrol_apply', 1)), $html, 'only the keyless one');
        $this->assertStringNotContainsString(s(get_string('keyreviewpending', 'enrol_apply', 2)), $html);
        $this->assertStringNotContainsString(s(get_string('keyonlypending', 'enrol_apply', 1)), $html);
        $this->assertStringContainsString(
            s(get_string('keyonlypending', 'enrol_apply', 2)),
            $this->key_only_warnings(),
            'the control: key only counts every waiting application'
        );
    }

    /**
     * The reviewed mode is warned when every keyed group is full, worded for applying.
     *
     * @return void
     */
    public function test_reviewed_key_only_mode_is_warned_when_every_keyed_group_is_full(): void {
        if (!class_exists('\local_groupdist\seats')) {
            $this->markTestSkipped('local_groupdist with \local_groupdist\seats is not installed');
        }
        \local_groupdist\local\fields::reset_field_cache();
        \local_groupdist\local\fields::ensure_fields_exist();
        \local_groupdist\local\fields::reset_field_cache();
        $group = $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'A',
            'enrolmentkey' => 'Sekret-A1',
        ]);
        $allfull = s(get_string('keyreviewallfull', 'enrol_apply'));
        $this->assertStringNotContainsString($allfull, $this->key_only_warnings(true), 'the control: it has a seat');

        $handler = \core_group\customfield\group_handler::create();
        $handler->instance_form_save((object) ['id' => $group->id, 'customfield_groupdist_seats' => 0]);
        $html = $this->key_only_warnings(true);
        $this->assertStringContainsString($allfull, $html);
        $this->assertStringNotContainsString(s(get_string('keyonlyallfull', 'enrol_apply')), $html);
    }

    /**
     * With local_groupdist, key-only mode is warned when every keyed group is full, and not while
     * one still has a seat.
     *
     * @return void
     */
    public function test_key_only_mode_is_warned_when_every_keyed_group_is_full(): void {
        if (!class_exists('\local_groupdist\seats')) {
            $this->markTestSkipped('local_groupdist with \local_groupdist\seats is not installed');
        }
        \local_groupdist\local\fields::reset_field_cache();
        \local_groupdist\local\fields::ensure_fields_exist();
        \local_groupdist\local\fields::reset_field_cache();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $full = $generator->create_group(['courseid' => $this->course->id, 'name' => 'A', 'enrolmentkey' => 'Sekret-A1']);
        $open = $generator->create_group(['courseid' => $this->course->id, 'name' => 'B', 'enrolmentkey' => 'Sekret-B1']);
        $handler = \core_group\customfield\group_handler::create();
        $handler->instance_form_save((object) ['id' => $full->id, 'customfield_groupdist_seats' => 0]);
        $allfull = s(get_string('keyonlyallfull', 'enrol_apply'));

        $this->assertStringNotContainsString($allfull, $this->key_only_warnings(), 'the control: B has a seat');

        $handler->instance_form_save((object) ['id' => $open->id, 'customfield_groupdist_seats' => 0]);
        $this->assertStringContainsString($allfull, $this->key_only_warnings());
    }

    /**
     * Whether the course's self enrolment accepts the same group keys, which follows enrol_self's
     * own rule: an enabled instance with a key of its own and group keys switched on.
     *
     * Changes that must make it fail: ignoring the group keys option or the instance's key.
     *
     * @return void
     */
    public function test_self_enrolment_with_group_keys_is_detected(): void {
        $method = new \ReflectionMethod(\enrol_apply_edit_form::class, 'self_enrolment_takes_group_keys');
        $self = enrol_get_plugin('self');
        $id = $self->add_instance($this->course, [
            'password' => 'course-key',
            'customint1' => 0,
            'status' => ENROL_INSTANCE_ENABLED,
        ]);

        $this->assertFalse($method->invoke($this->form(), (int) $this->course->id), 'the control: group keys off');

        global $DB;
        $DB->set_field('enrol', 'customint1', 1, ['id' => $id]);
        $this->assertTrue($method->invoke($this->form(), (int) $this->course->id));

        $DB->set_field('enrol', 'password', '', ['id' => $id]);
        $this->assertFalse(
            $method->invoke($this->form(), (int) $this->course->id),
            'no key of its own: enrol_self ignores group keys'
        );
    }

    /**
     * The summary under the option, read on its own rather than from the whole page: the group
     * chooser above it lists every group name too.
     *
     * @return string The summary's HTML.
     */
    private function group_key_summary(): string {
        $method = new \ReflectionMethod(\enrol_apply_edit_form::class, 'group_key_summary');
        return $method->invoke($this->form(), (int) $this->course->id, \context_course::instance($this->course->id));
    }

    /**
     * The summary names the groups that carry a key, flags a shared and an untypable key, and
     * never prints a key.
     *
     * Changes that must make it fail: passing the key into the template, or listing keyless
     * groups.
     *
     * @return void
     */
    public function test_the_summary_names_keyed_groups_and_never_a_key(): void {
        $generator = $this->getDataGenerator();
        $generator->create_group(['courseid' => $this->course->id, 'name' => 'Keyless', 'enrolmentkey' => '']);
        $generator->create_group(['courseid' => $this->course->id, 'name' => 'R&D Turma', 'enrolmentkey' => 'Sekret-A1']);
        $generator->create_group(['courseid' => $this->course->id, 'name' => 'Twin', 'enrolmentkey' => 'Sekret-A1']);
        $generator->create_group(['courseid' => $this->course->id, 'name' => 'Padded', 'enrolmentkey' => 'Sekret-B2 ']);

        $html = $this->group_key_summary();

        $this->assertStringContainsString('R&amp;D Turma', $html);
        // The plain spelling went in, so the ampersand is escaped once, not twice.
        $this->assertStringNotContainsString('R&amp;amp;D', $html);
        $this->assertStringContainsString('Twin', $html);
        $this->assertStringContainsString('Padded', $html);
        $this->assertStringNotContainsString('Keyless', $html);
        $this->assertStringNotContainsString('Sekret', $html);
        $this->assertStringContainsString(s(get_string('groupkeyshadowed', 'enrol_apply', 'R&D Turma')), $html);
        $this->assertStringContainsString(s(get_string('groupkeyuntypable', 'enrol_apply')), $html);
        $this->assertStringNotContainsString(s(get_string('groupkeysnone', 'enrol_apply')), $html);
    }

    /**
     * The summary leaves out a keyed group the reader may not see by core's visibility rule, and
     * the note naming it as the one that admits instead; a reader holding viewhiddengroups gets
     * both.
     *
     * Changes that must make it fail: listing a group the summary withheld the name of, or naming
     * a withheld group as the one that admits.
     *
     * @return void
     */
    public function test_the_summary_leaves_out_a_group_the_reader_may_not_see(): void {
        $generator = $this->getDataGenerator();
        $generator->create_group(['courseid' => $this->course->id, 'name' => 'Turma Open', 'enrolmentkey' => 'K-1']);
        $generator->create_group([
            'courseid' => $this->course->id,
            'name' => 'Turma Covert',
            'enrolmentkey' => 'K-2',
            'visibility' => GROUPS_VISIBILITY_NONE,
        ]);
        $generator->create_group(['courseid' => $this->course->id, 'name' => 'Turma Later', 'enrolmentkey' => 'K-2']);
        $roleid = $generator->create_role(['shortname' => 'applyclerk']);
        assign_capability('enrol/apply:config', CAP_ALLOW, $roleid, \context_system::instance());
        $clerk = $generator->create_user();
        $generator->enrol_user($clerk->id, $this->course->id, 'applyclerk');

        $form = $this->form();
        $method = new \ReflectionMethod(\enrol_apply_edit_form::class, 'group_key_summary');
        $this->setUser($clerk);
        $html = $method->invoke($form, (int) $this->course->id, \context_course::instance($this->course->id));

        $this->assertStringContainsString('Turma Open', $html);
        $this->assertStringContainsString('Turma Later', $html);
        $this->assertStringNotContainsString('Covert', $html);
        $this->assertSame(2, substr_count($html, '<li'), 'two groups listed, no entry for the withheld one');
        $this->assertStringNotContainsString(s(get_string('groupkeyshadowed', 'enrol_apply', '')), $html);
        $this->assertStringNotContainsString(s(get_string('groupkeysnone', 'enrol_apply')), $html);

        // The control: a reader who may see hidden groups reads the group and the note naming it.
        $this->setAdminUser();
        $html = $method->invoke($form, (int) $this->course->id, \context_course::instance($this->course->id));
        $this->assertStringContainsString('Turma Covert', $html);
        $this->assertStringContainsString(s(get_string('groupkeyshadowed', 'enrol_apply', 'Turma Covert')), $html);
    }

    /**
     * A group named "0" still shows as the one that admits: the template keys the note on a
     * flag, because Mustache reads the string "0" as false.
     *
     * @return void
     */
    public function test_a_group_named_zero_is_named_as_the_one_that_admits(): void {
        $generator = $this->getDataGenerator();
        $generator->create_group(['courseid' => $this->course->id, 'name' => '0', 'enrolmentkey' => 'Same-1']);
        $generator->create_group(['courseid' => $this->course->id, 'name' => 'Later', 'enrolmentkey' => 'Same-1']);

        $this->assertStringContainsString(s(get_string('groupkeyshadowed', 'enrol_apply', '0')), $this->group_key_summary());
    }

    /**
     * With no keyed group, the summary says every application is still reviewed.
     *
     * @return void
     */
    public function test_the_summary_warns_when_no_group_has_a_key(): void {
        $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Keyless']);

        $this->assertStringContainsString(s(get_string('groupkeysnone', 'enrol_apply')), $this->group_key_summary());
    }

    /**
     * With local_groupdist installed the summary shows each keyed group's seats and marks a
     * full one, whose key admits nobody now.
     *
     * @return void
     */
    public function test_the_summary_shows_seats_when_groupdist_is_installed(): void {
        if (!class_exists('\local_groupdist\seats')) {
            $this->markTestSkipped('local_groupdist with \local_groupdist\seats is not installed');
        }
        // Its Seats field is not provisioned on a site built by an initial install; see
        // groupseats_test::require_groupdist().
        \local_groupdist\local\fields::reset_field_cache();
        \local_groupdist\local\fields::ensure_fields_exist();
        \local_groupdist\local\fields::reset_field_cache();
        $generator = $this->getDataGenerator();
        $full = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Full', 'enrolmentkey' => 'K-1']);
        $generator->create_group(['courseid' => $this->course->id, 'name' => 'Open', 'enrolmentkey' => 'K-2']);
        \core_group\customfield\group_handler::create()->instance_form_save((object) [
            'id' => $full->id,
            'customfield_groupdist_seats' => 1,
        ]);
        groups_add_member($full->id, $generator->create_and_enrol($this->course)->id);

        $html = $this->group_key_summary();

        $this->assertStringContainsString(
            s(get_string('groupseatsof', 'enrol_apply', (object) ['members' => 1, 'seats' => 1])),
            $html
        );
        $this->assertStringContainsString(s(get_string('groupseatsnolimit', 'enrol_apply', 0)), $html);
        $this->assertSame(1, substr_count($html, s(get_string('groupkeyadmitsnobody', 'enrol_apply'))));
    }

    /**
     * Past the review page's threshold the summary folds: a count, the groups needing attention
     * outside the fold, and every group inside a closed accordion; at the threshold it does not.
     *
     * Changes that must make it fail: folding at the threshold or never, or the attention list
     * missing a key nobody can type or holding every group.
     *
     * @return void
     */
    public function test_the_summary_folds_past_the_threshold(): void {
        $generator = $this->getDataGenerator();
        $threshold = \enrol_apply\local\groupfilter::THRESHOLD;
        for ($i = 1; $i <= $threshold; $i++) {
            $generator->create_group(['courseid' => $this->course->id, 'name' => "Turma {$i}", 'enrolmentkey' => "Key-{$i}"]);
        }

        $html = $this->group_key_summary();
        $this->assertStringNotContainsString('enrol_apply_groupkeys_body', $html);
        $this->assertStringContainsString('Turma ' . $threshold, $html);

        $generator->create_group(['courseid' => $this->course->id, 'name' => 'Padded', 'enrolmentkey' => 'Key-padded ']);
        $html = $this->group_key_summary();

        $this->assertMatchesRegularExpression(
            '~class="accordion-collapse collapse" id="enrol_apply_groupkeys_body"~',
            $html,
            'the whole list folds, closed'
        );
        $this->assertStringContainsString(s(get_string('groupkeyscount', 'enrol_apply', $threshold + 1)), $html);
        $this->assertStringContainsString(s(get_string('groupkeysattentionone', 'enrol_apply')), $html);
        $this->assertSame(1, preg_match('~data-region="groupkeys-attention">(.*?)</ul>~s', $html, $attention));
        $this->assertSame(1, substr_count($attention[1], '<li'));
        $this->assertStringContainsString('Padded', $attention[1]);
        // Every group, the attention one included, is in the folded list.
        $this->assertSame(1, preg_match('~id="enrol_apply_groupkeys_body"(.*)~s', $html, $folded));
        $this->assertSame($threshold + 1, substr_count($folded[1], '<li'));
    }

    /**
     * A decider's name reaches the notification list escaped exactly once.
     *
     * element-select.mustache renders an option's text through a triple stash.
     *
     * @return void
     */
    public function test_a_deciders_name_is_escaped_once_in_the_notification_list(): void {
        global $DB;

        /* Written straight to {user}, as an external directory sync may store it: the user
           generator cleans a name of anything that looks like a tag. */
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $DB->update_record('user', (object) ['id' => $teacher->id, 'firstname' => 'R&D', 'lastname' => '<Lab>']);
        $teacher = \core_user::get_user($teacher->id, '*', MUST_EXIST);

        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($this->form());
        $texts = [];
        foreach ($mform->getElement('notify')->_options as $option) {
            $texts[(string) $option['attr']['value']] = $option['text'];
        }

        $this->assertSame(s(fullname($teacher)), $texts[(string) $teacher->id] ?? null);
        $this->assertStringContainsString('R&amp;D &lt;Lab&gt;', $texts[(string) $teacher->id]);
    }

    /**
     * The QuickForm the edit form builds for the instance.
     *
     * @return \MoodleQuickForm The form.
     */
    private function quickform(): \MoodleQuickForm {
        return (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($this->form());
    }

    /**
     * Outside separate groups "Who decides" is shown frozen, and a save keeps the stored answer
     * whether the post carries another one or none; in separate groups the posted answer is saved.
     *
     * The stored answer is the one that matters if the course later switches to separate groups,
     * so a save of an unrelated setting must not reset it.
     *
     * Changes that must make it fail: the freeze dropped, a freeze that posts its value (a forged
     * post then wins), or the freeze applied in separate groups too.
     *
     * @return void
     */
    public function test_a_frozen_deciders_keeps_its_stored_value_on_save(): void {
        global $DB;

        $options = \enrol_apply\local\instanceoptions::class;
        $enrolid = (int) $this->instance->id;
        $options::set($enrolid, ['deciders' => $options::DECIDERS_ALLGROUPS]);

        $this->assertTrue($this->quickform()->getElement('deciders')->isFrozen());

        $data = $this->submit(['deciders' => (string) $options::DECIDERS_GROUP]);
        $this->assertNotNull($data);
        $this->assertEquals($options::DECIDERS_ALLGROUPS, $data->deciders, 'a posted answer does not replace the stored one');
        \enrol_apply\local\groupquestion::save($enrolid, $data);
        $this->assertSame($options::DECIDERS_ALLGROUPS, $options::get($enrolid)->deciders);

        $data = $this->submit([], ['deciders']);
        $this->assertNotNull($data);
        $this->assertEquals($options::DECIDERS_ALLGROUPS, $data->deciders, 'nothing posted keeps the stored answer');

        // The control: in separate groups the answer is the teacher's to change.
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $this->course->id]);
        $this->assertFalse($this->quickform()->getElement('deciders')->isFrozen());
        $data = $this->submit(['deciders' => (string) $options::DECIDERS_GROUP]);
        $this->assertNotNull($data);
        $this->assertEquals($options::DECIDERS_GROUP, $data->deciders);
        \enrol_apply\local\groupquestion::save($enrolid, $data);
        $this->assertSame($options::DECIDERS_GROUP, $options::get($enrolid)->deciders);
    }

    /**
     * The note that "Who decides" changes nothing is under it in the two modes where that is
     * true, and absent in separate groups.
     *
     * Changes that must make it fail: the note dropped, or shown in separate groups.
     *
     * @return void
     */
    public function test_the_inert_note_follows_the_group_mode(): void {
        global $DB;

        $note = s(get_string('decidersinert', 'enrol_apply'));
        foreach ([NOGROUPS => true, VISIBLEGROUPS => true, SEPARATEGROUPS => false] as $mode => $shown) {
            $DB->set_field('course', 'groupmode', $mode, ['id' => $this->course->id]);
            $mform = $this->quickform();

            $this->assertSame($shown, $mform->elementExists('decidersinert'), "group mode {$mode}");
            $this->assertSame($shown, $mform->getElement('deciders')->isFrozen(), "group mode {$mode}");
            if ($shown) {
                $this->assertStringContainsString($note, $mform->getElement('decidersinert')->_text);
            }
        }
    }

    /**
     * Outside separate groups the "changes nothing" note is an information alert.
     *
     * Changes that must make it fail: the note rendered as a warning.
     *
     * @return void
     */
    public function test_the_inert_note_is_information(): void {
        global $DB;

        $DB->set_field('course', 'groupmode', NOGROUPS, ['id' => $this->course->id]);
        $html = $this->quickform()->getElement('decidersinert')->_text;

        $this->assertStringContainsString('alert-info', $html);
        $this->assertStringNotContainsString('alert-warning', $html);
        $this->assertStringNotContainsString('alert-danger', $html);
    }

    /**
     * The notification list offers "the deciders of the application's group" right after "everyone".
     *
     * Changes that must make it fail: the option dropped from the list.
     *
     * @return void
     */
    public function test_the_group_marker_is_offered_after_everyone(): void {
        $recipients = \enrol_apply\local\notifyrecipients::class;
        $options = $this->quickform()->getElement('notify')->_options;

        $this->assertSame(
            [$recipients::NONE, $recipients::ALL, $recipients::GROUP],
            array_map(static fn(array $option): string => (string) $option['attr']['value'], array_slice($options, 0, 3))
        );
        $this->assertSame(get_string('notifygroupdeciders', 'enrol_apply'), $options[2]['text']);
    }

    /**
     * The group marker picked with a person by name is saved as the marker followed by the id, and
     * the form opens with both selected again.
     *
     * Changes that must make it fail: the option dropped (the select exports only values it offers),
     * or the marker written after the ids.
     *
     * @return void
     */
    public function test_the_group_marker_and_a_named_person_round_trip(): void {
        global $DB;

        $recipients = \enrol_apply\local\notifyrecipients::class;
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $data = $this->submit(['notify' => [(string) $teacher->id, $recipients::GROUP]]);
        $this->assertNotNull($data);
        $this->assertEqualsCanonicalizing([(string) $teacher->id, $recipients::GROUP], array_map('strval', $data->notify));
        $stored = $recipients::from_form($data->notify);
        $this->assertSame($recipients::GROUP . ',' . $teacher->id, $stored);

        $DB->set_field('enrol', 'customtext3', $stored, ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
        $this->assertSame(
            [$recipients::GROUP, (string) $teacher->id],
            array_map('strval', $this->quickform()->getElement('notify')->getValue())
        );
    }

    /**
     * What the multi-select posts is stored by one rule: "everyone" swallows the rest, "nobody" is
     * dropped, and the group marker leads the people picked by name.
     *
     * Changes that must make it fail: "everyone" no longer winning, or the marker not written first.
     *
     * @return void
     */
    public function test_the_posted_recipients_are_stored_by_one_rule(): void {
        $recipients = \enrol_apply\local\notifyrecipients::class;

        $this->assertSame($recipients::ALL, $recipients::from_form([$recipients::GROUP, $recipients::ALL, '5']));
        $this->assertSame('', $recipients::from_form([$recipients::NONE]));
        $this->assertSame('', $recipients::from_form([]));
        $this->assertSame('5', $recipients::from_form([$recipients::NONE, '5']));
        $this->assertSame($recipients::GROUP . ',5,7', $recipients::from_form(['5', $recipients::GROUP, '7']));
        $this->assertSame($recipients::GROUP, $recipients::from_form([$recipients::GROUP, $recipients::NONE]));

        // And back: an empty value selects "nobody", anything else its items in stored order.
        $this->assertSame([$recipients::NONE], $recipients::to_form(''));
        $this->assertSame([$recipients::GROUP, '5'], $recipients::to_form($recipients::GROUP . ',5'));
    }

    /**
     * A new method's defaults, as edit.php hands them to the form.
     *
     * @return \stdClass The instance the form is built for.
     */
    private function new_instance(): \stdClass {
        $instance = (object) $this->plugin->get_instance_defaults();
        $instance->id = null;
        $instance->courseid = $this->course->id;

        return $instance;
    }

    /**
     * The QuickForm built for a method with a scenario requested, as edit.php builds it.
     *
     * @param string $preset The scenario as requested.
     * @param \stdClass|null $instance The instance, a new one by default.
     * @return \MoodleQuickForm The form.
     */
    private function preset_quickform(string $preset, ?\stdClass $instance = null): \MoodleQuickForm {
        $context = \context_course::instance($this->course->id);
        $form = new \enrol_apply_edit_form(null, [$instance ?? $this->new_instance(), $this->plugin, $context, $preset]);

        return (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
    }

    /**
     * The scenario cards of a form, rendered: its first element.
     *
     * @param \MoodleQuickForm $mform The form.
     * @return string The cards' HTML.
     */
    private function cards_html(\MoodleQuickForm $mform): string {
        $html = $mform->_elements[0]->toHtml();
        $this->assertStringContainsString('enrol_apply-presets', $html);

        return $html;
    }

    /**
     * One card's markup.
     *
     * @param string $html The cards' HTML.
     * @param string $key The scenario.
     * @return string From its data-preset attribute to the next card's.
     */
    private function card_html(string $html, string $key): string {
        $parts = explode('data-preset="', $html);
        foreach ($parts as $part) {
            if (str_starts_with($part, $key . '"')) {
                return $part;
            }
        }
        $this->fail("no card for {$key}");
    }

    /**
     * Save a new method the way edit.php does, from what the form exported.
     *
     * @param \stdClass $data The exported data.
     * @return int The new method's id.
     */
    private function save_new(\stdClass $data): int {
        $data->customtext3 = \enrol_apply\local\notifyrecipients::from_form((array) ($data->notify ?? []));
        $data->customchar1 = \enrol_apply\local\groupquestion::storage($data)->customchar1;
        $enrolid = (int) $this->plugin->add_instance($this->course, (array) $data);
        \enrol_apply\local\groupquestion::save($enrolid, $data);

        return $enrolid;
    }

    /**
     * Each scenario with the values it must put in the form, and the role it grants by id.
     *
     * @return array Scenario, values by element name and the role's short name ('' for the default),
     *     keyed by the scenario.
     */
    public static function scenario_provider(): array {
        $question = \enrol_apply\local\groupquestion::class;
        $options = \enrol_apply\local\instanceoptions::class;
        $recipients = \enrol_apply\local\notifyrecipients::class;

        return [
            'review' => ['review', [
                'groupdecided' => $question::DECIDER,
                'deciders' => $options::DECIDERS_GROUP,
                'notify' => [$recipients::ALL],
            ], ''],
            'classes' => ['classes', [
                'groupdecided' => $question::CHOICE,
                'groupchoicerequired' => $options::CHOICE_REQUIRED,
                'groupseats' => 1,
                'choicekey' => '',
                'deciders' => $options::DECIDERS_GROUP,
                'notify' => [$recipients::GROUP],
                'groupoffer' => [],
            ], ''],
            'key' => ['key', [
                'groupdecided' => $question::KEY,
                'customchar1' => 'any',
                'deciders' => $options::DECIDERS_GROUP,
                'notify' => [$recipients::GROUP],
            ], ''],
            'moderators' => ['moderators', [
                'groupdecided' => $question::CHOICE,
                'groupchoicerequired' => $options::CHOICE_REQUIRED,
                'groupseats' => 0,
                'choicekey' => '',
                'deciders' => $options::DECIDERS_ALLGROUPS,
                'notify' => [$recipients::ALL],
            ], 'editingteacher'],
        ];
    }

    /**
     * A scenario's values are the new method's form's initial data, the role of "Moderator
     * recruitment" included, and the Who decides section opens to show them where they differ from
     * the defaults.
     *
     * Read in a separate-groups course, where nothing is frozen. On a stock site the editing teacher is
     * the first offered role that decides.
     *
     * @param string $preset The scenario.
     * @param array $expected Its values by element name.
     * @param string $role The role's short name, '' for the site default.
     * @return void
     */
    #[DataProvider('scenario_provider')]
    public function test_each_scenario_fills_in_the_form(string $preset, array $expected, string $role): void {
        global $DB;

        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $this->course->id]);
        $defaults = $this->preset_quickform($preset)->_defaultValues;
        foreach ($expected as $name => $value) {
            $this->assertArrayHasKey($name, $defaults, $name);
            $this->assertEquals($value, $defaults[$name], $name);
        }
        $roleid = $role === ''
            ? (int) $this->plugin->get_config('roleid')
            : (int) $DB->get_field('role', 'id', ['shortname' => $role], MUST_EXIST);
        $this->assertEquals($roleid, $defaults['roleid']);

        // The control: the same form with no scenario holds the site defaults.
        $plain = $this->preset_quickform('')->_defaultValues;
        $this->assertSame(\enrol_apply\local\groupquestion::DECIDER, $plain['groupdecided']);
        $this->assertNotEquals($expected, array_intersect_key($plain, $expected));
    }

    /**
     * A scenario whose recipients or deciders differ from the defaults opens Who decides and who is
     * notified, closed by default outside separate groups; one that matches the defaults leaves it so.
     *
     * Changes that must make it fail: a scenario's values ignored by the section rule.
     *
     * @return void
     */
    public function test_a_scenarios_values_open_their_section(): void {
        $this->plugin->set_config('notifycoursebased', 1);

        $this->assertTrue($this->preset_quickform('')->_collapsibleElements['sectiondeciders'], 'closed by default');
        $this->assertTrue(
            $this->preset_quickform('review')->_collapsibleElements['sectiondeciders'],
            'everyone is the site default here'
        );
        $this->assertFalse($this->preset_quickform('classes')->_collapsibleElements['sectiondeciders'], 'the group marker');
        $this->assertFalse($this->preset_quickform('moderators')->_collapsibleElements['sectiondeciders'], 'all groups decide');
    }

    /**
     * Anything but a scenario's exact key builds the form a request with no scenario builds.
     *
     * Changes that must make it fail: a key matched without regard to case.
     *
     * @return void
     */
    public function test_an_unknown_scenario_is_no_scenario(): void {
        $plain = $this->preset_quickform('');
        foreach (['Classes', 'MODERATORS', 'keys', 'defaults'] as $requested) {
            $mform = $this->preset_quickform($requested);
            $this->assertEquals($plain->_defaultValues, $mform->_defaultValues, $requested);
            $this->assertSame('', $mform->getElement('preset')->getValue(), $requested);
            $this->assertStringNotContainsString('aria-current', $this->cards_html($mform), $requested);
        }

        // The control: the exact key is a scenario.
        $this->assertSame('classes', $this->preset_quickform('classes')->getElement('preset')->getValue());
    }

    /**
     * The cards are drawn above the first section of a new method only, and a scenario requested
     * for a saved method changes none of its values.
     *
     * Changes that must make it fail: the cards drawn for a saved method, or its values replaced.
     *
     * @return void
     */
    public function test_scenarios_are_offered_for_a_new_method_only(): void {
        $new = $this->preset_quickform('classes');
        $this->assertSame('header', $new->_elements[1]->getType(), 'the cards come before the first section');
        foreach (\enrol_apply\local\preset::KEYS as $key) {
            $this->assertStringContainsString('preset=' . $key, $this->cards_html($new));
        }
        $this->assertSame(\enrol_apply\local\groupquestion::CHOICE, $new->_defaultValues['groupdecided']);

        $saved = $this->preset_quickform('classes', $this->instance);
        foreach ($saved->_elements as $element) {
            if ($element->getType() === 'html') {
                $this->assertStringNotContainsString('enrol_apply-presets', $element->toHtml());
            }
        }
        $this->assertSame(\enrol_apply\local\groupquestion::DECIDER, $saved->_defaultValues['groupdecided']);
        $this->assertSame('', $saved->getElement('preset')->getValue());
    }

    /**
     * Choosing a scenario writes nothing; saving the form does.
     *
     * @return void
     */
    public function test_choosing_a_scenario_writes_nothing(): void {
        global $DB;

        $count = fn(): array => [
            $DB->count_records('enrol', ['courseid' => $this->course->id]),
            $DB->count_records('enrol_apply_options'),
        ];
        $before = $count();
        foreach (\enrol_apply\local\preset::KEYS as $key) {
            $this->cards_html($this->preset_quickform($key));
        }
        $this->assertSame($before, $count());

        // The control: the counts move when a method is saved.
        $data = $this->submit([], [], $this->new_instance(), 'review');
        $this->assertNotNull($data);
        $this->save_new($data);
        $this->assertSame([$before[0] + 1, $before[1] + 1], $count());
    }

    /**
     * A scenario's values, posted as the form shows them, are saved, and a frozen "Who decides" saves
     * the scenario's answer the screen showed rather than whatever is posted.
     *
     * @return void
     */
    public function test_saving_a_scenarios_values_stores_them(): void {
        global $DB;

        $options = \enrol_apply\local\instanceoptions::class;
        $recipients = \enrol_apply\local\notifyrecipients::class;
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $this->course->id]);

        $values = \enrol_apply\local\preset::values('classes', (int) $this->course->id, []);
        $data = $this->submit($values + ['groupoffer' => [$group->id]], [], $this->new_instance(), 'classes');
        $this->assertNotNull($data);
        $enrolid = $this->save_new($data);

        $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        $this->assertSame($recipients::GROUP, $instance->customtext3);
        $this->assertSame('', (string) $instance->customchar1);
        $stored = $options::get($enrolid);
        $this->assertSame($options::CHOICE_REQUIRED, $stored->groupchoice);
        $this->assertSame(1, $stored->groupseats);
        $this->assertSame($options::DECIDERS_GROUP, $stored->deciders);
        $this->assertSame([(int) $group->id], \enrol_apply\local\groupoffer::group_ids($enrolid));

        // Outside separate groups "Who decides" is frozen: the scenario's answer is what it saves.
        $DB->set_field('course', 'groupmode', NOGROUPS, ['id' => $this->course->id]);
        $editing = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $values = \enrol_apply\local\preset::values('moderators', (int) $this->course->id, [$editing]);
        $values['deciders'] = (string) $options::DECIDERS_GROUP;
        $data = $this->submit($values + ['groupoffer' => [$group->id]], [], $this->new_instance(), 'moderators');
        $this->assertNotNull($data);
        $enrolid = $this->save_new($data);

        $this->assertSame($options::DECIDERS_ALLGROUPS, $options::get($enrolid)->deciders);
        $this->assertSame(0, $options::get($enrolid)->groupseats);
        $this->assertEquals($editing, $DB->get_field('enrol', 'roleid', ['id' => $enrolid]));
        $this->assertSame($recipients::ALL, $DB->get_field('enrol', 'customtext3', ['id' => $enrolid]));
    }

    /**
     * The scenario rides in a hidden element, which edit.php reads back on the post that saves the
     * form or redisplays it after a validation error; a scenario leaves the offered groups to pick,
     * so its first post without one is refused.
     *
     * Changes that must make it fail: the element carrying nothing.
     *
     * @return void
     */
    public function test_the_scenario_rides_in_a_hidden_element(): void {
        $mform = $this->preset_quickform('moderators');
        $this->assertSame('hidden', $mform->getElementType('preset'));
        $this->assertSame('moderators', $mform->getElement('preset')->getValue());
        $this->assertMatchesRegularExpression('/name="preset"[^>]*value="moderators"/', $mform->getElement('preset')->toHtml());

        $values = \enrol_apply\local\preset::values('moderators', (int) $this->course->id, []);
        $this->assertNull($this->submit($values, [], $this->new_instance(), 'moderators'), 'no group offered yet');
    }

    /**
     * The cards built on groups state the course's group mode, say they need separate groups where
     * the course is not in them, and link the course settings only for who may change them.
     *
     * Changes that must make it fail: the link offered without moodle/course:update, or the need said
     * in a separate-groups course.
     *
     * @return void
     */
    public function test_the_prerequisite_line_follows_the_mode_and_the_capability(): void {
        global $DB;

        $link = '/course/edit.php?id=' . $this->course->id;
        $needs = s(get_string('presetneedsseparate', 'enrol_apply'));

        $html = $this->cards_html($this->preset_quickform(''));
        foreach (['classes', 'moderators'] as $key) {
            $card = $this->card_html($html, $key);
            $this->assertStringContainsString(s(get_string('groupsnone', 'group')), $card, $key);
            $this->assertStringContainsString($needs, $card, $key);
            $this->assertStringContainsString($link, $card, 'the control: an administrator may change them');
        }
        $this->assertStringNotContainsString($needs, $this->card_html($html, 'review'));

        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'teacher'));
        $this->assertFalse(has_capability('moodle/course:update', \context_course::instance($this->course->id)));
        $card = $this->card_html($this->cards_html($this->preset_quickform('')), 'classes');
        $this->assertStringContainsString($needs, $card);
        $this->assertStringNotContainsString($link, $card);

        $this->setAdminUser();
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $this->course->id]);
        $card = $this->card_html($this->cards_html($this->preset_quickform('')), 'classes');
        $this->assertStringContainsString(s(get_string('groupsseparate', 'group')), $card);
        $this->assertStringNotContainsString($needs, $card);
        $this->assertStringNotContainsString($link, $card);
    }

    /**
     * The chosen card alone is marked for assistive technology and on screen, its link reads
     * "Chosen", and the way back to the site defaults appears with it; a role name is escaped once.
     *
     * @return void
     */
    public function test_the_chosen_card_is_marked(): void {
        global $DB;

        $DB->set_field('role', 'name', 'R&D moderators', ['shortname' => 'editingteacher']);
        $html = $this->cards_html($this->preset_quickform('moderators'));

        $this->assertSame(1, substr_count($html, 'aria-current="true"'));
        $chosen = $this->card_html($html, 'moderators');
        $this->assertStringContainsString('aria-current="true"', $chosen);
        // The class sits on the card's own tag, just before its data-preset attribute: outside the slice.
        $marked = static fn(string $key): string => 'enrol_apply-preset-chosen" data-preset="' . $key . '"';
        $this->assertStringContainsString($marked('moderators'), $html);
        $this->assertStringContainsString(s(get_string('presetchosen', 'enrol_apply')), $chosen);
        $this->assertStringContainsString('R&amp;D moderators', $chosen);
        $this->assertStringNotContainsString('R&amp;amp;D', $chosen);
        foreach (['review', 'classes', 'key'] as $key) {
            $card = $this->card_html($html, $key);
            $this->assertStringNotContainsString($marked($key), $html, $key);
            $this->assertStringContainsString(s(get_string('presetuse', 'enrol_apply')), $card, $key);
        }
        $this->assertStringContainsString(s(get_string('presetclear', 'enrol_apply')), $html);

        // The control: with none chosen nothing is marked and there is no way back to offer.
        $html = $this->cards_html($this->preset_quickform(''));
        $this->assertStringNotContainsString('aria-current', $html);
        $this->assertStringNotContainsString(s(get_string('presetclear', 'enrol_apply')), $html);
    }
}
