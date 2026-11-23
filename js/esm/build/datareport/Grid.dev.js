var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The bounded grid (section 5b of the study): a real table in a frame that scrolls both ways.
 *
 * table-layout fixed with a col per column, so the widths are exact and the browser does not measure
 * every cell; the heading row sticks to the top of the frame and the lead column (the view's first,
 * named by `lead`) to its left, with a shadow on its edge once the frame has scrolled sideways.
 * Sortable headings are buttons, the order in aria-sort. Each heading's right edge is a separator that
 * resizes its column: a drag changes the col element alone and is saved once, on release, in the
 * reader's preference (AR-D13); the arrow keys move it by 16 px, saved once too, after the last key or
 * when the handle loses the focus; a double click restores the default.
 *
 * What a cell holds is the view's: `renderLead` draws the lead column's cell, `renderCell` every other.
 * A view that selects rows passes a selection, whose boxes lead the lead column's heading and cells,
 * each in a label of its own (visually hidden text) so it is named the way a form control is; a view
 * without one draws none.
 *
 * Strings read from the view's labels: grid, caption, resize.
 *
 * @module     enrol_apply/datareport/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useRef } from "react";
import { widthOf } from "./sizing";
import { fill } from "./str";
const STEP = 16;
const ARROWS = { ascending: "\u25B2", descending: "\u25BC", none: "" };
const ariaSort = /* @__PURE__ */ __name((sorted, direction) => {
  if (!sorted) {
    return "none";
  }
  return direction === "asc" ? "ascending" : "descending";
}, "ariaSort");
const PageBox = /* @__PURE__ */ __name(({ selection, rows }) => {
  const box = useRef(null);
  const open = rows.filter((row) => !selection.locked.has(row.id));
  const picked = open.filter((row) => selection.selected.has(row.id)).length;
  const all = open.length > 0 && picked === open.length;
  useEffect(() => {
    if (box.current) {
      box.current.indeterminate = picked > 0 && !all;
    }
  }, [picked, all]);
  return /* @__PURE__ */ jsxDEV("label", { className: "enrol_apply-rview-pickwrap", children: [
    /* @__PURE__ */ jsxDEV(
      "input",
      {
        ref: box,
        type: "checkbox",
        className: "enrol_apply-rview-pick",
        checked: all,
        disabled: open.length === 0,
        onChange: (event) => selection.onSelectPage(event.target.checked)
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
        lineNumber: 104,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: selection.pagelabel }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
      lineNumber: 112,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
    lineNumber: 103,
    columnNumber: 9
  });
}, "PageBox");
const RowBox = /* @__PURE__ */ __name(({ selection, row }) => selection.locked.has(row.id) ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-pickwrap enrol_apply-rview-pick--none" }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
  lineNumber: 124,
  columnNumber: 36
}) : /* @__PURE__ */ jsxDEV("label", { className: "enrol_apply-rview-pickwrap", children: [
  /* @__PURE__ */ jsxDEV(
    "input",
    {
      type: "checkbox",
      className: "enrol_apply-rview-pick",
      checked: selection.selected.has(row.id),
      onChange: (event) => selection.onSelect(row.id, event.target.checked)
    },
    void 0,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
      lineNumber: 126,
      columnNumber: 13
    }
  ),
  /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: selection.label(row) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
    lineNumber: 132,
    columnNumber: 13
  })
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
  lineNumber: 125,
  columnNumber: 9
}), "RowBox");
const Grid = /* @__PURE__ */ __name(({
  labels,
  columns,
  lead,
  rows,
  widths,
  sizing,
  sort,
  direction,
  selection,
  renderLead,
  renderCell,
  onSort,
  onWidth,
  onWidthCommit
}) => {
  const frame = useRef(null);
  const table = useRef(null);
  const drag = useRef(null);
  const total = columns.reduce((sum, column) => sum + widthOf(sizing, widths, column.key), 0);
  const scrolled = /* @__PURE__ */ __name((event) => {
    event.currentTarget.classList.toggle("is-scrolled", event.currentTarget.scrollLeft > 0);
  }, "scrolled");
  const paint = /* @__PURE__ */ __name((key, width) => {
    const col = table.current?.querySelector(`col[data-col="${CSS.escape(key)}"]`);
    if (!col || !table.current) {
      return;
    }
    col.style.width = `${width}px`;
    table.current.style.width = `${total - widthOf(sizing, widths, key) + width}px`;
  }, "paint");
  const bound = /* @__PURE__ */ __name((key, width) => Math.round(Math.min(sizing.maximum, Math.max(sizing.minimumWidth(key), width))), "bound");
  const down = /* @__PURE__ */ __name((event, key) => {
    if (event.button !== 0) {
      return;
    }
    event.preventDefault();
    event.currentTarget.setPointerCapture(event.pointerId);
    event.currentTarget.classList.add("is-active");
    const width = widthOf(sizing, widths, key);
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
    const width = bound(key, widthOf(sizing, widths, key) + step);
    if (width !== widthOf(sizing, widths, key)) {
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
          fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
          lineNumber: 283,
          columnNumber: 17
        }),
        /* @__PURE__ */ jsxDEV("colgroup", { children: columns.map((column) => /* @__PURE__ */ jsxDEV("col", { "data-col": column.key, style: { width: `${widthOf(sizing, widths, column.key)}px` } }, column.key, false, {
          fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
          lineNumber: 286,
          columnNumber: 25
        })) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
          lineNumber: 284,
          columnNumber: 17
        }),
        /* @__PURE__ */ jsxDEV("thead", { children: /* @__PURE__ */ jsxDEV("tr", { children: columns.map((column) => {
          const order = ariaSort(sort === column.key, direction);
          const title = /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-th-title", children: column.title }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
            lineNumber: 293,
            columnNumber: 43
          });
          const heading = column.sortable ? /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "enrol_apply-rview-sortbtn",
              onClick: () => onSort(column.key),
              children: [
                title,
                /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-arrow", "aria-hidden": "true", children: ARROWS[order] }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
                  lineNumber: 301,
                  columnNumber: 37
                })
              ]
            },
            void 0,
            true,
            {
              fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
              lineNumber: 295,
              columnNumber: 33
            }
          ) : /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-plain", children: title }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
            lineNumber: 305,
            columnNumber: 33
          });
          return /* @__PURE__ */ jsxDEV(
            "th",
            {
              scope: "col",
              className: column.key === lead ? "enrol_apply-rview-fixed" : void 0,
              "aria-sort": column.sortable ? order : void 0,
              title: column.title,
              "data-column": column.key,
              children: [
                selection && column.key === lead ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-lead", children: [
                  /* @__PURE__ */ jsxDEV(PageBox, { selection, rows }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
                    lineNumber: 317,
                    columnNumber: 45
                  }),
                  heading
                ] }, void 0, true, {
                  fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
                  lineNumber: 316,
                  columnNumber: 41
                }) : heading,
                /* @__PURE__ */ jsxDEV(
                  "span",
                  {
                    className: "enrol_apply-rview-resize",
                    role: "separator",
                    "aria-orientation": "vertical",
                    "aria-label": fill(labels.resize, column.title),
                    "aria-valuemin": sizing.minimumWidth(column.key),
                    "aria-valuemax": sizing.maximum,
                    "aria-valuenow": widthOf(sizing, widths, column.key),
                    tabIndex: 0,
                    onPointerDown: (event) => down(event, column.key),
                    onPointerMove: move,
                    onPointerUp: up,
                    onPointerCancel: up,
                    onDoubleClick: () => {
                      if (widthOf(sizing, widths, column.key) !== sizing.defaultWidth(column.key)) {
                        onWidth(column.key, null);
                      }
                    },
                    onKeyDown: (event) => keydown(event, column.key),
                    onBlur: onWidthCommit
                  },
                  void 0,
                  false,
                  {
                    fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
                    lineNumber: 321,
                    columnNumber: 37
                  }
                )
              ]
            },
            column.key,
            true,
            {
              fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
              lineNumber: 307,
              columnNumber: 33
            }
          );
        }) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
          lineNumber: 290,
          columnNumber: 21
        }) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
          lineNumber: 289,
          columnNumber: 17
        }),
        /* @__PURE__ */ jsxDEV("tbody", { children: rows.map((row) => /* @__PURE__ */ jsxDEV(
          "tr",
          {
            "data-record": row.id,
            className: selection?.selected.has(row.id) ? "enrol_apply-rview-row--selected" : void 0,
            children: columns.map((column) => column.key === lead ? /* @__PURE__ */ jsxDEV("th", { scope: "row", className: "enrol_apply-rview-fixed", children: selection ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-lead", children: [
              /* @__PURE__ */ jsxDEV(RowBox, { selection, row }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
                lineNumber: 359,
                columnNumber: 49
              }),
              renderLead(row, column)
            ] }, void 0, true, {
              fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
              lineNumber: 358,
              columnNumber: 45
            }) : renderLead(row, column) }, column.key, false, {
              fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
              lineNumber: 356,
              columnNumber: 37
            }) : /* @__PURE__ */ jsxDEV("td", { children: renderCell(row, column) }, column.key, false, {
              fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
              lineNumber: 365,
              columnNumber: 37
            }))
          },
          row.id,
          false,
          {
            fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
            lineNumber: 349,
            columnNumber: 25
          }
        )) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
          lineNumber: 347,
          columnNumber: 17
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
        lineNumber: 282,
        columnNumber: 13
      })
    },
    void 0,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
      lineNumber: 274,
      columnNumber: 9
    }
  );
}, "Grid");
const GridSkeleton = /* @__PURE__ */ __name(({ columns, lead, widths, sizing }) => {
  const total = columns.reduce((sum, column) => sum + widthOf(sizing, widths, column.key), 0);
  const shapes = ["enrol_apply-rview-skb--w80", "enrol_apply-rview-skb--w60", "enrol_apply-rview-skb--w40"];
  return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-gridframe", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV("table", { className: "enrol_apply-rview-grid", style: { width: `${total}px` }, children: [
    /* @__PURE__ */ jsxDEV("colgroup", { children: columns.map((column) => /* @__PURE__ */ jsxDEV("col", { style: { width: `${widthOf(sizing, widths, column.key)}px` } }, column.key, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
      lineNumber: 391,
      columnNumber: 46
    })) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
      lineNumber: 390,
      columnNumber: 17
    }),
    /* @__PURE__ */ jsxDEV("thead", { children: /* @__PURE__ */ jsxDEV("tr", { children: columns.map((column) => /* @__PURE__ */ jsxDEV("th", { className: column.key === lead ? "enrol_apply-rview-fixed" : void 0, children: /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-plain", children: /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-th-title", children: column.title }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
      lineNumber: 398,
      columnNumber: 37
    }) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
      lineNumber: 397,
      columnNumber: 33
    }) }, column.key, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
      lineNumber: 396,
      columnNumber: 29
    })) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
      lineNumber: 394,
      columnNumber: 21
    }) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
      lineNumber: 393,
      columnNumber: 17
    }),
    /* @__PURE__ */ jsxDEV("tbody", { children: Array.from({ length: 8 }, (_, i) => /* @__PURE__ */ jsxDEV("tr", { className: "enrol_apply-rview-sk", children: columns.map((column, j) => /* @__PURE__ */ jsxDEV("td", { className: column.key === lead ? "enrol_apply-rview-fixed" : void 0, children: /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb " + shapes[(i + j) % 3] }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
      lineNumber: 409,
      columnNumber: 37
    }) }, column.key, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
      lineNumber: 408,
      columnNumber: 33
    })) }, i, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
      lineNumber: 406,
      columnNumber: 25
    })) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
      lineNumber: 404,
      columnNumber: 17
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
    lineNumber: 389,
    columnNumber: 13
  }) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Grid.tsx",
    lineNumber: 388,
    columnNumber: 9
  });
}, "GridSkeleton");
export {
  Grid,
  GridSkeleton,
  RowBox
};
//# sourceMappingURL=Grid.dev.js.map
