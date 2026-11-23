import{useEffect as i,useState as a}from"react";/**
 * The narrow step and the reader's view choice, shared by every view of the data report.
 *
 * The narrow step is read from an element's own width, never the window's: the view sits in a page
 * whose drawers and blocks take a width the window does not show (AR-D9; stylelint refuses a
 * container query, which would otherwise say this in CSS).
 *
 * @module     enrol_apply/datareport/layout
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const d=576,w=t=>{const[e,s]=a(!1);return i(()=>{const r=t.current;if(!r||typeof ResizeObserver>"u")return;const o=new ResizeObserver(c=>{const n=c[0]?.contentRect.width??r.clientWidth;s(n>0&&n<d)});return o.observe(r),()=>o.disconnect()},[t]),e},l=t=>{try{const e=window.localStorage.getItem(t);return e==="table"||e==="cards"?e:"auto"}catch{return"auto"}},m=(t,e)=>{try{window.localStorage.setItem(t,e)}catch{}};export{d as NARROW,m as rememberView,l as storedView,w as useNarrow};
