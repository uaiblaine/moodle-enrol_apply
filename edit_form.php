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

        /* The window during which applications are accepted. It is separate from the
           enrolment period above: enrolperiod measures how long an approved enrolment
           lasts, these two decide when an application may be submitted at all. Both live
           on {enrol} already and are carried by core's own backup. */
        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_apply'), [
            'optional' => true,
        ]);
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_apply');

        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_apply'), [
            'optional' => true,
        ]);
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_apply');

        $mform->addElement('textarea', 'customtext1', get_string('editdescription', 'enrol_apply'));
        $mform->setType('customtext1', PARAM_RAW);

        $mform->addElement('select', 'customint7', get_string('opt_commentaryzone', 'enrol_apply'), $yesno);
        $mform->setDefault('customint7', 0);
        $mform->addHelpButton('customint7', 'opt_commentaryzone', 'enrol_apply');

        /* No setDefault. The one that stood here was dead - set_data() overrides it for any
           non-NULL scalar and get_instance_defaults() seeds '' - and reviving it would be worse
           than leaving it dead: the value it pre-filled was a get_string() call, so saving the
           form would freeze the CREATING teacher's own language into {enrol}.customtext2, after
           which the label would never follow the language pack again. Empty is the correct
           default, and both readers fall back to the shipped wording when it is empty.

           hideIf, because the element labels a field that only exists when the commentary zone
           is on, and a live editable text box for a control that is switched off is what made
           this setting read as a mystery. It costs nothing stored: hideIf sets the hidden
           attribute and display:none on the wrapper and does NOT disable the input
           (lib/form/form.js, the _updateDependentElement hide branch), so a label survives its
           zone being switched off and comes back with it. disabledIf would not - a disabled
           field posts nothing. */
        $mform->addElement('text', 'customtext2', get_string('custom_label', 'enrol_apply'));
        $mform->setType('customtext2', PARAM_TEXT);
        $mform->addHelpButton('customtext2', 'custom_label', 'enrol_apply');
        $mform->hideIf('customtext2', 'customint7', 'eq', 0);

        /* The two capacity numbers, and they answer different questions. customint3 is how
           many people may APPLY; customint4 is how many may be APPROVED at once. The gap
           between them is overbooking, which is the point in a plugin where approval is
           discretionary.

           They sit here, in the main section, and not where the first of them used to. The
           header opened for the profile fields below is never closed - renderHeader() closes a
           fieldset only when opening another, and the only other closer is the one
           add_action_buttons() emits - so everything after it rendered under a legend reading
           "Profile fields requested". Worse, it moved: with no offerable fields that header is
           not opened at all and the same element landed in the main section instead. Section
           membership was decided by configuration. */
        $mform->addElement('text', 'customint3', get_string('maxapplicants', 'enrol_apply'));
        $mform->setType('customint3', PARAM_INT);
        $mform->setDefault('customint3', $plugin->get_config('maxenrolled', 0));
        $mform->addHelpButton('customint3', 'maxapplicants', 'enrol_apply');

        $mform->addElement('text', 'customint4', get_string('places', 'enrol_apply'));
        $mform->setType('customint4', PARAM_INT);
        $mform->setDefault('customint4', $plugin->get_config('places', 0));
        $mform->addHelpButton('customint4', 'places', 'enrol_apply');

        /* What the two numbers currently hold, for the person setting them - who otherwise has
           the least context about what the instance is carrying. Only for an instance that
           exists: a new one has nothing to count, and counting would query for a guaranteed
           zero. */
        if (!empty($instance->id)) {
            $mform->addElement(
                'static',
                'currentoccupancy',
                get_string('places', 'enrol_apply'),
                get_string('placestaken', 'enrol_apply', (object) [
                    'taken' => \enrol_apply\local\capacity::places_taken($instance),
                    'limit' => \enrol_apply\local\capacity::places($instance),
                ])
            );
        }

        /* The profile fields this instance asks an applicant for, picked from the pool the
           administrator allows. Two checkboxes per field: collect it, and require it. The
           "required" box is hidden until the field itself is ticked, which is presentation
           only - edit.php recomputes the pair server side, because hideIf is a browser
           behaviour and decides nothing about what is submitted. */
        $pool = \enrol_apply\local\fields::pool();
        $offerable = array_intersect_key(\enrol_apply\local\fields::offerable(), array_flip($pool));

        if ($offerable) {
            $mform->addElement('header', 'requestedfieldsheader', get_string('requestedfields', 'enrol_apply'));
            $mform->addHelpButton('requestedfieldsheader', 'requestedfields', 'enrol_apply');
            $mform->setExpanded('requestedfieldsheader', true);

            foreach ($offerable as $key => $label) {
                /* The label is the escaped spelling: a moodleform element label renders
                   through a triple stash in element-template.mustache. */
                $escaped = \enrol_apply\local\fields::label($key, true);
                $group = [
                    $mform->createElement('advcheckbox', 'field_' . $key, '', $escaped),
                    $mform->createElement('advcheckbox', 'fieldreq_' . $key, '', get_string('fieldrequired', 'enrol_apply')),
                ];
                $mform->addGroup($group, 'fieldgroup_' . $key, $escaped, ' ', false);
                $mform->hideIf('fieldreq_' . $key, 'field_' . $key, 'notchecked');
            }
        } else {
            $mform->addElement(
                'static',
                'nofieldsoffered',
                get_string('requestedfields', 'enrol_apply'),
                get_string('nofieldsoffered', 'enrol_apply')
            );
        }

        $choices = [
            '$@NONE@$' => get_string('nobody'),
            '$@ALL@$' => get_string('everyonewhocan', 'admin', get_capability_string('enrol/apply:manageapplications')),
        ];
        foreach (get_enrolled_users($context, 'enrol/apply:manageapplications') as $userid => $user) {
            $choices[$userid] = fullname($user);
        }
        $notify = $mform->addElement('select', 'notify', get_string('notify_desc', 'enrol_apply'), $choices);
        $notify->setMultiple(true);

        /* Restrict applications to one cohort. The element is emitted even when there is
           nothing to choose from, as a hidden constant zero: enrol_plugin::update_instance()
           copies a property only when it is set on the submitted data, so omitting the
           element would make an existing restriction impossible to remove. */
        $cohorts = $this->get_cohort_options($instance, $context);
        if (count($cohorts) > 1) {
            $mform->addElement('select', 'customint5', get_string('cohortonly', 'enrol_apply'), $cohorts);
            $mform->addHelpButton('customint5', 'cohortonly', 'enrol_apply');
        } else {
            $mform->addElement('hidden', 'customint5');
            $mform->setType('customint5', PARAM_INT);
            $mform->setConstant('customint5', 0);
        }

        /* The per-instance half of the profile-write switch. Emitted either way, as a hidden
           constant zero when the site switch is off, for the same reason as the cohort
           element above: update_instance() copies a property only when it is set on the
           submitted data, so omitting it would make an existing opt-in impossible to clear. */
        if (get_config('enrol_apply', 'allowprofilewrite')) {
            $mform->addElement('advcheckbox', 'customint8', get_string('saveforfutureinstance', 'enrol_apply'));
            $mform->addHelpButton('customint8', 'saveforfutureinstance', 'enrol_apply');
            $mform->setDefault('customint8', 0);
        } else {
            $mform->addElement('hidden', 'customint8');
            $mform->setType('customint8', PARAM_INT);
            $mform->setConstant('customint8', 0);
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(true, $instance->id ? null : get_string('addinstance', 'enrol'));

        $this->set_data($this->prepare_instance_data($instance, $DB));
    }

    /**
     * Reject a submission whose dates or cohort do not hold up server side.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        [$instance, , $context] = $this->_customdata;

        /* Every read below defaults rather than indexing: a select whose submitted value is
           not one of its options exports as null, so a key present on screen can be missing
           here, and reaching for it directly would raise a warning that fails the build. */
        if (($data['status'] ?? ENROL_INSTANCE_ENABLED) == ENROL_INSTANCE_ENABLED) {
            $opens = (int) ($data['enrolstartdate'] ?? 0);
            $closes = (int) ($data['enrolenddate'] ?? 0);
            if ($closes > 0 && $closes < $opens) {
                $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_apply');
            }
        }

        /* The second barrier, not the first. HTML_QuickForm_select::exportValue() already
           intersects the submitted value with the options registered on the element, so a
           forged cohort id reaches this method as null and never becomes a restriction. The
           check below is what keeps that true if the element is ever changed to the ajax
           `cohort` autocomplete, whose exportValue() short-circuits and filters nothing —
           the reason enrol_cohort carries the same array_diff. The offered list is rebuilt
           here rather than read from a property stashed at definition() time, because a
           stashed list is whatever the last render happened to hold. */
        $submitted = (int) ($data['customint5'] ?? 0);
        if ($submitted !== 0) {
            $offered = array_map('intval', array_keys($this->get_cohort_options($instance, $context)));
            if (array_diff([$submitted], $offered)) {
                $errors['customint5'] = get_string('invaliddata', 'error');
            }
        }

        return $errors;
    }

    /**
     * The cohorts this user may restrict the instance to, keyed by cohort id.
     *
     * The list always carries a leading "no restriction" entry. A stored restriction the
     * current user cannot see — a hidden cohort, a deleted one, or the -1 sentinel a
     * cross-site restore writes — stays selectable so that editing an unrelated setting
     * does not silently lift it, but it is labelled by id rather than by name: resolving
     * the name through a plain get_record() here would turn the picker into a hidden-cohort
     * name oracle for anybody holding enrol/apply:config.
     *
     * @param stdClass $instance Instance record, or the defaults for a new instance.
     * @param context $context Course context the instance belongs to.
     * @return array Cohort names keyed by cohort id.
     */
    protected function get_cohort_options($instance, $context) {
        global $CFG;

        require_once($CFG->dirroot . '/cohort/lib.php');

        $options = [0 => get_string('no')];

        // The fourth argument is the limit, and it defaults to 25 — without it the picker truncates.
        foreach (cohort_get_available_cohorts($context, 0, 0, 0) as $cohort) {
            $name = format_string($cohort->name, true, ['context' => context::instance_by_id($cohort->contextid)]);
            if ($cohort->idnumber !== '' && $cohort->idnumber !== null) {
                $name .= ' [' . s($cohort->idnumber) . ']';
            }
            $options[$cohort->id] = $name;
        }

        $stored = (int) ($instance->customint5 ?? 0);
        if ($stored !== 0 && !isset($options[$stored])) {
            $options[$stored] = get_string('unknowncohort', 'cohort', $stored);
        }

        return $options;
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

        /* Unpack the stored envelope onto the picker's checkbox pairs. resolve() rather than
           the raw envelope, so a key the site no longer allows shows as unticked instead of
           as a ticked box the applicant would never be asked. */
        $resolved = \enrol_apply\local\fields::resolve($data);
        foreach ($resolved->keys() as $key) {
            $data->{'field_' . $key} = 1;
            $data->{'fieldreq_' . $key} = $resolved->is_required($key) ? 1 : 0;
        }

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
