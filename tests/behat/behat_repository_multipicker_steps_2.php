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

require_once(__DIR__ . '/repository_multipicker_context.php');

/**
 * Focused native chooser acceptance steps.
 */
class behat_repository_multipicker_steps_2 extends behat_repository_multipicker_base {
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
        get_file_storage()->create_file_from_string(
            ['contextid' => context_module::instance($cm->id)->id,
                'component' => 'mod_folder', 'filearea' => 'content', 'itemid' => 0, 'filepath' => '/',
                'filename' => str_repeat('Long filename ', 16) . '.txt'],
            'Long file'
        );
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
}
