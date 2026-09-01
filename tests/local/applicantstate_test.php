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
 * No database: the describer reads one status off a row and returns three strings, and the two
 * pages that call it are covered where they live. \basic_testcase is what the fleet standard
 * asks for a class with no $DB in it.
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
     * @return array What the applicant would be told.
     */
    protected function describe(int $status, bool $hasaccess = false): array {
        return applicantstate::describe((object) ['id' => 1, 'status' => $status], $hasaccess);
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
     * A deferred application says a decision WAS taken, which the pending wording denies.
     *
     * This is the defect the class exists for: both pages used to branch on nothing but "does a
     * row exist", so a deferred applicant read that their application was waiting for a decision
     * somebody had in fact already taken.
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
     * An ACTIVE row that really grants access says so, and says nothing alarming.
     *
     * The pair below is the whole reason access is a parameter rather than something read off
     * the row. applied.php's gate asks for a row and nothing more, so a fully enrolled
     * participant who keeps the link opens it perfectly legitimately - and a describer that read
     * ACTIVE as "approved with no access" told a working participant their enrolment was broken
     * and sent them to bother their teacher.
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
     * Core renders the enrolment page for a user is_enrolled() rejects with the onlyactive flag,
     * so an approval that has expired or has not started yet lands there - and this plugin has
     * already shipped a defect producing exactly that row. It is a WARNING because it is
     * something to act on rather than something to wait for.
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
     * Access is consulted for an ACTIVE row and for no other, which is what keeps it narrow.
     *
     * Without this, a describer that simply forwarded the flag - "no access, so something is
     * wrong" - would satisfy every assertion above while telling a pending applicant, who by
     * construction has no access, that their approved enrolment is not active.
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
     * The body's string id is offered as an id, for the caller that cannot take a sentence.
     *
     * The application form's refusal throws a moodle_exception, which takes a string identifier.
     * Without this the form would need its own copy of the state-to-wording mapping, and a third
     * copy is how the three surfaces came to disagree in the first place.
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
            $row = (object) ['id' => 1, 'status' => $status];
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
     * The load-bearing assertion of the file. Every test above would pass against a describer
     * that returned one wording for everything, if that wording happened to be the one each of
     * them named - and against one whose branches had been collapsed by a refactor into a single
     * string id.
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
}
