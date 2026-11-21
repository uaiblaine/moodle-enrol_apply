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

namespace enrol_apply\reportbuilder\local\systemreports;

use core_reportbuilder\system_report;
use core_reportbuilder\table\system_report_table;

/**
 * Core's system report table, read without rendering it.
 *
 * The same table core_table_get_dynamic_table_content and core's download build, so the rows carry
 * the report's base conditions (the course, the separate-groups narrowing), the reader's stored
 * filters and the formatters: the headless rows ({@see \enrol_apply\local\reportengine}) cannot
 * widen what the page shows. Two additions only: the report instance the table built, whose
 * aliases its SQL uses (each instance generates its own), and a way to append a condition to that
 * SQL, which can only narrow it.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class headless_table extends system_report_table {
    /**
     * The report this table was built from.
     *
     * @return system_report The report.
     */
    public function get_report(): system_report {
        return $this->report;
    }

    /**
     * Append a condition to the table's WHERE clause, with AND.
     *
     * @param string $sql The condition, its parameters named.
     * @param array $params Its parameters, under names of their own.
     * @return void
     */
    public function narrow(string $sql, array $params): void {
        $this->sql->where = "({$this->sql->where}) AND ({$sql})";
        $this->sql->params = array_merge($this->sql->params, $params);
    }
}
