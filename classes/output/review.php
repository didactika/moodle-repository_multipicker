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
 * Resulting draft files organized by destination directory.
 * @package repository_multipicker
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace repository_multipicker\output;

/**
 * Render reviewed operations without exposing storage references as user labels.
 */
final class review implements \renderable, \templatable {
    /** @var array Reviewed file operations. */
    private $operations;
    /** @var bool Native aliases are requested. */
    private $aliases;

    /**
     * Create a destination-file review model.
     * @param array $operations Reviewed file operations.
     * @param bool $aliases Use aliases instead of independent copies.
     */
    public function __construct(array $operations, bool $aliases = false) {
        $this->operations = $operations;
        $this->aliases = $aliases;
    }

    /**
     * Export one destination tree using native Moodle icons.
     * @param \renderer_base $output Native renderer.
     * @return array Template context.
     */
    public function export_for_template(\renderer_base $output): array {
        $tree = [];
        foreach ($this->operations as $operation) {
            $branch = &$tree;
            foreach (array_filter(explode('/', trim($operation['filepath'], '/')), 'strlen') as $folder) {
                $key = 'folder:' . $folder;
                $branch[$key] = $branch[$key] ?? ['name' => $folder, 'folder' => true, 'children' => [],
                    'source' => '', 'standalone' => false];
                $branch = &$branch[$key]['children'];
            }
            $branch['file:' . $operation['filename']] = ['name' => $operation['filename'],
                'source' => $operation['source'], 'aliases' => $this->aliases, 'folder' => false,
                'children' => [], 'standalone' => false];
            unset($branch);
        }
        return ['nodes' => $this->nodes($tree), 'count' => count($this->operations),
            'iconurl' => $output->image_url('f/document')->out(false)];
    }

    /**
     * Convert associative branches into ordered Mustache child lists.
     * @param array $branches Destination branches.
     * @return array Template nodes.
     */
    private function nodes(array $branches): array {
        ksort($branches, SORT_NATURAL | SORT_FLAG_CASE);
        $nodes = [];
        foreach ($branches as $branch) {
            if (!empty($branch['folder'])) {
                $branch['children'] = $this->nodes($branch['children']);
            }
            $nodes[] = $branch;
        }
        return $nodes;
    }
}
