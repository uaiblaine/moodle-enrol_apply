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

namespace enrol_apply\event;

use core\event\base;

/**
 * A decider approved an application: the enrolment became active, with its role and groups.
 *
 * Built by enrol_apply_plugin::complete_approval() and logged once per approval whichever route
 * reached it (the queue, the review page, the participants page's bulk operation, core's "Edit
 * enrolment"), always once the enrolment is active,
 * and never for a group key's admission, which logs application_admitted_by_key instead; see
 * \enrol_apply\local\decisionlog. other carries the groups the applicant joined and the role
 * assigned (0 for none), and whether the decider chose them or the method's defaults applied.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class application_approved extends application_decided {
    /**
     * The event name.
     *
     * @return string Localised name.
     */
    public static function get_name() {
        return get_string('eventapplicationapproved', 'enrol_apply');
    }

    /**
     * What happened, for the logs.
     *
     * The groups are read defensively: a log restore turns the array into NOT_MAPPED.
     *
     * @return string Description; not localised, as core's own descriptions are not.
     */
    public function get_description() {
        $groups = is_array($this->other['groups'] ?? null) ? array_map('intval', $this->other['groups']) : [];
        $joined = $groups ? "joined the groups with ids '" . implode(', ', $groups) . "'" : 'joined no group';
        $roleid = (int) ($this->other['roleid'] ?? 0);
        $role = $roleid > 0 ? "was given the role with id '{$roleid}'" : 'was given no role';

        return "The user with id '{$this->userid}' approved the enrolment application of the user with id " .
            "'{$this->relateduserid}' to the enrolment method with id '{$this->other['enrolid']}'; the applicant " .
            "{$joined} and {$role}.";
    }

    /**
     * The keys of other this event must carry, beyond the shared ones.
     *
     * @return array Key names.
     */
    protected static function own_other_keys(): array {
        return ['groups', 'roleid', 'chosen'];
    }

    /**
     * How the ids in other map on a log restore.
     *
     * The groups cannot be mapped: core's log restore hands each value of other to
     * get_mappingid() as one scalar.
     *
     * @return array The mapping.
     */
    public static function get_other_mapping() {
        return parent::get_other_mapping() + [
            'groups' => ['db' => 'groups', 'restore' => base::NOT_MAPPED],
            'roleid' => ['db' => 'role', 'restore' => 'role'],
        ];
    }
}
