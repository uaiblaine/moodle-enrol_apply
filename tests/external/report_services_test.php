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
 * Tests for the course report's web services (stage R3).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\external;

use context_course;
use enrol_apply\local\reportpreference;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The three web services, called as a client calls them, through call_external_function().
 *
 * What they add to the engine (tests/reportbuilder/headless_report_test.php) is the door: the
 * course from the enrol instance, the capability, the report's own can_view(), and the returns
 * cleaned against their declared structure.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report_rows::class)]
#[CoversClass(report_summary::class)]
#[CoversClass(set_report_preference::class)]
final class report_services_test extends \advanced_testcase {
    /** @var \stdClass Course carrying the apply instance. */
    private $course;

    /** @var \stdClass The apply instance. */
    private $instance;

    /**
     * A course with an apply instance and two records, one pending.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $id = $plugin->add_instance($this->course, $plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        foreach ([submission::STATUS_PENDING, submission::STATUS_APPROVED] as $status) {
            $user = $this->getDataGenerator()->create_user();
            $DB->insert_record('enrol_apply_submission', (object) [
                'courseid' => $this->course->id,
                'userid' => $user->id,
                'enrolid' => $this->instance->id,
                'userenrolmentid' => 0,
                'comment' => '<b>Bold & < bare</b>',
                'status' => $status,
                'outcomemessage' => '',
                'timecreated' => time() - DAYSECS,
                'timedecided' => $status === submission::STATUS_PENDING ? 0 : time(),
                'decidedby' => 0,
            ]);
        }
        $_POST['sesskey'] = sesskey();
    }

    /**
     * Call a function as a client does.
     *
     * @param string $function The function.
     * @param array $args Its arguments.
     * @return array The response: error, data or exception.
     */
    private function call(string $function, array $args): array {
        $_POST['sesskey'] = sesskey();

        return \core_external\external_api::call_external_function($function, $args, true);
    }

    /**
     * A teacher in the course with the stock role, enrolled, the role granted the report here.
     *
     * @param int $status The teacher's own enrolment status.
     * @return \stdClass The teacher.
     */
    private function teacher(int $status = ENROL_USER_ACTIVE): \stdClass {
        global $DB;

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher', 'manual', 0, 0, $status);
        // The report is a manager's by archetype; this course grants it to its teachers.
        $role = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('enrol/apply:viewreports', CAP_ALLOW, $role, context_course::instance($this->course->id)->id, true);

        return $teacher;
    }

    /**
     * A teacher reads the rows and the summary; the cells survive the return cleaning, a bare
     * "<" included.
     *
     * Changes that must make it fail: a return field of the wrong type (the response is refused
     * whole); the capability read anywhere but in the method's course.
     *
     * @return void
     */
    public function test_a_teacher_reads_the_rows_and_the_summary(): void {
        $this->setUser($this->teacher());

        $response = $this->call('enrol_apply_report_rows', ['enrolid' => $this->instance->id]);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $this->assertSame(2, $response['data']['total']);
        $comments = array_column(array_merge(...array_column($response['data']['rows'], 'cells')), 'html', 'key');
        $this->assertStringContainsString('&lt; bare', $comments['comment']);

        // The filters carry their counts by default; a page turn asks for none, and the answer says so.
        $this->assertTrue($response['data']['counted']);
        $status = array_column($response['data']['filters'], null, 'name')['submission:status'];
        $this->assertSame('choice', $status['facet']);
        $this->assertSame(2, array_sum(array_column($status['options'], 'count')));
        $this->assertSame(['decisions', 'answers', 'groups'], array_column($response['data']['presets'], 'name'));
        $response = $this->call('enrol_apply_report_rows', ['enrolid' => $this->instance->id, 'page' => 0, 'facets' => false]);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $this->assertFalse($response['data']['counted']);
        $status = array_column($response['data']['filters'], null, 'name')['submission:status'];
        $this->assertSame([-1], array_values(array_unique(array_column($status['options'], 'count'))));

        $response = $this->call('enrol_apply_report_summary', ['enrolid' => $this->instance->id, 'measure' => 'resolution']);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $this->assertSame(2, $response['data']['total']);
        $this->assertSame(1, $response['data']['open']);
        $this->assertSame('resolution', $response['data']['measure']);
    }

    /**
     * Every door refuses who the report refuses: a reader without the capability, a teacher whose
     * own enrolment is suspended, a guest, and an id that names another enrolment method.
     *
     * Changes that must make it fail: the capability check removed; the report's can_view() not
     * required; the instance looked up without its plugin.
     *
     * @return void
     */
    public function test_each_service_refuses_who_the_report_refuses(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $suspended = $this->teacher(ENROL_USER_SUSPENDED);
        $manual = (int) $DB->get_field('enrol', 'id', ['courseid' => $this->course->id, 'enrol' => 'manual']);

        $calls = [
            'enrol_apply_report_rows' => [],
            'enrol_apply_report_summary' => [],
            'enrol_apply_set_report_preference' => ['columns' => ['outcome']],
        ];
        foreach ($calls as $function => $args) {
            foreach (['student' => $student, 'suspended' => $suspended] as $who => $user) {
                $this->setUser($user);
                $response = $this->call($function, ['enrolid' => $this->instance->id] + $args);
                $this->assertTrue($response['error'], "{$function} {$who}");
            }
            $this->setGuestUser();
            $this->assertTrue($this->call($function, ['enrolid' => $this->instance->id] + $args)['error'], "{$function} guest");

            $this->setAdminUser();
            $this->assertTrue($this->call($function, ['enrolid' => $manual] + $args)['error'], "{$function} manual");
            $this->assertFalse($this->call($function, ['enrolid' => $this->instance->id] + $args)['error'], "{$function} admin");
        }
        $this->assertNull(get_user_preferences(reportpreference::NAME, null, $student->id));
    }

    /**
     * A teacher holding the report in another course cannot read this course's report by its
     * method's id.
     *
     * Changes that must make it fail: the course taken from anything but the method.
     *
     * @return void
     */
    public function test_a_teacher_of_another_course_reads_nothing_here(): void {
        global $DB;

        $other = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $other->id, 'editingteacher');
        $role = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('enrol/apply:viewreports', CAP_ALLOW, $role, context_course::instance($other->id)->id, true);
        $this->setUser($teacher);

        foreach (['enrol_apply_report_rows', 'enrol_apply_report_summary', 'enrol_apply_set_report_preference'] as $function) {
            $this->assertTrue($this->call($function, ['enrolid' => $this->instance->id])['error'], $function);
        }
    }

    /**
     * The preference is stored cleaned, read back by the rows, and forgotten on request.
     *
     * Changes that must make it fail: the keys or widths stored uncleaned; usedefault ignored.
     *
     * @return void
     */
    public function test_the_preference_is_stored_cleaned_and_read_by_the_rows(): void {
        $teacher = $this->teacher();
        $this->setUser($teacher);

        $response = $this->call('enrol_apply_set_report_preference', [
            'enrolid' => $this->instance->id,
            'columns' => ['outcome', 'outcome', 'Bad Key', 'wait'],
            'widths' => [
                ['key' => 'wait', 'width' => 5000],
                ['key' => 'outcome', 'width' => 10],
                ['key' => '<x>', 'width' => 99],
            ],
        ]);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $this->assertSame(['outcome', 'wait'], $response['data']['columns']);
        $this->assertSame(
            [
                ['key' => 'wait', 'width' => reportpreference::MAX_WIDTH],
                ['key' => 'outcome', 'width' => reportpreference::MIN_WIDTH],
            ],
            $response['data']['widths']
        );

        $rows = $this->call('enrol_apply_report_rows', ['enrolid' => $this->instance->id])['data'];
        $this->assertSame(['outcome', 'wait'], $rows['preference']['columns']);
        $this->assertSame(['applicant', 'identity:email', 'outcome', 'wait'], array_column($rows['rows'][0]['cells'], 'key'));

        $response = $this->call('enrol_apply_set_report_preference', ['enrolid' => $this->instance->id, 'usedefault' => true]);
        $this->assertTrue($response['data']['usedefault']);
        $this->assertNull(get_user_preferences(reportpreference::NAME, null, $teacher->id));
    }
}
