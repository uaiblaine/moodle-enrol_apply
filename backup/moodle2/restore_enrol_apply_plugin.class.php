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

use core_customfield\api;
use core_customfield\category_controller;
use core_customfield\field_controller;
use core_customfield\shared;
use enrol_apply\customfield\application_handler;
use enrol_apply\local\answers;
use enrol_apply\local\conditions;
use enrol_apply\local\submission;

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
            new restore_path_element('enrol_apply_appcategory', $this->get_pathfor('/applicationcategories/applicationcategory')),
            new restore_path_element(
                'enrol_apply_appfield',
                $this->get_pathfor('/applicationcategories/applicationcategory/applicationfields/applicationfield')
            ),
            new restore_path_element('enrol_apply_sharedcategory', $this->get_pathfor('/sharedcategories/sharedcategory')),
            new restore_path_element('enrol_apply_applygroup', $this->get_pathfor('/applygroups/applygroup')),
            new restore_path_element('enrol_apply_application', $this->get_pathfor('/applications/application')),
            new restore_path_element('enrol_apply_submission', $this->get_pathfor('/submissions/submission')),
            new restore_path_element('enrol_apply_answer', $this->get_pathfor('/submissions/submission/answers/answer')),
            new restore_path_element('enrol_apply_fieldcondition', $this->get_pathfor('/fieldconditions/fieldcondition')),
        ];
    }

    /**
     * A description format the archive carries, kept only when format_text() renders it.
     *
     * Core's custom field persistent takes any integer, and format_text() throws on FORMAT_WIKI
     * and on unknown formats, so a crafted archive would otherwise leave an application form that
     * fails for every applicant.
     *
     * @param mixed $format Format from the archive.
     * @return int FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN or FORMAT_MARKDOWN.
     */
    protected function description_format($format): int {
        // The FORMAT_ constants are strings ('1' for HTML), so they are compared as integers.
        $known = array_map('intval', [FORMAT_MOODLE, FORMAT_HTML, FORMAT_PLAIN, FORMAT_MARKDOWN]);
        $format = (int) $format;
        return in_array($format, $known, true) ? $format : (int) FORMAT_MOODLE;
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
     * The record's mapping is registered on EVERY path, to 0 when nothing was written. Its
     * answers find their record through get_new_parentid(), which returns the latest id mapped
     * under this name: left unset for a skipped record, it would still name the record restored
     * before it, and the skipped applicant's answers would be attached to somebody else.
     *
     * @param array $data Backup data of the application record.
     * @return void
     */
    public function process_enrol_apply_submission($data) {
        $data = (object) $data;

        $this->set_mapping('enrol_apply_submission', $data->id, $this->restore_submission($data));
    }

    /**
     * Write one durable application record, if it belongs here.
     *
     * An archive written before enrol_apply_answer existed carries the snapshot as a JSON
     * envelope on this element. It is read with the parser the envelope always had and written
     * through the one writer, so the archive gets exactly the tolerance a stored envelope got:
     * every part scalar-checked, malformed entries dropped, nothing interpreted beyond display.
     *
     * @param stdClass $data Backup data of the application record.
     * @return int Id of the new record, or 0 when none was written.
     */
    protected function restore_submission(stdClass $data): int {
        global $DB;

        $enrolid = $this->get_apply_instanceid();
        if (!$enrolid) {
            return 0;
        }

        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            return 0;
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
            // Its answers are already here with it, and must not be written a second time.
            return 0;
        }

        $id = (int) $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $courseid,
            'userid' => $userid,
            'enrolid' => $enrolid,
            'userenrolmentid' => $userenrolmentid,
            'comment' => $data->comment,
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

        if (isset($data->userinfodata) && is_string($data->userinfodata)) {
            submission::write_answers($id, submission::parse_envelope($data->userinfodata));
        }

        return $id;
    }

    /**
     * Restore one frozen answer under the record restored just before it.
     *
     * The archive is untrusted, so the row goes through the same check every other route into the
     * table does (submission::answer_record()). An empty element parses back as null rather than
     * the empty string it was written from, so a missing label or value is read as empty; a key
     * the record already holds is skipped rather than allowed to abort the restore on the unique
     * index.
     *
     * @param array $data Backup data of the answer.
     * @return void
     */
    public function process_enrol_apply_answer($data) {
        global $DB;

        $data = (object) $data;

        $submissionid = (int) $this->get_new_parentid('enrol_apply_submission');
        if (!$submissionid) {
            return;
        }

        $record = submission::answer_record(
            $submissionid,
            $data->fieldkey ?? '',
            $data->label ?? '',
            $data->value ?? '',
            (int) ($data->sortorder ?? 0)
        );
        if ($record === null) {
            return;
        }
        if ($DB->record_exists(submission::ANSWER_TABLE, ['submissionid' => $submissionid, 'fieldkey' => $record->fieldkey])) {
            return;
        }

        $DB->insert_record(submission::ANSWER_TABLE, $record);
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
     * Restore one category of the application form's custom fields.
     *
     * Created through core's own category controller and API, so the sort order and the events
     * are core's. The instance is always new (restore_instance() ends in add_instance()), so a
     * restore into an existing course cannot meet a category of this instance already there.
     *
     * The path element is named like the mapping it registers (enrol_apply_appcategory):
     * core tracks the latest parent id of a nested element by the mapping's item name, which is
     * how the field below finds its category. Stage 4 remaps through the same names.
     *
     * @param array $data Backup data of the category.
     * @return void
     */
    public function process_enrol_apply_appcategory($data) {
        $data = (object) $data;

        $enrolid = $this->get_apply_instanceid();
        if (!$enrolid) {
            return;
        }

        $name = trim(clean_param((string) ($data->name ?? ''), PARAM_TEXT));
        if ($name === '') {
            return;
        }

        $handler = application_handler::create($enrolid);
        $category = category_controller::create(0, (object) [
            'name' => $name,
            'description' => $data->description ?? null,
            'descriptionformat' => $this->description_format($data->descriptionformat ?? null),
        ], $handler);
        api::save_category($category);

        $this->set_mapping('enrol_apply_appcategory', $data->id, $category->get('id'));
    }

    /**
     * Restore one field of the application form.
     *
     * The archive is untrusted, so the field is re-validated here as the field form would
     * have: the type must be one the handler offers and be installed, the shortname is cleaned
     * to what core accepts and must be free, and configdata must be a JSON object.
     *
     * @param array $data Backup data of the field.
     * @return void
     */
    public function process_enrol_apply_appfield($data) {
        global $DB;

        $data = (object) $data;

        $enrolid = $this->get_apply_instanceid();
        if (!$enrolid) {
            return;
        }

        $categoryid = (int) $this->get_new_parentid('enrol_apply_appcategory');
        $record = $categoryid ? $DB->get_record('customfield_category', ['id' => $categoryid]) : false;
        if (!$record || (int) $record->itemid !== $enrolid || $record->component !== 'enrol_apply') {
            // The category was skipped, or belongs to somebody else's instance.
            return;
        }

        $type = (string) ($data->type ?? '');
        if (
            !in_array($type, application_handler::ALLOWED_TYPES, true)
            || !class_exists("\\customfield_{$type}\\field_controller")
        ) {
            $this->step->log('Application field of type ' . s($type) . ' not restored', backup::LOG_WARNING);
            return;
        }

        $shortname = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($data->shortname ?? '')));
        $name = trim(clean_param((string) ($data->name ?? ''), PARAM_TEXT));
        $handler = application_handler::create($enrolid);
        if ($shortname === '' || $name === '' || !api::is_shortname_unique($handler, $shortname, 0)) {
            $this->step->log('Application field ' . s($shortname) . ' not restored', backup::LOG_WARNING);
            return;
        }

        $configdata = json_decode((string) ($data->configdata ?? ''), true);
        $category = category_controller::create($categoryid, null, $handler);
        $field = field_controller::create(0, (object) ['type' => $type], $category);
        api::save_field_configuration($field, (object) [
            'shortname' => $shortname,
            'name' => $name,
            'description' => $data->description ?? null,
            'descriptionformat' => $this->description_format($data->descriptionformat ?? null),
            'configdata' => is_array($configdata) ? json_encode($configdata) : '{}',
        ]);

        $this->set_mapping('enrol_apply_appfield', $data->id, $field->get('id'));
    }

    /**
     * Switch a site-wide shared category on for the restored instance.
     *
     * Never creates one: shared categories belong to the site. The category is found by id and
     * name together, else by name when exactly one carries it, and otherwise the switch is
     * not restored.
     *
     * @param array $data Backup data of the switch.
     * @return void
     */
    public function process_enrol_apply_sharedcategory($data) {
        global $DB;

        $data = (object) $data;

        $enrolid = $this->get_apply_instanceid();
        if (!$enrolid) {
            return;
        }

        $name = (string) ($data->name ?? '');
        $shared = ['component' => 'core_customfield', 'area' => 'shared', 'itemid' => 0];
        $categoryid = 0;
        if ($DB->record_exists('customfield_category', $shared + ['id' => (int) $data->categoryid, 'name' => $name])) {
            $categoryid = (int) $data->categoryid;
        } else {
            $matches = $DB->get_records('customfield_category', $shared + ['name' => $name], '', 'id');
            if (count($matches) === 1) {
                $categoryid = (int) reset($matches)->id;
            }
        }
        if (!$categoryid) {
            $this->step->log('Shared category ' . s($name) . ' not found, not enabled', backup::LOG_WARNING);
            return;
        }

        $switch = ['categoryid' => $categoryid, 'component' => 'enrol_apply',
            'area' => application_handler::AREA, 'itemid' => $enrolid];
        if (!$DB->record_exists('customfield_shared', $switch)) {
            (new shared(0, (object) $switch))->create();
        }
    }

    /**
     * Restore one display condition of the application form.
     *
     * Both field ids are remapped through the fields this restore created. A field with no
     * mapping keeps its id only on a restore into the same site, where it may name a field of a
     * shared category, which a restore never creates; on another site the same id names an
     * unrelated field. The archive is untrusted, so the condition must then pass the same check as
     * one saved on condition.php, against the questions the restored instance asks: both fields
     * asked, the controller earlier and of a type that can control, the positions existing. That
     * check is also what limits an unmapped id to a field of a shared category switched on for the
     * restored instance: the instance's own fields are all new, so all mapped.
     *
     * @param array $data Backup data of the condition.
     * @return void
     */
    public function process_enrol_apply_fieldcondition($data) {
        global $DB;

        $data = (object) $data;

        $enrolid = $this->get_apply_instanceid();
        if (!$enrolid) {
            return;
        }

        $fieldid = $this->restored_field_id((int) ($data->fieldid ?? 0));
        $controllerid = $this->restored_field_id((int) ($data->dependsonfieldid ?? 0));
        $operator = (string) ($data->operator ?? '');
        $positions = conditions::parse_positions((string) ($data->value ?? ''));
        $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        if (
            !$fieldid || !$controllerid
            || conditions::problem(answers::fields($instance), $fieldid, $controllerid, $operator, $positions) !== ''
        ) {
            $this->step->log('Application field condition not restored', backup::LOG_WARNING);
            return;
        }

        conditions::save($enrolid, $fieldid, $controllerid, $operator, $positions);
    }

    /**
     * The id on this site of an application field named in the archive, or 0.
     *
     * @param int $oldid Field id in the archive.
     * @return int
     */
    protected function restored_field_id(int $oldid): int {
        $newid = (int) $this->get_mappingid('enrol_apply_appfield', $oldid);
        if ($newid) {
            return $newid;
        }

        return $this->task->is_samesite() ? $oldid : 0;
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
