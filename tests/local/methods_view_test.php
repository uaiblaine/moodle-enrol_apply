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
 * Tests for the "By course" view of the site-wide queue (stage W4, SQ-D9).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * The view read headlessly ({@see queueengine::methods()}), against the engine's own list of the queue.
 *
 * Two courses: Alpha with two apply methods, Morning (one place, taken; an applicant limit of five) and
 * Evening (an applicant limit of three, closing in ten days); Beta with one method, Beta's, whose role is
 * the editing teacher's (a deciding method). A third course, Gamma, has no apply method. Seven applications:
 * Ann (Morning, 20 days), Bob (Morning, deferred, 10 days), Cid (Evening, 10 days), Gil (Evening, 3 days),
 * Dan (Beta, 5 days), Eve (Beta, 2 days), Fay (Beta, an hour). Morning and Evening both hold two, a tie the
 * sort by what awaits breaks by the method's id. The readers: a site-wide decider and a site-wide viewer, a
 * mentor of Ann and Dan, and an editing teacher of Alpha.
 *
 * Every count is compared with what queueengine::rows() lists for the same reader and request, never with
 * a hand-written number.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(queueengine::class)]
#[CoversClass(decidingrole::class)]
final class methods_view_test extends \advanced_testcase {
    /** @var \enrol_plugin The apply plugin. */
    private $plugin;

    /** @var int The frozen clock's time. */
    private $now;

    /** @var array Name => course record: alpha, beta, gamma (no apply method). */
    private $courses = [];

    /** @var array Name => {enrol} record: morning, evening (Alpha), beta (Beta). */
    private $methods = [];

    /** @var array Name => user enrolment id. */
    private $ue = [];

    /** @var array Name => applicant. */
    private $users = [];

    /** @var \stdClass Holds the deciding capability at the system. */
    private $decider;

    /** @var \stdClass Holds the viewing capability at the system. */
    private $viewer;

    /** @var \stdClass Mentors Ann and Dan. */
    private $mentor;

    /** @var \stdClass Editing teacher of Alpha. */
    private $teacher;

    /**
     * The courses, the methods, the applications and the readers.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB, $PAGE;

        parent::setUp();
        $this->resetAfterTest();
        $this->now = $this->mock_clock_with_frozen()->time();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        $this->plugin = enrol_get_plugin('apply');

        $generator = $this->getDataGenerator();
        foreach (['alpha', 'beta', 'gamma'] as $name) {
            $this->courses[$name] = $generator->create_course(['fullname' => ucfirst($name) . ' course']);
        }
        $editingteacher = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $morning = ['customint4' => 1, 'customint3' => 5];
        $this->methods['morning'] = $this->method($this->courses['alpha'], 'Morning intake', $morning);
        $this->methods['evening'] = $this->method($this->courses['alpha'], 'Evening intake', [
            'customint3' => 3,
            'enrolenddate' => $this->now + 10 * DAYSECS,
        ]);
        $this->methods['beta'] = $this->method($this->courses['beta'], 'Beta intake', ['roleid' => $editingteacher]);

        $this->ue['ann'] = $this->applicant('morning', 'Ann', 20 * DAYSECS);
        $this->ue['bob'] = $this->applicant('morning', 'Bob', 10 * DAYSECS, ENROL_APPLY_USER_WAIT);
        $this->ue['cid'] = $this->applicant('evening', 'Cid', 10 * DAYSECS);
        $this->ue['gil'] = $this->applicant('evening', 'Gil', 3 * DAYSECS);
        $this->ue['dan'] = $this->applicant('beta', 'Dan', 5 * DAYSECS);
        $this->ue['eve'] = $this->applicant('beta', 'Eve', 2 * DAYSECS);
        $this->ue['fay'] = $this->applicant('beta', 'Fay', HOURSECS);
        // Morning's one place, taken by an approved learner, who is in no queue.
        $this->plugin->enrol_user($this->methods['morning'], $generator->create_user()->id, null, 0, 0, ENROL_USER_ACTIVE);

        $system = \context_system::instance();
        $role = $generator->create_role(['shortname' => 'methodsdecider']);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $role, $system->id, true);
        $this->decider = $generator->create_user();
        role_assign($role, $this->decider->id, $system->id);

        $role = $generator->create_role(['shortname' => 'methodsviewer']);
        assign_capability('enrol/apply:viewapplications', CAP_ALLOW, $role, $system->id, true);
        $this->viewer = $generator->create_user();
        role_assign($role, $this->viewer->id, $system->id);

        $this->mentor = $generator->create_user();
        $role = $generator->create_role(['shortname' => 'methodsmentor']);
        set_role_contextlevels($role, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $role, $system->id, true);
        foreach (['ann', 'dan'] as $mentee) {
            role_assign($role, $this->mentor->id, \context_user::instance($this->users[$mentee]->id)->id);
        }

        $this->teacher = $generator->create_and_enrol($this->courses['alpha'], 'editingteacher');

        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php'));
    }

    /**
     * An apply method in a course.
     *
     * @param \stdClass $course The course.
     * @param string $name The method's name.
     * @param array $fields Further {enrol} fields.
     * @return \stdClass The {enrol} record.
     */
    private function method(\stdClass $course, string $name, array $fields = []): \stdClass {
        global $DB;

        $id = $this->plugin->add_instance($course, ['name' => $name] + $fields + $this->plugin->get_instance_defaults());

        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Put one applicant on a method's queue, with a durable record.
     *
     * @param string $method A key of $methods.
     * @param string $name First name.
     * @param int $ago Seconds since the application.
     * @param int $status The enrolment's status.
     * @return int The user enrolment id.
     */
    private function applicant(string $method, string $name, int $ago, int $status = ENROL_USER_SUSPENDED): int {
        global $DB;

        $instance = $this->methods[$method];
        $user = $this->getDataGenerator()->create_user(['firstname' => $name, 'lastname' => $name . 'son']);
        $this->users[strtolower($name)] = $user;
        $this->plugin->enrol_user($instance, $user->id, null, 0, 0, $status);
        $ueid = (int) $DB->get_field('user_enrolments', 'id', ['userid' => $user->id, 'enrolid' => $instance->id], MUST_EXIST);
        $DB->set_field('user_enrolments', 'timecreated', $this->now - $ago, ['id' => $ueid]);
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $instance->courseid,
            'userid' => $user->id,
            'enrolid' => $instance->id,
            'userenrolmentid' => $ueid,
            'comment' => 'From ' . $name,
            'status' => submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => $this->now - $ago,
            'timedecided' => 0,
            'decidedby' => 0,
        ]);

        return $ueid;
    }

    /**
     * What a callable throws.
     *
     * @param callable $call The call.
     * @return \Throwable|null The exception, null when nothing was thrown.
     */
    private function thrown(callable $call): ?\Throwable {
        try {
            $call();
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    }

    /**
     * The method ids of the view's rows, in order.
     *
     * @param array $options What queueengine::methods() takes.
     * @return array The ids.
     */
    private function method_ids(array $options = []): array {
        return array_map(static fn(array $row): int => $row['method']['id'], queueengine::methods($options)['rows']);
    }

    /**
     * The engine refuses a mentor and an editing teacher of one course, each with the exception the queue
     * gives a refused reader, and answers the two site-wide readers.
     *
     * The teacher is refused by require_listing() itself, before anything else is read; the mentor, whom the
     * wide queue answers, by the view's own site-wide condition, with the same exception.
     *
     * Changes that must make it fail: the listing asked without require_listing() (the teacher's refusal
     * then comes from the site-wide condition); the site-wide condition dropped (the mentor answered).
     *
     * @return void
     */
    public function test_the_view_is_refused_to_a_mentor_and_a_teacher_and_answers_the_site_wide_readers(): void {
        $this->setUser($this->teacher);
        $expected = $this->thrown(static fn() => queueengine::require_listing(0));
        $this->assertInstanceOf(\required_capability_exception::class, $expected);
        $caught = $this->thrown(static fn() => queueengine::methods([]));
        $this->assertInstanceOf(\required_capability_exception::class, $caught);
        $this->assertSame($expected->getMessage(), $caught->getMessage());
        // The refusal is require_listing()'s own, thrown before the table is built.
        $this->assertSame('require_listing', $caught->getTrace()[0]['function']);
        $this->assertNotContains('table', array_column($caught->getTrace(), 'function'));

        $this->setUser($this->mentor);
        // Precondition: the wide queue answers the mentor, with their two mentees.
        $this->assertSame('mentees', queueengine::rows(0, [])['kind']);
        $this->assertSame(2, queueengine::rows(0, [])['total']);
        $caught = $this->thrown(static fn() => queueengine::methods([]));
        $this->assertInstanceOf(\required_capability_exception::class, $caught);
        $this->assertSame($expected->getMessage(), $caught->getMessage());

        // The controls: both site-wide readers get every method.
        foreach (['decider' => $this->decider, 'viewer' => $this->viewer] as $who => $user) {
            $this->setUser($user);
            $answer = queueengine::methods([]);
            $this->assertSame(3, $answer['total'], $who);
            $this->assertEqualsCanonicalizing(
                array_map(static fn(\stdClass $method): int => (int) $method->id, array_values($this->methods)),
                array_map(static fn(array $row): int => $row['method']['id'], $answer['rows']),
                $who
            );
        }
    }

    /**
     * Each method's row agrees with the list: what awaits is what rows() lists for that method under the same
     * scope, search and filters, what is deferred is how many of those are deferred, the oldest wait is the
     * longest of their waits; the methods are the ones the list names, and only those.
     *
     * Over no filter, each scope, a course filter, a waited threshold, a search, and two of them together.
     *
     * Changes that must make it fail: the deferred count read off another predicate than the deferred
     * status; the scope, a filter or the search read anywhere but from the table's WHERE.
     *
     * @return void
     */
    public function test_each_methods_counts_are_what_the_list_lists_for_it(): void {
        $this->setUser($this->decider);
        $alpha = (string) $this->courses['alpha']->id;

        $requests = [
            ['all', '', []],
            ['pending', '', []],
            ['deferred', '', []],
            ['all', '', ['course' => $alpha]],
            ['all', '', ['waited' => '7']],
            ['all', 'Gil', []],
            ['all', 'Beta course', []],
            ['pending', '', ['course' => $alpha, 'waited' => '3']],
        ];
        foreach ($requests as [$scope, $search, $filters]) {
            $label = "{$scope} {$search} " . json_encode($filters);
            $options = ['scope' => $scope, 'search' => $search, 'filters' => $filters, 'perpage' => 100];
            $answer = queueengine::methods($options);
            $listed = queueengine::rows(0, $options);
            $named = array_unique(array_map(static fn(array $row): int => $row['method']['id'], $listed['rows']));
            $this->assertEqualsCanonicalizing(array_values($named), $this->method_ids($options), $label);
            $this->assertSame(count($named), $answer['total'], $label);
            $this->assertSame($listed['total'], array_sum(array_column($answer['rows'], 'awaiting')), $label);

            foreach ($answer['rows'] as $row) {
                $id = $row['method']['id'];
                $rows = queueengine::rows(0, ['filters' => ['method' => (string) $id] + $filters] + $options)['rows'];
                $deferred = array_filter($rows, static fn(array $one): bool => $one['status'] === ENROL_APPLY_USER_WAIT);
                $this->assertSame(count($rows), $row['awaiting'], "{$label} awaiting {$id}");
                $this->assertSame(count($deferred), $row['deferred'], "{$label} deferred {$id}");
                $this->assertSame(max(array_column($rows, 'wait')), $row['oldestwait'], "{$label} oldest {$id}");
                // The method's and the course's facts are the list's own for the same method.
                $this->assertSame($rows[0]['method']['name'], $row['method']['name'], $label);
                $this->assertSame($rows[0]['method']['deciding'], $row['method']['deciding'], $label);
                $this->assertSame($rows[0]['method']['queueurl'], $row['method']['queueurl'], $label);
                $this->assertSame($rows[0]['course'], $row['course'], $label);
            }
        }

        // The controls: Morning holds a deferred application, Beta's role decides, and the requests narrow.
        $byid = [];
        foreach (queueengine::methods([])['rows'] as $row) {
            $byid[$row['method']['id']] = $row;
        }
        $morning = (int) $this->methods['morning']->id;
        $this->assertSame(1, $byid[$morning]['deferred']);
        $this->assertSame(20 * DAYSECS, $byid[$morning]['oldestwait']);
        $this->assertTrue($byid[(int) $this->methods['beta']->id]['method']['deciding']);
        $this->assertFalse($byid[(int) $this->methods['evening']->id]['method']['deciding']);
        $this->assertSame([(int) $this->methods['evening']->id], $this->method_ids(['search' => 'Gil']));
        $this->assertSame(0, array_sum(array_column(queueengine::methods(['scope' => 'pending'])['rows'], 'deferred')));
        $this->assertSame([$morning], $this->method_ids(['scope' => 'deferred']));
    }

    /**
     * The capacity facts of each row of the page are capacity::state()'s for its method, field for field.
     *
     * Changes that must make it fail: a fact renamed onto another of state()'s numbers; the capacity read
     * for anything but the row's own method.
     *
     * @return void
     */
    public function test_the_capacity_facts_are_states_own(): void {
        global $DB;

        $this->setUser($this->viewer);
        $rows = queueengine::methods([])['rows'];
        $this->assertCount(3, $rows);
        $facts = ['places', 'placestaken', 'applicantlimit', 'applicants', 'placesfull', 'limitreached', 'open', 'closes'];
        $byname = [];
        foreach ($rows as $row) {
            $instance = $DB->get_record('enrol', ['id' => $row['method']['id']], '*', MUST_EXIST);
            $state = capacity::state($instance, $this->now);
            $this->assertSame([
                'places' => $state->places,
                'placestaken' => $state->taken,
                'applicantlimit' => $state->limit,
                'applicants' => $state->held,
                'placesfull' => $state->placesfull,
                'limitreached' => $state->limitreached,
                'open' => $state->open,
                'closes' => $state->enddate,
            ], array_intersect_key($row, array_flip($facts)), $instance->name);
            $byname[$row['method']['name']] = $row;
        }

        // The controls: the fixture's capacities differ, so a fact read off another method's state shows.
        $this->assertTrue($byname['Morning intake']['placesfull']);
        $this->assertSame(1, $byname['Morning intake']['placestaken']);
        $this->assertSame(3, $byname['Morning intake']['applicants']);
        $this->assertSame(2, $byname['Evening intake']['applicants']);
        $this->assertSame($this->now + 10 * DAYSECS, $byname['Evening intake']['closes']);
        $this->assertSame(0, $byname['Beta intake']['places']);
        $this->assertFalse($byname['Beta intake']['placesfull']);
    }

    /**
     * A method without places says how many are enrolled, and that it has no limit: the view asks for the
     * places taken of every method of the page, and the row carries the count.
     *
     * Changes that must make it fail: the view asking capacity::states() without the flag (the count of
     * Beta's three active enrolments reads 0).
     *
     * @return void
     */
    public function test_a_method_without_places_carries_its_enrolled_count(): void {
        $this->setUser($this->viewer);
        $beta = $this->methods['beta'];
        $before = $this->row_of((int) $beta->id);
        $this->assertSame(0, $before['placestaken']);

        foreach (range(1, 3) as $ignored) {
            $this->plugin->enrol_user($beta, $this->getDataGenerator()->create_user()->id, null, 0, 0, ENROL_USER_ACTIVE);
        }
        $row = $this->row_of((int) $beta->id);
        $this->assertSame(0, $row['places']);
        $this->assertSame(3, $row['placestaken']);
        $this->assertFalse($row['placesfull']);
        // The control: a method with places counts as before.
        $this->assertSame(1, $this->row_of((int) $this->methods['morning']->id)['placestaken']);
    }

    /**
     * One row of the view by method id.
     *
     * @param int $id The method.
     * @return array The row.
     */
    private function row_of(int $id): array {
        foreach (queueengine::methods(['perpage' => 100])['rows'] as $row) {
            if ($row['method']['id'] === $id) {
                return $row;
            }
        }
        $this->fail("No row for method {$id}");
    }

    /**
     * One web service of the site-wide queue, as a client calls it.
     *
     * @param string $function The service.
     * @param array $args Its arguments.
     * @return array The answer's data.
     */
    private function call(string $function, array $args): array {
        $_POST['sesskey'] = sesskey();
        $response = \core_external\external_api::call_external_function($function, $args, true);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));

        return $response['data'];
    }

    /**
     * A method's capacity is told to a wide reader only where the reader could see the course: a hidden
     * course's method at its limit shows neutral facts, no "full" label and no count in the tile, to a reader
     * without moodle/course:viewhiddencourses, and everything to one who holds it.
     *
     * Two courses, one hidden, each with a method at its limit (a place taken, an application awaiting). The
     * reader holds viewapplications at the system and nothing else (stock archetypes hold the capability too).
     * Through the three services, as a client calls them.
     *
     * Changes that must make it fail: the predicate answering true for a hidden course (gate QM9); a reader
     * of the same site holding the capability still refused the facts.
     *
     * @return void
     */
    public function test_the_capacity_of_a_hidden_course_is_not_told_to_a_reader_who_could_not_enter_it(): void {
        $generator = $this->getDataGenerator();
        $hidden = $generator->create_course(['fullname' => 'Hidden course', 'visible' => 0]);
        $visible = $generator->create_course(['fullname' => 'Visible course']);
        $limits = ['customint3' => 2, 'customint4' => 1];
        $this->methods['hid'] = $this->method($hidden, 'Hidden intake', $limits);
        $this->methods['vis'] = $this->method($visible, 'Visible intake', $limits);
        foreach (['hid', 'vis'] as $key) {
            $this->applicant($key, 'Pat' . $key, DAYSECS);
            $this->plugin->enrol_user($this->methods[$key], $generator->create_user()->id, null, 0, 0, ENROL_USER_ACTIVE);
        }
        $hid = (int) $this->methods['hid']->id;
        $vis = (int) $this->methods['vis']->id;

        $clerk = $generator->create_user();
        $role = $generator->create_role(['shortname' => 'methodsclerk']);
        assign_capability('enrol/apply:viewapplications', CAP_ALLOW, $role, \context_system::instance()->id, true);
        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $clerk->id, \context_system::instance()->id);

        $read = function () use ($hid, $vis): array {
            $methods = [];
            foreach ($this->call('enrol_apply_queue_methods', ['perpage' => 100])['rows'] as $row) {
                $methods[$row['method']['id']] = $row;
            }
            $full = [];
            foreach ($this->call('enrol_apply_queue_rows', ['enrolid' => 0, 'perpage' => 100])['rows'] as $row) {
                $full[$row['method']['id']] = $row['method']['full'];
            }
            $summary = $this->call('enrol_apply_queue_summary', ['enrolid' => 0]);

            return [$methods, $full, $summary['methodsfull']];
        };

        // The reader who could not enter the hidden course.
        $this->setUser($this->viewer);
        [$methods, $full, $methodsfull] = $read();
        $this->assertFalse($methods[$hid]['capacityknown']);
        $this->assertSame(0, $methods[$hid]['places']);
        $this->assertSame(0, $methods[$hid]['placestaken']);
        $this->assertSame(0, $methods[$hid]['applicantlimit']);
        $this->assertSame(0, $methods[$hid]['applicants']);
        $this->assertFalse($methods[$hid]['placesfull']);
        $this->assertFalse($methods[$hid]['limitreached']);
        $this->assertFalse($methods[$hid]['open']);
        $this->assertSame(0, $methods[$hid]['closes']);
        // The course and its applications stay listed.
        $this->assertSame('Hidden course', $methods[$hid]['course']['name']);
        $this->assertSame(1, $methods[$hid]['awaiting']);
        $this->assertFalse($full[$hid]);
        $this->assertTrue($methods[$vis]['capacityknown']);
        $this->assertSame(1, $methods[$vis]['places']);
        $this->assertSame(1, $methods[$vis]['placestaken']);
        $this->assertTrue($methods[$vis]['placesfull']);
        $this->assertTrue($full[$vis]);
        // Morning, at its place, and the visible method: the hidden one is not counted.
        $this->assertSame(2, $methodsfull);

        // The control: the same calls by a reader holding the capability give the facts.
        $this->setUser($clerk);
        [$methods, $full, $methodsfull] = $read();
        $this->assertTrue($methods[$hid]['capacityknown']);
        $this->assertSame(1, $methods[$hid]['places']);
        $this->assertSame(1, $methods[$hid]['placestaken']);
        $this->assertSame(2, $methods[$hid]['applicantlimit']);
        $this->assertSame(2, $methods[$hid]['applicants']);
        $this->assertTrue($methods[$hid]['placesfull']);
        $this->assertTrue($methods[$hid]['limitreached']);
        $this->assertTrue($full[$hid]);
        $this->assertTrue($full[$vis]);
        $this->assertSame(3, $methodsfull);
    }

    /**
     * A reader holding moodle/course:viewhiddencourses in the system context but prohibited it in one
     * hidden course is not told that course's method is at its limit by the summary's tile, as the
     * rows and the "By course" view already did not tell them.
     *
     * Changes that must make it fail: the summary skipping the per-course question for a reader who
     * holds the capability in the system context (gate QM11).
     *
     * @return void
     */
    public function test_the_summary_honours_a_course_level_prohibit_on_hidden_courses(): void {
        $generator = $this->getDataGenerator();
        $hidden = $generator->create_course(['fullname' => 'Prohibited course', 'visible' => 0]);
        $this->methods['hid'] = $this->method($hidden, 'Prohibited intake', ['customint3' => 2, 'customint4' => 1]);
        $this->applicant('hid', 'Pro', DAYSECS);
        $this->plugin->enrol_user($this->methods['hid'], $generator->create_user()->id, null, 0, 0, ENROL_USER_ACTIVE);
        $hid = (int) $this->methods['hid']->id;

        $clerk = $generator->create_user();
        $role = $generator->create_role(['shortname' => 'prohibitedclerk']);
        $system = \context_system::instance();
        assign_capability('enrol/apply:viewapplications', CAP_ALLOW, $role, $system->id, true);
        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $role, $system->id, true);
        assign_capability('moodle/course:viewhiddencourses', CAP_PROHIBIT, $role, \context_course::instance($hidden->id)->id, true);
        role_assign($role, $clerk->id, $system->id);
        $this->setUser($clerk);
        // The premise: the capability is held site-wide and refused in that course.
        $this->assertTrue(has_capability('moodle/course:viewhiddencourses', $system));
        $this->assertFalse(has_capability('moodle/course:viewhiddencourses', \context_course::instance($hidden->id)));

        $methods = [];
        foreach ($this->call('enrol_apply_queue_methods', ['perpage' => 100])['rows'] as $row) {
            $methods[$row['method']['id']] = $row;
        }
        $this->assertFalse($methods[$hid]['capacityknown'], 'the view already withholds it');
        $full = count(array_filter(
            $methods,
            static fn(array $row): bool => $row['capacityknown'] && ($row['placesfull'] || $row['limitreached'])
        ));

        // Morning, at its place, and not the prohibited course's method: the tile agrees with the view.
        $this->assertSame(1, $full);
        $this->assertSame($full, $this->call('enrol_apply_queue_summary', ['enrolid' => 0])['methodsfull']);
    }

    /**
     * A hidden course's capacity is not told through a role kept from an enrolment that lapsed.
     *
     * A site-wide viewer who is also enrolled in the hidden course, with a role that carries
     * moodle/course:viewhiddencourses there, is told the method's capacity while the enrolment is active
     * (the control). Once it is suspended the role survives, and with it the capability, but
     * require_login($course) would refuse them, so the wide readers withhold the capacity too.
     *
     * Changes that must make it fail: shows_capacity() reading the bare capability again (LP26).
     *
     * @return void
     */
    public function test_the_capacity_of_a_hidden_course_is_not_told_through_a_lapsed_course_role(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $hidden = $generator->create_course(['fullname' => 'Lapsed course', 'visible' => 0]);
        $this->methods['lap'] = $this->method($hidden, 'Lapsed intake', ['customint3' => 2, 'customint4' => 1]);
        $this->applicant('lap', 'Lap', DAYSECS);
        $lap = (int) $this->methods['lap']->id;

        $clerk = $generator->create_user();
        $system = \context_system::instance();
        $siterole = $generator->create_role(['shortname' => 'lapsedclerk']);
        assign_capability('enrol/apply:viewapplications', CAP_ALLOW, $siterole, $system->id, true);
        role_assign($siterole, $clerk->id, $system->id);
        $courserole = $generator->create_role(['shortname' => 'lapsedhelper']);
        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $courserole, $system->id, true);
        $generator->enrol_user($clerk->id, $hidden->id, $courserole, 'manual');

        $known = function () use ($lap): bool {
            foreach ($this->call('enrol_apply_queue_methods', ['perpage' => 100])['rows'] as $row) {
                if ((int) $row['method']['id'] === $lap) {
                    return (bool) $row['capacityknown'];
                }
            }
            $this->fail('the hidden course\'s method is not listed');
        };

        $this->setUser($clerk);
        $coursecontext = \context_course::instance($hidden->id);
        // The premise: the capability comes from the course role alone.
        $this->assertFalse(has_capability('moodle/course:viewhiddencourses', $system));
        $this->assertTrue(has_capability('moodle/course:viewhiddencourses', $coursecontext));
        $this->assertTrue($known(), 'an active enrolment: the capacity is theirs to know');

        $manual = $DB->get_record('enrol', ['courseid' => $hidden->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->update_user_enrol($manual, (int) $clerk->id, ENROL_USER_SUSPENDED);
        $this->setUser($clerk);
        $this->assertTrue(has_capability('moodle/course:viewhiddencourses', $coursecontext), 'the role survives');
        $this->assertFalse(is_enrolled($coursecontext, null, '', true), 'but the enrolment grants no access');
        $this->assertFalse($known());
    }

    /**
     * Capacity is read for the page's methods alone, and a page is read in a number of statements that does
     * not grow with the methods on it: a page of four methods costs what a page of eight does when they share
     * the same four courses.
     *
     * The page-only read is watched through the engine's seam (page_states()), which a subclass records; the
     * same subclass narrows the page sizes to two (every read of them in methods() is static::).
     *
     * Changes that must make it fail: the capacity read over every method of the result; a read per method
     * (the capacity, the names, the deciding role).
     *
     * @return void
     */
    public function test_capacity_is_read_for_the_page_alone_and_a_page_costs_no_read_per_method(): void {
        $this->setUser($this->viewer);
        $engine = new class extends queueengine {
            /** @var int[] Two methods a page, for the test. */
            public const PERPAGES = [2];

            /** @var int Two methods a page, for the test. */
            public const PERPAGE = 2;

            /** @var array The method ids page_states() was last given. */
            public static array $seen = [];

            /** @var bool Whether page_states() was last asked to count places taken for every method. */
            public static bool $flag = false;

            /**
             * Record the methods, then read their capacity.
             *
             * @param array $instances The page's methods.
             * @param int $now The clock's time.
             * @param bool $alwaystaken Whether places taken are counted for a method without places too.
             * @return array What the engine's own seam returns.
             */
            protected static function page_states(array $instances, int $now, bool $alwaystaken = false): array {
                static::$seen = array_map('intval', array_keys($instances));
                static::$flag = $alwaystaken;

                return parent::page_states($instances, $now, $alwaystaken);
            }
        };
        $ids = static fn(array $answer): array => array_map(static fn(array $row): int => $row['method']['id'], $answer['rows']);

        // Beta's first (three awaiting), then Evening's and Morning's (two each, by id, most recent first).
        $first = $engine::methods([]);
        $this->assertSame([(int) $this->methods['beta']->id, (int) $this->methods['evening']->id], $ids($first));
        $this->assertSame($ids($first), $engine::$seen);
        // The view asks for the places taken of a method without places too.
        $this->assertTrue($engine::$flag);
        $second = $engine::methods(['page' => 1]);
        $this->assertSame([(int) $this->methods['morning']->id], $ids($second));
        $this->assertSame($ids($second), $engine::$seen);
        $this->assertSame(3, $second['total']);

        // Two more courses, each with a method holding places and a limit; then two more methods in each.
        $generator = $this->getDataGenerator();
        $courses = [
            'f' => $generator->create_course(['fullname' => 'First']),
            's' => $generator->create_course(['fullname' => 'Second']),
        ];
        $capped = ['customint3' => 50, 'customint4' => 10];
        foreach ($courses as $key => $course) {
            $this->methods[$key . '1'] = $this->method($course, strtoupper($key) . '1', $capped);
            $this->applicant($key . '1', 'Hal' . $key, DAYSECS);
        }
        $measure = function (int $methods): int {
            global $DB;

            queueengine::methods(['perpage' => 100]);
            $before = $DB->perf_get_reads();
            $answer = queueengine::methods(['perpage' => 100]);
            $reads = $DB->perf_get_reads() - $before;
            $this->assertCount($methods, $answer['rows']);
            $this->assertCount(4, array_unique(array_map(static fn(array $row): int => $row['course']['id'], $answer['rows'])));

            return $reads;
        };
        $five = $measure(5);
        foreach ($courses as $key => $course) {
            foreach ([2, 3] as $n) {
                $this->methods[$key . $n] = $this->method($course, strtoupper($key) . $n, $capped);
                $this->applicant($key . $n, 'Jo' . $key . $n, DAYSECS);
            }
        }
        $nine = $measure(9);
        $this->assertSame($five, $nine, "four more methods in the same courses: {$five} then {$nine}");
    }

    /**
     * Each sort key orders the methods totally in both directions, ties broken by the method's id; a key
     * outside the vocabulary falls back to what awaits, most first; a page size outside 30, 50 and 100 reads
     * as 50; a page past the end is the last page.
     *
     * Morning and Evening both hold two applications, so the sort by what awaits needs the id.
     *
     * Changes that must make it fail: the sort taken as sent (a forged key echoed); the fallback with the
     * request's direction; the order without the id last; the page size or the page taken as sent.
     *
     * @return void
     */
    public function test_the_sorts_and_the_paging(): void {
        $this->setUser($this->decider);
        $m = (int) $this->methods['morning']->id;
        $e = (int) $this->methods['evening']->id;
        $b = (int) $this->methods['beta']->id;
        // Precondition: Evening was added after Morning.
        $this->assertGreaterThan($m, $e);

        $expected = [
            ['awaiting', 'desc', [$b, $e, $m]],
            ['awaiting', 'asc', [$m, $e, $b]],
            ['oldest', 'asc', [$m, $e, $b]],
            ['oldest', 'desc', [$b, $e, $m]],
            ['course', 'asc', [$e, $m, $b]],
            ['course', 'desc', [$b, $m, $e]],
        ];
        foreach ($expected as [$sort, $direction, $ids]) {
            $answer = queueengine::methods(['sort' => $sort, 'direction' => $direction]);
            $this->assertSame($ids, array_column(array_column($answer['rows'], 'method'), 'id'), "{$sort} {$direction}");
            $this->assertSame($sort, $answer['sort']);
            $this->assertSame($direction, $answer['direction']);
        }

        // The default, and a forged key, which falls back with the default's own direction.
        foreach (['', 'nonsense', 'wait', 'e.id; DROP TABLE x'] as $forged) {
            $answer = queueengine::methods(['sort' => $forged, 'direction' => 'asc']);
            $this->assertSame('awaiting', $answer['sort'], $forged);
            $this->assertSame('desc', $answer['direction'], $forged);
            $this->assertSame([$b, $e, $m], array_column(array_column($answer['rows'], 'method'), 'id'), $forged);
        }

        $answer = queueengine::methods(['perpage' => 999, 'page' => 7]);
        $this->assertSame(50, $answer['perpage']);
        $this->assertSame(0, $answer['page']);
        $this->assertSame(3, $answer['total']);
        $this->assertCount(3, $answer['rows']);
        // The controls: a page size of the vocabulary is kept, and an empty answer stays on page 0.
        $this->assertSame(30, queueengine::methods(['perpage' => 30])['perpage']);
        $none = queueengine::methods(['search' => 'Nobody at all', 'page' => 3]);
        $this->assertSame(0, $none['total']);
        $this->assertSame(0, $none['page']);
        $this->assertSame([], $none['rows']);
    }

    /**
     * A forged filter does what it does for rows(): a method that is no apply instance, another plugin's
     * method, a course with no apply method, a category that does not exist, a filter the queue does not
     * declare and the status filter are each ignored, by the list and by the view alike; a method of another
     * course than the course filter lists nothing on both.
     *
     * Changes that must make it fail: the view reading a filter of its own rather than the table's.
     *
     * @return void
     */
    public function test_forged_filters_do_what_they_do_for_the_list(): void {
        global $DB;

        $this->setUser($this->decider);
        $where = ['courseid' => $this->courses['alpha']->id, 'enrol' => 'manual'];
        $manual = (string) $DB->get_field('enrol', 'id', $where, MUST_EXIST);
        $whole = $this->method_ids();
        $this->assertCount(3, $whole);

        $ignored = [
            ['method' => '999999'],
            ['method' => $manual],
            ['method' => '-3'],
            ['course' => (string) $this->courses['gamma']->id],
            ['category' => '999999'],
            ['nosuchfilter' => 'x'],
            ['status' => '0'],
        ];
        foreach ($ignored as $filters) {
            $label = json_encode($filters);
            $this->assertSame($whole, $this->method_ids(['filters' => $filters]), $label);
            $this->assertSame(7, queueengine::rows(0, ['filters' => $filters])['total'], $label);
        }

        $morning = (string) $this->methods['morning']->id;
        $crossed = ['method' => $morning, 'course' => (string) $this->courses['beta']->id];
        $this->assertSame(0, queueengine::rows(0, ['filters' => $crossed])['total']);
        $this->assertSame([], $this->method_ids(['filters' => $crossed]));

        // The controls: the same names with real values narrow both.
        $this->assertSame([(int) $morning], $this->method_ids(['filters' => ['method' => $morning]]));
        $beta = ['course' => (string) $this->courses['beta']->id];
        $this->assertSame([(int) $this->methods['beta']->id], $this->method_ids(['filters' => $beta]));
    }
}
