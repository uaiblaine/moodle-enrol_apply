var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * A segmented control (the Design System's segmented-control.md): one of two to four visible values.
 *
 * A radiogroup with one tab stop, the chosen cell; the arrow keys, Home and End move to another cell
 * and choose it.
 *
 * @module     enrol_apply/report/Segmented
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const Segmented = /* @__PURE__ */ __name(({ label, value, options, small, onChange }) => {
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
    onChange(options[next].value);
    const cells = event.currentTarget.querySelectorAll('[role="radio"]');
    cells[next]?.focus();
  }, "keydown");
  return /* @__PURE__ */ jsxDEV(
    "div",
    {
      className: "enrol_apply-rview-seg" + (small ? " enrol_apply-rview-seg--small" : ""),
      role: "radiogroup",
      "aria-label": label,
      onKeyDown: keydown,
      children: options.map((option) => /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "enrol_apply-rview-seg-cell",
          role: "radio",
          "aria-checked": option.value === value,
          tabIndex: option.value === value ? 0 : -1,
          onClick: () => onChange(option.value),
          children: option.label
        },
        option.value,
        false,
        {
          fileName: "public/enrol/apply/js/esm/src/report/Segmented.tsx",
          lineNumber: 79,
          columnNumber: 17
        }
      ))
    },
    void 0,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/report/Segmented.tsx",
      lineNumber: 72,
      columnNumber: 9
    }
  );
}, "Segmented");
export {
  Segmented
};
//# sourceMappingURL=Segmented.dev.js.map
