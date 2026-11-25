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
use enrol_apply\local\queueengine;

/**
 * A page of an approval queue, read headlessly (enrol_apply_queue_rows): one method's, or with
 * enrolid 0 the queue that spans courses this reader is given (the site-wide queue, deciding or read
 * only, or a mentor's).
 *
 * The rows are the queue table's own ({@see queueengine::rows()}): the scope
 * queue::listing_scope() gives the reader, the operator filters as the page's address carries them,
 * the masking of the answers by the course report's rule. The course comes from the method, and for
 * 0 the context is the system's ({@see queueengine::locate()}); nothing the request carries chooses
 * the scope, and `kind` says which one the reader got. A reader the queue refuses is refused by the
 * engine ({@see queueengine::rows()}), after the method, the context and the guest are checked here.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_rows extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'enrolid' => new external_value(PARAM_INT, 'The apply method whose queue is read, 0 for the queue that spans courses'),
            'scope' => new external_value(PARAM_ALPHA, 'Which applications: all, pending or deferred', VALUE_DEFAULT, 'all'),
            'search' => new external_value(PARAM_TEXT, 'Part of a name, an identity field or the comment', VALUE_DEFAULT, ''),
            'filters' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUM, 'Filter name, as the queue page\'s address names it'),
                    'value' => new external_value(PARAM_RAW, 'Its value'),
                ]),
                'The operator\'s filters; one the reader is not offered is ignored',
                VALUE_DEFAULT,
                []
            ),
            'page' => new external_value(PARAM_INT, 'The page, from 0', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Rows per page: 30, 50 or 100', VALUE_DEFAULT, queueengine::PERPAGE),
            'sort' => new external_value(PARAM_RAW, 'A sortable column key, empty for the applied date', VALUE_DEFAULT, ''),
            'direction' => new external_value(PARAM_ALPHA, 'asc or desc', VALUE_DEFAULT, 'asc'),
        ]);
    }

    /**
     * Read a page of the queue.
     *
     * The refusal of a reader who may not open the queue comes from the engine's own check, which
     * runs before anything is read.
     *
     * @param int $enrolid The apply method.
     * @param string $scope Which applications.
     * @param string $search Part of a name.
     * @param array $filters Each: name, value.
     * @param int $page The page, from 0.
     * @param int $perpage Rows per page.
     * @param string $sort A column key.
     * @param string $direction asc or desc.
     * @return array The page; see execute_returns().
     * @throws \moodle_exception When a non-zero id names no apply method, or for a guest.
     * @throws \required_capability_exception When the reader may not open the queue.
     */
    public static function execute(
        int $enrolid,
        string $scope = 'all',
        string $search = '',
        array $filters = [],
        int $page = 0,
        int $perpage = queueengine::PERPAGE,
        string $sort = '',
        string $direction = 'asc'
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'enrolid' => $enrolid,
            'scope' => $scope,
            'search' => $search,
            'filters' => $filters,
            'page' => $page,
            'perpage' => $perpage,
            'sort' => $sort,
            'direction' => $direction,
        ]);

        [, $context] = queueengine::locate($params['enrolid']);
        self::validate_context($context);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        $named = [];
        foreach ($params['filters'] as $filter) {
            $named[$filter['name']] = $filter['value'];
        }

        return queueengine::rows($params['enrolid'], [
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
     * What comes back: an allowlist.
     *
     * Titles, labels, names and values in the plain spelling, which the client renders as text; the
     * cells and the picture are server-formatted HTML, already escaped.
     *
     * @return external_single_structure The structure.
     */
    public static function execute_returns(): external_single_structure {
        $option = new external_single_structure([
            'value' => new external_value(PARAM_RAW, 'Value'),
            'label' => new external_value(PARAM_RAW, 'Label, plain'),
        ]);

        return new external_single_structure([
            'kind' => new external_value(PARAM_ALPHA, 'Which queue the reader got: method, site or mentees'),
            'scope' => new external_value(PARAM_ALPHA, 'Which applications'),
            'search' => new external_value(PARAM_RAW, 'The search applied, empty for none'),
            'sort' => new external_value(PARAM_RAW, 'The column key sorted by'),
            'direction' => new external_value(PARAM_ALPHA, 'asc or desc'),
            'page' => new external_value(PARAM_INT, 'The page returned, from 0'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page'),
            'total' => new external_value(PARAM_INT, 'Applications across every page'),
            'counts' => new external_single_structure([
                'all' => new external_value(PARAM_INT, 'Every application under the other criteria'),
                'pending' => new external_value(PARAM_INT, 'The pending ones'),
                'deferred' => new external_value(PARAM_INT, 'The deferred ones'),
            ]),
            'candecide' => new external_value(PARAM_BOOL, 'Whether the reader may decide from this queue'),
            'narrowed' => new external_value(PARAM_BOOL, 'Whether separate groups narrow this reader'),
            'notingroup' => new external_value(PARAM_BOOL, 'Whether the reader is narrowed to no group'),
            'groupdefault' => new external_value(PARAM_RAW, 'The group filter the page applies when its address names none'),
            'columns' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_RAW, 'Column key'),
                'title' => new external_value(PARAM_RAW, 'Title, plain'),
                'sortable' => new external_value(PARAM_BOOL, 'Whether it sorts'),
                'default' => new external_value(PARAM_BOOL, 'Whether the default set has it'),
                'active' => new external_value(PARAM_BOOL, 'Whether the reader sees it'),
                'fixed' => new external_value(PARAM_BOOL, 'Whether it is always shown'),
            ])),
            'presets' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_ALPHA, 'The set\'s name'),
                'columns' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'Column key, as the preference stores it')
                ),
                'active' => new external_value(PARAM_BOOL, 'Whether the reader sees this set'),
            ])),
            'rows' => new external_multiple_structure(new external_single_structure([
                'userenrolmentid' => new external_value(PARAM_INT, 'User enrolment, the decision form\'s value'),
                'userid' => new external_value(PARAM_INT, 'Applicant'),
                'selectable' => new external_value(PARAM_BOOL, 'Whether the row may be selected for a decision'),
                'reviewurl' => new external_value(PARAM_URL, 'The review page'),
                'status' => new external_value(PARAM_INT, 'The enrolment\'s status'),
                'applied' => new external_value(PARAM_INT, 'Applied'),
                'wait' => new external_value(PARAM_INT, 'Seconds waited, to now'),
                'applicant' => new external_single_structure([
                    'fullname' => new external_value(PARAM_RAW, 'Name, plain'),
                    'profileurl' => new external_value(PARAM_URL, 'The profile, empty where it would not open'),
                    'picture' => new external_value(PARAM_RAW, 'The picture, HTML'),
                    'identity' => new external_multiple_structure(new external_single_structure([
                        'name' => new external_value(PARAM_RAW, 'Identity field'),
                        'title' => new external_value(PARAM_RAW, 'Its name, plain'),
                        'value' => new external_value(PARAM_RAW, 'Value, plain'),
                    ])),
                    'tags' => new external_multiple_structure(new external_single_structure([
                        'kind' => new external_value(PARAM_ALPHA, 'deferred, key, appliedbefore or own'),
                        'text' => new external_value(PARAM_RAW, 'Label, plain'),
                        'title' => new external_value(PARAM_RAW, 'The longer wording, plain, empty for none'),
                    ])),
                ]),
                'cells' => new external_multiple_structure(new external_single_structure([
                    'key' => new external_value(PARAM_RAW, 'Column key'),
                    'html' => new external_value(PARAM_RAW, 'Formatted cell'),
                ])),
                'course' => new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'The course, 0 on a method\'s queue'),
                    'name' => new external_value(PARAM_RAW, 'Its full name, plain; empty on a method\'s queue'),
                    'url' => new external_value(PARAM_URL, 'The course page; empty on a method\'s queue and for a mentor'),
                ]),
                'method' => new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'The apply method, 0 on a method\'s queue'),
                    'name' => new external_value(PARAM_RAW, 'Its name, plain; empty on a method\'s queue'),
                    'deciding' => new external_value(PARAM_BOOL, 'Whether its role decides applications'),
                    'full' => new external_value(PARAM_BOOL, 'Whether every place is taken or its applicant limit reached; '
                        . 'never for a mentor'),
                    'queueurl' => new external_value(PARAM_URL, 'Its queue, for a site-wide reader only; empty otherwise'),
                ]),
            ])),
            'facets' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_ALPHANUM, 'Filter name'),
                'title' => new external_value(PARAM_RAW, 'Title, plain'),
                'applied' => new external_value(PARAM_RAW, 'The value applied, empty for none'),
                'options' => new external_multiple_structure(new external_single_structure([
                    'value' => new external_value(PARAM_RAW, 'Value'),
                    'label' => new external_value(PARAM_RAW, 'Label, plain'),
                    'count' => new external_value(PARAM_INT, 'Applications choosing it lists, under every other criterion'),
                ])),
                'omitted' => new external_value(PARAM_INT, 'Options that list something and were left out by the limit'),
            ])),
            'fieldfilters' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_ALPHANUM, 'Filter name, as the address carries it'),
                'title' => new external_value(PARAM_RAW, 'Title, plain'),
                'control' => new external_value(PARAM_ALPHA, 'text or select'),
                'options' => new external_multiple_structure($option),
            ])),
            'filters' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_ALPHANUM, 'Filter name'),
                'value' => new external_value(PARAM_RAW, 'The value applied, as cleaned'),
            ])),
            'preference' => preference_structure::get(),
        ]);
    }
}
