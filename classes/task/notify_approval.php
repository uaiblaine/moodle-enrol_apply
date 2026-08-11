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

namespace enrol_apply\task;

/**
 * Tells an applicant that their application was approved.
 *
 * Queued by enrol_apply_plugin::complete_approval(), which runs for every route an
 * approval can take — the plugin's own queue and core's "Edit enrolment" screen alike.
 * Doing it from a task rather than inline is what lets the second route notify at all:
 * that one goes through a core page which knows nothing about this plugin, and the
 * before_user_enrolment_updated hook fires before the row is even written.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify_approval extends \core\task\adhoc_task {
    /**
     * Name shown for this task in the admin screens.
     *
     * @return string Task name.
     */
    public function get_name() {
        return get_string('notifyapprovaltask', 'enrol_apply');
    }

    /**
     * Send the confirmation for the user enrolment named in the custom data.
     *
     * @return void
     */
    public function execute() {
        $data = $this->get_custom_data();
        if (empty($data->userenrolmentid)) {
            return;
        }

        $plugin = enrol_get_plugin('apply');
        $plugin->notify_confirmed_application((int) $data->userenrolmentid);
    }
}
