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
use Behat\Mink\Driver\Selenium2Driver;

/**
 * Extra logging/debug context.
 *
 * IMPORTANT:
 *   Add this context to your plugin's Behat suite so Moodle loads it.
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
    public function dump_browser_logs_after_failure(AfterScenarioScope $scope) {
        Global $CFG;
        // Only on fail.
        if ($scope->getTestResult()->getResultCode() === \Behat\Testwork\Tester\Result\TestResult::FAILED) {
            // Moodle's Behat bootstrap gives us $this->getSession() usually via behat_base.
            // But this context is standalone, so we need to reach Mink through the environment.
            // Trick: most Moodle contexts are registered in the same Mink instance. We can fetch it like this.
            $environment = $scope->getEnvironment();

            /** @var Mink $mink */
            $mink = null;
            foreach ($environment->getContexts() as $ctx) {
                if (method_exists($ctx, 'getMink')) {
                    $mink = $ctx->getMink();
                    break;
                }
            }
            if (!$mink) {
                // Can't get Mink, abort.
                return;
            }

            $session = $mink->getSession();
            $driver = $session->getDriver();

            if (!($driver instanceof Selenium2Driver)) {
                return;
            }

            // Prepare output dir.
            $outdir = $CFG->dataroot . '/behat_dump';
            if (!is_dir($outdir)) {
                @mkdir($outdir, 0777, true);
            }

            // Use scenario name (sanitized) as filename stem.
            $rawname = $scope->getScenario()->getTitle();
            $sanename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $rawname);
            $ts = date('Ymd_His');

            // 1) Browser console log.
            try {
                $consolelogentries = $driver->wdSession->log('browser');
            } catch (\Exception $e) {
                $consolelogentries = [['level' => 'ERROR', 'message' => 'Could not read browser log: ' . $e->getMessage()]];
            }

            file_put_contents(
                $outdir . "/{$ts}_{$sanename}_console.json",
                json_encode($consolelogentries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            // 2) Performance log (network-ish / timeline-ish).
            try {
                $perflogentries = $driver->wdSession->log('performance');
            } catch (\Exception $e) {
                $perflogentries = [['level' => 'ERROR', 'message' => 'Could not read performance log: ' . $e->getMessage()]];
            }

            file_put_contents(
                $outdir . "/{$ts}_{$sanename}_performance.json",
                json_encode($perflogentries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            // 3) HTML snapshot of the page at failure time.
            try {
                $html = $session->getPage()->getHtml();
            } catch (\Exception $e) {
                $html = '<error>' . $e->getMessage() . '</error>';
            }

            file_put_contents(
                $outdir . "/{$ts}_{$sanename}_page.html",
                $html
            );
        }
    }
}