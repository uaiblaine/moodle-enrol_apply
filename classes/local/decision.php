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
 * One decision posted from the queue, applied method by method where its messages differ.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

/**
 * One decision posted from the queue, applied method by method where its messages differ.
 *
 * The site-wide and mentee queues can select applications of several enrolment methods at once,
 * and the decision sheet then asks one message per method (MQ-D15), posted as
 * outcomemessages[<enrolid>]. The posted ids are grouped by the method {user_enrolments} says
 * they belong to, never by anything the post claims, and each group goes to the decision method
 * once with its own message. Per-row authorisation stays inside confirm_enrolment(),
 * wait_enrolment() and cancel_enrolment(), unchanged: grouping only chooses the message.
 *
 * Without per-method messages - the no-JavaScript path and every selection of one method - the
 * whole selection is one call with the plain message, exactly as manage.php made it before.
 *
 * A page script has no PHPUnit, so manage.php's half of this lives here to be testable.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class decision {
    /** @var int[] User enrolment ids the last confirm apply() call decided, see confirmed_ids(). */
    private static array $confirmed = [];

    /**
     * The user enrolments the last apply() call confirmed, which the posted list may exceed.
     *
     * What a post-decision read-back (the seats warning) may look at: the posted ids include rows
     * the decision skipped, among them rows outside the operator's groups.
     *
     * @return int[] User enrolment ids; empty after a wait or cancel decision.
     */
    public static function confirmed_ids(): array {
        return self::$confirmed;
    }

    /**
     * What a queue form posts beside the action and the selection, read and cleaned as manage.php reads it.
     *
     * One reader for every surface that posts the queue's contract - the table's form, the grid view's
     * form (built by its client with the same names, CQ-D4) and the review page's - so none of them can
     * be read differently; manage.php reads `formaction`, `userenrolments[]` and the sesskey itself.
     *
     * The message and the note are PARAM_TEXT and not PARAM_RAW: the message reaches the applicant's
     * notification, and the only formatting either is allowed is the line breaks the decider typed. The
     * message is escaped again at the sink in notify_applicant(); cleaning here as well keeps a forged
     * post from putting markup into the durable record, which outlives the enrolment it belongs to.
     *
     * The per-method messages are keyed by an enrol instance id, which the decision sheet asks for when
     * a selection on the site-wide or mentee queue spans methods (MQ-D15); which method a row belongs
     * to is read from the database by {@see groups()}, never from these keys, and an empty array is
     * one call for the whole selection.
     *
     * The groups and the role are cleaned to integers here and allowlisted per instance inside
     * confirm_enrolment(), because a site-wide batch can span courses and a group or role valid for
     * one application is not necessarily valid for the next. They are read unconditionally, not only
     * when the form offered a control, so the allowlist runs against whatever a forged post carries.
     * The note is always in the choice, because the writer tests the key with array_key_exists(): an
     * empty note clears the previous one, and only a caller omitting the key leaves it alone.
     *
     * @return array 'message' and 'note' (trimmed), 'messages' (enrol id => message), and 'decision'
     *     (groups, roleid and note, as confirm_enrolment() reads them).
     */
    public static function posted(): array {
        $message = trim(optional_param('outcomemessage', '', PARAM_TEXT));
        $note = trim(optional_param('decisionnote', '', PARAM_TEXT));

        return [
            'message' => $message,
            'note' => $note,
            'messages' => optional_param_array('outcomemessages', [], PARAM_TEXT),
            'decision' => [
                'groups' => optional_param_array('groups', [], PARAM_INT),
                'roleid' => optional_param('roleid', 0, PARAM_INT),
                'note' => $note,
            ],
        ];
    }

    /**
     * Apply one posted decision.
     *
     * An id that names no {user_enrolments} row lands in the group carrying the plain message, so
     * the decision method still meets it and skips it, and manage.php's "left alone" count, which
     * is the posted count minus the returned one, stays right. A message keyed by a method none
     * of the posted rows belongs to selects nothing and so changes nothing.
     *
     * @param string $action confirm, wait or cancel: manage.php's formaction.
     * @param array $userenrolmentids The posted userenrolments[], cleaned to integers.
     * @param string $message The plain message, already cleaned and trimmed.
     * @param array $messages Enrol instance id => message for that method's applicants, cleaned
     *     as PARAM_TEXT; empty when the post carried none.
     * @param array $decision Chosen groups, role and note, as confirm_enrolment() reads them;
     *     the same for every group.
     * @return int How many of the given applications were decided, summed over the calls.
     * @throws \moodle_exception When the action is none of the three decisions.
     */
    public static function apply(
        string $action,
        array $userenrolmentids,
        string $message,
        array $messages,
        array $decision
    ): int {
        if (!in_array($action, ['confirm', 'wait', 'cancel'], true)) {
            throw new \moodle_exception('invalidformaction', 'enrol_apply');
        }

        $decided = 0;
        self::$confirmed = [];
        foreach (self::groups($userenrolmentids, $message, $messages) as $group) {
            $decided += self::decide($action, $group['ids'], $group['message'], $decision);
        }

        return $decided;
    }

    /**
     * The posted ids grouped by the method they belong to, each group with its message.
     *
     * @param array $userenrolmentids The posted ids.
     * @param string $message The plain message.
     * @param array $messages Enrol instance id => message; empty for one group.
     * @return array List of ['ids' => int[], 'message' => string], in the order the methods first
     *     appear in the selection.
     */
    public static function groups(array $userenrolmentids, string $message, array $messages): array {
        global $DB;

        // Each id once: a repeated one would defer, and message the applicant, twice.
        $ids = array_values(array_unique(array_map('intval', array_values($userenrolmentids))));
        if (!$messages || !$ids) {
            return [['ids' => $ids, 'message' => $message]];
        }

        $permethod = [];
        foreach ($messages as $enrolid => $text) {
            $permethod[(int) $enrolid] = trim((string) $text);
        }

        // The method of each row, from the database: a post names ids, never what they belong to.
        $methodof = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk);
            $methodof += $DB->get_records_select_menu('user_enrolments', "id {$insql}", $params, '', 'id, enrolid');
        }

        $groups = [];
        foreach ($ids as $id) {
            $enrolid = isset($methodof[$id]) ? (int) $methodof[$id] : 0;
            // A method with no entry of its own, and an id naming nothing, take the plain message.
            $key = array_key_exists($enrolid, $permethod) ? $enrolid : 0;
            if (!isset($groups[$key])) {
                $groups[$key] = ['ids' => [], 'message' => $key ? $permethod[$key] : $message];
            }
            $groups[$key]['ids'][] = $id;
        }

        return array_values($groups);
    }

    /**
     * One call of the decision method the action names.
     *
     * @param string $action confirm, wait or cancel.
     * @param array $ids User enrolment ids.
     * @param string $message Message for these applicants.
     * @param array $decision Chosen groups, role and note.
     * @return int How many of them were decided.
     */
    protected static function decide(string $action, array $ids, string $message, array $decision): int {
        $plugin = enrol_get_plugin('apply');

        if ($action === 'confirm') {
            $count = (int) $plugin->confirm_enrolment($ids, $message, $decision);
            self::$confirmed = array_merge(self::$confirmed, $plugin->get_confirmed_enrolment_ids());

            return $count;
        }

        return match ($action) {
            'wait' => (int) $plugin->wait_enrolment($ids, $message, $decision),
            'cancel' => (int) $plugin->cancel_enrolment($ids, $message, $decision),
        };
    }
}
