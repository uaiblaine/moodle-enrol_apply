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
 * A synchronous hold on Moodle's pending-JavaScript counter, the one Behat waits on.
 *
 * core/ajax holds the counter only while a request is in flight; between its answer and the render
 * that draws it, and across a filter write followed by the reads it causes, nothing would be
 * pending and Behat would read the page as settled before the rows exist. M.util.js_pending, which
 * core/pending itself calls, is on every page and takes effect at once. block_compass's pending.ts
 * is the fleet's precedent.
 *
 * @module     enrol_apply/datareport/pending
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

type PendingUtil = {js_pending: (key: string) => void, js_complete: (key: string) => void};

/**
 * Take a hold; the returned function releases it, once, however many times it is called.
 *
 * @param {string} key The name the hold is counted under.
 * @returns {Function} Releases the hold.
 */
export const hold = (key: string): (() => void) => {
    const util = (window as unknown as {M?: {util?: PendingUtil}}).M?.util;
    util?.js_pending(key);
    let held = true;

    return () => {
        if (held) {
            held = false;
            util?.js_complete(key);
        }
    };
};
