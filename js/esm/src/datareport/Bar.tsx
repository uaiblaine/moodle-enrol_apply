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
 * The scope, the count, a capsule of icon buttons - Filters with its loud counter, Columns, View and,
 * where a view offers one, Download - and the search by name in a capsule of its own, folded to its
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
 * What differs between views arrives as props: the scope's values, the facets and their values with
 * the callback that writes a change where the view keeps its filters, the chips of the filters in
 * force, the column catalogue and its sets, the View choices and the link to the view's server table,
 * and the Download links (none: no Download button). Columns starts with the sets, each handed to the
 * view as the reader's choice, then lists the catalogue, a fixed column always shown, and ends with
 * the default columns and "Reset column widths". A view may put a link of its own at the end of the
 * chips' row (`aside`: the queue's "Open the report"), which then shows with no chip in force too.
 *
 * Strings read from the view's labels: scope, count (and count_one), filters, filterson, columns,
 * presets, always, defaultcolumns, resetwidths, view, viewauto, viewautohint, table, cards, coretable,
 * download, search, cleartext, removefilter, searchchip, clearall, and FilterPanel's.
 *
 * @module     enrol_apply/datareport/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useId, useRef, useState} from 'react';
import type {KeyboardEvent, ReactElement, ReactNode} from 'react';
import {FilterPanel} from './FilterPanel';
import {Icon} from './icons';
import {SetItems, menuKeys} from './menus';
import {Segmented} from './Segmented';
import {fill, fillCount, num} from './str';
import type {Chip, Choice, Column, ColumnSet, DownloadLink, ExtraView, FacetDefinition, ViewMode} from './types';

type Layer = 'filters' | 'columns' | 'view' | 'download' | null;

type BarProps = {
    labels: Record<string, string>,
    narrow: boolean,
    scopes: Choice[],
    scope: string,
    total: number | null,
    facets: FacetDefinition[],
    values: Record<string, string>,
    panelnote?: ReactNode,
    chips: Chip[],
    aside?: ReactNode,
    columns: Column[],
    sets: ColumnSet[],
    usedefault: boolean,
    haswidths: boolean,
    view: ViewMode | string,
    views: ViewMode[],
    extraViews?: ExtraView[],
    tableurl: string,
    downloads: DownloadLink[],
    search: string,
    searchmin: number,
    searching: boolean,
    onScope: (scope: string) => void,
    onSearch: (text: string) => void,
    onFacet: (name: string, value: string) => void,
    onRemoveChip: (key: string) => void,
    onClearFilters: () => void,
    onClearAll: () => void,
    onColumn: (key: string, on: boolean) => void,
    onSet: (name: string) => void,
    onDefaultColumns: () => void,
    onResetWidths: () => void,
    onView(view: ViewMode | string): void,
};

/**
 * The bar.
 *
 * @param {object} props See BarProps.
 * @returns {object} The bar.
 */
export const Bar = (props: BarProps): ReactElement => {
    const {labels, chips, columns} = props;
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

    const searched = props.search.trim().length >= props.searchmin;
    // The text in force is a chip once the field is closed; while it is open, the field says it.
    const searchchip = searched && !searchopen;
    const chipcount = chips.length + (searchchip ? 1 : 0);

    // At the narrow step the visible count is the bare number; the full sentence stays for assistive technology.
    const fullcount = props.total === null ? '…' : fillCount(labels, 'count', props.total, num(props.total));
    const countText = props.total !== null && props.narrow ? (
        <>
            <span aria-hidden="true">{num(props.total)}</span>
            <span className="visually-hidden">{fullcount}</span>
        </>
    ) : fullcount;

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

    const extraviews = props.extraViews ?? [];
    const viewicon = extraviews.find((extra) => extra.value === props.view)?.icon
        ?? {auto: 'auto', table: 'table', cards: 'cards'}[props.view as ViewMode];
    const chevron = <Icon name="chevron" className="enrol_apply-rview-chevron" />;
    // Every chip but the search's is a filter in force: their number is the Filters button's counter.
    const filtersname = chips.length > 0 ? fill(labels.filterson, num(chips.length)) : labels.filters;
    const badge = chips.length > 0
        ? <span className="enrol_apply-rview-badge" aria-hidden="true">{num(chips.length)}</span>
        : null;
    const viewchoices = ([['auto', labels.viewauto, labels.viewautohint], ['table', labels.table, ''],
        ['cards', labels.cards, '']] as [ViewMode, string, string][]).filter(([value]) => props.views.includes(value));
    const barclass = 'enrol_apply-rview-bar'
        + (props.narrow ? ' enrol_apply-rview-bar--narrow' : '')
        + (searchopen ? ' enrol_apply-rview-bar--searching' : '');

    return (
        <div className={barclass} data-region="enrol_apply-bar" ref={root} onKeyDown={escape}>
            <Segmented
                label={labels.scope}
                value={props.scope}
                options={props.scopes}
                onChange={props.onScope}
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
                                <SetItems heading={labels.presets} sets={props.sets} onSet={props.onSet} />
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
                                {viewchoices.map(([value, label, hint]) => (
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
                                {extraviews.length > 0 ? (
                                    <>
                                        <div className="enrol_apply-rview-menu-sep" role="separator" />
                                        {extraviews.map((extra) => (
                                            <button
                                                key={extra.value}
                                                type="button"
                                                className="enrol_apply-rview-menu-item"
                                                role="menuitemradio"
                                                aria-checked={props.view === extra.value}
                                                tabIndex={-1}
                                                onClick={() => {
                                                    props.onView(extra.value);
                                                    setLayer(null);
                                                    triggers.current.view?.focus();
                                                }}
                                            >
                                                <Icon name={extra.icon} />
                                                <span className="enrol_apply-rview-menu-label">{extra.label}</span>
                                                <Icon name="tick" className="enrol_apply-rview-tick" />
                                            </button>
                                        ))}
                                    </>
                                ) : null}
                                {props.tableurl !== '' ? (
                                    <>
                                        <div className="enrol_apply-rview-menu-sep" role="separator" />
                                        <a
                                            className="enrol_apply-rview-menu-item"
                                            role="menuitem"
                                            tabIndex={-1}
                                            href={props.tableurl}
                                        >
                                            <Icon name="table" />
                                            <span className="enrol_apply-rview-menu-label">{labels.coretable}</span>
                                        </a>
                                    </>
                                ) : null}
                            </div>
                        </div>
                        {props.downloads.length > 0 ? (
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
                                    {props.downloads.map((link) => (
                                        <a
                                            key={link.key}
                                            className="enrol_apply-rview-menu-item"
                                            role="menuitem"
                                            tabIndex={-1}
                                            href={link.href}
                                        >
                                            <Icon name="download" />
                                            <span className="enrol_apply-rview-menu-label">{link.label}</span>
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

                <FilterPanel
                    labels={labels}
                    id={uid + 'filters'}
                    hidden={layer !== 'filters'}
                    facets={props.facets}
                    values={props.values}
                    total={props.total}
                    note={props.panelnote}
                    onChange={props.onFacet}
                    onClear={props.onClearFilters}
                    onShow={() => {
                        setLayer(null);
                        triggers.current.filters?.focus();
                    }}
                />
            </div>

            <div className="enrol_apply-rview-chips" data-region="enrol_apply-chips" hidden={chipcount === 0 && !props.aside}>
                {chips.map((chip) => (
                    <button
                        key={chip.key}
                        type="button"
                        className="enrol_apply-rview-chip"
                        aria-label={fill(labels.removefilter, chip.text)}
                        onClick={() => props.onRemoveChip(chip.key)}
                    >
                        <span>{chip.text}</span>
                        <Icon name="close" />
                    </button>
                ))}
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
                {props.aside ?? null}
            </div>
        </div>
    );
};
