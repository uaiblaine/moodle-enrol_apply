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

/**
 * The report's filters as core stores them, read and rewritten for the panel and the chips.
 *
 * Core keeps every filter value of a report for a user in one store, keyed "<filter>_<field>", and
 * core_reportbuilder_set_filters REPLACES that whole set. So a change to one filter is written as
 * every stored value, with that filter's own keys changed, and a removal as every other filter's
 * keys. The panel draws only the filters that pick one option (core's select filter and this
 * plugin's group filter); the others (dates, text, durations) are set in core's table view and
 * appear here as chips that remove them.
 *
 * @module     enrol_apply/report/filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Filter} from './types';

/** Core_reportbuilder\local\filters\select::ANY_VALUE and EQUAL_TO. */
const ANY_VALUE = 0;
const EQUAL_TO = 1;

/** The filter classes whose values are one option of a list: the panel draws these. */
const CHOOSABLE = ['select', 'group'];

/**
 * Whether the panel can draw a filter: one option of a list it was handed.
 *
 * @param {object} filter The filter.
 * @returns {boolean} True for a select-like filter with options.
 */
export const choosable = (filter: Filter): boolean => CHOOSABLE.includes(filter.type) && filter.options.length > 0;

/**
 * A filter's own stored values.
 *
 * @param {object} filter The filter.
 * @returns {object} Its keys and values, as stored.
 */
export const ownValues = (filter: Filter): Record<string, unknown> => {
    try {
        const parsed = JSON.parse(filter.values || '{}');
        return parsed !== null && typeof parsed === 'object' ? parsed as Record<string, unknown> : {};
    } catch {
        return {};
    }
};

/**
 * Every stored value of every filter, as one set.
 *
 * @param {Array} filters The report's filters.
 * @returns {object} The set core stores.
 */
export const allValues = (filters: Filter[]): Record<string, unknown> =>
    filters.reduce((all, filter) => ({...all, ...ownValues(filter)}), {} as Record<string, unknown>);

/**
 * The option a choosable filter is set to, or '' for none.
 *
 * @param {object} filter The filter.
 * @returns {string} The chosen option's value, or ''.
 */
export const chosen = (filter: Filter): string => {
    if (!filter.applied) {
        return '';
    }
    const value = ownValues(filter)[`${filter.name}_value`];

    return value === undefined || value === null ? '' : String(value);
};

/**
 * The whole set with one choosable filter set to an option, or released with ''.
 *
 * @param {Array} filters The report's filters.
 * @param {object} filter The filter being set.
 * @param {string} value The option, or '' for any.
 * @returns {object} The set to store.
 */
export const withChoice = (filters: Filter[], filter: Filter, value: string): Record<string, unknown> => {
    const values = without(filters, filter);
    values[`${filter.name}_operator`] = value === '' ? ANY_VALUE : EQUAL_TO;
    values[`${filter.name}_value`] = value;

    return values;
};

/**
 * The whole set without one filter's values.
 *
 * @param {Array} filters The report's filters.
 * @param {object} filter The filter to remove.
 * @returns {object} The set to store.
 */
export const without = (filters: Filter[], filter: Filter): Record<string, unknown> => {
    const values = allValues(filters);
    for (const key of Object.keys(values)) {
        if (key.startsWith(`${filter.name}_`)) {
            delete values[key];
        }
    }

    return values;
};

/**
 * What a filter in force reads as on its chip: its option's label where it has one.
 *
 * @param {object} filter The filter, applied.
 * @returns {string} The option's label, or '' when the filter is not one of a list.
 */
export const chosenLabel = (filter: Filter): string => {
    if (!choosable(filter)) {
        return '';
    }
    const value = chosen(filter);

    return filter.options.find((option) => option.value === value)?.label ?? '';
};
