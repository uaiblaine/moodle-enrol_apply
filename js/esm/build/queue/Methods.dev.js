var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
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
import { useCallback, useEffect, useRef, useState } from "react";
import { Cards as DataCards } from "../datareport/Cards";
import { Grid as DataGrid } from "../datareport/Grid";
import { Pager } from "../datareport/Pager";
import { hold } from "../datareport/pending";
import { RowsRegion } from "../datareport/Rows";
import { makeSizing } from "../datareport/sizing";
import { duration, fill, fillObject, num } from "../datareport/str";
import { CourseMethod } from "./cells";
import { getMethods } from "./repository";
import { percent, shortDate } from "./Tiles";
const WIDTHS = {
  course: 300,
  awaiting: 170,
  deferred: 120,
  oldest: 150,
  places: 220,
  status: 190,
  queue: 170
};
const SIZING = makeSizing(WIDTHS, 150, { course: 160 }, 80, 800);
const FIRST = { course: "asc", awaiting: "desc", oldest: "asc" };
const methodColumns = /* @__PURE__ */ __name((labels) => [
  ["course", labels.coursemethod],
  ["awaiting", labels.awaiting],
  ["deferred", labels.deferred],
  ["oldest", labels.oldestwait],
  ["places", labels.placestaken],
  ["status", labels.status],
  ["queue", labels.queue]
].map(([key, title]) => ({
  key,
  title,
  sortable: key in FIRST,
  "default": true,
  active: true,
  fixed: key === "course"
})), "methodColumns");
const useMethods = /* @__PURE__ */ __name((onClamp) => {
  const [answer, setAnswer] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(false);
  const seq = useRef(0);
  const release = useRef(null);
  const clamp = useRef(onClamp);
  clamp.current = onClamp;
  const load = useCallback((asked) => {
    const mine = ++seq.current;
    if (!release.current) {
      release.current = hold("enrol_apply/queue:methods");
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
  useEffect(() => {
    if (!loading && release.current) {
      release.current();
      release.current = null;
    }
  }, [loading, answer, error]);
  return { answer, loading, error, load };
}, "useMethods");
const methodsDirection = /* @__PURE__ */ __name((answer, key) => {
  if (answer !== null && answer.sort === key) {
    return answer.direction === "asc" ? "desc" : "asc";
  }
  return FIRST[key] ?? "asc";
}, "methodsDirection");
const Places = /* @__PURE__ */ __name(({ labels, row }) => {
  if (!row.capacityknown) {
    return /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-c", children: [
      /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: "\u2013" }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 185,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: labels.capacityhidden }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 186,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
      lineNumber: 184,
      columnNumber: 13
    });
  }
  if (row.places <= 0) {
    return /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-c", children: fill(labels.takennolimit, num(row.placestaken)) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
      lineNumber: 191,
      columnNumber: 16
    });
  }
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-c", children: fillObject(labels.ofmany, { taken: num(row.placestaken), total: num(row.places) }) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
      lineNumber: 196,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "span",
      {
        className: "enrol_apply-qview-meter" + (row.placesfull ? " enrol_apply-qview-meter--warn" : ""),
        "aria-hidden": "true",
        children: /* @__PURE__ */ jsxDEV("span", { style: { width: `${percent(row.placestaken, row.places)}%` } }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
          lineNumber: 203,
          columnNumber: 17
        })
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 199,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
    lineNumber: 195,
    columnNumber: 9
  });
}, "Places");
const Status = /* @__PURE__ */ __name(({ labels, row }) => {
  if (!row.capacityknown) {
    return /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: labels.capacityhidden }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
      lineNumber: 219,
      columnNumber: 16
    });
  }
  if (row.placesfull || row.limitreached) {
    return /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-status enrol_apply-status--neutral", title: labels.methodfullwarn, children: [
      labels.methodfull,
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: " " + labels.methodfullwarn }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 225,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
      lineNumber: 223,
      columnNumber: 13
    });
  }
  if (!row.open) {
    return /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-c", children: labels.closed }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
      lineNumber: 230,
      columnNumber: 16
    });
  }
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-c", children: labels.open }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
      lineNumber: 235,
      columnNumber: 13
    }),
    row.closes > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-sub", children: fill(labels.closes, shortDate(row.closes)) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
      lineNumber: 237,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
    lineNumber: 234,
    columnNumber: 9
  });
}, "Status");
const OpenQueue = /* @__PURE__ */ __name(({ labels, row }) => row.method.queueurl === "" ? null : /* @__PURE__ */ jsxDEV("a", { className: "btn btn-sm btn-outline-secondary", href: row.method.queueurl, children: [
  labels.openqueue,
  /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: " " + row.method.name }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
    lineNumber: 253,
    columnNumber: 13
  })
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
  lineNumber: 251,
  columnNumber: 9
}), "OpenQueue");
const CourseCell = /* @__PURE__ */ __name(({ labels, row }) => /* @__PURE__ */ jsxDEV(CourseMethod, { labels, row: { course: row.course, method: { ...row.method, full: false } } }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
  lineNumber: 266,
  columnNumber: 5
}), "CourseCell");
const MethodCell = /* @__PURE__ */ __name(({ labels, row, column }) => {
  switch (column) {
    case "awaiting":
      return /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-qview-figure", "data-figure": "awaiting", children: num(row.awaiting) }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 279,
        columnNumber: 20
      });
    case "deferred":
      return /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-qview-figure", "data-figure": "deferred", children: num(row.deferred) }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 281,
        columnNumber: 20
      });
    case "oldest":
      return /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-c", children: duration(labels, row.oldestwait) }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 283,
        columnNumber: 20
      });
    case "places":
      return /* @__PURE__ */ jsxDEV(Places, { labels, row }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 285,
        columnNumber: 20
      });
    case "status":
      return /* @__PURE__ */ jsxDEV(Status, { labels, row }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 287,
        columnNumber: 20
      });
    default:
      return /* @__PURE__ */ jsxDEV(OpenQueue, { labels, row }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 289,
        columnNumber: 20
      });
  }
}, "MethodCell");
const MethodCards = /* @__PURE__ */ __name(({ labels, columns, rows, single }) => /* @__PURE__ */ jsxDEV(
  DataCards,
  {
    labels,
    rows,
    single,
    renderCard: (row) => ({
      head: /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-qview-methodhead", children: [
        /* @__PURE__ */ jsxDEV(CourseCell, { labels, row }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
          lineNumber: 309,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(OpenQueue, { labels, row }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
          lineNumber: 310,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 308,
        columnNumber: 17
      }),
      lines: /* @__PURE__ */ jsxDEV("dl", { className: "enrol_apply-rview-card-list", children: columns.filter((column) => !["course", "queue"].includes(column.key)).map((column) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-item", children: [
        /* @__PURE__ */ jsxDEV("dt", { children: column.title }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
          lineNumber: 317,
          columnNumber: 29
        }),
        /* @__PURE__ */ jsxDEV("dd", { children: /* @__PURE__ */ jsxDEV(MethodCell, { labels, row, column: column.key }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
          lineNumber: 318,
          columnNumber: 33
        }) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
          lineNumber: 318,
          columnNumber: 29
        })
      ] }, column.key, true, {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 316,
        columnNumber: 25
      })) }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 314,
        columnNumber: 17
      }),
      fields: []
    })
  },
  void 0,
  false,
  {
    fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
    lineNumber: 302,
    columnNumber: 5
  }
), "MethodCards");
const MethodsView = /* @__PURE__ */ __name((props) => {
  const { labels, answer, narrow } = props;
  const [widths, setWidths] = useState({});
  const columns = methodColumns(labels);
  const rows = answer === null ? [] : answer.rows.map((row) => ({ ...row, id: row.method.id }));
  const onWidth = /* @__PURE__ */ __name((key, width) => {
    const next = { ...widths };
    if (width === null) {
      delete next[key];
    } else {
      next[key] = width;
    }
    setWidths(next);
  }, "onWidth");
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV(
      RowsRegion,
      {
        labels,
        loading: props.loading,
        loaded: answer !== null,
        error: props.error,
        total: answer === null ? 0 : answer.total,
        filtered: props.filtered,
        cards: narrow,
        narrow,
        skeleton: { columns, lead: "course", widths, sizing: SIZING },
        onRetry: props.onRetry,
        children: narrow ? /* @__PURE__ */ jsxDEV(MethodCards, { labels, columns, rows, single: narrow }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
          lineNumber: 388,
          columnNumber: 27
        }) : /* @__PURE__ */ jsxDEV(
          DataGrid,
          {
            labels,
            columns,
            lead: "course",
            rows,
            widths,
            sizing: SIZING,
            sort: answer?.sort ?? "",
            direction: answer?.direction ?? "desc",
            renderLead: (row) => /* @__PURE__ */ jsxDEV(CourseCell, { labels, row }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
              lineNumber: 398,
              columnNumber: 46
            }),
            renderCell: (row, column) => /* @__PURE__ */ jsxDEV(MethodCell, { labels, row, column: column.key }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
              lineNumber: 399,
              columnNumber: 54
            }),
            onSort: props.onSort,
            onWidth,
            onWidthCommit: () => void 0
          },
          void 0,
          false,
          {
            fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
            lineNumber: 389,
            columnNumber: 21
          }
        )
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 376,
        columnNumber: 13
      }
    ),
    answer !== null && answer.total > 0 ? /* @__PURE__ */ jsxDEV(
      Pager,
      {
        labels,
        page: answer.page,
        perpage: answer.perpage,
        perpages: props.perpages,
        total: answer.total,
        shown: answer.rows.length,
        narrow,
        onPage: props.onPage,
        onPerpage: props.onPerpage
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
        lineNumber: 407,
        columnNumber: 17
      }
    ) : null
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/Methods.tsx",
    lineNumber: 375,
    columnNumber: 9
  });
}, "MethodsView");
export {
  MethodsView,
  methodsDirection,
  useMethods
};
//# sourceMappingURL=Methods.dev.js.map
