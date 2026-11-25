import{fill as a,num as l}from"../datareport/str";import{jsx as t,jsxs as c}from"react/jsx-runtime";/**
 * The dock under the rows: how many applications are selected and the three decisions.
 *
 * The decisions are the table's (renderer::queue_actions(), which the props carry): the same labels,
 * in the same order and the same styles, Cancel applications, Defer, Confirm. Each opens the decision
 * sheet; nothing posts from here. The count is worded "on this page", because that is what a selection
 * is: it is cleared
 * whenever the page, the scope, a filter, the search or the sort changes. On the queues that span
 * methods it adds how many methods the selection spans, when more than one, because the sheet then asks a
 * message per method. Drawn only while something is selected; the view keeps it out otherwise.
 *
 * @module     enrol_apply/queue/Dock
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const m=({labels:o,count:i,methods:n,actions:s,onDecide:p})=>c("div",{className:"enrol_apply-qview-dock",role:"region","aria-label":o.dock,"data-region":"enrol_apply-dock",children:[c("span",{className:"enrol_apply-qview-dockcount","aria-live":"polite",children:[a(o.selected,l(i)),n>1?t("span",{className:"enrol_apply-qview-dockmethods",children:" "+a(o.dockmethods,l(n))}):null]}),t("div",{className:"enrol_apply-qview-dockactions",children:s.map(e=>t("button",{type:"button",className:"btn "+e.classes,onClick:r=>p(e.value,r.currentTarget),children:e.label},e.value))})]});export{m as Dock};
