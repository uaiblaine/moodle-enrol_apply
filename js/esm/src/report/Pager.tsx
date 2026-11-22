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
 * The foot of the rows: which rows these are, the pages and the page size (AR-D14: 30, 50 or 100).
 *
 * The server pages (one data query and one count whatever the size); the client never holds more
 * than one page. Narrow, the pages read "Page 2 of 9" between Previous and Next.
 *
 * @module     enrol_apply/report/Pager
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useId} from 'react';
import type {ReactElement} from 'react';
import {fillObject, num} from './str';

type PagerProps = {
    labels: Record<string, string>,
    page: number,
    perpage: number,
    perpages: number[],
    total: number,
    shown: number,
    narrow: boolean,
    onPage: (page: number) => void,
    onPerpage: (perpage: number) => void,
};

/**
 * The page numbers drawn: the first, the last and the neighbours of the current one, 1-based.
 *
 * @param {number} current The current page, 1-based.
 * @param {number} pages How many pages.
 * @returns {Array} The page numbers, ascending, without repeats.
 */
const numbers = (current: number, pages: number): number[] =>
    [...new Set([1, current - 1, current, current + 1, pages])].filter((n) => n >= 1 && n <= pages).sort((a, b) => a - b);

/**
 * The foot.
 *
 * @param {object} props See PagerProps.
 * @returns {object} The foot.
 */
export const Pager = ({labels, page, perpage, perpages, total, shown, narrow, onPage, onPerpage}: PagerProps): ReactElement => {
    const id = useId();
    const pages = Math.max(1, Math.ceil(total / perpage));
    const current = page + 1;
    const from = total > 0 ? page * perpage + 1 : 0;
    const to = total > 0 ? from + shown - 1 : 0;

    let list: ReactElement[] = [];
    if (narrow) {
        list = [
            <span key="of" className="enrol_apply-rview-pager-gap">
                {fillObject(labels.pageof, {page: num(current), pages: num(pages)})}
            </span>,
        ];
    } else {
        const shownpages = numbers(current, pages);
        shownpages.forEach((n, index) => {
            if (index > 0 && n - shownpages[index - 1] > 1) {
                list.push(<span key={`gap${n}`} className="enrol_apply-rview-pager-gap" aria-hidden="true">…</span>);
            }
            list.push(
                <button
                    key={n}
                    type="button"
                    aria-current={n === current ? 'page' : undefined}
                    onClick={() => onPage(n - 1)}
                >
                    {num(n)}
                </button>
            );
        });
    }

    return (
        <div className="enrol_apply-rview-foot">
            <span className="enrol_apply-rview-num" data-region="enrol_apply-range">
                {fillObject(labels.range, {from: num(from), to: num(to), total: num(total)})}
            </span>
            {pages > 1 ? (
                <nav className="enrol_apply-rview-pager" aria-label={labels.pages}>
                    <button type="button" disabled={current === 1} onClick={() => onPage(page - 1)}>{labels.previous}</button>
                    {list}
                    <button type="button" disabled={current === pages} onClick={() => onPage(page + 1)}>{labels.next}</button>
                </nav>
            ) : null}
            <span className="enrol_apply-rview-perpage">
                <label htmlFor={id}>{labels.perpage}</label>
                <select
                    id={id}
                    className="form-select form-select-sm"
                    value={perpage}
                    onChange={(event) => onPerpage(Number(event.target.value))}
                >
                    {perpages.map((n) => <option key={n} value={n}>{num(n)}</option>)}
                </select>
            </span>
        </div>
    );
};
