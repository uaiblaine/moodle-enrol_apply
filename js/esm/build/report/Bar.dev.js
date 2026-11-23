var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The course report's filter bar: the shared bar (datareport/Bar) with the report's vocabulary.
 *
 * The scope is all, awaiting or decided. Filters draws the report's facets, which live in core's store
 * (core_reportbuilder_set_filters, filters.ts): the filters that pick one option of a list, "Waited more
 * than" for the wait the band measures and "Decided by", each option with the rows it would list under
 * every other filter in force, the scope and the search (the rows answer counts them). The other
 * filters are set in core's table view, which the panel's note links, and appear as chips that remove
 * them. Columns starts with three sets (Decisions, Answers, Groups). View is Automatic, Table or Cards,
 * and core's own table view. Download links core's download of the report with the measure, the scope
 * and the search in force, which the report applies itself.
 *
 * @module     enrol_apply/report/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Bar as DataBar } from "../datareport/Bar";
import { fillObject } from "../datareport/str";
import { choosable, chosen, chosenLabel } from "./filters";
const VIEWS = ["auto", "table", "cards"];
const facetTitle = /* @__PURE__ */ __name((labels, filter) => {
  if (filter.facet === "waited") {
    return labels.waitedmore;
  }
  return filter.facet === "decider" ? labels.decidedby : filter.title;
}, "facetTitle");
const presetLabel = /* @__PURE__ */ __name((labels, name) => {
  switch (name) {
    case "decisions":
      return labels.presetdecisions;
    case "answers":
      return labels.presetanswers;
    case "groups":
      return labels.presetgroups;
    default:
      return name;
  }
}, "presetLabel");
const downloadParameters = /* @__PURE__ */ __name((measure, scope, search) => {
  const parameters = { measure };
  if (scope !== "all") {
    parameters.scope = scope;
  }
  if (search !== "") {
    parameters.search = search;
  }
  return encodeURIComponent(JSON.stringify(parameters));
}, "downloadParameters");
const facet = /* @__PURE__ */ __name((labels, filter) => ({
  name: filter.name,
  title: facetTitle(labels, filter),
  options: filter.options,
  groupings: filter.groupings,
  find: filter.facet === "decider" ? { label: labels.searchdeciders, nomatch: labels.nodecidermatch } : { label: labels.searchgroups, nomatch: labels.nogroupmatch }
}), "facet");
const chip = /* @__PURE__ */ __name((labels, filter) => {
  const value = chosenLabel(filter);
  const title = value !== "" ? facetTitle(labels, filter) : filter.title;
  return { key: filter.name, text: value === "" ? title : fillObject(labels.filterchip, { title, value }) };
}, "chip");
const Bar = /* @__PURE__ */ __name((props) => {
  const { labels, filters } = props;
  const waited = props.measure === "resolution" ? "submission:resolutiontime" : "submission:waittime";
  const inpanel = /* @__PURE__ */ __name((filter) => choosable(filter) && (filter.facet !== "waited" || filter.name === waited), "inpanel");
  const panelfilters = filters.filter(inpanel);
  const others = filters.filter((filter) => !inpanel(filter));
  const named = /* @__PURE__ */ __name((name) => filters.find((filter) => filter.name === name), "named");
  const download = props.downloadurl !== "" && props.formats.length > 0;
  const parameters = downloadParameters(props.measure, props.scope, props.appliedsearch);
  const downloads = download ? props.formats.map((format) => ({
    key: format.value,
    label: format.label,
    href: `${props.downloadurl}&parameters=${parameters}&download=${encodeURIComponent(format.value)}`
  })) : [];
  const sets = props.presets.map((preset) => ({
    name: preset.name,
    label: presetLabel(labels, preset.name),
    active: preset.active
  }));
  return /* @__PURE__ */ jsxDEV(
    DataBar,
    {
      labels,
      narrow: props.narrow,
      scopes: [
        { value: "all", label: labels.all },
        { value: "awaiting", label: labels.awaiting },
        { value: "decided", label: labels.decided }
      ],
      scope: props.scope,
      total: props.total,
      facets: panelfilters.map((filter) => facet(labels, filter)),
      values: Object.fromEntries(panelfilters.map((filter) => [filter.name, chosen(filter)])),
      panelnote: others.length > 0 ? /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-note", children: [
        labels.morefiltershelp,
        " ",
        /* @__PURE__ */ jsxDEV("a", { href: props.tableurl, children: labels.morefilters }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
          lineNumber: 219,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
        lineNumber: 217,
        columnNumber: 17
      }) : null,
      chips: filters.filter((filter) => filter.applied).map((filter) => chip(labels, filter)),
      columns: props.columns,
      sets,
      usedefault: props.usedefault,
      haswidths: props.haswidths,
      view: props.view,
      views: VIEWS,
      tableurl: props.tableurl,
      downloads,
      search: props.search,
      searchmin: props.searchmin,
      searching: props.searching,
      onScope: (scope) => props.onScope(scope),
      onSearch: props.onSearch,
      onFacet: (name, value) => {
        const filter = named(name);
        if (filter) {
          props.onFilter(filter, value);
        }
      },
      onRemoveChip: (name) => {
        const filter = named(name);
        if (filter) {
          props.onRemoveFilter(filter);
        }
      },
      onClearFilters: props.onClearFilters,
      onClearAll: props.onClearAll,
      onColumn: props.onColumn,
      onSet: (name) => {
        const preset = props.presets.find((entry) => entry.name === name);
        if (preset) {
          props.onPreset(preset);
        }
      },
      onDefaultColumns: props.onDefaultColumns,
      onResetWidths: props.onResetWidths,
      onView: props.onView
    },
    void 0,
    false,
    {
      fileName: "public/enrol/apply/js/esm/src/report/Bar.tsx",
      lineNumber: 204,
      columnNumber: 9
    }
  );
}, "Bar");
export {
  Bar
};
//# sourceMappingURL=Bar.dev.js.map
