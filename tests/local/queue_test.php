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

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/apply/manage_table.php');

/**
 * What counts as an application awaiting a decision, and who may review one.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(queue::class)]
final class queue_test extends \advanced_testcase {
    /** @var \stdClass Course the apply instance belongs to. */
    private $course;

    /** @var \stdClass The enrol_apply instance record. */
    private $instance;

    /** @var \enrol_apply_plugin The plugin under test. */
    private $plugin;

    /**
     * A course carrying a single enabled apply enrolment instance.
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

        $this->plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $instanceid = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
    }

    /**
     * A fresh applicant on this course's apply instance, and their user enrolment id.
     *
     * @param int $status One of ENROL_USER_SUSPENDED, ENROL_APPLY_USER_WAIT, ENROL_USER_ACTIVE.
     * @param string $comment Comment submitted with the application.
     * @return array Two-element array of the user record and the user enrolment id.
     */
    private function applicant(int $status = ENROL_USER_SUSPENDED, string $comment = 'Please let me in'): array {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $user->id, null, 0, 0, $status);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $user->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $DB->insert_record('enrol_apply_applicationinfo', (object) [
            'userenrolmentid' => $ueid,
            'comment' => $comment,
        ]);

        return [$user, $ueid];
    }

    /**
     * A teacher of this course, who holds the capability there and nowhere else.
     *
     * @return \stdClass The teacher user record.
     */
    private function teacher(): \stdClass {
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');

        return $teacher;
    }

    /**
     * A mentor of the given user, holding the capability in that user's own context only.
     *
     * The standard Moodle mentor pattern: a role assignable at CONTEXT_USER, assigned there.
     *
     * @param \stdClass $mentee User to mentor.
     * @return \stdClass The mentor user record.
     */
    private function mentor(\stdClass $mentee): \stdClass {
        $mentor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applymentor']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $mentor->id, \context_user::instance($mentee->id)->id);

        return $mentor;
    }

    /**
     * A course carrying its own apply instance.
     *
     * @param array $options Course generator options, e.g. visible => 0.
     * @return array Two-element array of the course record and its enrol instance record.
     */
    private function course_with_instance(array $options = []): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course($options);
        $instanceid = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());

        return [$course, $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST)];
    }

    /**
     * An applicant on a nominated instance rather than on this course's own.
     *
     * @param \stdClass $instance Enrol instance to apply to.
     * @param int $status One of ENROL_USER_SUSPENDED, ENROL_APPLY_USER_WAIT, ENROL_USER_ACTIVE.
     * @return array Two-element array of the user record and the user enrolment id.
     */
    private function applicant_on(\stdClass $instance, int $status = ENROL_USER_SUSPENDED): array {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($instance, $user->id, null, 0, 0, $status);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $user->id, 'enrolid' => $instance->id],
            MUST_EXIST
        );

        return [$user, $ueid];
    }

    /**
     * Somebody holding the capability at the system context and nowhere in particular.
     *
     * @return \stdClass The user record.
     */
    private function site_manager(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applysitemanager']);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $user->id, \context_system::instance()->id);

        return $user;
    }

    /**
     * Somebody holding the capability in one course context, with no way into that course.
     *
     * A role assignment is not an enrolment and the role grants nothing else, so this user
     * passes can_manage_application() through its course-context arm - which is what puts them
     * on the review page - while can_access_course() refuses them on a hidden course.
     *
     * @param \stdClass $course Course to grant the capability in.
     * @return \stdClass The user record.
     */
    private function course_manager_locked_out(\stdClass $course): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applycoursemanager']);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $user->id, \context_course::instance($course->id)->id);

        return $user;
    }

    /**
     * The scope an operator gets for one application.
     *
     * @param int $ueid User enrolment id of the application under review.
     * @param \stdClass $instance Enrol instance it belongs to.
     * @return \stdClass Scope as queue::scope() returns it.
     */
    private function scope_for(int $ueid, \stdClass $instance): \stdClass {
        return queue::scope(queue::application($ueid), $instance);
    }

    /**
     * Walk the whole scope an operator gets for one application, from that application.
     *
     * @param int $ueid User enrolment id to anchor on.
     * @param \stdClass $instance Enrol instance it belongs to.
     * @return array User enrolment ids in the order the walk visits them.
     */
    private function walk_from(int $ueid, \stdClass $instance): array {
        return $this->walk(queue::application($ueid), $this->scope_for($ueid, $instance));
    }

    /**
     * Stamp an application with a chosen submission time.
     *
     * The walk is ordered by ue.timecreated, which enrol_user() writes as time() - so every
     * application a test creates shares one value and every ordering assertion would be about
     * the tiebreaker alone. Setting it explicitly is what lets a test be about the order.
     *
     * @param int $ueid User enrolment id.
     * @param int $timecreated Submission time to stamp.
     * @return int The same user enrolment id, so callers can chain.
     */
    private function submitted_at(int $ueid, int $timecreated): int {
        global $DB;

        $DB->set_field('user_enrolments', 'timecreated', $timecreated, ['id' => $ueid]);

        return $ueid;
    }

    /**
     * Walk back from one application until there is no previous, and return where that lands.
     *
     * Written as a walk rather than as a query so that the two directions hold each other: a
     * previous link that pointed at the wrong row would move the start, and every forward
     * assertion built on it would fail.
     *
     * @param \stdClass $from Any application in the scope.
     * @param \stdClass $scope Scope to walk in.
     * @return \stdClass The first application of the walk.
     */
    private function first_in_walk(\stdClass $from, \stdClass $scope): \stdClass {
        $current = $from;
        $seen = [(int) $current->id];

        while ($previous = queue::neighbours($current, $scope)['previous']) {
            $this->assertNotContains((int) $previous->id, $seen, 'the walk went back to a row it had left');
            $seen[] = (int) $previous->id;
            $current = queue::application((int) $previous->id);
        }

        return $current;
    }

    /**
     * Every application the walk reaches, from the start of the scope to the end of it.
     *
     * @param \stdClass $from Any application in the scope.
     * @param \stdClass $scope Scope to walk in.
     * @return array User enrolment ids in the order the walk visits them.
     */
    private function walk(\stdClass $from, \stdClass $scope): array {
        $current = $this->first_in_walk($from, $scope);
        $visited = [(int) $current->id];

        while ($next = queue::neighbours($current, $scope)['next']) {
            $this->assertNotContains((int) $next->id, $visited, 'the walk visited an application twice');
            $visited[] = (int) $next->id;
            $current = queue::application((int) $next->id);
        }

        return $visited;
    }

    /**
     * The order the queue's own table lists a scope in.
     *
     * @param int $enrolid Enrol instance to list, 0 for every one of them.
     * @param array|null $mentees Applicant ids to restrict to, null for no restriction.
     * @return array User enrolment ids in the listing's order.
     */
    private function listed(int $enrolid = 0, ?array $mentees = null): array {
        $table = new \enrol_apply_manage_table($enrolid, $mentees);
        $table->define_baseurl(new \moodle_url('/enrol/apply/manage.php'));
        ob_start();
        $table->out(50, false);
        ob_end_clean();

        return array_map(static fn($row) => (int) $row->userenrolmentid, array_values($table->rawdata));
    }

    /**
     * A pending application is found, with the fields a review page needs.
     *
     * @return void
     */
    public function test_a_pending_application_is_found(): void {
        [$user, $ueid] = $this->applicant();

        $application = queue::application($ueid);

        $this->assertNotNull($application);
        $this->assertEquals($ueid, (int) $application->id);
        $this->assertEquals($user->id, (int) $application->userid);
        $this->assertEquals($this->instance->id, (int) $application->enrolid);
        $this->assertEquals($this->course->id, (int) $application->courseid);
        $this->assertEquals($this->course->fullname, $application->coursename);
        $this->assertSame('Please let me in', $application->applycomment);
    }

    /**
     * So is one on the waiting list, which is invisible to core and easy to filter away.
     *
     * @return void
     */
    public function test_a_waiting_list_application_is_found(): void {
        [, $ueid] = $this->applicant(ENROL_APPLY_USER_WAIT);

        $this->assertNotNull(queue::application($ueid));
    }

    /**
     * An application that has been decided is not, and neither is one that never existed.
     *
     * Both give the same answer on purpose: a reader cannot act on the difference, and
     * separating them would answer "does user enrolment N exist?" for anybody who asks - which
     * matters here because the lookup runs before anybody has been authorised.
     *
     * @return void
     */
    public function test_a_decided_or_missing_application_is_not_found(): void {
        global $DB;

        [, $decided] = $this->applicant(ENROL_USER_ACTIVE);
        [, $gone] = $this->applicant();
        $DB->delete_records('user_enrolments', ['id' => $gone]);

        $this->assertNull(queue::application($decided));
        $this->assertNull(queue::application($gone));
        $this->assertNull(queue::application(99999999));
    }

    /**
     * Neither is an approved enrolment that has since expired.
     *
     * Same row shape as a fresh application to anything reading status alone, which is why the
     * predicate carries a second clause; the pending control proves the lookup runs at all.
     *
     * @return void
     */
    public function test_an_expired_enrolment_is_not_an_application(): void {
        global $DB;

        [, $expired] = $this->applicant();
        $DB->update_record('user_enrolments', (object) [
            'id' => $expired,
            'status' => ENROL_USER_SUSPENDED,
            'timestart' => time() - DAYSECS * 30,
            'timeend' => time() - DAYSECS,
        ]);
        [, $pending] = $this->applicant();

        $this->assertNull(queue::application($expired));
        $this->assertNotNull(queue::application($pending));
    }

    /**
     * The queue's listing and this lookup agree, because they are the same predicate.
     *
     * @return void
     */
    public function test_the_listing_and_the_lookup_agree(): void {
        global $DB;

        [, $pendingueid] = $this->applicant();
        [, $waitingueid] = $this->applicant(ENROL_APPLY_USER_WAIT);
        [, $expired] = $this->applicant();
        $DB->set_field('user_enrolments', 'timeend', time() - DAYSECS, ['id' => $expired]);
        [, $active] = $this->applicant(ENROL_USER_ACTIVE);

        $table = new \enrol_apply_manage_table($this->instance->id);
        $table->define_baseurl(new \moodle_url('/enrol/apply/manage.php'));
        ob_start();
        $table->out(50, false);
        ob_end_clean();
        $listed = array_map(static fn($row) => (int) $row->userenrolmentid, array_values($table->rawdata));

        $found = [];
        foreach ($DB->get_fieldset_select('user_enrolments', 'id', 'enrolid = ?', [$this->instance->id]) as $ueid) {
            if (queue::application((int) $ueid)) {
                $found[] = (int) $ueid;
            }
        }

        $this->assertEqualsCanonicalizing($listed, $found);
        /* The controls: something really was excluded, so agreeing is not agreeing on
           everything, and the two that survived are the two that should have. */
        $this->assertNotContains($expired, $found);
        $this->assertNotContains($active, $found);
        $this->assertEqualsCanonicalizing([$pendingueid, $waitingueid], $found);
    }

    /**
     * A teacher of the course may review an application made to it.
     *
     * This is the widening. Until now the review page required the capability in the
     * APPLICANT'S user context and nowhere else, which a course teacher does not hold -
     * measured on both branches - so a page meant for reviewing one application threw at
     * everybody except a mentor.
     *
     * @return void
     */
    public function test_a_course_teacher_may_review_an_application(): void {
        [, $ueid] = $this->applicant();
        $this->setUser($this->teacher());

        $context = queue::require_review_access(queue::application($ueid));

        $this->assertInstanceOf(\context_course::class, $context);
        $this->assertEquals($this->course->id, $context->instanceid);
    }

    /**
     * A mentor of the applicant still may, and in the applicant's own context.
     *
     * @return void
     */
    public function test_a_mentor_may_still_review_an_application(): void {
        [$applicant, $ueid] = $this->applicant();
        $this->setUser($this->mentor($applicant));

        $context = queue::require_review_access(queue::application($ueid));

        $this->assertInstanceOf(\context_user::class, $context);
        $this->assertEquals($applicant->id, $context->instanceid);
    }

    /**
     * So does an administrator, through the course context they inherit it in.
     *
     * @return void
     */
    public function test_an_administrator_may_review_an_application(): void {
        [, $ueid] = $this->applicant();
        $this->setAdminUser();

        $this->assertInstanceOf(
            \context_course::class,
            queue::require_review_access(queue::application($ueid))
        );
    }

    /**
     * Anybody else is refused.
     *
     * @return void
     */
    public function test_somebody_with_no_claim_is_refused(): void {
        [, $ueid] = $this->applicant();
        $outsider = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($outsider->id, $this->course->id, 'student');
        $this->setUser($outsider);

        $this->expectException(\required_capability_exception::class);
        queue::require_review_access(queue::application($ueid));
    }

    /**
     * A teacher of one course cannot review an application made to another.
     *
     * @return void
     */
    public function test_a_teacher_of_another_course_is_refused(): void {
        [, $ueid] = $this->applicant();
        $elsewhere = $this->getDataGenerator()->create_course();
        $stranger = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($stranger->id, $elsewhere->id, 'editingteacher');
        $this->setUser($stranger);

        $this->expectException(\required_capability_exception::class);
        queue::require_review_access(queue::application($ueid));
    }

    /**
     * A course teacher walks that course's own queue.
     *
     * @return void
     */
    public function test_a_course_teacher_walks_that_courses_own_queue(): void {
        [, $ueid] = $this->applicant();
        $this->setUser($this->teacher());

        $scope = $this->scope_for($ueid, $this->instance);

        $this->assertEquals($this->instance->id, $scope->enrolid);
        $this->assertNull($scope->mentees);
        $this->assertEquals(
            (new \moodle_url('/enrol/apply/manage.php', ['id' => (int) $this->instance->id]))->out(false),
            $scope->url->out(false)
        );
    }

    /**
     * So does an administrator, who could open the site-wide one but is looking at this course.
     *
     * @return void
     */
    public function test_an_administrator_walks_that_courses_own_queue_too(): void {
        [, $ueid] = $this->applicant();
        $this->setAdminUser();

        $scope = $this->scope_for($ueid, $this->instance);

        $this->assertEquals($this->instance->id, $scope->enrolid);
        $this->assertNull($scope->mentees);
    }

    /**
     * A mentor walks the users they mentor, and no instance restriction is applied.
     *
     * A mentor cannot open manage.php?id= at all - it calls require_capability() on the course
     * context - so scoping their walk to the instance would build a walk over a queue they are
     * refused, and land them on it after every decision.
     *
     * @return void
     */
    public function test_a_mentor_walks_only_the_users_they_mentor(): void {
        [$applicant, $ueid] = $this->applicant();
        $this->setUser($this->mentor($applicant));

        $scope = $this->scope_for($ueid, $this->instance);

        $this->assertSame(0, $scope->enrolid);
        $this->assertEquals([(int) $applicant->id], $scope->mentees);
        $this->assertEquals(
            (new \moodle_url('/enrol/apply/manage.php'))->out(false),
            $scope->url->out(false)
        );
    }

    /**
     * A mentor who happens to be enrolled in the course still walks their mentees.
     *
     * The measured defect this replaced. Where a decision sends the operator used to be decided
     * by can_access_course() with no capability argument, and a mentor enrolled in the course as
     * anything at all - a learner on it, a teacher without this capability - satisfies that. They
     * were redirected to manage.php?id=, whose require_capability() then threw at them: the
     * decision had been taken and was reported as an exception. Both halves are the test, because
     * the defect lived in the gap between them.
     *
     * @return void
     */
    public function test_a_mentor_enrolled_in_the_course_still_walks_their_mentees(): void {
        [$applicant, $ueid] = $this->applicant();
        $mentor = $this->mentor($applicant);
        $this->getDataGenerator()->enrol_user($mentor->id, $this->course->id, 'student');
        $this->setUser($mentor);

        /* Both preconditions, because the defect lived in the gap between them: the course
           really is open to them, which is all the old test asked, and they really do not hold
           the capability there, which is what manage.php?id= requires before it will render. */
        $this->assertTrue(can_access_course($this->course));
        $this->assertFalse(has_capability(
            'enrol/apply:manageapplications',
            \context_course::instance($this->course->id)
        ));

        $scope = $this->scope_for($ueid, $this->instance);

        $this->assertSame(0, $scope->enrolid);
        $this->assertEquals([(int) $applicant->id], $scope->mentees);
        $this->assertEquals(
            (new \moodle_url('/enrol/apply/manage.php'))->out(false),
            $scope->url->out(false)
        );
    }

    /**
     * A system-level grant with no way into the course walks the site-wide queue.
     *
     * This is the case the old can_access_course() test got right and the reason the capability
     * had to travel with it rather than replace it.
     *
     * @return void
     */
    public function test_a_system_grant_with_no_course_access_walks_the_site_wide_queue(): void {
        [, $hiddeninstance] = $this->course_with_instance(['visible' => 0]);
        [, $ueid] = $this->applicant_on($hiddeninstance);
        $this->setUser($this->site_manager());

        $scope = $this->scope_for($ueid, $hiddeninstance);

        $this->assertSame(0, $scope->enrolid);
        $this->assertNull($scope->mentees);
        $this->assertEquals(
            (new \moodle_url('/enrol/apply/manage.php'))->out(false),
            $scope->url->out(false)
        );
    }

    /**
     * Somebody who may decide but may open no queue is not sent to one that refuses them.
     *
     * Reachable rather than defensive: the capability held in a course context puts them on the
     * review page through can_manage_application(), while not being enrolled and holding no
     * moodle/course:view refuses can_access_course(), and mentoring nobody empties the third
     * scope. Sending them to either queue would report a successful decision as an exception -
     * manage.php?id= throws on require_capability(), and the parameterless one throws on the
     * same capability at system level once the mentee list is empty.
     *
     * The course is hidden here only because that is one more reason can_access_course() says
     * no; it is NOT what makes this branch reachable, and the sibling test below builds the
     * same outcome on a visible course. An earlier version of this docblock said the hidden
     * flag was the mechanism.
     *
     * @return void
     */
    public function test_an_operator_who_can_open_no_queue_is_not_sent_to_one_that_refuses_them(): void {
        [$hidden, $hiddeninstance] = $this->course_with_instance(['visible' => 0]);
        [, $ueid] = $this->applicant_on($hiddeninstance);
        $this->setUser($this->course_manager_locked_out($hidden));

        // The precondition: they really can review it, so this is not a test about a refusal.
        $this->assertInstanceOf(\context::class, queue::require_review_access(queue::application($ueid)));

        $scope = $this->scope_for($ueid, $hiddeninstance);

        $this->assertSame([], $scope->mentees);
        $this->assertStringNotContainsString('/enrol/apply/manage.php', $scope->url->out(false));
        $this->assertEquals(destination::home_page_url()->out(false), $scope->url->out(false));
    }

    /**
     * The walk visits exactly the applications the queue lists, in exactly the queue's order.
     *
     * The one test that holds the walk and the listing together. Sharing code between them
     * would only make them agree on whatever that code says; this asserts the behaviour, so a
     * scope clause, a join or an order that drifts on either side reddens it.
     *
     * @return void
     */
    public function test_the_walk_visits_exactly_what_the_queue_lists_and_in_its_order(): void {
        $this->setAdminUser();
        $base = time() - DAYSECS;

        $ueids = [];
        // Stamped out of creation order, so passing cannot be an accident of insertion order.
        foreach ([4, 1, 5, 2, 3] as $offset) {
            [, $ueid] = $this->applicant();
            $ueids[$offset] = $this->submitted_at($ueid, $base + $offset);
        }
        [, $waiting] = $this->applicant(ENROL_APPLY_USER_WAIT);
        $this->submitted_at($waiting, $base + 6);

        $listed = $this->listed((int) $this->instance->id);
        $walked = $this->walk_from($ueids[3], $this->instance);

        $this->assertSame($listed, $walked);
        // The control: the queue really did list something, so agreeing is not agreeing on nothing.
        $this->assertCount(6, $listed);
    }

    /**
     * The walk has an end in each direction.
     *
     * @return void
     */
    public function test_the_walk_stops_at_both_ends(): void {
        $this->setAdminUser();
        $base = time() - DAYSECS;
        [, $first] = $this->applicant();
        $this->submitted_at($first, $base + 1);
        [, $middle] = $this->applicant();
        $this->submitted_at($middle, $base + 2);
        [, $last] = $this->applicant();
        $this->submitted_at($last, $base + 3);

        $scope = $this->scope_for($middle, $this->instance);

        $this->assertNull(queue::neighbours(queue::application($first), $scope)['previous']);
        $this->assertNull(queue::neighbours(queue::application($last), $scope)['next']);
        // The control: the ends are ends, not a walk that resolves nothing in either direction.
        $this->assertEquals($first, (int) queue::neighbours(queue::application($middle), $scope)['previous']->id);
        $this->assertEquals($last, (int) queue::neighbours(queue::application($middle), $scope)['next']->id);
    }

    /**
     * Applications sharing a submission time are each visited exactly once.
     *
     * enrol_user() stamps timecreated with whole seconds, so a cohort admitted by one script
     * shares a value and this is the ordinary case rather than the edge one. Without the unique
     * final key the walk cannot move within a tied group at all: "later than this timestamp"
     * skips the whole group, and the operator reaches neither the rows before nor the rows
     * after by walking.
     *
     * @return void
     */
    public function test_the_walk_visits_every_application_of_a_tied_group(): void {
        $this->setAdminUser();
        $shared = time() - DAYSECS;

        $ueids = [];
        for ($i = 0; $i < 4; $i++) {
            [, $ueid] = $this->applicant();
            $ueids[] = $this->submitted_at($ueid, $shared);
        }

        $walked = $this->walk_from($ueids[2], $this->instance);

        /* assertSame and NOT assertEqualsCanonicalizing, which was the first spelling here and
           discarded the one property a tied group exists to test. Inside a tie the order is
           fully determined - ascending ue.id, which is the order these were created in - so
           there is nothing to be lenient about. */
        $this->assertSame($ueids, $walked);
    }

    /**
     * Inside a tied group, the neighbour is the ADJACENT row in both directions.
     *
     * The tie-break has to carry the direction, and the walk alone cannot see whether it does:
     * a walk restarts from the true first row and steps forward, so it still enumerates every
     * tied row even when "previous" is wrong. Writing the tie-break as
     * "ORDER BY ue.timecreated DESC, ue.id ASC" is a plausible edit and a real defect - previous
     * from the last of a tied group then jumps past the middle rows to the first - and it leaves
     * the walk test green. This asserts the neighbours themselves.
     *
     * @return void
     */
    public function test_inside_a_tied_group_the_neighbour_is_the_adjacent_row(): void {
        $this->setAdminUser();
        $shared = time() - DAYSECS;

        $ueids = [];
        for ($i = 0; $i < 4; $i++) {
            [, $ueid] = $this->applicant();
            $ueids[] = $this->submitted_at($ueid, $shared);
        }
        $scope = $this->scope_for($ueids[0], $this->instance);

        $last = queue::neighbours(queue::application($ueids[3]), $scope);
        $this->assertEquals($ueids[2], (int) $last['previous']->id);
        $this->assertNull($last['next']);

        $first = queue::neighbours(queue::application($ueids[0]), $scope);
        $this->assertEquals($ueids[1], (int) $first['next']->id);
        $this->assertNull($first['previous']);

        // And from the middle, so neither assertion above is about an end of the queue.
        $middle = queue::neighbours(queue::application($ueids[2]), $scope);
        $this->assertEquals($ueids[1], (int) $middle['previous']->id);
        $this->assertEquals($ueids[3], (int) $middle['next']->id);
    }

    /**
     * A neighbour record carries enough of the applicant to be named.
     *
     * The seam between the two test files: everything in this one reads a neighbour's id, and
     * the renderable's own tests build their records by hand, so the SELECT list that feeds
     * fullname() is asserted by neither. Drop the name fields and both files stay green while
     * every link on the page renders as "Previous: " with no applicant after it - destroying
     * the one property the walk's documented divergence from the on-screen list rests on.
     *
     * @return void
     */
    public function test_a_neighbour_record_names_its_applicant(): void {
        $this->setAdminUser();
        $base = time() - DAYSECS;
        [, $first] = $this->applicant();
        $this->submitted_at($first, $base + 1);
        [$nextapplicant, $second] = $this->applicant();
        $this->submitted_at($second, $base + 2);

        $neighbour = queue::neighbours(
            queue::application($first),
            $this->scope_for($first, $this->instance)
        )['next'];

        $this->assertEquals($second, (int) $neighbour->id);
        $this->assertEquals($nextapplicant->id, (int) $neighbour->userid);
        // Through fullname(), which is what the renderable calls and what needs the name fields.
        $this->assertSame(fullname($nextapplicant), fullname($neighbour));
        $this->assertNotSame('', trim(fullname($neighbour)));
    }

    /**
     * An application that is not awaiting a decision is not walked to.
     *
     * Both exclusions the queue makes, placed between two rows that are walked, so a walk that
     * lost the predicate would visit them rather than merely order them differently.
     *
     * @return void
     */
    public function test_the_walk_skips_an_application_that_is_not_awaiting_a_decision(): void {
        global $DB;

        $this->setAdminUser();
        $base = time() - DAYSECS;
        [, $first] = $this->applicant();
        $this->submitted_at($first, $base + 1);

        [, $active] = $this->applicant(ENROL_USER_ACTIVE);
        $this->submitted_at($active, $base + 2);

        [, $expired] = $this->applicant();
        $DB->update_record('user_enrolments', (object) [
            'id' => $expired,
            'timestart' => $base - DAYSECS,
            'timeend' => time() - HOURSECS,
        ]);
        $this->submitted_at($expired, $base + 3);

        [, $last] = $this->applicant();
        $this->submitted_at($last, $base + 4);

        $walked = $this->walk_from($first, $this->instance);

        $this->assertSame([$first, $last], $walked);
    }

    /**
     * A mentor's walk never reaches somebody they do not mentor.
     *
     * @return void
     */
    public function test_a_mentors_walk_never_reaches_somebody_they_do_not_mentor(): void {
        $base = time() - DAYSECS;
        [, $before] = $this->applicant();
        $this->submitted_at($before, $base + 1);
        [$mentee, $mine] = $this->applicant();
        $this->submitted_at($mine, $base + 2);
        [, $after] = $this->applicant();
        $this->submitted_at($after, $base + 3);

        $this->setUser($this->mentor($mentee));
        $walked = $this->walk_from($mine, $this->instance);

        $this->assertSame([$mine], $walked);

        /* The control: those two applications are reachable, and sit either side of this one -
           so the mentor's walk stopping is the scope working and not an empty fixture. */
        $this->setAdminUser();
        $this->assertSame(
            [$before, $mine, $after],
            $this->walk_from($mine, $this->instance)
        );
    }

    /**
     * The site-wide walk crosses courses; a course's own walk does not.
     *
     * @return void
     */
    public function test_the_site_wide_walk_crosses_courses_and_the_instance_walk_does_not(): void {
        $base = time() - DAYSECS;
        [, $hereinstance] = $this->course_with_instance(['visible' => 0]);
        [, $here] = $this->applicant_on($hereinstance);
        $this->submitted_at($here, $base + 1);
        [, $elsewhereinstance] = $this->course_with_instance(['visible' => 0]);
        [, $elsewhere] = $this->applicant_on($elsewhereinstance);
        $this->submitted_at($elsewhere, $base + 2);

        $this->setUser($this->site_manager());
        $this->assertSame(
            [$here, $elsewhere],
            $this->walk_from($here, $hereinstance)
        );

        // The same data, walked by somebody whose scope is one instance.
        $this->setAdminUser();
        $this->assertSame(
            [$here],
            $this->walk_from($here, $hereinstance)
        );
    }

    /**
     * A course carrying two apply instances gives each of them its own walk.
     *
     * The plugin supports a second instance on purpose, and each has a queue of its own.
     *
     * @return void
     */
    public function test_a_second_apply_instance_in_the_course_has_its_own_walk(): void {
        global $DB;

        $base = time() - DAYSECS;
        [$course, $firstinstance] = $this->course_with_instance(['visible' => 0]);
        [, $first] = $this->applicant_on($firstinstance);
        $this->submitted_at($first, $base + 1);

        $secondid = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());
        $second = $DB->get_record('enrol', ['id' => $secondid], '*', MUST_EXIST);
        [, $other] = $this->applicant_on($second);
        $this->submitted_at($other, $base + 2);

        $this->setAdminUser();
        $this->assertSame([$first], $this->walk_from($first, $firstinstance));
        $this->assertSame([$other], $this->walk_from($other, $second));

        /* The control: one scope does reach both of them, so each instance walk stopping after
           one row is the scope working rather than a fixture with nothing else in it. */
        $this->setUser($this->site_manager());
        $this->assertSame(
            [$first, $other],
            $this->walk_from($first, $firstinstance)
        );
    }

    /**
     * The same operator, on a VISIBLE course, still opens no queue.
     *
     * The control for the sibling above, and the reason its hidden course is incidental. What
     * refuses can_access_course() here is that a role granting only this plugin's capability
     * carries no moodle/course:view and is not an enrolment, so is_viewing() and is_enrolled()
     * are both false whatever the course's visibility says.
     *
     * @return void
     */
    public function test_the_no_queue_case_does_not_need_a_hidden_course(): void {
        [$visible, $visibleinstance] = $this->course_with_instance(['visible' => 1]);
        [, $ueid] = $this->applicant_on($visibleinstance);
        $this->setUser($this->course_manager_locked_out($visible));

        // The precondition: the course really is open to anybody who can get into it.
        $this->assertEquals(1, $visible->visible);

        $scope = $this->scope_for($ueid, $visibleinstance);

        $this->assertSame([], $scope->mentees);
        $this->assertEquals(destination::home_page_url()->out(false), $scope->url->out(false));
    }

    /**
     * A teacher whose own enrolment is suspended is not sent to a queue that refuses them.
     *
     * can_access_course() counts a suspended or expired enrolment as access, because its
     * $onlyactive parameter defaults to false; require_login($course), which manage.php?id=
     * calls before require_capability(), does not. Measured on 5.1 and 5.2: for this operator
     * the three-argument form returns true and require_login() raises require_login_exception
     * "Not enrolled", so the decision was applied and then reported as a bounce to the course
     * enrolment page. The role assignment survives the suspension, which is what core does, so
     * they still hold the capability and can still legitimately decide.
     *
     * @return void
     */
    public function test_a_teacher_whose_own_enrolment_is_suspended_opens_no_queue(): void {
        global $DB;

        [, $ueid] = $this->applicant();
        $teacher = $this->teacher();
        $DB->set_field(
            'user_enrolments',
            'status',
            ENROL_USER_SUSPENDED,
            ['userid' => $teacher->id, 'enrolid' => $DB->get_field(
                'enrol',
                'id',
                ['courseid' => $this->course->id, 'enrol' => 'manual'],
                MUST_EXIST
            )]
        );
        $this->setUser($teacher);

        /* The two preconditions, because the defect is the gap between them: they still hold
           the capability, and the enrolment that would let them into the course is not active. */
        $coursecontext = \context_course::instance($this->course->id);
        $this->assertTrue(has_capability('enrol/apply:manageapplications', $coursecontext));
        $this->assertFalse(is_enrolled($coursecontext, null, '', true));

        $scope = $this->scope_for($ueid, $this->instance);

        $this->assertSame(0, $scope->enrolid);
        $this->assertStringNotContainsString('/enrol/apply/manage.php', $scope->url->out(false));
    }

    /**
     * A mentor is not given a walk over an application none of their mentees made.
     *
     * The scope has to CONTAIN the application it was derived for. Anchored outside its own
     * set, neighbours() compares the anchor against rows it is not among and returns the
     * insertion point instead - so the page offers a "next" belonging to a different course,
     * the application on screen is reachable from neither of its links, and the walk cannot
     * lead back to it. That is the dead end this navigation exists to remove.
     *
     * @return void
     */
    public function test_an_application_outside_the_mentees_gives_no_walk_at_all(): void {
        $base = time() - DAYSECS;
        [, $elsewhereinstance] = $this->course_with_instance(['visible' => 1]);
        [$mentee, $menteeueid] = $this->applicant_on($elsewhereinstance);
        $this->submitted_at($menteeueid, $base + 1);

        // An application in THIS course, made by somebody the operator does not mentor.
        [, $strangerueid] = $this->applicant();
        $this->submitted_at($strangerueid, $base + 2);

        /* The operator mentors one person and holds the capability in this course through a
           role assignment that is not an enrolment, so they may decide the stranger's
           application without being able to open any queue that lists it. */
        $operator = $this->course_manager_locked_out($this->course);
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applymentor2']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $operator->id, \context_user::instance($mentee->id)->id);
        $this->setUser($operator);

        // The precondition: they really may decide it, so this is not a test about a refusal.
        $this->assertInstanceOf(
            \context::class,
            queue::require_review_access(queue::application($strangerueid))
        );
        // And they really do mentor somebody, which is what used to hand them the wrong walk.
        $this->assertEquals([(int) $mentee->id], applications::get_mentees());

        $scope = $this->scope_for($strangerueid, $this->instance);
        $neighbours = queue::neighbours(queue::application($strangerueid), $scope);

        $this->assertSame([], $scope->mentees);
        $this->assertNull($neighbours['previous']);
        $this->assertNull($neighbours['next']);

        /* The control: their mentee's own application still walks, so the empty walk above is
           the membership test working rather than the mentee scope being broken outright. */
        $this->assertSame([$menteeueid], $this->walk_from($menteeueid, $elsewhereinstance));
    }

    /**
     * The walk never steps onto an enrolment belonging to another enrolment method.
     *
     * The site-wide scope has no instance clause, so e.enrol = 'apply' is the only thing
     * keeping it inside this plugin - and every other test builds its fixture entirely out of
     * apply enrolments, so none of them could tell. A suspended manual participant is enough:
     * queue::application() filters the method, so following such a link lands on the "no
     * application" page.
     *
     * @return void
     */
    public function test_the_site_wide_walk_does_not_step_onto_another_enrolment_method(): void {
        global $DB;

        $base = time() - DAYSECS;
        [$course, $instance] = $this->course_with_instance(['visible' => 0]);
        [, $application] = $this->applicant_on($instance);
        $this->submitted_at($application, $base + 1);

        // A suspended participant of the same course, enrolled by the manual method.
        $participant = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($participant->id, $course->id, 'student');
        $manualueid = (int) $DB->get_field_sql(
            "SELECT ue.id
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :userid AND e.courseid = :courseid AND e.enrol = :enrol",
            ['userid' => $participant->id, 'courseid' => $course->id, 'enrol' => 'manual'],
            MUST_EXIST
        );
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['id' => $manualueid]);
        $this->submitted_at($manualueid, $base + 2);

        $this->setUser($this->site_manager());

        /* The precondition: that row really does look like an application to everything except
           the method filter - it is later than the anchor, not active, and carries no expiry. */
        $manual = $DB->get_record('user_enrolments', ['id' => $manualueid], '*', MUST_EXIST);
        $this->assertNotEquals(ENROL_USER_ACTIVE, (int) $manual->status);
        $this->assertEquals(0, (int) $manual->timeend);

        /* Asserted on the neighbour directly and not only through the walk. Without the method
           filter the walk does fail - queue::application() filters it, so the next step hands
           null to neighbours() - but it fails as a TypeError inside a test helper, which names
           neither the method filter nor the row that got through. */
        $scope = $this->scope_for($application, $instance);
        $this->assertNull(queue::neighbours(queue::application($application), $scope)['next']);
        $this->assertSame([$application], $this->walk_from($application, $instance));
    }
    /**
     * The earlier-applications lookup returns this applicant's records and nobody else's.
     *
     * Both scoping clauses are a disclosure boundary and neither was held by anything: the
     * capability gate on the panel decides WHETHER it renders, and these decide WHOSE rows it
     * renders. The failure would also be silent - fix_sql_params() tolerates surplus named
     * parameters, so dropping a clause while keeping its parameter runs clean.
     *
     * @return void
     */
    public function test_prior_applications_are_scoped_to_one_applicant_and_one_course(): void {
        global $DB;

        $this->resetAfterTest();

        $courseone = $this->getDataGenerator()->create_course();
        $coursetwo = $this->getDataGenerator()->create_course();
        $mine = $this->getDataGenerator()->create_user();
        $theirs = $this->getDataGenerator()->create_user();

        $record = function (int $courseid, int $userid, int $when) use ($DB): int {
            return (int) $DB->insert_record('enrol_apply_submission', (object) [
                'courseid' => $courseid,
                'userid' => $userid,
                'enrolid' => 0,
                'userenrolmentid' => 0,
                'status' => \enrol_apply\local\submission::STATUS_CANCELLED,
                'comment' => '',
                'userinfodata' => '',
                'outcomemessage' => '',
                'decidedgroups' => '',
                'decidedrole' => 0,
                'decidedby' => 0,
                'timecreated' => $when,
                'timedecided' => $when,
            ]);
        };

        $wanted = $record($courseone->id, $mine->id, 1700000000);
        $current = $record($courseone->id, $mine->id, 1700000100);
        $othercourse = $record($coursetwo->id, $mine->id, 1700000000);
        $otherperson = $record($courseone->id, $theirs->id, 1700000000);

        $found = queue::prior_applications((int) $courseone->id, (int) $mine->id, $current);
        $ids = array_map('intval', array_keys($found));

        $this->assertContains($wanted, $ids);
        // The application being reviewed is not listed as one of its own predecessors.
        $this->assertNotContains($current, $ids, 'the current application must be excluded');
        $this->assertNotContains($othercourse, $ids, 'another course must not be listed');
        $this->assertNotContains($otherperson, $ids, "another applicant's record must not be listed");
    }

    /**
     * A pseudonymised applicant gathers nothing.
     *
     * @return void
     */
    public function test_prior_applications_of_a_pseudonymised_record_are_not_gathered(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $this->assertSame([], queue::prior_applications((int) $course->id, 0, 0));
    }

    /**
     * The list is bounded, because nothing else bounds it.
     *
     * The natural key is deliberately not unique and a determined re-applicant accumulates rows
     * without limit - measured on the live site, one person holds eight records in one course.
     *
     * @return void
     */
    public function test_prior_applications_are_bounded(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        foreach (range(1, queue::PRIOR_APPLICATIONS_SHOWN + 3) as $n) {
            $DB->insert_record('enrol_apply_submission', (object) [
                'courseid' => $course->id,
                'userid' => $user->id,
                'enrolid' => 0,
                'userenrolmentid' => 0,
                'status' => \enrol_apply\local\submission::STATUS_CANCELLED,
                'comment' => '',
                'userinfodata' => '',
                'outcomemessage' => '',
                'decidedgroups' => '',
                'decidedrole' => 0,
                'decidedby' => 0,
                'timecreated' => 1700000000 + $n,
                'timedecided' => 1700000000 + $n,
            ]);
        }

        $found = queue::prior_applications((int) $course->id, (int) $user->id, 0);

        $this->assertCount(queue::PRIOR_APPLICATIONS_SHOWN, $found);
    }
}
