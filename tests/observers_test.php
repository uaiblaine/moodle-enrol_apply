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
}
