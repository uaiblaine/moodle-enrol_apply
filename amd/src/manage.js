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
 * Fills the gaps core leaves in the applications queue's bulk bar and filter bar.
 *
 * The bulk action's initial state: core/checkbox-toggleall's init() only binds click handlers, so
 * nothing disables the action until the first click. Core callers hardcode the disabled attribute
 * in their markup instead; this queue must stay operable without JavaScript, so the action is
 * disabled from here. That relies on the styles.css rule that puts the sticky footer back on
 * screen when no script ran; the two go together.
 *
 * Table refreshes: refreshTableContent() replaces the whole table region on a page turn, a sort or
 * a filter change, while the bulk bar (in the sticky footer) and the filter bar's chips, clear-all
 * control and count line all live outside that region. The refreshed html comes from
 * core_table\external\dynamic\get, which returns no JavaScript, so everything outside the region
 * is reset or redrawn here from the tableContentRefreshed event. The selection count is worded
 * "on this page" because a refresh discards the selection.
 *
 * The filter bar narrows the queue as the operator types. The GET form underneath still works
 * with scripting off, and pressing Enter still submits it, which gives the operator a permalink.
 *
 * @module     enrol_apply/manage
 * @copyright  2026 Anderson Blaine
 * @copyright  2016 sudile GbR (http://www.sudile.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {init as initDynamicTable, setFilters, getFilters} from 'core_table/dynamic';
import CheckboxToggleAll from 'core/checkbox-toggleall';
import DynamicTableEvents from 'core_table/local/dynamic/events';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {subscribe} from 'core/pubsub';
import {getString} from 'core/str';

const SELECTORS = {
    FORM: '#enrol_apply_manage_form',
    ACTION: '[data-toggle="action"][data-togglegroup="{group}"]',
    COUNT: '[data-region="selectedcount"]',
    TABLE: '[data-region="core_table/dynamic"]',
    FILTERS: '[data-region="queuefilters"]',
    SEARCH: '[data-region="searchinput"]',
    STATUS: '[data-region="statusselect"]',
    FIELD: '[data-region="filterfield"]',
    CHIPROW: '[data-region="chiprow"]',
    CLEARALL: '[data-region="clearall"]',
    FILTERCOUNT: '[data-region="filtercount"]',
    CHIPREMOVE: '.enrol_apply-chipremove',
};

/** @var {Number} How long to wait after the last keystroke before narrowing the queue. */
const DEBOUNCE = 250;

/**
 * @var {Array} Query parameters flexible_table owns, which a filter change invalidates.
 *
 * updateTable() resets the table to page one when the filter set changes but leaves the url alone,
 * so an operator who arrived on a paging link and then typed would keep `page=3` in the address
 * bar, and a reload or a shared link would land on a page the filtered result may not reach. The
 * GET form emits none of these; syncAddressBar() removes them on the AJAX path.
 *
 * The names are the defaults of flexible_table::$request, set in its constructor.
 */
const TABLE_PARAMS = ['page', 'tsort', 'tdir', 'thide', 'tshow', 'tifirst', 'tilast', 'treset'];

/**
 * Every filter token on the page, read from the controls rather than from a list here.
 *
 * The field filters are configurable per site (enrol_apply/queuefilterfields), so their tokens are
 * read from each control's data-filter attribute rather than listed here.
 *
 * @return {Array} Token strings, search and status included.
 */
const filterTokens = () => {
    const tokens = ['search', 'status'];
    document.querySelectorAll(SELECTORS.FIELD).forEach((el) => tokens.push(el.dataset.filter));

    return tokens;
};

/** @var {Number} Timer id of the pending debounce, or null. */
let pending = null;

/** @var {Boolean} Whether a refresh is in flight; see applyFilters() for why this matters. */
let refreshing = false;

/**
 * Every bulk action control belonging to this queue's toggle group.
 *
 * @param {String} group The checkbox-toggleall group the table and the bar share.
 * @return {NodeList} The controls.
 */
const actionElements = (group) => document.querySelectorAll(SELECTORS.ACTION.replace('{group}', group));

/**
 * Say how many rows are selected, and disable the action when none are.
 *
 * @param {String} group The checkbox-toggleall group the table and the bar share.
 * @param {Number} count How many rows are selected on the page as it now stands.
 * @return {Promise} Resolved once the count has been written.
 */
const setSelected = (group, count) => {
    actionElements(group).forEach((element) => {
        element.disabled = count === 0;
    });

    const label = document.querySelector(SELECTORS.COUNT);
    if (!label) {
        return Promise.resolve();
    }

    /* Written with textContent and not innerHTML: the string carries a number this module
       counted, but the wording around it is a lang string an administrator can edit. */
    return getString('queueselectedonpage', 'enrol_apply', count)
        .then((text) => {
            label.textContent = text;
            return text;
        })
        .catch(Notification.exception);
};

/**
 * What the operator has narrowed the queue to, read off the controls themselves.
 *
 * The controls are the only state; this module keeps no copy that could drift from what the
 * operator sees.
 *
 * @return {Object} search, status and statuslabel ('' when not applied), and fields: one entry
 *     ({token, value, label, text}) per field filter that holds a value.
 */
const currentFilters = () => {
    const search = document.querySelector(SELECTORS.SEARCH);
    const status = document.querySelector(SELECTORS.STATUS);

    /* One entry per control on the page, so a field added to the setting needs no change here.
       The label comes from the control's data-filterlabel, so a chip repeats the control's own
       wording. */
    const fields = [];
    document.querySelectorAll(SELECTORS.FIELD).forEach((el) => {
        const value = el.value.trim();
        if (value !== '') {
            fields.push({
                token: el.dataset.filter,
                value,
                label: el.dataset.filterlabel,
                text: el.tagName === 'SELECT' && el.selectedIndex >= 0 ? el.options[el.selectedIndex].text : value,
            });
        }
    });

    return {
        search: search ? search.value.trim() : '',
        status: status ? status.value : '',
        statuslabel: status && status.selectedIndex >= 0 ? status.options[status.selectedIndex].text : '',
        fields,
    };
};

/**
 * Hand the table the filterset the controls describe.
 *
 * Passes the whole envelope, not a bare map of filters: setFilters() stores what it is given in
 * dataset.tableFilters, which refreshTableContent() reads back expecting jointype beside filters,
 * so a bare map produces a request with no join type that the service refuses. Replacing the
 * filters of the existing envelope also keeps the scope's required enrolid filter.
 *
 * An empty search adds no filter rather than one carrying '', because string_filter accepts '' as
 * a live value and would empty the queue. See applications_filterset::get_optional_filters().
 *
 * @param {HTMLElement} tableRoot The table region.
 * @return {Promise} Resolved when the refresh completes.
 */
const applyFilters = (tableRoot) => {
    /* Refreshes are serialised here rather than in the debounce, because the select and date
       controls, chip removal and clear-all call this directly. refreshTableContent() has no abort
       and no sequence number: it replaces the node it was given, and jQuery's replaceWith() does
       nothing to a node that is already detached, so of two overlapping refreshes the second
       response is silently dropped and the table shows an earlier filter.

       A busy call is re-scheduled rather than dropped, so the latest filters are still applied:
       scheduleRefresh() re-reads the controls when it fires. */
    if (refreshing) {
        scheduleRefresh();

        return Promise.resolve(tableRoot);
    }

    const {search, status, fields} = currentFilters();
    const filterset = getFilters(tableRoot);
    const filters = {};
    const ours = filterTokens();

    /* Keep the scope's own filter and rewrite every token this page offers, so a field filter
       the operator has emptied is dropped rather than carried over from the previous request. */
    Object.keys(filterset.filters).forEach((name) => {
        if (ours.indexOf(name) === -1) {
            filters[name] = filterset.filters[name];
        }
    });

    if (search !== '') {
        filters.search = {name: 'search', jointype: 1, values: [search]};
    }
    if (status !== '') {
        // Number(), because everything read out of the DOM is a string and integer_filter's
        // add_filter_value() tests is_int() and throws a TypeError rather than refusing softly.
        filters.status = {name: 'status', jointype: 1, values: [Number(status)]};
    }
    fields.forEach((field) => {
        // Every one of these is a string_filter server-side, dates included.
        filters[field.token] = {name: field.token, jointype: 1, values: [field.value]};
    });

    refreshing = true;

    return setFilters(tableRoot, {jointype: filterset.jointype, filters})
        .catch(Notification.exception)
        .then((root) => {
            refreshing = false;
            return root;
        });
};

/**
 * Narrow the queue, after the operator has stopped typing.
 *
 * @return {void}
 */
const scheduleRefresh = () => {
    window.clearTimeout(pending);
    pending = window.setTimeout(() => {
        const tableRoot = document.querySelector(SELECTORS.TABLE);
        if (tableRoot) {
            // Busy check lives in applyFilters(), which is not reached only from here.
            applyFilters(tableRoot);
        }
    }, DEBOUNCE);
};

/**
 * The url this listing would have if one filter were dropped.
 *
 * Built from the address bar rather than from a value the server rendered, so it stays right after
 * an as-you-type change - which never reloads the page and so never re-renders a server-built url.
 *
 * @param {String|null} drop Filter to remove, or null to remove every one of them.
 * @return {String} The url.
 */
const urlWithout = (drop) => {
    const url = new URL(window.location.href);
    filterTokens().forEach((name) => {
        if (drop === null || drop === name) {
            url.searchParams.delete(name);
        }
    });

    return url.toString();
};

/**
 * Keep the address bar saying what the page is showing.
 *
 * So a reload, a bookmark or a copied link carries the filters applied as the operator typed.
 * replaceState rather than pushState, so the back button does not walk back one keystroke at a
 * time.
 *
 * @return {void}
 */
const syncAddressBar = () => {
    const {search, status, fields} = currentFilters();
    const url = new URL(window.location.href);

    if (search === '') {
        url.searchParams.delete('search');
    } else {
        url.searchParams.set('search', search);
    }
    if (status === '') {
        url.searchParams.delete('status');
    } else {
        url.searchParams.set('status', status);
    }
    /* Every offered token is cleared first and then re-set from the controls, so a field the
       operator has just emptied leaves the url rather than lingering in it. */
    document.querySelectorAll(SELECTORS.FIELD).forEach((el) => url.searchParams.delete(el.dataset.filter));
    fields.forEach((field) => url.searchParams.set(field.token, field.value));

    // The table is back on page one; see TABLE_PARAMS.
    TABLE_PARAMS.forEach((name) => url.searchParams.delete(name));

    window.history.replaceState({}, '', url.toString());
};

/**
 * One chip's markup, from the partial the server renders too.
 *
 * Kept out of redrawChips()'s chain because eslint's promise/no-nesting rejects the inline form.
 *
 * @param {Object} chip filter, name, value and removeurl for one applied filter.
 * @return {Promise} Resolved with the rendered html.
 */
const renderChip = (chip) => getString('queueremovefilter', 'enrol_apply', {
    name: chip.name,
    value: chip.value,
}).then((removelabel) => Templates.render('enrol_apply/queue_chip', {...chip, removelabel}));

/**
 * Redraw the chip row and the count for the filters now applied.
 *
 * The chips are rendered from the same partial the server uses (enrol_apply/queue_chip), so the
 * markup exists once. The status chip takes its wording from the selected option's text, so the
 * chip and the control cannot word the same state differently.
 *
 * @return {Promise} Resolved once the row has been redrawn.
 */
const redrawChips = () => {
    const row = document.querySelector(SELECTORS.CHIPROW);
    const clearall = document.querySelector(SELECTORS.CLEARALL);
    if (!row) {
        return Promise.resolve();
    }

    const {search, status, statuslabel, fields} = currentFilters();

    return Promise.all([
        getString('queuesearch', 'enrol_apply'),
        getString('queuefilterstatus', 'enrol_apply'),
    ]).then(([searchname, statusname]) => {
        const wanted = [];
        if (search !== '') {
            wanted.push({filter: 'search', name: searchname, value: search, removeurl: urlWithout('search')});
        }
        if (status !== '') {
            wanted.push({filter: 'status', name: statusname, value: statuslabel, removeurl: urlWithout('status')});
        }
        // Field labels come from the controls themselves; see currentFilters().
        fields.forEach((field) => {
            wanted.push({
                filter: field.token,
                name: field.label,
                value: field.text,
                removeurl: urlWithout(field.token),
            });
        });

        if (clearall) {
            clearall.href = urlWithout(null);
            clearall.classList.toggle('d-none', wanted.length === 0);
        }

        return Promise.all(wanted.map(renderChip));
    }).then((rendered) => {
        row.querySelectorAll('[data-region="chip"]').forEach((chip) => chip.remove());
        rendered.reverse().forEach((html) => row.insertAdjacentHTML('afterbegin', html));
        return rendered;
    }).catch(Notification.exception);
};

/**
 * Say how many applications match, of how many the queue holds.
 *
 * The matched half comes off the refreshed table, which is the only thing that knows it. The total
 * is the scope's and no filter changes it, so it is read from the attribute the server wrote once
 * rather than recounted.
 *
 * @param {HTMLElement} tableRoot The refreshed table region.
 * @return {Promise} Resolved once the line has been written.
 */
const redrawCount = (tableRoot) => {
    const line = document.querySelector(SELECTORS.FILTERCOUNT);
    if (!line || !tableRoot) {
        return Promise.resolve();
    }

    return getString('queuefiltercount', 'enrol_apply', {
        matched: Number(tableRoot.dataset.tableTotalRows || 0),
        total: Number(line.dataset.scopetotal || 0),
    }).then((text) => {
        line.textContent = text;
        return text;
    }).catch(Notification.exception);
};

/**
 * Drop one filter, or all of them, without a page load.
 *
 * @param {String|null} drop Filter to remove, or null for every one.
 * @return {void}
 */
const removeFilter = (drop) => {
    const search = document.querySelector(SELECTORS.SEARCH);
    const status = document.querySelector(SELECTORS.STATUS);

    if ((drop === null || drop === 'search') && search) {
        search.value = '';
    }
    if ((drop === null || drop === 'status') && status) {
        status.value = '';
    }
    document.querySelectorAll(SELECTORS.FIELD).forEach((el) => {
        if (drop === null || drop === el.dataset.filter) {
            el.value = '';
        }
    });

    /* Move focus, because the redraw removes the chip just activated (or hides clear-all) and a
       keyboard operator would be returned to the top of the page. The search box is the one
       control that is always there. */
    if (search) {
        search.focus();
    }

    const tableRoot = document.querySelector(SELECTORS.TABLE);
    if (tableRoot) {
        applyFilters(tableRoot);
    }
};

/**
 * Wire the queue.
 *
 * @param {String} group The checkbox-toggleall group the table and the bar share.
 * @return {void}
 */
export const init = (group) => {
    if (!document.querySelector(SELECTORS.FORM)) {
        return;
    }

    // Core already calls this from flexible_table::get_dynamic_table_html_end(); init() is idempotent.
    initDynamicTable();
    setSelected(group, 0);

    /* The subscribe import is named because core/pubsub has no default export: a default import
       compiles to undefined and this call throws inside init(). Neither eslint nor grunt catches
       that; Behat reports it only as a timeout waiting for JavaScript to be ready. */
    subscribe(CheckboxToggleAll.events.checkboxToggled, (data) => {
        if (data.toggleGroupName !== group) {
            return;
        }
        setSelected(group, data.checkedTargets.length);
    });

    // Everything outside the refreshed region is reset here; see the module docblock.
    document.addEventListener(DynamicTableEvents.tableContentRefreshed, (e) => {
        setSelected(group, 0);
        redrawChips();
        /* The event's target is the new table root (core dispatches on it), so the count is read
           from the refreshed markup rather than from the detached node. */
        redrawCount(e.target);
        syncAddressBar();
    });

    const filters = document.querySelector(SELECTORS.FILTERS);
    if (!filters) {
        return;
    }

    // Only typing is intercepted: Enter still submits the GET form (see the module docblock).
    const search = filters.querySelector(SELECTORS.SEARCH);
    if (search) {
        search.addEventListener('input', scheduleRefresh);
    }

    /* A text box narrows as you type; a select or a date picker commits in one action, so it
       refreshes on change. Both routes reach applyFilters(), which carries the busy check. */
    filters.querySelectorAll(SELECTORS.FIELD).forEach((el) => {
        if (el.tagName === 'SELECT' || el.type === 'date') {
            el.addEventListener('change', () => {
                const tableRoot = document.querySelector(SELECTORS.TABLE);
                if (tableRoot) {
                    applyFilters(tableRoot);
                }
            });
        } else {
            el.addEventListener('input', scheduleRefresh);
        }
    });

    const status = filters.querySelector(SELECTORS.STATUS);
    if (status) {
        // No debounce on a select: a change is deliberate and there is no second one coming.
        status.addEventListener('change', () => {
            const tableRoot = document.querySelector(SELECTORS.TABLE);
            if (tableRoot) {
                applyFilters(tableRoot);
            }
        });
    }

    /* Delegated from the row rather than bound per chip, because the chips are replaced wholesale
       on every refresh and a handler bound to one goes with it. */
    filters.addEventListener('click', (e) => {
        const remove = e.target.closest(SELECTORS.CHIPREMOVE);
        if (remove) {
            e.preventDefault();
            removeFilter(remove.dataset.filter);
            return;
        }

        const clearall = e.target.closest(SELECTORS.CLEARALL);
        if (clearall) {
            e.preventDefault();
            removeFilter(null);
        }
    });
};
