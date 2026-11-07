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
 * Tests for the applicant's profile link on the review page and the queue.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/apply/renderer.php');

/**
 * Tests for the applicant's profile link on the review page and the queue.
 *
 * The link is offered exactly where user/view.php would open the profile, so each refusal here is
 * paired with the change that lets core open it, which shows the refusal is core's rule and not a
 * blanket one.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(profilelink::class)]
final class profilelink_test extends \advanced_testcase {
    /** @var \stdClass The course, in separate groups mode. */
    protected $course;

    /** @var \stdClass The apply enrol instance, key for review. */
    protected $instance;

    /** @var \stdClass The group the moderator belongs to. */
    protected $group;

    /** @var \stdClass A moderator: the stock teacher role given manageapplications, in the group. */
    protected $moderator;

    /** @var \stdClass A pending applicant whose key named the moderator's group. */
    protected $applicant;

    /** @var int The applicant's user enrolment id. */
    protected $ueid;

    /**
     * Build a separate-groups course, a moderator in one group and an applicant for it.
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

        $generator = $this->getDataGenerator();
        $plugin = enrol_get_plugin('apply');
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $id = $plugin->add_instance($this->course, [
            'customchar1' => groupkey::MODE_REVIEW,
        ] + $plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $this->group = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Turma A']);

        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $teacher, \context_system::instance()->id, true);
        $this->moderator = $generator->create_and_enrol($this->course, 'teacher');
        $generator->create_group_member(['groupid' => $this->group->id, 'userid' => $this->moderator->id]);

        $this->applicant = $generator->create_user(['firstname' => 'Ana', 'lastname' => 'Ribeiro']);
        $plugin->enrol_user($this->instance, $this->applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $this->ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $this->applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $this->applicant->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => $this->ueid,
            'comment' => '',
            'status' => submission::STATUS_PENDING,
            'timecreated' => time(),
            'keygroupid' => (int) $this->group->id,
            'keyoutcome' => submission::KEY_FOR_REVIEW,
        ]);

        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php'));
    }

    /**
     * The link the two surfaces print for the fixture's applicant.
     *
     * @return string The href fragment, as it appears in the markup.
     */
    private function profile_href(): string {
        return 'user/view.php?id=' . $this->applicant->id . '&amp;course=' . $this->course->id;
    }

    /**
     * The review page's form for the fixture's application, for the current user.
     *
     * @return string The rendered form.
     */
    private function review_html(): string {
        global $DB, $PAGE;

        $url = new \moodle_url('/enrol/apply/manage.php', ['userenrol' => $this->ueid]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));
        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        return $PAGE->get_renderer('enrol_apply')->review_form(
            queue::application($this->ueid),
            $this->applicant,
            $instance,
            $url
        );
    }

    /**
     * The instance queue's markup, for the current user.
     *
     * @return string The rendered queue.
     */
    private function queue_html(): string {
        global $PAGE;

        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);
        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);

        return $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, $this->instance);
    }

    /**
     * A moderator narrowed by separate groups is not offered the profile of an applicant in no
     * group yet, on the review page or in the queue, and is once the applicant is in their group.
     *
     * Changes that must make it fail: the review page or the queue linking unconditionally, or
     * profilelink deciding something other than core's rule (the second half would then fail).
     *
     * @return void
     */
    public function test_a_narrowed_moderator_gets_the_link_only_when_core_opens_the_profile(): void {
        $this->setUser($this->moderator);

        $this->assertFalse(profilelink::viewable($this->applicant, $this->course));
        $review = $this->review_html();
        $queue = $this->queue_html();
        $this->assertStringNotContainsString($this->profile_href(), $review);
        $this->assertStringNotContainsString(get_string('reviewfullprofile', 'enrol_apply'), $review);
        $this->assertStringNotContainsString($this->profile_href(), $queue);
        // The control: the queue did list the applicant, by name.
        $this->assertStringContainsString('Ribeiro', $queue);

        // In the moderator's group, core opens the profile, and both surfaces link it.
        $this->getDataGenerator()->create_group_member(['groupid' => $this->group->id, 'userid' => $this->applicant->id]);
        $this->assertTrue(profilelink::viewable($this->applicant, $this->course));
        $this->assertStringContainsString($this->profile_href(), $this->review_html());
        $this->assertStringContainsString($this->profile_href(), $this->queue_html());
    }

    /**
     * A reader who may access every group is offered the link while the applicant is in none.
     *
     * @return void
     */
    public function test_an_editing_teacher_is_offered_the_link(): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));

        $this->assertTrue(profilelink::viewable($this->applicant, $this->course));
        $this->assertStringContainsString($this->profile_href(), $this->review_html());
    }

    /**
     * A deleted user's profile is never linked, even through core's "parent" branch, which
     * user/view.php guards with the deleted flag itself and core's profile rule never reaches.
     *
     * @return void
     */
    public function test_a_deleted_user_is_not_linked(): void {
        $parent = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'profilelinkdeleted']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('moodle/user:viewdetails', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        role_assign($roleid, $parent->id, \context_user::instance($this->applicant->id)->id);
        $this->setUser($parent);

        // The control: the same reader is linked while the user is not deleted.
        $this->assertTrue(profilelink::viewable($this->applicant, $this->course));
        $deleted = clone $this->applicant;
        $deleted->deleted = 1;
        $this->assertFalse(profilelink::viewable($deleted, $this->course));
        $this->assertNull(profilelink::url($deleted, $this->course));
    }

    /**
     * Core's "parent" branch: a role in the applicant's own context with moodle/user:viewdetails
     * there opens the profile without access to the course, and without that capability it does
     * not.
     *
     * @return void
     */
    public function test_a_parent_with_viewdetails_is_linked_without_the_course(): void {
        $parent = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'profilelinkparent']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        role_assign($roleid, $parent->id, \context_user::instance($this->applicant->id)->id);
        $this->setUser($parent);

        $this->assertFalse(profilelink::viewable($this->applicant, $this->course));

        assign_capability('moodle/user:viewdetails', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        $this->assertTrue(profilelink::viewable($this->applicant, $this->course));
    }

    /**
     * A reader who could see the profile by capability but cannot enter the course is refused, as
     * user/view.php's require_login($course) refuses them.
     *
     * @return void
     */
    public function test_a_reader_without_access_to_the_course_is_not_linked(): void {
        global $DB;

        $DB->set_field('course', 'groupmode', NOGROUPS, ['id' => $this->course->id]);
        $DB->set_field('course', 'groupmodeforce', 0, ['id' => $this->course->id]);
        $course = get_course($this->course->id);
        $reader = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'profilelinkreader']);
        assign_capability('moodle/user:viewdetails', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        role_assign($roleid, $reader->id, \context_system::instance()->id);
        $this->setUser($reader);

        $this->assertFalse(profilelink::viewable($this->applicant, $course));

        // The control: enrolled in the course, the same reader is let in.
        $this->getDataGenerator()->enrol_user($reader->id, $this->course->id, 'student');
        $this->assertTrue(profilelink::viewable($this->applicant, $course));
    }

    /**
     * The queue's batch decides every applicant as the single answer does, reader by reader.
     *
     * The applicants cover each step of core's rule: in no group, in the moderator's group, in a
     * group of hidden membership the moderator also belongs to, a course contact elsewhere, a
     * deleted one and one not enrolled in the course. Each reader's expected set is also stated
     * outright, so the agreement cannot be two answers wrong the same way.
     *
     * Changes that must make it fail: any step of viewable_ids() dropped or loosened (the parent
     * branch, course access, forceloginforprofiles, the course contact, the enrolment, viewdetails,
     * the group mode, accessallgroups, the shared group, or its visibility clause).
     *
     * @return void
     */
    public function test_the_batch_agrees_with_the_single_answer(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $editing = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        set_config('coursecontact', (string) $editing);
        $hidden = $generator->create_group([
            'courseid' => $this->course->id,
            'name' => 'Turma oculta',
            'visibility' => GROUPS_VISIBILITY_NONE,
        ]);
        $generator->create_group_member(['groupid' => $hidden->id, 'userid' => $this->moderator->id]);

        $plugin = enrol_get_plugin('apply');
        $applicants = ['nogroup' => $this->applicant];
        foreach (['ingroup', 'inhidden', 'contact', 'deleted'] as $name) {
            $applicants[$name] = $generator->create_user(['lastname' => $name]);
            $plugin->enrol_user($this->instance, $applicants[$name]->id, null, 0, 0, ENROL_USER_SUSPENDED);
        }
        // Not enrolled in the course at all: core refuses them for want of an enrolment.
        $applicants['outsider'] = $generator->create_user(['lastname' => 'outsider']);
        $generator->create_group_member(['groupid' => $this->group->id, 'userid' => $applicants['ingroup']->id]);
        $generator->create_group_member(['groupid' => $hidden->id, 'userid' => $applicants['inhidden']->id]);
        $elsewhere = $generator->create_course();
        $generator->enrol_user($applicants['contact']->id, $elsewhere->id, 'editingteacher');
        $applicants['deleted']->deleted = 1;

        $flags = [];
        foreach ($applicants as $applicant) {
            $flags[(int) $applicant->id] = (int) ($applicant->deleted ?? 0);
        }
        $ids = fn(array $names) => array_map(fn($name) => (int) $applicants[$name]->id, $names);
        $check = function (string $label, array $expected) use ($applicants, $flags, $ids): void {
            $batch = profilelink::viewable_ids($flags, get_course($this->course->id));
            $single = [];
            foreach ($applicants as $applicant) {
                if (profilelink::viewable($applicant, get_course($this->course->id))) {
                    $single[] = (int) $applicant->id;
                }
            }
            sort($batch);
            sort($single);
            $want = $ids($expected);
            sort($want);
            $this->assertSame($single, $batch, "{$label}: the batch and the single answer disagree");
            $this->assertSame($want, $batch, "{$label}: not the expected applicants");
        };

        $this->setUser($this->moderator);
        $check('moderator', ['ingroup', 'inhidden', 'contact']);

        // With profiles open to every logged-in user, core opens every one, enrolled or not.
        set_config('forceloginforprofiles', 0);
        $check('moderator, profiles not forced', ['nogroup', 'ingroup', 'inhidden', 'contact', 'outsider']);
        set_config('forceloginforprofiles', 1);

        // Without viewhiddengroups the hidden group's membership is not seen.
        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $context = \context_course::instance($this->course->id);
        assign_capability('moodle/course:viewhiddengroups', CAP_PROHIBIT, $teacher, $context->id, true);
        $check('moderator without viewhiddengroups', ['ingroup', 'contact']);

        // Without viewdetails only the course contact is shown.
        assign_capability('moodle/user:viewdetails', CAP_PROHIBIT, $teacher, $context->id, true);
        $check('moderator without viewdetails', ['contact']);

        $this->setUser($generator->create_and_enrol($this->course, 'editingteacher'));
        $check('editing teacher', ['nogroup', 'ingroup', 'inhidden', 'contact']);

        // Visible groups: nobody is narrowed.
        $DB->set_field('course', 'groupmode', VISIBLEGROUPS, ['id' => $this->course->id]);
        $this->setUser($generator->create_and_enrol($this->course, 'student'));
        $check('student in visible groups', ['nogroup', 'ingroup', 'inhidden', 'contact']);

        // A parent with viewdetails in one applicant's context, and no access to the course.
        $parent = $generator->create_user();
        $roleid = $generator->create_role(['shortname' => 'profilelinkbatchparent']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('moodle/user:viewdetails', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        role_assign($roleid, $parent->id, \context_user::instance($applicants['nogroup']->id)->id);
        $this->setUser($parent);
        $check('parent', ['nogroup']);
    }

    /**
     * A reader whose own enrolment in the course is suspended is not linked, as user/view.php's
     * require_login($course) refuses them, though they keep the role and its capabilities.
     *
     * @return void
     */
    public function test_a_reader_whose_enrolment_is_suspended_is_not_linked(): void {
        global $DB;

        $users = [$this->applicant->id => 0];

        // The control: an active editing teacher is linked.
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $this->assertTrue(profilelink::viewable($this->applicant, $this->course));
        $this->assertSame([(int) $this->applicant->id], profilelink::viewable_ids($users, $this->course));

        /* Suspended before setUser(): is_enrolled() caches the current user's enrolments on $USER,
           so a suspension written after the first question would not be seen. */
        $suspended = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['userid' => $suspended->id]);
        $this->setUser($suspended);
        $this->assertFalse(profilelink::viewable($this->applicant, $this->course));
        $this->assertSame([], profilelink::viewable_ids($users, $this->course));
    }

    /**
     * The reader's own profile is linked only once they may enter the course: a pending
     * applicant's suspended enrolment does not let them in, an active one does.
     *
     * @return void
     */
    public function test_the_readers_own_profile_needs_access_to_the_course(): void {
        global $DB;

        $this->setUser($this->applicant);
        $this->assertFalse(profilelink::viewable($this->applicant, $this->course));
        $this->assertSame([], profilelink::viewable_ids([$this->applicant->id => 0], $this->course));

        // A fresh $USER, because is_enrolled() caches the current user's enrolments on it.
        $DB->set_field('user_enrolments', 'status', ENROL_USER_ACTIVE, ['id' => $this->ueid]);
        $this->setUser($DB->get_record('user', ['id' => $this->applicant->id], '*', MUST_EXIST));
        $this->assertTrue(profilelink::viewable($this->applicant, $this->course));
        $this->assertSame([(int) $this->applicant->id], profilelink::viewable_ids([$this->applicant->id => 0], $this->course));
    }
}
