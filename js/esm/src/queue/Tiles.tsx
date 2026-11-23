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
 * The queue's top tiles (CQ-D10): the method's capacity and the reader's queue, from enrol_apply_queue_summary.
 *
 * Places taken against the places, and applications held against the applicant limit, each only where
 * the method sets one, with a meter, as the table page's capacity panel shows them (and "on this method"
 * for a reader narrowed by separate groups, whose queue is not the method's); the applications awaiting
 * a decision, with the oldest wait and how many have waited longer than a week, in the warning tone when
 * any has; open or closed, with the closing date while open. The summary does not move with a filter,
 * so the tiles load once.
 *
 * The site-wide queue's tiles are its own (SQ-D7, {@link WideTiles}): what awaits, what is deferred and its
 * share of what awaits, and for a site-wide reader the courses (and methods) holding an application and the
 * methods at their limit.
 *
 * @module     enrol_apply/queue/Tiles
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {duration, fill, fillObject, num} from '../datareport/str';
import type {Kind, SummaryAnswer} from './types';

type TilesProps = {
    labels: Record<string, string>,
    /** Which queue: the method's draws its capacity, the queues that span courses their own figures. */
    kind: Kind,
    summary: SummaryAnswer | null,
    error: boolean,
    onRetry: () => void,
};

/**
 * A meter's width, as a whole percentage that never leaves its track (renderer::meter_percent()).
 *
 * @param {number} value The count.
 * @param {number} total The limit, above zero.
 * @returns {number} 0 to 100.
 */
const percent = (value: number, total: number): number => Math.min(100, Math.max(0, Math.round(value * 100 / total)));

/**
 * One tile.
 *
 * @param {object} props The label, the value, the line under it, the meter's width (null for none) and the tone.
 * @returns {object} The tile.
 */
const Tile = ({label, value, sub, meter, warn}: {label: string, value: string, sub: string, meter: number | null,
        warn: boolean}): ReactElement => (
    <div className={'enrol_apply-rview-tile' + (warn ? ' enrol_apply-rview-tile--warn' : '')} role="listitem">
        <div className="enrol_apply-rview-tile-label">{label}</div>
        <div className="enrol_apply-rview-tile-value">{value}</div>
        {meter !== null ? (
            <span className={'enrol_apply-qview-meter' + (warn ? ' enrol_apply-qview-meter--warn' : '')} aria-hidden="true">
                <span style={{width: `${meter}%`}} />
            </span>
        ) : null}
        {sub !== '' ? <div className="enrol_apply-rview-tile-sub">{sub}</div> : null}
    </div>
);

/**
 * A date as the page's language writes a short one.
 *
 * @param {number} time Unix time.
 * @returns {string} The date.
 */
const shortDate = (time: number): string => {
    const lang = document.documentElement.lang || undefined;
    try {
        return new Intl.DateTimeFormat(lang, {dateStyle: 'short'}).format(new Date(time * 1000));
    } catch {
        return new Date(time * 1000).toLocaleDateString();
    }
};

/**
 * The awaiting tile, the same on every queue: how many, the oldest wait and how many have waited long.
 *
 * @param {object} props The strings and the summary.
 * @returns {object} The tile.
 */
const AwaitingTile = ({labels, summary}: {labels: Record<string, string>, summary: SummaryAnswer}): ReactElement => {
    const oldest = summary.awaiting > 0 ? fill(labels.oldest, duration(labels, summary.oldestwait)) : '';
    const late = summary.late > 0 ? fill(labels.late, num(summary.late)) : '';

    return (
        <Tile
            label={labels.awaiting}
            value={num(summary.awaiting)}
            sub={[oldest, late].filter((part) => part !== '').join(' · ')}
            meter={null}
            warn={summary.late > 0}
        />
    );
};

/**
 * The tiles of the queues that span courses (SQ-D7): awaiting, deferred, and for a site-wide reader the
 * courses with applications and the methods at their limit. They do not follow the filters.
 *
 * @param {object} props The strings and the summary.
 * @returns {object} The band of tiles.
 */
const WideTiles = ({labels, summary}: {labels: Record<string, string>, summary: SummaryAnswer}): ReactElement => (
    <div className="enrol_apply-rview-band" role="list" data-region="enrol_apply-tiles">
        <AwaitingTile labels={labels} summary={summary} />
        <Tile
            label={labels.deferred}
            value={num(summary.deferred)}
            sub={summary.awaiting > 0 ? fill(labels.deferredshare, num(percent(summary.deferred, summary.awaiting))) : ''}
            meter={null}
            warn={false}
        />
        {summary.sitefigures ? (
            <>
                <Tile
                    label={labels.courses}
                    value={num(summary.courses)}
                    sub={summary.methods === 1 ? labels.methods_one : fill(labels.methods, num(summary.methods))}
                    meter={null}
                    warn={false}
                />
                <Tile
                    label={labels.methodsfull}
                    value={num(summary.methodsfull)}
                    sub={labels.methodsfullsub}
                    meter={null}
                    warn={summary.methodsfull > 0}
                />
            </>
        ) : null}
    </div>
);

/**
 * The tiles, their skeleton while the summary loads, or a retry when it failed.
 *
 * @param {object} props See TilesProps.
 * @returns {object} The band of tiles.
 */
export const Tiles = ({labels, kind, summary, error, onRetry}: TilesProps): ReactElement => {
    if (summary === null && error) {
        return (
            <p className="enrol_apply-rview-error">
                {labels.loaderror}{' '}
                <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onRetry}>{labels.retry}</button>
            </p>
        );
    }
    if (summary === null) {
        return (
            <div className="enrol_apply-rview-band" aria-busy="true">
                {Array.from({length: 4}, (_, i) => (
                    <div key={i} className="enrol_apply-rview-tile enrol_apply-rview-sk">
                        <span className="enrol_apply-rview-skb enrol_apply-rview-skb--w60" />
                        <span className="enrol_apply-rview-skb enrol_apply-rview-skb--big" />
                    </div>
                ))}
                <span className="visually-hidden" role="status">{labels.loadsummary}</span>
            </div>
        );
    }

    if (kind !== 'method') {
        return <WideTiles labels={labels} summary={summary} />;
    }

    const methodnote = summary.narrowed ? labels.methodnote : '';

    return (
        <div className="enrol_apply-rview-band" role="list" data-region="enrol_apply-tiles">
            {summary.places > 0 ? (
                <Tile
                    label={labels.placestaken}
                    value={fillObject(labels.ofmany, {taken: num(summary.placestaken), total: num(summary.places)})}
                    sub={methodnote}
                    meter={percent(summary.placestaken, summary.places)}
                    warn={false}
                />
            ) : null}
            {summary.applicantlimit > 0 ? (
                <Tile
                    label={labels.held}
                    value={fillObject(labels.ofmany, {taken: num(summary.applicants), total: num(summary.applicantlimit)})}
                    sub={methodnote}
                    meter={percent(summary.applicants, summary.applicantlimit)}
                    // Warned at four fifths, as the table page's panel, before the limit closes the method.
                    warn={summary.applicants * 5 >= summary.applicantlimit * 4}
                />
            ) : null}
            <AwaitingTile labels={labels} summary={summary} />
            <Tile
                label={labels.status}
                value={summary.open ? labels.open : labels.closed}
                sub={summary.open && summary.closes > 0 ? fill(labels.closes, shortDate(summary.closes)) : ''}
                meter={null}
                warn={false}
            />
        </div>
    );
};
