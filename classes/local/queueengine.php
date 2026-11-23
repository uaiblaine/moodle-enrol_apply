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
use enrol_apply\reportbuilder\local\formatters\submission as formatter;
use enrol_apply\table\applications;
use enrol_apply\table\applications_filterset;
use moodle_url;
use stdClass;
use user_picture;

/**
 * The approval queue of one enrolment method, read headlessly (stage 1 of the queue as a report).
 *
 * The one reader of the queue for a client. It never writes a query of its own over what the queue
 * lists: it builds the queue's own table ({@see applications::for_scope()}), so the scope
 * ({@see queue::listing_scope()}: the method, "awaiting a decision", the separate-groups narrowing)
 * and every operator filter are the page's, and then reads that table's FROM and WHERE:
 *
 * - the page: the table's SELECT, FROM and WHERE, ordered by the sort asked for and ending in the
 *   unique key the table ends in (ue.id), in one statement; the cells' facts are then read by the
 *   table's own {@see applications::prepare_rows()}, in a bounded number of statements;
 * - the counts: the facets' and the scopes', one statement per distinct WHERE
 *   ({@see applications::where_without()}): every facet with no value of its own in force shares
 *   the listing's own WHERE, and a facet whose value is applied is counted without it, so each count
 *   is what choosing that option would list. Each statement groups the rows by every facet's value
 *   in the database and returns a handful of tallies, never the rows. The total of the page is the
 *   count of its scope, read there, so the two cannot disagree.
 *
 * So a page costs two statements of its own and one more per facet whose value is applied, plus the
 * table's per-page reads (the answers, the profile links of the page's course, the groups'
 * visibility) and the reads that resolve the reader's scope, whatever the page's size
 * (test_a_page_is_read_in_a_bounded_number_of_statements).
 *
 * Only the queue of ONE method (CQ-D3): the site-wide and the mentee queues keep the server table.
 * Computed per request and never cached: what a reader may see depends on capabilities, groups and
 * the live enrolments, on whichever node.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class queueengine {
    /** @var int[] The page sizes a reader may choose (CQ-D9); anything else reads as the default. */
    public const PERPAGES = [30, 50, 100];

    /** @var int The page size when none of PERPAGES is asked for (MQ-D13). */
    public const PERPAGE = 50;

    /** @var string[] Which applications: every one awaiting a decision, the pending ones, the deferred ones. */
    public const SCOPES = ['all', 'pending', 'deferred'];

    /** @var int Seconds after which an application counts as waiting long, in the summary. */
    public const LATE = 7 * DAYSECS;

    /** @var string The key a preference or a set stores for every answer column the method has. */
    public const ANSWERS = 'answers';

    /** @var string The prefix of an answer column's key, before the field key. */
    public const ANSWER_PREFIX = 'answer:';

    /**
     * @var array The Columns menu's sets (CQ-D7): name => the keys stored as the reader's choice,
     *     ANSWERS standing for every answer column of the method.
     */
    public const PRESETS = [
        'everything' => ['wait', 'group', self::ANSWERS, 'comment'],
        'glance' => ['wait', 'group', 'comment'],
        'answers' => [self::ANSWERS, 'comment'],
    ];

    /** @var int The applicant's picture, in pixels (MQ-D9). */
    public const PICTURE_SIZE = 35;

    /**
     * @var string[] Filters a request may not set through its filter list: the scope sets the
     *     status, the search has a parameter of its own, and the course and category exist only
     *     on the site-wide queue.
     */
    public const NOT_FILTERS = ['search', 'status', 'course', 'category'];

    /**
     * The apply method an id names, and its course's context.
     *
     * The course comes from the method, never from the client. A call naming no method is refused
     * (CQ-D3) by the same lookup: 0, the site-wide scope on the page, names no {enrol} row, so this
     * door does not open that queue.
     *
     * @param int $enrolid The enrol instance.
     * @return array [the instance record, its course context].
     * @throws \dml_missing_record_exception When the id names no apply method, 0 included.
     */
    public static function locate(int $enrolid): array {
        global $DB;

        $instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'apply'], '*', MUST_EXIST);

        return [$instance, context_course::instance((int) $instance->courseid)];
    }

    /**
     * Refuse a reader the queue page would refuse, as manage.php refuses them.
     *
     * {@see queue::listing_scope()} asked exactly as manage.php?id= asks it: allowed is the
     * deciding or the viewing capability in the method's course. Course access is the caller's
     * validate_context(), as it is the page's require_login($course). Nothing the request carries
     * chooses the scope: the id names the method, and the reader's narrowing is derived from the
     * course and $USER.
     *
     * @param int $enrolid The enrol instance, already located.
     * @return stdClass The listing scope.
     * @throws \required_capability_exception When the reader may not open the queue.
     */
    public static function require_listing(int $enrolid): stdClass {
        $listing = queue::listing_scope($enrolid);
        if (!$listing->allowed) {
            throw new \required_capability_exception($listing->context, groupscope::DECIDE, 'nopermissions', '');
        }

        return $listing;
    }

    /**
     * The scope's enrolment status, as the table's status filter takes it.
     *
     * @param string $scope One of SCOPES.
     * @return int|null The status, null for every application.
     */
    public static function status(string $scope): ?int {
        global $CFG;

        // ENROL_APPLY_USER_WAIT lives in the plugin's lib.php, which is not autoloaded.
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        return match ($scope) {
            'pending' => ENROL_USER_SUSPENDED,
            'deferred' => ENROL_APPLY_USER_WAIT,
            default => null,
        };
    }

    /**
     * The queue's table for a request, built as the page builds it.
     *
     * The filters go through the table, which cleans each one against what this reader is offered
     * and ignores the rest; a name the table does not declare, or one of NOT_FILTERS, is dropped
     * here first, because core's filterset throws on an undeclared name.
     *
     * @param int $enrolid The enrol instance.
     * @param string $scope One of SCOPES.
     * @param string $search The search, empty for none.
     * @param array $filters Filter name => raw value, as the page's address carries them.
     * @return applications The table, its SQL built.
     */
    public static function table(int $enrolid, string $scope, string $search, array $filters): applications {
        $declared = (new applications_filterset())->get_optional_filters();
        $clean = [];
        foreach ($filters as $name => $value) {
            $name = (string) $name;
            if (!array_key_exists($name, $declared) || in_array($name, self::NOT_FILTERS, true) || !is_scalar($value)) {
                continue;
            }
            $clean[$name] = (string) $value;
        }

        return applications::for_scope($enrolid, $search, self::status($scope), $clean);
    }

    /**
     * A page of the queue, with what a client needs to draw it.
     *
     * @param int $enrolid The enrol instance; refused to a reader {@see require_listing()} refuses.
     * @param array $options 'scope' (one of SCOPES), 'search', 'filters' (name => value), 'page'
     *     (0-based), 'perpage' (one of PERPAGES), 'sort' (a sortable catalogue key, '' for the
     *     applied date), 'direction' ('asc' or 'desc', of the applied date for 'wait').
     * @return array See \enrol_apply\external\queue_rows::execute_returns().
     * @throws \required_capability_exception When the reader may not open the queue.
     */
    public static function rows(int $enrolid, array $options): array {
        self::require_listing($enrolid);

        $scope = in_array($options['scope'] ?? '', self::SCOPES, true) ? $options['scope'] : 'all';
        $perpage = in_array((int) ($options['perpage'] ?? 0), self::PERPAGES, true) ? (int) $options['perpage'] : self::PERPAGE;
        $page = max(0, (int) ($options['page'] ?? 0));
        $direction = ($options['direction'] ?? '') === 'desc' ? SORT_DESC : SORT_ASC;

        $table = self::table($enrolid, $scope, trim((string) ($options['search'] ?? '')), (array) ($options['filters'] ?? []));
        $catalogue = self::catalogue($table);
        $stored = queuepreference::read();
        $active = self::active_keys($catalogue, $stored['columns']);

        // A key that is no sortable column of this reader's catalogue leaves the default sort.
        $sort = (string) ($options['sort'] ?? '');
        if (!isset($catalogue[$sort]) || !$catalogue[$sort]['sortable']) {
            $sort = 'wait';
            $direction = SORT_ASC;
        }

        $facets = self::facets($table);
        $counts = $facets['scopes'];
        $total = $counts[$scope];
        // Past the last page (a filter shrank the queue): the last page, as a pager would show.
        if ($total > 0 && $page * $perpage >= $total) {
            $page = intdiv($total - 1, $perpage);
        }

        $records = $total > 0 ? self::page($table, $sort, $direction, $page, $perpage) : [];
        $table->prepare_rows($records);

        $now = $table->now();
        $rows = [];
        foreach ($records as $record) {
            $rows[] = self::row($table, $record, $active, $now);
        }

        return [
            'scope' => $scope,
            'search' => $table->get_search(),
            'sort' => $sort,
            'direction' => $direction === SORT_DESC ? 'desc' : 'asc',
            'page' => $page,
            'perpage' => $perpage,
            'total' => $total,
            'counts' => $counts,
            'candecide' => $table->can_decide(),
            'narrowed' => $table->scope_groups() !== null,
            'notingroup' => $table->not_in_any_group(),
            'groupdefault' => (string) ($table->get_group_default() ?? ''),
            'columns' => self::columns($catalogue, $active),
            'presets' => self::presets($catalogue, $active),
            'rows' => $rows,
            'facets' => $facets['facets'],
            'fieldfilters' => self::field_filters($table),
            'filters' => self::applied($table),
            'preference' => queuepreference::export($stored),
        ];
    }

    /**
     * The capacity tiles' figures (CQ-D10): places, applications held, the queue, open or closed.
     *
     * The places and the applications held are the METHOD's, as the page's tiles show them to a
     * narrowed decider too, labelled as the method's; the queue's figures are the reader's scope
     * before any filter ({@see applications::scope_figures()}), so a moderator is told about their
     * own applications. Open or closed is {@see capacity::state()}, the page's own answer.
     *
     * @param stdClass $instance The apply method; refused to a reader {@see require_listing()} refuses.
     * @return array See \enrol_apply\external\queue_summary::execute_returns().
     * @throws \required_capability_exception When the reader may not open the queue.
     */
    public static function summary(stdClass $instance): array {
        self::require_listing((int) $instance->id);

        $table = applications::for_scope((int) $instance->id);
        $now = $table->now();
        $state = capacity::state($instance, $now);
        $figures = $table->scope_figures(self::LATE);

        return [
            'places' => $state->places,
            'placestaken' => $state->taken,
            'applicantlimit' => $state->limit,
            'applicants' => $state->held,
            'awaiting' => $figures['awaiting'],
            'deferred' => $figures['deferred'],
            'oldestwait' => $figures['oldest'] > 0 ? max(0, $now - $figures['oldest']) : 0,
            'late' => $figures['late'],
            'latedays' => (int) (self::LATE / DAYSECS),
            'open' => $state->open,
            'closes' => $state->enddate,
            'narrowed' => $table->scope_groups() !== null,
            'notingroup' => $table->not_in_any_group(),
        ];
    }

    /**
     * The columns this reader may have, in display order: key => title, sortable, default, fixed.
     *
     * The applicant (fixed), the wait (the applied date), the group where the course has groups,
     * one answer column per profile field and question THIS method asks that the reader may see,
     * the comment, and the earlier applications where they are counted (a holder of
     * enrol/apply:viewreports, CQ-D8). The questions are found and masked as the course report's
     * questions() finds and masks them ({@see questions()}), so a key withheld from this reader is
     * no column, and the engine reads no cell, sort or filter for a key that is not one.
     *
     * Titles in the plain spelling: the client renders them as text.
     *
     * @param applications $table The queue's table.
     * @return array Catalogue key => ['title', 'sortable', 'default', 'fixed'].
     * @throws \required_capability_exception When the reader may not open the queue.
     */
    public static function catalogue(applications $table): array {
        $instance = $table->get_instance();
        self::require_listing((int) $instance->id);

        $catalogue = [
            'applicant' => self::entry(get_string('reportapplicant', 'enrol_apply'), true, true, true),
            'wait' => self::entry(get_string('waittime', 'enrol_apply'), true, true),
        ];
        if ($table->has_group_column()) {
            // Not sortable, as the table's column is not (V-D1).
            $catalogue['group'] = self::entry(get_string('queuegroup', 'enrol_apply'), false, true);
        }
        foreach (self::questions($table) as $key => $title) {
            $catalogue[self::ANSWER_PREFIX . $key] = self::entry($title, true, true);
        }
        $catalogue['comment'] = self::entry(commentlabel::custom($instance, false), false, true);
        if ($table->counts_history()) {
            $catalogue['history'] = self::entry(get_string('queuehistory', 'enrol_apply'), false, false);
        }

        return $catalogue;
    }

    /**
     * One catalogue entry.
     *
     * @param string $title Title, plain.
     * @param bool $sortable Whether the page sorts by it.
     * @param bool $default Whether a reader with no preference sees it.
     * @param bool $fixed Whether it is always shown.
     * @return array The entry.
     */
    protected static function entry(string $title, bool $sortable, bool $default, bool $fixed = false): array {
        return ['title' => $title, 'sortable' => $sortable, 'default' => $default, 'fixed' => $fixed];
    }

    /**
     * The profile fields and questions this method asks that the reader may see.
     *
     * The method's own, as the queue lists one method: the requested profile fields first, then its
     * questions, in form order, each under its current name. Masked by the table's snapshot rule in
     * the method's course ({@see applications::visible_keys()}, the course report's
     * visible_keys()), as course_applications::questions() masks the report's columns.
     *
     * @param applications $table The queue's table.
     * @return array Field key => the question's current name, plain spelling.
     */
    public static function questions(applications $table): array {
        $instance = $table->get_instance();
        $visible = $table->visible_keys((int) $instance->courseid);

        $questions = [];
        foreach (fields::resolve($instance)->keys() as $key) {
            $questions[$key] ??= fields::label($key, false);
        }
        foreach (answers::fields($instance) as $field) {
            $questions[answers::key((int) $field->get('id'))] ??= $field->get_formatted_name(false);
        }

        return array_filter(
            $questions,
            static fn(string $key): bool => formatter::shows($key, $visible),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * The catalogue keys a reader sees, in catalogue order.
     *
     * The fixed ones always; then the default ones when the reader chose nothing, or the chosen
     * ones the catalogue has, ANSWERS standing for every answer column. A stored key the catalogue
     * does not have (another method's question, a key withheld from this reader) adds nothing.
     *
     * @param array $catalogue What {@see catalogue()} returned.
     * @param array|null $chosen The reader's stored keys, null for the default set.
     * @return array Catalogue keys.
     */
    public static function active_keys(array $catalogue, ?array $chosen): array {
        $wanted = $chosen === null ? null : array_flip(self::expand($chosen, $catalogue));

        $keys = [];
        foreach ($catalogue as $key => $entry) {
            $shown = $entry['fixed'] || ($wanted === null ? $entry['default'] : isset($wanted[$key]));
            if ($shown) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Stored keys as catalogue keys: ANSWERS becomes every answer column, others stay as they are.
     *
     * @param array $keys Stored keys.
     * @param array $catalogue What {@see catalogue()} returned.
     * @return array Keys.
     */
    protected static function expand(array $keys, array $catalogue): array {
        $expanded = [];
        foreach ($keys as $key) {
            if ($key !== self::ANSWERS) {
                $expanded[] = (string) $key;
                continue;
            }
            foreach (array_keys($catalogue) as $column) {
                if (str_starts_with($column, self::ANSWER_PREFIX)) {
                    $expanded[] = $column;
                }
            }
        }

        return $expanded;
    }

    /**
     * The catalogue as a client draws the Columns menu.
     *
     * @param array $catalogue What {@see catalogue()} returned.
     * @param array $active What {@see active_keys()} returned.
     * @return array Each: key, title, sortable, default, active, fixed.
     */
    protected static function columns(array $catalogue, array $active): array {
        $columns = [];
        foreach ($catalogue as $key => $entry) {
            $columns[] = [
                'key' => $key,
                'title' => $entry['title'],
                'sortable' => $entry['sortable'],
                'default' => $entry['default'],
                'active' => in_array($key, $active, true),
                'fixed' => $entry['fixed'],
            ];
        }

        return $columns;
    }

    /**
     * The Columns menu's sets, and which one the reader's columns are.
     *
     * Each set is stored as the reader's choice ({@see queuepreference}), so it means what a hand
     * choice of the same keys means: intersected with this method's catalogue.
     *
     * @param array $catalogue What {@see catalogue()} returned.
     * @param array $active What {@see active_keys()} returned.
     * @return array Each: name, columns (the keys stored), active (whether the reader sees that set).
     */
    protected static function presets(array $catalogue, array $active): array {
        $presets = [];
        foreach (self::PRESETS as $name => $keys) {
            $set = self::active_keys($catalogue, $keys);
            $presets[] = [
                'name' => $name,
                'columns' => $keys,
                'active' => $set === $active,
            ];
        }

        return $presets;
    }

    /**
     * The page's rows, in the table's SQL, in the order asked for.
     *
     * Applied date (the default, oldest first as the page lists them), the applicant's name in the
     * order the site writes full names, or an answer: the frozen value the newest record holds for
     * that key, joined on the (submissionid, fieldkey) unique index so a row is never multiplied and
     * no query runs per row. Every order ends in ue.id, the table's unique last key
     * ({@see applications::get_sort_columns()}), so a tie never moves a row between pages.
     *
     * @param applications $table The queue's table.
     * @param string $sort A sortable catalogue key.
     * @param int $direction SORT_ASC or SORT_DESC.
     * @param int $page 0-based page.
     * @param int $perpage Rows per page.
     * @return array The rows, in order.
     */
    protected static function page(applications $table, string $sort, int $direction, int $page, int $perpage): array {
        global $DB;

        $from = $table->sql->from;
        $params = $table->sql->params;
        if ($sort === 'applicant') {
            $order = [];
            foreach (self::name_fields() as $field) {
                $order[] = $DB->sql_order_by_null($field, $direction);
            }
            $order = implode(', ', $order);
        } else if (str_starts_with($sort, self::ANSWER_PREFIX)) {
            $from .= "
            LEFT JOIN {" . submission::ANSWER_TABLE . "} qsort ON qsort.submissionid = s.id AND qsort.fieldkey = :qsortkey";
            $params['qsortkey'] = substr($sort, strlen(self::ANSWER_PREFIX));
            $order = $DB->sql_order_by_null($DB->sql_order_by_text('qsort.value', 255), $direction);
        } else {
            $order = $DB->sql_order_by_null('ue.timecreated', $direction);
        }

        return array_values($DB->get_records_sql(
            "SELECT {$table->sql->fields}
               FROM {$from}
              WHERE {$table->sql->where}
           ORDER BY {$order}, ue.id ASC",
            $params,
            $page * $perpage,
            $perpage
        ));
    }

    /**
     * The name columns in the order the site writes a full name.
     *
     * @return array Two columns of the user aliased "u".
     */
    protected static function name_fields(): array {
        global $CFG;

        $template = (string) ($CFG->fullnamedisplay ?? 'language');
        if ($template === 'language') {
            $template = get_string('fullnamedisplay', 'langconfig');
        }
        $first = strpos($template, 'firstname');
        $last = strpos($template, 'lastname');

        return $last !== false && ($first === false || $last < $first)
            ? ['u.lastname', 'u.firstname']
            : ['u.firstname', 'u.lastname'];
    }

    /**
     * One row as a client draws it.
     *
     * @param applications $table The queue's table, its page prepared.
     * @param stdClass $record The row, as the table's SQL selects it.
     * @param array $active The catalogue keys shown.
     * @param int $now The clock's time.
     * @return array See \enrol_apply\external\queue_rows::execute_returns().
     */
    protected static function row(applications $table, stdClass $record, array $active, int $now): array {
        global $OUTPUT;

        $user = user_picture::unalias($record, ['username'], 'userid');
        $profile = $table->profile_url($record);

        $identity = [];
        foreach ($table->identity_values($record) as $name => $value) {
            $identity[] = ['name' => $name, 'title' => \core_user\fields::get_display_name($name), 'value' => $value];
        }

        $answers = [];
        foreach ($table->visible_entries($record) as $entry) {
            $answers[self::ANSWER_PREFIX . $entry['key']] = $entry['value'];
        }

        $cells = [];
        foreach ($active as $key) {
            $html = match (true) {
                $key === 'applicant' => null,
                $key === 'wait' => $table->applydate_html($record),
                $key === 'group' => (string) ($table->keygroup_html($record) ?? ''),
                $key === 'comment' => formatter::plaintext($record->applycomment ?? '', $record),
                $key === 'history' => (string) (int) $record->appliedbefore,
                default => formatter::plaintext($answers[$key] ?? '', $record),
            };
            if ($html !== null) {
                $cells[] = ['key' => $key, 'html' => $html];
            }
        }

        $ueid = (int) $record->userenrolmentid;

        return [
            'userenrolmentid' => $ueid,
            'userid' => (int) $record->userid,
            'selectable' => $table->selectable($record),
            /* Every row this listing holds is one its reader may open (the scope is
               can_view_application()'s, by construction), as the table's Review button says. */
            'reviewurl' => (new moodle_url('/enrol/apply/manage.php', ['userenrol' => $ueid]))->out(false),
            'status' => (int) $record->enrolstatus,
            'applied' => (int) $record->applydate,
            'wait' => max(0, $now - (int) $record->applydate),
            'applicant' => [
                'fullname' => fullname($user),
                'profileurl' => $profile === null ? '' : $profile->out(false),
                // Decorative beside the name, which is the row's label: no link, no alternative text.
                'picture' => $OUTPUT->user_picture($user, ['size' => self::PICTURE_SIZE, 'link' => false, 'alttext' => false]),
                'identity' => $identity,
                'tags' => $table->tags($record),
            ],
            'cells' => $cells,
        ];
    }

    /**
     * The scope counts and the facets, each counted under every other criterion in force.
     *
     * Facets: the scope (the enrolment status), the group (the values groupfilter::offered() gives
     * this reader), how the group was named (where the course has groups), waited more than, and
     * applied before (where the earlier applications are counted). One statement per distinct
     * WHERE: the facets with no value of their own in force share the listing's own; each facet
     * whose value is applied is counted apart, without it. Each statement groups the rows in the
     * database by every facet's value ({@see tally()}), so it returns tallies, never the rows.
     *
     * @param applications $table The queue's table.
     * @return array 'scopes' => all, pending, deferred; 'facets' => each: name, title, applied
     *     value ('' for none), options (value, label, count).
     */
    protected static function facets(applications $table): array {
        $applied = $table->applied_filter_names();

        $groups = $table->get_group_choices();
        $names = ['status'];
        if ($groups) {
            $names[] = groupfilter::NAME;
        }
        if ($table->has_group_column() && $table->get_instance() !== null) {
            $names[] = 'named';
        }
        $names[] = 'waited';
        if ($table->counts_history()) {
            $names[] = 'appliedbefore';
        }

        // Facet name => the filters its count leaves out: its own, where applied.
        $reads = [];
        foreach ($names as $name) {
            $without = in_array($name, $applied, true) ? [$name] : [];
            $reads[implode(',', $without)][] = $name;
        }

        $tallies = [];
        foreach ($reads as $without => $facetnames) {
            $tallies += self::tally($table, $without === '' ? [] : explode(',', $without), $facetnames);
        }

        $status = $tallies['status'];
        $scopes = [
            'all' => array_sum($status),
            'pending' => (int) ($status[ENROL_USER_SUSPENDED] ?? 0),
            'deferred' => (int) ($status[self::status('deferred')] ?? 0),
        ];

        $applieds = [groupfilter::NAME => (string) ($table->get_group_filter() ?? '')] + $table->get_facet_filters();
        // The reader's own groups, which "mine" selects; read once, and only where it is offered.
        $own = isset($groups[groupfilter::MINE]) ? $table->own_groups() : [];
        $facets = [];
        foreach ($names as $name) {
            if ($name === 'status') {
                continue;
            }
            $counts = $tallies[$name];
            $options = [];
            foreach (self::options($name, $groups) as $value => $label) {
                $options[] = [
                    'value' => (string) $value,
                    'label' => $label,
                    'count' => self::option_count($name, (string) $value, $counts, $own),
                ];
            }
            $facets[] = [
                'name' => $name,
                'title' => self::facet_title($name),
                'applied' => (string) ($applieds[$name] ?? ''),
                'options' => $options,
            ];
        }

        return ['scopes' => $scopes, 'facets' => $facets];
    }

    /**
     * Tally the rows of one WHERE by every facet's value, in one statement.
     *
     * The table's FROM and {@see applications::where_without()}, wrapped in a derived table grouped
     * by each value a facet reads: the enrolment status; the application's group on its newest
     * record, 0 where that names no group that exists (the joins the table's group column makes,
     * kgs and kg, present wherever the group facets are); how that record named it
     * ({@see applications::named_kind_sql()}); the largest "waited more than" threshold the row
     * passes, with the strict comparison the waited filter makes; and whether the applicant applied
     * before, by the filter's own subquery. MIN(ue.id) keys each group, so the DML's first-column
     * rule holds without a recordset.
     *
     * @param applications $table The queue's table.
     * @param array $without The filters left out of the WHERE.
     * @param array $facetnames The facets this read counts.
     * @return array Facet name => (value => rows).
     */
    protected static function tally(applications $table, array $without, array $facetnames): array {
        global $DB;

        [$where, $params] = $table->where_without($without);

        $dims = ['ue.status AS facetstatus'];
        $columns = ['facetstatus'];
        if ($table->has_group_column()) {
            $dims[] = 'COALESCE(kg.id, 0) AS facetgroup';
            $dims[] = applications::named_kind_sql('kgs') . ' AS facetnamed';
            $columns[] = 'facetgroup';
            $columns[] = 'facetnamed';
        }
        $cases = [];
        foreach (array_reverse(applications::WAITED_DAYS) as $days) {
            $cases[] = "WHEN ue.timecreated < :facetwaited{$days} THEN {$days}";
            $params["facetwaited{$days}"] = $table->now() - $days * DAYSECS;
        }
        $dims[] = 'CASE ' . implode(' ', $cases) . ' ELSE 0 END AS facetwaited';
        $columns[] = 'facetwaited';
        if ($table->counts_history()) {
            [$prior, $priorparams] = $table->prior_sql('priorfacet');
            $dims[] = "CASE WHEN EXISTS (SELECT 1 {$prior}) THEN 1 ELSE 0 END AS facetbefore";
            $columns[] = 'facetbefore';
            $params += $priorparams;
        }

        $grouped = implode(', ', array_map(static fn(string $column): string => "facetrows.{$column}", $columns));
        $records = $DB->get_records_sql(
            "SELECT MIN(facetrows.rowid) AS facetfirst, {$grouped}, COUNT(1) AS facetcount
               FROM (SELECT ue.id AS rowid, " . implode(', ', $dims) . "
                       FROM {$table->sql->from}
                      WHERE {$where}) facetrows
           GROUP BY {$grouped}",
            $params
        );

        $column = [
            'status' => 'facetstatus',
            groupfilter::NAME => 'facetgroup',
            'named' => 'facetnamed',
            'waited' => 'facetwaited',
            'appliedbefore' => 'facetbefore',
        ];
        $tallies = array_fill_keys($facetnames, []);
        foreach ($records as $record) {
            foreach ($facetnames as $name) {
                $value = (int) $record->{$column[$name]};
                $tallies[$name][$value] = ($tallies[$name][$value] ?? 0) + (int) $record->facetcount;
            }
        }

        return $tallies;
    }

    /**
     * A facet's options, labelled in the plain spelling.
     *
     * @param string $name The facet.
     * @param array $groups The group filter's choices, {@see applications::get_group_choices()}.
     * @return array Value => label.
     */
    protected static function options(string $name, array $groups): array {
        if ($name === groupfilter::NAME) {
            return $groups;
        }
        if ($name === 'named') {
            $options = [];
            foreach (applications::NAMED as $value) {
                $options[$value] = match ($value) {
                    applications::NAMED_CHOSEN => get_string('queuenamedchosen', 'enrol_apply'),
                    applications::NAMED_KEY => get_string('queuenamedkey', 'enrol_apply'),
                    default => get_string('queuenamednone', 'enrol_apply'),
                };
            }

            return $options;
        }
        if ($name === 'waited') {
            $options = [];
            foreach (applications::WAITED_DAYS as $days) {
                $options[(string) $days] = get_string('waitdays', 'enrol_apply', $days);
            }

            return $options;
        }

        return ['1' => get_string('yes')];
    }

    /**
     * How many rows choosing one option of a facet would list, from that facet's tally.
     *
     * The same reading of each value the filter's predicate makes: a group is the rows of that
     * group; "mine" the rows of the reader's own groups (groupfilter::own_groups(), what the filter
     * selects); "none" the rows naming no group that exists; a named kind its rows; a waited
     * threshold every row past it; applied before the rows that did.
     *
     * @param string $name The facet.
     * @param string $value The option.
     * @param array $counts The facet's tally, value => rows.
     * @param array $own The reader's own groups, {@see applications::own_groups()}.
     * @return int The rows.
     */
    protected static function option_count(string $name, string $value, array $counts, array $own): int {
        if ($name === groupfilter::NAME) {
            if ($value === groupfilter::MINE) {
                $sum = 0;
                foreach ($own as $groupid) {
                    $sum += (int) ($counts[(int) $groupid] ?? 0);
                }

                return $sum;
            }

            return (int) ($counts[$value === groupfilter::NONE ? 0 : (int) $value] ?? 0);
        }
        if ($name === 'named') {
            return (int) ($counts[applications::named_kind($value)] ?? 0);
        }
        if ($name === 'waited') {
            $sum = 0;
            foreach ($counts as $bucket => $rows) {
                if ($bucket >= (int) $value) {
                    $sum += $rows;
                }
            }

            return $sum;
        }

        return (int) ($counts[1] ?? 0);
    }

    /**
     * A facet's title, plain.
     *
     * @param string $name The facet.
     * @return string The title.
     */
    protected static function facet_title(string $name): string {
        return match ($name) {
            groupfilter::NAME => get_string('queuegroup', 'enrol_apply'),
            'named' => get_string('queuenamed', 'enrol_apply'),
            'waited' => get_string('reportviewwaitedmore', 'enrol_apply'),
            default => get_string('queueappliedbefore', 'enrol_apply'),
        };
    }

    /**
     * The profile-field filters this reader is offered, as a client draws them.
     *
     * The page's own (\enrol_apply\local\queuefilter, offered by the identity rule in the method's
     * course): name (the token the address carries), title, control (text or select), options.
     *
     * @param applications $table The queue's table.
     * @return array Each: name, title, control, options (value, label).
     */
    protected static function field_filters(applications $table): array {
        $filters = [];
        foreach ($table->get_offered_filters() as $token => $offered) {
            $options = [];
            foreach ($offered->options as $value => $label) {
                $options[] = ['value' => (string) $value, 'label' => (string) $label];
            }
            $filters[] = [
                'name' => (string) $token,
                'title' => queuefilter::label($offered->name, false),
                'control' => $offered->control,
                'options' => $options,
            ];
        }

        return $filters;
    }

    /**
     * Every filter the table applied, as it cleaned them: what the request asked for and survived.
     *
     * @param applications $table The queue's table.
     * @return array Each: name, value.
     */
    protected static function applied(applications $table): array {
        $values = [];
        if ($table->get_group_filter() !== null) {
            $values[groupfilter::NAME] = $table->get_group_filter();
        }
        $values += $table->get_field_filters();
        [$from, $to] = $table->get_applied_dates();
        if ($from !== null) {
            $values['appliedfrom'] = $from;
        }
        if ($to !== null) {
            $values['appliedto'] = $to;
        }
        $values += $table->get_facet_filters();

        $applied = [];
        foreach ($values as $name => $value) {
            $applied[] = ['name' => (string) $name, 'value' => (string) $value];
        }

        return $applied;
    }
}
