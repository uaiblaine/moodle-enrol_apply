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

use enrol_apply\fake_lock_factory;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/tests/fixtures/fake_lock_factory.php');

/**
 * Tests for the wrong-key throttle.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(keyattempts::class)]
final class keyattempts_test extends \advanced_testcase {
    /**
     * End the simulated request, so no lock outlives the test.
     *
     * @return void
     */
    protected function tearDown(): void {
        keyattempts::release_all();
        parent::tearDown();
    }

    /**
     * Record this many wrong keys.
     *
     * @param int $times How many.
     * @param int $instanceid Enrol instance id.
     * @param int $userid User id.
     * @return void
     */
    private function wrong_keys(int $times, int $instanceid = 7, int $userid = 42): void {
        for ($i = 0; $i < $times; $i++) {
            keyattempts::record_failure($instanceid, $userid);
        }
    }

    /**
     * The field locks at the LIMIT-th wrong key and not one before.
     *
     * Changes that must make it fail: `>` for `>=`, or a count that never grows.
     *
     * @return void
     */
    public function test_the_field_locks_at_the_limit(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1000000);

        $this->wrong_keys(keyattempts::LIMIT - 1);
        $this->assertFalse(keyattempts::locked(7, 42));

        $this->wrong_keys(1);
        $this->assertTrue(keyattempts::locked(7, 42));
    }

    /**
     * The window runs from its first wrong key; once it has passed, the tries come back.
     *
     * Changes that must make it fail: an entry that never expires, or a window restarted by
     * each wrong key.
     *
     * @return void
     */
    public function test_the_window_closes_from_its_first_wrong_key(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(1000000);

        $this->wrong_keys(1);
        $clock->bump(keyattempts::WINDOW - 10);
        $this->wrong_keys(keyattempts::LIMIT - 1);
        $this->assertTrue(keyattempts::locked(7, 42));

        $clock->bump(10);
        $this->assertFalse(keyattempts::locked(7, 42));
    }

    /**
     * One applicant's wrong keys lock neither another applicant nor another instance.
     *
     * @return void
     */
    public function test_counts_are_per_applicant_and_instance(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1000000);

        $this->wrong_keys(keyattempts::LIMIT, 7, 42);

        $this->assertTrue(keyattempts::locked(7, 42));
        $this->assertFalse(keyattempts::locked(7, 43));
        $this->assertFalse(keyattempts::locked(8, 42));
    }

    /**
     * An accepted key clears the count.
     *
     * @return void
     */
    public function test_clear_gives_the_tries_back(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1000000);

        $this->wrong_keys(keyattempts::LIMIT);
        keyattempts::clear(7, 42);

        $this->assertFalse(keyattempts::locked(7, 42));
    }

    /**
     * While another request of the applicant holds the lock, this one reads as locked, whatever
     * the count; once the lock is free again the count decides.
     *
     * Changes that must make it fail: a lock not obtained read as unlocked, or the lock never asked
     * for.
     *
     * @return void
     */
    public function test_a_lock_not_obtained_reads_as_locked(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1000000);
        fake_lock_factory::install([keyattempts::LOCK_TYPE]);

        $this->assertTrue(keyattempts::locked(7, 42), 'no wrong key yet, but no lock either');

        // The control: the next request obtains the lock and reads the count, which is zero.
        keyattempts::release_all();
        fake_lock_factory::$refused = [];
        $this->assertFalse(keyattempts::locked(7, 42));
    }

    /**
     * A request without the lock writes no failure: it could overwrite the count of the request
     * that holds it.
     *
     * Changes that must make it fail: record_failure() writing without the lock.
     *
     * @return void
     */
    public function test_without_the_lock_no_failure_is_written(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1000000);
        fake_lock_factory::install([keyattempts::LOCK_TYPE]);

        $this->wrong_keys(keyattempts::LIMIT);
        keyattempts::release_all();
        fake_lock_factory::$refused = [];

        $this->assertFalse(keyattempts::locked(7, 42));

        // The control: the same failures, written under the lock, lock the field.
        $this->wrong_keys(keyattempts::LIMIT);
        $this->assertTrue(keyattempts::locked(7, 42));
    }

    /**
     * The lock is taken on this instance and applicant, once per request however often the count
     * is read or written, and again by the next request.
     *
     * Changes that must make it fail: the lock never taken, taken on every call, or taken on a
     * resource that ignores the instance or the applicant.
     *
     * @return void
     */
    public function test_the_lock_is_taken_on_the_applicant_once_per_request(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1000000);
        fake_lock_factory::install();

        keyattempts::locked(7, 42);
        $this->assertSame(['7_42'], fake_lock_factory::requests_for(keyattempts::LOCK_TYPE), 'the first read takes it');

        keyattempts::locked(7, 42);
        keyattempts::record_failure(7, 42);
        $this->assertFalse(keyattempts::locked(7, 42));
        $this->assertSame(['7_42'], fake_lock_factory::requests_for(keyattempts::LOCK_TYPE), 'and keeps it');

        keyattempts::locked(8, 42);
        keyattempts::locked(7, 43);
        $this->assertSame(['7_42', '8_42', '7_43'], fake_lock_factory::requests_for(keyattempts::LOCK_TYPE));

        keyattempts::release_all();
        $this->assertSame([], fake_lock_factory::$taken, 'the end of the request releases every lock');
        keyattempts::locked(7, 42);
        $this->assertSame(
            ['7_42', '8_42', '7_43', '7_42'],
            fake_lock_factory::requests_for(keyattempts::LOCK_TYPE),
            'the next request asks again'
        );
    }

    /**
     * Requests that follow one another each obtain the lock, because each releases it at its end,
     * and together they count exactly: the field locks at the LIMIT-th wrong key.
     *
     * The factory refuses a lock already taken, as a lock held by another request would be, so a
     * request that kept its lock past its end would lock out the next one.
     *
     * Changes that must make it fail: release_all() not releasing.
     *
     * @return void
     */
    public function test_requests_in_turn_each_count_under_their_own_lock(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1000000);
        fake_lock_factory::install();

        for ($i = 1; $i <= keyattempts::LIMIT; $i++) {
            $this->assertFalse(keyattempts::locked(7, 42), "request {$i}");
            keyattempts::record_failure(7, 42);
            keyattempts::release_all();
        }

        $this->assertTrue(keyattempts::locked(7, 42));
        $this->assertCount(keyattempts::LIMIT + 1, fake_lock_factory::requests_for(keyattempts::LOCK_TYPE));
    }
}
