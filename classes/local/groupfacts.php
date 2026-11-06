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

use context_course;
use stdClass;

/**
 * What every group chooser of a decision reads about the groups it offers.
 *
 * One read for the queue's chooser, the review page's cards and search, and the participants
 * page's bulk form, so the decision surfaces cannot classify a group differently. The list is
 * {@see groupscope::choosable_groups()}, which confirm_enrolment() allowlists the posted ids
 * against and which narrows a decider in separate groups to their own (S6), so a narrowed reader
 * is never told another group's seats (V-D3).
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class groupfacts {
    /**
     * The groups the current user may choose on an approval in a course, with their seats.
     *
     * Natural order (RP-D22): groups_get_all_groups() sorts by name in the database's collation,
     * which puts "Turma 10" before "Turma 2".
     *
     * A full group is one whose members reach its seats (members >= seats); it stays selectable
     * (G15, R5): seats warn, they never block. A group declaring no seats, or every group without
     * local_groupdist, has hasseats false.
     *
     * The name is the plain spelling ('escape' => false): the queue's and the review page's
     * templates double stash it, and a moodleform option escapes it itself, see
     * {@see option_text()}.
     *
     * @param int $courseid Course id.
     * @param context_course $coursecontext Its context.
     * @return array Group id => object with id, name (plain), idnumber, hasseats, members, seats,
     *     full and percent (whole, clamped to the bar).
     */
    public static function for_course(int $courseid, context_course $coursecontext): array {
        $coursegroups = groupscope::choosable_groups($courseid);
        \core_collator::asort_objects_by_property($coursegroups, 'name', \core_collator::SORT_NATURAL);
        $seats = groupseats::for_groups(array_keys($coursegroups));

        $facts = [];
        foreach ($coursegroups as $group) {
            $seat = $seats[(int) $group->id] ?? null;
            $hasseats = $seat !== null && $seat->seats !== null;
            $members = $hasseats ? (int) $seat->members : 0;
            $total = $hasseats ? (int) $seat->seats : 0;
            $facts[(int) $group->id] = (object) [
                'id' => (int) $group->id,
                'name' => format_string($group->name, true, ['context' => $coursecontext, 'escape' => false]),
                'idnumber' => trim((string) ($group->idnumber ?? '')),
                'hasseats' => $hasseats,
                'members' => $members,
                'seats' => $total,
                'full' => $hasseats && $members >= $total,
                // A group declaring no seat at all is full from its first member.
                'percent' => $total > 0 ? self::percent($members, $total) : 100,
            ];
        }

        return $facts;
    }

    /**
     * The text of a group as an option of a chooser (RP-D21).
     *
     * Plain text: the name, the idnumber in parentheses when set, then the seat state in words,
     * never colour. A full group is worded as such and stays selectable (R5). The caller escapes
     * it where its sink renders raw.
     *
     * @param stdClass $fact As {@see for_course()} returns one.
     * @return string The plain option text.
     */
    public static function option_text(stdClass $fact): string {
        $label = $fact->idnumber !== ''
            ? get_string('reviewgroupidnumber', 'enrol_apply', (object) ['name' => $fact->name, 'idnumber' => $fact->idnumber])
            : $fact->name;
        if (!$fact->hasseats) {
            return $label;
        }

        $a = (object) ['name' => $label, 'members' => $fact->members, 'seats' => $fact->seats];

        return $fact->full
            ? get_string('reviewgroupoptionfull', 'enrol_apply', $a)
            : get_string('reviewgroupoption', 'enrol_apply', $a);
    }

    /**
     * A meter's width, as a whole percentage that never leaves the bar.
     *
     * @param int $value The count.
     * @param int $total The limit it sits against, greater than zero.
     * @return int Whole percentage between 0 and 100.
     */
    private static function percent(int $value, int $total): int {
        return (int) min(100, max(0, round($value * 100 / $total)));
    }
}
