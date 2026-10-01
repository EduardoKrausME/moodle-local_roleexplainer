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
 * Role explainer administration page.
 *
 * @package   local_roleexplainer
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_roleexplainer\ai_explainer;
use local_roleexplainer\form\analysis_form;
use local_roleexplainer\links_builder;
use local_roleexplainer\output\presenter;
use local_roleexplainer\permission_analyser;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_roleexplainer');
$systemcontext = context_system::instance();
require_capability('local/roleexplainer:use', $systemcontext);

$PAGE->set_url(new moodle_url('/local/roleexplainer/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_roleexplainer'));
$PAGE->set_heading(get_string('pluginname', 'local_roleexplainer'));

$form = new analysis_form();

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('intro', 'local_roleexplainer'), 'info', false);
$form->display();

if ($data = $form->get_data()) {
    try {
        $analyser = new permission_analyser();
        $analysis = $analyser->analyse((int)$data->userid, (int)$data->contextid, (string)$data->capability, true);

        $ai = null;
        $aierror = null;
        try {
            $ai = (new ai_explainer())->explain($analysis, (string)$data->mode);
        } catch (Throwable $e) {
            $aierror = $e->getMessage();
        }

        $links = (new links_builder())->build($analysis);
        $templatedata = (new presenter())->prepare($analysis, $ai, $aierror, $links);
        echo $OUTPUT->render_from_template('local_roleexplainer/result', $templatedata);
    } catch (moodle_exception $e) {
        echo $OUTPUT->notification($e->getMessage(), 'error');
    }
}

echo $OUTPUT->footer();
