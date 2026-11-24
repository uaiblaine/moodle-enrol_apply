import{fill as p,num as m}from"../datareport/str";import{MethodTags as u}from"./cells";import{Fragment as v,jsx as t,jsxs as r}from"react/jsx-runtime";/**
 * The decision sheet's lists of who is decided: the whole selection's, and, when a selection on the
 * site-wide queue spans enrolment methods, one section per method (SQ-D10, the table's sheet in
 * amd/src/manage.js, methodSection()).
 *
 * A section names the course and the method (text), the method's labels (it grants a deciding role, it is
 * at its limit, with the warning for an approval there), how many of the selection are its and who, and a
 * message box for them, posted as outcomemessages[<the method's id>]; the id is the one the rows service
 * gave the row, and the server groups the posted rows by the method the database names whatever the key
 * says. Each section links to the method's own queue where the reader may open it, the one place groups
 * and a role are chosen.
 *
 * @module     enrol_apply/queue/SheetMethods
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const h=5,R=e=>{const s=[];return e.forEach(o=>{const a=s.find(l=>l.id===o.method.id);a?a.rows.push(o):s.push({id:o.method.id,rows:[o]})}),s},g=({labels:e,rows:s})=>{const o=s.length-h;return r("ul",{className:"enrol_apply-qview-sheetwho",children:[s.slice(0,h).map(a=>r("li",{children:[t("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:a.applicant.picture}}),t("span",{children:a.applicant.fullname})]},a.id)),o>0?t("li",{className:"enrol_apply-qview-sheetmore",children:p(e.andmore,m(o))}):null]})},w=({labels:e,id:s,group:o,approval:a,message:l,onMessage:d})=>{const n=o.rows[0],i=o.rows.length;return r("section",{className:"enrol_apply-qview-sheetmethod","aria-labelledby":`${s}-head`,children:[r("div",{className:"enrol_apply-qview-sheetmethodhead",id:`${s}-head`,children:[t("strong",{children:n.course.name}),t("span",{children:" \xB7 "+n.method.name})," ",t(u,{labels:e,row:n})]}),t("div",{className:"enrol_apply-qview-sheetline",children:i===1?e.count_one:p(e.count,m(i))}),a&&n.method.full?t("div",{className:"alert alert-warning",children:e.methodfullwarn}):null,t(g,{labels:e,rows:o.rows}),t("label",{className:"form-label",htmlFor:`${s}-message`,children:e.sheetmessage}),t("textarea",{className:"form-control",id:`${s}-message`,rows:2,value:l,"aria-describedby":`${s}-head`,onChange:c=>d(o.id,c.target.value)}),n.method.queueurl!==""?r("p",{className:"enrol_apply-qview-sheetopen",children:[t("a",{href:n.method.queueurl,children:e.openmethod})," ",t("span",{className:"enrol_apply-qview-sheetline",children:e.openmethodline})]}):null]})},M=({labels:e,id:s,groups:o,approval:a,messages:l,onMessage:d})=>r(v,{children:[t("div",{className:"alert alert-info",children:p(e.spans,m(o.length))}),o.map((n,i)=>t(w,{labels:e,id:`${s}-method${i}`,group:n,approval:a,message:l[n.id]??"",onMessage:d},n.id))]});export{M as SheetMethods,g as WhoList,R as byMethod};
