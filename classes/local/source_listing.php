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
 * Paginate native repository and context listings without expanding source directories.
 */
final class source_listing {
    /**
     * Native.
     *
     * @param array $scope Authorized destination scope.
     * @param string $path Native source path.
     * @param int $page Zero-based page.
     * @param string $query Search terms.
     * @return array Result.
     */
    public static function native(array $scope, string $path, int $page, string $query = ''): array {
        $repository = \repository::get_repository_by_id(
            $scope['repositoryid'],
            $scope['contextid'],
            ['mimetypes' => $scope['types']]
        );
        $listing = $repository->get_native_listing($path, $query, $page);
        $nodes = $listing['list'];
        $search = $query !== '';
        $children = $search ? $nodes : array_slice($nodes, max(0, $page) * 30, 30);
        $cards = array_map([self::class, 'native_card'], $children);
        $ancestors = $listing['path'] ?? [];
        array_pop($ancestors);
        $parent = array_pop($ancestors);
        return ['cards' => $cards, 'path' => $path, 'page' => max(0, $page),
            'selection' => self::selection($nodes, $search),
            'previous' => $page > 0, 'next' => $search ? !empty($listing['pages']) : count($nodes) > ($page + 1) * 30,
            'parent' => $parent['path'] ?? '', 'location' => implode(' / ', array_column($listing['path'] ?? [], 'name'))];
    }

    /**
     * From context.
     *
     * @param array $scope Authorized destination scope.
     * @param string $path Native source path.
     * @param int $page Zero-based page.
     * @return array Result.
     */
    public static function from_context(array $scope, string $path, int $page): array {
        $context = \context::instance_by_id($scope['contextid']);
        $coursecontext = $context->get_course_context(false);
        $node = $path !== '' ? browser::source($path)
            : get_file_browser()->get_file_info($coursecontext ?: \context_system::instance());
        if (!$node || !$node->is_directory()) {
            throw new \moodle_exception('unavailable', 'repository_multipicker');
        }
        $children = $node->get_children();
        $childrenpage = array_slice($children, max(0, $page) * 30, 30);
        $readable = array_filter($childrenpage, static fn($child) => $child->is_readable());
        $cards = array_map([self::class, 'context_card'], array_values($readable));
        $parent = $node->get_parent();
        return ['cards' => $cards, 'path' => $path, 'page' => max(0, $page),
            'previous' => $page > 0, 'next' => count($children) > ($page + 1) * 30,
            'parent' => $parent ? base64_encode(json_encode($parent->get_params())) : '',
            'location' => $node->get_readable_fullname()];
    }

    /**
     * Native card.
     *
     * @param array $child Native listing entry.
     * @return array Result.
     */
    public static function native_card(array $child): array {
        return ['source' => $child['path'] ?? $child['source'],
            'name' => $child['title'], 'folder' => isset($child['path']),
            'size' => isset($child['size']) ? display_size($child['size']) : '',
            'author' => $child['author'] ?? '', 'license' => $child['license'] ?? ''];
    }

    /**
     * Context card.
     *
     * @param \file_info $child Readable native source.
     * @return array Result.
     */
    public static function context_card(\file_info $child): array {
        return ['source' => base64_encode(json_encode($child->get_params())),
            'name' => $child->get_visible_name(), 'folder' => $child->is_directory(),
            'size' => $child->is_directory() ? '' : display_size($child->get_filesize())];
    }

    /**
     * Selection.
     *
     * @param array $nodes Native children.
     * @param bool $search Native search result.
     * @return array|null Result.
     */
    private static function selection(array $nodes, bool $search): ?array {
        if ($search || count($nodes) > 500) {
            return null;
        }
        return array_map(static fn($child) => $child['path'] ?? $child['source'], $nodes);
    }
}
