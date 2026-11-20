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
 * Tests for the groups a method offers its applicants.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(groupoffer::class)]
final class groupoffer_test extends \advanced_testcase {
    /** @var \stdClass The method's course. */
    private $course;

    /** @var int An apply instance on that course. */
    private $enrolid;

    /**
     * An apply instance on a fresh course.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $this->enrolid = (int) $plugin->add_instance($this->course, $plugin->get_instance_defaults());
    }

    /**
     * Setting the offer replaces it whole, once per group, and reads back ascending.
     *
     * @return void
     */
    public function test_set_replaces_the_offer(): void {
        $a = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $b = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $c = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);

        groupoffer::set($this->enrolid, [(int) $b->id, (string) $a->id, (int) $b->id]);
        $this->assertSame([(int) $a->id, (int) $b->id], groupoffer::group_ids($this->enrolid));

        groupoffer::set($this->enrolid, [(int) $c->id]);
        $this->assertSame([(int) $c->id], groupoffer::group_ids($this->enrolid));

        groupoffer::set($this->enrolid, []);
        $this->assertSame([], groupoffer::group_ids($this->enrolid));
    }

    /**
     * A group of another course is refused, and the offer is left as it was.
     *
     * Changes that must make it fail: dropping the course check in set(), which would let a
     * posted id offer, and so name, a group of a course the teacher cannot see.
     *
     * @return void
     */
    public function test_set_refuses_a_group_of_another_course(): void {
        $mine = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $elsewhere = $this->getDataGenerator()->create_course();
        $foreign = $this->getDataGenerator()->create_group(['courseid' => $elsewhere->id]);
        groupoffer::set($this->enrolid, [(int) $mine->id]);

        try {
            groupoffer::set($this->enrolid, [(int) $mine->id, (int) $foreign->id]);
            $this->fail('A group of another course was offered.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertSame([(int) $mine->id], groupoffer::group_ids($this->enrolid));
        }
    }

    /**
     * Ids that are not positive integers are refused before anything is read or written.
     *
     * @return void
     */
    public function test_set_refuses_what_is_not_a_group_id(): void {
        foreach (['3 OR 1=1', -4, 0, 1.5, true, null] as $value) {
            try {
                groupoffer::set($this->enrolid, [$value]);
                $this->fail('A value that is not a group id was accepted: ' . var_export($value, true));
            } catch (\invalid_parameter_exception $e) {
                $this->assertSame([], groupoffer::group_ids($this->enrolid));
            }
        }
    }

    /**
     * The read joins the group on the method's own course, and never returns the enrolment key.
     *
     * Changes that must make it fail: dropping the course clause of the join, or selecting g.*.
     *
     * @return void
     */
    public function test_the_read_offers_only_groups_of_the_course_and_no_key(): void {
        global $DB;

        $mine = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'enrolmentkey' => 'Secret-1']);
        $elsewhere = $this->getDataGenerator()->create_course();
        $foreign = $this->getDataGenerator()->create_group(['courseid' => $elsewhere->id]);
        groupoffer::set($this->enrolid, [(int) $mine->id]);
        // Only a hand edit writes a row like this one.
        $DB->insert_record('enrol_apply_offer', (object) ['enrolid' => $this->enrolid, 'groupid' => $foreign->id]);

        $groups = groupoffer::groups($this->enrolid);

        $this->assertSame([(int) $mine->id], array_map('intval', array_keys($groups)));
        $this->assertFalse(property_exists($groups[$mine->id], 'enrolmentkey'));
        $this->assertSame($mine->name, $groups[$mine->id]->name);
    }

    /**
     * A deleted group drops out of the read without any clean-up.
     *
     * @return void
     */
    public function test_a_deleted_group_is_no_longer_offered(): void {
        $kept = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $gone = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        groupoffer::set($this->enrolid, [(int) $kept->id, (int) $gone->id]);

        groups_delete_group($gone->id);

        $this->assertSame([(int) $kept->id], groupoffer::group_ids($this->enrolid));
    }

    /**
     * Adding one group, as the restore does, checks the course and does not duplicate.
     *
     * @return void
     */
    public function test_add_checks_the_course_and_does_not_duplicate(): void {
        global $DB;

        $mine = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $foreign = $this->getDataGenerator()->create_group(['courseid' => $this->getDataGenerator()->create_course()->id]);

        $this->assertTrue(groupoffer::add($this->enrolid, (int) $mine->id));
        $this->assertTrue(groupoffer::add($this->enrolid, (int) $mine->id));
        $this->assertFalse(groupoffer::add($this->enrolid, (int) $foreign->id));

        $this->assertSame(1, $DB->count_records('enrol_apply_offer', ['enrolid' => $this->enrolid]));
    }

    /**
     * Every write refuses an instance of another enrolment method.
     *
     * @return void
     */
    public function test_the_writes_refuse_an_instance_of_another_method(): void {
        global $DB;

        $manualid = (int) $DB->get_field('enrol', 'id', ['enrol' => 'manual', 'courseid' => $this->course->id], MUST_EXIST);
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);

        $writes = [
            fn() => groupoffer::set($manualid, [(int) $group->id]),
            fn() => groupoffer::add($manualid, (int) $group->id),
        ];
        foreach ($writes as $write) {
            try {
                $write();
                $this->fail('An instance of another method was written to.');
            } catch (\coding_exception $e) {
                $this->assertFalse($DB->record_exists('enrol_apply_offer', ['enrolid' => $manualid]));
            }
        }
    }

    /**
     * The offer is never what an approval joins: offering a group writes nothing the joined
     * groups' readers see.
     *
     * @return void
     */
    public function test_an_offered_group_is_not_a_joined_group(): void {
        global $DB;

        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        groupoffer::set($this->enrolid, [(int) $group->id]);

        $this->assertFalse($DB->record_exists('enrol_apply_groups', ['enrolid' => $this->enrolid]));
        $instance = $DB->get_record('enrol', ['id' => $this->enrolid], '*', MUST_EXIST);
        $this->assertSame([], enrol_get_plugin('apply')->default_approval_groups($instance, 0));
    }

    /**
     * Before the upgrade has run nothing is offered, nothing is deleted and a write throws.
     *
     * Changes that must make it fail: any of the gates dropped.
     *
     * @return void
     */
    public function test_before_the_upgrade_the_offer_is_neither_read_nor_written(): void {
        global $DB;

        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        groupoffer::set($this->enrolid, [(int) $group->id]);
        set_config('version', ENROL_APPLY_OPTIONS_SCHEMA - 1, 'enrol_apply');

        $this->assertSame([], groupoffer::groups($this->enrolid));
        groupoffer::delete_for_instance($this->enrolid);
        $this->assertTrue($DB->record_exists('enrol_apply_offer', ['enrolid' => $this->enrolid]));
        $writes = [
            fn() => groupoffer::set($this->enrolid, []),
            fn() => groupoffer::add($this->enrolid, (int) $group->id),
        ];
        foreach ($writes as $write) {
            try {
                $write();
                $this->fail('A write went ahead before the upgrade.');
            } catch (\coding_exception $e) {
                $this->assertSame(1, $DB->count_records('enrol_apply_offer', ['enrolid' => $this->enrolid]));
            }
        }

        // The control: once the stored version reaches the step, the same row is offered.
        set_config('version', ENROL_APPLY_OPTIONS_SCHEMA, 'enrol_apply');
        $this->assertSame([(int) $group->id], groupoffer::group_ids($this->enrolid));
    }
}
