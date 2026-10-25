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

namespace enrol_apply\reportbuilder\local\entities;

use core\lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use enrol_apply\local\submission as submissionhelper;
use enrol_apply\reportbuilder\local\formatters\submission as formatter;

/**
 * The durable application record, as a Report Builder entity.
 *
 * initialise() is overridden to register every column and filter itself, which is the one
 * shape both supported branches accept: on Moodle 5.1 base::initialise() is abstract, while 5.2
 * makes it concrete and drives it from get_available_columns(), get_available_filters() and
 * get_available_conditions(), which do not exist on 5.1. An entity written in the 5.2 shape is
 * a fatal error on 5.1.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission extends base {
    /**
     * The database tables this entity uses.
     *
     * @return array Table names.
     */
    protected function get_default_tables(): array {
        return ['enrol_apply_submission', 'user_enrolments', 'groups'];
    }

    /**
     * The join onto the live enrolment the record was created for.
     *
     * LEFT, because the record outlives its user enrolment: cancellation and unenrolment delete
     * the enrolment while the record is kept, and an INNER join would drop exactly the rows the
     * outcome column exists to explain.
     *
     * Joined on userenrolmentid rather than on courseid + userid, which is not unique: an
     * applicant who was cancelled and applied again has two records for one course, and the
     * pair would attach the current enrolment to the earlier record too. A sequence never
     * recycles an id, so a stale one can only fail to match, which is the state the column
     * reports.
     *
     * @return string The join.
     */
    protected function enrolment_join(): string {
        $alias = $this->get_table_alias('enrol_apply_submission');
        $uealias = $this->get_table_alias('user_enrolments');

        return "LEFT JOIN {user_enrolments} {$uealias} ON {$uealias}.id = {$alias}.userenrolmentid";
    }

    /**
     * The join onto the group whose enrolment key the applicant presented.
     *
     * LEFT, because most records carry no key, and a key group can be deleted after the fact
     * while the record keeps its id.
     *
     * @return string The join.
     */
    protected function key_group_join(): string {
        $alias = $this->get_table_alias('enrol_apply_submission');
        $galias = $this->get_table_alias('groups');

        return "LEFT JOIN {groups} {$galias} ON {$galias}.id = {$alias}.keygroupid";
    }

    /**
     * The entity's title, as shown when picking columns.
     *
     * @return lang_string The title.
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity:submission', 'enrol_apply');
    }

    /**
     * Register every column, filter and condition this entity offers.
     *
     * Each filter is registered as a condition as well, so a custom report can fix a question
     * ("every pending application on the site") rather than merely offer a control. Core's own
     * course entity registers the same object as both. A system report never reads conditions,
     * so this is inert for the course report.
     *
     * @return base This entity.
     */
    public function initialise(): base {
        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }
        foreach ($this->get_all_filters() as $filter) {
            $this->add_filter($filter);
            $this->add_condition($filter);
        }

        return $this;
    }

    /**
     * The columns this entity offers.
     *
     * @return column[] The columns.
     */
    protected function get_all_columns(): array {
        $alias = $this->get_table_alias('enrol_apply_submission');
        $columns = [];

        /* The plugin's own status vocabulary, never core's enrolment status: core's labels
           (core_user\output\status_field: 0 active, 1 suspended, 2 not current) would mislabel
           every submission status, and core_course's enrolment formatter is deprecated from
           Moodle 5.2. */
        $columns[] = (new column(
            'status',
            new lang_string('submissionstatus', 'enrol_apply'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$alias}.status")
            ->set_is_sortable(true)
            ->add_callback([formatter::class, 'status']);

        $columns[] = (new column(
            'timecreated',
            new lang_string('submissiontimecreated', 'enrol_apply'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$alias}.timecreated")
            ->set_is_sortable(true)
            ->add_callback([\core_reportbuilder\local\helpers\format::class, 'userdate']);

        $columns[] = (new column(
            'timedecided',
            new lang_string('submissiontimedecided', 'enrol_apply'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$alias}.timedecided")
            ->set_is_sortable(true)
            // An undecided record carries 0, which userdate() would render as 1970.
            ->add_callback([formatter::class, 'timeornever']);

        $columns[] = (new column(
            'comment',
            new lang_string('applycomment', 'enrol_apply'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("{$alias}.comment", 'submissioncomment')
            ->set_is_sortable(false)
            // Same literal newlines and the same CSS as the snapshot; see the formatter.
            ->add_attributes(['class' => 'enrol_apply-linebreaks'])
            ->add_callback([formatter::class, 'plaintext']);

        /* The decider's note, rendered exactly like the applicant's comment beside it.

           No capability of its own: the site-wide datasource shares this entity and has no
           per-reader gate (see its class docblock), so the note gets the same exposure as the
           applicant's comment, which has the stronger claim to protection of the two. Only the
           snapshot, different in kind, is gated separately by each report. */
        $columns[] = (new column(
            'decisionnote',
            new lang_string('decisionnote', 'enrol_apply'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("{$alias}.decisionnote", 'submissiondecisionnote')
            ->set_is_sortable(false)
            ->add_attributes(['class' => 'enrol_apply-linebreaks'])
            ->add_callback([formatter::class, 'plaintext']);

        $uealias = $this->get_table_alias('user_enrolments');

        /* What the enrolment is doing now, which the stored status cannot say: the record holds
           the last decision this plugin took, while the participants page, course reset, user
           deletion and the expiry sweep change the enrolment and leave the record alone.

           It exists beside the outcome column because it is sortable: the outcome is computed in
           a display callback, which SQL sorting and filtering never reach. */
        $columns[] = (new column(
            'enrolment',
            new lang_string('submissionenrolment', 'enrol_apply'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->enrolment_join())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$uealias}.status", 'liveenrolstatus')
            ->add_field("{$alias}.userenrolmentid", 'liveueid')
            ->set_is_sortable(true)
            ->add_callback([formatter::class, 'enrolment']);

        /* "What happened to this application", derived from the stored decision and the live
           enrolment with no new storage; see formatter::outcome() and
           docs/design/audit-trail-analysis.md.

           Not sortable and no filter: the value is computed in a callback, so a sort would order
           by the first field and a filter would never reach it. The status and enrolment columns
           are the sortable, filterable primitives. */
        $columns[] = (new column(
            'outcome',
            new lang_string('submissionoutcome', 'enrol_apply'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->enrolment_join())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.status", 'outcomerecordstatus')
            ->add_field("{$uealias}.status", 'outcomeenrolstatus')
            ->add_field("{$uealias}.timeend", 'outcomeenroltimeend')
            ->add_field("{$alias}.userenrolmentid", 'outcomeueid')
            ->set_is_sortable(false)
            ->add_callback([formatter::class, 'outcome']);

        /* How the application was admitted, from the record: through review, or by a group key,
           with what a valid key that could not admit ran into. Sortable and filterable, because
           it is a stored column and a plain vocabulary, unlike the outcome above. */
        $columns[] = (new column(
            'admissionroute',
            new lang_string('admissionroute', 'enrol_apply'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$alias}.keyoutcome")
            ->set_is_sortable(true)
            ->add_callback([formatter::class, 'admission_route']);

        $galias = $this->get_table_alias('groups');
        $columns[] = (new column(
            'keygroup',
            new lang_string('keygroup', 'enrol_apply'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->key_group_join())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$galias}.name", 'keygroupname')
            ->add_field("{$alias}.keygroupid", 'keygroupid')
            ->add_field("{$alias}.keyoutcome", 'keygroupoutcome')
            ->add_field("{$galias}.courseid", 'keygroupcourseid')
            ->set_is_sortable(true, ["{$galias}.name"])
            ->add_callback([formatter::class, 'key_group']);

        /* The frozen snapshot, as one long-text column that is neither sortable nor filterable.
           Built from the record's rows in SQL (submission::aggregate_sql()), because a display
           callback sees one row and could only read them with a query per row.
           That is what makes a display callback a sound place to mask values here: there is no
           SQL path by which a reader could recover a hidden one by filtering and counting rows.
           test_the_snapshot_column_has_no_filter_and_is_not_sortable holds that precondition; if
           it fails, the masking is unsound.
           The callback is registered with no argument, which formatter::snapshot() answers with
           the name parts alone - the restrictive default any report reusing this entity
           inherits until it calls set_callback() with a context-based decision.
           test_an_entity_column_used_without_the_report_shows_names_only pins it. */
        $columns[] = (new column(
            'snapshot',
            new lang_string('submissionsnapshot', 'enrol_apply'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field(submissionhelper::aggregate_sql("{$alias}.id"), 'snapshot')
            ->set_is_sortable(false)
            /* The pairs are separated by a literal newline, which this class makes CSS draw;
               markup would corrupt the download (see formatter::snapshot()). */
            ->add_attributes(['class' => 'enrol_apply-linebreaks'])
            ->add_callback([formatter::class, 'snapshot']);

        return $columns;
    }

    /**
     * SQL giving the frozen value one record holds for one question, or null when it has none.
     *
     * A correlated scalar subquery rather than a join: a join per question would need the key in
     * the join text, which Report Builder joins cannot carry as a parameter, and a scalar
     * subquery cannot multiply the row. It is a scalar because enrol_apply_answer is unique on
     * (submissionid, fieldkey).
     *
     * @param string $key Field key, bound as a parameter.
     * @return array The SQL and its parameters.
     */
    protected function answer_sql(string $key): array {
        $alias = $this->get_table_alias('enrol_apply_submission');
        $param = database::generate_param_name();

        return [
            "(SELECT eaans.value
                FROM {" . submissionhelper::ANSWER_TABLE . "} eaans
               WHERE eaans.submissionid = {$alias}.id AND eaans.fieldkey = :{$param})",
            [$param => $key],
        ];
    }

    /**
     * A column showing one question's frozen answer, sortable, named after the field key.
     *
     * Not registered by this entity: which questions exist depends on the report (one course's
     * instances, or the site's shared fields), and so does who may see them. The caller decides
     * both and adds what it builds.
     *
     * The header is the question's CURRENT name, while each cell holds the value frozen with the
     * record. A column spans every record and has one header, and the name the reader manages
     * today is how they find the column; the question each record was actually asked stays in its
     * snapshot, beside its own frozen label.
     *
     * @param string $key Field key: s_column, c_profilefieldid or a_customfieldid.
     * @param string $title The question's current name, plain spelling.
     * @return column The column.
     */
    public function answer_column(string $key, string $title): column {
        [$sql, $params] = $this->answer_sql($key);

        return (new column(
            $key,
            new lang_string('customfieldcolumn', 'core_reportbuilder', $title),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field($sql, 'answer', $params)
            ->set_is_sortable(true)
            ->add_attributes(['class' => 'enrol_apply-linebreaks'])
            ->add_callback([formatter::class, 'plaintext']);
    }

    /**
     * A text filter on one question's frozen answer, for the column answer_column() builds.
     *
     * A new object per call, with its own parameter, so the same question can be both a filter
     * and a condition of one report.
     *
     * @param string $key Field key.
     * @param string $title The question's current name, plain spelling.
     * @return filter The filter.
     */
    public function answer_filter(string $key, string $title): filter {
        [$sql, $params] = $this->answer_sql($key);

        return (new filter(
            text::class,
            $key,
            new lang_string('customfieldcolumn', 'core_reportbuilder', $title),
            $this->get_entity_name(),
            $sql,
            $params
        ))
            ->add_joins($this->get_joins());
    }

    /**
     * The filters this entity offers.
     *
     * The snapshot deliberately has none; see get_all_columns().
     *
     * @return filter[] The filters.
     */
    protected function get_all_filters(): array {
        $alias = $this->get_table_alias('enrol_apply_submission');
        $filters = [];

        /* Four options, not a boolean: this is enrol_apply_submission.status (the
           submission::STATUS_* values), not user_enrolments.status. A boolean_select compiles to
           "= 1" or "= 0" and would make waiting and cancelled records unfindable. */
        $filters[] = (new filter(
            select::class,
            'status',
            new lang_string('submissionstatus', 'enrol_apply'),
            $this->get_entity_name(),
            "{$alias}.status"
        ))
            ->add_joins($this->get_joins())
            ->set_options([
                submissionhelper::STATUS_PENDING => new lang_string('submissionstatuspending', 'enrol_apply'),
                submissionhelper::STATUS_APPROVED => new lang_string('submissionstatusapproved', 'enrol_apply'),
                submissionhelper::STATUS_WAITING => new lang_string('submissionstatuswaiting', 'enrol_apply'),
                submissionhelper::STATUS_CANCELLED => new lang_string('submissionstatuscancelled', 'enrol_apply'),
            ]);

        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('submissiontimecreated', 'enrol_apply'),
            $this->get_entity_name(),
            "{$alias}.timecreated"
        ))
            ->add_joins($this->get_joins())
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_RANGE,
                date::DATE_PREVIOUS,
                date::DATE_CURRENT,
            ]);

        /* DATE_EMPTY is offered here and nowhere else: an undecided record carries
           timedecided = 0, so "never decided" is a question a manager actually asks. */
        $filters[] = (new filter(
            date::class,
            'timedecided',
            new lang_string('submissiontimedecided', 'enrol_apply'),
            $this->get_entity_name(),
            "{$alias}.timedecided"
        ))
            ->add_joins($this->get_joins())
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_EMPTY,
                date::DATE_NOT_EMPTY,
                date::DATE_RANGE,
            ]);

        $filters[] = (new filter(
            text::class,
            'comment',
            new lang_string('applycomment', 'enrol_apply'),
            $this->get_entity_name(),
            "{$alias}.comment"
        ))
            ->add_joins($this->get_joins());

        /* A text filter, not a select, because the note is free text: the reasons it records
           (waiting for a place, waiting for something to be validated) are too thin a
           distinction to freeze into a coded column. */
        $filters[] = (new filter(
            text::class,
            'decisionnote',
            new lang_string('decisionnote', 'enrol_apply'),
            $this->get_entity_name(),
            "{$alias}.decisionnote"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            select::class,
            'admissionroute',
            new lang_string('admissionroute', 'enrol_apply'),
            $this->get_entity_name(),
            "{$alias}.keyoutcome"
        ))
            ->add_joins($this->get_joins())
            ->set_options([
                submissionhelper::KEY_NONE => new lang_string('admissionroutereview', 'enrol_apply'),
                submissionhelper::KEY_ADMITTED => new lang_string('keyoutcomeadmitted', 'enrol_apply'),
                submissionhelper::KEY_NO_PLACE => new lang_string('keyoutcomenoplace', 'enrol_apply'),
                submissionhelper::KEY_GROUP_FULL => new lang_string('keyoutcomegroupfull', 'enrol_apply'),
                submissionhelper::KEY_SEAT_UNCONFIRMED => new lang_string('keyoutcomeseatunconfirmed', 'enrol_apply'),
            ]);

        return $filters;
    }
}
