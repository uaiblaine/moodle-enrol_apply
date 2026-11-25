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
 * The shapes the data report's shared parts read (the Design System's data-report.md).
 *
 * Nothing here names a view's rows, scopes or web services: a view (the course report, the approval
 * queue) adapts its own answers to these shapes and keeps its own types beside its own code.
 *
 * @module     enrol_apply/datareport/types
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export type ViewMode = 'auto' | 'table' | 'cards';

/**
 * A view a caller adds to the bar's View menu after the built-in choices; its value is the caller's
 * own (never a ViewMode), icon is a key of the icons' PATHS, and the caller draws its body itself.
 */
export type ExtraView = {value: string, label: string, icon: string};

/** A column a view shows or offers; fixed is always shown and cannot be chosen away. */
export type Column = {
    key: string,
    title: string,
    sortable: boolean,
    default: boolean,
    active: boolean,
    fixed: boolean,
};

/** The least a row carries for the grid and the cards: its id, unique in the page. */
export type RowBase = {id: number};

/** One value of a segmented control (the scope). */
export type Choice = {value: string, label: string};

/** One option of a facet, with the rows it would list under every other filter; -1 when not counted. */
export type FilterOption = {value: string, label: string, count: number};

/** A grouping of the groups a group facet offers: a select above its searched list. */
export type Grouping = {id: number, name: string, groups: number[]};

/**
 * One facet the filter panel draws: a heading and its options, one of which may be chosen.
 *
 * Above eight options the facet is a searched list: `find` is that list's search label (a string with
 * {$a}, the number of options) and the note drawn when nothing matches. A caller's `note` is drawn under
 * the facet's options, in either form. A facet whose options were cut by the server says how many it has
 * in all (`total`), so the search label counts them all and not the ones sent.
 */
export type FacetDefinition = {
    name: string,
    title: string,
    options: FilterOption[],
    groupings: Grouping[],
    find: {label: string, nomatch: string},
    /** A line under the facet's options, such as how many options the server left out; absent for none. */
    note?: string,
    /** Every option the facet has, the ones the server left out included; absent when all were sent. */
    total?: number,
};

/** One chip of what is in force: its text, and the key the removal names. */
export type Chip = {key: string, text: string};

/** One of the Columns menu's sets, the one in force checked. */
export type ColumnSet = {name: string, label: string, active: boolean};

/** One link of the Download menu. */
export type DownloadLink = {key: string, label: string, href: string};

/** A column's width policy: its default and minimum by key, and the widest any column may be dragged. */
export type Sizing = {
    defaultWidth: (key: string) => number,
    minimumWidth: (key: string) => number,
    maximum: number,
};

/**
 * A leading selection over the rows of the page, drawn in the first column's heading and cells and on
 * each card. A view without one passes none, and nothing of it is drawn.
 */
export type Selection<R extends RowBase> = {
    /** The ids of the rows selected. */
    selected: ReadonlySet<number>,
    /** The ids of the rows that cannot be selected: they get no box. */
    locked: ReadonlySet<number>,
    /** The box's accessible name for a row ("Select <name>"). */
    label: (row: R) => string,
    /** The heading box's accessible name. */
    pagelabel: string,
    onSelect: (id: number, on: boolean) => void,
    /** Select or release every selectable row of the page. */
    onSelectPage: (on: boolean) => void,
};
