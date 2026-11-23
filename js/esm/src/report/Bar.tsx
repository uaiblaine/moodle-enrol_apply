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
 * The course report's filter bar: the shared bar (datareport/Bar) with the report's vocabulary.
 *
 * The scope is all, awaiting or decided. Filters draws the report's facets, which live in core's store
 * (core_reportbuilder_set_filters, filters.ts): the filters that pick one option of a list, "Waited more
 * than" for the wait the band measures and "Decided by", each option with the rows it would list under
 * every other filter in force, the scope and the search (the rows answer counts them). The other
 * filters are set in core's table view, which the panel's note links, and appear as chips that remove
 * them. Columns starts with three sets (Decisions, Answers, Groups). View is Automatic, Table or Cards,
 * and core's own table view. Download links core's download of the report with the measure, the scope
 * and the search in force, which the report applies itself.
 *
 * @module     enrol_apply/report/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {Bar as DataBar} from '../datareport/Bar';
import {fillObject} from '../datareport/str';
import type {Chip, ColumnSet, DownloadLink, FacetDefinition} from '../datareport/types';
import {choosable, chosen, chosenLabel} from './filters';
import type {Column, Filter, Format, Measure, Preset, Scope, ViewMode} from './types';

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

/** Every view the report offers. */
const VIEWS: ViewMode[] = ['auto', 'table', 'cards'];

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
 * A report filter as a facet of the panel: a searched list's words are the groups' or the deciders'.
 *
 * @param {object} labels The strings.
 * @param {object} filter The filter, one the panel draws.
 * @returns {object} The facet.
 */
const facet = (labels: Record<string, string>, filter: Filter): FacetDefinition => ({
    name: filter.name,
    title: facetTitle(labels, filter),
    options: filter.options,
    groupings: filter.groupings,
    find: filter.facet === 'decider'
        ? {label: labels.searchdeciders, nomatch: labels.nodecidermatch}
        : {label: labels.searchgroups, nomatch: labels.nogroupmatch},
});

/**
 * What an applied filter reads as on its chip: its facet's heading and its option, or its title alone.
 *
 * @param {object} labels The strings.
 * @param {object} filter The filter, applied.
 * @returns {object} The chip.
 */
const chip = (labels: Record<string, string>, filter: Filter): Chip => {
    const value = chosenLabel(filter);
    const title = value !== '' ? facetTitle(labels, filter) : filter.title;

    return {key: filter.name, text: value === '' ? title : fillObject(labels.filterchip, {title, value})};
};

/**
 * The bar.
 *
 * @param {object} props See BarProps.
 * @returns {object} The bar.
 */
export const Bar = (props: BarProps): ReactElement => {
    const {labels, filters} = props;
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
     * The filter a facet or a chip names.
     *
     * @param {string} name The filter's name.
     * @returns {object|undefined} The filter.
     */
    const named = (name: string): Filter | undefined => filters.find((filter) => filter.name === name);

    const download = props.downloadurl !== '' && props.formats.length > 0;
    const parameters = downloadParameters(props.measure, props.scope, props.appliedsearch);
    const downloads: DownloadLink[] = download ? props.formats.map((format) => ({
        key: format.value,
        label: format.label,
        href: `${props.downloadurl}&parameters=${parameters}&download=${encodeURIComponent(format.value)}`,
    })) : [];
    const sets: ColumnSet[] = props.presets.map((preset) => ({
        name: preset.name,
        label: presetLabel(labels, preset.name),
        active: preset.active,
    }));

    return (
        <DataBar
            labels={labels}
            narrow={props.narrow}
            scopes={[
                {value: 'all', label: labels.all},
                {value: 'awaiting', label: labels.awaiting},
                {value: 'decided', label: labels.decided},
            ]}
            scope={props.scope}
            total={props.total}
            facets={panelfilters.map((filter) => facet(labels, filter))}
            values={Object.fromEntries(panelfilters.map((filter) => [filter.name, chosen(filter)]))}
            panelnote={others.length > 0 ? (
                <p className="enrol_apply-rview-note">
                    {labels.morefiltershelp}{' '}
                    <a href={props.tableurl}>{labels.morefilters}</a>
                </p>
            ) : null}
            chips={filters.filter((filter) => filter.applied).map((filter) => chip(labels, filter))}
            columns={props.columns}
            sets={sets}
            usedefault={props.usedefault}
            haswidths={props.haswidths}
            view={props.view}
            views={VIEWS}
            tableurl={props.tableurl}
            downloads={downloads}
            search={props.search}
            searchmin={props.searchmin}
            searching={props.searching}
            onScope={(scope) => props.onScope(scope as Scope)}
            onSearch={props.onSearch}
            onFacet={(name, value) => {
                const filter = named(name);
                if (filter) {
                    props.onFilter(filter, value);
                }
            }}
            onRemoveChip={(name) => {
                const filter = named(name);
                if (filter) {
                    props.onRemoveFilter(filter);
                }
            }}
            onClearFilters={props.onClearFilters}
            onClearAll={props.onClearAll}
            onColumn={props.onColumn}
            onSet={(name) => {
                const preset = props.presets.find((entry) => entry.name === name);
                if (preset) {
                    props.onPreset(preset);
                }
            }}
            onDefaultColumns={props.onDefaultColumns}
            onResetWidths={props.onResetWidths}
            onView={props.onView}
        />
    );
};
