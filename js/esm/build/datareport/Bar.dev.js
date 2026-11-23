var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The filter bar above the rows (the Design System's filter-bar.md, chip.md, menu.md, filter-panel.md).
 *
 * The scope, the count, a capsule of icon buttons - Filters with its loud counter, Columns, View and,
 * where a view offers one, Download - and the search by name in a capsule of its own, folded to its
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
 * What differs between views arrives as props: the scope's values, the facets and their values with
 * the callback that writes a change where the view keeps its filters, the chips of the filters in
 * force, the column catalogue and its sets, the View choices and the link to the view's server table,
 * and the Download links (none: no Download button). Columns starts with the sets, each handed to the
 * view as the reader's choice, then lists the catalogue, a fixed column always shown, and ends with
 * the default columns and "Reset column widths". A view may put a link of its own at the end of the
 * chips' row (`aside`: the queue's "Open the report"), which then shows with no chip in force too.
 *
 * Strings read from the view's labels: scope, count (and count_one), filters, filterson, columns,
 * presets, always, defaultcolumns, resetwidths, view, viewauto, viewautohint, table, cards, coretable,
 * download, search, cleartext, removefilter, searchchip, clearall, and FilterPanel's.
 *
 * @module     enrol_apply/datareport/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useId, useRef, useState } from "react";
import { FilterPanel } from "./FilterPanel";
import { Icon } from "./icons";
import { SetItems, menuKeys } from "./menus";
import { Segmented } from "./Segmented";
import { fill, fillCount, num } from "./str";
const Bar = /* @__PURE__ */ __name((props) => {
  const { labels, chips, columns } = props;
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
  const searched = props.search.trim().length >= props.searchmin;
  const searchchip = searched && !searchopen;
  const chipcount = chips.length + (searchchip ? 1 : 0);
  const fullcount = props.total === null ? "\u2026" : fillCount(labels, "count", props.total, num(props.total));
  const countText = props.total !== null && props.narrow ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: num(props.total) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
      lineNumber: 237,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: fullcount }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
      lineNumber: 238,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
    lineNumber: 236,
    columnNumber: 9
  }) : fullcount;
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
          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
          lineNumber: 265,
          columnNumber: 13
        }),
        extra
      ]
    },
    void 0,
    true,
    {
      fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
      lineNumber: 252,
      columnNumber: 9
    }
  ), "iconbutton");
  const viewicon = { auto: "auto", table: "table", cards: "cards" }[props.view];
  const chevron = /* @__PURE__ */ jsxDEV(Icon, { name: "chevron", className: "enrol_apply-rview-chevron" }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
    lineNumber: 271,
    columnNumber: 21
  });
  const filtersname = chips.length > 0 ? fill(labels.filterson, num(chips.length)) : labels.filters;
  const badge = chips.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-badge", "aria-hidden": "true", children: num(chips.length) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
    lineNumber: 275,
    columnNumber: 11
  }) : null;
  const viewchoices = [
    ["auto", labels.viewauto, labels.viewautohint],
    ["table", labels.table, ""],
    ["cards", labels.cards, ""]
  ].filter(([value]) => props.views.includes(value));
  const barclass = "enrol_apply-rview-bar" + (props.narrow ? " enrol_apply-rview-bar--narrow" : "") + (searchopen ? " enrol_apply-rview-bar--searching" : "");
  return /* @__PURE__ */ jsxDEV("div", { className: barclass, "data-region": "enrol_apply-bar", ref: root, onKeyDown: escape, children: [
    /* @__PURE__ */ jsxDEV(
      Segmented,
      {
        label: labels.scope,
        value: props.scope,
        options: props.scopes,
        onChange: props.onScope
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
        lineNumber: 285,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-bar-spacer" }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
      lineNumber: 291,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-bar-controls", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-bar-cluster", children: [
        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-bar-count", "data-region": "enrol_apply-total", "aria-live": "polite", children: countText }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
          lineNumber: 295,
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
                  /* @__PURE__ */ jsxDEV(SetItems, { heading: labels.presets, sets: props.sets, onSet: props.onSet }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                    lineNumber: 310,
                    columnNumber: 33
                  }),
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
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 322,
                          columnNumber: 49
                        }) }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 321,
                          columnNumber: 45
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: column.title }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 324,
                          columnNumber: 45
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-hint", children: labels.always }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 325,
                          columnNumber: 45
                        })
                      ]
                    },
                    column.key,
                    true,
                    {
                      fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                      lineNumber: 313,
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
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 338,
                          columnNumber: 49
                        }) }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 337,
                          columnNumber: 45
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: column.title }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 340,
                          columnNumber: 45
                        })
                      ]
                    },
                    column.key,
                    true,
                    {
                      fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                      lineNumber: 328,
                      columnNumber: 41
                    }
                  )),
                  /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-menu-sep", role: "separator" }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                    lineNumber: 344,
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
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 357,
                          columnNumber: 37
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: labels.defaultcolumns }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 358,
                          columnNumber: 37
                        })
                      ]
                    },
                    void 0,
                    true,
                    {
                      fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                      lineNumber: 345,
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
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 372,
                          columnNumber: 37
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: labels.resetwidths }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 373,
                          columnNumber: 37
                        })
                      ]
                    },
                    void 0,
                    true,
                    {
                      fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                      lineNumber: 360,
                      columnNumber: 33
                    }
                  )
                ]
              },
              void 0,
              true,
              {
                fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                lineNumber: 302,
                columnNumber: 29
              }
            )
          ] }, void 0, true, {
            fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
            lineNumber: 300,
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
                  viewchoices.map(([value, label, hint]) => /* @__PURE__ */ jsxDEV(
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
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 401,
                          columnNumber: 41
                        }),
                        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: label }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 402,
                          columnNumber: 41
                        }),
                        hint !== "" ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-hint", children: hint }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 403,
                          columnNumber: 56
                        }) : null,
                        /* @__PURE__ */ jsxDEV(Icon, { name: "tick", className: "enrol_apply-rview-tick" }, void 0, false, {
                          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                          lineNumber: 404,
                          columnNumber: 41
                        })
                      ]
                    },
                    value,
                    true,
                    {
                      fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                      lineNumber: 388,
                      columnNumber: 37
                    }
                  )),
                  props.tableurl !== "" ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
                    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-menu-sep", role: "separator" }, void 0, false, {
                      fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                      lineNumber: 409,
                      columnNumber: 41
                    }),
                    /* @__PURE__ */ jsxDEV(
                      "a",
                      {
                        className: "enrol_apply-rview-menu-item",
                        role: "menuitem",
                        tabIndex: -1,
                        href: props.tableurl,
                        children: [
                          /* @__PURE__ */ jsxDEV(Icon, { name: "table" }, void 0, false, {
                            fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                            lineNumber: 416,
                            columnNumber: 45
                          }),
                          /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: labels.coretable }, void 0, false, {
                            fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                            lineNumber: 417,
                            columnNumber: 45
                          })
                        ]
                      },
                      void 0,
                      true,
                      {
                        fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                        lineNumber: 410,
                        columnNumber: 41
                      }
                    )
                  ] }, void 0, true, {
                    fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                    lineNumber: 408,
                    columnNumber: 37
                  }) : null
                ]
              },
              void 0,
              true,
              {
                fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                lineNumber: 379,
                columnNumber: 29
              }
            )
          ] }, void 0, true, {
            fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
            lineNumber: 377,
            columnNumber: 25
          }),
          props.downloads.length > 0 ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-anchor", children: [
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
                children: props.downloads.map((link) => /* @__PURE__ */ jsxDEV(
                  "a",
                  {
                    className: "enrol_apply-rview-menu-item",
                    role: "menuitem",
                    tabIndex: -1,
                    href: link.href,
                    children: [
                      /* @__PURE__ */ jsxDEV(Icon, { name: "download" }, void 0, false, {
                        fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                        lineNumber: 442,
                        columnNumber: 45
                      }),
                      /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: link.label }, void 0, false, {
                        fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                        lineNumber: 443,
                        columnNumber: 45
                      })
                    ]
                  },
                  link.key,
                  true,
                  {
                    fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                    lineNumber: 435,
                    columnNumber: 41
                  }
                ))
              },
              void 0,
              false,
              {
                fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                lineNumber: 426,
                columnNumber: 33
              }
            )
          ] }, void 0, true, {
            fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
            lineNumber: 424,
            columnNumber: 29
          }) : null
        ] }, void 0, true, {
          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
          lineNumber: 298,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
        lineNumber: 294,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-capsule enrol_apply-rview-searchcap", children: searchopen ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-search", children: [
        props.searching && searched ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-spin", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
          lineNumber: 455,
          columnNumber: 35
        }) : /* @__PURE__ */ jsxDEV(Icon, { name: "search" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
          lineNumber: 456,
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
            fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
            lineNumber: 457,
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
                fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
                lineNumber: 493,
                columnNumber: 37
              })
            },
            void 0,
            false,
            {
              fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
              lineNumber: 482,
              columnNumber: 33
            }
          )
        ) : null
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
        lineNumber: 453,
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
            fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
            lineNumber: 509,
            columnNumber: 29
          })
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
          lineNumber: 498,
          columnNumber: 25
        }
      ) }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
        lineNumber: 451,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV(
        FilterPanel,
        {
          labels,
          id: uid + "filters",
          hidden: layer !== "filters",
          facets: props.facets,
          values: props.values,
          total: props.total,
          note: props.panelnote,
          onChange: props.onFacet,
          onClear: props.onClearFilters,
          onShow: () => {
            setLayer(null);
            triggers.current.filters?.focus();
          }
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
          lineNumber: 514,
          columnNumber: 17
        }
      )
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
      lineNumber: 293,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-chips", "data-region": "enrol_apply-chips", hidden: chipcount === 0 && !props.aside, children: [
      chips.map((chip) => /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "enrol_apply-rview-chip",
          "aria-label": fill(labels.removefilter, chip.text),
          onClick: () => props.onRemoveChip(chip.key),
          children: [
            /* @__PURE__ */ jsxDEV("span", { children: chip.text }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
              lineNumber: 540,
              columnNumber: 25
            }),
            /* @__PURE__ */ jsxDEV(Icon, { name: "close" }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
              lineNumber: 541,
              columnNumber: 25
            })
          ]
        },
        chip.key,
        true,
        {
          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
          lineNumber: 533,
          columnNumber: 21
        }
      )),
      searchchip ? /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "enrol_apply-rview-chip",
          "aria-label": fill(labels.removefilter, fill(labels.searchchip, props.search.trim())),
          onClick: () => props.onSearch(""),
          children: [
            /* @__PURE__ */ jsxDEV("span", { children: fill(labels.searchchip, props.search.trim()) }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
              lineNumber: 551,
              columnNumber: 25
            }),
            /* @__PURE__ */ jsxDEV(Icon, { name: "close" }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
              lineNumber: 552,
              columnNumber: 25
            })
          ]
        },
        void 0,
        true,
        {
          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
          lineNumber: 545,
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
          fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
          lineNumber: 556,
          columnNumber: 21
        }
      ) : null,
      props.aside ?? null
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
      lineNumber: 531,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/datareport/Bar.tsx",
    lineNumber: 284,
    columnNumber: 9
  });
}, "Bar");
export {
  Bar
};
//# sourceMappingURL=Bar.dev.js.map
