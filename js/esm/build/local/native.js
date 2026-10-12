/**
 * Native filemanager options and embedded chooser presentation.
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const c=()=>window.parent.M?.core_filepicker?.active_filepicker?.options,r=t=>{t.ownerDocument.body.classList.add("multipicker-embedded");const o=window.parent,e=window.frameElement,i=e?.closest(".file-picker");e&&i&&o.require(["repository_multipicker/local/picker_host"],n=>n.mount(e))};export{r as mount,c as options};
