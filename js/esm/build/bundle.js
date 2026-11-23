import{useCallback as sn,useEffect as Je,useRef as oe,useState as de}from"react";import{useEffect as Ir,useState as Vr}from"react";var Hr=576,yt=e=>{let[t,n]=Vr(!1);return Ir(()=>{let r=e.current;if(!r||typeof ResizeObserver>"u")return;let o=new ResizeObserver(a=>{let l=a[0]?.contentRect.width??r.clientWidth;n(l>0&&l<Hr)});return o.observe(r),()=>o.disconnect()},[e]),t},ft=e=>{try{let t=window.localStorage.getItem(e);return t==="table"||t==="cards"?t:"auto"}catch{return"auto"}},bt=(e,t)=>{try{window.localStorage.setItem(e,t)}catch{}};import{useId as Or}from"react";var R=(e,t)=>(e||"").split("{$a}").join(t),ce=(e,t)=>Object.entries(t).reduce((n,[r,o])=>n.split(`{$a->${r}}`).join(o),e||""),bn=(e,t,n,r)=>R(n===1&&e[`${t}_one`]?e[`${t}_one`]:e[t],r),f=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(t).format(e)}catch{return String(e)}},We=(e,t)=>{let n=Math.max(0,t);if(n<3600)return R(e.waitminutes,f(Math.max(1,Math.round(n/60))));if(n<86400)return R(e.waithours,f(Math.round(n/3600)));let r=Math.floor(n/86400),o=Math.round((n-r*86400)/3600);return o===24&&(r++,o=0),o>0&&r<10?ce(e.waitdayshours,{days:f(r),hours:f(o)}):R(e.waitdays,f(r))},Rt=e=>{let t=document.createElement("template");return t.innerHTML=e,(t.content.textContent||"").replace(/\s+/g," ").trim()};import{jsx as Ee,jsxs as Kt}from"react/jsx-runtime";var Br=(e,t)=>[...new Set([1,e-1,e,e+1,t])].filter(n=>n>=1&&n<=t).sort((n,r)=>n-r),_t=({labels:e,page:t,perpage:n,perpages:r,total:o,shown:a,narrow:l,onPage:i,onPerpage:u})=>{let s=Or(),g=Math.max(1,Math.ceil(o/n)),b=t+1,d=o>0?t*n+1:0,_=o>0?d+a-1:0,c=[];if(l)c=[Ee("span",{className:"enrol_apply-rview-pager-gap",children:ce(e.pageof,{page:f(b),pages:f(g)})},"of")];else{let N=Br(b,g);N.forEach((M,h)=>{h>0&&M-N[h-1]>1&&c.push(Ee("span",{className:"enrol_apply-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${M}`)),c.push(Ee("button",{type:"button","aria-current":M===b?"page":void 0,onClick:()=>i(M-1),children:f(M)},M))})}return Kt("div",{className:"enrol_apply-rview-foot",children:[Ee("span",{className:"enrol_apply-rview-num","data-region":"enrol_apply-range",children:ce(e.range,{from:f(d),to:f(_),total:f(o)})}),g>1?Kt("nav",{className:"enrol_apply-rview-pager","aria-label":e.pages,children:[Ee("button",{type:"button",disabled:b===1,onClick:()=>i(t-1),children:e.previous}),c,Ee("button",{type:"button",disabled:b===g,onClick:()=>i(t+1),children:e.next})]}):null,Kt("span",{className:"enrol_apply-rview-perpage",children:[Ee("label",{htmlFor:s,children:e.perpage}),Ee("select",{id:s,className:"form-select form-select-sm",value:n,onChange:N=>u(Number(N.target.value)),children:r.map(N=>Ee("option",{value:N,children:f(N)},N))})]})]})};var ue=e=>{let t=window.M?.util;t?.js_pending(e);let n=!0;return()=>{n&&(n=!1,t?.js_complete(e))}};import{useState as Qr}from"react";import{useEffect as Wr,useRef as St}from"react";var Nt=(e,t,n,r,o)=>({defaultWidth:a=>e[a]??t,minimumWidth:a=>n[a]??r,maximum:o}),he=(e,t,n)=>Math.min(e.maximum,Math.max(e.minimumWidth(n),t[n]??e.defaultWidth(n)));import{jsx as A,jsxs as Pe}from"react/jsx-runtime";var kt=16,$r={ascending:"\u25B2",descending:"\u25BC",none:""},qr=(e,t)=>e?t==="asc"?"ascending":"descending":"none",Ur=({selection:e,rows:t})=>{let n=St(null),r=t.filter(l=>!e.locked.has(l.id)),o=r.filter(l=>e.selected.has(l.id)).length,a=r.length>0&&o===r.length;return Wr(()=>{n.current&&(n.current.indeterminate=o>0&&!a)},[o,a]),Pe("label",{className:"enrol_apply-rview-pickwrap",children:[A("input",{ref:n,type:"checkbox",className:"enrol_apply-rview-pick",checked:a,disabled:r.length===0,onChange:l=>e.onSelectPage(l.target.checked)}),A("span",{className:"visually-hidden",children:e.pagelabel})]})},Gt=({selection:e,row:t})=>e.locked.has(t.id)?A("span",{className:"enrol_apply-rview-pickwrap enrol_apply-rview-pick--none"}):Pe("label",{className:"enrol_apply-rview-pickwrap",children:[A("input",{type:"checkbox",className:"enrol_apply-rview-pick",checked:e.selected.has(t.id),onChange:n=>e.onSelect(t.id,n.target.checked)}),A("span",{className:"visually-hidden",children:e.label(t)})]}),Ct=({labels:e,columns:t,lead:n,rows:r,widths:o,sizing:a,sort:l,direction:i,selection:u,renderLead:s,renderCell:g,onSort:b,onWidth:d,onWidthCommit:_})=>{let c=St(null),N=St(null),M=St(null),h=t.reduce((w,y)=>w+he(a,o,y.key),0),W=w=>{w.currentTarget.classList.toggle("is-scrolled",w.currentTarget.scrollLeft>0)},K=(w,y)=>{let m=N.current?.querySelector(`col[data-col="${CSS.escape(w)}"]`);!m||!N.current||(m.style.width=`${y}px`,N.current.style.width=`${h-he(a,o,w)+y}px`)},F=(w,y)=>Math.round(Math.min(a.maximum,Math.max(a.minimumWidth(w),y))),ge=(w,y)=>{if(w.button!==0)return;w.preventDefault(),w.currentTarget.setPointerCapture(w.pointerId),w.currentTarget.classList.add("is-active");let m=he(a,o,y);M.current={key:y,x:w.clientX,width:m,now:m}},G=w=>{let y=M.current;if(!y)return;let m=document.documentElement.dir==="rtl",D=(w.clientX-y.x)*(m?-1:1),I=F(y.key,y.width+D);I!==y.now&&(K(y.key,I),y.now=I)},U=w=>{let y=M.current;M.current=null,w.currentTarget.classList.remove("is-active"),y&&y.now!==y.width&&d(y.key,y.now)},J=(w,y)=>{let m=document.documentElement.dir==="rtl",D=0;if(w.key==="ArrowRight")D=m?-kt:kt;else if(w.key==="ArrowLeft")D=m?kt:-kt;else if(w.key==="Home"){w.preventDefault(),d(y,null,!0);return}if(D===0)return;w.preventDefault();let I=F(y,he(a,o,y)+D);I!==he(a,o,y)&&d(y,I,!0)};return A("div",{className:"enrol_apply-rview-gridframe",ref:c,tabIndex:0,role:"region","aria-label":e.grid,onScroll:W,children:Pe("table",{className:"enrol_apply-rview-grid",ref:N,style:{width:`${h}px`},children:[A("caption",{className:"visually-hidden",children:e.caption}),A("colgroup",{children:t.map(w=>A("col",{"data-col":w.key,style:{width:`${he(a,o,w.key)}px`}},w.key))}),A("thead",{children:A("tr",{children:t.map(w=>{let y=qr(l===w.key,i),m=A("span",{className:"enrol_apply-rview-th-title",children:w.title}),D=w.sortable?Pe("button",{type:"button",className:"enrol_apply-rview-sortbtn",onClick:()=>b(w.key),children:[m,A("span",{className:"enrol_apply-rview-arrow","aria-hidden":"true",children:$r[y]})]}):A("span",{className:"enrol_apply-rview-plain",children:m});return Pe("th",{scope:"col",className:w.key===n?"enrol_apply-rview-fixed":void 0,"aria-sort":w.sortable?y:void 0,title:w.title,"data-column":w.key,children:[u&&w.key===n?Pe("div",{className:"enrol_apply-rview-lead",children:[A(Ur,{selection:u,rows:r}),D]}):D,A("span",{className:"enrol_apply-rview-resize",role:"separator","aria-orientation":"vertical","aria-label":R(e.resize,w.title),"aria-valuemin":a.minimumWidth(w.key),"aria-valuemax":a.maximum,"aria-valuenow":he(a,o,w.key),tabIndex:0,onPointerDown:I=>ge(I,w.key),onPointerMove:G,onPointerUp:U,onPointerCancel:U,onDoubleClick:()=>{he(a,o,w.key)!==a.defaultWidth(w.key)&&d(w.key,null)},onKeyDown:I=>J(I,w.key),onBlur:_})]},w.key)})})}),A("tbody",{children:r.map(w=>A("tr",{"data-record":w.id,className:u?.selected.has(w.id)?"enrol_apply-rview-row--selected":void 0,children:t.map(y=>y.key===n?A("th",{scope:"row",className:"enrol_apply-rview-fixed",children:u?Pe("div",{className:"enrol_apply-rview-lead",children:[A(Gt,{selection:u,row:w}),s(w,y)]}):s(w,y)},y.key):A("td",{children:g(w,y)},y.key))},w.id))})]})})},Rn=({columns:e,lead:t,widths:n,sizing:r})=>{let o=e.reduce((l,i)=>l+he(r,n,i.key),0),a=["enrol_apply-rview-skb--w80","enrol_apply-rview-skb--w60","enrol_apply-rview-skb--w40"];return A("div",{className:"enrol_apply-rview-gridframe","aria-hidden":"true",children:Pe("table",{className:"enrol_apply-rview-grid",style:{width:`${o}px`},children:[A("colgroup",{children:e.map(l=>A("col",{style:{width:`${he(r,n,l.key)}px`}},l.key))}),A("thead",{children:A("tr",{children:e.map(l=>A("th",{className:l.key===t?"enrol_apply-rview-fixed":void 0,children:A("span",{className:"enrol_apply-rview-plain",children:A("span",{className:"enrol_apply-rview-th-title",children:l.title})})},l.key))})}),A("tbody",{children:Array.from({length:8},(l,i)=>A("tr",{className:"enrol_apply-rview-sk",children:e.map((u,s)=>A("td",{className:u.key===t?"enrol_apply-rview-fixed":void 0,children:A("span",{className:"enrol_apply-rview-skb "+a[(i+s)%3]})},u.key))},i))})]})})};import{jsx as we,jsxs as ot}from"react/jsx-runtime";var Kr=({labels:e,row:t,single:n,selection:r,renderCard:o})=>{let[a,l]=Qr(!1),i=o(t),u=!n||a,s=r?.selected.has(t.id)??!1;return ot("article",{className:"enrol_apply-rview-card"+(s?" enrol_apply-rview-card--selected":""),role:"listitem","data-record":t.id,children:[ot("div",{className:"enrol_apply-rview-card-l1",children:[r?we(Gt,{selection:r,row:t}):null,i.head]}),i.lines,i.fields.length>0?ot("div",{className:"enrol_apply-rview-card-more",children:[n?we("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":a,onClick:()=>l(!a),children:a?e.less:e.more}):null,u?we("dl",{className:"enrol_apply-rview-card-list",children:i.fields.map(g=>ot("div",{className:"enrol_apply-rview-card-item",children:[we("dt",{children:g.title}),we("dd",{children:g.content})]},g.key))}):null]}):null]})},Et=({labels:e,rows:t,single:n,selection:r,renderCard:o})=>we("div",{className:"enrol_apply-rview-cards"+(n?" enrol_apply-rview-cards--single":""),role:"list",children:t.map(a=>we(Kr,{labels:e,row:a,single:n,selection:r,renderCard:o},a.id))}),_n=({single:e})=>we("div",{className:"enrol_apply-rview-cards"+(e?" enrol_apply-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:e?4:6},(t,n)=>ot("div",{className:"enrol_apply-rview-card enrol_apply-rview-sk",children:[we("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),we("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"}),we("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),we("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"})]},n))});import{Fragment as Nn,jsx as Me,jsxs as Ye}from"react/jsx-runtime";var Gr=({labels:e,loaded:t,error:n,total:r,filtered:o,cards:a,narrow:l,skeleton:i,children:u,onRetry:s})=>{let g=Ye("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",Me("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:s,children:e.retry})]});return!t&&n?g:t?r===0?Ye("div",{className:"enrol_apply-rview-empty",children:[Me("p",{className:"enrol_apply-rview-empty-title",children:o?e.nomatch:e.empty}),o?Me("p",{className:"enrol_apply-rview-empty-text",children:e.nomatchhelp}):null]}):Ye("div",{className:"enrol_apply-rview-dim",children:[n?g:null,u,a?null:Me("p",{className:"enrol_apply-rview-hint",children:e.scrollhint})]}):Ye(Nn,{children:[a?Me(_n,{single:l}):Me(Rn,{...i}),Me("span",{className:"visually-hidden",role:"status",children:e.loading})]})},Mt=e=>Ye(Nn,{children:[Ye("section",{className:"enrol_apply-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"enrol_apply-rows",children:[e.loading&&e.loaded?Me("div",{className:"enrol_apply-rview-progress"}):null,Me(Gr,{...e})]}),e.dock??null]});var zr="table",zt="enrolApplyQueue",Yr=(e,t,n)=>{let r=[];return t.scope!=="all"&&r.push(["status",String(t.scope==="pending"?e.statuses.pending:e.statuses.deferred)]),t.search!==""&&r.push(["search",t.search]),Object.keys(t.filters).sort().forEach(o=>{t.filters[o]!==""&&r.push([o,t.filters[o]])}),n?(t.sort!==""&&(t.sort!=="wait"||t.direction!=="asc")&&r.push(["sort",t.sort],["dir",t.direction]),t.page>0&&r.push(["page",String(t.page)]),t.perpage!==e.perpage&&r.push(["perpage",String(t.perpage)])):r.push(["view",zr]),r},lt=(e,t,n)=>{let r=new URL(e.pageurl,window.location.href);return Yr(e,t,n).forEach(([o,a])=>r.searchParams.set(o,a)),r.toString()},kn=e=>({scope:e.initial.scope,search:e.initial.search,filters:Object.fromEntries(e.initial.filters.map(t=>[t.name,t.value])),sort:e.initial.sort,direction:e.initial.direction,page:e.initial.page,perpage:e.initial.perpage}),xt=(e,t,n)=>{let r=lt(e,t,!0),o={[zt]:t};try{n&&r!==window.location.href?window.history.pushState(o,"",r):window.history.replaceState(o,"",r)}catch{}document.querySelectorAll('[data-enrol-apply-queue="tablelink"]').forEach(a=>{a.href=lt(e,t,!1)})},Sn=e=>e===null||typeof e!="object"||!(zt in e)?null:e[zt];import{useEffect as Lt,useId as oo,useRef as st,useState as Fn}from"react";import{useId as Xr,useState as En}from"react";import{jsx as Cn}from"react/jsx-runtime";var jr={filter:"M2 3h12l-4.5 5.5V13l-3 1.5V8.5L2 3z",columns:"M2 3h12v10H2zM6.5 3v10M10 3v10",table:"M2 3h12v10H2zM2 6.5h12M2 10h12M6 6.5V13",cards:"M2 3h5v4H2zM9 3h5v4H9zM2 9h5v4H2zM9 9h5v4H9z",auto:"M2 3h7v10H2zM11 3h3v4h-3zM11 9h3v4h-3z",download:"M8 2v8M4.5 7 8 10.5 11.5 7M3 13h10",search:"M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM10.5 10.5 14 14",close:"M4 4l8 8M12 4l-8 8",eraser:"M9.5 2 14 6.5 7.5 13 3 8.5zM5.6 5.9l4.5 4.5M7.5 13H14",tick:"M3 8.5 6.5 12 13 4.5",chevron:"M4 6l4 4 4-4",reset:"M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5",queue:"M3 4h10M3 8h10M3 12h6"},H=({name:e,className:t})=>Cn("svg",{className:"enrol_apply-rview-icon"+(t?" "+t:""),viewBox:"0 0 16 16",width:"16",height:"16",fill:"none",stroke:"currentColor",strokeWidth:"1.5",strokeLinecap:"round",strokeLinejoin:"round","aria-hidden":"true",focusable:"false",children:Cn("path",{d:jr[e]??""})});import{Fragment as ro,jsx as B,jsxs as ve}from"react/jsx-runtime";var Zr=8,Yt=50,Jr=(e,t)=>t===null?e.show:R(e.showcount,f(t)),xn=({option:e})=>e.count>=0?B("small",{className:"enrol_apply-rview-optcount",children:f(e.count)}):null,eo=(e,t)=>e.options.filter(n=>n.count!==0||n.value===t),to=({labels:e,facet:t,shown:n,value:r,headid:o,onChange:a})=>{let[l,i]=En(""),[u,s]=En(""),g=l.trim().toLocaleLowerCase(),b=t.groupings.length>1?t.groupings:[],d=b.find(h=>String(h.id)===u)?.groups.map(String)??null,_=n.filter(h=>h.value===r),c=n.filter(h=>h.value!==r&&(g===""||h.label.toLocaleLowerCase().includes(g))&&(d===null||d.includes(h.value))),N=R(t.find.label,f(t.options.length)),M=b.length>0?e.moregroupsgrouping:e.moregroups;return ve(ro,{children:[ve("div",{className:"enrol_apply-rview-find",children:[B("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:N,"aria-label":N,value:l,onChange:h=>i(h.target.value)}),b.length>0?ve("select",{className:"form-select form-select-sm enrol_apply-rview-grouping","aria-label":e.grouping,value:u,onChange:h=>s(h.target.value),children:[B("option",{value:"",children:e.allgroupings}),b.map(h=>B("option",{value:String(h.id),children:h.name},h.id))]}):null]}),ve("div",{className:"enrol_apply-rview-optlist",role:"group","aria-labelledby":o,children:[[..._,...c.slice(0,Yt)].map(h=>ve("button",{type:"button",className:"enrol_apply-rview-optrow","aria-pressed":h.value===r,onClick:()=>a(t.name,h.value===r?"":h.value),children:[B("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:B(H,{name:"tick"})}),B("span",{className:"enrol_apply-rview-optrow-label",children:h.label})," ",B(xn,{option:h})]},h.value)),c.length>Yt?B("p",{className:"enrol_apply-rview-note",children:R(M,f(c.length-Yt))}):null,_.length===0&&c.length===0?B("p",{className:"enrol_apply-rview-note",children:t.find.nomatch}):null]})]})},Mn=({note:e})=>e?B("p",{className:"enrol_apply-rview-note",children:e}):null,no=({labels:e,facet:t,value:n,onChange:r})=>{let o=Xr(),a=eo(t,n),l=[{value:"",label:e.any,count:-1},...a];if(t.options.length>Zr)return ve("div",{className:"enrol_apply-rview-fgroup",children:[B("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),B(to,{labels:e,facet:t,shown:a,value:n,headid:o,onChange:r}),B(Mn,{note:t.note})]});let i=u=>{let s=l.findIndex(b=>b.value===n),g=-1;u.key==="ArrowRight"||u.key==="ArrowDown"?g=(s+1)%l.length:u.key==="ArrowLeft"||u.key==="ArrowUp"?g=(s-1+l.length)%l.length:u.key==="Home"?g=0:u.key==="End"&&(g=l.length-1),!(g<0)&&(u.preventDefault(),r(t.name,l[g].value),u.currentTarget.querySelectorAll('[role="radio"]')[g]?.focus())};return ve("div",{className:"enrol_apply-rview-fgroup",children:[B("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),B("div",{className:"enrol_apply-rview-fpanel-opts",role:"radiogroup","aria-labelledby":o,onKeyDown:i,children:l.map(u=>ve("button",{type:"button",className:"enrol_apply-rview-fpanel-opt",role:"radio","aria-checked":u.value===n,tabIndex:u.value===n?0:-1,onClick:()=>r(t.name,u.value),children:[u.label," ",B(xn,{option:u})]},u.value))}),B(Mn,{note:t.note})]})},Pn=({labels:e,id:t,hidden:n,facets:r,values:o,total:a,note:l,onChange:i,onClear:u,onShow:s})=>ve("div",{className:"enrol_apply-rview-fpanel",id:t,role:"dialog","aria-label":e.filters,hidden:n,children:[ve("div",{className:"enrol_apply-rview-fpanel-body",children:[r.map(g=>B(no,{labels:e,facet:g,value:o[g.name]??"",onChange:i},g.name)),l??null]}),ve("div",{className:"enrol_apply-rview-fpanel-foot",children:[B("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:u,children:e.clearfilters}),B("button",{type:"button",className:"btn btn-sm btn-primary",onClick:s,children:Jr(e,a)})]})]});import{Fragment as jt,jsx as Z,jsxs as Le}from"react/jsx-runtime";var Pt=e=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(e.key))return;let t=Array.from(e.currentTarget.querySelectorAll('[role^="menuitem"]'));if(t.length===0)return;e.preventDefault();let n=t.indexOf(document.activeElement),r=0;e.key==="End"?r=t.length-1:e.key==="ArrowDown"?r=(n+1)%t.length:e.key==="ArrowUp"&&(r=(n-1+t.length)%t.length),t[r].focus()},Ln=({heading:e,sets:t,onSet:n})=>t.length===0?null:Le(jt,{children:[Z("div",{className:"enrol_apply-rview-menu-head",role:"presentation",children:e}),t.map(r=>Le("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":r.active,tabIndex:-1,"data-preset":r.name,onClick:()=>{r.active||n(r.name)},children:[Z("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:Z(H,{name:"tick"})}),Z("span",{className:"enrol_apply-rview-menu-label",children:r.label})]},r.name)),Z("div",{className:"enrol_apply-rview-menu-sep",role:"separator"})]}),Dn=(e,t=[])=>t.find(n=>n.value===e)?.icon??{auto:"auto",table:"table",cards:"cards"}[e],An=e=>{let{labels:t}=e,n=e.extraViews??[],r=[["auto",t.viewauto,t.viewautohint],["table",t.table,""],["cards",t.cards,""]].filter(([o])=>e.views.includes(o));return Le("div",{className:"enrol_apply-rview-menu",id:e.id,role:"menu","aria-label":t.view,hidden:e.hidden,onKeyDown:Pt,children:[r.map(([o,a,l])=>Le("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===o,tabIndex:-1,onClick:()=>e.onChoose(o),children:[Z(H,{name:o}),Z("span",{className:"enrol_apply-rview-menu-label",children:a}),l!==""?Z("span",{className:"enrol_apply-rview-menu-hint",children:l}):null,Z(H,{name:"tick",className:"enrol_apply-rview-tick"})]},o)),n.length>0?Le(jt,{children:[Z("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),n.map(o=>Le("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===o.value,tabIndex:-1,onClick:()=>e.onChoose(o.value),children:[Z(H,{name:o.icon}),Z("span",{className:"enrol_apply-rview-menu-label",children:o.label}),Z(H,{name:"tick",className:"enrol_apply-rview-tick"})]},o.value))]}):null,e.tableurl!==""?Le(jt,{children:[Z("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),Le("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:e.tableurl,children:[Z(H,{name:"table"}),Z("span",{className:"enrol_apply-rview-menu-label",children:t.coretable})]})]}):null]})};import{jsx as Tn}from"react/jsx-runtime";var at=({label:e,value:t,options:n,small:r,onChange:o})=>{let a=l=>{let i=n.findIndex(g=>g.value===t),u=-1;if(l.key==="ArrowRight"||l.key==="ArrowDown"?u=(i+1)%n.length:l.key==="ArrowLeft"||l.key==="ArrowUp"?u=(i-1+n.length)%n.length:l.key==="Home"?u=0:l.key==="End"&&(u=n.length-1),u<0)return;l.preventDefault(),o(n[u].value),l.currentTarget.querySelectorAll('[role="radio"]')[u]?.focus()};return Tn("div",{className:"enrol_apply-rview-seg"+(r?" enrol_apply-rview-seg--small":""),role:"radiogroup","aria-label":e,onKeyDown:a,children:n.map(l=>Tn("button",{type:"button",className:"enrol_apply-rview-seg-cell",role:"radio","aria-checked":l.value===t,tabIndex:l.value===t?0:-1,onClick:()=>o(l.value),children:l.label},l.value))})};import{Fragment as ao,jsx as S,jsxs as $}from"react/jsx-runtime";var lo=(e,t)=>"enrol_apply-rview-bar"+(e?" enrol_apply-rview-bar--narrow":"")+(t?" enrol_apply-rview-bar--searching":""),Dt=e=>{let{labels:t,chips:n,columns:r}=e,o=oo(),a=st(null),l=st(null),i=st(null),u=st(!1),[s,g]=Fn(null),[b,d]=Fn(e.search!==""),_=st({});Lt(()=>{if(s===null)return;let m=D=>{a.current&&!a.current.contains(D.target)&&g(null)};return document.addEventListener("mousedown",m),()=>document.removeEventListener("mousedown",m)},[s]),Lt(()=>{if(s===null||s==="filters")return;let m=a.current?.querySelector(`#${CSS.escape(o+s)}`);(m?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])')??m?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])'))?.focus()},[s,o]),Lt(()=>{b?l.current?.focus():u.current&&(u.current=!1,i.current?.focus())},[b]),Lt(()=>{let m=D=>{if(D.key!=="/"||D.ctrlKey||D.metaKey||D.altKey||D.defaultPrevented)return;let I=document.activeElement;if(I&&(I.isContentEditable||I.closest("input, textarea, select")))return;let x=a.current?.getBoundingClientRect();!x||x.width===0||x.bottom<=0||x.top>=window.innerHeight||(D.preventDefault(),b?l.current?.focus():(g(null),d(!0)))};return document.addEventListener("keydown",m),()=>document.removeEventListener("keydown",m)},[b]);let c=m=>g(s===m?null:m),N=m=>{u.current=m,d(!1)},M=m=>{if(!(m.key!=="Escape"||m.defaultPrevented))if(s!==null){m.preventDefault();let D=_.current[s];g(null),D?.focus()}else b&&(m.preventDefault(),N(!0))},h=e.search.trim().length>=e.searchmin,W=h&&!b,K=n.length+(W?1:0),F=e.total===null?"\u2026":bn(t,"count",e.total,f(e.total)),ge=e.total!==null&&e.narrow?$(ao,{children:[S("span",{"aria-hidden":"true",children:f(e.total)}),S("span",{className:"visually-hidden",children:F})]}):F,G=(m,D,I,x)=>$("button",{type:"button",className:"enrol_apply-rview-iconbtn","aria-label":I,"data-enrol-apply-tip":I,"aria-haspopup":m==="filters"?"dialog":"menu","aria-expanded":s===m,"aria-controls":o+m,ref:j=>{_.current[m??""]=j},onClick:()=>c(m),children:[S(H,{name:D}),x]}),U=Dn(e.view,e.extraViews),J=S(H,{name:"chevron",className:"enrol_apply-rview-chevron"}),w=n.length>0?R(t.filterson,f(n.length)):t.filters,y=n.length>0?S("span",{className:"enrol_apply-rview-badge","aria-hidden":"true",children:f(n.length)}):null;return $("div",{className:lo(e.narrow,b),"data-region":"enrol_apply-bar",ref:a,onKeyDown:M,children:[S(at,{label:t.scope,value:e.scope,options:e.scopes,onChange:e.onScope}),S("span",{className:"enrol_apply-rview-bar-spacer"}),$("div",{className:"enrol_apply-rview-bar-controls",children:[$("div",{className:"enrol_apply-rview-bar-cluster",children:[S("span",{className:"enrol_apply-rview-bar-count","data-region":"enrol_apply-total","aria-live":"polite",children:ge}),$("div",{className:"enrol_apply-rview-capsule",children:[G("filters","filter",w,y),$("div",{className:"enrol_apply-rview-anchor",children:[G("columns","columns",t.columns,J),$("div",{className:"enrol_apply-rview-menu",id:o+"columns",role:"menu","aria-label":t.columns,hidden:s!=="columns",onKeyDown:Pt,children:[S(Ln,{heading:t.presets,sets:e.sets,onSet:e.onSet}),r.map(m=>m.fixed?$("div",{className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":"true","aria-disabled":"true",tabIndex:-1,children:[S("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:S(H,{name:"tick"})}),S("span",{className:"enrol_apply-rview-menu-label",children:m.title}),S("span",{className:"enrol_apply-rview-menu-hint",children:t.always})]},m.key):$("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":m.active,tabIndex:-1,onClick:()=>e.onColumn(m.key,!m.active),children:[S("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:S(H,{name:"tick"})}),S("span",{className:"enrol_apply-rview-menu-label",children:m.title})]},m.key)),S("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),$("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":e.usedefault,onClick:()=>{e.usedefault||e.onDefaultColumns()},children:[S(H,{name:"columns"}),S("span",{className:"enrol_apply-rview-menu-label",children:t.defaultcolumns})]}),$("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":!e.haswidths,onClick:()=>{e.haswidths&&e.onResetWidths()},children:[S(H,{name:"reset"}),S("span",{className:"enrol_apply-rview-menu-label",children:t.resetwidths})]})]})]}),$("div",{className:"enrol_apply-rview-anchor",children:[G("view",U,t.view,J),S(An,{id:o+"view",labels:t,hidden:s!=="view",view:e.view,views:e.views,extraViews:e.extraViews,tableurl:e.tableurl,onChoose:m=>{e.onView(m),g(null),_.current.view?.focus()}})]}),e.downloads.length>0?$("div",{className:"enrol_apply-rview-anchor",children:[G("download","download",t.download,J),S("div",{className:"enrol_apply-rview-menu",id:o+"download",role:"menu","aria-label":t.download,hidden:s!=="download",onKeyDown:Pt,children:e.downloads.map(m=>$("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:m.href,children:[S(H,{name:"download"}),S("span",{className:"enrol_apply-rview-menu-label",children:m.label})]},m.key))})]}):null]})]}),S("div",{className:"enrol_apply-rview-capsule enrol_apply-rview-searchcap",children:b?$("span",{className:"enrol_apply-rview-search",children:[e.searching&&h?S("span",{className:"enrol_apply-rview-spin","aria-hidden":"true"}):S(H,{name:"search"}),S("input",{ref:l,type:"search","aria-label":t.search,placeholder:t.search,autoComplete:"off",value:e.search,onChange:m=>e.onSearch(m.target.value),onKeyDown:m=>{m.key==="Escape"&&(m.preventDefault(),N(!0))},onBlur:()=>{e.search===""&&N(!1)}}),e.search!==""?S("button",{type:"button",className:"enrol_apply-rview-eraser","aria-label":t.cleartext,"data-enrol-apply-tip":t.cleartext,onMouseDown:m=>m.preventDefault(),onClick:()=>{e.onSearch(""),l.current?.focus()},children:S(H,{name:"eraser"})}):null]}):S("button",{ref:i,type:"button",className:"enrol_apply-rview-iconbtn","aria-label":t.search,"data-enrol-apply-tip":t.search,onClick:()=>{g(null),d(!0)},children:S(H,{name:"search"})})}),S(Pn,{labels:t,id:o+"filters",hidden:s!=="filters",facets:e.facets,values:e.values,total:e.total,note:e.panelnote,onChange:e.onFacet,onClear:e.onClearFilters,onShow:()=>{g(null),_.current.filters?.focus()}})]}),$("div",{className:"enrol_apply-rview-chips","data-region":"enrol_apply-chips",hidden:K===0&&!e.aside,children:[n.map(m=>$("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":R(t.removefilter,m.text),onClick:()=>e.onRemoveChip(m.key),children:[S("span",{children:m.text}),S(H,{name:"close"})]},m.key)),W?$("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":R(t.removefilter,R(t.searchchip,e.search.trim())),onClick:()=>e.onSearch(""),children:[S("span",{children:R(t.searchchip,e.search.trim())}),S(H,{name:"close"})]}):null,K>1?S("button",{type:"button",className:"enrol_apply-rview-clearall",onClick:()=>{e.onClearAll(),d(!1)},children:t.clearall}):null,e.aside??null]})]})};import{jsx as Xt,jsxs as mo}from"react/jsx-runtime";var it="all",je="group",so=["auto","table","cards"],io=(e,t)=>{switch(t){case"everything":return e.preseteverything;case"glance":return e.presetglance;case"answers":return e.presetanswers;default:return t}},In=(e,t)=>{switch(t){case"course":return{label:e.searchcourses,nomatch:e.nocoursematch};case"category":return{label:e.searchcategories,nomatch:e.nocategorymatch};default:return{label:e.searchgroups,nomatch:e.nogroupmatch}}},co=(e,t)=>t.omitted<=0?"":R(t.name==="category"?e.omittedcategories:e.omittedcourses,f(t.omitted)),uo=(e,t)=>{let n=t.facets.map(a=>({name:a.name,title:a.title,options:a.options,groupings:[],find:In(e,a.name),note:co(e,a)})),r=In(e,""),o=t.fieldfilters.filter(a=>a.control==="select").map(a=>({name:a.name,title:a.title,options:a.options.map(l=>({...l,count:-1})),groupings:[],find:r}));return[...n,...o]},po=(e,t,n,r)=>{let o=n,a=r,l=t.facets.find(u=>u.name===n),i=t.fieldfilters.find(u=>u.name===n);return l?(o=l.title,a=l.options.find(u=>u.value===r)?.label??r):i?(o=i.title,a=i.options.find(u=>u.value===r)?.label??r):n==="appliedfrom"?o=e.appliedfrom:n==="appliedto"&&(o=e.appliedto),{key:n,text:ce(e.filterchip,{title:o,value:a})}},Vn=e=>{let{labels:t,rows:n,query:r}=e,o=e.presets.map(i=>({name:i.name,label:io(t,i.name),active:i.active})),a=Object.fromEntries(Object.entries(r.filters).map(([i,u])=>[i,i===je&&u===it?"":u])),l=n===null?[]:n.filters.map(i=>po(t,n,i.name,i.value));return Xt(Dt,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"pending",label:t.pending},{value:"deferred",label:t.deferred}],scope:r.scope,total:n===null?null:n.total,facets:n===null?[]:uo(t,n),values:a,panelnote:mo("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",Xt("a",{href:e.tableurl,children:t.morefilters})]}),chips:l,aside:e.reporturl!==""?Xt("a",{className:"enrol_apply-qview-tolink",href:e.reporturl,children:t.openreport}):null,columns:e.columns,sets:o,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:so,tableurl:e.tableurl,downloads:[],search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:i=>e.onScope(i),onSearch:e.onSearch,onFacet:e.onFilter,onRemoveChip:i=>e.onFilter(i,""),onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:i=>{let u=e.presets.find(s=>s.name===i);u&&e.onPreset(u)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as wo,jsx as Hn}from"react/jsx-runtime";var On=(e,t)=>t?e.split(`
`).map(n=>n.trim()).filter(n=>n!==""):[e],At=({parts:e})=>Hn(wo,{children:e.map((t,n)=>Hn("span",{className:n===0?"enrol_apply-rview-c":"enrol_apply-rview-sub",title:Rt(t),dangerouslySetInnerHTML:{__html:t}},n))});import{Fragment as So,jsx as O,jsxs as Xe}from"react/jsx-runtime";var go={applicant:320,course:240,wait:150,group:180,comment:260,details:260,history:130},ho=180,vo={applicant:200},yo=80,fo=800,Tt=Nt(go,ho,vo,yo,fo),bo={deferred:"enrol_apply-status--warning",key:"enrol_apply-status--info",appliedbefore:"enrol_apply-status--neutral",own:"enrol_apply-status--neutral",deciding:"enrol_apply-status--warning",full:"enrol_apply-status--neutral"},$e=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",Zt=({html:e})=>O(At,{parts:[e]}),Bn=({tag:e})=>{let t=bo[e.kind]??"enrol_apply-status--neutral",n=e.title!==""&&e.title!==e.text;return Xe("span",{className:"enrol_apply-status "+t,title:n?e.title:void 0,children:[e.text,n?O("span",{className:"visually-hidden",children:" "+e.title}):null]})},Ro=({row:e})=>e.applicant.profileurl===""?O("span",{className:"enrol_apply-qview-name",children:e.applicant.fullname}):O("a",{className:"enrol_apply-qview-name",href:e.applicant.profileurl,children:e.applicant.fullname}),_o=({labels:e,row:t})=>O("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:t.reviewurl,"aria-label":R(e.reviewapplicant,t.applicant.fullname),children:e.review}),No=({row:e})=>Xe(So,{children:[O("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:e.applicant.picture}}),Xe("div",{className:"enrol_apply-qview-whotext",children:[O("span",{className:"enrol_apply-rview-c",children:O(Ro,{row:e})}),e.applicant.identity.map(t=>O("span",{className:"enrol_apply-rview-sub",title:t.value,children:t.value},t.name)),e.applicant.tags.length>0?O("span",{className:"enrol_apply-qview-tags",children:e.applicant.tags.map(t=>O(Bn,{tag:t},t.kind))}):null]})]}),Jt=({labels:e,row:t})=>Xe("div",{className:"enrol_apply-qview-who",children:[O(No,{row:t}),O(_o,{labels:e,row:t})]}),en=({labels:e,row:t})=>{let n=[];return t.method.deciding&&n.push({kind:"deciding",text:e.grantsdeciding,title:""}),t.method.full&&n.push({kind:"full",text:e.methodfull,title:e.methodfullwarn}),n.length===0?null:O("span",{className:"enrol_apply-qview-tags",children:n.map(r=>O(Bn,{tag:r},r.kind))})},ko=({row:e})=>e.method.queueurl===""?O("span",{children:e.method.name}):O("a",{href:e.method.queueurl,children:e.method.name}),tn=({labels:e,row:t})=>Xe("div",{className:"enrol_apply-qview-course",children:[O("span",{className:"enrol_apply-rview-c",title:t.course.name,children:O("a",{className:"enrol_apply-qview-coursename",href:t.course.url,children:t.course.name})}),Xe("div",{className:"enrol_apply-qview-methodline",children:[O("span",{className:"enrol_apply-rview-sub",title:t.method.name,children:O(ko,{row:t})}),O(en,{labels:e,row:t})]})]});var De=(e,t,n)=>{let r=document.createElement("input");r.type="hidden",r.name=t,r.value=n,e.appendChild(r)},Wn=e=>{let t=document.createElement("form");t.method="post",t.action=e.action,t.hidden=!0,De(t,"sesskey",e.sesskey),De(t,"formaction",e.decision),e.userenrolments.forEach(n=>De(t,"userenrolments[]",String(n))),De(t,"outcomemessage",e.messages===null?e.message:""),Object.entries(e.messages??{}).forEach(([n,r])=>De(t,`outcomemessages[${n}]`,r)),De(t,"decisionnote",e.note),e.decision==="confirm"&&e.messages===null&&(e.groups?.forEach(n=>De(t,"groups[]",String(n))),e.roleid!==null&&De(t,"roleid",String(e.roleid))),document.body.appendChild(t),t.submit()};var $n=(e,t)=>{let n=t.decision!==null&&e!==null&&e.candecide;return{decides:n,notingroup:!!e?.notingroup,narrowed:!!e?.narrowed,viewonly:e!==null&&!n,groupdefault:e?.groupdefault??"",allcolumns:e?.columns??[],usedefault:e?.preference.usedefault??!0,presets:e?.presets??[],total:e?.total??0,list:(e?.rows??[]).map(r=>({...r,id:r.userenrolmentid}))}},qn=(e,t,n,r)=>{let{list:o,decides:a}=n,l=new Set(o.filter(d=>!d.selectable).map(d=>d.id)),i=o.filter(d=>e.has(d.id)),u=(d,_)=>t(c=>{let N=new Set(c);return _&&!l.has(d)?N.add(d):N.delete(d),N}),s=d=>t(()=>d?new Set(o.filter(_=>!l.has(_.id)).map(_=>_.id)):new Set),b=a?{selected:e,locked:l,label:d=>R(r.selectapplicant,d.applicant.fullname),pagelabel:r.selectpage,onSelect:u,onSelectPage:s}:void 0;return{locked:l,chosen:i,selection:b}};import{jsx as nn,jsxs as Un}from"react/jsx-runtime";var Qn=({labels:e,count:t,methods:n,actions:r,onDecide:o})=>Un("div",{className:"enrol_apply-qview-dock",role:"region","aria-label":e.dock,"data-region":"enrol_apply-dock",children:[Un("span",{className:"enrol_apply-qview-dockcount","aria-live":"polite",children:[R(e.selected,f(t)),n>1?nn("small",{className:"enrol_apply-qview-dockmethods",children:" "+R(e.dockmethods,f(n))}):null]}),nn("div",{className:"enrol_apply-qview-dockactions",children:r.map(a=>nn("button",{type:"button",className:"btn "+a.classes,onClick:l=>o(a.value,l.currentTarget),children:a.label},a.value))})]});import{useEffect as Mo,useId as xo,useRef as Yn,useState as qe}from"react";import{Fragment as Eo,jsx as ne,jsxs as Ze}from"react/jsx-runtime";var Kn=5,Gn=e=>{let t=[];return e.forEach(n=>{let r=t.find(o=>o.id===n.method.id);r?r.rows.push(n):t.push({id:n.method.id,rows:[n]})}),t},rn=({labels:e,rows:t})=>{let n=t.length-Kn;return Ze("ul",{className:"enrol_apply-qview-sheetwho",children:[t.slice(0,Kn).map(r=>Ze("li",{children:[ne("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:r.applicant.picture}}),ne("span",{children:r.applicant.fullname})]},r.id)),n>0?ne("li",{className:"enrol_apply-qview-sheetmore",children:R(e.andmore,f(n))}):null]})},Co=({labels:e,id:t,group:n,approval:r,message:o,onMessage:a})=>{let l=n.rows[0],i=n.rows.length;return Ze("section",{className:"enrol_apply-qview-sheetmethod","aria-labelledby":`${t}-head`,children:[Ze("div",{className:"enrol_apply-qview-sheetmethodhead",id:`${t}-head`,children:[ne("strong",{children:l.course.name}),ne("span",{children:" \xB7 "+l.method.name})," ",ne(en,{labels:e,row:l})]}),ne("div",{className:"enrol_apply-qview-sheetline",children:i===1?e.count_one:R(e.count,f(i))}),r&&l.method.full?ne("div",{className:"alert alert-warning",children:e.methodfullwarn}):null,ne(rn,{labels:e,rows:n.rows}),ne("label",{className:"form-label",htmlFor:`${t}-message`,children:e.sheetmessage}),ne("textarea",{className:"form-control",id:`${t}-message`,rows:2,value:o,"aria-describedby":`${t}-head`,onChange:u=>a(n.id,u.target.value)}),l.method.queueurl!==""?Ze("p",{className:"enrol_apply-qview-sheetopen",children:[ne("a",{href:l.method.queueurl,children:e.openmethod})," ",ne("span",{className:"enrol_apply-qview-sheetline",children:e.openmethodline})]}):null]})},zn=({labels:e,id:t,groups:n,approval:r,messages:o,onMessage:a})=>Ze(Eo,{children:[ne("div",{className:"alert alert-info",children:R(e.spans,f(n.length))}),n.map((l,i)=>ne(Co,{labels:e,id:`${t}-method${i}`,group:l,approval:r,message:o[l.id]??"",onMessage:a},l.id))]});import{Fragment as Ft,jsx as T,jsxs as re}from"react/jsx-runtime";var Po="button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), a[href]",Lo=(e,t,n)=>{let r=f(n);return t==="cancel"?[R(n===1?e.sheetcancelone:e.sheetcancel,r),R(e.sheetcancelcommit,r)]:t==="wait"?[R(n===1?e.sheetwaitone:e.sheetwait,r),R(e.sheetwaitcommit,r)]:[R(n===1?e.sheetconfirmone:e.sheetconfirm,r),R(e.sheetconfirmcommit,r)]},on=({id:e,text:t})=>T("div",{className:"form-text",id:e,children:t}),jn=({id:e,label:t,help:n,value:r,last:o,onChange:a})=>re("div",{className:o?"mb-0":"mb-3",children:[T("label",{className:"form-label",htmlFor:e,children:t}),T("textarea",{className:"form-control",id:e,rows:3,value:r,"aria-describedby":`${e}help`,onChange:l=>a(l.target.value)}),T(on,{id:`${e}help`,text:n})]}),Do=({labels:e,decision:t,offer:n})=>re(Ft,{children:[t==="cancel"?T("div",{className:"alert alert-danger",children:e.sheetcanceldanger}):null,t==="wait"?T("p",{className:"enrol_apply-qview-sheetline",children:e.sheetwaitline}):null,t==="confirm"&&n.placesfull!==""?T("div",{className:"alert alert-warning",children:n.placesfull}):null]}),Ao=({id:e,offer:t,groups:n,setGroups:r,roleid:o,setRoleid:a})=>re(Ft,{children:[t.summary?re("div",{className:"enrol_apply-qview-sheetsummary",children:[re("dl",{className:"row mb-0",children:[T("dt",{className:"col-sm-4",children:t.summary.groupslabel}),T("dd",{className:"col-sm-8",children:t.summary.groups}),T("dt",{className:"col-sm-4",children:t.summary.rolelabel}),T("dd",{className:"col-sm-8",children:t.summary.role})]}),re("div",{className:"form-text",children:[t.summary.help,t.summary.note!==""?" "+t.summary.note:""]})]}):null,t.groups?re("div",{className:"mb-3",children:[T("label",{className:"form-label",htmlFor:`${e}-groups`,children:t.groups.label}),T("select",{className:"form-select",id:`${e}-groups`,multiple:!0,value:n,"aria-describedby":`${e}-groupshelp`,onChange:l=>r(Array.from(l.target.selectedOptions,i=>i.value)),children:t.groups.options.map(l=>T("option",{value:String(l.id),children:l.name},l.id))}),T(on,{id:`${e}-groupshelp`,text:t.groups.help})]}):null,t.roles?re("div",{className:"mb-3",children:[T("label",{className:"form-label",htmlFor:`${e}-role`,children:t.roles.label}),re("select",{className:"form-select",id:`${e}-role`,value:o,"aria-describedby":`${e}-rolehelp`,onChange:l=>a(l.target.value),children:[T("option",{value:"0",children:t.roles.default}),t.roles.options.map(l=>T("option",{value:String(l.id),children:l.name},l.id))]}),T(on,{id:`${e}-rolehelp`,text:t.roles.help})]}):null]}),To=(e,t,n,r)=>e.some(o=>o!=="")||t!=="0"||n.length!==r.length||n.some(o=>!r.includes(o)),Fo=(e,t,n)=>{if(!t)return;let r=Array.from(t.querySelectorAll(Po));if(r.length===0){e.preventDefault();return}let o=r[0],a=r[r.length-1],l=document.activeElement;e.shiftKey&&(l===o||l===n)?(e.preventDefault(),a.focus()):!e.shiftKey&&(l===a||l===n)&&(e.preventDefault(),o.focus())},Xn=({labels:e,decision:t,rows:n,offer:r,onClose:o,onCommit:a})=>{let l=xo(),i=Yn(null),u=Yn(null),[s,g]=qe(""),[b,d]=qe({}),[_,c]=qe(""),[N]=qe(()=>(r.groups?.options??[]).filter(x=>x.selected).map(x=>String(x.id))),[M,h]=qe(N),[W,K]=qe("0"),[F,ge]=qe(!1),[G,U]=Lo(e,t,n.length),J=t==="confirm",w=Gn(n),y=w.length>1;Mo(()=>(u.current?.focus(),document.body.classList.add("modal-open"),()=>document.body.classList.remove("modal-open")),[]);let m=x=>{if(x.key==="Escape"){x.preventDefault(),x.stopPropagation(),F||o();return}x.key==="Tab"&&Fo(x,i.current,u.current)},D=()=>{if(F)return;ge(!0);let x=J&&!y;a({message:y?"":s,note:_,messages:y?Object.fromEntries(w.map(j=>[j.id,b[j.id]??""])):null,groups:x&&r.groups?M.map(Number):null,roleid:x&&r.roles?Number(W):null})},I=To([s,_,...Object.values(b)],W,M,N);return re(Ft,{children:[T("div",{className:"modal-backdrop show"}),T("div",{className:"modal show d-block enrol_apply-qview-sheet",role:"presentation",onMouseDown:x=>{x.target===x.currentTarget&&!F&&!I&&o()},children:T("div",{className:"modal-dialog modal-lg modal-dialog-scrollable",children:re("div",{className:"modal-content",role:"dialog","aria-modal":"true","aria-labelledby":`${l}-title`,tabIndex:-1,ref:i,onKeyDown:m,"data-region":"enrol_apply-sheet",children:[re("div",{className:"modal-header",children:[T("h5",{className:"modal-title",id:`${l}-title`,tabIndex:-1,ref:u,children:G}),T("button",{type:"button",className:"btn-close","aria-label":e.close,onClick:o,disabled:F})]}),re("div",{className:"modal-body",children:[T(Do,{labels:e,decision:t,offer:r}),y?T(zn,{labels:e,id:l,groups:w,approval:J,messages:b,onMessage:(x,j)=>d(ee=>({...ee,[x]:j}))}):re(Ft,{children:[T(rn,{labels:e,rows:n}),J?T(Ao,{id:l,offer:r,groups:M,setGroups:h,roleid:W,setRoleid:K}):null,T(jn,{id:`${l}-message`,label:r.message.label,help:r.message.help,value:s,last:!1,onChange:g})]}),T(jn,{id:`${l}-note`,label:r.note.label,help:r.note.help,value:_,last:!0,onChange:c})]}),re("div",{className:"modal-footer",children:[T("button",{type:"button",className:"btn btn-secondary",onClick:o,disabled:F,children:e.back}),T("button",{type:"button",className:"btn "+(t==="cancel"?"btn-danger":"btn-primary"),onClick:D,disabled:F,children:U})]})]})})})]})};import{Fragment as nr,jsx as Y,jsxs as rr}from"react/jsx-runtime";var Io=["applicant","course","wait","group"],Vo=(e,t)=>e.filter(n=>!Io.includes(n.key)&&$e(t,n.key).trim()!=="").map(n=>({key:n.key,title:n.title,content:Y(Zt,{html:$e(t,n.key)})})),Zn=({labels:e,narrowed:t,viewonly:n})=>rr(nr,{children:[t?Y("p",{className:"enrol_apply-rview-narrowed",children:e.narrowed}):null,n?Y("div",{className:"alert alert-info",role:"status",children:e.viewonly}):null]}),Ho=({labels:e,row:t,column:n})=>n==="course"?Y(tn,{labels:e,row:t}):Y(Zt,{html:$e(t,n)}),Oo=({labels:e,row:t,shown:n})=>{let r=o=>n.some(a=>a.key===o);return rr(nr,{children:[r("course")?Y("div",{className:"enrol_apply-rview-card-line",children:Y(tn,{labels:e,row:t})}):null,r("wait")?Y("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--open",dangerouslySetInnerHTML:{__html:$e(t,"wait")}}):null,r("group")&&$e(t,"group")!==""?Y("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",dangerouslySetInnerHTML:{__html:$e(t,"group")}}):null]})},Jn=e=>{let{labels:t,list:n,shown:r,cards:o,narrow:a,selection:l}=e;return o?Y(Et,{labels:t,rows:n,single:a,selection:l,renderCard:i=>({head:Y(Jt,{labels:t,row:i}),lines:Y(Oo,{labels:t,row:i,shown:r}),fields:Vo(r,i)})}):Y(Ct,{labels:t,columns:r,lead:"applicant",rows:n,widths:e.widths,sizing:Tt,sort:e.sort,direction:e.direction,selection:l,renderLead:i=>Y(Jt,{labels:t,row:i}),renderCell:(i,u)=>Y(Ho,{labels:t,row:i,column:u.key}),onSort:e.onSort,onWidth:e.onWidth,onWidthCommit:e.onWidthCommit})},er=(e,t,n,r,o)=>t!==null&&n&&r.length>0?Y(Qn,{labels:e,count:r.length,methods:new Set(r.map(a=>a.method.id)).size,actions:t.actions,onDecide:o}):null,tr=(e,t,n,r,o,a)=>t!==null&&n!==null&&r.length>0?Y(Xn,{labels:e,decision:n,rows:r,offer:t,onClose:o,onCommit:a}):null;import{fetchOne as ln}from"@moodle/lms/core/ajax";var or=(e,t)=>ln({methodname:"enrol_apply_queue_rows",args:{enrolid:e,scope:t.scope,search:t.search,filters:Object.entries(t.filters).map(([n,r])=>({name:n,value:r})),page:t.page,perpage:t.perpage,sort:t.sort,direction:t.direction}}),lr=e=>ln({methodname:"enrol_apply_queue_summary",args:{enrolid:e}}),ar=(e,t)=>ln({methodname:"enrol_apply_set_queue_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}});import{Fragment as $o,jsx as q,jsxs as Ue}from"react/jsx-runtime";var an=(e,t)=>Math.min(100,Math.max(0,Math.round(e*100/t))),Qe=({label:e,value:t,sub:n,meter:r,warn:o})=>Ue("div",{className:"enrol_apply-rview-tile"+(o?" enrol_apply-rview-tile--warn":""),role:"listitem",children:[q("div",{className:"enrol_apply-rview-tile-label",children:e}),q("div",{className:"enrol_apply-rview-tile-value",children:t}),r!==null?q("span",{className:"enrol_apply-qview-meter"+(o?" enrol_apply-qview-meter--warn":""),"aria-hidden":"true",children:q("span",{style:{width:`${r}%`}})}):null,n!==""?q("div",{className:"enrol_apply-rview-tile-sub",children:n}):null]}),Bo=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.DateTimeFormat(t,{dateStyle:"short"}).format(new Date(e*1e3))}catch{return new Date(e*1e3).toLocaleDateString()}},sr=({labels:e,summary:t})=>{let n=t.awaiting>0?R(e.oldest,We(e,t.oldestwait)):"",r=t.late>0?R(e.late,f(t.late)):"";return q(Qe,{label:e.awaiting,value:f(t.awaiting),sub:[n,r].filter(o=>o!=="").join(" \xB7 "),meter:null,warn:t.late>0})},Wo=({labels:e,summary:t})=>Ue("div",{className:"enrol_apply-rview-band",role:"list","data-region":"enrol_apply-tiles",children:[q(sr,{labels:e,summary:t}),q(Qe,{label:e.deferred,value:f(t.deferred),sub:t.awaiting>0?R(e.deferredshare,f(an(t.deferred,t.awaiting))):"",meter:null,warn:!1}),t.sitefigures?Ue($o,{children:[q(Qe,{label:e.courses,value:f(t.courses),sub:t.methods===1?e.methods_one:R(e.methods,f(t.methods)),meter:null,warn:!1}),q(Qe,{label:e.methodsfull,value:f(t.methodsfull),sub:e.methodsfullsub,meter:null,warn:t.methodsfull>0})]}):null]}),ir=({labels:e,kind:t,summary:n,error:r,onRetry:o})=>{if(n===null&&r)return Ue("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",q("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:o,children:e.retry})]});if(n===null)return Ue("div",{className:"enrol_apply-rview-band","aria-busy":"true",children:[Array.from({length:4},(l,i)=>Ue("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[q("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),q("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"})]},i)),q("span",{className:"visually-hidden",role:"status",children:e.loadsummary})]});if(t!=="method")return q(Wo,{labels:e,summary:n});let a=n.narrowed?e.methodnote:"";return Ue("div",{className:"enrol_apply-rview-band",role:"list","data-region":"enrol_apply-tiles",children:[n.places>0?q(Qe,{label:e.placestaken,value:ce(e.ofmany,{taken:f(n.placestaken),total:f(n.places)}),sub:a,meter:an(n.placestaken,n.places),warn:!1}):null,n.applicantlimit>0?q(Qe,{label:e.held,value:ce(e.ofmany,{taken:f(n.applicants),total:f(n.applicantlimit)}),sub:a,meter:an(n.applicants,n.applicantlimit),warn:n.applicants*5>=n.applicantlimit*4}):null,q(sr,{labels:e,summary:n}),q(Qe,{label:e.status,value:n.open?e.open:e.closed,sub:n.open&&n.closes>0?R(e.closes,Bo(n.closes)):"",meter:null,warn:!1})]})};import{Fragment as jo,jsx as Ke,jsxs as ur}from"react/jsx-runtime";var qo=300,Uo=500,cr="enrol_apply_queue_view",Qo={method:["applicant","wait","group","comment"],site:["applicant","course","wait","details"],mentees:["applicant","course","wait","comment"]},Ko="method",Go="course",zo=e=>e.scope!=="all"||e.search!==""||Object.entries(e.filters).some(([t,n])=>n!==""&&!(t===je&&n===it)),Yo=e=>{let t=oe(e).current,{labels:n,enrolid:r}=t,o=oe(null),a=yt(o),[l,i]=de(()=>ft(cr)),[u]=de(()=>kn(t)),s=oe(u),[g,b]=de(u),[d,_]=de(u.search),[c,N]=de(null),[M,h]=de(!0),[W,K]=de(!1),[F,ge]=de(null),[G,U]=de(!0),[J,w]=de(!1),[y,m]=de({}),D=oe(0),I=oe(0),x=oe(null),j=oe(null),ee=oe(null),te=oe(null),be=oe(!1),X=oe(null),Fe=oe(null),Re=oe(null),ke=oe(()=>{}),[et,Se]=de(()=>new Set),[me,xe]=de(null),ae=oe(null),_e=sn(v=>{let L=++D.current;x.current||(x.current=ue("enrol_apply/queue:rows")),h(!0),or(r,v).then(C=>{L===D.current&&(be.current||(be.current=!0,m(Object.fromEntries(C.preference.widths.map(z=>[z.key,z.width])))),C.page!==s.current.page&&(s.current={...s.current,page:C.page},b(s.current),xt(t,s.current,!1)),N(C),Se(z=>{let Fr=new Set(C.rows.filter(rt=>rt.selectable).map(rt=>rt.userenrolmentid)),fn=Array.from(z).filter(rt=>Fr.has(rt));return fn.length===z.size?z:new Set(fn)}),K(!1),h(!1))}).catch(()=>{L===D.current&&(K(!0),h(!1))})},[r,t]),tt=sn(()=>{let v=++I.current;j.current||(j.current=ue("enrol_apply/queue:summary")),U(!0),lr(r).then(L=>{v===I.current&&(ge(L),w(!1),U(!1))}).catch(()=>{v===I.current&&(w(!0),U(!1))})},[r]),se=sn((v,L)=>{s.current={...s.current,...v},b(s.current),Se(new Set),xt(t,s.current,L),_e(s.current)},[_e,t]);Je(()=>{!M&&x.current&&(x.current(),x.current=null)},[M,c,W]),Je(()=>{!G&&j.current&&(j.current(),j.current=null)},[G,F,J]),Je(()=>{xt(t,s.current,!1),_e(s.current),tt()},[_e,tt,t]),Je(()=>{let v=L=>{let C=Sn(L.state);C!==null&&(s.current=C,b(C),_(C.search),Se(new Set),_e(C))};return window.addEventListener("popstate",v),()=>window.removeEventListener("popstate",v)},[_e]),Je(()=>()=>{ee.current!==null&&window.clearTimeout(ee.current),te.current?.(),ke.current()},[]);let Ie=v=>{i(v),bt(cr,v)},Ve=v=>{_(v),ee.current!==null&&(window.clearTimeout(ee.current),ee.current=null);let L=v.trim(),C=L.length>=t.searchmin?L:"";if(C===s.current.search){te.current?.(),te.current=null;return}if(v===""){te.current?.(),te.current=null,se({search:"",page:0},!1);return}te.current||(te.current=ue("enrol_apply/queue:search")),ee.current=window.setTimeout(()=>{ee.current=null,se({search:C,page:0},!1),te.current?.(),te.current=null},qo)},ie=$n(c,t),{groupdefault:He,allcolumns:Ce,usedefault:Ne,list:qt,decides:mt}=ie,Ut=(v,L)=>{let C={...s.current.filters};L!==""?C[v]=L:v===je&&He!==""?C[je]=it:delete C[v],v===Go&&delete C[Ko],se({filters:C,page:0},!0)},wt=()=>He!==""?{[je]:it}:{},Oe=(v,L)=>{let C=ue("enrol_apply/queue:preference");ar(r,v).then(()=>{L&&_e(s.current)}).catch(()=>{L&&K(!0)}).finally(C)},Ge=Ce.filter(v=>!v.fixed&&v.active).map(v=>v.key),ze=v=>Object.entries(v).map(([L,C])=>({key:L,width:C})),gt=(v,L)=>{let C=L?[...Ge,v]:Ge.filter(z=>z!==v);c&&N({...c,columns:c.columns.map(z=>z.key===v?{...z,active:L}:z),preference:{...c.preference,usedefault:!1,columns:C}}),Oe({usedefault:!1,columns:C,widths:ze(y)},!0)},ht=v=>{let L=new Set(v.columns);c&&N({...c,columns:c.columns.map(C=>C.fixed?C:{...C,active:L.has(C.key)||L.has("answers")&&C.key.startsWith("answer:")}),presets:c.presets.map(C=>({...C,active:C.name===v.name})),preference:{...c.preference,usedefault:!1,columns:v.columns}}),Oe({usedefault:!1,columns:v.columns,widths:ze(y)},!0)},nt=()=>{X.current!==null&&(window.clearTimeout(X.current),X.current=null);let v=Re.current;Re.current=null,v!==null&&Oe({usedefault:Ne,columns:Ne?[]:Ge,widths:ze(v)},!1),Fe.current?.(),Fe.current=null};ke.current=nt;let vt=(v,L,C=!1)=>{let z={...y};if(L===null?delete z[v]:z[v]=L,m(z),Re.current=z,!C){nt();return}X.current!==null&&window.clearTimeout(X.current),Fe.current||(Fe.current=ue("enrol_apply/queue:width")),X.current=window.setTimeout(()=>ke.current(),Uo)},Qt=v=>{let L=c!==null&&c.sort===v&&c.direction==="asc"?"desc":"asc";se({sort:v,direction:L,page:0},!0)},p=Ce.filter(v=>v.active),P=l==="cards"||l==="auto"&&a;Je(()=>{me===null&&ae.current&&(ae.current.focus(),ae.current=null)},[me]);let E=t.decision,{chosen:V,selection:Be}=qn(et,Se,ie,n),Lr=(v,L)=>{ae.current=L,xe(v)},Dr=v=>{E===null||me===null||Wn({action:lt(t,s.current,!0),sesskey:E.sesskey,decision:me,userenrolments:V.map(L=>L.id),...v})},Ar=c===null?null:Ke(Jn,{labels:n,list:qt,shown:p,cards:P,narrow:a,widths:y,sort:c.sort,direction:c.direction,selection:Be,onSort:Qt,onWidth:vt,onWidthCommit:()=>ke.current()}),Tr=Qo[t.kind].map(v=>({key:v,title:v==="applicant"?n.applicant:"",sortable:!1,default:!0,active:!0,fixed:v==="applicant"}));return ur("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-queueview",children:[Ke(ir,{labels:n,kind:t.kind,summary:F,error:J,onRetry:tt}),ie.notingroup?Ke("p",{className:"enrol_apply-rview-narrowed",children:n.notingroup}):ur(jo,{children:[Ke(Zn,{labels:n,narrowed:ie.narrowed,viewonly:ie.viewonly}),Ke(Vn,{labels:n,narrow:a,query:g,rows:c,columns:Ce,presets:ie.presets,usedefault:Ne,haswidths:Object.keys(y).length>0,view:l,search:d,searchmin:t.searchmin,searching:M,tableurl:lt(t,g,!1),reporturl:t.reporturl,onScope:v=>se({scope:v,page:0},!0),onSearch:Ve,onFilter:Ut,onClearFilters:()=>se({filters:wt(),page:0},!0),onClearAll:()=>{_(""),se({filters:wt(),search:"",page:0},!0)},onColumn:gt,onPreset:ht,onDefaultColumns:()=>{c&&N({...c,preference:{...c.preference,usedefault:!0,columns:[]}}),Oe({usedefault:!0,columns:[],widths:ze(y)},!0)},onResetWidths:()=>{m({}),Oe({usedefault:Ne,columns:Ne?[]:Ge,widths:[]},!1)},onView:Ie}),Ke(Mt,{labels:n,loading:M,loaded:c!==null,error:W,total:ie.total,filtered:zo(g),cards:P,narrow:a,skeleton:{columns:Tr,lead:"applicant",widths:y,sizing:Tt},dock:er(n,E,mt,V,Lr),onRetry:()=>_e(s.current),children:Ar}),c!==null&&c.total>0?Ke(_t,{labels:n,page:g.page,perpage:g.perpage,perpages:t.perpages,total:c.total,shown:c.rows.length,narrow:a,onPage:v=>se({page:v},!0),onPerpage:v=>se({perpage:v,page:0},!0)}):null]}),tr(n,E,me,V,()=>xe(null),Dr)]})},dr=Yo;import{useCallback as vn,useEffect as $t,useRef as pe,useState as fe}from"react";import{useState as cn}from"react";import{Fragment as dn,jsx as k,jsxs as Q}from"react/jsx-runtime";var un=8,pr=7,It=({label:e,value:t,sub:n,warn:r,link:o})=>Q("div",{className:"enrol_apply-rview-tile"+(r?" enrol_apply-rview-tile--warn":""),children:[k("div",{className:"enrol_apply-rview-tile-label",children:e}),k("div",{className:"enrol_apply-rview-tile-value",children:t}),k("div",{className:"enrol_apply-rview-tile-sub",children:n}),o??null]}),mr=({label:e,share:t,figure:n,warn:r})=>Q("div",{className:"enrol_apply-rview-hrow",role:"listitem",children:[k("span",{className:"enrol_apply-rview-hrow-label",title:e,children:e}),k("span",{className:"enrol_apply-rview-track","aria-hidden":"true",children:k("span",{className:"enrol_apply-rview-fill"+(r?" enrol_apply-rview-fill--late":""),style:{width:`${Math.round(Math.min(1,Math.max(0,t))*100)}%`}})}),k("span",{className:"enrol_apply-rview-hrow-n",children:n})]}),Xo=({compact:e})=>Q("div",{"aria-hidden":"true",children:[k("div",{className:"enrol_apply-rview-band",children:Array.from({length:e?3:4},(t,n)=>Q("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"}),k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"})]},n))}),e?null:k("div",{className:"enrol_apply-rview-dist",children:[0,1].map(t=>Q("div",{className:"enrol_apply-rview-panel enrol_apply-rview-sk",children:[k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"}),k("span",{className:"enrol_apply-rview-skb"}),k("span",{className:"enrol_apply-rview-skb"}),k("span",{className:"enrol_apply-rview-skb"})]},t))})]}),Zo=({labels:e,bymethod:t,bygroup:n,methods:r})=>{let[o,a]=cn(r>1?"method":"group"),[l,i]=cn(!1),[u,s]=cn(""),g=o==="method"?t:n,b=Math.max(86400,...g.map(h=>h.median??0)),d=h=>k(mr,{label:h.name,share:(h.median??0)/b,figure:`${h.median===null?"\u2013":We(e,h.median)} (${f(h.count)})`,warn:(h.median??0)>pr*86400},h.id),_=[...g].sort((h,W)=>W.count-h.count),c=o==="group"&&g.length>un,N=u.trim().toLocaleLowerCase(),M=[...g].filter(h=>N===""||h.name.toLocaleLowerCase().includes(N)).sort((h,W)=>(W.median??0)-(h.median??0));return Q("div",{className:"enrol_apply-rview-panel",children:[Q("div",{className:"enrol_apply-rview-panel-title",children:[k("span",{children:e.medianby}),r>1?k(at,{label:e.breakdown,small:!0,value:o,options:[{value:"method",label:e.bymethod},{value:"group",label:e.bygroup}],onChange:h=>a(h)}):k("span",{children:e.bygroup})]}),c?Q(dn,{children:[k("div",{className:"enrol_apply-rview-note",children:R(e.topgroups,f(un))}),k("div",{role:"list",children:_.slice(0,un).map(d)}),k("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":l,onClick:()=>i(!l),children:l?e.hide:R(e.allgroups,f(g.length))}),l?Q(dn,{children:[k("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:e.searchgroup,"aria-label":e.searchgroup,value:u,onChange:h=>s(h.target.value)}),k("div",{className:"enrol_apply-rview-scrolllist",role:"list",children:M.length?M.map(d):k("p",{className:"enrol_apply-rview-note",children:e.nogroupmatch})})]}):null]}):k("div",{role:"list",children:_.map(d)})]})},wr=({labels:e,summary:t,loading:n,error:r,compactHint:o,measure:a,methods:l,queueurl:i,pendingstatus:u,onMeasure:s,onRetry:g})=>{let b=Q("div",{className:"enrol_apply-rview-bandhead",children:[k("span",{className:"enrol_apply-rview-bandtitle",children:e.turnaround}),k(at,{label:e.measure,small:!0,value:a,options:[{value:"first",label:e.measurefirst},{value:"resolution",label:e.measureresolution}],onChange:_=>s(_)})]}),d;if(t===null&&r)d=Q("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",k("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:g,children:e.retry})]});else if(t===null)d=Q(dn,{children:[k(Xo,{compact:o}),k("span",{className:"visually-hidden",role:"status",children:e.loadband})]});else{let _=t.measure==="resolution",c=t.median??null,N=t.p90??null,M=t.oldest??null,h=e.nothingopen;M!==null&&(h=R(e.oldest,We(e,M))+(t.late>0?" \xB7 "+R(e.late,f(t.late)):""));let W=i===""?null:k("a",{className:"enrol_apply-rview-tile-link",href:_?i:`${i}&status=${u}`,children:e.openqueue}),K=Math.max(1,...t.buckets.map(F=>F.count));d=Q("div",{className:"enrol_apply-rview-dim",children:[Q("div",{className:"enrol_apply-rview-band",children:[k(It,{label:_?e.medianresolution:e.medianfirst,value:c===null?"\u2013":We(e,c),sub:R(e.mediansub,f(t.decided))}),t.compact?null:k(It,{label:e.p90,value:N===null?"\u2013":We(e,N),sub:e.p90sub}),k(It,{label:e.open,value:f(t.open),sub:h,warn:t.late>0,link:W}),k(It,{label:e.withoutperson,value:f(t.withoutperson),sub:e.withoutpersonsub})]}),t.compact?null:Q("div",{className:"enrol_apply-rview-dist",children:[Q("div",{className:"enrol_apply-rview-panel",children:[Q("div",{className:"enrol_apply-rview-panel-title",children:[k("span",{children:_?e.distresolution:e.distfirst}),k("span",{className:"enrol_apply-rview-note",children:R(e.decisions,f(t.decided))})]}),k("div",{role:"list",children:t.buckets.map((F,ge)=>{let G=f(F.todays??0),U=ce(e.bucket,{from:f(F.fromdays),to:G});return ge===0?U=R(e.bucketfirst,f(F.todays??0)):(F.todays??null)===null&&(U=R(e.bucketlast,f(F.fromdays))),k(mr,{label:U,share:F.count/K,figure:f(F.count),warn:F.fromdays>=pr},F.fromdays)})})]}),k(Zo,{labels:e,bymethod:t.bymethod,bygroup:t.bygroup,methods:l})]})]})}return Q("section",{className:"enrol_apply-rview-region enrol_apply-rview-bandregion","aria-label":e.turnaround,"aria-busy":n,"data-region":"enrol_apply-band",children:[n&&t!==null?k("div",{className:"enrol_apply-rview-progress"}):null,b,d]})};var gr=e=>e.facet!==""&&e.options.length>0,hr=e=>{try{let t=JSON.parse(e.values||"{}");return t!==null&&typeof t=="object"?t:{}}catch{return{}}},Jo=e=>e.reduce((t,n)=>({...t,...hr(n)}),{}),pn=e=>{if(!e.applied)return"";let t=hr(e),n=t[`${e.name}_value`];if(n==null)return"";let r=Number(t[`${e.name}_operator`]??0);if(e.facet==="waited"){let o=Number(n)*Number(t[`${e.name}_unit`]??0)/86400;return r===2&&Number.isFinite(o)?String(o):""}return e.facet==="decider"?r===3?String(n):"":String(n)},vr=(e,t,n)=>{let r=mn(e,t);return t.facet==="waited"?(r[`${t.name}_operator`]=n===""?0:2,r[`${t.name}_value`]=n===""?0:Number(n),r[`${t.name}_unit`]=86400):t.facet==="decider"?(r[`${t.name}_operator`]=n===""?0:3,r[`${t.name}_value`]=n):(r[`${t.name}_operator`]=n===""?0:1,r[`${t.name}_value`]=n),r},mn=(e,t)=>{let n=Jo(e);for(let r of Object.keys(n))r.startsWith(`${t.name}_`)&&delete n[r];return n},yr=e=>{let t=pn(e);return e.facet===""||t===""?"":e.options.find(n=>n.value===t)?.label??(e.facet==="decider"?t:"")};import{jsx as fr,jsxs as ll}from"react/jsx-runtime";var el=["auto","table","cards"],br=(e,t)=>t.facet==="waited"?e.waitedmore:t.facet==="decider"?e.decidedby:t.title,tl=(e,t)=>{switch(t){case"decisions":return e.presetdecisions;case"answers":return e.presetanswers;case"groups":return e.presetgroups;default:return t}},nl=(e,t,n)=>{let r={measure:e};return t!=="all"&&(r.scope=t),n!==""&&(r.search=n),encodeURIComponent(JSON.stringify(r))},rl=(e,t)=>({name:t.name,title:br(e,t),options:t.options,groupings:t.groupings,find:t.facet==="decider"?{label:e.searchdeciders,nomatch:e.nodecidermatch}:{label:e.searchgroups,nomatch:e.nogroupmatch}}),ol=(e,t)=>{let n=yr(t),r=n!==""?br(e,t):t.title;return{key:t.name,text:n===""?r:ce(e.filterchip,{title:r,value:n})}},Rr=e=>{let{labels:t,filters:n}=e,r=e.measure==="resolution"?"submission:resolutiontime":"submission:waittime",o=d=>gr(d)&&(d.facet!=="waited"||d.name===r),a=n.filter(o),l=n.filter(d=>!o(d)),i=d=>n.find(_=>_.name===d),u=e.downloadurl!==""&&e.formats.length>0,s=nl(e.measure,e.scope,e.appliedsearch),g=u?e.formats.map(d=>({key:d.value,label:d.label,href:`${e.downloadurl}&parameters=${s}&download=${encodeURIComponent(d.value)}`})):[],b=e.presets.map(d=>({name:d.name,label:tl(t,d.name),active:d.active}));return fr(Dt,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"awaiting",label:t.awaiting},{value:"decided",label:t.decided}],scope:e.scope,total:e.total,facets:a.map(d=>rl(t,d)),values:Object.fromEntries(a.map(d=>[d.name,pn(d)])),panelnote:l.length>0?ll("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",fr("a",{href:e.tableurl,children:t.morefilters})]}):null,chips:n.filter(d=>d.applied).map(d=>ol(t,d)),columns:e.columns,sets:b,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:el,tableurl:e.tableurl,downloads:g,search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:d=>e.onScope(d),onSearch:e.onSearch,onFacet:(d,_)=>{let c=i(d);c&&e.onFilter(c,_)},onRemoveChip:d=>{let _=i(d);_&&e.onRemoveFilter(_)},onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:d=>{let _=e.presets.find(c=>c.name===d);_&&e.onPreset(_)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as pl,jsx as Vt}from"react/jsx-runtime";var al=["outcome","wait","decided","group"],sl={applicant:260,outcome:180,submitted:132,wait:150,decided:172,group:200,method:170,route:170,comment:240,note:240},il=180,cl={applicant:160,submitted:104,decided:104},ul=80,dl=800,Ht=Nt(sl,il,cl,ul,dl),ye=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",Ot=(e,t)=>On(t,al.includes(e)),ct=({column:e,html:t})=>Vt(At,{parts:Ot(e,t)}),Bt=({row:e,label:t})=>e.reviewurl===""?null:Vt("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:e.reviewurl,children:t}),Wt=({row:e,identity:t})=>Vt(pl,{children:t.map(n=>{let r=ye(e,n.key);return r===""?null:Vt("span",{className:"enrol_apply-rview-sub",title:Rt(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});import{Fragment as _r,jsx as le,jsxs as dt}from"react/jsx-runtime";var ml=["applicant","outcome","submitted","wait","decided","group","method"],ut=({row:e,column:t})=>{let n=Ot(t,ye(e,t))[0]??"";return n===""?null:le("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:n}})},wl=(e,t,n,r)=>{let o=l=>t.some(i=>i.key===l),a=t.filter(l=>!ml.includes(l.key)&&ye(r,l.key).trim()!=="");return{head:dt(_r,{children:[dt("div",{className:"enrol_apply-rview-card-who",children:[le("span",{className:"enrol_apply-rview-card-name",dangerouslySetInnerHTML:{__html:ye(r,"applicant")}}),o("outcome")?le(ut,{row:r,column:"outcome"}):null,le(Wt,{row:r,identity:n})]}),le(Bt,{row:r,label:e.review})]}),lines:dt(_r,{children:[dt("div",{className:"enrol_apply-rview-card-line"+(r.open?" enrol_apply-rview-card-line--open":""),children:[o("wait")?le(ut,{row:r,column:"wait"}):null,o("wait")&&o("submitted")?le("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("submitted")?le(ut,{row:r,column:"submitted"}):null]}),o("decided")?le("div",{className:"enrol_apply-rview-card-line",children:Ot("decided",ye(r,"decided")).map((l,i)=>le("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:l}},i))}):null,o("group")||o("method")?dt("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",children:[o("group")?le(ut,{row:r,column:"group"}):null,o("group")&&o("method")&&ye(r,"group")!==""?le("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("method")?le(ut,{row:r,column:"method"}):null]}):null]}),fields:a.map(l=>({key:l.key,title:l.title,content:le(ct,{column:l.key,html:ye(r,l.key)})}))}},Nr=({labels:e,columns:t,identity:n,rows:r,single:o})=>le(Et,{labels:e,rows:r,single:o,renderCard:a=>wl(e,t,n,a)});import{Fragment as hl,jsx as Ae,jsxs as wn}from"react/jsx-runtime";var gl=({row:e,longest:t})=>!e.open&&!e.decidedbyperson?null:Ae("span",{className:"enrol_apply-rview-waitbar"+(e.open?" enrol_apply-rview-waitbar--open":""),"aria-hidden":"true",children:Ae("span",{style:{width:`${Math.min(100,Math.round(e.wait/t*100))}%`}})}),kr=({labels:e,columns:t,identity:n,rows:r,widths:o,sort:a,direction:l,onSort:i,onWidth:u,onWidthCommit:s})=>{let g=Math.max(86400,...r.filter(b=>b.open||b.decidedbyperson).map(b=>b.wait));return Ae(Ct,{labels:e,columns:t,lead:"applicant",rows:r,widths:o,sizing:Ht,sort:a,direction:l,renderLead:(b,d)=>wn("div",{className:"enrol_apply-rview-applicant",children:[wn("div",{className:"enrol_apply-rview-applicant-text",children:[Ae(ct,{column:d.key,html:ye(b,d.key)}),Ae(Wt,{row:b,identity:n})]}),Ae(Bt,{row:b,label:e.review})]}),renderCell:(b,d)=>wn(hl,{children:[Ae(ct,{column:d.key,html:ye(b,d.key)}),d.key==="wait"?Ae(gl,{row:b,longest:g}):null]}),onSort:i,onWidth:u,onWidthCommit:s})};import{fetchOne as pt}from"@moodle/lms/core/ajax";var Sr=(e,t,n)=>pt({methodname:"enrol_apply_report_rows",args:{enrolid:e,...t,facets:n}}),Cr=(e,t,n,r)=>pt({methodname:"enrol_apply_report_summary",args:{enrolid:e,measure:t,scope:n,search:r}}),Er=(e,t)=>pt({methodname:"enrol_apply_set_report_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}}),gn=(e,t,n)=>pt({methodname:"core_reportbuilder_set_filters",args:{reportid:e,parameters:JSON.stringify({measure:t}),values:JSON.stringify(n)}}),hn=(e,t)=>pt({methodname:"core_reportbuilder_filters_reset",args:{reportid:e,parameters:JSON.stringify({measure:t})}});import{Fragment as Rl,jsx as Te,jsxs as yn}from"react/jsx-runtime";var vl=300,yl=500,Mr="enrol_apply_report_view",fl=["applicant","outcome","submitted","wait","decided","group"],bl=e=>{let{labels:t,enrolid:n,reportid:r}=e,o=pe(null),a=yt(o),[l,i]=fe(()=>ft(Mr)),u={measure:e.measure,scope:"all",search:"",page:0,perpage:e.perpage,sort:"",direction:"desc"},s=pe(u),[g,b]=fe(u),[d,_]=fe(""),[c,N]=fe(null),[M,h]=fe(!0),[W,K]=fe(!1),[F,ge]=fe(null),[G,U]=fe(!0),[J,w]=fe(!1),[y,m]=fe({}),[D,I]=fe({}),x=pe(0),j=pe(0),ee=pe(null),te=pe(null),be=pe(null),X=pe(null),Fe=pe(!1),Re=pe(null),ke=pe(null),et=pe(null),Se=pe(()=>{}),me=vn((p,P)=>{let E=++x.current;ee.current||(ee.current=ue("enrol_apply/report:rows")),h(!0),Sr(n,p,P).then(V=>{E===x.current&&(V.counted&&I(Object.fromEntries(V.filters.map(Be=>[Be.name,Be]))),Fe.current||(Fe.current=!0,m(Object.fromEntries(V.preference.widths.map(Be=>[Be.key,Be.width])))),V.page!==s.current.page&&(s.current={...s.current,page:V.page},b(s.current)),N(V),K(!1),h(!1))}).catch(()=>{E===x.current&&(K(!0),h(!1))})},[n]),xe=vn(p=>{let P=++j.current;te.current||(te.current=ue("enrol_apply/report:band")),U(!0),Cr(n,p.measure,p.scope,p.search).then(E=>{P===j.current&&(ge(E),w(!1),U(!1))}).catch(()=>{P===j.current&&(w(!0),U(!1))})},[n]),ae=vn((p,P,E)=>{s.current={...s.current,...p},b(s.current),P&&me(s.current,E),E&&xe(s.current)},[me,xe]);$t(()=>{!M&&ee.current&&(ee.current(),ee.current=null)},[M,c,W]),$t(()=>{!G&&te.current&&(te.current(),te.current=null)},[G,F,J]),$t(()=>{me(s.current,!0),xe(s.current)},[me,xe]),$t(()=>()=>{be.current!==null&&window.clearTimeout(be.current),X.current?.(),Se.current()},[]);let _e=p=>{i(p),bt(Mr,p)},tt=p=>{_(p),be.current!==null&&(window.clearTimeout(be.current),be.current=null);let P=p.trim(),E=P.length>=e.searchmin?P:"";if(E===s.current.search){X.current?.(),X.current=null;return}if(p===""){X.current?.(),X.current=null,ae({search:"",page:0},!0,!0);return}X.current||(X.current=ue("enrol_apply/report:search")),be.current=window.setTimeout(()=>{be.current=null,ae({search:E,page:0},!0,!0),X.current?.(),X.current=null},vl)},se=p=>{let P=ue("enrol_apply/report:filters");p().then(()=>{ae({page:0},!0,!0)}).catch(()=>{K(!0)}).finally(P)},Ie=(c?.filters??[]).map(p=>D[p.name]?{...p,options:D[p.name].options,groupings:D[p.name].groupings}:p),Ve=(p,P)=>{let E=ue("enrol_apply/report:preference");Er(n,p).then(()=>{P&&me(s.current,!1)}).catch(()=>{P&&K(!0)}).finally(E)},ie=c?.columns??[],He=ie.filter(p=>!p.fixed&&p.active).map(p=>p.key),Ce=c?.preference.usedefault??!0,Ne=p=>Object.entries(p).map(([P,E])=>({key:P,width:E})),qt=(p,P)=>{let E=P?[...He,p]:He.filter(V=>V!==p);c&&N({...c,columns:c.columns.map(V=>V.key===p?{...V,active:P}:V),preference:{...c.preference,usedefault:!1,columns:E}}),Ve({usedefault:!1,columns:E,widths:Ne(y)},!0)},mt=()=>{Re.current!==null&&(window.clearTimeout(Re.current),Re.current=null);let p=et.current;et.current=null,p!==null&&Ve({usedefault:Ce,columns:Ce?[]:He,widths:Ne(p)},!1),ke.current?.(),ke.current=null};Se.current=mt;let Ut=(p,P,E=!1)=>{let V={...y};if(P===null?delete V[p]:V[p]=P,m(V),et.current=V,!E){mt();return}Re.current!==null&&window.clearTimeout(Re.current),ke.current||(ke.current=ue("enrol_apply/report:width")),Re.current=window.setTimeout(()=>Se.current(),yl)},wt=p=>{let P=new Set(p.columns);c&&N({...c,columns:c.columns.map(E=>E.fixed?E:{...E,active:P.has(E.key)||P.has("answers")&&E.key.startsWith("answer:")}),presets:c.presets.map(E=>({...E,active:E.name===p.name})),preference:{...c.preference,usedefault:!1,columns:p.columns}}),Ve({usedefault:!1,columns:p.columns,widths:Ne(y)},!0)},Oe=p=>{let E=(c?.sort??"")===p&&c?.direction==="asc"?"desc":"asc";ae({sort:p,direction:E,page:0},!0,!1)},Ge=p=>{let P=s.current,E=ie.some(V=>V.key==="wait"&&V.active);ae({measure:p},P.scope!=="all"||P.sort==="wait"||E,!0)},ze=Ie.some(p=>p.applied)||g.search!==""||g.scope!=="all",gt=ie.filter(p=>p.key.startsWith("identity:")),ht=ie.filter(p=>p.key==="applicant"||!p.fixed&&p.active),nt=l==="cards"||l==="auto"&&a,vt=null;c!==null&&(vt=nt?Te(Nr,{labels:t,columns:ht,identity:gt,rows:c.rows,single:a}):Te(kr,{labels:t,columns:ht,identity:gt,rows:c.rows,widths:y,sort:c.sort,direction:c.direction,onSort:Oe,onWidth:Ut,onWidthCommit:()=>Se.current()}));let Qt=fl.map(p=>({key:p,title:p==="applicant"?t.applicant:"",sortable:!1,default:!0,active:!0,fixed:p==="applicant"}));return yn("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-reportview",children:[c?.narrowed?Te("p",{className:"enrol_apply-rview-narrowed",children:t.narrowed}):null,Te(wr,{labels:t,summary:F,loading:G,error:J,compactHint:c?.narrowed??!1,measure:g.measure,methods:e.methods,queueurl:e.queueurl,pendingstatus:e.pendingstatus,onMeasure:Ge,onRetry:()=>xe(s.current)}),Te(Rr,{labels:t,narrow:a,scope:g.scope,total:c===null?null:c.total,filters:Ie,columns:ie.filter(p=>!p.key.startsWith("identity:")||p.active),presets:c?.presets??[],usedefault:Ce,haswidths:Object.keys(y).length>0,view:l,measure:g.measure,search:d,appliedsearch:g.search,searchmin:e.searchmin,searching:M,tableurl:e.tableurl,downloadurl:e.downloadurl,formats:e.formats,onScope:p=>ae({scope:p,page:0},!0,!0),onSearch:tt,onFilter:(p,P)=>se(()=>gn(r,s.current.measure,vr(Ie,p,P))),onRemoveFilter:p=>se(()=>gn(r,s.current.measure,mn(Ie,p))),onClearFilters:()=>se(()=>hn(r,s.current.measure)),onClearAll:()=>{_(""),s.current={...s.current,search:""},Ie.some(p=>p.applied)?se(()=>hn(r,s.current.measure)):ae({page:0},!0,!0)},onColumn:qt,onPreset:wt,onDefaultColumns:()=>{c&&N({...c,preference:{...c.preference,usedefault:!0,columns:[]}}),Ve({usedefault:!0,columns:[],widths:Ne(y)},!0)},onResetWidths:()=>{m({}),Ve({usedefault:Ce,columns:Ce?[]:He,widths:[]},!1)},onView:_e}),Te(Mt,{labels:t,loading:M,loaded:c!==null,error:W,total:c?.total??0,filtered:ze,cards:nt,narrow:a,skeleton:{columns:Qt,lead:"applicant",widths:y,sizing:Ht},onRetry:()=>me(s.current,!0),children:vt}),c!==null&&c.total>0?Te(_t,{labels:t,page:g.page,perpage:g.perpage,perpages:e.perpages,total:c.total,shown:c.rows.length,narrow:a,onPage:p=>ae({page:p},!0,!1),onPerpage:p=>ae({perpage:p,page:0},!0,!1)}):null,e.retention!==""?yn("p",{className:"enrol_apply-rview-retention","data-region":"enrol_apply-retention",children:[e.retention,e.retentionurl!==""?yn(Rl,{children:[" ",Te("a",{href:e.retentionurl,children:t.retentionchange})]}):null]}):null]})},xr=bl;import{jsx as Pr}from"react/jsx-runtime";var _l=e=>e.mount===void 0||e.mount==="report"?Pr(xr,{...e}):e.mount==="queue"?Pr(dr,{...e}):null,ei=_l;export{ei as default};
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
 * and a role are chosen.
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
 * The site-wide queue's tiles are its own (SQ-D7, {@link WideTiles}): what awaits, what is deferred and its
 * share of what awaits, and for a site-wide reader the courses (and methods) holding an application and the
 * methods at their limit.
 *
 * @module     enrol_apply/queue/Tiles
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * The approval queue as a data report: the method queue's page (stages 3 and 4 of
 * docs/design/confirm-queue-as-report-plan.md, CQ-D5) and the site-wide queue's (stage W3 of
 * docs/design/site-queue-as-report-plan.md, SQ-D12).
 *
 * One view for both, branching on the listing's kind (the props for the first paint, the rows answer
 * after): the site-wide queue draws its own tiles (SQ-D7), a "Course · method" column (SQ-D2), the course,
 * category, method and grouped facets the service returns (choosing another course releases the method),
 * and a decision sheet that splits a selection spanning methods by method (SQ-D10). Nothing of the
 * "By course" view yet (stage W4).
 *
 * manage.php?id=<method>, and manage.php for a site-wide reader, render the shell
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
