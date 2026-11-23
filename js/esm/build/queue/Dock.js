import{fill as c,num as r}from"../datareport/str";import{jsx as o,jsxs as s}from"react/jsx-runtime";/**
 * The dock under the rows: how many applications are selected and the three decisions.
 *
 * The decisions are the table's (renderer::queue_actions(), which the props carry): the same labels,
 * in the same order and the same styles, Cancel applications, Defer, Confirm. Each opens the decision
 * sheet; nothing posts from here. The count is worded "on this page", because that is what a selection
 * is: it is cleared
 * whenever the page, the scope, a filter, the search or the sort changes. Drawn only while something is
 * selected; the view keeps it out otherwise.
 *
 * @module     enrol_apply/queue/Dock
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const d=({labels:t,count:n,actions:l,onDecide:a})=>s("div",{className:"enrol_apply-qview-dock",role:"region","aria-label":t.dock,"data-region":"enrol_apply-dock",children:[o("span",{className:"enrol_apply-qview-dockcount","aria-live":"polite",children:c(t.selected,r(n))}),o("div",{className:"enrol_apply-qview-dockactions",children:l.map(e=>o("button",{type:"button",className:"btn "+e.classes,onClick:i=>a(e.value,i.currentTarget),children:e.label},e.value))})]});export{d as Dock};
