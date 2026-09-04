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
     * Optional by definition: a listing with no filters applied is the ordinary case, and the
     * required enrolid above is the only thing a request must say.
     *
     * `search` is a string_filter and `status` an integer_filter, and the two classes behave
     * differently in a way that decides code elsewhere. integer_filter::add_filter_value() tests
     * is_int() and throws a TypeError on anything else, so a value read from a DOM dataset - where
     * everything is a string - has to be cast first. string_filter::add_filter_value() is a
     * COMPLETE override that gates only on is_string() and never reaches the base class, whose
     * `''` rejection (lib/table/classes/local/filter/filter.php:218-221) therefore never runs for
     * it: an empty search string installs a live filter carrying nothing. applications::set_filterset()
     * treats that as no filter, because the alternative is a queue that empties itself the moment
     * somebody clears the box.
     *
     * **The identity-field filters are declared from the site's whole identity vocabulary, not from
     * this plugin's setting and not from what the reader may see.** Two separate reasons, and the
     * second was found by review rather than by design.
     *
     * Not per reader, because filterset::add_filter() throws InvalidArgumentException for a name
     * this method does not declare - a free first barrier against a forged request - and declaring
     * only what the reader may see would make that barrier the security boundary. It is the wrong
     * one to lean on: a filterset knows nothing about contexts. The per-reader refusal belongs to
     * applications::set_filterset(), which intersects the offered set with core's own identity
     * mapping and ignores a filter for a field this reader is not offered.
     *
     * And not from enrol_apply/queuefilterfields ALONE, because that refusal is OBSERVABLE BEFORE
     * ANY AUTHORISATION RUNS. core_table_get_dynamic_table_content is registered with no capability
     * of its own, and get.php calls add_filter_from_params() for every submitted name before it
     * constructs the table, before set_filterset(), and before validate_context() and
     * has_capability(). So with the tick-list as the declared set, any logged-in user with no
     * capability here at all could send `pf7` and read from which of the two exceptions came back
     * whether the administrator had ticked custom profile field 7 - a setting otherwise behind
     * moodle/site:config.
     *
     * The declared set is therefore the UNION of the site's published identity vocabulary and the
     * plugin's own list. Everything the site publishes is recognised whether ticked or not, so for
     * every field that could ever be offered to anybody the answer no longer depends on the
     * setting. **What remains, stated rather than papered over: for a field the site does NOT
     * publish, "recognised" still means "once ticked".** That is a fact about a setting entry with
     * no effect on anything - the queue cannot offer such a field to any reader - and closing it
     * completely would mean refusing names this table has always ignored, which is the behaviour
     * the union preserves: a filter whose field is withheld is dropped by set_filterset() rather
     * than throwing at a stale browser tab whose administrator changed the site under it.
     *
     * queuefilter::token() is safe to call here and queuefilter::choices() is NOT: token() reads
     * the field record, while choices() resolves labels through format_string(), which asks $PAGE
     * for a context this early in the request and does not get one.
     *
     * The dates are two filters and not one, because core's table filter classes express no range:
     * there is integer_filter, string_filter and their siblings, and nothing that carries a pair.
     *
     * **The submitted-profile snapshot is not here and cannot be.** It is masked per row, and no
     * filterable surface can honour a per-row mask - an operator would recover a withheld value by
     * filtering for it and reading the count. Only enrol_apply/queuefilterfields decides what is
     * offered, and it offers live identity fields.
     *
     * @return array Filter name => filter class.
     */
    public function get_optional_filters(): array {
        /* course and category are string filters rather than integer ones although their values
           are integers, and deliberately: the AMD module sends every control in the filter bar the
           same way, and a second shape there is a second thing to keep in step. The filterset's
           job is transport; \enrol_apply\table\applications is what decides whether a course has
           an apply method and whether a category exists. Declared unconditionally, for the reason
           the paragraph above gives about not making this the security boundary - the scope test
           lives in coursefilter::offered(), which the table applies. */
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
