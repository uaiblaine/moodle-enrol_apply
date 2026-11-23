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
 * The queue's cells as the grid and the cards draw them, and the columns' widths.
 *
 * Two kinds of value arrive, and they are drawn differently on purpose. The cells (`cells[].html`) and
 * the picture are the server's formatted HTML, the masking and the escaping already applied there
 * (queueengine::row()), so they are inserted as HTML. Everything the service documents as plain - the
 * applicant's name, the identity values, the tags' words and titles - is drawn as text, never as HTML.
 *
 * @module     enrol_apply/queue/cells
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {Lines} from '../datareport/cells';
import {makeSizing} from '../datareport/sizing';
import {fill} from '../datareport/str';
import type {Row, Tag} from './types';

/** Default widths, in pixels, by column key; anything else (an answer, mostly) takes DEFAULT_WIDTH. */
const WIDTHS: Record<string, number> = {
    applicant: 320,
    wait: 150,
    group: 180,
    comment: 260,
    history: 130,
};

/** The width of a column the table above does not name. */
const DEFAULT_WIDTH = 180;

/** Minimum widths: the applicant 200 px, with its picture and its Review link; anything else 80 px. */
const MINIMUMS: Record<string, number> = {applicant: 200};

/** The narrowest any column may be dragged. */
const MINIMUM = 80;

/** The widest a column may be dragged; the server keeps up to 1600. */
const MAXIMUM = 800;

/** The queue's width policy. */
export const SIZING = makeSizing(WIDTHS, DEFAULT_WIDTH, MINIMUMS, MINIMUM, MAXIMUM);

/**
 * The status label's tone of each kind of tag; keep in step with applications::TAG_TONES.
 */
const TONES: Record<string, string> = {
    deferred: 'enrol_apply-status--warning',
    key: 'enrol_apply-status--info',
    appliedbefore: 'enrol_apply-status--neutral',
    own: 'enrol_apply-status--neutral',
};

/**
 * A row's cell for a column, as HTML; '' where the service gave none.
 *
 * @param {object} row The row.
 * @param {string} key The column key.
 * @returns {string} The cell's HTML.
 */
export const cellHtml = (row: Row, key: string): string => row.cells.find((cell) => cell.key === key)?.html ?? '';

/**
 * One cell's content: the server's HTML as one line, its whole text in its title.
 *
 * @param {object} props The cell's HTML.
 * @returns {object} The line.
 */
export const CellLine = ({html}: {html: string}): ReactElement => <Lines parts={[html]} />;

/**
 * One tag as a status label (status-label.md), the longer wording in its title and for assistive technology.
 *
 * @param {object} props The tag.
 * @returns {object} The label.
 */
const StatusLabel = ({tag}: {tag: Tag}): ReactElement => {
    const tone = TONES[tag.kind] ?? 'enrol_apply-status--neutral';
    const long = tag.title !== '' && tag.title !== tag.text;

    return (
        <span className={'enrol_apply-status ' + tone} title={long ? tag.title : undefined}>
            {tag.text}
            {long ? <span className="visually-hidden">{' ' + tag.title}</span> : null}
        </span>
    );
};

/**
 * The applicant's name, a link where the profile would open for the reader.
 *
 * @param {object} props The row.
 * @returns {object} The name.
 */
const Name = ({row}: {row: Row}): ReactElement => (
    row.applicant.profileurl === ''
        ? <span className="enrol_apply-qview-name">{row.applicant.fullname}</span>
        : <a className="enrol_apply-qview-name" href={row.applicant.profileurl}>{row.applicant.fullname}</a>
);

/**
 * The Review link to the row's review page, named for the applicant.
 *
 * @param {object} props The strings and the row.
 * @returns {object} The link.
 */
export const ReviewLink = ({labels, row}: {labels: Record<string, string>, row: Row}): ReactElement => (
    <a
        className="btn btn-sm btn-outline-secondary enrol_apply-rview-review"
        href={row.reviewurl}
        aria-label={fill(labels.reviewapplicant, row.applicant.fullname)}
    >
        {labels.review}
    </a>
);

/**
 * Who applied: the picture, the name, the identity values and the tags.
 *
 * @param {object} props The row.
 * @returns {object} The picture and the lines beside it.
 */
export const Who = ({row}: {row: Row}): ReactElement => (
    <>
        {/* Decorative beside the name: the server gives it no link and no alternative text. */}
        <span className="enrol_apply-qview-picture" aria-hidden="true" dangerouslySetInnerHTML={{__html: row.applicant.picture}} />
        <div className="enrol_apply-qview-whotext">
            <span className="enrol_apply-rview-c"><Name row={row} /></span>
            {row.applicant.identity.map((field) => (
                <span key={field.name} className="enrol_apply-rview-sub" title={field.value}>{field.value}</span>
            ))}
            {row.applicant.tags.length > 0 ? (
                <span className="enrol_apply-qview-tags">
                    {row.applicant.tags.map((tag) => <StatusLabel key={tag.kind} tag={tag} />)}
                </span>
            ) : null}
        </div>
    </>
);

/**
 * The lead column's cell: who applied, and the Review link.
 *
 * @param {object} props The strings and the row.
 * @returns {object} The cell's content.
 */
export const ApplicantCell = ({labels, row}: {labels: Record<string, string>, row: Row}): ReactElement => (
    <div className="enrol_apply-qview-who">
        <Who row={row} />
        <ReviewLink labels={labels} row={row} />
    </div>
);
