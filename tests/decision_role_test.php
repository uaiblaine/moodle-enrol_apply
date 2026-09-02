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
 * The role used to be assigned by apply(), the moment somebody asked to join. It is now assigned
 * by complete_approval(), from a choice the decider makes and the durable record carries.
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
        /* The queue's table is dynamic, and get_dynamic_table_html_end() builds its
           "show all" link from $PAGE->url - so rendering one without a page url makes core
           emit a debugging() call, which advanced_testcase turns into a notice. manage.php
           always sets it; a test that renders the table is standing in for that page. */
        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php'));
    }

    /**
     * Submit an application through the real path, so it leaves a durable record.
     *
     * lib_test's create_application() bypasses apply() and writes no enrol_apply_submission row,
     * so a test using it would read an empty table and pass on nothing.
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
     * Mutation check: put $instance->roleid back into apply()'s enrol_user() call and TWO tests
     * go red - this one and test_a_chosen_role_replaces_the_instance_role. The second is the
     * informative one and the reason this change is a swap rather than a fill-in: with the role
     * assigned at application time, approving with a different one leaves the applicant holding
     * BOTH, one of them bare and unattributable. Measured, not predicted.
     *
     * The control here is the approval below the assertion, which proves the role really is
     * assigned by this plugin on this path and that an empty assertion is not passing by
     * accident.
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
     * The second assertion is the one that matters. complete_approval() runs twice for a queue
     * approval and the first pass carries no operator input; a role threaded through an argument
     * rather than read off the record would leave the applicant holding BOTH roles, with nothing
     * in the assignment to say which pass wrote it. Measured before the record existed: two
     * role_assignments rows.
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
     * Mutation check: delete the get_assignable_roles() allowlist from confirm_enrolment() and
     * exactly this test goes red. It asserts on BOTH halves, because either one alone can pass
     * against the unfixed code: role_assign() performs no assignability check whatsoever, so the
     * assignment is the live half, and the record is what the reports and an export would show.
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
     * Core's participants page "Edit enrolment" screen drives update_user_enrol() directly. That
     * route reaches complete_approval() through the before_user_enrolment_updated hook and can
     * never carry a role, because the hook has no operator input at all - so the fallback is the
     * only role it will ever produce. Until this change the role came from apply() and this path
     * needed nothing; it now needs the fallback or an out-of-band approval leaves a participant
     * with no role.
     *
     * Mutation check: make assign_decided_role() return early when nothing was recorded, instead
     * of falling back to $instance->roleid. Four tests go red - every one that expects a role
     * where the decider chose none, this one included. That breadth is the point: the fallback
     * is not a corner case, it is what the majority of approvals will use.
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
     * Mutation check: drop the fourth and fifth arguments from role_assign() in
     * assign_decided_role(). Five tests go red: this one, the three that assert the whole
     * assignment tuple, and the expiry test below - which is the only one of the five that says
     * why the stamp matters rather than merely that it is there. It reddens only because that
     * test's fixture was corrected; see its docblock.
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
     * This is why the assignment is stamped. process_expirations() guesses $instance->roleid
     * when the assignment carries no component, and once a decider can choose a different role
     * that guess is wrong by construction: measured on m502, a Teacher chosen against an
     * instance defaulting to Student survived the sweep under both expiredaction settings.
     * Stamped, core removes it by component in its "remove all roles that belong to this
     * instance" line.
     *
     * TWO things in the fixture are load bearing, and the first draft of this test had only one
     * of them, which made it vacuous - the stamp mutation left it green. The applicant needs a
     * second enrolment, so theirs is not the last one in the course and unenrol_user()'s blanket
     * sweep does not run; and that second enrolment must carry a DIFFERENT role, so the
     * applicant holds two assignments. With only one, process_expirations() takes its
     * "count == 1" branch and calls role_unassign_all() over every component-less row, which
     * removes an unstamped chosen role just as thoroughly as a stamped one. With two it takes
     * the "count > 1" branch instead, whose whole content is a guess at $instance->roleid - and
     * that guess is what the stamp exists to make unnecessary.
     *
     * Core also removes the manual plugin's Student assignment here, by that same guess. That is
     * core's pre-existing heuristic for a plugin whose roles are not protected, not something
     * this change introduces, and it happens identically with and without the stamp.
     *
     * @return void
     */
    public function test_the_expiry_sweep_removes_a_chosen_role(): void {
        global $DB;

        [$applicant, $ueid] = $this->apply_for_real();
        $this->setAdminUser();

        /* A second, unrelated enrolment carrying a second role. See the docblock: both halves
           are needed, and the role must differ from the one the approval will choose. */
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
     * roleid 0 is reachable: the column is nullable with a default of 0 and a restore writes 0
     * whenever the archived role maps to nothing the restoring user may assign. role_assign(0)
     * THROWS rather than doing nothing, and until this change enrol_user()'s own truthiness
     * guard was what swallowed it.
     *
     * Mutation check: delete the "$roleid <= 0" guard from assign_decided_role() and exactly
     * this test goes red, with a coding_exception rather than a failed assertion.
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
     * record_decided_role() writes a zero rather than returning early on one, which is where it
     * departs from record_decided_groups(). Without that, a stored role is sticky: a row that
     * comes back to the queue - core's "Edit enrolment" screen re-suspends one, and so does an
     * expiredaction of "suspend" - would be approved a second time with the superseded role and
     * nothing on screen to say so.
     *
     * Mutation check: make record_decided_role() return early when the role is 0 and exactly
     * this test goes red.
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
     * The two are different: an approval submitted with the select left on its default posts
     * roleid 0 and MEANS "use the instance role", while a programmatic call passing no decision
     * at all - the out-of-band route, and every caller that predates this change - means "I have
     * nothing to say about the role". Erasing a recorded decision on the second would let the
     * hook route silently overwrite what the queue recorded.
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
     * The control is the manager role, which an editing teacher may not assign and which the
     * server would refuse: the list the page renders and the list the server allowlists against
     * are the same call, and this is what keeps them from drifting apart.
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
