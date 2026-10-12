var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Chooser initialization.
 * @module repository_multipicker/local/controller
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Selectors from "@moodle/lms/repository_multipicker/local/selectors";
import { createActions } from "@moodle/lms/repository_multipicker/local/actions";
import { register } from "@moodle/lms/repository_multipicker/local/listeners";
import { mount } from "@moodle/lms/repository_multipicker/local/native";
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
//# sourceMappingURL=controller.dev.js.map
