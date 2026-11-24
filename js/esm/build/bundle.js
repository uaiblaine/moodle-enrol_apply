import{useCallback as Rt,useEffect as it,useRef as le,useState as ue}from"react";import{useEffect as uo,useState as po}from"react";var mo=576,Lt=e=>{let[t,n]=po(!1);return uo(()=>{let r=e.current;if(!r||typeof ResizeObserver>"u")return;let o=new ResizeObserver(l=>{let a=l[0]?.contentRect.width??r.clientWidth;n(a>0&&a<mo)});return o.observe(r),()=>o.disconnect()},[e]),t},Dt=e=>{try{let t=window.localStorage.getItem(e);return t==="table"||t==="cards"?t:"auto"}catch{return"auto"}},At=(e,t)=>{try{window.localStorage.setItem(e,t)}catch{}};import{useId as wo}from"react";var b=(e,t)=>(e||"").split("{$a}").join(t),ne=(e,t)=>Object.entries(t).reduce((n,[r,o])=>n.split(`{$a->${r}}`).join(o),e||""),Ln=(e,t,n,r)=>b(n===1&&e[`${t}_one`]?e[`${t}_one`]:e[t],r),f=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(t).format(e)}catch{return String(e)}},Le=(e,t)=>{let n=Math.max(0,t);if(n<3600)return b(e.waitminutes,f(Math.max(1,Math.round(n/60))));if(n<86400)return b(e.waithours,f(Math.round(n/3600)));let r=Math.floor(n/86400),o=Math.round((n-r*86400)/3600);return o===24&&(r++,o=0),o>0&&r<10?ne(e.waitdayshours,{days:f(r),hours:f(o)}):b(e.waitdays,f(r))},Tt=e=>{let t=document.createElement("template");return t.innerHTML=e,(t.content.textContent||"").replace(/\s+/g," ").trim()};import{jsx as De,jsxs as nn}from"react/jsx-runtime";var go=(e,t)=>[...new Set([1,e-1,e,e+1,t])].filter(n=>n>=1&&n<=t).sort((n,r)=>n-r),et=({labels:e,page:t,perpage:n,perpages:r,total:o,shown:l,narrow:a,onPage:p,onPerpage:c})=>{let i=wo(),g=Math.max(1,Math.ceil(o/n)),y=t+1,u=o>0?t*n+1:0,R=o>0?u+l-1:0,d=[];if(a)d=[De("span",{className:"enrol_apply-rview-pager-gap",children:ne(e.pageof,{page:f(y),pages:f(g)})},"of")];else{let _=go(y,g);_.forEach((C,v)=>{v>0&&C-_[v-1]>1&&d.push(De("span",{className:"enrol_apply-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${C}`)),d.push(De("button",{type:"button","aria-current":C===y?"page":void 0,onClick:()=>p(C-1),children:f(C)},C))})}return nn("div",{className:"enrol_apply-rview-foot",children:[De("span",{className:"enrol_apply-rview-num","data-region":"enrol_apply-range",children:ne(e.range,{from:f(u),to:f(R),total:f(o)})}),g>1?nn("nav",{className:"enrol_apply-rview-pager","aria-label":e.pages,children:[De("button",{type:"button",disabled:y===1,onClick:()=>p(t-1),children:e.previous}),d,De("button",{type:"button",disabled:y===g,onClick:()=>p(t+1),children:e.next})]}):null,nn("span",{className:"enrol_apply-rview-perpage",children:[De("label",{htmlFor:i,children:e.perpage}),De("select",{id:i,className:"form-select form-select-sm",value:n,onChange:_=>c(Number(_.target.value)),children:r.map(_=>De("option",{value:_,children:f(_)},_))})]})]})};var re=e=>{let t=window.M?.util;t?.js_pending(e);let n=!0;return()=>{n&&(n=!1,t?.js_complete(e))}};import{useState as bo}from"react";import{useEffect as ho,useRef as Ft}from"react";var tt=(e,t,n,r,o)=>({defaultWidth:l=>e[l]??t,minimumWidth:l=>n[l]??r,maximum:o}),fe=(e,t,n)=>Math.min(e.maximum,Math.max(e.minimumWidth(n),t[n]??e.defaultWidth(n)));import{jsx as P,jsxs as Ie}from"react/jsx-runtime";var Vt=16,fo={ascending:"\u25B2",descending:"\u25BC",none:""},vo=(e,t)=>e?t==="asc"?"ascending":"descending":"none",yo=({selection:e,rows:t})=>{let n=Ft(null),r=t.filter(a=>!e.locked.has(a.id)),o=r.filter(a=>e.selected.has(a.id)).length,l=r.length>0&&o===r.length;return ho(()=>{n.current&&(n.current.indeterminate=o>0&&!l)},[o,l]),Ie("label",{className:"enrol_apply-rview-pickwrap",children:[P("input",{ref:n,type:"checkbox",className:"enrol_apply-rview-pick",checked:l,disabled:r.length===0,onChange:a=>e.onSelectPage(a.target.checked)}),P("span",{className:"visually-hidden",children:e.pagelabel})]})},rn=({selection:e,row:t})=>e.locked.has(t.id)?P("span",{className:"enrol_apply-rview-pickwrap enrol_apply-rview-pick--none"}):Ie("label",{className:"enrol_apply-rview-pickwrap",children:[P("input",{type:"checkbox",className:"enrol_apply-rview-pick",checked:e.selected.has(t.id),onChange:n=>e.onSelect(t.id,n.target.checked)}),P("span",{className:"visually-hidden",children:e.label(t)})]}),nt=({labels:e,columns:t,lead:n,rows:r,widths:o,sizing:l,sort:a,direction:p,selection:c,renderLead:i,renderCell:g,onSort:y,onWidth:u,onWidthCommit:R})=>{let d=Ft(null),_=Ft(null),C=Ft(null),v=t.reduce((s,h)=>s+fe(l,o,h.key),0),B=s=>{s.currentTarget.classList.toggle("is-scrolled",s.currentTarget.scrollLeft>0)},z=(s,h)=>{let T=_.current?.querySelector(`col[data-col="${CSS.escape(s)}"]`);!T||!_.current||(T.style.width=`${h}px`,_.current.style.width=`${v-fe(l,o,s)+h}px`)},L=(s,h)=>Math.round(Math.min(l.maximum,Math.max(l.minimumWidth(s),h))),he=(s,h)=>{if(s.button!==0)return;s.preventDefault(),s.currentTarget.setPointerCapture(s.pointerId),s.currentTarget.classList.add("is-active");let T=fe(l,o,h);C.current={key:h,x:s.clientX,width:T,now:T}},Z=s=>{let h=C.current;if(!h)return;let T=document.documentElement.dir==="rtl",F=(s.clientX-h.x)*(T?-1:1),q=L(h.key,h.width+F);q!==h.now&&(z(h.key,q),h.now=q)},K=s=>{let h=C.current;C.current=null,s.currentTarget.classList.remove("is-active"),h&&h.now!==h.width&&u(h.key,h.now)},de=(s,h)=>{let T=document.documentElement.dir==="rtl",F=0;if(s.key==="ArrowRight")F=T?-Vt:Vt;else if(s.key==="ArrowLeft")F=T?Vt:-Vt;else if(s.key==="Home"){s.preventDefault(),u(h,null,!0);return}if(F===0)return;s.preventDefault();let q=L(h,fe(l,o,h)+F);q!==fe(l,o,h)&&u(h,q,!0)};return P("div",{className:"enrol_apply-rview-gridframe",ref:d,tabIndex:0,role:"region","aria-label":e.grid,onScroll:B,children:Ie("table",{className:"enrol_apply-rview-grid",ref:_,style:{width:`${v}px`},children:[P("caption",{className:"visually-hidden",children:e.caption}),P("colgroup",{children:t.map(s=>P("col",{"data-col":s.key,style:{width:`${fe(l,o,s.key)}px`}},s.key))}),P("thead",{children:P("tr",{children:t.map(s=>{let h=vo(a===s.key,p),T=P("span",{className:"enrol_apply-rview-th-title",children:s.title}),F=s.sortable?Ie("button",{type:"button",className:"enrol_apply-rview-sortbtn",onClick:()=>y(s.key),children:[T,P("span",{className:"enrol_apply-rview-arrow","aria-hidden":"true",children:fo[h]})]}):P("span",{className:"enrol_apply-rview-plain",children:T});return Ie("th",{scope:"col",className:s.key===n?"enrol_apply-rview-fixed":void 0,"aria-sort":s.sortable?h:void 0,title:s.title,"data-column":s.key,children:[c&&s.key===n?Ie("div",{className:"enrol_apply-rview-lead",children:[P(yo,{selection:c,rows:r}),F]}):F,P("span",{className:"enrol_apply-rview-resize",role:"separator","aria-orientation":"vertical","aria-label":b(e.resize,s.title),"aria-valuemin":l.minimumWidth(s.key),"aria-valuemax":l.maximum,"aria-valuenow":fe(l,o,s.key),tabIndex:0,onPointerDown:q=>he(q,s.key),onPointerMove:Z,onPointerUp:K,onPointerCancel:K,onDoubleClick:()=>{fe(l,o,s.key)!==l.defaultWidth(s.key)&&u(s.key,null)},onKeyDown:q=>de(q,s.key),onBlur:R})]},s.key)})})}),P("tbody",{children:r.map(s=>P("tr",{"data-record":s.id,className:c?.selected.has(s.id)?"enrol_apply-rview-row--selected":void 0,children:t.map(h=>h.key===n?P("th",{scope:"row",className:"enrol_apply-rview-fixed",children:c?Ie("div",{className:"enrol_apply-rview-lead",children:[P(rn,{selection:c,row:s}),i(s,h)]}):i(s,h)},h.key):P("td",{children:g(s,h)},h.key))},s.id))})]})})},Dn=({columns:e,lead:t,widths:n,sizing:r})=>{let o=e.reduce((a,p)=>a+fe(r,n,p.key),0),l=["enrol_apply-rview-skb--w80","enrol_apply-rview-skb--w60","enrol_apply-rview-skb--w40"];return P("div",{className:"enrol_apply-rview-gridframe","aria-hidden":"true",children:Ie("table",{className:"enrol_apply-rview-grid",style:{width:`${o}px`},children:[P("colgroup",{children:e.map(a=>P("col",{style:{width:`${fe(r,n,a.key)}px`}},a.key))}),P("thead",{children:P("tr",{children:e.map(a=>P("th",{className:a.key===t?"enrol_apply-rview-fixed":void 0,children:P("span",{className:"enrol_apply-rview-plain",children:P("span",{className:"enrol_apply-rview-th-title",children:a.title})})},a.key))})}),P("tbody",{children:Array.from({length:8},(a,p)=>P("tr",{className:"enrol_apply-rview-sk",children:e.map((c,i)=>P("td",{className:c.key===t?"enrol_apply-rview-fixed":void 0,children:P("span",{className:"enrol_apply-rview-skb "+l[(p+i)%3]})},c.key))},p))})]})})};import{jsx as ge,jsxs as wt}from"react/jsx-runtime";var Ro=({labels:e,row:t,single:n,selection:r,renderCard:o})=>{let[l,a]=bo(!1),p=o(t),c=!n||l,i=r?.selected.has(t.id)??!1;return wt("article",{className:"enrol_apply-rview-card"+(i?" enrol_apply-rview-card--selected":""),role:"listitem","data-record":t.id,children:[wt("div",{className:"enrol_apply-rview-card-l1",children:[r?ge(rn,{selection:r,row:t}):null,p.head]}),p.lines,p.fields.length>0?wt("div",{className:"enrol_apply-rview-card-more",children:[n?ge("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":l,onClick:()=>a(!l),children:l?e.less:e.more}):null,c?ge("dl",{className:"enrol_apply-rview-card-list",children:p.fields.map(g=>wt("div",{className:"enrol_apply-rview-card-item",children:[ge("dt",{children:g.title}),ge("dd",{children:g.content})]},g.key))}):null]}):null]})},rt=({labels:e,rows:t,single:n,selection:r,renderCard:o})=>ge("div",{className:"enrol_apply-rview-cards"+(n?" enrol_apply-rview-cards--single":""),role:"list",children:t.map(l=>ge(Ro,{labels:e,row:l,single:n,selection:r,renderCard:o},l.id))}),An=({single:e})=>ge("div",{className:"enrol_apply-rview-cards"+(e?" enrol_apply-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:e?4:6},(t,n)=>wt("div",{className:"enrol_apply-rview-card enrol_apply-rview-sk",children:[ge("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),ge("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"}),ge("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),ge("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"})]},n))});import{Fragment as Tn,jsx as Ae,jsxs as ot}from"react/jsx-runtime";var _o=({labels:e,loaded:t,error:n,total:r,filtered:o,cards:l,narrow:a,skeleton:p,children:c,onRetry:i})=>{let g=ot("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",Ae("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:i,children:e.retry})]});return!t&&n?g:t?r===0?ot("div",{className:"enrol_apply-rview-empty",children:[Ae("p",{className:"enrol_apply-rview-empty-title",children:o?e.nomatch:e.empty}),o?Ae("p",{className:"enrol_apply-rview-empty-text",children:e.nomatchhelp}):null]}):ot("div",{className:"enrol_apply-rview-dim",children:[n?g:null,c,l?null:Ae("p",{className:"enrol_apply-rview-hint",children:e.scrollhint})]}):ot(Tn,{children:[l?Ae(An,{single:a}):Ae(Dn,{...p}),Ae("span",{className:"visually-hidden",role:"status",children:e.loading})]})},at=e=>ot(Tn,{children:[ot("section",{className:"enrol_apply-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"enrol_apply-rows",children:[e.loading&&e.loaded?Ae("div",{className:"enrol_apply-rview-progress"}):null,Ae(_o,{...e})]}),e.dock??null]});var No="table",ve="methods",on="enrolApplyQueue",ko=e=>e.sort===""?!0:e.show===ve?e.sort==="awaiting"&&e.direction==="desc":e.sort==="wait"&&e.direction==="asc",So=(e,t,n)=>{let r=[];return t.scope!=="all"&&r.push(["status",String(t.scope==="pending"?e.statuses.pending:e.statuses.deferred)]),t.search!==""&&r.push(["search",t.search]),Object.keys(t.filters).sort().forEach(o=>{t.filters[o]!==""&&r.push([o,t.filters[o]])}),n?(t.show===ve&&r.push(["show",ve]),ko(t)||r.push(["sort",t.sort],["dir",t.direction]),t.page>0&&r.push(["page",String(t.page)]),t.perpage!==e.perpage&&r.push(["perpage",String(t.perpage)])):r.push(["view",No]),r},gt=(e,t,n)=>{let r=new URL(e.pageurl,window.location.href);return So(e,t,n).forEach(([o,l])=>r.searchParams.set(o,l)),r.toString()},Vn=e=>({scope:e.initial.scope,search:e.initial.search,filters:Object.fromEntries(e.initial.filters.map(t=>[t.name,t.value])),sort:e.initial.sort,direction:e.initial.direction,page:e.initial.page,perpage:e.initial.perpage,show:e.methods!==null?e.initial.show:""}),It=(e,t,n)=>{let r=gt(e,t,!0),o={[on]:t};try{n&&r!==window.location.href?window.history.pushState(o,"",r):window.history.replaceState(o,"",r)}catch{}document.querySelectorAll('[data-enrol-apply-queue="tablelink"]').forEach(l=>{l.href=gt(e,t,!1)})},Fn=e=>{if(e===null||typeof e!="object"||!(on in e))return null;let t=e[on];return{...t,show:t.show??""}};import{useEffect as Ot,useId as To,useRef as ft,useState as Kn}from"react";import{useId as Eo,useState as Hn}from"react";import{jsx as In}from"react/jsx-runtime";var Co={filter:"M2 3h12l-4.5 5.5V13l-3 1.5V8.5L2 3z",columns:"M2 3h12v10H2zM6.5 3v10M10 3v10",table:"M2 3h12v10H2zM2 6.5h12M2 10h12M6 6.5V13",cards:"M2 3h5v4H2zM9 3h5v4H9zM2 9h5v4H2zM9 9h5v4H9z",auto:"M2 3h7v10H2zM11 3h3v4h-3zM11 9h3v4h-3z",download:"M8 2v8M4.5 7 8 10.5 11.5 7M3 13h10",search:"M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM10.5 10.5 14 14",close:"M4 4l8 8M12 4l-8 8",eraser:"M9.5 2 14 6.5 7.5 13 3 8.5zM5.6 5.9l4.5 4.5M7.5 13H14",tick:"M3 8.5 6.5 12 13 4.5",chevron:"M4 6l4 4 4-4",reset:"M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5",queue:"M3 4h10M3 8h10M3 12h6"},H=({name:e,className:t})=>In("svg",{className:"enrol_apply-rview-icon"+(t?" "+t:""),viewBox:"0 0 16 16",width:"16",height:"16",fill:"none",stroke:"currentColor",strokeWidth:"1.5",strokeLinecap:"round",strokeLinejoin:"round","aria-hidden":"true",focusable:"false",children:In("path",{d:Co[e]??""})});import{Fragment as Ao,jsx as W,jsxs as ye}from"react/jsx-runtime";var Mo=8,an=50,xo=(e,t)=>t===null?e.show:b(e.showcount,f(t)),Wn=({option:e})=>e.count>=0?W("small",{className:"enrol_apply-rview-optcount",children:f(e.count)}):null,Po=(e,t)=>e.options.filter(n=>n.count!==0||n.value===t),Lo=({labels:e,facet:t,shown:n,value:r,headid:o,onChange:l})=>{let[a,p]=Hn(""),[c,i]=Hn(""),g=a.trim().toLocaleLowerCase(),y=t.groupings.length>1?t.groupings:[],u=y.find(v=>String(v.id)===c)?.groups.map(String)??null,R=n.filter(v=>v.value===r),d=n.filter(v=>v.value!==r&&(g===""||v.label.toLocaleLowerCase().includes(g))&&(u===null||u.includes(v.value))),_=b(t.find.label,f(t.options.length)),C=y.length>0?e.moregroupsgrouping:e.moregroups;return ye(Ao,{children:[ye("div",{className:"enrol_apply-rview-find",children:[W("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:_,"aria-label":_,value:a,onChange:v=>p(v.target.value)}),y.length>0?ye("select",{className:"form-select form-select-sm enrol_apply-rview-grouping","aria-label":e.grouping,value:c,onChange:v=>i(v.target.value),children:[W("option",{value:"",children:e.allgroupings}),y.map(v=>W("option",{value:String(v.id),children:v.name},v.id))]}):null]}),ye("div",{className:"enrol_apply-rview-optlist",role:"group","aria-labelledby":o,children:[[...R,...d.slice(0,an)].map(v=>ye("button",{type:"button",className:"enrol_apply-rview-optrow","aria-pressed":v.value===r,onClick:()=>l(t.name,v.value===r?"":v.value),children:[W("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:W(H,{name:"tick"})}),W("span",{className:"enrol_apply-rview-optrow-label",children:v.label})," ",W(Wn,{option:v})]},v.value)),d.length>an?W("p",{className:"enrol_apply-rview-note",children:b(C,f(d.length-an))}):null,R.length===0&&d.length===0?W("p",{className:"enrol_apply-rview-note",children:t.find.nomatch}):null]})]})},On=({note:e})=>e?W("p",{className:"enrol_apply-rview-note",children:e}):null,Do=({labels:e,facet:t,value:n,onChange:r})=>{let o=Eo(),l=Po(t,n),a=[{value:"",label:e.any,count:-1},...l];if(t.options.length>Mo)return ye("div",{className:"enrol_apply-rview-fgroup",children:[W("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),W(Lo,{labels:e,facet:t,shown:l,value:n,headid:o,onChange:r}),W(On,{note:t.note})]});let p=c=>{let i=a.findIndex(y=>y.value===n),g=-1;c.key==="ArrowRight"||c.key==="ArrowDown"?g=(i+1)%a.length:c.key==="ArrowLeft"||c.key==="ArrowUp"?g=(i-1+a.length)%a.length:c.key==="Home"?g=0:c.key==="End"&&(g=a.length-1),!(g<0)&&(c.preventDefault(),r(t.name,a[g].value),c.currentTarget.querySelectorAll('[role="radio"]')[g]?.focus())};return ye("div",{className:"enrol_apply-rview-fgroup",children:[W("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),W("div",{className:"enrol_apply-rview-fpanel-opts",role:"radiogroup","aria-labelledby":o,onKeyDown:p,children:a.map(c=>ye("button",{type:"button",className:"enrol_apply-rview-fpanel-opt",role:"radio","aria-checked":c.value===n,tabIndex:c.value===n?0:-1,onClick:()=>r(t.name,c.value),children:[c.label," ",W(Wn,{option:c})]},c.value))}),W(On,{note:t.note})]})},Bn=({labels:e,id:t,hidden:n,facets:r,values:o,total:l,note:a,onChange:p,onClear:c,onShow:i})=>ye("div",{className:"enrol_apply-rview-fpanel",id:t,role:"dialog","aria-label":e.filters,hidden:n,children:[ye("div",{className:"enrol_apply-rview-fpanel-body",children:[r.map(g=>W(Do,{labels:e,facet:g,value:o[g.name]??"",onChange:p},g.name)),a??null]}),ye("div",{className:"enrol_apply-rview-fpanel-foot",children:[W("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:c,children:e.clearfilters}),W("button",{type:"button",className:"btn btn-sm btn-primary",onClick:i,children:xo(e,l)})]})]});import{Fragment as ln,jsx as J,jsxs as He}from"react/jsx-runtime";var Ht=e=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(e.key))return;let t=Array.from(e.currentTarget.querySelectorAll('[role^="menuitem"]'));if(t.length===0)return;e.preventDefault();let n=t.indexOf(document.activeElement),r=0;e.key==="End"?r=t.length-1:e.key==="ArrowDown"?r=(n+1)%t.length:e.key==="ArrowUp"&&(r=(n-1+t.length)%t.length),t[r].focus()},qn=({heading:e,sets:t,onSet:n})=>t.length===0?null:He(ln,{children:[J("div",{className:"enrol_apply-rview-menu-head",role:"presentation",children:e}),t.map(r=>He("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":r.active,tabIndex:-1,"data-preset":r.name,onClick:()=>{r.active||n(r.name)},children:[J("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:J(H,{name:"tick"})}),J("span",{className:"enrol_apply-rview-menu-label",children:r.label})]},r.name)),J("div",{className:"enrol_apply-rview-menu-sep",role:"separator"})]}),$n=(e,t=[])=>t.find(n=>n.value===e)?.icon??{auto:"auto",table:"table",cards:"cards"}[e],Qn=e=>{let{labels:t}=e,n=e.extraViews??[],r=[["auto",t.viewauto,t.viewautohint],["table",t.table,""],["cards",t.cards,""]].filter(([o])=>e.views.includes(o));return He("div",{className:"enrol_apply-rview-menu",id:e.id,role:"menu","aria-label":t.view,hidden:e.hidden,onKeyDown:Ht,children:[r.map(([o,l,a])=>He("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===o,tabIndex:-1,onClick:()=>e.onChoose(o),children:[J(H,{name:o}),J("span",{className:"enrol_apply-rview-menu-label",children:l}),a!==""?J("span",{className:"enrol_apply-rview-menu-hint",children:a}):null,J(H,{name:"tick",className:"enrol_apply-rview-tick"})]},o)),n.length>0?He(ln,{children:[J("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),n.map(o=>He("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===o.value,tabIndex:-1,onClick:()=>e.onChoose(o.value),children:[J(H,{name:o.icon}),J("span",{className:"enrol_apply-rview-menu-label",children:o.label}),J(H,{name:"tick",className:"enrol_apply-rview-tick"})]},o.value))]}):null,e.tableurl!==""?He(ln,{children:[J("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),He("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:e.tableurl,children:[J(H,{name:"table"}),J("span",{className:"enrol_apply-rview-menu-label",children:t.coretable})]})]}):null]})};import{jsx as Un}from"react/jsx-runtime";var ht=({label:e,value:t,options:n,small:r,onChange:o})=>{let l=a=>{let p=n.findIndex(g=>g.value===t),c=-1;if(a.key==="ArrowRight"||a.key==="ArrowDown"?c=(p+1)%n.length:a.key==="ArrowLeft"||a.key==="ArrowUp"?c=(p-1+n.length)%n.length:a.key==="Home"?c=0:a.key==="End"&&(c=n.length-1),c<0)return;a.preventDefault(),o(n[c].value),a.currentTarget.querySelectorAll('[role="radio"]')[c]?.focus()};return Un("div",{className:"enrol_apply-rview-seg"+(r?" enrol_apply-rview-seg--small":""),role:"radiogroup","aria-label":e,onKeyDown:l,children:n.map(a=>Un("button",{type:"button",className:"enrol_apply-rview-seg-cell",role:"radio","aria-checked":a.value===t,tabIndex:a.value===t?0:-1,onClick:()=>o(a.value),children:a.label},a.value))})};import{Fragment as Io,jsx as k,jsxs as Q}from"react/jsx-runtime";var Vo=(e,t)=>"enrol_apply-rview-bar"+(e?" enrol_apply-rview-bar--narrow":"")+(t?" enrol_apply-rview-bar--searching":""),Fo=({labels:e,countkey:t="count",total:n,counted:r,narrow:o})=>{let l=r===void 0?n:r,a=l===null?"\u2026":Ln(e,t,l,f(l));return k("span",{className:"enrol_apply-rview-bar-count","data-region":"enrol_apply-total","aria-live":"polite",children:l!==null&&o?Q(Io,{children:[k("span",{"aria-hidden":"true",children:f(l)}),k("span",{className:"visually-hidden",children:a})]}):a})},Wt=e=>{let{labels:t,chips:n,columns:r}=e,o=To(),l=ft(null),a=ft(null),p=ft(null),c=ft(!1),[i,g]=Kn(null),[y,u]=Kn(e.search!==""),R=ft({});Ot(()=>{if(i===null)return;let s=h=>{l.current&&!l.current.contains(h.target)&&g(null)};return document.addEventListener("mousedown",s),()=>document.removeEventListener("mousedown",s)},[i]),Ot(()=>{if(i===null||i==="filters")return;let s=l.current?.querySelector(`#${CSS.escape(o+i)}`);(s?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])')??s?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])'))?.focus()},[i,o]),Ot(()=>{y?a.current?.focus():c.current&&(c.current=!1,p.current?.focus())},[y]),Ot(()=>{let s=h=>{if(h.key!=="/"||h.ctrlKey||h.metaKey||h.altKey||h.defaultPrevented)return;let T=document.activeElement;if(T&&(T.isContentEditable||T.closest("input, textarea, select")))return;let F=l.current?.getBoundingClientRect();!F||F.width===0||F.bottom<=0||F.top>=window.innerHeight||(h.preventDefault(),y?a.current?.focus():(g(null),u(!0)))};return document.addEventListener("keydown",s),()=>document.removeEventListener("keydown",s)},[y]);let d=s=>g(i===s?null:s),_=s=>{c.current=s,u(!1)},C=s=>{if(!(s.key!=="Escape"||s.defaultPrevented))if(i!==null){s.preventDefault();let h=R.current[i];g(null),h?.focus()}else y&&(s.preventDefault(),_(!0))},v=e.search.trim().length>=e.searchmin,B=v&&!y,z=n.length+(B?1:0),L=(s,h,T,F)=>Q("button",{type:"button",className:"enrol_apply-rview-iconbtn","aria-label":T,"data-enrol-apply-tip":T,"aria-haspopup":s==="filters"?"dialog":"menu","aria-expanded":i===s,"aria-controls":o+s,ref:q=>{R.current[s??""]=q},onClick:()=>d(s),children:[k(H,{name:h}),F]}),he=$n(e.view,e.extraViews),Z=k(H,{name:"chevron",className:"enrol_apply-rview-chevron"}),K=n.length>0?b(t.filterson,f(n.length)):t.filters,de=n.length>0?k("span",{className:"enrol_apply-rview-badge","aria-hidden":"true",children:f(n.length)}):null;return Q("div",{className:Vo(e.narrow,y),"data-region":"enrol_apply-bar",ref:l,onKeyDown:C,children:[k(ht,{label:t.scope,value:e.scope,options:e.scopes,onChange:e.onScope}),k("span",{className:"enrol_apply-rview-bar-spacer"}),Q("div",{className:"enrol_apply-rview-bar-controls",children:[Q("div",{className:"enrol_apply-rview-bar-cluster",children:[k(Fo,{labels:t,countkey:e.countkey,total:e.total,counted:e.counted,narrow:e.narrow}),Q("div",{className:"enrol_apply-rview-capsule",children:[L("filters","filter",K,de),Q("div",{className:"enrol_apply-rview-anchor",hidden:e.hidecolumns,children:[L("columns","columns",t.columns,Z),Q("div",{className:"enrol_apply-rview-menu",id:o+"columns",role:"menu","aria-label":t.columns,hidden:i!=="columns",onKeyDown:Ht,children:[k(qn,{heading:t.presets,sets:e.sets,onSet:e.onSet}),r.map(s=>s.fixed?Q("div",{className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":"true","aria-disabled":"true",tabIndex:-1,children:[k("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:k(H,{name:"tick"})}),k("span",{className:"enrol_apply-rview-menu-label",children:s.title}),k("span",{className:"enrol_apply-rview-menu-hint",children:t.always})]},s.key):Q("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":s.active,tabIndex:-1,onClick:()=>e.onColumn(s.key,!s.active),children:[k("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:k(H,{name:"tick"})}),k("span",{className:"enrol_apply-rview-menu-label",children:s.title})]},s.key)),k("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),Q("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":e.usedefault,onClick:()=>{e.usedefault||e.onDefaultColumns()},children:[k(H,{name:"columns"}),k("span",{className:"enrol_apply-rview-menu-label",children:t.defaultcolumns})]}),Q("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":!e.haswidths,onClick:()=>{e.haswidths&&e.onResetWidths()},children:[k(H,{name:"reset"}),k("span",{className:"enrol_apply-rview-menu-label",children:t.resetwidths})]})]})]}),Q("div",{className:"enrol_apply-rview-anchor",children:[L("view",he,t.view,Z),k(Qn,{id:o+"view",labels:t,hidden:i!=="view",view:e.view,views:e.views,extraViews:e.extraViews,tableurl:e.tableurl,onChoose:s=>{e.onView(s),g(null),R.current.view?.focus()}})]}),e.downloads.length>0?Q("div",{className:"enrol_apply-rview-anchor",children:[L("download","download",t.download,Z),k("div",{className:"enrol_apply-rview-menu",id:o+"download",role:"menu","aria-label":t.download,hidden:i!=="download",onKeyDown:Ht,children:e.downloads.map(s=>Q("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:s.href,children:[k(H,{name:"download"}),k("span",{className:"enrol_apply-rview-menu-label",children:s.label})]},s.key))})]}):null]})]}),k("div",{className:"enrol_apply-rview-capsule enrol_apply-rview-searchcap",children:y?Q("span",{className:"enrol_apply-rview-search",children:[e.searching&&v?k("span",{className:"enrol_apply-rview-spin","aria-hidden":"true"}):k(H,{name:"search"}),k("input",{ref:a,type:"search","aria-label":t.search,placeholder:t.search,autoComplete:"off",value:e.search,onChange:s=>e.onSearch(s.target.value),onKeyDown:s=>{s.key==="Escape"&&(s.preventDefault(),_(!0))},onBlur:()=>{e.search===""&&_(!1)}}),e.search!==""?k("button",{type:"button",className:"enrol_apply-rview-eraser","aria-label":t.cleartext,"data-enrol-apply-tip":t.cleartext,onMouseDown:s=>s.preventDefault(),onClick:()=>{e.onSearch(""),a.current?.focus()},children:k(H,{name:"eraser"})}):null]}):k("button",{ref:p,type:"button",className:"enrol_apply-rview-iconbtn","aria-label":t.search,"data-enrol-apply-tip":t.search,onClick:()=>{g(null),u(!0)},children:k(H,{name:"search"})})}),k(Bn,{labels:t,id:o+"filters",hidden:i!=="filters",facets:e.facets,values:e.values,total:e.total,note:e.panelnote,onChange:e.onFacet,onClear:e.onClearFilters,onShow:()=>{g(null),R.current.filters?.focus()}})]}),Q("div",{className:"enrol_apply-rview-chips","data-region":"enrol_apply-chips",hidden:z===0&&!e.aside,children:[n.map(s=>Q("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":b(t.removefilter,s.text),onClick:()=>e.onRemoveChip(s.key),children:[k("span",{children:s.text}),k(H,{name:"close"})]},s.key)),B?Q("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":b(t.removefilter,b(t.searchchip,e.search.trim())),onClick:()=>e.onSearch(""),children:[k("span",{children:b(t.searchchip,e.search.trim())}),k(H,{name:"close"})]}):null,z>1?k("button",{type:"button",className:"enrol_apply-rview-clearall",onClick:()=>{e.onClearAll(),u(!1)},children:t.clearall}):null,e.aside??null]})]})};import{jsx as sn,jsxs as $o}from"react/jsx-runtime";var vt="all",lt="group",Ho=["auto","table","cards"],Oo=(e,t)=>{switch(t){case"everything":return e.preseteverything;case"glance":return e.presetglance;case"answers":return e.presetanswers;default:return t}},Gn=(e,t)=>{switch(t){case"course":return{label:e.searchcourses,nomatch:e.nocoursematch};case"category":return{label:e.searchcategories,nomatch:e.nocategorymatch};default:return{label:e.searchgroups,nomatch:e.nogroupmatch}}},Wo=(e,t)=>t.omitted<=0?"":b(t.name==="category"?e.omittedcategories:e.omittedcourses,f(t.omitted)),Bo=(e,t)=>{let n=t.facets.map(l=>({name:l.name,title:l.title,options:l.options,groupings:[],find:Gn(e,l.name),note:Wo(e,l)})),r=Gn(e,""),o=t.fieldfilters.filter(l=>l.control==="select").map(l=>({name:l.name,title:l.title,options:l.options.map(a=>({...a,count:-1})),groupings:[],find:r}));return[...n,...o]},qo=(e,t,n,r)=>{let o=n,l=r,a=t.facets.find(c=>c.name===n),p=t.fieldfilters.find(c=>c.name===n);return a?(o=a.title,l=a.options.find(c=>c.value===r)?.label??r):p?(o=p.title,l=p.options.find(c=>c.value===r)?.label??r):n==="appliedfrom"?o=e.appliedfrom:n==="appliedto"&&(o=e.appliedto),{key:n,text:ne(e.filterchip,{title:o,value:l})}},zn=e=>{let{labels:t,rows:n,query:r}=e,o=e.methods!==void 0,l=e.presets.map(c=>({name:c.name,label:Oo(t,c.name),active:c.active})),a=Object.fromEntries(Object.entries(r.filters).map(([c,i])=>[c,c===lt&&i===vt?"":i])),p=n===null?[]:n.filters.map(c=>qo(t,n,c.name,c.value));return sn(Wt,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"pending",label:t.pending},{value:"deferred",label:t.deferred}],scope:r.scope,total:n===null?null:n.total,facets:n===null?[]:Bo(t,n),values:a,panelnote:$o("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",sn("a",{href:e.tableurl,children:t.morefilters})]}),chips:p,aside:e.reporturl!==""?sn("a",{className:"enrol_apply-qview-tolink",href:e.reporturl,children:t.openreport}):null,columns:e.columns,sets:l,usedefault:e.usedefault,haswidths:e.haswidths,view:o?r.show:e.view,views:Ho,extraViews:e.extraViews,hidecolumns:o,countkey:o?"methods":"count",counted:e.methods,tableurl:e.tableurl,downloads:[],search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:c=>e.onScope(c),onSearch:e.onSearch,onFacet:e.onFilter,onRemoveChip:c=>e.onFilter(c,""),onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:c=>{let i=e.presets.find(g=>g.name===c);i&&e.onPreset(i)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Qo,jsx as Yn}from"react/jsx-runtime";var jn=(e,t)=>t?e.split(`
`).map(n=>n.trim()).filter(n=>n!==""):[e],Bt=({parts:e})=>Yn(Qo,{children:e.map((t,n)=>Yn("span",{className:n===0?"enrol_apply-rview-c":"enrol_apply-rview-sub",title:Tt(t),dangerouslySetInnerHTML:{__html:t}},n))});import{Fragment as ta,jsx as O,jsxs as st}from"react/jsx-runtime";var Uo={applicant:320,course:240,wait:150,group:180,comment:260,details:260,history:130},Ko=180,Go={applicant:200},zo=80,Yo=800,qt=tt(Uo,Ko,Go,zo,Yo),jo={deferred:"enrol_apply-status--warning",key:"enrol_apply-status--info",appliedbefore:"enrol_apply-status--neutral",own:"enrol_apply-status--neutral",deciding:"enrol_apply-status--warning",full:"enrol_apply-status--neutral"},ze=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",cn=({html:e})=>O(Bt,{parts:[e]}),Zn=({tag:e})=>{let t=jo[e.kind]??"enrol_apply-status--neutral",n=e.title!==""&&e.title!==e.text;return st("span",{className:"enrol_apply-status "+t,title:n?e.title:void 0,children:[e.text,n?O("span",{className:"visually-hidden",children:" "+e.title}):null]})},Zo=({row:e})=>e.applicant.profileurl===""?O("span",{className:"enrol_apply-qview-name",children:e.applicant.fullname}):O("a",{className:"enrol_apply-qview-name",href:e.applicant.profileurl,children:e.applicant.fullname}),Xo=({labels:e,row:t})=>O("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:t.reviewurl,"aria-label":b(e.reviewapplicant,t.applicant.fullname),children:e.review}),Jo=({row:e})=>st(ta,{children:[O("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:e.applicant.picture}}),st("div",{className:"enrol_apply-qview-whotext",children:[O("span",{className:"enrol_apply-rview-c",children:O(Zo,{row:e})}),e.applicant.identity.map(t=>O("span",{className:"enrol_apply-rview-sub",title:t.value,children:t.value},t.name)),e.applicant.tags.length>0?O("span",{className:"enrol_apply-qview-tags",children:e.applicant.tags.map(t=>O(Zn,{tag:t},t.kind))}):null]})]}),dn=({labels:e,row:t})=>st("div",{className:"enrol_apply-qview-who",children:[O(Jo,{row:t}),O(Xo,{labels:e,row:t})]}),un=({labels:e,row:t})=>{let n=[];return t.method.deciding&&n.push({kind:"deciding",text:e.grantsdeciding,title:""}),t.method.full&&n.push({kind:"full",text:e.methodfull,title:e.methodfullwarn}),n.length===0?null:O("span",{className:"enrol_apply-qview-tags",children:n.map(r=>O(Zn,{tag:r},r.kind))})},ea=({row:e})=>e.method.queueurl===""?O("span",{children:e.method.name}):O("a",{href:e.method.queueurl,children:e.method.name}),yt=({labels:e,row:t})=>st("div",{className:"enrol_apply-qview-course",children:[O("span",{className:"enrol_apply-rview-c",title:t.course.name,children:O("a",{className:"enrol_apply-qview-coursename",href:t.course.url,children:t.course.name})}),st("div",{className:"enrol_apply-qview-methodline",children:[O("span",{className:"enrol_apply-rview-sub",title:t.method.name,children:O(ea,{row:t})}),O(un,{labels:e,row:t})]})]});var Oe=(e,t,n)=>{let r=document.createElement("input");r.type="hidden",r.name=t,r.value=n,e.appendChild(r)},Xn=e=>{let t=document.createElement("form");t.method="post",t.action=e.action,t.hidden=!0,Oe(t,"sesskey",e.sesskey),Oe(t,"formaction",e.decision),e.userenrolments.forEach(n=>Oe(t,"userenrolments[]",String(n))),Oe(t,"outcomemessage",e.messages===null?e.message:""),Object.entries(e.messages??{}).forEach(([n,r])=>Oe(t,`outcomemessages[${n}]`,r)),Oe(t,"decisionnote",e.note),e.decision==="confirm"&&e.messages===null&&(e.groups?.forEach(n=>Oe(t,"groups[]",String(n))),e.roleid!==null&&Oe(t,"roleid",String(e.roleid))),document.body.appendChild(t),t.submit()};var Jn=(e,t)=>{let n=t.decision!==null&&e!==null&&e.candecide;return{decides:n,notingroup:!!e?.notingroup,narrowed:!!e?.narrowed,viewonly:e!==null&&!n,groupdefault:e?.groupdefault??"",allcolumns:e?.columns??[],usedefault:e?.preference.usedefault??!0,presets:e?.presets??[],total:e?.total??0,list:(e?.rows??[]).map(r=>({...r,id:r.userenrolmentid}))}},er=(e,t,n,r)=>{let{list:o,decides:l}=n,a=new Set(o.filter(u=>!u.selectable).map(u=>u.id)),p=o.filter(u=>e.has(u.id)),c=(u,R)=>t(d=>{let _=new Set(d);return R&&!a.has(u)?_.add(u):_.delete(u),_}),i=u=>t(()=>u?new Set(o.filter(R=>!a.has(R.id)).map(R=>R.id)):new Set),y=l?{selected:e,locked:a,label:u=>b(r.selectapplicant,u.applicant.fullname),pagelabel:r.selectpage,onSelect:c,onSelectPage:i}:void 0;return{locked:a,chosen:p,selection:y}};import{useCallback as aa,useEffect as la,useRef as mn,useState as Qt}from"react";import{fetchOne as $t}from"@moodle/lms/core/ajax";var tr=(e,t)=>$t({methodname:"enrol_apply_queue_rows",args:{enrolid:e,scope:t.scope,search:t.search,filters:Object.entries(t.filters).map(([n,r])=>({name:n,value:r})),page:t.page,perpage:t.perpage,sort:t.sort,direction:t.direction}}),nr=e=>$t({methodname:"enrol_apply_queue_methods",args:{scope:e.scope,search:e.search,filters:Object.entries(e.filters).map(([t,n])=>({name:t,value:n})),page:e.page,perpage:e.perpage,sort:e.sort,direction:e.direction}}),rr=e=>$t({methodname:"enrol_apply_queue_summary",args:{enrolid:e}}),or=(e,t)=>$t({methodname:"enrol_apply_set_queue_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}});import{Fragment as oa,jsx as U,jsxs as Ye}from"react/jsx-runtime";var na={method:4,site:4,mentees:2},bt=(e,t)=>Math.min(100,Math.max(0,Math.round(e*100/t))),je=({label:e,value:t,sub:n,meter:r,warn:o})=>Ye("div",{className:"enrol_apply-rview-tile"+(o?" enrol_apply-rview-tile--warn":""),role:"listitem",children:[U("div",{className:"enrol_apply-rview-tile-label",children:e}),U("div",{className:"enrol_apply-rview-tile-value",children:t}),r!==null?U("span",{className:"enrol_apply-qview-meter"+(o?" enrol_apply-qview-meter--warn":""),"aria-hidden":"true",children:U("span",{style:{width:`${r}%`}})}):null,n!==""?U("div",{className:"enrol_apply-rview-tile-sub",children:n}):null]}),pn=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.DateTimeFormat(t,{dateStyle:"short"}).format(new Date(e*1e3))}catch{return new Date(e*1e3).toLocaleDateString()}},ar=({labels:e,summary:t})=>{let n=t.awaiting>0?b(e.oldest,Le(e,t.oldestwait)):"",r=t.late>0?b(e.late,f(t.late)):"";return U(je,{label:e.awaiting,value:f(t.awaiting),sub:[n,r].filter(o=>o!=="").join(" \xB7 "),meter:null,warn:t.late>0})},ra=({labels:e,summary:t})=>Ye("div",{className:"enrol_apply-rview-band",role:"list","data-region":"enrol_apply-tiles",children:[U(ar,{labels:e,summary:t}),U(je,{label:e.deferred,value:f(t.deferred),sub:t.awaiting>0?b(e.deferredshare,f(bt(t.deferred,t.awaiting))):"",meter:null,warn:!1}),t.sitefigures?Ye(oa,{children:[U(je,{label:e.courses,value:f(t.courses),sub:t.methods===1?e.methods_one:b(e.methods,f(t.methods)),meter:null,warn:!1}),U(je,{label:e.methodsfull,value:f(t.methodsfull),sub:e.methodsfullsub,meter:null,warn:t.methodsfull>0})]}):null]}),lr=({labels:e,kind:t,summary:n,error:r,onRetry:o})=>{if(n===null&&r)return Ye("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",U("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:o,children:e.retry})]});if(n===null)return Ye("div",{className:"enrol_apply-rview-band","aria-busy":"true",children:[Array.from({length:na[t]},(a,p)=>Ye("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[U("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),U("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"})]},p)),U("span",{className:"visually-hidden",role:"status",children:e.loadsummary})]});if(t!=="method")return U(ra,{labels:e,summary:n});let l=n.narrowed?e.methodnote:"";return Ye("div",{className:"enrol_apply-rview-band",role:"list","data-region":"enrol_apply-tiles",children:[n.places>0?U(je,{label:e.placestaken,value:ne(e.ofmany,{taken:f(n.placestaken),total:f(n.places)}),sub:l,meter:bt(n.placestaken,n.places),warn:!1}):null,n.applicantlimit>0?U(je,{label:e.held,value:ne(e.ofmany,{taken:f(n.applicants),total:f(n.applicantlimit)}),sub:l,meter:bt(n.applicants,n.applicantlimit),warn:n.applicants*5>=n.applicantlimit*4}):null,U(ar,{labels:e,summary:n}),U(je,{label:e.status,value:n.open?e.open:e.closed,sub:n.open&&n.closes>0?b(e.closes,pn(n.closes)):"",meter:null,warn:!1})]})};import{Fragment as wn,jsx as D,jsxs as We}from"react/jsx-runtime";var sa={course:300,awaiting:170,deferred:120,oldest:150,places:220,status:190,queue:170},sr=tt(sa,150,{course:200},80,800),ir={course:"asc",awaiting:"desc",oldest:"asc"},ia=e=>[["course",e.coursemethod],["awaiting",e.awaiting],["deferred",e.deferred],["oldest",e.oldestwait],["places",e.placestaken],["status",e.status],["queue",e.queue]].map(([t,n])=>({key:t,title:n,sortable:t in ir,default:!0,active:!0,fixed:t==="course"})),cr=e=>{let[t,n]=Qt(null),[r,o]=Qt(!1),[l,a]=Qt(!1),p=mn(0),c=mn(null),i=mn(e);i.current=e;let g=aa(y=>{let u=++p.current;c.current||(c.current=re("enrol_apply/queue:methods")),o(!0),nr(y).then(R=>{u===p.current&&(R.page!==y.page&&i.current(R.page),n(R),a(!1),o(!1))}).catch(()=>{u===p.current&&(a(!0),o(!1))})},[]);return la(()=>{!r&&c.current&&(c.current(),c.current=null)},[r,t,l]),{answer:t,loading:r,error:l,load:g}},dr=(e,t)=>e!==null&&e.sort===t?e.direction==="asc"?"desc":"asc":ir[t]??"asc",ca=({labels:e,row:t})=>t.capacityknown?t.places<=0?D("span",{className:"enrol_apply-rview-c",children:b(e.takennolimit,f(t.placestaken))}):We(wn,{children:[D("span",{className:"enrol_apply-rview-c",children:ne(e.ofmany,{taken:f(t.placestaken),total:f(t.places)})}),D("span",{className:"enrol_apply-qview-meter"+(t.placesfull?" enrol_apply-qview-meter--warn":""),"aria-hidden":"true",children:D("span",{style:{width:`${bt(t.placestaken,t.places)}%`}})})]}):We("span",{className:"enrol_apply-rview-c",children:[D("span",{"aria-hidden":"true",children:"\u2013"}),D("span",{className:"visually-hidden",children:e.capacityhidden})]}),da=({labels:e,row:t})=>t.capacityknown?t.placesfull||t.limitreached?We("span",{className:"enrol_apply-status enrol_apply-status--neutral",title:e.methodfullwarn,children:[e.methodfull,D("span",{className:"visually-hidden",children:" "+e.methodfullwarn})]}):t.open?We(wn,{children:[D("span",{className:"enrol_apply-rview-c",children:e.open}),t.closes>0?D("span",{className:"enrol_apply-rview-sub",children:b(e.closes,pn(t.closes))}):null]}):D("span",{className:"enrol_apply-rview-c",children:e.closed}):D("span",{className:"visually-hidden",children:e.capacityhidden}),ur=({labels:e,row:t})=>t.method.queueurl===""?null:We("a",{className:"btn btn-sm btn-outline-secondary",href:t.method.queueurl,children:[e.openqueue,D("span",{className:"visually-hidden",children:" "+t.method.name})]}),pr=({labels:e,row:t})=>D(yt,{labels:e,row:{course:t.course,method:{...t.method,full:!1}}}),mr=({labels:e,row:t,column:n})=>{switch(n){case"awaiting":return D("span",{className:"enrol_apply-qview-figure","data-figure":"awaiting",children:f(t.awaiting)});case"deferred":return D("span",{className:"enrol_apply-qview-figure","data-figure":"deferred",children:f(t.deferred)});case"oldest":return D("span",{className:"enrol_apply-rview-c",children:Le(e,t.oldestwait)});case"places":return D(ca,{labels:e,row:t});case"status":return D(da,{labels:e,row:t});default:return D(ur,{labels:e,row:t})}},ua=({labels:e,columns:t,rows:n,single:r})=>D(rt,{labels:e,rows:n,single:r,renderCard:o=>({head:We("div",{className:"enrol_apply-qview-methodhead",children:[D(pr,{labels:e,row:o}),D(ur,{labels:e,row:o})]}),lines:D("dl",{className:"enrol_apply-rview-card-list",children:t.filter(l=>!["course","queue"].includes(l.key)).map(l=>We("div",{className:"enrol_apply-rview-card-item",children:[D("dt",{children:l.title}),D("dd",{children:D(mr,{labels:e,row:o,column:l.key})})]},l.key))}),fields:[]})}),wr=e=>{let{labels:t,answer:n,narrow:r}=e,[o,l]=Qt({}),a=ia(t),p=n===null?[]:n.rows.map(i=>({...i,id:i.method.id})),c=(i,g)=>{let y={...o};g===null?delete y[i]:y[i]=g,l(y)};return We(wn,{children:[D(at,{labels:t,loading:e.loading,loaded:n!==null,error:e.error,total:n===null?0:n.total,filtered:e.filtered,cards:r,narrow:r,skeleton:{columns:a,lead:"course",widths:o,sizing:sr},onRetry:e.onRetry,children:r?D(ua,{labels:t,columns:a,rows:p,single:r}):D(nt,{labels:t,columns:a,lead:"course",rows:p,widths:o,sizing:sr,sort:n?.sort??"",direction:n?.direction??"desc",renderLead:i=>D(pr,{labels:t,row:i}),renderCell:(i,g)=>D(mr,{labels:t,row:i,column:g.key}),onSort:e.onSort,onWidth:c,onWidthCommit:()=>{}})}),n!==null&&n.total>0?D(et,{labels:t,page:n.page,perpage:n.perpage,perpages:e.perpages,total:n.total,shown:n.rows.length,narrow:r,onPage:e.onPage,onPerpage:e.onPerpage}):null]})};import{jsx as gn,jsxs as gr}from"react/jsx-runtime";var hr=({labels:e,count:t,methods:n,actions:r,onDecide:o})=>gr("div",{className:"enrol_apply-qview-dock",role:"region","aria-label":e.dock,"data-region":"enrol_apply-dock",children:[gr("span",{className:"enrol_apply-qview-dockcount","aria-live":"polite",children:[b(e.selected,f(t)),n>1?gn("small",{className:"enrol_apply-qview-dockmethods",children:" "+b(e.dockmethods,f(n))}):null]}),gn("div",{className:"enrol_apply-qview-dockactions",children:r.map(l=>gn("button",{type:"button",className:"btn "+l.classes,onClick:a=>o(l.value,a.currentTarget),children:l.label},l.value))})]});import{useEffect as ma,useId as wa,useRef as Rr,useState as Xe}from"react";import{Fragment as br,jsx as oe,jsxs as Ze}from"react/jsx-runtime";var fr=5,vr=e=>{let t=[];return e.forEach(n=>{let r=t.find(o=>o.id===n.method.id);r?r.rows.push(n):t.push({id:n.method.id,rows:[n]})}),t},hn=({labels:e,rows:t})=>{let n=t.length-fr;return Ze("ul",{className:"enrol_apply-qview-sheetwho",children:[t.slice(0,fr).map(r=>Ze("li",{children:[oe("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:r.applicant.picture}}),oe("span",{children:r.applicant.fullname})]},r.id)),n>0?oe("li",{className:"enrol_apply-qview-sheetmore",children:b(e.andmore,f(n))}):null]})},pa=({labels:e,id:t,group:n,approval:r,message:o,onMessage:l})=>{let a=n.rows[0],p=n.rows.length;return Ze("section",{className:"enrol_apply-qview-sheetmethod","aria-labelledby":`${t}-head`,children:[Ze("div",{className:"enrol_apply-qview-sheetmethodhead",id:`${t}-head`,children:[oe("strong",{children:a.course.name}),oe("span",{children:" \xB7 "+a.method.name})," ",oe(un,{labels:e,row:a})]}),oe("div",{className:"enrol_apply-qview-sheetline",children:p===1?e.count_one:b(e.count,f(p))}),r&&a.method.full?oe("div",{className:"alert alert-warning",children:e.methodfullwarn}):null,oe(hn,{labels:e,rows:n.rows}),oe("label",{className:"form-label",htmlFor:`${t}-message`,children:e.sheetmessage}),oe("textarea",{className:"form-control",id:`${t}-message`,rows:2,value:o,"aria-describedby":`${t}-head`,onChange:c=>l(n.id,c.target.value)}),a.method.queueurl!==""?Ze("p",{className:"enrol_apply-qview-sheetopen",children:[oe("a",{href:a.method.queueurl,children:e.openmethod}),r?Ze(br,{children:[" ",oe("span",{className:"enrol_apply-qview-sheetline",children:e.openmethodline})]}):null]}):null]})},yr=({labels:e,id:t,groups:n,approval:r,messages:o,onMessage:l})=>Ze(br,{children:[oe("div",{className:"alert alert-info",children:b(e.spans,f(n.length))}),n.map((a,p)=>oe(pa,{labels:e,id:`${t}-method${p}`,group:a,approval:r,message:o[a.id]??"",onMessage:l},a.id))]});import{Fragment as Ut,jsx as A,jsxs as ae}from"react/jsx-runtime";var ga="button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), a[href]",ha=(e,t,n)=>{let r=f(n);return t==="cancel"?[b(n===1?e.sheetcancelone:e.sheetcancel,r),b(e.sheetcancelcommit,r)]:t==="wait"?[b(n===1?e.sheetwaitone:e.sheetwait,r),b(e.sheetwaitcommit,r)]:[b(n===1?e.sheetconfirmone:e.sheetconfirm,r),b(e.sheetconfirmcommit,r)]},fn=({id:e,text:t})=>A("div",{className:"form-text",id:e,children:t}),_r=({id:e,label:t,help:n,value:r,last:o,onChange:l})=>ae("div",{className:o?"mb-0":"mb-3",children:[A("label",{className:"form-label",htmlFor:e,children:t}),A("textarea",{className:"form-control",id:e,rows:3,value:r,"aria-describedby":`${e}help`,onChange:a=>l(a.target.value)}),A(fn,{id:`${e}help`,text:n})]}),fa=({labels:e,decision:t,offer:n})=>ae(Ut,{children:[t==="cancel"?A("div",{className:"alert alert-danger",children:e.sheetcanceldanger}):null,t==="wait"?A("p",{className:"enrol_apply-qview-sheetline",children:e.sheetwaitline}):null,t==="confirm"&&n.placesfull!==""?A("div",{className:"alert alert-warning",children:n.placesfull}):null]}),va=({id:e,offer:t,groups:n,setGroups:r,roleid:o,setRoleid:l})=>ae(Ut,{children:[t.summary?ae("div",{className:"enrol_apply-qview-sheetsummary",children:[ae("dl",{className:"row mb-0",children:[A("dt",{className:"col-sm-4",children:t.summary.groupslabel}),A("dd",{className:"col-sm-8",children:t.summary.groups}),A("dt",{className:"col-sm-4",children:t.summary.rolelabel}),A("dd",{className:"col-sm-8",children:t.summary.role})]}),ae("div",{className:"form-text",children:[t.summary.help,t.summary.note!==""?" "+t.summary.note:""]})]}):null,t.groups?ae("div",{className:"mb-3",children:[A("label",{className:"form-label",htmlFor:`${e}-groups`,children:t.groups.label}),A("select",{className:"form-select",id:`${e}-groups`,multiple:!0,value:n,"aria-describedby":`${e}-groupshelp`,onChange:a=>r(Array.from(a.target.selectedOptions,p=>p.value)),children:t.groups.options.map(a=>A("option",{value:String(a.id),children:a.name},a.id))}),A(fn,{id:`${e}-groupshelp`,text:t.groups.help})]}):null,t.roles?ae("div",{className:"mb-3",children:[A("label",{className:"form-label",htmlFor:`${e}-role`,children:t.roles.label}),ae("select",{className:"form-select",id:`${e}-role`,value:o,"aria-describedby":`${e}-rolehelp`,onChange:a=>l(a.target.value),children:[A("option",{value:"0",children:t.roles.default}),t.roles.options.map(a=>A("option",{value:String(a.id),children:a.name},a.id))]}),A(fn,{id:`${e}-rolehelp`,text:t.roles.help})]}):null]}),ya=(e,t,n,r)=>e.some(o=>o!=="")||t!=="0"||n.length!==r.length||n.some(o=>!r.includes(o)),ba=(e,t,n)=>{if(!t)return;let r=Array.from(t.querySelectorAll(ga));if(r.length===0){e.preventDefault();return}let o=r[0],l=r[r.length-1],a=document.activeElement;e.shiftKey&&(a===o||a===n)?(e.preventDefault(),l.focus()):!e.shiftKey&&(a===l||a===n)&&(e.preventDefault(),o.focus())},Nr=({labels:e,decision:t,rows:n,offer:r,onClose:o,onCommit:l})=>{let a=wa(),p=Rr(null),c=Rr(null),[i,g]=Xe(""),[y,u]=Xe({}),[R,d]=Xe(""),[_]=Xe(()=>(r.groups?.options??[]).filter(V=>V.selected).map(V=>String(V.id))),[C,v]=Xe(_),[B,z]=Xe("0"),[L,he]=Xe(!1),[Z,K]=ha(e,t,n.length),de=t==="confirm",s=vr(n),h=s.length>1;ma(()=>(c.current?.focus(),document.body.classList.add("modal-open"),()=>document.body.classList.remove("modal-open")),[]);let T=V=>{if(V.key==="Escape"){V.preventDefault(),V.stopPropagation(),L||o();return}V.key==="Tab"&&ba(V,p.current,c.current)},F=()=>{if(L)return;he(!0);let V=de&&!h;l({message:h?"":i,note:R,messages:h?Object.fromEntries(s.map(ie=>[ie.id,y[ie.id]??""])):null,groups:V&&r.groups?C.map(Number):null,roleid:V&&r.roles?Number(B):null})},q=ya([i,R,...Object.values(y)],B,C,_);return ae(Ut,{children:[A("div",{className:"modal-backdrop show"}),A("div",{className:"modal show d-block enrol_apply-qview-sheet",role:"presentation",onMouseDown:V=>{V.target===V.currentTarget&&!L&&!q&&o()},children:A("div",{className:"modal-dialog modal-lg modal-dialog-scrollable",children:ae("div",{className:"modal-content",role:"dialog","aria-modal":"true","aria-labelledby":`${a}-title`,tabIndex:-1,ref:p,onKeyDown:T,"data-region":"enrol_apply-sheet",children:[ae("div",{className:"modal-header",children:[A("h5",{className:"modal-title",id:`${a}-title`,tabIndex:-1,ref:c,children:Z}),A("button",{type:"button",className:"btn-close","aria-label":e.close,onClick:o,disabled:L})]}),ae("div",{className:"modal-body",children:[A(fa,{labels:e,decision:t,offer:r}),h?A(yr,{labels:e,id:a,groups:s,approval:de,messages:y,onMessage:(V,ie)=>u(ee=>({...ee,[V]:ie}))}):ae(Ut,{children:[A(hn,{labels:e,rows:n}),de?A(va,{id:a,offer:r,groups:C,setGroups:v,roleid:B,setRoleid:z}):null,A(_r,{id:`${a}-message`,label:r.message.label,help:r.message.help,value:i,last:!1,onChange:g})]}),A(_r,{id:`${a}-note`,label:r.note.label,help:r.note.help,value:R,last:!0,onChange:d})]}),ae("div",{className:"modal-footer",children:[A("button",{type:"button",className:"btn btn-secondary",onClick:o,disabled:L,children:e.back}),A("button",{type:"button",className:"btn "+(t==="cancel"?"btn-danger":"btn-primary"),onClick:F,disabled:L,children:K})]})]})})})]})};import{Fragment as Mr,jsx as j,jsxs as xr}from"react/jsx-runtime";var Ra=["applicant","course","wait","group"],_a=(e,t)=>e.filter(n=>!Ra.includes(n.key)&&ze(t,n.key).trim()!=="").map(n=>({key:n.key,title:n.title,content:j(cn,{html:ze(t,n.key)})})),kr=({labels:e,narrowed:t,viewonly:n})=>xr(Mr,{children:[t?j("p",{className:"enrol_apply-rview-narrowed",children:e.narrowed}):null,n?j("div",{className:"alert alert-info",role:"status",children:e.viewonly}):null]}),Na=({labels:e,row:t,column:n})=>n==="course"?j(yt,{labels:e,row:t}):j(cn,{html:ze(t,n)}),ka=({labels:e,row:t,shown:n})=>{let r=o=>n.some(l=>l.key===o);return xr(Mr,{children:[r("course")?j("div",{className:"enrol_apply-rview-card-line",children:j(yt,{labels:e,row:t})}):null,r("wait")?j("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--open",dangerouslySetInnerHTML:{__html:ze(t,"wait")}}):null,r("group")&&ze(t,"group")!==""?j("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",dangerouslySetInnerHTML:{__html:ze(t,"group")}}):null]})},Sr=e=>{let{labels:t,list:n,shown:r,cards:o,narrow:l,selection:a}=e;return o?j(rt,{labels:t,rows:n,single:l,selection:a,renderCard:p=>({head:j(dn,{labels:t,row:p}),lines:j(ka,{labels:t,row:p,shown:r}),fields:_a(r,p)})}):j(nt,{labels:t,columns:r,lead:"applicant",rows:n,widths:e.widths,sizing:qt,sort:e.sort,direction:e.direction,selection:a,renderLead:p=>j(dn,{labels:t,row:p}),renderCell:(p,c)=>j(Na,{labels:t,row:p,column:c.key}),onSort:e.onSort,onWidth:e.onWidth,onWidthCommit:e.onWidthCommit})},Cr=(e,t,n,r,o)=>t!==null&&n&&r.length>0?j(hr,{labels:e,count:r.length,methods:new Set(r.map(l=>l.method.id)).size,actions:t.actions,onDecide:o}):null,Er=(e,t,n,r,o,l)=>t!==null&&n!==null&&r.length>0?j(Nr,{labels:e,decision:n,rows:r,offer:t,onClose:o,onCommit:l}):null;import{Fragment as Dr,jsx as Be,jsxs as vn}from"react/jsx-runtime";var Sa=300,Ca=500,Pr="enrol_apply_queue_view",Ea={method:["applicant","wait","group","comment"],site:["applicant","course","wait","details"],mentees:["applicant","course","wait","comment"]},Ma="method",xa="course",Pa=(e,t)=>e.show===ve?{...e,sort:"",direction:"asc",page:0,perpage:t[0]}:e,Lr=e=>e.scope!=="all"||e.search!==""||Object.entries(e.filters).some(([t,n])=>n!==""&&!(t===lt&&n===vt)),La=e=>{let t=le(e).current,{labels:n,enrolid:r}=t,o=le(null),l=Lt(o),[a,p]=ue(()=>Dt(Pr)),[c]=ue(()=>Vn(t)),i=le(c),[g,y]=ue(c),[u,R]=ue(c.search),[d,_]=ue(null),[C,v]=ue(!0),[B,z]=ue(!1),[L,he]=ue(null),[Z,K]=ue(!0),[de,s]=ue(!1),[h,T]=ue({}),F=le(0),q=le(0),V=le(null),ie=le(null),ee=le(null),te=le(null),_e=le(!1),X=le(null),Qe=le(null),Ne=le(null),ke=le(()=>{}),[ct,Se]=ue(()=>new Set),[me,Te]=ue(null),ce=le(null),dt=Rt(w=>{i.current={...i.current,page:w},y(i.current),It(t,i.current,!1)},[t]),Ve=cr(dt),Ce=Rt(w=>{let M=++F.current;V.current||(V.current=re("enrol_apply/queue:rows")),v(!0),tr(r,Pa(w,t.perpages)).then(S=>{M===F.current&&(_e.current||(_e.current=!0,T(Object.fromEntries(S.preference.widths.map(Y=>[Y.key,Y.width])))),w.show!==ve&&S.page!==i.current.page&&dt(S.page),_(S),Se(Y=>{let co=new Set(S.rows.filter(mt=>mt.selectable).map(mt=>mt.userenrolmentid)),Pn=Array.from(Y).filter(mt=>co.has(mt));return Pn.length===Y.size?Y:new Set(Pn)}),z(!1),v(!1))}).catch(()=>{M===F.current&&(z(!0),v(!1))})},[r,t,dt]),Ee=Ve.load,we=Rt(w=>{Ce(w),w.show===ve&&Ee(w)},[Ce,Ee]),Me=Rt(()=>{let w=++q.current;ie.current||(ie.current=re("enrol_apply/queue:summary")),K(!0),rr(r).then(M=>{w===q.current&&(he(M),s(!1),K(!1))}).catch(()=>{w===q.current&&(s(!0),K(!1))})},[r]),$=Rt((w,M)=>{i.current={...i.current,...w},y(i.current),Se(new Set),It(t,i.current,M),we(i.current)},[we,t]);it(()=>{!C&&V.current&&(V.current(),V.current=null)},[C,d,B]),it(()=>{!Z&&ie.current&&(ie.current(),ie.current=null)},[Z,L,de]),it(()=>{It(t,i.current,!1),we(i.current),Me()},[we,Me,t]),it(()=>{let w=M=>{let S=Fn(M.state);S!==null&&(i.current=S,y(S),R(S.search),Se(new Set),we(S))};return window.addEventListener("popstate",w),()=>window.removeEventListener("popstate",w)},[we]),it(()=>()=>{ee.current!==null&&window.clearTimeout(ee.current),te.current?.(),ke.current()},[]);let Ue=w=>{p(w),At(Pr,w)},xe=g.show===ve,Jt=w=>{if(w===ve){xe||$({show:ve,sort:"",direction:"asc",page:0},!0);return}Ue(w),xe&&$({show:"",sort:"",direction:"asc",page:0},!0)},Ct=w=>{R(w),ee.current!==null&&(window.clearTimeout(ee.current),ee.current=null);let M=w.trim(),S=M.length>=t.searchmin?M:"";if(S===i.current.search){te.current?.(),te.current=null;return}if(w===""){te.current?.(),te.current=null,$({search:"",page:0},!1);return}te.current||(te.current=re("enrol_apply/queue:search")),ee.current=window.setTimeout(()=>{ee.current=null,$({search:S,page:0},!1),te.current?.(),te.current=null},Sa)},Pe=Jn(d,t),{groupdefault:Et,allcolumns:ut,usedefault:Ke,list:en,decides:Mt}=Pe,xt=(w,M)=>{let S={...i.current.filters};M!==""?S[w]=M:w===lt&&Et!==""?S[lt]=vt:delete S[w],w===xa&&delete S[Ma],$({filters:S,page:0},!0)},pt=()=>Et!==""?{[lt]:vt}:{},Fe=(w,M)=>{let S=re("enrol_apply/queue:preference");or(r,w).then(()=>{M&&Ce(i.current)}).catch(()=>{M&&z(!0)}).finally(S)},Je=ut.filter(w=>!w.fixed&&w.active).map(w=>w.key),m=w=>Object.entries(w).map(([M,S])=>({key:M,width:S})),x=(w,M)=>{let S=M?[...Je,w]:Je.filter(Y=>Y!==w);d&&_({...d,columns:d.columns.map(Y=>Y.key===w?{...Y,active:M}:Y),preference:{...d.preference,usedefault:!1,columns:S}}),Fe({usedefault:!1,columns:S,widths:m(h)},!0)},E=w=>{let M=new Set(w.columns);d&&_({...d,columns:d.columns.map(S=>S.fixed?S:{...S,active:M.has(S.key)||M.has("answers")&&S.key.startsWith("answer:")}),presets:d.presets.map(S=>({...S,active:S.name===w.name})),preference:{...d.preference,usedefault:!1,columns:w.columns}}),Fe({usedefault:!1,columns:w.columns,widths:m(h)},!0)},I=()=>{X.current!==null&&(window.clearTimeout(X.current),X.current=null);let w=Ne.current;Ne.current=null,w!==null&&Fe({usedefault:Ke,columns:Ke?[]:Je,widths:m(w)},!1),Qe.current?.(),Qe.current=null};ke.current=I;let Ge=(w,M,S=!1)=>{let Y={...h};if(M===null?delete Y[w]:Y[w]=M,T(Y),Ne.current=Y,!S){I();return}X.current!==null&&window.clearTimeout(X.current),Qe.current||(Qe.current=re("enrol_apply/queue:width")),X.current=window.setTimeout(()=>ke.current(),Ca)},Jr=w=>{let M=d!==null&&d.sort===w&&d.direction==="asc"?"desc":"asc";$({sort:w,direction:M,page:0},!0)},eo=ut.filter(w=>w.active),to=t.methods===null?[]:[{value:ve,label:t.methods.label,icon:"queue"}],no={...n,...t.methods??{}},xn=a==="cards"||a==="auto"&&l;it(()=>{me===null&&ce.current&&(ce.current.focus(),ce.current=null)},[me]);let Pt=t.decision,{chosen:tn,selection:ro}=er(ct,Se,Pe,n),oo=(w,M)=>{ce.current=M,Te(w)},ao=w=>{Pt===null||me===null||Xn({action:gt(t,i.current,!0),sesskey:Pt.sesskey,decision:me,userenrolments:tn.map(M=>M.id),...w})},lo=d===null?null:Be(Sr,{labels:n,list:en,shown:eo,cards:xn,narrow:l,widths:h,sort:d.sort,direction:d.direction,selection:ro,onSort:Jr,onWidth:Ge,onWidthCommit:()=>ke.current()}),so=Ea[t.kind].map(w=>({key:w,title:w==="applicant"?n.applicant:"",sortable:!1,default:!0,active:!0,fixed:w==="applicant"})),io=vn(Dr,{children:[Be(at,{labels:n,loading:C,loaded:d!==null,error:B,total:Pe.total,filtered:Lr(g),cards:xn,narrow:l,skeleton:{columns:so,lead:"applicant",widths:h,sizing:qt},dock:Cr(n,Pt,Mt,tn,oo),onRetry:()=>Ce(i.current),children:lo}),d!==null&&d.total>0?Be(et,{labels:n,page:g.page,perpage:g.perpage,perpages:t.perpages,total:d.total,shown:d.rows.length,narrow:l,onPage:w=>$({page:w},!0),onPerpage:w=>$({perpage:w,page:0},!0)}):null]});return vn("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-queueview",children:[Be(lr,{labels:n,kind:t.kind,summary:L,error:de,onRetry:Me}),Pe.notingroup?Be("p",{className:"enrol_apply-rview-narrowed",children:n.notingroup}):vn(Dr,{children:[Be(kr,{labels:n,narrowed:Pe.narrowed,viewonly:Pe.viewonly}),Be(zn,{labels:n,narrow:l,query:g,rows:d,columns:ut,presets:Pe.presets,usedefault:Ke,haswidths:Object.keys(h).length>0,view:a,extraViews:to,methods:xe?Ve.answer?.total??null:void 0,search:u,searchmin:t.searchmin,searching:C,tableurl:gt(t,g,!1),reporturl:t.reporturl,onScope:w=>$({scope:w,page:0},!0),onSearch:Ct,onFilter:xt,onClearFilters:()=>$({filters:pt(),page:0},!0),onClearAll:()=>{R(""),$({filters:pt(),search:"",page:0},!0)},onColumn:x,onPreset:E,onDefaultColumns:()=>{d&&_({...d,preference:{...d.preference,usedefault:!0,columns:[]}}),Fe({usedefault:!0,columns:[],widths:m(h)},!0)},onResetWidths:()=>{T({}),Fe({usedefault:Ke,columns:Ke?[]:Je,widths:[]},!1)},onView:Jt}),xe?Be(wr,{labels:no,answer:Ve.answer,loading:Ve.loading,error:Ve.error,filtered:Lr(g),narrow:l,perpages:t.perpages,onSort:w=>$({sort:w,direction:dr(Ve.answer,w),page:0},!0),onPage:w=>$({page:w},!0),onPerpage:w=>$({perpage:w,page:0},!0),onRetry:()=>Ee(i.current)}):io]}),Er(n,Pt,me,tn,()=>Te(null),ao)]})},Ar=La;import{useCallback as En,useEffect as Xt,useRef as pe,useState as Re}from"react";import{useState as yn}from"react";import{Fragment as Rn,jsx as N,jsxs as G}from"react/jsx-runtime";var bn=8,Tr=7,Kt=({label:e,value:t,sub:n,warn:r,link:o})=>G("div",{className:"enrol_apply-rview-tile"+(r?" enrol_apply-rview-tile--warn":""),children:[N("div",{className:"enrol_apply-rview-tile-label",children:e}),N("div",{className:"enrol_apply-rview-tile-value",children:t}),N("div",{className:"enrol_apply-rview-tile-sub",children:n}),o??null]}),Vr=({label:e,share:t,figure:n,warn:r})=>G("div",{className:"enrol_apply-rview-hrow",role:"listitem",children:[N("span",{className:"enrol_apply-rview-hrow-label",title:e,children:e}),N("span",{className:"enrol_apply-rview-track","aria-hidden":"true",children:N("span",{className:"enrol_apply-rview-fill"+(r?" enrol_apply-rview-fill--late":""),style:{width:`${Math.round(Math.min(1,Math.max(0,t))*100)}%`}})}),N("span",{className:"enrol_apply-rview-hrow-n",children:n})]}),Da=({compact:e})=>G("div",{"aria-hidden":"true",children:[N("div",{className:"enrol_apply-rview-band",children:Array.from({length:e?3:4},(t,n)=>G("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[N("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),N("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"}),N("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"})]},n))}),e?null:N("div",{className:"enrol_apply-rview-dist",children:[0,1].map(t=>G("div",{className:"enrol_apply-rview-panel enrol_apply-rview-sk",children:[N("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"}),N("span",{className:"enrol_apply-rview-skb"}),N("span",{className:"enrol_apply-rview-skb"}),N("span",{className:"enrol_apply-rview-skb"})]},t))})]}),Aa=({labels:e,bymethod:t,bygroup:n,methods:r})=>{let[o,l]=yn(r>1?"method":"group"),[a,p]=yn(!1),[c,i]=yn(""),g=o==="method"?t:n,y=Math.max(86400,...g.map(v=>v.median??0)),u=v=>N(Vr,{label:v.name,share:(v.median??0)/y,figure:`${v.median===null?"\u2013":Le(e,v.median)} (${f(v.count)})`,warn:(v.median??0)>Tr*86400},v.id),R=[...g].sort((v,B)=>B.count-v.count),d=o==="group"&&g.length>bn,_=c.trim().toLocaleLowerCase(),C=[...g].filter(v=>_===""||v.name.toLocaleLowerCase().includes(_)).sort((v,B)=>(B.median??0)-(v.median??0));return G("div",{className:"enrol_apply-rview-panel",children:[G("div",{className:"enrol_apply-rview-panel-title",children:[N("span",{children:e.medianby}),r>1?N(ht,{label:e.breakdown,small:!0,value:o,options:[{value:"method",label:e.bymethod},{value:"group",label:e.bygroup}],onChange:v=>l(v)}):N("span",{children:e.bygroup})]}),d?G(Rn,{children:[N("div",{className:"enrol_apply-rview-note",children:b(e.topgroups,f(bn))}),N("div",{role:"list",children:R.slice(0,bn).map(u)}),N("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":a,onClick:()=>p(!a),children:a?e.hide:b(e.allgroups,f(g.length))}),a?G(Rn,{children:[N("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:e.searchgroup,"aria-label":e.searchgroup,value:c,onChange:v=>i(v.target.value)}),N("div",{className:"enrol_apply-rview-scrolllist",role:"list",children:C.length?C.map(u):N("p",{className:"enrol_apply-rview-note",children:e.nogroupmatch})})]}):null]}):N("div",{role:"list",children:R.map(u)})]})},Fr=({labels:e,summary:t,loading:n,error:r,compactHint:o,measure:l,methods:a,queueurl:p,pendingstatus:c,onMeasure:i,onRetry:g})=>{let y=G("div",{className:"enrol_apply-rview-bandhead",children:[N("span",{className:"enrol_apply-rview-bandtitle",children:e.turnaround}),N(ht,{label:e.measure,small:!0,value:l,options:[{value:"first",label:e.measurefirst},{value:"resolution",label:e.measureresolution}],onChange:R=>i(R)})]}),u;if(t===null&&r)u=G("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",N("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:g,children:e.retry})]});else if(t===null)u=G(Rn,{children:[N(Da,{compact:o}),N("span",{className:"visually-hidden",role:"status",children:e.loadband})]});else{let R=t.measure==="resolution",d=t.median??null,_=t.p90??null,C=t.oldest??null,v=e.nothingopen;C!==null&&(v=b(e.oldest,Le(e,C))+(t.late>0?" \xB7 "+b(e.late,f(t.late)):""));let B=p===""?null:N("a",{className:"enrol_apply-rview-tile-link",href:R?p:`${p}&status=${c}`,children:e.openqueue}),z=Math.max(1,...t.buckets.map(L=>L.count));u=G("div",{className:"enrol_apply-rview-dim",children:[G("div",{className:"enrol_apply-rview-band",children:[N(Kt,{label:R?e.medianresolution:e.medianfirst,value:d===null?"\u2013":Le(e,d),sub:b(e.mediansub,f(t.decided))}),t.compact?null:N(Kt,{label:e.p90,value:_===null?"\u2013":Le(e,_),sub:e.p90sub}),N(Kt,{label:e.open,value:f(t.open),sub:v,warn:t.late>0,link:B}),N(Kt,{label:e.withoutperson,value:f(t.withoutperson),sub:e.withoutpersonsub})]}),t.compact?null:G("div",{className:"enrol_apply-rview-dist",children:[G("div",{className:"enrol_apply-rview-panel",children:[G("div",{className:"enrol_apply-rview-panel-title",children:[N("span",{children:R?e.distresolution:e.distfirst}),N("span",{className:"enrol_apply-rview-note",children:b(e.decisions,f(t.decided))})]}),N("div",{role:"list",children:t.buckets.map((L,he)=>{let Z=f(L.todays??0),K=ne(e.bucket,{from:f(L.fromdays),to:Z});return he===0?K=b(e.bucketfirst,f(L.todays??0)):(L.todays??null)===null&&(K=b(e.bucketlast,f(L.fromdays))),N(Vr,{label:K,share:L.count/z,figure:f(L.count),warn:L.fromdays>=Tr},L.fromdays)})})]}),N(Aa,{labels:e,bymethod:t.bymethod,bygroup:t.bygroup,methods:a})]})]})}return G("section",{className:"enrol_apply-rview-region enrol_apply-rview-bandregion","aria-label":e.turnaround,"aria-busy":n,"data-region":"enrol_apply-band",children:[n&&t!==null?N("div",{className:"enrol_apply-rview-progress"}):null,y,u]})};var Ir=e=>e.facet!==""&&e.options.length>0,Hr=e=>{try{let t=JSON.parse(e.values||"{}");return t!==null&&typeof t=="object"?t:{}}catch{return{}}},Ta=e=>e.reduce((t,n)=>({...t,...Hr(n)}),{}),_n=e=>{if(!e.applied)return"";let t=Hr(e),n=t[`${e.name}_value`];if(n==null)return"";let r=Number(t[`${e.name}_operator`]??0);if(e.facet==="waited"){let o=Number(n)*Number(t[`${e.name}_unit`]??0)/86400;return r===2&&Number.isFinite(o)?String(o):""}return e.facet==="decider"?r===3?String(n):"":String(n)},Or=(e,t,n)=>{let r=Nn(e,t);return t.facet==="waited"?(r[`${t.name}_operator`]=n===""?0:2,r[`${t.name}_value`]=n===""?0:Number(n),r[`${t.name}_unit`]=86400):t.facet==="decider"?(r[`${t.name}_operator`]=n===""?0:3,r[`${t.name}_value`]=n):(r[`${t.name}_operator`]=n===""?0:1,r[`${t.name}_value`]=n),r},Nn=(e,t)=>{let n=Ta(e);for(let r of Object.keys(n))r.startsWith(`${t.name}_`)&&delete n[r];return n},Wr=e=>{let t=_n(e);return e.facet===""||t===""?"":e.options.find(n=>n.value===t)?.label??(e.facet==="decider"?t:"")};import{jsx as Br,jsxs as Wa}from"react/jsx-runtime";var Va=["auto","table","cards"],qr=(e,t)=>t.facet==="waited"?e.waitedmore:t.facet==="decider"?e.decidedby:t.title,Fa=(e,t)=>{switch(t){case"decisions":return e.presetdecisions;case"answers":return e.presetanswers;case"groups":return e.presetgroups;default:return t}},Ia=(e,t,n)=>{let r={measure:e};return t!=="all"&&(r.scope=t),n!==""&&(r.search=n),encodeURIComponent(JSON.stringify(r))},Ha=(e,t)=>({name:t.name,title:qr(e,t),options:t.options,groupings:t.groupings,find:t.facet==="decider"?{label:e.searchdeciders,nomatch:e.nodecidermatch}:{label:e.searchgroups,nomatch:e.nogroupmatch}}),Oa=(e,t)=>{let n=Wr(t),r=n!==""?qr(e,t):t.title;return{key:t.name,text:n===""?r:ne(e.filterchip,{title:r,value:n})}},$r=e=>{let{labels:t,filters:n}=e,r=e.measure==="resolution"?"submission:resolutiontime":"submission:waittime",o=u=>Ir(u)&&(u.facet!=="waited"||u.name===r),l=n.filter(o),a=n.filter(u=>!o(u)),p=u=>n.find(R=>R.name===u),c=e.downloadurl!==""&&e.formats.length>0,i=Ia(e.measure,e.scope,e.appliedsearch),g=c?e.formats.map(u=>({key:u.value,label:u.label,href:`${e.downloadurl}&parameters=${i}&download=${encodeURIComponent(u.value)}`})):[],y=e.presets.map(u=>({name:u.name,label:Fa(t,u.name),active:u.active}));return Br(Wt,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"awaiting",label:t.awaiting},{value:"decided",label:t.decided}],scope:e.scope,total:e.total,facets:l.map(u=>Ha(t,u)),values:Object.fromEntries(l.map(u=>[u.name,_n(u)])),panelnote:a.length>0?Wa("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",Br("a",{href:e.tableurl,children:t.morefilters})]}):null,chips:n.filter(u=>u.applied).map(u=>Oa(t,u)),columns:e.columns,sets:y,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:Va,tableurl:e.tableurl,downloads:g,search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:u=>e.onScope(u),onSearch:e.onSearch,onFacet:(u,R)=>{let d=p(u);d&&e.onFilter(d,R)},onRemoveChip:u=>{let R=p(u);R&&e.onRemoveFilter(R)},onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:u=>{let R=e.presets.find(d=>d.name===u);R&&e.onPreset(R)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Ga,jsx as Gt}from"react/jsx-runtime";var Ba=["outcome","wait","decided","group"],qa={applicant:260,outcome:180,submitted:132,wait:150,decided:172,group:200,method:170,route:170,comment:240,note:240},$a=180,Qa={applicant:160,submitted:104,decided:104},Ua=80,Ka=800,zt=tt(qa,$a,Qa,Ua,Ka),be=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",Yt=(e,t)=>jn(t,Ba.includes(e)),_t=({column:e,html:t})=>Gt(Bt,{parts:Yt(e,t)}),jt=({row:e,label:t})=>e.reviewurl===""?null:Gt("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:e.reviewurl,children:t}),Zt=({row:e,identity:t})=>Gt(Ga,{children:t.map(n=>{let r=be(e,n.key);return r===""?null:Gt("span",{className:"enrol_apply-rview-sub",title:Tt(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});import{Fragment as Qr,jsx as se,jsxs as kt}from"react/jsx-runtime";var za=["applicant","outcome","submitted","wait","decided","group","method"],Nt=({row:e,column:t})=>{let n=Yt(t,be(e,t))[0]??"";return n===""?null:se("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:n}})},Ya=(e,t,n,r)=>{let o=a=>t.some(p=>p.key===a),l=t.filter(a=>!za.includes(a.key)&&be(r,a.key).trim()!=="");return{head:kt(Qr,{children:[kt("div",{className:"enrol_apply-rview-card-who",children:[se("span",{className:"enrol_apply-rview-card-name",dangerouslySetInnerHTML:{__html:be(r,"applicant")}}),o("outcome")?se(Nt,{row:r,column:"outcome"}):null,se(Zt,{row:r,identity:n})]}),se(jt,{row:r,label:e.review})]}),lines:kt(Qr,{children:[kt("div",{className:"enrol_apply-rview-card-line"+(r.open?" enrol_apply-rview-card-line--open":""),children:[o("wait")?se(Nt,{row:r,column:"wait"}):null,o("wait")&&o("submitted")?se("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("submitted")?se(Nt,{row:r,column:"submitted"}):null]}),o("decided")?se("div",{className:"enrol_apply-rview-card-line",children:Yt("decided",be(r,"decided")).map((a,p)=>se("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:a}},p))}):null,o("group")||o("method")?kt("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",children:[o("group")?se(Nt,{row:r,column:"group"}):null,o("group")&&o("method")&&be(r,"group")!==""?se("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("method")?se(Nt,{row:r,column:"method"}):null]}):null]}),fields:l.map(a=>({key:a.key,title:a.title,content:se(_t,{column:a.key,html:be(r,a.key)})}))}},Ur=({labels:e,columns:t,identity:n,rows:r,single:o})=>se(rt,{labels:e,rows:r,single:o,renderCard:l=>Ya(e,t,n,l)});import{Fragment as Za,jsx as qe,jsxs as kn}from"react/jsx-runtime";var ja=({row:e,longest:t})=>!e.open&&!e.decidedbyperson?null:qe("span",{className:"enrol_apply-rview-waitbar"+(e.open?" enrol_apply-rview-waitbar--open":""),"aria-hidden":"true",children:qe("span",{style:{width:`${Math.min(100,Math.round(e.wait/t*100))}%`}})}),Kr=({labels:e,columns:t,identity:n,rows:r,widths:o,sort:l,direction:a,onSort:p,onWidth:c,onWidthCommit:i})=>{let g=Math.max(86400,...r.filter(y=>y.open||y.decidedbyperson).map(y=>y.wait));return qe(nt,{labels:e,columns:t,lead:"applicant",rows:r,widths:o,sizing:zt,sort:l,direction:a,renderLead:(y,u)=>kn("div",{className:"enrol_apply-rview-applicant",children:[kn("div",{className:"enrol_apply-rview-applicant-text",children:[qe(_t,{column:u.key,html:be(y,u.key)}),qe(Zt,{row:y,identity:n})]}),qe(jt,{row:y,label:e.review})]}),renderCell:(y,u)=>kn(Za,{children:[qe(_t,{column:u.key,html:be(y,u.key)}),u.key==="wait"?qe(ja,{row:y,longest:g}):null]}),onSort:p,onWidth:c,onWidthCommit:i})};import{fetchOne as St}from"@moodle/lms/core/ajax";var Gr=(e,t,n)=>St({methodname:"enrol_apply_report_rows",args:{enrolid:e,...t,facets:n}}),zr=(e,t,n,r)=>St({methodname:"enrol_apply_report_summary",args:{enrolid:e,measure:t,scope:n,search:r}}),Yr=(e,t)=>St({methodname:"enrol_apply_set_report_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}}),Sn=(e,t,n)=>St({methodname:"core_reportbuilder_set_filters",args:{reportid:e,parameters:JSON.stringify({measure:t}),values:JSON.stringify(n)}}),Cn=(e,t)=>St({methodname:"core_reportbuilder_filters_reset",args:{reportid:e,parameters:JSON.stringify({measure:t})}});import{Fragment as nl,jsx as $e,jsxs as Mn}from"react/jsx-runtime";var Xa=300,Ja=500,jr="enrol_apply_report_view",el=["applicant","outcome","submitted","wait","decided","group"],tl=e=>{let{labels:t,enrolid:n,reportid:r}=e,o=pe(null),l=Lt(o),[a,p]=Re(()=>Dt(jr)),c={measure:e.measure,scope:"all",search:"",page:0,perpage:e.perpage,sort:"",direction:"desc"},i=pe(c),[g,y]=Re(c),[u,R]=Re(""),[d,_]=Re(null),[C,v]=Re(!0),[B,z]=Re(!1),[L,he]=Re(null),[Z,K]=Re(!0),[de,s]=Re(!1),[h,T]=Re({}),[F,q]=Re({}),V=pe(0),ie=pe(0),ee=pe(null),te=pe(null),_e=pe(null),X=pe(null),Qe=pe(!1),Ne=pe(null),ke=pe(null),ct=pe(null),Se=pe(()=>{}),me=En((m,x)=>{let E=++V.current;ee.current||(ee.current=re("enrol_apply/report:rows")),v(!0),Gr(n,m,x).then(I=>{E===V.current&&(I.counted&&q(Object.fromEntries(I.filters.map(Ge=>[Ge.name,Ge]))),Qe.current||(Qe.current=!0,T(Object.fromEntries(I.preference.widths.map(Ge=>[Ge.key,Ge.width])))),I.page!==i.current.page&&(i.current={...i.current,page:I.page},y(i.current)),_(I),z(!1),v(!1))}).catch(()=>{E===V.current&&(z(!0),v(!1))})},[n]),Te=En(m=>{let x=++ie.current;te.current||(te.current=re("enrol_apply/report:band")),K(!0),zr(n,m.measure,m.scope,m.search).then(E=>{x===ie.current&&(he(E),s(!1),K(!1))}).catch(()=>{x===ie.current&&(s(!0),K(!1))})},[n]),ce=En((m,x,E)=>{i.current={...i.current,...m},y(i.current),x&&me(i.current,E),E&&Te(i.current)},[me,Te]);Xt(()=>{!C&&ee.current&&(ee.current(),ee.current=null)},[C,d,B]),Xt(()=>{!Z&&te.current&&(te.current(),te.current=null)},[Z,L,de]),Xt(()=>{me(i.current,!0),Te(i.current)},[me,Te]),Xt(()=>()=>{_e.current!==null&&window.clearTimeout(_e.current),X.current?.(),Se.current()},[]);let dt=m=>{p(m),At(jr,m)},Ve=m=>{R(m),_e.current!==null&&(window.clearTimeout(_e.current),_e.current=null);let x=m.trim(),E=x.length>=e.searchmin?x:"";if(E===i.current.search){X.current?.(),X.current=null;return}if(m===""){X.current?.(),X.current=null,ce({search:"",page:0},!0,!0);return}X.current||(X.current=re("enrol_apply/report:search")),_e.current=window.setTimeout(()=>{_e.current=null,ce({search:E,page:0},!0,!0),X.current?.(),X.current=null},Xa)},Ce=m=>{let x=re("enrol_apply/report:filters");m().then(()=>{ce({page:0},!0,!0)}).catch(()=>{z(!0)}).finally(x)},Ee=(d?.filters??[]).map(m=>F[m.name]?{...m,options:F[m.name].options,groupings:F[m.name].groupings}:m),we=(m,x)=>{let E=re("enrol_apply/report:preference");Yr(n,m).then(()=>{x&&me(i.current,!1)}).catch(()=>{x&&z(!0)}).finally(E)},Me=d?.columns??[],$=Me.filter(m=>!m.fixed&&m.active).map(m=>m.key),Ue=d?.preference.usedefault??!0,xe=m=>Object.entries(m).map(([x,E])=>({key:x,width:E})),Jt=(m,x)=>{let E=x?[...$,m]:$.filter(I=>I!==m);d&&_({...d,columns:d.columns.map(I=>I.key===m?{...I,active:x}:I),preference:{...d.preference,usedefault:!1,columns:E}}),we({usedefault:!1,columns:E,widths:xe(h)},!0)},Ct=()=>{Ne.current!==null&&(window.clearTimeout(Ne.current),Ne.current=null);let m=ct.current;ct.current=null,m!==null&&we({usedefault:Ue,columns:Ue?[]:$,widths:xe(m)},!1),ke.current?.(),ke.current=null};Se.current=Ct;let Pe=(m,x,E=!1)=>{let I={...h};if(x===null?delete I[m]:I[m]=x,T(I),ct.current=I,!E){Ct();return}Ne.current!==null&&window.clearTimeout(Ne.current),ke.current||(ke.current=re("enrol_apply/report:width")),Ne.current=window.setTimeout(()=>Se.current(),Ja)},Et=m=>{let x=new Set(m.columns);d&&_({...d,columns:d.columns.map(E=>E.fixed?E:{...E,active:x.has(E.key)||x.has("answers")&&E.key.startsWith("answer:")}),presets:d.presets.map(E=>({...E,active:E.name===m.name})),preference:{...d.preference,usedefault:!1,columns:m.columns}}),we({usedefault:!1,columns:m.columns,widths:xe(h)},!0)},ut=m=>{let E=(d?.sort??"")===m&&d?.direction==="asc"?"desc":"asc";ce({sort:m,direction:E,page:0},!0,!1)},Ke=m=>{let x=i.current,E=Me.some(I=>I.key==="wait"&&I.active);ce({measure:m},x.scope!=="all"||x.sort==="wait"||E,!0)},en=Ee.some(m=>m.applied)||g.search!==""||g.scope!=="all",Mt=Me.filter(m=>m.key.startsWith("identity:")),xt=Me.filter(m=>m.key==="applicant"||!m.fixed&&m.active),pt=a==="cards"||a==="auto"&&l,Fe=null;d!==null&&(Fe=pt?$e(Ur,{labels:t,columns:xt,identity:Mt,rows:d.rows,single:l}):$e(Kr,{labels:t,columns:xt,identity:Mt,rows:d.rows,widths:h,sort:d.sort,direction:d.direction,onSort:ut,onWidth:Pe,onWidthCommit:()=>Se.current()}));let Je=el.map(m=>({key:m,title:m==="applicant"?t.applicant:"",sortable:!1,default:!0,active:!0,fixed:m==="applicant"}));return Mn("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-reportview",children:[d?.narrowed?$e("p",{className:"enrol_apply-rview-narrowed",children:t.narrowed}):null,$e(Fr,{labels:t,summary:L,loading:Z,error:de,compactHint:d?.narrowed??!1,measure:g.measure,methods:e.methods,queueurl:e.queueurl,pendingstatus:e.pendingstatus,onMeasure:Ke,onRetry:()=>Te(i.current)}),$e($r,{labels:t,narrow:l,scope:g.scope,total:d===null?null:d.total,filters:Ee,columns:Me.filter(m=>!m.key.startsWith("identity:")||m.active),presets:d?.presets??[],usedefault:Ue,haswidths:Object.keys(h).length>0,view:a,measure:g.measure,search:u,appliedsearch:g.search,searchmin:e.searchmin,searching:C,tableurl:e.tableurl,downloadurl:e.downloadurl,formats:e.formats,onScope:m=>ce({scope:m,page:0},!0,!0),onSearch:Ve,onFilter:(m,x)=>Ce(()=>Sn(r,i.current.measure,Or(Ee,m,x))),onRemoveFilter:m=>Ce(()=>Sn(r,i.current.measure,Nn(Ee,m))),onClearFilters:()=>Ce(()=>Cn(r,i.current.measure)),onClearAll:()=>{R(""),i.current={...i.current,search:""},Ee.some(m=>m.applied)?Ce(()=>Cn(r,i.current.measure)):ce({page:0},!0,!0)},onColumn:Jt,onPreset:Et,onDefaultColumns:()=>{d&&_({...d,preference:{...d.preference,usedefault:!0,columns:[]}}),we({usedefault:!0,columns:[],widths:xe(h)},!0)},onResetWidths:()=>{T({}),we({usedefault:Ue,columns:Ue?[]:$,widths:[]},!1)},onView:dt}),$e(at,{labels:t,loading:C,loaded:d!==null,error:B,total:d?.total??0,filtered:en,cards:pt,narrow:l,skeleton:{columns:Je,lead:"applicant",widths:h,sizing:zt},onRetry:()=>me(i.current,!0),children:Fe}),d!==null&&d.total>0?$e(et,{labels:t,page:g.page,perpage:g.perpage,perpages:e.perpages,total:d.total,shown:d.rows.length,narrow:l,onPage:m=>ce({page:m},!0,!1),onPerpage:m=>ce({perpage:m,page:0},!0,!1)}):null,e.retention!==""?Mn("p",{className:"enrol_apply-rview-retention","data-region":"enrol_apply-retention",children:[e.retention,e.retentionurl!==""?Mn(nl,{children:[" ",$e("a",{href:e.retentionurl,children:t.retentionchange})]}):null]}):null]})},Zr=tl;import{jsx as Xr}from"react/jsx-runtime";var rl=e=>e.mount===void 0||e.mount==="report"?Xr(Zr,{...e}):e.mount==="queue"?Xr(Ar,{...e}):null,Yi=rl;export{Yi as default};
/**
 * The narrow step and the reader's view choice, shared by every view of the data report.
 *
 * The narrow step is read from an element's own width, never the window's: the view sits in a page
 * whose drawers and blocks take a width the window does not show (AR-D9; stylelint refuses a
 * container query, which would otherwise say this in CSS).
 *
 * @module     enrol_apply/datareport/layout
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Words and numbers: filling the strings the props carry, and the wait as the report words it.
 *
 * There is no core/str for an ES module, so the strings arrive translated with their placeholders
 * ("{$a} applications"), and only the client knows the value.
 *
 * @module     enrol_apply/datareport/str
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The foot of the rows: which rows these are, the pages and the page size (AR-D14: 30, 50 or 100).
 *
 * The server pages (one data query and one count whatever the size); the client never holds more
 * than one page. Narrow, the pages read "Page 2 of 9" between Previous and Next.
 *
 * @module     enrol_apply/datareport/Pager
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * A synchronous hold on Moodle's pending-JavaScript counter, the one Behat waits on.
 *
 * core/ajax holds the counter only while a request is in flight; between its answer and the render
 * that draws it, and across a filter write followed by the reads it causes, nothing would be
 * pending and Behat would read the page as settled before the rows exist. M.util.js_pending, which
 * core/pending itself calls, is on every page and takes effect at once. block_compass's pending.ts
 * is the fleet's precedent.
 *
 * @module     enrol_apply/datareport/pending
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Column widths: a view's policy built from its tables, and the width a column is drawn at.
 *
 * @module     enrol_apply/datareport/sizing
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The bounded grid (section 5b of the study): a real table in a frame that scrolls both ways.
 *
 * table-layout fixed with a col per column, so the widths are exact and the browser does not measure
 * every cell; the heading row sticks to the top of the frame and the lead column (the view's first,
 * named by `lead`) to its left, with a shadow on its edge once the frame has scrolled sideways.
 * Sortable headings are buttons, the order in aria-sort. Each heading's right edge is a separator that
 * resizes its column: a drag changes the col element alone and is saved once, on release, in the
 * reader's preference (AR-D13); the arrow keys move it by 16 px, saved once too, after the last key or
 * when the handle loses the focus; a double click restores the default.
 *
 * What a cell holds is the view's: `renderLead` draws the lead column's cell, `renderCell` every other.
 * A view that selects rows passes a selection, whose boxes lead the lead column's heading and cells,
 * each in a label of its own (visually hidden text) so it is named the way a form control is; a view
 * without one draws none.
 *
 * Strings read from the view's labels: grid, caption, resize.
 *
 * @module     enrol_apply/datareport/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
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
 */
/**
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
 */
/**
 * The queue's filters live in the page's address (CQ-D6), not in a preference.
 *
 * The names are the ones manage.php reads for its table: `status` (the enrolment status, which is the
 * scope), `search`, `group`, the identity fields' tokens, `appliedfrom` and `appliedto`. What the
 * table has no control for keeps names of its own: `named`, `waited`, `appliedbefore`, `sort`, `dir`,
 * `page` (from 0) and `perpage`. A value at its default is left out. The server reads the address for
 * the first paint (enrol_apply\output\queue_view::initial()), so a link the table page or the course
 * report builds lands filtered; this module writes it back, and the browser's history carries the
 * query for the back and forward buttons. The grid is the method queue's page (CQ-D5), so its address
 * carries no view parameter; the table's carries `view=table`. A decision posts to the grid's address
 * (queue/decide.ts), which manage.php cleans again for the page it lands on.
 *
 * The site-wide queue's "By course" view (SQ-D9) is `show=methods`, a parameter of its own so that it is
 * never read as `view`; there `sort`, `dir`, `page` and `perpage` are that view's, written as the list's
 * are, and its default sort (what awaits, most first) is left out. The table's address never carries it.
 *
 * @module     enrol_apply/queue/address
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The data report's icons, drawn in currentColor and always decorative.
 *
 * A component cannot render core's pix icons ({{#pix}} is Mustache), so the few glyphs the bar,
 * the menus and the chips need are inline SVG here, each beside a word or inside a named control.
 *
 * @module     enrol_apply/datareport/icons
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The filter panel (the Design System's filter-panel.md and counter.md): one group per facet.
 *
 * The panel knows facets, not where their values live: it is handed the definitions, the value each is
 * set to ('' for none) and a callback that a view turns into its own write (the course report rewrites
 * core's stored filters; a queue keeps its filters in the page's address). Each option carries the rows
 * it would list under every other filter in force; an option no row would list is hidden unless chosen.
 * A facet with more than eight options is a search over a list capped at fifty, the chosen one pinned
 * on top, with a grouping select when the facet has groupings (section 5b). A facet may carry a note of
 * its caller's, drawn under its options.
 *
 * Strings read from the view's labels: any, filters, clearfilters, show, showcount, grouping,
 * allgroupings, moregroups, moregroupsgrouping.
 *
 * @module     enrol_apply/datareport/FilterPanel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The bar's menus (the Design System's menu.md): the keys that move between items, and the Columns
 * menu's sets.
 *
 * @module     enrol_apply/datareport/menus
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * A segmented control (the Design System's segmented-control.md): one of two to four visible values.
 *
 * A radiogroup with one tab stop, the chosen cell; the arrow keys, Home and End move to another cell
 * and choose it.
 *
 * @module     enrol_apply/datareport/Segmented
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The filter bar above the rows (the Design System's filter-bar.md, chip.md, menu.md, filter-panel.md).
 *
 * The scope, the count, a capsule of icon buttons - Filters with its loud counter, Columns, View and,
 * where a view offers one, Download - and the search by name in a capsule of its own, folded to its
 * button until pressed or `/` is typed; the chips of what is in force on a row of their own, then
 * "Clear all". One layer is open at a time; Escape or a press outside closes it and gives the focus
 * back to its button. Each menu hangs under its own button, the panel under the row of controls (never
 * under the chips); at the narrow step (the view's container under 576 px) the menus hang from the
 * controls' end, the scope takes a line of its own after them, and the open search takes the line.
 *
 * The open search: Escape closes it whatever it holds, the text staying in force, and gives the focus
 * back to its button; leaving it empty closes it too; the eraser clears the text and keeps it open. The
 * text in force shows as a chip only while the field is closed, so it is never said twice.
 *
 * What differs between views arrives as props: the scope's values, the facets and their values with
 * the callback that writes a change where the view keeps its filters, the chips of the filters in
 * force, the column catalogue and its sets, the View choices and the link to the view's server table,
 * and the Download links (none: no Download button). Columns starts with the sets, each handed to the
 * view as the reader's choice, then lists the catalogue, a fixed column always shown, and ends with
 * the default columns and "Reset column widths". A view may put a link of its own at the end of the
 * chips' row (`aside`: the queue's "Open the report"), which then shows with no chip in force too.
 * A view drawing a body of its own (an extra view) may hide Columns (`hidecolumns`) and have the count
 * state another number in another string (`counted`, `countkey`: the queue's "N enrolment methods"); the
 * panel's "Show" still states `total`, the rows the filters list.
 *
 * Strings read from the view's labels: scope, count (and count_one), filters, filterson, columns,
 * presets, always, defaultcolumns, resetwidths, view, viewauto, viewautohint, table, cards, coretable,
 * download, search, cleartext, removefilter, searchchip, clearall, and FilterPanel's.
 *
 * @module     enrol_apply/datareport/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The queue's filter bar: the shared bar (datareport/Bar) with the queue's vocabulary.
 *
 * The scope is All, Pending or Deferred. Filters draws the facets the rows answer counts (group, how
 * the group was named, waited more than, applied before; on the site-wide queue the course and the
 * category, each searched and capped with a note of what was left out, the method once a course with two
 * is chosen, and with or without a group) and the identity fields the reader may filter
 * by from a list, which the service does not count; the text fields and the dates are set in the
 * table view, which the panel's note links, and show here as chips that remove them. Columns starts
 * with the three sets (CQ-D7). View is Automatic, Table or Cards, and the table view; the site-wide queue
 * adds "By course" (SQ-D9), whose body the queue view draws itself: while it is shown Columns is left out and
 * the count states the enrolment methods, while the panel's "Show" still states the applications the filters
 * list. No Download (CQ-D11): "Open the report" ends the chips' row for a reader the report admits.
 *
 * @module     enrol_apply/queue/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * A cell drawn from the server's formatted HTML, one fact per line.
 *
 * A data report's cells are formatted on the server, where the masking and the escaping ran, so a cell
 * is inserted as HTML and never built here. A cell that holds several facts has them joined by a
 * newline; the first line is the cell's line and the others its sub-lines. Every line ends in an
 * ellipsis, its whole text in its title.
 *
 * @module     enrol_apply/datareport/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The queue's cells as the grid and the cards draw them, and the columns' widths.
 *
 * Two kinds of value arrive, and they are drawn differently on purpose. The cells (`cells[].html`) and
 * the picture are the server's formatted HTML, the masking and the escaping already applied there
 * (queueengine::row()), so they are inserted as HTML. Everything the service documents as plain - the
 * applicant's name, the identity values, the tags' words and titles, the course's and the method's names
 * on the queues that span courses - is drawn as text, never as HTML.
 *
 * @module     enrol_apply/queue/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * A decision is a real form posted to manage.php (CQ-D4), never a web service.
 *
 * The form carries the table form's own fields under the table form's own names - `sesskey`,
 * `formaction`, one `userenrolments[]` per selected row, `outcomemessage`, `decisionnote`, and for an
 * approval offered the choosers, `groups[]` and `roleid` - so manage.php reads it as it reads the
 * table's (decision::posted()), requires the sesskey and a POST, and decision::apply() runs every guard
 * it has, row by row; what the client selected or offered decides nothing on the server. The form posts
 * to the view's own address (queue/address.ts), which carries the scope, the filters, the search, the
 * sort and the page as the table form's action carries the table's filters; manage.php cleans them
 * again (queue_view::return_url()) and redirects there with its notifications, so the browser lands
 * on the grid it decided from.
 *
 * A selection on the site-wide queue that spans methods posts what the site-wide table's sheet posts
 * (amd/src/manage.js, commit()): the page's own `outcomemessage` empty, and one
 * `outcomemessages[<enrolid>]` per method, keyed by the method id the rows service gave the rows, which
 * decision::groups() uses only to look up a message for the rows the database says belong to that method;
 * and never a chooser, which that sheet does not offer (SQ-D10).
 *
 * @module     enrol_apply/queue/decide
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * What the queue view reads off the newest rows answer, and the selection it builds over the page's rows.
 *
 * Both are plain functions of the answer and the props: the view keeps the state, this keeps the reading,
 * so the component stays small enough to follow.
 *
 * @module     enrol_apply/queue/derive
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The only module of the queue's view that talks to the server.
 *
 * Every call goes through core's ES module @moodle/lms/core/ajax and is ONE request of its own
 * (fetchOne), as the report's are: a rows read must never ride in a batch behind the preference write
 * it depends on. Core's ajax holds Behat's pending counter while each request is in flight; the view
 * holds it across the render that follows (datareport/pending.ts). None of these decides anything.
 *
 * @module     enrol_apply/queue/repository
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The queue's top tiles (CQ-D10): the method's capacity and the reader's queue, from enrol_apply_queue_summary.
 *
 * Places taken against the places, and applications held against the applicant limit, each only where
 * the method sets one, with a meter, as the table page's capacity panel shows them (and "on this method"
 * for a reader narrowed by separate groups, whose queue is not the method's); the applications awaiting
 * a decision, with the oldest wait and how many have waited longer than a week, in the warning tone when
 * any has; open or closed, with the closing date while open. The summary does not move with a filter,
 * so the tiles load once.
 *
 * The queues that span courses draw their own tiles (SQ-D7, {@link WideTiles}): what awaits, what is deferred
 * and its share of what awaits, and for a site-wide reader the courses (and methods) holding an application
 * and the methods at their limit; the mentee queue draws the first two alone (SQ-D13).
 *
 * @module     enrol_apply/queue/Tiles
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The site-wide queue's "By course" view (stage W4 of docs/design/site-queue-as-report-plan.md, SQ-D9): one
 * row per enrolment method of what the list holds under the scope, the search and the filters, with what
 * awaits, what is deferred, the oldest wait, the places taken and whether the method is open, and a link to
 * that method's queue.
 *
 * The rows come from enrol_apply_queue_methods (queue_methods.php), which reads the list's own table, so the
 * bar's scope, filters and search narrow this view as they narrow the list. No selection, no dock and no
 * column choice: the grid is the data report's frame (datareport/Grid) with a fixed set of columns, sortable
 * by the course, what awaits and the oldest application; its widths are this page's alone, never stored. At
 * the narrow step the methods are cards, the same facts stacked. Every name is text; nothing here is HTML.
 *
 * {@link useMethods} reads the answers: the newest request wins, and each wait holds Behat's pending counter
 * until the answer is drawn.
 *
 * @module     enrol_apply/queue/Methods
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
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
 */
/**
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
 */
/**
 * The decision sheet: what the table's sheet (amd/src/manage.js, MQ-D5, MQ-D6) says and asks, as a
 * modal dialog of the view's own.
 *
 * Its title names the decision and the count, its list the first five applicants and how many more;
 * a cancellation says what cancelling removes, a deferral where the application stays, an approval
 * that every place is taken when the table page says so. A selection on the site-wide queue that spans
 * enrolment methods is listed method by method instead, each with its own message box
 * (queue/SheetMethods, SQ-D10), and no group or role chooser is offered there. Then the fields of the table's decision
 * controls, with their labels and help: for an approval the groups and the role where the props offer
 * them (a holder of enrol/apply:choosedecision) or the summary of what an approval gives, then the
 * message to the applicant and the note for the record, which every decision posts. The sheet's commit
 * button is the confirmation, worded as the table's ("Cancel 2"); Back, the close button and Escape close
 * it without posting anything, and so does a press outside while no field holds typed text or a
 * non-default choice (core's modal does not hide a dialogue that holds a form either).
 *
 * Bootstrap's modal markup, drawn by React rather than by Bootstrap's script, so Moodle's dialogue
 * locator finds it by its title. The focus moves to the title when the dialog opens and stays inside it
 * (Tab and Shift+Tab wrap); the view gives it back to the dock button that opened it.
 *
 * @module     enrol_apply/queue/Sheet
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The parts of the queue view that only choose what to draw: the notices over the bar, the rows as the
 * grid or the cards, the dock and the decision sheet. Each takes what the view already holds and keeps
 * no state of its own.
 *
 * @module     enrol_apply/queue/Parts
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The approval queue as a data report: the method queue's page (stages 3 and 4 of
 * docs/design/confirm-queue-as-report-plan.md, CQ-D5), the site-wide queue's (stage W3 of
 * docs/design/site-queue-as-report-plan.md, SQ-D12) and the mentee queue's (stage W5, SQ-D13).
 *
 * One view for both, branching on the listing's kind (the props for the first paint, the rows answer
 * after): the site-wide queue draws its own tiles (SQ-D7), a "Course · method" column (SQ-D2), the course,
 * category, method and grouped facets the service returns (choosing another course releases the method),
 * and a decision sheet that splits a selection spanning methods by method (SQ-D10). The site-wide queue's
 * View menu adds "By course" (stage W4, SQ-D9, queue/Methods): one row per enrolment method of what the
 * list holds, read from enrol_apply_queue_methods under the same scope, filters and search, with no
 * selection, no dock and no Columns; the address says `show=methods`, and its sort and page are that view's.
 * The rows are still read while it is shown, at their first page, for the panel's facets and the chips.
 *
 * The mentee queue is the site-wide queue's grid with less (SQ-D13), and nothing here decides what less:
 * the rows service sends a mentor its catalogue (applicant, course and method, wait, comment), its one
 * facet, no identity value, no "Full" and no method queue's address; the summary sends no site figures;
 * the props offer no "By course" view and no report. Each part draws what it is given, so a mentor's page
 * falls on the reduced side wherever a part branches on what the answer holds.
 *
 * manage.php?id=<method>, and manage.php for a site-wide reader or a mentor, render the shell
 * (enrol_apply\output\queue_view) with configuration and strings, unless view=table asks for today's
 * table; the rows and the tiles come from the plugin's web
 * services, which read the queue's own table (classes/local/queueengine.php), so access, the
 * separate-groups narrowing, the masking of the answers and the filters are the table page's. Two
 * regions load on their own:
 *
 * - the rows (enrol_apply_queue_rows): the scope, a filter, the search, a sort or a page reloads them,
 *   and they come with the panel's counts each time;
 * - the tiles (enrol_apply_queue_summary): the method's capacity and the reader's queue before any
 *   filter, so they load once.
 *
 * The scope, the filters, the search, the sort, the page and the page size live in the page's address
 * (queue/address.ts, CQ-D6), never in a preference; the columns and their widths are the reader's
 * preference (enrol_apply_set_queue_preference, CQ-D7), and a column change reads the rows again only
 * once the write has landed, because the server builds the columns from it. One request is in flight
 * per region: core's ajax cannot abort, so each region numbers its requests and drops any answer but the
 * newest. Every wait holds Behat's pending counter until the answer it waited for is drawn.
 *
 * Deciding (stage 4): a reader who may decide selects rows of the page - only the rows the rows service
 * marks selectable, never their own application - with a box per row and one for the page; the
 * selection is cleared whenever the page, the scope, a filter, the search or the sort changes, so no row
 * out of sight stays selected, and the dock's count says "on this page". The dock under the rows holds
 * the table's three decisions; each opens the decision sheet (queue/Sheet), whose commit submits a real
 * form to manage.php with the table form's fields (queue/decide, CQ-D4). The server decides nothing on
 * the strength of what this view selected or offered: decision::apply() checks every row again. A
 * viewer gets no box, no dock and no sheet. Each row links to its review page.
 *
 * @module     enrol_apply/queue/QueueView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The summary band: how long applications waited, over the rows the reader's table lists.
 *
 * Tiles (median, 90th percentile, still open, decided without a person), the wait's distribution and
 * the median by method or by group (AR-D5, AR-D7: no breakdown by decider). A reader the separate
 * groups rule narrows gets the compact band, which the summary itself says (compact): the median, what
 * is still open and the decisions without a person (AR-D17). The measure switch (AR-D4) reloads this
 * region alone. "Still open" leads to the queue for a reader the queue admits (AR-D18), which the
 * props say by carrying its url.
 *
 * Every number is the server's (reportengine::summary()), over the same SQL as the rows.
 *
 * @module     enrol_apply/report/Band
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The report's filters as core stores them, read and rewritten for the panel and the chips.
 *
 * Core keeps every filter value of a report for a user in one store, keyed "<filter>_<field>", and
 * core_reportbuilder_set_filters REPLACES that whole set. So a change to one filter is written as
 * every stored value, with that filter's own keys changed, and a removal as every other filter's
 * keys. The panel draws the filters the rows answer names a facet for: the ones that pick one option
 * of a list (core's select filter and this plugin's group filter), the wait as "waited more than" a
 * few days (core's duration filter: at least that many days) and the decider's name (core's text
 * filter: is equal to one decider's name). Each writes its filter class's own keys. The others
 * (dates, free text, the other duration) are set in core's table view and appear here as chips that
 * remove them.
 *
 * @module     enrol_apply/report/filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The course report's filter bar: the shared bar (datareport/Bar) with the report's vocabulary.
 *
 * The scope is all, awaiting or decided. Filters draws the report's facets, which live in core's store
 * (core_reportbuilder_set_filters, filters.ts): the filters that pick one option of a list, "Waited more
 * than" for the wait the band measures and "Decided by", each option with the rows it would list under
 * every other filter in force, the scope and the search (the rows answer counts them). The other
 * filters are set in core's table view, which the panel's note links, and appear as chips that remove
 * them. Columns starts with three sets (Decisions, Answers, Groups). View is Automatic, Table or Cards,
 * and core's own table view. Download links core's download of the report with the measure, the scope
 * and the search in force, which the report applies itself.
 *
 * @module     enrol_apply/report/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The report's cells as the grid and the cards draw them, and the columns' widths.
 *
 * A cell is the report's own formatted HTML (format_row(): the masking and the escaping already ran
 * on the server, as on core's table), so it is inserted as HTML and never built here. A few cells hold
 * several facts, one per line (the formatters join them with a newline: the outcome and what the
 * record says, the wait and how it ended, the date and who decided, the group and the groups joined);
 * the first line is the cell's line and the others its sub-lines. Every line ends in an ellipsis, its
 * whole text in its title.
 *
 * @module     enrol_apply/report/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The course report's cards: the shared card view (datareport/Cards) with the report's four lines.
 *
 * Four fixed lines answer who, what happened, how long and who decided: the applicant with the outcome
 * and the Review action; the wait and the submission date; the decision; the group and the method. The
 * other chosen fields follow in the card's list, a field with nothing in it left out.
 *
 * @module     enrol_apply/report/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The course report's grid: the shared bounded grid (datareport/Grid) with the report's cells.
 *
 * The lead column is the applicant: their name as the report formats it, the identity fields the
 * reader's report shows under it, and the Review action on an open application. Every other cell is
 * the report's formatted HTML, one fact per line, and the wait's carries a bar of the row's wait against
 * the longest on the page. The report selects nothing.
 *
 * @module     enrol_apply/report/Grid
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
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
 */
/**
 * The course report of applications, drawn in React (stage R4 of docs/design/audit-report-study.md).
 *
 * report.php renders the shell (enrol_apply\output\report_view) with configuration and strings; the
 * rows and the summary come from the plugin's web services, which read the system report headlessly,
 * so access, the separate-groups narrowing, the masking, the reader's stored filters and their chosen
 * columns are the report's, as on core's table. Three regions load on their own:
 *
 * - the rows (enrol_apply_report_rows): a sort or a page reloads them alone;
 * - the band (enrol_apply_report_summary): the measure reloads it, and the rows where they show it;
 * - the grid (enrol_apply_set_report_preference): a column change is written, and the rows are read
 *   again only once the write has landed, because the report reads the columns in initialise().
 *
 * A filter, the scope or the search reloads both, and the rows then come with the filter panel's counts,
 * which a sort or a page turn cannot move and so does not ask for: the panel keeps the last counted
 * answer's options. A width set from the keyboard is saved once, 500 ms after the last key or when the
 * handle loses the focus, as a drag is saved once, on release. One request is in flight per region: core's ajax
 * cannot abort, so each region numbers its requests and drops any answer but the newest. The first
 * load draws skeletons with aria-busy; later ones keep the old rows, dimmed, under a 2 px line.
 *
 * The bar, the grid, the cards, the rows' region and the pager are the data report's shared parts
 * (js/esm/src/datareport), which Bar, Grid and Cards here give the report's vocabulary; the band, the
 * queries and the writes are the report's own.
 *
 * @module     enrol_apply/report/ReportView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The bundle's entry: every React view of the plugin, one chosen per mount by its props.
 *
 * Core's react_autoinit mounts the DEFAULT export of the module a mount names, and the fleet's bundle
 * step builds one entry into one file (js/esm/bundle.json), so every view of the plugin is reached
 * through this one component: each template names @moodle/lms/enrol_apply/bundle, and its props name
 * the view in `mount`. A mount that names none is the course report's, whose props (report_view)
 * predate the key; `queue` is the approval queue, of one method or site-wide (queue_view, mounted by
 * manage.php unless view=table asks for the table). Another view is one more branch here and a template
 * whose props name it; the views share the data report's parts, so the bundle carries them once.
 *
 * @module     enrol_apply/views
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
//# sourceMappingURL=bundle.js.map
