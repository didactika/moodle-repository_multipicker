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
 * Uses core file-browser permissions instead of course-family or catalog rules.
 */
class browser {
    /**
     * Resolve a live approval scope for the current user.
     *
     * @param string $token Session scope.
     * @param int $draftid Native draft supplied when a new filemanager has finished initialization.
     * @return array Current scope.
     */
    public static function scope(string $token, int $draftid = 0): array {
        global $SESSION, $USER;
        $scope = $SESSION->repository_multipicker[$token] ?? null;
        if (!$scope || $scope['userid'] != $USER->id || $scope['expires'] < time()) {
            throw new \moodle_exception('unavailable', 'repository_multipicker');
        }
        require_capability('repository/multipicker:view', \context::instance_by_id($scope['contextid']));
        if (!$scope['draftid'] && $draftid > 0) {
            $SESSION->repository_multipicker[$token]['draftid'] = $draftid;
            $scope['draftid'] = $draftid;
        }
        return $scope;
    }

    /**
     * Resolve a source through Moodle file-browser permissions.
     *
     * @param string $encoded Core JSON node parameters.
     * @return \file_info Authorized node.
     */
    public static function source(string $encoded): \file_info {
        $params = json_decode(base64_decode($encoded, true) ?: '', true, 8);
        if (!is_array($params) || empty($params['contextid'])) {
            throw new \moodle_exception('unavailable', 'repository_multipicker');
        }
        $context = \context::instance_by_id((int)$params['contextid'], MUST_EXIST);
        $node = get_file_browser()->get_file_info(
            $context,
            isset($params['component']) ? clean_param($params['component'], PARAM_COMPONENT) : null,
            isset($params['filearea']) ? clean_param($params['filearea'], PARAM_AREA) : null,
            isset($params['itemid']) ? (int)$params['itemid'] : null,
            isset($params['filepath']) ? clean_param($params['filepath'], PARAM_PATH) : null,
            isset($params['filename']) ? clean_param($params['filename'], PARAM_FILE) : null
        );
        if (!$node || !$node->is_readable()) {
            throw new \moodle_exception('unavailable', 'repository_multipicker');
        }
        return $node;
    }

    /**
     * Browse only one authorized directory; no eager recursive inventory.
     * @param array $scope Bound native context.
     * @param string $path Encoded directory.
     * @param int $page Zero-based page.
     * @param string $query Native Server files search terms.
     * @return array Template data.
     */
    public static function listing(array $scope, string $path, int $page, string $query = ''): array {
        if (!empty($scope['repositoryid'])) {
            $repository = \repository::get_repository_by_id(
                $scope['repositoryid'],
                $scope['contextid'],
                ['mimetypes' => $scope['types']]
            );
            $listing = $repository->get_native_listing($path, $query, $page);
            $nodes = $listing['list'];
            $search = $query !== '';
            $cards = [];
            foreach ($search ? $nodes : array_slice($nodes, max(0, $page) * 30, 30) as $child) {
                $cards[] = ['source' => $child['path'] ?? $child['source'],
                    'name' => $child['title'], 'folder' => isset($child['path']),
                    'size' => isset($child['size']) ? display_size($child['size']) : '',
                    'author' => $child['author'] ?? '', 'license' => $child['license'] ?? ''];
            }
            $ancestors = $listing['path'] ?? [];
            $current = array_pop($ancestors);
            $parent = array_pop($ancestors);
            return ['cards' => $cards, 'path' => $path, 'page' => max(0, $page),
                'previous' => $page > 0, 'next' => $search ? !empty($listing['pages']) : count($nodes) > ($page + 1) * 30,
                'parent' => $parent['path'] ?? '', 'location' => implode(' / ', array_column($listing['path'] ?? [], 'name'))];
        }
        $context = \context::instance_by_id($scope['contextid']);
        $coursecontext = $context->get_course_context(false);
        $node = $path !== '' ? self::source($path)
            : get_file_browser()->get_file_info($coursecontext ?: \context_system::instance());
        if (!$node || !$node->is_directory()) {
            throw new \moodle_exception('unavailable', 'repository_multipicker');
        }
        $children = $node->get_children();
        $cards = [];
        foreach (array_slice($children, max(0, $page) * 30, 30) as $child) {
            if (!$child->is_readable()) {
                continue;
            }
            $cards[] = ['source' => base64_encode(json_encode($child->get_params())),
                'name' => $child->get_visible_name(), 'folder' => $child->is_directory(),
                'size' => $child->is_directory() ? '' : display_size($child->get_filesize())];
        }
        $parent = $node->get_parent();
        return ['cards' => $cards, 'path' => $path, 'page' => max(0, $page),
            'previous' => $page > 0, 'next' => count($children) > ($page + 1) * 30,
            'parent' => $parent ? base64_encode(json_encode($parent->get_params())) : '',
            'location' => $node->get_readable_fullname()];
    }

    /**
     * Expand selected directories once, authorize each file and preflight all limits/conflicts.
     * @param array $scope Native scope.
     * @param array $sources Encoded selected nodes.
     * @param int $maxfiles Native client maximum; core validates again when the owning form is saved.
     * @param string $basepath Native destination directory.
     * @param bool $preservefolders Preserve selected source folder paths.
     * @param array $excluded Source nodes removed from a recursive selection.
     * @param array $counts Recursive selected-file totals keyed by encoded directory.
     * @return array Resulting file operations.
     */
    public static function review(
        array $scope,
        array $sources,
        int $maxfiles,
        string $basepath,
        bool $preservefolders = true,
        array $excluded = [],
        array &$counts = []
    ): array {
        global $USER;
        if (!$sources || count($sources) > 500 || !$scope['draftid']) {
            throw new \moodle_exception('unavailable', 'repository_multipicker');
        }
        $basepath = '/' . trim(clean_param($basepath, PARAM_PATH), '/') . '/';
        $basepath = $basepath === '//' ? '/' : $basepath;
        $counts = [];
        $pending = [];
        $omitted = [];
        if (count($excluded) > 5000) {
            throw new \moodle_exception('limit', 'repository_multipicker');
        }
        foreach ($excluded as $encoded) {
            $omitted[json_encode(self::source($encoded)->get_params())] = true;
        }
        foreach (array_unique($sources) as $encoded) {
            $node = self::source($encoded);
            $pending[] = [$node, $node->is_directory() ? clean_param($node->get_visible_name(), PARAM_FILE) . '/'
                : ltrim($node->get_params()['filepath'] ?? '/', '/'),
                self::ancestors($node)];
        }
        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);
        $existing = $fs->get_area_files($context->id, 'user', 'draft', $scope['draftid'], 'id', false);
        $paths = [];
        $bytes = 0;
        foreach ($existing as $file) {
            $paths[$file->get_filepath() . $file->get_filename()] = $file->get_contenthash();
            $bytes += $file->get_filesize();
        }
        $operations = [];
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
                foreach ($node->get_children() as $child) {
                    if (!$child->is_readable()) {
                        continue;
                    }
                    $pending[] = [$child, $relative . ($child->is_directory()
                        ? clean_param($child->get_visible_name(), PARAM_FILE) . '/' : ''),
                        $child->is_directory() ? array_merge($ancestors, [base64_encode(json_encode($child->get_params()))])
                            : $ancestors];
                }
                continue;
            }
            $params = $node->get_params();
            $file = $fs->get_file(
                $params['contextid'],
                $params['component'],
                $params['filearea'],
                $params['itemid'],
                $params['filepath'],
                $params['filename']
            );
            if (!$file || $file->is_directory() || isset($files[$file->get_pathnamehash()])) {
                continue;
            }
            $files[$file->get_pathnamehash()] = true;
            $types = (array)$scope['types'];
            if (!in_array('*', $types, true) && !file_extension_in_typegroup($file->get_filename(), $types)) {
                throw new \moodle_exception('limit', 'repository_multipicker');
            }
            $filepath = destination_path::make($basepath, $preservefolders ? $relative : '');
            $key = $filepath . $file->get_filename();
            if (isset($paths[$key])) {
                if ($paths[$key] === $file->get_contenthash()) {
                    continue;
                }
                throw new \moodle_exception('conflict', 'repository_multipicker');
            }
            $bytes += $file->get_filesize();
            if (
                ($scope['maxbytes'] > 0 && $file->get_filesize() > $scope['maxbytes'])
                    || ($scope['areamaxbytes'] > 0 && $bytes > $scope['areamaxbytes'])
                    || ($maxfiles >= 0 && count($existing) + count($operations) + 1 > $maxfiles)
                    || count($operations) >= 5000
            ) {
                throw new \moodle_exception('limit', 'repository_multipicker');
            }
            foreach ($ancestors as $ancestor) {
                $counts[$ancestor] = ($counts[$ancestor] ?? 0) + 1;
            }
            $paths[$key] = $file->get_contenthash();
            $operations[] = ['source' => base64_encode(json_encode($params)), 'filepath' => $filepath,
                'filename' => $file->get_filename(), 'hash' => $file->get_contenthash()];
        }
        return $operations;
    }

    /**
     * Count overlapping selections against the actual source hierarchy, independent of selection order.
     * @param \file_info $node Authorized source.
     * @return array Encoded parent directories, including a directory source itself.
     */
    private static function ancestors(\file_info $node): array {
        $directories = [];
        $parent = $node->is_directory() ? $node : $node->get_parent();
        while ($parent && count($directories) < 100) {
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
     * Recheck the entire approval before any writes; no silent overwrites.
     * @param string $token Session approval.
     * @param array $record Native draft destination.
     * @param int $maxbytes Native maximum.
     * @param int $areamaxbytes Native area maximum.
     * @return array Native picker response.
     */
    public static function apply(string $token, array $record, int $maxbytes, int $areamaxbytes): array {
        global $SESSION, $DB, $USER, $OUTPUT;
        $scope = self::scope($token);
        if ((int)$record['itemid'] !== $scope['draftid'] || empty($scope['approval'])) {
            throw new \moodle_exception('unavailable', 'repository_multipicker');
        }
        foreach (['maxbytes' => $maxbytes, 'areamaxbytes' => $areamaxbytes] as $key => $value) {
            if ($value > 0 && ($scope[$key] <= 0 || $value < $scope[$key])) {
                $scope[$key] = $value;
            }
        }
        $operations = self::review(
            $scope,
            $scope['sources'],
            $scope['maxfiles'],
            $scope['basepath'],
            $scope['preservefolders'] ?? true,
            $scope['excluded'] ?? []
        );
        if ($operations !== $scope['approval']) {
            throw new \moodle_exception('unavailable', 'repository_multipicker');
        }
        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);
        $transaction = $DB->start_delegated_transaction();
        $first = null;
        foreach ($operations as $operation) {
            $params = self::source($operation['source'])->get_params();
            $file = $fs->get_file(
                $params['contextid'],
                $params['component'],
                $params['filearea'],
                $params['itemid'],
                $params['filepath'],
                $params['filename']
            );
            $destination = ['contextid' => $context->id, 'component' => 'user',
                'filearea' => 'draft', 'itemid' => $scope['draftid'], 'filepath' => $operation['filepath'],
                'filename' => $operation['filename'], 'userid' => $USER->id,
                'author' => $file->get_author(), 'license' => $file->get_license()];
            if (($scope['copymode'] ?? 'copy') === 'reference') {
                $destination['contenthash'] = $file->get_contenthash();
                $destination['filesize'] = $file->get_filesize();
                $repositoryid = $file->is_external_file() ? $file->get_repository_id() : $scope['repositoryid'];
                $reference = $file->is_external_file() ? $file->get_reference() : \file_storage::pack_reference($params);
                $copied = $fs->create_file_from_reference($destination, $repositoryid, $reference);
            } else {
                $copied = $fs->create_file_from_storedfile($destination, $file);
            }
            $first = $first ?: $copied;
        }
        $transaction->allow_commit();
        // Native filemanagers can reopen the same embedded browser after an import.
        // Consume its approval, while retaining the authorized browsing scope.
        $approvalfields = ['approval', 'approvalnonce', 'sources', 'excluded', 'maxfiles', 'basepath',
            'copymode', 'preservefolders'];
        foreach ($approvalfields as $key) {
            unset($SESSION->repository_multipicker[$token][$key]);
        }
        return ['url' => \moodle_url::make_draftfile_url(
            $scope['draftid'],
            $first->get_filepath(),
            $first->get_filename()
        )->out(false), 'id' => $scope['draftid'], 'file' => $first->get_filename(),
            'icon' => $OUTPUT->image_url('f/document')->out(false)];
    }
}
