var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * The decision sheet: what the table's sheet (amd/src/manage.js, MQ-D5, MQ-D6) says and asks, as a
 * modal dialog of the view's own.
 *
 * Its title names the decision and the count, its list the first five applicants and how many more;
 * a cancellation says what cancelling removes, a deferral where the application stays, an approval
 * that every place is taken when the table page says so. Then the fields of the table's decision
 * controls, with their labels and help: for an approval the groups and the role where the props offer
 * them (a holder of enrol/apply:choosedecision) or the summary of what an approval gives, then the
 * message to the applicant and the note for the record, which every decision posts. The sheet's commit
 * button is the confirmation, worded as the table's ("Cancel 2"); Back, the close button, Escape and a
 * press outside close it without posting anything.
 *
 * Bootstrap's modal markup, drawn by React rather than by Bootstrap's script, so Moodle's dialogue
 * locator finds it by its title. The focus moves into the dialog when it opens and stays there (Tab and
 * Shift+Tab wrap); the view gives it back to the dock button that opened it.
 *
 * @module     enrol_apply/queue/Sheet
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useId, useRef, useState } from "react";
import { fill, num } from "../datareport/str";
const NAMED = 5;
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
  lineNumber: 95,
  columnNumber: 72
}), "Help");
const Sheet = /* @__PURE__ */ __name(({ labels, decision, rows, offer, onClose, onCommit }) => {
  const id = useId();
  const dialog = useRef(null);
  const [message, setMessage] = useState("");
  const [note, setNote] = useState("");
  const [groups, setGroups] = useState(
    () => (offer.groups?.options ?? []).filter((group) => group.selected).map((group) => String(group.id))
  );
  const [roleid, setRoleid] = useState("0");
  const [sending, setSending] = useState(false);
  const count = rows.length;
  const [title, commit] = wording(labels, decision, count);
  const approval = decision === "confirm";
  useEffect(() => {
    dialog.current?.focus();
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
    if (event.key !== "Tab" || !dialog.current) {
      return;
    }
    const items = Array.from(dialog.current.querySelectorAll(FOCUSABLE));
    if (items.length === 0) {
      event.preventDefault();
      return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    const at = document.activeElement;
    if (event.shiftKey && (at === first || at === dialog.current)) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && (at === last || at === dialog.current)) {
      event.preventDefault();
      first.focus();
    }
  }, "keydown");
  const submit = /* @__PURE__ */ __name(() => {
    if (sending) {
      return;
    }
    setSending(true);
    onCommit({
      message,
      note,
      groups: approval && offer.groups ? groups.map(Number) : null,
      roleid: approval && offer.roles ? Number(roleid) : null
    });
  }, "submit");
  const more = count - NAMED;
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("div", { className: "modal-backdrop show" }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
      lineNumber: 183,
      columnNumber: 13
    }),
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        className: "modal show d-block enrol_apply-qview-sheet",
        role: "presentation",
        onMouseDown: (event) => {
          if (event.target === event.currentTarget && !sending) {
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
                /* @__PURE__ */ jsxDEV("h5", { className: "modal-title", id: `${id}-title`, children: title }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 206,
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
                    lineNumber: 207,
                    columnNumber: 29
                  }
                )
              ] }, void 0, true, {
                fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                lineNumber: 205,
                columnNumber: 25
              }),
              /* @__PURE__ */ jsxDEV("div", { className: "modal-body", children: [
                decision === "cancel" ? /* @__PURE__ */ jsxDEV("div", { className: "alert alert-danger", children: labels.sheetcanceldanger }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 216,
                  columnNumber: 54
                }) : null,
                decision === "wait" ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-qview-sheetline", children: labels.sheetwaitline }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 217,
                  columnNumber: 52
                }) : null,
                approval && offer.placesfull !== "" ? /* @__PURE__ */ jsxDEV("div", { className: "alert alert-warning", children: offer.placesfull }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 219,
                  columnNumber: 33
                }) : null,
                /* @__PURE__ */ jsxDEV("ul", { className: "enrol_apply-qview-sheetwho", children: [
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
                        fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                        lineNumber: 225,
                        columnNumber: 41
                      }
                    ),
                    /* @__PURE__ */ jsxDEV("span", { children: row.applicant.fullname }, void 0, false, {
                      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                      lineNumber: 230,
                      columnNumber: 41
                    })
                  ] }, row.id, true, {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 223,
                    columnNumber: 37
                  })),
                  more > 0 ? /* @__PURE__ */ jsxDEV("li", { className: "enrol_apply-qview-sheetmore", children: fill(labels.andmore, num(more)) }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 234,
                    columnNumber: 37
                  }) : null
                ] }, void 0, true, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 221,
                  columnNumber: 29
                }),
                approval && offer.summary ? /* @__PURE__ */ jsxDEV("div", { className: "enrol_apply-qview-sheetsummary", children: [
                  /* @__PURE__ */ jsxDEV("dl", { className: "row mb-0", children: [
                    /* @__PURE__ */ jsxDEV("dt", { className: "col-sm-4", children: offer.summary.groupslabel }, void 0, false, {
                      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                      lineNumber: 240,
                      columnNumber: 41
                    }),
                    /* @__PURE__ */ jsxDEV("dd", { className: "col-sm-8", children: offer.summary.groups }, void 0, false, {
                      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                      lineNumber: 241,
                      columnNumber: 41
                    }),
                    /* @__PURE__ */ jsxDEV("dt", { className: "col-sm-4", children: offer.summary.rolelabel }, void 0, false, {
                      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                      lineNumber: 242,
                      columnNumber: 41
                    }),
                    /* @__PURE__ */ jsxDEV("dd", { className: "col-sm-8", children: offer.summary.role }, void 0, false, {
                      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                      lineNumber: 243,
                      columnNumber: 41
                    })
                  ] }, void 0, true, {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 239,
                    columnNumber: 37
                  }),
                  /* @__PURE__ */ jsxDEV("div", { className: "form-text", children: [
                    offer.summary.help,
                    offer.summary.note !== "" ? " " + offer.summary.note : ""
                  ] }, void 0, true, {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 245,
                    columnNumber: 37
                  })
                ] }, void 0, true, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 238,
                  columnNumber: 33
                }) : null,
                approval && offer.groups ? /* @__PURE__ */ jsxDEV("div", { className: "mb-3", children: [
                  /* @__PURE__ */ jsxDEV("label", { className: "form-label", htmlFor: `${id}-groups`, children: offer.groups.label }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 253,
                    columnNumber: 37
                  }),
                  /* @__PURE__ */ jsxDEV(
                    "select",
                    {
                      className: "form-select",
                      id: `${id}-groups`,
                      multiple: true,
                      value: groups,
                      "aria-describedby": `${id}-groupshelp`,
                      onChange: (event) => setGroups(
                        Array.from(event.target.selectedOptions, (option) => option.value)
                      ),
                      children: offer.groups.options.map((group) => /* @__PURE__ */ jsxDEV("option", { value: String(group.id), children: group.name }, group.id, false, {
                        fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                        lineNumber: 265,
                        columnNumber: 45
                      }))
                    },
                    void 0,
                    false,
                    {
                      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                      lineNumber: 254,
                      columnNumber: 37
                    }
                  ),
                  /* @__PURE__ */ jsxDEV(Help, { id: `${id}-groupshelp`, text: offer.groups.help }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 268,
                    columnNumber: 37
                  })
                ] }, void 0, true, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 252,
                  columnNumber: 33
                }) : null,
                approval && offer.roles ? /* @__PURE__ */ jsxDEV("div", { className: "mb-3", children: [
                  /* @__PURE__ */ jsxDEV("label", { className: "form-label", htmlFor: `${id}-role`, children: offer.roles.label }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 273,
                    columnNumber: 37
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
                          lineNumber: 281,
                          columnNumber: 41
                        }),
                        offer.roles.options.map((role) => /* @__PURE__ */ jsxDEV("option", { value: String(role.id), children: role.name }, role.id, false, {
                          fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                          lineNumber: 283,
                          columnNumber: 45
                        }))
                      ]
                    },
                    void 0,
                    true,
                    {
                      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                      lineNumber: 274,
                      columnNumber: 37
                    }
                  ),
                  /* @__PURE__ */ jsxDEV(Help, { id: `${id}-rolehelp`, text: offer.roles.help }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 286,
                    columnNumber: 37
                  })
                ] }, void 0, true, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 272,
                  columnNumber: 33
                }) : null,
                /* @__PURE__ */ jsxDEV("div", { className: "mb-3", children: [
                  /* @__PURE__ */ jsxDEV("label", { className: "form-label", htmlFor: `${id}-message`, children: offer.message.label }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 290,
                    columnNumber: 33
                  }),
                  /* @__PURE__ */ jsxDEV(
                    "textarea",
                    {
                      className: "form-control",
                      id: `${id}-message`,
                      rows: 3,
                      value: message,
                      "aria-describedby": `${id}-messagehelp`,
                      onChange: (event) => setMessage(event.target.value)
                    },
                    void 0,
                    false,
                    {
                      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                      lineNumber: 291,
                      columnNumber: 33
                    }
                  ),
                  /* @__PURE__ */ jsxDEV(Help, { id: `${id}-messagehelp`, text: offer.message.help }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 299,
                    columnNumber: 33
                  })
                ] }, void 0, true, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 289,
                  columnNumber: 29
                }),
                /* @__PURE__ */ jsxDEV("div", { className: "mb-0", children: [
                  /* @__PURE__ */ jsxDEV("label", { className: "form-label", htmlFor: `${id}-note`, children: offer.note.label }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 302,
                    columnNumber: 33
                  }),
                  /* @__PURE__ */ jsxDEV(
                    "textarea",
                    {
                      className: "form-control",
                      id: `${id}-note`,
                      rows: 3,
                      value: note,
                      "aria-describedby": `${id}-notehelp`,
                      onChange: (event) => setNote(event.target.value)
                    },
                    void 0,
                    false,
                    {
                      fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                      lineNumber: 303,
                      columnNumber: 33
                    }
                  ),
                  /* @__PURE__ */ jsxDEV(Help, { id: `${id}-notehelp`, text: offer.note.help }, void 0, false, {
                    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                    lineNumber: 311,
                    columnNumber: 33
                  })
                ] }, void 0, true, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 301,
                  columnNumber: 29
                })
              ] }, void 0, true, {
                fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                lineNumber: 215,
                columnNumber: 25
              }),
              /* @__PURE__ */ jsxDEV("div", { className: "modal-footer", children: [
                /* @__PURE__ */ jsxDEV("button", { type: "button", className: "btn btn-secondary", onClick: onClose, disabled: sending, children: labels.back }, void 0, false, {
                  fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                  lineNumber: 315,
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
                    lineNumber: 318,
                    columnNumber: 29
                  }
                )
              ] }, void 0, true, {
                fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
                lineNumber: 314,
                columnNumber: 25
              })
            ]
          },
          void 0,
          true,
          {
            fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
            lineNumber: 195,
            columnNumber: 21
          }
        ) }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
          lineNumber: 194,
          columnNumber: 17
        })
      },
      void 0,
      false,
      {
        fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
        lineNumber: 184,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/enrol/apply/js/esm/src/queue/Sheet.tsx",
    lineNumber: 182,
    columnNumber: 9
  });
}, "Sheet");
export {
  Sheet
};
//# sourceMappingURL=Sheet.dev.js.map
