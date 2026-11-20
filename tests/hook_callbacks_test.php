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
 * Tests for what course deletion does to the durable application trail.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use enrol_apply\local\approvalwrite;
use enrol_apply\local\keyattempts;
use enrol_apply\local\queue;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the hook callbacks: what course deletion does to the durable application trail, and
 * what core's "Edit enrolment" route may do to an application.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hook_callbacks::class)]
#[CoversClass(observers::class)]
#[CoversClass(approvalwrite::class)]
#[CoversClass(queue::class)]
#[CoversClass(\enrol_apply_plugin::class)]
#[CoversClass(\enrol_apply\table\applications::class)]
final class hook_callbacks_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin under test. */
    protected $plugin;

    /**
     * Enable the plugin.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Ids repeat between tests, and an expectation an event sink kept from its observer is static.
        approvalwrite::reset();
        $this->setAdminUser();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->plugin = enrol_get_plugin('apply');
    }

    /**
     * Release the key throttle's locks and forget any expected approval.
     *
     * @return void
     */
    protected function tearDown(): void {
        keyattempts::release_all();
        approvalwrite::reset();
        parent::tearDown();
    }

    /**
     * Create a course with an apply instance, one configured group and one live application.
     *
     * The applicant submits two profile fields, the city and the institution, and the instance
     * asks for the whole default pool unless told otherwise. The method gives the student role. The field set is written before
     * apply() runs, because apply() is what freezes the snapshot: set afterwards it would change
     * nothing about this application.
     *
     * @param string $comment Comment submitted with the application.
     * @param array|null $fieldkeys Field keys the instance asks for, null for the default pool.
     * @return array Course record, instance record, applicant record and user enrolment id.
     */
    protected function create_course_with_application(string $comment = 'Please let me in', ?array $fieldkeys = null): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        // A role to give, so an approval is seen in role_assignments too.
        $student = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $instanceid = $this->plugin->add_instance($course, ['roleid' => $student] + $this->plugin->get_instance_defaults());
        if ($fieldkeys !== null) {
            $DB->set_field('enrol', 'customtext4', local\fieldset::from_keys($fieldkeys)->to_json(), ['id' => $instanceid]);
        }
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $instanceid, 'groupid' => $group->id]);

        $applicant = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);
        $method->invoke($this->plugin, $instance, $applicant->id, (object) [
            'applydescription' => $comment,
            'city' => 'Recife',
            'institution' => 'Federal University',
        ]);
        $sink->close();

        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['enrolid' => $instanceid, 'userid' => $applicant->id],
            MUST_EXIST
        );

        return [$course, $instance, $applicant, $ueid];
    }

    /**
     * Deleting a course strips its trail of everything personal and keeps the rest.
     *
     * @return void
     */
    public function test_before_course_deleted_pseudonymises_the_rows(): void {
        global $DB;

        // Ask for a profile field, so the snapshot is not empty and its discarding is testable.
        [$course, , $applicant, $ueid] = $this->create_course_with_application('Let me in please', ['s_city']);

        /* Decide it first. An undecided row already has decidedby = 0, so asserting 0 after
           the deletion would hold with the pseudonymisation removed. */
        $decider = $this->getDataGenerator()->create_user();
        // The decider needs the capability: can_manage_application() authorises every row.
        $this->getDataGenerator()->enrol_user(
            $decider->id,
            $course->id,
            $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST)
        );
        $this->setUser($decider);
        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid]);
        $sink->close();
        $this->setAdminUser();

        /* The control: an application in a different course, which the deletion must not
           touch. Without it a callback that emptied the whole table would pass. */
        [$othercourse, , $otherapplicant] = $this->create_course_with_application('Another course', ['s_city']);

        $before = $DB->get_record('enrol_apply_submission', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertNotEmpty($before->comment);
        // The preconditions the assertions after the deletion are only meaningful against.
        $this->assertEquals($applicant->id, (int) $before->userid);
        $this->assertEquals($decider->id, (int) $before->decidedby);
        /* Exactly the field the instance asks for, although the applicant submitted two: the
           snapshot was taken against the configured set, not the default pool. */
        $this->assertSame(['s_city'], array_column(submission::read_snapshot((int) $before->id), 'key'));
        $otherbefore = (int) $DB->get_field('enrol_apply_submission', 'id', ['courseid' => $othercourse->id], MUST_EXIST);
        $this->assertNotEmpty(submission::read_snapshot($otherbefore), 'the control: the other course has answers to keep');

        delete_course($course, false);

        $after = $DB->get_record('enrol_apply_submission', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertEquals(0, (int) $after->userid);
        $this->assertEquals(0, (int) $after->decidedby);
        $this->assertSame('', (string) $after->comment);
        // Every frozen answer goes: each is something the applicant typed.
        $this->assertSame(0, $DB->count_records(submission::ANSWER_TABLE, ['submissionid' => $after->id]));

        // What an audit keeps: the shape of the application, which identifies nobody.
        $this->assertEquals($before->status, $after->status);
        $this->assertEquals($before->timecreated, $after->timecreated);
        $this->assertEquals($before->enrolid, $after->enrolid);

        $untouched = $DB->get_record('enrol_apply_submission', ['courseid' => $othercourse->id], '*', MUST_EXIST);
        $this->assertEquals($otherapplicant->id, (int) $untouched->userid);
        $this->assertSame('Another course', (string) $untouched->comment);
        $this->assertNotEmpty(submission::read_snapshot((int) $untouched->id));
    }

    /**
     * The snapshot captures the submitted value of each field the instance asks for.
     *
     * Complements the precondition in the test above by checking the value as well as the key,
     * so the value that pseudonymisation discards is known to have been captured.
     *
     * @return void
     */
    public function test_the_snapshot_is_captured_before_it_is_discarded(): void {
        global $DB;

        [, , $applicant] = $this->create_course_with_application('With a city', ['s_city']);

        $row = $DB->get_record('enrol_apply_submission', ['userid' => $applicant->id], '*', MUST_EXIST);
        $snapshot = submission::read_snapshot((int) $row->id);
        $this->assertCount(1, $snapshot);
        $this->assertSame('s_city', $snapshot[0]['key']);
        $this->assertSame('Recife', $snapshot[0]['value']);
    }

    /**
     * Two applicants in one deleted course pseudonymise to two rows, not to a key violation.
     *
     * Pseudonymisation zeroes userid, so both rows end up with the same (courseid, userid): this
     * is why that index is not unique, and making it unique must make this test fail.
     *
     * @return void
     */
    public function test_a_deleted_course_with_two_applicants_keeps_both_rows(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application('First');

        $second = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);
        $method->invoke($this->plugin, $instance, $second->id, (object) ['applydescription' => 'Second']);
        $sink->close();

        $this->assertCount(2, $DB->get_records('enrol_apply_submission', ['courseid' => $course->id]));

        delete_course($course, false);

        $rows = $DB->get_records('enrol_apply_submission', ['courseid' => $course->id]);
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertEquals(0, (int) $row->userid);
        }
    }

    /**
     * With the plugin disabled, the observer clears up what core orphaned.
     *
     * enrol_course_delete() never calls a disabled plugin's delete_instance() but deletes the
     * enrol and user_enrolments rows anyway; see {@see \enrol_apply\observers::course_deleted()}.
     *
     * @return void
     */
    public function test_the_course_deleted_observer_cleans_up_when_the_plugin_is_disabled(): void {
        global $DB;

        [$course, $instance, , $ueid] = $this->create_course_with_application();

        /* The control: the observer sweeps orphans rather than rows of one course, so without
           a live application elsewhere a callback that emptied both tables would pass. */
        [, $otherinstance, , $otherueid] = $this->create_course_with_application();

        $enabled = enrol_get_plugins(true);
        unset($enabled['apply']);
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        delete_course($course, false);

        // The precondition: core really did skip the plugin and delete the rows itself.
        $this->assertFalse($DB->record_exists('enrol', ['id' => $instance->id]));
        $this->assertFalse($DB->record_exists('user_enrolments', ['id' => $ueid]));

        $this->assertFalse($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $ueid]));
        $this->assertFalse($DB->record_exists('enrol_apply_groups', ['enrolid' => $instance->id]));

        $this->assertTrue($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $otherueid]));
        $this->assertTrue($DB->record_exists('enrol_apply_groups', ['enrolid' => $otherinstance->id]));
    }

    /**
     * The pseudonymisation comes from the hook, not from the course_deleted event.
     *
     * Both fire inside delete_course() and leave the row looking the same, so every other test
     * here would pass with the call moved to the observer. With the hook silenced, a deletion
     * that still pseudonymises is doing the work from the event, after the course context is
     * gone. See {@see \enrol_apply\hook_callbacks::before_course_deleted()} for why that is too
     * late.
     *
     * @return void
     */
    public function test_the_pseudonymisation_runs_from_the_hook_and_not_from_the_event(): void {
        global $DB;

        [$course, , $applicant] = $this->create_course_with_application('Still named');

        $this->redirectHook(\core_course\hook\before_course_deleted::class, static function (): void {
            // Deliberately nothing: the only route left is the course_deleted event.
        });
        delete_course($course, false);
        $this->stopHookRedirections();

        $row = $DB->get_record('enrol_apply_submission', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertEquals(
            $applicant->id,
            (int) $row->userid,
            'with the hook silenced nothing may pseudonymise the row, or the work is being done too late'
        );
        $this->assertSame('Still named', (string) $row->comment);
    }

    /**
     * The trail of a course deleted while the plugin is disabled is pseudonymised, not swept.
     *
     * Hook callbacks are loaded from every installed plugin whatever its state, so the
     * pseudonymisation runs in both cases; the observer deliberately leaves the trail alone.
     *
     * @return void
     */
    public function test_the_trail_is_pseudonymised_even_while_the_plugin_is_disabled(): void {
        global $DB;

        [$course] = $this->create_course_with_application();

        $enabled = enrol_get_plugins(true);
        unset($enabled['apply']);
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        delete_course($course, false);

        $row = $DB->get_record('enrol_apply_submission', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertEquals(0, (int) $row->userid);
        $this->assertSame('', (string) $row->comment);
    }

    /**
     * Write a pending application of a given user through the plugin's own apply().
     *
     * @param \stdClass $instance The apply instance.
     * @param \stdClass $user The applicant.
     * @param int $keygroupid The key's group, 0 for an application without a key.
     * @return int The user enrolment id.
     */
    private function apply_as(\stdClass $instance, \stdClass $user, int $keygroupid = 0): int {
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $written = $method->invoke(
            $this->plugin,
            $instance,
            (int) $user->id,
            (object) ['applydescription' => 'Please'],
            $keygroupid,
            $keygroupid ? submission::KEY_FOR_REVIEW : submission::KEY_NONE
        );
        $sink->close();

        return (int) $written['userenrolmentid'];
    }

    /**
     * A course enrolment administrator: a role holding enrol/apply:manage and nothing that decides.
     *
     * @param \stdClass $course The course to enrol them in.
     * @return \stdClass The user.
     */
    private function enrolment_administrator(\stdClass $course): \stdClass {
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applyenroladmin']);
        set_role_contextlevels($roleid, [CONTEXT_COURSE]);
        assign_capability('enrol/apply:manage', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $roleid);

        // The precondition: core's Edit enrolment admits them, and they decide nothing.
        $context = \context_course::instance($course->id);
        $this->assertTrue(has_capability('enrol/apply:manage', $context, $user));
        $this->assertFalse(has_capability('enrol/apply:manageapplications', $context, $user));

        return $user;
    }

    /**
     * What an application holds of an approval: its status, its role, its groups and its row.
     *
     * @param int $ueid User enrolment id.
     * @param \stdClass $applicant The applicant.
     * @return array Status, whether a role and a membership stamped by the plugin exist, and whether
     *         the application row is still there.
     */
    private function approval_state(int $ueid, \stdClass $applicant): array {
        global $DB;

        return [
            (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid], MUST_EXIST),
            $DB->record_exists('role_assignments', ['userid' => $applicant->id, 'component' => 'enrol_apply']),
            $DB->record_exists('groups_members', ['userid' => $applicant->id, 'component' => 'enrol_apply']),
            $DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $ueid]),
        ];
    }

    /**
     * A role holding enrol/apply:manage and not the deciding capability may not approve an
     * application on core's Edit enrolment, in a course without groups; an editing teacher may.
     *
     * Changes that must make it fail: the group check standing in for the deciding capability in
     * refuses_approval().
     *
     * @return void
     */
    public function test_a_manage_only_role_is_refused_approving_an_application(): void {
        [$course, $instance, $applicant, $ueid] = $this->create_course_with_application();
        $this->setUser($this->enrolment_administrator($course));

        try {
            $this->plugin->update_user_enrol($instance, $applicant->id, ENROL_USER_ACTIVE);
            $this->fail('the approval went through');
        } catch (\moodle_exception $e) {
            $this->assertSame('editenrolmentrefused', $e->errorcode);
        }
        $this->assertSame([ENROL_USER_SUSPENDED, false, false, true], $this->approval_state($ueid, $applicant));

        // The control: a decider takes the same route, and the approval is completed.
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $sink = $this->redirectMessages();
        $this->plugin->update_user_enrol($instance, $applicant->id, ENROL_USER_ACTIVE);
        $sink->close();
        $this->assertSame([ENROL_USER_ACTIVE, true, true, false], $this->approval_state($ueid, $applicant));
    }

    /**
     * The same role may not give an application an end date, which would take it out of the queue
     * as expired, or into core's expiry sweep.
     *
     * Changes that must make it fail: the hook judging only a move to active.
     *
     * @return void
     */
    public function test_a_manage_only_role_is_refused_dating_an_application(): void {
        global $DB;

        [$course, $instance, $applicant, $ueid] = $this->create_course_with_application();
        $this->setUser($this->enrolment_administrator($course));

        try {
            $this->plugin->update_user_enrol($instance, $applicant->id, null, null, time() - DAYSECS);
            $this->fail('the end date was written');
        } catch (\moodle_exception $e) {
            $this->assertSame('editenrolmentrefused', $e->errorcode);
        }

        $row = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertSame(0, (int) $row->timeend);
        $this->assertTrue(queue::is_awaiting_decision($row));
    }

    /**
     * The same role edits the dates of an active enrolment, which is core's and no decision, and
     * the approval work does not run again.
     *
     * Changes that must make it fail: the hook judging every enrolment of the method; the observer
     * completing an approval the hook never expected.
     *
     * @return void
     */
    public function test_a_manage_only_role_edits_the_dates_of_an_active_enrolment(): void {
        global $DB;

        [$course, $instance, $applicant, $ueid] = $this->create_course_with_application();
        $sink = $this->redirectMessages();
        $this->assertSame(1, $this->plugin->confirm_enrolment([$ueid]));
        $sink->close();
        $notify = ['classname' => '\\enrol_apply\\task\\notify_approval'];
        // The control: the approval queued its notification, so none below means none was queued.
        $this->assertCount(1, $DB->get_records('task_adhoc', $notify));
        $DB->delete_records('task_adhoc', $notify);

        $this->setUser($this->enrolment_administrator($course));
        $timeend = time() + WEEKSECS;
        $this->plugin->update_user_enrol($instance, $applicant->id, null, null, $timeend);

        $this->assertSame($timeend, (int) $DB->get_field('user_enrolments', 'timeend', ['id' => $ueid]));
        $this->assertCount(0, $DB->get_records('task_adhoc', $notify));
    }

    /**
     * A moderator narrowed by separate groups may not reactivate an approved-then-suspended
     * enrolment of another group, which is back in the queue with no application row; a decider
     * of every group may, and that is a second approval, as on the queue.
     *
     * Changes that must make it fail: the hook judging an application by its applicationinfo row
     * rather than by the queue's predicate.
     *
     * @return void
     */
    public function test_a_narrowed_moderator_is_refused_reactivating_a_requeued_application(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS]);
        $instanceid = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        $mine = $generator->create_group(['courseid' => $course->id]);
        $theirs = $generator->create_group(['courseid' => $course->id]);

        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $system = \context_system::instance()->id;
        assign_capability('enrol/apply:manage', CAP_ALLOW, $teacher, $system, true);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $teacher, $system, true);
        $moderator = $generator->create_and_enrol($course, 'teacher');
        $generator->create_group_member(['groupid' => $mine->id, 'userid' => $moderator->id]);

        $applicant = $generator->create_user();
        $ueid = $this->apply_as($instance, $applicant, (int) $theirs->id);

        // Approved, then suspended from the participants page: back in the queue.
        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid]);
        $this->plugin->update_user_enrol($instance, $applicant->id, ENROL_USER_SUSPENDED);
        $sink->close();
        $this->assertTrue(queue::is_awaiting_decision($DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST)));
        $this->assertFalse($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $ueid]));

        $this->setUser($moderator);
        try {
            $this->plugin->update_user_enrol($instance, $applicant->id, ENROL_USER_ACTIVE);
            $this->fail('the moderator reactivated another group\'s application');
        } catch (\moodle_exception $e) {
            $this->assertSame('editenrolmentrefused', $e->errorcode);
        }
        $this->assertSame(ENROL_USER_SUSPENDED, (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid]));

        // The control: who sees every group reactivates it, and that approves it again.
        $notify = ['classname' => '\\enrol_apply\\task\\notify_approval'];
        $DB->delete_records('task_adhoc', $notify);
        $this->setUser($generator->create_and_enrol($course, 'editingteacher'));
        $this->plugin->update_user_enrol($instance, $applicant->id, ENROL_USER_ACTIVE);
        $this->assertSame(ENROL_USER_ACTIVE, (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid]));
        $this->assertCount(1, $DB->get_records('task_adhoc', $notify));
    }

    /**
     * A group key admits an applicant who holds enrol/apply:manage in the course: the plugin's own
     * activation is not a decision the hook judges, so the self-decision refusal does not reach it.
     *
     * Changes that must make it fail: activate() writing outside approvalwrite::own(), or the hook
     * judging a write made inside it.
     *
     * @return void
     */
    public function test_a_key_admission_admits_an_applicant_who_holds_manage(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instanceid = $this->plugin->add_instance(
            $course,
            ['customchar1' => local\groupkey::MODE_ANY] + $this->plugin->get_instance_defaults()
        );
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        $generator->create_group(['courseid' => $course->id, 'enrolmentkey' => 'Turma-B-2027']);

        // A teacher of the course, enrolled by hand, applying to it through a key.
        $applicant = $generator->create_and_enrol($course, 'editingteacher');
        $this->assertTrue(has_capability('enrol/apply:manage', \context_course::instance($course->id), $applicant));
        $this->setUser($applicant);

        $sink = $this->redirectMessages();
        $result = $this->plugin->submit_application($instance, (int) $applicant->id, (object) [
            'applydescription' => '',
            'groupkey' => 'Turma-B-2027',
        ]);
        $sink->close();

        $this->assertTrue($result->was_admitted());
        $this->assertSame(
            ENROL_USER_ACTIVE,
            (int) $DB->get_field('user_enrolments', 'status', ['enrolid' => $instanceid, 'userid' => $applicant->id])
        );
    }

    /**
     * A listener of before_user_enrolment_updated that throws after this plugin's leaves no role,
     * no group and the application in place: the hook authorised the approval and wrote nothing.
     *
     * The control is the expectation the plugin's callback left: it ran, and authorised the approval.
     *
     * Changes that must make it fail: the approval completed from the hook, before core's write.
     *
     * @return void
     */
    public function test_a_listener_throwing_after_the_plugin_leaves_no_role_and_no_group(): void {
        global $DB;

        [, $instance, $applicant, $ueid] = $this->create_course_with_application();

        $this->redirectHook(
            \core_enrol\hook\before_user_enrolment_updated::class,
            static function (\core_enrol\hook\before_user_enrolment_updated $hook): void {
                hook_callbacks::before_user_enrolment_updated($hook);
                throw new \moodle_exception('error');
            }
        );
        try {
            $this->plugin->update_user_enrol($instance, $applicant->id, ENROL_USER_ACTIVE);
            $this->fail('the listener did not throw');
        } catch (\moodle_exception $e) {
            $this->assertSame('error', $e->errorcode);
        } finally {
            $this->stopHookRedirections();
        }

        $this->assertTrue(approvalwrite::is_expected($ueid), 'the plugin\'s callback did not authorise the approval');
        $this->assertSame([ENROL_USER_SUSPENDED, false, false, true], $this->approval_state($ueid, $applicant));
        $record = $DB->get_record('enrol_apply_submission', ['userenrolmentid' => $ueid], '*', MUST_EXIST);
        $this->assertSame(submission::STATUS_PENDING, (int) $record->status);
    }

    /**
     * Nobody decides their own application, on any of the three routes, but a site administrator.
     *
     * The control is the same teacher deciding somebody else's application.
     *
     * Changes that must make it fail: the self-decision refusal dropped from can_manage_application(),
     * or withheld from a site administrator too.
     *
     * @return void
     */
    public function test_nobody_but_a_site_administrator_decides_their_own_application(): void {
        global $CFG, $DB, $PAGE;
        require_once($CFG->dirroot . '/enrol/locallib.php');

        [$course, $instance, $other, $otherueid] = $this->create_course_with_application();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $ownueid = $this->apply_as($instance, $teacher);
        $courseid = (int) $course->id;
        $this->setUser($teacher);

        $this->assertTrue($this->plugin->can_manage_application($courseid, (int) $other->id, $otherueid), 'the control');
        $this->assertFalse($this->plugin->can_manage_application($courseid, (int) $teacher->id, $ownueid));

        // The queue and the review page.
        $this->assertSame(0, $this->plugin->confirm_enrolment([$ownueid]));

        // The participants page's bulk confirmation.
        $PAGE->set_url('/user/index.php', ['id' => $courseid]);
        $manager = new \course_enrolment_manager($PAGE, $course, $instance->id);
        $users = $manager->get_users_enrolments([$teacher->id]);
        $this->assertCount(1, $users);
        $operation = new \enrol_apply\bulk\confirm_operation($manager, $this->plugin);
        $operation->process($manager, $users, (object) ['outcomemessage' => '']);

        // Core's Edit enrolment.
        try {
            $this->plugin->update_user_enrol($instance, $teacher->id, ENROL_USER_ACTIVE);
            $this->fail('the teacher approved their own application');
        } catch (\moodle_exception $e) {
            $this->assertSame('editenrolmentrefused', $e->errorcode);
        }
        $this->assertSame(ENROL_USER_SUSPENDED, (int) $DB->get_field('user_enrolments', 'status', ['id' => $ownueid]));

        $sink = $this->redirectMessages();
        $this->assertSame(1, $this->plugin->confirm_enrolment([$otherueid]), 'the control: somebody else\'s');
        $sink->close();

        // A site administrator decides their own.
        $adminueid = $this->apply_as($instance, get_admin());
        $this->setAdminUser();
        $sink = $this->redirectMessages();
        $this->assertSame(1, $this->plugin->confirm_enrolment([$adminueid]));
        $sink->close();
    }

    /**
     * The queue offers no selection on the reader's own application and says why; the review page
     * offers no decision form for it and says why.
     *
     * The control is the other applicant's row, which keeps its box.
     *
     * Changes that must make it fail: the selection box drawn on the reader's own row.
     *
     * @return void
     */
    public function test_the_queue_and_the_review_page_say_why_the_own_application_is_not_decided(): void {
        global $PAGE;

        [$course, $instance, , $otherueid] = $this->create_course_with_application();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $ownueid = $this->apply_as($instance, $teacher);
        $this->setUser($teacher);

        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $instance->id]);
        $PAGE->set_url($url);
        $table = \enrol_apply\table\applications::for_scope((int) $instance->id);
        ob_start();
        $table->out(50, false);
        $html = ob_get_clean();

        $this->assertEqualsCanonicalizing(
            [$otherueid, $ownueid],
            array_map(static fn($row) => (int) $row->userenrolmentid, array_values($table->rawdata))
        );
        $this->assertStringContainsString('enrol_apply_ue_' . $otherueid . '"', $html);
        $this->assertStringNotContainsString('enrol_apply_ue_' . $ownueid . '"', $html);
        $this->assertStringContainsString(s(get_string('ownapplicationnotdecidable', 'enrol_apply')), $html);

        $application = queue::application($ownueid);
        $reviewurl = new \moodle_url('/enrol/apply/manage.php', ['userenrol' => $ownueid]);
        $PAGE->set_url($reviewurl);
        $PAGE->set_context(\context_course::instance($course->id));
        $review = $PAGE->get_renderer('enrol_apply')->review_form($application, $teacher, $instance, $reviewurl);
        $this->assertStringContainsString(s(get_string('reviewownapplication', 'enrol_apply')), $review);
        $this->assertStringNotContainsString('name="formaction"', $review);
    }
}
