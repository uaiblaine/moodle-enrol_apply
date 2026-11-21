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
 * Tests for the report's first response and resolution (stage R2, AR-D4).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\reportbuilder;

use core_reportbuilder\local\filters\duration;
use enrol_apply\local\submission;
use enrol_apply\reportbuilder\datasource\applications;
use enrol_apply\reportbuilder\local\entities\submission as submissionentity;
use enrol_apply\reportbuilder\local\formatters\submission as submissionformatter;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the wait to a first response, the wait to a resolution and the first decision's date,
 * read through a custom report over the plugin's datasource.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(submissionentity::class)]
#[CoversClass(submissionformatter::class)]
final class first_response_report_test extends \core_reportbuilder\tests\core_reportbuilder_testcase {
    /** @var int The frozen "now" every test reads. */
    private const NOW = 1893456000;

    /** @var \stdClass Course carrying the apply instance. */
    private $course;

    /** @var \stdClass The enrol_apply instance record. */
    private $instance;

    /**
     * A course with an enabled apply instance, core's clock frozen, and the admin reading.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::NOW);
        $this->setAdminUser();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $id = $plugin->add_instance($this->course, $plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Seed one application record, named by its comment.
     *
     * @param string $name The comment, which names the row in the report.
     * @param array $fields Fields over a pending record made now.
     * @return void
     */
    private function seed(string $name, array $fields = []): void {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $DB->insert_record('enrol_apply_submission', (object) ($fields + [
            'courseid' => $this->course->id,
            'userid' => $applicant->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => 0,
            'comment' => $name,
            'status' => submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => self::NOW,
            'timedecided' => 0,
            'timefirstdecided' => 0,
            'decidedby' => 0,
        ]));
    }

    /**
     * Seed the five shapes a record's waits take.
     *
     * @return void
     */
    private function seed_every_shape(): void {
        $this->seed('pending', ['timecreated' => self::NOW - 4 * DAYSECS]);
        $created = self::NOW - 5 * DAYSECS;
        $this->seed('deferred', [
            'status' => submission::STATUS_WAITING,
            'timecreated' => $created,
            'timefirstdecided' => $created + 2 * HOURSECS,
            'timedecided' => $created + 2 * HOURSECS,
        ]);
        $created = self::NOW - 10 * DAYSECS;
        $this->seed('deferredthenapproved', [
            'status' => submission::STATUS_APPROVED,
            'timecreated' => $created,
            'timefirstdecided' => $created + 2 * HOURSECS,
            'timedecided' => $created + 3 * DAYSECS,
        ]);
        $this->seed('legacy', [
            'status' => submission::STATUS_APPROVED,
            'timecreated' => $created,
            'timedecided' => $created + DAYSECS,
        ]);
        $this->seed('once', [
            'status' => submission::STATUS_CANCELLED,
            'timecreated' => $created,
            'timefirstdecided' => $created + 6 * HOURSECS,
            'timedecided' => $created + 6 * HOURSECS,
        ]);
    }

    /**
     * A custom report over the datasource, its rows keyed by comment.
     *
     * @param array $columns Column identifiers, after the comment.
     * @param array $filters Filter identifiers.
     * @param array $values Filter values.
     * @return array Comment => the other cells in column order.
     */
    private function rows(array $columns, array $filters = [], array $values = []): array {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Waits', 'source' => applications::class, 'default' => 0]);
        foreach (array_merge(['submission:comment'], $columns) as $column) {
            $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => $column]);
        }
        foreach ($filters as $filter) {
            $generator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => $filter]);
        }

        $rows = [];
        foreach ($this->get_custom_report_content($report->get('id'), 30, $values) as $row) {
            $cells = array_values($row);
            $rows[strip_tags((string) array_shift($cells))] = $cells;
        }
        ksort($rows);

        return $rows;
    }

    /**
     * The wait is to the first response, with the resolution, a deferral's open wait or the
     * older record's label under it.
     *
     * Changes that must make it fail: the SQL measuring to the last decision again (gate FD8), the
     * deferral's line (FD9), the resolution's line (FD10) or the older record's label (FD11).
     *
     * @return void
     */
    public function test_the_wait_is_to_the_first_response(): void {
        $this->seed_every_shape();

        $rows = $this->rows(['submission:waittime']);

        $this->assertSame([
            'deferred' => ["2 h\nDeferred, open 5 d"],
            'deferredthenapproved' => ["2 h\nResolved after 3 d"],
            'legacy' => ["1 d\nTo the last decision"],
            'once' => ['6 h'],
            'pending' => ['4 d, open'],
        ], $rows);
    }

    /**
     * The wait for a resolution runs to the approval or the cancellation, and a deferral is still
     * open.
     *
     * Changes that must make it fail: the resolution's SQL counting a deferral as settled
     * (gate FD12), or the cell not marking it open (FD13).
     *
     * @return void
     */
    public function test_the_resolution_runs_to_the_settling_decision(): void {
        $this->seed_every_shape();

        $rows = $this->rows(['submission:resolutiontime']);

        $this->assertSame([
            'deferred' => ['5 d, open'],
            'deferredthenapproved' => ['3 d'],
            'legacy' => ['1 d'],
            'once' => ['6 h'],
            'pending' => ['4 d, open'],
        ], $rows);
    }

    /**
     * "Waited more than N" for a resolution keeps the open deferral and leaves out the record
     * whose first response came quickly but whose resolution did not reach the bound.
     *
     * The unfiltered count is the control.
     *
     * @return void
     */
    public function test_the_resolution_filter_keeps_the_longer_waits(): void {
        $this->seed_every_shape();
        $this->assertCount(5, $this->rows([], ['submission:resolutiontime']));

        $rows = $this->rows([], ['submission:resolutiontime'], [
            'submission:resolutiontime_operator' => duration::DURATION_MINIMUM,
            'submission:resolutiontime_value' => 100,
            'submission:resolutiontime_unit' => HOURSECS,
        ]);

        $this->assertSame(['deferred'], array_keys($rows));
    }

    /**
     * The first decision's date is a column of its own, "-" where the record holds none.
     *
     * @return void
     */
    public function test_the_first_decision_date_is_a_column(): void {
        $this->seed_every_shape();

        $rows = $this->rows(['submission:timefirstdecided']);

        $this->assertSame(userdate(self::NOW - 10 * DAYSECS + 2 * HOURSECS), $rows['deferredthenapproved'][0]);
        $this->assertSame('-', $rows['legacy'][0]);
        $this->assertSame('-', $rows['pending'][0]);
    }

    /**
     * Before the upgrade that adds the column, the wait reads the last decision and says so, and
     * the first decision's date is not offered.
     *
     * Changes that must make it fail: the entity reading the column without its gate (gate FD14).
     *
     * @return void
     */
    public function test_before_the_upgrade_the_report_reads_the_last_decision(): void {
        $this->seed_every_shape();
        set_config('version', ENROL_APPLY_FIRST_RESPONSE_SCHEMA - 1, 'enrol_apply');

        $rows = $this->rows(['submission:waittime']);

        $this->assertSame(["3 d\nTo the last decision"], $rows['deferredthenapproved']);
        $this->assertArrayNotHasKey('timefirstdecided', (new submissionentity())->initialise()->get_columns());
    }

    /**
     * An admission by a key waited for nobody, for a resolution as for a first response; an
     * aggregated resolution is a bare duration.
     *
     * @return void
     */
    public function test_the_resolution_wording_of_a_key_and_of_an_aggregate(): void {
        $key = (object) [
            'resolutionstatus' => submission::STATUS_APPROVED,
            'resolutionkeyoutcome' => submission::KEY_ADMITTED,
            'resolutionapplicant' => 7,
            'resolutiondecidedby' => 7,
        ];
        $this->assertSame('By key', submissionformatter::resolution(30, $key));

        $pending = (object) ['resolutionstatus' => submission::STATUS_PENDING];
        $this->assertSame('2 h, open', submissionformatter::resolution(2 * HOURSECS, $pending));
        $this->assertSame('2 h', submissionformatter::resolution(2 * HOURSECS, $pending, null, 'avg'));
        $this->assertSame('', submissionformatter::resolution(null, $pending));
    }
}
