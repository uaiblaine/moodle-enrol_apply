var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The only module of the queue's view that talks to the server.
 *
 * Every call goes through core's ES module @moodle/lms/core/ajax and is ONE request of its own
 * (fetchOne), as the report's are: a rows read must never ride in a batch behind the preference write
 * it depends on. Core's ajax holds Behat's pending counter while each request is in flight; the view
 * holds it across the render that follows (datareport/pending.ts). None of these decides anything.
 *
 * @module     enrol_apply/queue/repository
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fetchOne } from "@moodle/lms/core/ajax";
const getRows = /* @__PURE__ */ __name((enrolid, query) => fetchOne({
  methodname: "enrol_apply_queue_rows",
  args: {
    enrolid,
    scope: query.scope,
    search: query.search,
    filters: Object.entries(query.filters).map(([name, value]) => ({ name, value })),
    page: query.page,
    perpage: query.perpage,
    sort: query.sort,
    direction: query.direction
  }
}), "getRows");
const getSummary = /* @__PURE__ */ __name((enrolid) => fetchOne({ methodname: "enrol_apply_queue_summary", args: { enrolid } }), "getSummary");
const setPreference = /* @__PURE__ */ __name((enrolid, preference) => fetchOne({
  methodname: "enrol_apply_set_queue_preference",
  args: { enrolid, usedefault: preference.usedefault, columns: preference.columns, widths: preference.widths }
}), "setPreference");
export {
  getRows,
  getSummary,
  setPreference
};
//# sourceMappingURL=repository.dev.js.map
