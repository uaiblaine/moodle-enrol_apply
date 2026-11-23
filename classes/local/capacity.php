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
class capacity {
    /** @var int Instance ids one statement of {@see states()} reads. */
    protected const BATCH = 5000;

    /**
     * How many applications this method will accept in total.
     *
     * Anything at or below zero means "no limit". It must stay `> 0` rather than `!== 0`, or a
     * negative value would mean "permanently closed": the instance form and the site default
     * refuse a negative, but restore_instance() passes the archived value through.
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
     * the form and its site default refuse a negative, but a restore passes the archived value
     * through.
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

    /**
     * What the queue's capacity tiles show: the two numbers held against their limits, and whether
     * the method accepts applications now.
     *
     * The one definition of "open" the tiles read, the queue page's
     * (renderer::queue_capacity_context()) and the headless summary's
     * (\enrol_apply\local\queueengine::summary()). It is not allow_apply(), whose cohort clause asks
     * whether the CURRENT user may apply, so a manager outside a restricted cohort would be told the
     * method is closed: it is allow_apply()'s instance-level clauses (status, customint6, the
     * enrolment dates) plus applications_closed(), which every caller of allow_apply() checks
     * beside it. A new instance-level clause in allow_apply() belongs here as well.
     *
     * Each count is taken only where its limit is set, as the tiles show it only then.
     * applications_closed() is inlined to reuse the count of applicants rather than run the same
     * COUNT again; applicant_limit() clamps a negative to 0, so `$limit > 0` is that method's
     * `$limit !== 0`. Keep the two in step.
     *
     * The two "full" flags are the same two rules as places_full() and applications_closed(), read
     * off the numbers already counted here: placesfull is every place taken, limitreached is the
     * applicant limit reached. Either is what a method "at its limit" means to the site-wide queue's
     * tile and label ({@see states()}).
     *
     * @param stdClass $instance Course enrol instance.
     * @param int|null $now The time to judge the enrolment window at, now when null.
     * @return stdClass places, taken (0 without places), limit, held (0 without a limit), open,
     *     startdate and enddate (0 for none), placesfull and limitreached.
     */
    public static function state(stdClass $instance, ?int $now = null): stdClass {
        $now = $now ?? time();
        $places = self::places($instance);
        $limit = self::applicant_limit($instance);
        $held = $limit > 0 ? self::applicants($instance) : 0;
        $taken = $places > 0 ? self::places_taken($instance) : 0;

        return self::compose($instance, $now, $taken, $held);
    }

    /**
     * What {@see state()} returns for every instance of a set, in two statements per chunk of ids.
     *
     * The batched twin of state(), for the queues that span methods: one grouped count of the places
     * taken over the instances that have places, one of the applications held over those that have
     * an applicant limit, each read in chunks of BATCH ids so that no statement outgrows a database's
     * bind-parameter cap (a chunk with nothing to read costs no statement). Each statement is its single-instance
     * method's predicate clause for clause, grouped by the method: places_taken() (ACTIVE rows not expired) and applicants() (every
     * status, not expired), both with their own full parameter arrays for the reason the class
     * docblock gives, and both reading the clock those methods read (time(), not $now, which only
     * judges the enrolment window, as in state()). Keep the three in step with those two methods;
     * test_the_batch_is_state_instance_by_instance holds it.
     *
     * @param array $instances Course enrol instances, each a full {enrol} record.
     * @param int|null $now The time to judge the enrolment windows at, now when null.
     * @return array Instance id => what state() returns for it; empty for an empty set, with no query.
     */
    public static function states(array $instances, ?int $now = null): array {
        global $DB;

        if (!$instances) {
            return [];
        }

        $now = $now ?? time();
        $withplaces = [];
        $withlimit = [];
        foreach ($instances as $instance) {
            if (self::places($instance) > 0) {
                $withplaces[] = (int) $instance->id;
            }
            if (self::applicant_limit($instance) > 0) {
                $withlimit[] = (int) $instance->id;
            }
        }

        // The places taken: places_taken()'s predicate, grouped by the method, one statement per chunk.
        $taken = [];
        foreach (array_chunk($withplaces, static::batch_size()) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'placesenrol');
            $params['active'] = ENROL_USER_ACTIVE;
            $params['now'] = time();
            $taken += $DB->get_records_sql_menu(
                "SELECT enrolid, COUNT(1) AS taken
                   FROM {user_enrolments}
                  WHERE enrolid {$insql} AND status = :active AND (timeend = 0 OR timeend > :now)
               GROUP BY enrolid",
                $params
            );
        }

        // The applications held: applicants()'s predicate, grouped by the method, one statement per chunk.
        $held = [];
        foreach (array_chunk($withlimit, static::batch_size()) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'heldenrol');
            $params['now'] = time();
            $held += $DB->get_records_sql_menu(
                "SELECT enrolid, COUNT(1) AS held
                   FROM {user_enrolments}
                  WHERE enrolid {$insql} AND (timeend = 0 OR timeend > :now)
               GROUP BY enrolid",
                $params
            );
        }

        $states = [];
        foreach ($instances as $instance) {
            $id = (int) $instance->id;
            $states[$id] = self::compose($instance, $now, (int) ($taken[$id] ?? 0), (int) ($held[$id] ?? 0));
        }

        return $states;
    }

    /**
     * How many instance ids one statement of {@see states()} reads.
     *
     * A seam for the tests; the databases cap bind parameters (65,535 on PostgreSQL), and a site can
     * hold more methods than that.
     *
     * @return int Ids per statement, at least one.
     */
    protected static function batch_size(): int {
        return static::BATCH;
    }

    /**
     * The state of one instance from its two counts, the one shape state() and states() return.
     *
     * @param stdClass $instance Course enrol instance.
     * @param int $now The time to judge the enrolment window at.
     * @param int $taken Places taken; ignored without places.
     * @param int $held Applications held; ignored without an applicant limit.
     * @return stdClass See state().
     */
    private static function compose(stdClass $instance, int $now, int $taken, int $held): stdClass {
        $places = self::places($instance);
        $limit = self::applicant_limit($instance);
        $held = $limit > 0 ? $held : 0;
        $taken = $places > 0 ? $taken : 0;
        $startdate = (int) ($instance->enrolstartdate ?? 0);
        $enddate = (int) ($instance->enrolenddate ?? 0);

        $open = $instance->status == ENROL_INSTANCE_ENABLED
            && !empty($instance->customint6)
            && !($startdate > 0 && $startdate > $now)
            && !($enddate > 0 && $enddate < $now)
            && !($limit > 0 && $held >= $limit);

        return (object) [
            'places' => $places,
            'taken' => $taken,
            'limit' => $limit,
            'held' => $held,
            'open' => $open,
            'startdate' => $startdate,
            'enddate' => $enddate,
            // The rule of places_full(): advisory, every place taken.
            'placesfull' => $places > 0 && $taken >= $places,
            // The rule of applications_closed(): no further application accepted.
            'limitreached' => $limit > 0 && $held >= $limit,
        ];
    }
}
