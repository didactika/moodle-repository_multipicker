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
        $fields = ['component' => PARAM_COMPONENT, 'filearea' => PARAM_AREA,
            'itemid' => PARAM_INT, 'filepath' => PARAM_PATH, 'filename' => PARAM_FILE];
        $values = [];
        foreach ($fields as $field => $type) {
            $values[] = isset($params[$field]) ? clean_param($params[$field], $type) : null;
        }
        $node = get_file_browser()->get_file_info($context, ...$values);
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
            return source_listing::native($scope, $path, $page, $query);
        }
        return source_listing::from_context($scope, $path, $page);
    }

    /**
     * Expand selected directories once, authorize each file and preflight all limits/conflicts.
     * @param array $scope Native scope.
     * @param array $sources Encoded selected nodes.
     * @param int $maxfiles Native client maximum; core validates again when the owning form is saved.
     * @param string $basepath Native destination directory.
     * @param string $layout Preserve or flatten selected source folder paths.
     * @param array $excluded Source nodes removed from a recursive selection.
     * @param array $counts Recursive selected-file totals keyed by encoded directory.
     * @return array Resulting file operations.
     */
    public static function review(
        array $scope,
        array $sources,
        int $maxfiles,
        string $basepath,
        string $layout = 'preserve',
        array $excluded = [],
        array &$counts = []
    ): array {
        if (!$sources || count($sources) > 500 || !$scope['draftid']) {
            throw new \moodle_exception('unavailable', 'repository_multipicker');
        }
        if (count($excluded) > 5000) {
            throw new \moodle_exception('limit', 'repository_multipicker');
        }
        return (new review_builder($scope, $maxfiles, $basepath))->build($sources, $layout, $excluded, $counts);
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
        $scope = self::tighten_limits($scope, $maxbytes, $areamaxbytes);
        $operations = self::review(
            $scope,
            $scope['sources'],
            $scope['maxfiles'],
            $scope['basepath'],
            ($scope['preservefolders'] ?? true) ? 'preserve' : 'flat',
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
            $copied = self::copy_operation($scope, $operation, $context->id, $fs);
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

    /**
     * Tighten limits.
     *
     * @param array $scope Approved native scope.
     * @param int $maxbytes Latest native file maximum.
     * @param int $areamaxbytes Latest native area maximum.
     * @return array Result.
     */
    private static function tighten_limits(array $scope, int $maxbytes, int $areamaxbytes): array {
        foreach (['maxbytes' => $maxbytes, 'areamaxbytes' => $areamaxbytes] as $key => $value) {
            if ($value > 0 && ($scope[$key] <= 0 || $value < $scope[$key])) {
                $scope[$key] = $value;
            }
        }
        return $scope;
    }

    /**
     * Copy operation.
     *
     * @param array $scope Approved native scope.
     * @param array $operation Revalidated operation.
     * @param int $contextid Native user context.
     * @param \file_storage $fs Native storage.
     * @return stored_file Result.
     */
    private static function copy_operation(array $scope, array $operation, int $contextid, \file_storage $fs): \stored_file {
        global $USER;
        $node = self::source($operation['source']);
        $params = $node->get_params();
        $file = source_tree::stored_file($node);
        $destination = ['contextid' => $contextid, 'component' => 'user',
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
        return $copied;
    }
}
