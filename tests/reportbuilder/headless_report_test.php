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
 * Tests for the course report read headlessly (stage R3).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\reportbuilder;

use context_course;
use core_reportbuilder\local\filters\select;
use enrol_apply\local\groupscope;
use enrol_apply\local\reportengine;
use enrol_apply\local\reportpreference;
use enrol_apply\local\submission;
use enrol_apply\reportbuilder\local\systemreports\course_applications;
use enrol_apply\reportbuilder\local\systemreports\headless_table;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The rows and the summary the report's web services return, read through the engine.
 *
 * Six records in a course in separate groups mode, one per shape the turnaround metrics tell
 * apart: pending; deferred two hours after it arrived; deferred and then approved three days in;
 * approved before the first decision had a column (legacy); cancelled once; admitted by a key. The
 * first two are in the reader's group (A), with the key admission; the approved ones in another
 * (B); the cancelled one in none. Core's clock is frozen, so every wait is exact.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reportengine::class)]
#[CoversClass(headless_table::class)]
#[CoversClass(course_applications::class)]
final class headless_report_test extends \core_reportbuilder\tests\core_reportbuilder_testcase {
    /** @var int The frozen "now". */
    private const NOW = 1893456000;

    /** @var \stdClass A course in separate groups mode. */
    private $course;

    /** @var \stdClass Its apply instance. */
    private $instance;

    /** @var \stdClass The narrowed reader's group. */
    private $mine;

    /** @var \stdClass Another group. */
    private $theirs;

    /** @var array Applicant id by shape. */
    private $applicants = [];

    /**
     * The course, its groups and the six records.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::NOW);

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $plugin = enrol_get_plugin('apply');
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS]);
        $id = $plugin->add_instance($this->course, $plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $this->mine = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Alpha']);
        $this->theirs = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Beta']);

        $decider = (int) $generator->create_user()->id;
        $a = (int) $this->mine->id;
        $b = (int) $this->theirs->id;
        $old = self::NOW - 10 * DAYSECS;
        $this->seed('pending', 'Ana', $a, ['timecreated' => self::NOW - 4 * DAYSECS]);
        $this->seed('deferred', 'Bruno', $a, [
            'status' => submission::STATUS_WAITING,
            'timecreated' => self::NOW - 5 * DAYSECS,
            'timefirstdecided' => self::NOW - 5 * DAYSECS + 2 * HOURSECS,
            'timedecided' => self::NOW - 5 * DAYSECS + 2 * HOURSECS,
            'decidedby' => $decider,
        ]);
        $this->seed('resolved', 'Carla', $b, [
            'status' => submission::STATUS_APPROVED,
            'timecreated' => $old,
            'timefirstdecided' => $old + 2 * HOURSECS,
            'timedecided' => $old + 3 * DAYSECS,
            'decidedby' => $decider,
        ]);
        $this->seed('legacy', 'Davi', $b, [
            'status' => submission::STATUS_APPROVED,
            'timecreated' => $old,
            'timedecided' => $old + DAYSECS,
            'decidedby' => $decider,
        ]);
        $this->seed('cancelled', 'Elisa', 0, [
            'status' => submission::STATUS_CANCELLED,
            'timecreated' => $old,
            'timefirstdecided' => $old + 6 * HOURSECS,
            'timedecided' => $old + 6 * HOURSECS,
            'decidedby' => $decider,
        ]);
        $this->seed('key', 'Fabio', $a, [
            'status' => submission::STATUS_APPROVED,
            'keyoutcome' => submission::KEY_ADMITTED,
            'timecreated' => self::NOW - 2 * DAYSECS,
            'timefirstdecided' => self::NOW - 2 * DAYSECS,
            'timedecided' => self::NOW - 2 * DAYSECS,
            'decidedby' => 'self',
        ]);
    }

    /**
     * Write one record.
     *
     * @param string $shape The shape's name.
     * @param string $firstname The applicant's first name, which a search finds.
     * @param int $groupid The record's key group, 0 for none.
     * @param array $fields Fields over a pending record; decidedby 'self' names the applicant.
     * @return void
     */
    private function seed(string $shape, string $firstname, int $groupid, array $fields): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user(['firstname' => $firstname, 'lastname' => 'Applicant']);
        if (($fields['decidedby'] ?? 0) === 'self') {
            $fields['decidedby'] = $user->id;
        }
        $DB->insert_record('enrol_apply_submission', (object) ($fields + [
            'courseid' => $this->course->id,
            'userid' => $user->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => 0,
            'comment' => $shape,
            'status' => submission::STATUS_PENDING,
            'outcomemessage' => '',
            'keygroupid' => $groupid,
            'keyoutcome' => $groupid ? submission::KEY_FOR_REVIEW : submission::KEY_NONE,
            'timedecided' => 0,
            'timefirstdecided' => 0,
            'decidedby' => 0,
        ]));
        $this->applicants[$shape] = (int) $user->id;
    }

    /**
     * A reader holding enrol/apply:viewreports in the course through a role of their own, a member
     * of group A.
     *
     * @param bool $allgroups Whether the role also holds moodle/site:accessallgroups.
     * @return \stdClass The reader.
     */
    private function reader(bool $allgroups = false): \stdClass {
        $system = \context_system::instance();
        $role = $this->getDataGenerator()->create_role();
        assign_capability(groupscope::REPORT, CAP_ALLOW, $role, $system->id, true);
        if ($allgroups) {
            assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $role, $system->id, true);
        }
        $user = $this->getDataGenerator()->create_user();
        role_assign($role, $user->id, context_course::instance($this->course->id)->id);
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id);
        $this->getDataGenerator()->create_group_member(['groupid' => $this->mine->id, 'userid' => $user->id]);

        return $user;
    }

    /**
     * The applicant ids of some shapes, sorted.
     *
     * @param array $shapes Shape names.
     * @return array Ids.
     */
    private function ids(array $shapes): array {
        $ids = array_map(fn(string $shape): int => $this->applicants[$shape], $shapes);
        sort($ids);

        return $ids;
    }

    /**
     * The applicant ids of a rows answer, sorted, or in the order given.
     *
     * @param array $answer What reportengine::rows() returned.
     * @param bool $sorted Whether to sort them.
     * @return array Ids.
     */
    private function row_ids(array $answer, bool $sorted = true): array {
        $ids = array_column($answer['rows'], 'userid');
        if ($sorted) {
            sort($ids);
        }

        return $ids;
    }

    /**
     * The report for the instance, as the web services build it.
     *
     * @param string $measure What the wait measures.
     * @return course_applications The report.
     */
    private function report(string $measure = 'first'): course_applications {
        /* Core keeps one instance of a report per user for the whole request, and a test is one
           request: each read here stands for a request of its own. */
        \core_reportbuilder\manager::reset_caches();

        return reportengine::report($this->instance, $measure);
    }

    /**
     * Every row of the course for a reader who sees all groups, with the report's own cells, and
     * the summary counting exactly those rows.
     *
     * Changes that must make it fail: the rows not read from the report's table; a cell not the
     * report's formatted value; the three counts of the summary not adding up to the rows.
     *
     * @return void
     */
    public function test_the_rows_and_the_summary_count_the_same_records(): void {
        $this->setAdminUser();

        $rows = reportengine::rows($this->report(), []);
        $this->assertSame(6, $rows['total']);
        $this->assertSame($this->ids(array_keys($this->applicants)), $this->row_ids($rows));
        $this->assertFalse($rows['narrowed']);

        $keys = array_column($rows['rows'][0]['cells'], 'key');
        $this->assertSame('applicant', $keys[0]);
        $this->assertContains('outcome', $keys);
        $this->assertContains('wait', $keys);
        $applicant = array_column($rows['rows'][0]['cells'], 'html', 'key')['applicant'];
        $this->assertStringContainsString('Applicant</a>', $applicant);

        $summary = reportengine::summary($this->report(), []);
        $this->assertSame(6, $summary['total']);
        $this->assertSame($summary['total'], $summary['decided'] + $summary['open'] + $summary['withoutperson']);
        $this->assertFalse($summary['compact']);
    }

    /**
     * A reader narrowed by separate groups reads their group's rows alone, and the summary counts
     * the same rows, in the compact band (AR-D17).
     *
     * Changes that must make it fail: the table built without the report's base conditions; the
     * summary reading its own SQL rather than the table's; the compact band dropped.
     *
     * @return void
     */
    public function test_a_narrowed_reader_reads_and_counts_their_groups_rows_alone(): void {
        $this->setUser($this->reader());

        $rows = reportengine::rows($this->report(), []);
        $this->assertSame($this->ids(['pending', 'deferred', 'key']), $this->row_ids($rows));
        $this->assertSame(3, $rows['total']);
        $this->assertTrue($rows['narrowed']);
        $this->assertFalse($rows['notingroup']);

        $summary = reportengine::summary($this->report(), []);
        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['open']);
        $this->assertSame(1, $summary['decided']);
        $this->assertSame(1, $summary['withoutperson']);
        $this->assertSame(2 * HOURSECS, $summary['median']);
        $this->assertTrue($summary['compact']);
        $this->assertNull($summary['p90']);
        $this->assertSame([], $summary['buckets']);
        $this->assertSame([], $summary['bymethod']);
        $this->assertSame([], $summary['bygroup']);
    }

    /**
     * The control: a reader in the same group who may access all groups reads every row, with the
     * whole band.
     *
     * @return void
     */
    public function test_a_reader_who_sees_every_group_gets_every_row_and_the_whole_band(): void {
        $this->setUser($this->reader(true));

        $this->assertSame(6, reportengine::rows($this->report(), [])['total']);
        $summary = reportengine::summary($this->report(), []);
        $this->assertSame(6, $summary['total']);
        $this->assertFalse($summary['compact']);
        $this->assertNotNull($summary['p90']);
    }

    /**
     * A filter the reader stored in core's store narrows the rows and the summary alike, and the
     * rows say it applies.
     *
     * Changes that must make it fail: the stored filters not applied to the headless table; the
     * summary built from the report's base conditions alone.
     *
     * @return void
     */
    public function test_a_stored_filter_narrows_the_rows_and_the_summary_alike(): void {
        $this->setAdminUser();
        $report = $this->report();
        $report->set_filter_values([
            'submission:status_operator' => select::EQUAL_TO,
            'submission:status_value' => submission::STATUS_APPROVED,
        ]);

        $rows = reportengine::rows($this->report(), []);
        $this->assertSame($this->ids(['resolved', 'legacy', 'key']), $this->row_ids($rows));
        $filters = array_column($rows['filters'], null, 'name');
        $this->assertTrue($filters['submission:status']['applied']);
        $this->assertFalse($filters['user:fullname']['applied']);
        $this->assertNotEmpty($filters['submission:status']['options']);

        $summary = reportengine::summary($this->report(), []);
        $this->assertSame(3, $summary['total']);
        $this->assertSame(0, $summary['open']);
        $this->assertSame(1, $summary['withoutperson']);
    }

    /**
     * The search matches part of the applicant's name, whatever the case, in the rows and in the
     * summary; a single character searches nothing.
     *
     * Changes that must make it fail: the search not applied, applied to the rows only, or
     * applied below two characters.
     *
     * @return void
     */
    public function test_the_search_narrows_the_rows_and_the_summary_by_name(): void {
        $this->setAdminUser();

        $rows = reportengine::rows($this->report(), ['search' => ' cARl ']);
        $this->assertSame('cARl', $rows['search']);
        $this->assertSame($this->ids(['resolved']), $this->row_ids($rows));
        $this->assertSame(1, reportengine::summary($this->report(), ['search' => 'carl'])['total']);

        $rows = reportengine::rows($this->report(), ['search' => 'c']);
        $this->assertSame('', $rows['search']);
        $this->assertSame(6, $rows['total']);
    }

    /**
     * The scope lists what the summary counts as open, for both measures: a deferral is a first
     * response but leaves the application unresolved.
     *
     * Changes that must make it fail: the scope's SQL and the summary's per-row rule disagreeing;
     * the measure not read.
     *
     * @return void
     */
    public function test_the_scope_and_the_summary_agree_on_what_is_open(): void {
        $this->setAdminUser();

        foreach (['first' => ['pending'], 'resolution' => ['pending', 'deferred']] as $measure => $open) {
            $report = $this->report($measure);
            $awaiting = reportengine::rows($report, ['scope' => 'awaiting']);
            $decided = reportengine::rows($report, ['scope' => 'decided']);
            $summary = reportengine::summary($report, ['measure' => $measure]);

            $this->assertSame($this->ids($open), $this->row_ids($awaiting), $measure);
            $this->assertSame(count($open), $summary['open'], $measure);
            $this->assertSame($summary['decided'] + $summary['withoutperson'], $decided['total'], $measure);
            foreach ($awaiting['rows'] as $row) {
                $this->assertTrue($row['open'], $measure);
            }
            foreach ($decided['rows'] as $row) {
                $this->assertFalse($row['open'], $measure);
            }
            $this->assertSame(
                count($open),
                reportengine::summary($report, ['measure' => $measure, 'scope' => 'awaiting'])['total'],
                $measure
            );
        }
    }

    /**
     * The band's numbers to the first response: the median and the 90th percentile of the
     * decisions taken by a person, the histogram, and the breakdowns; the key admission is counted
     * apart (AR-D5).
     *
     * Waits by a person: deferred 2 h, resolved 2 h (its first response), cancelled 6 h, legacy 1 d
     * (to its last decision). Median (2 h + 6 h) / 2 = 4 h; 90th percentile 6 h + 0.7 * 18 h.
     *
     * Changes that must make it fail: the key admission measured; the first decision not read; the
     * percentile not interpolated; a bucket's bound inclusive at the top.
     *
     * @return void
     */
    public function test_the_band_measures_the_first_response_of_decisions_by_a_person(): void {
        $this->setAdminUser();

        $summary = reportengine::summary($this->report(), []);
        $this->assertSame(4, $summary['decided']);
        $this->assertSame(1, $summary['open']);
        $this->assertSame(1, $summary['withoutperson']);
        $this->assertSame(4 * DAYSECS, $summary['oldest']);
        $this->assertSame(0, $summary['late']);
        $this->assertSame(4 * HOURSECS, $summary['median']);
        $this->assertSame((int) round(6 * HOURSECS + 0.7 * 18 * HOURSECS), $summary['p90']);
        $this->assertSame([3, 1, 0, 0, 0], array_column($summary['buckets'], 'count'));
        $this->assertNull(end($summary['buckets'])['todays']);

        $this->assertCount(1, $summary['bymethod']);
        $this->assertSame(4, $summary['bymethod'][0]['count']);
        $groups = array_column($summary['bygroup'], null, 'name');
        $this->assertSame(1, $groups['Alpha']['count']);
        $this->assertSame(2, $groups['Beta']['count']);
        $this->assertSame(1, $groups[get_string('reportnogroup', 'enrol_apply')]['count']);
        $this->assertSame((int) round((2 * HOURSECS + DAYSECS) / 2), $groups['Beta']['median']);
    }

    /**
     * To the resolution, a deferral is open and a deferred-then-approved record waited until its
     * approval.
     *
     * @return void
     */
    public function test_the_band_measures_the_resolution(): void {
        $this->setAdminUser();

        $summary = reportengine::summary($this->report('resolution'), ['measure' => 'resolution']);
        $this->assertSame('resolution', $summary['measure']);
        $this->assertSame(3, $summary['decided']);
        $this->assertSame(2, $summary['open']);
        $this->assertSame(5 * DAYSECS, $summary['oldest']);
        $this->assertSame(DAYSECS, $summary['median']);
    }

    /**
     * A record waiting longer than a week is late.
     *
     * @return void
     */
    public function test_an_application_open_for_more_than_a_week_is_late(): void {
        $this->setAdminUser();
        $this->seed('late', 'Gil', 0, ['timecreated' => self::NOW - 8 * DAYSECS]);

        $summary = reportengine::summary($this->report(), []);
        $this->assertSame(1, $summary['late']);
        $this->assertSame(8 * DAYSECS, $summary['oldest']);
    }

    /**
     * A group the reader may not see is not named in the breakdown: its records join one bucket.
     *
     * Changes that must make it fail: the breakdown naming groups without core's visibility rule.
     *
     * @return void
     */
    public function test_the_group_breakdown_does_not_name_a_group_the_reader_may_not_see(): void {
        global $DB;

        $DB->set_field('groups', 'visibility', GROUPS_VISIBILITY_NONE, ['id' => $this->theirs->id]);
        $this->setUser($this->reader(true));

        $groups = array_column(reportengine::summary($this->report(), [])['bygroup'], null, 'id');
        $this->assertArrayNotHasKey((int) $this->theirs->id, $groups);
        $this->assertSame(get_string('reportgroupwithheld', 'enrol_apply'), $groups[reportengine::GROUP_WITHHELD]['name']);
        $this->assertSame(2, $groups[reportengine::GROUP_WITHHELD]['count']);
        $this->assertSame('Alpha', $groups[(int) $this->mine->id]['name']);
    }

    /**
     * The rows sort by a column the report shows and can sort by; any other key leaves the report's
     * own sort, newest first.
     *
     * Changes that must make it fail: a sort key taken without checking the report shows it, or
     * that it sorts.
     *
     * @return void
     */
    public function test_the_rows_sort_only_by_a_sortable_column_the_report_shows(): void {
        $this->setAdminUser();

        $rows = reportengine::rows($this->report(), ['sort' => 'wait', 'direction' => 'asc']);
        $this->assertSame('wait', $rows['sort']);
        $this->assertSame('asc', $rows['direction']);
        $ids = $this->row_ids($rows, false);
        $this->assertSame($this->applicants['key'], $ids[0]);
        $this->assertSame($this->applicants['pending'], end($ids));
        $waits = array_column($rows['rows'], 'wait');
        $this->assertSame(0, $waits[0]);
        $this->assertSame(4 * DAYSECS, end($waits));

        foreach (['group', 'route', 'nope', 'submission:snapshot'] as $key) {
            $rows = reportengine::rows($this->report(), ['sort' => $key, 'direction' => 'asc']);
            $this->assertSame('', $rows['sort'], $key);
            $this->assertSame($this->applicants['key'], $this->row_ids($rows, false)[0], $key);
        }
    }

    /**
     * A page size outside the three offered reads as 30, and a page past the last one returns the
     * last.
     *
     * @return void
     */
    public function test_paging_is_bounded(): void {
        $this->setAdminUser();

        $rows = reportengine::rows($this->report(), ['perpage' => 2]);
        $this->assertSame(30, $rows['perpage']);
        $this->assertCount(6, $rows['rows']);

        $rows = reportengine::rows($this->report(), ['perpage' => 30, 'page' => 7]);
        $this->assertSame(0, $rows['page']);
        $this->assertCount(6, $rows['rows']);
    }

    /**
     * The report's table in its default sort, set up as the engine sets it up.
     *
     * @return headless_table The table.
     */
    private function table(): headless_table {
        $report = $this->report();
        $table = headless_table::create((int) $report->get_report_persistent()->get('id'), $report->get_parameters());
        $table->guess_base_url();
        $table->setup();

        return $table;
    }

    /**
     * One page of two rows, read through the table.
     *
     * @param headless_table $table The table, set up.
     * @param int $page 0-based page.
     * @return array [applicant ids in the page's order, the total].
     */
    private function page_of_two(headless_table $table, int $page): array {
        $table->set_page_number($page + 1);
        $table->query_db(2, false);
        $ids = array_map(fn(\stdClass $record): int => (int) $record->userid, $table->rawdata);

        return [$ids, (int) $table->totalrows];
    }

    /**
     * The pages of the default sort (newest application first) cover every row once, in order, each
     * page counting all six: the page read by ids and then whole (R6) agrees with the order of one
     * statement.
     *
     * Changes that must make it fail: the second statement's rows kept in its own order rather than
     * the first's; the offset or the limit dropped from the first statement; the total not read from
     * it.
     *
     * @return void
     */
    public function test_the_pages_hold_every_row_once_in_the_sort_order(): void {
        $this->setAdminUser();

        $pages = [];
        foreach ([0, 1, 2] as $page) {
            [$ids, $total] = $this->page_of_two($this->table(), $page);
            $this->assertSame(6, $total, "page {$page}");
            $this->assertCount(2, $ids, "page {$page}");
            $pages[] = $ids;
        }
        // Newest first; the three of the same second by record id, the order they were written in.
        $this->assertSame([
            [$this->applicants['key'], $this->applicants['pending']],
            [$this->applicants['deferred'], $this->applicants['resolved']],
            [$this->applicants['legacy'], $this->applicants['cancelled']],
        ], $pages);

        // A page past the last one reads nothing, as core's counted read does.
        [$ids] = $this->page_of_two($this->table(), 3);
        $this->assertSame([], $ids);
    }

    /**
     * The page's ids are ordered by the reader's sort and then by the record's id, so rows tied on
     * the sort (three of the six arrived in the same second) fall on one page each.
     *
     * Changes that must make it fail: the unique last key dropped; the reader's sort dropped.
     *
     * @return void
     */
    public function test_the_page_order_ends_in_a_unique_key(): void {
        $this->setAdminUser();

        $table = $this->table();
        $order = $table->page_order();
        $this->assertStringEndsWith(', results.id', $order);
        $this->assertStringStartsWith($table->get_sql_sort() . ',', $order);
        $this->assertNotSame('', $table->get_sql_sort());
    }

    /**
     * With no choice stored the report shows its default set; a stored choice shows the catalogue
     * keys it names and nothing it names that the report does not offer, on the headless rows and
     * on the report's own table alike (the download reads that table).
     *
     * Changes that must make it fail: the preference not read in initialise(); a stored key added
     * without the catalogue; a single question not shown; the default set changed.
     *
     * @return void
     */
    public function test_the_columns_follow_the_readers_choice_within_the_catalogue(): void {
        $this->setAdminUser();

        // The default set, then the questions the default method asks, one column each.
        $default = array_keys($this->report()->active_keys());
        $this->assertSame(
            ['applicant', 'identity:email', 'outcome', 'submitted', 'wait', 'decided', 'group', 'comment', 'note'],
            array_slice($default, 0, 9)
        );
        $this->assertNotEmpty(array_slice($default, 9));
        foreach (array_slice($default, 9) as $key) {
            $this->assertStringStartsWith('answer:', $key);
        }

        reportpreference::write(['note', 'route', 'nope', 'answer:s_city', 'identity:idnumber'], []);
        $report = $this->report();
        // One question named alone is shown where the course asks it.
        $expected = ['applicant', 'identity:email', 'route', 'note', 'answer:s_city'];
        $this->assertSame($expected, array_keys($report->active_keys()));
        $this->assertCount(5, $report->get_active_columns());

        $rows = reportengine::rows($report, []);
        $this->assertSame($expected, array_column($rows['rows'][0]['cells'], 'key'));
        $columns = array_column($rows['columns'], null, 'key');
        $this->assertTrue($columns['route']['active']);
        $this->assertFalse($columns['route']['default']);
        $this->assertFalse($columns['wait']['active']);
        $this->assertTrue($columns['applicant']['fixed']);
        $this->assertArrayNotHasKey('nope', $columns);
        $this->assertFalse($rows['preference']['usedefault']);
        $this->assertSame(['note', 'route', 'nope', 'answer:s_city', 'identity:idnumber'], $rows['preference']['columns']);
    }

    /**
     * A reader without moodle/site:viewuseridentity gets no identity column, chosen or not.
     *
     * @return void
     */
    public function test_an_identity_column_is_only_for_a_reader_who_may_see_it(): void {
        $this->setUser($this->reader(true));
        reportpreference::write(['outcome', 'identity:email'], []);

        $keys = array_keys($this->report()->active_keys());
        $this->assertSame(['applicant', 'outcome'], $keys);
    }

    /**
     * The measure picks the wait column: the first response, or the resolution.
     *
     * @return void
     */
    public function test_the_measure_picks_the_wait_column(): void {
        $this->setAdminUser();

        $this->assertSame('submission:waittime', $this->report()->active_keys()['wait']);
        $this->assertSame('submission:resolutiontime', $this->report('resolution')->active_keys()['wait']);
        $this->assertSame('submission:waittime', $this->report('anything')->active_keys()['wait']);
    }

    /**
     * An application awaiting a decision carries the review page's url for a reader who may open
     * it; a decided one carries none.
     *
     * Changes that must make it fail: the url drawn without the report's can_review().
     *
     * @return void
     */
    public function test_only_a_reviewable_row_carries_the_review_url(): void {
        global $DB;

        $this->setAdminUser();
        $plugin = enrol_get_plugin('apply');
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Hugo']);
        $plugin->enrol_user($this->instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $this->instance->id, 'userid' => $user->id]);
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $user->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => $ueid,
            'comment' => '',
            'status' => submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => self::NOW,
            'timedecided' => 0,
            'decidedby' => 0,
        ]);

        $rows = array_column(reportengine::rows($this->report(), [])['rows'], null, 'userid');
        $this->assertStringContainsString('/enrol/apply/manage.php?userenrol=' . $ueid, $rows[$user->id]['reviewurl']);
        $this->assertSame('', $rows[$this->applicants['legacy']]['reviewurl']);
    }

    /**
     * The per-row facts the rows return agree with the entity's wait columns.
     *
     * @return void
     */
    public function test_the_raw_wait_is_the_columns_wait(): void {
        $this->setAdminUser();

        $rows = array_column(reportengine::rows($this->report(), [])['rows'], null, 'userid');
        $this->assertSame(2 * HOURSECS, $rows[$this->applicants['resolved']]['wait']);
        $this->assertSame(DAYSECS, $rows[$this->applicants['legacy']]['wait']);
        $this->assertTrue($rows[$this->applicants['resolved']]['decidedbyperson']);
        $this->assertFalse($rows[$this->applicants['key']]['decidedbyperson']);

        $rows = array_column(reportengine::rows($this->report('resolution'), [])['rows'], null, 'userid');
        $this->assertSame(3 * DAYSECS, $rows[$this->applicants['resolved']]['wait']);
        $this->assertSame(5 * DAYSECS, $rows[$this->applicants['deferred']]['wait']);
        $this->assertTrue($rows[$this->applicants['deferred']]['open']);
    }

    /**
     * In one request the report is built once, so the rows read the first measure asked for; the
     * summary takes the measure it is given and is right whatever was asked before it.
     *
     * Changes that must make it fail: the summary reading the report's measure.
     *
     * @return void
     */
    public function test_the_summary_keeps_its_own_measure_within_one_request(): void {
        $this->setAdminUser();

        $report = $this->report('first');
        $this->assertSame('first', reportengine::rows($report, [])['measure']);
        $again = reportengine::report($this->instance, 'resolution');
        $this->assertSame('first', reportengine::rows($again, [])['measure']);

        $summary = reportengine::summary($again, ['measure' => 'resolution']);
        $this->assertSame('resolution', $summary['measure']);
        $this->assertSame(2, $summary['open']);
    }

    /**
     * No report reaches the rows or the summary for a reader it refuses: core runs the report's
     * can_view() while building it, whoever builds it, which is why the readers check nothing
     * themselves.
     *
     * @return void
     */
    public function test_the_report_cannot_be_built_for_a_reader_it_refuses(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);
        \core_reportbuilder\manager::reset_caches();

        $this->expectException(\core_reportbuilder\exception\report_access_exception::class);
        course_applications::for_method(context_course::instance($this->course->id), (int) $this->instance->id);
    }

    /**
     * The percentile interpolates between ranks and is null with nothing to measure.
     *
     * @return void
     */
    public function test_the_percentile(): void {
        $this->assertNull(reportengine::percentile([], 0.5));
        $this->assertSame(5, reportengine::percentile([5], 0.9));
        $this->assertSame(15, reportengine::percentile([10, 20], 0.5));
        $this->assertSame(19, reportengine::percentile([10, 20], 0.9));
    }
}
