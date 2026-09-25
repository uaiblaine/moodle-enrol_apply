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
 * Tests for the action icon this plugin adds to the course participants page.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

/**
 * The action icon this plugin adds to the course participants page.
 *
 * Built the way core's participants table builds it, from a real course_enrolment_manager
 * and the user enrolment rows it returns, following core's own tests of this method
 * ({@see \enrol_manual\lib_test::test_get_user_enrolment_actions()}). Like them it sets
 * $PAGE->url: the parent method reads it, and an unset url raises a debugging() call.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
final class user_enrolment_actions_test extends \advanced_testcase {
    /** @var \stdClass Course the apply instance belongs to. */
    protected $course;

    /** @var \stdClass The enrol_apply instance record. */
    protected $instance;

    /** @var \enrol_apply_plugin The plugin under test. */
    protected $plugin;

    /**
     * A course carrying one enabled apply enrolment instance.
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

        // The page core's participants table is rendered on, and what keeps $PAGE->url set.
        $PAGE->set_url('/user/index.php', ['id' => $this->course->id]);
    }

    /**
     * An applicant on this course's apply instance, left in the given state.
     *
     * @param int $status One of ENROL_USER_SUSPENDED, ENROL_APPLY_USER_WAIT or ENROL_USER_ACTIVE.
     * @return \stdClass The user record.
     */
    protected function applicant(int $status = ENROL_USER_SUSPENDED): \stdClass {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);

        if ($status !== ENROL_USER_SUSPENDED) {
            $DB->set_field(
                'user_enrolments',
                'status',
                $status,
                ['userid' => $user->id, 'enrolid' => $this->instance->id]
            );
        }

        return $user;
    }

    /**
     * The actions core would render for this user's apply enrolment, as it renders them.
     *
     * @param \stdClass $user The enrolled user.
     * @return array The user_enrolment_action objects, in the order the page shows them.
     */
    protected function actions_for(\stdClass $user): array {
        global $PAGE;

        $manager = new \course_enrolment_manager($PAGE, $this->course);
        $userenrolments = $manager->get_user_enrolments($user->id);
        $this->assertCount(1, $userenrolments);

        $ue = reset($userenrolments);

        return $this->plugin->get_user_enrolment_actions($manager, $ue);
    }

    /**
     * The titles of those actions, which is what an operator reads off the icons.
     *
     * @param \stdClass $user The enrolled user.
     * @return array Action titles.
     */
    protected function action_titles(\stdClass $user): array {
        return array_map(
            static fn(\user_enrolment_action $action): string => $action->get_title(),
            $this->actions_for($user)
        );
    }

    /**
     * A teacher of the course, who may decide its applications.
     *
     * @return \stdClass The teacher user record.
     */
    protected function teacher(): \stdClass {
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');

        return $teacher;
    }

    /**
     * A pending application is offered the decision icon, pointing at its own review page.
     *
     * @return void
     */
    public function test_a_pending_application_is_offered_the_decision_icon(): void {
        global $DB;

        $applicant = $this->applicant();
        $this->setUser($this->teacher());

        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        $actions = $this->actions_for($applicant);
        $decide = end($actions);

        $this->assertSame(get_string('decideapplication', 'enrol_apply'), $decide->get_title());
        $this->assertSame(
            (new \moodle_url('/enrol/apply/manage.php', ['userenrol' => $ueid]))->out(false),
            $decide->get_url()->out(false)
        );
    }

    /**
     * The icon is appended to core's own two, which the override must not swallow.
     *
     * Core builds edit and unenrol from allow_manage() and allow_unenrol_user(), both of
     * which this plugin leaves true, so an override that returned only its own action would
     * silently take two working controls away from every apply row on the page.
     *
     * @return void
     */
    public function test_the_icon_is_added_to_cores_own_actions(): void {
        $applicant = $this->applicant();
        $this->setUser($this->teacher());

        $this->assertSame(
            [
                get_string('editenrolment', 'enrol'),
                get_string('unenrol', 'enrol'),
                get_string('decideapplication', 'enrol_apply'),
            ],
            $this->action_titles($applicant)
        );
    }

    /**
     * A waiting-list application is offered it too.
     *
     * The row core paints with a green "Active" badge, because its switch has cases for 0 and
     * 1 and no default arm - so on that row the icon is the only thing saying a decision is
     * still owed.
     *
     * @return void
     */
    public function test_a_waiting_list_application_is_offered_the_decision_icon(): void {
        global $DB;

        $applicant = $this->applicant(ENROL_APPLY_USER_WAIT);

        /* Precondition: applicant() sets status 2 through an unchecked set_field(), and the
           icon is offered on status 1 as well, so without this the test could pass on a row
           still at status 1. */
        $this->assertEquals(
            ENROL_APPLY_USER_WAIT,
            (int) $DB->get_field(
                'user_enrolments',
                'status',
                ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
                MUST_EXIST
            )
        );

        $this->setUser($this->teacher());

        $this->assertContains(get_string('decideapplication', 'enrol_apply'), $this->action_titles($applicant));
    }

    /**
     * An approved application is not, so the icon does not follow the applicant for ever.
     *
     * @return void
     */
    public function test_an_approved_application_is_not_offered_the_decision_icon(): void {
        $applicant = $this->applicant(ENROL_USER_ACTIVE);
        $this->setUser($this->teacher());

        $titles = $this->action_titles($applicant);

        $this->assertNotContains(get_string('decideapplication', 'enrol_apply'), $titles);
        // Core's own two are still there, so the absence above is this gate and not a fatal.
        $this->assertContains(get_string('editenrolment', 'enrol'), $titles);
    }

    /**
     * Nor is an approved enrolment whose period has since run out.
     *
     * Under a suspend expiredaction, process_expirations() re-suspends such a row, so on
     * status alone it looks like a fresh application; only the timeend clause of
     * {@see \enrol_apply\local\queue::is_awaiting_decision()} excludes it. Deciding it would
     * cancel or re-approve a finished enrolment.
     *
     * @return void
     */
    public function test_an_expired_enrolment_is_not_offered_the_decision_icon(): void {
        global $DB;

        $applicant = $this->applicant();
        $DB->set_field(
            'user_enrolments',
            'timeend',
            time() - DAYSECS,
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id]
        );
        $this->setUser($this->teacher());

        $titles = $this->action_titles($applicant);

        $this->assertNotContains(get_string('decideapplication', 'enrol_apply'), $titles);
        $this->assertContains(get_string('editenrolment', 'enrol'), $titles);
    }

    /**
     * Somebody who may manage enrolments here but may not decide applications is not offered it.
     *
     * The capability is prohibited rather than merely absent, and the operator keeps
     * enrol/apply:manage and enrol/apply:unenrol, so core's own two icons are still built:
     * that is what makes the missing third one this gate rather than a broken override.
     *
     * @return void
     */
    public function test_the_icon_needs_the_deciding_capability_in_the_course(): void {
        $applicant = $this->applicant();

        $context = \context_course::instance($this->course->id);
        $operator = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/course:enrolreview', CAP_ALLOW, $roleid, $context->id, true);
        assign_capability('enrol/apply:manage', CAP_ALLOW, $roleid, $context->id, true);
        assign_capability('enrol/apply:unenrol', CAP_ALLOW, $roleid, $context->id, true);
        assign_capability('enrol/apply:manageapplications', CAP_PROHIBIT, $roleid, $context->id, true);
        role_assign($roleid, $operator->id, $context->id);
        $this->setUser($operator);

        $this->assertSame(
            [get_string('editenrolment', 'enrol'), get_string('unenrol', 'enrol')],
            $this->action_titles($applicant)
        );
    }

    /**
     * Disabling the plugin site wide does not withdraw the icon, by design.
     *
     * The manager resolves plugins through get_enrolment_plugins(false), so core's own Edit and
     * Unenrol icons render on a disabled plugin's rows too, and manage.php has no enabled
     * check; hiding the icon alone would hide the way in to a queue that still works
     * ({@see \enrol_apply_plugin::get_user_enrolment_actions()}).
     *
     * @return void
     */
    public function test_the_icon_survives_the_plugin_being_disabled_site_wide(): void {
        $applicant = $this->applicant();
        $this->setUser($this->teacher());

        $enabled = array_keys(enrol_get_plugins(true));
        set_config('enrol_plugins_enabled', implode(',', array_diff($enabled, ['apply'])));

        // Precondition: the plugin really is disabled.
        $this->assertArrayNotHasKey('apply', enrol_get_plugins(true));

        $this->assertContains(get_string('decideapplication', 'enrol_apply'), $this->action_titles($applicant));
    }

    /**
     * A mentor of the applicant is offered no decision icon.
     *
     * The icon reads the capability in the course context, as get_bulk_operations() does, and
     * deliberately not through can_manage_application(), whose third level is the applicant's
     * user context. This is the only test here with a user-context assignment, so it is the
     * one that fails if the gate is widened to can_manage_application().
     *
     * The mentor also holds moodle/course:enrolreview, enrol/apply:manage and
     * enrol/apply:unenrol in the course, so core's own two icons are still built and the
     * missing third one is down to this gate.
     *
     * @return void
     */
    public function test_a_mentor_is_offered_no_icon_in_the_course(): void {
        $applicant = $this->applicant();

        $context = \context_course::instance($this->course->id);
        $mentor = $this->getDataGenerator()->create_user();

        $courserole = $this->getDataGenerator()->create_role();
        assign_capability('moodle/course:enrolreview', CAP_ALLOW, $courserole, $context->id, true);
        assign_capability('enrol/apply:manage', CAP_ALLOW, $courserole, $context->id, true);
        assign_capability('enrol/apply:unenrol', CAP_ALLOW, $courserole, $context->id, true);
        role_assign($courserole, $mentor->id, $context->id);

        $mentorrole = $this->getDataGenerator()->create_role([
            'shortname' => 'applymentor',
            'name' => 'Apply mentor',
            'archetype' => '',
        ]);
        set_role_contextlevels($mentorrole, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $mentorrole, \context_system::instance()->id);
        role_assign($mentorrole, $mentor->id, \context_user::instance($applicant->id)->id);

        $this->setUser($mentor);

        // The precondition: they really are a mentor, so the widened gate would admit them.
        $this->assertTrue(
            $this->plugin->can_manage_application((int) $this->course->id, (int) $applicant->id),
            'the fixture does not reach can_manage_application(), so this test proves nothing'
        );

        $this->assertSame(
            [get_string('editenrolment', 'enrol'), get_string('unenrol', 'enrol')],
            $this->action_titles($applicant)
        );
    }

    /**
     * The link it points at admits the teacher who was offered it.
     *
     * The icon and the page it opens read the same capability differently - the course
     * context here, can_manage_application()'s three levels in
     * {@see \enrol_apply\local\queue::require_review_access()} - so the two can disagree; a
     * page that required the capability in the applicant's user context alone would refuse a
     * course teacher.
     *
     * @return void
     */
    public function test_the_page_the_icon_opens_admits_the_teacher_it_was_offered_to(): void {
        $applicant = $this->applicant();
        $this->setUser($this->teacher());

        $actions = $this->actions_for($applicant);
        $decide = end($actions);
        $userenrol = (int) $decide->get_url()->param('userenrol');

        $application = \enrol_apply\local\queue::application($userenrol);
        $this->assertNotNull($application);

        // Throws if it refuses, which is how the review page reports a refusal.
        $context = \enrol_apply\local\queue::require_review_access($application);
        $this->assertEquals(\context_course::instance($this->course->id)->id, $context->id);
    }
}
