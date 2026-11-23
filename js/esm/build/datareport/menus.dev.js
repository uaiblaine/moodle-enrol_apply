var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The bar's menus (the Design System's menu.md): the keys that move between items, and the Columns
 * menu's sets.
 *
 * @module     enrol_apply/datareport/menus
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Icon } from "./icons";
const menuKeys = /* @__PURE__ */ __name((event) => {
  if (!["ArrowDown", "ArrowUp", "Home", "End"].includes(event.key)) {
    return;
  }
  const items = Array.from(event.currentTarget.querySelectorAll('[role^="menuitem"]'));
  if (items.length === 0) {
    return;
  }
  event.preventDefault();
  const at = items.indexOf(document.activeElement);
  let next = 0;
  if (event.key === "End") {
    next = items.length - 1;
  } else if (event.key === "ArrowDown") {
    next = (at + 1) % items.length;
  } else if (event.key === "ArrowUp") {
    next = (at - 1 + items.length) % items.length;
  }
  items[next].focus();
}, "menuKeys");
const SetItems = /* @__PURE__ */ __name(({ heading, sets, onSet }) => {
  if (sets.length === 0) {
    return null;
  }
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-menu-head", role: "presentation", children: heading }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
      lineNumber: 72,
      columnNumber: 13
    }),
    sets.map((set) => /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: "enrol_apply-rview-menu-item",
        role: "menuitemcheckbox",
        "aria-checked": set.active,
        tabIndex: -1,
        "data-preset": set.name,
        onClick: () => {
          if (!set.active) {
            onSet(set.name);
          }
        },
        children: [
          /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-box", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV(Icon, { name: "tick" }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
            lineNumber: 88,
            columnNumber: 80
          }) }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
            lineNumber: 88,
            columnNumber: 21
          }),
          /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: set.label }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
            lineNumber: 89,
            columnNumber: 21
          })
        ]
      },
      set.name,
      true,
      {
        fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
        lineNumber: 74,
        columnNumber: 17
      }
    )),
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-menu-sep", role: "separator" }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
      lineNumber: 92,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
    lineNumber: 71,
    columnNumber: 9
  });
}, "SetItems");
const viewIcon = /* @__PURE__ */ __name((view, extraViews = []) => extraViews.find((extra) => extra.value === view)?.icon ?? { auto: "auto", table: "table", cards: "cards" }[view], "viewIcon");
const ViewMenu = /* @__PURE__ */ __name((props) => {
  const { labels } = props;
  const extraViews = props.extraViews ?? [];
  const choices = [
    ["auto", labels.viewauto, labels.viewautohint],
    ["table", labels.table, ""],
    ["cards", labels.cards, ""]
  ].filter(([value]) => props.views.includes(value));
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      className: "enrol_apply-rview-menu",
      id: props.id,
      role: "menu",
      "aria-label": labels.view,
      hidden: props.hidden,
      onKeyDown: menuKeys,
      children: [
        choices.map(([value, label, hint]) => /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "enrol_apply-rview-menu-item",
            role: "menuitemradio",
            "aria-checked": props.view === value,
            tabIndex: -1,
            onClick: () => props.onChoose(value),
            children: [
              /* @__PURE__ */ jsxDEV(Icon, { name: value }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
                lineNumber: 149,
                columnNumber: 21
              }),
              /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: label }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
                lineNumber: 150,
                columnNumber: 21
              }),
              hint !== "" ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-hint", children: hint }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
                lineNumber: 151,
                columnNumber: 36
              }) : null,
              /* @__PURE__ */ jsxDEV(Icon, { name: "tick", className: "enrol_apply-rview-tick" }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
                lineNumber: 152,
                columnNumber: 21
              })
            ]
          },
          value,
          true,
          {
            fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
            lineNumber: 140,
            columnNumber: 17
          }
        )),
        extraViews.length > 0 ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
          /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-menu-sep", role: "separator" }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
            lineNumber: 157,
            columnNumber: 21
          }),
          extraViews.map((extra) => /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "enrol_apply-rview-menu-item",
              role: "menuitemradio",
              "aria-checked": props.view === extra.value,
              tabIndex: -1,
              onClick: () => props.onChoose(extra.value),
              children: [
                /* @__PURE__ */ jsxDEV(Icon, { name: extra.icon }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
                  lineNumber: 168,
                  columnNumber: 29
                }),
                /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: extra.label }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
                  lineNumber: 169,
                  columnNumber: 29
                }),
                /* @__PURE__ */ jsxDEV(Icon, { name: "tick", className: "enrol_apply-rview-tick" }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
                  lineNumber: 170,
                  columnNumber: 29
                })
              ]
            },
            extra.value,
            true,
            {
              fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
              lineNumber: 159,
              columnNumber: 25
            }
          ))
        ] }, void 0, true, {
          fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
          lineNumber: 156,
          columnNumber: 17
        }) : null,
        props.tableurl !== "" ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
          /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-menu-sep", role: "separator" }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
            lineNumber: 177,
            columnNumber: 21
          }),
          /* @__PURE__ */ jsxDEV("a", { className: "enrol_apply-rview-menu-item", role: "menuitem", tabIndex: -1, href: props.tableurl, children: [
            /* @__PURE__ */ jsxDEV(Icon, { name: "table" }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
              lineNumber: 179,
              columnNumber: 25
            }),
            /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: labels.coretable }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
              lineNumber: 180,
              columnNumber: 25
            })
          ] }, void 0, true, {
            fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
            lineNumber: 178,
            columnNumber: 21
          })
        ] }, void 0, true, {
          fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
          lineNumber: 176,
          columnNumber: 17
        }) : null
      ]
    },
    void 0,
    true,
    {
      fileName: "public/enrol/apply/js/esm/src/datareport/menus.tsx",
      lineNumber: 131,
      columnNumber: 9
    }
  );
}, "ViewMenu");
export {
  SetItems,
  ViewMenu,
  menuKeys,
  viewIcon
};
//# sourceMappingURL=menus.dev.js.map
