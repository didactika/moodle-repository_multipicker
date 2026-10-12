var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Authenticated browser transport.
 * @module repository_multipicker/services/api
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Config from "@moodle/lms/core/config";
const request = /* @__PURE__ */ __name(async (scope, fields) => {
  const body = new URLSearchParams({ scope, ajax: "1", sesskey: Config.sesskey });
  Object.entries(fields).forEach(([key, value]) => {
    if (Array.isArray(value)) {
      value.forEach((item) => body.append(key + "[]", item));
    } else {
      body.append(key, String(value));
    }
  });
  const response = await fetch(Config.wwwroot + "/repository/multipicker/browser.php", {
    method: "POST",
    credentials: "same-origin",
    body
  });
  const result = await response.json();
  if (!response.ok || result.error) {
    throw new Error(result.error || response.statusText);
  }
  return result;
}, "request");
export {
  request
};
//# sourceMappingURL=api.dev.js.map
