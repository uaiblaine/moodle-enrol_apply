import{useCallback as Jt,useEffect as Ke,useRef as Z,useState as se}from"react";import{useEffect as br,useState as _r}from"react";var Rr=576,mt=e=>{let[t,n]=_r(!1);return br(()=>{let r=e.current;if(!r||typeof ResizeObserver>"u")return;let o=new ResizeObserver(a=>{let l=a[0]?.contentRect.width??r.clientWidth;n(l>0&&l<Rr)});return o.observe(r),()=>o.disconnect()},[e]),t},wt=e=>{try{let t=window.localStorage.getItem(e);return t==="table"||t==="cards"?t:"auto"}catch{return"auto"}},vt=(e,t)=>{try{window.localStorage.setItem(e,t)}catch{}};import{useId as Nr}from"react";var k=(e,t)=>(e||"").split("{$a}").join(t),re=(e,t)=>Object.entries(t).reduce((n,[r,o])=>n.split(`{$a->${r}}`).join(o),e||""),pn=(e,t,n,r)=>k(n===1&&e[`${t}_one`]?e[`${t}_one`]:e[t],r),_=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(t).format(e)}catch{return String(e)}},Ve=(e,t)=>{let n=Math.max(0,t);if(n<3600)return k(e.waitminutes,_(Math.max(1,Math.round(n/60))));if(n<86400)return k(e.waithours,_(Math.round(n/3600)));let r=Math.floor(n/86400),o=Math.round((n-r*86400)/3600);return o===24&&(r++,o=0),o>0&&r<10?re(e.waitdayshours,{days:_(r),hours:_(o)}):k(e.waitdays,_(r))},yt=e=>{let t=document.createElement("template");return t.innerHTML=e,(t.content.textContent||"").replace(/\s+/g," ").trim()};import{jsx as Se,jsxs as Ut}from"react/jsx-runtime";var kr=(e,t)=>[...new Set([1,e-1,e,e+1,t])].filter(n=>n>=1&&n<=t).sort((n,r)=>n-r),gt=({labels:e,page:t,perpage:n,perpages:r,total:o,shown:a,narrow:l,onPage:d,onPerpage:s})=>{let i=Nr(),w=Math.max(1,Math.ceil(o/n)),b=t+1,u=o>0?t*n+1:0,N=o>0?u+a-1:0,c=[];if(l)c=[Se("span",{className:"enrol_apply-rview-pager-gap",children:re(e.pageof,{page:_(b),pages:_(w)})},"of")];else{let C=kr(b,w);C.forEach((x,y)=>{y>0&&x-C[y-1]>1&&c.push(Se("span",{className:"enrol_apply-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${x}`)),c.push(Se("button",{type:"button","aria-current":x===b?"page":void 0,onClick:()=>d(x-1),children:_(x)},x))})}return Ut("div",{className:"enrol_apply-rview-foot",children:[Se("span",{className:"enrol_apply-rview-num","data-region":"enrol_apply-range",children:re(e.range,{from:_(u),to:_(N),total:_(o)})}),w>1?Ut("nav",{className:"enrol_apply-rview-pager","aria-label":e.pages,children:[Se("button",{type:"button",disabled:b===1,onClick:()=>d(t-1),children:e.previous}),c,Se("button",{type:"button",disabled:b===w,onClick:()=>d(t+1),children:e.next})]}):null,Ut("span",{className:"enrol_apply-rview-perpage",children:[Se("label",{htmlFor:i,children:e.perpage}),Se("select",{id:i,className:"form-select form-select-sm",value:n,onChange:C=>s(Number(C.target.value)),children:r.map(C=>Se("option",{value:C,children:_(C)},C))})]})]})};var oe=e=>{let t=window.M?.util;t?.js_pending(e);let n=!0;return()=>{n&&(n=!1,t?.js_complete(e))}};import{useState as xr}from"react";import{useEffect as Sr,useRef as bt}from"react";var ft=(e,t,n,r,o)=>({defaultWidth:a=>e[a]??t,minimumWidth:a=>n[a]??r,maximum:o}),ve=(e,t,n)=>Math.min(e.maximum,Math.max(e.minimumWidth(n),t[n]??e.defaultWidth(n)));import{jsx as D,jsxs as Me}from"react/jsx-runtime";var ht=16,Cr={ascending:"\u25B2",descending:"\u25BC",none:""},Er=(e,t)=>e?t==="asc"?"ascending":"descending":"none",Mr=({selection:e,rows:t})=>{let n=bt(null),r=t.filter(l=>!e.locked.has(l.id)),o=r.filter(l=>e.selected.has(l.id)).length,a=r.length>0&&o===r.length;return Sr(()=>{n.current&&(n.current.indeterminate=o>0&&!a)},[o,a]),Me("label",{className:"enrol_apply-rview-pickwrap",children:[D("input",{ref:n,type:"checkbox",className:"enrol_apply-rview-pick",checked:a,disabled:r.length===0,onChange:l=>e.onSelectPage(l.target.checked)}),D("span",{className:"visually-hidden",children:e.pagelabel})]})},Qt=({selection:e,row:t})=>e.locked.has(t.id)?D("span",{className:"enrol_apply-rview-pickwrap enrol_apply-rview-pick--none"}):Me("label",{className:"enrol_apply-rview-pickwrap",children:[D("input",{type:"checkbox",className:"enrol_apply-rview-pick",checked:e.selected.has(t.id),onChange:n=>e.onSelect(t.id,n.target.checked)}),D("span",{className:"visually-hidden",children:e.label(t)})]}),_t=({labels:e,columns:t,lead:n,rows:r,widths:o,sizing:a,sort:l,direction:d,selection:s,renderLead:i,renderCell:w,onSort:b,onWidth:u,onWidthCommit:N})=>{let c=bt(null),C=bt(null),x=bt(null),y=t.reduce((v,f)=>v+ve(a,o,f.key),0),$=v=>{v.currentTarget.classList.toggle("is-scrolled",v.currentTarget.scrollLeft>0)},G=(v,f)=>{let V=C.current?.querySelector(`col[data-col="${CSS.escape(v)}"]`);!V||!C.current||(V.style.width=`${f}px`,C.current.style.width=`${y-ve(a,o,v)+f}px`)},I=(v,f)=>Math.round(Math.min(a.maximum,Math.max(a.minimumWidth(v),f))),me=(v,f)=>{if(v.button!==0)return;v.preventDefault(),v.currentTarget.setPointerCapture(v.pointerId),v.currentTarget.classList.add("is-active");let V=ve(a,o,f);x.current={key:f,x:v.clientX,width:V,now:V}},z=v=>{let f=x.current;if(!f)return;let V=document.documentElement.dir==="rtl",h=(v.clientX-f.x)*(V?-1:1),O=I(f.key,f.width+h);O!==f.now&&(G(f.key,O),f.now=O)},H=v=>{let f=x.current;x.current=null,v.currentTarget.classList.remove("is-active"),f&&f.now!==f.width&&u(f.key,f.now)},we=(v,f)=>{let V=document.documentElement.dir==="rtl",h=0;if(v.key==="ArrowRight")h=V?-ht:ht;else if(v.key==="ArrowLeft")h=V?ht:-ht;else if(v.key==="Home"){v.preventDefault(),u(f,null,!0);return}if(h===0)return;v.preventDefault();let O=I(f,ve(a,o,f)+h);O!==ve(a,o,f)&&u(f,O,!0)};return D("div",{className:"enrol_apply-rview-gridframe",ref:c,tabIndex:0,role:"region","aria-label":e.grid,onScroll:$,children:Me("table",{className:"enrol_apply-rview-grid",ref:C,style:{width:`${y}px`},children:[D("caption",{className:"visually-hidden",children:e.caption}),D("colgroup",{children:t.map(v=>D("col",{"data-col":v.key,style:{width:`${ve(a,o,v.key)}px`}},v.key))}),D("thead",{children:D("tr",{children:t.map(v=>{let f=Er(l===v.key,d),V=D("span",{className:"enrol_apply-rview-th-title",children:v.title}),h=v.sortable?Me("button",{type:"button",className:"enrol_apply-rview-sortbtn",onClick:()=>b(v.key),children:[V,D("span",{className:"enrol_apply-rview-arrow","aria-hidden":"true",children:Cr[f]})]}):D("span",{className:"enrol_apply-rview-plain",children:V});return Me("th",{scope:"col",className:v.key===n?"enrol_apply-rview-fixed":void 0,"aria-sort":v.sortable?f:void 0,title:v.title,"data-column":v.key,children:[s&&v.key===n?Me("div",{className:"enrol_apply-rview-lead",children:[D(Mr,{selection:s,rows:r}),h]}):h,D("span",{className:"enrol_apply-rview-resize",role:"separator","aria-orientation":"vertical","aria-label":k(e.resize,v.title),"aria-valuemin":a.minimumWidth(v.key),"aria-valuemax":a.maximum,"aria-valuenow":ve(a,o,v.key),tabIndex:0,onPointerDown:O=>me(O,v.key),onPointerMove:z,onPointerUp:H,onPointerCancel:H,onDoubleClick:()=>{ve(a,o,v.key)!==a.defaultWidth(v.key)&&u(v.key,null)},onKeyDown:O=>we(O,v.key),onBlur:N})]},v.key)})})}),D("tbody",{children:r.map(v=>D("tr",{"data-record":v.id,className:s?.selected.has(v.id)?"enrol_apply-rview-row--selected":void 0,children:t.map(f=>f.key===n?D("th",{scope:"row",className:"enrol_apply-rview-fixed",children:s?Me("div",{className:"enrol_apply-rview-lead",children:[D(Qt,{selection:s,row:v}),i(v,f)]}):i(v,f)},f.key):D("td",{children:w(v,f)},f.key))},v.id))})]})})},mn=({columns:e,lead:t,widths:n,sizing:r})=>{let o=e.reduce((l,d)=>l+ve(r,n,d.key),0),a=["enrol_apply-rview-skb--w80","enrol_apply-rview-skb--w60","enrol_apply-rview-skb--w40"];return D("div",{className:"enrol_apply-rview-gridframe","aria-hidden":"true",children:Me("table",{className:"enrol_apply-rview-grid",style:{width:`${o}px`},children:[D("colgroup",{children:e.map(l=>D("col",{style:{width:`${ve(r,n,l.key)}px`}},l.key))}),D("thead",{children:D("tr",{children:e.map(l=>D("th",{className:l.key===t?"enrol_apply-rview-fixed":void 0,children:D("span",{className:"enrol_apply-rview-plain",children:D("span",{className:"enrol_apply-rview-th-title",children:l.title})})},l.key))})}),D("tbody",{children:Array.from({length:8},(l,d)=>D("tr",{className:"enrol_apply-rview-sk",children:e.map((s,i)=>D("td",{className:s.key===t?"enrol_apply-rview-fixed":void 0,children:D("span",{className:"enrol_apply-rview-skb "+a[(d+i)%3]})},s.key))},d))})]})})};import{jsx as de,jsxs as Xe}from"react/jsx-runtime";var Pr=({labels:e,row:t,single:n,selection:r,renderCard:o})=>{let[a,l]=xr(!1),d=o(t),s=!n||a,i=r?.selected.has(t.id)??!1;return Xe("article",{className:"enrol_apply-rview-card"+(i?" enrol_apply-rview-card--selected":""),role:"listitem","data-record":t.id,children:[Xe("div",{className:"enrol_apply-rview-card-l1",children:[r?de(Qt,{selection:r,row:t}):null,d.head]}),d.lines,d.fields.length>0?Xe("div",{className:"enrol_apply-rview-card-more",children:[n?de("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":a,onClick:()=>l(!a),children:a?e.less:e.more}):null,s?de("dl",{className:"enrol_apply-rview-card-list",children:d.fields.map(w=>Xe("div",{className:"enrol_apply-rview-card-item",children:[de("dt",{children:w.title}),de("dd",{children:w.content})]},w.key))}):null]}):null]})},Rt=({labels:e,rows:t,single:n,selection:r,renderCard:o})=>de("div",{className:"enrol_apply-rview-cards"+(n?" enrol_apply-rview-cards--single":""),role:"list",children:t.map(a=>de(Pr,{labels:e,row:a,single:n,selection:r,renderCard:o},a.id))}),wn=({single:e})=>de("div",{className:"enrol_apply-rview-cards"+(e?" enrol_apply-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:e?4:6},(t,n)=>Xe("div",{className:"enrol_apply-rview-card enrol_apply-rview-sk",children:[de("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),de("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"}),de("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),de("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"})]},n))});import{Fragment as vn,jsx as Ce,jsxs as Ue}from"react/jsx-runtime";var Lr=({labels:e,loaded:t,error:n,total:r,filtered:o,cards:a,narrow:l,skeleton:d,children:s,onRetry:i})=>{let w=Ue("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",Ce("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:i,children:e.retry})]});return!t&&n?w:t?r===0?Ue("div",{className:"enrol_apply-rview-empty",children:[Ce("p",{className:"enrol_apply-rview-empty-title",children:o?e.nomatch:e.empty}),o?Ce("p",{className:"enrol_apply-rview-empty-text",children:e.nomatchhelp}):null]}):Ue("div",{className:"enrol_apply-rview-dim",children:[n?w:null,s,a?null:Ce("p",{className:"enrol_apply-rview-hint",children:e.scrollhint})]}):Ue(vn,{children:[a?Ce(wn,{single:l}):Ce(mn,{...d}),Ce("span",{className:"visually-hidden",role:"status",children:e.loading})]})},Nt=e=>Ue(vn,{children:[Ue("section",{className:"enrol_apply-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"enrol_apply-rows",children:[e.loading&&e.loaded?Ce("div",{className:"enrol_apply-rview-progress"}):null,Ce(Lr,{...e})]}),e.dock??null]});var Dr="table",qt="enrolApplyQueue",Ar=(e,t,n)=>{let r=[];return t.scope!=="all"&&r.push(["status",String(t.scope==="pending"?e.statuses.pending:e.statuses.deferred)]),t.search!==""&&r.push(["search",t.search]),Object.keys(t.filters).sort().forEach(o=>{t.filters[o]!==""&&r.push([o,t.filters[o]])}),n?(t.sort!==""&&(t.sort!=="wait"||t.direction!=="asc")&&r.push(["sort",t.sort],["dir",t.direction]),t.page>0&&r.push(["page",String(t.page)]),t.perpage!==e.perpage&&r.push(["perpage",String(t.perpage)])):r.push(["view",Dr]),r},Ze=(e,t,n)=>{let r=new URL(e.pageurl,window.location.href);return Ar(e,t,n).forEach(([o,a])=>r.searchParams.set(o,a)),r.toString()},yn=e=>({scope:e.initial.scope,search:e.initial.search,filters:Object.fromEntries(e.initial.filters.map(t=>[t.name,t.value])),sort:e.initial.sort,direction:e.initial.direction,page:e.initial.page,perpage:e.initial.perpage}),kt=(e,t,n)=>{let r=Ze(e,t,!0),o={[qt]:t};try{n&&r!==window.location.href?window.history.pushState(o,"",r):window.history.replaceState(o,"",r)}catch{}document.querySelectorAll('[data-enrol-apply-queue="tablelink"]').forEach(a=>{a.href=Ze(e,t,!1)})},gn=e=>e===null||typeof e!="object"||!(qt in e)?null:e[qt];import{useEffect as Ct,useId as Ur,useRef as tt,useState as Sn}from"react";import{useId as Fr,useState as hn}from"react";import{jsx as fn}from"react/jsx-runtime";var Tr={filter:"M2 3h12l-4.5 5.5V13l-3 1.5V8.5L2 3z",columns:"M2 3h12v10H2zM6.5 3v10M10 3v10",table:"M2 3h12v10H2zM2 6.5h12M2 10h12M6 6.5V13",cards:"M2 3h5v4H2zM9 3h5v4H9zM2 9h5v4H2zM9 9h5v4H9z",auto:"M2 3h7v10H2zM11 3h3v4h-3zM11 9h3v4h-3z",download:"M8 2v8M4.5 7 8 10.5 11.5 7M3 13h10",search:"M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM10.5 10.5 14 14",close:"M4 4l8 8M12 4l-8 8",eraser:"M9.5 2 14 6.5 7.5 13 3 8.5zM5.6 5.9l4.5 4.5M7.5 13H14",tick:"M3 8.5 6.5 12 13 4.5",chevron:"M4 6l4 4 4-4",reset:"M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5",queue:"M3 4h10M3 8h10M3 12h6"},Q=({name:e,className:t})=>fn("svg",{className:"enrol_apply-rview-icon"+(t?" "+t:""),viewBox:"0 0 16 16",width:"16",height:"16",fill:"none",stroke:"currentColor",strokeWidth:"1.5",strokeLinecap:"round",strokeLinejoin:"round","aria-hidden":"true",focusable:"false",children:fn("path",{d:Tr[e]??""})});import{Fragment as Wr,jsx as q,jsxs as ye}from"react/jsx-runtime";var Ir=8,Kt=50,Vr=(e,t)=>t===null?e.show:k(e.showcount,_(t)),bn=({option:e})=>e.count>=0?q("small",{className:"enrol_apply-rview-optcount",children:_(e.count)}):null,Hr=(e,t)=>e.options.filter(n=>n.count!==0||n.value===t),Or=({labels:e,facet:t,shown:n,value:r,headid:o,onChange:a})=>{let[l,d]=hn(""),[s,i]=hn(""),w=l.trim().toLocaleLowerCase(),b=t.groupings.length>1?t.groupings:[],u=b.find(y=>String(y.id)===s)?.groups.map(String)??null,N=n.filter(y=>y.value===r),c=n.filter(y=>y.value!==r&&(w===""||y.label.toLocaleLowerCase().includes(w))&&(u===null||u.includes(y.value))),C=k(t.find.label,_(t.options.length)),x=b.length>0?e.moregroupsgrouping:e.moregroups;return ye(Wr,{children:[ye("div",{className:"enrol_apply-rview-find",children:[q("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:C,"aria-label":C,value:l,onChange:y=>d(y.target.value)}),b.length>0?ye("select",{className:"form-select form-select-sm enrol_apply-rview-grouping","aria-label":e.grouping,value:s,onChange:y=>i(y.target.value),children:[q("option",{value:"",children:e.allgroupings}),b.map(y=>q("option",{value:String(y.id),children:y.name},y.id))]}):null]}),ye("div",{className:"enrol_apply-rview-optlist",role:"group","aria-labelledby":o,children:[[...N,...c.slice(0,Kt)].map(y=>ye("button",{type:"button",className:"enrol_apply-rview-optrow","aria-pressed":y.value===r,onClick:()=>a(t.name,y.value===r?"":y.value),children:[q("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:q(Q,{name:"tick"})}),q("span",{className:"enrol_apply-rview-optrow-label",children:y.label})," ",q(bn,{option:y})]},y.value)),c.length>Kt?q("p",{className:"enrol_apply-rview-note",children:k(x,_(c.length-Kt))}):null,N.length===0&&c.length===0?q("p",{className:"enrol_apply-rview-note",children:t.find.nomatch}):null]})]})},Br=({labels:e,facet:t,value:n,onChange:r})=>{let o=Fr(),a=Hr(t,n),l=[{value:"",label:e.any,count:-1},...a];if(t.options.length>Ir)return ye("div",{className:"enrol_apply-rview-fgroup",children:[q("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),q(Or,{labels:e,facet:t,shown:a,value:n,headid:o,onChange:r})]});let d=s=>{let i=l.findIndex(b=>b.value===n),w=-1;s.key==="ArrowRight"||s.key==="ArrowDown"?w=(i+1)%l.length:s.key==="ArrowLeft"||s.key==="ArrowUp"?w=(i-1+l.length)%l.length:s.key==="Home"?w=0:s.key==="End"&&(w=l.length-1),!(w<0)&&(s.preventDefault(),r(t.name,l[w].value),s.currentTarget.querySelectorAll('[role="radio"]')[w]?.focus())};return ye("div",{className:"enrol_apply-rview-fgroup",children:[q("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),q("div",{className:"enrol_apply-rview-fpanel-opts",role:"radiogroup","aria-labelledby":o,onKeyDown:d,children:l.map(s=>ye("button",{type:"button",className:"enrol_apply-rview-fpanel-opt",role:"radio","aria-checked":s.value===n,tabIndex:s.value===n?0:-1,onClick:()=>r(t.name,s.value),children:[s.label," ",q(bn,{option:s})]},s.value))})]})},_n=({labels:e,id:t,hidden:n,facets:r,values:o,total:a,note:l,onChange:d,onClear:s,onShow:i})=>ye("div",{className:"enrol_apply-rview-fpanel",id:t,role:"dialog","aria-label":e.filters,hidden:n,children:[ye("div",{className:"enrol_apply-rview-fpanel-body",children:[r.map(w=>q(Br,{labels:e,facet:w,value:o[w.name]??"",onChange:d},w.name)),l??null]}),ye("div",{className:"enrol_apply-rview-fpanel-foot",children:[q("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:s,children:e.clearfilters}),q("button",{type:"button",className:"btn btn-sm btn-primary",onClick:i,children:Vr(e,a)})]})]});import{Fragment as $r,jsx as Je,jsxs as Rn}from"react/jsx-runtime";var St=e=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(e.key))return;let t=Array.from(e.currentTarget.querySelectorAll('[role^="menuitem"]'));if(t.length===0)return;e.preventDefault();let n=t.indexOf(document.activeElement),r=0;e.key==="End"?r=t.length-1:e.key==="ArrowDown"?r=(n+1)%t.length:e.key==="ArrowUp"&&(r=(n-1+t.length)%t.length),t[r].focus()},Nn=({heading:e,sets:t,onSet:n})=>t.length===0?null:Rn($r,{children:[Je("div",{className:"enrol_apply-rview-menu-head",role:"presentation",children:e}),t.map(r=>Rn("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":r.active,tabIndex:-1,"data-preset":r.name,onClick:()=>{r.active||n(r.name)},children:[Je("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:Je(Q,{name:"tick"})}),Je("span",{className:"enrol_apply-rview-menu-label",children:r.label})]},r.name)),Je("div",{className:"enrol_apply-rview-menu-sep",role:"separator"})]});import{jsx as kn}from"react/jsx-runtime";var et=({label:e,value:t,options:n,small:r,onChange:o})=>{let a=l=>{let d=n.findIndex(w=>w.value===t),s=-1;if(l.key==="ArrowRight"||l.key==="ArrowDown"?s=(d+1)%n.length:l.key==="ArrowLeft"||l.key==="ArrowUp"?s=(d-1+n.length)%n.length:l.key==="Home"?s=0:l.key==="End"&&(s=n.length-1),s<0)return;l.preventDefault(),o(n[s].value),l.currentTarget.querySelectorAll('[role="radio"]')[s]?.focus()};return kn("div",{className:"enrol_apply-rview-seg"+(r?" enrol_apply-rview-seg--small":""),role:"radiogroup","aria-label":e,onKeyDown:a,children:n.map(l=>kn("button",{type:"button",className:"enrol_apply-rview-seg-cell",role:"radio","aria-checked":l.value===t,tabIndex:l.value===t?0:-1,onClick:()=>o(l.value),children:l.label},l.value))})};import{Fragment as Gt,jsx as R,jsxs as B}from"react/jsx-runtime";var Et=e=>{let{labels:t,chips:n,columns:r}=e,o=Ur(),a=tt(null),l=tt(null),d=tt(null),s=tt(!1),[i,w]=Sn(null),[b,u]=Sn(e.search!==""),N=tt({});Ct(()=>{if(i===null)return;let m=T=>{a.current&&!a.current.contains(T.target)&&w(null)};return document.addEventListener("mousedown",m),()=>document.removeEventListener("mousedown",m)},[i]),Ct(()=>{if(i===null||i==="filters")return;let m=a.current?.querySelector(`#${CSS.escape(o+i)}`);(m?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])')??m?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])'))?.focus()},[i,o]),Ct(()=>{b?l.current?.focus():s.current&&(s.current=!1,d.current?.focus())},[b]),Ct(()=>{let m=T=>{if(T.key!=="/"||T.ctrlKey||T.metaKey||T.altKey||T.defaultPrevented)return;let F=document.activeElement;if(F&&(F.isContentEditable||F.closest("input, textarea, select")))return;let U=a.current?.getBoundingClientRect();!U||U.width===0||U.bottom<=0||U.top>=window.innerHeight||(T.preventDefault(),b?l.current?.focus():(w(null),u(!0)))};return document.addEventListener("keydown",m),()=>document.removeEventListener("keydown",m)},[b]);let c=m=>w(i===m?null:m),C=m=>{s.current=m,u(!1)},x=m=>{if(!(m.key!=="Escape"||m.defaultPrevented))if(i!==null){m.preventDefault();let T=N.current[i];w(null),T?.focus()}else b&&(m.preventDefault(),C(!0))},y=e.search.trim().length>=e.searchmin,$=y&&!b,G=n.length+($?1:0),I=e.total===null?"\u2026":pn(t,"count",e.total,_(e.total)),me=e.total!==null&&e.narrow?B(Gt,{children:[R("span",{"aria-hidden":"true",children:_(e.total)}),R("span",{className:"visually-hidden",children:I})]}):I,z=(m,T,F,U)=>B("button",{type:"button",className:"enrol_apply-rview-iconbtn","aria-label":F,"data-enrol-apply-tip":F,"aria-haspopup":m==="filters"?"dialog":"menu","aria-expanded":i===m,"aria-controls":o+m,ref:ce=>{N.current[m??""]=ce},onClick:()=>c(m),children:[R(Q,{name:T}),U]}),H=e.extraViews??[],we=H.find(m=>m.value===e.view)?.icon??{auto:"auto",table:"table",cards:"cards"}[e.view],v=R(Q,{name:"chevron",className:"enrol_apply-rview-chevron"}),f=n.length>0?k(t.filterson,_(n.length)):t.filters,V=n.length>0?R("span",{className:"enrol_apply-rview-badge","aria-hidden":"true",children:_(n.length)}):null,h=[["auto",t.viewauto,t.viewautohint],["table",t.table,""],["cards",t.cards,""]].filter(([m])=>e.views.includes(m)),O="enrol_apply-rview-bar"+(e.narrow?" enrol_apply-rview-bar--narrow":"")+(b?" enrol_apply-rview-bar--searching":"");return B("div",{className:O,"data-region":"enrol_apply-bar",ref:a,onKeyDown:x,children:[R(et,{label:t.scope,value:e.scope,options:e.scopes,onChange:e.onScope}),R("span",{className:"enrol_apply-rview-bar-spacer"}),B("div",{className:"enrol_apply-rview-bar-controls",children:[B("div",{className:"enrol_apply-rview-bar-cluster",children:[R("span",{className:"enrol_apply-rview-bar-count","data-region":"enrol_apply-total","aria-live":"polite",children:me}),B("div",{className:"enrol_apply-rview-capsule",children:[z("filters","filter",f,V),B("div",{className:"enrol_apply-rview-anchor",children:[z("columns","columns",t.columns,v),B("div",{className:"enrol_apply-rview-menu",id:o+"columns",role:"menu","aria-label":t.columns,hidden:i!=="columns",onKeyDown:St,children:[R(Nn,{heading:t.presets,sets:e.sets,onSet:e.onSet}),r.map(m=>m.fixed?B("div",{className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":"true","aria-disabled":"true",tabIndex:-1,children:[R("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:R(Q,{name:"tick"})}),R("span",{className:"enrol_apply-rview-menu-label",children:m.title}),R("span",{className:"enrol_apply-rview-menu-hint",children:t.always})]},m.key):B("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":m.active,tabIndex:-1,onClick:()=>e.onColumn(m.key,!m.active),children:[R("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:R(Q,{name:"tick"})}),R("span",{className:"enrol_apply-rview-menu-label",children:m.title})]},m.key)),R("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),B("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":e.usedefault,onClick:()=>{e.usedefault||e.onDefaultColumns()},children:[R(Q,{name:"columns"}),R("span",{className:"enrol_apply-rview-menu-label",children:t.defaultcolumns})]}),B("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":!e.haswidths,onClick:()=>{e.haswidths&&e.onResetWidths()},children:[R(Q,{name:"reset"}),R("span",{className:"enrol_apply-rview-menu-label",children:t.resetwidths})]})]})]}),B("div",{className:"enrol_apply-rview-anchor",children:[z("view",we,t.view,v),B("div",{className:"enrol_apply-rview-menu",id:o+"view",role:"menu","aria-label":t.view,hidden:i!=="view",onKeyDown:St,children:[h.map(([m,T,F])=>B("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===m,tabIndex:-1,onClick:()=>{e.onView(m),w(null),N.current.view?.focus()},children:[R(Q,{name:m}),R("span",{className:"enrol_apply-rview-menu-label",children:T}),F!==""?R("span",{className:"enrol_apply-rview-menu-hint",children:F}):null,R(Q,{name:"tick",className:"enrol_apply-rview-tick"})]},m)),H.length>0?B(Gt,{children:[R("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),H.map(m=>B("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===m.value,tabIndex:-1,onClick:()=>{e.onView(m.value),w(null),N.current.view?.focus()},children:[R(Q,{name:m.icon}),R("span",{className:"enrol_apply-rview-menu-label",children:m.label}),R(Q,{name:"tick",className:"enrol_apply-rview-tick"})]},m.value))]}):null,e.tableurl!==""?B(Gt,{children:[R("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),B("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:e.tableurl,children:[R(Q,{name:"table"}),R("span",{className:"enrol_apply-rview-menu-label",children:t.coretable})]})]}):null]})]}),e.downloads.length>0?B("div",{className:"enrol_apply-rview-anchor",children:[z("download","download",t.download,v),R("div",{className:"enrol_apply-rview-menu",id:o+"download",role:"menu","aria-label":t.download,hidden:i!=="download",onKeyDown:St,children:e.downloads.map(m=>B("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:m.href,children:[R(Q,{name:"download"}),R("span",{className:"enrol_apply-rview-menu-label",children:m.label})]},m.key))})]}):null]})]}),R("div",{className:"enrol_apply-rview-capsule enrol_apply-rview-searchcap",children:b?B("span",{className:"enrol_apply-rview-search",children:[e.searching&&y?R("span",{className:"enrol_apply-rview-spin","aria-hidden":"true"}):R(Q,{name:"search"}),R("input",{ref:l,type:"search","aria-label":t.search,placeholder:t.search,autoComplete:"off",value:e.search,onChange:m=>e.onSearch(m.target.value),onKeyDown:m=>{m.key==="Escape"&&(m.preventDefault(),C(!0))},onBlur:()=>{e.search===""&&C(!1)}}),e.search!==""?R("button",{type:"button",className:"enrol_apply-rview-eraser","aria-label":t.cleartext,"data-enrol-apply-tip":t.cleartext,onMouseDown:m=>m.preventDefault(),onClick:()=>{e.onSearch(""),l.current?.focus()},children:R(Q,{name:"eraser"})}):null]}):R("button",{ref:d,type:"button",className:"enrol_apply-rview-iconbtn","aria-label":t.search,"data-enrol-apply-tip":t.search,onClick:()=>{w(null),u(!0)},children:R(Q,{name:"search"})})}),R(_n,{labels:t,id:o+"filters",hidden:i!=="filters",facets:e.facets,values:e.values,total:e.total,note:e.panelnote,onChange:e.onFacet,onClear:e.onClearFilters,onShow:()=>{w(null),N.current.filters?.focus()}})]}),B("div",{className:"enrol_apply-rview-chips","data-region":"enrol_apply-chips",hidden:G===0&&!e.aside,children:[n.map(m=>B("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":k(t.removefilter,m.text),onClick:()=>e.onRemoveChip(m.key),children:[R("span",{children:m.text}),R(Q,{name:"close"})]},m.key)),$?B("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":k(t.removefilter,k(t.searchchip,e.search.trim())),onClick:()=>e.onSearch(""),children:[R("span",{children:k(t.searchchip,e.search.trim())}),R(Q,{name:"close"})]}):null,G>1?R("button",{type:"button",className:"enrol_apply-rview-clearall",onClick:()=>{e.onClearAll(),u(!1)},children:t.clearall}):null,e.aside??null]})]})};import{jsx as zt,jsxs as zr}from"react/jsx-runtime";var nt="all",Qe="group",Qr=["auto","table","cards"],qr=(e,t)=>{switch(t){case"everything":return e.preseteverything;case"glance":return e.presetglance;case"answers":return e.presetanswers;default:return t}},Kr=(e,t)=>{let n={label:e.searchgroups,nomatch:e.nogroupmatch},r=t.facets.map(a=>({name:a.name,title:a.title,options:a.options,groupings:[],find:n})),o=t.fieldfilters.filter(a=>a.control==="select").map(a=>({name:a.name,title:a.title,options:a.options.map(l=>({...l,count:-1})),groupings:[],find:n}));return[...r,...o]},Gr=(e,t,n,r)=>{let o=n,a=r,l=t.facets.find(s=>s.name===n),d=t.fieldfilters.find(s=>s.name===n);return l?(o=l.title,a=l.options.find(s=>s.value===r)?.label??r):d?(o=d.title,a=d.options.find(s=>s.value===r)?.label??r):n==="appliedfrom"?o=e.appliedfrom:n==="appliedto"&&(o=e.appliedto),{key:n,text:re(e.filterchip,{title:o,value:a})}},Cn=e=>{let{labels:t,rows:n,query:r}=e,o=e.presets.map(d=>({name:d.name,label:qr(t,d.name),active:d.active})),a=Object.fromEntries(Object.entries(r.filters).map(([d,s])=>[d,d===Qe&&s===nt?"":s])),l=n===null?[]:n.filters.map(d=>Gr(t,n,d.name,d.value));return zt(Et,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"pending",label:t.pending},{value:"deferred",label:t.deferred}],scope:r.scope,total:n===null?null:n.total,facets:n===null?[]:Kr(t,n),values:a,panelnote:zr("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",zt("a",{href:e.tableurl,children:t.morefilters})]}),chips:l,aside:e.reporturl!==""?zt("a",{className:"enrol_apply-qview-tolink",href:e.reporturl,children:t.openreport}):null,columns:e.columns,sets:o,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:Qr,tableurl:e.tableurl,downloads:[],search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:d=>e.onScope(d),onSearch:e.onSearch,onFacet:e.onFilter,onRemoveChip:d=>e.onFilter(d,""),onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:d=>{let s=e.presets.find(i=>i.name===d);s&&e.onPreset(s)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Yr,jsx as En}from"react/jsx-runtime";var Mn=(e,t)=>t?e.split(`
`).map(n=>n.trim()).filter(n=>n!==""):[e],Mt=({parts:e})=>En(Yr,{children:e.map((t,n)=>En("span",{className:n===0?"enrol_apply-rview-c":"enrol_apply-rview-sub",title:yt(t),dangerouslySetInnerHTML:{__html:t}},n))});import{Fragment as ao,jsx as le,jsxs as xt}from"react/jsx-runtime";var jr={applicant:320,wait:150,group:180,comment:260,history:130},Xr=180,Zr={applicant:200},Jr=80,eo=800,Pt=ft(jr,Xr,Zr,Jr,eo),to={deferred:"enrol_apply-status--warning",key:"enrol_apply-status--info",appliedbefore:"enrol_apply-status--neutral",own:"enrol_apply-status--neutral"},He=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",Yt=({html:e})=>le(Mt,{parts:[e]}),no=({tag:e})=>{let t=to[e.kind]??"enrol_apply-status--neutral",n=e.title!==""&&e.title!==e.text;return xt("span",{className:"enrol_apply-status "+t,title:n?e.title:void 0,children:[e.text,n?le("span",{className:"visually-hidden",children:" "+e.title}):null]})},ro=({row:e})=>e.applicant.profileurl===""?le("span",{className:"enrol_apply-qview-name",children:e.applicant.fullname}):le("a",{className:"enrol_apply-qview-name",href:e.applicant.profileurl,children:e.applicant.fullname}),oo=({labels:e,row:t})=>le("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:t.reviewurl,"aria-label":k(e.reviewapplicant,t.applicant.fullname),children:e.review}),lo=({row:e})=>xt(ao,{children:[le("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:e.applicant.picture}}),xt("div",{className:"enrol_apply-qview-whotext",children:[le("span",{className:"enrol_apply-rview-c",children:le(ro,{row:e})}),e.applicant.identity.map(t=>le("span",{className:"enrol_apply-rview-sub",title:t.value,children:t.value},t.name)),e.applicant.tags.length>0?le("span",{className:"enrol_apply-qview-tags",children:e.applicant.tags.map(t=>le(no,{tag:t},t.kind))}):null]})]}),jt=({labels:e,row:t})=>xt("div",{className:"enrol_apply-qview-who",children:[le(lo,{row:t}),le(oo,{labels:e,row:t})]});var Oe=(e,t,n)=>{let r=document.createElement("input");r.type="hidden",r.name=t,r.value=n,e.appendChild(r)},xn=e=>{let t=document.createElement("form");t.method="post",t.action=e.action,t.hidden=!0,Oe(t,"sesskey",e.sesskey),Oe(t,"formaction",e.decision),e.userenrolments.forEach(n=>Oe(t,"userenrolments[]",String(n))),Oe(t,"outcomemessage",e.message),Oe(t,"decisionnote",e.note),e.decision==="confirm"&&(e.groups?.forEach(n=>Oe(t,"groups[]",String(n))),e.roleid!==null&&Oe(t,"roleid",String(e.roleid))),document.body.appendChild(t),t.submit()};var Pn=(e,t)=>{let n=t.decision!==null&&e!==null&&e.candecide;return{decides:n,notingroup:!!e?.notingroup,narrowed:!!e?.narrowed,viewonly:e!==null&&!n,groupdefault:e?.groupdefault??"",allcolumns:e?.columns??[],usedefault:e?.preference.usedefault??!0,presets:e?.presets??[],total:e?.total??0,list:(e?.rows??[]).map(r=>({...r,id:r.userenrolmentid}))}},Ln=(e,t,n,r)=>{let{list:o,decides:a}=n,l=new Set(o.filter(u=>!u.selectable).map(u=>u.id)),d=o.filter(u=>e.has(u.id)),s=(u,N)=>t(c=>{let C=new Set(c);return N&&!l.has(u)?C.add(u):C.delete(u),C}),i=u=>t(()=>u?new Set(o.filter(N=>!l.has(N.id)).map(N=>N.id)):new Set),b=a?{selected:e,locked:l,label:u=>k(r.selectapplicant,u.applicant.fullname),pagelabel:r.selectpage,onSelect:s,onSelectPage:i}:void 0;return{locked:l,chosen:d,selection:b}};import{jsx as Xt,jsxs as so}from"react/jsx-runtime";var Dn=({labels:e,count:t,actions:n,onDecide:r})=>so("div",{className:"enrol_apply-qview-dock",role:"region","aria-label":e.dock,"data-region":"enrol_apply-dock",children:[Xt("span",{className:"enrol_apply-qview-dockcount","aria-live":"polite",children:k(e.selected,_(t))}),Xt("div",{className:"enrol_apply-qview-dockactions",children:n.map(o=>Xt("button",{type:"button",className:"btn "+o.classes,onClick:a=>r(o.value,a.currentTarget),children:o.label},o.value))})]});import{useEffect as io,useId as co,useRef as An,useState as qe}from"react";import{Fragment as mo,jsx as A,jsxs as X}from"react/jsx-runtime";var Tn=5,uo="button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), a[href]",po=(e,t,n)=>{let r=_(n);return t==="cancel"?[k(n===1?e.sheetcancelone:e.sheetcancel,r),k(e.sheetcancelcommit,r)]:t==="wait"?[k(n===1?e.sheetwaitone:e.sheetwait,r),k(e.sheetwaitcommit,r)]:[k(n===1?e.sheetconfirmone:e.sheetconfirm,r),k(e.sheetconfirmcommit,r)]},Lt=({id:e,text:t})=>A("div",{className:"form-text",id:e,children:t}),Fn=({labels:e,decision:t,rows:n,offer:r,onClose:o,onCommit:a})=>{let l=co(),d=An(null),s=An(null),[i,w]=qe(""),[b,u]=qe(""),[N]=qe(()=>(r.groups?.options??[]).filter(h=>h.selected).map(h=>String(h.id))),[c,C]=qe(N),[x,y]=qe("0"),[$,G]=qe(!1),I=n.length,[me,z]=po(e,t,I),H=t==="confirm";io(()=>(s.current?.focus(),document.body.classList.add("modal-open"),()=>document.body.classList.remove("modal-open")),[]);let we=h=>{if(h.key==="Escape"){h.preventDefault(),h.stopPropagation(),$||o();return}if(h.key!=="Tab"||!d.current)return;let O=Array.from(d.current.querySelectorAll(uo));if(O.length===0){h.preventDefault();return}let m=O[0],T=O[O.length-1],F=document.activeElement;h.shiftKey&&(F===m||F===s.current)?(h.preventDefault(),T.focus()):!h.shiftKey&&(F===T||F===s.current)&&(h.preventDefault(),m.focus())},v=()=>{$||(G(!0),a({message:i,note:b,groups:H&&r.groups?c.map(Number):null,roleid:H&&r.roles?Number(x):null}))},f=i!==""||b!==""||x!=="0"||c.length!==N.length||c.some(h=>!N.includes(h)),V=I-Tn;return X(mo,{children:[A("div",{className:"modal-backdrop show"}),A("div",{className:"modal show d-block enrol_apply-qview-sheet",role:"presentation",onMouseDown:h=>{h.target===h.currentTarget&&!$&&!f&&o()},children:A("div",{className:"modal-dialog modal-lg modal-dialog-scrollable",children:X("div",{className:"modal-content",role:"dialog","aria-modal":"true","aria-labelledby":`${l}-title`,tabIndex:-1,ref:d,onKeyDown:we,"data-region":"enrol_apply-sheet",children:[X("div",{className:"modal-header",children:[A("h5",{className:"modal-title",id:`${l}-title`,tabIndex:-1,ref:s,children:me}),A("button",{type:"button",className:"btn-close","aria-label":e.close,onClick:o,disabled:$})]}),X("div",{className:"modal-body",children:[t==="cancel"?A("div",{className:"alert alert-danger",children:e.sheetcanceldanger}):null,t==="wait"?A("p",{className:"enrol_apply-qview-sheetline",children:e.sheetwaitline}):null,H&&r.placesfull!==""?A("div",{className:"alert alert-warning",children:r.placesfull}):null,X("ul",{className:"enrol_apply-qview-sheetwho",children:[n.slice(0,Tn).map(h=>X("li",{children:[A("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:h.applicant.picture}}),A("span",{children:h.applicant.fullname})]},h.id)),V>0?A("li",{className:"enrol_apply-qview-sheetmore",children:k(e.andmore,_(V))}):null]}),H&&r.summary?X("div",{className:"enrol_apply-qview-sheetsummary",children:[X("dl",{className:"row mb-0",children:[A("dt",{className:"col-sm-4",children:r.summary.groupslabel}),A("dd",{className:"col-sm-8",children:r.summary.groups}),A("dt",{className:"col-sm-4",children:r.summary.rolelabel}),A("dd",{className:"col-sm-8",children:r.summary.role})]}),X("div",{className:"form-text",children:[r.summary.help,r.summary.note!==""?" "+r.summary.note:""]})]}):null,H&&r.groups?X("div",{className:"mb-3",children:[A("label",{className:"form-label",htmlFor:`${l}-groups`,children:r.groups.label}),A("select",{className:"form-select",id:`${l}-groups`,multiple:!0,value:c,"aria-describedby":`${l}-groupshelp`,onChange:h=>C(Array.from(h.target.selectedOptions,O=>O.value)),children:r.groups.options.map(h=>A("option",{value:String(h.id),children:h.name},h.id))}),A(Lt,{id:`${l}-groupshelp`,text:r.groups.help})]}):null,H&&r.roles?X("div",{className:"mb-3",children:[A("label",{className:"form-label",htmlFor:`${l}-role`,children:r.roles.label}),X("select",{className:"form-select",id:`${l}-role`,value:x,"aria-describedby":`${l}-rolehelp`,onChange:h=>y(h.target.value),children:[A("option",{value:"0",children:r.roles.default}),r.roles.options.map(h=>A("option",{value:String(h.id),children:h.name},h.id))]}),A(Lt,{id:`${l}-rolehelp`,text:r.roles.help})]}):null,X("div",{className:"mb-3",children:[A("label",{className:"form-label",htmlFor:`${l}-message`,children:r.message.label}),A("textarea",{className:"form-control",id:`${l}-message`,rows:3,value:i,"aria-describedby":`${l}-messagehelp`,onChange:h=>w(h.target.value)}),A(Lt,{id:`${l}-messagehelp`,text:r.message.help})]}),X("div",{className:"mb-0",children:[A("label",{className:"form-label",htmlFor:`${l}-note`,children:r.note.label}),A("textarea",{className:"form-control",id:`${l}-note`,rows:3,value:b,"aria-describedby":`${l}-notehelp`,onChange:h=>u(h.target.value)}),A(Lt,{id:`${l}-notehelp`,text:r.note.help})]})]}),X("div",{className:"modal-footer",children:[A("button",{type:"button",className:"btn btn-secondary",onClick:o,disabled:$,children:e.back}),A("button",{type:"button",className:"btn "+(t==="cancel"?"btn-danger":"btn-primary"),onClick:v,disabled:$,children:z})]})]})})})]})};import{Fragment as Bn,jsx as pe,jsxs as Wn}from"react/jsx-runtime";var wo=["applicant","wait","group"],vo=(e,t)=>e.filter(n=>!wo.includes(n.key)&&He(t,n.key).trim()!=="").map(n=>({key:n.key,title:n.title,content:pe(Yt,{html:He(t,n.key)})})),In=({labels:e,narrowed:t,viewonly:n})=>Wn(Bn,{children:[t?pe("p",{className:"enrol_apply-rview-narrowed",children:e.narrowed}):null,n?pe("div",{className:"alert alert-info",role:"status",children:e.viewonly}):null]}),Vn=e=>{let{labels:t,list:n,shown:r,cards:o,narrow:a,selection:l}=e,d=s=>r.some(i=>i.key===s);return o?pe(Rt,{labels:t,rows:n,single:a,selection:l,renderCard:s=>({head:pe(jt,{labels:t,row:s}),lines:Wn(Bn,{children:[d("wait")?pe("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--open",dangerouslySetInnerHTML:{__html:He(s,"wait")}}):null,d("group")&&He(s,"group")!==""?pe("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",dangerouslySetInnerHTML:{__html:He(s,"group")}}):null]}),fields:vo(r,s)})}):pe(_t,{labels:t,columns:r,lead:"applicant",rows:n,widths:e.widths,sizing:Pt,sort:e.sort,direction:e.direction,selection:l,renderLead:s=>pe(jt,{labels:t,row:s}),renderCell:(s,i)=>pe(Yt,{html:He(s,i.key)}),onSort:e.onSort,onWidth:e.onWidth,onWidthCommit:e.onWidthCommit})},Hn=(e,t,n,r,o)=>t!==null&&n&&r>0?pe(Dn,{labels:e,count:r,actions:t.actions,onDecide:o}):null,On=(e,t,n,r,o,a)=>t!==null&&n!==null&&r.length>0?pe(Fn,{labels:e,decision:n,rows:r,offer:t,onClose:o,onCommit:a}):null;import{fetchOne as Zt}from"@moodle/lms/core/ajax";var $n=(e,t)=>Zt({methodname:"enrol_apply_queue_rows",args:{enrolid:e,scope:t.scope,search:t.search,filters:Object.entries(t.filters).map(([n,r])=>({name:n,value:r})),page:t.page,perpage:t.perpage,sort:t.sort,direction:t.direction}}),Un=e=>Zt({methodname:"enrol_apply_queue_summary",args:{enrolid:e}}),Qn=(e,t)=>Zt({methodname:"enrol_apply_set_queue_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}});import{jsx as ae,jsxs as rt}from"react/jsx-runtime";var qn=(e,t)=>Math.min(100,Math.max(0,Math.round(e*100/t))),Dt=({label:e,value:t,sub:n,meter:r,warn:o})=>rt("div",{className:"enrol_apply-rview-tile"+(o?" enrol_apply-rview-tile--warn":""),role:"listitem",children:[ae("div",{className:"enrol_apply-rview-tile-label",children:e}),ae("div",{className:"enrol_apply-rview-tile-value",children:t}),r!==null?ae("span",{className:"enrol_apply-qview-meter"+(o?" enrol_apply-qview-meter--warn":""),"aria-hidden":"true",children:ae("span",{style:{width:`${r}%`}})}):null,n!==""?ae("div",{className:"enrol_apply-rview-tile-sub",children:n}):null]}),yo=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.DateTimeFormat(t,{dateStyle:"short"}).format(new Date(e*1e3))}catch{return new Date(e*1e3).toLocaleDateString()}},Kn=({labels:e,summary:t,error:n,onRetry:r})=>{if(t===null&&n)return rt("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",ae("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:r,children:e.retry})]});if(t===null)return rt("div",{className:"enrol_apply-rview-band","aria-busy":"true",children:[Array.from({length:4},(d,s)=>rt("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[ae("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),ae("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"})]},s)),ae("span",{className:"visually-hidden",role:"status",children:e.loadsummary})]});let o=t.narrowed?e.methodnote:"",a=t.awaiting>0?k(e.oldest,Ve(e,t.oldestwait)):"",l=t.late>0?k(e.late,_(t.late)):"";return rt("div",{className:"enrol_apply-rview-band",role:"list","data-region":"enrol_apply-tiles",children:[t.places>0?ae(Dt,{label:e.placestaken,value:re(e.ofmany,{taken:_(t.placestaken),total:_(t.places)}),sub:o,meter:qn(t.placestaken,t.places),warn:!1}):null,t.applicantlimit>0?ae(Dt,{label:e.held,value:re(e.ofmany,{taken:_(t.applicants),total:_(t.applicantlimit)}),sub:o,meter:qn(t.applicants,t.applicantlimit),warn:t.applicants*5>=t.applicantlimit*4}):null,ae(Dt,{label:e.awaiting,value:_(t.awaiting),sub:[a,l].filter(d=>d!=="").join(" \xB7 "),meter:null,warn:t.late>0}),ae(Dt,{label:e.status,value:t.open?e.open:e.closed,sub:t.open&&t.closes>0?k(e.closes,yo(t.closes)):"",meter:null,warn:!1})]})};import{Fragment as Ro,jsx as Be,jsxs as zn}from"react/jsx-runtime";var go=300,fo=500,Gn="enrol_apply_queue_view",ho=["applicant","wait","group","comment"],bo=e=>e.scope!=="all"||e.search!==""||Object.entries(e.filters).some(([t,n])=>n!==""&&!(t===Qe&&n===nt)),_o=e=>{let t=Z(e).current,{labels:n,enrolid:r}=t,o=Z(null),a=mt(o),[l,d]=se(()=>wt(Gn)),[s]=se(()=>yn(t)),i=Z(s),[w,b]=se(s),[u,N]=se(s.search),[c,C]=se(null),[x,y]=se(!0),[$,G]=se(!1),[I,me]=se(null),[z,H]=se(!0),[we,v]=se(!1),[f,V]=se({}),h=Z(0),O=Z(0),m=Z(null),T=Z(null),F=Z(null),U=Z(null),ce=Z(!1),j=Z(null),Le=Z(null),he=Z(null),Re=Z(()=>{}),[Ge,Ne]=se(()=>new Set),[ue,Ee]=se(null),ee=Z(null),be=Jt(g=>{let L=++h.current;m.current||(m.current=oe("enrol_apply/queue:rows")),y(!0),$n(r,g).then(M=>{L===h.current&&(ce.current||(ce.current=!0,V(Object.fromEntries(M.preference.widths.map(Y=>[Y.key,Y.width])))),M.page!==i.current.page&&(i.current={...i.current,page:M.page},b(i.current),kt(t,i.current,!1)),C(M),Ne(Y=>{let hr=new Set(M.rows.filter(je=>je.selectable).map(je=>je.userenrolmentid)),dn=Array.from(Y).filter(je=>hr.has(je));return dn.length===Y.size?Y:new Set(dn)}),G(!1),y(!1))}).catch(()=>{L===h.current&&(G(!0),y(!1))})},[r,t]),ze=Jt(()=>{let g=++O.current;T.current||(T.current=oe("enrol_apply/queue:summary")),H(!0),Un(r).then(L=>{g===O.current&&(me(L),v(!1),H(!1))}).catch(()=>{g===O.current&&(v(!0),H(!1))})},[r]),te=Jt((g,L)=>{i.current={...i.current,...g},b(i.current),Ne(new Set),kt(t,i.current,L),be(i.current)},[be,t]);Ke(()=>{!x&&m.current&&(m.current(),m.current=null)},[x,c,$]),Ke(()=>{!z&&T.current&&(T.current(),T.current=null)},[z,I,we]),Ke(()=>{kt(t,i.current,!1),be(i.current),ze()},[be,ze,t]),Ke(()=>{let g=L=>{let M=gn(L.state);M!==null&&(i.current=M,b(M),N(M.search),Ne(new Set),be(M))};return window.addEventListener("popstate",g),()=>window.removeEventListener("popstate",g)},[be]),Ke(()=>()=>{F.current!==null&&window.clearTimeout(F.current),U.current?.(),Re.current()},[]);let De=g=>{d(g),vt(Gn,g)},Ae=g=>{N(g),F.current!==null&&(window.clearTimeout(F.current),F.current=null);let L=g.trim(),M=L.length>=t.searchmin?L:"";if(M===i.current.search){U.current?.(),U.current=null;return}if(g===""){U.current?.(),U.current=null,te({search:"",page:0},!1);return}U.current||(U.current=oe("enrol_apply/queue:search")),F.current=window.setTimeout(()=>{F.current=null,te({search:M,page:0},!1),U.current?.(),U.current=null},go)},ne=Pn(c,t),{groupdefault:Te,allcolumns:ke,usedefault:_e,list:Bt,decides:it}=ne,Wt=(g,L)=>{let M={...i.current.filters};L!==""?M[g]=L:g===Qe&&Te!==""?M[Qe]=nt:delete M[g],te({filters:M,page:0},!0)},ct=()=>Te!==""?{[Qe]:nt}:{},Fe=(g,L)=>{let M=oe("enrol_apply/queue:preference");Qn(r,g).then(()=>{L&&be(i.current)}).catch(()=>{L&&G(!0)}).finally(M)},We=ke.filter(g=>!g.fixed&&g.active).map(g=>g.key),$e=g=>Object.entries(g).map(([L,M])=>({key:L,width:M})),ut=(g,L)=>{let M=L?[...We,g]:We.filter(Y=>Y!==g);c&&C({...c,columns:c.columns.map(Y=>Y.key===g?{...Y,active:L}:Y),preference:{...c.preference,usedefault:!1,columns:M}}),Fe({usedefault:!1,columns:M,widths:$e(f)},!0)},dt=g=>{let L=new Set(g.columns);c&&C({...c,columns:c.columns.map(M=>M.fixed?M:{...M,active:L.has(M.key)||L.has("answers")&&M.key.startsWith("answer:")}),presets:c.presets.map(M=>({...M,active:M.name===g.name})),preference:{...c.preference,usedefault:!1,columns:g.columns}}),Fe({usedefault:!1,columns:g.columns,widths:$e(f)},!0)},Ye=()=>{j.current!==null&&(window.clearTimeout(j.current),j.current=null);let g=he.current;he.current=null,g!==null&&Fe({usedefault:_e,columns:_e?[]:We,widths:$e(g)},!1),Le.current?.(),Le.current=null};Re.current=Ye;let pt=(g,L,M=!1)=>{let Y={...f};if(L===null?delete Y[g]:Y[g]=L,V(Y),he.current=Y,!M){Ye();return}j.current!==null&&window.clearTimeout(j.current),Le.current||(Le.current=oe("enrol_apply/queue:width")),j.current=window.setTimeout(()=>Re.current(),fo)},$t=g=>{let L=c!==null&&c.sort===g&&c.direction==="asc"?"desc":"asc";te({sort:g,direction:L,page:0},!0)},p=ke.filter(g=>g.active),P=l==="cards"||l==="auto"&&a;Ke(()=>{ue===null&&ee.current&&(ee.current.focus(),ee.current=null)},[ue]);let E=t.decision,{chosen:W,selection:Ie}=Ln(Ge,Ne,ne,n),vr=(g,L)=>{ee.current=L,Ee(g)},yr=g=>{E===null||ue===null||xn({action:Ze(t,i.current,!0),sesskey:E.sesskey,decision:ue,userenrolments:W.map(L=>L.id),...g})},gr=c===null?null:Be(Vn,{labels:n,list:Bt,shown:p,cards:P,narrow:a,widths:f,sort:c.sort,direction:c.direction,selection:Ie,onSort:$t,onWidth:pt,onWidthCommit:()=>Re.current()}),fr=ho.map(g=>({key:g,title:g==="applicant"?n.applicant:"",sortable:!1,default:!0,active:!0,fixed:g==="applicant"}));return zn("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-queueview",children:[Be(Kn,{labels:n,summary:I,error:we,onRetry:ze}),ne.notingroup?Be("p",{className:"enrol_apply-rview-narrowed",children:n.notingroup}):zn(Ro,{children:[Be(In,{labels:n,narrowed:ne.narrowed,viewonly:ne.viewonly}),Be(Cn,{labels:n,narrow:a,query:w,rows:c,columns:ke,presets:ne.presets,usedefault:_e,haswidths:Object.keys(f).length>0,view:l,search:u,searchmin:t.searchmin,searching:x,tableurl:Ze(t,w,!1),reporturl:t.reporturl,onScope:g=>te({scope:g,page:0},!0),onSearch:Ae,onFilter:Wt,onClearFilters:()=>te({filters:ct(),page:0},!0),onClearAll:()=>{N(""),te({filters:ct(),search:"",page:0},!0)},onColumn:ut,onPreset:dt,onDefaultColumns:()=>{c&&C({...c,preference:{...c.preference,usedefault:!0,columns:[]}}),Fe({usedefault:!0,columns:[],widths:$e(f)},!0)},onResetWidths:()=>{V({}),Fe({usedefault:_e,columns:_e?[]:We,widths:[]},!1)},onView:De}),Be(Nt,{labels:n,loading:x,loaded:c!==null,error:$,total:ne.total,filtered:bo(w),cards:P,narrow:a,skeleton:{columns:fr,lead:"applicant",widths:f,sizing:Pt},dock:Hn(n,E,it,W.length,vr),onRetry:()=>be(i.current),children:gr}),c!==null&&c.total>0?Be(gt,{labels:n,page:w.page,perpage:w.perpage,perpages:t.perpages,total:c.total,shown:c.rows.length,narrow:a,onPage:g=>te({page:g},!0),onPerpage:g=>te({perpage:g,page:0},!0)}):null]}),On(n,E,ue,W,()=>Ee(null),yr)]})},Yn=_o;import{useCallback as cn,useEffect as Ot,useRef as ie,useState as fe}from"react";import{useState as en}from"react";import{Fragment as nn,jsx as S,jsxs as K}from"react/jsx-runtime";var tn=8,jn=7,At=({label:e,value:t,sub:n,warn:r,link:o})=>K("div",{className:"enrol_apply-rview-tile"+(r?" enrol_apply-rview-tile--warn":""),children:[S("div",{className:"enrol_apply-rview-tile-label",children:e}),S("div",{className:"enrol_apply-rview-tile-value",children:t}),S("div",{className:"enrol_apply-rview-tile-sub",children:n}),o??null]}),Xn=({label:e,share:t,figure:n,warn:r})=>K("div",{className:"enrol_apply-rview-hrow",role:"listitem",children:[S("span",{className:"enrol_apply-rview-hrow-label",title:e,children:e}),S("span",{className:"enrol_apply-rview-track","aria-hidden":"true",children:S("span",{className:"enrol_apply-rview-fill"+(r?" enrol_apply-rview-fill--late":""),style:{width:`${Math.round(Math.min(1,Math.max(0,t))*100)}%`}})}),S("span",{className:"enrol_apply-rview-hrow-n",children:n})]}),No=({compact:e})=>K("div",{"aria-hidden":"true",children:[S("div",{className:"enrol_apply-rview-band",children:Array.from({length:e?3:4},(t,n)=>K("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[S("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),S("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"}),S("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"})]},n))}),e?null:S("div",{className:"enrol_apply-rview-dist",children:[0,1].map(t=>K("div",{className:"enrol_apply-rview-panel enrol_apply-rview-sk",children:[S("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"}),S("span",{className:"enrol_apply-rview-skb"}),S("span",{className:"enrol_apply-rview-skb"}),S("span",{className:"enrol_apply-rview-skb"})]},t))})]}),ko=({labels:e,bymethod:t,bygroup:n,methods:r})=>{let[o,a]=en(r>1?"method":"group"),[l,d]=en(!1),[s,i]=en(""),w=o==="method"?t:n,b=Math.max(86400,...w.map(y=>y.median??0)),u=y=>S(Xn,{label:y.name,share:(y.median??0)/b,figure:`${y.median===null?"\u2013":Ve(e,y.median)} (${_(y.count)})`,warn:(y.median??0)>jn*86400},y.id),N=[...w].sort((y,$)=>$.count-y.count),c=o==="group"&&w.length>tn,C=s.trim().toLocaleLowerCase(),x=[...w].filter(y=>C===""||y.name.toLocaleLowerCase().includes(C)).sort((y,$)=>($.median??0)-(y.median??0));return K("div",{className:"enrol_apply-rview-panel",children:[K("div",{className:"enrol_apply-rview-panel-title",children:[S("span",{children:e.medianby}),r>1?S(et,{label:e.breakdown,small:!0,value:o,options:[{value:"method",label:e.bymethod},{value:"group",label:e.bygroup}],onChange:y=>a(y)}):S("span",{children:e.bygroup})]}),c?K(nn,{children:[S("div",{className:"enrol_apply-rview-note",children:k(e.topgroups,_(tn))}),S("div",{role:"list",children:N.slice(0,tn).map(u)}),S("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":l,onClick:()=>d(!l),children:l?e.hide:k(e.allgroups,_(w.length))}),l?K(nn,{children:[S("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:e.searchgroup,"aria-label":e.searchgroup,value:s,onChange:y=>i(y.target.value)}),S("div",{className:"enrol_apply-rview-scrolllist",role:"list",children:x.length?x.map(u):S("p",{className:"enrol_apply-rview-note",children:e.nogroupmatch})})]}):null]}):S("div",{role:"list",children:N.map(u)})]})},Zn=({labels:e,summary:t,loading:n,error:r,compactHint:o,measure:a,methods:l,queueurl:d,pendingstatus:s,onMeasure:i,onRetry:w})=>{let b=K("div",{className:"enrol_apply-rview-bandhead",children:[S("span",{className:"enrol_apply-rview-bandtitle",children:e.turnaround}),S(et,{label:e.measure,small:!0,value:a,options:[{value:"first",label:e.measurefirst},{value:"resolution",label:e.measureresolution}],onChange:N=>i(N)})]}),u;if(t===null&&r)u=K("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",S("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:w,children:e.retry})]});else if(t===null)u=K(nn,{children:[S(No,{compact:o}),S("span",{className:"visually-hidden",role:"status",children:e.loadband})]});else{let N=t.measure==="resolution",c=t.median??null,C=t.p90??null,x=t.oldest??null,y=e.nothingopen;x!==null&&(y=k(e.oldest,Ve(e,x))+(t.late>0?" \xB7 "+k(e.late,_(t.late)):""));let $=d===""?null:S("a",{className:"enrol_apply-rview-tile-link",href:N?d:`${d}&status=${s}`,children:e.openqueue}),G=Math.max(1,...t.buckets.map(I=>I.count));u=K("div",{className:"enrol_apply-rview-dim",children:[K("div",{className:"enrol_apply-rview-band",children:[S(At,{label:N?e.medianresolution:e.medianfirst,value:c===null?"\u2013":Ve(e,c),sub:k(e.mediansub,_(t.decided))}),t.compact?null:S(At,{label:e.p90,value:C===null?"\u2013":Ve(e,C),sub:e.p90sub}),S(At,{label:e.open,value:_(t.open),sub:y,warn:t.late>0,link:$}),S(At,{label:e.withoutperson,value:_(t.withoutperson),sub:e.withoutpersonsub})]}),t.compact?null:K("div",{className:"enrol_apply-rview-dist",children:[K("div",{className:"enrol_apply-rview-panel",children:[K("div",{className:"enrol_apply-rview-panel-title",children:[S("span",{children:N?e.distresolution:e.distfirst}),S("span",{className:"enrol_apply-rview-note",children:k(e.decisions,_(t.decided))})]}),S("div",{role:"list",children:t.buckets.map((I,me)=>{let z=_(I.todays??0),H=re(e.bucket,{from:_(I.fromdays),to:z});return me===0?H=k(e.bucketfirst,_(I.todays??0)):(I.todays??null)===null&&(H=k(e.bucketlast,_(I.fromdays))),S(Xn,{label:H,share:I.count/G,figure:_(I.count),warn:I.fromdays>=jn},I.fromdays)})})]}),S(ko,{labels:e,bymethod:t.bymethod,bygroup:t.bygroup,methods:l})]})]})}return K("section",{className:"enrol_apply-rview-region enrol_apply-rview-bandregion","aria-label":e.turnaround,"aria-busy":n,"data-region":"enrol_apply-band",children:[n&&t!==null?S("div",{className:"enrol_apply-rview-progress"}):null,b,u]})};var Jn=e=>e.facet!==""&&e.options.length>0,er=e=>{try{let t=JSON.parse(e.values||"{}");return t!==null&&typeof t=="object"?t:{}}catch{return{}}},So=e=>e.reduce((t,n)=>({...t,...er(n)}),{}),rn=e=>{if(!e.applied)return"";let t=er(e),n=t[`${e.name}_value`];if(n==null)return"";let r=Number(t[`${e.name}_operator`]??0);if(e.facet==="waited"){let o=Number(n)*Number(t[`${e.name}_unit`]??0)/86400;return r===2&&Number.isFinite(o)?String(o):""}return e.facet==="decider"?r===3?String(n):"":String(n)},tr=(e,t,n)=>{let r=on(e,t);return t.facet==="waited"?(r[`${t.name}_operator`]=n===""?0:2,r[`${t.name}_value`]=n===""?0:Number(n),r[`${t.name}_unit`]=86400):t.facet==="decider"?(r[`${t.name}_operator`]=n===""?0:3,r[`${t.name}_value`]=n):(r[`${t.name}_operator`]=n===""?0:1,r[`${t.name}_value`]=n),r},on=(e,t)=>{let n=So(e);for(let r of Object.keys(n))r.startsWith(`${t.name}_`)&&delete n[r];return n},nr=e=>{let t=rn(e);return e.facet===""||t===""?"":e.options.find(n=>n.value===t)?.label??(e.facet==="decider"?t:"")};import{jsx as rr,jsxs as Lo}from"react/jsx-runtime";var Co=["auto","table","cards"],or=(e,t)=>t.facet==="waited"?e.waitedmore:t.facet==="decider"?e.decidedby:t.title,Eo=(e,t)=>{switch(t){case"decisions":return e.presetdecisions;case"answers":return e.presetanswers;case"groups":return e.presetgroups;default:return t}},Mo=(e,t,n)=>{let r={measure:e};return t!=="all"&&(r.scope=t),n!==""&&(r.search=n),encodeURIComponent(JSON.stringify(r))},xo=(e,t)=>({name:t.name,title:or(e,t),options:t.options,groupings:t.groupings,find:t.facet==="decider"?{label:e.searchdeciders,nomatch:e.nodecidermatch}:{label:e.searchgroups,nomatch:e.nogroupmatch}}),Po=(e,t)=>{let n=nr(t),r=n!==""?or(e,t):t.title;return{key:t.name,text:n===""?r:re(e.filterchip,{title:r,value:n})}},lr=e=>{let{labels:t,filters:n}=e,r=e.measure==="resolution"?"submission:resolutiontime":"submission:waittime",o=u=>Jn(u)&&(u.facet!=="waited"||u.name===r),a=n.filter(o),l=n.filter(u=>!o(u)),d=u=>n.find(N=>N.name===u),s=e.downloadurl!==""&&e.formats.length>0,i=Mo(e.measure,e.scope,e.appliedsearch),w=s?e.formats.map(u=>({key:u.value,label:u.label,href:`${e.downloadurl}&parameters=${i}&download=${encodeURIComponent(u.value)}`})):[],b=e.presets.map(u=>({name:u.name,label:Eo(t,u.name),active:u.active}));return rr(Et,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"awaiting",label:t.awaiting},{value:"decided",label:t.decided}],scope:e.scope,total:e.total,facets:a.map(u=>xo(t,u)),values:Object.fromEntries(a.map(u=>[u.name,rn(u)])),panelnote:l.length>0?Lo("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",rr("a",{href:e.tableurl,children:t.morefilters})]}):null,chips:n.filter(u=>u.applied).map(u=>Po(t,u)),columns:e.columns,sets:b,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:Co,tableurl:e.tableurl,downloads:w,search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:u=>e.onScope(u),onSearch:e.onSearch,onFacet:(u,N)=>{let c=d(u);c&&e.onFilter(c,N)},onRemoveChip:u=>{let N=d(u);N&&e.onRemoveFilter(N)},onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:u=>{let N=e.presets.find(c=>c.name===u);N&&e.onPreset(N)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Ho,jsx as Tt}from"react/jsx-runtime";var Do=["outcome","wait","decided","group"],Ao={applicant:260,outcome:180,submitted:132,wait:150,decided:172,group:200,method:170,route:170,comment:240,note:240},To=180,Fo={applicant:160,submitted:104,decided:104},Io=80,Vo=800,Ft=ft(Ao,To,Fo,Io,Vo),ge=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",It=(e,t)=>Mn(t,Do.includes(e)),ot=({column:e,html:t})=>Tt(Mt,{parts:It(e,t)}),Vt=({row:e,label:t})=>e.reviewurl===""?null:Tt("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:e.reviewurl,children:t}),Ht=({row:e,identity:t})=>Tt(Ho,{children:t.map(n=>{let r=ge(e,n.key);return r===""?null:Tt("span",{className:"enrol_apply-rview-sub",title:yt(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});import{Fragment as ar,jsx as J,jsxs as at}from"react/jsx-runtime";var Oo=["applicant","outcome","submitted","wait","decided","group","method"],lt=({row:e,column:t})=>{let n=It(t,ge(e,t))[0]??"";return n===""?null:J("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:n}})},Bo=(e,t,n,r)=>{let o=l=>t.some(d=>d.key===l),a=t.filter(l=>!Oo.includes(l.key)&&ge(r,l.key).trim()!=="");return{head:at(ar,{children:[at("div",{className:"enrol_apply-rview-card-who",children:[J("span",{className:"enrol_apply-rview-card-name",dangerouslySetInnerHTML:{__html:ge(r,"applicant")}}),o("outcome")?J(lt,{row:r,column:"outcome"}):null,J(Ht,{row:r,identity:n})]}),J(Vt,{row:r,label:e.review})]}),lines:at(ar,{children:[at("div",{className:"enrol_apply-rview-card-line"+(r.open?" enrol_apply-rview-card-line--open":""),children:[o("wait")?J(lt,{row:r,column:"wait"}):null,o("wait")&&o("submitted")?J("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("submitted")?J(lt,{row:r,column:"submitted"}):null]}),o("decided")?J("div",{className:"enrol_apply-rview-card-line",children:It("decided",ge(r,"decided")).map((l,d)=>J("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:l}},d))}):null,o("group")||o("method")?at("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",children:[o("group")?J(lt,{row:r,column:"group"}):null,o("group")&&o("method")&&ge(r,"group")!==""?J("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("method")?J(lt,{row:r,column:"method"}):null]}):null]}),fields:a.map(l=>({key:l.key,title:l.title,content:J(ot,{column:l.key,html:ge(r,l.key)})}))}},sr=({labels:e,columns:t,identity:n,rows:r,single:o})=>J(Rt,{labels:e,rows:r,single:o,renderCard:a=>Bo(e,t,n,a)});import{Fragment as $o,jsx as xe,jsxs as ln}from"react/jsx-runtime";var Wo=({row:e,longest:t})=>!e.open&&!e.decidedbyperson?null:xe("span",{className:"enrol_apply-rview-waitbar"+(e.open?" enrol_apply-rview-waitbar--open":""),"aria-hidden":"true",children:xe("span",{style:{width:`${Math.min(100,Math.round(e.wait/t*100))}%`}})}),ir=({labels:e,columns:t,identity:n,rows:r,widths:o,sort:a,direction:l,onSort:d,onWidth:s,onWidthCommit:i})=>{let w=Math.max(86400,...r.filter(b=>b.open||b.decidedbyperson).map(b=>b.wait));return xe(_t,{labels:e,columns:t,lead:"applicant",rows:r,widths:o,sizing:Ft,sort:a,direction:l,renderLead:(b,u)=>ln("div",{className:"enrol_apply-rview-applicant",children:[ln("div",{className:"enrol_apply-rview-applicant-text",children:[xe(ot,{column:u.key,html:ge(b,u.key)}),xe(Ht,{row:b,identity:n})]}),xe(Vt,{row:b,label:e.review})]}),renderCell:(b,u)=>ln($o,{children:[xe(ot,{column:u.key,html:ge(b,u.key)}),u.key==="wait"?xe(Wo,{row:b,longest:w}):null]}),onSort:d,onWidth:s,onWidthCommit:i})};import{fetchOne as st}from"@moodle/lms/core/ajax";var cr=(e,t,n)=>st({methodname:"enrol_apply_report_rows",args:{enrolid:e,...t,facets:n}}),ur=(e,t,n,r)=>st({methodname:"enrol_apply_report_summary",args:{enrolid:e,measure:t,scope:n,search:r}}),dr=(e,t)=>st({methodname:"enrol_apply_set_report_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}}),an=(e,t,n)=>st({methodname:"core_reportbuilder_set_filters",args:{reportid:e,parameters:JSON.stringify({measure:t}),values:JSON.stringify(n)}}),sn=(e,t)=>st({methodname:"core_reportbuilder_filters_reset",args:{reportid:e,parameters:JSON.stringify({measure:t})}});import{Fragment as Go,jsx as Pe,jsxs as un}from"react/jsx-runtime";var Uo=300,Qo=500,pr="enrol_apply_report_view",qo=["applicant","outcome","submitted","wait","decided","group"],Ko=e=>{let{labels:t,enrolid:n,reportid:r}=e,o=ie(null),a=mt(o),[l,d]=fe(()=>wt(pr)),s={measure:e.measure,scope:"all",search:"",page:0,perpage:e.perpage,sort:"",direction:"desc"},i=ie(s),[w,b]=fe(s),[u,N]=fe(""),[c,C]=fe(null),[x,y]=fe(!0),[$,G]=fe(!1),[I,me]=fe(null),[z,H]=fe(!0),[we,v]=fe(!1),[f,V]=fe({}),[h,O]=fe({}),m=ie(0),T=ie(0),F=ie(null),U=ie(null),ce=ie(null),j=ie(null),Le=ie(!1),he=ie(null),Re=ie(null),Ge=ie(null),Ne=ie(()=>{}),ue=cn((p,P)=>{let E=++m.current;F.current||(F.current=oe("enrol_apply/report:rows")),y(!0),cr(n,p,P).then(W=>{E===m.current&&(W.counted&&O(Object.fromEntries(W.filters.map(Ie=>[Ie.name,Ie]))),Le.current||(Le.current=!0,V(Object.fromEntries(W.preference.widths.map(Ie=>[Ie.key,Ie.width])))),W.page!==i.current.page&&(i.current={...i.current,page:W.page},b(i.current)),C(W),G(!1),y(!1))}).catch(()=>{E===m.current&&(G(!0),y(!1))})},[n]),Ee=cn(p=>{let P=++T.current;U.current||(U.current=oe("enrol_apply/report:band")),H(!0),ur(n,p.measure,p.scope,p.search).then(E=>{P===T.current&&(me(E),v(!1),H(!1))}).catch(()=>{P===T.current&&(v(!0),H(!1))})},[n]),ee=cn((p,P,E)=>{i.current={...i.current,...p},b(i.current),P&&ue(i.current,E),E&&Ee(i.current)},[ue,Ee]);Ot(()=>{!x&&F.current&&(F.current(),F.current=null)},[x,c,$]),Ot(()=>{!z&&U.current&&(U.current(),U.current=null)},[z,I,we]),Ot(()=>{ue(i.current,!0),Ee(i.current)},[ue,Ee]),Ot(()=>()=>{ce.current!==null&&window.clearTimeout(ce.current),j.current?.(),Ne.current()},[]);let be=p=>{d(p),vt(pr,p)},ze=p=>{N(p),ce.current!==null&&(window.clearTimeout(ce.current),ce.current=null);let P=p.trim(),E=P.length>=e.searchmin?P:"";if(E===i.current.search){j.current?.(),j.current=null;return}if(p===""){j.current?.(),j.current=null,ee({search:"",page:0},!0,!0);return}j.current||(j.current=oe("enrol_apply/report:search")),ce.current=window.setTimeout(()=>{ce.current=null,ee({search:E,page:0},!0,!0),j.current?.(),j.current=null},Uo)},te=p=>{let P=oe("enrol_apply/report:filters");p().then(()=>{ee({page:0},!0,!0)}).catch(()=>{G(!0)}).finally(P)},De=(c?.filters??[]).map(p=>h[p.name]?{...p,options:h[p.name].options,groupings:h[p.name].groupings}:p),Ae=(p,P)=>{let E=oe("enrol_apply/report:preference");dr(n,p).then(()=>{P&&ue(i.current,!1)}).catch(()=>{P&&G(!0)}).finally(E)},ne=c?.columns??[],Te=ne.filter(p=>!p.fixed&&p.active).map(p=>p.key),ke=c?.preference.usedefault??!0,_e=p=>Object.entries(p).map(([P,E])=>({key:P,width:E})),Bt=(p,P)=>{let E=P?[...Te,p]:Te.filter(W=>W!==p);c&&C({...c,columns:c.columns.map(W=>W.key===p?{...W,active:P}:W),preference:{...c.preference,usedefault:!1,columns:E}}),Ae({usedefault:!1,columns:E,widths:_e(f)},!0)},it=()=>{he.current!==null&&(window.clearTimeout(he.current),he.current=null);let p=Ge.current;Ge.current=null,p!==null&&Ae({usedefault:ke,columns:ke?[]:Te,widths:_e(p)},!1),Re.current?.(),Re.current=null};Ne.current=it;let Wt=(p,P,E=!1)=>{let W={...f};if(P===null?delete W[p]:W[p]=P,V(W),Ge.current=W,!E){it();return}he.current!==null&&window.clearTimeout(he.current),Re.current||(Re.current=oe("enrol_apply/report:width")),he.current=window.setTimeout(()=>Ne.current(),Qo)},ct=p=>{let P=new Set(p.columns);c&&C({...c,columns:c.columns.map(E=>E.fixed?E:{...E,active:P.has(E.key)||P.has("answers")&&E.key.startsWith("answer:")}),presets:c.presets.map(E=>({...E,active:E.name===p.name})),preference:{...c.preference,usedefault:!1,columns:p.columns}}),Ae({usedefault:!1,columns:p.columns,widths:_e(f)},!0)},Fe=p=>{let E=(c?.sort??"")===p&&c?.direction==="asc"?"desc":"asc";ee({sort:p,direction:E,page:0},!0,!1)},We=p=>{let P=i.current,E=ne.some(W=>W.key==="wait"&&W.active);ee({measure:p},P.scope!=="all"||P.sort==="wait"||E,!0)},$e=De.some(p=>p.applied)||w.search!==""||w.scope!=="all",ut=ne.filter(p=>p.key.startsWith("identity:")),dt=ne.filter(p=>p.key==="applicant"||!p.fixed&&p.active),Ye=l==="cards"||l==="auto"&&a,pt=null;c!==null&&(pt=Ye?Pe(sr,{labels:t,columns:dt,identity:ut,rows:c.rows,single:a}):Pe(ir,{labels:t,columns:dt,identity:ut,rows:c.rows,widths:f,sort:c.sort,direction:c.direction,onSort:Fe,onWidth:Wt,onWidthCommit:()=>Ne.current()}));let $t=qo.map(p=>({key:p,title:p==="applicant"?t.applicant:"",sortable:!1,default:!0,active:!0,fixed:p==="applicant"}));return un("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-reportview",children:[c?.narrowed?Pe("p",{className:"enrol_apply-rview-narrowed",children:t.narrowed}):null,Pe(Zn,{labels:t,summary:I,loading:z,error:we,compactHint:c?.narrowed??!1,measure:w.measure,methods:e.methods,queueurl:e.queueurl,pendingstatus:e.pendingstatus,onMeasure:We,onRetry:()=>Ee(i.current)}),Pe(lr,{labels:t,narrow:a,scope:w.scope,total:c===null?null:c.total,filters:De,columns:ne.filter(p=>!p.key.startsWith("identity:")||p.active),presets:c?.presets??[],usedefault:ke,haswidths:Object.keys(f).length>0,view:l,measure:w.measure,search:u,appliedsearch:w.search,searchmin:e.searchmin,searching:x,tableurl:e.tableurl,downloadurl:e.downloadurl,formats:e.formats,onScope:p=>ee({scope:p,page:0},!0,!0),onSearch:ze,onFilter:(p,P)=>te(()=>an(r,i.current.measure,tr(De,p,P))),onRemoveFilter:p=>te(()=>an(r,i.current.measure,on(De,p))),onClearFilters:()=>te(()=>sn(r,i.current.measure)),onClearAll:()=>{N(""),i.current={...i.current,search:""},De.some(p=>p.applied)?te(()=>sn(r,i.current.measure)):ee({page:0},!0,!0)},onColumn:Bt,onPreset:ct,onDefaultColumns:()=>{c&&C({...c,preference:{...c.preference,usedefault:!0,columns:[]}}),Ae({usedefault:!0,columns:[],widths:_e(f)},!0)},onResetWidths:()=>{V({}),Ae({usedefault:ke,columns:ke?[]:Te,widths:[]},!1)},onView:be}),Pe(Nt,{labels:t,loading:x,loaded:c!==null,error:$,total:c?.total??0,filtered:$e,cards:Ye,narrow:a,skeleton:{columns:$t,lead:"applicant",widths:f,sizing:Ft},onRetry:()=>ue(i.current,!0),children:pt}),c!==null&&c.total>0?Pe(gt,{labels:t,page:w.page,perpage:w.perpage,perpages:e.perpages,total:c.total,shown:c.rows.length,narrow:a,onPage:p=>ee({page:p},!0,!1),onPerpage:p=>ee({perpage:p,page:0},!0,!1)}):null,e.retention!==""?un("p",{className:"enrol_apply-rview-retention","data-region":"enrol_apply-retention",children:[e.retention,e.retentionurl!==""?un(Go,{children:[" ",Pe("a",{href:e.retentionurl,children:t.retentionchange})]}):null]}):null]})},mr=Ko;import{jsx as wr}from"react/jsx-runtime";var zo=e=>e.mount===void 0||e.mount==="report"?wr(mr,{...e}):e.mount==="queue"?wr(Yn,{...e}):null,_s=zo;export{_s as default};
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
