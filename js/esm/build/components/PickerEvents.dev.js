var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Delegated chooser listeners, independent of layout and result refreshes.
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Selectors from "../lib/selectors";
const register = /* @__PURE__ */ __name((root, actions) => {
  let timer;
  root.addEventListener("input", (event) => {
    const target = event.target;
    if (target instanceof HTMLInputElement && target.matches(Selectors.query)) {
      clearTimeout(timer);
      const value = target.value;
      timer = setTimeout(() => actions.search(value), 250);
    }
  });
  root.addEventListener("change", (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }
    const check = target.closest(Selectors.check);
    if (check) {
      actions.toggle(check);
    }
    const reviewed = target.closest(Selectors.reviewCheck);
    if (reviewed) {
      actions.reviewCheck(reviewed);
    }
    if (target.matches(Selectors.copyMode + "," + Selectors.preserve)) {
      actions.copyOptions();
    }
  });
  root.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }
    const open = target.closest(Selectors.open);
    const layout = target.closest(Selectors.layout);
    const more = target.closest(Selectors.treePage);
    const reviewToggle = target.closest(Selectors.reviewToggle);
    const reviewDelete = target.closest(Selectors.reviewDelete);
    const reviewPage = target.closest(Selectors.reviewPage);
    if (reviewDelete) {
      actions.remove([reviewDelete.dataset.multipickerDelete]);
    } else if (reviewPage) {
      actions.reviewPage(reviewPage);
    } else if (reviewToggle) {
      actions.reviewToggle(reviewToggle);
    } else if (open) {
      actions.open(open);
    } else if (more) {
      actions.treePage(more);
    } else if (layout) {
      actions.layout(layout.dataset.multipickerLayout);
    } else if (target.closest(Selectors.back)) {
      actions.back();
    } else if (target.closest(Selectors.clear)) {
      actions.clear();
    } else if (target.closest(Selectors.clearSearch)) {
      clearTimeout(timer);
      actions.clearSearch();
    } else if (target.closest(Selectors.selectall)) {
      actions.selectAll();
    } else if (target.closest(Selectors.previous)) {
      actions.paginate(-1);
    } else if (target.closest(Selectors.next)) {
      actions.paginate(1);
    } else if (target.closest(Selectors.review)) {
      actions.review();
    } else if (target.closest(Selectors.import)) {
      actions.import();
    } else if (target.closest(Selectors.remove)) {
      actions.remove();
    } else if (!target.closest("input, label, button, a")) {
      const check = target.closest(Selectors.card)?.querySelector(Selectors.check);
      if (check && !check.disabled) {
        check.checked = !check.checked;
        actions.toggle(check);
      }
      const reviewed = target.closest(Selectors.reviewRow)?.querySelector(Selectors.reviewCheck);
      if (reviewed) {
        reviewed.checked = !reviewed.checked;
        actions.reviewCheck(reviewed);
      }
    }
  });
}, "register");
export {
  register
};
//# sourceMappingURL=PickerEvents.dev.js.map
