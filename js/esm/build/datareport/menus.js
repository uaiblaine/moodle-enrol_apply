import{Icon as o}from"./icons";import{Fragment as m,jsx as r,jsxs as l}from"react/jsx-runtime";/**
 * The bar's menus (the Design System's menu.md): the keys that move between items, and the Columns
 * menu's sets.
 *
 * @module     enrol_apply/datareport/menus
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const p=t=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(t.key))return;const n=Array.from(t.currentTarget.querySelectorAll('[role^="menuitem"]'));if(n.length===0)return;t.preventDefault();const a=n.indexOf(document.activeElement);let e=0;t.key==="End"?e=n.length-1:t.key==="ArrowDown"?e=(a+1)%n.length:t.key==="ArrowUp"&&(e=(a-1+n.length)%n.length),n[e].focus()},s=({heading:t,sets:n,onSet:a})=>n.length===0?null:l(m,{children:[r("div",{className:"enrol_apply-rview-menu-head",role:"presentation",children:t}),n.map(e=>l("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":e.active,tabIndex:-1,"data-preset":e.name,onClick:()=>{e.active||a(e.name)},children:[r("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:r(o,{name:"tick"})}),r("span",{className:"enrol_apply-rview-menu-label",children:e.label})]},e.name)),r("div",{className:"enrol_apply-rview-menu-sep",role:"separator"})]});export{s as SetItems,p as menuKeys};
