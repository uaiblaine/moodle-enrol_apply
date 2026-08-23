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
 * Tests for reading the durable application record's frozen snapshot.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for reading the durable application record's frozen snapshot.
 *
 * read_snapshot() is the only thing standing between a restored archive and every reader of the
 * snapshot, so what it does with an envelope this site did not write is its whole job.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(submission::class)]
final class submission_test extends \basic_testcase {
    /**
     * Build an envelope around the given field entries.
     *
     * @param array $fields Field entries.
     * @return string JSON envelope.
     */
    protected function envelope(array $fields): string {
        return (string) json_encode([
            'version' => submission::SNAPSHOT_VERSION,
            'fields' => $fields,
        ]);
    }

    /**
     * A field whose value is not scalar is dropped, and does not raise a warning.
     *
     * A restore writes userinfodata verbatim out of an archive this site did not produce, so an
     * array or a nested object in there is reachable however careful the form is. Casting one to
     * string emits "Array to string conversion" - a PHP warning, and Moodle's PHPUnit runs with
     * failOnWarning, so before the guard this input did not merely render badly, it failed the
     * run. It also rendered the literal word "Array" as though the applicant had typed it.
     *
     * @return void
     */
    public function test_a_non_scalar_value_is_dropped(): void {
        $entries = submission::read_snapshot($this->envelope([
            ['key' => 's_city', 'label' => 'City', 'value' => ['a', 'b']],
            ['key' => 's_firstname', 'label' => 'First name', 'value' => 'Terry'],
        ]));

        /* The control: the well-formed entry beside it survives. Without it this would pass
           against a reader that had given up on the whole envelope, and against one that had
           stopped returning anything at all. */
        $this->assertCount(1, $entries);
        $this->assertSame('s_firstname', $entries[0]['key']);
        $this->assertSame('Terry', $entries[0]['value']);
    }

    /**
     * A field whose key or label is not scalar is dropped whole, not repaired.
     *
     * Repairing it to an empty string would be worse than it looks: the snapshot formatter masks
     * by matching each entry's key against the list of keys the reader may see, so an entry with
     * an unusable key cannot be masked correctly. It is withheld instead.
     *
     * @return void
     */
    public function test_a_non_scalar_key_or_label_drops_the_whole_entry(): void {
        $entries = submission::read_snapshot($this->envelope([
            ['key' => ['s_city'], 'label' => 'City', 'value' => 'Campinas'],
            ['key' => 's_country', 'label' => ['Country'], 'value' => 'Brazil'],
            ['key' => 's_firstname', 'label' => 'First name', 'value' => 'Terry'],
        ]));

        // The control again: the sound entry is still read.
        $this->assertCount(1, $entries);
        $this->assertSame('s_firstname', $entries[0]['key']);
    }

    /**
     * An ordinary envelope is read unchanged.
     *
     * The guard above must not have narrowed what a normal snapshot yields. Numbers are included
     * because a custom profile field of the number type stores one, and is_scalar() admits it.
     *
     * @return void
     */
    public function test_an_ordinary_envelope_is_read_whole(): void {
        $entries = submission::read_snapshot($this->envelope([
            ['key' => 's_firstname', 'label' => 'First name', 'value' => 'Terry'],
            ['key' => 'c_7', 'label' => 'Staff number', 'value' => 4815],
        ]));

        $this->assertCount(2, $entries);
        $this->assertSame('Terry', $entries[0]['value']);
        $this->assertSame('4815', $entries[1]['value']);
    }
}
