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

namespace enrol_apply\reportbuilder\local\filters;

use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\database;
use enrol_apply\local\groupscope;

/**
 * The course report's group filter: the records of one group, by key group or by a group joined.
 *
 * A select over the groups the report offers the reader, matching a record whose keygroupid is the
 * chosen group or whose joinedgroups names it, with decidedgroups read where joinedgroups is not
 * known: the shape of the separate-groups narrowing, {@see groupscope::record_where()}, for one
 * group, so the filter and the narrowing cannot disagree on which records belong to a group. Core's
 * core_group entity has no group-id filter, and a plain select compiles to "field = value", which
 * a comma-separated list cannot answer.
 *
 * The filter's field SQL is the id of the application record, e.g. "{$alias}.id", as core's badge
 * criteria filter takes the badge id: the predicate is an EXISTS over the record.
 *
 * A value is applied only when it is one of the options, which the report builds for the current
 * reader (their own groups when narrowed), so a forged id of another group filters nothing; and the
 * filter is an AND beside the report's base condition, never instead of it.
 *
 * Only "is any value" and "is equal to": "is not equal to" over a list a record may or may not
 * carry adds a negation the narrowing has no counterpart for.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group extends select {
    /**
     * The comparison operators offered.
     *
     * @return array Operator => label.
     */
    protected function get_operators(): array {
        return [
            self::ANY_VALUE => get_string('filterisanyvalue', 'core_reportbuilder'),
            self::EQUAL_TO => get_string('filterisequalto', 'core_reportbuilder'),
        ];
    }

    /**
     * The options, as the report's callback built them for the current reader.
     *
     * Read from the filter object every time, never through the parent's method, which keeps them
     * in a static keyed by the filter's name: one request reading two courses' reports, or two
     * PHPUnit tests, would be offered the first one's groups.
     *
     * @return array Group id => name.
     */
    protected function get_select_options(): array {
        return (array) $this->filter->get_options();
    }

    /**
     * The SQL the chosen group adds.
     *
     * @param array $values The filter's form values.
     * @return array Two elements: the where fragment and its named parameters; empty for no filter.
     */
    public function get_sql_filter(array $values): array {
        $operator = (int) ($values["{$this->name}_operator"] ?? self::ANY_VALUE);
        $value = (string) ($values["{$this->name}_value"] ?? '');

        if ($operator !== self::EQUAL_TO || !ctype_digit($value) || !array_key_exists((int) $value, $this->get_select_options())) {
            return ['', []];
        }

        $alias = database::generate_alias();
        [$where, $params] = groupscope::record_where($alias, [(int) $value], database::generate_param_name());

        return [
            "EXISTS (
                SELECT 1
                  FROM {enrol_apply_submission} {$alias}
                 WHERE {$alias}.id = {$this->filter->get_field_sql()}
                   AND {$where}
            )",
            $params + $this->filter->get_field_params(),
        ];
    }
}
