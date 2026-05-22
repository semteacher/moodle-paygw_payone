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

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Mink\Mink;
use Behat\Mink\Driver\WebDriver;

/**
 * Extra logging/debug context.
 *
 * @package    paygw_payone
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_logging_context implements Context {
    /**
     * After each scenario, if it failed, dump browser logs.
     *
     * @AfterScenario
     */
    public function dump_browser_logs_after_failure(AfterScenarioScope $scope): void {
        global $CFG;

        if ($scope->getTestResult()->getResultCode() !== \Behat\Testwork\Tester\Result\TestResult::FAILED) {
            return;
        }

        $mink = $this->get_mink_from_environment($scope);
        if (!$mink) {
            return;
        }

        $session = $mink->getSession();
        $driver = $session->getDriver();
        if (!($driver instanceof WebDriver) || empty($driver->wdSession)) {
            return;
        }

        $outdir = $CFG->dataroot . '/behat_dump';
        if (!is_dir($outdir)) {
            mkdir($outdir, 0777, true);
        }

        $rawname = $scope->getScenario()->getTitle();
        $sanename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $rawname);
        $ts = date('Ymd_His');

        $consolelogentries = $this->read_wd_log($driver, 'browser');
        file_put_contents(
            $outdir . "/{$ts}_{$sanename}_console.json",
            json_encode($consolelogentries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $perflogentries = $this->read_wd_log($driver, 'performance');
        file_put_contents(
            $outdir . "/{$ts}_{$sanename}_performance.json",
            json_encode($perflogentries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        try {
            $html = $session->getPage()->getHtml();
        } catch (\Throwable $e) {
            $html = '<error>' . s($e->getMessage()) . '</error>';
        }

        file_put_contents($outdir . "/{$ts}_{$sanename}_page.html", $html);
    }

    /**
     * Gets Mink instance from loaded contexts.
     *
     * @param AfterScenarioScope $scope
     * @return Mink|null
     */
    private function get_mink_from_environment(AfterScenarioScope $scope): ?Mink {
        $environment = $scope->getEnvironment();
        foreach ($environment->getContexts() as $context) {
            if (method_exists($context, 'getMink')) {
                return $context->getMink();
            }
        }
        return null;
    }

    /**
     * Reads webdriver log and prevents teardown-time fatal failures.
     *
     * @param WebDriver $driver
     * @param string $logtype
     * @return array
     */
    private function read_wd_log(WebDriver $driver, string $logtype): array {
        try {
            return $driver->wdSession->log($logtype);
        } catch (\Throwable $e) {
            return [['level' => 'ERROR', 'message' => 'Could not read ' . $logtype . ' log: ' . $e->getMessage()]];
        }
    }
}
