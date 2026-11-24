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
 * The plugin's web services: the course report read headlessly (stage R3), and the approval queue
 * read headlessly: one method's (stage 1 of the queue as a report), or with enrolid 0 the queue that
 * spans courses the reader is given (stage W1). No service decides an application: decisions stay
 * the form post to manage.php (CQ-D4).
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'enrol_apply_report_rows' => [
        'classname' => \enrol_apply\external\report_rows::class,
        'description' => 'A page of the course report of applications, with its columns, its filters and their counts.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'enrol/apply:viewreports',
    ],
    'enrol_apply_report_summary' => [
        'classname' => \enrol_apply\external\report_summary::class,
        'description' => 'The turnaround summary of the course report of applications.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'enrol/apply:viewreports',
    ],
    'enrol_apply_set_report_preference' => [
        'classname' => \enrol_apply\external\set_report_preference::class,
        'description' => 'Store the columns and widths the reader chose for the course report.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'enrol/apply:viewreports',
    ],
    'enrol_apply_queue_rows' => [
        'classname' => \enrol_apply\external\queue_rows::class,
        'description' => 'A page of an approval queue (one enrolment method\'s, or the one spanning courses), '
            . 'with its columns, its filters and their counts.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'enrol/apply:manageapplications, enrol/apply:viewapplications',
    ],
    'enrol_apply_queue_summary' => [
        'classname' => \enrol_apply\external\queue_summary::class,
        'description' => 'The figures above an approval queue (one enrolment method\'s, or the one spanning courses).',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'enrol/apply:manageapplications, enrol/apply:viewapplications',
    ],
    'enrol_apply_queue_methods' => [
        'classname' => \enrol_apply\external\queue_methods::class,
        'description' => 'The site-wide approval queue by enrolment method: what awaits, what is deferred, the oldest wait '
            . 'and the capacity of each method.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'enrol/apply:manageapplications, enrol/apply:viewapplications',
    ],
    'enrol_apply_set_queue_preference' => [
        'classname' => \enrol_apply\external\set_queue_preference::class,
        'description' => 'Store the columns and widths the reader chose for the approval queue.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'enrol/apply:manageapplications, enrol/apply:viewapplications',
    ],
];
