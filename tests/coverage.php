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
 * Which of this plugin's files PHPUnit measures when generating a coverage report.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The plugin files that are measurable, beyond the ones Moodle measures by default.
 *
 * Moodle decides what is measurable, not the coverage flag, and its default list is shaped for
 * activity modules: coverage_info::get_includelists() merges 'classes', 'tests/generator' and the
 * top-level externallib/lib/locallib/renderer/rsslib files. Everything else is not counted low, it
 * is ABSENT from the clover entirely - so unmeasured code makes a plugin look better rather than
 * worse, and the instinct that a low number is the pessimistic reading is backwards.
 *
 * For an enrol plugin that default measures classes/, lib.php and renderer.php, and leaves out
 * 1933 lines of top-level code plus db/upgrade.php - measured on this plugin. That is not glue:
 * edit_form.php is the whole instance configuration form, and backup/moodle2/ is the pair of
 * classes tests/backup_test.php exists for. A number over
 * the default denominator would be flattering by construction.
 *
 * The lists below ADD to the defaults rather than replacing them - get_includelists() array_merges
 * them - so classes/, lib.php and renderer.php are not repeated here.
 *
 * What is deliberately left out is the declaration-only half of db/: access.php, events.php,
 * hooks.php, messages.php and tasks.php define arrays and nothing else, so they would add lines
 * that are neither exercised nor exercisable and would move the number without meaning. Files
 * holding executable logic are in, whether or not a test reaches them today: a file at 0% is an
 * honest statement that nothing tests it.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_apply_coverage extends phpunit_coverage_info {
    /** @var array Plugin folders whose files are all measured. */
    protected $includelistfolders = [
        'backup',
    ];

    /** @var array Individual plugin files measured, on top of the defaults. */
    protected $includelistfiles = [
        'applied.php',
        'apply.php',
        'db/upgrade.php',
        'db/upgradelib.php',
        'edit.php',
        'edit_form.php',
        'manage.php',
        'notification.php',
        'profile.php',
        'report.php',
        'settings.php',
        'unenrolself.php',
    ];
}

return new enrol_apply_coverage();
