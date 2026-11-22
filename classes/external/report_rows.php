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

namespace enrol_apply\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use enrol_apply\local\reportengine;

/**
 * A page of the course report's rows, read headlessly (enrol_apply_report_rows, stage R3).
 *
 * The course comes from the enrol instance; the rows are the system report's own, through the
 * table core builds for it, so the base conditions (the course, the separate-groups narrowing),
 * the reader's stored filters and the masking hold here as on the page
 * ({@see reportengine::rows()}). The reader's filters are core's store: a client sets them with
 * core_reportbuilder_set_filters and reads the rows again.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_rows extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'enrolid' => new external_value(PARAM_INT, 'The apply method the report was opened from'),
            'measure' => new external_value(PARAM_ALPHA, 'What the wait measures: first or resolution', VALUE_DEFAULT, 'first'),
            'scope' => new external_value(PARAM_ALPHA, 'Which rows: all, awaiting or decided', VALUE_DEFAULT, 'all'),
            'search' => new external_value(PARAM_TEXT, 'Part of the applicant\'s name, two characters or more', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'The page, from 0', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Rows per page: 30, 50 or 100', VALUE_DEFAULT, 30),
            'sort' => new external_value(PARAM_RAW, 'The column key to sort by, empty for the report\'s sort', VALUE_DEFAULT, ''),
            'direction' => new external_value(PARAM_ALPHA, 'asc or desc', VALUE_DEFAULT, 'desc'),
            'facets' => new external_value(PARAM_BOOL, 'Whether to count the filter panel\'s options', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Read a page of rows.
     *
     * @param int $enrolid The apply method.
     * @param string $measure What the wait measures.
     * @param string $scope Which rows.
     * @param string $search Part of a name.
     * @param int $page The page, from 0.
     * @param int $perpage Rows per page.
     * @param string $sort The column key to sort by.
     * @param string $direction asc or desc.
     * @param bool $facets Whether to count the filter panel's options.
     * @return array The rows; see execute_returns().
     * @throws \moodle_exception When the method does not exist, or for a guest.
     * @throws \required_capability_exception When the reader may not view the report.
     */
    public static function execute(
        int $enrolid,
        string $measure = 'first',
        string $scope = 'all',
        string $search = '',
        int $page = 0,
        int $perpage = 30,
        string $sort = '',
        string $direction = 'desc',
        bool $facets = true
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'enrolid' => $enrolid,
            'measure' => $measure,
            'scope' => $scope,
            'search' => $search,
            'page' => $page,
            'perpage' => $perpage,
            'sort' => $sort,
            'direction' => $direction,
            'facets' => $facets,
        ]);

        [$instance, $context] = reportengine::locate($params['enrolid']);
        self::validate_context($context);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        require_capability('enrol/apply:viewreports', $context);

        return reportengine::rows(reportengine::report($instance, $params['measure']), $params);
    }

    /**
     * What comes back: an allowlist.
     *
     * Titles, labels and names in the plain spelling, which the client renders as text; cells are
     * the report's formatted HTML, already escaped by its formatters.
     *
     * @return external_single_structure The structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'measure' => new external_value(PARAM_ALPHA, 'What the wait measures'),
            'scope' => new external_value(PARAM_ALPHA, 'Which rows'),
            'search' => new external_value(PARAM_RAW, 'The search applied, empty for none'),
            'sort' => new external_value(PARAM_RAW, 'The column key sorted by, empty for the report\'s own sort'),
            'direction' => new external_value(PARAM_ALPHA, 'asc, desc, or empty'),
            'page' => new external_value(PARAM_INT, 'The page returned, from 0'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page'),
            'total' => new external_value(PARAM_INT, 'Rows across every page'),
            'narrowed' => new external_value(PARAM_BOOL, 'Whether separate groups narrow this reader'),
            'notingroup' => new external_value(PARAM_BOOL, 'Whether the reader is narrowed to no group'),
            'columns' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_RAW, 'Column key'),
                'title' => new external_value(PARAM_RAW, 'Title, plain'),
                'sortable' => new external_value(PARAM_BOOL, 'Whether it sorts'),
                'default' => new external_value(PARAM_BOOL, 'Whether the default set has it'),
                'active' => new external_value(PARAM_BOOL, 'Whether the report shows it'),
                'fixed' => new external_value(PARAM_BOOL, 'Whether it is always shown'),
            ])),
            'presets' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_ALPHA, 'The set\'s name'),
                'columns' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'Column key, as the preference stores it')
                ),
                'active' => new external_value(PARAM_BOOL, 'Whether the report shows this set'),
            ])),
            'rows' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Record id'),
                'userid' => new external_value(PARAM_INT, 'Applicant'),
                'userenrolmentid' => new external_value(PARAM_INT, 'User enrolment, 0 for none'),
                'status' => new external_value(PARAM_INT, 'The record\'s status'),
                'timecreated' => new external_value(PARAM_INT, 'Submitted'),
                'timedecided' => new external_value(PARAM_INT, 'Last decision, 0 for none'),
                'timefirstdecided' => new external_value(PARAM_INT, 'First decision, 0 for none or not recorded'),
                'decidedbyperson' => new external_value(PARAM_BOOL, 'Whether a person decided it'),
                'open' => new external_value(PARAM_BOOL, 'Whether it awaits what is measured'),
                'wait' => new external_value(PARAM_INT, 'Seconds waited for what is measured, to now while open'),
                'reviewurl' => new external_value(PARAM_URL, 'The review page, empty when the reader gets no Review action'),
                'cells' => new external_multiple_structure(new external_single_structure([
                    'key' => new external_value(PARAM_RAW, 'Column key'),
                    'html' => new external_value(PARAM_RAW, 'Formatted cell'),
                ])),
            ])),
            'counted' => new external_value(PARAM_BOOL, 'Whether the filters\' options carry their counts'),
            'filters' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_RAW, 'Filter unique identifier'),
                'title' => new external_value(PARAM_RAW, 'Title, plain'),
                'type' => new external_value(PARAM_ALPHANUMEXT, 'Filter class name'),
                'facet' => new external_value(PARAM_ALPHA, 'The panel\'s facet: choice, waited, decider, or empty for none'),
                'options' => new external_multiple_structure(new external_single_structure([
                    'value' => new external_value(PARAM_RAW, 'Value'),
                    'label' => new external_value(PARAM_RAW, 'Label, plain'),
                    'count' => new external_value(PARAM_INT, 'Rows with this option under every other filter, -1 when not counted'),
                ])),
                'applied' => new external_value(PARAM_BOOL, 'Whether the reader\'s stored values narrow the rows'),
                'values' => new external_value(PARAM_RAW, 'The reader\'s stored values for it, JSON'),
                'groupings' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Grouping id'),
                    'name' => new external_value(PARAM_RAW, 'Name, plain'),
                    'groups' => new external_multiple_structure(new external_value(PARAM_INT, 'An offered group it holds')),
                ])),
            ])),
            'preference' => preference_structure::get(),
        ]);
    }
}
