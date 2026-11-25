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

namespace enrol_apply;

use core\event\course_deleted;
use core\event\user_deleted;
use core\event\user_enrolment_updated;
use core_customfield\api;
use core_customfield\category_controller;
use core_customfield\event\field_deleted;
use enrol_apply\customfield\application_handler;
use enrol_apply\local\conditions;
use enrol_apply\local\approvalwrite;
use enrol_apply\local\reportpersistents;
use enrol_apply\local\submission;

/**
 * Event observers cleaning up after core has deleted rows this plugin hangs data off.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observers {
    /**
     * Drop the plugin rows core orphaned while deleting a course.
     *
     * This is the safety net for one case, and only one: the plugin installed but not
     * enabled. enrol_course_delete() (lib/enrollib.php) resolves its plugin objects from
     * enrol_get_plugins(true), so a disabled plugin's delete_instance() is never called - and
     * core then deletes the {enrol} and {user_enrolments} rows anyway. The plugin's own two
     * tables and its application fields key off exactly those rows, so they are left behind
     * with nothing pointing at them and nothing that will ever look at them again. Event
     * observers, unlike
     * delete_instance(), are registered by every installed plugin whatever its state.
     *
     * The sweep is expressed as "rows whose parent is gone" rather than "rows belonging to
     * this course", because by the time course_deleted fires the {enrol} rows that named the
     * course no longer exist - there is nothing left to join a courseid to. That also makes
     * it idempotent and correct for orphans of any other origin.
     *
     * enrol_apply_submission is deliberately not swept here: it is not an orphan, it is the
     * durable trail, and \enrol_apply\hook_callbacks::before_course_deleted() has already
     * pseudonymised it while the course context still existed. Its frozen answers are swept
     * when their record is gone, which no path of this plugin produces - each deletes the answers
     * first - but a record deleted by hand or by another tool would otherwise leave every value
     * the applicant typed with nothing left to say whose it was.
     *
     * @param course_deleted $event The dispatched event.
     * @return void
     */
    public static function course_deleted(course_deleted $event): void {
        global $DB;

        self::sweep_orphaned_applicationinfo();
        $DB->delete_records_select(
            'enrol_apply_groups',
            'NOT EXISTS (SELECT 1 FROM {enrol} e WHERE e.id = {enrol_apply_groups}.enrolid)'
        );
        // The two tables exist only once the upgrade has run, and a course can be deleted before.
        if (enrol_get_plugin('apply')->options_schema_ready()) {
            $DB->delete_records_select(
                'enrol_apply_offer',
                'NOT EXISTS (SELECT 1 FROM {enrol} e WHERE e.id = {enrol_apply_offer}.enrolid)'
            );
            $DB->delete_records_select(
                'enrol_apply_options',
                'NOT EXISTS (SELECT 1 FROM {enrol} e WHERE e.id = {enrol_apply_options}.enrolid)'
            );
        }

        /* The application fields key off the {enrol} row through the itemid. Categories go through
           core's API, which also deletes their fields and the files of their descriptions. */
        $params = ['component' => 'enrol_apply', 'area' => application_handler::AREA];
        $orphans = $DB->get_fieldset_select(
            'customfield_category',
            'id',
            'component = :component AND area = :area
             AND NOT EXISTS (SELECT 1 FROM {enrol} e WHERE e.id = {customfield_category}.itemid)',
            $params
        );
        foreach ($orphans as $categoryid) {
            api::delete_category(category_controller::create((int) $categoryid));
        }
        $DB->delete_records_select(
            'customfield_shared',
            'component = :component AND area = :area
             AND NOT EXISTS (SELECT 1 FROM {enrol} e WHERE e.id = {customfield_shared}.itemid)',
            $params
        );

        $DB->delete_records_select(
            'enrol_apply_answer',
            'NOT EXISTS (SELECT 1 FROM {enrol_apply_submission} s WHERE s.id = {enrol_apply_answer}.submissionid)'
        );

        // The display conditions; the ones between fields of a shared category outlive every field deletion above.
        $DB->delete_records_select(
            conditions::TABLE,
            'NOT EXISTS (SELECT 1 FROM {enrol} e WHERE e.id = {' . conditions::TABLE . '}.enrolid)'
        );

        /* The course report's persistents, with every reader's stored filter values: core deletes none with the
           context, and delete_instance() reaches only an enabled method's, built by the plugin's own page. */
        reportpersistents::delete_orphans();
    }

    /**
     * Forget the applications of a user deleted while this plugin is disabled.
     *
     * delete_user() calls enrol_user_delete(), which asks only the enabled enrol plugins to
     * unenrol the user (so the unenrol_user() of this plugin, which deletes the comment, is
     * skipped) and then deletes every {user_enrolments} row regardless. The comment of the
     * application would stay behind, keyed to an enrolment that no longer exists. Observers run
     * whatever the plugin's state.
     *
     * The durable trail in enrol_apply_submission is pseudonymised here too, because
     * delete_user() calls no privacy provider: the applicant's records lose their userid, texts
     * and frozen answers, and the records they decided lose their name
     * ({@see submission::pseudonymise_user()}). The dates, the status and the course stay, and
     * the rest follows the retention period. Idempotent, and a pair of probes when the user has
     * no record.
     *
     * The user's stored filter values of the course report go too: core deletes none, and the
     * privacy finder reads the user context, which is gone by now.
     *
     * @param user_deleted $event The dispatched event.
     * @return void
     */
    public static function user_deleted(user_deleted $event): void {
        // First: once the user context is gone nothing else reaches them, whatever throws below.
        reportpersistents::delete_user_filters((int) $event->objectid);
        self::sweep_orphaned_applicationinfo();
        submission::pseudonymise_user((int) $event->objectid);
    }

    /**
     * Delete the pending application comments whose user enrolment no longer exists.
     *
     * The one sweep behind both deletion events, so they cannot drift apart.
     *
     * A correlated NOT EXISTS rather than NOT IN over the whole parent table: this runs on every
     * course and user deletion, apply instance or not, and it reads the plugin's own small table
     * while probing the parent by primary key for each row. The outer table is named in full
     * because delete_records_select() writes a DELETE with no alias. Both columns are NOT NULL,
     * so it matches the same rows NOT IN would.
     *
     * @return void
     */
    protected static function sweep_orphaned_applicationinfo(): void {
        global $DB;

        $DB->delete_records_select(
            'enrol_apply_applicationinfo',
            'NOT EXISTS (SELECT 1 FROM {user_enrolments} ue WHERE ue.id = {enrol_apply_applicationinfo}.userenrolmentid)'
        );
    }

    /**
     * Drop the display conditions that name a deleted custom field.
     *
     * Core deletes a field from its own management UI and web services, and from
     * api::delete_category() for every field of a category, with no callback into the handler.
     * Any component's field fires the event; field ids are site-wide, so the delete can only
     * match this plugin's rows that named that field, as the controlled or the controlling one.
     *
     * @param field_deleted $event The dispatched event.
     * @return void
     */
    public static function field_deleted(field_deleted $event): void {
        conditions::delete_for_field((int) $event->objectid);
    }

    /**
     * Complete an approval the before_user_enrolment_updated hook authorised, now that core has written the row.
     *
     * enrol_plugin::update_user_enrol() dispatches that hook before it writes the row, with no
     * transaction, and triggers this event right after update_record(). So the hook only
     * expects the approval ({@see approvalwrite::expect()}) and the role, the groups, the decision
     * and the notification are given here, when the enrolment really is active: a write that
     * throws, or a listener after this plugin's that does, never reaches this event and leaves
     * nothing behind. complete_approval() also logs the approval here, after the write, unless an
     * outer caller owes it ({@see \enrol_apply\local\decisionlog}): activate() logs its own, a key admission none.
     *
     * Three guards. The enrol name in other filters out every other method's updates, which are
     * the vast majority. Nothing expected for the enrolment means the update was not an approval
     * the hook authorised. And the row is re-read: an entry left behind by an attempt whose write
     * failed, then met by a later update of the same enrolment that is not an activation, must not
     * complete an approval the row does not show.
     *
     * @param user_enrolment_updated $event The dispatched event.
     * @return void
     */
    public static function user_enrolment_updated(user_enrolment_updated $event): void {
        global $DB;

        if (($event->other['enrol'] ?? null) !== 'apply') {
            return;
        }

        $userenrolmentid = (int) $event->objectid;
        if (!approvalwrite::take($userenrolmentid)) {
            return;
        }

        $userenrolment = $DB->get_record('user_enrolments', ['id' => $userenrolmentid]);
        if (!$userenrolment || (int) $userenrolment->status !== ENROL_USER_ACTIVE) {
            return;
        }

        $instance = $DB->get_record('enrol', ['id' => $userenrolment->enrolid, 'enrol' => 'apply']);
        if (!$instance) {
            return;
        }

        enrol_get_plugin('apply')->complete_approval($instance, (int) $userenrolment->userid, $userenrolmentid);
    }
}
