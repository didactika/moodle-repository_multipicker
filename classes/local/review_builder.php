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
 * Preflight destination paths, conflicts and native count/type/byte limits before writes.
 */
final class review_builder {
    /** @var array Authorized native scope. */
    private array $scope;
    /** @var string Native destination base. */
    private string $basepath;
    /** @var int Native file limit. */
    private int $maxfiles;
    /** @var int Existing draft file count. */
    private int $existingcount;
    /** @var int Resulting draft bytes. */
    private int $bytes = 0;
    /** @var array Destination hashes. */
    private array $paths = [];

    /**
     * Read the native draft state used for count, byte and conflict checks.
     *
     * @param array $scope Authorized draft scope.
     * @param int $maxfiles Native file limit.
     * @param string $basepath Native destination directory.
     */
    public function __construct(array $scope, int $maxfiles, string $basepath) {
        global $USER;
        $this->scope = $scope;
        $this->maxfiles = $maxfiles;
        $basepath = '/' . trim(clean_param($basepath, PARAM_PATH), '/') . '/';
        $this->basepath = $basepath === '//' ? '/' : $basepath;
        $context = \context_user::instance($USER->id);
        $existing = get_file_storage()->get_area_files($context->id, 'user', 'draft', $scope['draftid'], 'id', false);
        $this->existingcount = count($existing);
        foreach ($existing as $file) {
            $this->paths[$file->get_filepath() . $file->get_filename()] = $file->get_contenthash();
            $this->bytes += $file->get_filesize();
        }
    }

    /**
     * Build.
     *
     * @param array $sources Selected source nodes.
     * @param string $layout Preserve or flatten source paths.
     * @param array $excluded Removed source descendants.
     * @param array $counts Resulting directory counts.
     * @return array Result.
     */
    public function build(array $sources, string $layout, array $excluded, array &$counts): array {
        $counts = [];
        $operations = [];
        foreach (source_tree::files($sources, $excluded) as [$file, $params, $relative, $ancestors]) {
            $operation = $this->operation($file, $params, $layout === 'preserve' ? $relative : '', count($operations));
            if ($operation === null) {
                continue;
            }
            foreach ($ancestors as $ancestor) {
                $counts[$ancestor] = ($counts[$ancestor] ?? 0) + 1;
            }
            $operations[] = $operation;
        }
        return $operations;
    }

    /**
     * Operation.
     *
     * @param \stored_file $file Authorized source file.
     * @param array $params Native source identity.
     * @param string $relative Relative source path.
     * @param int $count Prepared file count.
     * @return array|null Result.
     */
    private function operation(\stored_file $file, array $params, string $relative, int $count): ?array {
        $types = (array)$this->scope['types'];
        if (!in_array('*', $types, true) && !file_extension_in_typegroup($file->get_filename(), $types)) {
            throw new \moodle_exception('limit', 'repository_multipicker');
        }
        $filepath = destination_path::make($this->basepath, $relative);
        $key = $filepath . $file->get_filename();
        if (isset($this->paths[$key])) {
            if ($this->paths[$key] === $file->get_contenthash()) {
                return null;
            }
            throw new \moodle_exception('conflict', 'repository_multipicker');
        }
        $this->check_limits($file->get_filesize(), $count);
        $this->paths[$key] = $file->get_contenthash();
        return ['source' => base64_encode(json_encode($params)), 'filepath' => $filepath,
            'filename' => $file->get_filename(), 'hash' => $file->get_contenthash()];
    }

    /**
     * Check limits.
     *
     * @param int $size Source file bytes.
     * @param int $count Prepared file count.
     */
    private function check_limits(int $size, int $count): void {
        $this->bytes += $size;
        if (
            ($this->scope['maxbytes'] > 0 && $size > $this->scope['maxbytes'])
                || ($this->scope['areamaxbytes'] > 0 && $this->bytes > $this->scope['areamaxbytes'])
                || ($this->maxfiles >= 0 && $this->existingcount + $count + 1 > $this->maxfiles)
                || $count >= 5000
        ) {
            throw new \moodle_exception('limit', 'repository_multipicker');
        }
    }
}
