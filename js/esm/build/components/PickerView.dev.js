var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Render browser and review state in the native file picker.
 * @module repository_multipicker/components/PickerView
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Selectors from "../lib/selectors";
const renderPicker = /* @__PURE__ */ __name((root, state) => {
  const { selected, excluded, parents, folderCounts, reviewChecks, reviewPaths, count, review, busy, parent, query } = state;
  const element = /* @__PURE__ */ __name((selector) => root.querySelector(selector), "element");
  const isSelected = /* @__PURE__ */ __name((source) => {
    for (let depth = 0; source && depth < 100; depth++) {
      if (excluded.has(source)) {
        return false;
      }
      if (selected.has(source)) {
        return true;
      }
      source = parents.get(source) || "";
    }
    return false;
  }, "isSelected");
  const reviewedState = /* @__PURE__ */ __name((check) => {
    if (reviewChecks.has(check.value)) {
      return reviewChecks.get(check.value);
    }
    const path = check.closest(Selectors.reviewNode).dataset.path || "/";
    let checked = false, length = -1;
    reviewChecks.forEach((value, key) => {
      if (key.startsWith("folder:") && path.startsWith(key.slice(7)) && key.length > length) {
        checked = value;
        length = key.length;
      }
    });
    return checked;
  }, "reviewedState");
  root.querySelectorAll(Selectors.check).forEach((check) => {
    check.checked = isSelected(check.value);
    check.indeterminate = false;
    check.disabled = busy;
    check.closest(Selectors.card).classList.toggle("border-primary", check.checked);
  });
  [...root.querySelectorAll(Selectors.node)].reverse().forEach((node) => {
    const children = node.querySelector(Selectors.children);
    const check = node.querySelector(Selectors.check);
    if (!children || !check) {
      return;
    }
    const files = [...children.querySelectorAll(Selectors.check)];
    const any = files.some((file) => file.checked || file.indeterminate);
    const all = files.length > 0 && files.every((file) => file.checked && !file.indeterminate);
    check.indeterminate = children.dataset.complete === "true" ? any && !all : !check.checked && (folderCounts[check.value] || 0) > 0;
    if (children.dataset.complete === "true") {
      check.checked = all;
    }
    check.closest(Selectors.card).classList.toggle("border-primary", check.checked || check.indeterminate);
  });
  element(Selectors.count).textContent = String(count);
  element(Selectors.review).disabled = !count || busy;
  element(Selectors.review).hidden = review;
  element(Selectors.import).hidden = !review;
  element(Selectors.import).disabled = busy || !count;
  element(Selectors.selectionActions).hidden = review;
  element(Selectors.selectionActions).classList.toggle("d-flex", !review);
  element(Selectors.filters).hidden = review;
  element(Selectors.pagination).hidden = review;
  element(Selectors.pagination).classList.toggle("d-flex", !review);
  element(Selectors.back).hidden = !review && !parent;
  element(Selectors.clearSearch).disabled = !query;
  element(Selectors.remove).hidden = !review || ![...reviewChecks.values()].some(Boolean);
  element(Selectors.remove).disabled = busy;
  root.querySelectorAll(Selectors.reviewCheck).forEach((check) => {
    const path = check.closest(Selectors.reviewNode).dataset.path || "/";
    reviewPaths.set(check.value, path);
    check.checked = !!reviewedState(check);
    check.indeterminate = false;
    if (check.matches(Selectors.reviewGroup)) {
      reviewChecks.forEach((value, key) => {
        if (key !== check.value && (reviewPaths.get(key) || key.slice(7)).startsWith(path) && value !== check.checked) {
          check.indeterminate = true;
        }
      });
    }
  });
  root.querySelectorAll("[data-multipicker-folder-count]").forEach((badge) => {
    const total = folderCounts[badge.dataset.multipickerFolderCount] || 0;
    badge.textContent = root.dataset.folderselection.replace("{$a}", String(total));
  });
}, "renderPicker");
export {
  renderPicker
};
//# sourceMappingURL=PickerView.dev.js.map
