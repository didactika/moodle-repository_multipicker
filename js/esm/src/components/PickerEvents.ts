// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

/**
 * Delegated chooser listeners, independent of layout and result refreshes.
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import type {createActions} from '../engine/actions';
import Selectors from '../lib/selectors';

/**
 * Register listeners for one embedded chooser.
 * @param {HTMLElement} root Chooser root.
 * @param {Object} actions Scoped actions.
 */
export const register = (root: HTMLElement, actions: ReturnType<typeof createActions>): void => {
    let timer: ReturnType<typeof setTimeout>;
    root.addEventListener('input', event => {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.matches(Selectors.query)) {
            clearTimeout(timer);
            const value = target.value;
            timer = setTimeout(() => actions.search(value), 250);
        }
    });
    root.addEventListener('change', event => {
        const target = event.target;
        if (!(target instanceof Element)) { return; }
        const check = target.closest<HTMLInputElement>(Selectors.check);
        if (check) { actions.toggle(check); }
        const reviewed = target.closest<HTMLInputElement>(Selectors.reviewCheck);
        if (reviewed) { actions.reviewCheck(reviewed); }
        if (target.matches(Selectors.copyMode + ',' + Selectors.preserve)) { actions.copyOptions(); }
    });
    root.addEventListener('click', event => {
        const target = event.target;
        if (!(target instanceof Element)) { return; }
        const open = target.closest<HTMLButtonElement>(Selectors.open);
        const layout = target.closest<HTMLButtonElement>(Selectors.layout);
        const more = target.closest<HTMLButtonElement>(Selectors.treePage);
        const reviewToggle = target.closest<HTMLButtonElement>(Selectors.reviewToggle);
        const reviewDelete = target.closest<HTMLElement>(Selectors.reviewDelete);
        const reviewPage = target.closest<HTMLElement>(Selectors.reviewPage);
        if (reviewDelete) {
            actions.remove([reviewDelete.dataset.multipickerDelete!]);
        } else if (reviewPage) {
            actions.reviewPage(reviewPage);
        } else if (reviewToggle) { actions.reviewToggle(reviewToggle); }
        else if (open) { actions.open(open); }
        else if (more) { actions.treePage(more); }
        else if (layout) { actions.layout(layout.dataset.multipickerLayout!); }
        else if (target.closest(Selectors.back)) { actions.back(); }
        else if (target.closest(Selectors.clear)) { actions.clear(); }
        else if (target.closest(Selectors.clearSearch)) { clearTimeout(timer); actions.clearSearch(); }
        else if (target.closest(Selectors.selectall)) { actions.selectAll(); }
        else if (target.closest(Selectors.previous)) { actions.paginate(-1); }
        else if (target.closest(Selectors.next)) { actions.paginate(1); }
        else if (target.closest(Selectors.review)) { actions.review(); }
        else if (target.closest(Selectors.import)) { actions.import(); }
        else if (target.closest(Selectors.remove)) { actions.remove(); }
        else if (!target.closest('input, label, button, a')) {
            const check = target.closest(Selectors.card)?.querySelector<HTMLInputElement>(Selectors.check);
            if (check && !check.disabled) { check.checked = !check.checked; actions.toggle(check); }
            const reviewed = target.closest(Selectors.reviewRow)?.querySelector<HTMLInputElement>(Selectors.reviewCheck);
            if (reviewed) { reviewed.checked = !reviewed.checked; actions.reviewCheck(reviewed); }
        }
    });
};
