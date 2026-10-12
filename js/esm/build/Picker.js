import e from"./lib/selectors";import{createActions as s}from"./engine/actions";import{register as a}from"./components/PickerEvents";import{mount as i}from"./services/native";/**
 * Chooser initialization.
 * @module repository_multipicker/Picker
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const p=r=>{const t=document.querySelector(e.root);if(!t||t.dataset.scope!==r||t.dataset.ready)return;t.dataset.ready="true",i(t);const o=s(t);a(t,o),o.load()};export{p as init};
