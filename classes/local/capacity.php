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
 * The two capacity numbers an apply instance carries, and how many of each are held.
 *
 * **They are two different numbers answering two different questions**, and everything in this
 * class exists to keep them apart:
 *
 * - APPLICANTS (customint3) is how many applications the method will accept. Pending, deferred
 *   and approved rows all count, because each of those people is in the pipeline. When it is
 *   reached, nobody else may apply.
 * - PLACES (customint4) is how many applicants may be approved at once. Only ACTIVE rows count.
 *   When it is reached the manager is warned and nothing is blocked - see below.
 *
 * A third method, deferred(), counts a SUBSET of the applicants rather than a limit of its own:
 * it is what makes the applicant cap's one pathology legible. See its own docblock.
 *
 * The gap between them is what makes overbooking expressible: accept thirty applications for ten
 * places, because approval is discretionary and most plugins in this space wrongly assume it is
 * not. Before this class had both numbers, the single cap was labelled for one and implemented
 * as the other.
 *
 * **Places do not block an approval, by decision.** The manager is warned and decides. This
 * plugin's whole premise is that a human judges each application, and a hard block would also
 * have to be reproduced on three routes - the queue, the participants-page bulk action and the
 * per-row icon - the last of which has no channel to explain a refusal at all. The warning is
 * emitted where somebody is standing; never from complete_approval(), which runs TWICE for a
 * queue approval and would double every message.
 *
 * **Neither is queue::awaiting_decision_where().** That predicate is "not active AND not
 * expired" and answers which applications still need deciding. It is the complement of
 * places_taken() over the non-expired rows, not a substitute for either method here, and
 * borrowing it silently: fix_sql_params() tolerates surplus named parameters, so a half-refactor
 * that shares one parameter array between two counts runs clean and reddens nothing. That is
 * also why the two counts below are two methods with two full parameter arrays, rather than one
 * method taking a status flag.
 *
 * **Neither copies core's access predicate.** enrol_get_all_users_courses() and friends pair the
 * expiry test with `timestart < :now`. Somebody approved with a future start date will get
 * access, so they hold a place now. The line drawn here is "will this row ever grant access
 * again?" - expired: no; not started yet: yes.
 *
 * **Both exclude expired rows, and that is not optional.** The plugin ships
 * expiredaction = ENROL_EXT_REMOVED_KEEP, whose arm of process_expirations() is literally "no
 * changes", so an expired row survives - and stays ACTIVE, which is why places needs the clause
 * just as much as applicants does. Counted, either number becomes a ratchet that only ever
 * tightens: applications closed for ever, or a course that can never approve anybody again.
 *
 * The cost is a deliberate divergence from core's unfiltered Users column
 * (enrol/instances.php) and from enrol_self's cap. Documented rather than hidden, because a
 * teacher comparing the two screens will notice.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class capacity {
    /**
     * How many applications this method will accept in total.
     *
     * Anything at or below zero means "no limit", which is the reading every call site has
     * always had. It must stay `> 0` rather than `!== 0`: db/upgrade.php writes customint3 =
     * null on one path, and a negative would otherwise mean "permanently closed".
     *
     * @param stdClass $instance Course enrol instance.
     * @return int Applications allowed, or 0 when there is no limit.
     */
    public static function applicant_limit(stdClass $instance): int {
        $limit = (int) ($instance->customint3 ?? 0);

        return $limit > 0 ? $limit : 0;
    }

    /**
     * How many applications the method is holding.
     *
     * Every status counts - pending, waiting list and approved alike - because each of those
     * people is either in the course or waiting to be let into it.
     *
     * @param stdClass $instance Course enrol instance.
     * @return int Applications still in the pipeline.
     */
    public static function applicants(stdClass $instance): int {
        global $DB;

        return $DB->count_records_select(
            'user_enrolments',
            'enrolid = :enrolid AND (timeend = 0 OR timeend > :now)',
            ['enrolid' => (int) $instance->id, 'now' => time()]
        );
    }

    /**
     * Whether the method will accept another application.
     *
     * An unlimited instance short-circuits before the query, so this costs exactly what the
     * inline version it replaced cost: every call site already guarded its count behind a
     * `> 0` test.
     *
     * @param stdClass $instance Course enrol instance.
     * @return bool True when no further application may be made.
     */
    public static function applications_closed(stdClass $instance): bool {
        $limit = self::applicant_limit($instance);
        if ($limit === 0) {
            return false;
        }

        return self::applicants($instance) >= $limit;
    }

    /**
     * How many applicants the method may have approved at one time.
     *
     * Zero and negative both mean "no limit", matching applicant_limit() for the same reason:
     * an upgrade seeds this column at 0, and a restore can carry any value at all.
     *
     * @param stdClass $instance Course enrol instance.
     * @return int Places allowed, or 0 when there is no limit.
     */
    public static function places(stdClass $instance): int {
        $places = (int) ($instance->customint4 ?? 0);

        return $places > 0 ? $places : 0;
    }

    /**
     * How many places are occupied.
     *
     * ACTIVE rows only, which is the whole difference from applicants(): a pending application
     * and a deferred one are in the pipeline but hold no place, and ENROL_APPLY_USER_WAIT is on
     * the application side of that line rather than the place side. Written out in full rather
     * than sharing a fragment with applicants(), so the two predicates cannot be composed by
     * accident.
     *
     * @param stdClass $instance Course enrol instance.
     * @return int Places currently occupied.
     */
    public static function places_taken(stdClass $instance): int {
        global $DB;

        return $DB->count_records_select(
            'user_enrolments',
            'enrolid = :enrolid AND status = :active AND (timeend = 0 OR timeend > :now)',
            ['enrolid' => (int) $instance->id, 'active' => ENROL_USER_ACTIVE, 'now' => time()]
        );
    }

    /**
     * How many of the applications held are deferred.
     *
     * A third number, and it is a diagnosis rather than a limit: nothing is capped by it and
     * nothing is refused on the strength of it. It exists because a deferred row counts against
     * APPLICANTS for ever and nothing frees it - applicants() has no status clause,
     * wait_enrolment() writes timeend = 0 so no arm of process_expirations() can reach the row,
     * and get_unenrolself_link() demands ENROL_USER_ACTIVE (lib/enrollib.php:2517, identical on
     * both branches) so the applicant cannot withdraw. Measured on instance 4 of m502: one
     * deferred row, a limit of 1, applications_closed() true and places_taken() 0 - a course
     * refusing everybody with an empty queue and no screen able to say why.
     *
     * **The remedy is to make it visible, not to change the predicate**, and that is a hard
     * constraint rather than a preference. local_dimensions, local_unlistedcourses and
     * theme_boost_union_fundaseg all reach the cap through enrol_apply_plugin::is_full(), which
     * they call behind is_callable() because for each of them this plugin is an optional
     * dependency. Excluding deferred rows from applicants() would change what that method
     * ANSWERS for all three at once, and the mutation gate holding it (AC) proves only that it
     * still delegates - it cannot see a change in the answer. So this number is reported beside
     * the other two and cancelling the rows it counts is what frees the room.
     *
     * The predicate is written out in full with its own parameter array, like the two counts
     * above and for the same reason: fix_sql_params() tolerates surplus named parameters, so a
     * half-refactor that shares one array between two counts runs clean and reddens nothing.
     *
     * Deferred rows that have expired are excluded, exactly as the other two counts exclude
     * theirs - which keeps this number a strict subset of applicants() rather than a fourth
     * predicate that can disagree with it.
     *
     * @param stdClass $instance Course enrol instance.
     * @return int Deferred applications still counting against the applicant limit.
     */
    public static function deferred(stdClass $instance): int {
        global $CFG, $DB;

        /* ENROL_APPLY_USER_WAIT lives in the plugin's lib.php, which is not autoloaded while
           this class is, and an undefined constant is a fatal on PHP 8 - so this is a crash on
           first call rather than a wrong answer. Inside the method rather than at file scope,
           which is where the report formatter and the bulk operation already put the identical
           line: a require at the top of a namespaced class file is top-level code, and would
           drag in the MOODLE_INTERNAL guard this file correctly does without. */
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        return $DB->count_records_select(
            'user_enrolments',
            'enrolid = :enrolid AND status = :waiting AND (timeend = 0 OR timeend > :now)',
            ['enrolid' => (int) $instance->id, 'waiting' => ENROL_APPLY_USER_WAIT, 'now' => time()]
        );
    }

    /**
     * Whether every place is taken.
     *
     * Advisory: nothing refuses an approval on the strength of it. It can legitimately report
     * true with places_taken() ABOVE places(), because no route enforces it and because a
     * restore, or an administrator lowering the number, produces that state directly.
     *
     * @param stdClass $instance Course enrol instance.
     * @return bool True when no place is left.
     */
    public static function places_full(stdClass $instance): bool {
        $places = self::places($instance);
        if ($places === 0) {
            return false;
        }

        return self::places_taken($instance) >= $places;
    }
}
