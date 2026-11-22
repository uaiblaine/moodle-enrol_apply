import{plain as s}from"./str";import{Fragment as l,jsx as o}from"react/jsx-runtime";/**
 * The report's cells as the grid and the cards draw them, and the columns' widths.
 *
 * A cell is the report's own formatted HTML (format_row(): the masking and the escaping already ran
 * on the server, as on core's table), so it is inserted as HTML and never built here. A few cells hold
 * several facts, one per line (the formatters join them with a newline: the outcome and what the
 * record says, the wait and how it ended, the date and who decided, the group and the groups joined);
 * the first line is the cell's line and the others its sub-lines. Every line ends in an ellipsis, its
 * whole text in its title.
 *
 * @module     enrol_apply/report/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const m=["outcome","wait","decided","group"],c={applicant:260,outcome:180,submitted:132,wait:150,decided:172,group:200,method:170,route:170,comment:240,note:240},a=180,p={applicant:160,submitted:104,decided:104},u=80,d=800,g=t=>c[t]??a,w=t=>p[t]??u,I=(t,e)=>Math.min(d,Math.max(w(e),t[e]??g(e))),y=(t,e)=>t.cells.find(n=>n.key===e)?.html??"",b=(t,e)=>m.includes(t)?e.split(`
`).map(n=>n.trim()).filter(n=>n!==""):[e],R=({column:t,html:e})=>{const n=b(t,e);return o(l,{children:n.map((r,i)=>o("span",{className:i===0?"enrol_apply-rview-c":"enrol_apply-rview-sub",title:s(r),dangerouslySetInnerHTML:{__html:r}},i))})},h=({row:t,label:e})=>t.reviewurl===""?null:o("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:t.reviewurl,children:e}),f=({row:t,identity:e})=>o(l,{children:e.map(n=>{const r=y(t,n.key);return r===""?null:o("span",{className:"enrol_apply-rview-sub",title:s(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});export{R as CellLines,f as IdentityLines,d as MAXIMUM,h as ReviewButton,y as cellHtml,g as defaultWidth,b as lines,w as minimumWidth,I as widthOf};
