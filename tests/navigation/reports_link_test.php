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
 * Tests for the queue's link among the course reports.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\navigation;

use enrol_apply\local\queue;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\CoversMethod;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * Tests for enrol_apply_extend_navigation_course(), the queue's link among the course reports.
 *
 * The link exists for the reader who decides or views applications without editing enrolment
 * methods, so every test that expects no link first shows the same setup with the capability
 * granted produces one.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('enrol_apply_extend_navigation_course')]
#[CoversMethod(queue::class, 'may_list_instance')]
final class reports_link_test extends \advanced_testcase {
    /**
     * A course with one apply instance and a non-editing teacher in it.
     *
     * @return array Course, its context, the instance and the teacher.
     */
    protected function fixture(): array {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $plugin = enrol_get_plugin('apply');
        $instanceid = (int) $plugin->add_instance($course, $plugin->get_instance_defaults());
        $teacher = $generator->create_and_enrol($course, 'teacher');
        return [$course, \context_course::instance($course->id), $instanceid, $teacher];
    }

    /**
     * Allows one capability to the teacher role in the course.
     *
     * @param string $capability The capability.
     * @param \context_course $context The course context.
     * @param int $permission CAP_ALLOW or CAP_PROHIBIT.
     * @return void
     */
    protected function set_teacher_capability(string $capability, \context_course $context, int $permission = CAP_ALLOW): void {
        global $DB;
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability($capability, $permission, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * Runs the callback on a course administration node that holds core's Reports node or not.
     *
     * @param \stdClass $course The course.
     * @param \context_course $context The course context.
     * @param bool $withreports Whether core's Reports node is there before the callback runs.
     * @return \navigation_node The course administration node after the callback.
     */
    protected function run_callback(\stdClass $course, \context_course $context, bool $withreports = true): \navigation_node {
        $parent = new \navigation_node(['text' => 'Course administration', 'key' => 'courseadmin']);
        if ($withreports) {
            $parent->add(
                get_string('reports'),
                new \moodle_url('/report/view.php', ['courseid' => $course->id]),
                \navigation_node::TYPE_CONTAINER,
                null,
                'coursereports'
            );
        }
        enrol_apply_extend_navigation_course($parent, $course, $context);
        return $parent;
    }

    /**
     * The queue links under the Reports node, keyed by instance id.
     *
     * @param \navigation_node $parent The course administration node.
     * @return array Link urls (as strings) keyed by node key; empty without a Reports node.
     */
    protected function queue_links(\navigation_node $parent): array {
        $reports = $parent->get('coursereports', \navigation_node::TYPE_CONTAINER);
        if (!$reports) {
            return [];
        }
        $links = [];
        foreach ($reports->children as $child) {
            if (str_starts_with((string) $child->key, 'enrol_apply_queue_')) {
                $links[$child->key] = $child->action->out(false);
            }
        }
        return $links;
    }

    /**
     * Either reading capability in the course offers the queue, as manage.php?id= admits it.
     *
     * Changes that must make it fail: the gate dropped, or reading only one of the two
     * capabilities.
     *
     * @return void
     */
    public function test_either_reading_capability_offers_the_queue(): void {
        [$course, $context, $instanceid, $teacher] = $this->fixture();
        $this->setUser($teacher);
        $expected = [
            'enrol_apply_queue_' . $instanceid => (new \moodle_url('/enrol/apply/manage.php', ['id' => $instanceid]))->out(false),
        ];

        // Precondition: the default teacher role holds neither, and neither the page nor the link admits it.
        $this->assertFalse(queue::listing_scope($instanceid)->allowed);
        $this->assertSame([], $this->queue_links($this->run_callback($course, $context)));

        $this->set_teacher_capability('enrol/apply:viewapplications', $context);
        $this->assertTrue(queue::listing_scope($instanceid)->allowed);
        $this->assertSame($expected, $this->queue_links($this->run_callback($course, $context)));

        $this->set_teacher_capability('enrol/apply:viewapplications', $context, CAP_INHERIT);
        $this->set_teacher_capability('enrol/apply:manageapplications', $context);
        $this->assertTrue(queue::listing_scope($instanceid)->allowed);
        $this->assertSame($expected, $this->queue_links($this->run_callback($course, $context)));
    }

    /**
     * The link leaves enrolment-method editing out of it: the moderator it is for holds none.
     *
     * Changes that must make it fail: the link gated on moodle/course:enrolreview or
     * enrol/apply:config, the gates of the enrolment methods node.
     *
     * @return void
     */
    public function test_the_moderator_needs_no_enrolment_method_capability(): void {
        [$course, $context, $instanceid, $teacher] = $this->fixture();
        $this->set_teacher_capability('enrol/apply:manageapplications', $context);
        $this->set_teacher_capability('moodle/course:enrolreview', $context, CAP_PROHIBIT);
        $this->set_teacher_capability('enrol/apply:config', $context, CAP_PROHIBIT);
        $this->setUser($teacher);

        $this->assertArrayHasKey(
            'enrol_apply_queue_' . $instanceid,
            $this->queue_links($this->run_callback($course, $context))
        );
    }

    /**
     * A course with no apply instance gets no link, and no Reports node made for one.
     *
     * Changes that must make it fail: the instance filter dropped, or the empty-list return.
     *
     * @return void
     */
    public function test_a_course_without_an_apply_instance_gets_nothing(): void {
        [, , , $teacher] = $this->fixture();
        $generator = $this->getDataGenerator();
        $other = $generator->create_course();
        $generator->enrol_user($teacher->id, $other->id, 'teacher');
        $othercontext = \context_course::instance($other->id);
        $this->set_teacher_capability('enrol/apply:manageapplications', $othercontext);
        $this->setUser($teacher);

        // The manual and self instances every new course carries are not apply instances.
        $this->assertNotEmpty(enrol_get_instances($other->id, false));
        $this->assertSame([], $this->queue_links($this->run_callback($other, $othercontext)));
        $this->assertFalse($this->run_callback($other, $othercontext, false)->get('coursereports'));
    }

    /**
     * One instance is named by the page; several by their own names.
     *
     * Changes that must make it fail: the count test inverted, or a second instance dropped.
     *
     * @return void
     */
    public function test_several_instances_are_told_apart_by_name(): void {
        [$course, $context, $firstid, $teacher] = $this->fixture();
        $this->set_teacher_capability('enrol/apply:manageapplications', $context);
        $this->setUser($teacher);

        $reports = $this->run_callback($course, $context)->get('coursereports');
        $this->assertSame(get_string('confirmusers', 'enrol_apply'), $reports->get('enrol_apply_queue_' . $firstid)->text);

        $plugin = enrol_get_plugin('apply');
        $secondid = (int) $plugin->add_instance($course, ['name' => 'Second round'] + $plugin->get_instance_defaults());
        $reports = $this->run_callback($course, $context)->get('coursereports');
        $first = $plugin->get_instance_name(enrol_get_instances($course->id, false)[$firstid]);
        $this->assertSame(
            get_string('confirmusersinstance', 'enrol_apply', $first),
            $reports->get('enrol_apply_queue_' . $firstid)->text
        );
        $this->assertSame(
            get_string('confirmusersinstance', 'enrol_apply', 'Second round'),
            $reports->get('enrol_apply_queue_' . $secondid)->text
        );
    }

    /**
     * A Reports node core removed for being empty is put back on core's own condition.
     *
     * Changes that must make it fail: the node not recreated, or recreated without the
     * moodle/site:viewreports test.
     *
     * @return void
     */
    public function test_an_empty_reports_node_comes_back_only_for_a_report_viewer(): void {
        [$course, $context, $instanceid, $teacher] = $this->fixture();
        $this->set_teacher_capability('enrol/apply:manageapplications', $context);
        $this->setUser($teacher);

        // The default teacher role reads reports, as core's own condition asks.
        $this->assertTrue(has_capability('moodle/site:viewreports', $context));
        $parent = $this->run_callback($course, $context, false);
        $reports = $parent->get('coursereports', \navigation_node::TYPE_CONTAINER);
        $this->assertNotFalse($reports);
        $this->assertSame(
            (new \moodle_url('/report/view.php', ['courseid' => $course->id]))->out(false),
            $reports->action->out(false)
        );
        $this->assertArrayHasKey('enrol_apply_queue_' . $instanceid, $this->queue_links($parent));

        $this->set_teacher_capability('moodle/site:viewreports', $context, CAP_PROHIBIT);
        $this->assertFalse($this->run_callback($course, $context, false)->get('coursereports'));
    }

    /**
     * Core runs the callback after it builds the Reports node, so the link lands in it.
     *
     * The reason this is a file-scope callback rather than part of add_course_navigation():
     * enrol_add_course_navigation() runs before the Reports node exists.
     *
     * @return void
     */
    public function test_the_link_reaches_the_reports_node_of_the_real_settings_navigation(): void {
        global $PAGE;
        [$course, $context, $instanceid, $teacher] = $this->fixture();
        $this->set_teacher_capability('enrol/apply:manageapplications', $context);
        $this->setUser($teacher);

        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
        $PAGE->set_course($course);
        $PAGE->settingsnav->initialise();
        $reports = $PAGE->settingsnav->find('coursereports', \navigation_node::TYPE_CONTAINER);

        $this->assertNotFalse($reports);
        $this->assertNotFalse($reports->get('enrol_apply_queue_' . $instanceid));
    }
}
