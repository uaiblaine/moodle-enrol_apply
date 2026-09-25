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

namespace enrol_apply;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * The approval queue orders its rows by something unique.
 *
 * These assert on the ORDER BY the table emits, not on the order rows come back in: a tie is
 * reordered only when the database chooses to, and at fixture size it usually does not, so a
 * row-order test passes with the tiebreaker deleted.
 *
 * {@see \enrol_apply\table\applications::get_sort_columns()} has the rationale.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply\table\applications::class)]
final class sort_order_test extends \advanced_testcase {
    /**
     * Give the page a url before anything renders the queue.
     *
     * The queue's table is dynamic, and get_dynamic_table_html_end() builds its "show all" link
     * from $PAGE->url - so rendering one without a page url makes core emit a debugging() call,
     * which advanced_testcase turns into a notice. manage.php always sets it; a test that renders
     * the table is standing in for that page.
     *
     * @return void
     */
    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php'));
    }

    /**
     * Build the table for the site-wide scope, set up as a page would.
     *
     * setup() reads the sort parameters from the request and is what makes get_sort_columns()
     * callable at all, so it cannot be skipped.
     *
     * Built through for_scope() because a dynamic table has no bare state: set_filterset()
     * resolves the scope and defines the columns, so without it there is nothing to sort by. The
     * site-wide scope, as admin, needs no fixture.
     *
     * define_baseurl() is deliberately not called: guess_base_url() sets it from the scope, and
     * calling it here would mask a broken one.
     *
     * @param array $sortdata Sort items as flexible_table::set_sortdata() takes them.
     * @return \table_sql The table, set up.
     */
    protected function table(array $sortdata = []): \table_sql {
        $this->setAdminUser();

        $table = \enrol_apply\table\applications::for_scope(0);
        if ($sortdata) {
            $table->set_sortdata($sortdata);
        }

        ob_start();
        $table->setup();
        ob_end_clean();

        return $table;
    }

    /**
     * The default sort ends in a unique key.
     *
     * @return void
     */
    public function test_the_default_sort_ends_in_a_unique_key(): void {
        $this->resetAfterTest();

        $sort = $this->table()->get_sql_sort();

        $this->assertMatchesRegularExpression('/\bue\.id ASC\b[^,]*$/', $sort, $sort);
    }

    /**
     * So does every sort the operator can ask for by clicking a heading.
     *
     * A narrower fix misses this half: gradereport_history appends its unique key only when the
     * sort is exactly the default one, so any other heading drops it.
     *
     * @return void
     */
    public function test_every_sort_the_operator_can_choose_ends_in_a_unique_key(): void {
        $this->resetAfterTest();

        /* Both the columns and their sortability are read off the table, so this list cannot
           drift from what the table actually offers when a column is added. */
        $table = $this->table();
        $sortable = array_filter(
            array_keys($table->columns),
            static fn(string $column): bool => $table->is_sortable($column)
        );

        /* Control: an empty or truncated list would make the loop below assert nothing. The
           site-wide scope sorts by course, fullname and applydate; the identity fields are not
           columns, they sit inside the applicant's cell. */
        $this->assertGreaterThanOrEqual(3, count($sortable), implode(', ', $sortable));

        foreach ($sortable as $column) {
            foreach ([SORT_ASC, SORT_DESC] as $order) {
                $sort = $this->table([['sortby' => $column, 'sortorder' => $order]])->get_sql_sort();

                $this->assertMatchesRegularExpression(
                    '/\bue\.id ASC\b[^,]*$/',
                    $sort,
                    'sorted by ' . $column . ': ' . $sort
                );
                // The control: the column the operator picked really is in the sort, so the
                // assertion above is not being satisfied by a sort that ignored the click.
                $this->assertStringContainsString($column, $sort, $sort);
            }
        }
    }

    /**
     * The tiebreaker is the last key, not merely present somewhere.
     *
     * A unique key in front of the operator's own choice would order the table by row id and
     * ignore what they clicked.
     *
     * @return void
     */
    public function test_the_unique_key_comes_last(): void {
        $this->resetAfterTest();

        $sort = $this->table([['sortby' => 'course', 'sortorder' => SORT_ASC]])->get_sql_sort();

        /* Matched by prefix rather than by equality: construct_order_by() appends the driver's
           own NULL ordering, so PostgreSQL gives "ue.id ASC NULLS FIRST" where MariaDB gives
           "ue.id ASC", and CI runs both. It is harmless either way - ue.id is a primary key. */
        $keys = array_map('trim', explode(',', $sort));
        $this->assertStringStartsWith('ue.id ASC', end($keys), $sort);
        $this->assertGreaterThan(1, count($keys), $sort);
    }

    /**
     * The queue still returns the rows it is supposed to, tiebreaker and all.
     *
     * Proves the tiebreaker is valid SQL on both database families, which an assertion over the
     * string cannot: `ue.id` is the raw column, and the SELECT list aliases it to `userenrolmentid`.
     *
     * @return void
     */
    public function test_the_queue_still_runs_with_the_tiebreaker_in_place(): void {
        global $DB;

        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $plugin = enrol_get_plugin('apply');
        $course = $this->getDataGenerator()->create_course();
        $instanceid = $plugin->add_instance($course, $plugin->get_instance_defaults());
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $expected = [];
        foreach (range(1, 3) as $ignored) {
            $user = $this->getDataGenerator()->create_user();
            $plugin->enrol_user($instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
            $expected[] = (int) $user->id;
        }
        // Every application in the same second, which is what the tiebreaker exists for.
        $DB->set_field('user_enrolments', 'timecreated', 1700000000, ['enrolid' => $instance->id]);

        $this->setAdminUser();
        $table = \enrol_apply\table\applications::for_scope((int) $instance->id);
        ob_start();
        $table->out(50, false);
        ob_end_clean();

        $this->assertEqualsCanonicalizing(
            $expected,
            array_map(static fn($row) => (int) $row->userid, array_values($table->rawdata))
        );
    }
}
