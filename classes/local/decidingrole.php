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
 * Whether a role decides applications in a course, and so whether a method is a deciding one.
 *
 * A role decides when it holds enrol/apply:manageapplications in the course context as core
 * resolves a role's capability there: the role's definition and every override on the context's
 * path, the deepest one winning, and a prohibit anywhere winning over everything. That is
 * get_roles_with_cap_in_context() (lib/accesslib.php), which answers for roles rather than for a
 * user; get_roles_with_caps_in_context() subtracts the prohibited ones from the allowed ones.
 *
 * A method is a deciding method when the role it grants on approval ({enrol}.roleid) decides in
 * its course, and a learner method otherwise, a method granting no role included
 * (docs/design/applicant-group-choice.md, C-D29). Three rules read it, all through here: the lock
 * in {@see \enrol_apply_plugin::allow_apply()} (C-D29), the scope a membership written by a
 * method gives in {@see groupscope::member_groups()} (C-D28) and the instance form's warnings
 * (C-D27).
 *
 * Nothing is memoised, as in {@see groupscope}: overrides change within a request and PHPUnit
 * resets the id sequences between tests.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class decidingrole {
    /**
     * The roles that decide applications in a course.
     *
     * @param int $courseid Course id.
     * @return int[] Role ids, ascending; empty for a course that no longer exists.
     */
    public static function roles(int $courseid): array {
        $context = context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }

        $roles = array_map('intval', array_values(get_roles_with_caps_in_context($context, [groupscope::DECIDE])));
        sort($roles);

        return $roles;
    }

    /**
     * Whether one role decides applications in a course.
     *
     * @param int $roleid Role id; 0, no role, is never among the deciding roles.
     * @param int $courseid Course id.
     * @return bool True when the role holds the deciding capability in the course.
     */
    public static function decides(int $roleid, int $courseid): bool {
        return in_array($roleid, self::roles($courseid), true);
    }

    /**
     * Whether a method is a deciding method: the role it grants on approval decides in its course.
     *
     * @param stdClass $instance The {enrol} record, with courseid and roleid.
     * @return bool True for a deciding method, false for a learner method.
     */
    public static function method_decides(stdClass $instance): bool {
        return self::decides((int) ($instance->roleid ?? 0), (int) $instance->courseid);
    }

    /**
     * Whether each of a set of methods is a deciding method: {@see method_decides()} for each one, with the
     * deciding roles read once per course of the set rather than once per method.
     *
     * Kept within the call, never across calls (see the class docblock), for the queue's "By course" view,
     * whose page may hold several methods of one course.
     *
     * @param array $instances {enrol} records, each with id, courseid and roleid.
     * @return array Instance id => true for a deciding method, false for a learner method.
     */
    public static function methods_decide(array $instances): array {
        $roles = [];
        $decides = [];
        foreach ($instances as $instance) {
            $courseid = (int) $instance->courseid;
            $roles[$courseid] ??= self::roles($courseid);
            $decides[(int) $instance->id] = in_array((int) ($instance->roleid ?? 0), $roles[$courseid], true);
        }

        return $decides;
    }
}
