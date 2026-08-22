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
 * The offer to save is only worth showing when there is something to save. The old form
 * pre-filled every field from the user record, so anybody who edited nothing posted their own
 * record straight back - an offer computed from "what was submitted" rather than "what
 * changed" would be shown to everybody and would write nothing for almost all of them.
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
     * @param \stdClass $instance Enrol instance the application was submitted to.
     * @param \stdClass $user The applicant, as they currently stand.
     * @param array $submitted Submitted values keyed by form element name.
     * @return array List of arrays with key, label, before and after.
     */
    public static function compute(\stdClass $instance, \stdClass $user, array $submitted): array {
        $changes = [];

        foreach (fields::resolve($instance)->keys() as $key) {
            if (fields::classify($key, $user) !== fields::STATE_EDITABLE) {
                continue;
            }
            $name = fields::form_element_name($key);
            if ($name === '' || !array_key_exists($name, $submitted)) {
                continue;
            }

            $after = trim((string) $submitted[$name]);
            if ($after === '') {
                continue;
            }
            $before = trim(fields::current_value($key, $user));
            if ($before === $after) {
                continue;
            }

            $changes[] = [
                'key' => $key,
                'label' => fields::label($key, false),
                'before' => $before,
                'after' => $after,
            ];
        }

        return $changes;
    }
}
