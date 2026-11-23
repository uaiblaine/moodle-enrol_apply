var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The bundle's entry: every React view of the plugin, one chosen per mount by its props.
 *
 * Core's react_autoinit mounts the DEFAULT export of the module a mount names, and the fleet's bundle
 * step builds one entry into one file (js/esm/bundle.json), so every view of the plugin is reached
 * through this one component: each template names @moodle/lms/enrol_apply/bundle, and its props name
 * the view in `mount`. A mount that names none is the course report's, whose props (report_view)
 * predate the key; `queue` is the approval queue of one method (queue_view, mounted by manage.php with
 * view=grid). Another view is one more branch here and a template whose props name it; the views share
 * the data report's parts, so the bundle carries them once.
 *
 * @module     enrol_apply/views
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import QueueView from "./queue/QueueView";
import ReportView from "./report/ReportView";
const Views = /* @__PURE__ */ __name((props) => {
  if (props.mount === void 0 || props.mount === "report") {
    return /* @__PURE__ */ jsxDEV(ReportView, { ...props }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/views.tsx",
      lineNumber: 52,
      columnNumber: 16
    });
  }
  if (props.mount === "queue") {
    return /* @__PURE__ */ jsxDEV(QueueView, { ...props }, void 0, false, {
      fileName: "public/enrol/apply/js/esm/src/views.tsx",
      lineNumber: 55,
      columnNumber: 16
    });
  }
  return null;
}, "Views");
var views_default = Views;
export {
  views_default as default
};
//# sourceMappingURL=views.dev.js.map
