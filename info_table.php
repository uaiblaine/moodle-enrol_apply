<?php
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
 * Table listing the comments submitted with enrolment applications.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     emeneo (http://emeneo.com/)
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Table listing the comments submitted with enrolment applications.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_apply_info_table extends table_sql {
    /**
     * Build the table for the requested scope.
     *
     * @param int $enrolid Restrict to one enrol instance, 0 for no restriction.
     * @param string $commentlabel Heading of the comment column.
     */
    public function __construct($enrolid = 0, $commentlabel = '') {
        parent::__construct('enrol_apply_info_table');

        // Same "undecided application" predicate as the approval queue, see manage_table.php.
        $wheres = ['ue.status != :active', '(ue.timeend = 0 OR ue.timeend > :now)'];
        $params = ['active' => ENROL_USER_ACTIVE, 'now' => time()];

        if ($enrolid) {
            $wheres[] = 'e.id = :enrolid';
            $params['enrolid'] = $enrolid;
        } else {
            $wheres[] = 'e.enrol = :enrol';
            $params['enrol'] = 'apply';
        }

        $userfieldsapi = \core_user\fields::for_userpic();
        $userfields = $userfieldsapi->get_sql('u', false, '', 'userid', false)->selects;

        $this->set_sql(
            "ue.id AS userenrolmentid, ue.status AS enrolstatus, ue.timecreated AS applydate,
             ai.comment AS applycomment, c.fullname AS course, {$userfields}",
            "{user_enrolments} ue
        LEFT JOIN {enrol_apply_applicationinfo} ai ON ai.userenrolmentid = ue.id
             JOIN {user} u ON u.id = ue.userid
             JOIN {enrol} e ON e.id = ue.enrolid
             JOIN {course} c ON c.id = e.courseid",
            implode(' AND ', $wheres),
            $params
        );

        $this->define_columns(['fullname', 'applydate', 'applycomment']);
        $this->define_headers([
            // The heading of a column named 'fullname' is filled in by table_sql itself.
            'fullname',
            get_string('applydate', 'enrol_apply'),
            $commentlabel !== '' ? $commentlabel : get_string('applycomment', 'enrol_apply'),
        ]);
        $this->no_sorting('applycomment');
        /* Names the cell that identifies each row, so table_sql emits it as a
           <th scope="row"> and a screen reader announces the date and the comment against
           the applicant's name rather than reading them as free-standing values. */
        $this->define_header_column('fullname');
        $this->sortable(true, 'applydate', SORT_ASC);
    }

    /**
     * Extra CSS classes for a row.
     *
     * @param stdClass $row Row data, an object carrying every selected column.
     * @return string Value added to the class attribute of the row.
     */
    public function get_row_class($row) {
        if ($row->enrolstatus == ENROL_APPLY_USER_WAIT) {
            return 'enrol_apply_waitinglist_highlight';
        }
        return '';
    }

    /**
     * The applicant name column, with the user picture.
     *
     * @param stdClass $row Row data carrying the aliased user picture fields.
     * @return string Rendered cell.
     */
    public function col_fullname($row) {
        global $OUTPUT;

        $user = user_picture::unalias($row, null, 'userid');

        return $OUTPUT->user_picture($user, ['popup' => true]) . fullname($user);
    }

    /**
     * The application date column.
     *
     * @param stdClass $row Row data.
     * @return string Rendered cell.
     */
    public function col_applydate($row) {
        return userdate($row->applydate, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * The application comment column.
     *
     * @param stdClass $row Row data.
     * @return string Rendered cell.
     */
    public function col_applycomment($row) {
        return format_text($row->applycomment, FORMAT_PLAIN);
    }
}
