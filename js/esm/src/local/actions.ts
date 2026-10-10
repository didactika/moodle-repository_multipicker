// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

/**
 * Scoped selection and navigation actions.
 * @module repository_multipicker/local/actions
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import type {NativeWindow, NativeFile} from './contracts';
import Selectors from '@moodle/lms/repository_multipicker/local/selectors';
import {request} from '@moodle/lms/repository_multipicker/local/repository';

/** @param {HTMLElement} root Chooser root. @returns {Object} Scoped actions. */
export const createActions = (root: HTMLElement) => {
    const selected = new Set<string>();
    const scope = root.dataset.scope!;
    const results = root.querySelector<HTMLElement>(Selectors.results)!;
    let path = '';
    let parent = '';
    let page = 0;
    let review = false;
    let approvedFile: NativeFile | undefined;
    let busy = false;
    const picker = () => (window.parent as NativeWindow).M?.core_filepicker?.active_filepicker;
    const sync = () => {
        root.querySelectorAll<HTMLInputElement>(Selectors.check).forEach(check => {
            check.checked = selected.has(check.value);
            check.closest<HTMLElement>(Selectors.card)!.classList.toggle('border-primary', check.checked);
        });
        root.querySelector<HTMLElement>(Selectors.count)!.textContent = String(selected.size);
        root.querySelector<HTMLButtonElement>(Selectors.review)!.disabled = !selected.size || busy;
        root.querySelector<HTMLButtonElement>(Selectors.review)!.hidden = review;
        root.querySelector<HTMLButtonElement>(Selectors.import)!.hidden = !review;
        root.querySelector<HTMLButtonElement>(Selectors.import)!.disabled = busy;
        root.querySelector<HTMLElement>(Selectors.selectionActions)!.hidden = review;
        root.querySelector<HTMLElement>(Selectors.pagination)!.hidden = review;
        root.querySelector<HTMLElement>(Selectors.back)!.hidden = !review && !parent;
    };
    const run = async (operation: () => Promise<void>): Promise<void> => {
        if (busy) {
            return;
        }
        busy = true;
        results.setAttribute('aria-busy', 'true');
        root.querySelector<HTMLElement>(Selectors.error)!.hidden = true;
        sync();
        try {
            await operation();
        } catch (error) {
            root.querySelector<HTMLElement>(Selectors.error)!.textContent = error instanceof Error ? error.message : String(error);
            root.querySelector<HTMLElement>(Selectors.error)!.hidden = false;
        } finally {
            busy = false;
            results.removeAttribute('aria-busy');
            sync();
        }
    };
    const list = async () => {
        const response = await request(scope, {action: 'list', path, page});
        results.innerHTML = response.html ?? "";
        parent = response.parent ?? "";
        root.querySelector<HTMLButtonElement>(Selectors.previous)!.hidden = !response.previous;
        root.querySelector<HTMLButtonElement>(Selectors.next)!.hidden = !response.next;
        root.querySelector<HTMLElement>(Selectors.location)!.textContent = response.location ?? "";
        review = false;
    };
    const fields = (action: string) => {
        const options = picker()?.options;
        if (!options || options.env !== 'filemanager') {
            throw new Error('Open this repository from a native filemanager.');
        }
        return {action, sources: [...selected], maxfiles: options.maxfiles ?? 1, basepath: options.savepath || '/'};
    };
    return {
        load: () => run(list),
        toggle: (check: HTMLInputElement) => {
            if (check.checked) {
                selected.add(check.value);
            } else {
                selected.delete(check.value);
            }
            sync();
        },
        clear: () => {
            selected.clear();
            sync();
        },
        selectAll: () => {
            root.querySelectorAll<HTMLInputElement>(Selectors.check).forEach(check => selected.add(check.value));
            sync();
        },
        open: (source: string) => run(async () => {
            path = source;
            page = 0;
            await list();
        }),
        back: () => run(async () => {
            if (!review) {
                path = parent;
                page = 0;
            }
            await list();
        }),
        paginate: (offset: number) => run(async () => {
            page += offset;
            await list();
        }),
        review: () => run(async () => {
            const response = await request(scope, fields('review'));
            results.innerHTML = response.html ?? '';
            review = true;
            approvedFile = response.file;
        }),
        import: () => run(async () => {
            const response = await request(scope, fields('approve'));
            approvedFile = response.file;
            if (!approvedFile) {
                throw new Error('Missing approval descriptor.');
            }
            (window.parent as NativeWindow).M.core_filepicker.select_file(approvedFile);
            const confirmation = window.parent.document.querySelector<HTMLButtonElement>('.fp-select-confirm');
            if (!confirmation) {
                throw new Error('The native picker confirmation is unavailable.');
            }
            confirmation.click();
        }),
    };
};
