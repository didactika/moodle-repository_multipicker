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
    document.body.classList.add('multipicker-embedded');
    const parent = window.parent as Window & typeof globalThis;
    const object = window.frameElement as HostObject | null;
    const host = object?.closest<HTMLElement>('.file-picker');
    if (object && host) {
        // Only the connected embedded chooser owns this presentation marker.
        object.multipickerPresentationCleanup?.();
        host.classList.add('multipicker-native-host');
        const background = parent.getComputedStyle(host).backgroundColor;
        if (background !== 'rgba(0, 0, 0, 0)') {
            root.style.setProperty('--multipicker-background', background);
            document.body.style.setProperty('--multipicker-background', background);
        }
        // Keep cleanup in the parent realm, which survives replacement of the embedded document.
        const observer = new parent.MutationObserver(() => {
            if (!object.isConnected) {
                release();
            }
        });
        const release = () => {
            observer.disconnect();
            host.classList.remove('multipicker-native-host');
            delete object.multipickerPresentationCleanup;
        };
        object.multipickerPresentationCleanup = release;
        observer.observe(object.parentElement!, {childList: true});
        window.addEventListener('pagehide', () => {
            if (!object.isConnected) {
                release();
            }
        }, {once: true});
    }
};
