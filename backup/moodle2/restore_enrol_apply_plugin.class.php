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
            new restore_path_element('enrol_apply_application', $this->get_pathfor('/applications/application')),
            new restore_path_element('enrol_apply_submission', $this->get_pathfor('/submissions/submission')),
        ];
    }

    /**
     * The apply instance this element belongs to, or 0 when it is not one.
     *
     * When a restore converts enrolment methods to manual (users included, enrolments set to
     * "never"), core maps every old enrol id onto the course's manual instance, so
     * get_new_parentid('enrol') can return a valid id belonging to another enrolment method.
     * Without this check the plugin would write rows against the manual instance that nothing
     * owns or ever cleans up.
     *
     * @return int Enrol instance id, or 0 when this element did not land on an apply instance.
     */
    protected function get_apply_instanceid(): int {
        global $DB;

        $enrolid = (int) $this->get_new_parentid('enrol');
        if (!$enrolid) {
            // The enrol instance itself was not restored, so there is nothing to attach to.
            return 0;
        }
        if (!$DB->record_exists('enrol', ['id' => $enrolid, 'enrol' => 'apply'])) {
            return 0;
        }

        return $enrolid;
    }

    /**
     * Restore one durable application record.
     *
     * The applicant is mandatory and the decider is not. A row whose applicant cannot be
     * mapped - a cross-site restore where that person has no account here - is dropped rather
     * than written with userid = 0: an ownerless profile snapshot is loose personal data that
     * no subject access request can reach. A decider who cannot be mapped is only zeroed,
     * because the record is still the applicant's and still means what it says without a
     * name on the decision.
     *
     * @param array $data Backup data of the application record.
     * @return void
     */
    public function process_enrol_apply_submission($data) {
        global $DB;

        $data = (object) $data;

        $enrolid = $this->get_apply_instanceid();
        if (!$enrolid) {
            return;
        }

        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            return;
        }

        /* Rebuilt from this restore rather than carried: the record belongs to the course it
           lands in, whatever course it was taken from. */
        $courseid = (int) $DB->get_field('enrol', 'courseid', ['id' => $enrolid], MUST_EXIST);

        /* Best effort. The mapping exists only for enrolments this plugin itself restored,
           so a cancelled application - whose user enrolment was deleted long before the
           backup was taken - legitimately has none, and the reference is simply left empty. */
        $userenrolmentid = (int) $this->get_mappingid('enrol_apply_userenrolment', $data->userenrolmentid);

        /* A repeated restore into the same course must not double the trail. The check keys
           on the course, not on $enrolid: enrol_apply_plugin::restore_instance() always calls
           add_instance(), so no existing record can carry the id this restore just created.
           timecreated identifies one application among a user's several, since more than one
           per course and user is allowed. */
        $exists = $DB->record_exists('enrol_apply_submission', [
            'courseid' => $courseid,
            'userid' => $userid,
            'timecreated' => (int) $data->timecreated,
        ]);
        if ($exists) {
            return;
        }

        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $courseid,
            'userid' => $userid,
            'enrolid' => $enrolid,
            'userenrolmentid' => $userenrolmentid,
            'comment' => $data->comment,
            'userinfodata' => $data->userinfodata,
            'status' => (int) $data->status,
            'outcomemessage' => $data->outcomemessage,
            /* An empty element parses back as NULL, not as the '' it was written from, so the
               ?? here and on the two below is the ordinary path (every decision without a
               note, every undecided application), not an edge case. It also covers an archive
               predating these elements, where the property is absent; see
               test_an_archive_without_the_new_elements_still_restores. */
            'decisionnote' => $data->decisionnote ?? '',
            /* Without the ??, mapped_groups() would get NULL for its string parameter, and the
               TypeError would abort the restore. */
            'decidedgroups' => $this->mapped_groups($data->decidedgroups ?? ''),
            'decidedrole' => (int) $this->get_mappingid('role', $data->decidedrole ?? 0),
            'timecreated' => (int) $data->timecreated,
            'timedecided' => (int) $data->timedecided,
            'decidedby' => (int) $this->get_mappingid('user', $data->decidedby),
        ]);
    }

    /**
     * The decided groups, translated to the ids they have in the restored course.
     *
     * Group ids are course-local, so the archived ones name groups of the course the backup
     * was taken from and mean something else - or nothing - here. Every group of that course
     * is annotated by core, so each one that travelled has a mapping; an id with none did not
     * travel, which is what a groups-excluded backup produces for all of them, and is dropped.
     *
     * Dropping is safe because \enrol_apply\local\submission::chosen_groups() reads a value
     * naming no group as "no choice recorded", which puts the enrolment method's own list
     * back in charge. It must not return an empty array there: its caller passes the result
     * to get_in_or_equal(), which throws on one.
     *
     * @param string $decidedgroups Comma-separated group ids as the archive carries them.
     * @return string Comma-separated group ids in this site, empty when none mapped.
     */
    protected function mapped_groups(string $decidedgroups): string {
        $mapped = [];

        foreach (array_filter(array_map('intval', explode(',', $decidedgroups))) as $old) {
            $new = (int) $this->get_mappingid('group', $old);
            if ($new) {
                $mapped[] = $new;
            }
        }

        return implode(',', $mapped);
    }

    /**
     * Restore the comment submitted with one application.
     *
     * The row is keyed by user_enrolments.id, which core does not map, so this relies on
     * the mapping enrol_apply_plugin::restore_user_enrolment() registers as each
     * enrolment is restored. Core writes the user enrolments into the backup file before
     * the plugin's own data, so that mapping already exists by the time this runs; if it
     * does not, the comment is skipped rather than attached to the wrong person.
     *
     * @param array $data Backup data of the application.
     * @return void
     */
    public function process_enrol_apply_application($data) {
        global $DB;

        $data = (object) $data;

        $userenrolmentid = $this->get_mappingid('enrol_apply_userenrolment', $data->userenrolmentid);
        if (!$userenrolmentid) {
            return;
        }

        // The table is unique on userenrolmentid, so a repeated restore must not insert twice.
        if ($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $userenrolmentid])) {
            return;
        }

        $DB->insert_record('enrol_apply_applicationinfo', (object) [
            'userenrolmentid' => $userenrolmentid,
            'comment' => $data->comment,
        ]);
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

        $enrolid = $this->get_apply_instanceid();
        if (!$enrolid) {
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
