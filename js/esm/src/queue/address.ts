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
 * The queue's filters live in the page's address (CQ-D6), not in a preference.
 *
 * The names are the ones manage.php reads for its table: `status` (the enrolment status, which is the
 * scope), `search`, `group`, the identity fields' tokens, `appliedfrom` and `appliedto`. What the
 * table has no control for keeps names of its own: `named`, `waited`, `appliedbefore`, `sort`, `dir`,
 * `page` (from 0) and `perpage`. A value at its default is left out. The server reads the address for
 * the first paint (enrol_apply\output\queue_view::initial()), so a link the table page or the course
 * report builds lands filtered; this module writes it back, and the browser's history carries the
 * query for the back and forward buttons.
 *
 * @module     enrol_apply/queue/address
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Query, ViewProps} from './types';

/** The view parameter that asks manage.php for this view (queue_view::GRID). */
export const GRID = 'grid';

/** The key the query is kept under in the history entry's state. */
const STATE_KEY = 'enrolApplyQueue';

/**
 * The query's parameters in the address, in the table's vocabulary.
 *
 * @param {object} props The view's props: the statuses and the default page size.
 * @param {object} query The query.
 * @param {boolean} grid Whether the address is this view's (the sort and the page) or the table's (filters only).
 * @returns {Array} Name and value pairs, in a stable order.
 */
const params = (props: ViewProps, query: Query, grid: boolean): [string, string][] => {
    const pairs: [string, string][] = [];
    if (query.scope !== 'all') {
        pairs.push(['status', String(query.scope === 'pending' ? props.statuses.pending : props.statuses.deferred)]);
    }
    if (query.search !== '') {
        pairs.push(['search', query.search]);
    }
    Object.keys(query.filters).sort().forEach((name) => {
        if (query.filters[name] !== '') {
            pairs.push([name, query.filters[name]]);
        }
    });
    if (grid) {
        if (query.sort !== '' && (query.sort !== 'wait' || query.direction !== 'asc')) {
            pairs.push(['sort', query.sort], ['dir', query.direction]);
        }
        if (query.page > 0) {
            pairs.push(['page', String(query.page)]);
        }
        if (query.perpage !== props.perpage) {
            pairs.push(['perpage', String(query.perpage)]);
        }
        pairs.push(['view', GRID]);
    }

    return pairs;
};

/**
 * An address of the queue for a query.
 *
 * @param {object} props The view's props.
 * @param {object} query The query.
 * @param {boolean} grid Whether the address is this view's, or the table's.
 * @returns {string} The address.
 */
export const queueUrl = (props: ViewProps, query: Query, grid: boolean): string => {
    const url = new URL(props.pageurl, window.location.href);
    params(props, query, grid).forEach(([name, value]) => url.searchParams.set(name, value));

    return url.toString();
};

/**
 * The query the props say the address asked for.
 *
 * @param {object} props The view's props.
 * @returns {object} The query.
 */
export const initialQuery = (props: ViewProps): Query => ({
    scope: props.initial.scope,
    search: props.initial.search,
    filters: Object.fromEntries(props.initial.filters.map((filter) => [filter.name, filter.value])),
    sort: props.initial.sort,
    direction: props.initial.direction,
    page: props.initial.page,
    perpage: props.initial.perpage,
});

/**
 * Write the query into the address and the history entry, and the table link's address with it.
 *
 * A step the reader may want to step back from (a scope, a filter, a page) is a new entry; the
 * search as it is typed, and the first paint, replace the current one.
 *
 * @param {object} props The view's props.
 * @param {object} query The query.
 * @param {boolean} push Whether this is a new history entry.
 * @returns {void}
 */
export const writeAddress = (props: ViewProps, query: Query, push: boolean): void => {
    const url = queueUrl(props, query, true);
    const state = {[STATE_KEY]: query};
    try {
        if (push && url !== window.location.href) {
            window.history.pushState(state, '', url);
        } else {
            window.history.replaceState(state, '', url);
        }
    } catch {
        // A browser that refuses the history keeps the view working; only the address stays behind.
    }
    // The shell's own link to the table, outside the component, follows the filters in force.
    document.querySelectorAll<HTMLAnchorElement>('[data-enrol-apply-queue="tablelink"]').forEach((link) => {
        link.href = queueUrl(props, query, false);
    });
};

/**
 * The query a history entry holds, if this view wrote it.
 *
 * @param {unknown} state The entry's state.
 * @returns {object|null} The query, or null for an entry this view did not write.
 */
export const queryOf = (state: unknown): Query | null => {
    if (state === null || typeof state !== 'object' || !(STATE_KEY in state)) {
        return null;
    }

    return (state as Record<string, Query>)[STATE_KEY];
};
