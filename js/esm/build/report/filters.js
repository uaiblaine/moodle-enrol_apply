/**
 * The report's filters as core stores them, read and rewritten for the panel and the chips.
 *
 * Core keeps every filter value of a report for a user in one store, keyed "<filter>_<field>", and
 * core_reportbuilder_set_filters REPLACES that whole set. So a change to one filter is written as
 * every stored value, with that filter's own keys changed, and a removal as every other filter's
 * keys. The panel draws only the filters that pick one option (core's select filter and this
 * plugin's group filter); the others (dates, text, durations) are set in core's table view and
 * appear here as chips that remove them.
 *
 * @module     enrol_apply/report/filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const s=["select","group"],c=n=>s.includes(n.type)&&n.options.length>0,r=n=>{try{const e=JSON.parse(n.values||"{}");return e!==null&&typeof e=="object"?e:{}}catch{return{}}},u=n=>n.reduce((e,t)=>({...e,...r(t)}),{}),l=n=>{if(!n.applied)return"";const e=r(n)[`${n.name}_value`];return e==null?"":String(e)},a=(n,e,t)=>{const o=i(n,e);return o[`${e.name}_operator`]=t===""?0:1,o[`${e.name}_value`]=t,o},i=(n,e)=>{const t=u(n);for(const o of Object.keys(t))o.startsWith(`${e.name}_`)&&delete t[o];return t},p=n=>{if(!c(n))return"";const e=l(n);return n.options.find(t=>t.value===e)?.label??""};export{u as allValues,c as choosable,l as chosen,p as chosenLabel,r as ownValues,a as withChoice,i as without};
