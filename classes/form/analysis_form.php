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

namespace local_roleexplainer\form;

use context_system;
use core_user;
use core_user\fields;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Permission analysis form.
 *
 * @package   local_roleexplainer
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analysis_form extends moodleform {
    /**
     * Define the form.
     *
     * @return void
     */
    public function definition(): void {
        global $DB, $OUTPUT;

        $mform = $this->_form;
        $context = context_system::instance();

        $userattributes = [
            'multiple' => false,
            'ajax' => 'core_user/form_user_selector',
            'valuehtmlcallback' => static function ($userid) use ($context, $OUTPUT) {
                $fields = fields::for_name()->with_identity($context, false);
                $record = core_user::get_user($userid, 'id ' . $fields->get_sql()->selects, MUST_EXIST);
                $user = (object)[
                    'id' => $record->id,
                    'fullname' => fullname($record, has_capability('moodle/site:viewfullnames', $context)),
                    'extrafields' => [],
                ];
                foreach ($fields->get_required_fields([fields::PURPOSE_IDENTITY]) as $extrafield) {
                    $user->extrafields[] = (object)[
                        'name' => $extrafield,
                        'value' => s($record->$extrafield),
                    ];
                }
                return $OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $user);
            },
        ];
        $mform->addElement('autocomplete', 'userid', get_string('targetuser', 'local_roleexplainer'), [], $userattributes);
        $mform->addRule('userid', get_string('required'), 'required', null, 'client');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('text', 'contextid', get_string('contextid', 'local_roleexplainer'));
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', $context->id);
        $mform->addRule('contextid', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('contextid', 'contextid', 'local_roleexplainer');

        $capabilities = [];
        $records = $DB->get_records('capabilities', null, 'name ASC', 'id, name');
        foreach ($records as $record) {
            $capabilities[$record->name] = $record->name;
        }
        $mform->addElement('autocomplete', 'capability', get_string('capability', 'local_roleexplainer'), $capabilities, [
            'multiple' => false,
        ]);
        $mform->addRule('capability', get_string('required'), 'required', null, 'client');
        $mform->setType('capability', PARAM_CAPABILITY);

        $mform->addElement('select', 'mode', get_string('mode', 'local_roleexplainer'), [
            'full' => get_string('modefull', 'local_roleexplainer'),
            'whycan' => get_string('modewhycan', 'local_roleexplainer'),
            'whycannot' => get_string('modewhycannot', 'local_roleexplainer'),
        ]);
        $mform->setType('mode', PARAM_ALPHA);

        $this->add_action_buttons(false, get_string('analyse', 'local_roleexplainer'));
    }

    /**
     * Validate the form.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files): array {
        global $DB;

        $errors = parent::validation($data, $files);
        if (!empty($data['userid']) && !$DB->record_exists('user', ['id' => $data['userid'], 'deleted' => 0])) {
            $errors['userid'] = get_string('invaliduser', 'local_roleexplainer');
        }
        if (!empty($data['contextid']) && !$DB->record_exists('context', ['id' => $data['contextid']])) {
            $errors['contextid'] = get_string('invalidcontext', 'local_roleexplainer');
        }
        return $errors;
    }
}
