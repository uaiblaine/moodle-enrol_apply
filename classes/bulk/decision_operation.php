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
 * Core's driver for this extension point is user/action_redir.php. Its bulk branch performs
 * no require_login() and no require_capability(); its only gates are confirm_sesskey() and a
 * check that the plugin is enabled site wide. And it hands process() an array it makes no
 * promise about beyond "users of a course", so nothing upstream guarantees the rows belong to
 * this plugin.
 *
 * The decision itself is never taken here. Every operation delegates to the plugin's own
 * confirm_enrolment(), wait_enrolment() or cancel_enrolment(). Do not copy the core
 * precedents (enrol_manual and enrol_self): they write {user_enrolments} with a raw UPDATE and
 * build the event by hand, so \core_enrol\hook\before_user_enrolment_updated is never
 * dispatched. That hook is what reaches this plugin's complete_approval() out of band, so such
 * a bulk approval would flip the status to active and silently skip the role assignment, the
 * group memberships, the durable record and the applicant's notification.
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
     * where the operation decides what it owns. Through core's dispatch every row is an apply
     * row already (the manager is filtered to one instance), but a foreign user enrolment id
     * handed to confirm_enrolment() would not be skipped: get_pending_user_enrolment() has no
     * enrol-type predicate and the MUST_EXIST lookup that follows it throws.
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
     * The enrol instance core's dispatch will actually decide, for this course.
     *
     * Reproduces the instance lookup in user/action_redir.php: enrol_get_instances($courseid,
     * false), which includes disabled instances, and the first match in sortorder. So a
     * disabled apply method sorting first captures the whole dispatch, the manager is filtered
     * to it, get_users_enrolments() returns nothing, and core redirects with "No users
     * selected". Not the manager's own instance, which would agree by construction, and not
     * the enabled-only call, which is the difference being reproduced.
     *
     * This names the method in the warning; it must not gate the menu. An enabled instance that
     * is not the one the selection lives on breaks the dispatch identically, so a status gate
     * would close one case and leave the other open.
     *
     * An instance with no custom name comes back as the plugin's own name, so two unnamed
     * instances are indistinguishable in the warning, as they are on enrol/instances.php. The
     * warning still states how many applications were left alone.
     *
     * @param int $courseid Course the dispatch runs in.
     * @return stdClass|null The instance core will filter to, or null when the course has none.
     */
    public static function dispatch_instance(int $courseid): ?stdClass {
        foreach (enrol_get_instances($courseid, false) as $instance) {
            if ($instance->enrol === 'apply') {
                return $instance;
            }
        }

        return null;
    }

    /**
     * The sentence naming what a bulk decision will not reach, or the empty string.
     *
     * Shown twice: on the confirmation form, the only point before anything is written, and
     * again in the report after the decision.
     *
     * The method name is normalised through format_string(), which hands back the escaped
     * spelling - the right one here, because both sinks render raw: a moodleform static element
     * is a triple stash in core's element-template.mustache, and \core\output\notification
     * exports its message through clean_text() into a template that does the same.
     * get_instance_name() returns a custom name already through format_string(), but a bare,
     * unescaped language string when the instance has none.
     *
     * @param course_enrolment_manager $manager Manager core built for the course.
     * @param array $users Selected users carrying their user enrolments.
     * @param string $stringid Which wording to use: the form's or the report's.
     * @return string The localised sentence, empty when nothing is left behind.
     */
    protected function other_applications_notice(
        course_enrolment_manager $manager,
        array $users,
        string $stringid
    ): string {
        $courseid = (int) $manager->get_course()->id;
        $others = queue::other_applications(
            $courseid,
            array_map('intval', array_keys($users)),
            array_keys(static::enrolments_of($users))
        );
        if (!$others) {
            return '';
        }

        $instance = static::dispatch_instance($courseid);
        $method = $instance === null
            ? get_string('pluginname', 'enrol_apply')
            : format_string($this->plugin->get_instance_name($instance), true, [
                'context' => $manager->get_context(),
            ]);

        /* A literal per branch, never an id built from $stringid, so tools that check string
           usage can see both keys. */
        $data = (object) ['count' => count($others), 'method' => $method];

        return $stringid === 'form'
            ? get_string('bulkothermethodsform', 'enrol_apply', $data)
            : get_string('bulkothermethods', 'enrol_apply', $data);
    }

    /**
     * Which of those are actually applications awaiting a decision.
     *
     * The predicate is queue::is_awaiting_decision(), the object-form definition of "awaiting a
     * decision" kept next to the SQL one it has to agree with. Its expiry half matters here:
     * get_pending_user_enrolment() carries no timeend clause, so an approved enrolment that has
     * since lapsed reads as suspended and would otherwise be decided as if it were a fresh
     * application.
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

        /* moodleform is not autoloadable and user/action_redir.php does not include
           lib/formslib.php; core's precedents get it through enrol/bulkchange_forms.php. Only
           the Behat scenario catches a missing require here: formslib.php is already loaded
           in a PHPUnit run, so the unit tests stay green without it. */
        require_once($CFG->libdir . '/formslib.php');

        $customdata = is_array($defaultcustomdata) ? $defaultcustomdata : [];
        $customdata['title'] = $this->get_title();
        $customdata['description'] = $this->get_description();
        $customdata['button'] = $this->get_title();
        $customdata['courseid'] = (int) $this->manager->get_course()->id;
        $customdata['withdecision'] = $this->offers_decision_controls();

        /* Said before the decision as well as after it. The dispatch reaches one enrolment
           method and cannot be widened from here, so the only thing that helps an operator is
           knowing it before they press the button. */
        $customdata['othernotice'] = $this->other_applications_notice(
            $this->manager,
            is_array($defaultcustomdata) ? ($defaultcustomdata['users'] ?? []) : [],
            'form'
        );

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

        /* Checked twice on purpose. For core's driver the gate is get_bulk_operations(): it
           looks the operation up in the array that method returns and throws when it is
           absent. This check is the gate for any other caller, because process() is public
           and the base class declares it with no gate of its own. */
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

        /* The fourth counter, and the only one that is about applications this operation never
           saw. Read AFTER the decision, like the other three and for a sharper reason: approval
           and cancellation take their own rows out of the "awaiting a decision" predicate, so
           reading before would count them as untouched. Deferral does not, which is what the
           exclusion list is for. */
        $othernotice = $this->other_applications_notice($manager, $users, 'report');
        if ($othernotice !== '') {
            \core\notification::warning($othernotice);
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
