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
 * The filter bar above the rows (the Design System's filter-bar.md, chip.md, menu.md, filter-panel.md).
 *
 * The scope (all, awaiting, decided), the count, a capsule of icon buttons - Filters with its loud
 * counter, Columns, View, Download - and the search by name in a capsule of its own, folded to its
 * button until pressed or `/` is typed; the chips of what is in force on a row of their own, then
 * "Clear all". One layer is open at a time; Escape or a press outside closes it and gives the focus
 * back to its button. Each menu hangs under its own button, the panel under the row of controls (never
 * under the chips); at the narrow step (the view's container under 576 px) the menus hang from the
 * controls' end, the scope takes a line of its own after them, and the open search takes the line.
 *
 * The open search: Escape closes it whatever it holds, the text staying in force, and gives the focus
 * back to its button; leaving it empty closes it too; the eraser clears the text and keeps it open. The
 * text in force shows as a chip only while the field is closed, so it is never said twice.
 *
 * Filters draws the report's facets (core's store, through core_reportbuilder_set_filters): the filters
 * that pick one option of a list, "Waited more than" for the wait the band measures and "Decided by",
 * each option with the rows it would list under every other filter in force, the scope and the search
 * (the rows answer counts them; an option no row would list is hidden unless chosen). A facet with more
 * than eight options is a search over a list capped at fifty, the chosen one pinned on top, the group
 * facet's with a grouping select (section 5b). Columns starts with three sets (Decisions, Answers,
 * Groups), each written as the reader's choice like a column change, then lists the report's catalogue
 * for this reader, the applicant and the identity fields always shown, and ends with "Reset column
 * widths". View is Automatic, Table or Cards, and core's own table view. Download links core's download
 * of the report with the measure, the scope and the search in force, which the report applies itself.
 *
 * @module     enrol_apply/report/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useId, useRef, useState} from 'react';
import type {KeyboardEvent, ReactElement} from 'react';
import {choosable, chosen, chosenLabel} from './filters';
import {Icon} from './icons';
import {Segmented} from './Segmented';
import {fill, fillCount, fillObject, num} from './str';
import type {Column, Filter, FilterOption, Format, Measure, Preset, Scope, ViewMode} from './types';

/** A facet with more options than this is searched (RP-D18, section 5b). */
const FEW = 8;

/** The most options a searched list draws at once (section 5b). */
const CAP = 50;

type Layer = 'filters' | 'columns' | 'view' | 'download' | null;

type BarProps = {
    labels: Record<string, string>,
    narrow: boolean,
    scope: Scope,
    total: number | null,
    filters: Filter[],
    columns: Column[],
    presets: Preset[],
    usedefault: boolean,
    haswidths: boolean,
    view: ViewMode,
    measure: Measure,
    search: string,
    appliedsearch: string,
    searchmin: number,
    searching: boolean,
    tableurl: string,
    downloadurl: string,
    formats: Format[],
    onScope: (scope: Scope) => void,
    onSearch: (text: string) => void,
    onFilter: (filter: Filter, value: string) => void,
    onRemoveFilter: (filter: Filter) => void,
    onClearFilters: () => void,
    onClearAll: () => void,
    onColumn: (key: string, on: boolean) => void,
    onPreset: (preset: Preset) => void,
    onDefaultColumns: () => void,
    onResetWidths: () => void,
    onView: (view: ViewMode) => void,
};

/**
 * The heading a facet is drawn under: the panel's own words for the wait and the decider, whose filters
 * are named for core's form.
 *
 * @param {object} labels The strings.
 * @param {object} filter The filter.
 * @returns {string} The heading.
 */
const facetTitle = (labels: Record<string, string>, filter: Filter): string => {
    if (filter.facet === 'waited') {
        return labels.waitedmore;
    }

    return filter.facet === 'decider' ? labels.decidedby : filter.title;
};

/**
 * A Columns set's name, by the literal key of each (a label is never looked up by a computed name).
 *
 * @param {object} labels The strings.
 * @param {string} name The set's name.
 * @returns {string} Its label.
 */
const presetLabel = (labels: Record<string, string>, name: string): string => {
    switch (name) {
        case 'decisions':
            return labels.presetdecisions;
        case 'answers':
            return labels.presetanswers;
        case 'groups':
            return labels.presetgroups;
        default:
            return name;
    }
};

/**
 * The report parameters of the download link: the rows in view, as the report applies the scope and the
 * search from its own parameters (course_applications::initialise()).
 *
 * @param {string} measure What the wait measures.
 * @param {string} scope Which rows.
 * @param {string} search The name search in force, or ''.
 * @returns {string} The parameters, JSON, encoded for a url.
 */
const downloadParameters = (measure: Measure, scope: Scope, search: string): string => {
    const parameters: Record<string, string> = {measure};
    if (scope !== 'all') {
        parameters.scope = scope;
    }
    if (search !== '') {
        parameters.search = search;
    }

    return encodeURIComponent(JSON.stringify(parameters));
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
 * The Columns menu's sets, under their heading and before a separator: menuitemcheckbox items, the set
 * in force checked (menu.md); a press on another writes it as the reader's choice.
 *
 * @param {object} props The strings, the sets and the callback.
 * @returns {object|null} The items, or nothing without sets.
 */
const PresetItems = ({labels, presets, onPreset}: {labels: Record<string, string>, presets: Preset[],
        onPreset: (preset: Preset) => void}): ReactElement | null => {
    if (presets.length === 0) {
        return null;
    }

    return (
        <>
            <div className="enrol_apply-rview-menu-head" role="presentation">{labels.presets}</div>
            {presets.map((preset) => (
                <button
                    key={preset.name}
                    type="button"
                    className="enrol_apply-rview-menu-item"
                    role="menuitemcheckbox"
                    aria-checked={preset.active}
                    tabIndex={-1}
                    data-preset={preset.name}
                    onClick={() => {
                        if (!preset.active) {
                            onPreset(preset);
                        }
                    }}
                >
                    <span className="enrol_apply-rview-box" aria-hidden="true"><Icon name="tick" /></span>
                    <span className="enrol_apply-rview-menu-label">{presetLabel(labels, preset.name)}</span>
                </button>
            ))}
            <div className="enrol_apply-rview-menu-sep" role="separator" />
        </>
    );
};

/**
 * Move the focus between a menu's items with the arrow keys, Home and End.
 *
 * @param {object} event The keyboard event, on the menu.
 * @returns {void}
 */
const menuKeys = (event: KeyboardEvent<HTMLDivElement>): void => {
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
        return;
    }
    const items = Array.from(event.currentTarget.querySelectorAll<HTMLElement>('[role^="menuitem"]'));
    if (items.length === 0) {
        return;
    }
    event.preventDefault();
    const at = items.indexOf(document.activeElement as HTMLElement);
    let next = 0;
    if (event.key === 'End') {
        next = items.length - 1;
    } else if (event.key === 'ArrowDown') {
        next = (at + 1) % items.length;
    } else if (event.key === 'ArrowUp') {
        next = (at - 1 + items.length) % items.length;
    }
    items[next].focus();
};

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
 * @param {object} filter The filter.
 * @param {string} value The chosen option's value.
 * @returns {Array} The options.
 */
const shownOptions = (filter: Filter, value: string): FilterOption[] =>
    filter.options.filter((option) => option.count !== 0 || option.value === value);

/**
 * A facet of many options as a searched list (filter-panel.md): the search, for the groups a grouping
 * select (RP-D23), the chosen option pinned on top, then at most fifty matches and a note of the rest.
 *
 * @param {object} props The strings, the filter, its options shown, the chosen value, the heading's id
 *     and the callback.
 * @returns {object} The field and the list.
 */
const SearchedList = ({labels, filter, shown, value, headid, onFilter}: {labels: Record<string, string>, filter: Filter,
        shown: FilterOption[], value: string, headid: string, onFilter: (filter: Filter, value: string) => void}): ReactElement => {
    const [query, setQuery] = useState('');
    const [grouping, setGrouping] = useState('');
    const folded = query.trim().toLocaleLowerCase();
    const groups = filter.groupings.length > 1 ? filter.groupings : [];
    const inside = groups.find((entry) => String(entry.id) === grouping)?.groups.map(String) ?? null;
    const pinned = shown.filter((option) => option.value === value);
    const rest = shown.filter((option) => option.value !== value
        && (folded === '' || option.label.toLocaleLowerCase().includes(folded))
        && (inside === null || inside.includes(option.value)));
    const decider = filter.facet === 'decider';
    const searchlabel = fill(decider ? labels.searchdeciders : labels.searchgroups, num(filter.options.length));
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
                        onClick={() => onFilter(filter, option.value === value ? '' : option.value)}
                    >
                        <span className="enrol_apply-rview-box" aria-hidden="true"><Icon name="tick" /></span>
                        <span className="enrol_apply-rview-optrow-label">{option.label}</span>{' '}
                        <OptionCount option={option} />
                    </button>
                ))}
                {rest.length > CAP ? <p className="enrol_apply-rview-note">{fill(more, num(rest.length - CAP))}</p> : null}
                {pinned.length === 0 && rest.length === 0
                    ? <p className="enrol_apply-rview-note">{decider ? labels.nodecidermatch : labels.nogroupmatch}</p>
                    : null}
            </div>
        </>
    );
};

/**
 * One facet's group in the panel: a radio group of its options with their counts, or a searched list
 * above eight options.
 *
 * @param {object} props The strings, the filter, its heading and the callback.
 * @returns {object} The group.
 */
const FilterGroup = ({labels, filter, title, onFilter}: {labels: Record<string, string>, filter: Filter, title: string,
        onFilter: (filter: Filter, value: string) => void}): ReactElement => {
    const headid = useId();
    const value = chosen(filter);
    const shown = shownOptions(filter, value);
    const options: FilterOption[] = [{value: '', label: labels.any, count: -1}, ...shown];

    if (filter.options.length > FEW) {
        return (
            <div className="enrol_apply-rview-fgroup">
                <span className="enrol_apply-rview-fpanel-head" id={headid}>{title}</span>
                <SearchedList labels={labels} filter={filter} shown={shown} value={value} headid={headid} onFilter={onFilter} />
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
        onFilter(filter, options[next].value);
        event.currentTarget.querySelectorAll<HTMLButtonElement>('[role="radio"]')[next]?.focus();
    };

    return (
        <div className="enrol_apply-rview-fgroup">
            <span className="enrol_apply-rview-fpanel-head" id={headid}>{title}</span>
            <div className="enrol_apply-rview-fpanel-opts" role="radiogroup" aria-labelledby={headid} onKeyDown={keydown}>
                {options.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        className="enrol_apply-rview-fpanel-opt"
                        role="radio"
                        aria-checked={option.value === value}
                        tabIndex={option.value === value ? 0 : -1}
                        onClick={() => onFilter(filter, option.value)}
                    >
                        {option.label}{' '}
                        <OptionCount option={option} />
                    </button>
                ))}
            </div>
        </div>
    );
};

/**
 * The bar.
 *
 * @param {object} props See BarProps.
 * @returns {object} The bar.
 */
export const Bar = (props: BarProps): ReactElement => {
    const {labels, filters, columns} = props;
    const uid = useId();
    const root = useRef<HTMLDivElement | null>(null);
    const searchinput = useRef<HTMLInputElement | null>(null);
    const searchbutton = useRef<HTMLButtonElement | null>(null);
    const refocus = useRef(false);
    const [layer, setLayer] = useState<Layer>(null);
    const [searchopen, setSearchopen] = useState(props.search !== '');
    const triggers = useRef<Record<string, HTMLButtonElement | null>>({});

    // A press outside the bar closes the open layer.
    useEffect(() => {
        if (layer === null) {
            return undefined;
        }
        /**
         * Close the layer on a press outside the bar.
         *
         * @param {object} event The pointer event.
         * @returns {void}
         */
        const outside = (event: MouseEvent): void => {
            if (root.current && !root.current.contains(event.target as Node)) {
                setLayer(null);
            }
        };
        document.addEventListener('mousedown', outside);

        return () => document.removeEventListener('mousedown', outside);
    }, [layer]);

    // The open menu takes the focus on its chosen item, or its first.
    useEffect(() => {
        if (layer === null || layer === 'filters') {
            return;
        }
        const menu = root.current?.querySelector<HTMLElement>(`#${CSS.escape(uid + layer)}`);
        const item = menu?.querySelector<HTMLElement>('[aria-checked="true"]:not([aria-disabled="true"])')
            ?? menu?.querySelector<HTMLElement>('[role^="menuitem"]:not([aria-disabled="true"])');
        item?.focus();
    }, [layer, uid]);

    // The opened field takes the focus; the field closed by Escape gives it back to the search button.
    useEffect(() => {
        if (searchopen) {
            searchinput.current?.focus();
        } else if (refocus.current) {
            refocus.current = false;
            searchbutton.current?.focus();
        }
    }, [searchopen]);

    // Typing `/` anywhere but in a field opens the search, or moves the focus into the open one, while the
    // bar is on screen.
    useEffect(() => {
        /**
         * Open or focus the search on `/`.
         *
         * @param {object} event The keyboard event, on the document.
         * @returns {void}
         */
        const slash = (event: globalThis.KeyboardEvent): void => {
            if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented) {
                return;
            }
            const focused = document.activeElement as HTMLElement | null;
            if (focused && (focused.isContentEditable || focused.closest('input, textarea, select'))) {
                return;
            }
            const rect = root.current?.getBoundingClientRect();
            if (!rect || rect.width === 0 || rect.bottom <= 0 || rect.top >= window.innerHeight) {
                return;
            }
            event.preventDefault();
            if (searchopen) {
                searchinput.current?.focus();
            } else {
                setLayer(null);
                setSearchopen(true);
            }
        };
        document.addEventListener('keydown', slash);

        return () => document.removeEventListener('keydown', slash);
    }, [searchopen]);

    /**
     * Open a layer, or close it when it is the open one.
     *
     * @param {string} which The layer.
     * @returns {void}
     */
    const toggle = (which: Layer): void => setLayer(layer === which ? null : which);

    /**
     * Close the search field, leaving its text in force.
     *
     * @param {boolean} back Whether the focus goes back to the search button.
     * @returns {void}
     */
    const closeSearch = (back: boolean): void => {
        refocus.current = back;
        setSearchopen(false);
    };

    /**
     * Escape closes the open layer and gives the focus back to its button; with no layer open, it closes
     * the open search from any control of the bar (the field handles its own Escape first).
     *
     * @param {object} event The keyboard event.
     * @returns {void}
     */
    const escape = (event: KeyboardEvent<HTMLDivElement>): void => {
        if (event.key !== 'Escape' || event.defaultPrevented) {
            return;
        }
        if (layer !== null) {
            event.preventDefault();
            const trigger = triggers.current[layer];
            setLayer(null);
            trigger?.focus();
        } else if (searchopen) {
            event.preventDefault();
            closeSearch(true);
        }
    };

    const applied = filters.filter((filter) => filter.applied);
    const searched = props.search.trim().length >= props.searchmin;
    // The text in force is a chip once the field is closed; while it is open, the field says it.
    const searchchip = searched && !searchopen;
    const chipcount = applied.length + (searchchip ? 1 : 0);

    // At the narrow step the visible count is the bare number; the full sentence stays for assistive technology.
    const fullcount = props.total === null ? '…' : fillCount(labels, 'count', props.total, num(props.total));
    const countText = props.total !== null && props.narrow ? (
        <>
            <span aria-hidden="true">{num(props.total)}</span>
            <span className="visually-hidden">{fullcount}</span>
        </>
    ) : fullcount;
    // The wait the panel draws is the one the band measures; the other stays in core's table view.
    const waited = props.measure === 'resolution' ? 'submission:resolutiontime' : 'submission:waittime';
    /**
     * Whether the panel draws a filter.
     *
     * @param {object} filter The filter.
     * @returns {boolean} True for a facet with options, a wait only for the measure's.
     */
    const inpanel = (filter: Filter): boolean => choosable(filter) && (filter.facet !== 'waited' || filter.name === waited);
    const panelfilters = filters.filter(inpanel);
    const others = filters.filter((filter) => !inpanel(filter));

    /**
     * One icon button of the capsule.
     *
     * @param {string} which The layer it opens.
     * @param {string} icon The icon's name.
     * @param {string} name The button's accessible name.
     * @param {object|null} extra What follows the icon: a chevron or the loud counter.
     * @returns {object} The button.
     */
    const iconbutton = (which: Layer, icon: string, name: string, extra: ReactElement | null): ReactElement => (
        <button
            type="button"
            className="enrol_apply-rview-iconbtn"
            aria-label={name}
            data-enrol-apply-tip={name}
            aria-haspopup={which === 'filters' ? 'dialog' : 'menu'}
            aria-expanded={layer === which}
            aria-controls={uid + which}
            ref={(element) => {
                triggers.current[which ?? ''] = element;
            }}
            onClick={() => toggle(which)}
        >
            <Icon name={icon} />
            {extra}
        </button>
    );

    const viewicon = {auto: 'auto', table: 'table', cards: 'cards'}[props.view];
    const chevron = <Icon name="chevron" className="enrol_apply-rview-chevron" />;
    const filtersname = applied.length > 0 ? fill(labels.filterson, num(applied.length)) : labels.filters;
    const badge = applied.length > 0
        ? <span className="enrol_apply-rview-badge" aria-hidden="true">{num(applied.length)}</span>
        : null;
    const download = props.downloadurl !== '' && props.formats.length > 0;
    const parameters = downloadParameters(props.measure, props.scope, props.appliedsearch);
    const barclass = 'enrol_apply-rview-bar'
        + (props.narrow ? ' enrol_apply-rview-bar--narrow' : '')
        + (searchopen ? ' enrol_apply-rview-bar--searching' : '');

    return (
        <div className={barclass} data-region="enrol_apply-bar" ref={root} onKeyDown={escape}>
            <Segmented
                label={labels.scope}
                value={props.scope}
                options={[
                    {value: 'all', label: labels.all},
                    {value: 'awaiting', label: labels.awaiting},
                    {value: 'decided', label: labels.decided},
                ]}
                onChange={(value) => props.onScope(value as Scope)}
            />
            <span className="enrol_apply-rview-bar-spacer" />
            {/* The row of controls: the panel and, at the narrow step, the menus hang under it. */}
            <div className="enrol_apply-rview-bar-controls">
                <div className="enrol_apply-rview-bar-cluster">
                    <span className="enrol_apply-rview-bar-count" data-region="enrol_apply-total" aria-live="polite">
                        {countText}
                    </span>
                    <div className="enrol_apply-rview-capsule">
                        {iconbutton('filters', 'filter', filtersname, badge)}
                        <div className="enrol_apply-rview-anchor">
                            {iconbutton('columns', 'columns', labels.columns, chevron)}
                            <div
                                className="enrol_apply-rview-menu"
                                id={uid + 'columns'}
                                role="menu"
                                aria-label={labels.columns}
                                hidden={layer !== 'columns'}
                                onKeyDown={menuKeys}
                            >
                                <PresetItems labels={labels} presets={props.presets} onPreset={props.onPreset} />
                                {columns.map((column) => (
                                    column.fixed ? (
                                        <div
                                            key={column.key}
                                            className="enrol_apply-rview-menu-item"
                                            role="menuitemcheckbox"
                                            aria-checked="true"
                                            aria-disabled="true"
                                            tabIndex={-1}
                                        >
                                            <span className="enrol_apply-rview-box" aria-hidden="true">
                                                <Icon name="tick" />
                                            </span>
                                            <span className="enrol_apply-rview-menu-label">{column.title}</span>
                                            <span className="enrol_apply-rview-menu-hint">{labels.always}</span>
                                        </div>
                                    ) : (
                                        <button
                                            key={column.key}
                                            type="button"
                                            className="enrol_apply-rview-menu-item"
                                            role="menuitemcheckbox"
                                            aria-checked={column.active}
                                            tabIndex={-1}
                                            onClick={() => props.onColumn(column.key, !column.active)}
                                        >
                                            <span className="enrol_apply-rview-box" aria-hidden="true">
                                                <Icon name="tick" />
                                            </span>
                                            <span className="enrol_apply-rview-menu-label">{column.title}</span>
                                        </button>
                                    )
                                ))}
                                <div className="enrol_apply-rview-menu-sep" role="separator" />
                                <button
                                    type="button"
                                    className="enrol_apply-rview-menu-item"
                                    role="menuitem"
                                    tabIndex={-1}
                                    aria-disabled={props.usedefault}
                                    onClick={() => {
                                        if (!props.usedefault) {
                                            props.onDefaultColumns();
                                        }
                                    }}
                                >
                                    <Icon name="columns" />
                                    <span className="enrol_apply-rview-menu-label">{labels.defaultcolumns}</span>
                                </button>
                                <button
                                    type="button"
                                    className="enrol_apply-rview-menu-item"
                                    role="menuitem"
                                    tabIndex={-1}
                                    aria-disabled={!props.haswidths}
                                    onClick={() => {
                                        if (props.haswidths) {
                                            props.onResetWidths();
                                        }
                                    }}
                                >
                                    <Icon name="reset" />
                                    <span className="enrol_apply-rview-menu-label">{labels.resetwidths}</span>
                                </button>
                            </div>
                        </div>
                        <div className="enrol_apply-rview-anchor">
                            {iconbutton('view', viewicon, labels.view, chevron)}
                            <div
                                className="enrol_apply-rview-menu"
                                id={uid + 'view'}
                                role="menu"
                                aria-label={labels.view}
                                hidden={layer !== 'view'}
                                onKeyDown={menuKeys}
                            >
                                {([['auto', labels.viewauto, labels.viewautohint], ['table', labels.table, ''],
                                    ['cards', labels.cards, '']] as [ViewMode, string, string][]).map(([value, label, hint]) => (
                                    <button
                                        key={value}
                                        type="button"
                                        className="enrol_apply-rview-menu-item"
                                        role="menuitemradio"
                                        aria-checked={props.view === value}
                                        tabIndex={-1}
                                        onClick={() => {
                                            props.onView(value);
                                            setLayer(null);
                                            triggers.current.view?.focus();
                                        }}
                                    >
                                        <Icon name={value} />
                                        <span className="enrol_apply-rview-menu-label">{label}</span>
                                        {hint !== '' ? <span className="enrol_apply-rview-menu-hint">{hint}</span> : null}
                                        <Icon name="tick" className="enrol_apply-rview-tick" />
                                    </button>
                                ))}
                                <div className="enrol_apply-rview-menu-sep" role="separator" />
                                <a className="enrol_apply-rview-menu-item" role="menuitem" tabIndex={-1} href={props.tableurl}>
                                    <Icon name="table" />
                                    <span className="enrol_apply-rview-menu-label">{labels.coretable}</span>
                                </a>
                            </div>
                        </div>
                        {download ? (
                            <div className="enrol_apply-rview-anchor">
                                {iconbutton('download', 'download', labels.download, chevron)}
                                <div
                                    className="enrol_apply-rview-menu"
                                    id={uid + 'download'}
                                    role="menu"
                                    aria-label={labels.download}
                                    hidden={layer !== 'download'}
                                    onKeyDown={menuKeys}
                                >
                                    {props.formats.map((format) => (
                                        <a
                                            key={format.value}
                                            className="enrol_apply-rview-menu-item"
                                            role="menuitem"
                                            tabIndex={-1}
                                            href={`${props.downloadurl}&parameters=${parameters}`
                                                + `&download=${encodeURIComponent(format.value)}`}
                                        >
                                            <Icon name="download" />
                                            <span className="enrol_apply-rview-menu-label">{format.label}</span>
                                        </a>
                                    ))}
                                </div>
                            </div>
                        ) : null}
                    </div>
                </div>
                <div className="enrol_apply-rview-capsule enrol_apply-rview-searchcap">
                    {searchopen ? (
                        <span className="enrol_apply-rview-search">
                            {props.searching && searched
                                ? <span className="enrol_apply-rview-spin" aria-hidden="true" />
                                : <Icon name="search" />}
                            <input
                                ref={searchinput}
                                type="search"
                                aria-label={labels.search}
                                placeholder={labels.search}
                                autoComplete="off"
                                value={props.search}
                                onChange={(event) => props.onSearch(event.target.value)}
                                onKeyDown={(event) => {
                                    if (event.key === 'Escape') {
                                        // Before the browser empties a search field on the same key: Escape
                                        // closes the field whatever it holds, the text staying in force.
                                        event.preventDefault();
                                        closeSearch(true);
                                    }
                                }}
                                onBlur={() => {
                                    if (props.search === '') {
                                        closeSearch(false);
                                    }
                                }}
                            />
                            {props.search !== '' ? (
                                // The eraser keeps the field's focus on a press: it clears the text and the
                                // field stays open.
                                <button
                                    type="button"
                                    className="enrol_apply-rview-eraser"
                                    aria-label={labels.cleartext}
                                    data-enrol-apply-tip={labels.cleartext}
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={() => {
                                        props.onSearch('');
                                        searchinput.current?.focus();
                                    }}
                                >
                                    <Icon name="eraser" />
                                </button>
                            ) : null}
                        </span>
                    ) : (
                        <button
                            ref={searchbutton}
                            type="button"
                            className="enrol_apply-rview-iconbtn"
                            aria-label={labels.search}
                            data-enrol-apply-tip={labels.search}
                            onClick={() => {
                                setLayer(null);
                                setSearchopen(true);
                            }}
                        >
                            <Icon name="search" />
                        </button>
                    )}
                </div>

                <div
                    className="enrol_apply-rview-fpanel"
                    id={uid + 'filters'}
                    role="dialog"
                    aria-label={labels.filters}
                    hidden={layer !== 'filters'}
                >
                    <div className="enrol_apply-rview-fpanel-body">
                        {panelfilters.map((filter) => (
                            <FilterGroup
                                key={filter.name}
                                labels={labels}
                                filter={filter}
                                title={facetTitle(labels, filter)}
                                onFilter={props.onFilter}
                            />
                        ))}
                        {others.length > 0 ? (
                            <p className="enrol_apply-rview-note">
                                {labels.morefiltershelp}{' '}
                                <a href={props.tableurl}>{labels.morefilters}</a>
                            </p>
                        ) : null}
                    </div>
                    <div className="enrol_apply-rview-fpanel-foot">
                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={props.onClearFilters}>
                            {labels.clearfilters}
                        </button>
                        <button
                            type="button"
                            className="btn btn-sm btn-primary"
                            onClick={() => {
                                setLayer(null);
                                triggers.current.filters?.focus();
                            }}
                        >
                            {showLabel(labels, props.total)}
                        </button>
                    </div>
                </div>
            </div>

            <div className="enrol_apply-rview-chips" data-region="enrol_apply-chips" hidden={chipcount === 0}>
                {applied.map((filter) => {
                    const value = chosenLabel(filter);
                    const title = value !== '' ? facetTitle(labels, filter) : filter.title;
                    const text = value === '' ? title : fillObject(labels.filterchip, {title, value});
                    return (
                        <button
                            key={filter.name}
                            type="button"
                            className="enrol_apply-rview-chip"
                            aria-label={fill(labels.removefilter, text)}
                            onClick={() => props.onRemoveFilter(filter)}
                        >
                            <span>{text}</span>
                            <Icon name="close" />
                        </button>
                    );
                })}
                {searchchip ? (
                    <button
                        type="button"
                        className="enrol_apply-rview-chip"
                        aria-label={fill(labels.removefilter, fill(labels.searchchip, props.search.trim()))}
                        onClick={() => props.onSearch('')}
                    >
                        <span>{fill(labels.searchchip, props.search.trim())}</span>
                        <Icon name="close" />
                    </button>
                ) : null}
                {chipcount > 1 ? (
                    <button
                        type="button"
                        className="enrol_apply-rview-clearall"
                        onClick={() => {
                            props.onClearAll();
                            setSearchopen(false);
                        }}
                    >
                        {labels.clearall}
                    </button>
                ) : null}
            </div>
        </div>
    );
};
