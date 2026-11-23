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
use enrol_apply\local\decidingrole;
use enrol_apply\local\identity;
use enrol_apply\local\queue;
use enrol_apply\local\coursefilter;
use enrol_apply\local\groupfilter;
use enrol_apply\local\groupvisibility;
use enrol_apply\local\instanceoptions;
use enrol_apply\local\profilelink;
use enrol_apply\local\queuefilter;
use enrol_apply\local\search;
use enrol_apply\local\groupscope;
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

    /** @var string The named filter's value for a group given by a key. */
    public const NAMED_KEY = 'key';

    /** @var string The named filter's value for a group chosen from the method's list. */
    public const NAMED_CHOSEN = 'chosen';

    /** @var string The named filter's value for an application that named no group. */
    public const NAMED_NONE = 'none';

    /** @var string[] How the group was named, the named filter's vocabulary, in the order a panel lists it. */
    public const NAMED = [self::NAMED_CHOSEN, self::NAMED_KEY, self::NAMED_NONE];

    /** @var int[] The "waited more than" filter's thresholds, in days (the course report's, MQ mockup). */
    public const WAITED_DAYS = [1, 3, 7, 14];

    /**
     * @var string[] The tone of each kind of tag {@see tags()} gives a row, for the status labels of
     *     the applicant's cell.
     */
    public const TAG_TONES = [
        'deferred' => 'warning',
        'key' => 'info',
        'appliedbefore' => 'neutral',
        'own' => 'neutral',
    ];

    /** @var stdClass|null Everything this listing is scoped by, from queue::listing_scope(). */
    protected $scope = null;

    /** @var array Identity field names this reader may see, from \enrol_apply\local\identity. */
    protected $extrafields = [];

    /** @var array Snapshot keys this reader may see, memoised by course id; see visible_keys(). */
    protected $visiblekeys = [];

    /** @var array Submission id => the page's frozen answers, read in one query by query_db(). */
    protected $snapshots = [];

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

    /** @var array The group filter's values this reader may send; see groupfilter::offered(). */
    protected $groupoffered = [];

    /** @var string|null The group filter applied: a group id, none or mine; null for none. */
    protected $groupfilter = null;

    /** @var string|null What the group filter holds when a page names none; see groupfilter. */
    protected $groupdefault = null;

    /** @var bool Whether the listing carries the key group's column; see set_filterset(). */
    protected $groupcolumn = false;

    /** @var array Group id => true, for the groups on this page the reader may see; see query_db(). */
    protected $visiblegroups = [];

    /** @var array "courseid:userid" => true, for the applicants whose profile opens; see query_db(). */
    protected $profilelinks = [];

    /**
     * @var array Enrol instance id => object with name (plain) and deciding, for the methods of this
     *     page, on the queues that span methods only; see query_db().
     */
    protected $methods = [];

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

    /** @var string|null The named filter applied, one of NAMED; null for none. */
    protected $named = null;

    /** @var int|null The waited filter applied, one of WAITED_DAYS; null for none. */
    protected $waited = null;

    /** @var bool Whether the listing is narrowed to the applicants who applied to the course before. */
    protected $appliedbefore = false;

    /** @var bool Whether this listing counts each applicant's earlier applications; see set_filterset(). */
    protected $history = false;

    /** @var array|null The groups the course report narrows this reader to, for those earlier applications. */
    protected $reportgroups = null;

    /** @var int The clock's time when the filters were read, which the waited filter counts back from. */
    protected $now = 0;

    /** @var array The scope's [where fragments, parameters], as build_sql() read them from scope_where(). */
    protected $scopeparts = [[], []];

    /**
     * @var array Filter name => [where fragment, parameters], one entry per filter the operator applied.
     *
     * Kept apart from the scope so that a count can be taken under every filter but one
     * ({@see where_without()}): what choosing another value of that filter would list.
     */
    protected $narrowing = [];

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
        global $DB, $USER;

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

        /* The group filter, on the instance queue only, cleaned against what THIS reader is offered
           (groupfilter::offered(), derived from the scope and $USER): a narrowed reader posting
           another group's id, `none` or `mine` gets no filter, and their scope still holds. The
           default (V-D2) is not applied here but where the page reads its request, so an AJAX
           refresh sends whatever the control holds, `all` included, and is never re-defaulted. */
        $this->groupoffered = groupfilter::offered($this->scope);
        $this->groupdefault = groupfilter::default_value($this->scope, $this->groupoffered);
        $this->groupfilter = null;
        if ($this->groupoffered && $filterset->has_filter(groupfilter::NAME)) {
            $this->groupfilter = groupfilter::clean(
                $this->groupoffered,
                (string) $filterset->get_filter(groupfilter::NAME)->current()
            );
        }

        /* The key group's column (V-D1): on the instance and site-wide queues, never on the
           mentee queue, whose mentor holds nothing in the course; and on an instance queue only
           when its course has a group, where every row would otherwise read "Without a group". */
        $this->groupcolumn = $this->scope->mentees === null
            && ($this->scope->instance === null
                || $DB->record_exists('groups', ['courseid' => (int) $this->scope->instance->courseid]));

        $this->now = \core\di::get(\core\clock::class)->time();

        /* Whether this applicant applied to this course before: the "Applied before" label and the
           count of earlier applications. Only where the review page would show the history, on the
           same rule (renderer::history_context()): on one instance's queue, to a reader holding
           enrol/apply:viewreports in that course, narrowed by the report's own groups. It discloses
           that earlier applications exist, which is what that capability controls, so the mentee
           and site-wide queues, which span courses no single context can judge, and a reader
           without it count nothing. Decided here, once, because the select list, the filter and a
           facet count all read it. */
        $this->history = false;
        $this->reportgroups = null;
        if ($this->scope->enrolid && $this->scope->instance !== null) {
            $priorcourse = (int) $this->scope->instance->courseid;
            if (has_capability(groupscope::REPORT, context_course::instance($priorcourse))) {
                $this->history = true;
                $this->reportgroups = groupscope::narrowed_to($priorcourse, (int) $USER->id, groupscope::REPORT);
            }
        }

        /* How the group was named, on an instance queue with groups only, where a group can have been
           named at all; a value outside the vocabulary is no filter, as everywhere above. */
        $this->named = null;
        if ($this->groupcolumn && $this->scope->instance !== null && $filterset->has_filter('named')) {
            $named = trim((string) $filterset->get_filter('named')->current());
            $this->named = in_array($named, self::NAMED, true) ? $named : null;
        }

        $this->waited = null;
        if ($filterset->has_filter('waited')) {
            $days = (int) $filterset->get_filter('waited')->current();
            $this->waited = in_array($days, self::WAITED_DAYS, true) ? $days : null;
        }

        // Only where the earlier applications are counted: elsewhere the filter would disclose them.
        $this->appliedbefore = $this->history
            && $filterset->has_filter('appliedbefore')
            && trim((string) $filterset->get_filter('appliedbefore')->current()) === '1';

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

        /* Separate groups: a decider who may not access all groups lists only the applications of
           their own groups, and none without a key (S1, S3). The ids come from the scope, which
           queue::listing_scope() derived from the course and $USER on this request, the AJAX
           refresh included; nothing the client sends reaches them. An operator in no group gets
           the never-true predicate, and the page its notice (not_in_any_group()). */
        if ($this->scope->groups !== null) {
            [$groupwhere, $groupparams] = groupscope::where($this->scope->groups);
            $wheres[] = $groupwhere;
            $params += $groupparams;
        }

        return [$wheres, $params];
    }

    /**
     * Whether this operator is narrowed by separate groups and belongs to no group they may see.
     *
     * Core's participants page shows such a user its "not in any group" notice instead of the
     * list (user/index.php), and so does the queue (S4).
     *
     * @return bool True when the listing is narrowed to no group at all.
     */
    public function not_in_any_group(): bool {
        return $this->scope->groups === [];
    }

    /**
     * Whether this operator may decide from this listing, rather than only look at it.
     *
     * False for a holder of enrol/apply:viewapplications without the deciding capability (S5):
     * their queue has no selection column, and the page no bulk bar and no decision controls.
     * Read from the scope, which queue::listing_scope() derived on this request, so the page and
     * an AJAX refresh draw the same columns.
     *
     * @return bool True when the listing offers decisions.
     */
    public function can_decide(): bool {
        return (bool) ($this->scope->candecide ?? false);
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
     * How many applications of this operator's queue are deferred to the waiting list.
     *
     * The capacity header's second tile, counted over the same scope as scope_total() beside it
     * (V-D3): a decider narrowed to their groups is told how many of THEIR applications wait,
     * never the method's figure, which could exceed the "awaiting decision" tile it sits next to.
     * For a reader who is not narrowed this is capacity::deferred(), whose predicate it repeats
     * through scope_where(): the waiting status inside "awaiting a decision".
     *
     * @return int Deferred applications in scope, unfiltered.
     */
    public function scope_deferred(): int {
        global $CFG, $DB;

        // ENROL_APPLY_USER_WAIT lives in the plugin's lib.php, which is not autoloaded.
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        [$wheres, $params] = $this->scope_where();
        $wheres[] = 'ue.status = :deferredstatus';
        $params['deferredstatus'] = ENROL_APPLY_USER_WAIT;

        return (int) $DB->count_records_sql(
            'SELECT COUNT(1)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ' . implode(' AND ', $wheres),
            $params
        );
    }

    /**
     * The groups this operator's instance queue is narrowed to by separate groups.
     *
     * @return array|null Group ids, empty for a narrowed reader in no group; null when not narrowed.
     */
    public function scope_groups(): ?array {
        return $this->scope->groups;
    }

    /**
     * Whether the operator has narrowed this listing at all.
     *
     * @return bool True when a search term, status, field, date, group, course, category, named,
     *     waited or applied-before filter is applied.
     */
    public function is_narrowed(): bool {
        return $this->search !== ''
            || $this->status !== null
            || $this->fieldfilters !== []
            || $this->appliedfrom !== null
            || $this->appliedto !== null
            || $this->categoryfilter !== null
            || $this->coursefilter !== null
            || $this->groupfilter !== null
            || $this->named !== null
            || $this->waited !== null
            || $this->appliedbefore;
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
        /* An applied group, or `all` where the page would otherwise apply its default: without it
           a page turn or a removed chip would bring the reader's own groups back. */
        if ($this->groupfilter !== null) {
            $params[groupfilter::NAME] = $this->groupfilter;
        } else if ($this->groupdefault !== null) {
            $params[groupfilter::NAME] = groupfilter::ANY;
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
        if ($this->named !== null) {
            $params['named'] = $this->named;
        }
        if ($this->waited !== null) {
            $params['waited'] = $this->waited;
        }
        if ($this->appliedbefore) {
            $params['appliedbefore'] = 1;
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
     * The group filter applied.
     *
     * @return string|null A group id, none or mine; null when no group filter is applied.
     */
    public function get_group_filter(): ?string {
        return $this->groupfilter;
    }

    /**
     * What the group filter holds when a page names none (V-D2).
     *
     * @return string|null groupfilter::MINE in a visible-groups course for a member; null elsewhere.
     */
    public function get_group_default(): ?string {
        return $this->groupdefault;
    }

    /**
     * The group filter's options, labelled, in the order the control lists them.
     *
     * The labels are the PLAIN spelling, for a double stash and for a chip; the group names go
     * through format_string() in the course's context with escape off. Resolved here rather than
     * in set_filterset(), which the web service reaches before any page context exists.
     *
     * @return array Value => label: mine and none first where offered, then the groups in natural
     *     order; empty when the filter is not offered.
     */
    public function get_group_choices(): array {
        if (!$this->groupoffered) {
            return [];
        }

        $choices = [];
        if (in_array(groupfilter::MINE, $this->groupoffered, true)) {
            $choices[groupfilter::MINE] = get_string('queuegroupmine', 'enrol_apply');
        }
        if (in_array(groupfilter::NONE, $this->groupoffered, true)) {
            $choices[groupfilter::NONE] = get_string('queuegroupnone', 'enrol_apply');
        }

        $context = context_course::instance((int) $this->scope->instance->courseid);
        foreach (groupfilter::groups($this->scope) as $id => $group) {
            $choices[(string) $id] = format_string($group->name, true, ['context' => $context, 'escape' => false]);
        }

        return $choices;
    }

    /**
     * The names of the groups a narrowed reader's queue is limited to, for the line saying so (V-D14).
     *
     * Their own groups, which they may see by construction (groupscope::member_groups()), in
     * natural order and in the PLAIN spelling: the line is a lang-string parameter in a double
     * stash.
     *
     * @return array The names; empty for a reader who is not narrowed, or narrowed to no group.
     */
    public function scope_group_names(): array {
        if (!$this->scope->groups || $this->scope->instance === null) {
            return [];
        }

        $context = context_course::instance((int) $this->scope->instance->courseid);
        $names = [];
        foreach (groupfilter::groups($this->scope) as $group) {
            $names[] = format_string($group->name, true, ['context' => $context, 'escape' => false]);
        }

        return $names;
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

        /* The group filter, with its visible-groups default (V-D2) applied here, where a page
           without a group parameter is told apart from one carrying `all`; the table never
           defaults, so an AJAX refresh shows what the control holds. A value this reader was not
           offered is no filter, as in set_filterset(), and where a default exists the page then
           says `all` in its url so that no later link brings the default back. */
        $groupoffered = groupfilter::offered($listing);
        if ($groupoffered) {
            $groupdefault = groupfilter::default_value($listing, $groupoffered);
            $raw = optional_param(groupfilter::NAME, null, PARAM_ALPHANUM);
            $group = $raw === null ? $groupdefault : groupfilter::clean($groupoffered, (string) $raw);
            if ($group !== null) {
                $filters[groupfilter::NAME] = $group;
            } else if ($groupdefault !== null) {
                $filters[groupfilter::NAME] = groupfilter::ANY;
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
     * The WHERE is the scope ({@see scope_where()}) and then one entry of $narrowing per filter the
     * operator applied, kept apart so that {@see where_without()} can rebuild it without any of them:
     * the headless reader ({@see \enrol_apply\local\queueengine}) counts its facets that way, over this
     * same FROM, so the page and its counts cannot list different rows.
     *
     * @return void
     */
    protected function build_sql(): void {
        $this->scopeparts = $this->scope_where();

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
        /* How many times this applicant applied to this course before (see set_filterset() for
           where it is counted): the "Applied before" label and the earlier applications' column. A
           correlated subquery rather than a join, so the row count cannot change: (courseid,
           userid) is not unique, and a join would multiply the row per earlier application. It
           reads the courseuser index. */
        $priorsql = '0 AS appliedbefore';
        $selectparams = [];
        if ($this->history) {
            [$prior, $selectparams] = $this->prior_sql('priorscope');
            $priorsql = "(SELECT COUNT(1) {$prior}) AS appliedbefore";
        }

        /* s.id names the record whose frozen answers query_db() reads for the whole page. They
           have no fallback: enrol_apply_applicationinfo never held a snapshot, so an application
           predating the durable record shows no evidence. */
        $fields = "ue.id AS userenrolmentid, ue.status AS enrolstatus, ue.timecreated AS applydate,
                   COALESCE(s.comment, ai.comment) AS applycomment, s.id AS submissionid,
                   s.keyoutcome, u.deleted AS userdeleted,
                   c.fullname AS course,
                   c.id AS courseid, e.id AS enrolid, e.name AS enrolname, e.roleid AS enrolroleid,
                   e.customchar1 AS enrolkeymode, {$priorsql}, {$userfields}{$this->identitysql->selects}";

        /* The record is the NEWEST of the user enrolment, by the NOT EXISTS shape of
           groupscope::where() and the (timecreated, id) order of application_group(), so a user
           enrolment carrying two records (a restore, a hand edit) is one row, and every reader of
           s - the comment, the answers, the key's outcome, the search - reads the record the scope
           and the group column judge the row by. */
        $from = "{user_enrolments} ue
            LEFT JOIN {enrol_apply_applicationinfo} ai ON ai.userenrolmentid = ue.id
            LEFT JOIN {enrol_apply_submission} s ON s.userenrolmentid = ue.id
                      AND NOT EXISTS (
                          SELECT 1
                            FROM {enrol_apply_submission} snewer
                           WHERE snewer.userenrolmentid = s.userenrolmentid
                             AND (snewer.timecreated > s.timecreated
                                  OR (snewer.timecreated = s.timecreated AND snewer.id > s.id))
                      )
                 JOIN {user} u ON u.id = ue.userid
                 JOIN {enrol} e ON e.id = ue.enrolid
                 JOIN {course} c ON c.id = e.courseid
                 {$this->identitysql->joins}";

        /* The application's group on its NEWEST record, the one groupscope::where() and
           application_group() read - the key's group, or the one the applicant chose
           (groupscope::application_group_sql(), C-D4) - so the column names the group the scope and
           the group filter judge the row by. The fields keep their key-group names, as the column
           does: a chosen group is filed exactly where a key group is. Two LEFT joins, the first
           restricted to the newest record by the NOT EXISTS shape of groupscope::where(), so
           neither can add or remove a row. Only where the column is shown; the visibility of each
           group is judged per page in query_db(). */
        if ($this->groupcolumn) {
            $appgroup = groupscope::application_group_sql('kgs');
            $fields .= ", {$appgroup} AS keygroupid, kgs.keyoutcome AS keygroupoutcome,
                       kg.name AS keygroupname, kg.courseid AS keygroupcourseid, kg.visibility AS keygroupvisibility";
            $from .= "
            LEFT JOIN {enrol_apply_submission} kgs ON kgs.userenrolmentid = ue.id
                      AND NOT EXISTS (
                          SELECT 1
                            FROM {enrol_apply_submission} kgnewer
                           WHERE kgnewer.userenrolmentid = kgs.userenrolmentid
                             AND (kgnewer.timecreated > kgs.timecreated
                                  OR (kgnewer.timecreated = kgs.timecreated AND kgnewer.id > kgs.id))
                      )
            LEFT JOIN {groups} kg ON kg.id = {$appgroup}";
        }

        $this->narrowing = $this->build_narrowing();

        [$where, $params] = $this->where_without([]);
        $this->set_sql($fields, $from, $where, $selectparams + $params);
    }

    /**
     * The operator's own filters, one predicate each: everything scope_where() leaves out.
     *
     * The split scope_total() counts across, and the one {@see where_without()} leaves a filter out
     * of. Placeholder names are prefixed because the scope already binds `now`, `active`, `enrolid`
     * or `enrol`, `mentee<N>` and the group ids; a collision would make fix_sql_params() throw.
     *
     * @return array Filter name => [where fragment, parameters]: search, status, group (the
     *     group filter's name), one per field filter token, course, appliedfrom, appliedto, named,
     *     waited, appliedbefore; only the applied ones.
     */
    protected function build_narrowing(): array {
        global $DB;

        $narrowing = [];

        if ($this->search !== '') {
            $narrowing['search'] = $this->search_where();
        }

        /* The enrolment's status, not the record's: an approved participant later suspended from
           the participants page re-enters this queue as APPROVED on the record and SUSPENDED on
           the enrolment, and the queue lists what awaits a decision now. The option labels use the
           record's wording, which the operator reads on the review page. */
        if ($this->status !== null) {
            $narrowing['status'] = ['ue.status = :statusfilter', ['statusfilter' => $this->status]];
        }

        /* The group filter, AFTER the scope's own group narrowing and never instead of it: the
           value was cleaned against what this reader is offered, and even a value that got
           through could only narrow a narrowed reader's listing further. */
        if ($this->groupfilter !== null) {
            $narrowing[groupfilter::NAME] = groupfilter::where($this->scope, $this->groupfilter);
        }

        /* One predicate per applied field filter, over the expression core's identity mapping
           produced (see $identitymappings). */
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
                $narrowing[$token] = [$DB->sql_equal($offered->expression, ':' . $name, false, true), [$name => $value]];
                continue;
            }

            $unaccent = $unaccent ?? search::has_unaccent();
            $narrowing[$token] = [
                search::like_ai($offered->expression, ':' . $name, $unaccent),
                [$name => '%' . $DB->sql_like_escape($value) . '%'],
            ];
        }

        /* The course and the category, on indexed columns ({course}.category and {course}.id),
           unlike the search's LIKE, which can only scan. */
        [$coursewheres, $courseparams] = coursefilter::where($this->categoryfilter, $this->coursefilter);
        if ($coursewheres) {
            $narrowing['course'] = [implode(' AND ', $coursewheres), $courseparams];
        }

        /* The applied-date range, as whole days in the reader's timezone. The upper bound is the
           midnight starting the following day, compared with a strict less-than, so the "to" date
           is inclusive without assuming a day is 86400 seconds. */
        [$fromstamp, $tostamp] = queuefilter::day_bounds($this->appliedfrom, $this->appliedto);
        if ($fromstamp !== null) {
            $narrowing['appliedfrom'] = ['ue.timecreated >= :appliedfromstamp', ['appliedfromstamp' => $fromstamp]];
        }
        if ($tostamp !== null) {
            $narrowing['appliedto'] = ['ue.timecreated < :appliedtostamp', ['appliedtostamp' => $tostamp]];
        }

        if ($this->named !== null) {
            $narrowing['named'] = [$this->named_where($this->named), []];
        }

        /* Waited MORE than the days: submitted strictly before the moment that many days ago, the
           same comparison queueengine::tally() counts with. */
        if ($this->waited !== null) {
            $narrowing['waited'] = ['ue.timecreated < :waitedcut', ['waitedcut' => $this->now - $this->waited * DAYSECS]];
        }

        if ($this->appliedbefore) {
            [$prior, $priorparams] = $this->prior_sql('priorfilter');
            $narrowing['appliedbefore'] = ["EXISTS (SELECT 1 {$prior})", $priorparams];
        }

        return $narrowing;
    }

    /**
     * The FROM and WHERE of the applicant's earlier applications to this course, for a subquery.
     *
     * Every record of the applicant in the row's course but the row's own (`s`, the newest record
     * of the user enrolment; the null branch is for applications that predate the durable record
     * and have none), narrowed by the course report's groups for a reader it narrows, as the review
     * page's history is. Only meaningful where $history holds; the caller asks.
     *
     * @param string $prefix Placeholder prefix, unique in the caller's statement: the select list,
     *     the filter and a facet count each take their own.
     * @return array [SQL from "FROM" on, parameters].
     */
    public function prior_sql(string $prefix): array {
        $priorscope = '';
        $params = [];
        if ($this->reportgroups !== null) {
            [$priorwhere, $params] = groupscope::record_where('prior', $this->reportgroups, $prefix);
            $priorscope = 'AND ' . $priorwhere;
        }

        return [
            "FROM {enrol_apply_submission} prior
                   WHERE prior.courseid = c.id
                     AND prior.userid = ue.userid
                     AND (s.id IS NULL OR prior.id <> s.id)
                     {$priorscope}",
            $params,
        ];
    }

    /**
     * How a record named the application's group, as an SQL expression over a record aliased $alias.
     *
     * 1 for a key route (any key outcome: the key's group is the application's group), 2 for a group
     * chosen from the method's list without a key, 0 for neither, and 0 too where $alias found no
     * record. The two-way choice of {@see groupscope::application_group_sql()}, told apart rather
     * than merged. Before the upgrade that adds chosengroupid nobody chose.
     *
     * @param string $alias Alias of {enrol_apply_submission}, restricted to the newest record by the caller.
     * @return string An SQL expression taking no parameters.
     */
    public static function named_kind_sql(string $alias): string {
        $chosen = instanceoptions::schema_ready() ? "WHEN {$alias}.chosengroupid > 0 THEN 2 " : '';

        return "(CASE WHEN {$alias}.keyoutcome <> " . submissionrecord::KEY_NONE . " THEN 1 {$chosen}ELSE 0 END)";
    }

    /**
     * The kind {@see named_kind_sql()} gives each value of the named filter.
     *
     * @param string $value One of NAMED.
     * @return int 1 key, 2 chosen, 0 none.
     */
    public static function named_kind(string $value): int {
        return match ($value) {
            self::NAMED_KEY => 1,
            self::NAMED_CHOSEN => 2,
            default => 0,
        };
    }

    /**
     * The named filter's predicate, over a user enrolment aliased "ue".
     *
     * Judged on the newest record, the NOT EXISTS shape of {@see groupscope::group_where()}; an
     * application without a record named nothing, so "none" is the complement of the other two and
     * every row is reached by exactly one value.
     *
     * @param string $value One of NAMED.
     * @return string The where fragment; it takes no parameters.
     */
    protected function named_where(string $value): string {
        $kind = self::named_kind_sql('namedsub');
        $newest = "SELECT 1
                      FROM {enrol_apply_submission} namedsub
                     WHERE namedsub.userenrolmentid = ue.id
                       AND NOT EXISTS (
                           SELECT 1
                             FROM {enrol_apply_submission} namednewer
                            WHERE namednewer.userenrolmentid = namedsub.userenrolmentid
                              AND (namednewer.timecreated > namedsub.timecreated
                                   OR (namednewer.timecreated = namedsub.timecreated AND namednewer.id > namedsub.id))
                       )";

        if ($value === self::NAMED_NONE) {
            return "NOT EXISTS ({$newest} AND {$kind} <> 0)";
        }

        return "EXISTS ({$newest} AND {$kind} = " . self::named_kind($value) . ')';
    }

    /**
     * The listing's WHERE with some of the operator's filters left out, the scope always kept.
     *
     * What a count under "every other criterion in force" reads: a facet's options are counted
     * without the facet's own value, so each count is what choosing that option would list. With no
     * name it is the listing's own WHERE, which build_sql() hands to set_sql().
     *
     * @param array $names Filter names to leave out, as {@see applied_filter_names()} lists them.
     * @return array [where, parameters]: the parameters of the WHERE and of the FROM's identity joins.
     */
    public function where_without(array $names): array {
        [$wheres, $params] = $this->scopeparts;
        foreach ($this->narrowing as $name => [$where, $whereparams]) {
            if (in_array($name, $names, true)) {
                continue;
            }
            $wheres[] = $where;
            $params += $whereparams;
        }

        return [implode(' AND ', $wheres), $params + $this->identitysql->params];
    }

    /**
     * The names of the filters the operator applied, as {@see where_without()} takes them.
     *
     * @return array Filter names.
     */
    public function applied_filter_names(): array {
        return array_keys($this->narrowing);
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
        $columns = [];
        $headers = [];

        // Only where something can be done with a selection; see can_decide().
        if ($this->can_decide()) {
            $columns[] = 'checkboxcolumn';
            $headers[] = $this->select_all_header();
        }

        /* The course, only for the site-wide and mentee scopes, which span courses; every row of
           an instance-scoped queue belongs to the same course. */
        if ($this->scope->instance === null) {
            $columns[] = 'course';
            $headers[] = get_string('course');
        }

        $columns[] = 'fullname';
        // The heading of a column named 'fullname' is filled in by table_sql itself.
        $headers[] = 'fullname';

        /* The key group, right after the applicant so that the card view, which stacks the cells,
           shows it under the name like the identity line. See set_filterset() for where. */
        if ($this->groupcolumn) {
            $columns[] = 'keygroup';
            $headers[] = get_string('queuegroup', 'enrol_apply');
        }

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

        /* No column of its own for the link to each application's review page: it is a button
           in the applicant's cell (MQ-D7), so it stays on screen however far the grid scrolls. */

        $this->define_columns($columns);
        $this->define_headers($headers);

        /* Stable classes for the cells styles.css fixes to the grid's left edge and clamps, rather
           than core's positional c0, c1, ..., which shift with the scope's course column. */
        if ($this->can_decide()) {
            $this->column_class('checkboxcolumn', 'enrol_apply-col-select');
        }
        $this->column_class('fullname', 'enrol_apply-col-applicant');
        if ($this->scope->identitycontext !== null) {
            $this->column_class('snapshot', 'enrol_apply-col-answers');
        }
        $this->column_class('applycomment', 'enrol_apply-col-comment');
        // The short cells, which keep a width of their own and share a line on a card.
        foreach (['course', 'keygroup', 'applydate'] as $short) {
            $this->column_class($short, 'enrol_apply-col-short');
        }

        /* Names the cell that identifies each row, so table_sql emits it as a
           <th scope="row"> and a screen reader announces every other cell of the row
           against the applicant's name rather than reading a wall of bare values. */
        $this->define_header_column('fullname');
        $this->no_sorting('checkboxcolumn');
        /* Unsortable: the column renders pairs out of a JSON envelope, which SQL cannot order by;
           see also search_columns() on why it must stay out of SQL. */
        $this->no_sorting('snapshot');
        $this->no_sorting('applycomment');
        /* Not sortable, as the report's column is not (V-D1): an order by name would place a cell
           withheld from this reader among the names around it. The group filter stands in. */
        if ($this->groupcolumn) {
            $this->no_sorting('keygroup');
        }
        $this->sortable(true, 'applydate', SORT_ASC);
    }

    /**
     * What an empty result says, which depends on why it is empty.
     *
     * Core's "Nothing to display" is right for a queue with no applications, but a filter that
     * matched nothing gets a message naming the filter, so it does not read as an empty queue.
     *
     * Both are drawn as the fleet Design System's empty state (empty-state.md) inside core's
     * dynamic-table region, so an AJAX refresh still finds the region it replaces: the empty queue
     * as the full form, with core's own string (a Behat scenario in
     * tests/behat/enrol_apply.feature asserts it), and the filtered one as the compact line, with
     * no button of its own: the chip row's "Clear all" right above it is the way out. No initials
     * bar: see initialbars().
     *
     * @return void
     */
    public function print_nothing_to_display() {
        global $OUTPUT;

        echo $this->get_dynamic_table_html_start();
        echo $this->render_reset_button();

        if (!$this->is_narrowed()) {
            echo html_writer::div(
                html_writer::span($OUTPUT->pix_icon('i/enrolusers', ''), 'enrol_apply-empty-icon')
                    . html_writer::tag('p', s(get_string('nothingtodisplay')), ['class' => 'enrol_apply-empty-title']),
                'enrol_apply-empty',
                ['data-region' => 'queueempty']
            );
        } else {
            echo html_writer::tag(
                'p',
                s(get_string('queuefilterempty', 'enrol_apply')),
                ['class' => 'enrol_apply-empty--compact', 'data-region' => 'queueempty']
            );
        }

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
     * The per-row selection checkbox, on every row but the reader's own application.
     *
     * A decision posted for that row would be skipped by can_manage_application(), so a bulk
     * approval would leave it alone in silence; the name cell says why there is no box instead.
     *
     * @param stdClass $row Row data.
     * @return string Rendered checkbox with its accessible label, or nothing on the reader's own row.
     */
    public function col_checkboxcolumn($row) {
        global $OUTPUT;

        if (!$this->selectable($row)) {
            return '';
        }

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
     * Whether a row may be selected for a decision.
     *
     * Never for a reader who may not decide from this listing (a viewer, S5: their table has no
     * selection column at all), and never the reader's own application, which
     * can_manage_application() would skip in silence. The one predicate behind this table's
     * checkbox and the headless reader's selectable flag.
     *
     * @param stdClass $row Row data carrying userid.
     * @return bool True when the row gets a selection box.
     */
    public function selectable(stdClass $row): bool {
        return $this->can_decide() && !queue::is_own_application((int) $row->userid);
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
     * The applicant: their picture, their name, what is unusual about them, who they are, and the
     * way into their application.
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
        global $OUTPUT;

        $user = user_picture::unalias($row, ['username'], 'userid');

        // A link only where user/view.php would open the profile, judged for the page in query_db().
        $profile = $this->profile_url($row);
        $name = $profile !== null
            ? html_writer::link(
                $profile,
                s(fullname($user)),
                ['class' => 'enrol_apply-applicantname']
            )
            : html_writer::span(s(fullname($user)), 'enrol_apply-applicantname');

        /* Status labels of the fleet Design System (status-label.md), not Bootstrap badges: a
           static word in a tone, its tint, edge and ink read from one token triple. */
        $labels = [];
        foreach ($this->tags($row) as $tag) {
            $labels[] = $this->status_label($tag['text'], self::TAG_TONES[$tag['kind']], $tag['title']);
        }

        $identity = [];
        foreach ($this->identity_values($row) as $value) {
            $identity[] = html_writer::span(s($value), 'enrol_apply-identityvalue');
        }

        $lines = html_writer::div($name . ($labels ? ' ' . implode(' ', $labels) : ''), 'enrol_apply-applicantline');
        if ($identity) {
            $lines .= html_writer::div(
                implode('', $identity),
                'enrol_apply-identityline small text-muted'
            );
        }

        /* The door to this one application, in the cell that names the applicant rather than in
           a last column of its own, so it is on screen at any width and on every row (MQ-D7). A
           link, which every queue reader may follow: the review page opens read-only for a viewer.
           The aria-label names the applicant, as the visible word is "Review" on every row. Not a
           title, which is not announced reliably and is invisible to a keyboard user. */
        $review = html_writer::link(
            new moodle_url('/enrol/apply/manage.php', ['userenrol' => $row->userenrolmentid]),
            get_string('queuereview', 'enrol_apply'),
            [
                'class' => 'btn btn-sm btn-outline-secondary enrol_apply-reviewbutton',
                'aria-label' => get_string('queuereviewapplicant', 'enrol_apply', fullname($user)),
            ]
        );

        /* What the decision sheet reads off a selected row (amd/src/manage.js): the method the
           application belongs to, and on the queues that span methods its name, its course and
           whether its role decides. Plain spellings: html_writer escapes attribute values, and the
           module reads them back through dataset and writes them with textContent. Nothing here is
           new to this reader, who sees the course and the method in the row itself. */
        $attributes = ['data-region' => 'applicant', 'data-enrolid' => (int) $row->enrolid];
        $method = $this->methods[(int) $row->enrolid] ?? null;
        if ($method !== null) {
            $attributes['data-methodname'] = $method->name;
            $attributes['data-coursename'] = format_string($row->course, true, [
                'context' => context_course::instance((int) $row->courseid),
                'escape' => false,
            ]);
            $attributes['data-deciding'] = $method->deciding ? '1' : '0';
        }

        return html_writer::div(
            $OUTPUT->user_picture($user, ['popup' => true])
                . html_writer::div($lines, 'enrol_apply-applicanttext')
                . $review,
            'enrol_apply-applicant',
            $attributes
        );
    }

    /**
     * What is unusual about a row's application, as data: the labels under the applicant's name.
     *
     * The one definition of which labels a row carries, read by col_fullname() and by the headless
     * reader. In this order: Deferred (MQ-D10, the owner's word for the waiting list); what a group
     * key did, by the record's own label and without the group's name (the mentee scope spans
     * courses whose groups a mentor may not see, and the review page names the group to a reader
     * who may; the short form shows, MQ-D11, the full sentence is the title); Applied before, only
     * where the earlier applications are counted ({@see set_filterset()}); and Yours, the reason a
     * decider's queue offers no selection box on this row ({@see selectable()}).
     *
     * @param stdClass $row Row data.
     * @return array Each: kind (a key of TAG_TONES), text (plain), title (plain, '' for none).
     */
    public function tags(stdClass $row): array {
        global $CFG;

        /* ENROL_APPLY_USER_WAIT is defined in lib.php, which is not autoloaded. manage.php
           includes it, but the dynamic table service does not, so without this line an AJAX
           refresh fails. */
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        $tags = [];
        if ($row->enrolstatus == ENROL_APPLY_USER_WAIT) {
            $tags[] = ['kind' => 'deferred', 'text' => get_string('enrolmentwaiting', 'enrol_apply'), 'title' => ''];
        }
        $keyoutcome = (int) ($row->keyoutcome ?? 0);
        $keylabel = submissionrecord::key_outcome_label($keyoutcome);
        if ($keylabel !== '') {
            $tags[] = ['kind' => 'key', 'text' => submissionrecord::key_outcome_short_label($keyoutcome), 'title' => $keylabel];
        }
        if (!empty($row->appliedbefore)) {
            $tags[] = ['kind' => 'appliedbefore', 'text' => get_string('queueappliedbefore', 'enrol_apply'), 'title' => ''];
        }
        if ($this->can_decide() && queue::is_own_application((int) $row->userid)) {
            $tags[] = [
                'kind' => 'own',
                'text' => get_string('queueownapplication', 'enrol_apply'),
                'title' => get_string('ownapplicationnotdecidable', 'enrol_apply'),
            ];
        }

        return $tags;
    }

    /**
     * The applicant's profile, where user/view.php would open it for this reader.
     *
     * Judged for the whole page in {@see prepare_rows()} ({@see profilelink::viewable_ids()}).
     *
     * @param stdClass $row Row data carrying userid and courseid.
     * @return moodle_url|null The profile, or null where the name is not a link.
     */
    public function profile_url(stdClass $row): ?moodle_url {
        if (!isset($this->profilelinks[(int) $row->courseid . ':' . (int) $row->userid])) {
            return null;
        }

        return new moodle_url('/user/view.php', ['id' => (int) $row->userid, 'course' => (int) $row->courseid]);
    }

    /**
     * The identity fields of a row this reader may see, in core's order, empty ones left out.
     *
     * Core's rule ({@see identity}), resolved once for the listing in set_filterset(). Plain
     * spellings: every caller escapes.
     *
     * @param stdClass $row Row data.
     * @return array Identity field name => value.
     */
    public function identity_values(stdClass $row): array {
        $values = [];
        foreach ($this->extrafields as $field) {
            $value = (string) ($row->{$field} ?? '');
            if ($value !== '') {
                $values[$field] = $value;
            }
        }

        return $values;
    }

    /**
     * A status label of the fleet Design System: a short static word in one tone.
     *
     * The tone is one of the plugin's literal modifier classes, so the stylesheet pairs its tint,
     * edge and ink from one token triple. A longer wording, when there is one, becomes the label's
     * title for the pointer and visually hidden text after the short one, so a screen reader and a
     * text search of the page still meet the whole sentence.
     *
     * @param string $text The visible word, plain spelling.
     * @param string $tone One of warning, info or neutral.
     * @param string $long The full wording, plain spelling; empty, or equal to $text, for none.
     * @return string The label's markup.
     */
    protected function status_label(string $text, string $tone, string $long = ''): string {
        $class = match ($tone) {
            'warning' => 'enrol_apply-status enrol_apply-status--warning',
            'info' => 'enrol_apply-status enrol_apply-status--info',
            default => 'enrol_apply-status enrol_apply-status--neutral',
        };

        if ($long === '' || $long === $text) {
            return html_writer::span(s($text), $class);
        }

        // Attribute values are escaped by html_writer, so the title takes the plain spelling.
        return html_writer::span(
            s($text) . html_writer::span(' ' . s($long), 'visually-hidden'),
            $class,
            ['title' => $long]
        );
    }

    /**
     * The application's group - its key's, or the one the applicant chose (C-D4) - as far as this
     * reader may be told it.
     *
     * The rules of the course report's cells (submission formatter key_group() and
     * chosen_group()): a group the reader may not see by core's visibility rule leaves the cell
     * blank, card label included (V-D13); a group that is gone, or was never mapped by a restore,
     * says so; and an application made without a key or a choice says it has no group.
     *
     * An escaping boundary: flexible_table writes the cell unescaped, so the name goes out in the
     * ESCAPED spelling format_string() gives by default.
     *
     * @param stdClass $row Row data carrying keygroupid (the application's group), keygroupoutcome,
     *     keygroupname, keygroupcourseid and keygroupvisibility, null where the joins found nothing.
     * @return string Rendered cell.
     */
    public function col_keygroup($row) {
        $html = $this->keygroup_html($row);
        if ($html === null) {
            return '';
        }

        return $this->card_label(s(get_string('queuegroup', 'enrol_apply'))) . $html;
    }

    /**
     * The group cell's content without its card label, null where the reader may not be told it.
     *
     * The rules {@see col_keygroup()} documents; read by that cell and by the headless reader.
     *
     * @param stdClass $row Row data carrying the group fields col_keygroup() reads.
     * @return string|null The cell's HTML, escaped; null for a group withheld from this reader.
     */
    public function keygroup_html(stdClass $row): ?string {
        if ((int) ($row->keygroupoutcome ?? 0) === submissionrecord::KEY_NONE && (int) ($row->keygroupid ?? 0) <= 0) {
            return s(get_string('queuegroupnone', 'enrol_apply'));
        }
        if (($row->keygroupcourseid ?? null) === null) {
            return s(get_string('keygroupgone', 'enrol_apply'));
        }
        if (empty($this->visiblegroups[(int) $row->keygroupid])) {
            return null;
        }

        return format_string($row->keygroupname, true, [
            'context' => context_course::instance((int) $row->keygroupcourseid),
        ]);
    }

    /**
     * The course column, linking to the course, with the enrolment method under it.
     *
     * Only the site-wide and mentee queues have this column, and they are the queues whose
     * selection can span methods: two methods of one course share a course name, so the method's
     * name is the second line (MQ-D15), with a warning label when its role decides applications
     * (MQ-D16), the C-D29 rule read once per method in query_db(). The method name is escaped here,
     * flexible_table writing the cell unescaped.
     *
     * @param stdClass $row Row data.
     * @return string Rendered cell.
     */
    public function col_course($row) {
        $url = new moodle_url('/course/view.php', ['id' => $row->courseid]);
        $cell = $this->card_label(s(get_string('course')))
            . html_writer::link($url, format_string($row->course), ['target' => '_blank']);

        $method = $this->methods[(int) $row->enrolid] ?? null;
        if ($method === null) {
            return $cell;
        }

        return $cell . html_writer::div(
            html_writer::span(s($method->name), 'enrol_apply-methodname')
                . ($method->deciding
                    ? ' ' . $this->status_label(get_string('queuegrantsdecidingrole', 'enrol_apply'), 'warning')
                    : ''),
            'enrol_apply-methodline small text-muted'
        );
    }

    /**
     * The application date column.
     *
     * @param stdClass $row Row data.
     * @return string Rendered cell.
     */
    public function col_applydate($row) {
        return $this->card_label(s(get_string('applydate', 'enrol_apply'))) . $this->applydate_html($row);
    }

    /**
     * How long the applicant has been waiting, with the exact date underneath; no card label.
     *
     * @param stdClass $row Row data carrying applydate.
     * @return string The cell's HTML.
     */
    public function applydate_html(stdClass $row): string {
        return html_writer::div(format_time(time() - $row->applydate), 'enrol_apply-applyago')
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
     * @return array|bool What submissionformatter::shows() reads for this reader and course.
     */
    public function visible_keys(int $courseid) {
        if (!array_key_exists($courseid, $this->visiblekeys)) {
            $this->visiblekeys[$courseid] = submissionformatter::visible_keys(
                context_course::instance($courseid)
            );
        }

        return $this->visiblekeys[$courseid];
    }

    /**
     * Fetch the page, then the frozen answers of every row on it in one query.
     *
     * A batch read after the rows rather than an aggregate column in the listing's own SQL: the
     * answers keep their structure (key, label, value), which the per-row masking in col_snapshot()
     * matches on, and the listing's count and narrowing queries stay free of a subquery per row.
     * One extra statement per page, whatever its size;
     * test_the_answers_of_a_whole_page_are_read_in_one_query holds that. The key groups the page
     * names are judged for visibility the same way, at most one membership query per page.
     *
     * @param int $pagesize Rows per page.
     * @param bool $useinitialsbar Passed through to core.
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        parent::query_db($pagesize, $useinitialsbar);

        $this->prepare_rows((array) $this->rawdata);
    }

    /**
     * Read what the cells of a page need, in a bounded number of statements whatever its size.
     *
     * The answers of every row in one query, the profile links once per course of the page, the
     * groups' visibility at most once, the methods' facts from the rows themselves. Called by
     * query_db() for this table's own page and by the headless reader for the page it read over
     * this table's SQL, so the two draw their cells from the same facts.
     *
     * @param array $rows The page's rows, as this table's SQL selects them.
     * @return void
     */
    public function prepare_rows(array $rows): void {
        global $USER;

        $this->snapshots = submissionrecord::read_snapshots(array_column($rows, 'submissionid'));

        /* Whose profile user/view.php would open for this reader, judged once per course of the
           page: per row, core's rule runs three statements. A moderator narrowed by separate
           groups is refused the profile of an applicant who is in no group yet. */
        $this->profilelinks = [];
        $bycourse = [];
        foreach ($rows as $row) {
            $bycourse[(int) $row->courseid][(int) $row->userid] = (int) $row->userdeleted;
        }
        foreach ($bycourse as $courseid => $users) {
            foreach (profilelink::viewable_ids($users, get_course($courseid)) as $userid) {
                $this->profilelinks[$courseid . ':' . $userid] = true;
            }
        }

        /* Which of the page's key groups this reader may see, judged once for the page: per row,
           visible_to() would run a membership query for every row of a MEMBERS or OWN group. */
        $this->visiblegroups = [];
        if ($this->groupcolumn) {
            $groups = [];
            foreach ($rows as $row) {
                if (($row->keygroupcourseid ?? null) !== null) {
                    $groups[(int) $row->keygroupid] = (object) [
                        'id' => $row->keygroupid,
                        'courseid' => $row->keygroupcourseid,
                        'visibility' => $row->keygroupvisibility,
                    ];
                }
            }
            if ($groups) {
                $this->visiblegroups = array_fill_keys(groupvisibility::visible_ids($groups, (int) $USER->id), true);
            }
        }

        /* Which method each row belongs to, on the queues that span methods (MQ-D15, MQ-D16): its
           name, and whether its role decides applications in its course, the C-D29 rule. Judged
           once per method of the page, from the columns the listing already selects, because the
           role's answer is a capability resolution per call. */
        $this->methods = [];
        if ($this->scope->instance === null) {
            foreach ($rows as $row) {
                $enrolid = (int) $row->enrolid;
                if (isset($this->methods[$enrolid])) {
                    continue;
                }
                $instance = (object) [
                    'id' => $enrolid,
                    'enrol' => 'apply',
                    'courseid' => (int) $row->courseid,
                    'name' => (string) ($row->enrolname ?? ''),
                    'roleid' => (int) ($row->enrolroleid ?? 0),
                    'customchar1' => $row->enrolkeymode,
                ];
                $this->methods[$enrolid] = (object) [
                    'name' => self::method_name($instance),
                    'deciding' => decidingrole::method_decides($instance),
                ];
            }
        }
    }

    /**
     * A method's name in the PLAIN spelling, for a data attribute and for s() in a cell.
     *
     * The plugin's get_instance_name() returns two spellings: a custom name through
     * format_string() with its default escaping, and the plugin's own wording, a bare lang string,
     * when the method has none. The custom name is therefore formatted here without escaping, and
     * the lang string is taken as it is, so every caller escapes exactly once.
     *
     * @param stdClass $instance The {enrol} record, or the fields of it the listing selects.
     * @return string The name, plain spelling.
     */
    protected static function method_name(stdClass $instance): string {
        if (empty($instance->name)) {
            return enrol_get_plugin('apply')->get_instance_name($instance);
        }

        return format_string($instance->name, true, [
            'context' => context_course::instance((int) $instance->courseid),
            'escape' => false,
        ]);
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
     * @param stdClass $row Row data carrying the record's id as `submissionid`, null for none.
     * @return string Rendered cell.
     */
    public function col_snapshot($row) {
        $separator = get_string('labelsep', 'langconfig');
        $answers = [];
        $whole = [];
        foreach ($this->visible_entries($row) as $entry) {
            $label = s($entry['label']);
            $value = s($entry['value']);
            $answers[] = html_writer::span(
                html_writer::span($label . $separator, 'enrol_apply-fieldname') . $value,
                'enrol_apply-answer'
            );
            // Plain spelling: html_writer escapes the title attribute itself.
            $whole[] = $entry['label'] . $separator . $entry['value'];
        }

        if ($answers === []) {
            return '';
        }

        /* One flowing line of answers in one element, which styles.css clamps to two lines
           (MQ-D8); every answer stays in the markup and the whole set is the title. */
        return $this->card_label(s(get_string('queuesubmitted', 'enrol_apply')))
            . html_writer::div(implode('', $answers), 'enrol_apply-answers', ['title' => implode(' · ', $whole)]);
    }

    /**
     * The frozen answers of a row this reader may see, in the order they were submitted.
     *
     * Masked in the row's own course ({@see visible_keys()}); read by col_snapshot() and by the
     * headless reader's per-answer cells, so a key withheld from one is withheld from the other.
     *
     * @param stdClass $row Row data carrying courseid and the record's id as `submissionid`.
     * @return array Each: key, label, value (plain spellings).
     */
    public function visible_entries(stdClass $row): array {
        $visible = $this->visible_keys((int) $row->courseid);

        $entries = [];
        foreach ($this->snapshots[(int) ($row->submissionid ?? 0)] ?? [] as $entry) {
            if (!submissionformatter::shows($entry['key'], $visible)) {
                // Dropped without a marker; see submissionformatter::visible_keys().
                continue;
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Whether this listing carries the application's group (its column, its filter's facets).
     *
     * @return bool True where set_filterset() put the group column on.
     */
    public function has_group_column(): bool {
        return $this->groupcolumn;
    }

    /**
     * Whether this listing counts each applicant's earlier applications to the course.
     *
     * @return bool True on one instance's queue for a holder of enrol/apply:viewreports there.
     */
    public function counts_history(): bool {
        return $this->history;
    }

    /**
     * The clock's time the filters were read at, which the waited filter counts back from.
     *
     * @return int Unix time.
     */
    public function now(): int {
        return $this->now;
    }

    /**
     * The named, waited and applied-before filters applied, as their values travel.
     *
     * @return array Filter name => value, only the applied ones.
     */
    public function get_facet_filters(): array {
        $filters = [];
        if ($this->named !== null) {
            $filters['named'] = $this->named;
        }
        if ($this->waited !== null) {
            $filters['waited'] = (string) $this->waited;
        }
        if ($this->appliedbefore) {
            $filters['appliedbefore'] = '1';
        }

        return $filters;
    }

    /**
     * The reader's own groups in the scope's course, which the group filter's "mine" selects.
     *
     * @return array Group ids; empty off the instance queue.
     */
    public function own_groups(): array {
        if ($this->scope->instance === null) {
            return [];
        }

        return groupfilter::own_groups($this->scope);
    }

    /**
     * The scope's instance, null on the queues that span methods.
     *
     * @return stdClass|null The {enrol} record.
     */
    public function get_instance(): ?stdClass {
        return $this->scope->instance;
    }

    /**
     * How many of this operator's applications await a decision, how long the oldest has waited,
     * how many waited longer than a bound and how many are deferred, before any filter.
     *
     * One statement over scope_where(), the predicate scope_total() and scope_deferred() count:
     * the capacity tiles of a headless client, so a narrowed decider is told about their own
     * applications, as the page's tiles tell them (V-D3).
     *
     * @param int $late Seconds after which an application counts as waiting long.
     * @return array 'awaiting', 'deferred', 'late' (counts) and 'oldest' (the earliest applied
     *     time, 0 for none).
     */
    public function scope_figures(int $late): array {
        global $CFG, $DB;

        // ENROL_APPLY_USER_WAIT lives in the plugin's lib.php, which is not autoloaded.
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        [$wheres, $params] = $this->scope_where();
        $params['figuresdeferred'] = ENROL_APPLY_USER_WAIT;
        $params['figureslate'] = $this->now - $late;

        $record = $DB->get_record_sql(
            'SELECT COUNT(1) AS awaitingcount,
                    MIN(ue.timecreated) AS oldestapplied,
                    SUM(CASE WHEN ue.status = :figuresdeferred THEN 1 ELSE 0 END) AS deferredcount,
                    SUM(CASE WHEN ue.timecreated < :figureslate THEN 1 ELSE 0 END) AS latecount
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ' . implode(' AND ', $wheres),
            $params
        );

        return [
            'awaiting' => (int) $record->awaitingcount,
            'deferred' => (int) ($record->deferredcount ?? 0),
            'late' => (int) ($record->latecount ?? 0),
            'oldest' => (int) ($record->oldestapplied ?? 0),
        ];
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

        /* Clamped to two lines by styles.css; the whole comment stays in the markup and is the
           cell's title, the plain spelling, which html_writer escapes. */
        $comment = (string) ($row->applycomment ?? '');

        return $this->card_label($label) . html_writer::div(
            format_text($comment, FORMAT_PLAIN),
            'enrol_apply-clamp',
            $comment === '' ? [] : ['title' => $comment]
        );
    }
}
