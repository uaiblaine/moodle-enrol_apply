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

        /* Foreign keys on the two user columns only. Moodle's DDL generators create no
           constraint for them, but core's privacy table coverage test detects personal data
           only through a column named userid or a single-field foreign key to user.id, so
           without the decidedby key that role is invisible to it. The course, enrol and user
           enrolment references get plain indexes, because the row deliberately outlives all
           three.

           No UNIQUE (courseid, userid): course deletion pseudonymises by zeroing userid, so
           two applicants of a deleted course share the pair, and cancelling and re-applying,
           restoring into a course that already holds the trail, and a second apply instance
           in the course all produce legitimate duplicates. The lock in
           enrol_apply_plugin::submit_application() enforces only one live application per
           enrol instance and user. */
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('decidedby', XMLDB_KEY_FOREIGN, ['decidedby'], 'user', ['id']);

        $table->add_index('courseuser', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'userid']);
        $table->add_index('enrolid', XMLDB_INDEX_NOTUNIQUE, ['enrolid']);
        $table->add_index('userenrolmentid', XMLDB_INDEX_NOTUNIQUE, ['userenrolmentid']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        /* A site installed at version 2026082200 already has this table, declared in
           install.xml without the two foreign keys and with different column defaults.
           Nothing wrote to it at that version, so an empty existing table is recreated to
           match install.xml; a table holding rows is left alone. */
        if ($dbman->table_exists($table) && $DB->count_records('enrol_apply_submission') === 0) {
            $dbman->drop_table($table);
        }
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        /* Backfill one row per application still awaiting a decision. A decided application
           left no trace before this table existed, so there is nothing to reconstruct for it.
           The comment is carried across where the applicationinfo row survives,
           ue.timecreated is when the applicant applied, and waiting-list rows come across as
           waiting.

           The predicate is the queue's (\enrol_apply\local\queue::awaiting_decision_where()),
           timeend clause included, not merely "not active": under an expiredaction of suspend,
           process_expirations() re-suspends an approved enrolment whose period ran out, and
           backfilling that as an undecided application would be a false audit record. */
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
            /* Per-row check because the savepoint is reached only after the whole loop: a
               failure part way through leaves the rows already written committed, and
               re-running the upgrade re-enters this step. A duplicate would break the approval
               queue's one-to-one join on userenrolmentid. */
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

    if ($oldversion < 2026082400) {
        /* The groups a decider chose, joined on approval instead of the instance's own list.
           Stored rather than passed along because complete_approval() runs twice for a queue
           approval; see enrol_apply_plugin::add_instance_groups(). */
        $table = new xmldb_table('enrol_apply_submission');
        $field = new xmldb_field(
            'decidedgroups',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,
            null,
            null,
            'outcomemessage'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026082400, 'enrol', 'apply');
    }

    if ($oldversion < 2026082500) {
        /* The role a decider chose, stored for the same reason as the groups; see
           enrol_apply_plugin::assign_decided_role().

           0 means "the decider chose nothing", which is what every existing row gets, so
           approving an application already queued at upgrade time assigns the instance's
           default role as before. */
        $table = new xmldb_table('enrol_apply_submission');
        $field = new xmldb_field(
            'decidedrole',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'decidedgroups'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026082500, 'enrol', 'apply');
    }

    if ($oldversion < 2026083002) {
        /* Repair waiting-list rows that carry an expiry. wait_enrolment() now clears the date
           when it defers, but rows deferred earlier keep theirs, and such a row is stranded:
           core's suspend arms of process_expirations() filter on status = active, the queue's
           timeend clause hides it, and applicants() no longer counts it.

           The predicate is ENROL_APPLY_USER_WAIT and nothing wider: a suspended row with a
           past timeend is the normal state of an approval that expired under
           expiredaction = suspend, and zeroing it would make it reappear in the queue as an
           undecided application. "<> 0" rather than "< time()", because a future expiry is the
           same defect not yet due, and under expiredaction = unenrol it would unenrol the
           applicant. Scoped to this plugin's instances because status 2 means "waiting list"
           only for enrol_apply.

           A direct write rather than update_user_enrol(), which would dispatch
           before_user_enrolment_updated into every installed plugin mid-upgrade; core reads a
           row's timeend for access only together with status = active. Idempotent: the WHERE
           excludes the rows this step has already fixed. */
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        $DB->set_field_select(
            'user_enrolments',
            'timeend',
            0,
            "timeend <> 0 AND status = :waiting
               AND enrolid IN (SELECT id FROM {enrol} WHERE enrol = :enrol)",
            ['waiting' => ENROL_APPLY_USER_WAIT, 'enrol' => 'apply']
        );

        upgrade_plugin_savepoint(true, 2026083002, 'enrol', 'apply');
    }

    if ($oldversion < 2026083003) {
        /* Places: customint3 is how many people may apply, customint4 how many may be
           approved at once. Both are opt-in at 0, so seeding anything else would switch the
           feature on for every existing site.

           The global get_config(), not the plugin object's: enrol_plugin::get_config()
           returns its $default (null) for an absent setting, never false. The 2026081000 step
           above uses the plugin-object form, so its seeds never ran; it is left as it is
           because it has already run everywhere. */
        if (get_config('enrol_apply', 'places') === false) {
            set_config('places', 0, 'enrol_apply');
        }

        /* Existing rows carry NULL, which capacity::places() already reads as 0. Written as 0
           anyway so the column holds one spelling, matching get_instance_defaults().
           Idempotent: the WHERE excludes the rows this step has already written. */
        $DB->set_field_select(
            'enrol',
            'customint4',
            0,
            "enrol = :enrol AND customint4 IS NULL",
            ['enrol' => 'apply']
        );

        upgrade_plugin_savepoint(true, 2026083003, 'enrol', 'apply');
    }

    if ($oldversion < 2026083104) {
        /* Required by each step that uses it, as in the 2026082102 step above: nothing else in
           an upgrade request loads it, and a fresh install never executes this file, so a
           missing require would surface only as a fatal part way through a real upgrade. */
        require_once($CFG->dirroot . '/enrol/apply/db/upgradelib.php');

        /* Clear a Custom label that is really a leftover notification recipient marker; see
           enrol_apply_clear_legacy_comment_labels(). A cleared label falls back to the shipped
           wording, and the recipients now live in customtext3, so nothing still read is lost. */
        enrol_apply_clear_legacy_comment_labels();

        upgrade_plugin_savepoint(true, 2026083104, 'enrol', 'apply');
    }

    if ($oldversion < 2026083107) {
        /* The decider's own note, which is not the outcome message: that one is mailed to the
           applicant, while this one records why the decision was taken and never leaves the
           site. Nullable, because no default could say why an existing row was decided. */
        $table = new xmldb_table('enrol_apply_submission');
        $field = new xmldb_field('decisionnote', XMLDB_TYPE_TEXT, null, null, null, null, null, 'decidedrole');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026083107, 'enrol', 'apply');
    }

    if ($oldversion < 2026090302) {
        /* The queue's search needs the unaccent extension on PostgreSQL to match "goncalves"
           against "Gonçalves"; MariaDB and MySQL fold accents through the collation. Not a
           schema change, so install.xml's VERSION is unchanged. The helper is idempotent and
           swallows a failure; see \enrol_apply\local\search::ensure_unaccent(). */
        \enrol_apply\local\search::ensure_unaccent();

        upgrade_plugin_savepoint(true, 2026090302, 'enrol', 'apply');
    }

    return true;
}
