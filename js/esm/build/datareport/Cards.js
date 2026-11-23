import{useState as m}from"react";import{RowBox as v}from"./Grid";import{jsx as e,jsxs as t}from"react/jsx-runtime";/**
 * The card view (AR-D9): the same rows, page and order as the grid, one article each.
 *
 * A card is a first line (who, and the row's action), the view's fixed lines, then the other chosen
 * fields in a list, at once on a wide screen (a grid of 17.5rem cards) and behind "More" on a narrow
 * one (one column). What each part holds is the view's (`renderCard`); a view that selects rows passes
 * a selection, whose box leads the first line.
 *
 * Strings read from the view's labels: more, less.
 *
 * @module     enrol_apply/datareport/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const w=({labels:r,row:a,single:l,selection:n,renderCard:i})=>{const[s,p]=m(!1),o=i(a),c=!l||s,y=n?.selected.has(a.id)??!1;return t("article",{className:"enrol_apply-rview-card"+(y?" enrol_apply-rview-card--selected":""),role:"listitem","data-record":a.id,children:[t("div",{className:"enrol_apply-rview-card-l1",children:[n?e(v,{selection:n,row:a}):null,o.head]}),o.lines,o.fields.length>0?t("div",{className:"enrol_apply-rview-card-more",children:[l?e("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":s,onClick:()=>p(!s),children:s?r.less:r.more}):null,c?e("dl",{className:"enrol_apply-rview-card-list",children:o.fields.map(d=>t("div",{className:"enrol_apply-rview-card-item",children:[e("dt",{children:d.title}),e("dd",{children:d.content})]},d.key))}):null]}):null]})},b=({labels:r,rows:a,single:l,selection:n,renderCard:i})=>e("div",{className:"enrol_apply-rview-cards"+(l?" enrol_apply-rview-cards--single":""),role:"list",children:a.map(s=>e(w,{labels:r,row:s,single:l,selection:n,renderCard:i},s.id))}),k=({single:r})=>e("div",{className:"enrol_apply-rview-cards"+(r?" enrol_apply-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:r?4:6},(a,l)=>t("div",{className:"enrol_apply-rview-card enrol_apply-rview-sk",children:[e("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),e("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"}),e("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),e("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"})]},l))});export{b as Cards,k as CardsSkeleton};
