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
 * A method's "Notify of new applications" setting ({enrol}.customtext3): how it is stored and read.
 *
 * The column holds a comma separated list, the shape admin_setting_users_with_capability gives
 * the site-wide setting: '' or {@see NONE} for nobody, {@see ALL} for every course decider, or
 * user ids, optionally led by {@see GROUP}, the deciders of the application's group. Every writer
 * and reader goes through here: the instance form both ways ({@see from_form()}, {@see to_form()}),
 * the notice ({@see parse()}, {@see for_group()}, through
 * enrol_apply_plugin::get_notifycoursebased_users()) and the restore ({@see for_restore()}).
 *
 * The value is read strictly, because a restore carries it verbatim: {@see ALL} only as the whole
 * value and {@see GROUP} only as the first item, as the form writes them. Anything else is a list
 * of ids, and an item that names no decider of the course, an unknown marker among them, matches
 * nobody.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifyrecipients {
    /** @var string Nobody: the form's option, stored as an empty value. */
    public const NONE = '$@NONE@$';

    /** @var string Every actively enrolled decider of the course. */
    public const ALL = '$@ALL@$';

    /** @var string The deciders of the application's group, or who sees every group when it has none. */
    public const GROUP = '$@GROUP@$';

    /**
     * The stored value for what the form's multi-select posted.
     *
     * The way admin_setting_users_with_capability::write_setting() stores the site-wide setting:
     * "everyone" wins over any other pick, and "nobody" is dropped (an empty value when nothing
     * else is picked). The group marker is written first and the people picked by name after it,
     * which is the one place {@see parse()} looks for it.
     *
     * @param array $picked The option values posted, in any order.
     * @return string The value for customtext3.
     */
    public static function from_form(array $picked): string {
        $picked = array_map('strval', $picked);
        if (in_array(self::ALL, $picked, true)) {
            return self::ALL;
        }

        $group = in_array(self::GROUP, $picked, true);
        $named = array_values(array_diff($picked, [self::NONE, self::GROUP]));

        return implode(',', $group ? array_merge([self::GROUP], $named) : $named);
    }

    /**
     * The multi-select's selection for a stored value: the reverse of {@see from_form()}.
     *
     * @param string $stored The value of customtext3.
     * @return array The option values to select; {@see NONE} for an empty value.
     */
    public static function to_form(string $stored): array {
        return $stored === '' ? [self::NONE] : explode(',', $stored);
    }

    /**
     * What a stored value asks for.
     *
     * @param string $stored The value of customtext3.
     * @return stdClass 'everyone' (bool: every course decider), 'group' (bool: the deciders of the
     *     application's group) and 'ids' (int[]: the people picked by name, in stored order; an
     *     item that is not a positive whole number, an unknown marker among them, is left out,
     *     as it would match nobody).
     */
    public static function parse(string $stored): stdClass {
        $result = (object) ['everyone' => false, 'group' => false, 'ids' => []];
        if ($stored === '' || $stored === self::NONE) {
            return $result;
        }
        if ($stored === self::ALL) {
            $result->everyone = true;
            return $result;
        }

        $items = explode(',', $stored);
        if ($items[0] === self::GROUP) {
            $result->group = true;
            array_shift($items);
        }
        foreach ($items as $item) {
            if (preg_match('/^[1-9][0-9]*$/', $item)) {
                $result->ids[] = (int) $item;
            }
        }

        return $result;
    }

    /**
     * The course deciders told of an application of one group under {@see GROUP}.
     *
     * The group's deciders, when there is one: the candidates groupscope narrows in the course
     * whom {@see groupscope::allows_group()} lets decide an application of this group to this
     * method. Otherwise the candidates groupscope does not narrow, exactly the ones {@see ALL} would
     * tell after the per-recipient filter: an application without a group, a group no narrowed
     * decider holds, a method only those who see every group decide, or a course where nobody is
     * narrowed. So the result is always a subset of the candidates, and empty only when {@see ALL}
     * would tell nobody either. The caller still filters each recipient by allows_group().
     *
     * @param array $candidates The actively enrolled holders of the capability, keyed by user id.
     * @param int $courseid The course applied to.
     * @param int $enrolid The method applied to.
     * @param int $groupid The application's group ({@see groupscope::application_group()}), 0 for none.
     * @return array The recipients, a subset of $candidates keyed the same way.
     */
    public static function for_group(array $candidates, int $courseid, int $enrolid, int $groupid): array {
        $deciders = [];
        $unnarrowed = [];
        foreach ($candidates as $userid => $user) {
            if (groupscope::narrowed_to($courseid, (int) $userid, groupscope::DECIDE) === null) {
                $unnarrowed[$userid] = $user;
            } else if (groupscope::allows_group($courseid, $groupid, (int) $userid, groupscope::DECIDE, $enrolid)) {
                $deciders[$userid] = $user;
            }
        }

        return $deciders ?: $unnarrowed;
    }

    /**
     * The value a restore writes.
     *
     * On the same site every id still names the person it named. From another site an id names
     * somebody else, so the ids are dropped and the markers kept: {@see ALL} as it is,
     * {@see GROUP} without the people it was combined with, anything else as nobody.
     *
     * @param string $stored The value the archive carries.
     * @param bool $samesite Whether the archive comes from this site.
     * @return string The value to restore.
     */
    public static function for_restore(string $stored, bool $samesite): string {
        if ($samesite || $stored === '' || $stored === self::ALL) {
            return $stored;
        }

        return self::parse($stored)->group ? self::GROUP : '';
    }
}
