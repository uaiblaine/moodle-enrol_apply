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
 * Dynamic, so that paging, sorting and (in later slices) filtering refresh the table over
 * core_table_get_dynamic_table_content instead of reloading the page. Core resolves the handler
 * generically as \{component}\table\{handler} (lib/table/classes/external/dynamic/get.php:202), so
 * a plugin can implement the interface even though no plugin type in core does - the only
 * implementors are admin, ai, reportbuilder, sms and user.
 *
 * **The scope is the whole of the risk, and it is why queue::listing_scope() exists.** get.php
 * builds the table, calls set_filterset() with the client's filters, and then applies exactly one
 * capability check against exactly one context. This queue has three scopes across two context
 * levels. So one integer arrives - the enrol instance id - and the course, the context, the mentee
 * id list and the capability are all recomputed from it server-side on every request. The mentee
 * restriction never travels, so nothing a client sends can widen it.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  2016 sudile GbR (http://www.sudile.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class applications extends \table_sql implements dynamic_table {
    /**
     * The table's unique id, and it is a compatibility contract rather than a name.
     *
     * flexible_table keys stored preferences on it - $SESSION->flextable[<uniqueid>] holds the
     * sort, the collapsed columns and the initials - so renaming it silently discards every
     * operator's saved sort. It is the id the old root-level enrol_apply_manage_table used, kept
     * verbatim through the move to this class for exactly that reason.
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
     * Not the SELECT aliases in $extrafields, and the difference is the whole reason this exists:
     * WHERE is evaluated before SELECT on both database families, so a predicate naming an alias
     * is an error on PostgreSQL and silently reads a different column on MySQL. A custom profile
     * field's expression is a joined table's column, which no alias could stand in for.
     */
    protected $identitymappings = [];

    /** @var bool Whether define_table_columns() has run; see setup() for why it is deferred. */
    protected $columnsdefined = false;

    /**
     * Build the table.
     *
     * **No argument, deliberately, and core is what decides that.** get.php calls
     * `new $tableclass($uniqueid)` and PHP silently ignores an argument a constructor does not
     * declare, so the shape core's own dynamic tables use - core_sms\table\sms_gateway_table:45
     * and core_admin\table\plugin_management_table:51 - is to take none and pin the id here. That
     * also removes the last route by which a caller could name a scope: there is now exactly one,
     * the filterset, and exactly one thing in it.
     */
    public function __construct() {
        parent::__construct(self::UNIQUEID);
    }

    /**
     * The table for one scope, built the way the web service builds it.
     *
     * A named constructor so the page path and the refresh path go through the SAME door: a
     * filterset carrying the enrol instance id, and set_filterset() resolving everything else from
     * it. manage.php could assemble that itself, and an earlier shape had it do so - but then the
     * page and its own AJAX refreshes would have had two independent statements of how a scope is
     * established, which is the drift this whole slice exists to remove.
     *
     * The cast is not cosmetic. integer_filter::add_filter_value() tests is_int() and throws a
     * TypeError on anything else (lib/table/classes/local/filter/integer_filter.php:47), and a
     * later slice will read this id out of a data attribute, where everything is a string.
     *
     * The two optional filters ride the same door for the same reason: manage.php reads them from
     * the query string and the module sends them over the service, and a listing narrowed by one
     * route has to be the same listing narrowed by the other.
     *
     * An empty search adds NO filter rather than an empty one. string_filter::add_filter_value()
     * is a complete override gating only on is_string(), so it never reaches the base class's
     * rejection of '' - a filter carrying the empty string is live, and set_filterset() below has
     * to disarm it a second time for the requests this constructor never sees.
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

        /* The field and date filters, added by NAME rather than positionally, so this door and the
           web service's door hand the table the same shape. A name the filterset does not declare
           throws in core, which is why request_filters() only ever produces declared ones. */
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
     * **check_validity() is called here because nothing else calls it.** get.php goes
     * set_filterset() then validate_context() then has_capability() and never asks the filterset
     * whether its required filters are present (:228-231, byte-identical on 5.1 and 5.2), so
     * "required" in applications_filterset is a claim only this line enforces. Without it a
     * request omitting enrolid would reach get_filter() and die with a coding_exception naming
     * an array key, which is the same outcome told worse - and core's own participants table
     * relies on exactly that.
     *
     * Order matters twice. The scope is resolved BEFORE parent::set_filterset(), because that
     * calls guess_base_url() and the url is built from the scope. And the columns are defined
     * here rather than in the constructor, because which identity columns exist is a question
     * about the scope's context and the constructor has no scope yet.
     *
     * @param filterset $filterset Filters as the client sent them.
     * @return void
     */
    public function set_filterset(filterset $filterset): void {
        $filterset->check_validity();

        /* check_validity() proves the filter is THERE and proves nothing about what it holds: it
           tests array_key_exists() against the filterset's own map and stops
           (lib/table/classes/local/filter/filterset.php:231-248). A filter carrying an EMPTY
           value list is a well-formed request to core's service - get.php declares `values` as a
           multiple structure, which validates an empty array happily - and filter::current()
           answers null for one, because rewind() only takes a position `if
           (count($this->filtervalues))` (filter.php:137-145). `(int) null` is 0, which is not a
           refusal here: it is the WIDEST scope this queue has. So the value is read and tested,
           and the same exception raised, because "you named no scope" is one thing to a caller
           however the request managed to say it. */
        $enrolid = $filterset->get_filter('enrolid')->current();
        if ($enrolid === null) {
            throw new \moodle_exception('missingrequiredfields', 'core_table', '', 'enrolid');
        }

        $this->scope = queue::listing_scope((int) $enrolid);

        /* The identity fields, resolved HERE rather than in build_sql(), and the ordering is a
           correctness requirement rather than tidiness. parent::set_filterset() below calls
           guess_base_url(), which reads url_params(), which has to know which field filters are
           applied - and build_sql() does not run until afterwards. Resolve them late and the base
           url carries filters nothing validated, so page two of a filtered queue is a different
           queue. */
        $this->extrafields = identity::fields($this->scope->identitycontext);
        $this->identitysql = identity::sql($this->scope->identitycontext, 'u');
        // Field name => the expression producing it, which is what a WHERE clause can name.
        $this->identitymappings = $this->identitysql->mappings ?? [];
        $this->offeredfilters = queuefilter::resolve($this->identitymappings);

        /* Read BEFORE parent::set_filterset(), which calls guess_base_url() - the base url has to
           carry these or the no-JavaScript path loses them on the first page turn. */
        $this->search = '';
        if ($filterset->has_filter('search')) {
            /* Trimmed and tested for emptiness, because a live filter carrying '' reaches here.
               Treating it as a narrowing filter would empty the queue the moment somebody cleared
               the search box, and would make the count line say "0 of 312". */
            $this->search = trim((string) $filterset->get_filter('search')->current());
        }

        $this->status = null;
        if ($filterset->has_filter('status')) {
            $status = $filterset->get_filter('status')->current();
            if ($status !== null) {
                $this->status = (int) $status;
            }
        }

        /* One entry per field this reader is OFFERED, never per filter the request carried: a
           filter naming a field the administrator enabled but this reader may not see in this
           scope is simply not read. The filterset declares the site's whole list so that a forged
           name is refused by core before it reaches here; this loop is what refuses a name that is
           real but not this reader's. */
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
     * **The columns cannot be defined in set_filterset(), and they cannot be left to setup()
     * either. This is the one place that satisfies both constraints.**
     *
     * Not set_filterset(), because select_all_header() renders a core renderable through $OUTPUT
     * and get.php calls set_filterset() BEFORE validate_context() - so on the refresh path there
     * is no page context yet, $OUTPUT is still the bootstrap placeholder, and the first render
     * throws "$PAGE->context was not set". That worked on every page load, because manage.php
     * sets the context long before it builds the table, and only a real AJAX refresh in a real
     * browser ever showed it.
     *
     * Not setup() alone, because sql_table::out() PROBES for columns before it calls setup():
     * `if (!$this->columns)` runs an unpaginated `SELECT <fields> FROM <from> WHERE <where>` and
     * names the columns after that row's keys (lib/table/classes/sql_table.php:213-222). With the
     * definition deferred to setup() that branch fired on every single render - a second full
     * query, joins and the EXISTS subquery included, thrown away moments later. Defining them
     * here, before core's own out() is entered, is what keeps the branch cold.
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
     * Idempotent by a flag rather than by define_columns() being idempotent - it is: core's
     * define_columns() rebuilds $this->columns from scratch and resets the per-column styles and
     * classes with it (lib/table/classes/flexible_table.php:460-476). So a second call would not
     * duplicate anything; it would redo the identity lookup and the comment-label resolution for
     * no reason. The flag says once and means it.
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
     * Mandatory because get.php:230 calls it, not because the interface declares it - the
     * interface declares has_capability() and nothing else.
     *
     * Never null and never false: the return type is a context, so a refusal has to be carried by
     * has_capability() below. queue::listing_scope() answers an unresolvable id with the system
     * context and allowed false for that reason.
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
     * Mandatory for a dynamic table - flexible_table::guess_base_url() throws for one that does
     * not override it - and called by set_filterset(), which is why the scope is resolved first.
     *
     * Every filter that can be GET-encoded has to be carried here, or the no-JavaScript path
     * silently loses it on the first page turn: the scope, the search term and the status.
     *
     * It does NOT cover everything. get_dynamic_table_html_end() builds the "Show all / Show per
     * page" link from `new moodle_url($PAGE->url)` rather than from this base url
     * (lib/table/classes/flexible_table.php:1815), so manage.php has to put the same parameters
     * into $PAGE->set_url() as well - see url_params(), which is the one definition both read.
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
     * The "of 312" in the count line, and the number the capacity header's first tile reports.
     * They are the same number by construction rather than by two call sites agreeing: a filtered
     * table_sql totalrows in that tile would render "4 awaiting decision" beside a deferred count
     * read straight from \enrol_apply\local\capacity, which is arithmetically impossible and
     * reads as a bug in the capacity figures rather than in the header.
     *
     * One COUNT over two tables, and only when something is actually narrowing - otherwise it
     * equals totalrows, which the table has already paid for.
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
     * @return bool True when a search term or a status is applied.
     */
    public function is_narrowed(): bool {
        return $this->search !== ''
            || $this->status !== null
            || $this->fieldfilters !== []
            || $this->appliedfrom !== null
            || $this->appliedto !== null;
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
     * The vocabulary in one place, because three readers need it and a fourth spelling is how it
     * goes wrong: the renderer builds the select from it, manage.php validates the query string
     * against it, and the predicate in build_sql() compares to it. It is exactly the two states
     * queue::awaiting_decision_where() can leave a row in - ACTIVE is excluded by that predicate
     * and a cancelled application has no row at all - so no other value could ever match.
     *
     * That is not academic. The select's "any status" option carries the EMPTY string, so the GET
     * form submits `status=` whenever no status is chosen; read with
     * optional_param(..., PARAM_INT) that cleans to 0, which is ENROL_USER_ACTIVE, and every
     * search made through the form silently applied a status no row can hold. Validating against
     * this list is what makes an unrecognised value mean "no filter" rather than "filter by
     * something impossible".
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
     * One definition, read by guess_base_url() here and by manage.php for the page url and the
     * decision form's action. They have to agree: a bulk decision taken on a filtered queue posts
     * to whatever the form's action says and is redirected back to whatever the page url says, so
     * a disagreement drops the operator into the unfiltered queue after every decision.
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
     * Which parameters the queue reads off a url, and how each one is read.
     *
     * The ONE definition, called by manage.php and used to build both the table and the page url,
     * so the listing and the address it is reached at cannot disagree about what is applied. It
     * resolves the offered set from the scope for the same reason set_filterset() does: a
     * parameter naming a field this reader may not see is not read at all.
     *
     * **It returns the CLEANED set, which is what makes that claim true.** An earlier version
     * returned whatever survived optional_param(), while url_params() reports what survived
     * queuefilter::clean() and date_filter() - so a select value outside its own vocabulary, or a
     * malformed date, landed in the page url and the decision form's action while the table
     * ignored it. Inert, because neither side narrowed anything by it, but it was precisely the
     * disagreement the paragraph above says cannot happen, and the next filter to be added would
     * not have been inert.
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

        foreach (['appliedfrom', 'appliedto'] as $bound) {
            /* PARAM_ALPHANUMEXT keeps [A-Za-z0-9_-] and drops the rest, which is a safe transport
               charset rather than a date-shape check - `not-a-date` survives it whole. The shape
               is checked by queuefilter::day_bounds(), the same helper date_filter() validates
               through, so the url and the table agree about what a date is. */
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
     * **Derived from what this reader can already SEE, never from a fixed list.** Every entry here
     * is rendered on the row for this reader in this scope: the name by col_fullname(), the
     * identity fields by the same loop that draws the identity line, the comment by
     * col_applycomment(), and the course only where the course column exists. A column that is
     * merely present in the SELECT is not eligible - \core_user\fields::for_userpic() pulls
     * u.email into the projection of every scope including the mentee scope, where
     * identity::fields(null) returns nothing, so matching it would answer "does this applicant's
     * address contain <guess>?" to a reader who may not see the address. A hit count is an answer.
     *
     * The identity fields come from the mappings and not from $extrafields, which hold the SELECT
     * aliases: WHERE is evaluated before SELECT on both families, and a custom profile field's
     * value lives in a joined table that no alias can stand in for.
     *
     * The snapshot column is deliberately absent. It is masked PER ROW by visible_keys(), and a
     * searchable surface cannot honour a per-row mask - a reader denied a field on some rows would
     * recover it by searching for it.
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
     * One placeholder NAME per column, every one bound to the same value: fix_sql_params() counts
     * placeholder OCCURRENCES and throws duplicateparaminsql when the total differs from the
     * parameter array, so a single name reused across four columns is a fatal rather than a
     * convenience.
     *
     * has_unaccent() is resolved once here rather than inside like_ai(), so four searched columns
     * cost one catalogue lookup instead of four - on every keystroke, once the module is wired.
     *
     * sql_like_escape() is not optional. Core's own participants search omits it, which makes a
     * percent sign an applicant typed into a live wildcard; that is a defect to leave behind.
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

        /* The identity fields this reader may see, and the SQL for them. Everything about which
           fields those are is core's decision - see \enrol_apply\local\identity - so that the
           queue and the participants page beside it cannot answer differently. */

        $userfieldsapi = \core_user\fields::for_userpic()->including('username');
        $userfields = $userfieldsapi->get_sql('u', false, '', 'userid', false)->selects;

        /* The comment is read from the durable record first and from the application info
           row only as a fallback. The two hold the same text, but the application info row is
           deleted the moment a decision is taken, and a decided enrolment can come back to
           this queue: suspending an approved participant from core's participants page
           leaves status != active with timeend = 0, which is exactly the predicate above.
           Before this join those rows showed an empty comment; the fallback is what keeps
           applications that predate the durable record readable.

           Joined on the user enrolment and not on courseid + userid, which is the natural
           key of that table but is deliberately not unique: an applicant who was cancelled
           and applied again has two records for the same course, and joining on the pair
           would show each of their rows twice. */
        /* Whether this applicant has a record of applying to this course BEFORE. It is a badge
           on the row rather than a number, because what it changes is whether the operator opens
           the review page - "they were cancelled here in June" is the kind of fact that turns a
           30-second decision into a 3-minute one, and the queue is where that choice is made.

           A correlated EXISTS rather than a join, so the row count cannot change: a join to a
           table whose natural key (courseid, userid) is deliberately NOT unique - cancelling and
           re-applying is the ordinary route - would multiply the row per earlier application.
           The courseuser index is the one this reads. CASE WHEN EXISTS is portable; both
           database families CI runs plan it as a semi-join.

           `s.id IS NULL OR prior.id <> s.id` excludes the row's own record: a submission that IS
           the current application is not evidence of an earlier one. The null branch is for the
           applications that predate the durable record, which have no s.id to exclude. */
        $priorsql = "CASE WHEN EXISTS (
                            SELECT 1
                              FROM {enrol_apply_submission} prior
                             WHERE prior.courseid = c.id
                               AND prior.userid = ue.userid
                               AND (s.id IS NULL OR prior.id <> s.id)
                          ) THEN 1 ELSE 0 END AS appliedbefore";

        /* s.userinfodata is the frozen record of what the applicant typed, and it costs nothing
           to select: the row it comes from is already joined for the comment. There is no
           fallback to enrol_apply_applicationinfo the way applycomment has one, because that row
           has never held a snapshot - an application predating the durable record shows no
           evidence rather than an empty envelope that would read as "they filled nothing in". */
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

        /* The operator's own filters, last, so that everything above is the SCOPE and everything
           here is the narrowing - which is the split scope_total() counts across. */
        if ($this->search !== '') {
            [$searchwhere, $searchparams] = $this->search_where();
            $wheres[] = $searchwhere;
            $params += $searchparams;
        }

        /* The ENROLMENT's status, not the durable record's, and the two measurably disagree: the
           record is only moved to APPROVED on a transition to ENROL_USER_ACTIVE, so an approved
           participant later suspended from the participants page re-enters this queue carrying
           status APPROVED on the record and SUSPENDED on the enrolment. The queue lists what is
           awaiting a decision NOW, which is the enrolment's question. The option LABELS are the
           record's vocabulary because that is the wording the operator already reads on the review
           page; the predicate is the enrolment's. */
        if ($this->status !== null) {
            $wheres[] = 'ue.status = :statusfilter';
            $params['statusfilter'] = $this->status;
        }

        /* One predicate per applied field filter, over the EXPRESSION core's identity mapping
           produced - never over a column name this plugin composed, and never over the SELECT
           alias, since WHERE is evaluated first and a custom field's value lives in a joined table.

           The placeholder names are prefixed because this statement already binds `now` and
           `active` from awaiting_decision_where(), `enrolid` or `enrol`, `mentee<N>` from the
           mentee clause, `searchterm<N>` from the search and `statusfilter` above.
           fix_sql_params() counts placeholder occurrences and throws duplicateparaminsql when the
           total differs from the parameter array, so a collision is a fatal rather than a subtly
           wrong answer. */
        $unaccent = null;
        $index = 0;
        foreach ($this->fieldfilters as $token => $value) {
            $offered = $this->offeredfilters[$token];
            $name = 'queuefilter' . (++$index);

            if ($offered->control === 'select') {
                /* A closed vocabulary is compared for equality rather than matched loosely - but
                   through core's helper, because a bare `=` is not the same operator on the two
                   database families this plugin is tested on. moodle_database::sql_equal() emits
                   `=` unchanged, which on PostgreSQL is case sensitive, while
                   mysqli_native_moodle_database::sql_equal() has to force COLLATE <family>_bin to
                   reach that same behaviour - so a bare `=` on MariaDB takes the column's own,
                   normally case-insensitive collation instead. Nothing in the expression
                   neutralises it: sql_compare_text() delegates to sql_order_by_text(), which
                   returns the field name unchanged on both drivers.
                   Case matters here because stored data drifts out of case with the vocabulary
                   naming it - an administrator may re-case a menu option at any time and
                   {user_info_data} keeps whatever spelling it was written with. */
                $wheres[] = $DB->sql_equal($offered->expression, ':' . $name, false, false);
                $params[$name] = $value;
                continue;
            }

            $unaccent = $unaccent ?? search::has_unaccent();
            $wheres[] = search::like_ai($offered->expression, ':' . $name, $unaccent);
            $params[$name] = '%' . $DB->sql_like_escape($value) . '%';
        }

        /* The applied-date range, as whole days in the reader's own timezone. The upper bound is
           the midnight that STARTS the following day compared with a strict less-than, so the "to"
           date is inclusive without any second-level arithmetic and without assuming a day is
           86400 seconds - which it is not, twice a year. */
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
     * Every column this table offers can tie. `applydate` is `ue.timecreated`, which
     * `enrol_plugin::enrol_user()` writes as whole Unix seconds, so a cohort admitted by one
     * script or one busy minute shares a value - measured on the live 5.2 site, three pending
     * applications already do. `course`, `fullname` and `email` tie more easily still. With no
     * unique key the database is free to return a tied group in any order it likes, and it does
     * not have to make the same choice twice: each page of a paged table is a separately planned
     * statement with its own LIMIT and OFFSET, so a row can appear on two pages while another
     * appears on none. Measured on PostgreSQL 17 over a tied 100-row set: 11 rows duplicated,
     * 11 never shown, and adding a unique final key gave exactly 100 distinct rows.
     *
     * Core's own fallback cannot cover this. `set_sorting_preferences()` appends
     * `sort_default_column` when it is missing, and here that column IS `applydate` - so
     * clicking any other header gives "<that column> ASC, applydate ASC", which is two keys that
     * both tie, and clicking `applydate` itself appends nothing at all.
     *
     * This method, and not `get_sql_sort()` or `construct_order_by()`, is the injection point.
     * `construct_order_by()` is static and reached through `self::`, which is early bound, so an
     * override of it is never called. Appending to `get_sql_sort()`'s string instead would put a
     * raw fragment after core's per-driver NULL ordering, which is not portable. The shape below
     * is core's own, in `tool_policy`, `mod_quiz` and `mod_assign`.
     *
     * The raw `ue.id` and not the `userenrolmentid` alias: both work on PostgreSQL and MariaDB,
     * but the raw column is what core uses and it does not depend on the SELECT list.
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
     * The comment heading comes from the scope rather than from a caller. It used to be a
     * constructor argument, and a dynamic table has no constructor arguments to pass it in -
     * which is no loss, because the instance it is read from is exactly what the scope resolves.
     * The site-wide and mentee scopes span instances, each of which may word the question
     * differently, so a single heading there would be true of some rows and false of others;
     * those scopes carry no instance and get the shipped wording.
     *
     * @return void
     */
    protected function define_table_columns(): void {
        $columns = ['checkboxcolumn'];
        $headers = [$this->select_all_header()];

        /* The course, and only where a row's course is not already known from the url. Every row
           of an instance-scoped queue belongs to the same course, so the column would repeat one
           value down the page and cost the applicant's own cell the width to do it. The site-wide
           and mentee scopes span courses and cannot do without it. */
        if ($this->scope->instance === null) {
            $columns[] = 'course';
            $headers[] = get_string('course');
        }

        $columns[] = 'fullname';
        // The heading of a column named 'fullname' is filled in by table_sql itself.
        $headers[] = 'fullname';

        /* The identity fields are NOT columns any more; they ride as a second line inside the
           applicant's cell, and that is what makes a variable, capability-gated field list fit a
           table at all - a site naming five of them would otherwise push the evidence off the
           right-hand edge. What it costs is their sortability, which is a real loss and a small
           one: sorting a triage queue by institution is not a thing operators do, and the filters
           of a later slice are the affordance that replaces it. */

        $columns[] = 'applydate';
        $headers[] = get_string('applydate', 'enrol_apply');

        /* The evidence, and the reason this queue exists at all: the answers the applicant gave
           are what the decision is made on, and the page this replaces showed them nowhere.

           Absent on the mentee scope, by the decision recorded in the plan: identity fields
           appear on the ?id= and site-wide scopes only, never there. These pills are identity
           data of exactly that kind - a site offers city, institution and custom profile fields
           through this form - so the decision governs them too, and `identitycontext === null` is
           the predicate that already means "the mentee scope" everywhere else in this class.
           It is not that the column COULD not be masked there; visible_keys() judges each row in
           its own course and would answer for a mentor as well as for anybody. It is that the
           owner decided this data does not belong on that surface. */
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

        /* The door to one application, and the queue had none. Its only routes in were the
           participants-page icon, the notification e-mail and the previous/next chain, so an
           operator reading the queue could not open the row they were reading. The header is
           empty on purpose: a column of buttons needs no name, and every button carries one. */
        $columns[] = 'review';
        $headers[] = '';

        $this->define_columns($columns);
        $this->define_headers($headers);

        /* Names the cell that identifies each row, so table_sql emits it as a
           <th scope="row"> and a screen reader announces every other cell of the row
           against the applicant's name rather than reading a wall of bare values. */
        $this->define_header_column('fullname');
        $this->no_sorting('checkboxcolumn');
        /* Unsortable because there is nothing to sort on: the column renders a list of pairs out
           of a JSON envelope, and no database this plugin supports can order by one of them. */
        $this->no_sorting('snapshot');
        $this->no_sorting('applycomment');
        $this->no_sorting('review');
        $this->sortable(true, 'applydate', SORT_ASC);
    }

    /**
     * What an empty result says, which depends on why it is empty.
     *
     * Core's "Nothing to display" is right for a queue with no applications and wrong for a filter
     * that matched none: the operator has just typed something, and a message that does not
     * mention the filter reads as "this queue is empty" - which sends them looking for a fault in
     * the enrolment method rather than at the box they just typed in.
     *
     * The unfiltered branch delegates verbatim rather than reproducing core's markup, so the
     * dynamic header, the reset button and the initials bar keep whatever core does with them. A
     * Behat scenario in this plugin asserts core's own string on the unfiltered empty queue
     * (tests/behat/enrol_apply.feature), and that is the control on this override: change it to
     * fire unconditionally and that scenario goes red.
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
     * **Real text in the markup, and NOT `content: attr(data-label)`.** The first cut of the card
     * view put the wording in a data-* attribute and drew it from the stylesheet, which reads as
     * the tidier answer and is the weaker one: CSS-generated content is announced inconsistently
     * across screen readers, and - worse - turning the rows and cells into blocks costs the table
     * its own semantics in the accessibility tree, so the association between a value and its
     * column heading in the thead goes with it. Text inside the cell needs neither: it is
     * announced everywhere, and it is beside the value it names whatever the display is.
     *
     * `role="cell"` and friends were the other candidate and were rejected: flexible_table
     * offers no hook for ROW attributes, so the cells could have been given a role and the rows
     * could not, and an orphan role="cell" with no role="row" ancestor is worse than none.
     *
     * Hidden above the breakpoint by styles.css, where the thead already says all this.
     *
     * The caller passes the ESCAPED spelling: html_writer::span() concatenates its content
     * without escaping it, exactly as html_writer::tag() does for the headers.
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
     * Core's own renderable, so the queue is driven by core/checkbox-toggleall rather than by
     * markup of this plugin's invention. The label text is pinned to the same string in both
     * directions on purpose: the module rewrites the label's innerHTML on every toggle, and a
     * header cell whose label alternates between "Select all" and "Deselect all" changes width
     * under the reader. Core does the same, with the same comment, in the gradebook and in the
     * participation report.
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

        /* The name, the value and the label are unchanged from the hand-written markup this
           replaces, which is what keeps the POST contract (userenrolments[]) and the Behat
           locator ("Select Student 1") intact. Adopting core's renderable changes the data
           attributes, not the contract. */
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
     * The other half of the override below, and the two are genuinely separate:
     * get_sql_where() stops the filter narrowing the query, this stops the control being drawn.
     * Killing only the filter would leave an A-Z bar on the page that silently does nothing when
     * clicked, which is worse than either end state.
     *
     * It is an override rather than a call because the argument is not the caller's to make here:
     * renderer::capture_table() passes true to out(), and on core's dynamic-table path
     * external\dynamic\get calls out($pagesize, true) unconditionally. Forcing it false at the
     * source is what survives both.
     *
     * With use_initials false, get_initial_first() and get_initial_last() return null, so
     * print_initials_bar()'s condition is false on all three of its terms and nothing is drawn.
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
     * Rendering with `out(50, false)` hides the A-Z bar and does nothing else: get_sql_where()
     * reads `prefs['i_first']` and `prefs['i_last']` and never consults `use_initials`, and
     * table_sql::query_db() appends the result to both the count and the data query. Measured
     * against the real queue: with a stored `i_first = 'Z'` and no bar anywhere on the page, a
     * three-row queue returned nothing, with no control on screen able to explain it. The
     * preference lives in $SESSION->flextable, so it survives page loads and is invisible.
     *
     * On the dynamic path there is a third way in that the page path never had: get.php reads
     * `firstinitial` and `lastinitial` straight off the request and calls set_first_initial() /
     * set_last_initial() with them (:238-244). Those write the same preference, so a crafted
     * request could re-arm the filter this override exists to kill - and this override is what
     * keeps that harmless, because the preference is never read.
     *
     * Overriding this is the complete kill. Emptying $userfullnamecolumns also stops the filter
     * and silently costs the fullname column its firstname/lastname sub-sort links, which is why
     * it is not what this does.
     *
     * @return array Empty where clause and no parameters.
     */
    public function get_sql_where() {
        return ['', []];
    }

    /**
     * The applicant: their picture, their name, what is unusual about them, and who they are.
     *
     * Four things in one cell, and the reason they are in one cell rather than four columns is
     * that only one of them has a fixed width. The identity list is whatever the site named in
     * showuseridentity and this reader may see, so as columns it is a table whose shape changes
     * per site and per reader; as a second line it is a paragraph that wraps.
     *
     * **This is now the escaping boundary, and it moved here from other_cols().** flexible_table
     * writes a cell's value into the markup with no escaping of its own, and an identity field is
     * user-controlled text. Everything below that is not already a link or a lang string goes
     * through s() or format_string(). Gate AM is the one that reddens if an identity value stops
     * being escaped.
     *
     * The name is a plain profile link and deliberately not a details modal: the decision needs
     * the profile, and a modal that summarises it is a second thing to keep in step with the
     * first.
     *
     * @param stdClass $row Row data carrying the aliased user picture fields.
     * @return string Rendered cell.
     */
    public function col_fullname($row) {
        global $CFG, $OUTPUT;

        /* ENROL_APPLY_USER_WAIT lives in the plugin's lib.php, which is not autoloaded while this
           class is, and an undefined constant is a fatal on PHP 8. It is the FOURTH place in this
           plugin needing the same line, and the first where the page path hid the omission: this
           method is reached from manage.php, which requires lib.php itself, AND from core's
           dynamic-table service, which requires nothing of the sort. So it worked for every page
           load and died on the first AJAX refresh - measured, and only because a Behat scenario
           provoked a sort. Anything else this class reaches for from lib.php has the same shape. */
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        $user = user_picture::unalias($row, ['username'], 'userid');

        $name = html_writer::link(
            new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $row->courseid]),
            fullname($user),
            ['class' => 'enrol_apply-applicantname']
        );

        $badges = '';
        if ($row->enrolstatus == ENROL_APPLY_USER_WAIT) {
            /* text-dark beside bg-warning is not decoration. Bootstrap 5's .badge defaults its
               colour to WHITE, so a light fill renders white on near-white - measured at 1.95:1
               against the 4.5:1 floor. Every bg-* on a badge in this plugin carries an explicit
               text utility for that reason. */
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

        /* The label names the applicant and is hidden, because the visible word is "Review" on
           every row and a screen reader reading a column of them learns nothing. aria-label and
           not a title: a title is not announced reliably and is invisible to a keyboard user. */
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
        /* How long ago, over the date itself. An operator triaging a queue is asking "how long
           has this person been waiting", and a column of timestamps makes them do the subtraction
           on every row. The exact date stays underneath, because the answer to "when exactly" has
           to be on the page somewhere and this is where a reader looks for it. */
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
     * **Per row, and an earlier version of this class got it wrong in a way worth recording.**
     * That version resolved the mask once from the SCOPE's context, and justified it in a
     * docblock claiming that a capability held at system level is held in every course below it,
     * so a system-context mask could never be more permissive than a per-row one. That claim is
     * false, and core says so plainly: has_capability_in_accessdata() builds its list of contexts
     * by walking UPWARD from the one it is given (lib/accesslib.php:792-800, byte-identical on
     * 5.1 and 5.2) and returns false only for a CAP_PROHIBIT found in that list. A prohibit
     * recorded at a course context is therefore invisible to a check made at the system context.
     * So an operator holding moodle/site:viewuseridentity site-wide, with it prohibited in one
     * course - which is what the Permissions page exists to do - passed the system check and had
     * every pill of that course's applicants rendered to them.
     *
     * Judging each row in its own course context removes that, and it is also what makes this
     * column agree with renderer::snapshot_context(), which has always masked on
     * context_course::instance($application->courseid). The two surfaces onto this record now ask
     * literally the same question.
     *
     * The identity line in the applicant's cell beside these pills still asks the scope's
     * context, and that is NOT an oversight left behind: identity::fields() decides the SELECT
     * list, so one statement spanning courses has one field list and cannot be masked per row at
     * all. The snapshot is a JSON envelope rendered per row and carries no such constraint, which
     * is why the correct answer is available here and not there.
     *
     * Memoised because a site-wide queue spans courses: one context load and one capability check
     * per distinct course, not per row.
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
     * Read from the frozen snapshot the applicant's own submission wrote, and from nothing else.
     * The rule is the review page's and the reasoning is recorded in full on
     * renderer::snapshot_context(): re-resolving the field set from the LIVE enrol instance would
     * drop a field the teacher has since stopped asking for, and dereferencing a stored key
     * against the applicant's live profile once rendered a password hash from a crafted archive.
     * The stored labels are used for the same reason - they are the wording the applicant saw
     * when they typed.
     *
     * That also settles what the mockup calls a "not given" pill, which this column deliberately
     * does not draw. fields::submitted_values() never records an empty answer, so a field left
     * blank and a field that was never offered are the same absence in the envelope, and telling
     * them apart would need exactly the live re-resolution the paragraph above forbids - per row,
     * and impossible at all on a scope spanning instances that offer different fields. Showing
     * only what was actually answered is the honest half of the mockup.
     *
     * **This is an escaping boundary.** flexible_table::format_row() writes a cell's value into
     * the markup with no escaping of its own, and both halves of every pair are user-controlled:
     * the value is what the applicant typed, and a custom field's label is what an administrator
     * named it. s() on both, and not format_string(), whose strip_tags() would delete a restored
     * value from the first "<" onwards. Gate CP is the one that reddens if either stops.
     *
     * Nothing at all when there is nothing to show, the card label included: a card headed
     * "Submitted with the application" over empty space claims a section the record does not have.
     *
     * @param stdClass $row Row data carrying the stored envelope as `snapshot`.
     * @return string Rendered cell.
     */
    public function col_snapshot($row) {
        $visible = $this->visible_keys((int) $row->courseid);

        $pills = '';
        foreach (submissionrecord::read_snapshot($row->snapshot ?? null) as $entry) {
            if ($visible !== submissionformatter::ALL_FIELDS && !in_array($entry['key'], $visible, true)) {
                /* Withheld from every row rather than only from the rows holding a value: a
                   marker appearing exactly where there is data is a presence oracle, which is
                   the rule the report's own formatter states and both other surfaces inherit. */
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
        /* The instance's own wording, in the ESCAPED spelling, which is the same value and the
           same spelling the column header carries - so the card and the desktop cannot disagree
           about what the applicant was asked. commentlabel::custom() defaults to that spelling. */
        $label = $this->scope->instance === null
            ? s(get_string('applycomment', 'enrol_apply'))
            : commentlabel::custom($this->scope->instance);

        return $this->card_label($label) . format_text($row->applycomment, FORMAT_PLAIN);
    }
}
