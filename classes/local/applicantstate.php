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
 * One describer for the two pages that tell them - the enrolment page's own panel and the
 * acknowledgement page - because before this each of them branched on nothing but "does a row
 * exist" and therefore read the PENDING wording to everybody. A deferred applicant was told
 * their application was waiting for a decision that had in fact been taken; somebody approved
 * onto an enrolment that is not active was told the same thing, which is the state the plugin
 * has already shipped a defect into once (an approval inheriting a past expiry leaves the row
 * ACTIVE with no access, under the expiredaction this plugin ships).
 *
 * FOUR states, and the last two are the pair that is easy to collapse. An enrolment can be
 * ACTIVE and grant access, or ACTIVE and grant none - core's is_enrolled() with the onlyactive
 * flag pairs the status with the enrolment's own window, so an approval that has expired or has
 * not started yet is active and shut out. Telling either of them their application is still
 * being considered sends them to wait for a message that will never come; telling the FIRST of
 * them that their enrolment is not active is worse, because it is simply false and it sends a
 * working participant to bother their teacher.
 *
 * That last sentence is why access is a PARAMETER rather than something read off the row. The
 * row alone cannot answer it: core pairs the status and the window with the enrol INSTANCE's own
 * status, and each caller already knows the answer for its own page - the enrolment page is only
 * rendered to somebody without access, and the other two callers have the course context in hand
 * and ask core directly. Deriving it here would be this plugin reimplementing is_enrolled(), and
 * getting a third of it wrong is exactly how a working participant reads a broken-enrolment
 * warning.
 *
 * It is deliberately a describer and not a renderer. The two callers put the result in
 * different places: the enrolment page has no heading of its own and puts the body in a core
 * notification inside its enrol_page panel, while the acknowledgement page uses all three parts
 * and also has its own profile machinery below them.
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
     * Returned together rather than as three methods, so the three cannot fall out of step:
     * a heading saying "Application submitted" over a body explaining a deferral is the exact
     * failure this class exists to remove, and three parallel match statements is how it comes
     * back.
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
            /* Warning on the INACTIVE state alone, and it is not decoration: that applicant has
               an approval and no access, which is something to act on rather than to wait for.
               The other three are states the plugin is working correctly in. */
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
     * takes a string identifier and cannot be handed a rendered sentence. That refusal had the
     * same defect the two pages had - one wording for every state, so a deferred applicant who
     * reopened the form was told their application had been "successfully sent" - and this is
     * how it takes the same fix without a second copy of the mapping.
     *
     * A literal per branch, which is the shape the fleet standard asks for: the ban is on
     * building an id by concatenation, not on choosing between fixed ones.
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
     * A literal match per branch and never a computed string id, which the fleet standard bans
     * and the lang file checker cannot see. SUSPENDED is the default arm rather than a branch
     * of its own: it is the pending state, and so is any value neither this plugin nor core
     * writes - a restore can carry anything at all, and "waiting for a decision" is the only
     * answer that is safe to give somebody whose row means nothing to us.
     *
     * Access is consulted on the ACTIVE arm and nowhere else, which is what keeps it narrow: a
     * pending applicant has no access either, and telling them their approved enrolment is not
     * active would be a fresh falsehood rather than the one being fixed.
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
