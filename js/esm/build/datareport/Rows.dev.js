var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The rows' region: what it holds while the rows load, fail, are none, or are there.
 *
 * The first load draws a skeleton of the grid or of the cards, with aria-busy and a status for
 * assistive technology; later loads keep the old rows, dimmed, under a 2 px progress line. A failed
 * read says so with a retry, above the old rows when there are some. No rows is the empty state, worded
 * for a filtered view or an empty one. The rows themselves (the view's grid or cards) are the children;
 * a dock the view keeps under them (a queue's decision bar) follows the region.
 *
 * Strings read from the view's labels: caption, loading, loaderror, retry, empty, nomatch, nomatchhelp,
 * scrollhint.
 *
 * @module     enrol_apply/datareport/Rows
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { CardsSkeleton } from "./Cards";
import { GridSkeleton } from "./Grid";
const RowsContent = /* @__PURE__ */ __name(({
  labels,
  loaded,
  error,
  total,
  filtered,
  cards,
  narrow,
  skeleton,
  children,
  onRetry
}) => {
  const retry = /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-error", children: [
    labels.loaderror,
    " ",
    /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: onRetry, children: labels.retry }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
      lineNumber: 68,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
    lineNumber: 66,
    columnNumber: 9
  });
  if (!loaded && error) {
    return retry;
  }
  if (!loaded) {
    return /* @__PURE__ */ jsxDEV(Fragment, { children: [
      cards ? /* @__PURE__ */ jsxDEV(CardsSkeleton, { single: narrow }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
        lineNumber: 77,
        columnNumber: 26
      }) : /* @__PURE__ */ jsxDEV(GridSkeleton, { ...skeleton }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
        lineNumber: 77,
        columnNumber: 62
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", role: "status", children: labels.loading }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
        lineNumber: 78,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
      lineNumber: 76,
      columnNumber: 13
    });
  }
  if (total === 0) {
    return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-empty", children: [
      /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-empty-title", children: filtered ? labels.nomatch : labels.empty }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
        lineNumber: 85,
        columnNumber: 17
      }),
      filtered ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-empty-text", children: labels.nomatchhelp }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
        lineNumber: 86,
        columnNumber: 29
      }) : null
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
      lineNumber: 84,
      columnNumber: 13
    });
  }
  return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-dim", children: [
    error ? retry : null,
    children,
    cards ? null : /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-hint", children: labels.scrollhint }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
      lineNumber: 95,
      columnNumber: 29
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
    lineNumber: 92,
    columnNumber: 9
  });
}, "RowsContent");
const RowsRegion = /* @__PURE__ */ __name((props) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
  /* @__PURE__ */ jsxDEV(
    "section",
    {
      className: "enrol_apply-rview-region",
      "aria-label": props.labels.caption,
      "aria-busy": props.loading,
      "data-region": "enrol_apply-rows",
      children: [
        props.loading && props.loaded ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-progress" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
          lineNumber: 114,
          columnNumber: 46
        }) : null,
        /* @__PURE__ */ jsxDEV(RowsContent, { ...props }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
          lineNumber: 115,
          columnNumber: 13
        })
      ]
    },
    void 0,
    true,
    {
      fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
      lineNumber: 108,
      columnNumber: 9
    }
  ),
  props.dock ?? null
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/datareport/Rows.tsx",
  lineNumber: 107,
  columnNumber: 5
}), "RowsRegion");
export {
  RowsRegion
};
//# sourceMappingURL=Rows.dev.js.map
