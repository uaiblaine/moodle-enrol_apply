import{useCallback as rt,useEffect as Ke,useRef as W,useState as J}from"react";import{useEffect as dn,useState as un}from"react";var pn=576,ut=e=>{let[t,n]=un(!1);return dn(()=>{let r=e.current;if(!r||typeof ResizeObserver>"u")return;let a=new ResizeObserver(s=>{let o=s[0]?.contentRect.width??r.clientWidth;n(o>0&&o<pn)});return a.observe(r),()=>a.disconnect()},[e]),t},pt=e=>{try{let t=window.localStorage.getItem(e);return t==="table"||t==="cards"?t:"auto"}catch{return"auto"}},mt=(e,t)=>{try{window.localStorage.setItem(e,t)}catch{}};import{useId as mn}from"react";var M=(e,t)=>(e||"").split("{$a}").join(t),ne=(e,t)=>Object.entries(t).reduce((n,[r,a])=>n.split(`{$a->${r}}`).join(a),e||""),wt=(e,t,n,r)=>M(n===1&&e[`${t}_one`]?e[`${t}_one`]:e[t],r),_=e=>{let t=document.documentElement.lang||void 0;try{return new Intl.NumberFormat(t).format(e)}catch{return String(e)}},fe=(e,t)=>{let n=Math.max(0,t);if(n<3600)return M(e.waitminutes,_(Math.max(1,Math.round(n/60))));if(n<86400)return M(e.waithours,_(Math.round(n/3600)));let r=Math.floor(n/86400),a=Math.round((n-r*86400)/3600);return a===24&&(r++,a=0),a>0&&r<10?ne(e.waitdayshours,{days:_(r),hours:_(a)}):M(e.waitdays,_(r))},Ie=e=>{let t=document.createElement("template");return t.innerHTML=e,(t.content.textContent||"").replace(/\s+/g," ").trim()};import{jsx as Z,jsxs as Ge}from"react/jsx-runtime";var wn=(e,t)=>[...new Set([1,e-1,e,e+1,t])].filter(n=>n>=1&&n<=t).sort((n,r)=>n-r),yt=({labels:e,page:t,perpage:n,perpages:r,total:a,shown:s,narrow:o,onPage:g,onPerpage:p})=>{let d=mn(),m=Math.max(1,Math.ceil(a/n)),h=t+1,i=a>0?t*n+1:0,R=a>0?i+s-1:0,w=[];if(o)w=[Z("span",{className:"enrol_apply-rview-pager-gap",children:ne(e.pageof,{page:_(h),pages:_(m)})},"of")];else{let N=wn(h,m);N.forEach((S,y)=>{y>0&&S-N[y-1]>1&&w.push(Z("span",{className:"enrol_apply-rview-pager-gap","aria-hidden":"true",children:"\u2026"},`gap${S}`)),w.push(Z("button",{type:"button","aria-current":S===h?"page":void 0,onClick:()=>g(S-1),children:_(S)},S))})}return Ge("div",{className:"enrol_apply-rview-foot",children:[Z("span",{className:"enrol_apply-rview-num","data-region":"enrol_apply-range",children:ne(e.range,{from:_(i),to:_(R),total:_(a)})}),m>1?Ge("nav",{className:"enrol_apply-rview-pager","aria-label":e.pages,children:[Z("button",{type:"button",disabled:h===1,onClick:()=>g(t-1),children:e.previous}),w,Z("button",{type:"button",disabled:h===m,onClick:()=>g(t+1),children:e.next})]}):null,Ge("span",{className:"enrol_apply-rview-perpage",children:[Z("label",{htmlFor:d,children:e.perpage}),Z("select",{id:d,className:"form-select form-select-sm",value:n,onChange:N=>p(Number(N.target.value)),children:r.map(N=>Z("option",{value:N,children:_(N)},N))})]})]})};var ie=e=>{let t=window.M?.util;t?.js_pending(e);let n=!0;return()=>{n&&(n=!1,t?.js_complete(e))}};import{useState as hn}from"react";import{useEffect as yn,useRef as De}from"react";var vt=(e,t,n,r,a)=>({defaultWidth:s=>e[s]??t,minimumWidth:s=>n[s]??r,maximum:a}),z=(e,t,n)=>Math.min(e.maximum,Math.max(e.minimumWidth(n),t[n]??e.defaultWidth(n)));import{jsx as E,jsxs as he}from"react/jsx-runtime";var Te=16,vn={ascending:"\u25B2",descending:"\u25BC",none:""},gn=(e,t)=>e?t==="asc"?"ascending":"descending":"none",fn=({selection:e,rows:t})=>{let n=De(null),r=t.filter(o=>!e.locked.has(o.id)),a=r.filter(o=>e.selected.has(o.id)).length,s=r.length>0&&a===r.length;return yn(()=>{n.current&&(n.current.indeterminate=a>0&&!s)},[a,s]),E("input",{ref:n,type:"checkbox",className:"enrol_apply-rview-pick","aria-label":e.pagelabel,checked:s,disabled:r.length===0,onChange:o=>e.onSelectPage(o.target.checked)})},ze=({selection:e,row:t})=>e.locked.has(t.id)?E("span",{className:"enrol_apply-rview-pick enrol_apply-rview-pick--none"}):E("input",{type:"checkbox",className:"enrol_apply-rview-pick","aria-label":e.label(t),checked:e.selected.has(t.id),onChange:n=>e.onSelect(t.id,n.target.checked)}),gt=({labels:e,columns:t,lead:n,rows:r,widths:a,sizing:s,sort:o,direction:g,selection:p,renderLead:d,renderCell:m,onSort:h,onWidth:i,onWidthCommit:R})=>{let w=De(null),N=De(null),S=De(null),y=t.reduce((u,v)=>u+z(s,a,v.key),0),V=u=>{u.currentTarget.classList.toggle("is-scrolled",u.currentTarget.scrollLeft>0)},G=(u,v)=>{let I=N.current?.querySelector(`col[data-col="${CSS.escape(u)}"]`);!I||!N.current||(I.style.width=`${v}px`,N.current.style.width=`${y-z(s,a,u)+v}px`)},L=(u,v)=>Math.round(Math.min(s.maximum,Math.max(s.minimumWidth(u),v))),oe=(u,v)=>{if(u.button!==0)return;u.preventDefault(),u.currentTarget.setPointerCapture(u.pointerId),u.currentTarget.classList.add("is-active");let I=z(s,a,v);S.current={key:v,x:u.clientX,width:I,now:I}},U=u=>{let v=S.current;if(!v)return;let I=document.documentElement.dir==="rtl",H=(u.clientX-v.x)*(I?-1:1),c=L(v.key,v.width+H);c!==v.now&&(G(v.key,c),v.now=c)},$=u=>{let v=S.current;S.current=null,u.currentTarget.classList.remove("is-active"),v&&v.now!==v.width&&i(v.key,v.now)},te=(u,v)=>{let I=document.documentElement.dir==="rtl",H=0;if(u.key==="ArrowRight")H=I?-Te:Te;else if(u.key==="ArrowLeft")H=I?Te:-Te;else if(u.key==="Home"){u.preventDefault(),i(v,null,!0);return}if(H===0)return;u.preventDefault();let c=L(v,z(s,a,v)+H);c!==z(s,a,v)&&i(v,c,!0)};return E("div",{className:"enrol_apply-rview-gridframe",ref:w,tabIndex:0,role:"region","aria-label":e.grid,onScroll:V,children:he("table",{className:"enrol_apply-rview-grid",ref:N,style:{width:`${y}px`},children:[E("caption",{className:"visually-hidden",children:e.caption}),E("colgroup",{children:t.map(u=>E("col",{"data-col":u.key,style:{width:`${z(s,a,u.key)}px`}},u.key))}),E("thead",{children:E("tr",{children:t.map(u=>{let v=gn(o===u.key,g),I=E("span",{className:"enrol_apply-rview-th-title",children:u.title});return he("th",{scope:"col",className:u.key===n?"enrol_apply-rview-fixed":void 0,"aria-sort":u.sortable?v:void 0,title:u.title,"data-column":u.key,children:[p&&u.key===n?E(fn,{selection:p,rows:r}):null,u.sortable?he("button",{type:"button",className:"enrol_apply-rview-sortbtn",onClick:()=>h(u.key),children:[I,E("span",{className:"enrol_apply-rview-arrow","aria-hidden":"true",children:vn[v]})]}):E("span",{className:"enrol_apply-rview-plain",children:I}),E("span",{className:"enrol_apply-rview-resize",role:"separator","aria-orientation":"vertical","aria-label":M(e.resize,u.title),"aria-valuemin":s.minimumWidth(u.key),"aria-valuemax":s.maximum,"aria-valuenow":z(s,a,u.key),tabIndex:0,onPointerDown:H=>oe(H,u.key),onPointerMove:U,onPointerUp:$,onPointerCancel:$,onDoubleClick:()=>{z(s,a,u.key)!==s.defaultWidth(u.key)&&i(u.key,null)},onKeyDown:H=>te(H,u.key),onBlur:R})]},u.key)})})}),E("tbody",{children:r.map(u=>E("tr",{"data-record":u.id,className:p?.selected.has(u.id)?"enrol_apply-rview-row--selected":void 0,children:t.map(v=>v.key===n?he("td",{className:"enrol_apply-rview-fixed",children:[p?E(ze,{selection:p,row:u}):null,d(u,v)]},v.key):E("td",{children:m(u,v)},v.key))},u.id))})]})})},ft=({columns:e,lead:t,widths:n,sizing:r})=>{let a=e.reduce((o,g)=>o+z(r,n,g.key),0),s=["enrol_apply-rview-skb--w80","enrol_apply-rview-skb--w60","enrol_apply-rview-skb--w40"];return E("div",{className:"enrol_apply-rview-gridframe","aria-hidden":"true",children:he("table",{className:"enrol_apply-rview-grid",style:{width:`${a}px`},children:[E("colgroup",{children:e.map(o=>E("col",{style:{width:`${z(r,n,o.key)}px`}},o.key))}),E("thead",{children:E("tr",{children:e.map(o=>E("th",{className:o.key===t?"enrol_apply-rview-fixed":void 0,children:E("span",{className:"enrol_apply-rview-plain",children:E("span",{className:"enrol_apply-rview-th-title",children:o.title})})},o.key))})}),E("tbody",{children:Array.from({length:8},(o,g)=>E("tr",{className:"enrol_apply-rview-sk",children:e.map((p,d)=>E("td",{className:p.key===t?"enrol_apply-rview-fixed":void 0,children:E("span",{className:"enrol_apply-rview-skb "+s[(g+d)%3]})},p.key))},g))})]})})};import{jsx as q,jsxs as be}from"react/jsx-runtime";var bn=({labels:e,row:t,single:n,selection:r,renderCard:a})=>{let[s,o]=hn(!1),g=a(t),p=!n||s,d=r?.selected.has(t.id)??!1;return be("article",{className:"enrol_apply-rview-card"+(d?" enrol_apply-rview-card--selected":""),role:"listitem","data-record":t.id,children:[be("div",{className:"enrol_apply-rview-card-l1",children:[r?q(ze,{selection:r,row:t}):null,g.head]}),g.lines,g.fields.length>0?be("div",{className:"enrol_apply-rview-card-more",children:[n?q("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":s,onClick:()=>o(!s),children:s?e.less:e.more}):null,p?q("dl",{className:"enrol_apply-rview-card-list",children:g.fields.map(m=>be("div",{className:"enrol_apply-rview-card-item",children:[q("dt",{children:m.title}),q("dd",{children:m.content})]},m.key))}):null]}):null]})},ht=({labels:e,rows:t,single:n,selection:r,renderCard:a})=>q("div",{className:"enrol_apply-rview-cards"+(n?" enrol_apply-rview-cards--single":""),role:"list",children:t.map(s=>q(bn,{labels:e,row:s,single:n,selection:r,renderCard:a},s.id))}),bt=({single:e})=>q("div",{className:"enrol_apply-rview-cards"+(e?" enrol_apply-rview-cards--single":""),"aria-hidden":"true",children:Array.from({length:e?4:6},(t,n)=>be("div",{className:"enrol_apply-rview-card enrol_apply-rview-sk",children:[q("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),q("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"}),q("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),q("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"})]},n))});import{Fragment as Rt,jsx as ee,jsxs as ue}from"react/jsx-runtime";var _n=({labels:e,loaded:t,error:n,total:r,filtered:a,cards:s,narrow:o,skeleton:g,children:p,onRetry:d})=>{let m=ue("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",ee("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:d,children:e.retry})]});return!t&&n?m:t?r===0?ue("div",{className:"enrol_apply-rview-empty",children:[ee("p",{className:"enrol_apply-rview-empty-title",children:a?e.nomatch:e.empty}),a?ee("p",{className:"enrol_apply-rview-empty-text",children:e.nomatchhelp}):null]}):ue("div",{className:"enrol_apply-rview-dim",children:[n?m:null,p,s?null:ee("p",{className:"enrol_apply-rview-hint",children:e.scrollhint})]}):ue(Rt,{children:[s?ee(bt,{single:o}):ee(ft,{...g}),ee("span",{className:"visually-hidden",role:"status",children:e.loading})]})},_t=e=>ue(Rt,{children:[ue("section",{className:"enrol_apply-rview-region","aria-label":e.labels.caption,"aria-busy":e.loading,"data-region":"enrol_apply-rows",children:[e.loading&&e.loaded?ee("div",{className:"enrol_apply-rview-progress"}):null,ee(_n,{...e})]}),e.dock??null]});import{useState as Qe}from"react";import{jsx as kt}from"react/jsx-runtime";var _e=({label:e,value:t,options:n,small:r,onChange:a})=>{let s=o=>{let g=n.findIndex(m=>m.value===t),p=-1;if(o.key==="ArrowRight"||o.key==="ArrowDown"?p=(g+1)%n.length:o.key==="ArrowLeft"||o.key==="ArrowUp"?p=(g-1+n.length)%n.length:o.key==="Home"?p=0:o.key==="End"&&(p=n.length-1),p<0)return;o.preventDefault(),a(n[p].value),o.currentTarget.querySelectorAll('[role="radio"]')[p]?.focus()};return kt("div",{className:"enrol_apply-rview-seg"+(r?" enrol_apply-rview-seg--small":""),role:"radiogroup","aria-label":e,onKeyDown:s,children:n.map(o=>kt("button",{type:"button",className:"enrol_apply-rview-seg-cell",role:"radio","aria-checked":o.value===t,tabIndex:o.value===t?0:-1,onClick:()=>a(o.value),children:o.label},o.value))})};import{Fragment as Je,jsx as b,jsxs as D}from"react/jsx-runtime";var Ye=8,Nt=7,Fe=({label:e,value:t,sub:n,warn:r,link:a})=>D("div",{className:"enrol_apply-rview-tile"+(r?" enrol_apply-rview-tile--warn":""),children:[b("div",{className:"enrol_apply-rview-tile-label",children:e}),b("div",{className:"enrol_apply-rview-tile-value",children:t}),b("div",{className:"enrol_apply-rview-tile-sub",children:n}),a??null]}),Ct=({label:e,share:t,figure:n,warn:r})=>D("div",{className:"enrol_apply-rview-hrow",role:"listitem",children:[b("span",{className:"enrol_apply-rview-hrow-label",title:e,children:e}),b("span",{className:"enrol_apply-rview-track","aria-hidden":"true",children:b("span",{className:"enrol_apply-rview-fill"+(r?" enrol_apply-rview-fill--late":""),style:{width:`${Math.round(Math.min(1,Math.max(0,t))*100)}%`}})}),b("span",{className:"enrol_apply-rview-hrow-n",children:n})]}),Rn=({compact:e})=>D("div",{"aria-hidden":"true",children:[b("div",{className:"enrol_apply-rview-band",children:Array.from({length:e?3:4},(t,n)=>D("div",{className:"enrol_apply-rview-tile enrol_apply-rview-sk",children:[b("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w60"}),b("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--big"}),b("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w80"})]},n))}),e?null:b("div",{className:"enrol_apply-rview-dist",children:[0,1].map(t=>D("div",{className:"enrol_apply-rview-panel enrol_apply-rview-sk",children:[b("span",{className:"enrol_apply-rview-skb enrol_apply-rview-skb--w40"}),b("span",{className:"enrol_apply-rview-skb"}),b("span",{className:"enrol_apply-rview-skb"}),b("span",{className:"enrol_apply-rview-skb"})]},t))})]}),kn=({labels:e,bymethod:t,bygroup:n,methods:r})=>{let[a,s]=Qe(r>1?"method":"group"),[o,g]=Qe(!1),[p,d]=Qe(""),m=a==="method"?t:n,h=Math.max(86400,...m.map(y=>y.median??0)),i=y=>b(Ct,{label:y.name,share:(y.median??0)/h,figure:`${y.median===null?"\u2013":fe(e,y.median)} (${_(y.count)})`,warn:(y.median??0)>Nt*86400},y.id),R=[...m].sort((y,V)=>V.count-y.count),w=a==="group"&&m.length>Ye,N=p.trim().toLocaleLowerCase(),S=[...m].filter(y=>N===""||y.name.toLocaleLowerCase().includes(N)).sort((y,V)=>(V.median??0)-(y.median??0));return D("div",{className:"enrol_apply-rview-panel",children:[D("div",{className:"enrol_apply-rview-panel-title",children:[b("span",{children:e.medianby}),r>1?b(_e,{label:e.breakdown,small:!0,value:a,options:[{value:"method",label:e.bymethod},{value:"group",label:e.bygroup}],onChange:y=>s(y)}):b("span",{children:e.bygroup})]}),w?D(Je,{children:[b("div",{className:"enrol_apply-rview-note",children:M(e.topgroups,_(Ye))}),b("div",{role:"list",children:R.slice(0,Ye).map(i)}),b("button",{type:"button",className:"btn btn-link btn-sm enrol_apply-rview-linkbtn","aria-expanded":o,onClick:()=>g(!o),children:o?e.hide:M(e.allgroups,_(m.length))}),o?D(Je,{children:[b("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:e.searchgroup,"aria-label":e.searchgroup,value:p,onChange:y=>d(y.target.value)}),b("div",{className:"enrol_apply-rview-scrolllist",role:"list",children:S.length?S.map(i):b("p",{className:"enrol_apply-rview-note",children:e.nogroupmatch})})]}):null]}):b("div",{role:"list",children:R.map(i)})]})},Et=({labels:e,summary:t,loading:n,error:r,compactHint:a,measure:s,methods:o,queueurl:g,pendingstatus:p,onMeasure:d,onRetry:m})=>{let h=D("div",{className:"enrol_apply-rview-bandhead",children:[b("span",{className:"enrol_apply-rview-bandtitle",children:e.turnaround}),b(_e,{label:e.measure,small:!0,value:s,options:[{value:"first",label:e.measurefirst},{value:"resolution",label:e.measureresolution}],onChange:R=>d(R)})]}),i;if(t===null&&r)i=D("p",{className:"enrol_apply-rview-error",children:[e.loaderror," ",b("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:m,children:e.retry})]});else if(t===null)i=D(Je,{children:[b(Rn,{compact:a}),b("span",{className:"visually-hidden",role:"status",children:e.loadband})]});else{let R=t.measure==="resolution",w=t.median??null,N=t.p90??null,S=t.oldest??null,y=e.nothingopen;S!==null&&(y=M(e.oldest,fe(e,S))+(t.late>0?" \xB7 "+M(e.late,_(t.late)):""));let V=g===""?null:b("a",{className:"enrol_apply-rview-tile-link",href:R?g:`${g}&status=${p}`,children:e.openqueue}),G=Math.max(1,...t.buckets.map(L=>L.count));i=D("div",{className:"enrol_apply-rview-dim",children:[D("div",{className:"enrol_apply-rview-band",children:[b(Fe,{label:R?e.medianresolution:e.medianfirst,value:w===null?"\u2013":fe(e,w),sub:M(e.mediansub,_(t.decided))}),t.compact?null:b(Fe,{label:e.p90,value:N===null?"\u2013":fe(e,N),sub:e.p90sub}),b(Fe,{label:e.open,value:_(t.open),sub:y,warn:t.late>0,link:V}),b(Fe,{label:e.withoutperson,value:_(t.withoutperson),sub:e.withoutpersonsub})]}),t.compact?null:D("div",{className:"enrol_apply-rview-dist",children:[D("div",{className:"enrol_apply-rview-panel",children:[D("div",{className:"enrol_apply-rview-panel-title",children:[b("span",{children:R?e.distresolution:e.distfirst}),b("span",{className:"enrol_apply-rview-note",children:M(e.decisions,_(t.decided))})]}),b("div",{role:"list",children:t.buckets.map((L,oe)=>{let U=_(L.todays??0),$=ne(e.bucket,{from:_(L.fromdays),to:U});return oe===0?$=M(e.bucketfirst,_(L.todays??0)):(L.todays??null)===null&&($=M(e.bucketlast,_(L.fromdays))),b(Ct,{label:$,share:L.count/G,figure:_(L.count),warn:L.fromdays>=Nt},L.fromdays)})})]}),b(kn,{labels:e,bymethod:t.bymethod,bygroup:t.bygroup,methods:o})]})]})}return D("section",{className:"enrol_apply-rview-region enrol_apply-rview-bandregion","aria-label":e.turnaround,"aria-busy":n,"data-region":"enrol_apply-band",children:[n&&t!==null?b("div",{className:"enrol_apply-rview-progress"}):null,h,i]})};import{useEffect as Be,useId as In,useRef as ke,useState as It}from"react";import{useId as Cn,useState as Mt}from"react";import{jsx as St}from"react/jsx-runtime";var Nn={filter:"M2 3h12l-4.5 5.5V13l-3 1.5V8.5L2 3z",columns:"M2 3h12v10H2zM6.5 3v10M10 3v10",table:"M2 3h12v10H2zM2 6.5h12M2 10h12M6 6.5V13",cards:"M2 3h5v4H2zM9 3h5v4H9zM2 9h5v4H2zM9 9h5v4H9z",auto:"M2 3h7v10H2zM11 3h3v4h-3zM11 9h3v4h-3z",download:"M8 2v8M4.5 7 8 10.5 11.5 7M3 13h10",search:"M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM10.5 10.5 14 14",close:"M4 4l8 8M12 4l-8 8",eraser:"M9.5 2 14 6.5 7.5 13 3 8.5zM5.6 5.9l4.5 4.5M7.5 13H14",tick:"M3 8.5 6.5 12 13 4.5",chevron:"M4 6l4 4 4-4",reset:"M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5",queue:"M3 4h10M3 8h10M3 12h6"},A=({name:e,className:t})=>St("svg",{className:"enrol_apply-rview-icon"+(t?" "+t:""),viewBox:"0 0 16 16",width:"16",height:"16",fill:"none",stroke:"currentColor",strokeWidth:"1.5",strokeLinecap:"round",strokeLinejoin:"round","aria-hidden":"true",focusable:"false",children:St("path",{d:Nn[e]??""})});import{Fragment as Pn,jsx as F,jsxs as Q}from"react/jsx-runtime";var En=8,je=50,Sn=(e,t)=>t===null?e.show:M(e.showcount,_(t)),xt=({option:e})=>e.count>=0?F("small",{className:"enrol_apply-rview-optcount",children:_(e.count)}):null,Mn=(e,t)=>e.options.filter(n=>n.count!==0||n.value===t),xn=({labels:e,facet:t,shown:n,value:r,headid:a,onChange:s})=>{let[o,g]=Mt(""),[p,d]=Mt(""),m=o.trim().toLocaleLowerCase(),h=t.groupings.length>1?t.groupings:[],i=h.find(y=>String(y.id)===p)?.groups.map(String)??null,R=n.filter(y=>y.value===r),w=n.filter(y=>y.value!==r&&(m===""||y.label.toLocaleLowerCase().includes(m))&&(i===null||i.includes(y.value))),N=M(t.find.label,_(t.options.length)),S=h.length>0?e.moregroupsgrouping:e.moregroups;return Q(Pn,{children:[Q("div",{className:"enrol_apply-rview-find",children:[F("input",{type:"search",className:"form-control form-control-sm enrol_apply-rview-input",placeholder:N,"aria-label":N,value:o,onChange:y=>g(y.target.value)}),h.length>0?Q("select",{className:"form-select form-select-sm enrol_apply-rview-grouping","aria-label":e.grouping,value:p,onChange:y=>d(y.target.value),children:[F("option",{value:"",children:e.allgroupings}),h.map(y=>F("option",{value:String(y.id),children:y.name},y.id))]}):null]}),Q("div",{className:"enrol_apply-rview-optlist",role:"group","aria-labelledby":a,children:[[...R,...w.slice(0,je)].map(y=>Q("button",{type:"button",className:"enrol_apply-rview-optrow","aria-pressed":y.value===r,onClick:()=>s(t.name,y.value===r?"":y.value),children:[F("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:F(A,{name:"tick"})}),F("span",{className:"enrol_apply-rview-optrow-label",children:y.label})," ",F(xt,{option:y})]},y.value)),w.length>je?F("p",{className:"enrol_apply-rview-note",children:M(S,_(w.length-je))}):null,R.length===0&&w.length===0?F("p",{className:"enrol_apply-rview-note",children:t.find.nomatch}):null]})]})},Ln=({labels:e,facet:t,value:n,onChange:r})=>{let a=Cn(),s=Mn(t,n),o=[{value:"",label:e.any,count:-1},...s];if(t.options.length>En)return Q("div",{className:"enrol_apply-rview-fgroup",children:[F("span",{className:"enrol_apply-rview-fpanel-head",id:a,children:t.title}),F(xn,{labels:e,facet:t,shown:s,value:n,headid:a,onChange:r})]});let g=p=>{let d=o.findIndex(h=>h.value===n),m=-1;p.key==="ArrowRight"||p.key==="ArrowDown"?m=(d+1)%o.length:p.key==="ArrowLeft"||p.key==="ArrowUp"?m=(d-1+o.length)%o.length:p.key==="Home"?m=0:p.key==="End"&&(m=o.length-1),!(m<0)&&(p.preventDefault(),r(t.name,o[m].value),p.currentTarget.querySelectorAll('[role="radio"]')[m]?.focus())};return Q("div",{className:"enrol_apply-rview-fgroup",children:[F("span",{className:"enrol_apply-rview-fpanel-head",id:a,children:t.title}),F("div",{className:"enrol_apply-rview-fpanel-opts",role:"radiogroup","aria-labelledby":a,onKeyDown:g,children:o.map(p=>Q("button",{type:"button",className:"enrol_apply-rview-fpanel-opt",role:"radio","aria-checked":p.value===n,tabIndex:p.value===n?0:-1,onClick:()=>r(t.name,p.value),children:[p.label," ",F(xt,{option:p})]},p.value))})]})},Lt=({labels:e,id:t,hidden:n,facets:r,values:a,total:s,note:o,onChange:g,onClear:p,onShow:d})=>Q("div",{className:"enrol_apply-rview-fpanel",id:t,role:"dialog","aria-label":e.filters,hidden:n,children:[Q("div",{className:"enrol_apply-rview-fpanel-body",children:[r.map(m=>F(Ln,{labels:e,facet:m,value:a[m.name]??"",onChange:g},m.name)),o??null]}),Q("div",{className:"enrol_apply-rview-fpanel-foot",children:[F("button",{type:"button",className:"btn btn-sm btn-outline-secondary",onClick:p,children:e.clearfilters}),F("button",{type:"button",className:"btn btn-sm btn-primary",onClick:d,children:Sn(e,s)})]})]});import{Fragment as An,jsx as Re,jsxs as Pt}from"react/jsx-runtime";var He=e=>{if(!["ArrowDown","ArrowUp","Home","End"].includes(e.key))return;let t=Array.from(e.currentTarget.querySelectorAll('[role^="menuitem"]'));if(t.length===0)return;e.preventDefault();let n=t.indexOf(document.activeElement),r=0;e.key==="End"?r=t.length-1:e.key==="ArrowDown"?r=(n+1)%t.length:e.key==="ArrowUp"&&(r=(n-1+t.length)%t.length),t[r].focus()},At=({heading:e,sets:t,onSet:n})=>t.length===0?null:Pt(An,{children:[Re("div",{className:"enrol_apply-rview-menu-head",role:"presentation",children:e}),t.map(r=>Pt("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":r.active,tabIndex:-1,"data-preset":r.name,onClick:()=>{r.active||n(r.name)},children:[Re("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:Re(A,{name:"tick"})}),Re("span",{className:"enrol_apply-rview-menu-label",children:r.label})]},r.name)),Re("div",{className:"enrol_apply-rview-menu-sep",role:"separator"})]});import{Fragment as Tt,jsx as f,jsxs as x}from"react/jsx-runtime";var Dt=e=>{let{labels:t,chips:n,columns:r}=e,a=In(),s=ke(null),o=ke(null),g=ke(null),p=ke(!1),[d,m]=It(null),[h,i]=It(e.search!==""),R=ke({});Be(()=>{if(d===null)return;let c=T=>{s.current&&!s.current.contains(T.target)&&m(null)};return document.addEventListener("mousedown",c),()=>document.removeEventListener("mousedown",c)},[d]),Be(()=>{if(d===null||d==="filters")return;let c=s.current?.querySelector(`#${CSS.escape(a+d)}`);(c?.querySelector('[aria-checked="true"]:not([aria-disabled="true"])')??c?.querySelector('[role^="menuitem"]:not([aria-disabled="true"])'))?.focus()},[d,a]),Be(()=>{h?o.current?.focus():p.current&&(p.current=!1,g.current?.focus())},[h]),Be(()=>{let c=T=>{if(T.key!=="/"||T.ctrlKey||T.metaKey||T.altKey||T.defaultPrevented)return;let O=document.activeElement;if(O&&(O.isContentEditable||O.closest("input, textarea, select")))return;let K=s.current?.getBoundingClientRect();!K||K.width===0||K.bottom<=0||K.top>=window.innerHeight||(T.preventDefault(),h?o.current?.focus():(m(null),i(!0)))};return document.addEventListener("keydown",c),()=>document.removeEventListener("keydown",c)},[h]);let w=c=>m(d===c?null:c),N=c=>{p.current=c,i(!1)},S=c=>{if(!(c.key!=="Escape"||c.defaultPrevented))if(d!==null){c.preventDefault();let T=R.current[d];m(null),T?.focus()}else h&&(c.preventDefault(),N(!0))},y=e.search.trim().length>=e.searchmin,V=y&&!h,G=n.length+(V?1:0),L=e.total===null?"\u2026":wt(t,"count",e.total,_(e.total)),oe=e.total!==null&&e.narrow?x(Tt,{children:[f("span",{"aria-hidden":"true",children:_(e.total)}),f("span",{className:"visually-hidden",children:L})]}):L,U=(c,T,O,K)=>x("button",{type:"button",className:"enrol_apply-rview-iconbtn","aria-label":O,"data-enrol-apply-tip":O,"aria-haspopup":c==="filters"?"dialog":"menu","aria-expanded":d===c,"aria-controls":a+c,ref:le=>{R.current[c??""]=le},onClick:()=>w(c),children:[f(A,{name:T}),K]}),$={auto:"auto",table:"table",cards:"cards"}[e.view],te=f(A,{name:"chevron",className:"enrol_apply-rview-chevron"}),u=n.length>0?M(t.filterson,_(n.length)):t.filters,v=n.length>0?f("span",{className:"enrol_apply-rview-badge","aria-hidden":"true",children:_(n.length)}):null,I=[["auto",t.viewauto,t.viewautohint],["table",t.table,""],["cards",t.cards,""]].filter(([c])=>e.views.includes(c)),H="enrol_apply-rview-bar"+(e.narrow?" enrol_apply-rview-bar--narrow":"")+(h?" enrol_apply-rview-bar--searching":"");return x("div",{className:H,"data-region":"enrol_apply-bar",ref:s,onKeyDown:S,children:[f(_e,{label:t.scope,value:e.scope,options:e.scopes,onChange:e.onScope}),f("span",{className:"enrol_apply-rview-bar-spacer"}),x("div",{className:"enrol_apply-rview-bar-controls",children:[x("div",{className:"enrol_apply-rview-bar-cluster",children:[f("span",{className:"enrol_apply-rview-bar-count","data-region":"enrol_apply-total","aria-live":"polite",children:oe}),x("div",{className:"enrol_apply-rview-capsule",children:[U("filters","filter",u,v),x("div",{className:"enrol_apply-rview-anchor",children:[U("columns","columns",t.columns,te),x("div",{className:"enrol_apply-rview-menu",id:a+"columns",role:"menu","aria-label":t.columns,hidden:d!=="columns",onKeyDown:He,children:[f(At,{heading:t.presets,sets:e.sets,onSet:e.onSet}),r.map(c=>c.fixed?x("div",{className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":"true","aria-disabled":"true",tabIndex:-1,children:[f("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:f(A,{name:"tick"})}),f("span",{className:"enrol_apply-rview-menu-label",children:c.title}),f("span",{className:"enrol_apply-rview-menu-hint",children:t.always})]},c.key):x("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemcheckbox","aria-checked":c.active,tabIndex:-1,onClick:()=>e.onColumn(c.key,!c.active),children:[f("span",{className:"enrol_apply-rview-box","aria-hidden":"true",children:f(A,{name:"tick"})}),f("span",{className:"enrol_apply-rview-menu-label",children:c.title})]},c.key)),f("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),x("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":e.usedefault,onClick:()=>{e.usedefault||e.onDefaultColumns()},children:[f(A,{name:"columns"}),f("span",{className:"enrol_apply-rview-menu-label",children:t.defaultcolumns})]}),x("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,"aria-disabled":!e.haswidths,onClick:()=>{e.haswidths&&e.onResetWidths()},children:[f(A,{name:"reset"}),f("span",{className:"enrol_apply-rview-menu-label",children:t.resetwidths})]})]})]}),x("div",{className:"enrol_apply-rview-anchor",children:[U("view",$,t.view,te),x("div",{className:"enrol_apply-rview-menu",id:a+"view",role:"menu","aria-label":t.view,hidden:d!=="view",onKeyDown:He,children:[I.map(([c,T,O])=>x("button",{type:"button",className:"enrol_apply-rview-menu-item",role:"menuitemradio","aria-checked":e.view===c,tabIndex:-1,onClick:()=>{e.onView(c),m(null),R.current.view?.focus()},children:[f(A,{name:c}),f("span",{className:"enrol_apply-rview-menu-label",children:T}),O!==""?f("span",{className:"enrol_apply-rview-menu-hint",children:O}):null,f(A,{name:"tick",className:"enrol_apply-rview-tick"})]},c)),e.tableurl!==""?x(Tt,{children:[f("div",{className:"enrol_apply-rview-menu-sep",role:"separator"}),x("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:e.tableurl,children:[f(A,{name:"table"}),f("span",{className:"enrol_apply-rview-menu-label",children:t.coretable})]})]}):null]})]}),e.downloads.length>0?x("div",{className:"enrol_apply-rview-anchor",children:[U("download","download",t.download,te),f("div",{className:"enrol_apply-rview-menu",id:a+"download",role:"menu","aria-label":t.download,hidden:d!=="download",onKeyDown:He,children:e.downloads.map(c=>x("a",{className:"enrol_apply-rview-menu-item",role:"menuitem",tabIndex:-1,href:c.href,children:[f(A,{name:"download"}),f("span",{className:"enrol_apply-rview-menu-label",children:c.label})]},c.key))})]}):null]})]}),f("div",{className:"enrol_apply-rview-capsule enrol_apply-rview-searchcap",children:h?x("span",{className:"enrol_apply-rview-search",children:[e.searching&&y?f("span",{className:"enrol_apply-rview-spin","aria-hidden":"true"}):f(A,{name:"search"}),f("input",{ref:o,type:"search","aria-label":t.search,placeholder:t.search,autoComplete:"off",value:e.search,onChange:c=>e.onSearch(c.target.value),onKeyDown:c=>{c.key==="Escape"&&(c.preventDefault(),N(!0))},onBlur:()=>{e.search===""&&N(!1)}}),e.search!==""?f("button",{type:"button",className:"enrol_apply-rview-eraser","aria-label":t.cleartext,"data-enrol-apply-tip":t.cleartext,onMouseDown:c=>c.preventDefault(),onClick:()=>{e.onSearch(""),o.current?.focus()},children:f(A,{name:"eraser"})}):null]}):f("button",{ref:g,type:"button",className:"enrol_apply-rview-iconbtn","aria-label":t.search,"data-enrol-apply-tip":t.search,onClick:()=>{m(null),i(!0)},children:f(A,{name:"search"})})}),f(Lt,{labels:t,id:a+"filters",hidden:d!=="filters",facets:e.facets,values:e.values,total:e.total,note:e.panelnote,onChange:e.onFacet,onClear:e.onClearFilters,onShow:()=>{m(null),R.current.filters?.focus()}})]}),x("div",{className:"enrol_apply-rview-chips","data-region":"enrol_apply-chips",hidden:G===0,children:[n.map(c=>x("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":M(t.removefilter,c.text),onClick:()=>e.onRemoveChip(c.key),children:[f("span",{children:c.text}),f(A,{name:"close"})]},c.key)),V?x("button",{type:"button",className:"enrol_apply-rview-chip","aria-label":M(t.removefilter,M(t.searchchip,e.search.trim())),onClick:()=>e.onSearch(""),children:[f("span",{children:M(t.searchchip,e.search.trim())}),f(A,{name:"close"})]}):null,G>1?f("button",{type:"button",className:"enrol_apply-rview-clearall",onClick:()=>{e.onClearAll(),i(!1)},children:t.clearall}):null]})]})};var Ft=e=>e.facet!==""&&e.options.length>0,Ht=e=>{try{let t=JSON.parse(e.values||"{}");return t!==null&&typeof t=="object"?t:{}}catch{return{}}},Tn=e=>e.reduce((t,n)=>({...t,...Ht(n)}),{}),Xe=e=>{if(!e.applied)return"";let t=Ht(e),n=t[`${e.name}_value`];if(n==null)return"";let r=Number(t[`${e.name}_operator`]??0);if(e.facet==="waited"){let a=Number(n)*Number(t[`${e.name}_unit`]??0)/86400;return r===2&&Number.isFinite(a)?String(a):""}return e.facet==="decider"?r===3?String(n):"":String(n)},Bt=(e,t,n)=>{let r=Ze(e,t);return t.facet==="waited"?(r[`${t.name}_operator`]=n===""?0:2,r[`${t.name}_value`]=n===""?0:Number(n),r[`${t.name}_unit`]=86400):t.facet==="decider"?(r[`${t.name}_operator`]=n===""?0:3,r[`${t.name}_value`]=n):(r[`${t.name}_operator`]=n===""?0:1,r[`${t.name}_value`]=n),r},Ze=(e,t)=>{let n=Tn(e);for(let r of Object.keys(n))r.startsWith(`${t.name}_`)&&delete n[r];return n},Vt=e=>{let t=Xe(e);return e.facet===""||t===""?"":e.options.find(n=>n.value===t)?.label??(e.facet==="decider"?t:"")};import{jsx as Ot,jsxs as On}from"react/jsx-runtime";var Dn=["auto","table","cards"],Wt=(e,t)=>t.facet==="waited"?e.waitedmore:t.facet==="decider"?e.decidedby:t.title,Fn=(e,t)=>{switch(t){case"decisions":return e.presetdecisions;case"answers":return e.presetanswers;case"groups":return e.presetgroups;default:return t}},Hn=(e,t,n)=>{let r={measure:e};return t!=="all"&&(r.scope=t),n!==""&&(r.search=n),encodeURIComponent(JSON.stringify(r))},Bn=(e,t)=>({name:t.name,title:Wt(e,t),options:t.options,groupings:t.groupings,find:t.facet==="decider"?{label:e.searchdeciders,nomatch:e.nodecidermatch}:{label:e.searchgroups,nomatch:e.nogroupmatch}}),Vn=(e,t)=>{let n=Vt(t),r=n!==""?Wt(e,t):t.title;return{key:t.name,text:n===""?r:ne(e.filterchip,{title:r,value:n})}},Ut=e=>{let{labels:t,filters:n}=e,r=e.measure==="resolution"?"submission:resolutiontime":"submission:waittime",a=i=>Ft(i)&&(i.facet!=="waited"||i.name===r),s=n.filter(a),o=n.filter(i=>!a(i)),g=i=>n.find(R=>R.name===i),p=e.downloadurl!==""&&e.formats.length>0,d=Hn(e.measure,e.scope,e.appliedsearch),m=p?e.formats.map(i=>({key:i.value,label:i.label,href:`${e.downloadurl}&parameters=${d}&download=${encodeURIComponent(i.value)}`})):[],h=e.presets.map(i=>({name:i.name,label:Fn(t,i.name),active:i.active}));return Ot(Dt,{labels:t,narrow:e.narrow,scopes:[{value:"all",label:t.all},{value:"awaiting",label:t.awaiting},{value:"decided",label:t.decided}],scope:e.scope,total:e.total,facets:s.map(i=>Bn(t,i)),values:Object.fromEntries(s.map(i=>[i.name,Xe(i)])),panelnote:o.length>0?On("p",{className:"enrol_apply-rview-note",children:[t.morefiltershelp," ",Ot("a",{href:e.tableurl,children:t.morefilters})]}):null,chips:n.filter(i=>i.applied).map(i=>Vn(t,i)),columns:e.columns,sets:h,usedefault:e.usedefault,haswidths:e.haswidths,view:e.view,views:Dn,tableurl:e.tableurl,downloads:m,search:e.search,searchmin:e.searchmin,searching:e.searching,onScope:i=>e.onScope(i),onSearch:e.onSearch,onFacet:(i,R)=>{let w=g(i);w&&e.onFilter(w,R)},onRemoveChip:i=>{let R=g(i);R&&e.onRemoveFilter(R)},onClearFilters:e.onClearFilters,onClearAll:e.onClearAll,onColumn:e.onColumn,onSet:i=>{let R=e.presets.find(w=>w.name===i);R&&e.onPreset(R)},onDefaultColumns:e.onDefaultColumns,onResetWidths:e.onResetWidths,onView:e.onView})};import{Fragment as Wn,jsx as $t}from"react/jsx-runtime";var Kt=(e,t)=>t?e.split(`
`).map(n=>n.trim()).filter(n=>n!==""):[e],qt=({parts:e})=>$t(Wn,{children:e.map((t,n)=>$t("span",{className:n===0?"enrol_apply-rview-c":"enrol_apply-rview-sub",title:Ie(t),dangerouslySetInnerHTML:{__html:t}},n))});import{Fragment as Qn,jsx as Ve}from"react/jsx-runtime";var Un=["outcome","wait","decided","group"],$n={applicant:260,outcome:180,submitted:132,wait:150,decided:172,group:200,method:170,route:170,comment:240,note:240},Kn=180,qn={applicant:160,submitted:104,decided:104},Gn=80,zn=800,Oe=vt($n,Kn,qn,Gn,zn),Y=(e,t)=>e.cells.find(n=>n.key===t)?.html??"",We=(e,t)=>Kt(t,Un.includes(e)),Ne=({column:e,html:t})=>Ve(qt,{parts:We(e,t)}),Ue=({row:e,label:t})=>e.reviewurl===""?null:Ve("a",{className:"btn btn-sm btn-outline-secondary enrol_apply-rview-review",href:e.reviewurl,children:t}),$e=({row:e,identity:t})=>Ve(Qn,{children:t.map(n=>{let r=Y(e,n.key);return r===""?null:Ve("span",{className:"enrol_apply-rview-sub",title:Ie(r),dangerouslySetInnerHTML:{__html:r}},n.key)})});import{Fragment as Gt,jsx as B,jsxs as Ee}from"react/jsx-runtime";var Yn=["applicant","outcome","submitted","wait","decided","group","method"],Ce=({row:e,column:t})=>{let n=We(t,Y(e,t))[0]??"";return n===""?null:B("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:n}})},Jn=(e,t,n,r)=>{let a=o=>t.some(g=>g.key===o),s=t.filter(o=>!Yn.includes(o.key)&&Y(r,o.key).trim()!=="");return{head:Ee(Gt,{children:[Ee("div",{className:"enrol_apply-rview-card-who",children:[B("span",{className:"enrol_apply-rview-card-name",dangerouslySetInnerHTML:{__html:Y(r,"applicant")}}),a("outcome")?B(Ce,{row:r,column:"outcome"}):null,B($e,{row:r,identity:n})]}),B(Ue,{row:r,label:e.review})]}),lines:Ee(Gt,{children:[Ee("div",{className:"enrol_apply-rview-card-line"+(r.open?" enrol_apply-rview-card-line--open":""),children:[a("wait")?B(Ce,{row:r,column:"wait"}):null,a("wait")&&a("submitted")?B("span",{"aria-hidden":"true",children:" \xB7 "}):null,a("submitted")?B(Ce,{row:r,column:"submitted"}):null]}),a("decided")?B("div",{className:"enrol_apply-rview-card-line",children:We("decided",Y(r,"decided")).map((o,g)=>B("span",{className:"enrol_apply-rview-inline",dangerouslySetInnerHTML:{__html:o}},g))}):null,a("group")||a("method")?Ee("div",{className:"enrol_apply-rview-card-line enrol_apply-rview-card-line--muted",children:[a("group")?B(Ce,{row:r,column:"group"}):null,a("group")&&a("method")&&Y(r,"group")!==""?B("span",{"aria-hidden":"true",children:" \xB7 "}):null,a("method")?B(Ce,{row:r,column:"method"}):null]}):null]}),fields:s.map(o=>({key:o.key,title:o.title,content:B(Ne,{column:o.key,html:Y(r,o.key)})}))}},zt=({labels:e,columns:t,identity:n,rows:r,single:a})=>B(ht,{labels:e,rows:r,single:a,renderCard:s=>Jn(e,t,n,s)});import{Fragment as Xn,jsx as re,jsxs as et}from"react/jsx-runtime";var jn=({row:e,longest:t})=>!e.open&&!e.decidedbyperson?null:re("span",{className:"enrol_apply-rview-waitbar"+(e.open?" enrol_apply-rview-waitbar--open":""),"aria-hidden":"true",children:re("span",{style:{width:`${Math.min(100,Math.round(e.wait/t*100))}%`}})}),Qt=({labels:e,columns:t,identity:n,rows:r,widths:a,sort:s,direction:o,onSort:g,onWidth:p,onWidthCommit:d})=>{let m=Math.max(86400,...r.filter(h=>h.open||h.decidedbyperson).map(h=>h.wait));return re(gt,{labels:e,columns:t,lead:"applicant",rows:r,widths:a,sizing:Oe,sort:s,direction:o,renderLead:(h,i)=>et("div",{className:"enrol_apply-rview-applicant",children:[et("div",{className:"enrol_apply-rview-applicant-text",children:[re(Ne,{column:i.key,html:Y(h,i.key)}),re($e,{row:h,identity:n})]}),re(Ue,{row:h,label:e.review})]}),renderCell:(h,i)=>et(Xn,{children:[re(Ne,{column:i.key,html:Y(h,i.key)}),i.key==="wait"?re(jn,{row:h,longest:m}):null]}),onSort:g,onWidth:p,onWidthCommit:d})};import{fetchOne as Se}from"@moodle/lms/core/ajax";var Yt=(e,t,n)=>Se({methodname:"enrol_apply_report_rows",args:{enrolid:e,...t,facets:n}}),Jt=(e,t,n,r)=>Se({methodname:"enrol_apply_report_summary",args:{enrolid:e,measure:t,scope:n,search:r}}),jt=(e,t)=>Se({methodname:"enrol_apply_set_report_preference",args:{enrolid:e,usedefault:t.usedefault,columns:t.columns,widths:t.widths}}),tt=(e,t,n)=>Se({methodname:"core_reportbuilder_set_filters",args:{reportid:e,parameters:JSON.stringify({measure:t}),values:JSON.stringify(n)}}),nt=(e,t)=>Se({methodname:"core_reportbuilder_filters_reset",args:{reportid:e,parameters:JSON.stringify({measure:t})}});import{Fragment as rr,jsx as ae,jsxs as at}from"react/jsx-runtime";var Zn=300,er=500,Xt="enrol_apply_report_view",tr=["applicant","outcome","submitted","wait","decided","group"],nr=e=>{let{labels:t,enrolid:n,reportid:r}=e,a=W(null),s=ut(a),[o,g]=J(()=>pt(Xt)),p={measure:e.measure,scope:"all",search:"",page:0,perpage:e.perpage,sort:"",direction:"desc"},d=W(p),[m,h]=J(p),[i,R]=J(""),[w,N]=J(null),[S,y]=J(!0),[V,G]=J(!1),[L,oe]=J(null),[U,$]=J(!0),[te,u]=J(!1),[v,I]=J({}),[H,c]=J({}),T=W(0),O=W(0),K=W(null),le=W(null),se=W(null),j=W(null),ot=W(!1),ce=W(null),Me=W(null),qe=W(null),xe=W(()=>{}),de=rt((l,C)=>{let k=++T.current;K.current||(K.current=ie("enrol_apply/report:rows")),y(!0),Yt(n,l,C).then(P=>{k===T.current&&(P.counted&&c(Object.fromEntries(P.filters.map(ge=>[ge.name,ge]))),ot.current||(ot.current=!0,I(Object.fromEntries(P.preference.widths.map(ge=>[ge.key,ge.width])))),P.page!==d.current.page&&(d.current={...d.current,page:P.page},h(d.current)),N(P),G(!1),y(!1))}).catch(()=>{k===T.current&&(G(!0),y(!1))})},[n]),pe=rt(l=>{let C=++O.current;le.current||(le.current=ie("enrol_apply/report:band")),$(!0),Jt(n,l.measure,l.scope,l.search).then(k=>{C===O.current&&(oe(k),u(!1),$(!1))}).catch(()=>{C===O.current&&(u(!0),$(!1))})},[n]),X=rt((l,C,k)=>{d.current={...d.current,...l},h(d.current),C&&de(d.current,k),k&&pe(d.current)},[de,pe]);Ke(()=>{!S&&K.current&&(K.current(),K.current=null)},[S,w,V]),Ke(()=>{!U&&le.current&&(le.current(),le.current=null)},[U,L,te]),Ke(()=>{de(d.current,!0),pe(d.current)},[de,pe]),Ke(()=>()=>{se.current!==null&&window.clearTimeout(se.current),j.current?.(),xe.current()},[]);let en=l=>{g(l),mt(Xt,l)},tn=l=>{R(l),se.current!==null&&(window.clearTimeout(se.current),se.current=null);let C=l.trim(),k=C.length>=e.searchmin?C:"";if(k===d.current.search){j.current?.(),j.current=null;return}if(l===""){j.current?.(),j.current=null,X({search:"",page:0},!0,!0);return}j.current||(j.current=ie("enrol_apply/report:search")),se.current=window.setTimeout(()=>{se.current=null,X({search:k,page:0},!0,!0),j.current?.(),j.current=null},Zn)},Le=l=>{let C=ie("enrol_apply/report:filters");l().then(()=>{X({page:0},!0,!0)}).catch(()=>{G(!0)}).finally(C)},me=(w?.filters??[]).map(l=>H[l.name]?{...l,options:H[l.name].options,groupings:H[l.name].groupings}:l),we=(l,C)=>{let k=ie("enrol_apply/report:preference");jt(n,l).then(()=>{C&&de(d.current,!1)}).catch(()=>{C&&G(!0)}).finally(k)},ye=w?.columns??[],Pe=ye.filter(l=>!l.fixed&&l.active).map(l=>l.key),ve=w?.preference.usedefault??!0,Ae=l=>Object.entries(l).map(([C,k])=>({key:C,width:k})),nn=(l,C)=>{let k=C?[...Pe,l]:Pe.filter(P=>P!==l);w&&N({...w,columns:w.columns.map(P=>P.key===l?{...P,active:C}:P),preference:{...w.preference,usedefault:!1,columns:k}}),we({usedefault:!1,columns:k,widths:Ae(v)},!0)},lt=()=>{ce.current!==null&&(window.clearTimeout(ce.current),ce.current=null);let l=qe.current;qe.current=null,l!==null&&we({usedefault:ve,columns:ve?[]:Pe,widths:Ae(l)},!1),Me.current?.(),Me.current=null};xe.current=lt;let rn=(l,C,k=!1)=>{let P={...v};if(C===null?delete P[l]:P[l]=C,I(P),qe.current=P,!k){lt();return}ce.current!==null&&window.clearTimeout(ce.current),Me.current||(Me.current=ie("enrol_apply/report:width")),ce.current=window.setTimeout(()=>xe.current(),er)},an=l=>{let C=new Set(l.columns);w&&N({...w,columns:w.columns.map(k=>k.fixed?k:{...k,active:C.has(k.key)||C.has("answers")&&k.key.startsWith("answer:")}),presets:w.presets.map(k=>({...k,active:k.name===l.name})),preference:{...w.preference,usedefault:!1,columns:l.columns}}),we({usedefault:!1,columns:l.columns,widths:Ae(v)},!0)},on=l=>{let k=(w?.sort??"")===l&&w?.direction==="asc"?"desc":"asc";X({sort:l,direction:k,page:0},!0,!1)},ln=l=>{let C=d.current,k=ye.some(P=>P.key==="wait"&&P.active);X({measure:l},C.scope!=="all"||C.sort==="wait"||k,!0)},sn=me.some(l=>l.applied)||m.search!==""||m.scope!=="all",st=ye.filter(l=>l.key.startsWith("identity:")),it=ye.filter(l=>l.key==="applicant"||!l.fixed&&l.active),ct=o==="cards"||o==="auto"&&s,dt=null;w!==null&&(dt=ct?ae(zt,{labels:t,columns:it,identity:st,rows:w.rows,single:s}):ae(Qt,{labels:t,columns:it,identity:st,rows:w.rows,widths:v,sort:w.sort,direction:w.direction,onSort:on,onWidth:rn,onWidthCommit:()=>xe.current()}));let cn=tr.map(l=>({key:l,title:l==="applicant"?t.applicant:"",sortable:!1,default:!0,active:!0,fixed:l==="applicant"}));return at("div",{className:"enrol_apply-rview-root",ref:a,"data-region":"enrol_apply-reportview",children:[w?.narrowed?ae("p",{className:"enrol_apply-rview-narrowed",children:t.narrowed}):null,ae(Et,{labels:t,summary:L,loading:U,error:te,compactHint:w?.narrowed??!1,measure:m.measure,methods:e.methods,queueurl:e.queueurl,pendingstatus:e.pendingstatus,onMeasure:ln,onRetry:()=>pe(d.current)}),ae(Ut,{labels:t,narrow:s,scope:m.scope,total:w===null?null:w.total,filters:me,columns:ye.filter(l=>!l.key.startsWith("identity:")||l.active),presets:w?.presets??[],usedefault:ve,haswidths:Object.keys(v).length>0,view:o,measure:m.measure,search:i,appliedsearch:m.search,searchmin:e.searchmin,searching:S,tableurl:e.tableurl,downloadurl:e.downloadurl,formats:e.formats,onScope:l=>X({scope:l,page:0},!0,!0),onSearch:tn,onFilter:(l,C)=>Le(()=>tt(r,d.current.measure,Bt(me,l,C))),onRemoveFilter:l=>Le(()=>tt(r,d.current.measure,Ze(me,l))),onClearFilters:()=>Le(()=>nt(r,d.current.measure)),onClearAll:()=>{R(""),d.current={...d.current,search:""},me.some(l=>l.applied)?Le(()=>nt(r,d.current.measure)):X({page:0},!0,!0)},onColumn:nn,onPreset:an,onDefaultColumns:()=>{w&&N({...w,preference:{...w.preference,usedefault:!0,columns:[]}}),we({usedefault:!0,columns:[],widths:Ae(v)},!0)},onResetWidths:()=>{I({}),we({usedefault:ve,columns:ve?[]:Pe,widths:[]},!1)},onView:en}),ae(_t,{labels:t,loading:S,loaded:w!==null,error:V,total:w?.total??0,filtered:sn,cards:ct,narrow:s,skeleton:{columns:cn,lead:"applicant",widths:v,sizing:Oe},onRetry:()=>de(d.current,!0),children:dt}),w!==null&&w.total>0?ae(yt,{labels:t,page:m.page,perpage:m.perpage,perpages:e.perpages,total:w.total,shown:w.rows.length,narrow:s,onPage:l=>X({page:l},!0,!1),onPerpage:l=>X({perpage:l,page:0},!0,!1)}):null,e.retention!==""?at("p",{className:"enrol_apply-rview-retention","data-region":"enrol_apply-retention",children:[e.retention,e.retentionurl!==""?at(rr,{children:[" ",ae("a",{href:e.retentionurl,children:t.retentionchange})]}):null]}):null]})},Zt=nr;import{jsx as or}from"react/jsx-runtime";var ar=e=>e.mount===void 0||e.mount==="report"?or(Zt,{...e}):null,Ba=ar;export{Ba as default};
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
 * the default columns and "Reset column widths".
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
 * predate the key. Another view is one more branch here and a template whose props name it; the
 * views share the data report's parts, so the bundle carries them once.
 *
 * @module     enrol_apply/views
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
//# sourceMappingURL=bundle.js.map
