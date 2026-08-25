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

use context;
use context_course;
use context_user;
use stdClass;

/**
 * What counts as an application awaiting a decision, and who may decide it.
 *
 * The predicate below is the only definition of "awaiting a decision" in the plugin. It used
 * to be written out in each listing that needed it, which is how the participants-page bulk
 * decisions came to act on rows the queue deliberately excludes: two copies of a filter that
 * is also a correctness boundary drift, and the one that drifted was the newer.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class queue {
    /**
     * The SQL predicate for an application still awaiting a decision.
     *
     * Two clauses, and the second is the one that is easy to leave out. An undecided
     * application is "not active AND has not expired": process_expirations() re-suspends an
     * ACTIVE enrolment whose period ran out when expiredaction is "suspend", and a re-suspended
     * row would otherwise surface as a fresh application from somebody who was in fact approved
     * long ago. Pending and waiting-list rows always carry timeend = 0, because apply() never
     * stamps a period and wait_enrolment() does not touch the dates; only a once-approved row
     * can have one.
     *
     * The user enrolment must be aliased "ue" by the caller, which every caller already does.
     *
     * @return array Two-element array of the where clauses and their named parameters.
     */
    public static function awaiting_decision_where(): array {
        return [
            ['ue.status != :active', '(ue.timeend = 0 OR ue.timeend > :now)'],
            ['active' => ENROL_USER_ACTIVE, 'now' => time()],
        ];
    }

    /**
     * One application, if it is still awaiting a decision.
     *
     * Deliberately one lookup for three different outcomes - never applied, already decided,
     * enrolment gone - because they are the same thing to a reader of this page and telling
     * them apart would answer "does user enrolment N exist?" for anybody who asks. The caller
     * has not been authorised at this point and cannot be: the context to authorise against is
     * derived from this row.
     *
     * @param int $userenrolmentid User enrolment id.
     * @return stdClass|null The application, or null when there is none to decide.
     */
    public static function application(int $userenrolmentid): ?stdClass {
        global $DB;

        [$wheres, $params] = self::awaiting_decision_where();
        $params['ueid'] = $userenrolmentid;
        $params['enrol'] = 'apply';

        $sql = "SELECT ue.id, ue.userid, ue.enrolid, ue.status, ue.timecreated AS applydate,
                       COALESCE(s.comment, ai.comment) AS applycomment,
                       e.courseid, c.fullname AS coursename
                  FROM {user_enrolments} ue
             LEFT JOIN {enrol_apply_applicationinfo} ai ON ai.userenrolmentid = ue.id
             LEFT JOIN {enrol_apply_submission} s ON s.userenrolmentid = ue.id
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                 WHERE ue.id = :ueid AND e.enrol = :enrol AND " . implode(' AND ', $wheres);

        return $DB->get_record_sql($sql, $params) ?: null;
    }

    /**
     * Refuse anybody who may not decide this application, and say which context let them in.
     *
     * The review page used to require the capability in the applicant's own USER context and
     * nowhere else, which made it a mentor's page by accident: measured on both branches, a
     * course teacher holding enrol/apply:manageapplications in the course the application
     * belongs to fails that check, so opening a single application threw at them. The gate is
     * now the plugin's own can_manage_application(), which is the same predicate every
     * decision applies to every row - so the people who may act on an application are exactly
     * the people who may look at one. Nothing new is disclosed: a course teacher already sees
     * every one of these applications, with the same fields, on the queue.
     *
     * require_login($course) is deliberately not called. A mentor holds no course access at
     * all, which is the whole point of that delegation level, so it would refuse the one
     * audience this page has always served.
     *
     * @param stdClass $application Application as application() returns it.
     * @return context The context that granted access, for the page to sit in.
     */
    public static function require_review_access(stdClass $application): context {
        $coursecontext = context_course::instance($application->courseid, IGNORE_MISSING);
        if ($coursecontext && has_capability('enrol/apply:manageapplications', $coursecontext)) {
            // Covers a grant at system level too, which is inherited down to the course.
            return $coursecontext;
        }

        /* The mentor level. require_capability() rather than a bare throw, so a refusal is
           reported exactly as every other refusal in this plugin is. */
        $usercontext = context_user::instance($application->userid, MUST_EXIST);
        require_capability('enrol/apply:manageapplications', $usercontext);

        return $usercontext;
    }
}
