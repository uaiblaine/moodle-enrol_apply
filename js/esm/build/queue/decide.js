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
 * A selection on the site-wide queue that spans methods posts what the site-wide table's sheet posts
 * (amd/src/manage.js, commit()): the page's own `outcomemessage` empty, and one
 * `outcomemessages[<enrolid>]` per method, keyed by the method id the rows service gave the rows, which
 * decision::groups() uses only to look up a message for the rows the database says belong to that method;
 * and never a chooser, which that sheet does not offer (SQ-D10).
 *
 * @module     enrol_apply/queue/decide
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const s=(e,n,i)=>{const r=document.createElement("input");r.type="hidden",r.name=n,r.value=i,e.appendChild(r)},o=e=>{const n=document.createElement("form");n.method="post",n.action=e.action,n.hidden=!0,s(n,"sesskey",e.sesskey),s(n,"formaction",e.decision),e.userenrolments.forEach(i=>s(n,"userenrolments[]",String(i))),s(n,"outcomemessage",e.messages===null?e.message:""),Object.entries(e.messages??{}).forEach(([i,r])=>s(n,`outcomemessages[${i}]`,r)),s(n,"decisionnote",e.note),e.decision==="confirm"&&e.messages===null&&(e.groups?.forEach(i=>s(n,"groups[]",String(i))),e.roleid!==null&&s(n,"roleid",String(e.roleid))),document.body.appendChild(n),n.submit()};export{o as decide};
