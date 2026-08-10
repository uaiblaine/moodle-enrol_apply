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
 * Form a user fills in to apply for a course enrolment.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo.com (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     emeneo.com (http://emeneo.com/)
 * @author     Johannes Burk <johannes.burk@sudile.com>
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/user/editlib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * Form a user fills in to apply for a course enrolment.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo.com (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_apply_apply_form extends moodleform {
    /** @var stdClass The enrol instance this form applies to. */
    protected $instance;

    /**
     * Give each instance its own form id so several instances can coexist on one page.
     *
     * @return string Form identifier.
     */
    protected function get_form_identifier() {
        return $this->_customdata->id . '_' . get_class($this);
    }

    /**
     * Build the application form.
     *
     * @return void
     */
    public function definition() {
        global $DB, $OUTPUT, $USER;

        $mform = $this->_form;
        $instance = $this->_customdata;
        $this->instance = $instance;
        $plugin = enrol_get_plugin('apply');

        $mform->addElement('header', 'selfheader', $plugin->get_instance_name($instance));

        if ($instance->customint3 > 0) {
            $count = $DB->count_records('user_enrolments', ['enrolid' => $instance->id]);
            if ($count < $instance->customint3) {
                $tip = get_string('maxenrolled_tip', 'enrol_apply', (object) [
                    'count' => $count,
                    'max' => $instance->customint3,
                ]);
                $mform->addElement('html', $OUTPUT->notification($tip, \core\output\notification::NOTIFY_INFO, false));
            }
        }

        if (!empty($instance->customtext1)) {
            $mform->addElement('html', format_text($instance->customtext1, FORMAT_HTML));
        }

        if ($instance->customint7) {
            $commenttitle = get_string('comment', 'enrol_apply');
            if (!empty($instance->customtext2)) {
                $commenttitle = format_string($instance->customtext2);
            }
            $mform->addElement('textarea', 'applydescription', $commenttitle, ['cols' => 80, 'rows' => 5]);
            $mform->setType('applydescription', PARAM_TEXT);
        }

        // Standard and extra user profile fields, when the instance collects them.
        if ($instance->customint1) {
            $editoroptions = null;
            $filemanageroptions = null;
            useredit_shared_definition($mform, $editoroptions, $filemanageroptions, $USER);
        }

        if ($instance->customint2) {
            profile_definition($mform, $USER->id);
        }

        $mform->setDefaults((array) $USER);

        $this->add_action_buttons(false, get_string('enrolme', 'enrol_self'));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $instance->courseid);

        $mform->addElement('hidden', 'instance');
        $mform->setType('instance', PARAM_INT);
        $mform->setDefault('instance', $instance->id);
    }
}
