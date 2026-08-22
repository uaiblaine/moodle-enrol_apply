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

namespace enrol_apply\local;

use context_system;

/**
 * Writes an applicant's own answers back to their profile, when they ask for it.
 *
 * The whole class exists because core will not stop you. user_update_user() consults no
 * capability and no field_lock_* setting, and profile_save_data() performs no authorisation
 * check of any kind - it iterates every field and writes whatever is on the object it is
 * handed. Core's only defence against a forged post is setConstant() winning in
 * exportValues(), and this plugin does not even render a locked field as an input, so there
 * is no constant to win. Every guard therefore lives here.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profilewriter {
    /**
     * Whether this instance may write to a profile at all.
     *
     * Two switches, and neither alone is enough: a site master switch that defaults off and
     * has no restore surface, and a per-instance opt-in that a cross-site restore zeroes.
     * A course restored into a category the restorer controls must not be able to turn the
     * write on by itself.
     *
     * @param \stdClass $instance Enrol instance.
     * @return bool True when both switches are on.
     */
    public static function is_enabled(\stdClass $instance): bool {
        if (!get_config('enrol_apply', 'allowprofilewrite')) {
            return false;
        }

        return !empty($instance->customint8);
    }

    /**
     * Write the applicant's answers to their own profile.
     *
     * The keys written are recomputed here from the instance and the user, never taken from
     * the set of keys submitted. That is the difference between a guard and a decoration: a
     * post carrying "auth" or a locked "city" reaches this method exactly as a legitimate one
     * does, and the only thing standing between it and {user} is this recomputation.
     *
     * An enrolment form may add to a profile but never empty it. Core's own boundary would
     * erase - profile_field_base::edit_save_data() ignores a value only when the property is
     * ABSENT, so an empty string is written straight through - but blanking somebody's stored
     * details as a side effect of applying for a course is not something an applicant asked
     * for, and the profile page is where clearing a field belongs.
     *
     * @param \stdClass $instance Enrol instance the application was submitted to.
     * @param \stdClass $user The applicant.
     * @param array $submitted Submitted values keyed by form element name.
     * @return array The changes actually written, in the shape diff::compute() returns.
     */
    public static function write(\stdClass $instance, \stdClass $user, array $submitted): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');

        if (!self::is_enabled($instance)) {
            return [];
        }

        $authplugin = get_auth_plugin($user->auth);
        if (!$authplugin->can_edit_profile()) {
            return [];
        }
        if (!has_capability('moodle/user:editownprofile', context_system::instance(), $user)) {
            return [];
        }

        // Recomputed, not trusted: only what this user may edit on this instance right now.
        $changes = diff::compute($instance, $user, $submitted);
        if (!$changes) {
            return [];
        }

        $usernew = (object) ['id' => $user->id];
        $wrotestandard = false;
        foreach ($changes as $change) {
            $name = fields::form_element_name($change['key']);
            $usernew->{$name} = $change['after'];
            if (fields::is_standard($change['key'])) {
                $wrotestandard = true;
            }
        }
        $usernew->timemodified = time();

        /* Core's order, and each step matters. The auth plugin is told first, or an external
           directory goes stale; user_update_user() is called with $triggerevent = false so
           the event is not born in the middle of the write; profile_save_data() writes the
           custom fields; and one event is fired at the end. */
        if ($wrotestandard && !$authplugin->user_update($DB->get_record('user', ['id' => $user->id]), $usernew)) {
            throw new \moodle_exception('cannotupdateprofile');
        }
        if ($wrotestandard) {
            user_update_user($usernew, false, false);
        }
        profile_save_data($usernew);

        /* Ids and counts, never values. The log store is covered by no privacy provider and
           reached by no deletion request, so a value written into an event outlives every
           erasure the plugin can honour. */
        \core\event\user_updated::create_from_userid($user->id)->trigger();

        return $changes;
    }
}
