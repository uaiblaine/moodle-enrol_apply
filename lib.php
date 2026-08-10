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
 * Enrolment upon approval plugin.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo.com (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     emeneo.com (http://emeneo.com/)
 * @author     Johannes Burk <johannes.burk@sudile.com>
 */

/**
 * Applicant is on the waiting list, so the enrolment is not active.
 *
 * Stored in user_enrolments.status. Core only defines ENROL_USER_ACTIVE (0) and
 * ENROL_USER_SUSPENDED (1); every core check treats "status != ENROL_USER_ACTIVE"
 * as "no access", so this extra value is inert to core and only this plugin
 * distinguishes it from a plain pending application.
 */
define('ENROL_APPLY_USER_WAIT', 2);

/**
 * Enrolment upon approval plugin implementation.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo.com (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_apply_plugin extends enrol_plugin {
    /** @var stdClass|null Cached enroller user record, see get_enroller(). */
    protected $lasternoller = null;

    /** @var int Instance id the cached enroller in $lasternoller belongs to. */
    protected $lasternollerinstanceid = 0;

    /**
     * Add new instance of enrol plugin with default settings.
     *
     * @param stdClass $course Course to add the instance to.
     * @return int Id of the new instance.
     */
    public function add_default_instance($course) {
        $fields = $this->get_instance_defaults();
        return $this->add_instance($course, $fields);
    }

    /**
     * Users holding the unenrol capability may unenrol other users manually.
     *
     * @param stdClass $instance Course enrol instance.
     * @return bool Always true.
     */
    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    /**
     * Roles assigned by this plugin may be tweaked afterwards.
     *
     * @return bool Always false.
     */
    public function roles_protected() {
        return false;
    }

    /**
     * Check whether the given instance currently accepts applications.
     *
     * @param stdClass $instance Course enrol instance.
     * @return bool|string True when applications are accepted, otherwise the reason to show the user.
     */
    public function allow_apply(stdClass $instance) {
        if ($instance->status != ENROL_INSTANCE_ENABLED) {
            return get_string('cantenrol', 'enrol_apply');
        }
        if (!$instance->customint6) {
            // New enrolments are not allowed on this instance.
            return get_string('cantenrol', 'enrol_apply');
        }
        return true;
    }

    /**
     * Users holding the manage capability may tweak period and status.
     *
     * @param stdClass $instance Course enrol instance.
     * @return bool Always true.
     */
    public function allow_manage(stdClass $instance) {
        return true;
    }

    /**
     * Returns link to the page used to add a new instance of this plugin to a course.
     *
     * Multiple instances are supported.
     *
     * @param int $courseid Course id.
     * @return moodle_url|null Page url, or null when the user may not add an instance.
     */
    public function get_newinstance_link($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) || !has_capability('enrol/apply:config', $context)) {
            return null;
        }
        return new moodle_url('/enrol/apply/edit.php', ['courseid' => $courseid]);
    }

    /**
     * Render the application form on the course enrolment page and process its submission.
     *
     * @param stdClass $instance Course enrol instance.
     * @return string|null Rendered markup, or null when the current user may not apply.
     */
    public function enrol_page_hook(stdClass $instance) {
        global $CFG, $DB, $OUTPUT, $USER;

        if (isguestuser()) {
            // Guests can not apply.
            return null;
        }

        $allowapply = $this->allow_apply($instance);
        if ($allowapply !== true) {
            return $OUTPUT->notification($allowapply, \core\output\notification::NOTIFY_ERROR);
        }

        if ($DB->record_exists('user_enrolments', ['userid' => $USER->id, 'enrolid' => $instance->id])) {
            return $OUTPUT->notification(get_string('notification', 'enrol_apply'), \core\output\notification::NOTIFY_SUCCESS);
        }

        if ($instance->customint3 > 0) {
            // A maximum number of applicants is configured for this instance.
            $count = $DB->count_records('user_enrolments', ['enrolid' => $instance->id]);
            if ($count >= $instance->customint3) {
                $message = get_string('maxenrolledreached', 'enrol_apply', $count);
                return $OUTPUT->notification($message, \core\output\notification::NOTIFY_ERROR);
            }
        }

        require_once($CFG->dirroot . '/enrol/apply/apply_form.php');

        $form = new enrol_apply_apply_form(null, $instance);

        $data = $form->get_data();
        if ($data && $data->instance == $instance->id) {
            // Only process the submission that belongs to this instance (multi-instance support).
            $this->apply($instance, $USER->id, $data);

            $notification = $OUTPUT->notification(
                get_string('notification', 'enrol_apply'),
                \core\output\notification::NOTIFY_SUCCESS
            );
            $button = $OUTPUT->single_button(
                new moodle_url('/course/view.php', ['id' => $instance->courseid]),
                get_string('continue')
            );
            return $notification . $button;
        }

        return $OUTPUT->box($form->render());
    }

    /**
     * Record a new enrolment application for the given user.
     *
     * The enrolment is created suspended: the applicant gains no course access until
     * a manager confirms it through confirm_enrolment().
     *
     * The enrolment period deliberately does NOT start here. It is stamped on approval by
     * confirm_enrolment(), for two reasons: the clock should not run while the applicant
     * has no access, and a timeend on a pending row is actively dangerous — with
     * expiredaction set to "unenrol", process_expirations() sweeps every row with
     * timeend > 0 AND timeend < now with no status filter at all
     * (lib/enrollib.php, the ENROL_EXT_REMOVED_UNENROL branch), so an application nobody
     * got round to reviewing would be deleted instead of decided.
     *
     * @param stdClass $instance Course enrol instance.
     * @param int $userid Applicant user id.
     * @param stdClass $data Submitted application form data.
     * @return void
     */
    protected function apply($instance, $userid, $data) {
        global $DB;

        $this->enrol_user($instance, $userid, $instance->roleid, 0, 0, ENROL_USER_SUSPENDED);

        $userenrolment = $DB->get_record(
            'user_enrolments',
            ['userid' => $userid, 'enrolid' => $instance->id],
            'id',
            MUST_EXIST
        );

        $applicationinfo = new stdClass();
        $applicationinfo->userenrolmentid = $userenrolment->id;
        $applicationinfo->comment = isset($data->applydescription) ? $data->applydescription : '';
        $DB->insert_record('enrol_apply_applicationinfo', $applicationinfo);

        $this->send_application_notification($instance, $userid, $data);
    }

    /**
     * Add the applicant to every group configured on the instance.
     *
     * Memberships are tagged with this plugin as their component so that core removes
     * them again when the enrolment goes away (see unenrol_user() in lib/enrollib.php,
     * which only cleans up memberships carrying component 'enrol_apply').
     *
     * @param stdClass $instance Course enrol instance.
     * @param int $userid User to add to the configured groups.
     * @return void
     */
    protected function add_instance_groups($instance, $userid) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');

        /* Only groups that still belong to this course are used: a group deleted after
           the instance was configured leaves its mapping row behind, and groups_add_member()
           throws on an unknown group id. */
        $groups = $DB->get_records_sql(
            "SELECT g.id
               FROM {enrol_apply_groups} eag
               JOIN {groups} g ON g.id = eag.groupid AND g.courseid = :courseid
              WHERE eag.enrolid = :enrolid",
            ['courseid' => $instance->courseid, 'enrolid' => $instance->id]
        );
        foreach ($groups as $group) {
            groups_add_member($group->id, $userid, 'enrol_apply', $instance->id);
        }
    }

    /**
     * Returns the action icons shown for this instance on the course enrolment methods page.
     *
     * @param stdClass $instance Course enrol instance.
     * @return array Array of rendered action icons.
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'apply') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = [];

        if (has_capability('enrol/apply:config', $context)) {
            $editlink = new moodle_url('/enrol/apply/edit.php', [
                'courseid' => $instance->courseid,
                'id' => $instance->id,
            ]);
            $icons[] = $OUTPUT->action_icon(
                $editlink,
                new pix_icon('t/edit', get_string('edit'), 'core', ['class' => 'iconsmall'])
            );
        }

        if (has_capability('enrol/apply:manageapplications', $context)) {
            $managelink = new moodle_url('/enrol/apply/manage.php', ['id' => $instance->id]);
            $icons[] = $OUTPUT->action_icon(
                $managelink,
                new pix_icon('i/users', get_string('confirmenrol', 'enrol_apply'), 'core', ['class' => 'iconsmall'])
            );

            $infolink = new moodle_url('/enrol/apply/info.php', ['id' => $instance->id]);
            $icons[] = $OUTPUT->action_icon(
                $infolink,
                new pix_icon('i/files', get_string('submitted_info', 'enrol_apply'), 'core', ['class' => 'iconsmall'])
            );
        }

        return $icons;
    }

    /**
     * Is it possible to hide/show the enrol instance via the standard UI?
     *
     * @param stdClass $instance Course enrol instance.
     * @return bool True when the current user may toggle the instance.
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/apply:config', $context);
    }

    /**
     * Is it possible to delete the enrol instance via the standard UI?
     *
     * @param stdClass $instance Course enrol instance.
     * @return bool True when the current user may delete the instance.
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/apply:config', $context);
    }

    /**
     * Delete an instance together with the plugin data hanging off it.
     *
     * Core removes the user_enrolments rows, which cascades nothing on its own, so the
     * application info and group mapping rows have to be dropped here.
     *
     * @param stdClass $instance Course enrol instance.
     * @return void
     */
    public function delete_instance($instance) {
        global $DB;

        $userenrolments = $DB->get_fieldset_select('user_enrolments', 'id', 'enrolid = :enrolid', [
            'enrolid' => $instance->id,
        ]);
        if ($userenrolments) {
            [$insql, $params] = $DB->get_in_or_equal($userenrolments, SQL_PARAMS_NAMED);
            $DB->delete_records_select('enrol_apply_applicationinfo', "userenrolmentid {$insql}", $params);
        }
        $DB->delete_records('enrol_apply_groups', ['enrolid' => $instance->id]);

        parent::delete_instance($instance);
    }

    /**
     * Sets up navigation entries.
     *
     * @param navigation_node $instancesnode Node to add the instance link to.
     * @param stdClass $instance Course enrol instance.
     * @return void
     */
    public function add_course_navigation($instancesnode, stdClass $instance) {
        if ($instance->enrol !== 'apply') {
            throw new coding_exception('Invalid enrol instance type!');
        }

        $context = context_course::instance($instance->courseid);
        if (has_capability('enrol/apply:config', $context)) {
            $managelink = new moodle_url('/enrol/apply/edit.php', [
                'courseid' => $instance->courseid,
                'id' => $instance->id,
            ]);
            $instancesnode->add($this->get_instance_name($instance), $managelink, navigation_node::TYPE_SETTING);
        }
    }

    /**
     * Returns the defaults used for new instances.
     *
     * @return array Instance field defaults.
     */
    public function get_instance_defaults() {
        $fields = [];
        $fields['status'] = $this->get_config('status');
        $fields['roleid'] = $this->get_config('roleid', 0);
        $fields['customint1'] = $this->get_config('show_standard_user_profile');
        $fields['customint2'] = $this->get_config('show_extra_user_profile');
        $fields['customint3'] = (int) $this->get_config('maxenrolled', 0);
        $fields['customint6'] = $this->get_config('newenrols');
        $fields['customint7'] = (int) $this->get_config('opt_commentaryzone', 0);
        $fields['customtext2'] = '';
        $fields['customtext3'] = $this->get_config('notifycoursebased') ? '$@ALL@$' : '';
        $fields['enrolperiod'] = $this->get_config('enrolperiod', 0);

        return $fields;
    }

    /**
     * Whether the current user may decide on an application.
     *
     * Three delegation levels are accepted, in order of decreasing scope:
     *  - the capability held at system level, which covers every course;
     *  - the capability held in the course the application belongs to;
     *  - the capability held in the applicant's own user context, which lets a
     *    mentor decide for the users assigned to them regardless of the course.
     *
     * @param int $courseid Course the application belongs to.
     * @param int $userid Applicant user id.
     * @return bool True when the current user may confirm, defer or cancel the application.
     */
    public function can_manage_application($courseid, $userid) {
        if (has_capability('enrol/apply:manageapplications', context_system::instance())) {
            return true;
        }

        $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
        if ($coursecontext && has_capability('enrol/apply:manageapplications', $coursecontext)) {
            return true;
        }

        $usercontext = context_user::instance($userid, IGNORE_MISSING);
        if ($usercontext && has_capability('enrol/apply:manageapplications', $usercontext)) {
            return true;
        }

        return false;
    }

    /**
     * Confirm the given applications, activating the enrolments.
     *
     * Every application is authorised individually: an id the current user may not act
     * on is skipped rather than failing the whole batch.
     *
     * @param array $enrols User enrolment ids to confirm.
     * @return void
     */
    public function confirm_enrolment($enrols) {
        global $DB;

        foreach ($enrols as $enrol) {
            $userenrolment = $this->get_pending_user_enrolment($enrol);
            if (!$userenrolment) {
                continue;
            }

            $instance = $DB->get_record('enrol', ['id' => $userenrolment->enrolid, 'enrol' => 'apply'], '*', MUST_EXIST);

            if (!$this->can_manage_application($instance->courseid, $userenrolment->userid)) {
                continue;
            }

            // Set timestart and timeend if an enrolment duration is configured.
            $userenrolment->timestart = time();
            $userenrolment->timeend = 0;
            if ($instance->enrolperiod) {
                $userenrolment->timeend = $userenrolment->timestart + $instance->enrolperiod;
            }

            $this->update_user_enrol(
                $instance,
                $userenrolment->userid,
                ENROL_USER_ACTIVE,
                $userenrolment->timestart,
                $userenrolment->timeend
            );

            // Group membership follows approval, never the bare application.
            $this->add_instance_groups($instance, $userenrolment->userid);

            $DB->delete_records('enrol_apply_applicationinfo', ['userenrolmentid' => $userenrolment->id]);

            $this->notify_applicant(
                $instance,
                $userenrolment,
                'confirmation',
                get_config('enrol_apply', 'confirmmailsubject'),
                get_config('enrol_apply', 'confirmmailcontent')
            );
        }
    }

    /**
     * Move the given applications onto the waiting list.
     *
     * @param array $enrols User enrolment ids to defer.
     * @return void
     */
    public function wait_enrolment($enrols) {
        global $DB;

        foreach ($enrols as $enrol) {
            $userenrolment = $DB->get_record(
                'user_enrolments',
                ['id' => $enrol, 'status' => ENROL_USER_SUSPENDED],
                '*',
                IGNORE_MISSING
            );
            if (!$userenrolment) {
                continue;
            }

            $instance = $DB->get_record('enrol', ['id' => $userenrolment->enrolid, 'enrol' => 'apply'], '*', MUST_EXIST);

            if (!$this->can_manage_application($instance->courseid, $userenrolment->userid)) {
                continue;
            }

            $this->update_user_enrol($instance, $userenrolment->userid, ENROL_APPLY_USER_WAIT);

            $this->notify_applicant(
                $instance,
                $userenrolment,
                'waitinglist',
                get_config('enrol_apply', 'waitmailsubject'),
                get_config('enrol_apply', 'waitmailcontent')
            );
        }
    }

    /**
     * Cancel the given applications, unenrolling the applicants.
     *
     * @param array $enrols User enrolment ids to cancel.
     * @return void
     */
    public function cancel_enrolment($enrols) {
        global $DB;

        foreach ($enrols as $enrol) {
            $userenrolment = $this->get_pending_user_enrolment($enrol);
            if (!$userenrolment) {
                continue;
            }

            $instance = $DB->get_record('enrol', ['id' => $userenrolment->enrolid, 'enrol' => 'apply'], '*', MUST_EXIST);

            if (!$this->can_manage_application($instance->courseid, $userenrolment->userid)) {
                continue;
            }

            $this->unenrol_user($instance, $userenrolment->userid);
            $DB->delete_records('enrol_apply_applicationinfo', ['userenrolmentid' => $userenrolment->id]);

            $this->notify_applicant(
                $instance,
                $userenrolment,
                'cancelation',
                get_config('enrol_apply', 'cancelmailsubject'),
                get_config('enrol_apply', 'cancelmailcontent')
            );
        }
    }

    /**
     * Fetch a user enrolment that is still awaiting a decision.
     *
     * @param int $userenrolmentid User enrolment id.
     * @return stdClass|false The user enrolment record, or false when it is not pending.
     */
    protected function get_pending_user_enrolment($userenrolmentid) {
        global $DB;

        return $DB->get_record_select(
            'user_enrolments',
            'id = :id AND (status = :enrolusersuspended OR status = :enrolapplyuserwait)',
            [
                'id' => $userenrolmentid,
                'enrolusersuspended' => ENROL_USER_SUSPENDED,
                'enrolapplyuserwait' => ENROL_APPLY_USER_WAIT,
            ],
            '*',
            IGNORE_MISSING
        );
    }

    /**
     * Notify the applicant about the decision taken on their application.
     *
     * @param stdClass $instance Course enrol instance.
     * @param stdClass $userenrolment User enrolment the decision applies to.
     * @param string $type Notification type: confirmation, cancelation or waitinglist.
     * @param string $subject Message subject taken from the plugin settings.
     * @param string $content Message body taken from the plugin settings.
     * @return void
     */
    protected function notify_applicant($instance, $userenrolment, $type, $subject, $content) {
        global $CFG;

        require_once($CFG->dirroot . '/enrol/apply/notification.php');
        // Required for the course_get_url() function.
        require_once($CFG->dirroot . '/course/lib.php');

        $course = get_course($instance->courseid);
        $user = core_user::get_user($userenrolment->userid);
        if (!$user) {
            return;
        }

        $content = $this->update_mail_content($content, $course, $user, $userenrolment);

        $message = new enrol_apply_notification(
            $user,
            core_user::get_support_user(),
            $type,
            $subject,
            $content,
            course_get_url($course),
            $instance->courseid
        );
        message_send($message);
    }

    /**
     * Notify everybody configured to hear about a new application.
     *
     * @param stdClass $instance Course enrol instance.
     * @param int $userid Applicant user id.
     * @param stdClass $data Submitted application form data.
     * @return void
     */
    protected function send_application_notification($instance, $userid, $data) {
        global $CFG, $DB, $PAGE;

        require_once($CFG->dirroot . '/enrol/apply/notification.php');
        // Required for the course_get_url() function.
        require_once($CFG->dirroot . '/course/lib.php');

        $renderer = $PAGE->get_renderer('enrol_apply');

        $course = get_course($instance->courseid);
        $applicant = core_user::get_user($userid);
        $applydescription = isset($data->applydescription) ? $data->applydescription : '';

        // Include the standard user profile fields?
        $standarduserfields = null;
        if ($instance->customint1) {
            $standarduserfields = clone $data;
            unset($standarduserfields->applydescription);
        }

        // Include the extra user profile fields?
        $extrauserfields = null;
        if ($instance->customint2) {
            require_once($CFG->dirroot . '/user/profile/lib.php');
            profile_load_custom_fields($applicant);
            $extrauserfields = $applicant->profile;
        }

        // Notify users holding the capability in the course context.
        $recipients = $this->get_notifycoursebased_users($instance);
        $notified = [];
        if ($recipients) {
            $manageurl = new moodle_url('/enrol/apply/manage.php', ['id' => $instance->id]);
            $content = $renderer->application_notification_mail_body(
                $course,
                $applicant,
                $manageurl,
                $applydescription,
                $standarduserfields,
                $extrauserfields
            );
            foreach ($recipients as $user) {
                $this->send_application_notification_to($user, $applicant, $content, $manageurl, $instance->courseid);
                $notified[$user->id] = true;
            }
        }

        // Notify users holding the capability in the applicant's own user context.
        $recipients = $this->get_notifyuserbased_users($userid);
        if ($recipients) {
            $userenrolment = $DB->get_record('user_enrolments', ['userid' => $userid, 'enrolid' => $instance->id]);
            $manageurl = new moodle_url('/enrol/apply/manage.php', ['userenrol' => $userenrolment->id]);
            $content = $renderer->application_notification_mail_body(
                $course,
                $applicant,
                $manageurl,
                $applydescription,
                $standarduserfields,
                $extrauserfields
            );
            foreach ($recipients as $user) {
                if (isset($notified[$user->id])) {
                    continue;
                }
                $this->send_application_notification_to($user, $applicant, $content, $manageurl, $instance->courseid);
                $notified[$user->id] = true;
            }
        }

        // Notify users configured globally in the plugin settings.
        $recipients = $this->get_notifyglobal_users();
        if ($recipients) {
            $manageurl = new moodle_url('/enrol/apply/manage.php');
            $content = $renderer->application_notification_mail_body(
                $course,
                $applicant,
                $manageurl,
                $applydescription,
                $standarduserfields,
                $extrauserfields
            );
            foreach ($recipients as $user) {
                if (isset($notified[$user->id])) {
                    continue;
                }
                $this->send_application_notification_to($user, $applicant, $content, $manageurl, $instance->courseid);
                $notified[$user->id] = true;
            }
        }
    }

    /**
     * Send one new-application notification.
     *
     * @param stdClass $recipient User to notify.
     * @param stdClass $applicant User who applied.
     * @param string $content Rendered message body.
     * @param moodle_url $manageurl Link to the screen where the application can be decided.
     * @param int $courseid Course the application belongs to.
     * @return void
     */
    protected function send_application_notification_to($recipient, $applicant, $content, $manageurl, $courseid) {
        $message = new enrol_apply_notification(
            $recipient,
            $applicant,
            'application',
            get_string('mailtoteacher_subject', 'enrol_apply'),
            $content,
            $manageurl,
            $courseid
        );
        message_send($message);
    }

    /**
     * Returns enrolled users of a course who should be notified about new applications.
     *
     * Note: mostly copied from the get_users_from_config() function in moodlelib.php.
     *
     * @param stdClass $instance Enrol apply instance record.
     * @return array Array of user records keyed by user id.
     */
    public function get_notifycoursebased_users($instance) {
        $value = $instance->customtext3;
        if (empty($value) || $value === '$@NONE@$') {
            return [];
        }

        $context = context_course::instance($instance->courseid);

        /* We have to make sure that users still hold the necessary capability. It is
           faster to fetch them all first and then test whether they are present than
           to validate them one by one. */
        $users = get_enrolled_users($context, 'enrol/apply:manageapplications');

        if ($value === '$@ALL@$') {
            return $users;
        }

        $result = [];
        $allowed = explode(',', $value);
        foreach ($allowed as $uid) {
            if (isset($users[$uid])) {
                $result[$uid] = $users[$uid];
            }
        }

        return $result;
    }

    /**
     * Returns users holding the manage capability in the applicant's own user context.
     *
     * This is what lets a mentor decide on the applications of the users assigned to them.
     *
     * @param int $userid Applicant user id.
     * @return array Array of user records keyed by user id.
     */
    public function get_notifyuserbased_users($userid) {
        $usercontext = context_user::instance($userid, IGNORE_MISSING);
        if (!$usercontext) {
            return [];
        }

        return get_users_by_capability($usercontext, 'enrol/apply:manageapplications');
    }

    /**
     * Returns users who should be notified about new applications for any course.
     *
     * @return array Array of user records keyed by user id.
     */
    public function get_notifyglobal_users() {
        return get_users_from_config($this->get_config('notifyglobal'), 'enrol/apply:manageapplications', false);
    }

    /**
     * Replace the supported placeholders in a configured notification body.
     *
     * The result is sent as HTML, so every substituted value is escaped.
     *
     * @param string $content Configured message body.
     * @param stdClass $course Course the application belongs to.
     * @param stdClass $user Applicant user record.
     * @param stdClass $userenrolment User enrolment the message is about.
     * @return string The message body with placeholders replaced.
     */
    protected function update_mail_content($content, $course, $user, $userenrolment) {
        $replace = [
            'firstname' => s($user->firstname),
            'content' => format_string($course->fullname),
            'lastname' => s($user->lastname),
            'username' => s($user->username),
            'timeend' => !empty($userenrolment->timeend) ? userdate($userenrolment->timeend) : '',
        ];
        foreach ($replace as $key => $val) {
            $content = str_replace('{' . $key . '}', $val, $content);
        }
        return $content;
    }

    /**
     * Unenrol a user, taking this plugin's own rows with them.
     *
     * Core deletes the user_enrolments row but knows nothing about the application info
     * hanging off it, and unenrolment happens from plenty of places this plugin does not
     * control (the participants page, user deletion, course deletion). Without this the
     * comment outlives the enrolment it belongs to, which also makes it invisible to the
     * privacy provider, whose queries join through user_enrolments.
     *
     * @param stdClass $instance Course enrol instance.
     * @param int $userid User being unenrolled.
     * @return void
     */
    public function unenrol_user(stdClass $instance, $userid) {
        global $DB;

        $userenrolmentid = $DB->get_field('user_enrolments', 'id', [
            'enrolid' => $instance->id,
            'userid' => $userid,
        ]);
        if ($userenrolmentid) {
            $DB->delete_records('enrol_apply_applicationinfo', ['userenrolmentid' => $userenrolmentid]);
        }

        parent::unenrol_user($instance, $userid);
    }

    /**
     * Restore an enrol instance from a backup file.
     *
     * @param restore_enrolments_structure_step $step Restore step running the restore.
     * @param stdClass $data Instance data from the backup file.
     * @param stdClass $course Course being restored into.
     * @param int $oldid Instance id recorded in the backup file.
     * @return void
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        /* customtext3 holds either a marker or a list of user ids to notify. Ids from
           another site point at different people here, so anything but the "everyone"
           marker degrades to "nobody" on a cross-site restore. */
        if (!empty($data->customtext3) && $data->customtext3 !== '$@ALL@$' && !$step->get_task()->is_samesite()) {
            $data->customtext3 = '';
        }

        $instanceid = $this->add_instance($course, (array) $data);
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore a single user enrolment from a backup file.
     *
     * @param restore_enrolments_structure_step $step Restore step running the restore.
     * @param stdClass $data User enrolment data from the backup file.
     * @param stdClass $instance Enrol instance the enrolment belongs to.
     * @param int $userid User the enrolment belongs to.
     * @param int $oldinstancestatus Status the instance had when the backup was taken.
     * @return void
     */
    public function restore_user_enrolment(
        restore_enrolments_structure_step $step,
        $data,
        $instance,
        $userid,
        $oldinstancestatus
    ) {
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }

    /**
     * Returns the user who is responsible for enrolments in the given instance.
     *
     * Usually the editing teacher with the "highest authority" as defined by
     * sort_by_roleassignment_authority() who holds 'enrol/apply:manage'.
     *
     * @param int $instanceid Enrolment instance id.
     * @return stdClass User record.
     */
    protected function get_enroller($instanceid) {
        global $DB;

        if ($this->lasternollerinstanceid == $instanceid && $this->lasternoller) {
            return $this->lasternoller;
        }

        $instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => $this->get_name()], '*', MUST_EXIST);
        $context = context_course::instance($instance->courseid);

        if ($users = get_enrolled_users($context, 'enrol/apply:manage')) {
            $users = sort_by_roleassignment_authority($users, $context);
            $this->lasternoller = reset($users);
            unset($users);
        } else {
            $this->lasternoller = parent::get_enroller($instanceid);
        }

        $this->lasternollerinstanceid = $instanceid;

        return $this->lasternoller;
    }
}
