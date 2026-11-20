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
 * Tests for the applicant's group chooser on the application form.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\form;

use enrol_apply\local\fieldset;
use enrol_apply\local\groupchoice;
use enrol_apply\local\groupkey;
use enrol_apply\local\groupoffer;
use enrol_apply\local\instanceoptions;
use enrol_apply\local\keyattempts;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The applicant's group chooser on the application form (docs/design/applicant-group-choice.md, G4).
 *
 * The element is read from the built form; validation is driven through the web service the modal
 * calls, the real path for both, so what an applicant meets is what is asserted.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(application_form::class)]
#[CoversClass(groupchoice::class)]
final class group_choice_form_test extends \advanced_testcase {
    /** @var \stdClass Course the instance belongs to. */
    private $course;

    /** @var \stdClass The enrol_apply instance. */
    private $instance;

    /** @var \stdClass The applicant. */
    private $applicant;

    /** @var array Offered groups by short name: 'two', 'ten' and 'hidden'. */
    private $groups = [];

    #[\Override]
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $instanceid = (int) $plugin->add_instance($this->course, $plugin->get_instance_defaults());
        // No profile fields and no comment: the chooser is the subject.
        $DB->set_field('enrol', 'customtext4', fieldset::from_keys([])->to_json(), ['id' => $instanceid]);
        $DB->set_field('enrol', 'customint7', 0, ['id' => $instanceid]);
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        // Created out of natural order, so the order asserted is the chooser's and not the ids'.
        $this->groups['ten'] = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Turma 10']);
        $this->groups['two'] = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Turma 2']);
        $this->groups['hidden'] = $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'Turma oculta',
            'visibility' => GROUPS_VISIBILITY_MEMBERS,
        ]);
        groupoffer::set($instanceid, array_map(static fn($group) => (int) $group->id, array_values($this->groups)));

        $this->applicant = $this->getDataGenerator()->create_user();
        $this->setUser($this->applicant);
    }

    #[\Override]
    protected function tearDown(): void {
        keyattempts::release_all();
        parent::tearDown();
    }

    /**
     * Set the method's group choice.
     *
     * @param int $mode An instanceoptions CHOICE_ constant.
     * @param int $seats The groupseats option, 1 to count seats.
     * @return void
     */
    private function choice(int $mode, int $seats = 1): void {
        instanceoptions::set((int) $this->instance->id, ['groupchoice' => $mode, 'groupseats' => $seats]);
    }

    /**
     * Build the form the way both transports do.
     *
     * @return application_form The form.
     */
    private function make_form(): application_form {
        return new application_form(null, null, 'post', '', null, true, [
            'instance' => $this->instance->id,
            'id' => $this->course->id,
        ]);
    }

    /**
     * The chooser's options, as the plain select carries them.
     *
     * @param application_form $form The form.
     * @return array|null Each option's value => object with 'text' and 'attr'; null without a chooser.
     */
    private function options(application_form $form): ?array {
        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
        if (!$mform->elementExists('chosengroup')) {
            return null;
        }
        $options = [];
        foreach ($mform->getElement('chosengroup')->_options as $option) {
            $options[(string) $option['attr']['value']] = (object) ['text' => $option['text'], 'attr' => $option['attr']];
        }
        return $options;
    }

    /**
     * Submit through the web service the modal calls.
     *
     * @param array $posted Posted values on top of the ids.
     * @return array The web service's result: 'submitted', and 'data' or 'html'.
     */
    private function submit_through_the_modal(array $posted): array {
        $_POST['sesskey'] = sesskey();
        $query = http_build_query($posted + [
            'instance' => $this->instance->id,
            'id' => $this->course->id,
            'sesskey' => sesskey(),
            '_qf__enrol_apply_form_application_form' => 1,
        ], '', '&');

        ob_start();
        try {
            return \core_form\external\dynamic_form::execute(application_form::class, $query);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * The applicant's newest record.
     *
     * @return \stdClass|false The record.
     */
    private function record() {
        global $DB;

        $records = $DB->get_records('enrol_apply_submission', ['userid' => $this->applicant->id], 'id DESC', '*', 0, 1);
        return reset($records);
    }

    /**
     * Provision local_groupdist's Seats field, or skip; see groupseats_test::require_groupdist().
     *
     * @return void
     */
    private function require_groupdist(): void {
        if (!class_exists('\local_groupdist\seats')) {
            $this->markTestSkipped('local_groupdist with \local_groupdist\seats is not installed');
        }
        \local_groupdist\local\fields::reset_field_cache();
        \local_groupdist\local\fields::ensure_fields_exist();
        \local_groupdist\local\fields::reset_field_cache();
    }

    /**
     * Declare seats on a group, then act as the applicant again.
     *
     * @param \stdClass $group The group.
     * @param int $seats Seats; 0 is full from the start.
     * @return void
     */
    private function seats(\stdClass $group, int $seats): void {
        $this->setAdminUser();
        \core_group\customfield\group_handler::create()->instance_form_save((object) [
            'id' => $group->id,
            'customfield_groupdist_seats' => $seats,
        ]);
        $this->setUser($this->applicant);
    }

    /**
     * With the choice off there is no chooser at all; with it on, there is.
     *
     * Changes that must make it fail: the chooser added whatever the method's option says.
     *
     * @return void
     */
    public function test_the_chooser_is_absent_while_the_choice_is_off(): void {
        $this->assertNull($this->options($this->make_form()));

        $this->choice(instanceoptions::CHOICE_OPTIONAL);
        $this->assertNotNull($this->options($this->make_form()), 'the control: switched on, it is there');
    }

    /**
     * An optional chooser offers "No preference" first, then the groups in natural order.
     *
     * Changes that must make it fail: "No preference" missing or not first, or the groups in id or
     * database order ("Turma 10" before "Turma 2").
     *
     * @return void
     */
    public function test_an_optional_chooser_offers_no_preference_first(): void {
        $this->choice(instanceoptions::CHOICE_OPTIONAL);

        $options = $this->options($this->make_form());

        // Array keys: PHP stores a digit string as an int.
        $this->assertSame([0, (int) $this->groups['two']->id, (int) $this->groups['ten']->id], array_keys($options));
        $this->assertSame(get_string('chosengroupnone', 'enrol_apply'), $options['0']->text);
        $this->assertSame('Turma 2', $options[(string) $this->groups['two']->id]->attr['data-name']);
    }

    /**
     * A required chooser has an empty placeholder and no "No preference", and requires a group.
     *
     * Changes that must make it fail: "No preference" offered in required mode, or no group
     * required.
     *
     * @return void
     */
    public function test_a_required_chooser_asks_for_a_group(): void {
        $this->choice(instanceoptions::CHOICE_REQUIRED);

        $options = $this->options($this->make_form());

        $this->assertSame('', array_key_first($options));
        $this->assertArrayNotHasKey('0', $options);

        $result = $this->submit_through_the_modal([]);
        $this->assertFalse($result['submitted']);
        $this->assertStringContainsString(get_string('chosengrouprequired', 'enrol_apply'), $result['html']);
        $this->assertFalse($this->record(), 'nothing was written');
    }

    /**
     * A group hidden from non-members is never offered to an applicant outside it.
     *
     * Stock archetypes holding a course role all hold moodle/course:viewhiddengroups, so the
     * control gives the applicant a custom role holding it alone: it is the visibility rule that
     * hides the group, not anything else about it.
     *
     * Changes that must make it fail: the offer shown unfiltered by groupvisibility::visible_to().
     *
     * @return void
     */
    public function test_a_group_hidden_from_non_members_is_never_offered(): void {
        $this->choice(instanceoptions::CHOICE_OPTIONAL);
        $hidden = (string) $this->groups['hidden']->id;

        $this->assertArrayNotHasKey($hidden, $this->options($this->make_form()));

        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'seeshidden']);
        assign_capability('moodle/course:viewhiddengroups', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $this->applicant->id, \context_course::instance($this->course->id)->id);

        $this->assertArrayHasKey($hidden, $this->options($this->make_form()), 'the control: a reader who may see it');
    }

    /**
     * With seats known, each option says its places and a full group is listed, disabled.
     *
     * Changes that must make it fail: the full group left enabled or dropped, or the places not
     * carried on the option.
     *
     * @return void
     */
    public function test_a_full_group_is_listed_disabled_with_its_places(): void {
        $this->require_groupdist();
        $this->choice(instanceoptions::CHOICE_OPTIONAL);
        $this->seats($this->groups['two'], 3);
        $this->seats($this->groups['ten'], 0);

        $options = $this->options($this->make_form());
        $open = $options[(string) $this->groups['two']->id];
        $full = $options[(string) $this->groups['ten']->id];

        $this->assertSame('3', $open->attr['data-places']);
        $this->assertArrayNotHasKey('disabled', $open->attr);
        $this->assertStringContainsString(get_string('chosengroupplaces', 'enrol_apply', 3), $open->text);
        $this->assertSame('disabled', $full->attr['disabled'] ?? null);
        $this->assertSame('1', $full->attr['data-full'] ?? null);
        $this->assertStringContainsString(get_string('chosengroupfull', 'enrol_apply'), $full->text);
    }

    /**
     * Where the method does not count seats, no group is full and no option says places.
     *
     * Changes that must make it fail: the groupseats option ignored.
     *
     * @return void
     */
    public function test_seats_are_ignored_where_the_method_does_not_count_them(): void {
        $this->require_groupdist();
        $this->choice(instanceoptions::CHOICE_OPTIONAL, 0);
        $this->seats($this->groups['ten'], 0);

        $full = $this->options($this->make_form())[(string) $this->groups['ten']->id];

        $this->assertArrayNotHasKey('disabled', $full->attr);
        $this->assertArrayNotHasKey('data-places', $full->attr);
        $this->assertSame('Turma 10', $full->text);
    }

    /**
     * Without local_groupdist no group is full and no label shows.
     *
     * Through a subclass whose seat reader answers as a site without that plugin does, so it runs
     * whether or not the plugin is installed here.
     *
     * Changes that must make it fail: a group with unknown seats read as full, or labelled.
     *
     * @return void
     */
    public function test_without_seats_no_group_is_full_and_no_label_shows(): void {
        $this->choice(instanceoptions::CHOICE_OPTIONAL);
        $noseats = new class extends groupchoice {
            /**
             * Seats as a site without local_groupdist reads them.
             *
             * @param array $groupids Group ids.
             * @return array Every group declaring no limit.
             */
            protected static function seats_for(array $groupids): array {
                return array_fill_keys($groupids, (object) ['seats' => null, 'members' => null]);
            }
        };

        $offered = $noseats::offered($this->instance, (int) $this->applicant->id);
        $context = \context_course::instance($this->course->id);

        $this->assertCount(2, $offered);
        foreach ($offered as $group) {
            $this->assertFalse($group->full);
            $this->assertNull($group->places);
            $this->assertSame($group->name, $noseats::option_text($group, $context));
        }
    }

    /**
     * A group that is not offered is refused on the element, whichever way it is not offered.
     *
     * The optional mode, where a select's export would otherwise turn an id outside its options
     * into "no group" and let the application through with no group at all.
     *
     * Changes that must make it fail: validation reading the exported value, or not judging the
     * posted group.
     *
     * @return void
     */
    public function test_a_group_that_is_not_offered_is_refused_on_the_element(): void {
        $this->choice(instanceoptions::CHOICE_OPTIONAL);
        $notoffered = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Not offered']);
        $elsewhere = $this->getDataGenerator()->create_group([
            'courseid' => $this->getDataGenerator()->create_course()->id,
            'name' => 'Another course',
        ]);

        foreach ([$notoffered, $elsewhere, $this->groups['hidden']] as $group) {
            $result = $this->submit_through_the_modal(['chosengroup' => $group->id]);
            $this->assertFalse($result['submitted'], $group->name);
            $this->assertStringContainsString(get_string('chosengroupunavailable', 'enrol_apply'), $result['html']);
        }
        $this->assertFalse($this->record(), 'nothing was written');
    }

    /**
     * A full group is refused on the element, though the disabled option can be posted.
     *
     * Changes that must make it fail: validation not judging the seats.
     *
     * @return void
     */
    public function test_a_full_group_is_refused_on_the_element(): void {
        $this->require_groupdist();
        $this->choice(instanceoptions::CHOICE_OPTIONAL);
        $this->seats($this->groups['ten'], 0);

        $result = $this->submit_through_the_modal(['chosengroup' => $this->groups['ten']->id]);

        $this->assertFalse($result['submitted']);
        $this->assertStringContainsString(get_string('chosengroupnoplace', 'enrol_apply'), $result['html']);
    }

    /**
     * A chosen group is recorded on the application, and "No preference" records none.
     *
     * Changes that must make it fail: the chosen group not handed to the record.
     *
     * @return void
     */
    public function test_a_chosen_group_is_recorded(): void {
        $this->choice(instanceoptions::CHOICE_OPTIONAL);

        $sink = $this->redirectMessages();
        $result = $this->submit_through_the_modal(['chosengroup' => $this->groups['two']->id]);
        $sink->close();

        $this->assertTrue($result['submitted']);
        $this->assertStringContainsString('/enrol/apply/applied.php', json_decode($result['data']));
        $record = $this->record();
        $this->assertSame((int) $this->groups['two']->id, (int) $record->chosengroupid);
        $this->assertSame(submission::KEY_NONE, (int) $record->keyoutcome);
    }

    /**
     * In the 'any' key mode the chooser stays, and a valid key wins over it.
     *
     * The chooser is required and the key holder chooses nothing, or chooses another group: the
     * application goes through on the key, the key's group is the application's group and no
     * chosen group is recorded (C-D5).
     *
     * Changes that must make it fail: a group required of a key holder, or the chosen group
     * recorded beside the key's.
     *
     * @param bool $choosesanother Whether the key holder also posts a chosen group.
     * @return void
     */
    #[DataProvider('key_holder_provider')]
    public function test_a_valid_key_wins_over_the_chosen_group(bool $choosesanother): void {
        global $DB;

        $this->choice(instanceoptions::CHOICE_REQUIRED);
        $DB->set_field('enrol', 'customchar1', groupkey::MODE_ANY, ['id' => $this->instance->id]);
        $keyed = $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'Turma B',
            'enrolmentkey' => 'Turma-B-2027',
        ]);
        $this->assertNotNull($this->options($this->make_form()), 'the chooser stays beside the key');

        $posted = ['groupkey' => 'Turma-B-2027'] + ($choosesanother ? ['chosengroup' => $this->groups['two']->id] : []);
        $sink = $this->redirectMessages();
        $result = $this->submit_through_the_modal($posted);
        $sink->close();

        $this->assertTrue($result['submitted']);
        $record = $this->record();
        $this->assertSame((int) $keyed->id, (int) $record->keygroupid);
        $this->assertSame(submission::KEY_ADMITTED, (int) $record->keyoutcome);
        $this->assertSame(0, (int) $record->chosengroupid);
    }

    /**
     * Whether the key holder also chooses a group.
     *
     * @return array Case name => [whether a group is posted too].
     */
    public static function key_holder_provider(): array {
        return [
            'no group chosen' => [false],
            'another group chosen' => [true],
        ];
    }

    /**
     * Without a valid key the 'any' mode's chooser is judged as anywhere else.
     *
     * The control for the test above: the key is what lets the required chooser through.
     *
     * @return void
     */
    public function test_without_a_valid_key_the_chooser_is_still_required(): void {
        global $DB;

        $this->choice(instanceoptions::CHOICE_REQUIRED);
        $DB->set_field('enrol', 'customchar1', groupkey::MODE_ANY, ['id' => $this->instance->id]);

        $result = $this->submit_through_the_modal(['groupkey' => '']);

        $this->assertFalse($result['submitted']);
        $this->assertStringContainsString(get_string('chosengrouprequired', 'enrol_apply'), $result['html']);
    }

    /**
     * Neither key-only mode shows a chooser: the key decides the group there.
     *
     * Changes that must make it fail: the chooser added on a key-only mode.
     *
     * @param string $mode A key-only customchar1 mode.
     * @return void
     */
    #[DataProvider('key_only_provider')]
    public function test_key_only_modes_show_no_chooser(string $mode): void {
        global $DB;

        $this->choice(instanceoptions::CHOICE_REQUIRED);
        $DB->set_field('enrol', 'customchar1', $mode, ['id' => $this->instance->id]);

        $this->assertNull($this->options($this->make_form()));
        $this->assertSame(instanceoptions::CHOICE_OFF, groupchoice::mode($DB->get_record('enrol', ['id' => $this->instance->id])));
    }

    /**
     * The two key-only modes.
     *
     * @return array Case name => [mode].
     */
    public static function key_only_provider(): array {
        return [
            'key only' => [groupkey::MODE_REQUIRED],
            'key only, reviewed' => [groupkey::MODE_REVIEW],
        ];
    }

    /**
     * Somebody already enrolled in the course through another method can still apply here.
     *
     * The direct link of C-D5: enrol/index.php sends an enrolled user to the course, so the
     * method's own form is their way in, and nothing refuses them for that enrolment.
     *
     * Changes that must make it fail: the form or the door refusing an applicant enrolled in the
     * course through another method.
     *
     * @return void
     */
    public function test_an_applicant_enrolled_through_another_method_can_still_apply(): void {
        $this->choice(instanceoptions::CHOICE_REQUIRED);
        $this->getDataGenerator()->enrol_user($this->applicant->id, $this->course->id, 'student', 'manual');
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $this->applicant, '', true));

        $form = $this->make_form();
        $form->check_access_for_dynamic_submission();
        $this->assertArrayHasKey((string) $this->groups['two']->id, $this->options($form));

        $sink = $this->redirectMessages();
        $result = $this->submit_through_the_modal(['chosengroup' => $this->groups['two']->id]);
        $sink->close();

        $this->assertTrue($result['submitted']);
        $this->assertSame((int) $this->groups['two']->id, (int) $this->record()->chosengroupid);
    }
}
