/**
 * Typed native picker and browser response contracts.
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export interface NativeFile {title: string; source: string; sourcekey: string; repository_id: number; thumbnail: string}
export type NativeWindow = Window & {require(modules: string[], loaded: (adapter: {mount(object: Element): void}) => void): void; M: {core_filepicker: {
    active_filepicker: {options: {env: string; itemid?: number; maxfiles?: number; savepath?: string; return_types?: number}};
    select_file(file: NativeFile): void;
}}};
export interface Response {html?: string; error?: string; parent?: string; previous?: boolean; next?: boolean;
    location?: string; file?: NativeFile; count?: number; counts?: Record<string, number>; sources?: string[]; excluded?: string[]}
