var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Scoped selection and navigation actions.
 * @module repository_multipicker/local/actions
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Selectors from "@moodle/lms/repository_multipicker/local/selectors";
import { request } from "@moodle/lms/repository_multipicker/local/repository";
const createActions = /* @__PURE__ */ __name((root) => {
  const selected = /* @__PURE__ */ new Set();
  const scope = root.dataset.scope;
  const results = root.querySelector(Selectors.results);
  let path = "";
  let parent = "";
  let page = 0;
  let review = false;
  let approvedFile;
  let busy = false;
  const picker = /* @__PURE__ */ __name(() => window.parent.M?.core_filepicker?.active_filepicker, "picker");
  const sync = /* @__PURE__ */ __name(() => {
    root.querySelectorAll(Selectors.check).forEach((check) => {
      check.checked = selected.has(check.value);
      check.closest(Selectors.card).classList.toggle("border-primary", check.checked);
    });
    root.querySelector(Selectors.count).textContent = String(selected.size);
    root.querySelector(Selectors.review).disabled = !selected.size || busy;
    root.querySelector(Selectors.review).hidden = review;
    root.querySelector(Selectors.import).hidden = !review;
    root.querySelector(Selectors.import).disabled = busy;
    root.querySelector(Selectors.selectionActions).hidden = review;
    root.querySelector(Selectors.pagination).hidden = review;
    root.querySelector(Selectors.back).hidden = !review && !parent;
  }, "sync");
  const run = /* @__PURE__ */ __name(async (operation) => {
    if (busy) {
      return;
    }
    busy = true;
    results.setAttribute("aria-busy", "true");
    root.querySelector(Selectors.error).hidden = true;
    sync();
    try {
      await operation();
    } catch (error) {
      root.querySelector(Selectors.error).textContent = error instanceof Error ? error.message : String(error);
      root.querySelector(Selectors.error).hidden = false;
    } finally {
      busy = false;
      results.removeAttribute("aria-busy");
      sync();
    }
  }, "run");
  const list = /* @__PURE__ */ __name(async () => {
    const response = await request(scope, { action: "list", path, page });
    results.innerHTML = response.html ?? "";
    parent = response.parent ?? "";
    root.querySelector(Selectors.previous).hidden = !response.previous;
    root.querySelector(Selectors.next).hidden = !response.next;
    root.querySelector(Selectors.location).textContent = response.location ?? "";
    review = false;
  }, "list");
  const fields = /* @__PURE__ */ __name((action) => {
    const options = picker()?.options;
    if (!options || options.env !== "filemanager") {
      throw new Error("Open this repository from a native filemanager.");
    }
    return { action, sources: [...selected], maxfiles: options.maxfiles ?? 1, basepath: options.savepath || "/" };
  }, "fields");
  return {
    load: /* @__PURE__ */ __name(() => run(list), "load"),
    toggle: /* @__PURE__ */ __name((check) => {
      if (check.checked) {
        selected.add(check.value);
      } else {
        selected.delete(check.value);
      }
      sync();
    }, "toggle"),
    clear: /* @__PURE__ */ __name(() => {
      selected.clear();
      sync();
    }, "clear"),
    selectAll: /* @__PURE__ */ __name(() => {
      root.querySelectorAll(Selectors.check).forEach((check) => selected.add(check.value));
      sync();
    }, "selectAll"),
    open: /* @__PURE__ */ __name((source) => run(async () => {
      path = source;
      page = 0;
      await list();
    }), "open"),
    back: /* @__PURE__ */ __name(() => run(async () => {
      if (!review) {
        path = parent;
        page = 0;
      }
      await list();
    }), "back"),
    paginate: /* @__PURE__ */ __name((offset) => run(async () => {
      page += offset;
      await list();
    }), "paginate"),
    review: /* @__PURE__ */ __name(() => run(async () => {
      const response = await request(scope, fields("review"));
      results.innerHTML = response.html ?? "";
      review = true;
      approvedFile = response.file;
    }), "review"),
    import: /* @__PURE__ */ __name(() => run(async () => {
      const response = await request(scope, fields("approve"));
      approvedFile = response.file;
      if (!approvedFile) {
        throw new Error("Missing approval descriptor.");
      }
      window.parent.M.core_filepicker.select_file(approvedFile);
      const confirmation = window.parent.document.querySelector(".fp-select-confirm");
      if (!confirmation) {
        throw new Error("The native picker confirmation is unavailable.");
      }
      confirmation.click();
    }), "import")
  };
}, "createActions");
export {
  createActions
};
//# sourceMappingURL=actions.dev.js.map
