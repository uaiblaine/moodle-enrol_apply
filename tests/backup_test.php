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
 * Tests for backing up and restoring the plugin's own data.
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

/**
 * Tests for backing up and restoring the plugin's own data.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_enrol_apply_plugin
 * @covers     \restore_enrol_apply_plugin
 */
final class backup_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin under test. */
    protected $plugin;

    /**
     * Enable the plugin and load the backup libraries the controllers need.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->plugin = enrol_get_plugin('apply');
    }

    /**
     * Back a course up and restore it into a brand new one.
     *
     * @param \stdClass $course Course to copy.
     * @param bool $userdata Whether to include users in the backup.
     * @param bool $crosssite Whether to move the site identifier first, so the restore reads as cross-site.
     * @return int Id of the restored course.
     */
    protected function backup_and_restore($course, bool $userdata, bool $crosssite = false): int {
        global $CFG, $USER;

        $CFG->backup_file_logger_level = backup::LOG_NONE;

        /* MODE_SAMESITE, not MODE_IMPORT: backup_course_task skips
           backup_enrolments_structure_step outside a real backup ("prevent it in any
           IMPORT/HUB operation"), so an import-mode copy carries no enrol data at all —
           not even a manual method — and there is nothing here to test. This mirrors
           backup/moodle2/tests/moodle2_test.php::prepare_for_enrolments_test(). */
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
        $backupbasepath = $bc->get_plan()->get_basepath();
        $bc->execute_plan();
        $results = $bc->get_results();
        $bc->destroy();

        /* A real backup is zipped, and the restore controller can only build a plan from
           an extracted one — without this it returns null from get_plan(). */
        if (!file_exists($backupbasepath . '/moodle_backup.xml')) {
            $results['backup_destination']->extract_to_pathname(
                get_file_packer('application/vnd.moodle.backup'),
                $backupbasepath
            );
        }

        /* restore_controller works out whether the backup came from this site by comparing
           md5(get_site_identifier()) against the hash the backup recorded, and it does so
           while loading the plan in its constructor. Moving the identifier here is
           therefore the whole of "restore this into another site": nothing else about the
           archive changes, which is exactly the situation restore_instance() degrades for. */
        if ($crosssite) {
            set_config('siteidentifier', 'another-site-entirely');
        }

        $newcourseid = restore_dbops::create_new_course(
            $course->fullname,
            $course->shortname . '_copy',
            $course->category
        );
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        /* Default is ENROL_WITHUSERS, under which a restore without users converts every
           enrol instance into a manual one (restore_stepslib.php, process_enrol), so the
           apply instance would not exist in the copy. */
        $rc->get_plan()->get_setting('enrolments')->set_value(backup::ENROL_ALWAYS);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * Set up a course with an apply instance, one configured group and one application.
     *
     * @return array Course record, instance record and applicant user record.
     */
    protected function create_course_with_application(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instanceid = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $instanceid, 'groupid' => $group->id]);

        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($instance, $user->id, $instance->roleid, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = $DB->get_field('user_enrolments', 'id', ['enrolid' => $instanceid, 'userid' => $user->id], MUST_EXIST);
        $DB->insert_record('enrol_apply_applicationinfo', (object) [
            'userenrolmentid' => $ueid,
            'comment' => 'I would like to join this course',
        ]);

        return [$course, $instance, $user];
    }

    /**
     * The restored instance keeps its group mapping, remapped to the new group.
     *
     * @return void
     */
    public function test_group_mapping_survives_a_restore(): void {
        global $DB;

        [$course] = $this->create_course_with_application();

        $newcourseid = $this->backup_and_restore($course, false);

        $newinstanceid = $DB->get_field('enrol', 'id', ['courseid' => $newcourseid, 'enrol' => 'apply'], MUST_EXIST);
        $mapped = $DB->get_fieldset_select('enrol_apply_groups', 'groupid', 'enrolid = ?', [$newinstanceid]);
        $this->assertCount(1, $mapped);

        // The mapping must point at the copied course's group, not the original one.
        $newgroupid = $DB->get_field('groups', 'id', ['courseid' => $newcourseid], IGNORE_MULTIPLE);
        $this->assertEquals((int) $newgroupid, (int) reset($mapped));
    }

    /**
     * A course copy including users carries the pending application and its comment.
     *
     * @return void
     */
    public function test_application_comment_survives_a_restore_with_users(): void {
        global $DB;

        [$course, , $user] = $this->create_course_with_application();

        $newcourseid = $this->backup_and_restore($course, true);

        $newinstanceid = $DB->get_field('enrol', 'id', ['courseid' => $newcourseid, 'enrol' => 'apply'], MUST_EXIST);
        $newueid = $DB->get_field('user_enrolments', 'id', ['enrolid' => $newinstanceid, 'userid' => $user->id]);
        $this->assertNotEmpty($newueid, 'core should have restored the pending enrolment');
        $this->assertEquals(
            ENROL_USER_SUSPENDED,
            (int) $DB->get_field('user_enrolments', 'status', ['id' => $newueid])
        );

        $comment = $DB->get_field('enrol_apply_applicationinfo', 'comment', ['userenrolmentid' => $newueid]);
        $this->assertEquals('I would like to join this course', $comment);
    }

    /**
     * A cohort restriction restored into another site becomes a live refusal, not "no restriction".
     *
     * A cohort id names a different group of people on every other site, so it cannot be
     * carried across. Degrading it to zero would quietly open a course the backup had
     * closed; the sentinel keeps it closed until somebody re-picks a local cohort.
     *
     * @return void
     */
    public function test_restore_into_another_site_disables_the_cohort_restriction(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application();
        $cohort = $this->getDataGenerator()->create_cohort();
        $DB->set_field('enrol', 'customint5', $cohort->id, ['id' => $instance->id]);

        $newcourseid = $this->backup_and_restore($course, false, true);

        $restored = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'apply'], '*', MUST_EXIST);
        $this->assertEquals(-1, (int) $restored->customint5);
    }

    /**
     * A cohort restriction restored on the same site is kept exactly as it was.
     *
     * The control for the test above: without it, a restore_instance() that zeroed the
     * column unconditionally would still look correct on one half of the behaviour.
     *
     * @return void
     */
    public function test_restore_on_the_same_site_keeps_the_cohort_restriction(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application();
        $cohort = $this->getDataGenerator()->create_cohort();
        $DB->set_field('enrol', 'customint5', $cohort->id, ['id' => $instance->id]);

        $newcourseid = $this->backup_and_restore($course, false);

        $restored = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'apply'], '*', MUST_EXIST);
        $this->assertEquals((int) $cohort->id, (int) $restored->customint5);
    }

    /**
     * The application window travels with the course, because core backs both columns up itself.
     *
     * @return void
     */
    public function test_the_application_window_survives_a_restore(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application();
        $opens = time() + DAYSECS;
        $closes = time() + (2 * DAYSECS);
        $DB->set_field('enrol', 'enrolstartdate', $opens, ['id' => $instance->id]);
        $DB->set_field('enrol', 'enrolenddate', $closes, ['id' => $instance->id]);

        $newcourseid = $this->backup_and_restore($course, false);

        $restored = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'apply'], '*', MUST_EXIST);
        $this->assertEquals($opens, (int) $restored->enrolstartdate);
        $this->assertEquals($closes, (int) $restored->enrolenddate);
    }

    /**
     * A copy without users carries no application data at all.
     *
     * The comment is personal data, so it must follow the users setting.
     *
     * @return void
     */
    public function test_application_comment_is_absent_without_users(): void {
        global $DB;

        [$course] = $this->create_course_with_application();
        $before = $DB->count_records('enrol_apply_applicationinfo');

        $newcourseid = $this->backup_and_restore($course, false);

        $newinstanceid = $DB->get_field('enrol', 'id', ['courseid' => $newcourseid, 'enrol' => 'apply'], MUST_EXIST);
        $this->assertEquals(0, $DB->count_records('user_enrolments', ['enrolid' => $newinstanceid]));
        $this->assertEquals($before, $DB->count_records('enrol_apply_applicationinfo'));
    }
}
