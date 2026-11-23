var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The queue's cells as the grid and the cards draw them, and the columns' widths.
 *
 * Two kinds of value arrive, and they are drawn differently on purpose. The cells (`cells[].html`) and
 * the picture are the server's formatted HTML, the masking and the escaping already applied there
 * (queueengine::row()), so they are inserted as HTML. Everything the service documents as plain - the
 * applicant's name, the identity values, the tags' words and titles, the course's and the method's names
 * on the queues that span courses - is drawn as text, never as HTML.
 *
 * @module     enrol_apply/queue/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Lines } from "../datareport/cells";
import { makeSizing } from "../datareport/sizing";
import { fill } from "../datareport/str";
const WIDTHS = {
  applicant: 320,
  course: 240,
  wait: 150,
  group: 180,
  comment: 260,
  details: 260,
  history: 130
};
const DEFAULT_WIDTH = 180;
const MINIMUMS = { applicant: 200 };
const MINIMUM = 80;
const MAXIMUM = 800;
const SIZING = makeSizing(WIDTHS, DEFAULT_WIDTH, MINIMUMS, MINIMUM, MAXIMUM);
const TONES = {
  deferred: "enrol_apply-status--warning",
  key: "enrol_apply-status--info",
  appliedbefore: "enrol_apply-status--neutral",
  own: "enrol_apply-status--neutral",
  // The method's labels on the queues that span courses: the table's tone for a deciding role.
  deciding: "enrol_apply-status--warning",
  full: "enrol_apply-status--neutral"
};
const cellHtml = /* @__PURE__ */ __name((row, key) => row.cells.find((cell) => cell.key === key)?.html ?? "", "cellHtml");
const CellLine = /* @__PURE__ */ __name(({ html }) => /* @__PURE__ */ jsxDEV(Lines, { parts: [html] }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
  lineNumber: 90,
  columnNumber: 67
}), "CellLine");
const StatusLabel = /* @__PURE__ */ __name(({ tag }) => {
  const tone = TONES[tag.kind] ?? "enrol_apply-status--neutral";
  const long = tag.title !== "" && tag.title !== tag.text;
  return /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-status " + tone, title: long ? tag.title : void 0, children: [
    tag.text,
    long ? /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: " " + tag.title }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
      lineNumber: 105,
      columnNumber: 21
    }) : null
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
    lineNumber: 103,
    columnNumber: 9
  });
}, "StatusLabel");
const Name = /* @__PURE__ */ __name(({ row }) => row.applicant.profileurl === "" ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-qview-name", children: row.applicant.fullname }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
  lineNumber: 118,
  columnNumber: 11
}) : /* @__PURE__ */ jsxDEV("a", { className: "enrol_apply-qview-name", href: row.applicant.profileurl, children: row.applicant.fullname }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
  lineNumber: 119,
  columnNumber: 11
}), "Name");
const ReviewLink = /* @__PURE__ */ __name(({ labels, row }) => /* @__PURE__ */ jsxDEV(
  "a",
  {
    className: "btn btn-sm btn-outline-secondary enrol_apply-rview-review",
    href: row.reviewurl,
    "aria-label": fill(labels.reviewapplicant, row.applicant.fullname),
    children: labels.review
  },
  void 0,
  false,
  {
    fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
    lineNumber: 129,
    columnNumber: 5
  }
), "ReviewLink");
const Who = /* @__PURE__ */ __name(({ row }) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
  /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-qview-picture", "aria-hidden": "true", dangerouslySetInnerHTML: { __html: row.applicant.picture } }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
    lineNumber: 147,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-qview-whotext", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-c", children: /* @__PURE__ */ jsxDEV(Name, { row }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
      lineNumber: 149,
      columnNumber: 51
    }) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
      lineNumber: 149,
      columnNumber: 13
    }),
    row.applicant.identity.map((field) => /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-sub", title: field.value, children: field.value }, field.name, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
      lineNumber: 151,
      columnNumber: 17
    })),
    row.applicant.tags.length > 0 ? /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-qview-tags", children: row.applicant.tags.map((tag) => /* @__PURE__ */ jsxDEV(StatusLabel, { tag }, tag.kind, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
      lineNumber: 155,
      columnNumber: 54
    })) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
      lineNumber: 154,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
    lineNumber: 148,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
  lineNumber: 145,
  columnNumber: 5
}), "Who");
const ApplicantCell = /* @__PURE__ */ __name(({ labels, row }) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-qview-who", children: [
  /* @__PURE__ */ jsxDEV(Who, { row }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
    lineNumber: 170,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV(ReviewLink, { labels, row }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
    lineNumber: 171,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
  lineNumber: 169,
  columnNumber: 5
}), "ApplicantCell");
const MethodTags = /* @__PURE__ */ __name(({ labels, row }) => {
  const tags = [];
  if (row.method.deciding) {
    tags.push({ kind: "deciding", text: labels.grantsdeciding, title: "" });
  }
  if (row.method.full) {
    tags.push({ kind: "full", text: labels.methodfull, title: labels.methodfullwarn });
  }
  if (tags.length === 0) {
    return null;
  }
  return /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-qview-tags", children: tags.map((tag) => /* @__PURE__ */ jsxDEV(StatusLabel, { tag }, tag.kind, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
    lineNumber: 194,
    columnNumber: 72
  })) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
    lineNumber: 194,
    columnNumber: 12
  });
}, "MethodTags");
const MethodName = /* @__PURE__ */ __name(({ row }) => row.method.queueurl === "" ? /* @__PURE__ */ jsxDEV("span", { children: row.method.name }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
  lineNumber: 205,
  columnNumber: 11
}) : /* @__PURE__ */ jsxDEV("a", { href: row.method.queueurl, children: row.method.name }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
  lineNumber: 206,
  columnNumber: 11
}), "MethodName");
const CourseMethod = /* @__PURE__ */ __name(({ labels, row }) => /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-qview-course", children: [
  /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-c", title: row.course.name, children: /* @__PURE__ */ jsxDEV("a", { className: "enrol_apply-qview-coursename", href: row.course.url, children: row.course.name }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
    lineNumber: 219,
    columnNumber: 13
  }) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
    lineNumber: 218,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-qview-methodline", children: [
    /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-rview-sub", title: row.method.name, children: /* @__PURE__ */ jsxDEV(MethodName, { row }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
      lineNumber: 222,
      columnNumber: 77
    }) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
      lineNumber: 222,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(MethodTags, { labels, row }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
      lineNumber: 223,
      columnNumber: 13
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
    lineNumber: 221,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/queue/cells.tsx",
  lineNumber: 217,
  columnNumber: 5
}), "CourseMethod");
export {
  ApplicantCell,
  CellLine,
  CourseMethod,
  MethodTags,
  ReviewLink,
  SIZING,
  Who,
  cellHtml
};
//# sourceMappingURL=cells.dev.js.map
