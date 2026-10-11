// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

/**
 * Shared selection state, lazy directory trees and native draft imports.
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import type {NativeWindow, NativeFile} from './contracts';
import Selectors from '@moodle/lms/repository_multipicker/local/selectors';
import {request} from '@moodle/lms/repository_multipicker/local/repository';
import {options} from '@moodle/lms/repository_multipicker/local/native';

/**
 * Create actions for one embedded chooser.
 * @param {HTMLElement} root Chooser root.
 * @returns {Object} Scoped actions.
 */
export const createActions = (root: HTMLElement) => {
    const selected = new Set<string>();
    const excluded = new Set<string>();
    const parents = new Map<string, string>();
    const scope = root.dataset.scope!;
    const element = <T extends HTMLElement = HTMLElement>(selector: string) => root.querySelector<T>(selector)!;
    const results = element(Selectors.results);
    let path = '', parent = '', query = '';
    let page = 0, count = 0;
    let review = false, busy = false, pendingSearch = false;
    let approvedFile: NativeFile | undefined;
    const belongs = (source: string, nodes: Set<string>): boolean => {
        for (let depth = 0; source && depth < 100; depth++) {
            if (nodes.has(source)) { return true; }
            source = parents.get(source) || '';
        }
        return false;
    };
    const sync = () => {
        root.querySelectorAll<HTMLInputElement>(Selectors.check).forEach(check => {
            check.checked = belongs(check.value, selected) && !belongs(check.value, excluded);
            check.disabled = busy;
            check.closest<HTMLElement>(Selectors.card)!.classList.toggle('border-primary', check.checked);
        });
        element(Selectors.count).textContent = String(count);
        element<HTMLButtonElement>(Selectors.review).disabled = !count || busy;
        element<HTMLButtonElement>(Selectors.review).hidden = review;
        element<HTMLButtonElement>(Selectors.import).hidden = !review;
        element<HTMLButtonElement>(Selectors.import).disabled = busy || !count;
        element(Selectors.selectionActions).hidden = review;
        element(Selectors.selectionActions).classList.toggle('d-flex', !review);
        element(Selectors.filters).hidden = review;
        element(Selectors.pagination).hidden = review;
        element(Selectors.pagination).classList.toggle('d-flex', !review);
        element<HTMLButtonElement>(Selectors.back).hidden = !review && !parent;
        element<HTMLButtonElement>(Selectors.clearSearch).disabled = !query;
        element<HTMLButtonElement>(Selectors.remove).hidden = !review ||
            !root.querySelector(Selectors.reviewCheck + ':checked');
        element<HTMLButtonElement>(Selectors.remove).disabled = busy;
        root.querySelectorAll<HTMLElement>('.multipicker-meta').forEach(metadata => {
            ['border-top', 'pt-2', 'mt-2'].forEach(style => metadata.classList.toggle(style, root.dataset.layout === 'cards'));
        });
    };
    const fields = (action: string) => {
        const native = options();
        if (!native || native.env !== 'filemanager') { throw new Error(root.dataset.unavailable); }
        return {action, sources: [...selected], excluded: [...excluded], draftid: native.itemid || 0,
            maxfiles: native.maxfiles ?? 1, basepath: native.savepath || '/',
            copymode: element<HTMLSelectElement>(Selectors.copyMode).value,
            preservefolders: element<HTMLInputElement>(Selectors.preserve).checked ? 1 : 0};
    };
    const registerChildren = (container: HTMLElement, source: string) => {
        container.querySelectorAll<HTMLInputElement>(':scope > .multipicker-items > .multipicker-node > .card ' +
            Selectors.check).forEach(check => parents.set(check.value, source));
    };
    const list = async () => {
        const requestedQuery = query;
        const response = await request(scope, {action: 'list', path, page, query, draftid: options()?.itemid || 0});
        if (requestedQuery !== query) { pendingSearch = true; return; }
        results.innerHTML = response.html!;
        registerChildren(results, path);
        parent = response.parent || '';
        element<HTMLButtonElement>(Selectors.previous).hidden = !response.previous;
        element<HTMLButtonElement>(Selectors.next).hidden = !response.next;
        element(Selectors.location).textContent = response.location || '';
        review = false;
    };
    const run = async (operation: () => Promise<void>): Promise<void> => {
        if (busy) { return; }
        busy = true;
        results.setAttribute('aria-busy', 'true');
        element(Selectors.error).hidden = true;
        sync();
        try { await operation(); } catch (error) {
            element(Selectors.error).textContent = error instanceof Error ? error.message : String(error);
            element(Selectors.error).hidden = false;
        } finally {
            busy = false;
            results.removeAttribute('aria-busy');
            sync();
            if (pendingSearch) { pendingSearch = false; void run(list); }
        }
    };
    const inspect = async () => {
        count = selected.size ? (await request(scope, fields('inspect'))).count || 0 : 0;
    };
    const changeSelection = (mutate: () => void) => run(async () => {
        const oldSelection = [...selected], oldExclusions = [...excluded];
        mutate();
        sync();
        try { await inspect(); } catch (error) {
            selected.clear(); excluded.clear();
            oldSelection.forEach(source => selected.add(source));
            oldExclusions.forEach(source => excluded.add(source));
            throw error;
        }
    });
    const navigate = (source: string) => run(async () => {
        const previousPath = path;
        path = source; page = 0;
        try { await list(); } catch (error) { path = previousPath; throw error; }
    });
    const layout = (view: string) => {
        if (!['cards', 'list', 'tree'].includes(view)) { return; }
        root.dataset.layout = view;
        root.querySelectorAll<HTMLButtonElement>(Selectors.layout).forEach(button => {
            const active = button.dataset.multipickerLayout === view;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', String(active));
        });
        sync();
    };
    const tree = (button: HTMLButtonElement, nextPage = 0) => run(async () => {
        const node = button.closest<HTMLElement>(Selectors.node)!;
        const children = node.querySelector<HTMLElement>(Selectors.children)!;
        if (children.dataset.loaded && !nextPage) { children.hidden = !children.hidden; } else {
            const source = node.dataset.source!;
            const response = await request(scope, {action: 'list', path: source, page: nextPage, draftid: options()?.itemid || 0});
            node.querySelector(Selectors.treePage)?.remove();
            children.insertAdjacentHTML('beforeend', response.html!);
            registerChildren(children, source);
            if (response.next) {
                const more = document.createElement('button');
                more.type = 'button'; more.className = 'btn btn-sm btn-link';
                more.dataset.multipickerTreepage = String(nextPage + 1);
                more.textContent = element(Selectors.next).textContent;
                children.append(more);
            }
            children.dataset.loaded = 'true'; children.hidden = false;
        }
        node.querySelector(Selectors.open)?.setAttribute('aria-expanded', String(!children.hidden));
    });
    element<HTMLOptionElement>(Selectors.reference).disabled = Math.floor((options()?.return_types || 0) / 4) % 2 !== 1;
    return {
        load: () => run(list),
        toggle: (check: HTMLInputElement) => changeSelection(() => {
            if (check.checked) { selected.add(check.value); excluded.delete(check.value); } else {
                selected.delete(check.value);
                if (belongs(check.value, selected)) { excluded.add(check.value); }
            }
        }),
        clear: () => changeSelection(() => { selected.clear(); excluded.clear(); }),
        selectAll: () => run(async () => {
            const old = [...selected], oldExcluded = [...excluded];
            try {
                let currentPage = 0, more = true;
                while (more) {
                    const response = await request(scope, {action: 'list', path, query, page: currentPage++,
                        draftid: options()?.itemid || 0});
                    const fragment = document.createElement('div');
                    fragment.innerHTML = response.html!;
                    registerChildren(fragment, path);
                    fragment.querySelectorAll<HTMLInputElement>(Selectors.check).forEach(check => selected.add(check.value));
                    if (selected.size > 500 || currentPage > 100) { throw new Error(root.dataset.limit); }
                    more = !!response.next;
                }
                excluded.clear(); await inspect();
            } catch (error) {
                selected.clear(); excluded.clear();
                old.forEach(source => selected.add(source)); oldExcluded.forEach(source => excluded.add(source));
                throw error;
            }
        }),
        open: (button: HTMLButtonElement) => root.dataset.layout === 'tree' ? tree(button) : navigate(button.dataset.multipickerOpen!),
        treePage: (button: HTMLButtonElement) => tree(button, Number(button.dataset.multipickerTreepage)),
        layout,
        search: (value: string) => {
            query = value; page = 0;
            if (busy) { pendingSearch = true; } else { void run(list); }
        },
        clearSearch: () => {
            element<HTMLInputElement>(Selectors.query).value = ''; query = ''; page = 0;
            if (busy) { pendingSearch = true; } else { void run(list); }
        },
        copyOptions: () => run(inspect),
        back: () => review ? run(list) : navigate(parent),
        paginate: (offset: number) => run(async () => { page += offset; await list(); }),
        review: () => run(async () => {
            const response = await request(scope, fields('review'));
            results.innerHTML = response.html!; review = true; approvedFile = response.file; count = response.count || 0;
        }),
        reviewToggle: (button: HTMLButtonElement) => {
            const children = button.closest(Selectors.reviewNode)!.querySelector<HTMLElement>(Selectors.reviewChildren)!;
            children.hidden = !children.hidden;
            button.setAttribute('aria-expanded', String(!children.hidden));
        },
        reviewCheck: (check: HTMLInputElement) => {
            if (check.matches(Selectors.reviewGroup)) {
                check.closest(Selectors.reviewNode)!.querySelectorAll<HTMLInputElement>(Selectors.reviewCheck)
                    .forEach(child => { child.checked = check.checked; });
            }
            root.querySelectorAll<HTMLInputElement>(Selectors.reviewGroup).forEach(group => {
                const files = [...group.closest(Selectors.reviewNode)!.querySelectorAll<HTMLInputElement>(Selectors.reviewCheck)]
                    .filter(file => file.value);
                const checked = files.filter(file => file.checked).length;
                group.checked = files.length > 0 && checked === files.length;
                group.indeterminate = checked > 0 && checked < files.length;
            });
            sync();
        },
        remove: () => run(async () => {
            const previous = [...excluded];
            root.querySelectorAll<HTMLInputElement>(Selectors.reviewCheck + ':checked').forEach(check => {
                if (check.value) { excluded.add(check.value); }
            });
            try {
                const response = await request(scope, fields('review'));
                results.innerHTML = response.html!; count = response.count || 0;
            } catch (error) {
                excluded.clear(); previous.forEach(source => excluded.add(source)); throw error;
            }
        }),
        import: () => run(async () => {
            const response = await request(scope, fields('approve'));
            approvedFile = response.file;
            (window.parent as NativeWindow).M.core_filepicker.select_file(approvedFile!);
            const confirmation = window.parent.document.querySelector<HTMLButtonElement>('.file-picker.fp-select .fp-select-confirm');
            if (!confirmation) { throw new Error(root.dataset.unavailable); }
            confirmation.click();
        }),
    };
};
