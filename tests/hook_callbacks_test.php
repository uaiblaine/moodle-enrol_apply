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
 * Tests for what course deletion does to the durable application trail.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for what course deletion does to the durable application trail.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hook_callbacks::class)]
#[CoversClass(observers::class)]
final class hook_callbacks_test extends \advanced_testcase {
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
     * Create a course with an apply instance, one configured group and one live application.
     *
     * @param string $comment Comment submitted with the application.
     * @return array Course record, instance record, applicant record and user enrolment id.
     */
    protected function create_course_with_application(string $comment = 'Please let me in'): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instanceid = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $instanceid, 'groupid' => $group->id]);

        $applicant = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);
        $method->invoke($this->plugin, $instance, $applicant->id, (object) [
            'applydescription' => $comment,
            'city' => 'Recife',
        ]);
        $sink->close();

        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['enrolid' => $instanceid, 'userid' => $applicant->id],
            MUST_EXIST
        );

        return [$course, $instance, $applicant, $ueid];
    }

    /**
     * Deleting a course strips its trail of everything personal and keeps the rest.
     *
     * @return void
     */
    public function test_before_course_deleted_pseudonymises_the_rows(): void {
        global $DB;

        // Ask for a profile field, so the snapshot is not empty and its discarding is testable.
        [$course, $instance, $applicant, $ueid] = $this->create_course_with_application('Let me in please');
        $DB->set_field('enrol', 'customtext4', local\fieldset::from_keys(['s_city'])->to_json(), [
            'id' => $instance->id,
        ]);

        /* Decide it first. Without this the row's decidedby is 0 from the moment it is
           written, so asserting it is 0 afterwards would hold with the pseudonymisation
           deleted - the assertion would be watching a column nothing had ever set. */
        $decider = $this->getDataGenerator()->create_user();
        // The decider needs the capability: can_manage_application() authorises every row.
        $this->getDataGenerator()->enrol_user(
            $decider->id,
            $course->id,
            $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST)
        );
        $this->setUser($decider);
        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment([$ueid]);
        $sink->close();
        $this->setAdminUser();

        /* The control: an application in a different course, which the deletion must not
           touch. Without it a callback that emptied the whole table would pass. */
        [$othercourse, , $otherapplicant] = $this->create_course_with_application('Another course');

        $before = $DB->get_record('enrol_apply_submission', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertNotEmpty($before->comment);
        // The preconditions the assertions after the deletion are only meaningful against.
        $this->assertEquals($applicant->id, (int) $before->userid);
        $this->assertEquals($decider->id, (int) $before->decidedby);
        $this->assertNotEmpty($before->userinfodata);

        delete_course($course, false);

        $after = $DB->get_record('enrol_apply_submission', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertEquals(0, (int) $after->userid);
        $this->assertEquals(0, (int) $after->decidedby);
        $this->assertSame('', (string) $after->comment);
        $this->assertSame('', (string) $after->userinfodata);

        // What an audit keeps: the shape of the application, which identifies nobody.
        $this->assertEquals($before->status, $after->status);
        $this->assertEquals($before->timecreated, $after->timecreated);
        $this->assertEquals($before->enrolid, $after->enrolid);

        $untouched = $DB->get_record('enrol_apply_submission', ['courseid' => $othercourse->id], '*', MUST_EXIST);
        $this->assertEquals($otherapplicant->id, (int) $untouched->userid);
        $this->assertSame('Another course', (string) $untouched->comment);
    }

    /**
     * The snapshot really was there to be discarded.
     *
     * Without this the assertion above that userinfodata is empty would hold on a trail that
     * had never captured anything, and the discard would be unproven.
     *
     * @return void
     */
    public function test_the_snapshot_is_captured_before_it_is_discarded(): void {
        global $DB;

        [, $instance] = $this->create_course_with_application();
        $DB->set_field('enrol', 'customtext4', local\fieldset::from_keys(['s_city'])->to_json(), [
            'id' => $instance->id,
        ]);
        $reloaded = $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST);

        $applicant = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);
        $method->invoke($this->plugin, $reloaded, $applicant->id, (object) [
            'applydescription' => 'With a city',
            'city' => 'Recife',
        ]);
        $sink->close();

        $row = $DB->get_record('enrol_apply_submission', ['userid' => $applicant->id], '*', MUST_EXIST);
        $snapshot = submission::read_snapshot($row->userinfodata);
        $this->assertCount(1, $snapshot);
        $this->assertSame('s_city', $snapshot[0]['key']);
        $this->assertSame('Recife', $snapshot[0]['value']);
    }

    /**
     * Two applicants in one deleted course pseudonymise to two rows, not to a key violation.
     *
     * This is the measured reason the natural key is not unique, kept as a test so that
     * reinstating it - which the plan for this slice asked for - fails here rather than in
     * production on the first course deletion with two applicants.
     *
     * @return void
     */
    public function test_a_deleted_course_with_two_applicants_keeps_both_rows(): void {
        global $DB;

        [$course, $instance] = $this->create_course_with_application('First');

        $second = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);
        $method->invoke($this->plugin, $instance, $second->id, (object) ['applydescription' => 'Second']);
        $sink->close();

        $this->assertCount(2, $DB->get_records('enrol_apply_submission', ['courseid' => $course->id]));

        delete_course($course, false);

        $rows = $DB->get_records('enrol_apply_submission', ['courseid' => $course->id]);
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertEquals(0, (int) $row->userid);
        }
    }

    /**
     * With the plugin disabled, the observer clears up what core orphaned.
     *
     * enrol_course_delete() resolves its plugin objects from enrol_get_plugins(true), so a
     * disabled plugin's delete_instance() never runs - yet core deletes the enrol and
     * user_enrolments rows anyway, leaving the two tables that key off them pointing at
     * nothing. Event observers are registered whatever the plugin's state, which is the only
     * reason this is reachable at all.
     *
     * @return void
     */
    public function test_the_course_deleted_observer_cleans_up_when_the_plugin_is_disabled(): void {
        global $DB;

        [$course, $instance, , $ueid] = $this->create_course_with_application();

        /* The control, and it carries the whole test: the observer sweeps orphans rather
           than rows of one course, so without a live application elsewhere a callback that
           emptied both tables outright would pass. */
        [, $otherinstance, , $otherueid] = $this->create_course_with_application();

        $enabled = enrol_get_plugins(true);
        unset($enabled['apply']);
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        delete_course($course, false);

        // The precondition: core really did skip the plugin and delete the rows itself.
        $this->assertFalse($DB->record_exists('enrol', ['id' => $instance->id]));
        $this->assertFalse($DB->record_exists('user_enrolments', ['id' => $ueid]));

        $this->assertFalse($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $ueid]));
        $this->assertFalse($DB->record_exists('enrol_apply_groups', ['enrolid' => $instance->id]));

        $this->assertTrue($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $otherueid]));
        $this->assertTrue($DB->record_exists('enrol_apply_groups', ['enrolid' => $otherinstance->id]));
    }

    /**
     * The pseudonymisation comes from the hook, not from the course_deleted event.
     *
     * Both fire inside delete_course(), so the row ends up looking the same whichever one did
     * the work - which means every other test here passes just as well with the call moved to
     * the observer. Redirecting the hook is what tells them apart: with it silenced, a course
     * deletion that still pseudonymises proves the work is being done from the event, after
     * context_helper::delete_instance() has destroyed the course context.
     *
     * That distinction is the whole reason for the hook. The event route runs too late for a
     * privacy provider to reach the row, and core swallows an observer exception with nothing
     * but a debugging() call, so a failure there would leave real user ids behind in silence.
     *
     * @return void
     */
    public function test_the_pseudonymisation_runs_from_the_hook_and_not_from_the_event(): void {
        global $DB;

        [$course, , $applicant] = $this->create_course_with_application('Still named');

        $this->redirectHook(\core_course\hook\before_course_deleted::class, static function (): void {
            // Deliberately nothing: the only route left is the course_deleted event.
        });
        delete_course($course, false);
        $this->stopHookRedirections();

        $row = $DB->get_record('enrol_apply_submission', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertEquals(
            $applicant->id,
            (int) $row->userid,
            'with the hook silenced nothing may pseudonymise the row, or the work is being done too late'
        );
        $this->assertSame('Still named', (string) $row->comment);
    }

    /**
     * The trail of a course deleted while the plugin is disabled is pseudonymised, not swept.
     *
     * Hook callbacks are loaded from every installed plugin whatever its state, so the
     * pseudonymisation runs in both cases; the observer deliberately leaves the trail alone.
     *
     * @return void
     */
    public function test_the_trail_is_pseudonymised_even_while_the_plugin_is_disabled(): void {
        global $DB;

        [$course] = $this->create_course_with_application();

        $enabled = enrol_get_plugins(true);
        unset($enabled['apply']);
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        delete_course($course, false);

        $row = $DB->get_record('enrol_apply_submission', ['courseid' => $course->id], '*', MUST_EXIST);
        $this->assertEquals(0, (int) $row->userid);
        $this->assertSame('', (string) $row->comment);
    }
}
