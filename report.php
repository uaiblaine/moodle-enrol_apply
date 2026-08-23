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
 * The report of applications made to one course.
 *
 * This script authorises the first view. It does NOT authorise the ones that follow: sorting,
 * filtering and paging all go through core_table_get_dynamic_table_content, which never runs
 * this file. The gate that runs every time is course_applications::can_view().
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use core_reportbuilder\system_report_factory;
use enrol_apply\reportbuilder\local\systemreports\course_applications;

$id = required_param('id', PARAM_INT);

$instance = $DB->get_record('enrol', ['id' => $id, 'enrol' => 'apply'], '*', MUST_EXIST);
$course = get_course($instance->courseid);

require_login($course);
$context = context_course::instance($course->id, MUST_EXIST);
require_capability('enrol/apply:viewreports', $context);

$url = new moodle_url('/enrol/apply/report.php', ['id' => $instance->id]);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('report:course_applications', 'enrol_apply'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('report:course_applications', 'enrol_apply'));

/* The report is scoped by its context, not by the instance id in the URL. The id above chooses
   the course and authorises this request; it deliberately does not narrow the report to one
   enrolment method, because a course's applications are a course-level question and a reader
   arriving from either method's icon should see the same thing. Where a course has more than
   one apply method, the report offers a filter to narrow by it. */
$report = system_report_factory::create(course_applications::class, $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report:course_applications', 'enrol_apply'));
echo $report->output();
echo $OUTPUT->footer();
