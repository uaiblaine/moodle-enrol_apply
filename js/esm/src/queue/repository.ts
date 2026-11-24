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
 * The only module of the queue's view that talks to the server.
 *
 * Every call goes through core's ES module @moodle/lms/core/ajax and is ONE request of its own
 * (fetchOne), as the report's are: a rows read must never ride in a batch behind the preference write
 * it depends on. Core's ajax holds Behat's pending counter while each request is in flight; the view
 * holds it across the render that follows (datareport/pending.ts). None of these decides anything.
 *
 * @module     enrol_apply/queue/repository
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import type {MethodsAnswer, Preference, Query, RowsAnswer, SummaryAnswer} from './types';

/**
 * A page of the queue's rows.
 *
 * @param {number} enrolid The apply method.
 * @param {object} query What to read; see Query.
 * @returns {Promise} The answer.
 */
export const getRows = (enrolid: number, query: Query): Promise<RowsAnswer> =>
    fetchOne<RowsAnswer>({
        methodname: 'enrol_apply_queue_rows',
        args: {
            enrolid,
            scope: query.scope,
            search: query.search,
            filters: Object.entries(query.filters).map(([name, value]) => ({name, value})),
            page: query.page,
            perpage: query.perpage,
            sort: query.sort,
            direction: query.direction,
        },
    });

/**
 * A page of the site-wide queue's "By course" view: one row per enrolment method of what the list holds.
 *
 * @param {object} query What to read: the list's scope, search and filters, the view's sort and page.
 * @returns {Promise} The answer.
 */
export const getMethods = (query: Query): Promise<MethodsAnswer> =>
    fetchOne<MethodsAnswer>({
        methodname: 'enrol_apply_queue_methods',
        args: {
            scope: query.scope,
            search: query.search,
            filters: Object.entries(query.filters).map(([name, value]) => ({name, value})),
            page: query.page,
            perpage: query.perpage,
            sort: query.sort,
            direction: query.direction,
        },
    });

/**
 * The capacity tiles' figures: the method's places and applications held, the reader's queue.
 *
 * @param {number} enrolid The apply method.
 * @returns {Promise} The answer.
 */
export const getSummary = (enrolid: number): Promise<SummaryAnswer> =>
    fetchOne<SummaryAnswer>({methodname: 'enrol_apply_queue_summary', args: {enrolid}});

/**
 * Store the reader's grid: the columns they chose and the widths they set.
 *
 * @param {number} enrolid The apply method whose queue the reader is on.
 * @param {object} preference The grid; see Preference.
 * @returns {Promise} The grid as stored.
 */
export const setPreference = (enrolid: number, preference: Preference): Promise<Preference> =>
    fetchOne<Preference>({
        methodname: 'enrol_apply_set_queue_preference',
        args: {enrolid, usedefault: preference.usedefault, columns: preference.columns, widths: preference.widths},
    });
