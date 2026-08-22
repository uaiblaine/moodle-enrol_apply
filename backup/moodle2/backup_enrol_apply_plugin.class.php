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
 * Three things travel. The groups an approved applicant is added to are instance
 * configuration and always go. The comments submitted with applications, and the durable
 * application trail, are user data and go only when the backup includes users - not in the
 * logs block, where both settings also default to 0: the users setting LOCKS logs, so gating
 * on logs would be strictly narrower and would restore the comments while dropping the
 * record of the decisions taken on them.
 *
 * The comments are keyed by user_enrolments.id, for which core registers no mapping; the
 * plugin registers its own from restore_user_enrolment(), see
 * enrol_apply_plugin::restore_user_enrolment(). The trail is keyed by its own user ids
 * instead, which is why it annotates them.
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

        $submissions = new backup_nested_element('submissions');
        $submission = new backup_nested_element('submission', ['id'], [
            'userid',
            'userenrolmentid',
            'comment',
            'userinfodata',
            'status',
            'outcomemessage',
            'timecreated',
            'timedecided',
            'decidedby',
        ]);

        $pluginwrapper->add_child($applygroups);
        $applygroups->add_child($applygroup);
        $pluginwrapper->add_child($applications);
        $applications->add_child($application);
        $pluginwrapper->add_child($submissions);
        $submissions->add_child($submission);

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

            /* courseid and enrolid are deliberately not in the element: both are rebuilt on
               restore from the course being restored into and the instance the row lands
               under, and carrying the originals would only invite somebody to trust them.

               A known limitation, stated here because it is invisible otherwise: the source
               is keyed on the enrol instance, because that is the element this structure
               hangs off. A record whose instance has since been deleted - which the trail
               survives on purpose, see enrol_apply_plugin::delete_instance() - therefore has
               nothing to attach to and does not travel. Backup structures for an enrol plugin
               are addressed per instance by core, so there is no correct place to put an
               instance-less record without inventing one; it is recorded in README.md rather
               than papered over. */
            $submission->set_source_table('enrol_apply_submission', ['enrolid' => backup::VAR_PARENTID]);

            /* Both user columns, and inside the gate. Core annotates the applicant already,
               through its own user_enrolments element, but only for enrolments it backs up -
               and the decider is nobody core has any reason to have annotated. Without these
               a restored trail would name users who never reached users.xml.

               A decidedby of 0 on an undecided row is annotated too and is inert: users.xml
               is built by joining the annotated ids against {user}, where no row has id 0. */
            $submission->annotate_ids('user', 'userid');
            $submission->annotate_ids('user', 'decidedby');
        }

        $applygroup->annotate_ids('group', 'groupid');

        return $plugin;
    }
}
