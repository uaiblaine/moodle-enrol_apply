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

/**
 * Tests for the per-group seats read from local_groupdist.
 *
 * The "installed" cases skip where local_groupdist, or its public seats reader, is absent; the
 * "not installed" cases run everywhere, through a subclass that answers available() with false.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(groupseats::class)]
final class groupseats_test extends \advanced_testcase {
    /** @var \stdClass A course. */
    private $course;

    /**
     * A course to hold the groups.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Skip unless local_groupdist and its public reader are installed, then make sure its
     * Seats field exists.
     *
     * local_groupdist provisions the field from db/install.php, which returns early during an
     * initial install - the way every PHPUnit site is built - and it memoises the lookup per
     * process. So a test that needs seats provisions them through the call local_groupdist's own
     * tests use, clearing that memo on both sides. Test setup only: production code here reads
     * nothing but \local_groupdist\seats.
     *
     * @return void
     */
    private function require_groupdist(): void {
        if (!class_exists('\local_groupdist\seats')) {
            $this->markTestSkipped('local_groupdist with \local_groupdist\seats is not installed');
        }
        \local_groupdist\local\fields::reset_field_cache();
        \local_groupdist\local\fields::ensure_fields_exist();
        \local_groupdist\local\fields::reset_field_cache();
    }

    /**
     * A group with the given seats declared and the given number of members.
     *
     * @param int|null $seats Seats to declare, or null for none.
     * @param int $members Members to add.
     * @return int Group id.
     */
    private function group(?int $seats, int $members): int {
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        if ($seats !== null) {
            // The core handler saves only the fields the current user may edit.
            $this->setAdminUser();
            \core_group\customfield\group_handler::create()->instance_form_save((object) [
                'id' => $group->id,
                'customfield_groupdist_seats' => $seats,
            ]);
        }
        for ($i = 0; $i < $members; $i++) {
            $user = $this->getDataGenerator()->create_and_enrol($this->course);
            groups_add_member($group->id, $user->id);
        }
        return (int) $group->id;
    }

    /**
     * The class standing in for a site without local_groupdist.
     *
     * @return string Name of a groupseats subclass whose available() is false.
     */
    private function absent(): string {
        $absent = new class extends groupseats {
            /**
             * Pretend local_groupdist is not installed.
             *
             * @return bool Always false.
             */
            public static function available(): bool {
                return false;
            }
        };
        return get_class($absent);
    }

    /**
     * Without local_groupdist nothing is known, and nothing is ever full.
     *
     * Changes that must make it fail: reading the provider without asking available() first.
     *
     * @return void
     */
    public function test_without_groupdist_no_group_has_seats(): void {
        $absent = $this->absent();
        $groupid = (int) $this->getDataGenerator()->create_group(['courseid' => $this->course->id])->id;

        $result = $absent::for_groups([$groupid]);

        $this->assertNull($result[$groupid]->seats);
        $this->assertNull($result[$groupid]->members);
        $this->assertSame([], $absent::full([$groupid]));
    }

    /**
     * Seats and members come from local_groupdist when it is installed.
     *
     * @return void
     */
    public function test_with_groupdist_seats_and_members_are_read(): void {
        $this->require_groupdist();
        $groupid = $this->group(3, 2);

        $result = groupseats::for_groups([(string) $groupid, $groupid]);

        $this->assertTrue(groupseats::available());
        $this->assertSame([$groupid], array_keys($result));
        $this->assertSame(3, $result[$groupid]->seats);
        $this->assertSame(2, $result[$groupid]->members);
    }

    /**
     * Full means members >= seats; no declared seats never blocks; a declared 0 blocks at once.
     *
     * Changes that must make it fail: `>` for `>=`, treating a declared 0 as "no limit", or
     * reading a missing value as 0.
     *
     * @return void
     */
    public function test_which_groups_are_full(): void {
        $this->require_groupdist();
        $roomy = $this->group(3, 2);
        $exact = $this->group(2, 2);
        $over = $this->group(1, 2);
        $zero = $this->group(0, 0);
        $unlimited = $this->group(null, 5);

        $this->assertSame(
            [$exact, $over, $zero],
            groupseats::full([$roomy, $exact, $over, $zero, $unlimited])
        );
    }

    /**
     * Every member takes a seat, whatever their role and whichever component added them, as on
     * local_groupdist's own bulk edit page (G13).
     *
     * @return void
     */
    public function test_every_member_takes_a_seat(): void {
        $this->require_groupdist();
        $groupid = $this->group(3, 1);
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        groups_add_member($groupid, $teacher->id);
        $stamped = $this->getDataGenerator()->create_and_enrol($this->course);
        groups_add_member($groupid, $stamped->id, 'enrol_apply', 0);

        $this->assertSame(3, groupseats::for_groups([$groupid])[$groupid]->members);
        $this->assertSame([$groupid], groupseats::full([$groupid]));
    }

    /**
     * A Seats field deleted from the site reads as no seats: nothing is full any more.
     *
     * @return void
     */
    public function test_a_deleted_seats_field_reads_as_no_seats(): void {
        global $DB;

        $this->require_groupdist();
        $groupid = $this->group(0, 0);
        $this->assertSame([$groupid], groupseats::full([$groupid]), 'the precondition: a declared 0 is full');

        $fieldid = (int) $DB->get_field('customfield_field', 'id', ['shortname' => 'groupdist_seats'], MUST_EXIST);
        \core_customfield\api::delete_field_configuration(\core_customfield\field_controller::create($fieldid));
        \local_groupdist\local\fields::reset_field_cache();

        $this->assertNull(groupseats::for_groups([$groupid])[$groupid]->seats);
        $this->assertSame([], groupseats::full([$groupid]));
    }

    /**
     * An empty list asks nothing and answers nothing.
     *
     * @return void
     */
    public function test_an_empty_list_is_empty(): void {
        $this->assertSame([], groupseats::for_groups([]));
        $this->assertSame([], groupseats::full([]));
    }
}
