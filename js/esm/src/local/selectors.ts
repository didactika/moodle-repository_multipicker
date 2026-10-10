// This file is part of Moodle - https://moodle.org/
// Moodle is free software under the GNU GPL v3 or later.

/**
 * Shared DOM contracts.
 * @module repository_multipicker/local/selectors
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default {
    root: '[data-multipicker-root]', results: '[data-multipicker-results]', error: '[data-multipicker-error]',
    card: '[data-multipicker-card]', check: '[data-multipicker-check]', open: '[data-multipicker-open]',
    back: '[data-multipicker-back]', selectall: '[data-multipicker-selectall]', clear: '[data-multipicker-clear]',
    review: '[data-multipicker-review]', import: '[data-multipicker-import]', count: '[data-multipicker-count]',
    location: '[data-multipicker-location]', previous: '[data-multipicker-previous]', next: '[data-multipicker-next]',
    selectionActions: '[data-multipicker-selection-actions]', pagination: '[data-multipicker-pagination]',
};
