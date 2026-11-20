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
 * The instance form's question "How is an applicant's group decided?" (C-D6), and its storage.
 *
 * Three answers over two stored settings that stay as they were: customchar1 (the group key mode,
 * {@see groupkey}) and the groupchoice option ({@see instanceoptions}). No value of either
 * changes meaning and no upgrade rewrites them; the form reads the answer from them and
 * {@see storage()} maps it back on save, so the two can no longer describe a combination nobody
 * meant. In particular a choice never comes with a key-only mode (C-D5): the choice's own key
 * question offers "no key" and "a valid key enrols at once" alone.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class groupquestion {
    /** @var string A decider chooses the groups on approval: no key, no choice. */
    public const DECIDER = 'decider';

    /** @var string The applicant chooses from the method's offer. */
    public const CHOICE = 'choice';

    /** @var string The applicant types a group enrolment key. */
    public const KEY = 'key';

    /** @var array The answers, in the order the form offers them. */
    public const ANSWERS = [self::DECIDER, self::CHOICE, self::KEY];

    /** @var array The key modes the key answer offers; "off" is the decider's answer. */
    public const KEY_MODES = [groupkey::MODE_ANY, groupkey::MODE_REQUIRED, groupkey::MODE_REVIEW];

    /** @var array The key modes a choice may come with (C-D5). */
    public const CHOICE_KEY_MODES = [groupkey::MODE_OFF, groupkey::MODE_ANY];

    /**
     * The answer a saved method gives, read with the strict readers.
     *
     * A key-only mode wins over a stored choice, as {@see groupchoice::mode()} reads it: there the
     * key decides the group and the applicant is offered no list. A choice wins over "a valid key
     * enrols at once", which comes with it. Anything else with a key is the key answer.
     *
     * @param stdClass $instance The {enrol} record, or the defaults for a new method.
     * @param stdClass $options The method's options, from {@see instanceoptions::get()}.
     * @return string One of {@see ANSWERS}.
     */
    public static function answer(stdClass $instance, stdClass $options): string {
        if (groupkey::mandatory($instance)) {
            return self::KEY;
        }
        if ((int) $options->groupchoice !== instanceoptions::CHOICE_OFF) {
            return self::CHOICE;
        }
        if (groupkey::enabled($instance)) {
            return self::KEY;
        }

        return self::DECIDER;
    }

    /**
     * The two stored settings an answer of the form maps to.
     *
     * Each answer reads only its own elements, so a value left in an element the answer hides
     * changes nothing. A value outside its element's vocabulary, which validation refuses before
     * this runs, maps to off (no key, no choice) rather than to anything that admits.
     *
     * @param stdClass $data The form's data: groupdecided, and per answer customchar1 (key),
     *     choicekey and groupchoicerequired (choice).
     * @return stdClass customchar1 (a groupkey mode) and groupchoice (an instanceoptions CHOICE_ value).
     */
    public static function storage(stdClass $data): stdClass {
        $answer = (string) ($data->groupdecided ?? '');

        if ($answer === self::CHOICE) {
            $key = (string) ($data->choicekey ?? '');
            $required = (int) ($data->groupchoicerequired ?? 0) === instanceoptions::CHOICE_REQUIRED;

            return (object) [
                'customchar1' => $key === groupkey::MODE_ANY ? groupkey::MODE_ANY : groupkey::MODE_OFF,
                'groupchoice' => $required ? instanceoptions::CHOICE_REQUIRED : instanceoptions::CHOICE_OPTIONAL,
            ];
        }

        if ($answer === self::KEY) {
            $mode = (string) ($data->customchar1 ?? '');

            return (object) [
                'customchar1' => in_array($mode, self::KEY_MODES, true) ? $mode : groupkey::MODE_OFF,
                'groupchoice' => instanceoptions::CHOICE_OFF,
            ];
        }

        return (object) [
            'customchar1' => groupkey::MODE_OFF,
            'groupchoice' => instanceoptions::CHOICE_OFF,
        ];
    }

    /**
     * Save the options and the offer of a form's answer, once the method exists.
     *
     * The choice (from {@see storage()}), "Who decides", the group seats and the hidden card go to
     * {@see instanceoptions::set()}; the offer to {@see groupoffer::set()}, which refuses a group of
     * another course. The offer is replaced only when the applicant chooses: another answer leaves
     * it as it was, for when the choice is switched on again, and nothing reads it meanwhile
     * ({@see groupchoice::mode()} is off). Before the upgrade that creates their tables
     * ({@see instanceoptions::schema_ready()}) neither can be written, and the method keeps
     * behaving as the plugin did before.
     *
     * customchar1, the other half of the answer, is the instance's own column: the caller writes it
     * from {@see storage()} through update_instance() or add_instance(), before this.
     *
     * @param int $enrolid The method, already saved.
     * @param stdClass $data The form's data.
     * @return void
     */
    public static function save(int $enrolid, stdClass $data): void {
        global $DB;

        if (!instanceoptions::schema_ready()) {
            return;
        }

        $setup = self::storage($data);
        $options = [
            'groupchoice' => $setup->groupchoice,
            'deciders' => (int) ($data->deciders ?? instanceoptions::DECIDERS_GROUP) === instanceoptions::DECIDERS_ALLGROUPS
                ? instanceoptions::DECIDERS_ALLGROUPS
                : instanceoptions::DECIDERS_GROUP,
            'hidecard' => empty($data->hidecard) ? 0 : 1,
        ];
        // Seats are asked only under the choice; another answer keeps what was set for it.
        if ($setup->groupchoice !== instanceoptions::CHOICE_OFF) {
            $options['groupseats'] = empty($data->groupseats) ? 0 : 1;
        }
        instanceoptions::set($enrolid, $options);

        if ($setup->groupchoice !== instanceoptions::CHOICE_OFF) {
            $courseid = (int) $DB->get_field('enrol', 'courseid', ['id' => $enrolid], MUST_EXIST);
            groupoffer::set($enrolid, self::offer($courseid, $data));
        }
    }

    /**
     * The groups the method offers once a form's answer is saved: the picked ones and a grouping's.
     *
     * The grouping's groups are copied in once (C-D3). Read through groups_get_all_groups() on the
     * method's course, so a grouping of another course adds nothing; the ids picked are checked by
     * validation and again by {@see groupoffer::set()}.
     *
     * @param int $courseid The method's course.
     * @param stdClass $data The form's data: groupoffer and groupoffergrouping.
     * @return int[] Group ids, ascending, without duplicates.
     */
    public static function offer(int $courseid, stdClass $data): array {
        $ids = array_map('intval', (array) ($data->groupoffer ?? []));

        $grouping = (int) ($data->groupoffergrouping ?? 0);
        if ($grouping > 0) {
            $ids = array_merge($ids, array_map('intval', array_keys(groups_get_all_groups($courseid, 0, $grouping))));
        }

        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        sort($ids);

        return $ids;
    }
}
