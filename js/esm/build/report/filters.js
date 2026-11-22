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
 */const i=e=>e.facet!==""&&e.options.length>0,s=e=>{try{const n=JSON.parse(e.values||"{}");return n!==null&&typeof n=="object"?n:{}}catch{return{}}},c=e=>e.reduce((n,t)=>({...n,...s(t)}),{}),a=e=>{if(!e.applied)return"";const n=s(e),t=n[`${e.name}_value`];if(t==null)return"";const o=Number(n[`${e.name}_operator`]??0);if(e.facet==="waited"){const r=Number(t)*Number(n[`${e.name}_unit`]??0)/86400;return o===2&&Number.isFinite(r)?String(r):""}return e.facet==="decider"?o===3?String(t):"":String(t)},_=(e,n,t)=>{const o=u(e,n);return n.facet==="waited"?(o[`${n.name}_operator`]=t===""?0:2,o[`${n.name}_value`]=t===""?0:Number(t),o[`${n.name}_unit`]=86400):n.facet==="decider"?(o[`${n.name}_operator`]=t===""?0:3,o[`${n.name}_value`]=t):(o[`${n.name}_operator`]=t===""?0:1,o[`${n.name}_value`]=t),o},u=(e,n)=>{const t=c(e);for(const o of Object.keys(t))o.startsWith(`${n.name}_`)&&delete t[o];return t},d=e=>{const n=a(e);return e.facet===""||n===""?"":e.options.find(t=>t.value===n)?.label??(e.facet==="decider"?n:"")};export{c as allValues,i as choosable,a as chosen,d as chosenLabel,s as ownValues,_ as withChoice,u as without};
