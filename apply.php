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
 * The application form on a page of its own, for a browser with no JavaScript.
 *
 * The same form class the modal renders. Everything that decides whether this user may
 * apply lives in the form's own access check, which both transports call, so the two
 * routes cannot drift apart.
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

if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
    throw new moodle_exception('coursehidden');
}

/* Both ids ride in the url. They must NOT be passed as the form's $ajaxformdata argument:
   moodleform::_process_submission() treats a non-empty $ajaxformdata as the whole submission,
   so the _qf__ marker would never be seen and the form would silently never submit. */
$pageurl = new moodle_url('/enrol/apply/apply.php', [
    'instance' => $instance->id,
    'id' => $course->id,
]);
$PAGE->set_course($course);
$PAGE->set_context($context->get_parent_context());
$PAGE->set_pagelayout('incourse');
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('checkyourdetails', 'enrol_apply'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('limitedwidth');

$form = new \enrol_apply\form\application_form($pageurl, ['showbuttons' => true]);

/* The parent constructor runs this only for the AJAX transport, so the page transport calls
   it here. It covers the log-in-as session, guests, category visibility, allow_apply(), an
   application already submitted and the places cap. */
$form->check_access_for_dynamic_submission();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/enrol/index.php', ['id' => $course->id]));
} else if ($form->get_data()) {
    redirect($form->process_dynamic_submission());
}

$form->set_data_for_dynamic_submission();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('checkyourdetails', 'enrol_apply'));
$form->display();
echo $OUTPUT->footer();
