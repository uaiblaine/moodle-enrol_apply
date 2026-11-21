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
     * Judge every change core is about to write to an application, and expect the approvals.
     *
     * confirm_enrolment() is not the only way an application can become active. Core's
     * participants page offers "Edit enrolment", which posts to enrol/editenrolment.php (and
     * core_enrol_submit_user_enrolment_form does the same); both require only enrol/apply:manage
     * and call course_enrolment_manager::edit_enrolment(), which writes the status and the dates
     * straight through enrol_plugin::update_user_enrol(). Nothing on that path knows about this
     * plugin, so the applicant would end up active while still carrying an application row and
     * without the role and the groups the instance is configured to grant.
     *
     * What counts as an application is what the queue lists ({@see local\queue::is_awaiting_decision()})
     * on the row as it is BEFORE this update, read from the database because the hook carries the
     * new values: a re-queued enrolment (approved, then suspended) is one again, and the queue
     * offers it again. An expired one nobody ever decided, which still carries its application
     * row, is one too, as it always was for this hook. Any change to such a row is a decision on
     * it: activating approves it, and a date moves it out of the queue (a past timeend) or into
     * core's expiry sweep. So a holder of enrol/apply:manage must also be able to decide it
     * ({@see self::refuses_approval()}), and an active enrolment's dates stay core's.
     *
     * The hook is not stoppable, but neither the hook manager nor enrol/editenrolment.php catches
     * what a callback throws, so the exception aborts the update with the row unchanged.
     *
     * Nothing is written here: core writes the row after this hook, with no transaction, and a
     * listener after this one may still throw. An approval is only expected
     * ({@see local\approvalwrite}), and \enrol_apply\observers::user_enrolment_updated() runs
     * complete_approval() once core has written the row. The applicant is notified on this route
     * too: complete_approval() queues \enrol_apply\task\notify_approval, deduplicated while it is
     * queued, so a queue approval, which reaches complete_approval() both from that observer and
     * from activate(), still sends one message.
     *
     * @param before_user_enrolment_updated $hook The dispatched hook.
     * @return void
     * @throws \moodle_exception When the acting user may not decide the application.
     */
    public static function before_user_enrolment_updated(before_user_enrolment_updated $hook): void {
        global $DB;

        if ($hook->enrolinstance->enrol !== 'apply') {
            return;
        }

        $userenrolment = $hook->userenrolmentinstance;
        $userenrolmentid = (int) $userenrolment->id;

        /* An expectation no observer consumed (an earlier write of this enrolment that threw after
           this hook, or ran under an event sink) belongs to that write, not to this one. */
        local\approvalwrite::forget($userenrolmentid);

        // Core writes after this hook, so the database still holds the row as it was.
        $before = $DB->get_record('user_enrolments', ['id' => $userenrolmentid]);
        if (!$before || !self::is_application($before)) {
            return;
        }

        // Before anything is written, so a refusal leaves the application exactly as it was.
        if (!local\approvalwrite::is_own($userenrolmentid) && self::refuses_approval($hook->enrolinstance, $userenrolment)) {
            throw new \moodle_exception('editenrolmentrefused', 'enrol_apply');
        }

        // The row was not active, so an active one here is an approval.
        if ((int) $userenrolment->status === ENROL_USER_ACTIVE) {
            local\approvalwrite::expect($userenrolmentid);
        }
    }

    /**
     * Whether a user enrolment, as it stands in the database, is an application.
     *
     * @param \stdClass $userenrolment The {user_enrolments} row before the update.
     * @return bool True for a row the queue lists, or an expired one still carrying its application row.
     */
    private static function is_application(\stdClass $userenrolment): bool {
        global $DB;

        if (local\queue::is_awaiting_decision($userenrolment)) {
            return true;
        }

        return (int) $userenrolment->status !== ENROL_USER_ACTIVE
            && $DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $userenrolment->id]);
    }

    /**
     * Whether the acting user may not decide the application core is about to change.
     *
     * The acting user is $USER, and only somebody holding enrol/apply:manage in the course is
     * judged at all, because that is what both of core's routes into this hook require; such a
     * user must then pass can_manage_application(), exactly as on the queue: the deciding
     * capability, the group narrowing of the course grant, the mentor level, and never their own
     * application. The other callers are not deciders and must pass: a CLI script or a task runs
     * as the administrator or as nobody. The plugin's own activate() is not judged at all
     * ({@see local\approvalwrite::own()}): its callers decided first, and a key admission runs as
     * the applicant, whom can_manage_application() would refuse as their own application.
     *
     * @param \stdClass $instance The apply instance.
     * @param \stdClass $userenrolment The user enrolment about to change.
     * @return bool True when the update must be refused.
     */
    private static function refuses_approval(\stdClass $instance, \stdClass $userenrolment): bool {
        /* No separate check for a run with no user: nobody holds the capability then, so the
           line below already lets it through. */
        $context = \context_course::instance((int) $instance->courseid, IGNORE_MISSING);
        if (!$context || !has_capability('enrol/apply:manage', $context)) {
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
