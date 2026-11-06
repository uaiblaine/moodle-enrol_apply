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
 * The approval queue's group filter: what it offers each reader, and what each value selects.
 *
 * Offered on the instance queue alone (docs/design/group-screens.html, V-D1): groups belong to one
 * course, and the site-wide queue offers a course filter first. Its values (review-page-concepts,
 * RP-D27) are a group id; {@see NONE}, the applications in no group, only for a reader who sees
 * those at all; and {@see MINE}, the reader's own groups, only for a reader who is not narrowed and
 * belongs to at least one group. A reader narrowed by separate groups gets neither: their queue
 * already is their groups, and it never holds an application without one (S3).
 *
 * Every value is cleaned against the list offered to this reader before it reaches SQL, and the
 * predicate is an extra AND after the separate-groups scope, never instead of it
 * ({@see \enrol_apply\table\applications::scope_where()}): a forged id of another group is no
 * filter at all, and could only narrow the listing to nothing if it ever got through.
 *
 * In a course in visible groups mode the filter defaults to the reader's own groups, as core's
 * participants page does there (S2, V-D2), as a value the reader can remove, never a scope. The
 * default is applied where the request is read ({@see \enrol_apply\table\applications::request_filters()}),
 * so a page without a group parameter gets it, and {@see ANY} is the explicit "every group" the
 * page writes into its own urls once the reader has removed it.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class groupfilter {
    /** @var string The filter's name, in the query string and in the dynamic table's filterset. */
    public const NAME = 'group';

    /** @var string The applications whose newest record names no group of the course. */
    public const NONE = 'none';

    /** @var string The applications of the groups the reader belongs to. */
    public const MINE = 'mine';

    /** @var string Every group, explicitly: what a removed default leaves in the url. */
    public const ANY = 'all';

    /**
     * @var int Above this many choosable groups the control is a search rather than a select (RP-D18).
     *
     * Counted after the reader's narrowing, so a moderator of a few groups keeps a plain select.
     */
    public const THRESHOLD = 8;

    /**
     * The groups the reader may pick in the filter, in natural order.
     *
     * The groups of the scope's course the current user may see, as groups_get_all_groups() lists
     * them to $USER, narrowed to their own for a reader narrowed by separate groups (the scope's
     * `groups`, derived from the course and $USER by queue::listing_scope()). Sorted with
     * core_collator::SORT_NATURAL (RP-D22): the database's own order puts "Turma 10" before
     * "Turma 2".
     *
     * @param stdClass $listing The scope, from {@see queue::listing_scope()}.
     * @return array The {groups} records, keyed by id; empty off the instance queue.
     */
    public static function groups(stdClass $listing): array {
        if (empty($listing->instance) || !empty($listing->mentees)) {
            return [];
        }

        $groups = groups_get_all_groups((int) $listing->instance->courseid);
        if ($listing->groups !== null) {
            $groups = array_intersect_key($groups, array_flip($listing->groups));
        }

        \core_collator::asort_objects_by_property($groups, 'name', \core_collator::SORT_NATURAL);

        return $groups;
    }

    /**
     * The values this reader may send, each as the string the request carries.
     *
     * Empty when the filter is not offered at all: off the instance queue, and on a queue whose
     * reader may pick no group.
     *
     * @param stdClass $listing The scope, from {@see queue::listing_scope()}.
     * @return array Group ids as strings, then {@see MINE} and {@see NONE} where offered.
     */
    public static function offered(stdClass $listing): array {
        $groups = self::groups($listing);
        if (!$groups) {
            return [];
        }

        $values = array_map('strval', array_keys($groups));

        // Only who is not narrowed: a narrowed reader's queue already is their groups (RP-D27).
        if ($listing->groups === null) {
            if (self::own_groups($listing)) {
                $values[] = self::MINE;
            }
            // Who is not narrowed sees the applications without a key too (S3).
            $values[] = self::NONE;
        }

        return $values;
    }

    /**
     * A value off the request, if it is one this reader was offered.
     *
     * @param array $offered What {@see offered()} returned for this reader.
     * @param string $value The raw value.
     * @return string|null The value, or null for no filter.
     */
    public static function clean(array $offered, string $value): ?string {
        $value = trim($value);

        return in_array($value, $offered, true) ? $value : null;
    }

    /**
     * What the filter holds when the request names no group (V-D2).
     *
     * {@see MINE} in a course in visible groups mode, for a reader offered it; nothing anywhere
     * else. Separate groups need no default, since they narrow the scope itself, and a reader in
     * no group has nothing to default to.
     *
     * @param stdClass $listing The scope, from {@see queue::listing_scope()}.
     * @param array $offered What {@see offered()} returned for this reader.
     * @return string|null The default value, or null for none.
     */
    public static function default_value(stdClass $listing, array $offered): ?string {
        if (!in_array(self::MINE, $offered, true)) {
            return null;
        }

        $course = get_course((int) $listing->instance->courseid);

        return (int) groups_get_course_groupmode($course) === VISIBLEGROUPS ? self::MINE : null;
    }

    /**
     * The predicate one cleaned value adds, over a user enrolment aliased "ue".
     *
     * A group id and {@see MINE} reuse {@see groupscope::where()}, so "which group an application
     * belongs to" is the newest record's key group here exactly as in the separate-groups scope.
     * {@see NONE} is its complement over the groups that exist: no newest record naming a group of
     * the course. An application whose group has been deleted since lands there, beside the ones
     * without a key, so every row is reached by exactly one value of the filter (or by none, when
     * its group is hidden from the reader).
     *
     * @param stdClass $listing The scope, from {@see queue::listing_scope()}.
     * @param string $value A value {@see clean()} accepted.
     * @return array Two elements: the where fragment and its named parameters.
     */
    public static function where(stdClass $listing, string $value): array {
        if ($value === self::MINE) {
            return groupscope::where(self::own_groups($listing));
        }

        if ($value === self::NONE) {
            return [
                "NOT EXISTS (
                    SELECT 1
                      FROM {enrol_apply_submission} nonesub
                      JOIN {groups} nonegroup ON nonegroup.id = nonesub.keygroupid
                     WHERE nonesub.userenrolmentid = ue.id
                       AND NOT EXISTS (
                           SELECT 1
                             FROM {enrol_apply_submission} nonenewer
                            WHERE nonenewer.userenrolmentid = nonesub.userenrolmentid
                              AND (nonenewer.timecreated > nonesub.timecreated
                                   OR (nonenewer.timecreated = nonesub.timecreated AND nonenewer.id > nonesub.id))
                       )
                )",
                [],
            ];
        }

        return groupscope::where([(int) $value]);
    }

    /**
     * The groups of the scope's course the current user belongs to and may see.
     *
     * @param stdClass $listing The scope, from {@see queue::listing_scope()}.
     * @return array Group ids, ascending.
     */
    private static function own_groups(stdClass $listing): array {
        global $USER;

        return groupscope::member_groups((int) $listing->instance->courseid, (int) $USER->id);
    }
}
