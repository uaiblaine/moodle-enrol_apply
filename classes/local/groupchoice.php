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

use context_course;
use stdClass;

/**
 * The group an applicant chooses when applying (docs/design/applicant-group-choice.md, G4).
 *
 * One reader for the form that offers the groups and the write door that records the choice, so
 * the two cannot disagree on what may be chosen. Everything is read live from the database on
 * every call: the method's option, its offer, the applicant's view of each group and the seats.
 * The door calls {@see resolve()} inside submit_application()'s lock and never trusts what the
 * form concluded, because the offer, a group's visibility or its seats can change between the
 * two.
 *
 * Only in a mode where no key decides the group: in the two key-only modes the key names the
 * group, and a chooser could only contradict it (C-D5), so the mode reads as off there.
 *
 * A pending application takes no seat, so a choice reserves nothing; it is refused only when
 * the group is already full at submission (C-D8), and approval checks the seats again.
 *
 * Methods call each other through static:: so a test can stand in for a site without seats by
 * overriding {@see seats_for()} in a subclass.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class groupchoice {
    /**
     * Whether the applicant chooses a group on this method, and must.
     *
     * @param stdClass $instance The {enrol} record.
     * @return int One of instanceoptions' CHOICE_ constants; CHOICE_OFF in both key-only modes.
     */
    public static function mode(stdClass $instance): int {
        if (groupkey::mandatory($instance)) {
            return instanceoptions::CHOICE_OFF;
        }

        return (int) instanceoptions::get((int) $instance->id)->groupchoice;
    }

    /**
     * The groups this applicant may choose, in natural order, with their places.
     *
     * The method's offer ({@see groupoffer::groups()}, joined on the method's course), less every
     * group the applicant may not be told about ({@see groupvisibility::visible_to()}, C-D7). A
     * full group stays in the list, marked: the applicant learns why it cannot be chosen (C-D8).
     * Seats count only where the method's groupseats option is on (C-D26); without
     * local_groupdist no group declares any.
     *
     * @param stdClass $instance The {enrol} record.
     * @param int $userid The applicant.
     * @return array Group id => object with int id, int courseid, string name (raw), int
     *     visibility, bool hasseats, ?int places (left, never negative; null without seats) and
     *     bool full; natural order by name.
     */
    public static function offered(stdClass $instance, int $userid): array {
        $groups = array_filter(
            groupoffer::groups((int) $instance->id),
            static fn(stdClass $group): bool => groupvisibility::visible_to($group, $userid)
        );
        if (!$groups) {
            return [];
        }
        \core_collator::asort_objects_by_property($groups, 'name', \core_collator::SORT_NATURAL);

        $useseats = (int) instanceoptions::get((int) $instance->id)->groupseats === 1;
        $seats = $useseats ? static::seats_for(array_keys($groups)) : [];

        $offered = [];
        foreach ($groups as $group) {
            $seat = $seats[(int) $group->id] ?? null;
            $hasseats = $seat !== null && $seat->seats !== null;
            $left = $hasseats ? (int) $seat->seats - (int) $seat->members : null;
            $offered[(int) $group->id] = (object) [
                'id' => (int) $group->id,
                'courseid' => (int) $group->courseid,
                'name' => (string) $group->name,
                'visibility' => (int) $group->visibility,
                'hasseats' => $hasseats,
                'places' => $hasseats ? max(0, $left) : null,
                'full' => $hasseats && $left <= 0,
            ];
        }

        return $offered;
    }

    /**
     * What the submission names as the chosen group, as the write door reads it.
     *
     * The form hands an int through its typed data (PARAM_INT); a caller other than the form may
     * hand a string of digits. Anything else is not a group id, and is refused by
     * {@see resolve()} rather than read as "no group".
     *
     * @param stdClass|null $data Submitted application data.
     * @return int|null The group id, 0 for none chosen, or null when the value is not an id.
     */
    public static function posted(?stdClass $data): ?int {
        $value = $data->chosengroup ?? null;
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (is_string($value) && preg_match('/^\d{1,10}$/', $value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Judge a chosen group for this applicant now, and say what the application records.
     *
     * With the chooser off the posted value means nothing and is not judged: the record names no
     * group. Otherwise the group must be offered to this applicant (the offer, the method's
     * course, the applicant's visibility) and have a place where seats count; "none" is accepted
     * only where the choice is optional. A refusal is a sentence for the applicant, rendered raw
     * by a form error and by a core notification, so it names no group.
     *
     * @param stdClass $instance The {enrol} record.
     * @param int $userid The applicant.
     * @param int|null $groupid What {@see posted()} read; null for a value that is not an id.
     * @return stdClass With int 'groupid' (what to record, 0 for none) and ?string 'refusal'.
     */
    public static function resolve(stdClass $instance, int $userid, ?int $groupid): stdClass {
        $mode = static::mode($instance);
        if ($mode === instanceoptions::CHOICE_OFF) {
            return (object) ['groupid' => 0, 'refusal' => null];
        }
        if ($groupid === 0) {
            return (object) [
                'groupid' => 0,
                'refusal' => $mode === instanceoptions::CHOICE_REQUIRED ? get_string('chosengrouprequired', 'enrol_apply') : null,
            ];
        }

        $offered = $groupid === null ? [] : static::offered($instance, $userid);
        if (!isset($offered[$groupid])) {
            return (object) ['groupid' => 0, 'refusal' => get_string('chosengroupunavailable', 'enrol_apply')];
        }
        if ($offered[$groupid]->full) {
            return (object) ['groupid' => 0, 'refusal' => get_string('chosengroupnoplace', 'enrol_apply')];
        }

        return (object) ['groupid' => $groupid, 'refusal' => null];
    }

    /**
     * The text of an offered group as an option of the plain select.
     *
     * The escaped spelling: core's element-select.mustache renders an option's text through a
     * triple stash. The places follow the name in words (C-D8), never in colour alone.
     *
     * @param stdClass $group As {@see offered()} returns one.
     * @param context_course $context The method's course context.
     * @return string The option text, escaped for HTML.
     */
    public static function option_text(stdClass $group, context_course $context): string {
        $name = format_string($group->name, true, ['context' => $context]);
        if (!$group->hasseats) {
            return $name;
        }

        return get_string('chosengroupoption', 'enrol_apply', (object) [
            'name' => $name,
            'status' => self::places_text($group),
        ]);
    }

    /**
     * The places of an offered group in words: "7 places left", "1 place left" or "Full".
     *
     * @param stdClass $group As {@see offered()} returns one, with seats known.
     * @return string The words.
     */
    public static function places_text(stdClass $group): string {
        if ($group->full) {
            return get_string('chosengroupfull', 'enrol_apply');
        }

        return $group->places === 1
            ? get_string('chosengroupplace', 'enrol_apply')
            : get_string('chosengroupplaces', 'enrol_apply', $group->places);
    }

    /**
     * The seats of these groups, read live.
     *
     * The seam a test overrides to stand in for a site without local_groupdist.
     *
     * @param array $groupids Group ids.
     * @return array As {@see groupseats::for_groups()}.
     */
    protected static function seats_for(array $groupids): array {
        return groupseats::for_groups($groupids);
    }
}
