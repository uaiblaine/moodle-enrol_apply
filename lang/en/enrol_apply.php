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
$string['applicationcancelednotification'] = 'Your course enrolment application was canceled.';
$string['applicationconfirmednotification'] = 'Your course enrolment application was confirmed.';
$string['applicationdeferrednotification'] = 'Your course enrolment application was deferred (you are currently on the waiting list).';
$string['applicationsubmitted'] = 'Application submitted';
$string['applicationsubmitted_body'] = 'Your application has been submitted and is waiting for a decision. You will be notified once it has been reviewed.';
$string['applicationsupdated'] = 'The selected enrolment applications have been updated.';
$string['apply:config'] = 'Configure apply enrol instances';
$string['apply:manage'] = 'Manage user enrolments';
$string['apply:manageapplications'] = 'Manage apply enrolment';
$string['apply:unenrol'] = 'Cancel users from the course';
$string['apply:unenrolself'] = 'Cancel self from the course';
$string['applycomment'] = 'Comment';
$string['applydate'] = 'Enrol date';
$string['applymanage'] = 'Manage enrolment applications';
$string['applyuser'] = 'First name / Surname';
$string['btncancel'] = 'Cancel requests';
$string['btnconfirm'] = 'Confirm requests';
$string['btnwait'] = 'Defer requests';
$string['cancelmail_desc'] = '';
$string['cancelmail_heading'] = 'Cancelation email';
$string['cancelmailcontent'] = 'Cancelation email content';
$string['cancelmailcontent_desc'] = 'Please use the following special marks to replace email content with data from Moodle.<br/>{firstname}:The first name of the user; {content}:The course name;{lastname}:The last name of the user;{username}:The users registration username';
$string['cancelmailsubject'] = 'Cancelation email subject';
$string['cancelmailsubject_desc'] = '';
$string['canntenrolearly'] = 'You cannot apply yet; applications open on {$a}.';
$string['canntenrollate'] = 'You cannot apply any more, since applications closed on {$a}.';
$string['cantenrol'] = 'Enrolment is disabled or inactive';
$string['checkyourdetails'] = 'Check your details';
$string['cohortnonmemberinfo'] = 'Only members of cohort \'{$a}\' can apply for enrolment.';
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
$string['confirmmailcontent_desc'] = 'Please use the following special marks to replace email content with data from Moodle.<br/>{firstname}:The first name of the user; {content}:The course name;{lastname}:The last name of the user;{username}:The users registration username;{timeend}: The enrolment expiration date';
$string['confirmmailsubject'] = 'Confirmation email subject';
$string['confirmmailsubject_desc'] = '';
$string['confirmusers'] = 'Enrol Confirm';
$string['confirmusers_desc'] = 'Users in gray colored rows are on the waiting list.';
$string['coursename'] = 'Course';
$string['custom_label'] = 'Custom label';
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
$string['enrolstartdate'] = 'Applications open';
$string['enrolstartdate_help'] = 'If enabled, applications can be submitted from this date onward only. This is separate from the enrolment duration, which decides how long an approved enrolment lasts.';
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
$string['maxenrolled'] = 'Max enrolled users';
$string['maxenrolled_help'] = 'Specifies the maximum number of users that can apply for this course. 0 means no limit.';
$string['maxenrolled_tip'] = '{$a->count} out of {$a->max} seats already booked.';
$string['maxenrolledreached'] = 'The maximum number of users allowed ({$a}) has already been reached.';
$string['messageprovider:application'] = 'Course enrolment application notifications';
$string['messageprovider:cancelation'] = 'Course enrolment application cancelation notifications';
$string['messageprovider:confirmation'] = 'Course enrolment application confirmation notifications';
$string['messageprovider:expiry_notification'] = 'Apply enrolment expiry notifications';
$string['messageprovider:waitinglist'] = 'Course enrolment application defer notifications';
$string['newapplicationnotification'] = 'There is a new course enrolment application awaiting review.';
$string['newenrols'] = 'Allow new course enrol request';
$string['newenrols_desc'] = 'Allow users to apply for an enrolment in new instances by default.';
$string['nofieldsoffered'] = 'The administrator does not currently allow any profile field to be collected with an application.';
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
$string['privacy:metadata:enrol_apply_submission:enrolid'] = 'The enrolment method the application was submitted to.';
$string['privacy:metadata:enrol_apply_submission:outcomemessage'] = 'The message the decider wrote to the applicant.';
$string['privacy:metadata:enrol_apply_submission:status'] = 'Whether the application is pending, approved, on the waiting list or cancelled.';
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
$string['requestedfields'] = 'Profile fields requested';
$string['requestedfields_help'] = 'The profile fields the applicant is asked to fill in. Only fields the administrator allows site wide are offered here. Marking a field as required means an application cannot be submitted without it.';
$string['requiredtoapply'] = 'This is required to apply';
$string['retention_desc'] = 'How long the record of an enrolment application is kept after it was submitted. The record holds the comment and the profile details the applicant entered, together with the decision taken and who took it, and it outlives the enrolment itself.';
$string['retention_heading'] = 'Application records';
$string['retentiondays'] = 'Keep application records for';
$string['retentiondays_desc'] = 'Application records older than this are deleted by a daily scheduled task, whether or not they were ever decided. Set it to zero to keep them forever. Deleting a course removes the personal details from its records at once, whatever this is set to; an erasure request deletes them outright. Note that a record travels in a course backup only when the backup includes users, so with the site-wide "Include enrolled users" backup default switched off, a course that goes through the recycle bin comes back without its application records.';
$string['saveforfuture'] = 'Save these details for next time?';
$string['saveforfuture_desc'] = 'Nothing has been saved to your profile yet. Saving these means you will not have to enter them again for your next application.';
$string['saveforfutureinstance'] = 'Offer to save details to the profile';
$string['saveforfutureinstance_help'] = 'After submitting an application, offer the applicant the chance to save the details they entered to their own profile. Only fields the applicant is allowed to edit are ever written, and only when they accept the offer. This is only available while the site administrator allows it.';
$string['selectapplicant'] = 'Select {$a}';
$string['sendexpirynotificationstask'] = 'Apply enrolment send expiry notifications task';
$string['startapplication'] = 'Start application';
$string['status'] = 'Allow Course enrol confirmation';
$string['status_desc'] = 'Allow course access of internally enrolled users.';
$string['submissionstatusapproved'] = 'Approved';
$string['submissionstatuscancelled'] = 'Cancelled';
$string['submissionstatuspending'] = 'Pending';
$string['submissionstatuswaiting'] = 'Waiting list';
$string['submitapplication'] = 'Submit application';
$string['submitted_info'] = 'Enrol info';
$string['syncenrolmentstask'] = 'Apply enrolment synchronise expired enrolments task';
$string['updateprofile'] = 'Save to my profile';
$string['user_profile'] = 'User Profile';
$string['waitmail_desc'] = '';
$string['waitmail_heading'] = 'Waiting list email';
$string['waitmailcontent'] = 'Waiting list mail content';
$string['waitmailcontent_desc'] = 'Please use the following special marks to replace email content with data from Moodle.<br/>{firstname}:The first name of the user; {content}:The course name;{lastname}:The last name of the user;{username}:The users registration username';
$string['waitmailsubject'] = 'Waiting list mail subject';
$string['waitmailsubject_desc'] = '';
$string['whatyouentered'] = 'What you entered';
$string['youwillchecknddetails'] = 'You will be asked to check a few details before your application is submitted for review.';
