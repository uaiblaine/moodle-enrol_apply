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

use stdClass;

/**
 * Which profile fields the approval queue may be narrowed by, and how each one narrows it.
 *
 * The vocabulary in one place, because four things read it and they must not disagree: the admin
 * setting that offers the choices, the filterset that declares the accepted filter names, the table
 * that builds the predicate, and the renderer that draws the controls.
 *
 * The offered set is an intersection. The administrator picks from the fields this site names in
 * showuseridentity; the reader is then offered only those they may already see in the queue they
 * are looking at, so ticking a box grants nobody anything. That second half comes from the caller:
 * the field list passed to resolve() is core's own identity mapping, which
 * \core_user\fields::get_identity_fields() has already filtered by capability, by
 * hiddenuserfields and by dropping deleted custom fields.
 *
 * Never build that list here, and never hand a name to \core_user\fields::including(). That method
 * is array_merge with no validation, and get_sql()'s standard branch builds "{$alias}{$field}" for
 * any string it is given - so a stored setting value that is not (or no longer) an identity field
 * would become a WHERE-usable mapping onto any {user} column, u.password included, with no
 * capability check in the path. Taking the list from the mapping and nothing else inherits the
 * gate in get_identity_fields().
 *
 * The submitted-profile snapshot is not a filter source and cannot become one. It is masked per
 * row by the report's own visible_keys(), and no filterable surface can honour a per-row mask: an
 * operator would recover a withheld value by filtering for it and reading the count. The same rule
 * keeps the snapshot out of the search.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queuefilter {
    /**
     * Query-string names a filter token may not take.
     *
     * The queue's GET form carries each filter as a parameter of its own, so a token colliding with
     * one of these would silently overwrite the scope, the paging or the decision form's own fields.
     * token() checks standard field names against it; a custom field travels as CUSTOM_PREFIX plus
     * its id and cannot collide.
     *
     * @var array
     */
    public const RESERVED = [
        'id', 'search', 'status', 'page', 'perpage', 'tsort', 'tdir', 'treset',
        'tifirst', 'tilast', 'thide', 'tshow', 'download', 'userenrol', 'formaction',
        'userenrolments', 'sesskey', 'confirmed', 'appliedfrom', 'appliedto',
    ];

    /** @var string Prefix distinguishing a custom profile field's token from a standard column. */
    public const CUSTOM_PREFIX = 'pf';

    /**
     * The fields an administrator has offered as filters.
     *
     * Read with the global get_config() and never through an enrol_plugin object, which memoises
     * $this->config: a set_config() in a test would then leave an already-built plugin on the old
     * value and the test would exercise nothing.
     *
     * Absent and the empty string both mean no filters. The setting ships empty, so an existing
     * site upgrades with the date filters and no field filters until somebody ticks a box; and
     * admin_setting_configmulticheckbox::write_setting() stores nothing at all on a site with no
     * identity fields configured.
     *
     * @return array Identity field names, in core's own spelling.
     */
    public static function pool(): array {
        $stored = get_config('enrol_apply', 'queuefilterfields');
        if ($stored === false || $stored === null || $stored === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $stored)), static function (string $name): bool {
            return $name !== '';
        }));
    }

    /**
     * What the administration offers to tick.
     *
     * A null context on purpose: the settings page is asking what this SITE could filter by, not
     * what one reader may see, and get_identity_fields() skips its capability check for a null
     * context while still dropping a custom field that no longer exists.
     *
     * @return array Identity field name => label, in the ESCAPED spelling the setting's own
     *         template requires.
     */
    public static function choices(): array {
        $choices = [];
        foreach (\core_user\fields::get_identity_fields(null) as $name) {
            if (self::token($name) === '') {
                continue;
            }
            // The setting's template renders this through a triple stash; see label().
            $choices[$name] = self::label($name);
        }

        return $choices;
    }

    /**
     * A field's name as the reader reads it.
     *
     * Not core's get_display_name(), which mixes the two spellings: format_string() - escaping by
     * default - for a custom field, and a bare get_string() for a standard one. This produces one
     * spelling and lets each sink ask for what it needs, as the renderer does for role names.
     *
     * @param string $name Identity field name.
     * @param bool $escape True for a sink that renders raw, false for one that escapes.
     * @return string The label.
     */
    public static function label(string $name, bool $escape = true): string {
        global $CFG;

        if (preg_match(\core_user\fields::PROFILE_FIELD_REGEX, $name, $matches)) {
            require_once($CFG->dirroot . '/user/profile/lib.php');
            $field = profile_get_custom_field_data_by_shortname($matches[1], false);

            return $field ? format_string($field->name, true, ['escape' => $escape]) : $name;
        }

        $label = get_string($name);

        return $escape ? s($label) : $label;
    }

    /**
     * The alphanumeric token naming one field on the wire.
     *
     * Two constraints meet here. Core's dynamic-table service declares a filter name as
     * PARAM_ALPHANUM, so `profile_field_cpf` is refused before this plugin is consulted; and a
     * profile shortname may legally hold an underscore, so it cannot simply be stripped of its
     * prefix. The token is therefore the field's ID for a custom field - which is also the only
     * stable key it has, {user_info_field} carrying no unique index on shortname - and the bare
     * column for a standard one, which is alphanumeric already.
     *
     * @param string $name Identity field name.
     * @return string The token, or '' for a field that cannot be offered.
     */
    public static function token(string $name): string {
        global $CFG;

        if (preg_match(\core_user\fields::PROFILE_FIELD_REGEX, $name, $matches)) {
            require_once($CFG->dirroot . '/user/profile/lib.php');
            $field = profile_get_custom_field_data_by_shortname($matches[1], false);

            return $field ? self::CUSTOM_PREFIX . (int) $field->id : '';
        }

        if (in_array($name, self::RESERVED, true) || !preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            return '';
        }

        return $name;
    }

    /**
     * The filters this reader is actually offered, and how each one narrows the queue.
     *
     * No label is resolved here. This runs from applications::set_filterset(), which core's
     * dynamic-table service calls before validate_context()
     * (lib/table/classes/external/dynamic/get.php), so on the refresh path $PAGE has no context
     * yet - and every label of a custom profile field goes through format_string(), which asks
     * $PAGE for one.
     *
     * moodle_page::magic_get_context() then throws "$PAGE->context was not set" when AJAX_SCRIPT
     * and developer debugging are both on, so the queue stops refreshing with the exception inside
     * an HTTP 200 response. At lower debug levels it emits a debugging() notice and substitutes
     * the system context, so the label is silently resolved against the wrong context. The page
     * path sets the context first and sees neither. The renderer asks label() for a label when it
     * has a page to render onto.
     *
     * @param array $mappings Identity field name => SQL expression, from core's get_sql().
     * @return array Token => object carrying name, token, control, options and expression.
     */
    public static function resolve(array $mappings): array {
        $offered = [];
        foreach (self::pool() as $name) {
            if (!array_key_exists($name, $mappings)) {
                // Offered by the administrator, not visible to this reader in this scope.
                continue;
            }

            $token = self::token($name);
            if ($token === '') {
                continue;
            }

            $shape = self::shape($name);
            if ($shape === null) {
                continue;
            }

            $offered[$token] = (object) [
                'name' => $name,
                'token' => $token,
                'control' => $shape->control,
                'options' => $shape->options,
                'expression' => $mappings[$name],
            ];
        }

        return $offered;
    }

    /**
     * What kind of control a field gets, and the values it offers.
     *
     * A closed vocabulary becomes a select; everything else is a text box, for disclosure reasons.
     * A text filter is a strict narrowing of the search this queue already offers over the same
     * expressions, so it can answer no question the search box cannot. A list of the DISTINCT
     * values present would enumerate rather than confirm, and the query behind it would be a third
     * consumer of the scope predicate - one that, bounded by the instance alone, would list the
     * cities of applicants already approved or cancelled, rows this reader has never seen. Where
     * the vocabulary is closed the values come from the field's own definition instead, as core
     * does for its country filter.
     *
     * @param string $name Identity field name.
     * @return stdClass|null control and options, or null for a field that cannot be filtered.
     */
    protected static function shape(string $name): ?stdClass {
        global $CFG;

        if ($name === 'country') {
            return (object) [
                'control' => 'select',
                'options' => get_string_manager()->get_list_of_countries(true),
            ];
        }

        if (!preg_match(\core_user\fields::PROFILE_FIELD_REGEX, $name, $matches)) {
            return (object) ['control' => 'text', 'options' => []];
        }

        require_once($CFG->dirroot . '/user/profile/lib.php');
        $field = profile_get_custom_field_data_by_shortname($matches[1], false);
        if (!$field) {
            return null;
        }

        switch ($field->datatype) {
            case 'menu':
                /* Split exactly the way core does and do NOT trim: profile_field_menu builds its
                   options from a bare explode() of param1 and stores the chosen key verbatim, so
                   an option written with a leading or trailing space really is stored with it. A
                   trimmed vocabulary would offer a value the column can never hold. Blank lines
                   are dropped, because the empty string is this form's "no filter" value. */
                $values = array_filter(explode("\n", (string) $field->param1), static function ($v) {
                    return $v !== '';
                });

                return (object) ['control' => 'select', 'options' => array_combine($values, $values)];
            case 'checkbox':
                return (object) [
                    'control' => 'select',
                    'options' => ['1' => get_string('yes'), '0' => get_string('no')],
                ];
            case 'text':
                return (object) ['control' => 'text', 'options' => []];
            default:
                /* datetime and textarea are deliberately not offered. A datetime is a range
                   question this control cannot ask, and a textarea holds prose whose stored format
                   is not what the reader sees - matching it would answer about markup. */
                return null;
        }
    }

    /**
     * One filter value, as it may be used.
     *
     * @param stdClass $offered The field, from resolve().
     * @param string $raw What arrived.
     * @return string|null The value, or null when nothing is applied.
     */
    public static function clean(stdClass $offered, string $raw): ?string {
        if ($offered->control === 'select') {
            /* Compared UNTRIMMED, because the vocabulary itself may hold a leading or trailing
               space - see shape(). The select posts the option verbatim, so a trim here would
               refuse the site's own value. Not in the vocabulary is not a filter; refusing
               silently is what the status filter does. */
            return array_key_exists($raw, $offered->options) ? $raw : null;
        }

        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        return $value;
    }

    /**
     * The timestamps bounding a whole-day range, in the reader's own timezone.
     *
     * Not usergetmidnight() plus 86400: across a daylight-saving change a day is not 86400
     * seconds, so the upper bound would land an hour early or late and an application submitted in
     * that hour would be filed on the wrong day. Each boundary is computed from its own date, and
     * the upper bound is the midnight that starts the following day, compared with a strict
     * less-than, so the "to" date is inclusive.
     *
     * A malformed date is no filter rather than an error, which is what the status filter does with
     * a value outside its vocabulary: the queue narrows by what it understood.
     *
     * @param string|null $from Lower bound as YYYY-MM-DD, or null.
     * @param string|null $to Upper bound as YYYY-MM-DD, or null.
     * @return array [from timestamp or null, to timestamp or null].
     */
    public static function day_bounds(?string $from, ?string $to): array {
        return [self::midnight($from, 0), self::midnight($to, 1)];
    }

    /**
     * Midnight of a given day in the reader's timezone, optionally some days later.
     *
     * @param string|null $date YYYY-MM-DD, or null.
     * @param int $plusdays Days to add to the date before resolving midnight.
     * @return int|null The timestamp, or null when the date is absent or malformed.
     */
    protected static function midnight(?string $date, int $plusdays): ?int {
        if ($date === null || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return null;
        }

        [, $year, $month, $day] = array_map('intval', $m);
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        /* make_timestamp() normalises an overflowing day of month, so the day after the 31st is the
           1st of the next month without this having to know how long the month was. */
        return make_timestamp($year, $month, $day + $plusdays, 0, 0, 0, 99);
    }
}
