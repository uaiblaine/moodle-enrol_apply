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
     * Age is measured from timecreated, the submission date the retention setting is defined
     * against and the one date every record carries; timedecided stays 0 on a record that was
     * never decided, so it cannot date those.
     *
     * It never deletes a record whose application is still awaiting a decision, pending or
     * deferred, whatever its age. Nothing expires such an application (apply() and
     * wait_enrolment() both leave timeend at 0), so an old one is still live, and its record
     * holds the profile details the review page shows, which no other table keeps. The
     * plugin's own decision methods would rebuild a missing record through
     * submission::ensure(), but without those details, and an approval from core's "Edit
     * enrolment" screen, which reaches only complete_approval() and submission::decide(), would
     * be recorded nowhere. The record becomes sweepable as soon as the application leaves the
     * queue, by any route.
     *
     * It walks forward on the primary key, so a row that cannot be deleted is not selected
     * again, and it stops after TIME_BUDGET seconds because core's cron checks
     * task_scheduled_max_runtime only between tasks ({@see \core\cron::run_scheduled_tasks()}).
     * A row that fails to delete is skipped and reported rather than aborting the sweep.
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

        /* The queue's own predicate, not a copy of it, so "still awaiting a decision" means
           here exactly what it means on the approval queue. */
        [$awaitingwheres, $awaitingparams] = \enrol_apply\local\queue::awaiting_decision_where();
        $awaiting = implode(' AND ', $awaitingwheres);

        while (time() < $deadline) {
            $rows = $DB->get_records_sql(
                "SELECT s.id
                   FROM {enrol_apply_submission} s
                  WHERE s.timecreated < :cutoff AND s.id > :lastid
                        AND NOT EXISTS (
                            SELECT 1
                              FROM {user_enrolments} ue
                              JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = :enrol
                             WHERE ue.id = s.userenrolmentid AND {$awaiting}
                        )
               ORDER BY s.id ASC",
                [
                    'cutoff' => $cutoff,
                    'lastid' => $lastid,
                    'enrol' => 'apply',
                ] + $awaitingparams,
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
                    /* Reported through the trace rather than mtrace_exception(), so a caller
                       passing a null_progress_trace gets silence while the scheduled run's
                       text_progress_trace still reaches the task log. */
                    $trace->output(
                        'enrol_apply: skipping submission ' . $row->id . ', it could not be purged: '
                            . $e->getMessage()
                    );
                    /* An exception inside a transaction poisons it on PostgreSQL: every later
                       statement fails until it is rolled back. */
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
     * A separate method so a test can make a single row fail and check that the sweep
     * carries on; any exception raised here reaches the same per-row handler in purge().
     *
     * @param int $id Row id to delete.
     * @return void
     */
    protected function purge_row(int $id): void {
        global $DB;

        $DB->delete_records('enrol_apply_submission', ['id' => $id]);
    }
}
