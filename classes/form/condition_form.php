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

namespace enrol_apply\form;

use core_customfield\field_controller;
use enrol_apply\local\conditions;
use moodleform;

/**
 * When one application question is shown: always, or on the answer to an earlier question.
 *
 * Two steps, with no JavaScript: the first chooses the controlling question, and the second,
 * shown once one is chosen, chooses the answers that show this question. The second step's
 * elements are named after the controlling question's id, so switching to another one and
 * saving at once posts nothing for it, and the form asks again instead of reusing the answers
 * chosen for the previous one.
 *
 * Custom data: 'enrolid' (int), 'fieldid' (int, the question configured), 'fields' (what
 * answers::fields() returns for the instance) and 'controllerid' (int, the controlling question
 * the second step is drawn for, 0 for none). The page has already checked the question belongs
 * to the instance; validation() checks everything posted again.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition_form extends moodleform {
    /** @var string The no-submit button that draws the second step. */
    public const CHOOSE_BUTTON = 'choosecontroller';

    /**
     * The name of the second step's element for a select controller.
     *
     * @param int $controllerid The controlling field.
     * @return string
     */
    public static function positions_name(int $controllerid): string {
        return 'positions_' . $controllerid;
    }

    /**
     * The name of the second step's element for a checkbox controller.
     *
     * @param int $controllerid The controlling field.
     * @return string
     */
    public static function operator_name(int $controllerid): string {
        return 'operator_' . $controllerid;
    }

    /**
     * The form's elements.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $fields = $this->_customdata['fields'];
        $fieldid = (int) $this->_customdata['fieldid'];
        $controllers = conditions::controllers($fields, $fieldid);

        $mform->addElement('hidden', 'id', (int) $this->_customdata['enrolid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'fieldid', $fieldid);
        $mform->setType('fieldid', PARAM_INT);

        $name = isset($fields[$fieldid]) ? $fields[$fieldid]->get_formatted_name() : '';
        $mform->addElement('static', 'question', get_string('conditionquestion', 'enrol_apply'), $name);

        if (!$controllers) {
            $mform->addElement('static', 'nocontrollers', '', get_string('conditionsnone', 'enrol_apply'));
        }

        $choices = [0 => get_string('conditionalways', 'enrol_apply')];
        foreach ($controllers as $id => $controller) {
            $choices[$id] = $controller->get_formatted_name();
        }
        $mform->addElement('select', 'dependsonfieldid', get_string('conditioncontroller', 'enrol_apply'), $choices);
        $mform->setType('dependsonfieldid', PARAM_INT);
        $mform->addElement('submit', self::CHOOSE_BUTTON, get_string('conditionchoose', 'enrol_apply'));
        $mform->registerNoSubmitButton(self::CHOOSE_BUTTON);

        $controllerid = (int) $this->_customdata['controllerid'];
        if (isset($controllers[$controllerid])) {
            $this->add_answer_step($mform, $controllers[$controllerid]);
        }

        $this->add_action_buttons();
    }

    /**
     * The second step: which answers of the controlling question show this one.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param field_controller $controller The controlling question.
     * @return void
     */
    protected function add_answer_step(\MoodleQuickForm $mform, field_controller $controller): void {
        $controllerid = (int) $controller->get('id');

        if ($controller->get('type') === 'select') {
            $boxes = [];
            foreach (conditions::options($controller) as $position => $option) {
                // The plain option text, escaped once here: a checkbox's text is rendered raw.
                $boxes[] = $mform->createElement('advcheckbox', $position, '', s($option));
            }
            $mform->addGroup(
                $boxes,
                self::positions_name($controllerid),
                get_string('conditionpositions', 'enrol_apply'),
                '<br>',
                true
            );
            return;
        }

        $mform->addElement('select', self::operator_name($controllerid), get_string('conditionoperator', 'enrol_apply'), [
            conditions::OPERATOR_CHECKED => get_string('conditionchecked', 'enrol_apply'),
            conditions::OPERATOR_NOTCHECKED => get_string('conditionnotchecked', 'enrol_apply'),
        ]);
    }

    /**
     * The condition the submitted data describes.
     *
     * @param array $data Submitted data.
     * @return array The controlling field id (0 for "always shown"), the operator and the positions.
     */
    public function condition_from(array $data): array {
        $controllerid = (int) ($data['dependsonfieldid'] ?? 0);
        $fields = $this->_customdata['fields'];
        if (!$controllerid || !isset($fields[$controllerid])) {
            return [$controllerid, '', []];
        }

        if ($fields[$controllerid]->get('type') === 'select') {
            $positions = [];
            foreach ((array) ($data[self::positions_name($controllerid)] ?? []) as $position => $checked) {
                if (!empty($checked)) {
                    $positions[] = (int) $position;
                }
            }
            return [$controllerid, conditions::OPERATOR_IN, $positions];
        }

        return [$controllerid, (string) ($data[self::operator_name($controllerid)] ?? ''), []];
    }

    /**
     * Check what was posted against the questions the instance asks now.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Element name => error message.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        [$controllerid, $operator, $positions] = $this->condition_from($data);
        $fieldid = (int) $this->_customdata['fieldid'];
        if (!$controllerid) {
            if (!isset($this->_customdata['fields'][$fieldid])) {
                $errors['dependsonfieldid'] = get_string('conditionerror_field', 'enrol_apply');
            }
            return $errors;
        }

        $problem = conditions::problem($this->_customdata['fields'], $fieldid, $controllerid, $operator, $positions);
        if ($problem === 'conditionerror_positions') {
            $errors[self::positions_name($controllerid)] = conditions::problem_message($problem);
        } else if ($problem === 'conditionerror_operator') {
            $errors[self::operator_name($controllerid)] = conditions::problem_message($problem);
        } else if ($problem !== '') {
            $errors['dependsonfieldid'] = conditions::problem_message($problem);
        }

        return $errors;
    }
}
