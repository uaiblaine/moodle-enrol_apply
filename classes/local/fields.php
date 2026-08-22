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

use context;
use core_user;

/**
 * Which profile fields an application form may ask for, decided at two levels.
 *
 * An administrator sets the site-wide pool in enrol_apply/allowedfields; a teacher picks from
 * that pool per instance, and the picked set is stored on the instance in customtext4. The
 * rule everywhere is INTERSECTION, never negation: {@see resolve()} recomputes the pool from
 * the site on every read and keeps only the picked keys that survive it. That matters because
 * customtext4 is not plugin configuration - core backs it up verbatim and
 * enrol_plugin::add_instance() copies every key of a restored instance with no allowlist, so
 * anyone who can restore a course chooses its contents.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fields {
    /** @var string Prefix marking a standard column of the {user} table. */
    public const SOURCE_STANDARD = 's_';

    /** @var string Prefix marking a custom field, keyed by user_info_field.id. */
    public const SOURCE_CUSTOM = 'c_';

    /**
     * The standard fields offered by default, as field keys.
     *
     * This is \core_user::AUTHSYNCFIELDS (17 names, byte-identical on 5.1 and 5.2) minus four,
     * written out rather than derived so that the configuration and the renderer cannot drift
     * apart when core edits the constant. Excluded and why:
     *
     *  - email: a login identifier whenever $CFG->authloginviaemail is on, so collecting it
     *    through an enrolment form is an account-takeover surface.
     *  - idnumber: PARAM_RAW, no unique index, and neither core user form validates it, yet
     *    sites treat it as an external key and tool_uploaduser's picture importer matches on
     *    it. Letting an enrolment form rewrite it is a poor trade. (It is NOT the key
     *    tool_uploaduser itself reconciles accounts by - that is username, or email under
     *    uumatchemail.)
     *  - lang: core gates it on a negative user id, so a self-editing user is never shown it
     *    on their own profile form and neither should this plugin.
     *  - description: the only one of the 17 with a draft-file lifecycle. It needs the full
     *    file_prepare_standard_editor and file_postupdate_standard_editor cycle, and it is
     *    really two columns, description and descriptionformat. Out of scope, and nothing in
     *    the form may emit an editor for it.
     *
     * @var array
     */
    public const DEFAULT_SET = [
        's_firstname',
        's_lastname',
        's_city',
        's_country',
        's_institution',
        's_department',
        's_phone1',
        's_phone2',
        's_address',
        's_firstnamephonetic',
        's_lastnamephonetic',
        's_middlename',
        's_alternatename',
    ];

    /**
     * Keys that are refused however they arrive, enforced by pool().
     *
     * The second barrier, and core's own minimum: the first five are what
     * update_user_record_by_id() keeps out of a sync, and the rest are the credential and
     * account-state columns. user_update_user() skips only keys that are not columns of
     * {user} at all, so it is no defence against any of these.
     *
     * @var array
     */
    public const DENY = [
        's_username',
        's_id',
        's_auth',
        's_mnethostid',
        's_deleted',
        's_password',
        's_policyagreed',
        's_confirmed',
        's_suspended',
        's_secret',
        's_trustbitmask',
        's_email',
        's_idnumber',
        's_lang',
        's_description',
    ];

    /** @var string The field is rendered as an input and may be written back. */
    public const STATE_EDITABLE = 'editable';

    /** @var string The field is rendered read-only and is never written back. */
    public const STATE_LOCKED = 'locked';

    /** @var string The field is not rendered, not snapshotted and never written. */
    public const STATE_ABSENT = 'absent';

    /**
     * The field key naming a standard {user} column.
     *
     * @param string $column Column name, for example city.
     * @return string The key, for example s_city.
     */
    public static function standard_key(string $column): string {
        return self::SOURCE_STANDARD . $column;
    }

    /**
     * The field key naming a custom profile field.
     *
     * Keyed by id and never by shortname: {user_info_field} carries no unique index on
     * shortname, and core compares shortnames case-insensitively, so a rename would silently
     * re-point the choice at a different field.
     *
     * @param int $id Row id in {user_info_field}.
     * @return string The key, for example c_7.
     */
    public static function custom_key(int $id): string {
        return self::SOURCE_CUSTOM . $id;
    }

    /**
     * Whether the key names a standard {user} column.
     *
     * @param string $key Field key.
     * @return bool True for an s_ key.
     */
    public static function is_standard(string $key): bool {
        return str_starts_with($key, self::SOURCE_STANDARD);
    }

    /**
     * The column or custom field id a key names.
     *
     * @param string $key Field key.
     * @return string The part after the prefix, or an empty string when the key is malformed.
     */
    public static function key_target(string $key): string {
        if (self::is_standard($key)) {
            return substr($key, strlen(self::SOURCE_STANDARD));
        }
        if (str_starts_with($key, self::SOURCE_CUSTOM)) {
            return substr($key, strlen(self::SOURCE_CUSTOM));
        }

        return '';
    }

    /**
     * Every field a site could offer, before the administrator's pool narrows it.
     *
     * @return array Field key => label, in display order.
     */
    public static function offerable(): array {
        global $DB;

        $offerable = [];
        foreach (self::DEFAULT_SET as $key) {
            $offerable[$key] = self::label($key, false);
        }

        $custom = $DB->get_records('user_info_field', null, 'sortorder ASC, id ASC', 'id, name, shortname, datatype');
        foreach ($custom as $field) {
            $offerable[self::custom_key((int) $field->id)] = format_string($field->name, true, ['escape' => false]);
        }

        return $offerable;
    }

    /**
     * The keys the administrator allows courses to ask for.
     *
     * An unset setting means "the site has never been configured", which resolves to the
     * default set rather than to nothing. That distinction is load-bearing:
     * admin_setting_configmulticheckbox stores only the ticked keys, so an empty stored value
     * and an absent one look alike, and reading either as "allow nothing" would silently stop
     * every migrated instance collecting what it collected before the upgrade.
     *
     * @return array List of allowed field keys.
     */
    public static function pool(): array {
        $stored = get_config('enrol_apply', 'allowedfields');
        if ($stored === false || $stored === null) {
            return self::DEFAULT_SET;
        }

        $allowed = array_filter(array_map('trim', explode(',', (string) $stored)), static function (string $key): bool {
            return $key !== '';
        });

        return array_values(array_diff($allowed, self::DENY));
    }

    /**
     * The fields an instance actually collects, recomputed against this site.
     *
     * Four filters, in order: the stored envelope must parse; the key must be in the site
     * pool; the key must not be a name field this site's fullname format has switched off;
     * and a custom field must still exist. A key that fails any of them is dropped
     * silently, because the common reason for one to fail is a course restored from elsewhere
     * rather than an attack, and a hard failure would take out the enrolment page rather than
     * the one field.
     *
     * The deny list is NOT re-checked here, and that is deliberate rather than an oversight.
     * pool() strips it, and pool() is the only way a key reaches the loop below, so a second
     * check is unreachable - which means no test can hold it, and an unreachable guard that
     * nothing pins is worse than no guard: it reads as protection while proving nothing. The
     * barrier lives in pool(), where test_pool_drops_a_denied_key holds it.
     *
     * @param \stdClass $instance Enrol instance record.
     * @return fieldset The surviving set, in the order the instance stored them.
     */
    public static function resolve(\stdClass $instance): fieldset {
        $picked = fieldset::from_json($instance->customtext4 ?? null);
        if ($picked->is_empty()) {
            return $picked;
        }

        $pool = self::pool();
        $existing = self::existing_custom_ids();
        $disablednames = self::disabled_name_keys();

        $survivors = [];
        foreach ($picked->keys() as $key) {
            if (!in_array($key, $pool, true)) {
                continue;
            }
            if (in_array($key, $disablednames, true)) {
                continue;
            }
            if (!self::is_standard($key) && !in_array((int) self::key_target($key), $existing, true)) {
                continue;
            }
            $survivors[] = $key;
        }

        return $picked->only($survivors);
    }

    /**
     * The ids of every custom profile field that currently exists.
     *
     * @return array List of ids as integers.
     */
    protected static function existing_custom_ids(): array {
        global $DB;

        return array_map('intval', $DB->get_fieldset_select('user_info_field', 'id', ''));
    }

    /**
     * How a field should be treated for one user, decided before any element is created.
     *
     * Consumed from the form in a later slice. It is decided up front because a required rule
     * is attached when an element is created and HTML_QuickForm::validate() walks its rule
     * list by name without checking that the element still exists - so any add-then-remove
     * technique leaves the form permanently unsubmittable with no visible field to explain why.
     *
     * @param string $key Field key.
     * @param \stdClass $user User the form is being built for.
     * @return string One of the STATE_ constants.
     */
    public static function classify(string $key, \stdClass $user): string {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        if (in_array($key, self::DENY, true)) {
            return self::STATE_ABSENT;
        }
        if (isguestuser($user) || !empty($user->deleted) || is_mnet_remote_user($user)) {
            return self::STATE_ABSENT;
        }

        $authplugin = get_auth_plugin($user->auth ?? 'manual');
        if (!$authplugin->can_edit_profile()) {
            return self::STATE_ABSENT;
        }

        if (self::is_standard($key)) {
            $column = self::key_target($key);
            if (!in_array($key, self::DEFAULT_SET, true)) {
                return self::STATE_ABSENT;
            }

            return self::is_locked($authplugin, 'field_lock_' . $column, $user->{$column} ?? '')
                ? self::STATE_LOCKED
                : self::STATE_EDITABLE;
        }

        $field = $DB->get_record('user_info_field', ['id' => (int) self::key_target($key)], '*', IGNORE_MISSING);
        if (!$field) {
            return self::STATE_ABSENT;
        }
        /* PROFILE_VISIBLE_NONE is defined as the STRING '0', so both sides are cast: a
           strict comparison of int 0 against string '0' is false and the branch would never
           be taken. */
        if ((int) $field->visible === (int) PROFILE_VISIBLE_NONE) {
            return self::STATE_ABSENT;
        }
        if (!empty($field->locked)) {
            return self::STATE_LOCKED;
        }

        $stored = $DB->get_field('user_info_data', 'data', [
            'userid' => $user->id,
            'fieldid' => $field->id,
        ]);

        return self::is_locked($authplugin, 'field_lock_profile_field_' . $field->shortname, (string) $stored)
            ? self::STATE_LOCKED
            : self::STATE_EDITABLE;
    }

    /**
     * Whether an auth plugin locks the named field for a user who currently holds a value.
     *
     * The lock is read through the auth plugin object rather than through get_config(), which
     * is not a stylistic preference: auth_manual builds its own config by merging the legacy
     * component under the modern one, so get_config('auth_manual', ...) alone misses locks the
     * core user edit form honours, and every other auth plugin constructs its config its own
     * way. Note also that unlockedifempty tests emptiness with a loose comparison against the
     * empty string, so a stored '0' counts as FILLED and therefore locked - the opposite of
     * what !empty() would decide.
     *
     * @param \auth_plugin_base $authplugin Auth plugin the user authenticates through.
     * @param string $configkey Lock config key, for example field_lock_city.
     * @param string $value The value the user currently holds.
     * @return bool True when the field must be rendered read-only.
     */
    protected static function is_locked($authplugin, string $configkey, string $value): bool {
        $lock = $authplugin->config->{$configkey} ?? 'unlocked';
        if ($lock === 'locked') {
            return true;
        }
        if ($lock === 'unlockedifempty') {
            return $value != '';
        }

        return false;
    }

    /**
     * The human-readable label of a field, in the spelling the sink needs.
     *
     * Two spellings, because the two sinks differ and the difference is invisible. A moodleform
     * label renders through a triple stash in element-template.mustache, so it needs the
     * ESCAPED spelling; a Mustache double stash and a get_string parameter escape for
     * themselves and need the PLAIN one. Core solves the same problem the same way, in
     * core_customfield\field_controller::get_formatted_name(bool $escape = true).
     *
     * @param string $key Field key.
     * @param bool $escape Whether the caller renders raw and therefore needs the escaped spelling.
     * @return string The label, or the key itself when nothing names it.
     */
    public static function label(string $key, bool $escape = true): string {
        global $DB;

        if (self::is_standard($key)) {
            $column = self::key_target($key);
            /* A literal per key, never get_string('...' . $column): the fleet standard bans a
               dynamic string id, and every one of these is a core string. */
            $stringid = self::standard_label_id($column);

            return $stringid === '' ? $key : get_string($stringid);
        }

        $name = $DB->get_field('user_info_field', 'name', ['id' => (int) self::key_target($key)]);
        if ($name === false) {
            return $key;
        }

        return format_string($name, true, ['escape' => $escape]);
    }

    /**
     * The core language string identifier labelling a standard column.
     *
     * @param string $column Column of the {user} table.
     * @return string String identifier in core, or an empty string when the column has no label.
     */
    protected static function standard_label_id(string $column): string {
        return match ($column) {
            'firstname' => 'firstname',
            'lastname' => 'lastname',
            'city' => 'city',
            'country' => 'country',
            'institution' => 'institution',
            'department' => 'department',
            'phone1' => 'phone1',
            'phone2' => 'phone2',
            'address' => 'address',
            'firstnamephonetic' => 'firstnamephonetic',
            'lastnamephonetic' => 'lastnamephonetic',
            'middlename' => 'middlename',
            'alternatename' => 'alternatename',
            default => '',
        };
    }

    /**
     * What the applicant actually typed, as label and value pairs ready for the notification.
     *
     * Both standard and custom values are taken from the SUBMITTED data. Reading a custom
     * field back out of {user_info_data} instead - which is what this plugin used to do -
     * shows the approver whatever was already on the account rather than the answer in front
     * of them, and since the standard fields came from the form, the two halves of the same
     * message disagreed with each other.
     *
     * The value is returned EXACTLY as it was typed, and is deliberately not put through
     * format_string(). That is a considered departure from the plugin's usual rule for
     * user-supplied profile values, and the reason is measurable: format_string() runs
     * strip_tags(), which treats a bare "<" as the start of a tag and deletes everything
     * after it - so an applicant who types "A<B and R&D" has their answer delivered to the
     * approver as "A", silently, with nothing on either side to show that anything was lost.
     *
     * Escaping belongs at the sink, and the only sink here escapes for itself: the
     * notification template renders every value through a double stash. A value that later
     * has to satisfy PARAM_TEXT - a web service return, a report column - must be stripped at
     * THAT boundary, where losing the tail is a deliberate cost rather than an accident.
     *
     * @param \stdClass $instance Enrol instance the application was submitted to.
     * @param \stdClass $data Submitted form data.
     * @return array List of arrays with 'label' and 'value' keys, in the instance's field order.
     */
    public static function submitted_values(\stdClass $instance, \stdClass $data): array {
        $values = [];
        foreach (self::resolve($instance)->keys() as $key) {
            $property = self::form_element_name($key);
            if (!isset($data->$property)) {
                continue;
            }
            $raw = $data->$property;
            if (is_array($raw)) {
                // An editor or a multi-value element; the form never offers one for these keys.
                continue;
            }
            $value = trim((string) $raw);
            if ($value === '') {
                continue;
            }
            $values[] = [
                'label' => self::label($key, false),
                'value' => $value,
            ];
        }

        return $values;
    }

    /**
     * The form element name carrying a field's value.
     *
     * Standard fields keep their column name, because that is what core's own profile form
     * calls them; custom fields use core's profile_field_<shortname> convention, which is
     * what profile_definition() emits and what profile_save_data() reads.
     *
     * @param string $key Field key.
     * @return string Element name, or an empty string when the field no longer exists.
     */
    public static function form_element_name(string $key): string {
        global $DB;

        if (self::is_standard($key)) {
            return self::key_target($key);
        }

        $shortname = $DB->get_field('user_info_field', 'shortname', ['id' => (int) self::key_target($key)]);

        return $shortname === false ? '' : 'profile_field_' . $shortname;
    }

    /**
     * The value a user currently holds for a field.
     *
     * @param string $key Field key.
     * @param \stdClass $user User to read.
     * @return string The stored value, or an empty string when there is none.
     */
    public static function current_value(string $key, \stdClass $user): string {
        global $DB;

        if (self::is_standard($key)) {
            $column = self::key_target($key);

            return (string) ($user->{$column} ?? '');
        }

        $stored = $DB->get_field('user_info_data', 'data', [
            'userid' => $user->id,
            'fieldid' => (int) self::key_target($key),
        ]);

        return $stored === false ? '' : (string) $stored;
    }

    /**
     * The name-field keys the site's fullname format has switched off.
     *
     * The four phonetic, middle and alternate name fields are subject to
     * useredit_get_enabled_name_fields(): when $CFG->fullnamedisplay does not use them, core
     * does not render them on its own profile form and neither should this plugin.
     *
     * @return array List of field keys to drop.
     */
    public static function disabled_name_keys(): array {
        global $CFG;

        require_once($CFG->dirroot . '/user/editlib.php');

        $optional = ['firstnamephonetic', 'lastnamephonetic', 'middlename', 'alternatename'];
        $enabled = useredit_get_enabled_name_fields();

        $disabled = [];
        foreach ($optional as $column) {
            if (!in_array($column, $enabled, true)) {
                $disabled[] = self::standard_key($column);
            }
        }

        return $disabled;
    }
}
