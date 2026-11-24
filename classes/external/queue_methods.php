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

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use enrol_apply\local\queueengine;

/**
 * The site-wide queue's "By course" view (enrol_apply_queue_methods, SQ-D9): one row per enrolment method
 * of what the list holds under the scope, the search and the filters.
 *
 * It takes no enrolment method: the view exists for the site-wide queue alone, whose context is the
 * system's. The context, the guest and the reader are checked in that order, and the reader by the engine
 * ({@see queueengine::methods()}): who the queue refuses, and a mentor, whose list is their mentees'. It
 * reads and writes nothing else; deciding stays the POST to manage.php.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_methods extends external_api {
    /**
     * The parameters: what the list's rows service takes, but the method.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'scope' => new external_value(PARAM_ALPHA, 'Which applications: all, pending or deferred', VALUE_DEFAULT, 'all'),
            'search' => new external_value(PARAM_TEXT, 'Part of a name, an identity value, the comment', VALUE_DEFAULT, ''),
            'filters' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUM, 'Filter name, as the queue page\'s address names it'),
                    'value' => new external_value(PARAM_RAW, 'Its value'),
                ]),
                'The reader\'s filters; one the reader is not offered is ignored',
                VALUE_DEFAULT,
                []
            ),
            'page' => new external_value(PARAM_INT, 'The page, from 0', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Methods per page: 30, 50 or 100', VALUE_DEFAULT, queueengine::PERPAGE),
            'sort' => new external_value(PARAM_ALPHA, 'awaiting, oldest or course; empty for awaiting', VALUE_DEFAULT, ''),
            'direction' => new external_value(PARAM_ALPHA, 'asc or desc', VALUE_DEFAULT, 'desc'),
        ]);
    }

    /**
     * Read a page of the view.
     *
     * @param string $scope Which applications.
     * @param string $search Part of a name.
     * @param array $filters Each: name, value.
     * @param int $page The page, from 0.
     * @param int $perpage Methods per page.
     * @param string $sort A sort key.
     * @param string $direction asc or desc.
     * @return array The page; see execute_returns().
     * @throws \moodle_exception For a guest.
     * @throws \required_capability_exception When the reader is not a site-wide reader of the queue.
     */
    public static function execute(
        string $scope = 'all',
        string $search = '',
        array $filters = [],
        int $page = 0,
        int $perpage = queueengine::PERPAGE,
        string $sort = '',
        string $direction = 'desc'
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'scope' => $scope,
            'search' => $search,
            'filters' => $filters,
            'page' => $page,
            'perpage' => $perpage,
            'sort' => $sort,
            'direction' => $direction,
        ]);

        self::validate_context(context_system::instance());
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        $named = [];
        foreach ($params['filters'] as $filter) {
            $named[$filter['name']] = $filter['value'];
        }

        return queueengine::methods([
            'scope' => $params['scope'],
            'search' => $params['search'],
            'filters' => $named,
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'sort' => $params['sort'],
            'direction' => $params['direction'],
        ]);
    }

    /**
     * What comes back: an allowlist. Names in the plain spelling, which the client renders as text.
     *
     * @return external_single_structure The structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'scope' => new external_value(PARAM_ALPHA, 'Which applications'),
            'search' => new external_value(PARAM_RAW, 'The search applied, empty for none'),
            'sort' => new external_value(PARAM_ALPHA, 'The key sorted by'),
            'direction' => new external_value(PARAM_ALPHA, 'asc or desc'),
            'page' => new external_value(PARAM_INT, 'The page returned, from 0'),
            'perpage' => new external_value(PARAM_INT, 'Methods per page'),
            'total' => new external_value(PARAM_INT, 'Enrolment methods holding an application the list holds'),
            'rows' => new external_multiple_structure(new external_single_structure([
                'method' => new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'The apply method'),
                    'name' => new external_value(PARAM_RAW, 'Its name, plain'),
                    'deciding' => new external_value(PARAM_BOOL, 'Whether its role decides applications'),
                    'queueurl' => new external_value(PARAM_URL, 'Its queue'),
                ]),
                'course' => new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'The course'),
                    'name' => new external_value(PARAM_RAW, 'Its full name, plain'),
                    'url' => new external_value(PARAM_URL, 'The course page'),
                ]),
                'awaiting' => new external_value(PARAM_INT, 'Its applications the list holds'),
                'deferred' => new external_value(PARAM_INT, 'Of those, the deferred ones'),
                'oldestwait' => new external_value(PARAM_INT, 'Seconds the oldest of them has waited'),
                'places' => new external_value(PARAM_INT, 'The method\'s places, 0 for no limit'),
                'placestaken' => new external_value(PARAM_INT, 'Places taken by approved applicants, 0 without places'),
                'applicantlimit' => new external_value(PARAM_INT, 'Applications the method accepts, 0 for no limit'),
                'applicants' => new external_value(PARAM_INT, 'Applications it holds, 0 without a limit'),
                'placesfull' => new external_value(PARAM_BOOL, 'Whether every place is taken'),
                'limitreached' => new external_value(PARAM_BOOL, 'Whether its applicant limit is reached'),
                'open' => new external_value(PARAM_BOOL, 'Whether the method accepts applications now'),
                'closes' => new external_value(PARAM_INT, 'When applications close, 0 for no date'),
            ])),
        ]);
    }
}
