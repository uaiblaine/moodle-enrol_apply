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
 * By default {@see \phpunit_coverage_info::get_includelists()} measures only 'classes',
 * 'tests/generator' and the top-level externallib/lib/locallib/renderer/rsslib files. Anything
 * else is absent from the clover rather than counted as uncovered, so leaving it out makes the
 * number look better, not worse. For this plugin the default would omit the page scripts,
 * edit_form.php (the whole instance configuration form), db/upgrade.php and backup/moodle2/.
 *
 * The lists below are merged into the defaults, so classes/, lib.php and renderer.php are not
 * repeated here.
 *
 * Deliberately left out: the declaration-only files of db/ (access.php, events.php, hooks.php,
 * messages.php and tasks.php), which define arrays and nothing else and would add lines no test
 * can exercise. Files holding executable logic are listed whether or not a test reaches them.
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
        'db/install.php',
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
