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
 * Tests for the first decision's date of an application record (stage R2, AR-D4).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use context_course;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for timefirstdecided: stamped once by submission::decide(), behind its own schema gate,
 * and exported to both subjects.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(submission::class)]
#[CoversClass(\enrol_apply\privacy\provider::class)]
final class first_decision_test extends \core_privacy\tests\provider_testcase {
    /** @var int The user enrolment id the seeded record names; decide() matches on it alone. */
    private const UEID = 424242;

    /** @var \stdClass The course of the seeded record. */
    private $course;

    /**
     * A course with an apply instance.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        require_once($CFG->dirroot . '/enrol/apply/lib.php');
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Seed the record decide() will find.
     *
     * @param array $fields Fields over a pending, undecided record.
     * @param int $userid The applicant, 0 for a new user.
     * @return int The record id.
     */
    private function seed(array $fields = [], int $userid = 0): int {
        global $DB;

        $plugin = enrol_get_plugin('apply');
        $instanceid = $plugin->add_instance($this->course, $plugin->get_instance_defaults());

        return (int) $DB->insert_record('enrol_apply_submission', (object) ($fields + [
            'courseid' => $this->course->id,
            'userid' => $userid ?: $this->getDataGenerator()->create_user()->id,
            'enrolid' => $instanceid,
            'userenrolmentid' => self::UEID,
            'comment' => '',
            'status' => submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => 100,
            'timedecided' => 0,
            'decidedby' => 0,
        ]));
    }

    /**
     * The record's two decision dates.
     *
     * @param int $id The record.
     * @return array [timefirstdecided, timedecided].
     */
    private function dates(int $id): array {
        global $DB;

        $row = $DB->get_record('enrol_apply_submission', ['id' => $id], 'timefirstdecided, timedecided', MUST_EXIST);
        return [(int) $row->timefirstdecided, (int) $row->timedecided];
    }

    /**
     * A first decision stamps both dates alike, and a deferral counts as one.
     *
     * Changes that must make it fail: decide() not stamping the first decision, or stamping it
     * with another time than the decision's.
     *
     * @return void
     */
    public function test_the_first_decision_stamps_both_dates(): void {
        $id = $this->seed();

        submission::decide(self::UEID, submission::STATUS_WAITING, 2);

        [$first, $decided] = $this->dates($id);
        $this->assertGreaterThan(0, $first);
        $this->assertSame($decided, $first);
    }

    /**
     * A later decision moves the last date and keeps the first, also when the caller says the
     * decision is fresh.
     *
     * Changes that must make it fail: the first date stamped on every decision (gate FD1).
     *
     * @return void
     */
    public function test_a_later_decision_keeps_the_first_date(): void {
        global $DB;

        $id = $this->seed();
        submission::decide(self::UEID, submission::STATUS_WAITING, 2);
        $DB->update_record('enrol_apply_submission', (object) ['id' => $id, 'timefirstdecided' => 111, 'timedecided' => 111]);

        submission::decide(self::UEID, submission::STATUS_APPROVED, 3, true);
        [$first, $decided] = $this->dates($id);
        $this->assertSame(111, $first);
        $this->assertGreaterThan(111, $decided, 'the premise: the decision was written');

        // A re-decision at the same status, which only a fresh decision writes.
        $DB->set_field('enrol_apply_submission', 'timedecided', 222, ['id' => $id]);
        submission::decide(self::UEID, submission::STATUS_APPROVED, 3, true);
        [$first, $decided] = $this->dates($id);
        $this->assertSame(111, $first);
        $this->assertGreaterThan(222, $decided, 'the premise: the re-decision was written');
    }

    /**
     * A record decided before the column existed keeps no first date: its last decision may be a
     * re-decision, and calling a new one the first would be false.
     *
     * Changes that must make it fail: the stamp reading only timefirstdecided (gate FD2).
     *
     * @return void
     */
    public function test_a_record_decided_before_the_column_gets_no_first_date(): void {
        $id = $this->seed(['status' => submission::STATUS_WAITING, 'timedecided' => 500]);

        submission::decide(self::UEID, submission::STATUS_APPROVED, 3, true);

        [$first, $decided] = $this->dates($id);
        $this->assertSame(0, $first);
        $this->assertGreaterThan(500, $decided, 'the premise: the decision was written');
    }

    /**
     * A write back to pending is no response.
     *
     * Changes that must make it fail: the stamp not excluding STATUS_PENDING (gate FD3).
     *
     * @return void
     */
    public function test_a_pending_write_is_no_first_decision(): void {
        $id = $this->seed();

        submission::decide(self::UEID, submission::STATUS_PENDING, 2, true);

        [$first, $decided] = $this->dates($id);
        $this->assertGreaterThan(0, $decided, 'the premise: the record was written');
        $this->assertSame(0, $first);
    }

    /**
     * Before the upgrade that adds the column, decide() neither reads nor writes it; after it,
     * the same call stamps it (the control).
     *
     * The column exists in the test database whatever the stored version says, so the gate is
     * observed as the write that does not happen.
     *
     * Changes that must make it fail: decide() not asking the schema gate (gate FD4).
     *
     * @return void
     */
    public function test_decide_waits_for_the_upgrade(): void {
        $id = $this->seed();
        set_config('version', ENROL_APPLY_FIRST_RESPONSE_SCHEMA - 1, 'enrol_apply');

        submission::decide(self::UEID, submission::STATUS_WAITING, 2);
        [$first, $decided] = $this->dates($id);
        $this->assertGreaterThan(0, $decided, 'the premise: the decision was written');
        $this->assertSame(0, $first);

        $other = $this->seed(['userenrolmentid' => self::UEID + 1]);
        set_config('version', ENROL_APPLY_FIRST_RESPONSE_SCHEMA, 'enrol_apply');
        submission::decide(self::UEID + 1, submission::STATUS_WAITING, 2);
        $this->assertGreaterThan(0, $this->dates($other)[0]);
    }

    /**
     * The gate's constant names the upgrade step that adds the column, and the plugin's version
     * reaches it.
     *
     * @return void
     */
    public function test_the_schema_version_is_the_step_that_adds_the_column(): void {
        global $CFG;

        $upgrade = file_get_contents($CFG->dirroot . '/enrol/apply/db/upgrade.php');
        $this->assertSame(1, preg_match(
            '/if \(\$oldversion < (\d+)\) \{(?:(?!if \(\$oldversion).)*xmldb_field\(\'timefirstdecided\'/s',
            $upgrade,
            $matches
        ));
        $this->assertSame(ENROL_APPLY_FIRST_RESPONSE_SCHEMA, (int) $matches[1]);

        $plugin = new \stdClass();
        require($CFG->dirroot . '/enrol/apply/version.php');
        $this->assertGreaterThanOrEqual(ENROL_APPLY_FIRST_RESPONSE_SCHEMA, $plugin->version);
    }

    /**
     * Both subjects' exports carry the first decision's date, and null for a record without one.
     *
     * Changes that must make it fail: the export leaving the date out.
     *
     * @return void
     */
    public function test_the_first_decision_is_exported_to_both_subjects(): void {
        $applicant = $this->getDataGenerator()->create_user();
        $decider = $this->getDataGenerator()->create_user();
        $id = $this->seed([
            'status' => submission::STATUS_APPROVED,
            'timefirstdecided' => 1000,
            'timedecided' => 2000,
            'decidedby' => $decider->id,
        ], (int) $applicant->id);
        $context = context_course::instance($this->course->id);

        foreach (['privacy:roleapplicant' => $applicant, 'privacy:roledecider' => $decider] as $rolekey => $subject) {
            writer::reset();
            $this->export_context_data_for_user((int) $subject->id, $context, 'enrol_apply');
            $exported = writer::with_context($context)->get_data([
                get_string('privacy:trailpath', 'enrol_apply'),
                get_string($rolekey, 'enrol_apply'),
                get_string('privacy:recordpath', 'enrol_apply', $id),
            ]);
            $this->assertSame(transform::datetime(1000), $exported->timefirstdecided, $rolekey);
            $this->assertSame(transform::datetime(2000), $exported->timedecided, $rolekey);
        }

        // The control: a record no decision has touched exports no first date.
        global $DB;
        $DB->update_record('enrol_apply_submission', (object) ['id' => $id, 'timefirstdecided' => 0]);
        writer::reset();
        $this->export_context_data_for_user((int) $applicant->id, $context, 'enrol_apply');
        $exported = writer::with_context($context)->get_data([
            get_string('privacy:trailpath', 'enrol_apply'),
            get_string('privacy:roleapplicant', 'enrol_apply'),
            get_string('privacy:recordpath', 'enrol_apply', $id),
        ]);
        $this->assertNull($exported->timefirstdecided);
    }
}
