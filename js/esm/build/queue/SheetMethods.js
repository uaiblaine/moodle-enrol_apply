import{fill as m,num as p}from"../datareport/str";import{MethodTags as g}from"./cells";import{Fragment as c,jsx as o,jsxs as l}from"react/jsx-runtime";/**
 * The decision sheet's lists of who is decided: the whole selection's, and, when a selection on the
 * site-wide queue spans enrolment methods, one section per method (SQ-D10, the table's sheet in
 * amd/src/manage.js, methodSection()).
 *
 * A section names the course and the method (text), the method's labels (it grants a deciding role, it is
 * at its limit, with the warning for an approval there), how many of the selection are its and who, and a
 * message box for them, posted as outcomemessages[<the method's id>]; the id is the one the rows service
 * gave the row, and the server groups the posted rows by the method the database names whatever the key
 * says. Each section links to the method's own queue where the reader may open it, the one place groups
 * and a role are chosen; a mentor may open none (the rows service sends them no address), so their
 * sections carry neither the link nor the sentence about choosing there.
 *
 * @module     enrol_apply/queue/SheetMethods
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const h=5,R=e=>{const s=[];return e.forEach(t=>{const a=s.find(r=>r.id===t.method.id);a?a.rows.push(t):s.push({id:t.method.id,rows:[t]})}),s},w=({labels:e,rows:s})=>{const t=s.length-h;return l("ul",{className:"enrol_apply-qview-sheetwho",children:[s.slice(0,h).map(a=>l("li",{children:[o("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:a.applicant.picture}}),o("span",{children:a.applicant.fullname})]},a.id)),t>0?o("li",{className:"enrol_apply-qview-sheetmore",children:m(e.andmore,p(t))}):null]})},v=({labels:e,id:s,group:t,approval:a,message:r,onMessage:d})=>{const n=t.rows[0],i=t.rows.length;return l("section",{className:"enrol_apply-qview-sheetmethod","aria-labelledby":`${s}-head`,children:[l("div",{className:"enrol_apply-qview-sheetmethodhead",id:`${s}-head`,children:[o("strong",{children:n.course.name}),o("span",{children:" \xB7 "+n.method.name})," ",o(g,{labels:e,row:n})]}),o("div",{className:"enrol_apply-qview-sheetline",children:i===1?e.count_one:m(e.count,p(i))}),a&&n.method.full?o("div",{className:"alert alert-warning",children:e.methodfullwarn}):null,o(w,{labels:e,rows:t.rows}),o("label",{className:"form-label",htmlFor:`${s}-message`,children:e.sheetmessage}),o("textarea",{className:"form-control",id:`${s}-message`,rows:2,value:r,"aria-describedby":`${s}-head`,onChange:u=>d(t.id,u.target.value)}),n.method.queueurl!==""?l("p",{className:"enrol_apply-qview-sheetopen",children:[o("a",{href:n.method.queueurl,children:e.openmethod}),a?l(c,{children:[" ",o("span",{className:"enrol_apply-qview-sheetline",children:e.openmethodline})]}):null]}):null]})},M=({labels:e,id:s,groups:t,approval:a,messages:r,onMessage:d})=>l(c,{children:[o("div",{className:"alert alert-info",children:m(e.spans,p(t.length))}),t.map((n,i)=>o(v,{labels:e,id:`${s}-method${i}`,group:n,approval:a,message:r[n.id]??"",onMessage:d},n.id))]});export{M as SheetMethods,w as WhoList,R as byMethod};
