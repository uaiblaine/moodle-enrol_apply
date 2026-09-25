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

namespace enrol_apply\table;

use context;
use context_course;
use core_table\dynamic as dynamic_table;
use core_table\local\filter\filterset;
use core_table\local\filter\integer_filter;
use core_table\local\filter\string_filter;
use enrol_apply\local\commentlabel;
use enrol_apply\local\identity;
use enrol_apply\local\queue;
use enrol_apply\local\coursefilter;
use enrol_apply\local\queuefilter;
use enrol_apply\local\search;
use enrol_apply\local\submission as submissionrecord;
use enrol_apply\reportbuilder\local\formatters\submission as submissionformatter;
use html_writer;
use moodle_url;
use stdClass;
use user_picture;

/**
 * Table listing the enrolment applications awaiting a decision.
 *
 * Dynamic, so that paging, sorting and filtering refresh the table over
 * core_table_get_dynamic_table_content instead of reloading the page; core resolves the handler
 * as \{component}\table\{handler}.
 *
 * The scope is the risk, and it is why queue::listing_scope() exists. The service builds the
 * table, calls set_filterset() with the client's filters, and then applies one capability check
 * against one context, while this queue has three scopes across two context levels. So only the
 * enrol instance id arrives, and the course, context, mentee list and capability are recomputed
 * from it server-side on every request; nothing a client sends can widen the scope.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  2016 sudile GbR (http://www.sudile.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class applications extends \table_sql implements dynamic_table {
    /**
     * The table's unique id, kept from the former enrol_apply_manage_table class.
     *
     * flexible_table keys stored preferences on it ($SESSION->flextable[<uniqueid>] holds the
     * sort, the collapsed columns and the initials), so renaming it discards every operator's
     * saved sort.
     *
     * @var string
     */
    public const UNIQUEID = 'enrol_apply_manage_table';

    /**
     * @var string The core/checkbox-toggleall group tying the header checkbox, rows and bulk bar.
     *
     * getActionElements() matches this EXACTLY while the targets match by prefix, so the bar's
     * control has to carry the same string character for character. It also must not be a
     * prefix of any other group on the page: Report Builder uses 'report-select-all', which
     * does not collide, and nothing else on manage.php renders one.
     */
    public const TOGGLE_GROUP = 'enrol-apply-queue';

    /** @var stdClass|null Everything this listing is scoped by, from queue::listing_scope(). */
    protected $scope = null;

    /** @var array Identity field names this reader may see, from \enrol_apply\local\identity. */
    protected $extrafields = [];

    /** @var array Snapshot keys this reader may see, memoised by course id; see visible_keys(). */
    protected $visiblekeys = [];

    /** @var string What the listing is narrowed to match, empty for no search. */
    protected $search = '';

    /** @var int|null The {user_enrolments}.status the listing is narrowed to, null for none. */
    protected $status = null;

    /** @var array Token => cleaned value, for the identity fields this listing is narrowed by. */
    protected $fieldfilters = [];

    /** @var int|null Course category the listing is narrowed to, with its subtree. */
    protected $categoryfilter = null;

    /** @var int|null Course the listing is narrowed to. */
    protected $coursefilter = null;

    /** @var string|null Lower bound of the applied-date range as YYYY-MM-DD, null for none. */
    protected $appliedfrom = null;

    /** @var string|null Upper bound of the applied-date range as YYYY-MM-DD, null for none. */
    protected $appliedto = null;

    /** @var array Token => field object, the filters THIS reader is offered; see queuefilter. */
    protected $offeredfilters = [];

    /** @var \stdClass|null The identity SELECT/JOIN, resolved once in set_filterset(). */
    protected $identitysql = null;

    /**
     * @var array Identity field name => the SQL EXPRESSION producing it, from core's get_sql().
     *
     * Not the SELECT aliases in $extrafields: WHERE is evaluated before SELECT, so a predicate
     * cannot name an alias, and a custom profile field's value is a joined table's column.
     */
    protected $identitymappings = [];

    /** @var bool Whether define_table_columns() has run; see setup() for why it is deferred. */
    protected $columnsdefined = false;

    /**
     * Build the table.
     *
     * No argument: the dynamic table service calls `new $tableclass($uniqueid)`, which PHP
     * accepts and ignores, and core's own dynamic tables (core_sms\table\sms_gateway_table,
     * core_admin\table\plugin_management_table) likewise take none and pin the id. The filterset
     * is then the only route by which a caller names a scope.
     */
    public function __construct() {
        parent::__construct(self::UNIQUEID);
    }

    /**
     * The table for one scope, built the way the web service builds it.
     *
     * A named constructor so that the page and its AJAX refreshes establish a scope the same way:
     * a filterset carrying the enrol instance id and the filters, with set_filterset() resolving
     * everything else. $enrolid must be an int, as integer_filter::add_filter_value() throws a
     * TypeError on anything else.
     *
     * An empty search adds no filter: string_filter::add_filter_value() overrides the base class
     * without its rejection of '', so a filter carrying the empty string is live, and
     * set_filterset() has to disarm it again for requests that do not come through here.
     *
     * @param int $enrolid Enrol instance to list, 0 for every one this operator may decide in.
     * @param string $search Text to narrow the listing to, empty for none.
     * @param int|null $status {user_enrolments}.status to narrow to, null for none.
     * @param array $filters Field-filter token or date-bound name => raw value.
     * @return self The table, scoped.
     */
    public static function for_scope(
        int $enrolid,
        string $search = '',
        ?int $status = null,
        array $filters = []
    ): self {
        $filterset = new applications_filterset();
        $filterset->add_filter(new integer_filter('enrolid', null, [$enrolid]));

        if (trim($search) !== '') {
            $filterset->add_filter(new string_filter('search', null, [trim($search)]));
        }

        if ($status !== null) {
            $filterset->add_filter(new integer_filter('status', null, [$status]));
        }

        /* The field and date filters, added by name as the web service adds them. Core throws on
           a name the filterset does not declare, so request_filters() only produces declared ones. */
        foreach ($filters as $name => $value) {
            if (trim((string) $value) !== '') {
                $filterset->add_filter(new string_filter($name, null, [trim((string) $value)]));
            }
        }

        $table = new self();
        $table->set_filterset($filterset);

        return $table;
    }

    /**
     * Take the client's filters, resolve the scope from them, and build the query.
     *
     * check_validity() is called here because the dynamic table service never calls it, so the
     * "required" filters of applications_filterset are enforced by this line alone.
     *
     * The scope is resolved before parent::set_filterset(), which calls guess_base_url(), and
     * the url is built from the scope. The columns are defined later still; see out().
     *
     * @param filterset $filterset Filters as the client sent them.
     * @return void
     */
    public function set_filterset(filterset $filterset): void {
        $filterset->check_validity();

        /* check_validity() proves the filter is present, not that it holds a value. A filter
           with an empty value list is a valid request to the service, and current() answers null
           for it; (int) null would be 0, the widest scope this queue has. So an empty filter is
           refused with the same exception as a missing one. */
        $enrolid = $filterset->get_filter('enrolid')->current();
        if ($enrolid === null) {
            throw new \moodle_exception('missingrequiredfields', 'core_table', '', 'enrolid');
        }

        $this->scope = queue::listing_scope((int) $enrolid);

        /* The identity fields are resolved here rather than in build_sql() because
           parent::set_filterset() calls guess_base_url(), whose url_params() needs the validated
           field filters; otherwise page two of a filtered queue would be a different queue. */
        $this->extrafields = identity::fields($this->scope->identitycontext);
        $this->identitysql = identity::sql($this->scope->identitycontext, 'u');
        // Field name => the expression producing it, which is what a WHERE clause can name.
        $this->identitymappings = $this->identitysql->mappings ?? [];
        $this->offeredfilters = queuefilter::resolve($this->identitymappings);

        /* Read BEFORE parent::set_filterset(), which calls guess_base_url() - the base url has to
           carry these or the no-JavaScript path loses them on the first page turn. */
        $this->search = '';
        if ($filterset->has_filter('search')) {
            /* Trimmed, because string_filter accepts '' and a request to
               core_table_get_dynamic_table_content that neither for_scope() nor manage.js built may
               carry it; an empty term must mean no search rather than a narrowing one. */
            $this->search = trim((string) $filterset->get_filter('search')->current());
        }

        $this->status = null;
        if ($filterset->has_filter('status')) {
            $status = $filterset->get_filter('status')->current();
            if ($status !== null) {
                $this->status = (int) $status;
            }
        }

        /* The course and the category, on the site-wide queue only (see coursefilter::offered()).
           String filters like the dates, because the AMD module sends every filter-bar control the
           same way. Validated here: a course with no apply method, or a category that does not
           exist, is no filter. */
        $this->categoryfilter = null;
        $this->coursefilter = null;
        if (coursefilter::offered($this->scope)) {
            if ($filterset->has_filter('course')) {
                $this->coursefilter = coursefilter::clean_course(
                    (int) $filterset->get_filter('course')->current()
                );
            }
            if ($filterset->has_filter('category')) {
                $this->categoryfilter = coursefilter::clean_category(
                    (int) $filterset->get_filter('category')->current()
                );
            }
        }

        /* One entry per field this reader is offered, never per filter the request carried. The
           filterset declares the site's whole list, so core refuses a forged name; this loop
           ignores a real field this reader may not see in this scope. */
        $this->fieldfilters = [];
        foreach ($this->offeredfilters as $token => $offered) {
            if (!$filterset->has_filter($token)) {
                continue;
            }
            $value = queuefilter::clean($offered, (string) $filterset->get_filter($token)->current());
            if ($value !== null) {
                $this->fieldfilters[$token] = $value;
            }
        }

        $this->appliedfrom = $this->date_filter($filterset, 'appliedfrom');
        $this->appliedto = $this->date_filter($filterset, 'appliedto');

        parent::set_filterset($filterset);

        $this->build_sql();
    }

    /**
     * Render the table, having defined its columns first.
     *
     * The columns cannot be defined in set_filterset(): select_all_header() renders through
     * $OUTPUT, and the dynamic table service calls set_filterset() before validate_context(), so
     * on the AJAX refresh path there is no page context yet and rendering throws "$PAGE->context
     * was not set". Page loads do not show this, because manage.php sets the context first.
     *
     * Nor can they be left to setup(): sql_table::out() runs an extra query (joins and EXISTS
     * subquery included) to name the columns when none are defined before it calls setup().
     * Defining them here, before core's out() is entered, avoids that query.
     *
     * @param int $pagesize Rows per page.
     * @param bool $useinitialsbar Ignored downstream; see initialbars().
     * @param string $downloadhelpbutton Passed through to core.
     * @return void
     */
    public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '') {
        $this->define_columns_once();

        parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);
    }

    /**
     * Define the columns before core sets the table up.
     *
     * The other entry point, for a caller that reaches setup() without going through out().
     *
     * @return bool False when the table cannot be set up, as core's own does.
     */
    public function setup() {
        $this->define_columns_once();

        return parent::setup();
    }

    /**
     * Define the columns, once, however many of the entry points above are reached.
     *
     * Core's define_columns() is itself idempotent (it rebuilds the column list from scratch);
     * the flag only saves repeating the header rendering and the comment-label resolution.
     *
     * @return void
     */
    protected function define_columns_once(): void {
        if ($this->columnsdefined) {
            return;
        }

        $this->define_table_columns();
        $this->columnsdefined = true;
    }

    /**
     * The context this table is read in.
     *
     * Required: flexible_table's version throws for a dynamic table, and the dynamic table
     * service calls it for validate_context().
     *
     * Never null: a refusal is carried by has_capability() below, which is why
     * queue::listing_scope() answers an unresolvable id with the system context and allowed
     * false.
     *
     * @return context The scope's context.
     */
    public function get_context(): context {
        return $this->scope->context;
    }

    /**
     * Whether this operator may read this listing at all.
     *
     * The capability half only. The course-ACCESS half is applied on this path by
     * external_api::validate_context(), which calls require_login() from the context above, and on
     * the page path by manage.php itself.
     *
     * @return bool True when the listing may be read.
     */
    public function has_capability(): bool {
        return (bool) $this->scope->allowed;
    }

    /**
     * The url paging and sorting link back to.
     *
     * Required for a dynamic table (flexible_table's version throws), and called by
     * set_filterset(), which is why the scope is resolved first. Every filter has to be carried
     * here, or the no-JavaScript path loses it on the first page turn.
     *
     * The "Show all / Show per page" link is built from $PAGE->url instead, so manage.php has to
     * put the same parameters into $PAGE->set_url(); it takes them from request_filters().
     *
     * @return void
     */
    public function guess_base_url(): void {
        $this->baseurl = new moodle_url('/enrol/apply/manage.php', $this->url_params());
    }

    /**
     * Everything the listing is narrowed by BEFORE any filter the operator applied.
     *
     * Extracted from build_sql() so that scope_total() can count the same set without the
     * filters. The two must not drift: the count line reads "N of M", and an M computed from a
     * different predicate than the N would be a number nobody can reconcile with the page.
     *
     * Only ue and e are referenced, so a caller may join as little as those two tables.
     *
     * @return array [where fragments, parameters].
     */
    protected function scope_where(): array {
        global $DB;

        // The one definition of "awaiting a decision"; see the method for both its clauses.
        [$wheres, $params] = queue::awaiting_decision_where();

        if ($this->scope->enrolid) {
            $wheres[] = 'e.id = :enrolid';
            $params['enrolid'] = $this->scope->enrolid;
        } else {
            $wheres[] = 'e.enrol = :enrol';
            $params['enrol'] = 'apply';
        }

        if ($this->scope->mentees !== null) {
            if (!$this->scope->mentees) {
                // No mentees means nothing to show; a never-true predicate keeps the SQL valid.
                $wheres[] = '1 = 0';
            } else {
                [$insql, $inparams] = $DB->get_in_or_equal($this->scope->mentees, SQL_PARAMS_NAMED, 'mentee');
                $wheres[] = "ue.userid {$insql}";
                $params += $inparams;
            }
        }

        return [$wheres, $params];
    }

    /**
     * How many applications this operator's queue holds before any filter narrows it.
     *
     * The "M" of the "N of M" count line, and the figure the capacity header's first tile
     * reports. That tile must not use the filtered totalrows, which could fall below the deferred
     * count it sits beside (read unfiltered from \enrol_apply\local\capacity).
     *
     * One COUNT over two tables, and only when something is narrowing; otherwise it equals
     * totalrows, which the table has already computed.
     *
     * @return int Applications in scope, unfiltered.
     */
    public function scope_total(): int {
        global $DB;

        if (!$this->is_narrowed()) {
            return (int) $this->totalrows;
        }

        [$wheres, $params] = $this->scope_where();

        return (int) $DB->count_records_sql(
            'SELECT COUNT(1)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ' . implode(' AND ', $wheres),
            $params
        );
    }

    /**
     * Whether the operator has narrowed this listing at all.
     *
     * @return bool True when a search term, status, field, date, course or category filter is applied.
     */
    public function is_narrowed(): bool {
        return $this->search !== ''
            || $this->status !== null
            || $this->fieldfilters !== []
            || $this->appliedfrom !== null
            || $this->appliedto !== null
            || $this->categoryfilter !== null
            || $this->coursefilter !== null;
    }

    /**
     * One date bound off the filterset, kept as the operator typed it.
     *
     * Stored as the YYYY-MM-DD string rather than as a timestamp, because it has to go back into
     * the url and into the control the operator is looking at; the timestamps are computed where
     * the predicate is built.
     *
     * @param filterset $filterset The client's filters.
     * @param string $name appliedfrom or appliedto.
     * @return string|null The date, or null when absent or malformed.
     */
    protected function date_filter(filterset $filterset, string $name): ?string {
        if (!$filterset->has_filter($name)) {
            return null;
        }

        $value = trim((string) $filterset->get_filter($name)->current());

        // Validated by the same helper that turns it into a boundary, so the two cannot disagree.
        return queuefilter::day_bounds($value, null)[0] === null ? null : $value;
    }

    /**
     * The enrolment statuses this queue may be narrowed to.
     *
     * The renderer builds the status select from it and manage.php validates the query string
     * against it. It is exactly the two states queue::awaiting_decision_where() can leave a row
     * in: ACTIVE is excluded by that predicate and a cancelled application has no row at all.
     *
     * The validation matters because the select's "any status" option submits `status=`, which
     * PARAM_INT cleans to 0 (ENROL_USER_ACTIVE), a status no listed row can hold; an unrecognised
     * value must mean "no filter".
     *
     * @return array The statuses, as ints.
     */
    public static function filterable_statuses(): array {
        global $CFG;

        // ENROL_APPLY_USER_WAIT lives in the plugin's lib.php, which is not autoloaded.
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        return [ENROL_USER_SUSPENDED, ENROL_APPLY_USER_WAIT];
    }

    /**
     * The search term this listing is narrowed by.
     *
     * @return string The term, empty when none is applied.
     */
    public function get_search(): string {
        return $this->search;
    }

    /**
     * The enrolment status this listing is narrowed to.
     *
     * @return int|null The status, null when none is applied.
     */
    public function get_status(): ?int {
        return $this->status;
    }

    /**
     * The filters this listing carries, as query-string parameters.
     *
     * Read by guess_base_url() and by the renderer's filter chips. manage.php builds the page url
     * and the decision form's action from request_filters() instead, and the two must agree: a
     * disagreement drops the operator into a differently filtered queue after every decision.
     *
     * @return array Parameters for /enrol/apply/manage.php.
     */
    public function url_params(): array {
        $params = [];

        if ($this->scope->enrolid) {
            $params['id'] = (int) $this->scope->enrolid;
        }
        if ($this->search !== '') {
            $params['search'] = $this->search;
        }
        if ($this->status !== null) {
            $params['status'] = $this->status;
        }
        foreach ($this->fieldfilters as $token => $value) {
            $params[$token] = $value;
        }
        if ($this->appliedfrom !== null) {
            $params['appliedfrom'] = $this->appliedfrom;
        }
        if ($this->appliedto !== null) {
            $params['appliedto'] = $this->appliedto;
        }
        if ($this->categoryfilter !== null) {
            $params['category'] = $this->categoryfilter;
        }
        if ($this->coursefilter !== null) {
            $params['course'] = $this->coursefilter;
        }

        return $params;
    }

    /**
     * The filters the operator has applied, for the renderer to draw and to build chips from.
     *
     * @return array Token => cleaned value.
     */
    public function get_field_filters(): array {
        return $this->fieldfilters;
    }

    /**
     * The filters this reader is offered at all.
     *
     * @return array Token => field object, from \enrol_apply\local\queuefilter::resolve().
     */
    public function get_offered_filters(): array {
        return $this->offeredfilters;
    }

    /**
     * The applied-date bounds as the operator typed them.
     *
     * @return array [from, to], each YYYY-MM-DD or null.
     */
    public function get_applied_dates(): array {
        return [$this->appliedfrom, $this->appliedto];
    }

    /**
     * The course and category this listing is narrowed to, for the renderer to draw and chip.
     *
     * @return array [categoryid, courseid], each an int or null.
     */
    public function get_course_scope(): array {
        return [$this->categoryfilter, $this->coursefilter];
    }

    /**
     * Whether this scope offers the course and category controls at all.
     *
     * @return bool True on the site-wide queue and nowhere else.
     */
    public function offers_course_filters(): bool {
        return coursefilter::offered($this->scope);
    }

    /**
     * Which parameters the queue reads off a url, and how each one is read.
     *
     * Called by manage.php, which builds both the table and the page url from the result, so the
     * listing and its address agree about what is applied. Only fields this reader is offered in
     * the scope are read, as in set_filterset().
     *
     * It returns the cleaned set, validated by the same helpers set_filterset() uses
     * (queuefilter::clean(), coursefilter, queuefilter::day_bounds()), so a value the table
     * ignores never reaches the page url or the decision form's action.
     *
     * @param \stdClass $listing The scope, from \enrol_apply\local\queue::listing_scope().
     * @return array Parameter name => cleaned value, for the ones that narrow anything.
     */
    public static function request_filters(\stdClass $listing): array {
        $filters = [];

        foreach (queuefilter::resolve(identity::sql($listing->identitycontext, 'u')->mappings ?? []) as $token => $offered) {
            // Passed untrimmed: a select's vocabulary may hold a leading or trailing space.
            $value = queuefilter::clean($offered, optional_param($token, '', PARAM_NOTAGS));
            if ($value !== null) {
                $filters[$token] = $value;
            }
        }

        if (coursefilter::offered($listing)) {
            $course = coursefilter::clean_course(optional_param('course', 0, PARAM_INT));
            if ($course !== null) {
                $filters['course'] = $course;
            }
            $category = coursefilter::clean_category(optional_param('category', 0, PARAM_INT));
            if ($category !== null) {
                $filters['category'] = $category;
            }
        }

        foreach (['appliedfrom', 'appliedto'] as $bound) {
            /* PARAM_ALPHANUMEXT is a transport charset, not a date check (`not-a-date` survives
               it); the shape is checked by queuefilter::day_bounds(), as in date_filter(). */
            $value = trim(optional_param($bound, '', PARAM_ALPHANUMEXT));
            if ($value !== '' && queuefilter::day_bounds($value, null)[0] !== null) {
                $filters[$bound] = $value;
            }
        }

        return $filters;
    }

    /**
     * The columns a search term is matched against.
     *
     * Only what this reader can already see on the row in this scope: the name, the identity
     * fields, the comment, and the course where the course column exists. A column merely present
     * in the SELECT is not eligible: for_userpic() selects u.email in every scope, including the
     * mentee scope where no identity field is shown, and a hit count would disclose the address.
     *
     * Identity fields are matched through their expressions (see $identitymappings), not their
     * SELECT aliases.
     *
     * The snapshot is deliberately absent: it is masked per row by visible_keys(), and a search
     * cannot honour a per-row mask.
     *
     * @return array SQL expressions to match.
     */
    protected function search_columns(): array {
        global $DB;

        $columns = [$DB->sql_fullname('u.firstname', 'u.lastname')];

        foreach ($this->identitymappings as $expression) {
            $columns[] = $expression;
        }

        // The same expression col_applycomment() renders, not its SELECT alias.
        $columns[] = 'COALESCE(s.comment, ai.comment)';

        if ($this->scope->instance === null) {
            $columns[] = 'c.fullname';
        }

        return $columns;
    }

    /**
     * The predicate matching the operator's search term.
     *
     * One placeholder name per column, all bound to the same value: fix_sql_params() rejects a
     * reused placeholder.
     *
     * has_unaccent() is resolved once here rather than inside like_ai(), so the searched columns
     * cost one catalogue lookup rather than one each.
     *
     * sql_like_escape() keeps a "%" or "_" in the term literal rather than a wildcard.
     *
     * @return array [where fragment, parameters].
     */
    protected function search_where(): array {
        global $DB;

        $unaccent = search::has_unaccent();
        $value = '%' . $DB->sql_like_escape($this->search) . '%';

        $likes = [];
        $params = [];
        $index = 0;
        foreach ($this->search_columns() as $column) {
            $name = 'searchterm' . (++$index);
            $likes[] = search::like_ai($column, ':' . $name, $unaccent);
            $params[$name] = $value;
        }

        return ['(' . implode(' OR ', $likes) . ')', $params];
    }

    /**
     * Build the query for the resolved scope.
     *
     * @return void
     */
    protected function build_sql(): void {
        global $DB;

        [$wheres, $params] = $this->scope_where();

        /* The identity fields and their SQL were resolved in set_filterset(). Which fields those
           are is core's decision - see \enrol_apply\local\identity - so the queue and the
           participants page cannot answer differently. */

        $userfieldsapi = \core_user\fields::for_userpic()->including('username');
        $userfields = $userfieldsapi->get_sql('u', false, '', 'userid', false)->selects;

        /* The comment is read from the durable record first and from the application info row
           only as a fallback, for applications that predate the durable record. The info row is
           deleted on approval, and an approved enrolment can come back to this queue: suspending
           it from the participants page leaves status != active with timeend = 0.

           Joined on the user enrolment, not on courseid + userid, which is not unique: an
           applicant who was cancelled and applied again has two records for the course. */
        /* Whether this applicant applied to this course before: a badge prompting the operator
           to open the review page.

           A correlated EXISTS rather than a join, so the row count cannot change: (courseid,
           userid) is not unique, and a join would multiply the row per earlier application. It
           reads the courseuser index.

           `s.id IS NULL OR prior.id <> s.id` excludes the row's own record; the null branch is
           for applications that predate the durable record and have no s.id. */
        $priorsql = "CASE WHEN EXISTS (
                            SELECT 1
                              FROM {enrol_apply_submission} prior
                             WHERE prior.courseid = c.id
                               AND prior.userid = ue.userid
                               AND (s.id IS NULL OR prior.id <> s.id)
                          ) THEN 1 ELSE 0 END AS appliedbefore";

        /* s.userinfodata, the frozen snapshot, comes from the row already joined for the comment.
           It has no fallback: enrol_apply_applicationinfo never held a snapshot, so an
           application predating the durable record shows no evidence. */
        $fields = "ue.id AS userenrolmentid, ue.status AS enrolstatus, ue.timecreated AS applydate,
                   COALESCE(s.comment, ai.comment) AS applycomment, s.userinfodata AS snapshot,
                   c.fullname AS course,
                   c.id AS courseid, {$priorsql}, {$userfields}{$this->identitysql->selects}";
        $from = "{user_enrolments} ue
            LEFT JOIN {enrol_apply_applicationinfo} ai ON ai.userenrolmentid = ue.id
            LEFT JOIN {enrol_apply_submission} s ON s.userenrolmentid = ue.id
                 JOIN {user} u ON u.id = ue.userid
                 JOIN {enrol} e ON e.id = ue.enrolid
                 JOIN {course} c ON c.id = e.courseid
                 {$this->identitysql->joins}";

        /* The operator's own filters, last: everything above is the scope, everything below the
           narrowing, which is the split scope_total() counts across. */
        if ($this->search !== '') {
            [$searchwhere, $searchparams] = $this->search_where();
            $wheres[] = $searchwhere;
            $params += $searchparams;
        }

        /* The enrolment's status, not the record's: an approved participant later suspended from
           the participants page re-enters this queue as APPROVED on the record and SUSPENDED on
           the enrolment, and the queue lists what awaits a decision now. The option labels use the
           record's wording, which the operator reads on the review page. */
        if ($this->status !== null) {
            $wheres[] = 'ue.status = :statusfilter';
            $params['statusfilter'] = $this->status;
        }

        /* One predicate per applied field filter, over the expression core's identity mapping
           produced (see $identitymappings).

           The placeholder names are prefixed because this statement already binds `now`,
           `active`, `enrolid` or `enrol`, `mentee<N>`, `searchterm<N>` and `statusfilter`; a
           collision would make fix_sql_params() throw. */
        $unaccent = null;
        $index = 0;
        foreach ($this->fieldfilters as $token => $value) {
            $offered = $this->offeredfilters[$token];
            $name = 'queuefilter' . (++$index);

            if ($offered->control === 'select') {
                /* A closed vocabulary is compared for equality through sql_equal(), case-insensitive
                   and accent-sensitive, which every database family can express: LOWER() on both
                   sides, plus the charset's _bin collation on MySQL and MariaDB. A bare `=` is case
                   sensitive on PostgreSQL but follows the column's usually case- and
                   accent-insensitive collation on MariaDB.

                   Case must not matter because an administrator may re-case a menu option while
                   {user_info_data} keeps the spelling it was written with. Accents must, because
                   two options differing only by one ("Pais" and "País") are two members of the
                   vocabulary, and accent-insensitive equality is what MySQL and MariaDB alone
                   would give. */
                $wheres[] = $DB->sql_equal($offered->expression, ':' . $name, false, true);
                $params[$name] = $value;
                continue;
            }

            $unaccent = $unaccent ?? search::has_unaccent();
            $wheres[] = search::like_ai($offered->expression, ':' . $name, $unaccent);
            $params[$name] = '%' . $DB->sql_like_escape($value) . '%';
        }

        /* The course and the category, on indexed columns ({course}.category and {course}.id),
           unlike the search's LIKE, which can only scan. */
        [$coursewheres, $courseparams] = coursefilter::where($this->categoryfilter, $this->coursefilter);
        foreach ($coursewheres as $coursewhere) {
            $wheres[] = $coursewhere;
        }
        $params += $courseparams;

        /* The applied-date range, as whole days in the reader's timezone. The upper bound is the
           midnight starting the following day, compared with a strict less-than, so the "to" date
           is inclusive without assuming a day is 86400 seconds. */
        [$fromstamp, $tostamp] = queuefilter::day_bounds($this->appliedfrom, $this->appliedto);
        if ($fromstamp !== null) {
            $wheres[] = 'ue.timecreated >= :appliedfromstamp';
            $params['appliedfromstamp'] = $fromstamp;
        }
        if ($tostamp !== null) {
            $wheres[] = 'ue.timecreated < :appliedtostamp';
            $params['appliedtostamp'] = $tostamp;
        }

        $this->set_sql($fields, $from, implode(' AND ', $wheres), $params + $this->identitysql->params);
    }

    /**
     * The sort, with a unique final key so that two applications never trade places.
     *
     * Every column this table offers can tie: `applydate` is `ue.timecreated` in whole seconds,
     * so a batch of applications shares a value, and `course` and `fullname` tie more easily
     * still. Without a unique key each page (a separate statement with its own LIMIT and OFFSET)
     * may order a tied group differently, so a row can appear on two pages and another on none.
     *
     * Core's fallback does not cover this: set_sorting_preferences() appends
     * `sort_default_column`, which here is `applydate`, itself a key that ties.
     *
     * This method is the injection point: construct_order_by() is static and called through
     * `self::`, so an override is never reached, and appending to get_sql_sort()'s string would
     * land after core's per-driver NULL ordering. Core does the same in tool_policy, mod_quiz and
     * mod_assign. The raw `ue.id` rather than its alias does not depend on the SELECT list.
     *
     * @return array Column name => SORT_ASC or SORT_DESC, ending in a unique key.
     */
    public function get_sort_columns() {
        $sortcolumns = parent::get_sort_columns();
        $sortcolumns['ue.id'] = SORT_ASC;

        return $sortcolumns;
    }

    /**
     * Declare the columns and their headings.
     *
     * The comment heading comes from the scope's instance. The site-wide and mentee scopes span
     * instances, each of which may word the question differently, so they carry no instance and
     * get the shipped wording.
     *
     * @return void
     */
    protected function define_table_columns(): void {
        $columns = ['checkboxcolumn'];
        $headers = [$this->select_all_header()];

        /* The course, only for the site-wide and mentee scopes, which span courses; every row of
           an instance-scoped queue belongs to the same course. */
        if ($this->scope->instance === null) {
            $columns[] = 'course';
            $headers[] = get_string('course');
        }

        $columns[] = 'fullname';
        // The heading of a column named 'fullname' is filled in by table_sql itself.
        $headers[] = 'fullname';

        /* The identity fields are not columns: they form a second line inside the applicant's
           cell, so a variable, capability-gated field list fits the table. They lose sortability;
           the field filters stand in for it. */

        $columns[] = 'applydate';
        $headers[] = get_string('applydate', 'enrol_apply');

        /* The answers the applicant gave, which the decision is made on.

           Absent on the mentee scope (`identitycontext === null`), which shows no identity data:
           these answers are identity data of the same kind (city, institution, custom profile
           fields). visible_keys() could mask them per row there too; leaving them out is a
           product decision, not a technical limit. */
        if ($this->scope->identitycontext !== null) {
            $columns[] = 'snapshot';
            $headers[] = get_string('queuesubmitted', 'enrol_apply');
        }

        $columns[] = 'applycomment';
        /* The escaped spelling, because print_headers() emits this through html_writer::tag(),
           which concatenates its content without escaping it. commentlabel::custom() defaults to
           that spelling. */
        $headers[] = $this->scope->instance === null
            ? get_string('applycomment', 'enrol_apply')
            : commentlabel::custom($this->scope->instance);

        /* A link to each application's review page. The header is empty on purpose: every
           button carries its own accessible name. */
        $columns[] = 'review';
        $headers[] = '';

        $this->define_columns($columns);
        $this->define_headers($headers);

        /* Names the cell that identifies each row, so table_sql emits it as a
           <th scope="row"> and a screen reader announces every other cell of the row
           against the applicant's name rather than reading a wall of bare values. */
        $this->define_header_column('fullname');
        $this->no_sorting('checkboxcolumn');
        /* Unsortable: the column renders pairs out of a JSON envelope, which SQL cannot order by;
           see also search_columns() on why it must stay out of SQL. */
        $this->no_sorting('snapshot');
        $this->no_sorting('applycomment');
        $this->no_sorting('review');
        $this->sortable(true, 'applydate', SORT_ASC);
    }

    /**
     * What an empty result says, which depends on why it is empty.
     *
     * Core's "Nothing to display" is right for a queue with no applications, but a filter that
     * matched nothing gets a message naming the filter, so it does not read as an empty queue.
     *
     * The unfiltered branch delegates to core rather than reproducing its markup. A Behat
     * scenario in tests/behat/enrol_apply.feature asserts core's string on the unfiltered empty
     * queue.
     *
     * @return void
     */
    public function print_nothing_to_display() {
        global $OUTPUT;

        if (!$this->is_narrowed()) {
            parent::print_nothing_to_display();

            return;
        }

        echo $this->get_dynamic_table_html_start();
        echo $this->render_reset_button();
        echo $OUTPUT->notification(get_string('queuefilterempty', 'enrol_apply'), 'info', false);
        echo $this->get_dynamic_table_html_end();
    }

    /**
     * A cell's own heading, for the card the row becomes below the breakpoint.
     *
     * Real text in the markup rather than `content: attr(data-label)`: CSS-generated content is
     * announced inconsistently by screen readers, and turning rows and cells into blocks loses
     * the table semantics that tie a value to its thead heading. ARIA roles were rejected because
     * flexible_table offers no hook for row attributes, and role="cell" without a role="row"
     * ancestor is worse than none. Hidden above the breakpoint by styles.css.
     *
     * The caller passes the escaped spelling: html_writer::span() does not escape its content.
     *
     * @param string $label Heading for this cell, already escaped.
     * @return string The heading markup, to prefix the cell's own content with.
     */
    protected function card_label(string $label): string {
        return html_writer::span($label, 'enrol_apply-cardlabel');
    }

    /**
     * The select-all checkbox shown in the header of the checkbox column.
     *
     * Core's renderable, driven by core/checkbox-toggleall. The label is the same string in both
     * states because the module rewrites it on every toggle, and a header alternating between
     * "Select all" and "Deselect all" would change width.
     *
     * @return string Rendered checkbox with its accessible label.
     */
    protected function select_all_header() {
        global $OUTPUT;

        $selectall = get_string('selectall');

        return $OUTPUT->render(new \core\output\checkbox_toggleall(self::TOGGLE_GROUP, true, [
            'id' => 'enrol_apply_toggleall',
            'name' => 'enrol_apply_toggleall',
            'label' => $selectall,
            'labelclasses' => 'visually-hidden',
            'classes' => 'm-1',
            'checked' => false,
            'selectall' => $selectall,
            'deselectall' => $selectall,
        ]));
    }

    /**
     * The per-row selection checkbox.
     *
     * @param stdClass $row Row data.
     * @return string Rendered checkbox with its accessible label.
     */
    public function col_checkboxcolumn($row) {
        global $OUTPUT;

        /* The name and value are manage.php's POST contract (userenrolments[]), and the label is
           what Behat locates the checkbox by ("Select Student 1"). */
        return $OUTPUT->render(new \core\output\checkbox_toggleall(self::TOGGLE_GROUP, false, [
            'id' => 'enrol_apply_ue_' . $row->userenrolmentid,
            'name' => 'userenrolments[]',
            'value' => $row->userenrolmentid,
            'label' => get_string('selectapplicant', 'enrol_apply', fullname($row)),
            'labelclasses' => 'visually-hidden',
        ]));
    }

    /**
     * No initials BAR either, whatever the caller asks for.
     *
     * The other half of get_sql_where() below: that stops the filter, this stops the A-Z bar
     * being drawn, which would otherwise do nothing when clicked.
     *
     * An override, because both callers pass true: renderer::capture_table() to out(), and the
     * dynamic table service calls out($pagesize, true), which query_db() turns into
     * initialbars(true). With use_initials false, print_initials_bar() draws nothing.
     *
     * @param bool $bool Ignored.
     * @return void
     */
    public function initialbars($bool) {
        parent::initialbars(false);
    }

    /**
     * No initials filter, on either path.
     *
     * Hiding the A-Z bar is not enough: core's get_sql_where() reads `prefs['i_first']` and
     * `prefs['i_last']` without consulting `use_initials`, and query_db() applies the result to
     * both the count and the data query. The preference lives in $SESSION->flextable, so a
     * stale initial would silently empty the queue with no control on screen to explain it; the
     * dynamic table service can also set it from the request's firstinitial and lastinitial.
     *
     * Emptying $userfullnamecolumns would also stop the filter, but would cost the fullname
     * column its firstname/lastname sort links.
     *
     * @return array Empty where clause and no parameters.
     */
    public function get_sql_where() {
        return ['', []];
    }

    /**
     * The applicant: their picture, their name, what is unusual about them, and who they are.
     *
     * One cell rather than four columns because the identity list varies per site and per
     * reader; as a second line it wraps instead of changing the table's shape.
     *
     * This is the escaping boundary for the cell: flexible_table writes a cell's value into the
     * markup unescaped, and identity fields are user-controlled text, so every value below that
     * is not already a link or a lang string goes through s().
     *
     * The name links to the profile rather than opening a summary modal, which would be a second
     * rendering of the profile to keep in step.
     *
     * @param stdClass $row Row data carrying the aliased user picture fields.
     * @return string Rendered cell.
     */
    public function col_fullname($row) {
        global $CFG, $OUTPUT;

        /* ENROL_APPLY_USER_WAIT is defined in lib.php, which is not autoloaded. manage.php
           includes it, but the dynamic table service does not, so without this line an AJAX
           refresh fails. */
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        $user = user_picture::unalias($row, ['username'], 'userid');

        $name = html_writer::link(
            new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $row->courseid]),
            fullname($user),
            ['class' => 'enrol_apply-applicantname']
        );

        $badges = '';
        if ($row->enrolstatus == ENROL_APPLY_USER_WAIT) {
            /* An explicit text colour on every badge fill: Bootstrap 5's .badge defaults to white
               text, which gives 1.95:1 on this light fill against the 4.5:1 floor. */
            $badges .= html_writer::span(
                get_string('queuewaitinglist', 'enrol_apply'),
                'badge bg-warning text-dark me-1'
            );
        }
        if (!empty($row->appliedbefore)) {
            $badges .= html_writer::span(
                get_string('queueappliedbefore', 'enrol_apply'),
                'badge bg-secondary text-dark me-1'
            );
        }

        $identity = [];
        foreach ($this->extrafields as $field) {
            $value = (string) ($row->{$field} ?? '');
            if ($value !== '') {
                $identity[] = html_writer::span(s($value), 'enrol_apply-identityvalue');
            }
        }

        $lines = html_writer::div($name . ($badges !== '' ? ' ' . $badges : ''), 'enrol_apply-applicantline');
        if ($identity) {
            $lines .= html_writer::div(
                implode('', $identity),
                'enrol_apply-identityline small text-muted'
            );
        }

        return html_writer::div(
            $OUTPUT->user_picture($user, ['popup' => true]) . html_writer::div($lines, 'enrol_apply-applicanttext'),
            'enrol_apply-applicant'
        );
    }

    /**
     * The door to this one application.
     *
     * @param stdClass $row Row data.
     * @return string Rendered cell.
     */
    public function col_review($row) {
        $user = user_picture::unalias($row, ['username'], 'userid');

        /* The aria-label names the applicant, as the visible word is "Review" on every row. Not a
           title, which is not announced reliably and is invisible to a keyboard user. */
        return html_writer::link(
            new moodle_url('/enrol/apply/manage.php', ['userenrol' => $row->userenrolmentid]),
            get_string('queuereview', 'enrol_apply'),
            [
                'class' => 'btn btn-secondary btn-sm',
                'aria-label' => get_string('queuereviewapplicant', 'enrol_apply', fullname($user)),
            ]
        );
    }

    /**
     * The course column, linking to the course.
     *
     * @param stdClass $row Row data.
     * @return string Rendered cell.
     */
    public function col_course($row) {
        $url = new moodle_url('/course/view.php', ['id' => $row->courseid]);

        return $this->card_label(s(get_string('course')))
            . html_writer::link($url, format_string($row->course), ['target' => '_blank']);
    }

    /**
     * The application date column.
     *
     * @param stdClass $row Row data.
     * @return string Rendered cell.
     */
    public function col_applydate($row) {
        /* How long the applicant has been waiting, with the exact date underneath. */
        return $this->card_label(s(get_string('applydate', 'enrol_apply')))
            . html_writer::div(format_time(time() - $row->applydate), 'enrol_apply-applyago')
            . html_writer::div(
                userdate($row->applydate, get_string('strftimedatetimeshort', 'langconfig')),
                'enrol_apply-applyon small text-muted'
            );
    }

    /**
     * The snapshot keys this reader may see for one row, judged in that row's own course.
     *
     * Per row, not per scope: has_capability() walks upward from the context it is given, so a
     * check at the system context does not see a CAP_PROHIBIT set in one course, and a scope-level
     * mask would show that course's snapshots to an operator prohibited there. This matches
     * renderer::snapshot_context(), which masks in the application's course context.
     *
     * The identity line beside it uses the scope's context because identity::fields() decides the
     * SELECT list, one per statement; the snapshot is rendered per row and has no such limit.
     *
     * Memoised per course, as a site-wide queue spans courses.
     *
     * @param int $courseid Course the row's application was made to.
     * @return array|bool Keys this reader may see, or submissionformatter::ALL_FIELDS.
     */
    protected function visible_keys(int $courseid) {
        if (!array_key_exists($courseid, $this->visiblekeys)) {
            $this->visiblekeys[$courseid] = submissionformatter::visible_keys(
                context_course::instance($courseid)
            );
        }

        return $this->visiblekeys[$courseid];
    }

    /**
     * What the applicant submitted with this application.
     *
     * Read from the frozen snapshot only, with its stored labels, never re-resolved against the
     * live instance or profile; see renderer::snapshot_context() for why.
     *
     * No "not given" marker: fields::submitted_values() never records an empty answer, so a field
     * left blank and one never offered are the same absence, and telling them apart would need
     * that live re-resolution.
     *
     * An escaping boundary: flexible_table writes the cell unescaped, and both the value and a
     * custom field's label are user-controlled. s() on both, not format_string(), whose
     * strip_tags() would delete a restored value from the first "<" onwards.
     *
     * Returns nothing, card label included, when there is nothing to show.
     *
     * @param stdClass $row Row data carrying the stored envelope as `snapshot`.
     * @return string Rendered cell.
     */
    public function col_snapshot($row) {
        $visible = $this->visible_keys((int) $row->courseid);

        $pills = '';
        foreach (submissionrecord::read_snapshot($row->snapshot ?? null) as $entry) {
            if ($visible !== submissionformatter::ALL_FIELDS && !in_array($entry['key'], $visible, true)) {
                // Dropped without a marker; see submissionformatter::visible_keys().
                continue;
            }

            $pills .= html_writer::span(
                html_writer::span(s($entry['label']), 'enrol_apply-fieldname') . ' ' . s($entry['value']),
                'enrol_apply-fieldpill'
            );
        }

        if ($pills === '') {
            return '';
        }

        return $this->card_label(s(get_string('queuesubmitted', 'enrol_apply'))) . $pills;
    }

    /**
     * The application comment column.
     *
     * @param stdClass $row Row data.
     * @return string Rendered cell.
     */
    public function col_applycomment($row) {
        /* The same escaped label as the column header, so the card and the table agree about
           what the applicant was asked. */
        $label = $this->scope->instance === null
            ? s(get_string('applycomment', 'enrol_apply'))
            : commentlabel::custom($this->scope->instance);

        return $this->card_label($label) . format_text($row->applycomment, FORMAT_PLAIN);
    }
}
