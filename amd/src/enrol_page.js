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
    keyStep: 'form[data-enrol-apply-step]',
    save: "[data-action='save']",
};

/**
 * Make the modal's own submit button follow the step of a key-only form.
 *
 * Step 1 asks for the key alone and moves on with Continue, a button inside the form; the footer's
 * submit would send the key as a real submission and arrive at step 2 with every required field in
 * error, so it is hidden there. Step 2 shows it again as "Enrol me". Hidden rather than disabled:
 * ModalForm re-enables every action button after each re-render. A form with no step (any other
 * mode) is left alone.
 *
 * Runs on every body ModalForm shows: the first load, the re-render after Continue and the one
 * after a server-side validation error all dispatch core/modal:bodyRendered.
 *
 * @param {CustomEvent} event The core/modal:bodyRendered event; its detail is the modal.
 */
const followKeyStep = (event) => {
    const modal = event.detail;
    const form = modal.getRoot()[0].querySelector(SELECTORS.keyStep);
    const save = modal.getFooter()[0].querySelector(SELECTORS.save);
    if (!form || !save) {
        return;
    }
    if (form.dataset.enrolApplyStep === '1') {
        save.hidden = true;
        return;
    }
    save.hidden = false;
    modal.setButtonText('save', getString('enrolme', 'core_enrol'));
};

/**
 * Wire the card's button to the modal form.
 *
 * @param {Number} instanceId Enrol instance the card belongs to.
 */
export function init(instanceId) {
    // The modal's title comes from the button's data-title; these are the footer's labels.
    prefetchStrings('enrol_apply', ['submitapplication']);
    prefetchStrings('core_enrol', ['enrolme']);

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

        // Registered before show(), so it is in place for the first body.
        modalForm.addEventListener('core/modal:bodyRendered', followKeyStep);

        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (event) => {
            // The form's process_dynamic_submission() returns the acknowledgement page's url, or the
            // enrolment page's after a refusal.
            window.location.href = event.detail;
        });

        modalForm.show();
    });
}
