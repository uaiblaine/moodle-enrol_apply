var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The queue's top tiles (CQ-D10): the method's capacity and the reader's queue, from enrol_apply_queue_summary.
 *
 * Places taken against the places, and applications held against the applicant limit, each only where
 * the method sets one, with a meter, as the table page's capacity panel shows them (and "on this method"
 * for a reader narrowed by separate groups, whose queue is not the method's); the applications awaiting
 * a decision, with the oldest wait and how many have waited longer than a week, in the warning tone when
 * any has; open or closed, with the closing date while open. The summary does not move with a filter,
 * so the tiles load once.
 *
 * The site-wide queue's tiles are its own (SQ-D7, {@link WideTiles}): what awaits, what is deferred and its
 * share of what awaits, and for a site-wide reader the courses (and methods) holding an application and the
 * methods at their limit.
 *
 * @module     enrol_apply/queue/Tiles
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { duration, fill, fillObject, num } from "../datareport/str";
const percent = /* @__PURE__ */ __name((value, total) => Math.min(100, Math.max(0, Math.round(value * 100 / total))), "percent");
const Tile = /* @__PURE__ */ __name(({ label, value, sub, meter, warn }) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-tile" + (warn ? " enrol_apply-rview-tile--warn" : ""), role: "listitem", children: [
  /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-tile-label", children: label }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
    lineNumber: 66,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-tile-value", children: value }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
    lineNumber: 67,
    columnNumber: 9
  }),
  meter !== null ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-qview-meter" + (warn ? " enrol_apply-qview-meter--warn" : ""), "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV("span", { style: { width: `${meter}%` } }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
    lineNumber: 70,
    columnNumber: 17
  }) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
    lineNumber: 69,
    columnNumber: 13
  }) : null,
  sub !== "" ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-tile-sub", children: sub }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
    lineNumber: 73,
    columnNumber: 23
  }) : null
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
  lineNumber: 65,
  columnNumber: 5
}), "Tile");
const shortDate = /* @__PURE__ */ __name((time) => {
  const lang = document.documentElement.lang || void 0;
  try {
    return new Intl.DateTimeFormat(lang, { dateStyle: "short" }).format(new Date(time * 1e3));
  } catch {
    return new Date(time * 1e3).toLocaleDateString();
  }
}, "shortDate");
const AwaitingTile = /* @__PURE__ */ __name(({ labels, summary }) => {
  const oldest = summary.awaiting > 0 ? fill(labels.oldest, duration(labels, summary.oldestwait)) : "";
  const late = summary.late > 0 ? fill(labels.late, num(summary.late)) : "";
  return /* @__PURE__ */ jsxDEV(
    Tile,
    {
      label: labels.awaiting,
      value: num(summary.awaiting),
      sub: [oldest, late].filter((part) => part !== "").join(" \xB7 "),
      meter: null,
      warn: summary.late > 0
    },
    void 0,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
      lineNumber: 103,
      columnNumber: 9
    }
  );
}, "AwaitingTile");
const WideTiles = /* @__PURE__ */ __name(({ labels, summary }) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-band", role: "list", "data-region": "enrol_apply-tiles", children: [
  /* @__PURE__ */ jsxDEV(AwaitingTile, { labels, summary }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
    lineNumber: 122,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV(
    Tile,
    {
      label: labels.deferred,
      value: num(summary.deferred),
      sub: summary.awaiting > 0 ? fill(labels.deferredshare, num(percent(summary.deferred, summary.awaiting))) : "",
      meter: null,
      warn: false
    },
    void 0,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
      lineNumber: 123,
      columnNumber: 9
    }
  ),
  summary.sitefigures ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV(
      Tile,
      {
        label: labels.courses,
        value: num(summary.courses),
        sub: summary.methods === 1 ? labels.methods_one : fill(labels.methods, num(summary.methods)),
        meter: null,
        warn: false
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
        lineNumber: 132,
        columnNumber: 17
      }
    ),
    /* @__PURE__ */ jsxDEV(
      Tile,
      {
        label: labels.methodsfull,
        value: num(summary.methodsfull),
        sub: labels.methodsfullsub,
        meter: null,
        warn: summary.methodsfull > 0
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
        lineNumber: 139,
        columnNumber: 17
      }
    )
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
    lineNumber: 131,
    columnNumber: 13
  }) : null
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
  lineNumber: 121,
  columnNumber: 5
}), "WideTiles");
const Tiles = /* @__PURE__ */ __name(({ labels, kind, summary, error, onRetry }) => {
  if (summary === null && error) {
    return /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-error", children: [
      labels.loaderror,
      " ",
      /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: onRetry, children: labels.retry }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
        lineNumber: 162,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
      lineNumber: 160,
      columnNumber: 13
    });
  }
  if (summary === null) {
    return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-band", "aria-busy": "true", children: [
      Array.from({ length: 4 }, (_, i) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-tile enrol_apply-rview-sk", children: [
        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb enrol_apply-rview-skb--w60" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
          lineNumber: 171,
          columnNumber: 25
        }),
        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb enrol_apply-rview-skb--big" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
          lineNumber: 172,
          columnNumber: 25
        })
      ] }, i, true, {
        fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
        lineNumber: 170,
        columnNumber: 21
      })),
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", role: "status", children: labels.loadsummary }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
        lineNumber: 175,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
      lineNumber: 168,
      columnNumber: 13
    });
  }
  if (kind !== "method") {
    return /* @__PURE__ */ jsxDEV(WideTiles, { labels, summary }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
      lineNumber: 181,
      columnNumber: 16
    });
  }
  const methodnote = summary.narrowed ? labels.methodnote : "";
  return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-band", role: "list", "data-region": "enrol_apply-tiles", children: [
    summary.places > 0 ? /* @__PURE__ */ jsxDEV(
      Tile,
      {
        label: labels.placestaken,
        value: fillObject(labels.ofmany, { taken: num(summary.placestaken), total: num(summary.places) }),
        sub: methodnote,
        meter: percent(summary.placestaken, summary.places),
        warn: false
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
        lineNumber: 189,
        columnNumber: 17
      }
    ) : null,
    summary.applicantlimit > 0 ? /* @__PURE__ */ jsxDEV(
      Tile,
      {
        label: labels.held,
        value: fillObject(labels.ofmany, { taken: num(summary.applicants), total: num(summary.applicantlimit) }),
        sub: methodnote,
        meter: percent(summary.applicants, summary.applicantlimit),
        warn: summary.applicants * 5 >= summary.applicantlimit * 4
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
        lineNumber: 198,
        columnNumber: 17
      }
    ) : null,
    /* @__PURE__ */ jsxDEV(AwaitingTile, { labels, summary }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
      lineNumber: 207,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      Tile,
      {
        label: labels.status,
        value: summary.open ? labels.open : labels.closed,
        sub: summary.open && summary.closes > 0 ? fill(labels.closes, shortDate(summary.closes)) : "",
        meter: null,
        warn: false
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
        lineNumber: 208,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/Tiles.tsx",
    lineNumber: 187,
    columnNumber: 9
  });
}, "Tiles");
export {
  Tiles,
  percent,
  shortDate
};
//# sourceMappingURL=Tiles.dev.js.map
