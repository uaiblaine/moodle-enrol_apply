import{plain as p}from"./str";import{Fragment as i,jsx as n}from"react/jsx-runtime";/**
 * A cell drawn from the server's formatted HTML, one fact per line.
 *
 * A data report's cells are formatted on the server, where the masking and the escaping ran, so a cell
 * is inserted as HTML and never built here. A cell that holds several facts has them joined by a
 * newline; the first line is the cell's line and the others its sub-lines. Every line ends in an
 * ellipsis, its whole text in its title.
 *
 * @module     enrol_apply/datareport/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const s=(t,r)=>r?t.split(`
`).map(e=>e.trim()).filter(e=>e!==""):[t],o=({parts:t})=>n(i,{children:t.map((r,e)=>n("span",{className:e===0?"enrol_apply-rview-c":"enrol_apply-rview-sub",title:p(r),dangerouslySetInnerHTML:{__html:r}},e))});export{o as Lines,s as splitLines};
