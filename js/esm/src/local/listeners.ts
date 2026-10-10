// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

/**
 * Delegated DOM listeners.
 * @module repository_multipicker/local/listeners
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import type {createActions} from './actions';
import Selectors from '@moodle/lms/repository_multipicker/local/selectors';

/**
 * Register delegated chooser interactions.
 * @param {HTMLElement} root Chooser root.
 * @param {Object} actions Scoped actions.
 */
export const register = (root: HTMLElement, actions: ReturnType<typeof createActions>) => {
    root.addEventListener('change', event => {
        const check = event.target instanceof Element ? event.target.closest<HTMLInputElement>(Selectors.check) : null;
        if (check) {
            actions.toggle(check);
        }
    });
    root.addEventListener('click', event => {
        const target = event.target;
        if (!(target instanceof Element)) { return; }
        const open = target.closest<HTMLElement>(Selectors.open);
        if (open) {
            actions.open(open.dataset.multipickerOpen ?? "");
        } else if (target.closest(Selectors.back)) {
            actions.back();
        } else if (target.closest(Selectors.clear)) {
            actions.clear();
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
        } else if (!target.closest('input, label, button, a')) {
            const check = target.closest(Selectors.card)?.querySelector<HTMLInputElement>(Selectors.check);
            if (check) {
                check.checked = !check.checked;
                actions.toggle(check);
            }
        }
    });
};
