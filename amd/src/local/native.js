// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

/**
 * Native filemanager options and embedded chooser presentation.
 * @module repository_multipicker/local/native
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/** @returns {Object} Current owning filemanager options. */
export const options = () => window.parent.M?.core_filepicker?.active_filepicker?.options;
/** @param {HTMLElement} root Embedded chooser. */
export const mount = (root) => {
    document.body.classList.add('multipicker-embedded');
    const host = window.frameElement?.closest('.file-picker');
    if (host) {
        host.classList.add('multipicker-native-host');
        const background = window.parent.getComputedStyle(host).backgroundColor;
        if (background !== 'rgba(0, 0, 0, 0)') {
            root.style.setProperty('--multipicker-background', background);
            document.body.style.setProperty('--multipicker-background', background);
        }
        window.addEventListener('pagehide', () => host.classList.remove('multipicker-native-host'), {once: true});
    }
};
