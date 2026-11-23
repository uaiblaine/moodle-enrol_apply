var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Column widths: a view's policy built from its tables, and the width a column is drawn at.
 *
 * @module     enrol_apply/datareport/sizing
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const makeSizing = /* @__PURE__ */ __name((defaults, fallback, minimums, minimum, maximum) => ({
  /**
   * A column's default width.
   *
   * @param {string} key The column key.
   * @returns {number} Pixels.
   */
  defaultWidth: /* @__PURE__ */ __name((key) => defaults[key] ?? fallback, "defaultWidth"),
  /**
   * A column's minimum width.
   *
   * @param {string} key The column key.
   * @returns {number} Pixels.
   */
  minimumWidth: /* @__PURE__ */ __name((key) => minimums[key] ?? minimum, "minimumWidth"),
  maximum
}), "makeSizing");
const widthOf = /* @__PURE__ */ __name((sizing, widths, key) => Math.min(sizing.maximum, Math.max(sizing.minimumWidth(key), widths[key] ?? sizing.defaultWidth(key))), "widthOf");
export {
  makeSizing,
  widthOf
};
//# sourceMappingURL=sizing.dev.js.map
