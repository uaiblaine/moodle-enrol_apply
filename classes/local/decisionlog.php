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
 * approval runs it first from the before_user_enrolment_updated hook, inside update_user_enrol()
 * and BEFORE core writes the row, then again from activate() once the row is active; a key
 * admission runs both passes too, and logs application_admitted_by_key instead. A caller that will
 * reach complete_approval() itself after the write wraps the write in {@see self::defer()}, and
 * complete_approval() fires only when {@see self::is_owed()} is false. So:
 *  - the queue, the review page and the bulk operations fire from activate()'s own pass, after the
 *    enrolment is active; the nested hook pass is owed and stays silent, so a write that fails
 *    logs nothing;
 *  - core's "Edit enrolment" reaches complete_approval() only through the hook, unwrapped, and
 *    the hook runs BEFORE enrol_plugin::update_user_enrol() writes the row, with no hook after it
 *    (5.1 to 5.3). That pass therefore parks the built event under the user enrolment id
 *    ({@see self::park()}) and \enrol_apply\observers::user_enrolment_updated() fires it once
 *    core has written the row and triggered its own event, and only if the row is active: the
 *    record is made when the effect has happened, never ahead of it;
 *  - admit() wraps the whole of activate(), so neither pass fires.
 *
 * The marker cannot live on the plugin object: enrol_get_plugin() returns a new object on every
 * call, so the hook's plugin is never activate()'s. It is static state of this class rather than
 * of the plugin class because validate fatals on a two-modifier member in lib.php. It cannot leak
 * into another request, since PHP builds static state afresh for each one; within a request (a
 * cron run executing many tasks, a PHPUnit process) defer() undoes its own count in a finally, so
 * a failed write leaves nothing behind for the next approval. A parked event has no finally: a
 * throw between park() and the observer (core's write, or a listener before ours) leaves the entry
 * behind, and two things neutralise it. The observer re-reads the row and fires only when it is
 * active, so an entry whose write never happened logs nothing; and the entry dies with the
 * request, or is replaced by the next park() for the same enrolment, or dropped by activate().
 * The event's user is the decider at park time: it is built in the hook pass, in the deciding
 * request, so the parked decider wins over whoever the observer would read from $USER. Nested defers count, rather than
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

    /** @var array Approval events waiting for core's write, keyed by user enrolment id. */
    private static $parked = [];

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
     * Keep an approval event for the moment core has written the row.
     *
     * Replaces any entry for the same enrolment, so a stale one from an earlier failed attempt
     * in this request never outlives the attempt that supersedes it.
     *
     * @param int $userenrolmentid User enrolment being approved.
     * @param \enrol_apply\event\application_approved $event The built event, not yet triggered.
     * @return void
     */
    public static function park(int $userenrolmentid, \enrol_apply\event\application_approved $event): void {
        self::$parked[$userenrolmentid] = $event;
    }

    /**
     * Take the parked approval event of a user enrolment, removing it.
     *
     * @param int $userenrolmentid User enrolment.
     * @return \enrol_apply\event\application_approved|null The event, null when none is parked.
     */
    public static function take(int $userenrolmentid): ?\enrol_apply\event\application_approved {
        $event = self::$parked[$userenrolmentid] ?? null;
        unset(self::$parked[$userenrolmentid]);

        return $event;
    }

    /**
     * Drop the parked approval event of a user enrolment, if any.
     *
     * @param int $userenrolmentid User enrolment.
     * @return void
     */
    public static function clear(int $userenrolmentid): void {
        unset(self::$parked[$userenrolmentid]);
    }

    /**
     * Whether an approval event is parked for a user enrolment.
     *
     * @param int $userenrolmentid User enrolment.
     * @return bool True while an entry waits for core's write.
     */
    public static function is_parked(int $userenrolmentid): bool {
        return isset(self::$parked[$userenrolmentid]);
    }

    /**
     * Forget every marker and parked event; for tests only, which share one process.
     *
     * @return void
     */
    public static function reset(): void {
        self::$owed = [];
        self::$parked = [];
    }
}
