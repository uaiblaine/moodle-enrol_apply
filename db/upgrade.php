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
 * Upgrade steps of the enrolment upon approval plugin.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo.com (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Johannes Burk <johannes.burk@sudile.com>
 */

/**
 * Upgrade the enrol_apply plugin database.
 *
 * @param int $oldversion Version the site is upgrading from.
 * @return bool Always true.
 */
function xmldb_enrol_apply_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2016012801) {
        // Define table enrol_apply_applicationinfo to be created.
        $table = new xmldb_table('enrol_apply_applicationinfo');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userenrolmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('comment', XMLDB_TYPE_TEXT, null, null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userenrolment', XMLDB_KEY_FOREIGN_UNIQUE, ['userenrolmentid'], 'user_enrolments', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2016012801, 'enrol', 'apply');
    }

    if ($oldversion < 2016042202) {
        // Invert the settings for showing standard and extra user profile fields.
        $enrolapply = enrol_get_plugin('apply');
        $enrolapply->set_config('show_standard_user_profile', $enrolapply->get_config('show_standard_user_profile') == 0);
        $enrolapply->set_config('show_extra_user_profile', $enrolapply->get_config('show_extra_user_profile') == 0);

        $instances = $DB->get_records('enrol', ['enrol' => 'apply']);
        foreach ($instances as $instance) {
            $instance->customint1 = !$instance->customint1;
            $instance->customint2 = !$instance->customint2;
            $DB->update_record('enrol', $instance, true);
        }

        upgrade_plugin_savepoint(true, 2016042202, 'enrol', 'apply');
    }

    if ($oldversion < 2016060803) {
        // Convert the old notification settings.
        $enrolapply = enrol_get_plugin('apply');

        $enrolapply->set_config('notifycoursebased', $enrolapply->get_config('sendmailtoteacher'));
        $enrolapply->set_config('sendmailtoteacher', null);

        $enrolapply->set_config('notifyglobal', $enrolapply->get_config('sendmailtomanager') ? '$@ALL@$' : '');
        $enrolapply->set_config('sendmailtomanager', null);

        $instances = $DB->get_records('enrol', ['enrol' => 'apply']);
        foreach ($instances as $instance) {
            $instance->customtext3 = $instance->customint3 ? '$@ALL@$' : '';
            $instance->customint3 = null;
            $instance->customint4 = null;
            $DB->update_record('enrol', $instance, true);
        }

        upgrade_plugin_savepoint(true, 2016060803, 'enrol', 'apply');
    }

    if ($oldversion < 2017032400) {
        $DB->set_field('enrol', 'customint3', 0, ['enrol' => 'apply']);

        upgrade_plugin_savepoint(true, 2017032400, 'enrol', 'apply');
    }

    if ($oldversion < 2018112603) {
        $DB->set_field('enrol', 'customint6', 1, ['enrol' => 'apply']);

        upgrade_plugin_savepoint(true, 2018112603, 'enrol', 'apply');
    }

    if ($oldversion < 2021120501) {
        // Define table enrol_apply_groups to be created.
        $table = new xmldb_table('enrol_apply_groups');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('enrolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'enrolid');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('enrol', XMLDB_KEY_FOREIGN, ['enrolid'], 'enrol', ['id']);
        $table->add_key('group', XMLDB_KEY_FOREIGN, ['groupid'], 'groups', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2021120501, 'enrol', 'apply');
    }

    if ($oldversion < 2021120607) {
        $DB->set_field('enrol', 'expirythreshold', DAYSECS, ['enrol' => 'apply']);

        upgrade_plugin_savepoint(true, 2021120607, 'enrol', 'apply');
    }

    if ($oldversion < 2026081000) {
        $enrolapply = enrol_get_plugin('apply');

        /* Two instance defaults are now configurable site wide. They were read from
           settings that never existed, so every new instance silently got null. */
        if ($enrolapply->get_config('maxenrolled') === false) {
            $enrolapply->set_config('maxenrolled', 0);
        }
        if ($enrolapply->get_config('opt_commentaryzone') === false) {
            $enrolapply->set_config('opt_commentaryzone', 0);
        }

        // Drop group mappings whose group has been deleted in the meantime.
        $DB->delete_records_select(
            'enrol_apply_groups',
            'groupid NOT IN (SELECT id FROM {groups})'
        );

        // Drop application info rows whose user enrolment has already gone away.
        $DB->delete_records_select(
            'enrol_apply_applicationinfo',
            'userenrolmentid NOT IN (SELECT id FROM {user_enrolments})'
        );

        upgrade_plugin_savepoint(true, 2026081000, 'enrol', 'apply');
    }

    if ($oldversion < 2026082102) {
        require_once($CFG->dirroot . '/enrol/apply/db/upgradelib.php');

        enrol_apply_seed_field_pool();
        enrol_apply_migrate_field_switches();

        /* The two switches and the queue's profile-field column are retired. The column
           printed a profile field value with no visibility check of any kind, so dropping it
           closes an existing disclosure as well as a dead setting. */
        $enrolapply = enrol_get_plugin('apply');
        $enrolapply->set_config('show_standard_user_profile', null);
        $enrolapply->set_config('show_extra_user_profile', null);
        $enrolapply->set_config('profileoption', null);

        upgrade_plugin_savepoint(true, 2026082102, 'enrol', 'apply');
    }

    if ($oldversion < 2026082300) {
        // ENROL_APPLY_USER_WAIT lives in lib.php, which nothing has necessarily loaded yet.
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        // Define table enrol_apply_submission to be created.
        $table = new xmldb_table('enrol_apply_submission');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid');
        $table->add_field('enrolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'userid');
        $table->add_field('userenrolmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'enrolid');
        $table->add_field('comment', XMLDB_TYPE_TEXT, null, null, null, null, null, 'userenrolmentid');
        $table->add_field('userinfodata', XMLDB_TYPE_TEXT, null, null, null, null, null, 'comment');
        $table->add_field('status', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'userinfodata');
        $table->add_field('outcomemessage', XMLDB_TYPE_TEXT, null, null, null, null, null, 'status');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'outcomemessage');
        $table->add_field('timedecided', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        $table->add_field('decidedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timedecided');

        /* Foreign keys on the two user columns and nowhere else. They create no database
           constraint - Moodle's generators never emit one - but they are what core's privacy
           table coverage test reads, and it sees only a single-field foreign key to user.id
           or a column literally named userid. Without the decidedby key that role is
           invisible to it. The course, enrol and user enrolment references get plain indexes
           instead, because the row deliberately outlives all three and a foreign key would
           document an integrity claim this design breaks on purpose.

           There is deliberately no UNIQUE (courseid, userid). Course deletion pseudonymises
           by zeroing userid, so a deleted course with two applicants yields two rows sharing
           courseid and userid = 0 - measured against the database, not reasoned: the second
           insert raises dml_write_exception. Cancelling and re-applying, and restoring a
           course into one that already holds the trail, are both legitimate duplicates too.
           One live application per course and user is enforced by the lock in
           enrol_apply_plugin::submit_application() instead. */
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('decidedby', XMLDB_KEY_FOREIGN, ['decidedby'], 'user', ['id']);

        $table->add_index('courseuser', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'userid']);
        $table->add_index('enrolid', XMLDB_INDEX_NOTUNIQUE, ['enrolid']);
        $table->add_index('userenrolmentid', XMLDB_INDEX_NOTUNIQUE, ['userenrolmentid']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        /* A site can already carry this table in an earlier shape: it was declared in
           install.xml one commit before this step existed, so a development checkout
           installed from scratch at version 2026082200 has it without the two foreign keys
           and with different column defaults. Nothing ever wrote to it at that version - the
           code that inserts arrives with this same step - so an existing table is necessarily
           empty, and recreating it costs nothing where leaving it would make a fresh install
           and an upgraded site disagree about the schema permanently. The emptiness is
           checked rather than assumed, so that a table holding anything is left alone. */
        if ($dbman->table_exists($table) && $DB->count_records('enrol_apply_submission') === 0) {
            $dbman->drop_table($table);
        }
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        /* Backfill one row per application still awaiting a decision. Only those: a decided
           application left no trace anywhere before this table existed, so there is nothing
           to reconstruct, and inventing rows for enrolments that merely look approved would
           put fiction into an audit record. The comment is carried across where the
           applicationinfo row is still there, and ue.timecreated is when the applicant
           applied. Waiting-list rows come across as waiting, already decided once.

           The predicate is the queue's own definition of "awaiting a decision", timeend
           clause included, and not merely "not active". They differ on exactly the rows that
           would become fiction: process_expirations() re-suspends an ACTIVE enrolment whose
           period has run out when expiredaction is "suspend", so somebody approved long ago
           and since expired reads as status != active. Backfilling that as an application
           nobody ever decided is a false audit record, which is the one thing this table must
           never hold. Same predicate as manage_table.php, deliberately. */
        $sql = "SELECT ue.id AS userenrolmentid, ue.userid, ue.timecreated, ue.status,
                       e.id AS enrolid, e.courseid, ai.comment
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = :enrol
             LEFT JOIN {enrol_apply_applicationinfo} ai ON ai.userenrolmentid = ue.id
                 WHERE ue.status <> :active AND (ue.timeend = 0 OR ue.timeend > :now)";
        $pending = $DB->get_recordset_sql($sql, [
            'enrol' => 'apply',
            'active' => ENROL_USER_ACTIVE,
            'now' => time(),
        ]);
        foreach ($pending as $row) {
            /* Idempotent, because the savepoint below is only reached once the whole loop
               has run: a failure part way through leaves the rows already written committed
               and the stored version unchanged, so re-running the upgrade - the standard
               recovery - re-enters this step. Without the check the second pass duplicates
               every row it had already written, and the approval queue's join on
               userenrolmentid stops being one to one. */
            if ($DB->record_exists('enrol_apply_submission', ['userenrolmentid' => (int) $row->userenrolmentid])) {
                continue;
            }
            $waiting = (int) $row->status === ENROL_APPLY_USER_WAIT;
            $DB->insert_record('enrol_apply_submission', (object) [
                'courseid' => (int) $row->courseid,
                'userid' => (int) $row->userid,
                'enrolid' => (int) $row->enrolid,
                'userenrolmentid' => (int) $row->userenrolmentid,
                'comment' => (string) ($row->comment ?? ''),
                'userinfodata' => '',
                'status' => $waiting
                    ? \enrol_apply\local\submission::STATUS_WAITING
                    : \enrol_apply\local\submission::STATUS_PENDING,
                'outcomemessage' => '',
                'timecreated' => (int) $row->timecreated,
                'timedecided' => 0,
                'decidedby' => 0,
            ]);
        }
        $pending->close();

        upgrade_plugin_savepoint(true, 2026082300, 'enrol', 'apply');
    }

    return true;
}
