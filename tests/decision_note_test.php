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
 * Tests for the note a decider records about a decision.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * Tests for the note a decider records about a decision.
 *
 * The note is the outcome message's twin in shape and its opposite in audience, so every test
 * here that asserts it was stored also asserts it did NOT reach the applicant. Without that
 * half the whole set would pass against an implementation that simply appended the note to the
 * message, which is the one failure that would matter.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
#[CoversClass(submission::class)]
final class decision_note_test extends \advanced_testcase {
    /** @var \stdClass Course the apply instance belongs to. */
    protected $course;

    /** @var \stdClass The enrol_apply instance. */
    protected $instance;

    /** @var \enrol_apply_plugin The plugin. */
    protected $plugin;

    /**
     * Enable the plugin and give it a course with an instance.
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
     * Submit an application through the real path, so it leaves a durable record.
     *
     * @return array The applicant and their user enrolment id.
     */
    protected function apply(): array {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $this->setUser($applicant);

        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);
        $method->invoke($this->plugin, $this->instance, $applicant->id, (object) ['applydescription' => 'Please']);
        $sink->close();

        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        return [$applicant, $ueid];
    }

    /**
     * Enrol somebody as a pending applicant WITHOUT writing a durable record.
     *
     * This is the shape of every application older than the enrol_apply_submission table, and
     * of the ones core's own routes produce. It is what submission::ensure() exists for.
     *
     * @param string $comment Comment on the application info row, empty to write no row at all.
     * @return array The applicant and their user enrolment id.
     */
    protected function apply_without_a_record(string $comment = 'Please let me in'): array {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        if ($comment !== '') {
            $DB->insert_record('enrol_apply_applicationinfo', (object) [
                'userenrolmentid' => $ueid,
                'comment' => $comment,
            ]);
        }

        return [$applicant, $ueid];
    }

    /**
     * The single durable record of one applicant, failing when there is not exactly one.
     *
     * @param \stdClass $applicant The applicant.
     * @return \stdClass The record.
     */
    protected function record(\stdClass $applicant): \stdClass {
        global $DB;

        $rows = $DB->get_records('enrol_apply_submission', [
            'courseid' => $this->course->id,
            'userid' => $applicant->id,
        ]);
        $this->assertCount(1, $rows);

        return reset($rows);
    }

    /**
     * Everything sent to the applicant while the callback ran, subject and body alike.
     *
     * The adhoc queue is drained because approval does not notify synchronously:
     * complete_approval() queues a task and the message is only built when it runs.
     *
     * @param \stdClass $applicant Recipient to filter on.
     * @param callable $decide What to run while the sink is open.
     * @return string Everything that reached them, concatenated.
     */
    protected function everything_sent_to(\stdClass $applicant, callable $decide): string {
        $sink = $this->redirectMessages();
        $decide();

        while ($task = \core\task\manager::get_next_adhoc_task(time() + 1)) {
            ob_start();
            $task->execute();
            ob_end_clean();
            \core\task\manager::adhoc_task_complete($task);
        }

        $messages = $sink->get_messages();
        $sink->close();

        $parts = [];
        foreach ($messages as $message) {
            if ((int) $message->useridto !== (int) $applicant->id) {
                continue;
            }
            $parts[] = (string) $message->subject;
            $parts[] = (string) $message->fullmessage;
            $parts[] = (string) $message->fullmessagehtml;
            $parts[] = (string) ($message->smallmessage ?? '');
        }

        return implode(' ', $parts);
    }

    /**
     * Approving with a note records it and sends the applicant none of it.
     *
     * The message is written in the same call and IS delivered, which is the control: without
     * it this would pass against a decision path that had stopped notifying altogether.
     *
     * @return void
     */
    public function test_approving_records_the_note_and_keeps_it_from_the_applicant(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        // The control: it really is empty before the decision.
        $this->assertSame('', (string) $this->record($applicant)->decisionnote);

        $sent = $this->everything_sent_to($applicant, function () use ($ueid): void {
            $this->plugin->confirm_enrolment([$ueid], 'See you Monday.', [
                'note' => 'Registrar confirmed the transcript by phone.',
            ]);
        });

        $this->assertSame(
            'Registrar confirmed the transcript by phone.',
            (string) $this->record($applicant)->decisionnote
        );
        $this->assertStringNotContainsString('Registrar confirmed', $sent);
        $this->assertStringContainsString('See you Monday.', $sent);
    }

    /**
     * Deferring with a note records it and sends the applicant none of it.
     *
     * A test of its own rather than a provider case: this path notifies synchronously inside
     * the decision loop while approval notifies from a queued task, so the two fail differently.
     *
     * @return void
     */
    public function test_deferring_records_the_note_and_keeps_it_from_the_applicant(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $sent = $this->everything_sent_to($applicant, function () use ($ueid): void {
            $this->plugin->wait_enrolment([$ueid], 'You are third on the list.', [
                'note' => 'Holding for the September intake.',
            ]);
        });

        $this->assertSame('Holding for the September intake.', (string) $this->record($applicant)->decisionnote);
        $this->assertStringNotContainsString('September intake', $sent);
        $this->assertStringContainsString('You are third on the list.', $sent);
    }

    /**
     * Cancelling with a note records it on a record that outlives the enrolment.
     *
     * The write has to happen before unenrol_user(), which deletes the {user_enrolments} row
     * the record is matched on - the same ordering the decision itself needs.
     *
     * @return void
     */
    public function test_cancelling_records_the_note_and_keeps_it_from_the_applicant(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $sent = $this->everything_sent_to($applicant, function () use ($ueid): void {
            $this->plugin->cancel_enrolment([$ueid], 'The cohort is full this term.', [
                'note' => 'Duplicate of the application decided last week.',
            ]);
        });

        $this->assertSame(
            'Duplicate of the application decided last week.',
            (string) $this->record($applicant)->decisionnote
        );
        $this->assertStringNotContainsString('Duplicate of the application', $sent);
        $this->assertStringContainsString('The cohort is full this term.', $sent);
    }

    /**
     * A later decision with the box left empty CLEARS the earlier note.
     *
     * The note belongs to the decision being taken. Without this a re-queued application - which
     * core's "Edit enrolment" screen and an expiredaction of suspend both produce - would be
     * decided a second time carrying the first decision's reason, with nothing on screen saying
     * so. It is the same defect the outcome message was fixed for.
     *
     * @return void
     */
    public function test_a_later_decision_clears_an_earlier_note(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $sink = $this->redirectMessages();
        $this->plugin->wait_enrolment([$ueid], '', ['note' => 'Waiting for a place.']);
        $sink->close();
        // The control: it really was stored, so the assertion below is about clearing.
        $this->assertSame('Waiting for a place.', (string) $this->record($applicant)->decisionnote);

        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid], '', ['note' => '']);
        $sink->close();

        $this->assertSame('', (string) $this->record($applicant)->decisionnote);
    }

    /**
     * Whitespace alone is not a note, and does not survive as one.
     *
     * The fixture writes a real note first on purpose. Without that, an implementation that
     * simply refused to write blank input would pass - the column starts empty - and the
     * assertion would be about nothing. With it, the single assertion carries both properties
     * the trim keeps apart: whitespace is not stored AS a note, and a blank decision still
     * clears the one before it.
     *
     * @return void
     */
    public function test_whitespace_alone_is_not_a_note(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $sink = $this->redirectMessages();
        $this->plugin->wait_enrolment([$ueid], '', ['note' => 'Waiting for a place.']);
        // The control: it really was stored, so what follows is about replacing it.
        $this->assertSame('Waiting for a place.', (string) $this->record($applicant)->decisionnote);

        $this->plugin->wait_enrolment([$ueid], '', ['note' => "   \n  "]);
        $sink->close();

        $this->assertSame('', (string) $this->record($applicant)->decisionnote);
    }

    /**
     * A caller that says nothing about the note leaves the stored one alone.
     *
     * The gate is array_key_exists and not a test for emptiness, which is what makes the two
     * cases expressible at all: an empty note SUBMITTED clears, while a note not submitted is
     * not a decision about the note. The out-of-band approval route carries no operator input
     * and reaches this branch.
     *
     * @return void
     */
    public function test_a_caller_that_says_nothing_about_the_note_leaves_it_alone(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $sink = $this->redirectMessages();
        $this->plugin->wait_enrolment([$ueid], '', ['note' => 'Waiting for a place.']);
        $sink->close();

        // No decision array at all, which is what every caller predating the note passes.
        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid]);
        $sink->close();

        $this->assertSame('Waiting for a place.', (string) $this->record($applicant)->decisionnote);
    }

    /**
     * An application already deferred can have its note edited.
     *
     * wait_enrolment() used to look its row up with a strict status = suspended predicate, so a
     * second deferral found nothing, did nothing, and reported success. That made the reason on
     * a deferred application - the state this plugin's model spends the longest in - impossible
     * to correct.
     *
     * @return void
     */
    public function test_a_deferred_application_can_have_its_note_edited(): void {
        global $DB;

        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $sink = $this->redirectMessages();
        $this->plugin->wait_enrolment([$ueid], '', ['note' => 'Waiting for a place.']);
        $this->plugin->wait_enrolment([$ueid], '', ['note' => 'Waiting for the transcript.']);
        $sink->close();

        $this->assertSame('Waiting for the transcript.', (string) $this->record($applicant)->decisionnote);
        // Still deferred: editing the reason is not a second decision about the enrolment.
        $this->assertEquals(
            ENROL_APPLY_USER_WAIT,
            (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid], MUST_EXIST)
        );
    }

    /**
     * Correcting the reason on a deferred application does not re-mail the applicant.
     *
     * Widening the lookup is what makes the correction possible, and it is also what made this
     * defect possible: the row is now found, so everything the first deferral did runs again -
     * including the "your application was deferred" notification, which is news the applicant
     * already had. The control is the first deferral in the same run, which MUST notify.
     *
     * @return void
     */
    public function test_correcting_the_reason_does_not_notify_the_applicant_again(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        // The control: the first deferral really does reach them.
        $first = $this->everything_sent_to($applicant, function () use ($ueid): void {
            $this->plugin->wait_enrolment([$ueid], '', ['note' => 'Waiting for a place.']);
        });
        $this->assertNotSame('', trim($first));

        $second = $this->everything_sent_to($applicant, function () use ($ueid): void {
            $this->plugin->wait_enrolment([$ueid], '', ['note' => 'Waiting for the transcript.']);
        });

        $this->assertSame('', trim($second));
        // And the correction landed, so this is not passing because nothing happened at all.
        $this->assertSame('Waiting for the transcript.', (string) $this->record($applicant)->decisionnote);
    }

    /**
     * A message typed for the applicant IS sent, even when the enrolment does not move.
     *
     * The other half of the rule above, and the half that keeps it from becoming a silent loss:
     * the message box exists to reach the applicant, so a message written and then not sent
     * would be the defect class this plugin has already fixed twice.
     *
     * @return void
     */
    public function test_a_message_typed_on_a_second_deferral_still_reaches_the_applicant(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $sink = $this->redirectMessages();
        $this->plugin->wait_enrolment([$ueid], '', ['note' => 'Waiting for a place.']);
        $sink->close();

        $sent = $this->everything_sent_to($applicant, function () use ($ueid): void {
            $this->plugin->wait_enrolment([$ueid], 'The second intake opens in September.', [
                'note' => 'Told them about the September intake.',
            ]);
        });

        $this->assertStringContainsString('The second intake opens in September.', $sent);
        $this->assertStringNotContainsString('Told them about the September intake.', $sent);
    }

    /**
     * Correcting the reason does not re-attribute the decision to whoever corrected it.
     *
     * decide()'s guard already refuses to restamp a row at the target status; passing the fresh
     * flag unconditionally overrode it, so the trail credited the deferral to the person who had
     * only edited its note - the exact failure that flag was introduced to make possible in the
     * one case where the enrolment really does move.
     *
     * @return void
     */
    public function test_correcting_the_reason_leaves_the_original_decider_on_the_record(): void {
        [$applicant, $ueid] = $this->apply();

        $first = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($first->id, $this->course->id, 'editingteacher');
        $second = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($second->id, $this->course->id, 'editingteacher');

        $this->setUser($first);
        $sink = $this->redirectMessages();
        $this->plugin->wait_enrolment([$ueid], '', ['note' => 'Waiting for a place.']);
        $sink->close();

        $stamped = $this->record($applicant);
        $this->assertEquals($first->id, (int) $stamped->decidedby);

        $this->setUser($second);
        $sink = $this->redirectMessages();
        $this->plugin->wait_enrolment([$ueid], '', ['note' => 'Waiting for the transcript.']);
        $sink->close();

        $after = $this->record($applicant);
        $this->assertEquals($first->id, (int) $after->decidedby, 'the deferral belongs to whoever took it');
        $this->assertEquals((int) $stamped->timedecided, (int) $after->timedecided);
        // The control: the correction itself did land.
        $this->assertSame('Waiting for the transcript.', (string) $after->decisionnote);
    }

    /**
     * A first deferral still stamps the decider, which is what the guard above must not break.
     *
     * @return void
     */
    public function test_a_first_deferral_still_stamps_who_took_it(): void {
        [$applicant, $ueid] = $this->apply();

        $decider = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($decider->id, $this->course->id, 'editingteacher');
        $this->setUser($decider);

        $sink = $this->redirectMessages();
        $this->plugin->wait_enrolment([$ueid], '', ['note' => 'Waiting for a place.']);
        $sink->close();

        $row = $this->record($applicant);
        $this->assertEquals($decider->id, (int) $row->decidedby);
        $this->assertGreaterThan(0, (int) $row->timedecided);
    }

    /**
     * An application with no durable record gets one, and its message and note are kept.
     *
     * The live defect submission::ensure() closes. record_outcome_message() loops over the rows
     * it finds and, with none, writes nothing and says nothing - so an application older than
     * this table took a decision whose message was stored nowhere AND mailed nowhere. Both halves
     * are asserted, because a fix that wrote the record but still lost the message would pass
     * against the first alone.
     *
     * @return void
     */
    public function test_an_application_with_no_record_still_stores_its_message_and_note(): void {
        global $DB;

        [$applicant, $ueid] = $this->apply_without_a_record();
        $this->setAdminUser();

        // The control: there really is no record to write to before the decision.
        $this->assertSame(0, $DB->count_records('enrol_apply_submission', ['userid' => $applicant->id]));

        $sent = $this->everything_sent_to($applicant, function () use ($ueid): void {
            $this->plugin->wait_enrolment([$ueid], 'You are next in line.', [
                'note' => 'Reconstructed record; the application predates the trail.',
            ]);
        });

        $row = $this->record($applicant);
        $this->assertSame('You are next in line.', (string) $row->outcomemessage);
        $this->assertSame('Reconstructed record; the application predates the trail.', (string) $row->decisionnote);
        $this->assertEquals(submission::STATUS_WAITING, (int) $row->status);
        $this->assertEquals(get_admin()->id, (int) $row->decidedby);
        $this->assertStringContainsString('You are next in line.', $sent);
        $this->assertStringNotContainsString('Reconstructed record', $sent);
    }

    /**
     * The reconstructed record carries the application's own comment and date, not this moment.
     *
     * timecreated is what prior_applications() orders by and what the retention sweep filters
     * on, so stamping the reconstruction's own time would move an old application to the top of
     * a list and postpone its purge.
     *
     * @return void
     */
    public function test_a_reconstructed_record_keeps_the_applications_comment_and_date(): void {
        global $DB;

        [$applicant, $ueid] = $this->apply_without_a_record('I would like to join.');
        $DB->set_field('user_enrolments', 'timecreated', 1500000000, ['id' => $ueid]);

        submission::ensure($ueid);

        $row = $this->record($applicant);
        $this->assertSame('I would like to join.', (string) $row->comment);
        $this->assertEquals(1500000000, (int) $row->timecreated);
        $this->assertEquals($this->course->id, (int) $row->courseid);
        $this->assertEquals($this->instance->id, (int) $row->enrolid);
        $this->assertEquals($ueid, (int) $row->userenrolmentid);
        // Reconstructed as undecided: the caller stamps the real decision straight afterwards.
        $this->assertEquals(submission::STATUS_PENDING, (int) $row->status);
        $this->assertEquals(0, (int) $row->timedecided);
        $this->assertEquals(0, (int) $row->decidedby);
    }

    /**
     * An application that already has a record gets no second one.
     *
     * The state machine produces several records per course and user on purpose - cancelling
     * and re-applying is the ordinary way - so a duplicate here would be invisible to every
     * count and would double the applicant's own privacy export.
     *
     * @return void
     */
    public function test_ensure_leaves_an_existing_record_alone(): void {
        global $DB;

        [$applicant, $ueid] = $this->apply();
        $before = $this->record($applicant);

        submission::ensure($ueid);
        submission::ensure($ueid);

        $this->assertSame(1, $DB->count_records('enrol_apply_submission', ['userid' => $applicant->id]));
        $this->assertEquals($before->id, (int) $this->record($applicant)->id);
    }

    /**
     * An enrolment this plugin does not own writes nothing, and neither does one that is gone.
     *
     * get_pending_user_enrolment() carries no enrol-type predicate, so a foreign user enrolment
     * id reaches the decision methods and therefore reaches here, ahead of the MUST_EXIST lookup
     * that refuses it. Writing a record for it would put an enrol_apply trail on somebody else's
     * enrolment.
     *
     * @return void
     */
    public function test_ensure_ignores_an_enrolment_this_plugin_does_not_own(): void {
        global $DB;

        $outsider = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($outsider->id, $this->course->id);
        $manualid = (int) $DB->get_field_sql(
            "SELECT ue.id
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :userid AND e.enrol = :enrol",
            ['userid' => $outsider->id, 'enrol' => 'manual']
        );
        $this->assertGreaterThan(0, $manualid);

        submission::ensure($manualid);
        // And an id that names nothing at all.
        submission::ensure($manualid + 100000);

        $this->assertSame(0, $DB->count_records('enrol_apply_submission'));
    }

    /**
     * Each decision reports how many of the given applications it actually decided.
     *
     * manage.php prints "Applications updated" from this number, and before it existed the
     * message was printed for a post that had changed nothing at all - every decision method
     * skips a row it will not act on, and skips it in silence.
     *
     * @return void
     */
    public function test_a_decision_reports_how_many_it_decided(): void {
        [, $first] = $this->apply();
        [, $second] = $this->apply();
        $this->setAdminUser();

        $sink = $this->redirectMessages();
        $decided = $this->plugin->wait_enrolment([$first, $second], '', ['note' => 'Both on hold.']);
        $sink->close();

        $this->assertSame(2, $decided);
    }

    /**
     * A decision that reaches nothing reports nothing, and that is not the same as failing.
     *
     * An id whose application has already been approved is no longer awaiting a decision, so
     * every method skips it. The count is what lets the page say so instead of claiming success.
     *
     * @return void
     */
    public function test_a_decision_that_reaches_nothing_reports_nothing(): void {
        [, $ueid] = $this->apply();
        $this->setAdminUser();

        $sink = $this->redirectMessages();
        // The control: the first decision really did land, so the second one is the no-op.
        $this->assertSame(1, $this->plugin->confirm_enrolment([$ueid]));
        while ($task = \core\task\manager::get_next_adhoc_task(time() + 1)) {
            ob_start();
            $task->execute();
            ob_end_clean();
            \core\task\manager::adhoc_task_complete($task);
        }
        $this->assertSame(0, $this->plugin->wait_enrolment([$ueid], '', ['note' => 'Too late.']));
        $this->assertSame(0, $this->plugin->cancel_enrolment([$ueid]));
        $sink->close();
    }
}
