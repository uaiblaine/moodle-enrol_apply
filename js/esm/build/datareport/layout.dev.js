var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
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
import { useEffect, useState } from "react";
const NARROW = 576;
const useNarrow = /* @__PURE__ */ __name((ref) => {
  const [narrow, setNarrow] = useState(false);
  useEffect(() => {
    const element = ref.current;
    if (!element || typeof ResizeObserver === "undefined") {
      return void 0;
    }
    const observer = new ResizeObserver((entries) => {
      const width = entries[0]?.contentRect.width ?? element.clientWidth;
      setNarrow(width > 0 && width < NARROW);
    });
    observer.observe(element);
    return () => observer.disconnect();
  }, [ref]);
  return narrow;
}, "useNarrow");
const storedView = /* @__PURE__ */ __name((key) => {
  try {
    const value = window.localStorage.getItem(key);
    return value === "table" || value === "cards" ? value : "auto";
  } catch {
    return "auto";
  }
}, "storedView");
const rememberView = /* @__PURE__ */ __name((key, chosen) => {
  try {
    window.localStorage.setItem(key, chosen);
  } catch {
  }
}, "rememberView");
export {
  NARROW,
  rememberView,
  storedView,
  useNarrow
};
//# sourceMappingURL=layout.dev.js.map
