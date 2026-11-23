var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The approval queue of one method as a data report, read only (stage 3 of
 * docs/design/confirm-queue-as-report-plan.md).
 *
 * manage.php?id=<method>&view=grid renders the shell (enrol_apply\output\queue_view) with configuration
 * and strings; the rows and the tiles come from the plugin's web services, which read the queue's own
 * table (classes/local/queueengine.php), so access, the separate-groups narrowing, the masking of the
 * answers and the filters are the table page's. Two regions load on their own:
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
 * Nothing here decides: no selection, no decisions (stage 4). Each row links to its review page.
 *
 * @module     enrol_apply/queue/QueueView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useCallback, useEffect, useRef, useState } from "react";
import { Cards as DataCards } from "../datareport/Cards";
import { Grid as DataGrid } from "../datareport/Grid";
import { rememberView, storedView, useNarrow } from "../datareport/layout";
import { Pager } from "../datareport/Pager";
import { hold } from "../datareport/pending";
import { RowsRegion } from "../datareport/Rows";
import { initialQuery, queryOf, queueUrl, writeAddress } from "./address";
import { ANY_GROUP, Bar, GROUP } from "./Bar";
import { ApplicantCell, CellLine, SIZING, cellHtml } from "./cells";
import { getRows, getSummary, setPreference } from "./repository";
import { Tiles } from "./Tiles";
const SEARCH_DELAY = 300;
const WIDTH_DELAY = 500;
const VIEW_KEY = "enrol_apply_queue_view";
const SKELETON_COLUMNS = ["applicant", "wait", "group", "comment"];
const CARD_FIXED = ["applicant", "wait", "group"];
const narrows = /* @__PURE__ */ __name((query) => query.scope !== "all" || query.search !== "" || Object.entries(query.filters).some(([name, value]) => value !== "" && !(name === GROUP && value === ANY_GROUP)), "narrows");
const cardFields = /* @__PURE__ */ __name((shown, row) => shown.filter((column) => !CARD_FIXED.includes(column.key) && cellHtml(row, column.key).trim() !== "").map((column) => ({ key: column.key, title: column.title, content: /* @__PURE__ */ jsxDEV(CellLine, { html: cellHtml(row, column.key) }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
  lineNumber: 94,
  columnNumber: 71
}) })), "cardFields");
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
  const loadRows = useCallback((asked) => {
    const seq = ++rowsseq.current;
    if (!rowshold.current) {
      rowshold.current = hold("enrol_apply/queue:rows");
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
        query.current = { ...query.current, page: answer.page };
        setCurrent(query.current);
        writeAddress(config, query.current, false);
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
  }, [enrolid, config]);
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
    writeAddress(config, query.current, push);
    loadRows(query.current);
  }, [loadRows, config]);
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
    loadRows(query.current);
    loadSummary();
  }, [loadRows, loadSummary, config]);
  useEffect(() => {
    const popped = /* @__PURE__ */ __name((event) => {
      const restored = queryOf(event.state);
      if (restored === null) {
        return;
      }
      query.current = restored;
      setCurrent(restored);
      setSearchtext(restored.search);
      loadRows(restored);
    }, "popped");
    window.addEventListener("popstate", popped);
    return () => window.removeEventListener("popstate", popped);
  }, [loadRows]);
  useEffect(() => () => {
    if (searchtimer.current !== null) {
      window.clearTimeout(searchtimer.current);
    }
    searchhold.current?.();
    flushref.current();
  }, []);
  const setView = /* @__PURE__ */ __name((chosen) => {
    setViewState(chosen);
    rememberView(VIEW_KEY, chosen);
  }, "setView");
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
  const groupdefault = rows?.groupdefault ?? "";
  const onFilter = /* @__PURE__ */ __name((name, value) => {
    const filters = { ...query.current.filters };
    if (value !== "") {
      filters[name] = value;
    } else if (name === GROUP && groupdefault !== "") {
      filters[GROUP] = ANY_GROUP;
    } else {
      delete filters[name];
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
  const allcolumns = rows?.columns ?? [];
  const chosenkeys = allcolumns.filter((column) => !column.fixed && column.active).map((column) => column.key);
  const usedefault = rows?.preference.usedefault ?? true;
  const widthList = /* @__PURE__ */ __name((map) => Object.entries(map).map(([key, width]) => ({ key, width })), "widthList");
  const onColumn = /* @__PURE__ */ __name((key, on) => {
    const next = on ? [...chosenkeys, key] : chosenkeys.filter((chosen) => chosen !== key);
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
  const has = /* @__PURE__ */ __name((key) => shown.some((column) => column.key === key), "has");
  const cards = view === "cards" || view === "auto" && narrow;
  const list = (rows?.rows ?? []).map((row) => ({ ...row, id: row.userenrolmentid }));
  let body = null;
  if (rows !== null) {
    body = cards ? /* @__PURE__ */ jsxDEV(
      DataCards,
      {
        labels,
        rows: list,
        single: narrow,
        renderCard: (row) => ({
          head: /* @__PURE__ */ jsxDEV(ApplicantCell, { labels, row }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
            lineNumber: 504,
            columnNumber: 27
          }),
          lines: /* @__PURE__ */ jsxDEV(Fragment, { children: [
            has("wait") ? /* @__PURE__ */ jsxDEV(
              "div",
              {
                className: "enrol_apply-rview-card-line enrol_apply-rview-card-line--open",
                dangerouslySetInnerHTML: { __html: cellHtml(row, "wait") }
              },
              void 0,
              false,
              {
                fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
                lineNumber: 508,
                columnNumber: 33
              }
            ) : null,
            has("group") && cellHtml(row, "group") !== "" ? /* @__PURE__ */ jsxDEV(
              "div",
              {
                className: "enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",
                dangerouslySetInnerHTML: { __html: cellHtml(row, "group") }
              },
              void 0,
              false,
              {
                fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
                lineNumber: 514,
                columnNumber: 33
              }
            ) : null
          ] }, void 0, true, {
            fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
            lineNumber: 506,
            columnNumber: 25
          }),
          fields: cardFields(shown, row)
        })
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
        lineNumber: 499,
        columnNumber: 13
      }
    ) : /* @__PURE__ */ jsxDEV(
      DataGrid,
      {
        labels,
        columns: shown,
        lead: "applicant",
        rows: list,
        widths,
        sizing: SIZING,
        sort: rows.sort,
        direction: rows.direction,
        renderLead: (row) => /* @__PURE__ */ jsxDEV(ApplicantCell, { labels, row }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
          lineNumber: 534,
          columnNumber: 38
        }),
        renderCell: (row, column) => /* @__PURE__ */ jsxDEV(CellLine, { html: cellHtml(row, column.key) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
          lineNumber: 535,
          columnNumber: 46
        }),
        onSort,
        onWidth,
        onWidthCommit: () => flushref.current()
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
        lineNumber: 525,
        columnNumber: 13
      }
    );
  }
  const skeleton = SKELETON_COLUMNS.map((key) => ({
    key,
    title: key === "applicant" ? labels.applicant : "",
    sortable: false,
    "default": true,
    active: true,
    fixed: key === "applicant"
  }));
  return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-root", ref: root, "data-region": "enrol_apply-queueview", children: [
    /* @__PURE__ */ jsxDEV(Tiles, { labels, summary, error: summaryerror, onRetry: loadSummary }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
      lineNumber: 553,
      columnNumber: 13
    }),
    rows?.notingroup ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-narrowed", children: labels.notingroup }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
      lineNumber: 554,
      columnNumber: 33
    }) : /* @__PURE__ */ jsxDEV(Fragment, { children: [
      rows?.narrowed ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-narrowed", children: labels.narrowed }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
        lineNumber: 556,
        columnNumber: 39
      }) : null,
      /* @__PURE__ */ jsxDEV(
        Bar,
        {
          labels,
          narrow,
          query: current,
          rows,
          columns: allcolumns,
          presets: rows?.presets ?? [],
          usedefault,
          haswidths: Object.keys(widths).length > 0,
          view,
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
          onView: setView
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
          lineNumber: 557,
          columnNumber: 21
        }
      ),
      /* @__PURE__ */ jsxDEV(
        RowsRegion,
        {
          labels,
          loading: rowsloading,
          loaded: rows !== null,
          error: rowserror,
          total: rows?.total ?? 0,
          filtered: narrows(current),
          cards,
          narrow,
          skeleton: { columns: skeleton, lead: "applicant", widths, sizing: SIZING },
          onRetry: () => loadRows(query.current),
          children: body
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
          lineNumber: 594,
          columnNumber: 21
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
          lineNumber: 609,
          columnNumber: 25
        }
      ) : null
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
      lineNumber: 555,
      columnNumber: 17
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/QueueView.tsx",
    lineNumber: 552,
    columnNumber: 9
  });
}, "QueueView");
var QueueView_default = QueueView;
export {
  QueueView_default as default
};
//# sourceMappingURL=QueueView.dev.js.map
