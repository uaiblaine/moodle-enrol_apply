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

/* The report's SCOPE is its context and never the id in the url, and that half is a security
   boundary rather than a preference: a report's parameters arrive as PARAM_RAW in the filterset
   and are json_decoded straight into it, so anything a client can set cannot be what decides
   which course's rows are read. The base condition stays `courseid = <context>->instanceid`.

   What that argument establishes is that the url's id is not a security boundary. It does NOT
   establish that the id is inert, and an earlier version of this comment drew that second
   conclusion and stated it as intended behaviour. It was not: `get_action_icons()` builds one
   icon PER INSTANCE and puts the instance id in the url, so two apply methods in one course
   produced byte-identical reports under two different urls. Measured on the development site:
   instance 195 held no applications at all and its report rendered instance 4's eight - an audit
   report of a method's applications, under that method's url, containing none of them. */
/* Keyed on the METHOD, through the persistent's itemid, and that is what makes the scoping below
   survive past the first render. set_filter_values() writes to reportbuilder_user_filter, which
   is keyed on (reportid, usercreated) and nothing else, and the report persistent is keyed on
   the source, the context and these three - so with one report per COURSE both methods shared a
   single stored scope. Every request after the initial page load reads that store and nothing
   else: sorting and paging go through core_table_get_dynamic_table_content, whose filterset
   carries the reportid and the report's own parameters and nothing that names a method, and the
   Download button posts the same id to /reportbuilder/download.php. Two tabs open on two methods
   therefore overwrote each other, and
   the second one's scope answered the first one's next click - reinstating the very defect this
   page exists to remove, for every request but the first.

   The itemid is NOT a scope and must never become one: it arrives from this url and can_view()
   explains at length why the query is scoped on the CONTEXT instead. What it selects here is
   which stored filter set is loaded, which changes what this reader last chose and never which
   rows may be read. A client swapping it gets another method's stored choice, inside the same
   course, which they are already entitled to see. */
$report = course_applications::for_method($context, (int) $instance->id);

/* So the url's method is pre-applied as a FILTER value, which is the one mechanism that narrows
   without touching the boundary above. **The safety is the base condition and nothing else**, and
   an earlier version of this comment reasoned it the wrong way round: it said a forged value
   "shows fewer rows ... the intersection of `courseid = X` and a foreign enrolid is empty", and
   that intersection is never computed. select::get_sql_filter() checks the submitted value
   against its own options list and returns ['', []] when it is not there, so a forged value
   produces NO filter and the report widens to the whole course - which is the view this reader
   already holds enrol/apply:viewreports for. Safe, but for the other reason.

   Clearing it in the report widens the view back to that same whole course, which is what this
   page showed before.

   Merged into whatever the reader already had rather than replacing it, because set_filter_values()
   overwrites the lot and their status or date filters are not this page's to discard. Core's own
   precedent for seeding a system report from a url parameter is admin/tasklogs.php, which
   replaces; here the merge is the difference between a scoped report and a reset one.

   Applied on every load of this url, deliberately and not as an oversight: the url NAMES a
   method, so that is what it means. A reader who clears the filter widens the report for as long
   as they are working in it - the filter form posts over a web service and never reloads this
   script - and a reload of a method's url puts that method back.

   Only where the filter exists at all: the report adds it only when the course carries more than
   one LIVE apply method, because a filter offering a single choice reads as a control that does
   not work. Not because it could never narrow - enrol_apply_submission rows outlive the instance
   they name, so a course that once had two methods holds rows whose enrolid names no live
   instance, and a one-option filter would exclude exactly those. They stay in the report, which
   is the course-wide view this page falls back to anyway. */
$report->scope_to_method((int) $instance->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report:course_applications', 'enrol_apply'));
echo $report->output();
echo $OUTPUT->footer();
