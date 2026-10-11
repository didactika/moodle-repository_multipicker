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
     * Native repository changes restore the header and size before reopening the embedded chooser.
     * @Then /^standalone presentation should follow the active native repository$/
     */
    public function native_repository_presentation(): void {
        $driver = $this->getSession()->getDriver();
        $driver->executeScript(
            'window.nativePresentationHost=document.querySelector(".fp-object-container").closest(".file-picker");'
        );
        $expanded = $driver->evaluateScript(
            'window.nativePresentationHost.querySelector(".fp-content").getBoundingClientRect().height'
        );
        for ($cycle = 0; $cycle < 2; $cycle++) {
            $driver->executeScript(
                '[...document.querySelectorAll(".fp-repo-name")].find(node=>node.textContent.trim()==="Server files")'
                    . '.closest("a").click();'
            );
            if (
                !$this->getSession()->wait(
                    10000,
                    'window.nativePresentationHost.querySelector(".fp-repo.active .fp-repo-name")'
                        . '?.textContent.trim()==="Server files"'
                        . '&&getComputedStyle(window.nativePresentationHost.querySelector(".fp-navbar")).display!=="none"'
                        . '&&!window.nativePresentationHost.querySelector(".fp-object-container")'
                        . '&&!document.querySelector(".filebank-native-host,.multipicker-native-host")'
                )
            ) {
                $state = $driver->evaluateScript(
                    'JSON.stringify({host:window.nativePresentationHost.className,'
                        . 'navbar:getComputedStyle(window.nativePresentationHost.querySelector(".fp-navbar")).display,'
                        . 'objects:window.nativePresentationHost.querySelectorAll(".fp-object-container").length})'
                );
                throw new Exception('Switching repository did not restore the native header: ' . $state);
            }
            $height = $driver->evaluateScript(
                'window.nativePresentationHost.querySelector(".fp-content").getBoundingClientRect().height'
            );
            if (abs($height - $expanded) < 1 || ($cycle && abs($height - $normal) > 1)) {
                throw new Exception('Switching repository did not restore a stable native content height: '
                    . json_encode(['expanded' => $expanded, 'native' => $height, 'previous' => $normal ?? null]));
            }
            $normal = $height;
            $driver->executeScript(
                '[...document.querySelectorAll(".fp-repo-name")].find(node=>node.textContent.trim()==='
                    . json_encode('Server files MultiPicker') . ').closest("a").click();'
            );
            if (
                !$this->getSession()->wait(
                    10000,
                    '!!document.querySelector(".fp-object-container")?.contentDocument?.querySelector("[data-multipicker-card]")'
                        . '&&getComputedStyle(window.nativePresentationHost.querySelector(".fp-navbar")).display==="none"'
                )
            ) {
                throw new Exception('Reopening the repository did not restore the embedded chooser presentation.');
            }
            $height = $driver->evaluateScript(
                'window.nativePresentationHost.querySelector(".fp-content").getBoundingClientRect().height'
            );
            if (abs($height - $expanded) > 1) {
                throw new Exception('Reopening the repository changed its expanded height.');
            }
        }
    }

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
                '[...document.querySelectorAll(".fp-repo-name")].some(node=>node.textContent.trim()==="Server files MultiPicker")'
            )
        ) {
            throw new Exception('The native repository entry is unavailable.');
        }
        $this->getSession()->getDriver()->executeScript(
            '[...document.querySelectorAll(".fp-repo-name")].find(node=>node.textContent.trim()==="Server files MultiPicker")'
                . '.closest("a").click();'
        );
        if (
            !$this->getSession()->wait(
                10000,
                '!!document.querySelector(".fp-object-container")?.contentDocument?.querySelector("[data-multipicker-card]")'
            )
        ) {
            $this->getSession()->getDriver()->executeScript(
                'window.standaloneFailure="";const frame=document.querySelector(".fp-object-container"),'
                    . 'doc=frame.contentDocument,root=doc.querySelector("[data-multipicker-root]");'
                    . 'fetch(doc.defaultView.location.href,{method:"POST",body:new URLSearchParams({ajax:1,action:"list",'
                    . 'sesskey:root.dataset.sesskey,scope:root.dataset.scope})})'
                    . '.then(response=>response.text()).then(text=>window.standaloneFailure=text);'
            );
            $this->getSession()->wait(3000, 'window.standaloneFailure!==""');
            $detail = $this->getSession()->getDriver()->evaluateScript(
                'window.standaloneFailure'
            );
            throw new Exception('The standalone chooser did not load: ' . $detail);
        }
    }

    /**
     * Display a real authorized File API directory, then select its actual cards.
     *
     * @When /^I select the standalone source directory contents$/
     */
    public function select_source(): void {
        $this->getSession()->getDriver()->executeScript(
            'const doc=document.querySelector(".fp-object-container").contentDocument;'
                . '[...doc.querySelectorAll("[data-multipicker-card]")].find(node=>'
                . 'node.querySelector(".multipicker-name").textContent.trim().startsWith("Source files"))'
                . '.querySelector("[data-multipicker-open]").click();'
        );
        if (
            !$this->getSession()->wait(
                10000,
                '[...document.querySelector(".fp-object-container").contentDocument.querySelectorAll(".multipicker-name")]'
                . '.some(node=>node.textContent.trim()==="first.txt")'
            )
        ) {
            throw new Exception('The native Server files source directory did not load.');
        }
        foreach ([1, 0, 1] as $index => $expected) {
            $selector = $index === 2 ? '.multipicker-name' : '[data-multipicker-check]';
            $this->getSession()->getDriver()->executeScript(
                'const doc=document.querySelector(".fp-object-container").contentDocument;'
                    . '[...doc.querySelectorAll("[data-multipicker-card]")].find(node=>'
                    . 'node.querySelector(".multipicker-name").textContent.trim()==="first.txt")'
                    . '.querySelector(' . json_encode($selector) . ').click();'
            );
            $this->wait_selection_count($expected);
        }
        $this->getSession()->getDriver()->executeScript(
            'document.querySelector(".fp-object-container").contentDocument'
                . '.querySelector("[data-multipicker-clear]").click();'
        );
        $this->wait_selection_count(0);
        $this->getSession()->getDriver()->executeScript(
            'document.querySelector(".fp-object-container").contentDocument'
                . '.querySelector("[data-multipicker-selectall]").click();'
        );
        if (
            !$this->getSession()->wait(
                10000,
                'document.querySelector(".fp-object-container").contentDocument'
                . '.querySelector("[data-multipicker-count]").textContent==="2"'
            )
        ) {
            throw new Exception('The recursive native selection did not complete.');
        }
    }

    /**
     * Wait for server validation of a direct checkbox, card or directory change.
     * @param int $count Expected number of resulting files.
     */
    private function wait_selection_count(int $count): void {
        if (
            !$this->getSession()->wait(
                10000,
                '(()=>{const doc=document.querySelector(".fp-object-container").contentDocument;'
                    . 'return !doc.querySelector("[aria-busy=true]")'
                    . '&&doc.querySelector("[data-multipicker-count]").textContent==='
                    . json_encode((string)$count) . ';})()'
            )
        ) {
            throw new Exception('Direct file and directory checks do not share the resulting selection.');
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
            $visible = $this->getSession()->getDriver()->evaluateScript(
                'JSON.stringify({chooser:document.querySelector(".fp-object-container")?.contentDocument?.body.innerText,'
                . 'native:document.querySelector(".file-picker")?.innerText})'
            );
            throw new Exception('The native chooser action did not complete: ' . $action . ' / ' . $visible);
        }
        if ($action === 'review') {
            $visible = $this->getSession()->getDriver()->evaluateScript(
                'document.querySelector(".fp-object-container").contentDocument'
                    . '.querySelector("[data-multipicker-selection-actions]").getClientRects().length'
            );
            if ($visible) {
                throw new Exception('Browsing layout controls must be hidden during review.');
            }
        }
    }

    /**
     * Layout controls share real selected nodes, including lazily opened children.
     * @Then /^standalone selection remains consistent across all views$/
     */
    public function selection_views(): void {
        foreach (['list', 'tree', 'cards'] as $view) {
            $this->getSession()->getDriver()->executeScript(
                'const doc=document.querySelector(".fp-object-container").contentDocument;'
                    . 'doc.querySelector(' . json_encode('[data-multipicker-layout="' . $view . '"]') . ').click();'
            );
            if ($view === 'tree') {
                $this->getSession()->getDriver()->executeScript(
                    'const doc=document.querySelector(".fp-object-container").contentDocument;'
                        . '[...doc.querySelectorAll("[data-multipicker-card]")].find(node=>'
                        . 'node.querySelector(".multipicker-name").textContent.trim()==="nested")'
                        . '.querySelector("[data-multipicker-open]").click();'
                );
                if (
                    !$this->getSession()->wait(
                        10000,
                        '[...document.querySelector(".fp-object-container").contentDocument.querySelectorAll(".multipicker-name")]'
                        . '.some(node=>node.textContent.trim()==="second.txt")'
                    )
                ) {
                    throw new Exception('Lazy tree did not reach the actual file.');
                }
                $changes = ['clear' => 0, 'second.txt' => 1, 'nested' => 0, 'second.txt-again' => 1, 'first.txt' => 2];
                foreach ($changes as $name => $expected) {
                    $this->getSession()->getDriver()->executeScript(
                        'const doc=document.querySelector(".fp-object-container").contentDocument;'
                        . ($name === 'clear' ? 'doc.querySelector("[data-multipicker-clear]").click();'
                            : '[...doc.querySelectorAll("[data-multipicker-card]")].find(node=>'
                                . 'node.querySelector(".multipicker-name").textContent.trim()==='
                                . json_encode(str_replace('-again', '', $name))
                                . ').querySelector("[data-multipicker-check]").click();')
                    );
                    $this->wait_selection_count($expected);
                }
            }
            $valid = $this->getSession()->getDriver()->evaluateScript(
                '(()=>{const doc=document.querySelector(".fp-object-container").contentDocument;'
                    . 'return doc.querySelector("[data-multipicker-count]").textContent==="2"'
                    . '&&[...doc.querySelectorAll("[data-multipicker-check]")].every(check=>check.checked)'
                    . '&&doc.querySelectorAll("[data-multipicker-layout].active").length===1'
                    . '&&[...doc.querySelectorAll("[data-multipicker-open]")].every(button=>'
                    . '(doc.querySelector("[data-multipicker-root]").dataset.layout==="tree"||button.getBoundingClientRect().right>'
                    . 'button.closest("[data-multipicker-card]").getBoundingClientRect().right-20))'
                    . '&&[...doc.querySelectorAll("[data-multipicker-folder-count]")].every(badge=>'
                    . 'badge.textContent.trim()==="1 selected")'
                    . '&&doc.querySelector("[data-multipicker-back]").classList.contains("btn-outline-secondary")'
                    . '&&doc.querySelector("[data-multipicker-layout].active").dataset.multipickerLayout==='
                    . json_encode($view) . ';})()'
            );
            if (!$valid) {
                throw new Exception('Selection differs in ' . $view . ' layout.');
            }
        }
    }

    /**
     * Reopening the cached native chooser must not reuse a consumed approval.
     * @Then /^the reopened standalone browser is ready$/
     */
    public function reopened_browser(): void {
        $valid = $this->getSession()->getDriver()->evaluateScript(
            '(()=>{const doc=document.querySelector(".fp-object-container").contentDocument;'
                . 'return doc.querySelector("[data-multipicker-error]").hidden'
                . '&&!!doc.querySelector("[data-multipicker-card]");})()'
        );
        if (!$valid) {
            throw new Exception('A consumed import invalidated the reusable browser.');
        }
    }

    /**
     * Search refreshes only results and keeps selected sources.
     * @Then /^standalone search preserves and clears its restriction$/
     */
    public function search_selection(): void {
        $this->getSession()->getDriver()->executeScript(
            'const doc=document.querySelector(".fp-object-container").contentDocument,'
                . 'input=doc.querySelector("[data-multipicker-query]");'
                . 'input.value="first.txt";input.dispatchEvent(new doc.defaultView.Event("input",{bubbles:true}));'
        );
        if (
            !$this->getSession()->wait(
                10000,
                'document.querySelector(".fp-object-container").contentDocument'
                . '.querySelectorAll("[data-multipicker-card]").length===1'
                . '&&document.querySelector(".fp-object-container").contentDocument'
                . '.querySelector("[data-multipicker-query]").value==="first.txt"'
            )
        ) {
            throw new Exception('Native Server files search did not filter the listing.');
        }
        $this->getSession()->getDriver()->executeScript(
            'document.querySelector(".fp-object-container").contentDocument'
                . '.querySelector("[data-multipicker-clearsearch]").click();'
        );
        if (
            !$this->getSession()->wait(
                10000,
                'document.querySelector(".fp-object-container").contentDocument'
                . '.querySelectorAll("[data-multipicker-card]").length===2'
                . '&&document.querySelector(".fp-object-container").contentDocument'
                . '.querySelector("[data-multipicker-count]").textContent==="2"'
            )
        ) {
            throw new Exception('Clear search lost the original directory or selection.');
        }
    }

    /**
     * Removal selection is separate from the reviewed import approval.
     * @When /^I remove the nested standalone review folder$/
     */
    public function remove_review_folder(): void {
        $this->getSession()->getDriver()->executeScript(
            'const doc=document.querySelector(".fp-object-container").contentDocument;'
                . 'doc.querySelector("[data-multipicker-reviewgroup]").click();'
        );
        $valid = $this->getSession()->getDriver()->evaluateScript(
            '(()=>{const doc=document.querySelector(".fp-object-container").contentDocument;'
                . 'return !doc.querySelector("[data-multipicker-import]").disabled'
                . '&&!doc.querySelector("[data-multipicker-remove]").hidden;})()'
        );
        if (!$valid) {
            throw new Exception('Selecting a review folder disabled import or failed to show removal.');
        }
        $this->getSession()->getDriver()->executeScript(
            'document.querySelector(".fp-object-container").contentDocument.querySelector("[data-multipicker-remove]").click();'
        );
        if (
            !$this->getSession()->wait(
                10000,
                'document.querySelector(".fp-object-container").contentDocument'
                . '.querySelector("[data-multipicker-count]").textContent==="1"'
            )
        ) {
            throw new Exception('Folder removal did not update the resulting file count.');
        }
    }

    /**
     * Closed review folders load their file rows only when opened; delete acts on one row.
     * @Then /^standalone review loads branches on demand and deletes individual files$/
     */
    public function lazy_review_removal(): void {
        $driver = $this->getSession()->getDriver();
        $valid = $driver->evaluateScript(
            '(()=>{const doc=document.querySelector(".fp-object-container").contentDocument;'
                . 'return [...doc.querySelectorAll("[data-multipicker-reviewchildren]")]'
                . '.every(node=>node.hidden&&!node.children.length)'
                . '&&doc.querySelectorAll("[data-multipicker-reviewcheck]").length===2;})()'
        );
        if (!$valid) {
            throw new Exception('Review eagerly rendered closed directory contents.');
        }
        $driver->executeScript(
            'const doc=document.querySelector(".fp-object-container").contentDocument;'
                . '[...doc.querySelectorAll("[data-multipicker-reviewnode]")].find(node=>'
                . 'node.querySelector("strong").textContent==="first.txt").querySelector("[data-multipicker-delete]").click();'
        );
        $this->wait_selection_count(1);
        $driver->executeScript(
            'document.querySelector(".fp-object-container").contentDocument'
                . '.querySelector("[data-multipicker-reviewtoggle]").click();'
        );
        if (
            !$this->getSession()->wait(
                10000,
                '(()=>{const doc=document.querySelector(".fp-object-container").contentDocument;'
                    . 'return [...doc.querySelectorAll("[data-multipicker-reviewnode] strong")].some(node=>'
                    . 'node.textContent==="second.txt")'
                    . '&&!doc.querySelector("[aria-busy=true]");})()'
            )
        ) {
            throw new Exception('Opening a review folder did not load its actual files.');
        }
        $driver->executeScript(
            'const doc=document.querySelector(".fp-object-container").contentDocument;'
                . '[...doc.querySelectorAll("[data-multipicker-reviewnode]")].find(node=>'
                . 'node.querySelector("strong").textContent==="second.txt").querySelector("[data-multipicker-delete]").click();'
        );
        $this->wait_selection_count(0);
        $valid = $driver->evaluateScript(
            '(()=>{const doc=document.querySelector(".fp-object-container").contentDocument;'
                . 'return doc.querySelector("[data-multipicker-import]").disabled'
                . '&&doc.querySelector("[data-multipicker-error]").hidden;})()'
        );
        if (!$valid) {
            throw new Exception('Removing the last reviewed file did not leave a clean empty selection.');
        }
    }

    /**
     * Create a real long filename, preserving the ordinary fixture paths.
     * @Given /^standalone source has a long filename$/
     */
    public function create_long_name(): void {
        global $DB;
        $folder = $DB->get_record('folder', ['name' => 'Source files'], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('folder', $folder->id, $folder->course, false, MUST_EXIST);
        get_file_storage()->create_file_from_string(['contextid' => context_module::instance($cm->id)->id,
            'component' => 'mod_folder', 'filearea' => 'content', 'itemid' => 0, 'filepath' => '/',
            'filename' => str_repeat('Long filename ', 16) . '.txt'], 'Long file');
    }

    /**
     * Compare actual theme line metrics and verify full source location and title text.
     * @Then /^standalone long names occupy at most two lines in every view$/
     */
    public function long_name_layouts(): void {
        $driver = $this->getSession()->getDriver();
        $driver->executeScript(
            'const doc=document.querySelector(".fp-object-container").contentDocument;'
                . '[...doc.querySelectorAll("[data-multipicker-card]")].find(node=>'
                . 'node.querySelector(".multipicker-name").textContent.trim().startsWith("Source files"))'
                . '.querySelector("[data-multipicker-open]").click();'
        );
        if (
            !$this->getSession()->wait(
                10000,
                '[...document.querySelector(".fp-object-container").contentDocument.querySelectorAll(".multipicker-name")]'
                    . '.some(node=>node.textContent.length>200)'
            )
        ) {
            throw new Exception('The source directory did not list its long filename.');
        }
        foreach (['cards', 'list', 'tree'] as $view) {
            $driver->executeScript(
                'document.querySelector(".fp-object-container").contentDocument.querySelector('
                    . json_encode('[data-multipicker-layout="' . $view . '"]') . ').click();'
            );
            $valid = $driver->evaluateScript(
                '(()=>{const doc=document.querySelector(".fp-object-container").contentDocument,'
                    . 'name=[...doc.querySelectorAll(".multipicker-name")].find(node=>node.textContent.length>200),'
                    . 'location=doc.querySelector("[data-multipicker-location]");'
                    . 'return name.getBoundingClientRect().height<=parseFloat(getComputedStyle(name).lineHeight)*2+1'
                    . '&&name.title===name.textContent&&location.textContent.includes(" / ")'
                    . '&&location.title===location.textContent;})()'
            );
            if (!$valid) {
                $detail = $driver->evaluateScript(
                    '(()=>{const doc=document.querySelector(".fp-object-container").contentDocument,'
                        . 'name=[...doc.querySelectorAll(".multipicker-name")].find(node=>node.textContent.length>200),'
                        . 'location=doc.querySelector("[data-multipicker-location]"),css=getComputedStyle(name);'
                        . 'return JSON.stringify({height:name.getBoundingClientRect().height,line:css.lineHeight,'
                        . 'max:css.maxHeight,title:name.title===name.textContent,location:location.textContent,'
                        . 'locationtitle:location.title});})()'
                );
                throw new Exception('Long filename or source location is incorrect in ' . $view . ' view: ' . $detail);
            }
        }
    }

    /**
     * Select the alias mode offered by the actual native control.
     * @When /^I choose standalone file aliases$/
     */
    public function choose_aliases(): void {
        $this->getSession()->getDriver()->executeScript(
            'const doc=document.querySelector(".fp-object-container").contentDocument,'
                . 'select=doc.querySelector("[data-multipicker-copymode]");'
                . 'if(select.querySelector("[data-multipicker-reference]").hidden){throw new Error("Aliases unavailable");}'
                . 'select.value="reference";select.dispatchEvent(new doc.defaultView.Event("change",{bubbles:true}));'
        );
        if (
            !$this->getSession()->wait(
                10000,
                '!document.querySelector(".fp-object-container").contentDocument'
                . '.querySelector("[data-multipicker-review]").disabled'
            )
        ) {
            throw new Exception('Alias selection did not finish validating native limits.');
        }
    }

    /**
     * Verify that the original activity form saved both native aliases.
     * @Then /^the standalone destination contains two aliases$/
     */
    public function saved_aliases(): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => 'SP'], '*', MUST_EXIST);
        $folder = $DB->get_record('folder', ['course' => $course->id, 'name' => 'Destination files'], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('folder', $folder->id, $course->id, false, MUST_EXIST);
        $files = get_file_storage()->get_area_files(
            context_module::instance($cm->id)->id,
            'mod_folder',
            'content',
            0,
            'id',
            false
        );
        $aliases = array_filter($files, static function ($file) {
            return $file->is_external_file();
        });
        if (count($aliases) !== 2) {
            throw new Exception('The owning native form did not retain both imported aliases.');
        }
        foreach ($aliases as $alias) {
            if ($alias->get_content() !== $alias->get_filename() || $alias->get_author() !== 'Source teacher') {
                throw new Exception('Saved alias content or attribution differs from its source.');
            }
        }
    }
}
