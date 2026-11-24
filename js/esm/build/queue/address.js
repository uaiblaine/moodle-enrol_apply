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
 */const c="table",n="methods",a="enrolApplyQueue",h=e=>e.sort===""?!0:e.show===n?e.sort==="awaiting"&&e.direction==="desc":e.sort==="wait"&&e.direction==="asc",p=(e,t,o)=>{const i=[];return t.scope!=="all"&&i.push(["status",String(t.scope==="pending"?e.statuses.pending:e.statuses.deferred)]),t.search!==""&&i.push(["search",t.search]),Object.keys(t.filters).sort().forEach(s=>{t.filters[s]!==""&&i.push([s,t.filters[s]])}),o?(t.show===n&&i.push(["show",n]),h(t)||i.push(["sort",t.sort],["dir",t.direction]),t.page>0&&i.push(["page",String(t.page)]),t.perpage!==e.perpage&&i.push(["perpage",String(t.perpage)])):i.push(["view",c]),i},l=(e,t,o)=>{const i=new URL(e.pageurl,window.location.href);return p(e,t,o).forEach(([s,r])=>i.searchParams.set(s,r)),i.toString()},f=e=>({scope:e.initial.scope,search:e.initial.search,filters:Object.fromEntries(e.initial.filters.map(t=>[t.name,t.value])),sort:e.initial.sort,direction:e.initial.direction,page:e.initial.page,perpage:e.initial.perpage,show:e.methods!==null?e.initial.show:""}),u=(e,t,o)=>{const i=l(e,t,!0),s={[a]:t};try{o&&i!==window.location.href?window.history.pushState(s,"",i):window.history.replaceState(s,"",i)}catch{}document.querySelectorAll('[data-enrol-apply-queue="tablelink"]').forEach(r=>{r.href=l(e,t,!1)})},w=e=>{if(e===null||typeof e!="object"||!(a in e))return null;const t=e[a];return{...t,show:t.show??""}};export{n as METHODS,c as TABLE,f as initialQuery,w as queryOf,l as queueUrl,u as writeAddress};
