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
     * Only a caller that has asked a context may pass it. An array is taken as the explicit
     * list of visible keys, which is what the report passes for a reader without the identity
     * capability. Every other value - and in particular null, which is what core supplies when
     * no argument was registered - means the names and nothing else.
     *
     * @var bool
     */
    public const ALL_FIELDS = true;

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
     * A record restored without a mappable enrolment is left at its stored status, as nothing is
     * known about its enrolment.
     *
     * @param mixed $value The stored status.
     * @param stdClass $row Row being rendered, carrying outcomeenrolstatus, outcomeenroltimeend
     *                      and outcomeueid.
     * @return string Localised sentence.
     */
    public static function outcome($value, stdClass $row): string {
        $recordstatus = (int) $value;
        $unknown = (int) ($row->outcomeueid ?? 0) === 0;
        $enrolstatus = $row->outcomeenrolstatus ?? null;
        $gone = !$unknown && ($enrolstatus === null || $enrolstatus === '');

        if ($recordstatus === submissionhelper::STATUS_CANCELLED) {
            return get_string('outcomecancelled', 'enrol_apply');
        }

        if ($unknown) {
            return submissionhelper::status_label($recordstatus);
        }

        if ($recordstatus === submissionhelper::STATUS_PENDING) {
            return $gone
                ? get_string('outcomeneverdecided', 'enrol_apply')
                : get_string('outcomeawaiting', 'enrol_apply');
        }

        if ($recordstatus === submissionhelper::STATUS_WAITING) {
            return $gone
                ? get_string('outcomeneverdecided', 'enrol_apply')
                : get_string('outcomewaiting', 'enrol_apply');
        }

        if ($recordstatus !== submissionhelper::STATUS_APPROVED) {
            // Not a vocabulary member; the status formatter's own rule applies.
            return self::status($value, $row);
        }

        if ($gone) {
            return get_string('outcomeunenrolled', 'enrol_apply');
        }

        if ((int) $enrolstatus === ENROL_USER_ACTIVE) {
            return get_string('outcomeapproved', 'enrol_apply');
        }

        $enrolment = (object) [
            'status' => (int) $enrolstatus,
            'timeend' => (int) ($row->outcomeenroltimeend ?? 0),
        ];
        if (!queue::is_awaiting_decision($enrolment)) {
            return get_string('outcomeexpired', 'enrol_apply');
        }

        return get_string('outcomesuspended', 'enrol_apply');
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
     * fields are PARAM_TEXT), but a restore writes userinfodata and comment verbatim from the
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
     * @param mixed $value Stored JSON envelope.
     * @param stdClass $row Row being rendered.
     * @param mixed $visible Keys the reader may see, or ALL_FIELDS; anything else means names only.
     * @return string One "label: value" pair per line the reader may see.
     */
    public static function snapshot($value, stdClass $row, $visible = null): string {
        if ($value === null || $value === '') {
            return '';
        }

        if ($visible !== self::ALL_FIELDS && !is_array($visible)) {
            // No report judged the reader, so show the name fields only.
            $visible = self::name_keys();
        }

        $entries = submissionhelper::read_snapshot((string) $value);
        if (!$entries) {
            return '';
        }

        $lines = [];
        foreach ($entries as $entry) {
            if ($visible !== self::ALL_FIELDS && !in_array($entry['key'], $visible, true)) {
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
     * The snapshot keys a reader may see in the given context.
     *
     * The single masking rule for the snapshot, shared by the course report (as the callback's
     * argument), the queue table and the review page. Same rule as the identity columns: with
     * moodle/site:viewuseridentity in the course, everything; without it, only the name fields.
     * Withheld fields leave no marker: one appearing only where there is data would reveal that a
     * value exists.
     *
     * Returns ALL_FIELDS rather than null for the unrestricted reader, because null is what core
     * passes when no argument was registered; see snapshot().
     *
     * @param \context $context Context to judge the reader in.
     * @return array|bool List of visible field keys, or ALL_FIELDS for no restriction.
     */
    public static function visible_keys(\context $context): array|bool {
        if (has_capability('moodle/site:viewuseridentity', $context)) {
            return self::ALL_FIELDS;
        }

        return self::name_keys();
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
