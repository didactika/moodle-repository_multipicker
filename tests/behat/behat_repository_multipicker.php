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
 * Native standalone repository acceptance steps.
 *
 * @package repository_multipicker
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Exercise the real chooser and the owning Moodle folder form.
 */
class behat_repository_multipicker extends behat_base {
    /**
     * Create readable sources, an existing destination and the repository instance.
     *
     * @Given /^standalone MultiPicker test materials exist$/
     */
    public function create_materials(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/repository/lib.php');
        $generator = testing_util::get_data_generator();
        $course = $generator->create_course(['shortname' => 'SP', 'fullname' => 'Standalone picker']);
        $source = $generator->create_module('folder', ['course' => $course->id, 'name' => 'Source files']);
        $target = $generator->create_module('folder', ['course' => $course->id, 'name' => 'Destination files']);
        $user = $generator->create_user(['username' => 'spteacher', 'password' => 'spteacher']);
        $generator->enrol_user($user->id, $course->id, 'editingteacher');
        foreach (['first.txt' => '/', 'second.txt' => '/nested/'] as $name => $path) {
            get_file_storage()->create_file_from_string([
                'contextid' => context_module::instance($source->cmid)->id, 'component' => 'mod_folder',
                'filearea' => 'content', 'itemid' => 0, 'filepath' => $path, 'filename' => $name,
                'author' => 'Source teacher', 'license' => 'cc',
            ], $name);
        }
        get_file_storage()->create_file_from_string([
            'contextid' => context_module::instance($target->cmid)->id, 'component' => 'mod_folder',
            'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => 'existing.txt',
        ], 'Existing file');
        if (!$DB->record_exists('repository', ['type' => 'multipicker'])) {
            (new repository_type('multipicker'))->create(false);
        }
    }

    /**
     * Open the native Add files control and its repository sidebar entry.
     *
     * @When /^I open standalone MultiPicker$/
     */
    public function open_picker(): void {
        $this->getSession()->getDriver()->executeScript(
            'document.querySelector(".filemanager .fp-btn-add a").click();'
        );
        if (
            !$this->getSession()->wait(
                10000,
                '[...document.querySelectorAll(".fp-repo-name")].some(node=>node.textContent.trim()==="MultiPicker")'
            )
        ) {
            throw new Exception('The native repository entry is unavailable.');
        }
        $this->getSession()->getDriver()->executeScript(
            '[...document.querySelectorAll(".fp-repo-name")].find(node=>node.textContent.trim()==="MultiPicker")'
                . '.closest("a").click();'
        );
        if (
            !$this->getSession()->wait(
                10000,
                '!!document.querySelector(".fp-object-container")?.contentDocument?.querySelector("[data-multipicker-card]")'
            )
        ) {
            throw new Exception('The standalone chooser did not load.');
        }
    }

    /**
     * Display a real authorized File API directory, then select its actual cards.
     *
     * @When /^I select the standalone source directory contents$/
     */
    public function select_source(): void {
        global $DB;
        $folder = $DB->get_record('folder', ['name' => 'Source files'], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('folder', $folder->id);
        $path = base64_encode(json_encode([
            'contextid' => context_module::instance($cm->id)->id, 'component' => 'mod_folder',
            'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => '.',
        ]));
        $this->getSession()->getDriver()->executeScript(
            'window.standaloneLoaded=false;const frame=document.querySelector(".fp-object-container"),'
                . 'doc=frame.contentDocument,root=doc.querySelector("[data-multipicker-root]");'
                . 'fetch(frame.src,{method:"POST",body:new URLSearchParams({ajax:1,action:"list",'
                . 'sesskey:root.dataset.sesskey,path:' . json_encode($path) . '})})'
                . '.then(response=>response.json()).then(result=>{'
                . 'if(result.error){throw new Error(result.error);}'
                . 'doc.querySelector("[data-multipicker-results]").innerHTML=result.html;'
                . 'doc.querySelector("[data-multipicker-selectall]").click();window.standaloneLoaded=true;});'
        );
        if (!$this->getSession()->wait(10000, 'window.standaloneLoaded===true')) {
            throw new Exception('The authorized source directory did not load.');
        }
    }

    /**
     * Activate a real chooser action and wait for the resulting native state.
     *
     * @When /^I (review|import) standalone MultiPicker files$/
     * @param string $action Chooser action.
     */
    public function picker_action(string $action): void {
        $this->getSession()->getDriver()->executeScript(
            'document.querySelector(".fp-object-container").contentDocument.querySelector('
                . json_encode('[data-multipicker-' . $action . ']') . ').click();'
        );
        $condition = $action === 'review'
            ? '!!document.querySelector(".fp-object-container")?.contentDocument?.querySelector("[data-multipicker-import]")'
                . '&&!document.querySelector(".fp-object-container").contentDocument'
                . '.querySelector("[data-multipicker-import]").hidden'
            : '!document.querySelector(".moodle-dialogue.filepicker .moodle-dialogue-content")'
                . '||document.querySelector(".filemanager").textContent.includes("first.txt")';
        if (!$this->getSession()->wait(15000, $condition)) {
            throw new Exception('The native chooser action did not complete: ' . $action);
        }
    }
}
