var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The card view (AR-D9): the same rows, page and order as the grid, one article each.
 *
 * A card is a first line (who, and the row's action), the view's fixed lines, then the other chosen
 * fields in a list, at once on a wide screen (a grid of 17.5rem cards) and behind "More" on a narrow
 * one (one column). What each part holds is the view's (`renderCard`); a view that selects rows passes
 * a selection, whose box leads the first line.
 *
 * Strings read from the view's labels: more, less.
 *
 * @module     enrol_apply/datareport/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useState } from "react";
import { RowBox } from "./Grid";
const Card = /* @__PURE__ */ __name(({ labels, row, single, selection, renderCard }) => {
  const [more, setMore] = useState(false);
  const content = renderCard(row);
  const shown = !single || more;
  const picked = selection?.selected.has(row.id) ?? false;
  return /* @__PURE__ */ jsxDEV(
    "article",
    {
      className: "enrol_apply-rview-card" + (picked ? " enrol_apply-rview-card--selected" : ""),
      role: "listitem",
      "data-record": row.id,
      children: [
        /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-l1", children: [
          selection ? /* @__PURE__ */ jsxDEV(RowBox, { selection, row }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
            lineNumber: 77,
            columnNumber: 30
          }) : null,
          content.head
        ] }, void 0, true, {
          fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
          lineNumber: 76,
          columnNumber: 13
        }),
        content.lines,
        content.fields.length > 0 ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-more", children: [
          single ? /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "btn btn-link btn-sm enrol_apply-rview-linkbtn",
              "aria-expanded": more,
              onClick: () => setMore(!more),
              children: more ? labels.less : labels.more
            },
            void 0,
            false,
            {
              fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
              lineNumber: 84,
              columnNumber: 25
            }
          ) : null,
          shown ? /* @__PURE__ */ jsxDEV("dl", { className: "enrol_apply-rview-card-list", children: content.fields.map((field) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-item", children: [
            /* @__PURE__ */ jsxDEV("dt", { children: field.title }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
              lineNumber: 97,
              columnNumber: 37
            }),
            /* @__PURE__ */ jsxDEV("dd", { children: field.content }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
              lineNumber: 98,
              columnNumber: 37
            })
          ] }, field.key, true, {
            fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
            lineNumber: 96,
            columnNumber: 33
          })) }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
            lineNumber: 94,
            columnNumber: 25
          }) : null
        ] }, void 0, true, {
          fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
          lineNumber: 82,
          columnNumber: 17
        }) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
      lineNumber: 71,
      columnNumber: 9
    }
  );
}, "Card");
const Cards = /* @__PURE__ */ __name(({ labels, rows, single, selection, renderCard }) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-cards" + (single ? " enrol_apply-rview-cards--single" : ""), role: "list", children: rows.map((row) => /* @__PURE__ */ jsxDEV(Card, { labels, row, single, selection, renderCard }, row.id, false, {
  fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
  lineNumber: 118,
  columnNumber: 13
})) }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
  lineNumber: 116,
  columnNumber: 5
}), "Cards");
const CardsSkeleton = /* @__PURE__ */ __name(({ single }) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-cards" + (single ? " enrol_apply-rview-cards--single" : ""), "aria-hidden": "true", children: Array.from({ length: single ? 4 : 6 }, (_, i) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card enrol_apply-rview-sk", children: [
  /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb enrol_apply-rview-skb--w60" }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
    lineNumber: 133,
    columnNumber: 17
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb enrol_apply-rview-skb--w80" }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
    lineNumber: 134,
    columnNumber: 17
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb enrol_apply-rview-skb--w60" }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
    lineNumber: 135,
    columnNumber: 17
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb enrol_apply-rview-skb--w40" }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
    lineNumber: 136,
    columnNumber: 17
  })
] }, i, true, {
  fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
  lineNumber: 132,
  columnNumber: 13
})) }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/datareport/Cards.tsx",
  lineNumber: 130,
  columnNumber: 5
}), "CardsSkeleton");
export {
  Cards,
  CardsSkeleton
};
//# sourceMappingURL=Cards.dev.js.map
