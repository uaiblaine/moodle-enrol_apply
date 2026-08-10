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
 * Tests for the mentee lookup.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

/**
 * Tests for the mentee lookup.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_apply\local\applications
 */
final class applications_test extends \advanced_testcase {
    /**
     * Create a role assignable in user contexts that grants the manage capability.
     *
     * @return int The new role id.
     */
    protected function create_mentor_role(): int {
        $roleid = $this->getDataGenerator()->create_role([
            'shortname' => 'applymentor',
            'name' => 'Apply mentor',
            'archetype' => '',
        ]);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance()->id);

        return $roleid;
    }

    /**
     * A user assigned over another user's context mentors them.
     *
     * @return void
     */
    public function test_role_assignment_in_user_context_creates_a_mentee(): void {
        $this->resetAfterTest();

        $mentor = $this->getDataGenerator()->create_user();
        $mentee = $this->getDataGenerator()->create_user();
        $stranger = $this->getDataGenerator()->create_user();

        role_assign($this->create_mentor_role(), $mentor->id, \context_user::instance($mentee->id)->id);
        $this->setUser($mentor);

        $found = applications::get_mentees();
        $this->assertEquals([(int) $mentee->id], $found);
        $this->assertNotContains((int) $stranger->id, $found);
    }

    /**
     * Sharing a cohort is not by itself enough to mentor somebody.
     *
     * The previous implementation enumerated cohort peers, which both scanned the whole
     * cohort on every request and hid mentees who happened not to share one.
     *
     * @return void
     */
    public function test_cohort_membership_alone_does_not_create_a_mentee(): void {
        $this->resetAfterTest();

        $mentor = $this->getDataGenerator()->create_user();
        $peer = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $mentor->id);
        cohort_add_member($cohort->id, $peer->id);

        $this->setUser($mentor);

        $this->assertSame([], applications::get_mentees());
    }

    /**
     * A mentee outside the mentor's cohorts is still listed.
     *
     * @return void
     */
    public function test_mentee_outside_any_shared_cohort_is_listed(): void {
        $this->resetAfterTest();

        $mentor = $this->getDataGenerator()->create_user();
        $mentee = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $mentor->id);

        role_assign($this->create_mentor_role(), $mentor->id, \context_user::instance($mentee->id)->id);
        $this->setUser($mentor);

        $this->assertEquals([(int) $mentee->id], applications::get_mentees());
    }

    /**
     * A role assignment that does not carry the capability does not create a mentee.
     *
     * @return void
     */
    public function test_role_without_the_capability_creates_no_mentee(): void {
        $this->resetAfterTest();

        $mentor = $this->getDataGenerator()->create_user();
        $mentee = $this->getDataGenerator()->create_user();

        $roleid = $this->getDataGenerator()->create_role([
            'shortname' => 'plainmentor',
            'name' => 'Plain mentor',
            'archetype' => '',
        ]);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        role_assign($roleid, $mentor->id, \context_user::instance($mentee->id)->id);
        $this->setUser($mentor);

        $this->assertSame([], applications::get_mentees());
    }

    /**
     * A user with no role assignments at all mentors nobody.
     *
     * @return void
     */
    public function test_user_without_assignments_mentors_nobody(): void {
        $this->resetAfterTest();

        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertSame([], applications::get_mentees());
    }
}
