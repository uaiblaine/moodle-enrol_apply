var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The parts of the queue view that only choose what to draw: the notices over the bar, the rows as the
 * grid or the cards, the dock and the decision sheet. Each takes what the view already holds and keeps
 * no state of its own.
 *
 * @module     enrol_apply/queue/Parts
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Cards as DataCards } from "../datareport/Cards";
import { Grid as DataGrid } from "../datareport/Grid";
import { ApplicantCell, CellLine, SIZING, cellHtml } from "./cells";
import { Dock } from "./Dock";
import { Sheet } from "./Sheet";
const CARD_FIXED = ["applicant", "wait", "group"];
const cardFields = /* @__PURE__ */ __name((shown, row) => shown.filter((column) => !CARD_FIXED.includes(column.key) && cellHtml(row, column.key).trim() !== "").map((column) => ({ key: column.key, title: column.title, content: /* @__PURE__ */ jsxDEV(CellLine, { html: cellHtml(row, column.key) }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/queue/Parts.tsx",
  lineNumber: 50,
  columnNumber: 71
}) })), "cardFields");
const Notices = /* @__PURE__ */ __name(({ labels, narrowed, viewonly }) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
  narrowed ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-narrowed", children: labels.narrowed }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Parts.tsx",
    lineNumber: 66,
    columnNumber: 21
  }) : null,
  viewonly ? /* @__PURE__ */ jsxDEV("div", { className: "alert alert-info", role: "status", children: labels.viewonly }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Parts.tsx",
    lineNumber: 67,
    columnNumber: 21
  }) : null
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/queue/Parts.tsx",
  lineNumber: 65,
  columnNumber: 5
}), "Notices");
const RowsBody = /* @__PURE__ */ __name((props) => {
  const { labels, list, shown, cards, narrow, selection } = props;
  const has = /* @__PURE__ */ __name((key) => shown.some((column) => column.key === key), "has");
  if (cards) {
    return /* @__PURE__ */ jsxDEV(
      DataCards,
      {
        labels,
        rows: list,
        single: narrow,
        selection,
        renderCard: (row) => ({
          head: /* @__PURE__ */ jsxDEV(ApplicantCell, { labels, row }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/queue/Parts.tsx",
            lineNumber: 110,
            columnNumber: 27
          }),
          lines: /* @__PURE__ */ jsxDEV(Fragment, { children: [
            has("wait") ? /* @__PURE__ */ jsxDEV(
              "div",
              {
                className: "enrol_apply-rview-card-line enrol_apply-rview-card-line--open",
                dangerouslySetInnerHTML: { __html: cellHtml(row, "wait") }
              },
              void 0,
              false,
              {
                fileName: "public/enrol/apply/js/esm/src/queue/Parts.tsx",
                lineNumber: 114,
                columnNumber: 33
              }
            ) : null,
            has("group") && cellHtml(row, "group") !== "" ? /* @__PURE__ */ jsxDEV(
              "div",
              {
                className: "enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",
                dangerouslySetInnerHTML: { __html: cellHtml(row, "group") }
              },
              void 0,
              false,
              {
                fileName: "public/enrol/apply/js/esm/src/queue/Parts.tsx",
                lineNumber: 120,
                columnNumber: 33
              }
            ) : null
          ] }, void 0, true, {
            fileName: "public/enrol/apply/js/esm/src/queue/Parts.tsx",
            lineNumber: 112,
            columnNumber: 25
          }),
          fields: cardFields(shown, row)
        })
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/Parts.tsx",
        lineNumber: 104,
        columnNumber: 13
      }
    );
  }
  return /* @__PURE__ */ jsxDEV(
    DataGrid,
    {
      labels,
      columns: shown,
      lead: "applicant",
      rows: list,
      widths: props.widths,
      sizing: SIZING,
      sort: props.sort,
      direction: props.direction,
      selection,
      renderLead: (row) => /* @__PURE__ */ jsxDEV(ApplicantCell, { labels, row }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Parts.tsx",
        lineNumber: 144,
        columnNumber: 34
      }),
      renderCell: (row, column) => /* @__PURE__ */ jsxDEV(CellLine, { html: cellHtml(row, column.key) }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Parts.tsx",
        lineNumber: 145,
        columnNumber: 42
      }),
      onSort: props.onSort,
      onWidth: props.onWidth,
      onWidthCommit: props.onWidthCommit
    },
    void 0,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/queue/Parts.tsx",
      lineNumber: 134,
      columnNumber: 9
    }
  );
}, "RowsBody");
const dockFor = /* @__PURE__ */ __name((labels, offer, decides, count, onDecide) => offer !== null && decides && count > 0 ? /* @__PURE__ */ jsxDEV(Dock, { labels, count, actions: offer.actions, onDecide }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/queue/Parts.tsx",
  lineNumber: 170,
  columnNumber: 7
}) : null, "dockFor");
const sheetFor = /* @__PURE__ */ __name((labels, offer, decision, chosen, onClose, onCommit) => offer !== null && decision !== null && chosen.length > 0 ? /* @__PURE__ */ jsxDEV(Sheet, { labels, decision, rows: chosen, offer, onClose, onCommit }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/queue/Parts.tsx",
  lineNumber: 192,
  columnNumber: 7
}) : null, "sheetFor");
export {
  Notices,
  RowsBody,
  dockFor,
  sheetFor
};
//# sourceMappingURL=Parts.dev.js.map
