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
 * This file contains the definition for the renderable classes for the booking instance
 *
 * @package   paygw_payone
 * @copyright 2021 Georg Maißer {@link http://www.wunderbyte.at}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_payone\output;

use renderer_base;
use renderable;
use stdClass;
use templatable;
use core_payment\helper;

/**
 * This class prepares data for displaying a booking option instance
 *
 * @package     paygw_payone
 * @copyright   2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Bernhard Fischer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkout implements renderable, templatable {

    /** @var array $checkoutid */
    public $data = [];

    /**
     * In the Constructor, we gather all the data we need ans store it in the data property.
     *
     * @param int $hostedcheckoutid The client id.
     * @param string $paymentarea payone secret.
     * @param string $component payone brandname.
     * @param int $itemid The client id.
     */
    public function __construct($hostedcheckoutid, $paymentarea, $component, $itemid) {

        $this->data['orderid'] = $hostedcheckoutid;
        $this->data['paymentarea'] = $paymentarea;
        $this->data['component'] = $component;
        $this->data['itemid'] = $itemid;
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {

        return $this->data;
    }
}
