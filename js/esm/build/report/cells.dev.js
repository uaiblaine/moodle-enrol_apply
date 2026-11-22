var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The report's cells as the grid and the cards draw them, and the columns' widths.
 *
 * A cell is the report's own formatted HTML (format_row(): the masking and the escaping already ran
 * on the server, as on core's table), so it is inserted as HTML and never built here. A few cells hold
 * several facts, one per line (the formatters join them with a newline: the outcome and what the
 * record says, the wait and how it ended, the date and who decided, the group and the groups joined);
 * the first line is the cell's line and the others its sub-lines. Every line ends in an ellipsis, its
 * whole text in its title.
 *
 * @module     enrol_apply/report/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { plain } from "./str";
const MULTILINE = ["outcome", "wait", "decided", "group"];
const WIDTHS = {
  applicant: 260,
  outcome: 180,
  submitted: 132,
  wait: 150,
  decided: 172,
  group: 200,
  method: 170,
  route: 170,
  comment: 240,
  note: 240
};
const DEFAULT_WIDTH = 180;
const MINIMUMS = { applicant: 160, submitted: 104, decided: 104 };
const MINIMUM = 80;
const MAXIMUM = 800;
const defaultWidth = /* @__PURE__ */ __name((key) => WIDTHS[key] ?? DEFAULT_WIDTH, "defaultWidth");
const minimumWidth = /* @__PURE__ */ __name((key) => MINIMUMS[key] ?? MINIMUM, "minimumWidth");
const widthOf = /* @__PURE__ */ __name((widths, key) => Math.min(MAXIMUM, Math.max(minimumWidth(key), widths[key] ?? defaultWidth(key))), "widthOf");
const cellHtml = /* @__PURE__ */ __name((row, key) => row.cells.find((cell) => cell.key === key)?.html ?? "", "cellHtml");
const lines = /* @__PURE__ */ __name((key, html) => {
  if (!MULTILINE.includes(key)) {
    return [html];
  }
  return html.split("\n").map((line) => line.trim()).filter((line) => line !== "");
}, "lines");
const CellLines = /* @__PURE__ */ __name(({ column, html }) => {
  const parts = lines(column, html);
  return /* @__PURE__ */ jsxDEV(Fragment, { children: parts.map((part, index) => /* @__PURE__ */ jsxDEV(
    "span",
    {
      className: index === 0 ? "enrol_apply-rview-c" : "enrol_apply-rview-sub",
      title: plain(part),
      dangerouslySetInnerHTML: { __html: part }
    },
    index,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/report/cells.tsx",
      lineNumber: 126,
      columnNumber: 17
    }
  )) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/cells.tsx",
    lineNumber: 124,
    columnNumber: 9
  });
}, "CellLines");
const ReviewButton = /* @__PURE__ */ __name(({ row, label }) => row.reviewurl === "" ? null : /* @__PURE__ */ jsxDEV("a", { className: "btn btn-sm btn-outline-secondary enrol_apply-rview-review", href: row.reviewurl, children: label }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/report/cells.tsx",
  lineNumber: 146,
  columnNumber: 9
}), "ReviewButton");
const IdentityLines = /* @__PURE__ */ __name(({ row, identity }) => /* @__PURE__ */ jsxDEV(Fragment, { children: identity.map((column) => {
  const html = cellHtml(row, column.key);
  return html === "" ? null : /* @__PURE__ */ jsxDEV(
    "span",
    {
      className: "enrol_apply-rview-sub",
      title: plain(html),
      dangerouslySetInnerHTML: { __html: html }
    },
    column.key,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/report/cells.tsx",
      lineNumber: 161,
      columnNumber: 17
    }
  );
}) }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/report/cells.tsx",
  lineNumber: 157,
  columnNumber: 5
}), "IdentityLines");
export {
  CellLines,
  IdentityLines,
  MAXIMUM,
  ReviewButton,
  cellHtml,
  defaultWidth,
  lines,
  minimumWidth,
  widthOf
};
//# sourceMappingURL=cells.dev.js.map
