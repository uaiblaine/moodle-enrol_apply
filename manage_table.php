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

    /** @var array Identity field names this reader may see, from \enrol_apply\local\identity. */
    protected $extrafields = [];

    /**
     * Build the table for the requested scope.
     *
     * There used to be a third parameter restricting the table to ONE user enrolment, which is
     * how a single application was shown before it got a page of its own. Since manage.php
     * tests userenrol before id and leaves that branch through the review page, the only caller
     * could only ever pass 0 for it, so the one-row mode was unreachable. It is removed rather
     * than left in place: a constructor parameter is a claim that the mode exists.
     *
     * @param int $enrolid Restrict to one enrol instance, 0 for no restriction.
     * @param array|null $mentees Restrict to these applicant user ids, null for no restriction.
     * @param string $commentlabel Heading of the comment column, ALREADY ESCAPED, empty for the
     *        shipped wording. Only the instance-scoped queue can supply one: the site-wide and
     *        mentee scopes span instances, each of which may word the question differently, so a
     *        single heading there would be true of some rows and false of others.
     * @param \context|null $identitycontext Context to judge the applicant's identity fields in,
     *        null for a scope that spans courses and therefore shows none. See
     *        \enrol_apply\local\identity for why the mentee scope is the null one.
     */
    public function __construct($enrolid = 0, $mentees = null, $commentlabel = '', ?\context $identitycontext = null) {
        global $DB;

        parent::__construct('enrol_apply_manage_table');

        // The one definition of "awaiting a decision"; see the method for both its clauses.
        [$wheres, $params] = \enrol_apply\local\queue::awaiting_decision_where();

        if ($enrolid) {
            $wheres[] = 'e.id = :enrolid';
            $params['enrolid'] = $enrolid;
        } else {
            $wheres[] = 'e.enrol = :enrol';
            $params['enrol'] = 'apply';
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

        /* The identity fields this reader may see, and the SQL for them. Everything about which
           fields those are is core's decision - see \enrol_apply\local\identity - so that the
           queue and the participants page beside it cannot answer differently. */
        $this->extrafields = \enrol_apply\local\identity::fields($identitycontext);
        $identitysql = \enrol_apply\local\identity::sql($identitycontext, 'u');

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
                   c.id AS courseid, {$userfields}{$identitysql->selects}";
        $from = "{user_enrolments} ue
            LEFT JOIN {enrol_apply_applicationinfo} ai ON ai.userenrolmentid = ue.id
            LEFT JOIN {enrol_apply_submission} s ON s.userenrolmentid = ue.id
                 JOIN {user} u ON u.id = ue.userid
                 JOIN {enrol} e ON e.id = ue.enrolid
                 JOIN {course} c ON c.id = e.courseid
                 {$identitysql->joins}";

        $this->set_sql($fields, $from, implode(' AND ', $wheres), $params + $identitysql->params);

        $this->define_table_columns($commentlabel);
    }

    /**
     * The sort, with a unique final key so that two applications never trade places.
     *
     * Every column this table offers can tie. `applydate` is `ue.timecreated`, which
     * `enrol_plugin::enrol_user()` writes as whole Unix seconds, so a cohort admitted by one
     * script or one busy minute shares a value - measured on the live 5.2 site, three pending
     * applications already do. `course`, `fullname` and `email` tie more easily still. With no
     * unique key the database is free to return a tied group in any order it likes, and it does
     * not have to make the same choice twice: each page of a paged table is a separately planned
     * statement with its own LIMIT and OFFSET, so a row can appear on two pages while another
     * appears on none. Measured on PostgreSQL 17 over a tied 100-row set: 11 rows duplicated,
     * 11 never shown, and adding a unique final key gave exactly 100 distinct rows.
     *
     * Core's own fallback cannot cover this. `set_sorting_preferences()` appends
     * `sort_default_column` when it is missing, and here that column IS `applydate` - so
     * clicking any other header gives "<that column> ASC, applydate ASC", which is two keys that
     * both tie, and clicking `applydate` itself appends nothing at all.
     *
     * This method, and not `get_sql_sort()` or `construct_order_by()`, is the injection point.
     * `construct_order_by()` is static and reached through `self::`, which is early bound, so an
     * override of it is never called. Appending to `get_sql_sort()`'s string instead would put a
     * raw fragment after core's per-driver NULL ordering, which is not portable. The shape below
     * is core's own, in `tool_policy`, `mod_quiz` and `mod_assign`.
     *
     * The raw `ue.id` and not the `userenrolmentid` alias: both work on PostgreSQL and MariaDB,
     * but the raw column is what core uses and it does not depend on the SELECT list.
     *
     * @return array Column name => SORT_ASC or SORT_DESC, ending in a unique key.
     */
    public function get_sort_columns() {
        $sortcolumns = parent::get_sort_columns();
        $sortcolumns['ue.id'] = SORT_ASC;

        return $sortcolumns;
    }

    /**
     * Declare the columns and their headings.
     *
     * @param string $commentlabel Heading of the comment column, already escaped, empty for the default.
     * @return void
     */
    protected function define_table_columns($commentlabel = '') {
        $columns = ['checkboxcolumn', 'course', 'fullname'];
        $headers = [
            $this->select_all_header(),
            get_string('course'),
            // The heading of a column named 'fullname' is filled in by table_sql itself.
            'fullname',
        ];

        /* The e-mail column used to sit here unconditionally, which is what made the queue
           disclose more than the participants page next to it. It is now one identity field among
           whichever ones the site configured and this reader may see - so on a default site it is
           still here, and on a site that hides it, it is not. */
        foreach ($this->extrafields as $field) {
            $columns[] = $field;
            $headers[] = \core_user\fields::get_display_name($field);
        }

        $columns[] = 'applydate';
        $headers[] = get_string('applydate', 'enrol_apply');

        $columns[] = 'applycomment';
        /* The escaped spelling, because print_headers() emits this through html_writer::tag(),
           which concatenates its content without escaping it. The caller supplies it that way. */
        $headers[] = $commentlabel !== '' ? $commentlabel : get_string('applycomment', 'enrol_apply');

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
     * Render an identity column.
     *
     * **This is the escaping boundary and it is easy to miss.** flexible_table writes
     * `$row->$column` into the cell with no escaping of its own, so an identity field - which is
     * user-controlled text - reaches the markup raw unless something escapes it here. Core's own
     * participants table closes exactly this, with exactly this method, returning s() of the value;
     * this is that shape. The queue's other columns each have a col_*() method doing their own
     * escaping, which is why only the identity ones need this.
     *
     * @param string $colname Column being rendered.
     * @param stdClass $row Row data.
     * @return string Rendered cell, or the empty string for a column this method does not own.
     */
    public function other_cols($colname, $row) {
        if (!in_array($colname, $this->extrafields, true)) {
            return '';
        }

        return s($row->{$colname});
    }

    /**
     * No initials BAR either, whatever the caller asks for.
     *
     * The other half of the override below, and the two are genuinely separate:
     * get_sql_where() stops the filter narrowing the query, this stops the control being drawn.
     * Killing only the filter would leave an A-Z bar on the page that silently does nothing when
     * clicked, which is worse than either end state.
     *
     * It is an override rather than a call because the argument is not the caller's to make here:
     * renderer::capture_table() passes true to out(), and on core's dynamic-table path
     * external\dynamic\get calls out($pagesize, true) unconditionally. Forcing it false at the
     * source is what survives both.
     *
     * With use_initials false, get_initial_first() and get_initial_last() return null, so
     * print_initials_bar()'s condition is false on all three of its terms and nothing is drawn.
     *
     * @param bool $bool Ignored.
     * @return void
     */
    public function initialbars($bool) {
        parent::initialbars(false);
    }

    /**
     * No initials filter, on either path.
     *
     * Rendering with `out(50, false)` hides the A-Z bar and does nothing else: get_sql_where()
     * reads `prefs['i_first']` and `prefs['i_last']` and never consults `use_initials`, and
     * table_sql::query_db() appends the result to both the count and the data query. Measured
     * against the real queue: with a stored `i_first = 'Z'` and no bar anywhere on the page, a
     * three-row queue returned nothing, with no control on screen able to explain it. The
     * preference lives in $SESSION->flextable, so it survives page loads and is invisible.
     *
     * Overriding this is the complete kill. Emptying $userfullnamecolumns also stops the filter
     * and silently costs the fullname column its firstname/lastname sub-sort links, which is why
     * it is not what this does.
     *
     * @return array Empty where clause and no parameters.
     */
    public function get_sql_where() {
        return ['', []];
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
