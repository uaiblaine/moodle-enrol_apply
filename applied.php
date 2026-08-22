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
 * Acknowledgement shown once an application has been submitted.
 *
 * Not a free page: it names an enrolment method and a course, so it is only rendered for a
 * user who really does have an application on that instance.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$instanceid = required_param('instance', PARAM_INT);

$instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'apply'], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $instance->courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login();

/* The whole gate. Without it this page tells any logged-in user that a given enrolment
   method exists on a course they may not be able to see at all. */
if (!$DB->record_exists('user_enrolments', ['userid' => $USER->id, 'enrolid' => $instance->id])) {
    throw new moodle_exception('invalidaccess', 'error');
}

$PAGE->set_course($course);
$PAGE->set_context($context->get_parent_context());
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/enrol/apply/applied.php', ['instance' => $instance->id]);
$PAGE->set_title(get_string('applicationsubmitted', 'enrol_apply'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('limitedwidth');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('applicationsubmitted', 'enrol_apply'));
echo $OUTPUT->notification(
    get_string('applicationsubmitted_body', 'enrol_apply'),
    \core\output\notification::NOTIFY_SUCCESS,
    false
);

/* Never /course/view.php: the applicant is suspended on this course, so that destination
   bounces them straight back here. get_home_page() is what core itself uses to decide where
   a user belongs when there is nowhere specific to send them. */
echo $OUTPUT->single_button(
    \enrol_apply\local\destination::home_page_url(),
    get_string('continue'),
    'get'
);
echo $OUTPUT->footer();
