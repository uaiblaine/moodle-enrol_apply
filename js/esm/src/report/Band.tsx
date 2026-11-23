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
 * The summary band: how long applications waited, over the rows the reader's table lists.
 *
 * Tiles (median, 90th percentile, still open, decided without a person), the wait's distribution and
 * the median by method or by group (AR-D5, AR-D7: no breakdown by decider). A reader the separate
 * groups rule narrows gets the compact band, which the summary itself says (compact): the median, what
 * is still open and the decisions without a person (AR-D17). The measure switch (AR-D4) reloads this
 * region alone. "Still open" leads to the queue for a reader the queue admits (AR-D18), which the
 * props say by carrying its url.
 *
 * Every number is the server's (reportengine::summary()), over the same SQL as the rows.
 *
 * @module     enrol_apply/report/Band
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useState} from 'react';
import type {ReactElement} from 'react';
import {Segmented} from '../datareport/Segmented';
import {duration, fill, fillObject, num} from '../datareport/str';
import type {Breakdown, Measure, SummaryAnswer} from './types';

/** Groups shown before "All N groups" (section 5b of the study). */
const TOP_GROUPS = 8;

/** Days after which a wait is drawn in the warning tone, as the summary counts "late". */
const LATE_DAYS = 7;

type BandProps = {
    labels: Record<string, string>,
    summary: SummaryAnswer | null,
    loading: boolean,
    error: boolean,
    compactHint: boolean,
    measure: Measure,
    methods: number,
    queueurl: string,
    pendingstatus: number,
    onMeasure: (measure: Measure) => void,
    onRetry: () => void,
};

/**
 * One tile.
 *
 * @param {object} props The label, the value, the line under it, a warning tone and an optional link.
 * @returns {object} The tile.
 */
const Tile = ({label, value, sub, warn, link}: {label: string, value: string, sub: string, warn?: boolean,
        link?: ReactElement | null}): ReactElement => (
    <div className={'enrol_apply-rview-tile' + (warn ? ' enrol_apply-rview-tile--warn' : '')}>
        <div className="enrol_apply-rview-tile-label">{label}</div>
        <div className="enrol_apply-rview-tile-value">{value}</div>
        <div className="enrol_apply-rview-tile-sub">{sub}</div>
        {link ?? null}
    </div>
);

/**
 * One row of a bar list: label, bar, figure.
 *
 * @param {object} props The label, the bar's share (0 to 1), the figure, and the warning tone.
 * @returns {object} The row.
 */
const BarRow = ({label, share, figure, warn}: {label: string, share: number, figure: string, warn: boolean}): ReactElement => (
    <div className="enrol_apply-rview-hrow" role="listitem">
        <span className="enrol_apply-rview-hrow-label" title={label}>{label}</span>
        <span className="enrol_apply-rview-track" aria-hidden="true">
            <span
                className={'enrol_apply-rview-fill' + (warn ? ' enrol_apply-rview-fill--late' : '')}
                style={{width: `${Math.round(Math.min(1, Math.max(0, share)) * 100)}%`}}
            />
        </span>
        <span className="enrol_apply-rview-hrow-n">{figure}</span>
    </div>
);

/**
 * The band's skeleton, in the shape it will settle to.
 *
 * @param {object} props Whether the band is the compact one.
 * @returns {object} The skeleton.
 */
const BandSkeleton = ({compact}: {compact: boolean}): ReactElement => (
    <div aria-hidden="true">
        <div className="enrol_apply-rview-band">
            {Array.from({length: compact ? 3 : 4}, (_, i) => (
                <div key={i} className="enrol_apply-rview-tile enrol_apply-rview-sk">
                    <span className="enrol_apply-rview-skb enrol_apply-rview-skb--w60" />
                    <span className="enrol_apply-rview-skb enrol_apply-rview-skb--big" />
                    <span className="enrol_apply-rview-skb enrol_apply-rview-skb--w80" />
                </div>
            ))}
        </div>
        {compact ? null : (
            <div className="enrol_apply-rview-dist">
                {[0, 1].map((i) => (
                    <div key={i} className="enrol_apply-rview-panel enrol_apply-rview-sk">
                        <span className="enrol_apply-rview-skb enrol_apply-rview-skb--w40" />
                        <span className="enrol_apply-rview-skb" />
                        <span className="enrol_apply-rview-skb" />
                        <span className="enrol_apply-rview-skb" />
                    </div>
                ))}
            </div>
        )}
    </div>
);

/**
 * The median by method or by group, with the long list of groups capped and searched.
 *
 * @param {object} props The strings, the two breakdowns and how many methods the course has.
 * @returns {object} The panel.
 */
const BreakdownPanel = ({labels, bymethod, bygroup, methods}: {labels: Record<string, string>, bymethod: Breakdown[],
        bygroup: Breakdown[], methods: number}): ReactElement => {
    const [by, setBy] = useState<'method' | 'group'>(methods > 1 ? 'method' : 'group');
    const [all, setAll] = useState(false);
    const [query, setQuery] = useState('');
    const list = by === 'method' ? bymethod : bygroup;
    const most = Math.max(86400, ...list.map((entry) => entry.median ?? 0));

    /**
     * One breakdown row.
     *
     * @param {object} entry The method or group.
     * @returns {object} The row.
     */
    const row = (entry: Breakdown): ReactElement => (
        <BarRow
            key={entry.id}
            label={entry.name}
            share={(entry.median ?? 0) / most}
            figure={`${entry.median === null ? '–' : duration(labels, entry.median)} (${num(entry.count)})`}
            warn={(entry.median ?? 0) > LATE_DAYS * 86400}
        />
    );

    const bycount = [...list].sort((a, b) => b.count - a.count);
    const long = by === 'group' && list.length > TOP_GROUPS;
    const folded = query.trim().toLocaleLowerCase();
    const slowest = [...list]
        .filter((entry) => folded === '' || entry.name.toLocaleLowerCase().includes(folded))
        .sort((a, b) => (b.median ?? 0) - (a.median ?? 0));

    return (
        <div className="enrol_apply-rview-panel">
            <div className="enrol_apply-rview-panel-title">
                <span>{labels.medianby}</span>
                {methods > 1 ? (
                    <Segmented
                        label={labels.breakdown}
                        small
                        value={by}
                        options={[{value: 'method', label: labels.bymethod}, {value: 'group', label: labels.bygroup}]}
                        onChange={(value) => setBy(value as 'method' | 'group')}
                    />
                ) : <span>{labels.bygroup}</span>}
            </div>
            {long ? (
                <>
                    <div className="enrol_apply-rview-note">{fill(labels.topgroups, num(TOP_GROUPS))}</div>
                    <div role="list">{bycount.slice(0, TOP_GROUPS).map(row)}</div>
                    <button
                        type="button"
                        className="btn btn-link btn-sm enrol_apply-rview-linkbtn"
                        aria-expanded={all}
                        onClick={() => setAll(!all)}
                    >
                        {all ? labels.hide : fill(labels.allgroups, num(list.length))}
                    </button>
                    {all ? (
                        <>
                            <input
                                type="search"
                                className="form-control form-control-sm enrol_apply-rview-input"
                                placeholder={labels.searchgroup}
                                aria-label={labels.searchgroup}
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                            />
                            <div className="enrol_apply-rview-scrolllist" role="list">
                                {slowest.length
                                    ? slowest.map(row)
                                    : <p className="enrol_apply-rview-note">{labels.nogroupmatch}</p>}
                            </div>
                        </>
                    ) : null}
                </>
            ) : <div role="list">{bycount.map(row)}</div>}
        </div>
    );
};

/**
 * The band.
 *
 * @param {object} props See BandProps.
 * @returns {object} The band's region.
 */
export const Band = ({labels, summary, loading, error, compactHint, measure, methods, queueurl, pendingstatus, onMeasure,
        onRetry}: BandProps): ReactElement => {
    const head = (
        <div className="enrol_apply-rview-bandhead">
            <span className="enrol_apply-rview-bandtitle">{labels.turnaround}</span>
            <Segmented
                label={labels.measure}
                small
                value={measure}
                options={[{value: 'first', label: labels.measurefirst}, {value: 'resolution', label: labels.measureresolution}]}
                onChange={(value) => onMeasure(value as Measure)}
            />
        </div>
    );

    let body: ReactElement;
    if (summary === null && error) {
        body = (
            <p className="enrol_apply-rview-error">
                {labels.loaderror}{' '}
                <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onRetry}>{labels.retry}</button>
            </p>
        );
    } else if (summary === null) {
        body = (
            <>
                <BandSkeleton compact={compactHint} />
                <span className="visually-hidden" role="status">{labels.loadband}</span>
            </>
        );
    } else {
        const resolution = summary.measure === 'resolution';
        const median = summary.median ?? null;
        const p90 = summary.p90 ?? null;
        const oldest = summary.oldest ?? null;
        let opensub = labels.nothingopen;
        if (oldest !== null) {
            opensub = fill(labels.oldest, duration(labels, oldest))
                + (summary.late > 0 ? ' · ' + fill(labels.late, num(summary.late)) : '');
        }
        const queue = queueurl === '' ? null : (
            <a
                className="enrol_apply-rview-tile-link"
                href={resolution ? queueurl : `${queueurl}&status=${pendingstatus}`}
            >
                {labels.openqueue}
            </a>
        );
        const most = Math.max(1, ...summary.buckets.map((bucket) => bucket.count));
        body = (
            <div className="enrol_apply-rview-dim">
                <div className="enrol_apply-rview-band">
                    <Tile
                        label={resolution ? labels.medianresolution : labels.medianfirst}
                        value={median === null ? '–' : duration(labels, median)}
                        sub={fill(labels.mediansub, num(summary.decided))}
                    />
                    {summary.compact ? null : (
                        <Tile label={labels.p90} value={p90 === null ? '–' : duration(labels, p90)} sub={labels.p90sub} />
                    )}
                    <Tile label={labels.open} value={num(summary.open)} sub={opensub} warn={summary.late > 0} link={queue} />
                    <Tile label={labels.withoutperson} value={num(summary.withoutperson)} sub={labels.withoutpersonsub} />
                </div>
                {summary.compact ? null : (
                    <div className="enrol_apply-rview-dist">
                        <div className="enrol_apply-rview-panel">
                            <div className="enrol_apply-rview-panel-title">
                                <span>{resolution ? labels.distresolution : labels.distfirst}</span>
                                <span className="enrol_apply-rview-note">{fill(labels.decisions, num(summary.decided))}</span>
                            </div>
                            <div role="list">
                                {summary.buckets.map((bucket, index) => {
                                    const to = num(bucket.todays ?? 0);
                                    let label = fillObject(labels.bucket, {from: num(bucket.fromdays), to});
                                    if (index === 0) {
                                        label = fill(labels.bucketfirst, num(bucket.todays ?? 0));
                                    } else if ((bucket.todays ?? null) === null) {
                                        label = fill(labels.bucketlast, num(bucket.fromdays));
                                    }
                                    return (
                                        <BarRow
                                            key={bucket.fromdays}
                                            label={label}
                                            share={bucket.count / most}
                                            figure={num(bucket.count)}
                                            warn={bucket.fromdays >= LATE_DAYS}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                        <BreakdownPanel labels={labels} bymethod={summary.bymethod} bygroup={summary.bygroup} methods={methods} />
                    </div>
                )}
            </div>
        );
    }

    return (
        <section
            className="enrol_apply-rview-region enrol_apply-rview-bandregion"
            aria-label={labels.turnaround}
            aria-busy={loading}
            data-region="enrol_apply-band"
        >
            {loading && summary !== null ? <div className="enrol_apply-rview-progress" /> : null}
            {head}
            {body}
        </section>
    );
};
