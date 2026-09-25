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
 * Which of the fields an instance asks for the applicant has not filled in.
 *
 * Shown when the site does not allow the plugin to write profiles: the applicant is told
 * exactly what is missing and sent to their own profile page to fill it in, and nothing is
 * written by this plugin at all.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completeness {
    /**
     * The fields the user currently holds no value for.
     *
     * Values are read one field at a time through {@see fields::current_value()} rather than
     * from profile_user_record(). That function defaults $onlyinuserobject to true, and
     * profile_field_textarea::is_user_object_data() returns false, so a textarea custom field
     * is absent from what it returns - it would read as permanently empty, and the applicant
     * would be told to fill in a field they had already filled in.
     *
     * @param \stdClass $instance Enrol instance.
     * @param \stdClass $user The applicant.
     * @return array List of arrays with key and label, in the instance's field order.
     */
    public static function missing(\stdClass $instance, \stdClass $user): array {
        $missing = [];

        foreach (fields::resolve($instance)->keys() as $key) {
            if (fields::classify($key, $user) === fields::STATE_ABSENT) {
                continue;
            }
            if (trim(fields::current_value($key, $user)) !== '') {
                continue;
            }
            $missing[] = [
                'key' => $key,
                // The plain spelling: the caller escapes it for its own sink.
                'label' => fields::label($key, false),
            ];
        }

        return $missing;
    }
}
