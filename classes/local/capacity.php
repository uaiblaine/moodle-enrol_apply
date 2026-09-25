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
 * They are two different numbers answering two different questions, and this class exists to
 * keep them apart:
 *
 * - APPLICANTS (customint3) is how many applications the method will accept. Pending, deferred
 *   and approved rows all count, because each of those people is in the pipeline. When it is
 *   reached, nobody else may apply.
 * - PLACES (customint4) is how many applicants may be approved at once. Only ACTIVE rows count.
 *   When it is reached the manager is warned and nothing is blocked - see below.
 *
 * A third method, deferred(), counts a subset of the applicants rather than a limit of its own;
 * see its docblock.
 *
 * The gap between them is what makes overbooking expressible: accept thirty applications for ten
 * places, because approval is discretionary.
 *
 * Places do not block an approval, by decision. The manager is warned and decides: this plugin's
 * premise is that a human judges each application, and a hard block would have to be reproduced
 * on three routes - the queue, the participants-page bulk action and the per-row icon - the last
 * of which has no channel to explain a refusal. The warning is emitted where somebody is
 * standing, never from complete_approval(), which runs twice for a queue approval and would
 * double every message.
 *
 * Neither is queue::awaiting_decision_where(). That predicate is "not active AND not expired"
 * and answers which applications still need deciding; an approved, active learner holds a place
 * and is exactly what it excludes. Borrowing it fails silently: fix_sql_params() tolerates
 * surplus named parameters, so a half-refactor sharing one parameter array between two counts
 * runs clean. That is also why the counts below are separate methods with full parameter arrays
 * of their own, rather than one method taking a status flag.
 *
 * Neither copies core's access predicate. enrol_get_all_users_courses() and friends pair the
 * expiry test with `timestart < :now`, but somebody approved with a future start date will get
 * access, so they hold a place now. The line drawn here is "will this row ever grant access
 * again?" - expired: no; not started yet: yes.
 *
 * Both exclude expired rows. The plugin ships expiredaction = ENROL_EXT_REMOVED_KEEP, whose arm
 * of process_expirations() changes nothing, so an expired row survives - and stays ACTIVE, which
 * is why places needs the clause as much as applicants does. Counted, both counts would only ever
 * grow: applications would close for ever, and every place would read as taken, with a warning
 * nothing could clear.
 *
 * The cost is a deliberate divergence from core's unfiltered Users column (enrol/instances.php)
 * and from enrol_self's cap, which a teacher comparing the screens will notice.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class capacity {
    /**
     * How many applications this method will accept in total.
     *
     * Anything at or below zero means "no limit". It must stay `> 0` rather than `!== 0`, or a
     * negative value would mean "permanently closed", and nothing keeps one out: the instance
     * form field and the site default it starts from are PARAM_INT with no range check, and
     * restore_instance() passes the archived value through.
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
     * An unlimited instance short-circuits before the query.
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
     * Zero and negative both mean "no limit", as in applicant_limit() and for the same reason:
     * the form field and its site default accept any integer, and a restore passes the archived
     * value through.
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
     * A diagnosis rather than a limit: nothing is capped or refused on the strength of it. A
     * deferred row counts against APPLICANTS for ever and nothing frees it - applicants() has no
     * status clause, wait_enrolment() writes timeend = 0 so no arm of process_expirations() can
     * reach the row, and get_unenrolself_link() demands ENROL_USER_ACTIVE so the applicant cannot
     * withdraw. Deferred rows alone can therefore close applications while the queue of pending
     * ones is empty.
     *
     * The remedy is to make the number visible, not to change applicants(). Other plugins
     * (local_dimensions, local_unlistedcourses, theme_boost_union_fundaseg) reach the cap through
     * enrol_apply_plugin::is_full() behind is_callable(), so excluding deferred rows there would
     * silently change the answer for all of them at once. This number is reported beside the
     * other two, and cancelling the rows it counts frees the room.
     *
     * The predicate is written out in full with its own parameter array, like the two counts
     * above and for the same reason: fix_sql_params() tolerates surplus named parameters.
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
           this class is, and an undefined constant is a fatal error on PHP 8. Inside the method
           rather than at file scope, where it would be top-level code needing the
           MOODLE_INTERNAL guard this file correctly does without. */
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
