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
 * The only module of the report's view that talks to the server.
 *
 * Every call goes through core's ES module @moodle/lms/core/ajax (a component cannot import an AMD
 * module), and every call is ONE request of its own (fetchOne), never a batch: core keeps one
 * instance of a report per user for the whole request, so a rows call batched with a summary of
 * another measure, or after a filter write, would read the first call's report
 * (classes/local/reportengine.php says why). Core's ajax holds Behat's pending counter while each
 * request is in flight; the view holds it across the render that follows (pending.ts).
 *
 * @module     enrol_apply/report/repository
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import type {Measure, Preference, RowsAnswer, RowsQuery, Scope, SummaryAnswer} from './types';

/**
 * A page of the report's rows.
 *
 * @param {number} enrolid The apply method.
 * @param {object} query What to read; see RowsQuery.
 * @param {boolean} facets Whether to count the filter panel's options: only when a count can have moved.
 * @returns {Promise} The answer.
 */
export const getRows = (enrolid: number, query: RowsQuery, facets: boolean): Promise<RowsAnswer> =>
    fetchOne<RowsAnswer>({methodname: 'enrol_apply_report_rows', args: {enrolid, ...query, facets}});

/**
 * The turnaround summary of the same rows.
 *
 * @param {number} enrolid The apply method.
 * @param {string} measure What the wait measures.
 * @param {string} scope Which rows.
 * @param {string} search Part of the applicant's name, or ''.
 * @returns {Promise} The answer.
 */
export const getSummary = (enrolid: number, measure: Measure, scope: Scope, search: string): Promise<SummaryAnswer> =>
    fetchOne<SummaryAnswer>({methodname: 'enrol_apply_report_summary', args: {enrolid, measure, scope, search}});

/**
 * Store the reader's grid: the columns they chose and the widths they set.
 *
 * @param {number} enrolid The apply method whose report the reader is on.
 * @param {object} preference The grid; see Preference.
 * @returns {Promise} The grid as stored.
 */
export const setPreference = (enrolid: number, preference: Preference): Promise<Preference> =>
    fetchOne<Preference>({
        methodname: 'enrol_apply_set_report_preference',
        args: {enrolid, usedefault: preference.usedefault, columns: preference.columns, widths: preference.widths},
    });

/**
 * Store the reader's filter values in core's own store for the report.
 *
 * @param {number} reportid The report persistent.
 * @param {string} measure The report's parameter, as core rebuilds the report from it.
 * @param {object} values Every filter value of the report, as core's filter form posts them.
 * @returns {Promise} Whether core stored them.
 */
export const setFilters = (reportid: number, measure: Measure, values: Record<string, unknown>): Promise<boolean> =>
    fetchOne<boolean>({
        methodname: 'core_reportbuilder_set_filters',
        args: {reportid, parameters: JSON.stringify({measure}), values: JSON.stringify(values)},
    });

/**
 * Forget every filter value the reader stored for the report.
 *
 * @param {number} reportid The report persistent.
 * @param {string} measure The report's parameter.
 * @returns {Promise} Whether core reset them.
 */
export const resetFilters = (reportid: number, measure: Measure): Promise<boolean> =>
    fetchOne<boolean>({
        methodname: 'core_reportbuilder_filters_reset',
        args: {reportid, parameters: JSON.stringify({measure})},
    });
