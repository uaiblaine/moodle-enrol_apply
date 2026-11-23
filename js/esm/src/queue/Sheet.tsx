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
 * The decision sheet: what the table's sheet (amd/src/manage.js, MQ-D5, MQ-D6) says and asks, as a
 * modal dialog of the view's own.
 *
 * Its title names the decision and the count, its list the first five applicants and how many more;
 * a cancellation says what cancelling removes, a deferral where the application stays, an approval
 * that every place is taken when the table page says so. Then the fields of the table's decision
 * controls, with their labels and help: for an approval the groups and the role where the props offer
 * them (a holder of enrol/apply:choosedecision) or the summary of what an approval gives, then the
 * message to the applicant and the note for the record, which every decision posts. The sheet's commit
 * button is the confirmation, worded as the table's ("Cancel 2"); Back, the close button and Escape close
 * it without posting anything, and so does a press outside while no field holds typed text or a
 * non-default choice (core's modal does not hide a dialogue that holds a form either).
 *
 * Bootstrap's modal markup, drawn by React rather than by Bootstrap's script, so Moodle's dialogue
 * locator finds it by its title. The focus moves to the title when the dialog opens and stays inside it
 * (Tab and Shift+Tab wrap); the view gives it back to the dock button that opened it.
 *
 * @module     enrol_apply/queue/Sheet
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useId, useRef, useState} from 'react';
import type {KeyboardEvent, ReactElement} from 'react';
import {fill, num} from '../datareport/str';
import type {Decision, DecisionProps, Row} from './types';

/** How many applicants the sheet names before "and N more", as the table's sheet does. */
const NAMED = 5;

/** What can take the focus inside the dialog. */
const FOCUSABLE = 'button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), a[href]';

/** What the sheet posts besides the decision and the selection. */
export type SheetValues = {
    message: string,
    note: string,
    /** The chosen groups, null where no group chooser was offered. */
    groups: number[] | null,
    /** The chosen role (0 for the method's), null where no role chooser was offered. */
    roleid: number | null,
};

type SheetProps = {
    labels: Record<string, string>,
    decision: Decision,
    /** The selected rows, in the page's order. */
    rows: Row[],
    offer: DecisionProps,
    onClose: () => void,
    onCommit: (values: SheetValues) => void,
};

/**
 * The title and the commit button's label of a decision, by the literal keys of each.
 *
 * @param {object} labels The strings.
 * @param {string} decision confirm, wait or cancel.
 * @param {number} count How many applications.
 * @returns {Array} The title and the commit label.
 */
const wording = (labels: Record<string, string>, decision: Decision, count: number): [string, string] => {
    const shown = num(count);
    if (decision === 'cancel') {
        return [fill(count === 1 ? labels.sheetcancelone : labels.sheetcancel, shown), fill(labels.sheetcancelcommit, shown)];
    }
    if (decision === 'wait') {
        return [fill(count === 1 ? labels.sheetwaitone : labels.sheetwait, shown), fill(labels.sheetwaitcommit, shown)];
    }

    return [fill(count === 1 ? labels.sheetconfirmone : labels.sheetconfirm, shown), fill(labels.sheetconfirmcommit, shown)];
};

/**
 * A field's help line, under its control and naming it for assistive technology.
 *
 * @param {object} props The help's id and text.
 * @returns {object} The line.
 */
const Help = ({id, text}: {id: string, text: string}): ReactElement => <div className="form-text" id={id}>{text}</div>;

/**
 * The sheet.
 *
 * @param {object} props See SheetProps.
 * @returns {object} The dialog over its backdrop.
 */
export const Sheet = ({labels, decision, rows, offer, onClose, onCommit}: SheetProps): ReactElement => {
    const id = useId();
    const dialog = useRef<HTMLDivElement | null>(null);
    const heading = useRef<HTMLHeadingElement | null>(null);
    const [message, setMessage] = useState('');
    const [note, setNote] = useState('');
    const [initialGroups] = useState<string[]>(
        () => (offer.groups?.options ?? []).filter((group) => group.selected).map((group) => String(group.id))
    );
    const [groups, setGroups] = useState<string[]>(initialGroups);
    const [roleid, setRoleid] = useState('0');
    const [sending, setSending] = useState(false);

    const count = rows.length;
    const [title, commit] = wording(labels, decision, count);
    const approval = decision === 'confirm';

    // The title takes the focus as the dialog opens, and the page behind it stops scrolling while it is open.
    useEffect(() => {
        heading.current?.focus();
        document.body.classList.add('modal-open');

        return () => document.body.classList.remove('modal-open');
    }, []);

    /**
     * Escape closes; Tab and Shift+Tab wrap inside the dialog.
     *
     * @param {object} event The keyboard event.
     * @returns {void}
     */
    const keydown = (event: KeyboardEvent<HTMLDivElement>): void => {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            if (!sending) {
                onClose();
            }
            return;
        }
        if (event.key !== 'Tab' || !dialog.current) {
            return;
        }
        const items = Array.from(dialog.current.querySelectorAll<HTMLElement>(FOCUSABLE));
        if (items.length === 0) {
            event.preventDefault();
            return;
        }
        const first = items[0];
        const last = items[items.length - 1];
        const at = document.activeElement;
        if (event.shiftKey && (at === first || at === heading.current)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (at === last || at === heading.current)) {
            event.preventDefault();
            first.focus();
        }
    };

    /**
     * Post the decision: once, whatever number of presses.
     *
     * @returns {void}
     */
    const submit = (): void => {
        if (sending) {
            return;
        }
        setSending(true);
        onCommit({
            message,
            note,
            groups: approval && offer.groups ? groups.map(Number) : null,
            roleid: approval && offer.roles ? Number(roleid) : null,
        });
    };

    // Typed text or a non-default choice: a press on the backdrop must not throw it away.
    const dirty = message !== '' || note !== '' || roleid !== '0'
        || groups.length !== initialGroups.length || groups.some((group) => !initialGroups.includes(group));

    const more = count - NAMED;

    return (
        <>
            <div className="modal-backdrop show" />
            <div
                className="modal show d-block enrol_apply-qview-sheet"
                role="presentation"
                onMouseDown={(event) => {
                    // A press on the dimmed page closes the dialog unless it holds input, as core's modals do.
                    if (event.target === event.currentTarget && !sending && !dirty) {
                        onClose();
                    }
                }}
            >
                <div className="modal-dialog modal-lg modal-dialog-scrollable">
                    <div
                        className="modal-content"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby={`${id}-title`}
                        tabIndex={-1}
                        ref={dialog}
                        onKeyDown={keydown}
                        data-region="enrol_apply-sheet"
                    >
                        <div className="modal-header">
                            <h5 className="modal-title" id={`${id}-title`} tabIndex={-1} ref={heading}>{title}</h5>
                            <button
                                type="button"
                                className="btn-close"
                                aria-label={labels.close}
                                onClick={onClose}
                                disabled={sending}
                            />
                        </div>
                        <div className="modal-body">
                            {decision === 'cancel' ? <div className="alert alert-danger">{labels.sheetcanceldanger}</div> : null}
                            {decision === 'wait' ? <p className="enrol_apply-qview-sheetline">{labels.sheetwaitline}</p> : null}
                            {approval && offer.placesfull !== '' ? (
                                <div className="alert alert-warning">{offer.placesfull}</div>
                            ) : null}
                            <ul className="enrol_apply-qview-sheetwho">
                                {rows.slice(0, NAMED).map((row) => (
                                    <li key={row.id}>
                                        {/* Decorative beside the name, as in the row: no link, no alternative text. */}
                                        <span
                                            className="enrol_apply-qview-picture"
                                            aria-hidden="true"
                                            dangerouslySetInnerHTML={{__html: row.applicant.picture}}
                                        />
                                        <span>{row.applicant.fullname}</span>
                                    </li>
                                ))}
                                {more > 0 ? (
                                    <li className="enrol_apply-qview-sheetmore">{fill(labels.andmore, num(more))}</li>
                                ) : null}
                            </ul>
                            {approval && offer.summary ? (
                                <div className="enrol_apply-qview-sheetsummary">
                                    <dl className="row mb-0">
                                        <dt className="col-sm-4">{offer.summary.groupslabel}</dt>
                                        <dd className="col-sm-8">{offer.summary.groups}</dd>
                                        <dt className="col-sm-4">{offer.summary.rolelabel}</dt>
                                        <dd className="col-sm-8">{offer.summary.role}</dd>
                                    </dl>
                                    <div className="form-text">
                                        {offer.summary.help}
                                        {offer.summary.note !== '' ? ' ' + offer.summary.note : ''}
                                    </div>
                                </div>
                            ) : null}
                            {approval && offer.groups ? (
                                <div className="mb-3">
                                    <label className="form-label" htmlFor={`${id}-groups`}>{offer.groups.label}</label>
                                    <select
                                        className="form-select"
                                        id={`${id}-groups`}
                                        multiple
                                        value={groups}
                                        aria-describedby={`${id}-groupshelp`}
                                        onChange={(event) => setGroups(
                                            Array.from(event.target.selectedOptions, (option) => option.value)
                                        )}
                                    >
                                        {offer.groups.options.map((group) => (
                                            <option key={group.id} value={String(group.id)}>{group.name}</option>
                                        ))}
                                    </select>
                                    <Help id={`${id}-groupshelp`} text={offer.groups.help} />
                                </div>
                            ) : null}
                            {approval && offer.roles ? (
                                <div className="mb-3">
                                    <label className="form-label" htmlFor={`${id}-role`}>{offer.roles.label}</label>
                                    <select
                                        className="form-select"
                                        id={`${id}-role`}
                                        value={roleid}
                                        aria-describedby={`${id}-rolehelp`}
                                        onChange={(event) => setRoleid(event.target.value)}
                                    >
                                        <option value="0">{offer.roles.default}</option>
                                        {offer.roles.options.map((role) => (
                                            <option key={role.id} value={String(role.id)}>{role.name}</option>
                                        ))}
                                    </select>
                                    <Help id={`${id}-rolehelp`} text={offer.roles.help} />
                                </div>
                            ) : null}
                            <div className="mb-3">
                                <label className="form-label" htmlFor={`${id}-message`}>{offer.message.label}</label>
                                <textarea
                                    className="form-control"
                                    id={`${id}-message`}
                                    rows={3}
                                    value={message}
                                    aria-describedby={`${id}-messagehelp`}
                                    onChange={(event) => setMessage(event.target.value)}
                                />
                                <Help id={`${id}-messagehelp`} text={offer.message.help} />
                            </div>
                            <div className="mb-0">
                                <label className="form-label" htmlFor={`${id}-note`}>{offer.note.label}</label>
                                <textarea
                                    className="form-control"
                                    id={`${id}-note`}
                                    rows={3}
                                    value={note}
                                    aria-describedby={`${id}-notehelp`}
                                    onChange={(event) => setNote(event.target.value)}
                                />
                                <Help id={`${id}-notehelp`} text={offer.note.help} />
                            </div>
                        </div>
                        <div className="modal-footer">
                            <button type="button" className="btn btn-secondary" onClick={onClose} disabled={sending}>
                                {labels.back}
                            </button>
                            <button
                                type="button"
                                className={'btn ' + (decision === 'cancel' ? 'btn-danger' : 'btn-primary')}
                                onClick={submit}
                                disabled={sending}
                            >
                                {commit}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
};
