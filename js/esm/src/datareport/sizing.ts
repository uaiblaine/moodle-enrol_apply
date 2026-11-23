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
 * Column widths: a view's policy built from its tables, and the width a column is drawn at.
 *
 * @module     enrol_apply/datareport/sizing
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {Sizing} from './types';

/**
 * A view's width policy.
 *
 * @param {object} defaults Default widths in pixels, by column key.
 * @param {number} fallback The default of a column the table does not name.
 * @param {object} minimums Minimum widths in pixels, by column key.
 * @param {number} minimum The minimum of a column that table does not name.
 * @param {number} maximum The widest any column may be dragged.
 * @returns {object} The policy.
 */
export const makeSizing = (defaults: Record<string, number>, fallback: number, minimums: Record<string, number>,
        minimum: number, maximum: number): Sizing => ({
    /**
     * A column's default width.
     *
     * @param {string} key The column key.
     * @returns {number} Pixels.
     */
    defaultWidth: (key: string): number => defaults[key] ?? fallback,
    /**
     * A column's minimum width.
     *
     * @param {string} key The column key.
     * @returns {number} Pixels.
     */
    minimumWidth: (key: string): number => minimums[key] ?? minimum,
    maximum,
});

/**
 * The width a column is drawn at: the reader's, held to the bounds, or the default.
 *
 * @param {object} sizing The view's width policy.
 * @param {object} widths The reader's widths by key.
 * @param {string} key The column key.
 * @returns {number} Pixels.
 */
export const widthOf = (sizing: Sizing, widths: Record<string, number>, key: string): number =>
    Math.min(sizing.maximum, Math.max(sizing.minimumWidth(key), widths[key] ?? sizing.defaultWidth(key)));
