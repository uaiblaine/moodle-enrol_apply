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
 * Tests for the message a decider writes to the applicant.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the message a decider writes to the applicant.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
#[CoversClass(submission::class)]
final class outcome_message_test extends \advanced_testcase {
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
     * create_application() in lib_test bypasses apply() and writes no enrol_apply_submission
     * row, so the record would not exist before the decision.
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
     * The durable record for an applicant.
     *
     * @param \stdClass $applicant The applicant.
     * @return \stdClass The record.
     */
    protected function record(\stdClass $applicant): \stdClass {
        global $DB;

        return $DB->get_record(
            'enrol_apply_submission',
            ['courseid' => $this->course->id, 'userid' => $applicant->id],
            '*',
            MUST_EXIST
        );
    }

    /**
     * The bodies of every message sent to the applicant during the callback.
     *
     * Each body is the HTML half followed by the plain-text half, unless only the HTML half is
     * asked for: the plain half is derived from it by html_to_text(), which turns escaped markup
     * back into literal text, so an assertion that no markup reached the message reads the HTML
     * half alone.
     *
     * @param \stdClass $applicant Recipient to filter on.
     * @param callable $decide What to run while the sink is open.
     * @param bool $htmlonly Whether to return only the HTML half of each body.
     * @return array List of message bodies.
     */
    protected function bodies_of(\stdClass $applicant, callable $decide, bool $htmlonly = false): array {
        $sink = $this->redirectMessages();
        $decide();

        /* Approval notifies from \enrol_apply\task\notify_approval, queued by
           complete_approval(), so the queue is drained here. Deferral and cancellation notify
           inside the decision loop and leave nothing to drain. */
        while ($task = \core\task\manager::get_next_adhoc_task(time() + 1)) {
            ob_start();
            $task->execute();
            ob_end_clean();
            \core\task\manager::adhoc_task_complete($task);
        }

        $messages = $sink->get_messages();
        $sink->close();

        $bodies = [];
        foreach ($messages as $message) {
            if ((int) $message->useridto === (int) $applicant->id) {
                $bodies[] = $htmlonly
                    ? (string) $message->fullmessagehtml
                    : (string) $message->fullmessagehtml . ' ' . (string) $message->fullmessage;
            }
        }

        return $bodies;
    }

    /**
     * Approving with a message stores it and delivers it to the applicant.
     *
     * complete_approval() runs twice for a queue approval: update_user_enrol() dispatches its
     * hook before writing the row, so hook_callbacks reaches it first, carrying no operator
     * input. The message is therefore recorded before the status changes, and the adhoc task
     * that notifies the applicant reads it back off the record.
     *
     * @return void
     */
    public function test_approving_with_a_message_stores_and_delivers_it(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        // The control: it really is empty before the decision.
        $this->assertSame('', (string) $this->record($applicant)->outcomemessage);

        $bodies = $this->bodies_of($applicant, function () use ($ueid): void {
            $this->plugin->confirm_enrolment([$ueid], 'Welcome aboard, see you Monday.');
        });

        $this->assertSame('Welcome aboard, see you Monday.', (string) $this->record($applicant)->outcomemessage);
        $this->assertNotEmpty($bodies);
        $this->assertStringContainsString('Welcome aboard, see you Monday.', implode(' ', $bodies));
    }

    /**
     * Deferring with a message delivers it too.
     *
     * A separate test rather than a data provider case: this path notifies synchronously inside
     * the decision loop, while approval notifies from an adhoc task queued by
     * complete_approval(). They fail differently and the ordering that keeps them working is not
     * the same.
     *
     * @return void
     */
    public function test_deferring_with_a_message_delivers_it(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $bodies = $this->bodies_of($applicant, function () use ($ueid): void {
            $this->plugin->wait_enrolment([$ueid], 'You are third on the list.');
        });

        $this->assertSame('You are third on the list.', (string) $this->record($applicant)->outcomemessage);
        $this->assertStringContainsString('You are third on the list.', implode(' ', $bodies));
    }

    /**
     * Cancelling with a message delivers it, and the record survives to hold it.
     *
     * Cancellation unenrols, deleting the {user_enrolments} row. The durable record outlives it,
     * still carrying that userenrolmentid, and the notification sent after the unenrolment reads
     * the message from it.
     *
     * @return void
     */
    public function test_cancelling_with_a_message_delivers_it_and_keeps_the_record(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $bodies = $this->bodies_of($applicant, function () use ($ueid): void {
            $this->plugin->cancel_enrolment([$ueid], 'The cohort is full this term.');
        });

        $this->assertSame('The cohort is full this term.', (string) $this->record($applicant)->outcomemessage);
        $this->assertStringContainsString('The cohort is full this term.', implode(' ', $bodies));
    }

    /**
     * With no message typed, the applicant gets the standard wording and nothing more.
     *
     * The control that stops the delivery tests passing against a decision path that simply
     * appends everything it is given: here the record must stay empty and the body must not
     * grow a stray separator.
     *
     * @return void
     */
    public function test_no_message_leaves_the_standard_wording_alone(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $bodies = $this->bodies_of($applicant, function () use ($ueid): void {
            $this->plugin->confirm_enrolment([$ueid]);
        });

        $this->assertSame('', (string) $this->record($applicant)->outcomemessage);
        $this->assertNotEmpty($bodies);
        $this->assertStringNotContainsString('<br><br>', implode(' ', $bodies));
    }

    /**
     * A second approval after a manual suspension is recorded as a new decision.
     *
     * A re-approved application never leaves STATUS_APPROVED, so without $isfreshdecision
     * decide()'s same-status skip would keep naming the first decider while the applicant is
     * notified of the second approval. The skip itself stays for a bare decide() call, which
     * test_a_recorded_decision_is_not_restamped pins; that test is the control showing the
     * exception is narrow.
     *
     * Changes that must make it fail: decide() ignoring $isfreshdecision (the control stays
     * green).
     *
     * @return void
     */
    public function test_a_second_approval_is_recorded_as_a_new_decision(): void {
        global $DB;

        [$applicant, $ueid] = $this->apply();

        $first = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($first->id, $this->course->id, 'editingteacher');
        $this->setUser($first);
        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid]);
        $sink->close();

        $before = $this->record($applicant);
        $this->assertEquals($first->id, (int) $before->decidedby);

        /* Back into the queue the way core's participants page puts it there, which leaves the
           record at STATUS_APPROVED. */
        $this->plugin->update_user_enrol($this->instance, (int) $applicant->id, ENROL_USER_SUSPENDED);
        $DB->set_field('enrol_apply_submission', 'timedecided', 111, ['id' => $before->id]);

        $second = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($second->id, $this->course->id, 'editingteacher');
        $this->setUser($second);
        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid]);
        $sink->close();

        $after = $this->record($applicant);
        $this->assertEquals($second->id, (int) $after->decidedby, 'the trail names whoever decided last');
        $this->assertGreaterThan(111, (int) $after->timedecided, 'and when they decided');
    }

    /**
     * A later approval clears the group choice an earlier one recorded.
     *
     * An empty choice is written, and reads back as "no choice recorded", which puts the
     * instance's own list in charge. Were it skipped, re-approving a re-suspended application
     * would re-join the groups picked for the earlier decision.
     *
     * Changes that must make it fail: record_decided_groups() returning early on an empty list.
     *
     * @return void
     */
    public function test_a_later_approval_clears_an_earlier_group_choice(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);

        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid], '', ['groups' => [(int) $group->id]]);
        $sink->close();
        $this->assertSame(
            (string) $group->id,
            (string) $this->record($applicant)->decidedgroups,
            'the premise: the first decision really did record a group'
        );

        $this->plugin->update_user_enrol($this->instance, (int) $applicant->id, ENROL_USER_SUSPENDED);

        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid], '', ['groups' => []]);
        $sink->close();

        $this->assertSame('', (string) $this->record($applicant)->decidedgroups);
    }

    /**
     * A stored group list that names no usable group falls back to the instance's own.
     *
     * Neither record_decided_groups() nor the restore writes a value such as "0" or a lone
     * comma, but chosen_groups() must still read one as null: add_instance_groups() sends
     * anything else to get_in_or_equal(), which throws a coding_exception on an empty array.
     *
     * Changes that must make it fail: chosen_groups() returning the empty array rather than null
     * (the premise assertion fails first).
     *
     * @return void
     */
    public function test_a_group_choice_that_names_nothing_falls_back_to_the_instance(): void {
        global $DB;

        [$applicant, $ueid] = $this->apply();
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $DB->insert_record('enrol_apply_groups', (object) [
            'enrolid' => $this->instance->id,
            'groupid' => $group->id,
        ]);

        // Written past record_decided_groups() on purpose: the writer cannot produce this value.
        $DB->set_field(
            'enrol_apply_submission',
            'decidedgroups',
            '0',
            ['id' => $this->record($applicant)->id]
        );
        $this->assertNull(
            \enrol_apply\local\submission::chosen_groups($ueid),
            'the premise: a value naming no group reads as no choice recorded'
        );

        $this->setAdminUser();
        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid]);
        $sink->close();

        $this->assertTrue(
            groups_is_member((int) $group->id, (int) $applicant->id),
            'the instance list should have applied, as it does when nothing was recorded'
        );
    }

    /**
     * A later decision clears the message an earlier one recorded.
     *
     * Otherwise the applicant is sent the earlier decision's wording again, attached to a
     * decision nobody wrote it for.
     *
     * Changes that must make it fail: record_outcome_message() returning early on an empty
     * message. test_a_blank_message_is_not_recorded stays green either way; it is about
     * whitespace not being a message, not about clearing.
     *
     * @return void
     */
    public function test_a_later_decision_clears_an_earlier_message(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid], 'Welcome aboard');
        $sink->close();
        $this->assertSame('Welcome aboard', (string) $this->record($applicant)->outcomemessage);

        $this->plugin->update_user_enrol($this->instance, (int) $applicant->id, ENROL_USER_SUSPENDED);

        $bodies = $this->bodies_of($applicant, function () use ($ueid): void {
            $this->plugin->confirm_enrolment([$ueid], '');
        });

        $this->assertSame('', (string) $this->record($applicant)->outcomemessage);
        $this->assertNotEmpty($bodies, 'the second decision still notifies');
        $this->assertStringNotContainsString('Welcome aboard', implode(' ', $bodies));
    }

    /**
     * Whitespace alone is not a message.
     *
     * @return void
     */
    public function test_a_blank_message_is_not_recorded(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $this->plugin->confirm_enrolment([$ueid], "   \n  ");

        $this->assertSame('', (string) $this->record($applicant)->outcomemessage);
    }

    /**
     * The groups the decider chooses replace the instance's list, and do not add to it.
     *
     * An approval taken through the queue completes twice: update_user_enrol() dispatches its
     * hook before writing the row, so hook_callbacks finishes the approval first and
     * confirm_enrolment() finishes it again. Had the chosen list been passed as an argument, the
     * first pass would join the instance's groups and the second the chosen ones, so a group the
     * approver deselected would be joined anyway. The choice is stored and both passes read it.
     *
     * The instance group is what exposes such a union;
     * test_no_chosen_group_leaves_the_instance_list_in_charge is the control that replacing has
     * not become ignoring.
     *
     * @return void
     */
    public function test_a_chosen_group_replaces_the_instance_list(): void {
        global $DB;

        $instancegroup = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $chosengroup = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $DB->insert_record('enrol_apply_groups', (object) [
            'enrolid' => $this->instance->id,
            'groupid' => $instancegroup->id,
        ]);

        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $this->plugin->confirm_enrolment([$ueid], '', ['groups' => [$chosengroup->id]]);

        $this->assertTrue(groups_is_member($chosengroup->id, $applicant->id));
        $this->assertFalse(
            groups_is_member($instancegroup->id, $applicant->id),
            'the deselected instance group was joined anyway, so the two approval passes unioned'
        );
    }

    /**
     * With no choice made, the instance's own group list still applies.
     *
     * The other half of the test above: replacing must not become ignoring.
     *
     * @return void
     */
    public function test_no_chosen_group_leaves_the_instance_list_in_charge(): void {
        global $DB;

        $instancegroup = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $DB->insert_record('enrol_apply_groups', (object) [
            'enrolid' => $this->instance->id,
            'groupid' => $instancegroup->id,
        ]);

        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $this->plugin->confirm_enrolment([$ueid]);

        $this->assertTrue(groups_is_member($instancegroup->id, $applicant->id));
    }

    /**
     * A group from another course never reaches the membership or the durable record.
     *
     * Both halves are asserted because there are two guards. add_instance_groups() re-checks
     * the course before joining, so the membership alone cannot show whether the allowlist in
     * confirm_enrolment() works; that allowlist protects the record, which outlives the
     * enrolment and is read by the privacy export.
     *
     * groups_get_all_groups() is keyed by group id and its values are group records, so the
     * allowlist compares keys.
     *
     * @return void
     */
    public function test_a_group_outside_the_course_reaches_neither_membership_nor_record(): void {
        global $DB;

        $othercourse = $this->getDataGenerator()->create_course();
        $foreign = $this->getDataGenerator()->create_group(['courseid' => $othercourse->id]);
        $mine = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);

        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $this->plugin->confirm_enrolment([$ueid], '', ['groups' => [$foreign->id, $mine->id]]);

        // The control: the legitimate half of the same post was honoured, both times.
        $this->assertTrue(groups_is_member($mine->id, $applicant->id));
        $this->assertFalse(groups_is_member($foreign->id, $applicant->id));

        $recorded = (string) $DB->get_field(
            'enrol_apply_submission',
            'decidedgroups',
            ['courseid' => $this->course->id, 'userid' => $applicant->id],
            MUST_EXIST
        );
        $ids = array_map('intval', array_filter(explode(',', $recorded)));
        $this->assertContains((int) $mine->id, $ids);
        $this->assertNotContains((int) $foreign->id, $ids, 'a foreign group id was written to the audit trail');
    }

    /**
     * The membership carries this plugin's component stamp.
     *
     * Core's unenrol_user() deletes groups_members rows by component and itemid; without the
     * stamp the membership survives every unenrolment where the user holds another enrolment
     * in the course.
     *
     * @return void
     */
    public function test_a_chosen_group_membership_carries_the_component_stamp(): void {
        global $DB;

        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $this->plugin->confirm_enrolment([$ueid], '', ['groups' => [$group->id]]);

        $member = $DB->get_record(
            'groups_members',
            ['groupid' => $group->id, 'userid' => $applicant->id],
            '*',
            MUST_EXIST
        );
        $this->assertSame('enrol_apply', $member->component);
        $this->assertEquals($this->instance->id, (int) $member->itemid);
    }

    /**
     * The enrolment period is the method's, and it is stamped on approval and never before it.
     *
     * The period comes from the instance's enrolperiod, not from the decision. A timeend on a
     * still-pending row would be swept by the ENROL_EXT_REMOVED_UNENROL branch of
     * process_expirations(), which has no status filter, so the applicant would be unenrolled
     * instead of decided.
     *
     * The first assertions are the control: nothing is stamped while the application is pending,
     * so the ones after approval cannot pass by the fixture having carried dates all along.
     *
     * @return void
     */
    public function test_the_methods_period_is_stamped_on_approval_only(): void {
        global $DB;

        [, $ueid] = $this->apply();

        $pending = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertEquals(0, (int) $pending->timestart);
        $this->assertEquals(0, (int) $pending->timeend);

        $this->setAdminUser();
        $before = time();
        $this->plugin->confirm_enrolment([$ueid], '');
        $after = time();

        // A method with no period of its own: access starts now and does not end.
        $approved = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertGreaterThanOrEqual($before, (int) $approved->timestart);
        $this->assertLessThanOrEqual($after, (int) $approved->timestart);
        $this->assertEquals(0, (int) $approved->timeend);
    }

    /**
     * A method that declares a period ends the enrolment that far after the approval.
     *
     * The other half of the rule above: enrolperiod is set on the method's own form, which is
     * how a course sets how long its enrolments last.
     *
     * @return void
     */
    public function test_a_method_with_a_period_ends_the_enrolment_after_it(): void {
        global $DB;

        $this->plugin->update_instance($this->instance, (object) [
            'id' => $this->instance->id,
            'enrolperiod' => WEEKSECS,
        ]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        [, $ueid] = $this->apply();
        $this->setAdminUser();
        $this->plugin->confirm_enrolment([$ueid], '');

        $approved = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertEquals(
            (int) $approved->timestart + WEEKSECS,
            (int) $approved->timeend,
            'the enrolment must end one method period after it started'
        );
    }

    /**
     * The decider's text is escaped where it lands, and is not lost on the way.
     *
     * The body is assembled as HTML from the administrator's own template, which is trusted;
     * this half is free text somebody typed into a form. It is escaped at that boundary rather
     * than stripped, because stripping would silently delete from a bare "<" onwards.
     *
     * The typed text carries a whole tag, so the absence of the raw tag and the presence of its
     * escaped form are both about the escaping; the HTML half is read alone (see bodies_of()).
     *
     * @return void
     */
    public function test_the_message_is_escaped_where_it_lands(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $typed = 'Bring A<B and a pen & paper <script>alert(1)</script>';
        $decide = function () use ($ueid, $typed): void {
            $this->plugin->confirm_enrolment([$ueid], $typed);
        };
        $html = implode(' ', $this->bodies_of($applicant, $decide, true));

        // Stored exactly as typed: the record is the audit trail, not a rendering.
        $this->assertSame($typed, (string) $this->record($applicant)->outcomemessage);

        // Escaped in the HTML body, so no markup is injected and no tail is lost.
        $this->assertStringContainsString('A&lt;B', $html);
        $this->assertStringContainsString('pen &amp; paper', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }
}
