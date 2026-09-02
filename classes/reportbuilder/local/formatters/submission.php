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
use enrol_apply\local\submission as submissionhelper;
use stdClass;

/**
 * Display callbacks for the application record's columns.
 *
 * Every callback here takes its first parameter UNTYPED, and that is load bearing rather than
 * lazy - though not for the reason it looks like. Core does NOT change the value's type under
 * aggregation: format_value() passes the column's original type through, and
 * aggregation/base.php:165 says so in as many words. The requirement is NULLABILITY, which
 * core states in the same docblock as the type rule (column.php:500-502): the first argument
 * must be nullable wherever the field or the entity's join can produce null - and both
 * comment and userinfodata are NOTNULL="false" in db/install.xml. groupconcat additionally
 * restores a null marker before re-applying the callbacks value by value. A typed first
 * parameter turns any of that into a TypeError at render time, on a page that has already
 * passed every static gate.
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
     * Three states have to be told apart and only two of them are obvious. A NULL live status
     * means the join found nothing, which is a real "no longer enrolled". But a record restored
     * from an archive whose enrolment could not be mapped carries userenrolmentid = 0
     * (restore_enrol_apply_plugin.class.php casts a false mapping to 0), and zero also finds
     * nothing - so reporting it as "no longer enrolled" would be a fresh falsehood of exactly
     * the kind this column exists to remove. It is checked FIRST for that reason.
     *
     * @param mixed $value The live user_enrolments.status, null when there is no enrolment.
     * @param stdClass $row Row being rendered, carrying liveueid.
     * @return string Localised label.
     */
    public static function enrolment($value, stdClass $row): string {
        global $CFG;

        /* ENROL_APPLY_USER_WAIT lives in the plugin's lib.php, which is not autoloaded - nothing
           guarantees it has been included by the time a report renders, and an undefined constant
           is a fatal on PHP 8. This is the first autoloaded class in the plugin to need it.
           Deliberately not substituted with submission::STATUS_WAITING, which also happens to be
           2: one is the enrolment's status and the other the record's, and they are equal by
           coincidence rather than by contract. */
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
     * The stored status is the last decision this plugin's own state machine took, and it is
     * not the whole story: the participants page, course reset, user deletion and the expiry
     * sweep all change an enrolment without touching the record. Approved-and-enrolled and
     * approved-then-unenrolled are otherwise literally the same row.
     *
     * The suspended case is split by timeend because the two mean opposite things to the
     * operator. A suspension with no period is a manual one, and the queue's predicate
     * (status != active AND (timeend = 0 OR timeend > now)) puts that row straight back in the
     * approval queue - so the report must say so, or the queue and the report disagree in public
     * with neither mentioning the other. A suspension with a period in the past is the expiry
     * sweep's work and does NOT re-queue.
     *
     * A record restored without a mappable enrolment is left at its stored status rather than
     * being described: nothing is known about its enrolment, and guessing is what this column
     * exists to stop.
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

        $timeend = (int) ($row->outcomeenroltimeend ?? 0);
        if ($timeend > 0 && $timeend < time()) {
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
     * format_text(FORMAT_PLAIN) is the obvious choice and is the wrong one. It strips nothing:
     * its whole branch is s(), then rebuildnolinktag(), then a double-space substitution, then
     * nl2br() (lib/classes/formatting.php:243-248, identical on 5.1 and 5.2). So it would give
     * this column both of the defects snapshot() below is shaped to avoid - s() puts "&#039;"
     * into the download, and the injected "<br />" supplies the ">" that lets a decoded "<"
     * swallow the rest of its line. It goes through the same escape() instead, and its
     * newlines are drawn by the same CSS.
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
     * This is the ONE place in this plugin where a display callback may hide values, and it is
     * sound only because the column it serves carries no filter and is not sortable - so there
     * is no SQL path by which a reader could recover what the callback drops. Every other
     * masking decision in this report is made by NOT ADDING the column and the filter at all:
     * get_identity_columns() and get_identity_filters() return an empty array outside
     * moodle/site:viewuseridentity, so absence rather than a callback is what withholds them.
     * (set_is_available() would be the other way to express it and this plugin does not use it;
     * it is declared separately on column and on filter, so it could not remove both at once.)
     *
     * Three details that are easy to get wrong, each measured on m502 rather than reasoned:
     *
     * Neither half goes through format_string(), which is the opposite of the reflex. Under the
     * default formatstringstriptags it runs strip_tags(), which reads a bare "<" as the start
     * of a tag and deletes everything after it - measured, "A<B and R&D" rendered as "A", tail
     * gone, nothing on either side to say so.
     *
     * Note carefully where such a value can come from, because the obvious answer is wrong. An
     * applicant cannot type one: every editable field on the form is PARAM_TEXT
     * (form/application_form.php:260, and :168 for the comment), and formslib cleans the whole
     * submission through clean_param() before get_data() ever sees it, so the tail is already
     * gone at submission. The route that survives is a RESTORE, which writes userinfodata and
     * comment verbatim out of an archive this site did not produce
     * (backup/moodle2/restore_enrol_apply_plugin.class.php:136-137). Escaping rather than
     * stripping is what makes that safe without making it lossy.
     *
     * The label needs no format_string() either, and not because one was already applied to all
     * of them: fields::label() returns a core lang string for a standard field and the bare key
     * for a field since deleted, and only its surviving-custom-field path calls format_string -
     * in the PLAIN spelling, because submitted_values() passes escape: false. Which is exactly
     * why escape() below has to run on the label as well as on the value.
     *
     * What the cell does need is the ESCAPED spelling, because a Report Builder cell is raw
     * HTML; that is how user:fullnamewithlink emits a link. The snapshot can hold the value of
     * a profile_field_textarea, which core declares PARAM_RAW - core's own comment on the field
     * is "We MUST clean this before display!" - so escape() below is the sink, and it is
     * lossless where format_string() was not.
     *
     * The separator is a literal newline carrying no markup, and that is a stronger constraint
     * than "the export strips tags". The download path is base_export_format::format_text()
     * (lib/table/classes/base_export_format.php:82-88, identical on 5.1 and 5.2), which runs
     * html_entity_decode() FIRST and only then removes tag-shaped runs. So markup here is not
     * merely dropped: an escaped "&lt;" decodes back to a real "<" and the run then eats
     * everything up to the next ">" on that line, which a "<br />" would supply. Measured,
     * nl2br() turns "City: A&lt;B and R&amp;D" into "City: A" in the export - but only when a
     * further line follows it, because nl2br inserts nothing after the last one and the run
     * needs a ">" to close on. The bare newline exports intact either way; the pattern excludes
     * \r\n exactly so a newline ends the run.
     * A newline breaks no line in HTML, so the column carries a class and styles.css gives it
     * white-space: pre-line. That sits on the cell, never in the value, so the export is blind
     * to it.
     *
     * The keys the reader may see arrive as the callback's third argument, decided once in the
     * report where the COURSE context is in hand. Deciding it here instead was the first
     * attempt and it was wrong: a display callback has no context, so it can only ask about the
     * system one - and a reader legitimately granted the capability in their course would then
     * see nothing at all. The identity columns beside this one are gated on the course context;
     * this must match them or the two disagree about the same reader.
     *
     * The third argument is fail-closed, and which value means "nobody told me" is decided by
     * core rather than by this signature. A column callback is NEVER called with three
     * arguments: column::format_value() invokes every one of them as
     * ($callable)($value, (object) $values, $arguments, null) - reportbuilder column.php:733,
     * the same line number on 5.1 and 5.2, and aggregation/base.php:171 for the aggregated
     * path - where $arguments is whatever was registered, and add_callback() and
     * set_callback() both default it to null (:508 and :520, both branches). So a parameter
     * default here is unreachable: a column registered with no argument passes null, not
     * nothing.
     *
     * That is why null is the restrictive state and not the permissive one. The entity
     * registers this callback bare, and a datasource that reuses the entity registers nothing
     * at all - so null is exactly the case where nobody has asked a context, and it must show
     * the names and nothing else. Only ALL_FIELDS lifts the restriction, and only a caller
     * holding a context passes it.
     *
     * An earlier version had these two the other way round and said in this docblock that the
     * default was restrictive. It was not: the entity's own bare add_callback() passed null,
     * which meant "show everything", so the guard was fail-open on every path that did not go
     * through the report.
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
            // Nobody asked a context, so this reader gets what every user list already shows.
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
     * ENT_COMPAT and not ENT_QUOTES, which is what s() uses, and the difference is a name with
     * an apostrophe. s() writes "O&#039;Brien"; the download path decodes with ENT_COMPAT,
     * which by definition leaves single quotes alone, so that entity reaches the CSV verbatim
     * and the manager reads "O&#039;Brien". Measured on m502: every case round-trips under
     * ENT_COMPAT except tag-shaped text, which both escapings lose because html_entity_decode()
     * restores the real tag before the pattern strips it; only the apostrophe distinguishes the
     * two. The value lands in a text node and never in an attribute, so leaving a bare
     * apostrophe unescaped costs nothing.
     *
     * ENT_SUBSTITUTE is kept from s()'s own flags. Measured on PHP 8.4.24, htmlspecialchars()
     * without it returns an EMPTY STRING on a malformed UTF-8 byte rather than dropping the
     * byte - the whole cell, not the character. No current caller can reach that, because
     * json_decode() rejects malformed UTF-8 and read_snapshot() then returns an empty list; it
     * is kept for the caller that arrives later without that protection.
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
     * Called from the report, where the course context is known, and handed to the callback as
     * an argument. Same rule as the identity columns beside it: with
     * moodle/site:viewuseridentity in the course, everything; without it, only the name parts
     * that every user list already shows.
     *
     * Withheld fields are withheld from EVERY row, not only from the rows that happen to hold
     * a value - a marker that appears only where there is data is a presence oracle.
     *
     * It returns ALL_FIELDS rather than null for the unrestricted reader, because null is the
     * value core passes when nobody registered an argument at all - see snapshot().
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
     * Core's name fields, and nothing else: the address, the phone numbers, the employer and
     * every custom field are what the identity capability governs. Note this is slightly more
     * than a default participants list renders - fullnamedisplay defaults to "language", whose
     * en string is firstname and lastname alone - so the phonetic, middle and alternate names
     * are here because core classes them as name rather than identity data, not because every
     * user list already shows them.
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
