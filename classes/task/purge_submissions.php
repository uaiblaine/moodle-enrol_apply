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

namespace enrol_apply\task;

use enrol_apply\local\submission;
use progress_trace;

/**
 * Deletes application records that have outlived the configured retention period.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_submissions extends \core\task\scheduled_task {
    /** @var int Rows read per iteration. */
    public const CHUNK = 200;

    /** @var int Seconds this task allows itself before leaving the rest to the next run. */
    public const TIME_BUDGET = 60;

    /**
     * Name shown for this task in the scheduled tasks admin screen.
     *
     * @return string Task name.
     */
    public function get_name() {
        return get_string('purgesubmissionstask', 'enrol_apply');
    }

    /**
     * Run the retention sweep.
     *
     * @return void
     */
    public function execute() {
        $this->purge(new \text_progress_trace());
    }

    /**
     * Delete every application record older than the retention period.
     *
     * Four things about how this is written are load bearing.
     *
     * It sweeps on timecreated, not timedecided. An application nobody ever got round to
     * deciding carries timedecided = 0, so a sweep on that column would retain exactly the
     * abandoned applications forever - the opposite of what a retention period is for.
     *
     * It nevertheless spares a record whose application is STILL IN THE QUEUE. Nothing ever
     * expires a pending application - apply() enrols with timeend = 0 precisely so that
     * process_expirations() cannot reach it - so an application older than the retention is
     * a live one a manager can still act on, not an old one. Deleting its record while
     * leaving it on screen produces the one state the design must not reach: the decision
     * taken tomorrow would find no row to stamp and would be recorded nowhere at all. The
     * record becomes sweepable the moment the application leaves the queue, by any route.
     *
     * It walks forward on the primary key rather than re-running the same query. A row that
     * refuses to go would otherwise be selected again on the next iteration, and the loop
     * would never end.
     *
     * It bounds itself. Core's cron only checks its own wall-clock ceiling BETWEEN tasks
     * (lib/classes/cron.php), so an unbounded sweep does not get interrupted - it consumes
     * the whole scheduled-task window and starves everything queued behind it.
     *
     * And it never throws. One malformed row must cost that row, not the retention of every
     * other row on the site.
     *
     * @param progress_trace $trace Where progress is reported.
     * @return int Number of rows deleted.
     */
    public function purge(progress_trace $trace): int {
        global $DB;

        $retention = submission::retention_seconds();
        if ($retention === 0) {
            $trace->output('enrol_apply: application records are kept forever, nothing to purge.');
            return 0;
        }

        $cutoff = time() - $retention;
        $deadline = time() + static::TIME_BUDGET;
        $lastid = 0;
        $deleted = 0;
        $skipped = 0;

        while (time() < $deadline) {
            $rows = $DB->get_records_sql(
                "SELECT s.id
                   FROM {enrol_apply_submission} s
                  WHERE s.timecreated < :cutoff AND s.id > :lastid
                        AND NOT EXISTS (
                            SELECT 1
                              FROM {user_enrolments} ue
                              JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = :enrol
                             WHERE ue.id = s.userenrolmentid AND ue.status <> :active
                                   AND (ue.timeend = 0 OR ue.timeend > :now)
                        )
               ORDER BY s.id ASC",
                [
                    'cutoff' => $cutoff,
                    'lastid' => $lastid,
                    'enrol' => 'apply',
                    'active' => ENROL_USER_ACTIVE,
                    'now' => time(),
                ],
                0,
                static::CHUNK
            );
            if (!$rows) {
                break;
            }

            foreach ($rows as $row) {
                // Advanced before the work, so a row that cannot be purged is not retried forever.
                $lastid = (int) $row->id;
                try {
                    $this->purge_row((int) $row->id);
                    $deleted++;
                } catch (\Throwable $e) {
                    $skipped++;
                    /* Reported through the trace rather than through mtrace_exception(), so
                       that the task has exactly one output channel: a caller passing a
                       null_progress_trace gets silence, and the scheduled run still writes
                       to the task log because text_progress_trace goes to mtrace anyway. */
                    $trace->output(
                        'enrol_apply: skipping submission ' . $row->id . ', it could not be purged: '
                            . $e->getMessage()
                    );
                    /* An exception raised inside a transaction poisons it on PostgreSQL:
                       every later statement fails until it is rolled back, so without this
                       one bad row would take the rest of the sweep with it. */
                    if ($DB->is_transaction_started()) {
                        $DB->force_transaction_rollback();
                    }
                }
            }
        }

        $trace->output(
            'enrol_apply: purged ' . $deleted . ' application record(s) older than '
                . format_time($retention) . ', skipped ' . $skipped . '.'
        );

        return $deleted;
    }

    /**
     * Delete one application record.
     *
     * Its own method so that a test can make a single row fail and prove that the sweep
     * carries on. The isolation it demonstrates is not test-only: any dml_exception raised
     * here reaches the same handler.
     *
     * @param int $id Row id to delete.
     * @return void
     */
    protected function purge_row(int $id): void {
        global $DB;

        $DB->delete_records('enrol_apply_submission', ['id' => $id]);
    }
}
