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

        /* Which users' data travels is core's decision, not this plugin's, and the two are
           easy to confuse because the obvious reading - "the users setting" - is wrong.

           Core gates its own <user_enrolments> on "empty($keptroles) && $users", with a
           SECOND branch for a course copy that keeps roles (backup/moodle2/backup_stepslib.php,
           byte-identical on 5.1 and 5.2). Kept roles come from the asynchronous course copy,
           which sets the users setting to '1' whenever roles are kept AND user data is wanted
           (lib/classes/task/asynchronous_copy_task.php).

           Reading the setting alone therefore disagrees with core in both directions. With
           kept roles and user data, the setting is 1 and this plugin would write EVERY
           applicant's comment and profile snapshot while core writes only the enrolments of
           users holding a kept role - free text belonging to people the copy deliberately
           excluded, sitting in the archive file. It is dropped on restore, because the user
           mapping misses, so nothing ever looks wrong. With kept roles and no user data, the
           setting is 0 and this plugin would write nothing while core still writes those
           enrolments, so the copy loses the comments for enrolments it does carry.

           So the predicate below is core's, reproduced rather than approximated. */
        $keptroles = $this->task->get_kept_roles();
        $users = $this->task->get_setting_value('users');

        if (empty($keptroles) && $users) {
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
        } else if (!empty($keptroles)) {
            [$insql, $inparams] = $DB->get_in_or_equal($keptroles);
            $roleparams = [];
            foreach ($inparams as $inparam) {
                $roleparams[] = backup_helper::is_sqlparam($inparam);
            }

            /* EXISTS rather than core's INNER JOIN, and not as a stylistic preference: a user
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
