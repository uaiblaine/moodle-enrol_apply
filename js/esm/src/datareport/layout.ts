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
 * The narrow step and the reader's view choice, shared by every view of the data report.
 *
 * The narrow step is read from an element's own width, never the window's: the view sits in a page
 * whose drawers and blocks take a width the window does not show (AR-D9; stylelint refuses a
 * container query, which would otherwise say this in CSS).
 *
 * @module     enrol_apply/datareport/layout
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from 'react';
import type {RefObject} from 'react';
import type {ViewMode} from './types';

/** Below this width of the observed element, in pixels, the view takes its narrow form. */
export const NARROW = 576;

/**
 * Whether an element is narrower than the narrow step, followed as it resizes.
 *
 * @param {object} ref The element observed.
 * @returns {boolean} True under 576 px (never before the first measure).
 */
export const useNarrow = (ref: RefObject<HTMLElement | null>): boolean => {
    const [narrow, setNarrow] = useState(false);

    useEffect(() => {
        const element = ref.current;
        if (!element || typeof ResizeObserver === 'undefined') {
            return undefined;
        }
        const observer = new ResizeObserver((entries) => {
            const width = entries[0]?.contentRect.width ?? element.clientWidth;
            setNarrow(width > 0 && width < NARROW);
        });
        observer.observe(element);

        return () => observer.disconnect();
    }, [ref]);

    return narrow;
};

/**
 * The view the reader chose last in this browser, under a view's own key.
 *
 * @param {string} key The browser key: a convenience of this browser alone.
 * @returns {string} auto, table or cards.
 */
export const storedView = (key: string): ViewMode => {
    try {
        const value = window.localStorage.getItem(key);
        return value === 'table' || value === 'cards' ? value : 'auto';
    } catch {
        return 'auto';
    }
};

/**
 * Remember the reader's view choice in this browser.
 *
 * @param {string} key The browser key.
 * @param {string} chosen auto, table or cards.
 * @returns {void}
 */
export const rememberView = (key: string, chosen: ViewMode): void => {
    try {
        window.localStorage.setItem(key, chosen);
    } catch {
        // A browser that keeps nothing keeps the choice for this page only.
    }
};
