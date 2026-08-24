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
     * Check whether the given instance currently accepts applications from the current user.
     *
     * Every caller routes through this method, so each restriction is checked here rather
     * than in enrol_page_hook(): the hook is only one of the callers and the return value
     * is rendered raw by core's notification output, which is why the cohort name below is
     * escaped by format_string() before it is substituted into the message.
     *
     * @param stdClass $instance Course enrol instance.
     * @return bool|string True when applications are accepted, otherwise the reason to show the user.
     */
    public function allow_apply(stdClass $instance) {
        global $CFG, $DB, $USER;

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

            /* Read the cohort with a plain get_record() rather than cohort_get_cohort():
               the applicant holds moodle/cohort:view nowhere, so the visibility-aware
               helper would refuse every cohort and turn each restriction into "unresolved".
               enrol_self names the gating cohort to the applicant the same way. */
            $cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id, name, contextid');
            if (!$cohort) {
                // The cohort was deleted. Fail closed, and with a string the caller can render.
                return get_string('cohortunresolved', 'enrol_apply');
            }
            if (!cohort_is_member($cohortid, $USER->id)) {
                $name = format_string($cohort->name, true, ['context' => context::instance_by_id($cohort->contextid)]);
                return get_string('cohortnonmemberinfo', 'enrol_apply', $name);
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
     * The form itself is no longer rendered inline: two apply instances on one page emitted
     * two copies of every profile element, so every id was duplicated.
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

        $cap = (int) $instance->customint3;
        $taken = $cap > 0 ? $DB->count_records('user_enrolments', ['enrolid' => $instance->id]) : 0;

        $allowapply = $this->allow_apply($instance);
        if ($allowapply !== true) {
            $body = $allowapply;
        } else if ($DB->record_exists('user_enrolments', ['userid' => $USER->id, 'enrolid' => $instance->id])) {
            $body = get_string('notification', 'enrol_apply');
        } else if ($cap > 0 && $taken >= $cap) {
            $body = get_string('maxenrolledreached', 'enrol_apply', $taken);
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

        $notification = new \core\output\notification($body, \core\output\notification::NOTIFY_INFO, false);
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

        /* No role, on purpose. The role is assigned on approval by complete_approval(), which is
           what the "Role assigned to a user when their enrolment application is approved" setting
           has always said and what the code did not do. Somebody who may yet be refused should
           not hold a role meanwhile - and until this changed they did, visibly: a pending
           applicant satisfied has_capability() in the course and was returned by
           get_users_by_capability(), measured on 5.1 and 5.2, so anything asking those questions
           without also checking the enrolment treated an applicant as a participant.

           null rather than 0. The two are indistinguishable to enrol_user()'s own "if ($roleid)"
           guard, but the after_user_enrolled hook publishes the value as a nullable int and null
           is the honest one. It is also what restore_user_enrolment() below already passes. */
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
     * Submit an application, serialised so that two tabs cannot both get through.
     *
     * "One row per application" is an assertion, not a guarantee: two simultaneous
     * submissions both pass the already-applied check in the form and both reach apply().
     * Today the foreign-unique key on enrol_apply_applicationinfo makes the second insert
     * blow up with a database error rather than a message anybody can act on, and the
     * customint3 places cap has the same race with no key behind it at all.
     *
     * The lock is per instance and per user, so two people applying at once never wait on
     * each other. A failure to acquire is treated as "the other request is already doing
     * it", which is the truth: the caller's own already-applied check will see the row.
     *
     * @param stdClass $instance Course enrol instance.
     * @param int $userid Applicant user id.
     * @param stdClass $data Submitted application form data.
     * @return bool True when this call created the application, false when it was already there.
     */
    public function submit_application($instance, $userid, $data) {
        global $DB;

        $factory = \core\lock\lock_config::get_lock_factory('enrol_apply_submit');
        $lock = $factory->get_lock($instance->id . '_' . $userid, 10);
        if (!$lock) {
            return false;
        }

        try {
            if ($DB->record_exists('user_enrolments', ['userid' => $userid, 'enrolid' => $instance->id])) {
                return false;
            }
            if ($instance->customint3 > 0) {
                $count = $DB->count_records('user_enrolments', ['enrolid' => $instance->id]);
                if ($count >= $instance->customint3) {
                    return false;
                }
            }
            $this->apply($instance, $userid, $data);
        } finally {
            $lock->release();
        }

        return true;
    }

    /**
     * Apply everything that must follow an application becoming active.
     *
     * Called both by confirm_enrolment() and by the before_user_enrolment_updated
     * observer, so an approval made from core's "Edit enrolment" screen leaves the same
     * state behind as one made from the plugin's own queue. Idempotent by construction:
     * groups_add_member() is a no-op for an existing membership and the delete matches
     * nothing the second time.
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
            (int) $USER->id
        );

        $DB->delete_records('enrol_apply_applicationinfo', ['userenrolmentid' => $userenrolmentid]);

        /* The applicant is told by an ad-hoc task rather than from here. The hook route
           runs before the enrolment row is written, so notifying inline would announce an
           approval that a failed write could still undo; the task re-reads the enrolment
           and stays silent unless it really is active. Queueing is deduplicated on
           classname + component + customdata (\core\task\manager::get_queued_adhoc_task_record),
           so the two callers of this method cannot produce two messages. */
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
     * The decider's choice is read from the stored record rather than received as an argument,
     * and that is load bearing for the same reason it is for the groups: an approval taken
     * through the queue completes TWICE over, and only the second pass could carry an argument.
     * Two passes computing two different roles would assign both, and neither the UI nor a later
     * sweep can tell which one this plugin meant. Reading one stored answer is what makes both
     * passes agree, and role_assign() is idempotent on the whole tuple, so the second call
     * returns the first call's id rather than writing a second row.
     *
     * Where nothing was recorded the instance's own role applies, which is the only role the
     * out-of-band route can ever produce: core's "Edit enrolment" screen drives
     * update_user_enrol(), which does nothing about roles at all and has no operator input to
     * carry.
     *
     * The assignment is stamped with this plugin's component, and the plugin still returns false
     * from roles_protected(). That pair is deliberate and each half was measured. The stamp is
     * what lets core clean the assignment up exactly: unenrol_user() unassigns by component and
     * itemid whether or not this was the user's last enrolment in the course, and
     * process_expirations() does the same in its "remove all roles that belong to this instance"
     * line. Without it core falls back to guessing $instance->roleid, and once a decider can
     * choose a different role that guess is wrong by construction. Measured on m502, with an
     * unrelated manual enrolment in the same course: an expired enrolment approved as Teacher
     * against an instance defaulting to Student left the Teacher assignment behind under both
     * expiredaction settings when it was bare, and was removed correctly under both when it was
     * stamped. roles_protected() staying false is what keeps it removable by hand - the
     * participants page refuses to remove a component-owned assignment only when the owning
     * plugin protects its roles (user/classes/output/user_roles_editable.php, identical on 5.1
     * and 5.2) - so the stamp and the false together give both.
     *
     * @param stdClass $instance Course enrol instance.
     * @param int $userid Applicant user id.
     * @param int $userenrolmentid User enrolment whose recorded choice wins, 0 for none.
     * @return void
     */
    protected function assign_decided_role($instance, int $userid, int $userenrolmentid) {
        $roleid = \enrol_apply\local\submission::chosen_role($userenrolmentid) ?? (int) $instance->roleid;

        /* An instance can carry roleid 0 - the column is nullable with a default of 0, and a
           restore writes 0 whenever the archived role maps to nothing the restoring user may
           assign. role_assign(0, ...) throws rather than doing nothing, so this skip is required
           and not defensive: until this change enrol_user()'s own "if ($roleid)" swallowed it. */
        if ($roleid <= 0) {
            return;
        }

        role_assign($roleid, $userid, context_course::instance($instance->courseid)->id, 'enrol_apply', $instance->id);
    }

    /**
     * Add the applicant to the groups the decider chose, or to the instance's own list.
     *
     * Memberships are tagged with this plugin as their component so that core removes
     * them again when the enrolment goes away (see unenrol_user() in lib/enrollib.php,
     * which only cleans up memberships carrying component 'enrol_apply').
     *
     * The decider's choice is read from the stored record rather than received as an argument,
     * and that is load bearing. An approval taken through the queue completes TWICE over: the
     * enrolment update dispatches its hook before writing the row, so the hook callback finishes
     * the approval first and the queue finishes it again afterwards. Were the two given
     * different lists, the memberships would accumulate rather than replace one another, and a
     * group the approver had deselected would be joined anyway with nothing left to remove it.
     * Reading one stored answer is what makes both passes agree. Where nothing was recorded,
     * the instance's own list applies.
     *
     * @param stdClass $instance Course enrol instance.
     * @param int $userid User to add to the groups.
     * @param int $userenrolmentid User enrolment whose recorded choice wins, 0 for none.
     * @return void
     */
    protected function add_instance_groups($instance, $userid, int $userenrolmentid = 0) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');

        // The decider's choice, read from the record and not received; see the docblock.
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
     * Without this override the membership is silently lost. Core routes any groups_members
     * row whose component starts with "enrol_" to this method
     * (backup/moodle2/restore_stepslib.php), and enrol_plugin's base implementation is empty
     * - deliberately, because the plugins core had in mind re-derive their memberships from a
     * cohort or a linked course and do not need the row. This plugin does not: the membership
     * follows a one-off approval decision that nothing re-runs. Unlike the generic branch
     * beside it, that path has no groups_add_member() fallback and logs no warning, so the
     * loss is completely silent.
     *
     * The stamp is re-applied rather than dropped, so core's unenrol_user() can still clean
     * the membership up again by component and itemid.
     *
     * @param stdClass $instance Enrol instance the membership belongs to.
     * @param int $groupid Group to join.
     * @param int $userid User to add.
     * @return void
     */
    public function restore_group_member($instance, $groupid, $userid) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');

        /* The group must still exist and still belong to this course. A restore can carry a
           membership whose group did not survive, and groups_add_member() throws on an
           unknown group id - which would abort the whole restore over one membership. */
        if (!$DB->record_exists('groups', ['id' => $groupid, 'courseid' => $instance->courseid])) {
            return;
        }

        groups_add_member($groupid, $userid, 'enrol_apply', $instance->id);
    }

    /**
     * Re-create a role assignment this plugin owns, when a course is restored.
     *
     * The exact shape as restore_group_member() above, and it was missed when the approval's
     * role assignment gained its component stamp. Core routes any {role_assignments} row whose
     * component starts with "enrol_" to this method (backup/moodle2/restore_stepslib.php:2350,
     * the same line on 5.1 and 5.2), and enrol_plugin's base implementation is an empty stub.
     * That branch has NO fallback and logs nothing - the neighbouring generic-component branch
     * falls back to role_assign() and writes a backup::LOG_WARNING, and this one does neither -
     * so without this override the assignment simply disappears.
     *
     * Measured on 5.1 and 5.2, restoring one course with four assignments in it so the only
     * variable was the stamp: an applicant approved through confirm_enrolment() came back with
     * an ACTIVE enrolment and NO role, while a bare manual assignment, a bare assignment of the
     * pre-stamp shape and an enrol_self one all survived. That is not a lock-out - is_enrolled()
     * still passes - which is what makes it quiet: the person keeps their place in the course
     * and loses every capability the role carried, and the participants page shows "No roles".
     *
     * The stamp is re-applied rather than dropped, for the same reason it is written in the
     * first place: a bare assignment leaves process_expirations() guessing $instance->roleid,
     * which is wrong by construction once a decider can choose a different role. enrol_flatfile
     * is the precedent for this exact pairing - it stamps, its roles_protected() is false, and
     * it overrides this method (enrol/flatfile/lib.php:693-695).
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
     * The enrol_apply_submission rows are deliberately NOT dropped, which inverts what this
     * method used to do to the plugin's data as a whole. Deleting an enrolment method is an
     * administrative act on the course's configuration; the record of who applied, what they
     * were asked, and what was decided is not part of that configuration and outlives it.
     * The two ways it does go are the two that should end it: the course being deleted, which
     * pseudonymises through \enrol_apply\hook_callbacks::before_course_deleted(), and an
     * erasure request, which deletes it through the privacy provider.
     *
     * This route also covers a case no course-deletion path sees: a restore into an existing
     * course with "delete its contents first" reaches enrol_course_delete() through
     * restore_dbops::delete_course_content(), where the course survives and neither the hook
     * nor the course_deleted event ever fires.
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

        /* This method is core's own per-instance extension point for an enrol plugin,
           dispatched by enrol_add_course_navigation() (lib/enrollib.php) from the course
           settings navigation. A file-scope enrol_apply_extend_navigation_course() would look
           equivalent and is not: it fires for every course whether or not the course has an
           apply instance, and would duplicate this node where one does.
           (For the record, *_extend_settings_navigation() is not dispatched for enrol plugins
           at all, so it would pass the whole of CI while doing nothing.) */
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
     * Every application is authorised individually: an id the current user may not act
     * on is skipped rather than failing the whole batch.
     *
     * @param array $enrols User enrolment ids to confirm.
     * @param string $message Message the decider wrote to the applicant, empty for none.
     * @param array|null $decision Chosen groups and enrolment period, null for the instance defaults.
     * @return void
     */
    public function confirm_enrolment($enrols, string $message = '', ?array $decision = null) {
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

            /* Recorded before the status changes, never after and never through decide().
               update_user_enrol() below dispatches the hook that reaches complete_approval()
               first, and decide() skips a row already at the target status - so a message
               carried any further than here is dropped in silence. */
            \enrol_apply\local\submission::record_outcome_message((int) $userenrolment->id, $message);

            /* The role, allowlisted per instance for the same reason and by the same shape core
               uses at enrol/manual/externallib.php:98-104. It is not optional politeness:
               role_assign() performs no assignability check of any kind - measured on both
               branches, its body holds only argument-shape checks and a lookup that the user
               exists, and it will happily insert an assignment for a role id that does not exist
               at all. This parameter arrives as a bare optional_param() with nothing between it
               and that call, which is exactly the escalation shape enrol_gapply ships.

               get_assignable_roles() is keyed by role id, so the comparison is array_key_exists
               and never in_array: the values are LOCALISED NAMES, and testing an id against
               those lets everything through.

               A refused role records 0 rather than throwing, which matches what the rest of this
               method does with input it will not act on - an approver working a queue is not
               blocked by one bad id. The approval then proceeds with the instance's own role, and
               the forged one is assigned nowhere. Note the fallback is deliberately NOT
               allowlisted: it is what every application has been assigned since this plugin was
               written, and filtering it would silently stop an instance configured with a role
               its teacher may not assign from granting anything at all. */
            if ($decision !== null && array_key_exists('roleid', $decision)) {
                $assignable = get_assignable_roles(context_course::instance($instance->courseid));
                $chosenrole = (int) $decision['roleid'];
                \enrol_apply\local\submission::record_decided_role(
                    (int) $userenrolment->id,
                    array_key_exists($chosenrole, $assignable) ? $chosenrole : 0
                );
            }

            /* Allowlisted here, per instance, and not once for the batch: the ids arrive from a
               posted form and the batch can span courses, so a group that is legitimate for one
               application is not necessarily legitimate for the next. groups_get_all_groups()
               is keyed by group id, so the comparison is array_key_exists and never in_array,
               which would compare an id against group names. */
            if ($decision !== null && !empty($decision['groups'])) {
                $allowed = groups_get_all_groups($instance->courseid);
                $chosen = array_values(array_filter(
                    array_map('intval', $decision['groups']),
                    static function (int $groupid) use ($allowed): bool {
                        return array_key_exists($groupid, $allowed);
                    }
                ));
                \enrol_apply\local\submission::record_decided_groups((int) $userenrolment->id, $chosen);
            }

            /* The decider's period, falling back to the instance's. Recorded on the enrolment
               and not on the record: core already holds an enrolment's dates, and duplicating
               them would give the report two sources that can disagree. */
            $userenrolment->timestart = time();
            $userenrolment->timeend = 0;
            if ($instance->enrolperiod) {
                $userenrolment->timeend = $userenrolment->timestart + $instance->enrolperiod;
            }
            if ($decision !== null && !empty($decision['timestart'])) {
                $userenrolment->timestart = (int) $decision['timestart'];
            }
            if ($decision !== null && array_key_exists('timeend', $decision)) {
                $userenrolment->timeend = (int) $decision['timeend'];
            }

            /* update_user_enrol() dispatches before_user_enrolment_updated, so the
               observer in classes/hook_callbacks.php has usually already run
               complete_approval() by the time this returns. It is called again below on
               purpose: complete_approval() is idempotent, and this method must leave the
               right state behind even if the hook is not registered — after an upgrade
               that has not yet had its caches rebuilt, for instance. */
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
    }

    /**
     * Move the given applications onto the waiting list.
     *
     * @param array $enrols User enrolment ids to defer.
     * @param string $message Message the decider wrote to the applicant, empty for none.
     * @return void
     */
    public function wait_enrolment($enrols, string $message = '') {
        global $DB, $USER;

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

            \enrol_apply\local\submission::record_outcome_message((int) $userenrolment->id, $message);

            $this->update_user_enrol($instance, $userenrolment->userid, ENROL_APPLY_USER_WAIT);

            \enrol_apply\local\submission::decide(
                (int) $userenrolment->id,
                \enrol_apply\local\submission::STATUS_WAITING,
                (int) $USER->id
            );

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
     * @param string $message Message the decider wrote to the applicant, empty for none.
     * @return void
     */
    public function cancel_enrolment($enrols, string $message = '') {
        global $DB, $USER;

        foreach ($enrols as $enrol) {
            $userenrolment = $this->get_pending_user_enrolment($enrol);
            if (!$userenrolment) {
                continue;
            }

            $instance = $DB->get_record('enrol', ['id' => $userenrolment->enrolid, 'enrol' => 'apply'], '*', MUST_EXIST);

            if (!$this->can_manage_application($instance->courseid, $userenrolment->userid)) {
                continue;
            }

            \enrol_apply\local\submission::record_outcome_message((int) $userenrolment->id, $message);

            /* Stamped before the unenrolment, not after: unenrol_user() deletes the
               user_enrolments row, and the id it carried is how the durable record is
               matched. The record itself is untouched by the unenrolment on purpose - a
               cancelled application is exactly the outcome the trail exists to hold. */
            \enrol_apply\local\submission::decide(
                (int) $userenrolment->id,
                \enrol_apply\local\submission::STATUS_CANCELLED,
                (int) $USER->id
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

        /* Read from the durable record rather than passed in, which is what lets one lookup
           serve all three decisions and the approval notification alike - that one is sent from
           an adhoc task, long after any argument would have gone out of scope.
           s() and not format_text(): the surrounding body is the administrator's own template
           and is trusted, while this is free text a decider typed, and it lands in
           fullmessagehtml. nl2br so the paragraphs the decider typed survive; the plain-text
           half of the message is derived from this by html_to_text() in the notification. */
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

        /* What the applicant typed, keyed by the fields this instance actually asks for.
           Both halves come from the submitted data. They did not always: the custom fields
           used to be read back out of {user_info_data} through profile_load_custom_fields(),
           so an approver reviewing an application saw whatever was already on the account
           rather than the answers in front of them - and because the standard fields DID
           come from the form, the two halves of the same message disagreed. */
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
           the box if they meant it. */
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
           ever change, get_mappingid() simply returns false there and the comment is
           dropped — the behaviour before comments were backed up at all. */
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
