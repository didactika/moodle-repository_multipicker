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
 * Large selection, destination path and lazy review regression tests.
 *
 * @package repository_multipicker
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace repository_multipicker;

use repository_multipicker\local\browser;
use repository_multipicker\local\preview;
use repository_multipicker\local\destination_path;
use repository_multipicker\output\review;

/**
 * Exercise actual storage limits and bounded display work.
 * @covers \repository_multipicker\local\browser
 * @covers \repository_multipicker\local\preview
 * @covers \repository_multipicker\local\destination_path
 * @covers \repository_multipicker\output\review
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\repository_multipicker\local\browser::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\repository_multipicker\local\preview::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\repository_multipicker\local\destination_path::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\repository_multipicker\output\review::class)]
final class performance_test extends \advanced_testcase {
    /**
     * Create a long category/course/module hierarchy with Unicode source directories.
     * @return array Scope and course source.
     */
    private function fixture(): array {
        global $USER, $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['fullname' => str_repeat('Course ', 35)]);
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $course->id,
            'name' => str_repeat('Materials ', 24)]);
        $context = \context_module::instance($folder->cmid);
        foreach (['one.txt', 'two.txt'] as $name) {
            get_file_storage()->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_folder',
                'filearea' => 'content', 'itemid' => 0, 'filepath' => '/' . str_repeat('中', 80) . '/nested/',
                'filename' => $name, 'author' => 'Source author'], $name);
        }
        $scope = ['userid' => $USER->id, 'expires' => time() + 3600, 'draftid' => file_get_unused_draft_itemid(),
            'types' => ['*'], 'maxbytes' => -1, 'areamaxbytes' => -1, 'contextid' => $context->id];
        $SESSION->repository_multipicker['performance'] = $scope;
        $node = get_file_browser()->get_file_info(\context_course::instance($course->id));
        return [$scope, base64_encode(json_encode($node->get_params()))];
    }

    /**
     * Long source labels cannot reach the database with an oversized destination path.
     */
    public function test_long_unicode_hierarchy_is_copied_without_overflow(): void {
        global $SESSION, $USER;
        [$scope, $source] = $this->fixture();
        $operations = browser::review($scope, [$source], 10, '/');
        $this->assertCount(2, $operations);
        foreach ($operations as $operation) {
            $this->assertLessThanOrEqual(255, \core_text::strlen($operation['filepath']));
            $this->assertStringContainsString('-', $operation['filepath']);
        }
        $SESSION->repository_multipicker['performance'] += ['sources' => [$source], 'approval' => $operations,
            'maxfiles' => 10, 'basepath' => '/'];
        browser::apply('performance', ['itemid' => $scope['draftid']], -1, -1);
        $files = get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            $scope['draftid'],
            'id',
            false
        );
        $this->assertCount(2, $files);
        foreach ($files as $file) {
            $this->assertSame($file->get_filename(), $file->get_content());
            $this->assertSame('Source author', $file->get_author());
        }
        $this->assertNotSame(
            destination_path::make('/', str_repeat('Long name ', 20)),
            destination_path::make('/', str_repeat('Long name ', 20) . 'different')
        );
        $this->assertSame('/plain/nested/', destination_path::make('/plain/', 'nested/'));
        $this->assertLessThanOrEqual(255, \core_text::strlen(destination_path::make('/', str_repeat('deep/', 100))));
    }

    /**
     * A large mixed-source batch remains valid when the native Folder form saves its draft.
     */
    public function test_many_imported_files_save_through_the_native_folder_editor(): void {
        global $CFG, $SESSION;
        [$scope, $source] = $this->fixture();
        for ($index = 0; $index < 250; $index++) {
            get_file_storage()->create_file_from_string(['contextid' => $scope['contextid'], 'component' => 'mod_folder',
                'filearea' => 'content', 'itemid' => 0, 'filepath' => '/bulk/', 'filename' => 'bulk' . $index . '.txt',
                'source' => 'Site file ' . $index], 'bulk');
        }
        $operations = browser::review($scope, [$source], 1000, '/');
        $this->assertCount(252, $operations);
        $SESSION->repository_multipicker['performance'] += ['sources' => [$source], 'approval' => $operations,
            'maxfiles' => 1000, 'basepath' => '/'];
        browser::apply('performance', ['itemid' => $scope['draftid']], -1, -1);

        require_once($CFG->libdir . '/filelib.php');
        file_save_draft_area_files(
            $scope['draftid'],
            $scope['contextid'],
            'mod_folder',
            'content',
            17,
            ['subdirs' => true, 'maxfiles' => -1, 'maxbytes' => -1]
        );
        $saved = get_file_storage()->get_area_files($scope['contextid'], 'mod_folder', 'content', 17, 'filename', false);
        $this->assertCount(252, $saved);
        $sources = [];
        foreach ($saved as $file) {
            $sources[$file->get_filename()] = $file->get_source();
        }
        $this->assertSame('Site file 128', $sources['bulk128.txt']);
    }

    /**
     * The review reuses recent inspection work; approvals never reuse that authorization snapshot.
     */
    public function test_display_snapshot_avoids_repeated_walk_and_approval_rechecks(): void {
        global $DB;
        [$scope, $source] = $this->fixture();
        for ($index = 0; $index < 250; $index++) {
            get_file_storage()->create_file_from_string(['contextid' => $scope['contextid'], 'component' => 'mod_folder',
                'filearea' => 'content', 'itemid' => 0, 'filepath' => '/bulk/', 'filename' => 'bulk' . $index . '.txt'], 'bulk');
        }
        $options = ['sources' => [$source], 'maxfiles' => 1000, 'basepath' => '/',
            'preservefolders' => true, 'excluded' => []];
        $before = $DB->perf_get_queries();
        $first = preview::prepare('performance', $scope, $options);
        $this->assertCount(252, $first['operations']);
        $walkqueries = $DB->perf_get_queries() - $before;
        $before = $DB->perf_get_queries();
        $second = preview::prepare('performance', $scope, $options);
        $this->assertSame($first['operations'], $second['operations']);
        $this->assertLessThan($walkqueries, $DB->perf_get_queries() - $before);
        $params = browser::source($first['operations'][0]['source'])->get_params();
        $file = get_file_storage()->get_file(
            $params['contextid'],
            $params['component'],
            $params['filearea'],
            $params['itemid'],
            $params['filepath'],
            $params['filename']
        );
        $file->delete();
        $fresh = preview::prepare('performance', $scope, $options, true);
        $this->assertCount(count($first['operations']) - 1, $fresh['operations']);
    }

    /**
     * Thousands of selected files render one collapsed level, with removals covering unloaded children.
     */
    public function test_review_levels_are_bounded_and_folder_removal_respects_exceptions(): void {
        $operations = [];
        for ($index = 0; $index < 1000; $index++) {
            $operations[] = ['source' => 'source' . $index, 'filename' => $index . '.txt', 'filepath' => '/course/folder/'];
        }
        $root = review::level($operations, false);
        $this->assertCount(1, $root['nodes']);
        $this->assertArrayNotHasKey('children', $root['nodes'][0]);
        $this->assertSame(1000, $root['nodes'][0]['count']);
        $page = review::level($operations, false, '/course/folder/');
        $this->assertCount(30, $page['nodes']);
        $this->assertTrue($page['next']);
        $last = review::level($operations, false, '/course/folder/', 33);
        $this->assertCount(10, $last['nodes']);
        $this->assertFalse($last['next']);
        $removed = preview::exclusions($operations, ['folder:/course/'], ['source0']);
        $this->assertCount(999, $removed);
        $this->assertNotContains('source0', $removed);
        $this->assertSame(['source0'], preview::exclusions($operations, ['source0'], []));
    }
}
