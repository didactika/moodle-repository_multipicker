// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

/**
 * Chooser initialization.
 * @module repository_multipicker/local/controller
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Selectors from '@moodle/lms/repository_multipicker/local/selectors';
import {createActions} from '@moodle/lms/repository_multipicker/local/actions';
import {register} from '@moodle/lms/repository_multipicker/local/listeners';

/** @param {string} scope Session scope. */
export const init = (scope: string) => {
    const root = document.querySelector<HTMLElement>(Selectors.root);
    if (!root || root.dataset.scope !== scope || root.dataset.ready) {
        return;
    }
    root.dataset.ready = 'true';
    const actions = createActions(root);
    register(root, actions);
    actions.load();
};
