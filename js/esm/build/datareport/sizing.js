/**
 * Column widths: a view's policy built from its tables, and the width a column is drawn at.
 *
 * @module     enrol_apply/datareport/sizing
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const u=(m,n,i,t,e)=>({defaultWidth:r=>m[r]??n,minimumWidth:r=>i[r]??t,maximum:e}),d=(m,n,i)=>Math.min(m.maximum,Math.max(m.minimumWidth(i),n[i]??m.defaultWidth(i)));export{u as makeSizing,d as widthOf};
