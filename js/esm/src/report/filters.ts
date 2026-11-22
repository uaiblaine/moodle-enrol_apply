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
 * keys. The panel draws the filters the rows answer names a facet for: the ones that pick one option
 * of a list (core's select filter and this plugin's group filter), the wait as "waited more than" a
 * few days (core's duration filter: at least that many days) and the decider's name (core's text
 * filter: is equal to one decider's name). Each writes its filter class's own keys. The others
 * (dates, free text, the other duration) are set in core's table view and appear here as chips that
 * remove them.
 *
 * @module     enrol_apply/report/filters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Filter} from './types';

/** Every filter class's ANY_VALUE (select's, text's, duration's DURATION_ANY): the filter narrows nothing. */
const ANY_VALUE = 0;

/** Core_reportbuilder\local\filters\select::EQUAL_TO. */
const EQUAL_TO = 1;

/** Core_reportbuilder\local\filters\duration::DURATION_MINIMUM: at least the value. */
const DURATION_MINIMUM = 2;

/** Core_reportbuilder\local\filters\text::IS_EQUAL_TO. */
const IS_EQUAL_TO = 3;

/** DAYSECS: the duration filter's unit for the "waited more than" facet, whose options are days. */
const DAY = 86400;

/**
 * Whether the panel can draw a filter: a facet with options to offer.
 *
 * @param {object} filter The filter.
 * @returns {boolean} True for a facet with options.
 */
export const choosable = (filter: Filter): boolean => filter.facet !== '' && filter.options.length > 0;

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
    const own = ownValues(filter);
    const value = own[`${filter.name}_value`];
    if (value === undefined || value === null) {
        return '';
    }
    const operator = Number(own[`${filter.name}_operator`] ?? ANY_VALUE);
    if (filter.facet === 'waited') {
        // A number of days, whatever unit core's table view stored it in.
        const days = Number(value) * Number(own[`${filter.name}_unit`] ?? 0) / DAY;
        return operator === DURATION_MINIMUM && Number.isFinite(days) ? String(days) : '';
    }
    if (filter.facet === 'decider') {
        return operator === IS_EQUAL_TO ? String(value) : '';
    }

    return String(value);
};

/**
 * The whole set with one facet's filter set to an option, or released with '', in its class's own keys.
 *
 * @param {Array} filters The report's filters.
 * @param {object} filter The filter being set.
 * @param {string} value The option, or '' for any.
 * @returns {object} The set to store.
 */
export const withChoice = (filters: Filter[], filter: Filter, value: string): Record<string, unknown> => {
    const values = without(filters, filter);
    if (filter.facet === 'waited') {
        values[`${filter.name}_operator`] = value === '' ? ANY_VALUE : DURATION_MINIMUM;
        values[`${filter.name}_value`] = value === '' ? 0 : Number(value);
        values[`${filter.name}_unit`] = DAY;
    } else if (filter.facet === 'decider') {
        values[`${filter.name}_operator`] = value === '' ? ANY_VALUE : IS_EQUAL_TO;
        values[`${filter.name}_value`] = value;
    } else {
        values[`${filter.name}_operator`] = value === '' ? ANY_VALUE : EQUAL_TO;
        values[`${filter.name}_value`] = value;
    }

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
    const value = chosen(filter);
    if (filter.facet === '' || value === '') {
        return '';
    }

    // A decider's option is their name: the chip names them before the panel has counted anyone.
    return filter.options.find((option) => option.value === value)?.label ?? (filter.facet === 'decider' ? value : '');
};
