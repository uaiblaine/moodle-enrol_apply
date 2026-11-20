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

namespace enrol_apply\reportbuilder;

use enrol_apply\local\submission;
use enrol_apply\reportbuilder\local\formatters\submission as formatter;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * A group name carrying the report's separator characters cannot forge a group in the cell (audit M-06).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(formatter::class)]
#[CoversClass(submission::class)]
final class joined_groups_forgery_test extends \advanced_testcase {
    /**
     * The aggregate the report's SQL builds for a record that joined the given groups.
     *
     * @param \stdClass $course The course.
     * @param array $groups The groups joined.
     * @return string|null What joined_groups_sql() produced.
     */
    private function aggregate(\stdClass $course, array $groups): ?string {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $id = $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $course->id,
            'userid' => $user->id,
            'enrolid' => 0,
            'userenrolmentid' => 0,
            'comment' => '',
            'status' => submission::STATUS_APPROVED,
            'outcomemessage' => '',
            'timecreated' => time(),
            'timedecided' => time(),
            'decidedby' => 0,
            'joinedgroups' => implode(',', array_map(static fn($group) => (int) $group->id, $groups)),
        ]);
        $sql = submission::joined_groups_sql('s');
        $value = $DB->get_field_sql("SELECT {$sql} FROM {enrol_apply_submission} s WHERE s.id = :id", ['id' => $id]);

        return $value === false ? null : $value;
    }

    /**
     * A name that carries a forged part for a group of a missing course renders without a forged group.
     *
     * Both spellings of the call are held: the report's, which has no course on the row, and the
     * queue's, which has it. The control is an ordinary group, which still renders.
     *
     * Changes that must make it fail: the parts of another course kept, or the course context
     * read without IGNORE_MISSING (it throws for the forged course).
     *
     * @return void
     */
    public function test_a_forged_part_is_dropped_and_the_cell_renders(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $plain = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Plain']);
        $forger = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Forger']);
        $forged = "Real\x1E424242\x1F999999\x1F0\x1Fx";
        $DB->set_field('groups', 'name', $forged, ['id' => $forger->id]);

        $this->assertSame('Plain', formatter::joined_groups($this->aggregate($course, [$plain]), (object) []), 'the control');

        $value = $this->aggregate($course, [$plain, $forger]);
        $this->assertStringContainsString("\x1F999999\x1F", $value, 'the premise: the forged part is in the aggregate');
        $this->assertSame('Plain, Real', formatter::joined_groups($value, (object) []));
        $this->assertSame('Plain, Real', formatter::joined_groups($value, (object) ['courseid' => $course->id]));
        $this->assertSame([(int) $plain->id, (int) $forger->id], array_keys(submission::parse_joined_groups($value)));
    }

    /**
     * A group whose course no longer has a context is skipped rather than thrown on.
     *
     * Changes that must make it fail: the context read without IGNORE_MISSING.
     *
     * @return void
     */
    public function test_a_group_of_a_course_without_a_context_is_skipped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $value = "5\x1F999999\x1F0\x1FOrphan";

        $this->assertSame('', formatter::joined_groups($value, (object) []));
        $this->assertSame([5], array_keys(submission::parse_joined_groups($value)));
    }
}
