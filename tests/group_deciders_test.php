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

use enrol_apply\local\groupscope;
use enrol_apply\local\instanceoptions;
use enrol_apply\local\queue;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass targets below resolve when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * Who decides a method's applications (C-D20) and which memberships scope a decider (C-D28), on
 * every surface a narrowed decider reaches (docs/design/applicant-group-choice.md).
 *
 * The course is in separate groups mode with two apply methods: "student", granting Student and
 * decided by each group's deciders, and "recruit", granting Non-editing teacher, which setUp()
 * lets decide, and decided only by those who see every group. The moderator is a non-editing
 * teacher in Evening, by a membership made by hand; the editing teacher sees every group and is
 * the control. The course report's half is in reportbuilder/report_group_scope_test.php.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(groupscope::class)]
#[CoversClass(\enrol_apply_plugin::class)]
#[CoversClass(queue::class)]
#[CoversClass(\enrol_apply\table\applications::class)]
final class group_deciders_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass A course in separate groups mode. */
    private $course;

    /** @var \stdClass The learner method, decided by each group's deciders. */
    private $student;

    /** @var \stdClass The recruitment method, decided only by those who see every group. */
    private $recruit;

    /** @var \stdClass The moderator's group. */
    private $evening;

    /** @var \stdClass Another group. */
    private $morning;

    /** @var \stdClass Non-editing teacher, member of Evening by hand. */
    private $moderator;

    /** @var \stdClass Editing teacher, who may access all groups. */
    private $editing;

    /** @var int The Student role. */
    private $studentrole;

    /** @var int The Non-editing teacher role, made to decide. */
    private $teacherrole;

    /**
     * The course, its two methods, its groups and its two deciders.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB, $PAGE;

        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        $this->plugin = enrol_get_plugin('apply');

        $this->teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $this->studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $system = \context_system::instance()->id;
        foreach (['manageapplications', 'viewapplications', 'manage', 'choosedecision'] as $capability) {
            assign_capability('enrol/apply:' . $capability, CAP_ALLOW, $this->teacherrole, $system, true);
        }

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS]);
        $this->student = $this->method($this->studentrole);
        $this->recruit = $this->method($this->teacherrole);
        instanceoptions::set((int) $this->recruit->id, ['deciders' => instanceoptions::DECIDERS_ALLGROUPS]);

        $this->evening = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Evening']);
        $this->morning = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Morning']);
        $this->moderator = $generator->create_and_enrol($this->course, 'teacher');
        $generator->create_group_member(['groupid' => $this->evening->id, 'userid' => $this->moderator->id]);
        $this->editing = $generator->create_and_enrol($this->course, 'editingteacher');

        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php', ['id' => $this->student->id]));
    }

    /**
     * A method of the course granting a role and notifying every course decider.
     *
     * @param int $roleid The role it grants on approval.
     * @return \stdClass The {enrol} record.
     */
    private function method(int $roleid): \stdClass {
        global $DB;

        $id = $this->plugin->add_instance($this->course, [
            'roleid' => $roleid,
            'customtext3' => '$@ALL@$',
        ] + $this->plugin->get_instance_defaults());

        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Write a pending application through the plugin's own apply(), with a key group.
     *
     * @param \stdClass $instance The method.
     * @param \stdClass $group The key's group.
     * @return array The user enrolment id, the applicant and the messages the notice sent.
     */
    private function apply_for(\stdClass $instance, \stdClass $group): array {
        global $USER;

        $previous = $USER;
        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $written = (new \ReflectionMethod(\enrol_apply_plugin::class, 'apply'))->invoke(
            $this->plugin,
            $instance,
            (int) $applicant->id,
            (object) ['applydescription' => 'Please'],
            (int) $group->id,
            submission::KEY_FOR_REVIEW
        );
        $messages = $sink->get_messages();
        $sink->close();
        $this->setUser($previous);

        return [(int) $written['userenrolmentid'], $applicant, $messages];
    }

    /**
     * The user enrolment ids the queue's own table lists to the current user for a method.
     *
     * @param \stdClass $instance The method.
     * @return array The ids, ascending.
     */
    private function listed(\stdClass $instance): array {
        $table = \enrol_apply\table\applications::for_scope((int) $instance->id);
        ob_start();
        $table->out(50, false);
        ob_end_clean();

        $ids = array_map(static fn($row): int => (int) $row->userenrolmentid, array_values($table->rawdata));
        sort($ids);

        return $ids;
    }

    /**
     * The users a list of messages went to.
     *
     * @param array $messages The messages.
     * @return array User ids, ascending.
     */
    private function recipients(array $messages): array {
        $ids = array_map(static fn($message): int => (int) $message->useridto, $messages);
        sort($ids);

        return $ids;
    }

    /**
     * A narrowed moderator lists none of the recruitment method's applications, even of their own
     * group, while the learner method's of their group stay theirs; the editing teacher lists both.
     * The per-row check agrees with the listing on every row.
     *
     * Changes that must make it fail: the "Who decides" clause dropped from where(), or from allows().
     *
     * @return void
     */
    public function test_a_narrowed_decider_neither_lists_nor_decides_an_all_groups_method(): void {
        [$recruited, $candidate] = $this->apply_for($this->recruit, $this->evening);
        [$learner, $learnerapplicant] = $this->apply_for($this->student, $this->evening);

        $this->setUser($this->moderator);
        $this->assertSame([], $this->listed($this->recruit));
        $this->assertSame([$learner], $this->listed($this->student));
        $this->assertFalse($this->plugin->can_manage_application($this->course->id, $candidate->id, $recruited));
        $this->assertTrue($this->plugin->can_manage_application($this->course->id, $learnerapplicant->id, $learner));
        $this->assertFalse($this->plugin->can_view_application($this->course->id, $candidate->id, $recruited));

        $this->setUser($this->editing);
        $this->assertSame([$recruited], $this->listed($this->recruit));
        $this->assertTrue($this->plugin->can_manage_application($this->course->id, $candidate->id, $recruited));

        // The control: decided by the group's deciders, the moderator's group reaches them again.
        instanceoptions::set((int) $this->recruit->id, ['deciders' => instanceoptions::DECIDERS_GROUP]);
        $this->setUser($this->moderator);
        $this->assertSame([$recruited], $this->listed($this->recruit));
        $this->assertTrue($this->plugin->can_manage_application($this->course->id, $candidate->id, $recruited));
    }

    /**
     * The SQL scope and the per-row check agree on rows of both methods.
     *
     * Changes that must make it fail: either side reading "Who decides" and the other not.
     *
     * @return void
     */
    public function test_the_sql_and_the_per_row_check_agree_across_methods(): void {
        global $DB;

        $rows = [
            $this->apply_for($this->recruit, $this->evening)[0],
            $this->apply_for($this->recruit, $this->morning)[0],
            $this->apply_for($this->student, $this->evening)[0],
            $this->apply_for($this->student, $this->morning)[0],
        ];

        [$where, $params] = groupscope::where(groupscope::narrowed_to((int) $this->course->id, (int) $this->moderator->id));
        [$insql, $inparams] = $DB->get_in_or_equal($rows, SQL_PARAMS_NAMED, 'row');
        $matched = array_map('intval', $DB->get_fieldset_sql(
            "SELECT ue.id FROM {user_enrolments} ue WHERE ue.id {$insql} AND {$where} ORDER BY ue.id",
            $params + $inparams
        ));

        $this->assertSame([$rows[2]], $matched);
        foreach ($rows as $ueid) {
            $this->assertSame(
                in_array($ueid, $matched, true),
                groupscope::allows((int) $this->course->id, $ueid, (int) $this->moderator->id),
                "row {$ueid}"
            );
        }
    }

    /**
     * A reader who sees every group, filtering the recruitment method's queue by a group, still
     * finds its applications of that group: the filter is the group alone, the scope is applied
     * before it.
     *
     * Changes that must make it fail: the queue's group filter built on the scope's predicate,
     * which drops the methods only those who see every group decide.
     *
     * @return void
     */
    public function test_a_group_filter_keeps_an_all_groups_methods_applications(): void {
        global $DB;

        [$ueid] = $this->apply_for($this->recruit, $this->evening);
        $this->setUser($this->editing);
        $listing = queue::listing_scope((int) $this->recruit->id);
        [$where, $params] = \enrol_apply\local\groupfilter::where($listing, (string) $this->evening->id);

        $matched = array_map('intval', $DB->get_fieldset_sql(
            "SELECT ue.id FROM {user_enrolments} ue WHERE ue.enrolid = :enrolid AND {$where}",
            $params + ['enrolid' => $this->recruit->id]
        ));
        $this->assertSame([$ueid], $matched);
    }

    /**
     * The review page refuses the narrowed moderator a recruitment application of their group, and
     * the previous/next walk offers them no queue for it; the editing teacher reads and walks it.
     *
     * Changes that must make it fail: the "Who decides" clause dropped from allows().
     *
     * @return void
     */
    public function test_the_review_page_and_the_walk_follow_who_decides(): void {
        [$ueid] = $this->apply_for($this->recruit, $this->evening);
        $application = queue::application($ueid);

        $this->setUser($this->editing);
        $this->assertSame((int) $this->recruit->id, queue::scope($application, $this->recruit)->enrolid);
        $this->assertInstanceOf(\context_course::class, queue::require_review_access($application));

        $this->setUser($this->moderator);
        $this->assertFalse(queue::scope($application, $this->recruit)->hasqueue);
        $this->expectException(\required_capability_exception::class);
        queue::require_review_access($application);
    }

    /**
     * The group choosers offer a narrowed moderator nothing on the recruitment method and their own
     * group on the learner method; the editing teacher every group on either.
     *
     * Changes that must make it fail: the "Who decides" clause dropped from choosable_groups().
     *
     * @return void
     */
    public function test_the_choosers_follow_who_decides(): void {
        $courseid = (int) $this->course->id;

        $this->setUser($this->moderator);
        $this->assertSame([], groupscope::choosable_groups($courseid, (int) $this->recruit->id));
        $this->assertSame(
            [(int) $this->evening->id],
            array_keys(groupscope::choosable_groups($courseid, (int) $this->student->id))
        );

        $this->setUser($this->editing);
        $this->assertCount(2, groupscope::choosable_groups($courseid, (int) $this->recruit->id));
    }

    /**
     * The new-application notice of the recruitment method reaches the editing teacher and not the
     * moderator of the application's group; the learner method's reaches both.
     *
     * Changes that must make it fail: the method not handed to allows_group(), or the "Who decides"
     * clause dropped from it.
     *
     * @return void
     */
    public function test_the_notice_follows_who_decides(): void {
        [, , $recruitmessages] = $this->apply_for($this->recruit, $this->evening);
        [, , $learnermessages] = $this->apply_for($this->student, $this->evening);

        $recipients = $this->recipients($recruitmessages);
        $this->assertContains((int) $this->editing->id, $recipients);
        $this->assertNotContains((int) $this->moderator->id, $recipients);

        $recipients = $this->recipients($learnermessages);
        $this->assertContains((int) $this->editing->id, $recipients);
        $this->assertContains((int) $this->moderator->id, $recipients, 'the control: the group reaches its moderator');
    }

    /**
     * A user who studies in Evening through the learner method and moderates Morning through the
     * recruitment method decides Morning alone; a membership made by hand keeps core's rule.
     *
     * Both memberships and both roles are written as complete_approval() writes them, stamped with
     * the component and the method that made them.
     *
     * Changes that must make it fail: C-D28 dropped from member_groups() (Evening scopes them), the
     * deciding role not required (Evening scopes them through their Student assignment), the
     * method not matched (the recruitment method's role scopes the learner method's membership),
     * or every membership of the plugin dropped (Morning does not scope them), or the hand-made
     * one dropped with them.
     *
     * @return void
     */
    public function test_a_learner_membership_written_by_a_learner_method_scopes_nothing(): void {
        $generator = $this->getDataGenerator();
        $context = \context_course::instance($this->course->id);
        $dual = $generator->create_user();
        $this->plugin->enrol_user($this->student, $dual->id, null, 0, 0, ENROL_USER_ACTIVE);
        $this->plugin->enrol_user($this->recruit, $dual->id, null, 0, 0, ENROL_USER_ACTIVE);
        role_assign($this->studentrole, $dual->id, $context->id, 'enrol_apply', (int) $this->student->id);
        role_assign($this->teacherrole, $dual->id, $context->id, 'enrol_apply', (int) $this->recruit->id);
        $this->assertTrue(groups_add_member($this->evening, $dual, 'enrol_apply', (int) $this->student->id));
        $this->assertTrue(groups_add_member($this->morning, $dual, 'enrol_apply', (int) $this->recruit->id));
        $manual = $generator->create_group(['courseid' => $this->course->id, 'name' => 'By hand']);
        $generator->create_group_member(['groupid' => $manual->id, 'userid' => $dual->id]);

        $courseid = (int) $this->course->id;
        $expected = [(int) $this->morning->id, (int) $manual->id];
        sort($expected);
        $this->assertSame($expected, groupscope::narrowed_to($courseid, (int) $dual->id));

        [$evening] = $this->apply_for($this->student, $this->evening);
        [$morning] = $this->apply_for($this->student, $this->morning);
        $this->setUser($dual);
        $this->assertSame([$morning], $this->listed($this->student));
        $this->assertFalse(groupscope::allows($courseid, $evening, (int) $dual->id));
        $this->assertTrue(groupscope::allows($courseid, $morning, (int) $dual->id));
    }

    /**
     * A membership the plugin wrote scopes a moderator approved with a deciding role, and stops
     * when that role no longer decides in the course.
     *
     * Changes that must make it fail: the role judged at its definition rather than in the course.
     *
     * @return void
     */
    public function test_a_deciding_membership_scopes_while_its_role_decides(): void {
        $context = \context_course::instance($this->course->id);
        $recruited = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->recruit, $recruited->id, null, 0, 0, ENROL_USER_ACTIVE);
        role_assign($this->teacherrole, $recruited->id, $context->id, 'enrol_apply', (int) $this->recruit->id);
        $this->assertTrue(groups_add_member($this->morning, $recruited, 'enrol_apply', (int) $this->recruit->id));

        $this->assertSame([(int) $this->morning->id], groupscope::member_groups((int) $this->course->id, (int) $recruited->id));

        // The role stops deciding here (and with it the narrowing has nobody to narrow).
        assign_capability('enrol/apply:manageapplications', CAP_PROHIBIT, $this->teacherrole, $context->id, true);
        $this->assertSame([], groupscope::member_groups((int) $this->course->id, (int) $recruited->id));
    }
}
