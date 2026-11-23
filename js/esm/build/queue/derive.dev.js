var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
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
import { fill } from "../datareport/str";
const derive = /* @__PURE__ */ __name((rows, config) => {
  const decides = config.decision !== null && rows !== null && rows.candecide;
  return {
    decides,
    notingroup: Boolean(rows?.notingroup),
    narrowed: Boolean(rows?.narrowed),
    viewonly: rows !== null && !decides,
    groupdefault: rows?.groupdefault ?? "",
    allcolumns: rows?.columns ?? [],
    usedefault: rows?.preference.usedefault ?? true,
    presets: rows?.presets ?? [],
    total: rows?.total ?? 0,
    list: (rows?.rows ?? []).map((row) => ({ ...row, id: row.userenrolmentid }))
  };
}, "derive");
const chooseRows = /* @__PURE__ */ __name((selected, setSelected, derived, labels) => {
  const { list, decides } = derived;
  const locked = new Set(list.filter((row) => !row.selectable).map((row) => row.id));
  const chosen = list.filter((row) => selected.has(row.id));
  const onSelect = /* @__PURE__ */ __name((id, on) => setSelected((before) => {
    const next = new Set(before);
    if (on && !locked.has(id)) {
      next.add(id);
    } else {
      next.delete(id);
    }
    return next;
  }), "onSelect");
  const onSelectPage = /* @__PURE__ */ __name((on) => setSelected(
    () => on ? new Set(list.filter((row) => !locked.has(row.id)).map((row) => row.id)) : /* @__PURE__ */ new Set()
  ), "onSelectPage");
  const boxLabel = /* @__PURE__ */ __name((row) => fill(labels.selectapplicant, row.applicant.fullname), "boxLabel");
  const selection = decides ? {
    selected,
    locked,
    /**
     *
     * @param row
     */
    label: boxLabel,
    pagelabel: labels.selectpage,
    onSelect,
    onSelectPage
  } : void 0;
  return { locked, chosen, selection };
}, "chooseRows");
export {
  chooseRows,
  derive
};
//# sourceMappingURL=derive.dev.js.map
