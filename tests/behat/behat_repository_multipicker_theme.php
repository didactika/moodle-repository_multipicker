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
 * Native theme inheritance acceptance checks.
 *
 * @package repository_multipicker
 * @copyright 2026 Didactika.org
 * @author Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/repository_multipicker_context.php');

/**
 * Verify native modes, runtime tokens and embedded background without fixing a palette.
 *
 * @package repository_multipicker
 */
class behat_repository_multipicker_theme extends behat_repository_multipicker_base {
    /**
     * Compare the actual owning theme with the embedded document and its painted body.
     * @Then /^the standalone MultiPicker should inherit its native theme$/
     */
    public function assert_native_theme(): void {
        $condition = '(()=>{const doc=document.querySelector(".fp-object-container")?.contentDocument;'
            . 'if(!doc||doc.documentElement.getAttribute("data-bs-theme")'
            . '!==document.documentElement.getAttribute("data-bs-theme")){return false;}'
            . 'const host=document.querySelector(".file-picker .fp-content"),styles=getComputedStyle(host);'
            . 'if(doc.documentElement.style.getPropertyValue("--bs-primary")'
            . '!==styles.getPropertyValue("--bs-primary")){return false;}'
            . 'let expected="";for(let element=host;element;element=element.parentElement){'
            . 'const colour=getComputedStyle(element).backgroundColor;'
            . 'if(colour!=="transparent"&&colour!=="rgba(0, 0, 0, 0)"){expected=colour;break;}}'
            . 'if(doc.defaultView.getComputedStyle(doc.body).backgroundColor!==expected){return false;}'
            . 'if(Array.from(doc.querySelectorAll("*")).some(element=>'
            . 'doc.defaultView.getComputedStyle(element,":before").content'
            . '.startsWith("\"Deprecated style in use"))){return false;}'
            . 'const icons=Array.from(doc.querySelectorAll(".fa-folder.icon,.fa-file.icon"));'
            . 'return icons.length>0&&icons.every(icon=>{const paint=doc.defaultView.getComputedStyle(icon);'
            . 'return paint.color===doc.defaultView.getComputedStyle(icon.parentElement).color'
            . '&&paint.color!==expected;});})()';
        if (!$this->getSession()->wait(10000, $condition)) {
            throw new \Exception(
                'The embedded chooser does not inherit the owning modal surface, mapped icons or supported Bootstrap presentation.'
            );
        }
    }

    /**
     * Change and restore a Bootstrap token using another colour supplied by the theme.
     * @Then /^the standalone MultiPicker should follow runtime Bootstrap changes$/
     */
    public function change_native_token(): void {
        $driver = $this->getSession()->getDriver();
        $driver->executeScript(
            'const root=document.documentElement,styles=getComputedStyle(root);'
            . 'window.previousNativePrimary=root.style.getPropertyValue("--bs-primary");'
            . 'root.style.setProperty("--bs-primary",styles.getPropertyValue("--bs-success")'
            . '||styles.getPropertyValue("--success"));'
        );
        $this->assert_native_theme();
        $driver->executeScript(
            'if(window.previousNativePrimary){document.documentElement.style.setProperty('
            . '"--bs-primary",window.previousNativePrimary);}else{document.documentElement.style.removeProperty("--bs-primary");}'
        );
        $this->assert_native_theme();
        $driver->executeScript(
            'const host=document.querySelector(".file-picker .fp-content"),styles=getComputedStyle(host);'
            . 'window.previousNativeSurface=host.style.backgroundColor;'
            . 'host.style.backgroundColor=styles.getPropertyValue("--bs-success")||styles.getPropertyValue("--success");'
        );
        $this->assert_native_theme();
        $driver->executeScript(
            'document.querySelector(".file-picker .fp-content").style.backgroundColor=window.previousNativeSurface;'
        );
        $this->assert_native_theme();
    }

    /**
     * Capture the native modal after checking the embedded viewport bounds.
     * @Then /^I capture the standalone MultiPicker theme$/
     */
    public function capture_theme(): void {
        $page = $this->getSession()->getDriver()->evaluateScript(
            'document.querySelector(".fp-object-container").contentDocument'
            . '.querySelector(".multipicker-reviewtree")?"review":"browser"'
        );
        foreach (['desktop' => 1366, 'mobile' => 480] as $size => $width) {
            $this->getSession()->resizeWindow($width, 900);
            if (
                !$this->getSession()->wait(3000, '(()=>{'
                . 'const doc=document.querySelector(".fp-object-container").contentDocument;'
                . 'return doc.documentElement.scrollWidth<=doc.documentElement.clientWidth+1;})()')
            ) {
                throw new \Exception('The standalone chooser overflows the viewport horizontally.');
            }
            file_put_contents('/tmp/multipicker-' . $page . '-' . $size . '.png', $this->getSession()->getScreenshot());
        }
        $this->getSession()->resizeWindow(1366, 1000);
    }

    /**
     * Use the real Boost switcher while the embedded document remains open.
     * @When /^I switch the standalone MultiPicker owning theme to "(light|dark|auto)"$/
     * @param string $mode Native preference value.
     */
    public function switch_native_mode(string $mode): void {
        $this->getSession()->getDriver()->executeScript(
            'document.querySelector("[data-action=set-colourmode][data-colourmode=' . $mode . ']").click();'
        );
        if (!$this->getSession()->wait(10000, 'document.documentElement.dataset.colourmode===' . json_encode($mode))) {
            throw new \Exception('The native Boost colour mode switch did not complete.');
        }
        $this->assert_native_theme();
    }
}
