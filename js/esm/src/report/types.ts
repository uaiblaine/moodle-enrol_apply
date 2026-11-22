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

/**
 * The shapes the course report's view reads: its props and the three web services' answers.
 *
 * The answers are the allowlists of classes/external/report_rows.php, report_summary.php and
 * preference_structure.php, field for field; keep them in step.
 *
 * @module     enrol_apply/report/types
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export type Measure = 'first' | 'resolution';
export type Scope = 'all' | 'awaiting' | 'decided';
export type ViewMode = 'auto' | 'table' | 'cards';

/** One download format core offers (a dataformat plugin). */
export type Format = {value: string, label: string};

/** The props enrol_apply\output\report_view::props() writes into the page: configuration and strings, never a row. */
export type ViewProps = {
    enrolid: number,
    reportid: number,
    measure: Measure,
    perpage: number,
    perpages: number[],
    searchmin: number,
    downloadurl: string,
    formats: Format[],
    queueurl: string,
    pendingstatus: number,
    tableurl: string,
    retention: string,
    retentionurl: string,
    methods: number,
    labels: Record<string, string>,
};

/** A column the report shows or offers. */
export type Column = {
    key: string,
    title: string,
    sortable: boolean,
    default: boolean,
    active: boolean,
    fixed: boolean,
};

/** One cell of a row: the report's own formatted HTML. */
export type Cell = {key: string, html: string};

/** One row: the record's raw facts and its cells. */
export type Row = {
    id: number,
    userid: number,
    userenrolmentid: number,
    status: number,
    timecreated: number,
    timedecided: number,
    timefirstdecided: number,
    decidedbyperson: boolean,
    open: boolean,
    wait: number,
    reviewurl: string,
    cells: Cell[],
};

/** One option of a filter, with the rows it would list under every other filter; -1 when not counted. */
export type FilterOption = {value: string, label: string, count: number};

/** A grouping of the groups a group filter offers. */
export type Grouping = {id: number, name: string, groups: number[]};

/** The panel's facet that draws a filter; '' for one set in core's table view. */
export type Facet = '' | 'choice' | 'waited' | 'decider';

/** One of the report's filters, as the rows answer describes it. */
export type Filter = {
    name: string,
    title: string,
    type: string,
    facet: Facet,
    options: FilterOption[],
    applied: boolean,
    values: string,
    groupings: Grouping[],
};

/** One of the Columns menu's sets. */
export type Preset = {name: string, columns: string[], active: boolean};

/** The reader's grid. */
export type Preference = {
    usedefault: boolean,
    columns: string[],
    widths: {key: string, width: number}[],
};

/** The enrol_apply_report_rows answer. */
export type RowsAnswer = {
    measure: Measure,
    scope: Scope,
    search: string,
    sort: string,
    direction: '' | 'asc' | 'desc',
    page: number,
    perpage: number,
    total: number,
    narrowed: boolean,
    notingroup: boolean,
    columns: Column[],
    presets: Preset[],
    rows: Row[],
    counted: boolean,
    filters: Filter[],
    preference: Preference,
};

/** One row of a breakdown. */
export type Breakdown = {id: number, name: string, median: number | null, count: number};

/** One bucket of the wait histogram. */
export type Bucket = {fromdays: number, todays: number | null, count: number};

/** The enrol_apply_report_summary answer. */
export type SummaryAnswer = {
    measure: Measure,
    scope: Scope,
    search: string,
    compact: boolean,
    total: number,
    decided: number,
    open: number,
    withoutperson: number,
    late: number,
    oldest: number | null,
    median: number | null,
    p90: number | null,
    buckets: Bucket[],
    bymethod: Breakdown[],
    bygroup: Breakdown[],
};

/** What a rows request asks for. */
export type RowsQuery = {
    measure: Measure,
    scope: Scope,
    search: string,
    page: number,
    perpage: number,
    sort: string,
    direction: 'asc' | 'desc',
};
