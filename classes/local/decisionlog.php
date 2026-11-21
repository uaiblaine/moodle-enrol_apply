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

namespace enrol_apply\local;

/**
 * Which approvals owe their application_approved event to an outer caller, in this request.
 *
 * complete_approval() fires the event, and it is reached more than once per approval: a queue
 * approval runs it first from \enrol_apply\observers::user_enrolment_updated(), which core
 * triggers inside update_user_enrol() once the row is written, then again from activate(); a key
 * admission runs both passes too, and logs application_admitted_by_key instead. A caller that will
 * reach complete_approval() itself after the write wraps the write in {@see self::defer()}, and
 * complete_approval() fires only when {@see self::is_owed()} is false. So:
 *  - the queue, the review page and the bulk operations fire from activate()'s own pass; the
 *    observer's pass nested in its write is owed and stays silent;
 *  - core's "Edit enrolment" reaches complete_approval() only through the observer, unwrapped, so
 *    that pass fires, after the write ({@see approvalwrite});
 *  - admit() wraps the whole of activate(), so neither pass fires.
 *
 * The marker cannot live on the plugin object: enrol_get_plugin() returns a new object on every
 * call, so the observer's plugin is never activate()'s. It is static state of this class rather than
 * of the plugin class because validate fatals on a two-modifier member in lib.php. It cannot leak
 * into another request, since PHP builds static state afresh for each one; within a request (a
 * cron run executing many tasks, a PHPUnit process) defer() undoes its own count in a finally, so
 * a failed write leaves nothing behind for the next approval. Nested defers count, rather than
 * flag: admit() wraps activate(), which wraps the write again, and the inner one ending must not
 * make the outer one's approval loggable.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class decisionlog {
    /** @var array How many callers currently owe the approval event, keyed by user enrolment id. */
    private static $owed = [];

    /**
     * Run code during which complete_approval() must not log this approval, because the caller will.
     *
     * @param int $userenrolmentid User enrolment being approved.
     * @param callable $run The code to run; its return value is discarded.
     * @return void
     */
    public static function defer(int $userenrolmentid, callable $run): void {
        self::$owed[$userenrolmentid] = (self::$owed[$userenrolmentid] ?? 0) + 1;
        try {
            $run();
        } finally {
            self::$owed[$userenrolmentid]--;
            if (self::$owed[$userenrolmentid] <= 0) {
                unset(self::$owed[$userenrolmentid]);
            }
        }
    }

    /**
     * Whether an outer caller will log this approval itself.
     *
     * @param int $userenrolmentid User enrolment being approved.
     * @return bool True while a defer() for it is running.
     */
    public static function is_owed(int $userenrolmentid): bool {
        return isset(self::$owed[$userenrolmentid]);
    }

    /**
     * Forget every marker; for tests only, which share one process.
     *
     * @return void
     */
    public static function reset(): void {
        self::$owed = [];
    }
}
