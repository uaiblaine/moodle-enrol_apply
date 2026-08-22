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

/**
 * Event observers of the enrolment upon approval plugin.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        /* The safety net for a course deleted while this plugin is installed but disabled.
           enrol_course_delete() only calls delete_instance() for plugins in
           enrol_get_plugins(true), yet deletes the enrol and user_enrolments rows either
           way, so the plugin's two enrolment-keyed tables are orphaned with nobody
           consulted. Observers are registered whatever the plugin's state, which is what
           makes this reachable at all. The durable trail is handled by the
           before_course_deleted hook instead, because by the time this fires the course
           context is already gone. */
        'eventname' => '\core\event\course_deleted',
        'callback' => '\enrol_apply\observers::course_deleted',
    ],
];
