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
 * Tests for the enrol_apply plugin application state machine.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the enrol_apply plugin application state machine.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
final class lib_test extends \advanced_testcase {
    /** @var \stdClass Course the apply instance belongs to. */
    protected $course;

    /** @var \stdClass The enrol_apply instance record. */
    protected $instance;

    /** @var \enrol_apply_plugin The plugin instance under test. */
    protected $plugin;

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
        /* The queue's dynamic table builds its "show all" link from $PAGE->url, and reading an
           unset page url calls debugging(), which advanced_testcase reports as a notice.
           manage.php always sets it. */
        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php'));
    }

    /**
     * Enrol a freshly created user as a pending applicant and return the user enrolment id.
     *
     * @param string $comment Application comment stored alongside the enrolment.
     * @return array Two-element array of the user record and the user enrolment id.
     */
    protected function create_application(string $comment = 'Please let me in'): array {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        // No role, mirroring apply(): the role is assigned on approval, not on application.
        $this->plugin->enrol_user($this->instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
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
     * A pending application starts suspended and carries its application info row.
     *
     * @return void
     */
    public function test_pending_application_is_suspended(): void {
        global $DB;

        [$user, $ueid] = $this->create_application();

        $ue = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_SUSPENDED, (int) $ue->status);
        $this->assertTrue($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $ueid]));
        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $user, '', true));
    }

    /**
     * A pending application carries no timeend, so the expiry sweep cannot reach it.
     *
     * The ENROL_EXT_REMOVED_UNENROL branch of enrol_plugin::process_expirations() selects
     * on "timeend > 0 AND timeend < now" with no status filter, so a pending application
     * that carried an enrolment period would be deleted rather than decided.
     *
     * @return void
     */
    public function test_pending_application_is_not_reachable_by_the_expiry_sweep(): void {
        global $DB;

        $this->setAdminUser();
        /* Set on the plugin object, not through the global set_config():
           enrol_plugin::get_config() reads the memoised $this->config, so a global write
           would leave the sweep on "keep" and the test would exercise nothing. */
        $this->plugin->set_config('expiredaction', ENROL_EXT_REMOVED_UNENROL);

        // The control: an approved enrolment whose period has run out. The sweep must eat it.
        [, $controlueid] = $this->create_application();
        $this->plugin->confirm_enrolment([$controlueid]);
        $DB->set_field('user_enrolments', 'timeend', time() - DAYSECS, ['id' => $controlueid]);

        // The subject: a pending application, which carries no period by construction.
        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_for($applicant);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $this->assertEquals(0, (int) $DB->get_field('user_enrolments', 'timeend', ['id' => $ueid]));

        $this->plugin->process_expirations(new \null_progress_trace());

        $this->assertFalse(
            $DB->record_exists('user_enrolments', ['id' => $controlueid]),
            'the control proves the sweep actually ran'
        );
        $this->assertTrue($DB->record_exists('user_enrolments', ['id' => $ueid]));
        $this->assertEquals(ENROL_USER_SUSPENDED, (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid]));
    }

    /**
     * An approved enrolment that later expires must not come back as an application.
     *
     * With expiredaction = suspend, process_expirations() re-suspends an expired ACTIVE
     * enrolment. The queue selects on status != ACTIVE, so without the timeend predicate
     * a long-approved user would reappear as a fresh application.
     *
     * @return void
     */
    public function test_expired_enrolment_does_not_reappear_in_the_queue(): void {
        global $DB;

        $this->setAdminUser();
        $this->plugin->set_config('expiredaction', ENROL_EXT_REMOVED_SUSPEND);
        [$user, $ueid] = $this->create_application();
        $this->plugin->confirm_enrolment([$ueid]);

        // Approve, then wind the enrolment period into the past and run the expiry sweep.
        $DB->set_field('user_enrolments', 'timestart', time() - (10 * DAYSECS), ['id' => $ueid]);
        $DB->set_field('user_enrolments', 'timeend', time() - DAYSECS, ['id' => $ueid]);
        $this->plugin->process_expirations(new \null_progress_trace());

        $ue = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_SUSPENDED, (int) $ue->status, 'core should have re-suspended it');
        $this->assertNotContains((int) $user->id, $this->queued_user_ids());
    }

    /**
     * A genuinely pending application is still listed by the same query.
     *
     * Guards the predicate added for the expiry case from being written too broadly.
     *
     * @return void
     */
    public function test_pending_application_is_listed_in_the_queue(): void {
        [$user] = $this->create_application();

        $this->assertContains((int) $user->id, $this->queued_user_ids());
    }

    /**
     * A deferred application stays in the queue.
     *
     * @return void
     */
    public function test_waiting_list_application_is_listed_in_the_queue(): void {
        $this->setAdminUser();
        [$user, $ueid] = $this->create_application();
        $this->plugin->wait_enrolment([$ueid]);

        $this->assertContains((int) $user->id, $this->queued_user_ids());
    }

    /**
     * Approving through core's "Edit enrolment" screen completes the approval.
     *
     * enrol/editenrolment.php never calls confirm_enrolment(); it drives
     * update_user_enrol() directly. The hook observer is what keeps the group membership
     * and the application row in step on that path.
     *
     * @return void
     */
    public function test_activating_outside_confirm_enrolment_completes_the_approval(): void {
        global $DB;

        $this->setAdminUser();
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $DB->insert_record('enrol_apply_groups', (object) [
            'enrolid' => $this->instance->id,
            'groupid' => $group->id,
        ]);
        [$user, $ueid] = $this->create_application();

        // Exactly what course_enrolment_manager::edit_enrolment() does.
        $this->plugin->update_user_enrol($this->instance, $user->id, ENROL_USER_ACTIVE);

        $this->assertFalse(
            $DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $ueid]),
            'the application row should have been cleared'
        );
        $this->assertTrue(
            $DB->record_exists('groups_members', [
                'groupid' => $group->id,
                'userid' => $user->id,
                'component' => 'enrol_apply',
            ]),
            'the configured group membership should have been granted'
        );
    }

    /**
     * Approving queues exactly one notification task, whichever route was taken.
     *
     * @return void
     */
    public function test_approval_queues_one_notification_task(): void {
        global $DB;

        $this->setAdminUser();
        [, $ueid] = $this->create_application();

        $this->plugin->confirm_enrolment([$ueid]);

        $tasks = $DB->get_records('task_adhoc', ['classname' => '\enrol_apply\task\notify_approval']);
        $this->assertCount(1, $tasks, 'the hook and confirm_enrolment must not queue two');
        $this->assertEquals($ueid, (int) json_decode(reset($tasks)->customdata)->userenrolmentid);
    }

    /**
     * The queued task tells the applicant their application was approved.
     *
     * @return void
     */
    public function test_notification_task_messages_the_applicant(): void {
        $this->setAdminUser();
        $this->preventResetByRollback();
        [$user, $ueid] = $this->create_application();
        $this->plugin->confirm_enrolment([$ueid]);

        $sink = $this->redirectMessages();
        $task = new \enrol_apply\task\notify_approval();
        $task->set_custom_data(['userenrolmentid' => $ueid]);
        $task->execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertEquals($user->id, $messages[0]->useridto);
        $this->assertEquals('confirmation', $messages[0]->eventtype);
    }

    /**
     * The task stays silent when the approval did not survive.
     *
     * The hook that queues it runs before the enrolment row is written, so the task must
     * never assume the approval actually happened.
     *
     * @return void
     */
    public function test_notification_task_is_silent_when_the_enrolment_is_not_active(): void {
        $this->setAdminUser();
        $this->preventResetByRollback();
        [, $ueid] = $this->create_application();

        $sink = $this->redirectMessages();
        $task = new \enrol_apply\task\notify_approval();
        $task->set_custom_data(['userenrolmentid' => $ueid]);
        $task->execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages);
    }

    /**
     * Suspending an active enrolment must not be mistaken for an approval.
     *
     * @return void
     */
    public function test_suspending_an_enrolment_does_not_trigger_the_approval_work(): void {
        global $DB;

        $this->setAdminUser();
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $DB->insert_record('enrol_apply_groups', (object) [
            'enrolid' => $this->instance->id,
            'groupid' => $group->id,
        ]);
        [$user, $ueid] = $this->create_application();

        $this->plugin->update_user_enrol($this->instance, $user->id, ENROL_USER_SUSPENDED);

        $this->assertTrue($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $ueid]));
        $this->assertFalse($DB->record_exists('groups_members', ['groupid' => $group->id, 'userid' => $user->id]));
    }

    /**
     * The applicant user ids the approval queue would list for this course instance.
     *
     * @return array Array of user ids.
     */
    protected function queued_user_ids(): array {
        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);

        ob_start();
        $table->out(50, false);
        ob_end_clean();

        return array_map(static fn($row) => (int) $row->userid, array_values($table->rawdata));
    }

    /**
     * Submit an application for a user through apply(), so every row the real path writes is written.
     *
     * apply() is the protected worker submit_application() delegates to once its lock,
     * duplicate, cap and eligibility checks pass, and it is reached through reflection rather
     * than by widening it. Unlike create_application(), this leaves an enrol_apply_submission
     * row. The current user is left as it is: apply() takes the applicant as an argument.
     *
     * The notifications apply() sends are caught and returned, because a message sink cannot be
     * nested: opening one replaces whichever sink the caller had open.
     *
     * @param \stdClass $applicant User the application is for.
     * @param \stdClass|null $instance Enrol instance applied to, this test's own by default.
     * @param \stdClass|null $data Submitted form data, an empty comment by default.
     * @return array The messages apply() sent.
     */
    protected function apply_for(\stdClass $applicant, ?\stdClass $instance = null, ?\stdClass $data = null): array {
        $sink = $this->redirectMessages();

        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);
        $method->invoke(
            $this->plugin,
            $instance ?? $this->instance,
            $applicant->id,
            $data ?? (object) ['applydescription' => '']
        );

        $messages = $sink->get_messages();
        $sink->close();

        return $messages;
    }

    /**
     * Confirming an application activates the enrolment and clears the application info row.
     *
     * @return void
     */
    public function test_confirm_enrolment_activates_the_user(): void {
        global $DB;

        $this->setAdminUser();
        [$user, $ueid] = $this->create_application();

        $this->plugin->confirm_enrolment([$ueid]);

        $ue = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_ACTIVE, (int) $ue->status);
        $this->assertFalse($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $ueid]));
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $user, '', true));
    }

    /**
     * Confirming honours the instance enrolment period by setting timeend.
     *
     * @return void
     */
    public function test_confirm_enrolment_applies_the_enrolment_period(): void {
        global $DB;

        $this->setAdminUser();
        $DB->set_field('enrol', 'enrolperiod', DAYSECS, ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
        [, $ueid] = $this->create_application();

        $this->plugin->confirm_enrolment([$ueid]);

        $ue = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertGreaterThan(0, (int) $ue->timeend);
        $this->assertEqualsWithDelta((int) $ue->timestart + DAYSECS, (int) $ue->timeend, 5);
    }

    /**
     * Deferring an application moves it onto the waiting list without granting access.
     *
     * @return void
     */
    public function test_wait_enrolment_moves_to_the_waiting_list(): void {
        global $DB;

        $this->setAdminUser();
        [$user, $ueid] = $this->create_application();

        $this->plugin->wait_enrolment([$ueid]);

        $ue = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertEquals(ENROL_APPLY_USER_WAIT, (int) $ue->status);
        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $user, '', true));
        $this->assertTrue($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $ueid]));
    }

    /**
     * The plugin object answers the capacity question, and answers it the same way.
     *
     * is_full() is how plugins that treat enrol_apply as an optional dependency reach the count,
     * through is_callable() on the plugin object; renaming or removing it makes them fall back to
     * their own count silently. See {@see \enrol_apply_plugin::is_full()}.
     *
     * @return void
     */
    public function test_the_plugin_object_answers_the_capacity_question(): void {
        global $DB;

        $this->assertTrue(is_callable([$this->plugin, 'is_full']));

        $DB->set_field('enrol', 'customint3', 1, ['id' => $this->instance->id]);
        $instance = $this->reload_instance();
        $this->assertFalse($this->plugin->is_full($instance));

        $this->create_application();
        $this->assertTrue($this->plugin->is_full($this->reload_instance()));

        // It agrees with the class it fronts with an expired enrolment present, which the cap does not count.
        $expired = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $expired->id, null, 0, time() - DAYSECS, ENROL_USER_ACTIVE);
        $instance = $this->reload_instance();
        $this->assertSame(
            \enrol_apply\local\capacity::applications_closed($instance),
            $this->plugin->is_full($instance)
        );
    }

    /**
     * Deferring clears an expiry the row was carrying.
     *
     * update_user_enrol() writes a date only when given one, and a waiting-list row keeping a
     * timeend is stranded once that date passes: core's suspend arms of process_expirations()
     * filter on status = active, which it fails, and the queue's predicate excludes it by that
     * timeend, so nobody can decide it.
     *
     * The expiry is in the FUTURE, as on a row suspended from core's "Edit enrolment" screen part
     * way through its period. A past one would prove nothing here: that row is a lapsed approval,
     * not an application awaiting a decision, and wait_enrolment() does not reach it at all
     * (test_a_lapsed_approval_is_not_decided_again).
     *
     * The test asserts both that the row carried an expiry beforehand and that the deferral ran:
     * wait_enrolment() silently skips a row that is not awaiting a decision, and one that fails
     * can_manage_application(). Without both, the cleared value proves nothing.
     *
     * @return void
     */
    public function test_deferring_clears_an_expiry_the_row_was_carrying(): void {
        global $DB;

        $this->setAdminUser();
        [, $ueid] = $this->create_application();

        $DB->set_field('user_enrolments', 'timeend', time() + DAYSECS, ['id' => $ueid]);
        $this->assertNotEquals(
            0,
            (int) $DB->get_field('user_enrolments', 'timeend', ['id' => $ueid]),
            'The precondition: the row must be carrying an expiry before the call.'
        );

        $sink = $this->redirectMessages();
        $this->plugin->wait_enrolment([$ueid]);
        $sink->close();

        $ue = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertEquals(
            ENROL_APPLY_USER_WAIT,
            (int) $ue->status,
            'The deferral must actually have run, or the cleared date proves nothing.'
        );
        $this->assertSame(0, (int) $ue->timeend);
    }

    /**
     * Approving resets an expiry the row was carrying, rather than inheriting it.
     *
     * confirm_enrolment() starts from the stored {user_enrolments} row. On an instance with no
     * enrolperiod, dropping the reset would let the row's timeend survive the approval, so the
     * enrolment would end on whatever date the row happened to carry, and under the default
     * expiredaction (keep) nothing would correct it once passed. Reachable: a restore writes an
     * archived timeend verbatim, and core's "Edit enrolment" screen can suspend an approved
     * enrolment part way through its period, which puts it back in the queue.
     *
     * The expiry is in the future because only then is the row awaiting a decision: one whose
     * timeend has passed is a lapsed approval, which confirm_enrolment() does not reach
     * (test_a_lapsed_approval_is_not_decided_again).
     *
     * @return void
     */
    public function test_approving_resets_an_expiry_the_row_was_carrying(): void {
        global $DB;

        $this->setAdminUser();
        [$user, $ueid] = $this->create_application();

        // The instance has no period of its own, so nothing else would overwrite the date.
        $this->assertSame(0, (int) $this->reload_instance()->enrolperiod);

        $DB->set_field('user_enrolments', 'timeend', time() + DAYSECS, ['id' => $ueid]);
        $this->assertNotEquals(
            0,
            (int) $DB->get_field('user_enrolments', 'timeend', ['id' => $ueid]),
            'The precondition: the row must be carrying an expiry before the approval.'
        );

        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid]);
        $sink->close();

        $ue = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        // The control: the approval really ran, so the cleared date is its doing.
        $this->assertEquals(ENROL_USER_ACTIVE, (int) $ue->status);
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $user, '', true));
        // An instance with no period grants an enrolment with no end.
        $this->assertSame(0, (int) $ue->timeend);
    }

    /**
     * The wait notification does not print an expiry that was just cleared.
     *
     * notify_applicant() is handed the row as it was read BEFORE the update, and
     * update_mail_content() substitutes {timeend} for every message type, so without clearing
     * the in-memory copy too the applicant is told their enrolment ends on a date that no
     * longer exists. The expiry is in the future, the only kind a row awaiting a decision can
     * carry; see test_deferring_clears_an_expiry_the_row_was_carrying().
     *
     * @return void
     */
    public function test_the_wait_notification_carries_no_expiry_that_was_just_cleared(): void {
        global $DB;

        $expiry = time() + DAYSECS;
        set_config('waitmailsubject', 'Waiting list', 'enrol_apply');
        set_config('waitmailcontent', 'Marker. Ends: {timeend}.', 'enrol_apply');

        $this->setAdminUser();
        [, $ueid] = $this->create_application();
        $DB->set_field('user_enrolments', 'timeend', $expiry, ['id' => $ueid]);

        $sink = $this->redirectMessages();
        $this->plugin->wait_enrolment([$ueid]);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $body = $messages[0]->fullmessage . ' ' . $messages[0]->fullmessagehtml;

        // The control: the configured template really was used and really was substituted.
        $this->assertStringContainsString('Marker.', $body);
        $this->assertStringNotContainsString('{timeend}', $body);

        // And the date that was cleared is not announced.
        $this->assertStringNotContainsString(userdate($expiry), $body);
    }

    /**
     * A deferred application can still be confirmed afterwards.
     *
     * @return void
     */
    public function test_confirm_enrolment_accepts_a_deferred_application(): void {
        global $DB;

        $this->setAdminUser();
        [$user, $ueid] = $this->create_application();
        $this->plugin->wait_enrolment([$ueid]);

        $this->plugin->confirm_enrolment([$ueid]);

        $ue = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_ACTIVE, (int) $ue->status);
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $user, '', true));
    }

    /**
     * Cancelling an application unenrols the user and removes the application info row.
     *
     * @return void
     */
    public function test_cancel_enrolment_unenrols_the_user(): void {
        global $DB;

        $this->setAdminUser();
        [$user, $ueid] = $this->create_application();

        $this->plugin->cancel_enrolment([$ueid]);

        $this->assertFalse($DB->record_exists('user_enrolments', ['id' => $ueid]));
        $this->assertFalse($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $ueid]));
        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $user));
    }

    /**
     * A user without the manage capability cannot approve an application.
     *
     * Changes that must make it fail: removing the can_manage_application() check from
     * confirm_enrolment(), which also fails test_confirm_enrolment_is_scoped_to_the_course.
     *
     * @return void
     */
    public function test_confirm_enrolment_requires_the_capability(): void {
        global $DB;

        [$applicant, $ueid] = $this->create_application();
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);

        $this->plugin->confirm_enrolment([$ueid]);

        $ue = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_SUSPENDED, (int) $ue->status);
        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $applicant, '', true));
    }

    /**
     * A teacher of the course may approve applications for that course.
     *
     * @return void
     */
    public function test_confirm_enrolment_allows_the_course_teacher(): void {
        global $DB;

        [, $ueid] = $this->create_application();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->plugin->confirm_enrolment([$ueid]);

        $ue = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_ACTIVE, (int) $ue->status);
    }

    /**
     * A teacher of one course may not approve an application belonging to another course.
     *
     * @return void
     */
    public function test_confirm_enrolment_is_scoped_to_the_course(): void {
        global $DB;

        [, $ueid] = $this->create_application();
        $othercourse = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $othercourse->id, 'editingteacher');
        $this->setUser($teacher);

        $this->plugin->confirm_enrolment([$ueid]);

        $ue = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_SUSPENDED, (int) $ue->status);
    }

    /**
     * An approval whose period has ended is not decided again, whichever decision is posted.
     *
     * Under an expiredaction of suspend, process_expirations() puts an approved enrolment whose
     * period ran out back to suspended with its past timeend, so by status alone it reads like a
     * fresh application. The queue does not list it and the review page refuses it, but a posted
     * id reaches the decision methods directly: without the expiry half of the lookup it would be
     * cancelled, unenrolling the learner, deferred, which clears the expiry and puts it back in
     * the queue, or approved a second time.
     *
     * Each call also carries a pending application, the control that the method really ran.
     *
     * @return void
     */
    public function test_a_lapsed_approval_is_not_decided_again(): void {
        global $DB;

        $this->setAdminUser();
        $this->plugin->set_config('expiredaction', ENROL_EXT_REMOVED_SUSPEND);

        $learner = $this->getDataGenerator()->create_user();
        $this->apply_for($learner);
        $lapsedueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $learner->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$lapsedueid]);
        $sink->close();

        // Approved long ago, the period wound into the past, and core's own sweep run over it.
        $DB->set_field('user_enrolments', 'timestart', time() - (10 * DAYSECS), ['id' => $lapsedueid]);
        $DB->set_field('user_enrolments', 'timeend', time() - DAYSECS, ['id' => $lapsedueid]);
        $this->plugin->process_expirations(new \null_progress_trace());

        $lapsed = $DB->get_record('user_enrolments', ['id' => $lapsedueid], '*', MUST_EXIST);
        // The precondition: core re-suspended it, so its status alone reads like an application.
        $this->assertEquals(ENROL_USER_SUSPENDED, (int) $lapsed->status);

        foreach (['confirm_enrolment', 'wait_enrolment', 'cancel_enrolment'] as $method) {
            [, $controlueid] = $this->create_application();

            $sink = $this->redirectMessages();
            $decided = $this->plugin->$method([$lapsedueid, $controlueid], 'Crafted post.');
            $sink->close();

            /* get_field() returns false for a row a cancellation removed, which is not the
               suspended status, so this holds for all three methods. */
            $this->assertNotEquals(
                ENROL_USER_SUSPENDED,
                $DB->get_field('user_enrolments', 'status', ['id' => $controlueid]),
                $method . ' left the pending application alone as well'
            );
            $this->assertSame(1, $decided, $method . ' must count the pending application alone');

            $row = $DB->get_record('user_enrolments', ['id' => $lapsedueid]);
            $this->assertNotFalse($row, $method . ' unenrolled the lapsed approval');
            $this->assertEquals(ENROL_USER_SUSPENDED, (int) $row->status, $method);
            $this->assertEquals((int) $lapsed->timeend, (int) $row->timeend, $method);
        }

        // Nothing reached the durable record either: the first decision stands, with no message.
        $record = $this->submission_of($learner);
        $this->assertEquals(\enrol_apply\local\submission::STATUS_APPROVED, (int) $record->status);
        $this->assertSame('', (string) $record->outcomemessage);
    }

    /**
     * A user enrolment of another method in a posted batch is skipped, and the rest is decided.
     *
     * The lookup joins on this plugin's instances. Without that join a suspended enrolment of any
     * other method passes it and the instance lookup after it throws, so a batch holding one
     * foreign id stopped half way: the applications before it decided and notified, the ones
     * after it never reached. The foreign id sits between two applications so both halves show.
     *
     * @return void
     */
    public function test_a_batch_skips_an_enrolment_of_another_method(): void {
        global $DB;

        $this->setAdminUser();

        foreach (['confirm_enrolment', 'wait_enrolment', 'cancel_enrolment'] as $method) {
            [, $first] = $this->create_application();
            [, $second] = $this->create_application();

            $outsider = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user(
                $outsider->id,
                $this->course->id,
                'student',
                'manual',
                0,
                0,
                ENROL_USER_SUSPENDED
            );
            $foreign = $DB->get_record_sql(
                "SELECT ue.*
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid
                  WHERE ue.userid = :userid AND e.enrol = :enrol",
                ['userid' => $outsider->id, 'enrol' => 'manual'],
                MUST_EXIST
            );
            // The precondition: by its status alone the foreign row reads like an application.
            $this->assertEquals(ENROL_USER_SUSPENDED, (int) $foreign->status);

            $sink = $this->redirectMessages();
            $decided = $this->plugin->$method([$first, (int) $foreign->id, $second]);
            $sink->close();

            $this->assertSame(2, $decided, $method);
            foreach ([$first, $second] as $ueid) {
                $this->assertNotEquals(
                    ENROL_USER_SUSPENDED,
                    $DB->get_field('user_enrolments', 'status', ['id' => $ueid]),
                    $method . ' did not decide every application in the batch'
                );
            }

            $row = $DB->get_record('user_enrolments', ['id' => $foreign->id]);
            $this->assertNotFalse($row, $method . ' unenrolled a user enrolment of another method');
            $this->assertEquals(ENROL_USER_SUSPENDED, (int) $row->status, $method);
        }
    }

    /**
     * Reload the instance record so that a directly written column is visible to the plugin.
     *
     * @return \stdClass The instance as it now stands in the database.
     */
    protected function reload_instance(): \stdClass {
        global $DB;

        return $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
    }

    /**
     * Applications are refused before the application window opens.
     *
     * @return void
     */
    public function test_allow_apply_refuses_before_the_application_window_opens(): void {
        global $DB;

        // The control: with no window configured the same instance accepts applications.
        $this->assertTrue($this->plugin->allow_apply($this->instance));

        $opens = time() + DAYSECS;
        $DB->set_field('enrol', 'enrolstartdate', $opens, ['id' => $this->instance->id]);

        $refusal = $this->plugin->allow_apply($this->reload_instance());
        $this->assertIsString($refusal);
        $this->assertSame(get_string('canntenrolearly', 'enrol_apply', userdate($opens)), $refusal);
    }

    /**
     * Applications are refused after the application window closes.
     *
     * @return void
     */
    public function test_allow_apply_refuses_after_the_application_window_closes(): void {
        global $DB;

        // The control: with no window configured the same instance accepts applications.
        $this->assertTrue($this->plugin->allow_apply($this->instance));

        $closed = time() - DAYSECS;
        $DB->set_field('enrol', 'enrolenddate', $closed, ['id' => $this->instance->id]);

        $refusal = $this->plugin->allow_apply($this->reload_instance());
        $this->assertIsString($refusal);
        $this->assertSame(get_string('canntenrollate', 'enrol_apply', userdate($closed)), $refusal);
    }

    /**
     * A window that is currently open admits.
     *
     * The two refusal tests above use "no window configured" as their control, which leaves the
     * comparison against the current time unpinned. Changes that must make it fail: refusing
     * every instance that carries an opening date, whether or not it has arrived.
     *
     * @return void
     */
    public function test_allow_apply_admits_inside_an_open_application_window(): void {
        global $DB;

        $DB->set_field('enrol', 'enrolstartdate', time() - DAYSECS, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'enrolenddate', time() + DAYSECS, ['id' => $this->instance->id]);

        $this->assertTrue($this->plugin->allow_apply($this->reload_instance()));
    }

    /**
     * An opening date that has passed admits even with no closing date, and the mirror.
     *
     * @return void
     */
    public function test_allow_apply_admits_on_a_half_open_window(): void {
        global $DB;

        $DB->set_field('enrol', 'enrolstartdate', time() - DAYSECS, ['id' => $this->instance->id]);
        $this->assertTrue($this->plugin->allow_apply($this->reload_instance()));

        $DB->set_field('enrol', 'enrolstartdate', 0, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'enrolenddate', time() + DAYSECS, ['id' => $this->instance->id]);
        $this->assertTrue($this->plugin->allow_apply($this->reload_instance()));
    }

    /**
     * A cohort restriction admits members and refuses everybody else.
     *
     * @return void
     */
    public function test_allow_apply_admits_only_cohort_members(): void {
        global $DB;

        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'Servidores 2026']);
        $member = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $member->id);

        /* The control: with no restriction the outsider is admitted, which is what proves
           the restriction is the reason they are refused below and not the fixture. */
        $this->setUser($outsider);
        $this->assertTrue($this->plugin->allow_apply($this->instance));

        $DB->set_field('enrol', 'customint5', $cohort->id, ['id' => $this->instance->id]);
        $restricted = $this->reload_instance();

        $this->setUser($member);
        $this->assertTrue($this->plugin->allow_apply($restricted));

        $this->setUser($outsider);
        $refusal = $this->plugin->allow_apply($restricted);
        $this->assertIsString($refusal);
        $this->assertSame(get_string('cohortnonmemberinfo', 'enrol_apply'), $refusal);

        /* The refusal must not name the cohort: enrol_page_hook() renders it to any
           authenticated non-member, so the name would tell a stranger which group the course
           is for. The assertion is on the cohort's own name rather than on the message, so it
           keeps holding if the wording is rewritten. The name can only leak if the string
           regains a placeholder and allow_apply() passes the name again. */
        $this->assertStringNotContainsString('Servidores 2026', $refusal);
        $this->assertStringNotContainsString(
            '{$a}',
            $refusal,
            'The string must not carry an uninterpolated placeholder either.'
        );
    }

    /**
     * A restriction naming a cohort that no longer exists refuses with a real message.
     *
     * enrol_self returns null in this situation and its enrolment method then vanishes
     * from the page; here the return value is rendered straight into a notification, so a
     * null would render an empty one.
     *
     * @return void
     */
    public function test_allow_apply_returns_a_string_when_the_cohort_was_deleted(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $missing = (int) $DB->get_field_sql('SELECT COALESCE(MAX(id), 0) FROM {cohort}') + 1;
        $DB->set_field('enrol', 'customint5', $missing, ['id' => $this->instance->id]);

        $refusal = $this->plugin->allow_apply($this->reload_instance());
        $this->assertNotNull($refusal);
        $this->assertIsString($refusal);
        $this->assertNotSame('', $refusal);
    }

    /**
     * The sentinel a cross-site restore writes is a live refusal, not "no restriction".
     *
     * @return void
     */
    public function test_allow_apply_refuses_on_the_restore_sentinel(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // The control: zero really does mean "no restriction".
        $this->assertTrue($this->plugin->allow_apply($this->instance));

        $DB->set_field('enrol', 'customint5', -1, ['id' => $this->instance->id]);

        $refusal = $this->plugin->allow_apply($this->reload_instance());
        $this->assertIsString($refusal);
        $this->assertNotSame('', $refusal);
    }

    /**
     * The edit form refuses a cohort it never offered.
     *
     * The element is a picker over the cohorts the editor may see; without a server side
     * check the submitted value is any cohort id on the site, which makes the form a
     * membership oracle for anybody holding enrol/apply:config.
     *
     * @return void
     */
    public function test_edit_form_rejects_a_cohort_outside_the_offered_list(): void {
        global $CFG;

        require_once($CFG->dirroot . '/enrol/apply/edit_form.php');

        $this->setAdminUser();
        $context = \context_course::instance($this->course->id);

        // A cohort in a category context the course does not descend from is never offered.
        $othercategory = $this->getDataGenerator()->create_category();
        $hidden = $this->getDataGenerator()->create_cohort([
            'contextid' => \context_coursecat::instance($othercategory->id)->id,
        ]);

        $form = new \enrol_apply_edit_form(null, [$this->instance, $this->plugin, $context]);

        $errors = $form->validation([
            'status' => ENROL_INSTANCE_ENABLED,
            'enrolstartdate' => 0,
            'enrolenddate' => 0,
            'customint5' => $hidden->id,
        ], []);
        $this->assertArrayHasKey('customint5', $errors);

        // The control: a cohort the form does offer passes the same check.
        $offered = $this->getDataGenerator()->create_cohort([
            'contextid' => \context_system::instance()->id,
        ]);
        $errors = $form->validation([
            'status' => ENROL_INSTANCE_ENABLED,
            'enrolstartdate' => 0,
            'enrolenddate' => 0,
            'customint5' => $offered->id,
        ], []);
        $this->assertArrayNotHasKey('customint5', $errors);
    }

    /**
     * A forged cohort id never survives a real submission of the edit form.
     *
     * The direct call above pins the plugin's own guard; this one pins the whole path,
     * because that guard is deliberately the second barrier and not the first. Whichever
     * of the two acts, what must never happen is a restriction naming a cohort the editor
     * was not offered.
     *
     * @return void
     */
    public function test_the_form_never_carries_a_cohort_it_did_not_offer(): void {
        global $CFG;

        require_once($CFG->dirroot . '/enrol/apply/edit_form.php');

        $this->setAdminUser();
        $context = \context_course::instance($this->course->id);

        $othercategory = $this->getDataGenerator()->create_category();
        $foreign = $this->getDataGenerator()->create_cohort([
            'contextid' => \context_coursecat::instance($othercategory->id)->id,
        ]);
        // An offered cohort, so that the element really is a select and not the hidden fallback.
        $offered = $this->getDataGenerator()->create_cohort(['contextid' => \context_system::instance()->id]);

        /* The forged id does not merely arrive as zero: the key is dropped from the export
           altogether, because exportValue() returns null for a value that is not one of the
           element's options and moodleform omits a null. enrol_plugin::update_instance()
           copies a property only when isset(), so an existing restriction is left exactly
           as it was rather than being cleared by the forgery. */
        $forged = $this->submit_edit_form($context, $foreign->id);
        $this->assertObjectNotHasProperty('customint5', $forged);

        /* The control: the same submission carrying an offered cohort does come through
           with that id, which is what proves the element can carry a restriction at all
           rather than dropping customint5 unconditionally. */
        $accepted = $this->submit_edit_form($context, $offered->id);
        $this->assertEquals($offered->id, (int) $accepted->customint5);

        /* And zero is a real option, so a restriction stays removable. This is what the
           hidden setConstant(0) fallback in definition() exists to preserve on a site that
           offers no cohorts at all. */
        $cleared = $this->submit_edit_form($context, 0);
        $this->assertObjectHasProperty('customint5', $cleared);
        $this->assertSame(0, (int) $cleared->customint5);
    }

    /**
     * Drive a real submission of the instance edit form and return what it exports.
     *
     * @param \context_course $context Course context the instance belongs to.
     * @param int $cohortid Value to submit for the cohort restriction.
     * @return \stdClass The data the form exports.
     */
    protected function submit_edit_form(\context_course $context, int $cohortid): \stdClass {
        /* _process_submission() checks the sesskey through confirm_sesskey(), which reads the
           request rather than the data it is handed, and Moodle's PHPUnit harness does not
           reset $_POST between tests. So this is put back afterwards, or a later test inherits
           a live sesskey it never set. */
        $hadsesskey = array_key_exists('sesskey', $_POST);
        $previoussesskey = $_POST['sesskey'] ?? null;
        $_POST['sesskey'] = sesskey();

        $submitted = [
            '_qf__enrol_apply_edit_form' => 1,
            'sesskey' => sesskey(),
            'name' => '',
            'status' => ENROL_INSTANCE_ENABLED,
            'customint5' => $cohortid,
            'customint6' => 1,
            'roleid' => $this->instance->roleid,
            'notify' => ['$@NONE@$'],
            'customint3' => 0,
            'id' => $this->instance->id,
            'courseid' => $this->course->id,
        ];

        try {
            /* The constructor is where the sesskey is checked: _process_submission() runs
               from it, and throws invalidsesskey when confirm_sesskey() fails. */
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
        $this->assertNotNull($data, 'the simulated submission should reach the form');

        return $data;
    }

    /**
     * The form refuses a window whose closing date precedes its opening date.
     *
     * @return void
     */
    public function test_edit_form_rejects_a_window_that_closes_before_it_opens(): void {
        global $CFG;

        require_once($CFG->dirroot . '/enrol/apply/edit_form.php');

        $this->setAdminUser();
        $context = \context_course::instance($this->course->id);
        $form = new \enrol_apply_edit_form(null, [$this->instance, $this->plugin, $context]);

        $errors = $form->validation([
            'status' => ENROL_INSTANCE_ENABLED,
            'enrolstartdate' => time() + DAYSECS,
            'enrolenddate' => time(),
            'customint5' => 0,
        ], []);
        $this->assertArrayHasKey('enrolenddate', $errors);

        // The control: the same two dates the right way round are accepted.
        $errors = $form->validation([
            'status' => ENROL_INSTANCE_ENABLED,
            'enrolstartdate' => time(),
            'enrolenddate' => time() + DAYSECS,
            'customint5' => 0,
        ], []);
        $this->assertArrayNotHasKey('enrolenddate', $errors);
    }

    /**
     * The approver reads what the applicant typed, not what was already on their account.
     *
     * Standard and custom fields alike come from the submitted data; reading custom fields back
     * from {user_info_data} would show the approver a value the applicant did not enter.
     *
     * @return void
     */
    public function test_the_notification_carries_the_submitted_value_not_the_stored_one(): void {
        global $DB;

        $field = $this->create_text_profile_field('typedfield');
        $key = \enrol_apply\local\fields::custom_key((int) $field->id);
        set_config('allowedfields', $key . ',s_city', 'enrol_apply');
        $DB->set_field(
            'enrol',
            'customtext4',
            \enrol_apply\local\fieldset::from_keys([$key, 's_city'])->to_json(),
            ['id' => $this->instance->id]
        );

        $applicant = $this->getDataGenerator()->create_user(['city' => 'StoredCity']);
        $DB->insert_record('user_info_data', (object) [
            'userid' => $applicant->id,
            'fieldid' => $field->id,
            'data' => 'StoredAnswer',
            'dataformat' => 0,
        ]);

        // Somebody in the course has to be notified, or no message is built at all.
        $approver = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($approver->id, $this->course->id, 'editingteacher');
        $DB->set_field('enrol', 'customtext3', '$@ALL@$', ['id' => $this->instance->id]);
        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $messages = $this->apply_for($applicant, $instance, (object) [
            'applydescription' => 'Please let me in',
            'city' => 'TypedCity',
            'profile_field_typedfield' => 'TypedAnswer',
        ]);

        $this->assertNotEmpty($messages, 'the approver should have been notified');
        $body = $messages[0]->fullmessagehtml;

        $this->assertStringContainsString('TypedAnswer', $body);
        $this->assertStringNotContainsString('StoredAnswer', $body);
        $this->assertStringContainsString('TypedCity', $body);
        $this->assertStringNotContainsString('StoredCity', $body);
    }

    /**
     * Only teachers whose own enrolment is active are told about a new application.
     *
     * The message links to the course's approval queue, whose require_login() refuses a teacher
     * whose enrolment is suspended or has ended, while their role, and with it the capability,
     * survives. Both recipient settings are checked: everybody holding the capability, and a list
     * naming all three teachers. The active teacher is the control: without them the test would
     * pass against a notification that reaches nobody.
     *
     * @return void
     */
    public function test_only_actively_enrolled_teachers_hear_about_a_new_application(): void {
        global $DB;

        $active = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($active->id, $this->course->id, 'editingteacher');
        $suspended = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $suspended->id,
            $this->course->id,
            'editingteacher',
            'manual',
            0,
            0,
            ENROL_USER_SUSPENDED
        );
        $ended = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $ended->id,
            $this->course->id,
            'editingteacher',
            'manual',
            time() - (10 * DAYSECS),
            time() - DAYSECS
        );
        $teachers = [(int) $active->id, (int) $suspended->id, (int) $ended->id];

        foreach (['$@ALL@$', implode(',', $teachers)] as $setting) {
            $DB->set_field('enrol', 'customtext3', $setting, ['id' => $this->instance->id]);

            $messages = $this->apply_for($this->getDataGenerator()->create_user(), $this->reload_instance());
            $notified = array_map(static fn($message): int => (int) $message->useridto, $messages);

            $this->assertContains((int) $active->id, $notified, $setting);
            $this->assertNotContains((int) $suspended->id, $notified, $setting);
            $this->assertNotContains((int) $ended->id, $notified, $setting);
        }
    }

    /**
     * A raw angle bracket in a submitted value does not break the notification.
     *
     * The application form cleans its fields as PARAM_TEXT, but apply() takes whatever data its
     * caller hands it. The notification escapes each value at the sink rather than stripping it,
     * so a bare "<" reaches the approver whole; see fields::submitted_values().
     *
     * @return void
     */
    public function test_the_notification_survives_a_raw_angle_bracket(): void {
        global $DB;

        $field = $this->create_text_profile_field('rawfield');
        $key = \enrol_apply\local\fields::custom_key((int) $field->id);
        set_config('allowedfields', $key, 'enrol_apply');
        $DB->set_field(
            'enrol',
            'customtext4',
            \enrol_apply\local\fieldset::from_keys([$key])->to_json(),
            ['id' => $this->instance->id]
        );

        $applicant = $this->getDataGenerator()->create_user();
        $approver = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($approver->id, $this->course->id, 'editingteacher');
        $DB->set_field('enrol', 'customtext3', '$@ALL@$', ['id' => $this->instance->id]);
        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $messages = $this->apply_for($applicant, $instance, (object) [
            'applydescription' => '',
            'profile_field_rawfield' => 'A<B and R&D',
        ]);

        $this->assertNotEmpty($messages);
        $body = $messages[0]->fullmessagehtml;

        /* The whole answer arrives, escaped exactly once. Asserting only that "A<B" is
           absent would pass against a body that had silently truncated the value to "A". */
        $this->assertStringContainsString('A&lt;B and R&amp;D', $body);
        $this->assertStringNotContainsString('A&lt;B and R&amp;amp;D', $body);
        // And the bare bracket is not sitting in the body as the start of a tag.
        $this->assertStringNotContainsString('A<B', $body);
    }

    /**
     * Create a text custom profile field and return its record.
     *
     * @param string $shortname Field shortname.
     * @return \stdClass The created {user_info_field} record.
     */
    protected function create_text_profile_field(string $shortname): \stdClass {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        $categoryid = $DB->insert_record('user_info_category', (object) ['name' => 'Extra', 'sortorder' => 1]);
        $id = $DB->insert_record('user_info_field', (object) [
            'shortname' => $shortname,
            'name' => 'Extra ' . $shortname,
            'datatype' => 'text',
            'categoryid' => $categoryid,
            'sortorder' => 1,
            'required' => 0,
            'locked' => 0,
            'visible' => PROFILE_VISIBLE_ALL,
            'forceunique' => 0,
            'signup' => 0,
            'defaultdata' => '',
            'param1' => 30,
            'param2' => 2048,
        ]);

        return $DB->get_record('user_info_field', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Applications are refused while the instance is disabled or closed to new enrolments.
     *
     * @return void
     */
    public function test_allow_apply_respects_instance_state(): void {
        global $DB;

        $this->assertTrue($this->plugin->allow_apply($this->instance));

        $DB->set_field('enrol', 'customint6', 0, ['id' => $this->instance->id]);
        $closed = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
        $this->assertIsString($this->plugin->allow_apply($closed));

        $DB->set_field('enrol', 'customint6', 1, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $this->instance->id]);
        $disabled = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
        $this->assertIsString($this->plugin->allow_apply($disabled));
    }

    /**
     * Render the enrolment page's own panel for the current user.
     *
     * The page url is set as enrol/index.php sets it before the hook runs.
     *
     * @param \stdClass|null $instance Instance to render, the fixture's own by default.
     * @return string The rendered markup.
     */
    protected function enrol_panel(?\stdClass $instance = null): string {
        global $PAGE;

        $PAGE->set_url('/enrol/index.php', ['id' => $this->course->id]);

        return (string) $this->plugin->enrol_page_hook($instance ?? $this->instance);
    }

    /**
     * Somebody who has applied reads about their OWN application, in each of its four states.
     *
     * The last two are the pair worth the setup. An ACTIVE enrolment that grants access is
     * approved and working; one whose period has run out is approved and shut out, and only the
     * second may be told its enrolment is not active.
     *
     * @return void
     */
    public function test_the_enrolment_panel_describes_the_applicants_own_state(): void {
        global $DB;

        [$applicant, $ueid] = $this->create_application();
        $this->setUser($applicant);

        $this->assertStringContainsString(
            get_string('applicationsubmitted_body', 'enrol_apply'),
            $this->enrol_panel()
        );

        $DB->set_field('user_enrolments', 'status', ENROL_APPLY_USER_WAIT, ['id' => $ueid]);
        $this->assertStringContainsString(
            get_string('applicationdeferred_body', 'enrol_apply'),
            $this->enrol_panel()
        );

        $DB->set_field('user_enrolments', 'status', ENROL_USER_ACTIVE, ['id' => $ueid]);
        // The precondition: this row really does grant access, so the next assertion is about that.
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $applicant, '', true));
        $this->assertStringContainsString(
            get_string('applicationapproved_body', 'enrol_apply'),
            $this->enrol_panel()
        );

        $DB->set_field('user_enrolments', 'timeend', time() - DAYSECS, ['id' => $ueid]);
        /* is_enrolled() memoises "enrolled until" on the session user and this fixture writes the
           row behind its back, so $USER is rebuilt rather than left holding the previous answer.
           Production does not need it: update_user_enrol() marks the user dirty, which drops the
           memo. */
        $this->setUser($applicant);
        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $applicant, '', true));
        $this->assertStringContainsString(
            get_string('applicationinactive_body', 'enrol_apply'),
            $this->enrol_panel()
        );
    }

    /**
     * A method that has stopped taking applications still tells its applicants about theirs.
     *
     * Pins that enrol_page_hook() tests the applicant's own row before allow_apply(), so an
     * applicant is not shown "Enrolment is disabled or inactive". The control is in the same run:
     * somebody who has not applied still gets the refusal, so the test fails if the hook stops
     * calling allow_apply().
     *
     * @return void
     */
    public function test_an_applicant_is_not_told_about_somebody_elses_refusal(): void {
        global $DB;

        [$applicant] = $this->create_application();
        $DB->set_field('enrol', 'customint6', 0, ['id' => $this->instance->id]);
        $closed = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $this->setUser($applicant);
        $panel = $this->enrol_panel($closed);
        $this->assertStringContainsString(get_string('applicationsubmitted_body', 'enrol_apply'), $panel);
        $this->assertStringNotContainsString(get_string('cantenrol', 'enrol_apply'), $panel);

        // The control: a stranger to this method still reads the refusal.
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertStringContainsString(
            get_string('cantenrol', 'enrol_apply'),
            $this->enrol_panel($closed)
        );
    }

    /**
     * With the notification settings empty, the applicant still gets a subject and a body.
     *
     * All six settings ship empty, so notify_applicant() falls back at read time; see there for
     * why a settings.php default cannot do it.
     *
     * The subject is asserted in its PLAIN spelling against a course name carrying an ampersand:
     * the body is HTML and wants the escaped name, while the subject is not HTML and is escaped
     * again by the message sink.
     *
     * @return void
     */
    public function test_an_unconfigured_notification_still_carries_a_subject_and_a_body(): void {
        global $DB;

        $DB->set_field('course', 'fullname', 'R&D induction', ['id' => $this->course->id]);
        [$applicant, $ueid] = $this->create_application();
        $this->setAdminUser();

        $sink = $this->redirectMessages();
        $this->plugin->wait_enrolment([$ueid]);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame(
            get_string('waitmailsubject_default', 'enrol_apply', 'R&D induction'),
            (string) $message->subject
        );
        $this->assertNotEmpty(trim((string) $message->fullmessagehtml));
        $this->assertStringContainsString($applicant->firstname, (string) $message->fullmessagehtml);
        // The body's own course name IS escaped, because it lands in fullmessagehtml.
        $this->assertStringContainsString('R&amp;D induction', (string) $message->fullmessagehtml);
    }

    /**
     * An administrator who has written their own wording keeps it, and the two halves are
     * independent.
     *
     * The control for the fallback above. The subject and the body fall back independently, so
     * a fallback that switched on "either is empty" would throw away a configured subject.
     *
     * @return void
     */
    public function test_a_configured_notification_wording_is_not_replaced(): void {
        [, $ueid] = $this->create_application();
        $this->setAdminUser();
        set_config('waitmailsubject', 'We have your application', 'enrol_apply');

        $sink = $this->redirectMessages();
        $this->plugin->wait_enrolment([$ueid]);
        $messages = $sink->get_messages();
        $sink->close();

        $message = reset($messages);
        $this->assertSame('We have your application', (string) $message->subject);
        // The body was left empty, so it still falls back on its own.
        $this->assertNotEmpty(trim((string) $message->fullmessagehtml));
    }

    /**
     * The single durable record of one applicant, failing the test when there is not exactly one.
     *
     * @param \stdClass $user Applicant.
     * @return \stdClass The enrol_apply_submission row.
     */
    protected function submission_of(\stdClass $user): \stdClass {
        global $DB;

        $rows = $DB->get_records('enrol_apply_submission', [
            'courseid' => $this->course->id,
            'userid' => $user->id,
        ]);
        $this->assertCount(1, $rows);

        return reset($rows);
    }

    /**
     * Applying writes a durable record beside the application info row.
     *
     * @return void
     */
    public function test_applying_writes_a_submission_row(): void {
        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_for($applicant);

        $row = $this->submission_of($applicant);
        $this->assertEquals($this->instance->id, (int) $row->enrolid);
        $this->assertEquals(\enrol_apply\local\submission::STATUS_PENDING, (int) $row->status);
        $this->assertEquals(0, (int) $row->timedecided);
        $this->assertEquals(0, (int) $row->decidedby);
        $this->assertGreaterThan(0, (int) $row->timecreated);
        // No message was typed, so the column the decider writes to must still be empty.
        $this->assertSame('', (string) $row->outcomemessage);
    }

    /**
     * Approving an application keeps its durable record and stamps the decision on it.
     *
     * @return void
     */
    public function test_a_submission_row_survives_approval(): void {
        global $DB;

        $approver = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $approver->id,
            $this->course->id,
            $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST)
        );
        $this->setUser($approver);

        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_for($applicant);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid]);
        $sink->close();

        /* The control. The application info row is deleted on approval, which is the whole
           reason the durable record cannot live on that table - without this assertion the
           test would not distinguish "the trail survives" from "nothing is ever deleted". */
        $this->assertFalse($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $ueid]));

        $row = $this->submission_of($applicant);
        $this->assertEquals(\enrol_apply\local\submission::STATUS_APPROVED, (int) $row->status);
        $this->assertGreaterThan(0, (int) $row->timedecided);
        $this->assertEquals($approver->id, (int) $row->decidedby);
        $this->assertSame('', (string) $row->outcomemessage);
    }

    /**
     * Cancelling an application keeps its durable record, though it unenrols the applicant.
     *
     * @return void
     */
    public function test_a_submission_row_survives_cancellation(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_for($applicant);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        $sink = $this->redirectMessages();
        $this->plugin->cancel_enrolment([$ueid]);
        $sink->close();

        // The control: cancellation really did unenrol, so the record outlived a real deletion.
        $this->assertFalse($DB->record_exists('user_enrolments', ['id' => $ueid]));
        $this->assertFalse($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $ueid]));

        $row = $this->submission_of($applicant);
        $this->assertEquals(\enrol_apply\local\submission::STATUS_CANCELLED, (int) $row->status);
        $this->assertGreaterThan(0, (int) $row->timedecided);
        $this->assertEquals(get_admin()->id, (int) $row->decidedby);
    }

    /**
     * Deferring an application to the waiting list stamps its durable record.
     *
     * @return void
     */
    public function test_a_submission_row_records_a_deferral(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_for($applicant);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        $sink = $this->redirectMessages();
        $this->plugin->wait_enrolment([$ueid]);
        $sink->close();

        $row = $this->submission_of($applicant);
        $this->assertEquals(\enrol_apply\local\submission::STATUS_WAITING, (int) $row->status);
        $this->assertEquals(get_admin()->id, (int) $row->decidedby);
    }

    /**
     * Unenrolling an approved applicant leaves the durable record behind.
     *
     * @return void
     */
    public function test_a_submission_row_survives_unenrolment(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_for($applicant);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid]);
        $sink->close();

        $this->plugin->unenrol_user($this->instance, $applicant->id);

        // The control: the enrolment really is gone, so this is not a no-op.
        $this->assertFalse($DB->record_exists('user_enrolments', ['id' => $ueid]));

        $row = $this->submission_of($applicant);
        $this->assertEquals(\enrol_apply\local\submission::STATUS_APPROVED, (int) $row->status);
    }

    /**
     * Deleting the enrolment method keeps the durable records, and only those.
     *
     * The controls matter as much as the subject: enrol_apply_groups and the pending comment in
     * enrol_apply_applicationinfo must still be cleaned up, or "the record survived" would only
     * mean "nothing is deleted".
     *
     * @return void
     */
    public function test_delete_instance_keeps_the_submission_rows(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_for($applicant);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $DB->insert_record('enrol_apply_groups', (object) [
            'enrolid' => $this->instance->id,
            'groupid' => $group->id,
        ]);

        $this->plugin->delete_instance($this->instance);

        // The controls: instance configuration and the pending comment are still cleaned up.
        $this->assertFalse($DB->record_exists('enrol_apply_groups', ['enrolid' => $this->instance->id]));
        $this->assertFalse($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $ueid]));
        $this->assertFalse($DB->record_exists('enrol', ['id' => $this->instance->id]));

        // The subject: the record of who applied and what they wrote outlives the method.
        $row = $this->submission_of($applicant);
        $this->assertEquals($this->instance->id, (int) $row->enrolid);
    }

    /**
     * A decision already recorded is not restamped, so the record keeps the first one.
     *
     * decide() skips a row already at the target status unless the caller passes
     * $isfreshdecision, which the decision methods in lib.php do when they know the enrolment
     * moved. Without the skip, a bare call like the one below would move timedecided and
     * re-attribute the decision to whoever touched the enrolment next.
     *
     * @return void
     */
    public function test_a_recorded_decision_is_not_restamped(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_for($applicant);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid]);
        $sink->close();

        $first = $this->submission_of($applicant);
        $this->assertEquals(get_admin()->id, (int) $first->decidedby);

        /* Somebody else touches the same, already-approved enrolment. The decision belongs to
           whoever took it, not to whoever edited the row afterwards. */
        $later = $this->getDataGenerator()->create_user();
        $this->setUser($later);
        $DB->set_field('enrol_apply_submission', 'timedecided', 111, ['id' => $first->id]);
        \enrol_apply\local\submission::decide(
            $ueid,
            \enrol_apply\local\submission::STATUS_APPROVED,
            (int) $later->id
        );

        $again = $this->submission_of($applicant);
        $this->assertEquals(get_admin()->id, (int) $again->decidedby);
        $this->assertEquals(111, (int) $again->timedecided);

        // The control: a genuine change of decision IS recorded, so this is not a dead write.
        \enrol_apply\local\submission::decide(
            $ueid,
            \enrol_apply\local\submission::STATUS_CANCELLED,
            (int) $later->id
        );
        $changed = $this->submission_of($applicant);
        $this->assertEquals(\enrol_apply\local\submission::STATUS_CANCELLED, (int) $changed->status);
        $this->assertEquals($later->id, (int) $changed->decidedby);
    }

    /**
     * An unknown status is refused rather than written.
     *
     * @return void
     */
    public function test_deciding_with_an_unknown_status_throws(): void {
        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_for($applicant);

        $this->expectException(\coding_exception::class);
        \enrol_apply\local\submission::decide(1, 99, 1);
    }

    /**
     * A second application through the same enrolment method creates nothing, and is not a refusal.
     *
     * What is enforced is one live application per enrolment method, not per course:
     * submit_application()'s lock is keyed on the instance and the user, and its duplicate check
     * looks up user_enrolments by enrolid. So (courseid, userid) cannot be a unique key on the
     * durable record: a course carrying two apply instances lets the same person hold two
     * applications, and pseudonymising a deleted course zeroes userid.
     *
     * @return void
     */
    public function test_a_second_application_through_the_same_method_is_refused(): void {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $this->setUser($applicant);

        $sink = $this->redirectMessages();
        $first = $this->plugin->submit_application($this->instance, $applicant->id, (object) ['applydescription' => 'One']);
        $second = $this->plugin->submit_application($this->instance, $applicant->id, (object) ['applydescription' => 'Two']);
        $sink->close();

        $this->assertTrue($first->was_created());
        $this->assertFalse($second->was_created());
        $this->assertCount(1, $DB->get_records('enrol_apply_submission', ['userid' => $applicant->id]));

        /* And the second is NOT a refusal: the applicant does have an application. See
           application_result::already_applied(). */
        $this->assertFalse($second->is_refusal());
        $this->assertSame('', $second->reason());
    }

    /**
     * The write door refuses an applicant the cohort restriction excludes.
     *
     * submit_application() is called directly: it re-checks allow_apply() itself, so callers
     * other than the form are held to the same restrictions.
     *
     * The operator is a cohort MEMBER and the applicant is not. With the one-argument
     * allow_apply(), judged against $USER, the admin's own membership would admit the outsider,
     * so this pins that the applicant's user id is passed and not merely that the call exists.
     *
     * @return void
     */
    public function test_the_write_door_refuses_an_applicant_outside_the_cohort(): void {
        global $DB;

        $cohort = $this->getDataGenerator()->create_cohort();
        $member = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $member->id);

        $DB->set_field('enrol', 'customint5', $cohort->id, ['id' => $this->instance->id]);
        $instance = $this->reload_instance();

        $admin = get_admin();
        cohort_add_member($cohort->id, (int) $admin->id);
        $this->setAdminUser();

        $sink = $this->redirectMessages();
        $refused = $this->plugin->submit_application($instance, $outsider->id, (object) ['applydescription' => 'Out']);
        $admitted = $this->plugin->submit_application($instance, $member->id, (object) ['applydescription' => 'In']);
        $sink->close();

        $this->assertTrue($refused->is_refusal());
        $this->assertNotSame('', $refused->reason());
        $this->assertFalse($DB->record_exists('user_enrolments', [
            'userid' => $outsider->id,
            'enrolid' => $instance->id,
        ]));
        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['userid' => $outsider->id]));

        /* The control, in the same execution. Without it this test would keep passing if
           submit_application() refused everybody - by throwing early, by a broken fixture,
           or by a guard placed where nothing can get past it. */
        $this->assertTrue($admitted->was_created());
        $this->assertTrue($DB->record_exists('user_enrolments', [
            'userid' => $member->id,
            'enrolid' => $instance->id,
        ]));
    }

    /**
     * The write door refuses an instance that has stopped taking new applications.
     *
     * Unlike the cohort restriction, this one asks nothing about the applicant, so it pins the
     * guard itself rather than the user id passed to it.
     *
     * @return void
     */
    public function test_the_write_door_refuses_an_instance_taking_no_new_applications(): void {
        global $DB;

        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();

        /* The control runs first and on the untouched instance: it is what proves the
           refusal below is customint6 and not the fixture. */
        $this->setUser($first);
        $sink = $this->redirectMessages();
        $admitted = $this->plugin->submit_application($this->instance, $first->id, (object) ['applydescription' => 'Open']);
        $sink->close();

        $this->assertTrue($admitted->was_created());
        $this->assertTrue($DB->record_exists('user_enrolments', [
            'userid' => $first->id,
            'enrolid' => $this->instance->id,
        ]));

        $DB->set_field('enrol', 'customint6', 0, ['id' => $this->instance->id]);

        $this->setUser($second);
        $refused = $this->plugin->submit_application(
            $this->reload_instance(),
            $second->id,
            (object) ['applydescription' => 'Closed']
        );

        $this->assertTrue($refused->is_refusal());
        $this->assertNotSame('', $refused->reason());
        $this->assertFalse($DB->record_exists('user_enrolments', [
            'userid' => $second->id,
            'enrolid' => $this->instance->id,
        ]));
        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['userid' => $second->id]));
    }

    /**
     * Applying again after a cancellation is allowed, and produces a second record.
     *
     * Another reason the natural key is not unique: the first record is a cancelled application
     * that must not be overwritten by the second attempt.
     *
     * @return void
     */
    public function test_re_applying_after_a_cancellation_adds_a_second_record(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_for($applicant);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        $sink = $this->redirectMessages();
        $this->plugin->cancel_enrolment([$ueid]);
        $this->apply_for($applicant);
        $sink->close();

        $rows = $DB->get_records('enrol_apply_submission', [
            'courseid' => $this->course->id,
            'userid' => $applicant->id,
        ], 'id ASC');
        $this->assertCount(2, $rows);

        $statuses = array_map(static function (\stdClass $row): int {
            return (int) $row->status;
        }, array_values($rows));
        $this->assertSame(
            [\enrol_apply\local\submission::STATUS_CANCELLED, \enrol_apply\local\submission::STATUS_PENDING],
            $statuses
        );
    }

    /**
     * An approval made outside the plugin's own queue stamps the record too.
     *
     * complete_approval() is reached from core's "Edit enrolment" screen through the
     * before_user_enrolment_updated hook, and never touches confirm_enrolment(). Stamping the
     * decision there rather than in confirm_enrolment() is what makes the two routes agree.
     *
     * @return void
     */
    public function test_an_out_of_band_approval_stamps_the_submission_row(): void {
        global $DB;

        $approver = $this->getDataGenerator()->create_user();
        $this->setUser($approver);

        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_for($applicant);

        $this->plugin->update_user_enrol($this->instance, $applicant->id, ENROL_USER_ACTIVE);

        $row = $this->submission_of($applicant);
        $this->assertEquals(\enrol_apply\local\submission::STATUS_APPROVED, (int) $row->status);
        $this->assertEquals($approver->id, (int) $row->decidedby);
        $this->assertFalse($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $row->userenrolmentid]));
    }

    /**
     * Editing an already-approved enrolment does not run the approval work a second time.
     *
     * hook_callbacks treats the existence of an enrol_apply_applicationinfo row as proof that a
     * move to active is an approval, and complete_approval() deletes that row. That is why the
     * table cannot hold the durable record: were the row kept, every later status edit of an
     * approved user would re-add their groups and queue another notification.
     *
     * @return void
     */
    public function test_the_out_of_band_approval_path_still_short_circuits(): void {
        global $DB;

        $this->setAdminUser();

        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $DB->insert_record('enrol_apply_groups', (object) [
            'enrolid' => $this->instance->id,
            'groupid' => $group->id,
        ]);

        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_for($applicant);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid]);
        $sink->close();

        /* The control for the whole test: the first approval really did do the work, so a
           later assertion that it did not happen again is about the short circuit and not
           about the approval never having run. */
        $notify = ['classname' => '\enrol_apply\task\notify_approval'];
        $this->assertCount(1, $DB->get_records('task_adhoc', $notify));
        $this->assertTrue(groups_is_member($group->id, $applicant->id));

        $DB->delete_records('task_adhoc', $notify);
        groups_remove_member($group->id, $applicant->id);

        // Suspend and re-activate: a second status edit of an already-approved enrolment.
        $this->plugin->update_user_enrol($this->instance, $applicant->id, ENROL_USER_SUSPENDED);
        $this->plugin->update_user_enrol($this->instance, $applicant->id, ENROL_USER_ACTIVE);

        $this->assertCount(0, $DB->get_records('task_adhoc', $notify));
        $this->assertFalse(groups_is_member($group->id, $applicant->id));
    }
}
