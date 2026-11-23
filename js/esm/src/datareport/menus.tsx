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
import type {ColumnSet} from './types';

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
