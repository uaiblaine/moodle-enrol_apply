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
 * The filter panel (the Design System's filter-panel.md and counter.md): one group per facet.
 *
 * The panel knows facets, not where their values live: it is handed the definitions, the value each is
 * set to ('' for none) and a callback that a view turns into its own write (the course report rewrites
 * core's stored filters; a queue keeps its filters in the page's address). Each option carries the rows
 * it would list under every other filter in force; an option no row would list is hidden unless chosen.
 * A facet with more than eight options is a search over a list capped at fifty, the chosen one pinned
 * on top, with a grouping select when the facet has groupings (section 5b). A facet may carry a note of
 * its caller's, drawn under its options.
 *
 * Strings read from the view's labels: any, filters, clearfilters, show, showcount, grouping,
 * allgroupings, moregroups, moregroupsgrouping.
 *
 * @module     enrol_apply/datareport/FilterPanel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useId, useState} from 'react';
import type {KeyboardEvent, ReactElement, ReactNode} from 'react';
import {Icon} from './icons';
import {fill, num} from './str';
import type {FacetDefinition, FilterOption} from './types';

/** A facet with more options than this is searched (RP-D18, section 5b). */
const FEW = 8;

/** The most options a searched list draws at once (section 5b). */
const CAP = 50;

type PanelProps = {
    labels: Record<string, string>,
    id: string,
    hidden: boolean,
    facets: FacetDefinition[],
    values: Record<string, string>,
    total: number | null,
    note?: ReactNode,
    onChange: (name: string, value: string) => void,
    onClear: () => void,
    onShow: () => void,
};

/**
 * The panel's "Show" button: with the rows' count once it is known (filter-panel.md).
 *
 * @param {object} labels The strings.
 * @param {number|null} total The rows' total, or null before the first answer.
 * @returns {string} The label.
 */
const showLabel = (labels: Record<string, string>, total: number | null): string =>
    (total === null ? labels.show : fill(labels.showcount, num(total)));

/**
 * An option's quiet count (counter.md), or nothing where the panel has not counted.
 *
 * @param {object} props The option.
 * @returns {object|null} The count.
 */
const OptionCount = ({option}: {option: FilterOption}): ReactElement | null => (
    option.count >= 0 ? <small className="enrol_apply-rview-optcount">{num(option.count)}</small> : null
);

/**
 * The options a facet shows: every counted option but those no row would list, unless chosen (counter.md).
 *
 * @param {object} facet The facet.
 * @param {string} value The chosen option's value.
 * @returns {Array} The options.
 */
const shownOptions = (facet: FacetDefinition, value: string): FilterOption[] =>
    facet.options.filter((option) => option.count !== 0 || option.value === value);

type ListProps = {
    labels: Record<string, string>,
    facet: FacetDefinition,
    shown: FilterOption[],
    value: string,
    headid: string,
    onChange: (name: string, value: string) => void,
};

/**
 * A facet of many options as a searched list (filter-panel.md): the search, for the groups a grouping
 * select (RP-D23), the chosen option pinned on top, then at most fifty matches and a note of the rest.
 *
 * @param {object} props The strings, the facet, its options shown, the chosen value, the heading's id
 *     and the callback.
 * @returns {object} The field and the list.
 */
const SearchedList = ({labels, facet, shown, value, headid, onChange}: ListProps): ReactElement => {
    const [query, setQuery] = useState('');
    const [grouping, setGrouping] = useState('');
    const folded = query.trim().toLocaleLowerCase();
    const groups = facet.groupings.length > 1 ? facet.groupings : [];
    const inside = groups.find((entry) => String(entry.id) === grouping)?.groups.map(String) ?? null;
    const pinned = shown.filter((option) => option.value === value);
    const rest = shown.filter((option) => option.value !== value
        && (folded === '' || option.label.toLocaleLowerCase().includes(folded))
        && (inside === null || inside.includes(option.value)));
    const searchlabel = fill(facet.find.label, num(facet.options.length));
    const more = groups.length > 0 ? labels.moregroupsgrouping : labels.moregroups;

    return (
        <>
            <div className="enrol_apply-rview-find">
                <input
                    type="search"
                    className="form-control form-control-sm enrol_apply-rview-input"
                    placeholder={searchlabel}
                    aria-label={searchlabel}
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                />
                {groups.length > 0 ? (
                    <select
                        className="form-select form-select-sm enrol_apply-rview-grouping"
                        aria-label={labels.grouping}
                        value={grouping}
                        onChange={(event) => setGrouping(event.target.value)}
                    >
                        <option value="">{labels.allgroupings}</option>
                        {groups.map((entry) => <option key={entry.id} value={String(entry.id)}>{entry.name}</option>)}
                    </select>
                ) : null}
            </div>
            <div className="enrol_apply-rview-optlist" role="group" aria-labelledby={headid}>
                {[...pinned, ...rest.slice(0, CAP)].map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        className="enrol_apply-rview-optrow"
                        aria-pressed={option.value === value}
                        onClick={() => onChange(facet.name, option.value === value ? '' : option.value)}
                    >
                        <span className="enrol_apply-rview-box" aria-hidden="true"><Icon name="tick" /></span>
                        <span className="enrol_apply-rview-optrow-label">{option.label}</span>{' '}
                        <OptionCount option={option} />
                    </button>
                ))}
                {rest.length > CAP ? <p className="enrol_apply-rview-note">{fill(more, num(rest.length - CAP))}</p> : null}
                {pinned.length === 0 && rest.length === 0 ? <p className="enrol_apply-rview-note">{facet.find.nomatch}</p> : null}
            </div>
        </>
    );
};

/**
 * A caller's line under a facet's options, or nothing.
 *
 * @param {object} props The note, absent or '' for none.
 * @returns {object|null} The line.
 */
const FacetNote = ({note}: {note?: string}): ReactElement | null => (
    note ? <p className="enrol_apply-rview-note">{note}</p> : null
);

/**
 * One facet's group in the panel: a radio group of its options with their counts, or a searched list
 * above eight options.
 *
 * @param {object} props The strings, the facet, its value and the callback.
 * @returns {object} The group.
 */
const FacetGroup = ({labels, facet, value, onChange}: {labels: Record<string, string>, facet: FacetDefinition,
        value: string, onChange: (name: string, value: string) => void}): ReactElement => {
    const headid = useId();
    const shown = shownOptions(facet, value);
    const options: FilterOption[] = [{value: '', label: labels.any, count: -1}, ...shown];

    if (facet.options.length > FEW) {
        return (
            <div className="enrol_apply-rview-fgroup">
                <span className="enrol_apply-rview-fpanel-head" id={headid}>{facet.title}</span>
                <SearchedList labels={labels} facet={facet} shown={shown} value={value} headid={headid} onChange={onChange} />
                <FacetNote note={facet.note} />
            </div>
        );
    }

    /**
     * The arrow keys, Home and End in a radio group: move to another option and choose it.
     *
     * @param {object} event The keyboard event.
     * @returns {void}
     */
    const keydown = (event: KeyboardEvent<HTMLDivElement>): void => {
        const at = options.findIndex((option) => option.value === value);
        let next = -1;
        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
            next = (at + 1) % options.length;
        } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
            next = (at - 1 + options.length) % options.length;
        } else if (event.key === 'Home') {
            next = 0;
        } else if (event.key === 'End') {
            next = options.length - 1;
        }
        if (next < 0) {
            return;
        }
        event.preventDefault();
        onChange(facet.name, options[next].value);
        event.currentTarget.querySelectorAll<HTMLButtonElement>('[role="radio"]')[next]?.focus();
    };

    return (
        <div className="enrol_apply-rview-fgroup">
            <span className="enrol_apply-rview-fpanel-head" id={headid}>{facet.title}</span>
            <div className="enrol_apply-rview-fpanel-opts" role="radiogroup" aria-labelledby={headid} onKeyDown={keydown}>
                {options.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        className="enrol_apply-rview-fpanel-opt"
                        role="radio"
                        aria-checked={option.value === value}
                        tabIndex={option.value === value ? 0 : -1}
                        onClick={() => onChange(facet.name, option.value)}
                    >
                        {option.label}{' '}
                        <OptionCount option={option} />
                    </button>
                ))}
            </div>
            <FacetNote note={facet.note} />
        </div>
    );
};

/**
 * The panel: the facets, a view's note under them, and the foot with "Clear filters" and "Show".
 *
 * @param {object} props See PanelProps.
 * @returns {object} The dialog.
 */
export const FilterPanel = ({labels, id, hidden, facets, values, total, note, onChange, onClear,
        onShow}: PanelProps): ReactElement => (
    <div className="enrol_apply-rview-fpanel" id={id} role="dialog" aria-label={labels.filters} hidden={hidden}>
        <div className="enrol_apply-rview-fpanel-body">
            {facets.map((facet) => (
                <FacetGroup
                    key={facet.name}
                    labels={labels}
                    facet={facet}
                    value={values[facet.name] ?? ''}
                    onChange={onChange}
                />
            ))}
            {note ?? null}
        </div>
        <div className="enrol_apply-rview-fpanel-foot">
            <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onClear}>
                {labels.clearfilters}
            </button>
            <button type="button" className="btn btn-sm btn-primary" onClick={onShow}>
                {showLabel(labels, total)}
            </button>
        </div>
    </div>
);
