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
 * Authorization, recursive copying, limit and conflict regression tests.
 *
 * @package repository_multipicker
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace repository_multipicker;

use repository_multipicker\local\browser;

/**
 * Verify source authorization and native draft preflight.
 *
 * @covers \repository_multipicker\local\browser
 */
final class browser_test extends \advanced_testcase {
    /**
     * Create public folder materials and a native draft.
     *
     * @return array Scope and source directory.
     */
    private function fixture(): array {
        global $USER, $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $course->id]);
        $context = \context_module::instance($folder->cmid);
        $fs = get_file_storage();
        foreach (['one.txt' => 'one', 'two.txt' => 'two'] as $name => $content) {
            $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_folder',
                'filearea' => 'content', 'itemid' => 0, 'filepath' => '/nested/', 'filename' => $name,
                'author' => 'Source author', 'license' => 'cc'], $content);
        }
        $scope = ['userid' => $USER->id, 'expires' => time() + 3600, 'draftid' => file_get_unused_draft_itemid(),
            'types' => ['*'], 'maxbytes' => -1, 'areamaxbytes' => -1, 'contextid' => $context->id];
        $SESSION->repository_multipicker['test'] = $scope;
        $source = base64_encode(json_encode(['contextid' => $context->id, 'component' => 'mod_folder',
            'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => '.']));
        return [$scope, $source];
    }

    /**
     * Recursive selections copy files once and retain original metadata.
     */
    public function test_recursive_import_preserves_metadata_and_rejects_replay(): void {
        global $SESSION, $USER;
        [$scope, $source] = $this->fixture();
        $files = browser::review($scope, [$source, $source], 2, '/');
        $this->assertCount(2, $files);
        $SESSION->repository_multipicker['test'] += ['sources' => [$source], 'approval' => $files,
            'maxfiles' => 2, 'basepath' => '/'];
        browser::apply('test', ['itemid' => $scope['draftid']], -1, -1);
        $draft = get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            $scope['draftid'],
            'filename',
            false
        );
        $this->assertCount(2, $draft);
        foreach ($draft as $file) {
            $this->assertSame('Source author', $file->get_author());
            $this->assertSame('cc', $file->get_license());
            $this->assertStringContainsString('/nested/', $file->get_filepath());
        }
        $this->expectException(\moodle_exception::class);
        browser::scope('test');
    }

    /**
     * A recursive directory cannot bypass the remaining native file count.
     */
    public function test_folder_over_capacity_is_rejected(): void {
        [$scope, $source] = $this->fixture();
        $this->expectException(\moodle_exception::class);
        browser::review($scope, [$source], 1, '/');
    }

    /**
     * A changed source invalidates the approval before any copies are written.
     */
    public function test_changed_source_invalidates_approval(): void {
        global $SESSION, $USER;
        [$scope, $source] = $this->fixture();
        $files = browser::review($scope, [$source], 5, '/');
        $SESSION->repository_multipicker['test'] += ['sources' => [$source], 'approval' => $files,
            'maxfiles' => 5, 'basepath' => '/'];
        $params = browser::source($files[0]['source'])->get_params();
        $fs = get_file_storage();
        $file = $fs->get_file(
            $params['contextid'],
            $params['component'],
            $params['filearea'],
            $params['itemid'],
            $params['filepath'],
            $params['filename']
        );
        $file->delete();
        try {
            browser::apply('test', ['itemid' => $scope['draftid']], -1, -1);
            $this->fail('Changed source was accepted.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('unavailable', $exception->errorcode);
        }
        $this->assertEmpty($fs->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            $scope['draftid'],
            'id',
            false
        ));
    }

    /**
     * Session scopes cannot be used by another user.
     */
    public function test_scope_is_bound_to_user(): void {
        $this->fixture();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\moodle_exception::class);
        browser::scope('test');
    }
}
