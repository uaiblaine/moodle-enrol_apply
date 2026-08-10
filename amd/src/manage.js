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
 * Select-all handling for the enrolment application queue.
 *
 * The form submits through its own button, so this module is a progressive enhancement
 * only: with JavaScript disabled every checkbox is still operable one by one.
 *
 * @module     enrol_apply/manage
 * @copyright  2026 Anderson Blaine
 * @copyright  2016 sudile GbR (http://www.sudile.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    FORM: '#enrol_apply_manage_form',
    TOGGLE_ALL: '[data-action="toggleall"]',
    ROW_CHECKBOX: 'input[name="userenrolments[]"]',
};

/**
 * Wire the select-all checkbox to the per-row checkboxes.
 *
 * @return {void}
 */
export const init = () => {
    const form = document.querySelector(SELECTORS.FORM);
    if (!form) {
        return;
    }

    const toggleall = form.querySelector(SELECTORS.TOGGLE_ALL);
    if (!toggleall) {
        return;
    }

    const rows = () => Array.from(form.querySelectorAll(SELECTORS.ROW_CHECKBOX));

    toggleall.addEventListener('change', () => {
        rows().forEach((checkbox) => {
            checkbox.checked = toggleall.checked;
        });
    });

    form.addEventListener('change', (event) => {
        if (!event.target.matches(SELECTORS.ROW_CHECKBOX)) {
            return;
        }
        const all = rows();
        const checked = all.filter((checkbox) => checkbox.checked);
        toggleall.checked = all.length > 0 && checked.length === all.length;
        toggleall.indeterminate = checked.length > 0 && checked.length < all.length;
    });
};
