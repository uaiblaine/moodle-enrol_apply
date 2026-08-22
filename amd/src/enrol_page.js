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
 * Opens the application form in a modal from the course enrolment page.
 *
 * Progressive enhancement over a real link: the button already points at apply.php, which
 * renders the same form class on a page of its own, so a browser that never runs this module
 * still reaches the form.
 *
 * @module     enrol_apply/enrol_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {getString} from 'core/str';
import {prefetchStrings} from 'core/prefetch';

const SELECTORS = {
    button: (instanceId) => `[data-instance="${instanceId}"][data-form]`,
};

/**
 * Wire the card's button to the modal form.
 *
 * @param {Number} instanceId Enrol instance the card belongs to.
 */
export function init(instanceId) {
    prefetchStrings('enrol_apply', ['submitapplication', 'checkyourdetails']);

    const button = document.querySelector(SELECTORS.button(instanceId));
    if (!button) {
        return;
    }

    button.addEventListener('click', (e) => {
        e.preventDefault();

        const modalForm = new ModalForm({
            modalConfig: {
                title: button.dataset.title,
                large: true,
            },
            formClass: button.dataset.form,
            args: {id: button.dataset.id, instance: instanceId},
            saveButtonText: getString('submitapplication', 'enrol_apply'),
            returnFocus: button,
        });

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
            // The form's process_dynamic_submission() returns the acknowledgement page's url.
            window.location.href = event.detail;
        });

        modalForm.show();
    });
}
