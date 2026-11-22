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
 * button until pressed; the chips of what is in force on a row of their own, then "Clear all". One
 * layer is open at a time; Escape or a press outside closes it and gives the focus back to its button.
 *
 * Filters draws the report's own filters that pick one option of a list (core's store, through
 * core_reportbuilder_set_filters); a group filter with more than eight groups is a search over a list
 * capped at fifty, the chosen one pinned on top (section 5b). Columns lists the report's catalogue for
 * this reader, the applicant and the identity fields always shown, and ends with "Reset column widths".
 * View is Automatic, Table or Cards, and core's own table view.
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
import type {Column, Filter, Format, Measure, Scope, ViewMode} from './types';

/** A group filter with more options than this is searched (RP-D18, section 5b). */
const FEW = 8;

/** The most options a searched list draws at once (section 5b). */
const CAP = 50;

type Layer = 'filters' | 'columns' | 'view' | 'download' | null;

type BarProps = {
    labels: Record<string, string>,
    scope: Scope,
    total: number | null,
    filters: Filter[],
    columns: Column[],
    usedefault: boolean,
    haswidths: boolean,
    view: ViewMode,
    measure: Measure,
    search: string,
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
    onDefaultColumns: () => void,
    onResetWidths: () => void,
    onView: (view: ViewMode) => void,
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
 * One filter's group in the panel: a radio group of its options, or a searched list for many groups.
 *
 * @param {object} props The strings, the filter and the callback.
 * @returns {object} The group.
 */
const FilterGroup = ({labels, filter, onFilter}: {labels: Record<string, string>, filter: Filter,
        onFilter: (filter: Filter, value: string) => void}): ReactElement => {
    const headid = useId();
    const [query, setQuery] = useState('');
    const value = chosen(filter);
    const options = [{value: '', label: labels.any}, ...filter.options];

    if (filter.options.length > FEW) {
        const folded = query.trim().toLocaleLowerCase();
        const pinned = filter.options.filter((option) => option.value === value);
        const rest = filter.options.filter((option) => option.value !== value
            && (folded === '' || option.label.toLocaleLowerCase().includes(folded)));
        return (
            <div className="enrol_apply-rview-fgroup">
                <span className="enrol_apply-rview-fpanel-head" id={headid}>{filter.title}</span>
                <input
                    type="search"
                    className="form-control form-control-sm enrol_apply-rview-input"
                    placeholder={fill(labels.searchgroups, num(filter.options.length))}
                    aria-label={fill(labels.searchgroups, num(filter.options.length))}
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                />
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
                            <span className="enrol_apply-rview-optrow-label">{option.label}</span>
                        </button>
                    ))}
                    {rest.length > CAP
                        ? <p className="enrol_apply-rview-note">{fill(labels.moregroups, num(rest.length - CAP))}</p>
                        : null}
                    {pinned.length === 0 && rest.length === 0
                        ? <p className="enrol_apply-rview-note">{labels.nogroupmatch}</p>
                        : null}
                </div>
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
            <span className="enrol_apply-rview-fpanel-head" id={headid}>{filter.title}</span>
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
                        {option.label}
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

    useEffect(() => {
        if (searchopen) {
            searchinput.current?.focus();
        }
    }, [searchopen]);

    /**
     * Open a layer, or close it when it is the open one.
     *
     * @param {string} which The layer.
     * @returns {void}
     */
    const toggle = (which: Layer): void => setLayer(layer === which ? null : which);

    /**
     * Escape closes the open layer and gives the focus back to its button.
     *
     * @param {object} event The keyboard event.
     * @returns {void}
     */
    const escape = (event: KeyboardEvent<HTMLDivElement>): void => {
        if (event.key !== 'Escape' || layer === null) {
            return;
        }
        event.preventDefault();
        const trigger = triggers.current[layer];
        setLayer(null);
        trigger?.focus();
    };

    const applied = filters.filter((filter) => filter.applied);
    const searched = props.search.trim().length >= props.searchmin;
    const panelfilters = filters.filter(choosable);
    const others = filters.filter((filter) => !choosable(filter));

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
    const parameters = encodeURIComponent(JSON.stringify({measure: props.measure}));

    return (
        <div className="enrol_apply-rview-bar" data-region="enrol_apply-bar" ref={root} onKeyDown={escape}>
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
            <div className="enrol_apply-rview-bar-cluster">
                <span className="enrol_apply-rview-bar-count" data-region="enrol_apply-total" aria-live="polite">
                    {props.total === null ? '…' : fillCount(labels, 'count', props.total, num(props.total))}
                </span>
                <div className="enrol_apply-rview-capsule">
                    {iconbutton('filters', 'filter', filtersname, badge)}
                    {iconbutton('columns', 'columns', labels.columns, chevron)}
                    {iconbutton('view', viewicon, labels.view, chevron)}
                    {download ? iconbutton('download', 'download', labels.download, null) : null}
                </div>
            </div>
            <div className="enrol_apply-rview-capsule">
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
                                    event.preventDefault();
                                    if (props.search === '') {
                                        setSearchopen(false);
                                    } else {
                                        props.onSearch('');
                                    }
                                }
                            }}
                            onBlur={() => {
                                if (props.search === '') {
                                    setSearchopen(false);
                                }
                            }}
                        />
                        {props.search !== '' ? (
                            <button
                                type="button"
                                className="enrol_apply-rview-eraser"
                                aria-label={labels.cleartext}
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => {
                                    props.onSearch('');
                                    searchinput.current?.focus();
                                }}
                            >
                                <Icon name="close" />
                            </button>
                        ) : null}
                    </span>
                ) : (
                    <button
                        type="button"
                        className="enrol_apply-rview-iconbtn"
                        aria-label={labels.search}
                        data-enrol-apply-tip={labels.search}
                        onClick={() => setSearchopen(true)}
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
                        <FilterGroup key={filter.name} labels={labels} filter={filter} onFilter={props.onFilter} />
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
                        {labels.show}
                    </button>
                </div>
            </div>

            <div
                className="enrol_apply-rview-menu"
                id={uid + 'columns'}
                role="menu"
                aria-label={labels.columns}
                hidden={layer !== 'columns'}
                onKeyDown={menuKeys}
            >
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
                            <span className="enrol_apply-rview-box" aria-hidden="true"><Icon name="tick" /></span>
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
                            <span className="enrol_apply-rview-box" aria-hidden="true"><Icon name="tick" /></span>
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

            <div
                className="enrol_apply-rview-menu"
                id={uid + 'view'}
                role="menu"
                aria-label={labels.view}
                hidden={layer !== 'view'}
                onKeyDown={menuKeys}
            >
                {([['auto', labels.viewauto, labels.viewautohint], ['table', labels.table, ''], ['cards', labels.cards, '']] as
                        [ViewMode, string, string][]).map(([value, label, hint]) => (
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

            {download ? (
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
                            href={`${props.downloadurl}&parameters=${parameters}&download=${encodeURIComponent(format.value)}`}
                        >
                            <Icon name="download" />
                            <span className="enrol_apply-rview-menu-label">{format.label}</span>
                        </a>
                    ))}
                </div>
            ) : null}

            <div className="enrol_apply-rview-chips" data-region="enrol_apply-chips" hidden={applied.length === 0 && !searched}>
                {applied.map((filter) => {
                    const value = chosenLabel(filter);
                    const text = value === '' ? filter.title : fillObject(labels.filterchip, {title: filter.title, value});
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
                {searched ? (
                    <button
                        type="button"
                        className="enrol_apply-rview-chip"
                        aria-label={fill(labels.removefilter, fill(labels.searchchip, props.search.trim()))}
                        onClick={() => {
                            props.onSearch('');
                            setSearchopen(false);
                        }}
                    >
                        <span>{fill(labels.searchchip, props.search.trim())}</span>
                        <Icon name="close" />
                    </button>
                ) : null}
                {applied.length + (searched ? 1 : 0) > 1 ? (
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
