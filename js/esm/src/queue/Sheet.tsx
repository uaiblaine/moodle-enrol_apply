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
 * that every place is taken when the table page says so. A selection on the site-wide queue that spans
 * enrolment methods is listed method by method instead, each with its own message box
 * (queue/SheetMethods, SQ-D10), and no group or role chooser is offered there. Then the fields of the table's decision
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
import type {Dispatch, KeyboardEvent, ReactElement, SetStateAction} from 'react';
import {fill, num} from '../datareport/str';
import {SheetMethods, WhoList, byMethod} from './SheetMethods';
import type {Decision, DecisionProps, Row} from './types';

/** What can take the focus inside the dialog. */
const FOCUSABLE = 'button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), a[href]';

/** What the sheet posts besides the decision and the selection. */
export type SheetValues = {
    message: string,
    note: string,
    /**
     * One message per method (its id => the text) when the selection spans methods, posted as
     * outcomemessages[]; null for a selection of one method, whose message is `message`.
     */
    messages: Record<number, string> | null,
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

type TextProps = {
    id: string,
    label: string,
    help: string,
    value: string,
    last: boolean,
    onChange: (text: string) => void,
};

/**
 * A labelled text box with its help: the message or the note.
 *
 * @param {object} props See TextProps.
 * @returns {object} The field.
 */
const TextField = ({id, label, help, value, last, onChange}: TextProps): ReactElement => (
    <div className={last ? 'mb-0' : 'mb-3'}>
        <label className="form-label" htmlFor={id}>{label}</label>
        <textarea
            className="form-control"
            id={id}
            rows={3}
            value={value}
            aria-describedby={`${id}help`}
            onChange={(event) => onChange(event.target.value)}
        />
        <Help id={`${id}help`} text={help} />
    </div>
);

/**
 * What the decision says before the list: what cancelling removes, where a deferral stays, that every place
 * of the method is taken.
 *
 * @param {object} props The strings, the decision and the offer.
 * @returns {object} The notices.
 */
const Notices = ({labels, decision, offer}: {labels: Record<string, string>, decision: Decision,
        offer: DecisionProps}): ReactElement => (
    <>
        {decision === 'cancel' ? <div className="alert alert-danger">{labels.sheetcanceldanger}</div> : null}
        {decision === 'wait' ? <p className="enrol_apply-qview-sheetline">{labels.sheetwaitline}</p> : null}
        {decision === 'confirm' && offer.placesfull !== '' ? (
            <div className="alert alert-warning">{offer.placesfull}</div>
        ) : null}
    </>
);

type ChooserProps = {
    id: string,
    offer: DecisionProps,
    groups: string[],
    setGroups: Dispatch<SetStateAction<string[]>>,
    roleid: string,
    setRoleid: Dispatch<SetStateAction<string>>,
};

/**
 * What an approval offers beside the message: the summary of what it gives a decider who may not choose,
 * or the group and role choosers where the table form offers them (never on the site-wide queue).
 *
 * @param {object} props See ChooserProps.
 * @returns {object} The summary or the choosers.
 */
const Choosers = ({id, offer, groups, setGroups, roleid, setRoleid}: ChooserProps): ReactElement => (
    <>
        {offer.summary ? (
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
        {offer.groups ? (
            <div className="mb-3">
                <label className="form-label" htmlFor={`${id}-groups`}>{offer.groups.label}</label>
                <select
                    className="form-select"
                    id={`${id}-groups`}
                    multiple
                    value={groups}
                    aria-describedby={`${id}-groupshelp`}
                    onChange={(event) => setGroups(Array.from(event.target.selectedOptions, (option) => option.value))}
                >
                    {offer.groups.options.map((group) => (
                        <option key={group.id} value={String(group.id)}>{group.name}</option>
                    ))}
                </select>
                <Help id={`${id}-groupshelp`} text={offer.groups.help} />
            </div>
        ) : null}
        {offer.roles ? (
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
    </>
);

/**
 * Whether anything typed or chosen would be lost by a press on the backdrop.
 *
 * @param {Array} texts The text boxes' values.
 * @param {string} roleid The chosen role.
 * @param {Array} groups The chosen groups.
 * @param {Array} initial The groups chosen when the sheet opened.
 * @returns {boolean} True when the sheet holds input.
 */
const holdsInput = (texts: string[], roleid: string, groups: string[], initial: string[]): boolean =>
    texts.some((text) => text !== '') || roleid !== '0'
    || groups.length !== initial.length || groups.some((group) => !initial.includes(group));

/**
 * Keep the focus inside the dialog: Tab from the last item goes to the first, Shift+Tab from the first to
 * the last, and either from the title enters the cycle.
 *
 * @param {object} event The keyboard event.
 * @param {HTMLElement|null} dialog The dialog.
 * @param {HTMLElement|null} heading Its title.
 * @returns {void}
 */
const wrapFocus = (event: KeyboardEvent<HTMLDivElement>, dialog: HTMLElement | null, heading: HTMLElement | null): void => {
    if (!dialog) {
        return;
    }
    const items = Array.from(dialog.querySelectorAll<HTMLElement>(FOCUSABLE));
    if (items.length === 0) {
        event.preventDefault();
        return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    const at = document.activeElement;
    if (event.shiftKey && (at === first || at === heading)) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && (at === last || at === heading)) {
        event.preventDefault();
        first.focus();
    }
};

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
    const [messages, setMessages] = useState<Record<number, string>>({});
    const [note, setNote] = useState('');
    const [initialGroups] = useState<string[]>(
        () => (offer.groups?.options ?? []).filter((group) => group.selected).map((group) => String(group.id))
    );
    const [groups, setGroups] = useState<string[]>(initialGroups);
    const [roleid, setRoleid] = useState('0');
    const [sending, setSending] = useState(false);

    const [title, commit] = wording(labels, decision, rows.length);
    const approval = decision === 'confirm';
    const methods = byMethod(rows);
    // A selection of several methods takes a message per method and, as on the table, no chooser (SQ-D10).
    const spans = methods.length > 1;

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
        if (event.key === 'Tab') {
            wrapFocus(event, dialog.current, heading.current);
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
        const choosers = approval && !spans;
        onCommit({
            message: spans ? '' : message,
            note,
            messages: spans ? Object.fromEntries(methods.map((method) => [method.id, messages[method.id] ?? ''])) : null,
            groups: choosers && offer.groups ? groups.map(Number) : null,
            roleid: choosers && offer.roles ? Number(roleid) : null,
        });
    };

    // Typed text or a non-default choice: a press on the backdrop must not throw it away.
    const dirty = holdsInput([message, note, ...Object.values(messages)], roleid, groups, initialGroups);

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
                            <Notices labels={labels} decision={decision} offer={offer} />
                            {spans ? (
                                <SheetMethods
                                    labels={labels}
                                    id={id}
                                    groups={methods}
                                    approval={approval}
                                    messages={messages}
                                    onMessage={(method, text) => setMessages((before) => ({...before, [method]: text}))}
                                />
                            ) : (
                                <>
                                    <WhoList labels={labels} rows={rows} />
                                    {approval ? (
                                        <Choosers
                                            id={id}
                                            offer={offer}
                                            groups={groups}
                                            setGroups={setGroups}
                                            roleid={roleid}
                                            setRoleid={setRoleid}
                                        />
                                    ) : null}
                                    <TextField
                                        id={`${id}-message`}
                                        label={offer.message.label}
                                        help={offer.message.help}
                                        value={message}
                                        last={false}
                                        onChange={setMessage}
                                    />
                                </>
                            )}
                            <TextField
                                id={`${id}-note`}
                                label={offer.note.label}
                                help={offer.note.help}
                                value={note}
                                last={true}
                                onChange={setNote}
                            />
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
