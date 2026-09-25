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

        /* A distinct applicant per row. They need not be real users (the sweep never joins
           {user}), but distinct values keep these tests independent of whether
           (courseid, userid) is unique. */
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
     * The undecided rows are the point of the test: they carry timedecided = 0, so a sweep
     * keyed on that column instead of timecreated either keeps the old undecided row or
     * deletes the recent one.
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
     * retentiondays is an admin_setting_configduration, which stores seconds; this pins its
     * only reader, {@see submission::retention_seconds()}.
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
     * A row that cannot be purged costs that row and nothing else, and is attempted once.
     *
     * The sweep must complete rather than abort, and must not spin: the cursor advances past a
     * failing row before the work, so no later iteration can select it again. Two rows fail. The
     * first sits between rows that purge, which holds the skip-and-continue: the row after it is
     * still swept. The second has the highest id, so no later row can carry the cursor past it;
     * only the cursor itself can. Dropping `s.id > :lastid`, or advancing the cursor after
     * purge_row() rather than before it, makes the next iteration select that row again, which
     * the attempt count reports.
     *
     * @return void
     */
    public function test_a_bad_row_does_not_abort_the_sweep(): void {
        global $DB;

        /* On PostgreSQL the harness wraps each test in a transaction, which the sweep's error
           handler would roll back together with the fixtures below. Turning that off leaves no
           transaction open, so this test holds the skip-and-continue but not the rollback
           branch. */
        $this->preventResetByRollback();
        set_config('retentiondays', 30 * DAYSECS, 'enrol_apply');

        $first = $this->seed(90 * DAYSECS);
        $poisoned = $this->seed(85 * DAYSECS);
        $after = $this->seed(80 * DAYSECS);
        $highest = $this->seed(70 * DAYSECS);
        $this->assertSame($highest, (int) $DB->get_field_sql('SELECT MAX(id) FROM {enrol_apply_submission}'));

        $task = new class extends purge_submissions {
            /** @var int A short budget, so a cursor that stops advancing fails in seconds rather than a minute. */
            public const TIME_BUDGET = 5;

            /** @var array Row ids that refuse to be purged. */
            public $poisoned = [];

            /** @var array How many times purge_row() was called for each row id. */
            public $attempts = [];

            /**
             * Count every attempt, and fail for the poisoned rows.
             *
             * @param int $id Row id to delete.
             * @return void
             */
            protected function purge_row(int $id): void {
                $this->attempts[$id] = ($this->attempts[$id] ?? 0) + 1;
                if (in_array($id, $this->poisoned, true)) {
                    throw new \dml_write_exception('injected failure');
                }
                parent::purge_row($id);
            }
        };
        $task->poisoned = [$poisoned, $highest];

        $this->assertEquals(2, $this->sweep($task));

        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['id' => $first]));
        $this->assertTrue($DB->record_exists('enrol_apply_submission', ['id' => $poisoned]));
        $this->assertTrue($DB->record_exists('enrol_apply_submission', ['id' => $highest]));
        // The control: the row after the first failure was still swept, so the sweep carried on.
        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['id' => $after]));

        $this->assertSame(1, $task->attempts[$poisoned] ?? 0, 'a failing row was selected again');
        $this->assertSame(1, $task->attempts[$highest] ?? 0, 'the cursor did not move past the last failing row');
    }

    /**
     * More rows than one chunk holds are all swept.
     *
     * This holds that the loop iterates. It does not hold the primary-key cursor: with every
     * delete succeeding, re-running the same query would terminate as well. The cursor matters
     * on the failure path; see test_a_bad_row_does_not_abort_the_sweep.
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
     * Nothing expires a pending application (apply() enrols with timeend = 0), so age alone
     * does not make one finished. Purging its record would lose the profile snapshot the
     * applicant submitted, which no later decision can reconstruct
     * ({@see \enrol_apply\local\submission::ensure()}).
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

        /* The control: an equally old record whose application is not in the queue. Without
           it, a sweep that had simply stopped working would pass. */
        $orphan = $this->seed(90 * DAYSECS);

        $this->assertEquals(1, $this->sweep());

        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['id' => $orphan]));
        $this->assertTrue($DB->record_exists('enrol_apply_submission', ['userid' => $applicant->id]));
    }

    /**
     * An expired enrolment does not keep its record alive forever.
     *
     * Under a suspend expiredaction, process_expirations() re-suspends a lapsed enrolment, so
     * on status alone it looks like an application still waiting. The timeend clause of the
     * queue's predicate, which the sweep shares
     * ({@see \enrol_apply\local\queue::awaiting_decision_where()}), is what lets its record go.
     *
     * @return void
     */
    public function test_a_record_whose_enrolment_expired_is_not_spared(): void {
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

        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);

        // One whose enrolment was approved and has since lapsed: suspended, timeend in the past.
        $lapsed = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $method->invoke($plugin, $instance, $lapsed->id, (object) ['applydescription' => 'Long finished']);
        // The control: one still genuinely awaiting a decision, which must survive.
        $pending = $this->getDataGenerator()->create_user();
        $method->invoke($plugin, $instance, $pending->id, (object) ['applydescription' => 'Still waiting']);
        $sink->close();

        $DB->set_field(
            'user_enrolments',
            'timeend',
            time() - DAYSECS,
            ['userid' => $lapsed->id, 'enrolid' => $instance->id]
        );

        $old = time() - 90 * DAYSECS;
        $DB->set_field('enrol_apply_submission', 'timecreated', $old, ['userid' => $lapsed->id]);
        $DB->set_field('enrol_apply_submission', 'timecreated', $old, ['userid' => $pending->id]);

        $this->assertEquals(1, $this->sweep());

        $this->assertFalse(
            $DB->record_exists('enrol_apply_submission', ['userid' => $lapsed->id]),
            'the record of a lapsed enrolment was kept as though it were still awaiting a decision'
        );
        $this->assertTrue($DB->record_exists('enrol_apply_submission', ['userid' => $pending->id]));
    }

    /**
     * Once the application leaves the queue, its record becomes sweepable again.
     *
     * The other half of the rule above: "spare it while it is live" must not turn into
     * "keep it forever".
     *
     * It also pins that a decision does not restart the retention clock: the application is
     * decided moments before the sweep, and its record goes at once because it was submitted
     * longer ago than the retention period. That is the behaviour
     * {@see purge_submissions::purge()} documents as deliberate; a sweep counting from the
     * decision would keep the record and fail here.
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
        $decidedat = time();
        $plugin->confirm_enrolment([$ueid]);
        $sink->close();

        $DB->set_field('enrol_apply_submission', 'timecreated', time() - 90 * DAYSECS, [
            'userid' => $applicant->id,
        ]);

        // The precondition: the record carries a decision taken just now.
        $record = $DB->get_record('enrol_apply_submission', ['userid' => $applicant->id], '*', MUST_EXIST);
        $this->assertEquals(submission::STATUS_APPROVED, (int) $record->status);
        $this->assertGreaterThanOrEqual($decidedat, (int) $record->timedecided);

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
