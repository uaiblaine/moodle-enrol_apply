import{useCallback as Zt,useEffect as qe,useRef as J,useState as ce}from"react";import{useEffect as hr,useState as br}from"react";var _r=576,mt=e=>{let[t,n]=br(!1);return hr(()=>{let r=e.current;if(!r||typeof ResizeObserver>"u")return;let o=new ResizeObserver(a=>{let l=a[0]?.contentRect.width??r.clientWidth;n(l>0&&l<_r)});return o.observe(r),()=>o.disconnect()},[e]),t},wt=e=>{try{let t=window.localStorage.getItem(e);return t==="table"||t==="cards"?t:"auto"}catch{return"auto"}},vt=(e,t)=>{try{window.localStorage.setItem(e,t)}catch{}};import{useId as Rr}from"react";var N=(e,t)=>(e||"").split("{$a}").join(t),le=(e,t)=>Object.entries(t).reduce((n,[r,o])=>n.split(`{$a->${r}}`).join(o),e||""),dn=(e,t,n,r)=>N(n===1&&e[`${t}_one`]?e[`${t}_one`]:e[t],r),b=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(t).format(e)}catch{return String(e)}},Ve=(e,t)=>{let n=Math.max(0,t);if(n<3600)return N(e.waitminutes,b(Math.max(1,Math.round(n/60))));if(n<86400)return N(e.waithours,b(Math.round(n/3600)));let r=Math.floor(n/86400),o=Math.round((n-r*86400)/3600);return o===24&&(r++,o=0),o>0&&r<10?le(e.waitdayshours,{days:b(r),hours:b(o)}):N(e.waitdays,b(r))},yt=e=>{let t=document.createElement("template");return t.innerHTML=e,(t.content.textContent||"").replace(/\s+/g," ").trim()};import{jsx as Se,jsxs as Ut}from"react/jsx-runtime";var Nr=(e,t)=>[...new Set([1,e-1,e,e+1,t])].filter(n=>n>=1&&n<=t).sort((n,r)=>n-r),ft=({labels:e,page:t,perpage:n,perpages:r,total:o,shown:a,narrow:l,onPage:p,onPerpage:i})=>{let c=Rr(),v=Math.max(1,Math.ceil(o/n)),h=t+1,d=o>0?t*n+1:0,_=o>0?d+a-1:0,u=[];if(l)u=[Se("span",{className:"enrol_apply-rview-pager-gap",children:le(e.pageof,{page:b(h),pages:b(v)})},"of")];else{let S=Nr(h,v);S.forEach((C,y)=>{y>0&&C-S[y-1]>1&&u.push(Se("span",{className:"enrol_apply-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${C}`)),u.push(Se("button",{type:"button","aria-current":C===h?"page":void 0,onClick:()=>p(C-1),children:b(C)},C))})}return Ut("div",{className:"enrol_apply-rview-foot",children:[Se("span",{className:"enrol_apply-rview-num","data-region":"enrol_apply-range",children:le(e.range,{from:b(d),to:b(_),total:b(o)})}),v>1?Ut("nav",{className:"enrol_apply-rview-pager","aria-label":e.pages,children:[Se("button",{type:"button",disabled:h===1,onClick:()=>p(t-1),children:e.previous}),u,Se("button",{type:"button",disabled:h===v,onClick:()=>p(t+1),children:e.next})]}):null,Ut("span",{className:"enrol_apply-rview-perpage",children:[Se("label",{htmlFor:c,children:e.perpage}),Se("select",{id:c,className:"form-select form-select-sm",value:n,onChange:S=>i(Number(S.target.value)),children:r.map(S=>Se("option",{value:S,children:b(S)},S))})]})]})};var ae=e=>{let t=window.M?.util;t?.js_pending(e);let n=!0;return()=>{n&&(n=!1,t?.js_complete(e))}};import{useState as Mr}from"react";import{useEffect as kr,useRef as bt}from"react";var gt=(e,t,n,r,o)=>({defaultWidth:a=>e[a]??t,minimumWidth:a=>n[a]??r,maximum:o}),we=(e,t,n)=>Math.min(e.maximum,Math.max(e.minimumWidth(n),t[n]??e.defaultWidth(n)));import{jsx as L,jsxs as Me}from"react/jsx-runtime";var ht=16,Sr={ascending:"\u25B2",descending:"\u25BC",none:""},Cr=(e,t)=>e?t==="asc"?"ascending":"descending":"none",Er=({selection:e,rows:t})=>{let n=bt(null),r=t.filter(l=>!e.locked.has(l.id)),o=r.filter(l=>e.selected.has(l.id)).length,a=r.length>0&&o===r.length;return kr(()=>{n.current&&(n.current.indeterminate=o>0&&!a)},[o,a]),Me("label",{className:"enrol_apply-rview-pickwrap",children:[L("input",{ref:n,type:"checkbox",className:"enrol_apply-rview-pick",checked:a,disabled:r.length===0,onChange:l=>e.onSelectPage(l.target.checked)}),L("span",{className:"visually-hidden",children:e.pagelabel})]})},Qt=({selection:e,row:t})=>e.locked.has(t.id)?L("span",{className:"enrol_apply-rview-pickwrap enrol_apply-rview-pick--none"}):Me("label",{className:"enrol_apply-rview-pickwrap",children:[L("input",{type:"checkbox",className:"enrol_apply-rview-pick",checked:e.selected.has(t.id),onChange:n=>e.onSelect(t.id,n.target.checked)}),L("span",{className:"visually-hidden",children:e.label(t)})]}),_t=({labels:e,columns:t,lead:n,rows:r,widths:o,sizing:a,sort:l,direction:p,selection:i,renderLead:c,renderCell:v,onSort:h,onWidth:d,onWidthCommit:_})=>{let u=bt(null),S=bt(null),C=bt(null),y=t.reduce((s,f)=>s+we(a,o,f.key),0),W=s=>{s.currentTarget.classList.toggle("is-scrolled",s.currentTarget.scrollLeft>0)},K=(s,f)=>{let A=S.current?.querySelector(`col[data-col="${CSS.escape(s)}"]`);!A||!S.current||(A.style.width=`${f}px`,S.current.style.width=`${y-we(a,o,s)+f}px`)},T=(s,f)=>Math.round(Math.min(a.maximum,Math.max(a.minimumWidth(s),f))),j=(s,f)=>{if(s.button!==0)return;s.preventDefault(),s.currentTarget.setPointerCapture(s.pointerId),s.currentTarget.classList.add("is-active");let A=we(a,o,f);C.current={key:f,x:s.clientX,width:A,now:A}},G=s=>{let f=C.current;if(!f)return;let A=document.documentElement.dir==="rtl",H=(s.clientX-f.x)*(A?-1:1),w=T(f.key,f.width+H);w!==f.now&&(K(f.key,w),f.now=w)},U=s=>{let f=C.current;C.current=null,s.currentTarget.classList.remove("is-active"),f&&f.now!==f.width&&d(f.key,f.now)},te=(s,f)=>{let A=document.documentElement.dir==="rtl",H=0;if(s.key==="ArrowRight")H=A?-ht:ht;else if(s.key==="ArrowLeft")H=A?ht:-ht;else if(s.key==="Home"){s.preventDefault(),d(f,null,!0);return}if(H===0)return;s.preventDefault();let w=T(f,we(a,o,f)+H);w!==we(a,o,f)&&d(f,w,!0)};return L("div",{className:"enrol_apply-rview-gridframe",ref:u,tabIndex:0,role:"region","aria-label":e.grid,onScroll:W,children:Me("table",{className:"enrol_apply-rview-grid",ref:S,style:{width:`${y}px`},children:[L("caption",{className:"visually-hidden",children:e.caption}),L("colgroup",{children:t.map(s=>L("col",{"data-col":s.key,style:{width:`${we(a,o,s.key)}px`}},s.key))}),L("thead",{children:L("tr",{children:t.map(s=>{let f=Cr(l===s.key,p),A=L("span",{className:"enrol_apply-rview-th-title",children:s.title}),H=s.sortable?Me("button",{type:"button",className:"enrol_apply-rview-sortbtn",onClick:()=>h(s.key),children:[A,L("span",{className:"enrol_apply-rview-arrow","aria-hidden":"true",children:Sr[f]})]}):L("span",{className:"enrol_apply-rview-plain",children:A});return Me("th",{scope:"col",className:s.key===n?"enrol_apply-rview-fixed":void 0,"aria-sort":s.sortable?f:void 0,title:s.title,"data-column":s.key,children:[i&&s.key===n?Me("div",{className:"enrol_apply-rview-lead",children:[L(Er,{selection:i,rows:r}),H]}):H,L("span",{className:"enrol_apply-rview-resize",role:"separator","aria-orientation":"vertical","aria-label":N(e.resize,s.title),"aria-valuemin":a.minimumWidth(s.key),"aria-valuemax":a.maximum,"aria-valuenow":we(a,o,s.key),tabIndex:0,onPointerDown:w=>j(w,s.key),onPointerMove:G,onPointerUp:U,onPointerCancel:U,onDoubleClick:()=>{we(a,o,s.key)!==a.defaultWidth(s.key)&&d(s.key,null)},onKeyDown:w=>te(w,s.key),onBlur:_})]},s.key)})})}),L("tbody",{children:r.map(s=>L("tr",{"data-record":s.id,className:i?.selected.has(s.id)?"enrol_apply-rview-row--selected":void 0,children:t.map(f=>f.key===n?L("td",{className:"enrol_apply-rview-fixed",children:i?Me("div",{className:"enrol_apply-rview-lead",children:[L(Qt,{selection:i,row:s}),c(s,f)]}):c(s,f)},f.key):L("td",{children:v(s,f)},f.key))},s.id))})]})})},pn=({columns:e,lead:t,widths:n,sizing:r})=>{let o=e.reduce((l,p)=>l+we(r,n,p.key),0),a=["enrol_apply-rview-skb--w80","enrol_apply-rview-skb--w60","enrol_apply-rview-skb--w40"];return L("div",{className:"enrol_apply-rview-gridframe","aria-hidden":"true",children:Me("table",{className:"enrol_apply-rview-grid",style:{width:`${o}px`},children:[L("colgroup",{children:e.map(l=>L("col",{style:{width:`${we(r,n,l.key)}px`}},l.key))}),L("thead",{children:L("tr",{children:e.map(l=>L("th",{className:l.key===t?"enrol_apply-rview-fixed":void 0,children:L("span",{className:"enrol_apply-rview-plain",children:L("span",{className:"enrol_apply-rview-th-title",children:l.title})})},l.key))})}),L("tbody",{children:Array.from({length:8},(l,p)=>L("tr",{className:"enrol_apply-rview-sk",children:e.map((i,c)=>L("td",{className:i.key===t?"enrol_apply-rview-fixed":void 0,children:L("span",{className:"enrol_apply-rview-skb "+a[(p+c)%3]})},i.key))},p))})]})})};import{jsx as pe,jsxs as je}from"react/jsx-runtime";var xr=({labels:e,row:t,single:n,selection:r,renderCard:o})=>{let[a,l]=Mr(!1),p=o(t),i=!n||a,c=r?.selected.has(t.id)??!1;return je("article",{className:"enrol_apply-rview-card"+(c?" enrol_apply-rview-card--selected":""),role:"listitem","data-record":t.id,children:[je("div",{className:"enrol_apply-rview-card-l1",children:[r?pe(Qt,{selection:r,row:t}):null,p.head]}),p.lines,p.fields.length>0?je("div",{className:"enrol_apply-rview-card-more",children:[n?pe("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":a,onClick:()=>l(!a),children:a?e.less:e.more}):null,i?pe("dl",{className:"enrol_apply-rview-card-list",children:p.fields.map(v=>je("div",{className:"enrol_apply-rview-card-item",children:[pe("dt",{children:v.title}),pe("dd",{children:v.content})]},v.key))}):null]}):null]})},Rt=({labels:e,rows:t,single:n,selection:r,renderCard:o})=>pe("div",{className:"enrol_apply-rview-cards"+(n?" enrol_apply-rview-cards--single":""),role:"list",children:t.map(a=>pe(xr,{labels:e,row:a,single:n,selection:r,renderCard:o},a.id))}),mn=({single:e})=>pe("div",{className:"enrol_apply-rview-cards"+(e?" enrol_apply-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:e?4:6},(t,n)=>je("div",{className:"enrol_apply-rview-card enrol_apply-rview-sk",children:[pe("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),pe("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"}),pe("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),pe("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"})]},n))});import{Fragment as wn,jsx as Ce,jsxs as Ue}from"react/jsx-runtime";var Pr=({labels:e,loaded:t,error:n,total:r,filtered:o,cards:a,narrow:l,skeleton:p,children:i,onRetry:c})=>{let v=Ue("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",Ce("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:c,children:e.retry})]});return!t&&n?v:t?r===0?Ue("div",{className:"enrol_apply-rview-empty",children:[Ce("p",{className:"enrol_apply-rview-empty-title",children:o?e.nomatch:e.empty}),o?Ce("p",{className:"enrol_apply-rview-empty-text",children:e.nomatchhelp}):null]}):Ue("div",{className:"enrol_apply-rview-dim",children:[n?v:null,i,a?null:Ce("p",{className:"enrol_apply-rview-hint",children:e.scrollhint})]}):Ue(wn,{children:[a?Ce(mn,{single:l}):Ce(pn,{...p}),Ce("span",{className:"visually-hidden",role:"status",children:e.loading})]})},Nt=e=>Ue(wn,{children:[Ue("section",{className:"enrol_apply-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"enrol_apply-rows",children:[e.loading&&e.loaded?Ce("div",{className:"enrol_apply-rview-progress"}):null,Ce(Pr,{...e})]}),e.dock??null]});var Lr="table",qt="enrolApplyQueue",Dr=(e,t,n)=>{let r=[];return t.scope!=="all"&&r.push(["status",String(t.scope==="pending"?e.statuses.pending:e.statuses.deferred)]),t.search!==""&&r.push(["search",t.search]),Object.keys(t.filters).sort().forEach(o=>{t.filters[o]!==""&&r.push([o,t.filters[o]])}),n?(t.sort!==""&&(t.sort!=="wait"||t.direction!=="asc")&&r.push(["sort",t.sort],["dir",t.direction]),t.page>0&&r.push(["page",String(t.page)]),t.perpage!==e.perpage&&r.push(["perpage",String(t.perpage)])):r.push(["view",Lr]),r},Xe=(e,t,n)=>{let r=new URL(e.pageurl,window.location.href);return Dr(e,t,n).forEach(([o,a])=>r.searchParams.set(o,a)),r.toString()},vn=e=>({scope:e.initial.scope,search:e.initial.search,filters:Object.fromEntries(e.initial.filters.map(t=>[t.name,t.value])),sort:e.initial.sort,direction:e.initial.direction,page:e.initial.page,perpage:e.initial.perpage}),kt=(e,t,n)=>{let r=Xe(e,t,!0),o={[qt]:t};try{n&&r!==window.location.href?window.history.pushState(o,"",r):window.history.replaceState(o,"",r)}catch{}document.querySelectorAll('[data-enrol-apply-queue="tablelink"]').forEach(a=>{a.href=Xe(e,t,!1)})},yn=e=>e===null||typeof e!="object"||!(qt in e)?null:e[qt];import{useEffect as Ct,useId as $r,useRef as et,useState as kn}from"react";import{useId as Tr,useState as gn}from"react";import{jsx as fn}from"react/jsx-runtime";var Ar={filter:"M2 3h12l-4.5 5.5V13l-3 1.5V8.5L2 3z",columns:"M2 3h12v10H2zM6.5 3v10M10 3v10",table:"M2 3h12v10H2zM2 6.5h12M2 10h12M6 6.5V13",cards:"M2 3h5v4H2zM9 3h5v4H9zM2 9h5v4H2zM9 9h5v4H9z",auto:"M2 3h7v10H2zM11 3h3v4h-3zM11 9h3v4h-3z",download:"M8 2v8M4.5 7 8 10.5 11.5 7M3 13h10",search:"M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM10.5 10.5 14 14",close:"M4 4l8 8M12 4l-8 8",eraser:"M9.5 2 14 6.5 7.5 13 3 8.5zM5.6 5.9l4.5 4.5M7.5 13H14",tick:"M3 8.5 6.5 12 13 4.5",chevron:"M4 6l4 4 4-4",reset:"M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5",queue:"M3 4h10M3 8h10M3 12h6"},B=({name:e,className:t})=>fn("svg",{className:"enrol_apply-rview-icon"+(t?" "+t:""),viewBox:"0 0 16 16",width:"16",height:"16",fill:"none",stroke:"currentColor",strokeWidth:"1.5",strokeLinecap:"round",strokeLinejoin:"round","aria-hidden":"true",focusable:"false",children:fn("path",{d:Ar[e]??""})});import{Fragment as Br,jsx as Q,jsxs as ve}from"react/jsx-runtime";var Fr=8,Kt=50,Ir=(e,t)=>t===null?e.show:N(e.showcount,b(t)),hn=({option:e})=>e.count>=0?Q("small",{className:"enrol_apply-rview-optcount",children:b(e.count)}):null,Vr=(e,t)=>e.options.filter(n=>n.count!==0||n.value===t),Hr=({labels:e,facet:t,shown:n,value:r,headid:o,onChange:a})=>{let[l,p]=gn(""),[i,c]=gn(""),v=l.trim().toLocaleLowerCase(),h=t.groupings.length>1?t.groupings:[],d=h.find(y=>String(y.id)===i)?.groups.map(String)??null,_=n.filter(y=>y.value===r),u=n.filter(y=>y.value!==r&&(v===""||y.label.toLocaleLowerCase().includes(v))&&(d===null||d.includes(y.value))),S=N(t.find.label,b(t.options.length)),C=h.length>0?e.moregroupsgrouping:e.moregroups;return ve(Br,{children:[ve("div",{className:"enrol_apply-rview-find",children:[Q("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:S,"aria-label":S,value:l,onChange:y=>p(y.target.value)}),h.length>0?ve("select",{className:"form-select form-select-sm enrol_apply-rview-grouping","aria-label":e.grouping,value:i,onChange:y=>c(y.target.value),children:[Q("option",{value:"",children:e.allgroupings}),h.map(y=>Q("option",{value:String(y.id),children:y.name},y.id))]}):null]}),ve("div",{className:"enrol_apply-rview-optlist",role:"group","aria-labelledby":o,children:[[..._,...u.slice(0,Kt)].map(y=>ve("button",{type:"button",className:"enrol_apply-rview-optrow","aria-pressed":y.value===r,onClick:()=>a(t.name,y.value===r?"":y.value),children:[Q("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:Q(B,{name:"tick"})}),Q("span",{className:"enrol_apply-rview-optrow-label",children:y.label})," ",Q(hn,{option:y})]},y.value)),u.length>Kt?Q("p",{className:"enrol_apply-rview-note",children:N(C,b(u.length-Kt))}):null,_.length===0&&u.length===0?Q("p",{className:"enrol_apply-rview-note",children:t.find.nomatch}):null]})]})},Or=({labels:e,facet:t,value:n,onChange:r})=>{let o=Tr(),a=Vr(t,n),l=[{value:"",label:e.any,count:-1},...a];if(t.options.length>Fr)return ve("div",{className:"enrol_apply-rview-fgroup",children:[Q("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),Q(Hr,{labels:e,facet:t,shown:a,value:n,headid:o,onChange:r})]});let p=i=>{let c=l.findIndex(h=>h.value===n),v=-1;i.key==="ArrowRight"||i.key==="ArrowDown"?v=(c+1)%l.length:i.key==="ArrowLeft"||i.key==="ArrowUp"?v=(c-1+l.length)%l.length:i.key==="Home"?v=0:i.key==="End"&&(v=l.length-1),!(v<0)&&(i.preventDefault(),r(t.name,l[v].value),i.currentTarget.querySelectorAll('[role="radio"]')[v]?.focus())};return ve("div",{className:"enrol_apply-rview-fgroup",children:[Q("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),Q("div",{className:"enrol_apply-rview-fpanel-opts",role:"radiogroup","aria-labelledby":o,onKeyDown:p,children:l.map(i=>ve("button",{type:"button",className:"enrol_apply-rview-fpanel-opt",role:"radio","aria-checked":i.value===n,tabIndex:i.value===n?0:-1,onClick:()=>r(t.name,i.value),children:[i.label," ",Q(hn,{option:i})]},i.value))})]})},bn=({labels:e,id:t,hidden:n,facets:r,values:o,total:a,note:l,onChange:p,onClear:i,onShow:c})=>ve("div",{className:"enrol_apply-rview-fpanel",id:t,role:"dialog","aria-label":e.filters,hidden:n,children:[ve("div",{className:"enrol_apply-rview-fpanel-body",children:[r.map(v=>Q(Or,{labels:e,facet:v,value:o[v.name]??"",onChange:p},v.name)),l??null]}),ve("div",{className:"enrol_apply-rview-fpanel-foot",children:[Q("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:i,children:e.clearfilters}),Q("button",{type:"button",className:"btn btn-sm btn-primary",onClick:c,children:Ir(e,a)})]})]});import{Fragment as Wr,jsx as Ze,jsxs as _n}from"react/jsx-runtime";var St=e=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(e.key))return;let t=Array.from(e.currentTarget.querySelectorAll('[role^="menuitem"]'));if(t.length===0)return;e.preventDefault();let n=t.indexOf(document.activeElement),r=0;e.key==="End"?r=t.length-1:e.key==="ArrowDown"?r=(n+1)%t.length:e.key==="ArrowUp"&&(r=(n-1+t.length)%t.length),t[r].focus()},Rn=({heading:e,sets:t,onSet:n})=>t.length===0?null:_n(Wr,{children:[Ze("div",{className:"enrol_apply-rview-menu-head",role:"presentation",children:e}),t.map(r=>_n("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":r.active,tabIndex:-1,"data-preset":r.name,onClick:()=>{r.active||n(r.name)},children:[Ze("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:Ze(B,{name:"tick"})}),Ze("span",{className:"enrol_apply-rview-menu-label",children:r.label})]},r.name)),Ze("div",{className:"enrol_apply-rview-menu-sep",role:"separator"})]});import{jsx as Nn}from"react/jsx-runtime";var Je=({label:e,value:t,options:n,small:r,onChange:o})=>{let a=l=>{let p=n.findIndex(v=>v.value===t),i=-1;if(l.key==="ArrowRight"||l.key==="ArrowDown"?i=(p+1)%n.length:l.key==="ArrowLeft"||l.key==="ArrowUp"?i=(p-1+n.length)%n.length:l.key==="Home"?i=0:l.key==="End"&&(i=n.length-1),i<0)return;l.preventDefault(),o(n[i].value),l.currentTarget.querySelectorAll('[role="radio"]')[i]?.focus()};return Nn("div",{className:"enrol_apply-rview-seg"+(r?" enrol_apply-rview-seg--small":""),role:"radiogroup","aria-label":e,onKeyDown:a,children:n.map(l=>Nn("button",{type:"button",className:"enrol_apply-rview-seg-cell",role:"radio","aria-checked":l.value===t,tabIndex:l.value===t?0:-1,onClick:()=>o(l.value),children:l.label},l.value))})};import{Fragment as Sn,jsx as R,jsxs as V}from"react/jsx-runtime";var Et=e=>{let{labels:t,chips:n,columns:r}=e,o=$r(),a=et(null),l=et(null),p=et(null),i=et(!1),[c,v]=kn(null),[h,d]=kn(e.search!==""),_=et({});Ct(()=>{if(c===null)return;let w=F=>{a.current&&!a.current.contains(F.target)&&v(null)};return document.addEventListener("mousedown",w),()=>document.removeEventListener("mousedown",w)},[c]),Ct(()=>{if(c===null||c==="filters")return;let w=a.current?.querySelector(`#${CSS.escape(o+c)}`);(w?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])')??w?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])'))?.focus()},[c,o]),Ct(()=>{h?l.current?.focus():i.current&&(i.current=!1,p.current?.focus())},[h]),Ct(()=>{let w=F=>{if(F.key!=="/"||F.ctrlKey||F.metaKey||F.altKey||F.defaultPrevented)return;let $=document.activeElement;if($&&($.isContentEditable||$.closest("input, textarea, select")))return;let O=a.current?.getBoundingClientRect();!O||O.width===0||O.bottom<=0||O.top>=window.innerHeight||(F.preventDefault(),h?l.current?.focus():(v(null),d(!0)))};return document.addEventListener("keydown",w),()=>document.removeEventListener("keydown",w)},[h]);let u=w=>v(c===w?null:w),S=w=>{i.current=w,d(!1)},C=w=>{if(!(w.key!=="Escape"||w.defaultPrevented))if(c!==null){w.preventDefault();let F=_.current[c];v(null),F?.focus()}else h&&(w.preventDefault(),S(!0))},y=e.search.trim().length>=e.searchmin,W=y&&!h,K=n.length+(W?1:0),T=e.total===null?"\u2026":dn(t,"count",e.total,b(e.total)),j=e.total!==null&&e.narrow?V(Sn,{children:[R("span",{"aria-hidden":"true",children:b(e.total)}),R("span",{className:"visually-hidden",children:T})]}):T,G=(w,F,$,O)=>V("button",{type:"button",className:"enrol_apply-rview-iconbtn","aria-label":$,"data-enrol-apply-tip":$,"aria-haspopup":w==="filters"?"dialog":"menu","aria-expanded":c===w,"aria-controls":o+w,ref:z=>{_.current[w??""]=z},onClick:()=>u(w),children:[R(B,{name:F}),O]}),U={auto:"auto",table:"table",cards:"cards"}[e.view],te=R(B,{name:"chevron",className:"enrol_apply-rview-chevron"}),s=n.length>0?N(t.filterson,b(n.length)):t.filters,f=n.length>0?R("span",{className:"enrol_apply-rview-badge","aria-hidden":"true",children:b(n.length)}):null,A=[["auto",t.viewauto,t.viewautohint],["table",t.table,""],["cards",t.cards,""]].filter(([w])=>e.views.includes(w)),H="enrol_apply-rview-bar"+(e.narrow?" enrol_apply-rview-bar--narrow":"")+(h?" enrol_apply-rview-bar--searching":"");return V("div",{className:H,"data-region":"enrol_apply-bar",ref:a,onKeyDown:C,children:[R(Je,{label:t.scope,value:e.scope,options:e.scopes,onChange:e.onScope}),R("span",{className:"enrol_apply-rview-bar-spacer"}),V("div",{className:"enrol_apply-rview-bar-controls",children:[V("div",{className:"enrol_apply-rview-bar-cluster",children:[R("span",{className:"enrol_apply-rview-bar-count","data-region":"enrol_apply-total","aria-live":"polite",children:j}),V("div",{className:"enrol_apply-rview-capsule",children:[G("filters","filter",s,f),V("div",{className:"enrol_apply-rview-anchor",children:[G("columns","columns",t.columns,te),V("div",{className:"enrol_apply-rview-menu",id:o+"columns",role:"menu","aria-label":t.columns,hidden:c!=="columns",onKeyDown:St,children:[R(Rn,{heading:t.presets,sets:e.sets,onSet:e.onSet}),r.map(w=>w.fixed?V("div",{className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":"true","aria-disabled":"true",tabIndex:-1,children:[R("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:R(B,{name:"tick"})}),R("span",{className:"enrol_apply-rview-menu-label",children:w.title}),R("span",{className:"enrol_apply-rview-menu-hint",children:t.always})]},w.key):V("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":w.active,tabIndex:-1,onClick:()=>e.onColumn(w.key,!w.active),children:[R("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:R(B,{name:"tick"})}),R("span",{className:"enrol_apply-rview-menu-label",children:w.title})]},w.key)),R("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),V("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":e.usedefault,onClick:()=>{e.usedefault||e.onDefaultColumns()},children:[R(B,{name:"columns"}),R("span",{className:"enrol_apply-rview-menu-label",children:t.defaultcolumns})]}),V("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":!e.haswidths,onClick:()=>{e.haswidths&&e.onResetWidths()},children:[R(B,{name:"reset"}),R("span",{className:"enrol_apply-rview-menu-label",children:t.resetwidths})]})]})]}),V("div",{className:"enrol_apply-rview-anchor",children:[G("view",U,t.view,te),V("div",{className:"enrol_apply-rview-menu",id:o+"view",role:"menu","aria-label":t.view,hidden:c!=="view",onKeyDown:St,children:[A.map(([w,F,$])=>V("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===w,tabIndex:-1,onClick:()=>{e.onView(w),v(null),_.current.view?.focus()},children:[R(B,{name:w}),R("span",{className:"enrol_apply-rview-menu-label",children:F}),$!==""?R("span",{className:"enrol_apply-rview-menu-hint",children:$}):null,R(B,{name:"tick",className:"enrol_apply-rview-tick"})]},w)),e.tableurl!==""?V(Sn,{children:[R("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),V("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:e.tableurl,children:[R(B,{name:"table"}),R("span",{className:"enrol_apply-rview-menu-label",children:t.coretable})]})]}):null]})]}),e.downloads.length>0?V("div",{className:"enrol_apply-rview-anchor",children:[G("download","download",t.download,te),R("div",{className:"enrol_apply-rview-menu",id:o+"download",role:"menu","aria-label":t.download,hidden:c!=="download",onKeyDown:St,children:e.downloads.map(w=>V("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:w.href,children:[R(B,{name:"download"}),R("span",{className:"enrol_apply-rview-menu-label",children:w.label})]},w.key))})]}):null]})]}),R("div",{className:"enrol_apply-rview-capsule enrol_apply-rview-searchcap",children:h?V("span",{className:"enrol_apply-rview-search",children:[e.searching&&y?R("span",{className:"enrol_apply-rview-spin","aria-hidden":"true"}):R(B,{name:"search"}),R("input",{ref:l,type:"search","aria-label":t.search,placeholder:t.search,autoComplete:"off",value:e.search,onChange:w=>e.onSearch(w.target.value),onKeyDown:w=>{w.key==="Escape"&&(w.preventDefault(),S(!0))},onBlur:()=>{e.search===""&&S(!1)}}),e.search!==""?R("button",{type:"button",className:"enrol_apply-rview-eraser","aria-label":t.cleartext,"data-enrol-apply-tip":t.cleartext,onMouseDown:w=>w.preventDefault(),onClick:()=>{e.onSearch(""),l.current?.focus()},children:R(B,{name:"eraser"})}):null]}):R("button",{ref:p,type:"button",className:"enrol_apply-rview-iconbtn","aria-label":t.search,"data-enrol-apply-tip":t.search,onClick:()=>{v(null),d(!0)},children:R(B,{name:"search"})})}),R(bn,{labels:t,id:o+"filters",hidden:c!=="filters",facets:e.facets,values:e.values,total:e.total,note:e.panelnote,onChange:e.onFacet,onClear:e.onClearFilters,onShow:()=>{v(null),_.current.filters?.focus()}})]}),V("div",{className:"enrol_apply-rview-chips","data-region":"enrol_apply-chips",hidden:K===0&&!e.aside,children:[n.map(w=>V("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":N(t.removefilter,w.text),onClick:()=>e.onRemoveChip(w.key),children:[R("span",{children:w.text}),R(B,{name:"close"})]},w.key)),W?V("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":N(t.removefilter,N(t.searchchip,e.search.trim())),onClick:()=>e.onSearch(""),children:[R("span",{children:N(t.searchchip,e.search.trim())}),R(B,{name:"close"})]}):null,K>1?R("button",{type:"button",className:"enrol_apply-rview-clearall",onClick:()=>{e.onClearAll(),d(!1)},children:t.clearall}):null,e.aside??null]})]})};import{jsx as Gt,jsxs as Gr}from"react/jsx-runtime";var tt="all",Qe="group",Ur=["auto","table","cards"],Qr=(e,t)=>{switch(t){case"everything":return e.preseteverything;case"glance":return e.presetglance;case"answers":return e.presetanswers;default:return t}},qr=(e,t)=>{let n={label:e.searchgroups,nomatch:e.nogroupmatch},r=t.facets.map(a=>({name:a.name,title:a.title,options:a.options,groupings:[],find:n})),o=t.fieldfilters.filter(a=>a.control==="select").map(a=>({name:a.name,title:a.title,options:a.options.map(l=>({...l,count:-1})),groupings:[],find:n}));return[...r,...o]},Kr=(e,t,n,r)=>{let o=n,a=r,l=t.facets.find(i=>i.name===n),p=t.fieldfilters.find(i=>i.name===n);return l?(o=l.title,a=l.options.find(i=>i.value===r)?.label??r):p?(o=p.title,a=p.options.find(i=>i.value===r)?.label??r):n==="appliedfrom"?o=e.appliedfrom:n==="appliedto"&&(o=e.appliedto),{key:n,text:le(e.filterchip,{title:o,value:a})}},Cn=e=>{let{labels:t,rows:n,query:r}=e,o=e.presets.map(p=>({name:p.name,label:Qr(t,p.name),active:p.active})),a=Object.fromEntries(Object.entries(r.filters).map(([p,i])=>[p,p===Qe&&i===tt?"":i])),l=n===null?[]:n.filters.map(p=>Kr(t,n,p.name,p.value));return Gt(Et,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"pending",label:t.pending},{value:"deferred",label:t.deferred}],scope:r.scope,total:n===null?null:n.total,facets:n===null?[]:qr(t,n),values:a,panelnote:Gr("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",Gt("a",{href:e.tableurl,children:t.morefilters})]}),chips:l,aside:e.reporturl!==""?Gt("a",{className:"enrol_apply-qview-tolink",href:e.reporturl,children:t.openreport}):null,columns:e.columns,sets:o,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:Ur,tableurl:e.tableurl,downloads:[],search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:p=>e.onScope(p),onSearch:e.onSearch,onFacet:e.onFilter,onRemoveChip:p=>e.onFilter(p,""),onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:p=>{let i=e.presets.find(c=>c.name===p);i&&e.onPreset(i)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as zr,jsx as En}from"react/jsx-runtime";var Mn=(e,t)=>t?e.split(`
`).map(n=>n.trim()).filter(n=>n!==""):[e],Mt=({parts:e})=>En(zr,{children:e.map((t,n)=>En("span",{className:n===0?"enrol_apply-rview-c":"enrol_apply-rview-sub",title:yt(t),dangerouslySetInnerHTML:{__html:t}},n))});import{Fragment as lo,jsx as se,jsxs as xt}from"react/jsx-runtime";var Yr={applicant:320,wait:150,group:180,comment:260,history:130},jr=180,Xr={applicant:200},Zr=80,Jr=800,Pt=gt(Yr,jr,Xr,Zr,Jr),eo={deferred:"enrol_apply-status--warning",key:"enrol_apply-status--info",appliedbefore:"enrol_apply-status--neutral",own:"enrol_apply-status--neutral"},He=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",zt=({html:e})=>se(Mt,{parts:[e]}),to=({tag:e})=>{let t=eo[e.kind]??"enrol_apply-status--neutral",n=e.title!==""&&e.title!==e.text;return xt("span",{className:"enrol_apply-status "+t,title:n?e.title:void 0,children:[e.text,n?se("span",{className:"visually-hidden",children:" "+e.title}):null]})},no=({row:e})=>e.applicant.profileurl===""?se("span",{className:"enrol_apply-qview-name",children:e.applicant.fullname}):se("a",{className:"enrol_apply-qview-name",href:e.applicant.profileurl,children:e.applicant.fullname}),ro=({labels:e,row:t})=>se("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:t.reviewurl,"aria-label":N(e.reviewapplicant,t.applicant.fullname),children:e.review}),oo=({row:e})=>xt(lo,{children:[se("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:e.applicant.picture}}),xt("div",{className:"enrol_apply-qview-whotext",children:[se("span",{className:"enrol_apply-rview-c",children:se(no,{row:e})}),e.applicant.identity.map(t=>se("span",{className:"enrol_apply-rview-sub",title:t.value,children:t.value},t.name)),e.applicant.tags.length>0?se("span",{className:"enrol_apply-qview-tags",children:e.applicant.tags.map(t=>se(to,{tag:t},t.kind))}):null]})]}),Yt=({labels:e,row:t})=>xt("div",{className:"enrol_apply-qview-who",children:[se(oo,{row:t}),se(ro,{labels:e,row:t})]});var Oe=(e,t,n)=>{let r=document.createElement("input");r.type="hidden",r.name=t,r.value=n,e.appendChild(r)},xn=e=>{let t=document.createElement("form");t.method="post",t.action=e.action,t.hidden=!0,Oe(t,"sesskey",e.sesskey),Oe(t,"formaction",e.decision),e.userenrolments.forEach(n=>Oe(t,"userenrolments[]",String(n))),Oe(t,"outcomemessage",e.message),Oe(t,"decisionnote",e.note),e.decision==="confirm"&&(e.groups?.forEach(n=>Oe(t,"groups[]",String(n))),e.roleid!==null&&Oe(t,"roleid",String(e.roleid))),document.body.appendChild(t),t.submit()};var Pn=(e,t)=>{let n=t.decision!==null&&e!==null&&e.candecide;return{decides:n,notingroup:!!e?.notingroup,narrowed:!!e?.narrowed,viewonly:e!==null&&!n,groupdefault:e?.groupdefault??"",allcolumns:e?.columns??[],usedefault:e?.preference.usedefault??!0,presets:e?.presets??[],total:e?.total??0,list:(e?.rows??[]).map(r=>({...r,id:r.userenrolmentid}))}},Ln=(e,t,n,r)=>{let{list:o,decides:a}=n,l=new Set(o.filter(d=>!d.selectable).map(d=>d.id)),p=o.filter(d=>e.has(d.id)),i=(d,_)=>t(u=>{let S=new Set(u);return _&&!l.has(d)?S.add(d):S.delete(d),S}),c=d=>t(()=>d?new Set(o.filter(_=>!l.has(_.id)).map(_=>_.id)):new Set),h=a?{selected:e,locked:l,label:d=>N(r.selectapplicant,d.applicant.fullname),pagelabel:r.selectpage,onSelect:i,onSelectPage:c}:void 0;return{locked:l,chosen:p,selection:h}};import{jsx as jt,jsxs as ao}from"react/jsx-runtime";var Dn=({labels:e,count:t,actions:n,onDecide:r})=>ao("div",{className:"enrol_apply-qview-dock",role:"region","aria-label":e.dock,"data-region":"enrol_apply-dock",children:[jt("span",{className:"enrol_apply-qview-dockcount","aria-live":"polite",children:N(e.selected,b(t))}),jt("div",{className:"enrol_apply-qview-dockactions",children:n.map(o=>jt("button",{type:"button",className:"btn "+o.classes,onClick:a=>r(o.value,a.currentTarget),children:o.label},o.value))})]});import{useEffect as so,useId as io,useRef as co,useState as nt}from"react";import{Fragment as mo,jsx as D,jsxs as Z}from"react/jsx-runtime";var An=5,uo="button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), a[href]",po=(e,t,n)=>{let r=b(n);return t==="cancel"?[N(n===1?e.sheetcancelone:e.sheetcancel,r),N(e.sheetcancelcommit,r)]:t==="wait"?[N(n===1?e.sheetwaitone:e.sheetwait,r),N(e.sheetwaitcommit,r)]:[N(n===1?e.sheetconfirmone:e.sheetconfirm,r),N(e.sheetconfirmcommit,r)]},Lt=({id:e,text:t})=>D("div",{className:"form-text",id:e,children:t}),Tn=({labels:e,decision:t,rows:n,offer:r,onClose:o,onCommit:a})=>{let l=io(),p=co(null),[i,c]=nt(""),[v,h]=nt(""),[d,_]=nt(()=>(r.groups?.options??[]).filter(s=>s.selected).map(s=>String(s.id))),[u,S]=nt("0"),[C,y]=nt(!1),W=n.length,[K,T]=po(e,t,W),j=t==="confirm";so(()=>(p.current?.focus(),document.body.classList.add("modal-open"),()=>document.body.classList.remove("modal-open")),[]);let G=s=>{if(s.key==="Escape"){s.preventDefault(),s.stopPropagation(),C||o();return}if(s.key!=="Tab"||!p.current)return;let f=Array.from(p.current.querySelectorAll(uo));if(f.length===0){s.preventDefault();return}let A=f[0],H=f[f.length-1],w=document.activeElement;s.shiftKey&&(w===A||w===p.current)?(s.preventDefault(),H.focus()):!s.shiftKey&&(w===H||w===p.current)&&(s.preventDefault(),A.focus())},U=()=>{C||(y(!0),a({message:i,note:v,groups:j&&r.groups?d.map(Number):null,roleid:j&&r.roles?Number(u):null}))},te=W-An;return Z(mo,{children:[D("div",{className:"modal-backdrop show"}),D("div",{className:"modal show d-block enrol_apply-qview-sheet",role:"presentation",onMouseDown:s=>{s.target===s.currentTarget&&!C&&o()},children:D("div",{className:"modal-dialog modal-lg modal-dialog-scrollable",children:Z("div",{className:"modal-content",role:"dialog","aria-modal":"true","aria-labelledby":`${l}-title`,tabIndex:-1,ref:p,onKeyDown:G,"data-region":"enrol_apply-sheet",children:[Z("div",{className:"modal-header",children:[D("h5",{className:"modal-title",id:`${l}-title`,children:K}),D("button",{type:"button",className:"btn-close","aria-label":e.close,onClick:o,disabled:C})]}),Z("div",{className:"modal-body",children:[t==="cancel"?D("div",{className:"alert alert-danger",children:e.sheetcanceldanger}):null,t==="wait"?D("p",{className:"enrol_apply-qview-sheetline",children:e.sheetwaitline}):null,j&&r.placesfull!==""?D("div",{className:"alert alert-warning",children:r.placesfull}):null,Z("ul",{className:"enrol_apply-qview-sheetwho",children:[n.slice(0,An).map(s=>Z("li",{children:[D("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:s.applicant.picture}}),D("span",{children:s.applicant.fullname})]},s.id)),te>0?D("li",{className:"enrol_apply-qview-sheetmore",children:N(e.andmore,b(te))}):null]}),j&&r.summary?Z("div",{className:"enrol_apply-qview-sheetsummary",children:[Z("dl",{className:"row mb-0",children:[D("dt",{className:"col-sm-4",children:r.summary.groupslabel}),D("dd",{className:"col-sm-8",children:r.summary.groups}),D("dt",{className:"col-sm-4",children:r.summary.rolelabel}),D("dd",{className:"col-sm-8",children:r.summary.role})]}),Z("div",{className:"form-text",children:[r.summary.help,r.summary.note!==""?" "+r.summary.note:""]})]}):null,j&&r.groups?Z("div",{className:"mb-3",children:[D("label",{className:"form-label",htmlFor:`${l}-groups`,children:r.groups.label}),D("select",{className:"form-select",id:`${l}-groups`,multiple:!0,value:d,"aria-describedby":`${l}-groupshelp`,onChange:s=>_(Array.from(s.target.selectedOptions,f=>f.value)),children:r.groups.options.map(s=>D("option",{value:String(s.id),children:s.name},s.id))}),D(Lt,{id:`${l}-groupshelp`,text:r.groups.help})]}):null,j&&r.roles?Z("div",{className:"mb-3",children:[D("label",{className:"form-label",htmlFor:`${l}-role`,children:r.roles.label}),Z("select",{className:"form-select",id:`${l}-role`,value:u,"aria-describedby":`${l}-rolehelp`,onChange:s=>S(s.target.value),children:[D("option",{value:"0",children:r.roles.default}),r.roles.options.map(s=>D("option",{value:String(s.id),children:s.name},s.id))]}),D(Lt,{id:`${l}-rolehelp`,text:r.roles.help})]}):null,Z("div",{className:"mb-3",children:[D("label",{className:"form-label",htmlFor:`${l}-message`,children:r.message.label}),D("textarea",{className:"form-control",id:`${l}-message`,rows:3,value:i,"aria-describedby":`${l}-messagehelp`,onChange:s=>c(s.target.value)}),D(Lt,{id:`${l}-messagehelp`,text:r.message.help})]}),Z("div",{className:"mb-0",children:[D("label",{className:"form-label",htmlFor:`${l}-note`,children:r.note.label}),D("textarea",{className:"form-control",id:`${l}-note`,rows:3,value:v,"aria-describedby":`${l}-notehelp`,onChange:s=>h(s.target.value)}),D(Lt,{id:`${l}-notehelp`,text:r.note.help})]})]}),Z("div",{className:"modal-footer",children:[D("button",{type:"button",className:"btn btn-secondary",onClick:o,disabled:C,children:e.back}),D("button",{type:"button",className:"btn "+(t==="cancel"?"btn-danger":"btn-primary"),onClick:U,disabled:C,children:T})]})]})})})]})};import{Fragment as On,jsx as me,jsxs as Bn}from"react/jsx-runtime";var wo=["applicant","wait","group"],vo=(e,t)=>e.filter(n=>!wo.includes(n.key)&&He(t,n.key).trim()!=="").map(n=>({key:n.key,title:n.title,content:me(zt,{html:He(t,n.key)})})),Fn=({labels:e,narrowed:t,viewonly:n})=>Bn(On,{children:[t?me("p",{className:"enrol_apply-rview-narrowed",children:e.narrowed}):null,n?me("div",{className:"alert alert-info",role:"status",children:e.viewonly}):null]}),In=e=>{let{labels:t,list:n,shown:r,cards:o,narrow:a,selection:l}=e,p=i=>r.some(c=>c.key===i);return o?me(Rt,{labels:t,rows:n,single:a,selection:l,renderCard:i=>({head:me(Yt,{labels:t,row:i}),lines:Bn(On,{children:[p("wait")?me("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--open",dangerouslySetInnerHTML:{__html:He(i,"wait")}}):null,p("group")&&He(i,"group")!==""?me("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",dangerouslySetInnerHTML:{__html:He(i,"group")}}):null]}),fields:vo(r,i)})}):me(_t,{labels:t,columns:r,lead:"applicant",rows:n,widths:e.widths,sizing:Pt,sort:e.sort,direction:e.direction,selection:l,renderLead:i=>me(Yt,{labels:t,row:i}),renderCell:(i,c)=>me(zt,{html:He(i,c.key)}),onSort:e.onSort,onWidth:e.onWidth,onWidthCommit:e.onWidthCommit})},Vn=(e,t,n,r,o)=>t!==null&&n&&r>0?me(Dn,{labels:e,count:r,actions:t.actions,onDecide:o}):null,Hn=(e,t,n,r,o,a)=>t!==null&&n!==null&&r.length>0?me(Tn,{labels:e,decision:n,rows:r,offer:t,onClose:o,onCommit:a}):null;import{fetchOne as Xt}from"@moodle/lms/core/ajax";var Wn=(e,t)=>Xt({methodname:"enrol_apply_queue_rows",args:{enrolid:e,scope:t.scope,search:t.search,filters:Object.entries(t.filters).map(([n,r])=>({name:n,value:r})),page:t.page,perpage:t.perpage,sort:t.sort,direction:t.direction}}),$n=e=>Xt({methodname:"enrol_apply_queue_summary",args:{enrolid:e}}),Un=(e,t)=>Xt({methodname:"enrol_apply_set_queue_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}});import{jsx as ie,jsxs as rt}from"react/jsx-runtime";var Qn=(e,t)=>Math.min(100,Math.max(0,Math.round(e*100/t))),Dt=({label:e,value:t,sub:n,meter:r,warn:o})=>rt("div",{className:"enrol_apply-rview-tile"+(o?" enrol_apply-rview-tile--warn":""),role:"listitem",children:[ie("div",{className:"enrol_apply-rview-tile-label",children:e}),ie("div",{className:"enrol_apply-rview-tile-value",children:t}),r!==null?ie("span",{className:"enrol_apply-qview-meter"+(o?" enrol_apply-qview-meter--warn":""),"aria-hidden":"true",children:ie("span",{style:{width:`${r}%`}})}):null,n!==""?ie("div",{className:"enrol_apply-rview-tile-sub",children:n}):null]}),yo=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.DateTimeFormat(t,{dateStyle:"short"}).format(new Date(e*1e3))}catch{return new Date(e*1e3).toLocaleDateString()}},qn=({labels:e,summary:t,error:n,onRetry:r})=>{if(t===null&&n)return rt("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",ie("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:r,children:e.retry})]});if(t===null)return rt("div",{className:"enrol_apply-rview-band","aria-busy":"true",children:[Array.from({length:4},(p,i)=>rt("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[ie("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),ie("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"})]},i)),ie("span",{className:"visually-hidden",role:"status",children:e.loadsummary})]});let o=t.narrowed?e.methodnote:"",a=t.awaiting>0?N(e.oldest,Ve(e,t.oldestwait)):"",l=t.late>0?N(e.late,b(t.late)):"";return rt("div",{className:"enrol_apply-rview-band",role:"list","data-region":"enrol_apply-tiles",children:[t.places>0?ie(Dt,{label:e.placestaken,value:le(e.ofmany,{taken:b(t.placestaken),total:b(t.places)}),sub:o,meter:Qn(t.placestaken,t.places),warn:!1}):null,t.applicantlimit>0?ie(Dt,{label:e.held,value:le(e.ofmany,{taken:b(t.applicants),total:b(t.applicantlimit)}),sub:o,meter:Qn(t.applicants,t.applicantlimit),warn:t.applicants*5>=t.applicantlimit*4}):null,ie(Dt,{label:e.awaiting,value:b(t.awaiting),sub:[a,l].filter(p=>p!=="").join(" \xB7 "),meter:null,warn:t.late>0}),ie(Dt,{label:e.status,value:t.open?e.open:e.closed,sub:t.open&&t.closes>0?N(e.closes,yo(t.closes)):"",meter:null,warn:!1})]})};import{Fragment as Ro,jsx as Be,jsxs as Gn}from"react/jsx-runtime";var fo=300,go=500,Kn="enrol_apply_queue_view",ho=["applicant","wait","group","comment"],bo=e=>e.scope!=="all"||e.search!==""||Object.entries(e.filters).some(([t,n])=>n!==""&&!(t===Qe&&n===tt)),_o=e=>{let t=J(e).current,{labels:n,enrolid:r}=t,o=J(null),a=mt(o),[l,p]=ce(()=>wt(Kn)),[i]=ce(()=>vn(t)),c=J(i),[v,h]=ce(i),[d,_]=ce(i.search),[u,S]=ce(null),[C,y]=ce(!0),[W,K]=ce(!1),[T,j]=ce(null),[G,U]=ce(!0),[te,s]=ce(!1),[f,A]=ce({}),H=J(0),w=J(0),F=J(null),$=J(null),O=J(null),z=J(null),ge=J(!1),X=J(null),Le=J(null),he=J(null),Re=J(()=>{}),[Ke,Ne]=ce(()=>new Set),[de,Ee]=ce(null),ne=J(null),be=Zt(g=>{let P=++H.current;F.current||(F.current=ae("enrol_apply/queue:rows")),y(!0),Wn(r,g).then(M=>{P===H.current&&(ge.current||(ge.current=!0,A(Object.fromEntries(M.preference.widths.map(Y=>[Y.key,Y.width])))),M.page!==c.current.page&&(c.current={...c.current,page:M.page},h(c.current),kt(t,c.current,!1)),S(M),Ne(Y=>{let gr=new Set(M.rows.filter(Ye=>Ye.selectable).map(Ye=>Ye.userenrolmentid)),un=Array.from(Y).filter(Ye=>gr.has(Ye));return un.length===Y.size?Y:new Set(un)}),K(!1),y(!1))}).catch(()=>{P===H.current&&(K(!0),y(!1))})},[r,t]),Ge=Zt(()=>{let g=++w.current;$.current||($.current=ae("enrol_apply/queue:summary")),U(!0),$n(r).then(P=>{g===w.current&&(j(P),s(!1),U(!1))}).catch(()=>{g===w.current&&(s(!0),U(!1))})},[r]),re=Zt((g,P)=>{c.current={...c.current,...g},h(c.current),Ne(new Set),kt(t,c.current,P),be(c.current)},[be,t]);qe(()=>{!C&&F.current&&(F.current(),F.current=null)},[C,u,W]),qe(()=>{!G&&$.current&&($.current(),$.current=null)},[G,T,te]),qe(()=>{kt(t,c.current,!1),be(c.current),Ge()},[be,Ge,t]),qe(()=>{let g=P=>{let M=yn(P.state);M!==null&&(c.current=M,h(M),_(M.search),Ne(new Set),be(M))};return window.addEventListener("popstate",g),()=>window.removeEventListener("popstate",g)},[be]),qe(()=>()=>{O.current!==null&&window.clearTimeout(O.current),z.current?.(),Re.current()},[]);let De=g=>{p(g),vt(Kn,g)},Ae=g=>{_(g),O.current!==null&&(window.clearTimeout(O.current),O.current=null);let P=g.trim(),M=P.length>=t.searchmin?P:"";if(M===c.current.search){z.current?.(),z.current=null;return}if(g===""){z.current?.(),z.current=null,re({search:"",page:0},!1);return}z.current||(z.current=ae("enrol_apply/queue:search")),O.current=window.setTimeout(()=>{O.current=null,re({search:M,page:0},!1),z.current?.(),z.current=null},fo)},oe=Pn(u,t),{groupdefault:Te,allcolumns:ke,usedefault:_e,list:Bt,decides:it}=oe,Wt=(g,P)=>{let M={...c.current.filters};P!==""?M[g]=P:g===Qe&&Te!==""?M[Qe]=tt:delete M[g],re({filters:M,page:0},!0)},ct=()=>Te!==""?{[Qe]:tt}:{},Fe=(g,P)=>{let M=ae("enrol_apply/queue:preference");Un(r,g).then(()=>{P&&be(c.current)}).catch(()=>{P&&K(!0)}).finally(M)},We=ke.filter(g=>!g.fixed&&g.active).map(g=>g.key),$e=g=>Object.entries(g).map(([P,M])=>({key:P,width:M})),ut=(g,P)=>{let M=P?[...We,g]:We.filter(Y=>Y!==g);u&&S({...u,columns:u.columns.map(Y=>Y.key===g?{...Y,active:P}:Y),preference:{...u.preference,usedefault:!1,columns:M}}),Fe({usedefault:!1,columns:M,widths:$e(f)},!0)},dt=g=>{let P=new Set(g.columns);u&&S({...u,columns:u.columns.map(M=>M.fixed?M:{...M,active:P.has(M.key)||P.has("answers")&&M.key.startsWith("answer:")}),presets:u.presets.map(M=>({...M,active:M.name===g.name})),preference:{...u.preference,usedefault:!1,columns:g.columns}}),Fe({usedefault:!1,columns:g.columns,widths:$e(f)},!0)},ze=()=>{X.current!==null&&(window.clearTimeout(X.current),X.current=null);let g=he.current;he.current=null,g!==null&&Fe({usedefault:_e,columns:_e?[]:We,widths:$e(g)},!1),Le.current?.(),Le.current=null};Re.current=ze;let pt=(g,P,M=!1)=>{let Y={...f};if(P===null?delete Y[g]:Y[g]=P,A(Y),he.current=Y,!M){ze();return}X.current!==null&&window.clearTimeout(X.current),Le.current||(Le.current=ae("enrol_apply/queue:width")),X.current=window.setTimeout(()=>Re.current(),go)},$t=g=>{let P=u!==null&&u.sort===g&&u.direction==="asc"?"desc":"asc";re({sort:g,direction:P,page:0},!0)},m=ke.filter(g=>g.active),x=l==="cards"||l==="auto"&&a;qe(()=>{de===null&&ne.current&&(ne.current.focus(),ne.current=null)},[de]);let E=t.decision,{chosen:I,selection:Ie}=Ln(Ke,Ne,oe,n),wr=(g,P)=>{ne.current=P,Ee(g)},vr=g=>{E===null||de===null||xn({action:Xe(t,c.current,!0),sesskey:E.sesskey,decision:de,userenrolments:I.map(P=>P.id),...g})},yr=u===null?null:Be(In,{labels:n,list:Bt,shown:m,cards:x,narrow:a,widths:f,sort:u.sort,direction:u.direction,selection:Ie,onSort:$t,onWidth:pt,onWidthCommit:()=>Re.current()}),fr=ho.map(g=>({key:g,title:g==="applicant"?n.applicant:"",sortable:!1,default:!0,active:!0,fixed:g==="applicant"}));return Gn("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-queueview",children:[Be(qn,{labels:n,summary:T,error:te,onRetry:Ge}),oe.notingroup?Be("p",{className:"enrol_apply-rview-narrowed",children:n.notingroup}):Gn(Ro,{children:[Be(Fn,{labels:n,narrowed:oe.narrowed,viewonly:oe.viewonly}),Be(Cn,{labels:n,narrow:a,query:v,rows:u,columns:ke,presets:oe.presets,usedefault:_e,haswidths:Object.keys(f).length>0,view:l,search:d,searchmin:t.searchmin,searching:C,tableurl:Xe(t,v,!1),reporturl:t.reporturl,onScope:g=>re({scope:g,page:0},!0),onSearch:Ae,onFilter:Wt,onClearFilters:()=>re({filters:ct(),page:0},!0),onClearAll:()=>{_(""),re({filters:ct(),search:"",page:0},!0)},onColumn:ut,onPreset:dt,onDefaultColumns:()=>{u&&S({...u,preference:{...u.preference,usedefault:!0,columns:[]}}),Fe({usedefault:!0,columns:[],widths:$e(f)},!0)},onResetWidths:()=>{A({}),Fe({usedefault:_e,columns:_e?[]:We,widths:[]},!1)},onView:De}),Be(Nt,{labels:n,loading:C,loaded:u!==null,error:W,total:oe.total,filtered:bo(v),cards:x,narrow:a,skeleton:{columns:fr,lead:"applicant",widths:f,sizing:Pt},dock:Vn(n,E,it,I.length,wr),onRetry:()=>be(c.current),children:yr}),u!==null&&u.total>0?Be(ft,{labels:n,page:v.page,perpage:v.perpage,perpages:t.perpages,total:u.total,shown:u.rows.length,narrow:a,onPage:g=>re({page:g},!0),onPerpage:g=>re({perpage:g,page:0},!0)}):null]}),Hn(n,E,de,I,()=>Ee(null),vr)]})},zn=_o;import{useCallback as sn,useEffect as Ot,useRef as ue,useState as fe}from"react";import{useState as Jt}from"react";import{Fragment as tn,jsx as k,jsxs as q}from"react/jsx-runtime";var en=8,Yn=7,At=({label:e,value:t,sub:n,warn:r,link:o})=>q("div",{className:"enrol_apply-rview-tile"+(r?" enrol_apply-rview-tile--warn":""),children:[k("div",{className:"enrol_apply-rview-tile-label",children:e}),k("div",{className:"enrol_apply-rview-tile-value",children:t}),k("div",{className:"enrol_apply-rview-tile-sub",children:n}),o??null]}),jn=({label:e,share:t,figure:n,warn:r})=>q("div",{className:"enrol_apply-rview-hrow",role:"listitem",children:[k("span",{className:"enrol_apply-rview-hrow-label",title:e,children:e}),k("span",{className:"enrol_apply-rview-track","aria-hidden":"true",children:k("span",{className:"enrol_apply-rview-fill"+(r?" enrol_apply-rview-fill--late":""),style:{width:`${Math.round(Math.min(1,Math.max(0,t))*100)}%`}})}),k("span",{className:"enrol_apply-rview-hrow-n",children:n})]}),No=({compact:e})=>q("div",{"aria-hidden":"true",children:[k("div",{className:"enrol_apply-rview-band",children:Array.from({length:e?3:4},(t,n)=>q("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"}),k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"})]},n))}),e?null:k("div",{className:"enrol_apply-rview-dist",children:[0,1].map(t=>q("div",{className:"enrol_apply-rview-panel enrol_apply-rview-sk",children:[k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"}),k("span",{className:"enrol_apply-rview-skb"}),k("span",{className:"enrol_apply-rview-skb"}),k("span",{className:"enrol_apply-rview-skb"})]},t))})]}),ko=({labels:e,bymethod:t,bygroup:n,methods:r})=>{let[o,a]=Jt(r>1?"method":"group"),[l,p]=Jt(!1),[i,c]=Jt(""),v=o==="method"?t:n,h=Math.max(86400,...v.map(y=>y.median??0)),d=y=>k(jn,{label:y.name,share:(y.median??0)/h,figure:`${y.median===null?"\u2013":Ve(e,y.median)} (${b(y.count)})`,warn:(y.median??0)>Yn*86400},y.id),_=[...v].sort((y,W)=>W.count-y.count),u=o==="group"&&v.length>en,S=i.trim().toLocaleLowerCase(),C=[...v].filter(y=>S===""||y.name.toLocaleLowerCase().includes(S)).sort((y,W)=>(W.median??0)-(y.median??0));return q("div",{className:"enrol_apply-rview-panel",children:[q("div",{className:"enrol_apply-rview-panel-title",children:[k("span",{children:e.medianby}),r>1?k(Je,{label:e.breakdown,small:!0,value:o,options:[{value:"method",label:e.bymethod},{value:"group",label:e.bygroup}],onChange:y=>a(y)}):k("span",{children:e.bygroup})]}),u?q(tn,{children:[k("div",{className:"enrol_apply-rview-note",children:N(e.topgroups,b(en))}),k("div",{role:"list",children:_.slice(0,en).map(d)}),k("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":l,onClick:()=>p(!l),children:l?e.hide:N(e.allgroups,b(v.length))}),l?q(tn,{children:[k("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:e.searchgroup,"aria-label":e.searchgroup,value:i,onChange:y=>c(y.target.value)}),k("div",{className:"enrol_apply-rview-scrolllist",role:"list",children:C.length?C.map(d):k("p",{className:"enrol_apply-rview-note",children:e.nogroupmatch})})]}):null]}):k("div",{role:"list",children:_.map(d)})]})},Xn=({labels:e,summary:t,loading:n,error:r,compactHint:o,measure:a,methods:l,queueurl:p,pendingstatus:i,onMeasure:c,onRetry:v})=>{let h=q("div",{className:"enrol_apply-rview-bandhead",children:[k("span",{className:"enrol_apply-rview-bandtitle",children:e.turnaround}),k(Je,{label:e.measure,small:!0,value:a,options:[{value:"first",label:e.measurefirst},{value:"resolution",label:e.measureresolution}],onChange:_=>c(_)})]}),d;if(t===null&&r)d=q("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",k("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:v,children:e.retry})]});else if(t===null)d=q(tn,{children:[k(No,{compact:o}),k("span",{className:"visually-hidden",role:"status",children:e.loadband})]});else{let _=t.measure==="resolution",u=t.median??null,S=t.p90??null,C=t.oldest??null,y=e.nothingopen;C!==null&&(y=N(e.oldest,Ve(e,C))+(t.late>0?" \xB7 "+N(e.late,b(t.late)):""));let W=p===""?null:k("a",{className:"enrol_apply-rview-tile-link",href:_?p:`${p}&status=${i}`,children:e.openqueue}),K=Math.max(1,...t.buckets.map(T=>T.count));d=q("div",{className:"enrol_apply-rview-dim",children:[q("div",{className:"enrol_apply-rview-band",children:[k(At,{label:_?e.medianresolution:e.medianfirst,value:u===null?"\u2013":Ve(e,u),sub:N(e.mediansub,b(t.decided))}),t.compact?null:k(At,{label:e.p90,value:S===null?"\u2013":Ve(e,S),sub:e.p90sub}),k(At,{label:e.open,value:b(t.open),sub:y,warn:t.late>0,link:W}),k(At,{label:e.withoutperson,value:b(t.withoutperson),sub:e.withoutpersonsub})]}),t.compact?null:q("div",{className:"enrol_apply-rview-dist",children:[q("div",{className:"enrol_apply-rview-panel",children:[q("div",{className:"enrol_apply-rview-panel-title",children:[k("span",{children:_?e.distresolution:e.distfirst}),k("span",{className:"enrol_apply-rview-note",children:N(e.decisions,b(t.decided))})]}),k("div",{role:"list",children:t.buckets.map((T,j)=>{let G=b(T.todays??0),U=le(e.bucket,{from:b(T.fromdays),to:G});return j===0?U=N(e.bucketfirst,b(T.todays??0)):(T.todays??null)===null&&(U=N(e.bucketlast,b(T.fromdays))),k(jn,{label:U,share:T.count/K,figure:b(T.count),warn:T.fromdays>=Yn},T.fromdays)})})]}),k(ko,{labels:e,bymethod:t.bymethod,bygroup:t.bygroup,methods:l})]})]})}return q("section",{className:"enrol_apply-rview-region enrol_apply-rview-bandregion","aria-label":e.turnaround,"aria-busy":n,"data-region":"enrol_apply-band",children:[n&&t!==null?k("div",{className:"enrol_apply-rview-progress"}):null,h,d]})};var Zn=e=>e.facet!==""&&e.options.length>0,Jn=e=>{try{let t=JSON.parse(e.values||"{}");return t!==null&&typeof t=="object"?t:{}}catch{return{}}},So=e=>e.reduce((t,n)=>({...t,...Jn(n)}),{}),nn=e=>{if(!e.applied)return"";let t=Jn(e),n=t[`${e.name}_value`];if(n==null)return"";let r=Number(t[`${e.name}_operator`]??0);if(e.facet==="waited"){let o=Number(n)*Number(t[`${e.name}_unit`]??0)/86400;return r===2&&Number.isFinite(o)?String(o):""}return e.facet==="decider"?r===3?String(n):"":String(n)},er=(e,t,n)=>{let r=rn(e,t);return t.facet==="waited"?(r[`${t.name}_operator`]=n===""?0:2,r[`${t.name}_value`]=n===""?0:Number(n),r[`${t.name}_unit`]=86400):t.facet==="decider"?(r[`${t.name}_operator`]=n===""?0:3,r[`${t.name}_value`]=n):(r[`${t.name}_operator`]=n===""?0:1,r[`${t.name}_value`]=n),r},rn=(e,t)=>{let n=So(e);for(let r of Object.keys(n))r.startsWith(`${t.name}_`)&&delete n[r];return n},tr=e=>{let t=nn(e);return e.facet===""||t===""?"":e.options.find(n=>n.value===t)?.label??(e.facet==="decider"?t:"")};import{jsx as nr,jsxs as Lo}from"react/jsx-runtime";var Co=["auto","table","cards"],rr=(e,t)=>t.facet==="waited"?e.waitedmore:t.facet==="decider"?e.decidedby:t.title,Eo=(e,t)=>{switch(t){case"decisions":return e.presetdecisions;case"answers":return e.presetanswers;case"groups":return e.presetgroups;default:return t}},Mo=(e,t,n)=>{let r={measure:e};return t!=="all"&&(r.scope=t),n!==""&&(r.search=n),encodeURIComponent(JSON.stringify(r))},xo=(e,t)=>({name:t.name,title:rr(e,t),options:t.options,groupings:t.groupings,find:t.facet==="decider"?{label:e.searchdeciders,nomatch:e.nodecidermatch}:{label:e.searchgroups,nomatch:e.nogroupmatch}}),Po=(e,t)=>{let n=tr(t),r=n!==""?rr(e,t):t.title;return{key:t.name,text:n===""?r:le(e.filterchip,{title:r,value:n})}},or=e=>{let{labels:t,filters:n}=e,r=e.measure==="resolution"?"submission:resolutiontime":"submission:waittime",o=d=>Zn(d)&&(d.facet!=="waited"||d.name===r),a=n.filter(o),l=n.filter(d=>!o(d)),p=d=>n.find(_=>_.name===d),i=e.downloadurl!==""&&e.formats.length>0,c=Mo(e.measure,e.scope,e.appliedsearch),v=i?e.formats.map(d=>({key:d.value,label:d.label,href:`${e.downloadurl}&parameters=${c}&download=${encodeURIComponent(d.value)}`})):[],h=e.presets.map(d=>({name:d.name,label:Eo(t,d.name),active:d.active}));return nr(Et,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"awaiting",label:t.awaiting},{value:"decided",label:t.decided}],scope:e.scope,total:e.total,facets:a.map(d=>xo(t,d)),values:Object.fromEntries(a.map(d=>[d.name,nn(d)])),panelnote:l.length>0?Lo("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",nr("a",{href:e.tableurl,children:t.morefilters})]}):null,chips:n.filter(d=>d.applied).map(d=>Po(t,d)),columns:e.columns,sets:h,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:Co,tableurl:e.tableurl,downloads:v,search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:d=>e.onScope(d),onSearch:e.onSearch,onFacet:(d,_)=>{let u=p(d);u&&e.onFilter(u,_)},onRemoveChip:d=>{let _=p(d);_&&e.onRemoveFilter(_)},onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:d=>{let _=e.presets.find(u=>u.name===d);_&&e.onPreset(_)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Ho,jsx as Tt}from"react/jsx-runtime";var Do=["outcome","wait","decided","group"],Ao={applicant:260,outcome:180,submitted:132,wait:150,decided:172,group:200,method:170,route:170,comment:240,note:240},To=180,Fo={applicant:160,submitted:104,decided:104},Io=80,Vo=800,Ft=gt(Ao,To,Fo,Io,Vo),ye=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",It=(e,t)=>Mn(t,Do.includes(e)),ot=({column:e,html:t})=>Tt(Mt,{parts:It(e,t)}),Vt=({row:e,label:t})=>e.reviewurl===""?null:Tt("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:e.reviewurl,children:t}),Ht=({row:e,identity:t})=>Tt(Ho,{children:t.map(n=>{let r=ye(e,n.key);return r===""?null:Tt("span",{className:"enrol_apply-rview-sub",title:yt(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});import{Fragment as lr,jsx as ee,jsxs as at}from"react/jsx-runtime";var Oo=["applicant","outcome","submitted","wait","decided","group","method"],lt=({row:e,column:t})=>{let n=It(t,ye(e,t))[0]??"";return n===""?null:ee("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:n}})},Bo=(e,t,n,r)=>{let o=l=>t.some(p=>p.key===l),a=t.filter(l=>!Oo.includes(l.key)&&ye(r,l.key).trim()!=="");return{head:at(lr,{children:[at("div",{className:"enrol_apply-rview-card-who",children:[ee("span",{className:"enrol_apply-rview-card-name",dangerouslySetInnerHTML:{__html:ye(r,"applicant")}}),o("outcome")?ee(lt,{row:r,column:"outcome"}):null,ee(Ht,{row:r,identity:n})]}),ee(Vt,{row:r,label:e.review})]}),lines:at(lr,{children:[at("div",{className:"enrol_apply-rview-card-line"+(r.open?" enrol_apply-rview-card-line--open":""),children:[o("wait")?ee(lt,{row:r,column:"wait"}):null,o("wait")&&o("submitted")?ee("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("submitted")?ee(lt,{row:r,column:"submitted"}):null]}),o("decided")?ee("div",{className:"enrol_apply-rview-card-line",children:It("decided",ye(r,"decided")).map((l,p)=>ee("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:l}},p))}):null,o("group")||o("method")?at("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",children:[o("group")?ee(lt,{row:r,column:"group"}):null,o("group")&&o("method")&&ye(r,"group")!==""?ee("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("method")?ee(lt,{row:r,column:"method"}):null]}):null]}),fields:a.map(l=>({key:l.key,title:l.title,content:ee(ot,{column:l.key,html:ye(r,l.key)})}))}},ar=({labels:e,columns:t,identity:n,rows:r,single:o})=>ee(Rt,{labels:e,rows:r,single:o,renderCard:a=>Bo(e,t,n,a)});import{Fragment as $o,jsx as xe,jsxs as on}from"react/jsx-runtime";var Wo=({row:e,longest:t})=>!e.open&&!e.decidedbyperson?null:xe("span",{className:"enrol_apply-rview-waitbar"+(e.open?" enrol_apply-rview-waitbar--open":""),"aria-hidden":"true",children:xe("span",{style:{width:`${Math.min(100,Math.round(e.wait/t*100))}%`}})}),sr=({labels:e,columns:t,identity:n,rows:r,widths:o,sort:a,direction:l,onSort:p,onWidth:i,onWidthCommit:c})=>{let v=Math.max(86400,...r.filter(h=>h.open||h.decidedbyperson).map(h=>h.wait));return xe(_t,{labels:e,columns:t,lead:"applicant",rows:r,widths:o,sizing:Ft,sort:a,direction:l,renderLead:(h,d)=>on("div",{className:"enrol_apply-rview-applicant",children:[on("div",{className:"enrol_apply-rview-applicant-text",children:[xe(ot,{column:d.key,html:ye(h,d.key)}),xe(Ht,{row:h,identity:n})]}),xe(Vt,{row:h,label:e.review})]}),renderCell:(h,d)=>on($o,{children:[xe(ot,{column:d.key,html:ye(h,d.key)}),d.key==="wait"?xe(Wo,{row:h,longest:v}):null]}),onSort:p,onWidth:i,onWidthCommit:c})};import{fetchOne as st}from"@moodle/lms/core/ajax";var ir=(e,t,n)=>st({methodname:"enrol_apply_report_rows",args:{enrolid:e,...t,facets:n}}),cr=(e,t,n,r)=>st({methodname:"enrol_apply_report_summary",args:{enrolid:e,measure:t,scope:n,search:r}}),ur=(e,t)=>st({methodname:"enrol_apply_set_report_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}}),ln=(e,t,n)=>st({methodname:"core_reportbuilder_set_filters",args:{reportid:e,parameters:JSON.stringify({measure:t}),values:JSON.stringify(n)}}),an=(e,t)=>st({methodname:"core_reportbuilder_filters_reset",args:{reportid:e,parameters:JSON.stringify({measure:t})}});import{Fragment as Go,jsx as Pe,jsxs as cn}from"react/jsx-runtime";var Uo=300,Qo=500,dr="enrol_apply_report_view",qo=["applicant","outcome","submitted","wait","decided","group"],Ko=e=>{let{labels:t,enrolid:n,reportid:r}=e,o=ue(null),a=mt(o),[l,p]=fe(()=>wt(dr)),i={measure:e.measure,scope:"all",search:"",page:0,perpage:e.perpage,sort:"",direction:"desc"},c=ue(i),[v,h]=fe(i),[d,_]=fe(""),[u,S]=fe(null),[C,y]=fe(!0),[W,K]=fe(!1),[T,j]=fe(null),[G,U]=fe(!0),[te,s]=fe(!1),[f,A]=fe({}),[H,w]=fe({}),F=ue(0),$=ue(0),O=ue(null),z=ue(null),ge=ue(null),X=ue(null),Le=ue(!1),he=ue(null),Re=ue(null),Ke=ue(null),Ne=ue(()=>{}),de=sn((m,x)=>{let E=++F.current;O.current||(O.current=ae("enrol_apply/report:rows")),y(!0),ir(n,m,x).then(I=>{E===F.current&&(I.counted&&w(Object.fromEntries(I.filters.map(Ie=>[Ie.name,Ie]))),Le.current||(Le.current=!0,A(Object.fromEntries(I.preference.widths.map(Ie=>[Ie.key,Ie.width])))),I.page!==c.current.page&&(c.current={...c.current,page:I.page},h(c.current)),S(I),K(!1),y(!1))}).catch(()=>{E===F.current&&(K(!0),y(!1))})},[n]),Ee=sn(m=>{let x=++$.current;z.current||(z.current=ae("enrol_apply/report:band")),U(!0),cr(n,m.measure,m.scope,m.search).then(E=>{x===$.current&&(j(E),s(!1),U(!1))}).catch(()=>{x===$.current&&(s(!0),U(!1))})},[n]),ne=sn((m,x,E)=>{c.current={...c.current,...m},h(c.current),x&&de(c.current,E),E&&Ee(c.current)},[de,Ee]);Ot(()=>{!C&&O.current&&(O.current(),O.current=null)},[C,u,W]),Ot(()=>{!G&&z.current&&(z.current(),z.current=null)},[G,T,te]),Ot(()=>{de(c.current,!0),Ee(c.current)},[de,Ee]),Ot(()=>()=>{ge.current!==null&&window.clearTimeout(ge.current),X.current?.(),Ne.current()},[]);let be=m=>{p(m),vt(dr,m)},Ge=m=>{_(m),ge.current!==null&&(window.clearTimeout(ge.current),ge.current=null);let x=m.trim(),E=x.length>=e.searchmin?x:"";if(E===c.current.search){X.current?.(),X.current=null;return}if(m===""){X.current?.(),X.current=null,ne({search:"",page:0},!0,!0);return}X.current||(X.current=ae("enrol_apply/report:search")),ge.current=window.setTimeout(()=>{ge.current=null,ne({search:E,page:0},!0,!0),X.current?.(),X.current=null},Uo)},re=m=>{let x=ae("enrol_apply/report:filters");m().then(()=>{ne({page:0},!0,!0)}).catch(()=>{K(!0)}).finally(x)},De=(u?.filters??[]).map(m=>H[m.name]?{...m,options:H[m.name].options,groupings:H[m.name].groupings}:m),Ae=(m,x)=>{let E=ae("enrol_apply/report:preference");ur(n,m).then(()=>{x&&de(c.current,!1)}).catch(()=>{x&&K(!0)}).finally(E)},oe=u?.columns??[],Te=oe.filter(m=>!m.fixed&&m.active).map(m=>m.key),ke=u?.preference.usedefault??!0,_e=m=>Object.entries(m).map(([x,E])=>({key:x,width:E})),Bt=(m,x)=>{let E=x?[...Te,m]:Te.filter(I=>I!==m);u&&S({...u,columns:u.columns.map(I=>I.key===m?{...I,active:x}:I),preference:{...u.preference,usedefault:!1,columns:E}}),Ae({usedefault:!1,columns:E,widths:_e(f)},!0)},it=()=>{he.current!==null&&(window.clearTimeout(he.current),he.current=null);let m=Ke.current;Ke.current=null,m!==null&&Ae({usedefault:ke,columns:ke?[]:Te,widths:_e(m)},!1),Re.current?.(),Re.current=null};Ne.current=it;let Wt=(m,x,E=!1)=>{let I={...f};if(x===null?delete I[m]:I[m]=x,A(I),Ke.current=I,!E){it();return}he.current!==null&&window.clearTimeout(he.current),Re.current||(Re.current=ae("enrol_apply/report:width")),he.current=window.setTimeout(()=>Ne.current(),Qo)},ct=m=>{let x=new Set(m.columns);u&&S({...u,columns:u.columns.map(E=>E.fixed?E:{...E,active:x.has(E.key)||x.has("answers")&&E.key.startsWith("answer:")}),presets:u.presets.map(E=>({...E,active:E.name===m.name})),preference:{...u.preference,usedefault:!1,columns:m.columns}}),Ae({usedefault:!1,columns:m.columns,widths:_e(f)},!0)},Fe=m=>{let E=(u?.sort??"")===m&&u?.direction==="asc"?"desc":"asc";ne({sort:m,direction:E,page:0},!0,!1)},We=m=>{let x=c.current,E=oe.some(I=>I.key==="wait"&&I.active);ne({measure:m},x.scope!=="all"||x.sort==="wait"||E,!0)},$e=De.some(m=>m.applied)||v.search!==""||v.scope!=="all",ut=oe.filter(m=>m.key.startsWith("identity:")),dt=oe.filter(m=>m.key==="applicant"||!m.fixed&&m.active),ze=l==="cards"||l==="auto"&&a,pt=null;u!==null&&(pt=ze?Pe(ar,{labels:t,columns:dt,identity:ut,rows:u.rows,single:a}):Pe(sr,{labels:t,columns:dt,identity:ut,rows:u.rows,widths:f,sort:u.sort,direction:u.direction,onSort:Fe,onWidth:Wt,onWidthCommit:()=>Ne.current()}));let $t=qo.map(m=>({key:m,title:m==="applicant"?t.applicant:"",sortable:!1,default:!0,active:!0,fixed:m==="applicant"}));return cn("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-reportview",children:[u?.narrowed?Pe("p",{className:"enrol_apply-rview-narrowed",children:t.narrowed}):null,Pe(Xn,{labels:t,summary:T,loading:G,error:te,compactHint:u?.narrowed??!1,measure:v.measure,methods:e.methods,queueurl:e.queueurl,pendingstatus:e.pendingstatus,onMeasure:We,onRetry:()=>Ee(c.current)}),Pe(or,{labels:t,narrow:a,scope:v.scope,total:u===null?null:u.total,filters:De,columns:oe.filter(m=>!m.key.startsWith("identity:")||m.active),presets:u?.presets??[],usedefault:ke,haswidths:Object.keys(f).length>0,view:l,measure:v.measure,search:d,appliedsearch:v.search,searchmin:e.searchmin,searching:C,tableurl:e.tableurl,downloadurl:e.downloadurl,formats:e.formats,onScope:m=>ne({scope:m,page:0},!0,!0),onSearch:Ge,onFilter:(m,x)=>re(()=>ln(r,c.current.measure,er(De,m,x))),onRemoveFilter:m=>re(()=>ln(r,c.current.measure,rn(De,m))),onClearFilters:()=>re(()=>an(r,c.current.measure)),onClearAll:()=>{_(""),c.current={...c.current,search:""},De.some(m=>m.applied)?re(()=>an(r,c.current.measure)):ne({page:0},!0,!0)},onColumn:Bt,onPreset:ct,onDefaultColumns:()=>{u&&S({...u,preference:{...u.preference,usedefault:!0,columns:[]}}),Ae({usedefault:!0,columns:[],widths:_e(f)},!0)},onResetWidths:()=>{A({}),Ae({usedefault:ke,columns:ke?[]:Te,widths:[]},!1)},onView:be}),Pe(Nt,{labels:t,loading:C,loaded:u!==null,error:W,total:u?.total??0,filtered:$e,cards:ze,narrow:a,skeleton:{columns:$t,lead:"applicant",widths:f,sizing:Ft},onRetry:()=>de(c.current,!0),children:pt}),u!==null&&u.total>0?Pe(ft,{labels:t,page:v.page,perpage:v.perpage,perpages:e.perpages,total:u.total,shown:u.rows.length,narrow:a,onPage:m=>ne({page:m},!0,!1),onPerpage:m=>ne({perpage:m,page:0},!0,!1)}):null,e.retention!==""?cn("p",{className:"enrol_apply-rview-retention","data-region":"enrol_apply-retention",children:[e.retention,e.retentionurl!==""?cn(Go,{children:[" ",Pe("a",{href:e.retentionurl,children:t.retentionchange})]}):null]}):null]})},pr=Ko;import{jsx as mr}from"react/jsx-runtime";var zo=e=>e.mount===void 0||e.mount==="report"?mr(pr,{...e}):e.mount==="queue"?mr(zn,{...e}):null,_s=zo;export{_s as default};
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
 * on top, with a grouping select when the facet has groupings (section 5b).
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
 * the group was named, waited more than, applied before) and the identity fields the reader may filter
 * by from a list, which the service does not count; the text fields and the dates are set in the
 * table view, which the panel's note links, and show here as chips that remove them. Columns starts
 * with the three sets (CQ-D7). View is Automatic, Table or Cards, and the table view. No Download
 * (CQ-D11): "Open the report" ends the chips' row for a reader the report admits.
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
 * applicant's name, the identity values, the tags' words and titles - is drawn as text, never as HTML.
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
 */
/**
 * The decision sheet: what the table's sheet (amd/src/manage.js, MQ-D5, MQ-D6) says and asks, as a
 * modal dialog of the view's own.
 *
 * Its title names the decision and the count, its list the first five applicants and how many more;
 * a cancellation says what cancelling removes, a deferral where the application stays, an approval
 * that every place is taken when the table page says so. Then the fields of the table's decision
 * controls, with their labels and help: for an approval the groups and the role where the props offer
 * them (a holder of enrol/apply:choosedecision) or the summary of what an approval gives, then the
 * message to the applicant and the note for the record, which every decision posts. The sheet's commit
 * button is the confirmation, worded as the table's ("Cancel 2"); Back, the close button, Escape and a
 * press outside close it without posting anything.
 *
 * Bootstrap's modal markup, drawn by React rather than by Bootstrap's script, so Moodle's dialogue
 * locator finds it by its title. The focus moves into the dialog when it opens and stays there (Tab and
 * Shift+Tab wrap); the view gives it back to the dock button that opened it.
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
 * @module     enrol_apply/queue/Tiles
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The approval queue of one method as a data report: the method queue's page (stages 3 and 4 of
 * docs/design/confirm-queue-as-report-plan.md, CQ-D5).
 *
 * manage.php?id=<method> renders the shell (enrol_apply\output\queue_view) with configuration and
 * strings, unless view=table asks for today's table; the rows and the tiles come from the plugin's web
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
 * predate the key; `queue` is the approval queue of one method (queue_view, mounted by manage.php with
 * view=grid). Another view is one more branch here and a template whose props name it; the views share
 * the data report's parts, so the bundle carries them once.
 *
 * @module     enrol_apply/views
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
//# sourceMappingURL=bundle.js.map
