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
}
