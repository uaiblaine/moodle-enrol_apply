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
 * Cancel the selected applications from the participants page.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cancel_operation extends decision_operation {
    /**
     * The title shown in the participants page bulk menu, and on the confirmation button.
     *
     * @return string Localised title.
     */
    public function get_title() {
        return get_string('bulkcancel', 'enrol_apply');
    }

    /**
     * The identifier core uses as the operation's array key and url parameter.
     *
     * Deliberately not 'deleteselectedusers'. Core keys a special case on that literal
     * string - it drops the acting user from the selection with a warning, whatever the
     * plugin is - and this operation removes an enrolment nobody has been granted yet
     * rather than one they hold, so inheriting that behaviour would be misleading.
     *
     * @return string Operation identifier.
     */
    public function get_identifier() {
        return 'cancelapplications';
    }

    /**
     * The sentence explaining what the operator is about to do.
     *
     * @return string Localised description.
     */
    protected function get_description(): string {
        return get_string('bulkcanceldesc', 'enrol_apply');
    }

    /**
     * Cancel through the plugin, which unenrols the applicant and stamps the durable record.
     *
     * @param array $userenrolmentids User enrolment ids of the whole selection.
     * @param string $message Message the decider wrote to the applicants, empty for none.
     * @param stdClass $properties Submitted form data, for the decision note; a cancellation carries no choices.
     * @return void
     */
    protected function decide(array $userenrolmentids, string $message, stdClass $properties): void {
        $this->plugin->cancel_enrolment(
            $userenrolmentids,
            $message,
            ['note' => (string) ($properties->decisionnote ?? '')]
        );
    }

    /**
     * A cancelled application has no user enrolment left at all.
     *
     * @param int|null $status The user_enrolments.status, or null when the row is gone.
     * @return bool True when the row is gone.
     */
    protected function has_decided(?int $status): bool {
        return $status === null;
    }
}
