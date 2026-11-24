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

namespace enrol_apply\output;

use context_course;
use enrol_apply\local\capacity;
use enrol_apply\local\coursefilter;
use enrol_apply\local\groupscope;
use enrol_apply\local\queue;
use enrol_apply\local\queueengine;
use enrol_apply\table\applications;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * The approval queue as a data report: the page of the method queue (CQ-D5) and of the site-wide queue (W3).
 *
 * The shell manage.php renders for `?id=<method>`, and for the id-less page whatever its reader's listing
 * is there (the site-wide queue for a site-wide decider or viewer, the mentee queue for a mentor, W5;
 * {@see wanted()}), unless `view=table` asks for today's table. The mentee queue shows what the mentee table
 * shows and nothing more (SQ-D13): the server withholds the rest (the catalogue, the facets, the summary,
 * a method's queue address and "Full" are queueengine's, the props are {@see props()}), never the client. The
 * page carries configuration and strings only; every row and every number arrives through the
 * plugin's web services (enrol_apply_queue_rows, enrol_apply_queue_summary), which read the queue's
 * own table ({@see queueengine}). So nothing here may put an application into the props: the
 * component is mounted by core's react_autoinit from a data attribute, and whatever the props hold is
 * in the page for anyone who reads its source.
 *
 * The filters, the scope, the search, the sort and the page live in the page's address (CQ-D6), under
 * the names manage.php reads for its table (`status`, `search`, `group`, the identity tokens,
 * `appliedfrom`, `appliedto`) and, for what the table has no control for, `named`, `waited`,
 * `appliedbefore`, `sort`, `dir`, `page` and `perpage`. {@see initial()} reads them for the first
 * paint; the client writes them back as the reader changes them. Only the columns and their widths
 * are the reader's preference (enrol_apply_queue_grid).
 *
 * Deciding is not this view's (CQ-D4): the client submits a plain form to manage.php with the
 * table's own fields, so decision::apply() and every guard behind it run as they do for the table.
 * The props say only what the sheet may offer, read from the table form's own context
 * ({@see \enrol_apply_renderer::queue_decision_controls()}), and {@see return_url()} is where that
 * decision lands, the address cleaned as a first paint cleans it.
 *
 * Today's server-rendered queue goes into the shell's noscript, and the "Table view" link renders
 * it whole.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_view implements renderable, templatable {
    /** @var string The view parameter's value that asks for today's table instead of this view. */
    public const TABLE = 'table';

    /** @var string The view the bundle mounts for these props (js/esm/src/views.tsx). */
    public const MOUNT = 'queue';

    /** @var int The shortest search the client sends; the table itself searches any length. */
    public const SEARCH_MIN = 1;

    /** @var string[] The facets the queue's own controls do not send, read from the address here. */
    public const FACETS = ['named', 'waited', 'appliedbefore'];

    /** @var string[] The site-wide queue's own facets read here; its course and category are manage.php's. */
    public const WIDE_FACETS = ['waited', 'method', 'grouped'];

    /** @var string[] The mentee queue's own facets read here: the one its table and the rows service offer a mentor. */
    public const MENTEE_FACETS = ['waited'];

    /**
     * @var string The address parameter that asks the site-wide queue for its "By course" view (SQ-D9). Its own
     *     name, so that it is never read as `view`, which stays the server's switch to the table.
     */
    public const SHOW = 'show';

    /** @var string The one value SHOW takes: the "By course" view. */
    public const METHODS = 'methods';

    /** @var stdClass|null The apply method whose queue this is; null for the site-wide and mentee queues. */
    protected ?stdClass $instance;

    /** @var applications Today's queue for the same request, for the noscript. */
    protected applications $table;

    /** @var moodle_url The queue's address with its filters, without the view parameter. */
    protected moodle_url $manageurl;

    /** @var array What the address asks for; see {@see initial()}. */
    protected array $initial;

    /**
     * Build the shell.
     *
     * @param stdClass|null $instance The apply method; null for the site-wide and mentee queues.
     * @param applications $table Today's queue as manage.php built it for this request.
     * @param moodle_url $manageurl The queue's address with its filters, without the view parameter.
     * @param array $initial What the address asks for, from {@see initial()}.
     */
    public function __construct(?stdClass $instance, applications $table, moodle_url $manageurl, array $initial) {
        $this->instance = $instance;
        $this->table = $table;
        $this->manageurl = $manageurl;
        $this->initial = $initial;
    }

    /**
     * Whether manage.php renders this view for a listing request, rather than today's table.
     *
     * One predicate for every listing (CQ-D5, SQ-D12, SQ-D13): the queue of one method, the site-wide queue
     * (deciding or viewing) and the mentee queue take the grid, unless the address asks for the table with
     * `view=table`, which anyone may. The scope is the reader's listing as queue::listing_scope() resolved
     * it, never the request: for the id-less page it is the site-wide queue or the mentee queue by what the
     * reader holds.
     *
     * A refused listing (`allowed` false) never takes the grid, by its own condition here: manage.php
     * refuses that reader before asking, and the predicate does not lean on it. Asked on the listing path
     * only: manage.php serves `userenrol=` before it reads `id`.
     *
     * @param stdClass $listing The reader's listing scope, from queue::listing_scope().
     * @param string $view The request's view parameter.
     * @return bool True for an allowed listing without view=table.
     */
    public static function wanted(stdClass $listing, string $view): bool {
        return $view !== self::TABLE && $listing->allowed;
    }

    /**
     * The queue's address in one view or the other, with the filters it carries.
     *
     * @param moodle_url $manageurl The queue's address with its filters.
     * @param bool $grid Whether the address is this view's (no view parameter) or the table's (view=table).
     * @return moodle_url The address.
     */
    public static function url(moodle_url $manageurl, bool $grid): moodle_url {
        $url = new moodle_url($manageurl);
        if ($grid) {
            $url->remove_params('view');
        } else {
            $url->param('view', self::TABLE);
        }

        return $url;
    }

    /**
     * Where a decision posted from this view lands: the view, with the address it was posted from.
     *
     * The client posts to its own address (js/esm/src/queue/address.ts), so the scope, the filters,
     * the search, the sort, the page and the page size ride in the form's action as they ride in the
     * table form's. They are cleaned here as a first paint and the rows service clean them: the scope,
     * the search and the table's filters as manage.php read them; the facets through the queue's own
     * table, whose url_params() keeps only what it applied for this reader; the sort only when this
     * reader's catalogue offers it as sortable; the page size from its vocabulary ({@see initial()}).
     * A value at its default is left out, as the client leaves it out. Called before the decision (nothing in it
     * depends on the outcome), so an address can never fail a decision already applied; a page past the end is
     * the rows service's to bring back.
     *
     * With 0 it is the id-less page's address, rebuilt through the same table for the listing
     * queue::listing_scope(0) gives this reader and read as that listing's first paint ({@see initial()}): on
     * the site-wide queue its course, category, method and grouped filters as the table applied them, on the
     * mentee queue none of them (its table applies none), and its own catalogue's sort. Never the "By course"
     * view's SHOW: a decision is made from the list, so it lands on the list.
     *
     * @param int $enrolid The method, already authorised by manage.php; 0 for the site-wide or mentee queue.
     * @param string $search The search manage.php read.
     * @param int|null $status The status manage.php read, null for every application.
     * @param array $filters The filters manage.php read, name => value.
     * @return moodle_url manage.php?id=<method>, or manage.php for 0, with the cleaned address and no view parameter.
     * @throws \required_capability_exception When the reader may not open the queue.
     */
    public static function return_url(int $enrolid, string $search, ?int $status, array $filters): moodle_url {
        // The id-less page is read as the listing this reader has there; a method's queue needs none.
        $listing = $enrolid === 0 ? queue::listing_scope(0) : null;
        $initial = self::initial($search, $status, $filters, $listing);
        $table = queueengine::table(
            $enrolid,
            $initial['scope'],
            $initial['search'],
            array_column($initial['filters'], 'value', 'name')
        );

        $params = $table->url_params();
        $sort = $initial['sort'];
        $catalogue = queueengine::catalogue($table);
        if (isset($catalogue[$sort]) && $catalogue[$sort]['sortable'] && ($sort !== 'wait' || $initial['direction'] !== 'asc')) {
            $params['sort'] = $sort;
            $params['dir'] = $initial['direction'];
        }
        if ($initial['page'] > 0) {
            $params['page'] = $initial['page'];
        }
        if ($initial['perpage'] !== queueengine::PERPAGE) {
            $params['perpage'] = $initial['perpage'];
        }

        return new moodle_url('/enrol/apply/manage.php', $params);
    }

    /**
     * What the address asks the view to show first.
     *
     * The scope, the search and the table's filters are the ones manage.php has read and cleaned for
     * its table (`status`, `search`, {@see applications::request_filters()}, which applies the group
     * filter's default where the address names none), so a link the table page or the course report
     * builds lands filtered here too. The facets, the sort and the page are read here; the rows
     * service cleans every one of them again against what this reader is offered.
     *
     * Which queue the address is read for is the listing's kind (applications::kind_of()), the scope
     * queue::listing_scope() resolved for the reader, never a request parameter; no listing is a method's
     * queue. The site-wide queue reads its own facets (WIDE_FACETS), each cleaned to its vocabulary
     * here, as the table cleans it ({@see wide_facet()}); its course and category are manage.php's. It also
     * reads which view the address asks for (SHOW), cleaned to METHODS or nothing; whether this reader is
     * offered that view at all is the props' question ({@see offers_methods()}), and the view's own service
     * refuses anybody else. The sort, the direction and the page are then the "By course" view's, under the
     * same names as the list's; its service cleans them against its own vocabulary.
     *
     * The mentee queue (SQ-D13) reads what the mentee table reads and nothing else: its one facet
     * (MENTEE_FACETS), cleaned the same way, the sort, the direction, the page and the page size. Its
     * course, category, method, grouped and SHOW are ignored whatever the address carries: the course and
     * the category never reach $filters (applications::request_filters() reads them where
     * coursefilter::offered() holds), and the rest are not read here.
     *
     * @param string $search The search manage.php read.
     * @param int|null $status The status manage.php read, null for every application.
     * @param array $filters The filters manage.php read, name => value.
     * @param stdClass|null $listing The reader's listing scope, from queue::listing_scope(); null for a method's queue.
     * @return array scope, search, filters (each: name, value), sort, direction, page, perpage, show (METHODS or '').
     */
    public static function initial(string $search, ?int $status, array $filters, ?stdClass $listing = null): array {
        global $CFG;

        // ENROL_APPLY_USER_WAIT lives in the plugin's lib.php, which is not autoloaded.
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        $scope = match ($status) {
            ENROL_USER_SUSPENDED => 'pending',
            ENROL_APPLY_USER_WAIT => 'deferred',
            default => 'all',
        };

        $kind = $listing === null ? 'method' : applications::kind_of($listing);
        $facets = match ($kind) {
            'site' => self::WIDE_FACETS,
            'mentees' => self::MENTEE_FACETS,
            default => self::FACETS,
        };
        foreach ($facets as $name) {
            $value = trim(optional_param($name, '', PARAM_ALPHANUM));
            if ($kind !== 'method') {
                $value = self::wide_facet($name, $value);
            }
            if ($value !== '') {
                $filters[$name] = $value;
            }
        }
        $list = [];
        foreach ($filters as $name => $value) {
            $list[] = ['name' => (string) $name, 'value' => (string) $value];
        }

        $perpage = optional_param('perpage', queueengine::PERPAGE, PARAM_INT);

        return [
            'scope' => $scope,
            'search' => $search,
            'filters' => $list,
            // A column key such as answer:s_city; the engine keeps only a sortable key of the reader's catalogue.
            'sort' => trim(optional_param('sort', '', PARAM_NOTAGS)),
            'direction' => optional_param('dir', '', PARAM_ALPHA) === 'desc' ? 'desc' : 'asc',
            'page' => max(0, optional_param('page', 0, PARAM_INT)),
            'perpage' => in_array($perpage, queueengine::PERPAGES, true) ? $perpage : queueengine::PERPAGE,
            'show' => $kind === 'site' && optional_param(self::SHOW, '', PARAM_ALPHA) === self::METHODS ? self::METHODS : '',
        ];
    }

    /**
     * One facet of the address of a queue that spans courses, cleaned to the value the table would apply.
     *
     * The same vocabularies as the table's set_filterset(): a waited threshold of
     * applications::WAITED_DAYS, a method that is an apply instance (coursefilter::clean_method()), a
     * grouped value of applications::GROUPED. Whether this reader is offered the grouped filter at all is
     * the rows service's question, which drops it again for anybody else.
     *
     * @param string $name One of WIDE_FACETS or MENTEE_FACETS.
     * @param string $value What the address carries, alphanumeric.
     * @return string The value, '' for none.
     */
    protected static function wide_facet(string $name, string $value): string {
        return match ($name) {
            'waited' => in_array((int) $value, applications::WAITED_DAYS, true) ? (string) (int) $value : '',
            'method' => (string) (coursefilter::clean_method((int) $value) ?? ''),
            'grouped' => in_array($value, applications::GROUPED, true) ? $value : '',
            default => '',
        };
    }

    /**
     * The component's props: configuration and strings, never a row.
     *
     * @return array The props; see js/esm/src/queue/types.ts, ViewProps.
     */
    public function props(): array {
        global $CFG;

        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        $enrolid = (int) ($this->instance->id ?? 0);

        /* "Open the report" only for a reader the report admits (CQ-D11), as the review page's
           earlier applications are shown: the capability in the method's course. The site-wide and mentee
           queues have none: the report belongs to a course. */
        $reporturl = '';
        if ($this->instance !== null) {
            $context = context_course::instance((int) $this->instance->courseid);
            if (has_capability(groupscope::REPORT, $context)) {
                $reporturl = report_view::url($enrolid)->out(false);
            }
        }

        return [
            'mount' => self::MOUNT,
            'enrolid' => $enrolid,
            // Which queue the listing is (method, site, mentees); the rows answer says it again.
            'kind' => $this->table->scope_kind(),
            'initial' => $this->initial,
            'perpage' => queueengine::PERPAGE,
            'perpages' => queueengine::PERPAGES,
            'searchmin' => self::SEARCH_MIN,
            'pageurl' => (new moodle_url('/enrol/apply/manage.php', $enrolid ? ['id' => $enrolid] : []))->out(false),
            'statuses' => ['pending' => ENROL_USER_SUSPENDED, 'deferred' => ENROL_APPLY_USER_WAIT],
            'reporturl' => $reporturl,
            'decision' => $this->decision(),
            // The "By course" view (SQ-D9): its label and its table's strings, for a site-wide reader alone.
            'methods' => $this->offers_methods() ? self::methods_labels() : null,
            'labels' => self::labels(),
        ];
    }

    /**
     * Whether this queue offers the "By course" view: the site-wide queue's, and so a site-wide reader's.
     *
     * Never a method's queue, which is one method already, and never a mentor's: the view would tell them how
     * many applications a method holds beyond their mentees' and its capacity, and its service refuses them
     * ({@see queueengine::methods()}). Read from the listing the table was built for, never from the request.
     *
     * @return bool True on the site-wide queue.
     */
    public function offers_methods(): bool {
        return $this->table->scope_kind() === 'site';
    }

    /**
     * The "By course" view's own strings, by the name the client reads them under, in the plain spelling.
     *
     * What the queue's labels do not already carry, and the two the view words its own way over them (the
     * caption and the no-match title); the counts, the deferred heading, the status words and the wait's
     * wording are the queue's.
     *
     * @return array Name => string.
     */
    public static function methods_labels(): array {
        return [
            'label' => get_string('queueviewbycourse', 'enrol_apply'),
            'capacityhidden' => get_string('queueviewcapacityhidden', 'enrol_apply'),
            'caption' => get_string('queueviewmethodscaption', 'enrol_apply'),
            'coursemethod' => get_string('queuecoursemethod', 'enrol_apply'),
            // The grid's frame is named by what it holds, as the caption names the table.
            'grid' => get_string('queueviewmethodscaption', 'enrol_apply'),
            'nomatch' => get_string('queueviewmethodsnomatch', 'enrol_apply'),
            'oldestwait' => get_string('queueviewoldestwait', 'enrol_apply'),
            'openqueue' => get_string('queueviewopenqueue', 'enrol_apply'),
            'queue' => get_string('queueviewqueue', 'enrol_apply'),
            // The count and the placeholder, as the method form's places row words it.
            'takennolimit' => get_string('placestakennolimit', 'enrol_apply'),
        ];
    }

    /**
     * What the decision sheet may offer this reader, null for a reader who may not decide here.
     *
     * Read from the table form's own context ({@see \enrol_apply_renderer::queue_decision_controls()},
     * the same call manage_form() makes), never derived again here: which groups and roles a decider
     * may choose (enrol/apply:choosedecision in the course, the separate-groups narrowing, the roles
     * they may assign), what an approval gives a decider who may not choose, the labels and help of the
     * message and the note. The three decisions are the table's, in its order
     * ({@see \enrol_apply_renderer::queue_actions()}). The places notice is the one the table page
     * shows above the queue, read from the same capacity calls.
     *
     * Every name goes out in the plain spelling, because the client draws it as text: the context's
     * role names are escaped for a triple stash and are decoded once here. Nothing here authorises:
     * the posted choice is allowlisted again by confirm_enrolment(), row by row, whatever the sheet
     * offered.
     *
     * On the site-wide and mentee queues the table form's call is made with no instance, as manage_form()
     * makes it there: no chooser and no summary (SQ-D10, each approval uses its own method's role and
     * groups), and no places notice, which belongs to one method (each row of the site-wide queue says
     * "Full" instead; the mentee queue says neither, as its table does not).
     *
     * @return array|null sesskey, actions, message, note, groups, roles, summary, placesfull; null for a viewer.
     */
    protected function decision(): ?array {
        global $PAGE;

        $renderer = $PAGE->get_renderer('enrol_apply');
        $controls = $renderer->queue_decision_controls($this->table, $this->instance);
        if (!$controls) {
            return null;
        }

        $plain = static fn(string $escaped): string => htmlspecialchars_decode($escaped, ENT_QUOTES | ENT_HTML5);

        $actions = [];
        foreach ($renderer->queue_actions() as $action) {
            $actions[] = ['value' => $action['value'], 'label' => $action['label'], 'classes' => $action['classes']];
        }

        $groups = null;
        if ($controls['hasgroups']) {
            $options = [];
            foreach ($controls['groups'] as $group) {
                // Plain already: the table's template double stashes the group names.
                $options[] = [
                    'id' => (int) $group['id'],
                    'name' => (string) $group['name'],
                    'selected' => (bool) $group['selected'],
                ];
            }
            $groups = ['label' => $controls['grouplabel'], 'help' => $controls['grouphelp'], 'options' => $options];
        }

        $roles = null;
        if ($controls['hasroles']) {
            $options = [];
            foreach ($controls['roles'] as $role) {
                $options[] = ['id' => (int) $role['id'], 'name' => $plain((string) $role['name'])];
            }
            $roles = [
                'label' => $controls['rolelabel'],
                'help' => $controls['rolehelp'],
                'default' => $controls['roledefault'],
                'options' => $options,
            ];
        }

        $summary = null;
        if ($controls['hassummary']) {
            $summary = [
                'groupslabel' => $controls['summarygroupslabel'],
                'groups' => $controls['summarygroups'],
                'rolelabel' => $controls['summaryrolelabel'],
                'role' => $plain((string) $controls['summaryrole']),
                'help' => $controls['summaryhelp'],
                'note' => $controls['hassummarykeynote'] ? $controls['summarykeynote'] : '',
            ];
        }

        $placesfull = '';
        if ($this->instance !== null && capacity::places_full($this->instance)) {
            $placesfull = get_string('placesfull', 'enrol_apply', capacity::places($this->instance));
        }

        return [
            'sesskey' => sesskey(),
            'actions' => $actions,
            'message' => ['label' => $controls['messagelabel'], 'help' => $controls['messagehelp']],
            'note' => ['label' => $controls['notelabel'], 'help' => $controls['notehelp']],
            'groups' => $groups,
            'roles' => $roles,
            'summary' => $summary,
            'placesfull' => $placesfull,
        ];
    }

    /**
     * The strings the component draws, by the name it reads them under, in the plain spelling.
     *
     * A literal per string, so tools that check string ids find every one of them. Strings that take a
     * value arrive with their placeholder, which the client fills (js/esm/src/datareport/str.ts).
     *
     * @return array Name => string.
     */
    public static function labels(): array {
        return [
            'all' => get_string('queuestatusall', 'enrol_apply'),
            'andmore' => get_string('queuesheetandmore', 'enrol_apply'),
            'allgroupings' => get_string('reportviewallgroupings', 'enrol_apply'),
            'always' => get_string('reportviewalways', 'enrol_apply'),
            'any' => get_string('reportviewany', 'enrol_apply'),
            'applicant' => get_string('reportapplicant', 'enrol_apply'),
            'appliedfrom' => get_string('queuefilterfrom', 'enrol_apply'),
            'appliedto' => get_string('queuefilterto', 'enrol_apply'),
            'awaiting' => get_string('queueawaiting', 'enrol_apply'),
            'back' => get_string('back'),
            'caption' => get_string('queueviewcaption', 'enrol_apply'),
            'cards' => get_string('reportviewcards', 'enrol_apply'),
            'clearall' => get_string('reportviewclearall', 'enrol_apply'),
            'clearfilters' => get_string('reportviewclearfilters', 'enrol_apply'),
            'cleartext' => get_string('reportviewcleartext', 'enrol_apply'),
            'close' => get_string('closebuttontitle'),
            'closed' => get_string('queueapplicationsclosed', 'enrol_apply'),
            'closes' => get_string('queueviewcloses', 'enrol_apply'),
            'columns' => get_string('reportviewcolumns', 'enrol_apply'),
            'coretable' => get_string('reportviewtableview', 'enrol_apply'),
            'count' => get_string('reportviewcount', 'enrol_apply'),
            'count_one' => get_string('reportviewcount_one', 'enrol_apply'),
            'courses' => get_string('queueviewcourses', 'enrol_apply'),
            'defaultcolumns' => get_string('reportviewdefaultcolumns', 'enrol_apply'),
            'deferred' => get_string('submissionstatuswaiting', 'enrol_apply'),
            'deferredshare' => get_string('queueviewdeferredshare', 'enrol_apply'),
            'dock' => get_string('queueviewdock', 'enrol_apply'),
            'dockmethods' => get_string('queueviewdockmethods', 'enrol_apply'),
            'empty' => get_string('queueviewempty', 'enrol_apply'),
            'filterchip' => get_string('reportviewfilterchip', 'enrol_apply'),
            'filters' => get_string('reportviewfilters', 'enrol_apply'),
            'filterson' => get_string('reportviewfilterson', 'enrol_apply'),
            'grantsdeciding' => get_string('queuegrantsdecidingrole', 'enrol_apply'),
            'grid' => get_string('reportviewgrid', 'enrol_apply'),
            'grouping' => get_string('reportviewgrouping', 'enrol_apply'),
            'held' => get_string('reviewapplicants', 'enrol_apply'),
            'late' => get_string('reportviewlate', 'enrol_apply'),
            'less' => get_string('reportviewless', 'enrol_apply'),
            'loaderror' => get_string('reportviewloaderror', 'enrol_apply'),
            'loading' => get_string('reportviewloading', 'enrol_apply'),
            'loadsummary' => get_string('queueviewloadsummary', 'enrol_apply'),
            'methodnote' => get_string('queueonthismethod', 'enrol_apply'),
            'methodfull' => get_string('queueviewmethodfull', 'enrol_apply'),
            'methodfullwarn' => get_string('queueviewmethodfullwarn', 'enrol_apply'),
            'methods' => get_string('queueviewmethods', 'enrol_apply'),
            'methods_one' => get_string('queueviewmethods_one', 'enrol_apply'),
            'methodsfull' => get_string('queueviewmethodsfull', 'enrol_apply'),
            'methodsfullsub' => get_string('queueviewmethodsfullsub', 'enrol_apply'),
            'more' => get_string('reportviewmore', 'enrol_apply'),
            'morefilters' => get_string('reportviewtableview', 'enrol_apply'),
            'morefiltershelp' => get_string('queueviewmorefiltersnote', 'enrol_apply'),
            'moregroups' => get_string('reportviewmoregroups', 'enrol_apply'),
            'moregroupsgrouping' => get_string('reportviewmoregroupsgrouping', 'enrol_apply'),
            'narrowed' => get_string('reportviewnarrowed', 'enrol_apply'),
            'next' => get_string('reportviewnext', 'enrol_apply'),
            'nocategorymatch' => get_string('queueviewnocategorymatch', 'enrol_apply'),
            'nocoursematch' => get_string('queueviewnocoursematch', 'enrol_apply'),
            'nogroupmatch' => get_string('reportviewnogroupmatch', 'enrol_apply'),
            'nomatch' => get_string('reportviewnomatch', 'enrol_apply'),
            'nomatchhelp' => get_string('reportviewnomatch_help', 'enrol_apply'),
            'notingroup' => get_string('notingroup'),
            'ofmany' => get_string('reviewofmany', 'enrol_apply'),
            'oldest' => get_string('reportviewoldest', 'enrol_apply'),
            'omittedcategories' => get_string('queueviewomittedcategories', 'enrol_apply'),
            'omittedcourses' => get_string('queueviewomittedcourses', 'enrol_apply'),
            'open' => get_string('queueapplicationsopen', 'enrol_apply'),
            'openmethod' => get_string('queueviewopenmethod', 'enrol_apply'),
            'openmethodline' => get_string('queueviewopenmethodline', 'enrol_apply'),
            'openreport' => get_string('queueviewopenreport', 'enrol_apply'),
            'pageof' => get_string('reportviewpageof', 'enrol_apply'),
            'pages' => get_string('reportviewpages', 'enrol_apply'),
            'pending' => get_string('submissionstatuspending', 'enrol_apply'),
            'perpage' => get_string('reportviewperpage', 'enrol_apply'),
            'placestaken' => get_string('queueplacestaken', 'enrol_apply'),
            'presetanswers' => get_string('reportviewpresetanswers', 'enrol_apply'),
            'preseteverything' => get_string('queueviewpreseteverything', 'enrol_apply'),
            'presetglance' => get_string('queueviewpresetglance', 'enrol_apply'),
            'presets' => get_string('reportviewpresets', 'enrol_apply'),
            'previous' => get_string('reportviewprevious', 'enrol_apply'),
            'range' => get_string('reportviewrange', 'enrol_apply'),
            'removefilter' => get_string('reportviewremovefilter', 'enrol_apply'),
            'resetwidths' => get_string('reportviewresetwidths', 'enrol_apply'),
            'resize' => get_string('reportviewresize', 'enrol_apply'),
            'retry' => get_string('reportviewretry', 'enrol_apply'),
            'review' => get_string('queuereview', 'enrol_apply'),
            'reviewapplicant' => get_string('queuereviewapplicant', 'enrol_apply'),
            'scope' => get_string('reportviewscope', 'enrol_apply'),
            'scrollhint' => get_string('reportviewscrollhint', 'enrol_apply'),
            'search' => get_string('reportviewsearch', 'enrol_apply'),
            'searchcategories' => get_string('queueviewsearchcategories', 'enrol_apply'),
            'searchchip' => get_string('reportviewsearchchip', 'enrol_apply'),
            'searchcourses' => get_string('queueviewsearchcourses', 'enrol_apply'),
            'searchgroups' => get_string('reportviewsearchgroups', 'enrol_apply'),
            'selectapplicant' => get_string('selectapplicant', 'enrol_apply'),
            'selected' => get_string('queueselectedonpage', 'enrol_apply'),
            'selectpage' => get_string('queueviewselectpage', 'enrol_apply'),
            'sheetcancel' => get_string('queuesheetcancel', 'enrol_apply'),
            'sheetcancelcommit' => get_string('queuesheetcancelcommit', 'enrol_apply'),
            'sheetcanceldanger' => get_string('queuesheetcanceldanger', 'enrol_apply'),
            'sheetcancelone' => get_string('queuesheetcancelone', 'enrol_apply'),
            'sheetconfirm' => get_string('queuesheetconfirm', 'enrol_apply'),
            'sheetconfirmcommit' => get_string('queuesheetconfirmcommit', 'enrol_apply'),
            'sheetconfirmone' => get_string('queuesheetconfirmone', 'enrol_apply'),
            'sheetmessage' => get_string('queuesheetmessage', 'enrol_apply'),
            'sheetwait' => get_string('queuesheetwait', 'enrol_apply'),
            'sheetwaitcommit' => get_string('queuesheetwaitcommit', 'enrol_apply'),
            'sheetwaitline' => get_string('queuesheetwaitline', 'enrol_apply'),
            'sheetwaitone' => get_string('queuesheetwaitone', 'enrol_apply'),
            'spans' => get_string('queuesheetspans', 'enrol_apply'),
            'show' => get_string('reportviewshow', 'enrol_apply'),
            'showcount' => get_string('reportviewshowcount', 'enrol_apply'),
            'status' => get_string('queuestatus', 'enrol_apply'),
            'table' => get_string('reportviewtable', 'enrol_apply'),
            'view' => get_string('reportviewview', 'enrol_apply'),
            'viewauto' => get_string('reportviewviewauto', 'enrol_apply'),
            'viewautohint' => get_string('reportviewviewautohint', 'enrol_apply'),
            'viewonly' => get_string('queueviewonly', 'enrol_apply'),
            'waitdays' => get_string('waitdays', 'enrol_apply'),
            'waitdayshours' => get_string('waitdayshours', 'enrol_apply'),
            'waithours' => get_string('waithours', 'enrol_apply'),
            'waitminutes' => get_string('waitminutes', 'enrol_apply'),
        ];
    }

    /**
     * Export the shell.
     *
     * @param renderer_base $output The renderer.
     * @return array props (JSON), tableurl, noscript (today's queue for this request).
     */
    public function export_for_template(renderer_base $output): array {
        global $PAGE;

        return [
            'props' => json_encode($this->props(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE),
            'tableurl' => self::url($this->manageurl, false)->out(false),
            'noscript' => $PAGE->get_renderer('enrol_apply')->manage_form($this->table, $this->manageurl, $this->instance),
        ];
    }
}
