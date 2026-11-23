import{useCallback as Bt,useEffect as Ke,useRef as j,useState as ue}from"react";import{useState as zn}from"react";import{useEffect as $n,useRef as ot}from"react";var tt=(e,t,n,r,o)=>({defaultWidth:l=>e[l]??t,minimumWidth:l=>n[l]??r,maximum:o}),ie=(e,t,n)=>Math.min(e.maximum,Math.max(e.minimumWidth(n),t[n]??e.defaultWidth(n)));var x=(e,t)=>(e||"").split("{$a}").join(t),J=(e,t)=>Object.entries(t).reduce((n,[r,o])=>n.split(`{$a->${r}}`).join(o),e||""),Jt=(e,t,n,r)=>x(n===1&&e[`${t}_one`]?e[`${t}_one`]:e[t],r),R=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(t).format(e)}catch{return String(e)}},xe=(e,t)=>{let n=Math.max(0,t);if(n<3600)return x(e.waitminutes,R(Math.max(1,Math.round(n/60))));if(n<86400)return x(e.waithours,R(Math.round(n/3600)));let r=Math.floor(n/86400),o=Math.round((n-r*86400)/3600);return o===24&&(r++,o=0),o>0&&r<10?J(e.waitdayshours,{days:R(r),hours:R(o)}):x(e.waitdays,R(r))},nt=e=>{let t=document.createElement("template");return t.innerHTML=e,(t.content.textContent||"").replace(/\s+/g," ").trim()};import{jsx as P,jsxs as Oe}from"react/jsx-runtime";var rt=16,qn={ascending:"\u25B2",descending:"\u25BC",none:""},Kn=(e,t)=>e?t==="asc"?"ascending":"descending":"none",Gn=({selection:e,rows:t})=>{let n=ot(null),r=t.filter(s=>!e.locked.has(s.id)),o=r.filter(s=>e.selected.has(s.id)).length,l=r.length>0&&o===r.length;return $n(()=>{n.current&&(n.current.indeterminate=o>0&&!l)},[o,l]),P("input",{ref:n,type:"checkbox",className:"enrol_apply-rview-pick","aria-label":e.pagelabel,checked:l,disabled:r.length===0,onChange:s=>e.onSelectPage(s.target.checked)})},Lt=({selection:e,row:t})=>e.locked.has(t.id)?P("span",{className:"enrol_apply-rview-pick enrol_apply-rview-pick--none"}):P("input",{type:"checkbox",className:"enrol_apply-rview-pick","aria-label":e.label(t),checked:e.selected.has(t.id),onChange:n=>e.onSelect(t.id,n.target.checked)}),at=({labels:e,columns:t,lead:n,rows:r,widths:o,sizing:l,sort:s,direction:d,selection:u,renderLead:c,renderCell:f,onSort:h,onWidth:w,onWidthCommit:N})=>{let i=ot(null),C=ot(null),M=ot(null),y=t.reduce((v,g)=>v+ie(l,o,g.key),0),Q=v=>{v.currentTarget.classList.toggle("is-scrolled",v.currentTarget.scrollLeft>0)},G=(v,g)=>{let T=C.current?.querySelector(`col[data-col="${CSS.escape(v)}"]`);!T||!C.current||(T.style.width=`${g}px`,C.current.style.width=`${y-ie(l,o,v)+g}px`)},A=(v,g)=>Math.round(Math.min(l.maximum,Math.max(l.minimumWidth(v),g))),ge=(v,g)=>{if(v.button!==0)return;v.preventDefault(),v.currentTarget.setPointerCapture(v.pointerId),v.currentTarget.classList.add("is-active");let T=ie(l,o,g);M.current={key:g,x:v.clientX,width:T,now:T}},z=v=>{let g=M.current;if(!g)return;let T=document.documentElement.dir==="rtl",I=(v.clientX-g.x)*(T?-1:1),m=A(g.key,g.width+I);m!==g.now&&(G(g.key,m),g.now=m)},$=v=>{let g=M.current;M.current=null,v.currentTarget.classList.remove("is-active"),g&&g.now!==g.width&&w(g.key,g.now)},ae=(v,g)=>{let T=document.documentElement.dir==="rtl",I=0;if(v.key==="ArrowRight")I=T?-rt:rt;else if(v.key==="ArrowLeft")I=T?rt:-rt;else if(v.key==="Home"){v.preventDefault(),w(g,null,!0);return}if(I===0)return;v.preventDefault();let m=A(g,ie(l,o,g)+I);m!==ie(l,o,g)&&w(g,m,!0)};return P("div",{className:"enrol_apply-rview-gridframe",ref:i,tabIndex:0,role:"region","aria-label":e.grid,onScroll:Q,children:Oe("table",{className:"enrol_apply-rview-grid",ref:C,style:{width:`${y}px`},children:[P("caption",{className:"visually-hidden",children:e.caption}),P("colgroup",{children:t.map(v=>P("col",{"data-col":v.key,style:{width:`${ie(l,o,v.key)}px`}},v.key))}),P("thead",{children:P("tr",{children:t.map(v=>{let g=Kn(s===v.key,d),T=P("span",{className:"enrol_apply-rview-th-title",children:v.title});return Oe("th",{scope:"col",className:v.key===n?"enrol_apply-rview-fixed":void 0,"aria-sort":v.sortable?g:void 0,title:v.title,"data-column":v.key,children:[u&&v.key===n?P(Gn,{selection:u,rows:r}):null,v.sortable?Oe("button",{type:"button",className:"enrol_apply-rview-sortbtn",onClick:()=>h(v.key),children:[T,P("span",{className:"enrol_apply-rview-arrow","aria-hidden":"true",children:qn[g]})]}):P("span",{className:"enrol_apply-rview-plain",children:T}),P("span",{className:"enrol_apply-rview-resize",role:"separator","aria-orientation":"vertical","aria-label":x(e.resize,v.title),"aria-valuemin":l.minimumWidth(v.key),"aria-valuemax":l.maximum,"aria-valuenow":ie(l,o,v.key),tabIndex:0,onPointerDown:I=>ge(I,v.key),onPointerMove:z,onPointerUp:$,onPointerCancel:$,onDoubleClick:()=>{ie(l,o,v.key)!==l.defaultWidth(v.key)&&w(v.key,null)},onKeyDown:I=>ae(I,v.key),onBlur:N})]},v.key)})})}),P("tbody",{children:r.map(v=>P("tr",{"data-record":v.id,className:u?.selected.has(v.id)?"enrol_apply-rview-row--selected":void 0,children:t.map(g=>g.key===n?Oe("td",{className:"enrol_apply-rview-fixed",children:[u?P(Lt,{selection:u,row:v}):null,c(v,g)]},g.key):P("td",{children:f(v,g)},g.key))},v.id))})]})})},Zt=({columns:e,lead:t,widths:n,sizing:r})=>{let o=e.reduce((s,d)=>s+ie(r,n,d.key),0),l=["enrol_apply-rview-skb--w80","enrol_apply-rview-skb--w60","enrol_apply-rview-skb--w40"];return P("div",{className:"enrol_apply-rview-gridframe","aria-hidden":"true",children:Oe("table",{className:"enrol_apply-rview-grid",style:{width:`${o}px`},children:[P("colgroup",{children:e.map(s=>P("col",{style:{width:`${ie(r,n,s.key)}px`}},s.key))}),P("thead",{children:P("tr",{children:e.map(s=>P("th",{className:s.key===t?"enrol_apply-rview-fixed":void 0,children:P("span",{className:"enrol_apply-rview-plain",children:P("span",{className:"enrol_apply-rview-th-title",children:s.title})})},s.key))})}),P("tbody",{children:Array.from({length:8},(s,d)=>P("tr",{className:"enrol_apply-rview-sk",children:e.map((u,c)=>P("td",{className:u.key===t?"enrol_apply-rview-fixed":void 0,children:P("span",{className:"enrol_apply-rview-skb "+l[(d+c)%3]})},u.key))},d))})]})})};import{jsx as oe,jsxs as Be}from"react/jsx-runtime";var Yn=({labels:e,row:t,single:n,selection:r,renderCard:o})=>{let[l,s]=zn(!1),d=o(t),u=!n||l,c=r?.selected.has(t.id)??!1;return Be("article",{className:"enrol_apply-rview-card"+(c?" enrol_apply-rview-card--selected":""),role:"listitem","data-record":t.id,children:[Be("div",{className:"enrol_apply-rview-card-l1",children:[r?oe(Lt,{selection:r,row:t}):null,d.head]}),d.lines,d.fields.length>0?Be("div",{className:"enrol_apply-rview-card-more",children:[n?oe("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":l,onClick:()=>s(!l),children:l?e.less:e.more}):null,u?oe("dl",{className:"enrol_apply-rview-card-list",children:d.fields.map(f=>Be("div",{className:"enrol_apply-rview-card-item",children:[oe("dt",{children:f.title}),oe("dd",{children:f.content})]},f.key))}):null]}):null]})},lt=({labels:e,rows:t,single:n,selection:r,renderCard:o})=>oe("div",{className:"enrol_apply-rview-cards"+(n?" enrol_apply-rview-cards--single":""),role:"list",children:t.map(l=>oe(Yn,{labels:e,row:l,single:n,selection:r,renderCard:o},l.id))}),en=({single:e})=>oe("div",{className:"enrol_apply-rview-cards"+(e?" enrol_apply-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:e?4:6},(t,n)=>Be("div",{className:"enrol_apply-rview-card enrol_apply-rview-sk",children:[oe("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),oe("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"}),oe("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),oe("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"})]},n))});import{useEffect as jn,useState as Xn}from"react";var Jn=576,st=e=>{let[t,n]=Xn(!1);return jn(()=>{let r=e.current;if(!r||typeof ResizeObserver>"u")return;let o=new ResizeObserver(l=>{let s=l[0]?.contentRect.width??r.clientWidth;n(s>0&&s<Jn)});return o.observe(r),()=>o.disconnect()},[e]),t},it=e=>{try{let t=window.localStorage.getItem(e);return t==="table"||t==="cards"?t:"auto"}catch{return"auto"}},ct=(e,t)=>{try{window.localStorage.setItem(e,t)}catch{}};import{useId as Zn}from"react";import{jsx as _e,jsxs as At}from"react/jsx-runtime";var er=(e,t)=>[...new Set([1,e-1,e,e+1,t])].filter(n=>n>=1&&n<=t).sort((n,r)=>n-r),ut=({labels:e,page:t,perpage:n,perpages:r,total:o,shown:l,narrow:s,onPage:d,onPerpage:u})=>{let c=Zn(),f=Math.max(1,Math.ceil(o/n)),h=t+1,w=o>0?t*n+1:0,N=o>0?w+l-1:0,i=[];if(s)i=[_e("span",{className:"enrol_apply-rview-pager-gap",children:J(e.pageof,{page:R(h),pages:R(f)})},"of")];else{let C=er(h,f);C.forEach((M,y)=>{y>0&&M-C[y-1]>1&&i.push(_e("span",{className:"enrol_apply-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${M}`)),i.push(_e("button",{type:"button","aria-current":M===h?"page":void 0,onClick:()=>d(M-1),children:R(M)},M))})}return At("div",{className:"enrol_apply-rview-foot",children:[_e("span",{className:"enrol_apply-rview-num","data-region":"enrol_apply-range",children:J(e.range,{from:R(w),to:R(N),total:R(o)})}),f>1?At("nav",{className:"enrol_apply-rview-pager","aria-label":e.pages,children:[_e("button",{type:"button",disabled:h===1,onClick:()=>d(t-1),children:e.previous}),i,_e("button",{type:"button",disabled:h===f,onClick:()=>d(t+1),children:e.next})]}):null,At("span",{className:"enrol_apply-rview-perpage",children:[_e("label",{htmlFor:c,children:e.perpage}),_e("select",{id:c,className:"form-select form-select-sm",value:n,onChange:C=>u(Number(C.target.value)),children:r.map(C=>_e("option",{value:C,children:R(C)},C))})]})]})};var Z=e=>{let t=window.M?.util;t?.js_pending(e);let n=!0;return()=>{n&&(n=!1,t?.js_complete(e))}};import{Fragment as tn,jsx as Re,jsxs as Ae}from"react/jsx-runtime";var tr=({labels:e,loaded:t,error:n,total:r,filtered:o,cards:l,narrow:s,skeleton:d,children:u,onRetry:c})=>{let f=Ae("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",Re("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:c,children:e.retry})]});return!t&&n?f:t?r===0?Ae("div",{className:"enrol_apply-rview-empty",children:[Re("p",{className:"enrol_apply-rview-empty-title",children:o?e.nomatch:e.empty}),o?Re("p",{className:"enrol_apply-rview-empty-text",children:e.nomatchhelp}):null]}):Ae("div",{className:"enrol_apply-rview-dim",children:[n?f:null,u,l?null:Re("p",{className:"enrol_apply-rview-hint",children:e.scrollhint})]}):Ae(tn,{children:[l?Re(en,{single:s}):Re(Zt,{...d}),Re("span",{className:"visually-hidden",role:"status",children:e.loading})]})},pt=e=>Ae(tn,{children:[Ae("section",{className:"enrol_apply-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"enrol_apply-rows",children:[e.loading&&e.loaded?Re("div",{className:"enrol_apply-rview-progress"}):null,Re(tr,{...e})]}),e.dock??null]});var nr="grid",Tt="enrolApplyQueue",rr=(e,t,n)=>{let r=[];return t.scope!=="all"&&r.push(["status",String(t.scope==="pending"?e.statuses.pending:e.statuses.deferred)]),t.search!==""&&r.push(["search",t.search]),Object.keys(t.filters).sort().forEach(o=>{t.filters[o]!==""&&r.push([o,t.filters[o]])}),n&&(t.sort!==""&&(t.sort!=="wait"||t.direction!=="asc")&&r.push(["sort",t.sort],["dir",t.direction]),t.page>0&&r.push(["page",String(t.page)]),t.perpage!==e.perpage&&r.push(["perpage",String(t.perpage)]),r.push(["view",nr])),r},dt=(e,t,n)=>{let r=new URL(e.pageurl,window.location.href);return rr(e,t,n).forEach(([o,l])=>r.searchParams.set(o,l)),r.toString()},nn=e=>({scope:e.initial.scope,search:e.initial.search,filters:Object.fromEntries(e.initial.filters.map(t=>[t.name,t.value])),sort:e.initial.sort,direction:e.initial.direction,page:e.initial.page,perpage:e.initial.perpage}),mt=(e,t,n)=>{let r=dt(e,t,!0),o={[Tt]:t};try{n&&r!==window.location.href?window.history.pushState(o,"",r):window.history.replaceState(o,"",r)}catch{}document.querySelectorAll('[data-enrol-apply-queue="tablelink"]').forEach(l=>{l.href=dt(e,t,!1)})},rn=e=>e===null||typeof e!="object"||!(Tt in e)?null:e[Tt];import{useEffect as ft,useId as mr,useRef as Qe,useState as dn}from"react";import{useId as ar,useState as an}from"react";import{jsx as on}from"react/jsx-runtime";var or={filter:"M2 3h12l-4.5 5.5V13l-3 1.5V8.5L2 3z",columns:"M2 3h12v10H2zM6.5 3v10M10 3v10",table:"M2 3h12v10H2zM2 6.5h12M2 10h12M6 6.5V13",cards:"M2 3h5v4H2zM9 3h5v4H9zM2 9h5v4H2zM9 9h5v4H9z",auto:"M2 3h7v10H2zM11 3h3v4h-3zM11 9h3v4h-3z",download:"M8 2v8M4.5 7 8 10.5 11.5 7M3 13h10",search:"M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM10.5 10.5 14 14",close:"M4 4l8 8M12 4l-8 8",eraser:"M9.5 2 14 6.5 7.5 13 3 8.5zM5.6 5.9l4.5 4.5M7.5 13H14",tick:"M3 8.5 6.5 12 13 4.5",chevron:"M4 6l4 4 4-4",reset:"M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5",queue:"M3 4h10M3 8h10M3 12h6"},H=({name:e,className:t})=>on("svg",{className:"enrol_apply-rview-icon"+(t?" "+t:""),viewBox:"0 0 16 16",width:"16",height:"16",fill:"none",stroke:"currentColor",strokeWidth:"1.5",strokeLinecap:"round",strokeLinejoin:"round","aria-hidden":"true",focusable:"false",children:on("path",{d:or[e]??""})});import{Fragment as pr,jsx as B,jsxs as ce}from"react/jsx-runtime";var lr=8,Dt=50,sr=(e,t)=>t===null?e.show:x(e.showcount,R(t)),ln=({option:e})=>e.count>=0?B("small",{className:"enrol_apply-rview-optcount",children:R(e.count)}):null,ir=(e,t)=>e.options.filter(n=>n.count!==0||n.value===t),cr=({labels:e,facet:t,shown:n,value:r,headid:o,onChange:l})=>{let[s,d]=an(""),[u,c]=an(""),f=s.trim().toLocaleLowerCase(),h=t.groupings.length>1?t.groupings:[],w=h.find(y=>String(y.id)===u)?.groups.map(String)??null,N=n.filter(y=>y.value===r),i=n.filter(y=>y.value!==r&&(f===""||y.label.toLocaleLowerCase().includes(f))&&(w===null||w.includes(y.value))),C=x(t.find.label,R(t.options.length)),M=h.length>0?e.moregroupsgrouping:e.moregroups;return ce(pr,{children:[ce("div",{className:"enrol_apply-rview-find",children:[B("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:C,"aria-label":C,value:s,onChange:y=>d(y.target.value)}),h.length>0?ce("select",{className:"form-select form-select-sm enrol_apply-rview-grouping","aria-label":e.grouping,value:u,onChange:y=>c(y.target.value),children:[B("option",{value:"",children:e.allgroupings}),h.map(y=>B("option",{value:String(y.id),children:y.name},y.id))]}):null]}),ce("div",{className:"enrol_apply-rview-optlist",role:"group","aria-labelledby":o,children:[[...N,...i.slice(0,Dt)].map(y=>ce("button",{type:"button",className:"enrol_apply-rview-optrow","aria-pressed":y.value===r,onClick:()=>l(t.name,y.value===r?"":y.value),children:[B("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:B(H,{name:"tick"})}),B("span",{className:"enrol_apply-rview-optrow-label",children:y.label})," ",B(ln,{option:y})]},y.value)),i.length>Dt?B("p",{className:"enrol_apply-rview-note",children:x(M,R(i.length-Dt))}):null,N.length===0&&i.length===0?B("p",{className:"enrol_apply-rview-note",children:t.find.nomatch}):null]})]})},ur=({labels:e,facet:t,value:n,onChange:r})=>{let o=ar(),l=ir(t,n),s=[{value:"",label:e.any,count:-1},...l];if(t.options.length>lr)return ce("div",{className:"enrol_apply-rview-fgroup",children:[B("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),B(cr,{labels:e,facet:t,shown:l,value:n,headid:o,onChange:r})]});let d=u=>{let c=s.findIndex(h=>h.value===n),f=-1;u.key==="ArrowRight"||u.key==="ArrowDown"?f=(c+1)%s.length:u.key==="ArrowLeft"||u.key==="ArrowUp"?f=(c-1+s.length)%s.length:u.key==="Home"?f=0:u.key==="End"&&(f=s.length-1),!(f<0)&&(u.preventDefault(),r(t.name,s[f].value),u.currentTarget.querySelectorAll('[role="radio"]')[f]?.focus())};return ce("div",{className:"enrol_apply-rview-fgroup",children:[B("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),B("div",{className:"enrol_apply-rview-fpanel-opts",role:"radiogroup","aria-labelledby":o,onKeyDown:d,children:s.map(u=>ce("button",{type:"button",className:"enrol_apply-rview-fpanel-opt",role:"radio","aria-checked":u.value===n,tabIndex:u.value===n?0:-1,onClick:()=>r(t.name,u.value),children:[u.label," ",B(ln,{option:u})]},u.value))})]})},sn=({labels:e,id:t,hidden:n,facets:r,values:o,total:l,note:s,onChange:d,onClear:u,onShow:c})=>ce("div",{className:"enrol_apply-rview-fpanel",id:t,role:"dialog","aria-label":e.filters,hidden:n,children:[ce("div",{className:"enrol_apply-rview-fpanel-body",children:[r.map(f=>B(ur,{labels:e,facet:f,value:o[f.name]??"",onChange:d},f.name)),s??null]}),ce("div",{className:"enrol_apply-rview-fpanel-foot",children:[B("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:u,children:e.clearfilters}),B("button",{type:"button",className:"btn btn-sm btn-primary",onClick:c,children:sr(e,l)})]})]});import{Fragment as dr,jsx as We,jsxs as cn}from"react/jsx-runtime";var wt=e=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(e.key))return;let t=Array.from(e.currentTarget.querySelectorAll('[role^="menuitem"]'));if(t.length===0)return;e.preventDefault();let n=t.indexOf(document.activeElement),r=0;e.key==="End"?r=t.length-1:e.key==="ArrowDown"?r=(n+1)%t.length:e.key==="ArrowUp"&&(r=(n-1+t.length)%t.length),t[r].focus()},un=({heading:e,sets:t,onSet:n})=>t.length===0?null:cn(dr,{children:[We("div",{className:"enrol_apply-rview-menu-head",role:"presentation",children:e}),t.map(r=>cn("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":r.active,tabIndex:-1,"data-preset":r.name,onClick:()=>{r.active||n(r.name)},children:[We("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:We(H,{name:"tick"})}),We("span",{className:"enrol_apply-rview-menu-label",children:r.label})]},r.name)),We("div",{className:"enrol_apply-rview-menu-sep",role:"separator"})]});import{jsx as pn}from"react/jsx-runtime";var Ue=({label:e,value:t,options:n,small:r,onChange:o})=>{let l=s=>{let d=n.findIndex(f=>f.value===t),u=-1;if(s.key==="ArrowRight"||s.key==="ArrowDown"?u=(d+1)%n.length:s.key==="ArrowLeft"||s.key==="ArrowUp"?u=(d-1+n.length)%n.length:s.key==="Home"?u=0:s.key==="End"&&(u=n.length-1),u<0)return;s.preventDefault(),o(n[u].value),s.currentTarget.querySelectorAll('[role="radio"]')[u]?.focus()};return pn("div",{className:"enrol_apply-rview-seg"+(r?" enrol_apply-rview-seg--small":""),role:"radiogroup","aria-label":e,onKeyDown:l,children:n.map(s=>pn("button",{type:"button",className:"enrol_apply-rview-seg-cell",role:"radio","aria-checked":s.value===t,tabIndex:s.value===t?0:-1,onClick:()=>o(s.value),children:s.label},s.value))})};import{Fragment as mn,jsx as _,jsxs as D}from"react/jsx-runtime";var vt=e=>{let{labels:t,chips:n,columns:r}=e,o=mr(),l=Qe(null),s=Qe(null),d=Qe(null),u=Qe(!1),[c,f]=dn(null),[h,w]=dn(e.search!==""),N=Qe({});ft(()=>{if(c===null)return;let m=L=>{l.current&&!l.current.contains(L.target)&&f(null)};return document.addEventListener("mousedown",m),()=>document.removeEventListener("mousedown",m)},[c]),ft(()=>{if(c===null||c==="filters")return;let m=l.current?.querySelector(`#${CSS.escape(o+c)}`);(m?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])')??m?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])'))?.focus()},[c,o]),ft(()=>{h?s.current?.focus():u.current&&(u.current=!1,d.current?.focus())},[h]),ft(()=>{let m=L=>{if(L.key!=="/"||L.ctrlKey||L.metaKey||L.altKey||L.defaultPrevented)return;let O=document.activeElement;if(O&&(O.isContentEditable||O.closest("input, textarea, select")))return;let F=l.current?.getBoundingClientRect();!F||F.width===0||F.bottom<=0||F.top>=window.innerHeight||(L.preventDefault(),h?s.current?.focus():(f(null),w(!0)))};return document.addEventListener("keydown",m),()=>document.removeEventListener("keydown",m)},[h]);let i=m=>f(c===m?null:m),C=m=>{u.current=m,w(!1)},M=m=>{if(!(m.key!=="Escape"||m.defaultPrevented))if(c!==null){m.preventDefault();let L=N.current[c];f(null),L?.focus()}else h&&(m.preventDefault(),C(!0))},y=e.search.trim().length>=e.searchmin,Q=y&&!h,G=n.length+(Q?1:0),A=e.total===null?"\u2026":Jt(t,"count",e.total,R(e.total)),ge=e.total!==null&&e.narrow?D(mn,{children:[_("span",{"aria-hidden":"true",children:R(e.total)}),_("span",{className:"visually-hidden",children:A})]}):A,z=(m,L,O,F)=>D("button",{type:"button",className:"enrol_apply-rview-iconbtn","aria-label":O,"data-enrol-apply-tip":O,"aria-haspopup":m==="filters"?"dialog":"menu","aria-expanded":c===m,"aria-controls":o+m,ref:U=>{N.current[m??""]=U},onClick:()=>i(m),children:[_(H,{name:L}),F]}),$={auto:"auto",table:"table",cards:"cards"}[e.view],ae=_(H,{name:"chevron",className:"enrol_apply-rview-chevron"}),v=n.length>0?x(t.filterson,R(n.length)):t.filters,g=n.length>0?_("span",{className:"enrol_apply-rview-badge","aria-hidden":"true",children:R(n.length)}):null,T=[["auto",t.viewauto,t.viewautohint],["table",t.table,""],["cards",t.cards,""]].filter(([m])=>e.views.includes(m)),I="enrol_apply-rview-bar"+(e.narrow?" enrol_apply-rview-bar--narrow":"")+(h?" enrol_apply-rview-bar--searching":"");return D("div",{className:I,"data-region":"enrol_apply-bar",ref:l,onKeyDown:M,children:[_(Ue,{label:t.scope,value:e.scope,options:e.scopes,onChange:e.onScope}),_("span",{className:"enrol_apply-rview-bar-spacer"}),D("div",{className:"enrol_apply-rview-bar-controls",children:[D("div",{className:"enrol_apply-rview-bar-cluster",children:[_("span",{className:"enrol_apply-rview-bar-count","data-region":"enrol_apply-total","aria-live":"polite",children:ge}),D("div",{className:"enrol_apply-rview-capsule",children:[z("filters","filter",v,g),D("div",{className:"enrol_apply-rview-anchor",children:[z("columns","columns",t.columns,ae),D("div",{className:"enrol_apply-rview-menu",id:o+"columns",role:"menu","aria-label":t.columns,hidden:c!=="columns",onKeyDown:wt,children:[_(un,{heading:t.presets,sets:e.sets,onSet:e.onSet}),r.map(m=>m.fixed?D("div",{className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":"true","aria-disabled":"true",tabIndex:-1,children:[_("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:_(H,{name:"tick"})}),_("span",{className:"enrol_apply-rview-menu-label",children:m.title}),_("span",{className:"enrol_apply-rview-menu-hint",children:t.always})]},m.key):D("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":m.active,tabIndex:-1,onClick:()=>e.onColumn(m.key,!m.active),children:[_("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:_(H,{name:"tick"})}),_("span",{className:"enrol_apply-rview-menu-label",children:m.title})]},m.key)),_("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),D("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":e.usedefault,onClick:()=>{e.usedefault||e.onDefaultColumns()},children:[_(H,{name:"columns"}),_("span",{className:"enrol_apply-rview-menu-label",children:t.defaultcolumns})]}),D("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":!e.haswidths,onClick:()=>{e.haswidths&&e.onResetWidths()},children:[_(H,{name:"reset"}),_("span",{className:"enrol_apply-rview-menu-label",children:t.resetwidths})]})]})]}),D("div",{className:"enrol_apply-rview-anchor",children:[z("view",$,t.view,ae),D("div",{className:"enrol_apply-rview-menu",id:o+"view",role:"menu","aria-label":t.view,hidden:c!=="view",onKeyDown:wt,children:[T.map(([m,L,O])=>D("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===m,tabIndex:-1,onClick:()=>{e.onView(m),f(null),N.current.view?.focus()},children:[_(H,{name:m}),_("span",{className:"enrol_apply-rview-menu-label",children:L}),O!==""?_("span",{className:"enrol_apply-rview-menu-hint",children:O}):null,_(H,{name:"tick",className:"enrol_apply-rview-tick"})]},m)),e.tableurl!==""?D(mn,{children:[_("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),D("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:e.tableurl,children:[_(H,{name:"table"}),_("span",{className:"enrol_apply-rview-menu-label",children:t.coretable})]})]}):null]})]}),e.downloads.length>0?D("div",{className:"enrol_apply-rview-anchor",children:[z("download","download",t.download,ae),_("div",{className:"enrol_apply-rview-menu",id:o+"download",role:"menu","aria-label":t.download,hidden:c!=="download",onKeyDown:wt,children:e.downloads.map(m=>D("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:m.href,children:[_(H,{name:"download"}),_("span",{className:"enrol_apply-rview-menu-label",children:m.label})]},m.key))})]}):null]})]}),_("div",{className:"enrol_apply-rview-capsule enrol_apply-rview-searchcap",children:h?D("span",{className:"enrol_apply-rview-search",children:[e.searching&&y?_("span",{className:"enrol_apply-rview-spin","aria-hidden":"true"}):_(H,{name:"search"}),_("input",{ref:s,type:"search","aria-label":t.search,placeholder:t.search,autoComplete:"off",value:e.search,onChange:m=>e.onSearch(m.target.value),onKeyDown:m=>{m.key==="Escape"&&(m.preventDefault(),C(!0))},onBlur:()=>{e.search===""&&C(!1)}}),e.search!==""?_("button",{type:"button",className:"enrol_apply-rview-eraser","aria-label":t.cleartext,"data-enrol-apply-tip":t.cleartext,onMouseDown:m=>m.preventDefault(),onClick:()=>{e.onSearch(""),s.current?.focus()},children:_(H,{name:"eraser"})}):null]}):_("button",{ref:d,type:"button",className:"enrol_apply-rview-iconbtn","aria-label":t.search,"data-enrol-apply-tip":t.search,onClick:()=>{f(null),w(!0)},children:_(H,{name:"search"})})}),_(sn,{labels:t,id:o+"filters",hidden:c!=="filters",facets:e.facets,values:e.values,total:e.total,note:e.panelnote,onChange:e.onFacet,onClear:e.onClearFilters,onShow:()=>{f(null),N.current.filters?.focus()}})]}),D("div",{className:"enrol_apply-rview-chips","data-region":"enrol_apply-chips",hidden:G===0&&!e.aside,children:[n.map(m=>D("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":x(t.removefilter,m.text),onClick:()=>e.onRemoveChip(m.key),children:[_("span",{children:m.text}),_(H,{name:"close"})]},m.key)),Q?D("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":x(t.removefilter,x(t.searchchip,e.search.trim())),onClick:()=>e.onSearch(""),children:[_("span",{children:x(t.searchchip,e.search.trim())}),_(H,{name:"close"})]}):null,G>1?_("button",{type:"button",className:"enrol_apply-rview-clearall",onClick:()=>{e.onClearAll(),w(!1)},children:t.clearall}):null,e.aside??null]})]})};import{jsx as It,jsxs as gr}from"react/jsx-runtime";var $e="all",Te="group",wr=["auto","table","cards"],fr=(e,t)=>{switch(t){case"everything":return e.preseteverything;case"glance":return e.presetglance;case"answers":return e.presetanswers;default:return t}},vr=(e,t)=>{let n={label:e.searchgroups,nomatch:e.nogroupmatch},r=t.facets.map(l=>({name:l.name,title:l.title,options:l.options,groupings:[],find:n})),o=t.fieldfilters.filter(l=>l.control==="select").map(l=>({name:l.name,title:l.title,options:l.options.map(s=>({...s,count:-1})),groupings:[],find:n}));return[...r,...o]},yr=(e,t,n,r)=>{let o=n,l=r,s=t.facets.find(u=>u.name===n),d=t.fieldfilters.find(u=>u.name===n);return s?(o=s.title,l=s.options.find(u=>u.value===r)?.label??r):d?(o=d.title,l=d.options.find(u=>u.value===r)?.label??r):n==="appliedfrom"?o=e.appliedfrom:n==="appliedto"&&(o=e.appliedto),{key:n,text:J(e.filterchip,{title:o,value:l})}},wn=e=>{let{labels:t,rows:n,query:r}=e,o=e.presets.map(d=>({name:d.name,label:fr(t,d.name),active:d.active})),l=Object.fromEntries(Object.entries(r.filters).map(([d,u])=>[d,d===Te&&u===$e?"":u])),s=n===null?[]:n.filters.map(d=>yr(t,n,d.name,d.value));return It(vt,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"pending",label:t.pending},{value:"deferred",label:t.deferred}],scope:r.scope,total:n===null?null:n.total,facets:n===null?[]:vr(t,n),values:l,panelnote:gr("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",It("a",{href:e.tableurl,children:t.morefilters})]}),chips:s,aside:e.reporturl!==""?It("a",{className:"enrol_apply-qview-tolink",href:e.reporturl,children:t.openreport}):null,columns:e.columns,sets:o,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:wr,tableurl:e.tableurl,downloads:[],search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:d=>e.onScope(d),onSearch:e.onSearch,onFacet:e.onFilter,onRemoveChip:d=>e.onFilter(d,""),onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:d=>{let u=e.presets.find(c=>c.name===d);u&&e.onPreset(u)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as hr,jsx as fn}from"react/jsx-runtime";var vn=(e,t)=>t?e.split(`
`).map(n=>n.trim()).filter(n=>n!==""):[e],yt=({parts:e})=>fn(hr,{children:e.map((t,n)=>fn("span",{className:n===0?"enrol_apply-rview-c":"enrol_apply-rview-sub",title:nt(t),dangerouslySetInnerHTML:{__html:t}},n))});import{Fragment as Pr,jsx as ee,jsxs as gt}from"react/jsx-runtime";var br={applicant:320,wait:150,group:180,comment:260,history:130},_r=180,Rr={applicant:200},kr=80,Nr=800,Ft=tt(br,_r,Rr,kr,Nr),Cr={deferred:"enrol_apply-status--warning",key:"enrol_apply-status--info",appliedbefore:"enrol_apply-status--neutral",own:"enrol_apply-status--neutral"},Pe=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",Vt=({html:e})=>ee(yt,{parts:[e]}),Sr=({tag:e})=>{let t=Cr[e.kind]??"enrol_apply-status--neutral",n=e.title!==""&&e.title!==e.text;return gt("span",{className:"enrol_apply-status "+t,title:n?e.title:void 0,children:[e.text,n?ee("span",{className:"visually-hidden",children:" "+e.title}):null]})},Er=({row:e})=>e.applicant.profileurl===""?ee("span",{className:"enrol_apply-qview-name",children:e.applicant.fullname}):ee("a",{className:"enrol_apply-qview-name",href:e.applicant.profileurl,children:e.applicant.fullname}),Mr=({labels:e,row:t})=>ee("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:t.reviewurl,"aria-label":x(e.reviewapplicant,t.applicant.fullname),children:e.review}),xr=({row:e})=>gt(Pr,{children:[ee("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:e.applicant.picture}}),gt("div",{className:"enrol_apply-qview-whotext",children:[ee("span",{className:"enrol_apply-rview-c",children:ee(Er,{row:e})}),e.applicant.identity.map(t=>ee("span",{className:"enrol_apply-rview-sub",title:t.value,children:t.value},t.name)),e.applicant.tags.length>0?ee("span",{className:"enrol_apply-qview-tags",children:e.applicant.tags.map(t=>ee(Sr,{tag:t},t.kind))}):null]})]}),Ht=({labels:e,row:t})=>gt("div",{className:"enrol_apply-qview-who",children:[ee(xr,{row:t}),ee(Mr,{labels:e,row:t})]});import{fetchOne as Ot}from"@moodle/lms/core/ajax";var yn=(e,t)=>Ot({methodname:"enrol_apply_queue_rows",args:{enrolid:e,scope:t.scope,search:t.search,filters:Object.entries(t.filters).map(([n,r])=>({name:n,value:r})),page:t.page,perpage:t.perpage,sort:t.sort,direction:t.direction}}),gn=e=>Ot({methodname:"enrol_apply_queue_summary",args:{enrolid:e}}),hn=(e,t)=>Ot({methodname:"enrol_apply_set_queue_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}});import{jsx as te,jsxs as qe}from"react/jsx-runtime";var bn=(e,t)=>Math.min(100,Math.max(0,Math.round(e*100/t))),ht=({label:e,value:t,sub:n,meter:r,warn:o})=>qe("div",{className:"enrol_apply-rview-tile"+(o?" enrol_apply-rview-tile--warn":""),role:"listitem",children:[te("div",{className:"enrol_apply-rview-tile-label",children:e}),te("div",{className:"enrol_apply-rview-tile-value",children:t}),r!==null?te("span",{className:"enrol_apply-qview-meter"+(o?" enrol_apply-qview-meter--warn":""),"aria-hidden":"true",children:te("span",{style:{width:`${r}%`}})}):null,n!==""?te("div",{className:"enrol_apply-rview-tile-sub",children:n}):null]}),Lr=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.DateTimeFormat(t,{dateStyle:"short"}).format(new Date(e*1e3))}catch{return new Date(e*1e3).toLocaleDateString()}},_n=({labels:e,summary:t,error:n,onRetry:r})=>{if(t===null&&n)return qe("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",te("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:r,children:e.retry})]});if(t===null)return qe("div",{className:"enrol_apply-rview-band","aria-busy":"true",children:[Array.from({length:4},(d,u)=>qe("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[te("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),te("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"})]},u)),te("span",{className:"visually-hidden",role:"status",children:e.loadsummary})]});let o=t.narrowed?e.methodnote:"",l=t.awaiting>0?x(e.oldest,xe(e,t.oldestwait)):"",s=t.late>0?x(e.late,R(t.late)):"";return qe("div",{className:"enrol_apply-rview-band",role:"list","data-region":"enrol_apply-tiles",children:[t.places>0?te(ht,{label:e.placestaken,value:J(e.ofmany,{taken:R(t.placestaken),total:R(t.places)}),sub:o,meter:bn(t.placestaken,t.places),warn:!1}):null,t.applicantlimit>0?te(ht,{label:e.held,value:J(e.ofmany,{taken:R(t.applicants),total:R(t.applicantlimit)}),sub:o,meter:bn(t.applicants,t.applicantlimit),warn:t.applicants*5>=t.applicantlimit*4}):null,te(ht,{label:e.awaiting,value:R(t.awaiting),sub:[l,s].filter(d=>d!=="").join(" \xB7 "),meter:null,warn:t.late>0}),te(ht,{label:e.status,value:t.open?e.open:e.closed,sub:t.open&&t.closes>0?x(e.closes,Lr(t.closes)):"",meter:null,warn:!1})]})};import{Fragment as kn,jsx as X,jsxs as Wt}from"react/jsx-runtime";var Ar=300,Tr=500,Rn="enrol_apply_queue_view",Dr=["applicant","wait","group","comment"],Ir=["applicant","wait","group"],Fr=e=>e.scope!=="all"||e.search!==""||Object.entries(e.filters).some(([t,n])=>n!==""&&!(t===Te&&n===$e)),Vr=(e,t)=>e.filter(n=>!Ir.includes(n.key)&&Pe(t,n.key).trim()!=="").map(n=>({key:n.key,title:n.title,content:X(Vt,{html:Pe(t,n.key)})})),Hr=e=>{let t=j(e).current,{labels:n,enrolid:r}=t,o=j(null),l=st(o),[s,d]=ue(()=>it(Rn)),[u]=ue(()=>nn(t)),c=j(u),[f,h]=ue(u),[w,N]=ue(u.search),[i,C]=ue(null),[M,y]=ue(!0),[Q,G]=ue(!1),[A,ge]=ue(null),[z,$]=ue(!0),[ae,v]=ue(!1),[g,T]=ue({}),I=j(0),m=j(0),L=j(null),O=j(null),F=j(null),U=j(null),me=j(!1),q=j(null),Se=j(null),we=j(null),he=j(()=>{}),re=Bt(p=>{let S=++I.current;L.current||(L.current=Z("enrol_apply/queue:rows")),y(!0),yn(r,p).then(a=>{S===I.current&&(me.current||(me.current=!0,T(Object.fromEntries(a.preference.widths.map(b=>[b.key,b.width])))),a.page!==c.current.page&&(c.current={...c.current,page:a.page},h(c.current),mt(t,c.current,!1)),C(a),G(!1),y(!1))}).catch(()=>{S===I.current&&(G(!0),y(!1))})},[r,t]),ke=Bt(()=>{let p=++m.current;O.current||(O.current=Z("enrol_apply/queue:summary")),$(!0),gn(r).then(S=>{p===m.current&&(ge(S),v(!1),$(!1))}).catch(()=>{p===m.current&&(v(!0),$(!1))})},[r]),K=Bt((p,S)=>{c.current={...c.current,...p},h(c.current),mt(t,c.current,S),re(c.current)},[re,t]);Ke(()=>{!M&&L.current&&(L.current(),L.current=null)},[M,i,Q]),Ke(()=>{!z&&O.current&&(O.current(),O.current=null)},[z,A,ae]),Ke(()=>{mt(t,c.current,!1),re(c.current),ke()},[re,ke,t]),Ke(()=>{let p=S=>{let a=rn(S.state);a!==null&&(c.current=a,h(a),N(a.search),re(a))};return window.addEventListener("popstate",p),()=>window.removeEventListener("popstate",p)},[re]),Ke(()=>()=>{F.current!==null&&window.clearTimeout(F.current),U.current?.(),he.current()},[]);let Ee=p=>{d(p),ct(Rn,p)},le=p=>{N(p),F.current!==null&&(window.clearTimeout(F.current),F.current=null);let S=p.trim(),a=S.length>=t.searchmin?S:"";if(a===c.current.search){U.current?.(),U.current=null;return}if(p===""){U.current?.(),U.current=null,K({search:"",page:0},!1);return}U.current||(U.current=Z("enrol_apply/queue:search")),F.current=window.setTimeout(()=>{F.current=null,K({search:a,page:0},!1),U.current?.(),U.current=null},Ar)},Xe=i?.groupdefault??"",Et=(p,S)=>{let a={...c.current.filters};S!==""?a[p]=S:p===Te&&Xe!==""?a[Te]=$e:delete a[p],K({filters:a,page:0},!0)},Me=()=>Xe!==""?{[Te]:$e}:{},se=(p,S)=>{let a=Z("enrol_apply/queue:preference");hn(r,p).then(()=>{S&&re(c.current)}).catch(()=>{S&&G(!0)}).finally(a)},be=i?.columns??[],fe=be.filter(p=>!p.fixed&&p.active).map(p=>p.key),ve=i?.preference.usedefault??!0,ye=p=>Object.entries(p).map(([S,a])=>({key:S,width:a})),Le=(p,S)=>{let a=S?[...fe,p]:fe.filter(b=>b!==p);i&&C({...i,columns:i.columns.map(b=>b.key===p?{...b,active:S}:b),preference:{...i.preference,usedefault:!1,columns:a}}),se({usedefault:!1,columns:a,widths:ye(g)},!0)},Mt=p=>{let S=new Set(p.columns);i&&C({...i,columns:i.columns.map(a=>a.fixed?a:{...a,active:S.has(a.key)||S.has("answers")&&a.key.startsWith("answer:")}),presets:i.presets.map(a=>({...a,active:a.name===p.name})),preference:{...i.preference,usedefault:!1,columns:p.columns}}),se({usedefault:!1,columns:p.columns,widths:ye(g)},!0)},De=()=>{q.current!==null&&(window.clearTimeout(q.current),q.current=null);let p=we.current;we.current=null,p!==null&&se({usedefault:ve,columns:ve?[]:fe,widths:ye(p)},!1),Se.current?.(),Se.current=null};he.current=De;let xt=(p,S,a=!1)=>{let b={...g};if(S===null?delete b[p]:b[p]=S,T(b),we.current=b,!a){De();return}q.current!==null&&window.clearTimeout(q.current),Se.current||(Se.current=Z("enrol_apply/queue:width")),q.current=window.setTimeout(()=>he.current(),Tr)},Pt=p=>{let S=i!==null&&i.sort===p&&i.direction==="asc"?"desc":"asc";K({sort:p,direction:S,page:0},!0)},Ie=be.filter(p=>p.active),Je=p=>Ie.some(S=>S.key===p),Ze=s==="cards"||s==="auto"&&l,Fe=(i?.rows??[]).map(p=>({...p,id:p.userenrolmentid})),Ve=null;i!==null&&(Ve=Ze?X(lt,{labels:n,rows:Fe,single:l,renderCard:p=>({head:X(Ht,{labels:n,row:p}),lines:Wt(kn,{children:[Je("wait")?X("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--open",dangerouslySetInnerHTML:{__html:Pe(p,"wait")}}):null,Je("group")&&Pe(p,"group")!==""?X("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",dangerouslySetInnerHTML:{__html:Pe(p,"group")}}):null]}),fields:Vr(Ie,p)})}):X(at,{labels:n,columns:Ie,lead:"applicant",rows:Fe,widths:g,sizing:Ft,sort:i.sort,direction:i.direction,renderLead:p=>X(Ht,{labels:n,row:p}),renderCell:(p,S)=>X(Vt,{html:Pe(p,S.key)}),onSort:Pt,onWidth:xt,onWidthCommit:()=>he.current()}));let et=Dr.map(p=>({key:p,title:p==="applicant"?n.applicant:"",sortable:!1,default:!0,active:!0,fixed:p==="applicant"}));return Wt("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-queueview",children:[X(_n,{labels:n,summary:A,error:ae,onRetry:ke}),i?.notingroup?X("p",{className:"enrol_apply-rview-narrowed",children:n.notingroup}):Wt(kn,{children:[i?.narrowed?X("p",{className:"enrol_apply-rview-narrowed",children:n.narrowed}):null,X(wn,{labels:n,narrow:l,query:f,rows:i,columns:be,presets:i?.presets??[],usedefault:ve,haswidths:Object.keys(g).length>0,view:s,search:w,searchmin:t.searchmin,searching:M,tableurl:dt(t,f,!1),reporturl:t.reporturl,onScope:p=>K({scope:p,page:0},!0),onSearch:le,onFilter:Et,onClearFilters:()=>K({filters:Me(),page:0},!0),onClearAll:()=>{N(""),K({filters:Me(),search:"",page:0},!0)},onColumn:Le,onPreset:Mt,onDefaultColumns:()=>{i&&C({...i,preference:{...i.preference,usedefault:!0,columns:[]}}),se({usedefault:!0,columns:[],widths:ye(g)},!0)},onResetWidths:()=>{T({}),se({usedefault:ve,columns:ve?[]:fe,widths:[]},!1)},onView:Ee}),X(pt,{labels:n,loading:M,loaded:i!==null,error:Q,total:i?.total??0,filtered:Fr(f),cards:Ze,narrow:l,skeleton:{columns:et,lead:"applicant",widths:g,sizing:Ft},onRetry:()=>re(c.current),children:Ve}),i!==null&&i.total>0?X(ut,{labels:n,page:f.page,perpage:f.perpage,perpages:t.perpages,total:i.total,shown:i.rows.length,narrow:l,onPage:p=>K({page:p},!0),onPerpage:p=>K({perpage:p,page:0},!0)}):null]})]})},Nn=Hr;import{useCallback as jt,useEffect as St,useRef as ne,useState as de}from"react";import{useState as Ut}from"react";import{Fragment as $t,jsx as k,jsxs as W}from"react/jsx-runtime";var Qt=8,Cn=7,bt=({label:e,value:t,sub:n,warn:r,link:o})=>W("div",{className:"enrol_apply-rview-tile"+(r?" enrol_apply-rview-tile--warn":""),children:[k("div",{className:"enrol_apply-rview-tile-label",children:e}),k("div",{className:"enrol_apply-rview-tile-value",children:t}),k("div",{className:"enrol_apply-rview-tile-sub",children:n}),o??null]}),Sn=({label:e,share:t,figure:n,warn:r})=>W("div",{className:"enrol_apply-rview-hrow",role:"listitem",children:[k("span",{className:"enrol_apply-rview-hrow-label",title:e,children:e}),k("span",{className:"enrol_apply-rview-track","aria-hidden":"true",children:k("span",{className:"enrol_apply-rview-fill"+(r?" enrol_apply-rview-fill--late":""),style:{width:`${Math.round(Math.min(1,Math.max(0,t))*100)}%`}})}),k("span",{className:"enrol_apply-rview-hrow-n",children:n})]}),Or=({compact:e})=>W("div",{"aria-hidden":"true",children:[k("div",{className:"enrol_apply-rview-band",children:Array.from({length:e?3:4},(t,n)=>W("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"}),k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"})]},n))}),e?null:k("div",{className:"enrol_apply-rview-dist",children:[0,1].map(t=>W("div",{className:"enrol_apply-rview-panel enrol_apply-rview-sk",children:[k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"}),k("span",{className:"enrol_apply-rview-skb"}),k("span",{className:"enrol_apply-rview-skb"}),k("span",{className:"enrol_apply-rview-skb"})]},t))})]}),Br=({labels:e,bymethod:t,bygroup:n,methods:r})=>{let[o,l]=Ut(r>1?"method":"group"),[s,d]=Ut(!1),[u,c]=Ut(""),f=o==="method"?t:n,h=Math.max(86400,...f.map(y=>y.median??0)),w=y=>k(Sn,{label:y.name,share:(y.median??0)/h,figure:`${y.median===null?"\u2013":xe(e,y.median)} (${R(y.count)})`,warn:(y.median??0)>Cn*86400},y.id),N=[...f].sort((y,Q)=>Q.count-y.count),i=o==="group"&&f.length>Qt,C=u.trim().toLocaleLowerCase(),M=[...f].filter(y=>C===""||y.name.toLocaleLowerCase().includes(C)).sort((y,Q)=>(Q.median??0)-(y.median??0));return W("div",{className:"enrol_apply-rview-panel",children:[W("div",{className:"enrol_apply-rview-panel-title",children:[k("span",{children:e.medianby}),r>1?k(Ue,{label:e.breakdown,small:!0,value:o,options:[{value:"method",label:e.bymethod},{value:"group",label:e.bygroup}],onChange:y=>l(y)}):k("span",{children:e.bygroup})]}),i?W($t,{children:[k("div",{className:"enrol_apply-rview-note",children:x(e.topgroups,R(Qt))}),k("div",{role:"list",children:N.slice(0,Qt).map(w)}),k("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":s,onClick:()=>d(!s),children:s?e.hide:x(e.allgroups,R(f.length))}),s?W($t,{children:[k("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:e.searchgroup,"aria-label":e.searchgroup,value:u,onChange:y=>c(y.target.value)}),k("div",{className:"enrol_apply-rview-scrolllist",role:"list",children:M.length?M.map(w):k("p",{className:"enrol_apply-rview-note",children:e.nogroupmatch})})]}):null]}):k("div",{role:"list",children:N.map(w)})]})},En=({labels:e,summary:t,loading:n,error:r,compactHint:o,measure:l,methods:s,queueurl:d,pendingstatus:u,onMeasure:c,onRetry:f})=>{let h=W("div",{className:"enrol_apply-rview-bandhead",children:[k("span",{className:"enrol_apply-rview-bandtitle",children:e.turnaround}),k(Ue,{label:e.measure,small:!0,value:l,options:[{value:"first",label:e.measurefirst},{value:"resolution",label:e.measureresolution}],onChange:N=>c(N)})]}),w;if(t===null&&r)w=W("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",k("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:f,children:e.retry})]});else if(t===null)w=W($t,{children:[k(Or,{compact:o}),k("span",{className:"visually-hidden",role:"status",children:e.loadband})]});else{let N=t.measure==="resolution",i=t.median??null,C=t.p90??null,M=t.oldest??null,y=e.nothingopen;M!==null&&(y=x(e.oldest,xe(e,M))+(t.late>0?" \xB7 "+x(e.late,R(t.late)):""));let Q=d===""?null:k("a",{className:"enrol_apply-rview-tile-link",href:N?d:`${d}&status=${u}`,children:e.openqueue}),G=Math.max(1,...t.buckets.map(A=>A.count));w=W("div",{className:"enrol_apply-rview-dim",children:[W("div",{className:"enrol_apply-rview-band",children:[k(bt,{label:N?e.medianresolution:e.medianfirst,value:i===null?"\u2013":xe(e,i),sub:x(e.mediansub,R(t.decided))}),t.compact?null:k(bt,{label:e.p90,value:C===null?"\u2013":xe(e,C),sub:e.p90sub}),k(bt,{label:e.open,value:R(t.open),sub:y,warn:t.late>0,link:Q}),k(bt,{label:e.withoutperson,value:R(t.withoutperson),sub:e.withoutpersonsub})]}),t.compact?null:W("div",{className:"enrol_apply-rview-dist",children:[W("div",{className:"enrol_apply-rview-panel",children:[W("div",{className:"enrol_apply-rview-panel-title",children:[k("span",{children:N?e.distresolution:e.distfirst}),k("span",{className:"enrol_apply-rview-note",children:x(e.decisions,R(t.decided))})]}),k("div",{role:"list",children:t.buckets.map((A,ge)=>{let z=R(A.todays??0),$=J(e.bucket,{from:R(A.fromdays),to:z});return ge===0?$=x(e.bucketfirst,R(A.todays??0)):(A.todays??null)===null&&($=x(e.bucketlast,R(A.fromdays))),k(Sn,{label:$,share:A.count/G,figure:R(A.count),warn:A.fromdays>=Cn},A.fromdays)})})]}),k(Br,{labels:e,bymethod:t.bymethod,bygroup:t.bygroup,methods:s})]})]})}return W("section",{className:"enrol_apply-rview-region enrol_apply-rview-bandregion","aria-label":e.turnaround,"aria-busy":n,"data-region":"enrol_apply-band",children:[n&&t!==null?k("div",{className:"enrol_apply-rview-progress"}):null,h,w]})};var Mn=e=>e.facet!==""&&e.options.length>0,xn=e=>{try{let t=JSON.parse(e.values||"{}");return t!==null&&typeof t=="object"?t:{}}catch{return{}}},Wr=e=>e.reduce((t,n)=>({...t,...xn(n)}),{}),qt=e=>{if(!e.applied)return"";let t=xn(e),n=t[`${e.name}_value`];if(n==null)return"";let r=Number(t[`${e.name}_operator`]??0);if(e.facet==="waited"){let o=Number(n)*Number(t[`${e.name}_unit`]??0)/86400;return r===2&&Number.isFinite(o)?String(o):""}return e.facet==="decider"?r===3?String(n):"":String(n)},Pn=(e,t,n)=>{let r=Kt(e,t);return t.facet==="waited"?(r[`${t.name}_operator`]=n===""?0:2,r[`${t.name}_value`]=n===""?0:Number(n),r[`${t.name}_unit`]=86400):t.facet==="decider"?(r[`${t.name}_operator`]=n===""?0:3,r[`${t.name}_value`]=n):(r[`${t.name}_operator`]=n===""?0:1,r[`${t.name}_value`]=n),r},Kt=(e,t)=>{let n=Wr(e);for(let r of Object.keys(n))r.startsWith(`${t.name}_`)&&delete n[r];return n},Ln=e=>{let t=qt(e);return e.facet===""||t===""?"":e.options.find(n=>n.value===t)?.label??(e.facet==="decider"?t:"")};import{jsx as An,jsxs as Gr}from"react/jsx-runtime";var Ur=["auto","table","cards"],Tn=(e,t)=>t.facet==="waited"?e.waitedmore:t.facet==="decider"?e.decidedby:t.title,Qr=(e,t)=>{switch(t){case"decisions":return e.presetdecisions;case"answers":return e.presetanswers;case"groups":return e.presetgroups;default:return t}},$r=(e,t,n)=>{let r={measure:e};return t!=="all"&&(r.scope=t),n!==""&&(r.search=n),encodeURIComponent(JSON.stringify(r))},qr=(e,t)=>({name:t.name,title:Tn(e,t),options:t.options,groupings:t.groupings,find:t.facet==="decider"?{label:e.searchdeciders,nomatch:e.nodecidermatch}:{label:e.searchgroups,nomatch:e.nogroupmatch}}),Kr=(e,t)=>{let n=Ln(t),r=n!==""?Tn(e,t):t.title;return{key:t.name,text:n===""?r:J(e.filterchip,{title:r,value:n})}},Dn=e=>{let{labels:t,filters:n}=e,r=e.measure==="resolution"?"submission:resolutiontime":"submission:waittime",o=w=>Mn(w)&&(w.facet!=="waited"||w.name===r),l=n.filter(o),s=n.filter(w=>!o(w)),d=w=>n.find(N=>N.name===w),u=e.downloadurl!==""&&e.formats.length>0,c=$r(e.measure,e.scope,e.appliedsearch),f=u?e.formats.map(w=>({key:w.value,label:w.label,href:`${e.downloadurl}&parameters=${c}&download=${encodeURIComponent(w.value)}`})):[],h=e.presets.map(w=>({name:w.name,label:Qr(t,w.name),active:w.active}));return An(vt,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"awaiting",label:t.awaiting},{value:"decided",label:t.decided}],scope:e.scope,total:e.total,facets:l.map(w=>qr(t,w)),values:Object.fromEntries(l.map(w=>[w.name,qt(w)])),panelnote:s.length>0?Gr("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",An("a",{href:e.tableurl,children:t.morefilters})]}):null,chips:n.filter(w=>w.applied).map(w=>Kr(t,w)),columns:e.columns,sets:h,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:Ur,tableurl:e.tableurl,downloads:f,search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:w=>e.onScope(w),onSearch:e.onSearch,onFacet:(w,N)=>{let i=d(w);i&&e.onFilter(i,N)},onRemoveChip:w=>{let N=d(w);N&&e.onRemoveFilter(N)},onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:w=>{let N=e.presets.find(i=>i.name===w);N&&e.onPreset(N)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as eo,jsx as _t}from"react/jsx-runtime";var zr=["outcome","wait","decided","group"],Yr={applicant:260,outcome:180,submitted:132,wait:150,decided:172,group:200,method:170,route:170,comment:240,note:240},jr=180,Xr={applicant:160,submitted:104,decided:104},Jr=80,Zr=800,Rt=tt(Yr,jr,Xr,Jr,Zr),pe=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",kt=(e,t)=>vn(t,zr.includes(e)),Ge=({column:e,html:t})=>_t(yt,{parts:kt(e,t)}),Nt=({row:e,label:t})=>e.reviewurl===""?null:_t("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:e.reviewurl,children:t}),Ct=({row:e,identity:t})=>_t(eo,{children:t.map(n=>{let r=pe(e,n.key);return r===""?null:_t("span",{className:"enrol_apply-rview-sub",title:nt(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});import{Fragment as In,jsx as Y,jsxs as Ye}from"react/jsx-runtime";var to=["applicant","outcome","submitted","wait","decided","group","method"],ze=({row:e,column:t})=>{let n=kt(t,pe(e,t))[0]??"";return n===""?null:Y("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:n}})},no=(e,t,n,r)=>{let o=s=>t.some(d=>d.key===s),l=t.filter(s=>!to.includes(s.key)&&pe(r,s.key).trim()!=="");return{head:Ye(In,{children:[Ye("div",{className:"enrol_apply-rview-card-who",children:[Y("span",{className:"enrol_apply-rview-card-name",dangerouslySetInnerHTML:{__html:pe(r,"applicant")}}),o("outcome")?Y(ze,{row:r,column:"outcome"}):null,Y(Ct,{row:r,identity:n})]}),Y(Nt,{row:r,label:e.review})]}),lines:Ye(In,{children:[Ye("div",{className:"enrol_apply-rview-card-line"+(r.open?" enrol_apply-rview-card-line--open":""),children:[o("wait")?Y(ze,{row:r,column:"wait"}):null,o("wait")&&o("submitted")?Y("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("submitted")?Y(ze,{row:r,column:"submitted"}):null]}),o("decided")?Y("div",{className:"enrol_apply-rview-card-line",children:kt("decided",pe(r,"decided")).map((s,d)=>Y("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:s}},d))}):null,o("group")||o("method")?Ye("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",children:[o("group")?Y(ze,{row:r,column:"group"}):null,o("group")&&o("method")&&pe(r,"group")!==""?Y("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("method")?Y(ze,{row:r,column:"method"}):null]}):null]}),fields:l.map(s=>({key:s.key,title:s.title,content:Y(Ge,{column:s.key,html:pe(r,s.key)})}))}},Fn=({labels:e,columns:t,identity:n,rows:r,single:o})=>Y(lt,{labels:e,rows:r,single:o,renderCard:l=>no(e,t,n,l)});import{Fragment as oo,jsx as Ne,jsxs as Gt}from"react/jsx-runtime";var ro=({row:e,longest:t})=>!e.open&&!e.decidedbyperson?null:Ne("span",{className:"enrol_apply-rview-waitbar"+(e.open?" enrol_apply-rview-waitbar--open":""),"aria-hidden":"true",children:Ne("span",{style:{width:`${Math.min(100,Math.round(e.wait/t*100))}%`}})}),Vn=({labels:e,columns:t,identity:n,rows:r,widths:o,sort:l,direction:s,onSort:d,onWidth:u,onWidthCommit:c})=>{let f=Math.max(86400,...r.filter(h=>h.open||h.decidedbyperson).map(h=>h.wait));return Ne(at,{labels:e,columns:t,lead:"applicant",rows:r,widths:o,sizing:Rt,sort:l,direction:s,renderLead:(h,w)=>Gt("div",{className:"enrol_apply-rview-applicant",children:[Gt("div",{className:"enrol_apply-rview-applicant-text",children:[Ne(Ge,{column:w.key,html:pe(h,w.key)}),Ne(Ct,{row:h,identity:n})]}),Ne(Nt,{row:h,label:e.review})]}),renderCell:(h,w)=>Gt(oo,{children:[Ne(Ge,{column:w.key,html:pe(h,w.key)}),w.key==="wait"?Ne(ro,{row:h,longest:f}):null]}),onSort:d,onWidth:u,onWidthCommit:c})};import{fetchOne as je}from"@moodle/lms/core/ajax";var Hn=(e,t,n)=>je({methodname:"enrol_apply_report_rows",args:{enrolid:e,...t,facets:n}}),On=(e,t,n,r)=>je({methodname:"enrol_apply_report_summary",args:{enrolid:e,measure:t,scope:n,search:r}}),Bn=(e,t)=>je({methodname:"enrol_apply_set_report_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}}),zt=(e,t,n)=>je({methodname:"core_reportbuilder_set_filters",args:{reportid:e,parameters:JSON.stringify({measure:t}),values:JSON.stringify(n)}}),Yt=(e,t)=>je({methodname:"core_reportbuilder_filters_reset",args:{reportid:e,parameters:JSON.stringify({measure:t})}});import{Fragment as co,jsx as Ce,jsxs as Xt}from"react/jsx-runtime";var ao=300,lo=500,Wn="enrol_apply_report_view",so=["applicant","outcome","submitted","wait","decided","group"],io=e=>{let{labels:t,enrolid:n,reportid:r}=e,o=ne(null),l=st(o),[s,d]=de(()=>it(Wn)),u={measure:e.measure,scope:"all",search:"",page:0,perpage:e.perpage,sort:"",direction:"desc"},c=ne(u),[f,h]=de(u),[w,N]=de(""),[i,C]=de(null),[M,y]=de(!0),[Q,G]=de(!1),[A,ge]=de(null),[z,$]=de(!0),[ae,v]=de(!1),[g,T]=de({}),[I,m]=de({}),L=ne(0),O=ne(0),F=ne(null),U=ne(null),me=ne(null),q=ne(null),Se=ne(!1),we=ne(null),he=ne(null),re=ne(null),ke=ne(()=>{}),K=jt((a,b)=>{let E=++L.current;F.current||(F.current=Z("enrol_apply/report:rows")),y(!0),Hn(n,a,b).then(V=>{E===L.current&&(V.counted&&m(Object.fromEntries(V.filters.map(He=>[He.name,He]))),Se.current||(Se.current=!0,T(Object.fromEntries(V.preference.widths.map(He=>[He.key,He.width])))),V.page!==c.current.page&&(c.current={...c.current,page:V.page},h(c.current)),C(V),G(!1),y(!1))}).catch(()=>{E===L.current&&(G(!0),y(!1))})},[n]),Ee=jt(a=>{let b=++O.current;U.current||(U.current=Z("enrol_apply/report:band")),$(!0),On(n,a.measure,a.scope,a.search).then(E=>{b===O.current&&(ge(E),v(!1),$(!1))}).catch(()=>{b===O.current&&(v(!0),$(!1))})},[n]),le=jt((a,b,E)=>{c.current={...c.current,...a},h(c.current),b&&K(c.current,E),E&&Ee(c.current)},[K,Ee]);St(()=>{!M&&F.current&&(F.current(),F.current=null)},[M,i,Q]),St(()=>{!z&&U.current&&(U.current(),U.current=null)},[z,A,ae]),St(()=>{K(c.current,!0),Ee(c.current)},[K,Ee]),St(()=>()=>{me.current!==null&&window.clearTimeout(me.current),q.current?.(),ke.current()},[]);let Xe=a=>{d(a),ct(Wn,a)},Et=a=>{N(a),me.current!==null&&(window.clearTimeout(me.current),me.current=null);let b=a.trim(),E=b.length>=e.searchmin?b:"";if(E===c.current.search){q.current?.(),q.current=null;return}if(a===""){q.current?.(),q.current=null,le({search:"",page:0},!0,!0);return}q.current||(q.current=Z("enrol_apply/report:search")),me.current=window.setTimeout(()=>{me.current=null,le({search:E,page:0},!0,!0),q.current?.(),q.current=null},ao)},Me=a=>{let b=Z("enrol_apply/report:filters");a().then(()=>{le({page:0},!0,!0)}).catch(()=>{G(!0)}).finally(b)},se=(i?.filters??[]).map(a=>I[a.name]?{...a,options:I[a.name].options,groupings:I[a.name].groupings}:a),be=(a,b)=>{let E=Z("enrol_apply/report:preference");Bn(n,a).then(()=>{b&&K(c.current,!1)}).catch(()=>{b&&G(!0)}).finally(E)},fe=i?.columns??[],ve=fe.filter(a=>!a.fixed&&a.active).map(a=>a.key),ye=i?.preference.usedefault??!0,Le=a=>Object.entries(a).map(([b,E])=>({key:b,width:E})),Mt=(a,b)=>{let E=b?[...ve,a]:ve.filter(V=>V!==a);i&&C({...i,columns:i.columns.map(V=>V.key===a?{...V,active:b}:V),preference:{...i.preference,usedefault:!1,columns:E}}),be({usedefault:!1,columns:E,widths:Le(g)},!0)},De=()=>{we.current!==null&&(window.clearTimeout(we.current),we.current=null);let a=re.current;re.current=null,a!==null&&be({usedefault:ye,columns:ye?[]:ve,widths:Le(a)},!1),he.current?.(),he.current=null};ke.current=De;let xt=(a,b,E=!1)=>{let V={...g};if(b===null?delete V[a]:V[a]=b,T(V),re.current=V,!E){De();return}we.current!==null&&window.clearTimeout(we.current),he.current||(he.current=Z("enrol_apply/report:width")),we.current=window.setTimeout(()=>ke.current(),lo)},Pt=a=>{let b=new Set(a.columns);i&&C({...i,columns:i.columns.map(E=>E.fixed?E:{...E,active:b.has(E.key)||b.has("answers")&&E.key.startsWith("answer:")}),presets:i.presets.map(E=>({...E,active:E.name===a.name})),preference:{...i.preference,usedefault:!1,columns:a.columns}}),be({usedefault:!1,columns:a.columns,widths:Le(g)},!0)},Ie=a=>{let E=(i?.sort??"")===a&&i?.direction==="asc"?"desc":"asc";le({sort:a,direction:E,page:0},!0,!1)},Je=a=>{let b=c.current,E=fe.some(V=>V.key==="wait"&&V.active);le({measure:a},b.scope!=="all"||b.sort==="wait"||E,!0)},Ze=se.some(a=>a.applied)||f.search!==""||f.scope!=="all",Fe=fe.filter(a=>a.key.startsWith("identity:")),Ve=fe.filter(a=>a.key==="applicant"||!a.fixed&&a.active),et=s==="cards"||s==="auto"&&l,p=null;i!==null&&(p=et?Ce(Fn,{labels:t,columns:Ve,identity:Fe,rows:i.rows,single:l}):Ce(Vn,{labels:t,columns:Ve,identity:Fe,rows:i.rows,widths:g,sort:i.sort,direction:i.direction,onSort:Ie,onWidth:xt,onWidthCommit:()=>ke.current()}));let S=so.map(a=>({key:a,title:a==="applicant"?t.applicant:"",sortable:!1,default:!0,active:!0,fixed:a==="applicant"}));return Xt("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-reportview",children:[i?.narrowed?Ce("p",{className:"enrol_apply-rview-narrowed",children:t.narrowed}):null,Ce(En,{labels:t,summary:A,loading:z,error:ae,compactHint:i?.narrowed??!1,measure:f.measure,methods:e.methods,queueurl:e.queueurl,pendingstatus:e.pendingstatus,onMeasure:Je,onRetry:()=>Ee(c.current)}),Ce(Dn,{labels:t,narrow:l,scope:f.scope,total:i===null?null:i.total,filters:se,columns:fe.filter(a=>!a.key.startsWith("identity:")||a.active),presets:i?.presets??[],usedefault:ye,haswidths:Object.keys(g).length>0,view:s,measure:f.measure,search:w,appliedsearch:f.search,searchmin:e.searchmin,searching:M,tableurl:e.tableurl,downloadurl:e.downloadurl,formats:e.formats,onScope:a=>le({scope:a,page:0},!0,!0),onSearch:Et,onFilter:(a,b)=>Me(()=>zt(r,c.current.measure,Pn(se,a,b))),onRemoveFilter:a=>Me(()=>zt(r,c.current.measure,Kt(se,a))),onClearFilters:()=>Me(()=>Yt(r,c.current.measure)),onClearAll:()=>{N(""),c.current={...c.current,search:""},se.some(a=>a.applied)?Me(()=>Yt(r,c.current.measure)):le({page:0},!0,!0)},onColumn:Mt,onPreset:Pt,onDefaultColumns:()=>{i&&C({...i,preference:{...i.preference,usedefault:!0,columns:[]}}),be({usedefault:!0,columns:[],widths:Le(g)},!0)},onResetWidths:()=>{T({}),be({usedefault:ye,columns:ye?[]:ve,widths:[]},!1)},onView:Xe}),Ce(pt,{labels:t,loading:M,loaded:i!==null,error:Q,total:i?.total??0,filtered:Ze,cards:et,narrow:l,skeleton:{columns:S,lead:"applicant",widths:g,sizing:Rt},onRetry:()=>K(c.current,!0),children:p}),i!==null&&i.total>0?Ce(ut,{labels:t,page:f.page,perpage:f.perpage,perpages:e.perpages,total:i.total,shown:i.rows.length,narrow:l,onPage:a=>le({page:a},!0,!1),onPerpage:a=>le({perpage:a,page:0},!0,!1)}):null,e.retention!==""?Xt("p",{className:"enrol_apply-rview-retention","data-region":"enrol_apply-retention",children:[e.retention,e.retentionurl!==""?Xt(co,{children:[" ",Ce("a",{href:e.retentionurl,children:t.retentionchange})]}):null]}):null]})},Un=io;import{jsx as Qn}from"react/jsx-runtime";var uo=e=>e.mount===void 0||e.mount==="report"?Qn(Un,{...e}):e.mount==="queue"?Qn(Nn,{...e}):null,bl=uo;export{bl as default};
/**
 * Column widths: a view's policy built from its tables, and the width a column is drawn at.
 *
 * @module     enrol_apply/datareport/sizing
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
 * A view that selects rows passes a selection, whose boxes lead the lead column's heading and cells; a
 * view without one draws none.
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
 * query for the back and forward buttons.
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
 * The approval queue of one method as a data report, read only (stage 3 of
 * docs/design/confirm-queue-as-report-plan.md).
 *
 * manage.php?id=<method>&view=grid renders the shell (enrol_apply\output\queue_view) with configuration
 * and strings; the rows and the tiles come from the plugin's web services, which read the queue's own
 * table (classes/local/queueengine.php), so access, the separate-groups narrowing, the masking of the
 * answers and the filters are the table page's. Two regions load on their own:
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
 * Nothing here decides: no selection, no decisions (stage 4). Each row links to its review page.
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
