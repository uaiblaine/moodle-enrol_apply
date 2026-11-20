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

use enrol_apply\local\applicantstate;
use enrol_apply\local\groupchoice;
use enrol_apply\local\groupkey;
use enrol_apply\local\groupoffer;
use enrol_apply\local\groupvisibility;
use enrol_apply\local\instanceoptions;
use enrol_apply\local\keyattempts;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass target below resolves when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * The write door's half of the applicant's group choice, and what the applicant is told after it.
 *
 * Driven through submit_application() as a caller other than the form would, with no form in
 * front of it: the door re-resolves the chosen group itself and refuses what the form would have
 * refused, before anything is written (docs/design/applicant-group-choice.md, "What G2 leaves to
 * the later stages").
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
#[CoversClass(groupchoice::class)]
#[CoversClass(submission::class)]
#[CoversClass(applicantstate::class)]
final class group_choice_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass The apply instance, offering 'evening' and 'hidden'. */
    private $instance;

    /** @var \stdClass An offered group every applicant may see. */
    private $evening;

    /** @var \stdClass An offered group visible to its members only. */
    private $hidden;

    /** @var \stdClass The applicant. */
    private $applicant;

    #[\Override]
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $instanceid = (int) $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $this->evening = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Evening A']);
        $this->hidden = $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'Evening hidden',
            'visibility' => GROUPS_VISIBILITY_MEMBERS,
        ]);
        groupoffer::set($instanceid, [(int) $this->evening->id, (int) $this->hidden->id]);
        instanceoptions::set($instanceid, ['groupchoice' => instanceoptions::CHOICE_OPTIONAL]);

        $this->applicant = $this->getDataGenerator()->create_user();
        $this->setUser($this->applicant);
    }

    #[\Override]
    protected function tearDown(): void {
        $_GET = [];
        keyattempts::release_all();
        parent::tearDown();
    }

    /**
     * Submit through the write door, as a caller other than the form.
     *
     * @param array $data The submitted data.
     * @return \enrol_apply\local\application_result What the door did.
     */
    private function submit(array $data): \enrol_apply\local\application_result {
        $sink = $this->redirectMessages();
        try {
            return $this->plugin->submit_application($this->instance, (int) $this->applicant->id, (object) $data);
        } finally {
            $sink->close();
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
     * Assert the door refused with this reason and wrote nothing.
     *
     * @param \enrol_apply\local\application_result $result What the door did.
     * @param string $reason The string id of the reason.
     * @return void
     */
    private function assert_refused(\enrol_apply\local\application_result $result, string $reason): void {
        global $DB;

        $this->assertTrue($result->is_refusal());
        $this->assertSame(get_string($reason, 'enrol_apply'), $result->reason());
        $this->assertFalse($DB->record_exists('user_enrolments', ['enrolid' => $this->instance->id]), 'no enrolment');
        $this->assertFalse($this->record(), 'no record');
    }

    /**
     * Groups the door must refuse, by how they fail to be offered to this applicant.
     *
     * @return array Case name => [case].
     */
    public static function unavailable_provider(): array {
        return [
            'a group of the course the method does not offer' => ['notoffered'],
            'a group of another course' => ['elsewhere'],
            'an offered group hidden from the applicant' => ['hidden'],
            'a group that does not exist' => ['missing'],
        ];
    }

    /**
     * A caller other than the form is refused a group the method does not offer this applicant.
     *
     * Changes that must make it fail: the door not judging the chosen group, the offer read
     * without the method's course, or without the applicant's visibility.
     *
     * @param string $case Which group is posted.
     * @return void
     */
    #[DataProvider('unavailable_provider')]
    public function test_the_write_door_refuses_a_group_not_offered_to_the_applicant(string $case): void {
        $groupid = match ($case) {
            'notoffered' => (int) $this->getDataGenerator()->create_group(['courseid' => $this->course->id])->id,
            'elsewhere' => (int) $this->getDataGenerator()->create_group([
                'courseid' => $this->getDataGenerator()->create_course()->id,
            ])->id,
            'hidden' => (int) $this->hidden->id,
            'missing' => (int) $this->evening->id + 1000,
        };

        $this->assert_refused($this->submit(['chosengroup' => $groupid]), 'chosengroupunavailable');
    }

    /**
     * The door judges visibility for the applicant it was handed, not for whoever is logged in.
     *
     * An administrator, who sees every hidden group, submits for an applicant who does not: the
     * hidden group is still refused. Run as the applicant, as the other tests are, the two readers
     * coincide and a door judging $USER would pass.
     *
     * Changes that must make it fail: groupchoice::offered() judging the group for $USER.
     *
     * @return void
     */
    public function test_the_door_judges_the_applicant_and_not_the_logged_in_user(): void {
        $this->setAdminUser();
        $this->assertTrue(
            groupvisibility::visible_to($this->hidden, (int) get_admin()->id),
            'the premise: the operator may see the group the applicant may not'
        );

        $this->assert_refused($this->submit(['chosengroup' => (int) $this->hidden->id]), 'chosengroupunavailable');
    }

    /**
     * A value that is not a group id is refused, never read as "no group".
     *
     * Changes that must make it fail: posted() reading anything that is not an id as 0.
     *
     * @return void
     */
    public function test_the_write_door_refuses_a_value_that_is_not_a_group_id(): void {
        foreach (['abc', -1, ['1'], 1.5] as $value) {
            $this->assert_refused($this->submit(['chosengroup' => $value]), 'chosengroupunavailable');
        }
    }

    /**
     * A required choice refuses an application with no group; an optional one accepts it.
     *
     * Changes that must make it fail: "no group" accepted in required mode.
     *
     * @return void
     */
    public function test_the_write_door_refuses_no_group_when_one_is_required(): void {
        instanceoptions::set((int) $this->instance->id, ['groupchoice' => instanceoptions::CHOICE_REQUIRED]);
        $this->assert_refused($this->submit([]), 'chosengrouprequired');
        $this->assert_refused($this->submit(['chosengroup' => 0]), 'chosengrouprequired');

        instanceoptions::set((int) $this->instance->id, ['groupchoice' => instanceoptions::CHOICE_OPTIONAL]);
        $this->assertTrue($this->submit([])->was_created(), 'the control: optional, no group is a choice');
        $this->assertSame(0, (int) $this->record()->chosengroupid);
    }

    /**
     * A full group is refused at the door, though a pending application would take no seat.
     *
     * Changes that must make it fail: the door not judging the seats.
     *
     * @return void
     */
    public function test_the_write_door_refuses_a_full_group(): void {
        if (!class_exists('\local_groupdist\seats')) {
            $this->markTestSkipped('local_groupdist with \local_groupdist\seats is not installed');
        }
        \local_groupdist\local\fields::reset_field_cache();
        \local_groupdist\local\fields::ensure_fields_exist();
        \local_groupdist\local\fields::reset_field_cache();
        $this->setAdminUser();
        \core_group\customfield\group_handler::create()->instance_form_save((object) [
            'id' => $this->evening->id,
            'customfield_groupdist_seats' => 0,
        ]);
        $this->setUser($this->applicant);

        $this->assert_refused($this->submit(['chosengroup' => (int) $this->evening->id]), 'chosengroupnoplace');

        instanceoptions::set((int) $this->instance->id, ['groupseats' => 0]);
        $this->assertTrue(
            $this->submit(['chosengroup' => (int) $this->evening->id])->was_created(),
            'the control: a method that does not count seats takes it'
        );
    }

    /**
     * The chosen group is recorded, from an int or a string of digits, with no key route.
     *
     * Changes that must make it fail: the judged group not handed to the record.
     *
     * @return void
     */
    public function test_the_write_door_records_the_chosen_group(): void {
        $result = $this->submit(['chosengroup' => (string) $this->evening->id]);

        $this->assertTrue($result->was_created());
        $record = $this->record();
        $this->assertSame((int) $this->evening->id, (int) $record->chosengroupid);
        $this->assertSame(0, (int) $record->keygroupid);
        $this->assertSame(submission::KEY_NONE, (int) $record->keyoutcome);
    }

    /**
     * With the chooser off a posted group means nothing: nothing is refused, nothing recorded.
     *
     * Changes that must make it fail: the posted group recorded, or judged, while the choice is off.
     *
     * @return void
     */
    public function test_a_posted_group_means_nothing_while_the_choice_is_off(): void {
        instanceoptions::set((int) $this->instance->id, ['groupchoice' => instanceoptions::CHOICE_OFF]);

        $this->assertTrue($this->submit(['chosengroup' => (int) $this->hidden->id])->was_created());
        $this->assertSame(0, (int) $this->record()->chosengroupid);
    }

    /**
     * In the 'any' key mode a valid key wins at the door: the key's group, no chosen group.
     *
     * Changes that must make it fail: the chosen group recorded beside the key's, or judged first
     * (the chosen group here is hidden from the applicant, so judging it would refuse).
     *
     * @return void
     */
    public function test_a_valid_key_wins_over_the_chosen_group_at_the_door(): void {
        global $DB;

        $DB->set_field('enrol', 'customchar1', groupkey::MODE_ANY, ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
        $keyed = $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'Turma B',
            'enrolmentkey' => 'Turma-B-2027',
        ]);

        $result = $this->submit(['groupkey' => 'Turma-B-2027', 'chosengroup' => (int) $this->hidden->id]);

        $this->assertTrue($result->was_admitted());
        $record = $this->record();
        $this->assertSame((int) $keyed->id, (int) $record->keygroupid);
        $this->assertSame(0, (int) $record->chosengroupid);
    }

    /**
     * Without a valid key, the 'any' mode judges and records the chosen group.
     *
     * The control for the test above: the key is what set the chosen group aside.
     *
     * @return void
     */
    public function test_without_a_valid_key_the_any_mode_records_the_chosen_group(): void {
        global $DB;

        $DB->set_field('enrol', 'customchar1', groupkey::MODE_ANY, ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $result = $this->submit(['groupkey' => 'Wrong', 'withoutkey' => 1, 'chosengroup' => (int) $this->evening->id]);

        $this->assertTrue($result->was_created());
        $this->assertSame((int) $this->evening->id, (int) $this->record()->chosengroupid);
    }

    /**
     * A record never carries a chosen group beside a key route, nor a negative one.
     *
     * Changes that must make it fail: submission::create() accepting both.
     *
     * @return void
     */
    public function test_a_record_never_carries_a_chosen_group_beside_a_key_route(): void {
        $userenrolmentid = 999999;
        foreach ([[submission::KEY_FOR_REVIEW, 5], [submission::KEY_NONE, -1]] as [$outcome, $chosen]) {
            try {
                submission::create(
                    $this->instance,
                    (int) $this->applicant->id,
                    $userenrolmentid,
                    new \stdClass(),
                    $outcome === submission::KEY_NONE ? 0 : (int) $this->evening->id,
                    $outcome,
                    $chosen
                );
                $this->fail('a chosen group was recorded with the outcome ' . $outcome);
            } catch (\coding_exception $e) {
                $this->assertStringContainsString('chosen group', $e->getMessage());
            }
        }
        $this->assertFalse($this->record());
    }

    /**
     * The applicant's pending wording names the chosen group, in the escaped spelling.
     *
     * Both sinks render the sentence raw: the core notification on the enrolment page and on
     * applied.php, and the form's moodle_exception.
     *
     * Changes that must make it fail: the chosen group not read for a pending application, or its
     * name put in unescaped.
     *
     * @return void
     */
    public function test_the_pending_wording_names_the_chosen_group(): void {
        global $DB;

        $DB->set_field('groups', 'name', 'R&D < Evening', ['id' => $this->evening->id]);
        $this->submit(['chosengroup' => (int) $this->evening->id]);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $this->instance->id], 'id, status, timeend', MUST_EXIST);

        $this->assertSame('applicationchosengroup_body', applicantstate::message_key($ue, false));
        $state = applicantstate::describe($ue, false);
        $this->assertSame(
            get_string('applicationchosengroup_body', 'enrol_apply', 'R&amp;D &lt; Evening'),
            $state['message']
        );
        $this->assertSame(get_string('applicationsubmitted', 'enrol_apply'), $state['heading']);
    }

    /**
     * A chosen group the applicant may no longer see is not named; the plain pending wording is.
     *
     * Changes that must make it fail: the name read without the applicant's visibility.
     *
     * @return void
     */
    public function test_a_chosen_group_hidden_since_is_not_named(): void {
        global $DB;

        $this->submit(['chosengroup' => (int) $this->evening->id]);
        $DB->set_field('groups', 'visibility', GROUPS_VISIBILITY_MEMBERS, ['id' => $this->evening->id]);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $this->instance->id], 'id, status, timeend', MUST_EXIST);

        $this->assertSame('applicationsubmitted_body', applicantstate::message_key($ue, false));
        $this->assertStringNotContainsString('Evening A', applicantstate::describe($ue, false)['message']);
    }

    /**
     * The acknowledgement page names the chosen group.
     *
     * The page script is run whole, as the request would run it, and its markup read back.
     *
     * Changes that must make it fail: applied.php describing the row without the choice.
     *
     * @return void
     */
    public function test_the_acknowledgement_names_the_chosen_group(): void {
        global $CFG, $DB, $PAGE, $OUTPUT, $USER, $SITE, $COURSE;

        $this->submit(['chosengroup' => (int) $this->evening->id]);

        $_GET = ['instance' => $this->instance->id];
        $level = ob_get_level();
        ob_start();
        try {
            require($CFG->dirroot . '/enrol/apply/applied.php');
        } finally {
            $html = '';
            while (ob_get_level() > $level) {
                $html = ob_get_clean() . $html;
            }
        }

        $this->assertStringContainsString(get_string('applicationchosengroup_body', 'enrol_apply', 'Evening A'), $html);
    }
}
