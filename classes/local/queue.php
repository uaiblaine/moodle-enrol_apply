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
 * awaiting_decision_where() is the only SQL definition of "awaiting a decision" in the plugin:
 * the approval queue, the submitted-comments listing, the review lookup and the retention sweep
 * all read it, so a filter that is also a correctness boundary cannot drift between copies.
 *
 * is_awaiting_decision() is the one deliberate second expression of the rule, for
 * {user_enrolments} rows core's participants page has already loaded and that never reach a
 * query of this plugin's: the selection handed to a bulk decision, and the row behind each of
 * that page's action icons. Keep the two in step by hand; there is no third.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class queue {
    /** @var int How many of an applicant's earlier applications the review page lists. */
    public const PRIOR_APPLICATIONS_SHOWN = 5;

    /**
     * The SQL predicate for an application still awaiting a decision.
     *
     * An undecided application is "not active AND has not expired", and the second clause is the
     * easy one to leave out: process_expirations() re-suspends an ACTIVE enrolment whose period
     * ran out when expiredaction is "suspend", and that row would otherwise surface as a fresh
     * application from somebody approved long ago.
     *
     * Nothing this plugin writes puts a period on a pending or waiting-list row - apply() stamps
     * none, and wait_enrolment() clears any the row was carrying - but that is a property of the
     * writers, not of the table: enrol_apply_plugin::restore_user_enrolment() passes an archived
     * timeend through verbatim, so a foreign archive can produce any value.
     *
     * The caller must alias the user enrolment "ue".
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
     * The twin of {@see awaiting_decision_where()}; keep the two in step. Both callers are on
     * core's participants page, which hands over {user_enrolments} rows rather than running a
     * query of this plugin's: the bulk decisions, which get the selection, and
     * get_user_enrolment_actions(), which gets one row per action icon it is asked to build.
     *
     * The timeend clause matters most there, because core paints its own status badge from the
     * same status value: under an expiredaction of suspend, somebody approved long ago comes back
     * reading exactly like a fresh application. Under the shipped default of
     * ENROL_EXT_REMOVED_KEEP the row stays active and the first clause excludes it.
     *
     * Written as the SQL is - timeend 0 or in the future - so a negative timeend counts as
     * expired here too.
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
     * One lookup for three outcomes - never applied, already decided, enrolment gone - because
     * they are the same thing to somebody following a stale link, which is who reaches this.
     *
     * That stops anybody telling a decided application from a deleted one. It does not hide
     * whether an id names a pending application: the page refuses a pending one through
     * require_review_access() and renders "no application" for the rest, as any Moodle page
     * that refuses by capability answers about its own object, and the refusal names neither
     * the applicant nor the course. The caller cannot be authorised before this runs: the
     * context to authorise against is derived from this row.
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

        /* The decision columns come from a table this query already left joins, so they cost
           nothing. They let the review page say who deferred an application, when, and what
           they wrote to the applicant; s.id is what links the page to the durable record. */
        $sql = "SELECT ue.id, ue.userid, ue.enrolid, ue.status, ue.timecreated AS applydate,
                       COALESCE(s.comment, ai.comment) AS applycomment, s.userinfodata AS snapshot,
                       s.id AS submissionid, s.status AS recordstatus, s.timedecided,
                       s.decidedby, s.outcomemessage, s.decisionnote,
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
     * The review page serves three audiences with a different queue behind each, so "the next
     * application" means nothing until this is settled. It is derived from what the operator may
     * OPEN, never from the request: manage.php tests userenrol before id, so on the review path
     * the id parameter is never authorised, and a walk built on it would let a request parameter
     * choose which applications are enumerated.
     *
     * The three scopes are the three levels can_manage_application() accepts, so every
     * application the walk can reach is one this operator may decide, by construction rather
     * than by a per-candidate check:
     *  - the instance queue, when the operator may open it, where every row is in that one
     *    course and the course-context check passes for all of them;
     *  - the site-wide queue, where the system-context check passes for all of them;
     *  - the operator's own mentees, which get_mentees() enumerates by confirming the
     *    capability in each candidate's user context - the same check, one candidate at a time.
     * mod_book's skip-the-candidates-that-fail loop is therefore not needed; a test per scope
     * holds the property instead.
     *
     * Every scope must also CONTAIN the application it was derived for. neighbours() compares the
     * anchor's (timecreated, ue.id) against the scoped set; anchored outside it, it returns
     * insertion-point neighbours, so "next" leads somewhere with no link back to the application
     * on screen. The first two branches contain the anchor by construction; the mentee branch
     * tests membership.
     *
     * The same answer decides where a decision sends the operator back to, so that redirect never
     * lands on a queue that would refuse them.
     *
     * @param stdClass $application Application as application() returns it.
     * @param stdClass $instance Enrol instance the application under review belongs to.
     * @return stdClass Scope carrying: enrolid, the instance to restrict to or 0 for none;
     *                  mentees, applicant ids to restrict to or null for none; hasqueue, whether
     *                  url names a queue this operator may actually open; and url, that queue's
     *                  own page, or the home page for an operator who may open none.
     */
    public static function scope(stdClass $application, stdClass $instance): stdClass {
        $course = get_course($instance->courseid);

        /* All four arguments matter. The capability keeps out a mentor who is merely enrolled
           in the course, whom manage.php?id= would refuse after their decision was applied.
           $onlyactive set to true makes this agree with require_login($course), which manage.php?id=
           calls before require_capability(): with the default false, is_enrolled() counts a
           suspended or expired enrolment as access, and such a teacher would be sent to a queue
           that bounces them to the course enrolment page. */
        if (can_access_course($course, null, 'enrol/apply:manageapplications', true)) {
            return (object) [
                'enrolid' => (int) $instance->id,
                'mentees' => null,
                'hasqueue' => true,
                'url' => new moodle_url('/enrol/apply/manage.php', ['id' => (int) $instance->id]),
            ];
        }

        if (has_capability('enrol/apply:manageapplications', context_system::instance())) {
            return (object) [
                'enrolid' => 0,
                'mentees' => null,
                'hasqueue' => true,
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
                'hasqueue' => true,
                'url' => new moodle_url('/enrol/apply/manage.php'),
            ];
        }

        /* No queue at all, which is reachable rather than defensive: the capability held at a
           course context through a category role by somebody not enrolled (on a visible course
           as much as a hidden one) passes can_manage_application() and fails every test above.
           A teacher whose own enrolment is suspended lands here too, and so does a mentor
           looking at an application none of their mentees made.

           The parameterless queue would refuse the first of those, so sending them there after
           a decision would report a success as an exception. An empty mentee list means nothing
           can be walked, and hasqueue false keeps the review page from offering a way back to a
           queue that would refuse this operator. */
        return (object) [
            'enrolid' => 0,
            'mentees' => [],
            'hasqueue' => false,
            'url' => destination::home_page_url(),
        ];
    }

    /**
     * Everything the applications LISTING is scoped by, derived from one enrol instance id.
     *
     * The counterpart of scope() above: scope() asks which queue an operator reviewing an
     * application works in; this asks what the queue at this url shows, from the id in that url.
     *
     * The listing is a dynamic table. core_table\external\dynamic\get builds it, calls
     * set_filterset() with whatever the client sent, then validate_context() and
     * has_capability() once against one context, and never calls filterset::check_validity()
     * (lib/table/classes/external/dynamic/get.php). So the client names the scope on every
     * refresh, while this queue has three scopes across two context levels and the mentee
     * restriction is not something a client may be trusted to state.
     *
     * ONE integer therefore travels - the enrol instance id - and everything the listing is
     * narrowed by is recomputed here from it: the course, the context, the mentee id list and
     * whether this operator may see any of it. A forged id is answered by the capability check
     * against its own course, the same refusal manage.php?id= gives. The mentee list never
     * travels, so no request can widen it.
     *
     * Never returns false, and an unknown id does not throw: get_context() must return a context
     * and is called before has_capability(), and a forged request must get "no permission"
     * rather than a database exception. An id that resolves to nothing comes back with the
     * system context and allowed false.
     *
     * `allowed` is the capability half only. Course access is applied by each caller on its own
     * path: manage.php calls require_login($course) itself, and on the web service path
     * external_api::validate_context() calls require_login() from the context this returns.
     * Folding it in here would duplicate a check one caller has already made and the other
     * cannot skip.
     *
     * @param int $enrolid Enrol instance to list, 0 for every instance this operator may decide in.
     * @return stdClass Object carrying enrolid, instance, context, mentees, identitycontext,
     *                  allowed and url. See the property comments in the body for each.
     */
    public static function listing_scope(int $enrolid): stdClass {
        global $DB;

        if ($enrolid) {
            $instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'apply']);
            if (!$instance) {
                /* An id naming no apply instance. Refused rather than raised: on the web service
                   path this is simply what a forged filter value looks like. */
                return self::refused();
            }

            $context = context_course::instance($instance->courseid, MUST_EXIST);

            return (object) [
                'enrolid' => (int) $instance->id,
                'instance' => $instance,
                'context' => $context,
                // One instance, so no mentee restriction and one course to judge identity in.
                'mentees' => null,
                'identitycontext' => $context,
                'allowed' => has_capability('enrol/apply:manageapplications', $context),
                'url' => new moodle_url('/enrol/apply/manage.php', ['id' => (int) $instance->id]),
            ];
        }

        $system = context_system::instance();
        $url = new moodle_url('/enrol/apply/manage.php');

        if (has_capability('enrol/apply:manageapplications', $system)) {
            return (object) [
                'enrolid' => 0,
                'instance' => null,
                'context' => $system,
                'mentees' => null,
                // The system context is the right question for a site-wide capability holder.
                'identitycontext' => $system,
                'allowed' => true,
                'url' => $url,
            ];
        }

        /* No site-wide capability, so the mentees. A null restriction means "every application",
           which is why the capability test has to come first; an empty list is refused rather
           than listed.

           The identity context is null: this scope spans courses in a single statement, so no
           one context is the right question for it, and a per-row mask would be unsound for a
           sortable column. */
        $mentees = applications::get_mentees();

        return (object) [
            'enrolid' => 0,
            'instance' => null,
            'context' => $system,
            'mentees' => $mentees,
            'identitycontext' => null,
            'allowed' => (bool) $mentees,
            'url' => $url,
        ];
    }

    /**
     * The scope that shows nothing, for an operator or an id that has no listing.
     *
     * The system context and not null, because get_context() must return a context whatever the
     * answer is; the refusal is carried by `allowed`, which is the only field a caller may act on.
     *
     * @return stdClass The refused scope.
     */
    private static function refused(): stdClass {
        return (object) [
            'enrolid' => 0,
            'instance' => null,
            'context' => context_system::instance(),
            'mentees' => [],
            'identitycontext' => null,
            'allowed' => false,
            'url' => new moodle_url('/enrol/apply/manage.php'),
        ];
    }

    /**
     * This applicant's OTHER applications to the same course, newest first.
     *
     * The durable record holds them: its natural key (courseid, userid) is deliberately not
     * unique, because cancelling and re-applying is the ordinary route. This reads the
     * `courseuser` index.
     *
     * The live enrolment is joined because the stored status is only the last decision this
     * plugin's state machine took: the participants page, course reset, user deletion and the
     * expiry sweep all change an enrolment without touching the record. The three outcome
     * aliases below are what the report's outcome formatter reads, so both surfaces describe a
     * record the same way. LEFT JOIN, because a record outlives its enrolment on purpose.
     *
     * @param int $courseid Course the application under review was made to.
     * @param int $userid The applicant.
     * @param int $excludesubmissionid Record id to leave out, normally the one being reviewed.
     * @return array Records, newest first, carrying what the outcome formatter needs.
     */
    public static function prior_applications(int $courseid, int $userid, int $excludesubmissionid): array {
        global $DB;

        if ($userid <= 0) {
            /* A pseudonymised record carries userid 0, so this would gather every pseudonymised
               applicant to this course rather than one person's history. The review page never
               passes 0 (its argument comes from ue.userid); this guards a later caller reading
               the userid straight from enrol_apply_submission. */
            return [];
        }

        $sql = "SELECT s.id, s.status, s.timecreated, s.timedecided, s.decidedby,
                       s.userenrolmentid AS outcomeueid,
                       ue.status AS outcomeenrolstatus, ue.timeend AS outcomeenroltimeend
                  FROM {enrol_apply_submission} s
             LEFT JOIN {user_enrolments} ue ON ue.id = s.userenrolmentid
                 WHERE s.courseid = :courseid AND s.userid = :userid AND s.id <> :excludeid
              ORDER BY s.timecreated DESC, s.id DESC";

        /* Bounded, because nothing else bounds it: a determined re-applicant can accumulate
           records without limit, and this renders on a page whose purpose is one decision. The
           newest few are what a decision turns on; the rest is the report's job. */
        return $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'userid' => $userid,
            'excludeid' => $excludesubmissionid,
        ], 0, self::PRIOR_APPLICATIONS_SHOWN);
    }

    /**
     * The applications either side of this one, in the queue this operator is working in.
     *
     * Resolved in SQL, one statement per direction with a single row taken, rather than by
     * materialising the queue and looking for the current row in it, which would run this same
     * predicate with no LIMIT and hydrate a user record per row across every course on the
     * site-wide scope. {user_enrolments} is indexed on enrolid and userid but not on status or
     * timecreated, so the instance scope (e.id) and the mentee scope (ue.userid) each reach their
     * rows through an index, while the site-wide scope relies on e.enrol = 'apply'. Whatever the
     * planner decides, the sort and the LIMIT stay in the database.
     *
     * The walk is pinned to (timecreated ASC, ue.id ASC): the table's default sort plus the
     * unique final key {@see \enrol_apply\table\applications::get_sort_columns()} appends, which
     * keeps a tied group from trading places. It ignores the operator's own sort, which
     * flexible_table keeps in the session under \enrol_apply\table\applications::UNIQUEID (this
     * table is not persistent), because a server-resolved neighbour cannot depend on state this
     * page does not render. The walk can therefore disagree with a re-sorted queue; each link
     * names the applicant it leads to, so the operator reads where they are going first.
     *
     * The queue has no initials filter ({@see \enrol_apply\table\applications::get_sql_where()}),
     * so there is none for the walk to honour.
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
     * The applications these people have in this course that a bulk decision does NOT reach.
     *
     * A participants-page bulk decision reaches exactly ONE enrolment method, and nothing in
     * this plugin can widen it: user/action_redir.php resolves the plugin to the FIRST {enrol}
     * row of that type in the course - enrol_get_instances($courseid, false), break on the
     * first match, so a disabled method sorting first captures the whole dispatch - and filters
     * the manager to it, while the menu's url carries only the plugin name and the operation.
     *
     * Core warns only when a selected person has no enrolment on that instance. One person
     * holding two applications in one course is the silent case: they come back carrying the
     * filtered instance's row, core reports nothing removed, and the decision reports a clean
     * success. Two applications in one course are supported on purpose, because two apply
     * instances are two intakes.
     *
     * This method lets the operator be told; it deliberately does not let the other applications
     * be decided. The decision carries per-instance data - the role fallback is that instance's
     * roleid, the groups come from its own enrol_apply_groups, the two caps are its own - so
     * approving one intake says nothing about the other, a deferral note written about one is
     * false of the other, and a warning is reversible by the operator where a decision is not.
     *
     * Built from awaiting_decision_where(), so a change to the queue's definition of "awaiting a
     * decision" moves this with it. It does not join {course}: unlike neighbour(), which must
     * reproduce the listing's FROM exactly, nothing here reads a course and the scope is
     * expressed by e.courseid directly.
     *
     * @param int $courseid Course the bulk decision was dispatched in.
     * @param array $userids Users the operator selected.
     * @param array $excludeueids User enrolment ids the decision reached, excluded because a
     *        DEFERRAL leaves its row awaiting a decision - approval and cancellation remove
     *        themselves from this query's own predicate, and deferral does not.
     * @return array The other applications, keyed by user enrolment id; empty when there are none.
     */
    public static function other_applications(int $courseid, array $userids, array $excludeueids): array {
        global $DB;

        /* get_in_or_equal() throws on an empty array and this query takes two id lists. The
           first is the whole question - no users, no applications - so it returns early; the
           second is optional and its clause is simply left out. */
        if (!$userids) {
            return [];
        }

        [$wheres, $params] = self::awaiting_decision_where();
        $wheres[] = 'e.enrol = :enrol';
        $wheres[] = 'e.courseid = :courseid';
        $params['enrol'] = 'apply';
        $params['courseid'] = $courseid;

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'otheruser');
        $wheres[] = "ue.userid {$insql}";
        $params += $inparams;

        if ($excludeueids) {
            [$notinsql, $notinparams] = $DB->get_in_or_equal(
                $excludeueids,
                SQL_PARAMS_NAMED,
                'decided',
                false
            );
            $wheres[] = "ue.id {$notinsql}";
            $params += $notinparams;
        }

        $sql = "SELECT ue.id, ue.userid, ue.enrolid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE " . implode(' AND ', $wheres);

        return $DB->get_records_sql($sql, $params);
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
           nothing from, so that the walk's FROM is the listing's FROM. Neither drops a row on
           data this plugin produces ({enrol}.courseid is declared foreign to course.id, though
           XMLDB creates an index rather than a constraint), but if one ever did, a walk without
           it would offer a neighbour the queue does not list. The listing's two comment LEFT
           joins are omitted because nothing here reads a comment: a LEFT join cannot remove a
           row, and although submission.userenrolmentid is not unique and could multiply one,
           taking one row per statement is unaffected. */
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
     * The gate is the plugin's own can_manage_application(), the predicate every decision
     * applies to every row, so the people who may act on an application are exactly the people
     * who may look at one. Nothing new is disclosed: a course teacher already sees every one of
     * these applications, with the same fields, on the queue.
     *
     * require_login($course) is deliberately not called: a mentor holds no course access at
     * all, which is the point of that delegation level.
     *
     * @param stdClass $application Application as application() returns it.
     * @return context The context that granted access, for the page to sit in.
     */
    public static function require_review_access(stdClass $application): context {
        $usercontext = context_user::instance($application->userid, MUST_EXIST);

        /* can_manage_application() itself, not a second copy of its three levels, which would
           agree only until somebody added an override, a prohibit or a fourth level to one. */
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
