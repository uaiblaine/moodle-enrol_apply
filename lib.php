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
    /**
     * Whether an unfiltered manager has already been offered the participants-page bulk menu.
     *
     * @var bool
     */
    protected $bulkmenuoffered = false;

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
     * Check whether the given instance currently accepts applications from a user.
     *
     * The whole eligibility predicate lives here, not in enrol_page_hook(), because the hook
     * is only one of its callers. enrol_page_hook() renders the refusal raw in a core
     * notification, so every refusal is a language string with no user-controlled detail.
     *
     * The applicant defaults to the current user, the contract plugins outside this
     * repository rely on when they reach this method through is_callable() with one argument.
     * submit_application() passes its own $userid, because the cohort clause is the one
     * restriction that asks about a person rather than about the instance.
     *
     * @param stdClass $instance Course enrol instance.
     * @param int|null $userid Applicant to judge, or null for the current user.
     * @return bool|string True when applications are accepted, otherwise the reason to show the user.
     */
    public function allow_apply(stdClass $instance, ?int $userid = null) {
        global $CFG, $DB, $USER;

        $userid = $userid ?? (int) $USER->id;

        if ($instance->status != ENROL_INSTANCE_ENABLED) {
            return get_string('cantenrol', 'enrol_apply');
        }
        if (!$instance->customint6) {
            // New enrolments are not allowed on this instance.
            return get_string('cantenrol', 'enrol_apply');
        }

        $now = time();
        $startdate = (int) ($instance->enrolstartdate ?? 0);
        if ($startdate > 0 && $startdate > $now) {
            return get_string('canntenrolearly', 'enrol_apply', userdate($startdate));
        }
        $enddate = (int) ($instance->enrolenddate ?? 0);
        if ($enddate > 0 && $enddate < $now) {
            return get_string('canntenrollate', 'enrol_apply', userdate($enddate));
        }

        $cohortid = (int) ($instance->customint5 ?? 0);
        if ($cohortid < 0) {
            /* The sentinel restore_instance() writes when a restricted instance lands on
               another site: there WAS a restriction and this site cannot honour it. Reading
               it as "no restriction" would fail open and defeat the sentinel entirely. */
            return get_string('cohortunresolved', 'enrol_apply');
        }
        if ($cohortid > 0) {
            require_once($CFG->dirroot . '/cohort/lib.php');

            /* Read the cohort with a plain get_record() rather than cohort_get_cohort(): that
               helper refuses a hidden cohort to anybody without moodle/cohort:view, which an
               applicant does not hold, and would turn the restriction into "unresolved". Only
               the existence of the row is used - see the refusal below. */
            $cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id');
            if (!$cohort) {
                // The cohort was deleted. Fail closed, and with a string the caller can render.
                return get_string('cohortunresolved', 'enrol_apply');
            }
            if (!cohort_is_member($cohortid, $userid)) {
                /* Unlike enrol_self's 'cohortnonmemberinfo', the refusal does not name the
                   cohort: it is shown to any authenticated non-member, and a cohort's name can
                   itself be sensitive. The applicant still learns that the course is
                   restricted, which is what they can act on. */
                return get_string('cohortnonmemberinfo', 'enrol_apply');
            }
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
     * Render this method's card on the course enrolment page.
     *
     * One short card per enrolment method, and a button that opens the application form -
     * in a modal where JavaScript is available, and on a page of its own where it is not.
     * The form is not rendered inline because two apply instances on one page would emit
     * every profile element twice, duplicating their ids.
     *
     * @param stdClass $instance Course enrol instance.
     * @return string|null Rendered markup, or null when the current user may not apply.
     */
    public function enrol_page_hook(stdClass $instance) {
        global $DB, $OUTPUT, $PAGE, $USER;

        if (isguestuser()) {
            // Guests can not apply.
            return null;
        }

        $title = $this->get_instance_name($instance);
        $buttonurl = null;
        $buttonattrs = [];

        /* The applicant's own row is tested before allow_apply(): once a method stops accepting
           applications (window closed, instance disabled, cohort changed), somebody who already
           applied must be told about their application, not "Enrolment is disabled or
           inactive". IGNORE_MULTIPLE is defensive; {user_enrolments} is unique on
           (enrolid, userid). */
        $ownrow = $DB->get_record(
            'user_enrolments',
            ['userid' => $USER->id, 'enrolid' => $instance->id],
            'id, status',
            IGNORE_MULTIPLE
        );

        $notifytype = \core\output\notification::NOTIFY_INFO;
        $allowapply = $ownrow ? true : $this->allow_apply($instance);

        if ($ownrow) {
            /* \enrol_apply\local\applicantstate describes the row, shared with applied.php and
               the application form so the three surfaces agree. Access is asked of core rather
               than assumed false, although enrol/index.php redirects an actively enrolled user
               before this panel is shown. */
            $state = \enrol_apply\local\applicantstate::describe(
                $ownrow,
                is_enrolled(context_course::instance($instance->courseid), $USER, '', true)
            );
            $body = $state['message'];
            $notifytype = $state['type'];
        } else if ($allowapply !== true) {
            $body = $allowapply;
        } else if (\enrol_apply\local\capacity::applications_closed($instance)) {
            $body = get_string('maxenrolledreached', 'enrol_apply');
        } else {
            $body = get_string('youwillchecknddetails', 'enrol_apply');
            /* The href is the no-JavaScript transport and is a real destination, not a
               placeholder: the AMD module below intercepts the click only when it loads. */
            $buttonurl = new moodle_url('/enrol/apply/apply.php', ['instance' => $instance->id]);
            $buttonattrs = [
                'data-id' => $instance->courseid,
                'data-instance' => $instance->id,
                'data-form' => \enrol_apply\form\application_form::class,
                'data-title' => $title,
            ];
            $PAGE->requires->js_call_amd('enrol_apply/enrol_page', 'init', [$instance->id]);
        }

        $notification = new \core\output\notification($body, $notifytype, false);
        $notification->set_extra_classes(['mb-0']);

        $page = new \core_enrol\output\enrol_page(
            instance: $instance,
            header: $title,
            body: $OUTPUT->render($notification),
            buttons: $buttonurl ? [new single_button(
                $buttonurl,
                get_string('startapplication', 'enrol_apply'),
                'get',
                single_button::BUTTON_PRIMARY,
                $buttonattrs
            )] : []
        );

        return $OUTPUT->render($page);
    }

    /**
     * Record a new enrolment application for the given user.
     *
     * The enrolment is created suspended: the applicant gains no course access until
     * a manager confirms it through confirm_enrolment().
     *
     * The enrolment period does not start here; confirm_enrolment() stamps it on approval.
     * The clock should not run while the applicant has no access, and a timeend on a pending
     * row is dangerous: with expiredaction set to "unenrol", process_expirations() unenrols
     * every row with timeend > 0 AND timeend < now whatever its status, so an application
     * nobody reviewed in time would be deleted instead of decided.
     *
     * @param stdClass $instance Course enrol instance.
     * @param int $userid Applicant user id.
     * @param stdClass $data Submitted application form data.
     * @return void
     */
    protected function apply($instance, $userid, $data) {
        global $DB;

        /* No role: complete_approval() assigns it on approval. A role held while pending would
           let the applicant pass has_capability() in the course and appear in
           get_users_by_capability(), neither of which checks the enrolment status.

           null rather than 0: enrol_user() treats both as "no role", but the
           after_user_enrolled hook publishes the value as a nullable int. */
        $this->enrol_user($instance, $userid, null, 0, 0, ENROL_USER_SUSPENDED);

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

        /* The durable record of the same application. It is a second row rather than a
           column on the one above because that one is deleted the moment a decision is
           taken - on approval, on cancellation and in unenrol_user() - and a snapshot there
           would self-destruct exactly when it acquires audit value. */
        \enrol_apply\local\submission::create($instance, $userid, (int) $userenrolment->id, $data);

        $this->send_application_notification($instance, $userid, $data);
    }

    /**
     * Whether this instance has reached its applicant limit (customint3).
     *
     * Delegates to \enrol_apply\local\capacity::applications_closed() and exists for callers
     * outside this plugin, such as local_dimensions, local_unlistedcourses and
     * theme_boost_union_fundaseg. For them enrol_apply is an optional dependency, so they
     * guard a method on the plugin object with is_callable() rather than naming a class in
     * this plugin's namespace. Renaming it silently sends them back to their own counts.
     *
     * @param stdClass $instance Course enrol instance.
     * @return bool True when no further application may be made.
     */
    public function is_full(stdClass $instance) {
        return \enrol_apply\local\capacity::applications_closed($instance);
    }

    /**
     * Submit an application, serialised so that two tabs cannot both get through.
     *
     * Without the lock, two simultaneous submissions would both pass the form's already-applied
     * check and reach apply(): the second insert would fail on the foreign-unique key of
     * enrol_apply_applicationinfo with a database error, and the customint3 applicant limit
     * has the same race with no key behind it.
     *
     * The lock is per instance and per user, so two people applying at once never wait on
     * each other.
     *
     * The three outcomes are distinguished because two of them need opposite treatment: an
     * application that was already there is benign, while a refusal has to be explained. See
     * \enrol_apply\local\application_result.
     *
     * @param stdClass $instance Course enrol instance.
     * @param int $userid Applicant user id.
     * @param stdClass $data Submitted application form data.
     * @return \enrol_apply\local\application_result What this call did, and why if it refused.
     */
    public function submit_application($instance, $userid, $data) {
        global $DB;

        $factory = \core\lock\lock_config::get_lock_factory('enrol_apply_submit');
        $lock = $factory->get_lock($instance->id . '_' . $userid, 10);
        if (!$lock) {
            /* Another request for the same applicant and instance holds the lock. Reported as
               "already applied" rather than refused: that request almost certainly writes the
               application, and applied.php's own gate covers the case where it did not. */
            return \enrol_apply\local\application_result::already_applied();
        }

        try {
            /* The eligibility predicate on the write path, inside the lock beside the duplicate
               and cap checks, so callers other than the form (a task, a web service, an
               import) cannot bypass the instance status, the new-applications flag, the
               enrolment window or the cohort restriction. The form already re-runs
               check_access_for_dynamic_submission() on the submit request in both transports,
               so for the form this only closes the gap between that check and the write.

               The applicant is passed explicitly: given no user, allow_apply() judges $USER,
               and the cohort clause would then test the operator's membership
               (test_the_write_door_refuses_an_applicant_outside_the_cohort).

               `!== true` because the return is bool|string: anything but exactly true fails
               closed. */
            $allowapply = $this->allow_apply($instance, (int) $userid);
            if ($allowapply !== true) {
                /* The applicant is told the reason allow_apply() gave; enrol_page_hook() already
                   shows those strings to any authenticated non-member. A non-string refusal
                   falls back to a generic string, because refused() rejects an empty reason. */
                return \enrol_apply\local\application_result::refused(
                    is_string($allowapply) ? $allowapply : get_string('cantenrol', 'enrol_apply')
                );
            }

            if ($DB->record_exists('user_enrolments', ['userid' => $userid, 'enrolid' => $instance->id])) {
                return \enrol_apply\local\application_result::already_applied();
            }
            if (\enrol_apply\local\capacity::applications_closed($instance)) {
                /* No placeholder: the limit is competitive information an applicant cannot act
                   on. */
                return \enrol_apply\local\application_result::refused(
                    get_string('maxenrolledreached', 'enrol_apply')
                );
            }
            $this->apply($instance, $userid, $data);
        } finally {
            $lock->release();
        }

        return \enrol_apply\local\application_result::created();
    }

    /**
     * Apply everything that must follow an application becoming active.
     *
     * Called both by confirm_enrolment() and by the before_user_enrolment_updated hook
     * callback, so an approval made from core's "Edit enrolment" screen leaves the same
     * state behind as one made from the plugin's own queue. A queue approval runs it twice:
     * update_user_enrol() dispatches the hook before writing the row, so the callback runs it
     * first, with no operator input, and confirm_enrolment() runs it again. What the decider
     * chose is therefore read from the durable record, never passed in, and every step is
     * idempotent: role_assign() and groups_add_member() do not duplicate an existing row, the
     * delete matches nothing the second time, and the notification task is deduplicated.
     *
     * @param stdClass $instance Course enrol instance.
     * @param int $userid Applicant user id.
     * @param int $userenrolmentid User enrolment the application belongs to.
     * @return void
     */
    public function complete_approval($instance, $userid, $userenrolmentid) {
        global $DB, $USER;

        // The role follows approval, never the bare application. See assign_decided_role().
        $this->assign_decided_role($instance, (int) $userid, (int) $userenrolmentid);

        // Group membership follows approval, never the bare application.
        $this->add_instance_groups($instance, $userid, (int) $userenrolmentid);

        /* Stamped here rather than in confirm_enrolment() so that an approval made from
           core's "Edit enrolment" screen records its decider too: that route reaches this
           method through the before_user_enrolment_updated hook and never touches
           confirm_enrolment() at all. */
        \enrol_apply\local\submission::decide(
            (int) $userenrolmentid,
            \enrol_apply\local\submission::STATUS_APPROVED,
            (int) $USER->id,
            true
        );

        $DB->delete_records('enrol_apply_applicationinfo', ['userenrolmentid' => $userenrolmentid]);

        /* The applicant is told by an adhoc task rather than from here. The hook route runs
           before the enrolment row is written, so notifying inline could announce an approval
           a failed write then undoes; the task re-reads the enrolment and stays silent unless it
           is active. Queueing is deduplicated on classname, component and custom data
           (\core\task\manager::get_queued_adhoc_task_record()), so the two passes send one
           message. */
        $task = new \enrol_apply\task\notify_approval();
        $task->set_component('enrol_apply');
        $task->set_custom_data(['userenrolmentid' => (int) $userenrolmentid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Tell an applicant that their application was approved.
     *
     * Called from the ad-hoc task, so it re-establishes everything from the database and
     * does nothing unless the enrolment is really active on an apply instance. That guard
     * is what makes it safe to queue the task from a "before" hook.
     *
     * @param int $userenrolmentid User enrolment that was approved.
     * @return void
     */
    public function notify_confirmed_application($userenrolmentid) {
        global $DB;

        $sql = "SELECT ue.*
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = :enrol
                 WHERE ue.id = :id AND ue.status = :active";
        $userenrolment = $DB->get_record_sql($sql, [
            'enrol' => 'apply',
            'id' => $userenrolmentid,
            'active' => ENROL_USER_ACTIVE,
        ]);
        if (!$userenrolment) {
            // Approved and then undone, or never written. Nothing to announce.
            return;
        }

        $instance = $DB->get_record('enrol', ['id' => $userenrolment->enrolid], '*', MUST_EXIST);

        $this->notify_applicant(
            $instance,
            $userenrolment,
            'confirmation',
            get_config('enrol_apply', 'confirmmailsubject'),
            get_config('enrol_apply', 'confirmmailcontent')
        );
    }

    /**
     * Assign the applicant the role their approval carries.
     *
     * The decider's choice is read from the stored record because complete_approval() runs
     * twice for a queue approval (see there): two passes computing different roles would
     * assign both, and nothing could later tell which one this plugin meant. role_assign() is
     * idempotent on the whole tuple, so two passes reading the same answer write one row.
     * Where nothing was recorded the instance's own role applies, which is all core's "Edit
     * enrolment" route can produce.
     *
     * The assignment is stamped with component enrol_apply and the instance id, so
     * unenrol_user() and process_expirations() remove exactly this assignment. Unstamped,
     * process_expirations() would unassign $instance->roleid instead, which is wrong once a
     * decider can choose another role, and unenrol_user() would remove it only with the
     * user's last enrolment in the course. roles_protected() stays false so the participants
     * page can still remove the assignment by hand (user/classes/output/user_roles_editable.php).
     *
     * @param stdClass $instance Course enrol instance.
     * @param int $userid Applicant user id.
     * @param int $userenrolmentid User enrolment whose recorded choice wins, 0 for none.
     * @return void
     */
    protected function assign_decided_role($instance, int $userid, int $userenrolmentid) {
        $roleid = \enrol_apply\local\submission::chosen_role($userenrolmentid) ?? (int) $instance->roleid;

        /* An instance can carry roleid 0 (the column defaults to 0, and a restore writes 0 when
           the archived role is not mapped to a role here), and role_assign(0, ...) throws, so
           this skip is required. */
        if ($roleid <= 0) {
            return;
        }

        role_assign($roleid, $userid, context_course::instance($instance->courseid)->id, 'enrol_apply', $instance->id);
    }

    /**
     * Add the applicant to the groups the decider chose, or to the instance's own list.
     *
     * Memberships are tagged with this plugin's component and the instance id, so core's
     * unenrol_user() removes them with the enrolment; an untagged membership survives whenever
     * the user has another enrolment in the course.
     *
     * The decider's choice is read from the stored record because complete_approval() runs
     * twice for a queue approval (see there): memberships from two different lists would
     * accumulate, joining a group the approver had deselected. Where nothing was recorded, the
     * instance's own list applies.
     *
     * @param stdClass $instance Course enrol instance.
     * @param int $userid User to add to the groups.
     * @param int $userenrolmentid User enrolment whose recorded choice wins.
     * @return void
     */
    protected function add_instance_groups($instance, $userid, int $userenrolmentid) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');

        // The decider's choice, read from the record; see the docblock.
        $chosen = \enrol_apply\local\submission::chosen_groups($userenrolmentid);

        if ($chosen === null) {
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
            $groupids = array_keys($groups);
        } else {
            // Re-checked against the course even though the caller validated: the record can be
            // older than the group it names, and groups_add_member() throws on an unknown id.
            [$insql, $params] = $DB->get_in_or_equal($chosen, SQL_PARAMS_NAMED, 'gid');
            $params['courseid'] = $instance->courseid;
            $groupids = array_keys($DB->get_records_select('groups', "courseid = :courseid AND id {$insql}", $params));
        }

        foreach ($groupids as $groupid) {
            groups_add_member($groupid, $userid, 'enrol_apply', $instance->id);
        }
    }

    /**
     * Re-create a group membership this plugin owns, when a course is restored.
     *
     * Core hands every groups_members row whose component starts with "enrol_" to this method
     * ({@see restore_groups_members_structure_step::process_member()}), with no
     * groups_add_member() fallback and no log line, and the base implementation is empty
     * because the plugins core had in mind re-derive their memberships. This plugin cannot: the
     * membership follows a one-off approval. Without this override it is lost silently.
     *
     * The stamp is re-applied so core's unenrol_user() can still remove the membership.
     *
     * @param stdClass $instance Enrol instance the membership belongs to.
     * @param int $groupid Group to join.
     * @param int $userid User to add.
     * @return void
     */
    public function restore_group_member($instance, $groupid, $userid) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');

        /* The group must still exist in this course: groups_add_member() throws on an unknown
           group id, which would abort the whole restore over one membership. */
        if (!$DB->record_exists('groups', ['id' => $groupid, 'courseid' => $instance->courseid])) {
            return;
        }

        groups_add_member($groupid, $userid, 'enrol_apply', $instance->id);
    }

    /**
     * Re-create a role assignment this plugin owns, when a course is restored.
     *
     * The counterpart of restore_group_member(). Core hands every {role_assignments} row whose
     * component starts with "enrol_" to this method
     * ({@see restore_ras_and_caps_structure_step::process_assignment()}), with no role_assign()
     * fallback and no log line, and the base implementation is empty. Without this override a
     * restored applicant keeps an active enrolment and silently loses the role.
     *
     * The stamp is re-applied for the reason it is written at all; see assign_decided_role().
     * enrol_flatfile has the same pairing: it stamps, returns false from roles_protected() and
     * overrides this method.
     *
     * No guard is needed on the arguments: core has already mapped the role, confirmed the user
     * exists and derived the context, and it dispatches on $instance->enrol, so this is only
     * ever handed an apply instance.
     *
     * @param stdClass $instance Enrol instance the assignment belongs to.
     * @param int $roleid Role to assign.
     * @param int $userid User to assign it to.
     * @param int $contextid Context to assign it in.
     * @return void
     */
    public function restore_role_assignment($instance, $roleid, $userid, $contextid) {
        role_assign($roleid, $userid, $contextid, 'enrol_apply', $instance->id);
    }

    /**
     * The decisions this plugin offers from core's participants page bulk menu.
     *
     * This is the gate as well as the menu. user/action_redir.php looks the chosen operation
     * up in this array and throws when it is absent, and performs no require_login() or
     * require_capability() of its own in that branch, so returning an empty array is what
     * refuses an operator who may not decide here.
     *
     * The capability is checked at the course context, which is stricter than
     * can_manage_application(): a mentor holding it only in an applicant's user context is
     * offered nothing here, and manage.php serves that scope.
     *
     * @param course_enrolment_manager $manager Manager core built for the course.
     * @return array Operation identifier => enrol_bulk_enrolment_operation.
     */
    public function get_bulk_operations(course_enrolment_manager $manager) {
        global $CFG;

        /* enrol_bulk_enrolment_operation lives in a legacy file and is not autoloadable, so
           it must be loaded before the autoloader is asked for a subclass of it. */
        require_once($CFG->dirroot . '/enrol/locallib.php');

        /* user/index.php builds the menu from get_enrolment_plugins(false), disabled plugins
           included, while action_redir.php dispatches through the enabled-only list and throws
           errorwithbulkoperation, so a disabled plugin's entries could only lead to an error
           page. The per-row icon deliberately skips this check; see
           get_user_enrolment_actions(). */
        if (!enrol_is_enabled($this->get_name())) {
            return [];
        }

        if (!has_capability('enrol/apply:manageapplications', $manager->get_context())) {
            return [];
        }

        /* One optgroup per course, not per instance. user/index.php calls this once per
           enrolment instance of the course, on the same plugin object, with an unfiltered
           manager and a url naming only the plugin and the operation, so N instances would give
           N identical optgroups. Core's own enrol_self duplicates the same way; the real fix
           belongs in user/index.php.

           An empty filter identifies that menu: action_redir.php's dispatch and
           enrol/renderer.php always pass a filtered manager and must never be suppressed.
           empty() rather than === null, because get_enrolment_filter() may return null, 0 or
           the id as a string. An instance property rather than a static, because
           enrol_get_plugins() builds a fresh plugin object per call and a static would leak
           across managers and PHPUnit tests. The memo is set only after the gates above, so a
           call refused for the capability cannot silence the next one
           (test_a_menu_refused_for_the_capability_does_not_silence_the_next_one). */
        if (empty($manager->get_enrolment_filter())) {
            if ($this->bulkmenuoffered) {
                return [];
            }
            $this->bulkmenuoffered = true;
        }

        $offered = [
            new \enrol_apply\bulk\confirm_operation($manager, $this),
            new \enrol_apply\bulk\wait_operation($manager, $this),
            new \enrol_apply\bulk\cancel_operation($manager, $this),
        ];

        /* Keyed by each operation's own identifier so the two cannot drift: core dispatches on
           the array key and never calls get_identifier(). */
        $operations = [];
        foreach ($offered as $operation) {
            $operations[$operation->get_identifier()] = $operation;
        }

        return $operations;
    }

    /**
     * The per-row action icons this plugin adds to core's participants page.
     *
     * One icon, on an application still awaiting a decision, linking to the review page
     * (manage.php?userenrol=), which decides that one application; ?id= would open the whole
     * queue. After deciding there, queue::scope() returns the operator to the queue rather than
     * to the participants page.
     *
     * The link carries no data-action. Inside [data-region="core_table/dynamic"],
     * core_user/status_field claims editenrolment, unenrol and showdetails, and
     * core_table/dynamic claims hide, show and showcount
     * (lib/table/amd/src/local/dynamic/selectors.js), each with preventDefault(), so the
     * attribute could only get the link hijacked.
     *
     * The status gate is queue::is_awaiting_decision(), so decided rows get no icon; its
     * timeend clause matters only under a suspend expiredaction, since the shipped
     * ENROL_EXT_REMOVED_KEEP leaves a lapsed enrolment active. Core's status column shows a
     * waiting-list row (ENROL_APPLY_USER_WAIT, a value core does not know) as Active, so this
     * icon is the only sign on that row that a decision is owed.
     *
     * The capability is read in the course, as get_bulk_operations() reads it, so a mentor
     * holding it only in an applicant's user context is offered neither; manage.php with no
     * parameter serves them. test_a_mentor_is_offered_no_icon_in_the_course holds this.
     *
     * The plugin's site-wide enabled state is deliberately not checked: core renders its own
     * Edit and Unenrol icons on a disabled plugin's rows and manage.php still works, unlike
     * the bulk menu, whose dispatch core refuses for a disabled plugin.
     *
     * @param course_enrolment_manager $manager Manager core built for the course.
     * @param stdClass $ue The user enrolment row, carrying its instance and plugin.
     * @return array Core's own actions, plus this plugin's where it applies.
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        /* The standard Edit and Unenrol icons come first and stay as core builds them. The
           parent method gates each on its capability, the first also on allow_manage() and the
           second on allow_unenrol_user(), which simply asks allow_unenrol(), and this plugin
           always answers that with true. */
        $actions = parent::get_user_enrolment_actions($manager, $ue);

        if (!\enrol_apply\local\queue::is_awaiting_decision($ue)) {
            return $actions;
        }

        if (!has_capability('enrol/apply:manageapplications', $manager->get_context())) {
            return $actions;
        }

        $title = get_string('decideapplication', 'enrol_apply');
        $actions[] = new user_enrolment_action(
            new pix_icon('i/userevent', $title),
            $title,
            new moodle_url('/enrol/apply/manage.php', ['userenrol' => $ue->id])
        );

        return $actions;
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
        }

        /* Its own capability, checked separately: the report shows the frozen profile snapshot
           of every applicant the course has ever had, which is a wider disclosure than deciding
           on the applications currently in the queue. */
        if (has_capability('enrol/apply:viewreports', $context)) {
            $reportlink = new moodle_url('/enrol/apply/report.php', ['id' => $instance->id]);
            $icons[] = $OUTPUT->action_icon(
                $reportlink,
                new pix_icon(
                    'i/report',
                    get_string('report:course_applications', 'enrol_apply'),
                    'core',
                    ['class' => 'iconsmall']
                )
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
     * The enrol_apply_submission rows are deliberately kept: the record of who applied and
     * what was decided is not part of the course's configuration and outlives the method. It
     * ends only when the course is deleted, which pseudonymises it through
     * \enrol_apply\hook_callbacks::before_course_deleted(), or on an erasure request, through
     * the privacy provider.
     *
     * This method also runs where no course-deletion path does: a restore into an existing
     * course that deletes its contents first reaches enrol_course_delete() through
     * restore_dbops::delete_course_content(), and neither the hook nor the course_deleted
     * event fires.
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

        /* Added here, the per-instance hook enrol_add_course_navigation() calls, rather than
           from a file-scope enrol_apply_extend_navigation_course(), which fires for every
           course whether or not it has an apply instance. *_extend_settings_navigation() is
           not dispatched for enrol plugins at all. */
        if (has_capability('enrol/apply:viewreports', $context)) {
            $reportlink = new moodle_url('/enrol/apply/report.php', ['id' => $instance->id]);
            $instancesnode->add(
                get_string('report:course_applications', 'enrol_apply'),
                $reportlink,
                navigation_node::TYPE_SETTING
            );
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
        $fields['customint3'] = (int) $this->get_config('maxenrolled', 0);
        $fields['customint4'] = (int) $this->get_config('places', 0);
        $fields['customint5'] = 0;
        $fields['customint6'] = $this->get_config('newenrols');
        $fields['customint7'] = (int) $this->get_config('opt_commentaryzone', 0);
        $fields['customint8'] = 0;
        $fields['customtext2'] = '';
        $fields['customtext3'] = $this->get_config('notifycoursebased') ? '$@ALL@$' : '';
        $fields['customtext4'] = \enrol_apply\local\fieldset::from_keys(
            \enrol_apply\local\fields::pool()
        )->to_json();
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
     * Every id is looked up and authorised individually: one that is not an application of
     * this plugin's awaiting a decision (see get_pending_user_enrolment()), or that the current
     * user may not act on, is skipped rather than failing the whole batch.
     *
     * @param array $enrols User enrolment ids to confirm.
     * @param string $message Message the decider wrote to the applicant, empty for none.
     * @param array|null $decision Chosen groups, role and decision note under the keys 'groups',
     *        'roleid' and 'note'; null for the instance defaults and no note. The enrolment period
     *        is not among them: it comes from the method's own enrolperiod.
     * @return int How many of the given applications this call actually decided.
     */
    public function confirm_enrolment($enrols, string $message = '', ?array $decision = null) {
        global $DB;

        $decided = 0;

        foreach ($enrols as $enrol) {
            $userenrolment = $this->get_pending_user_enrolment($enrol);
            if (!$userenrolment) {
                continue;
            }

            $instance = $DB->get_record('enrol', ['id' => $userenrolment->enrolid, 'enrol' => 'apply'], '*', MUST_EXIST);

            if (!$this->can_manage_application($instance->courseid, $userenrolment->userid)) {
                continue;
            }

            $decided++;

            /* A record to write to. Without it record_outcome_message() below writes nothing,
               silently, for an application older than that table, and the message is stored
               and mailed nowhere. See ensure(). */
            \enrol_apply\local\submission::ensure((int) $userenrolment->id);

            /* Stored on the record rather than passed along, like the rest of the decision
               below: update_user_enrol() dispatches the hook that reaches complete_approval()
               with no operator input, and the notify_approval task it queues reads the message
               back through submission::outcome_message(). */
            \enrol_apply\local\submission::record_outcome_message((int) $userenrolment->id, $message);

            $this->record_decision_note($userenrolment, $decision);

            /* The chosen role is allowlisted per instance, as enrol/manual/externallib.php does:
               role_assign() performs no assignability check, so this is the only thing between
               a posted role id and a role assignment. get_assignable_roles() is keyed by role id
               with localised names as values, hence array_key_exists and never in_array.

               A refused role records 0 rather than throwing, so one bad id does not block a
               queue, and the approval proceeds with the instance's own role. That fallback is
               deliberately not allowlisted: filtering it would stop an instance configured with a
               role its teacher may not assign from granting any role at all. */
            if ($decision !== null && array_key_exists('roleid', $decision)) {
                $assignable = get_assignable_roles(context_course::instance($instance->courseid));
                $chosenrole = (int) $decision['roleid'];
                \enrol_apply\local\submission::record_decided_role(
                    (int) $userenrolment->id,
                    array_key_exists($chosenrole, $assignable) ? $chosenrole : 0
                );
            }

            /* Allowlisted per instance rather than once for the batch, because the posted batch
               can span courses. groups_get_all_groups() is keyed by group id, hence
               array_key_exists.

               The gate is array_key_exists and not !empty, so an empty posted list reaches the
               writer and clears an earlier choice; a caller with nothing to say about the groups
               omits the key. */
            if ($decision !== null && array_key_exists('groups', $decision)) {
                $allowed = groups_get_all_groups($instance->courseid);
                $chosen = array_values(array_filter(
                    array_map('intval', (array) $decision['groups']),
                    static function (int $groupid) use ($allowed): bool {
                        return array_key_exists($groupid, $allowed);
                    }
                ));
                \enrol_apply\local\submission::record_decided_groups((int) $userenrolment->id, $chosen);
            }

            /* The period starts on approval and lasts the method's enrolperiod; it is not the
               decider's to choose. It is stamped on the enrolment rather than on the record, so
               the report has one source for the dates, and never before approval (see apply()). */
            $userenrolment->timestart = time();
            $userenrolment->timeend = $instance->enrolperiod
                ? $userenrolment->timestart + $instance->enrolperiod
                : 0;

            /* update_user_enrol() dispatches before_user_enrolment_updated, so the callback in
               classes/hook_callbacks.php has usually run complete_approval() already. It is
               called again below on purpose: it is idempotent, and this method must leave the
               right state even when the hook is not registered, for instance before the hook
               cache is rebuilt after an upgrade. */
            $this->update_user_enrol(
                $instance,
                $userenrolment->userid,
                ENROL_USER_ACTIVE,
                $userenrolment->timestart,
                $userenrolment->timeend
            );

            // The applicant's notification is queued by complete_approval(); see it for why.
            $this->complete_approval($instance, (int) $userenrolment->userid, (int) $userenrolment->id);
        }

        return $decided;
    }

    /**
     * Defer the given applications: they keep waiting, and nobody is enrolled or unenrolled.
     *
     * The lookup is get_pending_user_enrolment(), as for confirm and cancel, so an application
     * already deferred is found again and its reason can be corrected. Such a correction is not
     * a second decision: the enrolment does not move, so decide() keeps the original decider
     * and date, and the applicant is notified again only if the decider typed a message for
     * them.
     *
     * @param array $enrols User enrolment ids to defer.
     * @param string $message Message the decider wrote to the applicant, empty for none.
     * @param array|null $decision The decision note, under the 'note' key; null for none. The
     *        three decision methods share one signature because PHP silently drops surplus
     *        arguments, so a mismatch would lose the note without an error.
     * @return int How many of the given applications this call actually decided.
     */
    public function wait_enrolment($enrols, string $message = '', ?array $decision = null) {
        global $DB, $USER;

        $decided = 0;

        foreach ($enrols as $enrol) {
            $userenrolment = $this->get_pending_user_enrolment($enrol);
            if (!$userenrolment) {
                continue;
            }

            $instance = $DB->get_record('enrol', ['id' => $userenrolment->enrolid, 'enrol' => 'apply'], '*', MUST_EXIST);

            if (!$this->can_manage_application($instance->courseid, $userenrolment->userid)) {
                continue;
            }

            $decided++;

            /* Whether this call moves the enrolment at all. Read before the update below, after
               which every row is on the waiting list whenever it arrived there. */
            $moved = (int) $userenrolment->status !== ENROL_APPLY_USER_WAIT;

            // A record to write to; see confirm_enrolment() and submission::ensure().
            \enrol_apply\local\submission::ensure((int) $userenrolment->id);

            \enrol_apply\local\submission::record_outcome_message((int) $userenrolment->id, $message);

            $this->record_decision_note($userenrolment, $decision);

            /* Deferring clears the expiry. update_user_enrol() writes only the dates that are
               set, so passing null would keep any future timeend the row carries: core's "Edit
               enrolment" screen can suspend an enrolment part way through its period, and a
               restore copies an archived timeend. Once that date passed, the deferred row would
               drop out of the queue, which lists only unexpired rows, with nothing left to
               decide it: the suspend arms of process_expirations() filter status = active, and
               the unenrol arm deletes it. It must be the integer 0: core's communication hook
               listener compares timeend !== 0, and '0' would drop the applicant from the course
               communication room. */
            $this->update_user_enrol($instance, $userenrolment->userid, ENROL_APPLY_USER_WAIT, null, 0);

            /* The row was read before the update, and notify_applicant() below substitutes
               {timeend} into the wait mail unconditionally. Without this the message would
               print the expiry that was just cleared. */
            $userenrolment->timeend = 0;

            /* $moved rather than true: on a re-deferral the enrolment did not move, so decide()'s
               same-status guard must keep the original decider and date instead of crediting
               whoever corrected the note. */
            \enrol_apply\local\submission::decide(
                (int) $userenrolment->id,
                \enrol_apply\local\submission::STATUS_WAITING,
                (int) $USER->id,
                $moved
            );

            /* Notified when the enrolment moved, or when the decider typed a message for the
               applicant, which must never be dropped silently; not when only the internal note
               was corrected. */
            if ($moved || trim($message) !== '') {
                $this->notify_applicant(
                    $instance,
                    $userenrolment,
                    'waitinglist',
                    get_config('enrol_apply', 'waitmailsubject'),
                    get_config('enrol_apply', 'waitmailcontent')
                );
            }
        }

        return $decided;
    }

    /**
     * Cancel the given applications, unenrolling the applicants.
     *
     * @param array $enrols User enrolment ids to cancel.
     * @param string $message Message the decider wrote to the applicant, empty for none.
     * @param array|null $decision The decision note, under the 'note' key; null for none. See
     *        wait_enrolment() for why all three take the same triple.
     * @return int How many of the given applications this call actually decided.
     */
    public function cancel_enrolment($enrols, string $message = '', ?array $decision = null) {
        global $DB, $USER;

        $decided = 0;

        foreach ($enrols as $enrol) {
            $userenrolment = $this->get_pending_user_enrolment($enrol);
            if (!$userenrolment) {
                continue;
            }

            $instance = $DB->get_record('enrol', ['id' => $userenrolment->enrolid, 'enrol' => 'apply'], '*', MUST_EXIST);

            if (!$this->can_manage_application($instance->courseid, $userenrolment->userid)) {
                continue;
            }

            $decided++;

            /* A record to write to, and here it must also be written before the unenrolment:
               unenrol_user() deletes the {user_enrolments} row this reconstruction reads. */
            \enrol_apply\local\submission::ensure((int) $userenrolment->id);

            \enrol_apply\local\submission::record_outcome_message((int) $userenrolment->id, $message);

            $this->record_decision_note($userenrolment, $decision);

            /* Stamped before the unenrolment, not after: unenrol_user() deletes the
               user_enrolments row, and the id it carried is how the durable record is
               matched. The record itself is untouched by the unenrolment on purpose - a
               cancelled application is exactly the outcome the trail exists to hold. */
            \enrol_apply\local\submission::decide(
                (int) $userenrolment->id,
                \enrol_apply\local\submission::STATUS_CANCELLED,
                (int) $USER->id,
                true
            );

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

        return $decided;
    }

    /**
     * Write the decision note, when the caller had one to write.
     *
     * array_key_exists and never !empty, as for the groups: an empty posted note must reach the
     * writer, because clearing is what stops a re-queued application inheriting the last
     * decision's reason. A caller with nothing to say about the note omits the key and the
     * stored note is left alone.
     *
     * @param stdClass $userenrolment User enrolment the decision applies to.
     * @param array|null $decision The decision the operator submitted, null for none.
     * @return void
     */
    protected function record_decision_note($userenrolment, ?array $decision) {
        if ($decision === null || !array_key_exists('note', $decision)) {
            return;
        }

        \enrol_apply\local\submission::record_decision_note(
            (int) $userenrolment->id,
            (string) $decision['note']
        );
    }

    /**
     * Fetch an application of this plugin's that is still awaiting a decision.
     *
     * "Awaiting a decision" is {@see \enrol_apply\local\queue::awaiting_decision_where()}, the
     * rule the approval queue and the review page list by, so a posted id can only reach a row
     * those pages would offer. Everything else returns false, and the decision methods skip it
     * as they skip a row the operator may not act on:
     *  - a user enrolment of another enrolment method, which would otherwise reach the caller's
     *    instance lookup and throw half way through a batch;
     *  - an application already decided;
     *  - an approval whose period has ended. Under an expiredaction of suspend,
     *    process_expirations() puts it back to suspended with its past timeend, and deciding it
     *    would unenrol the learner, put them back in the queue or approve them a second time.
     *
     * An application already deferred is still awaiting a decision, which is what lets
     * wait_enrolment() correct its reason.
     *
     * @param int $userenrolmentid User enrolment id.
     * @return stdClass|false The {user_enrolments} row, or false when there is nothing to decide.
     */
    protected function get_pending_user_enrolment($userenrolmentid) {
        global $DB;

        [$wheres, $params] = \enrol_apply\local\queue::awaiting_decision_where();
        $params['ueid'] = (int) $userenrolmentid;
        $params['enrol'] = 'apply';

        return $DB->get_record_sql(
            "SELECT ue.*
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.id = :ueid AND e.enrol = :enrol AND " . implode(' AND ', $wheres),
            $params,
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

        /* All six subject and body settings ship empty and e-mail is on by default, so without
           this fallback a stock site mails the applicant a notification with no subject and no
           body. It is applied at read time because a settings.php default cannot reach an
           existing site: only a fresh install applies defaults unconditionally, and afterwards
           admin_apply_default_settings() skips every setting already stored.

           Resolved before update_mail_content(), so the default body gets the same placeholder
           substitution and escaping as an administrator's own. Subject and body fall back
           independently. */
        [$defaultsubject, $defaultcontent] = $this->default_notification($type, $course);
        if (trim((string) $subject) === '') {
            $subject = $defaultsubject;
        }
        if (trim((string) $content) === '') {
            $content = $defaultcontent;
        }

        $content = $this->update_mail_content($content, $course, $user, $userenrolment);

        /* Read from the durable record rather than passed in, so the approval notification,
           sent later from an adhoc task, gets it too. s() and not format_text(): the body
           around it is the administrator's trusted template, while this is free text a decider
           typed, landing in fullmessagehtml. nl2br keeps the decider's paragraphs; the
           plain-text half is derived from this by html_to_text() in the notification. */
        $outcome = \enrol_apply\local\submission::outcome_message((int) $userenrolment->id);
        if (trim($outcome) !== '') {
            $content .= '<br><br>' . nl2br(s($outcome));
        }

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
     * The wording to send when the administrator has configured none.
     *
     * The subject and the body want different spellings of the course name. The body is HTML
     * and goes through update_mail_content(), which escapes what it substitutes; the subject is
     * plain text, so it takes the plain spelling, or a course called "R&D induction" would reach
     * the inbox as "R&amp;D induction".
     *
     * The default arm returns the empty pair, so a type without default wording keeps whatever
     * the administrator's settings hold; enrol_apply_notification's constructor is the one place
     * that refuses an unknown type.
     *
     * Known limitation: nothing on this path calls force_current_language(), so every applicant
     * notification, including these defaults and the smallmessage the Moodle app shows, is
     * composed in the decider's language.
     *
     * @param string $type Notification type: confirmation, cancelation or waitinglist.
     * @param stdClass $course Course the application belongs to.
     * @return array Two-element list: the subject, then the body.
     */
    protected function default_notification($type, $course) {
        // Plain, because the subject is not HTML; see the docblock.
        $coursename = format_string($course->fullname, true, [
            'context' => context_course::instance($course->id),
            'escape' => false,
        ]);

        return match ($type) {
            'confirmation' => [
                get_string('confirmmailsubject_default', 'enrol_apply', $coursename),
                get_string('confirmmailcontent_default', 'enrol_apply'),
            ],
            'cancelation' => [
                get_string('cancelmailsubject_default', 'enrol_apply', $coursename),
                get_string('cancelmailcontent_default', 'enrol_apply'),
            ],
            'waitinglist' => [
                get_string('waitmailsubject_default', 'enrol_apply', $coursename),
                get_string('waitmailcontent_default', 'enrol_apply'),
            ],
            default => ['', ''],
        };
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

        /* What the applicant submitted, keyed by the fields this instance asks for. Standard
           and custom fields both come from the submitted data, not from the account, so the
           approver sees the answers given in this application. */
        $submitted = \enrol_apply\local\fields::submitted_values($instance, $data);

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
                $submitted
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
                $submitted
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
                $submitted
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
     * Returns the actively enrolled users of a course who should be notified about new applications.
     *
     * Only active enrolments count: the message links to the course's approval queue, whose
     * require_login() refuses a user whose own enrolment is suspended or outside its dates, even
     * though their role, and with it the capability, survives.
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
        $users = get_enrolled_users($context, 'enrol/apply:manageapplications', 0, 'u.*', null, 0, 0, true);

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

        /* A cohort id from another site names a different group of people here, so the
           restriction degrades to the -1 sentinel rather than to 0: allow_apply() reads it
           as a live refusal, where a 0 would quietly drop the restriction the course was
           backed up with. */
        if (!empty($data->customint5) && !$step->get_task()->is_samesite()) {
            $data->customint5 = -1;
        }

        /* customtext4 names fields by site-local id for custom fields, so an envelope from
           another site can name fields that do not exist here or that this site does not
           allow. resolve() intersects against this site on every read anyway, but rewriting
           it now means the stored value matches what the instance will actually collect
           rather than carrying a set nobody can see. */
        if (!empty($data->customtext4)) {
            $data->customtext4 = \enrol_apply\local\fields::resolve($data)->to_json();
        }

        /* Unconditionally, and not only when the restore is cross-site. is_samesite() falls
           back to comparing a wwwroot string taken from the archive itself when the site
           identifier hash is absent, so it is forgeable - and the thing being switched off
           here writes to {user}. Somebody restoring a course they built elsewhere re-ticks
           the box if they meant it. backup_test::test_a_restore_switches_the_profile_write_off
           holds this. */
        $data->customint8 = 0;

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
        global $DB;

        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);

        /* Core registers no mapping for user_enrolments, so the plugin builds its own:
           the application comments are keyed by user enrolment id and have no other way
           back. This works because core writes <user_enrolments> into the enrol element
           before add_plugin_structure() appends the plugin's own data
           (backup/moodle2/backup_stepslib.php), so this method has already run by the
           time restore_enrol_apply_plugin processes an application. Should that order
           ever change, get_mappingid() returns false there and the comment is dropped
           rather than mis-attached. */
        $newid = $DB->get_field('user_enrolments', 'id', ['enrolid' => $instance->id, 'userid' => $userid]);
        if ($newid) {
            $step->set_mapping('enrol_apply_userenrolment', $data->id, $newid);
        }
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
