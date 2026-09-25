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
 * Which course, and which category, the site-wide applications queue is narrowed to.
 *
 * Offered on the site-wide queue only ({@see offered()}): the `?id=<enrolid>` queue already names
 * one course, and the mentee queue spans only the few courses a mentor's mentees applied to.
 *
 * No per-course capability check is needed. queue::listing_scope() returns the site-wide scope only
 * to a holder of enrol/apply:manageapplications at the SYSTEM context, who may manage applications
 * in every course the queue can list. The offered set is therefore exactly the courses with an apply
 * enrolment method, the set the unfiltered queue draws from: a wider list would disclose course
 * names, a narrower one would hide rows the queue shows.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coursefilter {
    /**
     * Whether this scope offers the course and category filters at all.
     *
     * @param stdClass $listing The scope, from queue::listing_scope().
     * @return bool True on the site-wide queue and nowhere else.
     */
    public static function offered(stdClass $listing): bool {
        return $listing->instance === null && $listing->mentees === null;
    }

    /**
     * Every course that has an apply enrolment method, as the operator reads it.
     *
     * For drawing the control, never for validation: format_string() asks $PAGE for a context,
     * and the dynamic table's service calls set_filterset() before validate_context() (see
     * queuefilter::resolve()). Use clean_course() to decide whether a value is real.
     *
     * @return array Course id => full name in the plain spelling, ordered by name.
     */
    public static function courses(): array {
        global $DB;

        $sql = "SELECT c.id, c.fullname, c.shortname
                  FROM {course} c
                  JOIN {enrol} e ON e.courseid = c.id AND e.enrol = :apply
              GROUP BY c.id, c.fullname, c.shortname
              ORDER BY c.fullname ASC";

        $courses = [];
        foreach ($DB->get_records_sql($sql, ['apply' => 'apply']) as $course) {
            $courses[(int) $course->id] = format_string($course->fullname, true, ['escape' => false]);
        }

        return $courses;
    }

    /**
     * Every category this reader may see, named by its full path in the plain spelling.
     *
     * Which categories, in which order, and which ancestors a path names are
     * \core_course_category::make_categories_list()'s answer, which caches it per session: the
     * categories this reader may view, by sortorder, each path skipping the ancestors they may not.
     * Its names are not used, because core formats them with format_string()'s default escaping
     * and the renderer puts these into double stashes, the option text and the filter chip, which
     * would escape them a second time. Each name along the path is formatted again here with
     * 'escape' => false, in the same filter context core uses. Decoding core's output instead would
     * be wrong, because the escaping is not reversible: a name typed as "A &amp; B" and one typed
     * as "A & B" come out of it identical.
     *
     * Not narrowed to categories that hold an apply course: that query would have to walk the tree
     * upwards for every match, and a category with no applications simply produces an empty queue.
     *
     * For drawing the control only, like courses(); clean_category() decides whether a value is real.
     *
     * @return array Category id => path name such as 'Engineering / Civil', not escaped.
     */
    public static function categories(): array {
        global $DB;

        $visible = \core_course_category::make_categories_list();
        if (!$visible) {
            return [];
        }

        $ctxselect = \context_helper::get_preload_record_columns_sql('ctx');
        $records = $DB->get_records_sql(
            "SELECT cc.id, cc.name, cc.path, {$ctxselect}
               FROM {course_categories} cc
               JOIN {context} ctx ON ctx.instanceid = cc.id AND ctx.contextlevel = :contextlevel",
            ['contextlevel' => CONTEXT_COURSECAT]
        );

        // Only the categories core listed may lend a name to a path, as in make_categories_list().
        $plain = [];
        foreach ($records as $record) {
            $id = (int) $record->id;
            if (!array_key_exists($id, $visible)) {
                continue;
            }
            \context_helper::preload_from_record($record);
            $filtercontext = \context_helper::get_navigation_filter_context(\context_coursecat::instance($id));
            $plain[$id] = format_string($record->name, true, ['context' => $filtercontext, 'escape' => false]);
        }

        $names = [];
        foreach (array_keys($visible) as $id) {
            // Deleted since the session cache was filled.
            if (!isset($records[$id])) {
                continue;
            }
            $chunks = [];
            foreach (explode('/', trim($records[$id]->path, '/')) as $ancestor) {
                if (isset($plain[(int) $ancestor])) {
                    $chunks[] = $plain[(int) $ancestor];
                }
            }
            $names[(int) $id] = implode(' / ', $chunks);
        }

        return $names;
    }

    /**
     * One course id, as it may be used.
     *
     * @param int $id What arrived.
     * @return int|null The id, or null when nothing is applied.
     */
    public static function clean_course(int $id): ?int {
        global $DB;

        if ($id <= 0) {
            return null;
        }

        // Existence only, and deliberately no formatting: this runs before validate_context().
        $exists = $DB->record_exists_sql(
            "SELECT 1 FROM {enrol} e WHERE e.courseid = :courseid AND e.enrol = :apply",
            ['courseid' => $id, 'apply' => 'apply']
        );

        return $exists ? $id : null;
    }

    /**
     * One category id, as it may be used.
     *
     * @param int $id What arrived.
     * @return int|null The id, or null when nothing is applied.
     */
    public static function clean_category(int $id): ?int {
        global $DB;

        if ($id <= 0) {
            return null;
        }

        return $DB->record_exists('course_categories', ['id' => $id]) ? $id : null;
    }

    /**
     * The predicates narrowing the queue to a category and a course.
     *
     * The category includes its whole subtree, which is what an operator filtering by
     * "Engineering" means when the courses live under "Engineering / Civil". Core stores the
     * ancestry as a materialised path on {course_categories} - `/1/7/12` - so the descendants are
     * a left-anchored prefix match on it rather than a recursive walk. A path holds only ids and
     * slashes; sql_like_escape() is applied anyway so the bound value can only ever be a prefix.
     *
     * Unlike the search, which can only scan, both predicates are on indexed columns
     * ({course}.category, {enrol}.courseid).
     *
     * @param int|null $categoryid The category, or null.
     * @param int|null $courseid The course, or null.
     * @return array [list of SQL fragments, parameters].
     */
    public static function where(?int $categoryid, ?int $courseid): array {
        global $DB;

        $wheres = [];
        $params = [];

        if ($courseid !== null) {
            $wheres[] = 'c.id = :queuecourseid';
            $params['queuecourseid'] = $courseid;
        }

        if ($categoryid !== null) {
            $path = $DB->get_field('course_categories', 'path', ['id' => $categoryid]);
            if ($path === false) {
                return [$wheres, $params];
            }

            $wheres[] = "c.category IN (
                             SELECT cc.id
                               FROM {course_categories} cc
                              WHERE cc.id = :queuecategoryid
                                 OR " . $DB->sql_like('cc.path', ':queuecategorypath', false) . "
                         )";
            $params['queuecategoryid'] = $categoryid;
            $params['queuecategorypath'] = $DB->sql_like_escape($path) . '/%';
        }

        return [$wheres, $params];
    }
}
