import{useId as k}from"react";import{fillObject as v,num as o}from"./str";import{jsx as a,jsxs as b}from"react/jsx-runtime";/**
 * The foot of the rows: which rows these are, the pages and the page size (AR-D14: 30, 50 or 100).
 *
 * The server pages (one data query and one count whatever the size); the client never holds more
 * than one page. Narrow, the pages read "Page 2 of 9" between Previous and Next.
 *
 * @module     enrol_apply/datareport/Pager
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const _=(e,n)=>[...new Set([1,e-1,e,e+1,n])].filter(r=>r>=1&&r<=n).sort((r,u)=>r-u),E=({labels:e,page:n,perpage:r,perpages:u,total:l,shown:y,narrow:h,onPage:c,onPerpage:w})=>{const g=k(),m=Math.max(1,Math.ceil(l/r)),s=n+1,d=l>0?n*r+1:0,N=l>0?d+y-1:0;let i=[];if(h)i=[a("span",{className:"enrol_apply-rview-pager-gap",children:v(e.pageof,{page:o(s),pages:o(m)})},"of")];else{const t=_(s,m);t.forEach((p,f)=>{f>0&&p-t[f-1]>1&&i.push(a("span",{className:"enrol_apply-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${p}`)),i.push(a("button",{type:"button","aria-current":p===s?"page":void 0,onClick:()=>c(p-1),children:o(p)},p))})}return b("div",{className:"enrol_apply-rview-foot",children:[a("span",{className:"enrol_apply-rview-num","data-region":"enrol_apply-range",children:v(e.range,{from:o(d),to:o(N),total:o(l)})}),m>1?b("nav",{className:"enrol_apply-rview-pager","aria-label":e.pages,children:[a("button",{type:"button",disabled:s===1,onClick:()=>c(n-1),children:e.previous}),i,a("button",{type:"button",disabled:s===m,onClick:()=>c(n+1),children:e.next})]}):null,b("span",{className:"enrol_apply-rview-perpage",children:[a("label",{htmlFor:g,children:e.perpage}),a("select",{id:g,className:"form-select form-select-sm",value:r,onChange:t=>w(Number(t.target.value)),children:u.map(t=>a("option",{value:t,children:o(t)},t))})]})]})};export{E as Pager};
