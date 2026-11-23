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
 */const i=(n,e,o)=>{const r=document.createElement("input");r.type="hidden",r.name=e,r.value=o,n.appendChild(r)},t=n=>{const e=document.createElement("form");e.method="post",e.action=n.action,e.hidden=!0,i(e,"sesskey",n.sesskey),i(e,"formaction",n.decision),n.userenrolments.forEach(o=>i(e,"userenrolments[]",String(o))),i(e,"outcomemessage",n.message),i(e,"decisionnote",n.note),n.decision==="confirm"&&(n.groups?.forEach(o=>i(e,"groups[]",String(o))),n.roleid!==null&&i(e,"roleid",String(n.roleid))),document.body.appendChild(e),e.submit()};export{t as decide};
