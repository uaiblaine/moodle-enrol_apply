import{Lines as i,splitLines as s}from"../datareport/cells";import{makeSizing as l}from"../datareport/sizing";import{plain as m}from"../datareport/str";import{Fragment as I,jsx as o}from"react/jsx-runtime";/**
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
 */const c=["outcome","wait","decided","group"],p={applicant:260,outcome:180,submitted:132,wait:150,decided:172,group:200,method:170,route:170,comment:240,note:240},a=180,u={applicant:200,submitted:104,decided:104},d=80,g=800,L=l(p,a,u,d,g),w=(t,e)=>t.cells.find(n=>n.key===e)?.html??"",y=(t,e)=>s(e,c.includes(t)),f=({column:t,html:e})=>o(i,{parts:y(t,e)}),h=({row:t,label:e})=>t.reviewurl===""?null:o("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:t.reviewurl,children:e}),v=({row:t,identity:e})=>o(I,{children:e.map(n=>{const r=w(t,n.key);return r===""?null:o("span",{className:"enrol_apply-rview-sub",title:m(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});export{f as CellLines,v as IdentityLines,h as ReviewButton,L as SIZING,w as cellHtml,y as lines};
