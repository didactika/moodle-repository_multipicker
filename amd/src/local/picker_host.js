// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

/**
 * Parent-document lifecycle adapter for the native YUI repository host.
 * @module repository_multipicker/local/picker_host
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const releases = new WeakMap();

/**
 * Release presentation when Moodle removes the embedded object or its wrapper.
 * All callbacks belong to the owning page, which survives Chrome object teardown.
 * @param {HTMLObjectElement} object Connected repository document.
 */
export const mount = object => {
    releases.get(object)?.();
    const picker = object.closest('.file-picker');
    const surface = picker?.querySelector('.fp-content');
    if (!object.isConnected || !surface) {
        return;
    }
    picker.classList.add('multipicker-native-host');
    const repositories = picker.querySelector('.fp-repo-area');
    const active = repositories?.querySelector('.fp-repo.active');
    const reveal = () => {
        if (active && window.innerWidth < 576) {
            repositories.scrollLeft = active.offsetLeft - repositories.offsetLeft;
        }
    };
    reveal();
    window.addEventListener('resize', reveal);
    const release = () => {
        observer.disconnect();
        window.removeEventListener('resize', reveal);
        picker.classList.remove('multipicker-native-host');
        releases.delete(object);
    };
    const observer = new MutationObserver(() => {
        if (!object.isConnected) {
            release();
        }
    });
    releases.set(object, release);
    observer.observe(surface, {childList: true, subtree: true});
};
