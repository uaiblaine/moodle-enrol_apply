/**
 * The queue's filters live in the page's address (CQ-D6), not in a preference.
 *
 * The names are the ones manage.php reads for its table: `status` (the enrolment status, which is the
 * scope), `search`, `group`, the identity fields' tokens, `appliedfrom` and `appliedto`. What the
 * table has no control for keeps names of its own: `named`, `waited`, `appliedbefore`, `sort`, `dir`,
 * `page` (from 0) and `perpage`. A value at its default is left out. The server reads the address for
 * the first paint (enrol_apply\output\queue_view::initial()), so a link the table page or the course
 * report builds lands filtered; this module writes it back, and the browser's history carries the
 * query for the back and forward buttons.
 *
 * @module     enrol_apply/queue/address
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const l="grid",o="enrolApplyQueue",c=(t,e,n)=>{const i=[];return e.scope!=="all"&&i.push(["status",String(e.scope==="pending"?t.statuses.pending:t.statuses.deferred)]),e.search!==""&&i.push(["search",e.search]),Object.keys(e.filters).sort().forEach(r=>{e.filters[r]!==""&&i.push([r,e.filters[r]])}),n&&(e.sort!==""&&(e.sort!=="wait"||e.direction!=="asc")&&i.push(["sort",e.sort],["dir",e.direction]),e.page>0&&i.push(["page",String(e.page)]),e.perpage!==t.perpage&&i.push(["perpage",String(e.perpage)]),i.push(["view",l])),i},a=(t,e,n)=>{const i=new URL(t.pageurl,window.location.href);return c(t,e,n).forEach(([r,s])=>i.searchParams.set(r,s)),i.toString()},p=t=>({scope:t.initial.scope,search:t.initial.search,filters:Object.fromEntries(t.initial.filters.map(e=>[e.name,e.value])),sort:t.initial.sort,direction:t.initial.direction,page:t.initial.page,perpage:t.initial.perpage}),f=(t,e,n)=>{const i=a(t,e,!0),r={[o]:e};try{n&&i!==window.location.href?window.history.pushState(r,"",i):window.history.replaceState(r,"",i)}catch{}document.querySelectorAll('[data-enrol-apply-queue="tablelink"]').forEach(s=>{s.href=a(t,e,!1)})},g=t=>t===null||typeof t!="object"||!(o in t)?null:t[o];export{l as GRID,p as initialQuery,g as queryOf,a as queueUrl,f as writeAddress};
