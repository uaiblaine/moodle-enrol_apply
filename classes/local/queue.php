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
use context_system;
use context_user;
use moodle_url;
use stdClass;

/**
 * What counts as an application awaiting a decision, and who may decide it.
 *
 * The predicate below is the only SQL definition of "awaiting a decision" in the plugin - the
 * approval queue, the submitted-comments listing, the review lookup and the retention sweep
 * all read it. It used to be written out in each of them, which is how the participants-page
 * bulk decisions came to act on rows the queue deliberately excludes: two copies of a filter
 * that is also a correctness boundary drift, and the one that drifted was the newer.
 *
 * There is one deliberate second expression of the same rule, and it is not SQL:
 * is_awaiting_decision() below applies it to a {user_enrolments} row that core has already
 * loaded and that never reaches a query - the objects the participants-page driver hands to
 * a bulk decision, and the one row behind each of that page's action icons. It lives here,
 * next to the SQL, because it used to live in classes/bulk/ and the icon would have made a
 * third copy of a filter that is also a correctness boundary. Keep the two in step by hand;
 * there is no third.
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
     * The same predicate, applied to a user enrolment row core has already loaded.
     *
     * The twin of awaiting_decision_where() above, and the only other place the rule is
     * written out. Both callers are on core's participants page, which hands over
     * {user_enrolments} rows rather than running a query of this plugin's: the bulk
     * decisions, which get the selection, and get_user_enrolment_actions(), which gets one
     * row per action icon it is asked to build.
     *
     * The second clause is the one that is easy to leave out and the reason this is not
     * simply "not active". Under an expiredaction of suspend, process_expirations()
     * re-suspends an ACTIVE enrolment whose period ran out, so somebody approved and enrolled
     * long ago comes back reading exactly like a fresh application - and the participants
     * page is where that row is most visible, since core paints its own status badge from the
     * same status value with none of this context. Under the shipped default of
     * ENROL_EXT_REMOVED_KEEP the row stays active and the first clause is what excludes it.
     *
     * It is written as "= 0", matching the SQL, rather than the "> 0 && <= now" the bulk copy
     * used, and the two disagree on exactly one input: a NEGATIVE timeend, which the old
     * object form reported as awaiting a decision and which the SQL has always excluded.
     * Measured over {0, -1, -86400, now, now +/- 10}; -1 and -86400 are the only rows that
     * move, and they move onto the side the queue was already on. Nothing this plugin writes
     * produces one - the column is NOT NULL and core stamps 0 or a real timestamp - so it is
     * a correction rather than a behaviour change, but it is a correction and is recorded
     * here rather than left for somebody to rediscover from a diff.
     *
     * @param stdClass $userenrolment A {user_enrolments} row, carrying at least status and timeend.
     * @return bool True when this enrolment is an application still awaiting a decision.
     */
    public static function is_awaiting_decision(stdClass $userenrolment): bool {
        if ((int) $userenrolment->status === ENROL_USER_ACTIVE) {
            return false;
        }

        $timeend = (int) $userenrolment->timeend;

        return $timeend === 0 || $timeend > time();
    }

    /**
     * One application, if it is still awaiting a decision.
     *
     * Deliberately one lookup for three different outcomes - never applied, already decided,
     * enrolment gone - because they are the same thing to somebody who followed a link that has
     * gone stale, which is who reaches this. It is worth being precise about what that does and
     * does not buy, because an earlier draft of this docblock claimed more.
     *
     * It does NOT make the page silent about whether an id names a live application. Measured
     * on 5.2 as a logged-in user with no claim on the course: an id with nothing behind it
     * renders the "no application" page with HTTP 200, while a pending one is refused by
     * require_review_access() below and comes back 500. So the page still answers "is user
     * enrolment N a pending application?" - as every Moodle page that refuses by capability
     * answers the same question about its own object, and the refusal names neither the
     * applicant nor the course.
     *
     * What it does buy is that a reader who IS entitled to the answer cannot tell a decided
     * application from a deleted one, and neither can anybody else. The caller has not been
     * authorised at this point and cannot be: the context to authorise against is derived from
     * this row.
     *
     * The profile snapshot comes from the durable record only, with no fallback: the
     * applicationinfo row has never held one, so an application that predates that record shows
     * its comment and no snapshot rather than an empty one that looks like "they filled nothing
     * in". Null and the empty string are the same thing to read_snapshot().
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
                       COALESCE(s.comment, ai.comment) AS applycomment, s.userinfodata AS snapshot,
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
     * Which queue this operator is working in, and where that queue lives.
     *
     * The review page is reachable by three audiences and each of them has a different queue
     * behind it, so "the next application" has no meaning until this is settled. It is settled
     * from what the operator may OPEN, never from the request: manage.php tests userenrol
     * before id, so on the review path the id parameter is read into a variable and then never
     * authorised and never used. A walk built on it would let a request parameter choose which
     * applications are enumerated - and an earlier plan for this navigation said to carry the
     * scope that way, on the belief that manage.php had already authorised it.
     *
     * Deriving it instead buys a property worth more than the parameter: every application the
     * walk can reach is one this operator may decide, by construction rather than by a
     * per-candidate check. The three scopes below are the three levels can_manage_application()
     * accepts, in the same order:
     *  - the instance queue, when the operator may open it, where every row is in that one
     *    course and the course-context check passes for all of them;
     *  - the site-wide queue, where the system-context check passes for all of them;
     *  - the operator's own mentees, which get_mentees() enumerates by confirming the
     *    capability in each candidate's user context - the same check, one candidate at a time.
     *
     * So mod_book's skip-the-candidates-that-fail loop, which is the shape a per-row gate
     * usually needs, is not needed here and is deliberately absent: an unreachable guard no
     * test can hold reads as protection while proving nothing. What holds the property instead
     * is a test per scope.
     *
     * Every scope also CONTAINS the application it was derived for, which is a second property
     * and not the same one. neighbours() compares the anchor's (timecreated, ue.id) against the
     * scoped set; anchored outside that set it returns insertion-point neighbours instead of
     * neighbours, so the application on screen is reachable from neither of its own links and
     * "next" leads somewhere the operator cannot get back from. That is exactly the dead end
     * this navigation exists to remove, and it was reachable: the mentee branch was taken
     * whenever the first two failed and the operator mentored ANYBODY, including for an
     * application belonging to none of their mentees. The first two branches contain the anchor
     * by construction; the third has to test for it.
     *
     * This is also the one answer to "where does a decision send the operator back to", which
     * manage.php used to work out separately with can_access_course() alone. That was measurably
     * wrong for a mentor who is enrolled in the course as anything else: can_access_course()
     * was true, so the decision redirected to manage.php?id=, whose require_capability() then
     * threw at them - a successful decision reported as an exception.
     *
     * @param stdClass $application Application as application() returns it.
     * @param stdClass $instance Enrol instance the application under review belongs to.
     * @return stdClass Scope carrying: enrolid, the instance to restrict to or 0 for none;
     *                  mentees, applicant ids to restrict to or null for none; and url, the
     *                  queue's own page.
     */
    public static function scope(stdClass $application, stdClass $instance): stdClass {
        $course = get_course($instance->courseid);

        /* The FOURTH argument is the whole test, and an earlier draft of this comment claimed
           the three-argument form was "the pair manage.php?id= itself demands". It is not.
           can_access_course() defaults $onlyactive to false and reaches is_enrolled() with it,
           so a SUSPENDED or EXPIRED enrolment counts as access - while require_login($course),
           which manage.php?id= calls before require_capability(), refuses both. Measured on 5.1
           and 5.2 over five operators: with $onlyactive true the two agree on every one of them
           (active teacher and category manager allowed, suspended, expired and unenrolled
           category teacher refused); with it false they disagree on the suspended and the
           expired one, who kept the capability and were sent to a queue that bounced them to
           the course enrolment page after their decision had already been applied. */
        if (can_access_course($course, null, 'enrol/apply:manageapplications', true)) {
            return (object) [
                'enrolid' => (int) $instance->id,
                'mentees' => null,
                'url' => new moodle_url('/enrol/apply/manage.php', ['id' => (int) $instance->id]),
            ];
        }

        if (has_capability('enrol/apply:manageapplications', context_system::instance())) {
            return (object) [
                'enrolid' => 0,
                'mentees' => null,
                'url' => new moodle_url('/enrol/apply/manage.php'),
            ];
        }

        /* Only when this application is one of theirs. Mentoring somebody does not make an
           arbitrary application walkable, and the membership test is what keeps the anchor
           inside the set - see the note above for what an anchor outside it produces. */
        $mentees = applications::get_mentees();
        if (in_array((int) $application->userid, $mentees, true)) {
            return (object) [
                'enrolid' => 0,
                'mentees' => $mentees,
                'url' => new moodle_url('/enrol/apply/manage.php'),
            ];
        }

        /* No queue at all, and this is reachable rather than defensive. The plainest route is
           the capability held at a course context through a category role, by somebody who is
           not enrolled: that passes can_manage_application() and fails every test above, on a
           VISIBLE course - measured, and worth stating because an earlier draft of this comment
           said the course had to be hidden. Hiding it is one sufficient condition among several,
           not a necessary one. A teacher whose own enrolment has been suspended lands here too,
           and so does a mentor looking at an application none of their mentees made.

           The parameterless queue would refuse the first of those, so sending them there after
           a decision would report a success as an exception - the same defect this method exists
           to remove, arrived at from the other end. An empty mentee list is the marker; nothing
           can be walked from it. */
        return (object) [
            'enrolid' => 0,
            'mentees' => [],
            'url' => destination::home_page_url(),
        ];
    }

    /**
     * The applications either side of this one, in the queue this operator is working in.
     *
     * Resolved in SQL, one statement per direction with a single row taken, rather than by
     * materialising the queue and looking for the current row in it - which is what both
     * gradebook reports do. The comparison is not close: materialising runs this same predicate
     * with no LIMIT, hands every row to PHP and hydrates a user record for each, and the
     * site-wide scope spans every course. This runs it twice and hands over two rows.
     *
     * What the plan actually depends on, measured with EXPLAIN ANALYZE on m502 rather than
     * assumed: {user_enrolments} carries indexes on enrolid and userid and NONE on status or
     * timecreated, so the instance scope (e.id) and the mentee scope (ue.userid) each reach
     * their rows through one, while the site-wide scope has only e.enrol = 'apply' to be
     * selective with - few instances, joined into {user_enrolments} on the enrolid index. On a
     * small site the planner ignores all of that and sequentially scans the 313-row table in
     * 0.1ms with a top-N heapsort, which is the right answer at that size; the point of the
     * shape is that the sort and the LIMIT stay in the database whatever it decides.
     *
     * The walk is pinned to (timecreated ASC, ue.id ASC) - the table's own default sort, with
     * the unique final key that keeps a tied group from trading places. It is pinned because a
     * server-resolved neighbour has no meaning otherwise: the table is user-sortable and
     * flexible_table keeps that choice in $SESSION->flextable['enrol_apply_manage_table'] (a
     * user preference only when is_persistent(true) is called, which this table does not do),
     * so "next" would otherwise depend on state this page cannot see. It follows that the walk
     * can disagree with a re-sorted queue. That divergence is documented rather than silent,
     * and the link names the applicant it leads to, so the operator reads where they are going
     * before they go there.
     *
     * The walk does NOT honour the initials bar, and this is a decision rather than an
     * oversight. The queue is rendered with out(50, true), and query_db() appends
     * get_sql_where() - firstname LIKE 'x%' - so an operator who has picked a letter is looking
     * at a narrower set than the predicate below describes. Three things settle it. Turning the
     * bar off would not close the gap: set_initials_preferences() runs from setup() whatever
     * the bar argument says, so the filter still applies from a stale preference or a crafted
     * request parameter, and only the CONTROL disappears. Honouring it would make the page
     * depend on session state it does not render, so a bookmarked or emailed review link would
     * silently lose its neighbours because of a letter clicked days earlier - invisible, which
     * is the failure mode this repository treats as the defect. Not honouring it fails visibly
     * instead: the next link names an applicant outside the filtered letter, before the click.
     * And it keeps one rule for the whole preference blob, rather than pinning the sort half
     * and obeying the filter half.
     *
     * @param stdClass $application Application as application() returns it.
     * @param stdClass $scope Scope as scope() returns it.
     * @return array Two keys, previous and next, each an application record or null.
     */
    public static function neighbours(stdClass $application, stdClass $scope): array {
        return [
            'previous' => self::neighbour($application, $scope, false),
            'next' => self::neighbour($application, $scope, true),
        ];
    }

    /**
     * The one application before or after this one in the pinned order.
     *
     * @param stdClass $application Application as application() returns it.
     * @param stdClass $scope Scope as scope() returns it.
     * @param bool $forward True for the next application, false for the previous one.
     * @return stdClass|null Record carrying the user enrolment id, the applicant id and their
     *                       name fields, or null when there is nothing that way.
     */
    private static function neighbour(stdClass $application, stdClass $scope, bool $forward): ?stdClass {
        global $DB;

        [$wheres, $params] = self::awaiting_decision_where();
        $wheres[] = 'e.enrol = :enrol';
        $params['enrol'] = 'apply';

        if ($scope->enrolid) {
            // The listing's own clause, character for character: e.id, not the equivalent ue.enrolid.
            $wheres[] = 'e.id = :enrolid';
            $params['enrolid'] = $scope->enrolid;
        }

        if ($scope->mentees !== null) {
            if (!$scope->mentees) {
                /* Nothing to walk, and this is the scope() branch for an operator who can open
                   no queue - not a defensive check. get_in_or_equal() throws on an empty array,
                   so the listing spells the same case as "1 = 0" to keep its SQL valid; here
                   there is no query to keep valid, so there is nothing to build. */
                return null;
            }
            [$insql, $inparams] = $DB->get_in_or_equal($scope->mentees, SQL_PARAMS_NAMED, 'mentee');
            $wheres[] = "ue.userid {$insql}";
            $params += $inparams;
        }

        /* Strictly past the current row in the pinned order, which needs the timestamp twice -
           once against the timestamp and once inside the tie-break. Two NAMES bound to the one
           value, never one name used twice: fix_sql_params() counts occurrences with
           preg_match_all() and throws duplicateparaminsql when that total differs from the
           parameter array. */
        $comparison = $forward ? '>' : '<';
        $wheres[] = "(ue.timecreated {$comparison} :walkafter
                      OR (ue.timecreated = :walkat AND ue.id {$comparison} :walkid))";
        $params['walkafter'] = (int) $application->applydate;
        $params['walkat'] = (int) $application->applydate;
        $params['walkid'] = (int) $application->id;

        $direction = $forward ? 'ASC' : 'DESC';
        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;

        /* The listing's own INNER joins, including the one to {course}, which this query reads
           nothing from. It is here so that the walk's FROM is the listing's FROM: neither can
           drop a row on data this plugin can produce - {enrol}.courseid is declared foreign to
           course.id, though XMLDB creates an index for it rather than a constraint - and where
           that ever stopped being true, a walk that had dropped the join would offer a
           neighbour the queue does not list. The listing's two comment joins are omitted
           because nothing here reads a comment: a LEFT join cannot REMOVE a row, so leaving
           them out cannot let anything through. It could in principle multiply one - measured,
           applicationinfo.userenrolmentid is declared foreign-unique and cannot, while
           submission.userenrolmentid is a plain index and could - but that is a property of
           the listing, and taking one row per statement is not affected by it either way. */
        $sql = "SELECT ue.id, ue.userid {$namefields}
                  FROM {user_enrolments} ue
                  JOIN {user} u ON u.id = ue.userid
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                 WHERE " . implode(' AND ', $wheres) . "
              ORDER BY ue.timecreated {$direction}, ue.id {$direction}";

        $records = $DB->get_records_sql($sql, $params, 0, 1);

        return $records ? reset($records) : null;
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
        $usercontext = context_user::instance($application->userid, MUST_EXIST);

        /* The gate is can_manage_application() itself and not a second reading of the same
           three levels. Written out again here it would be a copy of an authorisation
           boundary, which is the shape this class exists to remove from the predicate above -
           and the two would agree only until somebody added an override, a prohibit or a
           fourth level to one of them. */
        if (!enrol_get_plugin('apply')->can_manage_application((int) $application->courseid, (int) $application->userid)) {
            // Reported exactly as every other refusal in this plugin is.
            require_capability('enrol/apply:manageapplications', $usercontext);
        }

        /* Which context the PAGE then sits in is a rendering question, not an authorisation
           one - the decision has already been taken. The course context where the operator
           holds the capability there, because that is where the group and role choosers, the
           filters and the file serving belong; the applicant's own context otherwise, which
           is the only one a mentor has. */
        $coursecontext = context_course::instance($application->courseid, IGNORE_MISSING);
        if ($coursecontext && has_capability('enrol/apply:manageapplications', $coursecontext)) {
            return $coursecontext;
        }

        return $usercontext;
    }
}
