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
 * When an application question is shown: its condition on the answer to an earlier question.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use core_customfield\field_controller;
use MoodleQuickForm;
use stdClass;

/**
 * When an application question is shown: its condition on the answer to an earlier question.
 *
 * One condition per question and instance, stored in enrol_apply_fieldcond and owned by the
 * instance, so a field of a shared category can be controlled, and can control, differently
 * in every instance (docs/design/custom-application-fields.md, D12). Only an EARLIER question
 * may control a later one, in the order answers::fields() returns; that rules cycles out, and
 * a question whose controller is hidden is hidden too.
 *
 * The rule is checked again wherever a condition is read, not only where it is written: core's
 * management UI can reorder fields, edit a select's options or switch a shared category off
 * after the condition was saved. A stored condition that no longer passes is not effective, and
 * the question it names is then always shown: a question is never lost to a stale condition.
 *
 * The browser hides questions through the form's hideIf rules; the server decides again from the
 * submitted data (is_visible()), and only that decision reaches validation and the snapshot.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conditions {
    /** @var string The table holding the conditions. */
    public const TABLE = 'enrol_apply_fieldcond';

    /** @var string A select controller's answer is one of the stored option positions. */
    public const OPERATOR_IN = 'in';

    /** @var string A checkbox controller is checked. */
    public const OPERATOR_CHECKED = 'checked';

    /** @var string A checkbox controller is not checked. */
    public const OPERATOR_NOTCHECKED = 'notchecked';

    /** @var string[] Operators each controlling field type accepts, keyed by type. */
    public const OPERATORS = [
        'select' => [self::OPERATOR_IN],
        'checkbox' => [self::OPERATOR_CHECKED, self::OPERATOR_NOTCHECKED],
    ];

    /**
     * The stored conditions of an instance, whether still effective or not.
     *
     * @param int $enrolid Enrol instance id.
     * @return stdClass[] Rows of enrol_apply_fieldcond keyed by the controlled field id, each with
     *         an extra 'positions' member: the stored option positions as integers.
     */
    public static function stored(int $enrolid): array {
        global $DB;

        $conditions = [];
        foreach ($DB->get_records(self::TABLE, ['enrolid' => $enrolid], 'id') as $row) {
            $row->fieldid = (int) $row->fieldid;
            $row->dependsonfieldid = (int) $row->dependsonfieldid;
            $row->positions = self::parse_positions((string) $row->value);
            $conditions[$row->fieldid] = $row;
        }

        return $conditions;
    }

    /**
     * The stored conditions that still hold against the questions the instance asks now.
     *
     * @param stdClass $instance Enrol instance.
     * @param field_controller[] $fields What answers::fields() returns for the instance.
     * @return stdClass[] Keyed by the controlled field id; see stored().
     */
    public static function effective(stdClass $instance, array $fields): array {
        return array_filter(
            self::stored((int) $instance->id),
            static fn(stdClass $condition): bool => self::problem(
                $fields,
                $condition->fieldid,
                $condition->dependsonfieldid,
                (string) $condition->operator,
                $condition->positions
            ) === ''
        );
    }

    /**
     * The option texts of a select field by their 1-based position, in the plain spelling.
     *
     * Core's select stores the position of the chosen option, 0 being "nothing chosen"; this is
     * the same split customfield_select\field_controller::get_options() makes.
     *
     * @param field_controller $field A select field.
     * @return array Position => option text.
     */
    public static function options(field_controller $field): array {
        $context = $field->get_handler()->get_configuration_context();
        $options = preg_split(
            "/\s*\n\s*/",
            trim((string) $field->get_configdata_property('options')),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $positions = [];
        foreach ($options as $index => $option) {
            $positions[$index + 1] = format_string($option, true, ['context' => $context, 'escape' => false]);
        }
        return $positions;
    }

    /**
     * The questions that may control a given one: earlier select and checkbox questions.
     *
     * @param field_controller[] $fields What answers::fields() returns, in form order.
     * @param int $fieldid The controlled field.
     * @return field_controller[] Keyed by field id, in form order; empty when the field is not asked.
     */
    public static function controllers(array $fields, int $fieldid): array {
        $controllers = [];
        foreach ($fields as $id => $field) {
            if ((int) $id === $fieldid) {
                return $controllers;
            }
            if (array_key_exists($field->get('type'), self::OPERATORS)) {
                $controllers[(int) $id] = $field;
            }
        }

        return [];
    }

    /**
     * What is wrong with a condition, as a string identifier of enrol_apply, or '' when nothing is.
     *
     * The one check behind the configuration form, the restore and every read: the archive and
     * the browser are untrusted, and the configuration may have changed since the row was written.
     *
     * @param field_controller[] $fields What answers::fields() returns, in form order.
     * @param int $fieldid The controlled field.
     * @param int $controllerid The controlling field.
     * @param string $operator One of the OPERATOR_ constants.
     * @param int[] $positions Option positions, for OPERATOR_IN.
     * @return string
     */
    public static function problem(array $fields, int $fieldid, int $controllerid, string $operator, array $positions): string {
        if (!array_key_exists($fieldid, $fields)) {
            return 'conditionerror_field';
        }
        if (!array_key_exists($controllerid, $fields)) {
            return 'conditionerror_controller';
        }
        $type = $fields[$controllerid]->get('type');
        if (!array_key_exists($type, self::OPERATORS)) {
            return 'conditionerror_type';
        }
        if (!array_key_exists($controllerid, self::controllers($fields, $fieldid))) {
            return 'conditionerror_later';
        }
        if (!in_array($operator, self::OPERATORS[$type], true)) {
            return 'conditionerror_operator';
        }
        if ($operator === self::OPERATOR_IN) {
            $options = self::options($fields[$controllerid]);
            if (!$positions || array_diff($positions, array_keys($options))) {
                return 'conditionerror_positions';
            }
        }

        return '';
    }

    /**
     * The message of what problem() returned, in the plain spelling.
     *
     * @param string $problem What problem() returned.
     * @return string The message, or '' for no problem.
     */
    public static function problem_message(string $problem): string {
        return match ($problem) {
            'conditionerror_controller' => get_string('conditionerror_controller', 'enrol_apply'),
            'conditionerror_field' => get_string('conditionerror_field', 'enrol_apply'),
            'conditionerror_later' => get_string('conditionerror_later', 'enrol_apply'),
            'conditionerror_operator' => get_string('conditionerror_operator', 'enrol_apply'),
            'conditionerror_positions' => get_string('conditionerror_positions', 'enrol_apply'),
            'conditionerror_type' => get_string('conditionerror_type', 'enrol_apply'),
            default => '',
        };
    }

    /**
     * Option positions from their stored spelling: sorted, unique, positive integers.
     *
     * @param string $value Comma-separated positions.
     * @return int[]
     */
    public static function parse_positions(string $value): array {
        $positions = [];
        foreach (explode(',', $value) as $position) {
            $position = trim($position);
            if (ctype_digit($position) && (int) $position > 0) {
                $positions[] = (int) $position;
            }
        }
        $positions = array_values(array_unique($positions));
        sort($positions);

        return $positions;
    }

    /**
     * Whether a submitted answer satisfies a condition, ignoring whether its controller is shown.
     *
     * @param stdClass $condition An effective condition.
     * @param field_controller $controller The controlling field.
     * @param array $data Submitted form data.
     * @return bool
     */
    protected static function holds(stdClass $condition, field_controller $controller, array $data): bool {
        $raw = $data[answers::element_name($controller)] ?? null;
        if (is_array($raw) || is_object($raw)) {
            return false;
        }

        switch ($condition->operator) {
            case self::OPERATOR_IN:
                return in_array((int) $raw, $condition->positions, true);
            case self::OPERATOR_CHECKED:
                return !empty($raw);
            case self::OPERATOR_NOTCHECKED:
                return empty($raw);
            default:
                return false;
        }
    }

    /**
     * Whether a question is shown, given the submitted data.
     *
     * A question is shown when it has no effective condition, or when its controller is shown
     * and the controller's answer satisfies the condition. Controllers are always earlier, so the
     * recursion ends at the first question.
     *
     * @param int $fieldid The question.
     * @param stdClass[] $conditions What effective() returns.
     * @param field_controller[] $fields What answers::fields() returns.
     * @param array $data Submitted form data.
     * @return bool
     */
    public static function is_visible(int $fieldid, array $conditions, array $fields, array $data): bool {
        if (!isset($conditions[$fieldid])) {
            return true;
        }
        $condition = $conditions[$fieldid];

        return self::is_visible($condition->dependsonfieldid, $conditions, $fields, $data)
            && self::holds($condition, $fields[$condition->dependsonfieldid], $data);
    }

    /**
     * The questions hidden by the submitted data.
     *
     * @param stdClass[] $conditions What effective() returns.
     * @param field_controller[] $fields What answers::fields() returns.
     * @param array $data Submitted form data.
     * @return array Field id => true, for each hidden question.
     */
    public static function hidden(array $conditions, array $fields, array $data): array {
        $hidden = [];
        foreach (array_keys($conditions) as $fieldid) {
            if (!self::is_visible((int) $fieldid, $conditions, $fields, $data)) {
                $hidden[(int) $fieldid] = true;
            }
        }

        return $hidden;
    }

    /**
     * Wire the conditions into the application form.
     *
     * Each conditional question gets one hideIf per link of its chain, so a hidden controller
     * hides its dependants whatever value it still holds in the browser. core_form's dependency
     * manager has no "not in" operator, so "shown when one of these options" is written as "hidden
     * when in the other positions", 0 ("nothing chosen") among them; "shown when checked" is
     * "hidden when not checked", and the reverse.
     *
     * The required rule core added is removed from a conditional question: a hidden question
     * would otherwise refuse the form. answers::validate() enforces it again when the question
     * is shown according to the submitted data.
     *
     * @param MoodleQuickForm $mform The application form, with the questions already added.
     * @param stdClass[] $conditions What effective() returns.
     * @param field_controller[] $fields What answers::fields() returns.
     * @return void
     */
    public static function add_to_form(MoodleQuickForm $mform, array $conditions, array $fields): void {
        foreach ($conditions as $fieldid => $condition) {
            $name = answers::element_name($fields[$fieldid]);
            $names = [$name, $name . '_static'];

            $link = $condition;
            while ($link !== null) {
                $controller = $fields[$link->dependsonfieldid];
                $controllername = answers::element_name($controller);
                foreach ($names as $element) {
                    if (!$mform->elementExists($element)) {
                        continue;
                    }
                    if ($link->operator === self::OPERATOR_IN) {
                        $others = array_diff(range(0, count(self::options($controller))), $link->positions);
                        $mform->hideIf($element, $controllername, 'in', array_map('strval', array_values($others)));
                    } else if ($link->operator === self::OPERATOR_CHECKED) {
                        $mform->hideIf($element, $controllername, 'notchecked');
                    } else {
                        $mform->hideIf($element, $controllername, 'checked');
                    }
                }
                $link = $conditions[$link->dependsonfieldid] ?? null;
            }

            self::remove_required_rule($mform, $name);
        }
    }

    /**
     * Remove the required rule from one element, keeping its other rules.
     *
     * Core does the same in grade/classes/form/add_category.php, on the public QuickForm members.
     *
     * @param MoodleQuickForm $mform The form.
     * @param string $name Element name.
     * @return void
     */
    protected static function remove_required_rule(MoodleQuickForm $mform, string $name): void {
        if (isset($mform->_rules[$name])) {
            $mform->_rules[$name] = array_values(array_filter(
                $mform->_rules[$name],
                static fn(array $rule): bool => $rule['type'] !== 'required'
            ));
            if (!$mform->_rules[$name]) {
                unset($mform->_rules[$name]);
            }
        }
        $mform->_required = array_values(array_diff($mform->_required, [$name]));
    }

    /**
     * Whether a submitted answer counts as given, for a required question.
     *
     * The same meaning core's required rule has per type: text empty after trimming, select
     * position 0, date unset, number empty, checkbox unchecked.
     *
     * @param field_controller $field The question.
     * @param mixed $raw The submitted value.
     * @return bool
     */
    public static function is_answered(field_controller $field, $raw): bool {
        if (is_array($raw) || is_object($raw) || $raw === null) {
            return false;
        }

        switch ($field->get('type')) {
            case 'select':
                return (int) $raw > 0;
            case 'checkbox':
            case 'date':
                return !empty($raw);
            default:
                return trim((string) $raw) !== '';
        }
    }

    /**
     * Save the condition of a question, replacing any it had.
     *
     * Checked here with problem() against the instance's own questions, whatever the caller did:
     * the form and the restore check first to explain a refusal, but this is the write.
     *
     * @param int $enrolid Enrol instance id.
     * @param int $fieldid The controlled field.
     * @param int $controllerid The controlling field.
     * @param string $operator One of the OPERATOR_ constants.
     * @param int[] $positions Option positions, for OPERATOR_IN.
     * @return void
     * @throws \invalid_parameter_exception When the condition is not one this instance can hold.
     */
    public static function save(int $enrolid, int $fieldid, int $controllerid, string $operator, array $positions): void {
        global $DB;

        $instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'apply'], '*', MUST_EXIST);
        $problem = self::problem(answers::fields($instance), $fieldid, $controllerid, $operator, $positions);
        if ($problem !== '') {
            throw new \invalid_parameter_exception(self::problem_message($problem));
        }

        $value = $operator === self::OPERATOR_IN ? implode(',', self::parse_positions(implode(',', $positions))) : '';
        $record = [
            'enrolid' => $enrolid,
            'fieldid' => $fieldid,
            'dependsonfieldid' => $controllerid,
            'operator' => $operator,
            'value' => $value,
            'timemodified' => time(),
        ];

        $id = $DB->get_field(self::TABLE, 'id', ['enrolid' => $enrolid, 'fieldid' => $fieldid]);
        if ($id) {
            $DB->update_record(self::TABLE, (object) (['id' => $id] + $record));
        } else {
            $DB->insert_record(self::TABLE, (object) $record);
        }
    }

    /**
     * Remove the condition of a question: it is then always shown.
     *
     * @param int $enrolid Enrol instance id.
     * @param int $fieldid The controlled field.
     * @return void
     */
    public static function delete(int $enrolid, int $fieldid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['enrolid' => $enrolid, 'fieldid' => $fieldid]);
    }

    /**
     * Remove every condition naming a field, as the controlled or the controlling one.
     *
     * Called when core deletes the field; field ids are site-wide, so this touches only rows
     * that named the deleted field.
     *
     * @param int $fieldid Row id in {customfield_field}.
     * @return void
     */
    public static function delete_for_field(int $fieldid): void {
        global $DB;

        $DB->delete_records_select(
            self::TABLE,
            'fieldid = :fieldid OR dependsonfieldid = :dependsonfieldid',
            ['fieldid' => $fieldid, 'dependsonfieldid' => $fieldid]
        );
    }

    /**
     * One line saying when a question is shown, in the plain spelling.
     *
     * @param int $fieldid The question.
     * @param stdClass[] $stored What stored() returns.
     * @param stdClass[] $effective What effective() returns.
     * @param field_controller[] $fields What answers::fields() returns.
     * @return string
     */
    public static function summary(int $fieldid, array $stored, array $effective, array $fields): string {
        if (!isset($stored[$fieldid])) {
            return get_string('conditionalways', 'enrol_apply');
        }
        if (!isset($effective[$fieldid])) {
            return get_string('conditionstale', 'enrol_apply');
        }

        $condition = $effective[$fieldid];
        $controller = $fields[$condition->dependsonfieldid];
        $name = $controller->get_formatted_name(false);
        if ($condition->operator === self::OPERATOR_IN) {
            $options = array_intersect_key(self::options($controller), array_flip($condition->positions));
            return get_string('conditionsummaryin', 'enrol_apply', (object) [
                'field' => $name,
                'options' => implode(', ', $options),
            ]);
        }

        return match ($condition->operator) {
            self::OPERATOR_CHECKED => get_string('conditionsummarychecked', 'enrol_apply', $name),
            default => get_string('conditionsummarynotchecked', 'enrol_apply', $name),
        };
    }
}
