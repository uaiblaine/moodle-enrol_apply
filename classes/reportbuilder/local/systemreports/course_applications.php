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

use context_course;
use core\lang_string;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\system_report;
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
     * Whether the current user may see this report at all.
     *
     * This method is the whole gate, and it has to be self-sufficient rather than one layer of
     * several. Every sort, every filter and every page turn re-runs it: the browser fetches
     * pages through core_table_get_dynamic_table_content, whose execute() calls
     * set_filterset() FIRST, and that constructs the report - so require_can_view() has already
     * run by the time the service reaches its own validate_context() and has_capability() calls
     * two lines later (lib/table/classes/external/dynamic/get.php, byte-identical on 5.1 and
     * 5.2). Nothing here may assume report.php ran, or that require_login() was called against
     * this course, because on that path neither did.
     *
     * The context is the report persistent's own, resolved server-side from the report id. A
     * client swapping that id gets some other report's context, which this method then re-checks
     * - which is why scoping on the context is safe where scoping on get_parameter() would not
     * be. That one is demonstrably forgeable: the parameters arrive as PARAM_RAW in the
     * filterset and are json_decoded straight into the report.
     *
     * @return bool True when the user may view the report.
     */
    protected function can_view(): bool {
        $context = $this->get_context();

        /* Not defensive: system_report_factory::create() will happily build this report in any
           context it is handed, and the persistent row is keyed on one. A report of one
           course's applications has no meaning outside a course, and the capability check below
           would be evaluated against the wrong thing. */
        if (!$context instanceof context_course) {
            return false;
        }

        return has_capability('enrol/apply:viewreports', $context);
    }

    /**
     * Build the report.
     *
     * The order below is fixed by core and is not stylistic: add_entity() must precede any
     * add_column() naming that entity - report\base::add_column() throws "Invalid entity name"
     * otherwise - and set_main_table() must happen before validate() runs at the end of
     * construction. None of that is visible to phpcs, phpdoc or the mustache lint.
     *
     * @return void
     */
    protected function initialise(): void {
        $entity = new submission();
        $alias = $entity->get_table_alias('enrol_apply_submission');

        $this->set_main_table('enrol_apply_submission', $alias);
        $this->add_entity($entity);

        /* The only scoping there is. Not get_parameter(), which a client sets freely, and not
           the persistent's itemid, which is client-settable through the mobile web services. */
        $courseid = database::generate_param_name();
        $this->add_base_condition_sql("{$alias}.courseid = :{$courseid}", [
            $courseid => $this->get_context()->instanceid,
        ]);

        /* Pseudonymised records - what a deleted course leaves behind - carry userid 0 and no
           longer describe anybody. They are not this report's subject and would render as a
           row with no applicant at all.
           What excludes them is the INNER join immediately below, because no user holds id 0.
           There WAS an explicit "userid <> 0" base condition here as well, and it was removed
           rather than kept: deleting it left the whole suite green, so it read as a protection
           no test could hold. Anything that makes that join a LEFT one - showing rows for
           deleted users, say - has to bring the explicit condition back with it.
           test_a_pseudonymised_record_is_not_listed is what goes red if it does not. */
        $applicant = new user();
        $applicantalias = $applicant->get_table_alias('user');
        $this->add_entity($applicant->add_join(
            "JOIN {user} {$applicantalias} ON {$applicantalias}.id = {$alias}.userid"
        ));

        /* A second user entity for the decider, with its own name and its own alias. Without
           the rename both would answer to "user" and report\base::annotate_entity() would throw
           coding_exception('Duplicate entity name') at construction - loudly, on every request,
           not silently. LEFT JOIN, because an undecided record carries decidedby = 0. */
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

        /* Identity fields come from core's own helper, and so does the decision about which of
           them this reader may see: get_identity_fields() returns an empty array outside
           moodle/site:viewuseridentity (user/classes/fields.php, identical on 5.1 and 5.2), so
           a reader without it gets no column rather than a blank one.
           That distinction is the whole point. A display callback would leave the column, the
           filter and the sort in place, and filtering and sorting are SQL - they never reach a
           callback - so a reader could recover a hidden value by filtering on it and reading
           the row count, or simply by sorting on it. Absence is the only masking that holds. */
        $applicant = $this->get_entity('user');
        foreach ($applicant->get_identity_columns($this->get_context()) as $column) {
            $this->add_column($column);
        }

        $this->add_columns_from_entity('submission');

        /* The snapshot's masking decision is made HERE, where the course context is in hand,
           and handed to the formatter as an argument. The entity cannot make it: an entity has
           no context, so deciding it there could only ask about the system context, and a
           reader legitimately granted the capability in their course would see nothing. The
           formatter's own default is the restrictive one, so an entity used without this line
           shows less rather than more.
           visible_keys() returns formatter::ALL_FIELDS and not null for the unrestricted
           reader, and that is not a stylistic choice: null is precisely what core passes when
           nobody registered an argument, so null has to keep meaning "nobody asked". */
        $this->get_column('submission:snapshot')->set_callback(
            [formatter::class, 'snapshot'],
            formatter::visible_keys($this->get_context())
        );

        $this->add_column_from_entity(self::DECIDER . ':fullname');
    }

    /**
     * Add the report's filters.
     *
     * @return void
     */
    protected function add_report_filters(): void {
        global $DB;

        $this->add_filter_from_entity('user:fullname');

        /* The filters for the identity columns, from the same core helper and in the same
           order as the columns themselves. Both sides of the pair have to move together: a
           column a reader may see but not filter is merely awkward, while a filter for a
           column they may NOT see is the oracle this report is shaped to avoid - filtering is
           SQL, so a reader would recover the hidden value by narrowing on it and reading the
           row count. get_identity_filters() answers the same capability question
           get_identity_columns() does, which is what keeps the two in step. */
        $applicant = $this->get_entity('user');
        foreach ($applicant->get_identity_filters($this->get_context()) as $filter) {
            $this->add_filter($filter);
        }

        $this->add_filters_from_entity('submission');
        $this->add_filter_from_entity(self::DECIDER . ':fullname');

        /* The method filter earns its place only where there is a choice to make. A course with
           one apply instance would get a filter with a single option, which cannot narrow
           anything and reads as a control that does not work. */
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
