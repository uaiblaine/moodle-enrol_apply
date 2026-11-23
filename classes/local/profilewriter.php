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
 * The whole class exists because core will not stop you. \core\user::update_user() consults no
 * capability and no field_lock_* setting, and neither profile_save_data() nor a field type's
 * edit_save_data() performs an authorisation check of any kind - they write whatever is on
 * the object they are handed. Core's only defence against a forged post is setConstant() winning in
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
     * has no restore surface, and a per-instance opt-in that every restore zeroes
     * (enrol_apply_plugin::restore_instance()).
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
     * the set of keys submitted: a post carrying "auth" or a locked "city" reaches this method
     * exactly as a legitimate one does, and this recomputation is the only thing between it
     * and {user}.
     *
     * An enrolment form may add to a profile but never empty it. Core's own boundary would
     * erase - profile_field_base::edit_save_data() ignores a value only when the property is
     * ABSENT, so an empty string is written straight through - but blanking somebody's stored
     * details as a side effect of applying for a course is not something an applicant asked
     * for, and the profile page is where clearing a field belongs.
     *
     * The user is read again from the database, never taken as handed over. The caller's copy is
     * the session's $USER, which core refreshes only on its own profile page (user/edit.php copies
     * the reloaded row into it): an unlockedifempty field filled in by this write would still read
     * as empty there and could be overwritten again in the same session, and an auth plugin or a
     * locked value an administrator changed since the login would not be seen. For the same
     * reason the session's own copy is refreshed after a write.
     *
     * @param \stdClass $instance Enrol instance the application was submitted to.
     * @param \stdClass $user The applicant; only the id is read.
     * @param array $submitted Submitted values keyed by form element name.
     * @return array The changes actually written, in the shape diff::compute() returns.
     */
    public static function write(\stdClass $instance, \stdClass $user, array $submitted): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        if (!self::is_enabled($instance)) {
            return [];
        }

        $user = $DB->get_record('user', ['id' => (int) $user->id]);
        if (!$user) {
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
        $custom = [];
        $written = [];
        foreach ($changes as $change) {
            if (!fields::is_standard($change['key'])) {
                /* Asked again: the form validated this value when the application was submitted,
                   and the offer may be accepted much later, after another user took a value the
                   field requires to be unique. A change its type now refuses is not written and
                   not reported as written. */
                $field = customfield::field((int) fields::key_target($change['key']), (int) $user->id);
                if ($field !== null && !customfield::errors($field, (int) $user->id, $change['values'])) {
                    $custom[] = [$field, $change];
                    $written[] = $change;
                }
                continue;
            }
            $written[] = $change;
            foreach ($change['values'] as $name => $value) {
                $usernew->{$name} = $value;
            }
            $wrotestandard = true;
        }
        $usernew->timemodified = time();

        /* The order of core's user/edit.php, and each step matters. The auth plugin is told
           first, or an external directory goes stale; \core\user::update_user() is called with
           $triggerevent = false so the event is not born in the middle of the write; each
           custom field is written by its own type's edit_save_data(), one field at a time
           rather than through profile_save_data(), so each type finds its own elements in the
           request (customfield::save()); and one event is fired at the end. */
        if ($wrotestandard && !$authplugin->user_update($user, $usernew)) {
            throw new \moodle_exception('cannotupdateprofile');
        }
        if ($wrotestandard) {
            \core\user::update_user($usernew, false, false);
        }
        foreach ($custom as [$field, $change]) {
            customfield::save($field, (int) $user->id, $change['values']);
        }
        if (!$written) {
            return [];
        }

        self::refresh_session_user((int) $user->id, array_keys((array) $usernew), (bool) $custom);

        /* Ids only, as core's own profile edit fires it: no field value goes into the log. */
        \core\event\user_updated::create_from_userid($user->id)->trigger();

        return $written;
    }

    /**
     * Copy what was just written into the session's $USER, when the write was to the session's user.
     *
     * The values come from the row as stored, as user/edit.php copies them, so $USER holds what
     * core kept rather than what was sent.
     *
     * @param int $userid The user written to.
     * @param array $columns The {user} columns written.
     * @param bool $custom Whether a custom profile field was written.
     * @return void
     */
    protected static function refresh_session_user(int $userid, array $columns, bool $custom): void {
        global $DB, $USER;

        if ((int) ($USER->id ?? 0) !== $userid) {
            return;
        }
        $stored = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        foreach ($columns as $column) {
            if ($column !== 'id' && property_exists($stored, $column)) {
                $USER->{$column} = $stored->{$column};
            }
        }
        if ($custom) {
            profile_load_custom_fields($USER);
        }
    }
}
