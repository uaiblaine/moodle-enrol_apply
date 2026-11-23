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
use enrol_apply\local\queuepreference;

/**
 * Store the reader's grid on the approval queue (enrol_apply_set_queue_preference, CQ-D7).
 *
 * The columns they chose and the widths they dragged, in one user preference for the whole site
 * ({@see queuepreference}). The rows intersect it with the catalogue they build for the reader, so
 * a stored key can never add a column the reader may not see. Taken from a queue the reader may
 * open, so the write needs the same standing as the read; the method only judges that standing,
 * the preference is not the method's.
 *
 * No event: a reader's display preference, like core's own table preferences, which log nothing.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_queue_preference extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'enrolid' => new external_value(PARAM_INT, 'The apply method whose queue the reader is on'),
            'usedefault' => new external_value(PARAM_BOOL, 'Show the default columns, forgetting the choice', VALUE_DEFAULT, false),
            'columns' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Column key'),
                'The chosen columns',
                VALUE_DEFAULT,
                []
            ),
            'widths' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_RAW, 'Column key'),
                    'width' => new external_value(PARAM_INT, 'Width in pixels'),
                ]),
                'Every width the reader set; empty for the default widths',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Store the grid.
     *
     * @param int $enrolid The apply method.
     * @param bool $usedefault Whether to forget the column choice.
     * @param array $columns The chosen keys.
     * @param array $widths Each: key, width.
     * @return array What was stored; see execute_returns().
     * @throws \moodle_exception When no apply method has this id (0 included), or for a guest.
     * @throws \required_capability_exception When the reader may not open the queue.
     */
    public static function execute(int $enrolid, bool $usedefault = false, array $columns = [], array $widths = []): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'enrolid' => $enrolid,
            'usedefault' => $usedefault,
            'columns' => $columns,
            'widths' => $widths,
        ]);

        [, $context] = queueengine::locate($params['enrolid']);
        self::validate_context($context);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        queueengine::require_listing($params['enrolid']);

        $sizes = [];
        foreach ($params['widths'] as $width) {
            $sizes[$width['key']] = $width['width'];
        }

        return queuepreference::export(queuepreference::write($params['usedefault'] ? null : $params['columns'], $sizes));
    }

    /**
     * What comes back: the grid as stored, cleaned.
     *
     * @return external_single_structure The structure.
     */
    public static function execute_returns(): external_single_structure {
        return preference_structure::get();
    }
}
