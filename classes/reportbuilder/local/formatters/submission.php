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

namespace enrol_apply\reportbuilder\local\formatters;

use enrol_apply\local\fields as applyfields;
use enrol_apply\local\queue;
use enrol_apply\local\submission as submissionhelper;
use stdClass;

/**
 * Display callbacks for the application record's columns.
 *
 * Every callback takes its first parameter untyped because it must accept null, as
 * {@see \core_reportbuilder\local\report\column::add_callback()} requires wherever the field or
 * the entity's join can produce null: the text columns here are nullable, the enrolment columns
 * come from a LEFT join, and groupconcat aggregation restores nulls before calling back value by
 * value. A typed parameter would be a TypeError at render time.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission {
    /**
     * Sentinel meaning the reader may see every field the snapshot holds.
     *
     * Only a caller that has asked a context may pass it. A list is taken as the explicit list
     * of visible keys, which is what the report passes for a reader without the identity
     * capability; an array carrying EXCEPT is the rule for a reader holding it, see
     * visible_keys(). Every other value - and in particular null, which is what core supplies
     * when no argument was registered - means the names and nothing else. Read a value of any
     * shape through shows(), never with in_array().
     *
     * @var bool
     */
    public const ALL_FIELDS = true;

    /** @var string Key of the visibility array listing the keys withheld from an identity reader. */
    public const EXCEPT = 'except';

    /**
     * Key of the visibility array listing the custom profile fields that still exist.
     *
     * Null when the reader holds moodle/user:viewalldetails, which every visibility level yields
     * to; otherwise a c_ key naming a field deleted since submission is withheld, because its
     * visibility can no longer be read.
     *
     * @var string
     */
    public const KNOWNCUSTOM = 'knowncustom';

    /**
     * The {user} columns core treats as identity fields, which show only where showuseridentity
     * names them ({@see \core_user\fields::get_identity_fields()}, lib/myprofilelib.php).
     *
     * @var array
     */
    public const IDENTITY_COLUMNS = ['email', 'idnumber', 'phone1', 'phone2', 'institution', 'department', 'address'];

    /** @var string The application was cancelled. */
    protected const OUTCOME_CANCELLED = 'cancelled';

    /** @var string Nothing is known of the enrolment: a restore could not map it. */
    protected const OUTCOME_UNKNOWN = 'unknown';

    /** @var string Undecided, and the enrolment is gone. */
    protected const OUTCOME_NEVERDECIDED = 'neverdecided';

    /** @var string Undecided, and the enrolment's period has run out, so the queue lists it no more. */
    protected const OUTCOME_LAPSED = 'lapsed';

    /** @var string Pending, in the queue. */
    protected const OUTCOME_AWAITING = 'awaiting';

    /** @var string Deferred to the waiting list, in the queue. */
    protected const OUTCOME_WAITING = 'waiting';

    /** @var string Approved, and the enrolment is gone. */
    protected const OUTCOME_UNENROLLED = 'unenrolled';

    /** @var string Approved and active. */
    protected const OUTCOME_APPROVED = 'approved';

    /** @var string Approved, and the enrolment's period has run out. */
    protected const OUTCOME_EXPIRED = 'expired';

    /** @var string Approved, then suspended with no end passed: back in the queue. */
    protected const OUTCOME_SUSPENDED = 'suspended';

    /** @var string A status outside the vocabulary. */
    protected const OUTCOME_OTHER = 'other';

    /**
     * The status, as the plugin's own label.
     *
     * @param mixed $value Stored status.
     * @param stdClass $row Row being rendered.
     * @return string Localised label, or the raw value when it is not one this plugin knows.
     */
    public static function status($value, stdClass $row): string {
        if ($value === null || $value === '') {
            return '';
        }

        $status = (int) $value;
        if (!in_array($status, submissionhelper::STATUSES, true)) {
            // Not a vocabulary member: show it rather than inventing a label for it.
            return (string) $value;
        }

        return submissionhelper::status_label($status);
    }

    /**
     * How the application was admitted: through review, or what a group key did.
     *
     * @param mixed $value Stored keyoutcome.
     * @param stdClass $row Row being rendered.
     * @return string Localised label, or the raw value when it is not one this plugin knows.
     */
    public static function admission_route($value, stdClass $row): string {
        if ($value === null || $value === '') {
            return '';
        }

        $outcome = (int) $value;
        if ($outcome === submissionhelper::KEY_NONE) {
            return get_string('admissionroutereview', 'enrol_apply');
        }
        if (!in_array($outcome, submissionhelper::KEY_OUTCOMES, true)) {
            // Not a vocabulary member: show it rather than inventing a label for it.
            return (string) $value;
        }

        return submissionhelper::key_outcome_label($outcome);
    }

    /**
     * The group whose enrolment key the applicant presented.
     *
     * Escaped by format_string(), as core's group entity does: a Report Builder cell is raw HTML.
     * A key presented and its group since deleted, or never mapped by a restore (keygroupid 0
     * with an outcome), says so rather than reading as no key at all. A group the reader may not
     * see by core's visibility rule ({@see \enrol_apply\local\groupvisibility::visible_to()},
     * judged for $USER in the group's course) leaves the cell empty, as for no key: the admission
     * route beside it already says a key was used, and nothing more is said.
     *
     * @param mixed $value The group's name, null when the join found nothing.
     * @param stdClass $row Row being rendered, carrying keygroupid, keygroupoutcome, keygroupcourseid
     *     and keygroupvisibility.
     * @return string The name, a note that the group is gone, or empty when no key was presented
     *     or the reader may not see the group.
     */
    public static function key_group($value, stdClass $row): string {
        global $USER;

        if ((int) ($row->keygroupoutcome ?? 0) === submissionhelper::KEY_NONE) {
            return '';
        }
        if ($value === null || $value === '') {
            return get_string('keygroupgone', 'enrol_apply');
        }
        $group = (object) [
            'id' => $row->keygroupid ?? null,
            'courseid' => $row->keygroupcourseid ?? null,
            'visibility' => $row->keygroupvisibility ?? null,
        ];
        if (!\enrol_apply\local\groupvisibility::visible_to($group, (int) $USER->id)) {
            return '';
        }

        return format_string((string) $value, true, [
            'context' => \context_course::instance((int) $row->keygroupcourseid),
        ]);
    }

    /**
     * The group the applicant chose from the method's offer (C-D4).
     *
     * The rules of {@see key_group()}: escaped by format_string(), as a Report Builder cell is raw
     * HTML; a choice whose group was deleted since, or never mapped by a restore, says so; and a
     * group the reader may not see by core's visibility rule
     * ({@see \enrol_apply\local\groupvisibility::visible_to()}, judged for $USER) leaves the cell
     * empty, as for no choice. "No preference" is recorded as no group, so its cell is empty too.
     *
     * @param mixed $value The group's name, null when the join found nothing.
     * @param stdClass $row Row being rendered, carrying chosengroupid, chosengroupcourseid and
     *     chosengroupvisibility.
     * @return string The name, a note that the group is gone, or empty when no group was chosen or
     *     the reader may not see it.
     */
    public static function chosen_group($value, stdClass $row): string {
        global $USER;

        if ((int) ($row->chosengroupid ?? 0) <= 0) {
            return '';
        }
        if ($value === null || $value === '') {
            return get_string('keygroupgone', 'enrol_apply');
        }
        $group = (object) [
            'id' => $row->chosengroupid,
            'courseid' => $row->chosengroupcourseid ?? null,
            'visibility' => $row->chosengroupvisibility ?? null,
        ];
        if (!\enrol_apply\local\groupvisibility::visible_to($group, (int) $USER->id)) {
            return '';
        }

        return format_string((string) $value, true, [
            'context' => \context_course::instance((int) $row->chosengroupcourseid),
        ]);
    }

    /**
     * The groups the last approval left the applicant in, by name, in natural order.
     *
     * Each name is gated by core's visibility rule for $USER, judged for the cell's groups at once
     * ({@see \enrol_apply\local\groupvisibility::visible_ids()}): a group the reader may not see is
     * left out, and a cell whose every group is hidden is blank, as the key group's cell is
     * (V-D13). Nothing is counted in its place, since "one more" would tell a reader who may not
     * see a group that the applicant is in one. A group deleted since is left out as well: the
     * column's SQL finds only groups that exist ({@see submissionhelper::joined_groups_sql()}), the
     * deletion took the membership with it, and the cell names memberships, so a list of names
     * has nothing to say about it. Blank, too, for a record that joined none and for one whose
     * groups are not known (decided before the column existed).
     *
     * Escaped by format_string() in the group's course, as {@see key_group()} is.
     *
     * @param mixed $value What submissionhelper::joined_groups_sql() produced, null when it found no group.
     * @param stdClass $row Row being rendered.
     * @return string Names separated by commas, or empty.
     */
    public static function joined_groups($value, stdClass $row): string {
        global $USER;

        $groups = submissionhelper::parse_joined_groups(
            $value === null ? null : (string) $value,
            isset($row->courseid) ? (int) $row->courseid : null
        );
        if (!$groups) {
            return '';
        }

        $visible = \enrol_apply\local\groupvisibility::visible_ids($groups, (int) $USER->id);
        $groups = array_intersect_key($groups, array_flip($visible));
        \core_collator::asort_objects_by_property($groups, 'name', \core_collator::SORT_NATURAL);

        $names = [];
        foreach ($groups as $group) {
            $context = \context_course::instance($group->courseid, IGNORE_MISSING);
            if ($context) {
                $names[] = format_string($group->name, true, ['context' => $context]);
            }
        }

        return implode(', ', $names);
    }

    /**
     * What the enrolment is doing now, or why there is nothing to report.
     *
     * A null live status means the join found nothing: no longer enrolled. But a record restored
     * without a mappable enrolment carries userenrolmentid = 0 (restore_enrol_apply_plugin casts
     * a missing mapping to 0), which also finds nothing and must not be reported as "no longer
     * enrolled", so it is checked first.
     *
     * @param mixed $value The live user_enrolments.status, null when there is no enrolment.
     * @param stdClass $row Row being rendered, carrying liveueid.
     * @return string Localised label.
     */
    public static function enrolment($value, stdClass $row): string {
        global $CFG;

        /* ENROL_APPLY_USER_WAIT is defined in lib.php, which is not autoloaded and may not be
           included when a report renders. Not replaced by submission::STATUS_WAITING: that is
           the record's status, not the enrolment's, and both are 2 only by coincidence. */
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        if ((int) ($row->liveueid ?? 0) === 0) {
            return get_string('enrolmentunknown', 'enrol_apply');
        }

        if ($value === null || $value === '') {
            return get_string('enrolmentgone', 'enrol_apply');
        }

        return match ((int) $value) {
            ENROL_USER_ACTIVE => get_string('enrolmentactive', 'enrol_apply'),
            ENROL_USER_SUSPENDED => get_string('enrolmentsuspended', 'enrol_apply'),
            ENROL_APPLY_USER_WAIT => get_string('enrolmentwaiting', 'enrol_apply'),
            // Not a value this plugin or core writes: show it rather than inventing a label.
            default => (string) $value,
        };
    }

    /**
     * What actually happened to this application, from the decision and the live enrolment.
     *
     * The stored status is the last decision this plugin took; the participants page, course
     * reset, user deletion and the expiry sweep change an enrolment without touching the record,
     * so approved-and-enrolled and approved-then-unenrolled are otherwise the same row.
     *
     * A suspended approval is split by timeend, through the queue's own rule
     * ({@see queue::is_awaiting_decision()}) rather than a copy of it, so the report and the
     * queue cannot disagree about a row. A suspension with no end or an end still in the future
     * (usually a manual one) is back in the approval queue, so the report says so. Any other end
     * has expired, including one equal to now and a negative one, which a restore can carry: it
     * is the expiry sweep's work and does not re-queue.
     *
     * A pending or waiting-list record is split by the same rule: when its enrolment has lapsed
     * (not active, with an end that has passed) the queue no longer lists it, so it reads as
     * lapsed instead of awaiting a decision or deferred.
     *
     * A record restored without a mappable enrolment is left at its stored status, as nothing is
     * known about its enrolment.
     *
     * @param mixed $value The stored status.
     * @param stdClass $row Row being rendered, carrying outcomeenrolstatus, outcomeenroltimeend
     *                      and outcomeueid.
     * @return string Localised sentence.
     */
    public static function outcome($value, stdClass $row): string {
        return match (self::outcome_state($value, $row)) {
            self::OUTCOME_CANCELLED => get_string('outcomecancelled', 'enrol_apply'),
            self::OUTCOME_UNKNOWN => submissionhelper::status_label((int) $value),
            self::OUTCOME_NEVERDECIDED => get_string('outcomeneverdecided', 'enrol_apply'),
            self::OUTCOME_LAPSED => get_string('outcomelapsed', 'enrol_apply'),
            self::OUTCOME_AWAITING => get_string('outcomeawaiting', 'enrol_apply'),
            self::OUTCOME_WAITING => get_string('outcomewaiting', 'enrol_apply'),
            self::OUTCOME_UNENROLLED => get_string('outcomeunenrolled', 'enrol_apply'),
            self::OUTCOME_APPROVED => get_string('outcomeapproved', 'enrol_apply'),
            self::OUTCOME_EXPIRED => get_string('outcomeexpired', 'enrol_apply'),
            self::OUTCOME_SUSPENDED => get_string('outcomesuspended', 'enrol_apply'),
            // Not a vocabulary member; the status formatter's own rule applies.
            default => self::status($value, $row),
        };
    }

    /**
     * Which of the outcomes {@see outcome()} words a record and its live enrolment amount to.
     *
     * The one reading of the pair, shared by the sentence the review page's history shows and the
     * report's status label ({@see outcome_label()}), so the two cannot split a row differently.
     *
     * @param mixed $value The stored status.
     * @param stdClass $row Row being rendered, carrying outcomeenrolstatus, outcomeenroltimeend
     *                      and outcomeueid.
     * @return string One of the OUTCOME_ constants.
     */
    protected static function outcome_state($value, stdClass $row): string {
        $recordstatus = (int) $value;
        $unknown = (int) ($row->outcomeueid ?? 0) === 0;
        $enrolstatus = $row->outcomeenrolstatus ?? null;
        $gone = !$unknown && ($enrolstatus === null || $enrolstatus === '');

        if ($recordstatus === submissionhelper::STATUS_CANCELLED) {
            return self::OUTCOME_CANCELLED;
        }

        if ($unknown) {
            return self::OUTCOME_UNKNOWN;
        }

        if ($recordstatus === submissionhelper::STATUS_PENDING || $recordstatus === submissionhelper::STATUS_WAITING) {
            if ($gone) {
                return self::OUTCOME_NEVERDECIDED;
            }

            // An undecided application whose period has run out is not in the queue any more.
            $enrolment = (object) [
                'status' => (int) $enrolstatus,
                'timeend' => (int) ($row->outcomeenroltimeend ?? 0),
            ];
            if (!queue::is_awaiting_decision($enrolment)) {
                return self::OUTCOME_LAPSED;
            }

            return $recordstatus === submissionhelper::STATUS_PENDING ? self::OUTCOME_AWAITING : self::OUTCOME_WAITING;
        }

        if ($recordstatus !== submissionhelper::STATUS_APPROVED) {
            return self::OUTCOME_OTHER;
        }

        if ($gone) {
            return self::OUTCOME_UNENROLLED;
        }

        if ((int) $enrolstatus === ENROL_USER_ACTIVE) {
            return self::OUTCOME_APPROVED;
        }

        $enrolment = (object) [
            'status' => (int) $enrolstatus,
            'timeend' => (int) ($row->outcomeenroltimeend ?? 0),
        ];
        if (!queue::is_awaiting_decision($enrolment)) {
            return self::OUTCOME_EXPIRED;
        }

        return self::OUTCOME_SUSPENDED;
    }

    /**
     * The outcome as a Design System status label, with what the record says under it when the
     * live enrolment has since moved away from it.
     *
     * The label states what is true now, in one tone; the line under it, only where the two
     * differ, names the record's own decision ("Approved, now unenrolled"), so the record and the
     * enrolment are read in one cell rather than in three columns. An admission by a group key
     * that no person has decided over since reads as such ({@see submissionhelper::admitted_by_key()}).
     *
     * Both parts are this plugin's own strings and carry no "<", and they are separated by a
     * literal newline outside any tag: the download decodes entities and then drops tag-shaped
     * runs line by line, so it keeps "Unenrolled" and "Approved, now unenrolled" as two lines.
     *
     * @param mixed $value The stored status.
     * @param stdClass $row Row being rendered, carrying what {@see outcome_state()} reads plus
     *     outcomekeyoutcome, outcomeapplicant and outcomedecidedby.
     * @return string The label's markup, and the record's line when there is one.
     */
    public static function outcome_label($value, stdClass $row): string {
        if ($value === null || $value === '') {
            return '';
        }

        $record = submissionhelper::status_label((int) $value);
        $state = self::outcome_state($value, $row);
        $keyadmission = $state === self::OUTCOME_APPROVED && self::is_key_admission(
            (int) $value,
            $row->outcomekeyoutcome ?? null,
            $row->outcomeapplicant ?? null,
            $row->outcomedecidedby ?? null
        );

        [$label, $tone, $sub] = match ($state) {
            self::OUTCOME_CANCELLED => [get_string('outcomecancelled', 'enrol_apply'), 'danger', ''],
            self::OUTCOME_UNKNOWN => [$record, 'neutral', ''],
            self::OUTCOME_NEVERDECIDED, self::OUTCOME_UNENROLLED => [
                get_string('outcomelabelunenrolled', 'enrol_apply'),
                'neutral',
                get_string('outcomenowunenrolled', 'enrol_apply', $record),
            ],
            self::OUTCOME_LAPSED => [
                get_string('outcomelabellapsed', 'enrol_apply'),
                'neutral',
                get_string('outcomenowlapsed', 'enrol_apply', $record),
            ],
            self::OUTCOME_AWAITING => [get_string('outcomeawaiting', 'enrol_apply'), 'warning', ''],
            self::OUTCOME_WAITING => [get_string('outcomewaiting', 'enrol_apply'), 'info', ''],
            self::OUTCOME_APPROVED => $keyadmission
                ? [get_string('outcomelabelkey', 'enrol_apply'), 'brand', '']
                : [$record, 'success', ''],
            self::OUTCOME_EXPIRED => [
                get_string('outcomelabelexpired', 'enrol_apply'),
                'neutral',
                get_string('outcomenowexpired', 'enrol_apply', $record),
            ],
            // Back in the queue, so it is waiting on somebody, as a pending application is.
            self::OUTCOME_SUSPENDED => [
                get_string('enrolmentsuspended', 'enrol_apply'),
                'warning',
                get_string('outcomenowsuspended', 'enrol_apply', $record),
            ],
            default => [self::status($value, $row), 'neutral', ''],
        };

        // A literal per tone, so a scan for the class finds every one of them.
        $class = match ($tone) {
            'success' => 'enrol_apply-status enrol_apply-status--success',
            'warning' => 'enrol_apply-status enrol_apply-status--warning',
            'info' => 'enrol_apply-status enrol_apply-status--info',
            'danger' => 'enrol_apply-status enrol_apply-status--danger',
            'brand' => 'enrol_apply-status enrol_apply-status--brand',
            default => 'enrol_apply-status enrol_apply-status--neutral',
        };

        $cell = '<span class="' . $class . '">' . self::escape($label) . '</span>';
        if ($sub !== '') {
            $cell .= "\n" . '<span class="enrol_apply-reportsub">' . self::escape($sub) . '</span>';
        }

        return $cell;
    }

    /**
     * Whether a record is an admission by a group key that no person has decided over since.
     *
     * {@see submissionhelper::admitted_by_key()} on the record's own fields, and only while the
     * record still says approved: a later decision restamps the decider, so the applicant being
     * the recorded decider is what keeps the admission the key's (G11).
     *
     * @param int $status The record's status.
     * @param mixed $keyoutcome The record's keyoutcome.
     * @param mixed $userid The applicant.
     * @param mixed $decidedby The recorded decider.
     * @return bool True for an admission by a key.
     */
    protected static function is_key_admission(int $status, $keyoutcome, $userid, $decidedby): bool {
        $outcome = (int) $keyoutcome;
        if ($status !== submissionhelper::STATUS_APPROVED || $outcome === submissionhelper::KEY_NONE) {
            return false;
        }

        return submissionhelper::admitted_by_key((object) [
            'outcome' => $outcome,
            'userid' => (int) $userid,
            'decidedby' => (int) $decidedby,
        ]);
    }

    /**
     * A date in the short form, with the full one in a title.
     *
     * Both spellings are userdate()'s, escaped; the title sits inside the tag, which the download
     * drops, so the exported cell is the short date alone.
     *
     * @param mixed $value Stored timestamp.
     * @param stdClass $row Row being rendered.
     * @return string The date's markup, or empty for no date.
     */
    public static function shortdate($value, stdClass $row): string {
        $time = (int) $value;
        if ($time <= 0) {
            return '';
        }

        return '<span title="' . s(userdate($time)) . '">'
            . s(userdate($time, get_string('strftimedatetimeshort', 'core_langconfig'))) . '</span>';
    }

    /**
     * How long an application waited for a first response, in calendar days and hours (AR-D4,
     * AR-D6).
     *
     * Measured to the first decision, and to now while there is none, in which case the wait is
     * still running and says so ("4 d, open"). Under the value, on lines of their own: a record
     * decided before its first decision was recorded says it was measured to its last decision;
     * a deferred one says how long it has been open since it was submitted; one resolved later
     * than its first response says after how long. An admission by a key had no person to wait
     * for and reads as such rather than as a turnaround of a few seconds.
     *
     * An aggregated value (a custom report's average, minimum or maximum) arrives without the
     * row's other fields, so it is worded as a duration and nothing more.
     *
     * The lines carry no markup, for the reason {@see decided()} gives.
     *
     * @param mixed $value Seconds waited, as the column's SQL computes them.
     * @param stdClass $row Row being rendered, carrying waitdecided, waitstatus, waitkeyoutcome,
     *     waitapplicant, waitdecidedby and waitresolution, and waitfirst once the first decision's
     *     column exists.
     * @param mixed $argument Unused; core passes the column's callback argument.
     * @param string|null $aggregation The aggregation applied to the column, null for none.
     * @return string The wait, or empty when there is none.
     */
    public static function wait($value, stdClass $row, $argument = null, ?string $aggregation = null): string {
        if ($value === null || $value === '') {
            return '';
        }

        $text = self::duration((int) round((float) $value));
        if ($aggregation !== null) {
            return $text;
        }

        $keyadmission = self::is_key_admission(
            (int) ($row->waitstatus ?? -1),
            $row->waitkeyoutcome ?? null,
            $row->waitapplicant ?? null,
            $row->waitdecidedby ?? null
        );
        if ($keyadmission) {
            return get_string('waitbykey', 'enrol_apply');
        }

        $decided = (int) ($row->waitdecided ?? 0);
        if ($decided <= 0) {
            return get_string('waitopen', 'enrol_apply', $text);
        }

        $lines = [$text];
        $first = (int) ($row->waitfirst ?? 0);
        if ($first <= 0) {
            $lines[] = get_string('waitlastdecision', 'enrol_apply');
        }
        $resolution = self::duration((int) ($row->waitresolution ?? 0));
        if ((int) ($row->waitstatus ?? -1) === submissionhelper::STATUS_WAITING) {
            $lines[] = get_string('waitdeferredopen', 'enrol_apply', $resolution);
        } else if ($first > 0 && $first !== $decided) {
            $lines[] = get_string('waitresolvedafter', 'enrol_apply', $resolution);
        }

        return implode("\n", $lines);
    }

    /**
     * How long an application waited for a resolution: an approval or a cancellation.
     *
     * Worded as {@see wait()} words its value, "open" for a pending or deferred application and
     * "By key" for an admission by a key; an aggregated value as a bare duration.
     *
     * @param mixed $value Seconds waited, as the column's SQL computes them.
     * @param stdClass $row Row being rendered, carrying resolutionstatus, resolutionkeyoutcome,
     *     resolutionapplicant and resolutiondecidedby.
     * @param mixed $argument Unused; core passes the column's callback argument.
     * @param string|null $aggregation The aggregation applied to the column, null for none.
     * @return string The wait, or empty when there is none.
     */
    public static function resolution($value, stdClass $row, $argument = null, ?string $aggregation = null): string {
        if ($value === null || $value === '') {
            return '';
        }

        $text = self::duration((int) round((float) $value));
        if ($aggregation !== null) {
            return $text;
        }

        $status = (int) ($row->resolutionstatus ?? -1);
        $keyadmission = self::is_key_admission(
            $status,
            $row->resolutionkeyoutcome ?? null,
            $row->resolutionapplicant ?? null,
            $row->resolutiondecidedby ?? null
        );
        if ($keyadmission) {
            return get_string('waitbykey', 'enrol_apply');
        }
        if ($status === submissionhelper::STATUS_PENDING || $status === submissionhelper::STATUS_WAITING) {
            return get_string('waitopen', 'enrol_apply', $text);
        }

        return $text;
    }

    /**
     * A number of seconds as calendar time: minutes under an hour, hours under a day, days and
     * hours under ten days, days beyond.
     *
     * @param int $seconds Seconds; a negative value, which only a restore or a clock change can
     *     produce, reads as no time at all.
     * @return string The duration.
     */
    public static function duration(int $seconds): string {
        $seconds = max(0, $seconds);
        if ($seconds < HOURSECS) {
            return get_string('waitminutes', 'enrol_apply', max(1, (int) round($seconds / MINSECS)));
        }
        if ($seconds < DAYSECS) {
            return get_string('waithours', 'enrol_apply', (int) round($seconds / HOURSECS));
        }

        $days = intdiv($seconds, DAYSECS);
        $hours = (int) round(($seconds - $days * DAYSECS) / HOURSECS);
        if ($hours === 24) {
            $days++;
            $hours = 0;
        }
        if ($hours > 0 && $days < 10) {
            return get_string('waitdayshours', 'enrol_apply', (object) ['days' => $days, 'hours' => $hours]);
        }

        return get_string('waitdays', 'enrol_apply', $days);
    }

    /**
     * The decision in one cell: when, and by whom.
     *
     * The short date ({@see shortdate()}), then on its own line "by" the decider's full name, or
     * "By key" and the key's group for an admission by a key ({@see is_key_admission()}). The
     * group is named under {@see key_group()}'s rule, so a group the reader may not see leaves
     * "By key" alone, which the admission route already says. A decider the record no longer
     * names (pseudonymised, or never stamped) leaves the date alone. "Not yet" for a record no
     * decision has touched.
     *
     * The second line carries no markup: the download drops tag-shaped runs line by line after
     * decoding entities, so a name holding "<" must not share a line with a tag.
     *
     * @param mixed $value The record's timedecided.
     * @param stdClass $row Row being rendered, carrying decidedstatus, decidedkeyoutcome,
     *     decidedapplicant, decidedby, the decider's name fields prefixed "decider" and deciderid,
     *     and the key group's decidedkeygroupname, decidedkeygroupid, decidedkeygroupcourseid and
     *     decidedkeygroupvisibility.
     * @return string The cell.
     */
    public static function decided($value, stdClass $row): string {
        $time = (int) $value;
        if ($time <= 0) {
            return get_string('decidednotyet', 'enrol_apply');
        }

        $lines = [self::shortdate($time, $row)];
        $keyadmission = self::is_key_admission(
            (int) ($row->decidedstatus ?? -1),
            $row->decidedkeyoutcome ?? null,
            $row->decidedapplicant ?? null,
            $row->decidedby ?? null
        );
        if ($keyadmission) {
            $group = self::key_group($row->decidedkeygroupname ?? null, (object) [
                'keygroupoutcome' => $row->decidedkeyoutcome ?? null,
                'keygroupid' => $row->decidedkeygroupid ?? null,
                'keygroupcourseid' => $row->decidedkeygroupcourseid ?? null,
                'keygroupvisibility' => $row->decidedkeygroupvisibility ?? null,
            ]);
            $lines[] = $group === ''
                ? get_string('decidedbykeyonly', 'enrol_apply')
                : get_string('decidedbykey', 'enrol_apply', $group);
        } else if (!empty($row->deciderid)) {
            $decider = new stdClass();
            foreach (\core_user\fields::get_name_fields() as $field) {
                $decider->{$field} = (string) ($row->{'decider' . $field} ?? '');
            }
            $viewfullnames = has_capability('moodle/site:viewfullnames', \context_system::instance());
            $lines[] = get_string('decidedbyname', 'enrol_apply', self::escape(fullname($decider, $viewfullnames)));
        }

        return implode("\n", $lines);
    }

    /**
     * The application's group and the groups its approval joined, in one cell.
     *
     * "Key: <group>" for a group key presented, "Chose: <group>" for a group chosen (the record
     * holds one or the other, never both), then "Joined" and the groups the last approval left the
     * applicant in. Every name goes through the visibility formatter of its own column
     * ({@see key_group()}, {@see chosen_group()}, {@see joined_groups()}), so a group the reader may
     * not see is never named, and a line whose group is withheld is left out whole rather than
     * kept with an empty name. Plain text, one line each, for the reason {@see decided()} gives.
     *
     * @param mixed $value The key group's name, null when the join found nothing.
     * @param stdClass $row Row being rendered, carrying groupkeyoutcome, groupkeyid,
     *     groupkeycourseid and groupkeyvisibility; groupchosenname, groupchosenid,
     *     groupchosencourseid and groupchosenvisibility once the chosen group's column exists; and
     *     groupjoined.
     * @return string The cell, empty when there is no group to name.
     */
    public static function group($value, stdClass $row): string {
        $lines = [];

        if ((int) ($row->groupkeyoutcome ?? 0) !== submissionhelper::KEY_NONE) {
            $name = self::key_group($value, (object) [
                'keygroupoutcome' => $row->groupkeyoutcome,
                'keygroupid' => $row->groupkeyid ?? null,
                'keygroupcourseid' => $row->groupkeycourseid ?? null,
                'keygroupvisibility' => $row->groupkeyvisibility ?? null,
            ]);
            if ($name !== '') {
                $lines[] = get_string('reportgroupkey', 'enrol_apply', $name);
            }
        } else if (property_exists($row, 'groupchosenid')) {
            $name = self::chosen_group($row->groupchosenname ?? null, (object) [
                'chosengroupid' => $row->groupchosenid,
                'chosengroupcourseid' => $row->groupchosencourseid ?? null,
                'chosengroupvisibility' => $row->groupchosenvisibility ?? null,
            ]);
            if ($name !== '') {
                $lines[] = get_string('reportgroupchose', 'enrol_apply', $name);
            }
        }

        $joined = self::joined_groups($row->groupjoined ?? null, new stdClass());
        if ($joined !== '') {
            $lines[] = get_string('reportgroupjoined', 'enrol_apply', $joined);
        }

        return implode("\n", $lines);
    }

    /**
     * A timestamp, or a dash where there is none.
     *
     * @param mixed $value Stored timestamp.
     * @param stdClass $row Row being rendered.
     * @return string Formatted date, or a dash for an undecided record.
     */
    public static function timeornever($value, stdClass $row): string {
        if (empty($value)) {
            // An undecided record carries 0, which userdate() would render as 1970.
            return '-';
        }

        return userdate((int) $value);
    }

    /**
     * A stored free-text value, escaped for a raw HTML cell without losing anything.
     *
     * Not format_text(FORMAT_PLAIN): that runs s() and nl2br(), giving this column both defects
     * snapshot() avoids - s() puts "&#039;" into the download, and an injected "<br />" supplies
     * the ">" that lets a decoded "<" swallow the rest of its line. It uses the same escape()
     * instead, and its newlines are drawn by the same CSS.
     *
     * @param mixed $value Stored text.
     * @param stdClass $row Row being rendered.
     * @return string The text, safe to place in a table cell.
     */
    public static function plaintext($value, stdClass $row): string {
        if ($value === null || $value === '') {
            return '';
        }

        return self::escape((string) $value);
    }

    /**
     * The frozen profile snapshot, as label and value pairs the reader is entitled to see.
     *
     * The one place in this plugin where a display callback may hide values. It is sound only
     * because the column has no filter and is not sortable, so no SQL path lets a reader recover
     * what the callback drops. The identity columns and filters are withheld by absence instead:
     * get_identity_columns() and get_identity_filters() return nothing outside
     * moodle/site:viewuseridentity.
     *
     * Neither label nor value goes through format_string(): under the default
     * formatstringstriptags it runs strip_tags(), which reads a bare "<" as a tag and deletes the
     * rest of the value ("A<B and R&D" becomes "A"). The form cannot produce such a value (its
     * fields are PARAM_TEXT), but a restore writes the answers and the comment verbatim from the
     * archive. Labels need escaping too: they are stored in the plain spelling of
     * fields::label($key, false), and a restored label is arbitrary. A Report Builder cell is raw
     * HTML, so escape() is the sink, and it is lossless.
     *
     * The separator is a literal newline carrying no markup. The download path,
     * base_export_format::format_text(), runs html_entity_decode() before removing tag-shaped
     * runs, so an escaped "&lt;" becomes a real "<" that would swallow everything up to the next
     * ">" on its line - which a "<br />" would supply. The tag pattern stops at a newline, so a
     * bare newline exports intact. On screen, styles.css gives the cell white-space: pre-line.
     *
     * $visible is decided by the report, which has a context to judge the reader in (the course
     * report uses {@see visible_keys()}); a display callback has none. Core always passes the
     * registered argument, null when none was registered, so a default here is unreachable and
     * null must be the restrictive case: names only. Only ALL_FIELDS lifts it.
     *
     * @param mixed $value The record's answers, as submission::aggregate_sql() concatenates them.
     * @param stdClass $row Row being rendered.
     * @param mixed $visible What visible_keys() returned, or ALL_FIELDS; anything else means names only.
     * @return string One "label: value" pair per line the reader may see.
     */
    public static function snapshot($value, stdClass $row, $visible = null): string {
        if ($value === null || $value === '') {
            return '';
        }

        $entries = submissionhelper::parse_aggregate((string) $value);
        if (!$entries) {
            return '';
        }

        $lines = [];
        foreach ($entries as $entry) {
            // A $visible no report judged (null) shows the name fields only; see shows().
            if (!self::shows($entry['key'], $visible)) {
                continue;
            }
            $lines[] = self::escape($entry['label']) . ': ' . self::escape($entry['value']);
        }

        return implode("\n", $lines);
    }

    /**
     * One snapshot label or value, escaped for a raw HTML cell without losing anything.
     *
     * ENT_COMPAT rather than s()'s ENT_QUOTES: s() writes "O&#039;Brien", and the download path
     * decodes with ENT_COMPAT, which leaves that entity in the CSV. The value lands in a text
     * node, never an attribute, so an unescaped apostrophe is safe. Tag-shaped text is lost in
     * the download under either flag.
     *
     * ENT_SUBSTITUTE is kept from s(): without it htmlspecialchars() returns an empty string for
     * the whole cell on malformed UTF-8.
     *
     * @param string $text Stored label or value.
     * @return string The text, safe to place in a table cell.
     */
    protected static function escape(string $text): string {
        return htmlspecialchars($text, ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE);
    }

    /**
     * Which snapshot keys a reader may see in the given context.
     *
     * The single masking rule for the snapshot, shared by the course report (as the callback's
     * argument), the queue table, the review page and the approver's e-mail. Without
     * moodle/site:viewuseridentity in the course, only the name fields. With it, every key except
     * the ones core would not show this reader on the applicant's profile:
     *
     * - an identity column (IDENTITY_COLUMNS) that showuseridentity does not name;
     * - a column named in hiddenuserfields, without moodle/course:viewhiddenuserfields (inside a
     *   course) or moodle/user:viewhiddendetails (outside one), as get_identity_fields() decides;
     * - a custom profile field its own visibility hides ({@see self::custom_visible()}).
     *
     * The answers to the method's own questions (a_ keys) are not profile fields and follow the
     * identity capability alone. Withheld fields leave no marker: one appearing only where there
     * is data would reveal that a value exists.
     *
     * Returns ALL_FIELDS rather than null for the unrestricted reader, because null is what core
     * passes when no argument was registered; see snapshot().
     *
     * @param \context $context Context to judge the reader in.
     * @param int|null $userid Reader to judge, null for the current user (the e-mail judges each
     *        recipient).
     * @return array|bool What shows() reads: a list of visible keys, an EXCEPT rule, or ALL_FIELDS.
     */
    public static function visible_keys(\context $context, ?int $userid = null): array|bool {
        global $CFG, $DB;

        // In a course, not through a course role the reader's lapsed enrolment left behind.
        if (!\enrol_apply\local\groupscope::holds_in_course('moodle/site:viewuseridentity', $context, $userid)) {
            return self::name_keys();
        }

        $except = [];

        // Lower-cased as get_identity_fields() returns them.
        $identity = array_map('core_text::strtolower', self::config_list((string) $CFG->showuseridentity));
        $hidden = self::config_list((string) ($CFG->hiddenuserfields ?? ''));
        $hiddencapability = $context->get_course_context(false)
            ? 'moodle/course:viewhiddenuserfields'
            : 'moodle/user:viewhiddendetails';
        $canviewhidden = !$hidden || \enrol_apply\local\groupscope::holds_in_course($hiddencapability, $context, $userid);
        foreach (array_unique(array_merge(self::IDENTITY_COLUMNS, $hidden)) as $column) {
            $unlisted = in_array($column, self::IDENTITY_COLUMNS, true) && !in_array($column, $identity, true);
            $hiddenhere = in_array($column, $hidden, true) && !$canviewhidden;
            if ($unlisted || $hiddenhere) {
                $except[] = applyfields::standard_key($column);
            }
        }

        $viewalldetails = \enrol_apply\local\groupscope::holds_in_course('moodle/user:viewalldetails', $context, $userid);
        // A PROFILE_VISIBLE_NONE field is judged at the system, as core does for a field of a user.
        $viewsystem = $viewalldetails
            && has_capability('moodle/user:viewalldetails', \context_system::instance(), $userid);
        $knowncustom = [];
        foreach ($DB->get_records('user_info_field', null, '', 'id, visible') as $field) {
            $key = applyfields::custom_key((int) $field->id);
            $knowncustom[] = $key;
            if (!self::custom_visible((int) $field->visible, $viewalldetails, $viewsystem)) {
                $except[] = $key;
            }
        }

        if (!$except && $viewalldetails) {
            return self::ALL_FIELDS;
        }

        return [self::EXCEPT => $except, self::KNOWNCUSTOM => $viewalldetails ? null : $knowncustom];
    }

    /**
     * The names in a comma-separated site setting.
     *
     * @param string $value Stored setting, such as showuseridentity or hiddenuserfields.
     * @return array Non-empty names, trimmed.
     */
    protected static function config_list(string $value): array {
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn(string $name): bool => $name !== ''));
    }

    /**
     * Whether a reader holding moodle/site:viewuseridentity may see a custom profile field.
     *
     * Mirrors profile_field_base::is_visible() for a field read with no user (userid 0) in the
     * reader's context, which is how core_user\fields::get_identity_fields() calls it: everyone
     * sees a PROFILE_VISIBLE_ALL field, a PROFILE_VISIBLE_TEACHERS one follows
     * moodle/site:viewuseridentity (already held here), and PROFILE_VISIBLE_PRIVATE and
     * PROFILE_VISIBLE_NONE need moodle/user:viewalldetails, the latter judged at the system context as
     * core judges a field it reads for a user. Inlined rather than called because
     * is_visible() judges the current user only, and the e-mail judges each recipient. The
     * applicant's own-field exception does not arise: a reader is never judged on their own
     * application here.
     *
     * @param int $visibility The field's user_info_field.visible.
     * @param bool $viewalldetails Whether the reader holds moodle/user:viewalldetails in the context.
     * @param bool $viewsystem Whether the reader holds it at the system context, which judges NONE.
     * @return bool True when the field may be shown.
     */
    protected static function custom_visible(int $visibility, bool $viewalldetails, bool $viewsystem): bool {
        global $CFG;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        // Core defines the PROFILE_VISIBLE_* constants as strings, so compare the cast value.
        if ($visibility === (int) PROFILE_VISIBLE_ALL || $visibility === (int) PROFILE_VISIBLE_TEACHERS) {
            return true;
        }

        return $visibility === (int) PROFILE_VISIBLE_NONE ? $viewsystem : $viewalldetails;
    }

    /**
     * Whether one snapshot key may be shown, given what visible_keys() returned.
     *
     * The one reader of every shape that value takes, so no caller repeats the rule: ALL_FIELDS
     * shows every key; an EXCEPT rule shows every key it does not list, except a c_ key naming a
     * field that no longer exists when KNOWNCUSTOM is a list; a plain list shows its own keys; and
     * anything else, null included, shows the name fields only. Before all of those, a key that is
     * not an s_, c_ or a_ key is never shown.
     *
     * @param string $key Snapshot key.
     * @param mixed $visible What visible_keys() returned.
     * @return bool True when the key may be shown.
     */
    public static function shows(string $key, $visible): bool {
        // Fail closed on a key no rule here was written for, whatever the reader may see.
        if (
            !str_starts_with($key, applyfields::SOURCE_STANDARD)
            && !str_starts_with($key, applyfields::SOURCE_CUSTOM)
            && !str_starts_with($key, \enrol_apply\local\answers::SOURCE_ANSWER)
        ) {
            return false;
        }
        if ($visible === self::ALL_FIELDS) {
            return true;
        }
        if (!is_array($visible)) {
            return in_array($key, self::name_keys(), true);
        }
        if (!array_key_exists(self::EXCEPT, $visible)) {
            return in_array($key, $visible, true);
        }
        if (in_array($key, $visible[self::EXCEPT], true)) {
            return false;
        }
        $known = $visible[self::KNOWNCUSTOM] ?? null;
        if (is_array($known) && str_starts_with($key, applyfields::SOURCE_CUSTOM) && !in_array($key, $known, true)) {
            return false;
        }

        return true;
    }

    /**
     * The snapshot keys any reader of this report may see.
     *
     * Core's name fields and nothing else; the address, phone numbers, employer and custom fields
     * are governed by the identity capability. The phonetic, middle and alternate names are
     * included because core classes them as name data, although the default fullnamedisplay
     * shows only first and last name.
     *
     * @return array List of field keys.
     */
    protected static function name_keys(): array {
        return [
            applyfields::standard_key('firstname'),
            applyfields::standard_key('lastname'),
            applyfields::standard_key('firstnamephonetic'),
            applyfields::standard_key('lastnamephonetic'),
            applyfields::standard_key('middlename'),
            applyfields::standard_key('alternatename'),
        ];
    }
}
