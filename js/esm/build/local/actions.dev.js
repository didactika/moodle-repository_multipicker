var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Shared selection state, lazy directory trees and native draft imports.
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Selectors from "@moodle/lms/repository_multipicker/local/selectors";
import { request } from "@moodle/lms/repository_multipicker/local/repository";
import { options } from "@moodle/lms/repository_multipicker/local/native";
const createActions = /* @__PURE__ */ __name((root) => {
  const selected = /* @__PURE__ */ new Set();
  const excluded = /* @__PURE__ */ new Set();
  const parents = /* @__PURE__ */ new Map();
  const scope = root.dataset.scope;
  const element = /* @__PURE__ */ __name((selector) => root.querySelector(selector), "element");
  const results = element(Selectors.results);
  let path = "", parent = "", query = "";
  let page = 0, count = 0;
  let review = false, busy = false, pendingSearch = false;
  let approvedFile;
  const belongs = /* @__PURE__ */ __name((source, nodes) => {
    for (let depth = 0; source && depth < 100; depth++) {
      if (nodes.has(source)) {
        return true;
      }
      source = parents.get(source) || "";
    }
    return false;
  }, "belongs");
  const sync = /* @__PURE__ */ __name(() => {
    root.querySelectorAll(Selectors.check).forEach((check) => {
      check.checked = belongs(check.value, selected) && !belongs(check.value, excluded);
      check.disabled = busy;
      check.closest(Selectors.card).classList.toggle("border-primary", check.checked);
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
    element(Selectors.remove).hidden = !review || !root.querySelector(Selectors.reviewCheck + ":checked");
    element(Selectors.remove).disabled = busy;
    root.querySelectorAll(".multipicker-meta").forEach((metadata) => {
      ["border-top", "pt-2", "mt-2"].forEach((style) => metadata.classList.toggle(style, root.dataset.layout === "cards"));
    });
  }, "sync");
  const fields = /* @__PURE__ */ __name((action) => {
    const native = options();
    if (!native || native.env !== "filemanager") {
      throw new Error(root.dataset.unavailable);
    }
    return {
      action,
      sources: [...selected],
      excluded: [...excluded],
      draftid: native.itemid || 0,
      maxfiles: native.maxfiles ?? 1,
      basepath: native.savepath || "/",
      copymode: element(Selectors.copyMode).value,
      preservefolders: element(Selectors.preserve).checked ? 1 : 0
    };
  }, "fields");
  const registerChildren = /* @__PURE__ */ __name((container, source) => {
    container.querySelectorAll(":scope > .multipicker-items > .multipicker-node > .card " + Selectors.check).forEach((check) => parents.set(check.value, source));
  }, "registerChildren");
  const list = /* @__PURE__ */ __name(async () => {
    const requestedQuery = query;
    const response = await request(scope, { action: "list", path, page, query, draftid: options()?.itemid || 0 });
    if (requestedQuery !== query) {
      pendingSearch = true;
      return;
    }
    results.innerHTML = response.html;
    registerChildren(results, path);
    parent = response.parent || "";
    element(Selectors.previous).hidden = !response.previous;
    element(Selectors.next).hidden = !response.next;
    element(Selectors.location).textContent = response.location || "";
    review = false;
  }, "list");
  const run = /* @__PURE__ */ __name(async (operation) => {
    if (busy) {
      return;
    }
    busy = true;
    results.setAttribute("aria-busy", "true");
    element(Selectors.error).hidden = true;
    sync();
    try {
      await operation();
    } catch (error) {
      element(Selectors.error).textContent = error instanceof Error ? error.message : String(error);
      element(Selectors.error).hidden = false;
    } finally {
      busy = false;
      results.removeAttribute("aria-busy");
      sync();
      if (pendingSearch) {
        pendingSearch = false;
        void run(list);
      }
    }
  }, "run");
  const inspect = /* @__PURE__ */ __name(async () => {
    count = selected.size ? (await request(scope, fields("inspect"))).count || 0 : 0;
  }, "inspect");
  const changeSelection = /* @__PURE__ */ __name((mutate) => run(async () => {
    const oldSelection = [...selected], oldExclusions = [...excluded];
    mutate();
    sync();
    try {
      await inspect();
    } catch (error) {
      selected.clear();
      excluded.clear();
      oldSelection.forEach((source) => selected.add(source));
      oldExclusions.forEach((source) => excluded.add(source));
      throw error;
    }
  }), "changeSelection");
  const navigate = /* @__PURE__ */ __name((source) => run(async () => {
    const previousPath = path;
    path = source;
    page = 0;
    try {
      await list();
    } catch (error) {
      path = previousPath;
      throw error;
    }
  }), "navigate");
  const layout = /* @__PURE__ */ __name((view) => {
    if (!["cards", "list", "tree"].includes(view)) {
      return;
    }
    root.dataset.layout = view;
    root.querySelectorAll(Selectors.layout).forEach((button) => {
      const active = button.dataset.multipickerLayout === view;
      button.classList.toggle("active", active);
      button.setAttribute("aria-pressed", String(active));
    });
    sync();
  }, "layout");
  const tree = /* @__PURE__ */ __name((button, nextPage = 0) => run(async () => {
    const node = button.closest(Selectors.node);
    const children = node.querySelector(Selectors.children);
    if (children.dataset.loaded && !nextPage) {
      children.hidden = !children.hidden;
    } else {
      const source = node.dataset.source;
      const response = await request(scope, { action: "list", path: source, page: nextPage, draftid: options()?.itemid || 0 });
      node.querySelector(Selectors.treePage)?.remove();
      children.insertAdjacentHTML("beforeend", response.html);
      registerChildren(children, source);
      if (response.next) {
        const more = document.createElement("button");
        more.type = "button";
        more.className = "btn btn-sm btn-link";
        more.dataset.multipickerTreepage = String(nextPage + 1);
        more.textContent = element(Selectors.next).textContent;
        children.append(more);
      }
      children.dataset.loaded = "true";
      children.hidden = false;
    }
    node.querySelector(Selectors.open)?.setAttribute("aria-expanded", String(!children.hidden));
  }), "tree");
  element(Selectors.reference).disabled = Math.floor((options()?.return_types || 0) / 4) % 2 !== 1;
  return {
    load: /* @__PURE__ */ __name(() => run(list), "load"),
    toggle: /* @__PURE__ */ __name((check) => changeSelection(() => {
      if (check.checked) {
        selected.add(check.value);
        excluded.delete(check.value);
      } else {
        selected.delete(check.value);
        if (belongs(check.value, selected)) {
          excluded.add(check.value);
        }
      }
    }), "toggle"),
    clear: /* @__PURE__ */ __name(() => changeSelection(() => {
      selected.clear();
      excluded.clear();
    }), "clear"),
    selectAll: /* @__PURE__ */ __name(() => run(async () => {
      const old = [...selected], oldExcluded = [...excluded];
      try {
        let currentPage = 0, more = true;
        while (more) {
          const response = await request(scope, {
            action: "list",
            path,
            query,
            page: currentPage++,
            draftid: options()?.itemid || 0
          });
          const fragment = document.createElement("div");
          fragment.innerHTML = response.html;
          registerChildren(fragment, path);
          fragment.querySelectorAll(Selectors.check).forEach((check) => selected.add(check.value));
          if (selected.size > 500 || currentPage > 100) {
            throw new Error(root.dataset.limit);
          }
          more = !!response.next;
        }
        excluded.clear();
        await inspect();
      } catch (error) {
        selected.clear();
        excluded.clear();
        old.forEach((source) => selected.add(source));
        oldExcluded.forEach((source) => excluded.add(source));
        throw error;
      }
    }), "selectAll"),
    open: /* @__PURE__ */ __name((button) => root.dataset.layout === "tree" ? tree(button) : navigate(button.dataset.multipickerOpen), "open"),
    treePage: /* @__PURE__ */ __name((button) => tree(button, Number(button.dataset.multipickerTreepage)), "treePage"),
    layout,
    search: /* @__PURE__ */ __name((value) => {
      query = value;
      page = 0;
      if (busy) {
        pendingSearch = true;
      } else {
        void run(list);
      }
    }, "search"),
    clearSearch: /* @__PURE__ */ __name(() => {
      element(Selectors.query).value = "";
      query = "";
      page = 0;
      if (busy) {
        pendingSearch = true;
      } else {
        void run(list);
      }
    }, "clearSearch"),
    copyOptions: /* @__PURE__ */ __name(() => run(inspect), "copyOptions"),
    back: /* @__PURE__ */ __name(() => review ? run(list) : navigate(parent), "back"),
    paginate: /* @__PURE__ */ __name((offset) => run(async () => {
      page += offset;
      await list();
    }), "paginate"),
    review: /* @__PURE__ */ __name(() => run(async () => {
      const response = await request(scope, fields("review"));
      results.innerHTML = response.html;
      review = true;
      approvedFile = response.file;
      count = response.count || 0;
    }), "review"),
    reviewToggle: /* @__PURE__ */ __name((button) => {
      const children = button.closest(Selectors.reviewNode).querySelector(Selectors.reviewChildren);
      children.hidden = !children.hidden;
      button.setAttribute("aria-expanded", String(!children.hidden));
    }, "reviewToggle"),
    reviewCheck: /* @__PURE__ */ __name((check) => {
      if (check.matches(Selectors.reviewGroup)) {
        check.closest(Selectors.reviewNode).querySelectorAll(Selectors.reviewCheck).forEach((child) => {
          child.checked = check.checked;
        });
      }
      root.querySelectorAll(Selectors.reviewGroup).forEach((group) => {
        const files = [...group.closest(Selectors.reviewNode).querySelectorAll(Selectors.reviewCheck)].filter((file) => file.value);
        const checked = files.filter((file) => file.checked).length;
        group.checked = files.length > 0 && checked === files.length;
        group.indeterminate = checked > 0 && checked < files.length;
      });
      sync();
    }, "reviewCheck"),
    remove: /* @__PURE__ */ __name(() => run(async () => {
      const previous = [...excluded];
      root.querySelectorAll(Selectors.reviewCheck + ":checked").forEach((check) => {
        if (check.value) {
          excluded.add(check.value);
        }
      });
      try {
        const response = await request(scope, fields("review"));
        results.innerHTML = response.html;
        count = response.count || 0;
      } catch (error) {
        excluded.clear();
        previous.forEach((source) => excluded.add(source));
        throw error;
      }
    }), "remove"),
    import: /* @__PURE__ */ __name(() => run(async () => {
      const response = await request(scope, fields("approve"));
      approvedFile = response.file;
      window.parent.M.core_filepicker.select_file(approvedFile);
      const confirmation = window.parent.document.querySelector(".file-picker.fp-select .fp-select-confirm");
      if (!confirmation) {
        throw new Error(root.dataset.unavailable);
      }
      confirmation.click();
    }), "import")
  };
}, "createActions");
export {
  createActions
};
//# sourceMappingURL=actions.dev.js.map
