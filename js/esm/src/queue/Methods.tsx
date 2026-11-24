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
 * The site-wide queue's "By course" view (stage W4 of docs/design/site-queue-as-report-plan.md, SQ-D9): one
 * row per enrolment method of what the list holds under the scope, the search and the filters, with what
 * awaits, what is deferred, the oldest wait, the places taken and whether the method is open, and a link to
 * that method's queue.
 *
 * The rows come from enrol_apply_queue_methods (queue_methods.php), which reads the list's own table, so the
 * bar's scope, filters and search narrow this view as they narrow the list. No selection, no dock and no
 * column choice: the grid is the data report's frame (datareport/Grid) with a fixed set of columns, sortable
 * by the course, what awaits and the oldest application; its widths are this page's alone, never stored. At
 * the narrow step the methods are cards, the same facts stacked. Every name is text; nothing here is HTML.
 *
 * {@link useMethods} reads the answers: the newest request wins, and each wait holds Behat's pending counter
 * until the answer is drawn.
 *
 * @module     enrol_apply/queue/Methods
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useRef, useState} from 'react';
import type {ReactElement} from 'react';
import {Cards as DataCards} from '../datareport/Cards';
import {Grid as DataGrid} from '../datareport/Grid';
import {Pager} from '../datareport/Pager';
import {hold} from '../datareport/pending';
import {RowsRegion} from '../datareport/Rows';
import {makeSizing} from '../datareport/sizing';
import {duration, fill, fillObject, num} from '../datareport/str';
import type {Column} from '../datareport/types';
import {CourseMethod} from './cells';
import {getMethods} from './repository';
import {percent, shortDate} from './Tiles';
import type {MethodRow, MethodsAnswer, Query} from './types';

/** A row as the shared grid and cards read it: the method's, keyed by the method's id. */
type Line = MethodRow & {id: number};

/** Default widths, in pixels, by column key. */
const WIDTHS: Record<string, number> = {
    course: 300,
    awaiting: 130,
    deferred: 120,
    oldest: 150,
    places: 170,
    status: 190,
    queue: 170,
};

/** The view's width policy: the course 200 px at the least, anything else 80 px. */
const SIZING = makeSizing(WIDTHS, 150, {course: 200}, 80, 800);

/** The sortable columns, each with the direction a first press asks for. */
const FIRST: Record<string, 'asc' | 'desc'> = {course: 'asc', awaiting: 'desc', oldest: 'asc'};

/**
 * The columns, in the mockup's order; the course and its method lead.
 *
 * @param {object} labels The strings, the view's own merged over the queue's.
 * @returns {Array} The columns.
 */
const methodColumns = (labels: Record<string, string>): Column[] => ([
    ['course', labels.coursemethod],
    ['awaiting', labels.awaiting],
    ['deferred', labels.deferred],
    ['oldest', labels.oldestwait],
    ['places', labels.placestaken],
    ['status', labels.status],
    ['queue', labels.queue],
] as [string, string][]).map(([key, title]) => ({
    key,
    title,
    sortable: key in FIRST,
    "default": true,
    active: true,
    fixed: key === 'course',
}));

/** What the view's reader holds. */
export type MethodsState = {
    answer: MethodsAnswer | null,
    loading: boolean,
    error: boolean,
    load: (asked: Query) => void,
};

/**
 * Read the view's pages: the newest request wins, and the pending counter is held until it is drawn.
 *
 * @param {Function} onClamp Told the page the server answered when it is not the one asked (past the end).
 * @returns {object} The answer, whether a read is in flight or failed, and the reader.
 */
export const useMethods = (onClamp: (page: number) => void): MethodsState => {
    const [answer, setAnswer] = useState<MethodsAnswer | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);
    const seq = useRef(0);
    const release = useRef<(() => void) | null>(null);
    const clamp = useRef(onClamp);
    clamp.current = onClamp;

    /**
     * Read a page of the view; an answer to an older request than the newest is dropped.
     *
     * @param {object} asked What to read: the list's scope, search and filters, the view's sort and page.
     * @returns {void}
     */
    const load = useCallback((asked: Query): void => {
        const mine = ++seq.current;
        if (!release.current) {
            release.current = hold('enrol_apply/queue:methods');
        }
        setLoading(true);
        getMethods(asked).then((got) => {
            if (mine !== seq.current) {
                return;
            }
            if (got.page !== asked.page) {
                clamp.current(got.page);
            }
            setAnswer(got);
            setError(false);
            setLoading(false);
            return;
        }).catch(() => {
            if (mine === seq.current) {
                setError(true);
                setLoading(false);
            }
        });
    }, []);

    // Behat waits on the hold: it is released once the answer it waited for has been drawn.
    useEffect(() => {
        if (!loading && release.current) {
            release.current();
            release.current = null;
        }
    }, [loading, answer, error]);

    return {answer, loading, error, load};
};

/**
 * The direction a press on a heading asks for: the other one on the sorted heading, its first otherwise.
 *
 * @param {object|null} answer The last answer, whose sort is in force.
 * @param {string} key The heading pressed.
 * @returns {string} asc or desc.
 */
export const methodsDirection = (answer: MethodsAnswer | null, key: string): 'asc' | 'desc' => {
    if (answer !== null && answer.sort === key) {
        return answer.direction === 'asc' ? 'desc' : 'asc';
    }

    return FIRST[key] ?? 'asc';
};

/**
 * The places taken: "taken of places" with the meter where the method has places, "taken, no limit" where it
 * has none, a dash where the reader could not enter the method's course and so is not told its capacity.
 *
 * @param {object} props The strings and the row.
 * @returns {object} The cell's content.
 */
const Places = ({labels, row}: {labels: Record<string, string>, row: MethodRow}): ReactElement => {
    if (!row.capacityknown) {
        return (
            <span className="enrol_apply-rview-c">
                <span aria-hidden="true">{'\u2013'}</span>
                <span className="visually-hidden">{labels.capacityhidden}</span>
            </span>
        );
    }
    if (row.places <= 0) {
        return <span className="enrol_apply-rview-c">{fill(labels.takennolimit, num(row.placestaken))}</span>;
    }

    return (
        <>
            <span className="enrol_apply-rview-c">
                {fillObject(labels.ofmany, {taken: num(row.placestaken), total: num(row.places)})}
            </span>
            <span
                className={'enrol_apply-qview-meter' + (row.placesfull ? ' enrol_apply-qview-meter--warn' : '')}
                aria-hidden="true"
            >
                <span style={{width: `${percent(row.placestaken, row.places)}%`}} />
            </span>
        </>
    );
};

/**
 * Whether the method takes applications: nothing where the capacity is not the reader's to see, "Full" where
 * every place is taken or its applicant limit is reached, else open (with the closing date, as the method's
 * tiles word it) or closed.
 *
 * @param {object} props The strings and the row.
 * @returns {object} The cell's content.
 */
const Status = ({labels, row}: {labels: Record<string, string>, row: MethodRow}): ReactElement => {
    if (!row.capacityknown) {
        return <span className="visually-hidden">{labels.capacityhidden}</span>;
    }
    if (row.placesfull || row.limitreached) {
        return (
            <span className="enrol_apply-status enrol_apply-status--neutral" title={labels.methodfullwarn}>
                {labels.methodfull}
                <span className="visually-hidden">{' ' + labels.methodfullwarn}</span>
            </span>
        );
    }
    if (!row.open) {
        return <span className="enrol_apply-rview-c">{labels.closed}</span>;
    }

    return (
        <>
            <span className="enrol_apply-rview-c">{labels.open}</span>
            {row.closes > 0 ? (
                <span className="enrol_apply-rview-sub">{fill(labels.closes, shortDate(row.closes))}</span>
            ) : null}
        </>
    );
};

/**
 * The link to the method's queue, named for its method for assistive technology.
 *
 * @param {object} props The strings and the row.
 * @returns {object|null} The link, or nothing without an address.
 */
const OpenQueue = ({labels, row}: {labels: Record<string, string>, row: MethodRow}): ReactElement | null => (
    row.method.queueurl === '' ? null : (
        <a className="btn btn-sm btn-outline-secondary" href={row.method.queueurl}>
            {labels.openqueue}
            <span className="visually-hidden">{' ' + row.method.name}</span>
        </a>
    )
);

/**
 * The "Course · method" cell, the list's own; "Full" is the status column's, so the method carries only
 * whether its role decides.
 *
 * @param {object} props The strings and the row.
 * @returns {object} The cell's content.
 */
const CourseCell = ({labels, row}: {labels: Record<string, string>, row: MethodRow}): ReactElement => (
    <CourseMethod labels={labels} row={{course: row.course, method: {...row.method, full: false}}} />
);

/**
 * One cell of a column other than the course.
 *
 * @param {object} props The strings, the row and the column's key.
 * @returns {object} The cell's content.
 */
const MethodCell = ({labels, row, column}: {labels: Record<string, string>, row: MethodRow,
        column: string}): ReactElement | null => {
    switch (column) {
        case 'awaiting':
            return <span className="enrol_apply-qview-figure" data-figure="awaiting">{num(row.awaiting)}</span>;
        case 'deferred':
            return <span className="enrol_apply-qview-figure" data-figure="deferred">{num(row.deferred)}</span>;
        case 'oldest':
            return <span className="enrol_apply-rview-c">{duration(labels, row.oldestwait)}</span>;
        case 'places':
            return <Places labels={labels} row={row} />;
        case 'status':
            return <Status labels={labels} row={row} />;
        default:
            return <OpenQueue labels={labels} row={row} />;
    }
};

/**
 * The methods as cards, at the narrow step: the course and the method with the link to its queue, then the
 * same facts as the grid's, stacked.
 *
 * @param {object} props The strings, the columns, the rows and whether the cards are one column.
 * @returns {object} The cards.
 */
const MethodCards = ({labels, columns, rows, single}: {labels: Record<string, string>, columns: Column[], rows: Line[],
        single: boolean}): ReactElement => (
    <DataCards<Line>
        labels={labels}
        rows={rows}
        single={single}
        renderCard={(row) => ({
            head: (
                <div className="enrol_apply-qview-methodhead">
                    <CourseCell labels={labels} row={row} />
                    <OpenQueue labels={labels} row={row} />
                </div>
            ),
            lines: (
                <dl className="enrol_apply-rview-card-list">
                    {columns.filter((column) => !['course', 'queue'].includes(column.key)).map((column) => (
                        <div key={column.key} className="enrol_apply-rview-card-item">
                            <dt>{column.title}</dt>
                            <dd><MethodCell labels={labels} row={row} column={column.key} /></dd>
                        </div>
                    ))}
                </dl>
            ),
            fields: [],
        })}
    />
);

type MethodsViewProps = {
    /** The queue's strings with the view's own merged over them. */
    labels: Record<string, string>,
    answer: MethodsAnswer | null,
    loading: boolean,
    error: boolean,
    /** Whether a scope, a filter or a search is in force, for the empty state's wording. */
    filtered: boolean,
    narrow: boolean,
    perpages: number[],
    onSort: (key: string) => void,
    onPage: (page: number) => void,
    onPerpage: (perpage: number) => void,
    onRetry: () => void,
};

/**
 * The view: the region with the grid or the cards, then the pager.
 *
 * @param {object} props See MethodsViewProps.
 * @returns {object} The view's body.
 */
export const MethodsView = (props: MethodsViewProps): ReactElement => {
    const {labels, answer, narrow} = props;
    // The widths a reader drags are this page's alone: the view has no stored grid.
    const [widths, setWidths] = useState<Record<string, number>>({});
    const columns = methodColumns(labels);
    const rows: Line[] = answer === null ? [] : answer.rows.map((row) => ({...row, id: row.method.id}));

    /**
     * One column's width, drawn at once; null restores its default.
     *
     * @param {string} key The column key.
     * @param {number|null} width Pixels, or null.
     * @returns {void}
     */
    const onWidth = (key: string, width: number | null): void => {
        const next = {...widths};
        if (width === null) {
            delete next[key];
        } else {
            next[key] = width;
        }
        setWidths(next);
    };

    return (
        <>
            <RowsRegion
                labels={labels}
                loading={props.loading}
                loaded={answer !== null}
                error={props.error}
                total={answer === null ? 0 : answer.total}
                filtered={props.filtered}
                cards={narrow}
                narrow={narrow}
                skeleton={{columns, lead: 'course', widths, sizing: SIZING}}
                onRetry={props.onRetry}
            >
                {narrow ? <MethodCards labels={labels} columns={columns} rows={rows} single={narrow} /> : (
                    <DataGrid<Line>
                        labels={labels}
                        columns={columns}
                        lead="course"
                        rows={rows}
                        widths={widths}
                        sizing={SIZING}
                        sort={answer?.sort ?? ''}
                        direction={answer?.direction ?? 'desc'}
                        renderLead={(row) => <CourseCell labels={labels} row={row} />}
                        renderCell={(row, column) => <MethodCell labels={labels} row={row} column={column.key} />}
                        onSort={props.onSort}
                        onWidth={onWidth}
                        onWidthCommit={() => undefined}
                    />
                )}
            </RowsRegion>
            {answer !== null && answer.total > 0 ? (
                <Pager
                    labels={labels}
                    page={answer.page}
                    perpage={answer.perpage}
                    perpages={props.perpages}
                    total={answer.total}
                    shown={answer.rows.length}
                    narrow={narrow}
                    onPage={props.onPage}
                    onPerpage={props.onPerpage}
                />
            ) : null}
        </>
    );
};
