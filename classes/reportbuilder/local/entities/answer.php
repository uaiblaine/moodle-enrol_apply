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
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use enrol_apply\reportbuilder\local\formatters\submission as formatter;

/**
 * One frozen value an application submitted, as a Report Builder entity.
 *
 * One row per value: the field key, the question as the applicant read it, and the answer. Joined
 * by the datasource onto the application record, so a report using it lists each record once per
 * value it holds - which is what lets a cross-course report filter on an answer to a question that
 * exists in one instance only.
 *
 * Nothing here decides who may see these values; the datasource does, for the whole entity at once
 * (see \enrol_apply\reportbuilder\datasource\applications::restrict_submitted_data()).
 *
 * initialise() registers everything itself, as the submission entity does, because that is the one
 * shape Moodle 5.1 and 5.2 both accept.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answer extends base {
    /**
     * The database tables this entity uses.
     *
     * @return array Table names.
     */
    protected function get_default_tables(): array {
        return ['enrol_apply_answer'];
    }

    /**
     * The entity's title, as shown when picking columns.
     *
     * @return lang_string The title.
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity:answer', 'enrol_apply');
    }

    /**
     * Register every column, filter and condition this entity offers.
     *
     * @return base This entity.
     */
    public function initialise(): base {
        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }
        foreach ($this->get_all_filters() as $filter) {
            $this->add_filter($filter);
        }
        foreach ($this->get_all_filters() as $condition) {
            $this->add_condition($condition);
        }

        return $this;
    }

    /**
     * The columns this entity offers.
     *
     * The question and the answer are the frozen plain spellings, escaped for the raw cell by the
     * same formatter the record's free text uses.
     *
     * @return column[] The columns.
     */
    protected function get_all_columns(): array {
        $alias = $this->get_table_alias('enrol_apply_answer');
        $columns = [];

        $columns[] = (new column(
            'fieldkey',
            new lang_string('answerfieldkey', 'enrol_apply'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.fieldkey")
            ->set_is_sortable(true)
            ->add_callback([formatter::class, 'plaintext']);

        $columns[] = (new column(
            'question',
            new lang_string('answerquestion', 'enrol_apply'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.label", 'question')
            ->set_is_sortable(true)
            ->add_callback([formatter::class, 'plaintext']);

        $columns[] = (new column(
            'answer',
            new lang_string('answervalue', 'enrol_apply'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.value", 'answer')
            ->set_is_sortable(true)
            ->add_attributes(['class' => 'enrol_apply-linebreaks'])
            ->add_callback([formatter::class, 'plaintext']);

        return $columns;
    }

    /**
     * The filters this entity offers, built afresh on each call.
     *
     * Called twice by initialise(), so a filter and a condition on the same field are separate
     * objects, as the submission entity's per-question filters are.
     *
     * @return filter[] The filters.
     */
    protected function get_all_filters(): array {
        $alias = $this->get_table_alias('enrol_apply_answer');
        $filters = [];

        $filters[] = (new filter(
            text::class,
            'fieldkey',
            new lang_string('answerfieldkey', 'enrol_apply'),
            $this->get_entity_name(),
            "{$alias}.fieldkey"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'question',
            new lang_string('answerquestion', 'enrol_apply'),
            $this->get_entity_name(),
            "{$alias}.label"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            text::class,
            'answer',
            new lang_string('answervalue', 'enrol_apply'),
            $this->get_entity_name(),
            "{$alias}.value"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
