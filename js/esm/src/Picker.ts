// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

/**
 * Chooser initialization.
 * @module repository_multipicker/Picker
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Selectors from './lib/selectors';
import {createActions} from './engine/actions';
import {register} from './components/PickerEvents';
import {mount} from './services/native';

/** @param {string} scope Session scope. */
export const init = (scope: string) => {
    const root = document.querySelector<HTMLElement>(Selectors.root);
    if (!root || root.dataset.scope !== scope || root.dataset.ready) {
        return;
    }
    root.dataset.ready = 'true';
    mount(root);
    const actions = createActions(root);
    register(root, actions);
    actions.load();
};
