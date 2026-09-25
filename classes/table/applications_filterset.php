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

namespace enrol_apply\table;

use core_table\local\filter\filterset;
use core_table\local\filter\integer_filter;
use core_table\local\filter\string_filter;
use enrol_apply\local\queuefilter;

/**
 * What the applications table may be filtered by.
 *
 * Found by core, not registered: flexible_table::get_filterset_class() returns
 * `static::class . '_filterset'`, so this class must sit beside applications and be named for it.
 * Renaming either half breaks only the web service path;
 * test_the_filterset_class_is_the_one_core_derives catches it.
 *
 * One required filter: the enrol instance id is all the client is trusted to say, and
 * queue::listing_scope() recomputes everything else from it. An omitted id must be an error
 * rather than zero, because zero is the widest scope (every application this operator may
 * decide on). Core's service never calls check_validity(), so applications::set_filterset()
 * calls it and also refuses an empty value.
 *
 * Filter names must be strictly alphanumeric: the service declares them PARAM_ALPHANUM, so
 * `enrol_id` would be refused before this class is consulted.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class applications_filterset extends filterset {
    /**
     * The filters that must be present.
     *
     * @return array Filter name => filter class.
     */
    public function get_required_filters(): array {
        return [
            'enrolid' => integer_filter::class,
        ];
    }

    /**
     * The filters that may be present.
     *
     * `status` is an integer_filter, whose add_filter_value() throws a TypeError on anything but
     * an int, so a value read from a DOM dataset must be cast first. `search` is a string_filter,
     * which accepts '' where the base filter class ignores it; applications::set_filterset()
     * treats an empty value as no search.
     *
     * The identity-field filters are declared from the site's whole identity vocabulary plus the
     * plugin's own list (enrol_apply/queuefilterfields), not from what the reader may see and not
     * from the plugin's list alone:
     * - Not per reader, because a filterset knows nothing about contexts; the per-reader refusal
     *   is applications::set_filterset()'s.
     * - Not the plugin's list alone, because core refuses an undeclared name in
     *   add_filter_from_params() before any authorisation runs, and the service has no capability
     *   of its own. Any logged-in user could then probe which fields the administrator ticked, a
     *   setting behind moodle/site:config. With the union, every field the site publishes is
     *   recognised whether ticked or not; for a field it does not publish, "recognised" still
     *   means "ticked", which reveals a setting that has no effect on anything.
     *
     * queuefilter::token() is safe to call here and queuefilter::choices() is not: choices()
     * resolves labels through format_string(), which needs a $PAGE context that does not exist
     * yet on the web service path.
     *
     * The dates are two filters because core's table filters express no range. The snapshot is
     * not filterable: it is masked per row, and a filter would let an operator recover a withheld
     * value by counting results.
     *
     * @return array Filter name => filter class.
     */
    public function get_optional_filters(): array {
        /* The course and category are string filters although their values are integers, because
           the AMD module sends every filter-bar control the same way. Declared unconditionally:
           the table validates them and applies the scope test, coursefilter::offered(). */
        $filters = [
            'search' => string_filter::class,
            'status' => integer_filter::class,
            'appliedfrom' => string_filter::class,
            'appliedto' => string_filter::class,
            'category' => string_filter::class,
            'course' => string_filter::class,
        ];

        $names = array_unique(array_merge(\core_user\fields::get_identity_fields(null), queuefilter::pool()));
        foreach ($names as $name) {
            $token = queuefilter::token($name);
            if ($token !== '') {
                $filters[$token] = string_filter::class;
            }
        }

        return $filters;
    }
}
