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
 * The bar's menus (the Design System's menu.md): the keys that move between items, and the Columns
 * menu's sets.
 *
 * @module     enrol_apply/datareport/menus
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {KeyboardEvent, ReactElement} from 'react';
import {Icon} from './icons';
import type {ColumnSet, ExtraView, ViewMode} from './types';

/**
 * Move the focus between a menu's items with the arrow keys, Home and End.
 *
 * @param {object} event The keyboard event, on the menu.
 * @returns {void}
 */
export const menuKeys = (event: KeyboardEvent<HTMLDivElement>): void => {
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
 * The Columns menu's sets, under their heading and before a separator: menuitemcheckbox items, the set
 * in force checked (menu.md); a press on another hands its name to the view, which writes it as the
 * reader's choice.
 *
 * @param {object} props The heading, the sets and the callback.
 * @returns {object|null} The items, or nothing without sets.
 */
export const SetItems = ({heading, sets, onSet}: {heading: string, sets: ColumnSet[],
        onSet: (name: string) => void}): ReactElement | null => {
    if (sets.length === 0) {
        return null;
    }

    return (
        <>
            <div className="enrol_apply-rview-menu-head" role="presentation">{heading}</div>
            {sets.map((set) => (
                <button
                    key={set.name}
                    type="button"
                    className="enrol_apply-rview-menu-item"
                    role="menuitemcheckbox"
                    aria-checked={set.active}
                    tabIndex={-1}
                    data-preset={set.name}
                    onClick={() => {
                        if (!set.active) {
                            onSet(set.name);
                        }
                    }}
                >
                    <span className="enrol_apply-rview-box" aria-hidden="true"><Icon name="tick" /></span>
                    <span className="enrol_apply-rview-menu-label">{set.label}</span>
                </button>
            ))}
            <div className="enrol_apply-rview-menu-sep" role="separator" />
        </>
    );
};

/**
 * The icon of the View button: the selected extra view's own, else the built-in choice's.
 *
 * @param {string} view The selected view.
 * @param {Array} extraViews The caller's own views.
 * @returns {string} The icon's name.
 */
export const viewIcon = (view: ViewMode | string, extraViews: ExtraView[] = []): string => (
    extraViews.find((extra) => extra.value === view)?.icon ?? {auto: 'auto', table: 'table', cards: 'cards'}[view as ViewMode]
);

/**
 * The View menu: the built-in choices the caller allows, then its own views behind a separator, then
 * the link to the view's server table behind another. A press hands the value to onChoose.
 *
 * @param {object} props The menu's id, labels, state and callback.
 * @returns {object} The menu.
 */
export const ViewMenu = (props: {
    id: string,
    labels: Record<string, string>,
    hidden: boolean,
    view: ViewMode | string,
    views: ViewMode[],
    extraViews?: ExtraView[],
    tableurl: string,
    onChoose: (value: string) => void,
}): ReactElement => {
    const {labels} = props;
    const extraViews = props.extraViews ?? [];
    const choices = ([['auto', labels.viewauto, labels.viewautohint], ['table', labels.table, ''],
        ['cards', labels.cards, '']] as [ViewMode, string, string][]).filter(([value]) => props.views.includes(value));

    return (
        <div
            className="enrol_apply-rview-menu"
            id={props.id}
            role="menu"
            aria-label={labels.view}
            hidden={props.hidden}
            onKeyDown={menuKeys}
        >
            {choices.map(([value, label, hint]) => (
                <button
                    key={value}
                    type="button"
                    className="enrol_apply-rview-menu-item"
                    role="menuitemradio"
                    aria-checked={props.view === value}
                    tabIndex={-1}
                    onClick={() => props.onChoose(value)}
                >
                    <Icon name={value} />
                    <span className="enrol_apply-rview-menu-label">{label}</span>
                    {hint !== '' ? <span className="enrol_apply-rview-menu-hint">{hint}</span> : null}
                    <Icon name="tick" className="enrol_apply-rview-tick" />
                </button>
            ))}
            {extraViews.length > 0 ? (
                <>
                    <div className="enrol_apply-rview-menu-sep" role="separator" />
                    {extraViews.map((extra) => (
                        <button
                            key={extra.value}
                            type="button"
                            className="enrol_apply-rview-menu-item"
                            role="menuitemradio"
                            aria-checked={props.view === extra.value}
                            tabIndex={-1}
                            onClick={() => props.onChoose(extra.value)}
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
                    <a className="enrol_apply-rview-menu-item" role="menuitem" tabIndex={-1} href={props.tableurl}>
                        <Icon name="table" />
                        <span className="enrol_apply-rview-menu-label">{labels.coretable}</span>
                    </a>
                </>
            ) : null}
        </div>
    );
};
