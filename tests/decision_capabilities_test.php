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
 * Tests for enrol/apply:choosedecision and enrol/apply:viewapplications.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use enrol_apply\local\groupscope;
use enrol_apply\local\queue;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass targets below resolve when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * The two capabilities of stage 2's S5 (docs/design/group-review-scope.md).
 *
 * The course is in separate groups mode and its apply method gives the student role and one
 * listed group. The editing teacher, who may access all groups and so is never narrowed, is the
 * decider of the choosedecision tests; whether they may choose is set by an override in the
 * course, so the tests do not depend on the archetypes the test site was installed with. The
 * viewer is a role of its own holding enrol/apply:viewapplications alone, a member of one group.
 * Applications are written by the plugin's own apply(), so each carries its record.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
#[CoversClass(\enrol_apply\local\groupscope::class)]
#[CoversClass(\enrol_apply\local\queue::class)]
#[CoversClass(\enrol_apply\table\applications::class)]
#[CoversClass(\enrol_apply\form\bulk_decision_form::class)]
#[CoversClass(\enrol_apply_renderer::class)]
final class decision_capabilities_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass A course in separate groups mode. */
    private $course;

    /** @var \context_course Its context. */
    private $context;

    /** @var \stdClass Its apply instance, giving the student role and $listed. */
    private $instance;

    /** @var \stdClass The viewer's group. */
    private $mine;

    /** @var \stdClass Another group of the course. */
    private $theirs;

    /** @var \stdClass The group the apply method itself lists. */
    private $listed;

    /** @var \stdClass Editing teacher, who may access all groups. */
    private $editing;

    /** @var int The editing teacher role. */
    private $editingrole;

    /** @var int The student role, the method's own. */
    private $studentrole;

    /** @var int The non-editing teacher role, which the editing teacher may assign. */
    private $teacherrole;

    /**
     * The course, its groups, its method and its editing teacher.
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
        $this->plugin = enrol_get_plugin('apply');

        $this->editingrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $this->studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $this->teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS]);
        $this->context = \context_course::instance($this->course->id);
        $id = $this->plugin->add_instance($this->course, [
            'roleid' => $this->studentrole,
        ] + $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $this->mine = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Own group']);
        $this->theirs = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Other group']);
        $this->listed = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Listed group']);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $id, 'groupid' => $this->listed->id]);

        $this->editing = $generator->create_and_enrol($this->course, 'editingteacher');

        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]));
    }

    /**
     * Write a pending application through the plugin's own apply(), as the administrator.
     *
     * @param \stdClass|null $group The key's group; null for an application without a key.
     * @param \stdClass|null $instance The method applied to, the course's own by default.
     * @return array The user enrolment id and the applicant.
     */
    private function apply_for(?\stdClass $group, ?\stdClass $instance = null): array {
        global $USER;

        $previous = $USER;
        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $written = $method->invoke(
            $this->plugin,
            $instance ?? $this->instance,
            (int) $applicant->id,
            (object) ['applydescription' => 'Please'],
            $group ? (int) $group->id : 0,
            $group ? submission::KEY_FOR_REVIEW : submission::KEY_NONE
        );
        $sink->close();
        $this->setUser($previous);

        return [(int) $written['userenrolmentid'], $applicant];
    }

    /**
     * Let the editing teacher choose the groups and role of an approval in the course, or not.
     *
     * An override in the course rather than the archetype default, so the answer does not depend
     * on when the test site was installed.
     *
     * @param bool $allowed Whether they may choose.
     * @return void
     */
    private function editing_may_choose(bool $allowed): void {
        assign_capability(
            'enrol/apply:choosedecision',
            $allowed ? CAP_ALLOW : CAP_PROHIBIT,
            $this->editingrole,
            $this->context->id,
            true
        );
        // A test switches the override between two renders as the same user.
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * A user holding enrol/apply:viewapplications alone in the course, a member of $mine.
     *
     * @param bool $allgroups Whether their role also holds moodle/site:accessallgroups.
     * @return \stdClass The viewer.
     */
    private function viewer(bool $allgroups = false): \stdClass {
        $shortname = $allgroups ? 'applyviewerall' : 'applyviewer';
        $role = $this->getDataGenerator()->create_role(['shortname' => $shortname]);
        $system = \context_system::instance()->id;
        assign_capability(groupscope::VIEW, CAP_ALLOW, $role, $system, true);
        if ($allgroups) {
            assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $role, $system, true);
        }

        $viewer = $this->getDataGenerator()->create_and_enrol($this->course, $shortname);
        $this->getDataGenerator()->create_group_member(['groupid' => $this->mine->id, 'userid' => $viewer->id]);

        return $viewer;
    }

    /**
     * The user enrolment ids the queue's own table lists for the current user.
     *
     * @return array The ids.
     */
    private function listed(): array {
        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);
        ob_start();
        $table->out(50, false);
        ob_end_clean();

        return array_map(static fn($row) => (int) $row->userenrolmentid, array_values($table->rawdata));
    }

    /**
     * The queue page's markup for the current user.
     *
     * @return string The rendered queue.
     */
    private function queue_html(): string {
        global $PAGE;

        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);
        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);

        return $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, $this->instance);
    }

    /**
     * The review page's markup for one application, for the current user.
     *
     * @param int $ueid User enrolment id.
     * @return string The rendered review form.
     */
    private function review_html(int $ueid): string {
        global $PAGE;

        $application = queue::application($ueid);
        $applicant = \core_user::get_user((int) $application->userid, '*', MUST_EXIST);
        $url = new \moodle_url('/enrol/apply/manage.php', ['userenrol' => $ueid]);
        $PAGE->set_url($url);
        $PAGE->set_context($this->context);

        return $PAGE->get_renderer('enrol_apply')->review_form($application, $applicant, $this->instance, $url);
    }

    /**
     * Whether the applicant holds the role, as assigned by this plugin, in the course.
     *
     * @param int $roleid Role id.
     * @param \stdClass $applicant The applicant.
     * @return bool True when the plugin assigned it.
     */
    private function holds_role(int $roleid, \stdClass $applicant): bool {
        global $DB;

        return $DB->record_exists('role_assignments', [
            'roleid' => $roleid,
            'userid' => $applicant->id,
            'contextid' => $this->context->id,
            'component' => 'enrol_apply',
        ]);
    }

    /**
     * Whether the applicant is a member of the group.
     *
     * @param \stdClass $group The group.
     * @param \stdClass $applicant The applicant.
     * @return bool True when they are.
     */
    private function member(\stdClass $group, \stdClass $applicant): bool {
        global $DB;

        return $DB->record_exists('groups_members', ['groupid' => $group->id, 'userid' => $applicant->id]);
    }

    /**
     * A decider without choosedecision who posts a group and a role gets neither: the approval
     * joins the method's group and gives its role, and no choice is recorded.
     *
     * Changes that must make it fail: confirm_enrolment() honouring the posted choice without the
     * capability.
     *
     * @return void
     */
    public function test_a_decider_without_choosedecision_gets_the_defaults(): void {
        [$ueid, $applicant] = $this->apply_for(null);
        $this->editing_may_choose(false);
        $this->setUser($this->editing);
        // The preconditions: they decide here, and the posted role is one they may assign.
        $this->assertTrue(has_capability(groupscope::DECIDE, $this->context));
        $this->assertFalse(has_capability('enrol/apply:choosedecision', $this->context));
        $this->assertArrayHasKey($this->teacherrole, get_assignable_roles($this->context));

        $sink = $this->redirectMessages();
        $decided = $this->plugin->confirm_enrolment(
            [$ueid],
            '',
            ['groups' => [(int) $this->theirs->id], 'roleid' => $this->teacherrole]
        );
        $sink->close();

        $this->assertSame(1, $decided);
        $this->assertTrue($this->member($this->listed, $applicant));
        $this->assertFalse($this->member($this->theirs, $applicant));
        $this->assertTrue($this->holds_role($this->studentrole, $applicant));
        $this->assertFalse($this->holds_role($this->teacherrole, $applicant));
        $this->assertNull(submission::chosen_groups($ueid));
        $this->assertNull(submission::chosen_role($ueid));
    }

    /**
     * The control: the same post from a decider holding choosedecision is honoured.
     *
     * @return void
     */
    public function test_a_decider_with_choosedecision_has_their_choice_honoured(): void {
        [$ueid, $applicant] = $this->apply_for(null);
        $this->editing_may_choose(true);
        $this->setUser($this->editing);

        $sink = $this->redirectMessages();
        $this->plugin->confirm_enrolment(
            [$ueid],
            '',
            ['groups' => [(int) $this->theirs->id], 'roleid' => $this->teacherrole]
        );
        $sink->close();

        $this->assertTrue($this->member($this->theirs, $applicant));
        $this->assertFalse($this->member($this->listed, $applicant));
        $this->assertTrue($this->holds_role($this->teacherrole, $applicant));
        $this->assertFalse($this->holds_role($this->studentrole, $applicant));
    }

    /**
     * A choice an earlier approval recorded on the same application is not applied again by a
     * decider who may not choose, even when they post nothing about groups and role.
     *
     * Changes that must make it fail: confirm_enrolment() skipping the choice for such a decider
     * instead of recording an empty one.
     *
     * @return void
     */
    public function test_an_earlier_choice_is_not_reapplied_without_choosedecision(): void {
        [$ueid, $applicant] = $this->apply_for(null);
        submission::record_decided_groups($ueid, [(int) $this->theirs->id]);
        submission::record_decided_role($ueid, $this->teacherrole);
        // The precondition: the record carries a choice the approval would otherwise apply.
        $this->assertSame([(int) $this->theirs->id], submission::chosen_groups($ueid));

        $this->editing_may_choose(false);
        $this->setUser($this->editing);
        $sink = $this->redirectMessages();
        $this->assertSame(1, $this->plugin->confirm_enrolment([$ueid]));
        $sink->close();

        $this->assertTrue($this->member($this->listed, $applicant));
        $this->assertFalse($this->member($this->theirs, $applicant));
        $this->assertTrue($this->holds_role($this->studentrole, $applicant));
        $this->assertFalse($this->holds_role($this->teacherrole, $applicant));
    }

    /**
     * The review page shows a decider without choosedecision what the approval gives, read-only:
     * the key route of an application a key sent for review (the key's group and the method's)
     * and the method's role, with no chooser. With the capability, the choosers and no summary.
     *
     * Changes that must make it fail: the choosers shown without the capability, the summary
     * dropped, or the summary naming the method's list instead of this application's groups.
     *
     * @return void
     */
    public function test_the_review_page_shows_what_an_approval_gives_without_choosedecision(): void {
        global $DB;

        [$ueid] = $this->apply_for($this->mine);
        $this->editing_may_choose(false);
        $this->setUser($this->editing);

        $html = $this->review_html($ueid);

        $this->assertStringNotContainsString('name="groups[]"', $html);
        $this->assertStringNotContainsString('name="roleid"', $html);
        // They decide, so the form is there; only the choosers are not.
        $this->assertStringContainsString('enrol_apply_review_form', $html);
        $this->assertSame(
            1,
            preg_match('~<div class="enrol_apply-decisionsummary[^"]*">(.*?)</dl>~s', $html, $summary),
            $html
        );
        $this->assertStringContainsString('Own group', $summary[1]);
        $this->assertStringContainsString('Listed group', $summary[1]);
        $this->assertStringNotContainsString('Other group', $summary[1]);
        $student = $DB->get_record('role', ['id' => $this->studentrole], '*', MUST_EXIST);
        $this->assertStringContainsString(role_get_name($student, $this->context), $summary[1]);

        // The control: who may choose gets the choosers and no summary.
        $this->editing_may_choose(true);
        $html = $this->review_html($ueid);
        $this->assertStringContainsString('name="groups[]"', $html);
        $this->assertStringNotContainsString('enrol_apply-decisionsummary', $html);
    }

    /**
     * The participants page's bulk approval offers a decider without choosedecision no group
     * chooser, and says what each approval gives; with it, the chooser.
     *
     * Changes that must make it fail: the bulk form's choosers offered without the capability.
     *
     * @return void
     */
    public function test_the_bulk_form_says_what_an_approval_gives_without_choosedecision(): void {
        $render = function (): string {
            $form = new \enrol_apply\form\bulk_decision_form(null, [
                'users' => [],
                'description' => '',
                'button' => 'Approve',
                'courseid' => (int) $this->course->id,
                'withdecision' => true,
            ]);

            return $form->render();
        };

        $this->editing_may_choose(false);
        $this->setUser($this->editing);
        $html = $render();
        $this->assertStringNotContainsString('name="groups[]"', $html);
        $this->assertStringContainsString(s(get_string('decisionsummarybulk', 'enrol_apply')), $html);

        // The control: who may choose gets the chooser, and the course has groups to offer.
        $this->editing_may_choose(true);
        $html = $render();
        $this->assertStringContainsString('name="groups[]"', $html);
        $this->assertStringNotContainsString(s(get_string('decisionsummarybulk', 'enrol_apply')), $html);
    }

    /**
     * A viewer opens the queue and the review page of an application of their group, both
     * read-only: no selection, no bulk bar, no decision form. The enrolment methods page offers
     * them the queue's icon. A decider gets the forms.
     *
     * Changes that must make it fail: the listing or the review page offering decisions to a
     * viewer, the review page put in the applicant's user context for them, or the icon gated on
     * the deciding capability alone.
     *
     * @return void
     */
    public function test_a_viewer_opens_the_queue_and_the_review_page_read_only(): void {
        [$own] = $this->apply_for($this->mine);
        $viewer = $this->viewer();
        $this->setUser($viewer);
        // The precondition: they hold nothing that decides.
        $this->assertFalse(has_capability(groupscope::DECIDE, $this->context));

        $listing = queue::listing_scope((int) $this->instance->id);
        $this->assertTrue($listing->allowed);
        $this->assertFalse($listing->candecide);
        $this->assertSame([$own], $this->listed());

        $queue = $this->queue_html();
        $this->assertStringContainsString(get_string('queueviewonly', 'enrol_apply'), $queue);
        $this->assertStringNotContainsString('<form id="enrol_apply_manage_form"', $queue);
        $this->assertStringNotContainsString('name="userenrolments[]"', $queue);
        $this->assertStringNotContainsString('name="formaction"', $queue);
        $this->assertStringNotContainsString('name="outcomemessage"', $queue);

        $context = queue::require_review_access(queue::application($own));
        $this->assertSame($this->context->id, $context->id);
        $review = $this->review_html($own);
        $this->assertStringContainsString(get_string('reviewviewonly', 'enrol_apply'), $review);
        $this->assertStringNotContainsString('enrol_apply_review_form', $review);
        $this->assertStringNotContainsString('name="sesskey"', $review);
        $this->assertStringNotContainsString('name="formaction"', $review);

        // The walk is the listing's own: the instance queue, narrowed to their group.
        $scope = queue::scope(queue::application($own), $this->instance);
        $this->assertSame((int) $this->instance->id, $scope->enrolid);
        $this->assertSame([(int) $this->mine->id], $scope->groups);

        $icons = implode('', $this->plugin->get_action_icons($this->instance));
        $this->assertStringContainsString('/enrol/apply/manage.php', $icons);

        // The control: a decider gets both forms.
        $this->setUser($this->editing);
        $this->assertStringContainsString('<form id="enrol_apply_manage_form"', $this->queue_html());
        $this->assertStringContainsString('enrol_apply_review_form', $this->review_html($own));
    }

    /**
     * A viewer who may look at an application decides nothing on it, by any of the three
     * decisions.
     *
     * @return void
     */
    public function test_a_viewer_decides_nothing(): void {
        global $DB;

        $viewer = $this->viewer();
        foreach (['confirm_enrolment', 'wait_enrolment', 'cancel_enrolment'] as $method) {
            [$own, $applicant] = $this->apply_for($this->mine);
            $this->setUser($viewer);
            // The force is on: they may look at this very application.
            $this->assertTrue($this->plugin->can_view_application((int) $this->course->id, (int) $applicant->id, $own));
            $this->assertFalse($this->plugin->can_manage_application((int) $this->course->id, (int) $applicant->id, $own));

            $sink = $this->redirectMessages();
            $this->assertSame(0, $this->plugin->{$method}([$own]), $method);
            $sink->close();

            $this->assertSame(
                ENROL_USER_SUSPENDED,
                (int) $DB->get_field('user_enrolments', 'status', ['id' => $own], MUST_EXIST),
                $method
            );
        }
    }

    /**
     * Separate groups narrow a viewer as they narrow a decider: another group's application and
     * one without a key are refused them. A viewer who may access all groups lists and opens all.
     *
     * Changes that must make it fail: can_view_application()'s course branch not narrowed.
     *
     * @return void
     */
    public function test_a_viewer_is_narrowed_by_separate_groups(): void {
        [$own] = $this->apply_for($this->mine);
        [$other] = $this->apply_for($this->theirs);
        [$keyless] = $this->apply_for(null);

        $this->setUser($this->viewer());
        $this->assertSame([$own], $this->listed());
        foreach ([$other, $keyless] as $ueid) {
            try {
                queue::require_review_access(queue::application($ueid));
                $this->fail("application {$ueid} was opened");
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }

        // The control: moodle/site:accessallgroups lifts the narrowing for a viewer too.
        $this->setUser($this->viewer(true));
        $this->assertEqualsCanonicalizing([$own, $other, $keyless], $this->listed());
        foreach ([$own, $other, $keyless] as $ueid) {
            $this->assertSame($this->context->id, queue::require_review_access(queue::application($ueid))->id);
        }
    }

    /**
     * A viewer at system level opens the site-wide queue read-only, is not narrowed in a
     * separate-groups course though core's rule would narrow them, reads a review page in its
     * course, walks the site-wide queue from it, and decides nothing.
     *
     * Changes that must make it fail: the site-wide queue refused to a viewer or offering them
     * decisions, the instance queue narrowing them by the deciding capability's exemption, or the
     * walk not taking the site-wide queue for them.
     *
     * @return void
     */
    public function test_a_system_viewer_opens_the_site_wide_queue_read_only(): void {
        [$keyless] = $this->apply_for(null);
        $viewer = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role(['shortname' => 'siteviewer']);
        $system = \context_system::instance();
        assign_capability(groupscope::VIEW, CAP_ALLOW, $role, $system->id, true);
        role_assign($role, $viewer->id, $system->id);
        $this->setUser($viewer);
        // The precondition: core's rule alone does narrow them, so the exemption is what is tested.
        $this->assertTrue(groupscope::is_scoped((int) $this->course->id, (int) $viewer->id));

        $sitewide = queue::listing_scope(0);
        $this->assertTrue($sitewide->allowed);
        $this->assertFalse($sitewide->candecide);
        $this->assertNull($sitewide->mentees);

        $instance = queue::listing_scope((int) $this->instance->id);
        $this->assertTrue($instance->allowed);
        $this->assertFalse($instance->candecide);
        $this->assertNull($instance->groups);
        $this->assertSame([$keyless], $this->listed());

        $this->assertSame($this->context->id, queue::require_review_access(queue::application($keyless))->id);
        $scope = queue::scope(queue::application($keyless), $this->instance);
        $this->assertTrue($scope->hasqueue);
        $this->assertSame(0, $scope->enrolid);
        $this->assertNull($scope->mentees);

        $sink = $this->redirectMessages();
        $this->assertSame(0, $this->plugin->confirm_enrolment([$keyless]));
        $sink->close();
    }

    /**
     * A second course with its own apply method, one listed group and one other group.
     *
     * @return array The course, its method, the listed group and the other group.
     */
    private function second_course(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS]);
        $id = $this->plugin->add_instance($course, [
            'roleid' => $this->studentrole,
        ] + $this->plugin->get_instance_defaults());
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $listed = $generator->create_group(['courseid' => $course->id, 'name' => 'Second listed']);
        $theirs = $generator->create_group(['courseid' => $course->id, 'name' => 'Second other']);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $id, 'groupid' => $listed->id]);

        return [$course, $instance, $listed, $theirs];
    }

    /**
     * One confirm_enrolment() call over two courses honours the post only where the decider may
     * choose: the refusal of the first course does not carry into the second.
     *
     * Changes that must make it fail: the override of the first row reassigning the batch's
     * decision, so that every row after it gets the empty choice.
     *
     * @return void
     */
    public function test_a_batch_judges_choosedecision_in_each_course(): void {
        [$course, $instance, $listed, $theirs] = $this->second_course();
        [$first, $firstapplicant] = $this->apply_for(null);
        [$second, $secondapplicant] = $this->apply_for(null, $instance);

        $role = $this->getDataGenerator()->create_role(['shortname' => 'twocoursedecider']);
        $system = \context_system::instance()->id;
        assign_capability(groupscope::DECIDE, CAP_ALLOW, $role, $system, true);
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $role, $system, true);
        assign_capability('enrol/apply:choosedecision', CAP_ALLOW, $role, \context_course::instance($course->id)->id, true);
        $decider = $this->getDataGenerator()->create_user();
        role_assign($role, $decider->id, $system);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($decider);
        // The preconditions: the first course of the batch refuses the choice, the second allows it.
        $this->assertTrue(has_capability('enrol/apply:choosedecision', \context_course::instance($course->id)));
        $this->assertFalse(has_capability('enrol/apply:choosedecision', $this->context));

        $sink = $this->redirectMessages();
        $decided = $this->plugin->confirm_enrolment(
            [$first, $second],
            '',
            ['groups' => [(int) $this->theirs->id, (int) $theirs->id]]
        );
        $sink->close();

        $this->assertSame(2, $decided);
        // The course that refuses: the method's own group, not the posted one.
        $this->assertTrue($this->member($this->listed, $firstapplicant));
        $this->assertFalse($this->member($this->theirs, $firstapplicant));
        // The course that allows it: the posted group, not the method's.
        $this->assertTrue($this->member($theirs, $secondapplicant));
        $this->assertFalse($this->member($listed, $secondapplicant));
    }

    /**
     * A user who views site-wide and mentors keeps the mentee queue they decide in: deciding wins
     * over looking in the listing and in the walk. The pure site-wide viewer is the control.
     *
     * Changes that must make it fail: the mentee queue skipped in favour of the site-wide
     * read-only one, in the listing or in the walk.
     *
     * @return void
     */
    public function test_a_viewer_who_mentors_keeps_the_mentee_queue(): void {
        [$ueid, $applicant] = $this->apply_for(null);
        $system = \context_system::instance();

        $viewrole = $this->getDataGenerator()->create_role(['shortname' => 'siteviewer']);
        assign_capability(groupscope::VIEW, CAP_ALLOW, $viewrole, $system->id, true);
        $mentorrole = $this->getDataGenerator()->create_role(['shortname' => 'applymentor']);
        set_role_contextlevels($mentorrole, [CONTEXT_USER]);
        assign_capability(groupscope::DECIDE, CAP_ALLOW, $mentorrole, $system->id, true);

        $both = $this->getDataGenerator()->create_user();
        role_assign($viewrole, $both->id, $system->id);
        role_assign($mentorrole, $both->id, \context_user::instance($applicant->id)->id);
        $control = $this->getDataGenerator()->create_user();
        role_assign($viewrole, $control->id, $system->id);

        $this->setUser($both);
        $listing = queue::listing_scope(0);
        $this->assertTrue($listing->allowed);
        $this->assertTrue($listing->candecide);
        $this->assertSame([(int) $applicant->id], $listing->mentees);
        $scope = queue::scope(queue::application($ueid), $this->instance);
        $this->assertTrue($scope->hasqueue);
        $this->assertSame([(int) $applicant->id], $scope->mentees);

        $this->setUser($control);
        $listing = queue::listing_scope(0);
        $this->assertTrue($listing->allowed);
        $this->assertFalse($listing->candecide);
        $this->assertNull($listing->mentees);
        $this->assertNull(queue::scope(queue::application($ueid), $this->instance)->mentees);
    }

    /**
     * The review page's earlier applications follow the course report's narrowing: a reader the
     * report narrows to their group sees only the earlier application of that group, a reader of
     * every group sees both.
     *
     * Changes that must make it fail: the panel listing the applicant's records unnarrowed.
     *
     * @return void
     */
    public function test_the_earlier_applications_follow_the_report_narrowing(): void {
        global $DB;

        [$ueid, $applicant] = $this->apply_for($this->mine);
        $application = queue::application($ueid);
        foreach ([[$this->mine, 100], [$this->theirs, 200]] as [$group, $age]) {
            $DB->insert_record('enrol_apply_submission', (object) [
                'courseid' => $this->course->id,
                'userid' => $applicant->id,
                'enrolid' => $this->instance->id,
                'userenrolmentid' => 0,
                'comment' => 'Earlier',
                'status' => submission::STATUS_CANCELLED,
                'outcomemessage' => '',
                'decidedgroups' => '',
                'keygroupid' => $group->id,
                'keyoutcome' => submission::KEY_FOR_REVIEW,
                'timecreated' => time() - $age,
                'timedecided' => time() - $age,
                'decidedby' => 0,
            ]);
        }

        $generator = $this->getDataGenerator();
        $role = $generator->create_role(['shortname' => 'applyreports']);
        $system = \context_system::instance()->id;
        assign_capability('enrol/apply:viewreports', CAP_ALLOW, $role, $system, true);
        $narrowed = $generator->create_and_enrol($this->course, 'applyreports');
        $generator->create_group_member(['groupid' => $this->mine->id, 'userid' => $narrowed->id]);
        $this->assertTrue(groups_is_member((int) $this->mine->id, (int) $narrowed->id));
        $allrole = $generator->create_role(['shortname' => 'applyreportsall']);
        assign_capability('enrol/apply:viewreports', CAP_ALLOW, $allrole, $system, true);
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $allrole, $system, true);
        $control = $generator->create_and_enrol($this->course, 'applyreportsall');

        $history = new \ReflectionMethod(\enrol_apply_renderer::class, 'history_context');
        $renderer = $GLOBALS['PAGE']->get_renderer('enrol_apply');

        $this->setUser($narrowed);
        $this->assertCount(1, $history->invoke($renderer, $application, $this->context)['history']);
        $this->setUser($control);
        $this->assertCount(2, $history->invoke($renderer, $application, $this->context)['history']);
    }
}
