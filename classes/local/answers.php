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
 * The questions an instance asks at application time, and the answers frozen from them.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use core_customfield\data_controller;
use core_customfield\field_controller;
use enrol_apply\customfield\application_handler;
use MoodleQuickForm;
use stdClass;

/**
 * The questions an instance asks at application time, and the answers frozen from them.
 *
 * Core's custom field API supplies the definitions, the form elements and their validation;
 * nothing is written to customfield_data. What the applicant answered is frozen into the
 * submission's snapshot beside the profile fields, under keys of its own, with the label and a
 * PLAIN display value as they were at submission (docs/design/custom-application-fields.md, D7
 * and D11). Every reader of an application reads that snapshot, so none of them needs to know
 * these fields exist.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answers {
    /** @var string Snapshot key prefix of an application answer, beside fields::SOURCE_STANDARD and SOURCE_CUSTOM. */
    public const SOURCE_ANSWER = 'a_';

    /**
     * The snapshot key of an answer.
     *
     * Keyed by the field id, never the shortname, which a teacher can edit. The id is site-local,
     * so after a restore on another site it names nothing; the frozen label is what is shown.
     *
     * @param int $fieldid Row id in {customfield_field}.
     * @return string The key, for example a_12.
     */
    public static function key(int $fieldid): string {
        return self::SOURCE_ANSWER . $fieldid;
    }

    /**
     * Whether a snapshot key names an application answer.
     *
     * @param string $key Snapshot key.
     * @return bool
     */
    public static function is_answer(string $key): bool {
        return str_starts_with($key, self::SOURCE_ANSWER);
    }

    /**
     * The fields the instance asks, local and enabled shared ones, in form order.
     *
     * Only types the handler allows: a shared category can hold a textarea or a picture, which
     * this plugin cannot freeze, and those are left off the application form.
     *
     * @param stdClass $instance Enrol instance.
     * @return field_controller[] Keyed by field id.
     */
    public static function fields(stdClass $instance): array {
        $handler = application_handler::create((int) $instance->id);
        $allowed = $handler->get_available_field_types();

        return array_filter(
            $handler->get_editable_fields(0),
            static fn(field_controller $field): bool => array_key_exists($field->get('type'), $allowed)
        );
    }

    /**
     * A data controller with no row behind it, for a field of this instance.
     *
     * @param field_controller $field The field.
     * @return data_controller
     */
    protected static function data(field_controller $field): data_controller {
        return data_controller::create(0, null, $field);
    }

    /**
     * Add the instance's questions to a form, one header per category.
     *
     * @param MoodleQuickForm $mform The application form.
     * @param stdClass $instance Enrol instance.
     * @return void
     */
    public static function add_to_form(MoodleQuickForm $mform, stdClass $instance): void {
        $lastcategoryid = null;
        foreach (self::fields($instance) as $field) {
            $category = $field->get_category();
            if ($category->get('id') != $lastcategoryid) {
                $mform->addElement('header', 'category_' . $category->get('id'), $category->get_formatted_name());
                $mform->setExpanded('category_' . $category->get('id'), true);
                $lastcategoryid = $category->get('id');
            }

            self::data($field)->instance_form_definition($mform);

            $description = (string) $field->get('description');
            if ($description !== '') {
                $context = $field->get_handler()->get_configuration_context();
                $description = file_rewrite_pluginfile_urls(
                    $description,
                    'pluginfile.php',
                    $context->id,
                    'core_customfield',
                    'description',
                    $field->get('id')
                );
                $mform->addElement(
                    'static',
                    'customfield_' . $field->get('shortname') . '_static',
                    '',
                    format_text($description, $field->get('descriptionformat'), ['context' => $context])
                );
            }
        }
    }

    /**
     * Put each question's configured default on the form data.
     *
     * @param stdClass $instance Enrol instance.
     * @param stdClass $data Form data, changed in place.
     * @return void
     */
    public static function set_defaults(stdClass $instance, stdClass $data): void {
        foreach (self::fields($instance) as $field) {
            // A fresh object without an id: given the form's own id, core would look the answers up by it.
            $defaults = new stdClass();
            self::data($field)->instance_form_before_set_data($defaults);
            foreach ((array) $defaults as $name => $value) {
                $data->$name = $value;
            }
        }
    }

    /**
     * Validate the answers with each type's own rules.
     *
     * Core's uniqueness check is dropped: it compares against customfield_data, which this
     * plugin never writes, and for a field of a shared category it would compare against other
     * components' data and refuse an applicant over a value they never saw.
     *
     * @param stdClass $instance Enrol instance.
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Element name => error message.
     */
    public static function validate(stdClass $instance, array $data, array $files): array {
        $unique = get_string('erroruniquevalues', 'core_customfield');
        $errors = [];
        foreach (self::fields($instance) as $field) {
            $data[self::data($field)->get_form_element_name()] ??= null;
            foreach (self::data($field)->instance_form_validation($data, $files) as $name => $message) {
                if ($message !== $unique) {
                    $errors[$name] = $message;
                }
            }
        }

        return $errors;
    }

    /**
     * The answers as snapshot entries: key, frozen label and plain display value.
     *
     * Plain, never escaped, like fields::submitted_values(): every reader escapes on output. An
     * empty answer is left out, as the profile fields are, except a checkbox, whose "No" is an
     * answer.
     *
     * @param stdClass $instance Enrol instance.
     * @param stdClass $data Submitted form data.
     * @return array List of arrays carrying 'key', 'label' and 'value' string entries.
     */
    public static function submitted_values(stdClass $instance, stdClass $data): array {
        $values = [];
        foreach (self::fields($instance) as $field) {
            $name = self::data($field)->get_form_element_name();
            $value = self::display_value($field, $data->$name ?? null);
            if ($value === '') {
                continue;
            }
            $values[] = [
                'key' => self::key((int) $field->get('id')),
                'label' => $field->get_formatted_name(false),
                'value' => $value,
            ];
        }

        return $values;
    }

    /**
     * The plain display value of one submitted answer, or an empty string for no answer.
     *
     * Core's export_value() is not used for the types it escapes (it runs format_string() with
     * escaping on), because the snapshot holds plain text; see submission::snapshot().
     *
     * @param field_controller $field The field.
     * @param mixed $raw The submitted value.
     * @return string
     */
    public static function display_value(field_controller $field, $raw): string {
        if (is_array($raw) || is_object($raw)) {
            return '';
        }
        $context = $field->get_handler()->get_configuration_context();
        $plain = static fn(string $text): string => format_string($text, true, ['context' => $context, 'escape' => false]);

        switch ($field->get('type')) {
            case 'checkbox':
                return $raw ? get_string('yes') : get_string('no');

            case 'select':
                $options = preg_split(
                    "/\s*\n\s*/",
                    trim((string) $field->get_configdata_property('options')),
                    -1,
                    PREG_SPLIT_NO_EMPTY
                );
                // Core's select stores the 1-based position; 0 is "nothing chosen".
                $index = (int) $raw;
                return ($index >= 1 && isset($options[$index - 1])) ? $plain($options[$index - 1]) : '';

            case 'date':
                if (empty($raw)) {
                    return '';
                }
                $format = $field->get_configdata_property('includetime')
                    ? get_string('strftimedaydatetime', 'langconfig')
                    : get_string('strftimedate', 'langconfig');
                return userdate((int) $raw, $format);

            case 'number':
                if ($raw === null || trim((string) $raw) === '') {
                    return '';
                }
                $number = format_float((float) $raw, (int) $field->get_configdata_property('decimalplaces'));
                $display = (string) ($field->get_configdata_property('display') ?? '{value}');
                return $plain(str_replace('{value}', $number, $display === '' ? '{value}' : $display));

            case 'pattern':
                $text = trim((string) $raw);
                if ($text === '') {
                    return '';
                }
                // The type's own canonical spelling, so the snapshot matches what a saved field would hold.
                if (!method_exists($field, 'normalise_value') || !method_exists($field, 'format_for_display')) {
                    return $text;
                }
                return $field->format_for_display($field->normalise_value($text));

            default:
                return trim((string) $raw);
        }
    }
}
