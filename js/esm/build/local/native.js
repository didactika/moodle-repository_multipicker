/**
 * Native filemanager options and embedded chooser presentation.
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const c=()=>window.parent.M?.core_filepicker?.active_filepicker?.options,a=r=>{document.body.classList.add("multipicker-embedded");const n=window.parent,e=window.frameElement,t=e?.closest(".file-picker");if(e&&t){e.multipickerPresentationCleanup?.(),t.classList.add("multipicker-native-host");const o=n.getComputedStyle(t).backgroundColor;o!=="rgba(0, 0, 0, 0)"&&(r.style.setProperty("--multipicker-background",o),document.body.style.setProperty("--multipicker-background",o));const s=new n.MutationObserver(()=>{e.isConnected||i()}),i=()=>{s.disconnect(),t.classList.remove("multipicker-native-host"),delete e.multipickerPresentationCleanup};e.multipickerPresentationCleanup=i,s.observe(e.parentElement,{childList:!0}),window.addEventListener("pagehide",()=>{e.isConnected||i()},{once:!0})}};export{a as mount,c as options};
