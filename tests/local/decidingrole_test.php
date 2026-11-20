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
 * Tests for which roles decide applications in a course, and so which methods are deciding ones.
 *
 * A role decides as core resolves a role's capability in a context (docs/design/applicant-group-choice.md,
 * C-D29): its definition, every override on the path with the deepest winning, and a prohibit
 * anywhere winning over everything. The stock editing teacher holds enrol/apply:manageapplications
 * (db/access.php) and the student and the non-editing teacher do not.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(decidingrole::class)]
final class decidingrole_test extends \advanced_testcase {
    /** @var \stdClass A course in a category. */
    private $course;

    /** @var \stdClass The course's category. */
    private $category;

    /**
     * A course in a category of its own, so an override can sit on either.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->category = $this->getDataGenerator()->create_category();
        $this->course = $this->getDataGenerator()->create_course(['category' => $this->category->id]);
    }

    /**
     * A stock role's id.
     *
     * @param string $shortname The role's short name.
     * @return int The id.
     */
    private function role(string $shortname): int {
        global $DB;

        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * Roles decide by their definition: the editing teacher does, the student, the non-editing
     * teacher and no role at all do not.
     *
     * Changes that must make it fail: every role read as deciding, or none.
     *
     * @return void
     */
    public function test_a_role_decides_by_its_definition(): void {
        $courseid = (int) $this->course->id;

        $this->assertTrue(decidingrole::decides($this->role('editingteacher'), $courseid));
        $this->assertFalse(decidingrole::decides($this->role('student'), $courseid));
        $this->assertFalse(decidingrole::decides($this->role('teacher'), $courseid));
        $this->assertFalse(decidingrole::decides(0, $courseid));
        $this->assertContains($this->role('editingteacher'), decidingrole::roles($courseid));
        $this->assertNotContains($this->role('student'), decidingrole::roles($courseid));
    }

    /**
     * An override in the course makes a role decide there and nowhere else, and one that prevents
     * stops a role deciding there: the deepest context on the path wins.
     *
     * Changes that must make it fail: the course's overrides not read (the system definition
     * alone), or another course's read.
     *
     * @return void
     */
    public function test_an_override_on_the_path_decides(): void {
        $other = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($this->course->id);
        $teacher = $this->role('teacher');
        $editing = $this->role('editingteacher');

        assign_capability(groupscope::DECIDE, CAP_ALLOW, $teacher, $context->id, true);
        assign_capability(groupscope::DECIDE, CAP_PREVENT, $editing, $context->id, true);

        $this->assertTrue(decidingrole::decides($teacher, (int) $this->course->id));
        $this->assertFalse(decidingrole::decides($teacher, (int) $other->id), 'another course is not overridden');
        $this->assertFalse(decidingrole::decides($editing, (int) $this->course->id), 'the course prevents it');
        $this->assertTrue(decidingrole::decides($editing, (int) $other->id), 'the control: the definition still holds elsewhere');
    }

    /**
     * A prohibit higher up wins over an allow in the course, as core's resolution has it.
     *
     * Changes that must make it fail: the prohibited roles not subtracted (the allowed list alone).
     *
     * @return void
     */
    public function test_a_prohibit_on_the_path_wins(): void {
        $teacher = $this->role('teacher');
        assign_capability(groupscope::DECIDE, CAP_ALLOW, $teacher, \context_course::instance($this->course->id)->id, true);
        $this->assertTrue(decidingrole::decides($teacher, (int) $this->course->id), 'the precondition');

        assign_capability(groupscope::DECIDE, CAP_PROHIBIT, $teacher, \context_coursecat::instance($this->category->id)->id, true);
        $this->assertFalse(decidingrole::decides($teacher, (int) $this->course->id));
    }

    /**
     * A method decides by the role it grants on approval: Student is a learner method, a role
     * that decides is a deciding method, no role is a learner method.
     *
     * Changes that must make it fail: the method judged by anything but its role.
     *
     * @return void
     */
    public function test_a_method_decides_by_the_role_it_grants(): void {
        $method = fn(int $roleid): \stdClass => (object) ['courseid' => $this->course->id, 'roleid' => $roleid];

        $this->assertFalse(decidingrole::method_decides($method($this->role('student'))));
        $this->assertTrue(decidingrole::method_decides($method($this->role('editingteacher'))));
        $this->assertFalse(decidingrole::method_decides($method(0)));
    }
}
