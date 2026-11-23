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
 * The course report's grid: the shared bounded grid (datareport/Grid) with the report's cells.
 *
 * The lead column is the applicant: their name as the report formats it, the identity fields the
 * reader's report shows under it, and the Review action on an open application. Every other cell is
 * the report's formatted HTML, one fact per line, and the wait's carries a bar of the row's wait against
 * the longest on the page. The report selects nothing.
 *
 * @module     enrol_apply/report/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {Grid as DataGrid} from '../datareport/Grid';
import {CellLines, IdentityLines, ReviewButton, SIZING, cellHtml} from './cells';
import type {Column, Row} from './types';

type GridProps = {
    labels: Record<string, string>,
    columns: Column[],
    identity: Column[],
    rows: Row[],
    widths: Record<string, number>,
    sort: string,
    direction: string,
    onSort: (key: string) => void,
    onWidth: (key: string, width: number | null, defer?: boolean) => void,
    onWidthCommit: () => void,
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
export const Grid = ({labels, columns, identity, rows, widths, sort, direction, onSort, onWidth,
        onWidthCommit}: GridProps): ReactElement => {
    const longest = Math.max(86400, ...rows.filter((row) => row.open || row.decidedbyperson).map((row) => row.wait));

    return (
        <DataGrid<Row>
            labels={labels}
            columns={columns}
            lead="applicant"
            rows={rows}
            widths={widths}
            sizing={SIZING}
            sort={sort}
            direction={direction}
            renderLead={(row, column) => (
                <div className="enrol_apply-rview-applicant">
                    <div className="enrol_apply-rview-applicant-text">
                        <CellLines column={column.key} html={cellHtml(row, column.key)} />
                        <IdentityLines row={row} identity={identity} />
                    </div>
                    <ReviewButton row={row} label={labels.review} />
                </div>
            )}
            renderCell={(row, column) => (
                <>
                    <CellLines column={column.key} html={cellHtml(row, column.key)} />
                    {column.key === 'wait' ? <WaitBar row={row} longest={longest} /> : null}
                </>
            )}
            onSort={onSort}
            onWidth={onWidth}
            onWidthCommit={onWidthCommit}
        />
    );
};
