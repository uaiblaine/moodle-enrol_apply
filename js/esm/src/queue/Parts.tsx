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
 * The parts of the queue view that only choose what to draw: the notices over the bar, the rows as the
 * grid or the cards, the dock and the decision sheet. Each takes what the view already holds and keeps
 * no state of its own.
 *
 * @module     enrol_apply/queue/Parts
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {Cards as DataCards} from '../datareport/Cards';
import type {CardField} from '../datareport/Cards';
import {Grid as DataGrid} from '../datareport/Grid';
import type {Selection} from '../datareport/types';
import {ApplicantCell, CellLine, SIZING, cellHtml} from './cells';
import {Dock} from './Dock';
import {Sheet} from './Sheet';
import type {SheetValues} from './Sheet';
import type {Column, Decision, DecisionProps, Row} from './types';

/** The columns a card draws on its fixed lines; every other active column goes in its list. */
const CARD_FIXED = ['applicant', 'wait', 'group'];

/**
 * A card's list: the chosen columns its fixed lines do not draw, a field with nothing in it left out (a
 * card has no column to keep).
 *
 * @param {Array} shown The columns in view.
 * @param {object} row The row.
 * @returns {Array} The fields.
 */
const cardFields = (shown: Column[], row: Row): CardField[] => shown
    .filter((column) => !CARD_FIXED.includes(column.key) && cellHtml(row, column.key).trim() !== '')
    .map((column) => ({key: column.key, title: column.title, content: <CellLine html={cellHtml(row, column.key)} />}));

type NoticesProps = {
    labels: Record<string, string>,
    narrowed: boolean,
    viewonly: boolean,
};

/**
 * The notices over the bar: narrowed to the reader's groups, and read-only.
 *
 * @param {object} props See NoticesProps.
 * @returns {object} The notices.
 */
export const Notices = ({labels, narrowed, viewonly}: NoticesProps): ReactElement => (
    <>
        {narrowed ? <p className="enrol_apply-rview-narrowed">{labels.narrowed}</p> : null}
        {viewonly ? <div className="alert alert-info" role="status">{labels.viewonly}</div> : null}
    </>
);

type BodyProps = {
    labels: Record<string, string>,
    list: Row[],
    shown: Column[],
    cards: boolean,
    narrow: boolean,
    widths: Record<string, number>,
    sort: string,
    direction: 'asc' | 'desc',
    selection: Selection<Row> | undefined,
    onSort: (key: string) => void,
    onWidth: (key: string, width: number | null, defer?: boolean) => void,
    onWidthCommit: () => void,
};

/**
 * The rows as the reader views them: cards, or the grid.
 *
 * @param {object} props See BodyProps.
 * @returns {object} The rows.
 */
export const RowsBody = (props: BodyProps): ReactElement => {
    const {labels, list, shown, cards, narrow, selection} = props;
    /**
     * Whether the reader's grid shows a column.
     *
     * @param {string} key The column key.
     * @returns {boolean} True when it is active.
     */
    const has = (key: string): boolean => shown.some((column) => column.key === key);

    if (cards) {
        return (
            <DataCards<Row>
                labels={labels}
                rows={list}
                single={narrow}
                selection={selection}
                renderCard={(row) => ({
                    head: <ApplicantCell labels={labels} row={row} />,
                    lines: (
                        <>
                            {has('wait') ? (
                                <div
                                    className="enrol_apply-rview-card-line enrol_apply-rview-card-line--open"
                                    dangerouslySetInnerHTML={{__html: cellHtml(row, 'wait')}}
                                />
                            ) : null}
                            {has('group') && cellHtml(row, 'group') !== '' ? (
                                <div
                                    className="enrol_apply-rview-card-line enrol_apply-rview-card-line--muted"
                                    dangerouslySetInnerHTML={{__html: cellHtml(row, 'group')}}
                                />
                            ) : null}
                        </>
                    ),
                    fields: cardFields(shown, row),
                })}
            />
        );
    }

    return (
        <DataGrid<Row>
            labels={labels}
            columns={shown}
            lead="applicant"
            rows={list}
            widths={props.widths}
            sizing={SIZING}
            sort={props.sort}
            direction={props.direction}
            selection={selection}
            renderLead={(row) => <ApplicantCell labels={labels} row={row} />}
            renderCell={(row, column) => <CellLine html={cellHtml(row, column.key)} />}
            onSort={props.onSort}
            onWidth={props.onWidth}
            onWidthCommit={props.onWidthCommit}
        />
    );
};

/**
 * The dock under the rows, only while a reader who decides has something selected.
 *
 * @param {object} labels The strings.
 * @param {object} offer What the sheet may offer, null for a reader who may not decide.
 * @param {boolean} decides Whether this reader decides.
 * @param {number} count How many rows are selected.
 * @param {Function} onDecide Open the sheet for a decision.
 * @returns {object} The dock, or null.
 */
export const dockFor = (
    labels: Record<string, string>,
    offer: DecisionProps | null,
    decides: boolean,
    count: number,
    onDecide: (decision: Decision, button: HTMLButtonElement) => void,
): ReactElement | null => (offer !== null && decides && count > 0
    ? <Dock labels={labels} count={count} actions={offer.actions} onDecide={onDecide} />
    : null);

/**
 * The decision sheet, only while a decision is open over a selection.
 *
 * @param {object} labels The strings.
 * @param {object} offer What the sheet may offer, null for a reader who may not decide.
 * @param {string|null} decision The decision open, or null.
 * @param {Array} chosen The selected rows.
 * @param {Function} onClose Close the sheet.
 * @param {Function} onCommit Post the decision.
 * @returns {object} The sheet, or null.
 */
export const sheetFor = (
    labels: Record<string, string>,
    offer: DecisionProps | null,
    decision: Decision | null,
    chosen: Row[],
    onClose: () => void,
    onCommit: (values: SheetValues) => void,
): ReactElement | null => (offer !== null && decision !== null && chosen.length > 0
    ? <Sheet labels={labels} decision={decision} rows={chosen} offer={offer} onClose={onClose} onCommit={onCommit} />
    : null);
