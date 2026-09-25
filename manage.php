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

/**
 * Review pending enrolment applications and decide on them.
 *
 * Three scopes are served by this page, and userenrol is tested first because it selects a
 * different page rather than a narrower one:
 *  - userenrol=<ueid>  ONE application, reviewed on a page of its own, open to anybody who
 *                      may decide it - a site administrator, a teacher of the course it was
 *                      made to, or a mentor of the applicant;
 *  - id=<enrolid>      the queue of one course enrolment instance;
 *  - no parameter      every application the current user may decide on, either site-wide or
 *                      for the users they mentor.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo.com (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     emeneo.com (http://emeneo.com/)
 * @author     Johannes Burk <johannes.burk@sudile.com>
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/apply/renderer.php');

$id = optional_param('id', 0, PARAM_INT);
$userenrol = optional_param('userenrol', 0, PARAM_INT);
$formaction = optional_param('formaction', '', PARAM_ALPHA);
$userenrolments = optional_param_array('userenrolments', [], PARAM_INT);
/* The queue's filters are read here rather than left to the table, because the page url, the
   decision form's action and the post-decision redirect are all built from them. PARAM_NOTAGS
   rather than PARAM_RAW: the value is echoed into an input, a chip label and a lang-string
   parameter, and PARAM_NOTAGS also runs fix_utf8(), without which flexible_table's json_encode()
   of the filterset returns false on invalid UTF-8. */
$search = trim(optional_param('search', '', PARAM_NOTAGS));
/* Read as text and validated against the vocabulary, not as PARAM_INT: the form's "any status"
   option submits `status=`, which PARAM_INT cleans to 0 (ENROL_USER_ACTIVE), a status no queued
   row holds. See \enrol_apply\table\applications::filterable_statuses(). */
$statusparam = optional_param('status', '', PARAM_RAW_TRIMMED);
$status = null;
if ($statusparam !== '' && in_array((int) $statusparam, \enrol_apply\table\applications::filterable_statuses(), true)) {
    $status = (int) $statusparam;
}

require_login();

$manageurlparams = [];
$instance = null;
$afterdecisionurl = null;

if ($userenrol) {
    /* Scope: one application, reviewed on its own. Everything is derived from the user
       enrolment id server-side - the applicant, the enrolment method and therefore the
       course - and no context is ever taken from the request. */
    $application = \enrol_apply\local\queue::application($userenrol);
    if (!$application) {
        /* Nothing to decide: already decided, unenrolled, or never there. The three are one
           outcome to the reader, and the page cannot authorise anybody without a row to
           derive a context from, so it says so and offers the way back. */
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new moodle_url('/enrol/apply/manage.php', ['userenrol' => $userenrol]));
        $PAGE->set_pagelayout('admin');
        $PAGE->set_title(get_string('confirmusers', 'enrol_apply'));
        $PAGE->set_heading(get_string('confirmusers', 'enrol_apply'));
        $PAGE->get_renderer('enrol_apply')->no_application_page(
            new moodle_url('/enrol/apply/manage.php')
        );
        exit;
    }

    $applicant = core_user::get_user($application->userid, '*', MUST_EXIST);
    $instance = $DB->get_record('enrol', ['id' => $application->enrolid, 'enrol' => 'apply'], '*', MUST_EXIST);
    $context = \enrol_apply\local\queue::require_review_access($application);
    $manageurlparams['userenrol'] = $userenrol;
    $pageheading = fullname($applicant);

    /* Where a decision sends the operator back to and which applications the previous and next
       links walk must never disagree, so both come from queue::scope(), which derives the queue
       from what the operator may open rather than from the request.

       A decision does not return to this page: after a confirmation or a cancellation it would
       say "nothing to decide", which reads as a failure. A deferral would still render here, but
       is sent to the queue as well so that all three decisions land in the same place. */
    $scope = \enrol_apply\local\queue::scope($application, $instance);
    $afterdecisionurl = $scope->url;
} else {
    /* The two listing scopes - one enrolment instance, or everything this operator may decide
       on - resolved by queue::listing_scope(), the same resolver the table uses on its AJAX
       refreshes, so the page and the refresh a client can address directly cannot disagree about
       who may see what. Named $listing because $scope above is queue::scope()'s different answer. */
    $listing = \enrol_apply\local\queue::listing_scope($id);

    if ($id) {
        /* A url naming no apply instance is an error here, not an empty queue. listing_scope()
           cannot raise it: it never throws, because on the web service path an unresolvable id is
           what a forged filter value looks like and a quiet refusal is the right answer there. */
        $found = $DB->get_record('enrol', ['id' => $id, 'enrol' => 'apply'], '*', MUST_EXIST);
        $course = get_course($found->courseid);
        require_login($course);
        $manageurlparams['id'] = (int) $found->id;
        $pageheading = format_string($course->fullname);
    } else {
        $pageheading = get_string('confirmusers', 'enrol_apply');
    }

    $context = $listing->context;
    $instance = $listing->instance;

    if (!$listing->allowed) {
        /* Raised here rather than inside the resolver, because the dynamic table's
           has_capability() must answer with a bool: the resolver reports, each caller raises.
           The context is the one listing_scope() checked - the course for ?id=, the system
           context otherwise. */
        require_capability('enrol/apply:manageapplications', $context);
    }
}

/* The filters go into the url before it is built, because this url is $PAGE->url, which
   flexible_table's "Show all / Show per page" link reads its parameters from
   (get_dynamic_table_html_end() uses $PAGE->url, not the table's base url); it is also the
   decision form's action and the redirect after a decision. Without them an operator who narrows
   the queue and decides lands back on the unfiltered queue.

   Only on the listing path: the review page has no queue, so a search there would be stale. */
$queuefilters = [];
if (!$userenrol) {
    if ($search !== '') {
        $manageurlparams['search'] = $search;
    }
    if ($status !== null) {
        $manageurlparams['status'] = $status;
    }
    /* The per-field and date filters, read through the table's own definition of which parameters
       exist - so the listing and the address it is reached at cannot disagree about what is
       applied, and a parameter naming a field this reader may not see is not read at all. */
    $queuefilters = \enrol_apply\table\applications::request_filters($listing);
    foreach ($queuefilters as $name => $value) {
        $manageurlparams[$name] = $value;
    }
}

$manageurl = new moodle_url('/enrol/apply/manage.php', $manageurlparams);

$PAGE->set_context($context);
$PAGE->set_url($manageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pageheading);

if ($userenrol) {
    /* The review page belongs to a course. require_login() is deliberately not given it - a
       mentor holds no course access at all - so without this $COURSE stays the site course and
       the secondary navigation is the front page's.

       set_course() applies no access check, which is what makes it safe for a mentor entitled to
       decide this application without being able to enter the course. It sets the page context
       only when none is set yet, so a mentor keeps the user context set_context() gave above.

       The crumbs are built by hand because set_course() alone does not reliably produce them. */
    $PAGE->set_course(get_course($application->courseid));
    $PAGE->set_title(get_string('reviewtitle', 'enrol_apply', (object) [
        'applicant' => fullname($applicant),
        'course' => format_string($application->coursename, true, ['context' => $context]),
    ]));
    if ($scope->hasqueue) {
        $PAGE->navbar->add(get_string('confirmusers', 'enrol_apply'), $scope->url);
    }
    $PAGE->navbar->add(fullname($applicant));
} else {
    $PAGE->navbar->add(get_string('confirmusers', 'enrol_apply'));
    $PAGE->set_title(get_string('confirmusers', 'enrol_apply'));
}

if ($formaction !== '' && $userenrolments) {
    /* State change: reject anything that is not a sesskey-carrying request. Without this
       the whole queue can be confirmed by getting a manager to follow a crafted link,
       because optional_param() reads GET just as happily as POST. */
    require_sesskey();

    /* PARAM_TEXT and not PARAM_RAW: this reaches the applicant's notification, and the only
       formatting it is allowed is the line breaks the decider typed. It is escaped again at
       the sink in notify_applicant(); cleaning here as well keeps a forged post from putting
       markup into the durable record, which outlives the enrolment it belongs to. */
    $outcomemessage = trim(optional_param('outcomemessage', '', PARAM_TEXT));

    /* The decider's own note, never sent to the applicant. PARAM_TEXT for the same reason as the
       message above. */
    $decisionnote = trim(optional_param('decisionnote', '', PARAM_TEXT));

    /* Cleaned to integers here and allowlisted per instance inside confirm_enrolment(), because
       a site-wide batch can span courses and a group or role valid for one application is not
       necessarily valid for the next. Read unconditionally, not only when the form offered a
       control, so the allowlist runs against whatever a forged post carries. */
    $decision = [
        'groups' => optional_param_array('groups', [], PARAM_INT),
        'roleid' => optional_param('roleid', 0, PARAM_INT),
        /* Always present, because the writer tests the key with array_key_exists(): an empty
           note clears the previous one, and only a caller omitting the key leaves it alone. */
        'note' => $decisionnote,
    ];

    /* Cancelling is the one decision that destroys something: cancel_enrolment() unenrols, taking
       the {user_enrolments} row and the applicant's comment with it. On the review page the
       form's first submit is its default for Enter, and neither Confirm nor Cancel is safe in that
       role, so the destructive decision asks, as core's enrol/unenroluser.php does.

       Only on the review page: the queue has always applied the same formaction to its whole
       selection directly. */
    if ($userenrol && $formaction === 'cancel' && !optional_param('confirmed', 0, PARAM_BOOL)) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('reviewcancelconfirm', 'enrol_apply'));
        /* Both buttons are labelled explicitly: core's confirm() would label the second one
           "Cancel", beside a destructive primary button that also starts with "Cancel". */
        echo $OUTPUT->confirm(
            get_string('reviewcancelconfirm_desc', 'enrol_apply', fullname($applicant)),
            new single_button(
                /* single_button turns each param into a hidden input named by the raw key, so
                   `userenrolments[0]` is what optional_param_array() reads back.

                   The group and role choosers are not carried, here or on the way back: they are
                   the approval's parameters and cancelling reads neither. The message and note
                   travel both ways: onward because the cancellation records them, and back so
                   the operator's text survives backing out. */
                new moodle_url($manageurl, [
                    'formaction' => 'cancel',
                    'confirmed' => 1,
                    'sesskey' => sesskey(),
                    'userenrolments[0]' => $userenrol,
                    'outcomemessage' => $outcomemessage,
                    'decisionnote' => $decisionnote,
                ]),
                get_string('reviewcancelaction', 'enrol_apply'),
                'post'
            ),
            // Back to the review page with the typed text; see the continue button above.
            new single_button(
                new moodle_url($manageurl, [
                    'outcomemessage' => $outcomemessage,
                    'decisionnote' => $decisionnote,
                ]),
                get_string('reviewkeep', 'enrol_apply'),
                'get'
            )
        );
        echo $OUTPUT->footer();
        exit;
    }

    $enrolapply = enrol_get_plugin('apply');
    switch ($formaction) {
        case 'confirm':
            $decided = $enrolapply->confirm_enrolment($userenrolments, $outcomemessage, $decision);
            break;
        case 'wait':
            $decided = $enrolapply->wait_enrolment($userenrolments, $outcomemessage, $decision);
            break;
        case 'cancel':
            $decided = $enrolapply->cancel_enrolment($userenrolments, $outcomemessage, $decision);
            break;
        default:
            throw new moodle_exception('invalidformaction', 'enrol_apply');
    }

    /* Report what the decision methods actually did. Each silently skips a row it will not act
       on - no longer awaiting a decision, in a course this operator may not decide in, or with
       its enrolment gone - and only the method knows which rows it reached.

       A skipped row is a warning beside the success message, not an error: somebody else may be
       working the same queue, so one selection can legitimately hold both. */
    $skipped = count($userenrolments) - $decided;
    if ($skipped > 0) {
        \core\notification::warning(get_string('applicationsskipped', 'enrol_apply', $skipped));
    }
    /* Warn when the places are gone, re-reading after the decisions rather than predicting,
       since a decision method can silently skip a row. Places are advisory: the manager is told
       and decides, so this is a warning beside the success message, not a refusal.

       Emitted here and never from complete_approval(), which runs twice for a queue approval
       (the before_user_enrolment_updated hook reaches it first, and its first pass sees
       pre-write state) while \core\notification::add() does not deduplicate.

       Only where an instance is in scope: the site-wide and mentee queues span instances, and
       there is no single places number to report for them. */
    if ($instance !== null && \enrol_apply\local\capacity::places_full($instance)) {
        \core\notification::warning(
            get_string('placesfull', 'enrol_apply', \enrol_apply\local\capacity::places($instance))
        );
    }

    redirect(
        $afterdecisionurl ?? $manageurl,
        $decided > 0
            ? get_string('applicationsupdated', 'enrol_apply')
            : get_string('applicationsnonedecided', 'enrol_apply'),
        null,
        $decided > 0
            ? \core\output\notification::NOTIFY_SUCCESS
            : \core\output\notification::NOTIFY_INFO
    );
}

$renderer = $PAGE->get_renderer('enrol_apply');

if ($userenrol) {
    // One application gets a page of its own rather than a queue filtered down to one row.
    $neighbours = \enrol_apply\local\queue::neighbours($application, $scope);
    $navigation = new \enrol_apply\output\application_navigation(
        $neighbours['previous'],
        $neighbours['next'],
        $scope->hasqueue ? $scope->url : null
    );
    /* Whatever the operator had typed before they opened the confirmation and backed out of it.
       PARAM_TEXT and rendered through a double stash, exactly as it is on the way in. */
    $prefillmessage = optional_param('outcomemessage', '', PARAM_TEXT);
    // The note travels back on the same journey and for the same reason.
    $prefillnote = optional_param('decisionnote', '', PARAM_TEXT);

    $renderer->review_page(
        $application,
        $applicant,
        $instance,
        $manageurl,
        $navigation,
        $prefillmessage,
        $prefillnote
    );
    exit;
}

/* Everything else the listing is narrowed by - the mentee ids, the context that judges the
   identity fields, the wording of the comment heading - is resolved inside from the instance id,
   so this page and the web service that refreshes its rows cannot decide them differently. */
$table = \enrol_apply\table\applications::for_scope((int) $id, $search, $status, $queuefilters);
$renderer->manage_page($table, $manageurl, $instance);
