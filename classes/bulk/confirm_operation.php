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

namespace enrol_apply\bulk;

use stdClass;

/**
 * Confirm the selected applications from the participants page.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class confirm_operation extends decision_operation {
    /**
     * The title shown in the participants page bulk menu, and on the confirmation button.
     *
     * @return string Localised title.
     */
    public function get_title() {
        return get_string('bulkconfirm', 'enrol_apply');
    }

    /**
     * The identifier core uses as the operation's array key and url parameter.
     *
     * @return string Operation identifier.
     */
    public function get_identifier() {
        return 'confirmapplications';
    }

    /**
     * Confirmation offers the group and role choosers; the other two decisions do not.
     *
     * @return bool Always true.
     */
    protected function offers_decision_controls(): bool {
        return true;
    }

    /**
     * The sentence explaining what the operator is about to do.
     *
     * @return string Localised description.
     */
    protected function get_description(): string {
        return get_string('bulkconfirmdesc', 'enrol_apply');
    }

    /**
     * Confirm through the plugin, so the whole approval runs exactly as the queue's does.
     *
     * Both keys of the decision are passed whenever the form offered the controls, and are
     * passed even when nothing was picked: confirm_enrolment() gates on array_key_exists
     * rather than on emptiness precisely so an empty choice CLEARS the one a previous
     * decision on the same application left behind. Which of the posted ids are acceptable
     * is decided there, per instance, against the course's own groups and the operator's
     * assignable roles - never here, and never in the form.
     *
     * @param array $userenrolmentids User enrolment ids of the whole selection.
     * @param string $message Message the decider wrote to the applicants, empty for none.
     * @param stdClass $properties Submitted form data.
     * @return void
     */
    protected function decide(array $userenrolmentids, string $message, stdClass $properties): void {
        $decision = [
            'groups' => array_map('intval', (array) ($properties->groups ?? [])),
            'roleid' => (int) ($properties->roleid ?? 0),
        ];

        $this->plugin->confirm_enrolment($userenrolmentids, $message, $decision);
    }

    /**
     * A confirmed application is an active enrolment.
     *
     * @param int|null $status The user_enrolments.status, or null when the row is gone.
     * @return bool True when the enrolment is active.
     */
    protected function has_decided(?int $status): bool {
        return $status === ENROL_USER_ACTIVE;
    }
}
