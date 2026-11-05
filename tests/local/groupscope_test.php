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

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the separate-groups rule that narrows a decider (docs/design/group-review-scope.md, S1-S4).
 *
 * The stock non-editing teacher lacks moodle/site:accessallgroups and the editing teacher holds
 * it (lib/db/access.php), so the two are the scoped decider and its control. Neither archetype
 * holds this plugin's deciding capability for the non-editing teacher, so setUp() grants it to
 * that role, which is how a site would make a moderator of it.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(groupscope::class)]
final class groupscope_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /**
     * Enable the plugin and let the non-editing teacher decide applications.
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

        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $teacher, \context_system::instance()->id, true);
    }

    /**
     * A course in the given group mode, with an apply instance.
     *
     * @param int $groupmode NOGROUPS, VISIBLEGROUPS or SEPARATEGROUPS.
     * @return array The course and the instance.
     */
    private function course(int $groupmode): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['groupmode' => $groupmode]);
        $id = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());

        return [$course, $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST)];
    }

    /**
     * A pending application on the instance, recording the given key group.
     *
     * @param \stdClass $instance The apply instance.
     * @param int $groupid The key's group, 0 for none.
     * @return int The user enrolment id.
     */
    private function application(\stdClass $instance, int $groupid): int {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $instance->id, 'userid' => $user->id], MUST_EXIST);
        submission::create(
            $instance,
            (int) $user->id,
            $ueid,
            (object) [],
            $groupid,
            $groupid ? submission::KEY_FOR_REVIEW : submission::KEY_NONE
        );

        return $ueid;
    }

    /**
     * Each group mode, with whether it narrows a decider who may not access all groups.
     *
     * @return array Group mode and the expected answer, keyed by case name.
     */
    public static function mode_provider(): array {
        return [
            'no groups' => [NOGROUPS, false],
            'visible groups' => [VISIBLEGROUPS, false],
            'separate groups' => [SEPARATEGROUPS, true],
        ];
    }

    /**
     * Only separate groups narrow, and only somebody without moodle/site:accessallgroups (S1, S2).
     *
     * Changes that must make it fail: visible groups read as separate, the capability not
     * consulted, or the editing teacher narrowed.
     *
     * @param int $groupmode The course's group mode.
     * @param bool $scoped Whether the non-editing teacher is narrowed there.
     * @return void
     */
    #[DataProvider('mode_provider')]
    public function test_only_separate_groups_narrow_and_only_without_access_to_all_groups(int $groupmode, bool $scoped): void {
        [$course] = $this->course($groupmode);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $editing = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $teacher->id]);

        $this->assertSame($scoped, groupscope::is_scoped((int) $course->id, (int) $teacher->id));
        $this->assertSame(
            $scoped ? [(int) $group->id] : null,
            groupscope::narrowed_to((int) $course->id, (int) $teacher->id)
        );

        // The control: the editing teacher holds moodle/site:accessallgroups and is never narrowed.
        $this->assertFalse(groupscope::is_scoped((int) $course->id, (int) $editing->id));
        $this->assertNull(groupscope::narrowed_to((int) $course->id, (int) $editing->id));
    }

    /**
     * A holder of the deciding capability at system level is never narrowed inside a course,
     * though core's own rule does narrow them there.
     *
     * Changes that must make it fail: the system grant not exempted.
     *
     * @return void
     */
    public function test_a_system_grant_is_never_narrowed(): void {
        [$course] = $this->course(SEPARATEGROUPS);
        $sitedecider = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role(['shortname' => 'sitedecider']);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $sitedecider->id, \context_system::instance()->id);

        // The precondition: core's rule alone does narrow them, so the exemption is what is tested.
        $this->assertTrue(groupscope::is_scoped((int) $course->id, (int) $sitedecider->id));
        $this->assertNull(groupscope::narrowed_to((int) $course->id, (int) $sitedecider->id));
    }

    /**
     * The system exemption reads the capability being exercised: a narrowed decider who also
     * holds enrol/apply:viewapplications at system level is not narrowed when viewing, still is
     * when deciding, and is when reading the report, which neither grant exempts (S5, S8).
     *
     * Changes that must make it fail: the exemption reading the deciding capability whatever is
     * exercised.
     *
     * @return void
     */
    public function test_the_system_exemption_reads_the_capability_exercised(): void {
        [$course] = $this->course(SEPARATEGROUPS);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $teacher->id]);
        $role = $this->getDataGenerator()->create_role(['shortname' => 'siteviewer']);
        assign_capability(groupscope::VIEW, CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $teacher->id, \context_system::instance()->id);

        $this->assertNull(groupscope::narrowed_to((int) $course->id, (int) $teacher->id, groupscope::VIEW));
        $this->assertSame([(int) $group->id], groupscope::narrowed_to((int) $course->id, (int) $teacher->id, groupscope::DECIDE));
        $this->assertSame([(int) $group->id], groupscope::narrowed_to((int) $course->id, (int) $teacher->id));
        $this->assertSame([(int) $group->id], groupscope::narrowed_to((int) $course->id, (int) $teacher->id, groupscope::REPORT));
    }

    /**
     * A holder of the deciding capability through a role assigned at the course CATEGORY is
     * narrowed: only the system level is exempt, a role there does not reach down from the
     * system context.
     *
     * Changes that must make it fail: the exemption reading the course's parent context.
     *
     * @return void
     */
    public function test_a_category_grant_is_still_narrowed(): void {
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'category' => $category->id]);
        $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());
        $group = $generator->create_group(['courseid' => $course->id]);

        $user = $generator->create_and_enrol($course, 'student');
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        $role = $generator->create_role(['shortname' => 'categorydecider']);
        set_role_contextlevels($role, [CONTEXT_COURSECAT]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $user->id, \context_coursecat::instance($category->id)->id);

        $this->assertTrue(has_capability(
            'enrol/apply:manageapplications',
            \context_course::instance($course->id),
            $user->id
        ), 'the precondition: the category grant reaches the course');
        $this->assertSame([(int) $group->id], groupscope::narrowed_to((int) $course->id, (int) $user->id));
    }

    /**
     * A narrowed decider in no group is narrowed to nothing, not left unnarrowed (S4).
     *
     * Changes that must make it fail: an empty group list read as "not narrowed".
     *
     * @return void
     */
    public function test_a_narrowed_decider_in_no_group_decides_nothing(): void {
        [$course, $instance] = $this->course(SEPARATEGROUPS);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $ueid = $this->application($instance, (int) $group->id);

        $this->assertSame([], groupscope::narrowed_to((int) $course->id, (int) $teacher->id));
        $this->assertFalse(groupscope::allows((int) $course->id, $ueid, (int) $teacher->id));
        $this->assertSame([], groupscope::where([])[1]);
        $this->assertSame('1 = 0', groupscope::where([])[0]);
    }

    /**
     * A group hidden from its own member does not scope them into it, as core's
     * groups_get_all_groups() would not list it to them; one visible to members does.
     *
     * A custom role, because both stock teacher archetypes hold moodle/course:viewhiddengroups.
     *
     * Changes that must make it fail: the visibility clause dropped from member_groups().
     *
     * @return void
     */
    public function test_a_group_hidden_from_its_member_does_not_count(): void {
        [$course] = $this->course(SEPARATEGROUPS);
        $hidden = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'visibility' => GROUPS_VISIBILITY_NONE]);
        $own = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'visibility' => GROUPS_VISIBILITY_OWN]);
        $moderator = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role(['shortname' => 'moderator']);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $role, \context_system::instance()->id, true);
        $this->getDataGenerator()->enrol_user($moderator->id, $course->id, $role);
        foreach ([$hidden, $own] as $group) {
            $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $moderator->id]);
        }

        $this->assertSame([(int) $own->id], groupscope::narrowed_to((int) $course->id, (int) $moderator->id));

        // The control: with moodle/course:viewhiddengroups both count.
        assign_capability('moodle/course:viewhiddengroups', CAP_ALLOW, $role, \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertSame(
            [(int) $hidden->id, (int) $own->id],
            groupscope::narrowed_to((int) $course->id, (int) $moderator->id)
        );
    }

    /**
     * An application belongs to the group of its newest record, and one without a record or a key
     * to none.
     *
     * Changes that must make it fail: the oldest record read, or a missing record read as a group.
     *
     * @return void
     */
    public function test_an_application_belongs_to_the_group_of_its_newest_record(): void {
        global $DB;

        [$course, $instance] = $this->course(SEPARATEGROUPS);
        $first = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $second = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $ueid = $this->application($instance, (int) $first->id);
        $DB->set_field('enrol_apply_submission', 'timecreated', time() - HOURSECS, ['userenrolmentid' => $ueid]);
        $newer = $DB->get_record('enrol_apply_submission', ['userenrolmentid' => $ueid], '*', MUST_EXIST);
        unset($newer->id);
        $newer->keygroupid = $second->id;
        $newer->timecreated = time();
        $DB->insert_record('enrol_apply_submission', $newer);

        $this->assertSame((int) $second->id, groupscope::application_group($ueid));
        $this->assertSame(0, groupscope::application_group($this->application($instance, 0)));

        $bare = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($instance, $bare->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $bareueid = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $instance->id, 'userid' => $bare->id]);
        $this->assertSame(0, groupscope::application_group($bareueid), 'no record is no group');
    }

    /**
     * The SQL predicate and the per-row check agree on every kind of row: a key of one of the
     * reader's groups, of another, none, no record, and two records whose newest decides.
     *
     * Changes that must make it fail: either side reading another record, or a keyless row
     * matching.
     *
     * @return void
     */
    public function test_the_sql_and_the_per_row_check_agree(): void {
        global $DB;

        [$course, $instance] = $this->course(SEPARATEGROUPS);
        $mine = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $theirs = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->getDataGenerator()->create_group_member(['groupid' => $mine->id, 'userid' => $teacher->id]);

        $own = $this->application($instance, (int) $mine->id);
        $other = $this->application($instance, (int) $theirs->id);
        $keyless = $this->application($instance, 0);
        // Moved from my group to theirs by a newer record.
        $moved = $this->application($instance, (int) $mine->id);
        $DB->set_field('enrol_apply_submission', 'timecreated', time() - HOURSECS, ['userenrolmentid' => $moved]);
        $newer = $DB->get_record('enrol_apply_submission', ['userenrolmentid' => $moved], '*', MUST_EXIST);
        unset($newer->id);
        $newer->keygroupid = $theirs->id;
        $newer->timecreated = time();
        $DB->insert_record('enrol_apply_submission', $newer);

        [$where, $params] = groupscope::where(groupscope::narrowed_to((int) $course->id, (int) $teacher->id));
        $matched = array_map('intval', $DB->get_fieldset_sql(
            "SELECT ue.id FROM {user_enrolments} ue WHERE ue.enrolid = :enrolid AND {$where} ORDER BY ue.id",
            $params + ['enrolid' => $instance->id]
        ));

        $this->assertSame([$own], $matched);
        foreach ([$own, $other, $keyless, $moved] as $ueid) {
            $this->assertSame(
                in_array($ueid, $matched, true),
                groupscope::allows((int) $course->id, $ueid, (int) $teacher->id),
                "row {$ueid}"
            );
        }
    }

    /**
     * The choosers' list: every group for a decider who is not narrowed, their own for one who is
     * (S6).
     *
     * Changes that must make it fail: the narrowing dropped from choosable_groups().
     *
     * @return void
     */
    public function test_a_narrowed_decider_may_choose_only_their_own_groups(): void {
        [$course] = $this->course(SEPARATEGROUPS);
        $mine = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $theirs = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $editing = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->create_group_member(['groupid' => $mine->id, 'userid' => $teacher->id]);

        $this->setUser($teacher);
        $this->assertSame([(int) $mine->id], array_map('intval', array_keys(groupscope::choosable_groups((int) $course->id))));

        $this->setUser($editing);
        $this->assertEqualsCanonicalizing(
            [(int) $mine->id, (int) $theirs->id],
            array_map('intval', array_keys(groupscope::choosable_groups((int) $course->id)))
        );
    }

    /**
     * A group is allowed to a recipient by their own groups, not the current user's.
     *
     * Changes that must make it fail: allows_group() reading $USER.
     *
     * @return void
     */
    public function test_a_group_is_judged_for_the_user_named(): void {
        [$course] = $this->course(SEPARATEGROUPS);
        $mine = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $theirs = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->getDataGenerator()->create_group_member(['groupid' => $mine->id, 'userid' => $teacher->id]);
        $this->setAdminUser();

        $this->assertTrue(groupscope::allows_group((int) $course->id, (int) $mine->id, (int) $teacher->id));
        $this->assertFalse(groupscope::allows_group((int) $course->id, (int) $theirs->id, (int) $teacher->id));
        $this->assertFalse(groupscope::allows_group((int) $course->id, 0, (int) $teacher->id));
        // The control: the administrator, who is the current user here, is allowed all of them.
        $this->assertTrue(groupscope::allows_group((int) $course->id, 0, (int) get_admin()->id));
    }
}
