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
 * The approval queue as a data report: the method queue's page (stages 3 and 4 of
 * docs/design/confirm-queue-as-report-plan.md, CQ-D5) and the site-wide queue's (stage W3 of
 * docs/design/site-queue-as-report-plan.md, SQ-D12).
 *
 * One view for both, branching on the listing's kind (the props for the first paint, the rows answer
 * after): the site-wide queue draws its own tiles (SQ-D7), a "Course · method" column (SQ-D2), the course,
 * category, method and grouped facets the service returns (choosing another course releases the method),
 * and a decision sheet that splits a selection spanning methods by method (SQ-D10). Nothing of the
 * "By course" view yet (stage W4).
 *
 * manage.php?id=<method>, and manage.php for a site-wide reader, render the shell
 * (enrol_apply\output\queue_view) with configuration and strings, unless view=table asks for today's
 * table; the rows and the tiles come from the plugin's web
 * services, which read the queue's own table (classes/local/queueengine.php), so access, the
 * separate-groups narrowing, the masking of the answers and the filters are the table page's. Two
 * regions load on their own:
 *
 * - the rows (enrol_apply_queue_rows): the scope, a filter, the search, a sort or a page reloads them,
 *   and they come with the panel's counts each time;
 * - the tiles (enrol_apply_queue_summary): the method's capacity and the reader's queue before any
 *   filter, so they load once.
 *
 * The scope, the filters, the search, the sort, the page and the page size live in the page's address
 * (queue/address.ts, CQ-D6), never in a preference; the columns and their widths are the reader's
 * preference (enrol_apply_set_queue_preference, CQ-D7), and a column change reads the rows again only
 * once the write has landed, because the server builds the columns from it. One request is in flight
 * per region: core's ajax cannot abort, so each region numbers its requests and drops any answer but the
 * newest. Every wait holds Behat's pending counter until the answer it waited for is drawn.
 *
 * Deciding (stage 4): a reader who may decide selects rows of the page - only the rows the rows service
 * marks selectable, never their own application - with a box per row and one for the page; the
 * selection is cleared whenever the page, the scope, a filter, the search or the sort changes, so no row
 * out of sight stays selected, and the dock's count says "on this page". The dock under the rows holds
 * the table's three decisions; each opens the decision sheet (queue/Sheet), whose commit submits a real
 * form to manage.php with the table form's fields (queue/decide, CQ-D4). The server decides nothing on
 * the strength of what this view selected or offered: decision::apply() checks every row again. A
 * viewer gets no box, no dock and no sheet. Each row links to its review page.
 *
 * @module     enrol_apply/queue/QueueView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useRef, useState} from 'react';
import type {ReactElement} from 'react';
import {rememberView, storedView, useNarrow} from '../datareport/layout';
import {Pager} from '../datareport/Pager';
import {hold} from '../datareport/pending';
import {RowsRegion} from '../datareport/Rows';
import {initialQuery, queryOf, queueUrl, writeAddress} from './address';
import {ANY_GROUP, Bar, GROUP} from './Bar';
import {SIZING} from './cells';
import {decide} from './decide';
import {chooseRows, derive} from './derive';
import {Notices, RowsBody, dockFor, sheetFor} from './Parts';
import {getRows, getSummary, setPreference} from './repository';
import type {SheetValues} from './Sheet';
import {Tiles} from './Tiles';
import type {
    Column, Decision, Kind, Preference, Preset, Query, RowsAnswer, Scope, SummaryAnswer, ViewMode, ViewProps,
} from './types';

/** The wait before the search is sent, after the last key. */
const SEARCH_DELAY = 300;

/** The wait before a width set from the keyboard is saved, after the last key. */
const WIDTH_DELAY = 500;

/** The browser key the reader's view choice is remembered under: a convenience of this browser alone. */
const VIEW_KEY = 'enrol_apply_queue_view';

/** The columns the first skeleton draws, by the listing's kind, before the server has said which it shows. */
const SKELETON_COLUMNS: Record<Kind, string[]> = {
    method: ['applicant', 'wait', 'group', 'comment'],
    site: ['applicant', 'course', 'wait', 'details'],
    mentees: ['applicant', 'course', 'wait', 'comment'],
};

/** The method filter's name: it narrows within the course filter's course, so another course releases it. */
const METHOD = 'method';

/** The course filter's name. */
const COURSE = 'course';

/**
 * Whether a query narrows the queue at all: a scope, a filter (every group excepted) or a search.
 *
 * @param {object} query The query.
 * @returns {boolean} True when the empty state should say "no match" rather than "nothing waits".
 */
const narrows = (query: Query): boolean => query.scope !== 'all' || query.search !== ''
    || Object.entries(query.filters).some(([name, value]) => value !== '' && !(name === GROUP && value === ANY_GROUP));

/**
 * The queue's view.
 *
 * @param {object} props The props queue_view::props() wrote; see ViewProps.
 * @returns {object} The view.
 */
const QueueView = (props: ViewProps): ReactElement => {
    // Mounted once by react_autoinit: the props never change, and the callbacks below read them from here.
    const config = useRef(props).current;
    const {labels, enrolid} = config;
    const root = useRef<HTMLDivElement | null>(null);
    // Automatic is cards under a 576 px container: the view's own width, not the window's.
    const narrow = useNarrow(root);
    const [view, setViewState] = useState<ViewMode>(() => storedView(VIEW_KEY));

    const [start] = useState<Query>(() => initialQuery(config));
    const query = useRef<Query>(start);
    const [current, setCurrent] = useState<Query>(start);
    const [searchtext, setSearchtext] = useState(start.search);

    const [rows, setRows] = useState<RowsAnswer | null>(null);
    const [rowsloading, setRowsloading] = useState(true);
    const [rowserror, setRowserror] = useState(false);
    const [summary, setSummary] = useState<SummaryAnswer | null>(null);
    const [summaryloading, setSummaryloading] = useState(true);
    const [summaryerror, setSummaryerror] = useState(false);
    const [widths, setWidths] = useState<Record<string, number>>({});

    const rowsseq = useRef(0);
    const summaryseq = useRef(0);
    const rowshold = useRef<(() => void) | null>(null);
    const summaryhold = useRef<(() => void) | null>(null);
    const searchtimer = useRef<number | null>(null);
    const searchhold = useRef<(() => void) | null>(null);
    const widthsread = useRef(false);
    const widthtimer = useRef<number | null>(null);
    const widthhold = useRef<(() => void) | null>(null);
    const pendingwidths = useRef<Record<string, number> | null>(null);
    const flushref = useRef<() => void>(() => undefined);

    // The rows selected on this page, by user enrolment id; the sheet's decision while it is open.
    const [selected, setSelected] = useState<ReadonlySet<number>>(() => new Set());
    const [sheet, setSheet] = useState<Decision | null>(null);
    const opener = useRef<HTMLButtonElement | null>(null);

    /**
     * Read a page of rows; an answer to an older request than the newest is dropped.
     *
     * @param {object} asked What to read.
     * @returns {void}
     */
    const loadRows = useCallback((asked: Query): void => {
        const seq = ++rowsseq.current;
        if (!rowshold.current) {
            rowshold.current = hold('enrol_apply/queue:rows');
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
                // Past the last page (a filter shrank the queue): the server answered the last one.
                query.current = {...query.current, page: answer.page};
                setCurrent(query.current);
                writeAddress(config, query.current, false);
            }
            setRows(answer);
            // Only rows of this answer that may be selected stay selected: none out of sight.
            setSelected((before) => {
                const open = new Set(answer.rows.filter((row) => row.selectable).map((row) => row.userenrolmentid));
                const kept = Array.from(before).filter((id) => open.has(id));

                return kept.length === before.size ? before : new Set(kept);
            });
            setRowserror(false);
            setRowsloading(false);
            return;
        }).catch(() => {
            if (seq === rowsseq.current) {
                setRowserror(true);
                setRowsloading(false);
            }
        });
    }, [enrolid, config]);

    /**
     * Read the tiles' figures; an answer to an older request than the newest is dropped.
     *
     * @returns {void}
     */
    const loadSummary = useCallback((): void => {
        const seq = ++summaryseq.current;
        if (!summaryhold.current) {
            summaryhold.current = hold('enrol_apply/queue:summary');
        }
        setSummaryloading(true);
        getSummary(enrolid).then((answer) => {
            if (seq === summaryseq.current) {
                setSummary(answer);
                setSummaryerror(false);
                setSummaryloading(false);
            }
            return;
        }).catch(() => {
            if (seq === summaryseq.current) {
                setSummaryerror(true);
                setSummaryloading(false);
            }
        });
    }, [enrolid]);

    /**
     * Change what is asked for: written into the address, then the rows are read with it.
     *
     * @param {object} change The parts of the query that change.
     * @param {boolean} push Whether the change is a step of its own in the browser's history.
     * @returns {void}
     */
    const run = useCallback((change: Partial<Query>, push: boolean): void => {
        query.current = {...query.current, ...change};
        setCurrent(query.current);
        // Another page, scope, filter, search or sort is another set of rows: the selection goes.
        setSelected(new Set());
        writeAddress(config, query.current, push);
        loadRows(query.current);
    }, [loadRows, config]);

    // Behat waits on these holds: each is released once the answer it waited for has been drawn.
    useEffect(() => {
        if (!rowsloading && rowshold.current) {
            rowshold.current();
            rowshold.current = null;
        }
    }, [rowsloading, rows, rowserror]);
    useEffect(() => {
        if (!summaryloading && summaryhold.current) {
            summaryhold.current();
            summaryhold.current = null;
        }
    }, [summaryloading, summary, summaryerror]);

    // The first load: the address says what the server read, then both regions, each in a request of its own.
    useEffect(() => {
        writeAddress(config, query.current, false);
        loadRows(query.current);
        loadSummary();
    }, [loadRows, loadSummary, config]);

    // The back and forward buttons: an entry this view wrote holds its query.
    useEffect(() => {
        /**
         * Read the rows of the query a history entry holds.
         *
         * @param {object} event The popstate event.
         * @returns {void}
         */
        const popped = (event: PopStateEvent): void => {
            const restored = queryOf(event.state);
            if (restored === null) {
                return;
            }
            query.current = restored;
            setCurrent(restored);
            setSearchtext(restored.search);
            setSelected(new Set());
            loadRows(restored);
        };
        window.addEventListener('popstate', popped);

        return () => window.removeEventListener('popstate', popped);
    }, [loadRows]);

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
     * It replaces the address rather than adding a step per key.
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
        const applied = trimmed.length >= config.searchmin ? trimmed : '';
        if (applied === query.current.search) {
            searchhold.current?.();
            searchhold.current = null;
            return;
        }
        if (text === '') {
            searchhold.current?.();
            searchhold.current = null;
            run({search: '', page: 0}, false);
            return;
        }
        if (!searchhold.current) {
            searchhold.current = hold('enrol_apply/queue:search');
        }
        searchtimer.current = window.setTimeout(() => {
            searchtimer.current = null;
            run({search: applied, page: 0}, false);
            searchhold.current?.();
            searchhold.current = null;
        }, SEARCH_DELAY);
    };

    const derived = derive(rows, config);
    const {groupdefault, allcolumns, usedefault, list, decides} = derived;

    /**
     * Set one filter, or release it with ''. A released group filter where the group filter has a
     * default reads "every group" explicitly, as the table page's chip writes it, so the default does not
     * come back on the next visit. Another course, or none, releases the method filter, which only ever
     * offers the chosen course's methods.
     *
     * @param {string} name The filter's name, as the address names it.
     * @param {string} value Its value, or ''.
     * @returns {void}
     */
    const onFilter = (name: string, value: string): void => {
        const filters = {...query.current.filters};
        if (value !== '') {
            filters[name] = value;
        } else if (name === GROUP && groupdefault !== '') {
            filters[GROUP] = ANY_GROUP;
        } else {
            delete filters[name];
        }
        if (name === COURSE) {
            delete filters[METHOD];
        }
        run({filters, page: 0}, true);
    };

    /**
     * Every filter released, the group one to "every group" where it has a default.
     *
     * @returns {object} The filters.
     */
    const noFilters = (): Record<string, string> => (groupdefault !== '' ? {[GROUP]: ANY_GROUP} : {});

    /**
     * Store the reader's grid; read the rows again afterwards when the columns changed.
     *
     * @param {object} preference The whole grid.
     * @param {boolean} reread Whether the rows depend on it (a column change).
     * @returns {void}
     */
    const writePreference = (preference: Preference, reread: boolean): void => {
        const release = hold('enrol_apply/queue:preference');
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

    const chosenkeys = allcolumns.filter((column) => !column.fixed && column.active).map((column) => column.key);

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
     * Apply one of the Columns menu's sets: written as the reader's choice, then the rows are read with it.
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
            widthhold.current = hold('enrol_apply/queue:width');
        }
        widthtimer.current = window.setTimeout(() => flushref.current(), WIDTH_DELAY);
    };

    /**
     * Sort by a column: a second press on the sorted one turns the order round.
     *
     * @param {string} key The column key.
     * @returns {void}
     */
    const onSort = (key: string): void => {
        const direction = rows !== null && rows.sort === key && rows.direction === 'asc' ? 'desc' : 'asc';
        run({sort: key, direction, page: 0}, true);
    };

    const shown = allcolumns.filter((column) => column.active);
    const cards = view === 'cards' || (view === 'auto' && narrow);

    // The focus goes back to the dock button that opened the sheet, once the sheet has closed.
    useEffect(() => {
        if (sheet === null && opener.current) {
            opener.current.focus();
            opener.current = null;
        }
    }, [sheet]);

    const offer = config.decision;
    const {chosen, selection} = chooseRows(selected, setSelected, derived, labels);

    /**
     * Open the sheet for one of the dock's decisions.
     *
     * @param {string} decision confirm, wait or cancel.
     * @param {object} button The dock button pressed, which takes the focus back when the sheet closes.
     * @returns {void}
     */
    const onDecide = (decision: Decision, button: HTMLButtonElement): void => {
        opener.current = button;
        setSheet(decision);
    };

    /**
     * Post the sheet's decision for the rows selected on this page, from the view's own address.
     *
     * @param {object} values The message, the note and the choosers' values.
     * @returns {void}
     */
    const onCommit = (values: SheetValues): void => {
        if (offer === null || sheet === null) {
            return;
        }
        decide({
            action: queueUrl(config, query.current, true),
            sesskey: offer.sesskey,
            decision: sheet,
            userenrolments: chosen.map((row) => row.id),
            ...values,
        });
    };

    // The rows as the reader views them; the region draws them only once an answer has some.
    const body = rows === null ? null : (
        <RowsBody
            labels={labels}
            list={list}
            shown={shown}
            cards={cards}
            narrow={narrow}
            widths={widths}
            sort={rows.sort}
            direction={rows.direction}
            selection={selection}
            onSort={onSort}
            onWidth={onWidth}
            onWidthCommit={() => flushref.current()}
        />
    );
    const skeleton: Column[] = SKELETON_COLUMNS[config.kind].map((key) => ({
        key,
        title: key === 'applicant' ? labels.applicant : '',
        sortable: false,
        "default": true,
        active: true,
        fixed: key === 'applicant',
    }));

    return (
        <div className="enrol_apply-rview-root" ref={root} data-region="enrol_apply-queueview">
            <Tiles labels={labels} kind={config.kind} summary={summary} error={summaryerror} onRetry={loadSummary} />
            {derived.notingroup ? <p className="enrol_apply-rview-narrowed">{labels.notingroup}</p> : (
                <>
                    <Notices labels={labels} narrowed={derived.narrowed} viewonly={derived.viewonly} />
                    <Bar
                        labels={labels}
                        narrow={narrow}
                        query={current}
                        rows={rows}
                        columns={allcolumns}
                        presets={derived.presets}
                        usedefault={usedefault}
                        haswidths={Object.keys(widths).length > 0}
                        view={view}
                        search={searchtext}
                        searchmin={config.searchmin}
                        searching={rowsloading}
                        tableurl={queueUrl(config, current, false)}
                        reporturl={config.reporturl}
                        onScope={(scope: Scope) => run({scope, page: 0}, true)}
                        onSearch={onSearch}
                        onFilter={onFilter}
                        onClearFilters={() => run({filters: noFilters(), page: 0}, true)}
                        onClearAll={() => {
                            setSearchtext('');
                            run({filters: noFilters(), search: '', page: 0}, true);
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
                        total={derived.total}
                        filtered={narrows(current)}
                        cards={cards}
                        narrow={narrow}
                        skeleton={{columns: skeleton, lead: 'applicant', widths, sizing: SIZING}}
                        dock={dockFor(labels, offer, decides, chosen, onDecide)}
                        onRetry={() => loadRows(query.current)}
                    >
                        {body}
                    </RowsRegion>
                    {rows !== null && rows.total > 0 ? (
                        <Pager
                            labels={labels}
                            page={current.page}
                            perpage={current.perpage}
                            perpages={config.perpages}
                            total={rows.total}
                            shown={rows.rows.length}
                            narrow={narrow}
                            onPage={(page) => run({page}, true)}
                            onPerpage={(perpage) => run({perpage, page: 0}, true)}
                        />
                    ) : null}
                </>
            )}
            {sheetFor(labels, offer, sheet, chosen, () => setSheet(null), onCommit)}
        </div>
    );
};

export default QueueView;
