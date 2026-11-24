var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The dock under the rows: how many applications are selected and the three decisions.
 *
 * The decisions are the table's (renderer::queue_actions(), which the props carry): the same labels,
 * in the same order and the same styles, Cancel applications, Defer, Confirm. Each opens the decision
 * sheet; nothing posts from here. The count is worded "on this page", because that is what a selection
 * is: it is cleared
 * whenever the page, the scope, a filter, the search or the sort changes. On the queues that span
 * methods it adds how many methods the selection spans, when more than one, because the sheet then asks a
 * message per method. Drawn only while something is selected; the view keeps it out otherwise.
 *
 * @module     enrol_apply/queue/Dock
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fill, num } from "../datareport/str";
const Dock = /* @__PURE__ */ __name(({ labels, count, methods, actions, onDecide }) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-qview-dock", role: "region", "aria-label": labels.dock, "data-region": "enrol_apply-dock", children: [
  /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-qview-dockcount", "aria-live": "polite", children: [
    fill(labels.selected, num(count)),
    methods > 1 ? /* @__PURE__ */ jsxDEV("small", { className: "enrol_apply-qview-dockmethods", children: " " + fill(labels.dockmethods, num(methods)) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Dock.tsx",
      lineNumber: 57,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/Dock.tsx",
    lineNumber: 54,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-qview-dockactions", children: actions.map((action) => /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      className: "btn " + action.classes,
      onClick: (event) => onDecide(action.value, event.currentTarget),
      children: action.label
    },
    action.value,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/queue/Dock.tsx",
      lineNumber: 62,
      columnNumber: 17
    }
  )) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Dock.tsx",
    lineNumber: 60,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/queue/Dock.tsx",
  lineNumber: 53,
  columnNumber: 5
}), "Dock");
export {
  Dock
};
//# sourceMappingURL=Dock.dev.js.map
