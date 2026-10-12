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
            $fs->create_file_from_string(
                ['contextid' => $context->id, 'component' => 'mod_folder',
                    'filearea' => 'content', 'itemid' => 0, 'filepath' => '/nested/', 'filename' => $name,
                    'author' => 'Source author', 'license' => 'cc'],
                $content
            );
        }
        $scope = ['userid' => $USER->id, 'expires' => time() + 3600, 'draftid' => file_get_unused_draft_itemid(),
            'types' => ['*'], 'maxbytes' => -1, 'areamaxbytes' => -1, 'contextid' => $context->id];
        $SESSION->repository_multipicker['test'] = $scope;
        $directory = get_file_browser()->get_file_info($context, 'mod_folder', 'content', 0, '/', '.');
        $source = base64_encode(json_encode($directory->get_params()));
        return [$scope, $source];
    }

    /**
     * Recursive selections copy files once and retain original metadata.
     */
    public function test_recursive_import_preserves_metadata_and_rejects_replay(): void {
        global $SESSION, $USER;
        [$scope, $source] = $this->fixture();
        $counts = [];
        $files = browser::review($scope, [$source, $source], 2, '/', 'preserve', [], $counts);
        $this->assertCount(2, $files);
        $this->assertSame(2, $counts[$source]);
        $nested = browser::review($scope, [$files[0]['source']], 2, '/');
        $this->assertSame('/nested/', $nested[0]['filepath']);
        $flat = browser::review($scope, [$files[0]['source']], 2, '/', 'flat');
        $this->assertSame('/', $flat[0]['filepath']);
        browser::review($scope, [$source, $files[0]['source']], 2, '/', 'preserve', [], $counts);
        $this->assertSame(2, $counts[$source]);
        browser::review($scope, [$source], 2, '/', 'preserve', [$files[0]['source']], $counts);
        $this->assertSame(1, $counts[$source]);
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
        $this->assertArrayNotHasKey('approval', browser::scope('test'));
        $this->assertNotEmpty(browser::listing(browser::scope('test'), $source, 0)['cards']);
        $this->expectException(\moodle_exception::class);
        browser::apply('test', ['itemid' => $scope['draftid']], -1, -1);
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
        $this->assertEmpty(
            $fs->get_area_files(
                \context_user::instance($USER->id)->id,
                'user',
                'draft',
                $scope['draftid'],
                'id',
                false
            )
        );
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

    /**
     * Course teachers can browse without a site-wide repository role.
     */
    public function test_course_teacher_scope_uses_native_context_permissions(): void {
        global $SESSION;
        [$scope, $source] = $this->fixture();
        $context = \context::instance_by_id($scope['contextid']);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $context->get_course_context()->instanceid, 'editingteacher');
        $this->setUser($teacher);
        $scope['userid'] = $teacher->id;
        $SESSION->repository_multipicker['test'] = $scope;
        $this->assertFalse(has_capability('repository/multipicker:view', \context_system::instance()));
        $this->assertSame($teacher->id, browser::scope('test')['userid']);
        $this->assertNotEmpty(browser::listing($scope, $source, 0)['cards']);
        $this->assertCount(2, browser::review($scope, [$source], 2, '/'));
    }

    /**
     * Native folder structure and recursive exclusions are independent choices.
     */
    public function test_flat_import_and_recursive_exclusions(): void {
        [$scope, $source] = $this->fixture();
        $all = browser::review($scope, [$source], 2, '/', 'flat');
        $this->assertCount(2, $all);
        foreach ($all as $file) {
            $this->assertSame('/', $file['filepath']);
        }
        $remaining = browser::review($scope, [$source], 1, '/', 'flat', [$all[0]['source']]);
        $this->assertCount(1, $remaining);
        $this->assertSame($all[1]['source'], $remaining[0]['source']);
    }

    /**
     * An initialized native draft binds an otherwise valid new browsing scope.
     */
    public function test_new_scope_can_bind_its_native_draft(): void {
        global $SESSION;
        [$scope] = $this->fixture();
        $SESSION->repository_multipicker['test']['draftid'] = 0;
        $this->assertSame(0, browser::scope('test')['draftid']);
        $this->assertSame($scope['draftid'], browser::scope('test', $scope['draftid'])['draftid']);
        $this->assertSame($scope['draftid'], browser::scope('test', $scope['draftid'] + 1)['draftid']);
    }

    /**
     * Alias imports keep the native packed source reference and original metadata.
     */
    public function test_alias_import_uses_the_native_repository_reference(): void {
        global $CFG, $DB, $SESSION, $USER;
        [$scope, $source] = $this->fixture();
        require_once($CFG->dirroot . '/repository/lib.php');
        if (!$DB->record_exists('repository', ['type' => 'multipicker'])) {
            (new \repository_type('multipicker'))->create(false);
        }
        $typeid = $DB->get_field('repository', 'id', ['type' => 'multipicker']);
        $scope['repositoryid'] = (int)$DB->get_field('repository_instances', 'id', ['typeid' => $typeid], MUST_EXIST);
        $scope += ['approval' => browser::review($scope, [$source], 2, '/'), 'sources' => [$source],
            'maxfiles' => 2, 'basepath' => '/', 'copymode' => 'reference', 'approvalnonce' => 'current'];
        $SESSION->repository_multipicker['test'] = $scope;
        $repository = \repository::get_repository_by_id($scope['repositoryid'], \context::instance_by_id($scope['contextid']));
        $this->assertTrue($repository->has_moodle_files());
        $this->assertFalse($repository->file_is_accessible('batch:test:expired'));
        $this->assertTrue($repository->file_is_accessible('batch:test:current'));
        $this->assertTrue($repository->has_moodle_files());
        $this->assertFalse(\repository::get_moodle_file($repository->get_file_reference('batch:test:current')));
        browser::apply('test', ['itemid' => $scope['draftid']], -1, -1);
        $draft = get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            $scope['draftid'],
            'id',
            false
        );
        $this->assertCount(2, $draft);
        foreach ($draft as $file) {
            $this->assertTrue($file->is_external_file());
            $this->assertSame('Source author', $file->get_author());
            $this->assertSame('cc', $file->get_license());
            $this->assertSame($scope['repositoryid'], (int)$file->get_repository_id());
        }
    }
}
