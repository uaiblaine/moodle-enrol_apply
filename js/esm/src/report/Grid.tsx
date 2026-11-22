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
 * The bounded grid (section 5b of the study): a real table in a frame that scrolls both ways.
 *
 * table-layout fixed with a col per column, so the widths are exact and the browser does not measure
 * every cell; the heading row sticks to the top of the frame and the Applicant column to its left,
 * with a shadow on its edge once the frame has scrolled sideways. Sortable headings are buttons, the
 * order in aria-sort. Each heading's right edge is a separator that resizes its column: a drag changes
 * the col element alone and is saved once, on release, in the reader's preference (AR-D13); the arrow
 * keys move it by 16 px; a double click restores the default.
 *
 * @module     enrol_apply/report/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useRef} from 'react';
import type {KeyboardEvent, PointerEvent, ReactElement, UIEvent} from 'react';
import {CellLines, IdentityLines, MAXIMUM, ReviewButton, cellHtml, defaultWidth, minimumWidth, widthOf} from './cells';
import {fill} from './str';
import type {Column, Row} from './types';

/** The arrow keys' step on a resize handle, in pixels. */
const STEP = 16;

/** The arrow drawn beside a sorted heading, by its aria-sort value. */
const ARROWS: Record<string, string> = {ascending: '▲', descending: '▼', none: ''};

/**
 * A heading's aria-sort value.
 *
 * @param {boolean} sorted Whether the rows are sorted by this column.
 * @param {string} direction The direction they are sorted in.
 * @returns {string} ascending, descending or none.
 */
const ariaSort = (sorted: boolean, direction: string): 'ascending' | 'descending' | 'none' => {
    if (!sorted) {
        return 'none';
    }

    return direction === 'asc' ? 'ascending' : 'descending';
};

type GridProps = {
    labels: Record<string, string>,
    columns: Column[],
    identity: Column[],
    rows: Row[],
    widths: Record<string, number>,
    sort: string,
    direction: string,
    onSort: (key: string) => void,
    onWidth: (key: string, width: number | null) => void,
};

/**
 * The wait cell's bar: the row's wait against the longest on the page, in the warning tone while open.
 *
 * @param {object} props The row and the longest wait on the page.
 * @returns {object|null} The bar, or nothing for a decision without a person.
 */
const WaitBar = ({row, longest}: {row: Row, longest: number}): ReactElement | null => {
    if (!row.open && !row.decidedbyperson) {
        return null;
    }

    return (
        <span className={'enrol_apply-rview-waitbar' + (row.open ? ' enrol_apply-rview-waitbar--open' : '')} aria-hidden="true">
            <span style={{width: `${Math.min(100, Math.round(row.wait / longest * 100))}%`}} />
        </span>
    );
};

/**
 * The grid.
 *
 * @param {object} props See GridProps.
 * @returns {object} The frame and its table.
 */
export const Grid = ({labels, columns, identity, rows, widths, sort, direction, onSort, onWidth}: GridProps): ReactElement => {
    const frame = useRef<HTMLDivElement | null>(null);
    const table = useRef<HTMLTableElement | null>(null);
    const drag = useRef<{key: string, x: number, width: number, now: number} | null>(null);

    const total = columns.reduce((sum, column) => sum + widthOf(widths, column.key), 0);
    const longest = Math.max(86400, ...rows.filter((row) => row.open || row.decidedbyperson).map((row) => row.wait));

    /**
     * The shadow on the Applicant column's edge, once the frame has scrolled sideways.
     *
     * @param {object} event The scroll event.
     * @returns {void}
     */
    const scrolled = (event: UIEvent<HTMLDivElement>): void => {
        event.currentTarget.classList.toggle('is-scrolled', event.currentTarget.scrollLeft > 0);
    };

    /**
     * Draw one column at a width without rendering: its col element and the table's width.
     *
     * The table's width is the drawn total with this column's drawn width replaced, so a drag that
     * paints many times never accumulates a rounding of its own.
     *
     * @param {string} key The column key.
     * @param {number} width Pixels.
     * @returns {void}
     */
    const paint = (key: string, width: number): void => {
        const col = table.current?.querySelector<HTMLTableColElement>(`col[data-col="${CSS.escape(key)}"]`);
        if (!col || !table.current) {
            return;
        }
        col.style.width = `${width}px`;
        table.current.style.width = `${total - widthOf(widths, key) + width}px`;
    };

    /**
     * A width held to the column's bounds.
     *
     * @param {string} key The column key.
     * @param {number} width Pixels.
     * @returns {number} Pixels, within bounds.
     */
    const bound = (key: string, width: number): number => Math.round(Math.min(MAXIMUM, Math.max(minimumWidth(key), width)));

    /**
     * Start a drag on a handle.
     *
     * @param {object} event The pointer event.
     * @param {string} key The column key.
     * @returns {void}
     */
    const down = (event: PointerEvent<HTMLSpanElement>, key: string): void => {
        if (event.button !== 0) {
            return;
        }
        event.preventDefault();
        event.currentTarget.setPointerCapture(event.pointerId);
        event.currentTarget.classList.add('is-active');
        const width = widthOf(widths, key);
        drag.current = {key, x: event.clientX, width, now: width};
    };

    /**
     * Follow the drag: the col element alone changes.
     *
     * @param {object} event The pointer event.
     * @returns {void}
     */
    const move = (event: PointerEvent<HTMLSpanElement>): void => {
        const current = drag.current;
        if (!current) {
            return;
        }
        const rtl = document.documentElement.dir === 'rtl';
        const delta = (event.clientX - current.x) * (rtl ? -1 : 1);
        const width = bound(current.key, current.width + delta);
        if (width !== current.now) {
            paint(current.key, width);
            current.now = width;
        }
    };

    /**
     * End the drag: the width is saved once, now.
     *
     * @param {object} event The pointer event.
     * @returns {void}
     */
    const up = (event: PointerEvent<HTMLSpanElement>): void => {
        const current = drag.current;
        drag.current = null;
        event.currentTarget.classList.remove('is-active');
        if (current && current.now !== current.width) {
            onWidth(current.key, current.now);
        }
    };

    /**
     * The arrow keys on a handle: 16 px narrower or wider; Home restores the default.
     *
     * @param {object} event The keyboard event.
     * @param {string} key The column key.
     * @returns {void}
     */
    const keydown = (event: KeyboardEvent<HTMLSpanElement>, key: string): void => {
        const rtl = document.documentElement.dir === 'rtl';
        let step = 0;
        if (event.key === 'ArrowRight') {
            step = rtl ? -STEP : STEP;
        } else if (event.key === 'ArrowLeft') {
            step = rtl ? STEP : -STEP;
        } else if (event.key === 'Home') {
            event.preventDefault();
            onWidth(key, null);
            return;
        }
        if (step === 0) {
            return;
        }
        event.preventDefault();
        const width = bound(key, widthOf(widths, key) + step);
        if (width !== widthOf(widths, key)) {
            onWidth(key, width);
        }
    };

    return (
        <div
            className="enrol_apply-rview-gridframe"
            ref={frame}
            tabIndex={0}
            role="region"
            aria-label={labels.grid}
            onScroll={scrolled}
        >
            <table className="enrol_apply-rview-grid" ref={table} style={{width: `${total}px`}}>
                <caption className="visually-hidden">{labels.caption}</caption>
                <colgroup>
                    {columns.map((column) => (
                        <col key={column.key} data-col={column.key} style={{width: `${widthOf(widths, column.key)}px`}} />
                    ))}
                </colgroup>
                <thead>
                    <tr>
                        {columns.map((column) => {
                            const order = ariaSort(sort === column.key, direction);
                            const title = <span className="enrol_apply-rview-th-title">{column.title}</span>;
                            return (
                                <th
                                    key={column.key}
                                    scope="col"
                                    className={column.key === 'applicant' ? 'enrol_apply-rview-fixed' : undefined}
                                    aria-sort={column.sortable ? order : undefined}
                                    title={column.title}
                                    data-column={column.key}
                                >
                                    {column.sortable ? (
                                        <button
                                            type="button"
                                            className="enrol_apply-rview-sortbtn"
                                            onClick={() => onSort(column.key)}
                                        >
                                            {title}
                                            <span className="enrol_apply-rview-arrow" aria-hidden="true">
                                                {ARROWS[order]}
                                            </span>
                                        </button>
                                    ) : <span className="enrol_apply-rview-plain">{title}</span>}
                                    <span
                                        className="enrol_apply-rview-resize"
                                        role="separator"
                                        aria-orientation="vertical"
                                        aria-label={fill(labels.resize, column.title)}
                                        aria-valuemin={minimumWidth(column.key)}
                                        aria-valuemax={MAXIMUM}
                                        aria-valuenow={widthOf(widths, column.key)}
                                        tabIndex={0}
                                        onPointerDown={(event) => down(event, column.key)}
                                        onPointerMove={move}
                                        onPointerUp={up}
                                        onPointerCancel={up}
                                        onDoubleClick={() => {
                                            if (widthOf(widths, column.key) !== defaultWidth(column.key)) {
                                                onWidth(column.key, null);
                                            }
                                        }}
                                        onKeyDown={(event) => keydown(event, column.key)}
                                    />
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id} data-record={row.id}>
                            {columns.map((column) => (
                                column.key === 'applicant' ? (
                                    <td key={column.key} className="enrol_apply-rview-fixed">
                                        <div className="enrol_apply-rview-applicant">
                                            <div className="enrol_apply-rview-applicant-text">
                                                <CellLines column={column.key} html={cellHtml(row, column.key)} />
                                                <IdentityLines row={row} identity={identity} />
                                            </div>
                                            <ReviewButton row={row} label={labels.review} />
                                        </div>
                                    </td>
                                ) : (
                                    <td key={column.key}>
                                        <CellLines column={column.key} html={cellHtml(row, column.key)} />
                                        {column.key === 'wait' ? <WaitBar row={row} longest={longest} /> : null}
                                    </td>
                                )
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};

/**
 * The grid's skeleton: the frame and the headings, blocks for eight rows.
 *
 * @param {object} props The columns to draw and the reader's widths.
 * @returns {object} The skeleton.
 */
export const GridSkeleton = ({columns, widths}: {columns: Column[], widths: Record<string, number>}): ReactElement => {
    const total = columns.reduce((sum, column) => sum + widthOf(widths, column.key), 0);
    const shapes = ['enrol_apply-rview-skb--w80', 'enrol_apply-rview-skb--w60', 'enrol_apply-rview-skb--w40'];

    return (
        <div className="enrol_apply-rview-gridframe" aria-hidden="true">
            <table className="enrol_apply-rview-grid" style={{width: `${total}px`}}>
                <colgroup>
                    {columns.map((column) => <col key={column.key} style={{width: `${widthOf(widths, column.key)}px`}} />)}
                </colgroup>
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th key={column.key} className={column.key === 'applicant' ? 'enrol_apply-rview-fixed' : undefined}>
                                <span className="enrol_apply-rview-plain">
                                    <span className="enrol_apply-rview-th-title">{column.title}</span>
                                </span>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {Array.from({length: 8}, (_, i) => (
                        <tr key={i} className="enrol_apply-rview-sk">
                            {columns.map((column, j) => (
                                <td key={column.key} className={column.key === 'applicant' ? 'enrol_apply-rview-fixed' : undefined}>
                                    <span className={'enrol_apply-rview-skb ' + shapes[(i + j) % 3]} />
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};
