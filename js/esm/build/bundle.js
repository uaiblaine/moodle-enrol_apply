import{useCallback as Zt,useEffect as Ke,useRef as Z,useState as ie}from"react";import{useEffect as br,useState as _r}from"react";var Rr=576,mt=e=>{let[t,n]=_r(!1);return br(()=>{let r=e.current;if(!r||typeof ResizeObserver>"u")return;let o=new ResizeObserver(a=>{let l=a[0]?.contentRect.width??r.clientWidth;n(l>0&&l<Rr)});return o.observe(r),()=>o.disconnect()},[e]),t},wt=e=>{try{let t=window.localStorage.getItem(e);return t==="table"||t==="cards"?t:"auto"}catch{return"auto"}},yt=(e,t)=>{try{window.localStorage.setItem(e,t)}catch{}};import{useId as Nr}from"react";var k=(e,t)=>(e||"").split("{$a}").join(t),oe=(e,t)=>Object.entries(t).reduce((n,[r,o])=>n.split(`{$a->${r}}`).join(o),e||""),dn=(e,t,n,r)=>k(n===1&&e[`${t}_one`]?e[`${t}_one`]:e[t],r),_=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(t).format(e)}catch{return String(e)}},Ve=(e,t)=>{let n=Math.max(0,t);if(n<3600)return k(e.waitminutes,_(Math.max(1,Math.round(n/60))));if(n<86400)return k(e.waithours,_(Math.round(n/3600)));let r=Math.floor(n/86400),o=Math.round((n-r*86400)/3600);return o===24&&(r++,o=0),o>0&&r<10?oe(e.waitdayshours,{days:_(r),hours:_(o)}):k(e.waitdays,_(r))},vt=e=>{let t=document.createElement("template");return t.innerHTML=e,(t.content.textContent||"").replace(/\s+/g," ").trim()};import{jsx as Se,jsxs as Ut}from"react/jsx-runtime";var kr=(e,t)=>[...new Set([1,e-1,e,e+1,t])].filter(n=>n>=1&&n<=t).sort((n,r)=>n-r),gt=({labels:e,page:t,perpage:n,perpages:r,total:o,shown:a,narrow:l,onPage:d,onPerpage:s})=>{let i=Nr(),w=Math.max(1,Math.ceil(o/n)),b=t+1,u=o>0?t*n+1:0,R=o>0?u+a-1:0,c=[];if(l)c=[Se("span",{className:"enrol_apply-rview-pager-gap",children:oe(e.pageof,{page:_(b),pages:_(w)})},"of")];else{let C=kr(b,w);C.forEach((x,v)=>{v>0&&x-C[v-1]>1&&c.push(Se("span",{className:"enrol_apply-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${x}`)),c.push(Se("button",{type:"button","aria-current":x===b?"page":void 0,onClick:()=>d(x-1),children:_(x)},x))})}return Ut("div",{className:"enrol_apply-rview-foot",children:[Se("span",{className:"enrol_apply-rview-num","data-region":"enrol_apply-range",children:oe(e.range,{from:_(u),to:_(R),total:_(o)})}),w>1?Ut("nav",{className:"enrol_apply-rview-pager","aria-label":e.pages,children:[Se("button",{type:"button",disabled:b===1,onClick:()=>d(t-1),children:e.previous}),c,Se("button",{type:"button",disabled:b===w,onClick:()=>d(t+1),children:e.next})]}):null,Ut("span",{className:"enrol_apply-rview-perpage",children:[Se("label",{htmlFor:i,children:e.perpage}),Se("select",{id:i,className:"form-select form-select-sm",value:n,onChange:C=>s(Number(C.target.value)),children:r.map(C=>Se("option",{value:C,children:_(C)},C))})]})]})};var le=e=>{let t=window.M?.util;t?.js_pending(e);let n=!0;return()=>{n&&(n=!1,t?.js_complete(e))}};import{useState as xr}from"react";import{useEffect as Sr,useRef as bt}from"react";var ft=(e,t,n,r,o)=>({defaultWidth:a=>e[a]??t,minimumWidth:a=>n[a]??r,maximum:o}),we=(e,t,n)=>Math.min(e.maximum,Math.max(e.minimumWidth(n),t[n]??e.defaultWidth(n)));import{jsx as D,jsxs as Me}from"react/jsx-runtime";var ht=16,Cr={ascending:"\u25B2",descending:"\u25BC",none:""},Er=(e,t)=>e?t==="asc"?"ascending":"descending":"none",Mr=({selection:e,rows:t})=>{let n=bt(null),r=t.filter(l=>!e.locked.has(l.id)),o=r.filter(l=>e.selected.has(l.id)).length,a=r.length>0&&o===r.length;return Sr(()=>{n.current&&(n.current.indeterminate=o>0&&!a)},[o,a]),Me("label",{className:"enrol_apply-rview-pickwrap",children:[D("input",{ref:n,type:"checkbox",className:"enrol_apply-rview-pick",checked:a,disabled:r.length===0,onChange:l=>e.onSelectPage(l.target.checked)}),D("span",{className:"visually-hidden",children:e.pagelabel})]})},Qt=({selection:e,row:t})=>e.locked.has(t.id)?D("span",{className:"enrol_apply-rview-pickwrap enrol_apply-rview-pick--none"}):Me("label",{className:"enrol_apply-rview-pickwrap",children:[D("input",{type:"checkbox",className:"enrol_apply-rview-pick",checked:e.selected.has(t.id),onChange:n=>e.onSelect(t.id,n.target.checked)}),D("span",{className:"visually-hidden",children:e.label(t)})]}),_t=({labels:e,columns:t,lead:n,rows:r,widths:o,sizing:a,sort:l,direction:d,selection:s,renderLead:i,renderCell:w,onSort:b,onWidth:u,onWidthCommit:R})=>{let c=bt(null),C=bt(null),x=bt(null),v=t.reduce((y,f)=>y+we(a,o,f.key),0),O=y=>{y.currentTarget.classList.toggle("is-scrolled",y.currentTarget.scrollLeft>0)},K=(y,f)=>{let I=C.current?.querySelector(`col[data-col="${CSS.escape(y)}"]`);!I||!C.current||(I.style.width=`${f}px`,C.current.style.width=`${v-we(a,o,y)+f}px`)},F=(y,f)=>Math.round(Math.min(a.maximum,Math.max(a.minimumWidth(y),f))),me=(y,f)=>{if(y.button!==0)return;y.preventDefault(),y.currentTarget.setPointerCapture(y.pointerId),y.currentTarget.classList.add("is-active");let I=we(a,o,f);x.current={key:f,x:y.clientX,width:I,now:I}},G=y=>{let f=x.current;if(!f)return;let I=document.documentElement.dir==="rtl",h=(y.clientX-f.x)*(I?-1:1),p=F(f.key,f.width+h);p!==f.now&&(K(f.key,p),f.now=p)},B=y=>{let f=x.current;x.current=null,y.currentTarget.classList.remove("is-active"),f&&f.now!==f.width&&u(f.key,f.now)},ne=(y,f)=>{let I=document.documentElement.dir==="rtl",h=0;if(y.key==="ArrowRight")h=I?-ht:ht;else if(y.key==="ArrowLeft")h=I?ht:-ht;else if(y.key==="Home"){y.preventDefault(),u(f,null,!0);return}if(h===0)return;y.preventDefault();let p=F(f,we(a,o,f)+h);p!==we(a,o,f)&&u(f,p,!0)};return D("div",{className:"enrol_apply-rview-gridframe",ref:c,tabIndex:0,role:"region","aria-label":e.grid,onScroll:O,children:Me("table",{className:"enrol_apply-rview-grid",ref:C,style:{width:`${v}px`},children:[D("caption",{className:"visually-hidden",children:e.caption}),D("colgroup",{children:t.map(y=>D("col",{"data-col":y.key,style:{width:`${we(a,o,y.key)}px`}},y.key))}),D("thead",{children:D("tr",{children:t.map(y=>{let f=Er(l===y.key,d),I=D("span",{className:"enrol_apply-rview-th-title",children:y.title}),h=y.sortable?Me("button",{type:"button",className:"enrol_apply-rview-sortbtn",onClick:()=>b(y.key),children:[I,D("span",{className:"enrol_apply-rview-arrow","aria-hidden":"true",children:Cr[f]})]}):D("span",{className:"enrol_apply-rview-plain",children:I});return Me("th",{scope:"col",className:y.key===n?"enrol_apply-rview-fixed":void 0,"aria-sort":y.sortable?f:void 0,title:y.title,"data-column":y.key,children:[s&&y.key===n?Me("div",{className:"enrol_apply-rview-lead",children:[D(Mr,{selection:s,rows:r}),h]}):h,D("span",{className:"enrol_apply-rview-resize",role:"separator","aria-orientation":"vertical","aria-label":k(e.resize,y.title),"aria-valuemin":a.minimumWidth(y.key),"aria-valuemax":a.maximum,"aria-valuenow":we(a,o,y.key),tabIndex:0,onPointerDown:p=>me(p,y.key),onPointerMove:G,onPointerUp:B,onPointerCancel:B,onDoubleClick:()=>{we(a,o,y.key)!==a.defaultWidth(y.key)&&u(y.key,null)},onKeyDown:p=>ne(p,y.key),onBlur:R})]},y.key)})})}),D("tbody",{children:r.map(y=>D("tr",{"data-record":y.id,className:s?.selected.has(y.id)?"enrol_apply-rview-row--selected":void 0,children:t.map(f=>f.key===n?D("th",{scope:"row",className:"enrol_apply-rview-fixed",children:s?Me("div",{className:"enrol_apply-rview-lead",children:[D(Qt,{selection:s,row:y}),i(y,f)]}):i(y,f)},f.key):D("td",{children:w(y,f)},f.key))},y.id))})]})})},pn=({columns:e,lead:t,widths:n,sizing:r})=>{let o=e.reduce((l,d)=>l+we(r,n,d.key),0),a=["enrol_apply-rview-skb--w80","enrol_apply-rview-skb--w60","enrol_apply-rview-skb--w40"];return D("div",{className:"enrol_apply-rview-gridframe","aria-hidden":"true",children:Me("table",{className:"enrol_apply-rview-grid",style:{width:`${o}px`},children:[D("colgroup",{children:e.map(l=>D("col",{style:{width:`${we(r,n,l.key)}px`}},l.key))}),D("thead",{children:D("tr",{children:e.map(l=>D("th",{className:l.key===t?"enrol_apply-rview-fixed":void 0,children:D("span",{className:"enrol_apply-rview-plain",children:D("span",{className:"enrol_apply-rview-th-title",children:l.title})})},l.key))})}),D("tbody",{children:Array.from({length:8},(l,d)=>D("tr",{className:"enrol_apply-rview-sk",children:e.map((s,i)=>D("td",{className:s.key===t?"enrol_apply-rview-fixed":void 0,children:D("span",{className:"enrol_apply-rview-skb "+a[(d+i)%3]})},s.key))},d))})]})})};import{jsx as de,jsxs as Xe}from"react/jsx-runtime";var Pr=({labels:e,row:t,single:n,selection:r,renderCard:o})=>{let[a,l]=xr(!1),d=o(t),s=!n||a,i=r?.selected.has(t.id)??!1;return Xe("article",{className:"enrol_apply-rview-card"+(i?" enrol_apply-rview-card--selected":""),role:"listitem","data-record":t.id,children:[Xe("div",{className:"enrol_apply-rview-card-l1",children:[r?de(Qt,{selection:r,row:t}):null,d.head]}),d.lines,d.fields.length>0?Xe("div",{className:"enrol_apply-rview-card-more",children:[n?de("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":a,onClick:()=>l(!a),children:a?e.less:e.more}):null,s?de("dl",{className:"enrol_apply-rview-card-list",children:d.fields.map(w=>Xe("div",{className:"enrol_apply-rview-card-item",children:[de("dt",{children:w.title}),de("dd",{children:w.content})]},w.key))}):null]}):null]})},Rt=({labels:e,rows:t,single:n,selection:r,renderCard:o})=>de("div",{className:"enrol_apply-rview-cards"+(n?" enrol_apply-rview-cards--single":""),role:"list",children:t.map(a=>de(Pr,{labels:e,row:a,single:n,selection:r,renderCard:o},a.id))}),mn=({single:e})=>de("div",{className:"enrol_apply-rview-cards"+(e?" enrol_apply-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:e?4:6},(t,n)=>Xe("div",{className:"enrol_apply-rview-card enrol_apply-rview-sk",children:[de("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),de("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"}),de("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),de("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"})]},n))});import{Fragment as wn,jsx as Ce,jsxs as Ue}from"react/jsx-runtime";var Lr=({labels:e,loaded:t,error:n,total:r,filtered:o,cards:a,narrow:l,skeleton:d,children:s,onRetry:i})=>{let w=Ue("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",Ce("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:i,children:e.retry})]});return!t&&n?w:t?r===0?Ue("div",{className:"enrol_apply-rview-empty",children:[Ce("p",{className:"enrol_apply-rview-empty-title",children:o?e.nomatch:e.empty}),o?Ce("p",{className:"enrol_apply-rview-empty-text",children:e.nomatchhelp}):null]}):Ue("div",{className:"enrol_apply-rview-dim",children:[n?w:null,s,a?null:Ce("p",{className:"enrol_apply-rview-hint",children:e.scrollhint})]}):Ue(wn,{children:[a?Ce(mn,{single:l}):Ce(pn,{...d}),Ce("span",{className:"visually-hidden",role:"status",children:e.loading})]})},Nt=e=>Ue(wn,{children:[Ue("section",{className:"enrol_apply-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"enrol_apply-rows",children:[e.loading&&e.loaded?Ce("div",{className:"enrol_apply-rview-progress"}):null,Ce(Lr,{...e})]}),e.dock??null]});var Dr="table",qt="enrolApplyQueue",Ar=(e,t,n)=>{let r=[];return t.scope!=="all"&&r.push(["status",String(t.scope==="pending"?e.statuses.pending:e.statuses.deferred)]),t.search!==""&&r.push(["search",t.search]),Object.keys(t.filters).sort().forEach(o=>{t.filters[o]!==""&&r.push([o,t.filters[o]])}),n?(t.sort!==""&&(t.sort!=="wait"||t.direction!=="asc")&&r.push(["sort",t.sort],["dir",t.direction]),t.page>0&&r.push(["page",String(t.page)]),t.perpage!==e.perpage&&r.push(["perpage",String(t.perpage)])):r.push(["view",Dr]),r},Ze=(e,t,n)=>{let r=new URL(e.pageurl,window.location.href);return Ar(e,t,n).forEach(([o,a])=>r.searchParams.set(o,a)),r.toString()},yn=e=>({scope:e.initial.scope,search:e.initial.search,filters:Object.fromEntries(e.initial.filters.map(t=>[t.name,t.value])),sort:e.initial.sort,direction:e.initial.direction,page:e.initial.page,perpage:e.initial.perpage}),kt=(e,t,n)=>{let r=Ze(e,t,!0),o={[qt]:t};try{n&&r!==window.location.href?window.history.pushState(o,"",r):window.history.replaceState(o,"",r)}catch{}document.querySelectorAll('[data-enrol-apply-queue="tablelink"]').forEach(a=>{a.href=Ze(e,t,!1)})},vn=e=>e===null||typeof e!="object"||!(qt in e)?null:e[qt];import{useEffect as Ct,useId as Ur,useRef as tt,useState as kn}from"react";import{useId as Fr,useState as fn}from"react";import{jsx as gn}from"react/jsx-runtime";var Tr={filter:"M2 3h12l-4.5 5.5V13l-3 1.5V8.5L2 3z",columns:"M2 3h12v10H2zM6.5 3v10M10 3v10",table:"M2 3h12v10H2zM2 6.5h12M2 10h12M6 6.5V13",cards:"M2 3h5v4H2zM9 3h5v4H9zM2 9h5v4H2zM9 9h5v4H9z",auto:"M2 3h7v10H2zM11 3h3v4h-3zM11 9h3v4h-3z",download:"M8 2v8M4.5 7 8 10.5 11.5 7M3 13h10",search:"M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM10.5 10.5 14 14",close:"M4 4l8 8M12 4l-8 8",eraser:"M9.5 2 14 6.5 7.5 13 3 8.5zM5.6 5.9l4.5 4.5M7.5 13H14",tick:"M3 8.5 6.5 12 13 4.5",chevron:"M4 6l4 4 4-4",reset:"M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5",queue:"M3 4h10M3 8h10M3 12h6"},U=({name:e,className:t})=>gn("svg",{className:"enrol_apply-rview-icon"+(t?" "+t:""),viewBox:"0 0 16 16",width:"16",height:"16",fill:"none",stroke:"currentColor",strokeWidth:"1.5",strokeLinecap:"round",strokeLinejoin:"round","aria-hidden":"true",focusable:"false",children:gn("path",{d:Tr[e]??""})});import{Fragment as Wr,jsx as Q,jsxs as ye}from"react/jsx-runtime";var Ir=8,Kt=50,Vr=(e,t)=>t===null?e.show:k(e.showcount,_(t)),hn=({option:e})=>e.count>=0?Q("small",{className:"enrol_apply-rview-optcount",children:_(e.count)}):null,Hr=(e,t)=>e.options.filter(n=>n.count!==0||n.value===t),Or=({labels:e,facet:t,shown:n,value:r,headid:o,onChange:a})=>{let[l,d]=fn(""),[s,i]=fn(""),w=l.trim().toLocaleLowerCase(),b=t.groupings.length>1?t.groupings:[],u=b.find(v=>String(v.id)===s)?.groups.map(String)??null,R=n.filter(v=>v.value===r),c=n.filter(v=>v.value!==r&&(w===""||v.label.toLocaleLowerCase().includes(w))&&(u===null||u.includes(v.value))),C=k(t.find.label,_(t.options.length)),x=b.length>0?e.moregroupsgrouping:e.moregroups;return ye(Wr,{children:[ye("div",{className:"enrol_apply-rview-find",children:[Q("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:C,"aria-label":C,value:l,onChange:v=>d(v.target.value)}),b.length>0?ye("select",{className:"form-select form-select-sm enrol_apply-rview-grouping","aria-label":e.grouping,value:s,onChange:v=>i(v.target.value),children:[Q("option",{value:"",children:e.allgroupings}),b.map(v=>Q("option",{value:String(v.id),children:v.name},v.id))]}):null]}),ye("div",{className:"enrol_apply-rview-optlist",role:"group","aria-labelledby":o,children:[[...R,...c.slice(0,Kt)].map(v=>ye("button",{type:"button",className:"enrol_apply-rview-optrow","aria-pressed":v.value===r,onClick:()=>a(t.name,v.value===r?"":v.value),children:[Q("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:Q(U,{name:"tick"})}),Q("span",{className:"enrol_apply-rview-optrow-label",children:v.label})," ",Q(hn,{option:v})]},v.value)),c.length>Kt?Q("p",{className:"enrol_apply-rview-note",children:k(x,_(c.length-Kt))}):null,R.length===0&&c.length===0?Q("p",{className:"enrol_apply-rview-note",children:t.find.nomatch}):null]})]})},Br=({labels:e,facet:t,value:n,onChange:r})=>{let o=Fr(),a=Hr(t,n),l=[{value:"",label:e.any,count:-1},...a];if(t.options.length>Ir)return ye("div",{className:"enrol_apply-rview-fgroup",children:[Q("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),Q(Or,{labels:e,facet:t,shown:a,value:n,headid:o,onChange:r})]});let d=s=>{let i=l.findIndex(b=>b.value===n),w=-1;s.key==="ArrowRight"||s.key==="ArrowDown"?w=(i+1)%l.length:s.key==="ArrowLeft"||s.key==="ArrowUp"?w=(i-1+l.length)%l.length:s.key==="Home"?w=0:s.key==="End"&&(w=l.length-1),!(w<0)&&(s.preventDefault(),r(t.name,l[w].value),s.currentTarget.querySelectorAll('[role="radio"]')[w]?.focus())};return ye("div",{className:"enrol_apply-rview-fgroup",children:[Q("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),Q("div",{className:"enrol_apply-rview-fpanel-opts",role:"radiogroup","aria-labelledby":o,onKeyDown:d,children:l.map(s=>ye("button",{type:"button",className:"enrol_apply-rview-fpanel-opt",role:"radio","aria-checked":s.value===n,tabIndex:s.value===n?0:-1,onClick:()=>r(t.name,s.value),children:[s.label," ",Q(hn,{option:s})]},s.value))})]})},bn=({labels:e,id:t,hidden:n,facets:r,values:o,total:a,note:l,onChange:d,onClear:s,onShow:i})=>ye("div",{className:"enrol_apply-rview-fpanel",id:t,role:"dialog","aria-label":e.filters,hidden:n,children:[ye("div",{className:"enrol_apply-rview-fpanel-body",children:[r.map(w=>Q(Br,{labels:e,facet:w,value:o[w.name]??"",onChange:d},w.name)),l??null]}),ye("div",{className:"enrol_apply-rview-fpanel-foot",children:[Q("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:s,children:e.clearfilters}),Q("button",{type:"button",className:"btn btn-sm btn-primary",onClick:i,children:Vr(e,a)})]})]});import{Fragment as $r,jsx as Je,jsxs as _n}from"react/jsx-runtime";var St=e=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(e.key))return;let t=Array.from(e.currentTarget.querySelectorAll('[role^="menuitem"]'));if(t.length===0)return;e.preventDefault();let n=t.indexOf(document.activeElement),r=0;e.key==="End"?r=t.length-1:e.key==="ArrowDown"?r=(n+1)%t.length:e.key==="ArrowUp"&&(r=(n-1+t.length)%t.length),t[r].focus()},Rn=({heading:e,sets:t,onSet:n})=>t.length===0?null:_n($r,{children:[Je("div",{className:"enrol_apply-rview-menu-head",role:"presentation",children:e}),t.map(r=>_n("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":r.active,tabIndex:-1,"data-preset":r.name,onClick:()=>{r.active||n(r.name)},children:[Je("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:Je(U,{name:"tick"})}),Je("span",{className:"enrol_apply-rview-menu-label",children:r.label})]},r.name)),Je("div",{className:"enrol_apply-rview-menu-sep",role:"separator"})]});import{jsx as Nn}from"react/jsx-runtime";var et=({label:e,value:t,options:n,small:r,onChange:o})=>{let a=l=>{let d=n.findIndex(w=>w.value===t),s=-1;if(l.key==="ArrowRight"||l.key==="ArrowDown"?s=(d+1)%n.length:l.key==="ArrowLeft"||l.key==="ArrowUp"?s=(d-1+n.length)%n.length:l.key==="Home"?s=0:l.key==="End"&&(s=n.length-1),s<0)return;l.preventDefault(),o(n[s].value),l.currentTarget.querySelectorAll('[role="radio"]')[s]?.focus()};return Nn("div",{className:"enrol_apply-rview-seg"+(r?" enrol_apply-rview-seg--small":""),role:"radiogroup","aria-label":e,onKeyDown:a,children:n.map(l=>Nn("button",{type:"button",className:"enrol_apply-rview-seg-cell",role:"radio","aria-checked":l.value===t,tabIndex:l.value===t?0:-1,onClick:()=>o(l.value),children:l.label},l.value))})};import{Fragment as Sn,jsx as N,jsxs as W}from"react/jsx-runtime";var Et=e=>{let{labels:t,chips:n,columns:r}=e,o=Ur(),a=tt(null),l=tt(null),d=tt(null),s=tt(!1),[i,w]=kn(null),[b,u]=kn(e.search!==""),R=tt({});Ct(()=>{if(i===null)return;let p=T=>{a.current&&!a.current.contains(T.target)&&w(null)};return document.addEventListener("mousedown",p),()=>document.removeEventListener("mousedown",p)},[i]),Ct(()=>{if(i===null||i==="filters")return;let p=a.current?.querySelector(`#${CSS.escape(o+i)}`);(p?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])')??p?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])'))?.focus()},[i,o]),Ct(()=>{b?l.current?.focus():s.current&&(s.current=!1,d.current?.focus())},[b]),Ct(()=>{let p=T=>{if(T.key!=="/"||T.ctrlKey||T.metaKey||T.altKey||T.defaultPrevented)return;let $=document.activeElement;if($&&($.isContentEditable||$.closest("input, textarea, select")))return;let V=a.current?.getBoundingClientRect();!V||V.width===0||V.bottom<=0||V.top>=window.innerHeight||(T.preventDefault(),b?l.current?.focus():(w(null),u(!0)))};return document.addEventListener("keydown",p),()=>document.removeEventListener("keydown",p)},[b]);let c=p=>w(i===p?null:p),C=p=>{s.current=p,u(!1)},x=p=>{if(!(p.key!=="Escape"||p.defaultPrevented))if(i!==null){p.preventDefault();let T=R.current[i];w(null),T?.focus()}else b&&(p.preventDefault(),C(!0))},v=e.search.trim().length>=e.searchmin,O=v&&!b,K=n.length+(O?1:0),F=e.total===null?"\u2026":dn(t,"count",e.total,_(e.total)),me=e.total!==null&&e.narrow?W(Sn,{children:[N("span",{"aria-hidden":"true",children:_(e.total)}),N("span",{className:"visually-hidden",children:F})]}):F,G=(p,T,$,V)=>W("button",{type:"button",className:"enrol_apply-rview-iconbtn","aria-label":$,"data-enrol-apply-tip":$,"aria-haspopup":p==="filters"?"dialog":"menu","aria-expanded":i===p,"aria-controls":o+p,ref:z=>{R.current[p??""]=z},onClick:()=>c(p),children:[N(U,{name:T}),V]}),B={auto:"auto",table:"table",cards:"cards"}[e.view],ne=N(U,{name:"chevron",className:"enrol_apply-rview-chevron"}),y=n.length>0?k(t.filterson,_(n.length)):t.filters,f=n.length>0?N("span",{className:"enrol_apply-rview-badge","aria-hidden":"true",children:_(n.length)}):null,I=[["auto",t.viewauto,t.viewautohint],["table",t.table,""],["cards",t.cards,""]].filter(([p])=>e.views.includes(p)),h="enrol_apply-rview-bar"+(e.narrow?" enrol_apply-rview-bar--narrow":"")+(b?" enrol_apply-rview-bar--searching":"");return W("div",{className:h,"data-region":"enrol_apply-bar",ref:a,onKeyDown:x,children:[N(et,{label:t.scope,value:e.scope,options:e.scopes,onChange:e.onScope}),N("span",{className:"enrol_apply-rview-bar-spacer"}),W("div",{className:"enrol_apply-rview-bar-controls",children:[W("div",{className:"enrol_apply-rview-bar-cluster",children:[N("span",{className:"enrol_apply-rview-bar-count","data-region":"enrol_apply-total","aria-live":"polite",children:me}),W("div",{className:"enrol_apply-rview-capsule",children:[G("filters","filter",y,f),W("div",{className:"enrol_apply-rview-anchor",children:[G("columns","columns",t.columns,ne),W("div",{className:"enrol_apply-rview-menu",id:o+"columns",role:"menu","aria-label":t.columns,hidden:i!=="columns",onKeyDown:St,children:[N(Rn,{heading:t.presets,sets:e.sets,onSet:e.onSet}),r.map(p=>p.fixed?W("div",{className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":"true","aria-disabled":"true",tabIndex:-1,children:[N("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:N(U,{name:"tick"})}),N("span",{className:"enrol_apply-rview-menu-label",children:p.title}),N("span",{className:"enrol_apply-rview-menu-hint",children:t.always})]},p.key):W("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":p.active,tabIndex:-1,onClick:()=>e.onColumn(p.key,!p.active),children:[N("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:N(U,{name:"tick"})}),N("span",{className:"enrol_apply-rview-menu-label",children:p.title})]},p.key)),N("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),W("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":e.usedefault,onClick:()=>{e.usedefault||e.onDefaultColumns()},children:[N(U,{name:"columns"}),N("span",{className:"enrol_apply-rview-menu-label",children:t.defaultcolumns})]}),W("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":!e.haswidths,onClick:()=>{e.haswidths&&e.onResetWidths()},children:[N(U,{name:"reset"}),N("span",{className:"enrol_apply-rview-menu-label",children:t.resetwidths})]})]})]}),W("div",{className:"enrol_apply-rview-anchor",children:[G("view",B,t.view,ne),W("div",{className:"enrol_apply-rview-menu",id:o+"view",role:"menu","aria-label":t.view,hidden:i!=="view",onKeyDown:St,children:[I.map(([p,T,$])=>W("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===p,tabIndex:-1,onClick:()=>{e.onView(p),w(null),R.current.view?.focus()},children:[N(U,{name:p}),N("span",{className:"enrol_apply-rview-menu-label",children:T}),$!==""?N("span",{className:"enrol_apply-rview-menu-hint",children:$}):null,N(U,{name:"tick",className:"enrol_apply-rview-tick"})]},p)),e.tableurl!==""?W(Sn,{children:[N("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),W("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:e.tableurl,children:[N(U,{name:"table"}),N("span",{className:"enrol_apply-rview-menu-label",children:t.coretable})]})]}):null]})]}),e.downloads.length>0?W("div",{className:"enrol_apply-rview-anchor",children:[G("download","download",t.download,ne),N("div",{className:"enrol_apply-rview-menu",id:o+"download",role:"menu","aria-label":t.download,hidden:i!=="download",onKeyDown:St,children:e.downloads.map(p=>W("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:p.href,children:[N(U,{name:"download"}),N("span",{className:"enrol_apply-rview-menu-label",children:p.label})]},p.key))})]}):null]})]}),N("div",{className:"enrol_apply-rview-capsule enrol_apply-rview-searchcap",children:b?W("span",{className:"enrol_apply-rview-search",children:[e.searching&&v?N("span",{className:"enrol_apply-rview-spin","aria-hidden":"true"}):N(U,{name:"search"}),N("input",{ref:l,type:"search","aria-label":t.search,placeholder:t.search,autoComplete:"off",value:e.search,onChange:p=>e.onSearch(p.target.value),onKeyDown:p=>{p.key==="Escape"&&(p.preventDefault(),C(!0))},onBlur:()=>{e.search===""&&C(!1)}}),e.search!==""?N("button",{type:"button",className:"enrol_apply-rview-eraser","aria-label":t.cleartext,"data-enrol-apply-tip":t.cleartext,onMouseDown:p=>p.preventDefault(),onClick:()=>{e.onSearch(""),l.current?.focus()},children:N(U,{name:"eraser"})}):null]}):N("button",{ref:d,type:"button",className:"enrol_apply-rview-iconbtn","aria-label":t.search,"data-enrol-apply-tip":t.search,onClick:()=>{w(null),u(!0)},children:N(U,{name:"search"})})}),N(bn,{labels:t,id:o+"filters",hidden:i!=="filters",facets:e.facets,values:e.values,total:e.total,note:e.panelnote,onChange:e.onFacet,onClear:e.onClearFilters,onShow:()=>{w(null),R.current.filters?.focus()}})]}),W("div",{className:"enrol_apply-rview-chips","data-region":"enrol_apply-chips",hidden:K===0&&!e.aside,children:[n.map(p=>W("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":k(t.removefilter,p.text),onClick:()=>e.onRemoveChip(p.key),children:[N("span",{children:p.text}),N(U,{name:"close"})]},p.key)),O?W("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":k(t.removefilter,k(t.searchchip,e.search.trim())),onClick:()=>e.onSearch(""),children:[N("span",{children:k(t.searchchip,e.search.trim())}),N(U,{name:"close"})]}):null,K>1?N("button",{type:"button",className:"enrol_apply-rview-clearall",onClick:()=>{e.onClearAll(),u(!1)},children:t.clearall}):null,e.aside??null]})]})};import{jsx as Gt,jsxs as zr}from"react/jsx-runtime";var nt="all",Qe="group",Qr=["auto","table","cards"],qr=(e,t)=>{switch(t){case"everything":return e.preseteverything;case"glance":return e.presetglance;case"answers":return e.presetanswers;default:return t}},Kr=(e,t)=>{let n={label:e.searchgroups,nomatch:e.nogroupmatch},r=t.facets.map(a=>({name:a.name,title:a.title,options:a.options,groupings:[],find:n})),o=t.fieldfilters.filter(a=>a.control==="select").map(a=>({name:a.name,title:a.title,options:a.options.map(l=>({...l,count:-1})),groupings:[],find:n}));return[...r,...o]},Gr=(e,t,n,r)=>{let o=n,a=r,l=t.facets.find(s=>s.name===n),d=t.fieldfilters.find(s=>s.name===n);return l?(o=l.title,a=l.options.find(s=>s.value===r)?.label??r):d?(o=d.title,a=d.options.find(s=>s.value===r)?.label??r):n==="appliedfrom"?o=e.appliedfrom:n==="appliedto"&&(o=e.appliedto),{key:n,text:oe(e.filterchip,{title:o,value:a})}},Cn=e=>{let{labels:t,rows:n,query:r}=e,o=e.presets.map(d=>({name:d.name,label:qr(t,d.name),active:d.active})),a=Object.fromEntries(Object.entries(r.filters).map(([d,s])=>[d,d===Qe&&s===nt?"":s])),l=n===null?[]:n.filters.map(d=>Gr(t,n,d.name,d.value));return Gt(Et,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"pending",label:t.pending},{value:"deferred",label:t.deferred}],scope:r.scope,total:n===null?null:n.total,facets:n===null?[]:Kr(t,n),values:a,panelnote:zr("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",Gt("a",{href:e.tableurl,children:t.morefilters})]}),chips:l,aside:e.reporturl!==""?Gt("a",{className:"enrol_apply-qview-tolink",href:e.reporturl,children:t.openreport}):null,columns:e.columns,sets:o,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:Qr,tableurl:e.tableurl,downloads:[],search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:d=>e.onScope(d),onSearch:e.onSearch,onFacet:e.onFilter,onRemoveChip:d=>e.onFilter(d,""),onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:d=>{let s=e.presets.find(i=>i.name===d);s&&e.onPreset(s)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Yr,jsx as En}from"react/jsx-runtime";var Mn=(e,t)=>t?e.split(`
`).map(n=>n.trim()).filter(n=>n!==""):[e],Mt=({parts:e})=>En(Yr,{children:e.map((t,n)=>En("span",{className:n===0?"enrol_apply-rview-c":"enrol_apply-rview-sub",title:vt(t),dangerouslySetInnerHTML:{__html:t}},n))});import{Fragment as ao,jsx as ae,jsxs as xt}from"react/jsx-runtime";var jr={applicant:320,wait:150,group:180,comment:260,history:130},Xr=180,Zr={applicant:200},Jr=80,eo=800,Pt=ft(jr,Xr,Zr,Jr,eo),to={deferred:"enrol_apply-status--warning",key:"enrol_apply-status--info",appliedbefore:"enrol_apply-status--neutral",own:"enrol_apply-status--neutral"},He=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",zt=({html:e})=>ae(Mt,{parts:[e]}),no=({tag:e})=>{let t=to[e.kind]??"enrol_apply-status--neutral",n=e.title!==""&&e.title!==e.text;return xt("span",{className:"enrol_apply-status "+t,title:n?e.title:void 0,children:[e.text,n?ae("span",{className:"visually-hidden",children:" "+e.title}):null]})},ro=({row:e})=>e.applicant.profileurl===""?ae("span",{className:"enrol_apply-qview-name",children:e.applicant.fullname}):ae("a",{className:"enrol_apply-qview-name",href:e.applicant.profileurl,children:e.applicant.fullname}),oo=({labels:e,row:t})=>ae("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:t.reviewurl,"aria-label":k(e.reviewapplicant,t.applicant.fullname),children:e.review}),lo=({row:e})=>xt(ao,{children:[ae("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:e.applicant.picture}}),xt("div",{className:"enrol_apply-qview-whotext",children:[ae("span",{className:"enrol_apply-rview-c",children:ae(ro,{row:e})}),e.applicant.identity.map(t=>ae("span",{className:"enrol_apply-rview-sub",title:t.value,children:t.value},t.name)),e.applicant.tags.length>0?ae("span",{className:"enrol_apply-qview-tags",children:e.applicant.tags.map(t=>ae(no,{tag:t},t.kind))}):null]})]}),Yt=({labels:e,row:t})=>xt("div",{className:"enrol_apply-qview-who",children:[ae(lo,{row:t}),ae(oo,{labels:e,row:t})]});var Oe=(e,t,n)=>{let r=document.createElement("input");r.type="hidden",r.name=t,r.value=n,e.appendChild(r)},xn=e=>{let t=document.createElement("form");t.method="post",t.action=e.action,t.hidden=!0,Oe(t,"sesskey",e.sesskey),Oe(t,"formaction",e.decision),e.userenrolments.forEach(n=>Oe(t,"userenrolments[]",String(n))),Oe(t,"outcomemessage",e.message),Oe(t,"decisionnote",e.note),e.decision==="confirm"&&(e.groups?.forEach(n=>Oe(t,"groups[]",String(n))),e.roleid!==null&&Oe(t,"roleid",String(e.roleid))),document.body.appendChild(t),t.submit()};var Pn=(e,t)=>{let n=t.decision!==null&&e!==null&&e.candecide;return{decides:n,notingroup:!!e?.notingroup,narrowed:!!e?.narrowed,viewonly:e!==null&&!n,groupdefault:e?.groupdefault??"",allcolumns:e?.columns??[],usedefault:e?.preference.usedefault??!0,presets:e?.presets??[],total:e?.total??0,list:(e?.rows??[]).map(r=>({...r,id:r.userenrolmentid}))}},Ln=(e,t,n,r)=>{let{list:o,decides:a}=n,l=new Set(o.filter(u=>!u.selectable).map(u=>u.id)),d=o.filter(u=>e.has(u.id)),s=(u,R)=>t(c=>{let C=new Set(c);return R&&!l.has(u)?C.add(u):C.delete(u),C}),i=u=>t(()=>u?new Set(o.filter(R=>!l.has(R.id)).map(R=>R.id)):new Set),b=a?{selected:e,locked:l,label:u=>k(r.selectapplicant,u.applicant.fullname),pagelabel:r.selectpage,onSelect:s,onSelectPage:i}:void 0;return{locked:l,chosen:d,selection:b}};import{jsx as jt,jsxs as so}from"react/jsx-runtime";var Dn=({labels:e,count:t,actions:n,onDecide:r})=>so("div",{className:"enrol_apply-qview-dock",role:"region","aria-label":e.dock,"data-region":"enrol_apply-dock",children:[jt("span",{className:"enrol_apply-qview-dockcount","aria-live":"polite",children:k(e.selected,_(t))}),jt("div",{className:"enrol_apply-qview-dockactions",children:n.map(o=>jt("button",{type:"button",className:"btn "+o.classes,onClick:a=>r(o.value,a.currentTarget),children:o.label},o.value))})]});import{useEffect as io,useId as co,useRef as An,useState as qe}from"react";import{Fragment as mo,jsx as A,jsxs as X}from"react/jsx-runtime";var Tn=5,uo="button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), a[href]",po=(e,t,n)=>{let r=_(n);return t==="cancel"?[k(n===1?e.sheetcancelone:e.sheetcancel,r),k(e.sheetcancelcommit,r)]:t==="wait"?[k(n===1?e.sheetwaitone:e.sheetwait,r),k(e.sheetwaitcommit,r)]:[k(n===1?e.sheetconfirmone:e.sheetconfirm,r),k(e.sheetconfirmcommit,r)]},Lt=({id:e,text:t})=>A("div",{className:"form-text",id:e,children:t}),Fn=({labels:e,decision:t,rows:n,offer:r,onClose:o,onCommit:a})=>{let l=co(),d=An(null),s=An(null),[i,w]=qe(""),[b,u]=qe(""),[R]=qe(()=>(r.groups?.options??[]).filter(h=>h.selected).map(h=>String(h.id))),[c,C]=qe(R),[x,v]=qe("0"),[O,K]=qe(!1),F=n.length,[me,G]=po(e,t,F),B=t==="confirm";io(()=>(s.current?.focus(),document.body.classList.add("modal-open"),()=>document.body.classList.remove("modal-open")),[]);let ne=h=>{if(h.key==="Escape"){h.preventDefault(),h.stopPropagation(),O||o();return}if(h.key!=="Tab"||!d.current)return;let p=Array.from(d.current.querySelectorAll(uo));if(p.length===0){h.preventDefault();return}let T=p[0],$=p[p.length-1],V=document.activeElement;h.shiftKey&&(V===T||V===s.current)?(h.preventDefault(),$.focus()):!h.shiftKey&&(V===$||V===s.current)&&(h.preventDefault(),T.focus())},y=()=>{O||(K(!0),a({message:i,note:b,groups:B&&r.groups?c.map(Number):null,roleid:B&&r.roles?Number(x):null}))},f=i!==""||b!==""||x!=="0"||c.length!==R.length||c.some(h=>!R.includes(h)),I=F-Tn;return X(mo,{children:[A("div",{className:"modal-backdrop show"}),A("div",{className:"modal show d-block enrol_apply-qview-sheet",role:"presentation",onMouseDown:h=>{h.target===h.currentTarget&&!O&&!f&&o()},children:A("div",{className:"modal-dialog modal-lg modal-dialog-scrollable",children:X("div",{className:"modal-content",role:"dialog","aria-modal":"true","aria-labelledby":`${l}-title`,tabIndex:-1,ref:d,onKeyDown:ne,"data-region":"enrol_apply-sheet",children:[X("div",{className:"modal-header",children:[A("h5",{className:"modal-title",id:`${l}-title`,tabIndex:-1,ref:s,children:me}),A("button",{type:"button",className:"btn-close","aria-label":e.close,onClick:o,disabled:O})]}),X("div",{className:"modal-body",children:[t==="cancel"?A("div",{className:"alert alert-danger",children:e.sheetcanceldanger}):null,t==="wait"?A("p",{className:"enrol_apply-qview-sheetline",children:e.sheetwaitline}):null,B&&r.placesfull!==""?A("div",{className:"alert alert-warning",children:r.placesfull}):null,X("ul",{className:"enrol_apply-qview-sheetwho",children:[n.slice(0,Tn).map(h=>X("li",{children:[A("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:h.applicant.picture}}),A("span",{children:h.applicant.fullname})]},h.id)),I>0?A("li",{className:"enrol_apply-qview-sheetmore",children:k(e.andmore,_(I))}):null]}),B&&r.summary?X("div",{className:"enrol_apply-qview-sheetsummary",children:[X("dl",{className:"row mb-0",children:[A("dt",{className:"col-sm-4",children:r.summary.groupslabel}),A("dd",{className:"col-sm-8",children:r.summary.groups}),A("dt",{className:"col-sm-4",children:r.summary.rolelabel}),A("dd",{className:"col-sm-8",children:r.summary.role})]}),X("div",{className:"form-text",children:[r.summary.help,r.summary.note!==""?" "+r.summary.note:""]})]}):null,B&&r.groups?X("div",{className:"mb-3",children:[A("label",{className:"form-label",htmlFor:`${l}-groups`,children:r.groups.label}),A("select",{className:"form-select",id:`${l}-groups`,multiple:!0,value:c,"aria-describedby":`${l}-groupshelp`,onChange:h=>C(Array.from(h.target.selectedOptions,p=>p.value)),children:r.groups.options.map(h=>A("option",{value:String(h.id),children:h.name},h.id))}),A(Lt,{id:`${l}-groupshelp`,text:r.groups.help})]}):null,B&&r.roles?X("div",{className:"mb-3",children:[A("label",{className:"form-label",htmlFor:`${l}-role`,children:r.roles.label}),X("select",{className:"form-select",id:`${l}-role`,value:x,"aria-describedby":`${l}-rolehelp`,onChange:h=>v(h.target.value),children:[A("option",{value:"0",children:r.roles.default}),r.roles.options.map(h=>A("option",{value:String(h.id),children:h.name},h.id))]}),A(Lt,{id:`${l}-rolehelp`,text:r.roles.help})]}):null,X("div",{className:"mb-3",children:[A("label",{className:"form-label",htmlFor:`${l}-message`,children:r.message.label}),A("textarea",{className:"form-control",id:`${l}-message`,rows:3,value:i,"aria-describedby":`${l}-messagehelp`,onChange:h=>w(h.target.value)}),A(Lt,{id:`${l}-messagehelp`,text:r.message.help})]}),X("div",{className:"mb-0",children:[A("label",{className:"form-label",htmlFor:`${l}-note`,children:r.note.label}),A("textarea",{className:"form-control",id:`${l}-note`,rows:3,value:b,"aria-describedby":`${l}-notehelp`,onChange:h=>u(h.target.value)}),A(Lt,{id:`${l}-notehelp`,text:r.note.help})]})]}),X("div",{className:"modal-footer",children:[A("button",{type:"button",className:"btn btn-secondary",onClick:o,disabled:O,children:e.back}),A("button",{type:"button",className:"btn "+(t==="cancel"?"btn-danger":"btn-primary"),onClick:y,disabled:O,children:G})]})]})})})]})};import{Fragment as Bn,jsx as pe,jsxs as Wn}from"react/jsx-runtime";var wo=["applicant","wait","group"],yo=(e,t)=>e.filter(n=>!wo.includes(n.key)&&He(t,n.key).trim()!=="").map(n=>({key:n.key,title:n.title,content:pe(zt,{html:He(t,n.key)})})),In=({labels:e,narrowed:t,viewonly:n})=>Wn(Bn,{children:[t?pe("p",{className:"enrol_apply-rview-narrowed",children:e.narrowed}):null,n?pe("div",{className:"alert alert-info",role:"status",children:e.viewonly}):null]}),Vn=e=>{let{labels:t,list:n,shown:r,cards:o,narrow:a,selection:l}=e,d=s=>r.some(i=>i.key===s);return o?pe(Rt,{labels:t,rows:n,single:a,selection:l,renderCard:s=>({head:pe(Yt,{labels:t,row:s}),lines:Wn(Bn,{children:[d("wait")?pe("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--open",dangerouslySetInnerHTML:{__html:He(s,"wait")}}):null,d("group")&&He(s,"group")!==""?pe("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",dangerouslySetInnerHTML:{__html:He(s,"group")}}):null]}),fields:yo(r,s)})}):pe(_t,{labels:t,columns:r,lead:"applicant",rows:n,widths:e.widths,sizing:Pt,sort:e.sort,direction:e.direction,selection:l,renderLead:s=>pe(Yt,{labels:t,row:s}),renderCell:(s,i)=>pe(zt,{html:He(s,i.key)}),onSort:e.onSort,onWidth:e.onWidth,onWidthCommit:e.onWidthCommit})},Hn=(e,t,n,r,o)=>t!==null&&n&&r>0?pe(Dn,{labels:e,count:r,actions:t.actions,onDecide:o}):null,On=(e,t,n,r,o,a)=>t!==null&&n!==null&&r.length>0?pe(Fn,{labels:e,decision:n,rows:r,offer:t,onClose:o,onCommit:a}):null;import{fetchOne as Xt}from"@moodle/lms/core/ajax";var $n=(e,t)=>Xt({methodname:"enrol_apply_queue_rows",args:{enrolid:e,scope:t.scope,search:t.search,filters:Object.entries(t.filters).map(([n,r])=>({name:n,value:r})),page:t.page,perpage:t.perpage,sort:t.sort,direction:t.direction}}),Un=e=>Xt({methodname:"enrol_apply_queue_summary",args:{enrolid:e}}),Qn=(e,t)=>Xt({methodname:"enrol_apply_set_queue_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}});import{jsx as se,jsxs as rt}from"react/jsx-runtime";var qn=(e,t)=>Math.min(100,Math.max(0,Math.round(e*100/t))),Dt=({label:e,value:t,sub:n,meter:r,warn:o})=>rt("div",{className:"enrol_apply-rview-tile"+(o?" enrol_apply-rview-tile--warn":""),role:"listitem",children:[se("div",{className:"enrol_apply-rview-tile-label",children:e}),se("div",{className:"enrol_apply-rview-tile-value",children:t}),r!==null?se("span",{className:"enrol_apply-qview-meter"+(o?" enrol_apply-qview-meter--warn":""),"aria-hidden":"true",children:se("span",{style:{width:`${r}%`}})}):null,n!==""?se("div",{className:"enrol_apply-rview-tile-sub",children:n}):null]}),vo=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.DateTimeFormat(t,{dateStyle:"short"}).format(new Date(e*1e3))}catch{return new Date(e*1e3).toLocaleDateString()}},Kn=({labels:e,summary:t,error:n,onRetry:r})=>{if(t===null&&n)return rt("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",se("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:r,children:e.retry})]});if(t===null)return rt("div",{className:"enrol_apply-rview-band","aria-busy":"true",children:[Array.from({length:4},(d,s)=>rt("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[se("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),se("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"})]},s)),se("span",{className:"visually-hidden",role:"status",children:e.loadsummary})]});let o=t.narrowed?e.methodnote:"",a=t.awaiting>0?k(e.oldest,Ve(e,t.oldestwait)):"",l=t.late>0?k(e.late,_(t.late)):"";return rt("div",{className:"enrol_apply-rview-band",role:"list","data-region":"enrol_apply-tiles",children:[t.places>0?se(Dt,{label:e.placestaken,value:oe(e.ofmany,{taken:_(t.placestaken),total:_(t.places)}),sub:o,meter:qn(t.placestaken,t.places),warn:!1}):null,t.applicantlimit>0?se(Dt,{label:e.held,value:oe(e.ofmany,{taken:_(t.applicants),total:_(t.applicantlimit)}),sub:o,meter:qn(t.applicants,t.applicantlimit),warn:t.applicants*5>=t.applicantlimit*4}):null,se(Dt,{label:e.awaiting,value:_(t.awaiting),sub:[a,l].filter(d=>d!=="").join(" \xB7 "),meter:null,warn:t.late>0}),se(Dt,{label:e.status,value:t.open?e.open:e.closed,sub:t.open&&t.closes>0?k(e.closes,vo(t.closes)):"",meter:null,warn:!1})]})};import{Fragment as Ro,jsx as Be,jsxs as zn}from"react/jsx-runtime";var go=300,fo=500,Gn="enrol_apply_queue_view",ho=["applicant","wait","group","comment"],bo=e=>e.scope!=="all"||e.search!==""||Object.entries(e.filters).some(([t,n])=>n!==""&&!(t===Qe&&n===nt)),_o=e=>{let t=Z(e).current,{labels:n,enrolid:r}=t,o=Z(null),a=mt(o),[l,d]=ie(()=>wt(Gn)),[s]=ie(()=>yn(t)),i=Z(s),[w,b]=ie(s),[u,R]=ie(s.search),[c,C]=ie(null),[x,v]=ie(!0),[O,K]=ie(!1),[F,me]=ie(null),[G,B]=ie(!0),[ne,y]=ie(!1),[f,I]=ie({}),h=Z(0),p=Z(0),T=Z(null),$=Z(null),V=Z(null),z=Z(null),fe=Z(!1),j=Z(null),Le=Z(null),he=Z(null),Re=Z(()=>{}),[Ge,Ne]=ie(()=>new Set),[ue,Ee]=ie(null),ee=Z(null),be=Zt(g=>{let L=++h.current;T.current||(T.current=le("enrol_apply/queue:rows")),v(!0),$n(r,g).then(M=>{L===h.current&&(fe.current||(fe.current=!0,I(Object.fromEntries(M.preference.widths.map(Y=>[Y.key,Y.width])))),M.page!==i.current.page&&(i.current={...i.current,page:M.page},b(i.current),kt(t,i.current,!1)),C(M),Ne(Y=>{let hr=new Set(M.rows.filter(je=>je.selectable).map(je=>je.userenrolmentid)),un=Array.from(Y).filter(je=>hr.has(je));return un.length===Y.size?Y:new Set(un)}),K(!1),v(!1))}).catch(()=>{L===h.current&&(K(!0),v(!1))})},[r,t]),ze=Zt(()=>{let g=++p.current;$.current||($.current=le("enrol_apply/queue:summary")),B(!0),Un(r).then(L=>{g===p.current&&(me(L),y(!1),B(!1))}).catch(()=>{g===p.current&&(y(!0),B(!1))})},[r]),te=Zt((g,L)=>{i.current={...i.current,...g},b(i.current),Ne(new Set),kt(t,i.current,L),be(i.current)},[be,t]);Ke(()=>{!x&&T.current&&(T.current(),T.current=null)},[x,c,O]),Ke(()=>{!G&&$.current&&($.current(),$.current=null)},[G,F,ne]),Ke(()=>{kt(t,i.current,!1),be(i.current),ze()},[be,ze,t]),Ke(()=>{let g=L=>{let M=vn(L.state);M!==null&&(i.current=M,b(M),R(M.search),Ne(new Set),be(M))};return window.addEventListener("popstate",g),()=>window.removeEventListener("popstate",g)},[be]),Ke(()=>()=>{V.current!==null&&window.clearTimeout(V.current),z.current?.(),Re.current()},[]);let De=g=>{d(g),yt(Gn,g)},Ae=g=>{R(g),V.current!==null&&(window.clearTimeout(V.current),V.current=null);let L=g.trim(),M=L.length>=t.searchmin?L:"";if(M===i.current.search){z.current?.(),z.current=null;return}if(g===""){z.current?.(),z.current=null,te({search:"",page:0},!1);return}z.current||(z.current=le("enrol_apply/queue:search")),V.current=window.setTimeout(()=>{V.current=null,te({search:M,page:0},!1),z.current?.(),z.current=null},go)},re=Pn(c,t),{groupdefault:Te,allcolumns:ke,usedefault:_e,list:Bt,decides:it}=re,Wt=(g,L)=>{let M={...i.current.filters};L!==""?M[g]=L:g===Qe&&Te!==""?M[Qe]=nt:delete M[g],te({filters:M,page:0},!0)},ct=()=>Te!==""?{[Qe]:nt}:{},Fe=(g,L)=>{let M=le("enrol_apply/queue:preference");Qn(r,g).then(()=>{L&&be(i.current)}).catch(()=>{L&&K(!0)}).finally(M)},We=ke.filter(g=>!g.fixed&&g.active).map(g=>g.key),$e=g=>Object.entries(g).map(([L,M])=>({key:L,width:M})),ut=(g,L)=>{let M=L?[...We,g]:We.filter(Y=>Y!==g);c&&C({...c,columns:c.columns.map(Y=>Y.key===g?{...Y,active:L}:Y),preference:{...c.preference,usedefault:!1,columns:M}}),Fe({usedefault:!1,columns:M,widths:$e(f)},!0)},dt=g=>{let L=new Set(g.columns);c&&C({...c,columns:c.columns.map(M=>M.fixed?M:{...M,active:L.has(M.key)||L.has("answers")&&M.key.startsWith("answer:")}),presets:c.presets.map(M=>({...M,active:M.name===g.name})),preference:{...c.preference,usedefault:!1,columns:g.columns}}),Fe({usedefault:!1,columns:g.columns,widths:$e(f)},!0)},Ye=()=>{j.current!==null&&(window.clearTimeout(j.current),j.current=null);let g=he.current;he.current=null,g!==null&&Fe({usedefault:_e,columns:_e?[]:We,widths:$e(g)},!1),Le.current?.(),Le.current=null};Re.current=Ye;let pt=(g,L,M=!1)=>{let Y={...f};if(L===null?delete Y[g]:Y[g]=L,I(Y),he.current=Y,!M){Ye();return}j.current!==null&&window.clearTimeout(j.current),Le.current||(Le.current=le("enrol_apply/queue:width")),j.current=window.setTimeout(()=>Re.current(),fo)},$t=g=>{let L=c!==null&&c.sort===g&&c.direction==="asc"?"desc":"asc";te({sort:g,direction:L,page:0},!0)},m=ke.filter(g=>g.active),P=l==="cards"||l==="auto"&&a;Ke(()=>{ue===null&&ee.current&&(ee.current.focus(),ee.current=null)},[ue]);let E=t.decision,{chosen:H,selection:Ie}=Ln(Ge,Ne,re,n),yr=(g,L)=>{ee.current=L,Ee(g)},vr=g=>{E===null||ue===null||xn({action:Ze(t,i.current,!0),sesskey:E.sesskey,decision:ue,userenrolments:H.map(L=>L.id),...g})},gr=c===null?null:Be(Vn,{labels:n,list:Bt,shown:m,cards:P,narrow:a,widths:f,sort:c.sort,direction:c.direction,selection:Ie,onSort:$t,onWidth:pt,onWidthCommit:()=>Re.current()}),fr=ho.map(g=>({key:g,title:g==="applicant"?n.applicant:"",sortable:!1,default:!0,active:!0,fixed:g==="applicant"}));return zn("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-queueview",children:[Be(Kn,{labels:n,summary:F,error:ne,onRetry:ze}),re.notingroup?Be("p",{className:"enrol_apply-rview-narrowed",children:n.notingroup}):zn(Ro,{children:[Be(In,{labels:n,narrowed:re.narrowed,viewonly:re.viewonly}),Be(Cn,{labels:n,narrow:a,query:w,rows:c,columns:ke,presets:re.presets,usedefault:_e,haswidths:Object.keys(f).length>0,view:l,search:u,searchmin:t.searchmin,searching:x,tableurl:Ze(t,w,!1),reporturl:t.reporturl,onScope:g=>te({scope:g,page:0},!0),onSearch:Ae,onFilter:Wt,onClearFilters:()=>te({filters:ct(),page:0},!0),onClearAll:()=>{R(""),te({filters:ct(),search:"",page:0},!0)},onColumn:ut,onPreset:dt,onDefaultColumns:()=>{c&&C({...c,preference:{...c.preference,usedefault:!0,columns:[]}}),Fe({usedefault:!0,columns:[],widths:$e(f)},!0)},onResetWidths:()=>{I({}),Fe({usedefault:_e,columns:_e?[]:We,widths:[]},!1)},onView:De}),Be(Nt,{labels:n,loading:x,loaded:c!==null,error:O,total:re.total,filtered:bo(w),cards:P,narrow:a,skeleton:{columns:fr,lead:"applicant",widths:f,sizing:Pt},dock:Hn(n,E,it,H.length,yr),onRetry:()=>be(i.current),children:gr}),c!==null&&c.total>0?Be(gt,{labels:n,page:w.page,perpage:w.perpage,perpages:t.perpages,total:c.total,shown:c.rows.length,narrow:a,onPage:g=>te({page:g},!0),onPerpage:g=>te({perpage:g,page:0},!0)}):null]}),On(n,E,ue,H,()=>Ee(null),vr)]})},Yn=_o;import{useCallback as sn,useEffect as Ot,useRef as ce,useState as ge}from"react";import{useState as Jt}from"react";import{Fragment as tn,jsx as S,jsxs as q}from"react/jsx-runtime";var en=8,jn=7,At=({label:e,value:t,sub:n,warn:r,link:o})=>q("div",{className:"enrol_apply-rview-tile"+(r?" enrol_apply-rview-tile--warn":""),children:[S("div",{className:"enrol_apply-rview-tile-label",children:e}),S("div",{className:"enrol_apply-rview-tile-value",children:t}),S("div",{className:"enrol_apply-rview-tile-sub",children:n}),o??null]}),Xn=({label:e,share:t,figure:n,warn:r})=>q("div",{className:"enrol_apply-rview-hrow",role:"listitem",children:[S("span",{className:"enrol_apply-rview-hrow-label",title:e,children:e}),S("span",{className:"enrol_apply-rview-track","aria-hidden":"true",children:S("span",{className:"enrol_apply-rview-fill"+(r?" enrol_apply-rview-fill--late":""),style:{width:`${Math.round(Math.min(1,Math.max(0,t))*100)}%`}})}),S("span",{className:"enrol_apply-rview-hrow-n",children:n})]}),No=({compact:e})=>q("div",{"aria-hidden":"true",children:[S("div",{className:"enrol_apply-rview-band",children:Array.from({length:e?3:4},(t,n)=>q("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[S("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),S("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"}),S("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"})]},n))}),e?null:S("div",{className:"enrol_apply-rview-dist",children:[0,1].map(t=>q("div",{className:"enrol_apply-rview-panel enrol_apply-rview-sk",children:[S("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"}),S("span",{className:"enrol_apply-rview-skb"}),S("span",{className:"enrol_apply-rview-skb"}),S("span",{className:"enrol_apply-rview-skb"})]},t))})]}),ko=({labels:e,bymethod:t,bygroup:n,methods:r})=>{let[o,a]=Jt(r>1?"method":"group"),[l,d]=Jt(!1),[s,i]=Jt(""),w=o==="method"?t:n,b=Math.max(86400,...w.map(v=>v.median??0)),u=v=>S(Xn,{label:v.name,share:(v.median??0)/b,figure:`${v.median===null?"\u2013":Ve(e,v.median)} (${_(v.count)})`,warn:(v.median??0)>jn*86400},v.id),R=[...w].sort((v,O)=>O.count-v.count),c=o==="group"&&w.length>en,C=s.trim().toLocaleLowerCase(),x=[...w].filter(v=>C===""||v.name.toLocaleLowerCase().includes(C)).sort((v,O)=>(O.median??0)-(v.median??0));return q("div",{className:"enrol_apply-rview-panel",children:[q("div",{className:"enrol_apply-rview-panel-title",children:[S("span",{children:e.medianby}),r>1?S(et,{label:e.breakdown,small:!0,value:o,options:[{value:"method",label:e.bymethod},{value:"group",label:e.bygroup}],onChange:v=>a(v)}):S("span",{children:e.bygroup})]}),c?q(tn,{children:[S("div",{className:"enrol_apply-rview-note",children:k(e.topgroups,_(en))}),S("div",{role:"list",children:R.slice(0,en).map(u)}),S("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":l,onClick:()=>d(!l),children:l?e.hide:k(e.allgroups,_(w.length))}),l?q(tn,{children:[S("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:e.searchgroup,"aria-label":e.searchgroup,value:s,onChange:v=>i(v.target.value)}),S("div",{className:"enrol_apply-rview-scrolllist",role:"list",children:x.length?x.map(u):S("p",{className:"enrol_apply-rview-note",children:e.nogroupmatch})})]}):null]}):S("div",{role:"list",children:R.map(u)})]})},Zn=({labels:e,summary:t,loading:n,error:r,compactHint:o,measure:a,methods:l,queueurl:d,pendingstatus:s,onMeasure:i,onRetry:w})=>{let b=q("div",{className:"enrol_apply-rview-bandhead",children:[S("span",{className:"enrol_apply-rview-bandtitle",children:e.turnaround}),S(et,{label:e.measure,small:!0,value:a,options:[{value:"first",label:e.measurefirst},{value:"resolution",label:e.measureresolution}],onChange:R=>i(R)})]}),u;if(t===null&&r)u=q("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",S("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:w,children:e.retry})]});else if(t===null)u=q(tn,{children:[S(No,{compact:o}),S("span",{className:"visually-hidden",role:"status",children:e.loadband})]});else{let R=t.measure==="resolution",c=t.median??null,C=t.p90??null,x=t.oldest??null,v=e.nothingopen;x!==null&&(v=k(e.oldest,Ve(e,x))+(t.late>0?" \xB7 "+k(e.late,_(t.late)):""));let O=d===""?null:S("a",{className:"enrol_apply-rview-tile-link",href:R?d:`${d}&status=${s}`,children:e.openqueue}),K=Math.max(1,...t.buckets.map(F=>F.count));u=q("div",{className:"enrol_apply-rview-dim",children:[q("div",{className:"enrol_apply-rview-band",children:[S(At,{label:R?e.medianresolution:e.medianfirst,value:c===null?"\u2013":Ve(e,c),sub:k(e.mediansub,_(t.decided))}),t.compact?null:S(At,{label:e.p90,value:C===null?"\u2013":Ve(e,C),sub:e.p90sub}),S(At,{label:e.open,value:_(t.open),sub:v,warn:t.late>0,link:O}),S(At,{label:e.withoutperson,value:_(t.withoutperson),sub:e.withoutpersonsub})]}),t.compact?null:q("div",{className:"enrol_apply-rview-dist",children:[q("div",{className:"enrol_apply-rview-panel",children:[q("div",{className:"enrol_apply-rview-panel-title",children:[S("span",{children:R?e.distresolution:e.distfirst}),S("span",{className:"enrol_apply-rview-note",children:k(e.decisions,_(t.decided))})]}),S("div",{role:"list",children:t.buckets.map((F,me)=>{let G=_(F.todays??0),B=oe(e.bucket,{from:_(F.fromdays),to:G});return me===0?B=k(e.bucketfirst,_(F.todays??0)):(F.todays??null)===null&&(B=k(e.bucketlast,_(F.fromdays))),S(Xn,{label:B,share:F.count/K,figure:_(F.count),warn:F.fromdays>=jn},F.fromdays)})})]}),S(ko,{labels:e,bymethod:t.bymethod,bygroup:t.bygroup,methods:l})]})]})}return q("section",{className:"enrol_apply-rview-region enrol_apply-rview-bandregion","aria-label":e.turnaround,"aria-busy":n,"data-region":"enrol_apply-band",children:[n&&t!==null?S("div",{className:"enrol_apply-rview-progress"}):null,b,u]})};var Jn=e=>e.facet!==""&&e.options.length>0,er=e=>{try{let t=JSON.parse(e.values||"{}");return t!==null&&typeof t=="object"?t:{}}catch{return{}}},So=e=>e.reduce((t,n)=>({...t,...er(n)}),{}),nn=e=>{if(!e.applied)return"";let t=er(e),n=t[`${e.name}_value`];if(n==null)return"";let r=Number(t[`${e.name}_operator`]??0);if(e.facet==="waited"){let o=Number(n)*Number(t[`${e.name}_unit`]??0)/86400;return r===2&&Number.isFinite(o)?String(o):""}return e.facet==="decider"?r===3?String(n):"":String(n)},tr=(e,t,n)=>{let r=rn(e,t);return t.facet==="waited"?(r[`${t.name}_operator`]=n===""?0:2,r[`${t.name}_value`]=n===""?0:Number(n),r[`${t.name}_unit`]=86400):t.facet==="decider"?(r[`${t.name}_operator`]=n===""?0:3,r[`${t.name}_value`]=n):(r[`${t.name}_operator`]=n===""?0:1,r[`${t.name}_value`]=n),r},rn=(e,t)=>{let n=So(e);for(let r of Object.keys(n))r.startsWith(`${t.name}_`)&&delete n[r];return n},nr=e=>{let t=nn(e);return e.facet===""||t===""?"":e.options.find(n=>n.value===t)?.label??(e.facet==="decider"?t:"")};import{jsx as rr,jsxs as Lo}from"react/jsx-runtime";var Co=["auto","table","cards"],or=(e,t)=>t.facet==="waited"?e.waitedmore:t.facet==="decider"?e.decidedby:t.title,Eo=(e,t)=>{switch(t){case"decisions":return e.presetdecisions;case"answers":return e.presetanswers;case"groups":return e.presetgroups;default:return t}},Mo=(e,t,n)=>{let r={measure:e};return t!=="all"&&(r.scope=t),n!==""&&(r.search=n),encodeURIComponent(JSON.stringify(r))},xo=(e,t)=>({name:t.name,title:or(e,t),options:t.options,groupings:t.groupings,find:t.facet==="decider"?{label:e.searchdeciders,nomatch:e.nodecidermatch}:{label:e.searchgroups,nomatch:e.nogroupmatch}}),Po=(e,t)=>{let n=nr(t),r=n!==""?or(e,t):t.title;return{key:t.name,text:n===""?r:oe(e.filterchip,{title:r,value:n})}},lr=e=>{let{labels:t,filters:n}=e,r=e.measure==="resolution"?"submission:resolutiontime":"submission:waittime",o=u=>Jn(u)&&(u.facet!=="waited"||u.name===r),a=n.filter(o),l=n.filter(u=>!o(u)),d=u=>n.find(R=>R.name===u),s=e.downloadurl!==""&&e.formats.length>0,i=Mo(e.measure,e.scope,e.appliedsearch),w=s?e.formats.map(u=>({key:u.value,label:u.label,href:`${e.downloadurl}&parameters=${i}&download=${encodeURIComponent(u.value)}`})):[],b=e.presets.map(u=>({name:u.name,label:Eo(t,u.name),active:u.active}));return rr(Et,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"awaiting",label:t.awaiting},{value:"decided",label:t.decided}],scope:e.scope,total:e.total,facets:a.map(u=>xo(t,u)),values:Object.fromEntries(a.map(u=>[u.name,nn(u)])),panelnote:l.length>0?Lo("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",rr("a",{href:e.tableurl,children:t.morefilters})]}):null,chips:n.filter(u=>u.applied).map(u=>Po(t,u)),columns:e.columns,sets:b,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:Co,tableurl:e.tableurl,downloads:w,search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:u=>e.onScope(u),onSearch:e.onSearch,onFacet:(u,R)=>{let c=d(u);c&&e.onFilter(c,R)},onRemoveChip:u=>{let R=d(u);R&&e.onRemoveFilter(R)},onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:u=>{let R=e.presets.find(c=>c.name===u);R&&e.onPreset(R)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Ho,jsx as Tt}from"react/jsx-runtime";var Do=["outcome","wait","decided","group"],Ao={applicant:260,outcome:180,submitted:132,wait:150,decided:172,group:200,method:170,route:170,comment:240,note:240},To=180,Fo={applicant:160,submitted:104,decided:104},Io=80,Vo=800,Ft=ft(Ao,To,Fo,Io,Vo),ve=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",It=(e,t)=>Mn(t,Do.includes(e)),ot=({column:e,html:t})=>Tt(Mt,{parts:It(e,t)}),Vt=({row:e,label:t})=>e.reviewurl===""?null:Tt("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:e.reviewurl,children:t}),Ht=({row:e,identity:t})=>Tt(Ho,{children:t.map(n=>{let r=ve(e,n.key);return r===""?null:Tt("span",{className:"enrol_apply-rview-sub",title:vt(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});import{Fragment as ar,jsx as J,jsxs as at}from"react/jsx-runtime";var Oo=["applicant","outcome","submitted","wait","decided","group","method"],lt=({row:e,column:t})=>{let n=It(t,ve(e,t))[0]??"";return n===""?null:J("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:n}})},Bo=(e,t,n,r)=>{let o=l=>t.some(d=>d.key===l),a=t.filter(l=>!Oo.includes(l.key)&&ve(r,l.key).trim()!=="");return{head:at(ar,{children:[at("div",{className:"enrol_apply-rview-card-who",children:[J("span",{className:"enrol_apply-rview-card-name",dangerouslySetInnerHTML:{__html:ve(r,"applicant")}}),o("outcome")?J(lt,{row:r,column:"outcome"}):null,J(Ht,{row:r,identity:n})]}),J(Vt,{row:r,label:e.review})]}),lines:at(ar,{children:[at("div",{className:"enrol_apply-rview-card-line"+(r.open?" enrol_apply-rview-card-line--open":""),children:[o("wait")?J(lt,{row:r,column:"wait"}):null,o("wait")&&o("submitted")?J("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("submitted")?J(lt,{row:r,column:"submitted"}):null]}),o("decided")?J("div",{className:"enrol_apply-rview-card-line",children:It("decided",ve(r,"decided")).map((l,d)=>J("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:l}},d))}):null,o("group")||o("method")?at("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",children:[o("group")?J(lt,{row:r,column:"group"}):null,o("group")&&o("method")&&ve(r,"group")!==""?J("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("method")?J(lt,{row:r,column:"method"}):null]}):null]}),fields:a.map(l=>({key:l.key,title:l.title,content:J(ot,{column:l.key,html:ve(r,l.key)})}))}},sr=({labels:e,columns:t,identity:n,rows:r,single:o})=>J(Rt,{labels:e,rows:r,single:o,renderCard:a=>Bo(e,t,n,a)});import{Fragment as $o,jsx as xe,jsxs as on}from"react/jsx-runtime";var Wo=({row:e,longest:t})=>!e.open&&!e.decidedbyperson?null:xe("span",{className:"enrol_apply-rview-waitbar"+(e.open?" enrol_apply-rview-waitbar--open":""),"aria-hidden":"true",children:xe("span",{style:{width:`${Math.min(100,Math.round(e.wait/t*100))}%`}})}),ir=({labels:e,columns:t,identity:n,rows:r,widths:o,sort:a,direction:l,onSort:d,onWidth:s,onWidthCommit:i})=>{let w=Math.max(86400,...r.filter(b=>b.open||b.decidedbyperson).map(b=>b.wait));return xe(_t,{labels:e,columns:t,lead:"applicant",rows:r,widths:o,sizing:Ft,sort:a,direction:l,renderLead:(b,u)=>on("div",{className:"enrol_apply-rview-applicant",children:[on("div",{className:"enrol_apply-rview-applicant-text",children:[xe(ot,{column:u.key,html:ve(b,u.key)}),xe(Ht,{row:b,identity:n})]}),xe(Vt,{row:b,label:e.review})]}),renderCell:(b,u)=>on($o,{children:[xe(ot,{column:u.key,html:ve(b,u.key)}),u.key==="wait"?xe(Wo,{row:b,longest:w}):null]}),onSort:d,onWidth:s,onWidthCommit:i})};import{fetchOne as st}from"@moodle/lms/core/ajax";var cr=(e,t,n)=>st({methodname:"enrol_apply_report_rows",args:{enrolid:e,...t,facets:n}}),ur=(e,t,n,r)=>st({methodname:"enrol_apply_report_summary",args:{enrolid:e,measure:t,scope:n,search:r}}),dr=(e,t)=>st({methodname:"enrol_apply_set_report_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}}),ln=(e,t,n)=>st({methodname:"core_reportbuilder_set_filters",args:{reportid:e,parameters:JSON.stringify({measure:t}),values:JSON.stringify(n)}}),an=(e,t)=>st({methodname:"core_reportbuilder_filters_reset",args:{reportid:e,parameters:JSON.stringify({measure:t})}});import{Fragment as Go,jsx as Pe,jsxs as cn}from"react/jsx-runtime";var Uo=300,Qo=500,pr="enrol_apply_report_view",qo=["applicant","outcome","submitted","wait","decided","group"],Ko=e=>{let{labels:t,enrolid:n,reportid:r}=e,o=ce(null),a=mt(o),[l,d]=ge(()=>wt(pr)),s={measure:e.measure,scope:"all",search:"",page:0,perpage:e.perpage,sort:"",direction:"desc"},i=ce(s),[w,b]=ge(s),[u,R]=ge(""),[c,C]=ge(null),[x,v]=ge(!0),[O,K]=ge(!1),[F,me]=ge(null),[G,B]=ge(!0),[ne,y]=ge(!1),[f,I]=ge({}),[h,p]=ge({}),T=ce(0),$=ce(0),V=ce(null),z=ce(null),fe=ce(null),j=ce(null),Le=ce(!1),he=ce(null),Re=ce(null),Ge=ce(null),Ne=ce(()=>{}),ue=sn((m,P)=>{let E=++T.current;V.current||(V.current=le("enrol_apply/report:rows")),v(!0),cr(n,m,P).then(H=>{E===T.current&&(H.counted&&p(Object.fromEntries(H.filters.map(Ie=>[Ie.name,Ie]))),Le.current||(Le.current=!0,I(Object.fromEntries(H.preference.widths.map(Ie=>[Ie.key,Ie.width])))),H.page!==i.current.page&&(i.current={...i.current,page:H.page},b(i.current)),C(H),K(!1),v(!1))}).catch(()=>{E===T.current&&(K(!0),v(!1))})},[n]),Ee=sn(m=>{let P=++$.current;z.current||(z.current=le("enrol_apply/report:band")),B(!0),ur(n,m.measure,m.scope,m.search).then(E=>{P===$.current&&(me(E),y(!1),B(!1))}).catch(()=>{P===$.current&&(y(!0),B(!1))})},[n]),ee=sn((m,P,E)=>{i.current={...i.current,...m},b(i.current),P&&ue(i.current,E),E&&Ee(i.current)},[ue,Ee]);Ot(()=>{!x&&V.current&&(V.current(),V.current=null)},[x,c,O]),Ot(()=>{!G&&z.current&&(z.current(),z.current=null)},[G,F,ne]),Ot(()=>{ue(i.current,!0),Ee(i.current)},[ue,Ee]),Ot(()=>()=>{fe.current!==null&&window.clearTimeout(fe.current),j.current?.(),Ne.current()},[]);let be=m=>{d(m),yt(pr,m)},ze=m=>{R(m),fe.current!==null&&(window.clearTimeout(fe.current),fe.current=null);let P=m.trim(),E=P.length>=e.searchmin?P:"";if(E===i.current.search){j.current?.(),j.current=null;return}if(m===""){j.current?.(),j.current=null,ee({search:"",page:0},!0,!0);return}j.current||(j.current=le("enrol_apply/report:search")),fe.current=window.setTimeout(()=>{fe.current=null,ee({search:E,page:0},!0,!0),j.current?.(),j.current=null},Uo)},te=m=>{let P=le("enrol_apply/report:filters");m().then(()=>{ee({page:0},!0,!0)}).catch(()=>{K(!0)}).finally(P)},De=(c?.filters??[]).map(m=>h[m.name]?{...m,options:h[m.name].options,groupings:h[m.name].groupings}:m),Ae=(m,P)=>{let E=le("enrol_apply/report:preference");dr(n,m).then(()=>{P&&ue(i.current,!1)}).catch(()=>{P&&K(!0)}).finally(E)},re=c?.columns??[],Te=re.filter(m=>!m.fixed&&m.active).map(m=>m.key),ke=c?.preference.usedefault??!0,_e=m=>Object.entries(m).map(([P,E])=>({key:P,width:E})),Bt=(m,P)=>{let E=P?[...Te,m]:Te.filter(H=>H!==m);c&&C({...c,columns:c.columns.map(H=>H.key===m?{...H,active:P}:H),preference:{...c.preference,usedefault:!1,columns:E}}),Ae({usedefault:!1,columns:E,widths:_e(f)},!0)},it=()=>{he.current!==null&&(window.clearTimeout(he.current),he.current=null);let m=Ge.current;Ge.current=null,m!==null&&Ae({usedefault:ke,columns:ke?[]:Te,widths:_e(m)},!1),Re.current?.(),Re.current=null};Ne.current=it;let Wt=(m,P,E=!1)=>{let H={...f};if(P===null?delete H[m]:H[m]=P,I(H),Ge.current=H,!E){it();return}he.current!==null&&window.clearTimeout(he.current),Re.current||(Re.current=le("enrol_apply/report:width")),he.current=window.setTimeout(()=>Ne.current(),Qo)},ct=m=>{let P=new Set(m.columns);c&&C({...c,columns:c.columns.map(E=>E.fixed?E:{...E,active:P.has(E.key)||P.has("answers")&&E.key.startsWith("answer:")}),presets:c.presets.map(E=>({...E,active:E.name===m.name})),preference:{...c.preference,usedefault:!1,columns:m.columns}}),Ae({usedefault:!1,columns:m.columns,widths:_e(f)},!0)},Fe=m=>{let E=(c?.sort??"")===m&&c?.direction==="asc"?"desc":"asc";ee({sort:m,direction:E,page:0},!0,!1)},We=m=>{let P=i.current,E=re.some(H=>H.key==="wait"&&H.active);ee({measure:m},P.scope!=="all"||P.sort==="wait"||E,!0)},$e=De.some(m=>m.applied)||w.search!==""||w.scope!=="all",ut=re.filter(m=>m.key.startsWith("identity:")),dt=re.filter(m=>m.key==="applicant"||!m.fixed&&m.active),Ye=l==="cards"||l==="auto"&&a,pt=null;c!==null&&(pt=Ye?Pe(sr,{labels:t,columns:dt,identity:ut,rows:c.rows,single:a}):Pe(ir,{labels:t,columns:dt,identity:ut,rows:c.rows,widths:f,sort:c.sort,direction:c.direction,onSort:Fe,onWidth:Wt,onWidthCommit:()=>Ne.current()}));let $t=qo.map(m=>({key:m,title:m==="applicant"?t.applicant:"",sortable:!1,default:!0,active:!0,fixed:m==="applicant"}));return cn("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-reportview",children:[c?.narrowed?Pe("p",{className:"enrol_apply-rview-narrowed",children:t.narrowed}):null,Pe(Zn,{labels:t,summary:F,loading:G,error:ne,compactHint:c?.narrowed??!1,measure:w.measure,methods:e.methods,queueurl:e.queueurl,pendingstatus:e.pendingstatus,onMeasure:We,onRetry:()=>Ee(i.current)}),Pe(lr,{labels:t,narrow:a,scope:w.scope,total:c===null?null:c.total,filters:De,columns:re.filter(m=>!m.key.startsWith("identity:")||m.active),presets:c?.presets??[],usedefault:ke,haswidths:Object.keys(f).length>0,view:l,measure:w.measure,search:u,appliedsearch:w.search,searchmin:e.searchmin,searching:x,tableurl:e.tableurl,downloadurl:e.downloadurl,formats:e.formats,onScope:m=>ee({scope:m,page:0},!0,!0),onSearch:ze,onFilter:(m,P)=>te(()=>ln(r,i.current.measure,tr(De,m,P))),onRemoveFilter:m=>te(()=>ln(r,i.current.measure,rn(De,m))),onClearFilters:()=>te(()=>an(r,i.current.measure)),onClearAll:()=>{R(""),i.current={...i.current,search:""},De.some(m=>m.applied)?te(()=>an(r,i.current.measure)):ee({page:0},!0,!0)},onColumn:Bt,onPreset:ct,onDefaultColumns:()=>{c&&C({...c,preference:{...c.preference,usedefault:!0,columns:[]}}),Ae({usedefault:!0,columns:[],widths:_e(f)},!0)},onResetWidths:()=>{I({}),Ae({usedefault:ke,columns:ke?[]:Te,widths:[]},!1)},onView:be}),Pe(Nt,{labels:t,loading:x,loaded:c!==null,error:O,total:c?.total??0,filtered:$e,cards:Ye,narrow:a,skeleton:{columns:$t,lead:"applicant",widths:f,sizing:Ft},onRetry:()=>ue(i.current,!0),children:pt}),c!==null&&c.total>0?Pe(gt,{labels:t,page:w.page,perpage:w.perpage,perpages:e.perpages,total:c.total,shown:c.rows.length,narrow:a,onPage:m=>ee({page:m},!0,!1),onPerpage:m=>ee({perpage:m,page:0},!0,!1)}):null,e.retention!==""?cn("p",{className:"enrol_apply-rview-retention","data-region":"enrol_apply-retention",children:[e.retention,e.retentionurl!==""?cn(Go,{children:[" ",Pe("a",{href:e.retentionurl,children:t.retentionchange})]}):null]}):null]})},mr=Ko;import{jsx as wr}from"react/jsx-runtime";var zo=e=>e.mount===void 0||e.mount==="report"?wr(mr,{...e}):e.mount==="queue"?wr(Yn,{...e}):null,_s=zo;export{_s as default};
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
