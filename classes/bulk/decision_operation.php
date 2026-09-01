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

namespace enrol_apply\bulk;

use course_enrolment_manager;
use enrol_apply\form\bulk_decision_form;
use enrol_apply\local\queue;
use enrol_bulk_enrolment_operation;
use moodle_url;
use stdClass;

/**
 * What the three participants-page bulk decisions have in common.
 *
 * Core's driver for this extension point is user/action_redir.php, and the two things it
 * does NOT do decide almost everything below. It performs no require_login() and no
 * require_capability() of its own anywhere in the bulk branch - measured on 5.1 and 5.2,
 * where the file is byte-identical and its only gates are confirm_sesskey() and a check
 * that the plugin is enabled site wide. And it hands process() an array it makes no
 * promise about beyond "users of a course", so nothing upstream guarantees the rows
 * belong to this plugin.
 *
 * The decision itself is never taken here. Every operation delegates to the plugin's own
 * confirm_enrolment(), wait_enrolment() or cancel_enrolment(), which is the one rule this
 * class exists to enforce: both core precedents (enrol_manual and enrol_self, character
 * for character the same SQL) write {user_enrolments} with a raw UPDATE and build the
 * event by hand, so \core_enrol\hook\before_user_enrolment_updated is never dispatched.
 * That hook is what reaches this plugin's complete_approval() out of band, so a bulk
 * approval copied from either precedent would flip the status to active and skip the role
 * assignment, the group memberships, the durable record and the applicant's notification -
 * with nothing in the interface to say so.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class decision_operation extends enrol_bulk_enrolment_operation {
    /**
     * The user enrolments in the selection that belong to this plugin.
     *
     * The base class is handed `array $users` and promises nothing about it, so this is
     * where the operation decides what it owns. Through the live dispatch every row is an
     * apply row already - course_enrolment_manager::get_users_enrolments() is built with
     * the instance filter user/action_redir.php sets, and {user_enrolments} is unique on
     * (enrolid, userid), so each user carries exactly one enrolment - but a foreign user
     * enrolment id handed to confirm_enrolment() does not skip: get_pending_user_enrolment()
     * has no enrol-type predicate and the MUST_EXIST lookup that follows it throws.
     *
     * @param array $users Users as course_enrolment_manager::get_users_enrolments() builds them.
     * @return array User enrolment id => the user_enrolments row, for this plugin's rows only.
     */
    public static function enrolments_of(array $users): array {
        $found = [];

        foreach ($users as $user) {
            foreach ($user->enrolments as $enrolment) {
                if ($enrolment->enrolmentinstance->enrol !== 'apply') {
                    continue;
                }
                $found[(int) $enrolment->id] = $enrolment;
            }
        }

        return $found;
    }

    /**
     * Which of those are actually applications awaiting a decision.
     *
     * The predicate itself is queue::is_awaiting_decision(), which is where the plugin's
     * object-form definition of "awaiting a decision" lives - next to the SQL one it has to
     * agree with. It used to be written out here, and the participants page's own action
     * icon would then have been a third copy of a filter that is also a correctness
     * boundary: get_pending_user_enrolment() carries no timeend clause, so an approved
     * enrolment that has since lapsed reads as suspended and comes back looking exactly like
     * a fresh application unless the second half of the rule is applied.
     *
     * Of the three decisions, deferral USED to be the one that made a state nothing could undo:
     * wait_enrolment() called update_user_enrol() with no dates, and update_user_enrol() writes a
     * date only when one is passed, so an expired row kept its past timeend and became a deferred
     * application carrying an expiry - which no queue will list, and which the
     * ENROL_EXT_REMOVED_UNENROL branch of process_expirations() unenrols on sight, selecting on
     * timeend alone with no status filter. It passes 0 explicitly now, so the expiry is cleared.
     * The predicate still excludes expired rows, and for a reason that does not depend on that
     * fix: a lapsed approval is not an application awaiting a decision, whichever decision would
     * be taken on it.
     *
     * Rows excluded here stay in the selection for the counting, so the operator is told how
     * many people the decision did not apply to.
     *
     * @param array $users Users as course_enrolment_manager::get_users_enrolments() builds them.
     * @return array User enrolment ids awaiting a decision.
     */
    public static function awaiting_decision(array $users): array {
        $awaiting = [];

        foreach (static::enrolments_of($users) as $ueid => $enrolment) {
            if (queue::is_awaiting_decision($enrolment)) {
                $awaiting[] = $ueid;
            }
        }

        return $awaiting;
    }

    /**
     * The confirmation form shown before the decision is taken.
     *
     * A form is returned for all three decisions rather than acting immediately, for two
     * reasons: a bulk cancellation unenrols people, and the decision carries an outcome
     * message the applicant reads.
     *
     * @param moodle_url|string|null $defaultaction Url the form posts back to.
     * @param mixed $defaultcustomdata Custom data core supplies, carrying the selected users.
     * @return bulk_decision_form The confirmation form.
     */
    public function get_form($defaultaction = null, $defaultcustomdata = null) {
        global $CFG;

        /* moodleform is no more autoloadable than enrol_bulk_enrolment_operation is - measured,
           class_exists('moodleform', true) is false in a plain request - and
           user/action_redir.php does not pull it in either. Core's precedents reach it through
           the chain that ends at enrol/bulkchange_forms.php; this one has to ask.

           This line went missing once and the Behat scenario is what found it, because the
           PHPUnit suite cannot: measured with tests/bulk/operations_test.php as the only file
           in the run, lib/formslib.php is ALREADY included before the first test executes, so
           deleting this require leaves all 258 tests green while fataling on a live page. What
           includes it there is not pinned - it is not the bootstrap chain, not this plugin's
           own requires, and none of the autoloaded core form classes that require it at file
           scope was loaded. Do not read a green suite as evidence that this line is spare. */
        require_once($CFG->libdir . '/formslib.php');

        $customdata = is_array($defaultcustomdata) ? $defaultcustomdata : [];
        $customdata['title'] = $this->get_title();
        $customdata['description'] = $this->get_description();
        $customdata['button'] = $this->get_title();
        $customdata['courseid'] = (int) $this->manager->get_course()->id;
        $customdata['withdecision'] = $this->offers_decision_controls();

        return new bulk_decision_form($defaultaction, $customdata);
    }

    /**
     * Take the decision on every selected application.
     *
     * @param course_enrolment_manager $manager Manager the driver built for the course.
     * @param array $users Selected users carrying their user enrolments.
     * @param stdClass $properties Submitted form data.
     * @return bool False only when the operator may not decide here; true otherwise.
     */
    public function process(course_enrolment_manager $manager, array $users, stdClass $properties) {
        global $DB;

        /* Checked twice on purpose. get_bulk_operations() is the gate for core's own driver -
           it looks the operation up in the array that method returns and throws when it is
           absent, so a missing capability is refused there rather than here. This second
           check is the gate for anything else, because process() is public, the base class
           declares it abstract without a gate of its own, and core's driver adds none. */
        if (!has_capability('enrol/apply:manageapplications', $manager->get_context())) {
            \core\notification::error(get_string('bulknotpermitted', 'enrol_apply'));
            return false;
        }

        $selection = static::enrolments_of($users);
        if (!$selection) {
            \core\notification::warning(get_string('bulknothingdecided', 'enrol_apply'));
            return true;
        }

        /* PARAM_TEXT at the form, trimmed here, and passed on rather than written: each
           decision method records it on the durable record before it mutates the enrolment,
           which is the only ordering that survives complete_approval() running twice. */
        $message = trim((string) ($properties->outcomemessage ?? ''));

        $candidates = static::awaiting_decision($users);
        $this->decide($candidates, $message, $properties);

        /* Counted by re-reading, never by predicting. The decision methods skip a row they
           will not act on - one already in the state being asked for, an application in a
           course the operator does not hold the capability in - and they skip it silently,
           so the only truthful count is of the rows whose state actually moved. */
        $after = $DB->get_records_list('user_enrolments', 'id', array_keys($selection), '', 'id, status');
        $decided = 0;
        foreach ($candidates as $ueid) {
            $was = (int) $selection[$ueid]->status;
            $now = array_key_exists($ueid, $after) ? (int) $after[$ueid]->status : null;
            if (!$this->has_decided($was) && $this->has_decided($now)) {
                $decided++;
            }
        }

        /* Three counters rather than one, because one bucket would have to carry three
           unrelated reasons under a sentence naming a single one. Each number below is
           computed from the set its string describes, and nothing else. */
        if ($decided) {
            \core\notification::info(get_string('bulkdecided', 'enrol_apply', $decided));
        }

        $notawaiting = count($selection) - count($candidates);
        if ($notawaiting) {
            \core\notification::warning(get_string('bulkskipped', 'enrol_apply', $notawaiting));
        }

        $unchanged = count($candidates) - $decided;
        if ($unchanged) {
            \core\notification::warning(get_string('bulkunchanged', 'enrol_apply', $unchanged));
        }

        return true;
    }

    /**
     * Whether this decision offers the group and role choosers.
     *
     * Only confirmation acts on either: wait_enrolment() and cancel_enrolment() take a
     * message and nothing else.
     *
     * @return bool True when the form should offer the choosers.
     */
    protected function offers_decision_controls(): bool {
        return false;
    }

    /**
     * The sentence explaining what the operator is about to do.
     *
     * @return string Localised description.
     */
    abstract protected function get_description(): string;

    /**
     * Hand the selected applications to the plugin's own decision method.
     *
     * @param array $userenrolmentids User enrolment ids of the whole selection.
     * @param string $message Message the decider wrote to the applicants, empty for none.
     * @param stdClass $properties Submitted form data.
     * @return void
     */
    abstract protected function decide(array $userenrolmentids, string $message, stdClass $properties): void;

    /**
     * Whether a user enrolment in this state has had this decision taken on it.
     *
     * @param int|null $status The user_enrolments.status, or null when the row is gone.
     * @return bool True when the row is in the state this decision produces.
     */
    abstract protected function has_decided(?int $status): bool;
}
