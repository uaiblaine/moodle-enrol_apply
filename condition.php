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
 * Choose when one application question is shown, or remove its condition.
 *
 * Linked from the "Display conditions" section of fields.php. Saving goes through the moodleform,
 * whose submission carries the sesskey; removing is a POST carrying it too.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
// The form class is autoloaded, its parent moodleform is not.
require_once($CFG->libdir . '/formslib.php');

use enrol_apply\form\condition_form;
use enrol_apply\local\answers;
use enrol_apply\local\conditions;

$id = required_param('id', PARAM_INT);
$fieldid = required_param('fieldid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$instance = $DB->get_record('enrol', ['id' => $id, 'enrol' => 'apply'], '*', MUST_EXIST);
$course = get_course($instance->courseid);
$context = context_course::instance($course->id);

require_login($course);
require_capability('enrol/apply:config', $context);

$returnurl = new moodle_url('/enrol/apply/fields.php', ['id' => $instance->id]);

if ($action === 'remove') {
    // Scoped by the instance, so a row of another instance cannot be named here.
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest');
    }
    require_sesskey();
    conditions::delete((int) $instance->id, $fieldid);
    redirect($returnurl, get_string('conditionremoved', 'enrol_apply'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$fields = answers::fields($instance);
if (!isset($fields[$fieldid])) {
    throw new moodle_exception('conditionerror_field', 'enrol_apply', $returnurl);
}

$stored = conditions::stored((int) $instance->id)[$fieldid] ?? null;
// The first step's choice decides which second step is drawn; validation() checks it again.

$pageurl = new moodle_url('/enrol/apply/condition.php', ['id' => $instance->id, 'fieldid' => $fieldid]);
$form = new condition_form($pageurl, [
    'enrolid' => (int) $instance->id,
    'fieldid' => $fieldid,
    'fields' => $fields,
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    [$dependson, $operator, $positions] = $form->condition_from((array) $data);
    if ($dependson) {
        conditions::save((int) $instance->id, $fieldid, $dependson, $operator, $positions);
    } else {
        conditions::delete((int) $instance->id, $fieldid);
    }
    redirect($returnurl, get_string('conditionsaved', 'enrol_apply'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($stored && !$form->is_submitted()) {
    $defaults = ['dependsonfieldid' => $stored->dependsonfieldid];
    if ($stored->operator === conditions::OPERATOR_IN) {
        $defaults[condition_form::positions_name($stored->dependsonfieldid)] = array_fill_keys($stored->positions, 1);
    } else {
        $defaults[condition_form::operator_name($stored->dependsonfieldid)] = $stored->operator;
    }
    $form->set_data($defaults);
}

$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
navigation_node::override_active_url(new moodle_url('/enrol/instances.php', ['id' => $course->id]));

$title = get_string('conditionedit', 'enrol_apply');
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(enrol_get_plugin('apply')->get_instance_name($instance));
$PAGE->navbar->add(get_string('applicationfields', 'enrol_apply'), $returnurl);
$PAGE->navbar->add($title);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();
