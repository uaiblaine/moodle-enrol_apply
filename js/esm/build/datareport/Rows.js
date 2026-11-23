import{CardsSkeleton as u}from"./Cards";import{GridSkeleton as g}from"./Grid";import{Fragment as s,jsx as o,jsxs as n}from"react/jsx-runtime";/**
 * The rows' region: what it holds while the rows load, fail, are none, or are there.
 *
 * The first load draws a skeleton of the grid or of the cards, with aria-busy and a status for
 * assistive technology; later loads keep the old rows, dimmed, under a 2 px progress line. A failed
 * read says so with a retry, above the old rows when there are some. No rows is the empty state, worded
 * for a filtered view or an empty one. The rows themselves (the view's grid or cards) are the children;
 * a dock the view keeps under them (a queue's decision bar) follows the region.
 *
 * Strings read from the view's labels: caption, loading, loaderror, retry, empty, nomatch, nomatchhelp,
 * scrollhint.
 *
 * @module     enrol_apply/datareport/Rows
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const R=({labels:e,loaded:t,error:l,total:p,filtered:r,cards:a,narrow:c,skeleton:m,children:d,onRetry:y})=>{const i=n("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",o("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:y,children:e.retry})]});return!t&&l?i:t?p===0?n("div",{className:"enrol_apply-rview-empty",children:[o("p",{className:"enrol_apply-rview-empty-title",children:r?e.nomatch:e.empty}),r?o("p",{className:"enrol_apply-rview-empty-text",children:e.nomatchhelp}):null]}):n("div",{className:"enrol_apply-rview-dim",children:[l?i:null,d,a?null:o("p",{className:"enrol_apply-rview-hint",children:e.scrollhint})]}):n(s,{children:[a?o(u,{single:c}):o(g,{...m}),o("span",{className:"visually-hidden",role:"status",children:e.loading})]})},N=e=>n(s,{children:[n("section",{className:"enrol_apply-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"enrol_apply-rows",children:[e.loading&&e.loaded?o("div",{className:"enrol_apply-rview-progress"}):null,o(R,{...e})]}),e.dock??null]});export{N as RowsRegion};
