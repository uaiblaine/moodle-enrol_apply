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
 * Restore support for the enrolment upon approval plugin.
 *
 * @package   enrol_apply
 * @category  backup
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Restores the enrol_apply owned data from an enrolment backup.
 *
 * @package   enrol_apply
 * @category  backup
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_enrol_apply_plugin extends restore_enrol_plugin {
    /**
     * Declare the paths this plugin restores.
     *
     * @return array Array of restore_path_element objects.
     */
    protected function define_enrol_plugin_structure() {
        return [
            new restore_path_element('enrol_apply_applygroup', $this->get_pathfor('/applygroups/applygroup')),
        ];
    }

    /**
     * Restore one configured group mapping.
     *
     * @param array $data Backup data of the group mapping.
     * @return void
     */
    public function process_enrol_apply_applygroup($data) {
        global $DB;

        $data = (object) $data;

        $enrolid = $this->get_new_parentid('enrol');
        if (!$enrolid) {
            // The enrol instance itself was not restored, so there is nothing to attach to.
            return;
        }

        $groupid = $this->get_mappingid('group', $data->groupid);
        if (!$groupid) {
            // Groups were excluded from this restore, or the group no longer exists.
            return;
        }

        $exists = $DB->record_exists('enrol_apply_groups', ['enrolid' => $enrolid, 'groupid' => $groupid]);
        if (!$exists) {
            $DB->insert_record('enrol_apply_groups', (object) [
                'enrolid' => $enrolid,
                'groupid' => $groupid,
            ]);
        }
    }
}
