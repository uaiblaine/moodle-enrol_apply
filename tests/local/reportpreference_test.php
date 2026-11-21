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
 * Tests for the reader's grid on the course report.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The column preference: its shape, its bounds, and a stored value that is not its shape.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reportpreference::class)]
final class reportpreference_test extends \advanced_testcase {
    /**
     * Keys of a catalogue key's shape pass, once each; anything else is dropped.
     *
     * @return void
     */
    public function test_the_keys_are_cleaned_for_their_shape(): void {
        $keys = ['outcome', 'answer:s_city', 'answer:a_12', 'answer:c_3', 'identity:email'];
        $clean = reportpreference::clean(array_merge($keys, ['outcome', 'answer:x_1', 'Outcome', 7, 'a b']), []);
        $this->assertSame($keys, $clean['columns']);
        $this->assertNull(reportpreference::clean(null, [])['columns']);
        $this->assertSame([], reportpreference::clean([], [])['columns']);
    }

    /**
     * Widths are whole pixels within the bounds, for keys of a key's shape.
     *
     * @return void
     */
    public function test_the_widths_are_bounded(): void {
        $widths = ['wait' => 150.6, 'outcome' => 1, 'note' => 99999, 'bad key' => 100, 'decided' => 'wide'];
        $this->assertSame(
            ['wait' => 151, 'outcome' => reportpreference::MIN_WIDTH, 'note' => reportpreference::MAX_WIDTH],
            reportpreference::clean(null, $widths)['widths']
        );
    }

    /**
     * A stored value that is not the shape reads as no choice; writing nothing removes the row.
     *
     * Changes that must make it fail: a broken value read as a choice of no columns; an empty
     * choice left stored.
     *
     * @return void
     */
    public function test_a_broken_value_reads_as_no_choice_and_nothing_leaves_no_row(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        foreach (['not json', '"text"', '{"columns": "outcome"}', '[1, 2]'] as $value) {
            set_user_preference(reportpreference::NAME, $value);
            $this->assertSame(['columns' => null, 'widths' => []], reportpreference::read(), $value);
        }

        reportpreference::write(['outcome'], ['wait' => 200]);
        $this->assertSame(['columns' => ['outcome'], 'widths' => ['wait' => 200]], reportpreference::read((int) $user->id));
        reportpreference::write(null, []);
        $this->assertNull(get_user_preferences(reportpreference::NAME, null, $user->id));
    }

    /**
     * The cap holds.
     *
     * @return void
     */
    public function test_the_number_of_keys_is_capped(): void {
        $keys = array_map(static fn(int $i): string => 'answer:a_' . $i, range(1, reportpreference::MAX_KEYS + 10));
        $this->assertCount(reportpreference::MAX_KEYS, reportpreference::clean($keys, [])['columns']);
    }
}
