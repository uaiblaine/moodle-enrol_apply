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
use core_user\fields;

/**
 * Which identifying details of an applicant this operator may see, and in which scope.
 *
 * Everything here delegates to `\core_user\fields`, so the queue shows a reader the identity
 * details core's own participants page would show them (`showuseridentity`, `hiddenuserfields`
 * and the identity capabilities); a hand-written list is how the two screens drift apart.
 *
 * The scope decides whether identity is offered at all. `manage.php` serves three queues:
 *
 * - `?id=<enrolid>` has one course context, and that is the right context to ask about.
 * - no parameter, for a site-wide capability holder, asks about the system context - right for
 *   an operator holding the capability there, and failing closed for anybody who does not.
 * - the mentee queue spans courses in one statement. No single context is right for it, and a
 *   per-row mask is unsound for a column that can be sorted: a reader could recover a value they
 *   may not see by sorting on it. So that scope is offered no identity columns at all.
 *
 * The resolution is per scope, not per row, and the queue's filters inherit it. On the site-wide
 * queue every row is judged in the system context, so a course-level override narrowing identity
 * is not consulted: a check in the system context never looks below it. The field filters use the
 * same mapping, so they offer nothing more, but they make that gap answerable with one query rather
 * than by paging. A per-row mask is used only for the submitted-application snapshot, which can be
 * neither sorted nor filtered.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class identity {
    /**
     * The identity fields this operator may see in this scope.
     *
     * A null context means the mentee scope, which gets none - see the class docblock.
     *
     * Core already does the rest, so nothing here repeats it: get_identity_fields() returns an
     * empty array without moodle/site:viewuseridentity, which makes this safe to call for any
     * reader; drops a custom profile field that no longer exists or that the reader may not see;
     * and drops any standard field named in $CFG->hiddenuserfields unless the reader holds the
     * capability to see hidden fields.
     *
     * @param context|null $context Context to judge in, null for a scope that spans courses.
     * @return array Field names, in the order the site configured them.
     */
    public static function fields(?context $context): array {
        if ($context === null) {
            return [];
        }

        return fields::get_identity_fields($context);
    }

    /**
     * One applicant's identity values, resolved exactly as the queue resolves them.
     *
     * Not `$user->{$field}`: for a custom profile field core's list carries the key
     * `profile_field_<shortname>`, which a `{user}` record does not have, so the value would
     * silently vanish here while the queue, going through get_sql(), prints it. The values
     * therefore come from the queue's own SQL over one user - one extra query on a page that
     * renders a single application, and the two surfaces agree by construction.
     *
     * @param context|null $context Context to judge in, null for a scope that spans courses.
     * @param int $userid The applicant.
     * @return array Ordered field name => value, with the fields this reader may not see and the
     *         ones holding nothing both absent.
     */
    public static function values(?context $context, int $userid): array {
        global $DB;

        $fields = self::fields($context);
        if (!$fields || $userid <= 0) {
            return [];
        }

        $sql = self::sql($context, 'u');
        $record = $DB->get_record_sql(
            "SELECT u.id{$sql->selects} FROM {user} u {$sql->joins} WHERE u.id = :identityuserid",
            $sql->params + ['identityuserid' => $userid]
        );

        if (!$record) {
            /* The custom-field join core emits is an INNER one, so a field whose {user_info_field}
               row has gone takes the whole row with it. get_identity_fields() drops such a field
               before it can reach here - it checks the field still exists - but this query is one
               row rather than a listing, so failing to nothing is the honest fallback. */
            return [];
        }

        $values = [];
        foreach ($fields as $field) {
            $value = trim((string) ($record->{$field} ?? ''));
            if ($value !== '') {
                $values[$field] = $value;
            }
        }

        return $values;
    }

    /**
     * The SELECT, joins and parameters for those fields, ready to append to a table's SQL.
     *
     * `$namedparams` must be true: this plugin's queries bind by name, and fields::get_sql()
     * otherwise emits `?` placeholders, which makes fix_sql_params() throw `mixedtypesqlparam`.
     * The placeholders exist only for custom profile fields, so a test whose showuseridentity
     * names standard fields alone never reaches the throw.
     *
     * @param context|null $context Context to judge in, null for a scope that spans courses.
     * @param string $alias Table alias of {user} in the caller's query.
     * @return \stdClass Object with selects, joins, params and mappings; all empty when there are
     *         no fields to add.
     */
    public static function sql(?context $context, string $alias = 'u'): \stdClass {
        if (!self::fields($context)) {
            return (object) ['selects' => '', 'joins' => '', 'params' => [], 'mappings' => []];
        }

        return fields::for_identity($context)->get_sql($alias, true, '', '', true);
    }
}
