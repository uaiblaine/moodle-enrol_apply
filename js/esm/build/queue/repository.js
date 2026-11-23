import{fetchOne as s}from"@moodle/lms/core/ajax";/**
 * The only module of the queue's view that talks to the server.
 *
 * Every call goes through core's ES module @moodle/lms/core/ajax and is ONE request of its own
 * (fetchOne), as the report's are: a rows read must never ride in a batch behind the preference write
 * it depends on. Core's ajax holds Behat's pending counter while each request is in flight; the view
 * holds it across the render that follows (datareport/pending.ts). None of these decides anything.
 *
 * @module     enrol_apply/queue/repository
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const n=(r,e)=>s({methodname:"enrol_apply_queue_rows",args:{enrolid:r,scope:e.scope,search:e.search,filters:Object.entries(e.filters).map(([o,t])=>({name:o,value:t})),page:e.page,perpage:e.perpage,sort:e.sort,direction:e.direction}}),a=r=>s({methodname:"enrol_apply_queue_summary",args:{enrolid:r}}),p=(r,e)=>s({methodname:"enrol_apply_set_queue_preference",args:{enrolid:r,usedefault:e.usedefault,columns:e.columns,widths:e.widths}});export{n as getRows,a as getSummary,p as setPreference};
