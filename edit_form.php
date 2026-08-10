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
 * Form used to add or edit an enrolment upon approval instance.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo.com (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     emeneo.com (http://emeneo.com/)
 * @author     Johannes Burk <johannes.burk@sudile.com>
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form used to add or edit an enrolment upon approval instance.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo.com (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_apply_edit_form extends moodleform {
    /**
     * Build the instance edit form.
     *
     * @return void
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;

        [$instance, $plugin, $context] = $this->_customdata;

        $mform->addElement('header', 'header', get_string('pluginname', 'enrol_apply'));

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $yesno = [1 => get_string('yes'), 0 => get_string('no')];

        $mform->addElement('select', 'status', get_string('status', 'enrol_apply'), [
            ENROL_INSTANCE_ENABLED => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
        ]);
        $mform->setDefault('status', $plugin->get_config('status'));

        $mform->addElement('select', 'customint6', get_string('newenrols', 'enrol_apply'), $yesno);
        $mform->setDefault('customint6', $plugin->get_config('newenrols'));

        $roleid = $instance->id ? $instance->roleid : $plugin->get_config('roleid');
        $mform->addElement('select', 'roleid', get_string('defaultrole', 'role'), get_default_enrol_roles($context, $roleid));
        $mform->setDefault('roleid', $plugin->get_config('roleid'));

        // Groups the applicant joins once their application is approved.
        $groups = [];
        foreach (groups_get_all_groups($instance->courseid) as $group) {
            $groups[$group->id] = format_string($group->name, true, ['context' => $context]);
        }
        $groupselect = $mform->addElement('autocomplete', 'groupselect', get_string('group', 'enrol_apply'), $groups);
        $groupselect->setMultiple(true);
        $mform->addHelpButton('groupselect', 'group', 'enrol_apply');

        // Enrolment duration.
        $mform->addElement('duration', 'enrolperiod', get_string('defaultperiod', 'enrol_apply'), [
            'optional' => true,
            'defaultunit' => DAYSECS,
        ]);
        $mform->setDefault('enrolperiod', $plugin->get_config('enrolperiod'));
        $mform->addHelpButton('enrolperiod', 'defaultperiod', 'enrol_apply');

        // Expiry notification, stored across the expirynotify and notifyall instance fields.
        $mform->addElement('select', 'expirynotify', get_string('expirynotify', 'core_enrol'), [
            0 => get_string('no'),
            1 => get_string('expirynotifyenroller', 'enrol_apply'),
            2 => get_string('expirynotifyall', 'enrol_apply'),
        ]);
        $mform->addHelpButton('expirynotify', 'expirynotify', 'core_enrol');

        $mform->addElement('duration', 'expirythreshold', get_string('expirythreshold', 'core_enrol'), [
            'optional' => false,
            'defaultunit' => DAYSECS,
        ]);
        $mform->addHelpButton('expirythreshold', 'expirythreshold', 'core_enrol');
        $mform->disabledIf('expirythreshold', 'expirynotify', 'eq', 0);
        $mform->setDefault('expirythreshold', DAYSECS);

        $mform->addElement('textarea', 'customtext1', get_string('editdescription', 'enrol_apply'));
        $mform->setType('customtext1', PARAM_RAW);

        $mform->addElement('select', 'customint7', get_string('opt_commentaryzone', 'enrol_apply'), $yesno);
        $mform->setDefault('customint7', 0);
        $mform->addHelpButton('customint7', 'opt_commentaryzone', 'enrol_apply');

        $mform->addElement('text', 'customtext2', get_string('custom_label', 'enrol_apply'));
        $mform->setType('customtext2', PARAM_TEXT);
        $mform->setDefault('customtext2', get_string('comment', 'enrol_apply'));

        $mform->addElement('select', 'customint1', get_string('show_standard_user_profile', 'enrol_apply'), $yesno);
        $mform->setDefault('customint1', $plugin->get_config('show_standard_user_profile'));

        $mform->addElement('select', 'customint2', get_string('show_extra_user_profile', 'enrol_apply'), $yesno);
        $mform->setDefault('customint2', $plugin->get_config('show_extra_user_profile'));

        $choices = [
            '$@NONE@$' => get_string('nobody'),
            '$@ALL@$' => get_string('everyonewhocan', 'admin', get_capability_string('enrol/apply:manageapplications')),
        ];
        foreach (get_enrolled_users($context, 'enrol/apply:manageapplications') as $userid => $user) {
            $choices[$userid] = fullname($user);
        }
        $notify = $mform->addElement('select', 'notify', get_string('notify_desc', 'enrol_apply'), $choices);
        $notify->setMultiple(true);

        $mform->addElement('text', 'customint3', get_string('maxenrolled', 'enrol_apply'));
        $mform->setType('customint3', PARAM_INT);
        $mform->setDefault('customint3', $plugin->get_config('maxenrolled', 0));
        $mform->addHelpButton('customint3', 'maxenrolled', 'enrol_apply');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(true, $instance->id ? null : get_string('addinstance', 'enrol'));

        $this->set_data($this->prepare_instance_data($instance, $DB));
    }

    /**
     * Map the stored instance fields onto the form element names.
     *
     * The notification recipients live in customtext3 as a comma separated list, and the
     * configured groups live in their own table, so both have to be unpacked before
     * set_data() can populate the multi-select elements.
     *
     * @param stdClass $instance Instance record, or the defaults for a new instance.
     * @param moodle_database $db Database connection.
     * @return stdClass Instance data ready for set_data().
     */
    protected function prepare_instance_data($instance, $db) {
        $data = clone $instance;

        $stored = isset($data->customtext3) ? (string) $data->customtext3 : '';
        $data->notify = $stored === '' ? ['$@NONE@$'] : explode(',', $stored);

        $data->groupselect = [];
        if (!empty($instance->id)) {
            $data->groupselect = array_map(
                'intval',
                $db->get_fieldset_select('enrol_apply_groups', 'groupid', 'enrolid = :enrolid', [
                    'enrolid' => $instance->id,
                ])
            );
        }

        // The stored pair of flags collapses into the tri-state expirynotify select.
        if (!empty($data->notifyall) && !empty($data->expirynotify)) {
            $data->expirynotify = 2;
        }
        unset($data->notifyall);

        return $data;
    }
}
