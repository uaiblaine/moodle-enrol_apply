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
 * Add or edit an enrolment upon approval instance in a course.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo.com (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     emeneo.com (http://emeneo.com/)
 * @author     Johannes Burk <johannes.burk@sudile.com>
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/enrol/apply/edit_form.php');

$courseid = required_param('courseid', PARAM_INT);
$instanceid = optional_param('id', 0, PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('enrol/apply:config', $context);

$PAGE->set_url('/enrol/apply/edit.php', ['courseid' => $course->id, 'id' => $instanceid]);
$PAGE->set_pagelayout('admin');

$return = new moodle_url('/enrol/instances.php', ['id' => $course->id]);
if (!enrol_is_enabled('apply')) {
    redirect($return);
}

$plugin = enrol_get_plugin('apply');

if ($instanceid) {
    $instance = $DB->get_record(
        'enrol',
        ['courseid' => $course->id, 'enrol' => 'apply', 'id' => $instanceid],
        '*',
        MUST_EXIST
    );
} else {
    require_capability('moodle/course:enrolconfig', $context);
    // No instance yet, a new one has to be added.
    navigation_node::override_active_url(new moodle_url('/enrol/instances.php', ['id' => $course->id]));
    $instance = (object) $plugin->get_instance_defaults();
    $instance->id = null;
    $instance->courseid = $course->id;
}

$mform = new enrol_apply_edit_form(null, [$instance, $plugin, $context]);

if ($mform->is_cancelled()) {
    redirect($return);
} else if ($data = $mform->get_data()) {
    /* Notification recipients are stored in customtext3 the same way
       admin_setting_users_with_capability::write_setting() stores them: the "everyone"
       marker wins over any explicit pick, and "nobody" is represented by an empty value. */
    $notify = $data->notify;
    if (in_array('$@ALL@$', $notify)) {
        $notify = ['$@ALL@$'];
    }
    $notify = array_diff($notify, ['$@NONE@$']);
    $data->customtext3 = implode(',', $notify);

    /* Collect the picked field set into its envelope. The pool is read again here rather
       than trusted from the submission: hideIf is a browser behaviour, and a "required" flag
       posted for a field that is not itself ticked, or a key that is not in the pool at all,
       must not survive. */
    $pool = \enrol_apply\local\fields::pool();
    $picked = [];
    $required = [];
    foreach ($pool as $key) {
        if (empty($data->{'field_' . $key})) {
            continue;
        }
        $picked[] = $key;
        if (!empty($data->{'fieldreq_' . $key})) {
            $required[] = $key;
        }
    }
    $data->customtext4 = \enrol_apply\local\fieldset::from_keys($picked, $required)->to_json();

    // The tri-state expiry select expands back into the two instance flags.
    $data->notifyall = ($data->expirynotify == 2) ? 1 : 0;
    $data->expirynotify = ($data->expirynotify > 0) ? 1 : 0;
    $data->expirythreshold = max((int) $data->expirythreshold, DAYSECS);

    if ($instance->id) {
        $plugin->update_instance($instance, $data);
        $instanceid = $instance->id;
    } else {
        $instanceid = $plugin->add_instance($course, (array) $data);
    }

    // Replace the configured group set wholesale; it is small and always fully submitted.
    $DB->delete_records('enrol_apply_groups', ['enrolid' => $instanceid]);
    foreach ((array) ($data->groupselect ?? []) as $groupid) {
        $DB->insert_record('enrol_apply_groups', (object) [
            'enrolid' => $instanceid,
            'groupid' => (int) $groupid,
        ]);
    }

    redirect($return);
}

$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(get_string('pluginname', 'enrol_apply'));

$renderer = $PAGE->get_renderer('enrol_apply');
$renderer->edit_page($mform);
