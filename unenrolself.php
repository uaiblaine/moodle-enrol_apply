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
 * Self unenrolment for the enrolment upon approval plugin.
 *
 * The presence of this file plus the 'enrol/apply:unenrolself' capability is what puts
 * the "Unenrol me from ..." link into the course administration navigation.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$enrolid = required_param('enrolid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'apply'], '*', MUST_EXIST);
$course = get_course($instance->courseid);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);

$plugin = enrol_get_plugin('apply');

// The capability and enrolment checks for this action live in get_unenrolself_link().
if (!$plugin->get_unenrolself_link($instance)) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

$PAGE->set_context($context);
$PAGE->set_url('/enrol/apply/unenrolself.php', ['enrolid' => $instance->id]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title($plugin->get_instance_name($instance));

if ($confirm && confirm_sesskey()) {
    $plugin->unenrol_user($instance, $USER->id);
    redirect(new moodle_url('/index.php'));
}

echo $OUTPUT->header();
$yesurl = new moodle_url($PAGE->url, ['confirm' => 1, 'sesskey' => sesskey()]);
$nourl = new moodle_url('/course/view.php', ['id' => $course->id]);
// The core self enrolment wording fits this plugin exactly.
$message = get_string('unenrolselfconfirm', 'enrol_self', format_string($course->fullname));
echo $OUTPUT->confirm($message, $yesurl, $nourl);
echo $OUTPUT->footer();
