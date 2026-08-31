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
 * The queue used to print every applicant's e-mail address unconditionally, consulting neither
 * `showuseridentity` nor `hiddenuserfields`. On a site naming only `username` in the first and
 * listing `email` in the second, core's own participants page shows no e-mail column at all while
 * the queue beside it printed the address - so the plugin disclosed more than the core page it
 * sits next to, to the same reader, about the same people.
 *
 * Everything here delegates to `\core_user\fields`, which is the point: the answer has to be the
 * one core would give, and a hand-written list is how the two screens drift apart again.
 *
 * **The scope decides whether identity is offered at all**, and the third scope is the reason this
 * class exists rather than a bare call at the call site. `manage.php` serves three queues:
 *
 * - `?id=<enrolid>` has one course context, and that is the right context to ask about.
 * - no parameter, for a site-wide capability holder, asks about the system context - which is
 *   exactly right for an operator holding the capability there, and which fails CLOSED for
 *   anybody who does not.
 * - the MENTEE queue spans courses in one statement. No single context is right for it, and a
 *   per-row mask is unsound for a column that can be sorted: a reader could recover a value they
 *   may not see by sorting on it. So that scope is offered no identity columns at all.
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
     * Note what core already does here, so that nothing defends against it twice:
     * get_identity_fields() drops a custom profile field that no longer exists ("it may have been
     * deleted since user identity was configured"), and drops any standard field named in
     * $CFG->hiddenuserfields unless the reader holds the capability to see hidden fields. It also
     * returns an empty array outright without moodle/site:viewuseridentity. That last one is why
     * this is safe to call for any reader.
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
     * The SELECT, joins and parameters for those fields, ready to append to a table's SQL.
     *
     * `$namedparams` is TRUE and is not a style choice: this plugin's queries bind by name, and
     * fields::get_sql() emits `?` placeholders otherwise, which makes fix_sql_params() throw
     * `mixedtypesqlparam`. Note the shape of that failure before writing a test for it - the
     * placeholders exist only for CUSTOM profile fields, so a fixture whose showuseridentity names
     * standard fields alone never reaches the throw and would pass against the bug.
     *
     * The prefix keeps the aliases clear of the columns the queue already selects; the queue
     * aliases the user id to `userid`, and an identity field called `id` would otherwise collide.
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
