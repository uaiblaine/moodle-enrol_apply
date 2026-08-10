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
 * Read-only listing of the comments submitted with enrolment applications.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     emeneo (http://emeneo.com/)
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/apply/info_table.php');
require_once($CFG->dirroot . '/enrol/apply/renderer.php');

$id = optional_param('id', 0, PARAM_INT);

require_login();

$manageurlparams = [];
$instance = null;
$commentlabel = get_string('applycomment', 'enrol_apply');

if ($id) {
    $instance = $DB->get_record('enrol', ['id' => $id, 'enrol' => 'apply'], '*', MUST_EXIST);
    $course = get_course($instance->courseid);
    require_login($course);
    $context = context_course::instance($course->id, MUST_EXIST);
    require_capability('enrol/apply:manageapplications', $context);
    $manageurlparams['id'] = $instance->id;
    $pageheading = format_string($course->fullname);
    if (!empty($instance->customtext2)) {
        $commentlabel = format_string($instance->customtext2);
    }
} else {
    $context = context_system::instance();
    require_capability('enrol/apply:manageapplications', $context);
    $pageheading = get_string('submitted_info', 'enrol_apply');
}

$manageurl = new moodle_url('/enrol/apply/info.php', $manageurlparams);

$PAGE->set_context($context);
$PAGE->set_url($manageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pageheading);
$PAGE->navbar->add(get_string('submitted_info', 'enrol_apply'));
$PAGE->set_title(get_string('submitted_info', 'enrol_apply'));

$table = new enrol_apply_info_table($id, $commentlabel);
$table->define_baseurl($manageurl);

$renderer = $PAGE->get_renderer('enrol_apply');
$renderer->info_page($table, $manageurl, $instance);
