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
 * Tests for what an applicant is told about their own application.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use core\output\notification;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * Tests for what an applicant is told about their own application.
 *
 * No database: the describer reads the status and timeend off a row and returns three strings, and
 * the pages that call it are covered where they live.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(applicantstate::class)]
final class applicantstate_test extends \basic_testcase {
    /**
     * Describe an application in the given enrolment status.
     *
     * @param int $status A {user_enrolments}.status value.
     * @param bool $hasaccess Whether the enrolment currently lets them into the course.
     * @param int $timeend A {user_enrolments}.timeend value, 0 for no end.
     * @return array What the applicant would be told.
     */
    protected function describe(int $status, bool $hasaccess = false, int $timeend = 0): array {
        return applicantstate::describe(
            (object) ['id' => 1, 'status' => $status, 'timeend' => $timeend],
            $hasaccess
        );
    }

    /**
     * A suspended application is the pending one, and reads as such.
     *
     * @return void
     */
    public function test_a_pending_application_is_described_as_waiting_for_a_decision(): void {
        $state = $this->describe(ENROL_USER_SUSPENDED);

        $this->assertSame(get_string('applicationsubmitted', 'enrol_apply'), $state['heading']);
        $this->assertSame(get_string('applicationsubmitted_body', 'enrol_apply'), $state['message']);
        $this->assertSame(notification::NOTIFY_SUCCESS, $state['type']);
    }

    /**
     * A deferred application is described as deferred, not with the pending wording.
     *
     * @return void
     */
    public function test_a_deferred_application_is_described_as_deferred(): void {
        $state = $this->describe(ENROL_APPLY_USER_WAIT);

        $this->assertSame(get_string('applicationdeferred', 'enrol_apply'), $state['heading']);
        $this->assertSame(get_string('applicationdeferred_body', 'enrol_apply'), $state['message']);
        $this->assertSame(notification::NOTIFY_INFO, $state['type']);
    }

    /**
     * An ACTIVE row that really grants access is described as approved.
     *
     * applied.php only requires a row, so a fully enrolled participant who keeps the link opens it
     * legitimately and must not be told their enrolment is broken. This test and the next are why
     * access is a parameter rather than something read off the row.
     *
     * @return void
     */
    public function test_an_active_enrolment_that_grants_access_is_described_as_approved(): void {
        $state = $this->describe(ENROL_USER_ACTIVE, true);

        $this->assertSame(get_string('applicationapproved', 'enrol_apply'), $state['heading']);
        $this->assertSame(get_string('applicationapproved_body', 'enrol_apply'), $state['message']);
        $this->assertSame(notification::NOTIFY_SUCCESS, $state['type']);
    }

    /**
     * An ACTIVE row granting NO access is the fourth state, and it is a warning.
     *
     * An approval that has expired or has not started yet fails is_enrolled() with the onlyactive
     * flag, so core sends that user to the enrolment page. A warning, because it is something to
     * act on rather than something to wait for.
     *
     * @return void
     */
    public function test_an_active_enrolment_with_no_access_says_the_enrolment_is_not_active(): void {
        $state = $this->describe(ENROL_USER_ACTIVE, false);

        $this->assertSame(get_string('applicationinactive', 'enrol_apply'), $state['heading']);
        $this->assertSame(get_string('applicationinactive_body', 'enrol_apply'), $state['message']);
        $this->assertSame(notification::NOTIFY_WARNING, $state['type']);
    }

    /**
     * Access is consulted for an ACTIVE row and for no other.
     *
     * The tests above pass the access flag only as false for pending and deferred rows. Changes
     * that must make this one fail: consulting the flag outside the ACTIVE arm, for example
     * describing every row that grants access as approved.
     *
     * @return void
     */
    public function test_access_changes_nothing_for_an_undecided_or_deferred_application(): void {
        foreach ([ENROL_USER_SUSPENDED, ENROL_APPLY_USER_WAIT] as $status) {
            $this->assertSame(
                $this->describe($status, false),
                $this->describe($status, true),
                'status ' . $status
            );
        }
    }

    /**
     * A status neither this plugin nor core writes falls back to the pending wording.
     *
     * Reachable: a restore writes the archived status verbatim. "Waiting for a decision" is the
     * only answer that is safe to give somebody whose row means nothing to us - it promises
     * nothing and closes nothing off.
     *
     * @return void
     */
    public function test_an_unknown_status_falls_back_to_the_pending_wording(): void {
        $this->assertSame(
            get_string('applicationsubmitted_body', 'enrol_apply'),
            $this->describe(97)['message']
        );
    }

    /**
     * message_key() names the string describe() renders as the body.
     *
     * The application form's refusal throws a moodle_exception, which takes a string identifier;
     * message_key() lets it share the describer's mapping instead of keeping its own copy.
     *
     * @return void
     */
    public function test_the_message_key_names_the_string_the_describer_renders(): void {
        $cases = [
            [ENROL_USER_SUSPENDED, false],
            [ENROL_APPLY_USER_WAIT, false],
            [ENROL_USER_ACTIVE, false],
            [ENROL_USER_ACTIVE, true],
        ];

        foreach ($cases as [$status, $hasaccess]) {
            $row = (object) ['id' => 1, 'status' => $status, 'timeend' => 0];
            $key = applicantstate::message_key($row, $hasaccess);

            $this->assertTrue(
                get_string_manager()->string_exists($key, 'enrol_apply'),
                $key . ' must exist in the language pack'
            );
            $this->assertSame(
                get_string($key, 'enrol_apply'),
                applicantstate::describe($row, $hasaccess)['message'],
                'the key and the rendered message must name the same wording'
            );
        }
    }

    /**
     * The four states really are four different messages.
     *
     * The tests above compare against get_string() of the key each one names, so they would still
     * pass if two of those strings carried the same wording. This pins that the applicant can tell
     * the four states apart.
     *
     * @return void
     */
    public function test_the_four_states_do_not_share_a_wording(): void {
        $cases = [
            [ENROL_USER_SUSPENDED, false],
            [ENROL_APPLY_USER_WAIT, false],
            [ENROL_USER_ACTIVE, true],
            [ENROL_USER_ACTIVE, false],
        ];

        $messages = [];
        $headings = [];
        foreach ($cases as [$status, $hasaccess]) {
            $state = $this->describe($status, $hasaccess);
            $messages[] = $state['message'];
            $headings[] = $state['heading'];
        }

        $this->assertCount(4, array_unique($messages));
        $this->assertCount(4, array_unique($headings));
    }

    /**
     * A row that has lapsed is not described as waiting for a decision.
     *
     * A suspending expiredaction leaves status 1 with a timeend in the past, and the queue holds no
     * such row as awaiting a decision, so the applicant is told the enrolment is not active. The
     * control is the same status with no end and with an end in the future: both still read as
     * pending, which fails this test if the describer stops consulting the timeend at all.
     *
     * @return void
     */
    public function test_a_lapsed_application_is_not_described_as_waiting_for_a_decision(): void {
        $lapsed = $this->describe(ENROL_USER_SUSPENDED, false, time() - DAYSECS);

        $this->assertSame(get_string('applicationinactive', 'enrol_apply'), $lapsed['heading']);
        $this->assertSame(get_string('applicationinactive_body', 'enrol_apply'), $lapsed['message']);
        $this->assertSame(notification::NOTIFY_WARNING, $lapsed['type']);

        // The controls: the same status still awaiting a decision keeps the pending wording.
        foreach ([0, time() + DAYSECS] as $timeend) {
            $pending = $this->describe(ENROL_USER_SUSPENDED, false, $timeend);
            $this->assertSame(get_string('applicationsubmitted_body', 'enrol_apply'), $pending['message']);
            $this->assertNotSame($lapsed['message'], $pending['message']);
        }
    }

    /**
     * The describer and the queue never disagree about whether a decision is awaited.
     *
     * For every status other than ACTIVE and deferred, the pending wording is given exactly when
     * queue::is_awaiting_decision() says so, across the timeend values that split the two. The
     * precondition is asserted: the matrix really holds both answers, so the equality is not
     * satisfied by a describer and a queue that both always say the same thing.
     *
     * @return void
     */
    public function test_the_describer_agrees_with_the_queue_on_what_is_awaited(): void {
        $pending = get_string('applicationsubmitted_body', 'enrol_apply');
        $answers = [];

        foreach ([ENROL_USER_SUSPENDED, 97] as $status) {
            foreach ([0, time() + DAYSECS, time() - DAYSECS, -5] as $timeend) {
                $row = (object) ['id' => 1, 'status' => $status, 'timeend' => $timeend];
                $awaiting = queue::is_awaiting_decision($row);
                $answers[$awaiting ? 'awaiting' : 'lapsed'] = true;

                $this->assertSame(
                    $awaiting,
                    applicantstate::describe($row, false)['message'] === $pending,
                    'status ' . $status . ', timeend ' . $timeend
                );
            }
        }

        $this->assertSame(['awaiting', 'lapsed'], array_keys($answers));
    }

    /**
     * A deferred application stays deferred whatever its timeend says.
     *
     * The deferred state is decided by the status alone: the operator took that decision knowingly,
     * and the lapse rule is for rows nobody has looked at. The control is the pending status with
     * the same lapsed timeend, which does change its wording.
     *
     * @return void
     */
    public function test_a_deferred_application_stays_deferred_with_a_past_timeend(): void {
        $past = time() - DAYSECS;

        $deferred = $this->describe(ENROL_APPLY_USER_WAIT, false, $past);
        $this->assertSame(get_string('applicationdeferred_body', 'enrol_apply'), $deferred['message']);
        $this->assertSame(notification::NOTIFY_INFO, $deferred['type']);

        $this->assertNotSame(
            $this->describe(ENROL_USER_SUSPENDED, false, 0)['message'],
            $this->describe(ENROL_USER_SUSPENDED, false, $past)['message']
        );
    }
}
