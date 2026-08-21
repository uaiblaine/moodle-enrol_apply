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

/**
 * Tests for the enrol_apply plugin application state machine.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_apply_plugin
 */
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
        $this->plugin->enrol_user($this->instance, $user->id, $this->instance->roleid, 0, 0, ENROL_USER_SUSPENDED);
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
        $this->assertSame(get_string('cohortnonmemberinfo', 'enrol_apply', 'Servidores 2026'), $refusal);
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
}
