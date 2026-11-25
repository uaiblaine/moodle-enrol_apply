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

namespace enrol_apply;

use enrol_apply\local\groupscope;
use enrol_apply\local\queue;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass targets below resolve when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

/**
 * A reader whose own enrolment in the course grants no access decides and views nothing there.
 *
 * A role assignment survives the suspension or the end of the enrolment that gave it: core never
 * unassigns on suspend, and under the plugin's default expiredaction KEEP an ended row keeps
 * status 0 and its role. Such a teacher still holds enrol/apply:manageapplications in the course,
 * while every page behind require_login($course) refuses them. The two doors that do not call it,
 * manage.php?userenrol= (open to mentors on purpose) and core's user/action_redir.php bulk route,
 * reached the decision through can_manage_application()'s course branch. That branch, and the
 * viewer branch of can_view_application(), now refuse a reader who is enrolled without access and
 * does not otherwise view the course ({@see groupscope::lapsed()}).
 *
 * The controls are the readers who must keep deciding: an active teacher, a category manager who
 * is not enrolled, one whose own enrolment lapsed but who views the course by their role, a
 * mentor, and an operator holding only the plugin's capability through a category role.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
#[CoversClass(\enrol_apply\local\groupscope::class)]
#[CoversClass(\enrol_apply\local\queue::class)]
#[CoversClass(\enrol_apply\bulk\decision_operation::class)]
final class lapsed_decider_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass The course. */
    private $course;

    /** @var \context_course Its context. */
    private $context;

    /** @var \stdClass Its apply method, a learner one. */
    private $instance;

    /**
     * A course with one learner apply method.
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
        $this->context = \context_course::instance($this->course->id);
        $id = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Write a pending application through the plugin's own apply(), as the administrator.
     *
     * @param \stdClass|null $instance The method applied to, the course's learner one by default.
     * @return array The user enrolment id and the applicant.
     */
    private function apply_for(?\stdClass $instance = null): array {
        global $USER;

        $previous = $USER;
        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $written = (new \ReflectionMethod(\enrol_apply_plugin::class, 'apply'))->invoke(
            $this->plugin,
            $instance ?? $this->instance,
            (int) $applicant->id,
            (object) ['applydescription' => 'Please'],
            0,
            submission::KEY_NONE
        );
        $sink->close();
        $this->setUser($previous);

        return [(int) $written['userenrolmentid'], $applicant];
    }

    /**
     * The ways an enrolment stops granting access while its role stays.
     *
     * @return array The way, keyed by case name.
     */
    public static function lapse_provider(): array {
        return [
            'suspended' => ['suspended'],
            'ended, status still active (expiredaction KEEP)' => ['ended'],
        ];
    }

    /**
     * Let a user's manual enrolment in the course lapse, through core's own write, as the administrator.
     *
     * @param \stdClass $user The enrolled user.
     * @param string $how 'suspended' or 'ended'.
     * @return void
     */
    private function lapse(\stdClass $user, string $how): void {
        global $DB, $USER;

        $previous = $USER;
        $this->setAdminUser();
        $manual = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        if ($how === 'suspended') {
            enrol_get_plugin('manual')->update_user_enrol($manual, (int) $user->id, ENROL_USER_SUSPENDED);
        } else {
            enrol_get_plugin('manual')->update_user_enrol(
                $manual,
                (int) $user->id,
                ENROL_USER_ACTIVE,
                time() - 30 * DAYSECS,
                time() - DAYSECS
            );
        }
        $this->setUser($previous);
    }

    /**
     * An editing teacher of the course whose own enrolment has lapsed, logged in.
     *
     * The two preconditions are asserted, because the defect lies in the gap between them: the role,
     * and with it the capability, survives, while the enrolment grants no access.
     *
     * @param string $how 'suspended' or 'ended'.
     * @return \stdClass The teacher.
     */
    private function lapsed_teacher(string $how): \stdClass {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->lapse($teacher, $how);
        $this->setUser($teacher);

        $this->assertTrue(has_capability(groupscope::DECIDE, $this->context), 'the role survives');
        $this->assertTrue(is_enrolled($this->context, null, '', false), 'still enrolled');
        $this->assertFalse(is_enrolled($this->context, null, '', true), 'but with no access');

        return $teacher;
    }

    /**
     * The application's enrolment and its newest record, as a decision would change them.
     *
     * @param int $ueid User enrolment id.
     * @return array The enrolment's status and dates, and the record's status, decider and decision date.
     */
    private function state(int $ueid): array {
        global $DB;

        $ue = $DB->get_record('user_enrolments', ['id' => $ueid], 'id, status, timestart, timeend');
        $record = $DB->get_record(
            'enrol_apply_submission',
            ['userenrolmentid' => $ueid],
            'id, status, decidedby, timedecided, outcomemessage',
            MUST_EXIST
        );

        return [$ue ? (array) $ue : null, (array) $record];
    }

    /**
     * The review page refuses a teacher whose own enrolment lapsed: queue::require_review_access(),
     * the gate of manage.php?userenrol=, which does not call require_login($course).
     *
     * Changes that must make it fail: the course branch of can_manage_application() deciding on the
     * capability alone (gate LP1).
     *
     * @param string $how The way the enrolment lapsed.
     * @return void
     */
    #[DataProvider('lapse_provider')]
    public function test_the_review_page_refuses_a_teacher_whose_enrolment_lapsed(string $how): void {
        [$ueid] = $this->apply_for();
        $this->lapsed_teacher($how);

        $this->expectException(\required_capability_exception::class);
        queue::require_review_access(queue::application($ueid));
    }

    /**
     * Every decision the decision methods name, by every way an enrolment lapses.
     *
     * @return array The method and the way, keyed by case name.
     */
    public static function decision_provider(): array {
        $cases = [];
        foreach (['confirm_enrolment', 'wait_enrolment', 'cancel_enrolment'] as $method) {
            foreach (self::lapse_provider() as $name => [$how]) {
                $cases["{$method}, {$name}"] = [$method, $how];
            }
        }

        return $cases;
    }

    /**
     * No decision method acts for a teacher whose own enrolment lapsed: the enrolment and the durable
     * record are left exactly as they were, and the method reports nothing decided.
     *
     * These are the methods manage.php's POST and the bulk operations hand every row to.
     *
     * Changes that must make it fail: the course branch of can_manage_application() deciding on the
     * capability alone (gate LP1).
     *
     * @param string $method The decision method.
     * @param string $how The way the enrolment lapsed.
     * @return void
     */
    #[DataProvider('decision_provider')]
    public function test_no_decision_reaches_the_application_for_a_lapsed_teacher(string $method, string $how): void {
        [$ueid, $applicant] = $this->apply_for();
        $before = $this->state($ueid);
        $this->lapsed_teacher($how);
        $this->assertFalse($this->plugin->can_manage_application((int) $this->course->id, (int) $applicant->id, $ueid));

        $sink = $this->redirectMessages();
        $decided = $this->plugin->{$method}([$ueid], 'Welcome', ['note' => 'Lapsed note']);
        $sink->close();

        $this->assertSame(0, $decided);
        $this->assertSame($before, $this->state($ueid));
    }

    /**
     * Core's bulk route, user/action_redir.php, which calls no require_login(): the operation's own
     * capability gate passes for the lapsed teacher (the role survives), and the per-row check inside
     * the decision method decides nothing; the report says so.
     *
     * Changes that must make it fail: the course branch of can_manage_application() deciding on the
     * capability alone (gate LP1).
     *
     * @param string $how The way the enrolment lapsed.
     * @return void
     */
    #[DataProvider('lapse_provider')]
    public function test_the_bulk_route_decides_nothing_for_a_lapsed_teacher(string $how): void {
        global $PAGE;

        [$ueid, $applicant] = $this->apply_for();
        $before = $this->state($ueid);
        $this->lapsed_teacher($how);

        $PAGE->set_url('/user/index.php', ['id' => $this->course->id]);
        $manager = new \course_enrolment_manager($PAGE, $this->course, $this->instance->id);
        $users = $manager->get_users_enrolments([(int) $applicant->id]);
        $operation = new \enrol_apply\bulk\confirm_operation($manager, $this->plugin);
        \core\notification::fetch();

        $sink = $this->redirectMessages();
        $this->assertTrue($operation->process($manager, $users, (object) ['outcomemessage' => '']));
        $sink->close();

        $this->assertSame($before, $this->state($ueid));
        $messages = array_map(static fn($n): string => $n->get_message(), \core\notification::fetch());
        $this->assertSame([get_string('bulkunchanged', 'enrol_apply', 1)], $messages);
    }

    /**
     * A moderator recruited by a deciding method (the role it gave stamped enrol_apply) whose row on
     * that method is suspended decides nothing; while the row was active they decided.
     *
     * Changes that must make it fail: the course branch of can_manage_application() deciding on the
     * capability alone (gate LP1).
     *
     * @return void
     */
    public function test_a_recruited_moderator_whose_row_is_suspended_is_refused(): void {
        global $DB;

        $editingteacher = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $recruitmentid = $this->plugin->add_instance($this->course, [
            'name' => 'Moderators',
            'roleid' => $editingteacher,
        ] + $this->plugin->get_instance_defaults());
        $recruitment = $DB->get_record('enrol', ['id' => $recruitmentid], '*', MUST_EXIST);
        [$moderatorue, $moderator] = $this->apply_for($recruitment);
        $this->setAdminUser();
        $sink = $this->redirectMessages();
        $this->assertSame(1, $this->plugin->confirm_enrolment([$moderatorue]));
        $sink->close();
        $this->assertTrue($DB->record_exists('role_assignments', [
            'userid' => $moderator->id,
            'roleid' => $editingteacher,
            'contextid' => $this->context->id,
            'component' => 'enrol_apply',
            'itemid' => $recruitmentid,
        ]), 'the role the method gave is stamped');

        [$ueid, $applicant] = $this->apply_for();
        $this->setUser($moderator);
        $this->assertTrue(
            $this->plugin->can_manage_application((int) $this->course->id, (int) $applicant->id, $ueid),
            'the control: an active moderator decides'
        );

        $this->setAdminUser();
        $this->plugin->update_user_enrol($recruitment, (int) $moderator->id, ENROL_USER_SUSPENDED);
        $this->setUser($moderator);
        $this->assertTrue(has_capability(groupscope::DECIDE, $this->context), 'the stamped role survives');

        $before = $this->state($ueid);
        $this->assertFalse($this->plugin->can_manage_application((int) $this->course->id, (int) $applicant->id, $ueid));
        $sink = $this->redirectMessages();
        $this->assertSame(0, $this->plugin->confirm_enrolment([$ueid]));
        $sink->close();
        $this->assertSame($before, $this->state($ueid));
    }

    /**
     * A viewer (enrol/apply:viewapplications alone) whose own enrolment lapsed may not open the review
     * page; an active one may.
     *
     * Changes that must make it fail: the viewer branch of can_view_application() reading the
     * capability alone (gate LP2).
     *
     * @param string $how The way the enrolment lapsed.
     * @return void
     */
    #[DataProvider('lapse_provider')]
    public function test_a_viewer_whose_enrolment_lapsed_may_not_open_the_review_page(string $how): void {
        [$ueid, $applicant] = $this->apply_for();
        $role = $this->getDataGenerator()->create_role(['shortname' => 'lapsedviewer']);
        assign_capability(groupscope::VIEW, CAP_ALLOW, $role, \context_system::instance()->id, true);
        $active = $this->getDataGenerator()->create_and_enrol($this->course, 'lapsedviewer');
        $lapsed = $this->getDataGenerator()->create_and_enrol($this->course, 'lapsedviewer');
        $this->lapse($lapsed, $how);

        $this->setUser($active);
        $this->assertTrue(
            $this->plugin->can_view_application((int) $this->course->id, (int) $applicant->id, $ueid),
            'the control: an active viewer looks'
        );
        $this->assertInstanceOf(\context_course::class, queue::require_review_access(queue::application($ueid)));

        $this->setUser($lapsed);
        $this->assertTrue(has_capability(groupscope::VIEW, $this->context), 'the role survives');
        $this->assertFalse($this->plugin->can_view_application((int) $this->course->id, (int) $applicant->id, $ueid));
        $this->expectException(\required_capability_exception::class);
        queue::require_review_access(queue::application($ueid));
    }

    /**
     * Decide one application as the current user and say whether it moved.
     *
     * @param int $ueid User enrolment id.
     * @param int $applicantid The applicant.
     * @return void
     */
    private function assert_decides(int $ueid, int $applicantid): void {
        global $DB;

        $this->assertTrue($this->plugin->can_manage_application((int) $this->course->id, $applicantid, $ueid));
        $sink = $this->redirectMessages();
        $this->assertSame(1, $this->plugin->wait_enrolment([$ueid]));
        $sink->close();
        $this->assertSame(ENROL_APPLY_USER_WAIT, (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid]));
    }

    /**
     * An active teacher decides: the control for every refusal above, and the reader the predicate's
     * "not actively enrolled" half exists to keep.
     *
     * Changes that must make it fail: the predicate reading any enrolment as lapsed (gate LP4).
     *
     * @return void
     */
    public function test_an_active_teacher_still_decides(): void {
        [$ueid, $applicant] = $this->apply_for();
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));

        $this->assertInstanceOf(\context_course::class, queue::require_review_access(queue::application($ueid)));
        $this->assert_decides($ueid, (int) $applicant->id);
    }

    /**
     * A manager of the course's category who is not enrolled decides.
     *
     * @return void
     */
    public function test_a_category_manager_who_is_not_enrolled_still_decides(): void {
        [$ueid, $applicant] = $this->apply_for();
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id, \context_coursecat::instance($this->course->category)->id);
        $this->setUser($manager);
        $this->assertFalse(is_enrolled($this->context, null, '', false), 'the precondition: not enrolled');

        $this->assert_decides($ueid, (int) $applicant->id);
    }

    /**
     * A manager of the category whose own enrolment in the course is suspended still decides: their
     * role views the course (moodle/course:view), as require_login($course) lets them in.
     *
     * Changes that must make it fail: the predicate ignoring is_viewing() (gate LP3).
     *
     * @return void
     */
    public function test_a_category_manager_whose_enrolment_lapsed_still_decides(): void {
        [$ueid, $applicant] = $this->apply_for();
        $manager = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->getDataGenerator()->role_assign('manager', $manager->id, \context_coursecat::instance($this->course->category)->id);
        $this->lapse($manager, 'suspended');
        $this->setUser($manager);
        $this->assertFalse(is_enrolled($this->context, null, '', true), 'the precondition: no active enrolment');
        $this->assertTrue(is_viewing($this->context), 'the precondition: their role views the course');

        $this->assert_decides($ueid, (int) $applicant->id);
    }

    /**
     * A mentor, holding the capability in the applicant's own user context, decides: that level holds
     * no course access by design.
     *
     * @return void
     */
    public function test_a_mentor_still_decides(): void {
        [$ueid, $applicant] = $this->apply_for();
        $mentor = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role(['shortname' => 'lapsedmentor']);
        set_role_contextlevels($role, [CONTEXT_USER]);
        assign_capability(groupscope::DECIDE, CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $mentor->id, \context_user::instance($applicant->id)->id);
        $this->setUser($mentor);

        $this->assert_decides($ueid, (int) $applicant->id);
    }

    /**
     * An operator holding only the plugin's capability through a category role, neither enrolled nor
     * viewing the course, decides: somebody enrolled nowhere has no enrolment to have lapsed.
     *
     * Changes that must make it fail: the predicate reading "no active enrolment" alone, without
     * asking for an enrolment at all (gate LP5).
     *
     * @return void
     */
    public function test_an_operator_holding_only_the_plugins_capability_through_a_category_role_still_decides(): void {
        [$ueid, $applicant] = $this->apply_for();
        $operator = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role(['shortname' => 'lapsedoperator']);
        assign_capability(groupscope::DECIDE, CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $operator->id, \context_coursecat::instance($this->course->category)->id);
        $this->setUser($operator);
        $this->assertFalse(is_viewing($this->context), 'the precondition: their role does not view the course');
        $this->assertFalse(is_enrolled($this->context, null, '', false), 'the precondition: not enrolled');

        $this->assertInstanceOf(\context_course::class, queue::require_review_access(queue::application($ueid)));
        $this->assert_decides($ueid, (int) $applicant->id);
    }
}
