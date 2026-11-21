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
 * Tests for the orphan sweep that runs on every course deletion.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the orphan sweep that runs on every course deletion.
 *
 * hook_callbacks_test drives the sweep through the case it exists for, a course deleted while the
 * plugin is disabled. This file holds the predicates themselves: each table loses exactly the rows
 * whose parent is gone, whichever course was deleted.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(observers::class)]
final class observers_test extends \advanced_testcase {
    /**
     * Enable the plugin.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
    }

    /**
     * Deleting any course sweeps both tables' orphans and keeps every row with a live parent.
     *
     * The deleted course carries no apply instance, so nothing of this plugin belongs to it: the
     * sweep runs on every course deletion, and what it removes is decided by the parent rows alone.
     * Each orphan is made the way a real one arises, by deleting its parent row directly.
     *
     * The live rows are the control for each predicate: a sweep that emptied a table would remove
     * the orphan too, and only the live row tells the two apart. The orphans are the control the
     * other way: a predicate that matched nothing would keep the live rows as well.
     *
     * @return void
     */
    public function test_a_course_deletion_sweeps_exactly_the_orphans(): void {
        global $DB;

        $plugin = enrol_get_plugin('apply');
        $generator = $this->getDataGenerator();

        // A live application on a live instance with a configured group.
        $course = $generator->create_course();
        $instanceid = (int) $plugin->add_instance($course, $plugin->get_instance_defaults());
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        $group = $generator->create_group(['courseid' => $course->id]);
        $livegroup = (int) $DB->insert_record('enrol_apply_groups', (object) [
            'enrolid' => $instanceid,
            'groupid' => $group->id,
        ]);
        $applicant = $generator->create_user();
        $plugin->enrol_user($instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $liveueid = (int) $DB->get_field('user_enrolments', 'id', [
            'enrolid' => $instanceid,
            'userid' => $applicant->id,
        ], MUST_EXIST);
        $liveinfo = (int) $DB->insert_record('enrol_apply_applicationinfo', (object) [
            'userenrolmentid' => $liveueid,
            'comment' => 'Still mine',
        ]);

        // An applicationinfo row whose user enrolment was deleted underneath it.
        $gone = $generator->create_user();
        $plugin->enrol_user($instance, $gone->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $goneueid = (int) $DB->get_field('user_enrolments', 'id', [
            'enrolid' => $instanceid,
            'userid' => $gone->id,
        ], MUST_EXIST);
        $orphaninfo = (int) $DB->insert_record('enrol_apply_applicationinfo', (object) [
            'userenrolmentid' => $goneueid,
            'comment' => 'Nobody points here',
        ]);
        $DB->delete_records('user_enrolments', ['id' => $goneueid]);

        // A groups row whose enrol instance was deleted underneath it.
        $othercourse = $generator->create_course();
        $otherinstanceid = (int) $plugin->add_instance($othercourse, $plugin->get_instance_defaults());
        $othergroup = $generator->create_group(['courseid' => $othercourse->id]);
        $orphangroup = (int) $DB->insert_record('enrol_apply_groups', (object) [
            'enrolid' => $otherinstanceid,
            'groupid' => $othergroup->id,
        ]);
        $DB->delete_records('enrol', ['id' => $otherinstanceid]);

        // The course deleted carries no apply instance at all.
        $unrelated = $generator->create_course();
        $this->assertFalse($DB->record_exists('enrol', ['courseid' => $unrelated->id, 'enrol' => 'apply']));

        delete_course($unrelated, false);

        $this->assertFalse($DB->record_exists('enrol_apply_applicationinfo', ['id' => $orphaninfo]));
        $this->assertFalse($DB->record_exists('enrol_apply_groups', ['id' => $orphangroup]));

        $this->assertTrue($DB->record_exists('enrol_apply_applicationinfo', ['id' => $liveinfo]));
        $this->assertTrue($DB->record_exists('enrol_apply_groups', ['id' => $livegroup]));
    }

    /**
     * A user deleted while the plugin is disabled takes the comment of their application along.
     *
     * enrol_user_delete() asks only enabled plugins to unenrol the user, so with the plugin off
     * the comment survives the deletion of the enrolment it hangs off; the user_deleted observer
     * sweeps it. The other applicant's comment is the control: a sweep that emptied the table
     * would remove it too.
     *
     * Changes that must make it fail: the observer unregistered or left empty, or its sweep
     * written without the NOT EXISTS so that it empties the table.
     *
     * @return void
     */
    public function test_a_user_deleted_while_the_plugin_is_disabled_leaves_no_comment(): void {
        global $DB;

        $plugin = enrol_get_plugin('apply');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instanceid = (int) $plugin->add_instance($course, $plugin->get_instance_defaults());
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $infoof = function (\stdClass $user, string $comment) use ($plugin, $instance, $DB): int {
            $plugin->enrol_user($instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
            $ueid = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $instance->id, 'userid' => $user->id], MUST_EXIST);

            return (int) $DB->insert_record('enrol_apply_applicationinfo', (object) [
                'userenrolmentid' => $ueid,
                'comment' => $comment,
            ]);
        };
        $leaving = $generator->create_user();
        $staying = $generator->create_user();
        $leavinginfo = $infoof($leaving, 'Going away');
        $stayinginfo = $infoof($staying, 'Still here');

        // Disabled: unenrol_user() of the plugin is no longer reached by delete_user().
        set_config('enrol_plugins_enabled', '');
        delete_user($leaving);

        $this->assertFalse($DB->record_exists('enrol_apply_applicationinfo', ['id' => $leavinginfo]));
        $this->assertTrue($DB->record_exists('enrol_apply_applicationinfo', ['id' => $stayinginfo]));
    }

    /**
     * A course deletion sweeps the options and offers whose method is gone, the case a disabled
     * plugin leaves behind, and keeps the live ones.
     *
     * Changes that must make it fail: either of the two sweeps left out, or written without its
     * NOT EXISTS so that it empties the table.
     *
     * @return void
     */
    public function test_a_course_deletion_sweeps_orphaned_options_and_offers(): void {
        global $DB;

        $plugin = enrol_get_plugin('apply');
        $generator = $this->getDataGenerator();
        $make = function () use ($plugin, $generator): int {
            $course = $generator->create_course();
            $enrolid = (int) $plugin->add_instance($course, $plugin->get_instance_defaults());
            \enrol_apply\local\instanceoptions::set($enrolid, ['deciders' => 1]);
            $group = $generator->create_group(['courseid' => $course->id]);
            \enrol_apply\local\groupoffer::set($enrolid, [(int) $group->id]);
            return $enrolid;
        };
        $live = $make();
        $orphan = $make();
        // What enrol_course_delete() leaves when the plugin is disabled: the {enrol} row is gone.
        $DB->delete_records('enrol', ['id' => $orphan]);

        delete_course($generator->create_course(), false);

        $this->assertFalse($DB->record_exists('enrol_apply_options', ['enrolid' => $orphan]));
        $this->assertFalse($DB->record_exists('enrol_apply_offer', ['enrolid' => $orphan]));
        $this->assertTrue($DB->record_exists('enrol_apply_options', ['enrolid' => $live]));
        $this->assertTrue($DB->record_exists('enrol_apply_offer', ['enrolid' => $live]));
    }

    /**
     * Before the upgrade has run, deleting a method or a course leaves the two new tables alone.
     *
     * Measured on the mini cluster with the code deployed and the upgrade pending: without these
     * gates delete_instance() stops on a class core's class map does not know yet, and every course
     * deletion fails, because the recycle bin's backup reads a table that does not exist.
     *
     * Changes that must make it fail: the gate in the course_deleted sweep dropped. Dropping the one
     * in delete_instance() does not, because the two classes gate themselves; that gate stops the
     * class-not-found fatal, which only the cluster reproduces.
     *
     * @return void
     */
    public function test_before_the_upgrade_deletions_leave_the_new_tables_alone(): void {
        global $DB;

        $plugin = enrol_get_plugin('apply');
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $deleted = (int) $plugin->add_instance($course, $plugin->get_instance_defaults());
        $orphan = (int) $plugin->add_instance($course, $plugin->get_instance_defaults());
        foreach ([$deleted, $orphan] as $enrolid) {
            \enrol_apply\local\instanceoptions::set($enrolid, ['hidecard' => 1]);
            \enrol_apply\local\groupoffer::set($enrolid, [(int) $group->id]);
        }
        $DB->delete_records('enrol', ['id' => $orphan]);
        set_config('version', ENROL_APPLY_OPTIONS_SCHEMA - 1, 'enrol_apply');

        $plugin->delete_instance($DB->get_record('enrol', ['id' => $deleted], '*', MUST_EXIST));
        delete_course($this->getDataGenerator()->create_course(), false);

        $this->assertFalse($DB->record_exists('enrol', ['id' => $deleted]), 'the method itself is still deleted');
        foreach ([$deleted, $orphan] as $enrolid) {
            $this->assertTrue($DB->record_exists('enrol_apply_options', ['enrolid' => $enrolid]));
            $this->assertTrue($DB->record_exists('enrol_apply_offer', ['enrolid' => $enrolid]));
        }
    }

    /**
     * Deleting a method forgets its options and its offer, and nobody else's.
     *
     * Changes that must make it fail: either delete left out of delete_instance().
     *
     * @return void
     */
    public function test_deleting_a_method_forgets_its_options_and_offer(): void {
        global $DB;

        $plugin = enrol_get_plugin('apply');
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $ids = [];
        foreach (['gone', 'kept'] as $name) {
            $ids[$name] = (int) $plugin->add_instance($course, $plugin->get_instance_defaults());
            \enrol_apply\local\instanceoptions::set($ids[$name], ['hidecard' => 1]);
            \enrol_apply\local\groupoffer::set($ids[$name], [(int) $group->id]);
        }

        $plugin->delete_instance($DB->get_record('enrol', ['id' => $ids['gone']], '*', MUST_EXIST));

        $this->assertFalse($DB->record_exists('enrol_apply_options', ['enrolid' => $ids['gone']]));
        $this->assertFalse($DB->record_exists('enrol_apply_offer', ['enrolid' => $ids['gone']]));
        $this->assertTrue($DB->record_exists('enrol_apply_options', ['enrolid' => $ids['kept']]));
        $this->assertTrue($DB->record_exists('enrol_apply_offer', ['enrolid' => $ids['kept']]));
    }

    /**
     * Deleting any course sweeps the frozen answers whose record is gone, and keeps the rest.
     *
     * No path of this plugin leaves such a row - each deletes the answers before the record - so
     * the orphan is made by deleting the record directly, as a hand edit or another tool would.
     * Every value it holds is something an applicant typed, with nothing left to say whose.
     *
     * @return void
     */
    public function test_a_course_deletion_sweeps_answers_whose_record_is_gone(): void {
        global $DB;

        $record = function (): int {
            global $DB;

            return (int) $DB->insert_record('enrol_apply_submission', (object) [
                'courseid' => 0,
                'userid' => 0,
                'enrolid' => 0,
                'userenrolmentid' => 0,
                'comment' => '',
                'status' => local\submission::STATUS_PENDING,
                'timecreated' => time(),
            ]);
        };
        $live = $record();
        $orphaned = $record();
        foreach ([$live, $orphaned] as $id) {
            local\submission::write_answers($id, [['key' => 's_city', 'label' => 'City', 'value' => 'Recife']]);
        }
        $DB->delete_records('enrol_apply_submission', ['id' => $orphaned]);

        delete_course($this->getDataGenerator()->create_course(), false);

        $this->assertSame(0, $DB->count_records(local\submission::ANSWER_TABLE, ['submissionid' => $orphaned]));
        $this->assertSame(1, $DB->count_records(local\submission::ANSWER_TABLE, ['submissionid' => $live]));
    }

    /**
     * Insert a decided application record with one frozen answer.
     *
     * @param int $courseid Course of the record.
     * @param int $userid Applicant.
     * @param int $decidedby Decider.
     * @return int Record id.
     */
    protected function make_record(int $courseid, int $userid, int $decidedby): int {
        global $DB;

        $id = (int) $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $courseid,
            'userid' => $userid,
            'enrolid' => 7,
            'userenrolmentid' => 8,
            'comment' => 'My reason',
            'outcomemessage' => 'Welcome',
            'decisionnote' => 'Internal',
            'status' => local\submission::STATUS_APPROVED,
            'decidedby' => $decidedby,
            'timecreated' => 1000,
            'timedecided' => 2000,
        ]);
        local\submission::write_answers($id, [['key' => 's_city', 'label' => 'City', 'value' => 'Recife']]);

        return $id;
    }

    /**
     * Deleting an applicant pseudonymises their records and leaves everybody else's alone.
     *
     * Runs with the plugin disabled, since observers fire whatever the plugin's state. The second
     * applicant is the control: an update without its WHERE would blank that record too.
     *
     * Changes that must make it fail: the observer not calling pseudonymise_user(), the userid
     * branch skipped, the answers left in place, or an update written without the WHERE.
     *
     * @return void
     */
    public function test_deleting_an_applicant_pseudonymises_their_records(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $leaving = $generator->create_user();
        $staying = $generator->create_user();
        $decider = $generator->create_user();
        $mine = $this->make_record((int) $course->id, (int) $leaving->id, (int) $decider->id);
        $other = $this->make_record((int) $course->id, (int) $staying->id, (int) $decider->id);

        set_config('enrol_plugins_enabled', '');
        delete_user($leaving);

        $row = $DB->get_record('enrol_apply_submission', ['id' => $mine], '*', MUST_EXIST);
        $this->assertSame(0, (int) $row->userid);
        $this->assertSame('', $row->comment);
        $this->assertSame('', $row->outcomemessage);
        $this->assertSame('', $row->decisionnote);
        $this->assertSame((int) $decider->id, (int) $row->decidedby);
        $this->assertSame(local\submission::STATUS_APPROVED, (int) $row->status);
        $this->assertSame((int) $course->id, (int) $row->courseid);
        $this->assertSame(1000, (int) $row->timecreated);
        $this->assertSame(2000, (int) $row->timedecided);
        $this->assertSame(0, $DB->count_records(local\submission::ANSWER_TABLE, ['submissionid' => $mine]));

        $control = $DB->get_record('enrol_apply_submission', ['id' => $other], '*', MUST_EXIST);
        $this->assertSame((int) $staying->id, (int) $control->userid);
        $this->assertSame('My reason', $control->comment);
        $this->assertSame('Welcome', $control->outcomemessage);
        $this->assertSame('Internal', $control->decisionnote);
        $this->assertSame(1, $DB->count_records(local\submission::ANSWER_TABLE, ['submissionid' => $other]));
    }

    /**
     * Deleting a decider clears their name only; the applicant's data stays.
     *
     * Changes that must make it fail: the decidedby branch skipped, or the applicant's data blanked
     * by the same call.
     *
     * @return void
     */
    public function test_deleting_a_decider_clears_only_their_name(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $applicant = $generator->create_user();
        $decider = $generator->create_user();
        $bystander = $generator->create_user();
        $id = $this->make_record((int) $course->id, (int) $applicant->id, (int) $decider->id);
        $kept = $this->make_record((int) $course->id, (int) $applicant->id, (int) $bystander->id);

        delete_user($decider);

        $row = $DB->get_record('enrol_apply_submission', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(0, (int) $row->decidedby);
        $this->assertSame((int) $applicant->id, (int) $row->userid);
        $this->assertSame('My reason', $row->comment);
        $this->assertSame('Welcome', $row->outcomemessage);
        $this->assertSame('Internal', $row->decisionnote);
        $this->assertSame(1, $DB->count_records(local\submission::ANSWER_TABLE, ['submissionid' => $id]));
        $this->assertSame((int) $bystander->id, (int) $DB->get_field('enrol_apply_submission', 'decidedby', ['id' => $kept]));
    }

    /**
     * A second run, and a user with no record, change nothing.
     *
     * @return void
     */
    public function test_pseudonymising_a_user_twice_or_without_records_is_harmless(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $applicant = $generator->create_user();
        $id = $this->make_record((int) $course->id, (int) $applicant->id, 0);

        local\submission::pseudonymise_user((int) $applicant->id);
        local\submission::pseudonymise_user((int) $applicant->id);
        local\submission::pseudonymise_user((int) $generator->create_user()->id);

        $this->assertSame(0, (int) $DB->get_field('enrol_apply_submission', 'userid', ['id' => $id]));
        $this->assertSame(1, $DB->count_records('enrol_apply_submission'));
    }
}
