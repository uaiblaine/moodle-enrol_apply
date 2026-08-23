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

namespace enrol_apply\reportbuilder\datasource;

use core\lang_string;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use enrol_apply\reportbuilder\local\entities\submission;
use enrol_apply\reportbuilder\local\formatters\submission as formatter;

/**
 * Enrolment applications across every course, as a custom report source.
 *
 * Nothing registers this class. It is discovered from its path and namespace alone -
 * manager::get_report_datasources() asks core_component for every class in the
 * `<component>\reportbuilder\datasource` namespace - so there is no db/reportbuilder.php and
 * nothing to keep in step. The plan said a version.php bump is what makes it appear; that is
 * not so. The classmap cache is keyed on CORE's version, not a plugin's
 * (core_component::is_cache_valid()), and what rebuilds it is purge_caches(). On a developer
 * site it is not consulted at all.
 *
 * How this differs from the course report, and why both exist: course_applications is a
 * system_report with a can_view() that re-runs on every request, scoped to one course. This is
 * a datasource, and a datasource has NO can_view() - core offers a plugin no hook to gate one.
 * Access to a custom report is governed by the Report Builder capabilities and by the report's
 * audience, neither of which this plugin controls. That difference is the whole reason for the
 * snapshot decision below.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class applications extends datasource {
    /** @var string Entity name of the second user entity, the one naming the decider. */
    protected const DECIDER = 'applydecider';

    /**
     * The name shown in the report source picker.
     *
     * @return string Localised name.
     */
    public static function get_name(): string {
        return get_string('datasource:applications', 'enrol_apply');
    }

    /**
     * Build the source.
     *
     * @return void
     */
    protected function initialise(): void {
        $entity = new submission();
        $alias = $entity->get_table_alias('enrol_apply_submission');

        $this->set_main_table('enrol_apply_submission', $alias);
        $this->add_entity($entity);

        /* Pseudonymised records - what a deleted course leaves behind - carry userid 0 and name
           nobody. The course report excludes them through its INNER join onto {user}; here that
           would not work, and the difference is worth spelling out because it looks like
           duplication. A custom report emits an entity's joins only for the columns, filters and
           conditions actually in use (custom_report_table merges them per active element), so a
           report built from submission columns alone joins {user} not at all and would list the
           row. A base condition is applied unconditionally, whatever the report selects.
           Note the applicant entity below is LEFT joined for the same reason: with an INNER join
           the row set would silently shrink the moment an author added a user column. */
        $param = database::generate_param_name();
        $this->add_base_condition_sql("{$alias}.userid <> :{$param}", [$param => 0]);

        $applicant = new user();
        $applicantalias = $applicant->get_table_alias('user');
        $this->add_entity($applicant->add_join(
            "LEFT JOIN {user} {$applicantalias} ON {$applicantalias}.id = {$alias}.userid"
        ));

        /* A second user entity for the decider, renamed so it does not collide with the first.
           Without the rename report\base::annotate_entity() throws
           coding_exception('Duplicate entity name') at construction. */
        $decider = (new user())
            ->set_entity_name(self::DECIDER)
            ->set_entity_title(new lang_string('submissiondecidedby', 'enrol_apply'));
        $decideralias = $decider->get_table_alias('user');
        $this->add_entity($decider->add_join(
            "LEFT JOIN {user} {$decideralias} ON {$decideralias}.id = {$alias}.decidedby"
        ));

        /* The course entity carries the course filter this surface needs, and the course name
           column without which a site-wide list of applications cannot be read. The course
           report deliberately does NOT add this entity: a course-scoped report offering a course
           filter would let a manager page sideways into a course they were never authorised for.
           The split is enforced by construction rather than by an exclude list. */
        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity->add_join(
            "LEFT JOIN {course} {$coursealias} ON {$coursealias}.id = {$alias}.courseid"
        ));

        /* One call per entity, each passed its NAME and never the object. add_all_from_entities()
           takes an array, and the two branches disagree about what they do with it: 5.1 matches
           the argument against the registered entity names, so an object matches nothing and is
           silently skipped. Naming them one at a time is core's own shape and cannot diverge. */
        $this->add_all_from_entity($entity->get_entity_name());
        $this->add_all_from_entity($applicant->get_entity_name());
        $this->add_all_from_entity($decider->get_entity_name());
        $this->add_all_from_entity($courseentity->get_entity_name());

        $this->restrict_snapshot_column();
    }

    /**
     * Withhold the frozen profile snapshot from a reader who may not see everyone's details.
     *
     * The one decision in this source that is not mechanical, so the reasoning is recorded here
     * rather than in a commit message.
     *
     * The course report can gate this on the reader's capability IN THE COURSE, because it has a
     * course context and re-checks it on every request. Here there is neither. A custom report's
     * context is always the system context, a datasource has no can_view(), and
     * moodle/reportbuilder:view carries the `user` archetype - so the surface is reachable by any
     * authenticated account a single manager adds to a report's audience, downloadable as CSV,
     * and mailable on a schedule that renders once with the CREATOR's permissions.
     *
     * So the column is removed rather than blanked, and gated on moodle/user:viewalldetails at
     * the system context. Three reasons, each measured:
     *
     * Absence is core's own move for exactly this problem. local\helpers\user_profile_fields
     * masks custom profile field columns AND filters with
     * set_is_available($field->is_visible(system::instance())), and profile_field_base::is_visible()
     * resolves the private and hidden cases on moodle/user:viewalldetails. This snapshot can hold
     * the value of any such field and would otherwise walk straight past that gate.
     *
     * The capability fits the question. moodle/user:viewalldetails is RISK_PERSONAL and manager
     * only - the same shape as enrol/apply:viewreports. moodle/site:viewuseridentity, which the
     * course report uses, is declared CONTEXT_MODULE with teacher, editingteacher and manager
     * archetypes: a course-shaped question that would answer wrongly at site scale in both
     * directions.
     *
     * Whole-column is the only sound granularity. Per-field visibility cannot be reconstructed
     * from a snapshot whose custom field has since been deleted - fields::label() returns the
     * bare key and there is no field object left to ask.
     *
     * State the limit honestly rather than implying a per-reader guarantee: on a custom report
     * the effective control is the audience plus this one capability, and a scheduled report
     * delivers the creator's answer to every recipient.
     *
     * @return void
     */
    protected function restrict_snapshot_column(): void {
        $column = $this->get_column('submission:snapshot');
        if ($column === null) {
            return;
        }

        if (!has_capability('moodle/user:viewalldetails', $this->get_context())) {
            $column->set_is_available(false);
            return;
        }

        /* Past the gate, show the whole thing. The entity registers this callback bare, which
           core turns into a null argument and the formatter reads as "nobody asked a context" -
           the name parts alone, which user:fullname already shows. A column that can only ever
           repeat another column is worse than either shipping it or removing it. */
        $column->set_callback([formatter::class, 'snapshot'], formatter::ALL_FIELDS);
    }

    /**
     * The columns a newly created report starts with.
     *
     * submission:snapshot is deliberately absent and must stay absent. helpers\report's
     * add_report_column() validates against get_columns(), which filters out unavailable
     * columns, so a reader without moodle/user:viewalldetails creating a report from this source
     * would get invalid_parameter_exception rather than a report.
     *
     * @return array Column identifiers.
     */
    public function get_default_columns(): array {
        return [
            'user:fullname',
            'course:fullname',
            'submission:status',
            'submission:timecreated',
        ];
    }

    /**
     * The initial sort order of a newly created report.
     *
     * @return array Column identifier mapped to sort direction.
     */
    public function get_default_column_sorting(): array {
        return [
            'submission:timecreated' => SORT_DESC,
        ];
    }

    /**
     * The filters a newly created report starts with.
     *
     * @return array Filter identifiers.
     */
    public function get_default_filters(): array {
        return [
            'course:courseselector',
            'submission:status',
            'submission:timecreated',
        ];
    }

    /**
     * The conditions a newly created report starts with.
     *
     * @return array Condition identifiers.
     */
    public function get_default_conditions(): array {
        return [
            'submission:status',
        ];
    }
}
