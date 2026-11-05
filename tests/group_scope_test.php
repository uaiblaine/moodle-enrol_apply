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

use enrol_apply\local\queue;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass targets below resolve when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/apply/edit_form.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

/**
 * Every surface a decider narrowed by separate groups reaches (docs/design/group-review-scope.md,
 * stage 2, S1-S7).
 *
 * The course is in separate groups mode. The non-editing teacher, who lacks
 * moodle/site:accessallgroups, is made a moderator by granting their role the capabilities a
 * decision through this plugin, a choice of groups and core's edit-enrolment screen need; they are a member
 * of one group. The editing teacher holds moodle/site:accessallgroups and is the control that sees
 * everything. Applications are written by the plugin's own apply(), so each carries its record
 * and its comment row and sends its notice.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
#[CoversClass(\enrol_apply\local\groupscope::class)]
#[CoversClass(\enrol_apply\local\queue::class)]
#[CoversClass(\enrol_apply\table\applications::class)]
#[CoversClass(\enrol_apply\hook_callbacks::class)]
#[CoversClass(\enrol_apply\form\bulk_decision_form::class)]
#[CoversClass(\enrol_apply_renderer::class)]
#[CoversClass(\enrol_apply_edit_form::class)]
final class group_scope_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass A course in separate groups mode. */
    private $course;

    /** @var \stdClass Its apply instance, notifying every course decider. */
    private $instance;

    /** @var \stdClass The moderator's group. */
    private $mine;

    /** @var \stdClass Another group of the course. */
    private $theirs;

    /** @var \stdClass Non-editing teacher made moderator, member of $mine. */
    private $moderator;

    /** @var \stdClass Editing teacher, who may access all groups and is in no group. */
    private $editing;

    /**
     * The course, its groups and its two deciders.
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

        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $system = \context_system::instance()->id;
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $teacher, $system, true);
        assign_capability('enrol/apply:manage', CAP_ALLOW, $teacher, $system, true);
        /* The moderator here also chooses groups, which is what S1's chooser tests are about; a
           decider without enrol/apply:choosedecision is decision_capabilities_test's. */
        assign_capability('enrol/apply:choosedecision', CAP_ALLOW, $teacher, $system, true);

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS]);
        $id = $this->plugin->add_instance($this->course, [
            'customtext3' => '$@ALL@$',
        ] + $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $this->mine = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Own group']);
        $this->theirs = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Other group']);
        $this->moderator = $generator->create_and_enrol($this->course, 'teacher');
        $generator->create_group_member(['groupid' => $this->mine->id, 'userid' => $this->moderator->id]);
        $this->editing = $generator->create_and_enrol($this->course, 'editingteacher');

        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]));
    }

    /**
     * Write a pending application through the plugin's own apply(), as the administrator.
     *
     * @param \stdClass|null $group The key's group; null for an application without a key.
     * @param int|null $timecreated Submission time to stamp, null to leave it.
     * @return array The user enrolment id, the applicant and the messages the notice sent.
     */
    private function apply_for(?\stdClass $group, ?int $timecreated = null): array {
        global $DB, $USER;

        $previous = $USER;
        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $written = $method->invoke(
            $this->plugin,
            $this->instance,
            (int) $applicant->id,
            (object) ['applydescription' => 'Please'],
            $group ? (int) $group->id : 0,
            $group ? submission::KEY_FOR_REVIEW : submission::KEY_NONE
        );
        $messages = $sink->get_messages();
        $sink->close();
        $this->setUser($previous);

        $ueid = (int) $written['userenrolmentid'];
        if ($timecreated !== null) {
            $DB->set_field('user_enrolments', 'timecreated', $timecreated, ['id' => $ueid]);
        }

        return [$ueid, $applicant, $messages];
    }

    /**
     * The user enrolment ids the queue's own table lists for the current user, in its order.
     *
     * @param string $search A search narrowing the listing, empty for none.
     * @return array The ids, and the table for its totals.
     */
    private function listed(string $search = ''): array {
        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id, $search);
        ob_start();
        $table->out(50, false);
        ob_end_clean();

        return [array_map(static fn($row) => (int) $row->userenrolmentid, array_values($table->rawdata)), $table];
    }

    /**
     * The status of a user enrolment, or null once it is gone.
     *
     * @param int $ueid User enrolment id.
     * @return int|null Its status.
     */
    private function enrolment_status(int $ueid): ?int {
        global $DB;

        $status = $DB->get_field('user_enrolments', 'status', ['id' => $ueid]);

        return $status === false ? null : (int) $status;
    }

    /**
     * A role held in the applicant's user context with the deciding capability: a mentor.
     *
     * @param \stdClass $mentor Who mentors.
     * @param \stdClass $mentee Whom.
     * @return void
     */
    private function make_mentor(\stdClass $mentor, \stdClass $mentee): void {
        $role = $this->getDataGenerator()->create_role(['shortname' => 'applymentor']);
        set_role_contextlevels($role, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $mentor->id, \context_user::instance($mentee->id)->id);
    }

    /**
     * The moderator's queue lists the applications of their group alone, counts the same set,
     * and leaves out another group's and those without a key; the editing teacher's lists all.
     *
     * Changes that must make it fail: the group predicate dropped from scope_where(), or keyless
     * applications listed to a narrowed decider.
     *
     * @return void
     */
    public function test_the_queue_lists_only_the_moderators_group(): void {
        [$own] = $this->apply_for($this->mine);
        [$other] = $this->apply_for($this->theirs);
        [$keyless] = $this->apply_for(null);

        $this->setUser($this->moderator);
        [$rows] = $this->listed();
        $this->assertSame([$own], $rows);
        // The count line's M: a search that matches nothing makes scope_total() run its own query.
        [$none, $table] = $this->listed('no applicant is called this');
        $this->assertSame([], $none);
        $this->assertSame(1, $table->scope_total());

        // The control: who may access all groups lists every one of them.
        $this->setUser($this->editing);
        [$all] = $this->listed();
        $this->assertEqualsCanonicalizing([$own, $other, $keyless], $all);
    }

    /**
     * Visible groups narrow nobody (S2), so the same moderator lists every application there.
     *
     * Changes that must make it fail: visible groups read as separate ones.
     *
     * @return void
     */
    public function test_visible_groups_narrow_nobody(): void {
        global $DB;

        $DB->set_field('course', 'groupmode', VISIBLEGROUPS, ['id' => $this->course->id]);
        [$own] = $this->apply_for($this->mine);
        [$other] = $this->apply_for($this->theirs);
        [$keyless] = $this->apply_for(null);

        $this->setUser($this->moderator);
        [$rows] = $this->listed();
        $this->assertEqualsCanonicalizing([$own, $other, $keyless], $rows);
    }

    /**
     * A moderator in no group gets core's "not a member of any group" notice instead of a queue
     * (S4); a decider who sees every group, in no group either, gets the queue.
     *
     * Changes that must make it fail: the notice dropped from manage_form(), or shown to anybody
     * not narrowed.
     *
     * @return void
     */
    public function test_a_moderator_in_no_group_is_told_so(): void {
        global $PAGE;

        $this->apply_for($this->mine);
        $groupless = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $renderer = $PAGE->get_renderer('enrol_apply');
        $notice = get_string('notingroup');

        $this->setUser($groupless);
        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);
        $this->assertTrue($table->not_in_any_group());
        $this->assertStringContainsString($notice, $renderer->manage_form($table, $url, $this->instance));

        $this->setUser($this->editing);
        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);
        $this->assertFalse($table->not_in_any_group());
        $this->assertStringNotContainsString($notice, $renderer->manage_form($table, $url, $this->instance));
    }

    /**
     * The review page refuses the moderator an application outside their group, and one without
     * a key, and admits them to their own in the course context.
     *
     * Changes that must make it fail: can_manage_application()'s course branch not narrowed.
     *
     * @return void
     */
    public function test_the_review_page_refuses_an_application_outside_the_group(): void {
        [$own] = $this->apply_for($this->mine);
        [$other] = $this->apply_for($this->theirs);
        [$keyless] = $this->apply_for(null);
        $this->setUser($this->moderator);

        $context = queue::require_review_access(queue::application($own));
        $this->assertSame(\context_course::instance($this->course->id)->id, $context->id);

        foreach ([$other, $keyless] as $ueid) {
            try {
                queue::require_review_access(queue::application($ueid));
                $this->fail("application {$ueid} was opened");
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
    }

    /**
     * The previous/next walk visits exactly what the moderator's queue lists, in its order, with
     * other groups' and keyless applications interleaved in time.
     *
     * Changes that must make it fail: the group predicate dropped from neighbour(), or scope()
     * not carrying the narrowing.
     *
     * @return void
     */
    public function test_the_walk_stays_inside_the_moderators_queue(): void {
        $base = time() - DAYSECS;
        $own = [];
        foreach ([1, 3, 5] as $offset) {
            [$own[]] = $this->apply_for($this->mine, $base + $offset);
        }
        $this->apply_for($this->theirs, $base + 2);
        $this->apply_for(null, $base + 4);
        $this->apply_for($this->theirs, $base + 6);

        $this->setUser($this->moderator);
        [$listed] = $this->listed();

        $application = queue::application($own[1]);
        $scope = queue::scope($application, $this->instance);
        $this->assertSame([(int) $this->mine->id], $scope->groups);

        $walked = [];
        $current = queue::application($own[0]);
        $walked[] = (int) $current->id;
        while ($next = queue::neighbours($current, $scope)['next']) {
            $walked[] = (int) $next->id;
            $current = queue::application((int) $next->id);
        }
        $this->assertNull(queue::neighbours(queue::application($own[0]), $scope)['previous']);

        $this->assertSame($listed, $walked);
        $this->assertSame($own, $walked, 'the control: three rows, in time order');
    }

    /**
     * Approving, deferring and cancelling act on the moderator's own group and skip, by count,
     * an application of another group and one without a key.
     *
     * Changes that must make it fail: any of the three decision methods acting outside the group.
     *
     * @return void
     */
    public function test_the_decisions_skip_an_application_outside_the_group(): void {
        foreach (['confirm_enrolment', 'wait_enrolment', 'cancel_enrolment'] as $method) {
            [$own] = $this->apply_for($this->mine);
            [$other] = $this->apply_for($this->theirs);
            [$keyless] = $this->apply_for(null);

            $this->setUser($this->moderator);
            // Opened only now: apply_for() opens its own, and a message sink cannot be nested.
            $sink = $this->redirectMessages();
            $this->assertSame(1, $this->plugin->{$method}([$own, $other, $keyless]), $method);
            $sink->close();

            $this->assertNotSame(ENROL_USER_SUSPENDED, $this->enrolment_status($own), "{$method} acted on its own group");
            $this->assertSame(ENROL_USER_SUSPENDED, $this->enrolment_status($other), $method);
            $this->assertSame(ENROL_USER_SUSPENDED, $this->enrolment_status($keyless), $method);
        }
    }

    /**
     * A moderator who also mentors an applicant outside their group decides that application:
     * only the course grant is narrowed. The walk is then the mentor's, which contains it.
     *
     * Changes that must make it fail: the mentor level narrowed too, or scope() taking the
     * instance queue for an application outside it.
     *
     * @return void
     */
    public function test_the_mentor_level_is_not_narrowed(): void {
        [$keyless, $applicant] = $this->apply_for(null);
        $this->make_mentor($this->moderator, $applicant);
        $this->setUser($this->moderator);

        $this->assertTrue($this->plugin->can_manage_application((int) $this->course->id, (int) $applicant->id, $keyless));
        $context = queue::require_review_access(queue::application($keyless));
        $this->assertSame(\context_user::instance($applicant->id)->id, $context->id);
        $scope = queue::scope(queue::application($keyless), $this->instance);
        $this->assertSame(0, $scope->enrolid);
        $this->assertSame([(int) $applicant->id], $scope->mentees);

        $sink = $this->redirectMessages();
        $this->assertSame(1, $this->plugin->confirm_enrolment([$keyless]), 'the hook lets the mentor through');
        $sink->close();
        $this->assertSame(ENROL_USER_ACTIVE, $this->enrolment_status($keyless));
    }

    /**
     * The choosers offer the moderator their own group only: the queue's and review page's
     * shared context, and the participants page's bulk form; the editing teacher is offered both.
     *
     * Changes that must make it fail: either chooser reading every group of the course.
     *
     * @return void
     */
    public function test_the_choosers_offer_only_the_moderators_groups(): void {
        global $PAGE;

        $renderer = $PAGE->get_renderer('enrol_apply');
        $controls = new \ReflectionMethod($renderer, 'decision_controls_context');
        $offered = function () use ($renderer, $controls): array {
            $context = $controls->invoke($renderer, $this->instance);
            return array_map(static fn(array $group): int => (int) $group['id'], $context['groups']);
        };
        $bulk = function (): string {
            $form = new \enrol_apply\form\bulk_decision_form(null, [
                'users' => [],
                'description' => '',
                'button' => 'Approve',
                'courseid' => (int) $this->course->id,
                'withdecision' => true,
            ]);
            $html = $form->render();
            $this->assertSame(1, preg_match('~<select[^>]*name="groups\[\]".*?</select>~s', $html, $select), $html);
            return $select[0];
        };

        $this->setUser($this->moderator);
        $this->assertSame([(int) $this->mine->id], $offered());
        $select = $bulk();
        $this->assertStringContainsString('value="' . $this->mine->id . '"', $select);
        $this->assertStringNotContainsString('value="' . $this->theirs->id . '"', $select);

        $this->setUser($this->editing);
        $this->assertEqualsCanonicalizing([(int) $this->mine->id, (int) $this->theirs->id], $offered());
        $this->assertStringContainsString('value="' . $this->theirs->id . '"', $bulk());
    }

    /**
     * A posted group outside the moderator's is dropped from an approval, and their own is
     * joined (S6).
     *
     * Changes that must make it fail: the allowlist in confirm_enrolment() reading every group.
     *
     * @return void
     */
    public function test_a_posted_foreign_group_is_not_joined(): void {
        global $DB;

        [$own, $applicant] = $this->apply_for($this->mine);
        $this->setUser($this->moderator);
        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$own], '', ['groups' => [(int) $this->mine->id, (int) $this->theirs->id]]);
        $sink->close();

        $this->assertSame([(int) $this->mine->id], submission::chosen_groups($own));
        $this->assertTrue($DB->record_exists('groups_members', ['groupid' => $this->mine->id, 'userid' => $applicant->id]));
        $this->assertFalse($DB->record_exists('groups_members', ['groupid' => $this->theirs->id, 'userid' => $applicant->id]));
    }

    /**
     * The managers' notice reaches the moderator for an application of their group only, judged
     * for them; the editing teacher is told of all three.
     *
     * Changes that must make it fail: the recipient filter dropped, or judged for the applicant.
     *
     * @return void
     */
    public function test_the_notice_reaches_the_moderator_only_for_their_group(): void {
        $to = static fn(array $messages, \stdClass $user): int => count(array_filter(
            $messages,
            static fn($message): bool => (int) $message->useridto === (int) $user->id
        ));

        [, , $own] = $this->apply_for($this->mine);
        [, , $other] = $this->apply_for($this->theirs);
        [, , $keyless] = $this->apply_for(null);

        $this->assertSame(1, $to($own, $this->moderator));
        $this->assertSame(0, $to($other, $this->moderator));
        $this->assertSame(0, $to($keyless, $this->moderator));
        foreach ([$own, $other, $keyless] as $messages) {
            $this->assertSame(1, $to($messages, $this->editing), 'the control: all groups are told');
        }
    }

    /**
     * The participants page offers the moderator the decision icon on their group's application
     * only.
     *
     * Changes that must make it fail: the group gate dropped from get_user_enrolment_actions().
     *
     * @return void
     */
    public function test_the_icon_is_offered_only_inside_the_group(): void {
        global $PAGE;

        [, $ownapplicant] = $this->apply_for($this->mine);
        [, $otherapplicant] = $this->apply_for($this->theirs);
        $PAGE->set_url('/user/index.php', ['id' => $this->course->id]);
        $title = get_string('decideapplication', 'enrol_apply');

        $titles = function (\stdClass $user): array {
            global $PAGE;
            $manager = new \course_enrolment_manager($PAGE, $this->course);
            $ues = $manager->get_user_enrolments($user->id);
            $ue = reset($ues);
            return array_map(
                static fn(\user_enrolment_action $action): string => $action->get_title(),
                $this->plugin->get_user_enrolment_actions($manager, $ue)
            );
        };

        $this->setUser($this->moderator);
        $this->assertContains($title, $titles($ownapplicant));
        $this->assertNotContains($title, $titles($otherapplicant));

        $this->setUser($this->editing);
        $this->assertContains($title, $titles($otherapplicant), 'the control: all groups');
    }

    /**
     * Core's edit-enrolment route, enrol_plugin::update_user_enrol(), is refused to the moderator
     * for an application outside their group, before anything is written (S7).
     *
     * Changes that must make it fail: the refusal dropped from the hook, or thrown after
     * complete_approval().
     *
     * @return void
     */
    public function test_the_edit_enrolment_route_is_refused_outside_the_group(): void {
        global $DB;

        [$other, $applicant] = $this->apply_for($this->theirs);
        $this->setUser($this->moderator);

        try {
            $this->plugin->update_user_enrol($this->instance, $applicant->id, ENROL_USER_ACTIVE);
            $this->fail('the update went through');
        } catch (\moodle_exception $e) {
            $this->assertSame('groupscopeapprovalrefused', $e->errorcode);
        }

        $this->assertSame(ENROL_USER_SUSPENDED, $this->enrolment_status($other));
        $this->assertTrue($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $other]));
        $record = $DB->get_record('enrol_apply_submission', ['userenrolmentid' => $other], '*', MUST_EXIST);
        $this->assertSame(submission::STATUS_PENDING, (int) $record->status);
        $this->assertFalse($DB->record_exists('groups_members', ['userid' => $applicant->id]));
    }

    /**
     * The same route lets through the moderator inside their group, the editing teacher outside
     * it, the administrator, and a script running as nobody; and the plugin's own approval,
     * which reaches the same hook, inside the group.
     *
     * Changes that must make it fail: the hook refusing by the mode alone, judging the applicant
     * on a key admission, or refusing a CLI or cron run.
     *
     * @return void
     */
    public function test_the_edit_enrolment_route_lets_the_others_through(): void {
        [$own, $ownapplicant] = $this->apply_for($this->mine);
        $this->setUser($this->moderator);
        $this->plugin->update_user_enrol($this->instance, $ownapplicant->id, ENROL_USER_ACTIVE);
        $this->assertSame(ENROL_USER_ACTIVE, $this->enrolment_status($own), 'inside the group');

        [$other, $otherapplicant] = $this->apply_for($this->theirs);
        $this->setUser($this->editing);
        $this->plugin->update_user_enrol($this->instance, $otherapplicant->id, ENROL_USER_ACTIVE);
        $this->assertSame(ENROL_USER_ACTIVE, $this->enrolment_status($other), 'who may access all groups');

        [$byadmin, $adminapplicant] = $this->apply_for(null);
        $this->setAdminUser();
        $this->plugin->update_user_enrol($this->instance, $adminapplicant->id, ENROL_USER_ACTIVE);
        $this->assertSame(ENROL_USER_ACTIVE, $this->enrolment_status($byadmin), 'the administrator, as a CLI script runs');

        [$bynobody, $nobodyapplicant] = $this->apply_for(null);
        $this->setUser(null);
        $this->plugin->update_user_enrol($this->instance, $nobodyapplicant->id, ENROL_USER_ACTIVE);
        $this->assertSame(ENROL_USER_ACTIVE, $this->enrolment_status($bynobody), 'a run with no user');

        [$queued] = $this->apply_for($this->mine);
        $this->setUser($this->moderator);
        $this->assertSame(1, $this->plugin->confirm_enrolment([$queued]));
        $this->assertSame(ENROL_USER_ACTIVE, $this->enrolment_status($queued), 'the plugin\'s own approval');
    }

    /**
     * The user enrolment row core hands to allow_unenrol_user().
     *
     * @param int $ueid User enrolment id.
     * @return \stdClass The row.
     */
    private function enrolment_row(int $ueid): \stdClass {
        global $DB;

        return $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
    }

    /**
     * Core's unenrol route (the screen, the participants icon and its AJAX call) is refused to a
     * narrowed decider for a pending application outside their groups, keyless ones included,
     * and offered inside their group and to the editing teacher.
     *
     * Changes that must make it fail: the group check dropped from allow_unenrol_user(), or the
     * answer asked of a user who holds no enrol/apply:unenrol.
     *
     * @return void
     */
    public function test_the_unenrol_route_is_refused_outside_the_group(): void {
        global $DB;

        [$own] = $this->apply_for($this->mine);
        [$other] = $this->apply_for($this->theirs);
        [$keyless] = $this->apply_for(null);

        // The control: without enrol/apply:unenrol the moderator is not judged here (core refuses them anyway).
        $this->setUser($this->moderator);
        $this->assertTrue($this->plugin->allow_unenrol_user($this->instance, $this->enrolment_row($other)), 'not a holder');

        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('enrol/apply:unenrol', CAP_ALLOW, $teacher, \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($this->moderator);
        $this->assertFalse($this->plugin->allow_unenrol_user($this->instance, $this->enrolment_row($other)), 'another group');
        $this->assertFalse($this->plugin->allow_unenrol_user($this->instance, $this->enrolment_row($keyless)), 'no key');
        $this->assertTrue($this->plugin->allow_unenrol_user($this->instance, $this->enrolment_row($own)), 'own group');

        $this->setUser($this->editing);
        $this->assertTrue($this->plugin->allow_unenrol_user($this->instance, $this->enrolment_row($other)), 'all groups');
        $this->assertTrue($this->plugin->allow_unenrol_user($this->instance, $this->enrolment_row($keyless)), 'keyless');
    }

    /**
     * An ACTIVE enrolment is not an application: the answer in another group is the parent's.
     *
     * Changes that must make it fail: the awaiting-decision gate dropped from allow_unenrol_user().
     *
     * @return void
     */
    public function test_the_unenrol_route_leaves_an_active_enrolment_to_core(): void {
        global $DB;

        [$other, $applicant] = $this->apply_for($this->theirs);
        $this->setUser($this->editing);
        $this->plugin->update_user_enrol($this->instance, $applicant->id, ENROL_USER_ACTIVE);
        $this->assertSame(ENROL_USER_ACTIVE, $this->enrolment_status($other));

        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('enrol/apply:unenrol', CAP_ALLOW, $teacher, \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($this->moderator);
        $row = $this->enrolment_row($other);
        $parent = $this->plugin->allow_unenrol($this->instance);
        $this->assertTrue($parent, 'the precondition: core would allow it');
        $this->assertSame($parent, $this->plugin->allow_unenrol_user($this->instance, $row));
    }

    /**
     * A group key's admission runs the hook as the applicant, who is in no group yet and holds no
     * capability, in a separate-groups course: it is not a decider's update and is not refused.
     *
     * Changes that must make it fail: the hook judging every user, not only holders of
     * enrol/apply:manage.
     *
     * @return void
     */
    public function test_a_key_admission_is_not_refused(): void {
        global $DB;

        $DB->set_field('enrol', 'customchar1', \enrol_apply\local\groupkey::MODE_ANY, ['id' => $this->instance->id]);
        $DB->set_field('groups', 'enrolmentkey', 'Own-Key-1', ['id' => $this->mine->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
        $applicant = $this->getDataGenerator()->create_user();
        $this->setUser($applicant);

        $sink = $this->redirectMessages();
        $result = $this->plugin->submit_application($this->instance, (int) $applicant->id, (object) [
            'applydescription' => '',
            'groupkey' => 'Own-Key-1',
        ]);
        $sink->close();
        \enrol_apply\local\keyattempts::release_all();

        $this->assertTrue($result->was_admitted());
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $applicant, '', true));
    }

    /**
     * The instance form of a separate-groups course warns that applications without a key reach
     * only who can access all groups (S3); a course without groups is not warned.
     *
     * Changes that must make it fail: the warning dropped, or shown whatever the group mode.
     *
     * @return void
     */
    public function test_the_edit_form_warns_about_keyless_applications(): void {
        global $DB, $PAGE;

        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/enrol/apply/edit.php', ['courseid' => $this->course->id]));
        $warning = s(get_string('groupscopekeyless', 'enrol_apply'));
        $render = function (): string {
            $form = new \enrol_apply_edit_form(
                null,
                [$this->instance, $this->plugin, \context_course::instance($this->course->id)]
            );
            return $form->render();
        };

        $this->assertStringContainsString($warning, $render());

        $DB->set_field('course', 'groupmode', NOGROUPS, ['id' => $this->course->id]);
        $this->assertStringNotContainsString($warning, $render());
    }
}
