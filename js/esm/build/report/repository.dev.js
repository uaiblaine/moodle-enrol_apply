var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The only module of the report's view that talks to the server.
 *
 * Every call goes through core's ES module @moodle/lms/core/ajax (a component cannot import an AMD
 * module), and every call is ONE request of its own (fetchOne), never a batch: core keeps one
 * instance of a report per user for the whole request, so a rows call batched with a summary of
 * another measure, or after a filter write, would read the first call's report
 * (classes/local/reportengine.php says why). Core's ajax holds Behat's pending counter while each
 * request is in flight; the view holds it across the render that follows (pending.ts).
 *
 * @module     enrol_apply/report/repository
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fetchOne } from "@moodle/lms/core/ajax";
const getRows = /* @__PURE__ */ __name((enrolid, query) => fetchOne({ methodname: "enrol_apply_report_rows", args: { enrolid, ...query } }), "getRows");
const getSummary = /* @__PURE__ */ __name((enrolid, measure, scope, search) => fetchOne({ methodname: "enrol_apply_report_summary", args: { enrolid, measure, scope, search } }), "getSummary");
const setPreference = /* @__PURE__ */ __name((enrolid, preference) => fetchOne({
  methodname: "enrol_apply_set_report_preference",
  args: { enrolid, usedefault: preference.usedefault, columns: preference.columns, widths: preference.widths }
}), "setPreference");
const setFilters = /* @__PURE__ */ __name((reportid, measure, values) => fetchOne({
  methodname: "core_reportbuilder_set_filters",
  args: { reportid, parameters: JSON.stringify({ measure }), values: JSON.stringify(values) }
}), "setFilters");
const resetFilters = /* @__PURE__ */ __name((reportid, measure) => fetchOne({
  methodname: "core_reportbuilder_filters_reset",
  args: { reportid, parameters: JSON.stringify({ measure }) }
}), "resetFilters");
export {
  getRows,
  getSummary,
  resetFilters,
  setFilters,
  setPreference
};
//# sourceMappingURL=repository.dev.js.map
