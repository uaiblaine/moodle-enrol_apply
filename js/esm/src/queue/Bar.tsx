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
 * The queue's filter bar: the shared bar (datareport/Bar) with the queue's vocabulary.
 *
 * The scope is All, Pending or Deferred. Filters draws the facets the rows answer counts (group, how
 * the group was named, waited more than, applied before; on the site-wide queue the course and the
 * category, each searched and capped with a note of what was left out, the method once a course with two
 * is chosen, and with or without a group) and the identity fields the reader may filter
 * by from a list, which the service does not count; the text fields and the dates are set in the
 * table view, which the panel's note links, and show here as chips that remove them. Columns starts
 * with the three sets (CQ-D7). View is Automatic, Table or Cards, and the table view; the site-wide queue
 * adds "By course" (SQ-D9), whose body the queue view draws itself: while it is shown Columns is left out and
 * the count states the enrolment methods, while the panel's "Show" still states the applications the filters
 * list. No Download (CQ-D11): "Open the report" ends the chips' row for a reader the report admits.
 *
 * @module     enrol_apply/queue/Bar
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {Bar as DataBar} from '../datareport/Bar';
import {fill, fillObject, num} from '../datareport/str';
import type {Chip, ColumnSet, ExtraView, FacetDefinition} from '../datareport/types';
import type {Column, Facet, Preset, Query, RowsAnswer, Scope, ViewMode} from './types';

/** The group filter's value for every group, explicitly (groupfilter::ANY). */
export const ANY_GROUP = 'all';

/** The group filter's name (groupfilter::NAME). */
export const GROUP = 'group';

type BarProps = {
    labels: Record<string, string>,
    narrow: boolean,
    query: Query,
    rows: RowsAnswer | null,
    columns: Column[],
    presets: Preset[],
    usedefault: boolean,
    haswidths: boolean,
    view: ViewMode,
    /** The caller's own views after the built-in ones: "By course" on the site-wide queue, none elsewhere. */
    extraViews: ExtraView[],
    /** The "By course" view's methods while it is shown (null while they load), undefined while the list is. */
    methods?: number | null,
    search: string,
    searchmin: number,
    searching: boolean,
    tableurl: string,
    reporturl: string,
    onScope: (scope: Scope) => void,
    onSearch: (text: string) => void,
    onFilter: (name: string, value: string) => void,
    onClearFilters: () => void,
    onClearAll: () => void,
    onColumn: (key: string, on: boolean) => void,
    onPreset: (preset: Preset) => void,
    onDefaultColumns: () => void,
    onResetWidths: () => void,
    onView: (view: ViewMode | string) => void,
};

/** Every view the queue offers. */
const VIEWS: ViewMode[] = ['auto', 'table', 'cards'];

/**
 * A Columns set's name, by the literal key of each (a label is never looked up by a computed name).
 *
 * @param {object} labels The strings.
 * @param {string} name The set's name (queueengine::PRESETS).
 * @returns {string} Its label.
 */
const presetLabel = (labels: Record<string, string>, name: string): string => {
    switch (name) {
        case 'everything':
            return labels.preseteverything;
        case 'glance':
            return labels.presetglance;
        case 'answers':
            return labels.presetanswers;
        default:
            return name;
    }
};

/**
 * A searched facet's search label and no-match line, by the literal keys of each facet's.
 *
 * @param {object} labels The strings.
 * @param {string} name The facet's name.
 * @returns {object} The label (with the options' count as {$a}) and the no-match line.
 */
const finder = (labels: Record<string, string>, name: string): {label: string, nomatch: string} => {
    switch (name) {
        case 'course':
            return {label: labels.searchcourses, nomatch: labels.nocoursematch};
        case 'category':
            return {label: labels.searchcategories, nomatch: labels.nocategorymatch};
        default:
            return {label: labels.searchgroups, nomatch: labels.nogroupmatch};
    }
};

/**
 * The line under a capped facet (SQ-D6): how many options the server left out, and for the courses that
 * the bar's search finds a course by its name.
 *
 * @param {object} labels The strings.
 * @param {object} facet The facet as the rows answer counts it.
 * @returns {string} The line, '' when nothing was left out.
 */
const omittedNote = (labels: Record<string, string>, facet: Facet): string => {
    if (facet.omitted <= 0) {
        return '';
    }

    return fill(facet.name === 'category' ? labels.omittedcategories : labels.omittedcourses, num(facet.omitted));
};

/**
 * The panel's facets: the counted ones, then the identity fields offered as a list, uncounted.
 *
 * @param {object} labels The strings.
 * @param {object} rows The rows answer.
 * @returns {Array} The facets.
 */
const facets = (labels: Record<string, string>, rows: RowsAnswer): FacetDefinition[] => {
    const counted: FacetDefinition[] = rows.facets.map((facet) => ({
        name: facet.name,
        title: facet.title,
        options: facet.options,
        groupings: [],
        find: finder(labels, facet.name),
        note: omittedNote(labels, facet),
    }));
    const find = finder(labels, '');
    const listed: FacetDefinition[] = rows.fieldfilters.filter((field) => field.control === 'select').map((field) => ({
        name: field.name,
        title: field.title,
        // A count of -1 is "not counted": the option is shown, with no number.
        options: field.options.map((option) => ({...option, count: -1})),
        groupings: [],
        find,
    }));

    return [...counted, ...listed];
};

/**
 * What a filter in force reads as on its chip: its heading and its option's label, or its value.
 *
 * @param {object} labels The strings.
 * @param {object} rows The rows answer, whose filters are the ones the server applied.
 * @param {string} name The filter's name.
 * @param {string} value Its value.
 * @returns {object} The chip.
 */
const chip = (labels: Record<string, string>, rows: RowsAnswer, name: string, value: string): Chip => {
    let title = name;
    let shown = value;
    const facet = rows.facets.find((entry) => entry.name === name);
    const field = rows.fieldfilters.find((entry) => entry.name === name);
    if (facet) {
        title = facet.title;
        shown = facet.options.find((option) => option.value === value)?.label ?? value;
    } else if (field) {
        title = field.title;
        shown = field.options.find((option) => option.value === value)?.label ?? value;
    } else if (name === 'appliedfrom') {
        title = labels.appliedfrom;
    } else if (name === 'appliedto') {
        title = labels.appliedto;
    }

    return {key: name, text: fillObject(labels.filterchip, {title, value: shown})};
};

/**
 * The bar.
 *
 * @param {object} props See BarProps.
 * @returns {object} The bar.
 */
export const Bar = (props: BarProps): ReactElement => {
    const {labels, rows, query} = props;
    // The "By course" view is shown: its value is the View menu's choice, and the count is of its methods.
    const bycourse = props.methods !== undefined;
    const sets: ColumnSet[] = props.presets.map((preset) => ({
        name: preset.name,
        label: presetLabel(labels, preset.name),
        active: preset.active,
    }));
    // The panel shows what is asked now; "every group" is the panel's "Any".
    const values = Object.fromEntries(Object.entries(query.filters)
        .map(([name, value]) => [name, name === GROUP && value === ANY_GROUP ? '' : value]));
    // The chips name what the server applied, as it cleaned it.
    const chips = rows === null ? [] : rows.filters.map((filter) => chip(labels, rows, filter.name, filter.value));

    return (
        <DataBar
            labels={labels}
            narrow={props.narrow}
            scopes={[
                {value: 'all', label: labels.all},
                {value: 'pending', label: labels.pending},
                {value: 'deferred', label: labels.deferred},
            ]}
            scope={query.scope}
            total={rows === null ? null : rows.total}
            facets={rows === null ? [] : facets(labels, rows)}
            values={values}
            panelnote={(
                <p className="enrol_apply-rview-note">
                    {labels.morefiltershelp}{' '}
                    <a href={props.tableurl}>{labels.morefilters}</a>
                </p>
            )}
            chips={chips}
            aside={props.reporturl !== '' ? (
                <a className="enrol_apply-qview-tolink" href={props.reporturl}>{labels.openreport}</a>
            ) : null}
            columns={props.columns}
            sets={sets}
            usedefault={props.usedefault}
            haswidths={props.haswidths}
            view={bycourse ? query.show : props.view}
            views={VIEWS}
            extraViews={props.extraViews}
            hidecolumns={bycourse}
            countkey={bycourse ? 'methods' : 'count'}
            counted={props.methods}
            tableurl={props.tableurl}
            downloads={[]}
            search={props.search}
            searchmin={props.searchmin}
            searching={props.searching}
            onScope={(scope) => props.onScope(scope as Scope)}
            onSearch={props.onSearch}
            onFacet={props.onFilter}
            onRemoveChip={(name) => props.onFilter(name, '')}
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
