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

namespace enrol_apply\local;

use stdClass;

/**
 * The durable record of one enrolment application.
 *
 * Everything else the plugin owns is deleted at the moment it acquires audit value:
 * enrol_apply_applicationinfo is dropped on approval (lib.php), on cancellation and in
 * unenrol_user(), and the user_enrolments row it hangs off goes with the enrolment. This
 * table is the one that stays, so it is keyed by courseid and userid rather than by any of
 * those - userenrolmentid rides along as a reference and is never the key to a deletion.
 *
 * Two properties of that key are worth stating, because both look like defects:
 *
 *  - It is NOT unique. The design pseudonymises on course deletion by zeroing userid, so a
 *    deleted course with two applicants produces two rows with the same courseid and
 *    userid = 0. A unique key raises dml_write_exception on the second one - measured, not
 *    reasoned. Cancelling and re-applying legitimately produces a second row too, so does
 *    restoring a course into one that already holds the trail, and so does a course carrying
 *    two apply instances - which the plugin supports on purpose. The invariant that IS
 *    enforced is narrower than the key would have been: "one live application per enrol
 *    INSTANCE and user", by the lock in enrol_apply_plugin::submit_application(), which is
 *    keyed on the instance id and the user and guards a user_enrolments lookup by enrolid.
 *    Nothing anywhere enforces one per course and user, so even without pseudonymisation the
 *    key could not have been unique.
 *  - Only userid and decidedby carry a foreign key, and neither courseid, enrolid nor
 *    userenrolmentid does. A foreign key here is documentation and an index - Moodle's
 *    generators emit no database-level constraint (lib/ddl/sql_generator.php,
 *    $foreign_keys = false) - so declaring one for a reference the design deliberately
 *    outlives would document an integrity claim that is false by construction. The two
 *    user columns are different: they are never left dangling, because they are zeroed on
 *    course deletion and the whole row goes on erasure.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission {
    /** @var int Submitted, no decision taken yet. */
    public const STATUS_PENDING = 0;

    /** @var int Approved: the enrolment was activated. */
    public const STATUS_APPROVED = 1;

    /** @var int Deferred to the waiting list. */
    public const STATUS_WAITING = 2;

    /** @var int Cancelled: the applicant was unenrolled. */
    public const STATUS_CANCELLED = 3;

    /**
     * The whole state vocabulary, in the order a report should offer it.
     *
     * @var array
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_WAITING,
        self::STATUS_CANCELLED,
    ];

    /** @var int Format of the userinfodata envelope. Bumped only if the stored shape changes. */
    public const SNAPSHOT_VERSION = 1;

    /**
     * Record a new application.
     *
     * Called from enrol_apply_plugin::apply(), inside the lock that serialises submissions for
     * one instance and user, so two concurrent applications cannot both write a row.
     *
     * That lock gives mutual exclusion, NOT atomicity, and the difference matters: the
     * enrolment, the applicationinfo row and this one are three separately committed writes
     * with no transaction around them, so a failure part way through can leave an enrolment
     * with no record behind it. That is why decide() leaves a row it cannot find alone rather
     * than treating its absence as impossible.
     *
     * @param stdClass $instance Course enrol instance applied to.
     * @param int $userid Applicant.
     * @param int $userenrolmentid User enrolment created for the application.
     * @param stdClass $data Submitted application form data.
     * @return int Id of the new row.
     */
    public static function create(stdClass $instance, int $userid, int $userenrolmentid, stdClass $data): int {
        global $DB;

        return (int) $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => (int) $instance->courseid,
            'userid' => $userid,
            'enrolid' => (int) $instance->id,
            'userenrolmentid' => $userenrolmentid,
            'comment' => isset($data->applydescription) ? (string) $data->applydescription : '',
            'userinfodata' => self::snapshot($instance, $data),
            'status' => self::STATUS_PENDING,
            /* Written empty on purpose and never read in this slice. The column ships now
               because schema churn is the expensive part; the screen that lets a decider
               write a message to the applicant arrives later and is its only writer. */
            'outcomemessage' => '',
            'timecreated' => time(),
            'timedecided' => 0,
            'decidedby' => 0,
        ]);
    }

    /**
     * Stamp the decision taken on an application.
     *
     * Matched on userenrolmentid, which is the only reference that identifies one
     * application rather than one applicant: a user who applies, is cancelled and applies
     * again has two rows for the same course. A row that cannot be found is left alone
     * rather than guessed at - applications that predate this table, and the ones tests
     * create by calling enrol_user() directly, have none.
     *
     * Idempotent: complete_approval() runs twice for every approval made through the
     * plugin's own queue (once from the before_user_enrolment_updated hook, once from
     * confirm_enrolment()), and a row already at the target status is not restamped, so
     * timedecided records the first decision rather than the last write.
     *
     * @param int $userenrolmentid User enrolment the decision applies to.
     * @param int $status One of the STATUS_ constants.
     * @param int $decidedby User who took the decision.
     * @return void
     */
    public static function decide(int $userenrolmentid, int $status, int $decidedby): void {
        global $DB;

        if (!in_array($status, self::STATUSES, true)) {
            throw new \coding_exception('Unknown enrol_apply submission status: ' . $status);
        }

        $rows = $DB->get_records('enrol_apply_submission', ['userenrolmentid' => $userenrolmentid], '', 'id, status');
        foreach ($rows as $row) {
            if ((int) $row->status === $status) {
                continue;
            }
            $DB->update_record('enrol_apply_submission', (object) [
                'id' => $row->id,
                'status' => $status,
                'timedecided' => time(),
                'decidedby' => $decidedby,
            ]);
        }
    }

    /**
     * Discard everything personal in the applications of one course, keeping the shape.
     *
     * Runs from \core_course\hook\before_course_deleted and nowhere else. It has to be that
     * hook rather than the course_deleted event, and the reason is not a preference:
     * contextlist::add_from_sql() wraps every provider query in a JOIN against {context}
     * (privacy/classes/local/request/contextlist.php), and delete_course() destroys the
     * course context before the event fires (lib/moodlelib.php, context_helper::delete_instance
     * then the trigger). A row that kept a real userid past that point would be personal data
     * that no subject access request can reach and no erasure request can delete - silently.
     *
     * What survives is what an audit needs, with both user columns zeroed: the dates, the
     * status, and the course, enrol and user enrolment ids.
     *
     * That is pseudonymisation and not anonymisation, and the distinction is not pedantry.
     * userenrolmentid is retained, and logstore_standard records enrolment events with
     * objecttable = 'user_enrolments', objectid = that same id and the userid alongside - so
     * on a site keeping its standard log, a retained row can still be re-attached to a person
     * by joining it. The row is stripped of everything that identifies somebody directly; it
     * is not beyond re-identification by an administrator with the logs.
     *
     * @param int $courseid Course being deleted.
     * @return void
     */
    public static function pseudonymise(int $courseid): void {
        global $DB;

        $DB->execute(
            "UPDATE {enrol_apply_submission}
                SET userid = 0, decidedby = 0, comment = ?, userinfodata = ?, outcomemessage = ?
              WHERE courseid = ?",
            ['', '', '', $courseid]
        );
    }

    /**
     * The retention period, in seconds, after which decided and abandoned applications go.
     *
     * The single reader of the setting, and the reason it exists as a method: the setting is
     * an admin_setting_configduration, which stores SECONDS whatever unit the administrator
     * picks in the dropdown (lib/adminlib.php), while its name says days. Reading it raw
     * somewhere else is how a factor of 86400 gets into a delete statement.
     *
     * @return int Retention in seconds, or 0 when records are kept forever.
     */
    public static function retention_seconds(): int {
        return max(0, (int) get_config('enrol_apply', 'retentiondays'));
    }

    /**
     * Freeze what the applicant typed into the profile fields the instance asked for.
     *
     * The label is stored beside the key on purpose. A custom profile field can be renamed,
     * and a snapshot that resolved its label at read time would then show a question that was
     * never asked; the key alone is stored as well so a later reader can still tell which
     * field it was. The value is stored exactly as submitted - see
     * fields::submitted_values() for why it is not put through format_string().
     *
     * @param stdClass $instance Enrol instance the application was submitted to.
     * @param stdClass $data Submitted form data.
     * @return string JSON envelope, or an empty string when nothing was submitted.
     */
    public static function snapshot(stdClass $instance, stdClass $data): string {
        $values = fields::submitted_values($instance, $data);
        if (!$values) {
            return '';
        }

        return (string) json_encode([
            'version' => self::SNAPSHOT_VERSION,
            'fields' => $values,
        ]);
    }

    /**
     * The frozen field values of a submission, ready to display.
     *
     * Every failure mode returns an empty list rather than throwing: the column can hold an
     * envelope written by a newer version of this plugin, restored from another site.
     *
     * @param string|null $json Stored envelope.
     * @return array List of arrays carrying 'key', 'label' and 'value' string entries.
     */
    public static function read_snapshot(?string $json): array {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || (int) ($decoded['version'] ?? 0) !== self::SNAPSHOT_VERSION) {
            return [];
        }
        if (!isset($decoded['fields']) || !is_array($decoded['fields'])) {
            return [];
        }

        $entries = [];
        foreach ($decoded['fields'] as $entry) {
            if (!is_array($entry) || !isset($entry['value'])) {
                continue;
            }

            /* Every part is checked for being scalar BEFORE it is cast, and that is not
               belt-and-braces. This envelope is JSON that another site wrote and a restore
               copied in verbatim, so a 'value' holding an array or a nested object is
               reachable - and (string) on one emits "Array to string conversion", a PHP
               warning, which --fail-on-warning turns into a failed run. Measured on m502: the
               cast yields the literal string "Array", so the reader is shown a word the
               applicant never typed and nothing anywhere says so.
               The whole entry is dropped rather than repaired. A key that is not a string
               cannot be matched against the visible-key list the snapshot formatter masks with,
               and an entry that cannot be masked correctly must not be rendered at all. */
            $key = $entry['key'] ?? '';
            $label = $entry['label'] ?? '';
            if (!is_scalar($entry['value']) || !is_scalar($key) || !is_scalar($label)) {
                continue;
            }

            $entries[] = [
                'key' => (string) $key,
                'label' => (string) $label,
                'value' => (string) $entry['value'],
            ];
        }

        return $entries;
    }

    /**
     * Record the message the decider wrote to the applicant.
     *
     * Kept apart from decide() rather than folded into it, and that separation is the whole
     * point rather than tidiness. complete_approval() runs TWICE for an approval taken through
     * the queue: enrol_plugin::update_user_enrol() dispatches
     * \core_enrol\hook\before_user_enrolment_updated before it writes the row, so
     * hook_callbacks reaches complete_approval() first - and that call has no message, because
     * the hook carries none. decide() then skips any row already at the target status, so a
     * message threaded through it on the SECOND call would be dropped in silence, with the
     * status looking perfectly correct.
     *
     * Written before the decision for the same reason the decision is written before the
     * unenrolment: unenrol_user() deletes the user_enrolments row, and the id it carried is how
     * these rows are matched.
     *
     * @param int $userenrolmentid User enrolment the decision applies to.
     * @param string $message What the decider typed, empty for none.
     * @return void
     */
    public static function record_outcome_message(int $userenrolmentid, string $message): void {
        global $DB;

        if (trim($message) === '') {
            return;
        }

        $rows = $DB->get_records('enrol_apply_submission', ['userenrolmentid' => $userenrolmentid], '', 'id');
        foreach ($rows as $row) {
            $DB->update_record('enrol_apply_submission', (object) [
                'id' => $row->id,
                'outcomemessage' => $message,
            ]);
        }
    }

    /**
     * The message the decider wrote, for the notification that announces the decision.
     *
     * Read from the record rather than passed along, which is what lets one lookup serve all
     * three decisions AND the approval notification - that one is sent from an adhoc task, long
     * after any parameter would have gone out of scope.
     *
     * @param int $userenrolmentid User enrolment the decision applies to.
     * @return string The message, empty when none was written.
     */
    public static function outcome_message(int $userenrolmentid): string {
        global $DB;

        $rows = $DB->get_records(
            'enrol_apply_submission',
            ['userenrolmentid' => $userenrolmentid],
            'timecreated DESC, id DESC',
            'id, outcomemessage',
            0,
            1
        );
        $row = reset($rows);

        return $row ? (string) $row->outcomemessage : '';
    }

    /**
     * The language string naming a status.
     *
     * A literal per branch, never get_string('status_' . $status): a dynamic string id is
     * banned by the fleet coding standard and invisible to the lang file checker.
     *
     * @param int $status One of the STATUS_ constants.
     * @return string Localised label.
     */
    public static function status_label(int $status): string {
        return match ($status) {
            self::STATUS_APPROVED => get_string('submissionstatusapproved', 'enrol_apply'),
            self::STATUS_WAITING => get_string('submissionstatuswaiting', 'enrol_apply'),
            self::STATUS_CANCELLED => get_string('submissionstatuscancelled', 'enrol_apply'),
            default => get_string('submissionstatuspending', 'enrol_apply'),
        };
    }
}
