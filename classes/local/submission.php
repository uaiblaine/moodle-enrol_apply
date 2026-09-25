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
 * enrol_apply_applicationinfo is dropped on approval, on cancellation and in unenrol_user(),
 * and the user_enrolments row it hangs off goes with the enrolment. This table is the one that
 * stays, so it is keyed by courseid and userid rather than by any of those - userenrolmentid
 * rides along as a reference and is never the key to a deletion.
 *
 * Two properties of that key look like defects and are not:
 *
 *  - It is NOT unique. Course deletion pseudonymises by zeroing userid, so a deleted course
 *    with two applicants produces two rows with the same courseid and userid = 0, which a
 *    unique key would refuse. Cancelling and re-applying produces a second row too, so does
 *    restoring a course into one that already holds the trail, and so does a course carrying
 *    two apply instances - which the plugin supports on purpose. What IS enforced is narrower:
 *    one live application per enrol INSTANCE and user, by the lock in
 *    enrol_apply_plugin::submit_application(), which is keyed on the instance id and the user
 *    and guards a user_enrolments lookup by enrolid.
 *  - Only userid and decidedby carry a foreign key. A foreign key here is documentation and an
 *    index - Moodle's generators emit no database-level constraint (lib/ddl/sql_generator.php,
 *    $foreign_keys = false) - so declaring one for courseid, enrolid or userenrolmentid, which
 *    the row deliberately outlives, would document a false integrity claim. The two user
 *    columns are never left dangling: they are zeroed on course deletion, and an erasure
 *    request deletes the applicant's row or zeroes the decider.
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
            /* Empty until a decision: record_outcome_message() is its only writer. */
            'outcomemessage' => '',
            /* Empty for the same reason, and it stays empty on a new application by
               construction: the note belongs to a decision, and no decision has been taken. */
            'decisionnote' => '',
            'timecreated' => time(),
            'timedecided' => 0,
            'decidedby' => 0,
        ]);
    }

    /**
     * Make sure the application named by a user enrolment has a durable record.
     *
     * For the three decision methods. record_outcome_message() and record_decision_note()
     * write to the rows they find and silently write nothing when there are none, so without
     * this a decision on an application that predates this table would store the decider's
     * message and note nowhere, and the applicant's mail would arrive without the message.
     *
     * It reconstructs only what a pending application can honestly claim. The comment is
     * recovered from enrol_apply_applicationinfo, which is still written beside this table; the
     * profile snapshot is not, because the values the applicant typed were never stored anywhere
     * else, so an empty envelope is the truthful answer. The status is PENDING whatever the
     * enrolment is doing, because the caller stamps the real decision through decide()
     * immediately afterwards.
     *
     * A user enrolment that is not this plugin's, or that is already gone, writes nothing. The
     * three decision methods never pass a foreign id - each resolves the instance with a
     * MUST_EXIST lookup keyed on enrol = 'apply' first - but this method is public, and an
     * enrol_apply trail row against another plugin's enrolment would be uninterpretable.
     *
     * Not a substitute for create(), the writer for a real application and the only one that
     * can hold the snapshot.
     *
     * @param int $userenrolmentid User enrolment the decision is about.
     * @return void
     */
    public static function ensure(int $userenrolmentid): void {
        global $DB;

        if ($DB->record_exists('enrol_apply_submission', ['userenrolmentid' => $userenrolmentid])) {
            return;
        }

        $sql = "SELECT ue.id, ue.userid, ue.enrolid, ue.timecreated, e.courseid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = :enrol
                 WHERE ue.id = :ueid";
        $enrolment = $DB->get_record_sql($sql, ['enrol' => 'apply', 'ueid' => $userenrolmentid]);
        if (!$enrolment) {
            return;
        }

        /* False when there is no comment row either, which (string) turns into the empty
           string - the same value create() writes for an application submitted without one. */
        $comment = $DB->get_field(
            'enrol_apply_applicationinfo',
            'comment',
            ['userenrolmentid' => (int) $enrolment->id]
        );

        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => (int) $enrolment->courseid,
            'userid' => (int) $enrolment->userid,
            'enrolid' => (int) $enrolment->enrolid,
            'userenrolmentid' => (int) $enrolment->id,
            'comment' => (string) $comment,
            'userinfodata' => '',
            'status' => self::STATUS_PENDING,
            'outcomemessage' => '',
            'decidedgroups' => '',
            'decidedrole' => 0,
            'decisionnote' => '',
            /* The ENROLMENT's date, not this moment. The record is the trail of an application
               that was submitted long before anybody reached this method, and stamping it now
               would put the reconstruction's own date on it - which prior_applications() orders
               by and the retention sweep filters on. */
            'timecreated' => (int) $enrolment->timecreated,
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
     * A row already at the target status is not restamped by default, so a later no-op touch of
     * an already-decided enrolment does not re-attribute the decision to whoever did the
     * touching. test_a_recorded_decision_is_not_restamped pins that.
     *
     * $isfreshdecision is the narrow exception for a genuine second decision at the same status.
     * Approve, suspend from the participants page, approve again: the record never leaves
     * STATUS_APPROVED, so without it the trail would go on naming the first decider and date
     * while complete_approval() notifies the applicant of the second approval.
     *
     * Only the caller knows the enrolment genuinely moved: confirm_enrolment() processes only
     * rows get_pending_user_enrolment() returned, which admits suspended and waiting-list rows
     * only, and the hook callback fires only on a status change to active. A bare decide() call
     * knows nothing, so the default stays conservative.
     *
     * Passing it is safe for the double pass complete_approval() makes on every queue approval:
     * both passes run in one request with one $USER, so the second restamps the same decider.
     *
     * @param int $userenrolmentid User enrolment the decision applies to.
     * @param int $status One of the STATUS_ constants.
     * @param int $decidedby User who took the decision.
     * @param bool $isfreshdecision True when the caller knows the enrolment actually moved, so a
     *                              status that already matches is still a new decision.
     * @return void
     */
    public static function decide(
        int $userenrolmentid,
        int $status,
        int $decidedby,
        bool $isfreshdecision = false
    ): void {
        global $DB;

        if (!in_array($status, self::STATUSES, true)) {
            throw new \coding_exception('Unknown enrol_apply submission status: ' . $status);
        }

        $rows = $DB->get_records('enrol_apply_submission', ['userenrolmentid' => $userenrolmentid], '', 'id, status');
        foreach ($rows as $row) {
            if ((int) $row->status === $status && !$isfreshdecision) {
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
     * Runs from \core_course\hook\before_course_deleted and nowhere else, never from the
     * course_deleted event: contextlist::add_from_sql() wraps every provider query in a JOIN
     * against {context} (privacy/classes/local/request/contextlist.php), and delete_course()
     * deletes the course context before it triggers the event (lib/moodlelib.php). A row that
     * kept a real userid past that point would be personal data that no subject access request
     * can reach and no erasure request can delete.
     *
     * What survives is what an audit needs, with both user columns zeroed: the dates, the
     * status, and the course, enrol and user enrolment ids.
     *
     * That is pseudonymisation, not anonymisation. userenrolmentid is retained, and
     * logstore_standard records enrolment events with objecttable 'user_enrolments', objectid
     * set to that same id and the enrolled user as relateduserid - so on a site keeping its
     * standard log, an administrator can still re-attach a retained row to a person.
     *
     * @param int $courseid Course being deleted.
     * @return void
     */
    public static function pseudonymise(int $courseid): void {
        global $DB;

        $DB->execute(
            "UPDATE {enrol_apply_submission}
                SET userid = 0, decidedby = 0, comment = ?, userinfodata = ?, outcomemessage = ?,
                    decisionnote = ?
              WHERE courseid = ?",
            ['', '', '', '', $courseid]
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

            /* Every part is checked for being scalar before it is cast. The envelope may be JSON
               another site wrote and a restore copied in verbatim, so a 'value' holding an array
               is reachable, and (string) on one emits an "Array to string conversion" warning
               and shows the reader the word "Array".
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
     * Kept apart from decide(), which takes no message. complete_approval() runs twice for an
     * approval taken through the queue: enrol_plugin::update_user_enrol() dispatches
     * \core_enrol\hook\before_user_enrolment_updated before it writes the row, so
     * hook_callbacks reaches complete_approval() first, with no operator input because the
     * hook carries none. The message is therefore stored before the status changes, and the
     * notify_approval task reads it back through outcome_message().
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

        /* An empty message is written, not skipped: the message belongs to the decision being
           taken, so approving a re-suspended application with the box left empty must not
           re-send the message typed for the earlier decision. Callers that have nothing to say
           about the message do not call this at all - the out-of-band approval route passes no
           operator input and reaches no writer.

           Trimmed rather than merely tested for blankness, which keeps the two properties apart:
           whitespace alone is still not a message (it is stored as the empty string, which
           test_a_blank_message_is_not_recorded pins), and an empty decision still clears an
           earlier one. */
        $clean = trim($message);

        $rows = $DB->get_records('enrol_apply_submission', ['userenrolmentid' => $userenrolmentid], '', 'id');
        foreach ($rows as $row) {
            $DB->update_record('enrol_apply_submission', (object) [
                'id' => $row->id,
                'outcomemessage' => $clean,
            ]);
        }
    }

    /**
     * Record what the decider wrote for their colleagues about this decision.
     *
     * The twin of record_outcome_message() in shape and the opposite of it in audience. That
     * one is the APPLICANT's: notify_applicant() appends it to the mail body and the privacy
     * export hands it to the person it was written to. This one never leaves the site - it
     * answers "why was this deferred" (waiting for a place, waiting for something to be
     * validated), which does not belong in a message to the person waiting.
     *
     * Free text rather than a coded vocabulary: the distinction is thin, and a coded reason
     * offered as a queue filter would under-report, because the queue's row set and the
     * records diverge in both directions.
     *
     * Written for all three decisions rather than for deferral alone, so the two decision
     * surfaces cannot offer different things and a re-queued application cannot inherit an
     * explanation written for a decision that was superseded.
     *
     * The empty value is written, not skipped, as for the outcome message: otherwise an
     * application that comes back to the queue - which core's "Edit enrolment" screen and an
     * expiredaction of suspend both do - is decided a second time carrying the first decision's
     * reason, with nothing on screen to say so.
     *
     * @param int $userenrolmentid User enrolment the decision applies to.
     * @param string $note What the decider typed, empty for none.
     * @return void
     */
    public static function record_decision_note(int $userenrolmentid, string $note): void {
        global $DB;

        /* Trimmed rather than merely tested for blankness, which keeps the two properties
           apart: whitespace alone is not a note and is stored as the empty string, and an
           empty decision still clears an earlier one. */
        $clean = trim($note);

        $rows = $DB->get_records('enrol_apply_submission', ['userenrolmentid' => $userenrolmentid], '', 'id');
        foreach ($rows as $row) {
            $DB->update_record('enrol_apply_submission', (object) [
                'id' => $row->id,
                'decisionnote' => $clean,
            ]);
        }
    }

    /**
     * Record the groups the decider chose for the applicant to join.
     *
     * Stored rather than passed along, for the same reason the outcome message is:
     * complete_approval() runs twice for a queue approval and only the second call would carry
     * a chosen list. Two calls passing different lists would UNION them, because
     * groups_add_member() adds and never replaces - so a group the approver deselected would be
     * joined anyway and nothing would remove it. Both calls read this column instead, so both
     * see the same answer.
     *
     * Zero and duplicate ids are dropped before the list is joined. An empty list is stored as
     * the empty string and means "the decider chose nothing", which chosen_groups() reports as
     * null so the caller falls back to the instance's own list.
     *
     * @param int $userenrolmentid User enrolment the decision applies to.
     * @param array $groupids Group ids the decider chose, already validated by the caller.
     * @return void
     */
    public static function record_decided_groups(int $userenrolmentid, array $groupids): void {
        global $DB;

        /* An empty choice is written, not skipped, as for the outcome message: otherwise a
           second approval with the chooser left alone would re-join the groups picked for the
           earlier decision. chosen_groups() reads the empty value back as "no choice recorded",
           which puts the instance's own list back in charge. */
        $clean = array_values(array_unique(array_filter(array_map('intval', $groupids))));

        $rows = $DB->get_records('enrol_apply_submission', ['userenrolmentid' => $userenrolmentid], '', 'id');
        foreach ($rows as $row) {
            $DB->update_record('enrol_apply_submission', (object) [
                'id' => $row->id,
                'decidedgroups' => implode(',', $clean),
            ]);
        }
    }

    /**
     * The groups the decider chose, or null when no choice is recorded.
     *
     * Null means "use the instance's own list", and it is the ONLY way of saying nothing here:
     * a stored value that parses to no ids reads as no choice, never as an empty array. The
     * sole caller branches on `=== null` and hands anything else to get_in_or_equal(), which
     * throws on an empty array.
     *
     * record_decided_groups() stores only non-zero ids or the empty string, but a restore writes
     * this column from a foreign archive, so "0", a lone comma, or a list whose ids all fail to
     * map are all shapes the parse has to survive.
     *
     * @param int $userenrolmentid User enrolment the decision applies to.
     * @return array|null Group ids, never empty; null when nothing usable was recorded.
     */
    public static function chosen_groups(int $userenrolmentid): ?array {
        global $DB;

        $rows = $DB->get_records(
            'enrol_apply_submission',
            ['userenrolmentid' => $userenrolmentid],
            'timecreated DESC, id DESC',
            'id, decidedgroups',
            0,
            1
        );
        $row = reset($rows);
        if (!$row || trim((string) $row->decidedgroups) === '') {
            return null;
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $row->decidedgroups))));

        return $ids ?: null;
    }

    /**
     * Record the role the decider chose for the applicant to hold once approved.
     *
     * Stored rather than passed along, for the same reason the groups are, and the consequence
     * of getting it wrong is worse. complete_approval() runs twice for a queue approval and only
     * the second call could carry an argument; two calls carrying DIFFERENT roles would assign
     * both, and a role assignment records nothing about which pass made it. Both calls read this
     * column instead, so both compute the same role and role_assign() collapses them into one row.
     *
     * A zero is written rather than skipped. It means "no role was chosen, use the instance's
     * own", which is exactly what an approval submitted with the select left alone means - so
     * writing it lets a later decision REPLACE an earlier one. Skipping it would make a stored
     * role sticky: a row that comes back to the queue, which core's "Edit enrolment" screen and
     * an expiredaction of "suspend" both do, would be approved a second time with the superseded
     * role and nothing on screen to say so.
     *
     * @param int $userenrolmentid User enrolment the decision applies to.
     * @param int $roleid Role the decider chose, already allowlisted by the caller; 0 for none.
     * @return void
     */
    public static function record_decided_role(int $userenrolmentid, int $roleid): void {
        global $DB;

        $clean = $roleid > 0 ? $roleid : 0;

        $rows = $DB->get_records('enrol_apply_submission', ['userenrolmentid' => $userenrolmentid], '', 'id');
        foreach ($rows as $row) {
            $DB->update_record('enrol_apply_submission', (object) [
                'id' => $row->id,
                'decidedrole' => $clean,
            ]);
        }
    }

    /**
     * The role the decider chose, or null when they chose none.
     *
     * The form offers a role or nothing, and "nothing" means the instance's own role, so a plain
     * int or null covers every case. An explicit "no role at all" is not something the queue can
     * express.
     *
     * @param int $userenrolmentid User enrolment the decision applies to.
     * @return int|null Role id, or null when nothing was recorded.
     */
    public static function chosen_role(int $userenrolmentid): ?int {
        global $DB;

        $rows = $DB->get_records(
            'enrol_apply_submission',
            ['userenrolmentid' => $userenrolmentid],
            'timecreated DESC, id DESC',
            'id, decidedrole',
            0,
            1
        );
        $row = reset($rows);
        if (!$row || (int) $row->decidedrole <= 0) {
            return null;
        }

        return (int) $row->decidedrole;
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
     * A literal per branch, never get_string('status_' . $status): a dynamic string id cannot be
     * checked against the lang file.
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
