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
 * The view's icons, drawn in currentColor and always decorative.
 *
 * A component cannot render core's pix icons ({{#pix}} is Mustache), so the few glyphs the bar,
 * the menus and the chips need are inline SVG here, each beside a word or inside a named control.
 *
 * @module     enrol_apply/report/icons
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';

const PATHS: Record<string, string> = {
    filter: 'M2 3h12l-4.5 5.5V13l-3 1.5V8.5L2 3z',
    columns: 'M2 3h12v10H2zM6.5 3v10M10 3v10',
    table: 'M2 3h12v10H2zM2 6.5h12M2 10h12M6 6.5V13',
    cards: 'M2 3h5v4H2zM9 3h5v4H9zM2 9h5v4H2zM9 9h5v4H9z',
    auto: 'M2 3h7v10H2zM11 3h3v4h-3zM11 9h3v4h-3z',
    download: 'M8 2v8M4.5 7 8 10.5 11.5 7M3 13h10',
    search: 'M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM10.5 10.5 14 14',
    close: 'M4 4l8 8M12 4l-8 8',
    tick: 'M3 8.5 6.5 12 13 4.5',
    chevron: 'M4 6l4 4 4-4',
    reset: 'M3 8a5 5 0 1 0 1.5-3.5M3 2.5V5h2.5',
    queue: 'M3 4h10M3 8h10M3 12h6',
};

/**
 * One icon.
 *
 * @param {object} props The icon's name and an optional extra class.
 * @returns {object} The SVG, hidden from assistive technology.
 */
export const Icon = ({name, className}: {name: string, className?: string}): ReactElement => (
    <svg
        className={'enrol_apply-rview-icon' + (className ? ' ' + className : '')}
        viewBox="0 0 16 16"
        width="16"
        height="16"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
        focusable="false"
    >
        <path d={PATHS[name] ?? ''} />
    </svg>
);
