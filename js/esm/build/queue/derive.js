import{fill as f}from"../datareport/str";/**
 * What the queue view reads off the newest rows answer, and the selection it builds over the page's rows.
 *
 * Both are plain functions of the answer and the props: the view keeps the state, this keeps the reading,
 * so the component stays small enough to follow.
 *
 * @module     enrol_apply/queue/derive
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const w=(e,l)=>{const r=l.decision!==null&&e!==null&&e.candecide;return{decides:r,notingroup:!!e?.notingroup,narrowed:!!e?.narrowed,viewonly:e!==null&&!r,groupdefault:e?.groupdefault??"",allcolumns:e?.columns??[],usedefault:e?.preference.usedefault??!0,presets:e?.presets??[],total:e?.total??0,list:(e?.rows??[]).map(n=>({...n,id:n.userenrolmentid}))}},y=(e,l,r,n)=>{const{list:i,decides:d}=r,a=new Set(i.filter(o=>!o.selectable).map(o=>o.id)),u=i.filter(o=>e.has(o.id)),c=(o,t)=>l(b=>{const s=new Set(b);return t&&!a.has(o)?s.add(o):s.delete(o),s}),p=o=>l(()=>o?new Set(i.filter(t=>!a.has(t.id)).map(t=>t.id)):new Set),m=d?{selected:e,locked:a,label:o=>f(n.selectapplicant,o.applicant.fullname),pagelabel:n.selectpage,onSelect:c,onSelectPage:p}:void 0;return{locked:a,chosen:u,selection:m}};export{y as chooseRows,w as derive};
