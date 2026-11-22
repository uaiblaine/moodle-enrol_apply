var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The bounded grid (section 5b of the study): a real table in a frame that scrolls both ways.
 *
 * table-layout fixed with a col per column, so the widths are exact and the browser does not measure
 * every cell; the heading row sticks to the top of the frame and the Applicant column to its left,
 * with a shadow on its edge once the frame has scrolled sideways. Sortable headings are buttons, the
 * order in aria-sort. Each heading's right edge is a separator that resizes its column: a drag changes
 * the col element alone and is saved once, on release, in the reader's preference (AR-D13); the arrow
 * keys move it by 16 px, saved once too, after the last key or when the handle loses the focus; a
 * double click restores the default.
 *
 * @module     enrol_apply/report/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useRef } from "react";
import { CellLines, IdentityLines, MAXIMUM, ReviewButton, cellHtml, defaultWidth, minimumWidth, widthOf } from "./cells";
import { fill } from "./str";
const STEP = 16;
const ARROWS = { ascending: "\u25B2", descending: "\u25BC", none: "" };
const ariaSort = /* @__PURE__ */ __name((sorted, direction) => {
  if (!sorted) {
    return "none";
  }
  return direction === "asc" ? "ascending" : "descending";
}, "ariaSort");
const WaitBar = /* @__PURE__ */ __name(({ row, longest }) => {
  if (!row.open && !row.decidedbyperson) {
    return null;
  }
  return /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-waitbar" + (row.open ? " enrol_apply-rview-waitbar--open" : ""), "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV("span", { style: { width: `${Math.min(100, Math.round(row.wait / longest * 100))}%` } }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
    lineNumber: 85,
    columnNumber: 13
  }) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
    lineNumber: 84,
    columnNumber: 9
  });
}, "WaitBar");
const Grid = /* @__PURE__ */ __name(({
  labels,
  columns,
  identity,
  rows,
  widths,
  sort,
  direction,
  onSort,
  onWidth,
  onWidthCommit
}) => {
  const frame = useRef(null);
  const table = useRef(null);
  const drag = useRef(null);
  const total = columns.reduce((sum, column) => sum + widthOf(widths, column.key), 0);
  const longest = Math.max(86400, ...rows.filter((row) => row.open || row.decidedbyperson).map((row) => row.wait));
  const scrolled = /* @__PURE__ */ __name((event) => {
    event.currentTarget.classList.toggle("is-scrolled", event.currentTarget.scrollLeft > 0);
  }, "scrolled");
  const paint = /* @__PURE__ */ __name((key, width) => {
    const col = table.current?.querySelector(`col[data-col="${CSS.escape(key)}"]`);
    if (!col || !table.current) {
      return;
    }
    col.style.width = `${width}px`;
    table.current.style.width = `${total - widthOf(widths, key) + width}px`;
  }, "paint");
  const bound = /* @__PURE__ */ __name((key, width) => Math.round(Math.min(MAXIMUM, Math.max(minimumWidth(key), width))), "bound");
  const down = /* @__PURE__ */ __name((event, key) => {
    if (event.button !== 0) {
      return;
    }
    event.preventDefault();
    event.currentTarget.setPointerCapture(event.pointerId);
    event.currentTarget.classList.add("is-active");
    const width = widthOf(widths, key);
    drag.current = { key, x: event.clientX, width, now: width };
  }, "down");
  const move = /* @__PURE__ */ __name((event) => {
    const current = drag.current;
    if (!current) {
      return;
    }
    const rtl = document.documentElement.dir === "rtl";
    const delta = (event.clientX - current.x) * (rtl ? -1 : 1);
    const width = bound(current.key, current.width + delta);
    if (width !== current.now) {
      paint(current.key, width);
      current.now = width;
    }
  }, "move");
  const up = /* @__PURE__ */ __name((event) => {
    const current = drag.current;
    drag.current = null;
    event.currentTarget.classList.remove("is-active");
    if (current && current.now !== current.width) {
      onWidth(current.key, current.now);
    }
  }, "up");
  const keydown = /* @__PURE__ */ __name((event, key) => {
    const rtl = document.documentElement.dir === "rtl";
    let step = 0;
    if (event.key === "ArrowRight") {
      step = rtl ? -STEP : STEP;
    } else if (event.key === "ArrowLeft") {
      step = rtl ? STEP : -STEP;
    } else if (event.key === "Home") {
      event.preventDefault();
      onWidth(key, null, true);
      return;
    }
    if (step === 0) {
      return;
    }
    event.preventDefault();
    const width = bound(key, widthOf(widths, key) + step);
    if (width !== widthOf(widths, key)) {
      onWidth(key, width, true);
    }
  }, "keydown");
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      className: "enrol_apply-rview-gridframe",
      ref: frame,
      tabIndex: 0,
      role: "region",
      "aria-label": labels.grid,
      onScroll: scrolled,
      children: /* @__PURE__ */ jsxDEV("table", { className: "enrol_apply-rview-grid", ref: table, style: { width: `${total}px` }, children: [
        /* @__PURE__ */ jsxDEV("caption", { className: "visually-hidden", children: labels.caption }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
          lineNumber: 236,
          columnNumber: 17
        }),
        /* @__PURE__ */ jsxDEV("colgroup", { children: columns.map((column) => /* @__PURE__ */ jsxDEV("col", { "data-col": column.key, style: { width: `${widthOf(widths, column.key)}px` } }, column.key, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
          lineNumber: 239,
          columnNumber: 25
        })) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
          lineNumber: 237,
          columnNumber: 17
        }),
        /* @__PURE__ */ jsxDEV("thead", { children: /* @__PURE__ */ jsxDEV("tr", { children: columns.map((column) => {
          const order = ariaSort(sort === column.key, direction);
          const title = /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-th-title", children: column.title }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
            lineNumber: 246,
            columnNumber: 43
          });
          return /* @__PURE__ */ jsxDEV(
            "th",
            {
              scope: "col",
              className: column.key === "applicant" ? "enrol_apply-rview-fixed" : void 0,
              "aria-sort": column.sortable ? order : void 0,
              title: column.title,
              "data-column": column.key,
              children: [
                column.sortable ? /* @__PURE__ */ jsxDEV(
                  "button",
                  {
                    type: "button",
                    className: "enrol_apply-rview-sortbtn",
                    onClick: () => onSort(column.key),
                    children: [
                      title,
                      /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-arrow", "aria-hidden": "true", children: ARROWS[order] }, void 0, false, {
                        fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
                        lineNumber: 263,
                        columnNumber: 45
                      })
                    ]
                  },
                  void 0,
                  true,
                  {
                    fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
                    lineNumber: 257,
                    columnNumber: 41
                  }
                ) : /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-plain", children: title }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
                  lineNumber: 267,
                  columnNumber: 41
                }),
                /* @__PURE__ */ jsxDEV(
                  "span",
                  {
                    className: "enrol_apply-rview-resize",
                    role: "separator",
                    "aria-orientation": "vertical",
                    "aria-label": fill(labels.resize, column.title),
                    "aria-valuemin": minimumWidth(column.key),
                    "aria-valuemax": MAXIMUM,
                    "aria-valuenow": widthOf(widths, column.key),
                    tabIndex: 0,
                    onPointerDown: (event) => down(event, column.key),
                    onPointerMove: move,
                    onPointerUp: up,
                    onPointerCancel: up,
                    onDoubleClick: () => {
                      if (widthOf(widths, column.key) !== defaultWidth(column.key)) {
                        onWidth(column.key, null);
                      }
                    },
                    onKeyDown: (event) => keydown(event, column.key),
                    onBlur: onWidthCommit
                  },
                  void 0,
                  false,
                  {
                    fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
                    lineNumber: 268,
                    columnNumber: 37
                  }
                )
              ]
            },
            column.key,
            true,
            {
              fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
              lineNumber: 248,
              columnNumber: 33
            }
          );
        }) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
          lineNumber: 243,
          columnNumber: 21
        }) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
          lineNumber: 242,
          columnNumber: 17
        }),
        /* @__PURE__ */ jsxDEV("tbody", { children: rows.map((row) => /* @__PURE__ */ jsxDEV("tr", { "data-record": row.id, children: columns.map((column) => column.key === "applicant" ? /* @__PURE__ */ jsxDEV("td", { className: "enrol_apply-rview-fixed", children: /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-applicant", children: [
          /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-applicant-text", children: [
            /* @__PURE__ */ jsxDEV(CellLines, { column: column.key, html: cellHtml(row, column.key) }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
              lineNumber: 302,
              columnNumber: 49
            }),
            /* @__PURE__ */ jsxDEV(IdentityLines, { row, identity }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
              lineNumber: 303,
              columnNumber: 49
            })
          ] }, void 0, true, {
            fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
            lineNumber: 301,
            columnNumber: 45
          }),
          /* @__PURE__ */ jsxDEV(ReviewButton, { row, label: labels.review }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
            lineNumber: 305,
            columnNumber: 45
          })
        ] }, void 0, true, {
          fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
          lineNumber: 300,
          columnNumber: 41
        }) }, column.key, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
          lineNumber: 299,
          columnNumber: 37
        }) : /* @__PURE__ */ jsxDEV("td", { children: [
          /* @__PURE__ */ jsxDEV(CellLines, { column: column.key, html: cellHtml(row, column.key) }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
            lineNumber: 310,
            columnNumber: 41
          }),
          column.key === "wait" ? /* @__PURE__ */ jsxDEV(WaitBar, { row, longest }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
            lineNumber: 311,
            columnNumber: 66
          }) : null
        ] }, column.key, true, {
          fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
          lineNumber: 309,
          columnNumber: 37
        })) }, row.id, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
          lineNumber: 296,
          columnNumber: 25
        })) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
          lineNumber: 294,
          columnNumber: 17
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
        lineNumber: 235,
        columnNumber: 13
      })
    },
    void 0,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
      lineNumber: 227,
      columnNumber: 9
    }
  );
}, "Grid");
const GridSkeleton = /* @__PURE__ */ __name(({ columns, widths }) => {
  const total = columns.reduce((sum, column) => sum + widthOf(widths, column.key), 0);
  const shapes = ["enrol_apply-rview-skb--w80", "enrol_apply-rview-skb--w60", "enrol_apply-rview-skb--w40"];
  return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-gridframe", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV("table", { className: "enrol_apply-rview-grid", style: { width: `${total}px` }, children: [
    /* @__PURE__ */ jsxDEV("colgroup", { children: columns.map((column) => /* @__PURE__ */ jsxDEV("col", { style: { width: `${widthOf(widths, column.key)}px` } }, column.key, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
      lineNumber: 337,
      columnNumber: 46
    })) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
      lineNumber: 336,
      columnNumber: 17
    }),
    /* @__PURE__ */ jsxDEV("thead", { children: /* @__PURE__ */ jsxDEV("tr", { children: columns.map((column) => /* @__PURE__ */ jsxDEV("th", { className: column.key === "applicant" ? "enrol_apply-rview-fixed" : void 0, children: /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-plain", children: /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-th-title", children: column.title }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
      lineNumber: 344,
      columnNumber: 37
    }) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
      lineNumber: 343,
      columnNumber: 33
    }) }, column.key, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
      lineNumber: 342,
      columnNumber: 29
    })) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
      lineNumber: 340,
      columnNumber: 21
    }) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
      lineNumber: 339,
      columnNumber: 17
    }),
    /* @__PURE__ */ jsxDEV("tbody", { children: Array.from({ length: 8 }, (_, i) => /* @__PURE__ */ jsxDEV("tr", { className: "enrol_apply-rview-sk", children: columns.map((column, j) => /* @__PURE__ */ jsxDEV("td", { className: column.key === "applicant" ? "enrol_apply-rview-fixed" : void 0, children: /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb " + shapes[(i + j) % 3] }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
      lineNumber: 355,
      columnNumber: 37
    }) }, column.key, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
      lineNumber: 354,
      columnNumber: 33
    })) }, i, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
      lineNumber: 352,
      columnNumber: 25
    })) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
      lineNumber: 350,
      columnNumber: 17
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
    lineNumber: 335,
    columnNumber: 13
  }) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
    lineNumber: 334,
    columnNumber: 9
  });
}, "GridSkeleton");
export {
  Grid,
  GridSkeleton
};
//# sourceMappingURL=Grid.dev.js.map
