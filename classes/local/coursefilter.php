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
 * **Offered on one scope only, and the reason is not tidiness.** With `?id=<enrolid>` the queue
 * already names one course, so the control would be a filter over a set of one. The mentee queue
 * does span courses, but a mentor sees a handful of them and the control would be noise on a list
 * short enough to read. That leaves the site-wide queue, which is the one that can hold every
 * application on the site at once and the only one where narrowing by course is the difference
 * between a page and a search.
 *
 * **Nothing here needs a per-course capability check, and that is a property of the scope rather
 * than an omission.** queue::listing_scope() only returns the site-wide scope to a holder of
 * enrol/apply:manageapplications at the SYSTEM context, so a reader who reaches this filter can
 * manage applications in every course the queue could list. The offered set is therefore exactly
 * "courses with an apply enrolment method", which is the same set the unfiltered queue draws from.
 * Offer a wider list and the control becomes an oracle over course names; offer a narrower one and
 * it hides rows the queue is showing.
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
     * **Formatted, so this is for the RENDERER and not for validation.** format_string() asks
     * $PAGE for a context, and the dynamic table's service calls set_filterset() before
     * validate_context() - the same trap queuefilter::resolve() carries a warning about. Use
     * clean_course() to decide whether a value is real; use this to draw the control.
     *
     * @return array Course id => full name, ordered by name.
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
     * Every category the site has, indented by depth.
     *
     * Core's own helper, which is request-cached and already skips the categories this reader may
     * not see. Not narrowed to categories that hold an apply course: that query would have to walk
     * the tree upwards for every match, and a category with no applications simply produces an
     * empty queue, which is an honest answer rather than a broken one.
     *
     * @return array Category id => indented name.
     */
    public static function categories(): array {
        return \core_course_category::make_categories_list();
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
     * **The category includes its whole subtree**, which is what an operator filtering by
     * "Engineering" means when the courses live under "Engineering / Civil". Core stores the
     * ancestry as a materialised path on {course_categories} - `/1/7/12` - so the descendants are
     * a prefix match on it rather than a recursive walk. The path is read here and bound as a
     * value, so the LIKE is anchored at the left and its wildcard is the only one: a category
     * whose own path holds a percent sign cannot exist, but sql_like_escape() is applied anyway
     * because the day it can is not the day to find out.
     *
     * Both predicates are indexable, which is the point of the whole control: {course}.category
     * and {enrol}.courseid carry indexes, so this narrows the row set BEFORE the search's LIKE
     * ever runs. That is a different kind of filter from the search, which can only scan.
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
