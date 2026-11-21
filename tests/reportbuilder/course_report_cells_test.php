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
 * Tests for the course report's cells of stage R1: wait, decided, group, outcome and method.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\reportbuilder;

use context_course;
use core_reportbuilder\local\filters\duration;
use core_reportbuilder\system_report_factory;
use enrol_apply\local\submission;
use enrol_apply\reportbuilder\local\entities\submission as submissionentity;
use enrol_apply\reportbuilder\local\formatters\submission as submissionformatter;
use enrol_apply\reportbuilder\local\systemreports\course_applications;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the course report's cells of stage R1 (docs/design/audit-report-study.md, 3.0).
 *
 * Every cell is read as the download writes it, through core's export transform, so a cell whose
 * markup would lose a line or a character there fails here as well as on screen.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_applications::class)]
#[CoversClass(submissionentity::class)]
#[CoversClass(submissionformatter::class)]
final class course_report_cells_test extends \advanced_testcase {
    /** @var int The frozen "now" every test reads. */
    private const NOW = 1893456000;

    /** @var \stdClass Course carrying the apply instance. */
    private $course;

    /** @var \stdClass The enrol_apply instance record. */
    private $instance;

    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /**
     * A course with an enabled apply instance, and core's clock frozen.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(self::NOW);

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Seed one application record.
     *
     * @param array $fields Fields of the record to set, over a pending record made now.
     * @param array $user Fields of the applicant to create.
     * @return \stdClass The applicant.
     */
    private function seed(array $fields = [], array $user = []): \stdClass {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user($user);
        $DB->insert_record('enrol_apply_submission', (object) ($fields + [
            'courseid' => $this->course->id,
            'userid' => $applicant->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => 0,
            'comment' => '',
            'status' => submission::STATUS_PENDING,
            'outcomemessage' => '',
            'keygroupid' => 0,
            'keyoutcome' => submission::KEY_NONE,
            'timecreated' => self::NOW,
            'timedecided' => 0,
            'decidedby' => 0,
        ]));

        return $applicant;
    }

    /**
     * A reader of the report with a role of their own: no moodle/course:viewhiddengroups.
     *
     * @return \stdClass The reader.
     */
    private function reader(): \stdClass {
        $context = context_course::instance($this->course->id);
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('enrol/apply:viewreports', CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $user->id, $context->id);
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id);

        return $user;
    }

    /**
     * The course report for the course under test.
     *
     * @return \core_reportbuilder\system_report The report.
     */
    private function report(): \core_reportbuilder\system_report {
        return system_report_factory::create(course_applications::class, context_course::instance($this->course->id));
    }

    /**
     * The cells the current user reads, by applicant, each as the download writes it.
     *
     * @param \core_reportbuilder\system_report|null $report Report to read, null for a fresh one.
     * @param bool $exported Whether to pass each cell through core's export transform.
     * @return array Applicant id => (unique identifier => cell), in the report's row order.
     */
    private function cells(?\core_reportbuilder\system_report $report = null, bool $exported = true): array {
        $report = $report ?? $this->report();
        $table = \core_reportbuilder\table\system_report_table::create((int) $report->get_report_persistent()->get('id'), []);
        $table->define_baseurl(new \moodle_url('/enrol/apply/report.php'));
        $table->setup();
        $table->query_db(100, false);

        // The constructor takes the table by reference, so it must be a variable.
        $probe = new \core_table\flexible_table('enrol_apply_export_probe');
        $export = new \core_table\base_export_format($probe);
        $cells = [];
        foreach ($table->rawdata as $record) {
            $formatted = $table->format_row($record);
            $row = [];
            foreach ($report->get_active_columns_by_alias() as $alias => $column) {
                $cell = (string) ($formatted[$alias] ?? '');
                $row[$column->get_unique_identifier()] = $exported ? $export->format_text($cell) : $cell;
            }
            $cells[(int) $record->userid] = $row;
        }
        $table->close_recordset();
        $this->assertDebuggingNotCalled();

        return $cells;
    }

    /**
     * A date as the short form userdate() writes.
     *
     * @param int $time Timestamp.
     * @return string The short date.
     */
    private function short(int $time): string {
        return userdate($time, get_string('strftimedatetimeshort', 'core_langconfig'));
    }

    /**
     * The wait runs to the first decision, or to now while there is none, and says so.
     *
     * Changes that must make it fail: the SQL's pending branch not measuring to now (gate RB5), or
     * the cell not marking an undecided wait as open.
     *
     * @return void
     */
    public function test_the_wait_runs_to_the_decision_or_to_now(): void {
        $decided = $this->seed([
            'status' => submission::STATUS_APPROVED,
            'timecreated' => self::NOW - 10 * DAYSECS,
            'timedecided' => self::NOW - 7 * DAYSECS - 4 * HOURSECS,
            'timefirstdecided' => self::NOW - 7 * DAYSECS - 4 * HOURSECS,
        ]);
        $pending = $this->seed(['timecreated' => self::NOW - 4 * DAYSECS]);
        $minutes = $this->seed(['timecreated' => self::NOW - 20 * MINSECS]);
        $long = $this->seed([
            'status' => submission::STATUS_CANCELLED,
            'timecreated' => self::NOW - 40 * DAYSECS,
            'timedecided' => self::NOW - 25 * DAYSECS + 5 * HOURSECS,
            'timefirstdecided' => self::NOW - 25 * DAYSECS + 5 * HOURSECS,
        ]);
        $this->setAdminUser();

        $cells = $this->cells();
        $this->assertSame('2 d 20 h', $cells[$decided->id]['submission:waittime']);
        $this->assertSame('4 d, open', $cells[$pending->id]['submission:waittime']);
        $this->assertSame('20 min, open', $cells[$minutes->id]['submission:waittime']);
        // Ten days or more read in days alone.
        $this->assertSame('15 d', $cells[$long->id]['submission:waittime']);
    }

    /**
     * The wait sorts in SQL, the open ones by their age as of now.
     *
     * @return void
     */
    public function test_the_wait_sorts_by_the_seconds_waited(): void {
        $two = $this->seed(['timecreated' => self::NOW - 2 * DAYSECS]);
        $five = $this->seed([
            'status' => submission::STATUS_APPROVED,
            'timecreated' => self::NOW - 9 * DAYSECS,
            'timedecided' => self::NOW - 4 * DAYSECS,
        ]);
        $one = $this->seed(['timecreated' => self::NOW - DAYSECS]);
        $this->setAdminUser();

        $report = $this->report();
        $this->assertTrue($report->get_column('submission:waittime')->get_is_sortable());
        $table = \core_reportbuilder\table\system_report_table::create((int) $report->get_report_persistent()->get('id'), []);
        $table->define_baseurl(new \moodle_url('/enrol/apply/report.php'));
        $alias = array_search('submission:waittime', array_map(
            static fn($column) => $column->get_unique_identifier(),
            $report->get_active_columns_by_alias()
        ), true);
        $table->set_sortdata([['sortby' => $alias, 'sortorder' => SORT_ASC]]);
        $table->setup();
        $table->query_db(100, false);
        $order = [];
        foreach ($table->rawdata as $record) {
            $order[] = (int) $record->userid;
        }
        $table->close_recordset();

        $this->assertSame([(int) $one->id, (int) $two->id, (int) $five->id], $order);
    }

    /**
     * "Waited more than N days" keeps the applications past it, open ones included.
     *
     * The short wait is the control: without it, the assertion would pass with no filter applied.
     *
     * @return void
     */
    public function test_the_wait_filter_keeps_the_applications_that_waited_longer(): void {
        $short = $this->seed(['timecreated' => self::NOW - DAYSECS]);
        $open = $this->seed(['timecreated' => self::NOW - 4 * DAYSECS]);
        $decided = $this->seed([
            'status' => submission::STATUS_APPROVED,
            'timecreated' => self::NOW - 20 * DAYSECS,
            'timedecided' => self::NOW - 15 * DAYSECS,
        ]);
        $this->setAdminUser();

        $report = $this->report();
        $this->assertArrayHasKey('submission:waittime', $report->get_filters());
        $this->assertCount(3, $this->cells($report));

        $report->set_filter_values([
            'submission:waittime_operator' => duration::DURATION_MINIMUM,
            'submission:waittime_value' => 3,
            'submission:waittime_unit' => DAYSECS,
        ]);

        $this->assertEqualsCanonicalizing([(int) $open->id, (int) $decided->id], array_keys($this->cells($report)));
        $this->assertNotContains((int) $short->id, array_keys($this->cells($report)));
    }

    /**
     * The decided cell gives the date and the decider, or "Not yet".
     *
     * The decider's name comes from the entity's own join, so it needs no user entity beside it,
     * and a name holding "<" survives the download: it sits on a line with no tag.
     *
     * @return void
     */
    public function test_the_decided_cell_names_the_decider_under_the_date(): void {
        global $DB;

        // Written past the generator, which cleans a name; a restore or an auth plugin need not.
        $decider = $this->getDataGenerator()->create_user(['firstname' => 'Maria', 'lastname' => 'Souza']);
        $DB->set_field('user', 'lastname', 'R<D Souza', ['id' => $decider->id]);
        $decidedat = self::NOW - 3 * HOURSECS;
        $decided = $this->seed([
            'status' => submission::STATUS_APPROVED,
            'timecreated' => self::NOW - DAYSECS,
            'timedecided' => $decidedat,
            'decidedby' => $decider->id,
        ]);
        $pending = $this->seed();
        $nodecider = $this->seed([
            'status' => submission::STATUS_CANCELLED,
            'timecreated' => self::NOW - DAYSECS,
            'timedecided' => $decidedat,
        ]);
        $this->setAdminUser();

        $cells = $this->cells();
        $this->assertSame($this->short($decidedat) . "\nby Maria R<D Souza", $cells[$decided->id]['submission:decided']);
        $this->assertSame(get_string('decidednotyet', 'enrol_apply'), $cells[$pending->id]['submission:decided']);
        $this->assertSame($this->short($decidedat), $cells[$nodecider->id]['submission:decided']);

        // The full date on screen, in the title.
        $raw = $this->cells(null, false);
        $this->assertStringContainsString('title="' . s(userdate($decidedat)) . '"', $raw[$decided->id]['submission:decided']);
        $this->assertStringContainsString('R&lt;D Souza', $raw[$decided->id]['submission:decided']);
        $this->assertTrue($this->report()->get_column('submission:decided')->get_is_sortable());
    }

    /**
     * An admission by a key reads "By key" in the wait and names the key's group in the decided
     * cell only to a reader who may see that group.
     *
     * Changes that must make it fail: the decided cell naming the key's group without
     * formatter::key_group()'s rule (gate RB2).
     *
     * @return void
     */
    public function test_a_key_admission_names_its_group_only_to_who_may_see_it(): void {
        global $DB;

        $open = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Evening A']);
        $covert = $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'Turma Covert',
            'visibility' => GROUPS_VISIBILITY_NONE,
        ]);
        $admitted = [];
        foreach (['open' => $open, 'covert' => $covert] as $label => $group) {
            // A live, active enrolment, so the outcome describes it rather than the record alone.
            $user = $this->getDataGenerator()->create_user();
            $this->plugin->enrol_user($this->instance, $user->id, null, 0, 0, ENROL_USER_ACTIVE);
            $this->seed([
                'userid' => $user->id,
                'userenrolmentid' => $DB->get_field(
                    'user_enrolments',
                    'id',
                    ['enrolid' => $this->instance->id, 'userid' => $user->id],
                    MUST_EXIST
                ),
                'status' => submission::STATUS_APPROVED,
                'keygroupid' => $group->id,
                'keyoutcome' => submission::KEY_ADMITTED,
                'timecreated' => self::NOW - HOURSECS,
                'timedecided' => self::NOW - HOURSECS,
                'decidedby' => $user->id,
            ]);
            $admitted[$label] = $user;
        }
        $this->setUser($this->reader());

        $cells = $this->cells();
        $date = $this->short(self::NOW - HOURSECS);
        $this->assertSame(get_string('waitbykey', 'enrol_apply'), $cells[$admitted['open']->id]['submission:waittime']);
        $this->assertSame($date . "\nBy key: Evening A", $cells[$admitted['open']->id]['submission:decided']);
        $this->assertSame($date . "\nBy key", $cells[$admitted['covert']->id]['submission:decided']);
        $this->assertSame('', $cells[$admitted['covert']->id]['submission:group']);
        $this->assertStringContainsString(
            'enrol_apply-status--brand',
            $this->cells(null, false)[$admitted['open']->id]['submission:outcome']
        );

        // The control: a reader who sees every group is told the hidden one's name.
        $this->setAdminUser();
        $this->assertSame($date . "\nBy key: Turma Covert", $this->cells()[$admitted['covert']->id]['submission:decided']);
    }

    /**
     * The group cell names the key's group or the chosen one, then the groups joined, each only
     * to a reader who may see it.
     *
     * Changes that must make it fail: the cell naming the key's group or the chosen group without
     * its column's visibility rule (gates RB1, RB3).
     *
     * @return void
     */
    public function test_the_group_cell_names_only_the_groups_the_reader_may_see(): void {
        if (!\enrol_apply\local\instanceoptions::schema_ready()) {
            $this->markTestSkipped('The chosen group needs its upgrade.');
        }
        $generator = $this->getDataGenerator();
        $open = $generator->create_group(['courseid' => $this->course->id, 'name' => 'R&D Team']);
        $other = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Morning C']);
        $covert = $generator->create_group([
            'courseid' => $this->course->id,
            'name' => 'Turma Covert',
            'visibility' => GROUPS_VISIBILITY_NONE,
        ]);

        $key = $this->seed(['keygroupid' => $open->id, 'keyoutcome' => submission::KEY_FOR_REVIEW]);
        $hiddenkey = $this->seed(['keygroupid' => $covert->id, 'keyoutcome' => submission::KEY_FOR_REVIEW]);
        $chose = $this->seed([
            'chosengroupid' => $other->id,
            'status' => submission::STATUS_APPROVED,
            'timedecided' => self::NOW,
            'joinedgroups' => "{$other->id},{$covert->id}",
        ]);
        $hiddenchoice = $this->seed(['chosengroupid' => $covert->id]);
        $nothing = $this->seed();
        $this->setUser($this->reader());

        $cells = $this->cells();
        $this->assertSame('Key: R&D Team', $cells[$key->id]['submission:group']);
        $this->assertSame('', $cells[$hiddenkey->id]['submission:group']);
        $this->assertSame("Chose: Morning C\nJoined Morning C", $cells[$chose->id]['submission:group']);
        $this->assertSame('', $cells[$hiddenchoice->id]['submission:group']);
        $this->assertSame('', $cells[$nothing->id]['submission:group']);
        $this->assertFalse($this->report()->get_column('submission:group')->get_is_sortable());

        // The control: every group named to a reader who may see them all.
        $this->setAdminUser();
        $cells = $this->cells();
        $this->assertSame('Key: Turma Covert', $cells[$hiddenkey->id]['submission:group']);
        $this->assertSame('Chose: Turma Covert', $cells[$hiddenchoice->id]['submission:group']);
        $this->assertSame("Chose: Morning C\nJoined Morning C, Turma Covert", $cells[$chose->id]['submission:group']);
    }

    /**
     * The outcome is one status label in a tone, with the record's own decision under it only
     * where the live enrolment has moved since.
     *
     * @return void
     */
    public function test_the_outcome_label_carries_its_tone(): void {
        $cancelled = $this->seed(['status' => submission::STATUS_CANCELLED, 'timedecided' => self::NOW]);
        $this->setAdminUser();

        $raw = $this->cells(null, false)[$cancelled->id]['submission:outcome'];
        $this->assertStringContainsString('enrol_apply-status enrol_apply-status--danger', $raw);
        $this->assertSame(get_string('outcomecancelled', 'enrol_apply'), $this->cells()[$cancelled->id]['submission:outcome']);
    }

    /**
     * The method column exists only where the course has two or more apply methods, and names
     * each record's method from one read of the instances.
     *
     * Changes that must make it fail: the column added for a single method (gate RB6).
     *
     * @return void
     */
    public function test_the_method_column_needs_two_methods(): void {
        $first = $this->seed();
        $this->setAdminUser();
        $this->assertNull($this->report()->get_column('submission:method'));

        $defaults = $this->plugin->get_instance_defaults();
        $secondid = $this->plugin->add_instance($this->course, ['name' => 'Second intake'] + $defaults);
        $second = $this->seed(['enrolid' => $secondid]);
        $gone = $this->seed(['enrolid' => $secondid + 1000]);
        \core_reportbuilder\manager::reset_caches();

        $report = $this->report();
        $this->assertNotNull($report->get_column('submission:method'));
        $cells = $this->cells($report);
        $this->assertSame(
            $this->plugin->get_instance_name($this->instance),
            $cells[$first->id]['submission:method']
        );
        $this->assertSame('Second intake', $cells[$second->id]['submission:method']);
        $this->assertSame(get_string('submissionmethodgone', 'enrol_apply'), $cells[$gone->id]['submission:method']);
    }

    /**
     * Every column carries its heading for the phone cards, and the report the attribute the
     * stylesheet keys them on.
     *
     * @return void
     */
    public function test_every_cell_carries_its_card_title(): void {
        $this->seed();
        $this->setAdminUser();

        $report = $this->report();
        $this->assertSame('course', $report->get_attributes()['data-enrol-apply-report'] ?? null);
        foreach ($report->get_active_columns() as $identifier => $column) {
            $this->assertNotEmpty($column->get_attributes()['data-cardtitle'] ?? '', $identifier);
        }
        $this->assertSame(
            get_string('reportapplicant', 'enrol_apply'),
            $report->get_column('user:fullnamewithlink')->get_attributes()['data-cardtitle']
        );
        $this->assertSame(
            get_string('waittime', 'enrol_apply'),
            $report->get_column('submission:waittime')->get_attributes()['data-cardtitle']
        );
    }

    /**
     * The duration reads in calendar minutes, hours, days and hours, or days.
     *
     * @return void
     */
    public function test_the_duration_wording(): void {
        $cases = [
            'nothing' => [0, '1 min'],
            'negative' => [-500, '1 min'],
            'minutes' => [25 * MINSECS, '25 min'],
            'hours' => [5 * HOURSECS + 10 * MINSECS, '5 h'],
            'a day' => [DAYSECS, '1 d'],
            'days and hours' => [3 * DAYSECS + 4 * HOURSECS, '3 d 4 h'],
            'rounded to the next day' => [2 * DAYSECS + 23 * HOURSECS + 50 * MINSECS, '3 d'],
            'ten days and more' => [12 * DAYSECS + 6 * HOURSECS, '12 d'],
        ];
        foreach ($cases as $label => [$seconds, $expected]) {
            $this->assertSame($expected, submissionformatter::duration($seconds), $label);
        }
    }
}
