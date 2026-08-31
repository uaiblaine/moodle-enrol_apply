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

use context_course;
use stdClass;

/**
 * The wording that heads an instance's comment field, wherever it is read.
 *
 * A teacher who asks "Why do you want to join this course?" should see that question above the
 * answers, not the generic word this plugin ships. The instance stores the wording in
 * customtext2; this class is the one place that reads it, so the three surfaces that show it
 * cannot drift apart.
 *
 * **The escape flag is not optional, and the sinks disagree.** Two of the three render raw and
 * want the ESCAPED spelling: the queue's column header, which flexible_table::print_headers()
 * emits through html_writer::tag() (that helper concatenates its content and never escapes it),
 * and the applicant form's element label, which core renders through a triple stash in
 * element-template.mustache. The third, the review page's comment label, is a DOUBLE stash and
 * wants the PLAIN spelling - handing it the escaped one shows the reader the entities. The
 * switch is a parameter for the same reason core's own field_controller::get_formatted_name()
 * takes one: the caller knows its sink and the helper cannot.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class commentlabel {
    /**
     * @var string What a pre-2016 upstream build stored in customtext2 instead of a label.
     *
     * Upstream made customtext2 the notification recipient list in 2016 while still reading the
     * custom label from it, and the 2022 fix that moved the list to customtext3 retro-edited the
     * upgrade step, so a site already past that savepoint kept the value. db/upgrade.php clears
     * the stored ones; this constant exists because a RESTORE can bring one back - customtext2
     * is the one custom field restore_instance() does not sanitise, so it arrives verbatim from
     * an archive this site did not produce.
     *
     * Only this literal is recognised. The comma-separated user-id list the same column could
     * also hold is deliberately not, because it cannot be told apart from a label somebody might
     * genuinely type, and this one can.
     */
    public const LEGACY_MARKER = '$@ALL@$';

    /**
     * Whether the stored value is a leftover recipient list rather than a label.
     *
     * @param string|null $value Raw customtext2 value.
     * @return bool True when the value is the legacy marker and must not be shown to anybody.
     */
    public static function is_legacy_recipient_list(?string $value): bool {
        return trim((string) $value) === self::LEGACY_MARKER;
    }

    /**
     * The wording to head this instance's comment field with.
     *
     * @param stdClass $instance Course enrol instance, carrying customtext2 and courseid.
     * @param bool $escape Whether the sink renders raw and therefore needs the escaped spelling.
     * @return string The label, in the spelling the caller asked for.
     */
    public static function custom(stdClass $instance, bool $escape = true): string {
        $custom = (string) ($instance->customtext2 ?? '');

        if (trim($custom) === '' || self::is_legacy_recipient_list($custom)) {
            return get_string('applycomment', 'enrol_apply');
        }

        /* The course context and not the page's: this is read on the review page too, where
           queue::require_review_access() can return the APPLICANT'S user context, and a filter
           resolved against that would be answering about the wrong thing. */
        return format_string($custom, true, [
            'context' => context_course::instance($instance->courseid),
            'escape' => $escape,
        ]);
    }
}
