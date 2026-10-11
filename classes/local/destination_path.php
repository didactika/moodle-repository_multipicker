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
 * Native destination path bounds and stable folder labels.
 *
 * @package repository_multipicker
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace repository_multipicker\local;

/**
 * Bound destination paths before native storage writes, retaining deterministic directory identities.
 */
final class destination_path {
    /**
     * Preserve the native base and compact long source directory labels with stable suffixes.
     * @param string $base Native destination directory.
     * @param string $relative Source-relative directory.
     * @return string Valid destination path, never exceeding the native 255-character field.
     */
    public static function make(string $base, string $relative): string {
        $base = '/' . trim(clean_param($base, PARAM_PATH), '/') . '/';
        $base = $base === '//' ? '/' : $base;
        if (\core_text::strlen($base) > 255) {
            throw new \moodle_exception('pathlimit', 'repository_multipicker');
        }
        $parts = array_values(array_filter(explode('/', trim($relative, '/')), 'strlen'));
        $path = $base;
        $original = '';
        foreach ($parts as $index => $part) {
            $original .= '/' . $part;
            $name = clean_param($part, PARAM_FILE);
            if (\core_text::strlen($name) > 64) {
                $name = \core_text::substr($name, 0, 51) . '-' . substr(hash('sha256', $original), 0, 12);
            }
            $remaining = implode('/', array_slice($parts, $index));
            $reserve = $index < count($parts) - 1 ? 34 : 0;
            if (\core_text::strlen($path . $name . '/') + $reserve > 255) {
                $available = 255 - \core_text::strlen($path) - 1;
                if ($available < 16) {
                    throw new \moodle_exception('pathlimit', 'repository_multipicker');
                }
                $path .= \core_text::substr('Folders-' . hash('sha256', $original . '/' . $remaining), 0, $available) . '/';
                return $path;
            }
            $path .= $name . '/';
        }
        return $path;
    }
}
