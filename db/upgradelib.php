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
 * Migration helpers used by the plugin's upgrade steps.
 *
 * These live apart from db/upgrade.php so they can be tested directly. Calling an upgrade
 * step from a test is not possible: upgrade_plugin_savepoint() refuses when the savepoint
 * version is not greater than the installed one, and in a test environment the plugin is
 * always already installed at the current version.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Move the two all-or-nothing profile switches onto the per-instance field set.
 *
 * customint1 meant "ask for the standard profile fields" and customint2 "ask for every
 * custom field", so an instance that had them on keeps asking for the same things: the
 * standard default set, and every custom field that exists at migration time. An instance
 * that had neither on gets an empty envelope and asks for nothing, which is what it did
 * before. An instance that already carries an envelope is never touched, so the step is
 * safe to run twice.
 *
 * @return int Number of instances whose envelope was written.
 */
function enrol_apply_migrate_field_switches(): int {
    global $DB;

    $customids = $DB->get_fieldset_select('user_info_field', 'id', '');
    $instances = $DB->get_records('enrol', ['enrol' => 'apply'], '', 'id, customint1, customint2, customtext4');

    $migrated = 0;
    foreach ($instances as $instance) {
        if ($instance->customtext4 !== null && trim((string) $instance->customtext4) !== '') {
            // Already migrated, or configured since. Never overwrite a real choice.
            continue;
        }

        $keys = [];
        if (!empty($instance->customint1)) {
            $keys = \enrol_apply\local\fields::DEFAULT_SET;
        }
        if (!empty($instance->customint2)) {
            foreach ($customids as $customid) {
                $keys[] = \enrol_apply\local\fields::custom_key((int) $customid);
            }
        }

        $DB->set_field(
            'enrol',
            'customtext4',
            \enrol_apply\local\fieldset::from_keys($keys)->to_json(),
            ['id' => $instance->id]
        );
        $migrated++;
    }

    return $migrated;
}

/**
 * Write the site field pool explicitly, wide enough to keep every instance collecting.
 *
 * A setting's declared default is only applied when an administrator opens the settings
 * page, and admin_setting_configmulticheckbox::write_setting() reports success while
 * writing nothing when its choice list is empty, so the pool is written here instead.
 *
 * It is written WIDER than the default set when the site needs it to be. customint2 meant
 * "ask for every custom profile field", and the picked set is intersected with this pool on
 * every read - so seeding the pool with the standard fields alone would migrate each
 * instance's choice faithfully and then immediately filter the custom half of it away.
 * Every instance that was collecting custom fields would silently stop, which is the exact
 * failure this whole two-level design has to avoid. A fresh install is unaffected: upgrade
 * steps do not run on one, so a new site starts with the conservative declared default.
 *
 * @return bool True when the pool was written, false when it was already set.
 */
function enrol_apply_seed_field_pool(): bool {
    global $DB;

    if (get_config('enrol_apply', 'allowedfields') !== false) {
        return false;
    }

    $keys = \enrol_apply\local\fields::DEFAULT_SET;

    // Only widen for custom fields if some instance was actually collecting them.
    if ($DB->record_exists_select('enrol', "enrol = 'apply' AND customint2 = 1")) {
        foreach ($DB->get_fieldset_select('user_info_field', 'id', '') as $customid) {
            $keys[] = \enrol_apply\local\fields::custom_key((int) $customid);
        }
    }

    set_config('allowedfields', implode(',', $keys), 'enrol_apply');

    return true;
}

/**
 * Blank every Custom label that is really a leftover notification recipient list.
 *
 * Upstream made customtext2 the notification recipient list in commit 3d27870 (2016-06-13) while
 * the custom label was still read from the same column. Three writers put a value there - the
 * 2016060803 upgrade step, add_instance()'s defaults and the instance edit form - and all three
 * could write the literal marker below. Alexander Bias's b88a8d2 (2022-02-04), titled "for fresh
 * installations only", moved all three to customtext3 and RETRO-EDITED the 2016 step, so a site
 * already past that savepoint never re-ran it and kept the value. Such a site shows the marker as
 * the heading of the applicant's comment box.
 *
 * Only the exact marker is cleared. The same column could also hold a comma-separated list of
 * user ids, and that shape is deliberately left alone: it cannot be told apart from a label
 * somebody typed, and this one can. A reader-side guard in \enrol_apply\local\commentlabel
 * covers what a restore can still bring back, since customtext2 is the one custom field
 * restore_instance() does not sanitise.
 *
 * Idempotent by construction: the WHERE is the negation of the step's own effect.
 *
 * @return int How many instances were cleaned.
 */
function enrol_apply_clear_legacy_comment_labels(): int {
    global $DB;

    $marker = \enrol_apply\local\commentlabel::LEGACY_MARKER;

    $affected = $DB->count_records_select(
        'enrol',
        'enrol = :enrol AND customtext2 = :marker',
        ['enrol' => 'apply', 'marker' => $marker]
    );

    if ($affected > 0) {
        $DB->set_field_select(
            'enrol',
            'customtext2',
            '',
            'enrol = :enrol AND customtext2 = :marker',
            ['enrol' => 'apply', 'marker' => $marker]
        );
    }

    return $affected;
}
