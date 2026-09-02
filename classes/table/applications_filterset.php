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

/**
 * What the applications table may be filtered by.
 *
 * Found by core, not registered: flexible_table::get_filterset_class() returns
 * `static::class . '_filterset'` (lib/table/classes/flexible_table.php:2021), so this class has to
 * sit beside applications and be named for it. Renaming either half breaks the web service path
 * with "The filter specified (...) is invalid" and leaves the page path working, which is the
 * asymmetry test_the_filterset_class_is_the_one_core_derives exists to catch.
 *
 * **One required filter, and the requirement is not decorative.** The enrol instance id is the
 * whole of what the client is trusted to say; everything the listing is narrowed by is recomputed
 * from it server-side by queue::listing_scope(). An omitted id must therefore be a hard error
 * rather than a silent zero, because zero is itself a meaningful scope - every application this
 * operator may decide on - and a request that forgot to say which one it meant would silently get
 * the widest one.
 *
 * Nothing in core enforces that for us, and check_validity() does not go far enough on its own:
 * get.php never calls it, and it tests only that a filter of the name is PRESENT, not that it
 * carries a value. applications::set_filterset() therefore calls it AND reads the value; see the
 * note there for the empty-value request that slips through otherwise.
 *
 * Filter NAMES must be strictly alphanumeric. The service declares the name as PARAM_ALPHANUM
 * (lib/table/classes/external/dynamic/get.php:79), so `enrol_id` or `enrol-id` would be refused
 * by validate_parameters() with invalid_parameter_exception before this class is ever consulted.
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
     * None yet. The status, search and identity-field filters of the rebuilt queue arrive in
     * their own slices, and each is optional by definition: a listing with no filters applied is
     * the ordinary case.
     *
     * @return array Filter name => filter class.
     */
    public function get_optional_filters(): array {
        return [];
    }
}
