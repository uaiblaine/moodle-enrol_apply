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

/**
 * A lock factory that records what it is asked for and can refuse one lock type.
 *
 * Installed through $CFG->lock_factory, which lock_config::get_lock_factory_class() reads before
 * choosing a database factory, so every lock of the test goes through it. A granted lock stays
 * taken until released and a second request for it is refused, as another request holding it
 * would make it: one PHP process cannot otherwise stand for two requests, because the PostgreSQL
 * factory grants a token the process already holds.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_lock_factory implements \core\lock\lock_factory {
    /** @var array Lock types whose every request is refused, as if another request held them. */
    public static $refused = [];

    /** @var array Every request, in order, as "type/resource". */
    public static $requests = [];

    /** @var array Locks granted and not yet released, keyed "type/resource". */
    public static $taken = [];

    /** @var string The lock type this factory serves. */
    protected $type;

    /**
     * Use this factory for every lock of the test, starting from nothing asked and nothing taken.
     *
     * @param array $refused Lock types to refuse.
     * @return void
     */
    public static function install(array $refused = []): void {
        global $CFG;

        $CFG->lock_factory = '\\' . self::class;
        self::$refused = $refused;
        self::$requests = [];
        self::$taken = [];
    }

    /**
     * The requests made for one lock type, as resources.
     *
     * @param string $type The lock type.
     * @return array Resources, in order.
     */
    public static function requests_for(string $type): array {
        $prefix = $type . '/';
        $resources = [];
        foreach (self::$requests as $request) {
            if (str_starts_with($request, $prefix)) {
                $resources[] = substr($request, strlen($prefix));
            }
        }

        return $resources;
    }

    /**
     * A factory for one lock type.
     *
     * @param string $type The lock type.
     */
    public function __construct($type) {
        $this->type = $type;
    }

    /**
     * Whether the factory honours a timeout.
     *
     * @return bool Always true.
     */
    public function supports_timeout() {
        return true;
    }

    /**
     * Whether locks are released when the process ends.
     *
     * @return bool Always true.
     */
    public function supports_auto_release() {
        return true;
    }

    /**
     * Whether the factory can be used.
     *
     * @return bool Always true.
     */
    public function is_available() {
        return true;
    }

    /**
     * Grant the lock unless its type is refused or it is already taken.
     *
     * @param string $resource The resource.
     * @param int $timeout Ignored: the answer is immediate.
     * @param int $maxlifetime Ignored.
     * @return \core\lock\lock|false The lock, or false.
     */
    public function get_lock($resource, $timeout, $maxlifetime = 86400) {
        $key = $this->type . '/' . $resource;
        self::$requests[] = $key;
        if (in_array($this->type, self::$refused, true) || isset(self::$taken[$key])) {
            return false;
        }
        self::$taken[$key] = true;

        return new \core\lock\lock($key, $this);
    }

    /**
     * Release a lock this factory granted.
     *
     * @param \core\lock\lock $lock The lock.
     * @return bool Always true.
     */
    public function release_lock(\core\lock\lock $lock) {
        unset(self::$taken[$lock->get_key()]);

        return true;
    }
}
