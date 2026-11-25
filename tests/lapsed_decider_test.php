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
 * The second half: a reader admitted by another level (a lapsed teacher who mentors the applicant, a
 * site-wide decider) uses no course capability their lapsed course role still carries, on any path
 * that does not pass require_login($course) ({@see groupscope::holds_in_course()}); a capability held
 * in the system context still counts.
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
#[CoversClass(\enrol_apply\local\groupseats::class)]
#[CoversClass(\enrol_apply\customfield\application_handler::class)]
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
     * Core's bulk route, user/action_redir.php, which calls no require_login(): the menu the dispatch
     * looks the operation up in is empty for the lapsed teacher, and process() itself refuses them and
     * says so, deciding nothing. An active teacher is offered the menu (the control).
     *
     * Changes that must make it fail: get_bulk_operations() (gate LP8) or process() (gate LP9) reading
     * the course capability with has_capability() alone.
     *
     * @param string $how The way the enrolment lapsed.
     * @return void
     */
    #[DataProvider('lapse_provider')]
    public function test_the_bulk_route_decides_nothing_for_a_lapsed_teacher(string $how): void {
        global $PAGE;

        [$ueid, $applicant] = $this->apply_for();
        $before = $this->state($ueid);

        $PAGE->set_url('/user/index.php', ['id' => $this->course->id]);
        $manager = new \course_enrolment_manager($PAGE, $this->course, $this->instance->id);
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $this->assertNotEmpty(enrol_get_plugin('apply')->get_bulk_operations($manager), 'the control: an active teacher');

        $this->lapsed_teacher($how);
        $this->assertSame([], enrol_get_plugin('apply')->get_bulk_operations($manager));

        $users = $manager->get_users_enrolments([(int) $applicant->id]);
        $operation = new \enrol_apply\bulk\confirm_operation($manager, $this->plugin);
        \core\notification::fetch();

        $sink = $this->redirectMessages();
        $this->assertFalse($operation->process($manager, $users, (object) ['outcomemessage' => '']));
        $sink->close();

        $this->assertSame($before, $this->state($ueid));
        $messages = array_map(static fn($n): string => $n->get_message(), \core\notification::fetch());
        $this->assertSame([get_string('bulknotpermitted', 'enrol_apply')], $messages);
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

    /**
     * The course of the review tests: three groups, places on the method, an applicant who chose a
     * group, who holds another enrolment in the course and an earlier application, and the editing
     * teacher's role granted enrol/apply:viewreports, so every course-gated panel of the review page
     * has something to show to a reader entitled to it.
     *
     * @return array The user enrolment id, the applicant and the groups: listed, chosen, forged.
     */
    private function review_fixture(): array {
        global $DB, $USER;

        $generator = $this->getDataGenerator();
        set_config('showuseridentity', 'email');
        $listed = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Listed group']);
        $chosen = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Chosen group']);
        $forged = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Forged group']);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $this->instance->id, 'groupid' => $listed->id]);
        $DB->set_field('enrol', 'customint4', 5, ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
        $editingteacher = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability(groupscope::REPORT, CAP_ALLOW, $editingteacher, \context_system::instance()->id, true);

        $applicant = $generator->create_user(['email' => 'lapsed.applicant@example.org']);
        $previous = $USER;
        $this->setAdminUser();
        $sink = $this->redirectMessages();
        $written = (new \ReflectionMethod(\enrol_apply_plugin::class, 'apply'))->invoke(
            $this->plugin,
            $this->instance,
            (int) $applicant->id,
            (object) ['applydescription' => 'Please'],
            0,
            submission::KEY_NONE,
            (int) $chosen->id
        );
        $sink->close();
        $this->setUser($previous);

        $generator->enrol_user($applicant->id, $this->course->id, 'student');
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $applicant->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => 0,
            'comment' => '',
            'status' => submission::STATUS_CANCELLED,
            'timecreated' => time() - 10 * DAYSECS,
            'timedecided' => time() - 9 * DAYSECS,
            'decidedby' => 0,
        ]);

        return [(int) $written['userenrolmentid'], $applicant, $listed, $chosen, $forged];
    }

    /**
     * An editing teacher of the course who also mentors the applicant (the capability in the
     * applicant's user context), their own enrolment lapsed or not.
     *
     * @param \stdClass $applicant The applicant.
     * @param string|null $how 'suspended' or 'ended', or null for an active enrolment.
     * @param string $shortname The mentor role's short name, unique in the test.
     * @return \stdClass The teacher.
     */
    private function teacher_who_mentors(\stdClass $applicant, ?string $how, string $shortname): \stdClass {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        if ($how !== null) {
            $this->lapse($teacher, $how);
        }
        $role = $this->getDataGenerator()->create_role(['shortname' => $shortname]);
        set_role_contextlevels($role, [CONTEXT_USER]);
        assign_capability(groupscope::DECIDE, CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $teacher->id, \context_user::instance($applicant->id)->id);

        return $teacher;
    }

    /**
     * The review page's form for one application, rendered for the current user in the context the
     * page's gate chose for them.
     *
     * @param int $ueid User enrolment id.
     * @param \stdClass $applicant The applicant.
     * @param \context $context What queue::require_review_access() returned.
     * @return string The rendered form.
     */
    private function review_html(int $ueid, \stdClass $applicant, \context $context): string {
        global $PAGE;

        $url = new \moodle_url('/enrol/apply/manage.php', ['userenrol' => $ueid]);
        $PAGE->set_url($url);
        $PAGE->set_context($context);

        return $PAGE->get_renderer('enrol_apply')->review_form(queue::application($ueid), $applicant, $this->instance, $url);
    }

    /**
     * Post a decision as manage.php receives it and run its handler's half, as decision_post_test does.
     *
     * @param array $post The form's fields.
     * @return int How many applications were decided.
     */
    private function post(array $post): int {
        $_POST = $post;
        try {
            $posted = \enrol_apply\local\decision::posted();
            $sink = $this->redirectMessages();
            $decided = \enrol_apply\local\decision::apply(
                optional_param('formaction', '', PARAM_ALPHA),
                optional_param_array('userenrolments', [], PARAM_INT),
                $posted['message'],
                $posted['messages'],
                $posted['decision']
            );
            $sink->close();
        } finally {
            $_POST = [];
        }

        return $decided;
    }

    /**
     * Whether this plugin assigned the applicant the role in the course.
     *
     * @param string $shortname The role's short name.
     * @param \stdClass $applicant The applicant.
     * @return bool True when the stamped assignment exists.
     */
    private function holds_role(string $shortname, \stdClass $applicant): bool {
        global $DB;

        return $DB->record_exists('role_assignments', [
            'roleid' => (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST),
            'userid' => $applicant->id,
            'contextid' => $this->context->id,
            'component' => 'enrol_apply',
        ]);
    }

    /**
     * What the review page shows only to a reader entitled to the course, by what reveals it.
     *
     * @param \stdClass $applicant The applicant.
     * @return array Label => the text that appears when the page shows it.
     */
    private static function course_only_parts(\stdClass $applicant): array {
        return [
            'the chosen group' => 'Chosen group',
            'the method\'s group' => 'Listed group',
            'another group' => 'Forged group',
            'the group chooser' => 'name="groups[]"',
            'the role chooser' => 'name="roleid"',
            'the earlier applications' => 'data-region="history-list"',
            'the other enrolments' => 'data-region="review-enrolments"',
            'the capacity' => get_string('reviewmeterplaces', 'enrol_apply'),
            'the identity fields' => $applicant->email,
        ];
    }

    /**
     * A teacher whose own enrolment lapsed and who also mentors the applicant reviews as a mentor: the
     * page sits in the applicant's user context and shows nothing the course role would have given
     * them, neither choosers nor group names, history, other enrolments, capacity or identity fields.
     *
     * Changes that must make it fail, each a has_capability() put back where
     * groupscope::holds_in_course() reads: require_review_access()'s context (LP10), the decider and
     * group-name readings of the review page (LP13, LP15, LP16), the history (LP17), the other
     * enrolments (LP18), the capacity (LP19), the identity fields (LP20).
     *
     * @return void
     */
    public function test_a_lapsed_teacher_who_mentors_reviews_as_a_mentor(): void {
        [$ueid, $applicant] = $this->review_fixture();
        $this->setUser($this->teacher_who_mentors($applicant, 'suspended', 'lapsedmentor'));
        // The premise: the mentor level admits them, and the course role survives.
        $this->assertTrue($this->plugin->can_manage_application((int) $this->course->id, (int) $applicant->id, $ueid));
        $this->assertTrue(has_capability(groupscope::DECIDE, $this->context));

        $context = queue::require_review_access(queue::application($ueid));
        $this->assertInstanceOf(\context_user::class, $context);
        $this->assertSame((int) $applicant->id, (int) $context->instanceid);

        $html = $this->review_html($ueid, $applicant, $context);
        $this->assertStringContainsString('enrol_apply_review_form', $html, 'the page rendered');
        foreach (self::course_only_parts($applicant) as $label => $text) {
            $this->assertStringNotContainsString($text, $html, $label);
        }
    }

    /**
     * The control for the test above: the same teacher with an active enrolment reviews in the course
     * and is shown every one of those parts.
     *
     * @return void
     */
    public function test_an_active_teacher_who_mentors_reviews_in_the_course(): void {
        [$ueid, $applicant] = $this->review_fixture();
        $this->setUser($this->teacher_who_mentors($applicant, null, 'activementor'));

        $context = queue::require_review_access(queue::application($ueid));
        $this->assertInstanceOf(\context_course::class, $context);

        $html = $this->review_html($ueid, $applicant, $context);
        foreach (self::course_only_parts($applicant) as $label => $text) {
            $this->assertStringContainsString($text, $html, $label);
        }
    }

    /**
     * A lapsed teacher who mentors the applicant posts an approval with a forged role and group, through
     * the queue's own contract: the approval records an EMPTY choice and gives the method's own role and
     * groups, exactly as for a plain mentor. The control is the same post by an active teacher who
     * mentors, which is honoured.
     *
     * Changes that must make it fail: confirm_enrolment() reading enrol/apply:choosedecision with
     * has_capability() alone (gate LP6).
     *
     * @return void
     */
    public function test_a_lapsed_teacher_who_mentors_records_an_empty_choice(): void {
        global $DB;

        [$ueid, $applicant, $listed, $chosen, $forged] = $this->review_fixture();
        $teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $forgedpost = fn(int $id): array => [
            'sesskey' => sesskey(),
            'formaction' => 'confirm',
            'userenrolments' => [$id],
            'groups' => [(int) $forged->id],
            'roleid' => $teacherrole,
        ];
        $this->setUser($this->teacher_who_mentors($applicant, 'suspended', 'choosingmentor'));
        $this->assertArrayHasKey($teacherrole, get_assignable_roles($this->context), 'the premise: the role survives');

        $this->assertSame(1, $this->post($forgedpost($ueid)));

        $this->assertNull(submission::chosen_groups($ueid));
        $this->assertNull(submission::chosen_role($ueid));
        $this->assertTrue($this->holds_role('student', $applicant), 'the method\'s own role');
        $this->assertFalse($this->holds_role('teacher', $applicant));
        $this->assertTrue(groups_is_member($listed->id, $applicant->id), 'the method\'s own group');
        $this->assertTrue(groups_is_member($chosen->id, $applicant->id), 'the group the applicant chose');
        $this->assertFalse(groups_is_member($forged->id, $applicant->id));

        // The control: an active teacher who mentors has the same post honoured.
        [$otherueid, $other] = $this->apply_for();
        $this->setUser($this->teacher_who_mentors($other, null, 'choosingactive'));
        $this->assertSame(1, $this->post($forgedpost($otherueid)));
        $this->assertTrue($this->holds_role('teacher', $other));
        $this->assertTrue(groups_is_member($forged->id, $other->id));
    }

    /**
     * A site-wide decider (the capabilities held in the system context) whose own enrolment in the
     * course lapsed keeps the course context and the choosers: their grant does not come from the
     * course. And one whose choosing right comes only from the lapsed course role gets no chooser and
     * records an empty choice, while still deciding by the system grant.
     *
     * Changes that must make it fail: holds_in_course() ignoring the system exemption (the first half,
     * gate LP25), the review page's chooser (gate LP14) or confirm_enrolment() (gate LP6) reading
     * enrol/apply:choosedecision with has_capability() alone (the second half).
     *
     * @return void
     */
    public function test_a_site_wide_decider_keeps_what_the_system_grants_and_nothing_a_lapsed_role_does(): void {
        [$ueid, $applicant, , , $forged] = $this->review_fixture();
        $system = \context_system::instance()->id;

        $choosing = $this->getDataGenerator()->create_role(['shortname' => 'sitechooser']);
        assign_capability(groupscope::DECIDE, CAP_ALLOW, $choosing, $system, true);
        assign_capability('enrol/apply:choosedecision', CAP_ALLOW, $choosing, $system, true);
        $sitechooser = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        role_assign($choosing, $sitechooser->id, $system);
        $this->lapse($sitechooser, 'suspended');
        $this->setUser($sitechooser);

        $context = queue::require_review_access(queue::application($ueid));
        $this->assertInstanceOf(\context_course::class, $context);
        $this->assertStringContainsString('name="groups[]"', $this->review_html($ueid, $applicant, $context));

        $deciding = $this->getDataGenerator()->create_role(['shortname' => 'sitedecider']);
        assign_capability(groupscope::DECIDE, CAP_ALLOW, $deciding, $system, true);
        $sitedecider = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        role_assign($deciding, $sitedecider->id, $system);
        $this->lapse($sitedecider, 'suspended');
        $this->setUser($sitedecider);
        $this->assertTrue(has_capability('enrol/apply:choosedecision', $this->context), 'the premise: the course role');

        $context = queue::require_review_access(queue::application($ueid));
        $this->assertInstanceOf(\context_course::class, $context);
        $html = $this->review_html($ueid, $applicant, $context);
        $this->assertStringContainsString('Listed group', $html, 'a site-wide decider is told the groups');
        $this->assertStringNotContainsString('name="groups[]"', $html);
        $this->assertStringNotContainsString('name="roleid"', $html);

        $this->assertSame(1, $this->post([
            'sesskey' => sesskey(),
            'formaction' => 'confirm',
            'userenrolments' => [$ueid],
            'groups' => [(int) $forged->id],
        ]));
        $this->assertNull(submission::chosen_groups($ueid));
        $this->assertFalse(groups_is_member($forged->id, $applicant->id));
    }

    /**
     * The new-application notice names the chosen group to a recipient who mentors the applicant only
     * where their course role may be used: not to a teacher whose enrolment lapsed, to an active one.
     *
     * Changes that must make it fail: names_group_to() reading the course capability with
     * has_capability() alone (gate LP7).
     *
     * @return void
     */
    public function test_the_notice_names_the_group_to_a_mentor_only_through_a_live_course_role(): void {
        global $DB;

        $chosen = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Chosen group']);
        $DB->set_field('enrol', 'customtext3', '', ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
        $applicant = $this->getDataGenerator()->create_user();
        $lapsed = $this->teacher_who_mentors($applicant, 'suspended', 'noticelapsed');
        $active = $this->teacher_who_mentors($applicant, null, 'noticeactive');

        $this->setAdminUser();
        $sink = $this->redirectMessages();
        (new \ReflectionMethod(\enrol_apply_plugin::class, 'apply'))->invoke(
            $this->plugin,
            $this->instance,
            (int) $applicant->id,
            (object) ['applydescription' => 'Please'],
            0,
            submission::KEY_NONE,
            (int) $chosen->id
        );
        $messages = $sink->get_messages();
        $sink->close();
        $to = static fn(\stdClass $user): array => array_values(array_filter(
            $messages,
            static fn($m): bool => (int) $m->useridto === (int) $user->id
        ));

        $this->assertCount(1, $to($lapsed));
        $this->assertStringNotContainsString('Chosen group', $to($lapsed)[0]->fullmessagehtml);
        $this->assertCount(1, $to($active));
        $this->assertStringContainsString('Chosen group', $to($active)[0]->fullmessagehtml, 'the control');
    }

    /**
     * The snapshot's masking judges a reader in the course by a live course role only: a teacher whose
     * enrolment lapsed is masked as a reader holding nothing there; an active one is not.
     *
     * Changes that must make it fail: visible_keys() reading moodle/site:viewuseridentity with
     * has_capability() alone (gate LP21).
     *
     * @return void
     */
    public function test_the_snapshot_masking_ignores_a_lapsed_course_role(): void {
        $formatter = \enrol_apply\reportbuilder\local\formatters\submission::class;
        $nobody = $this->getDataGenerator()->create_user();
        $lapsed = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->lapse($lapsed, 'suspended');
        $active = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $nothing = $formatter::visible_keys($this->context, (int) $nobody->id);
        $this->assertSame($nothing, $formatter::visible_keys($this->context, (int) $lapsed->id));
        $this->assertNotSame($nothing, $formatter::visible_keys($this->context, (int) $active->id), 'the control');
    }

    /**
     * The warning after an approval names an overfilled group only to a reader whose course role is live.
     *
     * The overfilled set is stood in for (it needs local_groupdist's seats), so what is judged is the
     * naming alone.
     *
     * Changes that must make it fail: groupseats::overfilled_named_to() reading the course capability
     * with has_capability() alone (gate LP22).
     *
     * @return void
     */
    public function test_an_overfilled_group_is_not_named_through_a_lapsed_course_role(): void {
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Overfilled group']);
        $seats = new class extends \enrol_apply\local\groupseats {
            /** @var array What overfilled() answers. */
            public static $over = [];

            /**
             * The stood-in answer.
             *
             * @param array $userenrolmentids Ignored.
             * @return array Group id => the overfilled group.
             */
            public static function overfilled(array $userenrolmentids): array {
                return self::$over;
            }
        };
        $seats::$over = [(int) $group->id => (object) [
            'id' => (int) $group->id,
            'courseid' => (int) $this->course->id,
            'name' => $group->name,
            'visibility' => GROUPS_VISIBILITY_ALL,
            'members' => 3,
            'seats' => 2,
        ]];
        $lapsed = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->lapse($lapsed, 'suspended');
        $active = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $this->assertSame([], $seats::overfilled_named_to([], (int) $lapsed->id));
        $this->assertSame([(int) $group->id], array_keys($seats::overfilled_named_to([], (int) $active->id)), 'the control');
    }

    /**
     * Core's callback can_view() on the application's custom fields answers no to a reader whose
     * course role lapsed, and yes to an active one.
     *
     * Changes that must make it fail: application_handler::can_view() reading either course capability
     * with has_capability() alone (gates LP23, LP24).
     *
     * @return void
     */
    public function test_the_fields_handler_ignores_a_lapsed_course_role(): void {
        global $DB;

        /** @var \core_customfield_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category([
            'component' => 'enrol_apply',
            'area' => \enrol_apply\customfield\application_handler::AREA,
            'itemid' => (int) $this->instance->id,
        ]);
        $field = $generator->create_field([
            'categoryid' => $category->get('id'),
            'type' => 'text',
            'shortname' => 'lapsedmotivation',
        ]);
        $handler = \enrol_apply\customfield\application_handler::create((int) $this->instance->id);
        // Both capabilities the callback reads come from the course role, so each half is held.
        $editingteacher = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability(groupscope::REPORT, CAP_ALLOW, $editingteacher, \context_system::instance()->id, true);

        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $this->assertTrue($handler->can_view($field, (int) $this->instance->id), 'the control: an active teacher');

        $this->lapsed_teacher('suspended');
        $this->assertFalse($handler->can_view($field, (int) $this->instance->id));
    }
}
