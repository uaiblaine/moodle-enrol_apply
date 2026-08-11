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
 * Backup support for the enrolment upon approval plugin.
 *
 * @package   enrol_apply
 * @category  backup
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Adds the enrol_apply owned data to the enrolment backup structure.
 *
 * Two things travel: the groups an approved applicant is added to, which are instance
 * configuration, and the comments submitted with applications, which are user data and so
 * are only included when the backup includes users. The comments are keyed by
 * user_enrolments.id, for which core registers no mapping; the plugin registers its own
 * from restore_user_enrolment(), see enrol_apply_plugin::restore_user_enrolment().
 *
 * @package   enrol_apply
 * @category  backup
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_enrol_apply_plugin extends backup_enrol_plugin {
    /**
     * Append the enrol_apply structures to the enrolment backup.
     *
     * @return backup_plugin_element The plugin element with the plugin data attached.
     */
    protected function define_enrol_plugin_structure() {
        $plugin = $this->get_plugin_element();

        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $applygroups = new backup_nested_element('applygroups');
        $applygroup = new backup_nested_element('applygroup', ['id'], ['groupid']);

        $applications = new backup_nested_element('applications');
        $application = new backup_nested_element('application', ['id'], ['userenrolmentid', 'comment']);

        $pluginwrapper->add_child($applygroups);
        $applygroups->add_child($applygroup);
        $pluginwrapper->add_child($applications);
        $applications->add_child($application);

        $applygroup->set_source_table('enrol_apply_groups', ['enrolid' => backup::VAR_PARENTID]);

        /* The comment is personal data, so it follows the same setting as the user
           enrolment it belongs to: without users in the backup there is nothing for it to
           attach to on restore either. */
        if ($this->task->get_setting_value('users')) {
            $application->set_source_sql(
                "SELECT ai.id, ai.userenrolmentid, ai.comment
                   FROM {enrol_apply_applicationinfo} ai
                   JOIN {user_enrolments} ue ON ue.id = ai.userenrolmentid
                  WHERE ue.enrolid = ?",
                [backup::VAR_PARENTID]
            );
        }

        $applygroup->annotate_ids('group', 'groupid');

        return $plugin;
    }
}
