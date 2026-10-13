// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

/**
 * Render browser and review state in the native file picker.
 * @module repository_multipicker/components/PickerView
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Selectors from '../lib/selectors';

interface PickerViewState {
    selected: Set<string>;
    excluded: Set<string>;
    parents: Map<string, string>;
    folderCounts: Record<string, number>;
    reviewChecks: Map<string, boolean>;
    reviewPaths: Map<string, string>;
    count: number;
    review: boolean;
    busy: boolean;
    parent: string;
    query: string;
}

/**
 * Update only the mounted picker controls; requests and selection rules stay in the engine.
 * @param root The embedded picker root.
 * @param state Current selection and review state.
 */
export const renderPicker = (root: HTMLElement, state: PickerViewState): void => {
    const {selected, excluded, parents, folderCounts, reviewChecks, reviewPaths, count, review, busy, parent, query} = state;
    const element = <T extends HTMLElement = HTMLElement>(selector: string) => root.querySelector<T>(selector)!;
    const isSelected = (source: string) => {
        for (let depth = 0; source && depth < 100; depth++) {
            if (excluded.has(source)) { return false; }
            if (selected.has(source)) { return true; }
            source = parents.get(source) || '';
        }
        return false;
    };
    const reviewedState = (check: HTMLInputElement) => {
        if (reviewChecks.has(check.value)) { return reviewChecks.get(check.value); }
        const path = check.closest<HTMLElement>(Selectors.reviewNode)!.dataset.path || '/';
        let checked = false, length = -1;
        reviewChecks.forEach((value, key) => {
            if (key.startsWith('folder:') && path.startsWith(key.slice(7)) && key.length > length) {
                checked = value;
                length = key.length;
            }
        });
        return checked;
    };
    root.querySelectorAll<HTMLInputElement>(Selectors.check).forEach(check => {
        check.checked = isSelected(check.value);
        check.indeterminate = false;
        check.disabled = busy;
        check.closest<HTMLElement>(Selectors.card)!.classList.toggle('border-primary', check.checked);
    });
    // Derive visible directory state without fetching unopened descendants.
    [...root.querySelectorAll<HTMLElement>(Selectors.node)].reverse().forEach(node => {
        const children = node.querySelector<HTMLElement>(Selectors.children);
        const check = node.querySelector<HTMLInputElement>(Selectors.check);
        if (!children || !check) { return; }
        const files = [...children.querySelectorAll<HTMLInputElement>(Selectors.check)];
        const any = files.some(file => file.checked || file.indeterminate);
        const all = files.length > 0 && files.every(file => file.checked && !file.indeterminate);
        check.indeterminate = children.dataset.complete === 'true'
            ? any && !all : !check.checked && (folderCounts[check.value] || 0) > 0;
        if (children.dataset.complete === 'true') { check.checked = all; }
        check.closest<HTMLElement>(Selectors.card)!.classList.toggle('border-primary', check.checked || check.indeterminate);
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
    element<HTMLButtonElement>(Selectors.remove).hidden = !review || ![...reviewChecks.values()].some(Boolean);
    element<HTMLButtonElement>(Selectors.remove).disabled = busy;
    root.querySelectorAll<HTMLInputElement>(Selectors.reviewCheck).forEach(check => {
        const path = check.closest<HTMLElement>(Selectors.reviewNode)!.dataset.path || '/';
        reviewPaths.set(check.value, path);
        check.checked = !!reviewedState(check);
        check.indeterminate = false;
        if (check.matches(Selectors.reviewGroup)) {
            reviewChecks.forEach((value, key) => {
                if (key !== check.value && (reviewPaths.get(key) || key.slice(7)).startsWith(path)
                        && value !== check.checked) { check.indeterminate = true; }
            });
        }
    });
    root.querySelectorAll<HTMLElement>('[data-multipicker-folder-count]').forEach(badge => {
        const total = folderCounts[badge.dataset.multipickerFolderCount!] || 0;
        badge.textContent = root.dataset.folderselection!.replace('{$a}', String(total));
    });
};
