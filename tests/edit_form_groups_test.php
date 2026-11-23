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

use enrol_apply\local\groupkey;
use enrol_apply\local\groupoffer;
use enrol_apply\local\groupquestion;
use enrol_apply\local\instanceoptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass targets below resolve when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/edit_form.php');

/**
 * The instance form's Groups section (docs/design/applicant-group-choice.md, stage G3).
 *
 * The question "How is an applicant's group decided?" and its storage over customchar1 and the
 * groupchoice option (C-D6), the offer and its grouping shortcut (C-D3), "Who decides" (C-D20),
 * the group seats per method (C-D26), the hidden card (C-D25), the validation, the warnings of
 * C-D27 and the summary strip's two new facts. The course is in separate groups mode with Evening
 * A and Evening B in the grouping "Evenings", and Moderadores outside it.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_edit_form::class)]
#[CoversClass(groupquestion::class)]
final class edit_form_groups_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass A course in separate groups mode. */
    private $course;

    /** @var \stdClass Its apply method, granting Student. */
    private $instance;

    /** @var \stdClass Evening A, in the grouping. */
    private $eveninga;

    /** @var \stdClass Evening B, in the grouping. */
    private $eveningb;

    /** @var \stdClass Moderadores, outside the grouping. */
    private $moderadores;

    /** @var \stdClass The grouping of the two evening groups. */
    private $grouping;

    /**
     * The course, its groups and grouping, and one method.
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
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS]);
        $this->instance = $this->method((int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST));

        $this->eveninga = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Evening A']);
        $this->eveningb = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Evening B']);
        $this->moderadores = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Moderadores']);
        $this->grouping = $generator->create_grouping(['courseid' => $this->course->id, 'name' => 'Evenings']);
        foreach ([$this->eveninga, $this->eveningb] as $group) {
            $generator->create_grouping_group(['groupingid' => $this->grouping->id, 'groupid' => $group->id]);
        }

        $PAGE->set_url(new \moodle_url('/enrol/apply/edit.php', ['courseid' => $this->course->id]));
        $this->setAdminUser();
    }

    /**
     * A method of the course granting a role.
     *
     * @param int $roleid The role it grants on approval.
     * @return \stdClass The {enrol} record.
     */
    private function method(int $roleid): \stdClass {
        global $DB;

        $id = $this->plugin->add_instance($this->course, ['roleid' => $roleid] + $this->plugin->get_instance_defaults());

        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * A stock role's id.
     *
     * @param string $shortname The short name.
     * @return int The id.
     */
    private function role(string $shortname): int {
        global $DB;

        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * The edit form for a method.
     *
     * @param \stdClass|null $instance The method, the saved one by default.
     * @return \enrol_apply_edit_form The form.
     */
    private function form(?\stdClass $instance = null): \enrol_apply_edit_form {
        return new \enrol_apply_edit_form(
            null,
            [$instance ?? $this->instance, $this->plugin, \context_course::instance($this->course->id)]
        );
    }

    /**
     * The QuickForm a form built.
     *
     * @param \enrol_apply_edit_form $form The form.
     * @return \MoodleQuickForm Its QuickForm.
     */
    private function quickform(\enrol_apply_edit_form $form): \MoodleQuickForm {
        return (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
    }

    /**
     * Validation data that passes every check outside the Groups section, with the given values on top.
     *
     * @param array $values Element values, keyed by element name.
     * @return array The data validation() receives.
     */
    private function submission(array $values): array {
        return array_merge([
            'status' => ENROL_INSTANCE_ENABLED,
            'enrolstartdate' => 0,
            'enrolenddate' => 0,
            'customint5' => 0,
            'customchar1' => '',
            'customchar2' => '',
            'deciders' => '0',
            'groupdecided' => groupquestion::DECIDER,
        ], $values);
    }

    /**
     * Every stored combination, with the answer the form shows for it.
     *
     * @return array customchar1, groupchoice and the expected answer, keyed by a readable case name.
     */
    public static function answer_provider(): array {
        return [
            'nothing' => ['', instanceoptions::CHOICE_OFF, groupquestion::DECIDER],
            'a valid key enrols at once' => ['any', instanceoptions::CHOICE_OFF, groupquestion::KEY],
            'key only' => ['required', instanceoptions::CHOICE_OFF, groupquestion::KEY],
            'key only, reviewed' => ['review', instanceoptions::CHOICE_OFF, groupquestion::KEY],
            'a choice' => ['', instanceoptions::CHOICE_REQUIRED, groupquestion::CHOICE],
            'a choice with a valid key enrolling' => ['any', instanceoptions::CHOICE_OPTIONAL, groupquestion::CHOICE],
            'a choice beside key only, which the key wins' => ['review', instanceoptions::CHOICE_REQUIRED, groupquestion::KEY],
            'an unknown mode a restore carried in' => ['listed', instanceoptions::CHOICE_OFF, groupquestion::DECIDER],
        ];
    }

    /**
     * The form reads its answer from the two stored settings with the strict readers.
     *
     * Changes that must make it fail: a choice read beside a key-only mode, or the key read before the choice.
     *
     * @param string $mode The stored customchar1.
     * @param int $choice The stored groupchoice.
     * @param string $expected The answer.
     * @return void
     */
    #[DataProvider('answer_provider')]
    public function test_the_answer_is_read_from_the_stored_settings(string $mode, int $choice, string $expected): void {
        $instance = clone $this->instance;
        $instance->customchar1 = $mode;

        $options = instanceoptions::defaults();
        $options->groupchoice = $choice;
        $this->assertSame($expected, groupquestion::answer($instance, $options));
    }

    /**
     * Each answer maps onto the two stored settings, reading only its own elements: a choice never
     * comes with a key-only mode (C-D5), a key never with a choice, and a value outside an element's
     * vocabulary maps to off.
     *
     * Changes that must make it fail: a choice storing the posted key mode as it is, a key storing a
     * choice, or the decider's answer keeping a key.
     *
     * @return void
     */
    public function test_each_answer_maps_onto_the_stored_settings(): void {
        $map = static fn(array $data): array => (array) groupquestion::storage((object) $data);
        $hidden = ['customchar1' => 'required', 'choicekey' => 'any', 'groupchoicerequired' => '2'];

        $this->assertSame(
            ['customchar1' => '', 'groupchoice' => instanceoptions::CHOICE_OFF],
            $map(['groupdecided' => groupquestion::DECIDER] + $hidden)
        );
        $this->assertSame(
            ['customchar1' => 'any', 'groupchoice' => instanceoptions::CHOICE_REQUIRED],
            $map(['groupdecided' => groupquestion::CHOICE] + $hidden)
        );
        $this->assertSame(
            ['customchar1' => '', 'groupchoice' => instanceoptions::CHOICE_OPTIONAL],
            $map(['groupdecided' => groupquestion::CHOICE, 'choicekey' => '', 'groupchoicerequired' => '1'] + $hidden)
        );
        // A key-only mode posted beside a choice is never stored with it.
        $this->assertSame(
            ['customchar1' => '', 'groupchoice' => instanceoptions::CHOICE_REQUIRED],
            $map(['groupdecided' => groupquestion::CHOICE, 'choicekey' => 'required'] + $hidden)
        );
        $this->assertSame(
            ['customchar1' => 'required', 'groupchoice' => instanceoptions::CHOICE_OFF],
            $map(['groupdecided' => groupquestion::KEY] + $hidden)
        );
        $this->assertSame(
            ['customchar1' => '', 'groupchoice' => instanceoptions::CHOICE_OFF],
            $map(['groupdecided' => groupquestion::KEY, 'customchar1' => 'listed'])
        );
        $this->assertSame(['customchar1' => '', 'groupchoice' => instanceoptions::CHOICE_OFF], $map([]));
    }

    /**
     * The offer is the groups picked plus those of the grouping, once, and a grouping of another
     * course adds nothing.
     *
     * Changes that must make it fail: the grouping's groups not added, or read from another course.
     *
     * @return void
     */
    public function test_the_offer_adds_the_groups_of_a_grouping(): void {
        $courseid = (int) $this->course->id;
        $picked = (object) ['groupoffer' => [(string) $this->moderadores->id, (string) $this->eveninga->id]];
        $expected = [(int) $this->eveninga->id, (int) $this->moderadores->id];
        sort($expected);
        $this->assertSame($expected, groupquestion::offer($courseid, $picked));

        $picked->groupoffergrouping = (int) $this->grouping->id;
        $expected = [(int) $this->eveninga->id, (int) $this->eveningb->id, (int) $this->moderadores->id];
        sort($expected);
        $this->assertSame($expected, groupquestion::offer($courseid, $picked));

        $other = $this->getDataGenerator()->create_course();
        $foreign = $this->getDataGenerator()->create_grouping(['courseid' => $other->id]);
        $this->assertSame([], groupquestion::offer($courseid, (object) ['groupoffergrouping' => (int) $foreign->id]));
    }

    /**
     * Saving a choice writes the options and the offer; saving another answer writes the options
     * and leaves the offer as it was.
     *
     * Changes that must make it fail: an option not written, the offer not written for a choice, or
     * replaced for another answer.
     *
     * @return void
     */
    public function test_saving_writes_the_options_and_the_offer(): void {
        $enrolid = (int) $this->instance->id;
        groupquestion::save($enrolid, (object) [
            'groupdecided' => groupquestion::CHOICE,
            'groupchoicerequired' => '1',
            'choicekey' => '',
            'groupoffer' => [(string) $this->moderadores->id],
            'groupoffergrouping' => (string) $this->grouping->id,
            'deciders' => '1',
            'groupseats' => '0',
            'hidecard' => '1',
        ]);

        $options = instanceoptions::get($enrolid);
        $this->assertSame(instanceoptions::CHOICE_OPTIONAL, $options->groupchoice);
        $this->assertSame(instanceoptions::DECIDERS_ALLGROUPS, $options->deciders);
        $this->assertSame(0, $options->groupseats);
        $this->assertSame(1, $options->hidecard);
        $expected = [(int) $this->eveninga->id, (int) $this->eveningb->id, (int) $this->moderadores->id];
        sort($expected);
        $this->assertSame($expected, groupoffer::group_ids($enrolid));

        groupquestion::save($enrolid, (object) [
            'groupdecided' => groupquestion::DECIDER,
            'groupoffer' => [],
            'deciders' => '0',
            'groupseats' => '1',
            'hidecard' => '0',
        ]);
        $options = instanceoptions::get($enrolid);
        $this->assertSame(instanceoptions::CHOICE_OFF, $options->groupchoice);
        $this->assertSame(instanceoptions::DECIDERS_GROUP, $options->deciders);
        $this->assertSame(0, $options->groupseats, 'the seats are asked under the choice only, and kept');
        $this->assertSame(0, $options->hidecard);
        $this->assertSame($expected, groupoffer::group_ids($enrolid), 'the offer is kept for a later choice');
    }

    /**
     * The form loads a saved method's answer, options and offer.
     *
     * Changes that must make it fail: an option, the answer or the offer not loaded.
     *
     * @return void
     */
    public function test_the_form_loads_the_saved_method(): void {
        global $DB;

        $enrolid = (int) $this->instance->id;
        $DB->set_field('enrol', 'customchar1', groupkey::MODE_ANY, ['id' => $enrolid]);
        instanceoptions::set($enrolid, [
            'groupchoice' => instanceoptions::CHOICE_OPTIONAL,
            'deciders' => instanceoptions::DECIDERS_ALLGROUPS,
            'groupseats' => 0,
            'hidecard' => 1,
        ]);
        groupoffer::set($enrolid, [(int) $this->eveninga->id]);
        $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);

        $defaults = $this->quickform($this->form($instance))->_defaultValues;
        $this->assertSame(groupquestion::CHOICE, $defaults['groupdecided']);
        $this->assertSame(groupkey::MODE_ANY, $defaults['choicekey']);
        $this->assertEquals(instanceoptions::CHOICE_OPTIONAL, $defaults['groupchoicerequired']);
        $this->assertEquals(instanceoptions::DECIDERS_ALLGROUPS, $defaults['deciders']);
        $this->assertEquals(0, $defaults['groupseats']);
        $this->assertEquals(1, $defaults['hidecard']);
        $this->assertEquals([(int) $this->eveninga->id], $defaults['groupoffer']);
    }

    /**
     * A choice the applicant must make needs a group to offer, the picked ones or a grouping's,
     * and an optional one does not.
     *
     * Changes that must make it fail: the empty offer accepted, or the grouping's groups not counted.
     *
     * @return void
     */
    public function test_a_required_choice_needs_a_group_to_offer(): void {
        $form = $this->form();
        $choice = ['groupdecided' => groupquestion::CHOICE, 'choicekey' => '', 'groupchoicerequired' => '2', 'groupoffer' => []];

        $errors = $form->validation($this->submission($choice), []);
        $this->assertSame(get_string('groupofferempty', 'enrol_apply'), $errors['groupoffer'] ?? null);

        $errors = $form->validation($this->submission(['groupoffergrouping' => (string) $this->grouping->id] + $choice), []);
        $this->assertArrayNotHasKey('groupoffer', $errors);

        $errors = $form->validation($this->submission(['groupchoicerequired' => '1'] + $choice), []);
        $this->assertArrayNotHasKey('groupoffer', $errors);
    }

    /**
     * A group or a grouping of another course, a key-only mode beside a choice, a key answer with no
     * key mode, a missing answer and an unknown "Who decides" are refused.
     *
     * Changes that must make it fail: any of those checks dropped.
     *
     * @return void
     */
    public function test_the_groups_section_refuses_what_it_cannot_store(): void {
        $form = $this->form();
        $other = $this->getDataGenerator()->create_course();
        $foreigngroup = $this->getDataGenerator()->create_group(['courseid' => $other->id]);
        $foreigngrouping = $this->getDataGenerator()->create_grouping(['courseid' => $other->id]);
        $choice = [
            'groupdecided' => groupquestion::CHOICE,
            'choicekey' => '',
            'groupchoicerequired' => '1',
            'groupoffer' => [(string) $this->eveninga->id],
        ];

        $this->assertSame([], $form->validation($this->submission($choice), []), 'the control is accepted');

        $errors = $form->validation($this->submission(['groupoffer' => [(string) $foreigngroup->id]] + $choice), []);
        $this->assertArrayHasKey('groupoffer', $errors);
        $errors = $form->validation($this->submission(['groupoffergrouping' => (string) $foreigngrouping->id] + $choice), []);
        $this->assertArrayHasKey('groupoffergrouping', $errors);
        $errors = $form->validation($this->submission(['choicekey' => 'required'] + $choice), []);
        $this->assertArrayHasKey('choicekey', $errors);

        $errors = $form->validation($this->submission(['groupdecided' => groupquestion::KEY, 'customchar1' => '']), []);
        $this->assertArrayHasKey('customchar1', $errors);
        $errors = $form->validation($this->submission(['groupdecided' => groupquestion::KEY, 'customchar1' => 'review']), []);
        $this->assertSame([], $errors);

        $errors = $form->validation($this->submission(['groupdecided' => null]), []);
        $this->assertArrayHasKey('groupdecidedgroup', $errors);
        $errors = $form->validation($this->submission(['deciders' => '2']), []);
        $this->assertArrayHasKey('deciders', $errors);
    }

    /**
     * The rendered Groups section, for one method.
     *
     * @param \stdClass $instance The method.
     * @return string The form's HTML.
     */
    private function render(\stdClass $instance): string {
        return $this->form($instance)->render();
    }

    /**
     * The element names of a form under one section header, in form order.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param string $header The section's header name.
     * @return string[] The names, the header's own left out.
     */
    private function section(\MoodleQuickForm $mform, string $header): array {
        $names = [];
        $current = '';
        foreach ($mform->_elements as $element) {
            if ($element->getType() === 'header') {
                $current = $element->getName();
                continue;
            }
            // Formslib adds a hidden expand-state element after every collapsible header.
            if (
                $current === $header
                && (string) $element->getName() !== ''
                && strpos((string) $element->getName(), 'mform_isexpanded_') !== 0
            ) {
                $names[] = $element->getName();
            }
        }
        return $names;
    }

    /**
     * The elements a hideIf hides under one condition on one element.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param string $dependenton The element the condition reads.
     * @param string $condition The operator.
     * @return array The hidden element names, keyed by the condition's value as a string.
     */
    private function hidden_by(\MoodleQuickForm $mform, string $dependenton, string $condition): array {
        $hidden = [];
        // Protected on MoodleQuickForm: read it the way conditions_test.php does.
        $hideifs = (new \ReflectionProperty(\MoodleQuickForm::class, '_hideifs'))->getValue($mform);
        foreach ($hideifs[$dependenton][$condition] ?? [] as $value => $names) {
            $hidden[(string) $value] = $names;
        }
        return $hidden;
    }

    /**
     * Deny the deciding capability to every role in the course, so no role offered decides.
     *
     * @return void
     */
    private function no_role_decides(): void {
        $context = \context_course::instance($this->course->id);
        foreach (\enrol_apply\local\decidingrole::roles((int) $this->course->id) as $roleid) {
            assign_capability(\enrol_apply\local\groupscope::DECIDE, CAP_PROHIBIT, $roleid, $context->id, true);
        }
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertSame([], \enrol_apply\local\decidingrole::roles((int) $this->course->id), 'the precondition');
    }

    /**
     * In separate groups the warning that a moderator could approve another moderator is on the
     * form whenever "Role on approval" offers a role that decides, whatever role is saved (the
     * browser hides it for the others, see the next test); it is absent outside separate groups,
     * and when no offered role decides.
     *
     * Changes that must make it fail: the warning dropped, read from the saved role, given when no
     * offered role decides, or given in a course where no group narrows anybody.
     *
     * @return void
     */
    public function test_a_deciding_method_decided_by_groups_is_warned(): void {
        global $DB;

        $warning = s(get_string('warndecidingbygroups', 'enrol_apply'));
        $recruit = $this->method($this->role('editingteacher'));

        $this->assertStringContainsString($warning, $this->render($recruit));
        $this->assertStringContainsString($warning, $this->render($this->instance), 'a learner method may switch role');

        $DB->set_field('course', 'groupmode', NOGROUPS, ['id' => $this->course->id]);
        $this->assertStringNotContainsString($warning, $this->render($recruit));

        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $this->course->id]);
        $this->no_role_decides();
        $this->assertStringNotContainsString($warning, $this->render($recruit));
    }

    /**
     * The warning follows the role being edited: it is hidden while "Role on approval" holds any of
     * the offered roles that do not decide (the select's own option values), and still hidden under
     * "Only those who see every group".
     *
     * Changes that must make it fail: the role condition dropped, or built from other ids than the
     * select offers.
     *
     * @return void
     */
    public function test_the_deciding_warning_follows_the_role_being_edited(): void {
        $mform = $this->quickform($this->form());
        $this->assertTrue($mform->elementExists('decidingbygroups'));

        $offered = [];
        foreach ($mform->getElement('roleid')->_options as $option) {
            $offered[] = (int) $option['attr']['value'];
        }
        $learner = array_values(array_diff($offered, \enrol_apply\local\decidingrole::roles((int) $this->course->id)));
        sort($learner);
        $this->assertContains($this->role('student'), $learner, 'the precondition: a learner role is offered');
        $this->assertNotContains($this->role('editingteacher'), $learner);

        $byrole = array_filter(
            $this->hidden_by($mform, 'roleid', 'in'),
            static fn(array $names): bool => in_array('decidingbygroups', $names, true)
        );
        $this->assertSame([implode('|', $learner)], array_map('strval', array_keys($byrole)));

        $allgroups = (string) instanceoptions::DECIDERS_ALLGROUPS;
        $this->assertContains('decidingbygroups', $this->hidden_by($mform, 'deciders', 'eq')[$allgroups] ?? []);
    }

    /**
     * Who decides and who is notified holds "Who decides", every notice about deciders under it,
     * then the recipients; in separate groups "Who decides" is not frozen and carries no inert note.
     *
     * Changes that must make it fail: a notice left in Groups, the recipients before a notice, or the
     * section placed after After approval.
     *
     * @return void
     */
    public function test_who_decides_holds_the_deciders_notices_and_the_recipients(): void {
        $mform = $this->quickform($this->form($this->method($this->role('editingteacher'))));

        $this->assertSame([
            'deciders',
            'decidersallgroups',
            'groupscopekeyless',
            'groupscopekeylesskey',
            'groupscopenopreference',
            'decidingbygroups',
            'notify',
        ], $this->section($mform, 'sectiondeciders'));
        $this->assertFalse($mform->getElement('deciders')->isFrozen());

        $headers = [];
        foreach ($mform->_elements as $element) {
            if ($element->getType() === 'header') {
                $headers[] = $element->getName();
            }
        }
        $this->assertSame(['sectiongroups', 'sectiondeciders', 'sectionafter'], array_slice($headers, 4));
        $this->assertFalse($mform->_collapsibleElements['sectiondeciders'], 'separate groups open it');
    }

    /**
     * Under "Groups joined on approval", one line says the chosen group is joined as well, shown for
     * a choice alone, and one says the key's group is, shown for a key alone.
     *
     * Changes that must make it fail: either line dropped, or shown under the other answer.
     *
     * @return void
     */
    public function test_the_joined_groups_say_what_the_answer_adds(): void {
        $mform = $this->quickform($this->form());

        $groups = $this->section($mform, 'sectiongroups');
        $this->assertSame(['groupselect', 'groupselectchoice', 'groupselectkey'], array_slice($groups, -3));
        $this->assertStringContainsString(
            s(get_string('groupselectchoice', 'enrol_apply')),
            $mform->getElement('groupselectchoice')->_text
        );
        $this->assertStringContainsString(
            s(get_string('groupselectkey', 'enrol_apply')),
            $mform->getElement('groupselectkey')->_text
        );

        $neq = $this->hidden_by($mform, 'groupdecided', 'neq');
        $this->assertContains('groupselectchoice', $neq[groupquestion::CHOICE]);
        $this->assertNotContains('groupselectchoice', $neq[groupquestion::KEY]);
        $this->assertContains('groupselectkey', $neq[groupquestion::KEY]);
        $this->assertNotContains('groupselectkey', $neq[groupquestion::CHOICE]);
    }

    /**
     * The group modes, with the strings the line names each one with.
     *
     * @return array The mode, the core string id of its name and this plugin's consequence string id.
     */
    public static function group_mode_provider(): array {
        return [
            'no groups' => [NOGROUPS, 'groupsnone', 'coursegroupmodeshared'],
            'separate groups' => [SEPARATEGROUPS, 'groupsseparate', 'coursegroupmodeseparate'],
            'visible groups' => [VISIBLEGROUPS, 'groupsvisible', 'coursegroupmodeshared'],
        ];
    }

    /**
     * The first row of Groups names the course's group mode and what it does to who sees an
     * application, and the summary strip states the mode too.
     *
     * @param int $mode The course's group mode.
     * @param string $name The core string id of its name.
     * @param string $consequence The consequence string id.
     * @return void
     */
    #[DataProvider('group_mode_provider')]
    public function test_the_group_mode_line_names_the_mode_and_what_it_means(int $mode, string $name, string $consequence): void {
        global $DB;

        $DB->set_field('course', 'groupmode', $mode, ['id' => $this->course->id]);
        $mform = $this->quickform($this->form());

        $this->assertSame('coursegroupmode', $this->section($mform, 'sectiongroups')[0]);
        $line = $mform->getElement('coursegroupmode');
        $this->assertSame(get_string('coursegroupmode', 'enrol_apply'), $line->getLabel());
        $this->assertStringContainsString(s(get_string($name, 'group')), $line->_text);
        $this->assertStringContainsString(s(get_string($consequence, 'enrol_apply')), $line->_text);
        $other = $consequence === 'coursegroupmodeseparate' ? 'coursegroupmodeshared' : 'coursegroupmodeseparate';
        $this->assertStringNotContainsString(s(get_string($other, 'enrol_apply')), $line->_text);

        $method = new \ReflectionMethod(\enrol_apply_edit_form::class, 'summary_facts');
        $facts = $method->invoke($this->form(), $this->instance);
        $this->assertContains(get_string('summarygroupmode', 'enrol_apply', get_string($name, 'group')), $facts);
    }

    /**
     * The line links the course settings, where the mode is changed, only for a reader who may
     * change them.
     *
     * Changes that must make it fail: the link offered without moodle/course:update.
     *
     * @return void
     */
    public function test_the_course_settings_are_linked_only_for_who_may_change_them(): void {
        $link = '/course/edit.php?id=' . $this->course->id;
        $name = s(get_string('groupsseparate', 'group'));

        $text = $this->quickform($this->form())->getElement('coursegroupmode')->_text;
        $this->assertStringContainsString($link, $text, 'the control: an administrator may change them');

        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'teacher'));
        $this->assertFalse(has_capability('moodle/course:update', \context_course::instance($this->course->id)));
        $text = $this->quickform($this->form())->getElement('coursegroupmode')->_text;
        $this->assertStringContainsString($name, $text);
        $this->assertStringNotContainsString($link, $text);
    }

    /**
     * A group offered here that another method joins on approval with a deciding role is named;
     * the same group joined by a learner method is not warned about.
     *
     * Changes that must make it fail: the warning dropped, or every other method's groups read
     * whatever its role.
     *
     * @return void
     */
    public function test_an_offered_group_a_deciding_method_joins_is_warned(): void {
        global $DB;

        groupoffer::set((int) $this->instance->id, [(int) $this->eveninga->id, (int) $this->moderadores->id]);
        $other = $this->method($this->role('student'));
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $other->id, 'groupid' => $this->moderadores->id]);
        $unnamed = s(get_string('warnofferleakunnamed', 'enrol_apply'));
        $named = s(get_string('warnofferleak', 'enrol_apply', 'Moderadores'));

        $html = $this->render($this->instance);
        $this->assertStringNotContainsString($named, $html, 'a learner method joining it is no leak');
        $this->assertStringNotContainsString($unnamed, $html);

        $DB->set_field('enrol', 'roleid', $this->role('editingteacher'), ['id' => $other->id]);
        $this->assertStringContainsString($named, $this->render($this->instance));

        // Directly after the offer it is about.
        $groups = $this->section($this->quickform($this->form()), 'sectiongroups');
        $this->assertSame('offerleak', $groups[array_search('groupoffergrouping', $groups, true) + 1] ?? null);
    }

    /**
     * The separate-groups notice covers "No preference", and is replaced by one sentence when
     * only those who see every group decide.
     *
     * Changes that must make it fail: the "No preference" notice or the "Who decides" sentence dropped.
     *
     * @return void
     */
    public function test_the_separate_groups_notices(): void {
        $html = $this->render($this->instance);
        $this->assertStringContainsString(s(get_string('groupscopekeyless', 'enrol_apply')), $html);
        $this->assertStringContainsString(s(get_string('groupscopenopreference', 'enrol_apply')), $html);
        $this->assertStringContainsString(s(get_string('decidersallgroupsinfo', 'enrol_apply')), $html);
    }

    /**
     * The summary strip says how many groups the applicants choose among, and that only those who
     * see every group decide, where each applies.
     *
     * Changes that must make it fail: either fact dropped, or the choice counted in a key-only mode.
     *
     * @return void
     */
    public function test_the_strip_states_the_choice_and_who_decides(): void {
        $facts = function (\stdClass $instance): array {
            $method = new \ReflectionMethod(\enrol_apply_edit_form::class, 'summary_facts');
            return $method->invoke($this->form($instance), $instance);
        };
        $choice = get_string('summarychoice', 'enrol_apply', 2);
        $allgroups = get_string('summarydecidersallgroups', 'enrol_apply');

        $this->assertNotContains($choice, $facts($this->instance));
        $this->assertNotContains($allgroups, $facts($this->instance));

        $enrolid = (int) $this->instance->id;
        instanceoptions::set($enrolid, [
            'groupchoice' => instanceoptions::CHOICE_REQUIRED,
            'deciders' => instanceoptions::DECIDERS_ALLGROUPS,
        ]);
        groupoffer::set($enrolid, [(int) $this->eveninga->id, (int) $this->eveningb->id]);
        $this->assertContains($choice, $facts($this->instance));
        $this->assertContains($allgroups, $facts($this->instance));

        // In a key-only mode the key decides the group, and nobody is offered a list.
        $keyonly = clone $this->instance;
        $keyonly->customchar1 = groupkey::MODE_REVIEW;
        $this->assertNotContains($choice, $facts($keyonly));
    }
}
