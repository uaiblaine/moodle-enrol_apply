/**
 * Words and numbers: filling the strings the props carry, and the wait as the report words it.
 *
 * There is no core/str for an ES module, so the strings arrive translated with their placeholders
 * ("{$a} applications"), and only the client knows the value.
 *
 * @module     enrol_apply/report/str
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const s=(t,n)=>(t||"").split("{$a}").join(n),u=(t,n)=>Object.entries(n).reduce((r,[e,i])=>r.split(`{$a->${e}}`).join(i),t||""),a=(t,n,r,e)=>s(r===1&&t[`${n}_one`]?t[`${n}_one`]:t[n],e),o=t=>{const n=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(n).format(t)}catch{return String(t)}},c=(t,n)=>{const r=Math.max(0,n);if(r<3600)return s(t.waitminutes,o(Math.max(1,Math.round(r/60))));if(r<86400)return s(t.waithours,o(Math.round(r/3600)));let e=Math.floor(r/86400),i=Math.round((r-e*86400)/3600);return i===24&&(e++,i=0),i>0&&e<10?u(t.waitdayshours,{days:o(e),hours:o(i)}):s(t.waitdays,o(e))},g=t=>{const n=document.createElement("template");return n.innerHTML=t,(n.content.textContent||"").replace(/\s+/g," ").trim()};export{c as duration,s as fill,a as fillCount,u as fillObject,o as num,g as plain};
