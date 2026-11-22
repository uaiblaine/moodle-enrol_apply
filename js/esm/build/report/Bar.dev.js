var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The filter bar above the rows (the Design System's filter-bar.md, chip.md, menu.md, filter-panel.md).
 *
 * The scope (all, awaiting, decided), the count, a capsule of icon buttons - Filters with its loud
 * counter, Columns, View, Download - and the search by name in a capsule of its own, folded to its
 * button until pressed; the chips of what is in force on a row of their own, then "Clear all". One
 * layer is open at a time; Escape or a press outside closes it and gives the focus back to its button.
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
        lineNumber: 126,
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
          lineNumber: 127,
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
                lineNumber: 144,
                columnNumber: 88
              }) }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 144,
                columnNumber: 29
              }),
              /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-optrow-label", children: option.label }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 145,
                columnNumber: 29
              })
            ]
          },
          option.value,
          true,
          {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 137,
            columnNumber: 25
          }
        )),
        rest.length > CAP ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-note", children: fill(labels.moregroups, num(rest.length - CAP)) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 149,
          columnNumber: 27
        }) : null,
        pinned.length === 0 && rest.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-note", children: labels.nogroupmatch }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 152,
          columnNumber: 27
        }) : null
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 135,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 125,
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
      lineNumber: 187,
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
        lineNumber: 190,
        columnNumber: 21
      }
    )) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 188,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
    lineNumber: 186,
    columnNumber: 9
  });
}, "FilterGroup");
const Bar = /* @__PURE__ */ __name((props) => {
  const { labels, filters, columns } = props;
  const uid = useId();
  const root = useRef(null);
  const searchinput = useRef(null);
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
    }
  }, [searchopen]);
  const toggle = /* @__PURE__ */ __name((which) => setLayer(layer === which ? null : which), "toggle");
  const escape = /* @__PURE__ */ __name((event) => {
    if (event.key !== "Escape" || layer === null) {
      return;
    }
    event.preventDefault();
    const trigger = triggers.current[layer];
    setLayer(null);
    trigger?.focus();
  }, "escape");
  const applied = filters.filter((filter) => filter.applied);
  const searched = props.search.trim().length >= props.searchmin;
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
          lineNumber: 312,
          columnNumber: 13
        }),
        extra
      ]
    },
    void 0,
    true,
    {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 299,
      columnNumber: 9
    }
  ), "iconbutton");
  const viewicon = { auto: "auto", table: "table", cards: "cards" }[props.view];
  const chevron = /* @__PURE__ */ jsxDEV(Icon, { name: "chevron", className: "enrol_apply-rview-chevron" }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
    lineNumber: 318,
    columnNumber: 21
  });
  const filtersname = applied.length > 0 ? fill(labels.filterson, num(applied.length)) : labels.filters;
  const badge = applied.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-badge", "aria-hidden": "true", children: num(applied.length) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
    lineNumber: 321,
    columnNumber: 11
  }) : null;
  const download = props.downloadurl !== "" && props.formats.length > 0;
  const parameters = encodeURIComponent(JSON.stringify({ measure: props.measure }));
  return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-bar", "data-region": "enrol_apply-bar", ref: root, onKeyDown: escape, children: [
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
        lineNumber: 328,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-bar-spacer" }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 338,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-bar-cluster", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-bar-count", "data-region": "enrol_apply-total", "aria-live": "polite", children: props.total === null ? "\u2026" : fillCount(labels, "count", props.total, num(props.total)) }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 340,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-capsule", children: [
        iconbutton("filters", "filter", filtersname, badge),
        iconbutton("columns", "columns", labels.columns, chevron),
        iconbutton("view", viewicon, labels.view, chevron),
        download ? iconbutton("download", "download", labels.download, null) : null
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 343,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 339,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-capsule", children: searchopen ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-search", children: [
      props.searching && searched ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-spin", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 354,
        columnNumber: 31
      }) : /* @__PURE__ */ jsxDEV(Icon, { name: "search" }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 355,
        columnNumber: 31
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
              if (props.search === "") {
                setSearchopen(false);
              } else {
                props.onSearch("");
              }
            }
          },
          onBlur: () => {
            if (props.search === "") {
              setSearchopen(false);
            }
          }
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 356,
          columnNumber: 25
        }
      ),
      props.search !== "" ? /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "enrol_apply-rview-eraser",
          "aria-label": labels.cleartext,
          onMouseDown: (event) => event.preventDefault(),
          onClick: () => {
            props.onSearch("");
            searchinput.current?.focus();
          },
          children: /* @__PURE__ */ jsxDEV(Icon, { name: "close" }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 391,
            columnNumber: 33
          })
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 381,
          columnNumber: 29
        }
      ) : null
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 352,
      columnNumber: 21
    }) : /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: "enrol_apply-rview-iconbtn",
        "aria-label": labels.search,
        "data-enrol-apply-tip": labels.search,
        onClick: () => setSearchopen(true),
        children: /* @__PURE__ */ jsxDEV(Icon, { name: "search" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 403,
          columnNumber: 25
        })
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 396,
        columnNumber: 21
      }
    ) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 350,
      columnNumber: 13
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
              lineNumber: 417,
              columnNumber: 25
            })),
            others.length > 0 ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-note", children: [
              labels.morefiltershelp,
              " ",
              /* @__PURE__ */ jsxDEV("a", { href: props.tableurl, children: labels.morefilters }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 422,
                columnNumber: 29
              })
            ] }, void 0, true, {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 420,
              columnNumber: 25
            }) : null
          ] }, void 0, true, {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 415,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-fpanel-foot", children: [
            /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: props.onClearFilters, children: labels.clearfilters }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 427,
              columnNumber: 21
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
                lineNumber: 430,
                columnNumber: 21
              }
            )
          ] }, void 0, true, {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 426,
            columnNumber: 17
          })
        ]
      },
      void 0,
      true,
      {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 408,
        columnNumber: 13
      }
    ),
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
                  lineNumber: 461,
                  columnNumber: 88
                }) }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                  lineNumber: 461,
                  columnNumber: 29
                }),
                /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: column.title }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                  lineNumber: 462,
                  columnNumber: 29
                }),
                /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-hint", children: labels.always }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                  lineNumber: 463,
                  columnNumber: 29
                })
              ]
            },
            column.key,
            true,
            {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 453,
              columnNumber: 25
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
                  lineNumber: 475,
                  columnNumber: 88
                }) }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                  lineNumber: 475,
                  columnNumber: 29
                }),
                /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: column.title }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                  lineNumber: 476,
                  columnNumber: 29
                })
              ]
            },
            column.key,
            true,
            {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 466,
              columnNumber: 25
            }
          )),
          /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-menu-sep", role: "separator" }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 480,
            columnNumber: 17
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
                  lineNumber: 493,
                  columnNumber: 21
                }),
                /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: labels.defaultcolumns }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                  lineNumber: 494,
                  columnNumber: 21
                })
              ]
            },
            void 0,
            true,
            {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 481,
              columnNumber: 17
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
                  lineNumber: 508,
                  columnNumber: 21
                }),
                /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: labels.resetwidths }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                  lineNumber: 509,
                  columnNumber: 21
                })
              ]
            },
            void 0,
            true,
            {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 496,
              columnNumber: 17
            }
          )
        ]
      },
      void 0,
      true,
      {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 443,
        columnNumber: 13
      }
    ),
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
          [["auto", labels.viewauto, labels.viewautohint], ["table", labels.table, ""], ["cards", labels.cards, ""]].map(([value, label, hint]) => /* @__PURE__ */ jsxDEV(
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
                  lineNumber: 536,
                  columnNumber: 25
                }),
                /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: label }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                  lineNumber: 537,
                  columnNumber: 25
                }),
                hint !== "" ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-hint", children: hint }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                  lineNumber: 538,
                  columnNumber: 40
                }) : null,
                /* @__PURE__ */ jsxDEV(Icon, { name: "tick", className: "enrol_apply-rview-tick" }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                  lineNumber: 539,
                  columnNumber: 25
                })
              ]
            },
            value,
            true,
            {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 523,
              columnNumber: 21
            }
          )),
          /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-menu-sep", role: "separator" }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 542,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("a", { className: "enrol_apply-rview-menu-item", role: "menuitem", tabIndex: -1, href: props.tableurl, children: [
            /* @__PURE__ */ jsxDEV(Icon, { name: "table" }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 544,
              columnNumber: 21
            }),
            /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: labels.coretable }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 545,
              columnNumber: 21
            })
          ] }, void 0, true, {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 543,
            columnNumber: 17
          })
        ]
      },
      void 0,
      true,
      {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 513,
        columnNumber: 13
      }
    ),
    download ? /* @__PURE__ */ jsxDEV(
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
                lineNumber: 566,
                columnNumber: 29
              }),
              /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-menu-label", children: format.label }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 567,
                columnNumber: 29
              })
            ]
          },
          format.value,
          true,
          {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 559,
            columnNumber: 25
          }
        ))
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 550,
        columnNumber: 17
      }
    ) : null,
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-chips", "data-region": "enrol_apply-chips", hidden: applied.length === 0 && !searched, children: [
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
                lineNumber: 585,
                columnNumber: 29
              }),
              /* @__PURE__ */ jsxDEV(Icon, { name: "close" }, void 0, false, {
                fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
                lineNumber: 586,
                columnNumber: 29
              })
            ]
          },
          filter.name,
          true,
          {
            fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
            lineNumber: 578,
            columnNumber: 25
          }
        );
      }),
      searched ? /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "enrol_apply-rview-chip",
          "aria-label": fill(labels.removefilter, fill(labels.searchchip, props.search.trim())),
          onClick: () => {
            props.onSearch("");
            setSearchopen(false);
          },
          children: [
            /* @__PURE__ */ jsxDEV("span", { children: fill(labels.searchchip, props.search.trim()) }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 600,
              columnNumber: 25
            }),
            /* @__PURE__ */ jsxDEV(Icon, { name: "close" }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
              lineNumber: 601,
              columnNumber: 25
            })
          ]
        },
        void 0,
        true,
        {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 591,
          columnNumber: 21
        }
      ) : null,
      applied.length + (searched ? 1 : 0) > 1 ? /* @__PURE__ */ jsxDEV(
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
          lineNumber: 605,
          columnNumber: 21
        }
      ) : null
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 573,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
    lineNumber: 327,
    columnNumber: 9
  });
}, "Bar");
export {
  Bar
};
//# sourceMappingURL=Bar.dev.js.map
