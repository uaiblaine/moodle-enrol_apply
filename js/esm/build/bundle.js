import{useCallback as Xt,useEffect as Ke,useRef as ee,useState as ue}from"react";import{useState as hr}from"react";import{useEffect as vr,useRef as yt}from"react";var mt=(e,t,n,r,o)=>({defaultWidth:a=>e[a]??t,minimumWidth:a=>n[a]??r,maximum:o}),we=(e,t,n)=>Math.min(e.maximum,Math.max(e.minimumWidth(n),t[n]??e.defaultWidth(n)));var N=(e,t)=>(e||"").split("{$a}").join(t),ae=(e,t)=>Object.entries(t).reduce((n,[r,o])=>n.split(`{$a->${r}}`).join(o),e||""),mn=(e,t,n,r)=>N(n===1&&e[`${t}_one`]?e[`${t}_one`]:e[t],r),b=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(t).format(e)}catch{return String(e)}},Ve=(e,t)=>{let n=Math.max(0,t);if(n<3600)return N(e.waitminutes,b(Math.max(1,Math.round(n/60))));if(n<86400)return N(e.waithours,b(Math.round(n/3600)));let r=Math.floor(n/86400),o=Math.round((n-r*86400)/3600);return o===24&&(r++,o=0),o>0&&r<10?ae(e.waitdayshours,{days:b(r),hours:b(o)}):N(e.waitdays,b(r))},wt=e=>{let t=document.createElement("template");return t.innerHTML=e,(t.content.textContent||"").replace(/\s+/g," ").trim()};import{jsx as L,jsxs as xe}from"react/jsx-runtime";var vt=16,yr={ascending:"\u25B2",descending:"\u25BC",none:""},fr=(e,t)=>e?t==="asc"?"ascending":"descending":"none",gr=({selection:e,rows:t})=>{let n=yt(null),r=t.filter(l=>!e.locked.has(l.id)),o=r.filter(l=>e.selected.has(l.id)).length,a=r.length>0&&o===r.length;return vr(()=>{n.current&&(n.current.indeterminate=o>0&&!a)},[o,a]),xe("label",{className:"enrol_apply-rview-pickwrap",children:[L("input",{ref:n,type:"checkbox",className:"enrol_apply-rview-pick",checked:a,disabled:r.length===0,onChange:l=>e.onSelectPage(l.target.checked)}),L("span",{className:"visually-hidden",children:e.pagelabel})]})},Wt=({selection:e,row:t})=>e.locked.has(t.id)?L("span",{className:"enrol_apply-rview-pickwrap enrol_apply-rview-pick--none"}):xe("label",{className:"enrol_apply-rview-pickwrap",children:[L("input",{type:"checkbox",className:"enrol_apply-rview-pick",checked:e.selected.has(t.id),onChange:n=>e.onSelect(t.id,n.target.checked)}),L("span",{className:"visually-hidden",children:e.label(t)})]}),ft=({labels:e,columns:t,lead:n,rows:r,widths:o,sizing:a,sort:l,direction:m,selection:d,renderLead:u,renderCell:y,onSort:h,onWidth:v,onWidthCommit:S})=>{let i=yt(null),M=yt(null),x=yt(null),f=t.reduce((s,g)=>s+we(a,o,g.key),0),$=s=>{s.currentTarget.classList.toggle("is-scrolled",s.currentTarget.scrollLeft>0)},G=(s,g)=>{let A=M.current?.querySelector(`col[data-col="${CSS.escape(s)}"]`);!A||!M.current||(A.style.width=`${g}px`,M.current.style.width=`${f-we(a,o,s)+g}px`)},T=(s,g)=>Math.round(Math.min(a.maximum,Math.max(a.minimumWidth(s),g))),X=(s,g)=>{if(s.button!==0)return;s.preventDefault(),s.currentTarget.setPointerCapture(s.pointerId),s.currentTarget.classList.add("is-active");let A=we(a,o,g);x.current={key:g,x:s.clientX,width:A,now:A}},z=s=>{let g=x.current;if(!g)return;let A=document.documentElement.dir==="rtl",H=(s.clientX-g.x)*(A?-1:1),w=T(g.key,g.width+H);w!==g.now&&(G(g.key,w),g.now=w)},Q=s=>{let g=x.current;x.current=null,s.currentTarget.classList.remove("is-active"),g&&g.now!==g.width&&v(g.key,g.now)},ne=(s,g)=>{let A=document.documentElement.dir==="rtl",H=0;if(s.key==="ArrowRight")H=A?-vt:vt;else if(s.key==="ArrowLeft")H=A?vt:-vt;else if(s.key==="Home"){s.preventDefault(),v(g,null,!0);return}if(H===0)return;s.preventDefault();let w=T(g,we(a,o,g)+H);w!==we(a,o,g)&&v(g,w,!0)};return L("div",{className:"enrol_apply-rview-gridframe",ref:i,tabIndex:0,role:"region","aria-label":e.grid,onScroll:$,children:xe("table",{className:"enrol_apply-rview-grid",ref:M,style:{width:`${f}px`},children:[L("caption",{className:"visually-hidden",children:e.caption}),L("colgroup",{children:t.map(s=>L("col",{"data-col":s.key,style:{width:`${we(a,o,s.key)}px`}},s.key))}),L("thead",{children:L("tr",{children:t.map(s=>{let g=fr(l===s.key,m),A=L("span",{className:"enrol_apply-rview-th-title",children:s.title}),H=s.sortable?xe("button",{type:"button",className:"enrol_apply-rview-sortbtn",onClick:()=>h(s.key),children:[A,L("span",{className:"enrol_apply-rview-arrow","aria-hidden":"true",children:yr[g]})]}):L("span",{className:"enrol_apply-rview-plain",children:A});return xe("th",{scope:"col",className:s.key===n?"enrol_apply-rview-fixed":void 0,"aria-sort":s.sortable?g:void 0,title:s.title,"data-column":s.key,children:[d&&s.key===n?xe("div",{className:"enrol_apply-rview-lead",children:[L(gr,{selection:d,rows:r}),H]}):H,L("span",{className:"enrol_apply-rview-resize",role:"separator","aria-orientation":"vertical","aria-label":N(e.resize,s.title),"aria-valuemin":a.minimumWidth(s.key),"aria-valuemax":a.maximum,"aria-valuenow":we(a,o,s.key),tabIndex:0,onPointerDown:w=>X(w,s.key),onPointerMove:z,onPointerUp:Q,onPointerCancel:Q,onDoubleClick:()=>{we(a,o,s.key)!==a.defaultWidth(s.key)&&v(s.key,null)},onKeyDown:w=>ne(w,s.key),onBlur:S})]},s.key)})})}),L("tbody",{children:r.map(s=>L("tr",{"data-record":s.id,className:d?.selected.has(s.id)?"enrol_apply-rview-row--selected":void 0,children:t.map(g=>g.key===n?L("td",{className:"enrol_apply-rview-fixed",children:d?xe("div",{className:"enrol_apply-rview-lead",children:[L(Wt,{selection:d,row:s}),u(s,g)]}):u(s,g)},g.key):L("td",{children:y(s,g)},g.key))},s.id))})]})})},wn=({columns:e,lead:t,widths:n,sizing:r})=>{let o=e.reduce((l,m)=>l+we(r,n,m.key),0),a=["enrol_apply-rview-skb--w80","enrol_apply-rview-skb--w60","enrol_apply-rview-skb--w40"];return L("div",{className:"enrol_apply-rview-gridframe","aria-hidden":"true",children:xe("table",{className:"enrol_apply-rview-grid",style:{width:`${o}px`},children:[L("colgroup",{children:e.map(l=>L("col",{style:{width:`${we(r,n,l.key)}px`}},l.key))}),L("thead",{children:L("tr",{children:e.map(l=>L("th",{className:l.key===t?"enrol_apply-rview-fixed":void 0,children:L("span",{className:"enrol_apply-rview-plain",children:L("span",{className:"enrol_apply-rview-th-title",children:l.title})})},l.key))})}),L("tbody",{children:Array.from({length:8},(l,m)=>L("tr",{className:"enrol_apply-rview-sk",children:e.map((d,u)=>L("td",{className:d.key===t?"enrol_apply-rview-fixed":void 0,children:L("span",{className:"enrol_apply-rview-skb "+a[(m+u)%3]})},d.key))},m))})]})})};import{jsx as de,jsxs as Xe}from"react/jsx-runtime";var br=({labels:e,row:t,single:n,selection:r,renderCard:o})=>{let[a,l]=hr(!1),m=o(t),d=!n||a,u=r?.selected.has(t.id)??!1;return Xe("article",{className:"enrol_apply-rview-card"+(u?" enrol_apply-rview-card--selected":""),role:"listitem","data-record":t.id,children:[Xe("div",{className:"enrol_apply-rview-card-l1",children:[r?de(Wt,{selection:r,row:t}):null,m.head]}),m.lines,m.fields.length>0?Xe("div",{className:"enrol_apply-rview-card-more",children:[n?de("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":a,onClick:()=>l(!a),children:a?e.less:e.more}):null,d?de("dl",{className:"enrol_apply-rview-card-list",children:m.fields.map(y=>Xe("div",{className:"enrol_apply-rview-card-item",children:[de("dt",{children:y.title}),de("dd",{children:y.content})]},y.key))}):null]}):null]})},gt=({labels:e,rows:t,single:n,selection:r,renderCard:o})=>de("div",{className:"enrol_apply-rview-cards"+(n?" enrol_apply-rview-cards--single":""),role:"list",children:t.map(a=>de(br,{labels:e,row:a,single:n,selection:r,renderCard:o},a.id))}),vn=({single:e})=>de("div",{className:"enrol_apply-rview-cards"+(e?" enrol_apply-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:e?4:6},(t,n)=>Xe("div",{className:"enrol_apply-rview-card enrol_apply-rview-sk",children:[de("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),de("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"}),de("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),de("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"})]},n))});import{useEffect as _r,useState as Rr}from"react";var Nr=576,ht=e=>{let[t,n]=Rr(!1);return _r(()=>{let r=e.current;if(!r||typeof ResizeObserver>"u")return;let o=new ResizeObserver(a=>{let l=a[0]?.contentRect.width??r.clientWidth;n(l>0&&l<Nr)});return o.observe(r),()=>o.disconnect()},[e]),t},bt=e=>{try{let t=window.localStorage.getItem(e);return t==="table"||t==="cards"?t:"auto"}catch{return"auto"}},_t=(e,t)=>{try{window.localStorage.setItem(e,t)}catch{}};import{useId as kr}from"react";import{jsx as ke,jsxs as $t}from"react/jsx-runtime";var Sr=(e,t)=>[...new Set([1,e-1,e,e+1,t])].filter(n=>n>=1&&n<=t).sort((n,r)=>n-r),Rt=({labels:e,page:t,perpage:n,perpages:r,total:o,shown:a,narrow:l,onPage:m,onPerpage:d})=>{let u=kr(),y=Math.max(1,Math.ceil(o/n)),h=t+1,v=o>0?t*n+1:0,S=o>0?v+a-1:0,i=[];if(l)i=[ke("span",{className:"enrol_apply-rview-pager-gap",children:ae(e.pageof,{page:b(h),pages:b(y)})},"of")];else{let M=Sr(h,y);M.forEach((x,f)=>{f>0&&x-M[f-1]>1&&i.push(ke("span",{className:"enrol_apply-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${x}`)),i.push(ke("button",{type:"button","aria-current":x===h?"page":void 0,onClick:()=>m(x-1),children:b(x)},x))})}return $t("div",{className:"enrol_apply-rview-foot",children:[ke("span",{className:"enrol_apply-rview-num","data-region":"enrol_apply-range",children:ae(e.range,{from:b(v),to:b(S),total:b(o)})}),y>1?$t("nav",{className:"enrol_apply-rview-pager","aria-label":e.pages,children:[ke("button",{type:"button",disabled:h===1,onClick:()=>m(t-1),children:e.previous}),i,ke("button",{type:"button",disabled:h===y,onClick:()=>m(t+1),children:e.next})]}):null,$t("span",{className:"enrol_apply-rview-perpage",children:[ke("label",{htmlFor:u,children:e.perpage}),ke("select",{id:u,className:"form-select form-select-sm",value:n,onChange:M=>d(Number(M.target.value)),children:r.map(M=>ke("option",{value:M,children:b(M)},M))})]})]})};var se=e=>{let t=window.M?.util;t?.js_pending(e);let n=!0;return()=>{n&&(n=!1,t?.js_complete(e))}};import{Fragment as yn,jsx as Se,jsxs as Qe}from"react/jsx-runtime";var Cr=({labels:e,loaded:t,error:n,total:r,filtered:o,cards:a,narrow:l,skeleton:m,children:d,onRetry:u})=>{let y=Qe("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",Se("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:u,children:e.retry})]});return!t&&n?y:t?r===0?Qe("div",{className:"enrol_apply-rview-empty",children:[Se("p",{className:"enrol_apply-rview-empty-title",children:o?e.nomatch:e.empty}),o?Se("p",{className:"enrol_apply-rview-empty-text",children:e.nomatchhelp}):null]}):Qe("div",{className:"enrol_apply-rview-dim",children:[n?y:null,d,a?null:Se("p",{className:"enrol_apply-rview-hint",children:e.scrollhint})]}):Qe(yn,{children:[a?Se(vn,{single:l}):Se(wn,{...m}),Se("span",{className:"visually-hidden",role:"status",children:e.loading})]})},Nt=e=>Qe(yn,{children:[Qe("section",{className:"enrol_apply-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"enrol_apply-rows",children:[e.loading&&e.loaded?Se("div",{className:"enrol_apply-rview-progress"}):null,Se(Cr,{...e})]}),e.dock??null]});var Er="table",Ut="enrolApplyQueue",Mr=(e,t,n)=>{let r=[];return t.scope!=="all"&&r.push(["status",String(t.scope==="pending"?e.statuses.pending:e.statuses.deferred)]),t.search!==""&&r.push(["search",t.search]),Object.keys(t.filters).sort().forEach(o=>{t.filters[o]!==""&&r.push([o,t.filters[o]])}),n?(t.sort!==""&&(t.sort!=="wait"||t.direction!=="asc")&&r.push(["sort",t.sort],["dir",t.direction]),t.page>0&&r.push(["page",String(t.page)]),t.perpage!==e.perpage&&r.push(["perpage",String(t.perpage)])):r.push(["view",Er]),r},Je=(e,t,n)=>{let r=new URL(e.pageurl,window.location.href);return Mr(e,t,n).forEach(([o,a])=>r.searchParams.set(o,a)),r.toString()},fn=e=>({scope:e.initial.scope,search:e.initial.search,filters:Object.fromEntries(e.initial.filters.map(t=>[t.name,t.value])),sort:e.initial.sort,direction:e.initial.direction,page:e.initial.page,perpage:e.initial.perpage}),kt=(e,t,n)=>{let r=Je(e,t,!0),o={[Ut]:t};try{n&&r!==window.location.href?window.history.pushState(o,"",r):window.history.replaceState(o,"",r)}catch{}document.querySelectorAll('[data-enrol-apply-queue="tablelink"]').forEach(a=>{a.href=Je(e,t,!1)})},gn=e=>e===null||typeof e!="object"||!(Ut in e)?null:e[Ut];import{useEffect as Ct,useId as Hr,useRef as tt,useState as Cn}from"react";import{useId as Pr,useState as bn}from"react";import{jsx as hn}from"react/jsx-runtime";var xr={filter:"M2 3h12l-4.5 5.5V13l-3 1.5V8.5L2 3z",columns:"M2 3h12v10H2zM6.5 3v10M10 3v10",table:"M2 3h12v10H2zM2 6.5h12M2 10h12M6 6.5V13",cards:"M2 3h5v4H2zM9 3h5v4H9zM2 9h5v4H2zM9 9h5v4H9z",auto:"M2 3h7v10H2zM11 3h3v4h-3zM11 9h3v4h-3z",download:"M8 2v8M4.5 7 8 10.5 11.5 7M3 13h10",search:"M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM10.5 10.5 14 14",close:"M4 4l8 8M12 4l-8 8",eraser:"M9.5 2 14 6.5 7.5 13 3 8.5zM5.6 5.9l4.5 4.5M7.5 13H14",tick:"M3 8.5 6.5 12 13 4.5",chevron:"M4 6l4 4 4-4",reset:"M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5",queue:"M3 4h10M3 8h10M3 12h6"},W=({name:e,className:t})=>hn("svg",{className:"enrol_apply-rview-icon"+(t?" "+t:""),viewBox:"0 0 16 16",width:"16",height:"16",fill:"none",stroke:"currentColor",strokeWidth:"1.5",strokeLinecap:"round",strokeLinejoin:"round","aria-hidden":"true",focusable:"false",children:hn("path",{d:xr[e]??""})});import{Fragment as Ir,jsx as q,jsxs as ve}from"react/jsx-runtime";var Lr=8,Qt=50,Dr=(e,t)=>t===null?e.show:N(e.showcount,b(t)),_n=({option:e})=>e.count>=0?q("small",{className:"enrol_apply-rview-optcount",children:b(e.count)}):null,Ar=(e,t)=>e.options.filter(n=>n.count!==0||n.value===t),Tr=({labels:e,facet:t,shown:n,value:r,headid:o,onChange:a})=>{let[l,m]=bn(""),[d,u]=bn(""),y=l.trim().toLocaleLowerCase(),h=t.groupings.length>1?t.groupings:[],v=h.find(f=>String(f.id)===d)?.groups.map(String)??null,S=n.filter(f=>f.value===r),i=n.filter(f=>f.value!==r&&(y===""||f.label.toLocaleLowerCase().includes(y))&&(v===null||v.includes(f.value))),M=N(t.find.label,b(t.options.length)),x=h.length>0?e.moregroupsgrouping:e.moregroups;return ve(Ir,{children:[ve("div",{className:"enrol_apply-rview-find",children:[q("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:M,"aria-label":M,value:l,onChange:f=>m(f.target.value)}),h.length>0?ve("select",{className:"form-select form-select-sm enrol_apply-rview-grouping","aria-label":e.grouping,value:d,onChange:f=>u(f.target.value),children:[q("option",{value:"",children:e.allgroupings}),h.map(f=>q("option",{value:String(f.id),children:f.name},f.id))]}):null]}),ve("div",{className:"enrol_apply-rview-optlist",role:"group","aria-labelledby":o,children:[[...S,...i.slice(0,Qt)].map(f=>ve("button",{type:"button",className:"enrol_apply-rview-optrow","aria-pressed":f.value===r,onClick:()=>a(t.name,f.value===r?"":f.value),children:[q("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:q(W,{name:"tick"})}),q("span",{className:"enrol_apply-rview-optrow-label",children:f.label})," ",q(_n,{option:f})]},f.value)),i.length>Qt?q("p",{className:"enrol_apply-rview-note",children:N(x,b(i.length-Qt))}):null,S.length===0&&i.length===0?q("p",{className:"enrol_apply-rview-note",children:t.find.nomatch}):null]})]})},Fr=({labels:e,facet:t,value:n,onChange:r})=>{let o=Pr(),a=Ar(t,n),l=[{value:"",label:e.any,count:-1},...a];if(t.options.length>Lr)return ve("div",{className:"enrol_apply-rview-fgroup",children:[q("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),q(Tr,{labels:e,facet:t,shown:a,value:n,headid:o,onChange:r})]});let m=d=>{let u=l.findIndex(h=>h.value===n),y=-1;d.key==="ArrowRight"||d.key==="ArrowDown"?y=(u+1)%l.length:d.key==="ArrowLeft"||d.key==="ArrowUp"?y=(u-1+l.length)%l.length:d.key==="Home"?y=0:d.key==="End"&&(y=l.length-1),!(y<0)&&(d.preventDefault(),r(t.name,l[y].value),d.currentTarget.querySelectorAll('[role="radio"]')[y]?.focus())};return ve("div",{className:"enrol_apply-rview-fgroup",children:[q("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),q("div",{className:"enrol_apply-rview-fpanel-opts",role:"radiogroup","aria-labelledby":o,onKeyDown:m,children:l.map(d=>ve("button",{type:"button",className:"enrol_apply-rview-fpanel-opt",role:"radio","aria-checked":d.value===n,tabIndex:d.value===n?0:-1,onClick:()=>r(t.name,d.value),children:[d.label," ",q(_n,{option:d})]},d.value))})]})},Rn=({labels:e,id:t,hidden:n,facets:r,values:o,total:a,note:l,onChange:m,onClear:d,onShow:u})=>ve("div",{className:"enrol_apply-rview-fpanel",id:t,role:"dialog","aria-label":e.filters,hidden:n,children:[ve("div",{className:"enrol_apply-rview-fpanel-body",children:[r.map(y=>q(Fr,{labels:e,facet:y,value:o[y.name]??"",onChange:m},y.name)),l??null]}),ve("div",{className:"enrol_apply-rview-fpanel-foot",children:[q("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:d,children:e.clearfilters}),q("button",{type:"button",className:"btn btn-sm btn-primary",onClick:u,children:Dr(e,a)})]})]});import{Fragment as Vr,jsx as Ze,jsxs as Nn}from"react/jsx-runtime";var St=e=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(e.key))return;let t=Array.from(e.currentTarget.querySelectorAll('[role^="menuitem"]'));if(t.length===0)return;e.preventDefault();let n=t.indexOf(document.activeElement),r=0;e.key==="End"?r=t.length-1:e.key==="ArrowDown"?r=(n+1)%t.length:e.key==="ArrowUp"&&(r=(n-1+t.length)%t.length),t[r].focus()},kn=({heading:e,sets:t,onSet:n})=>t.length===0?null:Nn(Vr,{children:[Ze("div",{className:"enrol_apply-rview-menu-head",role:"presentation",children:e}),t.map(r=>Nn("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":r.active,tabIndex:-1,"data-preset":r.name,onClick:()=>{r.active||n(r.name)},children:[Ze("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:Ze(W,{name:"tick"})}),Ze("span",{className:"enrol_apply-rview-menu-label",children:r.label})]},r.name)),Ze("div",{className:"enrol_apply-rview-menu-sep",role:"separator"})]});import{jsx as Sn}from"react/jsx-runtime";var et=({label:e,value:t,options:n,small:r,onChange:o})=>{let a=l=>{let m=n.findIndex(y=>y.value===t),d=-1;if(l.key==="ArrowRight"||l.key==="ArrowDown"?d=(m+1)%n.length:l.key==="ArrowLeft"||l.key==="ArrowUp"?d=(m-1+n.length)%n.length:l.key==="Home"?d=0:l.key==="End"&&(d=n.length-1),d<0)return;l.preventDefault(),o(n[d].value),l.currentTarget.querySelectorAll('[role="radio"]')[d]?.focus()};return Sn("div",{className:"enrol_apply-rview-seg"+(r?" enrol_apply-rview-seg--small":""),role:"radiogroup","aria-label":e,onKeyDown:a,children:n.map(l=>Sn("button",{type:"button",className:"enrol_apply-rview-seg-cell",role:"radio","aria-checked":l.value===t,tabIndex:l.value===t?0:-1,onClick:()=>o(l.value),children:l.label},l.value))})};import{Fragment as En,jsx as _,jsxs as V}from"react/jsx-runtime";var Et=e=>{let{labels:t,chips:n,columns:r}=e,o=Hr(),a=tt(null),l=tt(null),m=tt(null),d=tt(!1),[u,y]=Cn(null),[h,v]=Cn(e.search!==""),S=tt({});Ct(()=>{if(u===null)return;let w=F=>{a.current&&!a.current.contains(F.target)&&y(null)};return document.addEventListener("mousedown",w),()=>document.removeEventListener("mousedown",w)},[u]),Ct(()=>{if(u===null||u==="filters")return;let w=a.current?.querySelector(`#${CSS.escape(o+u)}`);(w?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])')??w?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])'))?.focus()},[u,o]),Ct(()=>{h?l.current?.focus():d.current&&(d.current=!1,m.current?.focus())},[h]),Ct(()=>{let w=F=>{if(F.key!=="/"||F.ctrlKey||F.metaKey||F.altKey||F.defaultPrevented)return;let U=document.activeElement;if(U&&(U.isContentEditable||U.closest("input, textarea, select")))return;let B=a.current?.getBoundingClientRect();!B||B.width===0||B.bottom<=0||B.top>=window.innerHeight||(F.preventDefault(),h?l.current?.focus():(y(null),v(!0)))};return document.addEventListener("keydown",w),()=>document.removeEventListener("keydown",w)},[h]);let i=w=>y(u===w?null:w),M=w=>{d.current=w,v(!1)},x=w=>{if(!(w.key!=="Escape"||w.defaultPrevented))if(u!==null){w.preventDefault();let F=S.current[u];y(null),F?.focus()}else h&&(w.preventDefault(),M(!0))},f=e.search.trim().length>=e.searchmin,$=f&&!h,G=n.length+($?1:0),T=e.total===null?"\u2026":mn(t,"count",e.total,b(e.total)),X=e.total!==null&&e.narrow?V(En,{children:[_("span",{"aria-hidden":"true",children:b(e.total)}),_("span",{className:"visually-hidden",children:T})]}):T,z=(w,F,U,B)=>V("button",{type:"button",className:"enrol_apply-rview-iconbtn","aria-label":U,"data-enrol-apply-tip":U,"aria-haspopup":w==="filters"?"dialog":"menu","aria-expanded":u===w,"aria-controls":o+w,ref:Y=>{S.current[w??""]=Y},onClick:()=>i(w),children:[_(W,{name:F}),B]}),Q={auto:"auto",table:"table",cards:"cards"}[e.view],ne=_(W,{name:"chevron",className:"enrol_apply-rview-chevron"}),s=n.length>0?N(t.filterson,b(n.length)):t.filters,g=n.length>0?_("span",{className:"enrol_apply-rview-badge","aria-hidden":"true",children:b(n.length)}):null,A=[["auto",t.viewauto,t.viewautohint],["table",t.table,""],["cards",t.cards,""]].filter(([w])=>e.views.includes(w)),H="enrol_apply-rview-bar"+(e.narrow?" enrol_apply-rview-bar--narrow":"")+(h?" enrol_apply-rview-bar--searching":"");return V("div",{className:H,"data-region":"enrol_apply-bar",ref:a,onKeyDown:x,children:[_(et,{label:t.scope,value:e.scope,options:e.scopes,onChange:e.onScope}),_("span",{className:"enrol_apply-rview-bar-spacer"}),V("div",{className:"enrol_apply-rview-bar-controls",children:[V("div",{className:"enrol_apply-rview-bar-cluster",children:[_("span",{className:"enrol_apply-rview-bar-count","data-region":"enrol_apply-total","aria-live":"polite",children:X}),V("div",{className:"enrol_apply-rview-capsule",children:[z("filters","filter",s,g),V("div",{className:"enrol_apply-rview-anchor",children:[z("columns","columns",t.columns,ne),V("div",{className:"enrol_apply-rview-menu",id:o+"columns",role:"menu","aria-label":t.columns,hidden:u!=="columns",onKeyDown:St,children:[_(kn,{heading:t.presets,sets:e.sets,onSet:e.onSet}),r.map(w=>w.fixed?V("div",{className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":"true","aria-disabled":"true",tabIndex:-1,children:[_("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:_(W,{name:"tick"})}),_("span",{className:"enrol_apply-rview-menu-label",children:w.title}),_("span",{className:"enrol_apply-rview-menu-hint",children:t.always})]},w.key):V("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":w.active,tabIndex:-1,onClick:()=>e.onColumn(w.key,!w.active),children:[_("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:_(W,{name:"tick"})}),_("span",{className:"enrol_apply-rview-menu-label",children:w.title})]},w.key)),_("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),V("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":e.usedefault,onClick:()=>{e.usedefault||e.onDefaultColumns()},children:[_(W,{name:"columns"}),_("span",{className:"enrol_apply-rview-menu-label",children:t.defaultcolumns})]}),V("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":!e.haswidths,onClick:()=>{e.haswidths&&e.onResetWidths()},children:[_(W,{name:"reset"}),_("span",{className:"enrol_apply-rview-menu-label",children:t.resetwidths})]})]})]}),V("div",{className:"enrol_apply-rview-anchor",children:[z("view",Q,t.view,ne),V("div",{className:"enrol_apply-rview-menu",id:o+"view",role:"menu","aria-label":t.view,hidden:u!=="view",onKeyDown:St,children:[A.map(([w,F,U])=>V("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===w,tabIndex:-1,onClick:()=>{e.onView(w),y(null),S.current.view?.focus()},children:[_(W,{name:w}),_("span",{className:"enrol_apply-rview-menu-label",children:F}),U!==""?_("span",{className:"enrol_apply-rview-menu-hint",children:U}):null,_(W,{name:"tick",className:"enrol_apply-rview-tick"})]},w)),e.tableurl!==""?V(En,{children:[_("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),V("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:e.tableurl,children:[_(W,{name:"table"}),_("span",{className:"enrol_apply-rview-menu-label",children:t.coretable})]})]}):null]})]}),e.downloads.length>0?V("div",{className:"enrol_apply-rview-anchor",children:[z("download","download",t.download,ne),_("div",{className:"enrol_apply-rview-menu",id:o+"download",role:"menu","aria-label":t.download,hidden:u!=="download",onKeyDown:St,children:e.downloads.map(w=>V("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:w.href,children:[_(W,{name:"download"}),_("span",{className:"enrol_apply-rview-menu-label",children:w.label})]},w.key))})]}):null]})]}),_("div",{className:"enrol_apply-rview-capsule enrol_apply-rview-searchcap",children:h?V("span",{className:"enrol_apply-rview-search",children:[e.searching&&f?_("span",{className:"enrol_apply-rview-spin","aria-hidden":"true"}):_(W,{name:"search"}),_("input",{ref:l,type:"search","aria-label":t.search,placeholder:t.search,autoComplete:"off",value:e.search,onChange:w=>e.onSearch(w.target.value),onKeyDown:w=>{w.key==="Escape"&&(w.preventDefault(),M(!0))},onBlur:()=>{e.search===""&&M(!1)}}),e.search!==""?_("button",{type:"button",className:"enrol_apply-rview-eraser","aria-label":t.cleartext,"data-enrol-apply-tip":t.cleartext,onMouseDown:w=>w.preventDefault(),onClick:()=>{e.onSearch(""),l.current?.focus()},children:_(W,{name:"eraser"})}):null]}):_("button",{ref:m,type:"button",className:"enrol_apply-rview-iconbtn","aria-label":t.search,"data-enrol-apply-tip":t.search,onClick:()=>{y(null),v(!0)},children:_(W,{name:"search"})})}),_(Rn,{labels:t,id:o+"filters",hidden:u!=="filters",facets:e.facets,values:e.values,total:e.total,note:e.panelnote,onChange:e.onFacet,onClear:e.onClearFilters,onShow:()=>{y(null),S.current.filters?.focus()}})]}),V("div",{className:"enrol_apply-rview-chips","data-region":"enrol_apply-chips",hidden:G===0&&!e.aside,children:[n.map(w=>V("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":N(t.removefilter,w.text),onClick:()=>e.onRemoveChip(w.key),children:[_("span",{children:w.text}),_(W,{name:"close"})]},w.key)),$?V("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":N(t.removefilter,N(t.searchchip,e.search.trim())),onClick:()=>e.onSearch(""),children:[_("span",{children:N(t.searchchip,e.search.trim())}),_(W,{name:"close"})]}):null,G>1?_("button",{type:"button",className:"enrol_apply-rview-clearall",onClick:()=>{e.onClearAll(),v(!1)},children:t.clearall}):null,e.aside??null]})]})};import{jsx as qt,jsxs as Ur}from"react/jsx-runtime";var nt="all",qe="group",Or=["auto","table","cards"],Br=(e,t)=>{switch(t){case"everything":return e.preseteverything;case"glance":return e.presetglance;case"answers":return e.presetanswers;default:return t}},Wr=(e,t)=>{let n={label:e.searchgroups,nomatch:e.nogroupmatch},r=t.facets.map(a=>({name:a.name,title:a.title,options:a.options,groupings:[],find:n})),o=t.fieldfilters.filter(a=>a.control==="select").map(a=>({name:a.name,title:a.title,options:a.options.map(l=>({...l,count:-1})),groupings:[],find:n}));return[...r,...o]},$r=(e,t,n,r)=>{let o=n,a=r,l=t.facets.find(d=>d.name===n),m=t.fieldfilters.find(d=>d.name===n);return l?(o=l.title,a=l.options.find(d=>d.value===r)?.label??r):m?(o=m.title,a=m.options.find(d=>d.value===r)?.label??r):n==="appliedfrom"?o=e.appliedfrom:n==="appliedto"&&(o=e.appliedto),{key:n,text:ae(e.filterchip,{title:o,value:a})}},Mn=e=>{let{labels:t,rows:n,query:r}=e,o=e.presets.map(m=>({name:m.name,label:Br(t,m.name),active:m.active})),a=Object.fromEntries(Object.entries(r.filters).map(([m,d])=>[m,m===qe&&d===nt?"":d])),l=n===null?[]:n.filters.map(m=>$r(t,n,m.name,m.value));return qt(Et,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"pending",label:t.pending},{value:"deferred",label:t.deferred}],scope:r.scope,total:n===null?null:n.total,facets:n===null?[]:Wr(t,n),values:a,panelnote:Ur("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",qt("a",{href:e.tableurl,children:t.morefilters})]}),chips:l,aside:e.reporturl!==""?qt("a",{className:"enrol_apply-qview-tolink",href:e.reporturl,children:t.openreport}):null,columns:e.columns,sets:o,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:Or,tableurl:e.tableurl,downloads:[],search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:m=>e.onScope(m),onSearch:e.onSearch,onFacet:e.onFilter,onRemoveChip:m=>e.onFilter(m,""),onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:m=>{let d=e.presets.find(u=>u.name===m);d&&e.onPreset(d)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Qr,jsx as xn}from"react/jsx-runtime";var Pn=(e,t)=>t?e.split(`
`).map(n=>n.trim()).filter(n=>n!==""):[e],Mt=({parts:e})=>xn(Qr,{children:e.map((t,n)=>xn("span",{className:n===0?"enrol_apply-rview-c":"enrol_apply-rview-sub",title:wt(t),dangerouslySetInnerHTML:{__html:t}},n))});import{Fragment as to,jsx as ie,jsxs as xt}from"react/jsx-runtime";var qr={applicant:320,wait:150,group:180,comment:260,history:130},Kr=180,Gr={applicant:200},zr=80,Yr=800,Kt=mt(qr,Kr,Gr,zr,Yr),jr={deferred:"enrol_apply-status--warning",key:"enrol_apply-status--info",appliedbefore:"enrol_apply-status--neutral",own:"enrol_apply-status--neutral"},He=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",Gt=({html:e})=>ie(Mt,{parts:[e]}),Xr=({tag:e})=>{let t=jr[e.kind]??"enrol_apply-status--neutral",n=e.title!==""&&e.title!==e.text;return xt("span",{className:"enrol_apply-status "+t,title:n?e.title:void 0,children:[e.text,n?ie("span",{className:"visually-hidden",children:" "+e.title}):null]})},Jr=({row:e})=>e.applicant.profileurl===""?ie("span",{className:"enrol_apply-qview-name",children:e.applicant.fullname}):ie("a",{className:"enrol_apply-qview-name",href:e.applicant.profileurl,children:e.applicant.fullname}),Zr=({labels:e,row:t})=>ie("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:t.reviewurl,"aria-label":N(e.reviewapplicant,t.applicant.fullname),children:e.review}),eo=({row:e})=>xt(to,{children:[ie("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:e.applicant.picture}}),xt("div",{className:"enrol_apply-qview-whotext",children:[ie("span",{className:"enrol_apply-rview-c",children:ie(Jr,{row:e})}),e.applicant.identity.map(t=>ie("span",{className:"enrol_apply-rview-sub",title:t.value,children:t.value},t.name)),e.applicant.tags.length>0?ie("span",{className:"enrol_apply-qview-tags",children:e.applicant.tags.map(t=>ie(Xr,{tag:t},t.kind))}):null]})]}),zt=({labels:e,row:t})=>xt("div",{className:"enrol_apply-qview-who",children:[ie(eo,{row:t}),ie(Zr,{labels:e,row:t})]});var Oe=(e,t,n)=>{let r=document.createElement("input");r.type="hidden",r.name=t,r.value=n,e.appendChild(r)},Ln=e=>{let t=document.createElement("form");t.method="post",t.action=e.action,t.hidden=!0,Oe(t,"sesskey",e.sesskey),Oe(t,"formaction",e.decision),e.userenrolments.forEach(n=>Oe(t,"userenrolments[]",String(n))),Oe(t,"outcomemessage",e.message),Oe(t,"decisionnote",e.note),e.decision==="confirm"&&(e.groups?.forEach(n=>Oe(t,"groups[]",String(n))),e.roleid!==null&&Oe(t,"roleid",String(e.roleid))),document.body.appendChild(t),t.submit()};import{jsx as Yt,jsxs as no}from"react/jsx-runtime";var Dn=({labels:e,count:t,actions:n,onDecide:r})=>no("div",{className:"enrol_apply-qview-dock",role:"region","aria-label":e.dock,"data-region":"enrol_apply-dock",children:[Yt("span",{className:"enrol_apply-qview-dockcount","aria-live":"polite",children:N(e.selected,b(t))}),Yt("div",{className:"enrol_apply-qview-dockactions",children:n.map(o=>Yt("button",{type:"button",className:"btn "+o.classes,onClick:a=>r(o.value,a.currentTarget),children:o.label},o.value))})]});import{fetchOne as jt}from"@moodle/lms/core/ajax";var An=(e,t)=>jt({methodname:"enrol_apply_queue_rows",args:{enrolid:e,scope:t.scope,search:t.search,filters:Object.entries(t.filters).map(([n,r])=>({name:n,value:r})),page:t.page,perpage:t.perpage,sort:t.sort,direction:t.direction}}),Tn=e=>jt({methodname:"enrol_apply_queue_summary",args:{enrolid:e}}),Fn=(e,t)=>jt({methodname:"enrol_apply_set_queue_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}});import{useEffect as ro,useId as oo,useRef as lo,useState as rt}from"react";import{Fragment as io,jsx as D,jsxs as Z}from"react/jsx-runtime";var In=5,ao="button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), a[href]",so=(e,t,n)=>{let r=b(n);return t==="cancel"?[N(n===1?e.sheetcancelone:e.sheetcancel,r),N(e.sheetcancelcommit,r)]:t==="wait"?[N(n===1?e.sheetwaitone:e.sheetwait,r),N(e.sheetwaitcommit,r)]:[N(n===1?e.sheetconfirmone:e.sheetconfirm,r),N(e.sheetconfirmcommit,r)]},Pt=({id:e,text:t})=>D("div",{className:"form-text",id:e,children:t}),Vn=({labels:e,decision:t,rows:n,offer:r,onClose:o,onCommit:a})=>{let l=oo(),m=lo(null),[d,u]=rt(""),[y,h]=rt(""),[v,S]=rt(()=>(r.groups?.options??[]).filter(s=>s.selected).map(s=>String(s.id))),[i,M]=rt("0"),[x,f]=rt(!1),$=n.length,[G,T]=so(e,t,$),X=t==="confirm";ro(()=>(m.current?.focus(),document.body.classList.add("modal-open"),()=>document.body.classList.remove("modal-open")),[]);let z=s=>{if(s.key==="Escape"){s.preventDefault(),s.stopPropagation(),x||o();return}if(s.key!=="Tab"||!m.current)return;let g=Array.from(m.current.querySelectorAll(ao));if(g.length===0){s.preventDefault();return}let A=g[0],H=g[g.length-1],w=document.activeElement;s.shiftKey&&(w===A||w===m.current)?(s.preventDefault(),H.focus()):!s.shiftKey&&(w===H||w===m.current)&&(s.preventDefault(),A.focus())},Q=()=>{x||(f(!0),a({message:d,note:y,groups:X&&r.groups?v.map(Number):null,roleid:X&&r.roles?Number(i):null}))},ne=$-In;return Z(io,{children:[D("div",{className:"modal-backdrop show"}),D("div",{className:"modal show d-block enrol_apply-qview-sheet",role:"presentation",onMouseDown:s=>{s.target===s.currentTarget&&!x&&o()},children:D("div",{className:"modal-dialog modal-lg modal-dialog-scrollable",children:Z("div",{className:"modal-content",role:"dialog","aria-modal":"true","aria-labelledby":`${l}-title`,tabIndex:-1,ref:m,onKeyDown:z,"data-region":"enrol_apply-sheet",children:[Z("div",{className:"modal-header",children:[D("h5",{className:"modal-title",id:`${l}-title`,children:G}),D("button",{type:"button",className:"btn-close","aria-label":e.close,onClick:o,disabled:x})]}),Z("div",{className:"modal-body",children:[t==="cancel"?D("div",{className:"alert alert-danger",children:e.sheetcanceldanger}):null,t==="wait"?D("p",{className:"enrol_apply-qview-sheetline",children:e.sheetwaitline}):null,X&&r.placesfull!==""?D("div",{className:"alert alert-warning",children:r.placesfull}):null,Z("ul",{className:"enrol_apply-qview-sheetwho",children:[n.slice(0,In).map(s=>Z("li",{children:[D("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:s.applicant.picture}}),D("span",{children:s.applicant.fullname})]},s.id)),ne>0?D("li",{className:"enrol_apply-qview-sheetmore",children:N(e.andmore,b(ne))}):null]}),X&&r.summary?Z("div",{className:"enrol_apply-qview-sheetsummary",children:[Z("dl",{className:"row mb-0",children:[D("dt",{className:"col-sm-4",children:r.summary.groupslabel}),D("dd",{className:"col-sm-8",children:r.summary.groups}),D("dt",{className:"col-sm-4",children:r.summary.rolelabel}),D("dd",{className:"col-sm-8",children:r.summary.role})]}),Z("div",{className:"form-text",children:[r.summary.help,r.summary.note!==""?" "+r.summary.note:""]})]}):null,X&&r.groups?Z("div",{className:"mb-3",children:[D("label",{className:"form-label",htmlFor:`${l}-groups`,children:r.groups.label}),D("select",{className:"form-select",id:`${l}-groups`,multiple:!0,value:v,"aria-describedby":`${l}-groupshelp`,onChange:s=>S(Array.from(s.target.selectedOptions,g=>g.value)),children:r.groups.options.map(s=>D("option",{value:String(s.id),children:s.name},s.id))}),D(Pt,{id:`${l}-groupshelp`,text:r.groups.help})]}):null,X&&r.roles?Z("div",{className:"mb-3",children:[D("label",{className:"form-label",htmlFor:`${l}-role`,children:r.roles.label}),Z("select",{className:"form-select",id:`${l}-role`,value:i,"aria-describedby":`${l}-rolehelp`,onChange:s=>M(s.target.value),children:[D("option",{value:"0",children:r.roles.default}),r.roles.options.map(s=>D("option",{value:String(s.id),children:s.name},s.id))]}),D(Pt,{id:`${l}-rolehelp`,text:r.roles.help})]}):null,Z("div",{className:"mb-3",children:[D("label",{className:"form-label",htmlFor:`${l}-message`,children:r.message.label}),D("textarea",{className:"form-control",id:`${l}-message`,rows:3,value:d,"aria-describedby":`${l}-messagehelp`,onChange:s=>u(s.target.value)}),D(Pt,{id:`${l}-messagehelp`,text:r.message.help})]}),Z("div",{className:"mb-0",children:[D("label",{className:"form-label",htmlFor:`${l}-note`,children:r.note.label}),D("textarea",{className:"form-control",id:`${l}-note`,rows:3,value:y,"aria-describedby":`${l}-notehelp`,onChange:s=>h(s.target.value)}),D(Pt,{id:`${l}-notehelp`,text:r.note.help})]})]}),Z("div",{className:"modal-footer",children:[D("button",{type:"button",className:"btn btn-secondary",onClick:o,disabled:x,children:e.back}),D("button",{type:"button",className:"btn "+(t==="cancel"?"btn-danger":"btn-primary"),onClick:Q,disabled:x,children:T})]})]})})})]})};import{jsx as ce,jsxs as ot}from"react/jsx-runtime";var Hn=(e,t)=>Math.min(100,Math.max(0,Math.round(e*100/t))),Lt=({label:e,value:t,sub:n,meter:r,warn:o})=>ot("div",{className:"enrol_apply-rview-tile"+(o?" enrol_apply-rview-tile--warn":""),role:"listitem",children:[ce("div",{className:"enrol_apply-rview-tile-label",children:e}),ce("div",{className:"enrol_apply-rview-tile-value",children:t}),r!==null?ce("span",{className:"enrol_apply-qview-meter"+(o?" enrol_apply-qview-meter--warn":""),"aria-hidden":"true",children:ce("span",{style:{width:`${r}%`}})}):null,n!==""?ce("div",{className:"enrol_apply-rview-tile-sub",children:n}):null]}),co=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.DateTimeFormat(t,{dateStyle:"short"}).format(new Date(e*1e3))}catch{return new Date(e*1e3).toLocaleDateString()}},On=({labels:e,summary:t,error:n,onRetry:r})=>{if(t===null&&n)return ot("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",ce("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:r,children:e.retry})]});if(t===null)return ot("div",{className:"enrol_apply-rview-band","aria-busy":"true",children:[Array.from({length:4},(m,d)=>ot("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[ce("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),ce("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"})]},d)),ce("span",{className:"visually-hidden",role:"status",children:e.loadsummary})]});let o=t.narrowed?e.methodnote:"",a=t.awaiting>0?N(e.oldest,Ve(e,t.oldestwait)):"",l=t.late>0?N(e.late,b(t.late)):"";return ot("div",{className:"enrol_apply-rview-band",role:"list","data-region":"enrol_apply-tiles",children:[t.places>0?ce(Lt,{label:e.placestaken,value:ae(e.ofmany,{taken:b(t.placestaken),total:b(t.places)}),sub:o,meter:Hn(t.placestaken,t.places),warn:!1}):null,t.applicantlimit>0?ce(Lt,{label:e.held,value:ae(e.ofmany,{taken:b(t.applicants),total:b(t.applicantlimit)}),sub:o,meter:Hn(t.applicants,t.applicantlimit),warn:t.applicants*5>=t.applicantlimit*4}):null,ce(Lt,{label:e.awaiting,value:b(t.awaiting),sub:[a,l].filter(m=>m!=="").join(" \xB7 "),meter:null,warn:t.late>0}),ce(Lt,{label:e.status,value:t.open?e.open:e.closed,sub:t.open&&t.closes>0?N(e.closes,co(t.closes)):"",meter:null,warn:!1})]})};import{Fragment as Wn,jsx as j,jsxs as Jt}from"react/jsx-runtime";var uo=300,po=500,Bn="enrol_apply_queue_view",mo=["applicant","wait","group","comment"],wo=["applicant","wait","group"],vo=e=>e.scope!=="all"||e.search!==""||Object.entries(e.filters).some(([t,n])=>n!==""&&!(t===qe&&n===nt)),yo=(e,t)=>e.filter(n=>!wo.includes(n.key)&&He(t,n.key).trim()!=="").map(n=>({key:n.key,title:n.title,content:j(Gt,{html:He(t,n.key)})})),fo=e=>{let t=ee(e).current,{labels:n,enrolid:r}=t,o=ee(null),a=ht(o),[l,m]=ue(()=>bt(Bn)),[d]=ue(()=>fn(t)),u=ee(d),[y,h]=ue(d),[v,S]=ue(d.search),[i,M]=ue(null),[x,f]=ue(!0),[$,G]=ue(!1),[T,X]=ue(null),[z,Q]=ue(!0),[ne,s]=ue(!1),[g,A]=ue({}),H=ee(0),w=ee(0),F=ee(null),U=ee(null),B=ee(null),Y=ee(null),ge=ee(!1),J=ee(null),De=ee(null),he=ee(null),Ne=ee(()=>{}),[Be,be]=ue(()=>new Set),[le,Ce]=ue(null),re=ee(null),_e=Xt(c=>{let R=++H.current;F.current||(F.current=se("enrol_apply/queue:rows")),f(!0),An(r,c).then(E=>{R===H.current&&(ge.current||(ge.current=!0,A(Object.fromEntries(E.preference.widths.map(O=>[O.key,O.width])))),E.page!==u.current.page&&(u.current={...u.current,page:E.page},h(u.current),kt(t,u.current,!1)),M(E),be(O=>{let wr=new Set(E.rows.filter(je=>je.selectable).map(je=>je.userenrolmentid)),dn=Array.from(O).filter(je=>wr.has(je));return dn.length===O.size?O:new Set(dn)}),G(!1),f(!1))}).catch(()=>{R===H.current&&(G(!0),f(!1))})},[r,t]),Ge=Xt(()=>{let c=++w.current;U.current||(U.current=se("enrol_apply/queue:summary")),Q(!0),Tn(r).then(R=>{c===w.current&&(X(R),s(!1),Q(!1))}).catch(()=>{c===w.current&&(s(!0),Q(!1))})},[r]),oe=Xt((c,R)=>{u.current={...u.current,...c},h(u.current),be(new Set),kt(t,u.current,R),_e(u.current)},[_e,t]);Ke(()=>{!x&&F.current&&(F.current(),F.current=null)},[x,i,$]),Ke(()=>{!z&&U.current&&(U.current(),U.current=null)},[z,T,ne]),Ke(()=>{kt(t,u.current,!1),_e(u.current),Ge()},[_e,Ge,t]),Ke(()=>{let c=R=>{let E=gn(R.state);E!==null&&(u.current=E,h(E),S(E.search),be(new Set),_e(E))};return window.addEventListener("popstate",c),()=>window.removeEventListener("popstate",c)},[_e]),Ke(()=>()=>{B.current!==null&&window.clearTimeout(B.current),Y.current?.(),Ne.current()},[]);let Ae=c=>{m(c),_t(Bn,c)},Te=c=>{S(c),B.current!==null&&(window.clearTimeout(B.current),B.current=null);let R=c.trim(),E=R.length>=t.searchmin?R:"";if(E===u.current.search){Y.current?.(),Y.current=null;return}if(c===""){Y.current?.(),Y.current=null,oe({search:"",page:0},!1);return}Y.current||(Y.current=se("enrol_apply/queue:search")),B.current=window.setTimeout(()=>{B.current=null,oe({search:E,page:0},!1),Y.current?.(),Y.current=null},uo)},Ee=i?.groupdefault??"",We=(c,R)=>{let E={...u.current.filters};R!==""?E[c]=R:c===qe&&Ee!==""?E[qe]=nt:delete E[c],oe({filters:E,page:0},!0)},Me=()=>Ee!==""?{[qe]:nt}:{},Re=(c,R)=>{let E=se("enrol_apply/queue:preference");Fn(r,c).then(()=>{R&&_e(u.current)}).catch(()=>{R&&G(!0)}).finally(E)},ze=i?.columns??[],Fe=ze.filter(c=>!c.fixed&&c.active).map(c=>c.key),Ie=i?.preference.usedefault??!0,$e=c=>Object.entries(c).map(([R,E])=>({key:R,width:E})),Ot=(c,R)=>{let E=R?[...Fe,c]:Fe.filter(O=>O!==c);i&&M({...i,columns:i.columns.map(O=>O.key===c?{...O,active:R}:O),preference:{...i.preference,usedefault:!1,columns:E}}),Re({usedefault:!1,columns:E,widths:$e(g)},!0)},Bt=c=>{let R=new Set(c.columns);i&&M({...i,columns:i.columns.map(E=>E.fixed?E:{...E,active:R.has(E.key)||R.has("answers")&&E.key.startsWith("answer:")}),presets:i.presets.map(E=>({...E,active:E.name===c.name})),preference:{...i.preference,usedefault:!1,columns:c.columns}}),Re({usedefault:!1,columns:c.columns,widths:$e(g)},!0)},ct=()=>{J.current!==null&&(window.clearTimeout(J.current),J.current=null);let c=he.current;he.current=null,c!==null&&Re({usedefault:Ie,columns:Ie?[]:Fe,widths:$e(c)},!1),De.current?.(),De.current=null};Ne.current=ct;let ut=(c,R,E=!1)=>{let O={...g};if(R===null?delete O[c]:O[c]=R,A(O),he.current=O,!E){ct();return}J.current!==null&&window.clearTimeout(J.current),De.current||(De.current=se("enrol_apply/queue:width")),J.current=window.setTimeout(()=>Ne.current(),po)},pt=c=>{let R=i!==null&&i.sort===c&&i.direction==="asc"?"desc":"asc";oe({sort:c,direction:R,page:0},!0)},Ue=ze.filter(c=>c.active),Ye=c=>Ue.some(R=>R.key===c),dt=l==="cards"||l==="auto"&&a,p=(i?.rows??[]).map(c=>({...c,id:c.userenrolmentid}));Ke(()=>{le===null&&re.current&&(re.current.focus(),re.current=null)},[le]);let C=t.decision,P=C!==null&&i!==null&&i.candecide,I=new Set(p.filter(c=>!c.selectable).map(c=>c.id)),me=p.filter(c=>Be.has(c.id)),ir=c=>N(n.selectapplicant,c.applicant.fullname),cr=(c,R)=>be(E=>{let O=new Set(E);return R&&!I.has(c)?O.add(c):O.delete(c),O}),ur=c=>be(c?new Set(p.filter(R=>!I.has(R.id)).map(R=>R.id)):new Set),un=P?{selected:Be,locked:I,label:ir,pagelabel:n.selectpage,onSelect:cr,onSelectPage:ur}:void 0,pr=(c,R)=>{re.current=R,Ce(c)},dr=c=>{C===null||le===null||Ln({action:Je(t,u.current,!0),sesskey:C.sesskey,decision:le,userenrolments:me.map(R=>R.id),...c})},pn=null;i!==null&&(pn=dt?j(gt,{labels:n,rows:p,single:a,selection:un,renderCard:c=>({head:j(zt,{labels:n,row:c}),lines:Jt(Wn,{children:[Ye("wait")?j("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--open",dangerouslySetInnerHTML:{__html:He(c,"wait")}}):null,Ye("group")&&He(c,"group")!==""?j("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",dangerouslySetInnerHTML:{__html:He(c,"group")}}):null]}),fields:yo(Ue,c)})}):j(ft,{labels:n,columns:Ue,lead:"applicant",rows:p,widths:g,sizing:Kt,sort:i.sort,direction:i.direction,selection:un,renderLead:c=>j(zt,{labels:n,row:c}),renderCell:(c,R)=>j(Gt,{html:He(c,R.key)}),onSort:pt,onWidth:ut,onWidthCommit:()=>Ne.current()}));let mr=mo.map(c=>({key:c,title:c==="applicant"?n.applicant:"",sortable:!1,default:!0,active:!0,fixed:c==="applicant"}));return Jt("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-queueview",children:[j(On,{labels:n,summary:T,error:ne,onRetry:Ge}),i?.notingroup?j("p",{className:"enrol_apply-rview-narrowed",children:n.notingroup}):Jt(Wn,{children:[i?.narrowed?j("p",{className:"enrol_apply-rview-narrowed",children:n.narrowed}):null,i!==null&&!P?j("div",{className:"alert alert-info",role:"status",children:n.viewonly}):null,j(Mn,{labels:n,narrow:a,query:y,rows:i,columns:ze,presets:i?.presets??[],usedefault:Ie,haswidths:Object.keys(g).length>0,view:l,search:v,searchmin:t.searchmin,searching:x,tableurl:Je(t,y,!1),reporturl:t.reporturl,onScope:c=>oe({scope:c,page:0},!0),onSearch:Te,onFilter:We,onClearFilters:()=>oe({filters:Me(),page:0},!0),onClearAll:()=>{S(""),oe({filters:Me(),search:"",page:0},!0)},onColumn:Ot,onPreset:Bt,onDefaultColumns:()=>{i&&M({...i,preference:{...i.preference,usedefault:!0,columns:[]}}),Re({usedefault:!0,columns:[],widths:$e(g)},!0)},onResetWidths:()=>{A({}),Re({usedefault:Ie,columns:Ie?[]:Fe,widths:[]},!1)},onView:Ae}),j(Nt,{labels:n,loading:x,loaded:i!==null,error:$,total:i?.total??0,filtered:vo(y),cards:dt,narrow:a,skeleton:{columns:mr,lead:"applicant",widths:g,sizing:Kt},dock:C!==null&&P&&me.length>0?j(Dn,{labels:n,count:me.length,actions:C.actions,onDecide:pr}):null,onRetry:()=>_e(u.current),children:pn}),i!==null&&i.total>0?j(Rt,{labels:n,page:y.page,perpage:y.perpage,perpages:t.perpages,total:i.total,shown:i.rows.length,narrow:a,onPage:c=>oe({page:c},!0),onPerpage:c=>oe({perpage:c,page:0},!0)}):null]}),C!==null&&le!==null&&me.length>0?j(Vn,{labels:n,decision:le,rows:me,offer:C,onClose:()=>Ce(null),onCommit:dr}):null]})},$n=fo;import{useCallback as sn,useEffect as Ht,useRef as pe,useState as fe}from"react";import{useState as Zt}from"react";import{Fragment as tn,jsx as k,jsxs as K}from"react/jsx-runtime";var en=8,Un=7,Dt=({label:e,value:t,sub:n,warn:r,link:o})=>K("div",{className:"enrol_apply-rview-tile"+(r?" enrol_apply-rview-tile--warn":""),children:[k("div",{className:"enrol_apply-rview-tile-label",children:e}),k("div",{className:"enrol_apply-rview-tile-value",children:t}),k("div",{className:"enrol_apply-rview-tile-sub",children:n}),o??null]}),Qn=({label:e,share:t,figure:n,warn:r})=>K("div",{className:"enrol_apply-rview-hrow",role:"listitem",children:[k("span",{className:"enrol_apply-rview-hrow-label",title:e,children:e}),k("span",{className:"enrol_apply-rview-track","aria-hidden":"true",children:k("span",{className:"enrol_apply-rview-fill"+(r?" enrol_apply-rview-fill--late":""),style:{width:`${Math.round(Math.min(1,Math.max(0,t))*100)}%`}})}),k("span",{className:"enrol_apply-rview-hrow-n",children:n})]}),go=({compact:e})=>K("div",{"aria-hidden":"true",children:[k("div",{className:"enrol_apply-rview-band",children:Array.from({length:e?3:4},(t,n)=>K("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"}),k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"})]},n))}),e?null:k("div",{className:"enrol_apply-rview-dist",children:[0,1].map(t=>K("div",{className:"enrol_apply-rview-panel enrol_apply-rview-sk",children:[k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"}),k("span",{className:"enrol_apply-rview-skb"}),k("span",{className:"enrol_apply-rview-skb"}),k("span",{className:"enrol_apply-rview-skb"})]},t))})]}),ho=({labels:e,bymethod:t,bygroup:n,methods:r})=>{let[o,a]=Zt(r>1?"method":"group"),[l,m]=Zt(!1),[d,u]=Zt(""),y=o==="method"?t:n,h=Math.max(86400,...y.map(f=>f.median??0)),v=f=>k(Qn,{label:f.name,share:(f.median??0)/h,figure:`${f.median===null?"\u2013":Ve(e,f.median)} (${b(f.count)})`,warn:(f.median??0)>Un*86400},f.id),S=[...y].sort((f,$)=>$.count-f.count),i=o==="group"&&y.length>en,M=d.trim().toLocaleLowerCase(),x=[...y].filter(f=>M===""||f.name.toLocaleLowerCase().includes(M)).sort((f,$)=>($.median??0)-(f.median??0));return K("div",{className:"enrol_apply-rview-panel",children:[K("div",{className:"enrol_apply-rview-panel-title",children:[k("span",{children:e.medianby}),r>1?k(et,{label:e.breakdown,small:!0,value:o,options:[{value:"method",label:e.bymethod},{value:"group",label:e.bygroup}],onChange:f=>a(f)}):k("span",{children:e.bygroup})]}),i?K(tn,{children:[k("div",{className:"enrol_apply-rview-note",children:N(e.topgroups,b(en))}),k("div",{role:"list",children:S.slice(0,en).map(v)}),k("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":l,onClick:()=>m(!l),children:l?e.hide:N(e.allgroups,b(y.length))}),l?K(tn,{children:[k("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:e.searchgroup,"aria-label":e.searchgroup,value:d,onChange:f=>u(f.target.value)}),k("div",{className:"enrol_apply-rview-scrolllist",role:"list",children:x.length?x.map(v):k("p",{className:"enrol_apply-rview-note",children:e.nogroupmatch})})]}):null]}):k("div",{role:"list",children:S.map(v)})]})},qn=({labels:e,summary:t,loading:n,error:r,compactHint:o,measure:a,methods:l,queueurl:m,pendingstatus:d,onMeasure:u,onRetry:y})=>{let h=K("div",{className:"enrol_apply-rview-bandhead",children:[k("span",{className:"enrol_apply-rview-bandtitle",children:e.turnaround}),k(et,{label:e.measure,small:!0,value:a,options:[{value:"first",label:e.measurefirst},{value:"resolution",label:e.measureresolution}],onChange:S=>u(S)})]}),v;if(t===null&&r)v=K("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",k("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:y,children:e.retry})]});else if(t===null)v=K(tn,{children:[k(go,{compact:o}),k("span",{className:"visually-hidden",role:"status",children:e.loadband})]});else{let S=t.measure==="resolution",i=t.median??null,M=t.p90??null,x=t.oldest??null,f=e.nothingopen;x!==null&&(f=N(e.oldest,Ve(e,x))+(t.late>0?" \xB7 "+N(e.late,b(t.late)):""));let $=m===""?null:k("a",{className:"enrol_apply-rview-tile-link",href:S?m:`${m}&status=${d}`,children:e.openqueue}),G=Math.max(1,...t.buckets.map(T=>T.count));v=K("div",{className:"enrol_apply-rview-dim",children:[K("div",{className:"enrol_apply-rview-band",children:[k(Dt,{label:S?e.medianresolution:e.medianfirst,value:i===null?"\u2013":Ve(e,i),sub:N(e.mediansub,b(t.decided))}),t.compact?null:k(Dt,{label:e.p90,value:M===null?"\u2013":Ve(e,M),sub:e.p90sub}),k(Dt,{label:e.open,value:b(t.open),sub:f,warn:t.late>0,link:$}),k(Dt,{label:e.withoutperson,value:b(t.withoutperson),sub:e.withoutpersonsub})]}),t.compact?null:K("div",{className:"enrol_apply-rview-dist",children:[K("div",{className:"enrol_apply-rview-panel",children:[K("div",{className:"enrol_apply-rview-panel-title",children:[k("span",{children:S?e.distresolution:e.distfirst}),k("span",{className:"enrol_apply-rview-note",children:N(e.decisions,b(t.decided))})]}),k("div",{role:"list",children:t.buckets.map((T,X)=>{let z=b(T.todays??0),Q=ae(e.bucket,{from:b(T.fromdays),to:z});return X===0?Q=N(e.bucketfirst,b(T.todays??0)):(T.todays??null)===null&&(Q=N(e.bucketlast,b(T.fromdays))),k(Qn,{label:Q,share:T.count/G,figure:b(T.count),warn:T.fromdays>=Un},T.fromdays)})})]}),k(ho,{labels:e,bymethod:t.bymethod,bygroup:t.bygroup,methods:l})]})]})}return K("section",{className:"enrol_apply-rview-region enrol_apply-rview-bandregion","aria-label":e.turnaround,"aria-busy":n,"data-region":"enrol_apply-band",children:[n&&t!==null?k("div",{className:"enrol_apply-rview-progress"}):null,h,v]})};var Kn=e=>e.facet!==""&&e.options.length>0,Gn=e=>{try{let t=JSON.parse(e.values||"{}");return t!==null&&typeof t=="object"?t:{}}catch{return{}}},bo=e=>e.reduce((t,n)=>({...t,...Gn(n)}),{}),nn=e=>{if(!e.applied)return"";let t=Gn(e),n=t[`${e.name}_value`];if(n==null)return"";let r=Number(t[`${e.name}_operator`]??0);if(e.facet==="waited"){let o=Number(n)*Number(t[`${e.name}_unit`]??0)/86400;return r===2&&Number.isFinite(o)?String(o):""}return e.facet==="decider"?r===3?String(n):"":String(n)},zn=(e,t,n)=>{let r=rn(e,t);return t.facet==="waited"?(r[`${t.name}_operator`]=n===""?0:2,r[`${t.name}_value`]=n===""?0:Number(n),r[`${t.name}_unit`]=86400):t.facet==="decider"?(r[`${t.name}_operator`]=n===""?0:3,r[`${t.name}_value`]=n):(r[`${t.name}_operator`]=n===""?0:1,r[`${t.name}_value`]=n),r},rn=(e,t)=>{let n=bo(e);for(let r of Object.keys(n))r.startsWith(`${t.name}_`)&&delete n[r];return n},Yn=e=>{let t=nn(e);return e.facet===""||t===""?"":e.options.find(n=>n.value===t)?.label??(e.facet==="decider"?t:"")};import{jsx as jn,jsxs as Co}from"react/jsx-runtime";var _o=["auto","table","cards"],Xn=(e,t)=>t.facet==="waited"?e.waitedmore:t.facet==="decider"?e.decidedby:t.title,Ro=(e,t)=>{switch(t){case"decisions":return e.presetdecisions;case"answers":return e.presetanswers;case"groups":return e.presetgroups;default:return t}},No=(e,t,n)=>{let r={measure:e};return t!=="all"&&(r.scope=t),n!==""&&(r.search=n),encodeURIComponent(JSON.stringify(r))},ko=(e,t)=>({name:t.name,title:Xn(e,t),options:t.options,groupings:t.groupings,find:t.facet==="decider"?{label:e.searchdeciders,nomatch:e.nodecidermatch}:{label:e.searchgroups,nomatch:e.nogroupmatch}}),So=(e,t)=>{let n=Yn(t),r=n!==""?Xn(e,t):t.title;return{key:t.name,text:n===""?r:ae(e.filterchip,{title:r,value:n})}},Jn=e=>{let{labels:t,filters:n}=e,r=e.measure==="resolution"?"submission:resolutiontime":"submission:waittime",o=v=>Kn(v)&&(v.facet!=="waited"||v.name===r),a=n.filter(o),l=n.filter(v=>!o(v)),m=v=>n.find(S=>S.name===v),d=e.downloadurl!==""&&e.formats.length>0,u=No(e.measure,e.scope,e.appliedsearch),y=d?e.formats.map(v=>({key:v.value,label:v.label,href:`${e.downloadurl}&parameters=${u}&download=${encodeURIComponent(v.value)}`})):[],h=e.presets.map(v=>({name:v.name,label:Ro(t,v.name),active:v.active}));return jn(Et,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"awaiting",label:t.awaiting},{value:"decided",label:t.decided}],scope:e.scope,total:e.total,facets:a.map(v=>ko(t,v)),values:Object.fromEntries(a.map(v=>[v.name,nn(v)])),panelnote:l.length>0?Co("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",jn("a",{href:e.tableurl,children:t.morefilters})]}):null,chips:n.filter(v=>v.applied).map(v=>So(t,v)),columns:e.columns,sets:h,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:_o,tableurl:e.tableurl,downloads:y,search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:v=>e.onScope(v),onSearch:e.onSearch,onFacet:(v,S)=>{let i=m(v);i&&e.onFilter(i,S)},onRemoveChip:v=>{let S=m(v);S&&e.onRemoveFilter(S)},onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:v=>{let S=e.presets.find(i=>i.name===v);S&&e.onPreset(S)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Ao,jsx as At}from"react/jsx-runtime";var Eo=["outcome","wait","decided","group"],Mo={applicant:260,outcome:180,submitted:132,wait:150,decided:172,group:200,method:170,route:170,comment:240,note:240},xo=180,Po={applicant:160,submitted:104,decided:104},Lo=80,Do=800,Tt=mt(Mo,xo,Po,Lo,Do),ye=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",Ft=(e,t)=>Pn(t,Eo.includes(e)),lt=({column:e,html:t})=>At(Mt,{parts:Ft(e,t)}),It=({row:e,label:t})=>e.reviewurl===""?null:At("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:e.reviewurl,children:t}),Vt=({row:e,identity:t})=>At(Ao,{children:t.map(n=>{let r=ye(e,n.key);return r===""?null:At("span",{className:"enrol_apply-rview-sub",title:wt(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});import{Fragment as Zn,jsx as te,jsxs as st}from"react/jsx-runtime";var To=["applicant","outcome","submitted","wait","decided","group","method"],at=({row:e,column:t})=>{let n=Ft(t,ye(e,t))[0]??"";return n===""?null:te("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:n}})},Fo=(e,t,n,r)=>{let o=l=>t.some(m=>m.key===l),a=t.filter(l=>!To.includes(l.key)&&ye(r,l.key).trim()!=="");return{head:st(Zn,{children:[st("div",{className:"enrol_apply-rview-card-who",children:[te("span",{className:"enrol_apply-rview-card-name",dangerouslySetInnerHTML:{__html:ye(r,"applicant")}}),o("outcome")?te(at,{row:r,column:"outcome"}):null,te(Vt,{row:r,identity:n})]}),te(It,{row:r,label:e.review})]}),lines:st(Zn,{children:[st("div",{className:"enrol_apply-rview-card-line"+(r.open?" enrol_apply-rview-card-line--open":""),children:[o("wait")?te(at,{row:r,column:"wait"}):null,o("wait")&&o("submitted")?te("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("submitted")?te(at,{row:r,column:"submitted"}):null]}),o("decided")?te("div",{className:"enrol_apply-rview-card-line",children:Ft("decided",ye(r,"decided")).map((l,m)=>te("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:l}},m))}):null,o("group")||o("method")?st("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",children:[o("group")?te(at,{row:r,column:"group"}):null,o("group")&&o("method")&&ye(r,"group")!==""?te("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("method")?te(at,{row:r,column:"method"}):null]}):null]}),fields:a.map(l=>({key:l.key,title:l.title,content:te(lt,{column:l.key,html:ye(r,l.key)})}))}},er=({labels:e,columns:t,identity:n,rows:r,single:o})=>te(gt,{labels:e,rows:r,single:o,renderCard:a=>Fo(e,t,n,a)});import{Fragment as Vo,jsx as Pe,jsxs as on}from"react/jsx-runtime";var Io=({row:e,longest:t})=>!e.open&&!e.decidedbyperson?null:Pe("span",{className:"enrol_apply-rview-waitbar"+(e.open?" enrol_apply-rview-waitbar--open":""),"aria-hidden":"true",children:Pe("span",{style:{width:`${Math.min(100,Math.round(e.wait/t*100))}%`}})}),tr=({labels:e,columns:t,identity:n,rows:r,widths:o,sort:a,direction:l,onSort:m,onWidth:d,onWidthCommit:u})=>{let y=Math.max(86400,...r.filter(h=>h.open||h.decidedbyperson).map(h=>h.wait));return Pe(ft,{labels:e,columns:t,lead:"applicant",rows:r,widths:o,sizing:Tt,sort:a,direction:l,renderLead:(h,v)=>on("div",{className:"enrol_apply-rview-applicant",children:[on("div",{className:"enrol_apply-rview-applicant-text",children:[Pe(lt,{column:v.key,html:ye(h,v.key)}),Pe(Vt,{row:h,identity:n})]}),Pe(It,{row:h,label:e.review})]}),renderCell:(h,v)=>on(Vo,{children:[Pe(lt,{column:v.key,html:ye(h,v.key)}),v.key==="wait"?Pe(Io,{row:h,longest:y}):null]}),onSort:m,onWidth:d,onWidthCommit:u})};import{fetchOne as it}from"@moodle/lms/core/ajax";var nr=(e,t,n)=>it({methodname:"enrol_apply_report_rows",args:{enrolid:e,...t,facets:n}}),rr=(e,t,n,r)=>it({methodname:"enrol_apply_report_summary",args:{enrolid:e,measure:t,scope:n,search:r}}),or=(e,t)=>it({methodname:"enrol_apply_set_report_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}}),ln=(e,t,n)=>it({methodname:"core_reportbuilder_set_filters",args:{reportid:e,parameters:JSON.stringify({measure:t}),values:JSON.stringify(n)}}),an=(e,t)=>it({methodname:"core_reportbuilder_filters_reset",args:{reportid:e,parameters:JSON.stringify({measure:t})}});import{Fragment as $o,jsx as Le,jsxs as cn}from"react/jsx-runtime";var Ho=300,Oo=500,lr="enrol_apply_report_view",Bo=["applicant","outcome","submitted","wait","decided","group"],Wo=e=>{let{labels:t,enrolid:n,reportid:r}=e,o=pe(null),a=ht(o),[l,m]=fe(()=>bt(lr)),d={measure:e.measure,scope:"all",search:"",page:0,perpage:e.perpage,sort:"",direction:"desc"},u=pe(d),[y,h]=fe(d),[v,S]=fe(""),[i,M]=fe(null),[x,f]=fe(!0),[$,G]=fe(!1),[T,X]=fe(null),[z,Q]=fe(!0),[ne,s]=fe(!1),[g,A]=fe({}),[H,w]=fe({}),F=pe(0),U=pe(0),B=pe(null),Y=pe(null),ge=pe(null),J=pe(null),De=pe(!1),he=pe(null),Ne=pe(null),Be=pe(null),be=pe(()=>{}),le=sn((p,C)=>{let P=++F.current;B.current||(B.current=se("enrol_apply/report:rows")),f(!0),nr(n,p,C).then(I=>{P===F.current&&(I.counted&&w(Object.fromEntries(I.filters.map(me=>[me.name,me]))),De.current||(De.current=!0,A(Object.fromEntries(I.preference.widths.map(me=>[me.key,me.width])))),I.page!==u.current.page&&(u.current={...u.current,page:I.page},h(u.current)),M(I),G(!1),f(!1))}).catch(()=>{P===F.current&&(G(!0),f(!1))})},[n]),Ce=sn(p=>{let C=++U.current;Y.current||(Y.current=se("enrol_apply/report:band")),Q(!0),rr(n,p.measure,p.scope,p.search).then(P=>{C===U.current&&(X(P),s(!1),Q(!1))}).catch(()=>{C===U.current&&(s(!0),Q(!1))})},[n]),re=sn((p,C,P)=>{u.current={...u.current,...p},h(u.current),C&&le(u.current,P),P&&Ce(u.current)},[le,Ce]);Ht(()=>{!x&&B.current&&(B.current(),B.current=null)},[x,i,$]),Ht(()=>{!z&&Y.current&&(Y.current(),Y.current=null)},[z,T,ne]),Ht(()=>{le(u.current,!0),Ce(u.current)},[le,Ce]),Ht(()=>()=>{ge.current!==null&&window.clearTimeout(ge.current),J.current?.(),be.current()},[]);let _e=p=>{m(p),_t(lr,p)},Ge=p=>{S(p),ge.current!==null&&(window.clearTimeout(ge.current),ge.current=null);let C=p.trim(),P=C.length>=e.searchmin?C:"";if(P===u.current.search){J.current?.(),J.current=null;return}if(p===""){J.current?.(),J.current=null,re({search:"",page:0},!0,!0);return}J.current||(J.current=se("enrol_apply/report:search")),ge.current=window.setTimeout(()=>{ge.current=null,re({search:P,page:0},!0,!0),J.current?.(),J.current=null},Ho)},oe=p=>{let C=se("enrol_apply/report:filters");p().then(()=>{re({page:0},!0,!0)}).catch(()=>{G(!0)}).finally(C)},Ae=(i?.filters??[]).map(p=>H[p.name]?{...p,options:H[p.name].options,groupings:H[p.name].groupings}:p),Te=(p,C)=>{let P=se("enrol_apply/report:preference");or(n,p).then(()=>{C&&le(u.current,!1)}).catch(()=>{C&&G(!0)}).finally(P)},Ee=i?.columns??[],We=Ee.filter(p=>!p.fixed&&p.active).map(p=>p.key),Me=i?.preference.usedefault??!0,Re=p=>Object.entries(p).map(([C,P])=>({key:C,width:P})),ze=(p,C)=>{let P=C?[...We,p]:We.filter(I=>I!==p);i&&M({...i,columns:i.columns.map(I=>I.key===p?{...I,active:C}:I),preference:{...i.preference,usedefault:!1,columns:P}}),Te({usedefault:!1,columns:P,widths:Re(g)},!0)},Fe=()=>{he.current!==null&&(window.clearTimeout(he.current),he.current=null);let p=Be.current;Be.current=null,p!==null&&Te({usedefault:Me,columns:Me?[]:We,widths:Re(p)},!1),Ne.current?.(),Ne.current=null};be.current=Fe;let Ie=(p,C,P=!1)=>{let I={...g};if(C===null?delete I[p]:I[p]=C,A(I),Be.current=I,!P){Fe();return}he.current!==null&&window.clearTimeout(he.current),Ne.current||(Ne.current=se("enrol_apply/report:width")),he.current=window.setTimeout(()=>be.current(),Oo)},$e=p=>{let C=new Set(p.columns);i&&M({...i,columns:i.columns.map(P=>P.fixed?P:{...P,active:C.has(P.key)||C.has("answers")&&P.key.startsWith("answer:")}),presets:i.presets.map(P=>({...P,active:P.name===p.name})),preference:{...i.preference,usedefault:!1,columns:p.columns}}),Te({usedefault:!1,columns:p.columns,widths:Re(g)},!0)},Ot=p=>{let P=(i?.sort??"")===p&&i?.direction==="asc"?"desc":"asc";re({sort:p,direction:P,page:0},!0,!1)},Bt=p=>{let C=u.current,P=Ee.some(I=>I.key==="wait"&&I.active);re({measure:p},C.scope!=="all"||C.sort==="wait"||P,!0)},ct=Ae.some(p=>p.applied)||y.search!==""||y.scope!=="all",ut=Ee.filter(p=>p.key.startsWith("identity:")),pt=Ee.filter(p=>p.key==="applicant"||!p.fixed&&p.active),Ue=l==="cards"||l==="auto"&&a,Ye=null;i!==null&&(Ye=Ue?Le(er,{labels:t,columns:pt,identity:ut,rows:i.rows,single:a}):Le(tr,{labels:t,columns:pt,identity:ut,rows:i.rows,widths:g,sort:i.sort,direction:i.direction,onSort:Ot,onWidth:Ie,onWidthCommit:()=>be.current()}));let dt=Bo.map(p=>({key:p,title:p==="applicant"?t.applicant:"",sortable:!1,default:!0,active:!0,fixed:p==="applicant"}));return cn("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-reportview",children:[i?.narrowed?Le("p",{className:"enrol_apply-rview-narrowed",children:t.narrowed}):null,Le(qn,{labels:t,summary:T,loading:z,error:ne,compactHint:i?.narrowed??!1,measure:y.measure,methods:e.methods,queueurl:e.queueurl,pendingstatus:e.pendingstatus,onMeasure:Bt,onRetry:()=>Ce(u.current)}),Le(Jn,{labels:t,narrow:a,scope:y.scope,total:i===null?null:i.total,filters:Ae,columns:Ee.filter(p=>!p.key.startsWith("identity:")||p.active),presets:i?.presets??[],usedefault:Me,haswidths:Object.keys(g).length>0,view:l,measure:y.measure,search:v,appliedsearch:y.search,searchmin:e.searchmin,searching:x,tableurl:e.tableurl,downloadurl:e.downloadurl,formats:e.formats,onScope:p=>re({scope:p,page:0},!0,!0),onSearch:Ge,onFilter:(p,C)=>oe(()=>ln(r,u.current.measure,zn(Ae,p,C))),onRemoveFilter:p=>oe(()=>ln(r,u.current.measure,rn(Ae,p))),onClearFilters:()=>oe(()=>an(r,u.current.measure)),onClearAll:()=>{S(""),u.current={...u.current,search:""},Ae.some(p=>p.applied)?oe(()=>an(r,u.current.measure)):re({page:0},!0,!0)},onColumn:ze,onPreset:$e,onDefaultColumns:()=>{i&&M({...i,preference:{...i.preference,usedefault:!0,columns:[]}}),Te({usedefault:!0,columns:[],widths:Re(g)},!0)},onResetWidths:()=>{A({}),Te({usedefault:Me,columns:Me?[]:We,widths:[]},!1)},onView:_e}),Le(Nt,{labels:t,loading:x,loaded:i!==null,error:$,total:i?.total??0,filtered:ct,cards:Ue,narrow:a,skeleton:{columns:dt,lead:"applicant",widths:g,sizing:Tt},onRetry:()=>le(u.current,!0),children:Ye}),i!==null&&i.total>0?Le(Rt,{labels:t,page:y.page,perpage:y.perpage,perpages:e.perpages,total:i.total,shown:i.rows.length,narrow:a,onPage:p=>re({page:p},!0,!1),onPerpage:p=>re({perpage:p,page:0},!0,!1)}):null,e.retention!==""?cn("p",{className:"enrol_apply-rview-retention","data-region":"enrol_apply-retention",children:[e.retention,e.retentionurl!==""?cn($o,{children:[" ",Le("a",{href:e.retentionurl,children:t.retentionchange})]}):null]}):null]})},ar=Wo;import{jsx as sr}from"react/jsx-runtime";var Uo=e=>e.mount===void 0||e.mount==="report"?sr(ar,{...e}):e.mount==="queue"?sr($n,{...e}):null,us=Uo;export{us as default};
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
