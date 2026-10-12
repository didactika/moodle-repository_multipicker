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
 * Authorized Server files browsing, recursive selection and atomic draft copies.
 *
 * @package repository_multipicker
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace repository_multipicker\local;

/**
 * Traverse authorized source trees once with bounded depth, exclusions and content deduplication.
 */
final class source_tree {
    /**
     * Files.
     *
     * @param array $sources Selected source nodes.
     * @param array $excluded Removed descendants.
     * @return \Generator Authorized file leaves.
     */
    public static function files(array $sources, array $excluded): \Generator {
        [$pending, $omitted] = self::initial_nodes($sources, $excluded);
        $visited = [];
        $files = [];
        $steps = 0;
        while ($pending) {
            if (++$steps > 10000) {
                throw new \moodle_exception('limit', 'repository_multipicker');
            }
            [$node, $relative, $ancestors] = array_pop($pending);
            $identity = json_encode($node->get_params());
            if (isset($visited[$identity]) || isset($omitted[$identity])) {
                continue;
            }
            $visited[$identity] = true;
            if ($node->is_directory()) {
                self::children($node, $relative, $ancestors, $pending);
                continue;
            }
            $file = self::unique_file($node, $files);
            if ($file === null) {
                continue;
            }
            yield [$file, $node->get_params(), $relative, $ancestors];
        }
    }

    /**
     * Initial nodes.
     *
     * @param array $sources Selected source nodes.
     * @param array $excluded Removed source nodes.
     * @return array Result.
     */
    private static function initial_nodes(array $sources, array $excluded): array {
        $omitted = [];
        foreach ($excluded as $encoded) {
            $omitted[json_encode(browser::source($encoded)->get_params())] = true;
        }
        $pending = [];
        foreach (array_unique($sources) as $encoded) {
            $node = browser::source($encoded);
            $relative = $node->is_directory() ? clean_param($node->get_visible_name(), PARAM_FILE) . '/'
                : ltrim($node->get_params()['filepath'] ?? '/', '/');
            $pending[] = [$node, $relative, self::ancestors($node)];
        }
        return [$pending, $omitted];
    }

    /**
     * Children.
     *
     * @param \file_info $node Readable directory.
     * @param string $relative Relative source path.
     * @param array $ancestors Encoded ancestors.
     * @param array $pending Traversal stack.
     */
    private static function children(\file_info $node, string $relative, array $ancestors, array &$pending): void {
        foreach ($node->get_children() as $child) {
            if (!$child->is_readable()) {
                continue;
            }
            $directory = $child->is_directory();
            $childpath = $relative . ($directory ? clean_param($child->get_visible_name(), PARAM_FILE) . '/' : '');
            $parents = $directory ? array_merge($ancestors, [base64_encode(json_encode($child->get_params()))]) : $ancestors;
            $pending[] = [$child, $childpath, $parents];
        }
    }

    /**
     * Stored file.
     *
     * @param \file_info $node Authorized source node.
     * @return \stored_file|null Authorized file, or null when unavailable.
     */
    public static function stored_file(\file_info $node): ?\stored_file {
        $params = $node->get_params();
        return get_file_storage()->get_file(
            $params['contextid'],
            $params['component'],
            $params['filearea'],
            $params['itemid'],
            $params['filepath'],
            $params['filename']
        );
    }

    /**
     * Count overlapping selections against the actual source hierarchy, independent of selection order.
     * @param \file_info $node Authorized source.
     * @return array Encoded parent directories, including a directory source itself.
     */
    private static function ancestors(\file_info $node): array {
        $directories = [];
        $parent = $node->is_directory() ? $node : $node->get_parent();
        $depth = 0;
        while ($parent && $depth++ < 100) {
            $key = base64_encode(json_encode($parent->get_params()));
            if (isset($directories[$key])) {
                break;
            }
            $directories[$key] = $key;
            $parent = $parent->get_parent();
        }
        return array_values($directories);
    }

    /**
     * Deduplicate a readable leaf by its native pathname hash.
     *
     * @param \file_info $node Authorized source node.
     * @param array $files Already visited file hashes.
     * @return \stored_file|null Authorized file, or null when unavailable.
     */
    private static function unique_file(\file_info $node, array &$files): ?\stored_file {
        $file = self::stored_file($node);
        if (!$file || $file->is_directory() || isset($files[$file->get_pathnamehash()])) {
            return null;
        }
        $files[$file->get_pathnamehash()] = true;
        return $file;
    }
}
