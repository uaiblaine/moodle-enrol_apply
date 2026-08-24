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
 * Table listing the enrolment applications awaiting a decision.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  2016 sudile GbR (http://www.sudile.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Johannes Burk <johannes.burk@sudile.com>
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Table listing the enrolment applications awaiting a decision.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  2016 sudile GbR (http://www.sudile.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_apply_manage_table extends table_sql {
    /**
     * @var string The core/checkbox-toggleall group tying the header checkbox, rows and bulk bar.
     *
     * getActionElements() matches this EXACTLY while the targets match by prefix, so the bar's
     * control has to carry the same string character for character. It also must not be a
     * prefix of any other group on the page: Report Builder uses 'report-select-all', which
     * does not collide, and nothing else on manage.php renders one.
     */
    public const TOGGLE_GROUP = 'enrol-apply-queue';

    /**
     * Build the table for the requested scope.
     *
     * @param int $enrolid Restrict to one enrol instance, 0 for no restriction.
     * @param int $userenrolmentid Restrict to one user enrolment, 0 for no restriction.
     * @param array|null $mentees Restrict to these applicant user ids, null for no restriction.
     */
    public function __construct($enrolid = 0, $userenrolmentid = 0, $mentees = null) {
        global $DB;

        parent::__construct('enrol_apply_manage_table');

        /* An undecided application is "not active AND has not expired". The second half
           matters because process_expirations() re-suspends an ACTIVE enrolment whose
           period ran out when expiredaction is "suspend" (lib/enrollib.php), and a
           re-suspended row would otherwise surface here as a fresh application from a
           user who was in fact approved long ago. Pending and waiting-list rows always
           carry timeend = 0, because apply() never stamps a period and wait_enrolment()
           does not touch the dates; only a once-approved row can have one. */
        $wheres = ['ue.status != :active', '(ue.timeend = 0 OR ue.timeend > :now)'];
        $params = ['active' => ENROL_USER_ACTIVE, 'now' => time()];

        if ($enrolid) {
            $wheres[] = 'e.id = :enrolid';
            $params['enrolid'] = $enrolid;
        } else {
            $wheres[] = 'e.enrol = :enrol';
            $params['enrol'] = 'apply';
        }

        if ($userenrolmentid) {
            $wheres[] = 'ue.id = :userenrolmentid';
            $params['userenrolmentid'] = $userenrolmentid;
        }

        if ($mentees !== null) {
            if (!$mentees) {
                // No mentees means nothing to show; a never-true predicate keeps the SQL valid.
                $wheres[] = '1 = 0';
            } else {
                [$insql, $inparams] = $DB->get_in_or_equal($mentees, SQL_PARAMS_NAMED, 'mentee');
                $wheres[] = "ue.userid {$insql}";
                $params += $inparams;
            }
        }

        $userfieldsapi = \core_user\fields::for_userpic()->including('username');
        $userfields = $userfieldsapi->get_sql('u', false, '', 'userid', false)->selects;

        /* The comment is read from the durable record first and from the application info
           row only as a fallback. The two hold the same text, but the application info row is
           deleted the moment a decision is taken, and a decided enrolment can come back to
           this queue: suspending an approved participant from core's participants page
           leaves status != active with timeend = 0, which is exactly the predicate above.
           Before this join those rows showed an empty comment; the fallback is what keeps
           applications that predate the durable record readable.

           Joined on the user enrolment and not on courseid + userid, which is the natural
           key of that table but is deliberately not unique: an applicant who was cancelled
           and applied again has two records for the same course, and joining on the pair
           would show each of their rows twice. */
        $fields = "ue.id AS userenrolmentid, ue.status AS enrolstatus, ue.timecreated AS applydate,
                   COALESCE(s.comment, ai.comment) AS applycomment, c.fullname AS course,
                   c.id AS courseid, {$userfields}";
        $from = "{user_enrolments} ue
            LEFT JOIN {enrol_apply_applicationinfo} ai ON ai.userenrolmentid = ue.id
            LEFT JOIN {enrol_apply_submission} s ON s.userenrolmentid = ue.id
                 JOIN {user} u ON u.id = ue.userid
                 JOIN {enrol} e ON e.id = ue.enrolid
                 JOIN {course} c ON c.id = e.courseid";

        $this->set_sql($fields, $from, implode(' AND ', $wheres), $params);

        $this->define_table_columns();
    }

    /**
     * Declare the columns and their headings.
     *
     * @return void
     */
    protected function define_table_columns() {
        $columns = ['checkboxcolumn', 'course', 'fullname', 'email', 'applydate'];
        $headers = [
            $this->select_all_header(),
            get_string('course'),
            // The heading of a column named 'fullname' is filled in by table_sql itself.
            'fullname',
            get_string('email'),
            get_string('applydate', 'enrol_apply'),
        ];

        $columns[] = 'applycomment';
        $headers[] = get_string('applycomment', 'enrol_apply');

        $this->define_columns($columns);
        $this->define_headers($headers);
        /* Names the cell that identifies each row, so table_sql emits it as a
           <th scope="row"> and a screen reader announces every other cell of the row
           against the applicant's name rather than reading a wall of bare values. */
        $this->define_header_column('fullname');
        $this->no_sorting('checkboxcolumn');
        $this->no_sorting('applycomment');
        $this->sortable(true, 'applydate', SORT_ASC);
    }

    /**
     * The select-all checkbox shown in the header of the checkbox column.
     *
     * Core's own renderable, so the queue is driven by core/checkbox-toggleall rather than by
     * markup of this plugin's invention. The label text is pinned to the same string in both
     * directions on purpose: the module rewrites the label's innerHTML on every toggle, and a
     * header cell whose label alternates between "Select all" and "Deselect all" changes width
     * under the reader. Core does the same, with the same comment, in the gradebook and in the
     * participation report.
     *
     * @return string Rendered checkbox with its accessible label.
     */
    protected function select_all_header() {
        global $OUTPUT;

        $selectall = get_string('selectall');

        return $OUTPUT->render(new \core\output\checkbox_toggleall(self::TOGGLE_GROUP, true, [
            'id' => 'enrol_apply_toggleall',
            'name' => 'enrol_apply_toggleall',
            'label' => $selectall,
            'labelclasses' => 'visually-hidden',
            'classes' => 'm-1',
            'checked' => false,
            'selectall' => $selectall,
            'deselectall' => $selectall,
        ]));
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
     * The per-row selection checkbox.
     *
     * @param stdClass $row Row data.
     * @return string Rendered checkbox with its accessible label.
     */
    public function col_checkboxcolumn($row) {
        global $OUTPUT;

        /* The name, the value and the label are unchanged from the hand-written markup this
           replaces, which is what keeps the POST contract (userenrolments[]) and the Behat
           locator ("Select Student 1") intact. Adopting core's renderable changes the data
           attributes, not the contract. */
        return $OUTPUT->render(new \core\output\checkbox_toggleall(self::TOGGLE_GROUP, false, [
            'id' => 'enrol_apply_ue_' . $row->userenrolmentid,
            'name' => 'userenrolments[]',
            'value' => $row->userenrolmentid,
            'label' => get_string('selectapplicant', 'enrol_apply', fullname($row)),
            'labelclasses' => 'visually-hidden',
        ]));
    }

    /**
     * The applicant name column, with the user picture.
     *
     * @param stdClass $row Row data carrying the aliased user picture fields.
     * @return string Rendered cell.
     */
    public function col_fullname($row) {
        global $OUTPUT;

        $user = user_picture::unalias($row, ['username'], 'userid');

        return $OUTPUT->user_picture($user, ['popup' => true]) . fullname($user);
    }

    /**
     * The course column, linking to the course.
     *
     * @param stdClass $row Row data.
     * @return string Rendered cell.
     */
    public function col_course($row) {
        $url = new moodle_url('/course/view.php', ['id' => $row->courseid]);

        return html_writer::link($url, format_string($row->course), ['target' => '_blank']);
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
