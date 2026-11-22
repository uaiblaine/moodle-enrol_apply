var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The card view (AR-D9): the same rows, page and order as the grid, one article each.
 *
 * Four fixed lines answer who, what happened, how long and who decided: the applicant with the outcome
 * and the Review action; the wait and the submission date; the decision; the group and the method. The
 * other chosen fields follow in a list, at once on a wide screen (a grid of 17.5rem cards) and behind
 * "More" on a narrow one (one column).
 *
 * @module     enrol_apply/report/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useState } from "react";
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
const Card = /* @__PURE__ */ __name(({ labels, columns, identity, row, single }) => {
  const [more, setMore] = useState(false);
  const extra = columns.filter((column) => !FIXED.includes(column.key));
  const shown = !single || more;
  const has = /* @__PURE__ */ __name((key) => columns.some((column) => column.key === key), "has");
  const list = /* @__PURE__ */ jsxDEV("dl", { className: "enrol_apply-rview-card-list", children: extra.map((column) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-item", children: [
    /* @__PURE__ */ jsxDEV("dt", { children: column.title }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
      lineNumber: 80,
      columnNumber: 21
    }),
    /* @__PURE__ */ jsxDEV("dd", { children: /* @__PURE__ */ jsxDEV(CellLines, { column: column.key, html: cellHtml(row, column.key) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
      lineNumber: 81,
      columnNumber: 25
    }) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
      lineNumber: 81,
      columnNumber: 21
    })
  ] }, column.key, true, {
    fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
    lineNumber: 79,
    columnNumber: 17
  })) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
    lineNumber: 77,
    columnNumber: 9
  });
  return /* @__PURE__ */ jsxDEV("article", { className: "enrol_apply-rview-card", role: "listitem", "data-record": row.id, children: [
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-l1", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-who", children: [
        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-card-name", dangerouslySetInnerHTML: { __html: cellHtml(row, "applicant") } }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
          lineNumber: 91,
          columnNumber: 21
        }),
        has("outcome") ? /* @__PURE__ */ jsxDEV(First, { row, column: "outcome" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
          lineNumber: 92,
          columnNumber: 39
        }) : null,
        /* @__PURE__ */ jsxDEV(IdentityLines, { row, identity }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
          lineNumber: 93,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 90,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(ReviewButton, { row, label: labels.review }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 95,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
      lineNumber: 89,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-line" + (row.open ? " enrol_apply-rview-card-line--open" : ""), children: [
      has("wait") ? /* @__PURE__ */ jsxDEV(First, { row, column: "wait" }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 98,
        columnNumber: 32
      }) : null,
      has("wait") && has("submitted") ? /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: " \xB7 " }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 99,
        columnNumber: 52
      }) : null,
      has("submitted") ? /* @__PURE__ */ jsxDEV(First, { row, column: "submitted" }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 100,
        columnNumber: 37
      }) : null
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
      lineNumber: 97,
      columnNumber: 13
    }),
    has("decided") ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-line", children: lines("decided", cellHtml(row, "decided")).map((part, index) => /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-inline", dangerouslySetInnerHTML: { __html: part } }, index, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
      lineNumber: 105,
      columnNumber: 25
    })) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
      lineNumber: 103,
      columnNumber: 17
    }) : null,
    has("group") || has("method") ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-line enrol_apply-rview-card-line--muted", children: [
      has("group") ? /* @__PURE__ */ jsxDEV(First, { row, column: "group" }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 111,
        columnNumber: 37
      }) : null,
      has("group") && has("method") && cellHtml(row, "group") !== "" ? /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: " \xB7 " }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 112,
        columnNumber: 87
      }) : null,
      has("method") ? /* @__PURE__ */ jsxDEV(First, { row, column: "method" }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
        lineNumber: 113,
        columnNumber: 38
      }) : null
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
      lineNumber: 110,
      columnNumber: 17
    }) : null,
    extra.length > 0 ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card-more", children: [
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
          fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
          lineNumber: 119,
          columnNumber: 25
        }
      ) : null,
      shown ? list : null
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
      lineNumber: 117,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
    lineNumber: 88,
    columnNumber: 9
  });
}, "Card");
const Cards = /* @__PURE__ */ __name(({ labels, columns, identity, rows, single }) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-cards" + (single ? " enrol_apply-rview-cards--single" : ""), role: "list", children: rows.map((row) => /* @__PURE__ */ jsxDEV(Card, { labels, columns, identity, row, single }, row.id, false, {
  fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
  lineNumber: 144,
  columnNumber: 13
})) }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
  lineNumber: 142,
  columnNumber: 5
}), "Cards");
const CardsSkeleton = /* @__PURE__ */ __name(({ single }) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-cards" + (single ? " enrol_apply-rview-cards--single" : ""), "aria-hidden": "true", children: Array.from({ length: single ? 4 : 6 }, (_, i) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-card enrol_apply-rview-sk", children: [
  /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb enrol_apply-rview-skb--w60" }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
    lineNumber: 159,
    columnNumber: 17
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb enrol_apply-rview-skb--w80" }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
    lineNumber: 160,
    columnNumber: 17
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb enrol_apply-rview-skb--w60" }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
    lineNumber: 161,
    columnNumber: 17
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb enrol_apply-rview-skb--w40" }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
    lineNumber: 162,
    columnNumber: 17
  })
] }, i, true, {
  fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
  lineNumber: 158,
  columnNumber: 13
})) }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/report/Cards.tsx",
  lineNumber: 156,
  columnNumber: 5
}), "CardsSkeleton");
export {
  Cards,
  CardsSkeleton
};
//# sourceMappingURL=Cards.dev.js.map
