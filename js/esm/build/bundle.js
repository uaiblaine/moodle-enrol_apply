import{useCallback as kt,useEffect as mt,useRef as re,useState as de}from"react";import{useEffect as uo,useState as po}from"react";var mo=576,Tt=e=>{let[t,n]=po(!1);return uo(()=>{let r=e.current;if(!r||typeof ResizeObserver>"u")return;let o=new ResizeObserver(l=>{let a=l[0]?.contentRect.width??r.clientWidth;n(a>0&&a<mo)});return o.observe(r),()=>o.disconnect()},[e]),t},Vt=e=>{try{let t=window.localStorage.getItem(e);return t==="table"||t==="cards"?t:"auto"}catch{return"auto"}},Ft=(e,t)=>{try{window.localStorage.setItem(e,t)}catch{}};import{useId as wo}from"react";var b=(e,t)=>(e||"").split("{$a}").join(t),J=(e,t)=>Object.entries(t).reduce((n,[r,o])=>n.split(`{$a->${r}}`).join(o),e||""),Vn=(e,t,n,r)=>b(n===1&&e[`${t}_one`]?e[`${t}_one`]:e[t],r),f=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(t).format(e)}catch{return String(e)}},xe=(e,t)=>{let n=Math.max(0,t);if(n<3600)return b(e.waitminutes,f(Math.max(1,Math.round(n/60))));if(n<86400)return b(e.waithours,f(Math.round(n/3600)));let r=Math.floor(n/86400),o=Math.round((n-r*86400)/3600);return o===24&&(r++,o=0),o>0&&r<10?J(e.waitdayshours,{days:f(r),hours:f(o)}):b(e.waitdays,f(r))},It=e=>{let t=document.createElement("template");return t.innerHTML=e,(t.content.textContent||"").replace(/\s+/g," ").trim()};import{jsx as Pe,jsxs as ln}from"react/jsx-runtime";var go=(e,t)=>[...new Set([1,e-1,e,e+1,t])].filter(n=>n>=1&&n<=t).sort((n,r)=>n-r),at=({labels:e,page:t,perpage:n,perpages:r,total:o,shown:l,narrow:a,onPage:p,onPerpage:c})=>{let i=wo(),g=Math.max(1,Math.ceil(o/n)),y=t+1,d=o>0?t*n+1:0,R=o>0?d+l-1:0,u=[];if(a)u=[Pe("span",{className:"enrol_apply-rview-pager-gap",children:J(e.pageof,{page:f(y),pages:f(g)})},"of")];else{let _=go(y,g);_.forEach((C,v)=>{v>0&&C-_[v-1]>1&&u.push(Pe("span",{className:"enrol_apply-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${C}`)),u.push(Pe("button",{type:"button","aria-current":C===y?"page":void 0,onClick:()=>p(C-1),children:f(C)},C))})}return ln("div",{className:"enrol_apply-rview-foot",children:[Pe("span",{className:"enrol_apply-rview-num","data-region":"enrol_apply-range",children:J(e.range,{from:f(d),to:f(R),total:f(o)})}),g>1?ln("nav",{className:"enrol_apply-rview-pager","aria-label":e.pages,children:[Pe("button",{type:"button",disabled:y===1,onClick:()=>p(t-1),children:e.previous}),u,Pe("button",{type:"button",disabled:y===g,onClick:()=>p(t+1),children:e.next})]}):null,ln("span",{className:"enrol_apply-rview-perpage",children:[Pe("label",{htmlFor:i,children:e.perpage}),Pe("select",{id:i,className:"form-select form-select-sm",value:n,onChange:_=>c(Number(_.target.value)),children:r.map(_=>Pe("option",{value:_,children:f(_)},_))})]})]})};var ee=e=>{let t=window.M?.util;t?.js_pending(e);let n=!0;return()=>{n&&(n=!1,t?.js_complete(e))}};import{useState as bo}from"react";import{useEffect as ho,useRef as Ot}from"react";var lt=(e,t,n,r,o)=>({defaultWidth:l=>e[l]??t,minimumWidth:l=>n[l]??r,maximum:o}),be=(e,t,n)=>Math.min(e.maximum,Math.max(e.minimumWidth(n),t[n]??e.defaultWidth(n)));import{jsx as P,jsxs as Fe}from"react/jsx-runtime";var Ht=16,fo={ascending:"\u25B2",descending:"\u25BC",none:""},vo=(e,t)=>e?t==="asc"?"ascending":"descending":"none",yo=({selection:e,rows:t})=>{let n=Ot(null),r=t.filter(a=>!e.locked.has(a.id)),o=r.filter(a=>e.selected.has(a.id)).length,l=r.length>0&&o===r.length;return ho(()=>{n.current&&(n.current.indeterminate=o>0&&!l)},[o,l]),Fe("label",{className:"enrol_apply-rview-pickwrap",children:[P("input",{ref:n,type:"checkbox",className:"enrol_apply-rview-pick",checked:l,disabled:r.length===0,onChange:a=>e.onSelectPage(a.target.checked)}),P("span",{className:"visually-hidden",children:e.pagelabel})]})},sn=({selection:e,row:t})=>e.locked.has(t.id)?P("span",{className:"enrol_apply-rview-pickwrap enrol_apply-rview-pick--none"}):Fe("label",{className:"enrol_apply-rview-pickwrap",children:[P("input",{type:"checkbox",className:"enrol_apply-rview-pick",checked:e.selected.has(t.id),onChange:n=>e.onSelect(t.id,n.target.checked)}),P("span",{className:"visually-hidden",children:e.label(t)})]}),st=({labels:e,columns:t,lead:n,rows:r,widths:o,sizing:l,sort:a,direction:p,selection:c,renderLead:i,renderCell:g,onSort:y,onWidth:d,onWidthCommit:R})=>{let u=Ot(null),_=Ot(null),C=Ot(null),v=t.reduce((s,h)=>s+be(l,o,h.key),0),q=s=>{s.currentTarget.classList.toggle("is-scrolled",s.currentTarget.scrollLeft>0)},z=(s,h)=>{let T=_.current?.querySelector(`col[data-col="${CSS.escape(s)}"]`);!T||!_.current||(T.style.width=`${h}px`,_.current.style.width=`${v-be(l,o,s)+h}px`)},L=(s,h)=>Math.round(Math.min(l.maximum,Math.max(l.minimumWidth(s),h))),fe=(s,h)=>{if(s.button!==0)return;s.preventDefault(),s.currentTarget.setPointerCapture(s.pointerId),s.currentTarget.classList.add("is-active");let T=be(l,o,h);C.current={key:h,x:s.clientX,width:T,now:T}},Z=s=>{let h=C.current;if(!h)return;let T=document.documentElement.dir==="rtl",F=(s.clientX-h.x)*(T?-1:1),I=L(h.key,h.width+F);I!==h.now&&(z(h.key,I),h.now=I)},K=s=>{let h=C.current;C.current=null,s.currentTarget.classList.remove("is-active"),h&&h.now!==h.width&&d(h.key,h.now)},se=(s,h)=>{let T=document.documentElement.dir==="rtl",F=0;if(s.key==="ArrowRight")F=T?-Ht:Ht;else if(s.key==="ArrowLeft")F=T?Ht:-Ht;else if(s.key==="Home"){s.preventDefault(),d(h,null,!0);return}if(F===0)return;s.preventDefault();let I=L(h,be(l,o,h)+F);I!==be(l,o,h)&&d(h,I,!0)};return P("div",{className:"enrol_apply-rview-gridframe",ref:u,tabIndex:0,role:"region","aria-label":e.grid,onScroll:q,children:Fe("table",{className:"enrol_apply-rview-grid",ref:_,style:{width:`${v}px`},children:[P("caption",{className:"visually-hidden",children:e.caption}),P("colgroup",{children:t.map(s=>P("col",{"data-col":s.key,style:{width:`${be(l,o,s.key)}px`}},s.key))}),P("thead",{children:P("tr",{children:t.map(s=>{let h=vo(a===s.key,p),T=P("span",{className:"enrol_apply-rview-th-title",children:s.title}),F=s.sortable?Fe("button",{type:"button",className:"enrol_apply-rview-sortbtn",onClick:()=>y(s.key),children:[T,P("span",{className:"enrol_apply-rview-arrow","aria-hidden":"true",children:fo[h]})]}):P("span",{className:"enrol_apply-rview-plain",children:T});return Fe("th",{scope:"col",className:s.key===n?"enrol_apply-rview-fixed":void 0,"aria-sort":s.sortable?h:void 0,title:s.title,"data-column":s.key,children:[c&&s.key===n?Fe("div",{className:"enrol_apply-rview-lead",children:[P(yo,{selection:c,rows:r}),F]}):F,P("span",{className:"enrol_apply-rview-resize",role:"separator","aria-orientation":"vertical","aria-label":b(e.resize,s.title),"aria-valuemin":l.minimumWidth(s.key),"aria-valuemax":l.maximum,"aria-valuenow":be(l,o,s.key),tabIndex:0,onPointerDown:I=>fe(I,s.key),onPointerMove:Z,onPointerUp:K,onPointerCancel:K,onDoubleClick:()=>{be(l,o,s.key)!==l.defaultWidth(s.key)&&d(s.key,null)},onKeyDown:I=>se(I,s.key),onBlur:R})]},s.key)})})}),P("tbody",{children:r.map(s=>P("tr",{"data-record":s.id,className:c?.selected.has(s.id)?"enrol_apply-rview-row--selected":void 0,children:t.map(h=>h.key===n?P("th",{scope:"row",className:"enrol_apply-rview-fixed",children:c?Fe("div",{className:"enrol_apply-rview-lead",children:[P(sn,{selection:c,row:s}),i(s,h)]}):i(s,h)},h.key):P("td",{children:g(s,h)},h.key))},s.id))})]})})},Fn=({columns:e,lead:t,widths:n,sizing:r})=>{let o=e.reduce((a,p)=>a+be(r,n,p.key),0),l=["enrol_apply-rview-skb--w80","enrol_apply-rview-skb--w60","enrol_apply-rview-skb--w40"];return P("div",{className:"enrol_apply-rview-gridframe","aria-hidden":"true",children:Fe("table",{className:"enrol_apply-rview-grid",style:{width:`${o}px`},children:[P("colgroup",{children:e.map(a=>P("col",{style:{width:`${be(r,n,a.key)}px`}},a.key))}),P("thead",{children:P("tr",{children:e.map(a=>P("th",{className:a.key===t?"enrol_apply-rview-fixed":void 0,children:P("span",{className:"enrol_apply-rview-plain",children:P("span",{className:"enrol_apply-rview-th-title",children:a.title})})},a.key))})}),P("tbody",{children:Array.from({length:8},(a,p)=>P("tr",{className:"enrol_apply-rview-sk",children:e.map((c,i)=>P("td",{className:c.key===t?"enrol_apply-rview-fixed":void 0,children:P("span",{className:"enrol_apply-rview-skb "+l[(p+i)%3]})},c.key))},p))})]})})};import{jsx as ge,jsxs as ft}from"react/jsx-runtime";var Ro=({labels:e,row:t,single:n,selection:r,renderCard:o})=>{let[l,a]=bo(!1),p=o(t),c=!n||l,i=r?.selected.has(t.id)??!1;return ft("article",{className:"enrol_apply-rview-card"+(i?" enrol_apply-rview-card--selected":""),role:"listitem","data-record":t.id,children:[ft("div",{className:"enrol_apply-rview-card-l1",children:[r?ge(sn,{selection:r,row:t}):null,p.head]}),p.lines,p.fields.length>0?ft("div",{className:"enrol_apply-rview-card-more",children:[n?ge("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":l,onClick:()=>a(!l),children:l?e.less:e.more}):null,c?ge("dl",{className:"enrol_apply-rview-card-list",children:p.fields.map(g=>ft("div",{className:"enrol_apply-rview-card-item",children:[ge("dt",{children:g.title}),ge("dd",{children:g.content})]},g.key))}):null]}):null]})},it=({labels:e,rows:t,single:n,selection:r,renderCard:o})=>ge("div",{className:"enrol_apply-rview-cards"+(n?" enrol_apply-rview-cards--single":""),role:"list",children:t.map(l=>ge(Ro,{labels:e,row:l,single:n,selection:r,renderCard:o},l.id))}),In=({single:e})=>ge("div",{className:"enrol_apply-rview-cards"+(e?" enrol_apply-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:e?4:6},(t,n)=>ft("div",{className:"enrol_apply-rview-card enrol_apply-rview-sk",children:[ge("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),ge("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"}),ge("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),ge("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"})]},n))});import{Fragment as Hn,jsx as Le,jsxs as ct}from"react/jsx-runtime";var _o=({labels:e,loaded:t,error:n,total:r,filtered:o,cards:l,narrow:a,skeleton:p,children:c,onRetry:i})=>{let g=ct("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",Le("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:i,children:e.retry})]});return!t&&n?g:t?r===0?ct("div",{className:"enrol_apply-rview-empty",children:[Le("p",{className:"enrol_apply-rview-empty-title",children:o?e.nomatch:e.empty}),o?Le("p",{className:"enrol_apply-rview-empty-text",children:e.nomatchhelp}):null]}):ct("div",{className:"enrol_apply-rview-dim",children:[n?g:null,c,l?null:Le("p",{className:"enrol_apply-rview-hint",children:e.scrollhint})]}):ct(Hn,{children:[l?Le(In,{single:a}):Le(Fn,{...p}),Le("span",{className:"visually-hidden",role:"status",children:e.loading})]})},ut=e=>ct(Hn,{children:[ct("section",{className:"enrol_apply-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"enrol_apply-rows",children:[e.loading&&e.loaded?Le("div",{className:"enrol_apply-rview-progress"}):null,Le(_o,{...e})]}),e.dock??null]});var No="table",Re="methods",cn="enrolApplyQueue",ko=e=>e.sort===""?!0:e.show===Re?e.sort==="awaiting"&&e.direction==="desc":e.sort==="wait"&&e.direction==="asc",So=(e,t,n)=>{let r=[];return t.scope!=="all"&&r.push(["status",String(t.scope==="pending"?e.statuses.pending:e.statuses.deferred)]),t.search!==""&&r.push(["search",t.search]),Object.keys(t.filters).sort().forEach(o=>{t.filters[o]!==""&&r.push([o,t.filters[o]])}),n?(t.show===Re&&r.push(["show",Re]),ko(t)||r.push(["sort",t.sort],["dir",t.direction]),t.page>0&&r.push(["page",String(t.page)]),t.perpage!==e.perpage&&r.push(["perpage",String(t.perpage)])):r.push(["view",No]),r},vt=(e,t,n)=>{let r=new URL(e.pageurl,window.location.href);return So(e,t,n).forEach(([o,l])=>r.searchParams.set(o,l)),r.toString()},On=e=>({scope:e.initial.scope,search:e.initial.search,filters:Object.fromEntries(e.initial.filters.map(t=>[t.name,t.value])),sort:e.initial.sort,direction:e.initial.direction,page:e.initial.page,perpage:e.initial.perpage,show:e.methods!==null?e.initial.show:""}),Wt=(e,t,n)=>{let r=vt(e,t,!0),o={[cn]:t};try{n&&r!==window.location.href?window.history.pushState(o,"",r):window.history.replaceState(o,"",r)}catch{}document.querySelectorAll('[data-enrol-apply-queue="tablelink"]').forEach(l=>{l.href=vt(e,t,!1)})},Wn=e=>{if(e===null||typeof e!="object"||!(cn in e))return null;let t=e[cn];return{...t,show:t.show??""}};import{useEffect as qt,useId as To,useRef as bt,useState as jn}from"react";import{useId as Eo,useState as qn}from"react";import{jsx as Bn}from"react/jsx-runtime";var Co={filter:"M2 3h12l-4.5 5.5V13l-3 1.5V8.5L2 3z",columns:"M2 3h12v10H2zM6.5 3v10M10 3v10",table:"M2 3h12v10H2zM2 6.5h12M2 10h12M6 6.5V13",cards:"M2 3h5v4H2zM9 3h5v4H9zM2 9h5v4H2zM9 9h5v4H9z",auto:"M2 3h7v10H2zM11 3h3v4h-3zM11 9h3v4h-3z",download:"M8 2v8M4.5 7 8 10.5 11.5 7M3 13h10",search:"M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM10.5 10.5 14 14",close:"M4 4l8 8M12 4l-8 8",eraser:"M9.5 2 14 6.5 7.5 13 3 8.5zM5.6 5.9l4.5 4.5M7.5 13H14",tick:"M3 8.5 6.5 12 13 4.5",chevron:"M4 6l4 4 4-4",reset:"M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5",queue:"M3 4h10M3 8h10M3 12h6"},O=({name:e,className:t})=>Bn("svg",{className:"enrol_apply-rview-icon"+(t?" "+t:""),viewBox:"0 0 16 16",width:"16",height:"16",fill:"none",stroke:"currentColor",strokeWidth:"1.5",strokeLinecap:"round",strokeLinejoin:"round","aria-hidden":"true",focusable:"false",children:Bn("path",{d:Co[e]??""})});import{Fragment as Ao,jsx as B,jsxs as _e}from"react/jsx-runtime";var Mo=8,un=50,xo=(e,t)=>t===null?e.show:b(e.showcount,f(t)),Qn=({option:e})=>e.count>=0?B("small",{className:"enrol_apply-rview-optcount",children:f(e.count)}):null,Po=(e,t)=>e.options.filter(n=>n.count!==0||n.value===t),Lo=({labels:e,facet:t,shown:n,value:r,headid:o,onChange:l})=>{let[a,p]=qn(""),[c,i]=qn(""),g=a.trim().toLocaleLowerCase(),y=t.groupings.length>1?t.groupings:[],d=y.find(v=>String(v.id)===c)?.groups.map(String)??null,R=n.filter(v=>v.value===r),u=n.filter(v=>v.value!==r&&(g===""||v.label.toLocaleLowerCase().includes(g))&&(d===null||d.includes(v.value))),_=b(t.find.label,f(t.options.length)),C=y.length>0?e.moregroupsgrouping:e.moregroups;return _e(Ao,{children:[_e("div",{className:"enrol_apply-rview-find",children:[B("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:_,"aria-label":_,value:a,onChange:v=>p(v.target.value)}),y.length>0?_e("select",{className:"form-select form-select-sm enrol_apply-rview-grouping","aria-label":e.grouping,value:c,onChange:v=>i(v.target.value),children:[B("option",{value:"",children:e.allgroupings}),y.map(v=>B("option",{value:String(v.id),children:v.name},v.id))]}):null]}),_e("div",{className:"enrol_apply-rview-optlist",role:"group","aria-labelledby":o,children:[[...R,...u.slice(0,un)].map(v=>_e("button",{type:"button",className:"enrol_apply-rview-optrow","aria-pressed":v.value===r,onClick:()=>l(t.name,v.value===r?"":v.value),children:[B("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:B(O,{name:"tick"})}),B("span",{className:"enrol_apply-rview-optrow-label",children:v.label})," ",B(Qn,{option:v})]},v.value)),u.length>un?B("p",{className:"enrol_apply-rview-note",children:b(C,f(u.length-un))}):null,R.length===0&&u.length===0?B("p",{className:"enrol_apply-rview-note",children:t.find.nomatch}):null]})]})},$n=({note:e})=>e?B("p",{className:"enrol_apply-rview-note",children:e}):null,Do=({labels:e,facet:t,value:n,onChange:r})=>{let o=Eo(),l=Po(t,n),a=[{value:"",label:e.any,count:-1},...l];if(t.options.length>Mo)return _e("div",{className:"enrol_apply-rview-fgroup",children:[B("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),B(Lo,{labels:e,facet:t,shown:l,value:n,headid:o,onChange:r}),B($n,{note:t.note})]});let p=c=>{let i=a.findIndex(y=>y.value===n),g=-1;c.key==="ArrowRight"||c.key==="ArrowDown"?g=(i+1)%a.length:c.key==="ArrowLeft"||c.key==="ArrowUp"?g=(i-1+a.length)%a.length:c.key==="Home"?g=0:c.key==="End"&&(g=a.length-1),!(g<0)&&(c.preventDefault(),r(t.name,a[g].value),c.currentTarget.querySelectorAll('[role="radio"]')[g]?.focus())};return _e("div",{className:"enrol_apply-rview-fgroup",children:[B("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),B("div",{className:"enrol_apply-rview-fpanel-opts",role:"radiogroup","aria-labelledby":o,onKeyDown:p,children:a.map(c=>_e("button",{type:"button",className:"enrol_apply-rview-fpanel-opt",role:"radio","aria-checked":c.value===n,tabIndex:c.value===n?0:-1,onClick:()=>r(t.name,c.value),children:[c.label," ",B(Qn,{option:c})]},c.value))}),B($n,{note:t.note})]})},Un=({labels:e,id:t,hidden:n,facets:r,values:o,total:l,note:a,onChange:p,onClear:c,onShow:i})=>_e("div",{className:"enrol_apply-rview-fpanel",id:t,role:"dialog","aria-label":e.filters,hidden:n,children:[_e("div",{className:"enrol_apply-rview-fpanel-body",children:[r.map(g=>B(Do,{labels:e,facet:g,value:o[g.name]??"",onChange:p},g.name)),a??null]}),_e("div",{className:"enrol_apply-rview-fpanel-foot",children:[B("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:c,children:e.clearfilters}),B("button",{type:"button",className:"btn btn-sm btn-primary",onClick:i,children:xo(e,l)})]})]});import{Fragment as dn,jsx as X,jsxs as Ie}from"react/jsx-runtime";var Bt=e=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(e.key))return;let t=Array.from(e.currentTarget.querySelectorAll('[role^="menuitem"]'));if(t.length===0)return;e.preventDefault();let n=t.indexOf(document.activeElement),r=0;e.key==="End"?r=t.length-1:e.key==="ArrowDown"?r=(n+1)%t.length:e.key==="ArrowUp"&&(r=(n-1+t.length)%t.length),t[r].focus()},Kn=({heading:e,sets:t,onSet:n})=>t.length===0?null:Ie(dn,{children:[X("div",{className:"enrol_apply-rview-menu-head",role:"presentation",children:e}),t.map(r=>Ie("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":r.active,tabIndex:-1,"data-preset":r.name,onClick:()=>{r.active||n(r.name)},children:[X("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:X(O,{name:"tick"})}),X("span",{className:"enrol_apply-rview-menu-label",children:r.label})]},r.name)),X("div",{className:"enrol_apply-rview-menu-sep",role:"separator"})]}),Gn=(e,t=[])=>t.find(n=>n.value===e)?.icon??{auto:"auto",table:"table",cards:"cards"}[e],zn=e=>{let{labels:t}=e,n=e.extraViews??[],r=[["auto",t.viewauto,t.viewautohint],["table",t.table,""],["cards",t.cards,""]].filter(([o])=>e.views.includes(o));return Ie("div",{className:"enrol_apply-rview-menu",id:e.id,role:"menu","aria-label":t.view,hidden:e.hidden,onKeyDown:Bt,children:[r.map(([o,l,a])=>Ie("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===o,tabIndex:-1,onClick:()=>e.onChoose(o),children:[X(O,{name:o}),X("span",{className:"enrol_apply-rview-menu-label",children:l}),a!==""?X("span",{className:"enrol_apply-rview-menu-hint",children:a}):null,X(O,{name:"tick",className:"enrol_apply-rview-tick"})]},o)),n.length>0?Ie(dn,{children:[X("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),n.map(o=>Ie("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===o.value,tabIndex:-1,onClick:()=>e.onChoose(o.value),children:[X(O,{name:o.icon}),X("span",{className:"enrol_apply-rview-menu-label",children:o.label}),X(O,{name:"tick",className:"enrol_apply-rview-tick"})]},o.value))]}):null,e.tableurl!==""?Ie(dn,{children:[X("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),Ie("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:e.tableurl,children:[X(O,{name:"table"}),X("span",{className:"enrol_apply-rview-menu-label",children:t.coretable})]})]}):null]})};import{jsx as Yn}from"react/jsx-runtime";var yt=({label:e,value:t,options:n,small:r,onChange:o})=>{let l=a=>{let p=n.findIndex(g=>g.value===t),c=-1;if(a.key==="ArrowRight"||a.key==="ArrowDown"?c=(p+1)%n.length:a.key==="ArrowLeft"||a.key==="ArrowUp"?c=(p-1+n.length)%n.length:a.key==="Home"?c=0:a.key==="End"&&(c=n.length-1),c<0)return;a.preventDefault(),o(n[c].value),a.currentTarget.querySelectorAll('[role="radio"]')[c]?.focus()};return Yn("div",{className:"enrol_apply-rview-seg"+(r?" enrol_apply-rview-seg--small":""),role:"radiogroup","aria-label":e,onKeyDown:l,children:n.map(a=>Yn("button",{type:"button",className:"enrol_apply-rview-seg-cell",role:"radio","aria-checked":a.value===t,tabIndex:a.value===t?0:-1,onClick:()=>o(a.value),children:a.label},a.value))})};import{Fragment as Io,jsx as k,jsxs as Q}from"react/jsx-runtime";var Vo=(e,t)=>"enrol_apply-rview-bar"+(e?" enrol_apply-rview-bar--narrow":"")+(t?" enrol_apply-rview-bar--searching":""),Fo=({labels:e,countkey:t="count",total:n,counted:r,narrow:o})=>{let l=r===void 0?n:r,a=l===null?"\u2026":Vn(e,t,l,f(l));return k("span",{className:"enrol_apply-rview-bar-count","data-region":"enrol_apply-total","aria-live":"polite",children:l!==null&&o?Q(Io,{children:[k("span",{"aria-hidden":"true",children:f(l)}),k("span",{className:"visually-hidden",children:a})]}):a})},$t=e=>{let{labels:t,chips:n,columns:r}=e,o=To(),l=bt(null),a=bt(null),p=bt(null),c=bt(!1),[i,g]=jn(null),[y,d]=jn(e.search!==""),R=bt({});qt(()=>{if(i===null)return;let s=h=>{l.current&&!l.current.contains(h.target)&&g(null)};return document.addEventListener("mousedown",s),()=>document.removeEventListener("mousedown",s)},[i]),qt(()=>{if(i===null||i==="filters")return;let s=l.current?.querySelector(`#${CSS.escape(o+i)}`);(s?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])')??s?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])'))?.focus()},[i,o]),qt(()=>{y?a.current?.focus():c.current&&(c.current=!1,p.current?.focus())},[y]),qt(()=>{let s=h=>{if(h.key!=="/"||h.ctrlKey||h.metaKey||h.altKey||h.defaultPrevented)return;let T=document.activeElement;if(T&&(T.isContentEditable||T.closest("input, textarea, select")))return;let F=l.current?.getBoundingClientRect();!F||F.width===0||F.bottom<=0||F.top>=window.innerHeight||(h.preventDefault(),y?a.current?.focus():(g(null),d(!0)))};return document.addEventListener("keydown",s),()=>document.removeEventListener("keydown",s)},[y]);let u=s=>g(i===s?null:s),_=s=>{c.current=s,d(!1)},C=s=>{if(!(s.key!=="Escape"||s.defaultPrevented))if(i!==null){s.preventDefault();let h=R.current[i];g(null),h?.focus()}else y&&(s.preventDefault(),_(!0))},v=e.search.trim().length>=e.searchmin,q=v&&!y,z=n.length+(q?1:0),L=(s,h,T,F)=>Q("button",{type:"button",className:"enrol_apply-rview-iconbtn","aria-label":T,"data-enrol-apply-tip":T,"aria-haspopup":s==="filters"?"dialog":"menu","aria-expanded":i===s,"aria-controls":o+s,ref:I=>{R.current[s??""]=I},onClick:()=>u(s),children:[k(O,{name:h}),F]}),fe=Gn(e.view,e.extraViews),Z=k(O,{name:"chevron",className:"enrol_apply-rview-chevron"}),K=n.length>0?b(t.filterson,f(n.length)):t.filters,se=n.length>0?k("span",{className:"enrol_apply-rview-badge","aria-hidden":"true",children:f(n.length)}):null;return Q("div",{className:Vo(e.narrow,y),"data-region":"enrol_apply-bar",ref:l,onKeyDown:C,children:[k(yt,{label:t.scope,value:e.scope,options:e.scopes,onChange:e.onScope}),k("span",{className:"enrol_apply-rview-bar-spacer"}),Q("div",{className:"enrol_apply-rview-bar-controls",children:[Q("div",{className:"enrol_apply-rview-bar-cluster",children:[k(Fo,{labels:t,countkey:e.countkey,total:e.total,counted:e.counted,narrow:e.narrow}),Q("div",{className:"enrol_apply-rview-capsule",children:[L("filters","filter",K,se),Q("div",{className:"enrol_apply-rview-anchor",hidden:e.hidecolumns,children:[L("columns","columns",t.columns,Z),Q("div",{className:"enrol_apply-rview-menu",id:o+"columns",role:"menu","aria-label":t.columns,hidden:i!=="columns",onKeyDown:Bt,children:[k(Kn,{heading:t.presets,sets:e.sets,onSet:e.onSet}),r.map(s=>s.fixed?Q("div",{className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":"true","aria-disabled":"true",tabIndex:-1,children:[k("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:k(O,{name:"tick"})}),k("span",{className:"enrol_apply-rview-menu-label",children:s.title}),k("span",{className:"enrol_apply-rview-menu-hint",children:t.always})]},s.key):Q("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":s.active,tabIndex:-1,onClick:()=>e.onColumn(s.key,!s.active),children:[k("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:k(O,{name:"tick"})}),k("span",{className:"enrol_apply-rview-menu-label",children:s.title})]},s.key)),k("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),Q("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":e.usedefault,onClick:()=>{e.usedefault||e.onDefaultColumns()},children:[k(O,{name:"columns"}),k("span",{className:"enrol_apply-rview-menu-label",children:t.defaultcolumns})]}),Q("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":!e.haswidths,onClick:()=>{e.haswidths&&e.onResetWidths()},children:[k(O,{name:"reset"}),k("span",{className:"enrol_apply-rview-menu-label",children:t.resetwidths})]})]})]}),Q("div",{className:"enrol_apply-rview-anchor",children:[L("view",fe,t.view,Z),k(zn,{id:o+"view",labels:t,hidden:i!=="view",view:e.view,views:e.views,extraViews:e.extraViews,tableurl:e.tableurl,onChoose:s=>{e.onView(s),g(null),R.current.view?.focus()}})]}),e.downloads.length>0?Q("div",{className:"enrol_apply-rview-anchor",children:[L("download","download",t.download,Z),k("div",{className:"enrol_apply-rview-menu",id:o+"download",role:"menu","aria-label":t.download,hidden:i!=="download",onKeyDown:Bt,children:e.downloads.map(s=>Q("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:s.href,children:[k(O,{name:"download"}),k("span",{className:"enrol_apply-rview-menu-label",children:s.label})]},s.key))})]}):null]})]}),k("div",{className:"enrol_apply-rview-capsule enrol_apply-rview-searchcap",children:y?Q("span",{className:"enrol_apply-rview-search",children:[e.searching&&v?k("span",{className:"enrol_apply-rview-spin","aria-hidden":"true"}):k(O,{name:"search"}),k("input",{ref:a,type:"search","aria-label":t.search,placeholder:t.search,autoComplete:"off",value:e.search,onChange:s=>e.onSearch(s.target.value),onKeyDown:s=>{s.key==="Escape"&&(s.preventDefault(),_(!0))},onBlur:()=>{e.search===""&&_(!1)}}),e.search!==""?k("button",{type:"button",className:"enrol_apply-rview-eraser","aria-label":t.cleartext,"data-enrol-apply-tip":t.cleartext,onMouseDown:s=>s.preventDefault(),onClick:()=>{e.onSearch(""),a.current?.focus()},children:k(O,{name:"eraser"})}):null]}):k("button",{ref:p,type:"button",className:"enrol_apply-rview-iconbtn","aria-label":t.search,"data-enrol-apply-tip":t.search,onClick:()=>{g(null),d(!0)},children:k(O,{name:"search"})})}),k(Un,{labels:t,id:o+"filters",hidden:i!=="filters",facets:e.facets,values:e.values,total:e.total,note:e.panelnote,onChange:e.onFacet,onClear:e.onClearFilters,onShow:()=>{g(null),R.current.filters?.focus()}})]}),Q("div",{className:"enrol_apply-rview-chips","data-region":"enrol_apply-chips",hidden:z===0&&!e.aside,children:[n.map(s=>Q("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":b(t.removefilter,s.text),onClick:()=>e.onRemoveChip(s.key),children:[k("span",{children:s.text}),k(O,{name:"close"})]},s.key)),q?Q("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":b(t.removefilter,b(t.searchchip,e.search.trim())),onClick:()=>e.onSearch(""),children:[k("span",{children:b(t.searchchip,e.search.trim())}),k(O,{name:"close"})]}):null,z>1?k("button",{type:"button",className:"enrol_apply-rview-clearall",onClick:()=>{e.onClearAll(),d(!1)},children:t.clearall}):null,e.aside??null]})]})};import{jsx as pn,jsxs as $o}from"react/jsx-runtime";var Rt="all",dt="group",Ho=["auto","table","cards"],Oo=(e,t)=>{switch(t){case"everything":return e.preseteverything;case"glance":return e.presetglance;case"answers":return e.presetanswers;default:return t}},Zn=(e,t)=>{switch(t){case"course":return{label:e.searchcourses,nomatch:e.nocoursematch};case"category":return{label:e.searchcategories,nomatch:e.nocategorymatch};default:return{label:e.searchgroups,nomatch:e.nogroupmatch}}},Wo=(e,t)=>t.omitted<=0?"":b(t.name==="category"?e.omittedcategories:e.omittedcourses,f(t.omitted)),Bo=(e,t)=>{let n=t.facets.map(l=>({name:l.name,title:l.title,options:l.options,groupings:[],find:Zn(e,l.name),note:Wo(e,l)})),r=Zn(e,""),o=t.fieldfilters.filter(l=>l.control==="select").map(l=>({name:l.name,title:l.title,options:l.options.map(a=>({...a,count:-1})),groupings:[],find:r}));return[...n,...o]},qo=(e,t,n,r)=>{let o=n,l=r,a=t.facets.find(c=>c.name===n),p=t.fieldfilters.find(c=>c.name===n);return a?(o=a.title,l=a.options.find(c=>c.value===r)?.label??r):p?(o=p.title,l=p.options.find(c=>c.value===r)?.label??r):n==="appliedfrom"?o=e.appliedfrom:n==="appliedto"&&(o=e.appliedto),{key:n,text:J(e.filterchip,{title:o,value:l})}},Xn=e=>{let{labels:t,rows:n,query:r}=e,o=e.methods!==void 0,l=e.presets.map(c=>({name:c.name,label:Oo(t,c.name),active:c.active})),a=Object.fromEntries(Object.entries(r.filters).map(([c,i])=>[c,c===dt&&i===Rt?"":i])),p=n===null?[]:n.filters.map(c=>qo(t,n,c.name,c.value));return pn($t,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"pending",label:t.pending},{value:"deferred",label:t.deferred}],scope:r.scope,total:n===null?null:n.total,facets:n===null?[]:Bo(t,n),values:a,panelnote:$o("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",pn("a",{href:e.tableurl,children:t.morefilters})]}),chips:p,aside:e.reporturl!==""?pn("a",{className:"enrol_apply-qview-tolink",href:e.reporturl,children:t.openreport}):null,columns:e.columns,sets:l,usedefault:e.usedefault,haswidths:e.haswidths,view:o?r.show:e.view,views:Ho,extraViews:e.extraViews,hidecolumns:o,countkey:o?"methods":"count",counted:e.methods,tableurl:e.tableurl,downloads:[],search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:c=>e.onScope(c),onSearch:e.onSearch,onFacet:e.onFilter,onRemoveChip:c=>e.onFilter(c,""),onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:c=>{let i=e.presets.find(g=>g.name===c);i&&e.onPreset(i)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Qo,jsx as Jn}from"react/jsx-runtime";var er=(e,t)=>t?e.split(`
`).map(n=>n.trim()).filter(n=>n!==""):[e],Qt=({parts:e})=>Jn(Qo,{children:e.map((t,n)=>Jn("span",{className:n===0?"enrol_apply-rview-c":"enrol_apply-rview-sub",title:It(t),dangerouslySetInnerHTML:{__html:t}},n))});import{Fragment as ta,jsx as W,jsxs as pt}from"react/jsx-runtime";var Uo={applicant:320,course:240,wait:150,group:180,comment:260,details:260,history:130},Ko=180,Go={applicant:200},zo=80,Yo=800,Ut=lt(Uo,Ko,Go,zo,Yo),jo={deferred:"enrol_apply-status--warning",key:"enrol_apply-status--info",appliedbefore:"enrol_apply-status--neutral",own:"enrol_apply-status--neutral",deciding:"enrol_apply-status--warning",full:"enrol_apply-status--neutral"},Xe=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",mn=({html:e})=>W(Qt,{parts:[e]}),tr=({tag:e})=>{let t=jo[e.kind]??"enrol_apply-status--neutral",n=e.title!==""&&e.title!==e.text;return pt("span",{className:"enrol_apply-status "+t,title:n?e.title:void 0,children:[e.text,n?W("span",{className:"visually-hidden",children:" "+e.title}):null]})},Zo=({row:e})=>e.applicant.profileurl===""?W("span",{className:"enrol_apply-qview-name",children:e.applicant.fullname}):W("a",{className:"enrol_apply-qview-name",href:e.applicant.profileurl,children:e.applicant.fullname}),Xo=({labels:e,row:t})=>W("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:t.reviewurl,"aria-label":b(e.reviewapplicant,t.applicant.fullname),children:e.review}),Jo=({row:e})=>pt(ta,{children:[W("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:e.applicant.picture}}),pt("div",{className:"enrol_apply-qview-whotext",children:[W("span",{className:"enrol_apply-rview-c",children:W(Zo,{row:e})}),e.applicant.identity.map(t=>W("span",{className:"enrol_apply-rview-sub",title:t.value,children:t.value},t.name)),e.applicant.tags.length>0?W("span",{className:"enrol_apply-qview-tags",children:e.applicant.tags.map(t=>W(tr,{tag:t},t.kind))}):null]})]}),wn=({labels:e,row:t})=>pt("div",{className:"enrol_apply-qview-who",children:[W(Jo,{row:t}),W(Xo,{labels:e,row:t})]}),gn=({labels:e,row:t})=>{let n=[];return t.method.deciding&&n.push({kind:"deciding",text:e.grantsdeciding,title:""}),t.method.full&&n.push({kind:"full",text:e.methodfull,title:e.methodfullwarn}),n.length===0?null:W("span",{className:"enrol_apply-qview-tags",children:n.map(r=>W(tr,{tag:r},r.kind))})},ea=({row:e})=>e.method.queueurl===""?W("span",{children:e.method.name}):W("a",{href:e.method.queueurl,children:e.method.name}),_t=({labels:e,row:t})=>pt("div",{className:"enrol_apply-qview-course",children:[W("span",{className:"enrol_apply-rview-c",title:t.course.name,children:W("a",{className:"enrol_apply-qview-coursename",href:t.course.url,children:t.course.name})}),pt("div",{className:"enrol_apply-qview-methodline",children:[W("span",{className:"enrol_apply-rview-sub",title:t.method.name,children:W(ea,{row:t})}),W(gn,{labels:e,row:t})]})]});var He=(e,t,n)=>{let r=document.createElement("input");r.type="hidden",r.name=t,r.value=n,e.appendChild(r)},nr=e=>{let t=document.createElement("form");t.method="post",t.action=e.action,t.hidden=!0,He(t,"sesskey",e.sesskey),He(t,"formaction",e.decision),e.userenrolments.forEach(n=>He(t,"userenrolments[]",String(n))),He(t,"outcomemessage",e.messages===null?e.message:""),Object.entries(e.messages??{}).forEach(([n,r])=>He(t,`outcomemessages[${n}]`,r)),He(t,"decisionnote",e.note),e.decision==="confirm"&&e.messages===null&&(e.groups?.forEach(n=>He(t,"groups[]",String(n))),e.roleid!==null&&He(t,"roleid",String(e.roleid))),document.body.appendChild(t),t.submit()};var rr=(e,t)=>{let n=t.decision!==null&&e!==null&&e.candecide;return{decides:n,notingroup:!!e?.notingroup,narrowed:!!e?.narrowed,viewonly:e!==null&&!n,groupdefault:e?.groupdefault??"",allcolumns:e?.columns??[],usedefault:e?.preference.usedefault??!0,presets:e?.presets??[],total:e?.total??0,list:(e?.rows??[]).map(r=>({...r,id:r.userenrolmentid}))}},or=(e,t,n,r)=>{let{list:o,decides:l}=n,a=new Set(o.filter(d=>!d.selectable).map(d=>d.id)),p=o.filter(d=>e.has(d.id)),c=(d,R)=>t(u=>{let _=new Set(u);return R&&!a.has(d)?_.add(d):_.delete(d),_}),i=d=>t(()=>d?new Set(o.filter(R=>!a.has(R.id)).map(R=>R.id)):new Set),y=l?{selected:e,locked:a,label:d=>b(r.selectapplicant,d.applicant.fullname),pagelabel:r.selectpage,onSelect:c,onSelectPage:i}:void 0;return{locked:a,chosen:p,selection:y}};import{useCallback as aa,useEffect as la,useRef as fn,useState as Gt}from"react";import{fetchOne as Kt}from"@moodle/lms/core/ajax";var ar=(e,t)=>Kt({methodname:"enrol_apply_queue_rows",args:{enrolid:e,scope:t.scope,search:t.search,filters:Object.entries(t.filters).map(([n,r])=>({name:n,value:r})),page:t.page,perpage:t.perpage,sort:t.sort,direction:t.direction}}),lr=e=>Kt({methodname:"enrol_apply_queue_methods",args:{scope:e.scope,search:e.search,filters:Object.entries(e.filters).map(([t,n])=>({name:t,value:n})),page:e.page,perpage:e.perpage,sort:e.sort,direction:e.direction}}),sr=e=>Kt({methodname:"enrol_apply_queue_summary",args:{enrolid:e}}),ir=(e,t)=>Kt({methodname:"enrol_apply_set_queue_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}});import{Fragment as oa,jsx as U,jsxs as Je}from"react/jsx-runtime";var na={method:4,site:4,mentees:2},Nt=(e,t)=>Math.min(100,Math.max(0,Math.round(e*100/t))),et=({label:e,value:t,sub:n,meter:r,warn:o})=>Je("div",{className:"enrol_apply-rview-tile"+(o?" enrol_apply-rview-tile--warn":""),role:"listitem",children:[U("div",{className:"enrol_apply-rview-tile-label",children:e}),U("div",{className:"enrol_apply-rview-tile-value",children:t}),r!==null?U("span",{className:"enrol_apply-qview-meter"+(o?" enrol_apply-qview-meter--warn":""),"aria-hidden":"true",children:U("span",{style:{width:`${r}%`}})}):null,n!==""?U("div",{className:"enrol_apply-rview-tile-sub",children:n}):null]}),hn=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.DateTimeFormat(t,{dateStyle:"short"}).format(new Date(e*1e3))}catch{return new Date(e*1e3).toLocaleDateString()}},cr=({labels:e,summary:t})=>{let n=t.awaiting>0?b(e.oldest,xe(e,t.oldestwait)):"",r=t.late>0?b(e.late,f(t.late)):"";return U(et,{label:e.awaiting,value:f(t.awaiting),sub:[n,r].filter(o=>o!=="").join(" \xB7 "),meter:null,warn:t.late>0})},ra=({labels:e,summary:t})=>Je("div",{className:"enrol_apply-rview-band",role:"list","data-region":"enrol_apply-tiles",children:[U(cr,{labels:e,summary:t}),U(et,{label:e.deferred,value:f(t.deferred),sub:t.awaiting>0?b(e.deferredshare,f(Nt(t.deferred,t.awaiting))):"",meter:null,warn:!1}),t.sitefigures?Je(oa,{children:[U(et,{label:e.courses,value:f(t.courses),sub:t.methods===1?e.methods_one:b(e.methods,f(t.methods)),meter:null,warn:!1}),U(et,{label:e.methodsfull,value:f(t.methodsfull),sub:e.methodsfullsub,meter:null,warn:t.methodsfull>0})]}):null]}),ur=({labels:e,kind:t,summary:n,error:r,onRetry:o})=>{if(n===null&&r)return Je("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",U("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:o,children:e.retry})]});if(n===null)return Je("div",{className:"enrol_apply-rview-band","aria-busy":"true",children:[Array.from({length:na[t]},(a,p)=>Je("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[U("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),U("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"})]},p)),U("span",{className:"visually-hidden",role:"status",children:e.loadsummary})]});if(t!=="method")return U(ra,{labels:e,summary:n});let l=n.narrowed?e.methodnote:"";return Je("div",{className:"enrol_apply-rview-band",role:"list","data-region":"enrol_apply-tiles",children:[n.places>0?U(et,{label:e.placestaken,value:J(e.ofmany,{taken:f(n.placestaken),total:f(n.places)}),sub:l,meter:Nt(n.placestaken,n.places),warn:!1}):null,n.applicantlimit>0?U(et,{label:e.held,value:J(e.ofmany,{taken:f(n.applicants),total:f(n.applicantlimit)}),sub:l,meter:Nt(n.applicants,n.applicantlimit),warn:n.applicants*5>=n.applicantlimit*4}):null,U(cr,{labels:e,summary:n}),U(et,{label:e.status,value:n.open?e.open:e.closed,sub:n.open&&n.closes>0?b(e.closes,hn(n.closes)):"",meter:null,warn:!1})]})};import{Fragment as vn,jsx as D,jsxs as Oe}from"react/jsx-runtime";var sa={course:300,awaiting:170,deferred:120,oldest:150,places:220,status:190,queue:170},dr=lt(sa,150,{course:200},80,800),pr={course:"asc",awaiting:"desc",oldest:"asc"},ia=e=>[["course",e.coursemethod],["awaiting",e.awaiting],["deferred",e.deferred],["oldest",e.oldestwait],["places",e.placestaken],["status",e.status],["queue",e.queue]].map(([t,n])=>({key:t,title:n,sortable:t in pr,default:!0,active:!0,fixed:t==="course"})),mr=e=>{let[t,n]=Gt(null),[r,o]=Gt(!1),[l,a]=Gt(!1),p=fn(0),c=fn(null),i=fn(e);i.current=e;let g=aa(y=>{let d=++p.current;c.current||(c.current=ee("enrol_apply/queue:methods")),o(!0),lr(y).then(R=>{d===p.current&&(R.page!==y.page&&i.current(R.page),n(R),a(!1),o(!1))}).catch(()=>{d===p.current&&(a(!0),o(!1))})},[]);return la(()=>{!r&&c.current&&(c.current(),c.current=null)},[r,t,l]),{answer:t,loading:r,error:l,load:g}},wr=(e,t)=>e!==null&&e.sort===t?e.direction==="asc"?"desc":"asc":pr[t]??"asc",ca=({labels:e,row:t})=>t.capacityknown?t.places<=0?D("span",{className:"enrol_apply-rview-c",children:b(e.takennolimit,f(t.placestaken))}):Oe(vn,{children:[D("span",{className:"enrol_apply-rview-c",children:J(e.ofmany,{taken:f(t.placestaken),total:f(t.places)})}),D("span",{className:"enrol_apply-qview-meter"+(t.placesfull?" enrol_apply-qview-meter--warn":""),"aria-hidden":"true",children:D("span",{style:{width:`${Nt(t.placestaken,t.places)}%`}})})]}):Oe("span",{className:"enrol_apply-rview-c",children:[D("span",{"aria-hidden":"true",children:"\u2013"}),D("span",{className:"visually-hidden",children:e.capacityhidden})]}),ua=({labels:e,row:t})=>t.capacityknown?t.placesfull||t.limitreached?Oe("span",{className:"enrol_apply-status enrol_apply-status--neutral",title:e.methodfullwarn,children:[e.methodfull,D("span",{className:"visually-hidden",children:" "+e.methodfullwarn})]}):t.open?Oe(vn,{children:[D("span",{className:"enrol_apply-rview-c",children:e.open}),t.closes>0?D("span",{className:"enrol_apply-rview-sub",children:b(e.closes,hn(t.closes))}):null]}):D("span",{className:"enrol_apply-rview-c",children:e.closed}):D("span",{className:"visually-hidden",children:e.capacityhidden}),gr=({labels:e,row:t})=>t.method.queueurl===""?null:Oe("a",{className:"btn btn-sm btn-outline-secondary",href:t.method.queueurl,children:[e.openqueue,D("span",{className:"visually-hidden",children:" "+t.method.name})]}),hr=({labels:e,row:t})=>D(_t,{labels:e,row:{course:t.course,method:{...t.method,full:!1}}}),fr=({labels:e,row:t,column:n})=>{switch(n){case"awaiting":return D("span",{className:"enrol_apply-qview-figure","data-figure":"awaiting",children:f(t.awaiting)});case"deferred":return D("span",{className:"enrol_apply-qview-figure","data-figure":"deferred",children:f(t.deferred)});case"oldest":return D("span",{className:"enrol_apply-rview-c",children:xe(e,t.oldestwait)});case"places":return D(ca,{labels:e,row:t});case"status":return D(ua,{labels:e,row:t});default:return D(gr,{labels:e,row:t})}},da=({labels:e,columns:t,rows:n,single:r})=>D(it,{labels:e,rows:n,single:r,renderCard:o=>({head:Oe("div",{className:"enrol_apply-qview-methodhead",children:[D(hr,{labels:e,row:o}),D(gr,{labels:e,row:o})]}),lines:D("dl",{className:"enrol_apply-rview-card-list",children:t.filter(l=>!["course","queue"].includes(l.key)).map(l=>Oe("div",{className:"enrol_apply-rview-card-item",children:[D("dt",{children:l.title}),D("dd",{children:D(fr,{labels:e,row:o,column:l.key})})]},l.key))}),fields:[]})}),vr=e=>{let{labels:t,answer:n,narrow:r}=e,[o,l]=Gt({}),a=ia(t),p=n===null?[]:n.rows.map(i=>({...i,id:i.method.id})),c=(i,g)=>{let y={...o};g===null?delete y[i]:y[i]=g,l(y)};return Oe(vn,{children:[D(ut,{labels:t,loading:e.loading,loaded:n!==null,error:e.error,total:n===null?0:n.total,filtered:e.filtered,cards:r,narrow:r,skeleton:{columns:a,lead:"course",widths:o,sizing:dr},onRetry:e.onRetry,children:r?D(da,{labels:t,columns:a,rows:p,single:r}):D(st,{labels:t,columns:a,lead:"course",rows:p,widths:o,sizing:dr,sort:n?.sort??"",direction:n?.direction??"desc",renderLead:i=>D(hr,{labels:t,row:i}),renderCell:(i,g)=>D(fr,{labels:t,row:i,column:g.key}),onSort:e.onSort,onWidth:c,onWidthCommit:()=>{}})}),n!==null&&n.total>0?D(at,{labels:t,page:n.page,perpage:n.perpage,perpages:e.perpages,total:n.total,shown:n.rows.length,narrow:r,onPage:e.onPage,onPerpage:e.onPerpage}):null]})};import{jsx as yn,jsxs as yr}from"react/jsx-runtime";var br=({labels:e,count:t,methods:n,actions:r,onDecide:o})=>yr("div",{className:"enrol_apply-qview-dock",role:"region","aria-label":e.dock,"data-region":"enrol_apply-dock",children:[yr("span",{className:"enrol_apply-qview-dockcount","aria-live":"polite",children:[b(e.selected,f(t)),n>1?yn("small",{className:"enrol_apply-qview-dockmethods",children:" "+b(e.dockmethods,f(n))}):null]}),yn("div",{className:"enrol_apply-qview-dockactions",children:r.map(l=>yn("button",{type:"button",className:"btn "+l.classes,onClick:a=>o(l.value,a.currentTarget),children:l.label},l.value))})]});import{useEffect as ma,useId as wa,useRef as Sr,useState as nt}from"react";import{Fragment as kr,jsx as te,jsxs as tt}from"react/jsx-runtime";var Rr=5,_r=e=>{let t=[];return e.forEach(n=>{let r=t.find(o=>o.id===n.method.id);r?r.rows.push(n):t.push({id:n.method.id,rows:[n]})}),t},bn=({labels:e,rows:t})=>{let n=t.length-Rr;return tt("ul",{className:"enrol_apply-qview-sheetwho",children:[t.slice(0,Rr).map(r=>tt("li",{children:[te("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:r.applicant.picture}}),te("span",{children:r.applicant.fullname})]},r.id)),n>0?te("li",{className:"enrol_apply-qview-sheetmore",children:b(e.andmore,f(n))}):null]})},pa=({labels:e,id:t,group:n,approval:r,message:o,onMessage:l})=>{let a=n.rows[0],p=n.rows.length;return tt("section",{className:"enrol_apply-qview-sheetmethod","aria-labelledby":`${t}-head`,children:[tt("div",{className:"enrol_apply-qview-sheetmethodhead",id:`${t}-head`,children:[te("strong",{children:a.course.name}),te("span",{children:" \xB7 "+a.method.name})," ",te(gn,{labels:e,row:a})]}),te("div",{className:"enrol_apply-qview-sheetline",children:p===1?e.count_one:b(e.count,f(p))}),r&&a.method.full?te("div",{className:"alert alert-warning",children:e.methodfullwarn}):null,te(bn,{labels:e,rows:n.rows}),te("label",{className:"form-label",htmlFor:`${t}-message`,children:e.sheetmessage}),te("textarea",{className:"form-control",id:`${t}-message`,rows:2,value:o,"aria-describedby":`${t}-head`,onChange:c=>l(n.id,c.target.value)}),a.method.queueurl!==""?tt("p",{className:"enrol_apply-qview-sheetopen",children:[te("a",{href:a.method.queueurl,children:e.openmethod}),r?tt(kr,{children:[" ",te("span",{className:"enrol_apply-qview-sheetline",children:e.openmethodline})]}):null]}):null]})},Nr=({labels:e,id:t,groups:n,approval:r,messages:o,onMessage:l})=>tt(kr,{children:[te("div",{className:"alert alert-info",children:b(e.spans,f(n.length))}),n.map((a,p)=>te(pa,{labels:e,id:`${t}-method${p}`,group:a,approval:r,message:o[a.id]??"",onMessage:l},a.id))]});import{Fragment as zt,jsx as A,jsxs as ne}from"react/jsx-runtime";var ga="button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), a[href]",ha=(e,t,n)=>{let r=f(n);return t==="cancel"?[b(n===1?e.sheetcancelone:e.sheetcancel,r),b(e.sheetcancelcommit,r)]:t==="wait"?[b(n===1?e.sheetwaitone:e.sheetwait,r),b(e.sheetwaitcommit,r)]:[b(n===1?e.sheetconfirmone:e.sheetconfirm,r),b(e.sheetconfirmcommit,r)]},Rn=({id:e,text:t})=>A("div",{className:"form-text",id:e,children:t}),Cr=({id:e,label:t,help:n,value:r,last:o,onChange:l})=>ne("div",{className:o?"mb-0":"mb-3",children:[A("label",{className:"form-label",htmlFor:e,children:t}),A("textarea",{className:"form-control",id:e,rows:3,value:r,"aria-describedby":`${e}help`,onChange:a=>l(a.target.value)}),A(Rn,{id:`${e}help`,text:n})]}),fa=({labels:e,decision:t,offer:n})=>ne(zt,{children:[t==="cancel"?A("div",{className:"alert alert-danger",children:e.sheetcanceldanger}):null,t==="wait"?A("p",{className:"enrol_apply-qview-sheetline",children:e.sheetwaitline}):null,t==="confirm"&&n.placesfull!==""?A("div",{className:"alert alert-warning",children:n.placesfull}):null]}),va=({id:e,offer:t,groups:n,setGroups:r,roleid:o,setRoleid:l})=>ne(zt,{children:[t.summary?ne("div",{className:"enrol_apply-qview-sheetsummary",children:[ne("dl",{className:"row mb-0",children:[A("dt",{className:"col-sm-4",children:t.summary.groupslabel}),A("dd",{className:"col-sm-8",children:t.summary.groups}),A("dt",{className:"col-sm-4",children:t.summary.rolelabel}),A("dd",{className:"col-sm-8",children:t.summary.role})]}),ne("div",{className:"form-text",children:[t.summary.help,t.summary.note!==""?" "+t.summary.note:""]})]}):null,t.groups?ne("div",{className:"mb-3",children:[A("label",{className:"form-label",htmlFor:`${e}-groups`,children:t.groups.label}),A("select",{className:"form-select",id:`${e}-groups`,multiple:!0,value:n,"aria-describedby":`${e}-groupshelp`,onChange:a=>r(Array.from(a.target.selectedOptions,p=>p.value)),children:t.groups.options.map(a=>A("option",{value:String(a.id),children:a.name},a.id))}),A(Rn,{id:`${e}-groupshelp`,text:t.groups.help})]}):null,t.roles?ne("div",{className:"mb-3",children:[A("label",{className:"form-label",htmlFor:`${e}-role`,children:t.roles.label}),ne("select",{className:"form-select",id:`${e}-role`,value:o,"aria-describedby":`${e}-rolehelp`,onChange:a=>l(a.target.value),children:[A("option",{value:"0",children:t.roles.default}),t.roles.options.map(a=>A("option",{value:String(a.id),children:a.name},a.id))]}),A(Rn,{id:`${e}-rolehelp`,text:t.roles.help})]}):null]}),ya=(e,t,n,r)=>e.some(o=>o!=="")||t!=="0"||n.length!==r.length||n.some(o=>!r.includes(o)),ba=(e,t,n)=>{if(!t)return;let r=Array.from(t.querySelectorAll(ga));if(r.length===0){e.preventDefault();return}let o=r[0],l=r[r.length-1],a=document.activeElement;e.shiftKey&&(a===o||a===n)?(e.preventDefault(),l.focus()):!e.shiftKey&&(a===l||a===n)&&(e.preventDefault(),o.focus())},Er=({labels:e,decision:t,rows:n,offer:r,onClose:o,onCommit:l})=>{let a=wa(),p=Sr(null),c=Sr(null),[i,g]=nt(""),[y,d]=nt({}),[R,u]=nt(""),[_]=nt(()=>(r.groups?.options??[]).filter(V=>V.selected).map(V=>String(V.id))),[C,v]=nt(_),[q,z]=nt("0"),[L,fe]=nt(!1),[Z,K]=ha(e,t,n.length),se=t==="confirm",s=_r(n),h=s.length>1;ma(()=>(c.current?.focus(),document.body.classList.add("modal-open"),()=>document.body.classList.remove("modal-open")),[]);let T=V=>{if(V.key==="Escape"){V.preventDefault(),V.stopPropagation(),L||o();return}V.key==="Tab"&&ba(V,p.current,c.current)},F=()=>{if(L)return;fe(!0);let V=se&&!h;l({message:h?"":i,note:R,messages:h?Object.fromEntries(s.map(ae=>[ae.id,y[ae.id]??""])):null,groups:V&&r.groups?C.map(Number):null,roleid:V&&r.roles?Number(q):null})},I=ya([i,R,...Object.values(y)],q,C,_);return ne(zt,{children:[A("div",{className:"modal-backdrop show"}),A("div",{className:"modal show d-block enrol_apply-qview-sheet",role:"presentation",onMouseDown:V=>{V.target===V.currentTarget&&!L&&!I&&o()},children:A("div",{className:"modal-dialog modal-lg modal-dialog-scrollable",children:ne("div",{className:"modal-content",role:"dialog","aria-modal":"true","aria-labelledby":`${a}-title`,tabIndex:-1,ref:p,onKeyDown:T,"data-region":"enrol_apply-sheet",children:[ne("div",{className:"modal-header",children:[A("h5",{className:"modal-title",id:`${a}-title`,tabIndex:-1,ref:c,children:Z}),A("button",{type:"button",className:"btn-close","aria-label":e.close,onClick:o,disabled:L})]}),ne("div",{className:"modal-body",children:[A(fa,{labels:e,decision:t,offer:r}),h?A(Nr,{labels:e,id:a,groups:s,approval:se,messages:y,onMessage:(V,ae)=>d(ve=>({...ve,[V]:ae}))}):ne(zt,{children:[A(bn,{labels:e,rows:n}),se?A(va,{id:a,offer:r,groups:C,setGroups:v,roleid:q,setRoleid:z}):null,A(Cr,{id:`${a}-message`,label:r.message.label,help:r.message.help,value:i,last:!1,onChange:g})]}),A(Cr,{id:`${a}-note`,label:r.note.label,help:r.note.help,value:R,last:!0,onChange:u})]}),ne("div",{className:"modal-footer",children:[A("button",{type:"button",className:"btn btn-secondary",onClick:o,disabled:L,children:e.back}),A("button",{type:"button",className:"btn "+(t==="cancel"?"btn-danger":"btn-primary"),onClick:F,disabled:L,children:K})]})]})})})]})};import{Fragment as Dr,jsx as j,jsxs as Ar}from"react/jsx-runtime";var Ra=["applicant","course","wait","group"],_a=(e,t)=>e.filter(n=>!Ra.includes(n.key)&&Xe(t,n.key).trim()!=="").map(n=>({key:n.key,title:n.title,content:j(mn,{html:Xe(t,n.key)})})),Mr=({labels:e,narrowed:t,viewonly:n})=>Ar(Dr,{children:[t?j("p",{className:"enrol_apply-rview-narrowed",children:e.narrowed}):null,n?j("div",{className:"alert alert-info",role:"status",children:e.viewonly}):null]}),Na=({labels:e,row:t,column:n})=>n==="course"?j(_t,{labels:e,row:t}):j(mn,{html:Xe(t,n)}),ka=({labels:e,row:t,shown:n})=>{let r=o=>n.some(l=>l.key===o);return Ar(Dr,{children:[r("course")?j("div",{className:"enrol_apply-rview-card-line",children:j(_t,{labels:e,row:t})}):null,r("wait")?j("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--open",dangerouslySetInnerHTML:{__html:Xe(t,"wait")}}):null,r("group")&&Xe(t,"group")!==""?j("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",dangerouslySetInnerHTML:{__html:Xe(t,"group")}}):null]})},xr=e=>{let{labels:t,list:n,shown:r,cards:o,narrow:l,selection:a}=e;return o?j(it,{labels:t,rows:n,single:l,selection:a,renderCard:p=>({head:j(wn,{labels:t,row:p}),lines:j(ka,{labels:t,row:p,shown:r}),fields:_a(r,p)})}):j(st,{labels:t,columns:r,lead:"applicant",rows:n,widths:e.widths,sizing:Ut,sort:e.sort,direction:e.direction,selection:a,renderLead:p=>j(wn,{labels:t,row:p}),renderCell:(p,c)=>j(Na,{labels:t,row:p,column:c.key}),onSort:e.onSort,onWidth:e.onWidth,onWidthCommit:e.onWidthCommit})},Pr=(e,t,n,r,o)=>t!==null&&n&&r.length>0?j(br,{labels:e,count:r.length,methods:new Set(r.map(l=>l.method.id)).size,actions:t.actions,onDecide:o}):null,Lr=(e,t,n,r,o,l)=>t!==null&&n!==null&&r.length>0?j(Er,{labels:e,decision:n,rows:r,offer:t,onClose:o,onCommit:l}):null;import{Fragment as Fr,jsx as We,jsxs as _n}from"react/jsx-runtime";var Sa=300,Ca=500,Tr="enrol_apply_queue_view",Ea={method:["applicant","wait","group","comment"],site:["applicant","course","wait","details"],mentees:["applicant","course","wait","comment"]},Ma="method",xa="course",Pa=(e,t)=>e.show===Re?{...e,sort:"",direction:"asc",page:0,perpage:t[0]}:e,Vr=e=>e.scope!=="all"||e.search!==""||Object.entries(e.filters).some(([t,n])=>n!==""&&!(t===dt&&n===Rt)),La=e=>{let t=re(e).current,{labels:n,enrolid:r}=t,o=re(null),l=Tt(o),[a,p]=de(()=>Vt(Tr)),[c]=de(()=>On(t)),i=re(c),[g,y]=de(c),[d,R]=de(c.search),[u,_]=de(null),[C,v]=de(!0),[q,z]=de(!1),[L,fe]=de(null),[Z,K]=de(!0),[se,s]=de(!1),[h,T]=de({}),F=re(0),I=re(0),V=re(null),ae=re(null),ve=re(null),pe=re(null),rt=re(!1),ke=re(null),Se=re(null),Me=re(null),me=re(()=>{}),[ye,$e]=de(()=>new Set),[we,Qe]=de(null),De=re(null),Ae=kt(w=>{i.current={...i.current,page:w},y(i.current),Wt(t,i.current,!1)},[t]),ie=mr(Ae),Ce=kt(w=>{let M=++F.current;V.current||(V.current=ee("enrol_apply/queue:rows")),v(!0),ar(r,Pa(w,t.perpages)).then(S=>{M===F.current&&(rt.current||(rt.current=!0,T(Object.fromEntries(S.preference.widths.map(Y=>[Y.key,Y.width])))),w.show!==Re&&S.page!==i.current.page&&Ae(S.page),_(S),$e(Y=>{let co=new Set(S.rows.filter(ht=>ht.selectable).map(ht=>ht.userenrolmentid)),Tn=Array.from(Y).filter(ht=>co.has(ht));return Tn.length===Y.size?Y:new Set(Tn)}),z(!1),v(!1))}).catch(()=>{M===F.current&&(z(!0),v(!1))})},[r,t,Ae]),ce=ie.load,Te=kt(w=>{Ce(w),w.show===Re&&ce(w)},[Ce,ce]),wt=kt(()=>{let w=++I.current;ae.current||(ae.current=ee("enrol_apply/queue:summary")),K(!0),sr(r).then(M=>{w===I.current&&(fe(M),s(!1),K(!1))}).catch(()=>{w===I.current&&(s(!0),K(!1))})},[r]),$=kt((w,M)=>{i.current={...i.current,...w},y(i.current),$e(new Set),Wt(t,i.current,M),Te(i.current)},[Te,t]);mt(()=>{!C&&V.current&&(V.current(),V.current=null)},[C,u,q]),mt(()=>{!Z&&ae.current&&(ae.current(),ae.current=null)},[Z,L,se]),mt(()=>{Wt(t,i.current,!1),Te(i.current),wt()},[Te,wt,t]),mt(()=>{let w=M=>{let S=Wn(M.state);S!==null&&(i.current=S,y(S),R(S.search),$e(new Set),Te(S))};return window.addEventListener("popstate",w),()=>window.removeEventListener("popstate",w)},[Te]),mt(()=>()=>{ve.current!==null&&window.clearTimeout(ve.current),pe.current?.(),me.current()},[]);let Ue=w=>{p(w),Ft(Tr,w)},Ee=g.show===Re,Ke=w=>{if(w===Re){Ee||$({show:Re,sort:"",direction:"asc",page:0},!0);return}Ue(w),Ee&&$({show:"",sort:"",direction:"asc",page:0},!0)},ot=w=>{R(w),ve.current!==null&&(window.clearTimeout(ve.current),ve.current=null);let M=w.trim(),S=M.length>=t.searchmin?M:"";if(S===i.current.search){pe.current?.(),pe.current=null;return}if(w===""){pe.current?.(),pe.current=null,$({search:"",page:0},!1);return}pe.current||(pe.current=ee("enrol_apply/queue:search")),ve.current=window.setTimeout(()=>{ve.current=null,$({search:S,page:0},!1),pe.current?.(),pe.current=null},Sa)},ue=rr(u,t),{groupdefault:Ge,allcolumns:gt,usedefault:Ve,list:nn,decides:rn}=ue,on=(w,M)=>{let S={...i.current.filters};M!==""?S[w]=M:w===dt&&Ge!==""?S[dt]=Rt:delete S[w],w===xa&&delete S[Ma],$({filters:S,page:0},!0)},xt=()=>Ge!==""?{[dt]:Rt}:{},ze=(w,M)=>{let S=ee("enrol_apply/queue:preference");ir(r,w).then(()=>{M&&Ce(i.current)}).catch(()=>{M&&z(!0)}).finally(S)},Ye=gt.filter(w=>!w.fixed&&w.active).map(w=>w.key),je=w=>Object.entries(w).map(([M,S])=>({key:M,width:S})),Pt=(w,M)=>{let S=M?[...Ye,w]:Ye.filter(Y=>Y!==w);u&&_({...u,columns:u.columns.map(Y=>Y.key===w?{...Y,active:M}:Y),preference:{...u.preference,usedefault:!1,columns:S}}),ze({usedefault:!1,columns:S,widths:je(h)},!0)},Lt=w=>{let M=new Set(w.columns);u&&_({...u,columns:u.columns.map(S=>S.fixed?S:{...S,active:M.has(S.key)||M.has("answers")&&S.key.startsWith("answer:")}),presets:u.presets.map(S=>({...S,active:S.name===w.name})),preference:{...u.preference,usedefault:!1,columns:w.columns}}),ze({usedefault:!1,columns:w.columns,widths:je(h)},!0)},Dt=()=>{ke.current!==null&&(window.clearTimeout(ke.current),ke.current=null);let w=Me.current;Me.current=null,w!==null&&ze({usedefault:Ve,columns:Ve?[]:Ye,widths:je(w)},!1),Se.current?.(),Se.current=null};me.current=Dt;let m=(w,M,S=!1)=>{let Y={...h};if(M===null?delete Y[w]:Y[w]=M,T(Y),Me.current=Y,!S){Dt();return}ke.current!==null&&window.clearTimeout(ke.current),Se.current||(Se.current=ee("enrol_apply/queue:width")),ke.current=window.setTimeout(()=>me.current(),Ca)},x=w=>{let M=u!==null&&u.sort===w&&u.direction==="asc"?"desc":"asc";$({sort:w,direction:M,page:0},!0)},E=gt.filter(w=>w.active),H=t.methods===null?[]:[{value:Re,label:t.methods.label,icon:"queue"}],Ze={...n,...t.methods??{}},An=a==="cards"||a==="auto"&&l;mt(()=>{we===null&&De.current&&(De.current.focus(),De.current=null)},[we]);let At=t.decision,{chosen:an,selection:ro}=or(ye,$e,ue,n),oo=(w,M)=>{De.current=M,Qe(w)},ao=w=>{At===null||we===null||nr({action:vt(t,i.current,!0),sesskey:At.sesskey,decision:we,userenrolments:an.map(M=>M.id),...w})},lo=u===null?null:We(xr,{labels:n,list:nn,shown:E,cards:An,narrow:l,widths:h,sort:u.sort,direction:u.direction,selection:ro,onSort:x,onWidth:m,onWidthCommit:()=>me.current()}),so=Ea[t.kind].map(w=>({key:w,title:w==="applicant"?n.applicant:"",sortable:!1,default:!0,active:!0,fixed:w==="applicant"})),io=_n(Fr,{children:[We(ut,{labels:n,loading:C,loaded:u!==null,error:q,total:ue.total,filtered:Vr(g),cards:An,narrow:l,skeleton:{columns:so,lead:"applicant",widths:h,sizing:Ut},dock:Pr(n,At,rn,an,oo),onRetry:()=>Ce(i.current),children:lo}),u!==null&&u.total>0?We(at,{labels:n,page:g.page,perpage:g.perpage,perpages:t.perpages,total:u.total,shown:u.rows.length,narrow:l,onPage:w=>$({page:w},!0),onPerpage:w=>$({perpage:w,page:0},!0)}):null]});return _n("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-queueview",children:[We(ur,{labels:n,kind:t.kind,summary:L,error:se,onRetry:wt}),ue.notingroup?We("p",{className:"enrol_apply-rview-narrowed",children:n.notingroup}):_n(Fr,{children:[We(Mr,{labels:n,narrowed:ue.narrowed,viewonly:ue.viewonly}),We(Xn,{labels:n,narrow:l,query:g,rows:u,columns:gt,presets:ue.presets,usedefault:Ve,haswidths:Object.keys(h).length>0,view:a,extraViews:H,methods:Ee?ie.answer?.total??null:void 0,search:d,searchmin:t.searchmin,searching:C,tableurl:vt(t,g,!1),reporturl:t.reporturl,onScope:w=>$({scope:w,page:0},!0),onSearch:ot,onFilter:on,onClearFilters:()=>$({filters:xt(),page:0},!0),onClearAll:()=>{R(""),$({filters:xt(),search:"",page:0},!0)},onColumn:Pt,onPreset:Lt,onDefaultColumns:()=>{u&&_({...u,preference:{...u.preference,usedefault:!0,columns:[]}}),ze({usedefault:!0,columns:[],widths:je(h)},!0)},onResetWidths:()=>{T({}),ze({usedefault:Ve,columns:Ve?[]:Ye,widths:[]},!1)},onView:Ke}),Ee?We(vr,{labels:Ze,answer:ie.answer,loading:ie.loading,error:ie.error,filtered:Vr(g),narrow:l,perpages:t.perpages,onSort:w=>$({sort:w,direction:wr(ie.answer,w),page:0},!0),onPage:w=>$({page:w},!0),onPerpage:w=>$({perpage:w,page:0},!0),onRetry:()=>ce(i.current)}):io]}),Lr(n,At,we,an,()=>Qe(null),ao)]})},Ir=La;import{useCallback as Ln,useEffect as tn,useRef as le,useState as he}from"react";import{useState as Nn}from"react";import{Fragment as Sn,jsx as N,jsxs as G}from"react/jsx-runtime";var kn=8,Hr=7,Yt=({label:e,value:t,sub:n,warn:r,link:o})=>G("div",{className:"enrol_apply-rview-tile"+(r?" enrol_apply-rview-tile--warn":""),children:[N("div",{className:"enrol_apply-rview-tile-label",children:e}),N("div",{className:"enrol_apply-rview-tile-value",children:t}),N("div",{className:"enrol_apply-rview-tile-sub",children:n}),o??null]}),Or=({label:e,share:t,figure:n,warn:r})=>G("div",{className:"enrol_apply-rview-hrow",role:"listitem",children:[N("span",{className:"enrol_apply-rview-hrow-label",title:e,children:e}),N("span",{className:"enrol_apply-rview-track","aria-hidden":"true",children:N("span",{className:"enrol_apply-rview-fill"+(r?" enrol_apply-rview-fill--late":""),style:{width:`${Math.round(Math.min(1,Math.max(0,t))*100)}%`}})}),N("span",{className:"enrol_apply-rview-hrow-n",children:n})]}),Da=({compact:e})=>G("div",{"aria-hidden":"true",children:[N("div",{className:"enrol_apply-rview-band",children:Array.from({length:e?3:4},(t,n)=>G("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[N("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),N("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"}),N("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"})]},n))}),e?null:N("div",{className:"enrol_apply-rview-dist",children:[0,1].map(t=>G("div",{className:"enrol_apply-rview-panel enrol_apply-rview-sk",children:[N("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"}),N("span",{className:"enrol_apply-rview-skb"}),N("span",{className:"enrol_apply-rview-skb"}),N("span",{className:"enrol_apply-rview-skb"})]},t))})]}),Aa=({labels:e,bymethod:t,bygroup:n,methods:r})=>{let[o,l]=Nn(r>1?"method":"group"),[a,p]=Nn(!1),[c,i]=Nn(""),g=o==="method"?t:n,y=Math.max(86400,...g.map(v=>v.median??0)),d=v=>N(Or,{label:v.name,share:(v.median??0)/y,figure:`${v.median===null?"\u2013":xe(e,v.median)} (${f(v.count)})`,warn:(v.median??0)>Hr*86400},v.id),R=[...g].sort((v,q)=>q.count-v.count),u=o==="group"&&g.length>kn,_=c.trim().toLocaleLowerCase(),C=[...g].filter(v=>_===""||v.name.toLocaleLowerCase().includes(_)).sort((v,q)=>(q.median??0)-(v.median??0));return G("div",{className:"enrol_apply-rview-panel",children:[G("div",{className:"enrol_apply-rview-panel-title",children:[N("span",{children:e.medianby}),r>1?N(yt,{label:e.breakdown,small:!0,value:o,options:[{value:"method",label:e.bymethod},{value:"group",label:e.bygroup}],onChange:v=>l(v)}):N("span",{children:e.bygroup})]}),u?G(Sn,{children:[N("div",{className:"enrol_apply-rview-note",children:b(e.topgroups,f(kn))}),N("div",{role:"list",children:R.slice(0,kn).map(d)}),N("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":a,onClick:()=>p(!a),children:a?e.hide:b(e.allgroups,f(g.length))}),a?G(Sn,{children:[N("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:e.searchgroup,"aria-label":e.searchgroup,value:c,onChange:v=>i(v.target.value)}),N("div",{className:"enrol_apply-rview-scrolllist",role:"list",children:C.length?C.map(d):N("p",{className:"enrol_apply-rview-note",children:e.nogroupmatch})})]}):null]}):N("div",{role:"list",children:R.map(d)})]})},Wr=({labels:e,summary:t,loading:n,error:r,compactHint:o,measure:l,methods:a,queueurl:p,pendingstatus:c,onMeasure:i,onRetry:g})=>{let y=G("div",{className:"enrol_apply-rview-bandhead",children:[N("span",{className:"enrol_apply-rview-bandtitle",children:e.turnaround}),N(yt,{label:e.measure,small:!0,value:l,options:[{value:"first",label:e.measurefirst},{value:"resolution",label:e.measureresolution}],onChange:R=>i(R)})]}),d;if(t===null&&r)d=G("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",N("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:g,children:e.retry})]});else if(t===null)d=G(Sn,{children:[N(Da,{compact:o}),N("span",{className:"visually-hidden",role:"status",children:e.loadband})]});else{let R=t.measure==="resolution",u=t.median??null,_=t.p90??null,C=t.oldest??null,v=e.nothingopen;C!==null&&(v=b(e.oldest,xe(e,C))+(t.late>0?" \xB7 "+b(e.late,f(t.late)):""));let q=p===""?null:N("a",{className:"enrol_apply-rview-tile-link",href:R?p:`${p}&status=${c}`,children:e.openqueue}),z=Math.max(1,...t.buckets.map(L=>L.count));d=G("div",{className:"enrol_apply-rview-dim",children:[G("div",{className:"enrol_apply-rview-band",children:[N(Yt,{label:R?e.medianresolution:e.medianfirst,value:u===null?"\u2013":xe(e,u),sub:b(e.mediansub,f(t.decided))}),t.compact?null:N(Yt,{label:e.p90,value:_===null?"\u2013":xe(e,_),sub:e.p90sub}),N(Yt,{label:e.open,value:f(t.open),sub:v,warn:t.late>0,link:q}),N(Yt,{label:e.withoutperson,value:f(t.withoutperson),sub:e.withoutpersonsub})]}),t.compact?null:G("div",{className:"enrol_apply-rview-dist",children:[G("div",{className:"enrol_apply-rview-panel",children:[G("div",{className:"enrol_apply-rview-panel-title",children:[N("span",{children:R?e.distresolution:e.distfirst}),N("span",{className:"enrol_apply-rview-note",children:b(e.decisions,f(t.decided))})]}),N("div",{role:"list",children:t.buckets.map((L,fe)=>{let Z=f(L.todays??0),K=J(e.bucket,{from:f(L.fromdays),to:Z});return fe===0?K=b(e.bucketfirst,f(L.todays??0)):(L.todays??null)===null&&(K=b(e.bucketlast,f(L.fromdays))),N(Or,{label:K,share:L.count/z,figure:f(L.count),warn:L.fromdays>=Hr},L.fromdays)})})]}),N(Aa,{labels:e,bymethod:t.bymethod,bygroup:t.bygroup,methods:a})]})]})}return G("section",{className:"enrol_apply-rview-region enrol_apply-rview-bandregion","aria-label":e.turnaround,"aria-busy":n,"data-region":"enrol_apply-band",children:[n&&t!==null?N("div",{className:"enrol_apply-rview-progress"}):null,y,d]})};var Br=e=>e.facet!==""&&e.options.length>0,qr=e=>{try{let t=JSON.parse(e.values||"{}");return t!==null&&typeof t=="object"?t:{}}catch{return{}}},Ta=e=>e.reduce((t,n)=>({...t,...qr(n)}),{}),Cn=e=>{if(!e.applied)return"";let t=qr(e),n=t[`${e.name}_value`];if(n==null)return"";let r=Number(t[`${e.name}_operator`]??0);if(e.facet==="waited"){let o=Number(n)*Number(t[`${e.name}_unit`]??0)/86400;return r===2&&Number.isFinite(o)?String(o):""}return e.facet==="decider"?r===3?String(n):"":String(n)},$r=(e,t,n)=>{let r=En(e,t);return t.facet==="waited"?(r[`${t.name}_operator`]=n===""?0:2,r[`${t.name}_value`]=n===""?0:Number(n),r[`${t.name}_unit`]=86400):t.facet==="decider"?(r[`${t.name}_operator`]=n===""?0:3,r[`${t.name}_value`]=n):(r[`${t.name}_operator`]=n===""?0:1,r[`${t.name}_value`]=n),r},En=(e,t)=>{let n=Ta(e);for(let r of Object.keys(n))r.startsWith(`${t.name}_`)&&delete n[r];return n},Qr=e=>{let t=Cn(e);return e.facet===""||t===""?"":e.options.find(n=>n.value===t)?.label??(e.facet==="decider"?t:"")};import{jsx as Ur,jsxs as Wa}from"react/jsx-runtime";var Va=["auto","table","cards"],Kr=(e,t)=>t.facet==="waited"?e.waitedmore:t.facet==="decider"?e.decidedby:t.title,Fa=(e,t)=>{switch(t){case"decisions":return e.presetdecisions;case"answers":return e.presetanswers;case"groups":return e.presetgroups;default:return t}},Ia=(e,t,n)=>{let r={measure:e};return t!=="all"&&(r.scope=t),n!==""&&(r.search=n),encodeURIComponent(JSON.stringify(r))},Ha=(e,t)=>({name:t.name,title:Kr(e,t),options:t.options,groupings:t.groupings,find:t.facet==="decider"?{label:e.searchdeciders,nomatch:e.nodecidermatch}:{label:e.searchgroups,nomatch:e.nogroupmatch}}),Oa=(e,t)=>{let n=Qr(t),r=n!==""?Kr(e,t):t.title;return{key:t.name,text:n===""?r:J(e.filterchip,{title:r,value:n})}},Gr=e=>{let{labels:t,filters:n}=e,r=e.measure==="resolution"?"submission:resolutiontime":"submission:waittime",o=d=>Br(d)&&(d.facet!=="waited"||d.name===r),l=n.filter(o),a=n.filter(d=>!o(d)),p=d=>n.find(R=>R.name===d),c=e.downloadurl!==""&&e.formats.length>0,i=Ia(e.measure,e.scope,e.appliedsearch),g=c?e.formats.map(d=>({key:d.value,label:d.label,href:`${e.downloadurl}&parameters=${i}&download=${encodeURIComponent(d.value)}`})):[],y=e.presets.map(d=>({name:d.name,label:Fa(t,d.name),active:d.active}));return Ur($t,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"awaiting",label:t.awaiting},{value:"decided",label:t.decided}],scope:e.scope,total:e.total,facets:l.map(d=>Ha(t,d)),values:Object.fromEntries(l.map(d=>[d.name,Cn(d)])),panelnote:a.length>0?Wa("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",Ur("a",{href:e.tableurl,children:t.morefilters})]}):null,chips:n.filter(d=>d.applied).map(d=>Oa(t,d)),columns:e.columns,sets:y,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:Va,tableurl:e.tableurl,downloads:g,search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:d=>e.onScope(d),onSearch:e.onSearch,onFacet:(d,R)=>{let u=p(d);u&&e.onFilter(u,R)},onRemoveChip:d=>{let R=p(d);R&&e.onRemoveFilter(R)},onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:d=>{let R=e.presets.find(u=>u.name===d);R&&e.onPreset(R)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Ga,jsx as jt}from"react/jsx-runtime";var Ba=["outcome","wait","decided","group"],qa={applicant:260,outcome:180,submitted:132,wait:150,decided:172,group:200,method:170,route:170,comment:240,note:240},$a=180,Qa={applicant:160,submitted:104,decided:104},Ua=80,Ka=800,Zt=lt(qa,$a,Qa,Ua,Ka),Ne=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",Xt=(e,t)=>er(t,Ba.includes(e)),St=({column:e,html:t})=>jt(Qt,{parts:Xt(e,t)}),Jt=({row:e,label:t})=>e.reviewurl===""?null:jt("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:e.reviewurl,children:t}),en=({row:e,identity:t})=>jt(Ga,{children:t.map(n=>{let r=Ne(e,n.key);return r===""?null:jt("span",{className:"enrol_apply-rview-sub",title:It(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});import{Fragment as zr,jsx as oe,jsxs as Et}from"react/jsx-runtime";var za=["applicant","outcome","submitted","wait","decided","group","method"],Ct=({row:e,column:t})=>{let n=Xt(t,Ne(e,t))[0]??"";return n===""?null:oe("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:n}})},Ya=(e,t,n,r)=>{let o=a=>t.some(p=>p.key===a),l=t.filter(a=>!za.includes(a.key)&&Ne(r,a.key).trim()!=="");return{head:Et(zr,{children:[Et("div",{className:"enrol_apply-rview-card-who",children:[oe("span",{className:"enrol_apply-rview-card-name",dangerouslySetInnerHTML:{__html:Ne(r,"applicant")}}),o("outcome")?oe(Ct,{row:r,column:"outcome"}):null,oe(en,{row:r,identity:n})]}),oe(Jt,{row:r,label:e.review})]}),lines:Et(zr,{children:[Et("div",{className:"enrol_apply-rview-card-line"+(r.open?" enrol_apply-rview-card-line--open":""),children:[o("wait")?oe(Ct,{row:r,column:"wait"}):null,o("wait")&&o("submitted")?oe("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("submitted")?oe(Ct,{row:r,column:"submitted"}):null]}),o("decided")?oe("div",{className:"enrol_apply-rview-card-line",children:Xt("decided",Ne(r,"decided")).map((a,p)=>oe("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:a}},p))}):null,o("group")||o("method")?Et("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",children:[o("group")?oe(Ct,{row:r,column:"group"}):null,o("group")&&o("method")&&Ne(r,"group")!==""?oe("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("method")?oe(Ct,{row:r,column:"method"}):null]}):null]}),fields:l.map(a=>({key:a.key,title:a.title,content:oe(St,{column:a.key,html:Ne(r,a.key)})}))}},Yr=({labels:e,columns:t,identity:n,rows:r,single:o})=>oe(it,{labels:e,rows:r,single:o,renderCard:l=>Ya(e,t,n,l)});import{Fragment as Za,jsx as Be,jsxs as Mn}from"react/jsx-runtime";var ja=({row:e,longest:t})=>!e.open&&!e.decidedbyperson?null:Be("span",{className:"enrol_apply-rview-waitbar"+(e.open?" enrol_apply-rview-waitbar--open":""),"aria-hidden":"true",children:Be("span",{style:{width:`${Math.min(100,Math.round(e.wait/t*100))}%`}})}),jr=({labels:e,columns:t,identity:n,rows:r,widths:o,sort:l,direction:a,onSort:p,onWidth:c,onWidthCommit:i})=>{let g=Math.max(86400,...r.filter(y=>y.open||y.decidedbyperson).map(y=>y.wait));return Be(st,{labels:e,columns:t,lead:"applicant",rows:r,widths:o,sizing:Zt,sort:l,direction:a,renderLead:(y,d)=>Mn("div",{className:"enrol_apply-rview-applicant",children:[Mn("div",{className:"enrol_apply-rview-applicant-text",children:[Be(St,{column:d.key,html:Ne(y,d.key)}),Be(en,{row:y,identity:n})]}),Be(Jt,{row:y,label:e.review})]}),renderCell:(y,d)=>Mn(Za,{children:[Be(St,{column:d.key,html:Ne(y,d.key)}),d.key==="wait"?Be(ja,{row:y,longest:g}):null]}),onSort:p,onWidth:c,onWidthCommit:i})};import{fetchOne as Mt}from"@moodle/lms/core/ajax";var Zr=(e,t,n)=>Mt({methodname:"enrol_apply_report_rows",args:{enrolid:e,...t,facets:n}}),Xr=(e,t,n,r)=>Mt({methodname:"enrol_apply_report_summary",args:{enrolid:e,measure:t,scope:n,search:r}}),Jr=(e,t)=>Mt({methodname:"enrol_apply_set_report_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}}),xn=(e,t,n)=>Mt({methodname:"core_reportbuilder_set_filters",args:{reportid:e,parameters:JSON.stringify({measure:t}),values:JSON.stringify(n)}}),Pn=(e,t)=>Mt({methodname:"core_reportbuilder_filters_reset",args:{reportid:e,parameters:JSON.stringify({measure:t})}});import{Fragment as nl,jsx as qe,jsxs as Dn}from"react/jsx-runtime";var Xa=300,Ja=500,eo="enrol_apply_report_view",el=["applicant","outcome","submitted","wait","decided","group"],tl=e=>{let{labels:t,enrolid:n,reportid:r}=e,o=le(null),l=Tt(o),[a,p]=he(()=>Vt(eo)),c={measure:e.measure,scope:"all",search:"",page:0,perpage:e.perpage,sort:"",direction:"desc"},i=le(c),[g,y]=he(c),[d,R]=he(""),[u,_]=he(null),[C,v]=he(!0),[q,z]=he(!1),[L,fe]=he(null),[Z,K]=he(!0),[se,s]=he(!1),[h,T]=he(!1),F=le(0),[I,V]=he({}),[ae,ve]=he({}),pe=m=>m||h,rt=le(0),ke=le(0),Se=le(null),Me=le(null),me=le(null),ye=le(null),$e=le(!1),we=le(null),Qe=le(null),De=le(null),Ae=le(()=>{}),ie=Ln((m,x)=>{let E=++rt.current;Se.current||(Se.current=ee("enrol_apply/report:rows")),v(!0),Zr(n,m,x).then(H=>{E===rt.current&&(H.counted&&ve(Object.fromEntries(H.filters.map(Ze=>[Ze.name,Ze]))),$e.current||($e.current=!0,V(Object.fromEntries(H.preference.widths.map(Ze=>[Ze.key,Ze.width])))),H.page!==i.current.page&&(i.current={...i.current,page:H.page},y(i.current)),_(H),z(!1),v(!1))}).catch(()=>{E===rt.current&&(z(!0),v(!1))})},[n]),Ce=Ln(m=>{let x=++ke.current;Me.current||(Me.current=ee("enrol_apply/report:band")),K(!0),Xr(n,m.measure,m.scope,m.search).then(E=>{x===ke.current&&(fe(E),s(!1),K(!1))}).catch(()=>{x===ke.current&&(s(!0),K(!1))})},[n]),ce=Ln((m,x,E)=>{i.current={...i.current,...m},y(i.current),x&&ie(i.current,E),E&&Ce(i.current)},[ie,Ce]);tn(()=>{!C&&Se.current&&(Se.current(),Se.current=null)},[C,u,q]),tn(()=>{!Z&&Me.current&&(Me.current(),Me.current=null)},[Z,L,se]),tn(()=>{ie(i.current,!0),Ce(i.current)},[ie,Ce]),tn(()=>()=>{me.current!==null&&window.clearTimeout(me.current),ye.current?.(),Ae.current()},[]);let Te=m=>{p(m),Ft(eo,m)},wt=m=>{R(m),me.current!==null&&(window.clearTimeout(me.current),me.current=null);let x=m.trim(),E=x.length>=e.searchmin?x:"";if(E===i.current.search){ye.current?.(),ye.current=null;return}if(m===""){ye.current?.(),ye.current=null,ce({search:"",page:0},!0,!0);return}ye.current||(ye.current=ee("enrol_apply/report:search")),me.current=window.setTimeout(()=>{me.current=null,ce({search:E,page:0},!0,!0),ye.current?.(),ye.current=null},Xa)},$=m=>{let x=ee("enrol_apply/report:filters");F.current+=1,T(!0),m().then(()=>{ce({page:0},!0,!0)}).catch(()=>{z(!0)}).finally(()=>{F.current-=1,T(F.current>0),x()})},Ue=(u?.filters??[]).map(m=>ae[m.name]?{...m,options:ae[m.name].options,groupings:ae[m.name].groupings}:m),Ee=(m,x)=>{let E=ee("enrol_apply/report:preference");Jr(n,m).then(()=>{x&&ie(i.current,!1)}).catch(()=>{x&&z(!0)}).finally(E)},Ke=u?.columns??[],ot=Ke.filter(m=>!m.fixed&&m.active).map(m=>m.key),ue=u?.preference.usedefault??!0,Ge=m=>Object.entries(m).map(([x,E])=>({key:x,width:E})),gt=(m,x)=>{let E=x?[...ot,m]:ot.filter(H=>H!==m);u&&_({...u,columns:u.columns.map(H=>H.key===m?{...H,active:x}:H),preference:{...u.preference,usedefault:!1,columns:E}}),Ee({usedefault:!1,columns:E,widths:Ge(I)},!0)},Ve=()=>{we.current!==null&&(window.clearTimeout(we.current),we.current=null);let m=De.current;De.current=null,m!==null&&Ee({usedefault:ue,columns:ue?[]:ot,widths:Ge(m)},!1),Qe.current?.(),Qe.current=null};Ae.current=Ve;let nn=(m,x,E=!1)=>{let H={...I};if(x===null?delete H[m]:H[m]=x,V(H),De.current=H,!E){Ve();return}we.current!==null&&window.clearTimeout(we.current),Qe.current||(Qe.current=ee("enrol_apply/report:width")),we.current=window.setTimeout(()=>Ae.current(),Ja)},rn=m=>{let x=new Set(m.columns);u&&_({...u,columns:u.columns.map(E=>E.fixed?E:{...E,active:x.has(E.key)||x.has("answers")&&E.key.startsWith("answer:")}),presets:u.presets.map(E=>({...E,active:E.name===m.name})),preference:{...u.preference,usedefault:!1,columns:m.columns}}),Ee({usedefault:!1,columns:m.columns,widths:Ge(I)},!0)},on=m=>{let E=(u?.sort??"")===m&&u?.direction==="asc"?"desc":"asc";ce({sort:m,direction:E,page:0},!0,!1)},xt=m=>{let x=i.current,E=Ke.some(H=>H.key==="wait"&&H.active);ce({measure:m},x.scope!=="all"||x.sort==="wait"||E,!0)},ze=Ue.some(m=>m.applied)||g.search!==""||g.scope!=="all",Ye=Ke.filter(m=>m.key.startsWith("identity:")),je=Ke.filter(m=>m.key==="applicant"||!m.fixed&&m.active),Pt=a==="cards"||a==="auto"&&l,Lt=null;u!==null&&(Lt=Pt?qe(Yr,{labels:t,columns:je,identity:Ye,rows:u.rows,single:l}):qe(jr,{labels:t,columns:je,identity:Ye,rows:u.rows,widths:I,sort:u.sort,direction:u.direction,onSort:on,onWidth:nn,onWidthCommit:()=>Ae.current()}));let Dt=el.map(m=>({key:m,title:m==="applicant"?t.applicant:"",sortable:!1,default:!0,active:!0,fixed:m==="applicant"}));return Dn("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-reportview",children:[u?.narrowed?qe("p",{className:"enrol_apply-rview-narrowed",children:t.narrowed}):null,qe(Wr,{labels:t,summary:L,loading:pe(Z),error:se,compactHint:u?.narrowed??!1,measure:g.measure,methods:e.methods,queueurl:e.queueurl,pendingstatus:e.pendingstatus,onMeasure:xt,onRetry:()=>Ce(i.current)}),qe(Gr,{labels:t,narrow:l,scope:g.scope,total:u===null?null:u.total,filters:Ue,columns:Ke.filter(m=>!m.key.startsWith("identity:")||m.active),presets:u?.presets??[],usedefault:ue,haswidths:Object.keys(I).length>0,view:a,measure:g.measure,search:d,appliedsearch:g.search,searchmin:e.searchmin,searching:C,tableurl:e.tableurl,downloadurl:e.downloadurl,formats:e.formats,onScope:m=>ce({scope:m,page:0},!0,!0),onSearch:wt,onFilter:(m,x)=>$(()=>xn(r,i.current.measure,$r(Ue,m,x))),onRemoveFilter:m=>$(()=>xn(r,i.current.measure,En(Ue,m))),onClearFilters:()=>$(()=>Pn(r,i.current.measure)),onClearAll:()=>{R(""),i.current={...i.current,search:""},Ue.some(m=>m.applied)?$(()=>Pn(r,i.current.measure)):ce({page:0},!0,!0)},onColumn:gt,onPreset:rn,onDefaultColumns:()=>{u&&_({...u,preference:{...u.preference,usedefault:!0,columns:[]}}),Ee({usedefault:!0,columns:[],widths:Ge(I)},!0)},onResetWidths:()=>{V({}),Ee({usedefault:ue,columns:ue?[]:ot,widths:[]},!1)},onView:Te}),qe(ut,{labels:t,loading:pe(C),loaded:u!==null,error:q,total:u?.total??0,filtered:ze,cards:Pt,narrow:l,skeleton:{columns:Dt,lead:"applicant",widths:I,sizing:Zt},onRetry:()=>ie(i.current,!0),children:Lt}),u!==null&&u.total>0?qe(at,{labels:t,page:g.page,perpage:g.perpage,perpages:e.perpages,total:u.total,shown:u.rows.length,narrow:l,onPage:m=>ce({page:m},!0,!1),onPerpage:m=>ce({perpage:m,page:0},!0,!1)}):null,e.retention!==""?Dn("p",{className:"enrol_apply-rview-retention","data-region":"enrol_apply-retention",children:[e.retention,e.retentionurl!==""?Dn(nl,{children:[" ",qe("a",{href:e.retentionurl,children:t.retentionchange})]}):null]}):null]})},to=tl;import{jsx as no}from"react/jsx-runtime";var rl=e=>e.mount===void 0||e.mount==="report"?no(to,{...e}):e.mount==="queue"?no(Ir,{...e}):null,Yi=rl;export{Yi as default};
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
