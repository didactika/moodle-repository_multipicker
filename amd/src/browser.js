/**
 * Load the typed chooser through Moodle's native ESM bridge.
 * @module repository_multipicker/browser
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {init as controller} from 'core/esm!@moodle/lms/repository_multipicker/Picker';
export const init = controller;
