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
 * The bundle's entry: every React view of the plugin, one chosen per mount by its props.
 *
 * Core's react_autoinit mounts the DEFAULT export of the module a mount names, and the fleet's bundle
 * step builds one entry into one file (js/esm/bundle.json), so every view of the plugin is reached
 * through this one component: each template names @moodle/lms/enrol_apply/bundle, and its props name
 * the view in `mount`. A mount that names none is the course report's, whose props (report_view)
 * predate the key. Another view is one more branch here and a template whose props name it; the
 * views share the data report's parts, so the bundle carries them once.
 *
 * @module     enrol_apply/views
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import ReportView from './report/ReportView';
import type {ViewProps as ReportProps} from './report/types';

/**
 * What a mount's props carry: one view's own props, and that view's name in `mount`, optional for the
 * report alone. A second view joins as a member of this union whose `mount` is required.
 */
type MountProps = ReportProps & {mount?: 'report'};

/**
 * The view a mount names.
 *
 * @param {object} props The view's props, with the view's name in `mount`.
 * @returns {object|null} The view, or nothing for a name this bundle does not carry.
 */
const Views = (props: MountProps): ReactElement | null => {
    if (props.mount === undefined || props.mount === 'report') {
        return <ReportView {...props} />;
    }

    return null;
};

export default Views;
