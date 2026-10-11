/**
 * Native filemanager options and embedded chooser presentation.
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const c=()=>window.parent.M?.core_filepicker?.active_filepicker?.options,s=n=>{document.body.classList.add("multipicker-embedded");const o=window.parent,e=window.frameElement,i=e?.closest(".file-picker");if(e&&i){o.require(["repository_multipicker/local/picker_host"],r=>r.mount(e));const t=o.getComputedStyle(i).backgroundColor;t!=="rgba(0, 0, 0, 0)"&&(n.style.setProperty("--multipicker-background",t),document.body.style.setProperty("--multipicker-background",t))}};export{s as mount,c as options};
