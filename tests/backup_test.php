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
use enrol_apply\local\submission;
use restore_controller;
use restore_dbops;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/* The two coverage targets are not autoloadable: core loads backup/moodle2/*.class.php by path,
   only when a backup or restore reaches an enrol_apply element. PHPUnit resolves a CoversClass
   target per test, so without these requires every test that runs before the first restoring one
   warns that the target is not valid for code coverage, and those warnings fail a coverage run.
   The core includes come first because both plugin classes extend core backup classes they load. */
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
     * Back a course up and extract the archive, ready to be read or restored.
     *
     * MODE_SAMESITE by default, not MODE_IMPORT: backup_course_task skips
     * backup_enrolments_structure_step outside a real backup ("prevent it in any IMPORT/HUB
     * operation"), so an import-mode copy carries no enrol data at all - not even a manual
     * method - and there is nothing here to test. This mirrors
     * backup/moodle2/tests/moodle2_test.php::prepare_for_enrolments_test().
     *
     * Kept roles need MODE_COPY: backup_controller::set_kept_roles() throws
     * cannot_set_keep_roles_wrong_mode in any other mode, so roles passed with another mode fail
     * the test rather than being ignored.
     *
     * @param \stdClass $course Course to back up.
     * @param bool $userdata Value of the users setting.
     * @param int $mode One of the backup::MODE_* constants.
     * @param array $keptroles Role ids whose holders' enrolments a copy keeps, empty to keep no roles.
     * @param bool $anonymize Value of the anonymize setting.
     * @return array The backup id and the path of the extracted archive, in that order.
     */
    protected function backup_course(
        $course,
        bool $userdata,
        int $mode = backup::MODE_SAMESITE,
        array $keptroles = [],
        bool $anonymize = false
    ): array {
        global $CFG, $USER;

        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            $mode,
            $USER->id
        );
        if ($keptroles) {
            $bc->set_kept_roles($keptroles);
        }
        $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value($userdata);
        if ($anonymize) {
            $bc->get_plan()->get_setting('anonymize')->set_value(true);
        }
        $backupid = $bc->get_backupid();
        $basepath = $bc->get_plan()->get_basepath();
        $bc->execute_plan();
        $results = $bc->get_results();
        $bc->destroy();

        /* Every mode but MODE_IMPORT zips the archive and deletes the directory it was written
           in, so it is extracted back there: the XML cannot be read otherwise, and without it
           the restore controller builds no plan and get_plan() returns null. */
        if (!file_exists($basepath . '/moodle_backup.xml')) {
            $results['backup_destination']->extract_to_pathname(
                get_file_packer('application/vnd.moodle.backup'),
                $basepath
            );
        }

        return [$backupid, $basepath];
    }

    /**
     * Back a course up and restore it into a brand new one.
     *
     * @param \stdClass $course Course to copy.
     * @param bool $userdata Whether to include users in the backup.
     * @param bool $crosssite Whether to move the site identifier first, so the restore reads as cross-site.
     * @param bool|null $restoreusers Users setting on the RESTORE side, null to match the backup.
     * @param int $enrolments One of the backup::ENROL_* constants for the restore.
     * @param callable|null $tweakarchive Called with the extracted archive's basepath before the
     *                                    restore, to check the XML or to alter it into something a
     *                                    fixture cannot produce.
     * @return int Id of the restored course.
     */
    protected function backup_and_restore(
        $course,
        bool $userdata,
        bool $crosssite = false,
        ?bool $restoreusers = null,
        int $enrolments = backup::ENROL_ALWAYS,
        ?callable $tweakarchive = null
    ): int {
        global $USER;

        [$backupid, $backupbasepath] = $this->backup_course($course, $userdata);

        /* The callback sees the archive before the restore reads it, to check what it carries or
           to edit it into what no fixture can produce, such as an archive written by an older
           version of this plugin. A restore handler reading an element missing from such an
           archive gets an undefined property, an E_WARNING that --fail-on-warning turns into a
           failed run. */
        if ($tweakarchive !== null) {
            $tweakarchive($backupbasepath);
        }

        /* restore_controller decides whether the backup came from this site by comparing
           md5(get_site_identifier()) with the hash the backup recorded, while loading the plan
           in its constructor. Changing the identifier here is therefore the whole of "restore
           into another site", which is the case restore_instance() degrades for. */
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
        /* For a users-excluded restore, restore_course_task schedules the enrolments step only
           under ENROL_ALWAYS; otherwise no enrol instance comes across and the callers' lookups
           would throw rather than fail. */
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
     * A restore cannot see the backup-side users gate: with users left out there is no user
     * mapping either, so the restore handler drops the record whatever the backup did. The gate
     * keeps personal data out of the archive file, so the archive is where it is observed.
     *
     * @param \stdClass $course Course to back up.
     * @param bool $userdata Whether to include users.
     * @return string Contents of course/enrolments.xml.
     */
    protected function backup_enrolments_xml($course, bool $userdata): string {
        [, $basepath] = $this->backup_course($course, $userdata);

        $xml = $basepath . '/course/enrolments.xml';
        $this->assertFileExists($xml, 'MODE_SAMESITE must produce an enrolments file');

        return file_get_contents($xml);
    }

    /**
     * Every test file naming a backup class as a coverage target loads that class itself.
     *
     * The two classes are not autoloadable (see the requires at the top of this file), so a file
     * whose CoversClass names one must require it at file scope, or running that file alone warns
     * that the target is not valid for code coverage, and the warning fails a coverage run.
     * class_exists() cannot see this: PHPUnit includes every test file of the suite before it runs
     * a test, so another file's requires satisfy it whichever file lost its own, which is how the
     * check this replaced stayed green with the requires deleted. The file is read instead.
     *
     * The control: the scan finds this file and tests/backup_fields_test.php, so it is not
     * vacuous.
     *
     * Changes that must make it fail: deleting the file-scope requires of either file.
     *
     * @return void
     */
    public function test_every_file_covering_a_backup_class_requires_it(): void {
        global $CFG;

        $classes = [
            'backup_enrol_apply_plugin' => '/enrol/apply/backup/moodle2/backup_enrol_apply_plugin.class.php',
            'restore_enrol_apply_plugin' => '/enrol/apply/backup/moodle2/restore_enrol_apply_plugin.class.php',
        ];
        $scanned = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($CFG->dirroot . '/enrol/apply/tests'));
        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '_test.php')) {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            foreach ($classes as $class => $path) {
                if (!str_contains($source, '#[CoversClass(\\' . $class . '::class)]')) {
                    continue;
                }
                $scanned[$file->getFilename()] = true;
                $this->assertMatchesRegularExpression(
                    '~^require_once\(\$CFG->dirroot \. \'' . preg_quote($path, '~') . '\'\);$~m',
                    $source,
                    $file->getFilename() . ' names ' . $class . ' as a coverage target and does not load it'
                );
            }
        }

        $this->assertEqualsCanonicalizing(['backup_test.php', 'backup_fields_test.php'], array_keys($scanned));
    }

    /**
     * Seed a decision on the record, with groups and a role the restore has to translate.
     *
     * @param \stdClass $course Course the application belongs to.
     * @param \stdClass $applicant The applicant.
     * @param array $groupids Group ids the decider chose.
     * @param int $roleid Role id the decider chose.
     * @param string $note Note the decider recorded about the decision.
     * @return void
     */
    protected function record_decision(
        \stdClass $course,
        \stdClass $applicant,
        array $groupids,
        int $roleid,
        string $note = ''
    ): void {
        global $DB;

        /* A waiting application, the status that may carry a choice: a restore zeroes the decided role
           and groups of a pending one, which has no decision. */
        $DB->set_field_select(
            'enrol_apply_submission',
            'status',
            submission::STATUS_WAITING,
            'courseid = :courseid AND userid = :userid',
            ['courseid' => $course->id, 'userid' => $applicant->id]
        );
        $DB->set_field_select(
            'enrol_apply_submission',
            'decidedgroups',
            implode(',', $groupids),
            'courseid = :courseid AND userid = :userid',
            ['courseid' => $course->id, 'userid' => $applicant->id]
        );
        $DB->set_field_select(
            'enrol_apply_submission',
            'decidedrole',
            $roleid,
            'courseid = :courseid AND userid = :userid',
            ['courseid' => $course->id, 'userid' => $applicant->id]
        );
        $DB->set_field_select(
            'enrol_apply_submission',
            'decisionnote',
            $note,
            'courseid = :courseid AND userid = :userid',
            ['courseid' => $course->id, 'userid' => $applicant->id]
        );
    }

    /**
     * The restored record for the only applicant of a course.
     *
     * @param int $courseid Restored course id.
     * @return \stdClass The record.
     */
    protected function restored_record(int $courseid): \stdClass {
        global $DB;

        $records = $DB->get_records('enrol_apply_submission', ['courseid' => $courseid]);
        $this->assertCount(1, $records);

        return reset($records);
    }

    /**
     * The decided groups and role survive a restore, translated to the new course's ids.
     *
     * Both are ids of things the restore re-creates, so carrying them verbatim would be worse
     * than dropping them: the archived numbers name a group and a role of the course the backup
     * came from, and in the destination they name something else or nothing at all. The
     * assertion is therefore that the values CHANGED and now name the right things.
     *
     * The role is deliberately not the instance's default. Core annotates every enrol instance's
     * own roleid ({@see \backup_enrolments_structure_step}), so the default role reaches the
     * archive whatever this plugin does; only a role the instance does not name makes this
     * plugin's own role annotation necessary.
     *
     * @return void
     */
    public function test_the_decided_groups_and_role_survive_a_restore(): void {
        global $DB;

        [$course, $instance, $applicant] = $this->create_course_with_application();
        $chosen = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Chosen on approval']);
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $this->assertNotEquals(
            $roleid,
            (int) $instance->roleid,
            'the premise: the decided role must not be the one core annotates for the instance'
        );
        $this->record_decision($course, $applicant, [(int) $chosen->id], $roleid);

        $newcourseid = $this->backup_and_restore($course, true);
        $restored = $this->restored_record($newcourseid);

        $newgroupid = (int) $DB->get_field(
            'groups',
            'id',
            ['courseid' => $newcourseid, 'name' => 'Chosen on approval'],
            MUST_EXIST
        );
        $this->assertNotEquals((int) $chosen->id, $newgroupid, 'the premise: the group really was re-created');
        $this->assertSame((string) $newgroupid, (string) $restored->decidedgroups);

        // A role is site-wide, so a same-site restore maps it to itself; this assertion cannot
        // tell the mapping from a verbatim copy, and the test below can.
        $this->assertSame($roleid, (int) $restored->decidedrole);
    }

    /**
     * A decided role that no longer names a role is restored as none, not copied through.
     *
     * The test above cannot tell a mapping from a verbatim copy, because a same-site restore maps
     * a role to itself. A role deleted after the decision can: its id is still in the archive,
     * but roles.xml is built by joining the annotated ids against {role}, so the archive carries
     * no such role and the restore has no mapping for it. A handler copying the value through
     * would write an id that names nothing.
     *
     * @return void
     */
    public function test_a_decided_role_that_names_no_role_is_restored_as_none(): void {
        [$course, , $applicant] = $this->create_course_with_application();
        $roleid = create_role('Deleted after the decision', 'deletedafterdecision', '');
        $this->record_decision($course, $applicant, [], $roleid);
        delete_role($roleid);

        $premise = function (string $basepath) use ($roleid): void {
            $this->assertStringContainsString(
                '<decidedrole>' . $roleid . '</decidedrole>',
                file_get_contents($basepath . '/course/enrolments.xml'),
                'the premise: the archive still carries the deleted role\'s id'
            );
        };
        $newcourseid = $this->backup_and_restore($course, true, false, null, backup::ENROL_ALWAYS, $premise);
        $restored = $this->restored_record($newcourseid);

        // The control: the record itself travelled, so the 0 is the mapping and not a missing row.
        $this->assertSame('I would like to join this course', $restored->comment);
        $this->assertSame(0, (int) $restored->decidedrole);
    }

    /**
     * A group that did not travel is dropped rather than carried as a foreign id.
     *
     * The id names a group of another course, which no backup of this one annotates, so it has
     * no mapping. Writing it through unchanged would put the applicant into whatever that number
     * means in the destination.
     *
     * The group belongs to another course rather than being excluded from the backup: with
     * groups excluded, the id still came back mapped to itself.
     *
     * @return void
     */
    public function test_a_group_that_did_not_travel_is_dropped(): void {
        [$course, , $applicant] = $this->create_course_with_application();
        $elsewhere = $this->getDataGenerator()->create_course();
        $foreign = $this->getDataGenerator()->create_group(['courseid' => $elsewhere->id, 'name' => 'Another course']);
        $this->record_decision($course, $applicant, [(int) $foreign->id], 0);

        $newcourseid = $this->backup_and_restore($course, true);
        $restored = $this->restored_record($newcourseid);

        $this->assertSame('', (string) $restored->decidedgroups);
        // The control: the record itself did travel, so the empty value is the mapping having
        // dropped the id and not a record that never arrived.
        $this->assertSame('I would like to join this course', $restored->comment);
    }

    /**
     * The groups an approval joined survive a restore, translated to the new course's ids, and
     * an id that did not travel is dropped, as for the decided groups.
     *
     * Changes that must make it fail: the column left out of the backup element, or copied
     * through without the mapping.
     *
     * @return void
     */
    public function test_the_joined_groups_survive_a_restore(): void {
        global $DB;

        [$course, , $applicant] = $this->create_course_with_application();
        $joined = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Joined on approval']);
        $elsewhere = $this->getDataGenerator()->create_course();
        $foreign = $this->getDataGenerator()->create_group(['courseid' => $elsewhere->id, 'name' => 'Another course']);
        // Approved: a pending record has joined nothing, and a restore does not carry joined groups for it.
        $DB->set_field('enrol_apply_submission', 'status', submission::STATUS_APPROVED, ['userid' => $applicant->id]);
        $DB->set_field_select(
            'enrol_apply_submission',
            'joinedgroups',
            $foreign->id . ',' . $joined->id,
            'courseid = :courseid AND userid = :userid',
            ['courseid' => $course->id, 'userid' => $applicant->id]
        );

        $newcourseid = $this->backup_and_restore($course, true);
        $restored = $this->restored_record($newcourseid);

        $newgroupid = (int) $DB->get_field(
            'groups',
            'id',
            ['courseid' => $newcourseid, 'name' => 'Joined on approval'],
            MUST_EXIST
        );
        $this->assertNotEquals((int) $joined->id, $newgroupid, 'the premise: the group really was re-created');
        $this->assertSame((string) $newgroupid, (string) $restored->joinedgroups);
    }

    /**
     * Groups joined that are not known stay not known through a restore, rather than becoming
     * "joined none": the archive cannot tell the two apart, and "none" would be the false one.
     *
     * @return void
     */
    public function test_joined_groups_not_known_stay_not_known(): void {
        [$course] = $this->create_course_with_application();

        $newcourseid = $this->backup_and_restore($course, true);
        $restored = $this->restored_record($newcourseid);

        // The control: the record itself travelled.
        $this->assertSame('I would like to join this course', $restored->comment);
        $this->assertNull($restored->joinedgroups);
    }

    /**
     * Stamp a group key route on the only applicant's record.
     *
     * @param \stdClass $course Course the application belongs to.
     * @param \stdClass $applicant The applicant.
     * @param int $groupid Group whose key was presented.
     * @param int $outcome Outcome to store, a submission::KEY_ constant or a foreign value.
     * @return void
     */
    protected function record_key(\stdClass $course, \stdClass $applicant, int $groupid, int $outcome): void {
        global $DB;

        $where = 'courseid = :courseid AND userid = :userid';
        $params = ['courseid' => $course->id, 'userid' => $applicant->id];
        $DB->set_field_select('enrol_apply_submission', 'keygroupid', $groupid, $where, $params);
        $DB->set_field_select('enrol_apply_submission', 'keyoutcome', $outcome, $where, $params);
    }

    /**
     * The key route survives a restore, its group translated to the new course's id.
     *
     * Changes that must make it fail: the two elements left out of the backup, or the group
     * copied through rather than mapped.
     *
     * @return void
     */
    public function test_the_key_route_survives_a_restore(): void {
        global $DB;

        [$course, , $applicant] = $this->create_course_with_application();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Joined by key']);
        $this->record_key($course, $applicant, (int) $group->id, submission::KEY_ADMITTED);

        $newcourseid = $this->backup_and_restore($course, true);
        $restored = $this->restored_record($newcourseid);

        $newgroupid = (int) $DB->get_field('groups', 'id', ['courseid' => $newcourseid, 'name' => 'Joined by key'], MUST_EXIST);
        $this->assertNotEquals((int) $group->id, $newgroupid, 'the premise: the group really was re-created');
        $this->assertSame($newgroupid, (int) $restored->keygroupid);
        $this->assertSame(submission::KEY_ADMITTED, (int) $restored->keyoutcome);
    }

    /**
     * A key group that did not travel restores as 0, and the outcome stays: the applicant was
     * admitted by a key whatever happened to the group.
     *
     * Changes that must make it fail: copying the archived group id through unmapped.
     *
     * @return void
     */
    public function test_a_key_group_that_did_not_travel_keeps_its_outcome(): void {
        [$course, , $applicant] = $this->create_course_with_application();
        $elsewhere = $this->getDataGenerator()->create_course();
        $foreign = $this->getDataGenerator()->create_group(['courseid' => $elsewhere->id]);
        $this->record_key($course, $applicant, (int) $foreign->id, submission::KEY_NO_PLACE);

        $restored = $this->restored_record($this->backup_and_restore($course, true));

        $this->assertSame(0, (int) $restored->keygroupid);
        $this->assertSame(submission::KEY_NO_PLACE, (int) $restored->keyoutcome);
    }

    /**
     * An outcome outside the vocabulary, which only a foreign archive carries, restores as no
     * key at all, group included.
     *
     * Changes that must make it fail: dropping the vocabulary check in key_fields().
     *
     * @return void
     */
    public function test_an_unknown_key_outcome_restores_as_no_key(): void {
        [$course, , $applicant] = $this->create_course_with_application();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->record_key($course, $applicant, (int) $group->id, 9);

        $restored = $this->restored_record($this->backup_and_restore($course, true));

        $this->assertSame(0, (int) $restored->keygroupid);
        $this->assertSame(submission::KEY_NONE, (int) $restored->keyoutcome);
        // The control: the record itself travelled.
        $this->assertSame('I would like to join this course', $restored->comment);
    }

    /**
     * An archive written before these two elements existed restores without a warning.
     *
     * This covers the absent property. The commoner case, a present but empty element, parses
     * back as null and is produced by every undecided application, so every other restore test
     * here exercises the handler's ?? on that path. This one strips the elements from the
     * extracted XML, because no fixture can produce an archive from an older plugin version.
     *
     * @return void
     */
    public function test_an_archive_without_the_new_elements_still_restores(): void {
        [$course, , $applicant] = $this->create_course_with_application();
        $chosen = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Chosen on approval']);
        $this->record_decision($course, $applicant, [(int) $chosen->id], 0);

        $strip = function (string $basepath): void {
            $path = $basepath . '/course/enrolments.xml';
            $xml = file_get_contents($path);
            $this->assertStringContainsString('<decidedgroups>', $xml, 'the premise: the elements were written');
            $this->assertStringContainsString('<decisionnote>', $xml, 'the premise: the note was written');
            $xml = preg_replace('#\s*<decided(groups|role)>.*?</decided(groups|role)>#s', '', $xml);
            $xml = preg_replace('#\s*<decisionnote>.*?</decisionnote>#s', '', $xml);
            file_put_contents($path, $xml);
        };

        $newcourseid = $this->backup_and_restore($course, true, false, null, backup::ENROL_ALWAYS, $strip);
        $restored = $this->restored_record($newcourseid);

        $this->assertSame('', (string) $restored->decidedgroups);
        $this->assertSame(0, (int) $restored->decidedrole);
        /* Without the note's own ??, the absent property raises a warning rather than a
           TypeError, which fails the run under failOnWarning while the row itself looks right. */
        $this->assertSame('', (string) $restored->decisionnote);
    }

    /**
     * The decision note survives a backup and restore.
     *
     * It holds no ids, so it is carried verbatim and annotates nobody - which is why it is not
     * enough to know that the two id-bearing columns travel. A column simply left out of the
     * backup element is silent in both directions: the archive is well formed and the restored
     * record is complete apart from the one field nobody looks at.
     *
     * @return void
     */
    public function test_the_decision_note_survives_a_restore(): void {
        [$course, , $applicant] = $this->create_course_with_application();
        $this->record_decision($course, $applicant, [], 0, 'Transcript verified with the registrar.');

        $newcourseid = $this->backup_and_restore($course, true);
        $restored = $this->restored_record($newcourseid);

        $this->assertSame('Transcript verified with the registrar.', (string) $restored->decisionnote);
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
        $this->assertStringContainsString('<answer id=', $withusers);
        $this->assertStringContainsString('Recife', $withusers);

        $withoutusers = $this->backup_enrolments_xml($course, false);
        $this->assertStringNotContainsString('<submission id=', $withoutusers);
        $this->assertStringNotContainsString('I would like to join this course', $withoutusers);
        // The answers are the record's children, so the record's gate is theirs.
        $this->assertStringNotContainsString('<answer id=', $withoutusers);
        $this->assertStringNotContainsString('Recife', $withoutusers);

        /* The second control: instance configuration is NOT gated and still travels, so the
           assertions above are about the gate rather than about an empty backup. */
        $this->assertStringContainsString('<applygroup id=', $withoutusers);
    }

    /**
     * An anonymised backup carries none of the three personal tables.
     *
     * Core anonymises users.xml and drops the custom profile data, but the comment, the
     * submission record and the typed answers are free text only this plugin writes. Read from the
     * archive file, since a restore would drop the rows for its own reasons. The same backup with
     * anonymisation off is the control, and the instance configuration still travels in both.
     *
     * Changes that must make it fail: the backup reading the anonymize setting nowhere, or only
     * for one of the three sources.
     *
     * @return void
     */
    public function test_an_anonymised_backup_carries_no_personal_data(): void {
        [$course] = $this->create_course_with_application();

        $plain = $this->backup_enrolments_xml($course, true);
        $this->assertStringContainsString('<application id=', $plain);
        $this->assertStringContainsString('<submission id=', $plain);
        $this->assertStringContainsString('<answer id=', $plain);
        $this->assertStringContainsString('I would like to join this course', $plain);
        $this->assertStringContainsString('Recife', $plain);

        [, $basepath] = $this->backup_course($course, true, backup::MODE_SAMESITE, [], true);
        $anonymised = file_get_contents($basepath . '/course/enrolments.xml');
        $this->assertStringNotContainsString('<application id=', $anonymised);
        $this->assertStringNotContainsString('<submission id=', $anonymised);
        $this->assertStringNotContainsString('<answer id=', $anonymised);
        $this->assertStringNotContainsString('I would like to join this course', $anonymised);
        $this->assertStringNotContainsString('Recife', $anonymised);
        // The control the other way: configuration still travels, so the file is not just empty.
        $this->assertStringContainsString('<applygroup id=', $anonymised);
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
        [, $basepath] = $this->backup_course($course, $userdata, backup::MODE_COPY, $keptroles);

        $xml = $basepath . '/course/enrolments.xml';
        $this->assertFileExists($xml, 'a copy must still produce an enrolments file');

        return file_get_contents($xml);
    }

    /**
     * Seed one more applicant, holding the given role here or in another course.
     *
     * The pending comment and the durable record get different markers (the given marker
     * suffixed PENDING and RECORD), so an assertion can tell which of the two elements it is
     * looking at. Both are gated separately and a test that could not distinguish them would
     * hold only one.
     *
     * @param \stdClass $course Course to apply to.
     * @param \stdClass $instance Apply instance to apply to.
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

        /* The third applicant separates the two halves of core's predicate: they hold the kept
           role in a different course and are only a student here, so core writes no enrolment
           for them. Changes that must make it fail: dropping the "ra.contextid = ?" conjunct,
           which would write the data of anyone holding the kept role anywhere on the site. */
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
     * Core does write its own kept-role enrolments in this cell, but this plugin must not. With
     * user data off the copy's archive carries no users, so the restore's enrolments setting
     * defaults to ENROL_NEVER and no apply instance or user enrolment reaches the destination;
     * core re-enrols the kept-role users through the manual plugin afterwards instead. Anything
     * this plugin wrote there would be a comment and a profile snapshot in an archive with
     * nowhere to go.
     *
     * The control is the test above: with user data on, the same fixture does travel.
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
     * Checking the archive alone is not enough. enrol_apply_applicationinfo is keyed on the
     * user-enrolment mapping, which misses for an excluded user, but enrol_apply_submission is
     * keyed on the user mapping. A kept-roles copy annotates every course-context role
     * assignment into users.xml ({@see \backup_roles_structure_step}), so the excluded
     * applicant's user mapping resolves and, without the backup-side gate, their comment and
     * profile snapshot land in the copied course under a live user id.
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
        global $USER;

        [$backupid] = $this->backup_course($course, true);

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
        // Nor its answers: a record that already exists keeps the ones it has.
        $this->assertSame(2, $DB->count_records(\enrol_apply\local\submission::ANSWER_TABLE, ['submissionid' => $row->id]));
    }

    /**
     * A record the restore skips does not lend its answers to the record restored before it.
     *
     * The answers find their record through get_new_parentid(), which returns the LATEST id
     * mapped under the record's name. A record that is skipped - here because the target already
     * holds it - must therefore still register a mapping, to nothing; left unset, its answers
     * would be attached to whichever applicant was restored just before it.
     *
     * The order matters and is built on purpose: the first applicant's record is removed from the
     * target between the two restores, so the second restore writes it and then skips the second
     * applicant's, which comes after it in the archive.
     *
     * @return void
     */
    public function test_a_skipped_record_does_not_lend_its_answers_to_another(): void {
        global $DB;

        [$course, $instance, $first] = $this->create_course_with_application();
        $second = $this->add_applicant($course, $instance, 'Second applicant', 'student');
        $secondrecord = (int) $DB->get_field('enrol_apply_submission', 'id', ['userid' => $second->id], MUST_EXIST);
        \enrol_apply\local\submission::write_answers($secondrecord, [
            ['key' => 's_country', 'label' => 'Country', 'value' => 'BR'],
        ]);
        $target = $this->getDataGenerator()->create_course();

        $this->restore_into_existing($course, $target->id);
        $restoredfirst = (int) $DB->get_field(
            'enrol_apply_submission',
            'id',
            ['courseid' => $target->id, 'userid' => $first->id],
            MUST_EXIST
        );
        \enrol_apply\local\submission::delete($restoredfirst);

        $this->restore_into_existing($course, $target->id);

        $rows = $DB->get_records('enrol_apply_submission', ['courseid' => $target->id], '', 'userid, id');
        $this->assertCount(2, $rows);
        $this->assertSame(
            ['s_city', 'a_7'],
            array_column(\enrol_apply\local\submission::read_snapshot((int) $rows[$first->id]->id), 'key')
        );
        $this->assertSame(
            ['s_country'],
            array_column(\enrol_apply\local\submission::read_snapshot((int) $rows[$second->id]->id), 'key')
        );
    }

    /**
     * An archive written before the answer table restores its envelope into rows.
     *
     * Such an archive carries the snapshot as JSON on the submission element and no answers. It is
     * as untrusted as any archive, so it gets the old reader's tolerance: the malformed entry is
     * dropped, the rest arrive in the envelope's order. No fixture can write the old shape, so the
     * extracted XML is edited into it.
     *
     * @return void
     */
    public function test_an_archive_carrying_the_old_envelope_restores_its_answers(): void {
        global $DB;

        [$course, , $user] = $this->create_course_with_application();

        $envelope = json_encode([
            'version' => \enrol_apply\local\submission::SNAPSHOT_VERSION,
            'fields' => [
                ['key' => 'a_7', 'label' => 'Why this course?', 'value' => 'To learn'],
                ['key' => 'c_3', 'label' => 'Broken', 'value' => ['not', 'scalar']],
                ['key' => 's_city', 'label' => 'City', 'value' => 'Olinda'],
            ],
        ]);
        $downgrade = function (string $basepath) use ($envelope): void {
            $path = $basepath . '/course/enrolments.xml';
            $xml = file_get_contents($path);
            $this->assertStringContainsString('<answers>', $xml, 'the premise: the new archive carries answers');
            $xml = preg_replace(
                '#<answers>.*?</answers>#s',
                '<userinfodata>' . htmlspecialchars($envelope, ENT_COMPAT | ENT_XML1) . '</userinfodata>',
                $xml
            );
            file_put_contents($path, $xml);
        };

        $newcourseid = $this->backup_and_restore($course, true, false, null, backup::ENROL_ALWAYS, $downgrade);

        $row = $DB->get_record('enrol_apply_submission', ['courseid' => $newcourseid, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame([
            ['key' => 'a_7', 'label' => 'Why this course?', 'value' => 'To learn'],
            ['key' => 's_city', 'label' => 'City', 'value' => 'Olinda'],
        ], \enrol_apply\local\submission::read_snapshot((int) $row->id));
    }

    /**
     * Give the application's instance a question of its own, answered on the application.
     *
     * @param \stdClass $instance The apply instance.
     * @param \stdClass $user The applicant.
     * @return int Id of the question in {customfield_field}.
     */
    protected function answer_a_question_of_the_instance(\stdClass $instance, \stdClass $user): int {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category([
            'name' => 'About you', 'component' => 'enrol_apply', 'area' => 'application', 'itemid' => $instance->id,
        ]);
        $field = $generator->create_field([
            'categoryid' => $category->get('id'), 'type' => 'text', 'shortname' => 'motive', 'name' => 'Motive',
        ]);
        $recordid = (int) $DB->get_field('enrol_apply_submission', 'id', ['userid' => $user->id], MUST_EXIST);
        $DB->insert_record(submission::ANSWER_TABLE, (object) [
            'submissionid' => $recordid,
            'fieldkey' => \enrol_apply\local\answers::key((int) $field->get('id')),
            'label' => 'Motive',
            'value' => 'Curiosity',
            'sortorder' => 5,
        ]);

        return (int) $field->get('id');
    }

    /**
     * The id of the question the restored instance asks under a shortname.
     *
     * @param int $courseid Restored course id.
     * @param string $shortname Question shortname.
     * @return int Id in {customfield_field}.
     */
    protected function restored_question(int $courseid, string $shortname): int {
        global $DB;

        $enrolid = (int) $DB->get_field('enrol', 'id', ['courseid' => $courseid, 'enrol' => 'apply'], MUST_EXIST);

        return (int) $DB->get_field_sql(
            "SELECT f.id
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = 'enrol_apply' AND c.area = 'application' AND c.itemid = :enrolid
                    AND f.shortname = :shortname",
            ['enrolid' => $enrolid, 'shortname' => $shortname],
            MUST_EXIST
        );
    }

    /**
     * An answer to one of the instance's own questions follows the question's new id.
     *
     * Every restore recreates the instance's questions, so their ids change even on the same
     * site; the report's per-question columns look answers up by the new key and would otherwise
     * find nothing. a_7 names no question of the instance, so on the same site it may name a
     * shared one and keeps its id: the control that only a mapped key moves.
     *
     * @return void
     */
    public function test_an_answer_follows_its_question_to_the_restored_instance(): void {
        [$course, $instance, $user] = $this->create_course_with_application();
        $oldid = $this->answer_a_question_of_the_instance($instance, $user);

        $newcourseid = $this->backup_and_restore($course, true);

        $newid = $this->restored_question($newcourseid, 'motive');
        $this->assertNotSame($oldid, $newid, 'the premise: the question was given a new id');
        $snapshot = array_column(submission::read_snapshot((int) $this->restored_record($newcourseid)->id), 'value', 'key');
        $this->assertSame(
            ['s_city' => 'Recife', 'a_7' => 'R&D <2>', 'a_' . $newid => 'Curiosity'],
            $snapshot
        );
    }

    /**
     * From another site, an answer whose key names a site-local id it cannot map is not restored.
     *
     * A c_ key is masked by the visibility of the profile field with that id on the restoring
     * site, which on another site is an unrelated field, so a value hidden where it was given could
     * be shown. An unmapped a_ key there may name another question. The standard field and the
     * answer to the instance's own, remapped, question are the control that the record itself
     * was restored.
     *
     * @return void
     */
    public function test_answers_naming_another_sites_ids_are_not_restored(): void {
        global $DB;

        [$course, $instance, $user] = $this->create_course_with_application();
        $this->answer_a_question_of_the_instance($instance, $user);
        $profilefield = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'secret', 'name' => 'Secret', 'visible' => PROFILE_VISIBLE_ALL,
        ]);
        $recordid = (int) $DB->get_field('enrol_apply_submission', 'id', ['userid' => $user->id], MUST_EXIST);
        $DB->insert_record(submission::ANSWER_TABLE, (object) [
            'submissionid' => $recordid,
            'fieldkey' => 'c_' . $profilefield->id,
            'label' => 'Secret',
            'value' => 'hidden where it was given',
            'sortorder' => 6,
        ]);

        $newcourseid = $this->backup_and_restore($course, true, true);

        $newid = $this->restored_question($newcourseid, 'motive');
        $snapshot = array_column(submission::read_snapshot((int) $this->restored_record($newcourseid)->id), 'value', 'key');
        $this->assertSame(['s_city' => 'Recife', 'a_' . $newid => 'Curiosity'], $snapshot);
    }

    /**
     * On the same site a profile field id still names the same field, so its answer is kept.
     *
     * @return void
     */
    public function test_a_profile_field_answer_is_kept_on_the_same_site(): void {
        global $DB;

        [$course, , $user] = $this->create_course_with_application();
        $profilefield = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'secret', 'name' => 'Secret',
        ]);
        $recordid = (int) $DB->get_field('enrol_apply_submission', 'id', ['userid' => $user->id], MUST_EXIST);
        $DB->insert_record(submission::ANSWER_TABLE, (object) [
            'submissionid' => $recordid,
            'fieldkey' => 'c_' . $profilefield->id,
            'label' => 'Secret',
            'value' => 'Kept',
            'sortorder' => 6,
        ]);

        $newcourseid = $this->backup_and_restore($course, true);

        $snapshot = array_column(submission::read_snapshot((int) $this->restored_record($newcourseid)->id), 'value', 'key');
        $this->assertSame('Kept', $snapshot['c_' . $profilefield->id] ?? null);
    }

    /**
     * An old archive's envelope goes through the same key rule as answer rows.
     *
     * @return void
     */
    public function test_an_old_envelope_from_another_site_drops_what_it_cannot_map(): void {
        global $DB;

        [$course, , $user] = $this->create_course_with_application();
        $envelope = json_encode([
            'version' => submission::SNAPSHOT_VERSION,
            'fields' => [
                ['key' => 'a_7', 'label' => 'Why this course?', 'value' => 'To learn'],
                ['key' => 'c_3', 'label' => 'Secret', 'value' => 'hidden where it was given'],
                ['key' => 's_city', 'label' => 'City', 'value' => 'Olinda'],
            ],
        ]);
        $downgrade = function (string $basepath) use ($envelope): void {
            $path = $basepath . '/course/enrolments.xml';
            $xml = preg_replace(
                '#<answers>.*?</answers>#s',
                '<userinfodata>' . htmlspecialchars($envelope, ENT_COMPAT | ENT_XML1) . '</userinfodata>',
                file_get_contents($path)
            );
            file_put_contents($path, $xml);
        };

        $newcourseid = $this->backup_and_restore($course, true, true, null, backup::ENROL_ALWAYS, $downgrade);

        $row = $DB->get_record('enrol_apply_submission', ['courseid' => $newcourseid, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame(
            [['key' => 's_city', 'label' => 'City', 'value' => 'Olinda']],
            submission::read_snapshot((int) $row->id)
        );
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
        $submissionid = (int) $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $course->id,
            'userid' => $user->id,
            'enrolid' => $instanceid,
            'userenrolmentid' => $ueid,
            'comment' => 'I would like to join this course',
            'status' => \enrol_apply\local\submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => self::SUBMITTED_AT,
            'timedecided' => 0,
            'decidedby' => $decider ? $decider->id : 0,
        ]);
        // Two answers, in an order that is not the keys' alphabetical one, so the order is tested.
        \enrol_apply\local\submission::write_answers($submissionid, [
            ['key' => 's_city', 'label' => 'City', 'value' => 'Recife'],
            ['key' => 'a_7', 'label' => 'Why this course?', 'value' => 'R&D <2>'],
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
     * Group memberships are stamped with this plugin as their component so core's
     * unenrol_user() can clean them up, and core routes any component starting with "enrol_" to
     * enrol_plugin::restore_group_member(), whose base implementation is empty, with no fallback
     * and no warning on that branch. Without the plugin's override the membership is lost.
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
     * Core routes any {role_assignments} row whose component starts with "enrol_" to
     * enrol_plugin::restore_role_assignment() ({@see \restore_ras_and_caps_structure_step::process_assignment()}),
     * whose base implementation is empty. Unlike the generic-component branch beside it, that
     * branch has no role_assign() fallback and logs no warning, so without the plugin's override
     * a restored applicant keeps an active enrolment and loses the role.
     *
     * The control is a second user's bare manual assignment in the same course: its survival
     * proves the restore processed the role assignments at all, so the applicant's missing role
     * cannot be a restore that assigned nothing to anybody.
     *
     * The stamp is asserted, not merely the role: a bare restored assignment would leave
     * process_expirations() guessing $instance->roleid.
     *
     * Changes that must make it fail: emptying enrol_apply_plugin::restore_role_assignment()
     * (the applicant half fails while the control stays green).
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

        /* A second, unrestricted instance pins the "there was a restriction" half of the guard:
           without that half every cross-site restore would get the sentinel, and every restored
           course would refuse every application although it was never restricted. The
           restricted instance's -1 proves the cross-site path ran. */
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
     * A restore switches the instance's profile write off, on the same site as well as on another.
     *
     * customint8 is the per-instance half of the switch that lets an application write to
     * {user} ({@see \enrol_apply\local\profilewriter::is_enabled()}), and restore_instance()
     * zeroes it on every restore. A same-site restore is the one to test: a guard narrowed to
     * cross-site restores would still pass a cross-site test, and is_samesite() can be satisfied
     * by what the archive itself claims.
     *
     * Two things make the zero mean something. The archive is checked to carry the switch on, and
     * customint3, which core backs up in the same element, arrives unchanged, so the restore did
     * write the instance's custom fields and zeroed this one alone.
     *
     * @return void
     */
    public function test_a_restore_switches_the_profile_write_off(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application();
        $DB->set_field('enrol', 'customint8', 1, ['id' => $instance->id]);
        $DB->set_field('enrol', 'customint3', 7, ['id' => $instance->id]);

        $premise = function (string $basepath): void {
            $this->assertStringContainsString(
                '<customint8>1</customint8>',
                file_get_contents($basepath . '/course/enrolments.xml'),
                'the premise: the archive carries the switch on'
            );
        };
        $newcourseid = $this->backup_and_restore($course, false, false, null, backup::ENROL_ALWAYS, $premise);

        $restored = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'apply'], '*', MUST_EXIST);
        $this->assertEquals(7, (int) $restored->customint3, 'the control: the instance\'s custom fields were restored');
        $this->assertEquals(0, (int) $restored->customint8);
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

        // The snapshot travels whole and in order: it is the answer as given, not a reference to resolve later.
        $this->assertSame([
            ['key' => 's_city', 'label' => 'City', 'value' => 'Recife'],
            ['key' => 'a_7', 'label' => 'Why this course?', 'value' => 'R&D <2>'],
        ], \enrol_apply\local\submission::read_snapshot((int) $row->id));

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
     * else. Without the guard this restore writes an enrol_apply_groups row against the manual
     * instance, which nothing owns and nothing ever cleans up.
     *
     * @return void
     */
    public function test_plugin_data_is_not_restored_onto_another_enrolment_method(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application();
        \enrol_apply\local\instanceoptions::set((int) $instance->id, ['deciders' => 1]);
        $offered = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        \enrol_apply\local\groupoffer::set((int) $instance->id, [(int) $offered->id]);

        $newcourseid = $this->backup_and_restore($course, true, false, null, backup::ENROL_NEVER);

        // The precondition: this restore really did convert the instances, so the trap was set.
        $this->assertFalse($DB->record_exists('enrol', ['courseid' => $newcourseid, 'enrol' => 'apply']));
        $manualid = $DB->get_field('enrol', 'id', ['courseid' => $newcourseid, 'enrol' => 'manual'], MUST_EXIST);

        $this->assertFalse($DB->record_exists('enrol_apply_groups', ['enrolid' => $manualid]));
        $this->assertFalse($DB->record_exists('enrol_apply_options', ['enrolid' => $manualid]));
        $this->assertFalse($DB->record_exists('enrol_apply_offer', ['enrolid' => $manualid]));
        $this->assertEquals(0, $DB->count_records('enrol_apply_submission', ['courseid' => $newcourseid]));
    }

    /**
     * The method's options and its offer survive a restore without users, the offer translated
     * to the new course's groups.
     *
     * Changes that must make it fail: either element left out of the backup or put under the
     * users gate, or the offered group copied through rather than mapped.
     *
     * @return void
     */
    public function test_the_options_and_the_offer_survive_a_restore(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application();
        \enrol_apply\local\instanceoptions::set((int) $instance->id, [
            'groupchoice' => \enrol_apply\local\instanceoptions::CHOICE_REQUIRED,
            'deciders' => \enrol_apply\local\instanceoptions::DECIDERS_ALLGROUPS,
            'groupseats' => 0,
            'hidecard' => 1,
        ]);
        $evening = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Evening A']);
        $morning = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Morning C']);
        \enrol_apply\local\groupoffer::set((int) $instance->id, [(int) $evening->id, (int) $morning->id]);

        $newcourseid = $this->backup_and_restore($course, false);
        $newinstanceid = (int) $DB->get_field('enrol', 'id', ['courseid' => $newcourseid, 'enrol' => 'apply'], MUST_EXIST);

        $this->assertEquals((object) [
            'groupchoice' => \enrol_apply\local\instanceoptions::CHOICE_REQUIRED,
            'deciders' => \enrol_apply\local\instanceoptions::DECIDERS_ALLGROUPS,
            'groupseats' => 0,
            'hidecard' => 1,
        ], \enrol_apply\local\instanceoptions::get($newinstanceid));

        $names = array_map(fn($group) => $group->name, \enrol_apply\local\groupoffer::groups($newinstanceid));
        sort($names);
        $this->assertSame(['Evening A', 'Morning C'], $names);
        foreach (\enrol_apply\local\groupoffer::groups($newinstanceid) as $group) {
            $this->assertEquals($newcourseid, $group->courseid);
        }
    }

    /**
     * An option outside its vocabulary in the archive restores as its default, and the restore
     * goes on.
     *
     * Changes that must make it fail: the restore writing the archive's values without
     * normalising them, which set() would then refuse, failing the whole restore.
     *
     * @return void
     */
    public function test_an_option_outside_the_vocabulary_restores_as_its_default(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application();
        \enrol_apply\local\instanceoptions::set((int) $instance->id, ['groupchoice' => 1, 'deciders' => 1]);
        // What a foreign archive can carry; the backup reads the row as it is.
        $DB->set_field('enrol_apply_options', 'deciders', 9, ['enrolid' => $instance->id]);

        $newcourseid = $this->backup_and_restore($course, false);
        $newinstanceid = (int) $DB->get_field('enrol', 'id', ['courseid' => $newcourseid, 'enrol' => 'apply'], MUST_EXIST);

        $options = \enrol_apply\local\instanceoptions::get($newinstanceid);
        $this->assertSame(\enrol_apply\local\instanceoptions::DECIDERS_GROUP, $options->deciders);
        $this->assertSame(\enrol_apply\local\instanceoptions::CHOICE_OPTIONAL, $options->groupchoice);
        $this->assertSame(9, (int) $DB->get_field('enrol_apply_options', 'deciders', ['enrolid' => $instance->id]), 'the premise');
        $this->assertNotEquals(9, (int) $DB->get_field('enrol_apply_options', 'deciders', ['enrolid' => $newinstanceid]));
    }

    /**
     * The group an applicant chose survives a restore with users, translated to the new
     * course's id; a group that did not travel restores as no choice.
     *
     * Changes that must make it fail: the column left out of the backup, or copied unmapped.
     *
     * @return void
     */
    public function test_the_chosen_group_survives_a_restore(): void {
        global $DB;

        [$course, , $applicant] = $this->create_course_with_application();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Chosen by the applicant']);
        $where = ['courseid' => $course->id, 'userid' => $applicant->id];
        $DB->set_field('enrol_apply_submission', 'chosengroupid', $group->id, $where);

        $newcourseid = $this->backup_and_restore($course, true);
        $restored = $this->restored_record($newcourseid);

        $newgroupid = (int) $DB->get_field(
            'groups',
            'id',
            ['courseid' => $newcourseid, 'name' => 'Chosen by the applicant'],
            MUST_EXIST
        );
        $this->assertNotEquals((int) $group->id, $newgroupid, 'the premise: the group really was re-created');
        $this->assertSame($newgroupid, (int) $restored->chosengroupid);

        // A group of another course never travels with this one, and restores as 0.
        $foreign = $this->getDataGenerator()->create_group(['courseid' => $this->getDataGenerator()->create_course()->id]);
        $DB->set_field('enrol_apply_submission', 'chosengroupid', $foreign->id, $where);
        $this->assertSame(0, (int) $this->restored_record($this->backup_and_restore($course, true))->chosengroupid);
    }

    /**
     * A chosen group beside a key route, which only a foreign archive carries, restores as no
     * choice and keeps the key.
     *
     * Changes that must make it fail: restoring the chosen group whatever the key route says.
     *
     * @return void
     */
    public function test_a_chosen_group_beside_a_key_route_restores_as_no_choice(): void {
        global $DB;

        [$course, , $applicant] = $this->create_course_with_application();
        $keyed = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Keyed']);
        $chosen = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Chosen']);
        $this->record_key($course, $applicant, (int) $keyed->id, submission::KEY_FOR_REVIEW);
        $where = ['courseid' => $course->id, 'userid' => $applicant->id];
        $DB->set_field('enrol_apply_submission', 'chosengroupid', $chosen->id, $where);

        $newcourseid = $this->backup_and_restore($course, true);
        $restored = $this->restored_record($newcourseid);

        $this->assertSame(0, (int) $restored->chosengroupid);
        $this->assertSame(submission::KEY_FOR_REVIEW, (int) $restored->keyoutcome);
        $this->assertSame(
            (int) $DB->get_field('groups', 'id', ['courseid' => $newcourseid, 'name' => 'Keyed'], MUST_EXIST),
            (int) $restored->keygroupid
        );
    }

    /**
     * A backup taken before the upgrade has run writes neither the new elements nor the new
     * column, and its restore leaves the new tables alone.
     *
     * The recycle bin backs every course up before deleting it, so a backup that read the new
     * schema too early failed every course deletion on a node serving this code before the upgrade
     * (measured on the mini cluster).
     *
     * Changes that must make it fail: the backup's gate dropped, or either restore handler's.
     *
     * @return void
     */
    public function test_before_the_upgrade_a_backup_and_a_restore_leave_the_new_schema_alone(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application();
        \enrol_apply\local\instanceoptions::set((int) $instance->id, ['deciders' => 1]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        \enrol_apply\local\groupoffer::set((int) $instance->id, [(int) $group->id]);
        set_config('version', ENROL_APPLY_OPTIONS_SCHEMA - 1, 'enrol_apply');

        $xml = $this->backup_enrolments_xml($course, true);
        $this->assertStringNotContainsString('<options', $xml);
        $this->assertStringNotContainsString('<offeredgroups', $xml);
        $this->assertStringNotContainsString('chosengroupid', $xml);
        // The control: the plugin's other data is still written.
        $this->assertStringContainsString('<applygroups', $xml);

        // An archive taken after the upgrade, restored on a node still before it.
        set_config('version', ENROL_APPLY_OPTIONS_SCHEMA, 'enrol_apply');
        $before = $DB->count_records('enrol_apply_options');
        $newcourseid = $this->backup_and_restore($course, false, false, null, backup::ENROL_ALWAYS, function () {
            set_config('version', ENROL_APPLY_OPTIONS_SCHEMA - 1, 'enrol_apply');
        });
        $newinstanceid = (int) $DB->get_field('enrol', 'id', ['courseid' => $newcourseid, 'enrol' => 'apply'], MUST_EXIST);
        $this->assertSame($before, $DB->count_records('enrol_apply_options'));
        $this->assertFalse($DB->record_exists('enrol_apply_offer', ['enrolid' => $newinstanceid]));
    }

    /**
     * The first decision's date travels with the record, and an archive taken before the column
     * existed restores as 0, never as the last decision's date.
     *
     * Changes that must make it fail: the element left out of the backup, or the restore not
     * writing it (gate FD5).
     *
     * @return void
     */
    public function test_the_first_decision_survives_a_restore(): void {
        global $DB;

        [$course, , $applicant] = $this->create_course_with_application();
        $where = ['courseid' => $course->id, 'userid' => $applicant->id];
        $DB->set_field('enrol_apply_submission', 'status', submission::STATUS_WAITING, $where);
        $DB->set_field('enrol_apply_submission', 'timefirstdecided', self::SUBMITTED_AT + 100, $where);
        $DB->set_field('enrol_apply_submission', 'timedecided', self::SUBMITTED_AT + 200, $where);

        $restored = $this->restored_record($this->backup_and_restore($course, true));
        $this->assertSame(self::SUBMITTED_AT + 100, (int) $restored->timefirstdecided);
        $this->assertSame(self::SUBMITTED_AT + 200, (int) $restored->timedecided);

        // An archive from before the column: no element at all.
        $strip = function (string $basepath): void {
            $this->edit_submissions($basepath, static fn(string $block): string =>
                preg_replace('#\s*<timefirstdecided>.*?</timefirstdecided>#s', '', $block));
        };
        $newcourseid = $this->backup_and_restore($course, true, false, null, backup::ENROL_ALWAYS, $strip);
        $restored = $this->restored_record($newcourseid);
        $this->assertSame(0, (int) $restored->timefirstdecided);
        $this->assertSame(self::SUBMITTED_AT + 200, (int) $restored->timedecided, 'the control: the record travelled');
    }

    /**
     * Before the upgrade that adds the first decision's column, the backup does not read it and
     * the restore does not write it.
     *
     * Changes that must make it fail: the backup's gate dropped (gate FD6), or the restore's
     * (gate FD7).
     *
     * @return void
     */
    public function test_before_the_upgrade_the_first_decision_is_left_alone(): void {
        global $DB;

        [$course, , $applicant] = $this->create_course_with_application();
        $where = ['courseid' => $course->id, 'userid' => $applicant->id];
        $DB->set_field('enrol_apply_submission', 'timefirstdecided', self::SUBMITTED_AT + 100, $where);
        set_config('version', ENROL_APPLY_FIRST_RESPONSE_SCHEMA - 1, 'enrol_apply');

        $xml = $this->backup_enrolments_xml($course, true);
        $this->assertStringNotContainsString('timefirstdecided', $xml);
        // The control: the record itself is written.
        $this->assertStringContainsString('<timedecided>', $xml);

        // An archive taken after the upgrade, restored on a node still before it.
        set_config('version', ENROL_APPLY_FIRST_RESPONSE_SCHEMA, 'enrol_apply');
        $newcourseid = $this->backup_and_restore($course, true, false, null, backup::ENROL_ALWAYS, function () {
            set_config('version', ENROL_APPLY_FIRST_RESPONSE_SCHEMA - 1, 'enrol_apply');
        });
        $restored = $this->restored_record($newcourseid);
        $this->assertSame(0, (int) $restored->timefirstdecided);
        $this->assertSame('I would like to join this course', $restored->comment, 'the control: the record travelled');
    }

    /**
     * A record restored before the upgrade has run carries no chosen group, and its key route
     * still travels.
     *
     * Before the upgrade the column does not exist; the PHPUnit site has it, which is what lets
     * this test see that the restore leaves it alone rather than counting on insert_record()
     * dropping a column the table lacks.
     *
     * Changes that must make it fail: the gate in group_route_fields() dropped.
     *
     * @return void
     */
    public function test_before_the_upgrade_a_restored_record_carries_no_chosen_group(): void {
        global $DB;

        [$course, , $applicant] = $this->create_course_with_application();
        $chosen = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Chosen']);
        $where = ['courseid' => $course->id, 'userid' => $applicant->id];
        $DB->set_field('enrol_apply_submission', 'chosengroupid', $chosen->id, $where);

        $newcourseid = $this->backup_and_restore($course, true, false, null, backup::ENROL_ALWAYS, function () {
            set_config('version', ENROL_APPLY_OPTIONS_SCHEMA - 1, 'enrol_apply');
        });
        $restored = $this->restored_record($newcourseid);

        $this->assertSame(0, (int) $restored->chosengroupid);
        // The control: the record itself travelled.
        $this->assertSame('I would like to join this course', $restored->comment);
    }

    /**
     * Rewrite the submissions block of the extracted archive, leaving every other element alone.
     *
     * @param string $basepath Path of the extracted archive.
     * @param callable $edit Takes the block's XML and returns it edited.
     * @return void
     */
    protected function edit_submissions(string $basepath, callable $edit): void {
        $path = $basepath . '/course/enrolments.xml';
        $xml = file_get_contents($path);
        $edited = preg_replace_callback('#<submissions>.*?</submissions>#s', static fn(array $m): string => $edit($m[0]), $xml);
        $this->assertNotSame($xml, $edited, 'the premise: the edit changed the archive');
        file_put_contents($path, $edited);
    }

    /**
     * Edit one applicant's submission element in the extracted archive.
     *
     * @param string $basepath Path of the extracted archive.
     * @param int $userid The applicant, as the archive names them.
     * @param callable $edit Takes the element's XML and returns it edited.
     * @return void
     */
    protected function edit_submission_of(string $basepath, int $userid, callable $edit): void {
        $this->edit_submissions($basepath, static function (string $block) use ($userid, $edit): string {
            return preg_replace_callback(
                '#<submission id="\d+">(?:(?!</submission>).)*?<userid>' . $userid . '</userid>.*?</submission>#s',
                static fn(array $m): string => $edit($m[0]),
                $block
            );
        });
    }

    /**
     * The restored record of an applicant.
     *
     * @param int $courseid Restored course id.
     * @param \stdClass $user The applicant.
     * @return \stdClass|false The record, false when none was restored.
     */
    protected function restored_record_of(int $courseid, \stdClass $user) {
        global $DB;

        return $DB->get_record('enrol_apply_submission', ['courseid' => $courseid, 'userid' => $user->id]);
    }

    /**
     * A record naming another applicant's user enrolment restores without the reference.
     *
     * The mapping is by archive id, so a crafted archive can hang a record on any enrolment this
     * restore created. The control is the other applicant, untouched, who keeps their own.
     *
     * Changes that must make it fail: the user enrolment kept without checking whose it is.
     *
     * @return void
     */
    public function test_a_record_naming_another_applicants_enrolment_loses_the_reference(): void {
        global $DB;

        [$course, $instance, $first] = $this->create_course_with_application();
        $second = $this->add_applicant($course, $instance, 'Second', 'student');
        $secondue = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $instance->id, 'userid' => $second->id]);

        $swap = function (string $basepath) use ($first, $secondue): void {
            $this->edit_submission_of($basepath, (int) $first->id, static fn(string $xml): string => preg_replace(
                '#<userenrolmentid>\d+</userenrolmentid>#',
                '<userenrolmentid>' . $secondue . '</userenrolmentid>',
                $xml
            ));
        };
        $newcourseid = $this->backup_and_restore($course, true, false, null, backup::ENROL_ALWAYS, $swap);

        $newenrolid = (int) $DB->get_field('enrol', 'id', ['courseid' => $newcourseid, 'enrol' => 'apply'], MUST_EXIST);
        $this->assertSame(0, (int) $this->restored_record_of($newcourseid, $first)->userenrolmentid);
        $this->assertSame(
            (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $newenrolid, 'userid' => $second->id], MUST_EXIST),
            (int) $this->restored_record_of($newcourseid, $second)->userenrolmentid,
            'the control: an untouched record keeps its enrolment'
        );
    }

    /**
     * A comment is restored onto an enrolment of the restored method only.
     *
     * Two apply methods in the course: the second's comment, repointed at the first's applicant
     * in the archive, would otherwise be written onto an enrolment of another method.
     *
     * Changes that must make it fail: the enrolment's method not compared with the restored one.
     *
     * @return void
     */
    public function test_a_comment_is_not_restored_onto_another_methods_enrolment(): void {
        global $DB;

        [$course, $first, $firstuser] = $this->create_course_with_application();
        $secondid = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());
        $second = $DB->get_record('enrol', ['id' => $secondid], '*', MUST_EXIST);
        $seconduser = $this->add_applicant($course, $second, 'Other', 'student');
        $firstue = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $first->id, 'userid' => $firstuser->id]);
        $secondue = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $second->id, 'userid' => $seconduser->id]);
        // Both comments are repointed at the other method's enrolment, so whichever method restores
        // second finds the other's mapping; their own rows are gone so none is skipped as a repeat.
        $DB->delete_records('enrol_apply_applicationinfo', ['userenrolmentid' => $firstue]);
        $DB->delete_records('enrol_apply_applicationinfo', ['userenrolmentid' => $secondue]);
        $DB->insert_record('enrol_apply_applicationinfo', (object) ['userenrolmentid' => $firstue, 'comment' => 'FirstMark']);
        $DB->insert_record('enrol_apply_applicationinfo', (object) ['userenrolmentid' => $secondue, 'comment' => 'OtherMark']);

        $repoint = function (string $basepath) use ($firstue, $secondue): void {
            $path = $basepath . '/course/enrolments.xml';
            $xml = file_get_contents($path);
            foreach (['FirstMark' => $secondue, 'OtherMark' => $firstue] as $mark => $target) {
                $edited = preg_replace_callback(
                    '#<application id="\d+">(?:(?!</application>).)*' . $mark . '.*?</application>#s',
                    static fn(array $m): string => preg_replace(
                        '#<userenrolmentid>\d+</userenrolmentid>#',
                        '<userenrolmentid>' . $target . '</userenrolmentid>',
                        $m[0]
                    ),
                    $xml
                );
                $this->assertNotSame($xml, $edited, 'the premise: ' . $mark . ' was repointed');
                $xml = $edited;
            }
            file_put_contents($path, $xml);
        };
        $newcourseid = $this->backup_and_restore($course, true, false, null, backup::ENROL_ALWAYS, $repoint);

        $this->assertNotEmpty($this->restored_record_of($newcourseid, $seconduser), 'the control: the record travelled');
        $this->assertSame(
            0,
            $DB->count_records_select(
                'enrol_apply_applicationinfo',
                $DB->sql_compare_text('comment') . ' IN (:a, :b)',
                ['a' => 'FirstMark', 'b' => 'OtherMark']
            ) - 2,
            'no comment is hung on the other method\'s enrolment (the two originals remain)'
        );
    }

    /**
     * A record with a status outside the vocabulary is not restored; the applicant beside it is.
     *
     * Changes that must make it fail: the status copied through.
     *
     * @return void
     */
    public function test_a_record_with_an_unknown_status_is_not_restored(): void {
        [$course, $instance, $first] = $this->create_course_with_application();
        $second = $this->add_applicant($course, $instance, 'Second', 'student');

        $forge = function (string $basepath) use ($first): void {
            $this->edit_submission_of($basepath, (int) $first->id, static fn(string $xml): string => preg_replace(
                '#<status>\d+</status>#',
                '<status>99</status>',
                $xml
            ));
        };
        $newcourseid = $this->backup_and_restore($course, true, false, null, backup::ENROL_ALWAYS, $forge);

        $this->assertFalse($this->restored_record_of($newcourseid, $first));
        $this->assertNotFalse($this->restored_record_of($newcourseid, $second), 'the control');
    }

    /**
     * A pending record restores without a decided role or groups; a waiting one keeps them.
     *
     * Changes that must make it fail: the decision columns restored for a record nobody decided.
     *
     * @return void
     */
    public function test_a_pending_record_carries_no_decision_through_a_restore(): void {
        global $DB;

        [$course, $instance, $pending] = $this->create_course_with_application();
        $waiting = $this->add_applicant($course, $instance, 'Second', 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Decided']);
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        foreach ([$pending, $waiting] as $user) {
            $DB->set_field('enrol_apply_submission', 'decidedgroups', (string) $group->id, ['userid' => $user->id]);
            $DB->set_field('enrol_apply_submission', 'decidedrole', $roleid, ['userid' => $user->id]);
            $DB->set_field('enrol_apply_submission', 'joinedgroups', (string) $group->id, ['userid' => $user->id]);
        }
        $DB->set_field('enrol_apply_submission', 'status', submission::STATUS_WAITING, ['userid' => $waiting->id]);

        $newcourseid = $this->backup_and_restore($course, true);

        $restoredpending = $this->restored_record_of($newcourseid, $pending);
        $this->assertSame('', (string) $restoredpending->decidedgroups);
        $this->assertSame(0, (int) $restoredpending->decidedrole);
        $this->assertNull($restoredpending->joinedgroups);
        $restoredwaiting = $this->restored_record_of($newcourseid, $waiting);
        $this->assertNotSame('', (string) $restoredwaiting->decidedgroups, 'the control: a waiting record keeps its choice');
        $this->assertSame($roleid, (int) $restoredwaiting->decidedrole);
        $this->assertNotNull($restoredwaiting->joinedgroups);
    }

    /**
     * An answer under a key of a shape nothing reads is not restored; the known shapes are.
     *
     * Changes that must make it fail: restored_answer_key() returning any other key as it is.
     *
     * @return void
     */
    public function test_an_answer_under_an_unknown_key_is_not_restored(): void {
        global $DB;

        [$course, , $applicant] = $this->create_course_with_application();
        $profilefield = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'kept', 'name' => 'Kept', 'visible' => PROFILE_VISIBLE_ALL,
        ]);
        $recordid = (int) $DB->get_field('enrol_apply_submission', 'id', ['userid' => $applicant->id], MUST_EXIST);
        $keys = ['s_email', 'c_' . $profilefield->id, 's_password', 's_nosuchcolumn', 'c_x', 'a_x', 'bogus', 'x_city', 's_'];
        foreach ($keys as $position => $key) {
            $DB->insert_record(submission::ANSWER_TABLE, (object) [
                'submissionid' => $recordid,
                'fieldkey' => $key,
                'label' => 'Label',
                'value' => 'Value',
                'sortorder' => 10 + $position,
            ]);
        }

        $newcourseid = $this->backup_and_restore($course, true);

        $restored = array_column(submission::read_snapshot((int) $this->restored_record($newcourseid)->id), 'key');
        $this->assertEqualsCanonicalizing(['s_city', 'a_7', 's_email', 'c_' . $profilefield->id], $restored);
    }

    /**
     * A category with no name maps to nothing, so its fields are not attached to the one before it.
     *
     * The second category is the one made nameless in the archive; without its own mapping the
     * latest parent is still the first category, which would take the second one's field.
     *
     * Changes that must make it fail: the empty category returning without registering a mapping.
     *
     * @return void
     */
    public function test_a_nameless_category_does_not_lend_its_predecessor_to_its_fields(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $areas = ['component' => 'enrol_apply', 'area' => 'application', 'itemid' => $instance->id];
        $kept = $generator->create_category(['name' => 'Kept category'] + $areas);
        $generator->create_field([
            'categoryid' => $kept->get('id'), 'type' => 'text', 'shortname' => 'inkept', 'name' => 'In kept',
        ]);
        $nameless = $generator->create_category(['name' => 'Nameless category'] + $areas);
        $generator->create_field([
            'categoryid' => $nameless->get('id'), 'type' => 'text', 'shortname' => 'innameless', 'name' => 'In nameless',
        ]);

        $blank = function (string $basepath): void {
            $path = $basepath . '/course/enrolments.xml';
            $xml = file_get_contents($path);
            $edited = str_replace('<name>Nameless category</name>', '<name></name>', $xml);
            $this->assertNotSame($xml, $edited, 'the premise: the category was blanked');
            file_put_contents($path, $edited);
        };
        $newcourseid = $this->backup_and_restore($course, true, false, null, backup::ENROL_ALWAYS, $blank);

        $newenrolid = (int) $DB->get_field('enrol', 'id', ['courseid' => $newcourseid, 'enrol' => 'apply'], MUST_EXIST);
        $shortnames = $DB->get_fieldset_sql(
            "SELECT f.shortname
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = 'enrol_apply' AND c.area = 'application' AND c.itemid = :enrolid",
            ['enrolid' => $newenrolid]
        );
        $this->assertSame(['inkept'], $shortnames);
    }

    /**
     * A restored question does not keep the text field's password switch.
     *
     * The control is another setting of the same configuration, which travels.
     *
     * Changes that must make it fail: configdata restored as the archive carries it.
     *
     * @return void
     */
    public function test_a_restored_question_loses_the_password_switch(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category([
            'name' => 'About you', 'component' => 'enrol_apply', 'area' => 'application', 'itemid' => $instance->id,
        ]);
        $field = $generator->create_field([
            'categoryid' => $category->get('id'), 'type' => 'text', 'shortname' => 'secretq', 'name' => 'Secret question',
            'configdata' => ['ispassword' => 1, 'required' => 1],
        ]);
        $stored = json_decode($DB->get_field('customfield_field', 'configdata', ['id' => $field->get('id')]), true);
        $this->assertSame(1, (int) $stored['ispassword'], 'the premise: the switch was stored');

        $newcourseid = $this->backup_and_restore($course, true);

        $restored = json_decode($DB->get_field('customfield_field', 'configdata', [
            'id' => $this->restored_question($newcourseid, 'secretq'),
        ]), true);
        $this->assertArrayNotHasKey('ispassword', $restored);
        $this->assertSame(1, (int) $restored['required']);
    }
}
