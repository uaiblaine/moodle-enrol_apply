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

namespace enrol_apply;

use enrol_apply\local\groupscope;
use enrol_apply\local\instanceoptions;
use enrol_apply\local\submission;
use enrol_apply\reportbuilder\local\formatters\submission as formatter;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass targets below resolve when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/apply/renderer.php');

/**
 * The decision side of the applicant's group choice (docs/design/applicant-group-choice.md, G5).
 *
 * The course is in separate groups mode. Its method offers a choice and joins "Method group" on
 * approval. The moderator is a non-editing teacher made a decider, a member of "Own group"; the
 * editing teacher sees every group and holds moodle/course:viewhiddengroups by archetype. The
 * coordinator holds a custom role that decides and may access all groups but may NOT see hidden
 * groups, which every stock archetype holding this plugin's capabilities can: it is the reader a
 * group visible to its members only is hidden from. Applications go through the plugin's own
 * apply(), so each carries its record, its chosen group and its notice.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
#[CoversClass(\enrol_apply\local\groupscope::class)]
#[CoversClass(\enrol_apply\local\groupfilter::class)]
#[CoversClass(\enrol_apply\table\applications::class)]
#[CoversClass(\enrol_apply_renderer::class)]
#[CoversClass(\enrol_apply\reportbuilder\local\entities\submission::class)]
#[CoversClass(\enrol_apply\reportbuilder\local\formatters\submission::class)]
final class group_choice_decision_test extends \advanced_testcase {
    /** @var string A name carrying both characters an escaper rewrites. */
    private const AWKWARD = 'R&D < Team';

    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass A course in separate groups mode. */
    private $course;

    /** @var \context_course Its context. */
    private $context;

    /** @var \stdClass Its apply method, offering a choice and joining $listed on approval. */
    private $instance;

    /** @var \stdClass The moderator's group. */
    private $mine;

    /** @var \stdClass Another group of the course. */
    private $theirs;

    /** @var \stdClass The group the method joins on approval. */
    private $listed;

    /** @var \stdClass A group visible to its members only. */
    private $hidden;

    /** @var \stdClass Non-editing teacher made moderator, member of $mine. */
    private $moderator;

    /** @var \stdClass Editing teacher, who sees every group, hidden ones included. */
    private $editing;

    /** @var \stdClass A decider who may access all groups but not see hidden ones. */
    private $coordinator;

    /** @var int The coordinator's role. */
    private $coordinatorrole;

    /**
     * The course, its method, its groups and its three deciders.
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

        $generator = $this->getDataGenerator();
        $system = \context_system::instance()->id;
        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        foreach (['manageapplications', 'viewapplications', 'manage', 'choosedecision'] as $capability) {
            assign_capability('enrol/apply:' . $capability, CAP_ALLOW, $teacher, $system, true);
        }

        $this->coordinatorrole = (int) $generator->create_role(['shortname' => 'applycoordinator']);
        set_role_contextlevels($this->coordinatorrole, [CONTEXT_COURSE]);
        foreach (['manageapplications', 'viewapplications', 'manage', 'choosedecision'] as $capability) {
            assign_capability('enrol/apply:' . $capability, CAP_ALLOW, $this->coordinatorrole, $system, true);
        }
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $this->coordinatorrole, $system, true);
        assign_capability('moodle/course:view', CAP_ALLOW, $this->coordinatorrole, $system, true);
        assign_capability('moodle/course:enrolreview', CAP_ALLOW, $this->coordinatorrole, $system, true);

        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS]);
        $this->context = \context_course::instance($this->course->id);
        $id = $this->plugin->add_instance($this->course, [
            'name' => 'Apply as a moderator',
            'customtext3' => '$@ALL@$',
        ] + $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        instanceoptions::set($id, ['groupchoice' => instanceoptions::CHOICE_OPTIONAL]);

        $this->mine = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Own group']);
        $this->theirs = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Other group']);
        $this->listed = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Method group']);
        $this->hidden = $generator->create_group([
            'courseid' => $this->course->id,
            'name' => 'Hidden group',
            'visibility' => GROUPS_VISIBILITY_MEMBERS,
        ]);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $id, 'groupid' => $this->listed->id]);

        $this->moderator = $generator->create_and_enrol($this->course, 'teacher');
        $generator->create_group_member(['groupid' => $this->mine->id, 'userid' => $this->moderator->id]);
        $this->editing = $generator->create_and_enrol($this->course, 'editingteacher');
        $this->coordinator = $generator->create_user();
        $generator->enrol_user($this->coordinator->id, $this->course->id, $this->coordinatorrole);

        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]));
        $PAGE->set_context($this->context);
    }

    /**
     * Write a pending application with a chosen group through the plugin's own apply(), as the administrator.
     *
     * @param \stdClass|null $group The group chosen; null for "No preference".
     * @param \stdClass|null $applicant Who applies; null for a new user.
     * @return array The user enrolment id, the applicant and the messages the notice sent.
     */
    private function choose(?\stdClass $group, ?\stdClass $applicant = null): array {
        global $USER;

        $previous = $USER;
        $this->setAdminUser();
        $applicant = $applicant ?? $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $written = (new \ReflectionMethod(\enrol_apply_plugin::class, 'apply'))->invoke(
            $this->plugin,
            $this->instance,
            (int) $applicant->id,
            (object) ['applydescription' => 'Please'],
            0,
            submission::KEY_NONE,
            $group ? (int) $group->id : 0
        );
        $messages = $sink->get_messages();
        $sink->close();
        $this->setUser($previous);

        return [(int) $written['userenrolmentid'], $applicant, $messages];
    }

    /**
     * The user enrolment ids the queue's own table lists to the current user.
     *
     * @param array $filters Filter name => raw value, as manage.php hands them over.
     * @return array The ids, ascending, and the table.
     */
    private function listed(array $filters = []): array {
        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id, '', null, $filters);
        ob_start();
        $table->out(50, false);
        ob_end_clean();

        $ids = array_map(static fn($row): int => (int) $row->userenrolmentid, array_values($table->rawdata));
        sort($ids);

        return [$ids, $table];
    }

    /**
     * A role held in the applicant's user context with the deciding capability: a mentor.
     *
     * @param \stdClass $mentee Whom the new mentor mentors.
     * @return \stdClass The mentor.
     */
    private function mentor_of(\stdClass $mentee): \stdClass {
        $mentor = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role(['shortname' => 'applymentor' . $mentee->id]);
        set_role_contextlevels($role, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $mentor->id, \context_user::instance($mentee->id)->id);

        return $mentor;
    }

    /**
     * The review page's form for one application, as the current user sees it.
     *
     * @param int $ueid The application.
     * @param \stdClass $applicant Its applicant.
     * @return string The rendered form.
     */
    private function review_html(int $ueid, \stdClass $applicant): string {
        global $DB, $PAGE;

        $url = new \moodle_url('/enrol/apply/manage.php', ['userenrol' => $ueid]);
        $PAGE->set_url($url);
        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        return $PAGE->get_renderer('enrol_apply')->review_form(
            \enrol_apply\local\queue::application($ueid),
            $applicant,
            $instance,
            $url
        );
    }

    /**
     * Whether a group card of the review page is ticked as the page loads.
     *
     * @param string $html The review page.
     * @param \stdClass $group The group.
     * @return bool|null True ticked, false unticked, null when there is no card.
     */
    private function ticked(string $html, \stdClass $group): ?bool {
        if (!preg_match('~<input[^>]*id="enrol_apply_group_' . (int) $group->id . '"[^>]*>~s', $html, $m)) {
            return null;
        }

        return (bool) preg_match('~\schecked\b~', $m[0]);
    }

    /**
     * The "Already in this course" card of a review page, or null when there is none.
     *
     * @param string $html The review page.
     * @return string|null The card's inner markup.
     */
    private function enrolments_card(string $html): ?string {
        return preg_match('~<section[^>]*data-region="review-enrolments"[^>]*>(.*?)</section>~s', $html, $m) ? $m[1] : null;
    }

    /**
     * Whether a user is a member of a group through this plugin.
     *
     * @param \stdClass $group The group.
     * @param \stdClass $user The user.
     * @return bool True for a stamped membership.
     */
    private function member(\stdClass $group, \stdClass $user): bool {
        global $DB;

        return $DB->record_exists('groups_members', [
            'groupid' => $group->id,
            'userid' => $user->id,
            'component' => 'enrol_apply',
            'itemid' => $this->instance->id,
        ]);
    }

    /**
     * The message one recipient got among a notice's messages, or null.
     *
     * @param array $messages The messages.
     * @param \stdClass $user The recipient.
     * @return \stdClass|null The message.
     */
    private static function message_to(array $messages, \stdClass $user): ?\stdClass {
        foreach ($messages as $message) {
            if ((int) $message->useridto === (int) $user->id) {
                return $message;
            }
        }

        return null;
    }

    /**
     * A chosen group routes the application to that group's moderator and to no other group's, in
     * the listing and the per-row check alike (C-D11); "No preference" reaches only who sees every
     * group, and the editing teacher lists all three.
     *
     * Changes that must make it fail: the scope's SQL reading the key group alone, or the CASE
     * forgetting the chosen group.
     *
     * @return void
     */
    public function test_a_chosen_application_reaches_its_groups_deciders_only(): void {
        [$own, $ownapplicant] = $this->choose($this->mine);
        [$other, $otherapplicant] = $this->choose($this->theirs);
        [$nopreference, $nopreferenceapplicant] = $this->choose(null);

        $this->setUser($this->moderator);
        $this->assertSame([$own], $this->listed()[0]);
        $this->assertTrue($this->plugin->can_manage_application($this->course->id, $ownapplicant->id, $own));
        $this->assertFalse($this->plugin->can_manage_application($this->course->id, $otherapplicant->id, $other));
        $this->assertFalse($this->plugin->can_manage_application($this->course->id, $nopreferenceapplicant->id, $nopreference));

        $this->setUser($this->editing);
        $expected = [$own, $other, $nopreference];
        sort($expected);
        $this->assertSame($expected, $this->listed()[0]);

        // Under "Only those who see every group" the chosen group routes nobody (C-D20).
        instanceoptions::set((int) $this->instance->id, ['deciders' => instanceoptions::DECIDERS_ALLGROUPS]);
        $this->setUser($this->moderator);
        $this->assertSame([], $this->listed()[0]);
        $this->assertFalse($this->plugin->can_manage_application($this->course->id, $ownapplicant->id, $own));
    }

    /**
     * The queue files a chosen application under its group: the group column names it, the
     * group's filter value selects it, and "Without a group" does not.
     *
     * Changes that must make it fail: the column's join, or the "none" predicate, reading the key
     * group alone.
     *
     * @return void
     */
    public function test_the_queue_files_a_chosen_application_under_its_group(): void {
        [$own] = $this->choose($this->mine);
        [$nopreference] = $this->choose(null);

        $this->setUser($this->editing);
        [, $table] = $this->listed();
        $groups = [];
        foreach ($table->rawdata as $row) {
            $groups[(int) $row->userenrolmentid] = (int) $row->keygroupid;
        }
        $this->assertSame((int) $this->mine->id, $groups[$own]);
        $this->assertSame(0, $groups[$nopreference]);
        $ownrow = array_values(array_filter(
            (array) $table->rawdata,
            static fn($row): bool => (int) $row->userenrolmentid === $own
        ))[0];
        $this->assertStringContainsString('Own group', $table->col_keygroup($ownrow));

        $this->assertSame([$own], $this->listed(['group' => (string) $this->mine->id])[0]);
        $this->assertSame([$nopreference], $this->listed(['group' => \enrol_apply\local\groupfilter::NONE])[0]);
    }

    /**
     * The managers' notice is routed by the chosen group and names it to each recipient who may
     * see it: the moderator of the group and the editing teacher by name, the coordinator (who may
     * not see a hidden group) and a mentor (who holds nothing in the course) without it.
     *
     * Changes that must make it fail: the recipients narrowed by the key group alone, the name
     * handed to a recipient the group is hidden from, or to one without the course capability.
     *
     * @return void
     */
    public function test_the_notice_is_routed_by_the_chosen_group_and_names_it_per_recipient(): void {
        $named = get_string('mailtoteacher_chosengroup', 'enrol_apply', 'Own group');
        $unnamed = get_string('mailtoteacher_chosengroupunnamed', 'enrol_apply');

        $applicant = $this->getDataGenerator()->create_user();
        $mentor = $this->mentor_of($applicant);
        [, , $own] = $this->choose($this->mine, $applicant);
        [, , $other] = $this->choose($this->theirs);
        [, , $hidden] = $this->choose($this->hidden);

        $this->assertNotNull(self::message_to($own, $this->moderator), 'their group');
        $this->assertNull(self::message_to($other, $this->moderator), 'another group');
        $this->assertStringContainsString($named, self::message_to($own, $this->moderator)->fullmessagehtml);
        $this->assertStringContainsString($named, self::message_to($own, $this->editing)->fullmessagehtml);

        $mentormessage = self::message_to($own, $mentor);
        $this->assertNotNull($mentormessage, 'the mentor is told');
        $this->assertStringContainsString($unnamed, $mentormessage->fullmessagehtml);
        $this->assertStringNotContainsString('Own group', $mentormessage->fullmessagehtml);

        $coordinator = self::message_to($hidden, $this->coordinator);
        $this->assertNotNull($coordinator, 'who may access all groups is told');
        $this->assertStringContainsString($unnamed, $coordinator->fullmessagehtml);
        $this->assertStringNotContainsString('Hidden group', $coordinator->fullmessagehtml);
        // The control: who may see hidden groups is told its name.
        $this->assertStringContainsString(
            get_string('mailtoteacher_chosengroup', 'enrol_apply', 'Hidden group'),
            self::message_to($hidden, $this->editing)->fullmessagehtml
        );
    }

    /**
     * The review page shows "Chose: <group>" and ticks the chosen group on top of the method's,
     * badged as the applicant's choice, with the line a decider who unticks it would read.
     *
     * Changes that must make it fail: the chosen group dropped from the approval's defaults, or
     * the card and the line worded as a key's.
     *
     * @return void
     */
    public function test_the_review_page_shows_the_chip_and_ticks_the_chosen_group(): void {
        [$own, $applicant] = $this->choose($this->mine);

        $this->setUser($this->editing);
        $html = $this->review_html($own, $applicant);

        $this->assertMatchesRegularExpression('~data-region="chosen-chip"[^>]*>.*?Chose: Own group</span>~s', $html);
        $this->assertTrue($this->ticked($html, $this->mine), 'the choice');
        $this->assertTrue($this->ticked($html, $this->listed), 'the method\'s group');
        $this->assertFalse($this->ticked($html, $this->theirs));
        $this->assertStringContainsString(s(get_string('reviewgroupchosen', 'enrol_apply')), $html);
        $this->assertStringNotContainsString(s(get_string('reviewgroupkey', 'enrol_apply')), $html);
        $this->assertStringContainsString(
            get_string('reviewoutcomenotchosen', 'enrol_apply', 'Own group'),
            $html,
            'the line shown once the choice is unticked'
        );
    }

    /**
     * The chip names the chosen group only to a reader who may see it: the coordinator reads
     * "Chose a group" for a hidden group and the group is named nowhere on their page, a mentor
     * reads it for any group, and the editing teacher reads the name. A record with a key route
     * gets no chosen chip at all, even with a chosen group written beside it by hand.
     *
     * Changes that must make it fail: the chip ignoring core's visibility rule, naming a group to
     * a mentor, or shown beside a key's.
     *
     * @return void
     */
    public function test_the_chip_names_the_group_only_to_who_may_see_it(): void {
        global $DB;

        $chip = '~data-region="chosen-chip"[^>]*>(.*?)</span>~s';
        [$hiddenueid, $hiddenapplicant] = $this->choose($this->hidden);

        $this->setUser($this->coordinator);
        $html = $this->review_html($hiddenueid, $hiddenapplicant);
        $this->assertMatchesRegularExpression($chip, $html);
        preg_match($chip, $html, $m);
        $this->assertStringContainsString(get_string('chosenchipunnamed', 'enrol_apply'), $m[1]);
        $this->assertStringNotContainsString('Hidden group', $html);

        $this->setUser($this->editing);
        preg_match($chip, $this->review_html($hiddenueid, $hiddenapplicant), $m);
        $this->assertStringContainsString('Chose: Hidden group', $m[1], 'the control');

        $applicant = $this->getDataGenerator()->create_user();
        $mentor = $this->mentor_of($applicant);
        [$own] = $this->choose($this->mine, $applicant);
        $this->setUser($mentor);
        preg_match($chip, $this->review_html($own, $applicant), $m);
        $this->assertStringContainsString(get_string('chosenchipunnamed', 'enrol_apply'), $m[1]);
        $this->assertStringNotContainsString('Own group', $m[1]);

        // A key route beside a choice, which only a hand edit writes: the key's chip alone.
        [$keyed, $keyedapplicant] = $this->choose($this->mine);
        $DB->set_field('enrol_apply_submission', 'keygroupid', $this->theirs->id, ['userenrolmentid' => $keyed]);
        $DB->set_field('enrol_apply_submission', 'keyoutcome', submission::KEY_FOR_REVIEW, ['userenrolmentid' => $keyed]);
        $this->setUser($this->editing);
        $this->assertDoesNotMatchRegularExpression($chip, $this->review_html($keyed, $keyedapplicant));
    }

    /**
     * An approval that records no group choice joins the chosen group plus the method's: the
     * plugin's approval with no decision, a bulk approval with an empty choice, core's "Edit
     * enrolment" and a decider without enrol/apply:choosedecision whatever they post. The groups
     * joined are recorded for the approval message.
     *
     * Changes that must make it fail: the chosen group left out of default_approval_groups().
     *
     * @return void
     */
    public function test_an_approval_without_a_recorded_choice_joins_the_chosen_group_and_the_methods(): void {
        global $DB;

        $expected = [(int) $this->theirs->id, (int) $this->listed->id];
        sort($expected);

        [$plain, $plainapplicant] = $this->choose($this->theirs);
        [$bulk, $bulkapplicant] = $this->choose($this->theirs);
        [$edit, $editapplicant] = $this->choose($this->theirs);
        [$nochooser, $nochooserapplicant] = $this->choose($this->theirs);

        $sink = $this->redirectMessages();
        $this->setUser($this->editing);
        $this->assertSame(1, $this->plugin->confirm_enrolment([$plain]));
        $this->assertSame(1, $this->plugin->confirm_enrolment([$bulk], '', ['groups' => [], 'roleid' => 0]));
        $this->plugin->update_user_enrol($this->instance, $editapplicant->id, ENROL_USER_ACTIVE);

        unassign_capability('enrol/apply:choosedecision', $this->coordinatorrole, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->coordinator);
        $this->assertSame(1, $this->plugin->confirm_enrolment([$nochooser], '', ['groups' => [(int) $this->mine->id]]));
        $sink->close();

        foreach ([$plain => $plainapplicant, $bulk => $bulkapplicant, $edit => $editapplicant] as $ueid => $applicant) {
            $this->assertTrue($this->member($this->theirs, $applicant), "the choice, {$ueid}");
            $this->assertTrue($this->member($this->listed, $applicant), "the method's group, {$ueid}");
            $this->assertSame(
                $expected,
                submission::stamped_groups((int) $this->course->id, (int) $this->instance->id, (int) $applicant->id)
            );
        }
        $this->assertTrue($this->member($this->theirs, $nochooserapplicant));
        $this->assertTrue($this->member($this->listed, $nochooserapplicant));
        $this->assertFalse($this->member($this->mine, $nochooserapplicant), 'a decider who may not choose adds nothing');

        // The approval message reads what was joined.
        $this->assertSame(
            implode(',', $expected),
            (string) $DB->get_field('enrol_apply_submission', 'joinedgroups', ['userenrolmentid' => $plain])
        );
    }

    /**
     * A decider who unticks the chosen group and picks another places the applicant there alone.
     *
     * The control is the approval without a recorded choice above, which joins the chosen group.
     *
     * Changes that must make it fail: the chosen group added on top of a recorded choice.
     *
     * @return void
     */
    public function test_a_decider_who_unticks_the_chosen_group_leaves_it_out(): void {
        [$ueid, $applicant] = $this->choose($this->theirs);

        $this->setUser($this->editing);
        $sink = $this->redirectMessages();
        $this->assertSame(1, $this->plugin->confirm_enrolment([$ueid], '', ['groups' => [(int) $this->mine->id]]));
        $sink->close();

        $this->assertTrue($this->member($this->mine, $applicant));
        $this->assertFalse($this->member($this->theirs, $applicant));
        $this->assertFalse($this->member($this->listed, $applicant), 'the editing teacher may choose every group');
    }

    /**
     * A chosen group deleted while the application waited is skipped: the approval joins the
     * method's group and does not throw.
     *
     * Changes that must make it fail: the deleted group handed to groups_add_member(), which
     * throws on an unknown group.
     *
     * @return void
     */
    public function test_a_chosen_group_deleted_while_waiting_is_skipped(): void {
        [$ueid, $applicant] = $this->choose($this->theirs);
        groups_delete_group($this->theirs->id);

        $this->assertSame([(int) $this->listed->id], $this->plugin->default_approval_groups($this->instance, $ueid));
        $this->setUser($this->editing);
        $sink = $this->redirectMessages();
        $this->assertSame(1, $this->plugin->confirm_enrolment([$ueid]));
        $sink->close();
        $this->assertTrue($this->member($this->listed, $applicant));
    }

    /**
     * The course report files a record under its chosen group, as the narrowing and the group
     * filter read it (record_group_where()), and never under another group.
     *
     * Changes that must make it fail: the report's predicate reading the key group alone.
     *
     * @return void
     */
    public function test_the_report_files_a_chosen_record_under_its_group(): void {
        global $DB;

        [$own] = $this->choose($this->mine);
        [$other] = $this->choose($this->theirs);

        [$where, $params] = groupscope::record_where('s', [(int) $this->mine->id], 'chosentest');
        $matched = array_map('intval', $DB->get_fieldset_sql(
            "SELECT s.userenrolmentid FROM {enrol_apply_submission} s WHERE s.courseid = :courseid AND {$where}",
            $params + ['courseid' => $this->course->id]
        ));
        $this->assertSame([$own], $matched);

        [$where, $params] = groupscope::record_group_where('s', [(int) $this->theirs->id], 'chosenfilter');
        $matched = array_map('intval', $DB->get_fieldset_sql(
            "SELECT s.userenrolmentid FROM {enrol_apply_submission} s WHERE s.courseid = :courseid AND {$where}",
            $params + ['courseid' => $this->course->id]
        ));
        $this->assertSame([$other], $matched);
    }

    /**
     * The report's "Group chosen" column names the group to a reader who may see it, escaped
     * once; leaves the cell empty for no choice and for a group hidden from the reader; says a
     * deleted group is gone; and is not sortable, as the key group's is not.
     *
     * Changes that must make it fail: the visibility gate, the escaping or the no-choice branch
     * dropped from formatter::chosen_group(), or the column made sortable.
     *
     * @return void
     */
    public function test_the_report_column_names_the_chosen_group_to_who_may_see_it(): void {
        global $DB;

        $DB->set_field('groups', 'name', self::AWKWARD, ['id' => $this->mine->id]);
        $row = static fn(\stdClass $group): \stdClass => (object) [
            'chosengroupid' => $group->id,
            'chosengroupcourseid' => $group->courseid,
            'chosengroupvisibility' => $group->visibility ?? GROUPS_VISIBILITY_ALL,
        ];

        $this->setUser($this->coordinator);
        $this->assertSame('R&amp;D &lt; Team', formatter::chosen_group(self::AWKWARD, $row($this->mine)));
        $this->assertSame('', formatter::chosen_group('Hidden group', $row($this->hidden)));
        $this->assertSame('', formatter::chosen_group(null, (object) ['chosengroupid' => 0]));
        $this->assertSame(
            get_string('keygroupgone', 'enrol_apply'),
            formatter::chosen_group(null, (object) ['chosengroupid' => 999999])
        );

        $this->setUser($this->editing);
        $this->assertSame('Hidden group', formatter::chosen_group('Hidden group', $row($this->hidden)), 'the control');

        $entity = (new \enrol_apply\reportbuilder\local\entities\submission())->initialise();
        $this->assertFalse($entity->get_column('chosengroup')->get_is_sortable());
    }

    /**
     * Before the upgrade that adds chosengroupid nothing reads it: the report offers no "Group
     * chosen" column and the application's group in SQL is the key's alone.
     *
     * Changes that must make it fail: either reader asking for the column without the schema gate.
     *
     * @return void
     */
    public function test_before_the_upgrade_nothing_reads_the_choice(): void {
        set_config('version', ENROL_APPLY_OPTIONS_SCHEMA - 1, 'enrol_apply');
        $this->assertFalse(instanceoptions::schema_ready(), 'the premise');

        $entity = (new \enrol_apply\reportbuilder\local\entities\submission())->initialise();
        $this->assertArrayNotHasKey('chosengroup', $entity->get_columns());
        $this->assertStringNotContainsString('chosengroupid', groupscope::application_group_sql('s'));

        // The control: once it has run, both read it.
        set_config('version', ENROL_APPLY_OPTIONS_SCHEMA, 'enrol_apply');
        $entity = (new \enrol_apply\reportbuilder\local\entities\submission())->initialise();
        $this->assertArrayHasKey('chosengroup', $entity->get_columns());
        $this->assertStringContainsString('chosengroupid', groupscope::application_group_sql('s'));
    }

    /**
     * On the queue, a decider who may not choose is told that an application made with a chosen
     * group also joins it.
     *
     * Changes that must make it fail: the sentence left to the reviewed key mode alone.
     *
     * @return void
     */
    public function test_the_queue_summary_says_a_chosen_group_is_joined(): void {
        global $PAGE;

        unassign_capability('enrol/apply:choosedecision', $this->coordinatorrole, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->coordinator);

        $renderer = $PAGE->get_renderer('enrol_apply');
        $context = (new \ReflectionMethod($renderer, 'decision_controls_context'))->invoke($renderer, $this->instance);

        $this->assertTrue($context['hassummary']);
        $this->assertTrue($context['hassummarykeynote']);
        $this->assertSame(get_string('decisionsummarychosen', 'enrol_apply'), $context['summarykeynote']);
    }

    /**
     * The rules' meter of the chosen group says it is the applicant's choice, not a key's.
     *
     * Changes that must make it fail: the label worded as the key group's.
     *
     * @return void
     */
    public function test_the_rules_meter_names_the_choice(): void {
        global $PAGE;

        $renderer = $PAGE->get_renderer('enrol_apply');
        $fact = (object) [
            'id' => 7,
            'name' => 'Turma 7',
            'idnumber' => '',
            'hasseats' => true,
            'members' => 3,
            'seats' => 10,
            'full' => false,
            'percent' => 30,
        ];
        $capacity = ['hascapacity' => false];
        $rules = (new \ReflectionMethod($renderer, 'review_rules_context'))
            ->invoke($renderer, [7 => $fact], [7], 7, $capacity, [], true);

        $this->assertSame(get_string('reviewmetergroupchosen', 'enrol_apply', 'Turma 7'), $rules['meters'][0]['label']);
    }

    /**
     * "Already in this course" (C-D5): the applicant's other enrolments, by method, with their
     * status, the roles each method gave, the groups joined through it and how it was admitted,
     * as recorded. The reviewed application is not among them, and a role given outside any method
     * is listed once rather than credited to one.
     *
     * The applicant got into the course through a student method with Evening's key (a membership
     * of "Own group" and of the hidden group, stamped, with a stamped Student role) and through a
     * manual enrolment as Student (unstamped), and now applies to moderate.
     *
     * Names follow the page's rules: the editing teacher reads every group and the key's group; the
     * coordinator, who may not see hidden groups, reads "Own group" alone and an unnamed key; a
     * mentor reads no panel, and given core's enrolment gate in the course still no group name.
     *
     * Changes that must make it fail: the reviewed enrolment listed, roles credited to a method
     * that did not give them, group names shown to a mentor, or the key's group named to a reader
     * it is hidden from.
     *
     * @return void
     */
    public function test_the_panel_lists_the_other_enrolments_as_recorded(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $applicant = $generator->create_user();
        $mentor = $this->mentor_of($applicant);
        $studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

        $studentid = $this->plugin->add_instance($this->course, [
            'name' => 'Apply as a student',
        ] + $this->plugin->get_instance_defaults());
        $student = $DB->get_record('enrol', ['id' => $studentid], '*', MUST_EXIST);
        $this->plugin->enrol_user($student, $applicant->id, null, time() - DAYSECS, 0, ENROL_USER_ACTIVE);
        $studentue = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $studentid, 'userid' => $applicant->id]);
        role_assign($studentrole, $applicant->id, $this->context->id, 'enrol_apply', $studentid);
        groups_add_member($this->mine->id, $applicant->id, 'enrol_apply', $studentid);
        groups_add_member($this->hidden->id, $applicant->id, 'enrol_apply', $studentid);
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $applicant->id,
            'enrolid' => $studentid,
            'userenrolmentid' => $studentue,
            'comment' => '',
            'status' => submission::STATUS_APPROVED,
            'timecreated' => time() - DAYSECS,
            'timedecided' => time() - DAYSECS,
            'decidedby' => $applicant->id,
            'keygroupid' => $this->hidden->id,
            'keyoutcome' => submission::KEY_ADMITTED,
            'joinedgroups' => implode(',', [(int) $this->mine->id, (int) $this->hidden->id]),
        ]);
        $generator->enrol_user($applicant->id, $this->course->id, 'student', 'manual');

        [$ueid] = $this->choose($this->theirs, $applicant);

        $this->setUser($this->editing);
        $card = $this->enrolments_card($this->review_html($ueid, $applicant));
        $this->assertNotNull($card);
        $this->assertStringContainsString('Apply as a student', $card);
        $this->assertStringNotContainsString('Apply as a moderator', $card, 'the application under review');
        $this->assertStringContainsString(get_string('participationactive', 'enrol'), $card);
        $this->assertStringContainsString('Own group', $card);
        $this->assertStringContainsString(get_string('reviewenrolmentsbykey', 'enrol_apply', 'Hidden group'), $card);
        $this->assertStringContainsString(get_string('pluginname', 'enrol_manual'), $card);
        // Student twice: the student method's own entry, and the manual role listed apart.
        $this->assertSame(1, preg_match_all('~<dd>Student</dd>~', $card), 'one entry credited with a role');
        $this->assertStringContainsString(get_string('reviewenrolmentsotherroles', 'enrol_apply') . ': Student', $card);

        $this->setUser($this->coordinator);
        $card = $this->enrolments_card($this->review_html($ueid, $applicant));
        $this->assertStringContainsString('Own group', $card);
        $this->assertStringNotContainsString('Hidden group', $card);
        $this->assertStringContainsString(get_string('reviewenrolmentsbykeyunnamed', 'enrol_apply'), $card);

        // A mentor holds nothing in the course, so not core's gate for reading enrolments: no panel.
        $this->setUser($mentor);
        $this->assertNull($this->enrolments_card($this->review_html($ueid, $applicant)));

        // Given core's gate (and every group) in the course but no capability of this plugin's there,
        // the mentor reads the panel and still no group name.
        $reader = (int) $generator->create_role(['shortname' => 'applyenrolreader']);
        set_role_contextlevels($reader, [CONTEXT_COURSE]);
        foreach (['moodle/course:enrolreview', 'moodle/site:accessallgroups'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $reader, \context_system::instance()->id, true);
        }
        role_assign($reader, $mentor->id, $this->context->id);
        $card = $this->enrolments_card($this->review_html($ueid, $applicant));
        $this->assertNotNull($card);
        $this->assertStringContainsString('Apply as a student', $card);
        $this->assertStringNotContainsString('Own group', $card);
        $this->assertStringNotContainsString('Hidden group', $card);
    }

    /**
     * A moderator narrowed by separate groups sees the panel only with core's enrolment gate, and
     * then only this plugin's records the course report shows them: their own group's, never
     * another group's, never a method decided only by those who see every group.
     *
     * Changes that must make it fail: the panel shown without moodle/course:enrolreview, or its
     * entries not narrowed as the report narrows.
     *
     * @return void
     */
    public function test_the_panel_is_gated_and_narrowed_as_the_report_is(): void {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $entry = function (string $name, \stdClass $group, int $deciders) use ($applicant): void {
            global $DB;
            $id = $this->plugin->add_instance($this->course, ['name' => $name] + $this->plugin->get_instance_defaults());
            instanceoptions::set($id, ['deciders' => $deciders]);
            $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
            $this->plugin->enrol_user($instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
            $ueid = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $id, 'userid' => $applicant->id]);
            $DB->insert_record('enrol_apply_submission', (object) [
                'courseid' => $this->course->id,
                'userid' => $applicant->id,
                'enrolid' => $id,
                'userenrolmentid' => $ueid,
                'comment' => '',
                'status' => submission::STATUS_PENDING,
                'timecreated' => time() - DAYSECS,
                'chosengroupid' => (int) $group->id,
            ]);
        };
        $entry('Mine by choice', $this->mine, instanceoptions::DECIDERS_GROUP);
        $entry('Theirs by choice', $this->theirs, instanceoptions::DECIDERS_GROUP);
        $entry('Mine, every group decides', $this->mine, instanceoptions::DECIDERS_ALLGROUPS);

        [$ueid] = $this->choose($this->mine, $applicant);
        $this->setUser($this->moderator);
        $this->assertNull($this->enrolments_card($this->review_html($ueid, $applicant)), 'no enrolreview, no panel');

        assign_capability('moodle/course:enrolreview', CAP_ALLOW, $teacher, \context_system::instance()->id, true);
        $card = $this->enrolments_card($this->review_html($ueid, $applicant));
        $this->assertNotNull($card);
        $this->assertStringContainsString('Mine by choice', $card);
        $this->assertStringNotContainsString('Theirs by choice', $card);
        $this->assertStringNotContainsString('every group decides', $card);

        // The control: a reader who sees every group lists all three.
        $this->setUser($this->editing);
        $card = $this->enrolments_card($this->review_html($ueid, $applicant));
        $this->assertStringContainsString('Theirs by choice', $card);
        $this->assertStringContainsString('every group decides', $card);
    }

    /**
     * The panel names who approved another enrolment only to a holder of enrol/apply:viewreports,
     * the gate under which the page already names deciders; anybody else reads the date. An
     * application of the applicant's still waiting elsewhere reads as awaiting a decision.
     *
     * Changes that must make it fail: the decider named without the report capability, or a
     * pending application read as suspended.
     *
     * @return void
     */
    public function test_the_panel_names_the_decider_only_under_the_history_gate(): void {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $approvedid = $this->plugin->add_instance($this->course, [
            'name' => 'Apply as a student',
        ] + $this->plugin->get_instance_defaults());
        $approved = $DB->get_record('enrol', ['id' => $approvedid], '*', MUST_EXIST);
        $this->plugin->enrol_user($approved, $applicant->id, null, time() - DAYSECS, 0, ENROL_USER_ACTIVE);
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $applicant->id,
            'enrolid' => $approvedid,
            'userenrolmentid' => (int) $DB->get_field(
                'user_enrolments',
                'id',
                ['enrolid' => $approvedid, 'userid' => $applicant->id]
            ),
            'comment' => '',
            'status' => submission::STATUS_APPROVED,
            'timecreated' => time() - DAYSECS,
            'timedecided' => time() - HOURSECS,
            'decidedby' => $this->editing->id,
            'keygroupid' => 0,
            'keyoutcome' => submission::KEY_NONE,
            'joinedgroups' => '',
        ]);

        $pendingid = $this->plugin->add_instance($this->course, [
            'name' => 'Apply to the waiting cohort',
        ] + $this->plugin->get_instance_defaults());
        $pending = $DB->get_record('enrol', ['id' => $pendingid], '*', MUST_EXIST);
        $this->plugin->enrol_user($pending, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        submission::create(
            $pending,
            (int) $applicant->id,
            (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $pendingid, 'userid' => $applicant->id]),
            (object) []
        );

        [$ueid] = $this->choose($this->theirs, $applicant);
        $name = fullname($this->editing);

        $this->setAdminUser();
        $card = $this->enrolments_card($this->review_html($ueid, $applicant));
        $this->assertStringContainsString($name, $card, 'the report\'s reader');
        $this->assertStringContainsString(get_string('outcomeawaiting', 'enrol_apply'), $card);

        $this->setUser($this->editing);
        $this->assertFalse(has_capability('enrol/apply:viewreports', $this->context), 'the premise');
        $card = $this->enrolments_card($this->review_html($ueid, $applicant));
        $this->assertStringNotContainsString($name, $card);
        $this->assertStringContainsString(
            get_string('reviewenrolmentsapprovedon', 'enrol_apply', userdate(
                (int) $DB->get_field('enrol_apply_submission', 'timedecided', ['enrolid' => $approvedid]),
                get_string('strftimedatetimeshort', 'langconfig')
            )),
            $card
        );
    }

    /**
     * Another of the applicant's applications that has LAPSED (not active, its end date passed) reads
     * as not current, as the queue treats it, not as awaiting a decision or on the waiting list; one
     * still in the queue keeps its words.
     *
     * Three other methods: a pending application with no end (the control), a pending one and a
     * deferred one whose end date has passed, which the queue no longer lists.
     *
     * Changes that must make it fail: the panel judging an application's state by its status and
     * record alone, without queue::is_awaiting_decision() (gate PN1).
     *
     * @return void
     */
    public function test_the_panel_reads_a_lapsed_application_as_not_current(): void {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $method = function (string $name, int $status, int $timeend) use ($applicant, $DB): void {
            $id = $this->plugin->add_instance($this->course, ['name' => $name] + $this->plugin->get_instance_defaults());
            $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
            $this->plugin->enrol_user($instance, $applicant->id, null, $timeend ? $timeend - DAYSECS : 0, $timeend, $status);
            submission::create(
                $instance,
                (int) $applicant->id,
                (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $id, 'userid' => $applicant->id], MUST_EXIST),
                (object) []
            );
        };
        $method('Still waiting', ENROL_USER_SUSPENDED, 0);
        $method('Lapsed while pending', ENROL_USER_SUSPENDED, time() - DAYSECS);
        $method('Lapsed on the list', ENROL_APPLY_USER_WAIT, time() - DAYSECS);

        [$ueid] = $this->choose($this->theirs, $applicant);
        $this->setAdminUser();
        $card = $this->enrolments_card($this->review_html($ueid, $applicant));

        $this->assertNotNull($card);
        $this->assertSame(1, substr_count($card, get_string('outcomeawaiting', 'enrol_apply')), 'the control and nothing else');
        $this->assertSame(0, substr_count($card, get_string('outcomewaiting', 'enrol_apply')));
        $this->assertSame(2, substr_count($card, get_string('participationnotcurrent', 'enrol')));
    }
}
