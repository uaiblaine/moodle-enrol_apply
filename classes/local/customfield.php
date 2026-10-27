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

namespace enrol_apply\local;

use stdClass;

/**
 * A custom profile field, drawn, read and written through its own type's API.
 *
 * A field type is free to store a shape of its own and to draw several elements for it, so
 * nothing about a custom field may be assumed from the {user_info_data} column. Everything that
 * depends on the type goes through the type: edit_field_add() draws it, edit_load_user_data()
 * fills it, edit_save_data_preprocess() turns a submission into the value it would store,
 * display_data() says what a stored value means, and edit_save_data() writes it.
 * profilefield_brasilufmunicipio is the case that proves it: it stores JSON and draws a hidden
 * input beside two selects, and a text box over its column showed the applicant the JSON and
 * wrote back whatever they typed over it.
 *
 * A stored value travels as an object carrying data and dataformat, the two columns
 * edit_save_data() writes, so a textarea keeps its format between the submission and the write.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customfield {
    /**
     * Types whose stored value is already the text a reader should see.
     *
     * display() hands their value back as it is rather than through display_data(). The text
     * type's display_data() runs format_string(), whose strip_tags() deletes everything after a
     * bare "<", and every sink of this plugin escapes for itself; see
     * {@see fields::submitted_values()}.
     *
     * @var array
     */
    public const VERBATIM_TYPES = ['text', 'social'];

    /**
     * Whether the site still has the code of a field type.
     *
     * Uninstalling a profile field plugin can leave rows of its type in {user_info_field}, and
     * profile_get_user_field() would then require a file that no longer exists.
     *
     * @param string $datatype The type, as {user_info_field}.datatype names it.
     * @return bool True when the type can be instantiated.
     */
    public static function type_available(string $datatype): bool {
        return array_key_exists($datatype, \core_component::get_plugin_list('profilefield'));
    }

    /**
     * The field as its type sees it, carrying one user's stored value.
     *
     * The required flag is applied to the definition before the object is built, because a type
     * may read it in its constructor: profile_field_menu adds its empty "Choose..." option there,
     * and only for a required field.
     *
     * @param int $fieldid Row id in {user_info_field}.
     * @param int $userid User whose stored value the object carries, or 0 for none.
     * @param bool|null $required Whether the field is required here, replacing the site's own
     *        flag; null keeps the site's.
     * @return \profile_field_base|null The field, or null when it or its type no longer exists.
     */
    public static function field(int $fieldid, int $userid, ?bool $required = null): ?\profile_field_base {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        $record = $DB->get_record('user_info_field', ['id' => $fieldid]);
        if (!$record || !self::type_available((string) $record->datatype)) {
            return null;
        }
        if ($required !== null) {
            $record->required = (int) $required;
        }

        $record->hasuserdata = 0;
        if ($userid > 0) {
            $stored = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $fieldid], 'data, dataformat');
            if ($stored) {
                $record->hasuserdata = 1;
                $record->data = $stored->data;
                $record->dataformat = $stored->dataformat;
            }
        }

        return profile_get_user_field($record->datatype, $fieldid, $userid, $record);
    }

    /**
     * Add the field to a form, as core's profile page adds it.
     *
     * core's profile_field_base::edit_field() without its is_editable() test, which asks about
     * capabilities and visibility that fields::classify() has already decided for this form.
     * The type's own required rule is attached, so a type that draws several elements marks the
     * ones it means; field() set the flag it reads.
     *
     * @param \MoodleQuickForm $mform Form to add to.
     * @param \profile_field_base $field The field, from field().
     * @return void
     */
    public static function add_to_form(\MoodleQuickForm $mform, \profile_field_base $field): void {
        $field->edit_field_add($mform);
        $field->edit_field_set_default($mform);
        $field->edit_field_set_required($mform);
    }

    /**
     * The form values that show the user's stored value, keyed by element name.
     *
     * @param \profile_field_base $field The field, from field() with the user's id.
     * @return array Element name => value.
     */
    public static function form_values(\profile_field_base $field): array {
        $values = new stdClass();
        $field->edit_load_user_data($values);

        return (array) $values;
    }

    /**
     * The submitted values that belong to this field, keyed by element name.
     *
     * The field's own element and every element named after it, which is how a type that draws
     * several elements names the others (profile_field_<shortname>_uf). A name belongs to the
     * field whose element name is the LONGEST that it starts with: fields cidade and
     * cidade_natal both prefix profile_field_cidade_natal_uf, and it is cidade_natal's.
     *
     * @param \profile_field_base $field The field.
     * @param array $submitted Submitted values keyed by element name.
     * @return array The field's share of them.
     */
    public static function own_values(\profile_field_base $field, array $submitted): array {
        global $DB;

        // The other fields whose element names extend this one's, and so could claim a name first.
        $longer = [];
        foreach ($DB->get_fieldset_select('user_info_field', 'shortname', 'id <> ?', [$field->fieldid]) as $shortname) {
            $inputname = 'profile_field_' . $shortname;
            if (str_starts_with($inputname, $field->inputname . '_')) {
                $longer[] = $inputname;
            }
        }

        $own = [];
        foreach ($submitted as $name => $value) {
            $name = (string) $name;
            if ($name !== $field->inputname && !str_starts_with($name, $field->inputname . '_')) {
                continue;
            }
            foreach ($longer as $other) {
                if ($name === $other || str_starts_with($name, $other . '_')) {
                    continue 2;
                }
            }
            $own[$name] = $value;
        }

        return $own;
    }

    /**
     * The value the field would store for a submission, without storing it.
     *
     * @param \profile_field_base $field The field.
     * @param array $submitted Submitted values keyed by element name.
     * @return stdClass|null Object carrying data and dataformat, or null when the submission
     *         carries nothing the type would store.
     */
    public static function stored_value(\profile_field_base $field, array $submitted): ?stdClass {
        if (!array_key_exists($field->inputname, $submitted)) {
            return null;
        }

        $record = new stdClass();
        $data = self::with_request(
            self::own_values($field, $submitted),
            static fn() => $field->edit_save_data_preprocess($submitted[$field->inputname], $record)
        );
        if ($data === null || !is_scalar($data)) {
            return null;
        }

        return (object) [
            'data' => (string) $data,
            'dataformat' => (int) ($record->dataformat ?? FORMAT_MOODLE),
        ];
    }

    /**
     * The value the user currently holds.
     *
     * Read from hasuserdata rather than from the data property, which set_field() fills with the
     * field's default when the user has nothing stored.
     *
     * @param \profile_field_base $field The field, from field() with the user's id.
     * @return stdClass|null Object carrying data and dataformat, or null when nothing is stored.
     */
    public static function current(\profile_field_base $field): ?stdClass {
        if (empty($field->field->hasuserdata)) {
            return null;
        }

        return (object) ['data' => (string) $field->data, 'dataformat' => (int) $field->dataformat];
    }

    /**
     * What a stored value says, as plain text: the plain spelling, for a sink that escapes.
     *
     * Empty when the type itself calls the value empty, or when it displays as nothing at all.
     * The second test is what catches a type that stores a placeholder for "nothing chosen":
     * profilefield_brasilufmunicipio has stored its hidden input's value, the string "1", when
     * neither select was used, and displays it as nothing.
     *
     * @param \profile_field_base $field The field.
     * @param stdClass|null $value Object carrying data and dataformat, or null for none.
     * @return string The text, or an empty string when there is nothing to show.
     */
    public static function display(\profile_field_base $field, ?stdClass $value): string {
        if ($value === null) {
            return '';
        }

        // The value goes in before construction, where profile_field_menu maps it to its option.
        $record = clone $field->field;
        $record->hasuserdata = 1;
        $record->data = $value->data;
        $record->dataformat = $value->dataformat;
        $holder = profile_get_user_field($record->datatype, (int) $record->id, (int) $field->userid, $record);
        // The constructor sets it only for a user; the snapshot reads with none.
        $holder->set_user_data($value->data, $value->dataformat);
        if ($holder->is_empty()) {
            return '';
        }

        if (in_array($record->datatype, self::VERBATIM_TYPES, true)) {
            return trim($value->data);
        }

        return trim(html_to_text($holder->display_data(), 0, false));
    }

    /**
     * Write a submission to the user's profile through the type's own save.
     *
     * The caller decides whether the write is allowed; edit_save_data() checks nothing.
     *
     * @param \profile_field_base $field The field, from field() with the user's id.
     * @param int $userid User to write to.
     * @param array $submitted Submitted values keyed by element name.
     * @return void
     */
    public static function save(\profile_field_base $field, int $userid, array $submitted): void {
        $own = self::own_values($field, $submitted);
        if (!array_key_exists($field->inputname, $own)) {
            return;
        }

        $usernew = (object) ['id' => $userid, $field->inputname => $own[$field->inputname]];
        self::with_request($own, static fn() => $field->edit_save_data($usernew));
    }

    /**
     * Run a call with a field's submitted values in the request, then put the request back.
     *
     * A type is written against core's profile page, where every element it draws arrives in
     * the request, and edit_save_data_preprocess() is handed only the value of its own element:
     * a type that draws more than one has to read the others with optional_param(), as
     * profilefield_brasilufmunicipio does. Neither path here posts them. The modal submits
     * through core_form_dynamic_form, whose request carries one url-encoded formdata string,
     * and the profile write happens on profile.php, which posts nothing but the instance and
     * the sesskey. So the values are put where the type looks, replacing whatever the request
     * carried under the same names: on profile.php those are values the applicant never saw.
     * A name the type reads that is not among them still comes from the real request, so on
     * profile.php the applicant can choose it - for their own field, which the same write
     * already requires them to be allowed to edit on their own profile page.
     *
     * @param array $values Element name => value.
     * @param callable $call What to run.
     * @return mixed Whatever the call returned.
     */
    protected static function with_request(array $values, callable $call) {
        $saved = [];
        foreach ($values as $name => $value) {
            $saved[$name] = array_key_exists($name, $_POST) ? [$_POST[$name]] : [];
            $_POST[$name] = $value;
        }

        try {
            return $call();
        } finally {
            foreach ($saved as $name => $previous) {
                if ($previous) {
                    $_POST[$name] = $previous[0];
                } else {
                    unset($_POST[$name]);
                }
            }
        }
    }
}
