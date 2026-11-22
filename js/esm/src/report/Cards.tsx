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
 * The card view (AR-D9): the same rows, page and order as the grid, one article each.
 *
 * Four fixed lines answer who, what happened, how long and who decided: the applicant with the outcome
 * and the Review action; the wait and the submission date; the decision; the group and the method. The
 * other chosen fields follow in a list, at once on a wide screen (a grid of 17.5rem cards) and behind
 * "More" on a narrow one (one column).
 *
 * @module     enrol_apply/report/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useState} from 'react';
import type {ReactElement} from 'react';
import {CellLines, IdentityLines, ReviewButton, cellHtml, lines} from './cells';
import type {Column, Row} from './types';

/** The columns the four fixed lines draw; every other active column goes in the list. */
const FIXED = ['applicant', 'outcome', 'submitted', 'wait', 'decided', 'group', 'method'];

type CardsProps = {
    labels: Record<string, string>,
    columns: Column[],
    identity: Column[],
    rows: Row[],
    single: boolean,
};

/**
 * A cell's first line only, inline.
 *
 * @param {object} props The row and the column key.
 * @returns {object|null} The line, or nothing for an empty cell.
 */
const First = ({row, column}: {row: Row, column: string}): ReactElement | null => {
    const first = lines(column, cellHtml(row, column))[0] ?? '';

    return first === '' ? null : <span className="enrol_apply-rview-inline" dangerouslySetInnerHTML={{__html: first}} />;
};

/**
 * One card.
 *
 * @param {object} props The strings, the active columns, the identity columns, the row, and whether the list folds.
 * @returns {object} The article.
 */
const Card = ({labels, columns, identity, row, single}: {labels: Record<string, string>, columns: Column[],
        identity: Column[], row: Row, single: boolean}): ReactElement => {
    const [more, setMore] = useState(false);
    // A field with nothing in it is left out of the card: the table keeps its empty cell, a card has no column to keep.
    const extra = columns.filter((column) => !FIXED.includes(column.key) && cellHtml(row, column.key).trim() !== '');
    const shown = !single || more;
    /**
     * Whether the reader's report shows a column.
     *
     * @param {string} key The column key.
     * @returns {boolean} True when it is active.
     */
    const has = (key: string): boolean => columns.some((column) => column.key === key);

    const list = (
        <dl className="enrol_apply-rview-card-list">
            {extra.map((column) => (
                <div key={column.key} className="enrol_apply-rview-card-item">
                    <dt>{column.title}</dt>
                    <dd><CellLines column={column.key} html={cellHtml(row, column.key)} /></dd>
                </div>
            ))}
        </dl>
    );

    return (
        <article className="enrol_apply-rview-card" role="listitem" data-record={row.id}>
            <div className="enrol_apply-rview-card-l1">
                <div className="enrol_apply-rview-card-who">
                    <span className="enrol_apply-rview-card-name" dangerouslySetInnerHTML={{__html: cellHtml(row, 'applicant')}} />
                    {has('outcome') ? <First row={row} column="outcome" /> : null}
                    <IdentityLines row={row} identity={identity} />
                </div>
                <ReviewButton row={row} label={labels.review} />
            </div>
            <div className={'enrol_apply-rview-card-line' + (row.open ? ' enrol_apply-rview-card-line--open' : '')}>
                {has('wait') ? <First row={row} column="wait" /> : null}
                {has('wait') && has('submitted') ? <span aria-hidden="true"> · </span> : null}
                {has('submitted') ? <First row={row} column="submitted" /> : null}
            </div>
            {has('decided') ? (
                <div className="enrol_apply-rview-card-line">
                    {lines('decided', cellHtml(row, 'decided')).map((part, index) => (
                        <span key={index} className="enrol_apply-rview-inline" dangerouslySetInnerHTML={{__html: part}} />
                    ))}
                </div>
            ) : null}
            {has('group') || has('method') ? (
                <div className="enrol_apply-rview-card-line enrol_apply-rview-card-line--muted">
                    {has('group') ? <First row={row} column="group" /> : null}
                    {has('group') && has('method') && cellHtml(row, 'group') !== '' ? <span aria-hidden="true"> · </span> : null}
                    {has('method') ? <First row={row} column="method" /> : null}
                </div>
            ) : null}
            {extra.length > 0 ? (
                <div className="enrol_apply-rview-card-more">
                    {single ? (
                        <button
                            type="button"
                            className="btn btn-link btn-sm enrol_apply-rview-linkbtn"
                            aria-expanded={more}
                            onClick={() => setMore(!more)}
                        >
                            {more ? labels.less : labels.more}
                        </button>
                    ) : null}
                    {shown ? list : null}
                </div>
            ) : null}
        </article>
    );
};

/**
 * The cards.
 *
 * @param {object} props See CardsProps.
 * @returns {object} The list of cards.
 */
export const Cards = ({labels, columns, identity, rows, single}: CardsProps): ReactElement => (
    <div className={'enrol_apply-rview-cards' + (single ? ' enrol_apply-rview-cards--single' : '')} role="list">
        {rows.map((row) => (
            <Card key={row.id} labels={labels} columns={columns} identity={identity} row={row} single={single} />
        ))}
    </div>
);

/**
 * The cards' skeleton.
 *
 * @param {object} props Whether the cards are one column.
 * @returns {object} The skeleton.
 */
export const CardsSkeleton = ({single}: {single: boolean}): ReactElement => (
    <div className={'enrol_apply-rview-cards' + (single ? ' enrol_apply-rview-cards--single' : '')} aria-hidden="true">
        {Array.from({length: single ? 4 : 6}, (_, i) => (
            <div key={i} className="enrol_apply-rview-card enrol_apply-rview-sk">
                <span className="enrol_apply-rview-skb enrol_apply-rview-skb--w60" />
                <span className="enrol_apply-rview-skb enrol_apply-rview-skb--w80" />
                <span className="enrol_apply-rview-skb enrol_apply-rview-skb--w60" />
                <span className="enrol_apply-rview-skb enrol_apply-rview-skb--w40" />
            </div>
        ))}
    </div>
);
