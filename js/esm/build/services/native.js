/**
 * Native filemanager options and embedded chooser presentation.
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const s=()=>window.parent.M?.core_filepicker?.active_filepicker?.options,c=t=>{t.ownerDocument.body.classList.add("multipicker-embedded");const o=window.parent,e=window.frameElement,i=e?.closest(".file-picker");e&&i&&o.require(["repository_multipicker/local/picker_host"],n=>n.mount(e))};export{c as mount,s as options};
