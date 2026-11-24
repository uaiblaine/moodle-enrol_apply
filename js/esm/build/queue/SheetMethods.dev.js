var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The decision sheet's lists of who is decided: the whole selection's, and, when a selection on the
 * site-wide queue spans enrolment methods, one section per method (SQ-D10, the table's sheet in
 * amd/src/manage.js, methodSection()).
 *
 * A section names the course and the method (text), the method's labels (it grants a deciding role, it is
 * at its limit, with the warning for an approval there), how many of the selection are its and who, and a
 * message box for them, posted as outcomemessages[<the method's id>]; the id is the one the rows service
 * gave the row, and the server groups the posted rows by the method the database names whatever the key
 * says. Each section links to the method's own queue where the reader may open it, the one place groups
 * and a role are chosen; a mentor may open none (the rows service sends them no address), so their
 * sections carry neither the link nor the sentence about choosing there.
 *
 * @module     enrol_apply/queue/SheetMethods
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fill, num } from "../datareport/str";
import { MethodTags } from "./cells";
const NAMED = 5;
const byMethod = /* @__PURE__ */ __name((rows) => {
  const groups = [];
  rows.forEach((row) => {
    const found = groups.find((group) => group.id === row.method.id);
    if (found) {
      found.rows.push(row);
    } else {
      groups.push({ id: row.method.id, rows: [row] });
    }
  });
  return groups;
}, "byMethod");
const WhoList = /* @__PURE__ */ __name(({ labels, rows }) => {
  const more = rows.length - NAMED;
  return /* @__PURE__ */ jsxDEV("ul", { className: "enrol_apply-qview-sheetwho", children: [
    rows.slice(0, NAMED).map((row) => /* @__PURE__ */ jsxDEV("li", { children: [
      /* @__PURE__ */ jsxDEV(
        "span",
        {
          className: "enrol_apply-qview-picture",
          "aria-hidden": "true",
          dangerouslySetInnerHTML: { __html: row.applicant.picture }
        },
        void 0,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
          lineNumber: 79,
          columnNumber: 21
        }
      ),
      /* @__PURE__ */ jsxDEV("span", { children: row.applicant.fullname }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
        lineNumber: 84,
        columnNumber: 21
      })
    ] }, row.id, true, {
      fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
      lineNumber: 77,
      columnNumber: 17
    })),
    more > 0 ? /* @__PURE__ */ jsxDEV("li", { className: "enrol_apply-qview-sheetmore", children: fill(labels.andmore, num(more)) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
      lineNumber: 87,
      columnNumber: 25
    }) : null
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
    lineNumber: 75,
    columnNumber: 9
  });
}, "WhoList");
const MethodSection = /* @__PURE__ */ __name(({ labels, id, group, approval, message, onMessage }) => {
  const first = group.rows[0];
  const count = group.rows.length;
  return /* @__PURE__ */ jsxDEV("section", { className: "enrol_apply-qview-sheetmethod", "aria-labelledby": `${id}-head`, children: [
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-qview-sheetmethodhead", id: `${id}-head`, children: [
      /* @__PURE__ */ jsxDEV("strong", { children: first.course.name }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
        lineNumber: 114,
        columnNumber: 17
      }),
      /* @__PURE__ */ jsxDEV("span", { children: " \xB7 " + first.method.name }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
        lineNumber: 115,
        columnNumber: 17
      }),
      " ",
      /* @__PURE__ */ jsxDEV(MethodTags, { labels, row: first }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
        lineNumber: 116,
        columnNumber: 17
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
      lineNumber: 113,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-qview-sheetline", children: count === 1 ? labels.count_one : fill(labels.count, num(count)) }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
      lineNumber: 118,
      columnNumber: 13
    }),
    approval && first.method.full ? /* @__PURE__ */ jsxDEV("div", { className: "alert alert-warning", children: labels.methodfullwarn }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
      lineNumber: 121,
      columnNumber: 46
    }) : null,
    /* @__PURE__ */ jsxDEV(WhoList, { labels, rows: group.rows }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
      lineNumber: 122,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV("label", { className: "form-label", htmlFor: `${id}-message`, children: labels.sheetmessage }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
      lineNumber: 123,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "textarea",
      {
        className: "form-control",
        id: `${id}-message`,
        rows: 2,
        value: message,
        "aria-describedby": `${id}-head`,
        onChange: (event) => onMessage(group.id, event.target.value)
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
        lineNumber: 124,
        columnNumber: 13
      }
    ),
    first.method.queueurl !== "" ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-qview-sheetopen", children: [
      /* @__PURE__ */ jsxDEV("a", { href: first.method.queueurl, children: labels.openmethod }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
        lineNumber: 134,
        columnNumber: 21
      }),
      approval ? /* @__PURE__ */ jsxDEV(Fragment, { children: [
        " ",
        /* @__PURE__ */ jsxDEV("span", { className: "enrol_apply-qview-sheetline", children: labels.openmethodline }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
          lineNumber: 135,
          columnNumber: 40
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
        lineNumber: 135,
        columnNumber: 33
      }) : null
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
      lineNumber: 133,
      columnNumber: 17
    }) : null
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
    lineNumber: 112,
    columnNumber: 9
  });
}, "MethodSection");
const SheetMethods = /* @__PURE__ */ __name(({ labels, id, groups, approval, messages, onMessage }) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
  /* @__PURE__ */ jsxDEV("div", { className: "alert alert-info", children: fill(labels.spans, num(groups.length)) }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
    lineNumber: 159,
    columnNumber: 9
  }),
  groups.map((group, index) => /* @__PURE__ */ jsxDEV(
    MethodSection,
    {
      labels,
      id: `${id}-method${index}`,
      group,
      approval,
      message: messages[group.id] ?? "",
      onMessage
    },
    group.id,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
      lineNumber: 161,
      columnNumber: 13
    }
  ))
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/queue/SheetMethods.tsx",
  lineNumber: 158,
  columnNumber: 5
}), "SheetMethods");
export {
  SheetMethods,
  WhoList,
  byMethod
};
//# sourceMappingURL=SheetMethods.dev.js.map
