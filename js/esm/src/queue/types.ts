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
 * The shapes the approval queue's view reads: its props and the three web services' answers.
 *
 * The answers are the allowlists of classes/external/queue_rows.php, queue_summary.php and
 * preference_structure.php, field for field, and the props are enrol_apply\output\queue_view::props();
 * keep them in step.
 *
 * @module     enrol_apply/queue/types
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Column, ViewMode} from '../datareport/types';

export type {Column, ViewMode};

/** Which applications: every one awaiting a decision, the pending ones, the deferred ones. */
export type Scope = 'all' | 'pending' | 'deferred';

/** One filter as the address and the rows service carry it. */
export type NamedValue = {name: string, value: string};

/** What the view asks the rows service for, and what the page's address says. */
export type Query = {
    scope: Scope,
    search: string,
    /** Filter name => value, as the address names them; group may hold "all", every group explicitly. */
    filters: Record<string, string>,
    sort: string,
    direction: 'asc' | 'desc',
    page: number,
    perpage: number,
};

/**
 * The props enrol_apply\output\queue_view::props() writes into the page: configuration and strings, never
 * a row.
 */
export type ViewProps = {
    mount: 'queue',
    enrolid: number,
    initial: {
        scope: Scope,
        search: string,
        filters: NamedValue[],
        sort: string,
        direction: 'asc' | 'desc',
        page: number,
        perpage: number,
    },
    perpage: number,
    perpages: number[],
    searchmin: number,
    /** The queue's address with the method alone (manage.php?id=). */
    pageurl: string,
    /** The enrolment status the address's `status` carries for each scope. */
    statuses: {pending: number, deferred: number},
    /** The course report, '' for a reader who may not open it. */
    reporturl: string,
    /** What the decision sheet may offer, from the table form's own context; null for a reader who may not decide. */
    decision: DecisionProps | null,
    labels: Record<string, string>,
};

/** One of the queue's three decisions: its formaction, and the table's label and button style for it. */
export type Action = {value: Decision, label: string, classes: string};

/** A decision, as manage.php's formaction names it. */
export type Decision = 'confirm' | 'wait' | 'cancel';

/** A field's label and its help, as the table's decision controls word them. */
export type Wording = {label: string, help: string};

/**
 * What the sheet may offer, all plain text: queue_view::decision(), which reads the table form's
 * decision controls (renderer::queue_decision_controls()). The groups and the role are offered only
 * where that form offers them (enrol/apply:choosedecision); a decider who may not choose reads the
 * summary of what an approval gives instead.
 */
export type DecisionProps = {
    sesskey: string,
    actions: Action[],
    message: Wording,
    note: Wording,
    groups: (Wording & {options: {id: number, name: string, selected: boolean}[]}) | null,
    roles: (Wording & {default: string, options: {id: number, name: string}[]}) | null,
    summary: {groupslabel: string, groups: string, rolelabel: string, role: string, help: string, note: string} | null,
    /** The table page's notice that every place on the method is taken, '' while some are left. */
    placesfull: string,
};

/** A label under the applicant's name: deferred, key (its outcome), appliedbefore or own. */
export type Tag = {kind: string, text: string, title: string};

/** One identity field of the applicant, plain. */
export type Identity = {name: string, title: string, value: string};

/** The applicant's cell: the name and the identity values plain, the picture as the server's HTML. */
export type Applicant = {
    fullname: string,
    profileurl: string,
    picture: string,
    identity: Identity[],
    tags: Tag[],
};

/** One cell: the server's formatted HTML, already escaped. */
export type Cell = {key: string, html: string};

/** One row as the rows service returns it. */
export type ServerRow = {
    userenrolmentid: number,
    userid: number,
    selectable: boolean,
    reviewurl: string,
    status: number,
    applied: number,
    wait: number,
    applicant: Applicant,
    cells: Cell[],
};

/** One row as the shared parts read it: the service's, keyed by its user enrolment. */
export type Row = ServerRow & {id: number};

/** One of the Columns menu's sets. */
export type Preset = {name: string, columns: string[], active: boolean};

/** The reader's grid. */
export type Preference = {
    usedefault: boolean,
    columns: string[],
    widths: {key: string, width: number}[],
};

/** One facet the rows answer counts. */
export type Facet = {
    name: string,
    title: string,
    applied: string,
    options: {value: string, label: string, count: number}[],
};

/** One identity-field filter the reader is offered. */
export type FieldFilter = {
    name: string,
    title: string,
    control: 'text' | 'select',
    options: {value: string, label: string}[],
};

/** The enrol_apply_queue_rows answer. */
export type RowsAnswer = {
    scope: Scope,
    search: string,
    sort: string,
    direction: 'asc' | 'desc',
    page: number,
    perpage: number,
    total: number,
    counts: {all: number, pending: number, deferred: number},
    candecide: boolean,
    narrowed: boolean,
    notingroup: boolean,
    groupdefault: string,
    columns: Column[],
    presets: Preset[],
    rows: ServerRow[],
    facets: Facet[],
    fieldfilters: FieldFilter[],
    filters: NamedValue[],
    preference: Preference,
};

/** The enrol_apply_queue_summary answer. */
export type SummaryAnswer = {
    places: number,
    placestaken: number,
    applicantlimit: number,
    applicants: number,
    awaiting: number,
    deferred: number,
    oldestwait: number,
    late: number,
    latedays: number,
    open: boolean,
    closes: number,
    narrowed: boolean,
    notingroup: boolean,
};
