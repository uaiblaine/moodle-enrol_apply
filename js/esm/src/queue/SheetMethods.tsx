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
 * The decision sheet's lists of who is decided: the whole selection's, and, when a selection on the
 * site-wide queue spans enrolment methods, one section per method (SQ-D10, the table's sheet in
 * amd/src/manage.js, methodSection()).
 *
 * A section names the course and the method (text), the method's labels (it grants a deciding role, it is
 * at its limit, with the warning for an approval there), how many of the selection are its and who, and a
 * message box for them, posted as outcomemessages[<the method's id>]; the id is the one the rows service
 * gave the row, and the server groups the posted rows by the method the database names whatever the key
 * says. Each section links to the method's own queue where the reader may open it, the one place groups
 * and a role are chosen.
 *
 * @module     enrol_apply/queue/SheetMethods
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {fill, num} from '../datareport/str';
import {MethodTags} from './cells';
import type {Row} from './types';

/** How many applicants a list names before "and N more", as the table's sheet does. */
const NAMED = 5;

/** The selected rows of one method, in the order they appear on the page. */
export type MethodRows = {id: number, rows: Row[]};

/**
 * The selection grouped by the method each row belongs to, in the order the methods first appear.
 *
 * @param {Array} rows The selected rows.
 * @returns {Array} One entry per method.
 */
export const byMethod = (rows: Row[]): MethodRows[] => {
    const groups: MethodRows[] = [];
    rows.forEach((row) => {
        const found = groups.find((group) => group.id === row.method.id);
        if (found) {
            found.rows.push(row);
        } else {
            groups.push({id: row.method.id, rows: [row]});
        }
    });

    return groups;
};

/**
 * Who is decided: the first five pictures and names, then how many more.
 *
 * @param {object} props The strings and the rows.
 * @returns {object} The list.
 */
export const WhoList = ({labels, rows}: {labels: Record<string, string>, rows: Row[]}): ReactElement => {
    const more = rows.length - NAMED;

    return (
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
            {more > 0 ? <li className="enrol_apply-qview-sheetmore">{fill(labels.andmore, num(more))}</li> : null}
        </ul>
    );
};

type SectionProps = {
    labels: Record<string, string>,
    id: string,
    group: MethodRows,
    approval: boolean,
    message: string,
    onMessage: (method: number, text: string) => void,
};

/**
 * One method's section.
 *
 * @param {object} props See SectionProps.
 * @returns {object} The section.
 */
const MethodSection = ({labels, id, group, approval, message, onMessage}: SectionProps): ReactElement => {
    const first = group.rows[0];
    const count = group.rows.length;

    return (
        <section className="enrol_apply-qview-sheetmethod" aria-labelledby={`${id}-head`}>
            <div className="enrol_apply-qview-sheetmethodhead" id={`${id}-head`}>
                <strong>{first.course.name}</strong>
                <span>{' · ' + first.method.name}</span>{' '}
                <MethodTags labels={labels} row={first} />
            </div>
            <div className="enrol_apply-qview-sheetline">
                {count === 1 ? labels.count_one : fill(labels.count, num(count))}
            </div>
            {approval && first.method.full ? <div className="alert alert-warning">{labels.methodfullwarn}</div> : null}
            <WhoList labels={labels} rows={group.rows} />
            <label className="form-label" htmlFor={`${id}-message`}>{labels.sheetmessage}</label>
            <textarea
                className="form-control"
                id={`${id}-message`}
                rows={2}
                value={message}
                aria-describedby={`${id}-head`}
                onChange={(event) => onMessage(group.id, event.target.value)}
            />
            {first.method.queueurl !== '' ? (
                <p className="enrol_apply-qview-sheetopen">
                    <a href={first.method.queueurl}>{labels.openmethod}</a>{' '}
                    <span className="enrol_apply-qview-sheetline">{labels.openmethodline}</span>
                </p>
            ) : null}
        </section>
    );
};

type MethodsProps = {
    labels: Record<string, string>,
    id: string,
    groups: MethodRows[],
    approval: boolean,
    messages: Record<number, string>,
    onMessage: (method: number, text: string) => void,
};

/**
 * The sheet's body for a selection that spans methods: the spans sentence, then a section per method.
 *
 * @param {object} props See MethodsProps.
 * @returns {object} The sections.
 */
export const SheetMethods = ({labels, id, groups, approval, messages, onMessage}: MethodsProps): ReactElement => (
    <>
        <div className="alert alert-info">{fill(labels.spans, num(groups.length))}</div>
        {groups.map((group, index) => (
            <MethodSection
                key={group.id}
                labels={labels}
                id={`${id}-method${index}`}
                group={group}
                approval={approval}
                message={messages[group.id] ?? ''}
                onMessage={onMessage}
            />
        ))}
    </>
);
