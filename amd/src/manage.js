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
 * The one thing core/checkbox-toggleall does not do for the applications queue.
 *
 * Selecting rows, selecting all of them and re-enabling the bulk action are core's job. What
 * core never does is set the action control's INITIAL state: checkbox-toggleall's init() binds
 * two delegated click handlers and nothing else, and setActionElementStates() is reached only
 * from those handlers, so a control is live until the first click.
 *
 * Every core caller closes that by hardcoding the disabled attribute in its server markup. This
 * plugin does not, and that is the one place it departs from core on purpose: the queue is
 * operable without JavaScript and was before the bar moved into a sticky footer, so an attribute
 * only JavaScript can clear would take a working path away from a no-JS operator to buy an
 * affordance only a JavaScript one can see. Doing it from here gives each audience what it can
 * use.
 *
 * That only holds because styles.css puts the footer back on screen when no script ran. Core
 * parks a sticky footer at "bottom: calc(<height> * -1)" and slides it in by adding a class from
 * theme_boost/sticky-footer.js, so without that rule the control this module leaves enabled
 * would be painted where nobody can see it - the departure from core would buy nothing and cost
 * the affordance. The two live together or not at all.
 *
 * @module     enrol_apply/manage
 * @copyright  2026 Anderson Blaine
 * @copyright  2016 sudile GbR (http://www.sudile.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    FORM: '#enrol_apply_manage_form',
    ACTION: '[data-toggle="action"][data-togglegroup="{group}"]',
};

/**
 * Disable the bulk action until something is selected.
 *
 * @param {String} group The checkbox-toggleall group the table and the bar share.
 * @return {void}
 */
export const init = (group) => {
    if (!document.querySelector(SELECTORS.FORM)) {
        return;
    }

    document.querySelectorAll(SELECTORS.ACTION.replace('{group}', group)).forEach((element) => {
        element.disabled = true;
    });
};
