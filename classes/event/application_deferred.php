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

/**
 * A decider deferred an application, or wrote to the applicant again about a deferral.
 *
 * Fired by enrol_apply_plugin::wait_enrolment() under the rule its notification follows: when the
 * enrolment moved to the waiting list, or when the decider wrote a message for the applicant. A
 * re-deferral that only corrected the internal note is no decision and logs nothing. other says
 * which of the two it was (moved) and whether there was a message, never what it said.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class application_deferred extends application_decided {
    /**
     * The event name.
     *
     * @return string Localised name.
     */
    public static function get_name() {
        return get_string('eventapplicationdeferred', 'enrol_apply');
    }

    /**
     * What happened, for the logs.
     *
     * @return string Description; not localised, as core's own descriptions are not.
     */
    public function get_description() {
        if (!empty($this->other['moved'])) {
            return "The user with id '{$this->userid}' deferred the enrolment application of the user with id " .
                "'{$this->relateduserid}' to the enrolment method with id '{$this->other['enrolid']}'.";
        }

        return "The user with id '{$this->userid}' wrote again to the user with id '{$this->relateduserid}' about " .
            "their deferred enrolment application to the enrolment method with id '{$this->other['enrolid']}'.";
    }

    /**
     * The review page, which a deferred application still has.
     *
     * @return \moodle_url The review page.
     */
    public function get_url() {
        return new \moodle_url('/enrol/apply/manage.php', ['userenrol' => $this->other['userenrolmentid']]);
    }

    /**
     * The keys of other this event must carry, beyond the shared ones.
     *
     * @return array Key names.
     */
    protected static function own_other_keys(): array {
        return ['moved', 'withmessage'];
    }
}
