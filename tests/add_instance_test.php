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

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass target below resolves when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * A method created with fields missing carries the plugin's defaults for them.
 *
 * Core's generic enrol/editinstance.php?type=apply (reachable by a holder of enrol/apply:config or
 * moodle/course:editcoursewelcomemessage with moodle/course:enrolconfig) builds no form of this
 * plugin's and calls add_instance($course, $fields) with id, courseid, type and returnurl alone, so
 * the method's settings used to be NULL, every one of them.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
final class add_instance_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass The course. */
    private $course;

    /**
     * A course, and the plugin enabled.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * The fields core's generic page posts for a new method, exactly.
     *
     * @return array As enrol/editinstance.php hands them to add_instance().
     */
    private function generic_page_fields(): array {
        return [
            'id' => 0,
            'courseid' => (int) $this->course->id,
            'type' => 'apply',
            'returnurl' => '',
        ];
    }

    /**
     * A method created with only what core's generic page posts carries every default but the status,
     * which stays core's own "enabled", as that page means for a method a teacher adds by hand.
     *
     * The status default is switched to disabled first, so the status half asserts something.
     *
     * Changes that must make it fail: add_instance() not completing the fields (gate NI1), or taking
     * the status from the defaults too (gate NI2).
     *
     * @return void
     */
    public function test_a_method_created_with_no_fields_carries_the_defaults(): void {
        global $DB;

        $this->plugin->set_config('status', ENROL_INSTANCE_DISABLED);
        $this->plugin->set_config('newenrols', 1);
        $this->plugin->set_config('maxenrolled', 7);
        $defaults = $this->plugin->get_instance_defaults();

        $id = $this->plugin->add_instance($this->course, $this->generic_page_fields());

        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(ENROL_INSTANCE_ENABLED, (int) $instance->status);
        foreach ($defaults as $field => $value) {
            if ($field === 'status') {
                continue;
            }
            $this->assertNotNull($instance->{$field}, $field);
            $this->assertEquals($value, $instance->{$field}, $field);
        }
        $this->assertSame(7, (int) $instance->customint3, 'the configured default, not a literal');
        $this->assertSame(1, (int) $instance->customint6);
        $this->assertTrue($this->plugin->allow_apply($instance, (int) $this->getDataGenerator()->create_user()->id));
    }

    /**
     * Fields the caller gives are kept, the status included; only the missing ones are completed.
     *
     * The control for the test above: the completion must never overwrite what edit.php, a restore or
     * add_default_instance() passes.
     *
     * @return void
     */
    public function test_the_fields_given_are_kept(): void {
        global $DB;

        $this->plugin->set_config('maxenrolled', 7);
        $id = $this->plugin->add_instance($this->course, [
            'status' => ENROL_INSTANCE_DISABLED,
            'customint3' => 3,
            'customchar1' => \enrol_apply\local\groupkey::MODE_REQUIRED,
            'customtext2' => 'Why this course?',
        ]);

        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(ENROL_INSTANCE_DISABLED, (int) $instance->status);
        $this->assertSame(3, (int) $instance->customint3);
        $this->assertSame(\enrol_apply\local\groupkey::MODE_REQUIRED, $instance->customchar1);
        $this->assertSame('Why this course?', $instance->customtext2);
        $this->assertSame(
            $this->plugin->get_instance_defaults()['customtext4'],
            $instance->customtext4,
            'a field not given is still completed'
        );
    }
}
