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
 * every cell; the heading row sticks to the top of the frame and the lead column (the view's first,
 * named by `lead`) to its left, with a shadow on its edge once the frame has scrolled sideways.
 * Sortable headings are buttons, the order in aria-sort. Each heading's right edge is a separator that
 * resizes its column: a drag changes the col element alone and is saved once, on release, in the
 * reader's preference (AR-D13); the arrow keys move it by 16 px, saved once too, after the last key or
 * when the handle loses the focus; a double click restores the default.
 *
 * What a cell holds is the view's: `renderLead` draws the lead column's cell, `renderCell` every other.
 * A view that selects rows passes a selection, whose boxes lead the lead column's heading and cells; a
 * view without one draws none.
 *
 * Strings read from the view's labels: grid, caption, resize.
 *
 * @module     enrol_apply/datareport/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef} from 'react';
import type {KeyboardEvent, PointerEvent, ReactElement, ReactNode, UIEvent} from 'react';
import {widthOf} from './sizing';
import {fill} from './str';
import type {Column, RowBase, Selection, Sizing} from './types';

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

type GridProps<R extends RowBase> = {
    labels: Record<string, string>,
    columns: Column[],
    lead: string,
    rows: R[],
    widths: Record<string, number>,
    sizing: Sizing,
    sort: string,
    direction: string,
    selection?: Selection<R>,
    renderLead: (row: R, column: Column) => ReactNode,
    renderCell: (row: R, column: Column) => ReactNode,
    onSort: (key: string) => void,
    onWidth: (key: string, width: number | null, defer?: boolean) => void,
    onWidthCommit: () => void,
};

/**
 * The box that selects every selectable row of the page: checked when all are, mixed when some are.
 *
 * @param {object} props The selection and the page's rows.
 * @returns {object} The box.
 */
const PageBox = <R extends RowBase>({selection, rows}: {selection: Selection<R>, rows: R[]}): ReactElement => {
    const box = useRef<HTMLInputElement | null>(null);
    const open = rows.filter((row) => !selection.locked.has(row.id));
    const picked = open.filter((row) => selection.selected.has(row.id)).length;
    const all = open.length > 0 && picked === open.length;

    // Mixed is a property, not an attribute: React cannot write it.
    useEffect(() => {
        if (box.current) {
            box.current.indeterminate = picked > 0 && !all;
        }
    }, [picked, all]);

    return (
        <input
            ref={box}
            type="checkbox"
            className="enrol_apply-rview-pick"
            aria-label={selection.pagelabel}
            checked={all}
            disabled={open.length === 0}
            onChange={(event) => selection.onSelectPage(event.target.checked)}
        />
    );
};

/**
 * One row's box, or an empty place of the same width on a row that cannot be selected.
 *
 * @param {object} props The selection and the row.
 * @returns {object} The box or its place.
 */
export const RowBox = <R extends RowBase>({selection, row}: {selection: Selection<R>, row: R}): ReactElement => (
    selection.locked.has(row.id) ? <span className="enrol_apply-rview-pick enrol_apply-rview-pick--none" /> : (
        <input
            type="checkbox"
            className="enrol_apply-rview-pick"
            aria-label={selection.label(row)}
            checked={selection.selected.has(row.id)}
            onChange={(event) => selection.onSelect(row.id, event.target.checked)}
        />
    )
);

/**
 * The grid.
 *
 * @param {object} props See GridProps.
 * @returns {object} The frame and its table.
 */
export const Grid = <R extends RowBase>({labels, columns, lead, rows, widths, sizing, sort, direction, selection,
        renderLead, renderCell, onSort, onWidth, onWidthCommit}: GridProps<R>): ReactElement => {
    const frame = useRef<HTMLDivElement | null>(null);
    const table = useRef<HTMLTableElement | null>(null);
    const drag = useRef<{key: string, x: number, width: number, now: number} | null>(null);

    const total = columns.reduce((sum, column) => sum + widthOf(sizing, widths, column.key), 0);

    /**
     * The shadow on the lead column's edge, once the frame has scrolled sideways.
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
        table.current.style.width = `${total - widthOf(sizing, widths, key) + width}px`;
    };

    /**
     * A width held to the column's bounds.
     *
     * @param {string} key The column key.
     * @param {number} width Pixels.
     * @returns {number} Pixels, within bounds.
     */
    const bound = (key: string, width: number): number =>
        Math.round(Math.min(sizing.maximum, Math.max(sizing.minimumWidth(key), width)));

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
        const width = widthOf(sizing, widths, key);
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
     * The arrow keys on a handle: 16 px narrower or wider; Home restores the default. Each step is drawn
     * at once and saved later, once, as a drag is (onWidth's defer).
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
            onWidth(key, null, true);
            return;
        }
        if (step === 0) {
            return;
        }
        event.preventDefault();
        const width = bound(key, widthOf(sizing, widths, key) + step);
        if (width !== widthOf(sizing, widths, key)) {
            onWidth(key, width, true);
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
                        <col key={column.key} data-col={column.key} style={{width: `${widthOf(sizing, widths, column.key)}px`}} />
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
                                    className={column.key === lead ? 'enrol_apply-rview-fixed' : undefined}
                                    aria-sort={column.sortable ? order : undefined}
                                    title={column.title}
                                    data-column={column.key}
                                >
                                    {selection && column.key === lead ? <PageBox selection={selection} rows={rows} /> : null}
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
                                        aria-valuemin={sizing.minimumWidth(column.key)}
                                        aria-valuemax={sizing.maximum}
                                        aria-valuenow={widthOf(sizing, widths, column.key)}
                                        tabIndex={0}
                                        onPointerDown={(event) => down(event, column.key)}
                                        onPointerMove={move}
                                        onPointerUp={up}
                                        onPointerCancel={up}
                                        onDoubleClick={() => {
                                            if (widthOf(sizing, widths, column.key) !== sizing.defaultWidth(column.key)) {
                                                onWidth(column.key, null);
                                            }
                                        }}
                                        onKeyDown={(event) => keydown(event, column.key)}
                                        onBlur={onWidthCommit}
                                    />
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={row.id}
                            data-record={row.id}
                            className={selection?.selected.has(row.id) ? 'enrol_apply-rview-row--selected' : undefined}
                        >
                            {columns.map((column) => (
                                column.key === lead ? (
                                    <td key={column.key} className="enrol_apply-rview-fixed">
                                        {selection ? <RowBox selection={selection} row={row} /> : null}
                                        {renderLead(row, column)}
                                    </td>
                                ) : (
                                    <td key={column.key}>{renderCell(row, column)}</td>
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
 * @param {object} props The columns to draw, the lead column's key, the reader's widths and the view's sizing.
 * @returns {object} The skeleton.
 */
export const GridSkeleton = ({columns, lead, widths, sizing}: {columns: Column[], lead: string, widths: Record<string, number>,
        sizing: Sizing}): ReactElement => {
    const total = columns.reduce((sum, column) => sum + widthOf(sizing, widths, column.key), 0);
    const shapes = ['enrol_apply-rview-skb--w80', 'enrol_apply-rview-skb--w60', 'enrol_apply-rview-skb--w40'];

    return (
        <div className="enrol_apply-rview-gridframe" aria-hidden="true">
            <table className="enrol_apply-rview-grid" style={{width: `${total}px`}}>
                <colgroup>
                    {columns.map((column) => <col key={column.key} style={{width: `${widthOf(sizing, widths, column.key)}px`}} />)}
                </colgroup>
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th key={column.key} className={column.key === lead ? 'enrol_apply-rview-fixed' : undefined}>
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
                                <td key={column.key} className={column.key === lead ? 'enrol_apply-rview-fixed' : undefined}>
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
