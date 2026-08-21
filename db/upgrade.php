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

    return true;
}
