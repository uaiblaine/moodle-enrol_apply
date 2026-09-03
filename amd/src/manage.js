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
 * The three things core does not do for the applications queue.
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
 * @module     enrol_apply/manage
 * @copyright  2026 Anderson Blaine
 * @copyright  2016 sudile GbR (http://www.sudile.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {init as initDynamicTable} from 'core_table/dynamic';
import CheckboxToggleAll from 'core/checkbox-toggleall';
import DynamicTableEvents from 'core_table/local/dynamic/events';
import Notification from 'core/notification';
import {subscribe} from 'core/pubsub';
import {getString} from 'core/str';

const SELECTORS = {
    FORM: '#enrol_apply_manage_form',
    ACTION: '[data-toggle="action"][data-togglegroup="{group}"]',
    COUNT: '[data-region="selectedcount"]',
};

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
       carries no JavaScript, and the bar being reset is not in that region anyway. */
    document.addEventListener(DynamicTableEvents.tableContentRefreshed, () => {
        setSelected(group, 0);
    });
};
