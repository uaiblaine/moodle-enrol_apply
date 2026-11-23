import{useCallback as en,useEffect as ze,useRef as Z,useState as ce}from"react";import{useEffect as Rr,useState as Nr}from"react";var kr=576,wt=e=>{let[t,n]=Nr(!1);return Rr(()=>{let r=e.current;if(!r||typeof ResizeObserver>"u")return;let o=new ResizeObserver(a=>{let l=a[0]?.contentRect.width??r.clientWidth;n(l>0&&l<kr)});return o.observe(r),()=>o.disconnect()},[e]),t},vt=e=>{try{let t=window.localStorage.getItem(e);return t==="table"||t==="cards"?t:"auto"}catch{return"auto"}},yt=(e,t)=>{try{window.localStorage.setItem(e,t)}catch{}};import{useId as Sr}from"react";var N=(e,t)=>(e||"").split("{$a}").join(t),le=(e,t)=>Object.entries(t).reduce((n,[r,o])=>n.split(`{$a->${r}}`).join(o),e||""),mn=(e,t,n,r)=>N(n===1&&e[`${t}_one`]?e[`${t}_one`]:e[t],r),_=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(t).format(e)}catch{return String(e)}},Oe=(e,t)=>{let n=Math.max(0,t);if(n<3600)return N(e.waitminutes,_(Math.max(1,Math.round(n/60))));if(n<86400)return N(e.waithours,_(Math.round(n/3600)));let r=Math.floor(n/86400),o=Math.round((n-r*86400)/3600);return o===24&&(r++,o=0),o>0&&r<10?le(e.waitdayshours,{days:_(r),hours:_(o)}):N(e.waitdays,_(r))},gt=e=>{let t=document.createElement("template");return t.innerHTML=e,(t.content.textContent||"").replace(/\s+/g," ").trim()};import{jsx as Ce,jsxs as Qt}from"react/jsx-runtime";var Cr=(e,t)=>[...new Set([1,e-1,e,e+1,t])].filter(n=>n>=1&&n<=t).sort((n,r)=>n-r),ft=({labels:e,page:t,perpage:n,perpages:r,total:o,shown:a,narrow:l,onPage:d,onPerpage:s})=>{let i=Sr(),v=Math.max(1,Math.ceil(o/n)),b=t+1,u=o>0?t*n+1:0,R=o>0?u+a-1:0,c=[];if(l)c=[Ce("span",{className:"enrol_apply-rview-pager-gap",children:le(e.pageof,{page:_(b),pages:_(v)})},"of")];else{let S=Cr(b,v);S.forEach((x,g)=>{g>0&&x-S[g-1]>1&&c.push(Ce("span",{className:"enrol_apply-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${x}`)),c.push(Ce("button",{type:"button","aria-current":x===b?"page":void 0,onClick:()=>d(x-1),children:_(x)},x))})}return Qt("div",{className:"enrol_apply-rview-foot",children:[Ce("span",{className:"enrol_apply-rview-num","data-region":"enrol_apply-range",children:le(e.range,{from:_(u),to:_(R),total:_(o)})}),v>1?Qt("nav",{className:"enrol_apply-rview-pager","aria-label":e.pages,children:[Ce("button",{type:"button",disabled:b===1,onClick:()=>d(t-1),children:e.previous}),c,Ce("button",{type:"button",disabled:b===v,onClick:()=>d(t+1),children:e.next})]}):null,Qt("span",{className:"enrol_apply-rview-perpage",children:[Ce("label",{htmlFor:i,children:e.perpage}),Ce("select",{id:i,className:"form-select form-select-sm",value:n,onChange:S=>s(Number(S.target.value)),children:r.map(S=>Ce("option",{value:S,children:_(S)},S))})]})]})};var ae=e=>{let t=window.M?.util;t?.js_pending(e);let n=!0;return()=>{n&&(n=!1,t?.js_complete(e))}};import{useState as Lr}from"react";import{useEffect as Er,useRef as _t}from"react";var ht=(e,t,n,r,o)=>({defaultWidth:a=>e[a]??t,minimumWidth:a=>n[a]??r,maximum:o}),ve=(e,t,n)=>Math.min(e.maximum,Math.max(e.minimumWidth(n),t[n]??e.defaultWidth(n)));import{jsx as D,jsxs as xe}from"react/jsx-runtime";var bt=16,Mr={ascending:"\u25B2",descending:"\u25BC",none:""},xr=(e,t)=>e?t==="asc"?"ascending":"descending":"none",Pr=({selection:e,rows:t})=>{let n=_t(null),r=t.filter(l=>!e.locked.has(l.id)),o=r.filter(l=>e.selected.has(l.id)).length,a=r.length>0&&o===r.length;return Er(()=>{n.current&&(n.current.indeterminate=o>0&&!a)},[o,a]),xe("label",{className:"enrol_apply-rview-pickwrap",children:[D("input",{ref:n,type:"checkbox",className:"enrol_apply-rview-pick",checked:a,disabled:r.length===0,onChange:l=>e.onSelectPage(l.target.checked)}),D("span",{className:"visually-hidden",children:e.pagelabel})]})},qt=({selection:e,row:t})=>e.locked.has(t.id)?D("span",{className:"enrol_apply-rview-pickwrap enrol_apply-rview-pick--none"}):xe("label",{className:"enrol_apply-rview-pickwrap",children:[D("input",{type:"checkbox",className:"enrol_apply-rview-pick",checked:e.selected.has(t.id),onChange:n=>e.onSelect(t.id,n.target.checked)}),D("span",{className:"visually-hidden",children:e.label(t)})]}),Rt=({labels:e,columns:t,lead:n,rows:r,widths:o,sizing:a,sort:l,direction:d,selection:s,renderLead:i,renderCell:v,onSort:b,onWidth:u,onWidthCommit:R})=>{let c=_t(null),S=_t(null),x=_t(null),g=t.reduce((y,h)=>y+ve(a,o,h.key),0),V=y=>{y.currentTarget.classList.toggle("is-scrolled",y.currentTarget.scrollLeft>0)},q=(y,h)=>{let m=S.current?.querySelector(`col[data-col="${CSS.escape(y)}"]`);!m||!S.current||(m.style.width=`${h}px`,S.current.style.width=`${g-ve(a,o,y)+h}px`)},F=(y,h)=>Math.round(Math.min(a.maximum,Math.max(a.minimumWidth(y),h))),we=(y,h)=>{if(y.button!==0)return;y.preventDefault(),y.currentTarget.setPointerCapture(y.pointerId),y.currentTarget.classList.add("is-active");let m=ve(a,o,h);x.current={key:h,x:y.clientX,width:m,now:m}},K=y=>{let h=x.current;if(!h)return;let m=document.documentElement.dir==="rtl",w=(y.clientX-h.x)*(m?-1:1),T=F(h.key,h.width+w);T!==h.now&&(q(h.key,T),h.now=T)},H=y=>{let h=x.current;x.current=null,y.currentTarget.classList.remove("is-active"),h&&h.now!==h.width&&u(h.key,h.now)},re=(y,h)=>{let m=document.documentElement.dir==="rtl",w=0;if(y.key==="ArrowRight")w=m?-bt:bt;else if(y.key==="ArrowLeft")w=m?bt:-bt;else if(y.key==="Home"){y.preventDefault(),u(h,null,!0);return}if(w===0)return;y.preventDefault();let T=F(h,ve(a,o,h)+w);T!==ve(a,o,h)&&u(h,T,!0)};return D("div",{className:"enrol_apply-rview-gridframe",ref:c,tabIndex:0,role:"region","aria-label":e.grid,onScroll:V,children:xe("table",{className:"enrol_apply-rview-grid",ref:S,style:{width:`${g}px`},children:[D("caption",{className:"visually-hidden",children:e.caption}),D("colgroup",{children:t.map(y=>D("col",{"data-col":y.key,style:{width:`${ve(a,o,y.key)}px`}},y.key))}),D("thead",{children:D("tr",{children:t.map(y=>{let h=xr(l===y.key,d),m=D("span",{className:"enrol_apply-rview-th-title",children:y.title}),w=y.sortable?xe("button",{type:"button",className:"enrol_apply-rview-sortbtn",onClick:()=>b(y.key),children:[m,D("span",{className:"enrol_apply-rview-arrow","aria-hidden":"true",children:Mr[h]})]}):D("span",{className:"enrol_apply-rview-plain",children:m});return xe("th",{scope:"col",className:y.key===n?"enrol_apply-rview-fixed":void 0,"aria-sort":y.sortable?h:void 0,title:y.title,"data-column":y.key,children:[s&&y.key===n?xe("div",{className:"enrol_apply-rview-lead",children:[D(Pr,{selection:s,rows:r}),w]}):w,D("span",{className:"enrol_apply-rview-resize",role:"separator","aria-orientation":"vertical","aria-label":N(e.resize,y.title),"aria-valuemin":a.minimumWidth(y.key),"aria-valuemax":a.maximum,"aria-valuenow":ve(a,o,y.key),tabIndex:0,onPointerDown:T=>we(T,y.key),onPointerMove:K,onPointerUp:H,onPointerCancel:H,onDoubleClick:()=>{ve(a,o,y.key)!==a.defaultWidth(y.key)&&u(y.key,null)},onKeyDown:T=>re(T,y.key),onBlur:R})]},y.key)})})}),D("tbody",{children:r.map(y=>D("tr",{"data-record":y.id,className:s?.selected.has(y.id)?"enrol_apply-rview-row--selected":void 0,children:t.map(h=>h.key===n?D("th",{scope:"row",className:"enrol_apply-rview-fixed",children:s?xe("div",{className:"enrol_apply-rview-lead",children:[D(qt,{selection:s,row:y}),i(y,h)]}):i(y,h)},h.key):D("td",{children:v(y,h)},h.key))},y.id))})]})})},wn=({columns:e,lead:t,widths:n,sizing:r})=>{let o=e.reduce((l,d)=>l+ve(r,n,d.key),0),a=["enrol_apply-rview-skb--w80","enrol_apply-rview-skb--w60","enrol_apply-rview-skb--w40"];return D("div",{className:"enrol_apply-rview-gridframe","aria-hidden":"true",children:xe("table",{className:"enrol_apply-rview-grid",style:{width:`${o}px`},children:[D("colgroup",{children:e.map(l=>D("col",{style:{width:`${ve(r,n,l.key)}px`}},l.key))}),D("thead",{children:D("tr",{children:e.map(l=>D("th",{className:l.key===t?"enrol_apply-rview-fixed":void 0,children:D("span",{className:"enrol_apply-rview-plain",children:D("span",{className:"enrol_apply-rview-th-title",children:l.title})})},l.key))})}),D("tbody",{children:Array.from({length:8},(l,d)=>D("tr",{className:"enrol_apply-rview-sk",children:e.map((s,i)=>D("td",{className:s.key===t?"enrol_apply-rview-fixed":void 0,children:D("span",{className:"enrol_apply-rview-skb "+a[(d+i)%3]})},s.key))},d))})]})})};import{jsx as pe,jsxs as Je}from"react/jsx-runtime";var Dr=({labels:e,row:t,single:n,selection:r,renderCard:o})=>{let[a,l]=Lr(!1),d=o(t),s=!n||a,i=r?.selected.has(t.id)??!1;return Je("article",{className:"enrol_apply-rview-card"+(i?" enrol_apply-rview-card--selected":""),role:"listitem","data-record":t.id,children:[Je("div",{className:"enrol_apply-rview-card-l1",children:[r?pe(qt,{selection:r,row:t}):null,d.head]}),d.lines,d.fields.length>0?Je("div",{className:"enrol_apply-rview-card-more",children:[n?pe("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":a,onClick:()=>l(!a),children:a?e.less:e.more}):null,s?pe("dl",{className:"enrol_apply-rview-card-list",children:d.fields.map(v=>Je("div",{className:"enrol_apply-rview-card-item",children:[pe("dt",{children:v.title}),pe("dd",{children:v.content})]},v.key))}):null]}):null]})},Nt=({labels:e,rows:t,single:n,selection:r,renderCard:o})=>pe("div",{className:"enrol_apply-rview-cards"+(n?" enrol_apply-rview-cards--single":""),role:"list",children:t.map(a=>pe(Dr,{labels:e,row:a,single:n,selection:r,renderCard:o},a.id))}),vn=({single:e})=>pe("div",{className:"enrol_apply-rview-cards"+(e?" enrol_apply-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:e?4:6},(t,n)=>Je("div",{className:"enrol_apply-rview-card enrol_apply-rview-sk",children:[pe("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),pe("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"}),pe("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),pe("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"})]},n))});import{Fragment as yn,jsx as Ee,jsxs as qe}from"react/jsx-runtime";var Ar=({labels:e,loaded:t,error:n,total:r,filtered:o,cards:a,narrow:l,skeleton:d,children:s,onRetry:i})=>{let v=qe("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",Ee("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:i,children:e.retry})]});return!t&&n?v:t?r===0?qe("div",{className:"enrol_apply-rview-empty",children:[Ee("p",{className:"enrol_apply-rview-empty-title",children:o?e.nomatch:e.empty}),o?Ee("p",{className:"enrol_apply-rview-empty-text",children:e.nomatchhelp}):null]}):qe("div",{className:"enrol_apply-rview-dim",children:[n?v:null,s,a?null:Ee("p",{className:"enrol_apply-rview-hint",children:e.scrollhint})]}):qe(yn,{children:[a?Ee(vn,{single:l}):Ee(wn,{...d}),Ee("span",{className:"visually-hidden",role:"status",children:e.loading})]})},kt=e=>qe(yn,{children:[qe("section",{className:"enrol_apply-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"enrol_apply-rows",children:[e.loading&&e.loaded?Ee("div",{className:"enrol_apply-rview-progress"}):null,Ee(Ar,{...e})]}),e.dock??null]});var Tr="table",Kt="enrolApplyQueue",Fr=(e,t,n)=>{let r=[];return t.scope!=="all"&&r.push(["status",String(t.scope==="pending"?e.statuses.pending:e.statuses.deferred)]),t.search!==""&&r.push(["search",t.search]),Object.keys(t.filters).sort().forEach(o=>{t.filters[o]!==""&&r.push([o,t.filters[o]])}),n?(t.sort!==""&&(t.sort!=="wait"||t.direction!=="asc")&&r.push(["sort",t.sort],["dir",t.direction]),t.page>0&&r.push(["page",String(t.page)]),t.perpage!==e.perpage&&r.push(["perpage",String(t.perpage)])):r.push(["view",Tr]),r},et=(e,t,n)=>{let r=new URL(e.pageurl,window.location.href);return Fr(e,t,n).forEach(([o,a])=>r.searchParams.set(o,a)),r.toString()},gn=e=>({scope:e.initial.scope,search:e.initial.search,filters:Object.fromEntries(e.initial.filters.map(t=>[t.name,t.value])),sort:e.initial.sort,direction:e.initial.direction,page:e.initial.page,perpage:e.initial.perpage}),St=(e,t,n)=>{let r=et(e,t,!0),o={[Kt]:t};try{n&&r!==window.location.href?window.history.pushState(o,"",r):window.history.replaceState(o,"",r)}catch{}document.querySelectorAll('[data-enrol-apply-queue="tablelink"]').forEach(a=>{a.href=et(e,t,!1)})},fn=e=>e===null||typeof e!="object"||!(Kt in e)?null:e[Kt];import{useEffect as Et,useId as Qr,useRef as nt,useState as En}from"react";import{useId as Vr,useState as bn}from"react";import{jsx as hn}from"react/jsx-runtime";var Ir={filter:"M2 3h12l-4.5 5.5V13l-3 1.5V8.5L2 3z",columns:"M2 3h12v10H2zM6.5 3v10M10 3v10",table:"M2 3h12v10H2zM2 6.5h12M2 10h12M6 6.5V13",cards:"M2 3h5v4H2zM9 3h5v4H9zM2 9h5v4H2zM9 9h5v4H9z",auto:"M2 3h7v10H2zM11 3h3v4h-3zM11 9h3v4h-3z",download:"M8 2v8M4.5 7 8 10.5 11.5 7M3 13h10",search:"M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM10.5 10.5 14 14",close:"M4 4l8 8M12 4l-8 8",eraser:"M9.5 2 14 6.5 7.5 13 3 8.5zM5.6 5.9l4.5 4.5M7.5 13H14",tick:"M3 8.5 6.5 12 13 4.5",chevron:"M4 6l4 4 4-4",reset:"M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5",queue:"M3 4h10M3 8h10M3 12h6"},O=({name:e,className:t})=>hn("svg",{className:"enrol_apply-rview-icon"+(t?" "+t:""),viewBox:"0 0 16 16",width:"16",height:"16",fill:"none",stroke:"currentColor",strokeWidth:"1.5",strokeLinecap:"round",strokeLinejoin:"round","aria-hidden":"true",focusable:"false",children:hn("path",{d:Ir[e]??""})});import{Fragment as Ur,jsx as U,jsxs as ye}from"react/jsx-runtime";var Hr=8,Gt=50,Or=(e,t)=>t===null?e.show:N(e.showcount,_(t)),_n=({option:e})=>e.count>=0?U("small",{className:"enrol_apply-rview-optcount",children:_(e.count)}):null,Br=(e,t)=>e.options.filter(n=>n.count!==0||n.value===t),Wr=({labels:e,facet:t,shown:n,value:r,headid:o,onChange:a})=>{let[l,d]=bn(""),[s,i]=bn(""),v=l.trim().toLocaleLowerCase(),b=t.groupings.length>1?t.groupings:[],u=b.find(g=>String(g.id)===s)?.groups.map(String)??null,R=n.filter(g=>g.value===r),c=n.filter(g=>g.value!==r&&(v===""||g.label.toLocaleLowerCase().includes(v))&&(u===null||u.includes(g.value))),S=N(t.find.label,_(t.options.length)),x=b.length>0?e.moregroupsgrouping:e.moregroups;return ye(Ur,{children:[ye("div",{className:"enrol_apply-rview-find",children:[U("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:S,"aria-label":S,value:l,onChange:g=>d(g.target.value)}),b.length>0?ye("select",{className:"form-select form-select-sm enrol_apply-rview-grouping","aria-label":e.grouping,value:s,onChange:g=>i(g.target.value),children:[U("option",{value:"",children:e.allgroupings}),b.map(g=>U("option",{value:String(g.id),children:g.name},g.id))]}):null]}),ye("div",{className:"enrol_apply-rview-optlist",role:"group","aria-labelledby":o,children:[[...R,...c.slice(0,Gt)].map(g=>ye("button",{type:"button",className:"enrol_apply-rview-optrow","aria-pressed":g.value===r,onClick:()=>a(t.name,g.value===r?"":g.value),children:[U("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:U(O,{name:"tick"})}),U("span",{className:"enrol_apply-rview-optrow-label",children:g.label})," ",U(_n,{option:g})]},g.value)),c.length>Gt?U("p",{className:"enrol_apply-rview-note",children:N(x,_(c.length-Gt))}):null,R.length===0&&c.length===0?U("p",{className:"enrol_apply-rview-note",children:t.find.nomatch}):null]})]})},$r=({labels:e,facet:t,value:n,onChange:r})=>{let o=Vr(),a=Br(t,n),l=[{value:"",label:e.any,count:-1},...a];if(t.options.length>Hr)return ye("div",{className:"enrol_apply-rview-fgroup",children:[U("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),U(Wr,{labels:e,facet:t,shown:a,value:n,headid:o,onChange:r})]});let d=s=>{let i=l.findIndex(b=>b.value===n),v=-1;s.key==="ArrowRight"||s.key==="ArrowDown"?v=(i+1)%l.length:s.key==="ArrowLeft"||s.key==="ArrowUp"?v=(i-1+l.length)%l.length:s.key==="Home"?v=0:s.key==="End"&&(v=l.length-1),!(v<0)&&(s.preventDefault(),r(t.name,l[v].value),s.currentTarget.querySelectorAll('[role="radio"]')[v]?.focus())};return ye("div",{className:"enrol_apply-rview-fgroup",children:[U("span",{className:"enrol_apply-rview-fpanel-head",id:o,children:t.title}),U("div",{className:"enrol_apply-rview-fpanel-opts",role:"radiogroup","aria-labelledby":o,onKeyDown:d,children:l.map(s=>ye("button",{type:"button",className:"enrol_apply-rview-fpanel-opt",role:"radio","aria-checked":s.value===n,tabIndex:s.value===n?0:-1,onClick:()=>r(t.name,s.value),children:[s.label," ",U(_n,{option:s})]},s.value))})]})},Rn=({labels:e,id:t,hidden:n,facets:r,values:o,total:a,note:l,onChange:d,onClear:s,onShow:i})=>ye("div",{className:"enrol_apply-rview-fpanel",id:t,role:"dialog","aria-label":e.filters,hidden:n,children:[ye("div",{className:"enrol_apply-rview-fpanel-body",children:[r.map(v=>U($r,{labels:e,facet:v,value:o[v.name]??"",onChange:d},v.name)),l??null]}),ye("div",{className:"enrol_apply-rview-fpanel-foot",children:[U("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:s,children:e.clearfilters}),U("button",{type:"button",className:"btn btn-sm btn-primary",onClick:i,children:Or(e,a)})]})]});import{Fragment as zt,jsx as Y,jsxs as Pe}from"react/jsx-runtime";var Ct=e=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(e.key))return;let t=Array.from(e.currentTarget.querySelectorAll('[role^="menuitem"]'));if(t.length===0)return;e.preventDefault();let n=t.indexOf(document.activeElement),r=0;e.key==="End"?r=t.length-1:e.key==="ArrowDown"?r=(n+1)%t.length:e.key==="ArrowUp"&&(r=(n-1+t.length)%t.length),t[r].focus()},Nn=({heading:e,sets:t,onSet:n})=>t.length===0?null:Pe(zt,{children:[Y("div",{className:"enrol_apply-rview-menu-head",role:"presentation",children:e}),t.map(r=>Pe("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":r.active,tabIndex:-1,"data-preset":r.name,onClick:()=>{r.active||n(r.name)},children:[Y("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:Y(O,{name:"tick"})}),Y("span",{className:"enrol_apply-rview-menu-label",children:r.label})]},r.name)),Y("div",{className:"enrol_apply-rview-menu-sep",role:"separator"})]}),kn=(e,t=[])=>t.find(n=>n.value===e)?.icon??{auto:"auto",table:"table",cards:"cards"}[e],Sn=e=>{let{labels:t}=e,n=e.extraViews??[],r=[["auto",t.viewauto,t.viewautohint],["table",t.table,""],["cards",t.cards,""]].filter(([o])=>e.views.includes(o));return Pe("div",{className:"enrol_apply-rview-menu",id:e.id,role:"menu","aria-label":t.view,hidden:e.hidden,onKeyDown:Ct,children:[r.map(([o,a,l])=>Pe("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===o,tabIndex:-1,onClick:()=>e.onChoose(o),children:[Y(O,{name:o}),Y("span",{className:"enrol_apply-rview-menu-label",children:a}),l!==""?Y("span",{className:"enrol_apply-rview-menu-hint",children:l}):null,Y(O,{name:"tick",className:"enrol_apply-rview-tick"})]},o)),n.length>0?Pe(zt,{children:[Y("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),n.map(o=>Pe("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===o.value,tabIndex:-1,onClick:()=>e.onChoose(o.value),children:[Y(O,{name:o.icon}),Y("span",{className:"enrol_apply-rview-menu-label",children:o.label}),Y(O,{name:"tick",className:"enrol_apply-rview-tick"})]},o.value))]}):null,e.tableurl!==""?Pe(zt,{children:[Y("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),Pe("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:e.tableurl,children:[Y(O,{name:"table"}),Y("span",{className:"enrol_apply-rview-menu-label",children:t.coretable})]})]}):null]})};import{jsx as Cn}from"react/jsx-runtime";var tt=({label:e,value:t,options:n,small:r,onChange:o})=>{let a=l=>{let d=n.findIndex(v=>v.value===t),s=-1;if(l.key==="ArrowRight"||l.key==="ArrowDown"?s=(d+1)%n.length:l.key==="ArrowLeft"||l.key==="ArrowUp"?s=(d-1+n.length)%n.length:l.key==="Home"?s=0:l.key==="End"&&(s=n.length-1),s<0)return;l.preventDefault(),o(n[s].value),l.currentTarget.querySelectorAll('[role="radio"]')[s]?.focus()};return Cn("div",{className:"enrol_apply-rview-seg"+(r?" enrol_apply-rview-seg--small":""),role:"radiogroup","aria-label":e,onKeyDown:a,children:n.map(l=>Cn("button",{type:"button",className:"enrol_apply-rview-seg-cell",role:"radio","aria-checked":l.value===t,tabIndex:l.value===t?0:-1,onClick:()=>o(l.value),children:l.label},l.value))})};import{Fragment as Kr,jsx as C,jsxs as W}from"react/jsx-runtime";var qr=(e,t)=>"enrol_apply-rview-bar"+(e?" enrol_apply-rview-bar--narrow":"")+(t?" enrol_apply-rview-bar--searching":""),Mt=e=>{let{labels:t,chips:n,columns:r}=e,o=Qr(),a=nt(null),l=nt(null),d=nt(null),s=nt(!1),[i,v]=En(null),[b,u]=En(e.search!==""),R=nt({});Et(()=>{if(i===null)return;let m=w=>{a.current&&!a.current.contains(w.target)&&v(null)};return document.addEventListener("mousedown",m),()=>document.removeEventListener("mousedown",m)},[i]),Et(()=>{if(i===null||i==="filters")return;let m=a.current?.querySelector(`#${CSS.escape(o+i)}`);(m?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])')??m?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])'))?.focus()},[i,o]),Et(()=>{b?l.current?.focus():s.current&&(s.current=!1,d.current?.focus())},[b]),Et(()=>{let m=w=>{if(w.key!=="/"||w.ctrlKey||w.metaKey||w.altKey||w.defaultPrevented)return;let T=document.activeElement;if(T&&(T.isContentEditable||T.closest("input, textarea, select")))return;let B=a.current?.getBoundingClientRect();!B||B.width===0||B.bottom<=0||B.top>=window.innerHeight||(w.preventDefault(),b?l.current?.focus():(v(null),u(!0)))};return document.addEventListener("keydown",m),()=>document.removeEventListener("keydown",m)},[b]);let c=m=>v(i===m?null:m),S=m=>{s.current=m,u(!1)},x=m=>{if(!(m.key!=="Escape"||m.defaultPrevented))if(i!==null){m.preventDefault();let w=R.current[i];v(null),w?.focus()}else b&&(m.preventDefault(),S(!0))},g=e.search.trim().length>=e.searchmin,V=g&&!b,q=n.length+(V?1:0),F=e.total===null?"\u2026":mn(t,"count",e.total,_(e.total)),we=e.total!==null&&e.narrow?W(Kr,{children:[C("span",{"aria-hidden":"true",children:_(e.total)}),C("span",{className:"visually-hidden",children:F})]}):F,K=(m,w,T,B)=>W("button",{type:"button",className:"enrol_apply-rview-iconbtn","aria-label":T,"data-enrol-apply-tip":T,"aria-haspopup":m==="filters"?"dialog":"menu","aria-expanded":i===m,"aria-controls":o+m,ref:ee=>{R.current[m??""]=ee},onClick:()=>c(m),children:[C(O,{name:w}),B]}),H=kn(e.view,e.extraViews),re=C(O,{name:"chevron",className:"enrol_apply-rview-chevron"}),y=n.length>0?N(t.filterson,_(n.length)):t.filters,h=n.length>0?C("span",{className:"enrol_apply-rview-badge","aria-hidden":"true",children:_(n.length)}):null;return W("div",{className:qr(e.narrow,b),"data-region":"enrol_apply-bar",ref:a,onKeyDown:x,children:[C(tt,{label:t.scope,value:e.scope,options:e.scopes,onChange:e.onScope}),C("span",{className:"enrol_apply-rview-bar-spacer"}),W("div",{className:"enrol_apply-rview-bar-controls",children:[W("div",{className:"enrol_apply-rview-bar-cluster",children:[C("span",{className:"enrol_apply-rview-bar-count","data-region":"enrol_apply-total","aria-live":"polite",children:we}),W("div",{className:"enrol_apply-rview-capsule",children:[K("filters","filter",y,h),W("div",{className:"enrol_apply-rview-anchor",children:[K("columns","columns",t.columns,re),W("div",{className:"enrol_apply-rview-menu",id:o+"columns",role:"menu","aria-label":t.columns,hidden:i!=="columns",onKeyDown:Ct,children:[C(Nn,{heading:t.presets,sets:e.sets,onSet:e.onSet}),r.map(m=>m.fixed?W("div",{className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":"true","aria-disabled":"true",tabIndex:-1,children:[C("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:C(O,{name:"tick"})}),C("span",{className:"enrol_apply-rview-menu-label",children:m.title}),C("span",{className:"enrol_apply-rview-menu-hint",children:t.always})]},m.key):W("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":m.active,tabIndex:-1,onClick:()=>e.onColumn(m.key,!m.active),children:[C("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:C(O,{name:"tick"})}),C("span",{className:"enrol_apply-rview-menu-label",children:m.title})]},m.key)),C("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),W("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":e.usedefault,onClick:()=>{e.usedefault||e.onDefaultColumns()},children:[C(O,{name:"columns"}),C("span",{className:"enrol_apply-rview-menu-label",children:t.defaultcolumns})]}),W("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":!e.haswidths,onClick:()=>{e.haswidths&&e.onResetWidths()},children:[C(O,{name:"reset"}),C("span",{className:"enrol_apply-rview-menu-label",children:t.resetwidths})]})]})]}),W("div",{className:"enrol_apply-rview-anchor",children:[K("view",H,t.view,re),C(Sn,{id:o+"view",labels:t,hidden:i!=="view",view:e.view,views:e.views,extraViews:e.extraViews,tableurl:e.tableurl,onChoose:m=>{e.onView(m),v(null),R.current.view?.focus()}})]}),e.downloads.length>0?W("div",{className:"enrol_apply-rview-anchor",children:[K("download","download",t.download,re),C("div",{className:"enrol_apply-rview-menu",id:o+"download",role:"menu","aria-label":t.download,hidden:i!=="download",onKeyDown:Ct,children:e.downloads.map(m=>W("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:m.href,children:[C(O,{name:"download"}),C("span",{className:"enrol_apply-rview-menu-label",children:m.label})]},m.key))})]}):null]})]}),C("div",{className:"enrol_apply-rview-capsule enrol_apply-rview-searchcap",children:b?W("span",{className:"enrol_apply-rview-search",children:[e.searching&&g?C("span",{className:"enrol_apply-rview-spin","aria-hidden":"true"}):C(O,{name:"search"}),C("input",{ref:l,type:"search","aria-label":t.search,placeholder:t.search,autoComplete:"off",value:e.search,onChange:m=>e.onSearch(m.target.value),onKeyDown:m=>{m.key==="Escape"&&(m.preventDefault(),S(!0))},onBlur:()=>{e.search===""&&S(!1)}}),e.search!==""?C("button",{type:"button",className:"enrol_apply-rview-eraser","aria-label":t.cleartext,"data-enrol-apply-tip":t.cleartext,onMouseDown:m=>m.preventDefault(),onClick:()=>{e.onSearch(""),l.current?.focus()},children:C(O,{name:"eraser"})}):null]}):C("button",{ref:d,type:"button",className:"enrol_apply-rview-iconbtn","aria-label":t.search,"data-enrol-apply-tip":t.search,onClick:()=>{v(null),u(!0)},children:C(O,{name:"search"})})}),C(Rn,{labels:t,id:o+"filters",hidden:i!=="filters",facets:e.facets,values:e.values,total:e.total,note:e.panelnote,onChange:e.onFacet,onClear:e.onClearFilters,onShow:()=>{v(null),R.current.filters?.focus()}})]}),W("div",{className:"enrol_apply-rview-chips","data-region":"enrol_apply-chips",hidden:q===0&&!e.aside,children:[n.map(m=>W("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":N(t.removefilter,m.text),onClick:()=>e.onRemoveChip(m.key),children:[C("span",{children:m.text}),C(O,{name:"close"})]},m.key)),V?W("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":N(t.removefilter,N(t.searchchip,e.search.trim())),onClick:()=>e.onSearch(""),children:[C("span",{children:N(t.searchchip,e.search.trim())}),C(O,{name:"close"})]}):null,q>1?C("button",{type:"button",className:"enrol_apply-rview-clearall",onClick:()=>{e.onClearAll(),u(!1)},children:t.clearall}):null,e.aside??null]})]})};import{jsx as Yt,jsxs as Xr}from"react/jsx-runtime";var rt="all",Ke="group",Gr=["auto","table","cards"],zr=(e,t)=>{switch(t){case"everything":return e.preseteverything;case"glance":return e.presetglance;case"answers":return e.presetanswers;default:return t}},Yr=(e,t)=>{let n={label:e.searchgroups,nomatch:e.nogroupmatch},r=t.facets.map(a=>({name:a.name,title:a.title,options:a.options,groupings:[],find:n})),o=t.fieldfilters.filter(a=>a.control==="select").map(a=>({name:a.name,title:a.title,options:a.options.map(l=>({...l,count:-1})),groupings:[],find:n}));return[...r,...o]},jr=(e,t,n,r)=>{let o=n,a=r,l=t.facets.find(s=>s.name===n),d=t.fieldfilters.find(s=>s.name===n);return l?(o=l.title,a=l.options.find(s=>s.value===r)?.label??r):d?(o=d.title,a=d.options.find(s=>s.value===r)?.label??r):n==="appliedfrom"?o=e.appliedfrom:n==="appliedto"&&(o=e.appliedto),{key:n,text:le(e.filterchip,{title:o,value:a})}},Mn=e=>{let{labels:t,rows:n,query:r}=e,o=e.presets.map(d=>({name:d.name,label:zr(t,d.name),active:d.active})),a=Object.fromEntries(Object.entries(r.filters).map(([d,s])=>[d,d===Ke&&s===rt?"":s])),l=n===null?[]:n.filters.map(d=>jr(t,n,d.name,d.value));return Yt(Mt,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"pending",label:t.pending},{value:"deferred",label:t.deferred}],scope:r.scope,total:n===null?null:n.total,facets:n===null?[]:Yr(t,n),values:a,panelnote:Xr("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",Yt("a",{href:e.tableurl,children:t.morefilters})]}),chips:l,aside:e.reporturl!==""?Yt("a",{className:"enrol_apply-qview-tolink",href:e.reporturl,children:t.openreport}):null,columns:e.columns,sets:o,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:Gr,tableurl:e.tableurl,downloads:[],search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:d=>e.onScope(d),onSearch:e.onSearch,onFacet:e.onFilter,onRemoveChip:d=>e.onFilter(d,""),onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:d=>{let s=e.presets.find(i=>i.name===d);s&&e.onPreset(s)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Zr,jsx as xn}from"react/jsx-runtime";var Pn=(e,t)=>t?e.split(`
`).map(n=>n.trim()).filter(n=>n!==""):[e],xt=({parts:e})=>xn(Zr,{children:e.map((t,n)=>xn("span",{className:n===0?"enrol_apply-rview-c":"enrol_apply-rview-sub",title:gt(t),dangerouslySetInnerHTML:{__html:t}},n))});import{Fragment as co,jsx as se,jsxs as Pt}from"react/jsx-runtime";var Jr={applicant:320,wait:150,group:180,comment:260,history:130},eo=180,to={applicant:200},no=80,ro=800,Lt=ht(Jr,eo,to,no,ro),oo={deferred:"enrol_apply-status--warning",key:"enrol_apply-status--info",appliedbefore:"enrol_apply-status--neutral",own:"enrol_apply-status--neutral"},Be=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",jt=({html:e})=>se(xt,{parts:[e]}),lo=({tag:e})=>{let t=oo[e.kind]??"enrol_apply-status--neutral",n=e.title!==""&&e.title!==e.text;return Pt("span",{className:"enrol_apply-status "+t,title:n?e.title:void 0,children:[e.text,n?se("span",{className:"visually-hidden",children:" "+e.title}):null]})},ao=({row:e})=>e.applicant.profileurl===""?se("span",{className:"enrol_apply-qview-name",children:e.applicant.fullname}):se("a",{className:"enrol_apply-qview-name",href:e.applicant.profileurl,children:e.applicant.fullname}),so=({labels:e,row:t})=>se("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:t.reviewurl,"aria-label":N(e.reviewapplicant,t.applicant.fullname),children:e.review}),io=({row:e})=>Pt(co,{children:[se("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:e.applicant.picture}}),Pt("div",{className:"enrol_apply-qview-whotext",children:[se("span",{className:"enrol_apply-rview-c",children:se(ao,{row:e})}),e.applicant.identity.map(t=>se("span",{className:"enrol_apply-rview-sub",title:t.value,children:t.value},t.name)),e.applicant.tags.length>0?se("span",{className:"enrol_apply-qview-tags",children:e.applicant.tags.map(t=>se(lo,{tag:t},t.kind))}):null]})]}),Xt=({labels:e,row:t})=>Pt("div",{className:"enrol_apply-qview-who",children:[se(io,{row:t}),se(so,{labels:e,row:t})]});var We=(e,t,n)=>{let r=document.createElement("input");r.type="hidden",r.name=t,r.value=n,e.appendChild(r)},Ln=e=>{let t=document.createElement("form");t.method="post",t.action=e.action,t.hidden=!0,We(t,"sesskey",e.sesskey),We(t,"formaction",e.decision),e.userenrolments.forEach(n=>We(t,"userenrolments[]",String(n))),We(t,"outcomemessage",e.message),We(t,"decisionnote",e.note),e.decision==="confirm"&&(e.groups?.forEach(n=>We(t,"groups[]",String(n))),e.roleid!==null&&We(t,"roleid",String(e.roleid))),document.body.appendChild(t),t.submit()};var Dn=(e,t)=>{let n=t.decision!==null&&e!==null&&e.candecide;return{decides:n,notingroup:!!e?.notingroup,narrowed:!!e?.narrowed,viewonly:e!==null&&!n,groupdefault:e?.groupdefault??"",allcolumns:e?.columns??[],usedefault:e?.preference.usedefault??!0,presets:e?.presets??[],total:e?.total??0,list:(e?.rows??[]).map(r=>({...r,id:r.userenrolmentid}))}},An=(e,t,n,r)=>{let{list:o,decides:a}=n,l=new Set(o.filter(u=>!u.selectable).map(u=>u.id)),d=o.filter(u=>e.has(u.id)),s=(u,R)=>t(c=>{let S=new Set(c);return R&&!l.has(u)?S.add(u):S.delete(u),S}),i=u=>t(()=>u?new Set(o.filter(R=>!l.has(R.id)).map(R=>R.id)):new Set),b=a?{selected:e,locked:l,label:u=>N(r.selectapplicant,u.applicant.fullname),pagelabel:r.selectpage,onSelect:s,onSelectPage:i}:void 0;return{locked:l,chosen:d,selection:b}};import{jsx as Zt,jsxs as uo}from"react/jsx-runtime";var Tn=({labels:e,count:t,actions:n,onDecide:r})=>uo("div",{className:"enrol_apply-qview-dock",role:"region","aria-label":e.dock,"data-region":"enrol_apply-dock",children:[Zt("span",{className:"enrol_apply-qview-dockcount","aria-live":"polite",children:N(e.selected,_(t))}),Zt("div",{className:"enrol_apply-qview-dockactions",children:n.map(o=>Zt("button",{type:"button",className:"btn "+o.classes,onClick:a=>r(o.value,a.currentTarget),children:o.label},o.value))})]});import{useEffect as po,useId as mo,useRef as Fn,useState as Ge}from"react";import{Fragment as yo,jsx as A,jsxs as X}from"react/jsx-runtime";var In=5,wo="button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), a[href]",vo=(e,t,n)=>{let r=_(n);return t==="cancel"?[N(n===1?e.sheetcancelone:e.sheetcancel,r),N(e.sheetcancelcommit,r)]:t==="wait"?[N(n===1?e.sheetwaitone:e.sheetwait,r),N(e.sheetwaitcommit,r)]:[N(n===1?e.sheetconfirmone:e.sheetconfirm,r),N(e.sheetconfirmcommit,r)]},Dt=({id:e,text:t})=>A("div",{className:"form-text",id:e,children:t}),Vn=({labels:e,decision:t,rows:n,offer:r,onClose:o,onCommit:a})=>{let l=mo(),d=Fn(null),s=Fn(null),[i,v]=Ge(""),[b,u]=Ge(""),[R]=Ge(()=>(r.groups?.options??[]).filter(w=>w.selected).map(w=>String(w.id))),[c,S]=Ge(R),[x,g]=Ge("0"),[V,q]=Ge(!1),F=n.length,[we,K]=vo(e,t,F),H=t==="confirm";po(()=>(s.current?.focus(),document.body.classList.add("modal-open"),()=>document.body.classList.remove("modal-open")),[]);let re=w=>{if(w.key==="Escape"){w.preventDefault(),w.stopPropagation(),V||o();return}if(w.key!=="Tab"||!d.current)return;let T=Array.from(d.current.querySelectorAll(wo));if(T.length===0){w.preventDefault();return}let B=T[0],ee=T[T.length-1],$=document.activeElement;w.shiftKey&&($===B||$===s.current)?(w.preventDefault(),ee.focus()):!w.shiftKey&&($===ee||$===s.current)&&(w.preventDefault(),B.focus())},y=()=>{V||(q(!0),a({message:i,note:b,groups:H&&r.groups?c.map(Number):null,roleid:H&&r.roles?Number(x):null}))},h=i!==""||b!==""||x!=="0"||c.length!==R.length||c.some(w=>!R.includes(w)),m=F-In;return X(yo,{children:[A("div",{className:"modal-backdrop show"}),A("div",{className:"modal show d-block enrol_apply-qview-sheet",role:"presentation",onMouseDown:w=>{w.target===w.currentTarget&&!V&&!h&&o()},children:A("div",{className:"modal-dialog modal-lg modal-dialog-scrollable",children:X("div",{className:"modal-content",role:"dialog","aria-modal":"true","aria-labelledby":`${l}-title`,tabIndex:-1,ref:d,onKeyDown:re,"data-region":"enrol_apply-sheet",children:[X("div",{className:"modal-header",children:[A("h5",{className:"modal-title",id:`${l}-title`,tabIndex:-1,ref:s,children:we}),A("button",{type:"button",className:"btn-close","aria-label":e.close,onClick:o,disabled:V})]}),X("div",{className:"modal-body",children:[t==="cancel"?A("div",{className:"alert alert-danger",children:e.sheetcanceldanger}):null,t==="wait"?A("p",{className:"enrol_apply-qview-sheetline",children:e.sheetwaitline}):null,H&&r.placesfull!==""?A("div",{className:"alert alert-warning",children:r.placesfull}):null,X("ul",{className:"enrol_apply-qview-sheetwho",children:[n.slice(0,In).map(w=>X("li",{children:[A("span",{className:"enrol_apply-qview-picture","aria-hidden":"true",dangerouslySetInnerHTML:{__html:w.applicant.picture}}),A("span",{children:w.applicant.fullname})]},w.id)),m>0?A("li",{className:"enrol_apply-qview-sheetmore",children:N(e.andmore,_(m))}):null]}),H&&r.summary?X("div",{className:"enrol_apply-qview-sheetsummary",children:[X("dl",{className:"row mb-0",children:[A("dt",{className:"col-sm-4",children:r.summary.groupslabel}),A("dd",{className:"col-sm-8",children:r.summary.groups}),A("dt",{className:"col-sm-4",children:r.summary.rolelabel}),A("dd",{className:"col-sm-8",children:r.summary.role})]}),X("div",{className:"form-text",children:[r.summary.help,r.summary.note!==""?" "+r.summary.note:""]})]}):null,H&&r.groups?X("div",{className:"mb-3",children:[A("label",{className:"form-label",htmlFor:`${l}-groups`,children:r.groups.label}),A("select",{className:"form-select",id:`${l}-groups`,multiple:!0,value:c,"aria-describedby":`${l}-groupshelp`,onChange:w=>S(Array.from(w.target.selectedOptions,T=>T.value)),children:r.groups.options.map(w=>A("option",{value:String(w.id),children:w.name},w.id))}),A(Dt,{id:`${l}-groupshelp`,text:r.groups.help})]}):null,H&&r.roles?X("div",{className:"mb-3",children:[A("label",{className:"form-label",htmlFor:`${l}-role`,children:r.roles.label}),X("select",{className:"form-select",id:`${l}-role`,value:x,"aria-describedby":`${l}-rolehelp`,onChange:w=>g(w.target.value),children:[A("option",{value:"0",children:r.roles.default}),r.roles.options.map(w=>A("option",{value:String(w.id),children:w.name},w.id))]}),A(Dt,{id:`${l}-rolehelp`,text:r.roles.help})]}):null,X("div",{className:"mb-3",children:[A("label",{className:"form-label",htmlFor:`${l}-message`,children:r.message.label}),A("textarea",{className:"form-control",id:`${l}-message`,rows:3,value:i,"aria-describedby":`${l}-messagehelp`,onChange:w=>v(w.target.value)}),A(Dt,{id:`${l}-messagehelp`,text:r.message.help})]}),X("div",{className:"mb-0",children:[A("label",{className:"form-label",htmlFor:`${l}-note`,children:r.note.label}),A("textarea",{className:"form-control",id:`${l}-note`,rows:3,value:b,"aria-describedby":`${l}-notehelp`,onChange:w=>u(w.target.value)}),A(Dt,{id:`${l}-notehelp`,text:r.note.help})]})]}),X("div",{className:"modal-footer",children:[A("button",{type:"button",className:"btn btn-secondary",onClick:o,disabled:V,children:e.back}),A("button",{type:"button",className:"btn "+(t==="cancel"?"btn-danger":"btn-primary"),onClick:y,disabled:V,children:K})]})]})})})]})};import{Fragment as $n,jsx as me,jsxs as Un}from"react/jsx-runtime";var go=["applicant","wait","group"],fo=(e,t)=>e.filter(n=>!go.includes(n.key)&&Be(t,n.key).trim()!=="").map(n=>({key:n.key,title:n.title,content:me(jt,{html:Be(t,n.key)})})),Hn=({labels:e,narrowed:t,viewonly:n})=>Un($n,{children:[t?me("p",{className:"enrol_apply-rview-narrowed",children:e.narrowed}):null,n?me("div",{className:"alert alert-info",role:"status",children:e.viewonly}):null]}),On=e=>{let{labels:t,list:n,shown:r,cards:o,narrow:a,selection:l}=e,d=s=>r.some(i=>i.key===s);return o?me(Nt,{labels:t,rows:n,single:a,selection:l,renderCard:s=>({head:me(Xt,{labels:t,row:s}),lines:Un($n,{children:[d("wait")?me("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--open",dangerouslySetInnerHTML:{__html:Be(s,"wait")}}):null,d("group")&&Be(s,"group")!==""?me("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",dangerouslySetInnerHTML:{__html:Be(s,"group")}}):null]}),fields:fo(r,s)})}):me(Rt,{labels:t,columns:r,lead:"applicant",rows:n,widths:e.widths,sizing:Lt,sort:e.sort,direction:e.direction,selection:l,renderLead:s=>me(Xt,{labels:t,row:s}),renderCell:(s,i)=>me(jt,{html:Be(s,i.key)}),onSort:e.onSort,onWidth:e.onWidth,onWidthCommit:e.onWidthCommit})},Bn=(e,t,n,r,o)=>t!==null&&n&&r>0?me(Tn,{labels:e,count:r,actions:t.actions,onDecide:o}):null,Wn=(e,t,n,r,o,a)=>t!==null&&n!==null&&r.length>0?me(Vn,{labels:e,decision:n,rows:r,offer:t,onClose:o,onCommit:a}):null;import{fetchOne as Jt}from"@moodle/lms/core/ajax";var Qn=(e,t)=>Jt({methodname:"enrol_apply_queue_rows",args:{enrolid:e,scope:t.scope,search:t.search,filters:Object.entries(t.filters).map(([n,r])=>({name:n,value:r})),page:t.page,perpage:t.perpage,sort:t.sort,direction:t.direction}}),qn=e=>Jt({methodname:"enrol_apply_queue_summary",args:{enrolid:e}}),Kn=(e,t)=>Jt({methodname:"enrol_apply_set_queue_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}});import{jsx as ie,jsxs as ot}from"react/jsx-runtime";var Gn=(e,t)=>Math.min(100,Math.max(0,Math.round(e*100/t))),At=({label:e,value:t,sub:n,meter:r,warn:o})=>ot("div",{className:"enrol_apply-rview-tile"+(o?" enrol_apply-rview-tile--warn":""),role:"listitem",children:[ie("div",{className:"enrol_apply-rview-tile-label",children:e}),ie("div",{className:"enrol_apply-rview-tile-value",children:t}),r!==null?ie("span",{className:"enrol_apply-qview-meter"+(o?" enrol_apply-qview-meter--warn":""),"aria-hidden":"true",children:ie("span",{style:{width:`${r}%`}})}):null,n!==""?ie("div",{className:"enrol_apply-rview-tile-sub",children:n}):null]}),ho=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.DateTimeFormat(t,{dateStyle:"short"}).format(new Date(e*1e3))}catch{return new Date(e*1e3).toLocaleDateString()}},zn=({labels:e,summary:t,error:n,onRetry:r})=>{if(t===null&&n)return ot("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",ie("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:r,children:e.retry})]});if(t===null)return ot("div",{className:"enrol_apply-rview-band","aria-busy":"true",children:[Array.from({length:4},(d,s)=>ot("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[ie("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),ie("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"})]},s)),ie("span",{className:"visually-hidden",role:"status",children:e.loadsummary})]});let o=t.narrowed?e.methodnote:"",a=t.awaiting>0?N(e.oldest,Oe(e,t.oldestwait)):"",l=t.late>0?N(e.late,_(t.late)):"";return ot("div",{className:"enrol_apply-rview-band",role:"list","data-region":"enrol_apply-tiles",children:[t.places>0?ie(At,{label:e.placestaken,value:le(e.ofmany,{taken:_(t.placestaken),total:_(t.places)}),sub:o,meter:Gn(t.placestaken,t.places),warn:!1}):null,t.applicantlimit>0?ie(At,{label:e.held,value:le(e.ofmany,{taken:_(t.applicants),total:_(t.applicantlimit)}),sub:o,meter:Gn(t.applicants,t.applicantlimit),warn:t.applicants*5>=t.applicantlimit*4}):null,ie(At,{label:e.awaiting,value:_(t.awaiting),sub:[a,l].filter(d=>d!=="").join(" \xB7 "),meter:null,warn:t.late>0}),ie(At,{label:e.status,value:t.open?e.open:e.closed,sub:t.open&&t.closes>0?N(e.closes,ho(t.closes)):"",meter:null,warn:!1})]})};import{Fragment as So,jsx as $e,jsxs as jn}from"react/jsx-runtime";var bo=300,_o=500,Yn="enrol_apply_queue_view",Ro=["applicant","wait","group","comment"],No=e=>e.scope!=="all"||e.search!==""||Object.entries(e.filters).some(([t,n])=>n!==""&&!(t===Ke&&n===rt)),ko=e=>{let t=Z(e).current,{labels:n,enrolid:r}=t,o=Z(null),a=wt(o),[l,d]=ce(()=>vt(Yn)),[s]=ce(()=>gn(t)),i=Z(s),[v,b]=ce(s),[u,R]=ce(s.search),[c,S]=ce(null),[x,g]=ce(!0),[V,q]=ce(!1),[F,we]=ce(null),[K,H]=ce(!0),[re,y]=ce(!1),[h,m]=ce({}),w=Z(0),T=Z(0),B=Z(null),ee=Z(null),$=Z(null),j=Z(null),he=Z(!1),z=Z(null),Ae=Z(null),be=Z(null),Ne=Z(()=>{}),[Ye,ke]=ce(()=>new Set),[de,Me]=ce(null),te=Z(null),_e=en(f=>{let L=++w.current;B.current||(B.current=ae("enrol_apply/queue:rows")),g(!0),Qn(r,f).then(M=>{L===w.current&&(he.current||(he.current=!0,m(Object.fromEntries(M.preference.widths.map(G=>[G.key,G.width])))),M.page!==i.current.page&&(i.current={...i.current,page:M.page},b(i.current),St(t,i.current,!1)),S(M),ke(G=>{let _r=new Set(M.rows.filter(Ze=>Ze.selectable).map(Ze=>Ze.userenrolmentid)),pn=Array.from(G).filter(Ze=>_r.has(Ze));return pn.length===G.size?G:new Set(pn)}),q(!1),g(!1))}).catch(()=>{L===w.current&&(q(!0),g(!1))})},[r,t]),je=en(()=>{let f=++T.current;ee.current||(ee.current=ae("enrol_apply/queue:summary")),H(!0),qn(r).then(L=>{f===T.current&&(we(L),y(!1),H(!1))}).catch(()=>{f===T.current&&(y(!0),H(!1))})},[r]),ne=en((f,L)=>{i.current={...i.current,...f},b(i.current),ke(new Set),St(t,i.current,L),_e(i.current)},[_e,t]);ze(()=>{!x&&B.current&&(B.current(),B.current=null)},[x,c,V]),ze(()=>{!K&&ee.current&&(ee.current(),ee.current=null)},[K,F,re]),ze(()=>{St(t,i.current,!1),_e(i.current),je()},[_e,je,t]),ze(()=>{let f=L=>{let M=fn(L.state);M!==null&&(i.current=M,b(M),R(M.search),ke(new Set),_e(M))};return window.addEventListener("popstate",f),()=>window.removeEventListener("popstate",f)},[_e]),ze(()=>()=>{$.current!==null&&window.clearTimeout($.current),j.current?.(),Ne.current()},[]);let Te=f=>{d(f),yt(Yn,f)},Fe=f=>{R(f),$.current!==null&&(window.clearTimeout($.current),$.current=null);let L=f.trim(),M=L.length>=t.searchmin?L:"";if(M===i.current.search){j.current?.(),j.current=null;return}if(f===""){j.current?.(),j.current=null,ne({search:"",page:0},!1);return}j.current||(j.current=ae("enrol_apply/queue:search")),$.current=window.setTimeout(()=>{$.current=null,ne({search:M,page:0},!1),j.current?.(),j.current=null},bo)},oe=Dn(c,t),{groupdefault:Ie,allcolumns:Se,usedefault:Re,list:Wt,decides:ct}=oe,$t=(f,L)=>{let M={...i.current.filters};L!==""?M[f]=L:f===Ke&&Ie!==""?M[Ke]=rt:delete M[f],ne({filters:M,page:0},!0)},ut=()=>Ie!==""?{[Ke]:rt}:{},Ve=(f,L)=>{let M=ae("enrol_apply/queue:preference");Kn(r,f).then(()=>{L&&_e(i.current)}).catch(()=>{L&&q(!0)}).finally(M)},Ue=Se.filter(f=>!f.fixed&&f.active).map(f=>f.key),Qe=f=>Object.entries(f).map(([L,M])=>({key:L,width:M})),dt=(f,L)=>{let M=L?[...Ue,f]:Ue.filter(G=>G!==f);c&&S({...c,columns:c.columns.map(G=>G.key===f?{...G,active:L}:G),preference:{...c.preference,usedefault:!1,columns:M}}),Ve({usedefault:!1,columns:M,widths:Qe(h)},!0)},pt=f=>{let L=new Set(f.columns);c&&S({...c,columns:c.columns.map(M=>M.fixed?M:{...M,active:L.has(M.key)||L.has("answers")&&M.key.startsWith("answer:")}),presets:c.presets.map(M=>({...M,active:M.name===f.name})),preference:{...c.preference,usedefault:!1,columns:f.columns}}),Ve({usedefault:!1,columns:f.columns,widths:Qe(h)},!0)},Xe=()=>{z.current!==null&&(window.clearTimeout(z.current),z.current=null);let f=be.current;be.current=null,f!==null&&Ve({usedefault:Re,columns:Re?[]:Ue,widths:Qe(f)},!1),Ae.current?.(),Ae.current=null};Ne.current=Xe;let mt=(f,L,M=!1)=>{let G={...h};if(L===null?delete G[f]:G[f]=L,m(G),be.current=G,!M){Xe();return}z.current!==null&&window.clearTimeout(z.current),Ae.current||(Ae.current=ae("enrol_apply/queue:width")),z.current=window.setTimeout(()=>Ne.current(),_o)},Ut=f=>{let L=c!==null&&c.sort===f&&c.direction==="asc"?"desc":"asc";ne({sort:f,direction:L,page:0},!0)},p=Se.filter(f=>f.active),P=l==="cards"||l==="auto"&&a;ze(()=>{de===null&&te.current&&(te.current.focus(),te.current=null)},[de]);let E=t.decision,{chosen:I,selection:He}=An(Ye,ke,oe,n),gr=(f,L)=>{te.current=L,Me(f)},fr=f=>{E===null||de===null||Ln({action:et(t,i.current,!0),sesskey:E.sesskey,decision:de,userenrolments:I.map(L=>L.id),...f})},hr=c===null?null:$e(On,{labels:n,list:Wt,shown:p,cards:P,narrow:a,widths:h,sort:c.sort,direction:c.direction,selection:He,onSort:Ut,onWidth:mt,onWidthCommit:()=>Ne.current()}),br=Ro.map(f=>({key:f,title:f==="applicant"?n.applicant:"",sortable:!1,default:!0,active:!0,fixed:f==="applicant"}));return jn("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-queueview",children:[$e(zn,{labels:n,summary:F,error:re,onRetry:je}),oe.notingroup?$e("p",{className:"enrol_apply-rview-narrowed",children:n.notingroup}):jn(So,{children:[$e(Hn,{labels:n,narrowed:oe.narrowed,viewonly:oe.viewonly}),$e(Mn,{labels:n,narrow:a,query:v,rows:c,columns:Se,presets:oe.presets,usedefault:Re,haswidths:Object.keys(h).length>0,view:l,search:u,searchmin:t.searchmin,searching:x,tableurl:et(t,v,!1),reporturl:t.reporturl,onScope:f=>ne({scope:f,page:0},!0),onSearch:Fe,onFilter:$t,onClearFilters:()=>ne({filters:ut(),page:0},!0),onClearAll:()=>{R(""),ne({filters:ut(),search:"",page:0},!0)},onColumn:dt,onPreset:pt,onDefaultColumns:()=>{c&&S({...c,preference:{...c.preference,usedefault:!0,columns:[]}}),Ve({usedefault:!0,columns:[],widths:Qe(h)},!0)},onResetWidths:()=>{m({}),Ve({usedefault:Re,columns:Re?[]:Ue,widths:[]},!1)},onView:Te}),$e(kt,{labels:n,loading:x,loaded:c!==null,error:V,total:oe.total,filtered:No(v),cards:P,narrow:a,skeleton:{columns:br,lead:"applicant",widths:h,sizing:Lt},dock:Bn(n,E,ct,I.length,gr),onRetry:()=>_e(i.current),children:hr}),c!==null&&c.total>0?$e(ft,{labels:n,page:v.page,perpage:v.perpage,perpages:t.perpages,total:c.total,shown:c.rows.length,narrow:a,onPage:f=>ne({page:f},!0),onPerpage:f=>ne({perpage:f,page:0},!0)}):null]}),Wn(n,E,de,I,()=>Me(null),fr)]})},Xn=ko;import{useCallback as un,useEffect as Bt,useRef as ue,useState as fe}from"react";import{useState as tn}from"react";import{Fragment as rn,jsx as k,jsxs as Q}from"react/jsx-runtime";var nn=8,Zn=7,Tt=({label:e,value:t,sub:n,warn:r,link:o})=>Q("div",{className:"enrol_apply-rview-tile"+(r?" enrol_apply-rview-tile--warn":""),children:[k("div",{className:"enrol_apply-rview-tile-label",children:e}),k("div",{className:"enrol_apply-rview-tile-value",children:t}),k("div",{className:"enrol_apply-rview-tile-sub",children:n}),o??null]}),Jn=({label:e,share:t,figure:n,warn:r})=>Q("div",{className:"enrol_apply-rview-hrow",role:"listitem",children:[k("span",{className:"enrol_apply-rview-hrow-label",title:e,children:e}),k("span",{className:"enrol_apply-rview-track","aria-hidden":"true",children:k("span",{className:"enrol_apply-rview-fill"+(r?" enrol_apply-rview-fill--late":""),style:{width:`${Math.round(Math.min(1,Math.max(0,t))*100)}%`}})}),k("span",{className:"enrol_apply-rview-hrow-n",children:n})]}),Co=({compact:e})=>Q("div",{"aria-hidden":"true",children:[k("div",{className:"enrol_apply-rview-band",children:Array.from({length:e?3:4},(t,n)=>Q("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"}),k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"})]},n))}),e?null:k("div",{className:"enrol_apply-rview-dist",children:[0,1].map(t=>Q("div",{className:"enrol_apply-rview-panel enrol_apply-rview-sk",children:[k("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"}),k("span",{className:"enrol_apply-rview-skb"}),k("span",{className:"enrol_apply-rview-skb"}),k("span",{className:"enrol_apply-rview-skb"})]},t))})]}),Eo=({labels:e,bymethod:t,bygroup:n,methods:r})=>{let[o,a]=tn(r>1?"method":"group"),[l,d]=tn(!1),[s,i]=tn(""),v=o==="method"?t:n,b=Math.max(86400,...v.map(g=>g.median??0)),u=g=>k(Jn,{label:g.name,share:(g.median??0)/b,figure:`${g.median===null?"\u2013":Oe(e,g.median)} (${_(g.count)})`,warn:(g.median??0)>Zn*86400},g.id),R=[...v].sort((g,V)=>V.count-g.count),c=o==="group"&&v.length>nn,S=s.trim().toLocaleLowerCase(),x=[...v].filter(g=>S===""||g.name.toLocaleLowerCase().includes(S)).sort((g,V)=>(V.median??0)-(g.median??0));return Q("div",{className:"enrol_apply-rview-panel",children:[Q("div",{className:"enrol_apply-rview-panel-title",children:[k("span",{children:e.medianby}),r>1?k(tt,{label:e.breakdown,small:!0,value:o,options:[{value:"method",label:e.bymethod},{value:"group",label:e.bygroup}],onChange:g=>a(g)}):k("span",{children:e.bygroup})]}),c?Q(rn,{children:[k("div",{className:"enrol_apply-rview-note",children:N(e.topgroups,_(nn))}),k("div",{role:"list",children:R.slice(0,nn).map(u)}),k("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":l,onClick:()=>d(!l),children:l?e.hide:N(e.allgroups,_(v.length))}),l?Q(rn,{children:[k("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:e.searchgroup,"aria-label":e.searchgroup,value:s,onChange:g=>i(g.target.value)}),k("div",{className:"enrol_apply-rview-scrolllist",role:"list",children:x.length?x.map(u):k("p",{className:"enrol_apply-rview-note",children:e.nogroupmatch})})]}):null]}):k("div",{role:"list",children:R.map(u)})]})},er=({labels:e,summary:t,loading:n,error:r,compactHint:o,measure:a,methods:l,queueurl:d,pendingstatus:s,onMeasure:i,onRetry:v})=>{let b=Q("div",{className:"enrol_apply-rview-bandhead",children:[k("span",{className:"enrol_apply-rview-bandtitle",children:e.turnaround}),k(tt,{label:e.measure,small:!0,value:a,options:[{value:"first",label:e.measurefirst},{value:"resolution",label:e.measureresolution}],onChange:R=>i(R)})]}),u;if(t===null&&r)u=Q("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",k("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:v,children:e.retry})]});else if(t===null)u=Q(rn,{children:[k(Co,{compact:o}),k("span",{className:"visually-hidden",role:"status",children:e.loadband})]});else{let R=t.measure==="resolution",c=t.median??null,S=t.p90??null,x=t.oldest??null,g=e.nothingopen;x!==null&&(g=N(e.oldest,Oe(e,x))+(t.late>0?" \xB7 "+N(e.late,_(t.late)):""));let V=d===""?null:k("a",{className:"enrol_apply-rview-tile-link",href:R?d:`${d}&status=${s}`,children:e.openqueue}),q=Math.max(1,...t.buckets.map(F=>F.count));u=Q("div",{className:"enrol_apply-rview-dim",children:[Q("div",{className:"enrol_apply-rview-band",children:[k(Tt,{label:R?e.medianresolution:e.medianfirst,value:c===null?"\u2013":Oe(e,c),sub:N(e.mediansub,_(t.decided))}),t.compact?null:k(Tt,{label:e.p90,value:S===null?"\u2013":Oe(e,S),sub:e.p90sub}),k(Tt,{label:e.open,value:_(t.open),sub:g,warn:t.late>0,link:V}),k(Tt,{label:e.withoutperson,value:_(t.withoutperson),sub:e.withoutpersonsub})]}),t.compact?null:Q("div",{className:"enrol_apply-rview-dist",children:[Q("div",{className:"enrol_apply-rview-panel",children:[Q("div",{className:"enrol_apply-rview-panel-title",children:[k("span",{children:R?e.distresolution:e.distfirst}),k("span",{className:"enrol_apply-rview-note",children:N(e.decisions,_(t.decided))})]}),k("div",{role:"list",children:t.buckets.map((F,we)=>{let K=_(F.todays??0),H=le(e.bucket,{from:_(F.fromdays),to:K});return we===0?H=N(e.bucketfirst,_(F.todays??0)):(F.todays??null)===null&&(H=N(e.bucketlast,_(F.fromdays))),k(Jn,{label:H,share:F.count/q,figure:_(F.count),warn:F.fromdays>=Zn},F.fromdays)})})]}),k(Eo,{labels:e,bymethod:t.bymethod,bygroup:t.bygroup,methods:l})]})]})}return Q("section",{className:"enrol_apply-rview-region enrol_apply-rview-bandregion","aria-label":e.turnaround,"aria-busy":n,"data-region":"enrol_apply-band",children:[n&&t!==null?k("div",{className:"enrol_apply-rview-progress"}):null,b,u]})};var tr=e=>e.facet!==""&&e.options.length>0,nr=e=>{try{let t=JSON.parse(e.values||"{}");return t!==null&&typeof t=="object"?t:{}}catch{return{}}},Mo=e=>e.reduce((t,n)=>({...t,...nr(n)}),{}),on=e=>{if(!e.applied)return"";let t=nr(e),n=t[`${e.name}_value`];if(n==null)return"";let r=Number(t[`${e.name}_operator`]??0);if(e.facet==="waited"){let o=Number(n)*Number(t[`${e.name}_unit`]??0)/86400;return r===2&&Number.isFinite(o)?String(o):""}return e.facet==="decider"?r===3?String(n):"":String(n)},rr=(e,t,n)=>{let r=ln(e,t);return t.facet==="waited"?(r[`${t.name}_operator`]=n===""?0:2,r[`${t.name}_value`]=n===""?0:Number(n),r[`${t.name}_unit`]=86400):t.facet==="decider"?(r[`${t.name}_operator`]=n===""?0:3,r[`${t.name}_value`]=n):(r[`${t.name}_operator`]=n===""?0:1,r[`${t.name}_value`]=n),r},ln=(e,t)=>{let n=Mo(e);for(let r of Object.keys(n))r.startsWith(`${t.name}_`)&&delete n[r];return n},or=e=>{let t=on(e);return e.facet===""||t===""?"":e.options.find(n=>n.value===t)?.label??(e.facet==="decider"?t:"")};import{jsx as lr,jsxs as To}from"react/jsx-runtime";var xo=["auto","table","cards"],ar=(e,t)=>t.facet==="waited"?e.waitedmore:t.facet==="decider"?e.decidedby:t.title,Po=(e,t)=>{switch(t){case"decisions":return e.presetdecisions;case"answers":return e.presetanswers;case"groups":return e.presetgroups;default:return t}},Lo=(e,t,n)=>{let r={measure:e};return t!=="all"&&(r.scope=t),n!==""&&(r.search=n),encodeURIComponent(JSON.stringify(r))},Do=(e,t)=>({name:t.name,title:ar(e,t),options:t.options,groupings:t.groupings,find:t.facet==="decider"?{label:e.searchdeciders,nomatch:e.nodecidermatch}:{label:e.searchgroups,nomatch:e.nogroupmatch}}),Ao=(e,t)=>{let n=or(t),r=n!==""?ar(e,t):t.title;return{key:t.name,text:n===""?r:le(e.filterchip,{title:r,value:n})}},sr=e=>{let{labels:t,filters:n}=e,r=e.measure==="resolution"?"submission:resolutiontime":"submission:waittime",o=u=>tr(u)&&(u.facet!=="waited"||u.name===r),a=n.filter(o),l=n.filter(u=>!o(u)),d=u=>n.find(R=>R.name===u),s=e.downloadurl!==""&&e.formats.length>0,i=Lo(e.measure,e.scope,e.appliedsearch),v=s?e.formats.map(u=>({key:u.value,label:u.label,href:`${e.downloadurl}&parameters=${i}&download=${encodeURIComponent(u.value)}`})):[],b=e.presets.map(u=>({name:u.name,label:Po(t,u.name),active:u.active}));return lr(Mt,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"awaiting",label:t.awaiting},{value:"decided",label:t.decided}],scope:e.scope,total:e.total,facets:a.map(u=>Do(t,u)),values:Object.fromEntries(a.map(u=>[u.name,on(u)])),panelnote:l.length>0?To("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",lr("a",{href:e.tableurl,children:t.morefilters})]}):null,chips:n.filter(u=>u.applied).map(u=>Ao(t,u)),columns:e.columns,sets:b,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:xo,tableurl:e.tableurl,downloads:v,search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:u=>e.onScope(u),onSearch:e.onSearch,onFacet:(u,R)=>{let c=d(u);c&&e.onFilter(c,R)},onRemoveChip:u=>{let R=d(u);R&&e.onRemoveFilter(R)},onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:u=>{let R=e.presets.find(c=>c.name===u);R&&e.onPreset(R)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Wo,jsx as Ft}from"react/jsx-runtime";var Fo=["outcome","wait","decided","group"],Io={applicant:260,outcome:180,submitted:132,wait:150,decided:172,group:200,method:170,route:170,comment:240,note:240},Vo=180,Ho={applicant:160,submitted:104,decided:104},Oo=80,Bo=800,It=ht(Io,Vo,Ho,Oo,Bo),ge=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",Vt=(e,t)=>Pn(t,Fo.includes(e)),lt=({column:e,html:t})=>Ft(xt,{parts:Vt(e,t)}),Ht=({row:e,label:t})=>e.reviewurl===""?null:Ft("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:e.reviewurl,children:t}),Ot=({row:e,identity:t})=>Ft(Wo,{children:t.map(n=>{let r=ge(e,n.key);return r===""?null:Ft("span",{className:"enrol_apply-rview-sub",title:gt(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});import{Fragment as ir,jsx as J,jsxs as st}from"react/jsx-runtime";var $o=["applicant","outcome","submitted","wait","decided","group","method"],at=({row:e,column:t})=>{let n=Vt(t,ge(e,t))[0]??"";return n===""?null:J("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:n}})},Uo=(e,t,n,r)=>{let o=l=>t.some(d=>d.key===l),a=t.filter(l=>!$o.includes(l.key)&&ge(r,l.key).trim()!=="");return{head:st(ir,{children:[st("div",{className:"enrol_apply-rview-card-who",children:[J("span",{className:"enrol_apply-rview-card-name",dangerouslySetInnerHTML:{__html:ge(r,"applicant")}}),o("outcome")?J(at,{row:r,column:"outcome"}):null,J(Ot,{row:r,identity:n})]}),J(Ht,{row:r,label:e.review})]}),lines:st(ir,{children:[st("div",{className:"enrol_apply-rview-card-line"+(r.open?" enrol_apply-rview-card-line--open":""),children:[o("wait")?J(at,{row:r,column:"wait"}):null,o("wait")&&o("submitted")?J("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("submitted")?J(at,{row:r,column:"submitted"}):null]}),o("decided")?J("div",{className:"enrol_apply-rview-card-line",children:Vt("decided",ge(r,"decided")).map((l,d)=>J("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:l}},d))}):null,o("group")||o("method")?st("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",children:[o("group")?J(at,{row:r,column:"group"}):null,o("group")&&o("method")&&ge(r,"group")!==""?J("span",{"aria-hidden":"true",children:" \xB7 "}):null,o("method")?J(at,{row:r,column:"method"}):null]}):null]}),fields:a.map(l=>({key:l.key,title:l.title,content:J(lt,{column:l.key,html:ge(r,l.key)})}))}},cr=({labels:e,columns:t,identity:n,rows:r,single:o})=>J(Nt,{labels:e,rows:r,single:o,renderCard:a=>Uo(e,t,n,a)});import{Fragment as qo,jsx as Le,jsxs as an}from"react/jsx-runtime";var Qo=({row:e,longest:t})=>!e.open&&!e.decidedbyperson?null:Le("span",{className:"enrol_apply-rview-waitbar"+(e.open?" enrol_apply-rview-waitbar--open":""),"aria-hidden":"true",children:Le("span",{style:{width:`${Math.min(100,Math.round(e.wait/t*100))}%`}})}),ur=({labels:e,columns:t,identity:n,rows:r,widths:o,sort:a,direction:l,onSort:d,onWidth:s,onWidthCommit:i})=>{let v=Math.max(86400,...r.filter(b=>b.open||b.decidedbyperson).map(b=>b.wait));return Le(Rt,{labels:e,columns:t,lead:"applicant",rows:r,widths:o,sizing:It,sort:a,direction:l,renderLead:(b,u)=>an("div",{className:"enrol_apply-rview-applicant",children:[an("div",{className:"enrol_apply-rview-applicant-text",children:[Le(lt,{column:u.key,html:ge(b,u.key)}),Le(Ot,{row:b,identity:n})]}),Le(Ht,{row:b,label:e.review})]}),renderCell:(b,u)=>an(qo,{children:[Le(lt,{column:u.key,html:ge(b,u.key)}),u.key==="wait"?Le(Qo,{row:b,longest:v}):null]}),onSort:d,onWidth:s,onWidthCommit:i})};import{fetchOne as it}from"@moodle/lms/core/ajax";var dr=(e,t,n)=>it({methodname:"enrol_apply_report_rows",args:{enrolid:e,...t,facets:n}}),pr=(e,t,n,r)=>it({methodname:"enrol_apply_report_summary",args:{enrolid:e,measure:t,scope:n,search:r}}),mr=(e,t)=>it({methodname:"enrol_apply_set_report_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}}),sn=(e,t,n)=>it({methodname:"core_reportbuilder_set_filters",args:{reportid:e,parameters:JSON.stringify({measure:t}),values:JSON.stringify(n)}}),cn=(e,t)=>it({methodname:"core_reportbuilder_filters_reset",args:{reportid:e,parameters:JSON.stringify({measure:t})}});import{Fragment as jo,jsx as De,jsxs as dn}from"react/jsx-runtime";var Ko=300,Go=500,wr="enrol_apply_report_view",zo=["applicant","outcome","submitted","wait","decided","group"],Yo=e=>{let{labels:t,enrolid:n,reportid:r}=e,o=ue(null),a=wt(o),[l,d]=fe(()=>vt(wr)),s={measure:e.measure,scope:"all",search:"",page:0,perpage:e.perpage,sort:"",direction:"desc"},i=ue(s),[v,b]=fe(s),[u,R]=fe(""),[c,S]=fe(null),[x,g]=fe(!0),[V,q]=fe(!1),[F,we]=fe(null),[K,H]=fe(!0),[re,y]=fe(!1),[h,m]=fe({}),[w,T]=fe({}),B=ue(0),ee=ue(0),$=ue(null),j=ue(null),he=ue(null),z=ue(null),Ae=ue(!1),be=ue(null),Ne=ue(null),Ye=ue(null),ke=ue(()=>{}),de=un((p,P)=>{let E=++B.current;$.current||($.current=ae("enrol_apply/report:rows")),g(!0),dr(n,p,P).then(I=>{E===B.current&&(I.counted&&T(Object.fromEntries(I.filters.map(He=>[He.name,He]))),Ae.current||(Ae.current=!0,m(Object.fromEntries(I.preference.widths.map(He=>[He.key,He.width])))),I.page!==i.current.page&&(i.current={...i.current,page:I.page},b(i.current)),S(I),q(!1),g(!1))}).catch(()=>{E===B.current&&(q(!0),g(!1))})},[n]),Me=un(p=>{let P=++ee.current;j.current||(j.current=ae("enrol_apply/report:band")),H(!0),pr(n,p.measure,p.scope,p.search).then(E=>{P===ee.current&&(we(E),y(!1),H(!1))}).catch(()=>{P===ee.current&&(y(!0),H(!1))})},[n]),te=un((p,P,E)=>{i.current={...i.current,...p},b(i.current),P&&de(i.current,E),E&&Me(i.current)},[de,Me]);Bt(()=>{!x&&$.current&&($.current(),$.current=null)},[x,c,V]),Bt(()=>{!K&&j.current&&(j.current(),j.current=null)},[K,F,re]),Bt(()=>{de(i.current,!0),Me(i.current)},[de,Me]),Bt(()=>()=>{he.current!==null&&window.clearTimeout(he.current),z.current?.(),ke.current()},[]);let _e=p=>{d(p),yt(wr,p)},je=p=>{R(p),he.current!==null&&(window.clearTimeout(he.current),he.current=null);let P=p.trim(),E=P.length>=e.searchmin?P:"";if(E===i.current.search){z.current?.(),z.current=null;return}if(p===""){z.current?.(),z.current=null,te({search:"",page:0},!0,!0);return}z.current||(z.current=ae("enrol_apply/report:search")),he.current=window.setTimeout(()=>{he.current=null,te({search:E,page:0},!0,!0),z.current?.(),z.current=null},Ko)},ne=p=>{let P=ae("enrol_apply/report:filters");p().then(()=>{te({page:0},!0,!0)}).catch(()=>{q(!0)}).finally(P)},Te=(c?.filters??[]).map(p=>w[p.name]?{...p,options:w[p.name].options,groupings:w[p.name].groupings}:p),Fe=(p,P)=>{let E=ae("enrol_apply/report:preference");mr(n,p).then(()=>{P&&de(i.current,!1)}).catch(()=>{P&&q(!0)}).finally(E)},oe=c?.columns??[],Ie=oe.filter(p=>!p.fixed&&p.active).map(p=>p.key),Se=c?.preference.usedefault??!0,Re=p=>Object.entries(p).map(([P,E])=>({key:P,width:E})),Wt=(p,P)=>{let E=P?[...Ie,p]:Ie.filter(I=>I!==p);c&&S({...c,columns:c.columns.map(I=>I.key===p?{...I,active:P}:I),preference:{...c.preference,usedefault:!1,columns:E}}),Fe({usedefault:!1,columns:E,widths:Re(h)},!0)},ct=()=>{be.current!==null&&(window.clearTimeout(be.current),be.current=null);let p=Ye.current;Ye.current=null,p!==null&&Fe({usedefault:Se,columns:Se?[]:Ie,widths:Re(p)},!1),Ne.current?.(),Ne.current=null};ke.current=ct;let $t=(p,P,E=!1)=>{let I={...h};if(P===null?delete I[p]:I[p]=P,m(I),Ye.current=I,!E){ct();return}be.current!==null&&window.clearTimeout(be.current),Ne.current||(Ne.current=ae("enrol_apply/report:width")),be.current=window.setTimeout(()=>ke.current(),Go)},ut=p=>{let P=new Set(p.columns);c&&S({...c,columns:c.columns.map(E=>E.fixed?E:{...E,active:P.has(E.key)||P.has("answers")&&E.key.startsWith("answer:")}),presets:c.presets.map(E=>({...E,active:E.name===p.name})),preference:{...c.preference,usedefault:!1,columns:p.columns}}),Fe({usedefault:!1,columns:p.columns,widths:Re(h)},!0)},Ve=p=>{let E=(c?.sort??"")===p&&c?.direction==="asc"?"desc":"asc";te({sort:p,direction:E,page:0},!0,!1)},Ue=p=>{let P=i.current,E=oe.some(I=>I.key==="wait"&&I.active);te({measure:p},P.scope!=="all"||P.sort==="wait"||E,!0)},Qe=Te.some(p=>p.applied)||v.search!==""||v.scope!=="all",dt=oe.filter(p=>p.key.startsWith("identity:")),pt=oe.filter(p=>p.key==="applicant"||!p.fixed&&p.active),Xe=l==="cards"||l==="auto"&&a,mt=null;c!==null&&(mt=Xe?De(cr,{labels:t,columns:pt,identity:dt,rows:c.rows,single:a}):De(ur,{labels:t,columns:pt,identity:dt,rows:c.rows,widths:h,sort:c.sort,direction:c.direction,onSort:Ve,onWidth:$t,onWidthCommit:()=>ke.current()}));let Ut=zo.map(p=>({key:p,title:p==="applicant"?t.applicant:"",sortable:!1,default:!0,active:!0,fixed:p==="applicant"}));return dn("div",{className:"enrol_apply-rview-root",ref:o,"data-region":"enrol_apply-reportview",children:[c?.narrowed?De("p",{className:"enrol_apply-rview-narrowed",children:t.narrowed}):null,De(er,{labels:t,summary:F,loading:K,error:re,compactHint:c?.narrowed??!1,measure:v.measure,methods:e.methods,queueurl:e.queueurl,pendingstatus:e.pendingstatus,onMeasure:Ue,onRetry:()=>Me(i.current)}),De(sr,{labels:t,narrow:a,scope:v.scope,total:c===null?null:c.total,filters:Te,columns:oe.filter(p=>!p.key.startsWith("identity:")||p.active),presets:c?.presets??[],usedefault:Se,haswidths:Object.keys(h).length>0,view:l,measure:v.measure,search:u,appliedsearch:v.search,searchmin:e.searchmin,searching:x,tableurl:e.tableurl,downloadurl:e.downloadurl,formats:e.formats,onScope:p=>te({scope:p,page:0},!0,!0),onSearch:je,onFilter:(p,P)=>ne(()=>sn(r,i.current.measure,rr(Te,p,P))),onRemoveFilter:p=>ne(()=>sn(r,i.current.measure,ln(Te,p))),onClearFilters:()=>ne(()=>cn(r,i.current.measure)),onClearAll:()=>{R(""),i.current={...i.current,search:""},Te.some(p=>p.applied)?ne(()=>cn(r,i.current.measure)):te({page:0},!0,!0)},onColumn:Wt,onPreset:ut,onDefaultColumns:()=>{c&&S({...c,preference:{...c.preference,usedefault:!0,columns:[]}}),Fe({usedefault:!0,columns:[],widths:Re(h)},!0)},onResetWidths:()=>{m({}),Fe({usedefault:Se,columns:Se?[]:Ie,widths:[]},!1)},onView:_e}),De(kt,{labels:t,loading:x,loaded:c!==null,error:V,total:c?.total??0,filtered:Qe,cards:Xe,narrow:a,skeleton:{columns:Ut,lead:"applicant",widths:h,sizing:It},onRetry:()=>de(i.current,!0),children:mt}),c!==null&&c.total>0?De(ft,{labels:t,page:v.page,perpage:v.perpage,perpages:e.perpages,total:c.total,shown:c.rows.length,narrow:a,onPage:p=>te({page:p},!0,!1),onPerpage:p=>te({perpage:p,page:0},!0,!1)}):null,e.retention!==""?dn("p",{className:"enrol_apply-rview-retention","data-region":"enrol_apply-retention",children:[e.retention,e.retentionurl!==""?dn(jo,{children:[" ",De("a",{href:e.retentionurl,children:t.retentionchange})]}):null]}):null]})},vr=Yo;import{jsx as yr}from"react/jsx-runtime";var Xo=e=>e.mount===void 0||e.mount==="report"?yr(vr,{...e}):e.mount==="queue"?yr(Xn,{...e}):null,ks=Xo;export{ks as default};
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
