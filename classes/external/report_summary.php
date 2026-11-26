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
use enrol_apply\local\groupscope;
use enrol_apply\local\reportengine;

/**
 * The turnaround summary of the course report (enrol_apply_report_summary, stage R3).
 *
 * Computed over the same table the rows are read from, with the same scope and search, so it
 * counts exactly the rows the reader's table lists ({@see reportengine::summary()}). A reader the
 * separate-groups rule narrows gets the compact band (AR-D17).
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_summary extends external_api {
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
            'search' => new external_value(PARAM_TEXT, 'Part of the applicant\'s name, two characters at least', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Compute the summary.
     *
     * @param int $enrolid The apply method.
     * @param string $measure What the wait measures.
     * @param string $scope Which rows.
     * @param string $search Part of a name.
     * @return array The summary; see execute_returns().
     * @throws \moodle_exception When the method does not exist, or for a guest.
     * @throws \required_capability_exception When the reader may not view the report.
     */
    public static function execute(int $enrolid, string $measure = 'first', string $scope = 'all', string $search = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'enrolid' => $enrolid,
            'measure' => $measure,
            'scope' => $scope,
            'search' => $search,
        ]);

        [$instance, $context] = reportengine::locate($params['enrolid']);
        self::validate_context($context);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        groupscope::require_in_course('enrol/apply:viewreports', $context);

        return reportengine::summary(reportengine::report($instance, $params['measure']), $params);
    }

    /**
     * What comes back: an allowlist. Waits in seconds; null where there is nothing to measure or
     * the compact band leaves it out.
     *
     * @return external_single_structure The structure.
     */
    public static function execute_returns(): external_single_structure {
        $breakdown = new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Method or group id; 0 no group, -1 the groups the reader may not see'),
            'name' => new external_value(PARAM_RAW, 'Name, plain'),
            'median' => new external_value(PARAM_INT, 'Median wait', VALUE_OPTIONAL, null, NULL_ALLOWED),
            'count' => new external_value(PARAM_INT, 'Decisions measured'),
        ]));

        return new external_single_structure([
            'measure' => new external_value(PARAM_ALPHA, 'What the wait measures'),
            'scope' => new external_value(PARAM_ALPHA, 'Which rows'),
            'search' => new external_value(PARAM_RAW, 'The search applied, empty for none'),
            'compact' => new external_value(PARAM_BOOL, 'Whether this is the narrowed reader\'s compact band'),
            'total' => new external_value(PARAM_INT, 'Rows counted: decided + open + withoutperson'),
            'decided' => new external_value(PARAM_INT, 'Decisions by a person, the ones measured'),
            'open' => new external_value(PARAM_INT, 'Rows awaiting what is measured'),
            'withoutperson' => new external_value(PARAM_INT, 'Decisions without a person: key admissions, no decider'),
            'late' => new external_value(PARAM_INT, 'Open rows older than seven days'),
            'oldest' => new external_value(PARAM_INT, 'Age of the oldest open row', VALUE_OPTIONAL, null, NULL_ALLOWED),
            'median' => new external_value(PARAM_INT, 'Median wait', VALUE_OPTIONAL, null, NULL_ALLOWED),
            'p90' => new external_value(PARAM_INT, '90th percentile wait', VALUE_OPTIONAL, null, NULL_ALLOWED),
            'buckets' => new external_multiple_structure(new external_single_structure([
                'fromdays' => new external_value(PARAM_INT, 'From, in days'),
                'todays' => new external_value(
                    PARAM_INT,
                    'Up to, in days, exclusive; null for none',
                    VALUE_OPTIONAL,
                    null,
                    NULL_ALLOWED
                ),
                'count' => new external_value(PARAM_INT, 'Decisions in it'),
            ])),
            'bymethod' => $breakdown,
            'bygroup' => $breakdown,
        ]);
    }
}
