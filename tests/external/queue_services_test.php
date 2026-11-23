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
 * Tests for the approval queue's web services (stage 1 of the queue as a report).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\external;

use context_course;
use enrol_apply\local\queuepreference;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * The three services, called as a client calls them, through call_external_function().
 *
 * What they add to the engine (tests/local/queueengine_test.php) is the door: the course from the
 * method, course access, guests refused, the queue's own capability asked as manage.php asks it,
 * and the returns cleaned against their declared structure.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(queue_rows::class)]
#[CoversClass(queue_summary::class)]
#[CoversClass(set_queue_preference::class)]
final class queue_services_test extends \advanced_testcase {
    /** @var \stdClass Course carrying the apply method. */
    private $course;

    /** @var \stdClass The apply method. */
    private $instance;

    /** @var \stdClass The applicant of the one application not the teacher's. */
    private $applicant;

    /** @var array The three services, each with the arguments it needs beside the method. */
    private const CALLS = [
        'enrol_apply_queue_rows' => [],
        'enrol_apply_queue_summary' => [],
        'enrol_apply_set_queue_preference' => ['columns' => ['wait']],
    ];

    /**
     * A course with an apply method and two applications, one of them the editing teacher's own.
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

        $user = $this->getDataGenerator()->create_user();
        $this->applicant = $user;
        $plugin->enrol_user($this->instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = (int) $DB->get_field('user_enrolments', 'id', ['userid' => $user->id, 'enrolid' => $id], MUST_EXIST);
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $user->id,
            'enrolid' => $id,
            'userenrolmentid' => $ueid,
            'comment' => '<b>Bold & < bare</b>',
            'status' => submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => time() - DAYSECS,
            'timedecided' => 0,
            'decidedby' => 0,
        ]);
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
     * A user enrolled in a course with a role made for the test, holding the given capabilities.
     *
     * @param \stdClass $course The course.
     * @param array $capabilities Capability names granted at the course.
     * @return \stdClass The user.
     */
    private function reader(\stdClass $course, array $capabilities): \stdClass {
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $role = $this->getDataGenerator()->create_role();
        $context = context_course::instance($course->id);
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $role, $context->id, true);
        }
        role_assign($role, $user->id, $context->id);

        return $user;
    }

    /**
     * A decider reads the rows and the summary, writes the preference, and the cells survive the
     * return cleaning, a bare "<" included.
     *
     * Changes that must make it fail: a return field of the wrong type (the response is refused
     * whole); the queue's capability read anywhere but in the method's course.
     *
     * @return void
     */
    public function test_a_decider_reads_the_queue_and_keeps_a_grid(): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));

        $response = $this->call('enrol_apply_queue_rows', ['enrolid' => $this->instance->id]);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $this->assertSame(1, $response['data']['total']);
        $this->assertSame(['all' => 1, 'pending' => 1, 'deferred' => 0], $response['data']['counts']);
        $this->assertTrue($response['data']['rows'][0]['selectable']);
        $cells = array_column($response['data']['rows'][0]['cells'], 'html', 'key');
        $this->assertStringContainsString('&lt; bare', $cells['comment']);
        $this->assertSame(['everything', 'glance', 'answers'], array_column($response['data']['presets'], 'name'));

        $response = $this->call('enrol_apply_queue_summary', ['enrolid' => $this->instance->id]);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $this->assertSame(1, $response['data']['awaiting']);

        $response = $this->call('enrol_apply_set_queue_preference', [
            'enrolid' => $this->instance->id,
            'columns' => ['comment', 'not a key', 'comment'],
            'widths' => [['key' => 'comment', 'width' => 99999]],
        ]);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $this->assertSame(['comment'], $response['data']['columns']);
        $this->assertSame([['key' => 'comment', 'width' => 1600]], $response['data']['widths']);
        $response = $this->call('enrol_apply_queue_rows', ['enrolid' => $this->instance->id]);
        $this->assertSame(['comment'], $response['data']['preference']['columns']);
        $this->assertSame(['comment'], array_column($response['data']['rows'][0]['cells'], 'key'));

        $response = $this->call('enrol_apply_set_queue_preference', ['enrolid' => $this->instance->id, 'usedefault' => true]);
        $this->assertTrue($response['data']['usedefault']);
        $this->assertNull(get_user_preferences(queuepreference::NAME));
    }

    /**
     * A viewer reads the queue and selects nothing; the summary and the preference are theirs too.
     *
     * Changes that must make it fail: a viewer refused (allowed read as deciding alone); a viewer's
     * rows selectable.
     *
     * @return void
     */
    public function test_a_viewer_reads_and_selects_nothing(): void {
        $this->setUser($this->reader($this->course, ['enrol/apply:viewapplications']));

        foreach (self::CALLS as $function => $args) {
            $response = $this->call($function, ['enrolid' => $this->instance->id] + $args);
            $this->assertFalse($response['error'], $function . ' ' . json_encode($response['exception'] ?? null));
        }
        $response = $this->call('enrol_apply_queue_rows', ['enrolid' => $this->instance->id]);
        $this->assertFalse($response['data']['candecide']);
        $this->assertFalse($response['data']['rows'][0]['selectable']);
    }

    /**
     * Every door refuses who the queue page refuses: a guest, a user with no capability, a holder
     * of the REPORT's capability alone, a decider of another course, a call naming a negative id or
     * another enrolment method.
     *
     * 0 named no method here until stage W1; it is now the wide queue, whose doors
     * test_the_wide_queue_answers_its_three_readers_and_no_one_else holds, so the call that used to be
     * refused for naming no method is a negative id.
     *
     * Changes that must make it fail: the queue's capability not asked (listing_scope()'s allowed
     * ignored); the method looked up without its plugin; the course taken from anything but the
     * method.
     *
     * @return void
     */
    public function test_each_service_refuses_who_the_queue_refuses(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $reportonly = $this->reader($this->course, ['enrol/apply:viewreports']);
        $other = $this->getDataGenerator()->create_course();
        $elsewhere = $this->getDataGenerator()->create_and_enrol($other, 'editingteacher');
        $manual = (int) $DB->get_field('enrol', 'id', ['courseid' => $this->course->id, 'enrol' => 'manual']);

        foreach (self::CALLS as $function => $args) {
            foreach (['student' => $student, 'reportonly' => $reportonly, 'elsewhere' => $elsewhere] as $who => $user) {
                $this->setUser($user);
                $response = $this->call($function, ['enrolid' => $this->instance->id] + $args);
                $this->assertTrue($response['error'], "{$function} {$who}");
            }
            $this->setGuestUser();
            $this->assertTrue($this->call($function, ['enrolid' => $this->instance->id] + $args)['error'], "{$function} guest");

            $this->setAdminUser();
            $this->assertTrue($this->call($function, ['enrolid' => -1] + $args)['error'], "{$function} no method");
            $this->assertTrue($this->call($function, ['enrolid' => $manual] + $args)['error'], "{$function} manual");
            // The control: the same call by somebody the page admits.
            $this->assertFalse($this->call($function, ['enrolid' => $this->instance->id] + $args)['error'], "{$function} admin");
        }
        foreach ([$student, $reportonly, $elsewhere] as $user) {
            $this->assertNull(get_user_preferences(queuepreference::NAME, null, $user->id));
        }
    }

    /**
     * The wide queue (enrolid 0) answers its three readers - a site-wide decider, a site-wide viewer
     * and a mentor - and refuses an editing teacher of one course with no mentee, and a guest, with
     * nothing written.
     *
     * The teacher holds the deciding capability in their course, which opens that course's method
     * queue (the control that the refusal is the wide queue's) and nothing site-wide.
     *
     * Changes that must make it fail: a service reading the wide queue without the engine's refusal;
     * the preference written before the refusal, or into the method queues' preference.
     *
     * @return void
     */
    public function test_the_wide_queue_answers_its_three_readers_and_no_one_else(): void {
        $system = \context_system::instance();
        $generator = $this->getDataGenerator();

        $teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $decider = $generator->create_user();
        $role = $generator->create_role();
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $role, $system->id, true);
        role_assign($role, $decider->id, $system->id);
        $viewer = $generator->create_user();
        $role = $generator->create_role();
        assign_capability('enrol/apply:viewapplications', CAP_ALLOW, $role, $system->id, true);
        role_assign($role, $viewer->id, $system->id);
        $mentor = $generator->create_user();
        $role = $generator->create_role();
        set_role_contextlevels($role, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $role, $system->id, true);
        role_assign($role, $mentor->id, \context_user::instance($this->applicant->id)->id);

        $this->setUser($teacher);
        foreach (self::CALLS as $function => $args) {
            $response = $this->call($function, ['enrolid' => 0] + $args);
            $this->assertTrue($response['error'], "{$function} teacher");
            $this->assertSame('nopermissions', $response['exception']->errorcode, "{$function} teacher");
            // The control: the teacher's own method queue answers them.
            $this->assertFalse($this->call($function, ['enrolid' => $this->instance->id] + $args)['error'], "{$function} own");
        }
        $this->assertNull(get_user_preferences(queuepreference::NAME_WIDE, null, $teacher->id));
        // The method queue's write above was the control's; the wide one wrote nothing.
        $this->assertNotNull(get_user_preferences(queuepreference::NAME, null, $teacher->id));

        $this->setGuestUser();
        foreach (self::CALLS as $function => $args) {
            $this->assertTrue($this->call($function, ['enrolid' => 0] + $args)['error'], "{$function} guest");
        }

        foreach (['site' => $decider, 'viewer' => $viewer, 'mentees' => $mentor] as $who => $user) {
            $this->setUser($user);
            foreach (self::CALLS as $function => $args) {
                $response = $this->call($function, ['enrolid' => 0] + $args);
                $this->assertFalse($response['error'], "{$function} {$who} " . json_encode($response['exception'] ?? null));
            }
            $rows = $this->call('enrol_apply_queue_rows', ['enrolid' => 0])['data'];
            $this->assertSame($who === 'mentees' ? 'mentees' : 'site', $rows['kind'], $who);
            $this->assertSame(1, $rows['total'], $who);
            $this->assertSame((int) $this->course->id, $rows['rows'][0]['course']['id'], $who);
            $this->assertSame((int) $this->instance->id, $rows['rows'][0]['method']['id'], $who);
            $summary = $this->call('enrol_apply_queue_summary', ['enrolid' => 0])['data'];
            $this->assertSame(1, $summary['awaiting'], $who);
            $this->assertSame($who !== 'mentees', $summary['sitefigures'], $who);
            // The grid was written to the wide queues' preference, never to the method queues'.
            $stored = get_user_preferences(queuepreference::NAME_WIDE, null, $user->id);
            $this->assertSame('{"columns":["wait"],"widths":[]}', $stored, $who);
            $this->assertNull(get_user_preferences(queuepreference::NAME, null, $user->id), $who);
        }
    }

    /**
     * A forged page size, sort key, scope or filter name changes nothing but what the vocabulary
     * allows.
     *
     * Changes that must make it fail: a page size or a sort key taken as sent.
     *
     * @return void
     */
    public function test_forged_parameters_read_as_the_defaults(): void {
        $this->setAdminUser();

        $response = $this->call('enrol_apply_queue_rows', [
            'enrolid' => $this->instance->id,
            'scope' => 'everything',
            'perpage' => 5000,
            'sort' => 'answer:s_password',
            'direction' => 'sideways',
            'filters' => [['name' => 'status', 'value' => '0'], ['name' => 'nosuchfilter', 'value' => 'x']],
        ]);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        $this->assertSame('all', $response['data']['scope']);
        $this->assertSame(50, $response['data']['perpage']);
        $this->assertSame('wait', $response['data']['sort']);
        $this->assertSame('asc', $response['data']['direction']);
        $this->assertSame(1, $response['data']['total']);
        $this->assertSame([], $response['data']['filters']);
    }
}
