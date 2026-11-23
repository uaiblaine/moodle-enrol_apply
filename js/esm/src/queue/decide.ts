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
 * A decision is a real form posted to manage.php (CQ-D4), never a web service.
 *
 * The form carries the table form's own fields under the table form's own names - `sesskey`,
 * `formaction`, one `userenrolments[]` per selected row, `outcomemessage`, `decisionnote`, and for an
 * approval offered the choosers, `groups[]` and `roleid` - so manage.php reads it as it reads the
 * table's (decision::posted()), requires the sesskey and a POST, and decision::apply() runs every guard
 * it has, row by row; what the client selected or offered decides nothing on the server. The form posts
 * to the view's own address (queue/address.ts), which carries the scope, the filters, the search, the
 * sort and the page as the table form's action carries the table's filters; manage.php cleans them
 * again (queue_view::return_url()) and redirects there with its notifications, so the browser lands
 * on the grid it decided from.
 *
 * A selection on the site-wide queue that spans methods posts what the site-wide table's sheet posts
 * (amd/src/manage.js, commit()): the page's own `outcomemessage` empty, and one
 * `outcomemessages[<enrolid>]` per method, keyed by the method id the rows service gave the rows, which
 * decision::groups() uses only to look up a message for the rows the database says belong to that method;
 * and never a chooser, which that sheet does not offer (SQ-D10).
 *
 * @module     enrol_apply/queue/decide
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Decision} from './types';

/** What one decision posts. */
export type Posted = {
    /** The view's address: manage.php with the query in force. */
    action: string,
    sesskey: string,
    decision: Decision,
    userenrolments: number[],
    message: string,
    note: string,
    /** Method id => its applicants' message when the selection spans methods; null otherwise. */
    messages: Record<number, string> | null,
    /** The chosen groups, or null where the sheet offered no group chooser. */
    groups: number[] | null,
    /** The chosen role (0, the method's own), or null where the sheet offered no role chooser. */
    roleid: number | null,
};

/**
 * One hidden field of the form.
 *
 * @param {HTMLFormElement} form The form.
 * @param {string} name The field's name, as manage.php reads it.
 * @param {string} value Its value.
 * @returns {void}
 */
const field = (form: HTMLFormElement, name: string, value: string): void => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
};

/**
 * Build the form and submit it: the browser leaves the page for manage.php's answer.
 *
 * The choosers' fields go only with an approval of one method, the one decision that reads them
 * (wait_enrolment() and cancel_enrolment() read the note alone), and only where the sheet offered them, as
 * the table form posts them only where it renders them.
 *
 * @param {object} posted What to post; see Posted.
 * @returns {void}
 */
export const decide = (posted: Posted): void => {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = posted.action;
    form.hidden = true;

    field(form, 'sesskey', posted.sesskey);
    field(form, 'formaction', posted.decision);
    posted.userenrolments.forEach((id) => field(form, 'userenrolments[]', String(id)));
    field(form, 'outcomemessage', posted.messages === null ? posted.message : '');
    Object.entries(posted.messages ?? {}).forEach(([method, text]) => field(form, `outcomemessages[${method}]`, text));
    field(form, 'decisionnote', posted.note);
    if (posted.decision === 'confirm' && posted.messages === null) {
        posted.groups?.forEach((id) => field(form, 'groups[]', String(id)));
        if (posted.roleid !== null) {
            field(form, 'roleid', String(posted.roleid));
        }
    }

    document.body.appendChild(form);
    form.submit();
};
