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
 * Bounded display snapshots and reviewed branch exclusions.
 *
 * @package repository_multipicker
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace repository_multipicker\local;

/**
 * Short-lived display snapshots. Approval and copying always reauthorize the full source tree.
 */
final class preview {
    /**
     * Reuse a prepared selection for display only while its options and native draft remain identical.
     * @param string $token Bound scope token.
     * @param array $scope Current authorized scope.
     * @param array $options Selection request.
     * @param bool $fresh Force complete authorization for an approval.
     * @return array Prepared operations and directory counts.
     */
    public static function prepare(string $token, array $scope, array $options, bool $fresh = false): array {
        global $SESSION, $DB, $USER;
        $draft = $DB->get_records(
            'files',
            ['contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user', 'filearea' => 'draft', 'itemid' => $scope['draftid']],
            'id',
            'id, filepath, filename, contenthash, filesize, timemodified, author, license'
        );
        $signature = hash('sha256', json_encode([$options, $draft]));
        $snapshot = $SESSION->repository_multipicker[$token]['preview'] ?? null;
        if (!$fresh && $snapshot && $snapshot['signature'] === $signature && $snapshot['expires'] >= time()) {
            foreach ($options['sources'] as $source) {
                browser::source($source);
            }
            return $snapshot;
        }
        $counts = [];
        $operations = browser::review(
            $scope,
            $options['sources'],
            $options['maxfiles'],
            $options['basepath'],
            $options['preservefolders'],
            $options['excluded'],
            $counts
        );
        $snapshot = ['operations' => $operations, 'counts' => $counts, 'signature' => $signature, 'expires' => time() + 15];
        $SESSION->repository_multipicker[$token]['preview'] = $snapshot;
        return $snapshot;
    }

    /**
     * Resolve checked destination branches to actual file sources, including unloaded descendants.
     * @param array $operations Current resulting files.
     * @param array $removed Explicit checked rows or directory prefixes.
     * @param array $kept Explicit unchecked rows or directory prefixes.
     * @return array Source exclusions for the recursive selection.
     */
    public static function exclusions(array $operations, array $removed, array $kept): array {
        $result = [];
        foreach ($operations as $operation) {
            $exclude = false;
            $specificity = -1;
            foreach ([false => $kept, true => $removed] as $selected => $rules) {
                foreach ($rules as $rule) {
                    $folder = strpos($rule, 'folder:') === 0 ? substr($rule, 7) : null;
                    $matches = $folder !== null ? strpos($operation['filepath'], $folder) === 0
                        : $operation['source'] === $rule;
                    $length = $folder !== null ? strlen($folder) : PHP_INT_MAX;
                    if ($matches && $length > $specificity) {
                        $exclude = (bool)$selected;
                        $specificity = $length;
                    }
                }
            }
            if ($exclude) {
                $result[] = $operation['source'];
            }
        }
        return $result;
    }
}
