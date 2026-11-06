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
 * Tests for the report's groups-joined column and group filter.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\reportbuilder;

use context_course;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\manager;
use enrol_apply\local\decisionlog;
use enrol_apply\local\groupscope;
use enrol_apply\local\submission;
use enrol_apply\reportbuilder\datasource\applications;
use enrol_apply\reportbuilder\local\filters\group as groupfilter;
use enrol_apply\reportbuilder\local\formatters\submission as submissionformatter;
use enrol_apply\reportbuilder\local\systemreports\course_applications;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the approval route and the group API are loaded for every test.
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

/**
 * The course report's "Groups joined" column and group filter, and the narrowing reading what an
 * approval joined (docs/design/group-screens.html, V5).
 *
 * The course is in separate groups mode. Readers hold enrol/apply:viewreports through a role of
 * their own, never moodle/course:viewhiddengroups, so core's visibility rule applies to them.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_applications::class)]
#[CoversClass(groupfilter::class)]
#[CoversClass(submissionformatter::class)]
#[CoversClass(groupscope::class)]
final class joined_groups_report_test extends \core_reportbuilder\tests\core_reportbuilder_testcase {
    /** @var string Unique identifier of the course report's group filter. */
    private const FILTER = 'submission:group';

    /** @var string Unique identifier of the groups-joined column. */
    private const COLUMN = 'submission:joinedgroups';

    /** @var \stdClass A course in separate groups mode. */
    private $course;

    /** @var \stdClass Its apply instance. */
    private $instance;

    /** @var \stdClass Group "Turma 2". */
    private $two;

    /** @var \stdClass Group "Turma 10", created first, so that id order and natural order disagree. */
    private $ten;

    /**
     * The course, its instance and two visible groups.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $plugin = enrol_get_plugin('apply');
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS]);
        $id = $plugin->add_instance($this->course, $plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        // Created "Turma 10" first, so ascending ids put it first and only a natural sort does not.
        $this->ten = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Turma 10']);
        $this->two = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Turma 2']);
    }

    /**
     * Forget any decision marker the approval test left.
     *
     * @return void
     */
    protected function tearDown(): void {
        decisionlog::reset();
        parent::tearDown();
    }

    /**
     * Write one record of the course.
     *
     * @param int $keygroupid The key's group, 0 for none.
     * @param string|null $joined The joined groups, comma separated; null for not known.
     * @param string $decided The decided groups, comma separated.
     * @return int The applicant's id.
     */
    private function seed(int $keygroupid, ?string $joined, string $decided = ''): int {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $user->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => 0,
            'comment' => '',
            'status' => $joined === null && $decided === '' ? submission::STATUS_PENDING : submission::STATUS_APPROVED,
            'outcomemessage' => '',
            'decidedgroups' => $decided,
            'joinedgroups' => $joined,
            'keygroupid' => $keygroupid,
            'keyoutcome' => $keygroupid ? submission::KEY_FOR_REVIEW : submission::KEY_NONE,
            'timecreated' => time(),
            'timedecided' => 0,
            'decidedby' => 0,
        ]);

        return (int) $user->id;
    }

    /**
     * A reader of the report holding enrol/apply:viewreports through a role of their own.
     *
     * @param string $shortname The role's short name, one per kind of reader.
     * @param bool $allgroups Whether the role also holds moodle/site:accessallgroups.
     * @param array $groups Groups the reader is a member of.
     * @return \stdClass The reader.
     */
    private function reader(string $shortname, bool $allgroups, array $groups = []): \stdClass {
        $system = \context_system::instance();
        $role = $this->getDataGenerator()->create_role(['shortname' => $shortname]);
        assign_capability(groupscope::REPORT, CAP_ALLOW, $role, $system->id, true);
        if ($allgroups) {
            assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $role, $system->id, true);
        }

        $user = $this->getDataGenerator()->create_user();
        role_assign($role, $user->id, context_course::instance($this->course->id)->id);
        if ($groups) {
            // Enrolled with no role: groups_add_member() refuses a user who is not enrolled.
            $this->getDataGenerator()->enrol_user($user->id, $this->course->id);
            foreach ($groups as $group) {
                $this->assertTrue(groups_add_member($group->id, $user->id));
            }
        }

        return $user;
    }

    /**
     * The course report as report.php builds it.
     *
     * @return course_applications The report.
     */
    private function report(): course_applications {
        $report = course_applications::for_method(context_course::instance($this->course->id), (int) $this->instance->id);
        $this->assertInstanceOf(course_applications::class, $report);

        return $report;
    }

    /**
     * The rows the current user reads, as applicant id => the formatted groups-joined cell.
     *
     * Read through system_report_table::create(), which re-creates the report from its persistent
     * and the reader's stored filter values, as a sort or a page turn does.
     *
     * @return array Applicant id => cell, ordered by applicant id.
     */
    private function cells(): array {
        $report = $this->report();
        $table = \core_reportbuilder\table\system_report_table::create(
            (int) $report->get_report_persistent()->get('id'),
            []
        );
        $table->define_baseurl(new \moodle_url('/enrol/apply/report.php'));
        $table->setup();
        $table->query_db(100, false);

        $alias = array_search(self::COLUMN, array_map(
            static fn($column) => $column->get_unique_identifier(),
            $report->get_active_columns_by_alias()
        ), true);
        $this->assertNotFalse($alias, 'the premise: the column is in the report');

        $cells = [];
        foreach ($table->rawdata as $record) {
            $cells[(int) $record->userid] = (string) ($table->format_row($record)[$alias] ?? 'missing');
        }
        $table->close_recordset();
        ksort($cells);

        return $cells;
    }

    /**
     * Set the group filter for the current user, as the filter form would.
     *
     * @param int $groupid The group chosen.
     * @return void
     */
    private function filter_by(int $groupid): void {
        $this->report()->set_filter_values([
            self::FILTER . '_operator' => select::EQUAL_TO,
            self::FILTER . '_value' => $groupid,
        ]);
    }

    /**
     * Applicant ids, sorted.
     *
     * @param array $ids Applicant ids.
     * @return array Sorted ids.
     */
    private function sorted(array $ids): array {
        sort($ids);

        return $ids;
    }

    /**
     * The column names the groups the reader may see, in natural order; a hidden group and a
     * deleted one are left out without a trace, a group of another course is never read, and a
     * record whose groups are hidden, unknown or none reads blank. Not sortable.
     *
     * Changes that must make it fail: the cell naming groups without the visibility rule; the
     * names in id order; the column made sortable.
     *
     * @return void
     */
    public function test_the_column_names_the_groups_the_reader_may_see(): void {
        $generator = $this->getDataGenerator();
        $covert = $generator->create_group([
            'courseid' => $this->course->id,
            'name' => 'Turma Covert',
            'visibility' => GROUPS_VISIBILITY_NONE,
        ]);
        $members = $generator->create_group([
            'courseid' => $this->course->id,
            'name' => 'Turma Members',
            'visibility' => GROUPS_VISIBILITY_MEMBERS,
        ]);
        $deleted = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Turma Deleted']);
        $deletedid = (int) $deleted->id;
        groups_delete_group($deleted);
        $elsewhere = $generator->create_group(['courseid' => $generator->create_course()->id, 'name' => 'Elsewhere']);

        $all = implode(',', [$this->ten->id, $this->two->id, $covert->id, $members->id, $deletedid, $elsewhere->id]);
        $many = $this->seed(0, $all);
        $hidden = $this->seed(0, (string) $covert->id);
        $unknown = $this->seed(0, null, (string) $this->two->id);
        $none = $this->seed(0, '');

        $reader = $this->reader('joinedcolumnreader', true);
        $this->setUser($reader);
        $this->assertFalse($this->report()->get_column(self::COLUMN)->get_is_sortable());
        $this->assertSame([
            $many => 'Turma 2, Turma 10',
            $hidden => '',
            $unknown => '',
            $none => '',
        ], $this->cells());

        // A member of a MEMBERS group is told its name.
        $generator->enrol_user($reader->id, $this->course->id);
        $this->assertTrue(groups_add_member($members->id, $reader->id));
        $this->assertSame('Turma 2, Turma 10, Turma Members', $this->cells()[$many]);

        // The control: viewhiddengroups names the hidden one too, and still not the deleted one.
        $this->setAdminUser();
        $cells = $this->cells();
        $this->assertSame('Turma 2, Turma 10, Turma Covert, Turma Members', $cells[$many]);
        $this->assertSame('Turma Covert', $cells[$hidden]);
    }

    /**
     * The filter selects, for each group, the records whose key group is it or whose joined groups
     * name it, reading the decided groups only where the joined groups are not known; with no
     * value it selects every record. The options are the groups the reader may see, in natural
     * order.
     *
     * Changes that must make it fail: the filter matching the key group alone, or the joined
     * groups alone; the decided groups read where the joined groups are known; the options in id
     * order.
     *
     * @return void
     */
    public function test_the_filter_selects_each_groups_records(): void {
        $two = (int) $this->two->id;
        $ten = (int) $this->ten->id;
        $keyed = $this->seed($two, null);
        $joined = $this->seed(0, "{$ten},{$two}");
        $joinedten = $this->seed(0, (string) $ten);
        $fallback = $this->seed(0, null, (string) $two);
        $overridden = $this->seed(0, '', (string) $two);
        $keyless = $this->seed(0, null);
        $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'Turma Covert',
            'visibility' => GROUPS_VISIBILITY_NONE,
        ]);

        $this->setUser($this->reader('joinedfilterreader', true));
        $this->assertSame([$two => 'Turma 2', $ten => 'Turma 10'], $this->report()->group_options());
        $this->assertSame(
            $this->sorted([$keyed, $joined, $joinedten, $fallback, $overridden, $keyless]),
            array_keys($this->cells()),
            'the control: no value, every record'
        );

        $this->filter_by($two);
        $this->assertSame($this->sorted([$keyed, $joined, $fallback]), array_keys($this->cells()));

        $this->filter_by($ten);
        $this->assertSame($this->sorted([$joined, $joinedten]), array_keys($this->cells()));
    }

    /**
     * A narrowed reader is offered their own groups and nothing else, and a value naming another
     * group filters nothing: the rows stay their base condition's, never another group's.
     *
     * Changes that must make it fail: the options not narrowed; a value outside the options
     * applied.
     *
     * @return void
     */
    public function test_a_narrowed_reader_cannot_pick_another_group(): void {
        $two = (int) $this->two->id;
        $ten = (int) $this->ten->id;
        $mine = $this->seed(0, (string) $two);
        $both = $this->seed(0, "{$two},{$ten}");
        $theirs = $this->seed(0, (string) $ten);

        $this->setUser($this->reader('joinednarrowreader', false, [$this->two]));
        $this->assertSame([$two => 'Turma 2'], $this->report()->group_options());
        $this->assertSame($this->sorted([$mine, $both]), array_keys($this->cells()));

        $this->filter_by($two);
        $this->assertSame($this->sorted([$mine, $both]), array_keys($this->cells()));

        // A forged value: the other group's id, which the form never offered.
        $this->filter_by($ten);
        $cells = $this->cells();
        $this->assertSame($this->sorted([$mine, $both]), array_keys($cells));
        $this->assertArrayNotHasKey($theirs, $cells);
    }

    /**
     * The finding: an application without a key, approved by an editing teacher onto the method's
     * defaults, which joined a group, is read by that group's moderator, and filtered in by that
     * group. The decided groups are empty, so before the column the narrowing missed it: the
     * record set back to "not known" is the control that the column is what reaches it.
     *
     * Changes that must make it fail: the narrowing not reading the joined groups; the approval
     * not recording them.
     *
     * @return void
     */
    public function test_an_approval_on_the_defaults_reaches_the_moderator_of_its_group(): void {
        global $DB;

        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $this->instance->id, 'groupid' => $this->two->id]);
        $plugin = enrol_get_plugin('apply');
        $applicant = $this->getDataGenerator()->create_user();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);
        $method->invoke($plugin, $this->instance, $applicant->id, (object) ['applydescription' => '']);
        $ueid = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $this->instance->id, 'userid' => $applicant->id]);

        $sink = $this->redirectMessages();
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $this->assertSame(1, $plugin->confirm_enrolment([$ueid], '', []));
        $sink->close();
        $record = $DB->get_record('enrol_apply_submission', ['userenrolmentid' => $ueid], '*', MUST_EXIST);
        $this->assertSame('', (string) $record->decidedgroups, 'the premise: the defaults applied');
        $this->assertSame(0, (int) $record->keygroupid, 'the premise: no key');

        $moderator = $this->reader('joineddefaultsmoderator', false, [$this->two]);
        $other = $this->reader('joineddefaultsother', false, [$this->ten]);

        $this->setUser($moderator);
        $this->assertArrayHasKey((int) $applicant->id, $this->cells());
        $this->filter_by((int) $this->two->id);
        $this->assertSame('Turma 2', $this->cells()[(int) $applicant->id] ?? 'missing');

        $this->setUser($other);
        $this->assertArrayNotHasKey((int) $applicant->id, $this->cells(), 'the control: another group');

        // The control: the same record with its joined groups not known, as before the column.
        $DB->set_field('enrol_apply_submission', 'joinedgroups', null, ['id' => $record->id]);
        $this->setUser($moderator);
        $this->assertArrayNotHasKey((int) $applicant->id, $this->cells());
    }

    /**
     * The filter is offered only where the course has a group the reader may see; the column is
     * there either way.
     *
     * @return void
     */
    public function test_the_filter_is_offered_only_where_there_is_a_group(): void {
        $this->seed(0, null);
        $this->setUser($this->reader('joinedofferreader', true));
        $this->assertArrayHasKey(self::FILTER, $this->report()->get_filters());

        groups_delete_group($this->two);
        groups_delete_group($this->ten);
        // Core keeps one report instance per report and user for the request: build it afresh.
        \core_reportbuilder\manager::reset_caches();
        $report = $this->report();
        $this->assertArrayNotHasKey(self::FILTER, $report->get_filters());
        $this->assertNotNull($report->get_column(self::COLUMN));
    }

    /**
     * The custom-report source carries the column and no group filter or condition (V-D11), and
     * its cell names the groups as the course report's does.
     *
     * @return void
     */
    public function test_the_source_has_the_column_and_no_group_filter(): void {
        $this->seed(0, "{$this->ten->id},{$this->two->id}");
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $persistent = $generator->create_report(['name' => 'Joined', 'source' => applications::class, 'default' => 0]);
        $instance = manager::get_report_from_persistent($persistent);

        $this->assertArrayHasKey(self::COLUMN, $instance->get_columns());
        $this->assertArrayNotHasKey(self::FILTER, $instance->get_filters());
        $this->assertArrayNotHasKey(self::COLUMN, $instance->get_filters());
        $this->assertArrayNotHasKey(self::FILTER, $instance->get_conditions());
        $this->assertArrayNotHasKey(self::COLUMN, $instance->get_conditions());

        $generator->create_column(['reportid' => $persistent->get('id'), 'uniqueidentifier' => 'submission:status']);
        $generator->create_column(['reportid' => $persistent->get('id'), 'uniqueidentifier' => self::COLUMN]);
        $content = $this->get_custom_report_content($persistent->get('id'));
        $this->assertCount(1, $content);
        $this->assertSame('Turma 2, Turma 10', array_values(reset($content))[1]);
    }
}
