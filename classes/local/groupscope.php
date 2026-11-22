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
 * A pending application belongs to "the application's group" (docs/design/applicant-group-choice.md,
 * C-D4): on the newest record of its user enrolment, the key's group when a key was presented
 * (enrol_apply_submission.keygroupid) and the group the applicant chose otherwise (chosengroupid),
 * 0 for none; {@see application_group()} and {@see application_group_sql()}. A narrowed decider
 * may see and decide it only when that group is one of theirs, so a chosen group routes exactly as
 * a key group does (C-D11), and an application without either reaches only those who can access
 * all groups (S3).
 *
 * Only a COURSE-level grant is narrowed, and the rule asks about the capability actually being
 * exercised: enrol/apply:manageapplications for deciding, enrol/apply:viewapplications for
 * looking without deciding (S5), enrol/apply:viewreports for the course report (S8). A holder of
 * that capability at system level is never narrowed, even inside a course, and the mentor level
 * (manageapplications in the applicant's user context) does not come from the course at all, so
 * {@see \enrol_apply_plugin::can_manage_application()} and
 * {@see \enrol_apply_plugin::can_view_application()} apply this to their course branch alone.
 *
 * Two refinements narrow a narrowed reader further (docs/design/applicant-group-choice.md). A
 * method whose "Who decides" is only those who see every group (C-D20,
 * {@see decided_by_all_groups()}) gives a narrowed reader none of its applications, whatever their
 * group. And a membership this plugin wrote on approval scopes its member only when the same
 * method gave them a role that decides (C-D28, {@see member_groups()}), so a moderator who is also
 * a student of the course decides nothing for the group they study in.
 *
 * Every reader of the rule comes here: can_manage_application() (the per-row backstop of every
 * decision and of the review page), can_view_application(), the queue's listing, its
 * previous/next walk, the group choosers and their allowlist, the managers' notice, the
 * participants page icon, the hook that guards core's edit-enrolment screen and the course
 * report's base condition and its group filter ({@see record_where()}).
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
     * from the course (unless they switched role in it, {@see system_exempt()}), and for anybody
     * {@see is_scoped()} does not narrow. The exemption reads the
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
        if (self::system_exempt($courseid, $userid, $capability)) {
            return null;
        }

        if (!self::is_scoped($courseid, $userid)) {
            return null;
        }

        return self::member_groups($courseid, $userid);
    }

    /**
     * Whether a system-level grant of the capability exempts this user from this course's narrowing.
     *
     * Not while the current user has switched role in the course ("Switch role to..."): core judges
     * a switched user by the role switched to in that course, so its participants page narrows a
     * site administrator previewing as a moderator to the moderator's groups. Read at system
     * level, where nothing is switched, the exemption would list that previewer every group's
     * applications instead. The switch lives in $USER, so only the current user can have made
     * one; anybody else, such as the recipient of a notice, is judged by their grants alone.
     *
     * @param int $courseid Course id.
     * @param int $userid The decider, the viewer, or the recipient of a notice.
     * @param string $capability The capability exercised: {@see DECIDE}, {@see VIEW} or {@see REPORT}.
     * @return bool True when the system-level grant applies here.
     */
    public static function system_exempt(int $courseid, int $userid, string $capability): bool {
        global $USER;

        if ($userid === (int) $USER->id && self::switched($courseid)) {
            return false;
        }

        return has_capability($capability, context_system::instance(), $userid);
    }

    /**
     * Whether the current user has switched role in this course ("Switch role to...").
     *
     * A course that no longer exists has no switch.
     *
     * @param int $courseid Course id.
     * @return bool True while the switch holds.
     */
    public static function switched(int $courseid): bool {
        return context_course::instance($courseid, IGNORE_MISSING) && is_role_switched($courseid);
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
     * The groups of this course whose applications this user decides (or looks at) when narrowed.
     *
     * The groups they belong to and may see: the answer of array_keys(groups_get_all_groups($courseid,
     * $userid)), which is what core's participants page asks for $USER (user/index.php,
     * participants_search.php), written out because that helper judges
     * moodle/course:viewhiddengroups for $USER whatever $userid says, while a notice is judged for
     * its recipient. The visibility half is {@see groupvisibility::visible_to()}, the plugin's copy
     * of that clause for an explicit reader, so a group hidden from its own member does not scope
     * them into it.
     *
     * Less one kind of membership (C-D28, decided (b)): one this plugin wrote on an approval
     * (component enrol_apply, itemid the method) counts only when the role assignment the same
     * method gave the same user in the course (role_assignments stamped enrol_apply with the same
     * itemid, at the course context) is of a role that decides there ({@see decidingrole}). So the
     * membership a student method left a student in decides nothing, while the one a recruitment
     * method left a moderator in does; a membership written by hand or by another plugin keeps
     * core's rule. Both facts are the ones the approval recorded (groups_add_member() and
     * role_assign() in complete_approval(), each stamped), never the method's current settings.
     *
     * Read once here and handed to both the per-row check ({@see allows()}) and the listing
     * ({@see where()}), so the two cannot disagree on it.
     *
     * @param int $courseid Course id.
     * @param int $userid The member.
     * @return array Group ids, ascending.
     */
    public static function member_groups(int $courseid, int $userid): array {
        global $DB;

        $params = [
            'courseid' => $courseid,
            'userid' => $userid,
            'gmcomponent' => 'enrol_apply',
            'racomponent' => 'enrol_apply',
        ];

        /* A membership of this plugin's narrows only through a deciding role the same method
           assigned. get_in_or_equal() throws on an empty array, so a course where no role decides
           lets none of them through. */
        $deciding = decidingrole::roles($courseid);
        $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
        if ($deciding && $coursecontext) {
            [$rolesql, $roleparams] = $DB->get_in_or_equal($deciding, SQL_PARAMS_NAMED, 'scoperole');
            $params += $roleparams + ['coursecontextid' => $coursecontext->id];
            $ownmembership = "EXISTS (
                    SELECT 1
                      FROM {role_assignments} ra
                     WHERE ra.userid = gm.userid
                       AND ra.component = :racomponent
                       AND ra.itemid = gm.itemid
                       AND ra.contextid = :coursecontextid
                       AND ra.roleid {$rolesql}
                )";
        } else {
            $ownmembership = '1 = 0';
        }

        // DISTINCT: {groups_members} carries no unique key on (groupid, userid).
        $groups = $DB->get_records_sql(
            "SELECT DISTINCT g.id, g.courseid, g.visibility
               FROM {groups} g
               JOIN {groups_members} gm ON gm.groupid = g.id
              WHERE g.courseid = :courseid AND gm.userid = :userid
                AND (gm.component <> :gmcomponent OR {$ownmembership})
           ORDER BY g.id",
            $params
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
     * Whether a method is decided only by those groupscope does not narrow (C-D20).
     *
     * The method's "Who decides" option ({@see instanceoptions::DECIDERS_ALLGROUPS}), read strictly
     * by {@see instanceoptions::get()}, which answers the default (the group's deciders) before the
     * upgrade that creates the options has run. A narrowed reader gets none of such a method's
     * applications, whatever their group: in the listing ({@see where()}), the per-row check
     * ({@see allows()}), the notices ({@see allows_group()}), the choosers
     * ({@see choosable_groups()}) and the course report ({@see record_where()}).
     *
     * @param int $enrolid Enrol instance id.
     * @return bool True when only readers who see every group decide it.
     */
    public static function decided_by_all_groups(int $enrolid): bool {
        return (int) instanceoptions::get($enrolid)->deciders === instanceoptions::DECIDERS_ALLGROUPS;
    }

    /**
     * The SQL form of {@see decided_by_all_groups()}, negated: true for a method its groups' deciders decide.
     *
     * Over a column holding the enrol instance id. The option's value is a class constant, written
     * into the SQL rather than bound, so the fragment can sit twice in one statement without two
     * placeholders of one name. Read as instanceoptions reads it: a value other than
     * DECIDERS_ALLGROUPS, or no row, is the group's deciders. Before the upgrade that creates the
     * table nothing can be stored there, so the fragment is the always-true one rather than a
     * query of a table that does not exist yet.
     *
     * @param string $enrolidcolumn The column, such as "ue.enrolid" or "s.enrolid".
     * @return string The where fragment; it takes no parameters.
     */
    public static function decided_by_groups_where(string $enrolidcolumn): string {
        if (!instanceoptions::schema_ready()) {
            return '1 = 1';
        }

        return "NOT EXISTS (
                SELECT 1
                  FROM {enrol_apply_options} scopeoption
                 WHERE scopeoption.enrolid = {$enrolidcolumn}
                   AND scopeoption.deciders = " . instanceoptions::DECIDERS_ALLGROUPS . "
            )";
    }

    /**
     * The group a pending application belongs to: "the application's group" (C-D4).
     *
     * Read on the newest record of the user enrolment, the record key_of(), chosen_group_of() and
     * chosen_groups() read, so that a user enrolment carrying an older record does not take its
     * group from it: the key's group when the record has a key route (any keyoutcome but
     * KEY_NONE, the vocabulary's or not), and the group the applicant chose otherwise. A record
     * never carries both ({@see submission::create()} refuses it, and the restore drops a choice
     * beside a key), so which one wins matters only for a hand-edited row, and the key wins there
     * as everywhere else. {@see application_group_sql()} expresses the same choice in SQL; keep
     * the two in step (test_the_sql_and_the_per_row_check_agree).
     *
     * Read here rather than through submission::key_of(), which answers null for an outcome
     * outside its vocabulary: the SQL cannot tell such a row apart, and the per-row check and the
     * listing must agree on every row. Before the upgrade that adds chosengroupid nobody chose,
     * and the column is not read.
     *
     * @param int $userenrolmentid User enrolment the application belongs to.
     * @return int Group id, 0 for an application without a key or a choice, or without a record.
     */
    public static function application_group(int $userenrolmentid): int {
        global $DB;

        $choice = instanceoptions::schema_ready();
        $rows = $DB->get_records(
            'enrol_apply_submission',
            ['userenrolmentid' => $userenrolmentid],
            'timecreated DESC, id DESC',
            'id, keygroupid, keyoutcome' . ($choice ? ', chosengroupid' : ''),
            0,
            1
        );
        $row = reset($rows);
        if (!$row) {
            return 0;
        }
        if ((int) $row->keyoutcome !== submission::KEY_NONE) {
            return max(0, (int) $row->keygroupid);
        }

        return $choice ? max(0, (int) $row->chosengroupid) : 0;
    }

    /**
     * The SQL form of {@see application_group()} over one record aliased $alias.
     *
     * The key's group on a record with a key route, the chosen group otherwise: a CASE, because
     * a record never carries both (C-D4). Every reader that files an application under a group
     * selects or compares this expression and never a bare keygroupid: the scope and its filter
     * ({@see group_where()}), the report's narrowing and filter ({@see record_group_where()}), the
     * queue's "without a group" ({@see groupfilter::where()}) and its group column. The caller
     * restricts $alias to the newest record where that matters, as {@see group_where()} does.
     *
     * Before the upgrade that adds chosengroupid nobody chose, so the expression reads the key's
     * group alone rather than a column that does not exist yet. The constant is written into the
     * SQL rather than bound, so the expression can sit several times in one statement.
     *
     * @param string $alias Alias of {enrol_apply_submission} in the caller's query.
     * @return string An SQL expression taking no parameters; 0 for no group.
     */
    public static function application_group_sql(string $alias): string {
        $otherwise = instanceoptions::schema_ready() ? "{$alias}.chosengroupid" : '0';

        return "(CASE WHEN {$alias}.keyoutcome <> " . submission::KEY_NONE
            . " THEN {$alias}.keygroupid ELSE {$otherwise} END)";
    }

    /**
     * Whether a group is one this user decides for in this course.
     *
     * @param int $courseid Course id.
     * @param int $groupid The group, 0 for an application without one.
     * @param int $userid The decider, or the recipient of a notice.
     * @param string $capability The capability exercised, see {@see narrowed_to()}.
     * @param int $enrolid The method the application was made to, whose "Who decides" a narrowed
     *     user is judged by too ({@see decided_by_all_groups()}); 0 for a group with no method.
     * @return bool True when the user is not narrowed, or the group is one of theirs on a method its
     *     groups' deciders decide.
     */
    public static function allows_group(
        int $courseid,
        int $groupid,
        int $userid,
        string $capability = self::DECIDE,
        int $enrolid = 0
    ): bool {
        $groups = self::narrowed_to($courseid, $userid, $capability);
        if ($groups === null) {
            return true;
        }

        if ($enrolid > 0 && self::decided_by_all_groups($enrolid)) {
            return false;
        }

        // No group id is 0, so an application without a group is never in the list.
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
     * @return bool True when the user is not narrowed, or the application's group is theirs on a
     *     method its groups' deciders decide.
     */
    public static function allows(int $courseid, int $userenrolmentid, int $userid, string $capability = self::DECIDE): bool {
        global $DB;

        $groups = self::narrowed_to($courseid, $userid, $capability);
        if ($groups === null) {
            return true;
        }

        // A method only those who see every group decide (C-D20); a missing enrolment is no method.
        $enrolid = (int) $DB->get_field('user_enrolments', 'enrolid', ['id' => $userenrolmentid]);
        if ($enrolid > 0 && self::decided_by_all_groups($enrolid)) {
            return false;
        }

        $groupid = self::application_group($userenrolmentid);

        // No group id is 0, so an application without a group is never in the list.
        return in_array($groupid, $groups, true);
    }

    /**
     * The SQL form of {@see allows()} for a narrowed reader, over a user enrolment aliased "ue".
     *
     * {@see group_where()}, on a method its groups' deciders decide
     * ({@see decided_by_groups_where()}, C-D20): the scope of the listing, its counts and its walk.
     * An operator's filter on one group, which adds to the scope and never narrows a reader, is
     * {@see group_where()} alone.
     *
     * @param array $groupids The reader's groups, as {@see narrowed_to()} returns them.
     * @return array Two elements: the where fragment and its named parameters.
     */
    public static function where(array $groupids): array {
        [$where, $params] = self::group_where($groupids);
        if ($where === '1 = 0') {
            return [$where, $params];
        }

        return ['(' . $where . ' AND ' . self::decided_by_groups_where('ue.enrolid') . ')', $params];
    }

    /**
     * Whether an application belongs to one of the groups, over a user enrolment aliased "ue".
     *
     * True when "the application's group" on the newest record of the user enrolment
     * ({@see application_group_sql()}: the key's group, or the chosen one) is one of the groups.
     * Written with NOT EXISTS rather than a LIMIT inside a subquery, which not every supported
     * database accepts; "newest" is (timecreated, id), as {@see application_group()} orders. An
     * application without a record, or naming no group, matches nothing, because no group id is
     * 0.
     *
     * get_in_or_equal() throws on an empty array, so a reader in no group gets a never-true
     * predicate rather than a query.
     *
     * @param array $groupids Group ids: the reader's, as {@see narrowed_to()} returns them, or a filter's.
     * @return array Two elements: the where fragment and its named parameters.
     */
    public static function group_where(array $groupids): array {
        global $DB;

        if (!$groupids) {
            return ['1 = 0', []];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_values($groupids), SQL_PARAMS_NAMED, 'scopegroup');
        $group = self::application_group_sql('scopesub');

        return [
            "EXISTS (
                SELECT 1
                  FROM {enrol_apply_submission} scopesub
                 WHERE scopesub.userenrolmentid = ue.id
                   AND {$group} {$insql}
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
     * {@see record_group_where()}, on a method its groups' deciders decide
     * ({@see decided_by_groups_where()} over the record's enrolid, C-D20), so a narrowed reader of
     * the report reads none of the records of a method only those who see every group decide. The
     * report's group filter, which adds to the narrowing and never replaces it, is
     * {@see record_group_where()} alone.
     *
     * @param string $alias Alias of {enrol_apply_submission} in the caller's query.
     * @param array $groupids The reader's groups, as {@see narrowed_to()} returns them.
     * @param string $prefix Prefix for the placeholder names, unique in the caller's statement.
     * @return array Two elements: the where fragment and its named parameters.
     */
    public static function record_where(string $alias, array $groupids, string $prefix): array {
        [$where, $params] = self::record_group_where($alias, $groupids, $prefix);
        if ($where === '1 = 0') {
            return [$where, $params];
        }

        return ['(' . $where . ' AND ' . self::decided_by_groups_where("{$alias}.enrolid") . ')', $params];
    }

    /**
     * Whether a durable record belongs to one of the groups, over the record aliased $alias.
     *
     * A record belongs to a group by "the application's group" (its key's group, or the group the
     * applicant chose, {@see application_group_sql()}) and, once approved, also by the groups the
     * approval left the applicant in: true when the application's group is one of the groups, or
     * when the comma-separated joinedgroups names one ({@see submission::record_joined_groups()} writes it).
     * That is what joined, defaults included, where decidedgroups is only the decider's choice and
     * is empty for every approval left on the method's defaults, which a moderator of the group the
     * defaults joined would otherwise never see (V-D10). Unlike {@see where()} every record of the
     * course is a row of the report, so each is judged by its own columns rather than by the newest
     * record of its user enrolment.
     *
     * Where joinedgroups is NULL - not known: a record approved before the column whose
     * memberships the upgrade could not find, or one never approved - decidedgroups is read
     * instead, which is what this narrowing read before the column existed, so a record a reader
     * was shown then is not withdrawn by the upgrade. It never applies once an approval has written
     * joinedgroups, the empty string ("joined none") included: COALESCE picks the first non-NULL.
     *
     * The list is matched as ",<ids>," against "%,<id>,%", one LIKE per group, so that group 1
     * never matches a list naming 11 or 21. Portable on purpose: sql_concat() and sql_like() rather
     * than FIND_IN_SET or a family branch, and each LIKE takes a placeholder of its own, because a
     * named placeholder may appear once per statement. The final '' keeps a record with neither
     * list from making the whole disjunct unknown on one family and false on another.
     *
     * get_in_or_equal() throws on an empty array, so a reader in no group gets a never-true
     * predicate rather than a query, and the report shows core's notice (S4).
     *
     * @param string $alias Alias of {enrol_apply_submission} in the caller's query.
     * @param array $groupids Group ids: the reader's, as {@see narrowed_to()} returns them, or a filter's.
     * @param string $prefix Prefix for the placeholder names, unique in the caller's statement.
     * @return array Two elements: the where fragment and its named parameters.
     */
    public static function record_group_where(string $alias, array $groupids, string $prefix): array {
        global $DB;

        $groupids = array_values(array_map('intval', $groupids));
        if (!$groupids) {
            return ['1 = 0', []];
        }

        [$insql, $params] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, $prefix . 'key');

        $list = $DB->sql_concat("','", "COALESCE({$alias}.joinedgroups, {$alias}.decidedgroups, '')", "','");
        $likes = [];
        foreach ($groupids as $index => $groupid) {
            $name = $prefix . 'joined' . $index;
            $likes[] = $DB->sql_like($list, ':' . $name);
            $params[$name] = '%,' . $groupid . ',%';
        }

        return ['(' . self::application_group_sql($alias) . " {$insql} OR " . implode(' OR ', $likes) . ')', $params];
    }

    /**
     * {@see record_group_where()} for the group a column names, rather than for listed ids.
     *
     * The same predicate, read against a group id column of the caller's query (the report's group
     * facet joins the offered groups and counts each record once per group it belongs to,
     * {@see \enrol_apply\local\reportengine::facet_counts()}): the application's group equal to the
     * column, or ",<column>," found in ",<joined or decided groups>,". The two must answer alike for
     * every record and group (test_the_column_predicate_agrees_with_the_listed_one), so that a
     * group's count is what choosing that group in the filter lists.
     *
     * @param string $alias Alias of {enrol_apply_submission} in the caller's query.
     * @param string $column A group id column of the caller's query.
     * @return string The where fragment; it takes no parameters.
     */
    public static function record_group_column_where(string $alias, string $column): string {
        global $DB;

        $list = $DB->sql_concat("','", "COALESCE({$alias}.joinedgroups, {$alias}.decidedgroups, '')", "','");
        // A substring test rather than LIKE: sql_like() wants a bound pattern, and ",<id>," has no wildcard to match.
        $needle = $DB->sql_concat("','", $DB->sql_cast_to_char($column), "','");

        return '(' . self::application_group_sql($alias) . " = {$column} OR " . $DB->sql_position($needle, $list) . ' > 0)';
    }

    /**
     * The groups of this course the current user may choose for an approval (S6).
     *
     * Every group of the course groups_get_all_groups() lists to $USER, narrowed to their own
     * for a narrowed decider, and to none on a method only those who see every group decide
     * (C-D20), whose applications a narrowed decider decides none of. The group choosers (the
     * queue, the review page, the participants page's bulk form) offer this list and
     * confirm_enrolment() allowlists the posted ids against it, so a control cannot offer a group
     * the decision would drop, and a forged post naming a foreign group joins nothing.
     *
     * @param int $courseid Course id.
     * @param int $enrolid The method the decision belongs to; 0 where the chooser spans methods
     *     (the bulk form), whose decisions the per-row check judges method by method.
     * @return array The {groups} records, keyed by group id.
     */
    public static function choosable_groups(int $courseid, int $enrolid = 0): array {
        global $USER;

        $groups = groups_get_all_groups($courseid);
        $own = self::narrowed_to($courseid, (int) $USER->id);
        if ($own === null) {
            return $groups;
        }
        if ($enrolid > 0 && self::decided_by_all_groups($enrolid)) {
            return [];
        }

        return array_intersect_key($groups, array_flip($own));
    }
}
