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
 * How many wrong group keys one applicant has typed on one instance lately.
 *
 * Without a limit the form would answer "is this a key?" as often as it is asked, for keys that
 * skip a human decision. After LIMIT wrong keys within WINDOW seconds the key field locks; on the
 * key-or-review mode the application can still be sent without a key, while a locked key-only
 * form cannot be sent at all until the window has passed.
 *
 * The count is read and written under a core lock on the applicant and the instance, held from
 * the first read of a request to its end ({@see self::hold()}). Requests of one session are
 * serialised by the session lock, but several sessions of one account are not; without this lock
 * each of them would read a count under the limit, get a real answer, and the last write would
 * win, so far more than LIMIT guesses would fit in the window. A request that cannot obtain the
 * lock in time reads as locked.
 *
 * Kept in the application cache rather than a table: the count is worth nothing once the
 * window has passed, and a purged cache only gives the applicant their tries back early.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class keyattempts {
    /** @var int Wrong keys allowed within the window before the field locks. */
    public const LIMIT = 5;

    /** @var int Length of the window in seconds, counted from the first wrong key in it. */
    public const WINDOW = 900;

    /** @var int Seconds a request waits for another request of the same applicant to finish. */
    public const LOCK_TIMEOUT = 5;

    /** @var string Lock type of the per-applicant lock. */
    public const LOCK_TYPE = 'enrol_apply_keyattempts';

    /**
     * @var array The locks this request asked for, by resource: the lock held until the end of the
     *     request, or false when it was not obtained, so the whole request reads as locked.
     */
    private static $held = [];

    /** @var bool Whether release_all() is registered to run at the end of the request. */
    private static $releaseregistered = false;

    /**
     * Count one wrong key.
     *
     * @param int $instanceid Enrol instance id.
     * @param int $userid Applicant user id.
     * @return void
     */
    public static function record_failure(int $instanceid, int $userid): void {
        /* Without the lock this request was shown the locked form and learned nothing, and a write
           here could overwrite the count of the request that does hold it. */
        if (!self::hold($instanceid, $userid)) {
            return;
        }
        $now = \core\di::get(\core\clock::class)->time();
        $entry = self::current($instanceid, $userid, $now);
        $entry->count++;
        self::cache()->set(self::key($instanceid, $userid), (array) $entry);
    }

    /**
     * Whether the applicant has used up their wrong keys for now.
     *
     * The first call of a request takes the applicant's lock for the rest of it, so the count read
     * here stays exact until this request has counted its own try.
     *
     * @param int $instanceid Enrol instance id.
     * @param int $userid Applicant user id.
     * @return bool True when the key field must not be offered, including when the lock could not
     *     be obtained in time.
     */
    public static function locked(int $instanceid, int $userid): bool {
        if (!self::hold($instanceid, $userid)) {
            return true;
        }
        $now = \core\di::get(\core\clock::class)->time();
        return self::current($instanceid, $userid, $now)->count >= self::LIMIT;
    }

    /**
     * Forget the wrong keys, after a key was accepted.
     *
     * @param int $instanceid Enrol instance id.
     * @param int $userid Applicant user id.
     * @return void
     */
    public static function clear(int $instanceid, int $userid): void {
        self::cache()->delete(self::key($instanceid, $userid));
    }

    /**
     * Release every lock this request holds: the end of the request.
     *
     * Registered as a shutdown function by the first hold() of a request. Tests call it between
     * the requests they simulate, and in tearDown(), so no lock outlives its test.
     *
     * @return void
     */
    public static function release_all(): void {
        $held = self::$held;
        self::$held = [];
        foreach ($held as $lock) {
            if ($lock) {
                $lock->release();
            }
        }
    }

    /**
     * Take the applicant's lock on this instance, once per request, and keep it to the end.
     *
     * The registry, not the lock factory, is what makes a second call of the same request reuse
     * the lock (the web service builds the form twice on Continue): core's factories differ on
     * re-entry within one process. lock_config::get_lock_factory() builds a new factory on every
     * call; postgres_lock_factory then grants a second lock on a token the process already holds,
     * while a file or record lock would wait for itself until the timeout. A lock not obtained is
     * remembered as well, so one request never reads both answers.
     *
     * @param int $instanceid Enrol instance id.
     * @param int $userid Applicant user id.
     * @return bool True when this request holds the lock.
     */
    protected static function hold(int $instanceid, int $userid): bool {
        $resource = self::key($instanceid, $userid);
        if (!array_key_exists($resource, self::$held)) {
            if (!self::$releaseregistered) {
                \core_shutdown_manager::register_function([self::class, 'release_all']);
                self::$releaseregistered = true;
            }
            $factory = \core\lock\lock_config::get_lock_factory(self::LOCK_TYPE);
            self::$held[$resource] = $factory->get_lock($resource, self::LOCK_TIMEOUT);
        }

        return self::$held[$resource] !== false;
    }

    /**
     * The applicant's entry for the window still open at $now, or a fresh one.
     *
     * The cache definition carries a TTL as well, but that only bounds storage: the window is
     * judged here, against the time of its first wrong key, so a cache store that ignores TTLs
     * cannot lock anybody for ever.
     *
     * @param int $instanceid Enrol instance id.
     * @param int $userid Applicant user id.
     * @param int $now Current time.
     * @return \stdClass Object with int 'count' and int 'since'.
     */
    protected static function current(int $instanceid, int $userid, int $now): \stdClass {
        $stored = self::cache()->get(self::key($instanceid, $userid));
        if (is_array($stored) && isset($stored['count'], $stored['since']) && $now - (int) $stored['since'] < self::WINDOW) {
            return (object) ['count' => (int) $stored['count'], 'since' => (int) $stored['since']];
        }
        return (object) ['count' => 0, 'since' => $now];
    }

    /**
     * The cache key and lock resource: instance and user, joined without a colon (MUC keys must
     * not carry one).
     *
     * @param int $instanceid Enrol instance id.
     * @param int $userid Applicant user id.
     * @return string The key.
     */
    protected static function key(int $instanceid, int $userid): string {
        return $instanceid . '_' . $userid;
    }

    /**
     * The cache.
     *
     * @return \cache_application The keyattempts cache.
     */
    protected static function cache(): \cache_application {
        return \cache::make('enrol_apply', 'keyattempts');
    }
}
