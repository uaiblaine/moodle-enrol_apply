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
 * Fills the gaps core leaves in the applications queue's decision bar and filter bar, and opens the
 * decision sheet.
 *
 * The decisions' initial state: core/checkbox-toggleall's init() only binds click handlers, so
 * nothing disables the footer's three buttons until the first click. Core callers hardcode the
 * disabled attribute in their markup instead; this queue must stay operable without JavaScript, so
 * the buttons are disabled from here. That relies on the styles.css rule that puts the sticky
 * footer back on screen when no script ran; the two go together.
 *
 * The decision sheet (MQ-D5, MQ-D6): a press on Cancel applications, Defer or Confirm opens a
 * core/modal naming the selected applicants instead of posting. Its body holds the page's own
 * decision fields, MOVED in from the decision options above the table and moved back before
 * anything posts, so the one form that posts is still #enrol_apply_manage_form and no input is ever
 * copied. Its decision button submits that form with form.submit() and a hidden formaction this
 * module writes, because a programmatic submit posts no submitter. A selection spanning enrolment
 * methods (the site-wide and mentee queues only) gets a section and a message box per method,
 * posted as outcomemessages[<enrolid>] and grouped by method again on the server from the
 * database, never from these keys (MQ-D15, MQ-D16).
 *
 * Table refreshes: refreshTableContent() replaces the whole table region on a page turn, a sort or
 * a filter change, while the bulk bar (in the sticky footer), the filter bar's chips, clear-all
 * control and count line, and the "Showing" line under the table all live outside that region.
 * The refreshed html comes from core_table\external\dynamic\get, which returns no JavaScript, so
 * everything outside the region is reset or redrawn here from the tableContentRefreshed event.
 * The selection count is worded "on this page" because a refresh discards the selection.
 *
 * The filter bar narrows the queue as the operator types. The GET form underneath still works
 * with scripting off, and pressing Enter still submits it, which gives the operator a permalink.
 *
 * @module     enrol_apply/manage
 * @copyright  2026 Anderson Blaine
 * @copyright  2016 sudile GbR (http://www.sudile.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {setFilters, getFilters} from 'core_table/dynamic';
import CheckboxToggleAll from 'core/checkbox-toggleall';
import DynamicTableEvents from 'core_table/local/dynamic/events';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {subscribe} from 'core/pubsub';
import {getString, getStrings} from 'core/str';

const SELECTORS = {
    FORM: '#enrol_apply_manage_form',
    ACTION: '[data-toggle="action"][data-togglegroup="{group}"]',
    COUNT: '[data-region="selectedcount"]',
    TABLE: '[data-region="core_table/dynamic"]',
    FILTERS: '[data-region="queuefilters"]',
    SEARCH: '[data-region="searchinput"]',
    STATUSCELL: '[data-region="statuscell"]',
    FIELD: '[data-region="filterfield"]',
    PANEL: '[data-region="filterpanel"]',
    PANELCOUNT: '[data-region="filterpanelcount"]',
    CHIPROW: '[data-region="chiprow"]',
    CLEARALL: '[data-region="clearall"]',
    CLEARFILTERS: '[data-region="clearfilters"]',
    FILTERCOUNT: '[data-region="filtercount"]',
    SHOWING: '[data-region="showing"]',
    CHIPREMOVE: '.enrol_apply-chipremove',
    DECIDE: '[data-region="decide"]',
    OPTIONS: '[data-region="decisionoptions"]',
    CONTROLS: '[data-region="decisioncontrols"]',
    SELECTED: 'input[name="userenrolments[]"]:checked',
    APPLICANT: '[data-region="applicant"]',
    /* The parts of the decision fields that belong to an approval alone: hidden in the sheet for a
       deferral or a cancellation, which read the note and the message only. */
    CONFIRMONLY: '.enrol_apply-decisiongroups, .enrol_apply-decisionrole, .enrol_apply-decisionsummary',
    MESSAGE: '.enrol_apply-outcomemessage',
};

/**
 * @var {Object} The sheet's strings per decision, literal ids rather than built ones, so every id
 *     can be found by a search of the lang file.
 */
const SHEET = {
    confirm: {title: 'queuesheetconfirm', one: 'queuesheetconfirmone', commit: 'queuesheetconfirmcommit'},
    wait: {title: 'queuesheetwait', one: 'queuesheetwaitone', commit: 'queuesheetwaitcommit'},
    cancel: {title: 'queuesheetcancel', one: 'queuesheetcancelone', commit: 'queuesheetcancelcommit'},
};

/** @var {Number} How many applicants the sheet names before "and N more". */
const NAMED = 5;

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

/**
 * The value of a control that means "not filtered".
 *
 * Empty for every control but the group select where the page defaults it to the reader's own
 * groups: there "every group" is the explicit `all`, carried in data-emptyvalue, because a url
 * without the parameter would bring the default back.
 *
 * @param {HTMLElement} el A filter control.
 * @return {String} The value meaning "no filter".
 */
const emptyValue = (el) => el.dataset.emptyvalue || '';

/** @var {Number} Timer id of the pending debounce, or null. */
let pending = null;

/** @var {Boolean} Whether a refresh is in flight; see applyFilters() for why this matters. */
let refreshing = false;

/**
 * @var {Boolean} Whether a decision sheet is open or opening: the decision fields can be in one
 *     dialogue only, so a second press while the first sheet is being built opens nothing.
 */
let sheetopen = false;

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
 * @return {Object} search and status ('' when not applied), and fields: one entry
 *     ({token, value, label, text}) per field filter, the Filters panel's, that holds a value.
 */
const currentFilters = () => {
    const search = document.querySelector(SELECTORS.SEARCH);
    const status = document.querySelector(SELECTORS.STATUSCELL + '[aria-current="true"]');

    /* One entry per control on the page, so a field added to the setting needs no change here.
       The label comes from the control's data-filterlabel, so a chip repeats the control's own
       wording. */
    const fields = [];
    document.querySelectorAll(SELECTORS.FIELD).forEach((el) => {
        const value = el.value.trim();
        if (value !== '' && value !== emptyValue(el)) {
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
        status: status ? status.dataset.status : '',
        fields,
    };
};

/**
 * Make one value of the status control the current one.
 *
 * @param {String} value The status parameter's value, empty for every status.
 * @return {void}
 */
const setStatus = (value) => {
    document.querySelectorAll(SELECTORS.STATUSCELL).forEach((cell) => {
        if (cell.dataset.status === value) {
            cell.setAttribute('aria-current', 'true');
        } else {
            cell.removeAttribute('aria-current');
        }
    });
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
    // A control whose "no filter" is a value of its own says so, or the page's default comes back.
    document.querySelectorAll(SELECTORS.FIELD).forEach((el) => {
        if ((drop === null || drop === el.dataset.filter) && emptyValue(el) !== '') {
            url.searchParams.set(el.dataset.filter, emptyValue(el));
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
    document.querySelectorAll(SELECTORS.FIELD).forEach((el) => {
        url.searchParams.delete(el.dataset.filter);
        if (emptyValue(el) !== '') {
            url.searchParams.set(el.dataset.filter, emptyValue(el));
        }
    });
    fields.forEach((field) => url.searchParams.set(field.token, field.value));

    // The table is back on page one; see TABLE_PARAMS.
    TABLE_PARAMS.forEach((name) => url.searchParams.delete(name));

    window.history.replaceState({}, '', url.toString());

    /* Each status value stays a link to this listing with that status, as the server built it, so
       opening one in a new tab carries the filters applied since the page loaded. */
    document.querySelectorAll(SELECTORS.STATUSCELL).forEach((cell) => {
        const target = new URL(url.toString());
        if (cell.dataset.status === '') {
            target.searchParams.delete('status');
        } else {
            target.searchParams.set('status', cell.dataset.status);
        }
        cell.href = target.toString();
    });
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
 * Redraw the chip row, the panel's loud counter and the clear controls for the filters applied.
 *
 * The chips are rendered from the same partial the server uses (enrol_apply/queue_chip), so the
 * markup exists once. The status has no chip, as on the server: the status control says which
 * value is current.
 *
 * @return {Promise} Resolved once the row has been redrawn.
 */
const redrawChips = () => {
    const row = document.querySelector(SELECTORS.CHIPROW);
    const clearall = document.querySelector(SELECTORS.CLEARALL);
    if (!row) {
        return Promise.resolve();
    }

    const {search, fields} = currentFilters();

    // One per panel filter in force, as renderer::queue_filters_context() counts them.
    const badge = document.querySelector(SELECTORS.PANELCOUNT);
    if (badge) {
        badge.textContent = String(fields.length);
        badge.hidden = fields.length === 0;
    }
    const clearfilters = document.querySelector(SELECTORS.CLEARFILTERS);
    if (clearfilters) {
        clearfilters.href = urlWithout(null);
    }

    return getString('queuesearch', 'enrol_apply').then((searchname) => {
        const wanted = [];
        if (search !== '') {
            wanted.push({filter: 'search', name: searchname, value: search, removeurl: urlWithout('search')});
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
 * Say which rows of how many the refreshed table shows, or hide the line when it shows none.
 *
 * The same rule as enrol_apply_renderer::manage_form(), which renders the line on page load: a page
 * holding no row has no range to name, so the line is emptied and hidden rather than reading
 * "Showing 1-0 of 0". The three numbers come off the refreshed table root, which core writes from
 * the table's own state.
 *
 * @param {HTMLElement} tableRoot The refreshed table region.
 * @return {Promise} Resolved once the line has been written or hidden.
 */
const redrawShowing = (tableRoot) => {
    const line = document.querySelector(SELECTORS.SHOWING);
    if (!line || !tableRoot) {
        return Promise.resolve();
    }

    const total = Number(tableRoot.dataset.tableTotalRows || 0);
    const size = Number(tableRoot.dataset.tablePageSize || 0);
    const from = (Number(tableRoot.dataset.tablePageNumber || 1) - 1) * size + 1;
    const to = Math.min(total, from + size - 1);

    if (from > to) {
        line.textContent = '';
        line.classList.add('d-none');
        return Promise.resolve();
    }

    return getString('queueshowing', 'enrol_apply', {from, to, total}).then((text) => {
        line.textContent = text;
        line.classList.remove('d-none');
        return text;
    }).catch(Notification.exception);
};

/**
 * Drop one filter, or all of them, without a page load where that can be done.
 *
 * A control core/form-autocomplete enhances draws its selection from the enhancement, which a
 * value set from here does not redraw, so the chip would leave the control naming a filter no
 * longer applied. Dropping one of those follows the link instead, which the server renders right.
 *
 * @param {String|null} drop Filter to remove, or null for every one.
 * @param {String} href The link's own url, the same listing without that filter.
 * @return {void}
 */
const removeFilter = (drop, href) => {
    const search = document.querySelector(SELECTORS.SEARCH);

    const reload = Array.from(document.querySelectorAll(SELECTORS.FIELD)).some((el) => el.dataset.reloadonremove
        && (drop === null || drop === el.dataset.filter)
        && el.value !== emptyValue(el));
    if (reload) {
        window.location.assign(href);
        return;
    }

    if ((drop === null || drop === 'search') && search) {
        search.value = '';
    }
    if (drop === null || drop === 'status') {
        setStatus('');
    }
    document.querySelectorAll(SELECTORS.FIELD).forEach((el) => {
        if (drop === null || drop === el.dataset.filter) {
            el.value = emptyValue(el);
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
 * What the sheet names of one selected row, read off the row the server rendered for this reader.
 *
 * Nothing is fetched: the picture, the name and the method are the row's own markup and data
 * attributes, so the sheet discloses nothing the page did not already show.
 *
 * @param {HTMLInputElement} checkbox A selected row's checkbox.
 * @return {Object} name, picture (a detached copy of core's picture, or null), and the method:
 *     enrolid, methodname, coursename, deciding.
 */
const selectedRow = (checkbox) => {
    const row = checkbox.closest('tr');
    const applicant = row ? row.querySelector(SELECTORS.APPLICANT) : null;
    if (!applicant) {
        return {name: checkbox.value, picture: null, enrolid: '', methodname: '', coursename: '', deciding: false};
    }

    const nameNode = applicant.querySelector('.enrol_apply-applicantname');
    const pictureNode = applicant.querySelector('.userpicture, .userinitials');
    let picture = null;
    if (pictureNode) {
        // The picture without its link: the sheet repeats the picture, never the profile link (section 4).
        picture = pictureNode.cloneNode(true);
        picture.removeAttribute('id');
        picture.setAttribute('aria-hidden', 'true');
        if (picture.tagName === 'IMG') {
            picture.alt = '';
        }
    }

    return {
        name: nameNode ? nameNode.textContent.trim() : '',
        picture,
        enrolid: applicant.dataset.enrolid || '',
        methodname: applicant.dataset.methodname || '',
        coursename: applicant.dataset.coursename || '',
        deciding: applicant.dataset.deciding === '1',
    };
};

/**
 * The list of applicants a sheet or one of its sections names: the first five, then "and N more".
 *
 * @param {Array} rows What selectedRow() read, for the applicants to name.
 * @param {String} more The "and N more" wording for this list, empty when it names everybody.
 * @return {HTMLElement} The list.
 */
const whoList = (rows, more) => {
    const list = document.createElement('ul');
    list.className = 'enrol_apply-sheetwho';
    rows.slice(0, NAMED).forEach((row) => {
        const item = document.createElement('li');
        if (row.picture) {
            item.appendChild(row.picture.cloneNode(true));
        }
        const name = document.createElement('span');
        name.textContent = row.name;
        item.appendChild(name);
        list.appendChild(item);
    });
    if (more !== '') {
        const item = document.createElement('li');
        item.className = 'enrol_apply-sheetmore';
        item.textContent = more;
        list.appendChild(item);
    }

    return list;
};

/**
 * A paragraph or alert of the sheet, worded with textContent.
 *
 * @param {String} tag The element to create.
 * @param {String} className Its classes.
 * @param {String} text Its wording, a lang string.
 * @return {HTMLElement} The element.
 */
const sheetLine = (tag, className, text) => {
    const node = document.createElement(tag);
    node.className = className;
    node.textContent = text;

    return node;
};

/**
 * Text as html, for the two parts of core/modal that take markup: the title and the footer.
 *
 * @param {String} text Plain text, a lang string with a number in it.
 * @return {String} The same text, escaped.
 */
const escapeHtml = (text) => {
    const node = document.createElement('span');
    node.textContent = text;

    return node.innerHTML;
};

/**
 * The selection grouped by enrolment method, in the order the methods first appear.
 *
 * @param {Array} rows What selectedRow() read for every selected row.
 * @return {Array} One {enrolid, methodname, coursename, deciding, rows} per method.
 */
const byMethod = (rows) => {
    const methods = [];
    rows.forEach((row) => {
        let method = methods.find((candidate) => candidate.enrolid === row.enrolid);
        if (!method) {
            method = {
                enrolid: row.enrolid,
                methodname: row.methodname,
                coursename: row.coursename,
                deciding: row.deciding,
                rows: [],
            };
            methods.push(method);
        }
        method.rows.push(row);
    });

    return methods;
};

/**
 * One section of a sheet whose selection spans methods: the method, its course and count, the
 * label of a deciding method, the applicants, and the message box for them.
 *
 * The box is created here and posted from the hidden holder in the form (see commit()); its name
 * is outcomemessages[<enrolid>], an enrol id the server only uses as a key to look up, grouping the
 * posted rows by the method the database names.
 *
 * @param {Object} method One entry of byMethod().
 * @param {Object} words The sheet's strings: messagelabel, deciding, and more (a function of a count).
 * @param {Number} index The section's position, for unique ids.
 * @return {HTMLElement} The section.
 */
const methodSection = (method, words, index) => {
    const section = document.createElement('section');
    section.className = 'enrol_apply-sheetsection';

    const head = document.createElement('div');
    head.className = 'enrol_apply-sheetsectionhead';
    head.id = 'enrol_apply_sheetmethod_' + index;
    head.appendChild(sheetLine('strong', '', method.methodname));
    head.appendChild(sheetLine('span', 'enrol_apply-sheetcourse', method.coursename + ' · ' + method.rows.length));
    if (method.deciding) {
        head.appendChild(sheetLine('span', 'enrol_apply-status enrol_apply-status--warning', words.deciding));
    }
    section.appendChild(head);

    const hidden = method.rows.length - NAMED;
    section.appendChild(whoList(method.rows, hidden > 0 ? words.more[method.rows.length] : ''));

    const id = 'enrol_apply_sheetmessage_' + index;
    const label = sheetLine('label', 'form-label', words.messagelabel);
    label.htmlFor = id;
    const box = document.createElement('textarea');
    box.className = 'form-control';
    box.id = id;
    box.rows = 2;
    box.name = 'outcomemessages[' + method.enrolid + ']';
    box.setAttribute('aria-describedby', head.id);
    section.appendChild(label);
    section.appendChild(box);

    return section;
};

/**
 * Open the decision sheet for one of the footer's three decisions.
 *
 * Moves the page's decision fields into the dialogue and back when it closes, by any route: Back,
 * the close cross, Escape or a press outside all hide the modal, and the hidden event puts the
 * fields home and discards the sheet's own message boxes, so nothing is posted. Only the decision
 * button posts, through commit().
 *
 * @param {HTMLButtonElement} button The footer button pressed; its value is the decision.
 * @return {Promise} Resolved once the sheet is shown.
 */
const openSheet = async(button) => {
    const form = document.querySelector(SELECTORS.FORM);
    const controls = form ? form.querySelector(SELECTORS.CONTROLS) : null;
    const action = button.value;
    const rows = form ? Array.from(form.querySelectorAll(SELECTORS.SELECTED)).map(selectedRow) : [];
    if (!controls || !SHEET[action] || !rows.length || sheetopen) {
        return;
    }
    sheetopen = true;
    const methods = byMethod(rows);
    const spans = methods.length > 1;
    const count = rows.length;

    /* Every "and N more" a list of this sheet can need: the whole selection's, and each section's
       when the sheet is split by method. */
    const mores = [count, ...methods.map((method) => method.rows.length)].filter((n) => n > NAMED);
    const ids = [
        {key: count === 1 ? SHEET[action].one : SHEET[action].title, component: 'enrol_apply', param: count},
        {key: SHEET[action].commit, component: 'enrol_apply', param: count},
        {key: 'back', component: 'core'},
        {key: 'queuesheetcanceldanger', component: 'enrol_apply'},
        {key: 'queuesheetwaitline', component: 'enrol_apply'},
        {key: 'queuesheetspans', component: 'enrol_apply', param: methods.length},
        {key: 'queuesheetmessage', component: 'enrol_apply'},
        {key: 'queuegrantsdecidingrole', component: 'enrol_apply'},
        ...mores.map((n) => ({key: 'queuesheetandmore', component: 'enrol_apply', param: n - NAMED})),
    ];
    const strings = await getStrings(ids);
    const [title, commitlabel, backlabel, danger, waitline, spansline, messagelabel, deciding] = strings;
    const more = {};
    mores.forEach((n, index) => {
        more[n] = strings[8 + index];
    });

    const body = document.createElement('div');
    if (action === 'cancel') {
        body.appendChild(sheetLine('div', 'alert alert-danger', danger));
    }
    if (action === 'wait') {
        body.appendChild(sheetLine('p', 'enrol_apply-sheetline', waitline));
    }
    if (spans) {
        body.appendChild(sheetLine('div', 'alert alert-warning', spansline));
        const words = {messagelabel, deciding, more};
        methods.forEach((method, index) => body.appendChild(methodSection(method, words, index)));
    } else {
        body.appendChild(whoList(rows, count > NAMED ? more[count] : ''));
    }

    // What the sheet hides inside the moved fields, so that closing it puts each back as it was.
    const hidden = [];
    const hide = (node) => {
        if (!node.hidden) {
            node.hidden = true;
            hidden.push(node);
        }
    };
    if (action !== 'confirm') {
        controls.querySelectorAll(SELECTORS.CONFIRMONLY).forEach(hide);
    }
    if (spans) {
        // Each section has its own box; the page's single one would reach every method.
        controls.querySelectorAll(SELECTORS.MESSAGE).forEach(hide);
    }

    const home = controls.parentNode;
    const modal = await Modal.create({
        title: escapeHtml(title),
        body: '',
        footer: '<button type="button" class="btn btn-secondary" data-action="hide">' + escapeHtml(backlabel) + '</button>'
            + '<button type="button" class="btn ' + (action === 'cancel' ? 'btn-danger' : 'btn-primary')
            + '" data-region="sheetcommit">' + escapeHtml(commitlabel) + '</button>',
        large: true,
        removeOnClose: true,
        returnElement: button,
    });
    modal.getRoot().addClass('enrol_apply-sheet');

    const sheetbody = modal.getBody()[0];
    sheetbody.appendChild(body);
    sheetbody.appendChild(controls);

    let restored = false;
    const restore = () => {
        if (restored) {
            return;
        }
        restored = true;
        sheetopen = false;
        home.appendChild(controls);
        hidden.forEach((node) => {
            node.hidden = false;
        });
    };
    modal.getRoot().on(ModalEvents.hidden, restore);

    modal.getFooter()[0].querySelector('[data-region="sheetcommit"]').addEventListener('click', () => {
        commit(form, action, body, restore);
    });

    modal.show();
};

/**
 * Post the decision the sheet was opened for, from the page's one form.
 *
 * The fields go home first, so they are inside the form when it posts; the sheet's message boxes,
 * if any, go into the form beside them. form.submit() posts no submitter, so the decision rides on
 * a hidden formaction written here: requestSubmit(button) would post the button's own, but it is
 * missing from browsers this plugin's versions still support, and a hidden input posts the same
 * contract everywhere.
 *
 * @param {HTMLFormElement} form The decision form.
 * @param {String} action confirm, wait or cancel.
 * @param {HTMLElement} body The sheet's own body, holding any per-method message boxes.
 * @param {Function} restore Puts the decision fields back into the form.
 * @return {void}
 */
const commit = (form, action, body, restore) => {
    restore();

    let holder = form.querySelector('[data-region="sheetmessages"]');
    if (!holder) {
        holder = document.createElement('div');
        holder.hidden = true;
        holder.dataset.region = 'sheetmessages';
        form.appendChild(holder);
    }
    holder.replaceChildren(...body.querySelectorAll('textarea[name^="outcomemessages["]'));

    let input = form.querySelector('input[type="hidden"][name="formaction"]');
    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'formaction';
        form.appendChild(input);
    }
    input.value = action;

    form.submit();
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

    /* No core_table/dynamic init() here: flexible_table::get_dynamic_table_html_end() already
       requires it for every dynamic table it renders, an empty one included. */
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

    /* The decision options live in the sheet once there is a script to open it; without one the
       details above the table holds them and the footer's buttons post them directly. */
    const options = document.querySelector(SELECTORS.OPTIONS);
    if (options) {
        options.hidden = true;
    }
    document.querySelectorAll(SELECTORS.DECIDE).forEach((button) => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            openSheet(button).catch((error) => {
                sheetopen = false;
                Notification.exception(error);
            });
        });
    });

    // Everything outside the refreshed region is reset here; see the module docblock.
    document.addEventListener(DynamicTableEvents.tableContentRefreshed, (e) => {
        setSelected(group, 0);
        redrawChips();
        /* The event's target is the new table root (core dispatches on it), so the count is read
           from the refreshed markup rather than from the detached node. */
        redrawCount(e.target);
        redrawShowing(e.target);
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

    /* The panel closes on Escape and gives the focus back to its control, as a menu does. Its
       open state is the details element's own, so it opens and closes without this module too. */
    const panel = filters.querySelector(SELECTORS.PANEL);
    if (panel) {
        panel.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && panel.open) {
                panel.open = false;
                panel.querySelector('summary').focus();
            }
        });
        /* A popover closes on a press outside it: open, it lies over the chip row and the top of
           the table. */
        document.addEventListener('click', (e) => {
            if (panel.open && !panel.contains(e.target)) {
                panel.open = false;
            }
        });
    }

    /* Delegated from the form rather than bound per chip, because the chips are replaced
       wholesale on every refresh and a handler bound to one goes with it. */
    filters.addEventListener('click', (e) => {
        /* A status value is a link to the same listing with that status; here it refreshes the
           table instead, like any other filter, with no debounce: a press is deliberate. */
        const cell = e.target.closest(SELECTORS.STATUSCELL);
        if (cell) {
            e.preventDefault();
            setStatus(cell.dataset.status);
            const tableRoot = document.querySelector(SELECTORS.TABLE);
            if (tableRoot) {
                applyFilters(tableRoot);
            }
            return;
        }

        const remove = e.target.closest(SELECTORS.CHIPREMOVE);
        if (remove) {
            e.preventDefault();
            removeFilter(remove.dataset.filter, remove.href);
            return;
        }

        const clearall = e.target.closest(SELECTORS.CLEARALL + ', ' + SELECTORS.CLEARFILTERS);
        if (clearall) {
            e.preventDefault();
            removeFilter(null, clearall.href);
        }
    });
};
