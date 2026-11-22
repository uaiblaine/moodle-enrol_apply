import{jsx as o}from"react/jsx-runtime";/**
 * A segmented control (the Design System's segmented-control.md): one of two to four visible values.
 *
 * A radiogroup with one tab stop, the chosen cell; the arrow keys, Home and End move to another cell
 * and choose it.
 *
 * @module     enrol_apply/report/Segmented
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const i=({label:s,value:a,options:l,small:d,onChange:t})=>{const g=e=>{const n=l.findIndex(y=>y.value===a);let r=-1;if(e.key==="ArrowRight"||e.key==="ArrowDown"?r=(n+1)%l.length:e.key==="ArrowLeft"||e.key==="ArrowUp"?r=(n-1+l.length)%l.length:e.key==="Home"?r=0:e.key==="End"&&(r=l.length-1),r<0)return;e.preventDefault(),t(l[r].value),e.currentTarget.querySelectorAll('[role="radio"]')[r]?.focus()};return o("div",{className:"enrol_apply-rview-seg"+(d?" enrol_apply-rview-seg--small":""),role:"radiogroup","aria-label":s,onKeyDown:g,children:l.map(e=>o("button",{type:"button",className:"enrol_apply-rview-seg-cell",role:"radio","aria-checked":e.value===a,tabIndex:e.value===a?0:-1,onClick:()=>t(e.value),children:e.label},e.value))})};export{i as Segmented};
