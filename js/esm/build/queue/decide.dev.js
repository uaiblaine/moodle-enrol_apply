var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * A decision is a real form posted to manage.php (CQ-D4), never a web service.
 *
 * The form carries the table form's own fields under the table form's own names - `sesskey`,
 * `formaction`, one `userenrolments[]` per selected row, `outcomemessage`, `decisionnote`, and for an
 * approval offered the choosers, `groups[]` and `roleid` - so manage.php reads it as it reads the
 * table's (decision::posted()), requires the sesskey and a POST, and decision::apply() runs every guard
 * it has, row by row; what the client selected or offered decides nothing on the server. The form posts
 * to the view's own address (queue/address.ts), which carries the scope, the filters, the search, the
 * sort and the page as the table form's action carries the table's filters; manage.php cleans them
 * again (queue_view::return_url()) and redirects there with its notifications, so the browser lands
 * on the grid it decided from.
 *
 * @module     enrol_apply/queue/decide
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const field = /* @__PURE__ */ __name((form, name, value) => {
  const input = document.createElement("input");
  input.type = "hidden";
  input.name = name;
  input.value = value;
  form.appendChild(input);
}, "field");
const decide = /* @__PURE__ */ __name((posted) => {
  const form = document.createElement("form");
  form.method = "post";
  form.action = posted.action;
  form.hidden = true;
  field(form, "sesskey", posted.sesskey);
  field(form, "formaction", posted.decision);
  posted.userenrolments.forEach((id) => field(form, "userenrolments[]", String(id)));
  field(form, "outcomemessage", posted.message);
  field(form, "decisionnote", posted.note);
  if (posted.decision === "confirm") {
    posted.groups?.forEach((id) => field(form, "groups[]", String(id)));
    if (posted.roleid !== null) {
      field(form, "roleid", String(posted.roleid));
    }
  }
  document.body.appendChild(form);
  form.submit();
}, "decide");
export {
  decide
};
//# sourceMappingURL=decide.dev.js.map
