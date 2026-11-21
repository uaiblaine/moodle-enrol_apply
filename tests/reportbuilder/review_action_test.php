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
 * Tests for the course report's Review action (AR-D18).
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
use enrol_apply\local\queue;
use enrol_apply\local\submission;
use enrol_apply\reportbuilder\local\systemreports\course_applications;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope: ENROL_APPLY_USER_WAIT lives in the plugin's lib.php.
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * Tests for the course report's Review action and the set of rows it is drawn on.
 *
 * A course in separate groups mode with two groups. Every reader holds the plugin's capabilities
 * through a role of their own, so no archetype grants anything the test does not name.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_applications::class)]
#[CoversClass(queue::class)]
final class review_action_test extends \advanced_testcase {
    /** @var \stdClass The course, in separate groups mode. */
    private $course;

    /** @var \stdClass The apply instance. */
    private $instance;

    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass The group the moderator belongs to. */
    private $mine;

    /** @var \stdClass Another group. */
    private $theirs;

    /** @var \stdClass The moderator: decides and reads the report, narrowed to $mine. */
    private $moderator;

    /** @var array Label => user enrolment id of each application. */
    private $applications = [];

    /**
     * The course, its groups, the moderator and one application of each kind.
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
        $this->plugin = enrol_get_plugin('apply');

        $this->course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $id = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $this->mine = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Mine']);
        $this->theirs = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Theirs']);

        $this->moderator = $this->reader('r1moderator', [groupscope::DECIDE, groupscope::REPORT]);
        $this->getDataGenerator()->create_group_member(['groupid' => $this->mine->id, 'userid' => $this->moderator->id]);

        $this->applications = [
            'pending in mine' => $this->application((int) $this->mine->id),
            'pending in theirs' => $this->application((int) $this->theirs->id),
            'pending, no group' => $this->application(0),
            'deferred in mine' => $this->application((int) $this->mine->id, ENROL_APPLY_USER_WAIT),
            'decided in mine' => $this->application((int) $this->mine->id, ENROL_USER_ACTIVE),
            'the moderator\'s own' => $this->application((int) $this->mine->id, ENROL_USER_SUSPENDED, $this->moderator),
        ];
    }

    /**
     * A reader holding the given capabilities in the course through a role of their own.
     *
     * Enrolled with no role, so can_view() admits them where they hold the report capability.
     *
     * @param string $shortname The role's short name.
     * @param array $capabilities Capabilities to allow.
     * @return \stdClass The reader.
     */
    private function reader(string $shortname, array $capabilities): \stdClass {
        $context = context_course::instance($this->course->id);
        $roleid = $this->getDataGenerator()->create_role(['shortname' => $shortname]);
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        }
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, null, 'manual');
        role_assign($roleid, $user->id, $context->id);

        return $user;
    }

    /**
     * An application on the instance, filed under a group by its key.
     *
     * @param int $groupid The key's group, 0 for none.
     * @param int $status The enrolment's status: suspended is pending, the waiting value deferred,
     *     active decided.
     * @param \stdClass|null $user The applicant, null for a new one.
     * @return int The user enrolment id.
     */
    private function application(int $groupid, int $status = ENROL_USER_SUSPENDED, ?\stdClass $user = null): int {
        global $DB;

        $user = $user ?? $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $user->id, null, 0, 0, $status);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['enrolid' => $this->instance->id, 'userid' => $user->id],
            MUST_EXIST
        );
        submission::create(
            $this->instance,
            (int) $user->id,
            $ueid,
            (object) [],
            $groupid,
            $groupid ? submission::KEY_FOR_REVIEW : submission::KEY_NONE
        );
        $recordstatus = match ($status) {
            ENROL_USER_ACTIVE => submission::STATUS_APPROVED,
            ENROL_APPLY_USER_WAIT => submission::STATUS_WAITING,
            default => submission::STATUS_PENDING,
        };
        $DB->set_field('enrol_apply_submission', 'status', $recordstatus, ['userenrolmentid' => $ueid]);
        $DB->set_field(
            'enrol_apply_submission',
            'timedecided',
            $recordstatus === submission::STATUS_PENDING ? 0 : time(),
            ['userenrolmentid' => $ueid]
        );

        return $ueid;
    }

    /**
     * The labels of the applications the current user may review from the report, sorted.
     *
     * Each one checked against can_view_application() and the queue's own "awaiting a decision"
     * on the way: the set and the per-row answer must agree on every application of the course.
     *
     * @return array Labels.
     */
    private function reviewable(): array {
        global $DB;

        $set = queue::reviewable_in_course((int) $this->course->id);
        $labels = [];
        foreach ($this->applications as $label => $ueid) {
            $ue = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);
            $expected = queue::is_awaiting_decision($ue)
                && $this->plugin->can_view_application((int) $this->course->id, (int) $ue->userid, $ueid);
            $this->assertSame($expected, isset($set[$ueid]), "{$label}: the set and can_view_application() disagree");
            if ($expected) {
                $labels[] = $label;
            }
        }
        $this->assertCount(count($labels), $set, 'the set holds nothing outside the course\'s applications');
        sort($labels);

        return $labels;
    }

    /**
     * The set of reviewable rows agrees with can_view_application() row by row, for every kind of
     * reader.
     *
     * A moderator narrowed by separate groups reviews the pending and the deferred applications of
     * their group and neither their own application nor a decided one; a holder of the report
     * capability alone reviews nothing; a viewer who sees every group reviews every application
     * still awaiting a decision, their own included, as can_view_application() admits; a mentor
     * reviews their mentee's alone.
     *
     * Changes that must make it fail: the set read without the group narrowing, without the
     * self-decision rule or without "awaiting a decision" (gates RB4A, RB4B, RB4C).
     *
     * @return void
     */
    public function test_the_reviewable_set_agrees_with_can_view_application(): void {
        global $DB;

        $this->setUser($this->moderator);
        $this->assertSame(['deferred in mine', 'pending in mine'], $this->reviewable());

        $this->setUser($this->reader('r1reportonly', [groupscope::REPORT, 'moodle/site:accessallgroups']));
        $this->assertSame([], $this->reviewable());

        $viewer = $this->reader('r1viewer', [groupscope::VIEW, groupscope::REPORT, 'moodle/site:accessallgroups']);
        $this->setUser($viewer);
        $this->assertSame(
            ['deferred in mine', 'pending in mine', 'pending in theirs', 'pending, no group', 'the moderator\'s own'],
            $this->reviewable()
        );

        $mentor = $this->reader('r1mentor', [groupscope::REPORT]);
        $mentorrole = $this->getDataGenerator()->create_role(['shortname' => 'r1mentee']);
        assign_capability(groupscope::DECIDE, CAP_ALLOW, $mentorrole, \context_system::instance()->id, true);
        $theirsuser = (int) $DB->get_field('user_enrolments', 'userid', ['id' => $this->applications['pending in theirs']]);
        role_assign($mentorrole, $mentor->id, \context_user::instance($theirsuser)->id);
        $this->setUser($mentor);
        $this->assertSame(['pending in theirs'], $this->reviewable());

        $this->setAdminUser();
        $this->assertSame(
            ['deferred in mine', 'pending in mine', 'pending in theirs', 'pending, no group', 'the moderator\'s own'],
            $this->reviewable()
        );
    }

    /**
     * The report draws the Review action on exactly the rows of the set, and to a reader holding
     * the report capability alone on none.
     *
     * Changes that must make it fail: the action's callback not reading the set (gate RB4D).
     *
     * @return void
     */
    public function test_the_report_draws_review_on_the_reviewable_rows_only(): void {
        $this->setUser($this->moderator);
        $this->assertSame(['deferred in mine', 'pending in mine'], $this->with_action());

        $this->setUser($this->reader('r1reportonly', [groupscope::REPORT, 'moodle/site:accessallgroups']));
        $this->assertSame([], $this->with_action(6));
    }

    /**
     * The labels of the rows the current user reads that carry the Review action, sorted.
     *
     * @param int|null $rows How many rows the reader must read, null to skip the check.
     * @return array Labels.
     */
    private function with_action(?int $rows = null): array {
        $report = system_report_factory::create(course_applications::class, context_course::instance($this->course->id));
        $table = \core_reportbuilder\table\system_report_table::create((int) $report->get_report_persistent()->get('id'), []);
        $table->define_baseurl(new \moodle_url('/enrol/apply/report.php'));
        $table->setup();
        $table->query_db(100, false);

        $labels = array_flip($this->applications);
        $found = [];
        $read = 0;
        foreach ($table->rawdata as $record) {
            $read++;
            $actions = (string) ($table->format_row($record)['actions'] ?? '');
            $ueid = (int) $record->userenrolmentid;
            if (preg_match('~/enrol/apply/manage\.php\?userenrol=' . $ueid . '(?!\d)~', $actions)) {
                $found[] = $labels[$ueid];
            }
        }
        $table->close_recordset();
        if ($rows !== null) {
            $this->assertSame($rows, $read, 'the control: the reader reads the rows');
        }
        sort($found);

        return $found;
    }
}
