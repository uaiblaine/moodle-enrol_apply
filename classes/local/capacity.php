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
 * How many places an apply instance has, and how many are still held.
 *
 * One definition, read by the enrolment card, the form's access check and the write door.
 * It used to be written out at all three, which is how it came to be enforced on some doors
 * and not others: the cap was held by a single test on one of the three, so deleting it
 * reddened nothing at all.
 *
 * **This is NOT queue::awaiting_decision_where(), and composing the two is the most damaging
 * mistake available here.** That predicate is "not active AND not expired" - it answers which
 * applications are still waiting for somebody to decide them. Capacity asks a different
 * question: which enrolments still hold a place. An approved, active learner holds one, and
 * that is exactly what the queue's `status != active` clause excludes. Borrowing it, or
 * indexing into the array it returns to lift the timeend half out, makes the cap exceedable by
 * the number of approvals - and it does so silently, because fix_sql_params() tolerates
 * surplus named parameters, so a half-refactor that passes the whole parameter array with one
 * clause runs clean and fails no test.
 *
 * **It is also not core's access predicate.** enrol_get_all_users_courses() and friends pair
 * the timeend test with `timestart < :now`, and that half must not be copied: somebody enrolled
 * with a future start date will get access, so they hold a place now. The line this class
 * draws is "will this row ever grant access again?" - expired: no; not started yet: yes.
 *
 * **It diverges from core's own count, on purpose.** The Users column on the Enrolment methods
 * page (enrol/instances.php) counts {user_enrolments} unfiltered, and so does enrol_self's cap.
 * So an administrator can see "Users: 10" against a limit of 10 on a course that is still
 * accepting applications. That is confusing, and it is the lesser of the two evils: counting
 * expired rows makes the cap a ratchet that only ever tightens. This plugin ships
 * expiredaction = ENROL_EXT_REMOVED_KEEP, whose arm in process_expirations() is literally "no
 * changes", so an expired enrolment keeps its row forever - and a course whose places were
 * filled and then expired had applications closed permanently, with an empty approval queue
 * and no screen anywhere able to say why.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class capacity {
    /**
     * How many places the instance allows.
     *
     * Anything at or below zero means "no limit", which is the reading all three call sites
     * have always had. It must stay `> 0` rather than `!== 0`: db/upgrade.php writes
     * customint3 = null on one path, and a negative value would otherwise turn an uncapped
     * instance into a permanently full one.
     *
     * @param stdClass $instance Course enrol instance.
     * @return int Places allowed, or 0 when the instance is uncapped.
     */
    public static function limit(stdClass $instance): int {
        $limit = (int) ($instance->customint3 ?? 0);

        return $limit > 0 ? $limit : 0;
    }

    /**
     * How many enrolments still hold a place on the instance.
     *
     * Every status counts - pending, waiting list and approved alike - because each of those
     * people is either in the course or waiting to be let into it. What does not count is an
     * enrolment whose period has run out, which is the whole point of this class.
     *
     * @param stdClass $instance Course enrol instance.
     * @return int Enrolments still occupying a place.
     */
    public static function taken(stdClass $instance): int {
        global $DB;

        return $DB->count_records_select(
            'user_enrolments',
            'enrolid = :enrolid AND (timeend = 0 OR timeend > :now)',
            ['enrolid' => (int) $instance->id, 'now' => time()]
        );
    }

    /**
     * Whether the instance has no place left.
     *
     * An uncapped instance short-circuits before the query, so this costs exactly what the
     * inline version cost: all three call sites already guarded their count behind `$cap > 0`.
     *
     * @param stdClass $instance Course enrol instance.
     * @return bool True when no place is left.
     */
    public static function is_full(stdClass $instance): bool {
        $limit = self::limit($instance);
        if ($limit === 0) {
            return false;
        }

        return self::taken($instance) >= $limit;
    }
}
