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

    /* Which queue this operator is working in. It answers two questions that must never be
       able to disagree - where a decision sends them back to, and which applications the
       previous and next links walk - so it is resolved once, by queue::scope(), which also
       records why it is derived from the operator rather than read out of the request.

       Not back to this page after a decision: two of the three decisions leave this url
       reviewing an application that is no longer awaiting one, and landing on "nothing to
       decide" having just decided it reads as a failure. Deferring is the exception - a
       waiting-list application is still awaiting a decision and the page would render again
       perfectly well - but sending one decision somewhere else than the other two would be
       stranger than sending all three to the queue. */
    $scope = \enrol_apply\local\queue::scope($application, $instance);
    $afterdecisionurl = $scope->url;
} else {
    /* The two LISTING scopes - one enrolment instance, or everything this operator may decide
       on - and both are resolved by queue::listing_scope(), which the table resolves from too.
       Named $listing and not $scope, because the review branch above already binds $scope to
       queue::scope()'s answer, which is a different object answering a different question.
       That is the point of it: the page and the AJAX refreshes that replace its rows would
       otherwise hold two independent statements of who may see what, and the refresh path is
       the one a client can address directly. */
    $listing = \enrol_apply\local\queue::listing_scope($id);

    if ($id) {
        /* A url naming no apply instance is an error here and not an empty queue, the same way
           report.php's MUST_EXIST makes it one. listing_scope() cannot raise it: it is total on
           purpose, because on the web service path an unresolvable id is simply what a forged
           filter value looks like, and refusing quietly is the right answer there. */
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
        /* The refusal both scopes gave before, raised here rather than inside the resolver:
           the dynamic table's has_capability() answers with a bool, so the resolver reports and
           each caller raises in the way its own path raises. The context differs by scope - the
           course for ?id=, the system context otherwise - and each is the one that was checked. */
        require_capability('enrol/apply:manageapplications', $context);
    }
}

$manageurl = new moodle_url('/enrol/apply/manage.php', $manageurlparams);

$PAGE->set_context($context);
$PAGE->set_url($manageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pageheading);

if ($userenrol) {
    /* The review page belongs to a course, and until this call it did not say so. require_login()
       is deliberately not given one here - a mentor holds no course access at all, which is the
       whole point of that delegation level - so $COURSE stayed the SITE course and every
       navigation node was built from it: measured, eight secondary-navigation links all pointing
       at course id 1, one of them the front page's own settings, on a page deciding another
       course's application.

       set_course() applies no access check of any kind, which is exactly what makes it safe
       here: the operator may be a mentor, entitled to decide this application and unable to enter
       the course at all.

       It cannot clobber the context in either order, and an earlier version of this comment said
       it could. set_course() sets the page context only `if (!$this->_context)` (lib/pagelib.php,
       :1190 on 5.2 and :1170 on 5.1), so placed first it would set the course context and the
       explicit set_context() below would immediately win; placed here it does nothing to the
       context at all. Either way the mentor keeps the user context. It is called after for
       readability, not for safety.

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
    /* State change: reject anything that is not a sesskey-carrying POST. Without this
       the whole queue can be confirmed by getting a manager to follow a crafted link,
       because optional_param() reads GET just as happily as POST. */
    require_sesskey();

    /* PARAM_TEXT and not PARAM_RAW: this reaches the applicant's notification, and the only
       formatting it is allowed is the line breaks the decider typed. It is escaped again at
       the sink in notify_applicant(); cleaning here as well keeps a forged post from putting
       markup into the durable record, which outlives the enrolment it belongs to. */
    $outcomemessage = trim(optional_param('outcomemessage', '', PARAM_TEXT));

    /* The decider's own note, on the same contract as the message above and with the opposite
       audience: it is never sent to the applicant and never leaves the site. PARAM_TEXT for the
       same reason - it outlives the enrolment it belongs to, so a forged post must not be able
       to put markup into the durable record. */
    $decisionnote = trim(optional_param('decisionnote', '', PARAM_TEXT));

    /* Cleaned to integers here and allowlisted per instance inside confirm_enrolment(), not
       here: the batch can span courses when the queue is opened site wide, so a group or a role
       that is legitimate for one application is not necessarily legitimate for the next. Both
       are read unconditionally rather than only when the form offered a control, because the
       allowlist has to run against whatever a forged post carries, not against what the page
       chose to render. */
    $decision = [
        'groups' => optional_param_array('groups', [], PARAM_INT),
        'roleid' => optional_param('roleid', 0, PARAM_INT),
        /* Present on every decision, which is what lets the key be read with
           array_key_exists() rather than tested for emptiness: an empty note submitted with a
           decision CLEARS the previous one, and only a caller that omits the key entirely
           leaves it alone. */
        'note' => $decisionnote,
    ];

    /* Cancelling is the one decision that destroys something: cancel_enrolment() unenrols, which
       takes the {user_enrolments} row and the applicant's comment with it. On the review page it
       is also one click from Confirm, and Confirm is the form's FIRST submit and therefore its
       default - so Enter on either chooser used to approve the enrolment. Button order alone
       cannot fix that: whichever submit comes first becomes the default, and neither of these two
       is safe as one. So the destructive decision asks.

       Only on the review page. The queue posts the same formaction for a whole selection and has
       always applied it directly; intercepting there would change a shipped flow this slice is
       not rebuilding, and would break the scenario that proves the queue still works without
       JavaScript. Core's own precedent for asking before this exact act is enrol/unenroluser.php. */
    if ($userenrol && $formaction === 'cancel' && !optional_param('confirmed', 0, PARAM_BOOL)) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('reviewcancelconfirm', 'enrol_apply'));
        /* Both buttons are named explicitly. Core's confirm() labels its second one "Cancel",
           which beside a decision this plugin also calls "Cancel this application" gives the
           reader two buttons opening on the same word, one of them destructive and rendered as
           the primary. Naming them for what they DO removes the ambiguity rather than relying on
           the reader parsing the rest of the label. */
        echo $OUTPUT->confirm(
            get_string('reviewcancelconfirm_desc', 'enrol_apply', fullname($applicant)),
            new single_button(
                /* The key carries its own index because single_button turns each param into a
                   hidden input whose NAME is the raw key, so `userenrolments[0]` is what
                   optional_param_array() reads back on the next request. Not because a
                   moodle_url param has to be scalar - it does not: url::params() explicitly
                   leaves arrays alone and stringifies everything else (lib/classes/url.php:203,
                   "Converts given URL parameter values that are not arrays into strings"), and
                   an earlier version of this comment claimed the opposite.

                   What travels is what this decision needs: the session key, the action, the one
                   application and the message. The group and role choosers are deliberately NOT
                   carried - they are the APPROVAL's parameters and cancelling reads neither, so
                   re-emitting them would be re-emitting inputs the branch about to run ignores. */
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
            /* The typed message travels back, so backing out does not throw away the prose the
               operator wrote before they hesitated. The two choosers do not: they are the
               APPROVAL's parameters, this branch reads neither, and re-selecting a group on the
               way back from a cancellation would be restoring a choice for a decision that was
               not taken. The message is different - it is what they would write for either. */
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

    /* What the decision methods actually did, reported rather than assumed. Each of them skips
       a row it will not act on - one that is no longer awaiting a decision, one in a course this
       operator may not decide in, one whose enrolment has gone - and it skips it in silence, so
       "Applications updated" was printed for a post that changed nothing at all. The count comes
       from the method, which is the only thing that knows which rows it reached.

       A skipped row is not an error and does not replace the success message: a selection can
       legitimately hold both, because the queue is a listing somebody else may be working at the
       same time. */
    $skipped = count($userenrolments) - $decided;
    if ($skipped > 0) {
        \core\notification::warning(get_string('applicationsskipped', 'enrol_apply', $skipped));
    }
    /* Warn when the places are gone, re-reading AFTER the decisions rather than predicting -
       the same discipline the counts in classes/bulk/ apply, and the only truthful reading when
       a decision method can silently skip a row.

       Places do not block an approval: the manager is told and decides, which is the whole
       premise of a plugin built around discretion. So this is a warning beside the success
       message, not a refusal instead of one.

       Emitted here and never from complete_approval(), which runs TWICE for a queue approval -
       the before_user_enrolment_updated hook reaches it before confirm_enrolment() does - while
       \core\notification::add() does not deduplicate. A batch of ten would warn twenty times,
       and the earlier of the two passes sees pre-write state anyway.

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

/* One argument, and everything the listing is narrowed by - the mentee ids, the context that
   judges the identity fields, the wording of the comment heading - resolved from it inside. Those
   three used to be computed here and passed in, which meant this page and the web service that
   refreshes its rows each decided them separately. */
$table = \enrol_apply\table\applications::for_scope((int) $id);
$renderer->manage_page($table, $manageurl, $instance);
