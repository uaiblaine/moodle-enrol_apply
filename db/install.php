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
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Install the unaccent extension the queue's search uses, where the database account may.
 *
 * A failure is swallowed by {@see \enrol_apply\local\search::ensure_unaccent()}: such a site keeps
 * an accent-sensitive search, as the search field's help string describes.
 *
 * @return bool Always true; the plugin installs either way.
 */
function xmldb_enrol_apply_install() {
    \enrol_apply\local\search::ensure_unaccent();

    return true;
}
