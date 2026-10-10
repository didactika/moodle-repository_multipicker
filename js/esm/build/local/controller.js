import r from"@moodle/lms/repository_multipicker/local/selectors";import{createActions as s}from"@moodle/lms/repository_multipicker/local/actions";import{register as a}from"@moodle/lms/repository_multipicker/local/listeners";/**
 * Chooser initialization.
 * @module repository_multipicker/local/controller
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const m=o=>{const t=document.querySelector(r.root);if(!t||t.dataset.scope!==o||t.dataset.ready)return;t.dataset.ready="true";const e=s(t);a(t,e),e.load()};export{m as init};
