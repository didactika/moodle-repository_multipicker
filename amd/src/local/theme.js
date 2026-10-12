// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

/**
 * Inherit native colour modes and Bootstrap tokens across the repository document boundary.
 * Kept in AMD because Moodle's owning YUI picker loads this lifecycle adapter in its parent document.
 * @module repository_multipicker/local/theme
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const legacyTokens = new Set(['--primary', '--secondary', '--success', '--info', '--warning', '--danger', '--light', '--dark']);

/**
 * Match the actual picker surface, which can differ from the page body token.
 * Read the native painted surface instead of supplying a plugin colour.
 * @param {HTMLElement} surface Native picker content.
 * @returns {String} Active theme's background.
 */
const nativeBackground = surface => {
    for (let element = surface; element; element = element.parentElement) {
        const background = window.getComputedStyle(element).backgroundColor;
        if (background !== 'transparent' && background !== 'rgba(0, 0, 0, 0)') {
            return background;
        }
    }
    return '';
};

/**
 * Follow the owning page without choosing a colour mode or defining a palette.
 * @param {HTMLObjectElement} object Native embedded document.
 * @param {HTMLElement} surface Owning picker content.
 * @returns {Function} Release the parent-document observer on repository teardown.
 */
export const observe = (object, surface) => {
    const target = object.contentDocument?.documentElement;
    if (!target) {
        return () => {};
    }
    const source = surface.ownerDocument.documentElement;
    let copied = new Set();
    const refresh = () => {
        const mode = source.getAttribute('data-bs-theme');
        if (mode === null) {
            target.removeAttribute('data-bs-theme');
        } else {
            target.setAttribute('data-bs-theme', mode);
        }
        const styles = window.getComputedStyle(surface);
        const current = new Set();
        for (const name of styles) {
            if (name.startsWith('--bs-') || legacyTokens.has(name)) {
                target.style.setProperty(name, styles.getPropertyValue(name));
                current.add(name);
            }
        }
        target.style.setProperty('--bs-body-bg', nativeBackground(surface));
        target.style.setProperty('--bs-body-color', styles.color);
        current.add('--bs-body-bg');
        current.add('--bs-body-color');
        copied.forEach(name => {
            if (!current.has(name)) {
                target.style.removeProperty(name);
            }
        });
        copied = current;
    };
    const observer = new MutationObserver(refresh);
    const options = {attributes: true, attributeFilter: ['data-bs-theme', 'class', 'style']};
    for (let element = surface; element; element = element.parentElement) {
        observer.observe(element, options);
    }
    refresh();
    return () => observer.disconnect();
};
