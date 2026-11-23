var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The course report's grid: the shared bounded grid (datareport/Grid) with the report's cells.
 *
 * The lead column is the applicant: their name as the report formats it, the identity fields the
 * reader's report shows under it, and the Review action on an open application. Every other cell is
 * the report's formatted HTML, one fact per line, and the wait's carries a bar of the row's wait against
 * the longest on the page. The report selects nothing.
 *
 * @module     enrol_apply/report/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Grid as DataGrid } from "../datareport/Grid";
import { CellLines, IdentityLines, ReviewButton, SIZING, cellHtml } from "./cells";
const WaitBar = /* @__PURE__ */ __name(({ row, longest }) => {
  if (!row.open && !row.decidedbyperson) {
    return null;
  }
  return /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-waitbar" + (row.open ? " enrol_apply-rview-waitbar--open" : ""), "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV("span", { style: { width: `${Math.min(100, Math.round(row.wait / longest * 100))}%` } }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
    lineNumber: 60,
    columnNumber: 13
  }) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
    lineNumber: 59,
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
  const longest = Math.max(86400, ...rows.filter((row) => row.open || row.decidedbyperson).map((row) => row.wait));
  return /* @__PURE__ */ jsxDEV(
    DataGrid,
    {
      labels,
      columns,
      lead: "applicant",
      rows,
      widths,
      sizing: SIZING,
      sort,
      direction,
      renderLead: (row, column) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-applicant", children: [
        /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-applicant-text", children: [
          /* @__PURE__ */ jsxDEV(CellLines, { column: column.key, html: cellHtml(row, column.key) }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
            lineNumber: 88,
            columnNumber: 25
          }),
          /* @__PURE__ */ jsxDEV(IdentityLines, { row, identity }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
            lineNumber: 89,
            columnNumber: 25
          })
        ] }, void 0, true, {
          fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
          lineNumber: 87,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(ReviewButton, { row, label: labels.review }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
          lineNumber: 91,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
        lineNumber: 86,
        columnNumber: 17
      }),
      renderCell: (row, column) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
        /* @__PURE__ */ jsxDEV(CellLines, { column: column.key, html: cellHtml(row, column.key) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
          lineNumber: 96,
          columnNumber: 21
        }),
        column.key === "wait" ? /* @__PURE__ */ jsxDEV(WaitBar, { row, longest }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
          lineNumber: 97,
          columnNumber: 46
        }) : null
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
        lineNumber: 95,
        columnNumber: 17
      }),
      onSort,
      onWidth,
      onWidthCommit
    },
    void 0,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/report/Grid.tsx",
      lineNumber: 76,
      columnNumber: 9
    }
  );
}, "Grid");
export {
  Grid
};
//# sourceMappingURL=Grid.dev.js.map
