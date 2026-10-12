// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

/**
 * Native filemanager options and embedded chooser presentation.
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import type {NativeWindow} from './contracts';

type HostObject = Element & {multipickerPresentationCleanup?: () => void};

/** @returns {Object} Current owning filemanager options. */
export const options = () => (window.parent as NativeWindow).M?.core_filepicker?.active_filepicker?.options;

/** @param {HTMLElement} root Embedded chooser. */
export const mount = (root: HTMLElement): void => {
    root.ownerDocument.body.classList.add('multipicker-embedded');
    const parent = window.parent as Window & typeof globalThis & NativeWindow;
    const object = window.frameElement as HostObject | null;
    const host = object?.closest<HTMLElement>('.file-picker');
    if (object && host) {
        parent.require(['repository_multipicker/local/picker_host'], (adapter: {mount(object: Element): void}) => adapter.mount(object));
    }
};
