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
     * Seven sections, each opened by a header of its own, so every element lands in the section
     * declared just above it: a header stays open until the next one, and closeHeaderBefore()
     * ends the last before the buttons. An element added between two sections would join the
     * one above it, so a new element goes inside its section's block.
     *
     * @return void
     */
    protected function definition() {
        global $DB, $OUTPUT;

        $mform = $this->_form;

        [$instance, $plugin, $context] = $this->_customdata;

        // The summary strip reads the saved instance, so a new one has none.
        if (!empty($instance->id)) {
            $mform->addElement('html', $this->summary_strip($instance));
        }

        /* Which collapsed sections open on load. General, When and how many, Application form and
           Groups are always open; the other three open only when they hold a value other than the
           default, so nothing configured hides behind a closed section. */
        $opened = $this->opened_sections($instance, $plugin);

        $yesno = [1 => get_string('yes'), 0 => get_string('no')];

        // Section 1: General.
        $mform->addElement('header', 'sectiongeneral', get_string('sectiongeneral', 'enrol_apply'));
        $mform->addHelpButton('sectiongeneral', 'sectiongeneral', 'enrol_apply');
        $mform->setExpanded('sectiongeneral', true);

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('select', 'status', get_string('status', 'enrol_apply'), [
            ENROL_INSTANCE_ENABLED => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
        ]);
        $mform->setDefault('status', $plugin->get_config('status'));
        $mform->addHelpButton('status', 'status', 'enrol_apply');

        $mform->addElement('select', 'customint6', get_string('newenrols', 'enrol_apply'), $yesno);
        $mform->setDefault('customint6', $plugin->get_config('newenrols'));
        $mform->addHelpButton('customint6', 'newenrols', 'enrol_apply');

        $roleid = $instance->id ? $instance->roleid : $plugin->get_config('roleid');
        $mform->addElement(
            'select',
            'roleid',
            get_string('roleonapproval', 'enrol_apply'),
            get_default_enrol_roles($context, $roleid)
        );
        $mform->setDefault('roleid', $plugin->get_config('roleid'));
        $mform->addHelpButton('roleid', 'roleonapproval', 'enrol_apply');

        // Section 2: When and how many.
        $mform->addElement('header', 'sectionwhen', get_string('sectionwhen', 'enrol_apply'));
        $mform->addHelpButton('sectionwhen', 'sectionwhen', 'enrol_apply');
        $mform->setExpanded('sectionwhen', true);

        /* The window during which applications are accepted. It is separate from the
           enrolment period under "After approval": enrolperiod measures how long an approved
           enrolment lasts, these two decide when an application may be submitted at all. Both
           live on {enrol} already and are carried by core's own backup. */
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

        /* The two capacity numbers: customint3 is how many people may apply, customint4 how
           many may be approved at once. The gap between them is overbooking, which is
           legitimate where approval is discretionary. 0 means no limit, and validation()
           refuses a negative. */
        $mform->addElement('text', 'customint3', get_string('maxapplicants', 'enrol_apply'));
        $mform->setType('customint3', PARAM_INT);
        $mform->setDefault('customint3', $plugin->get_config('maxenrolled', 0));
        $mform->addHelpButton('customint3', 'maxapplicants', 'enrol_apply');

        $mform->addElement('text', 'customint4', get_string('places', 'enrol_apply'));
        $mform->setType('customint4', PARAM_INT);
        $mform->setDefault('customint4', $plugin->get_config('places', 0));
        $mform->addHelpButton('customint4', 'places', 'enrol_apply');

        /* How many places are currently taken, for the person setting the limits. Only for an
           existing instance: a new one has nothing to count. */
        if (!empty($instance->id)) {
            $mform->addElement(
                'static',
                'currentoccupancy',
                get_string('placestakenlabel', 'enrol_apply'),
                $this->places_taken_text($instance)
            );
        }

        // Section 3: Who may apply.
        $mform->addElement('header', 'sectionwho', get_string('sectionwho', 'enrol_apply'));
        $mform->addHelpButton('sectionwho', 'sectionwho', 'enrol_apply');
        $mform->setExpanded('sectionwho', $opened['sectionwho']);

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
            // The section would otherwise be an empty fieldset.
            $mform->addElement(
                'static',
                'nocohorts',
                get_string('cohortonly', 'enrol_apply'),
                get_string('nocohortsavailable', 'enrol_apply')
            );
        }

        // Section 4: Application form.
        $mform->addElement('header', 'sectionform', get_string('sectionform', 'enrol_apply'));
        $mform->addHelpButton('sectionform', 'sectionform', 'enrol_apply');
        $mform->setExpanded('sectionform', true);

        $mform->addElement('textarea', 'customtext1', get_string('editdescription', 'enrol_apply'));
        $mform->setType('customtext1', PARAM_RAW);
        $mform->addHelpButton('customtext1', 'editdescription', 'enrol_apply');

        $mform->addElement('select', 'customint7', get_string('opt_commentaryzone', 'enrol_apply'), $yesno);
        $mform->setDefault('customint7', 0);
        $mform->addHelpButton('customint7', 'opt_commentaryzone', 'enrol_apply');

        /* No setDefault: empty is the correct default, and the readers fall back to the shipped
           wording. Pre-filling a get_string() here would freeze the creating teacher's language
           into customtext2, so the label would stop following the language pack.

           hideIf rather than disabledIf, because the label only matters while the commentary
           zone is on. hideIf hides the wrapper without disabling the input (_hideElement() in
           lib/form/form.js), so a label survives the zone being switched off; a disabled field
           would post nothing. */
        $mform->addElement('text', 'customtext2', get_string('custom_label', 'enrol_apply'));
        $mform->setType('customtext2', PARAM_TEXT);
        $mform->addHelpButton('customtext2', 'custom_label', 'enrol_apply');
        $mform->hideIf('customtext2', 'customint7', 'eq', 0);

        /* The profile fields this instance asks an applicant for, picked from the pool the
           administrator allows. Two checkboxes per field: collect it, and require it. The
           "required" box is hidden until the field itself is ticked, which is presentation
           only - edit.php recomputes the pair server side, because hideIf is a browser
           behaviour and decides nothing about what is submitted. */
        $pool = \enrol_apply\local\fields::pool();
        $offerable = array_intersect_key(\enrol_apply\local\fields::offerable(), array_flip($pool));

        if ($offerable) {
            $mform->addElement(
                'static',
                'requestedfields',
                get_string('requestedfields', 'enrol_apply'),
                get_string('requestedfieldsintro', 'enrol_apply')
            );
            $mform->addHelpButton('requestedfields', 'requestedfields', 'enrol_apply');

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

        /* The method's own questions hang off the instance id, so they are edited on their own
           page once the instance exists, reached by the "Save and edit" button at the foot of
           the form. */
        $mform->addElement('static', 'questions', get_string('questions', 'enrol_apply'), $this->questions_text($instance));

        // Section 5: Groups.
        $mform->addElement('header', 'sectiongroups', get_string('sectiongroups', 'enrol_apply'));
        $mform->addHelpButton('sectiongroups', 'sectiongroups', 'enrol_apply');
        $mform->setExpanded('sectiongroups', true);

        // Groups the applicant joins once their application is approved.
        $groups = [];
        foreach (groups_get_all_groups($instance->courseid) as $group) {
            $groups[$group->id] = format_string($group->name, true, ['context' => $context]);
        }
        $groupselect = $mform->addElement('autocomplete', 'groupselect', get_string('group', 'enrol_apply'), $groups);
        $groupselect->setMultiple(true);
        $mform->addHelpButton('groupselect', 'group', 'enrol_apply');

        /* What a group's enrolment key does: nothing, admit, or send for review. The groups that
           carry a key are listed beneath it, the keys themselves never; hideIf because the list
           only matters while the option is on. */
        $mform->addElement('select', 'customchar1', get_string('groupkeys', 'enrol_apply'), [
            \enrol_apply\local\groupkey::MODE_OFF => get_string('groupkeys_off', 'enrol_apply'),
            \enrol_apply\local\groupkey::MODE_ANY => get_string('groupkeys_any', 'enrol_apply'),
            \enrol_apply\local\groupkey::MODE_REQUIRED => get_string('groupkeys_required', 'enrol_apply'),
            \enrol_apply\local\groupkey::MODE_REVIEW => get_string('groupkeys_review', 'enrol_apply'),
        ]);
        $mform->setType('customchar1', PARAM_ALPHA);
        $mform->addHelpButton('customchar1', 'groupkeys', 'enrol_apply');

        /* Read only in key-only mode (groupkey::announces_admissions()), so shown only there. A
           hidden element is not posted on 5.3, and update_instance() writes only what it is
           given, so switching modes keeps the stored answer. */
        $mform->addElement('select', 'customchar2', get_string('admissionnotice', 'enrol_apply'), [
            '' => get_string('no'),
            \enrol_apply\local\groupkey::NOTICE_ON => get_string('yes'),
        ]);
        $mform->setType('customchar2', PARAM_ALPHANUM);
        $mform->addHelpButton('customchar2', 'admissionnotice', 'enrol_apply');
        $mform->hideIf('customchar2', 'customchar1', 'neq', \enrol_apply\local\groupkey::MODE_REQUIRED);
        $mform->addElement(
            'static',
            'groupkeysummary',
            get_string('groupkeysingroups', 'enrol_apply'),
            $this->group_key_summary((int) $instance->courseid, $context)
        );
        $mform->hideIf('groupkeysummary', 'customchar1', 'eq', \enrol_apply\local\groupkey::MODE_OFF);

        /* What key-only mode would do with this course as it stands: enrol nobody, or leave the
           applications made earlier waiting. Shown only while that mode is selected, because only
           it is affected; the facts are read whatever mode is saved, so a teacher switching to it
           is warned before saving. */
        $warnings = $this->key_only_warnings($instance);
        if ($warnings !== '') {
            $mform->addElement('static', 'keyonlywarnings', '', $warnings);
            $mform->hideIf('keyonlywarnings', 'customchar1', 'neq', \enrol_apply\local\groupkey::MODE_REQUIRED);
        }
        // The same facts for the mode that sends a keyed application for review, worded for applying.
        $reviewwarnings = $this->key_only_warnings($instance, true);
        if ($reviewwarnings !== '') {
            $mform->addElement('static', 'keyreviewwarnings', '', $reviewwarnings);
            $mform->hideIf('keyreviewwarnings', 'customchar1', 'neq', \enrol_apply\local\groupkey::MODE_REVIEW);
        }
        /* In a separate-groups course an application without a key belongs to no group, so it
           reaches only those who can access all groups (S3). Shown while the selected mode lets
           such an application in; the two key-only modes never make one. */
        if ($this->separate_groups((int) $instance->courseid)) {
            $mform->addElement('static', 'groupscopekeyless', '', $OUTPUT->render_from_template('enrol_apply/groupkey_warnings', [
                'warnings' => [['danger' => false, 'message' => get_string('groupscopekeyless', 'enrol_apply')]],
            ]));
            $mform->hideIf(
                'groupscopekeyless',
                'customchar1',
                'in',
                \enrol_apply\local\groupkey::MODE_REQUIRED . '|' . \enrol_apply\local\groupkey::MODE_REVIEW
            );
        }

        // Self enrolment reads the same keys (G1), so they would enrol there without the questions.
        if ($this->self_enrolment_takes_group_keys((int) $instance->courseid)) {
            $mform->addElement('static', 'groupkeyselfkeys', '', $OUTPUT->render_from_template('enrol_apply/groupkey_warnings', [
                'warnings' => [['danger' => false, 'message' => get_string('groupkeyselfkeys', 'enrol_apply')]],
            ]));
            $mform->hideIf('groupkeyselfkeys', 'customchar1', 'eq', \enrol_apply\local\groupkey::MODE_OFF);
        }

        // Section 6: After approval.
        $mform->addElement('header', 'sectionafter', get_string('sectionafter', 'enrol_apply'));
        $mform->addHelpButton('sectionafter', 'sectionafter', 'enrol_apply');
        $mform->setExpanded('sectionafter', $opened['sectionafter']);

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

        /* The per-instance half of the profile-write switch. Emitted either way, as a hidden
           constant zero when the site switch is off, for the same reason as the cohort
           element: update_instance() copies a property only when it is set on the submitted
           data, so omitting it would make an existing opt-in impossible to clear. */
        if (get_config('enrol_apply', 'allowprofilewrite')) {
            $mform->addElement('advcheckbox', 'customint8', get_string('saveforfutureinstance', 'enrol_apply'));
            $mform->addHelpButton('customint8', 'saveforfutureinstance', 'enrol_apply');
            $mform->setDefault('customint8', 0);
        } else {
            $mform->addElement('hidden', 'customint8');
            $mform->setType('customint8', PARAM_INT);
            $mform->setConstant('customint8', 0);
        }

        // Section 7: Notifications.
        $mform->addElement('header', 'sectionnotify', get_string('sectionnotify', 'enrol_apply'));
        $mform->addHelpButton('sectionnotify', 'sectionnotify', 'enrol_apply');
        $mform->setExpanded('sectionnotify', $opened['sectionnotify']);

        $choices = [
            '$@NONE@$' => get_string('nobody'),
            '$@ALL@$' => get_string('everyonewhocan', 'admin', get_capability_string('enrol/apply:manageapplications')),
        ];
        foreach (get_enrolled_users($context, 'enrol/apply:manageapplications') as $userid => $user) {
            $choices[$userid] = fullname($user);
        }
        $notify = $mform->addElement('select', 'notify', get_string('notify', 'enrol_apply'), $choices);
        $notify->setMultiple(true);
        $mform->addHelpButton('notify', 'notify', 'enrol_apply');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        /* A second save button that lands on the instance's own questions: they hang off the
           instance id, so they can only be edited once the instance exists. */
        $savelabel = $instance->id ? get_string('savechanges') : get_string('addinstance', 'enrol');
        $buttons = [
            $mform->createElement('submit', 'submitbutton', $savelabel),
            $mform->createElement('submit', 'savefields', get_string('saveandeditfields', 'enrol_apply')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');

        $this->set_data($this->prepare_instance_data($instance, $DB));
    }

    /**
     * Which of the sections closed by default open on load.
     *
     * Who may apply, After approval and Notifications are closed by default; each opens when the
     * instance holds a value in it other than the default, so a configured value is never hidden
     * behind a closed section. For a new instance the "instance" is the defaults, so a site
     * default such as an enrolment duration opens its section too.
     *
     * @param stdClass $instance Instance record, or the defaults for a new instance.
     * @param enrol_apply_plugin $plugin The plugin, for the notification default.
     * @return array Whether to open each section, keyed by header name: sectionwho, sectionafter
     *         and sectionnotify.
     */
    protected function opened_sections($instance, $plugin): array {
        $defaults = $plugin->get_instance_defaults();

        return [
            'sectionwho' => (int) ($instance->customint5 ?? 0) !== 0,
            'sectionafter' => !empty($instance->enrolperiod)
                || !empty($instance->expirynotify)
                || !empty($instance->customint8),
            'sectionnotify' => (string) ($instance->customtext3 ?? '') !== (string) $defaults['customtext3'],
        ];
    }

    /**
     * The facts the summary strip states about a saved instance, in reading order.
     *
     * Whether it accepts applications (in allow_apply()'s order: disabled, not accepting new
     * ones, not open yet, closed, the applicant limit reached, then open, until the closing date
     * when there is one), how many places are taken of the limit, and what a group enrolment key
     * does. Each fact comes from the instance row or an existing reader; the cohort restriction
     * is left out, because whether it lets somebody in depends on who asks.
     *
     * @param stdClass $instance The saved {enrol} record.
     * @return string[] The facts, plain text.
     */
    protected function summary_facts(stdClass $instance): array {
        $facts = [];

        $now = time();
        $opens = (int) ($instance->enrolstartdate ?? 0);
        $closes = (int) ($instance->enrolenddate ?? 0);
        if ($instance->status != ENROL_INSTANCE_ENABLED) {
            $facts[] = get_string('summarydisabled', 'enrol_apply');
        } else if (empty($instance->customint6)) {
            $facts[] = get_string('summarynotaccepting', 'enrol_apply');
        } else if ($opens > 0 && $opens > $now) {
            $facts[] = get_string('summarynotopen', 'enrol_apply', userdate($opens));
        } else if ($closes > 0 && $closes < $now) {
            $facts[] = get_string('summaryclosed', 'enrol_apply', userdate($closes));
        } else if (\enrol_apply\local\capacity::applications_closed($instance)) {
            $facts[] = get_string('summaryfull', 'enrol_apply');
        } else if ($closes > 0) {
            $facts[] = get_string('summaryacceptinguntil', 'enrol_apply', userdate($closes));
        } else {
            $facts[] = get_string('summaryaccepting', 'enrol_apply');
        }

        $taken = \enrol_apply\local\capacity::places_taken($instance);
        $limit = \enrol_apply\local\capacity::places($instance);
        $facts[] = $limit > 0
            ? get_string('summaryplaces', 'enrol_apply', (object) ['taken' => $taken, 'limit' => $limit])
            : get_string('summaryplacesnolimit', 'enrol_apply', $taken);

        // The strict readers, so a mode a restore carried in verbatim reads as off, as everywhere else.
        if (!\enrol_apply\local\groupkey::enabled($instance)) {
            $facts[] = get_string('summarykeysoff', 'enrol_apply');
        } else if (\enrol_apply\local\groupkey::required($instance)) {
            $facts[] = get_string('summarykeysrequired', 'enrol_apply');
        } else if (\enrol_apply\local\groupkey::mandatory($instance)) {
            $facts[] = get_string('summarykeysreview', 'enrol_apply');
        } else {
            $facts[] = get_string('summarykeysany', 'enrol_apply');
        }

        return $facts;
    }

    /**
     * The summary strip above the sections, rendered.
     *
     * @param stdClass $instance The saved {enrol} record.
     * @return string HTML for the form's html element, which renders it raw.
     */
    protected function summary_strip(stdClass $instance): string {
        global $OUTPUT;

        $facts = [];
        foreach ($this->summary_facts($instance) as $index => $text) {
            $facts[] = ['first' => $index === 0, 'text' => $text];
        }

        return $OUTPUT->render_from_template('enrol_apply/instance_summary', [
            'label' => get_string('summarylabel', 'enrol_apply'),
            'facts' => $facts,
        ]);
    }

    /**
     * The places taken, for the static row under the places limit.
     *
     * @param stdClass $instance The saved {enrol} record.
     * @return string Plain text; the static element renders it raw, and it holds no user data.
     */
    protected function places_taken_text(stdClass $instance): string {
        $taken = \enrol_apply\local\capacity::places_taken($instance);
        $limit = \enrol_apply\local\capacity::places($instance);

        return $limit > 0
            ? get_string('placestaken', 'enrol_apply', (object) ['taken' => $taken, 'limit' => $limit])
            : get_string('placestakennolimit', 'enrol_apply', $taken);
    }

    /**
     * The line about the method's own questions in the Application form section.
     *
     * A new instance has none yet: its questions hang off its id. A saved one says how many it
     * asks and how many of them are shown on a condition, read as the conditions page reads them.
     *
     * @param stdClass $instance Instance record, or the defaults for a new instance.
     * @return string Escaped text for the static element, which renders it raw.
     */
    protected function questions_text($instance): string {
        $button = get_string('saveandeditfields', 'enrol_apply');
        if (empty($instance->id)) {
            return s(get_string('questionsnew', 'enrol_apply', $button));
        }

        $fields = \enrol_apply\local\answers::fields($instance);
        $count = count($fields);
        $conditional = count(\enrol_apply\local\conditions::effective($instance, $fields));

        if ($count === 0) {
            $line = get_string('questionsnone', 'enrol_apply', $button);
        } else if ($count === 1) {
            // A condition is set by an earlier question, so a lone question never has one.
            $line = get_string('questionsone', 'enrol_apply', $button);
        } else if ($conditional === 0) {
            $line = get_string('questionsmany', 'enrol_apply', (object) ['button' => $button, 'count' => $count]);
        } else {
            $line = get_string('questionsmanyconditional', 'enrol_apply', (object) [
                'button' => $button,
                'count' => $count,
                'conditional' => $conditional,
            ]);
        }

        return s($line);
    }

    /**
     * Reject a submission whose dates, capacity limits or cohort do not hold up server side.
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
           here. */
        if (($data['status'] ?? ENROL_INSTANCE_ENABLED) == ENROL_INSTANCE_ENABLED) {
            $opens = (int) ($data['enrolstartdate'] ?? 0);
            $closes = (int) ($data['enrolenddate'] ?? 0);
            if ($closes > 0 && $closes < $opens) {
                $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_apply');
            }
        }

        /* A negative limit is refused rather than stored: \enrol_apply\local\capacity reads
           anything below 1 as no limit, so the instance would keep a number it does not honour
           as typed. 0 is the way to ask for no limit. */
        foreach (['customint3', 'customint4'] as $limit) {
            if ((int) ($data[$limit] ?? 0) < 0) {
                $errors[$limit] = get_string('limitnegative', 'enrol_apply');
            }
        }

        /* The second barrier. HTML_QuickForm_select::exportValue() already drops a submitted
           value that is not among the element's options, so a forged cohort id arrives as
           null; this check keeps that true if the element becomes the ajax `cohort`
           autocomplete, whose exportValue() filters nothing (enrol_cohort carries the same
           array_diff for that reason). The offered list is rebuilt rather than stashed at
           definition() time, so it reflects the current cohorts. */
        $submitted = (int) ($data['customint5'] ?? 0);
        if ($submitted !== 0) {
            $offered = array_map('intval', array_keys($this->get_cohort_options($instance, $context)));
            if (array_diff([$submitted], $offered)) {
                $errors['customint5'] = get_string('invaliddata', 'error');
            }
        }

        if (!in_array((string) ($data['customchar1'] ?? ''), \enrol_apply\local\groupkey::MODES, true)) {
            $errors['customchar1'] = get_string('invaliddata', 'error');
        }
        if (!in_array((string) ($data['customchar2'] ?? ''), ['', \enrol_apply\local\groupkey::NOTICE_ON], true)) {
            $errors['customchar2'] = get_string('invaliddata', 'error');
        }

        return $errors;
    }

    /**
     * The groups of the course that carry an enrolment key, rendered for the static element.
     *
     * Names only, never a key. With local_groupdist installed each group also shows its seats,
     * and a full one is marked, because its key currently admits nobody.
     *
     * A group the teacher may not see by core's visibility rule is left out, and so is the note
     * naming it as the group that admits instead: the list says what it says when there is no
     * such group. The sentence saying no group has a key still needs there to be none at all.
     *
     * @param int $courseid Course id.
     * @param context $context Course context, for formatting the group names.
     * @return string HTML for the static element, which renders it raw.
     */
    protected function group_key_summary(int $courseid, $context): string {
        global $OUTPUT, $USER;

        $keyed = \enrol_apply\local\groupkey::summary($courseid, (int) $USER->id);
        $seats = \enrol_apply\local\groupseats::for_groups(array_keys($keyed));
        $hasseats = \enrol_apply\local\groupseats::available();

        $names = [];
        foreach ($keyed as $id => $group) {
            if ($group->name === null) {
                continue;
            }
            // Plain spelling: every sink in the template is a double stash or a {{#str}} parameter.
            $names[$id] = format_string($group->name, true, ['context' => $context, 'escape' => false]);
        }

        $groups = [];
        foreach ($keyed as $id => $group) {
            if (!isset($names[$id])) {
                continue;
            }
            $seat = $seats[$id];
            $shadowedby = $group->shadowedby !== null && isset($names[$group->shadowedby]) ? $group->shadowedby : null;
            $groups[] = [
                'name' => $names[$id],
                'hasseats' => $hasseats,
                'nolimit' => $seat->seats === null,
                'members' => (int) $seat->members,
                'seats' => (int) $seat->seats,
                'full' => $seat->seats !== null && (int) $seat->members >= $seat->seats,
                // A flag beside the name: Mustache reads a group named "0" as false.
                'isshadowed' => $shadowedby !== null,
                'shadowedby' => $shadowedby === null ? '' : $names[$shadowedby],
                'untypable' => $group->untypable,
            ];
        }

        /* Above the review page's threshold the list folds (RP-D18): a course with dozens of keyed
           groups made this form a page long. What needs the teacher's attention stays outside the
           fold: a full group, whose key admits nobody now, a key another group shadows, and a key
           nobody can type. */
        $fold = count($groups) > \enrol_apply\local\groupfilter::THRESHOLD;
        $attention = array_values(array_filter(
            $groups,
            static fn(array $group): bool => ($group['hasseats'] && $group['full']) || $group['isshadowed'] || $group['untypable']
        ));

        return $OUTPUT->render_from_template('enrol_apply/groupkey_summary', [
            'hasgroups' => (bool) $groups,
            'haskeys' => (bool) $keyed,
            'groups' => $groups,
            'fold' => $fold,
            'foldsummary' => get_string('groupkeyscount', 'enrol_apply', count($groups)),
            'hasattention' => $fold && $attention,
            'attention' => $fold ? $attention : [],
            'attentionline' => count($attention) === 1
                ? get_string('groupkeysattentionone', 'enrol_apply')
                : get_string('groupkeysattention', 'enrol_apply', count($attention)),
            'showall' => get_string('groupkeysshowall', 'enrol_apply', count($groups)),
            'manageurl' => (new moodle_url('/group/index.php', ['id' => $courseid]))->out(false),
        ]);
    }

    /**
     * The warnings key-only mode would give for this course and instance as they stand.
     *
     * Three facts, each only when it holds: no group of the course carries a key, so the mode
     * enrols nobody; every key would join a group with no seat left (local_groupdist), so it
     * enrols nobody now; applications made before still wait, which the mode does not decide.
     * A key's own group and the instance's list both count, as at the write door (G14).
     *
     * For the mode that sends a keyed application for review, the first two read "apply" and the
     * third counts only the applications without a key, the ones that mode never makes and does
     * not decide.
     *
     * @param \stdClass $instance The instance being edited; id 0 for a new one.
     * @param bool $review True to word and count them for MODE_REVIEW instead of MODE_REQUIRED.
     * @return string The rendered warnings, or '' when there are none.
     */
    protected function key_only_warnings(\stdClass $instance, bool $review = false): string {
        global $DB, $OUTPUT;

        $warnings = [];
        $keyed = \enrol_apply\local\groupkey::summary((int) $instance->courseid);
        if (!$keyed) {
            $warnings[] = [
                'danger' => true,
                'message' => get_string($review ? 'keyreviewnokeys' : 'keyonlynokeys', 'enrol_apply'),
            ];
        } else if (\enrol_apply\local\groupseats::available()) {
            $plugin = enrol_get_plugin('apply');
            $admitting = array_filter(
                array_keys($keyed),
                static fn(int $groupid): bool => !\enrol_apply\local\groupseats::full(
                    $plugin->key_route_groups($instance, $groupid)
                )
            );
            if (!$admitting) {
                $warnings[] = [
                    'danger' => true,
                    'message' => get_string($review ? 'keyreviewallfull' : 'keyonlyallfull', 'enrol_apply'),
                ];
            }
        }

        if (!empty($instance->id)) {
            [$where, $params] = \enrol_apply\local\queue::awaiting_decision_where();
            $sql = 'SELECT COUNT(1) FROM {user_enrolments} ue WHERE ue.enrolid = :enrolid AND ' . implode(' AND ', $where);
            if ($review) {
                // Only the applications made without a key: the keyed ones are what this mode writes.
                $sql .= ' AND NOT EXISTS (SELECT 1 FROM {enrol_apply_submission} s'
                    . ' WHERE s.userenrolmentid = ue.id AND s.keygroupid <> 0)';
            }
            $waiting = $DB->count_records_sql($sql, $params + ['enrolid' => $instance->id]);
            if ($waiting) {
                $warnings[] = [
                    'danger' => false,
                    'message' => get_string($review ? 'keyreviewpending' : 'keyonlypending', 'enrol_apply', $waiting),
                ];
            }
        }

        return $warnings ? $OUTPUT->render_from_template('enrol_apply/groupkey_warnings', ['warnings' => $warnings]) : '';
    }

    /**
     * Whether the course is in separate groups mode, the one mode that narrows deciders.
     *
     * @param int $courseid Course id.
     * @return bool True for SEPARATEGROUPS.
     */
    protected function separate_groups(int $courseid): bool {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id, groupmode', IGNORE_MISSING);

        return $course && (int) groups_get_course_groupmode($course) === SEPARATEGROUPS;
    }

    /**
     * Whether an enabled self enrolment method of the course accepts group keys too.
     *
     * enrol_self reads group keys only when the instance has a key of its own and its "use group
     * enrolment keys" option on (enrol/self/lib.php, password && customint1).
     *
     * @param int $courseid Course id.
     * @return bool True when the same keys would enrol through self enrolment.
     */
    protected function self_enrolment_takes_group_keys(int $courseid): bool {
        global $DB;

        return $DB->record_exists_select(
            'enrol',
            "courseid = :courseid AND enrol = :self AND status = :enabled AND customint1 = 1 AND password <> ''",
            ['courseid' => $courseid, 'self' => 'self', 'enabled' => ENROL_INSTANCE_ENABLED]
        );
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
