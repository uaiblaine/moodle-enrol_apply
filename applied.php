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

/* The whole gate: without an application on this instance, the page would tell any logged-in
   user that the enrolment method exists on a course they may not be able to see. The row is
   read rather than counted because its status decides what the page says; see
   \enrol_apply\local\applicantstate. */
$ownrow = $DB->get_record(
    'user_enrolments',
    ['userid' => $USER->id, 'enrolid' => $instance->id],
    'id, status',
    IGNORE_MULTIPLE
);
if (!$ownrow) {
    throw new moodle_exception('invalidaccess', 'error');
}

/* The access fact is passed because the gate above asks only for a row: a fully enrolled
   participant who kept the link opens this page too, and must not be told their enrolment is
   inactive. */
$state = \enrol_apply\local\applicantstate::describe(
    $ownrow,
    is_enrolled($context, $USER, '', true)
);

$PAGE->set_course($course);
$PAGE->set_context($context->get_parent_context());
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/enrol/apply/applied.php', ['instance' => $instance->id]);
$PAGE->set_title($state['heading']);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('limitedwidth');

echo $OUTPUT->header();
echo $OUTPUT->heading($state['heading']);
echo $OUTPUT->notification($state['message'], $state['type'], false);

if (\enrol_apply\local\profilewriter::is_enabled($instance)) {
    // Writing is allowed: offer to save what was just typed, and write nothing until asked.
    $changes = \enrol_apply\local\offer::peek($instance->id);
    if ($changes) {
        echo $OUTPUT->render_from_template('enrol_apply/profile_offer', [
            'heading' => get_string('saveforfuture', 'enrol_apply'),
            'intro' => get_string('saveforfuture_desc', 'enrol_apply'),
            'fieldlabel' => get_string('requestedfields', 'enrol_apply'),
            'beforelabel' => get_string('profilenow', 'enrol_apply'),
            'afterlabel' => get_string('whatyouentered', 'enrol_apply'),
            'changes' => $changes,
            'formurl' => (new moodle_url('/enrol/apply/profile.php'))->out(false),
            'sesskey' => sesskey(),
            'instanceid' => $instance->id,
            'savelabel' => get_string('updateprofile', 'enrol_apply'),
        ]);
    }
} else {
    /* Writing is switched off, so the applicant is told what is missing and sent to their own
       profile page. /user/edit.php takes no field values (only id, course, returnto and
       cancelemailchange), so this list is the only thing telling them what to fill in. */
    $missing = \enrol_apply\local\completeness::missing($instance, $USER);
    if ($missing) {
        echo $PAGE->get_renderer('enrol_apply')->profile_missing($missing);
        echo $OUTPUT->single_button(
            new moodle_url('/user/edit.php', ['id' => $USER->id, 'returnto' => 'profile']),
            get_string('gotoprofile', 'enrol_apply'),
            'get'
        );
    }
}

/* Never the course page: an applicant without access would be bounced back to the enrolment
   page. See \enrol_apply\local\destination::home_page_url(). */
echo $OUTPUT->single_button(
    \enrol_apply\local\destination::home_page_url(),
    get_string('continue'),
    'get'
);
echo $OUTPUT->footer();
