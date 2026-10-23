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
 * Unlike enrol_self, a wrong key here costs the applicant nothing - they can still apply for
 * review - so without a limit the form answers "is this a key?" for free, for keys that skip
 * a human decision. After LIMIT wrong keys within WINDOW seconds the key field locks; the
 * application itself stays open.
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

    /**
     * Count one wrong key.
     *
     * @param int $instanceid Enrol instance id.
     * @param int $userid Applicant user id.
     * @return void
     */
    public static function record_failure(int $instanceid, int $userid): void {
        $now = \core\di::get(\core\clock::class)->time();
        $entry = self::current($instanceid, $userid, $now);
        $entry->count++;
        self::cache()->set(self::key($instanceid, $userid), (array) $entry);
    }

    /**
     * Whether the applicant has used up their wrong keys for now.
     *
     * @param int $instanceid Enrol instance id.
     * @param int $userid Applicant user id.
     * @return bool True when the key field must not be offered.
     */
    public static function locked(int $instanceid, int $userid): bool {
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
     * The cache key: instance and user, joined without a colon (MUC keys must not carry one).
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
