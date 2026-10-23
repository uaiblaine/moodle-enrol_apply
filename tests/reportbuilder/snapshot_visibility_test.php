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
 * Tests for the snapshot's masking rule.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\reportbuilder;

use context_course;
use enrol_apply\reportbuilder\local\formatters\submission as formatter;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The snapshot shows a native or custom profile field only where core would show it.
 *
 * Every reader here holds moodle/site:viewuseridentity in the course unless a test says
 * otherwise, so each test isolates one of core's other rules: showuseridentity for the identity
 * columns, hiddenuserfields for the hidden ones, and each custom field's own visibility. Each
 * capability a test turns off is PROHIBITed in the course, so a default elsewhere cannot decide
 * the result.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(formatter::class)]
final class snapshot_visibility_test extends \advanced_testcase {
    /** @var \stdClass The course the readers are judged in. */
    private $course;

    /**
     * A course to judge readers in.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * A reader holding the given capabilities, and prohibited the others named, in the course.
     *
     * @param array $allow Capabilities allowed.
     * @param array $prohibit Capabilities prohibited.
     * @return \stdClass The user.
     */
    private function reader(array $allow, array $prohibit = []): \stdClass {
        $context = context_course::instance($this->course->id);
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($allow as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        }
        foreach ($prohibit as $capability) {
            assign_capability($capability, CAP_PROHIBIT, $roleid, $context->id, true);
        }
        role_assign($roleid, $user->id, $context->id);

        return $user;
    }

    /**
     * Whether the reader may see a key in the course.
     *
     * @param string $key Snapshot key.
     * @param \stdClass $reader Reader, judged by id rather than as the session user.
     * @return bool
     */
    private function shows(string $key, \stdClass $reader): bool {
        $visible = formatter::visible_keys(context_course::instance($this->course->id), (int) $reader->id);

        return formatter::shows($key, $visible);
    }

    /**
     * Department and institution show only where showuseridentity names them.
     *
     * @return void
     */
    public function test_identity_columns_follow_showuseridentity(): void {
        $reader = $this->reader(['moodle/site:viewuseridentity']);

        set_config('showuseridentity', 'email');
        $this->assertFalse($this->shows('s_department', $reader));
        $this->assertFalse($this->shows('s_institution', $reader));
        // Controls: a name and a column that is not an identity field still show.
        $this->assertTrue($this->shows('s_firstname', $reader));
        $this->assertTrue($this->shows('s_city', $reader));

        set_config('showuseridentity', 'email,department');
        $this->assertTrue($this->shows('s_department', $reader));
        $this->assertFalse($this->shows('s_institution', $reader));
    }

    /**
     * A column named in hiddenuserfields needs moodle/course:viewhiddenuserfields.
     *
     * @return void
     */
    public function test_hidden_columns_follow_viewhiddenuserfields(): void {
        set_config('hiddenuserfields', 'city');
        $without = $this->reader(['moodle/site:viewuseridentity'], ['moodle/course:viewhiddenuserfields']);
        $with = $this->reader(['moodle/site:viewuseridentity', 'moodle/course:viewhiddenuserfields']);

        $this->assertFalse($this->shows('s_city', $without));
        // Control: a column the site does not hide shows to the same reader.
        $this->assertTrue($this->shows('s_country', $without));
        $this->assertTrue($this->shows('s_city', $with));
    }

    /**
     * A custom profile field follows its own visibility, as profile_field_base::is_visible() does.
     *
     * @return void
     */
    public function test_custom_fields_follow_their_visibility(): void {
        global $CFG;

        require_once($CFG->dirroot . '/user/profile/lib.php');
        $generator = $this->getDataGenerator();
        $keys = [];
        foreach ([PROFILE_VISIBLE_ALL, PROFILE_VISIBLE_TEACHERS, PROFILE_VISIBLE_PRIVATE, PROFILE_VISIBLE_NONE] as $visible) {
            $field = $generator->create_custom_profile_field([
                'datatype' => 'text',
                'shortname' => 'vis' . $visible,
                'name' => 'Visibility ' . $visible,
                'visible' => $visible,
            ]);
            $keys[$visible] = \enrol_apply\local\fields::custom_key((int) $field->id);
        }
        $gone = \enrol_apply\local\fields::custom_key(max(array_map(static fn($key) => (int) substr($key, 2), $keys)) + 1000);

        $teacher = $this->reader(['moodle/site:viewuseridentity'], ['moodle/user:viewalldetails']);
        $this->assertTrue($this->shows($keys[PROFILE_VISIBLE_ALL], $teacher));
        $this->assertTrue($this->shows($keys[PROFILE_VISIBLE_TEACHERS], $teacher));
        $this->assertFalse($this->shows($keys[PROFILE_VISIBLE_PRIVATE], $teacher));
        $this->assertFalse($this->shows($keys[PROFILE_VISIBLE_NONE], $teacher));
        // A field deleted since submission has no visibility left to read, so it is withheld.
        $this->assertFalse($this->shows($gone, $teacher));

        $all = $this->reader(['moodle/site:viewuseridentity', 'moodle/user:viewalldetails']);
        $this->assertTrue($this->shows($keys[PROFILE_VISIBLE_PRIVATE], $all));
        $this->assertTrue($this->shows($keys[PROFILE_VISIBLE_NONE], $all));
        $this->assertTrue($this->shows($gone, $all));
    }

    /**
     * The answers to the method's own questions follow the identity capability alone.
     *
     * @return void
     */
    public function test_answers_and_names_follow_the_identity_capability(): void {
        $with = $this->reader(['moodle/site:viewuseridentity']);
        $without = $this->reader([], ['moodle/site:viewuseridentity']);

        $this->assertTrue($this->shows('a_5', $with));
        $this->assertFalse($this->shows('a_5', $without));
        $this->assertFalse($this->shows('s_city', $without));
        // Control: the name is the one thing a reader without the capability keeps.
        $this->assertTrue($this->shows('s_lastname', $without));
    }

    /**
     * The reader named is judged, not the session user, which the e-mail relies on.
     *
     * @return void
     */
    public function test_the_named_reader_is_judged_not_the_session_user(): void {
        set_config('hiddenuserfields', 'city');
        $reader = $this->reader(['moodle/site:viewuseridentity'], ['moodle/course:viewhiddenuserfields']);
        $this->setAdminUser();

        $this->assertFalse($this->shows('s_city', $reader));
        // Control: the administrator running the request holds the override and would see it.
        $context = context_course::instance($this->course->id);
        $this->assertTrue(formatter::shows('s_city', formatter::visible_keys($context)));
    }
}
