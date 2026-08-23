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
 * Three scopes are served by this page:
 *  - id=<enrolid>      the applications of one course enrolment instance;
 *  - userenrol=<ueid>  a single application, reachable by a mentor holding the
 *                      capability in the applicant's user context;
 *  - no parameter      every application the current user may decide on, either
 *                      site-wide or for the users they mentor.
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

if ($id) {
    // Scope: one course enrolment instance.
    $instance = $DB->get_record('enrol', ['id' => $id, 'enrol' => 'apply'], '*', MUST_EXIST);
    $course = get_course($instance->courseid);
    require_login($course);
    $context = context_course::instance($course->id, MUST_EXIST);
    require_capability('enrol/apply:manageapplications', $context);
    $manageurlparams['id'] = $instance->id;
    $pageheading = format_string($course->fullname);
} else if ($userenrol) {
    /* Scope: one application. The applicant is derived from the user enrolment id
       server-side; the context is never taken from the request. */
    $applicantid = $DB->get_field_sql(
        "SELECT ue.userid
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
          WHERE ue.id = :ueid AND e.enrol = :enrol",
        ['ueid' => $userenrol, 'enrol' => 'apply'],
        MUST_EXIST
    );
    $applicant = core_user::get_user($applicantid, '*', MUST_EXIST);
    $context = context_user::instance($applicantid, MUST_EXIST);
    require_capability('enrol/apply:manageapplications', $context);
    $manageurlparams['userenrol'] = $userenrol;
    $pageheading = fullname($applicant);
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
       markup into the durable record, which the report and the privacy export both read. */
    $outcomemessage = trim(optional_param('outcomemessage', '', PARAM_TEXT));

    /* Cleaned to integers here and allowlisted per instance inside confirm_enrolment(), not
       here: the batch can span courses when the queue is opened site wide, so a group that is
       legitimate for one application is not necessarily legitimate for the next. */
    $decision = ['groups' => optional_param_array('groups', [], PARAM_INT)];

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
    redirect($manageurl, get_string('applicationsupdated', 'enrol_apply'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$table = new enrol_apply_manage_table($id, $userenrol, $mentees);
$table->define_baseurl($manageurl);

$renderer = $PAGE->get_renderer('enrol_apply');
$renderer->manage_page($table, $manageurl, $instance);
