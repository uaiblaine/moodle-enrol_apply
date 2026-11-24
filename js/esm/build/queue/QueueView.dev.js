var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The approval queue as a data report: the method queue's page (stages 3 and 4 of
 * docs/design/confirm-queue-as-report-plan.md, CQ-D5) and the site-wide queue's (stage W3 of
 * docs/design/site-queue-as-report-plan.md, SQ-D12).
 *
 * One view for both, branching on the listing's kind (the props for the first paint, the rows answer
 * after): the site-wide queue draws its own tiles (SQ-D7), a "Course · method" column (SQ-D2), the course,
 * category, method and grouped facets the service returns (choosing another course releases the method),
 * and a decision sheet that splits a selection spanning methods by method (SQ-D10). The site-wide queue's
 * View menu adds "By course" (stage W4, SQ-D9, queue/Methods): one row per enrolment method of what the
 * list holds, read from enrol_apply_queue_methods under the same scope, filters and search, with no
 * selection, no dock and no Columns; the address says `show=methods`, and its sort and page are that view's.
 * The rows are still read while it is shown, at their first page, for the panel's facets and the chips.
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
import { useCallback, useEffect, useRef, useState } from "react";
import { rememberView, storedView, useNarrow } from "../datareport/layout";
import { Pager } from "../datareport/Pager";
import { hold } from "../datareport/pending";
import { RowsRegion } from "../datareport/Rows";
import { METHODS, initialQuery, queryOf, queueUrl, writeAddress } from "./address";
import { ANY_GROUP, Bar, GROUP } from "./Bar";
import { SIZING } from "./cells";
import { decide } from "./decide";
import { chooseRows, derive } from "./derive";
import { MethodsView, methodsDirection, useMethods } from "./Methods";
import { Notices, RowsBody, dockFor, sheetFor } from "./Parts";
import { getRows, getSummary, setPreference } from "./repository";
import { Tiles } from "./Tiles";
const SEARCH_DELAY = 300;
const WIDTH_DELAY = 500;
const VIEW_KEY = "enrol_apply_queue_view";
const SKELETON_COLUMNS = {
  method: ["applicant", "wait", "group", "comment"],
  site: ["applicant", "course", "wait", "details"],
  mentees: ["applicant", "course", "wait", "comment"]
};
const METHOD = "method";
const COURSE = "course";
const listQuery = /* @__PURE__ */ __name((asked, perpages) => asked.show === METHODS ? { ...asked, sort: "", direction: "asc", page: 0, perpage: perpages[0] } : asked, "listQuery");
const narrows = /* @__PURE__ */ __name((query) => query.scope !== "all" || query.search !== "" || Object.entries(query.filters).some(([name, value]) => value !== "" && !(name === GROUP && value === ANY_GROUP)), "narrows");
const QueueView = /* @__PURE__ */ __name((props) => {
  const config = useRef(props).current;
  const { labels, enrolid } = config;
  const root = useRef(null);
  const narrow = useNarrow(root);
  const [view, setViewState] = useState(() => storedView(VIEW_KEY));
  const [start] = useState(() => initialQuery(config));
  const query = useRef(start);
  const [current, setCurrent] = useState(start);
  const [searchtext, setSearchtext] = useState(start.search);
  const [rows, setRows] = useState(null);
  const [rowsloading, setRowsloading] = useState(true);
  const [rowserror, setRowserror] = useState(false);
  const [summary, setSummary] = useState(null);
  const [summaryloading, setSummaryloading] = useState(true);
  const [summaryerror, setSummaryerror] = useState(false);
  const [widths, setWidths] = useState({});
  const rowsseq = useRef(0);
  const summaryseq = useRef(0);
  const rowshold = useRef(null);
  const summaryhold = useRef(null);
  const searchtimer = useRef(null);
  const searchhold = useRef(null);
  const widthsread = useRef(false);
  const widthtimer = useRef(null);
  const widthhold = useRef(null);
  const pendingwidths = useRef(null);
  const flushref = useRef(() => void 0);
  const [selected, setSelected] = useState(() => /* @__PURE__ */ new Set());
  const [sheet, setSheet] = useState(null);
  const opener = useRef(null);
  const clampPage = useCallback((page) => {
    query.current = { ...query.current, page };
    setCurrent(query.current);
    writeAddress(config, query.current, false);
  }, [config]);
  const bycourse = useMethods(clampPage);
  const loadRows = useCallback((asked) => {
    const seq = ++rowsseq.current;
    if (!rowshold.current) {
      rowshold.current = hold("enrol_apply/queue:rows");
    }
    setRowsloading(true);
    getRows(enrolid, listQuery(asked, config.perpages)).then((answer) => {
      if (seq !== rowsseq.current) {
        return;
      }
      if (!widthsread.current) {
        widthsread.current = true;
        setWidths(Object.fromEntries(answer.preference.widths.map((entry) => [entry.key, entry.width])));
      }
      if (asked.show !== METHODS && answer.page !== query.current.page) {
        clampPage(answer.page);
      }
      setRows(answer);
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
  }, [enrolid, config, clampPage]);
  const loadMethods = bycourse.load;
  const reload = useCallback((asked) => {
    loadRows(asked);
    if (asked.show === METHODS) {
      loadMethods(asked);
    }
  }, [loadRows, loadMethods]);
  const loadSummary = useCallback(() => {
    const seq = ++summaryseq.current;
    if (!summaryhold.current) {
      summaryhold.current = hold("enrol_apply/queue:summary");
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
  const run = useCallback((change, push) => {
    query.current = { ...query.current, ...change };
    setCurrent(query.current);
    setSelected(/* @__PURE__ */ new Set());
    writeAddress(config, query.current, push);
    reload(query.current);
  }, [reload, config]);
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
  useEffect(() => {
    writeAddress(config, query.current, false);
    reload(query.current);
    loadSummary();
  }, [reload, loadSummary, config]);
  useEffect(() => {
    const popped = /* @__PURE__ */ __name((event) => {
      const restored = queryOf(event.state);
      if (restored === null) {
        return;
      }
      query.current = restored;
      setCurrent(restored);
      setSearchtext(restored.search);
      setSelected(/* @__PURE__ */ new Set());
      reload(restored);
    }, "popped");
    window.addEventListener("popstate", popped);
    return () => window.removeEventListener("popstate", popped);
  }, [reload]);
  useEffect(() => () => {
    if (searchtimer.current !== null) {
      window.clearTimeout(searchtimer.current);
    }
    searchhold.current?.();
    flushref.current();
  }, []);
  const setView = /* @__PURE__ */ __name((chosen2) => {
    setViewState(chosen2);
    rememberView(VIEW_KEY, chosen2);
  }, "setView");
  const showing = current.show === METHODS;
  const onView = /* @__PURE__ */ __name((chosen2) => {
    if (chosen2 === METHODS) {
      if (!showing) {
        run({ show: METHODS, sort: "", direction: "asc", page: 0 }, true);
      }
      return;
    }
    setView(chosen2);
    if (showing) {
      run({ show: "", sort: "", direction: "asc", page: 0 }, true);
    }
  }, "onView");
  const onSearch = /* @__PURE__ */ __name((text) => {
    setSearchtext(text);
    if (searchtimer.current !== null) {
      window.clearTimeout(searchtimer.current);
      searchtimer.current = null;
    }
    const trimmed = text.trim();
    const applied = trimmed.length >= config.searchmin ? trimmed : "";
    if (applied === query.current.search) {
      searchhold.current?.();
      searchhold.current = null;
      return;
    }
    if (text === "") {
      searchhold.current?.();
      searchhold.current = null;
      run({ search: "", page: 0 }, false);
      return;
    }
    if (!searchhold.current) {
      searchhold.current = hold("enrol_apply/queue:search");
    }
    searchtimer.current = window.setTimeout(() => {
      searchtimer.current = null;
      run({ search: applied, page: 0 }, false);
      searchhold.current?.();
      searchhold.current = null;
    }, SEARCH_DELAY);
  }, "onSearch");
  const derived = derive(rows, config);
  const { groupdefault, allcolumns, usedefault, list, decides } = derived;
  const onFilter = /* @__PURE__ */ __name((name, value) => {
    const filters = { ...query.current.filters };
    if (value !== "") {
      filters[name] = value;
    } else if (name === GROUP && groupdefault !== "") {
      filters[GROUP] = ANY_GROUP;
    } else {
      delete filters[name];
    }
    if (name === COURSE) {
      delete filters[METHOD];
    }
    run({ filters, page: 0 }, true);
  }, "onFilter");
  const noFilters = /* @__PURE__ */ __name(() => groupdefault !== "" ? { [GROUP]: ANY_GROUP } : {}, "noFilters");
  const writePreference = /* @__PURE__ */ __name((preference, reread) => {
    const release = hold("enrol_apply/queue:preference");
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
  }, "writePreference");
  const chosenkeys = allcolumns.filter((column) => !column.fixed && column.active).map((column) => column.key);
  const widthList = /* @__PURE__ */ __name((map) => Object.entries(map).map(([key, width]) => ({ key, width })), "widthList");
  const onColumn = /* @__PURE__ */ __name((key, on) => {
    const next = on ? [...chosenkeys, key] : chosenkeys.filter((chosen2) => chosen2 !== key);
    if (rows) {
      setRows({
        ...rows,
        columns: rows.columns.map((column) => column.key === key ? { ...column, active: on } : column),
        preference: { ...rows.preference, usedefault: false, columns: next }
      });
    }
    writePreference({ usedefault: false, columns: next, widths: widthList(widths) }, true);
  }, "onColumn");
  const onPreset = /* @__PURE__ */ __name((preset) => {
    const named = new Set(preset.columns);
    if (rows) {
      setRows({
        ...rows,
        columns: rows.columns.map((column) => column.fixed ? column : {
          ...column,
          active: named.has(column.key) || named.has("answers") && column.key.startsWith("answer:")
        }),
        presets: rows.presets.map((entry) => ({ ...entry, active: entry.name === preset.name })),
        preference: { ...rows.preference, usedefault: false, columns: preset.columns }
      });
    }
    writePreference({ usedefault: false, columns: preset.columns, widths: widthList(widths) }, true);
  }, "onPreset");
  const flushWidths = /* @__PURE__ */ __name(() => {
    if (widthtimer.current !== null) {
      window.clearTimeout(widthtimer.current);
      widthtimer.current = null;
    }
    const next = pendingwidths.current;
    pendingwidths.current = null;
    if (next !== null) {
      writePreference({ usedefault, columns: usedefault ? [] : chosenkeys, widths: widthList(next) }, false);
    }
    widthhold.current?.();
    widthhold.current = null;
  }, "flushWidths");
  flushref.current = flushWidths;
  const onWidth = /* @__PURE__ */ __name((key, width, defer = false) => {
    const next = { ...widths };
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
      widthhold.current = hold("enrol_apply/queue:width");
    }
    widthtimer.current = window.setTimeout(() => flushref.current(), WIDTH_DELAY);
  }, "onWidth");
  const onSort = /* @__PURE__ */ __name((key) => {
    const direction = rows !== null && rows.sort === key && rows.direction === "asc" ? "desc" : "asc";
    run({ sort: key, direction, page: 0 }, true);
  }, "onSort");
  const shown = allcolumns.filter((column) => column.active);
  const extraViews = config.methods === null ? [] : [{ value: METHODS, label: config.methods.label, icon: "queue" }];
  const methodlabels = { ...labels, ...config.methods ?? {} };
  const cards = view === "cards" || view === "auto" && narrow;
  useEffect(() => {
    if (sheet === null && opener.current) {
      opener.current.focus();
      opener.current = null;
    }
  }, [sheet]);
  const offer = config.decision;
  const { chosen, selection } = chooseRows(selected, setSelected, derived, labels);
  const onDecide = /* @__PURE__ */ __name((decision, button) => {
    opener.current = button;
    setSheet(decision);
  }, "onDecide");
  const onCommit = /* @__PURE__ */ __name((values) => {
    if (offer === null || sheet === null) {
      return;
    }
    decide({
      action: queueUrl(config, query.current, true),
      sesskey: offer.sesskey,
      decision: sheet,
      userenrolments: chosen.map((row) => row.id),
      ...values
    });
  }, "onCommit");
  const body = rows === null ? null : /* @__PURE__ */ jsxDEV(
    RowsBody,
    {
      labels,
      list,
      shown,
      cards,
      narrow,
      widths,
      sort: rows.sort,
      direction: rows.direction,
      selection,
      onSort,
      onWidth,
      onWidthCommit: () => flushref.current()
    },
    void 0,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
      lineNumber: 632,
      columnNumber: 9
    }
  );
  const skeleton = SKELETON_COLUMNS[config.kind].map((key) => ({
    key,
    title: key === "applicant" ? labels.applicant : "",
    sortable: false,
    "default": true,
    active: true,
    fixed: key === "applicant"
  }));
  const listbody = /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV(
      RowsRegion,
      {
        labels,
        loading: rowsloading,
        loaded: rows !== null,
        error: rowserror,
        total: derived.total,
        filtered: narrows(current),
        cards,
        narrow,
        skeleton: { columns: skeleton, lead: "applicant", widths, sizing: SIZING },
        dock: dockFor(labels, offer, decides, chosen, onDecide),
        onRetry: () => loadRows(query.current),
        children: body
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
        lineNumber: 659,
        columnNumber: 13
      }
    ),
    rows !== null && rows.total > 0 ? /* @__PURE__ */ jsxDEV(
      Pager,
      {
        labels,
        page: current.page,
        perpage: current.perpage,
        perpages: config.perpages,
        total: rows.total,
        shown: rows.rows.length,
        narrow,
        onPage: (page) => run({ page }, true),
        onPerpage: (perpage) => run({ perpage, page: 0 }, true)
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
        lineNumber: 675,
        columnNumber: 17
      }
    ) : null
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
    lineNumber: 658,
    columnNumber: 9
  });
  return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-root", ref: root, "data-region": "enrol_apply-queueview", children: [
    /* @__PURE__ */ jsxDEV(Tiles, { labels, kind: config.kind, summary, error: summaryerror, onRetry: loadSummary }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
      lineNumber: 692,
      columnNumber: 13
    }),
    derived.notingroup ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-narrowed", children: labels.notingroup }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
      lineNumber: 693,
      columnNumber: 35
    }) : /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV(Notices, { labels, narrowed: derived.narrowed, viewonly: derived.viewonly }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
        lineNumber: 695,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV(
        Bar,
        {
          labels,
          narrow,
          query: current,
          rows,
          columns: allcolumns,
          presets: derived.presets,
          usedefault,
          haswidths: Object.keys(widths).length > 0,
          view,
          extraViews,
          methods: showing ? bycourse.answer?.total ?? null : void 0,
          search: searchtext,
          searchmin: config.searchmin,
          searching: rowsloading,
          tableurl: queueUrl(config, current, false),
          reporturl: config.reporturl,
          onScope: (scope) => run({ scope, page: 0 }, true),
          onSearch,
          onFilter,
          onClearFilters: () => run({ filters: noFilters(), page: 0 }, true),
          onClearAll: () => {
            setSearchtext("");
            run({ filters: noFilters(), search: "", page: 0 }, true);
          },
          onColumn,
          onPreset,
          onDefaultColumns: () => {
            if (rows) {
              setRows({ ...rows, preference: { ...rows.preference, usedefault: true, columns: [] } });
            }
            writePreference({ usedefault: true, columns: [], widths: widthList(widths) }, true);
          },
          onResetWidths: () => {
            setWidths({});
            writePreference({ usedefault, columns: usedefault ? [] : chosenkeys, widths: [] }, false);
          },
          onView
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
          lineNumber: 696,
          columnNumber: 21
        }
      ),
      showing ? /* @__PURE__ */ jsxDEV(
        MethodsView,
        {
          labels: methodlabels,
          answer: bycourse.answer,
          loading: bycourse.loading,
          error: bycourse.error,
          filtered: narrows(current),
          narrow,
          perpages: config.perpages,
          onSort: (key) => run({ sort: key, direction: methodsDirection(bycourse.answer, key), page: 0 }, true),
          onPage: (page) => run({ page }, true),
          onPerpage: (perpage) => run({ perpage, page: 0 }, true),
          onRetry: () => loadMethods(query.current)
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
          lineNumber: 736,
          columnNumber: 25
        }
      ) : listbody
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
      lineNumber: 694,
      columnNumber: 17
    }),
    sheetFor(labels, offer, sheet, chosen, () => setSheet(null), onCommit)
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
    lineNumber: 691,
    columnNumber: 9
  });
}, "QueueView");
var QueueView_default = QueueView;
export {
  QueueView_default as default
};
//# sourceMappingURL=QueueView.dev.js.map
