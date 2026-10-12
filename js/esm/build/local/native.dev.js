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
  root.ownerDocument.body.classList.add("multipicker-embedded");
  const parent = window.parent;
  const object = window.frameElement;
  const host = object?.closest(".file-picker");
  if (object && host) {
    parent.require(["repository_multipicker/local/picker_host"], (adapter) => adapter.mount(object));
  }
}, "mount");
export {
  mount,
  options
};
//# sourceMappingURL=native.dev.js.map
