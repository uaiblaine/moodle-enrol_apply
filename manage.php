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
require_once($CFG->dirroot . '/enrol/apply/manage_table.php');
require_once($CFG->dirroot . '/enrol/apply/renderer.php');

$id = optional_param('id', 0, PARAM_INT);
$userenrol = optional_param('userenrol', 0, PARAM_INT);
$formaction = optional_param('formaction', '', PARAM_ALPHA);
$userenrolments = optional_param_array('userenrolments', [], PARAM_INT);

require_login();

$manageurlparams = [];
$instance = null;
$mentees = null;
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
} else if ($id) {
    // Scope: one course enrolment instance.
    $instance = $DB->get_record('enrol', ['id' => $id, 'enrol' => 'apply'], '*', MUST_EXIST);
    $course = get_course($instance->courseid);
    require_login($course);
    $context = context_course::instance($course->id, MUST_EXIST);
    require_capability('enrol/apply:manageapplications', $context);
    $manageurlparams['id'] = $instance->id;
    $pageheading = format_string($course->fullname);
} else {
    /* Scope: everything the current user may decide on. Site-wide for holders of the
       capability at system level; otherwise restricted to the users they mentor, which
       here means the users they hold a role assignment over in those users' own
       contexts. */
    $context = context_system::instance();
    if (!has_capability('enrol/apply:manageapplications', $context)) {
        /* No site-wide capability, so fall back to the mentees. A null restriction means
           "every application"; an empty list would silently widen the query the same way,
           which is why the capability check has to come first. */
        $mentees = \enrol_apply\local\applications::get_mentees();
        if (!$mentees) {
            require_capability('enrol/apply:manageapplications', $context);
        }
    }
    $pageheading = get_string('confirmusers', 'enrol_apply');
}

$manageurl = new moodle_url('/enrol/apply/manage.php', $manageurlparams);

$PAGE->set_context($context);
$PAGE->set_url($manageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($pageheading);
$PAGE->navbar->add(get_string('confirmusers', 'enrol_apply'));
$PAGE->set_title(get_string('confirmusers', 'enrol_apply'));

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

    /* Cleaned to integers here and allowlisted per instance inside confirm_enrolment(), not
       here: the batch can span courses when the queue is opened site wide, so a group or a role
       that is legitimate for one application is not necessarily legitimate for the next. Both
       are read unconditionally rather than only when the form offered a control, because the
       allowlist has to run against whatever a forged post carries, not against what the page
       chose to render. */
    $decision = [
        'groups' => optional_param_array('groups', [], PARAM_INT),
        'roleid' => optional_param('roleid', 0, PARAM_INT),
    ];

    $enrolapply = enrol_get_plugin('apply');
    switch ($formaction) {
        case 'confirm':
            $enrolapply->confirm_enrolment($userenrolments, $outcomemessage, $decision);
            break;
        case 'wait':
            $enrolapply->wait_enrolment($userenrolments, $outcomemessage);
            break;
        case 'cancel':
            $enrolapply->cancel_enrolment($userenrolments, $outcomemessage);
            break;
        default:
            throw new moodle_exception('invalidformaction', 'enrol_apply');
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
        get_string('applicationsupdated', 'enrol_apply'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$renderer = $PAGE->get_renderer('enrol_apply');

if ($userenrol) {
    // One application gets a page of its own rather than a queue filtered down to one row.
    $neighbours = \enrol_apply\local\queue::neighbours($application, $scope);
    $navigation = new \enrol_apply\output\application_navigation(
        $neighbours['previous'],
        $neighbours['next']
    );
    $renderer->review_page($application, $applicant, $instance, $manageurl, $navigation);
    exit;
}

/* The instance-scoped queue heads its comment column with whatever the teacher asked for, in the
   ESCAPED spelling the table's header sink needs. $instance is null on the site-wide and mentee
   scopes, which span instances and therefore have no single question to quote. */
$commentlabel = $instance === null ? '' : \enrol_apply\local\commentlabel::custom($instance);

/* Which context judges the applicant's identity fields, and null where none can. The instance
   queue has one course; the site-wide queue is opened by a system-level capability holder and the
   system context is the right question for them. The MENTEE queue is the null one: it spans
   courses in a single statement, so no context is right for it and a per-row mask would be unsound
   for a sortable column. $mentees is non-null on exactly that branch. */
$identitycontext = null;
if ($mentees === null) {
    $identitycontext = $instance === null ? context_system::instance() : $context;
}

$table = new enrol_apply_manage_table($id, $mentees, $commentlabel, $identitycontext);
$table->define_baseurl($manageurl);
$renderer->manage_page($table, $manageurl, $instance);
