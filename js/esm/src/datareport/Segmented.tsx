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
 * A segmented control (the Design System's segmented-control.md): one of two to four visible values.
 *
 * A radiogroup with one tab stop, the chosen cell; the arrow keys, Home and End move to another cell
 * and choose it.
 *
 * @module     enrol_apply/datareport/Segmented
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {KeyboardEvent, ReactElement} from 'react';

type SegmentedProps = {
    label: string,
    value: string,
    options: {value: string, label: string}[],
    small?: boolean,
    onChange: (value: string) => void,
};

/**
 * The control.
 *
 * @param {object} props The group's name, the chosen value, the options and the callback; see SegmentedProps.
 * @returns {object} The radiogroup.
 */
export const Segmented = ({label, value, options, small, onChange}: SegmentedProps): ReactElement => {
    /**
     * The arrow keys, Home and End: move the focus to another cell and choose it.
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
        onChange(options[next].value);
        const cells = event.currentTarget.querySelectorAll<HTMLButtonElement>('[role="radio"]');
        cells[next]?.focus();
    };

    return (
        <div
            className={'enrol_apply-rview-seg' + (small ? ' enrol_apply-rview-seg--small' : '')}
            role="radiogroup"
            aria-label={label}
            onKeyDown={keydown}
        >
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    className="enrol_apply-rview-seg-cell"
                    role="radio"
                    aria-checked={option.value === value}
                    tabIndex={option.value === value ? 0 : -1}
                    onClick={() => onChange(option.value)}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
};
