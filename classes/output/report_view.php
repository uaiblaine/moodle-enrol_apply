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
use core_reportbuilder\system_report;
use enrol_apply\local\queue;
use enrol_apply\local\reportengine;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * The course report's React view (stage R4): the shell report.php renders by default.
 *
 * The page carries configuration and strings only; every row and every number arrives through the
 * plugin's web services (enrol_apply_report_rows, enrol_apply_report_summary), which read the same
 * system report ({@see reportengine}). So nothing here may put a record into the props: the
 * component is mounted by core's react_autoinit from a data attribute, and whatever the props hold
 * is in the page for anyone who reads its source, whatever the component then draws.
 *
 * Core's own rendering of the report goes into the shell's noscript, without its filter form (a
 * dynamic form, which does nothing without JavaScript, and whose script would look for a form the
 * browser never builds from a noscript), and "Table view" (report.php?view=table) renders it whole.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_view implements renderable, templatable {
    /** @var string The view parameter that asks for core's table instead of the React view. */
    public const TABLE = 'table';

    /** @var stdClass The apply method the report was opened from. */
    protected stdClass $instance;

    /** @var system_report The course report, as report.php built it. */
    protected system_report $report;

    /**
     * Build the shell.
     *
     * @param stdClass $instance The apply method.
     * @param system_report $report The course report for that method (course_applications::for_method()).
     */
    public function __construct(stdClass $instance, system_report $report) {
        $this->instance = $instance;
        $this->report = $report;
    }

    /**
     * Whether report.php renders the React shell for a request, rather than core's table.
     *
     * @param string $view The request's view parameter: 'table' for core's table, anything else for the default.
     * @return bool True unless core's table was asked for.
     */
    public static function wanted(string $view): bool {
        return $view !== self::TABLE;
    }

    /**
     * The report page's url.
     *
     * @param int $enrolid The apply method.
     * @param bool $table Whether the url asks for core's table.
     * @return moodle_url The url.
     */
    public static function url(int $enrolid, bool $table = false): moodle_url {
        $params = ['id' => $enrolid];
        if ($table) {
            $params['view'] = self::TABLE;
        }

        return new moodle_url('/enrol/apply/report.php', $params);
    }

    /**
     * The component's props: configuration and strings, never a row.
     *
     * @return array The props; see js/esm/src/report/types.ts, ViewProps.
     */
    public function props(): array {
        global $DB;

        $context = $this->report->get_context();
        $enrolid = (int) $this->instance->id;

        $download = '';
        $formats = [];
        if ($this->report->can_be_downloaded()) {
            // What core's own table offers (system_report_table::download_buttons()); the client adds
            // the report's parameters and the format, as core's selector posts them.
            $download = (new moodle_url('/reportbuilder/download.php', [
                'id' => $this->report->get_report_persistent()->get('id'),
            ]))->out(false);
            foreach (\core_plugin_manager::instance()->get_plugins_of_type('dataformat') as $format) {
                if ($format->is_enabled()) {
                    $formats[] = ['value' => $format->name, 'label' => get_string('dataformat', $format->component)];
                }
            }
        }

        /* The band's "Still open" leads to the queue only for a reader the queue would admit (AR-D18):
           its own capability rule, for this reader, from the method; a viewer of the report alone
           gets the number and no link that would refuse them. */
        $retention = reportengine::retention();

        $queueurl = '';
        if ($context instanceof context_course && queue::listing_scope($enrolid)->allowed) {
            $queueurl = (new moodle_url('/enrol/apply/manage.php', ['id' => $enrolid]))->out(false);
        }

        return [
            'enrolid' => $enrolid,
            'reportid' => (int) $this->report->get_report_persistent()->get('id'),
            'measure' => reportengine::measure((string) ($this->report->get_parameters()['measure'] ?? 'first')),
            'perpage' => reportengine::PERPAGES[0],
            'perpages' => reportengine::PERPAGES,
            'searchmin' => reportengine::SEARCH_MIN,
            'downloadurl' => $download,
            'formats' => $formats,
            'queueurl' => $queueurl,
            'pendingstatus' => ENROL_USER_SUSPENDED,
            'tableurl' => self::url($enrolid, true)->out(false),
            'retention' => $retention['text'] ?? '',
            'retentionurl' => $retention['url'] ?? '',
            'methods' => $DB->count_records('enrol', ['courseid' => $context->instanceid, 'enrol' => 'apply']),
            'labels' => self::labels(),
        ];
    }

    /**
     * The strings the component draws, by the name it reads them under, in the plain spelling.
     *
     * A literal per string, so tools that check string ids find every one of them. Strings that take a
     * value arrive with their placeholder, which the client fills (js/esm/src/report/str.ts).
     *
     * @return array Name => string.
     */
    public static function labels(): array {
        return [
            'all' => get_string('reportviewall', 'enrol_apply'),
            'allgroups' => get_string('reportviewallgroups', 'enrol_apply'),
            'allgroupings' => get_string('reportviewallgroupings', 'enrol_apply'),
            'always' => get_string('reportviewalways', 'enrol_apply'),
            'any' => get_string('reportviewany', 'enrol_apply'),
            'applicant' => get_string('reportapplicant', 'enrol_apply'),
            'awaiting' => get_string('reportviewawaiting', 'enrol_apply'),
            'breakdown' => get_string('reportviewbreakdown', 'enrol_apply'),
            'bucket' => get_string('reportviewbucket', 'enrol_apply'),
            'bucketfirst' => get_string('reportviewbucketfirst', 'enrol_apply'),
            'bucketlast' => get_string('reportviewbucketlast', 'enrol_apply'),
            'bygroup' => get_string('reportviewbygroup', 'enrol_apply'),
            'bymethod' => get_string('reportviewbymethod', 'enrol_apply'),
            'caption' => get_string('report:course_applications', 'enrol_apply'),
            'cards' => get_string('reportviewcards', 'enrol_apply'),
            'clearall' => get_string('reportviewclearall', 'enrol_apply'),
            'clearfilters' => get_string('reportviewclearfilters', 'enrol_apply'),
            'cleartext' => get_string('reportviewcleartext', 'enrol_apply'),
            'columns' => get_string('reportviewcolumns', 'enrol_apply'),
            'coretable' => get_string('reportviewcoretable', 'enrol_apply'),
            'count' => get_string('reportviewcount', 'enrol_apply'),
            'count_one' => get_string('reportviewcount_one', 'enrol_apply'),
            'decided' => get_string('reportviewdecided', 'enrol_apply'),
            'decidedby' => get_string('submissiondecidedby', 'enrol_apply'),
            'decisions' => get_string('reportviewdecisions', 'enrol_apply'),
            'defaultcolumns' => get_string('reportviewdefaultcolumns', 'enrol_apply'),
            'distfirst' => get_string('reportviewdistfirst', 'enrol_apply'),
            'distresolution' => get_string('reportviewdistresolution', 'enrol_apply'),
            'download' => get_string('reportviewdownload', 'enrol_apply'),
            'empty' => get_string('reportviewempty', 'enrol_apply'),
            'filterchip' => get_string('reportviewfilterchip', 'enrol_apply'),
            'filters' => get_string('reportviewfilters', 'enrol_apply'),
            'filterson' => get_string('reportviewfilterson', 'enrol_apply'),
            'grid' => get_string('reportviewgrid', 'enrol_apply'),
            'grouping' => get_string('reportviewgrouping', 'enrol_apply'),
            'hide' => get_string('reportviewhide', 'enrol_apply'),
            'late' => get_string('reportviewlate', 'enrol_apply'),
            'less' => get_string('reportviewless', 'enrol_apply'),
            'loadband' => get_string('reportviewloadband', 'enrol_apply'),
            'loaderror' => get_string('reportviewloaderror', 'enrol_apply'),
            'loading' => get_string('reportviewloading', 'enrol_apply'),
            'measure' => get_string('reportviewmeasure', 'enrol_apply'),
            'measurefirst' => get_string('reportviewmeasurefirst', 'enrol_apply'),
            'measureresolution' => get_string('reportviewmeasureresolution', 'enrol_apply'),
            'medianby' => get_string('reportviewmedianby', 'enrol_apply'),
            'medianfirst' => get_string('reportviewmedianfirst', 'enrol_apply'),
            'medianresolution' => get_string('reportviewmedianresolution', 'enrol_apply'),
            'mediansub' => get_string('reportviewmediansub', 'enrol_apply'),
            'more' => get_string('reportviewmore', 'enrol_apply'),
            'morefilters' => get_string('reportviewmorefilters', 'enrol_apply'),
            'morefiltershelp' => get_string('reportviewmorefilters_help', 'enrol_apply'),
            'moregroups' => get_string('reportviewmoregroups', 'enrol_apply'),
            'moregroupsgrouping' => get_string('reportviewmoregroupsgrouping', 'enrol_apply'),
            'narrowed' => get_string('reportviewnarrowed', 'enrol_apply'),
            'next' => get_string('reportviewnext', 'enrol_apply'),
            'nodecidermatch' => get_string('reportviewnodecidermatch', 'enrol_apply'),
            'nogroupmatch' => get_string('reportviewnogroupmatch', 'enrol_apply'),
            'nomatch' => get_string('reportviewnomatch', 'enrol_apply'),
            'nomatchhelp' => get_string('reportviewnomatch_help', 'enrol_apply'),
            'nothingopen' => get_string('reportviewnothingopen', 'enrol_apply'),
            'oldest' => get_string('reportviewoldest', 'enrol_apply'),
            'open' => get_string('reportviewopen', 'enrol_apply'),
            'openqueue' => get_string('reportviewopenqueue', 'enrol_apply'),
            'p90' => get_string('reportviewp90', 'enrol_apply'),
            'p90sub' => get_string('reportviewp90sub', 'enrol_apply'),
            'pageof' => get_string('reportviewpageof', 'enrol_apply'),
            'pages' => get_string('reportviewpages', 'enrol_apply'),
            'perpage' => get_string('reportviewperpage', 'enrol_apply'),
            'previous' => get_string('reportviewprevious', 'enrol_apply'),
            'range' => get_string('reportviewrange', 'enrol_apply'),
            'removefilter' => get_string('reportviewremovefilter', 'enrol_apply'),
            'presetanswers' => get_string('reportviewpresetanswers', 'enrol_apply'),
            'presetdecisions' => get_string('reportviewpresetdecisions', 'enrol_apply'),
            'presetgroups' => get_string('reportviewpresetgroups', 'enrol_apply'),
            'presets' => get_string('reportviewpresets', 'enrol_apply'),
            'resetwidths' => get_string('reportviewresetwidths', 'enrol_apply'),
            'resize' => get_string('reportviewresize', 'enrol_apply'),
            'retentionchange' => get_string('reportretention_change', 'enrol_apply'),
            'retry' => get_string('reportviewretry', 'enrol_apply'),
            'review' => get_string('queuereview', 'enrol_apply'),
            'scope' => get_string('reportviewscope', 'enrol_apply'),
            'scrollhint' => get_string('reportviewscrollhint', 'enrol_apply'),
            'search' => get_string('reportviewsearch', 'enrol_apply'),
            'searchchip' => get_string('reportviewsearchchip', 'enrol_apply'),
            'searchgroup' => get_string('reportviewsearchgroup', 'enrol_apply'),
            'searchdeciders' => get_string('reportviewsearchdeciders', 'enrol_apply'),
            'searchgroups' => get_string('reportviewsearchgroups', 'enrol_apply'),
            'show' => get_string('reportviewshow', 'enrol_apply'),
            'showcount' => get_string('reportviewshowcount', 'enrol_apply'),
            'table' => get_string('reportviewtable', 'enrol_apply'),
            'topgroups' => get_string('reportviewtopgroups', 'enrol_apply'),
            'turnaround' => get_string('reportviewturnaround', 'enrol_apply'),
            'view' => get_string('reportviewview', 'enrol_apply'),
            'viewauto' => get_string('reportviewviewauto', 'enrol_apply'),
            'viewautohint' => get_string('reportviewviewautohint', 'enrol_apply'),
            'waitdays' => get_string('waitdays', 'enrol_apply'),
            'waitedmore' => get_string('reportviewwaitedmore', 'enrol_apply'),
            'waitdayshours' => get_string('waitdayshours', 'enrol_apply'),
            'waithours' => get_string('waithours', 'enrol_apply'),
            'waitminutes' => get_string('waitminutes', 'enrol_apply'),
            'withoutperson' => get_string('reportviewwithoutperson', 'enrol_apply'),
            'withoutpersonsub' => get_string('reportviewwithoutpersonsub', 'enrol_apply'),
        ];
    }

    /**
     * Export the shell.
     *
     * @param renderer_base $output The renderer.
     * @return array props (JSON), tableurl, noscript (the retention notice and core's rendering of the report).
     */
    public function export_for_template(renderer_base $output): array {
        global $PAGE;

        /* Without JavaScript core's filter form is a dynamic form that cannot submit, and its script,
           which the template registers on the page whatever the browser does with a noscript, would
           look up a form a scripting browser never builds. */
        $this->report->set_filter_form_default(false);

        return [
            'props' => json_encode($this->props(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE),
            'tableurl' => self::url((int) $this->instance->id, true)->out(false),
            'noscript' => $PAGE->get_renderer('enrol_apply')->retention_notice() . $this->report->output(),
        ];
    }
}
