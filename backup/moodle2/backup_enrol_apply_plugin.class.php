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
 * application trail, are user data and follow exactly the users core itself backs up, which
 * is not always the same as the users setting; see {@see define_enrol_plugin_structure()}.
 * The trail is not gated on the logs setting: logs depends on users, so that gate would be
 * narrower and would restore the comments while dropping the record of the decisions taken on
 * them.
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
        global $DB;

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
            'decisionnote',
            'decidedgroups',
            'decidedrole',
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

        /* Whose data travels mirrors core's own <user_enrolments> element in
           backup_enrolments_structure_step, not the users setting alone. A course copy can keep
           only the users holding chosen roles: core then writes their enrolments through a
           second branch joining {role_assignments}, and \core\task\asynchronous_copy_task sets
           users to '1' whenever roles are kept and user data is wanted. Reading the setting
           alone would put every applicant's comment and profile snapshot into an archive meant
           to exclude them.

           Whether anything travels is still the users setting, so the role branch is nested
           inside it. With user data off, the copy task sets users to '0' and the restore then
           defaults to no users and no enrolment methods, so nothing this plugin writes could be
           restored. Core still writes its kept-role enrolments in that case (it re-enrols those
           users through enrol_manual after the restore), but for this plugin the same rows
           would be personal data in an archive with nowhere to go. */
        $keptroles = $this->task->get_kept_roles();
        $users = $this->task->get_setting_value('users');

        if ($users && empty($keptroles)) {
            $application->set_source_sql(
                "SELECT ai.id, ai.userenrolmentid, ai.comment
                   FROM {enrol_apply_applicationinfo} ai
                   JOIN {user_enrolments} ue ON ue.id = ai.userenrolmentid
                  WHERE ue.enrolid = ?",
                [backup::VAR_PARENTID]
            );

            /* courseid and enrolid are deliberately not in the element: both are rebuilt on
               restore from the course being restored into and the instance the row lands
               under.

               Known limitation, documented in README.md: the source is keyed on the enrol
               instance, because core addresses an enrol plugin's backup structure per
               instance. A record whose instance has since been deleted (the trail survives
               that on purpose, see enrol_apply_plugin::delete_instance()) has nothing to attach
               to and does not travel. */
            $submission->set_source_table('enrol_apply_submission', ['enrolid' => backup::VAR_PARENTID]);
        } else if ($users) {
            [$insql, $inparams] = $DB->get_in_or_equal($keptroles);
            $roleparams = [];
            foreach ($inparams as $inparam) {
                $roleparams[] = backup_helper::is_sqlparam($inparam);
            }

            /* Both halves of core's predicate: the role must be one of the kept ones, and the
               assignment must be in this course's context - somebody who holds a kept role only
               elsewhere gets no enrolment from core either.

               EXISTS rather than core's INNER JOIN: a user holding two of the kept roles would
               match the join twice and write the same application into the archive twice. */
            $application->set_source_sql(
                "SELECT ai.id, ai.userenrolmentid, ai.comment
                   FROM {enrol_apply_applicationinfo} ai
                   JOIN {user_enrolments} ue ON ue.id = ai.userenrolmentid
                  WHERE ue.enrolid = ?
                        AND EXISTS (
                            SELECT 1
                              FROM {role_assignments} ra
                             WHERE ra.userid = ue.userid AND ra.contextid = ? AND ra.roleid {$insql}
                        )",
                array_merge([backup::VAR_PARENTID, backup::VAR_CONTEXTID], $roleparams)
            );

            $submission->set_source_sql(
                "SELECT s.*
                   FROM {enrol_apply_submission} s
                  WHERE s.enrolid = ?
                        AND EXISTS (
                            SELECT 1
                              FROM {role_assignments} ra
                             WHERE ra.userid = s.userid AND ra.contextid = ? AND ra.roleid {$insql}
                        )",
                array_merge([backup::VAR_PARENTID, backup::VAR_CONTEXTID], $roleparams)
            );
        }

        /* Outside the gate, and safe there: backup_structure_processor::process_final_element()
           annotates only a final element that is_set(), so an element with no source annotates
           nobody. Core annotates the applicant only for the enrolments it writes, and never the
           decider; without these a restored trail would name users who never reached users.xml.

           A decidedby of 0 on an undecided row is annotated too and is inert: users.xml is
           built by joining the annotated ids against {user}, where no row has id 0. */
        $submission->annotate_ids('user', 'userid');
        $submission->annotate_ids('user', 'decidedby');

        /* The role a decider chose is annotated; the groups deliberately are not.

           Groups need nothing: when the groups setting is on,
           backup_annotate_course_groups_and_groupings annotates every group of the course, so
           each one reaches groups.xml whether or not anything refers to it. decidedgroups is a
           comma-separated column in any case, which annotate_ids() - one id per row - could
           not walk.

           The role does need it: roles.xml selects on 'rolefinal', so a role reaches the
           archive only through an annotation, usually from a role_assignment the applicant
           holds. A cancelled application has none, and without this line its role would be
           absent from the archive and unmappable on restore.

           A decidedrole of 0 is annotated too and is inert: roles.xml joins the annotated ids
           against {role}, where no row has id 0. */
        $submission->annotate_ids('role', 'decidedrole');

        $applygroup->annotate_ids('group', 'groupid');

        return $plugin;
    }
}
