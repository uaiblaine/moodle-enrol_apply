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
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/* The two classes this file declares as its coverage targets are NOT autoloadable: core loads
   backup/moodle2/*.class.php by path, from the backup and restore machinery, only when a run
   actually reaches an enrol_apply element. PHPUnit resolves a CoversClass target per test, so
   before this require the target resolved only once some earlier test had happened to perform a
   restore - and every test running before that one reported
   "restore_enrol_apply_plugin" is not a valid target for code coverage.

   Measured: exactly the four tests that never restore warned, because they are the four that run
   before the first restoring test, so which tests warn was decided by execution order alone. Four
   PHPUnit warnings are enough to fail the run under coverage, which is why this plugin had no
   coverage number at all - and nothing in ordinary CI sees it, because the workflow passes
   coverage: none and never resolves a target.

   The includes have to come first and in this order: both plugin classes extend core backup
   classes that these files are what load. setUp() already requires the same two includes, which
   is why the classes resolve at all once a restore has run. */
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/enrol/apply/backup/moodle2/backup_enrol_apply_plugin.class.php');
require_once($CFG->dirroot . '/enrol/apply/backup/moodle2/restore_enrol_apply_plugin.class.php');

/**
 * Tests for backing up and restoring the plugin's own data.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\backup_enrol_apply_plugin::class)]
#[CoversClass(\restore_enrol_apply_plugin::class)]
final class backup_test extends \advanced_testcase {
    /** @var int Fixed submission timestamp, so a restored row can be matched to its original. */
    protected const SUBMITTED_AT = 1750000000;

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
     * @param bool|null $restoreusers Users setting on the RESTORE side, null to match the backup.
     * @param int $enrolments One of the backup::ENROL_* constants for the restore.
     * @return int Id of the restored course.
     */
    protected function backup_and_restore(
        $course,
        bool $userdata,
        bool $crosssite = false,
        ?bool $restoreusers = null,
        int $enrolments = backup::ENROL_ALWAYS
    ): int {
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
        /* Without ENROL_ALWAYS a users-excluded restore does not merely degrade: restore_course_task
           does not schedule the enrolments step at all (its default drops to ENROL_NEVER when users
           cannot be restored), so no enrol instance of any kind would come across and the assertions
           below would throw rather than fail. */
        $rc->get_plan()->get_setting('enrolments')->set_value($enrolments);
        if ($restoreusers !== null) {
            $rc->get_plan()->get_setting('users')->set_value($restoreusers);
        }
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * Back a course up and return the enrolments.xml it produced.
     *
     * A restore cannot see the backup-side users gate at all: with users left out there is no
     * user mapping either, so the plugin's restore handler drops the record whatever the
     * backup did. The gate exists to keep personal data OUT OF THE ARCHIVE FILE, and the
     * archive is therefore the only place it can be observed.
     *
     * @param \stdClass $course Course to back up.
     * @param bool $userdata Whether to include users.
     * @return string Contents of course/enrolments.xml.
     */
    protected function backup_enrolments_xml($course, bool $userdata): string {
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

        $xml = $basepath . '/course/enrolments.xml';
        $this->assertFileExists($xml, 'MODE_SAMESITE must produce an enrolments file');

        return file_get_contents($xml);
    }

    /**
     * The classes this file declares as coverage targets are loaded before any test runs.
     *
     * Deliberately the FIRST test in the file, and deliberately `class_exists(..., false)`: the
     * point is that the two classes are already in memory without anything having autoloaded or
     * restored them, which is what PHPUnit needs in order to resolve a CoversClass target. They
     * are not autoloadable - core loads backup/moodle2/*.class.php by path from the backup and
     * restore machinery - so before the file-scope requires above, the target resolved only from
     * whichever test first performed a restore, and every test before that one warned.
     *
     * What this test can and cannot hold is worth stating. Deleting those requires reddens it,
     * measured. It holds because no other test file in this plugin loads either class - also
     * measured - so nothing earlier in a full-suite run can satisfy it by accident. It would stop
     * holding under a randomised test order, which is why the fix is a require rather than a
     * convention about ordering; this test guards the require, not the ordering.
     *
     * @return void
     */
    public function test_the_declared_coverage_targets_are_loaded(): void {
        $this->assertTrue(
            class_exists(\backup_enrol_apply_plugin::class, false),
            'the CoversClass target is unresolvable, which fails the run under --coverage'
        );
        $this->assertTrue(
            class_exists(\restore_enrol_apply_plugin::class, false),
            'the CoversClass target is unresolvable, which fails the run under --coverage'
        );
    }

    /**
     * The archive carries the durable record only when the backup includes users.
     *
     * The gate is on the backup side and nothing downstream can stand in for it: restoring a
     * users-excluded archive drops the record because no user mapping exists, so a
     * restore-based test passes with the gate deleted. This one reads the file.
     *
     * @return void
     */
    public function test_the_audit_trail_is_only_written_to_the_archive_with_users(): void {
        [$course] = $this->create_course_with_application();

        $withusers = $this->backup_enrolments_xml($course, true);
        // The control: the record really is written when it should be, so absence means something.
        $this->assertStringContainsString('<submission id=', $withusers);
        $this->assertStringContainsString('I would like to join this course', $withusers);

        $withoutusers = $this->backup_enrolments_xml($course, false);
        $this->assertStringNotContainsString('<submission id=', $withoutusers);
        $this->assertStringNotContainsString('I would like to join this course', $withoutusers);

        /* The second control: instance configuration is NOT gated and still travels, so the
           assertions above are about the gate rather than about an empty backup. */
        $this->assertStringContainsString('<applygroup id=', $withoutusers);
    }

    /**
     * Back a course up as a COPY that keeps only the given roles, and return its enrolments.xml.
     *
     * MODE_COPY is not a stylistic choice: backup_controller::set_kept_roles() throws
     * cannot_set_keep_roles_wrong_mode in any other mode, so a copy controller is the only way
     * to reach the branch under test. The roles reach the plan at execute_plan() time, just
     * before the plan runs, which is why they are readable from define_structure().
     *
     * @param \stdClass $course Course to copy.
     * @param array $keptroles Role ids whose holders' enrolments the copy keeps.
     * @param bool $userdata Value of the users setting, as the copy task would set it.
     * @return string Contents of course/enrolments.xml.
     */
    protected function copy_enrolments_xml($course, array $keptroles, bool $userdata): string {
        global $CFG, $USER;

        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_COPY,
            $USER->id
        );
        $bc->set_kept_roles($keptroles);
        $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value($userdata);

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

        $xml = $basepath . '/course/enrolments.xml';
        $this->assertFileExists($xml, 'a copy must still produce an enrolments file');

        return file_get_contents($xml);
    }

    /**
     * Seed a second applicant holding a role the copy will not keep.
     *
     * @param \stdClass $course Course to apply to.
     * @param \stdClass $instance Apply instance to apply to.
     * The pending comment and the durable record get DIFFERENT markers, suffixed rather than
     * shared, so that an assertion can tell which of the two elements it is looking at. Both
     * are gated separately and a test that could not distinguish them would hold only one.
     *
     * @param string $comment Marker to submit, suffixed per table.
     * @param string $roleshortname Role to assign.
     * @param \stdClass|null $rolecourse Course to hold the role in, null for the applied-to course.
     * @return \stdClass The applicant.
     */
    protected function add_applicant(
        $course,
        $instance,
        string $comment,
        string $roleshortname,
        ?\stdClass $rolecourse = null
    ): \stdClass {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $user->id,
            ($rolecourse ?? $course)->id,
            $DB->get_field('role', 'id', ['shortname' => $roleshortname], MUST_EXIST)
        );
        if ($rolecourse) {
            // Still an applicant here, just without the kept role in THIS course.
            $this->getDataGenerator()->enrol_user(
                $user->id,
                $course->id,
                $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST)
            );
        }

        // No role, mirroring apply(): the role is assigned on approval, not on application.
        $this->plugin->enrol_user($instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = $DB->get_field('user_enrolments', 'id', ['enrolid' => $instance->id, 'userid' => $user->id], MUST_EXIST);
        $DB->insert_record('enrol_apply_applicationinfo', (object) [
            'userenrolmentid' => $ueid,
            'comment' => $comment . 'PENDING',
        ]);
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $course->id,
            'userid' => $user->id,
            'enrolid' => $instance->id,
            'userenrolmentid' => $ueid,
            'comment' => $comment . 'RECORD',
            'userinfodata' => '',
            'status' => \enrol_apply\local\submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => self::SUBMITTED_AT,
            'timedecided' => 0,
            'decidedby' => 0,
        ]);

        return $user;
    }

    /**
     * A course copy that keeps roles carries only the kept-role users' application data.
     *
     * The users setting is 1 for this copy - the copy task sets it whenever roles are kept and
     * user data is wanted - so a gate reading that setting alone writes every applicant's
     * comment and profile snapshot into the archive, including those of the people the copy
     * exists to leave out.
     *
     * This reads the archive; test_an_excluded_applicant_does_not_reach_the_copied_course
     * asserts the same gate against the copied course's database, which is where those rows
     * were actually landing.
     *
     * @return void
     */
    public function test_a_kept_roles_copy_carries_only_the_kept_users_data(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application();
        $this->add_applicant($course, $instance, 'KEPTROLEAPPLICANT', 'editingteacher');
        $this->add_applicant($course, $instance, 'DROPPEDROLEAPPLICANT', 'student');

        /* The third applicant separates the two halves of core's predicate. They hold the kept
           role, but in a DIFFERENT course, and are only a student here - so core writes no
           enrolment for them. Without this fixture the "ra.contextid = ?" conjunct is
           unguarded: deleting it leaves every assertion green while the plugin starts writing
           the data of anyone holding the kept role anywhere on the site. */
        $elsewhere = $this->getDataGenerator()->create_course();
        $this->add_applicant($course, $instance, 'OTHERCONTEXTAPPLICANT', 'editingteacher', $elsewhere);

        $keptroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $xml = $this->copy_enrolments_xml($course, [$keptroleid], true);

        /* The control: the kept-role applicant's data really is written, so absence means
           something. Both elements are asserted, because both are gated separately. */
        $this->assertStringContainsString('KEPTROLEAPPLICANTPENDING', $xml);
        $this->assertStringContainsString('KEPTROLEAPPLICANTRECORD', $xml);

        $this->assertStringNotContainsString('DROPPEDROLEAPPLICANTPENDING', $xml);
        $this->assertStringNotContainsString('DROPPEDROLEAPPLICANTRECORD', $xml);

        $this->assertStringNotContainsString('OTHERCONTEXTAPPLICANTPENDING', $xml);
        $this->assertStringNotContainsString('OTHERCONTEXTAPPLICANTRECORD', $xml);
    }

    /**
     * A course copy that keeps roles WITHOUT user data carries no application data at all.
     *
     * Core does write its own kept-role enrolments in this cell, and matching that was this
     * fix's first instinct - wrongly. With user data off, core forces the restore's users
     * setting off and its enrolments setting to ENROL_NEVER, so no apply instance and no user
     * enrolment reaches the destination; core re-enrols the kept-role users through the manual
     * plugin afterwards instead. Anything this plugin wrote there would be a comment and a
     * profile snapshot in an archive with nowhere to go - the same exposure the gate exists to
     * prevent, in the one cell where it buys nothing.
     *
     * The control is the cell above: with user data on, the same fixture DOES travel.
     *
     * @return void
     */
    public function test_a_kept_roles_copy_without_user_data_carries_nothing(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application();
        $this->add_applicant($course, $instance, 'KEPTROLEAPPLICANT', 'editingteacher');

        $keptroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $xml = $this->copy_enrolments_xml($course, [$keptroleid], false);

        // The precondition: core really did write its own enrolment, so the archive is not empty.
        $this->assertStringContainsString('<enrolment id=', $xml);

        $this->assertStringNotContainsString('KEPTROLEAPPLICANTPENDING', $xml);
        $this->assertStringNotContainsString('KEPTROLEAPPLICANTRECORD', $xml);
    }

    /**
     * An applicant holding two kept roles is written once, not twice.
     *
     * Core's own kept-roles query joins {role_assignments}, so a user holding two of the kept
     * roles matches twice. Nothing downstream would break, but the archive would carry the
     * same application under the same id twice.
     *
     * @return void
     */
    public function test_an_applicant_with_two_kept_roles_is_written_once(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application();
        $applicant = $this->add_applicant($course, $instance, 'TWICEHELDROLES', 'editingteacher');
        $this->getDataGenerator()->enrol_user(
            $applicant->id,
            $course->id,
            $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST)
        );

        $kept = [
            (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST),
            (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST),
        ];
        $xml = $this->copy_enrolments_xml($course, $kept, true);

        $this->assertEquals(1, substr_count($xml, 'TWICEHELDROLESPENDING'));
        $this->assertEquals(1, substr_count($xml, 'TWICEHELDROLESRECORD'));
    }

    /**
     * The excluded applicant's record does not reach the copied course's database.
     *
     * This test exists because the note that used to stand here was wrong, and wrong in the
     * direction that discourages writing it. It said the leaked rows were "dropped on restore,
     * because the user mapping misses, so the only place this is ever visible is the archive
     * file". That holds for enrol_apply_applicationinfo, which is keyed on the user-enrolment
     * mapping - and NOT for enrol_apply_submission, which is keyed on the user mapping. In a
     * kept-roles copy core's roles step annotates every course-context role assignment, so the
     * excluded applicant IS in users.xml and their user mapping DOES resolve. Measured against
     * the pre-fix code: their comment and profile snapshot were inserted into the destination
     * course's table, under a live user id, for somebody with no enrolment there at all.
     *
     * So the blast radius was a live database, not only an archive, and the assertion below -
     * which the old note argued would be vacuous - goes red without the gate.
     *
     * It drives copy_helper, not a hand-built controller pair, so the whole production path
     * runs including the manual re-enrolment core performs after a copy.
     *
     * @return void
     */
    public function test_an_excluded_applicant_does_not_reach_the_copied_course(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/backup/util/helper/copy_helper.class.php');

        $this->preventResetByRollback();
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        [$course, $instance] = $this->create_course_with_application();
        $kept = $this->add_applicant($course, $instance, 'KEPTROLEAPPLICANT', 'editingteacher');
        $excluded = $this->add_applicant($course, $instance, 'DROPPEDROLEAPPLICANT', 'student');
        $keptroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);

        $formdata = (object) [
            'courseid' => $course->id,
            'fullname' => 'Copy of the course',
            'shortname' => $course->shortname . '_copy',
            'category' => $course->category,
            'visible' => 1,
            'startdate' => $course->startdate,
            'enddate' => 0,
            'idnumber' => '',
            'userdata' => 1,
            'role_' . $keptroleid => $keptroleid,
        ];
        $result = \copy_helper::create_copy(\copy_helper::process_formdata($formdata));
        $newcourseid = (int) \restore_controller::load_controller($result['restoreid'])->get_courseid();

        $task = \core\task\manager::get_next_adhoc_task(time());
        $this->assertInstanceOf(\core\task\asynchronous_copy_task::class, $task);
        ob_start();
        $task->execute();
        ob_end_clean();
        \core\task\manager::adhoc_task_complete($task);

        $records = $DB->get_records('enrol_apply_submission', ['courseid' => $newcourseid]);
        $userids = array_map(static function (\stdClass $row): int {
            return (int) $row->userid;
        }, array_values($records));

        /* The control: the kept-role applicant's record really did make the journey, so the
           excluded one's absence is the gate working rather than the copy failing. */
        $this->assertContains((int) $kept->id, $userids);
        $this->assertNotContains((int) $excluded->id, $userids);

        foreach ($records as $record) {
            $this->assertStringNotContainsString('DROPPEDROLEAPPLICANT', (string) $record->comment);
        }
    }

    /**
     * Restore a backup of one course into another, existing course.
     *
     * @param \stdClass $course Course to back up.
     * @param int $targetid Course to restore into.
     * @return void
     */
    protected function restore_into_existing($course, int $targetid): void {
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
        $bc->get_plan()->get_setting('users')->set_value(true);
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

        $rc = new restore_controller(
            $backupid,
            $targetid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_EXISTING_ADDING
        );
        $rc->get_plan()->get_setting('enrolments')->set_value(backup::ENROL_ALWAYS);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
    }

    /**
     * Restoring the same course twice into one target does not double the trail.
     *
     * The only way the de-duplication check is reachable at all: a restore into a NEW course
     * can never collide, and a restore always creates a fresh enrol instance, so the check
     * cannot be keyed on the instance either - it would then be comparing against an id no
     * existing record can possibly carry.
     *
     * @return void
     */
    public function test_restoring_the_same_course_twice_does_not_double_the_trail(): void {
        global $DB;

        [$course, , $user] = $this->create_course_with_application();
        $target = $this->getDataGenerator()->create_course();

        $this->restore_into_existing($course, $target->id);
        $this->assertEquals(
            1,
            $DB->count_records('enrol_apply_submission', ['courseid' => $target->id]),
            'the first restore must bring the record across at all'
        );

        $this->restore_into_existing($course, $target->id);

        $this->assertEquals(1, $DB->count_records('enrol_apply_submission', ['courseid' => $target->id]));
        // The record is the right one, not merely one of two.
        $row = $DB->get_record('enrol_apply_submission', ['courseid' => $target->id], '*', MUST_EXIST);
        $this->assertEquals($user->id, (int) $row->userid);
        $this->assertEquals(self::SUBMITTED_AT, (int) $row->timecreated);
    }

    /**
     * Set up a course with an apply instance, one configured group and one application.
     *
     * @param \stdClass|null $decider User to record as having decided the application, null for none.
     * @return array Course record, instance record and applicant user record.
     */
    protected function create_course_with_application(?\stdClass $decider = null): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instanceid = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $instanceid, 'groupid' => $group->id]);

        $user = $this->getDataGenerator()->create_user();
        // No role, mirroring apply(): the role is assigned on approval, not on application.
        $this->plugin->enrol_user($instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = $DB->get_field('user_enrolments', 'id', ['enrolid' => $instanceid, 'userid' => $user->id], MUST_EXIST);
        $DB->insert_record('enrol_apply_applicationinfo', (object) [
            'userenrolmentid' => $ueid,
            'comment' => 'I would like to join this course',
        ]);

        /* The durable record of the same application, seeded rather than produced by apply()
           so that the decider and the snapshot are fixed values the assertions can name. */
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $course->id,
            'userid' => $user->id,
            'enrolid' => $instanceid,
            'userenrolmentid' => $ueid,
            'comment' => 'I would like to join this course',
            'userinfodata' => json_encode([
                'version' => \enrol_apply\local\submission::SNAPSHOT_VERSION,
                'fields' => [['key' => 's_city', 'label' => 'City', 'value' => 'Recife']],
            ]),
            'status' => \enrol_apply\local\submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => self::SUBMITTED_AT,
            'timedecided' => 0,
            'decidedby' => $decider ? $decider->id : 0,
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
     * An approved applicant's group membership survives a restore with users.
     *
     * It did not before. Group memberships are stamped with this plugin as their component so
     * core's unenrol_user() can clean them up, and core routes any component starting with
     * "enrol_" to enrol_plugin::restore_group_member() - whose base implementation is empty,
     * with no fallback and no warning on that branch. The membership simply disappeared.
     *
     * @return void
     */
    public function test_an_approved_group_membership_survives_a_restore(): void {
        global $DB;

        [$course, $instance, $user] = $this->create_course_with_application();

        $ueid = $DB->get_field('user_enrolments', 'id', ['enrolid' => $instance->id, 'userid' => $user->id], MUST_EXIST);
        $this->plugin->confirm_enrolment([$ueid]);

        $groupid = $DB->get_field('groups', 'id', ['courseid' => $course->id], IGNORE_MULTIPLE);
        $this->assertTrue($DB->record_exists('groups_members', [
            'groupid' => $groupid,
            'userid' => $user->id,
            'component' => 'enrol_apply',
        ]), 'the approval should have created a stamped membership');

        $newcourseid = $this->backup_and_restore($course, true);

        $newgroupid = $DB->get_field('groups', 'id', ['courseid' => $newcourseid], IGNORE_MULTIPLE);
        $this->assertNotEmpty($newgroupid);
        $this->assertTrue(
            $DB->record_exists('groups_members', ['groupid' => $newgroupid, 'userid' => $user->id]),
            'the restored course should carry the membership'
        );

        /* And it keeps the stamp, so unenrol_user() can still remove it by component and
           itemid - a membership restored without one is never cleaned up again. */
        $newinstanceid = $DB->get_field('enrol', 'id', ['courseid' => $newcourseid, 'enrol' => 'apply'], MUST_EXIST);
        $this->assertTrue($DB->record_exists('groups_members', [
            'groupid' => $newgroupid,
            'userid' => $user->id,
            'component' => 'enrol_apply',
            'itemid' => $newinstanceid,
        ]));
    }

    /**
     * An approved applicant's ROLE survives a restore with users, and keeps its stamp.
     *
     * It did not, between the commit that stamped the assignment and this one, and the loss was
     * completely silent. Core routes any {role_assignments} row whose component starts with
     * "enrol_" to enrol_plugin::restore_role_assignment() (restore_stepslib.php:2350, the same
     * line on 5.1 and 5.2), whose base implementation is an empty stub. Unlike the neighbouring
     * generic-component branch, that one has no role_assign() fallback and writes no
     * backup::LOG_WARNING - so a restored applicant came back with an ACTIVE enrolment and no
     * role at all, keeping their place in the course and losing every capability with it.
     *
     * THE CONTROL IS THE POINT. A second user holds a bare assignment in the same course, and
     * the assertion that theirs survives is what proves the restore processed roles.xml at all.
     * Without it this test passes just as happily against a restore that assigned nothing to
     * anybody, which is the vacuous shape the fleet rules warn about - and it is not
     * hypothetical here, because the defect being pinned IS "the roles quietly do not arrive".
     *
     * The stamp is asserted, not merely the role's existence. Restoring bare would satisfy a
     * role-exists assertion while losing the cleanup contract the stamp is written for:
     * process_expirations() would be back to guessing $instance->roleid.
     *
     * Mutation check: delete the body of enrol_apply_plugin::restore_role_assignment() and
     * exactly this test goes red, on the applicant half, with the control still green.
     *
     * @return void
     */
    public function test_an_approved_applicants_role_survives_a_restore(): void {
        global $DB;

        [$course, $instance, $user] = $this->create_course_with_application();
        $coursecontext = \context_course::instance($course->id);
        $studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

        /* The control: an ordinary manual enrolment, whose assignment carries no component and
           therefore comes back through core's own branch whatever this plugin does. */
        $control = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($control->id, $course->id, $studentroleid);

        $ueid = $DB->get_field('user_enrolments', 'id', ['enrolid' => $instance->id, 'userid' => $user->id], MUST_EXIST);
        $this->plugin->confirm_enrolment([$ueid]);

        $this->assertTrue($DB->record_exists('role_assignments', [
            'contextid' => $coursecontext->id,
            'userid' => $user->id,
            'component' => 'enrol_apply',
        ]), 'the approval should have created a stamped assignment');

        $newcourseid = $this->backup_and_restore($course, true);
        $newcontext = \context_course::instance($newcourseid);

        $this->assertTrue($DB->record_exists('role_assignments', [
            'contextid' => $newcontext->id,
            'userid' => $control->id,
        ]), 'the control proves the restore really processed the role assignments');

        $newinstanceid = (int) $DB->get_field(
            'enrol',
            'id',
            ['courseid' => $newcourseid, 'enrol' => 'apply'],
            MUST_EXIST
        );
        $this->assertTrue($DB->record_exists('role_assignments', [
            'contextid' => $newcontext->id,
            'userid' => $user->id,
            'roleid' => $studentroleid,
            'component' => 'enrol_apply',
            'itemid' => $newinstanceid,
        ]), 'the applicant keeps the role AND the stamp that lets core clean it up again');
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
        $DB->set_field('enrol', 'name', 'Restricted', ['id' => $instance->id]);

        /* A second, unrestricted instance in the same course. Without it the "there was a
           restriction" half of the guard is unpinned: dropping it would rewrite EVERY
           cross-site restore to the sentinel, so every restored course would refuse every
           application with "restricted to a cohort that does not exist on this site" having
           never been restricted at all - a worse failure than the one the sentinel prevents,
           and the whole suite would stay green. The restricted instance's -1 below is what
           proves the cross-site path really ran, so neither assertion can pass vacuously. */
        $openid = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());
        $DB->set_field('enrol', 'name', 'Unrestricted', ['id' => $openid]);

        $newcourseid = $this->backup_and_restore($course, false, true);

        $restored = $DB->get_record(
            'enrol',
            ['courseid' => $newcourseid, 'enrol' => 'apply', 'name' => 'Restricted'],
            '*',
            MUST_EXIST
        );
        $this->assertEquals(-1, (int) $restored->customint5);

        $untouched = $DB->get_record(
            'enrol',
            ['courseid' => $newcourseid, 'enrol' => 'apply', 'name' => 'Unrestricted'],
            '*',
            MUST_EXIST
        );
        $this->assertEquals(0, (int) $untouched->customint5);
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

    /**
     * A copy including users carries the durable application record, decider and all.
     *
     * @return void
     */
    public function test_the_audit_trail_travels_when_users_are_included(): void {
        global $DB;

        $decider = $this->getDataGenerator()->create_user();
        [$course, , $user] = $this->create_course_with_application($decider);

        $newcourseid = $this->backup_and_restore($course, true);

        $newinstanceid = $DB->get_field('enrol', 'id', ['courseid' => $newcourseid, 'enrol' => 'apply'], MUST_EXIST);
        $row = $DB->get_record('enrol_apply_submission', ['courseid' => $newcourseid], '*', MUST_EXIST);

        $this->assertEquals($user->id, (int) $row->userid);
        $this->assertEquals($decider->id, (int) $row->decidedby);
        $this->assertEquals($newinstanceid, (int) $row->enrolid);
        $this->assertEquals(self::SUBMITTED_AT, (int) $row->timecreated);
        $this->assertSame('I would like to join this course', (string) $row->comment);

        // The snapshot travels whole: it is the answer as given, not a reference to resolve later.
        $snapshot = \enrol_apply\local\submission::read_snapshot($row->userinfodata);
        $this->assertCount(1, $snapshot);
        $this->assertSame('Recife', $snapshot[0]['value']);

        /* The reference is remapped rather than carried, so it names the restored enrolment
           and not one in the original course. */
        $newueid = $DB->get_field('user_enrolments', 'id', ['enrolid' => $newinstanceid, 'userid' => $user->id]);
        $this->assertEquals((int) $newueid, (int) $row->userenrolmentid);
    }

    /**
     * A copy without users carries no durable application record either.
     *
     * The control is what makes this non-vacuous, and it is not the absence of a submission
     * row: with no users in the archive there would be nothing to restore whatever the
     * plugin did. It is the group mapping, which is instance configuration and DOES come
     * across in the same restore - so the plugin's restore handlers demonstrably ran, and
     * chose not to write the trail.
     *
     * @return void
     */
    public function test_the_audit_trail_is_absent_without_users(): void {
        global $DB;

        [$course] = $this->create_course_with_application();
        $before = $DB->count_records('enrol_apply_submission');

        $newcourseid = $this->backup_and_restore($course, false);

        $newinstanceid = $DB->get_field('enrol', 'id', ['courseid' => $newcourseid, 'enrol' => 'apply'], MUST_EXIST);
        $this->assertTrue($DB->record_exists('enrol_apply_groups', ['enrolid' => $newinstanceid]));

        $this->assertEquals($before, $DB->count_records('enrol_apply_submission'));
        $this->assertEquals(0, $DB->count_records('enrol_apply_submission', ['courseid' => $newcourseid]));
    }

    /**
     * A record whose applicant cannot be mapped is dropped, never written ownerless.
     *
     * The archive carries the record - it was taken with users - but the restore leaves users
     * out, so no user mapping exists when the plugin's handler runs. Core registers the
     * plugin's restore paths unconditionally, unlike its own enrolment path, so the handler
     * really is called with data whose parents did not restore.
     *
     * The control is again the group mapping: it proves the handlers ran at all, so "no row
     * written" cannot be confused with "nothing happened".
     *
     * @return void
     */
    public function test_a_row_whose_user_mapping_fails_is_dropped(): void {
        global $DB;

        [$course] = $this->create_course_with_application();

        $newcourseid = $this->backup_and_restore($course, true, false, false);

        $newinstanceid = $DB->get_field('enrol', 'id', ['courseid' => $newcourseid, 'enrol' => 'apply'], MUST_EXIST);
        $this->assertTrue($DB->record_exists('enrol_apply_groups', ['enrolid' => $newinstanceid]));

        $this->assertEquals(0, $DB->count_records('enrol_apply_submission', ['courseid' => $newcourseid]));
        // Nothing anywhere is left ownerless, which is the alternative this guards against.
        $this->assertEquals(0, $DB->count_records('enrol_apply_submission', ['userid' => 0]));
    }

    /**
     * Plugin data is never restored onto another enrolment method's instance.
     *
     * Core wires a plugin's restore handlers to every enrol element in the archive, and a
     * restore with enrolments set to "never" maps every old enrol id onto the course's MANUAL
     * instance - so get_new_parentid('enrol') returns a valid id that belongs to somebody
     * else. Measured before the guard existed: this restore wrote an enrol_apply_groups row
     * against the manual instance, which nothing owns and nothing ever cleans up.
     *
     * @return void
     */
    public function test_plugin_data_is_not_restored_onto_another_enrolment_method(): void {
        global $DB;

        [$course] = $this->create_course_with_application();

        $newcourseid = $this->backup_and_restore($course, true, false, null, backup::ENROL_NEVER);

        // The precondition: this restore really did convert the instances, so the trap was set.
        $this->assertFalse($DB->record_exists('enrol', ['courseid' => $newcourseid, 'enrol' => 'apply']));
        $manualid = $DB->get_field('enrol', 'id', ['courseid' => $newcourseid, 'enrol' => 'manual'], MUST_EXIST);

        $this->assertFalse($DB->record_exists('enrol_apply_groups', ['enrolid' => $manualid]));
        $this->assertEquals(0, $DB->count_records('enrol_apply_submission', ['courseid' => $newcourseid]));
    }
}
