var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The decision sheet: what the table's sheet (amd/src/manage.js, MQ-D5, MQ-D6) says and asks, as a
 * modal dialog of the view's own.
 *
 * Its title names the decision and the count, its list the first five applicants and how many more;
 * a cancellation says what cancelling removes, a deferral where the application stays, an approval
 * that every place is taken when the table page says so. A selection on the site-wide queue that spans
 * enrolment methods is listed method by method instead, each with its own message box
 * (queue/SheetMethods, SQ-D10), and no group or role chooser is offered there. Then the fields of the table's decision
 * controls, with their labels and help: for an approval the groups and the role where the props offer
 * them (a holder of enrol/apply:choosedecision) or the summary of what an approval gives, then the
 * message to the applicant and the note for the record, which every decision posts. The sheet's commit
 * button is the confirmation, worded as the table's ("Cancel 2"); Back, the close button and Escape close
 * it without posting anything, and so does a press outside while no field holds typed text or a
 * non-default choice (core's modal does not hide a dialogue that holds a form either).
 *
 * Bootstrap's modal markup, drawn by React rather than by Bootstrap's script, so Moodle's dialogue
 * locator finds it by its title. The focus moves to the title when the dialog opens and stays inside it
 * (Tab and Shift+Tab wrap); the view gives it back to the dock button that opened it.
 *
 * @module     enrol_apply/queue/Sheet
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useId, useRef, useState } from "react";
import { fill, num } from "../datareport/str";
import { SheetMethods, WhoList, byMethod } from "./SheetMethods";
const FOCUSABLE = "button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), a[href]";
const wording = /* @__PURE__ */ __name((labels, decision, count) => {
  const shown = num(count);
  if (decision === "cancel") {
    return [fill(count === 1 ? labels.sheetcancelone : labels.sheetcancel, shown), fill(labels.sheetcancelcommit, shown)];
  }
  if (decision === "wait") {
    return [fill(count === 1 ? labels.sheetwaitone : labels.sheetwait, shown), fill(labels.sheetwaitcommit, shown)];
  }
  return [fill(count === 1 ? labels.sheetconfirmone : labels.sheetconfirm, shown), fill(labels.sheetconfirmcommit, shown)];
}, "wording");
const Help = /* @__PURE__ */ __name(({ id, text }) => /* @__PURE__ */ jsxDEV("div", { className: "form-text", id, children: text }, void 0, false, {
  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
  lineNumber: 101,
  columnNumber: 72
}), "Help");
const TextField = /* @__PURE__ */ __name(({ id, label, help, value, last, onChange }) => /* @__PURE__ */ jsxDEV("div", { className: last ? "mb-0" : "mb-3", children: [
  /* @__PURE__ */ jsxDEV("label", { className: "form-label", htmlFor: id, children: label }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
    lineNumber: 120,
    columnNumber: 9
  }),
  /* @__PURE__ */ jsxDEV(
    "textarea",
    {
      className: "form-control",
      id,
      rows: 3,
      value,
      "aria-describedby": `${id}help`,
      onChange: (event) => onChange(event.target.value)
    },
    void 0,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
      lineNumber: 121,
      columnNumber: 9
    }
  ),
  /* @__PURE__ */ jsxDEV(Help, { id: `${id}help`, text: help }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
    lineNumber: 129,
    columnNumber: 9
  })
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
  lineNumber: 119,
  columnNumber: 5
}), "TextField");
const Notices = /* @__PURE__ */ __name(({ labels, decision, offer }) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
  decision === "cancel" ? /* @__PURE__ */ jsxDEV("div", { className: "alert alert-danger", children: labels.sheetcanceldanger }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
    lineNumber: 143,
    columnNumber: 34
  }) : null,
  decision === "wait" ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-qview-sheetline", children: labels.sheetwaitline }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
    lineNumber: 144,
    columnNumber: 32
  }) : null,
  decision === "confirm" && offer.placesfull !== "" ? /* @__PURE__ */ jsxDEV("div", { className: "alert alert-warning", children: offer.placesfull }, void 0, false, {
    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
    lineNumber: 146,
    columnNumber: 13
  }) : null
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
  lineNumber: 142,
  columnNumber: 5
}), "Notices");
const Choosers = /* @__PURE__ */ __name(({ id, offer, groups, setGroups, roleid, setRoleid }) => /* @__PURE__ */ jsxDEV(Fragment, { children: [
  offer.summary ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-qview-sheetsummary", children: [
    /* @__PURE__ */ jsxDEV("dl", { className: "row mb-0", children: [
      /* @__PURE__ */ jsxDEV("dt", { className: "col-sm-4", children: offer.summary.groupslabel }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
        lineNumber: 172,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("dd", { className: "col-sm-8", children: offer.summary.groups }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
        lineNumber: 173,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("dt", { className: "col-sm-4", children: offer.summary.rolelabel }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
        lineNumber: 174,
        columnNumber: 21
      }),
      /* @__PURE__ */ jsxDEV("dd", { className: "col-sm-8", children: offer.summary.role }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
        lineNumber: 175,
        columnNumber: 21
      })
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
      lineNumber: 171,
      columnNumber: 17
    }),
    /* @__PURE__ */ jsxDEV("div", { className: "form-text", children: [
      offer.summary.help,
      offer.summary.note !== "" ? " " + offer.summary.note : ""
    ] }, void 0, true, {
      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
      lineNumber: 177,
      columnNumber: 17
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
    lineNumber: 170,
    columnNumber: 13
  }) : null,
  offer.groups ? /* @__PURE__ */ jsxDEV("div", { className: "mb-3", children: [
    /* @__PURE__ */ jsxDEV("label", { className: "form-label", htmlFor: `${id}-groups`, children: offer.groups.label }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
      lineNumber: 185,
      columnNumber: 17
    }),
    /* @__PURE__ */ jsxDEV(
      "select",
      {
        className: "form-select",
        id: `${id}-groups`,
        multiple: true,
        value: groups,
        "aria-describedby": `${id}-groupshelp`,
        onChange: (event) => setGroups(Array.from(event.target.selectedOptions, (option) => option.value)),
        children: offer.groups.options.map((group) => /* @__PURE__ */ jsxDEV("option", { value: String(group.id), children: group.name }, group.id, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
          lineNumber: 195,
          columnNumber: 25
        }))
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
        lineNumber: 186,
        columnNumber: 17
      }
    ),
    /* @__PURE__ */ jsxDEV(Help, { id: `${id}-groupshelp`, text: offer.groups.help }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
      lineNumber: 198,
      columnNumber: 17
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
    lineNumber: 184,
    columnNumber: 13
  }) : null,
  offer.roles ? /* @__PURE__ */ jsxDEV("div", { className: "mb-3", children: [
    /* @__PURE__ */ jsxDEV("label", { className: "form-label", htmlFor: `${id}-role`, children: offer.roles.label }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
      lineNumber: 203,
      columnNumber: 17
    }),
    /* @__PURE__ */ jsxDEV(
      "select",
      {
        className: "form-select",
        id: `${id}-role`,
        value: roleid,
        "aria-describedby": `${id}-rolehelp`,
        onChange: (event) => setRoleid(event.target.value),
        children: [
          /* @__PURE__ */ jsxDEV("option", { value: "0", children: offer.roles.default }, void 0, false, {
            fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
            lineNumber: 211,
            columnNumber: 21
          }),
          offer.roles.options.map((role) => /* @__PURE__ */ jsxDEV("option", { value: String(role.id), children: role.name }, role.id, false, {
            fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
            lineNumber: 213,
            columnNumber: 25
          }))
        ]
      },
      void 0,
      true,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
        lineNumber: 204,
        columnNumber: 17
      }
    ),
    /* @__PURE__ */ jsxDEV(Help, { id: `${id}-rolehelp`, text: offer.roles.help }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
      lineNumber: 216,
      columnNumber: 17
    })
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
    lineNumber: 202,
    columnNumber: 13
  }) : null
] }, void 0, true, {
  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
  lineNumber: 168,
  columnNumber: 5
}), "Choosers");
const holdsInput = /* @__PURE__ */ __name((texts, roleid, groups, initial) => texts.some((text) => text !== "") || roleid !== "0" || groups.length !== initial.length || groups.some((group) => !initial.includes(group)), "holdsInput");
const wrapFocus = /* @__PURE__ */ __name((event, dialog, heading) => {
  if (!dialog) {
    return;
  }
  const items = Array.from(dialog.querySelectorAll(FOCUSABLE));
  if (items.length === 0) {
    event.preventDefault();
    return;
  }
  const first = items[0];
  const last = items[items.length - 1];
  const at = document.activeElement;
  if (event.shiftKey && (at === first || at === heading)) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && (at === last || at === heading)) {
    event.preventDefault();
    first.focus();
  }
}, "wrapFocus");
const Sheet = /* @__PURE__ */ __name(({ labels, decision, rows, offer, onClose, onCommit }) => {
  const id = useId();
  const dialog = useRef(null);
  const heading = useRef(null);
  const [message, setMessage] = useState("");
  const [messages, setMessages] = useState({});
  const [note, setNote] = useState("");
  const [initialGroups] = useState(
    () => (offer.groups?.options ?? []).filter((group) => group.selected).map((group) => String(group.id))
  );
  const [groups, setGroups] = useState(initialGroups);
  const [roleid, setRoleid] = useState("0");
  const [sending, setSending] = useState(false);
  const [title, commit] = wording(labels, decision, rows.length);
  const approval = decision === "confirm";
  const methods = byMethod(rows);
  const spans = methods.length > 1;
  useEffect(() => {
    heading.current?.focus();
    document.body.classList.add("modal-open");
    return () => document.body.classList.remove("modal-open");
  }, []);
  const keydown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      event.stopPropagation();
      if (!sending) {
        onClose();
      }
      return;
    }
    if (event.key === "Tab") {
      wrapFocus(event, dialog.current, heading.current);
    }
  }, "keydown");
  const submit = /* @__PURE__ */ __name(() => {
    if (sending) {
      return;
    }
    setSending(true);
    const choosers = approval && !spans;
    onCommit({
      message: spans ? "" : message,
      note,
      messages: spans ? Object.fromEntries(methods.map((method) => [method.id, messages[method.id] ?? ""])) : null,
      groups: choosers && offer.groups ? groups.map(Number) : null,
      roleid: choosers && offer.roles ? Number(roleid) : null
    });
  }, "submit");
  const dirty = holdsInput([message, note, ...Object.values(messages)], roleid, groups, initialGroups);
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("div", { className: "modal-backdrop show" }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
      lineNumber: 344,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        className: "modal show d-block enrol_apply-qview-sheet",
        role: "presentation",
        onMouseDown: (event) => {
          if (event.target === event.currentTarget && !sending && !dirty) {
            onClose();
          }
        },
        children: /* @__PURE__ */ jsxDEV("div", { className: "modal-dialog modal-lg modal-dialog-scrollable", children: /* @__PURE__ */ jsxDEV(
          "div",
          {
            className: "modal-content",
            role: "dialog",
            "aria-modal": "true",
            "aria-labelledby": `${id}-title`,
            tabIndex: -1,
            ref: dialog,
            onKeyDown: keydown,
            "data-region": "enrol_apply-sheet",
            children: [
              /* @__PURE__ */ jsxDEV("div", { className: "modal-header", children: [
                /* @__PURE__ */ jsxDEV("h5", { className: "modal-title", id: `${id}-title`, tabIndex: -1, ref: heading, children: title }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 367,
                  columnNumber: 29
                }),
                /* @__PURE__ */ jsxDEV(
                  "button",
                  {
                    type: "button",
                    className: "btn-close",
                    "aria-label": labels.close,
                    onClick: onClose,
                    disabled: sending
                  },
                  void 0,
                  false,
                  {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 368,
                    columnNumber: 29
                  }
                )
              ] }, void 0, true, {
                fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                lineNumber: 366,
                columnNumber: 25
              }),
              /* @__PURE__ */ jsxDEV("div", { className: "modal-body", children: [
                /* @__PURE__ */ jsxDEV(Notices, { labels, decision, offer }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 377,
                  columnNumber: 29
                }),
                spans ? /* @__PURE__ */ jsxDEV(
                  SheetMethods,
                  {
                    labels,
                    id,
                    groups: methods,
                    approval,
                    messages,
                    onMessage: (method, text) => setMessages((before) => ({ ...before, [method]: text }))
                  },
                  void 0,
                  false,
                  {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 379,
                    columnNumber: 33
                  }
                ) : /* @__PURE__ */ jsxDEV(Fragment, { children: [
                  /* @__PURE__ */ jsxDEV(WhoList, { labels, rows }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 389,
                    columnNumber: 37
                  }),
                  approval ? /* @__PURE__ */ jsxDEV(
                    Choosers,
                    {
                      id,
                      offer,
                      groups,
                      setGroups,
                      roleid,
                      setRoleid
                    },
                    void 0,
                    false,
                    {
                      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                      lineNumber: 391,
                      columnNumber: 41
                    }
                  ) : null,
                  /* @__PURE__ */ jsxDEV(
                    TextField,
                    {
                      id: `${id}-message`,
                      label: offer.message.label,
                      help: offer.message.help,
                      value: message,
                      last: false,
                      onChange: setMessage
                    },
                    void 0,
                    false,
                    {
                      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                      lineNumber: 400,
                      columnNumber: 37
                    }
                  )
                ] }, void 0, true, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 388,
                  columnNumber: 33
                }),
                /* @__PURE__ */ jsxDEV(
                  TextField,
                  {
                    id: `${id}-note`,
                    label: offer.note.label,
                    help: offer.note.help,
                    value: note,
                    last: true,
                    onChange: setNote
                  },
                  void 0,
                  false,
                  {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 410,
                    columnNumber: 29
                  }
                )
              ] }, void 0, true, {
                fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                lineNumber: 376,
                columnNumber: 25
              }),
              /* @__PURE__ */ jsxDEV("div", { className: "modal-footer", children: [
                /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-secondary", onClick: onClose, disabled: sending, children: labels.back }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 420,
                  columnNumber: 29
                }),
                /* @__PURE__ */ jsxDEV(
                  "button",
                  {
                    type: "button",
                    className: "btn " + (decision === "cancel" ? "btn-danger" : "btn-primary"),
                    onClick: submit,
                    disabled: sending,
                    children: commit
                  },
                  void 0,
                  false,
                  {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 423,
                    columnNumber: 29
                  }
                )
              ] }, void 0, true, {
                fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                lineNumber: 419,
                columnNumber: 25
              })
            ]
          },
          void 0,
          true,
          {
            fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
            lineNumber: 356,
            columnNumber: 21
          }
        ) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
          lineNumber: 355,
          columnNumber: 17
        })
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
        lineNumber: 345,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
    lineNumber: 343,
    columnNumber: 9
  });
}, "Sheet");
export {
  Sheet
};
//# sourceMappingURL=Sheet.dev.js.map
