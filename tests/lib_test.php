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
        set_config('expiredaction', ENROL_EXT_REMOVED_UNENROL, 'enrol_apply');
        $DB->set_field('enrol', 'enrolperiod', DAYSECS, ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $applicant = $this->getDataGenerator()->create_user();
        $this->setUser($applicant);
        $this->apply_as_current_user($applicant);
        $this->setAdminUser();

        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $this->assertEquals(0, (int) $DB->get_field('user_enrolments', 'timeend', ['id' => $ueid]));

        // Wind the clock past any plausible period and run the sweep the scheduled task runs.
        $DB->set_field('user_enrolments', 'timestart', time() - (30 * DAYSECS), ['id' => $ueid]);
        $this->plugin->process_expirations(new \null_progress_trace());

        $this->assertTrue($DB->record_exists('user_enrolments', ['id' => $ueid]));
        $this->assertEquals(ENROL_USER_SUSPENDED, (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid]));
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
