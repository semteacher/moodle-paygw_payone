<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Behat steps for paygw_payone.
 *
 * @package    paygw_payone
 * @category   test
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Behat\Mink\Exception\ExpectationException;

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Steps specific to paygw_payone.
 *
 * @package    paygw_payone
 * @category   test
 * @copyright  2024 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_paygw_payone extends behat_base {

    /**
     * Click an element and wait until browser returns from hosted checkout back to Moodle.
     *
     * Keeping click + redirect wait in one step avoids the next-step JS hook racing with
     * slow CI redirects.
     *
     * @When /^I click on "(?P<element_string>(?:[^"\\]|\\.)*)" "(?P<selector_string>[^"\\]*)" and wait for PayOne redirect$/
     * @param string $element
     * @param string $selectortype
     */
    public function i_click_and_wait_for_payone_redirect(string $element, string $selectortype): void {
        global $CFG;

        $this->execute('behat_general::i_click_on', [$element, $selectortype]);

        $condition = 'window.location.href.indexOf(' . json_encode($CFG->wwwroot) . ') === 0';
        $timeoutms = self::get_extended_timeout() * 1000;
        $returned = $this->getSession()->wait($timeoutms, $condition);

        if (!$returned) {
            throw new ExpectationException(
                'Timed out waiting for redirect back to Moodle after PayOne hosted checkout.',
                $this->getSession()
            );
        }

        $this->wait_for_pending_js();
    }
}
