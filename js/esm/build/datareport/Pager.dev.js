var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The foot of the rows: which rows these are, the pages and the page size (AR-D14: 30, 50 or 100).
 *
 * The server pages (one data query and one count whatever the size); the client never holds more
 * than one page. Narrow, the pages read "Page 2 of 9" between Previous and Next.
 *
 * @module     enrol_apply/datareport/Pager
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useId } from "react";
import { fillObject, num } from "./str";
const numbers = /* @__PURE__ */ __name((current, pages) => [.../* @__PURE__ */ new Set([1, current - 1, current, current + 1, pages])].filter((n) => n >= 1 && n <= pages).sort((a, b) => a - b), "numbers");
const Pager = /* @__PURE__ */ __name(({ labels, page, perpage, perpages, total, shown, narrow, onPage, onPerpage }) => {
  const id = useId();
  const pages = Math.max(1, Math.ceil(total / perpage));
  const current = page + 1;
  const from = total > 0 ? page * perpage + 1 : 0;
  const to = total > 0 ? from + shown - 1 : 0;
  let list = [];
  if (narrow) {
    list = [
      /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-pager-gap", children: fillObject(labels.pageof, { page: num(current), pages: num(pages) }) }, "of", false, {
        fileName: "public/enrol/apply/js/esm/src/datareport/Pager.tsx",
        lineNumber: 69,
        columnNumber: 13
      })
    ];
  } else {
    const shownpages = numbers(current, pages);
    shownpages.forEach((n, index) => {
      if (index > 0 && n - shownpages[index - 1] > 1) {
        list.push(/* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-pager-gap", "aria-hidden": "true", children: "\u2026" }, `gap${n}`, false, {
          fileName: "public/enrol/apply/js/esm/src/datareport/Pager.tsx",
          lineNumber: 77,
          columnNumber: 27
        }));
      }
      list.push(
        /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            "aria-current": n === current ? "page" : void 0,
            onClick: () => onPage(n - 1),
            children: num(n)
          },
          n,
          false,
          {
            fileName: "public/enrol/apply/js/esm/src/datareport/Pager.tsx",
            lineNumber: 80,
            columnNumber: 17
          }
        )
      );
    });
  }
  return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-foot", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-num", "data-region": "enrol_apply-range", children: fillObject(labels.range, { from: num(from), to: num(to), total: num(total) }) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Pager.tsx",
      lineNumber: 94,
      columnNumber: 13
    }),
    pages > 1 ? /* @__PURE__ */ jsxDEV("nav", { className: "enrol_apply-rview-pager", "aria-label": labels.pages, children: [
      /* @__PURE__ */ jsxDEV("button", { type: "button", disabled: current === 1, onClick: () => onPage(page - 1), children: labels.previous }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/datareport/Pager.tsx",
        lineNumber: 99,
        columnNumber: 21
      }),
      list,
      /* @__PURE__ */ jsxDEV("button", { type: "button", disabled: current === pages, onClick: () => onPage(page + 1), children: labels.next }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/datareport/Pager.tsx",
        lineNumber: 101,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Pager.tsx",
      lineNumber: 98,
      columnNumber: 17
    }) : null,
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-perpage", children: [
      /* @__PURE__ */ jsxDEV("label", { htmlFor: id, children: labels.perpage }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/datareport/Pager.tsx",
        lineNumber: 105,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(
        "select",
        {
          id,
          className: "form-select form-select-sm",
          value: perpage,
          onChange: (event) => onPerpage(Number(event.target.value)),
          children: perpages.map((n) => /* @__PURE__ */ jsxDEV("option", { value: n, children: num(n) }, n, false, {
            fileName: "public/enrol/apply/js/esm/src/datareport/Pager.tsx",
            lineNumber: 112,
            columnNumber: 42
          }))
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/datareport/Pager.tsx",
          lineNumber: 106,
          columnNumber: 17
        }
      )
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Pager.tsx",
      lineNumber: 104,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Pager.tsx",
    lineNumber: 93,
    columnNumber: 9
  });
}, "Pager");
export {
  Pager
};
//# sourceMappingURL=Pager.dev.js.map
