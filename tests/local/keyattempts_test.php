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

use PHPUnit\Framework\Attributes\CoversClass;

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
}
