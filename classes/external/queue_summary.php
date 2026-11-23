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
use core_external\external_single_structure;
use core_external\external_value;
use enrol_apply\local\queueengine;

/**
 * The tiles above an approval queue (enrol_apply_queue_summary, CQ-D10, SQ-D7).
 *
 * One method's: places taken against the places, applications held against the limit, the reader's
 * applications awaiting a decision with the oldest wait and how many waited long, and whether the
 * method is open. With enrolid 0, the queue that spans courses: the reader's applications awaiting,
 * the oldest wait, the late and the deferred ones, and for a site-wide reader the courses and methods
 * holding an application and the methods at their limit ({@see queueengine::summary()}). Behind the
 * same door as the rows: the method (or the system context for 0), the context and the guest are
 * checked here, a reader the queue refuses is refused by the engine's summary.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_summary extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'enrolid' => new external_value(PARAM_INT, 'The apply method whose queue is read, 0 for the queue that spans courses'),
        ]);
    }

    /**
     * Read the tiles' figures.
     *
     * @param int $enrolid The apply method.
     * @return array The figures; see execute_returns().
     * @throws \moodle_exception When a non-zero id names no apply method, or for a guest.
     * @throws \required_capability_exception When the reader may not open the queue.
     */
    public static function execute(int $enrolid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['enrolid' => $enrolid]);

        [$instance, $context] = queueengine::locate($params['enrolid']);
        self::validate_context($context);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        return queueengine::summary($instance);
    }

    /**
     * What comes back: an allowlist.
     *
     * @return external_single_structure The structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'places' => new external_value(PARAM_INT, 'The method\'s places, 0 for no limit'),
            'placestaken' => new external_value(PARAM_INT, 'Places taken by approved applicants, 0 without places'),
            'applicantlimit' => new external_value(PARAM_INT, 'Applications the method accepts, 0 for no limit'),
            'applicants' => new external_value(PARAM_INT, 'Applications it holds, 0 without a limit'),
            'awaiting' => new external_value(PARAM_INT, 'The reader\'s applications awaiting a decision'),
            'deferred' => new external_value(PARAM_INT, 'Of those, the deferred ones'),
            'oldestwait' => new external_value(PARAM_INT, 'Seconds the oldest of them has waited, 0 for none'),
            'late' => new external_value(PARAM_INT, 'Of those, the ones waiting longer than latedays'),
            'latedays' => new external_value(PARAM_INT, 'The days after which an application counts as late'),
            'open' => new external_value(PARAM_BOOL, 'Whether the method accepts applications now'),
            'closes' => new external_value(PARAM_INT, 'When applications close, 0 for no date'),
            'narrowed' => new external_value(PARAM_BOOL, 'Whether separate groups narrow this reader'),
            'notingroup' => new external_value(PARAM_BOOL, 'Whether the reader is narrowed to no group'),
            'sitefigures' => new external_value(PARAM_BOOL, 'Whether the three site figures below are given: '
                . 'a site-wide reader of the queue that spans courses only'),
            'courses' => new external_value(PARAM_INT, 'Courses holding an application in the reader\'s scope, 0 where not given'),
            'methods' => new external_value(PARAM_INT, 'Apply methods holding one, 0 where not given'),
            'methodsfull' => new external_value(PARAM_INT, 'Of those, the ones with every place taken or their applicant '
                . 'limit reached, 0 where not given'),
        ]);
    }
}
