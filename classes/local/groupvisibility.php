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
 * Whether a reader may be told a group's name, by core's group visibility rule.
 *
 * Every surface of this plugin that reads {groups} itself and names a group to somebody other
 * than the applicant who typed its key goes through here, so that a group core hides from a reader
 * is not named to them by this plugin either.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class groupvisibility {
    /**
     * Whether the reader may see this group, as groups_get_all_groups() would list it to them.
     *
     * Mirrors the visibility clause of groups_get_all_groups() (lib/grouplib.php): a reader holding
     * moodle/course:viewhiddengroups in the group's course sees every group; anybody else sees a
     * group whose visibility is GROUPS_VISIBILITY_ALL, and one whose visibility is MEMBERS or OWN
     * only while a member of it. NONE is hidden from everybody else, members included. That helper
     * judges $USER only, while a notice is judged for each recipient, hence this copy taking the
     * reader. A record without its visibility, or of a course that no longer exists, reads as
     * hidden.
     *
     * @param stdClass $group The {groups} record, carrying at least id, courseid and visibility.
     * @param int $readerid The user the name would be shown to.
     * @return bool True when the name may be shown.
     */
    public static function visible_to(stdClass $group, int $readerid): bool {
        global $DB;

        if (!isset($group->id, $group->courseid, $group->visibility)) {
            return false;
        }
        $visibility = (int) $group->visibility;
        if ($visibility === GROUPS_VISIBILITY_ALL) {
            return true;
        }

        $context = \context_course::instance((int) $group->courseid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }
        if (has_capability('moodle/course:viewhiddengroups', $context, $readerid)) {
            return true;
        }
        if ($visibility !== GROUPS_VISIBILITY_MEMBERS && $visibility !== GROUPS_VISIBILITY_OWN) {
            return false;
        }

        /* Not groups_is_member(): it filters by what $USER may see, which is not the reader of a
           notice. */
        return $DB->record_exists('groups_members', ['groupid' => (int) $group->id, 'userid' => $readerid]);
    }

    /**
     * Which of these groups the reader may see, judged for a whole page at once.
     *
     * The same rule as {@see visible_to()}, clause for clause, for a listing that names a group on
     * every row: the capability is asked once per course and the memberships in one query, where
     * visible_to() would run a membership query per row of a MEMBERS or OWN group. Keep the two in
     * step; test_the_batch_agrees_with_the_single_answer holds them together.
     *
     * @param array $groups The {groups} records, each carrying at least id, courseid and visibility.
     * @param int $readerid The user the names would be shown to.
     * @return array The ids of the groups the reader may see, ascending.
     */
    public static function visible_ids(array $groups, int $readerid): array {
        global $DB;

        $visible = [];
        $members = [];
        $seehidden = [];
        foreach ($groups as $group) {
            if (!isset($group->id, $group->courseid, $group->visibility)) {
                continue;
            }
            $id = (int) $group->id;
            $visibility = (int) $group->visibility;
            if ($visibility === GROUPS_VISIBILITY_ALL) {
                $visible[$id] = $id;
                continue;
            }

            $courseid = (int) $group->courseid;
            if (!array_key_exists($courseid, $seehidden)) {
                $context = \context_course::instance($courseid, IGNORE_MISSING);
                // Null for a course that no longer exists: hidden, as in visible_to().
                $seehidden[$courseid] = $context
                    ? has_capability('moodle/course:viewhiddengroups', $context, $readerid)
                    : null;
            }
            if ($seehidden[$courseid] === null) {
                continue;
            }
            if ($seehidden[$courseid]) {
                $visible[$id] = $id;
                continue;
            }
            if ($visibility === GROUPS_VISIBILITY_MEMBERS || $visibility === GROUPS_VISIBILITY_OWN) {
                $members[$id] = $id;
            }
        }

        // The memberships, in one query rather than one per group; get_in_or_equal() throws on [].
        if ($members) {
            [$insql, $params] = $DB->get_in_or_equal(array_values($members), SQL_PARAMS_NAMED, 'visgroup');
            $params['visreader'] = $readerid;
            $joined = $DB->get_fieldset_select('groups_members', 'groupid', "groupid {$insql} AND userid = :visreader", $params);
            foreach ($joined as $groupid) {
                $visible[(int) $groupid] = (int) $groupid;
            }
        }

        ksort($visible);

        return array_values($visible);
    }
}
