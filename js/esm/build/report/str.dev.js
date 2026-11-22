var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Words and numbers: filling the strings the props carry, and the wait as the report words it.
 *
 * There is no core/str for an ES module, so the strings arrive translated with their placeholders
 * ("{$a} applications"), and only the client knows the value.
 *
 * @module     enrol_apply/report/str
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const fill = /* @__PURE__ */ __name((template, value) => (template || "").split("{$a}").join(value), "fill");
const fillObject = /* @__PURE__ */ __name((template, values) => Object.entries(values).reduce((text, [name, value]) => text.split(`{$a->${name}}`).join(value), template || ""), "fillObject");
const fillCount = /* @__PURE__ */ __name((labels, key, count, shown) => fill(count === 1 && labels[`${key}_one`] ? labels[`${key}_one`] : labels[key], shown), "fillCount");
const num = /* @__PURE__ */ __name((value) => {
  const lang = document.documentElement.lang || void 0;
  try {
    return new Intl.NumberFormat(lang).format(value);
  } catch {
    return String(value);
  }
}, "num");
const duration = /* @__PURE__ */ __name((labels, seconds) => {
  const s = Math.max(0, seconds);
  if (s < 3600) {
    return fill(labels.waitminutes, num(Math.max(1, Math.round(s / 60))));
  }
  if (s < 86400) {
    return fill(labels.waithours, num(Math.round(s / 3600)));
  }
  let days = Math.floor(s / 86400);
  let hours = Math.round((s - days * 86400) / 3600);
  if (hours === 24) {
    days++;
    hours = 0;
  }
  if (hours > 0 && days < 10) {
    return fillObject(labels.waitdayshours, { days: num(days), hours: num(hours) });
  }
  return fill(labels.waitdays, num(days));
}, "duration");
const plain = /* @__PURE__ */ __name((html) => {
  const template = document.createElement("template");
  template.innerHTML = html;
  return (template.content.textContent || "").replace(/\s+/g, " ").trim();
}, "plain");
export {
  duration,
  fill,
  fillCount,
  fillObject,
  num,
  plain
};
//# sourceMappingURL=str.dev.js.map
