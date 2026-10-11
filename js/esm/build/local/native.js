/**
 * Native filemanager options and embedded chooser presentation.
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const i=()=>window.parent.M?.core_filepicker?.active_filepicker?.options,n=o=>{document.body.classList.add("multipicker-embedded");const e=window.frameElement?.closest(".file-picker");if(e){e.classList.add("multipicker-native-host");const t=window.parent.getComputedStyle(e).backgroundColor;t!=="rgba(0, 0, 0, 0)"&&(o.style.setProperty("--multipicker-background",t),document.body.style.setProperty("--multipicker-background",t)),window.addEventListener("pagehide",()=>e.classList.remove("multipicker-native-host"),{once:!0})}};export{n as mount,i as options};
