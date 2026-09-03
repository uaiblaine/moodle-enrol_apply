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
 * Install-time work for the applications queue.
 *
 * No MOODLE_INTERNAL guard: the file's only top-level construct is a function definition, so the
 * sniff moodle.Files.MoodleInternal.MoodleInternalNotNeeded fires on one, and a single warning
 * fails the build under --max-warnings 0.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Give the queue's search the best matching the database will allow.
 *
 * DDL, so it runs here and in the upgrade step rather than on any request path. Failure is not an
 * error: a least-privilege database account cannot create an extension, and such a site keeps an
 * accent-sensitive search, which the search field's help string describes.
 *
 * @return bool Always true; the plugin installs either way.
 */
function xmldb_enrol_apply_install() {
    \enrol_apply\local\search::ensure_unaccent();

    return true;
}
