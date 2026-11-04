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
use enrol_apply\local\keyattempts;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass target below resolves when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/apply/tests/fixtures/key_changing_plugin.php');

/**
 * Tests for the write door of a key-only instance (docs/design/group-key-only.md, Q1).
 *
 * Every route the key-or-review mode leaves waiting for a decision is refused here, before
 * anything is written; key_admission_test holds the other mode.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
#[CoversClass(\enrol_apply\local\groupkey::class)]
final class key_only_test extends \advanced_testcase {
    /** @var string The key of the keyed group. */
    private const KEY = 'Turma-A-2027';

    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass A key-only apply instance that lists one group of its own. */
    private $instance;

    /** @var \stdClass The group the key names. */
    private $keyed;

    /** @var \stdClass The group the instance's own list names. */
    private $listed;

    /** @var \stdClass A teacher told about every new application. */
    private $teacher;

    /**
     * A course with a key-only instance, a keyed group, a listed group and a teacher.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->plugin = enrol_get_plugin('apply');
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $id = $this->plugin->add_instance($this->course, [
            'customchar1' => groupkey::MODE_REQUIRED,
            'customtext3' => '$@ALL@$',
        ] + $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $this->keyed = $generator->create_group(['courseid' => $this->course->id, 'name' => 'A', 'enrolmentkey' => self::KEY]);
        $this->listed = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Everyone']);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $id, 'groupid' => $this->listed->id]);

        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * End the simulated request: the wrong-key throttle holds its lock until then.
     *
     * @return void
     */
    protected function tearDown(): void {
        keyattempts::release_all();
        parent::tearDown();
    }

    /**
     * Submit an application as the admin, the way a caller other than the form would.
     *
     * @param \stdClass $applicant The applicant.
     * @param array $data Form data on top of an empty comment.
     * @return array The result and the messages sent while it ran.
     */
    private function submit(\stdClass $applicant, array $data): array {
        $this->setAdminUser();
        $sink = $this->redirectMessages();
        $result = $this->plugin->submit_application($this->instance, (int) $applicant->id, (object) ($data + [
            'applydescription' => 'Please',
        ]));
        $messages = $sink->get_messages();
        $sink->close();

        return [$result, $messages];
    }

    /**
     * Set a column of the instance, in the database and in the object the tests pass.
     *
     * @param string $field Column.
     * @param mixed $value Value.
     * @return void
     */
    private function set_instance(string $field, $value): void {
        global $DB;

        $DB->set_field('enrol', $field, $value, ['id' => $this->instance->id]);
        $this->instance->{$field} = $value;
    }

    /**
     * Assert that a refusal left nothing behind: no enrolment, no record (which owns any answers),
     * no comment row, no message, no wrong-key count from the door.
     *
     * @param \stdClass $applicant The applicant.
     * @param array $messages Messages sent while the submission ran.
     * @return void
     */
    private function assert_nothing_written(\stdClass $applicant, array $messages): void {
        global $DB;

        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $applicant->id, 'enrolid' => $this->instance->id]));
        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['userid' => $applicant->id]));
        $this->assertSame(0, $DB->count_records('enrol_apply_applicationinfo'));
        $this->assertSame([], $messages);
        $this->assertFalse(keyattempts::locked((int) $this->instance->id, (int) $applicant->id));
    }

    /**
     * Whether the user is in the group through this method.
     *
     * @param \stdClass $group The group.
     * @param \stdClass $user The user.
     * @return bool True when the membership exists and carries this plugin's stamp.
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
     * The admission notice switch, and how many messages the teacher gets.
     *
     * @return array customchar2 and the expected count, keyed by case name.
     */
    public static function notice_provider(): array {
        return [
            'switch never set' => [null, 0],
            'switch off' => ['', 0],
            'switch on' => ['1', 1],
        ];
    }

    /**
     * A valid key admits at once, as in the other mode, and the managers are told only when the
     * instance's switch is on.
     *
     * Changes that must make it fail: the notice sent whatever the switch says, or never sent.
     *
     * @param string|null $switch customchar2.
     * @param int $told Messages the teacher gets.
     * @return void
     */
    #[DataProvider('notice_provider')]
    public function test_a_valid_key_admits_and_tells_the_managers_only_when_asked(?string $switch, int $told): void {
        global $DB;

        $this->set_instance('customchar2', $switch);
        $applicant = $this->getDataGenerator()->create_user();

        [$result, $messages] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertTrue($result->was_admitted());
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $applicant, '', true));
        $this->assertTrue($this->member($this->keyed, $applicant));
        $this->assertTrue($this->member($this->listed, $applicant));
        $record = $DB->get_record('enrol_apply_submission', ['userid' => $applicant->id], '*', MUST_EXIST);
        $this->assertSame(submission::KEY_ADMITTED, (int) $record->keyoutcome);

        $toteacher = array_values(array_filter($messages, fn($m): bool => (int) $m->useridto === (int) $this->teacher->id));
        $this->assertCount($told, $toteacher);
        if ($told) {
            $this->assertSame(get_string('mailtoteacher_admitted_subject', 'enrol_apply'), $toteacher[0]->subject);
        }
    }

    /**
     * Posts that carry no key that counts, with the reason each is given.
     *
     * @return array Posted data and the expected reason's string id, keyed by case name.
     */
    public static function no_key_provider(): array {
        return [
            'no key' => [[], 'groupkeyrequired'],
            'an empty key' => [['groupkey' => ''], 'groupkeyrequired'],
            'a key that is not a string' => [['groupkey' => [self::KEY]], 'groupkeyrequired'],
            'a wrong key' => [['groupkey' => 'Turma-Z-2027'], 'groupkeyinvalid_required'],
            'a wrong key with "without the key" ticked' => [
                ['groupkey' => 'Turma-Z-2027', 'withoutkey' => 1],
                'groupkeyinvalid_required',
            ],
            'no key with "without the key" ticked' => [['withoutkey' => 1], 'groupkeyrequired'],
        ];
    }

    /**
     * Without a key that counts, a key-only instance refuses and writes nothing, where the other
     * mode would leave an application waiting.
     *
     * Changes that must make it fail: falling through to apply(), or honouring "without the key".
     *
     * @param array $data Posted data.
     * @param string $reason The expected reason's string id.
     * @return void
     */
    #[DataProvider('no_key_provider')]
    public function test_without_a_valid_key_nothing_is_written(array $data, string $reason): void {
        $applicant = $this->getDataGenerator()->create_user();

        [$result, $messages] = $this->submit($applicant, $data);

        $this->assertTrue($result->is_refusal());
        $this->assertSame(get_string($reason, 'enrol_apply'), $result->reason());
        $this->assert_nothing_written($applicant, $messages);
    }

    /**
     * "Without the key" means nothing on a key-only instance: a valid key posted with it still
     * admits.
     *
     * Changes that must make it fail: the door honouring the box on this mode.
     *
     * @return void
     */
    public function test_a_ticked_way_out_is_ignored(): void {
        $applicant = $this->getDataGenerator()->create_user();

        [$result] = $this->submit($applicant, ['groupkey' => self::KEY, 'withoutkey' => 1]);

        $this->assertTrue($result->was_admitted());
    }

    /**
     * A valid key with no place left on the method is refused, and nothing is written.
     *
     * The control is the same key admitting once the place is free again.
     *
     * Changes that must make it fail: leaving the flagged application the other mode leaves.
     *
     * @return void
     */
    public function test_no_place_left_refuses(): void {
        $this->set_instance('customint4', 1);
        $occupant = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $occupant->id);
        $applicant = $this->getDataGenerator()->create_user();

        [$result, $messages] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertTrue($result->is_refusal());
        $this->assertSame(get_string('keynoplace', 'enrol_apply'), $result->reason());
        $this->assert_nothing_written($applicant, $messages);

        $this->set_instance('customint4', 2);
        [$result] = $this->submit($applicant, ['groupkey' => self::KEY]);
        $this->assertTrue($result->was_admitted(), 'the control: with a place free the key admits');
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
     * Declare seats on a group.
     *
     * @param \stdClass $group The group.
     * @param int $seats Seats.
     * @return void
     */
    private function seats(\stdClass $group, int $seats): void {
        $this->setAdminUser();
        \core_group\customfield\group_handler::create()->instance_form_save((object) [
            'id' => $group->id,
            'customfield_groupdist_seats' => $seats,
        ]);
    }

    /**
     * A valid key whose own group is out of seats is refused naming that group, and nothing is
     * written.
     *
     * Changes that must make it fail: leaving the flagged application the other mode leaves.
     *
     * @return void
     */
    public function test_the_keys_group_out_of_seats_refuses_naming_it(): void {
        $this->require_groupdist();
        $this->seats($this->keyed, 0);
        $applicant = $this->getDataGenerator()->create_user();

        [$result, $messages] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertTrue($result->is_refusal());
        $this->assertSame(get_string('keygroupfull', 'enrol_apply', 'A'), $result->reason());
        $this->assert_nothing_written($applicant, $messages);
    }

    /**
     * A group of the instance's own list out of seats refuses too, without naming a group the
     * applicant was never told of.
     *
     * @return void
     */
    public function test_a_listed_group_out_of_seats_refuses_without_naming_it(): void {
        $this->require_groupdist();
        $this->seats($this->listed, 0);
        $applicant = $this->getDataGenerator()->create_user();

        [$result, $messages] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertTrue($result->is_refusal());
        $this->assertSame(get_string('keyseatsfull', 'enrol_apply'), $result->reason());
        $this->assertStringNotContainsString('Everyone', $result->reason());
        $this->assert_nothing_written($applicant, $messages);
    }

    /**
     * A lock not obtained in time is refused with "try again", and nothing is written.
     *
     * @return void
     */
    public function test_an_unconfirmed_seat_refuses(): void {
        $this->plugin = new class extends \enrol_apply_plugin {
            /**
             * Stand in for a lock held elsewhere for the whole timeout.
             *
             * @param \stdClass $instance Course enrol instance.
             * @param array $groupids Group ids.
             * @return array|null Always null.
             */
            protected function lock_admission(\stdClass $instance, array $groupids): ?array {
                return null;
            }
        };
        $applicant = $this->getDataGenerator()->create_user();

        [$result, $messages] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertTrue($result->is_refusal());
        $this->assertSame(get_string('keyseatunconfirmed', 'enrol_apply'), $result->reason());
        $this->assert_nothing_written($applicant, $messages);
    }

    /**
     * A key that stops naming its group while the admission locks are taken is refused as a
     * wrong key, and nothing is written. The control is the same plugin changing nothing.
     *
     * @return void
     */
    public function test_a_key_rotated_while_the_locks_are_taken_refuses(): void {
        $this->plugin = key_changing_plugin::making('rotated', $this->keyed, $this->listed);
        $applicant = $this->getDataGenerator()->create_user();

        [$result, $messages] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertTrue($result->is_refusal());
        $this->assertSame(get_string('groupkeyinvalid_required', 'enrol_apply'), $result->reason());
        $this->assert_nothing_written($applicant, $messages);

        $this->plugin = key_changing_plugin::making('none', $this->keyed, $this->listed);
        $control = $this->getDataGenerator()->create_user();
        [$result] = $this->submit($control, ['groupkey' => self::KEY . '-new']);
        $this->assertTrue($result->was_admitted());
    }

    /**
     * The applicant limit still refuses a key holder before anything else, as in the other mode.
     *
     * @return void
     */
    public function test_the_applicant_limit_still_refuses_first(): void {
        $this->set_instance('customint3', 1);
        [$first] = $this->submit($this->getDataGenerator()->create_user(), ['groupkey' => self::KEY]);
        $this->assertTrue($first->was_admitted(), 'the control: the first key holder is admitted');
        $applicant = $this->getDataGenerator()->create_user();

        [$result, $messages] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertTrue($result->is_refusal());
        $this->assertSame(get_string('maxenrolledreached', 'enrol_apply'), $result->reason());
        $this->assert_nothing_written($applicant, $messages);
    }

    /**
     * The enrolment page's card says a key is needed before any form, and its button says so; the
     * other mode keeps its own card.
     *
     * Changes that must make it fail: the key-or-review card or button on a key-only instance.
     *
     * @return void
     */
    public function test_the_card_says_a_key_is_needed(): void {
        global $PAGE;

        $PAGE->set_url('/enrol/index.php', ['id' => $this->course->id]);
        $this->setUser($this->getDataGenerator()->create_user());

        $card = (string) $this->plugin->enrol_page_hook($this->instance);
        $this->assertStringContainsString(s(get_string('keyonlycard', 'enrol_apply')), $card);
        $this->assertStringContainsString(s(get_string('enrolwithkey', 'enrol_apply')), $card);
        $this->assertStringNotContainsString(s(get_string('youwillchecknddetails', 'enrol_apply')), $card);

        $this->set_instance('customchar1', groupkey::MODE_ANY);
        $card = (string) $this->plugin->enrol_page_hook($this->instance);
        $this->assertStringContainsString(s(get_string('startapplication', 'enrol_apply')), $card, 'the control');
        $this->assertStringNotContainsString(s(get_string('keyonlycard', 'enrol_apply')), $card);
    }

    /**
     * The names an instance takes, by mode and by whether the teacher named it.
     *
     * @return array customchar1, the stored name and the expected name's source, keyed by case name.
     */
    public static function name_provider(): array {
        return [
            'key only, unnamed' => ['required', '', 'keyonly'],
            'key only, named' => ['required', 'Intake & 2027', 'own'],
            'key or review, unnamed' => ['any', '', 'plugin'],
            'off, unnamed' => ['', '', 'plugin'],
        ];
    }

    /**
     * An unnamed key-only instance is called after what it does; a name the teacher typed wins,
     * in core's escaped spelling; the other modes keep the plugin's name.
     *
     * Changes that must make it fail: ignoring the instance's own name, or the mode.
     *
     * @param string $mode customchar1.
     * @param string $name The stored name.
     * @param string $source Which name is expected.
     * @return void
     */
    #[DataProvider('name_provider')]
    public function test_an_unnamed_key_only_method_is_named_after_what_it_does(string $mode, string $name, string $source): void {
        $this->set_instance('customchar1', $mode);
        $this->set_instance('name', $name);

        $expected = match ($source) {
            'keyonly' => get_string('pluginname_keyonly', 'enrol_apply'),
            'own' => 'Intake &amp; 2027',
            'plugin' => get_string('pluginname', 'enrol_apply'),
        };
        $this->assertSame($expected, $this->plugin->get_instance_name($this->instance));
    }
}
