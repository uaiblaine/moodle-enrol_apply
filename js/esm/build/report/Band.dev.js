var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The summary band: how long applications waited, over the rows the reader's table lists.
 *
 * Tiles (median, 90th percentile, still open, decided without a person), the wait's distribution and
 * the median by method or by group (AR-D5, AR-D7: no breakdown by decider). A reader the separate
 * groups rule narrows gets the compact band, which the summary itself says (compact): the median, what
 * is still open and the decisions without a person (AR-D17). The measure switch (AR-D4) reloads this
 * region alone. "Still open" leads to the queue for a reader the queue admits (AR-D18), which the
 * props say by carrying its url.
 *
 * Every number is the server's (reportengine::summary()), over the same SQL as the rows.
 *
 * @module     enrol_apply/report/Band
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useState } from "react";
import { Segmented } from "./Segmented";
import { duration, fill, fillObject, num } from "./str";
const TOP_GROUPS = 8;
const LATE_DAYS = 7;
const Tile = /* @__PURE__ */ __name(({ label, value, sub, warn, link }) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-tile" + (warn ? " enrol_apply-rview-tile--warn" : ""), children: [
  /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-tile-label", children: label }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
    lineNumber: 68,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-tile-value", children: value }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
    lineNumber: 69,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-tile-sub", children: sub }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
    lineNumber: 70,
    columnNumber: 9
  }),
  link ?? null
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
  lineNumber: 67,
  columnNumber: 5
}), "Tile");
const BarRow = /* @__PURE__ */ __name(({ label, share, figure, warn }) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-hrow", role: "listitem", children: [
  /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-hrow-label", title: label, children: label }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
    lineNumber: 83,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-track", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV(
    "span",
    {
      className: "enrol_apply-rview-fill" + (warn ? " enrol_apply-rview-fill--late" : ""),
      style: { width: `${Math.round(Math.min(1, Math.max(0, share)) * 100)}%` }
    },
    void 0,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 85,
      columnNumber: 13
    }
  ) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
    lineNumber: 84,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-hrow-n", children: figure }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
    lineNumber: 90,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
  lineNumber: 82,
  columnNumber: 5
}), "BarRow");
const BandSkeleton = /* @__PURE__ */ __name(({ compact }) => /* @__PURE__ */ jsxDEV("div", { "aria-hidden": "true", children: [
  /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-band", children: Array.from({ length: compact ? 3 : 4 }, (_, i) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-tile enrol_apply-rview-sk", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb enrol_apply-rview-skb--w60" }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 105,
      columnNumber: 21
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb enrol_apply-rview-skb--big" }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 106,
      columnNumber: 21
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb enrol_apply-rview-skb--w80" }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 107,
      columnNumber: 21
    })
  ] }, i, true, {
    fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
    lineNumber: 104,
    columnNumber: 17
  })) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
    lineNumber: 102,
    columnNumber: 9
  }),
  compact ? null : /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-dist", children: [0, 1].map((i) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-panel enrol_apply-rview-sk", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb enrol_apply-rview-skb--w40" }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 115,
      columnNumber: 25
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb" }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 116,
      columnNumber: 25
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb" }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 117,
      columnNumber: 25
    }),
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-skb" }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 118,
      columnNumber: 25
    })
  ] }, i, true, {
    fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
    lineNumber: 114,
    columnNumber: 21
  })) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
    lineNumber: 112,
    columnNumber: 13
  })
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
  lineNumber: 101,
  columnNumber: 5
}), "BandSkeleton");
const BreakdownPanel = /* @__PURE__ */ __name(({ labels, bymethod, bygroup, methods }) => {
  const [by, setBy] = useState(methods > 1 ? "method" : "group");
  const [all, setAll] = useState(false);
  const [query, setQuery] = useState("");
  const list = by === "method" ? bymethod : bygroup;
  const most = Math.max(86400, ...list.map((entry) => entry.median ?? 0));
  const row = /* @__PURE__ */ __name((entry) => /* @__PURE__ */ jsxDEV(
    BarRow,
    {
      label: entry.name,
      share: (entry.median ?? 0) / most,
      figure: `${entry.median === null ? "\u2013" : duration(labels, entry.median)} (${num(entry.count)})`,
      warn: (entry.median ?? 0) > LATE_DAYS * 86400
    },
    entry.id,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 147,
      columnNumber: 9
    }
  ), "row");
  const bycount = [...list].sort((a, b) => b.count - a.count);
  const long = by === "group" && list.length > TOP_GROUPS;
  const folded = query.trim().toLocaleLowerCase();
  const slowest = [...list].filter((entry) => folded === "" || entry.name.toLocaleLowerCase().includes(folded)).sort((a, b) => (b.median ?? 0) - (a.median ?? 0));
  return /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-panel", children: [
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-panel-title", children: [
      /* @__PURE__ */ jsxDEV("span", { children: labels.medianby }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
        lineNumber: 166,
        columnNumber: 17
      }),
      methods > 1 ? /* @__PURE__ */ jsxDEV(
        Segmented,
        {
          label: labels.breakdown,
          small: true,
          value: by,
          options: [{ value: "method", label: labels.bymethod }, { value: "group", label: labels.bygroup }],
          onChange: (value) => setBy(value)
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
          lineNumber: 168,
          columnNumber: 21
        }
      ) : /* @__PURE__ */ jsxDEV("span", { children: labels.bygroup }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
        lineNumber: 175,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 165,
      columnNumber: 13
    }),
    long ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-note", children: fill(labels.topgroups, num(TOP_GROUPS)) }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
        lineNumber: 179,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("div", { role: "list", children: bycount.slice(0, TOP_GROUPS).map(row) }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
        lineNumber: 180,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "btn btn-link btn-sm enrol_apply-rview-linkbtn",
          "aria-expanded": all,
          onClick: () => setAll(!all),
          children: all ? labels.hide : fill(labels.allgroups, num(list.length))
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
          lineNumber: 181,
          columnNumber: 21
        }
      ),
      all ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
        /* @__PURE__ */ jsxDEV(
          "input",
          {
            type: "search",
            className: "form-control form-control-sm enrol_apply-rview-input",
            placeholder: labels.searchgroup,
            "aria-label": labels.searchgroup,
            value: query,
            onChange: (event) => setQuery(event.target.value)
          },
          void 0,
          false,
          {
            fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
            lineNumber: 191,
            columnNumber: 29
          }
        ),
        /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-scrolllist", role: "list", children: slowest.length ? slowest.map(row) : /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-note", children: labels.nogroupmatch }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
          lineNumber: 202,
          columnNumber: 39
        }) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
          lineNumber: 199,
          columnNumber: 29
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
        lineNumber: 190,
        columnNumber: 25
      }) : null
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 178,
      columnNumber: 17
    }) : /* @__PURE__ */ jsxDEV("div", { role: "list", children: bycount.map(row) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 207,
      columnNumber: 17
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
    lineNumber: 164,
    columnNumber: 9
  });
}, "BreakdownPanel");
const Band = /* @__PURE__ */ __name(({
  labels,
  summary,
  loading,
  error,
  compactHint,
  measure,
  methods,
  queueurl,
  pendingstatus,
  onMeasure,
  onRetry
}) => {
  const head = /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-bandhead", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-bandtitle", children: labels.turnaround }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 222,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      Segmented,
      {
        label: labels.measure,
        small: true,
        value: measure,
        options: [{ value: "first", label: labels.measurefirst }, { value: "resolution", label: labels.measureresolution }],
        onChange: (value) => onMeasure(value)
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
        lineNumber: 223,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
    lineNumber: 221,
    columnNumber: 9
  });
  let body;
  if (summary === null && error) {
    body = /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-error", children: [
      labels.loaderror,
      " ",
      /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-sm btn-outline-secondary", onClick: onRetry, children: labels.retry }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
        lineNumber: 238,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 236,
      columnNumber: 13
    });
  } else if (summary === null) {
    body = /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV(BandSkeleton, { compact: compactHint }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
        lineNumber: 244,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", role: "status", children: labels.loadband }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
        lineNumber: 245,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 243,
      columnNumber: 13
    });
  } else {
    const resolution = summary.measure === "resolution";
    const median = summary.median ?? null;
    const p90 = summary.p90 ?? null;
    const oldest = summary.oldest ?? null;
    let opensub = labels.nothingopen;
    if (oldest !== null) {
      opensub = fill(labels.oldest, duration(labels, oldest)) + (summary.late > 0 ? " \xB7 " + fill(labels.late, num(summary.late)) : "");
    }
    const queue = queueurl === "" ? null : /* @__PURE__ */ jsxDEV(
      "a",
      {
        className: "enrol_apply-rview-tile-link",
        href: resolution ? queueurl : `${queueurl}&status=${pendingstatus}`,
        children: labels.openqueue
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
        lineNumber: 259,
        columnNumber: 13
      }
    );
    const most = Math.max(1, ...summary.buckets.map((bucket) => bucket.count));
    body = /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-dim", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-band", children: [
        /* @__PURE__ */ jsxDEV(
          Tile,
          {
            label: resolution ? labels.medianresolution : labels.medianfirst,
            value: median === null ? "\u2013" : duration(labels, median),
            sub: fill(labels.mediansub, num(summary.decided))
          },
          void 0,
          false,
          {
            fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
            lineNumber: 270,
            columnNumber: 21
          }
        ),
        summary.compact ? null : /* @__PURE__ */ jsxDEV(Tile, { label: labels.p90, value: p90 === null ? "\u2013" : duration(labels, p90), sub: labels.p90sub }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
          lineNumber: 276,
          columnNumber: 25
        }),
        /* @__PURE__ */ jsxDEV(Tile, { label: labels.open, value: num(summary.open), sub: opensub, warn: summary.late > 0, link: queue }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
          lineNumber: 278,
          columnNumber: 21
        }),
        /* @__PURE__ */ jsxDEV(Tile, { label: labels.withoutperson, value: num(summary.withoutperson), sub: labels.withoutpersonsub }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
          lineNumber: 279,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
        lineNumber: 269,
        columnNumber: 17
      }),
      summary.compact ? null : /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-dist", children: [
        /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-panel", children: [
          /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-panel-title", children: [
            /* @__PURE__ */ jsxDEV("span", { children: resolution ? labels.distresolution : labels.distfirst }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
              lineNumber: 285,
              columnNumber: 33
            }),
            /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-note", children: fill(labels.decisions, num(summary.decided)) }, void 0, false, {
              fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
              lineNumber: 286,
              columnNumber: 33
            })
          ] }, void 0, true, {
            fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
            lineNumber: 284,
            columnNumber: 29
          }),
          /* @__PURE__ */ jsxDEV("div", { role: "list", children: summary.buckets.map((bucket, index) => {
            const to = num(bucket.todays ?? 0);
            let label = fillObject(labels.bucket, { from: num(bucket.fromdays), to });
            if (index === 0) {
              label = fill(labels.bucketfirst, num(bucket.todays ?? 0));
            } else if ((bucket.todays ?? null) === null) {
              label = fill(labels.bucketlast, num(bucket.fromdays));
            }
            return /* @__PURE__ */ jsxDEV(
              BarRow,
              {
                label,
                share: bucket.count / most,
                figure: num(bucket.count),
                warn: bucket.fromdays >= LATE_DAYS
              },
              bucket.fromdays,
              false,
              {
                fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
                lineNumber: 298,
                columnNumber: 41
              }
            );
          }) }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
            lineNumber: 288,
            columnNumber: 29
          })
        ] }, void 0, true, {
          fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
          lineNumber: 283,
          columnNumber: 25
        }),
        /* @__PURE__ */ jsxDEV(BreakdownPanel, { labels, bymethod: summary.bymethod, bygroup: summary.bygroup, methods }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
          lineNumber: 309,
          columnNumber: 25
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
        lineNumber: 282,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 268,
      columnNumber: 13
    });
  }
  return /* @__PURE__ */ jsxDEV(
    "section",
    {
      className: "enrol_apply-rview-region enrol_apply-rview-bandregion",
      "aria-label": labels.turnaround,
      "aria-busy": loading,
      "data-region": "enrol_apply-band",
      children: [
        loading && summary !== null ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-rview-progress" }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
          lineNumber: 323,
          columnNumber: 44
        }) : null,
        head,
        body
      ]
    },
    void 0,
    true,
    {
      fileName: "public/enrol/apply/js/esm/src/report/Band.tsx",
      lineNumber: 317,
      columnNumber: 9
    }
  );
}, "Band");
export {
  Band
};
//# sourceMappingURL=Band.dev.js.map
