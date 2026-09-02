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

namespace enrol_apply\form;

use context_course;
use moodleform;

/**
 * The confirmation step of a participants-page bulk decision.
 *
 * Core ships a base form for this extension point, enrol_bulk_enrolment_change_form, and both
 * precedents subclass it with an empty body. This one does not, for two measured reasons.
 * Its users table indexes an options array holding only -1, 0 and 1 by the row's status with
 * no isset() guard (enrol/bulkchange_forms.php:57-61 and :84), so every waiting-list row -
 * ENROL_APPLY_USER_WAIT is 2 - raises "Undefined array key 2"; and its labels are read out of
 * the enrol_manual language pack, which is not this plugin's to depend on.
 *
 * What it must still copy is the one line that is not decoration. The selected user ids reach
 * the driver from the participants table's own checkbox names, which exist only on the FIRST
 * post; on the second they survive purely because core's base form emits a hidden bulkuser[]
 * input per row (enrol/bulkchange_forms.php:81, read back at user/action_redir.php:67). A form
 * that omits them submits cleanly and then bounces the operator back to the participants page
 * with "No users selected", as though they had ticked nothing.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_decision_form extends moodleform {
    /**
     * Build the confirmation form.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $users = $this->_customdata['users'] ?? [];

        /* No header element, deliberately. Core already prints the operation's title above
           this form as the page heading (user/action_redir.php:271), so one would only repeat
           it - and a collapsible header renders an <a role="button"> carrying that same title
           in a visually-hidden span. Behat's "button" selector matches any element with
           role="button" by its text, and that toggle sits before the submit input in document
           order, so pressing the button whose label is the operation's title COLLAPSED the
           form instead of submitting it. Measured: the scenario failed with the form still on
           screen, no notification and no validation error. */
        $mform->addElement('static', 'bulkdescription', '', $this->_customdata['description']);

        /* What this decision will NOT reach, said before anything is written. The dispatch core
           hands the plugin is filtered to ONE enrolment method and cannot be widened from here,
           and the case where that is silent - one person holding applications on two of them -
           is reachable rather than exotic, because two apply methods are two intakes.

           Rendered through the renderer's own notification rather than as bare text: it is a
           warning and it has to read as one beside a submit button. A static element's content
           is written into core's element-template.mustache through a TRIPLE stash, so the
           sentence arrives already escaped, which is what other_applications_notice() produces. */
        if (!empty($this->_customdata['othernotice'])) {
            global $OUTPUT;

            $mform->addElement('static', 'bulkothermethods', '', $OUTPUT->notification(
                $this->_customdata['othernotice'],
                \core\output\notification::NOTIFY_WARNING,
                false
            ));
        }

        /* Names go through s() because a static element is rendered by core's own
           element-template.mustache through a triple stash. */
        $names = [];
        foreach ($users as $user) {
            $names[] = s(fullname($user));
        }
        $mform->addElement(
            'static',
            'bulkapplicants',
            get_string('bulkapplicants', 'enrol_apply'),
            implode(', ', $names)
        );

        /* The selection itself. See the class docblock: without these the second post
           carries no ids and core redirects as though nothing had been ticked. */
        foreach (array_keys($users) as $index => $userid) {
            $mform->addElement('hidden', 'bulkuser[' . $index . ']', $userid);
            $mform->setType('bulkuser[' . $index . ']', PARAM_INT);
        }

        /* PARAM_TEXT and not PARAM_RAW, exactly as the queue's own message box is: this
           reaches the applicant's notification and the durable record, which outlives the
           enrolment it belongs to. */
        $mform->addElement(
            'textarea',
            'outcomemessage',
            get_string('outcomemessage', 'enrol_apply'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('outcomemessage', PARAM_TEXT);
        $mform->addHelpButton('outcomemessage', 'outcomemessage', 'enrol_apply');

        /* The decider's own note, offered here for the same reason the message is: this is a
           third decision surface, and a field the queue and the review page both offer while
           this one silently does not is exactly how two surfaces come to describe the same
           record differently. Same PARAM_TEXT, same durable record, opposite audience - the
           applicant never reads this one. */
        $mform->addElement(
            'textarea',
            'decisionnote',
            get_string('decisionnote', 'enrol_apply'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('decisionnote', PARAM_TEXT);
        $mform->addHelpButton('decisionnote', 'decisionnote', 'enrol_apply');

        if (!empty($this->_customdata['withdecision'])) {
            $this->add_decision_controls((int) $this->_customdata['courseid']);
        }

        $this->add_action_buttons(true, $this->_customdata['button']);
    }

    /**
     * Add the group and role choosers, each only where it has something to offer.
     *
     * Both lists carry the ESCAPED spelling of every name, which is the opposite of what the
     * queue's own renderer hands its Mustache template. The sink is what decides: core renders
     * a select's options through a triple stash in element-select.mustache, where the escaped
     * spelling is correct, while a double stash escapes for itself and needs the plain one.
     * The role list is normalised through format_string() for a second reason as well -
     * get_assignable_roles() returns a mixed list, escaping a role that carries a name of its
     * own and returning a bare language string for one that does not, which is every role a
     * stock site ships. The call is idempotent on the half that is already escaped.
     *
     * Nothing here is a security boundary. confirm_enrolment() allowlists the posted group ids
     * against the course's own groups and the posted role against get_assignable_roles(), per
     * instance, because a forged post never passes through this form at all.
     *
     * @param int $courseid Course the selected applications belong to.
     * @return void
     */
    protected function add_decision_controls(int $courseid): void {
        $mform = $this->_form;
        $coursecontext = context_course::instance($courseid);

        $groups = [];
        foreach (groups_get_all_groups($courseid) as $group) {
            $groups[$group->id] = format_string($group->name, true, ['context' => $coursecontext]);
        }
        if ($groups) {
            $mform->addElement(
                'select',
                'groups',
                get_string('decisiongroups', 'enrol_apply'),
                $groups,
                ['multiple' => 'multiple', 'size' => min(6, count($groups))]
            );
            $mform->setType('groups', PARAM_INT);
            $mform->addHelpButton('groups', 'decisiongroups', 'enrol_apply');
        }

        $roles = [0 => get_string('decisionroledefault', 'enrol_apply')];
        foreach (get_assignable_roles($coursecontext) as $roleid => $rolename) {
            $roles[$roleid] = format_string($rolename, true, ['context' => $coursecontext]);
        }
        if (count($roles) > 1) {
            $mform->addElement('select', 'roleid', get_string('decisionrole', 'enrol_apply'), $roles);
            $mform->setType('roleid', PARAM_INT);
            $mform->setDefault('roleid', 0);
            $mform->addHelpButton('roleid', 'decisionrole', 'enrol_apply');
        }
    }
}
