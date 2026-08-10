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

$string['applicationcancelednotification'] = 'Your course enrolment application was canceled.';
$string['applicationconfirmednotification'] = 'Your course enrolment application was confirmed.';
$string['applicationdeferrednotification'] = 'Your course enrolment application was deferred (you are currently on the waiting list).';
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
$string['cantenrol'] = 'Enrolment is disabled or inactive';
$string['comment'] = 'Comment';
$string['confirmenrol'] = 'Manage application';
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
$string['editdescription'] = 'Textarea description';
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
$string['group'] = 'Group assignment';
$string['group_help'] = 'You can assign none or multiples groups. Members are added once the enrolment application is approved.';
$string['invalidformaction'] = 'Unknown action requested for the selected enrolment applications.';
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
$string['notification'] = 'Enrolment application successfully sent. You will be informed by email when your enrolment has been confirmed.';
$string['notify_desc'] = 'Define who gets notified about new enrolment applications.';
$string['notify_heading'] = 'Notification settings';
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
$string['profileoption'] = 'Profile Field to Show in Table';
$string['profileoption_desc'] = 'An extra text profile field to show as a column on the enrolment application queue.';
$string['selectapplicant'] = 'Select {$a}';
$string['sendexpirynotificationstask'] = 'Apply enrolment send expiry notifications task';
$string['show_extra_user_profile'] = 'Show extra user profile fields on enrolment screen';
$string['show_extra_user_profile_desc'] = 'Default for new instances: collect the custom user profile fields on the application form.';
$string['show_standard_user_profile'] = 'Show standard user profile fields on enrolment screen';
$string['show_standard_user_profile_desc'] = 'Default for new instances: collect the standard user profile fields on the application form.';
$string['status'] = 'Allow Course enrol confirmation';
$string['status_desc'] = 'Allow course access of internally enrolled users.';
$string['submitted_info'] = 'Enrol info';
$string['syncenrolmentstask'] = 'Apply enrolment synchronise expired enrolments task';
$string['user_profile'] = 'User Profile';
$string['waitmail_desc'] = '';
$string['waitmail_heading'] = 'Waiting list email';
$string['waitmailcontent'] = 'Waiting list mail content';
$string['waitmailcontent_desc'] = 'Please use the following special marks to replace email content with data from Moodle.<br/>{firstname}:The first name of the user; {content}:The course name;{lastname}:The last name of the user;{username}:The users registration username';
$string['waitmailsubject'] = 'Waiting list mail subject';
$string['waitmailsubject_desc'] = '';
