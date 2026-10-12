// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

/**
 * Shared selection state, lazy directory trees and native draft imports.
 * @module repository_multipicker/local/actions
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Selectors from 'repository_multipicker/local/selectors';
import {request} from 'repository_multipicker/local/repository';
import {options} from 'repository_multipicker/local/native';
/**
 * Create actions for one embedded chooser.
 * @param {HTMLElement} root Chooser root.
 * @returns {Object} Scoped actions.
 */
export const createActions = (root) => {
    const selected = new Set();
    const excluded = new Set();
    const parents = new Map();
    let listingSources;
    let folderCounts = {};
    const reviewChecks = new Map();
    const reviewPaths = new Map();
    const scope = root.dataset.scope;
    const element = (selector) => root.querySelector(selector);
    const results = element(Selectors.results);
    let path = '';
    let parent = '';
    let query = '';
    let page = 0;
    let count = 0;
    let review = false;
    let busy = false;
    let pendingSearch = false;
    let approvedFile;
    const belongs = (source, nodes) => {
        for (let depth = 0; source && depth < 100; depth++) {
            if (nodes.has(source)) {
                return true;
            }
            source = parents.get(source) || '';
        }
        return false;
    };
    const isSelected = (source) => {
        for (let depth = 0; source && depth < 100; depth++) {
            if (excluded.has(source)) {
                return false;
            }
            if (selected.has(source)) {
                return true;
            }
            source = parents.get(source) || '';
        }
        return false;
    };
    const reviewedState = (check) => {
        if (reviewChecks.has(check.value)) {
            return reviewChecks.get(check.value);
        }
        const path = check.closest(Selectors.reviewNode).dataset.path || '/';
        let state = false;
        let length = -1;
        reviewChecks.forEach((selected, key) => {
            if (key.startsWith('folder:') && path.startsWith(key.slice(7)) && key.length > length) {
                state = selected;
                length = key.length;
            }
        });
        return state;
    };
    const sync = () => {
        root.querySelectorAll(Selectors.check).forEach(check => {
            check.checked = isSelected(check.value);
            check.indeterminate = false;
            check.disabled = busy;
            check.closest(Selectors.card).classList.toggle('border-primary', check.checked);
        });
        // Derive visible directory state without fetching unopened descendants.
        [...root.querySelectorAll(Selectors.node)].reverse().forEach(node => {
            const children = node.querySelector(Selectors.children);
            const check = node.querySelector(Selectors.check);
            if (!children || !check) {
                return;
            }
            const files = [...children.querySelectorAll(Selectors.check)];
            const any = files.some(file => file.checked || file.indeterminate);
            const all = files.length > 0 && files.every(file => file.checked && !file.indeterminate);
            check.indeterminate = children.dataset.complete === 'true'
                ? any && !all : !check.checked && (folderCounts[check.value] || 0) > 0;
            if (children.dataset.complete === 'true') {
                check.checked = all;
            }
            check.closest(Selectors.card).classList.toggle('border-primary', check.checked || check.indeterminate);
        });
        element(Selectors.count).textContent = String(count);
        element(Selectors.review).disabled = !count || busy;
        element(Selectors.review).hidden = review;
        element(Selectors.import).hidden = !review;
        element(Selectors.import).disabled = busy || !count;
        element(Selectors.selectionActions).hidden = review;
        element(Selectors.selectionActions).classList.toggle('d-flex', !review);
        element(Selectors.filters).hidden = review;
        element(Selectors.pagination).hidden = review;
        element(Selectors.pagination).classList.toggle('d-flex', !review);
        element(Selectors.back).hidden = !review && !parent;
        element(Selectors.clearSearch).disabled = !query;
        element(Selectors.remove).hidden = !review || ![...reviewChecks.values()].some(Boolean);
        element(Selectors.remove).disabled = busy;
        root.querySelectorAll(Selectors.reviewCheck).forEach(check => {
            const path = check.closest(Selectors.reviewNode).dataset.path || '/';
            reviewPaths.set(check.value, path);
            check.checked = !!reviewedState(check);
            check.indeterminate = false;
            if (check.matches(Selectors.reviewGroup)) {
                reviewChecks.forEach((state, key) => {
                    if (key !== check.value && (reviewPaths.get(key) || key.slice(7)).startsWith(path)
                            && state !== check.checked) {
                        check.indeterminate = true;
                    }
                });
            }
        });
        root.querySelectorAll('[data-multipicker-folder-count]').forEach(badge => {
            const total = folderCounts[badge.dataset.multipickerFolderCount] || 0;
            badge.textContent = root.dataset.folderselection.replace('{$a}', String(total));
        });
    };
    const fields = (action) => {
        const native = options();
        if (!native || native.env !== 'filemanager') {
            throw new Error(root.dataset.unavailable);
        }
        return {action, sources: [...selected], excluded: [...excluded], draftid: native.itemid || 0,
            maxfiles: native.maxfiles ?? 1, basepath: native.savepath || '/',
            copymode: element(Selectors.copyMode).value,
            preservefolders: element(Selectors.preserve).checked ? 1 : 0};
    };
    const registerChildren = (container, source) => {
        container.querySelectorAll(':scope > .multipicker-items > .multipicker-node > .card ' +
            Selectors.check).forEach(check => parents.set(check.value, source));
    };
    const list = async() => {
        const requestedQuery = query;
        const response = await request(scope, {action: 'list', path, page, query, draftid: options()?.itemid || 0});
        if (requestedQuery !== query) {
            pendingSearch = true;
            return;
        }
        listingSources = response.sources;
        results.innerHTML = response.html;
        registerChildren(results, path);
        parent = response.parent || '';
        element(Selectors.previous).hidden = !response.previous;
        element(Selectors.next).hidden = !response.next;
        element(Selectors.location).textContent = response.location || '';
        element(Selectors.location).title = response.location || '';
        review = false;
    };
    const run = async(operation) => {
        if (busy) {
            return;
        }
        busy = true;
        results.setAttribute('aria-busy', 'true');
        element(Selectors.error).hidden = true;
        sync();
        try {
            await operation();
        } catch (error) {
            element(Selectors.error).textContent = error instanceof Error ? error.message : String(error);
            element(Selectors.error).hidden = false;
        } finally {
            busy = false;
            results.removeAttribute('aria-busy');
            sync();
            if (pendingSearch) {
                pendingSearch = false;
                void run(list);
            }
        }
    };
    const inspect = async() => {
        const response = selected.size ? await request(scope, fields('inspect')) : {count: 0, counts: {}};
        count = response.count || 0;
        folderCounts = response.counts || {};
    };
    const changeSelection = (mutate) => run(async() => {
        const oldSelection = [...selected];
        const oldExclusions = [...excluded];
        mutate();
        sync();
        try {
            await inspect();
        } catch (error) {
            selected.clear();
            excluded.clear();
            oldSelection.forEach(source => selected.add(source));
            oldExclusions.forEach(source => excluded.add(source));
            throw error;
        }
    });
    const navigate = (source) => run(async() => {
        const previousPath = path;
        path = source;
        page = 0;
        try {
            await list();
        } catch (error) {
            path = previousPath;
            throw error;
        }
    });
    const layout = (view) => {
        if (!['cards', 'list', 'tree'].includes(view)) {
            return;
        }
        root.dataset.layout = view;
        root.querySelectorAll(Selectors.layout).forEach(button => {
            const active = button.dataset.multipickerLayout === view;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', String(active));
        });
        sync();
    };
    const tree = (button, nextPage = 0) => run(async() => {
        const node = button.closest(Selectors.node);
        const children = node.querySelector(Selectors.children);
        if (children.dataset.loaded && !nextPage) {
            children.hidden = !children.hidden;
        } else {
            const source = node.dataset.source;
            const response = await request(scope, {action: 'list', path: source, page: nextPage, draftid: options()?.itemid || 0});
            node.querySelector(Selectors.treePage)?.remove();
            children.insertAdjacentHTML('beforeend', response.html);
            registerChildren(children, source);
            if (response.next) {
                const more = document.createElement('button');
                more.type = 'button';
                more.className = 'btn btn-sm btn-link';
                more.dataset.multipickerTreepage = String(nextPage + 1);
                more.textContent = element(Selectors.next).textContent;
                children.append(more);
            }
            children.dataset.complete = String(!response.next);
            children.dataset.loaded = 'true';
            children.hidden = false;
        }
        node.querySelector(Selectors.open)?.setAttribute('aria-expanded', String(!children.hidden));
    });
    element(Selectors.reference).disabled = Math.floor((options()?.return_types || 0) / 4) % 2 !== 1;
    return {
        load: () => run(list),
        toggle: (check) => {
            const checked = check.checked;
            const branch = new Set([check.value]);
            return changeSelection(() => {
                if (checked) {
                    selected.add(check.value);
                    excluded.forEach(source => {
                        if (belongs(source, branch)) {
                            excluded.delete(source);
                        }
                    });
                } else {
                    selected.forEach(source => {
                        if (belongs(source, branch)) {
                            selected.delete(source);
                        }
                    });
                    if (belongs(check.value, selected)) {
                        excluded.add(check.value);
                    }
                }
            });
        },
        clear: () => changeSelection(() => {
            selected.clear();
            excluded.clear();
        }),
        selectAll: () => run(async() => {
            const old = [...selected];
            const oldExcluded = [...excluded];
            try {
                const response = !query && listingSources ? {sources: listingSources}
                    : await request(scope, {action: 'selectall', path, query, draftid: options()?.itemid || 0});
                (response.sources || []).forEach(source => {
                    selected.add(source);
                    parents.set(source, path);
                });
                if (selected.size > 500) {
                    throw new Error(root.dataset.limit);
                }
                excluded.clear();
                await inspect();
            } catch (error) {
                selected.clear();
                excluded.clear();
                old.forEach(source => selected.add(source));
                oldExcluded.forEach(source => excluded.add(source));
                throw error;
            }
        }),
        open: (button) => root.dataset.layout === 'tree' ? tree(button) : navigate(button.dataset.multipickerOpen),
        treePage: (button) => tree(button, Number(button.dataset.multipickerTreepage)),
        layout,
        search: (value) => {
            query = value;
            page = 0;
            if (busy) {
                pendingSearch = true;
            } else {
                void run(list);
            }
        },
        clearSearch: () => {
            element(Selectors.query).value = '';
            query = '';
            page = 0;
            if (busy) {
                pendingSearch = true;
            } else {
                void run(list);
            }
        },
        copyOptions: () => run(inspect),
        back: () => review ? run(list) : navigate(parent),
        paginate: (offset) => run(async() => {
 page += offset; await list();
}),
        review: () => run(async() => {
            const response = await request(scope, fields('review'));
            results.innerHTML = response.html;
            review = true;
            reviewChecks.clear();
            reviewPaths.clear();
            approvedFile = response.file;
            count = response.count || 0;
            folderCounts = response.counts || {};
        }),
        reviewToggle: (button) => run(async() => {
            const node = button.closest(Selectors.reviewNode);
            const children = node.querySelector(Selectors.reviewChildren);
            if (!children.dataset.loaded) {
                const response = await request(scope, {action: 'reviewchildren', path: node.dataset.path,
                    aliases: element(Selectors.copyMode).value === 'reference' ? 1 : 0});
                children.innerHTML = response.html || '';
                children.dataset.loaded = 'true';
                children.hidden = false;
            } else {
                children.hidden = !children.hidden;
            }
            button.setAttribute('aria-expanded', String(!children.hidden));
        }),
        reviewPage: (button) => run(async() => {
            const response = await request(scope, {action: 'reviewchildren', path: button.dataset.path,
                page: Number(button.dataset.multipickerReviewpage),
                aliases: element(Selectors.copyMode).value === 'reference' ? 1 : 0});
            button.insertAdjacentHTML('beforebegin', response.html || '');
            button.remove();
        }),
        reviewCheck: (check) => {
            if (check.matches(Selectors.reviewGroup)) {
                const path = check.closest(Selectors.reviewNode).dataset.path || '/';
                reviewChecks.forEach((_state, key) => {
                    if ((reviewPaths.get(key) || key.slice(7)).startsWith(path)) {
                        reviewChecks.delete(key);
                    }
                });
            }
            reviewChecks.set(check.value, check.checked);
            sync();
        },
        remove: (sources = []) => run(async() => {
            const removed = sources.length ? sources : [...reviewChecks].filter(([, value]) => value).map(([key]) => key);
            const kept = sources.length ? [] : [...reviewChecks].filter(([, value]) => !value).map(([key]) => key);
            const response = await request(scope, {...fields('review'), removed, kept});
            results.innerHTML = response.html || '';
            excluded.clear();
            (response.excluded || []).forEach(source => excluded.add(source));
            count = response.count || 0;
            folderCounts = response.counts || {};
            reviewChecks.clear();
            reviewPaths.clear();
        }),
        "import": () => run(async() => {
            const response = await request(scope, fields('approve'));
            approvedFile = response.file;
            window.parent.M.core_filepicker.select_file(approvedFile);
            const confirmation = window.parent.document.querySelector('.file-picker.fp-select .fp-select-confirm');
            if (!confirmation) {
                throw new Error(root.dataset.unavailable);
            }
            confirmation.click();
        }),
    };
};
