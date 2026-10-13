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
 * Tests for storing and reading the durable application record's frozen answers.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for storing and reading the durable application record's frozen answers.
 *
 * write_answers() is the one writer for every route an answer arrives by - a submission, the
 * upgrade from the JSON envelope, and a restored archive - so what it does with data this site
 * did not write is its whole job. Most tests here feed it an envelope through parse_envelope(),
 * which is exactly what the upgrade and an old archive do.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(submission::class)]
final class submission_test extends \advanced_testcase {
    /**
     * Reset the database after each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Build an envelope around the given field entries.
     *
     * @param array $fields Field entries.
     * @param int $version Envelope version.
     * @return string JSON envelope.
     */
    protected function envelope(array $fields, int $version = submission::SNAPSHOT_VERSION): string {
        return (string) json_encode([
            'version' => $version,
            'fields' => $fields,
        ]);
    }

    /**
     * A bare record to hang answers off.
     *
     * @param int $courseid Course it names.
     * @return int Its id.
     */
    protected function record(int $courseid = 0): int {
        global $DB;

        return (int) $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $courseid,
            'userid' => 0,
            'enrolid' => 0,
            'userenrolmentid' => 0,
            'comment' => '',
            'status' => submission::STATUS_PENDING,
            'timecreated' => time(),
        ]);
    }

    /**
     * Store an envelope's entries on a new record and read them back.
     *
     * @param string $json The envelope.
     * @return array What read_snapshot() returns for that record.
     */
    protected function stored(string $json): array {
        $id = $this->record();
        submission::write_answers($id, submission::parse_envelope($json));

        return submission::read_snapshot($id);
    }

    /**
     * A field whose value is not scalar is dropped, and does not raise a warning.
     *
     * A restore copies what an archive this site did not produce says, so an array or a nested
     * object is reachable however careful the form is. Casting one to string raises an "Array to
     * string conversion" warning and renders the word "Array" as though the applicant had typed it.
     *
     * @return void
     */
    public function test_a_non_scalar_value_is_dropped(): void {
        $entries = $this->stored($this->envelope([
            ['key' => 's_city', 'label' => 'City', 'value' => ['a', 'b']],
            ['key' => 's_firstname', 'label' => 'First name', 'value' => 'Terry'],
        ]));

        /* The control: the well-formed entry beside it survives, so a writer that dropped the
           whole envelope fails here. */
        $this->assertCount(1, $entries);
        $this->assertSame('s_firstname', $entries[0]['key']);
        $this->assertSame('Terry', $entries[0]['value']);
    }

    /**
     * A field whose key or label is not scalar is dropped whole, not repaired.
     *
     * The masking matches each entry's key against the keys the reader may see, so an entry with
     * an unusable key cannot be masked correctly and is withheld rather than repaired.
     *
     * @return void
     */
    public function test_a_non_scalar_key_or_label_drops_the_whole_entry(): void {
        $entries = $this->stored($this->envelope([
            ['key' => ['s_city'], 'label' => 'City', 'value' => 'Campinas'],
            ['key' => 's_country', 'label' => ['Country'], 'value' => 'Brazil'],
            ['key' => 's_firstname', 'label' => 'First name', 'value' => 'Terry'],
        ]));

        // The control again: the sound entry is still stored.
        $this->assertCount(1, $entries);
        $this->assertSame('s_firstname', $entries[0]['key']);
    }

    /**
     * An ordinary envelope is stored unchanged and read back in its own order.
     *
     * A number is included because an envelope from another site's archive may carry one; this
     * plugin writes strings, and is_scalar() admits both. The order is the envelope's, not the
     * keys' alphabetical one: "c_7" sorts before "s_firstname" and is second.
     *
     * @return void
     */
    public function test_an_ordinary_envelope_is_stored_whole_and_in_order(): void {
        $entries = $this->stored($this->envelope([
            ['key' => 's_firstname', 'label' => 'First name', 'value' => 'Terry'],
            ['key' => 'c_7', 'label' => 'Staff number', 'value' => 4815],
            ['key' => 'a_3', 'label' => 'Why this course?', 'value' => 'A<B and R&D'],
        ]));

        $this->assertSame(['s_firstname', 'c_7', 'a_3'], array_column($entries, 'key'));
        $this->assertSame('4815', $entries[1]['value']);
        // Plain spelling, never escaped and never stripped: every reader escapes at its sink.
        $this->assertSame('A<B and R&D', $entries[2]['value']);
    }

    /**
     * An envelope of another version, or none at all, writes nothing.
     *
     * @return void
     */
    public function test_an_unknown_version_or_no_envelope_writes_nothing(): void {
        $this->assertSame([], $this->stored($this->envelope([
            ['key' => 's_city', 'label' => 'City', 'value' => 'Recife'],
        ], submission::SNAPSHOT_VERSION + 1)));
        $this->assertSame([], submission::parse_envelope(null));
        $this->assertSame([], submission::parse_envelope('not json'));
    }

    /**
     * A key repeated within one snapshot keeps its first value, and the record still saves.
     *
     * The table is unique on (submissionid, fieldkey) because a per-question report column reads
     * one value per record; without the writer's own check a crafted envelope would abort the
     * upgrade or the restore on that index.
     *
     * @return void
     */
    public function test_a_repeated_key_keeps_its_first_value(): void {
        $entries = $this->stored($this->envelope([
            ['key' => 's_city', 'label' => 'City', 'value' => 'Recife'],
            ['key' => 's_city', 'label' => 'City', 'value' => 'Olinda'],
            ['key' => 's_country', 'label' => 'Country', 'value' => 'BR'],
        ]));

        $this->assertSame(['s_city', 's_country'], array_column($entries, 'key'));
        $this->assertSame('Recife', $entries[0]['value']);
    }

    /**
     * A key the column cannot hold as given is dropped, never shortened or cleaned.
     *
     * A shortened or cleaned key could become one the masking shows, so the entry goes. The
     * control characters removed from a label or a value change nothing a reader sees, and keep
     * aggregate_sql()'s two separators out of the data.
     *
     * @return void
     */
    public function test_an_unstorable_key_is_dropped_and_control_characters_are_removed(): void {
        $entries = $this->stored($this->envelope([
            ['key' => str_repeat('k', submission::KEY_LENGTH + 1), 'label' => 'Long', 'value' => 'x'],
            ['key' => "s_first\x1Fname", 'label' => 'Forged', 'value' => 'x'],
            ['key' => 's_city', 'label' => "Ci\x1Fty", 'value' => "Rec\x1Eife\nline two"],
        ]));

        $this->assertCount(1, $entries);
        $this->assertSame('s_city', $entries[0]['key']);
        $this->assertSame('City', $entries[0]['label']);
        // The newline is kept: a textarea custom field is offerable and the cells draw it.
        $this->assertSame("Recife\nline two", $entries[0]['value']);
    }

    /**
     * The batch reader returns each record's own answers, in order, from one query.
     *
     * @return void
     */
    public function test_the_batch_reader_reads_many_records_in_one_query(): void {
        global $DB;

        $first = $this->record();
        $second = $this->record();
        $empty = $this->record();
        submission::write_answers($first, [
            ['key' => 's_city', 'label' => 'City', 'value' => 'Recife'],
            ['key' => 'a_1', 'label' => 'Q', 'value' => 'Yes'],
        ]);
        submission::write_answers($second, [['key' => 's_city', 'label' => 'City', 'value' => 'Natal']]);

        $reads = $DB->perf_get_reads();
        $snapshots = submission::read_snapshots([$first, $second, $empty, 0, $first]);
        $this->assertSame(1, $DB->perf_get_reads() - $reads);

        $this->assertSame(['s_city', 'a_1'], array_column($snapshots[$first], 'key'));
        $this->assertSame('Natal', $snapshots[$second][0]['value']);
        $this->assertArrayNotHasKey($empty, $snapshots);
        $this->assertSame([], submission::read_snapshots([]));
    }

    /**
     * The aggregate the custom report reads splits back into exactly the stored entries.
     *
     * Run against the database, because sql_group_concat() and sql_concat() differ per driver.
     *
     * @return void
     */
    public function test_the_report_aggregate_round_trips(): void {
        global $DB;

        $id = $this->record();
        submission::write_answers($id, [
            ['key' => 's_city', 'label' => 'City', 'value' => "Recife\nPE"],
            ['key' => 'a_9', 'label' => 'Size & fit', 'value' => 'O\'Brien <M>'],
        ]);

        $aggregate = $DB->get_field_sql(
            'SELECT ' . submission::aggregate_sql('s.id') . ' FROM {enrol_apply_submission} s WHERE s.id = :id',
            ['id' => $id]
        );

        $this->assertSame(submission::read_snapshot($id), submission::parse_aggregate($aggregate));
        $this->assertSame([], submission::parse_aggregate(null));
        // A row cut short, which is what a truncated GROUP_CONCAT leaves, is dropped.
        $this->assertSame([], submission::parse_aggregate('s_city' . submission::AGGREGATE_UNIT . 'City'));
    }

    /**
     * A record and its answers are deleted together, and other records keep theirs.
     *
     * @return void
     */
    public function test_delete_takes_the_answers_with_the_record(): void {
        global $DB;

        $gone = $this->record();
        $kept = $this->record();
        submission::write_answers($gone, [['key' => 's_city', 'label' => 'City', 'value' => 'Recife']]);
        submission::write_answers($kept, [['key' => 's_city', 'label' => 'City', 'value' => 'Natal']]);

        submission::delete($gone);

        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['id' => $gone]));
        $this->assertSame(0, $DB->count_records(submission::ANSWER_TABLE, ['submissionid' => $gone]));
        $this->assertSame(1, $DB->count_records(submission::ANSWER_TABLE, ['submissionid' => $kept]));
    }

    /**
     * Run the envelope migration against a temporary userinfodata column.
     *
     * The column no longer exists in install.xml, so it is added for the test and dropped again
     * whatever happens, leaving the schema as the suite expects it.
     *
     * @param callable $test Called with the column in place.
     * @return void
     */
    protected function with_envelope_column(callable $test): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/enrol/apply/db/upgradelib.php');

        $dbman = $DB->get_manager();
        $table = new \xmldb_table('enrol_apply_submission');
        $field = new \xmldb_field('userinfodata', XMLDB_TYPE_TEXT, null, null, null, null, null, 'comment');
        $dbman->add_field($table, $field);
        try {
            $test();
        } finally {
            $dbman->drop_field($table, $field);
        }
    }

    /**
     * The upgrade turns every envelope into rows, in order, dropping what the old reader dropped.
     *
     * Run twice: the second run must add nothing, because the savepoint is reached only after the
     * whole loop and a failure part way through re-enters the step from the first record.
     *
     * @return void
     */
    public function test_the_upgrade_moves_every_envelope_into_rows_once(): void {
        global $DB;

        $this->with_envelope_column(function () use ($DB): void {
            $good = $this->record();
            $none = $this->record();
            $wrongversion = $this->record();
            $DB->set_field('enrol_apply_submission', 'userinfodata', $this->envelope([
                ['key' => 's_city', 'label' => 'City', 'value' => 'Recife'],
                ['key' => 'c_4', 'label' => 'Broken', 'value' => ['nested']],
                'not an entry',
                ['key' => 'a_2', 'label' => 'Why?', 'value' => 'Because'],
            ]), ['id' => $good]);
            $DB->set_field('enrol_apply_submission', 'userinfodata', $this->envelope([
                ['key' => 's_city', 'label' => 'City', 'value' => 'Natal'],
            ], 2), ['id' => $wrongversion]);

            $this->assertSame(1, enrol_apply_migrate_snapshot_envelopes());

            $rows = $DB->get_records(submission::ANSWER_TABLE, ['submissionid' => $good], 'sortorder');
            $this->assertSame(['s_city', 'a_2'], array_values(array_column($rows, 'fieldkey')));
            $this->assertSame(['0', '1'], array_map('strval', array_values(array_column($rows, 'sortorder'))));
            $this->assertSame(0, $DB->count_records(submission::ANSWER_TABLE, ['submissionid' => $none]));
            $this->assertSame(0, $DB->count_records(submission::ANSWER_TABLE, ['submissionid' => $wrongversion]));

            // Re-entered, as a failed upgrade is: nothing is added.
            $this->assertSame(0, enrol_apply_migrate_snapshot_envelopes());
            $this->assertSame(2, $DB->count_records(submission::ANSWER_TABLE));
        });
    }

    /**
     * A record that already has rows is skipped whole, even when its envelope says more.
     *
     * That is the per-record idempotency the step relies on: the rows a record has were written in
     * one transaction, so "has rows" means "is done".
     *
     * @return void
     */
    public function test_the_upgrade_skips_a_record_that_already_has_rows(): void {
        global $DB;

        $this->with_envelope_column(function () use ($DB): void {
            $id = $this->record();
            submission::write_answers($id, [['key' => 's_city', 'label' => 'City', 'value' => 'Recife']]);
            $DB->set_field('enrol_apply_submission', 'userinfodata', $this->envelope([
                ['key' => 's_city', 'label' => 'City', 'value' => 'Recife'],
                ['key' => 's_country', 'label' => 'Country', 'value' => 'BR'],
            ]), ['id' => $id]);

            $this->assertSame(0, enrol_apply_migrate_snapshot_envelopes());
            $this->assertSame(1, $DB->count_records(submission::ANSWER_TABLE, ['submissionid' => $id]));
        });
    }
}
