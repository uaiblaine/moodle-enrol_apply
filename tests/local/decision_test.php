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
 * Tests for a queue decision whose selection spans enrolment methods.
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
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * Tests for a queue decision whose selection spans enrolment methods (MQ-D15).
 *
 * Two methods in two courses, one application on each, decided in one post as the site-wide queue
 * posts it. Deferral is the decision used, because it notifies inside the decision loop and so
 * needs no adhoc task drained to read the message the applicant received.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(decision::class)]
final class decision_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin. */
    protected $plugin;

    /** @var \stdClass The first method, a student intake. */
    protected $students;

    /** @var \stdClass The second method, in another course. */
    protected $moderators;

    /** @var \stdClass The applicant to the first method. */
    protected $student;

    /** @var \stdClass The applicant to the second method. */
    protected $moderator;

    /** @var int The first applicant's user enrolment id. */
    protected $studentue;

    /** @var int The second applicant's user enrolment id. */
    protected $moderatorue;

    /**
     * Two methods in two courses, each with one application holding a durable record.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        $this->plugin = enrol_get_plugin('apply');

        $this->students = $this->instance_in($this->getDataGenerator()->create_course());
        $this->moderators = $this->instance_in($this->getDataGenerator()->create_course());
        [$this->student, $this->studentue] = $this->apply_to($this->students);
        [$this->moderator, $this->moderatorue] = $this->apply_to($this->moderators);
    }

    /**
     * An apply method in a course.
     *
     * @param \stdClass $course The course.
     * @return \stdClass The {enrol} record.
     */
    protected function instance_in(\stdClass $course): \stdClass {
        global $DB;

        $id = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());

        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Apply through the plugin's own apply(), so the application has its durable record.
     *
     * @param \stdClass $instance The method.
     * @return array The applicant and their user enrolment id.
     */
    protected function apply_to(\stdClass $instance): array {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $this->setUser($applicant);
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->invoke($this->plugin, $instance, (int) $applicant->id, (object) ['applydescription' => '']);
        $sink->close();
        $this->setUser(null);

        $ueid = (int) $DB->get_field('user_enrolments', 'id', ['userid' => $applicant->id, 'enrolid' => $instance->id], MUST_EXIST);

        return [$applicant, $ueid];
    }

    /**
     * The message recorded on an application's durable record.
     *
     * @param int $ueid User enrolment id.
     * @return string The recorded outcome message.
     */
    protected function recorded(int $ueid): string {
        global $DB;

        return (string) $DB->get_field('enrol_apply_submission', 'outcomemessage', ['userenrolmentid' => $ueid], MUST_EXIST);
    }

    /**
     * Run a decision and collect what each applicant was sent.
     *
     * @param callable $decide The decision to run.
     * @return array Recipient id => their message bodies, joined.
     */
    protected function sent(callable $decide): array {
        $sink = $this->redirectMessages();
        $decide();
        $messages = $sink->get_messages();
        $sink->close();

        $bodies = [];
        foreach ($messages as $message) {
            $to = (int) $message->useridto;
            // The HTML half: the plain half is wrapped by html_to_text(), which can split a sentence.
            $bodies[$to] = ($bodies[$to] ?? '') . ' ' . (string) $message->fullmessagehtml;
        }

        return $bodies;
    }

    /**
     * Each method's applicants get that method's message, on their record and in what they are sent.
     *
     * The control is the other method's wording, absent from each applicant's own: one message
     * reaching both would put a moderator's wording in a student's mail.
     *
     * Changes that must make it fail: grouping by the posted keys instead of the method
     * {user_enrolments} names, or every group given the plain message.
     *
     * @return void
     */
    public function test_each_method_gets_its_own_message(): void {
        $this->setAdminUser();

        $bodies = $this->sent(function (): void {
            $decided = decision::apply('wait', [$this->studentue, $this->moderatorue], 'Plain wording', [
                (int) $this->students->id => '  Your intake starts in March.  ',
                (int) $this->moderators->id => 'We will call you about moderating.',
            ], ['note' => '']);
            $this->assertSame(2, $decided);
        });

        $this->assertSame('Your intake starts in March.', $this->recorded($this->studentue));
        $this->assertSame('We will call you about moderating.', $this->recorded($this->moderatorue));

        $tostudent = $bodies[(int) $this->student->id] ?? '';
        $tomoderator = $bodies[(int) $this->moderator->id] ?? '';
        $this->assertStringContainsString('Your intake starts in March.', $tostudent);
        $this->assertStringNotContainsString('We will call you about moderating.', $tostudent);
        $this->assertStringContainsString('We will call you about moderating.', $tomoderator);
        $this->assertStringNotContainsString('Your intake starts in March.', $tomoderator);
        $this->assertStringNotContainsString('Plain wording', $tostudent . $tomoderator);
    }

    /**
     * A row the operator may not decide is still skipped, and counted as left alone.
     *
     * A teacher of the first course posts both ids with a message for each method: grouping the
     * ids must not let a message carry a row past the per-row check. The control is the first
     * row, which is theirs and is decided.
     *
     * @return void
     */
    public function test_a_row_the_operator_may_not_decide_is_left_alone(): void {
        global $DB;

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->students->courseid, 'editingteacher');
        $this->setUser($teacher);

        $decided = decision::apply('wait', [$this->studentue, $this->moderatorue], '', [
            (int) $this->students->id => 'For the students',
            (int) $this->moderators->id => 'For the moderators',
        ], ['note' => '']);

        $this->assertSame(1, $decided);
        $this->assertSame(ENROL_APPLY_USER_WAIT, (int) $DB->get_field('user_enrolments', 'status', ['id' => $this->studentue]));
        $this->assertSame('For the students', $this->recorded($this->studentue));
        $this->assertSame(ENROL_USER_SUSPENDED, (int) $DB->get_field('user_enrolments', 'status', ['id' => $this->moderatorue]));
        $this->assertSame('', $this->recorded($this->moderatorue));
    }

    /**
     * A confirmation reports the rows it decided, not the rows it was posted.
     *
     * The seats warning reads groups back from these ids, so a posted row the decision skipped
     * (here a course the teacher does not teach) must not be among them. The control: the
     * teacher's own row is, and a wait decision confirms none.
     *
     * Changes that must make it fail: confirmed_ids() returning the posted list, or not reset
     * between decisions.
     *
     * @return void
     */
    public function test_confirmed_ids_are_the_rows_the_decision_reached(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->students->courseid, 'editingteacher');
        $this->setUser($teacher);

        $sink = $this->redirectMessages();
        $decided = decision::apply('confirm', [$this->studentue, $this->moderatorue], '', [], ['note' => '']);
        $sink->close();

        $this->assertSame(1, $decided);
        $this->assertSame([$this->studentue], decision::confirmed_ids());

        decision::apply('wait', [$this->moderatorue], '', [], ['note' => '']);
        $this->assertSame([], decision::confirmed_ids());
    }

    /**
     * Without per-method messages the selection is one call carrying the plain message.
     *
     * @return void
     */
    public function test_without_messages_it_is_one_call_with_the_plain_message(): void {
        $this->setAdminUser();

        $ids = [$this->studentue, $this->moderatorue];
        $this->assertSame([['ids' => $ids, 'message' => 'One for all']], decision::groups($ids, 'One for all', []));

        $this->assertSame(2, decision::apply('wait', $ids, 'One for all', [], ['note' => '']));
        $this->assertSame('One for all', $this->recorded($this->studentue));
        $this->assertSame('One for all', $this->recorded($this->moderatorue));
    }

    /**
     * A message keyed by a method no posted row belongs to changes nothing.
     *
     * The forged key selects no row, and the second method, which has no entry of its own, keeps
     * the plain message rather than inheriting anybody's.
     *
     * Changes that must make it fail: a method with no entry no longer falling back to the plain
     * message.
     *
     * @return void
     */
    public function test_a_forged_method_key_changes_nothing(): void {
        $this->setAdminUser();
        $forged = (int) $this->moderators->id + 1000;

        $decided = decision::apply('wait', [$this->studentue, $this->moderatorue], 'Plain wording', [
            $forged => 'Forged wording',
            (int) $this->students->id => 'For the students',
        ], ['note' => '']);

        $this->assertSame(2, $decided);
        $this->assertSame('For the students', $this->recorded($this->studentue));
        $this->assertSame('Plain wording', $this->recorded($this->moderatorue));
    }

    /**
     * An id naming no enrolment is still handed on, so it is counted as left alone.
     *
     * @return void
     */
    public function test_an_id_naming_nothing_is_counted_as_left_alone(): void {
        global $DB;

        $this->setAdminUser();
        $gone = (int) $DB->get_field_sql('SELECT MAX(id) FROM {user_enrolments}') + 1000;

        $groups = decision::groups([$this->studentue, $gone], 'Plain wording', [(int) $this->students->id => 'Mine']);
        $this->assertSame([
            ['ids' => [$this->studentue], 'message' => 'Mine'],
            ['ids' => [$gone], 'message' => 'Plain wording'],
        ], $groups);

        $this->assertSame(1, decision::apply('wait', [$this->studentue, $gone], 'Plain wording', [
            (int) $this->students->id => 'Mine',
        ], ['note' => '']));
    }

    /**
     * An action that is none of the three decisions is refused before anything is read.
     *
     * @return void
     */
    public function test_an_unknown_action_is_refused(): void {
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        decision::apply('approve', [$this->studentue], '', [], ['note' => '']);
    }
}
