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

namespace enrol_apply;

use context;
use core_reportbuilder\system_report_factory;
use enrol_apply\reportbuilder\local\formatters\submission as formatter;
use enrol_apply\reportbuilder\local\systemreports\course_applications;

/**
 * The course report with every entity column the report itself no longer shows.
 *
 * The status, the live enrolment, the date decided, the admission route, the three group columns,
 * the snapshot and the decider's name left the course report's default set (R1) and stay in the
 * entity, where the datasource offers them. Their tests read them under the course report's own
 * gate, base condition and separate-groups narrowing, which this subclass inherits whole; it adds
 * the columns after the report's own and nothing else. The snapshot gets the masking a report in a
 * course context must give it, the rule {@see formatter::visible_keys()} decides.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_applications_every_column extends course_applications {
    /** @var array The submission entity's columns this subclass adds back, in the entity's order. */
    public const ADDED = [
        'status',
        'timedecided',
        'enrolment',
        'admissionroute',
        'keygroup',
        'chosengroup',
        'joinedgroups',
        'snapshot',
    ];

    /**
     * The report's own columns, then the entity's others and the decider's name.
     *
     * @return void
     */
    protected function add_report_columns(): void {
        parent::add_report_columns();

        foreach (self::ADDED as $name) {
            // The chosen group's column exists only once its upgrade has run.
            if ($this->get_entity('submission')->get_column($name) !== null) {
                $this->add_column_from_entity("submission:{$name}");
            }
        }
        $this->get_column('submission:snapshot')->set_callback(
            [formatter::class, 'snapshot'],
            formatter::visible_keys($this->get_context())
        );

        $this->add_column_from_entity(self::DECIDER . ':fullname');
    }

    /**
     * This report, identified by the enrolment method as {@see course_applications::for_method()} does.
     *
     * @param context $context Course context the report belongs to.
     * @param int $enrolid Enrol instance the reader opened it from.
     * @param array $parameters Report parameters.
     * @return \core_reportbuilder\system_report The report.
     */
    public static function for_method(context $context, int $enrolid, array $parameters = []): \core_reportbuilder\system_report {
        return system_report_factory::create(self::class, $context, 'enrol_apply', 'method', $enrolid, $parameters);
    }
}
