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
 * Tests for the application record retention sweep.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\task;

use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the application record retention sweep.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(purge_submissions::class)]
final class purge_submissions_test extends \advanced_testcase {
    /** @var int Counter giving each seeded record its own applicant. */
    protected $seeded = 0;

    /**
     * Reset the database between tests.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Write one application record.
     *
     * @param int $age How long ago it was submitted, in seconds.
     * @param int $status Status to record.
     * @param int $timedecided When it was decided, 0 for an application nobody looked at.
     * @return int Id of the new row.
     */
    protected function seed(int $age, int $status = submission::STATUS_APPROVED, int $timedecided = 0): int {
        global $DB;

        /* A distinct applicant per row. They need not be real users - the sweep never joins
           {user} - but they must differ, so that these fixtures carry no signal of their own
           when the natural key is mutated. */
        $this->seeded++;

        return (int) $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => 42,
            'userid' => $this->seeded,
            'enrolid' => 11,
            'userenrolmentid' => 0,
            'comment' => 'Seeded',
            'userinfodata' => '',
            'status' => $status,
            'outcomemessage' => '',
            'timecreated' => time() - $age,
            'timedecided' => $timedecided,
            'decidedby' => 0,
        ]);
    }

    /**
     * Run the sweep, discarding its output.
     *
     * @param purge_submissions|null $task Task to run, or null for a plain one.
     * @return int Number of rows deleted.
     */
    protected function sweep(?purge_submissions $task = null): int {
        $task = $task ?? new purge_submissions();

        return $task->purge(new \null_progress_trace());
    }

    /**
     * The sweep takes everything older than the retention period, decided or not.
     *
     * The undecided row is the point of the test: it carries timedecided = 0, so a sweep
     * written against that column would retain exactly the abandoned applications forever.
     *
     * @return void
     */
    public function test_the_sweep_removes_rows_older_than_the_retention(): void {
        global $DB;

        set_config('retentiondays', 30 * DAYSECS, 'enrol_apply');

        $olddecided = $this->seed(60 * DAYSECS, submission::STATUS_APPROVED, time() - 59 * DAYSECS);
        $oldundecided = $this->seed(60 * DAYSECS, submission::STATUS_PENDING, 0);
        $recent = $this->seed(2 * DAYSECS, submission::STATUS_PENDING, 0);

        $this->assertEquals(2, $this->sweep());

        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['id' => $olddecided]));
        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['id' => $oldundecided]));
        $this->assertTrue($DB->record_exists('enrol_apply_submission', ['id' => $recent]));
    }

    /**
     * A retention of zero keeps everything.
     *
     * @return void
     */
    public function test_retention_zero_keeps_everything(): void {
        global $DB;

        set_config('retentiondays', 0, 'enrol_apply');

        $ancient = $this->seed(10 * YEARSECS);

        $this->assertEquals(0, $this->sweep());
        $this->assertTrue($DB->record_exists('enrol_apply_submission', ['id' => $ancient]));
    }

    /**
     * The setting is read as seconds, whatever its name suggests.
     *
     * admin_setting_configduration stores seconds however the administrator sets the unit,
     * while the setting is called retentiondays. This pins the one place that reads it, so
     * that a factor of 86400 cannot creep into a delete statement.
     *
     * @return void
     */
    public function test_the_retention_setting_is_read_as_seconds(): void {
        set_config('retentiondays', 30 * DAYSECS, 'enrol_apply');
        $this->assertEquals(2592000, submission::retention_seconds());

        // A row 29 days old survives a 30-day retention; a row 31 days old does not.
        $young = $this->seed(29 * DAYSECS);
        $old = $this->seed(31 * DAYSECS);

        $this->assertEquals(1, $this->sweep());

        global $DB;
        $this->assertTrue($DB->record_exists('enrol_apply_submission', ['id' => $young]));
        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['id' => $old]));
    }

    /**
     * A negative retention is read as "keep forever" rather than as a cutoff in the future.
     *
     * @return void
     */
    public function test_a_negative_retention_keeps_everything(): void {
        global $DB;

        set_config('retentiondays', -1, 'enrol_apply');

        $ancient = $this->seed(10 * YEARSECS);

        $this->assertEquals(0, $this->sweep());
        $this->assertTrue($DB->record_exists('enrol_apply_submission', ['id' => $ancient]));
    }

    /**
     * A row that cannot be purged costs that row and nothing else.
     *
     * The failure is injected through the same method any dml_exception would surface in, so
     * what is being proven is the isolation, not the injection. Two things have to hold: the
     * sweep completes rather than aborting, and it does not spin - the cursor advances past
     * the failing row, so the next iteration cannot select it again.
     *
     * @return void
     */
    public function test_a_bad_row_does_not_abort_the_sweep(): void {
        global $DB;

        /* The harness runs each test inside a transaction of its own, and the sweep's error
           handler rolls back whatever transaction is open. Without this the handler would
           discard the fixtures below and the test would fail for a reason unrelated to what
           it checks. The cost is that the rollback branch itself is then NOT exercised here -
           with no transaction open, is_transaction_started() is false and the handler skips
           it. What this test holds is the skip-and-continue, and the cursor advancing past a
           row that refuses to go; the rollback is core's own documented remedy for a
           transaction poisoned by an exception on PostgreSQL. */
        $this->preventResetByRollback();
        set_config('retentiondays', 30 * DAYSECS, 'enrol_apply');

        $first = $this->seed(90 * DAYSECS);
        $poisoned = $this->seed(80 * DAYSECS);
        $last = $this->seed(70 * DAYSECS);

        $task = new class extends purge_submissions {
            /** @var int Row id that refuses to be purged. */
            public $poisoned = 0;

            /**
             * Fail for one row and behave normally for every other.
             *
             * @param int $id Row id to delete.
             * @return void
             */
            protected function purge_row(int $id): void {
                if ($id === $this->poisoned) {
                    throw new \dml_write_exception('injected failure');
                }
                parent::purge_row($id);
            }
        };
        $task->poisoned = $poisoned;

        $this->assertEquals(2, $this->sweep($task));

        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['id' => $first]));
        $this->assertTrue($DB->record_exists('enrol_apply_submission', ['id' => $poisoned]));
        // The control: the row AFTER the failure was still swept, so the sweep carried on.
        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['id' => $last]));
    }

    /**
     * More rows than one chunk holds are all swept.
     *
     * What this holds is that the loop iterates: a sweep that read one chunk and stopped
     * would leave the remainder behind forever. It does NOT hold the primary-key cursor -
     * with every delete succeeding, re-running the same query each time would terminate just
     * as well. The cursor earns its place on the failure path, where a row that refuses to go
     * would otherwise be selected again on every iteration until the time budget expired;
     * test_a_bad_row_does_not_abort_the_sweep is what exercises that.
     *
     * @return void
     */
    public function test_the_sweep_walks_past_the_first_chunk(): void {
        global $DB;

        set_config('retentiondays', DAYSECS, 'enrol_apply');

        $wanted = purge_submissions::CHUNK + 5;
        for ($i = 0; $i < $wanted; $i++) {
            $this->seed(10 * DAYSECS);
        }

        $this->assertEquals($wanted, $this->sweep());
        $this->assertEquals(0, $DB->count_records('enrol_apply_submission'));
    }

    /**
     * A record whose application is still in the queue is spared, however old it is.
     *
     * Nothing expires a pending application - apply() enrols with timeend = 0 so that
     * process_expirations() cannot reach it - so age alone does not make one finished. Purging
     * the record of an application a manager can still see and act on produces the one state
     * this table exists to prevent: the decision taken afterwards finds no row to stamp and is
     * recorded nowhere at all.
     *
     * @return void
     */
    public function test_a_record_backing_a_live_application_is_spared(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('retentiondays', 30 * DAYSECS, 'enrol_apply');

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        $plugin = enrol_get_plugin('apply');

        $course = $this->getDataGenerator()->create_course();
        $instanceid = $plugin->add_instance($course, $plugin->get_instance_defaults());
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $applicant = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);
        $method->invoke($plugin, $instance, $applicant->id, (object) ['applydescription' => 'Still waiting']);
        $sink->close();

        // Age the application well past the retention period.
        $old = time() - 90 * DAYSECS;
        $DB->set_field('enrol_apply_submission', 'timecreated', $old, ['userid' => $applicant->id]);

        /* The control: an equally old record whose application is NOT in the queue. Without
           it, a sweep that had simply stopped working would pass. */
        $orphan = $this->seed(90 * DAYSECS);

        $this->assertEquals(1, $this->sweep());

        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['id' => $orphan]));
        $this->assertTrue($DB->record_exists('enrol_apply_submission', ['userid' => $applicant->id]));
    }

    /**
     * Once the application leaves the queue, its record becomes sweepable again.
     *
     * The other half of the rule above: "spare it while it is live" must not turn into
     * "keep it forever".
     *
     * @return void
     */
    public function test_a_record_is_swept_once_its_application_is_decided(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('retentiondays', 30 * DAYSECS, 'enrol_apply');

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        $plugin = enrol_get_plugin('apply');
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instanceid = $plugin->add_instance($course, $plugin->get_instance_defaults());
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $applicant = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);
        $method->invoke($plugin, $instance, $applicant->id, (object) ['applydescription' => 'Decide me']);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['enrolid' => $instanceid, 'userid' => $applicant->id],
            MUST_EXIST
        );
        $plugin->confirm_enrolment([$ueid]);
        $sink->close();

        $DB->set_field('enrol_apply_submission', 'timecreated', time() - 90 * DAYSECS, [
            'userid' => $applicant->id,
        ]);

        $this->assertEquals(1, $this->sweep());
        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['userid' => $applicant->id]));
    }

    /**
     * The scheduled task is registered and names itself from a language string.
     *
     * @return void
     */
    public function test_the_task_is_registered(): void {
        $tasks = \core\task\manager::load_scheduled_tasks_for_component('enrol_apply');

        $classnames = array_map(static function (\core\task\scheduled_task $task): string {
            return get_class($task);
        }, $tasks);
        $this->assertContains(purge_submissions::class, $classnames);

        $this->assertNotEmpty((new purge_submissions())->get_name());
    }
}
