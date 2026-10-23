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
 * Group enrolment keys: whether an instance accepts them, and which group a key names.
 *
 * The key is core's own {groups}.enrolmentkey, the one enrol_self reads; this plugin stores
 * none. An instance opts in through customchar1, so keys left on groups from an old self
 * enrolment setup do not turn into a way past the review the moment this plugin is added.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class groupkey {
    /** @var string customchar1 value of an instance that accepts no key. */
    public const MODE_OFF = '';

    /** @var string customchar1 value of an instance that accepts the key of any group of its course. */
    public const MODE_ANY = 'any';

    /** @var array Every value customchar1 may hold. */
    public const MODES = [self::MODE_OFF, self::MODE_ANY];

    /**
     * Whether the instance accepts group keys.
     *
     * Strict on purpose: customchar1 travels verbatim through backup and restore, so anything
     * other than the one known value reads as off rather than as some other mode.
     *
     * @param stdClass $instance The {enrol} record.
     * @return bool True when a valid key admits without review.
     */
    public static function enabled(stdClass $instance): bool {
        return ($instance->customchar1 ?? self::MODE_OFF) === self::MODE_ANY;
    }

    /**
     * The group of the instance's course whose enrolment key is exactly this one.
     *
     * The comparison is exact, as enrol_self's: no trimming. The form element refuses a value
     * with wrapping whitespace on its own (err_wrappingwhitespace on 5.1 to 5.3), and trimming
     * here would make two keys that differ only by a space collide.
     *
     * Groups are scanned in id order and the first match wins. Core refuses a duplicate key
     * only in the group form; the CSV import and core_group_create_groups do not, so two
     * groups can share a key, and the lowest id is the one enrol_self would pick too.
     *
     * @param stdClass $instance The {enrol} record.
     * @param string $key The key the applicant typed.
     * @return int|null The group id, or null when the key matches nothing or the instance
     *     accepts no key.
     */
    public static function resolve(stdClass $instance, string $key): ?int {
        global $DB;

        /* The empty key is refused here and nowhere else: past this line hash_equals() can never
           pair it with a group that carries no key (null or ''), which would admit anybody. */
        if ($key === '' || !self::enabled($instance)) {
            return null;
        }

        $groups = $DB->get_records('groups', ['courseid' => $instance->courseid], 'id ASC', 'id, enrolmentkey');
        foreach ($groups as $group) {
            if (hash_equals((string) ($group->enrolmentkey ?? ''), $key)) {
                return (int) $group->id;
            }
        }

        return null;
    }

    /**
     * The groups of a course that carry a key, for the teacher configuring the instance.
     *
     * The keys themselves are never returned. Each group says whether its key is shadowed by
     * a lower id carrying the same one, and whether the key has wrapping whitespace, which
     * nobody can type into the application form.
     *
     * @param int $courseid Course id.
     * @return array Group id => object with 'id', 'name' (unformatted), 'shadowedby' (the
     *     id of the group that admits instead, or null) and 'untypable' (bool), in id order.
     */
    public static function summary(int $courseid): array {
        global $DB;

        $groups = $DB->get_records('groups', ['courseid' => $courseid], 'id ASC', 'id, name, enrolmentkey');

        $firstbykey = [];
        $result = [];
        foreach ($groups as $group) {
            $stored = (string) ($group->enrolmentkey ?? '');
            if ($stored === '') {
                continue;
            }
            $id = (int) $group->id;
            $result[$id] = (object) [
                'id' => $id,
                'name' => $group->name,
                'shadowedby' => $firstbykey[$stored] ?? null,
                'untypable' => $stored !== trim($stored),
            ];
            $firstbykey[$stored] = $firstbykey[$stored] ?? $id;
        }

        return $result;
    }
}
