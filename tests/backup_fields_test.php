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
 * Tests for backing up and restoring the application form's field definitions.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use backup;
use backup_controller;
use backup_setting;
use restore_controller;
use restore_dbops;
use core_customfield\api;
use enrol_apply\customfield\application_handler;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/enrol/apply/backup/moodle2/backup_enrol_apply_plugin.class.php');
require_once($CFG->dirroot . '/enrol/apply/backup/moodle2/restore_enrol_apply_plugin.class.php');

/**
 * Tests for the backup and restore of the application field definitions.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\backup_enrol_apply_plugin::class)]
#[CoversClass(\restore_enrol_apply_plugin::class)]
final class backup_fields_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin under test. */
    protected $plugin;

    /**
     * Enable the plugin.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->plugin = enrol_get_plugin('apply');
    }

    /**
     * Back a course up (same site) and restore it into a new course.
     *
     * Mirrors backup_test::backup_and_restore(); MODE_SAMESITE plus an explicit unzip because
     * other modes produce no enrolments.xml.
     *
     * @param \stdClass $course Course to copy.
     * @param bool $userdata Whether users are included.
     * @param int $enrolments One of the backup::ENROL_* constants for the restore.
     * @param callable|null $afterbackup Called between the backup and the restore, to change the site.
     * @return int Id of the restored course.
     */
    protected function backup_and_restore(
        $course,
        bool $userdata,
        int $enrolments = backup::ENROL_ALWAYS,
        ?callable $afterbackup = null
    ): int {
        global $CFG, $USER;

        $CFG->backup_file_logger_level = backup::LOG_NONE;
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_SAMESITE,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value($userdata);
        $backupid = $bc->get_backupid();
        $basepath = $bc->get_plan()->get_basepath();
        $bc->execute_plan();
        $results = $bc->get_results();
        $bc->destroy();
        if (!file_exists($basepath . '/moodle_backup.xml')) {
            $results['backup_destination']->extract_to_pathname(
                get_file_packer('application/vnd.moodle.backup'),
                $basepath
            );
        }

        if ($afterbackup !== null) {
            $afterbackup();
        }

        $newcourseid = restore_dbops::create_new_course($course->fullname, $course->shortname . '_copy', $course->category);
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('enrolments')->set_value($enrolments);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * A course whose apply instance has two categories, an ordered set of fields and a shared category.
     *
     * @return array Course, instance id, the instance's first category, and a site shared category.
     */
    protected function create_source(): array {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $course = $this->getDataGenerator()->create_course();
        $enrolid = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());

        $first = $generator->create_category([
            'name' => 'About you', 'component' => 'enrol_apply', 'area' => 'application', 'itemid' => $enrolid,
        ]);
        $generator->create_category([
            'name' => 'Extra', 'component' => 'enrol_apply', 'area' => 'application', 'itemid' => $enrolid,
        ]);
        $generator->create_field(['categoryid' => $first->get('id'), 'type' => 'text', 'shortname' => 'city', 'name' => 'City']);
        $generator->create_field([
            'categoryid' => $first->get('id'),
            'type' => 'select',
            'shortname' => 'level',
            'name' => 'Level',
            'configdata' => ['options' => "Basic\nAdvanced", 'defaultvalue' => 'Basic'],
        ]);

        $shared = $generator->create_category([
            'name' => 'Site wide', 'component' => 'core_customfield', 'area' => 'shared', 'itemid' => 0,
        ]);

        return [$course, $enrolid, $first, $shared];
    }

    /**
     * Enable a shared category on an instance.
     *
     * @param int $categoryid Shared category id.
     * @param int $enrolid Enrol instance id.
     * @return void
     */
    protected function enable_shared(int $categoryid, int $enrolid): void {
        (new \core_customfield\shared(0, (object) [
            'categoryid' => $categoryid, 'component' => 'enrol_apply', 'area' => 'application', 'itemid' => $enrolid,
        ]))->create();
    }

    /**
     * The id of the apply instance of a course.
     *
     * @param int $courseid Course id.
     * @return int
     */
    protected function apply_instance(int $courseid): int {
        global $DB;
        return (int) $DB->get_field('enrol', 'id', ['courseid' => $courseid, 'enrol' => 'apply'], MUST_EXIST);
    }

    /**
     * Categories of an instance with their fields, as name => [shortname => [type, name, configdata]].
     *
     * @param int $enrolid Enrol instance id.
     * @return array
     */
    protected function definitions(int $enrolid): array {
        global $DB;

        $out = [];
        $categories = $DB->get_records('customfield_category', [
            'component' => 'enrol_apply', 'area' => 'application', 'itemid' => $enrolid,
        ], 'sortorder, id');
        foreach ($categories as $category) {
            $out[$category->name] = [];
            foreach ($DB->get_records('customfield_field', ['categoryid' => $category->id], 'sortorder, id') as $field) {
                $out[$category->name][$field->shortname] = [$field->type, $field->name, json_decode($field->configdata, true)];
            }
        }

        return $out;
    }

    /**
     * Definitions come back under the new instance and the original ones are untouched.
     *
     * @return void
     */
    public function test_a_restore_recreates_the_definitions_under_the_new_instance(): void {
        [$course, $enrolid] = $this->create_source();
        $before = $this->definitions($enrolid);
        $this->assertCount(2, $before);

        $newcourseid = $this->backup_and_restore($course, true);
        $newenrolid = $this->apply_instance($newcourseid);

        $this->assertNotEquals($enrolid, $newenrolid);
        $restored = $this->definitions($newenrolid);
        $this->assertSame(['About you', 'Extra'], array_keys($restored));
        $this->assertSame(['city', 'level'], array_keys($restored['About you']));
        $this->assertSame('text', $restored['About you']['city'][0]);
        $this->assertSame('Level', $restored['About you']['level'][1]);
        $this->assertSame('Advanced', explode("\n", $restored['About you']['level'][2]['options'])[1]);
        $this->assertEquals($before, $this->definitions($enrolid), 'the source instance is untouched');
    }

    /**
     * Definitions are configuration: they travel with users excluded.
     *
     * @return void
     */
    public function test_definitions_travel_without_users(): void {
        [$course, $enrolid] = $this->create_source();

        $newcourseid = $this->backup_and_restore($course, false);

        $this->assertEquals($this->definitions($enrolid), $this->definitions($this->apply_instance($newcourseid)));
    }

    /**
     * An enabled shared category is enabled on the restored instance; a disabled one is not.
     *
     * @return void
     */
    public function test_the_shared_category_switch_travels(): void {
        global $DB;

        [$course, $enrolid, , $shared] = $this->create_source();
        $other = $this->getDataGenerator()->get_plugin_generator('core_customfield')->create_category([
            'name' => 'Not enabled', 'component' => 'core_customfield', 'area' => 'shared', 'itemid' => 0,
        ]);
        $this->enable_shared((int) $shared->get('id'), $enrolid);

        $newenrolid = $this->apply_instance($this->backup_and_restore($course, true));

        $this->assertTrue(api::is_shared_category_enabled((int) $shared->get('id'), 'enrol_apply', 'application', $newenrolid));
        $this->assertFalse(api::is_shared_category_enabled((int) $other->get('id'), 'enrol_apply', 'application', $newenrolid));
        $this->assertEquals(1, $DB->count_records('customfield_shared', ['itemid' => $newenrolid]));
    }

    /**
     * A shared category the site no longer has under that name is skipped, never created.
     *
     * @return void
     */
    public function test_a_missing_shared_category_is_skipped_not_created(): void {
        global $DB;

        [$course, $enrolid, , $shared] = $this->create_source();
        $this->enable_shared((int) $shared->get('id'), $enrolid);
        $sharedcount = $DB->count_records('customfield_category', ['component' => 'core_customfield', 'area' => 'shared']);

        $rename = function () use ($DB, $shared): void {
            $DB->set_field('customfield_category', 'name', 'Renamed', ['id' => $shared->get('id')]);
        };
        $newenrolid = $this->apply_instance($this->backup_and_restore($course, true, backup::ENROL_ALWAYS, $rename));

        $this->assertFalse(api::is_shared_category_enabled((int) $shared->get('id'), 'enrol_apply', 'application', $newenrolid));
        $this->assertEquals(0, $DB->count_records('customfield_shared', ['itemid' => $newenrolid, 'component' => 'enrol_apply']));
        $this->assertEquals(
            $sharedcount,
            $DB->count_records('customfield_category', ['component' => 'core_customfield', 'area' => 'shared'])
        );
    }

    /**
     * A field of a type the handler does not offer is not restored; its allowed sibling is.
     *
     * @return void
     */
    public function test_a_type_outside_the_allowed_list_is_not_restored(): void {
        global $DB;

        [$course, $enrolid, $first] = $this->create_source();
        $this->getDataGenerator()->get_plugin_generator('core_customfield')->create_field([
            'categoryid' => $first->get('id'), 'type' => 'textarea', 'shortname' => 'bio', 'name' => 'Bio',
        ]);
        $this->assertTrue($DB->record_exists('customfield_field', ['categoryid' => $first->get('id'), 'type' => 'textarea']));

        $newenrolid = $this->apply_instance($this->backup_and_restore($course, true));

        $restored = $this->definitions($newenrolid);
        $this->assertArrayNotHasKey('bio', $restored['About you']);
        $this->assertArrayHasKey('city', $restored['About you']);
    }

    /**
     * Nothing is written against the manual instance a never-enrolments restore maps onto.
     *
     * @return void
     */
    public function test_nothing_is_restored_onto_another_enrolment_method(): void {
        global $DB;

        [$course, $enrolid, , $shared] = $this->create_source();
        $this->enable_shared((int) $shared->get('id'), $enrolid);
        $categories = $DB->count_records('customfield_category', ['component' => 'enrol_apply']);
        $switches = $DB->count_records('customfield_shared', ['component' => 'enrol_apply']);

        $newcourseid = $this->backup_and_restore($course, true, backup::ENROL_NEVER);

        $this->assertFalse($DB->record_exists('enrol', ['courseid' => $newcourseid, 'enrol' => 'apply']));
        $this->assertEquals($categories, $DB->count_records('customfield_category', ['component' => 'enrol_apply']));
        $this->assertEquals($switches, $DB->count_records('customfield_shared', ['component' => 'enrol_apply']));
    }
}
