var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The filter bar above the rows (the Design System's filter-bar.md, chip.md, menu.md, filter-panel.md).
 *
 * The scope (all, awaiting, decided), the count, a capsule of icon buttons - Filters with its loud
 * counter, Columns, View, Download - and the search by name in a capsule of its own, folded to its
 * button until pressed or `/` is typed; the chips of what is in force on a row of their own, then
 * "Clear all". One layer is open at a time; Escape or a press outside closes it and gives the focus
 * back to its button. Each menu hangs under its own button, the panel under the row of controls (never
 * under the chips); at the narrow step (the view's container under 576 px) the menus hang from the
 * controls' end, the scope takes a line of its own after them, and the open search takes the line.
 *
 * The open search: Escape closes it whatever it holds, the text staying in force, and gives the focus
 * back to its button; leaving it empty closes it too; the eraser clears the text and keeps it open. The
 * text in force shows as a chip only while the field is closed, so it is never said twice.
 *
 * Filters draws the report's own filters that pick one option of a list (core's store, through
 * core_reportbuilder_set_filters); a group filter with more than eight groups is a search over a list
 * capped at fifty, the chosen one pinned on top (section 5b). Columns lists the report's catalogue for
 * this reader, the applicant and the identity fields always shown, and ends with "Reset column widths".
 * View is Automatic, Table or Cards, and core's own table view.
 *
 * @module     enrol_apply/report/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useId, useRef, useState } from "react";
import { choosable, chosen, chosenLabel } from "./filters";
import { Icon } from "./icons";
import { Segmented } from "./Segmented";
import { fill, fillCount, fillObject, num } from "./str";
const FEW = 8;
const CAP = 50;
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
const FilterGroup = /* @__PURE__ */ __name(({ labels, filter, onFilter }) => {
  const headid = useId();
  const [query, setQuery] = useState("");
  const value = chosen(filter);
  const options = [{ value: "", label: labels.any }, ...filter.options];
  if (filter.options.length > FEW) {
    const folded = query.trim().toLocaleLowerCase();
    const pinned = filter.options.filter((option) => option.value === value);
    const rest = filter.options.filter((option) => option.value !== value && (folded === "" || option.label.toLocaleLowerCase().includes(folded)));
    return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-fgroup", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-fpanel-head", id: headid, children: filter.title }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 134,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(
        "input",
        {
          type: "search",
          className: "form-control form-control-sm enrol_apply-rview-input",
          placeholder: fill(labels.searchgroups, num(filter.options.length)),
          "aria-label": fill(labels.searchgroups, num(filter.options.length)),
          value: query,
          onChange: (event) => setQuery(event.target.value)
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 135,
          columnNumber: 17
        }
      ),
      /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-optlist", role: "group", "aria-labelledby": headid, children: [
        [...pinned, ...rest.slice(0, CAP)].map((option) => /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "enrol_apply-rview-optrow",
            "aria-pressed": option.value === value,
            onClick: () => onFilter(filter, option.value === value ? "" : option.value),
            children: [
              /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-box", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV(Icon, { name: "tick" }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 152,
                columnNumber: 88
              }) }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 152,
                columnNumber: 29
              }),
              /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-optrow-label", children: option.label }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 153,
                columnNumber: 29
              })
            ]
          },
          option.value,
          true,
          {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 145,
            columnNumber: 25
          }
        )),
        rest.length > CAP ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-note", children: fill(labels.moregroups, num(rest.length - CAP)) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 157,
          columnNumber: 27
        }) : null,
        pinned.length === 0 && rest.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-note", children: labels.nogroupmatch }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 160,
          columnNumber: 27
        }) : null
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 143,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 133,
      columnNumber: 13
    });
  }
  const keydown = /* @__PURE__ */ __name((event) => {
    const at = options.findIndex((option) => option.value === value);
    let next = -1;
    if (event.key === "ArrowRight" || event.key === "ArrowDown") {
      next = (at + 1) % options.length;
    } else if (event.key === "ArrowLeft" || event.key === "ArrowUp") {
      next = (at - 1 + options.length) % options.length;
    } else if (event.key === "Home") {
      next = 0;
    } else if (event.key === "End") {
      next = options.length - 1;
    }
    if (next < 0) {
      return;
    }
    event.preventDefault();
    onFilter(filter, options[next].value);
    event.currentTarget.querySelectorAll('[role="radio"]')[next]?.focus();
  }, "keydown");
  return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-fgroup", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-fpanel-head", id: headid, children: filter.title }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 195,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-fpanel-opts", role: "radiogroup", "aria-labelledby": headid, onKeyDown: keydown, children: options.map((option) => /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: "enrol_apply-rview-fpanel-opt",
        role: "radio",
        "aria-checked": option.value === value,
        tabIndex: option.value === value ? 0 : -1,
        onClick: () => onFilter(filter, option.value),
        children: option.label
      },
      option.value,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 198,
        columnNumber: 21
      }
    )) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 196,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
    lineNumber: 194,
    columnNumber: 9
  });
}, "FilterGroup");
const Bar = /* @__PURE__ */ __name((props) => {
  const { labels, filters, columns } = props;
  const uid = useId();
  const root = useRef(null);
  const searchinput = useRef(null);
  const searchbutton = useRef(null);
  const refocus = useRef(false);
  const [layer, setLayer] = useState(null);
  const [searchopen, setSearchopen] = useState(props.search !== "");
  const triggers = useRef({});
  useEffect(() => {
    if (layer === null) {
      return void 0;
    }
    const outside = /* @__PURE__ */ __name((event) => {
      if (root.current && !root.current.contains(event.target)) {
        setLayer(null);
      }
    }, "outside");
    document.addEventListener("mousedown", outside);
    return () => document.removeEventListener("mousedown", outside);
  }, [layer]);
  useEffect(() => {
    if (layer === null || layer === "filters") {
      return;
    }
    const menu = root.current?.querySelector(`#${CSS.escape(uid + layer)}`);
    const item = menu?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])') ?? menu?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])');
    item?.focus();
  }, [layer, uid]);
  useEffect(() => {
    if (searchopen) {
      searchinput.current?.focus();
    } else if (refocus.current) {
      refocus.current = false;
      searchbutton.current?.focus();
    }
  }, [searchopen]);
  useEffect(() => {
    const slash = /* @__PURE__ */ __name((event) => {
      if (event.key !== "/" || event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented) {
        return;
      }
      const focused = document.activeElement;
      if (focused && (focused.isContentEditable || focused.closest("input, textarea, select"))) {
        return;
      }
      const rect = root.current?.getBoundingClientRect();
      if (!rect || rect.width === 0 || rect.bottom <= 0 || rect.top >= window.innerHeight) {
        return;
      }
      event.preventDefault();
      if (searchopen) {
        searchinput.current?.focus();
      } else {
        setLayer(null);
        setSearchopen(true);
      }
    }, "slash");
    document.addEventListener("keydown", slash);
    return () => document.removeEventListener("keydown", slash);
  }, [searchopen]);
  const toggle = /* @__PURE__ */ __name((which) => setLayer(layer === which ? null : which), "toggle");
  const closeSearch = /* @__PURE__ */ __name((back) => {
    refocus.current = back;
    setSearchopen(false);
  }, "closeSearch");
  const escape = /* @__PURE__ */ __name((event) => {
    if (event.key !== "Escape" || event.defaultPrevented) {
      return;
    }
    if (layer !== null) {
      event.preventDefault();
      const trigger = triggers.current[layer];
      setLayer(null);
      trigger?.focus();
    } else if (searchopen) {
      event.preventDefault();
      closeSearch(true);
    }
  }, "escape");
  const applied = filters.filter((filter) => filter.applied);
  const searched = props.search.trim().length >= props.searchmin;
  const searchchip = searched && !searchopen;
  const chipcount = applied.length + (searchchip ? 1 : 0);
  const fullcount = props.total === null ? "\u2026" : fillCount(labels, "count", props.total, num(props.total));
  const countText = props.total !== null && props.narrow ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: num(props.total) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 359,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: fullcount }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 360,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
    lineNumber: 358,
    columnNumber: 9
  }) : fullcount;
  const panelfilters = filters.filter(choosable);
  const others = filters.filter((filter) => !choosable(filter));
  const iconbutton = /* @__PURE__ */ __name((which, icon, name, extra) => /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      className: "enrol_apply-rview-iconbtn",
      "aria-label": name,
      "data-enrol-apply-tip": name,
      "aria-haspopup": which === "filters" ? "dialog" : "menu",
      "aria-expanded": layer === which,
      "aria-controls": uid + which,
      ref: (element) => {
        triggers.current[which ?? ""] = element;
      },
      onClick: () => toggle(which),
      children: [
        /* @__PURE__ */ jsxDEV(Icon, { name: icon }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 389,
          columnNumber: 13
        }),
        extra
      ]
    },
    void 0,
    true,
    {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 376,
      columnNumber: 9
    }
  ), "iconbutton");
  const viewicon = { auto: "auto", table: "table", cards: "cards" }[props.view];
  const chevron = /* @__PURE__ */ jsxDEV(Icon, { name: "chevron", className: "enrol_apply-rview-chevron" }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
    lineNumber: 395,
    columnNumber: 21
  });
  const filtersname = applied.length > 0 ? fill(labels.filterson, num(applied.length)) : labels.filters;
  const badge = applied.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-badge", "aria-hidden": "true", children: num(applied.length) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
    lineNumber: 398,
    columnNumber: 11
  }) : null;
  const download = props.downloadurl !== "" && props.formats.length > 0;
  const parameters = encodeURIComponent(JSON.stringify({ measure: props.measure }));
  const barclass = "enrol_apply-rview-bar" + (props.narrow ? " enrol_apply-rview-bar--narrow" : "") + (searchopen ? " enrol_apply-rview-bar--searching" : "");
  return /* @__PURE__ */ jsxDEV("div", { className: barclass, "data-region": "enrol_apply-bar", ref: root, onKeyDown: escape, children: [
    /* @__PURE__ */ jsxDEV(
      Segmented,
      {
        label: labels.scope,
        value: props.scope,
        options: [
          { value: "all", label: labels.all },
          { value: "awaiting", label: labels.awaiting },
          { value: "decided", label: labels.decided }
        ],
        onChange: (value) => props.onScope(value)
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 408,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-bar-spacer" }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 418,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-bar-controls", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-bar-cluster", children: [
        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-bar-count", "data-region": "enrol_apply-total", "aria-live": "polite", children: countText }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 422,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-capsule", children: [
          iconbutton("filters", "filter", filtersname, badge),
          /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-anchor", children: [
            iconbutton("columns", "columns", labels.columns, chevron),
            /* @__PURE__ */ jsxDEV(
              "div",
              {
                className: "enrol_apply-rview-menu",
                id: uid + "columns",
                role: "menu",
                "aria-label": labels.columns,
                hidden: layer !== "columns",
                onKeyDown: menuKeys,
                children: [
                  columns.map((column) => column.fixed ? /* @__PURE__ */ jsxDEV(
                    "div",
                    {
                      className: "enrol_apply-rview-menu-item",
                      role: "menuitemcheckbox",
                      "aria-checked": "true",
                      "aria-disabled": "true",
                      tabIndex: -1,
                      children: [
                        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-box", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV(Icon, { name: "tick" }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 448,
                          columnNumber: 49
                        }) }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 447,
                          columnNumber: 45
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: column.title }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 450,
                          columnNumber: 45
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-hint", children: labels.always }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 451,
                          columnNumber: 45
                        })
                      ]
                    },
                    column.key,
                    true,
                    {
                      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                      lineNumber: 439,
                      columnNumber: 41
                    }
                  ) : /* @__PURE__ */ jsxDEV(
                    "button",
                    {
                      type: "button",
                      className: "enrol_apply-rview-menu-item",
                      role: "menuitemcheckbox",
                      "aria-checked": column.active,
                      tabIndex: -1,
                      onClick: () => props.onColumn(column.key, !column.active),
                      children: [
                        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-box", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV(Icon, { name: "tick" }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 464,
                          columnNumber: 49
                        }) }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 463,
                          columnNumber: 45
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: column.title }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 466,
                          columnNumber: 45
                        })
                      ]
                    },
                    column.key,
                    true,
                    {
                      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                      lineNumber: 454,
                      columnNumber: 41
                    }
                  )),
                  /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-menu-sep", role: "separator" }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                    lineNumber: 470,
                    columnNumber: 33
                  }),
                  /* @__PURE__ */ jsxDEV(
                    "button",
                    {
                      type: "button",
                      className: "enrol_apply-rview-menu-item",
                      role: "menuitem",
                      tabIndex: -1,
                      "aria-disabled": props.usedefault,
                      onClick: () => {
                        if (!props.usedefault) {
                          props.onDefaultColumns();
                        }
                      },
                      children: [
                        /* @__PURE__ */ jsxDEV(Icon, { name: "columns" }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 483,
                          columnNumber: 37
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: labels.defaultcolumns }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 484,
                          columnNumber: 37
                        })
                      ]
                    },
                    void 0,
                    true,
                    {
                      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                      lineNumber: 471,
                      columnNumber: 33
                    }
                  ),
                  /* @__PURE__ */ jsxDEV(
                    "button",
                    {
                      type: "button",
                      className: "enrol_apply-rview-menu-item",
                      role: "menuitem",
                      tabIndex: -1,
                      "aria-disabled": !props.haswidths,
                      onClick: () => {
                        if (props.haswidths) {
                          props.onResetWidths();
                        }
                      },
                      children: [
                        /* @__PURE__ */ jsxDEV(Icon, { name: "reset" }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 498,
                          columnNumber: 37
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: labels.resetwidths }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 499,
                          columnNumber: 37
                        })
                      ]
                    },
                    void 0,
                    true,
                    {
                      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                      lineNumber: 486,
                      columnNumber: 33
                    }
                  )
                ]
              },
              void 0,
              true,
              {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 429,
                columnNumber: 29
              }
            )
          ] }, void 0, true, {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 427,
            columnNumber: 25
          }),
          /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-anchor", children: [
            iconbutton("view", viewicon, labels.view, chevron),
            /* @__PURE__ */ jsxDEV(
              "div",
              {
                className: "enrol_apply-rview-menu",
                id: uid + "view",
                role: "menu",
                "aria-label": labels.view,
                hidden: layer !== "view",
                onKeyDown: menuKeys,
                children: [
                  [
                    ["auto", labels.viewauto, labels.viewautohint],
                    ["table", labels.table, ""],
                    ["cards", labels.cards, ""]
                  ].map(([value, label, hint]) => /* @__PURE__ */ jsxDEV(
                    "button",
                    {
                      type: "button",
                      className: "enrol_apply-rview-menu-item",
                      role: "menuitemradio",
                      "aria-checked": props.view === value,
                      tabIndex: -1,
                      onClick: () => {
                        props.onView(value);
                        setLayer(null);
                        triggers.current.view?.focus();
                      },
                      children: [
                        /* @__PURE__ */ jsxDEV(Icon, { name: value }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 528,
                          columnNumber: 41
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: label }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 529,
                          columnNumber: 41
                        }),
                        hint !== "" ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-hint", children: hint }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 530,
                          columnNumber: 56
                        }) : null,
                        /* @__PURE__ */ jsxDEV(Icon, { name: "tick", className: "enrol_apply-rview-tick" }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                          lineNumber: 531,
                          columnNumber: 41
                        })
                      ]
                    },
                    value,
                    true,
                    {
                      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                      lineNumber: 515,
                      columnNumber: 37
                    }
                  )),
                  /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-menu-sep", role: "separator" }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                    lineNumber: 534,
                    columnNumber: 33
                  }),
                  /* @__PURE__ */ jsxDEV("a", { className: "enrol_apply-rview-menu-item", role: "menuitem", tabIndex: -1, href: props.tableurl, children: [
                    /* @__PURE__ */ jsxDEV(Icon, { name: "table" }, void 0, false, {
                      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                      lineNumber: 536,
                      columnNumber: 37
                    }),
                    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: labels.coretable }, void 0, false, {
                      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                      lineNumber: 537,
                      columnNumber: 37
                    })
                  ] }, void 0, true, {
                    fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                    lineNumber: 535,
                    columnNumber: 33
                  })
                ]
              },
              void 0,
              true,
              {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 505,
                columnNumber: 29
              }
            )
          ] }, void 0, true, {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 503,
            columnNumber: 25
          }),
          download ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-anchor", children: [
            iconbutton("download", "download", labels.download, chevron),
            /* @__PURE__ */ jsxDEV(
              "div",
              {
                className: "enrol_apply-rview-menu",
                id: uid + "download",
                role: "menu",
                "aria-label": labels.download,
                hidden: layer !== "download",
                onKeyDown: menuKeys,
                children: props.formats.map((format) => /* @__PURE__ */ jsxDEV(
                  "a",
                  {
                    className: "enrol_apply-rview-menu-item",
                    role: "menuitem",
                    tabIndex: -1,
                    href: `${props.downloadurl}&parameters=${parameters}&download=${encodeURIComponent(format.value)}`,
                    children: [
                      /* @__PURE__ */ jsxDEV(Icon, { name: "download" }, void 0, false, {
                        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                        lineNumber: 561,
                        columnNumber: 45
                      }),
                      /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: format.label }, void 0, false, {
                        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                        lineNumber: 562,
                        columnNumber: 45
                      })
                    ]
                  },
                  format.value,
                  true,
                  {
                    fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                    lineNumber: 553,
                    columnNumber: 41
                  }
                ))
              },
              void 0,
              false,
              {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 544,
                columnNumber: 33
              }
            )
          ] }, void 0, true, {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 542,
            columnNumber: 29
          }) : null
        ] }, void 0, true, {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 425,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 421,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-capsule enrol_apply-rview-searchcap", children: searchopen ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-search", children: [
        props.searching && searched ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-spin", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 574,
          columnNumber: 35
        }) : /* @__PURE__ */ jsxDEV(Icon, { name: "search" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 575,
          columnNumber: 35
        }),
        /* @__PURE__ */ jsxDEV(
          "input",
          {
            ref: searchinput,
            type: "search",
            "aria-label": labels.search,
            placeholder: labels.search,
            autoComplete: "off",
            value: props.search,
            onChange: (event) => props.onSearch(event.target.value),
            onKeyDown: (event) => {
              if (event.key === "Escape") {
                event.preventDefault();
                closeSearch(true);
              }
            },
            onBlur: () => {
              if (props.search === "") {
                closeSearch(false);
              }
            }
          },
          void 0,
          false,
          {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 576,
            columnNumber: 29
          }
        ),
        props.search !== "" ? (
          // The eraser keeps the field's focus on a press: it clears the text and the
          // field stays open.
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "enrol_apply-rview-eraser",
              "aria-label": labels.cleartext,
              "data-enrol-apply-tip": labels.cleartext,
              onMouseDown: (event) => event.preventDefault(),
              onClick: () => {
                props.onSearch("");
                searchinput.current?.focus();
              },
              children: /* @__PURE__ */ jsxDEV(Icon, { name: "eraser" }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 612,
                columnNumber: 37
              })
            },
            void 0,
            false,
            {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 601,
              columnNumber: 33
            }
          )
        ) : null
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 572,
        columnNumber: 25
      }) : /* @__PURE__ */ jsxDEV(
        "button",
        {
          ref: searchbutton,
          type: "button",
          className: "enrol_apply-rview-iconbtn",
          "aria-label": labels.search,
          "data-enrol-apply-tip": labels.search,
          onClick: () => {
            setLayer(null);
            setSearchopen(true);
          },
          children: /* @__PURE__ */ jsxDEV(Icon, { name: "search" }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 628,
            columnNumber: 29
          })
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 617,
          columnNumber: 25
        }
      ) }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 570,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(
        "div",
        {
          className: "enrol_apply-rview-fpanel",
          id: uid + "filters",
          role: "dialog",
          "aria-label": labels.filters,
          hidden: layer !== "filters",
          children: [
            /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-fpanel-body", children: [
              panelfilters.map((filter) => /* @__PURE__ */ jsxDEV(FilterGroup, { labels, filter, onFilter: props.onFilter }, filter.name, false, {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 642,
                columnNumber: 29
              })),
              others.length > 0 ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-note", children: [
                labels.morefiltershelp,
                " ",
                /* @__PURE__ */ jsxDEV("a", { href: props.tableurl, children: labels.morefilters }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                  lineNumber: 647,
                  columnNumber: 33
                })
              ] }, void 0, true, {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 645,
                columnNumber: 29
              }) : null
            ] }, void 0, true, {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 640,
              columnNumber: 21
            }),
            /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-fpanel-foot", children: [
              /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: props.onClearFilters, children: labels.clearfilters }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 652,
                columnNumber: 25
              }),
              /* @__PURE__ */ jsxDEV(
                "button",
                {
                  type: "button",
                  className: "btn btn-sm btn-primary",
                  onClick: () => {
                    setLayer(null);
                    triggers.current.filters?.focus();
                  },
                  children: labels.show
                },
                void 0,
                false,
                {
                  fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                  lineNumber: 655,
                  columnNumber: 25
                }
              )
            ] }, void 0, true, {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 651,
              columnNumber: 21
            })
          ]
        },
        void 0,
        true,
        {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 633,
          columnNumber: 17
        }
      )
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 420,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-chips", "data-region": "enrol_apply-chips", hidden: chipcount === 0, children: [
      applied.map((filter) => {
        const value = chosenLabel(filter);
        const text = value === "" ? filter.title : fillObject(labels.filterchip, { title: filter.title, value });
        return /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            className: "enrol_apply-rview-chip",
            "aria-label": fill(labels.removefilter, text),
            onClick: () => props.onRemoveFilter(filter),
            children: [
              /* @__PURE__ */ jsxDEV("span", { children: text }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 681,
                columnNumber: 29
              }),
              /* @__PURE__ */ jsxDEV(Icon, { name: "close" }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 682,
                columnNumber: 29
              })
            ]
          },
          filter.name,
          true,
          {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 674,
            columnNumber: 25
          }
        );
      }),
      searchchip ? /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "enrol_apply-rview-chip",
          "aria-label": fill(labels.removefilter, fill(labels.searchchip, props.search.trim())),
          onClick: () => props.onSearch(""),
          children: [
            /* @__PURE__ */ jsxDEV("span", { children: fill(labels.searchchip, props.search.trim()) }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 693,
              columnNumber: 25
            }),
            /* @__PURE__ */ jsxDEV(Icon, { name: "close" }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 694,
              columnNumber: 25
            })
          ]
        },
        void 0,
        true,
        {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 687,
          columnNumber: 21
        }
      ) : null,
      chipcount > 1 ? /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "enrol_apply-rview-clearall",
          onClick: () => {
            props.onClearAll();
            setSearchopen(false);
          },
          children: labels.clearall
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 698,
          columnNumber: 21
        }
      ) : null
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 669,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
    lineNumber: 407,
    columnNumber: 9
  });
}, "Bar");
export {
  Bar
};
//# sourceMappingURL=Bar.dev.js.map
