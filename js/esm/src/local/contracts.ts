/**
 * Typed native picker and browser response contracts.
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export interface NativeFile {title: string; source: string; repository_id: number; thumbnail: string}
export type NativeWindow = Window & {M: {core_filepicker: {
    active_filepicker: {options: {env: string; maxfiles?: number; savepath?: string}};
    select_file(file: NativeFile): void;
}}};
export interface Response {html?: string; error?: string; parent?: string; previous?: boolean; next?: boolean;
    location?: string; file?: NativeFile}
