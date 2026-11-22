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
 * A filter, the scope or the search reloads both. One request is in flight per region: core's ajax
 * cannot abort, so each region numbers its requests and drops any answer but the newest. The first
 * load draws skeletons with aria-busy; later ones keep the old rows, dimmed, under a 2 px line.
 *
 * @module     enrol_apply/report/ReportView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useCallback, useEffect, useRef, useState } from "react";
import { Band } from "./Band";
import { Bar } from "./Bar";
import { Cards, CardsSkeleton } from "./Cards";
import { withChoice, without } from "./filters";
import { Grid, GridSkeleton } from "./Grid";
import { Pager } from "./Pager";
import { hold } from "./pending";
import { getRows, getSummary, resetFilters, setFilters, setPreference } from "./repository";
const NARROW = 576;
const SEARCH_DELAY = 300;
const VIEW_KEY = "enrol_apply_report_view";
const SKELETON_COLUMNS = ["applicant", "outcome", "submitted", "wait", "decided", "group"];
const storedView = /* @__PURE__ */ __name(() => {
  try {
    const value = window.localStorage.getItem(VIEW_KEY);
    return value === "table" || value === "cards" ? value : "auto";
  } catch {
    return "auto";
  }
}, "storedView");
const RowsContent = /* @__PURE__ */ __name(({
  labels,
  rows,
  error,
  cards,
  narrow,
  filtered,
  columns,
  identity,
  widths,
  onRetry,
  onSort,
  onWidth
}) => {
  const retry = /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-error", children: [
    labels.loaderror,
    " ",
    /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: onRetry, children: labels.retry }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
      lineNumber: 102,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
    lineNumber: 100,
    columnNumber: 9
  });
  if (rows === null && error) {
    return retry;
  }
  if (rows === null) {
    const skeleton = SKELETON_COLUMNS.map((key) => ({
      key,
      title: key === "applicant" ? labels.applicant : "",
      sortable: false,
      "default": true,
      active: true,
      fixed: key === "applicant"
    }));
    return /* @__PURE__ */ jsxDEV(Fragment, { children: [
      cards ? /* @__PURE__ */ jsxDEV(CardsSkeleton, { single: narrow }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
        lineNumber: 119,
        columnNumber: 26
      }) : /* @__PURE__ */ jsxDEV(GridSkeleton, { columns: skeleton, widths }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
        lineNumber: 119,
        columnNumber: 62
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", role: "status", children: labels.loading }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
        lineNumber: 120,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
      lineNumber: 118,
      columnNumber: 13
    });
  }
  if (rows.total === 0) {
    return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-empty", children: [
      /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-empty-title", children: filtered ? labels.nomatch : labels.empty }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
        lineNumber: 127,
        columnNumber: 17
      }),
      filtered ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-empty-text", children: labels.nomatchhelp }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
        lineNumber: 128,
        columnNumber: 29
      }) : null
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
      lineNumber: 126,
      columnNumber: 13
    });
  }
  return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-dim", children: [
    error ? retry : null,
    cards ? /* @__PURE__ */ jsxDEV(Cards, { labels, columns, identity, rows: rows.rows, single: narrow }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
      lineNumber: 137,
      columnNumber: 17
    }) : /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV(
        Grid,
        {
          labels,
          columns,
          identity,
          rows: rows.rows,
          widths,
          sort: rows.sort,
          direction: rows.direction,
          onSort,
          onWidth
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
          lineNumber: 140,
          columnNumber: 21
        }
      ),
      /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-hint", children: labels.scrollhint }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
        lineNumber: 151,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
      lineNumber: 139,
      columnNumber: 17
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
    lineNumber: 134,
    columnNumber: 9
  });
}, "RowsContent");
const ReportView = /* @__PURE__ */ __name((props) => {
  const { labels, enrolid, reportid } = props;
  const root = useRef(null);
  const [narrow, setNarrow] = useState(false);
  const [view, setViewState] = useState(storedView);
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
  const [widths, setWidths] = useState({});
  const rowsseq = useRef(0);
  const bandseq = useRef(0);
  const rowshold = useRef(null);
  const bandhold = useRef(null);
  const searchtimer = useRef(null);
  const searchhold = useRef(null);
  const widthsread = useRef(false);
  const loadRows = useCallback((asked) => {
    const seq = ++rowsseq.current;
    if (!rowshold.current) {
      rowshold.current = hold("enrol_apply/report:rows");
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
      loadRows(query.current);
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
    loadRows(query.current);
    loadBand(query.current);
  }, [loadRows, loadBand]);
  useEffect(() => {
    const element = root.current;
    if (!element || typeof ResizeObserver === "undefined") {
      return void 0;
    }
    const observer = new ResizeObserver((entries) => {
      const width = entries[0]?.contentRect.width ?? element.clientWidth;
      setNarrow(width > 0 && width < NARROW);
    });
    observer.observe(element);
    return () => observer.disconnect();
  }, []);
  useEffect(() => () => {
    if (searchtimer.current !== null) {
      window.clearTimeout(searchtimer.current);
    }
    searchhold.current?.();
  }, []);
  const setView = /* @__PURE__ */ __name((chosen) => {
    setViewState(chosen);
    try {
      window.localStorage.setItem(VIEW_KEY, chosen);
    } catch {
    }
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
    write().then(() => {
      run({ page: 0 }, true, true);
      return;
    }).catch(() => {
      setRowserror(true);
    }).finally(release);
  }, "writeFilters");
  const filters = rows?.filters ?? [];
  const writePreference = /* @__PURE__ */ __name((preference, reread) => {
    const release = hold("enrol_apply/report:preference");
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
  const onWidth = /* @__PURE__ */ __name((key, width) => {
    const next = { ...widths };
    if (width === null) {
      delete next[key];
    } else {
      next[key] = width;
    }
    setWidths(next);
    writePreference({ usedefault, columns: usedefault ? [] : chosenkeys, widths: widthList(next) }, false);
  }, "onWidth");
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
  return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-root", ref: root, "data-region": "enrol_apply-reportview", children: [
    rows?.narrowed ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-narrowed", children: labels.narrowed }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
      lineNumber: 500,
      columnNumber: 31
    }) : null,
    /* @__PURE__ */ jsxDEV(
      Band,
      {
        labels,
        summary,
        loading: bandloading,
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
        lineNumber: 501,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV(
      Bar,
      {
        labels,
        scope: current.scope,
        total: rows === null ? null : rows.total,
        filters,
        columns: allcolumns.filter((column) => !column.key.startsWith("identity:") || column.active),
        usedefault,
        haswidths: Object.keys(widths).length > 0,
        view,
        measure: current.measure,
        search: searchtext,
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
        lineNumber: 514,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV(
      "section",
      {
        className: "enrol_apply-rview-region",
        "aria-label": labels.caption,
        "aria-busy": rowsloading,
        "data-region": "enrol_apply-rows",
        children: [
          rowsloading && rows !== null ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-progress" }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
            lineNumber: 565,
            columnNumber: 49
          }) : null,
          /* @__PURE__ */ jsxDEV(
            RowsContent,
            {
              labels,
              rows,
              error: rowserror,
              cards,
              narrow,
              filtered,
              columns: shown,
              identity,
              widths,
              onRetry: () => loadRows(query.current),
              onSort,
              onWidth
            },
            void 0,
            false,
            {
              fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
              lineNumber: 566,
              columnNumber: 17
            }
          )
        ]
      },
      void 0,
      true,
      {
        fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
        lineNumber: 559,
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
        lineNumber: 582,
        columnNumber: 17
      }
    ) : null
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/report/ReportView.tsx",
    lineNumber: 499,
    columnNumber: 9
  });
}, "ReportView");
var ReportView_default = ReportView;
export {
  ReportView_default as default
};
//# sourceMappingURL=ReportView.dev.js.map
