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
 * This script authorises the first view. It does not authorise the ones that follow: sorting,
 * filtering and paging all go through core_table_get_dynamic_table_content, which never runs
 * this file. The gate that runs every time is course_applications::can_view().
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

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

/* Rows are scoped by the report's context, never by the id in the url: a report's parameters
   arrive from the client in the filterset, so they cannot decide which course is read (see
   course_applications::can_view()). The id still matters, because a course can carry several
   apply methods and get_action_icons() links each method to its own report.

   One report persistent per method, keyed by the enrol instance as itemid: the reader's filter
   choice is stored per report and user, and every request after this first render - sorting,
   paging, downloading - reads that store without naming a method. The itemid only selects which
   stored choice is loaded, never which rows may be read. See course_applications::for_method(). */
$report = course_applications::for_method($context, (int) $instance->id);

/* So the url's method is pre-applied as a filter value, merged into the reader's other stored
   filters, on every load of this url: the url names a method, so a reload restores it even after
   the reader cleared the filter. A forged value is not one of the filter's options and is
   ignored, which widens the report to the whole course the reader may already see; the base
   condition is the boundary. See course_applications::scope_to_method().

   The filter exists only when the course carries more than one apply method, so on a
   single-method course this does nothing and the report keeps the rows of deleted methods. */
$report->scope_to_method((int) $instance->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report:course_applications', 'enrol_apply'));
echo $report->output();
echo $OUTPUT->footer();
