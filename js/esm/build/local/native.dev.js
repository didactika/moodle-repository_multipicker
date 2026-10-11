var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Native filemanager options and embedded chooser presentation.
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const options = /* @__PURE__ */ __name(() => window.parent.M?.core_filepicker?.active_filepicker?.options, "options");
const mount = /* @__PURE__ */ __name((root) => {
  document.body.classList.add("multipicker-embedded");
  const host = window.frameElement?.closest(".file-picker");
  if (host) {
    host.classList.add("multipicker-native-host");
    const background = window.parent.getComputedStyle(host).backgroundColor;
    if (background !== "rgba(0, 0, 0, 0)") {
      root.style.setProperty("--multipicker-background", background);
      document.body.style.setProperty("--multipicker-background", background);
    }
    window.addEventListener("pagehide", () => host.classList.remove("multipicker-native-host"), { once: true });
  }
}, "mount");
export {
  mount,
  options
};
//# sourceMappingURL=native.dev.js.map
