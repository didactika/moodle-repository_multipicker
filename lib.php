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
 * Standalone native picker integration.
 *
 * @package repository_multipicker
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/repository/lib.php');
require_once($CFG->dirroot . '/repository/local/lib.php');

/**
 * Server files permissions with a standalone multiple-selection chooser.
 */
class repository_multipicker extends repository_local {
    /**
     * Keep the native repository entry translated and independent of instance labels.
     * @return string Repository name.
     */
    public function get_name() {
        return get_string('pluginname', 'repository_multipicker');
    }

    /**
     * This repository does not expose a custom instance-name setting.
     * @return array Instance settings.
     */
    public static function get_instance_option_names() {
        return [];
    }

    /**
     * Reuse the exact Server files hierarchy and source permissions.
     * @param string $path Native source path.
     * @param string $query Search terms.
     * @param int $page Search page.
     * @return array Core listing.
     */
    public function get_native_listing(string $path, string $query = '', int $page = 0): array {
        return $query === '' ? parent::get_listing($path) : parent::search($query, $page + 1);
    }
    /**
     * @var string|null Approval currently being downloaded by core.
     */
    private $approval = null;

    /**
     * Batch approvals require native copying rather than a reference to one file.
     *
     * @return int Native copy mode.
     */
    public function supported_returntypes() {
        return FILE_INTERNAL;
    }


    /**
     * Open the embedded chooser for native filemanagers.
     *
     * @param string $path Navigation path.
     * @param mixed $page Native page.
     * @return array Listing.
     */
    public function get_listing($path = '', $page = '') {
        global $SESSION, $USER;
        if (optional_param('env', '', PARAM_ALPHA) !== 'filemanager') {
            return parent::get_listing($path, $page);
        }
        require_capability('repository/multipicker:view', $this->context);
        $token = bin2hex(random_bytes(24));
        $SESSION->repository_multipicker = $SESSION->repository_multipicker ?? [];
        foreach ($SESSION->repository_multipicker as $key => $value) {
            if ($value['expires'] < time()) {
                unset($SESSION->repository_multipicker[$key]);
            }
        }
        $SESSION->repository_multipicker[$token] = [
            'userid' => $USER->id, 'expires' => time() + 3600, 'repositoryid' => $this->id,
            'contextid' => $this->context->id, 'draftid' => optional_param('itemid', 0, PARAM_INT),
            'types' => $this->options['mimetypes'] ?? ['*'],
            'maxbytes' => optional_param('maxbytes', -1, PARAM_INT),
            'areamaxbytes' => optional_param('areamaxbytes', -1, PARAM_INT),
            'name' => get_string('pluginname', 'repository_multipicker'),
        ];
        return ['nologin' => true, 'nosearch' => true, 'norefresh' => true, 'allowcaching' => false,
            'object' => ['type' => 'text/html', 'src' => (new moodle_url(
                '/repository/multipicker/browser.php',
                ['scope' => $token]
            ))->out(false)]];
    }

    /**
     * Resolve a native source or the current session approval.
     *
     * @param string $source Token or native source.
     * @return string Packed native reference.
     */
    public function get_file_reference($source) {
        if (strpos($source, 'batch:') !== 0) {
            return parent::get_file_reference($source);
        }
        [$token, $nonce] = array_pad(explode(':', substr($source, 6), 2), 2, '');
        $scope = \repository_multipicker\local\browser::scope($token);
        if (
            empty($scope['approval']) || $scope['repositoryid'] != $this->id
                || $scope['contextid'] != $this->context->id
                || empty($scope['approvalnonce']) || !hash_equals($scope['approvalnonce'], $nonce)
        ) {
            throw new moodle_exception('unavailable', 'repository_multipicker');
        }
        $this->approval = $token;
        // A batch has no single stored-file identity. Core must not unwrap its first file's alias
        // and switch repositories before reaching our native copy_to_area() implementation.
        return file_storage::pack_reference([
            'contextid' => $scope['contextid'], 'component' => 'repository_multipicker',
            'filearea' => 'batch', 'itemid' => 0, 'filepath' => '/', 'filename' => $nonce,
        ]);
    }

    /**
     * Check current source access.
     *
     * @param string $source Source.
     * @return bool Current authorization.
     */
    public function file_is_accessible($source) {
        if (strpos($source, 'batch:') !== 0) {
            return parent::file_is_accessible($source);
        }
        try {
            $this->get_file_reference($source);
            return true;
        } catch (moodle_exception $exception) {
            return false;
        }
    }

    /**
     * Copy an approved batch into the current user's native draft.
     * @param string $source Packed reference.
     * @param array|stdClass $filerecord Native destination.
     * @param int $maxbytes File limit.
     * @param int $areamaxbytes Draft limit.
     * @return array Native picker callback result.
     */
    public function copy_to_area($source, $filerecord, $maxbytes = -1, $areamaxbytes = FILE_AREA_MAX_BYTES_UNLIMITED) {
        if ($this->approval === null) {
            return parent::copy_to_area($source, $filerecord, $maxbytes, $areamaxbytes);
        }
        $token = $this->approval;
        $this->approval = null;
        return \repository_multipicker\local\browser::apply($token, (array)$filerecord, $maxbytes, $areamaxbytes);
    }
}
