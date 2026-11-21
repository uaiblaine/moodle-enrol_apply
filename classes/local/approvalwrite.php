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
 * The approvals core is about to write, between the hook that authorises them and the event after the write.
 *
 * enrol_plugin::update_user_enrol() dispatches before_user_enrolment_updated before it writes the
 * row, with no transaction, and triggers \core\event\user_enrolment_updated after it (5.1 to 5.3).
 * The hook ({@see \enrol_apply\hook_callbacks::before_user_enrolment_updated()}) therefore only
 * authorises and records the user enrolment id here with {@see self::expect()};
 * \enrol_apply\observers::user_enrolment_updated() takes it with {@see self::take()} and runs
 * complete_approval() once core has written the row. A write that throws (core's, or another
 * listener's after this plugin's) never reaches the observer, so it leaves no role, no group and no
 * decision behind. The entry it leaves is dropped by the next hook dispatch for the same
 * enrolment ({@see self::forget()}), and the observer re-reads the row's status before acting on
 * one. The same holds for an entry no observer consumed because events were redirected, as a
 * PHPUnit event sink does.
 *
 * The second half is the plugin's own activation, {@see self::own()}: activate() wraps its
 * update_user_enrol() in it, because both of its callers decided before calling (a decider's
 * confirm_enrolment() asked can_manage_application(), a key admission resolved the key). The hook
 * judges nobody while it runs; this is what lets a key admission through for an applicant who holds
 * enrol/apply:manage in the course, whom can_manage_application() refuses as a self-decision. It is
 * keyed by the user enrolment, so an update of any other enrolment inside it is judged as usual.
 *
 * Static state, as in {@see decisionlog}: enrol_get_plugin() returns a new object on every call, and
 * PHP builds static state afresh for each request. own() counts and undoes its count in a finally,
 * so a failed write leaves nothing behind for the next approval in a cron run or a PHPUnit process.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class approvalwrite {
    /** @var array Approvals authorised by the hook and waiting for core's write, keyed by user enrolment id. */
    private static $expected = [];

    /** @var array How many activate() calls are writing each user enrolment, keyed by its id. */
    private static $own = [];

    /**
     * Run the plugin's own activation of a user enrolment, which its caller has already authorised.
     *
     * @param int $userenrolmentid User enrolment being activated.
     * @param callable $run The code to run; its return value is discarded.
     * @return void
     */
    public static function own(int $userenrolmentid, callable $run): void {
        self::$own[$userenrolmentid] = (self::$own[$userenrolmentid] ?? 0) + 1;
        try {
            $run();
        } finally {
            self::$own[$userenrolmentid]--;
            if (self::$own[$userenrolmentid] <= 0) {
                unset(self::$own[$userenrolmentid]);
            }
        }
    }

    /**
     * Whether the plugin's own activate() is writing this user enrolment.
     *
     * @param int $userenrolmentid User enrolment.
     * @return bool True while an own() for it is running.
     */
    public static function is_own(int $userenrolmentid): bool {
        return isset(self::$own[$userenrolmentid]);
    }

    /**
     * Note that an authorised approval of this user enrolment waits for core's write.
     *
     * @param int $userenrolmentid User enrolment being approved.
     * @return void
     */
    public static function expect(int $userenrolmentid): void {
        self::$expected[$userenrolmentid] = true;
    }

    /**
     * Take the expected approval of a user enrolment, removing it.
     *
     * @param int $userenrolmentid User enrolment.
     * @return bool True when one was expected.
     */
    public static function take(int $userenrolmentid): bool {
        $expected = isset(self::$expected[$userenrolmentid]);
        unset(self::$expected[$userenrolmentid]);

        return $expected;
    }

    /**
     * Drop the expected approval of a user enrolment, if any.
     *
     * @param int $userenrolmentid User enrolment.
     * @return void
     */
    public static function forget(int $userenrolmentid): void {
        unset(self::$expected[$userenrolmentid]);
    }

    /**
     * Whether an approval of this user enrolment waits for core's write.
     *
     * @param int $userenrolmentid User enrolment.
     * @return bool True while an entry waits.
     */
    public static function is_expected(int $userenrolmentid): bool {
        return isset(self::$expected[$userenrolmentid]);
    }

    /**
     * Forget every entry; for tests only, which share one process.
     *
     * @return void
     */
    public static function reset(): void {
        self::$expected = [];
        self::$own = [];
    }
}
