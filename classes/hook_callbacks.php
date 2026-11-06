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

namespace enrol_apply;

use core_course\hook\before_course_deleted;
use core_enrol\hook\before_user_enrolment_updated;

/**
 * Hook callbacks keeping the plugin's own data consistent with core enrolment changes.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Finish an approval that was performed outside confirm_enrolment().
     *
     * confirm_enrolment() is not the only way an application can become active. Core's
     * participants page offers "Edit enrolment", which posts to enrol/editenrolment.php;
     * that page requires only enrol/apply:manage and calls
     * course_enrolment_manager::edit_enrolment(), which sets the status straight through
     * enrol_plugin::update_user_enrol(). Nothing on that path knows about this plugin, so
     * the applicant would end up active while still carrying an application row and
     * without the group memberships the instance is configured to grant.
     *
     * Rather than trying to forbid that path — enrol/apply:manage is core's "may edit
     * enrolments" capability and denying it would remove legitimate date editing too —
     * this observer reconciles the state afterwards, whichever route was taken.
     *
     * The applicant is notified on this route too: complete_approval() queues
     * \enrol_apply\task\notify_approval, and queueing is deduplicated on classname, component
     * and custom data while the task is queued, so a queue approval, which reaches
     * complete_approval() both directly and through this hook, still sends one message.
     *
     * It is also the one place that can refuse that route to a decider narrowed by separate
     * groups (docs/design/group-review-scope.md, S7). The hook is not stoppable, but
     * enrol_plugin::update_user_enrol() dispatches it before its own write, and neither the hook
     * manager nor enrol/editenrolment.php catches what a callback throws, so the exception
     * aborts the update with the row unchanged. Both of core's routes here, the page and
     * core_enrol_submit_user_enrolment_form, need enrol/apply:manage; see refuses_approval().
     *
     * @param before_user_enrolment_updated $hook The dispatched hook.
     * @return void
     * @throws \moodle_exception When the acting user may not approve an application outside their groups.
     */
    public static function before_user_enrolment_updated(before_user_enrolment_updated $hook): void {
        global $DB;

        if ($hook->enrolinstance->enrol !== 'apply' || !$hook->statusmodified) {
            return;
        }

        /* The hook fires before the row is written, and $userenrolmentinstance already
           carries the new status (lib/enrollib.php sets it, then dispatches, then
           updates). Anything other than a move to active leaves the application alone:
           suspending or deferring keeps it in the queue on purpose. */
        $userenrolment = $hook->userenrolmentinstance;
        if ((int) $userenrolment->status !== ENROL_USER_ACTIVE) {
            return;
        }

        // No application row means this was an ordinary enrolment edit, not an approval.
        if (!$DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $userenrolment->id])) {
            return;
        }

        // Before anything is written, so a refusal leaves the application exactly as it was.
        if (self::refuses_approval($hook->enrolinstance, $userenrolment)) {
            throw new \moodle_exception('groupscopeapprovalrefused', 'enrol_apply');
        }

        $plugin = enrol_get_plugin('apply');
        $plugin->complete_approval($hook->enrolinstance, (int) $userenrolment->userid, (int) $userenrolment->id);
    }

    /**
     * Whether the acting user is a decider narrowed by separate groups approving an application
     * outside their groups.
     *
     * The acting user is $USER, and only somebody holding enrol/apply:manage in the course is
     * judged at all, because that is what both of core's routes into this hook require. The
     * other callers are not deciders and must pass: a group key's admission runs as the
     * applicant, who is in no group yet, and a CLI script or a task runs as the administrator,
     * who may access all groups, or as nobody. The plugin's own confirm_enrolment() reaches this
     * hook too, after can_manage_application() has applied the same group check to the row, so
     * it passes.
     *
     * The group check is {@see \enrol_apply\local\groupscope::allows()}, the one the course
     * grant is narrowed by. A narrowed user who also mentors the applicant may still approve,
     * because the mentor level is not narrowed: can_manage_application() decides that, exactly
     * as it does on the queue.
     *
     * @param \stdClass $instance The apply instance.
     * @param \stdClass $userenrolment The user enrolment about to become active.
     * @return bool True when the update must be refused.
     */
    private static function refuses_approval(\stdClass $instance, \stdClass $userenrolment): bool {
        global $USER;

        /* No separate check for a run with no user: nobody holds the capability then, so the
           line below already lets it through. */
        $context = \context_course::instance((int) $instance->courseid, IGNORE_MISSING);
        if (!$context || !has_capability('enrol/apply:manage', $context)) {
            return false;
        }

        if (local\groupscope::allows((int) $instance->courseid, (int) $userenrolment->id, (int) $USER->id)) {
            return false;
        }

        return !enrol_get_plugin('apply')->can_manage_application(
            (int) $instance->courseid,
            (int) $userenrolment->userid,
            (int) $userenrolment->id
        );
    }

    /**
     * Strip the personal data out of a deleted course's application trail.
     *
     * The trail survives every other deletion path on purpose - approval, cancellation,
     * unenrolment, and the removal of the enrolment method itself - but a deleted course is
     * where it has to stop being personal data, because after this point it can no longer be
     * reached by a data subject at all.
     *
     * It must be this hook and not the course_deleted event. delete_course()
     * (lib/moodlelib.php) dispatches the hook first, then empties the course, then calls
     * context_helper::delete_instance(CONTEXT_COURSE, ...), and only then triggers the event.
     * Every privacy provider query is wrapped in a JOIN against {context} by
     * contextlist::add_from_sql(), so a row still carrying a real userid once that context
     * row is gone is silently invisible to subject access and unreachable by erasure.
     *
     * What is kept is the dates and the status, which identify nobody; what goes is both
     * user ids and the whole snapshot the applicant submitted.
     *
     * @param before_course_deleted $hook The dispatched hook.
     * @return void
     */
    public static function before_course_deleted(before_course_deleted $hook): void {
        \enrol_apply\local\submission::pseudonymise((int) $hook->course->id);
    }
}
