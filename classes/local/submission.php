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

    /** @var int No group enrolment key was presented, or none counted. */
    public const KEY_NONE = 0;

    /** @var int A valid key admitted the applicant without review. */
    public const KEY_ADMITTED = 1;

    /** @var int The key was valid, but the method had no place left, so the application waits. */
    public const KEY_NO_PLACE = 2;

    /** @var int The key was valid, but a group the admission would join had no seat left. */
    public const KEY_GROUP_FULL = 3;

    /**
     * Every value keyoutcome may hold.
     *
     * @var array
     */
    public const KEY_OUTCOMES = [
        self::KEY_NONE,
        self::KEY_ADMITTED,
        self::KEY_NO_PLACE,
        self::KEY_GROUP_FULL,
    ];

    /**
     * Format of the JSON envelope the snapshot was stored as before it became rows.
     *
     * Read by parse_envelope() and nothing else: the upgrade to enrol_apply_answer and the
     * restore of an archive written before it both still meet that envelope.
     *
     * @var int
     */
    public const SNAPSHOT_VERSION = 1;

    /** @var string The table holding one frozen row per submitted value. */
    public const ANSWER_TABLE = 'enrol_apply_answer';

    /** @var int Length of enrol_apply_answer.fieldkey; a longer key cannot be stored as it was. */
    public const KEY_LENGTH = 100;

    /** @var string Separates key, label and value of one row in aggregate_sql(). */
    public const AGGREGATE_UNIT = "\x1F";

    /** @var string Separates the rows in aggregate_sql(). */
    public const AGGREGATE_RECORD = "\x1E";

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
     * The record and its frozen answers ARE one transaction: a record whose answers were half
     * written would show the reviewer an application the applicant never submitted.
     *
     * What a group enrolment key did is written here too, with the record, so no later display
     * has to recompute it from seats and places that have moved since. It is the caller's
     * resolution of the key, never anything the form carried: the key itself is not stored.
     *
     * @param stdClass $instance Course enrol instance applied to.
     * @param int $userid Applicant.
     * @param int $userenrolmentid User enrolment created for the application.
     * @param stdClass $data Submitted application form data.
     * @param int $keygroupid Group whose key was presented and resolved, 0 for none.
     * @param int $keyoutcome One of the KEY_ constants; KEY_NONE exactly when $keygroupid is 0.
     * @return int Id of the new row.
     * @throws \coding_exception When the outcome is unknown, or the group and the outcome disagree.
     */
    public static function create(
        stdClass $instance,
        int $userid,
        int $userenrolmentid,
        stdClass $data,
        int $keygroupid = 0,
        int $keyoutcome = self::KEY_NONE
    ): int {
        global $DB;

        /* Refused rather than corrected: a group with no outcome, or an outcome with no group, is
           a caller that resolved the key one way and recorded it another. */
        if (!in_array($keyoutcome, self::KEY_OUTCOMES, true)) {
            throw new \coding_exception("Unknown group key outcome {$keyoutcome}");
        }
        if (($keyoutcome === self::KEY_NONE) !== ($keygroupid <= 0)) {
            throw new \coding_exception('A group key outcome needs its group, and a key group needs an outcome');
        }

        $transaction = $DB->start_delegated_transaction();
        $id = (int) $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => (int) $instance->courseid,
            'userid' => $userid,
            'enrolid' => (int) $instance->id,
            'userenrolmentid' => $userenrolmentid,
            'comment' => isset($data->applydescription) ? (string) $data->applydescription : '',
            'status' => self::STATUS_PENDING,
            /* Empty until a decision: record_outcome_message() is its only writer. */
            'outcomemessage' => '',
            /* Empty for the same reason, and it stays empty on a new application by
               construction: the note belongs to a decision, and no decision has been taken. */
            'decisionnote' => '',
            'timecreated' => time(),
            'timedecided' => 0,
            'decidedby' => 0,
            'keygroupid' => max(0, $keygroupid),
            'keyoutcome' => $keyoutcome,
        ]);
        self::snapshot($id, $instance, $data);
        $transaction->allow_commit();

        return $id;
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
     * else, so no answer rows is the truthful answer. The status is PENDING whatever the
     * enrolment is doing, because the caller stamps the real decision through decide()
     * immediately afterwards.
     *
     * A user enrolment that is not this plugin's, or that is already gone, writes nothing. The
     * three decision methods never pass a foreign id, because their lookup
     * (enrol_apply_plugin::get_pending_user_enrolment()) joins on enrol = 'apply', but this
     * method is public, and an enrol_apply trail row against another plugin's enrolment would be
     * uninterpretable.
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
            /* No key is the honest reconstruction: an application with no record went through no
               key route, which writes its record in create(). */
            'keygroupid' => 0,
            'keyoutcome' => self::KEY_NONE,
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
     * rows get_pending_user_enrolment() returned, which are this plugin's applications still
     * awaiting a decision, and the hook callback fires only on a status change to active. A bare
     * decide() call knows nothing, so the default stays conservative.
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
     * status, and the course, enrol and user enrolment ids. The frozen answers go whole: every
     * one of them is what the applicant typed, and a question with no answer identifies nobody
     * worth keeping.
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

        $transaction = $DB->start_delegated_transaction();
        self::delete_answers('courseid = :courseid', ['courseid' => $courseid]);
        $DB->execute(
            "UPDATE {enrol_apply_submission}
                SET userid = 0, decidedby = 0, comment = ?, outcomemessage = ?, decisionnote = ?
              WHERE courseid = ?",
            ['', '', '', $courseid]
        );
        $transaction->allow_commit();
    }

    /**
     * Delete the frozen answers of the records a condition on enrol_apply_submission selects.
     *
     * The one way every delete path removes answers, so none of them can forget the second
     * table: privacy erasure, pseudonymisation and the retention sweep each name the records they
     * remove and call this before removing them. The condition is applied to the record table,
     * never to the answers, because the answers carry no user, course or date of their own.
     *
     * @param string $select WHERE clause over {enrol_apply_submission}, unaliased, named parameters only.
     * @param array $params Its parameters.
     * @return void
     */
    public static function delete_answers(string $select, array $params): void {
        global $DB;

        $DB->delete_records_select(
            self::ANSWER_TABLE,
            "submissionid IN (SELECT id FROM {enrol_apply_submission} WHERE {$select})",
            $params
        );
    }

    /**
     * Delete one record with its frozen answers.
     *
     * @param int $id Row id in enrol_apply_submission.
     * @return void
     */
    public static function delete(int $id): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(self::ANSWER_TABLE, ['submissionid' => $id]);
        $DB->delete_records('enrol_apply_submission', ['id' => $id]);
        $transaction->allow_commit();
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
     * Freeze what the applicant typed into the profile fields and the questions the instance asked.
     *
     * One row per value in enrol_apply_answer, written inside create()'s transaction. The answers
     * to the instance's own questions sit beside the profile fields under a key of their own
     * (answers::SOURCE_ANSWER), so every reader of the snapshot shows them without knowing they
     * exist. The label is stored beside the key on purpose. A custom profile field or a question
     * can be renamed, and a snapshot that resolved its label at read time would then show a
     * question that was never asked; the key is stored as well so a later reader can still tell
     * which field it was, and mask it. The value is stored exactly as submitted - see
     * fields::submitted_values() for why it is not put through format_string().
     *
     * @param int $submissionid Record the answers belong to.
     * @param stdClass $instance Enrol instance the application was submitted to.
     * @param stdClass $data Submitted form data.
     * @return int Number of rows written.
     */
    public static function snapshot(int $submissionid, stdClass $instance, stdClass $data): int {
        $values = array_merge(fields::submitted_values($instance, $data), answers::submitted_values($instance, $data));

        return self::write_answers($submissionid, $values);
    }

    /**
     * Write a snapshot's entries as frozen rows, in the order given.
     *
     * The single writer for every route a snapshot arrives by: a submission, the upgrade that
     * moved the old JSON envelopes into rows, and the restore of an archive that still carries
     * one. Entries are filtered through answer_record(); a key repeated within one snapshot keeps
     * its first value, because a per-question report column reads one value per record. All rows
     * or none: an upgrade re-run skips a record that already has rows, so a half-written one would
     * stay half written.
     *
     * @param int $submissionid Record the answers belong to.
     * @param array $entries List of arrays carrying 'key', 'label' and 'value'.
     * @return int Number of rows written.
     */
    public static function write_answers(int $submissionid, array $entries): int {
        global $DB;

        $records = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $record = self::answer_record(
                $submissionid,
                $entry['key'] ?? '',
                $entry['label'] ?? '',
                $entry['value'] ?? null,
                count($records)
            );
            if ($record === null || isset($records[$record->fieldkey])) {
                continue;
            }
            $records[$record->fieldkey] = $record;
        }
        if (!$records) {
            return 0;
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->insert_records(self::ANSWER_TABLE, array_values($records));
        $transaction->allow_commit();

        return count($records);
    }

    /**
     * One frozen row, or null when the entry cannot be stored and masked as it was given.
     *
     * The tolerance is the old envelope reader's, because two of the three routes into this table
     * hand it data this site did not produce: a restored archive, and an envelope a restore wrote
     * before the table existed. Every part must be scalar - an array would show the reader the
     * word "Array" - and the entry is dropped rather than repaired, since a key that cannot be
     * matched against visible_keys() cannot be masked, and an entry that cannot be masked must not
     * be rendered at all.
     *
     * Two rules are the table's own. A key longer than the column, or carrying a control
     * character, is dropped: truncating or cleaning it could turn a key the masking withholds into
     * one it shows. Control characters are removed from the label and the value instead, which
     * changes nothing a reader can see; aggregate_sql() separates rows and parts with two of them,
     * so a value carrying one could otherwise forge an entry under another key.
     *
     * @param int $submissionid Record the row belongs to.
     * @param mixed $key Field key.
     * @param mixed $label Frozen question text, plain spelling.
     * @param mixed $value Frozen display value, plain spelling.
     * @param int $sortorder Position in the snapshot.
     * @return stdClass|null The row to insert, or null to drop the entry.
     */
    public static function answer_record(int $submissionid, $key, $label, $value, int $sortorder): ?stdClass {
        if (!is_scalar($key) || !is_scalar($label) || !is_scalar($value)) {
            return null;
        }

        $key = (string) $key;
        if (\core_text::strlen($key) > self::KEY_LENGTH || preg_match('/[\x00-\x1F\x7F]/', $key)) {
            return null;
        }

        $clean = static fn($text): string => (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $text);

        return (object) [
            'submissionid' => $submissionid,
            'fieldkey' => $key,
            'label' => $clean($label),
            'value' => $clean($value),
            'sortorder' => $sortorder,
        ];
    }

    /**
     * The frozen values of one submission, ready to display, in the order they were submitted.
     *
     * @param int $submissionid Row id in enrol_apply_submission; 0 reads nothing.
     * @return array List of arrays carrying 'key', 'label' and 'value' string entries.
     */
    public static function read_snapshot(int $submissionid): array {
        return self::read_snapshots([$submissionid])[$submissionid] ?? [];
    }

    /**
     * The frozen values of many submissions at once, for a listing that renders many rows.
     *
     * One query per thousand records rather than one per record: the approval queue renders a
     * page of applications and would otherwise read the answers row by row. An empty list reads
     * nothing.
     *
     * @param array $submissionids Row ids in enrol_apply_submission; zeros and repeats are ignored.
     * @return array Submission id => list of arrays carrying 'key', 'label' and 'value'. A record
     *               with no answers has no entry.
     */
    public static function read_snapshots(array $submissionids): array {
        global $DB;

        $ids = array_values(array_unique(array_filter(array_map('intval', $submissionids))));
        $snapshots = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'sub');
            /* get_records rather than a recordset: one statement on every driver, where the
               PostgreSQL recordset adds a cursor's declare and close. */
            $rows = $DB->get_records_select(
                self::ANSWER_TABLE,
                "submissionid {$insql}",
                $params,
                'submissionid, sortorder, id',
                'id, submissionid, fieldkey, label, value'
            );
            foreach ($rows as $row) {
                $snapshots[(int) $row->submissionid][] = [
                    'key' => (string) $row->fieldkey,
                    'label' => (string) $row->label,
                    'value' => (string) $row->value,
                ];
            }
        }

        return $snapshots;
    }

    /**
     * SQL giving one submission's answers as a single string, for a Report Builder column.
     *
     * A display callback sees one row and could only read the answers with a query per row, so the
     * custom report's snapshot column aggregates them in SQL instead, through the DML's portable
     * sql_group_concat(). Parts and rows are separated by two control characters, which
     * answer_record() keeps out of every key, label and value, so parse_aggregate() can split the
     * string back without ambiguity. MySQL truncates GROUP_CONCAT at group_concat_max_len (1024
     * bytes unless raised; MariaDB defaults to 1M), as it does for core's own groupconcat
     * aggregation; parse_aggregate() drops a row cut in half.
     *
     * @param string $submissionidsql SQL naming the submission id in the outer query.
     * @return string A scalar subquery.
     */
    public static function aggregate_sql(string $submissionidsql): string {
        global $DB;

        $unit = "'" . self::AGGREGATE_UNIT . "'";
        $entry = $DB->sql_concat('eaagg.fieldkey', $unit, 'eaagg.label', $unit, 'eaagg.value');

        return "(SELECT " . $DB->sql_group_concat($entry, self::AGGREGATE_RECORD, 'eaagg.sortorder') . "
                   FROM {" . self::ANSWER_TABLE . "} eaagg
                  WHERE eaagg.submissionid = {$submissionidsql})";
    }

    /**
     * Split what aggregate_sql() produced back into entries.
     *
     * A row that does not split into exactly three parts is dropped, which is what a row cut by
     * MySQL's group_concat_max_len looks like.
     *
     * @param string|null $aggregate The aggregated string, null when there are no answers.
     * @return array List of arrays carrying 'key', 'label' and 'value' string entries.
     */
    public static function parse_aggregate(?string $aggregate): array {
        if ($aggregate === null || $aggregate === '') {
            return [];
        }

        $entries = [];
        foreach (explode(self::AGGREGATE_RECORD, $aggregate) as $row) {
            $parts = explode(self::AGGREGATE_UNIT, $row);
            if (count($parts) !== 3) {
                continue;
            }
            $entries[] = ['key' => $parts[0], 'label' => $parts[1], 'value' => $parts[2]];
        }

        return $entries;
    }

    /**
     * The entries of a JSON envelope, as the snapshot was stored before it became rows.
     *
     * Read by the upgrade that moved every envelope into enrol_apply_answer, and by the restore
     * of an archive written before that, which still carries one on its submission element. Every
     * failure mode returns an empty list rather than throwing: the envelope may be one a newer
     * version of this plugin wrote on another site. The entries come back unfiltered, as the
     * envelope held them; answer_record() decides which can be stored.
     *
     * @param string|null $json Stored envelope.
     * @return array The envelope's entries, each still to be checked.
     */
    public static function parse_envelope(?string $json): array {
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

        return array_values($decoded['fields']);
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

    /**
     * What a group enrolment key did for the newest application of a user enrolment.
     *
     * Newest first, as chosen_groups() reads: a user enrolment can carry an older record from a
     * cancelled application, and the key route of that one is not this one's.
     *
     * @param int $userenrolmentid User enrolment the application belongs to.
     * @return stdClass|null Object with int 'groupid' (0 when the group could not be restored) and
     *     int 'outcome' (a KEY_ constant other than KEY_NONE); null when no key was presented.
     */
    public static function key_of(int $userenrolmentid): ?stdClass {
        global $DB;

        $rows = $DB->get_records(
            'enrol_apply_submission',
            ['userenrolmentid' => $userenrolmentid],
            'timecreated DESC, id DESC',
            'id, keygroupid, keyoutcome',
            0,
            1
        );
        $row = reset($rows);
        $outcome = $row ? (int) $row->keyoutcome : self::KEY_NONE;
        /* An outcome outside the vocabulary - only a hand edit writes one; the restore cleans
           them - reads as no key rather than as some route that never ran. */
        if ($outcome === self::KEY_NONE || !in_array($outcome, self::KEY_OUTCOMES, true)) {
            return null;
        }

        return (object) ['groupid' => (int) $row->keygroupid, 'outcome' => $outcome];
    }

    /**
     * The language string naming a group key outcome.
     *
     * A literal per branch, as status_label() is.
     *
     * @param int $outcome One of the KEY_ constants.
     * @return string Localised label; empty for KEY_NONE.
     */
    public static function key_outcome_label(int $outcome): string {
        return match ($outcome) {
            self::KEY_ADMITTED => get_string('keyoutcomeadmitted', 'enrol_apply'),
            self::KEY_NO_PLACE => get_string('keyoutcomenoplace', 'enrol_apply'),
            self::KEY_GROUP_FULL => get_string('keyoutcomegroupfull', 'enrol_apply'),
            default => '',
        };
    }
}
