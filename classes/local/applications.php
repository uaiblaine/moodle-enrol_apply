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

use context_user;

/**
 * Helpers shared by the enrolment application screens.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class applications {
    /**
     * Users the current user mentors.
     *
     * A mentee is a user the current user holds a role assignment over in that user's own
     * context — Moodle's standard mentor pattern, enumerated the same way core does it in
     * blocks/mentees/block_mentees.php: role_assignments joined to context at
     * CONTEXT_USER. The capability is then confirmed per candidate with has_capability()
     * rather than by joining role_capabilities, because only has_capability() honours
     * overrides, prohibits and the admin bypass; the candidate set is small enough
     * (one row per mentee assignment) that the per-user check is cheap.
     *
     * @return array Array of user ids, empty when the current user mentors nobody.
     */
    public static function get_mentees(): array {
        global $DB, $USER;

        $sql = "SELECT DISTINCT ctx.instanceid AS userid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :contextlevel
                 WHERE ra.userid = :mentorid AND ctx.instanceid <> :self";
        $candidates = $DB->get_fieldset_sql($sql, [
            'contextlevel' => CONTEXT_USER,
            'mentorid' => $USER->id,
            'self' => $USER->id,
        ]);

        $mentees = [];
        foreach ($candidates as $candidateid) {
            $usercontext = context_user::instance($candidateid, IGNORE_MISSING);
            if ($usercontext && has_capability('enrol/apply:manageapplications', $usercontext)) {
                $mentees[] = (int) $candidateid;
            }
        }

        return $mentees;
    }
}
