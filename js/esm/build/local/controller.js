import e from"@moodle/lms/repository_multipicker/local/selectors";import{createActions as s}from"@moodle/lms/repository_multipicker/local/actions";import{register as a}from"@moodle/lms/repository_multipicker/local/listeners";import{mount as i}from"@moodle/lms/repository_multipicker/local/native";/**
 * Chooser initialization.
 * @module repository_multipicker/local/controller
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const p=r=>{const t=document.querySelector(e.root);if(!t||t.dataset.scope!==r||t.dataset.ready)return;t.dataset.ready="true",i(t);const o=s(t);a(t,o),o.load()};export{p as init};
