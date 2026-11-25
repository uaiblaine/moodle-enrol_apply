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
 * The dock under the rows: how many applications are selected and the three decisions.
 *
 * The decisions are the table's (renderer::queue_actions(), which the props carry): the same labels,
 * in the same order and the same styles, Cancel applications, Defer, Confirm. Each opens the decision
 * sheet; nothing posts from here. The count is worded "on this page", because that is what a selection
 * is: it is cleared
 * whenever the page, the scope, a filter, the search or the sort changes. On the queues that span
 * methods it adds how many methods the selection spans, when more than one, because the sheet then asks a
 * message per method. Drawn only while something is selected; the view keeps it out otherwise.
 *
 * @module     enrol_apply/queue/Dock
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {fill, num} from '../datareport/str';
import type {Action, Decision} from './types';

type DockProps = {
    labels: Record<string, string>,
    count: number,
    /** How many enrolment methods the selection spans; 1 on a method's queue. */
    methods: number,
    actions: Action[],
    /** Open the sheet for a decision; the button pressed gets the focus back when the sheet closes. */
    onDecide: (decision: Decision, button: HTMLButtonElement) => void,
};

/**
 * The dock.
 *
 * @param {object} props See DockProps.
 * @returns {object} The dock.
 */
export const Dock = ({labels, count, methods, actions, onDecide}: DockProps): ReactElement => (
    <div className="enrol_apply-qview-dock" role="region" aria-label={labels.dock} data-region="enrol_apply-dock">
        <span className="enrol_apply-qview-dockcount" aria-live="polite">
            {fill(labels.selected, num(count))}
            {methods > 1 ? (
                <span className="enrol_apply-qview-dockmethods">{' ' + fill(labels.dockmethods, num(methods))}</span>
            ) : null}
        </span>
        <div className="enrol_apply-qview-dockactions">
            {actions.map((action) => (
                <button
                    key={action.value}
                    type="button"
                    className={'btn ' + action.classes}
                    onClick={(event) => onDecide(action.value, event.currentTarget)}
                >
                    {action.label}
                </button>
            ))}
        </div>
    </div>
);
