var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
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
import { useCallback, useEffect, useRef, useState } from "react";
import { rememberView, storedView, useNarrow } from "../datareport/layout";
import { Pager } from "../datareport/Pager";
import { hold } from "../datareport/pending";
import { RowsRegion } from "../datareport/Rows";
import { Band } from "./Band";
import { Bar } from "./Bar";
import { Cards } from "./Cards";
import { SIZING } from "./cells";
import { withChoice, without } from "./filters";
import { Grid } from "./Grid";
import { getRows, getSummary, resetFilters, setFilters, setPreference } from "./repository";
const SEARCH_DELAY = 300;
const WIDTH_DELAY = 500;
const VIEW_KEY = "enrol_apply_report_view";
const SKELETON_COLUMNS = ["applicant", "outcome", "submitted", "wait", "decided", "group"];
const ReportView = /* @__PURE__ */ __name((props) => {
  const { labels, enrolid, reportid } = props;
  const root = useRef(null);
  const narrow = useNarrow(root);
  const [view, setViewState] = useState(() => storedView(VIEW_KEY));
  const initial = {
    measure: props.measure,
    scope: "all",
    search: "",
    page: 0,
    perpage: props.perpage,
    sort: "",
    direction: "desc"
  };
  const query = useRef(initial);
  const [current, setCurrent] = useState(initial);
  const [searchtext, setSearchtext] = useState("");
  const [rows, setRows] = useState(null);
  const [rowsloading, setRowsloading] = useState(true);
  const [rowserror, setRowserror] = useState(false);
  const [summary, setSummary] = useState(null);
  const [bandloading, setBandloading] = useState(true);
  const [banderror, setBanderror] = useState(false);
  const [writing, setWriting] = useState(false);
  const writes = useRef(0);
  const [widths, setWidths] = useState({});
  const [counted, setCounted] = useState({});
  const dimmed = /* @__PURE__ */ __name((loading) => loading || writing, "dimmed");
  const rowsseq = useRef(0);
  const bandseq = useRef(0);
  const rowshold = useRef(null);
  const bandhold = useRef(null);
  const searchtimer = useRef(null);
  const searchhold = useRef(null);
  const widthsread = useRef(false);
  const widthtimer = useRef(null);
  const widthhold = useRef(null);
  const pendingwidths = useRef(null);
  const flushref = useRef(() => void 0);
  const loadRows = useCallback((asked, facets) => {
    const seq = ++rowsseq.current;
    if (!rowshold.current) {
      rowshold.current = hold("enrol_apply/report:rows");
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
        query.current = { ...query.current, page: answer.page };
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
  const loadBand = useCallback((asked) => {
    const seq = ++bandseq.current;
    if (!bandhold.current) {
      bandhold.current = hold("enrol_apply/report:band");
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
  const run = useCallback((change, readrows, readband) => {
    query.current = { ...query.current, ...change };
    setCurrent(query.current);
    if (readrows) {
      loadRows(query.current, readband);
    }
    if (readband) {
      loadBand(query.current);
    }
  }, [loadRows, loadBand]);
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
  useEffect(() => {
    loadRows(query.current, true);
    loadBand(query.current);
  }, [loadRows, loadBand]);
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
    const applied = trimmed.length >= props.searchmin ? trimmed : "";
    if (applied === query.current.search) {
      searchhold.current?.();
      searchhold.current = null;
      return;
    }
    if (text === "") {
      searchhold.current?.();
      searchhold.current = null;
      run({ search: "", page: 0 }, true, true);
      return;
    }
    if (!searchhold.current) {
      searchhold.current = hold("enrol_apply/report:search");
    }
    searchtimer.current = window.setTimeout(() => {
      searchtimer.current = null;
      run({ search: applied, page: 0 }, true, true);
      searchhold.current?.();
      searchhold.current = null;
    }, SEARCH_DELAY);
  }, "onSearch");
  const writeFilters = /* @__PURE__ */ __name((write) => {
    const release = hold("enrol_apply/report:filters");
    writes.current += 1;
    setWriting(true);
    write().then(() => {
      run({ page: 0 }, true, true);
      return;
    }).catch(() => {
      setRowserror(true);
    }).finally(() => {
      writes.current -= 1;
      setWriting(writes.current > 0);
      release();
    });
  }, "writeFilters");
  const filters = (rows?.filters ?? []).map((filter) => counted[filter.name] ? { ...filter, options: counted[filter.name].options, groupings: counted[filter.name].groupings } : filter);
  const writePreference = /* @__PURE__ */ __name((preference, reread) => {
    const release = hold("enrol_apply/report:preference");
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
      widthhold.current = hold("enrol_apply/report:width");
    }
    widthtimer.current = window.setTimeout(() => flushref.current(), WIDTH_DELAY);
  }, "onWidth");
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
  const onSort = /* @__PURE__ */ __name((key) => {
    const sorted = rows?.sort ?? "";
    const direction = sorted === key && rows?.direction === "asc" ? "desc" : "asc";
    run({ sort: key, direction, page: 0 }, true, false);
  }, "onSort");
  const onMeasure = /* @__PURE__ */ __name((measure) => {
    const q = query.current;
    const waitshown = allcolumns.some((column) => column.key === "wait" && column.active);
    run({ measure }, q.scope !== "all" || q.sort === "wait" || waitshown, true);
  }, "onMeasure");
  const filtered = filters.some((filter) => filter.applied) || current.search !== "" || current.scope !== "all";
  const identity = allcolumns.filter((column) => column.key.startsWith("identity:"));
  const shown = allcolumns.filter((column) => column.key === "applicant" || !column.fixed && column.active);
  const cards = view === "cards" || view === "auto" && narrow;
  let body = null;
  if (rows !== null) {
    body = cards ? /* @__PURE__ */ jsxDEV(Cards, { labels, columns: shown, identity, rows: rows.rows, single: narrow }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
      lineNumber: 489,
      columnNumber: 13
    }) : /* @__PURE__ */ jsxDEV(
      Grid,
      {
        labels,
        columns: shown,
        identity,
        rows: rows.rows,
        widths,
        sort: rows.sort,
        direction: rows.direction,
        onSort,
        onWidth,
        onWidthCommit: () => flushref.current()
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
        lineNumber: 491,
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
  return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-root", ref: root, "data-region": "enrol_apply-reportview", children: [
    rows?.narrowed ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-narrowed", children: labels.narrowed }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
      lineNumber: 516,
      columnNumber: 31
    }) : null,
    /* @__PURE__ */ jsxDEV(
      Band,
      {
        labels,
        summary,
        loading: dimmed(bandloading),
        error: banderror,
        compactHint: rows?.narrowed ?? false,
        measure: current.measure,
        methods: props.methods,
        queueurl: props.queueurl,
        pendingstatus: props.pendingstatus,
        onMeasure,
        onRetry: () => loadBand(query.current)
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
        lineNumber: 517,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV(
      Bar,
      {
        labels,
        narrow,
        scope: current.scope,
        total: rows === null ? null : rows.total,
        filters,
        columns: allcolumns.filter((column) => !column.key.startsWith("identity:") || column.active),
        presets: rows?.presets ?? [],
        usedefault,
        haswidths: Object.keys(widths).length > 0,
        view,
        measure: current.measure,
        search: searchtext,
        appliedsearch: current.search,
        searchmin: props.searchmin,
        searching: rowsloading,
        tableurl: props.tableurl,
        downloadurl: props.downloadurl,
        formats: props.formats,
        onScope: (scope) => run({ scope, page: 0 }, true, true),
        onSearch,
        onFilter: (filter, value) => writeFilters(() => setFilters(
          reportid,
          query.current.measure,
          withChoice(filters, filter, value)
        )),
        onRemoveFilter: (filter) => writeFilters(() => setFilters(
          reportid,
          query.current.measure,
          without(filters, filter)
        )),
        onClearFilters: () => writeFilters(() => resetFilters(reportid, query.current.measure)),
        onClearAll: () => {
          setSearchtext("");
          query.current = { ...query.current, search: "" };
          if (filters.some((filter) => filter.applied)) {
            writeFilters(() => resetFilters(reportid, query.current.measure));
          } else {
            run({ page: 0 }, true, true);
          }
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
        fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
        lineNumber: 530,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV(
      RowsRegion,
      {
        labels,
        loading: dimmed(rowsloading),
        loaded: rows !== null,
        error: rowserror,
        total: rows?.total ?? 0,
        filtered,
        cards,
        narrow,
        skeleton: { columns: skeleton, lead: "applicant", widths, sizing: SIZING },
        onRetry: () => loadRows(query.current, true),
        children: body
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
        lineNumber: 579,
        columnNumber: 13
      }
    ),
    rows !== null && rows.total > 0 ? /* @__PURE__ */ jsxDEV(
      Pager,
      {
        labels,
        page: current.page,
        perpage: current.perpage,
        perpages: props.perpages,
        total: rows.total,
        shown: rows.rows.length,
        narrow,
        onPage: (page) => run({ page }, true, false),
        onPerpage: (perpage) => run({ perpage, page: 0 }, true, false)
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
        lineNumber: 594,
        columnNumber: 17
      }
    ) : null,
    props.retention !== "" ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-retention", "data-region": "enrol_apply-retention", children: [
      props.retention,
      props.retentionurl !== "" ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
        " ",
        /* @__PURE__ */ jsxDEV("a", { href: props.retentionurl, children: labels.retentionchange }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
          lineNumber: 609,
          columnNumber: 57
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
        lineNumber: 609,
        columnNumber: 50
      }) : null
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
      lineNumber: 607,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
    lineNumber: 515,
    columnNumber: 9
  });
}, "ReportView");
var ReportView_default = ReportView;
export {
  ReportView_default as default
};
//# sourceMappingURL=ReportView.dev.js.map
