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
 * What the plugin leaves in core's tables that core's uninstall does not remove.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove the plugin's rows in core's tables that core's uninstall_plugin() (lib/adminlib.php) leaves behind.
 *
 * Core drops the plugin's own tables, its configuration, capabilities, tasks, events, files and message providers,
 * but neither the report builder persistents of its system reports (one per enrolment method the course report was
 * opened for, each with every reader's stored filter values, which can hold a group or a typed name) nor user
 * preferences. Each report goes through core's persistent ({@see \enrol_apply\local\reportpersistents::delete_all()}),
 * whose before_delete() removes what hangs on it; then the plugin's own user preferences, by their exact names.
 *
 * @return bool
 */
function xmldb_enrol_apply_uninstall() {
    global $DB;
    \enrol_apply\local\reportpersistents::delete_all();
    [$insql, $params] = $DB->get_in_or_equal([
        \enrol_apply\local\reportpreference::NAME,
        \enrol_apply\local\queuepreference::NAME,
        \enrol_apply\local\queuepreference::NAME_WIDE,
    ]);
    $DB->delete_records_select('user_preferences', "name $insql", $params);
    return true;
}
