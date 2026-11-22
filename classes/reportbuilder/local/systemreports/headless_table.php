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

use core_reportbuilder\local\helpers\database;
use core_reportbuilder\system_report;
use core_reportbuilder\table\system_report_table;

/**
 * Core's system report table, read without rendering it.
 *
 * The same table core_table_get_dynamic_table_content and core's download build, so the rows carry
 * the report's base conditions (the course, the separate-groups narrowing), the reader's stored
 * filters and the formatters: the headless rows ({@see \enrol_apply\local\reportengine}) cannot
 * widen what the page shows. Three additions only: the report instance the table built, whose
 * aliases its SQL uses (each instance generates its own), a way to append a condition to that
 * SQL, which can only narrow it, and a page read whose cost does not grow with the page number.
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

    /**
     * Read one page in two statements: the page's ids and the total, then those rows whole.
     *
     * Core's own read ({@see \core_reportbuilder\table\base_report_table::query_db()}) selects every
     * column under `COUNT(1) OVER()` with LIMIT and OFFSET, so the database computes each column of
     * every row before the page, and this report's group and answer columns are subqueries per row:
     * a page cost more the later it came. The first statement here selects only the main table's id
     * over the same SQL, which leaves the database nothing to compute but the sort keys; the second
     * reads the page's rows whole, with the table's own conditions as well as the ids, and puts them
     * in the first statement's order.
     *
     * The ids are ordered by {@see page_order()}. The count follows core's
     * {@see \moodle_database::get_counted_recordset_sql()}: a window count where the database offers
     * one, a count of its own first where it does not.
     *
     * @param int $pagesize Rows per page.
     * @param bool $useinitialsbar Unused, as in core's report table.
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = true): void {
        global $DB;

        $this->pagesize($pagesize, 0);

        $tablesql = $this->get_table_sql(false);
        $countalias = database::generate_alias();
        $count = $DB->is_count_window_function_supported()
            ? 'COUNT(1) OVER()'
            : (string) $DB->count_records_sql("SELECT COUNT(1) FROM ({$tablesql}) results", $this->sql->params);
        $ids = [];
        $total = 0;
        $records = $DB->get_records_sql(
            "SELECT results.id, {$count} AS {$countalias} FROM ({$tablesql}) results ORDER BY {$this->page_order()}",
            $this->sql->params,
            (int) $this->get_page_start(),
            (int) $this->get_page_size()
        );
        foreach ($records as $record) {
            $ids[] = (int) $record->id;
            $total = (int) $record->{$countalias};
        }
        $this->pagesize($pagesize, $total);

        $rows = [];
        if ($ids) {
            [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, database::generate_param_name() . '_');
            $alias = $this->report->get_main_table_alias();
            /* With a limit, as core's own read has: PostgreSQL's driver reads a recordset with no limit
               through a cursor, three statements where this is one. */
            $recordset = $DB->get_recordset_sql(
                "SELECT {$this->sql->fields} FROM {$this->sql->from}
                  WHERE ({$this->sql->where}) AND {$alias}.id {$insql} {$this->groupbysql}",
                $this->sql->params + $inparams,
                0,
                count($ids)
            );
            foreach ($recordset as $record) {
                $rows[(int) $record->id] = $record;
            }
            $recordset->close();
        }
        // A row gone between the two statements is left out, as a later read would leave it.
        $this->rawdata = array_values(array_filter(array_map(fn(int $id) => $rows[$id] ?? null, $ids)));
    }

    /**
     * The ORDER BY of the page's ids: the reader's sort, then the main table's id.
     *
     * A unique last key, as the queue's listing has (CLAUDE.md, "Both listings order by a unique
     * key"): each page is a statement of its own, and rows tied on the sort, which the report's dates
     * are when one script writes many applications in a second, could otherwise appear on two pages
     * or on none.
     *
     * @return string The ORDER BY list, over the aliases of the table's SQL wrapped as `results`.
     */
    public function page_order(): string {
        $sort = $this->get_sql_sort();

        return ($sort !== '' ? "{$sort}, " : '') . 'results.id';
    }
}
