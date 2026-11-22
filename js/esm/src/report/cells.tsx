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
 * The report's cells as the grid and the cards draw them, and the columns' widths.
 *
 * A cell is the report's own formatted HTML (format_row(): the masking and the escaping already ran
 * on the server, as on core's table), so it is inserted as HTML and never built here. A few cells hold
 * several facts, one per line (the formatters join them with a newline: the outcome and what the
 * record says, the wait and how it ended, the date and who decided, the group and the groups joined);
 * the first line is the cell's line and the others its sub-lines. Every line ends in an ellipsis, its
 * whole text in its title.
 *
 * @module     enrol_apply/report/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {plain} from './str';
import type {Column, Row} from './types';

/** The columns whose formatter writes one fact per line (formatters\submission). */
const MULTILINE = ['outcome', 'wait', 'decided', 'group'];

/** Default widths, in pixels, by column key; anything else takes DEFAULT_WIDTH. */
const WIDTHS: Record<string, number> = {
    applicant: 260,
    outcome: 180,
    submitted: 132,
    wait: 150,
    decided: 172,
    group: 200,
    method: 170,
    route: 170,
    comment: 240,
    note: 240,
};

/** The width of a column the table above does not name (a question, mostly). */
const DEFAULT_WIDTH = 180;

/** Minimum widths: the applicant 160 px, a date 104 px (section 5b), anything else 80 px. */
const MINIMUMS: Record<string, number> = {applicant: 160, submitted: 104, decided: 104};

/** The narrowest any column may be dragged. */
const MINIMUM = 80;

/** The widest a column may be dragged; the server keeps up to 1600. */
export const MAXIMUM = 800;

/**
 * A column's default width.
 *
 * @param {string} key The column key.
 * @returns {number} Pixels.
 */
export const defaultWidth = (key: string): number => WIDTHS[key] ?? DEFAULT_WIDTH;

/**
 * A column's minimum width.
 *
 * @param {string} key The column key.
 * @returns {number} Pixels.
 */
export const minimumWidth = (key: string): number => MINIMUMS[key] ?? MINIMUM;

/**
 * The width a column is drawn at: the reader's, held to the bounds, or the default.
 *
 * @param {object} widths The reader's widths by key.
 * @param {string} key The column key.
 * @returns {number} Pixels.
 */
export const widthOf = (widths: Record<string, number>, key: string): number =>
    Math.min(MAXIMUM, Math.max(minimumWidth(key), widths[key] ?? defaultWidth(key)));

/**
 * A row's cell for a column, as HTML; '' where the report gave none.
 *
 * @param {object} row The row.
 * @param {string} key The column key.
 * @returns {string} The cell's HTML.
 */
export const cellHtml = (row: Row, key: string): string => row.cells.find((cell) => cell.key === key)?.html ?? '';

/**
 * A cell's lines: one for most columns, one per fact for the columns that join several.
 *
 * @param {string} key The column key.
 * @param {string} html The cell's HTML.
 * @returns {Array} The lines, as HTML.
 */
export const lines = (key: string, html: string): string[] => {
    if (!MULTILINE.includes(key)) {
        return [html];
    }

    return html.split('\n').map((line) => line.trim()).filter((line) => line !== '');
};

/**
 * One cell's content: its first line, then its sub-lines.
 *
 * @param {object} props The column key and the cell's HTML.
 * @returns {object} The lines.
 */
export const CellLines = ({column, html}: {column: string, html: string}): ReactElement => {
    const parts = lines(column, html);

    return (
        <>
            {parts.map((part, index) => (
                <span
                    key={index}
                    className={index === 0 ? 'enrol_apply-rview-c' : 'enrol_apply-rview-sub'}
                    title={plain(part)}
                    dangerouslySetInnerHTML={{__html: part}}
                />
            ))}
        </>
    );
};

/**
 * The Review action, on a row the server gave a review url (AR-D18): an application still awaiting a
 * decision, for a reader the review page admits.
 *
 * @param {object} props The row and the button's word.
 * @returns {object|null} The link, or nothing.
 */
export const ReviewButton = ({row, label}: {row: Row, label: string}): ReactElement | null => (
    row.reviewurl === '' ? null : (
        <a className="btn btn-sm btn-outline-secondary enrol_apply-rview-review" href={row.reviewurl}>{label}</a>
    )
);

/**
 * The identity columns' values of a row: the lines under the applicant's name.
 *
 * @param {object} props The row and the identity columns.
 * @returns {object} The lines.
 */
export const IdentityLines = ({row, identity}: {row: Row, identity: Column[]}): ReactElement => (
    <>
        {identity.map((column) => {
            const html = cellHtml(row, column.key);
            return html === '' ? null : (
                <span
                    key={column.key}
                    className="enrol_apply-rview-sub"
                    title={plain(html)}
                    dangerouslySetInnerHTML={{__html: html}}
                />
            );
        })}
    </>
);
