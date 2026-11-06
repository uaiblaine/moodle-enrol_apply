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
use context_user;
use moodle_url;
use stdClass;

/**
 * Whether the current user would be shown an applicant's course profile, and the link to it.
 *
 * The review page and the queue link the applicant to user/view.php?id=&course=. That page
 * refuses many readers this plugin admits: a moderator in a separate-groups course, for one,
 * because a pending applicant is in no group yet and core hides a user who shares no group with
 * the reader. A link that ends on "You cannot view the profile of this user" is not offered, so
 * the decision here is the one user/view.php takes, in its order {@see viewable()}.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profilelink {
    /**
     * Whether user/view.php would show this user's profile in this course to the current user.
     *
     * Mirrors user/view.php: the reader's own profile needs access to the course and them enrolled
     * or viewing; a reader with a role in the user's own context and moodle/user:viewdetails there
     * (core's "parent" branch, which is how this plugin's mentor level is granted) is let in
     * without the course; everybody else needs access to the course ({@see enters()}), then
     * core's profile rule: \core\user::can_view_profile() from 5.3, user_can_view_profile()
     * before it.
     *
     * @param stdClass $user The applicant: id and deleted are read.
     * @param stdClass $course The course the profile is shown in: a course record.
     * @return bool True when the link would open the profile.
     */
    public static function viewable(stdClass $user, stdClass $course): bool {
        global $DB, $USER;

        if (!empty($user->deleted)) {
            return false;
        }
        $usercontext = context_user::instance((int) $user->id, IGNORE_MISSING);
        $coursecontext = context_course::instance((int) $course->id, IGNORE_MISSING);
        if (!$usercontext || !$coursecontext) {
            return false;
        }

        if ((int) $user->id === (int) $USER->id) {
            return self::enters($course) && (is_viewing($coursecontext) || is_enrolled($coursecontext));
        }

        if (
            $DB->record_exists('role_assignments', ['userid' => $USER->id, 'contextid' => $usercontext->id])
            && has_capability('moodle/user:viewdetails', $usercontext)
        ) {
            return true;
        }

        if (!self::enters($course)) {
            return false;
        }

        return self::core_rule($user, $course);
    }

    /**
     * Which of a page of applicants' profiles user/view.php would open for the current user.
     *
     * The batch form of {@see viewable()} for the queue, which would otherwise run core's rule
     * once per row, three statements each. It takes the same steps in the same order and stays
     * clause for clause with it (test_the_batch_agrees_with_the_single_answer), core's
     * is_enrolled() included, asked for the page at once. Core's own per-user rule is used
     * whenever a plugin implements control_view_profile, whose callbacks judge one user at a time.
     *
     * @param array $users User id => deleted flag.
     * @param stdClass $course The course the profiles are shown in: a course record.
     * @return array The ids of the users whose profile would open, in no particular order.
     */
    public static function viewable_ids(array $users, stdClass $course): array {
        global $CFG, $DB, $USER;

        $ids = [];
        foreach ($users as $id => $deleted) {
            if (empty($deleted)) {
                $ids[] = (int) $id;
            }
        }
        $coursecontext = context_course::instance((int) $course->id, IGNORE_MISSING);
        if (!$ids || !$coursecontext) {
            return [];
        }

        $allowed = [];
        $reader = (int) $USER->id;
        if (in_array($reader, $ids, true)) {
            if (self::enters($course) && (is_viewing($coursecontext) || is_enrolled($coursecontext))) {
                $allowed[$reader] = true;
            }
            $ids = array_values(array_diff($ids, [$reader]));
        }

        // Core's "parent" branch: a role in the user's own context and viewdetails there.
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'pl');
            $parents = $DB->get_fieldset_sql(
                "SELECT DISTINCT ctx.instanceid
                   FROM {role_assignments} ra
                   JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :userlevel
                  WHERE ra.userid = :reader AND ctx.instanceid $insql",
                $params + ['userlevel' => CONTEXT_USER, 'reader' => $reader]
            );
            foreach ($parents as $id) {
                if (has_capability('moodle/user:viewdetails', context_user::instance((int) $id))) {
                    $allowed[(int) $id] = true;
                }
            }
            $ids = array_values(array_diff($ids, array_keys($allowed)));
        }

        if (!$ids || !self::enters($course)) {
            return array_keys($allowed);
        }

        if (get_plugins_with_function('control_view_profile')) {
            foreach ($ids as $id) {
                if (self::core_rule((object) ['id' => $id, 'deleted' => 0], $course)) {
                    $allowed[$id] = true;
                }
            }
            return array_keys($allowed);
        }

        // Core's rule from here, its steps in its order.
        if (empty($CFG->forceloginforprofiles)) {
            return array_merge(array_keys($allowed), $ids);
        }
        if (!isloggedin() || isguestuser()) {
            return array_keys($allowed);
        }

        // The page's has_coursecontact_role().
        if (!empty($CFG->coursecontact)) {
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'cc');
            $contacts = $DB->get_fieldset_sql(
                "SELECT DISTINCT userid FROM {role_assignments} WHERE userid $insql AND roleid IN ($CFG->coursecontact)",
                $params
            );
            foreach ($contacts as $id) {
                $allowed[(int) $id] = true;
            }
            $ids = array_values(array_diff($ids, array_keys($allowed)));
        }

        // The page's is_enrolled($coursecontext, $user): any enrolment in the course, any status.
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'en');
            $ids = array_map('intval', $DB->get_fieldset_sql(
                "SELECT DISTINCT ue.userid
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
                   JOIN {user} u ON u.id = ue.userid
                  WHERE ue.userid $insql AND u.deleted = 0",
                $params + ['courseid' => (int) $course->id]
            ));
        }

        if (
            !$ids
            || (!has_capability('moodle/user:viewdetails', $coursecontext)
                && !has_capability('moodle/user:viewalldetails', $coursecontext))
        ) {
            return array_keys($allowed);
        }

        // The page's groups_user_groups_visible().
        $groupmode = groups_get_course_groupmode($course);
        if (
            $groupmode == NOGROUPS || $groupmode == VISIBLEGROUPS
            || has_capability('moodle/site:accessallgroups', $coursecontext)
        ) {
            return array_merge(array_keys($allowed), $ids);
        }

        /* A group the user and the reader both belong to, among the groups groups_get_all_groups()
           returns for a given user: those of visibility ALL, MEMBERS or OWN, or every one for a
           holder of moodle/course:viewhiddengroups. */
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'gu');
        $visibility = '';
        if (!has_capability('moodle/course:viewhiddengroups', $coursecontext)) {
            [$vsql, $vparams] = $DB->get_in_or_equal(
                [GROUPS_VISIBILITY_ALL, GROUPS_VISIBILITY_MEMBERS, GROUPS_VISIBILITY_OWN],
                SQL_PARAMS_NAMED,
                'gv'
            );
            $visibility = "AND g.visibility $vsql";
            $params += $vparams;
        }
        $shared = $DB->get_fieldset_sql(
            "SELECT DISTINCT theirs.userid
               FROM {groups_members} theirs
               JOIN {groups} g ON g.id = theirs.groupid AND g.courseid = :courseid
               JOIN {groups_members} mine ON mine.groupid = g.id AND mine.userid = :reader
              WHERE theirs.userid $insql $visibility",
            $params + ['courseid' => (int) $course->id, 'reader' => $reader]
        );
        foreach ($shared as $id) {
            $allowed[(int) $id] = true;
        }

        return array_keys($allowed);
    }

    /**
     * Whether the current user would pass user/view.php's require_login($course).
     *
     * The four-argument can_access_course(): with $onlyactive false it counts a suspended or
     * expired enrolment as access, which require_login() refuses.
     *
     * @param stdClass $course The course: a course record.
     * @return bool True when the reader may enter the course.
     */
    private static function enters(stdClass $course): bool {
        return can_access_course($course, null, '', true);
    }

    /**
     * Core's profile rule for one user in one course, whichever core this is.
     *
     * @param stdClass $user The user: id and deleted are read.
     * @param stdClass $course The course: a course record.
     * @return bool What core answers.
     */
    private static function core_rule(stdClass $user, stdClass $course): bool {
        global $CFG;

        $usercontext = context_user::instance((int) $user->id, IGNORE_MISSING);
        if (!$usercontext) {
            return false;
        }
        if (method_exists(\core\user::class, 'can_view_profile')) {
            return \core\user::can_view_profile($user, $course, $usercontext);
        }
        require_once($CFG->dirroot . '/user/lib.php');
        return user_can_view_profile($user, $course, $usercontext);
    }

    /**
     * The applicant's profile in the course, or null when the current user would be refused it.
     *
     * @param stdClass $user The applicant: id and deleted are read.
     * @param stdClass $course The course the profile is shown in: a course record.
     * @return moodle_url|null The user/view.php link, or null.
     */
    public static function url(stdClass $user, stdClass $course): ?moodle_url {
        if (!self::viewable($user, $course)) {
            return null;
        }

        return new moodle_url('/user/view.php', ['id' => (int) $user->id, 'course' => (int) $course->id]);
    }
}
