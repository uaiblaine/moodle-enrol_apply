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
use context_system;
use enrol_apply\reportbuilder\local\formatters\submission as formatter;
use enrol_apply\table\applications;
use enrol_apply\table\applications_filterset;
use moodle_url;
use stdClass;
use user_picture;

/**
 * The approval queue read headlessly: the queue of one method, and the two that span courses.
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
 * The id 0 is the wide queue (SQ-D1): whichever of the site-wide queue (deciding or read only) and
 * the mentee queue queue::listing_scope(0) gives this reader, never chosen by the request. Its
 * catalogue is the table's columns for that scope (the course and its method, the group and the
 * submitted answers as one cell on the site-wide queue only), its rows carry the course and the
 * method, and its facets add the course, the category, the method and whether the application has
 * a group, all on the site-wide queue only. A wide page adds the reads that grow with its distinct
 * courses (the profile links, the answers' mask, the method's deciding role) and, for a site-wide
 * reader, one read of the page's methods and at most two grouped counts of their capacity
 * ({@see capacity::states()}); test_a_wide_page_grows_with_its_courses_not_its_rows holds it.
 *
 * Computed per request and never cached: what a reader may see depends on capabilities, groups and
 * the live enrolments, on whichever node.
 *
 * Not final: a test narrows FACET_LIMIT through a subclass (every read of it is static::).
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queueengine {
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

    /**
     * @var array The Columns menu's sets on the queues that span courses (SQ-D11), stored in their own
     *     preference and intersected with that catalogue like the method queue's.
     */
    public const WIDE_PRESETS = [
        'everything' => ['course', 'wait', 'group', 'details', 'comment'],
        'glance' => ['course', 'wait', 'group'],
    ];

    /** @var int The most options the course and category facets send (SQ-D6); the rest are counted as left out. */
    public const FACET_LIMIT = 200;

    /** @var string[] The facets whose options are read from the site rather than from a vocabulary. */
    protected const SITE_FACETS = ['course', 'category', 'method'];

    /** @var int The applicant's picture, in pixels (MQ-D9). */
    public const PICTURE_SIZE = 35;

    /**
     * @var string[] Filters a request may not set through its filter list: the scope sets the
     *     status and the search has a parameter of its own. The course, the category, the method
     *     and grouped do reach the table, which applies them on the site-wide queue only
     *     ({@see \enrol_apply\local\coursefilter::offered()}) and ignores them elsewhere.
     */
    public const NOT_FILTERS = ['search', 'status'];

    /**
     * The apply method an id names, and its course's context; for 0, the wide queue's system context.
     *
     * The course comes from the method, never from the client. 0 is the wide queue (SQ-D1): no
     * instance and the system context, by its level, whatever the reader; which wide queue it is,
     * and whether the reader may open it, is {@see require_listing()}'s question. Any other id must
     * name an apply method, a negative one included.
     *
     * @param int $enrolid The enrol instance, 0 for the queues that span courses.
     * @return array [the instance record or null for 0, its course context or the system context].
     * @throws \dml_missing_record_exception When a non-zero id names no apply method.
     */
    public static function locate(int $enrolid): array {
        global $DB;

        if ($enrolid === 0) {
            return [null, context_system::instance()];
        }

        $instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'apply'], '*', MUST_EXIST);

        return [$instance, context_course::instance((int) $instance->courseid)];
    }

    /**
     * Refuse a reader the queue page would refuse, as manage.php refuses them.
     *
     * {@see queue::listing_scope()} asked exactly as manage.php asks it: for a method, allowed is the
     * deciding or the viewing capability in the method's course; for 0, the deciding capability in
     * the system context, a mentor's mentees, or the viewing capability in the system context, in
     * that order, and nobody else (an editing teacher of one course is refused). Course access is
     * the caller's validate_context(), as it is the page's require_login(). Nothing the request
     * carries chooses the scope: the id names the method or the wide queue, and the reader's
     * narrowing, mentees and identity context are derived from the course and $USER.
     *
     * @param int $enrolid The enrol instance, already located; 0 for the queues that span courses.
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
     * The reader is refused by the engine's own check, which runs first: the table below is built from
     * the reader's scope, and the catalogue it then builds asks {@see require_listing()} again.
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
        $wide = $enrolid === 0;

        $scope = in_array($options['scope'] ?? '', self::SCOPES, true) ? $options['scope'] : 'all';
        $perpage = in_array((int) ($options['perpage'] ?? 0), self::PERPAGES, true) ? (int) $options['perpage'] : self::PERPAGE;
        $page = max(0, (int) ($options['page'] ?? 0));
        $direction = ($options['direction'] ?? '') === 'desc' ? SORT_DESC : SORT_ASC;

        $table = self::table($enrolid, $scope, trim((string) ($options['search'] ?? '')), (array) ($options['filters'] ?? []));
        $catalogue = self::catalogue($table);
        $stored = queuepreference::read(null, $wide);
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
        // Which methods of the page are at their limit: a site-wide reader's label, never a mentor's.
        $full = $table->offers_course_filters() ? self::full_methods($records, $now) : [];
        $rows = [];
        foreach ($records as $record) {
            $rows[] = self::row($table, $record, $active, $now, $full);
        }

        return [
            'kind' => $table->scope_kind(),
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
            'presets' => self::presets($catalogue, $active, $wide ? self::WIDE_PRESETS : self::PRESETS),
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
     * With no instance it is the wide queue's ({@see wide_summary()}). One return structure for both:
     * the fields of the other shape hold neutral values (0, false), so the method queue's answer is
     * today's with four neutral fields after it.
     *
     * @param stdClass|null $instance The apply method, null for the queues that span courses; refused
     *     to a reader {@see require_listing()} refuses.
     * @return array See \enrol_apply\external\queue_summary::execute_returns().
     * @throws \required_capability_exception When the reader may not open the queue.
     */
    public static function summary(?stdClass $instance): array {
        self::require_listing((int) ($instance->id ?? 0));

        if ($instance === null) {
            return self::wide_summary();
        }

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
            'sitefigures' => false,
            'courses' => 0,
            'methods' => 0,
            'methodsfull' => 0,
        ];
    }

    /**
     * The wide queue's tiles (SQ-D7): what awaits, the oldest wait, the late ones, the deferred ones,
     * and for a site-wide reader the courses and methods holding an application and the methods at
     * their limit.
     *
     * The four queue figures are {@see applications::scope_figures()} over the reader's scope before
     * any filter, as on the method queue. The site figures are read over the same scope
     * ({@see applications::scope_methods()}), so a method counted holds an application this reader
     * lists; "at its limit" is {@see capacity::states()}'s own two flags, every place taken or the
     * applicant limit reached, computed on this request and never cached (SQ-D8). A mentor gets the
     * four figures, sitefigures false and zeroes: the methods their mentees applied to are not a
     * site's figures, and the capacity of a course they hold nothing in is not theirs to read.
     *
     * The caller has asked {@see require_listing()} for 0.
     *
     * @return array See \enrol_apply\external\queue_summary::execute_returns().
     */
    protected static function wide_summary(): array {
        $table = applications::for_scope(0);
        $now = $table->now();
        $figures = $table->scope_figures(self::LATE);

        $sitefigures = $table->offers_course_filters();
        $courses = 0;
        $methods = 0;
        $methodsfull = 0;
        if ($sitefigures) {
            $instances = $table->scope_methods();
            $methods = count($instances);
            $courses = count(array_unique(array_map(static fn(stdClass $instance): int => (int) $instance->courseid, $instances)));
            foreach (capacity::states($instances, $now) as $state) {
                if ($state->placesfull || $state->limitreached) {
                    $methodsfull++;
                }
            }
        }

        return [
            'places' => 0,
            'placestaken' => 0,
            'applicantlimit' => 0,
            'applicants' => 0,
            'awaiting' => $figures['awaiting'],
            'deferred' => $figures['deferred'],
            'oldestwait' => $figures['oldest'] > 0 ? max(0, $now - $figures['oldest']) : 0,
            'late' => $figures['late'],
            'latedays' => (int) (self::LATE / DAYSECS),
            'open' => false,
            'closes' => 0,
            'narrowed' => $table->scope_groups() !== null,
            'notingroup' => $table->not_in_any_group(),
            'sitefigures' => $sitefigures,
            'courses' => $courses,
            'methods' => $methods,
            'methodsfull' => $methodsfull,
        ];
    }

    /**
     * The methods of a page at their limit, for the rows' "full" label.
     *
     * {@see capacity::states()} over the page's distinct methods: one read of their {enrol} records
     * and at most two grouped counts, whatever the page's size.
     *
     * @param array $records The page's rows, as the table's SQL selects them.
     * @param int $now The clock's time.
     * @return array Enrol instance id => true, for the methods with every place taken or the applicant
     *     limit reached.
     */
    protected static function full_methods(array $records, int $now): array {
        global $DB;

        $ids = array_values(array_unique(array_map(static fn(stdClass $record): int => (int) $record->enrolid, $records)));
        if (!$ids) {
            return [];
        }

        $full = [];
        foreach (capacity::states($DB->get_records_list('enrol', 'id', $ids), $now) as $id => $state) {
            if ($state->placesfull || $state->limitreached) {
                $full[(int) $id] = true;
            }
        }

        return $full;
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
     * A table with no instance is the wide queue's, whose catalogue is {@see wide_catalogue()}.
     *
     * @param applications $table The queue's table.
     * @return array Catalogue key => ['title', 'sortable', 'default', 'fixed'].
     * @throws \required_capability_exception When the reader may not open the queue.
     */
    public static function catalogue(applications $table): array {
        $instance = $table->get_instance();
        self::require_listing((int) ($instance->id ?? 0));

        if ($instance === null) {
            return self::wide_catalogue($table);
        }

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
     * The columns of the queues that span courses, the table's own for the scope.
     *
     * The applicant (fixed), the course and its method (SQ-D2, sortable by the course's name), the
     * wait, the group where the table draws it (the site-wide queue, never a mentor's), the submitted
     * answers as one "Details submitted" column where the table draws it (the same, SQ-D3; masked per
     * row in the row's own course, so neither sortable nor a source of filters), and the comment
     * under the generic heading: the methods word the question each their own way. No answer
     * column and no earlier applications: both need one method.
     *
     * The caller has asked {@see require_listing()}.
     *
     * @param applications $table The queue's table, with no instance.
     * @return array Catalogue key => ['title', 'sortable', 'default', 'fixed'].
     */
    protected static function wide_catalogue(applications $table): array {
        $catalogue = [
            'applicant' => self::entry(get_string('reportapplicant', 'enrol_apply'), true, true, true),
            'course' => self::entry(get_string('course'), true, true),
            'wait' => self::entry(get_string('waittime', 'enrol_apply'), true, true),
        ];
        if ($table->has_group_column()) {
            $catalogue['group'] = self::entry(get_string('queuegroup', 'enrol_apply'), false, true);
        }
        if ($table->has_details_column()) {
            $catalogue['details'] = self::entry(get_string('queuesubmitted', 'enrol_apply'), false, true);
        }
        $catalogue['comment'] = self::entry(get_string('applycomment', 'enrol_apply'), false, true);

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
     * choice of the same keys means: intersected with this queue's catalogue.
     *
     * @param array $catalogue What {@see catalogue()} returned.
     * @param array $active What {@see active_keys()} returned.
     * @param array $sets PRESETS for a method's queue, WIDE_PRESETS for the queues that span courses.
     * @return array Each: name, columns (the keys stored), active (whether the reader sees that set).
     */
    protected static function presets(array $catalogue, array $active, array $sets): array {
        $presets = [];
        foreach ($sets as $name => $keys) {
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
     * order the site writes full names, the course (its full name, then the method's stored name and
     * id, the table's own course order), or an answer: the frozen value the newest record holds for
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
        } else if ($sort === 'course') {
            $order = implode(', ', [
                $DB->sql_order_by_null('c.fullname', $direction),
                $DB->sql_order_by_null('e.name', $direction),
                $DB->sql_order_by_null('e.id', $direction),
            ]);
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
     * The course and the method are the row's on the queues that span courses, and zeroes and empty
     * strings on a method's queue, which names both in its own page (one return structure for all
     * three). The course column draws from them, as the applicant column draws from the applicant,
     * so it has no cell. The method's queue address is given to a site-wide reader only, who may open
     * it (a mentor holds nothing in the course); its "full" flag only where the caller read the
     * capacity, which is never for a mentor ({@see rows()}).
     *
     * @param array $active The catalogue keys shown.
     * @param int $now The clock's time.
     * @param array $full Enrol instance id => true for the page's methods at their limit, from full_methods().
     * @return array See \enrol_apply\external\queue_rows::execute_returns().
     */
    protected static function row(applications $table, stdClass $record, array $active, int $now, array $full): array {
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
                $key === 'applicant', $key === 'course' => null,
                $key === 'wait' => $table->applydate_html($record),
                $key === 'details' => $table->details_html($record),
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

        $course = ['id' => 0, 'name' => '', 'url' => ''];
        $method = ['id' => 0, 'name' => '', 'deciding' => false, 'full' => false, 'queueurl' => ''];
        if ($table->get_instance() === null) {
            $courseid = (int) $record->courseid;
            $enrolid = (int) $record->enrolid;
            $facts = $table->method_facts($enrolid);
            $course = [
                'id' => $courseid,
                'name' => format_string($record->course, true, [
                    'context' => context_course::instance($courseid),
                    'escape' => false,
                ]),
                // The address col_course() links.
                'url' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
            ];
            $method = [
                'id' => $enrolid,
                'name' => (string) ($facts->name ?? ''),
                'deciding' => (bool) ($facts->deciding ?? false),
                'full' => !empty($full[$enrolid]),
                'queueurl' => $table->offers_course_filters()
                    ? (new moodle_url('/enrol/apply/manage.php', ['id' => $enrolid]))->out(false)
                    : '',
            ];
        }

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
                /* Decorative beside the name, which is the row's label: no link, and hidden from assistive
                   technology as a whole, because core names the user on the initials it draws for a user
                   with no picture whatever 'alttext' says. */
                'picture' => '<span aria-hidden="true">' .
                    $OUTPUT->user_picture($user, ['size' => self::PICTURE_SIZE, 'link' => false, 'alttext' => false]) .
                    '</span>',
                'identity' => $identity,
                'tags' => $table->tags($record),
            ],
            'cells' => $cells,
            'course' => $course,
            'method' => $method,
        ];
    }

    /**
     * The scope counts and the facets, each counted under every other criterion in force.
     *
     * Facets: the scope (the enrolment status), the group (the values groupfilter::offered() gives
     * this reader), how the group was named (where the course has groups), waited more than, and
     * applied before (where the earlier applications are counted). On the site-wide queue, and
     * nowhere else, the course, the category, the method (only once a course is chosen, and only
     * where that course holds applications of more than one method) and whether the application has
     * a group (SQ-D4, SQ-D5). One statement per distinct WHERE: the facets with no value of their own
     * in force share the listing's own; each facet whose value is applied is counted apart, without
     * it. Each statement groups the rows in the database by every facet's value ({@see tally()}), so
     * it returns tallies, never the rows; the course, category and method options then cost one read
     * each for their names ({@see site_facet()}).
     *
     * @param applications $table The queue's table.
     * @return array 'scopes' => all, pending, deferred; 'facets' => each: name, title, applied
     *     value ('' for none), options (value, label, count), omitted (options left out by FACET_LIMIT).
     */
    protected static function facets(applications $table): array {
        $applied = $table->applied_filter_names();
        $sitewide = $table->offers_course_filters();

        $groups = $table->get_group_choices();
        $names = ['status'];
        if ($groups) {
            $names[] = groupfilter::NAME;
        }
        if ($table->has_group_column() && $table->get_instance() !== null) {
            $names[] = 'named';
        }
        if ($sitewide) {
            $names[] = 'course';
            $names[] = 'category';
            // Offered once a course is chosen (SQ-D4); dropped below when that course holds one method.
            if (in_array('course', $applied, true)) {
                $names[] = 'method';
            }
        }
        $names[] = 'waited';
        if ($sitewide) {
            $names[] = 'grouped';
        }
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

        $applieds = [groupfilter::NAME => (string) ($table->get_group_filter() ?? '')]
            + $table->get_facet_filters() + $table->get_course_filters();
        // The reader's own groups, which "mine" selects; read once, and only where it is offered.
        $own = isset($groups[groupfilter::MINE]) ? $table->own_groups() : [];
        $facets = [];
        foreach ($names as $name) {
            if ($name === 'status') {
                continue;
            }
            $counts = $tallies[$name];
            if (in_array($name, self::SITE_FACETS, true)) {
                $facet = static::site_facet($name, $counts, (string) ($applieds[$name] ?? ''));
                if ($facet !== null) {
                    $facets[] = $facet;
                }
                continue;
            }
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
                'omitted' => 0,
            ];
        }

        return ['scopes' => $scopes, 'facets' => $facets];
    }

    /**
     * A facet whose options are the site's courses, categories or methods, from its tally.
     *
     * Each count is what choosing the option lists under every other criterion, as for every facet:
     * a course is its rows; a category is the rows of the courses in its subtree, which is what the
     * category filter matches ({@see coursefilter::where()}: the category, or a path below it), so a
     * category's count is the sum over its own courses and those of every category beneath it; a
     * method is its rows. Only options that list something are offered, most applications first and
     * then by name; the course and category facets send at most FACET_LIMIT of them (SQ-D6), cut by
     * count with ties broken by id so that no name is read for an option left out, plus the applied
     * one if the cut left it out, and say how many they left out. A category is offered only
     * where \core_course_category::make_categories_list() lets this reader see it, under the path
     * name the category filter's own control shows ({@see coursefilter::categories()}). The method
     * facet is returned only where the chosen course holds applications of more than one method.
     * Labels are plain.
     *
     * @param string $name course, category or method.
     * @param array $counts The facet's tally, value => rows.
     * @param string $applied The value applied, '' for none.
     * @return array|null The facet, as facets() returns each; null for a method facet with one method.
     */
    protected static function site_facet(string $name, array $counts, string $applied): ?array {
        global $DB;

        $counts = array_filter($counts, static fn(int $rows): bool => $rows > 0);
        $visible = null;
        if ($name === 'category') {
            $counts = self::subtree_counts($counts);
            $visible = coursefilter::categories();
            $counts = array_intersect_key($counts, $visible);
        } else if ($name === 'method' && count($counts) < 2) {
            return null;
        }

        // Most applications first, ties by id, so that the cut needs no name; then cut.
        $ids = array_keys($counts);
        usort($ids, static fn(int $a, int $b): int => [$counts[$b], $a] <=> [$counts[$a], $b]);
        $kept = $name === 'method' ? $ids : array_slice($ids, 0, static::FACET_LIMIT);
        $omitted = count($ids) - count($kept);
        // The applied value stays on offer even when the cut, or a count of nothing, left it out.
        if ($applied !== '' && !in_array((int) $applied, $kept, true)) {
            $kept[] = (int) $applied;
            if (isset($counts[(int) $applied])) {
                $omitted--;
            }
        }

        $labels = [];
        if ($name === 'course') {
            $labels = self::course_names($kept);
        } else if ($name === 'category') {
            $labels = $visible;
        } else {
            foreach ($DB->get_records_list('enrol', 'id', $kept) as $instance) {
                $labels[(int) $instance->id] = applications::method_name($instance);
            }
        }

        $options = [];
        foreach ($kept as $id) {
            if (isset($labels[$id])) {
                $options[] = ['value' => (string) $id, 'label' => (string) $labels[$id], 'count' => (int) ($counts[$id] ?? 0)];
            }
        }
        usort($options, static fn(array $a, array $b): int =>
            [$b['count'], $a['label'], (int) $a['value']] <=> [$a['count'], $b['label'], (int) $b['value']]);

        return [
            'name' => $name,
            'title' => self::facet_title($name),
            'applied' => $applied,
            'options' => $options,
            'omitted' => $omitted,
        ];
    }

    /**
     * Courses' full names in the plain spelling, in one read whatever their number.
     *
     * @param array $ids Course ids.
     * @return array Course id => name.
     */
    protected static function course_names(array $ids): array {
        global $DB;

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if (!$ids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'facetcourse');
        $ctxselect = \context_helper::get_preload_record_columns_sql('ctx');
        $params['courselevel'] = CONTEXT_COURSE;
        $records = $DB->get_records_sql(
            "SELECT c.id, c.fullname, {$ctxselect}
               FROM {course} c
               JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :courselevel
              WHERE c.id {$insql}",
            $params
        );

        $names = [];
        foreach ($records as $record) {
            \context_helper::preload_from_record($record);
            $names[(int) $record->id] = format_string($record->fullname, true, [
                'context' => context_course::instance((int) $record->id),
                'escape' => false,
            ]);
        }

        return $names;
    }

    /**
     * Rows per category over its whole subtree, from rows per course category.
     *
     * A course in category Y is in category X's subtree when Y is X or Y's path runs through X, the
     * two halves of {@see coursefilter::where()}; so each category's rows are added to it and to
     * every category on its path. One read of the paths, whatever the number of categories.
     *
     * @param array $leaves Category id => rows of the courses directly in it.
     * @return array Category id => rows of its subtree.
     */
    protected static function subtree_counts(array $leaves): array {
        global $DB;

        if (!$leaves) {
            return [];
        }

        $totals = [];
        foreach ($DB->get_records_list('course_categories', 'id', array_keys($leaves), '', 'id, path') as $category) {
            foreach (explode('/', trim((string) $category->path, '/')) as $ancestor) {
                if ($ancestor !== '') {
                    $totals[(int) $ancestor] = ($totals[(int) $ancestor] ?? 0) + (int) $leaves[(int) $category->id];
                }
            }
        }

        return $totals;
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
     * before, by the filter's own subquery. On the site-wide queue, in place of the group and how it
     * was named, the course, its category, the method, and whether the application's group exists
     * (kg found it, the grouped filter's definition). MIN(ue.id) keys each group, so the DML's
     * first-column rule holds without a recordset.
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
        if ($table->has_group_column() && $table->get_instance() !== null) {
            $dims[] = 'COALESCE(kg.id, 0) AS facetgroup';
            $dims[] = applications::named_kind_sql('kgs') . ' AS facetnamed';
            $columns[] = 'facetgroup';
            $columns[] = 'facetnamed';
        }
        if ($table->offers_course_filters()) {
            $dims[] = 'c.id AS facetcourse';
            $dims[] = 'c.category AS facetcategory';
            $dims[] = 'e.id AS facetmethod';
            $dims[] = 'CASE WHEN kg.id IS NULL THEN 0 ELSE 1 END AS facetgrouped';
            array_push($columns, 'facetcourse', 'facetcategory', 'facetmethod', 'facetgrouped');
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
            'course' => 'facetcourse',
            'category' => 'facetcategory',
            'method' => 'facetmethod',
            'grouped' => 'facetgrouped',
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
        if ($name === 'grouped') {
            return [
                applications::GROUPED_YES => get_string('queuegroupwith', 'enrol_apply'),
                applications::GROUPED_NO => get_string('queuegroupnone', 'enrol_apply'),
            ];
        }

        return ['1' => get_string('yes')];
    }

    /**
     * How many rows choosing one option of a facet would list, from that facet's tally.
     *
     * The same reading of each value the filter's predicate makes: a group is the rows of that
     * group; "mine" the rows of the reader's own groups (groupfilter::own_groups(), what the filter
     * selects); "none" the rows naming no group that exists; a named kind its rows; a waited
     * threshold every row past it; grouped the rows whose group exists, or does not; applied before
     * the rows that did.
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
        if ($name === 'grouped') {
            return (int) ($counts[$value === applications::GROUPED_YES ? 1 : 0] ?? 0);
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
            'grouped' => get_string('queuegroup', 'enrol_apply'),
            'course' => get_string('queuefiltercourse', 'enrol_apply'),
            'category' => get_string('queuefiltercategory', 'enrol_apply'),
            'method' => get_string('queuefiltermethod', 'enrol_apply'),
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
        $values += $table->get_course_filters();
        $values += $table->get_facet_filters();

        $applied = [];
        foreach ($values as $name => $value) {
            $applied[] = ['name' => (string) $name, 'value' => (string) $value];
        }

        return $applied;
    }
}
