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
 * Discovered by namespace (manager::get_report_datasources()), so nothing registers it.
 *
 * Unlike {@see \enrol_apply\reportbuilder\local\systemreports\course_applications}, a system
 * report scoped to one course with a can_view() re-run on every request, a datasource has no
 * per-reader access check: a custom report is governed by the Report Builder capabilities and
 * the report's audience, neither of which this plugin controls. That is why the snapshot column
 * is gated separately, in restrict_snapshot_column().
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

        /* Pseudonymised records (what a deleted course leaves behind) carry userid 0 and name
           nobody. The course report excludes them through an INNER join onto {user}, but a custom
           report joins an entity only for the columns, filters and conditions in use, so a report
           built from submission columns alone would list them; a base condition always applies.
           The applicant entity is LEFT joined for the same reason: the row set must not depend on
           whether the author added a user column. */
        $param = database::generate_param_name();
        $this->add_base_condition_sql("{$alias}.userid <> :{$param}", [$param => 0]);

        $applicant = new user();
        $applicantalias = $applicant->get_table_alias('user');
        $this->add_entity($applicant->add_join(
            "LEFT JOIN {user} {$applicantalias} ON {$applicantalias}.id = {$alias}.userid"
        ));

        /* A second user entity for the decider, renamed so it does not collide with the first
           (report\base::annotate_entity() throws 'Duplicate entity name'). */
        $decider = (new user())
            ->set_entity_name(self::DECIDER)
            ->set_entity_title(new lang_string('submissiondecidedby', 'enrol_apply'));
        $decideralias = $decider->get_table_alias('user');
        $this->add_entity($decider->add_join(
            "LEFT JOIN {user} {$decideralias} ON {$decideralias}.id = {$alias}.decidedby"
        ));

        /* The course entity supplies the course name and the course filter a site-wide list
           needs. The course report does not add it: every row there belongs to one course. */
        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity->add_join(
            "LEFT JOIN {course} {$coursealias} ON {$coursealias}.id = {$alias}.courseid"
        ));

        /* One call per entity, passing its name, never the object: on Moodle 5.1
           add_all_from_entity() accepts only a name, and add_all_from_entities() matches its
           array against entity names, so an entity object there is silently skipped. */
        $this->add_all_from_entity($entity->get_entity_name());
        $this->add_all_from_entity($applicant->get_entity_name());
        $this->add_all_from_entity($decider->get_entity_name());
        $this->add_all_from_entity($courseentity->get_entity_name());

        $this->restrict_snapshot_column();
    }

    /**
     * Withhold the frozen profile snapshot from a reader who may not see everyone's details.
     *
     * The course report gates this on a capability in the course, re-checked on every request.
     * A custom report has neither: it lives in the system context, a datasource has no
     * can_view(), and moodle/reportbuilder:view carries the `user` archetype, so any account in a
     * report's audience can read it, download it, or receive it on a schedule (which by default
     * renders with the schedule creator's permissions).
     *
     * So the column is removed rather than blanked, gated on moodle/user:viewalldetails in the
     * report's context. This is core's own approach: local\helpers\user_profile_fields masks
     * profile field columns and filters with set_is_available($field->is_visible(...)), and
     * profile_field_base::is_visible() resolves private and hidden fields on
     * moodle/user:viewalldetails - values this snapshot can hold. moodle/site:viewuseridentity,
     * which the course report uses, is a course-level capability (teacher archetypes) and would
     * answer wrongly at site scale.
     *
     * The whole column goes because per-field visibility cannot be reconstructed for a snapshot
     * field whose custom profile field has since been deleted. The effective control is therefore
     * the report audience plus this one capability, not a per-reader guarantee.
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

        /* Past the gate, show every field. The entity registers the callback with no argument,
           which the formatter answers with the name parts alone - a repeat of user:fullname. */
        $column->set_callback([formatter::class, 'snapshot'], formatter::ALL_FIELDS);
    }

    /**
     * The columns a newly created report starts with.
     *
     * submission:snapshot must stay absent: helpers\report::add_report_column() rejects an
     * unavailable column, so a reader without moodle/user:viewalldetails creating a report from
     * this source would get invalid_parameter_exception rather than a report.
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
