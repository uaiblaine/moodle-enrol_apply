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

namespace enrol_apply\reportbuilder\local\systemreports;

use context;
use context_course;
use core\lang_string;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\system_report;
use core_reportbuilder\system_report_factory;
use enrol_apply\reportbuilder\local\entities\submission;
use enrol_apply\reportbuilder\local\formatters\submission as formatter;

/**
 * The applications made to one course.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_applications extends system_report {
    /** @var string Entity name of the second user entity, the one naming the decider. */
    protected const DECIDER = 'applydecider';

    /**
     * Unique identifier of the filter that narrows the report to one enrolment method.
     *
     * Core builds it from the entity name and filter name used in add_report_filters(); it is
     * repeated here because report.php needs to name it. If the two drift apart,
     * scope_to_method() silently returns false;
     * test_the_method_filter_identifier_is_the_one_the_page_scopes_by holds the coupling.
     *
     * @var string
     */
    public const METHOD_FILTER = 'submission:method';

    /**
     * Whether the current user may see this report at all.
     *
     * This method is the whole gate and must be self-sufficient: every sort, filter and page turn
     * goes through core_table_get_dynamic_table_content, which constructs the report (running
     * this check) before its own validate_context(), so nothing here may assume report.php ran or
     * that require_login() was called against this course.
     *
     * The context is the report persistent's own, resolved server-side from the report id; a
     * client swapping the id gets another report's context, which this method re-checks. That is
     * why scoping on the context is safe, unlike get_parameter(), whose values the client sends
     * in the filterset.
     *
     * @return bool True when the user may view the report.
     */
    protected function can_view(): bool {
        $context = $this->get_context();

        /* Reachable: system_report_factory::create() builds this report in any context it is
           handed, and outside a course the capability check below would test the wrong thing. */
        if (!$context instanceof context_course) {
            return false;
        }

        return has_capability('enrol/apply:viewreports', $context);
    }

    /**
     * Build the report.
     *
     * @return void
     */
    protected function initialise(): void {
        $entity = new submission();
        $alias = $entity->get_table_alias('enrol_apply_submission');

        $this->set_main_table('enrol_apply_submission', $alias);
        $this->add_entity($entity);

        /* The only scoping there is, built from the context. Not get_parameter(), which a client
           sets freely, and not the persistent's itemid, which is client-settable through
           core_reportbuilder_retrieve_system_report. The itemid (the enrol instance, see
           for_method()) only selects which stored filter choices come back. */
        $courseid = database::generate_param_name();
        $this->add_base_condition_sql("{$alias}.courseid = :{$courseid}", [
            $courseid => $this->get_context()->instanceid,
        ]);

        /* Pseudonymised records (what a deleted course leaves behind) carry userid 0 and
           describe nobody. The INNER join below is what excludes them, as no user has id 0;
           making it a LEFT join needs a "userid <> 0" base condition, as in the datasource.
           test_a_pseudonymised_record_is_not_listed holds this. */
        $applicant = new user();
        $applicantalias = $applicant->get_table_alias('user');
        $this->add_entity($applicant->add_join(
            "JOIN {user} {$applicantalias} ON {$applicantalias}.id = {$alias}.userid"
        ));

        /* A second user entity for the decider, renamed so it does not collide with the first
           (report\base::annotate_entity() throws 'Duplicate entity name'). LEFT JOIN, because an
           undecided record carries decidedby = 0. */
        $decider = (new user())
            ->set_entity_name(self::DECIDER)
            ->set_entity_title(new lang_string('submissiondecidedby', 'enrol_apply'));
        $decideralias = $decider->get_table_alias('user');
        $this->add_entity($decider->add_join(
            "LEFT JOIN {user} {$decideralias} ON {$decideralias}.id = {$alias}.decidedby"
        ));

        $this->add_base_fields("{$alias}.id, {$alias}.userid, {$alias}.userenrolmentid, {$alias}.status");

        $this->add_report_columns();
        $this->add_report_filters();

        $this->set_initial_sort_column('submission:timecreated', SORT_DESC);
        $this->set_default_per_page(30);
        $this->set_downloadable(true, get_string('report:course_applications', 'enrol_apply'));
    }

    /**
     * Add the report's columns, in display order.
     *
     * @return void
     */
    protected function add_report_columns(): void {
        $this->add_column_from_entity('user:fullnamewithlink');

        /* Identity fields from core's helper, which returns none outside
           moodle/site:viewuseridentity, so a reader without it gets no column rather than a
           blanked one: sorting and filtering are SQL and never reach a display callback, so only
           absence keeps a hidden value from being recovered. */
        $applicant = $this->get_entity('user');
        foreach ($applicant->get_identity_columns($this->get_context()) as $column) {
            $this->add_column($column);
        }

        $this->add_columns_from_entity('submission');

        /* The snapshot's masking is decided here, in the course context, and handed to the
           formatter as its argument; the entity has no context to decide it in. Without this
           line the formatter shows names only. */
        $this->get_column('submission:snapshot')->set_callback(
            [formatter::class, 'snapshot'],
            formatter::visible_keys($this->get_context())
        );

        $this->add_column_from_entity(self::DECIDER . ':fullname');
    }

    /**
     * This report, identified by the enrolment method it was opened from.
     *
     * scope_to_method() stores the reader's choice through set_filter_values(), which is keyed on
     * the report id and the user only. Sorting, paging and downloading read that store and carry
     * nothing that names a method, so each method needs its own report persistent (keyed here by
     * the enrol instance as itemid) or two methods in one course would share one stored scope.
     *
     * The itemid is identity, never scope: rows are limited by the base condition, built from the
     * context. A named constructor so that the PHPUnit suite can reach it
     * (test_two_methods_keep_independent_scopes); report.php is a page script no test runs.
     *
     * @param context $context Course context the report belongs to.
     * @param int $enrolid Enrol instance the reader opened it from.
     * @return \core_reportbuilder\system_report The report.
     */
    public static function for_method(context $context, int $enrolid): \core_reportbuilder\system_report {
        return system_report_factory::create(self::class, $context, 'enrol_apply', 'method', $enrolid);
    }

    /**
     * Narrow this reader's view to one enrolment method, leaving their other filters alone.
     *
     * A filter value, never a base condition, so it stays outside the security boundary: the
     * base condition limits rows to the course whatever a filter carries. A value that is not one
     * of the filter's options is ignored by select::get_sql_filter(), so a forged enrolid widens
     * the report to the whole course - a view the reader is already allowed - rather than
     * narrowing it.
     *
     * Merged with the stored values, as set_filter_values() replaces them all and the reader's
     * other filters are not this page's to discard. array_merge(), not +, which would let the
     * stored method win (test_the_url_method_wins_over_a_stored_one).
     *
     * @param int $enrolid Enrol instance to narrow to.
     * @return bool False when this course has no method filter to set, which is every course
     *              carrying a single live apply method - the filter is added only where the
     *              course offers a choice between methods.
     */
    public function scope_to_method(int $enrolid): bool {
        if (!array_key_exists(self::METHOD_FILTER, $this->get_filter_instances())) {
            return false;
        }

        return $this->set_filter_values(array_merge($this->get_filter_values(), [
            self::METHOD_FILTER . '_operator' => select::EQUAL_TO,
            self::METHOD_FILTER . '_value' => $enrolid,
        ]));
    }

    /**
     * Add the report's filters.
     *
     * @return void
     */
    protected function add_report_filters(): void {
        global $DB;

        $this->add_filter_from_entity('user:fullname');

        /* The identity filters must stay in step with the identity columns: a filter on a field
           the reader may not see would let them recover its value by narrowing and counting
           rows. get_identity_filters() answers the same capability question as
           get_identity_columns(). */
        $applicant = $this->get_entity('user');
        foreach ($applicant->get_identity_filters($this->get_context()) as $filter) {
            $this->add_filter($filter);
        }

        $this->add_filters_from_entity('submission');
        $this->add_filter_from_entity(self::DECIDER . ':fullname');

        /* The method filter is added only where the course offers a choice of methods. With one
           it would read as a control that does nothing, and selecting it would hide the records
           of deleted methods, which delete_instance() keeps. */
        $instances = $DB->get_records(
            'enrol',
            ['courseid' => $this->get_context()->instanceid, 'enrol' => 'apply'],
            'id ASC'
        );
        if (count($instances) > 1) {
            $plugin = enrol_get_plugin('apply');
            $options = [];
            foreach ($instances as $id => $instance) {
                // The whole record is needed, not a menu: the instance name is built from more.
                $options[$id] = $plugin->get_instance_name($instance);
            }

            $alias = $this->get_entity('submission')->get_table_alias('enrol_apply_submission');
            $this->add_filter((new filter(
                select::class,
                'method',
                new lang_string('submissionmethod', 'enrol_apply'),
                'submission',
                "{$alias}.enrolid"
            ))->set_options($options));
        }
    }
}
