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
 * Tests for the course report's separate-groups narrowing.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\reportbuilder;

use context_course;
use core_reportbuilder\system_report_factory;
use enrol_apply\local\groupscope;
use enrol_apply\local\submission;
use enrol_apply\reportbuilder\local\systemreports\course_applications;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The course report narrowed by separate groups (docs/design/group-review-scope.md, S8).
 *
 * The course is in separate groups mode, and the reader holds enrol/apply:viewreports through a
 * role of their own, without moodle/site:accessallgroups, as a member of one group. Five records
 * are written directly, one per case: a pending one keyed to the reader's group, a decided one
 * whose key named another group but whose approval joined the reader's (it is matched only
 * through decidedgroups), one of another group, one without a key, and a decided one whose
 * decidedgroups carries ids that CONTAIN the reader's group id as a substring (its id followed by
 * a 1, and preceded by a 9), which a match on the bare id would take for the reader's.
 *
 * Each case is read on both of the report's paths: the report built through for_method(), as
 * report.php builds it, and the course-level one system_report_factory::create() builds.
 * Either way the rows come back through system_report_table::create(), which re-creates the
 * report from its persistent as core_table_get_dynamic_table_content does on a sort or a page
 * turn.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_applications::class)]
#[CoversClass(groupscope::class)]
final class report_group_scope_test extends \core_reportbuilder\tests\core_reportbuilder_testcase {
    /** @var \stdClass A course in separate groups mode. */
    private $course;

    /** @var \stdClass Its apply instance. */
    private $instance;

    /** @var \stdClass The reader's group. */
    private $mine;

    /** @var \stdClass Another group. */
    private $theirs;

    /** @var array Applicant id by case: keyed, decided, other, keyless, substring. */
    private $applicants = [];

    /**
     * The course, its groups and one record per case.
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

        $this->mine = $generator->create_group(['courseid' => $this->course->id]);
        $this->theirs = $generator->create_group(['courseid' => $this->course->id]);

        $mine = (int) $this->mine->id;
        $theirs = (int) $this->theirs->id;
        $cases = [
            'keyed' => [$mine, '', submission::STATUS_PENDING],
            'decided' => [$theirs, "{$theirs},{$mine}", submission::STATUS_APPROVED],
            'other' => [$theirs, (string) $theirs, submission::STATUS_APPROVED],
            'keyless' => [0, '', submission::STATUS_PENDING],
            'substring' => [0, ($mine * 10 + 1) . ',9' . $mine, submission::STATUS_APPROVED],
        ];
        foreach ($cases as $case => [$keygroupid, $decidedgroups, $status]) {
            $this->applicants[$case] = $this->seed($keygroupid, $decidedgroups, $status);
        }
    }

    /**
     * Write one record of the course.
     *
     * @param int $keygroupid The key's group, 0 for none.
     * @param string $decidedgroups The decided groups, comma separated, empty for none.
     * @param int $status The record's status.
     * @return int The applicant's id.
     */
    private function seed(int $keygroupid, string $decidedgroups, int $status): int {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $user->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => 0,
            'comment' => 'Please',
            'status' => $status,
            'outcomemessage' => '',
            'decidedgroups' => $decidedgroups,
            'keygroupid' => $keygroupid,
            'keyoutcome' => $keygroupid ? submission::KEY_FOR_REVIEW : submission::KEY_NONE,
            'timecreated' => time(),
            'timedecided' => $status === submission::STATUS_PENDING ? 0 : time(),
            'decidedby' => 0,
        ]);

        return (int) $user->id;
    }

    /**
     * A reader of the report holding enrol/apply:viewreports through a role of their own.
     *
     * @param string $shortname The role's short name, one per kind of reader.
     * @param bool $allgroups Whether the role also holds moodle/site:accessallgroups.
     * @param bool $member Whether the reader belongs to $mine.
     * @param bool $sitewide Whether the role is assigned at system level rather than in the course.
     * @return \stdClass The reader.
     */
    private function reader(string $shortname, bool $allgroups = false, bool $member = true, bool $sitewide = false): \stdClass {
        $system = \context_system::instance();
        $role = $this->getDataGenerator()->create_role(['shortname' => $shortname]);
        assign_capability(groupscope::REPORT, CAP_ALLOW, $role, $system->id, true);
        if ($allgroups) {
            assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $role, $system->id, true);
        }

        $user = $this->getDataGenerator()->create_user();
        role_assign($role, $user->id, $sitewide ? $system->id : context_course::instance($this->course->id)->id);
        if ($member) {
            // Enrolled with no role: groups_add_member() returns false, without an exception, for
            // a user who is not enrolled, which would leave the reader in no group.
            $this->getDataGenerator()->enrol_user($user->id, $this->course->id);
            $this->getDataGenerator()->create_group_member(['groupid' => $this->mine->id, 'userid' => $user->id]);
            $this->assertTrue(groups_is_member((int) $this->mine->id, (int) $user->id));
        }

        return $user;
    }

    /**
     * The applicant ids of the rows the current user reads, on each of the report's two paths.
     *
     * @return array Two lists of applicant ids, sorted: through for_method(), and through the bare
     *     course-level report.
     */
    private function both_paths(): array {
        $context = context_course::instance($this->course->id);
        $paths = [
            course_applications::for_method($context, (int) $this->instance->id),
            system_report_factory::create(course_applications::class, $context),
        ];

        return array_map(function (\core_reportbuilder\system_report $report): array {
            $table = \core_reportbuilder\table\system_report_table::create(
                (int) $report->get_report_persistent()->get('id'),
                []
            );
            $table->define_baseurl(new \moodle_url('/enrol/apply/report.php'));
            $table->setup();
            // Query_db() rather than out(): out() consumes the recordset.
            $table->query_db(100, false);
            $userids = [];
            foreach ($table->rawdata as $record) {
                $userids[] = (int) $record->userid;
            }
            $table->close_recordset();
            sort($userids);

            return $userids;
        }, $paths);
    }

    /**
     * The applicant ids of the named cases, sorted.
     *
     * @param array $cases Case names.
     * @return array Applicant ids.
     */
    private function applicants_of(array $cases): array {
        $ids = array_map(fn(string $case): int => $this->applicants[$case], $cases);
        sort($ids);

        return $ids;
    }

    /**
     * A narrowed reader reads the records of their group alone, by key group or by decided
     * groups, on both paths; not another group's, not a keyless one, and not one whose decided
     * groups merely contain their group's id as a substring.
     *
     * Changes that must make it fail: the base condition dropped, the decided groups not read,
     * or matched without their separators.
     *
     * @return void
     */
    public function test_a_narrowed_reader_reads_only_their_groups(): void {
        $this->setUser($this->reader('narrowreader'));

        $expected = $this->applicants_of(['keyed', 'decided']);
        [$page, $refresh] = $this->both_paths();
        $this->assertSame($expected, $page);
        $this->assertSame($expected, $refresh);
    }

    /**
     * The control: a reader who may access all groups reads every record, on both paths.
     *
     * @return void
     */
    public function test_a_reader_who_sees_every_group_reads_everything(): void {
        $this->setUser($this->reader('allreader', true));

        $expected = $this->applicants_of(['keyed', 'decided', 'other', 'keyless', 'substring']);
        [$page, $refresh] = $this->both_paths();
        $this->assertSame($expected, $page);
        $this->assertSame($expected, $refresh);
    }

    /**
     * A reader holding the report capability at system level is not narrowed, though core's
     * rule would narrow them: the exemption reads the report's own capability.
     *
     * Changes that must make it fail: the report narrowing by the deciding capability's
     * exemption.
     *
     * @return void
     */
    public function test_a_site_wide_reader_is_not_narrowed(): void {
        $reader = $this->reader('sitereader', false, true, true);
        $this->setUser($reader);
        // The precondition: core's rule alone does narrow them, so the exemption is what is tested.
        $this->assertTrue(groupscope::is_scoped((int) $this->course->id, (int) $reader->id));

        $expected = $this->applicants_of(['keyed', 'decided', 'other', 'keyless', 'substring']);
        [$page, $refresh] = $this->both_paths();
        $this->assertSame($expected, $page);
        $this->assertSame($expected, $refresh);
    }

    /**
     * A narrowed reader in no group reads no row, and the report says it is narrowed to nothing,
     * which is what report.php shows core's notice for.
     *
     * Changes that must make it fail: an empty group list reaching get_in_or_equal(), which throws.
     *
     * @return void
     */
    public function test_a_narrowed_reader_in_no_group_reads_nothing(): void {
        $this->setUser($this->reader('grouplessreader', false, false));

        [$page, $refresh] = $this->both_paths();
        $this->assertSame([], $page);
        $this->assertSame([], $refresh);
        $report = course_applications::for_method(context_course::instance($this->course->id), (int) $this->instance->id);
        $this->assertSame([], $report->narrowed_groups());
    }
}
