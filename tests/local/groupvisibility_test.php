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
 * Tests for the group visibility rule the plugin's group names follow.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(groupvisibility::class)]
final class groupvisibility_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private $course;

    /**
     * A course.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * A user enrolled with a role of their own that may manage applications and holds no
     * moodle/course:viewhiddengroups, which every stock archetype holding that capability holds.
     *
     * @return \stdClass The user.
     */
    private function clerk(): \stdClass {
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applyclerk']);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        $clerk = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($clerk->id, $this->course->id, 'applyclerk');

        return $clerk;
    }

    /**
     * A group of the course with the given visibility.
     *
     * @param int $visibility One of the GROUPS_VISIBILITY_ constants.
     * @return \stdClass The {groups} record.
     */
    private function group(int $visibility): \stdClass {
        return $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'visibility' => $visibility]);
    }

    /**
     * Each visibility, for a member and for somebody who is not one.
     *
     * @return array Visibility, whether the reader is a member, and the expected answer.
     */
    public static function visibility_provider(): array {
        return [
            'all, not a member' => [GROUPS_VISIBILITY_ALL, false, true],
            'members, a member' => [GROUPS_VISIBILITY_MEMBERS, true, true],
            'members, not a member' => [GROUPS_VISIBILITY_MEMBERS, false, false],
            'own, a member' => [GROUPS_VISIBILITY_OWN, true, true],
            'own, not a member' => [GROUPS_VISIBILITY_OWN, false, false],
            'none, a member' => [GROUPS_VISIBILITY_NONE, true, false],
            'none, not a member' => [GROUPS_VISIBILITY_NONE, false, false],
        ];
    }

    /**
     * A reader without viewhiddengroups sees exactly what groups_get_all_groups() lists to them.
     *
     * Changes that must make it fail: a visible-to-all group hidden, a member of a MEMBERS or OWN
     * group not told its name, or a hidden group named to somebody who is not a member.
     *
     * @param int $visibility The group's visibility.
     * @param bool $member Whether the reader is a member.
     * @param bool $expected Whether the name may be shown.
     * @return void
     */
    #[DataProvider('visibility_provider')]
    public function test_a_reader_sees_what_core_lists_to_them(int $visibility, bool $member, bool $expected): void {
        $clerk = $this->clerk();
        $group = $this->group($visibility);
        if ($member) {
            groups_add_member($group->id, $clerk->id);
        }

        $this->assertSame($expected, groupvisibility::visible_to($group, (int) $clerk->id));

        // The rule it mirrors, asked as that reader.
        $this->setUser($clerk);
        $this->assertSame($expected, array_key_exists($group->id, groups_get_all_groups($this->course->id)));
    }

    /**
     * A reader holding viewhiddengroups in the course sees every group, a member or not.
     *
     * Changes that must make it fail: the capability not consulted.
     *
     * @return void
     */
    public function test_viewhiddengroups_sees_every_group(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $this->assertTrue(groupvisibility::visible_to($this->group(GROUPS_VISIBILITY_NONE), (int) $teacher->id));
        $this->assertTrue(groupvisibility::visible_to($this->group(GROUPS_VISIBILITY_MEMBERS), (int) $teacher->id));
    }

    /**
     * The reader passed is the one judged, not the session's user.
     *
     * Changes that must make it fail: judging $USER.
     *
     * @return void
     */
    public function test_the_reader_is_judged_rather_than_the_session_user(): void {
        $clerk = $this->clerk();
        $hidden = $this->group(GROUPS_VISIBILITY_NONE);

        $this->setAdminUser();
        $this->assertFalse(groupvisibility::visible_to($hidden, (int) $clerk->id));

        $this->setUser($clerk);
        $this->assertTrue(groupvisibility::visible_to($hidden, (int) get_admin()->id));
    }

    /**
     * A record that does not carry its visibility reads as hidden, from a reader who would see the
     * group with it.
     *
     * @return void
     */
    public function test_a_record_without_its_visibility_reads_as_hidden(): void {
        $clerk = $this->clerk();
        $group = $this->group(GROUPS_VISIBILITY_ALL);

        $this->assertTrue(groupvisibility::visible_to($group, (int) $clerk->id), 'the control');
        $this->assertFalse(groupvisibility::visible_to(
            (object) ['id' => $group->id, 'courseid' => $group->courseid],
            (int) $clerk->id
        ));
    }
}
