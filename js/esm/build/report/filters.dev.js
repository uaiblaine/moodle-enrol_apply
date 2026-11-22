var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The report's filters as core stores them, read and rewritten for the panel and the chips.
 *
 * Core keeps every filter value of a report for a user in one store, keyed "<filter>_<field>", and
 * core_reportbuilder_set_filters REPLACES that whole set. So a change to one filter is written as
 * every stored value, with that filter's own keys changed, and a removal as every other filter's
 * keys. The panel draws only the filters that pick one option (core's select filter and this
 * plugin's group filter); the others (dates, text, durations) are set in core's table view and
 * appear here as chips that remove them.
 *
 * @module     enrol_apply/report/filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const ANY_VALUE = 0;
const EQUAL_TO = 1;
const CHOOSABLE = ["select", "group"];
const choosable = /* @__PURE__ */ __name((filter) => CHOOSABLE.includes(filter.type) && filter.options.length > 0, "choosable");
const ownValues = /* @__PURE__ */ __name((filter) => {
  try {
    const parsed = JSON.parse(filter.values || "{}");
    return parsed !== null && typeof parsed === "object" ? parsed : {};
  } catch {
    return {};
  }
}, "ownValues");
const allValues = /* @__PURE__ */ __name((filters) => filters.reduce((all, filter) => ({ ...all, ...ownValues(filter) }), {}), "allValues");
const chosen = /* @__PURE__ */ __name((filter) => {
  if (!filter.applied) {
    return "";
  }
  const value = ownValues(filter)[`${filter.name}_value`];
  return value === void 0 || value === null ? "" : String(value);
}, "chosen");
const withChoice = /* @__PURE__ */ __name((filters, filter, value) => {
  const values = without(filters, filter);
  values[`${filter.name}_operator`] = value === "" ? ANY_VALUE : EQUAL_TO;
  values[`${filter.name}_value`] = value;
  return values;
}, "withChoice");
const without = /* @__PURE__ */ __name((filters, filter) => {
  const values = allValues(filters);
  for (const key of Object.keys(values)) {
    if (key.startsWith(`${filter.name}_`)) {
      delete values[key];
    }
  }
  return values;
}, "without");
const chosenLabel = /* @__PURE__ */ __name((filter) => {
  if (!choosable(filter)) {
    return "";
  }
  const value = chosen(filter);
  return filter.options.find((option) => option.value === value)?.label ?? "";
}, "chosenLabel");
export {
  allValues,
  choosable,
  chosen,
  chosenLabel,
  ownValues,
  withChoice,
  without
};
//# sourceMappingURL=filters.dev.js.map
