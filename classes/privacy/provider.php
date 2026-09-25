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

namespace enrol_apply\privacy;

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use enrol_apply\local\submission;

/**
 * Privacy Subsystem implementation for enrol_apply.
 *
 * Two tables and two roles. enrol_apply_applicationinfo holds the comment submitted with an
 * application still awaiting a decision, keyed by the user enrolment. enrol_apply_submission
 * is the durable record of the same application, which outlives the enrolment, the enrolment
 * method and every decision taken on it, and which names two people: the applicant in userid
 * and whoever decided in decidedby. Both roles are exported and both are erased.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data held by this plugin.
     *
     * @param collection $items Collection to add the metadata to.
     * @return collection The collection with this plugin's metadata added.
     */
    public static function get_metadata(collection $items): collection {
        $items->add_database_table(
            'enrol_apply_applicationinfo',
            [
                'userenrolmentid' => 'privacy:metadata:enrol_apply_applicationinfo:userenrolmentid',
                'comment' => 'privacy:metadata:enrol_apply_applicationinfo:comment',
            ],
            'privacy:metadata:enrol_apply_applicationinfo'
        );

        $items->add_database_table(
            'enrol_apply_submission',
            [
                'courseid' => 'privacy:metadata:enrol_apply_submission:courseid',
                'userid' => 'privacy:metadata:enrol_apply_submission:userid',
                'enrolid' => 'privacy:metadata:enrol_apply_submission:enrolid',
                'userenrolmentid' => 'privacy:metadata:enrol_apply_submission:userenrolmentid',
                'comment' => 'privacy:metadata:enrol_apply_submission:comment',
                'userinfodata' => 'privacy:metadata:enrol_apply_submission:userinfodata',
                'decidedgroups' => 'privacy:metadata:enrol_apply_submission:decidedgroups',
                'decidedrole' => 'privacy:metadata:enrol_apply_submission:decidedrole',
                'status' => 'privacy:metadata:enrol_apply_submission:status',
                'outcomemessage' => 'privacy:metadata:enrol_apply_submission:outcomemessage',
                'decisionnote' => 'privacy:metadata:enrol_apply_submission:decisionnote',
                'timecreated' => 'privacy:metadata:enrol_apply_submission:timecreated',
                'timedecided' => 'privacy:metadata:enrol_apply_submission:timedecided',
                'decidedby' => 'privacy:metadata:enrol_apply_submission:decidedby',
            ],
            'privacy:metadata:enrol_apply_submission'
        );

        return $items;
    }

    /**
     * Course contexts in which the given user applied, or decided on somebody's application.
     *
     * @param int $userid User to look up.
     * @return contextlist The contexts holding data for this user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {enrol_apply_applicationinfo} ai
                  JOIN {user_enrolments} ue ON ue.id = ai.userenrolmentid
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = :enrol
                  JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :contextlevel
                 WHERE ue.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'enrol' => 'apply',
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        // Both roles. Two names bound to one value: fix_sql_params() rejects a reused placeholder.
        $sql = "SELECT ctx.id
                  FROM {enrol_apply_submission} s
                  JOIN {context} ctx ON ctx.instanceid = s.courseid AND ctx.contextlevel = :contextlevel
                 WHERE s.userid = :userid OR s.decidedby = :decidedby";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
            'decidedby' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Users holding an application, or named as its decider, in the given context.
     *
     * @param userlist $userlist Userlist to add the matching users to.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }

        $sql = "SELECT ue.userid
                  FROM {enrol_apply_applicationinfo} ai
                  JOIN {user_enrolments} ue ON ue.id = ai.userenrolmentid
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = :enrol
                 WHERE e.courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, ['enrol' => 'apply', 'courseid' => $context->instanceid]);

        /* No "<> 0" filter is needed although decidedby is 0 until somebody decides:
           userlist::add_from_sql() joins the result to {user}, and no user has id 0. */
        $userlist->add_from_sql(
            'userid',
            "SELECT s.userid FROM {enrol_apply_submission} s WHERE s.courseid = :courseid",
            ['courseid' => $context->instanceid]
        );

        $userlist->add_from_sql(
            'decidedby',
            "SELECT s.decidedby FROM {enrol_apply_submission} s WHERE s.courseid = :courseid",
            ['courseid' => $context->instanceid]
        );
    }

    /**
     * Export the applications of the given user in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts to export.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $user = $contextlist->get_user();
        $courseids = self::get_course_ids($contextlist);
        if (!$courseids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        $params['enrol'] = 'apply';
        $params['userid'] = $user->id;

        $sql = "SELECT ai.id, ai.comment, ue.timecreated, e.courseid, e.id AS enrolid
                  FROM {enrol_apply_applicationinfo} ai
                  JOIN {user_enrolments} ue ON ue.id = ai.userenrolmentid
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = :enrol
                 WHERE ue.userid = :userid AND e.courseid {$insql}";

        foreach ($DB->get_records_sql($sql, $params) as $application) {
            $context = context_course::instance($application->courseid);
            writer::with_context($context)->export_data(
                self::application_subcontext((int) $application->enrolid),
                (object) [
                    'comment' => $application->comment,
                    'timecreated' => transform::datetime($application->timecreated),
                ]
            );
        }

        self::export_submissions($contextlist, $courseids, true);
        self::export_submissions($contextlist, $courseids, false);
    }

    /**
     * Export the durable application records naming the user in one of the two roles.
     *
     * @param approved_contextlist $contextlist Approved contexts to export.
     * @param array $courseids Course ids of those contexts.
     * @param bool $asapplicant True to export the rows the user applied on, false for the ones they decided.
     * @return void
     */
    protected static function export_submissions(
        approved_contextlist $contextlist,
        array $courseids,
        bool $asapplicant
    ) {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        $params['userid'] = $contextlist->get_user()->id;
        $column = $asapplicant ? 'userid' : 'decidedby';

        $sql = "SELECT s.*
                  FROM {enrol_apply_submission} s
                 WHERE s.{$column} = :userid AND s.courseid {$insql}";

        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $context = context_course::instance($row->courseid, IGNORE_MISSING);
            if (!$context) {
                // Should be unreachable: the context is what put this course in the list.
                continue;
            }

            $export = (object) [
                'role' => $asapplicant
                    ? get_string('privacy:roleapplicant', 'enrol_apply')
                    : get_string('privacy:roledecider', 'enrol_apply'),
                'enrolid' => (int) $row->enrolid,
                'status' => submission::status_label((int) $row->status),
                'timecreated' => transform::datetime($row->timecreated),
                'timedecided' => $row->timedecided ? transform::datetime($row->timedecided) : null,
            ];

            /* The decision (message to the applicant, groups, role) goes to both subjects: the
               decider is entitled to a record of what they decided, the applicant to a record of
               what was decided about them. */
            $export->outcomemessage = trim((string) $row->outcomemessage) !== ''
                ? $row->outcomemessage
                : null;

            /* The decider's note goes to both subjects too. No page shows it to the applicant,
               but it is a member of staff's assessment of them, which is what a subject access
               request exists to reach. */
            $export->decisionnote = trim((string) $row->decisionnote) !== ''
                ? $row->decisionnote
                : null;
            $export->decidedgroups = self::group_names((string) $row->decidedgroups, $context);
            $export->decidedrole = self::role_name((int) $row->decidedrole);

            /* The comment and the profile snapshot are the applicant's own data and go into the
               applicant's export only; handing them to the decider would disclose a third
               party's data. */
            if ($asapplicant) {
                $export->comment = $row->comment;
                $export->submittedfields = submission::read_snapshot($row->userinfodata);
            }

            $subcontext = self::submission_subcontext((int) $row->id, $asapplicant);
            writer::with_context($context)->export_data($subcontext, $export);
        }
    }

    /**
     * The names of the groups a decider chose, for a data export.
     *
     * The column stores group ids, which mean nothing to the subject, so they are resolved to
     * names. A group deleted since the decision is left out rather than reported as a bare id.
     *
     * Names are taken in their plain spelling (escape => false): the export file is not HTML,
     * so the escaped spelling would reach the subject as the literal "R&amp;D".
     *
     * @param string $decidedgroups Comma-separated group ids as the record stores them.
     * @param context $context Course context the groups belong to.
     * @return array Group names, empty when none were recorded or none still exist.
     */
    protected static function group_names(string $decidedgroups, context $context): array {
        global $DB;

        $ids = array_filter(array_map('intval', explode(',', $decidedgroups)));
        if (!$ids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'gid');
        $groups = $DB->get_records_select('groups', "id {$insql}", $params, '', 'id, name');

        $names = [];
        foreach ($groups as $group) {
            $names[] = format_string($group->name, true, ['context' => $context, 'escape' => false]);
        }

        return $names;
    }

    /**
     * The name of the role a decider chose, for a data export.
     *
     * role_get_name() is not used for a named role because its spelling is mixed: a non-empty
     * role.name comes back through format_string() and is escaped, while an empty one (every
     * standard role) comes back from get_string() and is not. Both branches here return the
     * plain spelling, as the export file is not HTML.
     *
     * The course alias is deliberately not applied: the record holds the role that was assigned,
     * not what one course chose to call it.
     *
     * @param int $roleid Role id as the record stores it, 0 when the instance default applied.
     * @return string|null The role name, or null when none was recorded or the role is gone.
     */
    protected static function role_name(int $roleid): ?string {
        global $DB;

        if (!$roleid) {
            return null;
        }

        $role = $DB->get_record('role', ['id' => $roleid], 'id, name, shortname');
        if (!$role) {
            return null;
        }

        if (trim((string) $role->name) !== '') {
            // The system context, because that is the one core filters a role name against.
            return format_string($role->name, true, [
                'context' => \context_system::instance(),
                'escape' => false,
            ]);
        }

        // Empty name: role_get_name() returns the localised get_string(), already unescaped.
        return role_get_name($role, null, ROLENAME_ORIGINAL);
    }

    /**
     * Where an application belonging to one enrolment method is written in the export.
     *
     * The enrolment method id is part of the path so that a course with two apply methods
     * exports both applications instead of the second overwriting the first.
     *
     * @param int $enrolid Enrol instance the application was submitted to.
     * @return array Subcontext path.
     */
    protected static function application_subcontext(int $enrolid): array {
        return [
            get_string('privacy:applicationpath', 'enrol_apply'),
            get_string('privacy:methodpath', 'enrol_apply', $enrolid),
        ];
    }

    /**
     * Where a durable application record is written in the export.
     *
     * Both discriminators are needed. The role, because one person can be the applicant on one
     * record and the decider on another in the same course. The record id, because one user can
     * hold several records per course and method (cancelling and re-applying produces a new
     * one), and a path without it would export only the last.
     *
     * @param int $recordid Id of the enrol_apply_submission row.
     * @param bool $asapplicant True for the applicant's own record, false for one they decided.
     * @return array Subcontext path.
     */
    protected static function submission_subcontext(int $recordid, bool $asapplicant): array {
        return [
            get_string('privacy:trailpath', 'enrol_apply'),
            $asapplicant
                ? get_string('privacy:roleapplicant', 'enrol_apply')
                : get_string('privacy:roledecider', 'enrol_apply'),
            get_string('privacy:recordpath', 'enrol_apply', $recordid),
        ];
    }

    /**
     * Delete every application recorded in the given context.
     *
     * @param context $context Context to purge.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if (!$context instanceof context_course) {
            return;
        }

        $sql = "SELECT ai.id
                  FROM {enrol_apply_applicationinfo} ai
                  JOIN {user_enrolments} ue ON ue.id = ai.userenrolmentid
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = :enrol
                 WHERE e.courseid = :courseid";

        $ids = $DB->get_fieldset_sql($sql, ['enrol' => 'apply', 'courseid' => $context->instanceid]);
        self::delete_by_id($ids);

        $DB->delete_records('enrol_apply_submission', ['courseid' => $context->instanceid]);
    }

    /**
     * Delete the applications of one user in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts to purge.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $courseids = self::get_course_ids($contextlist);
        if (!$courseids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        $params['enrol'] = 'apply';
        $params['userid'] = $contextlist->get_user()->id;

        $sql = "SELECT ai.id
                  FROM {enrol_apply_applicationinfo} ai
                  JOIN {user_enrolments} ue ON ue.id = ai.userenrolmentid
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = :enrol
                 WHERE ue.userid = :userid AND e.courseid {$insql}";

        self::delete_by_id($DB->get_fieldset_sql($sql, $params));
        self::delete_submissions($courseids, [$contextlist->get_user()->id]);
    }

    /**
     * Delete the applications of several users in one context.
     *
     * @param approved_userlist $userlist Approved users to purge.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }

        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        $params['enrol'] = 'apply';
        $params['courseid'] = $context->instanceid;

        $sql = "SELECT ai.id
                  FROM {enrol_apply_applicationinfo} ai
                  JOIN {user_enrolments} ue ON ue.id = ai.userenrolmentid
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = :enrol
                 WHERE e.courseid = :courseid AND ue.userid {$insql}";

        self::delete_by_id($DB->get_fieldset_sql($sql, $params));
        self::delete_submissions([$context->instanceid], $userids);
    }

    /**
     * Erase the given users from the durable application records of the given courses.
     *
     * The two roles are erased differently. As the applicant, the record is theirs and is
     * deleted whole: erasure wins over keeping the trail.
     *
     * As the decider, only their id goes. The record carries the applicant's comment and
     * profile snapshot, and deleting it would destroy a third party's data. Zeroing decidedby
     * matches what {@see submission::pseudonymise()} does on course deletion.
     *
     * @param array $courseids Courses to erase within.
     * @param array $userids Users to erase, in either role.
     * @return void
     */
    protected static function delete_submissions(array $courseids, array $userids) {
        global $DB;

        if (!$courseids || !$userids) {
            return;
        }

        [$courseinsql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        [$userinsql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');

        $DB->delete_records_select(
            'enrol_apply_submission',
            "courseid {$courseinsql} AND userid {$userinsql}",
            $courseparams + $userparams
        );

        [$deciderinsql, $deciderparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'decider');
        $DB->set_field_select(
            'enrol_apply_submission',
            'decidedby',
            0,
            "courseid {$courseinsql} AND decidedby {$deciderinsql}",
            $courseparams + $deciderparams
        );
    }

    /**
     * Course ids of the course contexts held in a context list.
     *
     * @param approved_contextlist $contextlist Context list to read.
     * @return array Array of course ids.
     */
    protected static function get_course_ids(approved_contextlist $contextlist): array {
        $courseids = [];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_course) {
                $courseids[] = $context->instanceid;
            }
        }

        return $courseids;
    }

    /**
     * Delete application info rows by id.
     *
     * @param array $ids Row ids to delete.
     * @return void
     */
    protected static function delete_by_id(array $ids) {
        global $DB;

        if (!$ids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'id');
        $DB->delete_records_select('enrol_apply_applicationinfo', "id {$insql}", $params);
    }
}
