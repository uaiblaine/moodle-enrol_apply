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
 * application trail, are user data and follow exactly the users core itself backs up - not
 * the logs block, where both settings also default to 0: the users setting LOCKS logs, so
 * gating on logs would be strictly narrower and would restore the comments while dropping the
 * record of the decisions taken on them.
 *
 * "Exactly the users core backs up" is not the same as "the users setting", and the difference
 * is what {@see define_enrol_plugin_structure()} reproduces below.
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

        /* WHOSE data travels is core's decision, not this plugin's, and the obvious reading -
           "the users setting" - is wrong.

           A course copy can keep the enrolments of users holding chosen roles. Core gates its
           own <user_enrolments> on "empty($keptroles) && $users" and has a SECOND branch that
           joins {role_assignments} when roles are kept (backup/moodle2/backup_stepslib.php,
           byte-identical on 5.1 and 5.2). The copy task sets the users setting to '1' whenever
           roles are kept AND user data is wanted (lib/classes/task/asynchronous_copy_task.php).

           So "users is 1" does NOT mean "every user's data may travel". In a kept-roles copy
           it means "the kept-role users' data may travel", and reading the setting alone put
           every applicant's comment and profile snapshot into the archive - free text
           belonging to the people the copy exists to exclude.

           WHETHER any of it travels is still the users setting, which is why the role check
           is nested inside it rather than beside it. With user data off, the copy task sets
           the setting to '0' and core forces the restore's own users setting off and its
           enrolments setting to ENROL_NEVER: no apply instance and no user enrolment reaches
           the destination, so nothing this plugin writes could ever be restored there. Core
           still writes its <enrolment> rows in that cell - it re-enrols the kept-role users
           through the MANUAL plugin after the restore instead - but for this plugin the same
           write would be personal data in an archive with nowhere to go, which is the exact
           exposure the rest of this method exists to prevent. Measured on 5.2: destination
           enrol instances [manual, guest, self], zero user enrolments, zero submission rows. */
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
        } else if ($users) {
            [$insql, $inparams] = $DB->get_in_or_equal($keptroles);
            $roleparams = [];
            foreach ($inparams as $inparam) {
                $roleparams[] = backup_helper::is_sqlparam($inparam);
            }

            /* Both halves of core's predicate matter and only one of them is obvious. The
               role must be one of the kept ones, AND the assignment must be in THIS COURSE's
               context - somebody who is a student here and a teacher elsewhere holds the kept
               role, but not here, and core writes no enrolment for them.

               EXISTS rather than core's INNER JOIN, and not as a stylistic preference: a user
               holding two of the kept roles matches the join twice, which would write the same
               application into the archive twice. Core tolerates that for its own enrolments;
               here it is free to avoid, and avoiding it keeps the element's ids unique. */
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

        /* Outside the gate, and safe there: an annotation fires per row actually written
           (backup_structure_processor::process_final_element only annotates a final element
           that is_set()), so an element with no source annotates nobody. Core annotates the
           applicant already through its own user_enrolments element, but only for the
           enrolments IT writes, and the decider is somebody core has no reason to have
           annotated at all - without these a restored trail would name users who never
           reached users.xml.

           A decidedby of 0 on an undecided row is annotated too and is inert: users.xml is
           built by joining the annotated ids against {user}, where no row has id 0. */
        $submission->annotate_ids('user', 'userid');
        $submission->annotate_ids('user', 'decidedby');

        $applygroup->annotate_ids('group', 'groupid');

        return $plugin;
    }
}
