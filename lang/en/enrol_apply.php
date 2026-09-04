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
 * Strings for the enrolment upon approval plugin.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo.com (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     emeneo.com (http://emeneo.com/)
 * @author     Johannes Burk <johannes.burk@sudile.com>
 */

defined('MOODLE_INTERNAL') || die();

$string['allowedfields'] = 'Profile fields courses may ask for';
$string['allowedfields_desc'] = 'The pool of profile fields a course may collect with an enrolment application. A teacher picks from this list for each enrolment method; unticking a field here removes it from every existing method as well, because the picked set is checked against this list on every use.';
$string['allowprofilewrite'] = 'Allow courses to offer to save profile details';
$string['allowprofilewrite_desc'] = 'When enabled, an enrolment method may offer an applicant the chance to save the details they entered to their own profile. The applicant is always asked first, and only the fields they may edit are ever written. When disabled, applicants are instead shown which details are missing and sent to their profile page.';
$string['applicationapproved'] = 'Application approved';
$string['applicationapproved_body'] = 'Your enrolment application was approved and your enrolment is active. You can enter the course.';
$string['applicationcancelednotification'] = 'Your course enrolment application was canceled.';
$string['applicationconfirmednotification'] = 'Your course enrolment application was confirmed.';
$string['applicationdeferred'] = 'Application deferred';
$string['applicationdeferred_body'] = 'Your enrolment application has been deferred. No decision has been taken on it yet: it is waiting either for a place or for something to be checked. You will be notified by email when it is decided.';
$string['applicationdeferrednotification'] = 'Your course enrolment application was deferred.';
$string['applicationgone'] = 'This enrolment application is no longer awaiting a decision. It may have been decided already, or the enrolment may have been removed.';
$string['applicationinactive'] = 'Enrolment not active';
$string['applicationinactive_body'] = 'Your enrolment application was approved, but your enrolment in this course is not active, so you cannot enter it. Contact the teacher of the course or the site administrator.';
$string['applicationsclosednotice'] = 'This enrolment method holds {$a->held} of a maximum {$a->limit} applications and is accepting no more. {$a->deferred} of them are deferred, and a deferred application is freed by nothing: cancel the ones you no longer need to make room.';
$string['applicationsnonedecided'] = 'No application was decided. The ones you selected are no longer awaiting a decision, or are not yours to decide.';
$string['applicationsskipped'] = 'Applications left alone, because they were no longer awaiting a decision or were not yours to decide: {$a}';
$string['applicationsubmitted'] = 'Application submitted';
$string['applicationsubmitted_body'] = 'Your application has been submitted and is waiting for a decision. You will be notified once it has been reviewed.';
$string['applicationsupdated'] = 'The selected enrolment applications have been updated.';
$string['apply:config'] = 'Configure apply enrol instances';
$string['apply:manage'] = 'Manage user enrolments';
$string['apply:manageapplications'] = 'Manage apply enrolment';
$string['apply:unenrol'] = 'Cancel users from the course';
$string['apply:unenrolself'] = 'Cancel self from the course';
$string['apply:viewreports'] = 'View the report of enrolment applications';
$string['applycomment'] = 'Comment';
$string['applydate'] = 'Application date';
$string['applymanage'] = 'Manage enrolment applications';
$string['applyuser'] = 'First name / Surname';
$string['backtoapplications'] = 'Back to the enrolment applications';
$string['btncancel'] = 'Cancel requests';
$string['btnconfirm'] = 'Confirm requests';
$string['btnwait'] = 'Defer requests';
$string['bulkapplicants'] = 'Selected applicants';
$string['bulkcancel'] = 'Cancel enrolment applications';
$string['bulkcanceldesc'] = 'The applications listed below will be cancelled and the applicants unenrolled. Anybody in the selection who is not waiting for a decision is left alone.';
$string['bulkconfirm'] = 'Confirm enrolment applications';
$string['bulkconfirmdesc'] = 'The applications listed below will be confirmed and the applicants enrolled. Anybody in the selection who is not waiting for a decision is left alone.';
$string['bulkdecided'] = 'Enrolment applications decided: {$a}';
$string['bulknothingdecided'] = 'None of the selected users had an application to decide.';
$string['bulknotpermitted'] = 'You cannot decide enrolment applications in this course.';
$string['bulkothermethods'] = 'A bulk decision from this page reaches one enrolment method, and this one reached "{$a->method}". Applications by the same people on other methods of this course, left alone: {$a->count}';
$string['bulkothermethodsform'] = 'A bulk decision from this page reaches one enrolment method, and this one reaches "{$a->method}". The people you selected have {$a->count} more on other methods of this course, and those will be left alone. Decide them from the approval queue, which reaches every method.';
$string['bulkskipped'] = 'Selected users with no application awaiting a decision, left unchanged: {$a}';
$string['bulkunchanged'] = 'Applications whose enrolment did not change: {$a}';
$string['bulkwait'] = 'Defer enrolment applications';
$string['bulkwaitdesc'] = 'The applications listed below will be deferred: they keep waiting, and nobody is enrolled or unenrolled. Anybody in the selection who is not waiting for a decision is left alone.';
$string['cancelmail_desc'] = '';
$string['cancelmail_heading'] = 'Cancelation email';
$string['cancelmailcontent'] = 'Cancelation email content';
$string['cancelmailcontent_default'] = '<p>Hello {firstname},</p><p>Your enrolment application for {content} has not been accepted.</p>';
$string['cancelmailcontent_desc'] = 'Leave empty to use the wording this plugin ships. Please use the following special marks to replace email content with data from Moodle.<br/>{firstname}:The first name of the user; {content}:The course name;{lastname}:The last name of the user;{username}:The users registration username';
$string['cancelmailsubject'] = 'Cancelation email subject';
$string['cancelmailsubject_default'] = 'Your enrolment application for {$a} was not accepted';
$string['cancelmailsubject_desc'] = 'Leave empty to use the wording this plugin ships.';
$string['canntenrolearly'] = 'You cannot apply yet; applications open on {$a}.';
$string['canntenrollate'] = 'You cannot apply any more, since applications closed on {$a}.';
$string['cantenrol'] = 'Enrolment is disabled or inactive';
$string['checkyourdetails'] = 'Check your details';
$string['cohortnonmemberinfo'] = 'Enrolment in this course is restricted to the members of a specific cohort. If you believe you should have access, contact the course administration.';
$string['cohortonly'] = 'Only cohort members';
$string['cohortonly_help'] = 'Applications may be restricted to members of a specified cohort only. Note that changing this setting has no effect on existing applications or enrolments.';
$string['cohortunresolved'] = 'This enrolment method is restricted to a cohort that does not exist on this site, so no applications can be accepted. Please contact the course administrator.';
$string['comment'] = 'Comment';
$string['confirmalldetails'] = 'These details are up to date';
$string['confirmenrol'] = 'Manage application';
$string['confirmfield'] = '\'{$a}\' is up to date';
$string['confirmmail_desc'] = '';
$string['confirmmail_heading'] = 'Confirmation email';
$string['confirmmailcontent'] = 'Confirmation email content';
$string['confirmmailcontent_default'] = '<p>Hello {firstname},</p><p>Your enrolment application for {content} has been approved. You can now enter the course.</p>';
$string['confirmmailcontent_desc'] = 'Leave empty to use the wording this plugin ships. Please use the following special marks to replace email content with data from Moodle.<br/>{firstname}:The first name of the user; {content}:The course name;{lastname}:The last name of the user;{username}:The users registration username;{timeend}: The enrolment expiration date';
$string['confirmmailsubject'] = 'Confirmation email subject';
$string['confirmmailsubject_default'] = 'Your enrolment application for {$a} was approved';
$string['confirmmailsubject_desc'] = 'Leave empty to use the wording this plugin ships.';
$string['confirmusers'] = 'Enrol Confirm';
$string['confirmusers_desc'] = 'Applications badged as on the waiting list have been deferred.';
$string['coursename'] = 'Course';
$string['custom_label'] = 'Custom label';
$string['custom_label_help'] = 'Heads the applicant\'s comment box, and the comment column on the approval queue and the review page, so the question you ask and the answers you read carry the same wording. It has no effect unless \'Commentary field\' is set to Yes. Leave it empty to use the shipped wording.';
$string['datasource:applications'] = 'Enrolment applications';
$string['decideapplication'] = 'Decide this application';
$string['decisiongroups'] = 'Groups to join on approval';
$string['decisiongroups_help'] = 'The approved applicants join these groups. Leave it empty to use the groups configured on the enrolment method.';
$string['decisionnote'] = 'Note for the record';
$string['decisionnote_help'] = 'Kept with the application for whoever looks at it next, and shown in the applications report. The applicant never receives it - use "Message to the applicant" for anything they should read. Leaving it empty clears any note an earlier decision left, because the note belongs to the decision being taken.';
$string['decisionrole'] = 'Role to assign on approval';
$string['decisionrole_help'] = 'The approved applicants are given this role in the course. Leave it alone to use the role configured on the enrolment method. Only roles you may assign in this course are offered, and only approval uses this - deferring or cancelling ignores it.';
$string['decisionroledefault'] = 'Use the role set on the enrolment method';
$string['defaultperiod'] = 'Default enrolment duration';
$string['defaultperiod_desc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['defaultperiod_help'] = 'Default length of time that the enrolment is valid, starting with the moment the user is enrolled. If disabled, the enrolment duration will be unlimited by default.';
$string['defaultrole_desc'] = 'Role assigned to a user when their enrolment application is approved.';
$string['detailsthattravel'] = 'Details that travel with your application';
$string['detailsthattravel_desc'] = 'These details are sent to whoever reviews your application. Editing them here does not change your profile.';
$string['editdescription'] = 'Textarea description';
$string['enrolenddate'] = 'Applications close';
$string['enrolenddate_help'] = 'If enabled, applications can be submitted until this date only. This is separate from the enrolment duration, which decides how long an approved enrolment lasts.';
$string['enrolenddaterror'] = 'The date applications close cannot be earlier than the date they open';
$string['enrolmentactive'] = 'Enrolled';
$string['enrolmentgone'] = 'No longer enrolled';
$string['enrolmentsuspended'] = 'Suspended';
$string['enrolmentunknown'] = 'Unknown';
$string['enrolmentwaiting'] = 'Deferred';
$string['enrolstartdate'] = 'Applications open';
$string['enrolstartdate_help'] = 'If enabled, applications can be submitted from this date onward only. This is separate from the enrolment duration, which decides how long an approved enrolment lasts.';
$string['entity:submission'] = 'Enrolment application';
$string['expiredaction'] = 'Enrolment expiry action';
$string['expiredaction_help'] = 'Select action to carry out when user enrolment expires. Please note that some user data and settings are purged from course during course unenrolment.';
$string['expiry_desc'] = '';
$string['expiry_heading'] = 'Expiry settings';
$string['expirymessageenrolledbody'] = 'Dear {$a->user},

This is a notification that your enrolment in the course \'{$a->course}\' is due to expire on {$a->timeend}.

If you need help, please contact {$a->enroller}.';
$string['expirymessageenrolledsubject'] = 'Apply enrolment expiry notification';
$string['expirymessageenrollerbody'] = 'Apply enrolment in the course \'{$a->course}\' will expire within the next {$a->threshold} for the following users:

    {$a->users}

To extend their enrolment, go to {$a->extendurl}';
$string['expirymessageenrollersubject'] = 'Apply enrolment expiry notification';
$string['expirynotifyall'] = 'Teacher and enrolled user';
$string['expirynotifyenroller'] = 'Teacher only';
$string['expirynotifyhour_desc'] = 'Hour of the day at which the enrolment expiry notifications are sent.';
$string['fieldrequired'] = 'Required';
$string['gotoprofile'] = 'Go to my profile';
$string['group'] = 'Group assignment';
$string['group_help'] = 'You can assign none or multiples groups. Members are added once the enrolment application is approved.';
$string['invalidformaction'] = 'Unknown action requested for the selected enrolment applications.';
$string['lockedby'] = 'Details set by your institution';
$string['mailtoteacher_subject'] = 'New Enrolment request!';
$string['maxapplicants'] = 'Maximum applicants';
$string['maxapplicants_help'] = 'The largest number of applications this enrolment method will accept. Pending, deferred and approved applications all count towards it; an enrolment whose period has ended does not, so this number will not match the Users column on the enrolment methods page. 0 means no limit. Applicants are never shown this number - once it is reached they are simply told that applications are closed.';
$string['maxenrolled'] = 'Max enrolled users';
$string['maxenrolled_help'] = 'Specifies the maximum number of users that can apply for this course. 0 means no limit.';
$string['maxenrolledreached'] = 'No more applications are being accepted.';
$string['messageprovider:application'] = 'Course enrolment application notifications';
$string['messageprovider:cancelation'] = 'Course enrolment application cancelation notifications';
$string['messageprovider:confirmation'] = 'Course enrolment application confirmation notifications';
$string['messageprovider:expiry_notification'] = 'Apply enrolment expiry notifications';
$string['messageprovider:waitinglist'] = 'Course enrolment application deferral notifications';
$string['newapplicationnotification'] = 'There is a new course enrolment application awaiting review.';
$string['newenrols'] = 'Allow new course enrol request';
$string['newenrols_desc'] = 'Allow users to apply for an enrolment in new instances by default.';
$string['nocomment'] = 'The applicant did not write anything.';
$string['nofieldsoffered'] = 'The administrator does not currently allow any profile field to be collected with an application.';
$string['nothingtoprovide'] = 'There is nothing to fill in. Submit to apply for {$a}.';
$string['notification'] = 'Enrolment application successfully sent. You will be informed by email when your enrolment has been confirmed.';
$string['notify_desc'] = 'Define who gets notified about new enrolment applications.';
$string['notify_heading'] = 'Notification settings';
$string['notifyapprovaltask'] = 'Apply enrolment send approval notification task';
$string['notifycoursebased'] = 'New enrolment application notification (instance based, eg. course teachers)';
$string['notifycoursebased_desc'] = 'Default for new instances: Notify everyone who have the \'Manage apply enrolment\' capability for the corresponding course (eg. teachers and managers)';
$string['notifyglobal'] = 'New enrolment application notification (global, eg. global managers and admins)';
$string['notifyglobal_desc'] = 'Define who gets notified about new enrolment applications for any course.';
$string['opt_commentaryzone'] = 'Commentary field';
$string['opt_commentaryzone_help'] = 'Yes -> Enable the commentary field in the enrol form';
$string['outcomeapproved'] = 'Approved, enrolled';
$string['outcomeawaiting'] = 'Awaiting a decision';
$string['outcomecancelled'] = 'Cancelled';
$string['outcomeexpired'] = 'Approved, then expired';
$string['outcomemessage'] = 'Message to the applicant';
$string['outcomemessage_help'] = 'Included in the message the applicant receives with your decision. Leave it empty to send the standard wording alone.';
$string['outcomeneverdecided'] = 'Never decided, and no longer enrolled';
$string['outcomesuspended'] = 'Approved, then suspended';
$string['outcomeunenrolled'] = 'Approved, then unenrolled';
$string['outcomewaiting'] = 'Deferred';
$string['places'] = 'Places';
$string['places_help'] = 'How many applicants this enrolment method may have approved at one time. Only approved enrolments count, so the method can accept more applications than it has places - which is the point, since not every applicant is approved. An enrolment whose period has ended releases its place. 0 means no limit. Reaching it does not block an approval: the manager is warned and decides.';
$string['placesfull'] = 'All {$a} places on this enrolment method are taken.';
$string['placestaken'] = 'Places taken: {$a->taken} of {$a->limit}.';
$string['pluginname'] = 'Course enrol confirmation';
$string['pluginname_desc'] = 'With this plug-in users can apply to be enrolled in a course. A teacher or site manager will then have to approve the enrolment before the user gets enroled.';
$string['privacy:applicationpath'] = 'Enrolment application';
$string['privacy:metadata:enrol_apply_applicationinfo'] = 'Information submitted with a course enrolment application.';
$string['privacy:metadata:enrol_apply_applicationinfo:comment'] = 'The comment the user wrote when applying for the course enrolment.';
$string['privacy:metadata:enrol_apply_applicationinfo:userenrolmentid'] = 'The enrolment the application belongs to, which identifies the applying user and the course.';
$string['privacy:metadata:enrol_apply_submission'] = 'The durable record of a course enrolment application: what was submitted, what was decided, by whom and when.';
$string['privacy:metadata:enrol_apply_submission:comment'] = 'The comment the user wrote when applying for the course enrolment.';
$string['privacy:metadata:enrol_apply_submission:courseid'] = 'The course the application was submitted to.';
$string['privacy:metadata:enrol_apply_submission:decidedby'] = 'The user who approved, deferred or cancelled the application.';
$string['privacy:metadata:enrol_apply_submission:decidedgroups'] = 'The groups the decider chose for the applicant to join on approval.';
$string['privacy:metadata:enrol_apply_submission:decidedrole'] = 'The role the decider chose for the applicant to hold on approval.';
$string['privacy:metadata:enrol_apply_submission:decisionnote'] = 'The note the decider recorded about the decision, which is never sent to the applicant.';
$string['privacy:metadata:enrol_apply_submission:enrolid'] = 'The enrolment method the application was submitted to.';
$string['privacy:metadata:enrol_apply_submission:outcomemessage'] = 'The message the decider wrote to the applicant.';
$string['privacy:metadata:enrol_apply_submission:status'] = 'Whether the application is pending, approved, deferred or cancelled.';
$string['privacy:metadata:enrol_apply_submission:timecreated'] = 'The time the application was submitted.';
$string['privacy:metadata:enrol_apply_submission:timedecided'] = 'The time the application was decided.';
$string['privacy:metadata:enrol_apply_submission:userenrolmentid'] = 'The enrolment the application was submitted for, where it still exists.';
$string['privacy:metadata:enrol_apply_submission:userid'] = 'The user who submitted the application.';
$string['privacy:metadata:enrol_apply_submission:userinfodata'] = 'The profile details the user entered on the application form.';
$string['privacy:methodpath'] = 'Enrolment method {$a}';
$string['privacy:recordpath'] = 'Application record {$a}';
$string['privacy:roleapplicant'] = 'Applications you submitted';
$string['privacy:roledecider'] = 'Applications you decided';
$string['privacy:trailpath'] = 'Enrolment application records';
$string['profileincomplete'] = 'Some details are missing from your profile';
$string['profileincomplete_desc'] = 'Your application has been submitted. These details are not yet on your profile, and this site does not allow courses to fill them in for you.';
$string['profilenotupdated'] = 'Nothing needed saving to your profile.';
$string['profilenow'] = 'Your profile now';
$string['profileupdated'] = 'Your profile has been updated.';
$string['purgesubmissionstask'] = 'Delete enrolment application records past their retention period';
$string['queueapplicationsclosed'] = 'Closed';
$string['queueapplicationsopen'] = 'Open';
$string['queueappliedbefore'] = 'Applied before';
$string['queueawaiting'] = 'Awaiting decision';
$string['queueclearfilters'] = 'Clear all';
$string['queuecloseson'] = 'closes {$a}';
$string['queuedeferred'] = 'Deferred to the waiting list';
$string['queuefiltercount'] = '{$a->matched} of {$a->total} applications';
$string['queuefilterempty'] = 'No application matches the filters applied. Clear them to see the whole queue.';
$string['queuefilterfields'] = 'Profile fields the applications queue may be filtered by';
$string['queuefilterfields_desc'] = 'Adds one filter control to the approval queue for each field ticked here. Three things are worth knowing. The filters read the applicant\'s <em>current profile</em>, not the details they submitted with the application - those are frozen per application and cannot be filtered. A field appears only for a reader who may already see it in that queue, so ticking a box grants nobody anything they did not already have. And the filter row wraps past about three controls, so a short selection reads better than a complete one. The list offers exactly the fields this site names in Site administration / Users / User policies / Show user identity.';
$string['queuefilterfrom'] = 'Applied from';
$string['queuefilternoidentity'] = 'No identity fields are configured for this site, so there is nothing the queue can offer as a filter. Set Show user identity in Site administration / Users / User policies first.';
$string['queuefiltersgroup'] = 'Narrow the queue';
$string['queuefilterstatus'] = 'Application status';
$string['queuefilterto'] = 'Applied to';
$string['queueplacestaken'] = 'Places taken (approved)';
$string['queueremaining'] = '{$a} more applications will be accepted before the method closes itself.';
$string['queueremovefilter'] = 'Remove the filter {$a->name}: {$a->value}';
$string['queuereview'] = 'Review';
$string['queuereviewapplicant'] = 'Review the application from {$a}';
$string['queuesearch'] = 'Search';
$string['queuesearch_help'] = 'Narrows the queue to applications whose applicant name, identity fields or comment contain what you type. It does NOT reach the details submitted with an application: those are frozen per application and are shown only to a reader entitled to each one, which no single search could honour. Accents are ignored on MySQL and MariaDB, and on PostgreSQL sites whose database allows the unaccent extension to be installed; elsewhere an accented letter has to be typed as it was entered.';
$string['queueselectedonpage'] = '{$a} selected on this page';
$string['queueshowing'] = 'Showing {$a->from}-{$a->to} of {$a->total}';
$string['queuestatus'] = 'Status';
$string['queuestatusany'] = 'Any status';
$string['queuesubmitted'] = 'Submitted with the application';
$string['queuewaitinglist'] = 'Waiting list';
$string['report:course_applications'] = 'Enrolment applications';
$string['requestedfields'] = 'Profile fields requested';
$string['requestedfields_help'] = 'The profile fields the applicant is asked to fill in. Only fields the administrator allows site wide are offered here. Marking a field as required means an application cannot be submitted without it.';
$string['requiredtoapply'] = 'This is required to apply';
$string['retention_desc'] = 'How long the record of an enrolment application is kept after it was submitted. The record holds the comment and the profile details the applicant entered, together with the decision taken and who took it, and it outlives the enrolment itself.';
$string['retention_heading'] = 'Application records';
$string['retentiondays'] = 'Keep application records for';
$string['retentiondays_desc'] = 'Application records older than this are deleted by a daily scheduled task, whether or not they were ever decided. Set it to zero to keep them forever. Deleting a course removes the personal details from its records at once, whatever this is set to; an erasure request deletes them outright. Note that a record travels in a course backup only when the backup includes users, so with the site-wide "Include enrolled users" backup default switched off, a course that goes through the recycle bin comes back without its application records.';
$string['reviewapplicants'] = 'Applications held';
$string['reviewcancel'] = 'Cancel this application';
$string['reviewcancelaction'] = 'Cancel and unenrol';
$string['reviewcancelconfirm'] = 'Cancel this application?';
$string['reviewcancelconfirm_desc'] = 'Cancelling removes {$a}\'s enrolment from the course, along with the comment they submitted. This cannot be undone: to reconsider later, defer the application instead.';
$string['reviewcapacity'] = 'Where this lands';
$string['reviewconfirm'] = 'Confirm this application';
$string['reviewdecision'] = 'The decision so far';
$string['reviewdeferred'] = 'Deferred';
$string['reviewdeferredby'] = 'Deferred by {$a->who} on {$a->when}.';
$string['reviewdeferredon'] = 'Deferred on {$a}.';
$string['reviewhistory'] = 'Earlier applications to this course';
$string['reviewkeep'] = 'Keep the application';
$string['reviewnavigation'] = 'Applications awaiting a decision';
$string['reviewnext'] = 'Next: {$a}';
$string['reviewnolimit'] = 'No limit';
$string['reviewofmany'] = '{$a->taken} of {$a->total}';
$string['reviewprevious'] = 'Previous: {$a}';
$string['reviewqueue'] = 'All applications';
$string['reviewtitle'] = '{$a->applicant} - {$a->course}';
$string['reviewwait'] = 'Defer this application';
$string['saveforfuture'] = 'Save these details for next time?';
$string['saveforfuture_desc'] = 'Nothing has been saved to your profile yet. Saving these means you will not have to enter them again for your next application.';
$string['saveforfutureinstance'] = 'Offer to save details to the profile';
$string['saveforfutureinstance_help'] = 'After submitting an application, offer the applicant the chance to save the details they entered to their own profile. Only fields the applicant is allowed to edit are ever written, and only when they accept the offer. This is only available while the site administrator allows it.';
$string['selectapplicant'] = 'Select {$a}';
$string['sendexpirynotificationstask'] = 'Apply enrolment send expiry notifications task';
$string['startapplication'] = 'Start application';
$string['status'] = 'Allow Course enrol confirmation';
$string['status_desc'] = 'Allow course access of internally enrolled users.';
$string['submissiondecidedby'] = 'Decided by';
$string['submissionenrolment'] = 'Enrolment now';
$string['submissionmethod'] = 'Enrolment method';
$string['submissionoutcome'] = 'Outcome';
$string['submissionsnapshot'] = 'Details submitted';
$string['submissionstatus'] = 'Status';
$string['submissionstatusapproved'] = 'Approved';
$string['submissionstatuscancelled'] = 'Cancelled';
$string['submissionstatuspending'] = 'Pending';
$string['submissionstatuswaiting'] = 'Deferred';
$string['submissiontimecreated'] = 'Submitted on';
$string['submissiontimedecided'] = 'Decided on';
$string['submitapplication'] = 'Submit application';
$string['submitted_info'] = 'Enrol info';
$string['submittedprofile'] = 'Details submitted with this application';
$string['syncenrolmentstask'] = 'Apply enrolment synchronise expired enrolments task';
$string['updateprofile'] = 'Save to my profile';
$string['user_profile'] = 'User Profile';
$string['waitmail_desc'] = '';
$string['waitmail_heading'] = 'Deferral email';
$string['waitmailcontent'] = 'Deferral email content';
$string['waitmailcontent_default'] = '<p>Hello {firstname},</p><p>Your enrolment application for {content} has been deferred. No decision has been taken on it yet; you will be notified by email when it is.</p>';
$string['waitmailcontent_desc'] = 'Leave empty to use the wording this plugin ships. Please use the following special marks to replace email content with data from Moodle.<br/>{firstname}:The first name of the user; {content}:The course name;{lastname}:The last name of the user;{username}:The users registration username';
$string['waitmailsubject'] = 'Deferral email subject';
$string['waitmailsubject_default'] = 'Your enrolment application for {$a} was deferred';
$string['waitmailsubject_desc'] = 'Leave empty to use the wording this plugin ships.';
$string['whatyouentered'] = 'What you entered';
$string['youwillchecknddetails'] = 'You will be asked to check a few details before your application is submitted for review.';
