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
 * A filter, the scope or the search reloads both, and the rows then come with the filter panel's counts,
 * which a sort or a page turn cannot move and so does not ask for: the panel keeps the last counted
 * answer's options. A width set from the keyboard is saved once, 500 ms after the last key or when the
 * handle loses the focus, as a drag is saved once, on release. One request is in flight per region: core's ajax
 * cannot abort, so each region numbers its requests and drops any answer but the newest. The first
 * load draws skeletons with aria-busy; later ones keep the old rows, dimmed, under a 2 px line.
 *
 * The bar, the grid, the cards, the rows' region and the pager are the data report's shared parts
 * (js/esm/src/datareport), which Bar, Grid and Cards here give the report's vocabulary; the band, the
 * queries and the writes are the report's own.
 *
 * @module     enrol_apply/report/ReportView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useRef, useState} from 'react';
import type {ReactElement} from 'react';
import {rememberView, storedView, useNarrow} from '../datareport/layout';
import {Pager} from '../datareport/Pager';
import {hold} from '../datareport/pending';
import {RowsRegion} from '../datareport/Rows';
import {Band} from './Band';
import {Bar} from './Bar';
import {Cards} from './Cards';
import {SIZING} from './cells';
import {withChoice, without} from './filters';
import {Grid} from './Grid';
import {getRows, getSummary, resetFilters, setFilters, setPreference} from './repository';
import type {Column, Filter, Measure, Preference, Preset, RowsAnswer, RowsQuery, Scope, SummaryAnswer, ViewMode,
    ViewProps} from './types';

/** The wait before the search is sent, after the last key (section 5b). */
const SEARCH_DELAY = 300;

/** The wait before a width set from the keyboard is saved, after the last key. */
const WIDTH_DELAY = 500;

/** The browser key the reader's view choice is remembered under: a convenience of this browser alone. */
const VIEW_KEY = 'enrol_apply_report_view';

/** The columns the first skeleton draws, before the report has said which it shows. */
const SKELETON_COLUMNS = ['applicant', 'outcome', 'submitted', 'wait', 'decided', 'group'];

/**
 * The report's view.
 *
 * @param {object} props The props report_view::props() wrote; see ViewProps.
 * @returns {object} The view.
 */
const ReportView = (props: ViewProps): ReactElement => {
    const {labels, enrolid, reportid} = props;
    const root = useRef<HTMLDivElement | null>(null);
    // Automatic is cards under a 576 px container: the report's own width, not the window's.
    const narrow = useNarrow(root);
    const [view, setViewState] = useState<ViewMode>(() => storedView(VIEW_KEY));

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
    const [counted, setCounted] = useState<Record<string, Filter>>({});

    const rowsseq = useRef(0);
    const bandseq = useRef(0);
    const rowshold = useRef<(() => void) | null>(null);
    const bandhold = useRef<(() => void) | null>(null);
    const searchtimer = useRef<number | null>(null);
    const searchhold = useRef<(() => void) | null>(null);
    const widthsread = useRef(false);
    const widthtimer = useRef<number | null>(null);
    const widthhold = useRef<(() => void) | null>(null);
    const pendingwidths = useRef<Record<string, number> | null>(null);
    const flushref = useRef<() => void>(() => undefined);

    /**
     * Read a page of rows; an answer to an older request than the newest is dropped.
     *
     * @param {object} asked What to read.
     * @param {boolean} facets Whether the panel's counts can have moved, and so are asked for.
     * @returns {void}
     */
    const loadRows = useCallback((asked: RowsQuery, facets: boolean): void => {
        const seq = ++rowsseq.current;
        if (!rowshold.current) {
            rowshold.current = hold('enrol_apply/report:rows');
        }
        setRowsloading(true);
        getRows(enrolid, asked, facets).then((answer) => {
            if (seq !== rowsseq.current) {
                return;
            }
            if (answer.counted) {
                setCounted(Object.fromEntries(answer.filters.map((filter) => [filter.name, filter])));
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
            // The band moves exactly when what the panel counts moves: a filter, the scope, the search, the measure.
            loadRows(query.current, readband);
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
        loadRows(query.current, true);
        loadBand(query.current);
    }, [loadRows, loadBand]);

    // A pending search is dropped with the component, and its hold released; a pending width is saved.
    useEffect(() => () => {
        if (searchtimer.current !== null) {
            window.clearTimeout(searchtimer.current);
        }
        searchhold.current?.();
        flushref.current();
    }, []);

    /**
     * Remember the view and draw it.
     *
     * @param {string} chosen auto, table or cards.
     * @returns {void}
     */
    const setView = (chosen: ViewMode): void => {
        setViewState(chosen);
        rememberView(VIEW_KEY, chosen);
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

    // The panel draws each filter as the rows last counted it, its applied values as they are now.
    const filters: Filter[] = (rows?.filters ?? []).map((filter) => (counted[filter.name]
        ? {...filter, options: counted[filter.name].options, groupings: counted[filter.name].groupings}
        : filter));

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
                loadRows(query.current, false);
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
     * Save the widths waiting to be saved, if any: once, whatever number of steps led to them.
     *
     * @returns {void}
     */
    const flushWidths = (): void => {
        if (widthtimer.current !== null) {
            window.clearTimeout(widthtimer.current);
            widthtimer.current = null;
        }
        const next = pendingwidths.current;
        pendingwidths.current = null;
        if (next !== null) {
            writePreference({usedefault, columns: usedefault ? [] : chosenkeys, widths: widthList(next)}, false);
        }
        widthhold.current?.();
        widthhold.current = null;
    };
    // The timer and the unmount call the newest flush, which writes the columns as they are then.
    flushref.current = flushWidths;

    /**
     * One column's width, drawn at once; null restores its default. A drag's release and a double click
     * write it now; a key step (defer) waits for the last key, or for the handle to lose the focus.
     *
     * @param {string} key The column key.
     * @param {number|null} width Pixels, or null.
     * @param {boolean} defer Whether to wait for more steps before writing.
     * @returns {void}
     */
    const onWidth = (key: string, width: number | null, defer = false): void => {
        const next = {...widths};
        if (width === null) {
            delete next[key];
        } else {
            next[key] = width;
        }
        setWidths(next);
        pendingwidths.current = next;
        if (!defer) {
            flushWidths();
            return;
        }
        if (widthtimer.current !== null) {
            window.clearTimeout(widthtimer.current);
        }
        if (!widthhold.current) {
            widthhold.current = hold('enrol_apply/report:width');
        }
        widthtimer.current = window.setTimeout(() => flushref.current(), WIDTH_DELAY);
    };

    /**
     * Apply one of the Columns menu's sets: written as the reader's choice, then the rows are read with it.
     *
     * The menu answers at once with the set's columns as the catalogue offers them ("answers" being every
     * question); the server's answer then says which set is in force.
     *
     * @param {object} preset The set.
     * @returns {void}
     */
    const onPreset = (preset: Preset): void => {
        const named = new Set(preset.columns);
        if (rows) {
            setRows({
                ...rows,
                columns: rows.columns.map((column) => (column.fixed ? column : {
                    ...column,
                    active: named.has(column.key) || (named.has('answers') && column.key.startsWith('answer:')),
                })),
                presets: rows.presets.map((entry) => ({...entry, active: entry.name === preset.name})),
                preference: {...rows.preference, usedefault: false, columns: preset.columns},
            });
        }
        writePreference({usedefault: false, columns: preset.columns, widths: widthList(widths)}, true);
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
    // The rows as the reader views them; the region draws them only once an answer has some.
    let body: ReactElement | null = null;
    if (rows !== null) {
        body = cards ? (
            <Cards labels={labels} columns={shown} identity={identity} rows={rows.rows} single={narrow} />
        ) : (
            <Grid
                labels={labels}
                columns={shown}
                identity={identity}
                rows={rows.rows}
                widths={widths}
                sort={rows.sort}
                direction={rows.direction}
                onSort={onSort}
                onWidth={onWidth}
                onWidthCommit={() => flushref.current()}
            />
        );
    }
    const skeleton: Column[] = SKELETON_COLUMNS.map((key) => ({
        key,
        title: key === 'applicant' ? labels.applicant : '',
        sortable: false,
        "default": true,
        active: true,
        fixed: key === 'applicant',
    }));

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
                presets={rows?.presets ?? []}
                usedefault={usedefault}
                haswidths={Object.keys(widths).length > 0}
                view={view}
                measure={current.measure}
                search={searchtext}
                appliedsearch={current.search}
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
                onPreset={onPreset}
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
            <RowsRegion
                labels={labels}
                loading={rowsloading}
                loaded={rows !== null}
                error={rowserror}
                total={rows?.total ?? 0}
                filtered={filtered}
                cards={cards}
                narrow={narrow}
                skeleton={{columns: skeleton, lead: 'applicant', widths, sizing: SIZING}}
                onRetry={() => loadRows(query.current, true)}
            >
                {body}
            </RowsRegion>
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
            {props.retention !== '' ? (
                <p className="enrol_apply-rview-retention" data-region="enrol_apply-retention">
                    {props.retention}
                    {props.retentionurl !== '' ? <>{' '}<a href={props.retentionurl}>{labels.retentionchange}</a></> : null}
                </p>
            ) : null}
        </div>
    );
};

export default ReportView;
