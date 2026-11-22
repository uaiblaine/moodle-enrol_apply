import{fetchOne as o}from"@moodle/lms/core/ajax";/**
 * The only module of the report's view that talks to the server.
 *
 * Every call goes through core's ES module @moodle/lms/core/ajax (a component cannot import an AMD
 * module), and every call is ONE request of its own (fetchOne), never a batch: core keeps one
 * instance of a report per user for the whole request, so a rows call batched with a summary of
 * another measure, or after a filter write, would read the first call's report
 * (classes/local/reportengine.php says why). Core's ajax holds Behat's pending counter while each
 * request is in flight; the view holds it across the render that follows (pending.ts).
 *
 * @module     enrol_apply/report/repository
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const m=(r,e,s)=>o({methodname:"enrol_apply_report_rows",args:{enrolid:r,...e,facets:s}}),a=(r,e,s,t)=>o({methodname:"enrol_apply_report_summary",args:{enrolid:r,measure:e,scope:s,search:t}}),u=(r,e)=>o({methodname:"enrol_apply_set_report_preference",args:{enrolid:r,usedefault:e.usedefault,columns:e.columns,widths:e.widths}}),i=(r,e,s)=>o({methodname:"core_reportbuilder_set_filters",args:{reportid:r,parameters:JSON.stringify({measure:e}),values:JSON.stringify(s)}}),p=(r,e)=>o({methodname:"core_reportbuilder_filters_reset",args:{reportid:r,parameters:JSON.stringify({measure:e})}});export{m as getRows,a as getSummary,p as resetFilters,i as setFilters,u as setPreference};
