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
 * Capabilities of the enrolment upon approval plugin.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo.com (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     emeneo.com (http://emeneo.com/)
 * @author     Johannes Burk <johannes.burk@sudile.com>
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    /* Add, edit or remove an apply enrol instance. */
    'enrol/apply:config' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    /* Decide on enrolment applications.
     * Granted at system level it covers every course, which is what the
     * Site administration -> Courses -> Manage enrolment applications page uses.
     *
     * It is also evaluated against the applicant's own user context, which lets a mentor
     * decide for the users assigned to them. A capability declares one context level, so
     * CONTEXT_COURSE is recorded here, as core does for moodle/grade:viewall.
     *
     * Consequence: context_user::get_capabilities() lists only CONTEXT_USER capabilities
     * (plus a hardcoded moodle/grade:viewall), so this one does not appear when overriding
     * permissions on a user. Defining the mentor role still works, because
     * admin/roles/define.php lists every capability. See the README for the setup. */
    'enrol/apply:manageapplications' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    /* Manage the enrolments of users. */
    'enrol/apply:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    /* Unenrol anybody from the course - watch out for data loss. */
    'enrol/apply:unenrol' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    /* Read the report of applications made to a course.
     *
     * RISK_PERSONAL and granted to managers only, unlike the capabilities above: the report
     * shows the profile snapshot every applicant submitted, for every application the course
     * has ever had, including decided ones and ones whose enrolment is gone. A site that
     * wants teachers to have it grants it to them deliberately. */
    'enrol/apply:viewreports' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    /* Voluntarily unenrol self from the course - watch out for data loss. */
    'enrol/apply:unenrolself' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'student' => CAP_ALLOW,
        ],
    ],
];
