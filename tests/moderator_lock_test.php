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

namespace enrol_apply;

use enrol_apply\local\instanceoptions;
use enrol_apply\local\keyattempts;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass target below resolves when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/apply/tests/fixtures/fake_lock_factory.php');
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * The lock between a deciding method and a learner method of one course (C-D29), and the card a
 * method hides from people outside its cohort (C-D25), in docs/design/applicant-group-choice.md.
 *
 * The course has two apply methods: "student", granting Student, a learner method; and
 * "recruit", granting Non-editing teacher, a role setUp() lets decide applications, a deciding
 * method. Applications are written through submit_application(), the write door every caller of
 * allow_apply() ends in.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
final class moderator_lock_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass The learner method, granting Student. */
    private $student;

    /** @var \stdClass The deciding method, granting Non-editing teacher. */
    private $recruit;

    /**
     * The course and its two methods, both accepting applications.
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

        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $teacher, \context_system::instance()->id, true);

        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->method((int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST));
        $this->recruit = $this->method($teacher);
    }

    /**
     * Forget the throttle's request locks and the fake lock factory between tests.
     *
     * @return void
     */
    protected function tearDown(): void {
        global $CFG;

        keyattempts::release_all();
        unset($CFG->lock_factory);
        parent::tearDown();
    }

    /**
     * A method of the course granting a role, enabled and accepting applications.
     *
     * @param int $roleid The role it grants on approval.
     * @return \stdClass The {enrol} record.
     */
    private function method(int $roleid): \stdClass {
        global $DB;

        $id = $this->plugin->add_instance($this->course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,
            'roleid' => $roleid,
        ] + $this->plugin->get_instance_defaults());

        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Submit an application through the write door.
     *
     * @param \stdClass $instance The method.
     * @param \stdClass $user The applicant.
     * @return \enrol_apply\local\application_result What the door did.
     */
    private function submit(\stdClass $instance, \stdClass $user): \enrol_apply\local\application_result {
        $sink = $this->redirectMessages();
        try {
            return $this->plugin->submit_application($instance, (int) $user->id, (object) ['applydescription' => '']);
        } finally {
            $sink->close();
        }
    }

    /**
     * Whether the user holds a user enrolment on the method.
     *
     * @param \stdClass $instance The method.
     * @param \stdClass $user The user.
     * @return bool True when they do.
     */
    private function enrolled_on(\stdClass $instance, \stdClass $user): bool {
        global $DB;

        return $DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);
    }

    /**
     * An approved, active enrolment with the role its approval stamped.
     *
     * The plugin's roles_protected() is false, so enrol_user() assigns a role bare; the stamp
     * (component enrol_apply, the method's id) is what complete_approval() writes, and what the
     * lock reads.
     *
     * @param \stdClass $instance The method.
     * @param \stdClass $user The applicant.
     * @param int $roleid The role the approval gave.
     * @param int $timeend When the enrolment ends, 0 for never.
     * @return void
     */
    private function approve(\stdClass $instance, \stdClass $user, int $roleid, int $timeend = 0): void {
        $this->plugin->enrol_user($instance, $user->id, null, 0, $timeend, ENROL_USER_ACTIVE);
        role_assign($roleid, $user->id, \context_course::instance($this->course->id)->id, 'enrol_apply', (int) $instance->id);
    }

    /**
     * The lock's refusal.
     *
     * @return string The sentence.
     */
    private function locked(): string {
        return get_string('applydecidingelsewhere', 'enrol_apply');
    }

    /**
     * Who applied as a moderator, and is still waiting, cannot apply as a student: the predicate
     * refuses with the generic sentence and the write door writes nothing.
     *
     * Changes that must make it fail: the lock deleted from allow_apply(), or its pending half.
     *
     * @return void
     */
    public function test_a_moderator_applicant_is_refused_by_the_learner_method(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertTrue($this->plugin->allow_apply($this->student, (int) $user->id), 'the precondition');

        $this->assertTrue($this->submit($this->recruit, $user)->was_created());

        $this->assertSame($this->locked(), $this->plugin->allow_apply($this->student, (int) $user->id));
        $result = $this->submit($this->student, $user);
        $this->assertTrue($result->is_refusal());
        $this->assertSame($this->locked(), $result->reason());
        $this->assertFalse($this->enrolled_on($this->student, $user));
    }

    /**
     * An approved moderator, and one deferred to the waiting list, cannot apply as a student either.
     *
     * Changes that must make it fail: the active half of the lock dropped (only waiting rows read),
     * or the deferred row read as no application.
     *
     * @return void
     */
    public function test_an_approved_or_deferred_moderator_is_refused_by_the_learner_method(): void {
        $approved = $this->getDataGenerator()->create_user();
        $this->approve($this->recruit, $approved, (int) $this->recruit->roleid);
        $this->assertSame($this->locked(), $this->plugin->allow_apply($this->student, (int) $approved->id));

        $deferred = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->recruit, $deferred->id, null, 0, 0, ENROL_APPLY_USER_WAIT);
        $this->assertSame($this->locked(), $this->plugin->allow_apply($this->student, (int) $deferred->id));
    }

    /**
     * An active enrolment is judged by the role its approval recorded, not by its method's setting:
     * a decider who chose a deciding role on the student method locks that user out of another
     * learner method, an approved moderator stays locked after the recruitment method's role is
     * edited to a learner one, and a moderator method's approval that gave the student role does
     * not lock.
     *
     * Changes that must make it fail: the active half read from the method's role again
     * (decidingrole::method_decides()) instead of the stamped role assignment.
     *
     * @return void
     */
    public function test_an_active_enrolment_is_judged_by_the_role_it_recorded(): void {
        global $DB;

        $teacher = (int) $this->recruit->roleid;
        $studentrole = (int) $this->student->roleid;
        $other = $this->method($studentrole);

        $chosen = $this->getDataGenerator()->create_user();
        $this->approve($this->student, $chosen, $teacher);
        $this->assertSame($this->locked(), $this->plugin->allow_apply($other, (int) $chosen->id));

        $moderator = $this->getDataGenerator()->create_user();
        $this->approve($this->recruit, $moderator, $teacher);
        $DB->set_field('enrol', 'roleid', $studentrole, ['id' => $this->recruit->id]);
        $this->assertSame($this->locked(), $this->plugin->allow_apply($this->student, (int) $moderator->id));

        // The control: an approval on the recruitment method that recorded the student role.
        $DB->set_field('enrol', 'roleid', $teacher, ['id' => $this->recruit->id]);
        $learner = $this->getDataGenerator()->create_user();
        $this->approve($this->recruit, $learner, $studentrole);
        $this->assertTrue($this->plugin->allow_apply($this->student, (int) $learner->id));
    }

    /**
     * A moderator application or enrolment that will never grant access again lifts the lock:
     * one whose period has ended, active or suspended.
     *
     * Changes that must make it fail: the lock reading every row whatever its end.
     *
     * @return void
     */
    public function test_an_ended_moderator_enrolment_lifts_the_lock(): void {
        $ended = $this->getDataGenerator()->create_user();
        $this->approve($this->recruit, $ended, (int) $this->recruit->roleid, time() - DAYSECS);
        $this->assertTrue($this->plugin->allow_apply($this->student, (int) $ended->id));

        $lapsed = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->recruit, $lapsed->id, null, 0, time() - DAYSECS, ENROL_USER_SUSPENDED);
        $this->assertTrue($this->plugin->allow_apply($this->student, (int) $lapsed->id));
    }

    /**
     * The other order stays open (C-D5): a student, pending or enrolled, may apply to the deciding
     * method, and a moderator of one deciding method may apply to another.
     *
     * Changes that must make it fail: the lock applied by a deciding method too.
     *
     * @return void
     */
    public function test_a_deciding_method_admits_a_student_and_a_moderator(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->student, $user->id, null, 0, 0, ENROL_USER_ACTIVE);

        $this->assertTrue($this->plugin->allow_apply($this->recruit, (int) $user->id));
        $this->assertTrue($this->submit($this->recruit, $user)->was_created());

        // A second deciding method, applied to by the moderator applicant of the first.
        $second = $this->method((int) $this->recruit->roleid);
        $this->assertTrue($this->plugin->allow_apply($second, (int) $user->id));
    }

    /**
     * A learner method's own application elsewhere locks nothing: only a deciding method's does.
     *
     * Changes that must make it fail: the lock reading every apply method of the course.
     *
     * @return void
     */
    public function test_an_application_to_another_learner_method_does_not_lock(): void {
        $user = $this->getDataGenerator()->create_user();
        $second = $this->method((int) $this->student->roleid);
        $this->assertTrue($this->submit($second, $user)->was_created());

        $this->assertTrue($this->plugin->allow_apply($this->student, (int) $user->id));
    }

    /**
     * Which method is which is read from the role it grants, as the course resolves it: a role
     * overridden in the course to decide makes its method a deciding one.
     *
     * Changes that must make it fail: the method's role not judged in its course.
     *
     * @return void
     */
    public function test_the_lock_follows_the_role_as_the_course_resolves_it(): void {
        $user = $this->getDataGenerator()->create_user();
        $observer = (int) $this->getDataGenerator()->create_role(['shortname' => 'observer']);
        $other = $this->method($observer);
        $this->plugin->enrol_user($other, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $this->assertTrue($this->plugin->allow_apply($this->student, (int) $user->id), 'the precondition');

        assign_capability(
            'enrol/apply:manageapplications',
            CAP_ALLOW,
            $observer,
            \context_course::instance($this->course->id)->id,
            true
        );
        $this->assertSame($this->locked(), $this->plugin->allow_apply($this->student, (int) $user->id));
    }

    /**
     * The lock is read for the applicant handed to the predicate, not for whoever is logged in,
     * in both directions.
     *
     * Changes that must make it fail: the lock reading $USER.
     *
     * @return void
     */
    public function test_the_lock_is_read_for_the_applicant_passed(): void {
        $moderator = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->recruit, $moderator->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $other = $this->getDataGenerator()->create_user();

        $this->setUser($other);
        $this->assertSame($this->locked(), $this->plugin->allow_apply($this->student, (int) $moderator->id));

        $this->setUser($moderator);
        $this->assertTrue($this->plugin->allow_apply($this->student, (int) $other->id));
    }

    /**
     * The write door takes a second lock on the course and the applicant, after the per-method
     * one, around the check and the write: with another request holding it, the application is
     * refused and nothing is written, so a moderator application and a student one sent together
     * cannot both pass. Both locks are released afterwards.
     *
     * Changes that must make it fail: the course lock not taken, taken before the method's, or not
     * released.
     *
     * @return void
     */
    public function test_the_course_lock_holds_two_applications_apart(): void {
        $user = $this->getDataGenerator()->create_user();
        fake_lock_factory::install();
        $resource = (int) $this->course->id . '_' . (int) $user->id;

        // Another request, writing this person's application to the other method, holds the course.
        fake_lock_factory::$taken['enrol_apply_course/' . $resource] = true;
        $result = $this->submit($this->student, $user);
        $this->assertTrue($result->is_refusal());
        $this->assertSame(get_string('applycourselocked', 'enrol_apply'), $result->reason());
        $this->assertFalse($this->enrolled_on($this->student, $user));
        $this->assertSame([$this->student->id . '_' . $user->id], fake_lock_factory::requests_for('enrol_apply_submit'));
        $this->assertSame(
            ['enrol_apply_submit/' . $this->student->id . '_' . $user->id, 'enrol_apply_course/' . $resource],
            fake_lock_factory::$requests,
            'the method first, then the course'
        );
        $this->assertSame(['enrol_apply_course/' . $resource => true], fake_lock_factory::$taken, 'the method lock is released');

        // The other request is done: the application is written, and both locks released.
        unset(fake_lock_factory::$taken['enrol_apply_course/' . $resource]);
        $this->assertTrue($this->submit($this->student, $user)->was_created());
        $this->assertSame([$resource], array_values(array_unique(fake_lock_factory::requests_for('enrol_apply_course'))));
        $this->assertSame([], fake_lock_factory::$taken);
    }

    /**
     * Turn the hidden card on for the recruitment method, restricted to a new cohort.
     *
     * @return \stdClass The cohort.
     */
    private function hide_card_outside_a_cohort(): \stdClass {
        global $DB;

        $cohort = $this->getDataGenerator()->create_cohort(['name' => 'Moderator candidates']);
        $DB->set_field('enrol', 'customint5', $cohort->id, ['id' => $this->recruit->id]);
        $this->recruit = $DB->get_record('enrol', ['id' => $this->recruit->id], '*', MUST_EXIST);
        instanceoptions::set((int) $this->recruit->id, ['hidecard' => 1]);

        return $cohort;
    }

    /**
     * The card is hidden from a logged-in outsider and from a visitor, not from a member, and not
     * at all with the option off or without a cohort; the restore sentinel and a deleted cohort
     * have no members.
     *
     * Changes that must make it fail: the option not read, a member read as an outsider, the
     * cohort not required, or a visitor shown the card.
     *
     * @return void
     */
    public function test_hides_card_from_reads_the_option_the_cohort_and_the_user(): void {
        global $DB;

        $member = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        $this->assertFalse($this->plugin->hides_card_from($this->recruit, (int) $outsider->id), 'no cohort, no option');

        $cohort = $this->hide_card_outside_a_cohort();
        cohort_add_member($cohort->id, $member->id);

        $this->assertTrue($this->plugin->hides_card_from($this->recruit, (int) $outsider->id));
        $this->assertTrue($this->plugin->hides_card_from($this->recruit, null));
        $this->assertFalse($this->plugin->hides_card_from($this->recruit, (int) $member->id));

        // The option off: the card shows to everybody, saying they may not apply.
        instanceoptions::set((int) $this->recruit->id, ['hidecard' => 0]);
        $this->assertFalse($this->plugin->hides_card_from($this->recruit, (int) $outsider->id));
        $this->assertFalse($this->plugin->hides_card_from($this->recruit, null));

        // The option on and no cohort: nothing to be outside of.
        instanceoptions::set((int) $this->recruit->id, ['hidecard' => 1]);
        $nocohort = clone $this->recruit;
        $nocohort->customint5 = 0;
        $this->assertFalse($this->plugin->hides_card_from($nocohort, (int) $outsider->id));
        $this->assertFalse($this->plugin->hides_card_from($nocohort, null));

        // The restore sentinel, and a cohort deleted since, have no members.
        $sentinel = clone $this->recruit;
        $sentinel->customint5 = -1;
        $this->assertTrue($this->plugin->hides_card_from($sentinel, (int) $member->id));
        $DB->delete_records('cohort', ['id' => $cohort->id]);
        $this->assertTrue($this->plugin->hides_card_from($this->recruit, (int) $member->id));
    }

    /**
     * The enrolment page shows no card to an outsider, the card to a member, and the card to an
     * outsider who already applied, who is told about their application.
     *
     * Changes that must make it fail: the card not hidden, hidden from a member, or hidden from
     * somebody who already applied.
     *
     * @return void
     */
    public function test_the_card_is_hidden_from_outsiders_who_have_not_applied(): void {
        global $PAGE;

        $PAGE->set_url('/enrol/index.php', ['id' => $this->course->id]);
        $member = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        $applied = $this->getDataGenerator()->create_user();
        $cohort = $this->hide_card_outside_a_cohort();
        cohort_add_member($cohort->id, $member->id);
        // Applied while a member, then left the cohort.
        $this->plugin->enrol_user($this->recruit, $applied->id, null, 0, 0, ENROL_USER_SUSPENDED);

        $this->setUser($outsider);
        $this->assertNull($this->plugin->enrol_page_hook($this->recruit));
        // The control: the learner method's card, which hides nothing, still shows.
        $this->assertNotNull($this->plugin->enrol_page_hook($this->student));

        $this->setUser($member);
        $this->assertStringContainsString(
            get_string('startapplication', 'enrol_apply'),
            (string) $this->plugin->enrol_page_hook($this->recruit)
        );

        $this->setUser($applied);
        $this->assertStringContainsString(
            get_string('applicationsubmitted_body', 'enrol_apply'),
            (string) $this->plugin->enrol_page_hook($this->recruit)
        );
    }
}
