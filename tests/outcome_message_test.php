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
     * create_application() in lib_test bypasses apply() and leaves no enrol_apply_submission
     * row at all, which would make every assertion here read an empty table.
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
     * @param \stdClass $applicant Recipient to filter on.
     * @param callable $decide What to run while the sink is open.
     * @return array List of message bodies.
     */
    protected function bodies_of(\stdClass $applicant, callable $decide): array {
        $sink = $this->redirectMessages();
        $decide();

        /* Drain the queue rather than assuming the decision notified synchronously. Approval
           does not: complete_approval() queues \enrol_apply\task\notify_approval, and the
           message is only built when that runs. Deferral and cancellation notify inside the
           decision loop, so for those this drains nothing - which is the point of using one
           helper for all three. */
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
                $bodies[] = (string) $message->fullmessagehtml . ' ' . (string) $message->fullmessage;
            }
        }

        return $bodies;
    }

    /**
     * Approving with a message stores it and delivers it to the applicant.
     *
     * The approval path is the one that can silently lose this. complete_approval() runs TWICE
     * for a queue approval - update_user_enrol() dispatches its hook before writing the row, so
     * hook_callbacks reaches it first, with no message - and submission::decide() skips a row
     * already at the target status. A message carried through decide() would be dropped with
     * the status still looking correct. It is recorded before the status changes instead.
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
     * the decision loop, while approval notifies from an adhoc task queued by the hook. They
     * fail differently and the ordering that keeps them working is not the same.
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
     * Cancellation unenrols, which deletes the user_enrolments row the message is keyed on, so
     * the recording has to happen before that - the same reason the decision itself is stamped
     * before the unenrolment.
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
     * The groups the decider chooses REPLACE the instance's list, and do not add to it.
     *
     * The defect this pins is silent and would look like a working feature. An approval taken
     * through the queue completes twice: update_user_enrol() dispatches its hook before writing
     * the row, so hook_callbacks finishes the approval first and confirm_enrolment() finishes it
     * again. Had the chosen list been passed as an argument, the first pass would have joined
     * the instance's groups and the second the chosen ones - a union, so a group the approver
     * deselected is joined anyway and nothing removes it. The choice is stored and both passes
     * read it.
     *
     * The instance group is the control. Without it this would pass against an implementation
     * that simply ignored the instance list altogether, which is a different thing from
     * replacing it.
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
     * A group from another course never reaches the membership OR the durable record.
     *
     * Both halves are asserted because there are two guards and they defend different things.
     * add_instance_groups() re-checks against the course before joining, so membership alone is
     * already safe - and asserting only that made the allowlist in confirm_enrolment()
     * unreachable, which was measured: removing it reddened nothing. Its job is the RECORD,
     * which outlives the enrolment and is read by the reports and by a subject access request.
     * A foreign id stored there is wrong even though it is never joined.
     *
     * groups_get_all_groups() is keyed by group id, so the allowlist compares KEYS. Comparing
     * values would test the id against group names and let everything through.
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
     * The decider's enrolment period is stamped on approval, and never before it.
     *
     * A timeend on a pending row is swept by the ENROL_EXT_REMOVED_UNENROL branch of
     * process_expirations(), which selects on timeend with no status filter - so the applicant
     * would be unenrolled instead of decided.
     *
     * @return void
     */
    public function test_the_chosen_period_is_stamped_on_approval_only(): void {
        global $DB;

        [$applicant, $ueid] = $this->apply();

        // The control: nothing is stamped while the application is still pending.
        $pending = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertEquals(0, (int) $pending->timestart);
        $this->assertEquals(0, (int) $pending->timeend);

        $this->setAdminUser();
        $start = time() + 86400;
        $end = $start + (7 * 86400);
        $this->plugin->confirm_enrolment([$ueid], '', ['timestart' => $start, 'timeend' => $end]);

        $approved = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
        $this->assertEquals($start, (int) $approved->timestart);
        $this->assertEquals($end, (int) $approved->timeend);
    }

    /**
     * The decider's text is escaped where it lands, and is not lost on the way.
     *
     * The body is assembled as HTML from the administrator's own template, which is trusted;
     * this half is free text somebody typed into a form. It is escaped at that boundary rather
     * than stripped, because stripping would silently delete from a bare "<" onwards - the
     * defect this plugin has already fixed twice elsewhere.
     *
     * @return void
     */
    public function test_the_message_is_escaped_where_it_lands(): void {
        [$applicant, $ueid] = $this->apply();
        $this->setAdminUser();

        $typed = 'Bring A<B and a pen & paper';
        $bodies = $this->bodies_of($applicant, function () use ($ueid, $typed): void {
            $this->plugin->confirm_enrolment([$ueid], $typed);
        });
        $body = implode(' ', $bodies);

        // Stored exactly as typed: the record is the audit trail, not a rendering.
        $this->assertSame($typed, (string) $this->record($applicant)->outcomemessage);

        // Escaped in the HTML body, so no markup is injected and no tail is lost.
        $this->assertStringContainsString('A&lt;B', $body);
        $this->assertStringContainsString('&amp;', $body);
        $this->assertStringNotContainsString('<script', $body);
    }
}
