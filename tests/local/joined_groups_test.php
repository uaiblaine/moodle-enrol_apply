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
 * Tests for the record of the groups an approval joined.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass targets resolve and the bulk operation finds its manager.
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/enrol/apply/db/upgradelib.php');
require_once($CFG->dirroot . '/group/lib.php');

/**
 * The groups an approval joined, enrol_apply_submission.joinedgroups (V-D10).
 *
 * Every approval route is driven for real, because what is under test is what the memberships are
 * once both passes of complete_approval() have run, which only the real routes produce. The
 * upgrade's backfill is called directly, on rows seeded the way the past left them.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(submission::class)]
#[CoversClass(\enrol_apply_plugin::class)]
final class joined_groups_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin under test. */
    private $plugin;

    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass The apply instance, whose own list joins $listed. */
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
        $id = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $this->listed = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Everyone']);
        $this->chosen = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Chosen']);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $id, 'groupid' => $this->listed->id]);

        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->messages = $this->redirectMessages();
    }

    /**
     * Close the sink and forget any decision marker.
     *
     * @return void
     */
    protected function tearDown(): void {
        $this->messages->close();
        decisionlog::reset();
        parent::tearDown();
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
     * The joinedgroups column of the newest record of a user enrolment.
     *
     * @param int $ueid User enrolment id.
     * @return string|null The stored value, null when not known.
     */
    private function joined_of(int $ueid): ?string {
        global $DB;

        $rows = $DB->get_records(
            'enrol_apply_submission',
            ['userenrolmentid' => $ueid],
            'timecreated DESC, id DESC',
            'id, joinedgroups',
            0,
            1
        );
        $row = reset($rows);
        $this->assertNotFalse($row, 'the premise: the application has a record');

        return $row->joinedgroups === null ? null : (string) $row->joinedgroups;
    }

    /**
     * A record that was never approved says nothing about groups: the column is NULL, not known.
     *
     * @return void
     */
    public function test_an_application_not_yet_approved_records_nothing(): void {
        [, $ueid] = $this->application();

        $this->assertNull($this->joined_of($ueid));
    }

    /**
     * A queue approval with the decider's choice records the chosen group, and only it.
     *
     * Changes that must make it fail: complete_approval() not recording the groups.
     *
     * @return void
     */
    public function test_a_queue_approval_with_a_choice_records_what_it_joined(): void {
        [, $ueid] = $this->application();
        $this->setUser($this->teacher);

        $this->assertSame(1, $this->plugin->confirm_enrolment([$ueid], '', ['groups' => [$this->chosen->id]]));

        $this->assertSame((string) $this->chosen->id, $this->joined_of($ueid));
    }

    /**
     * A queue approval left on the method's defaults records the method's group, which the
     * decider's choice (decidedgroups) does not: that difference is the whole reason for the column.
     *
     * Changes that must make it fail: complete_approval() not recording the groups, or recording
     * the decider's (empty) choice instead of what was joined.
     *
     * @return void
     */
    public function test_an_approval_on_the_defaults_records_the_methods_group(): void {
        global $DB;

        [, $ueid] = $this->application();
        $this->setUser($this->teacher);

        $this->assertSame(1, $this->plugin->confirm_enrolment([$ueid], '', []));

        $this->assertSame((string) $this->listed->id, $this->joined_of($ueid));
        // The premise of the finding: the decider's choice names nothing.
        $this->assertSame(
            '',
            (string) $DB->get_field('enrol_apply_submission', 'decidedgroups', ['userenrolmentid' => $ueid])
        );
    }

    /**
     * Core's "Edit enrolment" route, which reaches complete_approval() only through the hook,
     * records the method's group too.
     *
     * @return void
     */
    public function test_an_edit_enrolment_approval_records_what_it_joined(): void {
        [$applicant, $ueid] = $this->application();
        $this->setUser($this->teacher);

        $this->plugin->update_user_enrol($this->instance, $applicant->id, ENROL_USER_ACTIVE);

        $this->assertSame((string) $this->listed->id, $this->joined_of($ueid));
    }

    /**
     * The participants page's bulk confirmation records each application's groups.
     *
     * @return void
     */
    public function test_a_bulk_approval_records_each_applications_groups(): void {
        global $PAGE;

        [$first, $firstueid] = $this->application();
        [$second, $secondueid] = $this->application();
        $this->setUser($this->teacher);

        $PAGE->set_url('/user/index.php', ['id' => $this->course->id]);
        $manager = new \course_enrolment_manager($PAGE, $this->course, $this->instance->id);
        $users = $manager->get_users_enrolments([$first->id, $second->id]);
        $operation = new \enrol_apply\bulk\confirm_operation($manager, $this->plugin);
        $this->assertTrue($operation->process($manager, $users, (object) ['outcomemessage' => '']));

        $this->assertSame((string) $this->listed->id, $this->joined_of($firstueid));
        $this->assertSame((string) $this->listed->id, $this->joined_of($secondueid));
    }

    /**
     * The two passes of complete_approval() write one answer: the hook pass, which runs before
     * core writes the row, already records the memberships, and the second pass reads the same
     * memberships and writes the same value.
     *
     * Changes that must make it fail: the value read from what one pass's groups_add_member()
     * calls returned rather than from the memberships.
     *
     * @return void
     */
    public function test_both_passes_converge_on_one_answer(): void {
        [$applicant, $ueid] = $this->application();
        $this->setUser($this->teacher);
        submission::record_decided_groups($ueid, [(int) $this->chosen->id, (int) $this->listed->id]);

        // The hook pass alone, as before_user_enrolment_updated runs it.
        $this->plugin->complete_approval($this->instance, (int) $applicant->id, $ueid);
        $expected = implode(',', [min($this->listed->id, $this->chosen->id), max($this->listed->id, $this->chosen->id)]);
        $this->assertSame($expected, $this->joined_of($ueid));

        // The second pass, with the row written.
        $this->plugin->complete_approval($this->instance, (int) $applicant->id, $ueid, true);
        $this->assertSame($expected, $this->joined_of($ueid));
    }

    /**
     * A group core refused to join is not recorded: groups_add_member() returns false, without an
     * exception, for a user who is not enrolled in the course.
     *
     * The control is the next assertion's shape on every other test here: an enrolled applicant
     * approved onto the same defaults records the group.
     *
     * Changes that must make it fail: the record written from the groups the approval meant to
     * join rather than the ones it did.
     *
     * @return void
     */
    public function test_a_membership_core_refused_is_not_recorded(): void {
        global $DB;

        [$applicant, $ueid] = $this->application();
        $this->setUser($this->teacher);
        // The enrolment gone from under the approval, the record kept: groups_add_member() refuses.
        $DB->delete_records('user_enrolments', ['id' => $ueid]);

        $this->plugin->complete_approval($this->instance, (int) $applicant->id, $ueid, true);

        $this->assertFalse(groups_is_member((int) $this->listed->id, (int) $applicant->id));
        $this->assertSame('', $this->joined_of($ueid));
    }

    /**
     * A membership the applicant held already, by another route, is not claimed for the approval:
     * groups_add_member() leaves the existing row alone, unstamped, so the approval joined nothing.
     *
     * @return void
     */
    public function test_a_membership_held_before_is_not_claimed(): void {
        [$applicant, $ueid] = $this->application();
        $this->assertTrue(groups_add_member($this->listed->id, $applicant->id));
        $this->setUser($this->teacher);

        $this->assertSame(1, $this->plugin->confirm_enrolment([$ueid], '', []));

        $this->assertTrue(groups_is_member((int) $this->listed->id, (int) $applicant->id));
        $this->assertSame('', $this->joined_of($ueid));
    }

    /**
     * An application re-queued and approved again records what the second approval left, not the
     * first one's list: the value is replaced, never appended to.
     *
     * The first approval's membership is removed by hand before the second, so the two lists are
     * disjoint; a membership the first left in place would still be reported, because the
     * applicant is still in that group through this method.
     *
     * Changes that must make it fail: the writer keeping an earlier value.
     *
     * @return void
     */
    public function test_a_second_approval_replaces_the_first(): void {
        [$applicant, $ueid] = $this->application();
        $this->setUser($this->teacher);
        $this->assertSame(1, $this->plugin->confirm_enrolment([$ueid], '', []));
        $this->assertSame((string) $this->listed->id, $this->joined_of($ueid));

        // Re-queued, as core's "Edit enrolment" does, and the membership taken away.
        $this->plugin->update_user_enrol($this->instance, $applicant->id, ENROL_USER_SUSPENDED);
        groups_remove_member($this->listed->id, $applicant->id);

        $this->assertSame(1, $this->plugin->confirm_enrolment([$ueid], '', ['groups' => [$this->chosen->id]]));
        $this->assertSame((string) $this->chosen->id, $this->joined_of($ueid));
    }

    /**
     * Only the newest record of a user enrolment is written: an older record's approval was not
     * this one.
     *
     * @return void
     */
    public function test_only_the_newest_record_is_written(): void {
        global $DB;

        [, $ueid] = $this->application();
        $newest = $DB->get_record('enrol_apply_submission', ['userenrolmentid' => $ueid], '*', MUST_EXIST);
        $older = clone $newest;
        unset($older->id);
        $older->timecreated = (int) $newest->timecreated - 100;
        $olderid = (int) $DB->insert_record('enrol_apply_submission', $older);

        submission::record_joined_groups($ueid, [(int) $this->chosen->id]);

        $this->assertSame(
            (string) $this->chosen->id,
            (string) $DB->get_field('enrol_apply_submission', 'joinedgroups', ['id' => $newest->id])
        );
        $this->assertNull($DB->get_field('enrol_apply_submission', 'joinedgroups', ['id' => $olderid]));
    }

    /**
     * Seed a record the way an approval before the column left it: enrolled, the record NULL.
     *
     * @param int $status The record's status.
     * @param array $groups Groups to make the applicant a member of, stamped with the method.
     * @return \stdClass Object with int 'id' (the record), 'userid' and 'ueid'.
     */
    private function seed_past(int $status, array $groups): \stdClass {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $user->id, null, 0, 0, ENROL_USER_ACTIVE);
        $ueid = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $this->instance->id, 'userid' => $user->id]);
        foreach ($groups as $group) {
            $this->assertTrue(groups_add_member($group->id, $user->id, 'enrol_apply', $this->instance->id));
        }
        $id = (int) $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $user->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => $ueid,
            'comment' => '',
            'status' => $status,
            'outcomemessage' => '',
            'decidedgroups' => '',
            'timecreated' => time(),
            'timedecided' => time(),
            'decidedby' => 0,
        ]);

        return (object) ['id' => $id, 'userid' => (int) $user->id, 'ueid' => $ueid];
    }

    /**
     * The upgrade fills an approval from the memberships it stamped, and only from them: an
     * approval with none stays NULL, and so do a pending record with a membership, an approval
     * whose enrolment is gone, an approval whose membership is stamped by another method, and a
     * membership the applicant held unstamped. A second run changes nothing.
     *
     * Changes that must make it fail: the status clause dropped, the enrolment join dropped, the
     * component or itemid clause dropped, or a re-run rewriting what the first wrote.
     *
     * @return void
     */
    public function test_the_upgrade_fills_approvals_from_their_memberships_once(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $third = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Third']);
        $othermethod = (int) $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());

        $filled = $this->seed_past(submission::STATUS_APPROVED, [$third, $this->chosen]);
        $none = $this->seed_past(submission::STATUS_APPROVED, []);
        $pending = $this->seed_past(submission::STATUS_PENDING, [$this->chosen]);
        $gone = $this->seed_past(submission::STATUS_APPROVED, [$this->chosen]);
        $DB->delete_records('user_enrolments', ['id' => $gone->ueid]);
        $foreign = $this->seed_past(submission::STATUS_APPROVED, []);
        $this->assertTrue(groups_add_member($this->chosen->id, $foreign->userid, 'enrol_apply', $othermethod));
        $unstamped = $this->seed_past(submission::STATUS_APPROVED, []);
        $this->assertTrue(groups_add_member($this->chosen->id, $unstamped->userid));
        $known = $this->seed_past(submission::STATUS_APPROVED, [$this->chosen]);
        $DB->set_field('enrol_apply_submission', 'joinedgroups', '', ['id' => $known->id]);

        $values = function () use ($filled, $none, $pending, $gone, $foreign, $unstamped, $known, $DB): array {
            $out = [];
            foreach (compact('filled', 'none', 'pending', 'gone', 'foreign', 'unstamped', 'known') as $case => $seed) {
                $value = $DB->get_field('enrol_apply_submission', 'joinedgroups', ['id' => $seed->id]);
                $out[$case] = $value === null ? null : (string) $value;
            }
            return $out;
        };

        $ids = [(int) $third->id, (int) $this->chosen->id];
        sort($ids);
        $expected = [
            'filled' => implode(',', $ids),
            'none' => null,
            'pending' => null,
            'gone' => null,
            'foreign' => null,
            'unstamped' => null,
            'known' => '',
        ];

        $this->assertSame(1, enrol_apply_backfill_joined_groups());
        $this->assertSame($expected, $values());

        $this->assertSame(0, enrol_apply_backfill_joined_groups(), 'a second run writes nothing');
        $this->assertSame($expected, $values());
    }

    /**
     * The upgrade fills the newest record of a user enrolment only, the one the writer updates.
     *
     * @return void
     */
    public function test_the_upgrade_fills_the_newest_record_only(): void {
        global $DB;

        $seed = $this->seed_past(submission::STATUS_APPROVED, [$this->chosen]);
        $older = $DB->get_record('enrol_apply_submission', ['id' => $seed->id], '*', MUST_EXIST);
        unset($older->id);
        $older->timecreated = (int) $older->timecreated - 100;
        $olderid = (int) $DB->insert_record('enrol_apply_submission', $older);

        $this->assertSame(1, enrol_apply_backfill_joined_groups());

        $this->assertSame(
            (string) $this->chosen->id,
            (string) $DB->get_field('enrol_apply_submission', 'joinedgroups', ['id' => $seed->id])
        );
        $this->assertNull($DB->get_field('enrol_apply_submission', 'joinedgroups', ['id' => $olderid]));
    }
}
