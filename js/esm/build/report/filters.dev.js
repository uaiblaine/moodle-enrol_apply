var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The report's filters as core stores them, read and rewritten for the panel and the chips.
 *
 * Core keeps every filter value of a report for a user in one store, keyed "<filter>_<field>", and
 * core_reportbuilder_set_filters REPLACES that whole set. So a change to one filter is written as
 * every stored value, with that filter's own keys changed, and a removal as every other filter's
 * keys. The panel draws the filters the rows answer names a facet for: the ones that pick one option
 * of a list (core's select filter and this plugin's group filter), the wait as "waited more than" a
 * few days (core's duration filter: at least that many days) and the decider's name (core's text
 * filter: is equal to one decider's name). Each writes its filter class's own keys. The others
 * (dates, free text, the other duration) are set in core's table view and appear here as chips that
 * remove them.
 *
 * @module     enrol_apply/report/filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const ANY_VALUE = 0;
const EQUAL_TO = 1;
const DURATION_MINIMUM = 2;
const IS_EQUAL_TO = 3;
const DAY = 86400;
const choosable = /* @__PURE__ */ __name((filter) => filter.facet !== "" && filter.options.length > 0, "choosable");
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
  const own = ownValues(filter);
  const value = own[`${filter.name}_value`];
  if (value === void 0 || value === null) {
    return "";
  }
  const operator = Number(own[`${filter.name}_operator`] ?? ANY_VALUE);
  if (filter.facet === "waited") {
    const days = Number(value) * Number(own[`${filter.name}_unit`] ?? 0) / DAY;
    return operator === DURATION_MINIMUM && Number.isFinite(days) ? String(days) : "";
  }
  if (filter.facet === "decider") {
    return operator === IS_EQUAL_TO ? String(value) : "";
  }
  return String(value);
}, "chosen");
const withChoice = /* @__PURE__ */ __name((filters, filter, value) => {
  const values = without(filters, filter);
  if (filter.facet === "waited") {
    values[`${filter.name}_operator`] = value === "" ? ANY_VALUE : DURATION_MINIMUM;
    values[`${filter.name}_value`] = value === "" ? 0 : Number(value);
    values[`${filter.name}_unit`] = DAY;
  } else if (filter.facet === "decider") {
    values[`${filter.name}_operator`] = value === "" ? ANY_VALUE : IS_EQUAL_TO;
    values[`${filter.name}_value`] = value;
  } else {
    values[`${filter.name}_operator`] = value === "" ? ANY_VALUE : EQUAL_TO;
    values[`${filter.name}_value`] = value;
  }
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
  const value = chosen(filter);
  if (filter.facet === "" || value === "") {
    return "";
  }
  return filter.options.find((option) => option.value === value)?.label ?? (filter.facet === "decider" ? value : "");
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
