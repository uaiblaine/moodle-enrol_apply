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
 * Tests for the role an approval assigns.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * Tests for the role an approval assigns.
 *
 * The role is assigned by complete_approval(), never by apply(): the decider's choice as stored on
 * the durable record, falling back to the instance's own role.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
#[CoversClass(submission::class)]
final class decision_role_test extends \advanced_testcase {
    /** @var \stdClass Course the apply instance belongs to. */
    protected $course;

    /** @var \stdClass The enrol_apply instance. */
    protected $instance;

    /** @var \enrol_apply_plugin The plugin. */
    protected $plugin;

    /** @var int The student role id, which is the instance default. */
    protected $studentroleid;

    /** @var int The non-editing teacher role id, which an editing teacher may also assign. */
    protected $teacherroleid;

    /**
     * Enable the plugin and give it a course with an instance defaulting to the student role.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB, $PAGE;

        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $this->teacherroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);

        $this->plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $fields = $this->plugin->get_instance_defaults();
        $fields['roleid'] = $this->studentroleid;
        $instanceid = $this->plugin->add_instance($this->course, $fields);
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        /* The queue's dynamic table builds its "show all" link from $PAGE->url, and reading an
           unset page url calls debugging(), which advanced_testcase reports as a notice.
           manage.php always sets it. */
        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php'));
    }

    /**
     * Submit an application through the real path, so it leaves a durable record.
     *
     * lib_test's create_application() bypasses apply() and writes no enrol_apply_submission row.
     *
     * @return array The applicant and their user enrolment id.
     */
    protected function apply_for_real(): array {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $this->setUser($applicant);

        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->setAccessible(true);
        $method->invoke($this->plugin, $this->instance, $applicant->id, (object) ['applydescription' => 'Please']);
        $sink->close();

        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        return [$applicant, $ueid];
    }

    /**
     * Every role assignment the given user holds in the course, as roleid => [component, itemid].
     *
     * @param \stdClass $user The user.
     * @return array Role assignments keyed by role id.
     */
    protected function assignments(\stdClass $user): array {
        global $DB;

        $rows = $DB->get_records('role_assignments', [
            'contextid' => \context_course::instance($this->course->id)->id,
            'userid' => $user->id,
        ]);

        $found = [];
        foreach ($rows as $row) {
            $found[(int) $row->roleid] = [(string) $row->component, (int) $row->itemid];
        }

        return $found;
    }

    /**
     * A pending applicant holds no role at all until somebody approves them.
     *
     * Changes that must make it fail: passing $instance->roleid to apply()'s enrol_user() call.
     * That also fails test_a_chosen_role_replaces_the_instance_role, because approving with a
     * different role would leave the applicant holding both, one of them bare and unattributable.
     *
     * The approval after the assertion is the control: it proves this path does assign a role.
     *
     * @return void
     */
    public function test_a_pending_applicant_holds_no_role(): void {
        [$applicant] = $this->apply_for_real();

        $this->assertSame([], $this->assignments($applicant));

        // The control: approving the same application does assign one.
        $this->setAdminUser();
        $this->plugin->confirm_enrolment([
            (int) key($this->pending_ids($applicant)),
        ]);
        $this->assertArrayHasKey($this->studentroleid, $this->assignments($applicant));
    }

    /**
     * The user enrolment ids of an applicant's pending applications, keyed by id.
     *
     * @param \stdClass $applicant The applicant.
     * @return array Ids as both key and value.
     */
    protected function pending_ids(\stdClass $applicant): array {
        global $DB;

        $ids = $DB->get_fieldset_select(
            'user_enrolments',
            'id',
            'userid = :userid AND enrolid = :enrolid',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id]
        );

        return array_combine($ids, $ids);
    }

    /**
     * Approving without choosing a role assigns the one the enrolment method carries.
     *
     * @return void
     */
    public function test_approving_without_a_choice_assigns_the_instance_role(): void {
        [$applicant, $ueid] = $this->apply_for_real();
        $this->setAdminUser();

        $this->plugin->confirm_enrolment([$ueid], '', ['groups' => [], 'roleid' => 0]);

        $this->assertSame(
            [$this->studentroleid => ['enrol_apply', (int) $this->instance->id]],
            $this->assignments($applicant)
        );
        $this->assertSame(0, (int) $this->record($applicant)->decidedrole);
    }

    /**
     * The durable record for an applicant.
     *
     * @param \stdClass $applicant The applicant.
     * @return \stdClass The record.
     */
    protected function record(\stdClass $applicant): \stdClass {
        global $DB;

        return $DB->get_record(
            'enrol_apply_submission',
            ['courseid' => $this->course->id, 'userid' => $applicant->id],
            '*',
            MUST_EXIST
        );
    }

    /**
     * A chosen role replaces the instance default, and only that role is assigned.
     *
     * complete_approval() runs twice for a queue approval and the first pass, from the hook,
     * carries no operator input. A role passed as an argument instead of read off the record
     * would leave both roles assigned, which the exact match on the assignments catches.
     *
     * @return void
     */
    public function test_a_chosen_role_replaces_the_instance_role(): void {
        [$applicant, $ueid] = $this->apply_for_real();
        $this->setAdminUser();

        $this->plugin->confirm_enrolment([$ueid], '', ['groups' => [], 'roleid' => $this->teacherroleid]);

        $this->assertSame(
            [$this->teacherroleid => ['enrol_apply', (int) $this->instance->id]],
            $this->assignments($applicant),
            'the chosen role, and only the chosen role'
        );
        $this->assertSame($this->teacherroleid, (int) $this->record($applicant)->decidedrole);
    }

    /**
     * A role the decider may not assign is refused, and the instance default is used instead.
     *
     * role_assign() performs no assignability check, so the get_assignable_roles() allowlist in
     * confirm_enrolment() is the only barrier; removing it must make this test fail. Both the
     * live assignment and the recorded decidedrole are asserted, since the report and the privacy
     * export read the latter.
     *
     * @return void
     */
    public function test_a_role_the_decider_may_not_assign_is_refused(): void {
        global $DB;

        [$applicant, $ueid] = $this->apply_for_real();

        $manager = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);

        // The premise: an editing teacher really may not assign this role here.
        $this->assertArrayNotHasKey(
            $manager,
            get_assignable_roles(\context_course::instance($this->course->id)),
            'the fixture must actually be a role this decider cannot assign'
        );

        $this->plugin->confirm_enrolment([$ueid], '', ['groups' => [], 'roleid' => $manager]);

        $held = $this->assignments($applicant);
        $this->assertArrayNotHasKey($manager, $held, 'the forged role must not be assigned');
        $this->assertArrayHasKey($this->studentroleid, $held, 'the instance default applies instead');
        $this->assertSame(0, (int) $this->record($applicant)->decidedrole, 'and nothing is recorded');
    }

    /**
     * An approval taken outside the queue falls back to the instance role.
     *
     * Core's "Edit enrolment" screen drives update_user_enrol() directly. That route reaches
     * complete_approval() through the before_user_enrolment_updated hook, which carries no
     * operator input, so the instance role is the only role it can produce.
     *
     * Changes that must make it fail: assign_decided_role() returning early when no role was
     * recorded instead of falling back to $instance->roleid.
     *
     * @return void
     */
    public function test_an_out_of_band_approval_assigns_the_instance_role(): void {
        [$applicant, $ueid] = $this->apply_for_real();
        $this->setAdminUser();

        $this->plugin->update_user_enrol($this->instance, (int) $applicant->id, ENROL_USER_ACTIVE);

        $this->assertSame(
            [$this->studentroleid => ['enrol_apply', (int) $this->instance->id]],
            $this->assignments($applicant)
        );
        unset($ueid);
    }

    /**
     * The assignment carries this plugin's component stamp, so core can clean it up.
     *
     * test_the_expiry_sweep_removes_a_chosen_role() shows why the stamp matters.
     *
     * @return void
     */
    public function test_the_assignment_carries_the_component_stamp(): void {
        [$applicant, $ueid] = $this->apply_for_real();
        $this->setAdminUser();

        $this->plugin->confirm_enrolment([$ueid], '', ['groups' => [], 'roleid' => $this->teacherroleid]);

        $this->assertSame(
            ['enrol_apply', (int) $this->instance->id],
            $this->assignments($applicant)[$this->teacherroleid] ?? null
        );
    }

    /**
     * The expiry sweep removes a chosen role, which without the stamp it cannot.
     *
     * Under ENROL_EXT_REMOVED_SUSPENDNOROLES, process_expirations() removes a stamped assignment
     * in its "remove all roles that belong to this instance and user" step. For an unstamped one
     * it guesses $instance->roleid, which is wrong once the decider chose a different role.
     *
     * The fixture gives the applicant a second enrolment carrying a DIFFERENT role, so they hold
     * two assignments and core takes its "count > 1" branch, the guess. With a single assignment
     * core takes the "count == 1" branch, role_unassign_all() over every component-less row,
     * which removes an unstamped role too and would let the test pass without the stamp.
     *
     * Core's guess also removes the manual enrolment's Student assignment. That is core's
     * heuristic for a plugin whose roles are not protected, and it happens with or without the
     * stamp.
     *
     * Changes that must make it fail: dropping the component and itemid arguments from
     * role_assign() in assign_decided_role().
     *
     * @return void
     */
    public function test_the_expiry_sweep_removes_a_chosen_role(): void {
        global $DB;

        [$applicant, $ueid] = $this->apply_for_real();
        $this->setAdminUser();

        /* A second, unrelated enrolment carrying a role different from the one the approval
           will choose; see the docblock. */
        $manual = enrol_get_plugin('manual');
        $manualinstance = $DB->get_record(
            'enrol',
            ['courseid' => $this->course->id, 'enrol' => 'manual'],
            '*',
            MUST_EXIST
        );
        $manual->enrol_user($manualinstance, $applicant->id, $this->studentroleid, 0, 0, ENROL_USER_ACTIVE);

        $this->plugin->confirm_enrolment([$ueid], '', ['groups' => [], 'roleid' => $this->teacherroleid]);
        $held = $this->assignments($applicant);
        $this->assertArrayHasKey($this->teacherroleid, $held);
        $this->assertCount(2, $held, 'the fixture must put core on its count > 1 branch');

        $this->plugin->set_config('expiredaction', ENROL_EXT_REMOVED_SUSPENDNOROLES);
        $DB->set_field('user_enrolments', 'timestart', time() - (10 * DAYSECS), ['id' => $ueid]);
        $DB->set_field('user_enrolments', 'timeend', time() - DAYSECS, ['id' => $ueid]);
        $this->plugin->process_expirations(new \null_progress_trace());

        // The control: the sweep really ran.
        $this->assertEquals(
            ENROL_USER_SUSPENDED,
            (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid]),
            'the control proves the sweep actually ran'
        );
        $this->assertSame([], $this->assignments($applicant));
    }

    /**
     * An instance carrying no role approves cleanly and assigns nothing.
     *
     * roleid 0 is reachable: the column defaults to 0, and a restore writes 0 when the archived
     * role cannot be mapped. role_assign() throws a coding_exception for roleid 0, so removing the
     * "$roleid <= 0" guard from assign_decided_role() must make this test fail.
     *
     * @return void
     */
    public function test_an_instance_with_no_role_approves_without_assigning_one(): void {
        global $DB;

        $DB->set_field('enrol', 'roleid', 0, ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        [$applicant, $ueid] = $this->apply_for_real();
        $this->setAdminUser();

        $this->plugin->confirm_enrolment([$ueid]);

        $this->assertEquals(
            ENROL_USER_ACTIVE,
            (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid]),
            'the approval itself must still succeed'
        );
        $this->assertSame([], $this->assignments($applicant));
    }

    /**
     * A later approval replaces the role an earlier one recorded.
     *
     * record_decided_role() writes a zero rather than returning early on one. Otherwise a stored
     * role is sticky: a row that comes back to the queue (core's "Edit enrolment" screen
     * re-suspends one, and so does an expiredaction of "suspend") would be approved again with
     * the superseded role. Changes that must make it fail: record_decided_role() returning early
     * when the role is 0.
     *
     * @return void
     */
    public function test_a_later_approval_clears_an_earlier_choice(): void {
        global $DB;

        [$applicant, $ueid] = $this->apply_for_real();
        $this->setAdminUser();

        $this->plugin->confirm_enrolment([$ueid], '', ['groups' => [], 'roleid' => $this->teacherroleid]);
        $this->assertSame($this->teacherroleid, (int) $this->record($applicant)->decidedrole);

        // Back into the queue, the way core's "Edit enrolment" screen puts it there.
        $this->plugin->update_user_enrol($this->instance, (int) $applicant->id, ENROL_USER_SUSPENDED);
        $DB->insert_record('enrol_apply_applicationinfo', (object) [
            'userenrolmentid' => $ueid,
            'comment' => 'Again',
        ]);

        $this->plugin->confirm_enrolment([$ueid], '', ['groups' => [], 'roleid' => 0]);

        $this->assertSame(0, (int) $this->record($applicant)->decidedrole);
    }

    /**
     * A decision carrying no role key at all leaves an earlier choice alone.
     *
     * A posted roleid of 0, the chooser left on its default, means "use the instance role" and
     * clears a stored choice. An absent key means the caller has nothing to say about the role,
     * and must not erase what was recorded.
     *
     * @return void
     */
    public function test_a_decision_without_a_role_key_leaves_the_record_alone(): void {
        [$applicant, $ueid] = $this->apply_for_real();
        $this->setAdminUser();

        submission::record_decided_role($ueid, $this->teacherroleid);

        $this->plugin->confirm_enrolment([$ueid], '', ['groups' => []]);

        $this->assertSame($this->teacherroleid, (int) $this->record($applicant)->decidedrole);
        $this->assertArrayHasKey($this->teacherroleid, $this->assignments($applicant));
    }

    /**
     * The role chooser offers only roles the decider may assign in this course.
     *
     * The manager role, which an editing teacher may not assign and confirm_enrolment() would
     * refuse, is the control: the chooser must offer the list the server allowlists against.
     *
     * @return void
     */
    public function test_the_chooser_offers_only_assignable_roles(): void {
        global $DB, $PAGE;

        [, $ueid] = $this->apply_for_real();
        unset($ueid);

        $manager = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);

        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));
        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);

        $html = $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, $this->instance);

        /* Scoped to the select. An unscoped search would match the applicant's own row and the
           surrounding page, so it could pass with the chooser entirely wrong. */
        $this->assertMatchesRegularExpression('~<select[^>]*name="roleid".*?</select>~s', $html);
        preg_match('~<select[^>]*name="roleid".*?</select>~s', $html, $matches);
        $chooser = $matches[0];

        $this->assertStringContainsString('value="' . $this->studentroleid . '"', $chooser);
        $this->assertStringNotContainsString('value="' . $manager . '"', $chooser);
    }
}
