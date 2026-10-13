var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Chooser initialization.
 * @module repository_multipicker/Picker
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Selectors from "./lib/selectors";
import { createActions } from "./engine/actions";
import { register } from "./components/PickerEvents";
import { mount } from "./services/native";
const init = /* @__PURE__ */ __name((scope) => {
  const root = document.querySelector(Selectors.root);
  if (!root || root.dataset.scope !== scope || root.dataset.ready) {
    return;
  }
  root.dataset.ready = "true";
  mount(root);
  const actions = createActions(root);
  register(root, actions);
  actions.load();
}, "init");
export {
  init
};
//# sourceMappingURL=Picker.dev.js.map
