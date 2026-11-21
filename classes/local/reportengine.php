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

use context_course;
use core_reportbuilder\local\helpers\database;
use enrol_apply\reportbuilder\local\systemreports\course_applications;
use enrol_apply\reportbuilder\local\systemreports\headless_table;
use moodle_url;
use stdClass;

/**
 * The course report read headlessly: its rows and its turnaround summary (stage R3).
 *
 * The system report stays the one definition of rows, columns, access and filters; this class only
 * reads it. Both answers start from the table core itself builds for the report
 * ({@see headless_table}), so they carry the report's base conditions (the course, the
 * separate-groups narrowing, S8), the reader's stored filters and its columns as the reader chose
 * them. Two conditions are added to that table's SQL and nothing else: the scope (awaiting or
 * decided, for the wait being measured) and the search by the applicant's name (AR-D15). Both can
 * only narrow it. The summary reads the same table's FROM and WHERE, so its counts are the rows'
 * by construction.
 *
 * Nothing is cached and nothing is node-local: every answer is computed per request from the
 * database, with "now" from core's clock.
 *
 * Core keeps one instance of a report per user for the whole request
 * (\core_reportbuilder\manager::get_report_from_persistent()), and core/ajax sends calls made
 * together in one request. So the report's columns and its measure are those of the first call of a
 * request that builds it: the rows echo the measure they used, the summary takes its measure from
 * its own arguments and never from the report, and the preference's writer does not build the
 * report at all.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reportengine {
    /** @var int[] The page sizes a reader may choose (AR-D14); anything else reads as the first. */
    public const PERPAGES = [30, 50, 100];

    /** @var string[] Which rows: every one, those still awaiting what the wait measures, the others. */
    public const SCOPES = ['all', 'awaiting', 'decided'];

    /** @var int A search shorter than this many characters matches every row, as the client sends none. */
    public const SEARCH_MIN = 2;

    /** @var int Seconds after which an open application counts as late in the summary. */
    public const LATE = 7 * DAYSECS;

    /** @var int[] Lower bounds, in days, of the wait histogram's buckets; the last is open-ended. */
    public const BUCKETS = [0, 1, 3, 7, 14];

    /** @var int The group bucket of the records whose group the reader may not see named. */
    public const GROUP_WITHHELD = -1;

    /**
     * The apply method an id names, and its course's context.
     *
     * The course comes from the enrol instance, never from the client (section 5 of the study).
     *
     * @param int $enrolid The enrol instance.
     * @return array [the instance record, its course context].
     * @throws \dml_missing_record_exception When the id names no apply method.
     */
    public static function locate(int $enrolid): array {
        global $DB;

        $instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'apply'], '*', MUST_EXIST);

        return [$instance, context_course::instance((int) $instance->courseid)];
    }

    /**
     * The course report for this method, refused to a reader it would refuse.
     *
     * @param stdClass $instance The apply method.
     * @param string $measure What the wait measures: 'first' or 'resolution'.
     * @return course_applications The report.
     * @throws \core_reportbuilder\exception\report_access_exception When the report's can_view() refuses the current user.
     */
    public static function report(stdClass $instance, string $measure): course_applications {
        $context = context_course::instance((int) $instance->courseid);

        /* Core's system_report runs require_can_view() while it is built (validate()), so no
           instance exists for a reader the report refuses, and the readers below need no check of
           their own: whatever built the report they are handed passed it. */
        return course_applications::for_method($context, (int) $instance->id, ['measure' => self::measure($measure)]);
    }

    /**
     * The measure, as the report reads it.
     *
     * @param string $measure The requested measure.
     * @return string 'first' or 'resolution'.
     */
    public static function measure(string $measure): string {
        return in_array($measure, course_applications::MEASURES, true) ? $measure : 'first';
    }

    /**
     * The scope, cleaned.
     *
     * @param string $scope The requested scope.
     * @return string One of SCOPES; anything else reads as 'all'.
     */
    public static function scope(string $scope): string {
        return in_array($scope, self::SCOPES, true) ? $scope : 'all';
    }

    /**
     * The search as it will be applied: trimmed, and empty when too short to search with.
     *
     * @param string $search The requested search.
     * @return string The search, or ''.
     */
    public static function search(string $search): string {
        $search = trim($search);

        return \core_text::strlen($search) >= self::SEARCH_MIN ? $search : '';
    }

    /**
     * SQL that is true of a record still awaiting what the wait measures.
     *
     * The first response: no decision yet. The resolution: no approval or cancellation yet, so a
     * deferred record is open. The same split the entity's wait_sql() and resolution_sql() make
     * between "to a decision" and "to now", and the one {@see is_open()} applies per row; the two
     * must agree (test_the_scope_and_the_summary_agree_on_what_is_open).
     *
     * @param string $alias The submission table's alias.
     * @param string $measure 'first' or 'resolution'.
     * @return string The condition; integers only, so no parameters.
     */
    public static function open_sql(string $alias, string $measure): string {
        if ($measure === 'resolution') {
            $settled = submission::STATUS_APPROVED . ', ' . submission::STATUS_CANCELLED;

            return "NOT ({$alias}.status IN ({$settled}) AND {$alias}.timedecided > 0)";
        }

        return "{$alias}.timedecided = 0";
    }

    /**
     * Whether a record is still awaiting what the wait measures; {@see open_sql()} per row.
     *
     * @param stdClass $record The record: status and timedecided.
     * @param string $measure 'first' or 'resolution'.
     * @return bool True while open.
     */
    public static function is_open(stdClass $record, string $measure): bool {
        if ($measure === 'resolution') {
            $status = (int) $record->status;
            $settled = $status === submission::STATUS_APPROVED || $status === submission::STATUS_CANCELLED;

            return !($settled && (int) $record->timedecided > 0);
        }

        return (int) $record->timedecided === 0;
    }

    /**
     * How many seconds a record waited for what is measured, to now while it is open.
     *
     * The entity's wait_sql() and resolution_sql() in PHP, so a client computes with the same
     * number the column sorts and filters by. Never negative: a restored record may carry a
     * decision older than its submission.
     *
     * @param stdClass $record The record: status, timecreated, timedecided and, once the schema has
     *     it, timefirstdecided.
     * @param string $measure 'first' or 'resolution'.
     * @param int $now The clock's time.
     * @return int Seconds.
     */
    public static function wait(stdClass $record, string $measure, int $now): int {
        $created = (int) $record->timecreated;
        if (self::is_open($record, $measure)) {
            return max(0, $now - $created);
        }
        $first = (int) ($record->timefirstdecided ?? 0);
        $end = $measure === 'first' && $first > 0 ? $first : (int) $record->timedecided;

        return max(0, $end - $created);
    }

    /**
     * Whether a person took the record's decision: a decider who is not the applicant.
     *
     * Key admissions (the applicant is the recorded decider, {@see submission::admitted_by_key()})
     * and records without a decider are out of the turnaround metrics (AR-D5).
     *
     * @param stdClass $record The record: userid and decidedby.
     * @return bool True for a decision by a person.
     */
    public static function decided_by_person(stdClass $record): bool {
        $decidedby = (int) $record->decidedby;

        return $decidedby > 0 && $decidedby !== (int) $record->userid;
    }

    /**
     * A page of the report's rows, with what a client needs to draw them.
     *
     * The cells are the report's own formatted values (format_row(), where masking and escaping
     * already ran) for the columns the report shows this reader, and nothing more; beside them the
     * record's raw facts for the few things a client computes (the wait, whether it is open, the
     * Review link the report's own row action would draw). The sort names a catalogue key; one the
     * report does not show, or cannot sort by, leaves the report's own sort.
     *
     * @param course_applications $report The report, as {@see report()} returned it.
     * @param array $options 'scope', 'search', 'page' (0-based), 'perpage', 'sort' (a
     *     column key), 'direction' ('asc' or 'desc').
     * @return array The rows, the total, the columns, the filters and the reader's grid; see
     *     \enrol_apply\external\report_rows::execute_returns().
     */
    public static function rows(course_applications $report, array $options): array {
        $measure = $report->measure();
        $scope = self::scope((string) ($options['scope'] ?? 'all'));
        $search = self::search((string) ($options['search'] ?? ''));
        $perpage = in_array((int) ($options['perpage'] ?? 0), self::PERPAGES, true) ? (int) $options['perpage'] : self::PERPAGES[0];
        $page = max(0, (int) ($options['page'] ?? 0));
        $direction = ($options['direction'] ?? '') === 'asc' ? SORT_ASC : SORT_DESC;

        $table = self::table($report, $measure, $scope, $search);
        /** @var course_applications $tablereport */
        $tablereport = $table->get_report();
        $columns = $tablereport->get_active_columns();
        $keys = $tablereport->active_keys();

        $sort = (string) ($options['sort'] ?? '');
        $sorted = '';
        if (isset($keys[$sort], $columns[$keys[$sort]]) && $columns[$keys[$sort]]->get_is_sortable()) {
            $table->set_sortdata([['sortby' => $columns[$keys[$sort]]->get_column_alias(), 'sortorder' => $direction]]);
            $sorted = $sort;
        }
        $table->guess_base_url();
        $table->setup();

        [$records, $total] = self::page($table, $page, $perpage);
        if ($records === [] && $page > 0) {
            // The counted query counts nothing past the last row, so count again.
            $total = $table->get_total_row_count();
        }
        if ($records === [] && $total > 0 && $page > 0) {
            // Past the last page (a filter shrank the rows): the last page, as a pager would show.
            $page = intdiv($total - 1, $perpage);
            $table = self::table($report, $measure, $scope, $search);
            if ($sorted !== '') {
                $tablecolumns = $table->get_report()->get_active_columns();
                $table->set_sortdata([['sortby' => $tablecolumns[$keys[$sorted]]->get_column_alias(), 'sortorder' => $direction]]);
            }
            $table->guess_base_url();
            $table->setup();
            [$records, $total] = self::page($table, $page, $perpage);
            $tablereport = $table->get_report();
            $columns = $tablereport->get_active_columns();
        }

        $aliases = [];
        foreach ($keys as $key => $identifier) {
            $aliases[$key] = $columns[$identifier]->get_column_alias();
        }

        $now = \core\di::get(\core\clock::class)->time();
        $rows = [];
        foreach ($records as $record) {
            $formatted = $table->format_row($record);
            $cells = [];
            foreach ($aliases as $key => $alias) {
                $cells[] = ['key' => $key, 'html' => (string) ($formatted[$alias] ?? '')];
            }
            $ueid = (int) $record->userenrolmentid;
            $rows[] = [
                'id' => (int) $record->id,
                'userid' => (int) $record->userid,
                'userenrolmentid' => $ueid,
                'status' => (int) $record->status,
                'timecreated' => (int) $record->timecreated,
                'timedecided' => (int) $record->timedecided,
                'timefirstdecided' => (int) ($record->timefirstdecided ?? 0),
                'decidedbyperson' => self::decided_by_person($record),
                'open' => self::is_open($record, $measure),
                'wait' => self::wait($record, $measure, $now),
                'reviewurl' => $tablereport->can_review($ueid)
                    ? (new moodle_url('/enrol/apply/manage.php', ['userenrol' => $ueid]))->out(false)
                    : '',
                'cells' => $cells,
            ];
        }

        $narrowed = $tablereport->narrowed_groups();

        return [
            'measure' => $measure,
            'scope' => $scope,
            'search' => $search,
            'sort' => $sorted,
            'direction' => $sorted === '' ? '' : ($direction === SORT_ASC ? 'asc' : 'desc'),
            'page' => $page,
            'perpage' => $perpage,
            'total' => $total,
            'narrowed' => $narrowed !== null,
            'notingroup' => $narrowed === [],
            'columns' => self::columns($tablereport),
            'rows' => $rows,
            'filters' => self::filters($tablereport),
            'preference' => self::preference(),
        ];
    }

    /**
     * The turnaround summary of the rows the same request would list (AR-D5, AR-D17).
     *
     * One query over the report table's own FROM and WHERE, the scope and the search included, so
     * the summary cannot count a row the reader's table would not show (section 5 of the study).
     * Each record is open (awaiting what is measured), decided by a person (its wait goes into the
     * median), or decided without one (a key admission, or no decider): the three counts add up to
     * the table's total. Percentiles are computed here, over the decided waits, because the DML
     * layer has no portable percentile.
     *
     * A reader the separate-groups rule narrows gets the compact band (AR-D17): the median, what is
     * still open and the decisions without a person; no 90th percentile, distribution or breakdown.
     *
     * @param course_applications $report The report, as {@see report()} returned it.
     * @param array $options 'measure' (read here, not from the report), 'scope', 'search'.
     * @return array The metrics; see \enrol_apply\external\report_summary::execute_returns().
     */
    public static function summary(course_applications $report, array $options): array {
        global $DB;

        $measure = self::measure((string) ($options['measure'] ?? 'first'));
        $scope = self::scope((string) ($options['scope'] ?? 'all'));
        $search = self::search((string) ($options['search'] ?? ''));
        $table = self::table($report, $measure, $scope, $search);
        /** @var course_applications $tablereport */
        $tablereport = $table->get_report();
        $alias = $tablereport->submission_alias();
        $compact = $tablereport->narrowed_groups() !== null;

        $fields = "{$alias}.id, {$alias}.userid, {$alias}.status, {$alias}.timecreated, {$alias}.timedecided,"
            . " {$alias}.decidedby, {$alias}.enrolid, {$alias}.keyoutcome, {$alias}.keygroupid";
        $chosen = instanceoptions::schema_ready();
        if ($chosen) {
            $fields .= ", {$alias}.chosengroupid";
        }
        if (enrol_get_plugin('apply')->first_response_schema_ready()) {
            $fields .= ", {$alias}.timefirstdecided";
        }
        $sql = "SELECT {$fields} FROM {$table->sql->from} WHERE {$table->sql->where}";

        $now = \core\di::get(\core\clock::class)->time();
        $total = $open = $withoutperson = $late = 0;
        $oldest = null;
        $waits = [];
        $bymethod = [];
        $bygroup = [];
        $seen = [];
        $recordset = $DB->get_recordset_sql($sql, $table->sql->params);
        foreach ($recordset as $record) {
            // Each joined table is one row per record, so a repeat would be a join's defect, not a row.
            if (isset($seen[$record->id])) {
                continue;
            }
            $seen[$record->id] = true;
            $total++;
            if (self::is_open($record, $measure)) {
                $open++;
                $age = max(0, $now - (int) $record->timecreated);
                $oldest = max($oldest ?? 0, $age);
                if ($age > self::LATE) {
                    $late++;
                }
                continue;
            }
            if (!self::decided_by_person($record)) {
                $withoutperson++;
                continue;
            }
            $wait = self::wait($record, $measure, $now);
            $waits[] = $wait;
            $bymethod[(int) $record->enrolid][] = $wait;
            $group = (int) $record->keyoutcome !== submission::KEY_NONE
                ? max(0, (int) $record->keygroupid)
                : ($chosen ? max(0, (int) $record->chosengroupid) : 0);
            $bygroup[$group][] = $wait;
        }
        $recordset->close();

        sort($waits);
        $summary = [
            'measure' => $measure,
            'scope' => $scope,
            'search' => $search,
            'compact' => $compact,
            'total' => $total,
            'decided' => count($waits),
            'open' => $open,
            'withoutperson' => $withoutperson,
            'late' => $late,
            'oldest' => $oldest,
            'median' => self::percentile($waits, 0.5),
            'p90' => null,
            'buckets' => [],
            'bymethod' => [],
            'bygroup' => [],
        ];
        if ($compact) {
            return $summary;
        }

        $summary['p90'] = self::percentile($waits, 0.9);
        foreach (self::BUCKETS as $index => $from) {
            $to = self::BUCKETS[$index + 1] ?? null;
            $summary['buckets'][] = [
                'fromdays' => $from,
                'todays' => $to,
                'count' => count(array_filter(
                    $waits,
                    static fn(int $wait): bool => $wait >= $from * DAYSECS && ($to === null || $wait < $to * DAYSECS)
                )),
            ];
        }
        $summary['bymethod'] = self::breakdown($bymethod, self::method_names($tablereport));
        [$bygroup, $groupnames] = self::group_buckets($tablereport, $bygroup);
        $summary['bygroup'] = self::breakdown($bygroup, $groupnames);

        return $summary;
    }

    /**
     * Linear interpolation between the closest ranks, as the mockup computes it.
     *
     * @param int[] $sorted Waits, ascending.
     * @param float $fraction 0.5 for the median, 0.9 for the 90th percentile.
     * @return int|null Seconds, rounded; null with no wait to measure.
     */
    public static function percentile(array $sorted, float $fraction): ?int {
        if ($sorted === []) {
            return null;
        }
        $position = (count($sorted) - 1) * $fraction;
        $low = (int) floor($position);
        $high = (int) ceil($position);

        return (int) round($sorted[$low] + ($sorted[$high] - $sorted[$low]) * ($position - $low));
    }

    /**
     * The report's table, with the scope and the search appended to its conditions.
     *
     * Built from the report's persistent and parameters, as core builds it for a page turn; the
     * conditions use that table's report's aliases, since each instance generates its own.
     *
     * @param course_applications $report The report.
     * @param string $measure A cleaned measure, which the scope reads.
     * @param string $scope A cleaned scope.
     * @param string $search A cleaned search, '' for none.
     * @return headless_table The table, not yet set up.
     */
    protected static function table(course_applications $report, string $measure, string $scope, string $search): headless_table {
        global $DB;

        /** @var headless_table $table */
        $table = headless_table::create((int) $report->get_report_persistent()->get('id'), $report->get_parameters());
        /** @var course_applications $tablereport */
        $tablereport = $table->get_report();
        $alias = $tablereport->submission_alias();

        if ($scope !== 'all') {
            $open = self::open_sql($alias, $measure);
            $table->narrow($scope === 'awaiting' ? $open : "NOT ({$open})", []);
        }

        /* The applicant's name as core's text filter on it matches (reportbuilder's text filter,
           CONTAINS): the report's own fullname filter's field SQL, case and accent insensitive. A
           parameter of the request, never a stored filter, so a keystroke writes nothing. Its
           parameters renamed with a prefix of this class's, so they cannot meet the same filter's
           when the reader also applied it. */
        if ($search !== '') {
            $filter = $tablereport->get_filter('user:fullname');
            [$fieldsql, $fieldparams] = database::sql_replace_parameters(
                $filter->get_field_sql(),
                $filter->get_field_params(),
                static fn(string $name): string => "hs_{$name}"
            );
            $param = database::generate_param_name();
            $table->narrow(
                $DB->sql_like($fieldsql, ":{$param}", false, false),
                $fieldparams + [$param => '%' . $DB->sql_like_escape($search) . '%']
            );
        }

        return $table;
    }

    /**
     * Query one page of a set-up table.
     *
     * @param headless_table $table The table, set up.
     * @param int $page 0-based page.
     * @param int $perpage Rows per page.
     * @return array [the records, the total].
     */
    protected static function page(headless_table $table, int $page, int $perpage): array {
        $table->set_page_number($page + 1);
        $table->query_db($perpage, false);
        $records = [];
        foreach ($table->rawdata as $record) {
            $records[] = $record;
        }
        $table->close_recordset();

        return [$records, (int) $table->totalrows];
    }

    /**
     * The columns the reader sees and the ones they may choose, in display order.
     *
     * The applicant and the identity fields first (fixed), then the catalogue. Titles in the plain
     * spelling: the client renders them as text.
     *
     * @param course_applications $report The table's report.
     * @return array Each: key, title, sortable, default, active, fixed.
     */
    protected static function columns(course_applications $report): array {
        $active = $report->get_active_columns();
        $keys = $report->active_keys();
        $columns = [];
        foreach ($keys as $key => $identifier) {
            if ($key !== 'applicant' && !str_starts_with($key, 'identity:')) {
                continue;
            }
            $columns[] = [
                'key' => $key,
                'title' => $key === 'applicant' ? get_string('reportapplicant', 'enrol_apply') : $active[$identifier]->get_title(),
                'sortable' => $active[$identifier]->get_is_sortable(),
                'default' => true,
                'active' => true,
                'fixed' => true,
            ];
        }
        foreach ($report->column_catalogue() as $key => $entry) {
            $columns[] = [
                'key' => $key,
                'title' => $entry['title'],
                'sortable' => isset($keys[$key]) ? $active[$keys[$key]]->get_is_sortable() : $entry['sortable'],
                'default' => $entry['default'],
                'active' => isset($keys[$key]),
                'fixed' => false,
            ];
        }

        return $columns;
    }

    /**
     * The report's filters as a client draws them: what each is, what it offers, whether it applies.
     *
     * Built from the report's own active filters, so a panel cannot offer a filter the report
     * would not apply; "applied" is the filter class's own answer for the reader's stored values.
     * Option labels come back in the plain spelling: the report's own options are the escaped one
     * its form wants.
     *
     * @param course_applications $report The table's report.
     * @return array Each: name, title, type, options (value, label), applied, values (JSON).
     */
    protected static function filters(course_applications $report): array {
        $values = $report->get_filter_values();
        $filters = [];
        foreach ($report->get_active_filters() as $identifier => $filter) {
            $class = $filter->get_filter_class();
            $own = [];
            foreach ($values as $name => $value) {
                if (str_starts_with($name, $identifier . '_')) {
                    $own[$name] = $value;
                }
            }
            $options = [];
            $offered = $filter->get_options();
            if (is_array($offered)) {
                foreach ($offered as $value => $label) {
                    $options[] = [
                        'value' => (string) $value,
                        'label' => html_entity_decode((string) $label, ENT_QUOTES | ENT_HTML401, 'UTF-8'),
                    ];
                }
            }
            [$sql] = $class::create($filter)->get_sql_filter($values);
            $filters[] = [
                'name' => $identifier,
                'title' => $filter->get_header(),
                'type' => (new \ReflectionClass($class))->getShortName(),
                'options' => $options,
                'applied' => $sql !== '',
                'values' => json_encode((object) $own),
            ];
        }

        return $filters;
    }

    /**
     * The reader's grid, for the client to draw the columns menu and the widths.
     *
     * @return array 'usedefault', 'columns' (keys), 'widths' (key, width).
     */
    protected static function preference(): array {
        $stored = reportpreference::read();
        $widths = [];
        foreach ($stored['widths'] as $key => $width) {
            $widths[] = ['key' => $key, 'width' => $width];
        }

        return [
            'usedefault' => $stored['columns'] === null,
            'columns' => $stored['columns'] ?? [],
            'widths' => $widths,
        ];
    }

    /**
     * One breakdown's rows: the median and the count of each key's decided waits, by name.
     *
     * @param array $waits Key => waits.
     * @param array $names Key => plain name.
     * @return array Each: id, name, median, count; sorted by name, naturally.
     */
    protected static function breakdown(array $waits, array $names): array {
        $rows = [];
        foreach ($waits as $id => $list) {
            sort($list);
            $rows[] = [
                'id' => (int) $id,
                'name' => $names[$id] ?? '',
                'median' => self::percentile($list, 0.5),
                'count' => count($list),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * The plain names of the course's apply methods, as get_instance_name() words them.
     *
     * @param course_applications $report The report.
     * @return array Enrol instance id => plain name.
     */
    protected static function method_names(course_applications $report): array {
        global $DB;

        $context = $report->get_context();
        $plugin = enrol_get_plugin('apply');
        $names = [];
        foreach ($DB->get_records('enrol', ['courseid' => $context->instanceid, 'enrol' => 'apply']) as $id => $instance) {
            $names[(int) $id] = trim((string) $instance->name) !== ''
                ? format_string($instance->name, true, ['context' => $context, 'escape' => false])
                : $plugin->get_instance_name($instance);
        }

        return $names;
    }

    /**
     * The group breakdown's buckets and their names, as this reader may be told them.
     *
     * A group that no longer exists reads as no group, as the queue's group filter files it. A group
     * the reader may not see named ({@see groupvisibility::visible_ids()}) is merged with every
     * other such group into GROUP_WITHHELD, so the breakdown says neither its name nor how many
     * hidden groups there are.
     *
     * @param course_applications $report The report.
     * @param array $waits Group id (0 for none) => waits.
     * @return array [bucket id => waits, bucket id => plain name].
     */
    protected static function group_buckets(course_applications $report, array $waits): array {
        global $DB, $USER;

        $context = $report->get_context();
        $ids = array_values(array_filter(array_keys($waits), static fn(int $id): bool => $id > 0));
        $groups = [];
        if ($ids !== []) {
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $params['courseid'] = $context->instanceid;
            $groups = $DB->get_records_select(
                'groups',
                "id {$insql} AND courseid = :courseid",
                $params,
                '',
                'id, courseid, name, visibility'
            );
        }
        $visible = array_flip(groupvisibility::visible_ids($groups, (int) $USER->id));

        $buckets = [];
        $names = [];
        foreach ($waits as $id => $list) {
            if ($id > 0 && isset($groups[$id], $visible[$id])) {
                $bucket = $id;
                $names[$bucket] = format_string($groups[$id]->name, true, ['context' => $context, 'escape' => false]);
            } else if ($id > 0 && isset($groups[$id])) {
                $bucket = self::GROUP_WITHHELD;
                $names[$bucket] = get_string('reportgroupwithheld', 'enrol_apply');
            } else {
                $bucket = 0;
                $names[$bucket] = get_string('reportnogroup', 'enrol_apply');
            }
            $buckets[$bucket] = array_merge($buckets[$bucket] ?? [], $list);
        }

        return [$buckets, $names];
    }
}
