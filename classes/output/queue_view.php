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
use enrol_apply\local\groupscope;
use enrol_apply\local\queueengine;
use enrol_apply\table\applications;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * The approval queue of one method as a data report, read only (stage 3 of the queue as a report).
 *
 * The shell manage.php renders for `?id=<method>&view=grid`. The page carries configuration and
 * strings only; every row and every number arrives through the plugin's web services
 * (enrol_apply_queue_rows, enrol_apply_queue_summary), which read the queue's own table
 * ({@see queueengine}). So nothing here may put an application into the props: the component is
 * mounted by core's react_autoinit from a data attribute, and whatever the props hold is in the page
 * for anyone who reads its source.
 *
 * The filters, the scope, the search, the sort and the page live in the page's address (CQ-D6), under
 * the names manage.php reads for its table (`status`, `search`, `group`, the identity tokens,
 * `appliedfrom`, `appliedto`) and, for what the table has no control for, `named`, `waited`,
 * `appliedbefore`, `sort`, `dir`, `page` and `perpage`. {@see initial()} reads them for the first
 * paint; the client writes them back as the reader changes them. Only the columns and their widths
 * are the reader's preference (enrol_apply_queue_grid).
 *
 * Today's server-rendered queue goes into the shell's noscript, and the "Table view" link renders
 * it whole.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_view implements renderable, templatable {
    /** @var string The view parameter that asks for this view instead of today's table. */
    public const GRID = 'grid';

    /** @var string The view the bundle mounts for these props (js/esm/src/views.tsx). */
    public const MOUNT = 'queue';

    /** @var int The shortest search the client sends; the table itself searches any length. */
    public const SEARCH_MIN = 1;

    /** @var string[] The facets the queue's own controls do not send, read from the address here. */
    public const FACETS = ['named', 'waited', 'appliedbefore'];

    /** @var stdClass The apply method whose queue this is. */
    protected stdClass $instance;

    /** @var applications Today's queue for the same request, for the noscript. */
    protected applications $table;

    /** @var moodle_url The queue's address with its filters, without the view parameter. */
    protected moodle_url $manageurl;

    /** @var array What the address asks for; see {@see initial()}. */
    protected array $initial;

    /**
     * Build the shell.
     *
     * @param stdClass $instance The apply method.
     * @param applications $table Today's queue as manage.php built it for this request.
     * @param moodle_url $manageurl The queue's address with its filters, without the view parameter.
     * @param array $initial What the address asks for, from {@see initial()}.
     */
    public function __construct(stdClass $instance, applications $table, moodle_url $manageurl, array $initial) {
        $this->instance = $instance;
        $this->table = $table;
        $this->manageurl = $manageurl;
        $this->initial = $initial;
    }

    /**
     * Whether manage.php renders this view for a listing request, rather than today's table.
     *
     * Only the queue of one method (CQ-D3: the site-wide and mentee queues keep the table), and only
     * when the address asks for it: the view cannot decide yet, so it must not replace the page that
     * can. Stage 4, which brings the decisions, inverts the second condition to `view=table`, making
     * this view the method queue's default. Asked on the listing path only: manage.php serves
     * `userenrol=` before it reads `id`.
     *
     * @param int $enrolid The request's id parameter, 0 for the site-wide or mentee queue.
     * @param string $view The request's view parameter.
     * @return bool True for the method queue with view=grid.
     */
    public static function wanted(int $enrolid, string $view): bool {
        return $enrolid > 0 && $view === self::GRID;
    }

    /**
     * The queue's address in one view or the other, with the filters it carries.
     *
     * @param moodle_url $manageurl The queue's address with its filters.
     * @param bool $grid Whether the address asks for this view.
     * @return moodle_url The address.
     */
    public static function url(moodle_url $manageurl, bool $grid): moodle_url {
        $url = new moodle_url($manageurl);
        if ($grid) {
            $url->param('view', self::GRID);
        } else {
            $url->remove_params('view');
        }

        return $url;
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
     * @param string $search The search manage.php read.
     * @param int|null $status The status manage.php read, null for every application.
     * @param array $filters The filters manage.php read, name => value.
     * @return array scope, search, filters (each: name, value), sort, direction, page, perpage.
     */
    public static function initial(string $search, ?int $status, array $filters): array {
        global $CFG;

        // ENROL_APPLY_USER_WAIT lives in the plugin's lib.php, which is not autoloaded.
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        $scope = match ($status) {
            ENROL_USER_SUSPENDED => 'pending',
            ENROL_APPLY_USER_WAIT => 'deferred',
            default => 'all',
        };

        foreach (self::FACETS as $name) {
            $value = trim(optional_param($name, '', PARAM_ALPHANUM));
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
        ];
    }

    /**
     * The component's props: configuration and strings, never a row.
     *
     * @return array The props; see js/esm/src/queue/types.ts, ViewProps.
     */
    public function props(): array {
        global $CFG;

        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        $enrolid = (int) $this->instance->id;
        $context = context_course::instance((int) $this->instance->courseid);

        /* "Open the report" only for a reader the report admits (CQ-D11), as the review page's
           earlier applications are shown: the capability in the method's course. */
        $reporturl = '';
        if (has_capability(groupscope::REPORT, $context)) {
            $reporturl = report_view::url($enrolid)->out(false);
        }

        return [
            'mount' => self::MOUNT,
            'enrolid' => $enrolid,
            'initial' => $this->initial,
            'perpage' => queueengine::PERPAGE,
            'perpages' => queueengine::PERPAGES,
            'searchmin' => self::SEARCH_MIN,
            'pageurl' => (new moodle_url('/enrol/apply/manage.php', ['id' => $enrolid]))->out(false),
            'statuses' => ['pending' => ENROL_USER_SUSPENDED, 'deferred' => ENROL_APPLY_USER_WAIT],
            'reporturl' => $reporturl,
            'labels' => self::labels(),
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
            'allgroupings' => get_string('reportviewallgroupings', 'enrol_apply'),
            'always' => get_string('reportviewalways', 'enrol_apply'),
            'any' => get_string('reportviewany', 'enrol_apply'),
            'applicant' => get_string('reportapplicant', 'enrol_apply'),
            'appliedfrom' => get_string('queuefilterfrom', 'enrol_apply'),
            'appliedto' => get_string('queuefilterto', 'enrol_apply'),
            'awaiting' => get_string('queueawaiting', 'enrol_apply'),
            'caption' => get_string('queueviewcaption', 'enrol_apply'),
            'cards' => get_string('reportviewcards', 'enrol_apply'),
            'clearall' => get_string('reportviewclearall', 'enrol_apply'),
            'clearfilters' => get_string('reportviewclearfilters', 'enrol_apply'),
            'cleartext' => get_string('reportviewcleartext', 'enrol_apply'),
            'closed' => get_string('queueapplicationsclosed', 'enrol_apply'),
            'closes' => get_string('queueviewcloses', 'enrol_apply'),
            'columns' => get_string('reportviewcolumns', 'enrol_apply'),
            'coretable' => get_string('reportviewtableview', 'enrol_apply'),
            'count' => get_string('reportviewcount', 'enrol_apply'),
            'count_one' => get_string('reportviewcount_one', 'enrol_apply'),
            'defaultcolumns' => get_string('reportviewdefaultcolumns', 'enrol_apply'),
            'deferred' => get_string('submissionstatuswaiting', 'enrol_apply'),
            'empty' => get_string('queueviewempty', 'enrol_apply'),
            'filterchip' => get_string('reportviewfilterchip', 'enrol_apply'),
            'filters' => get_string('reportviewfilters', 'enrol_apply'),
            'filterson' => get_string('reportviewfilterson', 'enrol_apply'),
            'grid' => get_string('reportviewgrid', 'enrol_apply'),
            'grouping' => get_string('reportviewgrouping', 'enrol_apply'),
            'held' => get_string('reviewapplicants', 'enrol_apply'),
            'late' => get_string('reportviewlate', 'enrol_apply'),
            'less' => get_string('reportviewless', 'enrol_apply'),
            'loaderror' => get_string('reportviewloaderror', 'enrol_apply'),
            'loading' => get_string('reportviewloading', 'enrol_apply'),
            'loadsummary' => get_string('queueviewloadsummary', 'enrol_apply'),
            'methodnote' => get_string('queueonthismethod', 'enrol_apply'),
            'more' => get_string('reportviewmore', 'enrol_apply'),
            'morefilters' => get_string('reportviewtableview', 'enrol_apply'),
            'morefiltershelp' => get_string('queueviewmorefiltersnote', 'enrol_apply'),
            'moregroups' => get_string('reportviewmoregroups', 'enrol_apply'),
            'moregroupsgrouping' => get_string('reportviewmoregroupsgrouping', 'enrol_apply'),
            'narrowed' => get_string('reportviewnarrowed', 'enrol_apply'),
            'next' => get_string('reportviewnext', 'enrol_apply'),
            'nogroupmatch' => get_string('reportviewnogroupmatch', 'enrol_apply'),
            'nomatch' => get_string('reportviewnomatch', 'enrol_apply'),
            'nomatchhelp' => get_string('reportviewnomatch_help', 'enrol_apply'),
            'notingroup' => get_string('notingroup'),
            'ofmany' => get_string('reviewofmany', 'enrol_apply'),
            'oldest' => get_string('reportviewoldest', 'enrol_apply'),
            'open' => get_string('queueapplicationsopen', 'enrol_apply'),
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
            'searchchip' => get_string('reportviewsearchchip', 'enrol_apply'),
            'searchgroups' => get_string('reportviewsearchgroups', 'enrol_apply'),
            'show' => get_string('reportviewshow', 'enrol_apply'),
            'showcount' => get_string('reportviewshowcount', 'enrol_apply'),
            'status' => get_string('queuestatus', 'enrol_apply'),
            'table' => get_string('reportviewtable', 'enrol_apply'),
            'view' => get_string('reportviewview', 'enrol_apply'),
            'viewauto' => get_string('reportviewviewauto', 'enrol_apply'),
            'viewautohint' => get_string('reportviewviewautohint', 'enrol_apply'),
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
