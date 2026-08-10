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

/**
 * Privacy Subsystem implementation for enrol_apply.
 *
 * The plugin stores the free-text comment a user submits with an enrolment application,
 * keyed by the user enrolment the application belongs to, so it holds personal data and
 * cannot use the null provider.
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

        return $items;
    }

    /**
     * Course contexts in which the given user has a pending enrolment application.
     *
     * @param int $userid User to look up.
     * @return contextlist The contexts holding data for this user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {enrol_apply_applicationinfo} ai
                  JOIN {user_enrolments} ue ON ue.id = ai.userenrolmentid
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = :enrol
                  JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :contextlevel
                 WHERE ue.userid = :userid";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'enrol' => 'apply',
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Users holding an application in the given context.
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

        $sql = "SELECT ai.id, ai.comment, ue.timecreated, e.courseid
                  FROM {enrol_apply_applicationinfo} ai
                  JOIN {user_enrolments} ue ON ue.id = ai.userenrolmentid
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = :enrol
                 WHERE ue.userid = :userid AND e.courseid {$insql}";

        $applications = $DB->get_records_sql($sql, $params);
        foreach ($applications as $application) {
            $context = context_course::instance($application->courseid);
            writer::with_context($context)->export_data(
                [get_string('privacy:applicationpath', 'enrol_apply')],
                (object) [
                    'comment' => $application->comment,
                    'timecreated' => transform::datetime($application->timecreated),
                ]
            );
        }
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
