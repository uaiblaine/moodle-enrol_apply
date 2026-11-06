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

/**
 * Whether a decider is narrowed to their own groups in a course, and to which ones.
 *
 * The rule is core's own for the participants page (user/index.php and
 * participants_search.php): a user is group-scoped in a course whose group mode is
 * SEPARATEGROUPS when they lack moodle/site:accessallgroups in its context. VISIBLEGROUPS and
 * NOGROUPS scope nobody (docs/design/group-review-scope.md, S1 and S2).
 *
 * A pending application belongs to the group of its key, enrol_apply_submission.keygroupid on
 * the newest record of its user enrolment, 0 for none. A narrowed decider may see and decide it
 * only when that group is one of theirs, so an application without a key reaches only those
 * who can access all groups (S3).
 *
 * Only a COURSE-level grant is narrowed, and the rule asks about the capability actually being
 * exercised: enrol/apply:manageapplications for deciding, enrol/apply:viewapplications for
 * looking without deciding (S5), enrol/apply:viewreports for the course report (S8). A holder of
 * that capability at system level is never narrowed, even inside a course, and the mentor level
 * (manageapplications in the applicant's user context) does not come from the course at all, so
 * {@see \enrol_apply_plugin::can_manage_application()} and
 * {@see \enrol_apply_plugin::can_view_application()} apply this to their course branch alone.
 *
 * Every reader of the rule comes here: can_manage_application() (the per-row backstop of every
 * decision and of the review page), can_view_application(), the queue's listing, its
 * previous/next walk, the group choosers and their allowlist, the managers' notice, the
 * participants page icon, the hook that guards core's edit-enrolment screen and the course
 * report's base condition ({@see record_where()}).
 *
 * Nothing is memoised. PHPUnit resets the database sequences between tests, so a static keyed
 * on course and user ids would hand one test another test's answer, and the answer depends on
 * role assignments, overrides and memberships that a request can change. The cost is one
 * indexed query over {groups_members} per call; has_capability() is cached by core.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class groupscope {
    /** @var string The capability a decision exercises. */
    public const DECIDE = 'enrol/apply:manageapplications';

    /** @var string The capability looking at applications without deciding exercises (S5). */
    public const VIEW = 'enrol/apply:viewapplications';

    /** @var string The capability the course report exercises (S8). */
    public const REPORT = 'enrol/apply:viewreports';

    /**
     * Whether core's separate-groups rule narrows this user in this course.
     *
     * The bare rule, with no regard for the plugin's capabilities: {@see narrowed_to()} is what
     * a decision reads. A course that no longer exists, or has no context, scopes nobody; the
     * callers' own capability checks refuse there anyway.
     *
     * @param int $courseid Course id.
     * @param int $userid The user the question is about.
     * @return bool True when the course is in separate groups mode and the user may not access
     *     all groups in it.
     */
    public static function is_scoped(int $courseid, int $userid): bool {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id, groupmode', IGNORE_MISSING);
        if (!$course || (int) groups_get_course_groupmode($course) !== SEPARATEGROUPS) {
            return false;
        }

        $context = context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }

        return !has_capability('moodle/site:accessallgroups', $context, $userid);
    }

    /**
     * The groups this user decides for (or looks at) in this course, or null when not narrowed.
     *
     * Null for a holder of $capability at system level, whose site-wide delegation does not come
     * from the course, and for anybody {@see is_scoped()} does not narrow. The exemption reads the
     * capability being exercised and no other: a site-wide viewer is not thereby a site-wide
     * decider, and a site-wide decider's exemption says nothing about the report. An empty array is
     * a narrowed user in no group they may see: they decide (or see) nothing here, and the queue
     * and the report say so with core's own notice (S4).
     *
     * @param int $courseid Course id.
     * @param int $userid The decider, the viewer, or the recipient of a notice.
     * @param string $capability The capability exercised: {@see DECIDE}, {@see VIEW} or {@see REPORT}.
     * @return array|null Group ids, ascending; null when every application of the course is theirs.
     */
    public static function narrowed_to(int $courseid, int $userid, string $capability = self::DECIDE): ?array {
        if (has_capability($capability, context_system::instance(), $userid)) {
            return null;
        }

        if (!self::is_scoped($courseid, $userid)) {
            return null;
        }

        return self::member_groups($courseid, $userid);
    }

    /**
     * The capability the current user exercises when they read applications in a context.
     *
     * Deciding when they hold enrol/apply:manageapplications there, looking without deciding
     * otherwise. The queue's listing ({@see queue::listing_scope()}), its walk ({@see queue::scope()})
     * and the review page's context ({@see queue::require_review_access()}) all narrow by the
     * answer, so the three agree on which system exemption applies: a decider narrowed in the
     * course who also views site-wide lists their own groups on the instance queue and reaches the
     * rest through the site-wide one, read-only.
     *
     * @param \context $context The course context, or the system context for the site-wide queue.
     * @return string {@see DECIDE} or {@see VIEW}.
     */
    public static function reader_capability(\context $context): string {
        return has_capability(self::DECIDE, $context) ? self::DECIDE : self::VIEW;
    }

    /**
     * The groups of this course the user belongs to and may see.
     *
     * The same answer as array_keys(groups_get_all_groups($courseid, $userid)), which is what
     * core's participants page asks for $USER (user/index.php, participants_search.php), written
     * out because that helper judges moodle/course:viewhiddengroups for $USER whatever $userid
     * says, while a notice is judged for its recipient. The visibility half is
     * {@see groupvisibility::visible_to()}, the plugin's copy of that clause for an explicit
     * reader, so a group hidden from its own member does not scope them into it.
     *
     * @param int $courseid Course id.
     * @param int $userid The member.
     * @return array Group ids, ascending.
     */
    public static function member_groups(int $courseid, int $userid): array {
        global $DB;

        // DISTINCT: {groups_members} carries no unique key on (groupid, userid).
        $groups = $DB->get_records_sql(
            "SELECT DISTINCT g.id, g.courseid, g.visibility
               FROM {groups} g
               JOIN {groups_members} gm ON gm.groupid = g.id
              WHERE g.courseid = :courseid AND gm.userid = :userid
           ORDER BY g.id",
            ['courseid' => $courseid, 'userid' => $userid]
        );

        $ids = [];
        foreach ($groups as $group) {
            if (groupvisibility::visible_to($group, $userid)) {
                $ids[] = (int) $group->id;
            }
        }

        return $ids;
    }

    /**
     * The group a pending application belongs to.
     *
     * The key's group on the newest record of the user enrolment, the record key_of() and
     * chosen_groups() read, so that a user enrolment carrying an older record does not take its
     * group from it. {@see where()} expresses the same choice in SQL; keep the two in step.
     *
     * Read here rather than through submission::key_of(), which answers null for an outcome
     * outside its vocabulary: the SQL cannot tell such a row apart, and the per-row check and the
     * listing must agree on every row.
     *
     * @param int $userenrolmentid User enrolment the application belongs to.
     * @return int Group id, 0 for an application without a key or without a record.
     */
    public static function application_group(int $userenrolmentid): int {
        global $DB;

        $rows = $DB->get_records(
            'enrol_apply_submission',
            ['userenrolmentid' => $userenrolmentid],
            'timecreated DESC, id DESC',
            'id, keygroupid',
            0,
            1
        );
        $row = reset($rows);

        return $row ? max(0, (int) $row->keygroupid) : 0;
    }

    /**
     * Whether a group is one this user decides for in this course.
     *
     * @param int $courseid Course id.
     * @param int $groupid The group, 0 for an application without one.
     * @param int $userid The decider, or the recipient of a notice.
     * @param string $capability The capability exercised, see {@see narrowed_to()}.
     * @return bool True when the user is not narrowed, or the group is one of theirs.
     */
    public static function allows_group(int $courseid, int $groupid, int $userid, string $capability = self::DECIDE): bool {
        $groups = self::narrowed_to($courseid, $userid, $capability);
        if ($groups === null) {
            return true;
        }

        // No group id is 0, so an application without a key is never in the list.
        return in_array($groupid, $groups, true);
    }

    /**
     * Whether the course-level grant lets this user see and decide (or only see) this application.
     *
     * Only the group half: the caller has checked $capability in the course.
     *
     * @param int $courseid Course the application belongs to.
     * @param int $userenrolmentid User enrolment the application belongs to.
     * @param int $userid The decider or viewer.
     * @param string $capability The capability exercised, see {@see narrowed_to()}.
     * @return bool True when the user is not narrowed, or the application's group is theirs.
     */
    public static function allows(int $courseid, int $userenrolmentid, int $userid, string $capability = self::DECIDE): bool {
        $groups = self::narrowed_to($courseid, $userid, $capability);
        if ($groups === null) {
            return true;
        }

        $groupid = self::application_group($userenrolmentid);

        // No group id is 0, so an application without a key is never in the list.
        return in_array($groupid, $groups, true);
    }

    /**
     * The SQL form of {@see allows()} for a narrowed reader, over a user enrolment aliased "ue".
     *
     * True when the newest record of the user enrolment names one of the groups. Written with
     * NOT EXISTS rather than a LIMIT inside a subquery, which not every supported database
     * accepts; "newest" is (timecreated, id), as {@see application_group()} orders. An
     * application without a record, or whose key named no group, matches nothing, because no
     * group id is 0.
     *
     * get_in_or_equal() throws on an empty array, so a reader in no group gets a never-true
     * predicate rather than a query.
     *
     * @param array $groupids The reader's groups, as {@see narrowed_to()} returns them.
     * @return array Two elements: the where fragment and its named parameters.
     */
    public static function where(array $groupids): array {
        global $DB;

        if (!$groupids) {
            return ['1 = 0', []];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_values($groupids), SQL_PARAMS_NAMED, 'scopegroup');

        return [
            "EXISTS (
                SELECT 1
                  FROM {enrol_apply_submission} scopesub
                 WHERE scopesub.userenrolmentid = ue.id
                   AND scopesub.keygroupid {$insql}
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {enrol_apply_submission} scopenewer
                        WHERE scopenewer.userenrolmentid = scopesub.userenrolmentid
                          AND (scopenewer.timecreated > scopesub.timecreated
                               OR (scopenewer.timecreated = scopesub.timecreated AND scopenewer.id > scopesub.id))
                   )
            )",
            $params,
        ];
    }

    /**
     * The SQL form of the report's narrowing (S8), over the durable record aliased $alias.
     *
     * A record belongs to a group by its key while it waits and, once decided, also by the groups
     * the decider chose: true when keygroupid is one of the groups, or when the comma-separated
     * decidedgroups names one ({@see submission::record_decided_groups()} writes it). Unlike
     * {@see where()} every record of the course is a row of the report, so each is judged by its
     * own columns rather than by the newest record of its user enrolment.
     *
     * The list is matched as ",<ids>," against "%,<id>,%", one LIKE per group, so that group 1
     * never matches a list naming 11 or 21. Portable on purpose: sql_concat() and sql_like() rather
     * than FIND_IN_SET or a family branch, and each LIKE takes a placeholder of its own, because a
     * named placeholder may appear once per statement. COALESCE keeps a NULL list (a record older
     * than the column) from making the whole disjunct unknown on one family and false on another.
     *
     * get_in_or_equal() throws on an empty array, so a reader in no group gets a never-true
     * predicate rather than a query, and the report shows core's notice (S4).
     *
     * @param string $alias Alias of {enrol_apply_submission} in the caller's query.
     * @param array $groupids The reader's groups, as {@see narrowed_to()} returns them.
     * @param string $prefix Prefix for the placeholder names, unique in the caller's statement.
     * @return array Two elements: the where fragment and its named parameters.
     */
    public static function record_where(string $alias, array $groupids, string $prefix): array {
        global $DB;

        $groupids = array_values(array_map('intval', $groupids));
        if (!$groupids) {
            return ['1 = 0', []];
        }

        [$insql, $params] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, $prefix . 'key');

        $list = $DB->sql_concat("','", "COALESCE({$alias}.decidedgroups, '')", "','");
        $likes = [];
        foreach ($groupids as $index => $groupid) {
            $name = $prefix . 'decided' . $index;
            $likes[] = $DB->sql_like($list, ':' . $name);
            $params[$name] = '%,' . $groupid . ',%';
        }

        return ["({$alias}.keygroupid {$insql} OR " . implode(' OR ', $likes) . ')', $params];
    }

    /**
     * The groups of this course the current user may choose for an approval (S6).
     *
     * Every group of the course groups_get_all_groups() lists to $USER, narrowed to their own
     * for a narrowed decider. The group choosers (the queue, the review page, the participants
     * page's bulk form) offer this list and confirm_enrolment() allowlists the posted ids against
     * it, so a control cannot offer a group the decision would drop, and a forged post naming a
     * foreign group joins nothing.
     *
     * @param int $courseid Course id.
     * @return array The {groups} records, keyed by group id.
     */
    public static function choosable_groups(int $courseid): array {
        global $USER;

        $groups = groups_get_all_groups($courseid);
        $own = self::narrowed_to($courseid, (int) $USER->id);
        if ($own === null) {
            return $groups;
        }

        return array_intersect_key($groups, array_flip($own));
    }
}
