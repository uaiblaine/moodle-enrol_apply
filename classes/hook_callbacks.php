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
     * The applicant IS notified, and that is worth stating because this docblock used to
     * claim the opposite: complete_approval() queues \enrol_apply\task\notify_approval for
     * every route an approval can take, this one included. Queueing is deduplicated on
     * classname, component and custom data, so the manager who approves from core's screen
     * and the one who approves from the plugin's queue each produce exactly one message.
     *
     * @param before_user_enrolment_updated $hook The dispatched hook.
     * @return void
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

        $plugin = enrol_get_plugin('apply');
        $plugin->complete_approval($hook->enrolinstance, (int) $userenrolment->userid, (int) $userenrolment->id);
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
     * row is gone is invisible to subject access and unreachable by erasure - with nothing
     * anywhere to say so. The event is too late by two statements.
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
