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

namespace enrol_apply\local;

use stdClass;

/**
 * What an applicant is told about their own application.
 *
 * One describer for every surface that tells them - the enrolment page's own panel, the
 * acknowledgement page, and the application form's refusal through message_key() - so no two
 * of them can describe the same row differently.
 *
 * Four states, and the last two are the pair that is easy to collapse. An enrolment can be
 * ACTIVE and grant access, or ACTIVE and grant none: core's is_enrolled() with the onlyactive
 * flag pairs the status with the enrolment's own window and the enrol instance's own status,
 * so an approval that has expired or has not started yet is active and shut out. Neither may
 * be told the application is still being considered, and only the second may be told that the
 * enrolment is not active.
 *
 * That is why access is a parameter rather than something read off the row: the row alone
 * cannot answer it, and every caller asks is_enrolled() itself (the enrolment page is only
 * rendered to somebody core has refused, so it gets false in practice). Deriving it here would
 * mean reimplementing is_enrolled().
 *
 * It is deliberately a describer and not a renderer. The enrolment page has no heading of its
 * own and puts the body in a core notification inside its enrol_page panel, while the
 * acknowledgement page uses all three parts.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class applicantstate {
    /** @var string The application is waiting for a decision nobody has taken. */
    private const PENDING = 'pending';

    /** @var string A decision was taken and it was to defer: still waiting, but knowingly. */
    private const DEFERRED = 'deferred';

    /** @var string Approved, and the enrolment really does grant access. */
    private const APPROVED = 'approved';

    /** @var string Approved, and yet the applicant cannot enter the course. */
    private const INACTIVE = 'inactive';

    /**
     * Heading, body and notification level for one applicant's own application.
     *
     * Returned together, all derived from one state(), so a heading can never describe a
     * different state from its body.
     *
     * @param stdClass $userenrolment The applicant's own {user_enrolments} row, carrying status.
     * @param bool $hasaccess Whether that enrolment currently lets them into the course, as the
     *        caller's own is_enrolled(..., onlyactive: true) answered it.
     * @return array Keys 'heading', 'message' and 'type', the last a \core\output\notification level.
     */
    public static function describe(stdClass $userenrolment, bool $hasaccess): array {
        $state = self::state($userenrolment, $hasaccess);

        return [
            'heading' => match ($state) {
                self::DEFERRED => get_string('applicationdeferred', 'enrol_apply'),
                self::APPROVED => get_string('applicationapproved', 'enrol_apply'),
                self::INACTIVE => get_string('applicationinactive', 'enrol_apply'),
                default => get_string('applicationsubmitted', 'enrol_apply'),
            },
            'message' => get_string(self::message_key($userenrolment, $hasaccess), 'enrol_apply'),
            /* Warning on the INACTIVE state alone: that applicant has an approval and no access,
               which is something to act on rather than to wait for. */
            'type' => match ($state) {
                self::INACTIVE => \core\output\notification::NOTIFY_WARNING,
                self::DEFERRED => \core\output\notification::NOTIFY_INFO,
                default => \core\output\notification::NOTIFY_SUCCESS,
            },
        ];
    }

    /**
     * The string id of the body, for a caller that needs an ID rather than the text.
     *
     * The application form's own refusal is that caller: it throws a moodle_exception, which
     * takes a string identifier rather than a rendered sentence.
     *
     * A literal per branch rather than an id built by concatenation, so tools that check
     * string usage can see every key.
     *
     * @param stdClass $userenrolment The applicant's own {user_enrolments} row.
     * @param bool $hasaccess Whether that enrolment currently lets them into the course.
     * @return string A string id in this plugin's language pack.
     */
    public static function message_key(stdClass $userenrolment, bool $hasaccess): string {
        return match (self::state($userenrolment, $hasaccess)) {
            self::DEFERRED => 'applicationdeferred_body',
            self::APPROVED => 'applicationapproved_body',
            self::INACTIVE => 'applicationinactive_body',
            default => 'applicationsubmitted_body',
        };
    }

    /**
     * Which of the four states this enrolment puts the applicant in.
     *
     * SUSPENDED is the default arm rather than a branch of its own: it is the pending state, and
     * so is any value neither this plugin nor core writes - a restore can carry anything, and
     * "waiting for a decision" is the only safe answer for a status this plugin does not know.
     *
     * Access is consulted on the ACTIVE arm and nowhere else: a pending applicant has no access
     * either, and must not be told their enrolment is inactive.
     *
     * @param stdClass $userenrolment The applicant's own {user_enrolments} row.
     * @param bool $hasaccess Whether that enrolment currently lets them into the course.
     * @return string One of the state constants.
     */
    private static function state(stdClass $userenrolment, bool $hasaccess): string {
        global $CFG;

        /* ENROL_APPLY_USER_WAIT lives in the plugin's lib.php, which is not autoloaded while
           this class is, and an undefined constant is a fatal on PHP 8. Same reason and same
           shape as capacity::deferred() and the report formatter. */
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        return match ((int) $userenrolment->status) {
            ENROL_APPLY_USER_WAIT => self::DEFERRED,
            ENROL_USER_ACTIVE => $hasaccess ? self::APPROVED : self::INACTIVE,
            default => self::PENDING,
        };
    }
}
