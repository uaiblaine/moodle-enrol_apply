var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The queue's filter bar: the shared bar (datareport/Bar) with the queue's vocabulary.
 *
 * The scope is All, Pending or Deferred. Filters draws the facets the rows answer counts (group, how
 * the group was named, waited more than, applied before; on the site-wide queue the course and the
 * category, each searched and capped with a note of what was left out, the method once a course with two
 * is chosen, and with or without a group) and the identity fields the reader may filter
 * by from a list, which the service does not count; the text fields and the dates are set in the
 * table view, which the panel's note links, and show here as chips that remove them. Columns starts
 * with the three sets (CQ-D7). View is Automatic, Table or Cards, and the table view; the site-wide queue
 * adds "By course" (SQ-D9), whose body the queue view draws itself: while it is shown Columns is left out and
 * the count states the enrolment methods, while the panel's "Show" still states the applications the filters
 * list. No Download (CQ-D11): "Open the report" ends the chips' row for a reader the report admits.
 *
 * @module     enrol_apply/queue/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { Bar as DataBar } from "../datareport/Bar";
import { fill, fillObject, num } from "../datareport/str";
const ANY_GROUP = "all";
const GROUP = "group";
const VIEWS = ["auto", "table", "cards"];
const presetLabel = /* @__PURE__ */ __name((labels, name) => {
  switch (name) {
    case "everything":
      return labels.preseteverything;
    case "glance":
      return labels.presetglance;
    case "answers":
      return labels.presetanswers;
    default:
      return name;
  }
}, "presetLabel");
const finder = /* @__PURE__ */ __name((labels, name) => {
  switch (name) {
    case "course":
      return { label: labels.searchcourses, nomatch: labels.nocoursematch };
    case "category":
      return { label: labels.searchcategories, nomatch: labels.nocategorymatch };
    default:
      return { label: labels.searchgroups, nomatch: labels.nogroupmatch };
  }
}, "finder");
const omittedNote = /* @__PURE__ */ __name((labels, facet) => {
  if (facet.omitted <= 0) {
    return "";
  }
  return fill(facet.name === "category" ? labels.omittedcategories : labels.omittedcourses, num(facet.omitted));
}, "omittedNote");
const facets = /* @__PURE__ */ __name((labels, rows) => {
  const counted = rows.facets.map((facet) => ({
    name: facet.name,
    title: facet.title,
    options: facet.options,
    groupings: [],
    find: finder(labels, facet.name),
    note: omittedNote(labels, facet),
    // The search names every option the facet has, the ones the cap left out included.
    total: facet.options.length + Math.max(0, facet.omitted)
  }));
  const find = finder(labels, "");
  const listed = rows.fieldfilters.filter((field) => field.control === "select").map((field) => ({
    name: field.name,
    title: field.title,
    // A count of -1 is "not counted": the option is shown, with no number.
    options: field.options.map((option) => ({ ...option, count: -1 })),
    groupings: [],
    find
  }));
  return [...counted, ...listed];
}, "facets");
const chip = /* @__PURE__ */ __name((labels, rows, name, value) => {
  let title = name;
  let shown = value;
  const facet = rows.facets.find((entry) => entry.name === name);
  const field = rows.fieldfilters.find((entry) => entry.name === name);
  if (facet) {
    title = facet.title;
    shown = facet.options.find((option) => option.value === value)?.label ?? value;
  } else if (field) {
    title = field.title;
    shown = field.options.find((option) => option.value === value)?.label ?? value;
  } else if (name === "appliedfrom") {
    title = labels.appliedfrom;
  } else if (name === "appliedto") {
    title = labels.appliedto;
  }
  return { key: name, text: fillObject(labels.filterchip, { title, value: shown }) };
}, "chip");
const Bar = /* @__PURE__ */ __name((props) => {
  const { labels, rows, query } = props;
  const bycourse = props.methods !== void 0;
  const sets = props.presets.map((preset) => ({
    name: preset.name,
    label: presetLabel(labels, preset.name),
    active: preset.active
  }));
  const values = Object.fromEntries(Object.entries(query.filters).map(([name, value]) => [name, name === GROUP && value === ANY_GROUP ? "" : value]));
  const chips = rows === null ? [] : rows.filters.map((filter) => chip(labels, rows, filter.name, filter.value));
  return /* @__PURE__ */ jsxDEV(
    DataBar,
    {
      labels,
      narrow: props.narrow,
      scopes: [
        { value: "all", label: labels.all },
        { value: "pending", label: labels.pending },
        { value: "deferred", label: labels.deferred }
      ],
      scope: query.scope,
      total: rows === null ? null : rows.total,
      facets: rows === null ? [] : facets(labels, rows),
      values,
      panelnote: /* @__PURE__ */ jsxDEV("p", { className: "enrol_apply-rview-note", children: [
        labels.morefiltershelp,
        " ",
        /* @__PURE__ */ jsxDEV("a", { href: props.tableurl, children: labels.morefilters }, void 0, false, {
          fileName: "public/enrol/apply/js/esm/src/queue/Bar.tsx",
          lineNumber: 232,
          columnNumber: 21
        })
      ] }, void 0, true, {
        fileName: "public/enrol/apply/js/esm/src/queue/Bar.tsx",
        lineNumber: 230,
        columnNumber: 17
      }),
      chips,
      aside: props.reporturl !== "" ? /* @__PURE__ */ jsxDEV("a", { className: "enrol_apply-qview-tolink", href: props.reporturl, children: labels.openreport }, void 0, false, {
        fileName: "public/enrol/apply/js/esm/src/queue/Bar.tsx",
        lineNumber: 237,
        columnNumber: 17
      }) : null,
      columns: props.columns,
      sets,
      usedefault: props.usedefault,
      haswidths: props.haswidths,
      view: bycourse ? query.show : props.view,
      views: VIEWS,
      extraViews: props.extraViews,
      hidecolumns: bycourse,
      countkey: bycourse ? "methods" : "count",
      counted: props.methods,
      tableurl: props.tableurl,
      downloads: [],
      search: props.search,
      searchmin: props.searchmin,
      searching: props.searching,
      onScope: (scope) => props.onScope(scope),
      onSearch: props.onSearch,
      onFacet: props.onFilter,
      onRemoveChip: (name) => props.onFilter(name, ""),
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
      fileName: "public/enrol/apply/js/esm/src/queue/Bar.tsx",
      lineNumber: 217,
      columnNumber: 9
    }
  );
}, "Bar");
export {
  ANY_GROUP,
  Bar,
  GROUP
};
//# sourceMappingURL=Bar.dev.js.map
