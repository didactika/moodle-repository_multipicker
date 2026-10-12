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
    public function __construct(array $operations, bool $aliases) {
        $this->operations = $operations;
        $this->aliases = $aliases;
    }

    /**
     * Export one destination tree using native Moodle icons.
     *
     * {@inheritdoc}
     * @param \renderer_base $output Native renderer.
     * @return array Template context.
     */
    public function export_for_template(\renderer_base $output): array {
        return self::level($this->operations, $this->aliases);
    }

    /**
     * Export only direct children of a reviewed destination, with bounded pages.
     * @param array $operations Already prepared operations.
     * @param bool $aliases Native reference mode.
     * @param string $path Directory prefix, not a source token.
     * @param int $page Zero-based child page.
     * @return array One review level.
     */
    public static function level(array $operations, bool $aliases, string $path = '/', int $page = 0): array {
        $nodes = [];
        foreach ($operations as $operation) {
            if (strpos($operation['filepath'], $path) !== 0) {
                continue;
            }
            $remaining = substr($operation['filepath'], strlen($path));
            if ($remaining !== '') {
                $name = explode('/', $remaining)[0];
                $key = 'folder:' . $path . $name . '/';
                $nodes[$key] = $nodes[$key] ?? ['name' => $name, 'folder' => true, 'source' => $key,
                    'path' => $path . $name . '/', 'count' => 0];
                $nodes[$key]['count']++;
            } else {
                $nodes[$operation['source']] = ['name' => $operation['filename'], 'folder' => false,
                    'source' => $operation['source'], 'path' => $path, 'aliases' => $aliases];
            }
        }
        uasort($nodes, static fn($a, $b) => ($b['folder'] <=> $a['folder']) ?: strnatcasecmp($a['name'], $b['name']));
        $page = max(0, $page);
        return ['nodes' => array_slice(array_values($nodes), $page * 30, 30), 'count' => count($operations),
            'next' => count($nodes) > ($page + 1) * 30, 'nextpage' => $page + 1, 'path' => $path];
    }
}
