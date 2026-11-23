var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * A cell drawn from the server's formatted HTML, one fact per line.
 *
 * A data report's cells are formatted on the server, where the masking and the escaping ran, so a cell
 * is inserted as HTML and never built here. A cell that holds several facts has them joined by a
 * newline; the first line is the cell's line and the others its sub-lines. Every line ends in an
 * ellipsis, its whole text in its title.
 *
 * @module     enrol_apply/datareport/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { plain } from "./str";
const splitLines = /* @__PURE__ */ __name((html, multiline) => {
  if (!multiline) {
    return [html];
  }
  return html.split("\n").map((line) => line.trim()).filter((line) => line !== "");
}, "splitLines");
const Lines = /* @__PURE__ */ __name(({ parts }) => /* @__PURE__ */ jsxDEV(Fragment, { children: parts.map((part, index) => /* @__PURE__ */ jsxDEV(
  "span",
  {
    className: index === 0 ? "enrol_apply-rview-c" : "enrol_apply-rview-sub",
    title: plain(part),
    dangerouslySetInnerHTML: { __html: part }
  },
  index,
  false,
  {
    fileName: "public/enrol/apply/js/esm/src/datareport/cells.tsx",
    lineNumber: 56,
    columnNumber: 13
  }
)) }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/datareport/cells.tsx",
  lineNumber: 54,
  columnNumber: 5
}), "Lines");
export {
  Lines,
  splitLines
};
//# sourceMappingURL=cells.dev.js.map
