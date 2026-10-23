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
     * The group key mode accepts the two known values and nothing else.
     *
     * @return void
     */
    public function test_the_group_key_mode_is_validated(): void {
        $form = $this->form();

        foreach (['', 'any'] as $mode) {
            $errors = $form->validation($this->submission(['customchar1' => $mode]), []);
            $this->assertArrayNotHasKey('customchar1', $errors, "mode '{$mode}' should be accepted");
        }
        foreach (['ANY', 'listed'] as $mode) {
            $errors = $form->validation($this->submission(['customchar1' => $mode]), []);
            $this->assertArrayHasKey('customchar1', $errors, "mode '{$mode}' should be refused");
        }
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
