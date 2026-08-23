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
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use enrol_apply\local\submission as submissionhelper;
use enrol_apply\reportbuilder\local\formatters\submission as formatter;

/**
 * The durable application record, as a Report Builder entity.
 *
 * initialise() is overridden and does the work of registering every column and filter itself.
 * That is not a style choice and it is not optional: on 5.1 base::initialise() is
 * `abstract public function initialise(): self` (reportbuilder/classes/local/entities/base.php),
 * while 5.2 makes it concrete and drives it from new get_available_columns(),
 * get_available_filters() and get_available_conditions() hooks. Those three hooks DO NOT EXIST
 * on 5.1, so an entity written in the 5.2 shape is a compile-time fatal there - not a catchable
 * exception, the whole request dies at autoload. Note which runner would miss it: `mdl ci` with
 * no flags defaults to MOODLE_501_STABLE, so it is the leg that CATCHES this. What is blind to
 * it is the m502-only local loop this repo works in day to day - `mdl phpunit m502`,
 * `mdl behat m502`. Overriding initialise() is the one shape both branches accept.
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
        return ['enrol_apply_submission'];
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
     * Each filter is registered as a CONDITION as well, which is what lets a site-level custom
     * report fix a question rather than merely offer a control - "every pending application on
     * the site" is a condition, not a filter. Core's own course entity registers the same object
     * as both, so sharing one instance is the supported shape.
     *
     * This is inert for the course report, and that was measured rather than assumed:
     * reportbuilder/classes/system_report.php contains no reference to conditions at all on
     * either 5.1 or 5.2, so a system report never reads them. Its base conditions are a separate
     * mechanism entirely.
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

        /* Its own status column, never core's enrolment:status. The two look interchangeable
           and are not: core derives its value in SQL and maps it through
           status_field::STATUS_NOT_CURRENT, so this table's vocabulary comes out wrong for
           EVERY one of its four values - 1 would read "Suspended" and 2 "Not current". Borrowing
           core's formatter is worse still: core_course\reportbuilder\local\formatters\enrolment
           is deprecated on 5.2 (MDL-87000) and emits a notice per call that 5.1 does not. Do
           not lean on CI to catch that half - the notice arrives through debugging() at
           DEBUG_DEVELOPER, which PHPUnit re-emits at teardown as E_USER_NOTICE, and Moodle's
           phpunit.xml.dist sets failOnDeprecation and failOnWarning but not failOnNotice. The
           wrong vocabulary above is the reason not to borrow it; the deprecation is a second
           reason that would probably go unnoticed. */
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

        /* The frozen snapshot, as ONE long-text column: not sortable, and with no filter
           declared anywhere in this entity. That combination is what makes it the single place
           in this plugin where a display callback may legitimately hide values from a reader.
           Everywhere else, filtering and sorting are SQL and go straight past a callback, so a
           reader recovers a hidden value by filtering on it and reading the row count. Here
           there is no SQL path in, so there is nothing to go past.
           test_the_snapshot_column_has_no_filter_and_is_not_sortable holds that precondition;
           if it ever goes red, the masking below is unsound and must move.
           The callback is registered here with NO argument, which core turns into a null third
           argument, which the formatter reads as "nobody asked a context" and answers with the
           name parts alone. That is the masking any datasource reusing this entity inherits -
           slice 8's included - and it is meant to be the restrictive one: the report overrides
           it through set_callback() once it has a context to judge the reader in.
           test_an_entity_column_used_without_the_report_shows_names_only pins it. */
        $columns[] = (new column(
            'snapshot',
            new lang_string('submissionsnapshot', 'enrol_apply'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("{$alias}.userinfodata")
            ->set_is_sortable(false)
            /* The pairs are separated by a literal newline, which HTML collapses to a space.
               The line breaks are CSS because they cannot be markup: see the formatter, where
               the download path's decode-then-strip order is set out. */
            ->add_attributes(['class' => 'enrol_apply-linebreaks'])
            ->add_callback([formatter::class, 'snapshot']);

        return $columns;
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

        /* Four options and not a boolean. Note the vocabulary: this column is
           enrol_apply_submission.status, whose values are submission::STATUS_PENDING,
           _APPROVED, _WAITING and _CANCELLED - NOT user_enrolments.status, which this repo's
           CLAUDE.md warns shares the number 2 with it and nothing else. boolean_select compiles
           to "= 1" or "= 0", so it would not merely lose the waiting list: PENDING and APPROVED
           would be the only records any filter setting could reach, and WAITING and CANCELLED
           would both become unfindable. */
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

        return $filters;
    }
}
