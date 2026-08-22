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
     * tables key off exactly those rows, so they are left behind with nothing pointing at
     * them and nothing that will ever look at them again. Event observers, unlike
     * delete_instance(), are registered by every installed plugin whatever its state.
     *
     * The sweep is expressed as "rows whose parent is gone" rather than "rows belonging to
     * this course", because by the time course_deleted fires the {enrol} rows that named the
     * course no longer exist - there is nothing left to join a courseid to. That also makes
     * it idempotent and correct for orphans of any other origin.
     *
     * enrol_apply_submission is deliberately not swept here: it is not an orphan, it is the
     * durable trail, and \enrol_apply\hook_callbacks::before_course_deleted() has already
     * pseudonymised it while the course context still existed.
     *
     * @param course_deleted $event The dispatched event.
     * @return void
     */
    public static function course_deleted(course_deleted $event): void {
        global $DB;

        $DB->delete_records_select(
            'enrol_apply_applicationinfo',
            'userenrolmentid NOT IN (SELECT id FROM {user_enrolments})'
        );
        $DB->delete_records_select(
            'enrol_apply_groups',
            'enrolid NOT IN (SELECT id FROM {enrol})'
        );
    }
}
