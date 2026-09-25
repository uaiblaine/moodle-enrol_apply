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
 * Tests for the outcome of submitting an application.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the outcome of submitting an application.
 *
 * No database: the class is pure logic, so it uses basic_testcase.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(application_result::class)]
final class application_result_test extends \basic_testcase {
    /**
     * A created application is the only outcome that reports having written anything.
     *
     * @return void
     */
    public function test_a_created_application_reports_that_it_was_created(): void {
        $result = application_result::created();

        $this->assertTrue($result->was_created());
        $this->assertFalse($result->is_refusal());
        $this->assertSame('', $result->reason());
    }

    /**
     * An application that was already there is neither created nor refused.
     *
     * A duplicate submission is the commonest way to reach this outcome, and it is not an error:
     * the applicant does have an application, so the caller routes it to the acknowledgement.
     *
     * @return void
     */
    public function test_an_existing_application_is_neither_created_nor_refused(): void {
        $result = application_result::already_applied();

        $this->assertFalse($result->was_created());
        $this->assertFalse($result->is_refusal());
        $this->assertSame('', $result->reason());
    }

    /**
     * A refusal carries the reason through to whoever has to render it.
     *
     * @return void
     */
    public function test_a_refusal_carries_its_reason(): void {
        $result = application_result::refused('No more applications are being accepted.');

        $this->assertTrue($result->is_refusal());
        $this->assertFalse($result->was_created());
        $this->assertSame('No more applications are being accepted.', $result->reason());
    }

    /**
     * A refusal with nothing to say is a coding error, not a silent generic message.
     *
     * See application_result::refused() for why there is no fallback.
     *
     * @return void
     */
    public function test_a_refusal_without_a_reason_is_refused(): void {
        $this->expectException(\coding_exception::class);

        application_result::refused('');
    }

    /**
     * Whitespace is not a reason either.
     *
     * Pins the trim() in the guard: without it a whitespace-only reason passes and renders as a
     * blank error box.
     *
     * @return void
     */
    public function test_a_whitespace_reason_is_refused_too(): void {
        $this->expectException(\coding_exception::class);

        application_result::refused("  \n ");
    }
}
