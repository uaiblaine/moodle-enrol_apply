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
 * The course report of applications, drawn in React (stage R4 of docs/design/audit-report-study.md).
 *
 * report.php renders the shell (enrol_apply\output\report_view) with configuration and strings; the
 * rows and the summary come from the plugin's web services, which read the system report headlessly,
 * so access, the separate-groups narrowing, the masking, the reader's stored filters and their chosen
 * columns are the report's, as on core's table. Three regions load on their own:
 *
 * - the rows (enrol_apply_report_rows): a sort or a page reloads them alone;
 * - the band (enrol_apply_report_summary): the measure reloads it, and the rows where they show it;
 * - the grid (enrol_apply_set_report_preference): a column change is written, and the rows are read
 *   again only once the write has landed, because the report reads the columns in initialise().
 *
 * A filter, the scope or the search reloads both. One request is in flight per region: core's ajax
 * cannot abort, so each region numbers its requests and drops any answer but the newest. The first
 * load draws skeletons with aria-busy; later ones keep the old rows, dimmed, under a 2 px line.
 *
 * @module     enrol_apply/report/ReportView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useRef, useState} from 'react';
import type {ReactElement} from 'react';
import {Band} from './Band';
import {Bar} from './Bar';
import {Cards, CardsSkeleton} from './Cards';
import {withChoice, without} from './filters';
import {Grid, GridSkeleton} from './Grid';
import {Pager} from './Pager';
import {hold} from './pending';
import {getRows, getSummary, resetFilters, setFilters, setPreference} from './repository';
import type {Column, Filter, Measure, Preference, RowsAnswer, RowsQuery, Scope, SummaryAnswer, ViewMode, ViewProps} from './types';

/** Below this container width, in pixels, Automatic draws cards (AR-D9; stylelint refuses a container query). */
const NARROW = 576;

/** The wait before the search is sent, after the last key (section 5b). */
const SEARCH_DELAY = 300;

/** The browser key the reader's view choice is remembered under: a convenience of this browser alone. */
const VIEW_KEY = 'enrol_apply_report_view';

/** The columns the first skeleton draws, before the report has said which it shows. */
const SKELETON_COLUMNS = ['applicant', 'outcome', 'submitted', 'wait', 'decided', 'group'];

/**
 * The view the reader chose last in this browser.
 *
 * @returns {string} auto, table or cards.
 */
const storedView = (): ViewMode => {
    try {
        const value = window.localStorage.getItem(VIEW_KEY);
        return value === 'table' || value === 'cards' ? value : 'auto';
    } catch {
        return 'auto';
    }
};

type RowsContentProps = {
    labels: Record<string, string>,
    rows: RowsAnswer | null,
    error: boolean,
    cards: boolean,
    narrow: boolean,
    filtered: boolean,
    columns: Column[],
    identity: Column[],
    widths: Record<string, number>,
    onRetry: () => void,
    onSort: (key: string) => void,
    onWidth: (key: string, width: number | null) => void,
};

/**
 * What the rows region holds: the first load's skeleton, an error with a retry, the empty state, or the rows.
 *
 * @param {object} props See RowsContentProps.
 * @returns {object} The region's content.
 */
const RowsContent = ({labels, rows, error, cards, narrow, filtered, columns, identity, widths, onRetry, onSort,
        onWidth}: RowsContentProps): ReactElement => {
    const retry = (
        <p className="enrol_apply-rview-error">
            {labels.loaderror}{' '}
            <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onRetry}>{labels.retry}</button>
        </p>
    );
    if (rows === null && error) {
        return retry;
    }
    if (rows === null) {
        const skeleton: Column[] = SKELETON_COLUMNS.map((key) => ({
            key,
            title: key === 'applicant' ? labels.applicant : '',
            sortable: false,
            "default": true,
            active: true,
            fixed: key === 'applicant',
        }));
        return (
            <>
                {cards ? <CardsSkeleton single={narrow} /> : <GridSkeleton columns={skeleton} widths={widths} />}
                <span className="visually-hidden" role="status">{labels.loading}</span>
            </>
        );
    }
    if (rows.total === 0) {
        return (
            <div className="enrol_apply-rview-empty">
                <p className="enrol_apply-rview-empty-title">{filtered ? labels.nomatch : labels.empty}</p>
                {filtered ? <p className="enrol_apply-rview-empty-text">{labels.nomatchhelp}</p> : null}
            </div>
        );
    }

    return (
        <div className="enrol_apply-rview-dim">
            {error ? retry : null}
            {cards ? (
                <Cards labels={labels} columns={columns} identity={identity} rows={rows.rows} single={narrow} />
            ) : (
                <>
                    <Grid
                        labels={labels}
                        columns={columns}
                        identity={identity}
                        rows={rows.rows}
                        widths={widths}
                        sort={rows.sort}
                        direction={rows.direction}
                        onSort={onSort}
                        onWidth={onWidth}
                    />
                    <p className="enrol_apply-rview-hint">{labels.scrollhint}</p>
                </>
            )}
        </div>
    );
};

/**
 * The report's view.
 *
 * @param {object} props The props report_view::props() wrote; see ViewProps.
 * @returns {object} The view.
 */
const ReportView = (props: ViewProps): ReactElement => {
    const {labels, enrolid, reportid} = props;
    const root = useRef<HTMLDivElement | null>(null);
    const [narrow, setNarrow] = useState(false);
    const [view, setViewState] = useState<ViewMode>(storedView);

    const initial: RowsQuery = {
        measure: props.measure,
        scope: 'all',
        search: '',
        page: 0,
        perpage: props.perpage,
        sort: '',
        direction: 'desc',
    };
    const query = useRef<RowsQuery>(initial);
    const [current, setCurrent] = useState<RowsQuery>(initial);
    const [searchtext, setSearchtext] = useState('');

    const [rows, setRows] = useState<RowsAnswer | null>(null);
    const [rowsloading, setRowsloading] = useState(true);
    const [rowserror, setRowserror] = useState(false);
    const [summary, setSummary] = useState<SummaryAnswer | null>(null);
    const [bandloading, setBandloading] = useState(true);
    const [banderror, setBanderror] = useState(false);
    const [widths, setWidths] = useState<Record<string, number>>({});

    const rowsseq = useRef(0);
    const bandseq = useRef(0);
    const rowshold = useRef<(() => void) | null>(null);
    const bandhold = useRef<(() => void) | null>(null);
    const searchtimer = useRef<number | null>(null);
    const searchhold = useRef<(() => void) | null>(null);
    const widthsread = useRef(false);

    /**
     * Read a page of rows; an answer to an older request than the newest is dropped.
     *
     * @param {object} asked What to read.
     * @returns {void}
     */
    const loadRows = useCallback((asked: RowsQuery): void => {
        const seq = ++rowsseq.current;
        if (!rowshold.current) {
            rowshold.current = hold('enrol_apply/report:rows');
        }
        setRowsloading(true);
        getRows(enrolid, asked).then((answer) => {
            if (seq !== rowsseq.current) {
                return;
            }
            if (!widthsread.current) {
                widthsread.current = true;
                setWidths(Object.fromEntries(answer.preference.widths.map((entry) => [entry.key, entry.width])));
            }
            if (answer.page !== query.current.page) {
                // Past the last page (a filter shrank the rows): the server answered the last one.
                query.current = {...query.current, page: answer.page};
                setCurrent(query.current);
            }
            setRows(answer);
            setRowserror(false);
            setRowsloading(false);
            return;
        }).catch(() => {
            if (seq === rowsseq.current) {
                setRowserror(true);
                setRowsloading(false);
            }
        });
    }, [enrolid]);

    /**
     * Read the summary of the same rows; an answer to an older request than the newest is dropped.
     *
     * @param {object} asked The measure, the scope and the search to summarise.
     * @returns {void}
     */
    const loadBand = useCallback((asked: RowsQuery): void => {
        const seq = ++bandseq.current;
        if (!bandhold.current) {
            bandhold.current = hold('enrol_apply/report:band');
        }
        setBandloading(true);
        getSummary(enrolid, asked.measure, asked.scope, asked.search).then((answer) => {
            if (seq === bandseq.current) {
                setSummary(answer);
                setBanderror(false);
                setBandloading(false);
            }
            return;
        }).catch(() => {
            if (seq === bandseq.current) {
                setBanderror(true);
                setBandloading(false);
            }
        });
    }, [enrolid]);

    /**
     * Change what is asked for and read the regions it moves.
     *
     * @param {object} change The parts of the query that change.
     * @param {boolean} readrows Whether the rows move.
     * @param {boolean} readband Whether the band moves.
     * @returns {void}
     */
    const run = useCallback((change: Partial<RowsQuery>, readrows: boolean, readband: boolean): void => {
        query.current = {...query.current, ...change};
        setCurrent(query.current);
        if (readrows) {
            loadRows(query.current);
        }
        if (readband) {
            loadBand(query.current);
        }
    }, [loadRows, loadBand]);

    // Behat waits on these holds: each is released once the answer it waited for has been drawn.
    useEffect(() => {
        if (!rowsloading && rowshold.current) {
            rowshold.current();
            rowshold.current = null;
        }
    }, [rowsloading, rows, rowserror]);
    useEffect(() => {
        if (!bandloading && bandhold.current) {
            bandhold.current();
            bandhold.current = null;
        }
    }, [bandloading, summary, banderror]);

    // The first load: both regions, in parallel, each in a request of its own.
    useEffect(() => {
        loadRows(query.current);
        loadBand(query.current);
    }, [loadRows, loadBand]);

    // Automatic is cards under a 576 px container: the report's own width, not the window's.
    useEffect(() => {
        const element = root.current;
        if (!element || typeof ResizeObserver === 'undefined') {
            return undefined;
        }
        const observer = new ResizeObserver((entries) => {
            const width = entries[0]?.contentRect.width ?? element.clientWidth;
            setNarrow(width > 0 && width < NARROW);
        });
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    // A pending search is dropped with the component, and its hold released.
    useEffect(() => () => {
        if (searchtimer.current !== null) {
            window.clearTimeout(searchtimer.current);
        }
        searchhold.current?.();
    }, []);

    /**
     * Remember the view and draw it.
     *
     * @param {string} chosen auto, table or cards.
     * @returns {void}
     */
    const setView = (chosen: ViewMode): void => {
        setViewState(chosen);
        try {
            window.localStorage.setItem(VIEW_KEY, chosen);
        } catch {
            // A browser that keeps nothing keeps the choice for this page only.
        }
    };

    /**
     * The search as typed: sent 300 ms after the last key, from the minimum length; cleared at once.
     *
     * @param {string} text What the field holds.
     * @returns {void}
     */
    const onSearch = (text: string): void => {
        setSearchtext(text);
        if (searchtimer.current !== null) {
            window.clearTimeout(searchtimer.current);
            searchtimer.current = null;
        }
        const trimmed = text.trim();
        const applied = trimmed.length >= props.searchmin ? trimmed : '';
        if (applied === query.current.search) {
            searchhold.current?.();
            searchhold.current = null;
            return;
        }
        if (text === '') {
            searchhold.current?.();
            searchhold.current = null;
            run({search: '', page: 0}, true, true);
            return;
        }
        if (!searchhold.current) {
            searchhold.current = hold('enrol_apply/report:search');
        }
        searchtimer.current = window.setTimeout(() => {
            searchtimer.current = null;
            run({search: applied, page: 0}, true, true);
            searchhold.current?.();
            searchhold.current = null;
        }, SEARCH_DELAY);
    };

    /**
     * Store a new set of filter values in core's store, then read both regions again.
     *
     * @param {Function} write The write: core's set or reset.
     * @returns {void}
     */
    const writeFilters = (write: () => Promise<boolean>): void => {
        const release = hold('enrol_apply/report:filters');
        write().then(() => {
            run({page: 0}, true, true);
            return;
        }).catch(() => {
            setRowserror(true);
        }).finally(release);
    };

    const filters: Filter[] = rows?.filters ?? [];

    /**
     * Store the reader's grid; read the rows again afterwards when the columns changed.
     *
     * @param {object} preference The whole grid.
     * @param {boolean} reread Whether the rows depend on it (a column change).
     * @returns {void}
     */
    const writePreference = (preference: Preference, reread: boolean): void => {
        const release = hold('enrol_apply/report:preference');
        setPreference(enrolid, preference).then(() => {
            if (reread) {
                loadRows(query.current);
            }
            return;
        }).catch(() => {
            if (reread) {
                setRowserror(true);
            }
        }).finally(release);
    };

    const allcolumns: Column[] = rows?.columns ?? [];
    const chosenkeys = allcolumns.filter((column) => !column.fixed && column.active).map((column) => column.key);
    const usedefault = rows?.preference.usedefault ?? true;

    /**
     * The widths as the preference stores them.
     *
     * @param {object} map The widths by key.
     * @returns {Array} Each: key, width.
     */
    const widthList = (map: Record<string, number>): {key: string, width: number}[] =>
        Object.entries(map).map(([key, width]) => ({key, width}));

    /**
     * Show or hide one column: the choice is written, then the rows are read with it.
     *
     * @param {string} key The column key.
     * @param {boolean} on Whether to show it.
     * @returns {void}
     */
    const onColumn = (key: string, on: boolean): void => {
        const next = on ? [...chosenkeys, key] : chosenkeys.filter((chosen) => chosen !== key);
        if (rows) {
            // The menu answers at once; the grid follows when the rows come back with the column.
            setRows({
                ...rows,
                columns: rows.columns.map((column) => (column.key === key ? {...column, active: on} : column)),
                preference: {...rows.preference, usedefault: false, columns: next},
            });
        }
        writePreference({usedefault: false, columns: next, widths: widthList(widths)}, true);
    };

    /**
     * One column's width: written once, on release; null restores its default.
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
        writePreference({usedefault, columns: usedefault ? [] : chosenkeys, widths: widthList(next)}, false);
    };

    /**
     * Sort by a column: a second press on the sorted one turns the order round.
     *
     * @param {string} key The column key.
     * @returns {void}
     */
    const onSort = (key: string): void => {
        const sorted = rows?.sort ?? '';
        const direction = sorted === key && rows?.direction === 'asc' ? 'desc' : 'asc';
        run({sort: key, direction, page: 0}, true, false);
    };

    /**
     * Measure the wait to the first response or to the resolution (AR-D4).
     *
     * The band moves; the rows move too where they show or select by the wait, which is almost
     * always, because the wait column's cells are the server's words for the measure.
     *
     * @param {string} measure The measure.
     * @returns {void}
     */
    const onMeasure = (measure: Measure): void => {
        const q = query.current;
        const waitshown = allcolumns.some((column) => column.key === 'wait' && column.active);
        run({measure}, q.scope !== 'all' || q.sort === 'wait' || waitshown, true);
    };

    const filtered = filters.some((filter) => filter.applied) || current.search !== '' || current.scope !== 'all';
    const identity = allcolumns.filter((column) => column.key.startsWith('identity:'));
    const shown = allcolumns.filter((column) => column.key === 'applicant' || (!column.fixed && column.active));
    const cards = view === 'cards' || (view === 'auto' && narrow);

    return (
        <div className="enrol_apply-rview-root" ref={root} data-region="enrol_apply-reportview">
            {rows?.narrowed ? <p className="enrol_apply-rview-narrowed">{labels.narrowed}</p> : null}
            <Band
                labels={labels}
                summary={summary}
                loading={bandloading}
                error={banderror}
                compactHint={rows?.narrowed ?? false}
                measure={current.measure}
                methods={props.methods}
                queueurl={props.queueurl}
                pendingstatus={props.pendingstatus}
                onMeasure={onMeasure}
                onRetry={() => loadBand(query.current)}
            />
            <Bar
                labels={labels}
                narrow={narrow}
                scope={current.scope}
                total={rows === null ? null : rows.total}
                filters={filters}
                columns={allcolumns.filter((column) => !column.key.startsWith('identity:') || column.active)}
                usedefault={usedefault}
                haswidths={Object.keys(widths).length > 0}
                view={view}
                measure={current.measure}
                search={searchtext}
                searchmin={props.searchmin}
                searching={rowsloading}
                tableurl={props.tableurl}
                downloadurl={props.downloadurl}
                formats={props.formats}
                onScope={(scope: Scope) => run({scope, page: 0}, true, true)}
                onSearch={onSearch}
                onFilter={(filter, value) => writeFilters(() => setFilters(reportid, query.current.measure,
                    withChoice(filters, filter, value)))}
                onRemoveFilter={(filter) => writeFilters(() => setFilters(reportid, query.current.measure,
                    without(filters, filter)))}
                onClearFilters={() => writeFilters(() => resetFilters(reportid, query.current.measure))}
                onClearAll={() => {
                    setSearchtext('');
                    query.current = {...query.current, search: ''};
                    if (filters.some((filter) => filter.applied)) {
                        writeFilters(() => resetFilters(reportid, query.current.measure));
                    } else {
                        run({page: 0}, true, true);
                    }
                }}
                onColumn={onColumn}
                onDefaultColumns={() => {
                    if (rows) {
                        setRows({...rows, preference: {...rows.preference, usedefault: true, columns: []}});
                    }
                    writePreference({usedefault: true, columns: [], widths: widthList(widths)}, true);
                }}
                onResetWidths={() => {
                    setWidths({});
                    writePreference({usedefault, columns: usedefault ? [] : chosenkeys, widths: []}, false);
                }}
                onView={setView}
            />
            <section
                className="enrol_apply-rview-region"
                aria-label={labels.caption}
                aria-busy={rowsloading}
                data-region="enrol_apply-rows"
            >
                {rowsloading && rows !== null ? <div className="enrol_apply-rview-progress" /> : null}
                <RowsContent
                    labels={labels}
                    rows={rows}
                    error={rowserror}
                    cards={cards}
                    narrow={narrow}
                    filtered={filtered}
                    columns={shown}
                    identity={identity}
                    widths={widths}
                    onRetry={() => loadRows(query.current)}
                    onSort={onSort}
                    onWidth={onWidth}
                />
            </section>
            {rows !== null && rows.total > 0 ? (
                <Pager
                    labels={labels}
                    page={current.page}
                    perpage={current.perpage}
                    perpages={props.perpages}
                    total={rows.total}
                    shown={rows.rows.length}
                    narrow={narrow}
                    onPage={(page) => run({page}, true, false)}
                    onPerpage={(perpage) => run({perpage, page: 0}, true, false)}
                />
            ) : null}
        </div>
    );
};

export default ReportView;
