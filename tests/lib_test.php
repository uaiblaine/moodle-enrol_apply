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
        /* The plugin caches its own config on the instance (enrol_plugin::get_config
           reads $this->config), so the global set_config() would leave this object
           believing the action is still "keep" and the sweep would do nothing at all —
           a test that passes without exercising anything. */
        $this->plugin->set_config('expiredaction', ENROL_EXT_REMOVED_UNENROL);

        // The control: an approved enrolment whose period has run out. The sweep must eat it.
        [, $controlueid] = $this->create_application();
        $this->plugin->confirm_enrolment([$controlueid]);
        $DB->set_field('user_enrolments', 'timeend', time() - DAYSECS, ['id' => $controlueid]);

        // The subject: a pending application, which carries no period by construction.
        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_as_current_user($applicant);
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
        global $CFG;

        // Not autoloaded: the table classes live in plain files, not under classes/.
        require_once($CFG->dirroot . '/enrol/apply/manage_table.php');

        $table = new \enrol_apply_manage_table($this->instance->id);
        $table->define_baseurl(new \moodle_url('/enrol/apply/manage.php'));

        ob_start();
        $table->out(50, false);
        ob_end_clean();

        return array_map(static fn($row) => (int) $row->userid, array_values($table->rawdata));
    }

    /**
     * Submit an application through the plugin itself, exercising the real code path.
     *
     * enrol_page_hook() cannot be driven from a unit test because it needs a submitted
     * moodleform, so the protected worker it delegates to is invoked directly.
     *
     * @param \stdClass $applicant User submitting the application.
     * @return void
     */
    protected function apply_as_current_user(\stdClass $applicant): void {
        $sink = $this->redirectMessages();

        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);
        $method->invoke($this->plugin, $this->instance, $applicant->id, (object) ['applydescription' => '']);

        $sink->close();
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
     * Mutation check: removing the check_privileges() gate in confirm_enrolment()
     * must turn exactly this test red.
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
     * A window that is currently open admits, which no other test proves.
     *
     * The two refusal tests above use "no window configured" as their control, and that is
     * not the same statement: it leaves the comparison against the current time unpinned.
     * Measured on 2026-08-21, a mutant reading "if ($startdate > 0)" - refusing every
     * instance that carries an opening date, whether or not it has arrived - passed the
     * whole suite green.
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

        /* The refusal must not NAME the cohort. It is rendered to any authenticated
           non-member by enrol_page_hook(), so naming it would tell a stranger which
           group the course belongs to - on this platform, which security force. The
           assertion is on the cohort's own name rather than on the message, so it
           keeps holding if the wording is ever rewritten.

           Both halves are needed and neither is redundant: the language string carries
           no {$a} placeholder, and allow_apply() passes no argument. Restoring either
           one alone leaves the name unreachable - this assertion is what fails if both
           come back. */
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
     * null would paint an empty red box.
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
        /* _process_submission() reads the sesskey through optional_param() rather than from
           the data it is handed, and Moodle's PHPUnit harness does not reset $_POST between
           tests — so this is put back afterwards, or a later test inherits a live sesskey it
           never set and can pass without exercising its own guard. */
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
               from it, and discards the whole submission when confirm_sesskey() fails. */
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
     * The custom half of the notification used to be read back out of {user_info_data}
     * through profile_load_custom_fields(), while the standard half came from the submitted
     * form - so the two halves of the same message disagreed, and the half that mattered
     * most showed the approver a value the applicant had not entered.
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

        $sink = $this->redirectMessages();
        $this->invoke_apply($instance, $applicant->id, (object) [
            'applydescription' => 'Please let me in',
            'city' => 'TypedCity',
            'profile_field_typedfield' => 'TypedAnswer',
        ]);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertNotEmpty($messages, 'the approver should have been notified');
        $body = $messages[0]->fullmessagehtml;

        $this->assertStringContainsString('TypedAnswer', $body);
        $this->assertStringNotContainsString('StoredAnswer', $body);
        $this->assertStringContainsString('TypedCity', $body);
        $this->assertStringNotContainsString('StoredCity', $body);
    }

    /**
     * A raw angle bracket in a submitted value does not break the notification.
     *
     * A custom field of the textarea datatype is PARAM_RAW, and core's own comment on it is
     * "We MUST clean this before display!". A value holding a bare "<" followed by a
     * non-space renders as markup in an HTML mail body and kills clean_returnvalue() on any
     * web service hop.
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

        $sink = $this->redirectMessages();
        $this->invoke_apply($instance, $applicant->id, (object) [
            'applydescription' => '',
            'profile_field_rawfield' => 'A<B and R&D',
        ]);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertNotEmpty($messages);
        $body = $messages[0]->fullmessagehtml;

        /* The whole answer arrives, escaped exactly once. Asserting only that "A<B" is
           absent would pass against a body that had silently truncated the value to "A",
           which is precisely the defect this test exists to catch. */
        $this->assertStringContainsString('A&lt;B and R&amp;D', $body);
        $this->assertStringNotContainsString('A&lt;B and R&amp;amp;D', $body);
        // And the bare bracket is not sitting in the body as the start of a tag.
        $this->assertStringNotContainsString('A<B', $body);
    }

    /**
     * Submit an application the way enrol_page_hook() does.
     *
     * apply() is protected, and it is called through reflection rather than by widening it:
     * the production API should not grow a public method because a test found it convenient,
     * and the form class that will call it from outside arrives with a later slice.
     *
     * @param \stdClass $instance Enrol instance applied to.
     * @param int $userid Applicant.
     * @param \stdClass $data Submitted form data.
     * @return void
     */
    protected function invoke_apply(\stdClass $instance, int $userid, \stdClass $data): void {
        $method = new \ReflectionMethod($this->plugin, 'apply');
        $method->setAccessible(true);
        $method->invoke($this->plugin, $instance, $userid, $data);
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
        $this->apply_as_current_user($applicant);

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
        $this->apply_as_current_user($applicant);
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
        $this->apply_as_current_user($applicant);
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
        $this->apply_as_current_user($applicant);
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
        $this->apply_as_current_user($applicant);
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
     * This inverts what delete_instance() used to do to the plugin's data as a whole, so the
     * controls matter more than the subject: the two tables that ARE instance configuration
     * must still be cleaned up, or "the trail survived" would just mean "nothing is deleted".
     *
     * @return void
     */
    public function test_delete_instance_keeps_the_submission_rows(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_as_current_user($applicant);
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
     * complete_approval() runs TWICE for every approval made through the plugin's own queue -
     * once from the before_user_enrolment_updated hook, once from confirm_enrolment() - and
     * decide() is called each time. Without the already-at-this-status check, timedecided
     * would record the last write rather than the moment of the decision, and a record could
     * be re-attributed to whoever happened to touch the enrolment next.
     *
     * @return void
     */
    public function test_a_recorded_decision_is_not_restamped(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_as_current_user($applicant);
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
        $this->apply_as_current_user($applicant);

        $this->expectException(\coding_exception::class);
        \enrol_apply\local\submission::decide(1, 99, 1);
    }

    /**
     * A second application through the same enrolment method is refused before it reaches apply().
     *
     * The plan for this slice specified a UNIQUE (courseid, userid) key. That key is
     * incompatible with pseudonymising a deleted course by zeroing userid - and what replaces
     * it is narrower than the key would have been, which is worth being precise about. The
     * lock in submit_application() is keyed on the INSTANCE and the user, and the check behind
     * it looks up user_enrolments by enrolid, so what is enforced is one live application per
     * enrolment method. A course carrying two apply instances lets the same person hold two,
     * which is a further reason the key could never have been unique.
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

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertCount(1, $DB->get_records('enrol_apply_submission', ['userid' => $applicant->id]));
    }

    /**
     * Applying again after a cancellation is allowed, and produces a second record.
     *
     * The other half of the reason the natural key is not unique: the first record is a
     * cancelled application that must not be overwritten by the second attempt.
     *
     * @return void
     */
    public function test_re_applying_after_a_cancellation_adds_a_second_record(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $this->apply_as_current_user($applicant);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        $sink = $this->redirectMessages();
        $this->plugin->cancel_enrolment([$ueid]);
        $this->apply_as_current_user($applicant);
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
        $this->apply_as_current_user($applicant);

        $this->plugin->update_user_enrol($this->instance, $applicant->id, ENROL_USER_ACTIVE);

        $row = $this->submission_of($applicant);
        $this->assertEquals(\enrol_apply\local\submission::STATUS_APPROVED, (int) $row->status);
        $this->assertEquals($approver->id, (int) $row->decidedby);
        $this->assertFalse($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $row->userenrolmentid]));
    }

    /**
     * Editing an already-approved enrolment does not run the approval work a second time.
     *
     * This is the guard the untouched enrol_apply_applicationinfo row provides, and the
     * reason that table is not repurposed to hold the durable record: hook_callbacks uses its
     * EXISTENCE as proof that a status change to active was an approval. Were the row to stop
     * being deleted, every later status edit of an approved user would re-add their groups
     * and queue another confirmation message.
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
        $this->apply_as_current_user($applicant);
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
