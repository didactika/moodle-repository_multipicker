import n from"@moodle/lms/core/config";/**
 * Authenticated browser transport.
 * @module repository_multipicker/services/api
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const m=async(i,a)=>{const r=new URLSearchParams({scope:i,ajax:"1",sesskey:n.sesskey});Object.entries(a).forEach(([t,o])=>{Array.isArray(o)?o.forEach(p=>r.append(t+"[]",p)):r.append(t,String(o))});const e=await fetch(n.wwwroot+"/repository/multipicker/browser.php",{method:"POST",credentials:"same-origin",body:r}),s=await e.json();if(!e.ok||s.error)throw new Error(s.error||e.statusText);return s};export{m as request};
