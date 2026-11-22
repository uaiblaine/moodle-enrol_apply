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
 * Tests for the course report's filter panel counts, column sets and download conditions.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\reportbuilder;

use context_course;
use core_reportbuilder\local\filters\duration;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use enrol_apply\local\groupscope;
use enrol_apply\local\reportengine;
use enrol_apply\local\reportpreference;
use enrol_apply\local\submission;
use enrol_apply\reportbuilder\local\systemreports\course_applications;
use enrol_apply\reportbuilder\local\systemreports\headless_table;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The panel's faceted counts, the Columns menu's sets and the download's scope and search.
 *
 * Six records in a course in separate groups mode: pending (group A); deferred two hours in, by
 * Maria (A); approved after three days, by Zeca (B); approved a day in before the first decision had
 * a column, by Maria (B, and joined A); cancelled after six hours, by Zeca (no group); admitted by a
 * key (A). Group A is in grouping North, B in grouping South. Core's clock is frozen.
 *
 * Every count is checked against what the rows list once its option is chosen: the count is the
 * promise "choose this and you get that many rows", so the test chooses it.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reportengine::class)]
#[CoversClass(headless_table::class)]
#[CoversClass(course_applications::class)]
#[CoversClass(groupscope::class)]
final class report_facets_test extends \core_reportbuilder\tests\core_reportbuilder_testcase {
    /** @var int The frozen "now". */
    private const NOW = 1893456000;

    /** @var \stdClass A course in separate groups mode. */
    private $course;

    /** @var \stdClass Its apply instance. */
    private $instance;

    /** @var \stdClass Group A, the narrowed reader's. */
    private $mine;

    /** @var \stdClass Group B. */
    private $theirs;

    /** @var array Applicant id by shape. */
    private $applicants = [];

    /** @var \stdClass The first decider. */
    private $maria;

    /** @var \stdClass The second decider. */
    private $zeca;

    /**
     * The course, its groups and groupings, and the six records.
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
        $north = $generator->create_grouping(['courseid' => $this->course->id, 'name' => 'North']);
        $south = $generator->create_grouping(['courseid' => $this->course->id, 'name' => 'South']);
        $generator->create_grouping_group(['groupingid' => $north->id, 'groupid' => $this->mine->id]);
        $generator->create_grouping_group(['groupingid' => $south->id, 'groupid' => $this->theirs->id]);

        $this->maria = $generator->create_user(['firstname' => 'Maria', 'lastname' => 'Souza']);
        $this->zeca = $generator->create_user(['firstname' => 'Zeca', 'lastname' => 'Lima']);
        $a = (int) $this->mine->id;
        $b = (int) $this->theirs->id;
        $old = self::NOW - 10 * DAYSECS;
        $this->seed('pending', 'Ana', $a, ['timecreated' => self::NOW - 4 * DAYSECS]);
        $this->seed('deferred', 'Bruno', $a, [
            'status' => submission::STATUS_WAITING,
            'timecreated' => self::NOW - 5 * DAYSECS,
            'timefirstdecided' => self::NOW - 5 * DAYSECS + 2 * HOURSECS,
            'timedecided' => self::NOW - 5 * DAYSECS + 2 * HOURSECS,
            'decidedby' => $this->maria->id,
        ]);
        $this->seed('resolved', 'Carla', $b, [
            'status' => submission::STATUS_APPROVED,
            'timecreated' => $old,
            'timefirstdecided' => $old + 2 * HOURSECS,
            'timedecided' => $old + 3 * DAYSECS,
            'decidedby' => $this->zeca->id,
            'joinedgroups' => (string) $b,
        ]);
        $this->seed('legacy', 'Davi', $b, [
            'status' => submission::STATUS_APPROVED,
            'timecreated' => $old,
            'timedecided' => $old + DAYSECS,
            'decidedby' => $this->maria->id,
            'joinedgroups' => $b . ',' . $a,
        ]);
        $this->seed('cancelled', 'Elisa', 0, [
            'status' => submission::STATUS_CANCELLED,
            'timecreated' => $old,
            'timefirstdecided' => $old + 6 * HOURSECS,
            'timedecided' => $old + 6 * HOURSECS,
            'decidedby' => $this->zeca->id,
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
     * A reader holding the report in the course through a role of their own, a member of group A.
     *
     * @return \stdClass The reader.
     */
    private function reader(): \stdClass {
        $role = $this->getDataGenerator()->create_role();
        assign_capability(groupscope::REPORT, CAP_ALLOW, $role, \context_system::instance()->id, true);
        $user = $this->getDataGenerator()->create_user();
        role_assign($role, $user->id, context_course::instance($this->course->id)->id);
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id);
        $this->getDataGenerator()->create_group_member(['groupid' => $this->mine->id, 'userid' => $user->id]);

        return $user;
    }

    /**
     * The report for the instance, as the web services build it, as a request of its own.
     *
     * @param string $measure What the wait measures.
     * @return course_applications The report.
     */
    private function report(string $measure = 'first'): course_applications {
        \core_reportbuilder\manager::reset_caches();

        return reportengine::report($this->instance, $measure);
    }

    /**
     * Store the reader's filter values, replacing the stored set as core's service does.
     *
     * @param array $values The values.
     * @return void
     */
    private function store(array $values): void {
        $this->report()->set_filter_values($values);
    }

    /**
     * One filter of a rows answer.
     *
     * @param array $answer What reportengine::rows() returned.
     * @param string $name The filter's unique identifier.
     * @return array The filter.
     */
    private function filter(array $answer, string $name): array {
        $filters = array_column($answer['filters'], null, 'name');
        $this->assertArrayHasKey($name, $filters);

        return $filters[$name];
    }

    /**
     * A facet's counts, by option value.
     *
     * @param array $answer What reportengine::rows() returned.
     * @param string $name The filter's unique identifier.
     * @return array Value => count.
     */
    private function counts(array $answer, string $name): array {
        return array_column($this->filter($answer, $name)['options'], 'count', 'value');
    }

    /**
     * The applicant ids of a list of rows, sorted.
     *
     * @param array $rows Records or answer rows carrying userid.
     * @return array Ids.
     */
    private function userids(array $rows): array {
        $ids = array_map(static fn($row): int => (int) ((array) $row)['userid'], $rows);
        sort($ids);

        return $ids;
    }

    /**
     * Every count of a facet is what the rows list once that option is chosen, under the same other
     * filters; and the stored values are put back afterwards.
     *
     * @param array $others The other stored values in force.
     * @param string $name The facet's filter.
     * @param array $counts Value => count, as the facet answered.
     * @param callable $values Given an option's value, the facet's own stored values that choose it.
     * @param array $options Options for the rows call (scope, search).
     * @return void
     */
    private function assert_counts_are_what_choosing_lists(
        array $others,
        string $name,
        array $counts,
        callable $values,
        array $options = []
    ): void {
        $this->assertNotEmpty($counts);
        foreach ($counts as $value => $count) {
            $this->store($others + $values((string) $value));
            $this->assertSame($count, reportengine::rows($this->report(), $options)['total'], "{$name} = {$value}");
        }
        $this->store($others);
    }

    /**
     * A reader narrowed by separate groups is counted their own rows alone: the status facet's counts,
     * one status per record, add up to what their table lists, which is less than the whole course.
     *
     * Changes that must make it fail: the counts read without the report's base conditions (S8), or
     * over the course rather than the table's FROM and WHERE.
     *
     * @return void
     */
    public function test_a_narrowed_readers_counts_sum_to_what_their_table_lists(): void {
        $this->setAdminUser();
        $all = reportengine::rows($this->report(), []);
        $this->assertSame(6, $all['total']);
        $this->assertSame(6, array_sum($this->counts($all, 'submission:status')));

        $this->setUser($this->reader());
        $mine = reportengine::rows($this->report(), []);
        // Pending, deferred and the key admission by the key group; the legacy approval by joining A.
        $this->assertSame(4, $mine['total']);
        $this->assertTrue($mine['counted']);
        $counts = $this->counts($mine, 'submission:status');
        $this->assertSame($mine['total'], array_sum($counts));
        $this->assertSame(2, $counts[(string) submission::STATUS_APPROVED]);
        $this->assertSame(0, $counts[(string) submission::STATUS_CANCELLED]);

        // The group facet offers and counts the reader's own group alone, and its one grouping.
        $group = $this->filter($mine, 'submission:group');
        $this->assertSame([(string) $this->mine->id => 4], $this->counts($mine, 'submission:group'));
        $this->assertSame(['North'], array_column($group['groupings'], 'name'));
        $this->assertSame([(int) $this->mine->id], $group['groupings'][0]['groups']);
    }

    /**
     * A facet is counted without its own value: with one status chosen the other statuses still say
     * how many rows they would list, and the chosen one's count is the rows' total.
     *
     * Changes that must make it fail: the facet's own filter left in force while counting it.
     *
     * @return void
     */
    public function test_a_facet_is_counted_without_its_own_value(): void {
        $this->setAdminUser();
        $this->store([
            'submission:status_operator' => select::EQUAL_TO,
            'submission:status_value' => submission::STATUS_APPROVED,
        ]);

        $answer = reportengine::rows($this->report(), []);
        $this->assertSame(3, $answer['total']);
        $counts = $this->counts($answer, 'submission:status');
        $this->assertSame($answer['total'], $counts[(string) submission::STATUS_APPROVED]);
        $this->assertSame(1, $counts[(string) submission::STATUS_PENDING]);
        $this->assertSame(1, $counts[(string) submission::STATUS_WAITING]);
        $this->assertSame(6, array_sum($counts));

        // Another facet is counted under the chosen status: the admission routes of approvals only.
        $routes = $this->counts($answer, 'submission:admissionroute');
        $this->assertSame(3, array_sum($routes));
        $this->assertSame(1, $routes[(string) submission::KEY_ADMITTED]);
    }

    /**
     * A facet is counted under every other filter in force, the scope and the search, and each count
     * is what choosing that option lists.
     *
     * Changes that must make it fail: another stored filter, the scope or the search left out of the
     * counts.
     *
     * @return void
     */
    public function test_a_facet_is_counted_under_the_other_filters_the_scope_and_the_search(): void {
        $this->setAdminUser();
        $others = [
            'submission:group_operator' => select::EQUAL_TO,
            'submission:group_value' => (string) $this->theirs->id,
        ];
        $this->store($others);
        $answer = reportengine::rows($this->report(), []);
        $this->assertSame(2, $answer['total']);
        $counts = $this->counts($answer, 'submission:status');
        $this->assertSame([2], array_values(array_filter($counts)));
        $this->assert_counts_are_what_choosing_lists($others, 'submission:status', $counts, static fn(string $value): array => [
            'submission:status_operator' => select::EQUAL_TO,
            'submission:status_value' => $value,
        ]);

        // The scope: under "awaiting" only the open ones count, the pending one alone.
        $this->store([]);
        $answer = reportengine::rows($this->report(), ['scope' => 'awaiting']);
        $this->assertSame(1, $answer['total']);
        $this->assertSame(1, array_sum($this->counts($answer, 'submission:status')));
        $this->assertSame(1, $this->counts($answer, 'submission:status')[(string) submission::STATUS_PENDING]);

        // The search: one applicant's name, one count.
        $answer = reportengine::rows($this->report(), ['search' => 'carl']);
        $this->assertSame(1, array_sum($this->counts($answer, 'submission:status')));
        $this->assertSame(1, $this->counts($answer, 'submission:status')[(string) submission::STATUS_APPROVED]);
    }

    /**
     * Each group counts the records the group filter lists for it, a record joined to a second group
     * counting in both; the groupings name the offered groups.
     *
     * Changes that must make it fail: the group facet counting by the key group alone (the joined
     * groups ignored), or by a predicate of its own the filter does not apply.
     *
     * @return void
     */
    public function test_each_group_counts_what_choosing_that_group_lists(): void {
        $this->setAdminUser();
        $answer = reportengine::rows($this->report(), []);
        $counts = $this->counts($answer, 'submission:group');
        $this->assertSame(4, $counts[(string) $this->mine->id]);
        $this->assertSame(2, $counts[(string) $this->theirs->id]);
        $this->assert_counts_are_what_choosing_lists([], 'submission:group', $counts, static fn(string $value): array => [
            'submission:group_operator' => select::EQUAL_TO,
            'submission:group_value' => $value,
        ]);

        $groupings = $this->filter($answer, 'submission:group')['groupings'];
        $this->assertSame(['North', 'South'], array_column($groupings, 'name'));
        $this->assertSame([(int) $this->theirs->id], $groupings[1]['groups']);
    }

    /**
     * The group facet's column predicate answers as the filter's listed one for every record and group.
     *
     * @return void
     */
    public function test_the_column_predicate_agrees_with_the_listed_one(): void {
        global $DB;

        foreach ([$this->mine->id, $this->theirs->id] as $groupid) {
            [$listed, $params] = groupscope::record_group_where('r', [(int) $groupid], 'tcp');
            $byid = $DB->get_fieldset_sql("SELECT r.id FROM {enrol_apply_submission} r WHERE {$listed} ORDER BY r.id", $params);
            $column = groupscope::record_group_column_where('r', 'g.id');
            $bycolumn = $DB->get_fieldset_sql(
                "SELECT r.id FROM {enrol_apply_submission} r JOIN {groups} g ON g.id = :g AND {$column} ORDER BY r.id",
                ['g' => $groupid]
            );
            $this->assertNotEmpty($byid);
            $this->assertSame($byid, $bycolumn);
        }
    }

    /**
     * "Waited more than": each threshold counts what core's duration filter lists at that many days,
     * for the measure's filter, and the other measure's facet is counted too.
     *
     * Changes that must make it fail: a threshold compared otherwise than core's filter compares it.
     *
     * @return void
     */
    public function test_each_wait_threshold_counts_what_choosing_it_lists(): void {
        $this->setAdminUser();
        $answer = reportengine::rows($this->report(), []);
        $waited = $this->filter($answer, 'submission:waittime');
        $this->assertSame('waited', $waited['facet']);
        $this->assertSame(['1', '3', '7', '14'], array_column($waited['options'], 'value'));
        $counts = $this->counts($answer, 'submission:waittime');
        // Pending four days and the legacy approval one day to the day.
        $this->assertSame(['1' => 2, '3' => 1, '7' => 0, '14' => 0], $counts);
        $this->assert_counts_are_what_choosing_lists([], 'submission:waittime', $counts, static fn(string $value): array => [
            'submission:waittime_operator' => duration::DURATION_MINIMUM,
            'submission:waittime_value' => $value,
            'submission:waittime_unit' => DAYSECS,
        ]);
        $this->assertSame('waited', $this->filter($answer, 'submission:resolutiontime')['facet']);
    }

    /**
     * "Decided by" names the people who decided, never a key admission's applicant, and each count is
     * what core's text filter lists for that name.
     *
     * Changes that must make it fail: a key admission counted as its applicant's decision (AR-D5).
     *
     * @return void
     */
    public function test_the_decider_facet_counts_what_choosing_a_decider_lists(): void {
        $this->setAdminUser();
        $answer = reportengine::rows($this->report(), []);
        $decider = $this->filter($answer, reportengine::DECIDER_FILTER);
        $this->assertSame('decider', $decider['facet']);
        $counts = $this->counts($answer, reportengine::DECIDER_FILTER);
        $this->assertSame([fullname($this->maria) => 2, fullname($this->zeca) => 2], $counts);
        $this->assert_counts_are_what_choosing_lists([], 'decider', $counts, static fn(string $value): array => [
            reportengine::DECIDER_FILTER . '_operator' => text::IS_EQUAL_TO,
            reportengine::DECIDER_FILTER . '_value' => $value,
        ]);
    }

    /**
     * Without the facets asked for, nothing is counted: every option says -1, the decider facet offers
     * nothing, no grouping is read; the rows are the same.
     *
     * @return void
     */
    public function test_a_read_without_facets_counts_nothing(): void {
        $this->setAdminUser();
        $answer = reportengine::rows($this->report(), ['facets' => false]);
        $this->assertFalse($answer['counted']);
        $this->assertSame(6, $answer['total']);
        $this->assertSame([-1], array_values(array_unique($this->counts($answer, 'submission:status'))));
        $this->assertSame([], $this->filter($answer, reportengine::DECIDER_FILTER)['options']);
        $this->assertSame([], $this->filter($answer, 'submission:group')['groupings']);
    }

    /**
     * The WHERE without a filter no stored value applies is the table's own: the counts and the rows
     * read the same rows.
     *
     * Changes that must make it fail: the rebuilt WHERE leaving out the base condition, a stored filter
     * or an appended condition.
     *
     * @return void
     */
    public function test_the_where_without_no_filter_is_the_tables_own(): void {
        global $DB;

        $this->setUser($this->reader());
        $this->store([
            'submission:status_operator' => select::EQUAL_TO,
            'submission:status_value' => submission::STATUS_APPROVED,
        ]);
        $report = $this->report();
        $table = headless_table::create((int) $report->get_report_persistent()->get('id'), $report->get_parameters());
        $table->narrow(reportengine::open_sql($table->get_report()->submission_alias(), 'first') . ' OR 1=1', []);
        $table->narrow("NOT (" . reportengine::open_sql($table->get_report()->submission_alias(), 'first') . ")", []);

        [$where, $params] = $table->where_without('no:such');
        $sql = "SELECT COUNT(1) FROM {$table->sql->from} WHERE ";
        $own = $DB->count_records_sql($sql . $table->sql->where, $table->sql->params);
        $this->assertSame(2, $own);
        $this->assertSame($own, $DB->count_records_sql($sql . $where, array_merge($table->sql->params, $params)));
        [$where, $params] = $table->where_without('submission:status');
        $this->assertSame(3, $DB->count_records_sql($sql . $where, array_merge($table->sql->params, $params)));
    }

    /**
     * A Columns set is the reader's choice of its keys, and is in force exactly when the report shows
     * that set.
     *
     * @return void
     */
    public function test_a_column_set_is_in_force_when_the_report_shows_it(): void {
        $this->setAdminUser();
        $presets = array_column(reportengine::rows($this->report(), [])['presets'], null, 'name');
        $this->assertSame(['decisions', 'answers', 'groups'], array_keys($presets));
        $this->assertFalse($presets['decisions']['active']);

        reportpreference::write(reportengine::PRESETS['decisions'], []);
        $answer = reportengine::rows($this->report(), []);
        $presets = array_column($answer['presets'], 'active', 'name');
        $this->assertSame(['decisions' => true, 'answers' => false, 'groups' => false], $presets);
        // One method: the set's "method" adds nothing here, as a hand choice of it would not.
        $shown = array_column(array_filter($answer['columns'], static fn(array $c): bool => $c['active'] && !$c['fixed']), 'key');
        $this->assertSame(['outcome', 'submitted', 'wait', 'decided', 'group'], $shown);

        reportpreference::write(reportengine::PRESETS['groups'], []);
        $this->assertSame(
            ['decisions' => false, 'answers' => false, 'groups' => true],
            array_column(reportengine::rows($this->report(), [])['presets'], 'active', 'name')
        );
    }

    /**
     * The rows a download lists: the report rebuilt from its parameters, as core's download.php
     * rebuilds it.
     *
     * @param array $parameters The report's parameters.
     * @return array Applicant ids, sorted.
     */
    private function downloaded(array $parameters): array {
        $persistent = (int) $this->report()->get_report_persistent()->get('id');
        \core_reportbuilder\manager::reset_caches();
        $table = headless_table::create($persistent, $parameters);
        $table->guess_base_url();
        $table->setup();
        $table->query_db(1000, false);
        $records = $table->rawdata;
        $table->close_recordset();

        return $this->userids($records);
    }

    /**
     * The download lists the rows in view: the report applies the scope and the search from its own
     * parameters, with the rows call's SQL, for either measure.
     *
     * Changes that must make it fail: the report not reading the scope or the search from its
     * parameters (the download lists every row).
     *
     * @return void
     */
    public function test_the_download_lists_the_rows_of_the_scope_and_the_search(): void {
        $this->setAdminUser();
        $cases = [
            ['measure' => 'first', 'scope' => 'decided', 'search' => 'Applicant'],
            ['measure' => 'resolution', 'scope' => 'awaiting', 'search' => 'Applicant'],
            ['measure' => 'first', 'scope' => 'all', 'search' => 'arl'],
        ];
        $expected = [5, 2, 1];
        foreach ($cases as $index => $case) {
            $rows = reportengine::rows($this->report($case['measure']), $case);
            $this->assertSame($expected[$index], $rows['total']);
            $this->assertSame($this->userids($rows['rows']), $this->downloaded($case));
        }
        $this->assertCount(6, $this->downloaded(['measure' => 'first']));
    }

    /**
     * A parameter only narrows: a scope outside the vocabulary and a search too short read as none,
     * and a narrowed reader's download is their own rows whatever it asks.
     *
     * Changes that must make it fail: the scope not read through the vocabulary (an unknown one would
     * read as "decided"); the base conditions replaced by the parameters.
     *
     * @return void
     */
    public function test_a_download_parameter_only_narrows(): void {
        $this->setAdminUser();
        $this->assertCount(6, $this->downloaded(['measure' => 'first', 'scope' => 'everything']));
        $this->assertCount(6, $this->downloaded(['measure' => 'first', 'search' => 'a']));

        $this->setUser($this->reader());
        $this->assertCount(4, $this->downloaded(['measure' => 'first', 'scope' => 'all']));
        $this->assertCount(3, $this->downloaded(['measure' => 'first', 'scope' => 'decided']));
    }
}
