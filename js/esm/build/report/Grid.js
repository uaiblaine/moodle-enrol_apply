import{Grid as v}from"../datareport/Grid";import{CellLines as a,IdentityLines as g,ReviewButton as b,SIZING as R,cellHtml as d}from"./cells";import{Fragment as k,jsx as t,jsxs as o}from"react/jsx-runtime";/**
 * The course report's grid: the shared bounded grid (datareport/Grid) with the report's cells.
 *
 * The lead column is the applicant: their name as the report formats it, the identity fields the
 * reader's report shows under it, and the Review action on an open application. Every other cell is
 * the report's formatted HTML, one fact per line, and the wait's carries a bar of the row's wait against
 * the longest on the page. The report selects nothing.
 *
 * @module     enrol_apply/report/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const f=({row:i,longest:r})=>!i.open&&!i.decidedbyperson?null:t("span",{className:"enrol_apply-rview-waitbar"+(i.open?" enrol_apply-rview-waitbar--open":""),"aria-hidden":"true",children:t("span",{style:{width:`${Math.min(100,Math.round(i.wait/r*100))}%`}})}),W=({labels:i,columns:r,identity:s,rows:l,widths:p,sort:m,direction:y,onSort:c,onWidth:w,onWidthCommit:u})=>{const h=Math.max(86400,...l.filter(e=>e.open||e.decidedbyperson).map(e=>e.wait));return t(v,{labels:i,columns:r,lead:"applicant",rows:l,widths:p,sizing:R,sort:m,direction:y,renderLead:(e,n)=>o("div",{className:"enrol_apply-rview-applicant",children:[o("div",{className:"enrol_apply-rview-applicant-text",children:[t(a,{column:n.key,html:d(e,n.key)}),t(g,{row:e,identity:s})]}),t(b,{row:e,label:i.review})]}),renderCell:(e,n)=>o(k,{children:[t(a,{column:n.key,html:d(e,n.key)}),n.key==="wait"?t(f,{row:e,longest:h}):null]}),onSort:c,onWidth:w,onWidthCommit:u})};export{W as Grid};
