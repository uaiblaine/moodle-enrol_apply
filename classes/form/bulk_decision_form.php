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
 * precedents subclass it with an empty body. This one does not, for two reasons. Its
 * get_users_table() indexes the get_status_options() array, which holds only -1, 0 and 1, by
 * the row's status with no isset() guard, so every waiting-list row (ENROL_APPLY_USER_WAIT is
 * 2) raises "Undefined array key 2"; and its labels come from the enrol_manual language pack,
 * which is not this plugin's to depend on.
 *
 * What it must still copy is the hidden bulkuser[] input per row. The selected user ids reach
 * the driver from the participants table's own checkbox names only on the first post; on the
 * second, user/action_redir.php falls back to bulkuser[]. A form that omits them submits
 * cleanly and sends the operator back to the participants page with "No users selected".
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

        /* No header element, deliberately. user/action_redir.php already prints the operation's
           title as the page heading, so one would only repeat it - and a collapsible header
           renders an <a role="button"> carrying that same title in a visually-hidden span.
           Behat's "button" selector matches any element with role="button" by its text, and
           that toggle sits before the submit input, so pressing the button labelled with the
           operation's title would collapse the form instead of submitting it. */
        $mform->addElement('static', 'bulkdescription', '', $this->_customdata['description']);

        /* Applications this decision will not reach, said before anything is written; see
           \enrol_apply\bulk\decision_operation::other_applications_notice(), which produces the
           sentence already escaped because the notification and the static element both render
           raw. Rendered as a notification so it reads as a warning beside the submit button. */
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

        /* PARAM_TEXT and not PARAM_RAW, as in the queue's own message box: this reaches the
           applicant's notification and the durable record, which outlives the enrolment it
           belongs to. */
        $mform->addElement(
            'textarea',
            'outcomemessage',
            get_string('outcomemessage', 'enrol_apply'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('outcomemessage', PARAM_TEXT);
        $mform->addHelpButton('outcomemessage', 'outcomemessage', 'enrol_apply');

        /* The decider's own note, offered as on the queue and the review page so every decision
           surface records the same fields. Same PARAM_TEXT and durable record, opposite
           audience: the applicant never reads this one. */
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
     * Both lists carry the escaped spelling of every name, unlike what the queue's own renderer
     * hands its Mustache template: core renders a select's options through a triple stash in
     * element-select.mustache, while a double stash escapes for itself and needs the plain one.
     * The role list is normalised through format_string() for a second reason:
     * get_assignable_roles() escapes a role that carries a name of its own but returns a bare
     * language string for one that does not, which is every role a stock site ships.
     * format_string() is idempotent on the half that is already escaped.
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
