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
 * A decider cancelled an application, unenrolling the applicant.
 *
 * Fired by enrol_apply_plugin::cancel_enrolment() for each application it cancelled, after the
 * unenrolment. Its object, the durable record, survives the unenrolment on purpose; the user
 * enrolment id in other names a row that no longer exists.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class application_cancelled extends application_decided {
    /**
     * The event name.
     *
     * @return string Localised name.
     */
    public static function get_name() {
        return get_string('eventapplicationcancelled', 'enrol_apply');
    }

    /**
     * What happened, for the logs.
     *
     * @return string Description; not localised, as core's own descriptions are not.
     */
    public function get_description() {
        return "The user with id '{$this->userid}' cancelled the enrolment application of the user with id " .
            "'{$this->relateduserid}' to the enrolment method with id '{$this->other['enrolid']}'.";
    }
}
