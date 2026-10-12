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
 * Shared native chooser readiness helpers.
 */
abstract class behat_repository_multipicker_base extends behat_base {
    /**
     * Wait for server validation of a direct checkbox, card or directory change.
     * @param int $count Expected number of resulting files.
     */
    protected function wait_selection_count(int $count): void {
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
}
