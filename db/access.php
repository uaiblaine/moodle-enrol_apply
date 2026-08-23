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
     * This capability is also evaluated against the applicant's own user context, which
     * is what lets a mentor decide for the users assigned to them. A capability can only
     * declare one context level, so CONTEXT_COURSE is the one recorded here; core has the
     * same situation with moodle/grade:viewall, whose declaration in lib/db/access.php
     * carries the comment "and CONTEXT_USER".
     *
     * The consequence is limited to one screen: context_user::get_capabilities() lists
     * only capabilities declared at CONTEXT_USER (plus a hardcoded moodle/grade:viewall
     * that a plugin cannot extend), so this one does not appear when overriding
     * permissions on a user. Defining the mentor role still works, because
     * admin/roles/define.php runs in the system context, where every capability is
     * listed. See the README for the three steps. */
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
     * RISK_PERSONAL, and archetypes stated rather than inherited. The other five capabilities
     * in this file carry no riskbitmask, and the four that are not unenrolself - which defaults
     * to student - default to editingteacher. That is defensible for configuring a method or
     * deciding an application. It is not defensible here: the
     * report shows the frozen profile snapshot every applicant submitted, for every
     * application the course has ever had, including those already decided and those whose
     * enrolment is long gone. Inheriting the neighbours' default by omission would hand that
     * to every editing teacher on the site.
     *
     * A site that wants teachers to have it grants it to them deliberately. */
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
