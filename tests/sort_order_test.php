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
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/apply/manage_table.php');
require_once($CFG->dirroot . '/enrol/apply/info_table.php');

/**
 * Both listings order their rows by something unique.
 *
 * These assert on the ORDER BY the table emits, deliberately, and not on the order rows come
 * back in. A tie only reorders when the database chooses to reorder it, and at fixture size it
 * usually does not: the same five rows on the live 5.2 site page cleanly today while carrying
 * three applications that share a timestamp. A row-order test therefore passes with the
 * tiebreaker deleted, which is the whole reason this defect survived until now.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_manage_table::class)]
#[CoversClass(\enrol_apply_info_table::class)]
final class sort_order_test extends \advanced_testcase {
    /**
     * The two listings, and the sortable columns each offers.
     *
     * @return array Table class => the sortable column names it defines.
     */
    public static function table_provider(): array {
        return [
            'approval queue' => [\enrol_apply_manage_table::class, ['course', 'fullname', 'email', 'applydate']],
            'submitted comments' => [\enrol_apply_info_table::class, ['fullname', 'applydate']],
        ];
    }

    /**
     * Build a table of the given class, set up as a page would.
     *
     * setup() reads the sort parameters from the request and is what makes get_sort_columns()
     * callable at all, so it cannot be skipped.
     *
     * @param string $class Table class to build.
     * @param array $sortdata Sort items as flexible_table::set_sortdata() takes them.
     * @return \table_sql The table, set up.
     */
    protected function table(string $class, array $sortdata = []): \table_sql {
        $table = new $class();
        $table->define_baseurl(new \moodle_url('/enrol/apply/manage.php'));
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
     * @param string $class Table class under test.
     * @return void
     */
    #[DataProvider('table_provider')]
    public function test_the_default_sort_ends_in_a_unique_key(string $class): void {
        $this->resetAfterTest();

        $sort = $this->table($class)->get_sql_sort();

        $this->assertMatchesRegularExpression('/\bue\.id ASC\b[^,]*$/', $sort, $class . ': ' . $sort);
    }

    /**
     * So does every sort the operator can ask for by clicking a heading.
     *
     * This is the half core's own fallback cannot reach and the half a narrower fix misses:
     * gradereport_history appends its unique key only when the sort is exactly the default one,
     * so clicking any other heading silently drops it.
     *
     * @param string $class Table class under test.
     * @param array $columns Every sortable column the table defines.
     * @return void
     */
    #[DataProvider('table_provider')]
    public function test_every_sort_the_operator_can_choose_ends_in_a_unique_key(
        string $class,
        array $columns
    ): void {
        $this->resetAfterTest();

        foreach ($columns as $column) {
            foreach ([SORT_ASC, SORT_DESC] as $order) {
                $sort = $this->table($class, [['sortby' => $column, 'sortorder' => $order]])->get_sql_sort();

                $this->assertMatchesRegularExpression(
                    '/\bue\.id ASC\b[^,]*$/',
                    $sort,
                    $class . ' sorted by ' . $column . ': ' . $sort
                );
                // The control: the column the operator picked really is in the sort, so the
                // assertion above is not being satisfied by a sort that ignored the click.
                $this->assertStringContainsString($column, $sort, $class . ': ' . $sort);
            }
        }
    }

    /**
     * The tiebreaker is the LAST key, not merely present somewhere.
     *
     * A unique key in front of the operator's own choice would order the table by row id and
     * ignore what they clicked, which is a different defect with the same test passing.
     *
     * @return void
     */
    public function test_the_unique_key_comes_last(): void {
        $this->resetAfterTest();

        $sort = $this->table(
            \enrol_apply_manage_table::class,
            [['sortby' => 'course', 'sortorder' => SORT_ASC]]
        )->get_sql_sort();

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
     * The point of this one is that the new ORDER BY term is valid SQL against both database
     * families CI runs, which an assertion over a string cannot tell you: `ue.id` is the raw
     * column and the SELECT list aliases it to `userenrolmentid`.
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

        $table = new \enrol_apply_manage_table($instance->id);
        $table->define_baseurl(new \moodle_url('/enrol/apply/manage.php'));
        ob_start();
        $table->out(50, false);
        ob_end_clean();

        $this->assertEqualsCanonicalizing(
            $expected,
            array_map(static fn($row) => (int) $row->userid, array_values($table->rawdata))
        );
    }
}
