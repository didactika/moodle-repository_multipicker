<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English source strings.
 *
 * @package repository_multipicker
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
$string['back'] = 'Back';
$string['cards'] = 'Cards';
$string['clear'] = 'Clear selection';
$string['clearsearch'] = 'Clear search';
$string['configplugin'] = 'Server files MultiPicker configuration';
$string['conflict'] = 'A different file already uses this destination path. Remove it from the selection or choose another destination.';
$string['copy'] = 'Copy files';
$string['copymode'] = 'Import method';
$string['copymode_help'] = 'Copies are independent files. Aliases retain a reference to the original file and can follow its changes. Aliases are available only when the owning control supports them.';
$string['empty'] = 'No accessible files or folders here.';
$string['file'] = 'File';
$string['folder'] = 'Folder';
$string['folderselection'] = '{$a} selected';
$string['import'] = 'Import files';
$string['limit'] = 'The selection exceeds the file count, accepted types or storage limits of this control.';
$string['list'] = 'List';
$string['multipicker:view'] = 'Select several Server files at once';
$string['nothing'] = 'All selected files are already in this draft.';
$string['open'] = 'Open folder';
$string['pathlimit'] = 'There is not enough space in this destination path. Choose a higher folder or turn off Preserve folders.';
$string['pluginname'] = 'Server files MultiPicker';
$string['preservefolders'] = 'Keep folders';
$string['preservefolders_help'] = 'Long directory labels use a short stable suffix to keep destination paths within Moodle storage limits. File names are preserved. Keep the selected folders and their subfolders under the current destination. Turn this off to place all resulting files directly in the destination; conflicting names must be resolved before import.';
$string['privacy:metadata'] = 'MultiPicker stores no persistent personal data. Selections and approvals expire in the user session; copied files use Moodle draft storage.';
$string['reference'] = 'Create aliases';
$string['remove'] = 'Remove selected files';
$string['review'] = 'Review selection';
$string['search'] = 'Search Server files';
$string['search_help'] = 'Search the files available through Moodle’s Server files browser. Changing or clearing the search keeps your current selection.';
$string['selectall'] = 'Select all';
$string['selected'] = 'selected files';
$string['tree'] = 'Tree';
$string['unavailable'] = 'This selection is unavailable or has expired. Open the repository again.';
