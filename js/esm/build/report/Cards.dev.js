var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The course report's cards: the shared card view (datareport/Cards) with the report's four lines.
 *
 * Four fixed lines answer who, what happened, how long and who decided: the applicant with the outcome
 * and the Review action; the wait and the submission date; the decision; the group and the method. The
 * other chosen fields follow in the card's list, a field with nothing in it left out.
 *
 * @module     enrol_apply/report/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Cards as DataCards } from "../datareport/Cards";
import { CellLines, IdentityLines, ReviewButton, cellHtml, lines } from "./cells";
const FIXED = ["applicant", "outcome", "submitted", "wait", "decided", "group", "method"];
const First = /* @__PURE__ */ __name(({ row, column }) => {
  const first = lines(column, cellHtml(row, column))[0] ?? "";
  return first === "" ? null : /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-inline", dangerouslySetInnerHTML: { __html: first } }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
    lineNumber: 54,
    columnNumber: 34
  });
}, "First");
const content = /* @__PURE__ */ __name((labels, columns, identity, row) => {
  const has = /* @__PURE__ */ __name((key) => columns.some((column) => column.key === key), "has");
  const extra = columns.filter((column) => !FIXED.includes(column.key) && cellHtml(row, column.key).trim() !== "");
  return {
    head: /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-who", children: [
        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-card-name", dangerouslySetInnerHTML: { __html: cellHtml(row, "applicant") } }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
          lineNumber: 81,
          columnNumber: 21
        }),
        has("outcome") ? /* @__PURE__ */ jsxDEV(First, { row, column: "outcome" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
          lineNumber: 82,
          columnNumber: 39
        }) : null,
        /* @__PURE__ */ jsxDEV(IdentityLines, { row, identity }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
          lineNumber: 83,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 80,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(ReviewButton, { row, label: labels.review }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 85,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
      lineNumber: 79,
      columnNumber: 13
    }),
    lines: /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-line" + (row.open ? " enrol_apply-rview-card-line--open" : ""), children: [
        has("wait") ? /* @__PURE__ */ jsxDEV(First, { row, column: "wait" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
          lineNumber: 91,
          columnNumber: 36
        }) : null,
        has("wait") && has("submitted") ? /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: " \xB7 " }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
          lineNumber: 92,
          columnNumber: 56
        }) : null,
        has("submitted") ? /* @__PURE__ */ jsxDEV(First, { row, column: "submitted" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
          lineNumber: 93,
          columnNumber: 41
        }) : null
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 90,
        columnNumber: 17
      }),
      has("decided") ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-line", children: lines("decided", cellHtml(row, "decided")).map((part, index) => /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-inline", dangerouslySetInnerHTML: { __html: part } }, index, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 98,
        columnNumber: 29
      })) }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 96,
        columnNumber: 21
      }) : null,
      has("group") || has("method") ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-line enrol_apply-rview-card-line--muted", children: [
        has("group") ? /* @__PURE__ */ jsxDEV(First, { row, column: "group" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
          lineNumber: 104,
          columnNumber: 41
        }) : null,
        has("group") && has("method") && cellHtml(row, "group") !== "" ? /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: " \xB7 " }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
          lineNumber: 106,
          columnNumber: 31
        }) : null,
        has("method") ? /* @__PURE__ */ jsxDEV(First, { row, column: "method" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
          lineNumber: 108,
          columnNumber: 42
        }) : null
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 103,
        columnNumber: 21
      }) : null
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
      lineNumber: 89,
      columnNumber: 13
    }),
    fields: extra.map((column) => ({
      key: column.key,
      title: column.title,
      content: /* @__PURE__ */ jsxDEV(CellLines, { column: column.key, html: cellHtml(row, column.key) }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 116,
        columnNumber: 22
      })
    }))
  };
}, "content");
const Cards = /* @__PURE__ */ __name(({ labels, columns, identity, rows, single }) => /* @__PURE__ */ jsxDEV(
  DataCards,
  {
    labels,
    rows,
    single,
    renderCard: (row) => content(labels, columns, identity, row)
  },
  void 0,
  false,
  {
    fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
    lineNumber: 128,
    columnNumber: 5
  }
), "Cards");
export {
  Cards
};
//# sourceMappingURL=Cards.dev.js.map
