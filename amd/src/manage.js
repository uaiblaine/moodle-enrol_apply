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
 * The four things core does not do for the applications queue.
 *
 * ONE. The bulk action control's INITIAL state. checkbox-toggleall's init() binds two delegated
 * click handlers and nothing else, and setActionElementStates() is reached only from those
 * handlers, so a control is live until the first click. Every core caller closes that by
 * hardcoding the disabled attribute in its server markup. This plugin does not, and that is the
 * one place it departs from core on purpose: the queue is operable without JavaScript, so an
 * attribute only JavaScript can clear would take a working path away from a no-JS operator to buy
 * an affordance only a JavaScript one can see. Doing it from here gives each audience what it can
 * use. That only holds because styles.css puts the sticky footer back on screen when no script
 * ran; the two live together or not at all.
 *
 * TWO. The bulk bar must not lie about what is selected. refreshTableContent() replaces the whole
 * table region on a page turn, a sort or a filter change, so every checkbox in it is destroyed -
 * while the bar lives in the sticky footer OUTSIDE that region and survives, with whatever
 * enabled state and whatever count it had. So the count is maintained here, worded "on this
 * page" because that is what a selection is, and both it and the action control are reset on
 * every refresh. Nothing in core does this: get.php returns {html, warnings} and, unlike
 * core_form\external\dynamic_form, never returns get_end_code(), so a refreshed table carries no
 * JavaScript of its own and anything it needs must be bound from a stable ancestor.
 *
 * THREE. Nothing refreshes the table unless core_table/dynamic's own init() has run. It is not
 * automatic: the markup carries the data-region a refresh targets, and the module that acts on it
 * has to be asked for. Without this the queue's paging and sorting stay full page loads - which
 * still work, and are what a no-JavaScript operator gets either way.
 *
 * FOUR. The filter bar narrows the queue as the operator types, and everything it displays lives
 * OUTSIDE the region a refresh replaces - so the chip row, the clear-all control and the count
 * line would each go on describing the filters that were applied when the page loaded. They are
 * redrawn from tableContentRefreshed for that reason, the chips through the same Mustache partial
 * the server renders, so the markup exists once. The GET form underneath is untouched and still
 * works with scripting off; pressing Enter still submits it, which is how an operator gets a
 * permalink to what they are looking at.
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
 * updateTable() resets the table to page one whenever the filter set changes, and says nothing
 * about the url. So an operator who arrived on a real paging link - they are ordinary anchors, by
 * design - and then typed would keep `page=3` in the address bar while looking at page one, and a
 * reload or a shared link would land somewhere the filtered result does not reach. The GET form
 * avoids this by emitting none of them; this is the AJAX path inheriting the same protection.
 *
 * The names are flexible_table's own (lib/table/classes/flexible_table.php:168-175).
 */
const TABLE_PARAMS = ['page', 'tsort', 'tdir', 'thide', 'tshow', 'tifirst', 'tilast', 'treset'];

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
 * The controls are the state. Keeping a copy in this module would give the page two answers to
 * one question, and the one that drifts is the copy - the controls are what the operator can see.
 *
 * @return {Object} search and status, each empty when not applied.
 */
const currentFilters = () => {
    const search = document.querySelector(SELECTORS.SEARCH);
    const status = document.querySelector(SELECTORS.STATUS);

    return {
        search: search ? search.value.trim() : '',
        status: status ? status.value : '',
        statuslabel: status && status.selectedIndex >= 0 ? status.options[status.selectedIndex].text : '',
    };
};

/**
 * Hand the table the filterset the controls describe.
 *
 * **The whole envelope, not a bare map of filters.** setFilters() stringifies what it is given
 * straight into dataset.tableFilters, which refreshTableContent() then reads back expecting
 * jointype alongside filters - so a bare map produces a request with no join type and the service
 * refuses it. Read the existing envelope and replace its filters, which also keeps the scope's own
 * required enrolid filter without this module having to know what it is.
 *
 * An empty search adds NO filter rather than one carrying the empty string. string_filter accepts
 * '' as a live value - its add_filter_value() is a complete override that never reaches the base
 * class's rejection - so sending one narrows the queue to nothing the moment the box is cleared.
 *
 * @param {HTMLElement} tableRoot The table region.
 * @return {Promise} Resolved when the refresh completes.
 */
const applyFilters = (tableRoot) => {
    /* **The serialisation guard lives HERE and not in the debounce**, because three things call
       this and only one of them is the debounce: the status select changes immediately, and so do
       chip removal and clear-all. An earlier version guarded only scheduleRefresh(), and this
       comment claimed refreshes were serialised while typing a term and then clicking a chip put
       two in flight - which is exactly the race described below, reachable by ordinary use.

       It matters because refreshTableContent() has no abort and no sequence number: it captures
       the node it was given, fetches, and replaces that node. jQuery's replaceWith only calls
       replaceChild when the target still has a parent, so whichever response lands SECOND is
       silently dropped if the first already detached the node - and the operator is left looking
       at the result of an earlier keystroke, with no error anywhere.

       Re-scheduling rather than dropping: the operator's latest intent must still be applied, and
       scheduleRefresh() re-reads the controls when it fires. */
    if (refreshing) {
        scheduleRefresh();

        return Promise.resolve(tableRoot);
    }

    const {search, status} = currentFilters();
    const filterset = getFilters(tableRoot);
    const filters = {};

    // The scope's own filter survives untouched; only the operator's two are rewritten.
    Object.keys(filterset.filters).forEach((name) => {
        if (name !== 'search' && name !== 'status') {
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
    ['search', 'status'].forEach((name) => {
        if (drop === null || drop === name) {
            url.searchParams.delete(name);
        }
    });

    return url.toString();
};

/**
 * Keep the address bar saying what the page is showing.
 *
 * replaceState rather than pushState: each keystroke is not a place in the operator's history, and
 * a back button that walked one letter at a time would be unusable. What it buys is that a reload,
 * a bookmark and a link copied out of the address bar all carry the filter the operator applied -
 * without it, an as-you-type search is invisible to every one of those.
 *
 * @return {void}
 */
const syncAddressBar = () => {
    const {search, status} = currentFilters();
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

    // The table is back on page one and unsorted-by-request; the url must not claim otherwise.
    TABLE_PARAMS.forEach((name) => url.searchParams.delete(name));

    window.history.replaceState({}, '', url.toString());
};

/**
 * One chip's markup, from the partial the server renders too.
 *
 * A function of its own rather than a promise chained inside redrawChips()'s own chain: eslint's
 * promise/no-nesting rejects the inline form, and the rule is right here - the nested version
 * hides that the label has to resolve before the template can be given it.
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
 * The chips are rendered from the SAME partial the server uses, through core/templates, so the
 * markup exists once. Only the context is assembled here - and the status chip takes its wording
 * from the selected option's own text rather than from a string of its own, so the chip and the
 * control it describes cannot word the same state differently.
 *
 * @return {Promise} Resolved once the row has been redrawn.
 */
const redrawChips = () => {
    const row = document.querySelector(SELECTORS.CHIPROW);
    const clearall = document.querySelector(SELECTORS.CLEARALL);
    if (!row) {
        return Promise.resolve();
    }

    const {search, status, statuslabel} = currentFilters();

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

    /* Focus moves deliberately, because the control that was just activated has been removed from
       the document - a keyboard operator would otherwise be returned to the top of the page with
       nothing to say where they were. The search box is the one control that is always there. */
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

    initDynamicTable();
    setSelected(group, 0);

    /* A NAMED import, and the distinction is not style: core/pubsub is an ES module exporting
       subscribe, unsubscribe and publish and NOTHING as default, so `import PubSub from
       'core/pubsub'` compiles to a default that is undefined and the first call is a TypeError.
       core/notification, imported as a default just above, does declare one - "to maintain
       backwards compatability", says core - and core/checkbox-toggleall is an old-style AMD
       module, which the interop wrapper turns into a default. Three modules, three shapes; the
       only way to know is to read each one.

       Nothing in the pipeline catches getting this wrong. eslint resolves no Moodle module names,
       and the built bundle is valid JavaScript either way. What it produces is a throw inside
       init(), which means js_call_amd's js_complete() never runs, which Behat reports twenty
       seconds later as "Javascript code and/or AJAX requests are not ready" naming the module -
       a timeout, with no stack and no mention of the line. */
    subscribe(CheckboxToggleAll.events.checkboxToggled, (data) => {
        if (data.toggleGroupName !== group) {
            return;
        }
        setSelected(group, data.checkedTargets.length);
    });

    /* The reset, and it has to be here rather than in the refreshed markup: the refreshed region
       carries no JavaScript, and the bar being reset is not in that region anyway.

       The chip row and the count are redrawn from the SAME event, and for the same reason: they
       live outside the region a refresh replaces, so nothing else would ever tell them the filters
       changed. The event carries the new region as its target, which is what redrawCount() reads -
       a closure over the old node would read a count from markup already detached. */
    document.addEventListener(DynamicTableEvents.tableContentRefreshed, (e) => {
        setSelected(group, 0);
        redrawChips();
        /* The event's target IS the new table root: core dispatches on that node, and a target
           does not change while an event bubbles. */
        redrawCount(e.target);
        syncAddressBar();
    });

    const filters = document.querySelector(SELECTORS.FILTERS);
    if (!filters) {
        return;
    }

    /* The GET form stays a working form: with scripting off it is the whole mechanism, and with
       scripting on pressing Enter still submits it, which reloads the page onto a url carrying the
       filter. That is a feature rather than a duplicate path - it is how an operator gets a
       permalink to what they are looking at. */
    const search = filters.querySelector(SELECTORS.SEARCH);
    if (search) {
        search.addEventListener('input', scheduleRefresh);
    }

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
