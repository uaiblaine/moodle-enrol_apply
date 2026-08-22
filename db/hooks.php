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
 * Hook callbacks of the enrolment upon approval plugin.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core_enrol\hook\before_user_enrolment_updated::class,
        'callback' => '\enrol_apply\hook_callbacks::before_user_enrolment_updated',
    ],
    [
        /* Pseudonymises the application trail while the course context still exists. It
           cannot be done from the course_deleted event: that fires after
           context_helper::delete_instance() has removed the context row, and every privacy
           provider query joins {context}, so the retained rows would become personal data
           that no subject access or erasure request can reach. */
        'hook' => \core_course\hook\before_course_deleted::class,
        'callback' => '\enrol_apply\hook_callbacks::before_course_deleted',
    ],
];
