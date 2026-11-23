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
 * The rows' region: what it holds while the rows load, fail, are none, or are there.
 *
 * The first load draws a skeleton of the grid or of the cards, with aria-busy and a status for
 * assistive technology; later loads keep the old rows, dimmed, under a 2 px progress line. A failed
 * read says so with a retry, above the old rows when there are some. No rows is the empty state, worded
 * for a filtered view or an empty one. The rows themselves (the view's grid or cards) are the children;
 * a dock the view keeps under them (a queue's decision bar) follows the region.
 *
 * Strings read from the view's labels: caption, loading, loaderror, retry, empty, nomatch, nomatchhelp,
 * scrollhint.
 *
 * @module     enrol_apply/datareport/Rows
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement, ReactNode} from 'react';
import {CardsSkeleton} from './Cards';
import {GridSkeleton} from './Grid';
import type {Column, Sizing} from './types';

type RowsRegionProps = {
    labels: Record<string, string>,
    /** Whether a read of the rows is in flight. */
    loading: boolean,
    /** Whether any answer has arrived: before it, the skeleton or the bare error. */
    loaded: boolean,
    error: boolean,
    /** The rows' total in the last answer. */
    total: number,
    filtered: boolean,
    cards: boolean,
    narrow: boolean,
    /** What the first skeleton draws, before the server has said which columns it shows. */
    skeleton: {columns: Column[], lead: string, widths: Record<string, number>, sizing: Sizing},
    dock?: ReactNode,
    children: ReactNode,
    onRetry: () => void,
};

/**
 * What the region holds: the first load's skeleton, an error with a retry, the empty state, or the rows.
 *
 * @param {object} props See RowsRegionProps.
 * @returns {object} The region's content.
 */
const RowsContent = ({labels, loaded, error, total, filtered, cards, narrow, skeleton, children,
        onRetry}: RowsRegionProps): ReactElement => {
    const retry = (
        <p className="enrol_apply-rview-error">
            {labels.loaderror}{' '}
            <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onRetry}>{labels.retry}</button>
        </p>
    );
    if (!loaded && error) {
        return retry;
    }
    if (!loaded) {
        return (
            <>
                {cards ? <CardsSkeleton single={narrow} /> : <GridSkeleton {...skeleton} />}
                <span className="visually-hidden" role="status">{labels.loading}</span>
            </>
        );
    }
    if (total === 0) {
        return (
            <div className="enrol_apply-rview-empty">
                <p className="enrol_apply-rview-empty-title">{filtered ? labels.nomatch : labels.empty}</p>
                {filtered ? <p className="enrol_apply-rview-empty-text">{labels.nomatchhelp}</p> : null}
            </div>
        );
    }

    return (
        <div className="enrol_apply-rview-dim">
            {error ? retry : null}
            {children}
            {cards ? null : <p className="enrol_apply-rview-hint">{labels.scrollhint}</p>}
        </div>
    );
};

/**
 * The region, and the dock under it.
 *
 * @param {object} props See RowsRegionProps.
 * @returns {object} The region.
 */
export const RowsRegion = (props: RowsRegionProps): ReactElement => (
    <>
        <section
            className="enrol_apply-rview-region"
            aria-label={props.labels.caption}
            aria-busy={props.loading}
            data-region="enrol_apply-rows"
        >
            {props.loading && props.loaded ? <div className="enrol_apply-rview-progress" /> : null}
            <RowsContent {...props} />
        </section>
        {props.dock ?? null}
    </>
);
