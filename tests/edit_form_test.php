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
     * @return \stdClass|null The exported data, or null when the form refused the submission.
     */
    private function submit(array $values): ?\stdClass {
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
            'notify' => ['$@NONE@$'],
            'customint3' => '0',
            'customint4' => '0',
            'id' => $this->instance->id,
            'courseid' => $this->course->id,
        ], $values);

        try {
            $context = \context_course::instance($this->course->id);
            $form = new \enrol_apply_edit_form(
                null,
                [$this->instance, $this->plugin, $context],
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

        $data = $this->submit(['customchar1' => 'required', 'customchar2' => '1']);
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
}
