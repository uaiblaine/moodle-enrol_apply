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

/* A scenario to start a new method from (\enrol_apply\local\preset): its values fill in the form
   and nothing else. The form reads it only for a new method and only as one of its keys, and carries
   it in a hidden element, so the post that saves reads it again from there. */
$preset = optional_param('preset', '', PARAM_ALPHA);

$mform = new enrol_apply_edit_form(null, [$instance, $plugin, $context, $preset]);

if ($mform->is_cancelled()) {
    redirect($return);
} else if ($data = $mform->get_data()) {
    // Notification recipients, stored in customtext3 by the one writer of that value.
    $data->customtext3 = \enrol_apply\local\notifyrecipients::from_form((array) ($data->notify ?? []));

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

    /* The Groups section's question onto the two stored settings (C-D6): the key mode lives on
       the instance, the choice among the options, written below once the instance exists. */
    $groupsetup = \enrol_apply\local\groupquestion::storage($data);
    $data->customchar1 = $groupsetup->customchar1;

    /* One transaction for the method, its options, its offer and its groups: a method saved
       without its options would read the defaults, "the group's deciders" among them, which is
       the unsafe one for a method recruiting deciders. */
    $transaction = $DB->start_delegated_transaction();
    if ($instance->id) {
        $plugin->update_instance($instance, $data);
        $instanceid = $instance->id;
    } else {
        $instanceid = $plugin->add_instance($course, (array) $data);
    }

    /* The per-method options and the offer, for a new method as for a saved one and before the
       redirect of "Save and edit", so every way out of the form saves them. */
    \enrol_apply\local\groupquestion::save((int) $instanceid, $data);

    // Replace the configured group set wholesale; it is small and always fully submitted.
    $DB->delete_records('enrol_apply_groups', ['enrolid' => $instanceid]);
    foreach ((array) ($data->groupselect ?? []) as $groupid) {
        $DB->insert_record('enrol_apply_groups', (object) [
            'enrolid' => $instanceid,
            'groupid' => (int) $groupid,
        ]);
    }
    $transaction->allow_commit();

    if (!empty($data->savefields)) {
        redirect(new moodle_url('/enrol/apply/fields.php', ['id' => $instanceid]));
    }
    redirect($return);
}

$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(get_string('pluginname', 'enrol_apply'));

$renderer = $PAGE->get_renderer('enrol_apply');
$renderer->edit_page($mform);
