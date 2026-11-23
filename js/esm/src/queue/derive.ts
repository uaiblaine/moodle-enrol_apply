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
 * What the queue view reads off the newest rows answer, and the selection it builds over the page's rows.
 *
 * Both are plain functions of the answer and the props: the view keeps the state, this keeps the reading,
 * so the component stays small enough to follow.
 *
 * @module     enrol_apply/queue/derive
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fill} from '../datareport/str';
import type {Selection} from '../datareport/types';
import type {Column, Preset, Row, RowsAnswer, ViewProps} from './types';

/** What the view reads off the newest answer; the empty values stand for "no answer yet". */
export type Derived = {
    groupdefault: string,
    allcolumns: Column[],
    usedefault: boolean,
    presets: Preset[],
    total: number,
    list: Row[],
    /** Whether the props offer a decision and the rows service agrees. */
    decides: boolean,
    /** The reader is in no group of a method narrowed to groups: nothing but the notice is drawn. */
    notingroup: boolean,
    narrowed: boolean,
    /** An answer is drawn and this reader may not decide on it. */
    viewonly: boolean,
};

/**
 * Read the answer.
 *
 * @param {object} rows The newest rows answer, or null before the first.
 * @param {object} config The view's props.
 * @returns {object} What the view draws from.
 */
export const derive = (rows: RowsAnswer | null, config: ViewProps): Derived => {
    const decides = config.decision !== null && rows !== null && rows.candecide;

    return {
        decides,
        notingroup: Boolean(rows?.notingroup),
        narrowed: Boolean(rows?.narrowed),
        viewonly: rows !== null && !decides,
        groupdefault: rows?.groupdefault ?? '',
        allcolumns: rows?.columns ?? [],
        usedefault: rows?.preference.usedefault ?? true,
        presets: rows?.presets ?? [],
        total: rows?.total ?? 0,
        list: (rows?.rows ?? []).map((row) => ({...row, id: row.userenrolmentid})),
    };
};

/** The selection as the view uses it. */
export type Chosen = {
    locked: ReadonlySet<number>,
    chosen: Row[],
    selection: Selection<Row> | undefined,
};

/**
 * Build the selection over this page's rows.
 *
 * @param {object} selected The ids selected.
 * @param {Function} setSelected The state's setter.
 * @param {object} derived What the view reads off the answer.
 * @param {object} labels The strings.
 * @returns {object} The locked ids, the selected rows and the grid's selection (undefined for a viewer).
 */
export const chooseRows = (
    selected: ReadonlySet<number>,
    setSelected: (update: (before: ReadonlySet<number>) => ReadonlySet<number>) => void,
    derived: Derived,
    labels: Record<string, string>,
): Chosen => {
    const {list, decides} = derived;
    const locked = new Set(list.filter((row) => !row.selectable).map((row) => row.id));
    const chosen = list.filter((row) => selected.has(row.id));

    /**
     * Select or release one row; a row that may not be selected is never added.
     *
     * @param {number} id The row's user enrolment id.
     * @param {boolean} on Whether to select it.
     * @returns {void}
     */
    const onSelect = (id: number, on: boolean): void => setSelected((before) => {
        const next = new Set(before);
        if (on && !locked.has(id)) {
            next.add(id);
        } else {
            next.delete(id);
        }
        return next;
    });

    /**
     * Select every selectable row of this page, and no other, or release them all.
     *
     * @param {boolean} on Whether to select them.
     * @returns {void}
     */
    const onSelectPage = (on: boolean): void => setSelected(
        () => (on ? new Set(list.filter((row) => !locked.has(row.id)).map((row) => row.id)) : new Set<number>())
    );

    /**
     * A row box's name: "Select" and the applicant.
     *
     * @param {object} row The row.
     * @returns {string} The name.
     */
    const boxLabel = (row: Row): string => fill(labels.selectapplicant, row.applicant.fullname);

    const selection: Selection<Row> | undefined = decides ? {
        selected,
        locked,
        /**
         *
         * @param row
         */
        label: boxLabel,
        pagelabel: labels.selectpage,
        onSelect,
        onSelectPage,
    } : undefined;

    return {locked, chosen, selection};
};
