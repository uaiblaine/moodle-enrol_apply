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
use context_system;
use moodle_url;

/**
 * The scenarios a new method's form can start from, offered as cards above its first section.
 *
 * A scenario is a set of values for the form's own elements, loaded as the form's initial data on
 * top of the site defaults ({@see values()}); the manager then changes what they like and saves.
 * Nothing here is stored: the key travels in the url (edit.php?preset=) and in a hidden element
 * of the form, so a redisplay after a validation error loads the same values, and no reader of a
 * saved method ever asks which scenario it came from.
 *
 * Every key is read strictly ({@see key()}): anything else is no scenario.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class preset {
    /** @var string A decider chooses the group on approval; everyone who decides is told. */
    public const REVIEW = 'review';

    /** @var string The applicant must choose a class; its moderators decide and are told. */
    public const CLASSES = 'classes';

    /** @var string The applicant types a group enrolment key; the group's deciders are told. */
    public const KEY = 'key';

    /** @var string The method recruits moderators: only those who see every group decide. */
    public const MODERATORS = 'moderators';

    /** @var array The keys, in the order the cards are shown. */
    public const KEYS = [self::REVIEW, self::CLASSES, self::KEY, self::MODERATORS];

    /**
     * The scenario a requested key names.
     *
     * @param string $requested The key as requested (edit.php's preset parameter).
     * @return string One of {@see KEYS}, or '' for anything else.
     */
    public static function key(string $requested): string {
        return match ($requested) {
            self::REVIEW => self::REVIEW,
            self::CLASSES => self::CLASSES,
            self::KEY => self::KEY,
            self::MODERATORS => self::MODERATORS,
            default => '',
        };
    }

    /**
     * The form values a scenario sets, keyed by the instance form's element names.
     *
     * Only what the scenario decides: every other element keeps the site default. The offered
     * groups are left for the manager to pick (the form refuses a required choice with none).
     *
     * @param string $key A key; an unknown one sets nothing.
     * @param int $courseid The course the method is added to.
     * @param array $offeredroles The role ids the form's "Role on approval" offers, in its order.
     * @return array Element name => value; 'notify' is the multi-select's array of option values.
     */
    public static function values(string $key, int $courseid, array $offeredroles): array {
        $question = groupquestion::class;
        $options = instanceoptions::class;
        $recipients = notifyrecipients::class;

        switch (self::key($key)) {
            case self::REVIEW:
                return [
                    'groupdecided' => $question::DECIDER,
                    'deciders' => $options::DECIDERS_GROUP,
                    'notify' => [$recipients::ALL],
                ];
            case self::CLASSES:
                return [
                    'groupdecided' => $question::CHOICE,
                    'groupchoicerequired' => $options::CHOICE_REQUIRED,
                    'groupseats' => 1,
                    'choicekey' => groupkey::MODE_OFF,
                    'deciders' => $options::DECIDERS_GROUP,
                    'notify' => [$recipients::GROUP],
                ];
            case self::KEY:
                return [
                    'groupdecided' => $question::KEY,
                    'customchar1' => groupkey::MODE_ANY,
                    'deciders' => $options::DECIDERS_GROUP,
                    'notify' => [$recipients::GROUP],
                ];
            case self::MODERATORS:
                /* No key beside the list: a valid key would enrol at once, granting a deciding role
                   nobody approved. */
                $values = [
                    'groupdecided' => $question::CHOICE,
                    'groupchoicerequired' => $options::CHOICE_REQUIRED,
                    'groupseats' => 0,
                    'choicekey' => groupkey::MODE_OFF,
                    'deciders' => $options::DECIDERS_ALLGROUPS,
                    'notify' => [$recipients::ALL],
                ];
                $roleid = self::moderator_role($courseid, $offeredroles);
                if ($roleid !== null) {
                    $values['roleid'] = $roleid;
                }
                return $values;
            default:
                return [];
        }
    }

    /**
     * The role "Moderator recruitment" grants on approval: one that decides applications.
     *
     * Among the offered roles that decide in the course ({@see decidingrole::roles()}), one of
     * archetype teacher (the non-editing teacher) when there is one, else the first offered.
     *
     * @param int $courseid The course.
     * @param array $offeredroles The role ids the form offers, in its order.
     * @return int|null The role id, or null when no offered role decides.
     */
    public static function moderator_role(int $courseid, array $offeredroles): ?int {
        global $DB;

        $deciding = decidingrole::roles($courseid);
        $candidates = array_values(array_filter(
            array_map('intval', $offeredroles),
            static fn(int $roleid): bool => in_array($roleid, $deciding, true)
        ));
        if (!$candidates) {
            return null;
        }

        $archetypes = $DB->get_records_list('role', 'id', $candidates, '', 'id, archetype');
        foreach ($candidates as $roleid) {
            if (isset($archetypes[$roleid]) && $archetypes[$roleid]->archetype === 'teacher') {
                return $roleid;
            }
        }

        return $candidates[0];
    }

    /**
     * The cards' template context ({@see \enrol_apply_edit_form}, template enrol_apply/instance_presets).
     *
     * Every text is in its plain spelling: the template escapes it.
     *
     * @param string $chosen The scenario in force, '' for none.
     * @param int $courseid The course the method is added to.
     * @param array $offeredroles The role ids the form's "Role on approval" offers, in its order.
     * @param string $modename The course's group mode, as core names it.
     * @param bool $separate Whether the course is in separate groups mode.
     * @param bool $canupdate Whether the reader may change the course settings (moodle/course:update).
     * @return array The context: heading, intro, haschosen, clearurl, clearlabel and cards.
     */
    public static function cards(
        string $chosen,
        int $courseid,
        array $offeredroles,
        string $modename,
        bool $separate,
        bool $canupdate
    ): array {
        $chosen = self::key($chosen);

        $cards = [];
        foreach (self::KEYS as $key) {
            $needsseparate = $key === self::CLASSES || $key === self::MODERATORS;
            $iscurrent = $key === $chosen;
            $note = $key === self::MODERATORS ? get_string('presetmoderatorsnote', 'enrol_apply') : '';
            $cards[] = [
                'key' => $key,
                'title' => self::title($key),
                'when' => self::when($key),
                'sets' => self::sets($key, $courseid, $offeredroles),
                'hasprerequisite' => $needsseparate,
                'modelabel' => get_string('coursegroupmode', 'enrol_apply'),
                'mode' => $modename,
                'needsseparate' => $needsseparate && !$separate,
                'needstext' => get_string('presetneedsseparate', 'enrol_apply'),
                'hassettingsurl' => $needsseparate && !$separate && $canupdate,
                'settingsurl' => (new moodle_url('/course/edit.php', ['id' => $courseid]))->out(false),
                'settingstext' => get_string('coursegroupmodelink', 'enrol_apply'),
                'hasnote' => $note !== '',
                'note' => $note,
                'url' => (new moodle_url('/enrol/apply/edit.php', ['courseid' => $courseid, 'preset' => $key]))->out(false),
                'chosen' => $iscurrent,
                'buttonlabel' => $iscurrent
                    ? get_string('presetchosen', 'enrol_apply')
                    : get_string('presetuse', 'enrol_apply'),
            ];
        }

        return [
            'heading' => get_string('presets', 'enrol_apply'),
            'intro' => get_string('presetsintro', 'enrol_apply'),
            'haschosen' => $chosen !== '',
            'clearurl' => (new moodle_url('/enrol/apply/edit.php', ['courseid' => $courseid]))->out(false),
            'clearlabel' => get_string('presetclear', 'enrol_apply'),
            'cards' => $cards,
        ];
    }

    /**
     * A scenario's title.
     *
     * @param string $key One of {@see KEYS}.
     * @return string The title, plain.
     */
    protected static function title(string $key): string {
        return match ($key) {
            self::REVIEW => get_string('presetreview', 'enrol_apply'),
            self::CLASSES => get_string('presetclasses', 'enrol_apply'),
            self::KEY => get_string('presetkey', 'enrol_apply'),
            self::MODERATORS => get_string('presetmoderators', 'enrol_apply'),
        };
    }

    /**
     * The sentence saying when to use a scenario.
     *
     * @param string $key One of {@see KEYS}.
     * @return string The sentence, plain.
     */
    protected static function when(string $key): string {
        return match ($key) {
            self::REVIEW => get_string('presetreviewwhen', 'enrol_apply'),
            self::CLASSES => get_string('presetclasseswhen', 'enrol_apply'),
            self::KEY => get_string('presetkeywhen', 'enrol_apply'),
            self::MODERATORS => get_string('presetmoderatorswhen', 'enrol_apply'),
        };
    }

    /**
     * What a scenario sets, in the form's own labels and option texts.
     *
     * @param string $key One of {@see KEYS}.
     * @param int $courseid The course.
     * @param array $offeredroles The role ids the form offers.
     * @return array One ['label' => ..., 'value' => ...] per setting, plain.
     */
    protected static function sets(string $key, int $courseid, array $offeredroles): array {
        $everyone = get_string('everyonewhocan', 'admin', get_capability_string('enrol/apply:manageapplications'));
        $item = static fn(string $label, string $value): array => ['label' => $label, 'value' => $value];
        $decided = get_string('groupdecided', 'enrol_apply');
        $deciders = get_string('deciders', 'enrol_apply');
        $notify = get_string('notify', 'enrol_apply');
        $seats = get_string('groupseatsoption', 'enrol_apply');

        return match ($key) {
            self::REVIEW => [
                $item($decided, get_string('groupdecided_decider', 'enrol_apply')),
                $item($deciders, get_string('deciders_group', 'enrol_apply')),
                $item($notify, $everyone),
            ],
            self::CLASSES => [
                $item($decided, get_string('groupdecided_choice', 'enrol_apply')),
                $item(get_string('groupchoicerequired', 'enrol_apply'), get_string('groupchoicerequired_yes', 'enrol_apply')),
                $item($seats, get_string('groupseatsoption_yes', 'enrol_apply')),
                $item($notify, get_string('notifygroupdeciders', 'enrol_apply')),
            ],
            self::KEY => [
                $item($decided, get_string('groupdecided_key', 'enrol_apply')),
                $item(get_string('groupkeys', 'enrol_apply'), get_string('groupkeys_any', 'enrol_apply')),
                $item($deciders, get_string('deciders_group', 'enrol_apply')),
                $item($notify, get_string('notifygroupdeciders', 'enrol_apply')),
            ],
            self::MODERATORS => [
                $item($decided, get_string('groupdecided_choice', 'enrol_apply')),
                $item($seats, get_string('groupseatsoption_no', 'enrol_apply')),
                $item($deciders, get_string('deciders_allgroups', 'enrol_apply')),
                $item(get_string('roleonapproval', 'enrol_apply'), self::moderator_role_name($courseid, $offeredroles)),
            ],
        };
    }

    /**
     * The name of the role "Moderator recruitment" grants, or what to do when none decides.
     *
     * A role has no single spelling: a name typed by an administrator comes out of role_get_name()
     * escaped, a stock role's localised name plain. Each source is read here in its plain spelling.
     *
     * @param int $courseid The course.
     * @param array $offeredroles The role ids the form offers.
     * @return string The plain name, or the sentence asking for a deciding role.
     */
    protected static function moderator_role_name(int $courseid, array $offeredroles): string {
        global $DB;

        $roleid = self::moderator_role($courseid, $offeredroles);
        $role = $roleid === null ? false : $DB->get_record('role', ['id' => $roleid]);
        if (!$role) {
            return get_string('presetrolemissing', 'enrol_apply');
        }

        $context = context_course::instance($courseid);
        $alias = role_get_name($role, $context, ROLENAME_ALIAS_RAW);
        if ($alias !== null && trim($alias) !== '') {
            return format_string($alias, true, ['context' => $context, 'escape' => false]);
        }
        if (trim((string) $role->name) !== '') {
            return format_string($role->name, true, ['context' => context_system::instance(), 'escape' => false]);
        }

        // A stock role's name is a language string, never escaped.
        return role_get_name($role, $context, ROLENAME_ORIGINAL);
    }
}
