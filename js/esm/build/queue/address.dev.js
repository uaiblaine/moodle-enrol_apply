var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The queue's filters live in the page's address (CQ-D6), not in a preference.
 *
 * The names are the ones manage.php reads for its table: `status` (the enrolment status, which is the
 * scope), `search`, `group`, the identity fields' tokens, `appliedfrom` and `appliedto`. What the
 * table has no control for keeps names of its own: `named`, `waited`, `appliedbefore`, `sort`, `dir`,
 * `page` (from 0) and `perpage`. A value at its default is left out. The server reads the address for
 * the first paint (enrol_apply\output\queue_view::initial()), so a link the table page or the course
 * report builds lands filtered; this module writes it back, and the browser's history carries the
 * query for the back and forward buttons. The grid is the method queue's page (CQ-D5), so its address
 * carries no view parameter; the table's carries `view=table`. A decision posts to the grid's address
 * (queue/decide.ts), which manage.php cleans again for the page it lands on.
 *
 * The site-wide queue's "By course" view (SQ-D9) is `show=methods`, a parameter of its own so that it is
 * never read as `view`; there `sort`, `dir`, `page` and `perpage` are that view's, written as the list's
 * are, and its default sort (what awaits, most first) is left out. The table's address never carries it.
 *
 * @module     enrol_apply/queue/address
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const TABLE = "table";
const METHODS = "methods";
const STATE_KEY = "enrolApplyQueue";
const defaultSort = /* @__PURE__ */ __name((query) => {
  if (query.sort === "") {
    return true;
  }
  return query.show === METHODS ? query.sort === "awaiting" && query.direction === "desc" : query.sort === "wait" && query.direction === "asc";
}, "defaultSort");
const params = /* @__PURE__ */ __name((props, query, grid) => {
  const pairs = [];
  if (query.scope !== "all") {
    pairs.push(["status", String(query.scope === "pending" ? props.statuses.pending : props.statuses.deferred)]);
  }
  if (query.search !== "") {
    pairs.push(["search", query.search]);
  }
  Object.keys(query.filters).sort().forEach((name) => {
    if (query.filters[name] !== "") {
      pairs.push([name, query.filters[name]]);
    }
  });
  if (grid) {
    if (query.show === METHODS) {
      pairs.push(["show", METHODS]);
    }
    if (!defaultSort(query)) {
      pairs.push(["sort", query.sort], ["dir", query.direction]);
    }
    if (query.page > 0) {
      pairs.push(["page", String(query.page)]);
    }
    if (query.perpage !== props.perpage) {
      pairs.push(["perpage", String(query.perpage)]);
    }
  } else {
    pairs.push(["view", TABLE]);
  }
  return pairs;
}, "params");
const queueUrl = /* @__PURE__ */ __name((props, query, grid) => {
  const url = new URL(props.pageurl, window.location.href);
  params(props, query, grid).forEach(([name, value]) => url.searchParams.set(name, value));
  return url.toString();
}, "queueUrl");
const initialQuery = /* @__PURE__ */ __name((props) => ({
  scope: props.initial.scope,
  search: props.initial.search,
  filters: Object.fromEntries(props.initial.filters.map((filter) => [filter.name, filter.value])),
  sort: props.initial.sort,
  direction: props.initial.direction,
  page: props.initial.page,
  perpage: props.initial.perpage,
  // The view is drawn only where the props offer it.
  show: props.methods !== null ? props.initial.show : ""
}), "initialQuery");
const writeAddress = /* @__PURE__ */ __name((props, query, push) => {
  const url = queueUrl(props, query, true);
  const state = { [STATE_KEY]: query };
  try {
    if (push && url !== window.location.href) {
      window.history.pushState(state, "", url);
    } else {
      window.history.replaceState(state, "", url);
    }
  } catch {
  }
  document.querySelectorAll('[data-enrol-apply-queue="tablelink"]').forEach((link) => {
    link.href = queueUrl(props, query, false);
  });
}, "writeAddress");
const queryOf = /* @__PURE__ */ __name((state) => {
  if (state === null || typeof state !== "object" || !(STATE_KEY in state)) {
    return null;
  }
  const query = state[STATE_KEY];
  return { ...query, show: query.show ?? "" };
}, "queryOf");
export {
  METHODS,
  TABLE,
  initialQuery,
  queryOf,
  queueUrl,
  writeAddress
};
//# sourceMappingURL=address.dev.js.map
