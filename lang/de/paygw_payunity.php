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
 * Strings for component 'paygw_payunity', language 'en'
 *
 * @package    paygw_payunity
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['amountmismatch'] = 'Der Betrag, den Sie zu zahlen versuchen, entspricht nicht dem gespeicherten Betrag. Ihr Konto wurde nicht belastet.';
$string['authorising'] = 'Authorisiere die Zahlung. Bitte warten...';
$string['brandname'] = 'Markenname';
$string['brandname_help'] = 'Ein optionaler Name der den Namen Payiunity auf der Zahlungsseite ersetzt.';
$string['cannotfetchorderdetails'] = 'Konnte keine Zahlungsdetails von Payunity erhalten. Ihr Konto wurde nicht belastet.';
$string['clientid'] = 'Kunden ID';
$string['clientid_help'] = 'Die Kunden ID die PayUnity für Ihre Seite generiert hat.';
$string['environment'] = 'Umgebung.';
$string['environment_help'] = 'You can set this to Sandbox if you are using sandbox accounts (for testing purpose only).';
$string['gatewaydescription'] = 'PayUnity ist ein authorisierter Zahlungsanbieter um Ihre Kreditkartenzahlung abzuwickeln.';
$string['gatewayname'] = 'PayUnity';
$string['internalerror'] = 'Ein interner Fehler ist aufgetreten. Bitte kontaktieren Sie uns.';
$string['live'] = 'Live';
$string['paymentnotcleared'] = 'Zahlung wurde von PayUnity nicht akzeptiert.';
$string['pluginname'] = 'PayUnity';
$string['pluginname_desc'] = 'Das PayUnity plugin erlaubt es, Zahlungen mittels PayUnity zu erhalten.';
$string['privacy:metadata'] = 'Das PayUnity plugin speichert keine persönlichen Daten.';
$string['repeatedorder'] = 'Der Auftrag wurde bereits früher bearbeitet.';
$string['sandbox'] = 'Sandbox';
$string['secret'] = 'Access Token';
$string['secret_help'] = 'Der access Token den PayUnity für diese Anwendung bereitstellt.';

$string['checkout'] = 'Checkout';
$string['loading'] = 'Laden...';

$string['success'] = 'Erfolg';
$string['error'] = 'Fehler';
$string['proceed'] = 'Fortfahren';

$string['payment_added'] = 'Zahlungstransaktion wurde gestartet. (Offener Auftrag wurde angelegt.)';
$string['payment_completed'] = 'Zahlungstransaktion wurde abgeschlossen.';
$string['payment_successful'] = 'Zahlung erfolgreich. Klicken sie auf "Fortfahren" um zu Ihrem Kurs weitergeleitet zu werden.';
$string['payment_error'] = 'Ein Fehler ist bei der Zahlung mit PayUnity aufgetreten. Bitte versuchen sie es später erneut.';
$string['payment_alreadyexists'] = 'Zahlung nicht bearbeitet, da sie bereits existiert.';
$string['delivery_error'] = 'Die Zahlung war erfolgreich, aber bei der Auslieferung gab es ein Problem. Bitte wenden Sie sich an den Support.';

$string['other_options'] = "Andere Zahlungsarten";
$string['more'] = "Mehr";

$string['quick_checkout'] = "Schneller Checkout";
$string['paycredit'] = "Mit Kreditkarte zahlen";

$string['unknownbrand'] = "UK";
$string['MASTER'] = "MC";
$string['VISA'] = "VC";
$string['EPS'] = "EP";
