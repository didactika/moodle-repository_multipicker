import t from"@moodle/lms/repository_multipicker/local/selectors";/**
 * Delegated DOM listeners.
 * @module repository_multipicker/local/listeners
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const i=(r,l)=>{r.addEventListener("change",c=>{const e=c.target instanceof Element?c.target.closest(t.check):null;e&&l.toggle(e)}),r.addEventListener("click",c=>{const e=c.target;if(!(e instanceof Element))return;const n=e.closest(t.open);if(n)l.open(n.dataset.multipickerOpen??"");else if(e.closest(t.back))l.back();else if(e.closest(t.clear))l.clear();else if(e.closest(t.selectall))l.selectAll();else if(e.closest(t.previous))l.paginate(-1);else if(e.closest(t.next))l.paginate(1);else if(e.closest(t.review))l.review();else if(e.closest(t.import))l.import();else if(!e.closest("input, label, button, a")){const s=e.closest(t.card)?.querySelector(t.check);s&&(s.checked=!s.checked,l.toggle(s))}})};export{i as register};
