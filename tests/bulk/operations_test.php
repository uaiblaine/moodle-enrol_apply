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

namespace enrol_apply\bulk;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

/**
 * The participants-page bulk decisions.
 *
 * Core has no PHPUnit coverage for this extension point at all - its only test is Behat driving
 * the participants page end to end - so every one of these builds the users array the way core's
 * driver does, through a real course_enrolment_manager, rather than hand-rolling the shape.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(decision_operation::class)]
#[CoversClass(confirm_operation::class)]
#[CoversClass(wait_operation::class)]
#[CoversClass(cancel_operation::class)]
final class operations_test extends \advanced_testcase {
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
     * A second apply instance on the same course, which is the shape U4 exists for.
     *
     * Two apply methods in one course is supported on purpose - they are two intakes - and it is
     * the configuration in which core's dispatch quietly reaches only one of them.
     *
     * @param bool $enabled Whether the new instance is enabled.
     * @param int|null $sortorder Where it sits in the course's own ordering; core's dispatch takes
     *        the first apply row by sortorder, disabled or not.
     * @return \stdClass The new enrol instance record.
     */
    protected function second_instance(bool $enabled = true, ?int $sortorder = null): \stdClass {
        global $DB;

        $id = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        if (!$enabled) {
            $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $id]);
        }
        if ($sortorder !== null) {
            $DB->set_field('enrol', 'sortorder', $sortorder, ['id' => $id]);
        }

        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The participants page's own manager: the whole course, with no instance filter.
     *
     * The helper above always filters, because that is what core's DISPATCH does. This is the
     * other caller - user/index.php - and the two must be told apart, because the menu memo
     * applies to one of them and would break the other.
     *
     * @return \course_enrolment_manager The unfiltered manager.
     */
    protected function unfiltered_manager(): \course_enrolment_manager {
        global $PAGE;

        $PAGE->set_url('/user/index.php', ['id' => $this->course->id]);

        return new \course_enrolment_manager($PAGE, $this->course);
    }

    /**
     * A fresh user with a real application on the apply instance, left in the given state.
     *
     * Applications are submitted through the plugin itself rather than by enrolling the user
     * by hand, because a hand-made enrolment leaves no enrol_apply_submission row - and the
     * decision's own data, the outcome message and the chosen groups among it, is written on
     * that row. A test taking the short cut asserts against a table that is simply empty.
     *
     * @param int $status One of ENROL_USER_SUSPENDED and ENROL_APPLY_USER_WAIT, or
     *                    ENROL_USER_ACTIVE for an application that has already been approved.
     * @param \stdClass|null $instance Instance to apply to, this course's by default.
     * @return \stdClass The user record.
     */
    protected function applicant(int $status = ENROL_USER_SUSPENDED, ?\stdClass $instance = null): \stdClass {
        global $DB;

        $instance = $instance ?? $this->instance;
        $user = $this->getDataGenerator()->create_user();

        /* enrol_page_hook() needs a submitted moodleform, so the worker it delegates to is
           invoked directly - the same route tests/lib_test.php takes. */
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);
        $method->invoke($this->plugin, $instance, $user->id, (object) ['applydescription' => '']);
        $sink->close();

        if ($status !== ENROL_USER_SUSPENDED) {
            $DB->set_field(
                'user_enrolments',
                'status',
                $status,
                ['userid' => $user->id, 'enrolid' => $instance->id]
            );
        }

        return $user;
    }

    /**
     * A user whose application was approved and whose enrolment has since expired.
     *
     * Reproduces exactly what process_expirations() leaves behind under an expiredaction of
     * suspend: status back to suspended, timeend still in the past. That row is the one the
     * plugin's queue excludes with the second half of its predicate, and the one the bulk
     * path must exclude too.
     *
     * @return \stdClass The user record.
     */
    protected function expired_participant(): \stdClass {
        global $DB;

        $user = $this->applicant();
        $ueid = $this->ueid($user);
        $DB->update_record('user_enrolments', (object) [
            'id' => $ueid,
            'status' => ENROL_USER_SUSPENDED,
            'timestart' => time() - DAYSECS * 30,
            'timeend' => time() - DAYSECS,
        ]);

        return $user;
    }

    /**
     * The user enrolment id of a user on this course's apply instance.
     *
     * @param \stdClass $user User to look up.
     * @return int The user_enrolments id.
     */
    protected function ueid(\stdClass $user): int {
        global $DB;

        return (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $user->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
    }

    /**
     * The users array exactly as core's driver assembles it before calling process().
     *
     * user/action_redir.php builds the manager with the first apply instance of the course
     * and then calls get_users_enrolments() on it, so this is the real shape, joins and all.
     *
     * @param array $users Users to select.
     * @param \stdClass|null $instance Enrol instance to filter to, this course's apply one by default.
     * @return array Two-element array of the manager and the users array.
     */
    protected function selection(array $users, ?\stdClass $instance = null): array {
        global $PAGE;

        $instance = $instance ?? $this->instance;
        $course = get_course($instance->courseid);
        $PAGE->set_url('/user/index.php', ['id' => $course->id]);
        $manager = new \course_enrolment_manager($PAGE, $course, $instance->id);

        $ids = array_map(static fn(\stdClass $user): int => (int) $user->id, $users);

        /* get_in_or_equal() refuses an empty array, and core never reaches this with one:
           user/action_redir.php redirects with "No users selected" first. */
        return [$manager, $ids ? $manager->get_users_enrolments($ids) : []];
    }

    /**
     * A course teacher who may decide applications in this course and nowhere else.
     *
     * @param \stdClass|null $course Course to make them a teacher of, this test's by default.
     * @return \stdClass The teacher user record.
     */
    protected function teacher(?\stdClass $course = null): \stdClass {
        $course = $course ?? $this->course;
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        return $teacher;
    }

    /**
     * The notification messages the operation left for the operator, cleared as it reads them.
     *
     * @return array Message strings.
     */
    protected function notifications(): array {
        return array_map(
            static fn($notification): string => $notification->get_message(),
            \core\notification::fetch()
        );
    }

    /**
     * The participants menu is offered once per COURSE, not once per instance.
     *
     * user/index.php loops over the course's enrolment instances and calls get_bulk_operations()
     * for each of them, on the same plugin object, with a manager carrying no instance filter and
     * a url built from the plugin name and the operation alone - so N instances gave N identical
     * optgroups. It reproduces in stock core with enrol_self and no third-party plugin at all.
     *
     * The control is the second half: a FILTERED manager is never suppressed, because that is
     * core's dispatch and suppressing it would break every bulk decision rather than tidy a menu.
     *
     * @return void
     */
    public function test_the_participants_menu_is_offered_once_per_course(): void {
        $this->second_instance();
        $this->setUser($this->teacher());

        $manager = $this->unfiltered_manager();
        $this->assertNotEmpty($this->plugin->get_bulk_operations($manager));
        $this->assertSame([], $this->plugin->get_bulk_operations($manager));

        /* The control, on the same plugin object the memo lives on: the dispatch's own filtered
           manager still gets its operations, and gets them every time. */
        [$filtered] = $this->selection([]);
        $this->assertNotEmpty($this->plugin->get_bulk_operations($filtered));
        $this->assertNotEmpty($this->plugin->get_bulk_operations($filtered));
    }

    /**
     * A menu refused for the capability does not silence the next caller.
     *
     * The memo is set only once every gate above it has passed, and this is what holds that
     * ordering. It had to be written for it: the obvious candidate,
     * test_the_bulk_menu_is_empty_without_the_capability, asks with a FILTERED manager and so
     * never enters the memo branch at all - it holds a different property, that the memo does not
     * ignore the filter, and it cannot hold this one.
     *
     * Not reachable in production today, and that is worth stating rather than implying
     * otherwise: one request asks with one $USER and one context, so the capability answer is
     * constant across user/index.php's loop. The ordering is a property of the code that nothing
     * would notice losing, which is exactly the kind this repository pins.
     *
     * @return void
     */
    public function test_a_menu_refused_for_the_capability_does_not_silence_the_next_one(): void {
        $outsider = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($outsider->id, $this->course->id, 'student');
        $manager = $this->unfiltered_manager();

        $this->setUser($outsider);
        $this->assertSame([], $this->plugin->get_bulk_operations($manager));

        $this->setUser($this->teacher());
        $this->assertNotEmpty($this->plugin->get_bulk_operations($manager));
    }

    /**
     * The memo lives on the plugin OBJECT, so a fresh one offers the menu again.
     *
     * enrol_get_plugins() constructs a new plugin object per call, which is why this must not be
     * a static: a static memo would outlive the manager it belongs to, silence the menu on every
     * later page load of the same request, and leak across tests in one PHPUnit run.
     *
     * @return void
     */
    public function test_a_fresh_plugin_object_offers_the_menu_again(): void {
        $this->setUser($this->teacher());
        $manager = $this->unfiltered_manager();

        $this->assertNotEmpty($this->plugin->get_bulk_operations($manager));
        $this->assertSame([], $this->plugin->get_bulk_operations($manager));

        $this->assertNotEmpty(enrol_get_plugin('apply')->get_bulk_operations($manager));
    }

    /**
     * A site-disabled plugin offers no menu, because its entries could only throw.
     *
     * Core's two sides disagree: user/index.php builds the menu from get_enrolment_plugins(false),
     * which INCLUDES disabled plugins, while action_redir.php resolves the dispatch through the
     * enabled-only default and throws errorwithbulkoperation. So the entries were offered and then
     * refused. This is the opposite of what the per-row action icon does, deliberately - that one
     * leads to a queue that still works.
     *
     * @return void
     */
    public function test_a_disabled_plugin_offers_no_bulk_menu(): void {
        $this->setUser($this->teacher());
        $manager = $this->unfiltered_manager();

        // The control: enabled, and the menu is there.
        $this->assertNotEmpty($this->plugin->get_bulk_operations($manager));

        $enabled = enrol_get_plugins(true);
        unset($enabled['apply']);
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->assertSame([], enrol_get_plugin('apply')->get_bulk_operations($this->unfiltered_manager()));
    }

    /**
     * The dispatch instance is the FIRST apply row by sortorder, disabled or not.
     *
     * Reproducing action_redir.php literally is the whole point: a disabled method sorting first
     * captures the entire dispatch, the manager is filtered to it, and core redirects with "No
     * users selected". Taking the enabled-only list here would agree with core exactly when it
     * does not matter and disagree in the case this exists to name.
     *
     * @return void
     */
    public function test_the_dispatch_instance_is_the_first_one_disabled_or_not(): void {
        global $DB;

        // Sorted ahead of the fixture's own instance, and disabled.
        $first = $this->second_instance(false, -1);

        $this->assertEquals(
            (int) $first->id,
            (int) decision_operation::dispatch_instance((int) $this->course->id)->id
        );

        // The control: a course with no apply method at all has no dispatch instance.
        $elsewhere = $this->getDataGenerator()->create_course();
        $this->assertNull(decision_operation::dispatch_instance((int) $elsewhere->id));

        // And the fixture's own instance is what is returned once the disabled one sorts later.
        $DB->set_field('enrol', 'sortorder', 99, ['id' => $first->id]);
        $this->assertEquals(
            (int) $this->instance->id,
            (int) decision_operation::dispatch_instance((int) $this->course->id)->id
        );
    }

    /**
     * The three decisions are offered to somebody who may decide.
     *
     * @return void
     */
    public function test_the_bulk_menu_offers_the_three_decisions(): void {
        $this->setUser($this->teacher());
        [$manager] = $this->selection([]);

        $operations = $this->plugin->get_bulk_operations($manager);

        $this->assertEqualsCanonicalizing(
            ['confirmapplications', 'waitapplications', 'cancelapplications'],
            array_keys($operations)
        );
        foreach ($operations as $identifier => $operation) {
            // Core dispatches on the array key and never calls get_identifier(), so the two
            // agreeing is a property of this plugin alone.
            $this->assertSame($identifier, $operation->get_identifier());
        }
    }

    /**
     * Without the capability the menu is empty, which is also what refuses the dispatch.
     *
     * Core's driver looks the chosen operation up in this array and throws when it is absent,
     * and it applies no capability check of its own, so an empty array is the whole gate.
     *
     * @return void
     */
    public function test_the_bulk_menu_is_empty_without_the_capability(): void {
        $outsider = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($outsider->id, $this->course->id, 'student');
        $this->setUser($outsider);
        [$manager] = $this->selection([]);

        $this->assertSame([], $this->plugin->get_bulk_operations($manager));

        // Control: the same call for a teacher of the same course is not empty, so the empty
        // result is the capability and not the course, the manager or the instance.
        $this->setUser($this->teacher());
        $this->assertNotEmpty($this->plugin->get_bulk_operations($manager));
    }

    /**
     * A bulk confirmation runs the plugin's own approval, not a status update.
     *
     * This is the guard against the shape both core precedents ship: a raw UPDATE of
     * {user_enrolments} skips \core_enrol\hook\before_user_enrolment_updated, and with it
     * the role, the groups, the durable record and the applicant's notification.
     *
     * @return void
     */
    public function test_a_bulk_confirmation_runs_the_plugins_own_approval(): void {
        global $DB;

        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);

        $teacher = $this->teacher();
        $applicant = $this->applicant();
        $ueid = $this->ueid($applicant);
        $this->setUser($teacher);

        [$manager, $users] = $this->selection([$applicant]);
        $operation = new confirm_operation($manager, $this->plugin);
        $properties = (object) ['outcomemessage' => '', 'groups' => [$group->id], 'roleid' => 0];
        $this->assertTrue($operation->process($manager, $users, $properties));

        $this->assertEquals(
            ENROL_USER_ACTIVE,
            (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid], MUST_EXIST)
        );

        // Exactly one notification, whichever of the two approval passes queued it.
        $tasks = $DB->get_records('task_adhoc', ['classname' => '\enrol_apply\task\notify_approval']);
        $this->assertCount(1, $tasks);
        $this->assertEquals($ueid, (int) json_decode(reset($tasks)->customdata)->userenrolmentid);

        // The group membership, carrying the component stamp that lets core clean it up again.
        $membership = $DB->get_record(
            'groups_members',
            ['groupid' => $group->id, 'userid' => $applicant->id],
            '*',
            MUST_EXIST
        );
        $this->assertSame('enrol_apply', $membership->component);
        $this->assertEquals($this->instance->id, (int) $membership->itemid);

        // The durable record names the teacher who decided it.
        $record = $DB->get_record(
            'enrol_apply_submission',
            ['courseid' => $this->course->id, 'userid' => $applicant->id],
            '*',
            MUST_EXIST
        );
        $this->assertEquals(\enrol_apply\local\submission::STATUS_APPROVED, (int) $record->status);
        $this->assertEquals($teacher->id, (int) $record->decidedby);

        $this->assertSame([get_string('bulkdecided', 'enrol_apply', 1)], $this->notifications());
    }

    /**
     * A waiting-list application is decided by a bulk confirmation, and an active one is not.
     *
     * The control is what makes this test worth having: ENROL_APPLY_USER_WAIT is invisible to
     * core, so a predicate written as "suspended" rather than "not active" would drop the
     * deferred half of the queue while still passing every test about a pending application.
     *
     * @return void
     */
    public function test_a_bulk_confirmation_decides_a_waiting_list_application(): void {
        global $DB;

        $this->setUser($this->teacher());
        $waiting = $this->applicant(ENROL_APPLY_USER_WAIT);
        $active = $this->applicant(ENROL_USER_ACTIVE);

        [$manager, $users] = $this->selection([$waiting, $active]);
        $operation = new confirm_operation($manager, $this->plugin);
        $operation->process($manager, $users, (object) ['outcomemessage' => '']);

        $this->assertEquals(
            ENROL_USER_ACTIVE,
            (int) $DB->get_field('user_enrolments', 'status', ['id' => $this->ueid($waiting)], MUST_EXIST)
        );

        // The already active row was in the selection, was left alone, and was counted as such.
        $this->assertEqualsCanonicalizing(
            [
                get_string('bulkdecided', 'enrol_apply', 1),
                get_string('bulkskipped', 'enrol_apply', 1),
            ],
            $this->notifications()
        );
    }

    /**
     * A bulk deferral moves the applications to the waiting list.
     *
     * @return void
     */
    public function test_a_bulk_deferral_moves_the_applications_to_the_waiting_list(): void {
        global $DB;

        $this->setUser($this->teacher());
        $applicant = $this->applicant();

        [$manager, $users] = $this->selection([$applicant]);
        $operation = new wait_operation($manager, $this->plugin);
        $this->assertTrue($operation->process($manager, $users, (object) ['outcomemessage' => '']));

        $this->assertEquals(
            ENROL_APPLY_USER_WAIT,
            (int) $DB->get_field('user_enrolments', 'status', ['id' => $this->ueid($applicant)], MUST_EXIST)
        );
        $this->assertSame([get_string('bulkdecided', 'enrol_apply', 1)], $this->notifications());
    }

    /**
     * A bulk cancellation unenrols the applicants.
     *
     * @return void
     */
    public function test_a_bulk_cancellation_unenrols_the_applicants(): void {
        global $DB;

        $this->setUser($this->teacher());
        $applicant = $this->applicant();
        $ueid = $this->ueid($applicant);

        [$manager, $users] = $this->selection([$applicant]);
        $operation = new cancel_operation($manager, $this->plugin);
        $this->assertTrue($operation->process($manager, $users, (object) ['outcomemessage' => '']));

        $this->assertFalse($DB->record_exists('user_enrolments', ['id' => $ueid]));
        $this->assertSame([get_string('bulkdecided', 'enrol_apply', 1)], $this->notifications());
    }

    /**
     * process() refuses an operator without the capability, and says so.
     *
     * The capability is checked here as well as in get_bulk_operations() because process() is
     * public and core's driver adds no gate of its own: it performs no require_login() and no
     * require_capability() anywhere in the bulk branch.
     *
     * @return void
     */
    public function test_process_refuses_an_operator_without_the_capability(): void {
        global $DB;

        $teacher = $this->teacher();
        $applicant = $this->applicant();
        $ueid = $this->ueid($applicant);

        // Assembled as the teacher, then acted on as somebody who may not decide - which is
        // what a hand-built post is, and what any driver other than core's might hand over.
        $this->setUser($teacher);
        [$manager, $users] = $this->selection([$applicant]);

        $outsider = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($outsider->id, $this->course->id, 'student');
        $this->setUser($outsider);

        $operation = new confirm_operation($manager, $this->plugin);
        $this->assertFalse($operation->process($manager, $users, (object) ['outcomemessage' => '']));

        $this->assertEquals(
            ENROL_USER_SUSPENDED,
            (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid], MUST_EXIST)
        );
        $this->assertSame([get_string('bulknotpermitted', 'enrol_apply')], $this->notifications());
    }

    /**
     * Only this plugin's enrolments are handed to the decision.
     *
     * The base class promises nothing about the array process() receives, and a foreign user
     * enrolment id does not skip when it reaches confirm_enrolment(): get_pending_user_enrolment()
     * has no enrol-type predicate and the MUST_EXIST lookup after it throws.
     *
     * @return void
     */
    public function test_only_this_plugins_enrolments_are_decided(): void {
        global $DB, $PAGE;

        $this->setUser($this->teacher());
        $applicant = $this->applicant();
        $manual = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manual->id, $this->course->id, 'student');

        // An UNFILTERED manager, which is what every caller other than the participants-page
        // driver builds, so both enrolment methods are present in the one array.
        $PAGE->set_url('/user/index.php', ['id' => $this->course->id]);
        $manager = new \course_enrolment_manager($PAGE, $this->course);
        $users = $manager->get_users_enrolments([(int) $applicant->id, (int) $manual->id]);

        $manualueid = (int) $DB->get_field_sql(
            "SELECT ue.id
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :userid AND e.enrol = :enrol",
            ['userid' => $manual->id, 'enrol' => 'manual'],
            MUST_EXIST
        );
        $found = decision_operation::enrolments_of($users);
        $this->assertSame([$this->ueid($applicant)], array_keys($found));
        $this->assertEquals(ENROL_USER_SUSPENDED, (int) reset($found)->status);

        $operation = new confirm_operation($manager, $this->plugin);
        $this->assertTrue($operation->process($manager, $users, (object) ['outcomemessage' => '']));

        // The manual enrolment was in the selection and was never touched.
        $manualrow = $DB->get_record('user_enrolments', ['id' => $manualueid], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_ACTIVE, (int) $manualrow->status);
        $this->assertSame([get_string('bulkdecided', 'enrol_apply', 1)], $this->notifications());
    }

    /**
     * An application in a course the operator holds no capability in is not decided.
     *
     * The bulk path adds no per-row authorisation of its own: it inherits the one
     * can_manage_application() applies inside every decision method, which is the reason it
     * delegates rather than writing the status itself. The count then reports the refusal,
     * because it is taken by re-reading the rows rather than by predicting them.
     *
     * @return void
     */
    public function test_an_application_in_another_course_is_not_decided(): void {
        global $DB;

        $elsewhere = $this->getDataGenerator()->create_course();
        $foreigninstanceid = $this->plugin->add_instance($elsewhere, $this->plugin->get_instance_defaults());
        $foreigninstance = $DB->get_record('enrol', ['id' => $foreigninstanceid], '*', MUST_EXIST);
        $stranger = $this->applicant(ENROL_USER_SUSPENDED, $foreigninstance);
        $strangerueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $stranger->id, 'enrolid' => $foreigninstance->id],
            MUST_EXIST
        );

        $mine = $this->applicant();
        $this->setUser($this->teacher());

        /* A manager for this course, holding a selection that reaches beyond it. Core's own
           driver cannot produce that shape - it builds the manager with the instance filter,
           so an id from another course never comes back - so this is the shape a driver other
           than core's produces, which is the same standing the capability re-check has. */
        [$manager, $users] = $this->selection([$mine]);
        [, $foreignusers] = $this->selection([$stranger], $foreigninstance);
        $users += $foreignusers;

        $operation = new confirm_operation($manager, $this->plugin);
        $operation->process($manager, $users, (object) ['outcomemessage' => '']);

        $this->assertEquals(
            ENROL_USER_ACTIVE,
            (int) $DB->get_field('user_enrolments', 'status', ['id' => $this->ueid($mine)], MUST_EXIST)
        );
        $this->assertEquals(
            ENROL_USER_SUSPENDED,
            (int) $DB->get_field('user_enrolments', 'status', ['id' => $strangerueid], MUST_EXIST)
        );
        /* The refusal is counted as an application left unchanged, not as somebody with no
           application: the foreign row IS awaiting a decision, it is just not this
           operator's to take. */
        $this->assertEqualsCanonicalizing(
            [
                get_string('bulkdecided', 'enrol_apply', 1),
                get_string('bulkunchanged', 'enrol_apply', 1),
            ],
            $this->notifications()
        );
    }

    /**
     * The message typed on the confirmation form reaches the applicant's durable record.
     *
     * @return void
     */
    public function test_the_outcome_message_reaches_the_durable_record(): void {
        $this->setUser($this->teacher());
        $applicant = $this->applicant();

        [$manager, $users] = $this->selection([$applicant]);
        $operation = new confirm_operation($manager, $this->plugin);
        $operation->process($manager, $users, (object) ['outcomemessage' => '  Welcome aboard  ']);

        $this->assertSame(
            'Welcome aboard',
            \enrol_apply\local\submission::outcome_message($this->ueid($applicant))
        );
    }

    /**
     * The confirmation form carries the selection into its own post.
     *
     * The selected ids reach core's driver from the participants table's checkbox names, which
     * exist only on the first post. On the second they survive purely because the form emits a
     * hidden bulkuser[] input per row - and a form that omits them submits cleanly and then
     * redirects the operator back with "No users selected", as though nothing had been ticked.
     *
     * @return void
     */
    public function test_the_confirmation_form_carries_the_selection_forward(): void {
        $this->setUser($this->teacher());
        $first = $this->applicant();
        $second = $this->applicant();

        [$manager, $users] = $this->selection([$first, $second]);
        $operation = new confirm_operation($manager, $this->plugin);
        $form = $operation->get_form(new \moodle_url('/user/action_redir.php'), ['users' => $users]);

        $html = $form->render();
        preg_match_all('/<input[^>]*name="bulkuser\[\d+\]"[^>]*>/', $html, $matches);
        $this->assertCount(2, $matches[0], 'one hidden input per selected user');

        // Read back the way user/action_redir.php reads it, so the test holds the round trip
        // and not merely the markup.
        preg_match_all('/name="bulkuser\[(\d+)\]"\s+type="hidden"\s+value="(\d+)"/', $html, $pairs);
        $_POST['bulkuser'] = array_combine($pairs[1], $pairs[2]);
        $posted = optional_param_array('bulkuser', [], PARAM_INT);
        unset($_POST['bulkuser']);

        $this->assertEqualsCanonicalizing([(int) $first->id, (int) $second->id], array_values($posted));
    }

    /**
     * Only confirmation offers the group and role choosers.
     *
     * wait_enrolment() and cancel_enrolment() take a message and nothing else, so a chooser on
     * either form would be a control with no effect.
     *
     * @return void
     */
    public function test_only_confirmation_offers_the_group_and_role_choosers(): void {
        $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->setUser($this->teacher());
        $applicant = $this->applicant();
        [$manager, $users] = $this->selection([$applicant]);

        $confirm = (new confirm_operation($manager, $this->plugin))
            ->get_form(new \moodle_url('/user/action_redir.php'), ['users' => $users])
            ->render();
        $this->assertMatchesRegularExpression('/<select[^>]*name="groups\[\]"/', $confirm);
        $this->assertMatchesRegularExpression('/<select[^>]*name="roleid"/', $confirm);

        foreach ([new wait_operation($manager, $this->plugin), new cancel_operation($manager, $this->plugin)] as $op) {
            $html = $op->get_form(new \moodle_url('/user/action_redir.php'), ['users' => $users])->render();
            $this->assertDoesNotMatchRegularExpression('/name="groups\[\]"/', $html, $op->get_identifier());
            $this->assertDoesNotMatchRegularExpression('/name="roleid"/', $html, $op->get_identifier());
            $this->assertMatchesRegularExpression('/name="outcomemessage"/', $html, $op->get_identifier());
        }
    }

    /**
     * An expired enrolment is not an application, and no bulk decision touches one.
     *
     * process_expirations() re-suspends an enrolment whose period ran out, so somebody
     * approved and enrolled long ago comes back looking exactly like a fresh application.
     * The plugin's queue excludes that row deliberately - its predicate pairs
     * "status != active" with a timeend clause, and
     * tests/lib_test.php::test_expired_enrolment_does_not_reappear_in_the_queue pins it -
     * but the exclusion lives only in the LISTING, and the participants page is a second
     * listing that core owns. The pending applicant in each selection is the control: it
     * proves the decision really ran, so the expired row surviving is a refusal rather than
     * a batch that did nothing.
     *
     * @return void
     */
    public function test_no_bulk_decision_touches_an_expired_enrolment(): void {
        global $DB;

        foreach ([confirm_operation::class, wait_operation::class, cancel_operation::class] as $class) {
            $this->setUser($this->teacher());
            $expired = $this->expired_participant();
            $expiredueid = $this->ueid($expired);
            $expiredend = (int) $DB->get_field('user_enrolments', 'timeend', ['id' => $expiredueid], MUST_EXIST);
            $pending = $this->applicant();
            $pendingueid = $this->ueid($pending);

            [$manager, $users] = $this->selection([$expired, $pending]);
            $operation = new $class($manager, $this->plugin);
            $operation->process($manager, $users, (object) ['outcomemessage' => '']);

            $row = $DB->get_record('user_enrolments', ['id' => $expiredueid]);
            $this->assertNotFalse($row, $class . ' unenrolled an expired participant');
            $this->assertEquals(ENROL_USER_SUSPENDED, (int) $row->status, $class);
            $this->assertEquals($expiredend, (int) $row->timeend, $class);

            /* The control: the decision really ran on the application that was awaiting one.
               get_field() returns false where the row is gone, which is what a cancellation
               leaves, so this holds for all three without knowing which ran. */
            $this->assertNotEquals(
                ENROL_USER_SUSPENDED,
                $DB->get_field('user_enrolments', 'status', ['id' => $pendingueid]),
                $class . ' left the pending application alone as well'
            );
            $this->assertEqualsCanonicalizing(
                [
                    get_string('bulkdecided', 'enrol_apply', 1),
                    get_string('bulkskipped', 'enrol_apply', 1),
                ],
                $this->notifications(),
                $class
            );
        }
    }

    /**
     * An application already on the waiting list is reported as unchanged, not as absent.
     *
     * The row IS reached now - wait_enrolment()'s lookup admits a deferred application, which is
     * what lets a decider edit its reason - but its enrolment does not move, so the operation
     * counts it as unchanged. It is still an application awaiting a decision, which the queue
     * lists, so counting it under the "no application awaiting a decision" heading would tell
     * the operator something the plugin's own queue denies.
     *
     * @return void
     */
    public function test_deferring_an_already_deferred_application_reports_it_as_unchanged(): void {
        global $DB;

        $this->setUser($this->teacher());
        $deferred = $this->applicant(ENROL_APPLY_USER_WAIT);
        $pending = $this->applicant();

        [$manager, $users] = $this->selection([$deferred, $pending]);
        $operation = new wait_operation($manager, $this->plugin);
        $operation->process($manager, $users, (object) ['outcomemessage' => '']);

        $this->assertEquals(
            ENROL_APPLY_USER_WAIT,
            (int) $DB->get_field('user_enrolments', 'status', ['id' => $this->ueid($deferred)], MUST_EXIST)
        );
        // The control: the pending one really was deferred, so the batch was not a no-op.
        $this->assertEquals(
            ENROL_APPLY_USER_WAIT,
            (int) $DB->get_field('user_enrolments', 'status', ['id' => $this->ueid($pending)], MUST_EXIST)
        );
        $this->assertEqualsCanonicalizing(
            [
                get_string('bulkdecided', 'enrol_apply', 1),
                get_string('bulkunchanged', 'enrol_apply', 1),
            ],
            $this->notifications()
        );
    }

    /**
     * A selection carrying none of this plugin's enrolments says so and decides nothing.
     *
     * Unreachable through core's driver, which redirects with "No users selected" first -
     * but reachable, and held here, for the same reason the capability re-check in
     * process() is: the base class promises nothing about the array it hands over.
     *
     * @return void
     */
    public function test_a_selection_with_no_applications_decides_nothing(): void {
        global $DB, $PAGE;

        $this->setUser($this->teacher());
        $manual = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manual->id, $this->course->id, 'student');

        $PAGE->set_url('/user/index.php', ['id' => $this->course->id]);
        $manager = new \course_enrolment_manager($PAGE, $this->course);
        $users = $manager->get_users_enrolments([(int) $manual->id]);

        $operation = new confirm_operation($manager, $this->plugin);
        $this->assertTrue($operation->process($manager, $users, (object) ['outcomemessage' => '']));

        $this->assertSame([get_string('bulknothingdecided', 'enrol_apply')], $this->notifications());
        $this->assertSame(0, $DB->count_records('enrol_apply_submission', ['status' => 1]));
    }

    /**
     * Every name the confirmation form puts on screen is escaped exactly once.
     *
     * All three sinks here render RAW - a static element is {{{element.html}}} and a select's
     * options are {{{text}}} - so each value has to arrive escaped and must not be escaped
     * again. Nothing in the pipeline reads this: phpcs reads PHP, the mustache lint reads
     * structure, and neither knows which stash a value lands in. The plugin has shipped this
     * defect class four times, which is why it is pinned rather than described.
     *
     * @return void
     */
    public function test_the_confirmation_form_escapes_every_name_exactly_once(): void {
        global $DB;

        $awkward = 'R&D < Team';
        $escapedonce = 'R&amp;D &lt; Team';
        $escapedtwice = 'R&amp;amp;D &amp;lt; Team';

        $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => $awkward]);
        $this->setUser($this->teacher());
        $applicant = $this->applicant();
        // Written straight to the column, which is what an LDAP or SSO sync does; the
        // generator cleans its input and would strip the very characters under test.
        $DB->set_field('user', 'lastname', $awkward, ['id' => $applicant->id]);

        [$manager, $users] = $this->selection([$applicant]);
        $html = (new confirm_operation($manager, $this->plugin))
            ->get_form(new \moodle_url('/user/action_redir.php'), ['users' => $users])
            ->render();

        // The applicant's name, in the static element. Scoped to that element, because the
        // page carries the group name too and either would satisfy a whole-page match.
        $this->assertSame(1, preg_match(
            '/id="fitem_id_bulkapplicants".*?(?=id="fitem_id_|<\/form>)/s',
            $html,
            $item
        ));
        $this->assertStringContainsString($escapedonce, $item[0]);
        $this->assertStringNotContainsString($awkward, $item[0]);
        $this->assertStringNotContainsString($escapedtwice, $item[0]);

        // The group name, in the select.
        $this->assertSame(1, preg_match('/<select[^>]*name="groups\[\]".*?<\/select>/s', $html, $select));
        $this->assertStringContainsString($escapedonce, $select[0]);
        $this->assertStringNotContainsString($awkward, $select[0]);
        $this->assertStringNotContainsString($escapedtwice, $select[0]);
    }

    /**
     * A bulk decision warns about the same person's applications on another method.
     *
     * The silent case, and the reachable one. Core's dispatch filters the manager to ONE apply
     * instance, so get_users_enrolments() returns only that instance's row - and for one person
     * holding an application on each of two, nothing is "removed", core warns nothing, and the
     * plugin reported a clean success. Two applications in one course are supported on purpose:
     * two apply methods are two intakes.
     *
     * The other application must still be there afterwards. That is the second half of the
     * decision recorded in the plan - warn, never decide - and without it this test would pass
     * against an implementation that had helpfully decided both.
     *
     * @return void
     */
    public function test_a_bulk_decision_warns_about_applications_on_another_method(): void {
        global $DB;

        $this->setUser($this->teacher());
        $applicant = $this->applicant();
        $other = $this->second_instance();
        $this->plugin->enrol_user($other, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $otherueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $other->id],
            MUST_EXIST
        );

        [$manager, $users] = $this->selection([$applicant]);
        // The precondition: core really did hand over one row, which is why it warns nothing.
        $this->assertCount(1, $users[$applicant->id]->enrolments);

        $sink = $this->redirectMessages();
        (new wait_operation($manager, $this->plugin))
            ->process($manager, $users, (object) ['outcomemessage' => '', 'decisionnote' => '']);
        $sink->close();

        $this->assertContains(
            get_string('bulkothermethods', 'enrol_apply', (object) [
                'count' => 1,
                'method' => get_string('pluginname', 'enrol_apply'),
            ]),
            $this->notifications()
        );

        // Warned, never decided: the other application is exactly as it was.
        $this->assertEquals(
            ENROL_USER_SUSPENDED,
            (int) $DB->get_field('user_enrolments', 'status', ['id' => $otherueid], MUST_EXIST)
        );
    }

    /**
     * One method in the course means nothing to warn about.
     *
     * The control. Without it the assertion above passes just as well against an operation that
     * warns on every decision it takes.
     *
     * @return void
     */
    public function test_a_single_method_produces_no_other_methods_warning(): void {
        $this->setUser($this->teacher());
        $applicant = $this->applicant();

        [$manager, $users] = $this->selection([$applicant]);
        $sink = $this->redirectMessages();
        (new wait_operation($manager, $this->plugin))
            ->process($manager, $users, (object) ['outcomemessage' => '', 'decisionnote' => '']);
        $sink->close();

        foreach ($this->notifications() as $message) {
            $this->assertStringNotContainsString('will be left alone', $message);
            $this->assertStringNotContainsString('left alone', $message);
        }
    }

    /**
     * The confirmation form says it too, before anything is written.
     *
     * The only surface in this flow that can speak before the decision. A warning that arrives
     * afterwards is a report; this one is a chance to stop and use the approval queue instead,
     * which reaches every method.
     *
     * @return void
     */
    public function test_the_confirmation_form_warns_before_anything_is_written(): void {
        $this->setUser($this->teacher());
        $applicant = $this->applicant();
        $other = $this->second_instance();
        $this->plugin->enrol_user($other, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);

        [$manager, $users] = $this->selection([$applicant]);
        $html = (new wait_operation($manager, $this->plugin))
            ->get_form(new \moodle_url('/user/action_redir.php'), ['users' => $users])
            ->render();

        $this->assertStringContainsString(
            get_string('bulkothermethodsform', 'enrol_apply', (object) [
                'count' => 1,
                'method' => get_string('pluginname', 'enrol_apply'),
            ]),
            $html
        );
    }

    /**
     * All three bulk decisions offer the note box and record what is typed in it.
     *
     * The participants page is the third decision surface, and a field the queue and the review
     * page both offer while this one silently does not is how two surfaces come to describe the
     * same record differently. The loop covers all three because each operation builds its own
     * call into the plugin and only confirmation shares a code path with the choosers.
     *
     * @return void
     */
    public function test_every_bulk_decision_offers_the_note_and_records_it(): void {
        global $DB;

        $classes = [
            confirm_operation::class => 'Transcript verified.',
            wait_operation::class => 'Holding for the September intake.',
            cancel_operation::class => 'Duplicate of last week.',
        ];

        foreach ($classes as $class => $note) {
            $this->setUser($this->teacher());
            $applicant = $this->applicant();
            $ueid = $this->ueid($applicant);

            [$manager, $users] = $this->selection([$applicant]);
            $operation = new $class($manager, $this->plugin);

            // The control: the box is really offered, so the recording below is reachable by hand.
            $html = $operation->get_form(new \moodle_url('/user/action_redir.php'), ['users' => $users])->render();
            $this->assertMatchesRegularExpression('~<textarea[^>]*name="decisionnote"~', $html, $class);

            $sink = $this->redirectMessages();
            $operation->process($manager, $users, (object) ['outcomemessage' => '', 'decisionnote' => $note]);
            $sink->close();
            $this->notifications();

            $this->assertSame(
                $note,
                (string) $DB->get_field(
                    'enrol_apply_submission',
                    'decisionnote',
                    ['userenrolmentid' => $ueid],
                    MUST_EXIST
                ),
                $class
            );
        }
    }
}
