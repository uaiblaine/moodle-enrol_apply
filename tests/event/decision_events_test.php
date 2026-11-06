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
 * Tests for the three decision events and the marker that keeps an approval logged once.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\event;

use enrol_apply\local\decisionlog;
use enrol_apply\local\keyattempts;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass targets resolve and the bulk operation finds its manager.
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

/**
 * Tests for the three decision events and the marker that keeps an approval logged once.
 *
 * Every approval route is driven for real, because the property under test is a count across two
 * passes of complete_approval() that only the real routes produce. Most read an event sink; Edit
 * enrolment reads the standard log store instead, since a sink stops observers from running and
 * that route is fired by one.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(application_decided::class)]
#[CoversClass(application_approved::class)]
#[CoversClass(application_deferred::class)]
#[CoversClass(application_cancelled::class)]
#[CoversClass(decisionlog::class)]
#[CoversClass(\enrol_apply_plugin::class)]
final class decision_events_test extends \advanced_testcase {
    /** @var string The group enrolment key of the keyed test. */
    private const KEY = 'Turma-A-2027';

    /** @var \enrol_apply_plugin The plugin under test. */
    private $plugin;

    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass The apply instance, giving the student role and one group. */
    private $instance;

    /** @var \stdClass The group the instance's own list joins. */
    private $listed;

    /** @var \stdClass A group only a decider's choice joins. */
    private $chosen;

    /** @var \stdClass An editing teacher of the course, who decides. */
    private $teacher;

    /** @var \phpunit_message_sink Messages the decisions send, kept out of the way. */
    private $messages;

    /**
     * A course with an apply instance, two groups and a deciding teacher.
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
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $id = $this->plugin->add_instance(
            $this->course,
            ['roleid' => $this->role('student')] + $this->plugin->get_instance_defaults()
        );
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $this->listed = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Everyone']);
        $this->chosen = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Chosen']);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $id, 'groupid' => $this->listed->id]);

        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->messages = $this->redirectMessages();
    }

    /**
     * Release what the keyed test held and forget any marker.
     *
     * @return void
     */
    protected function tearDown(): void {
        $this->messages->close();
        keyattempts::release_all();
        decisionlog::reset();
        parent::tearDown();
    }

    /**
     * A role id by shortname.
     *
     * @param string $shortname Role shortname.
     * @return int Role id.
     */
    private function role(string $shortname): int {
        global $DB;

        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * A fresh applicant with a real application, so the durable record exists.
     *
     * @return array The applicant and the user enrolment id.
     */
    private function application(): array {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);
        $method->invoke($this->plugin, $this->instance, $applicant->id, (object) ['applydescription' => '']);

        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['enrolid' => $this->instance->id, 'userid' => $applicant->id],
            MUST_EXIST
        );

        return [$applicant, $ueid];
    }

    /**
     * The id of the durable record of an application.
     *
     * @param int $ueid User enrolment id.
     * @return int Record id.
     */
    private function record_id(int $ueid): int {
        global $DB;

        return (int) $DB->get_field('enrol_apply_submission', 'id', ['userenrolmentid' => $ueid], MUST_EXIST);
    }

    /**
     * The captured events of one class, in the order they were triggered.
     *
     * @param \phpunit_event_sink $sink The sink.
     * @param string $class Event class.
     * @return array Events.
     */
    private function events_of(\phpunit_event_sink $sink, string $class): array {
        return array_values(array_filter($sink->get_events(), static fn($e): bool => $e instanceof $class));
    }

    /**
     * Where in the sink an event of a class was triggered.
     *
     * @param \phpunit_event_sink $sink The sink.
     * @param string $class Event class.
     * @return int Position, -1 when absent.
     */
    private function position_of(\phpunit_event_sink $sink, string $class): int {
        foreach (array_values($sink->get_events()) as $i => $event) {
            if ($event instanceof $class) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * An approval from the queue (and the review page, which posts to the same method) logs one
     * event, after core's write, naming what it gave and who decided.
     *
     * Changes that must make it fail: activate() writing outside decisionlog::defer(), so the hook
     * pass logs too; complete_approval() ignoring the marker; the groups or the role not reported.
     *
     * @return void
     */
    public function test_a_queue_approval_logs_once_after_the_write(): void {
        [$applicant, $ueid] = $this->application();
        $this->setUser($this->teacher);
        $sink = $this->redirectEvents();

        $decided = $this->plugin->confirm_enrolment([$ueid], 'A message for you', [
            'groups' => [$this->chosen->id],
            'roleid' => $this->role('teacher'),
            'note' => 'An internal note',
        ]);

        $this->assertSame(1, $decided);
        $approved = $this->events_of($sink, application_approved::class);
        $this->assertCount(1, $approved);
        $event = $approved[0];

        // After core's own event, which core triggers once the row is written.
        $this->assertGreaterThan(
            $this->position_of($sink, \core\event\user_enrolment_updated::class),
            $this->position_of($sink, application_approved::class)
        );
        $this->assertNotSame(-1, $this->position_of($sink, \core\event\user_enrolment_updated::class));

        $this->assertSame('enrol_apply_submission', $event->objecttable);
        $this->assertSame($this->record_id($ueid), (int) $event->objectid);
        $this->assertSame((int) $applicant->id, (int) $event->relateduserid);
        $this->assertSame((int) $this->teacher->id, (int) $event->userid);
        $this->assertSame(\context_course::instance($this->course->id)->id, (int) $event->contextid);
        $this->assertSame('u', $event->crud);
        $this->assertSame(\core\event\base::LEVEL_TEACHING, $event->edulevel);
        $this->assertSame((int) $this->instance->id, $event->other['enrolid']);
        $this->assertSame($ueid, $event->other['userenrolmentid']);
        $this->assertSame([(int) $this->chosen->id], $event->other['groups']);
        $this->assertSame($this->role('teacher'), $event->other['roleid']);
        $this->assertTrue($event->other['chosen']);
        $this->assertSame(0, $event->other['keygroupid']);

        // Ids only: neither text the decider wrote reaches the log.
        $encoded = json_encode($event->other);
        $this->assertStringNotContainsString('A message for you', $encoded);
        $this->assertStringNotContainsString('An internal note', $encoded);
    }

    /**
     * In the site log, where observers run, a queue approval is still recorded once: the hook
     * pass that core's write dispatches stays silent while activate() owes the approval, and
     * nothing it might have parked survives to be fired by the observer after the write.
     *
     * Changes that must make it fail: activate() writing outside decisionlog::defer(), or
     * complete_approval() ignoring the marker, either of which leaves a parked copy for the
     * observer to log beside activate()'s own.
     *
     * @return void
     */
    public function test_a_queue_approval_is_logged_once_in_the_site_log(): void {
        [, $ueid] = $this->application();
        $this->setUser($this->teacher);
        $this->enable_the_log();

        $this->assertSame(1, $this->plugin->confirm_enrolment([$ueid], '', []));

        $this->assertCount(1, $this->logged(application_approved::class));
        $this->assertFalse(decisionlog::is_parked($ueid));
    }

    /**
     * The participants page's bulk confirmation logs one approval per application.
     *
     * Changes that must make it fail: activate() writing outside decisionlog::defer().
     *
     * @return void
     */
    public function test_a_bulk_approval_logs_once_per_application(): void {
        global $PAGE;

        [$first] = $this->application();
        [$second] = $this->application();
        $this->setUser($this->teacher);

        $PAGE->set_url('/user/index.php', ['id' => $this->course->id]);
        $manager = new \course_enrolment_manager($PAGE, $this->course, $this->instance->id);
        $users = $manager->get_users_enrolments([$first->id, $second->id]);
        $operation = new \enrol_apply\bulk\confirm_operation($manager, $this->plugin);

        $sink = $this->redirectEvents();
        $this->assertTrue($operation->process($manager, $users, (object) ['outcomemessage' => '']));

        $approved = $this->events_of($sink, application_approved::class);
        $this->assertCount(2, $approved);
        $this->assertEqualsCanonicalizing(
            [(int) $first->id, (int) $second->id],
            array_map(static fn($e): int => (int) $e->relateduserid, $approved)
        );
    }

    /**
     * Send the site's log to the standard log store, written at once, and return a reader.
     *
     * A sink would do for the other routes, but redirecting events stops observers from running,
     * and the Edit enrolment route is fired by one: only a real dispatch tells it apart.
     *
     * @return void
     */
    private function enable_the_log(): void {
        // Logging waits until the transaction is committed, and the test's own is never committed.
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
    }

    /**
     * The log rows of one event class, oldest first.
     *
     * @param string $class Event class.
     * @return array The rows, in id order.
     */
    private function logged(string $class): array {
        global $DB;

        return array_values($DB->get_records('logstore_standard_log', ['eventname' => '\\' . ltrim($class, '\\')], 'id'));
    }

    /**
     * Core's "Edit enrolment" route, which reaches complete_approval() only through the hook,
     * logs one approval, AFTER core has written the row and triggered its own event, with the
     * method's defaults.
     *
     * Changes that must make it fail: the approval event dropped from complete_approval(); the
     * chosen flag claiming a choice nobody made; the event fired from the hook pass, before the
     * write.
     *
     * @return void
     */
    public function test_an_edit_enrolment_approval_logs_once_after_the_write(): void {
        global $DB;

        [$applicant, $ueid] = $this->application();
        $this->setUser($this->teacher);
        $this->enable_the_log();

        $this->plugin->update_user_enrol($this->instance, $applicant->id, ENROL_USER_ACTIVE);

        $approved = $this->logged(application_approved::class);
        $this->assertCount(1, $approved);
        $updated = $this->logged(\core\event\user_enrolment_updated::class);
        $this->assertNotEmpty($updated);
        $this->assertGreaterThan((int) end($updated)->id, (int) $approved[0]->id, 'logged before core wrote the row');
        $this->assertSame((int) ENROL_USER_ACTIVE, (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid]));
        $this->assertSame($this->record_id($ueid), (int) $approved[0]->objectid);
        $this->assertSame((int) $this->teacher->id, (int) $approved[0]->userid);
        // Either format the store may be set to, by the rule of \tool_log\helper\reader::decode_other().
        $raw = (string) $approved[0]->other;
        $other = preg_match('~^.:~', $raw) ? unserialize($raw, ['allowed_classes' => false]) : json_decode($raw, true);
        $this->assertSame([(int) $this->listed->id], $other['groups']);
        $this->assertSame($this->role('student'), $other['roleid']);
        $this->assertFalse($other['chosen']);
        $this->assertFalse(decisionlog::is_parked($ueid));
    }

    /**
     * An entry parked for a write that never happens logs nothing, even when an unrelated update
     * of the same enrolment reaches the observer afterwards.
     *
     * The control is the first update that does activate the row: the same parked event logs then.
     *
     * Changes that must make it fail: the observer firing without re-reading the row's status.
     *
     * @return void
     */
    public function test_a_parked_approval_whose_write_never_happened_logs_nothing(): void {
        global $DB;

        [$applicant, $ueid] = $this->application();
        $this->setUser($this->teacher);
        $this->enable_the_log();

        // The hook pass alone: the row is not written, as when core's write throws.
        $hookpass = function () use ($applicant, $ueid): void {
            $this->plugin->complete_approval($this->instance, (int) $applicant->id, $ueid);
        };
        $hookpass();
        $this->assertTrue(decisionlog::is_parked($ueid));
        $this->assertCount(0, $this->logged(application_approved::class));
        $this->assertNotSame((int) ENROL_USER_ACTIVE, (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid]));

        // An unrelated update of the still-pending row, as core's date editing would trigger.
        \core\event\user_enrolment_updated::create([
            'objectid' => $ueid,
            'courseid' => $this->course->id,
            'context' => \context_course::instance($this->course->id),
            'relateduserid' => $applicant->id,
            'other' => ['enrol' => 'apply'],
        ])->trigger();

        $this->assertCount(0, $this->logged(application_approved::class));
        $this->assertFalse(decisionlog::is_parked($ueid), 'the entry was not consumed');

        // The control: with the row active, a parked entry does log.
        $hookpass();
        $DB->set_field('user_enrolments', 'status', ENROL_USER_ACTIVE, ['id' => $ueid]);
        \core\event\user_enrolment_updated::create([
            'objectid' => $ueid,
            'courseid' => $this->course->id,
            'context' => \context_course::instance($this->course->id),
            'relateduserid' => $applicant->id,
            'other' => ['enrol' => 'apply'],
        ])->trigger();
        $this->assertCount(1, $this->logged(application_approved::class));
    }

    /**
     * The observer ignores another method's user_enrolment_updated, and leaves the entry parked.
     *
     * Changes that must make it fail: the enrol name filter dropped from the observer.
     *
     * @return void
     */
    public function test_the_observer_ignores_another_enrol_plugins_update(): void {
        global $DB;

        [$applicant, $ueid] = $this->application();
        $this->setUser($this->teacher);
        $this->enable_the_log();
        $this->plugin->complete_approval($this->instance, (int) $applicant->id, $ueid);
        $DB->set_field('user_enrolments', 'status', ENROL_USER_ACTIVE, ['id' => $ueid]);

        \core\event\user_enrolment_updated::create([
            'objectid' => $ueid,
            'courseid' => $this->course->id,
            'context' => \context_course::instance($this->course->id),
            'relateduserid' => $applicant->id,
            'other' => ['enrol' => 'manual'],
        ])->trigger();

        $this->assertCount(0, $this->logged(application_approved::class));
        $this->assertTrue(decisionlog::is_parked($ueid));
    }

    /**
     * A parked entry is dropped when activate() takes over the approval, so the write it makes
     * logs once, from activate(), and not a second time from the stale entry.
     *
     * Changes that must make it fail: the clear dropped from activate().
     *
     * @return void
     */
    public function test_activate_drops_a_stale_parked_approval(): void {
        [$applicant, $ueid] = $this->application();
        $this->setUser($this->teacher);
        $this->enable_the_log();
        $this->plugin->complete_approval($this->instance, (int) $applicant->id, $ueid);
        $this->assertTrue(decisionlog::is_parked($ueid));

        $this->plugin->confirm_enrolment([$ueid]);

        $this->assertFalse(decisionlog::is_parked($ueid));
        $this->assertCount(1, $this->logged(application_approved::class));
    }

    /**
     * A decider narrowed by separate groups who is refused on Edit enrolment (S7) leaves no
     * approval in the log and nothing parked.
     *
     * Changes that must make it fail: the refusal moved after complete_approval() in the hook.
     *
     * @return void
     */
    public function test_a_refused_edit_enrolment_approval_logs_nothing(): void {
        global $DB;

        $moderatorrole = $this->role('teacher');
        $system = \context_system::instance()->id;
        assign_capability('enrol/apply:manage', CAP_ALLOW, $moderatorrole, $system, true);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $moderatorrole, $system, true);
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $this->course->id]);
        $moderator = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->getDataGenerator()->create_group_member(['groupid' => $this->chosen->id, 'userid' => $moderator->id]);
        [$applicant, $ueid] = $this->application();
        $this->setUser($moderator);
        $this->enable_the_log();

        try {
            $this->plugin->update_user_enrol($this->instance, $applicant->id, ENROL_USER_ACTIVE);
            $this->fail('the update went through');
        } catch (\moodle_exception $e) {
            $this->assertSame('groupscopeapprovalrefused', $e->errorcode);
        }

        $this->assertCount(0, $this->logged(application_approved::class));
        $this->assertFalse(decisionlog::is_parked($ueid));
        $this->assertNotSame((int) ENROL_USER_ACTIVE, (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid]));
    }

    /**
     * Touching an application already decided logs no further approval.
     *
     * The control is the first approval, which does log: the later touches are silent because
     * nothing is approved, not because the event never fires.
     *
     * @return void
     */
    public function test_a_no_op_re_touch_logs_nothing(): void {
        [$applicant, $ueid] = $this->application();
        $this->setUser($this->teacher);
        $sink = $this->redirectEvents();

        $this->plugin->confirm_enrolment([$ueid]);
        $this->assertCount(1, $this->events_of($sink, application_approved::class));
        $sink->clear();

        $this->assertSame(0, $this->plugin->confirm_enrolment([$ueid]));
        $this->plugin->update_user_enrol($this->instance, $applicant->id, ENROL_USER_ACTIVE);
        $this->plugin->update_user_enrol($this->instance, $applicant->id, null, null, time() + WEEKSECS);

        $this->assertCount(0, $this->events_of($sink, application_approved::class));
    }

    /**
     * A group key's admission logs its own event and no approval.
     *
     * Changes that must make it fail: admit() calling activate() outside decisionlog::defer();
     * the marker kept as a flag rather than a count, so activate()'s inner defer ending clears
     * admit()'s.
     *
     * @return void
     */
    public function test_a_key_admission_logs_no_approval(): void {
        global $DB;

        $id = $this->plugin->add_instance($this->course, [
            'customchar1' => \enrol_apply\local\groupkey::MODE_ANY,
            'roleid' => $this->role('student'),
        ] + $this->plugin->get_instance_defaults());
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'Keyed',
            'enrolmentkey' => self::KEY,
        ]);
        $applicant = $this->getDataGenerator()->create_user();
        $this->setAdminUser();
        $sink = $this->redirectEvents();

        $result = $this->plugin->submit_application($instance, (int) $applicant->id, (object) [
            'applydescription' => '',
            'groupkey' => self::KEY,
        ]);

        $this->assertTrue($result->was_admitted());
        $this->assertCount(1, $this->events_of($sink, application_admitted_by_key::class));
        $this->assertCount(0, $this->events_of($sink, application_approved::class));
    }

    /**
     * Deferring logs on a move and on a message, and not when only the note was corrected.
     *
     * Changes that must make it fail: the deferral event fired outside the notification's rule;
     * moved always reported true.
     *
     * @return void
     */
    public function test_a_deferral_logs_a_move_and_a_message_but_not_a_silent_correction(): void {
        [$applicant, $ueid] = $this->application();
        $this->setUser($this->teacher);
        $sink = $this->redirectEvents();

        // The move.
        $this->plugin->wait_enrolment([$ueid], '', ['note' => 'First reason']);
        $deferred = $this->events_of($sink, application_deferred::class);
        $this->assertCount(1, $deferred);
        $this->assertTrue($deferred[0]->other['moved']);
        $this->assertFalse($deferred[0]->other['withmessage']);
        $this->assertSame($this->record_id($ueid), (int) $deferred[0]->objectid);
        $this->assertSame((int) $applicant->id, (int) $deferred[0]->relateduserid);
        $this->assertSame((int) $this->teacher->id, (int) $deferred[0]->userid);
        $sink->clear();

        // A silent correction of the note: no decision, no line.
        $this->assertSame(1, $this->plugin->wait_enrolment([$ueid], '', ['note' => 'Second reason']));
        $this->assertCount(0, $this->events_of($sink, application_deferred::class));

        // A message about the same deferral is logged, as not moved.
        $this->plugin->wait_enrolment([$ueid], 'Still waiting for a place');
        $deferred = $this->events_of($sink, application_deferred::class);
        $this->assertCount(1, $deferred);
        $this->assertFalse($deferred[0]->other['moved']);
        $this->assertTrue($deferred[0]->other['withmessage']);
        $this->assertStringNotContainsString('Still waiting', json_encode($deferred[0]->other));
    }

    /**
     * Each application cancelled logs once, after the unenrolment, against a record that survives.
     *
     * The approved applicant in the batch is the control: a row not awaiting a decision is
     * skipped, and logs nothing.
     *
     * Changes that must make it fail: the cancellation event dropped.
     *
     * @return void
     */
    public function test_each_cancellation_logs_once(): void {
        global $DB;

        [$first, $firstueid] = $this->application();
        [, $secondueid] = $this->application();
        [, $approvedueid] = $this->application();
        $this->setUser($this->teacher);
        $this->plugin->confirm_enrolment([$approvedueid]);
        $sink = $this->redirectEvents();

        $this->assertSame(2, $this->plugin->cancel_enrolment([$firstueid, $secondueid, $approvedueid]));

        $cancelled = $this->events_of($sink, application_cancelled::class);
        $this->assertCount(2, $cancelled);
        $this->assertSame((int) $first->id, (int) $cancelled[0]->relateduserid);
        $this->assertSame($this->record_id($firstueid), (int) $cancelled[0]->objectid);
        $this->assertSame((int) $this->teacher->id, (int) $cancelled[0]->userid);
        $this->assertFalse($DB->record_exists('user_enrolments', ['id' => $firstueid]));
        $this->assertGreaterThan(
            $this->position_of($sink, \core\event\user_enrolment_deleted::class),
            $this->position_of($sink, application_cancelled::class)
        );
    }

    /**
     * A write that throws inside activate() leaves no marker: the next approval of the same
     * application, in the same request, is logged.
     *
     * Changes that must make it fail: decisionlog::defer() undoing its count outside a finally.
     *
     * @return void
     */
    public function test_an_approval_after_a_failed_write_still_logs(): void {
        [, $ueid] = $this->application();
        $this->setUser($this->teacher);
        $failing = new class extends \enrol_apply_plugin {
            /**
             * Fail as a database write might.
             *
             * @param \stdClass $instance Course enrol instance.
             * @param int $userid User id.
             * @param int|null $status New status.
             * @param int|null $timestart New start.
             * @param int|null $timeend New end.
             * @return void
             * @throws \moodle_exception Always.
             */
            public function update_user_enrol(\stdClass $instance, $userid, $status = null, $timestart = null, $timeend = null) {
                throw new \moodle_exception('error');
            }
        };
        $sink = $this->redirectEvents();

        try {
            $failing->confirm_enrolment([$ueid]);
            $this->fail('the failure did not reach the caller');
        } catch (\moodle_exception $e) {
            $this->assertSame('error', $e->errorcode);
        }
        $this->assertFalse(decisionlog::is_owed($ueid));
        $this->assertCount(0, $this->events_of($sink, application_approved::class));

        $this->plugin->confirm_enrolment([$ueid]);

        $this->assertCount(1, $this->events_of($sink, application_approved::class));
    }

    /**
     * The marker counts nested defers and is gone after an exception.
     *
     * @return void
     */
    public function test_the_marker_counts_nested_defers_and_clears_after_a_throw(): void {
        decisionlog::defer(7, function (): void {
            decisionlog::defer(7, fn() => null);
            $this->assertTrue(decisionlog::is_owed(7), 'the inner defer ending cleared the outer one');
        });
        $this->assertFalse(decisionlog::is_owed(7));

        try {
            decisionlog::defer(7, function (): void {
                throw new \coding_exception('boom');
            });
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('boom', $e->getMessage());
        }
        $this->assertFalse(decisionlog::is_owed(7));
        $this->assertFalse(decisionlog::is_owed(8));
    }

    /**
     * An application with no durable record logs neither an object table nor an object id.
     *
     * Changes that must make it fail: the object table kept with no id.
     *
     * @return void
     */
    public function test_an_application_without_a_record_logs_neither_table_nor_id(): void {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['enrolid' => $this->instance->id, 'userid' => $applicant->id],
            MUST_EXIST
        );
        $DB->insert_record('enrol_apply_applicationinfo', (object) ['userenrolmentid' => $ueid, 'comment' => '']);
        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['userenrolmentid' => $ueid]));
        $this->setUser($this->teacher);
        // Core's Edit enrolment route logs from an observer, which an event sink would silence.
        $this->enable_the_log();

        $this->plugin->update_user_enrol($this->instance, $applicant->id, ENROL_USER_ACTIVE);

        $approved = $this->logged(application_approved::class);
        $this->assertCount(1, $approved);
        $this->assertNull($approved[0]->objecttable);
        $this->assertNull($approved[0]->objectid);
        $this->assertDebuggingNotCalled();
    }

    /**
     * The descriptions name the decider, the applicant and the method, and the links lead to the
     * queue or, for a deferral, to the review page.
     *
     * @return void
     */
    public function test_descriptions_and_urls(): void {
        [$applicant, $ueid] = $this->application();
        [, $cancelueid] = $this->application();
        $this->setUser($this->teacher);
        $sink = $this->redirectEvents();

        $this->plugin->wait_enrolment([$ueid]);
        $this->plugin->confirm_enrolment([$ueid]);
        $this->plugin->cancel_enrolment([$cancelueid]);

        $deferred = $this->events_of($sink, application_deferred::class)[0];
        $approved = $this->events_of($sink, application_approved::class)[0];
        $cancelled = $this->events_of($sink, application_cancelled::class)[0];

        foreach ([$deferred, $approved, $cancelled] as $event) {
            $description = $event->get_description();
            $this->assertStringContainsString("'{$this->teacher->id}'", $description);
            $this->assertStringContainsString("'{$this->instance->id}'", $description);
            $this->assertNotEmpty($event::get_name());
        }
        $this->assertStringContainsString("'{$applicant->id}'", $approved->get_description());
        $this->assertStringContainsString("'{$this->listed->id}'", $approved->get_description());
        $this->assertStringContainsString("'{$this->role('student')}'", $approved->get_description());
        $this->assertStringContainsString('deferred', $deferred->get_description());
        $this->assertStringContainsString('cancelled', $cancelled->get_description());

        $this->assertStringEndsWith('/enrol/apply/manage.php', $approved->get_url()->get_path(false));
        $this->assertEquals($this->instance->id, $approved->get_url()->get_param('id'));
        $this->assertEquals($this->instance->id, $cancelled->get_url()->get_param('id'));
        $this->assertEquals($ueid, $deferred->get_url()->get_param('userenrol'));
    }

    /**
     * Every id the events carry in other is declared for the log restore; the flags are not ids.
     *
     * Changes that must make it fail: the groups array mapped as a single group.
     *
     * @return void
     */
    public function test_every_id_in_other_is_declared_for_the_restore(): void {
        [, $ueid] = $this->application();
        [, $cancelueid] = $this->application();
        $this->setUser($this->teacher);
        $sink = $this->redirectEvents();
        $this->plugin->wait_enrolment([$ueid]);
        $this->plugin->confirm_enrolment([$ueid]);
        $this->plugin->cancel_enrolment([$cancelueid]);

        $flags = ['chosen', 'moved', 'withmessage'];
        foreach ([application_approved::class, application_deferred::class, application_cancelled::class] as $class) {
            $events = $this->events_of($sink, $class);
            $this->assertCount(1, $events, $class);
            $mapping = $class::get_other_mapping();
            $ids = array_diff(array_keys($events[0]->other), $flags);
            $this->assertEqualsCanonicalizing($ids, array_keys($mapping), $class);
            $this->assertSame(
                ['db' => 'enrol_apply_submission', 'restore' => 'enrol_apply_submission'],
                $class::get_objectid_mapping()
            );
        }

        $mapping = application_approved::get_other_mapping();
        $this->assertSame(\core\event\base::NOT_MAPPED, $mapping['groups']['restore']);
        $this->assertSame('role', $mapping['roleid']['restore']);
        $this->assertSame('group', $mapping['keygroupid']['restore']);
    }

    /**
     * An event missing one of its keys is refused.
     *
     * @return void
     */
    public function test_an_approval_without_its_groups_is_refused(): void {
        $this->expectException(\coding_exception::class);

        application_approved::create([
            'context' => \context_course::instance($this->course->id),
            'relateduserid' => (int) $this->teacher->id,
            'other' => ['enrolid' => 1, 'userenrolmentid' => 1, 'keygroupid' => 0, 'roleid' => 0, 'chosen' => false],
        ]);
    }
}
