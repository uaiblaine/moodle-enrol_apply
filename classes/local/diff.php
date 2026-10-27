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

/**
 * What an application would change about the applicant's profile, if they asked it to.
 *
 * The offer to save is only worth showing when there is something to save. The form pre-fills
 * every field from the user record, so anybody who edits nothing posts their own record
 * straight back - an offer computed from "what was submitted" rather than "what changed" would
 * be shown to everybody and would write nothing for almost all of them.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diff {
    /**
     * The fields whose submitted value differs from what the user currently holds.
     *
     * Only editable fields are considered: a locked field is rendered read-only, so a
     * difference in one can only have come from a forged post, and an absent field was never
     * on screen at all. Blanking is never a change - see {@see profilewriter::write()} for
     * why an enrolment form may add to a profile but never empty it.
     *
     * A custom field is compared by what its type says the two values are, not by its stored
     * shape: two JSON documents naming the same city in a different key order are no change,
     * and a type's placeholder for "nothing chosen" is a blank, not a value to write.
     *
     * Each change carries, under 'values', the submitted elements that produce it, keyed by
     * element name. The write replays those through the field's own save, because a type that
     * draws several elements needs all of them; 'before' and 'after' are only what the
     * applicant is shown.
     *
     * @param \stdClass $instance Enrol instance the application was submitted to.
     * @param \stdClass $user The applicant, as they currently stand.
     * @param array $submitted Submitted values keyed by form element name.
     * @return array List of arrays with key, label, before, after and values.
     */
    public static function compute(\stdClass $instance, \stdClass $user, array $submitted): array {
        $changes = [];

        foreach (fields::resolve($instance)->keys() as $key) {
            if (fields::classify($key, $user) !== fields::STATE_EDITABLE) {
                continue;
            }

            $change = fields::is_standard($key)
                ? self::standard_change($key, $user, $submitted)
                : self::custom_change($key, $user, $submitted);
            if ($change !== null) {
                $changes[] = $change;
            }
        }

        return $changes;
    }

    /**
     * The change a submission makes to a standard {user} column, or null for none.
     *
     * @param string $key Field key.
     * @param \stdClass $user The applicant.
     * @param array $submitted Submitted values keyed by form element name.
     * @return array|null The change, in compute()'s shape.
     */
    protected static function standard_change(string $key, \stdClass $user, array $submitted): ?array {
        $name = fields::form_element_name($key);
        if ($name === '' || !array_key_exists($name, $submitted)) {
            return null;
        }

        $after = trim((string) $submitted[$name]);
        if ($after === '') {
            return null;
        }
        $before = trim(fields::current_value($key, $user));
        if ($before === $after) {
            return null;
        }

        return [
            'key' => $key,
            'label' => fields::label($key, false),
            'before' => $before,
            'after' => $after,
            'values' => [$name => $after],
        ];
    }

    /**
     * The change a submission makes to a custom profile field, or null for none.
     *
     * @param string $key Field key.
     * @param \stdClass $user The applicant.
     * @param array $submitted Submitted values keyed by form element name.
     * @return array|null The change, in compute()'s shape.
     */
    protected static function custom_change(string $key, \stdClass $user, array $submitted): ?array {
        $field = customfield::field((int) fields::key_target($key), (int) $user->id);
        if ($field === null || !array_key_exists($field->inputname, $submitted)) {
            return null;
        }

        $after = customfield::display($field, customfield::stored_value($field, $submitted));
        if ($after === '') {
            return null;
        }
        $before = customfield::display($field, customfield::current($field));
        if ($before === $after) {
            return null;
        }

        return [
            'key' => $key,
            'label' => fields::label($key, false),
            'before' => $before,
            'after' => $after,
            'values' => customfield::own_values($field, $submitted),
        ];
    }
}
