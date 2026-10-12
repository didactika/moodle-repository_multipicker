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
class behat_repository_multipicker_steps_3 extends behat_repository_multipicker_base {
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
        $aliases = array_filter(
            $files,
            static function ($file) {
                return $file->is_external_file();
            }
        );
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
