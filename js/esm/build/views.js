import r from"./queue/QueueView";import o from"./report/ReportView";import{jsx as t}from"react/jsx-runtime";/**
 * The bundle's entry: every React view of the plugin, one chosen per mount by its props.
 *
 * Core's react_autoinit mounts the DEFAULT export of the module a mount names, and the fleet's bundle
 * step builds one entry into one file (js/esm/bundle.json), so every view of the plugin is reached
 * through this one component: each template names @moodle/lms/enrol_apply/bundle, and its props name
 * the view in `mount`. A mount that names none is the course report's, whose props (report_view)
 * predate the key; `queue` is the approval queue of one method (queue_view, mounted by manage.php with
 * view=grid). Another view is one more branch here and a template whose props name it; the views share
 * the data report's parts, so the bundle carries them once.
 *
 * @module     enrol_apply/views
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const u=e=>e.mount===void 0||e.mount==="report"?t(o,{...e}):e.mount==="queue"?t(r,{...e}):null;var n=u;export{n as default};
