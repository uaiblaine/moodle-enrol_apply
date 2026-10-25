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

use enrol_apply\local\keyattempts;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass target below resolves when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * Tests for the write door's group key route (docs/design/group-enrolment-key.md, K4).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
#[CoversClass(\enrol_apply\local\application_result::class)]
#[CoversClass(\enrol_apply\event\application_admitted_by_key::class)]
final class key_admission_test extends \advanced_testcase {
    /** @var string The key of the keyed group. */
    private const KEY = 'Turma-A-2027';

    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass An apply instance that accepts group keys and lists one group of its own. */
    private $instance;

    /** @var \stdClass The group the key names. */
    private $keyed;

    /** @var \stdClass The group the instance's own list names. */
    private $listed;

    /** @var \stdClass A teacher told about every new application. */
    private $teacher;

    /**
     * A course with a key-accepting instance, a keyed group, a listed group and a teacher.
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
            'customchar1' => \enrol_apply\local\groupkey::MODE_ANY,
            'customtext3' => '$@ALL@$',
        ] + $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $this->keyed = $generator->create_group(['courseid' => $this->course->id, 'name' => 'A', 'enrolmentkey' => self::KEY]);
        $this->listed = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Everyone']);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $id, 'groupid' => $this->listed->id]);

        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * Submit an application as the admin, the way a caller other than the form would.
     *
     * Run as the admin on purpose: what the route records must not depend on who is logged in.
     *
     * @param \stdClass $applicant The applicant.
     * @param array $data Form data on top of an empty comment.
     * @return array The result and the messages sent while it ran.
     */
    private function submit(\stdClass $applicant, array $data): array {
        $this->setAdminUser();
        $sink = $this->redirectMessages();
        $result = $this->plugin->submit_application($this->instance, (int) $applicant->id, (object) ($data + [
            'applydescription' => '',
        ]));
        $messages = $sink->get_messages();
        $sink->close();

        return [$result, $messages];
    }

    /**
     * The applicant's record.
     *
     * @param \stdClass $applicant The applicant.
     * @return \stdClass The enrol_apply_submission row.
     */
    private function record(\stdClass $applicant): \stdClass {
        global $DB;

        return $DB->get_record('enrol_apply_submission', ['userid' => $applicant->id], '*', MUST_EXIST);
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
     * The messages a list holds for one user.
     *
     * @param array $messages Messages from a sink.
     * @param \stdClass $user The recipient.
     * @return int How many reached them.
     */
    private function sent_to(array $messages, \stdClass $user): int {
        return count(array_filter($messages, static fn($m): bool => (int) $m->useridto === (int) $user->id));
    }

    /**
     * A valid key admits at once: active, in the key's group and the instance's own, recorded as
     * decided by the applicant, no manager asked to decide, the event raised, the wrong-key count
     * cleared.
     *
     * Changes that must make it fail: the admission not recording its groups before activating,
     * the instance's list left out of the groups, the decider left as whoever is logged in, the
     * "new application" message sent anyway, the event or the throttle reset dropped.
     *
     * @return void
     */
    public function test_a_valid_key_admits_at_once(): void {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        for ($i = 0; $i < keyattempts::LIMIT; $i++) {
            keyattempts::record_failure((int) $this->instance->id, (int) $applicant->id);
        }
        $events = $this->redirectEvents();

        [$result, $messages] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertTrue($result->was_admitted());
        $this->assertSame((int) $this->keyed->id, $result->key_group());
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $applicant, '', true));
        $this->assertTrue($this->member($this->keyed, $applicant));
        $this->assertTrue($this->member($this->listed, $applicant));

        $record = $this->record($applicant);
        $this->assertSame(submission::KEY_ADMITTED, (int) $record->keyoutcome);
        $this->assertSame((int) $this->keyed->id, (int) $record->keygroupid);
        $this->assertSame(submission::STATUS_APPROVED, (int) $record->status);
        $this->assertSame((int) $applicant->id, (int) $record->decidedby);
        $this->assertFalse($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $record->userenrolmentid]));

        $this->assertSame(0, $this->sent_to($messages, $this->teacher), 'no manager is asked to decide');
        $this->assertFalse(keyattempts::locked((int) $this->instance->id, (int) $applicant->id));

        $admitted = array_values(array_filter(
            $events->get_events(),
            static fn($e): bool => $e instanceof \enrol_apply\event\application_admitted_by_key
        ));
        $this->assertCount(1, $admitted);
        $this->assertSame((int) $record->id, (int) $admitted[0]->objectid);
        $this->assertSame((int) $applicant->id, (int) $admitted[0]->relateduserid);
        $this->assertSame((int) $this->keyed->id, (int) $admitted[0]->other['groupid']);
    }

    /**
     * An admission by key sends the applicant no "your application was approved" message, and
     * an approval by a person still does.
     *
     * Changes that must make it fail: dropping the key check in notify_confirmed_application().
     *
     * @return void
     */
    public function test_an_admission_announces_no_approval(): void {
        $admitted = $this->getDataGenerator()->create_user();
        $approved = $this->getDataGenerator()->create_user();
        $this->submit($admitted, ['groupkey' => self::KEY]);
        $this->submit($approved, []);
        $this->plugin->confirm_enrolment([(int) $this->record($approved)->userenrolmentid]);

        $sink = $this->redirectMessages();
        $this->runAdhocTasks(\enrol_apply\task\notify_approval::class);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertSame(0, $this->sent_to($messages, $admitted));
        $this->assertSame(1, $this->sent_to($messages, $approved), 'the control: a person\'s approval is announced');
    }

    /**
     * The ways a key does not count, with the data each posts.
     *
     * @return array Posted data and whether the option is on, keyed by case name.
     */
    public static function no_key_provider(): array {
        return [
            'no key' => [[], true],
            'a wrong key' => [['groupkey' => 'Turma-Z-2027'], true],
            'the right key with "without the key" ticked' => [['groupkey' => self::KEY, 'withoutkey' => 1], true],
            'the right key on an instance that accepts none' => [['groupkey' => self::KEY], false],
            'a key that is not a string' => [['groupkey' => [self::KEY]], true],
        ];
    }

    /**
     * Without a key that counts, the application waits for a decision as it always did.
     *
     * Changes that must make it fail: the write door honouring a key posted with the box ticked
     * (the page transport posts both), ignoring the instance's option, or casting a posted array
     * to a string (a warning, and the key "Array").
     *
     * @param array $data Posted data.
     * @param bool $optionon Whether the instance accepts keys.
     * @return void
     */
    #[DataProvider('no_key_provider')]
    public function test_without_a_counted_key_the_application_waits(array $data, bool $optionon): void {
        global $DB;

        if (!$optionon) {
            $DB->set_field('enrol', 'customchar1', \enrol_apply\local\groupkey::MODE_OFF, ['id' => $this->instance->id]);
            $this->instance->customchar1 = \enrol_apply\local\groupkey::MODE_OFF;
        }
        $applicant = $this->getDataGenerator()->create_user();

        [$result, $messages] = $this->submit($applicant, $data);

        $this->assertTrue($result->was_created());
        $this->assertFalse($result->was_admitted());
        $this->assertSame(submission::KEY_NONE, $result->key_outcome());
        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $applicant, '', true));
        $this->assertSame(submission::KEY_NONE, (int) $this->record($applicant)->keyoutcome);
        $this->assertSame(1, $this->sent_to($messages, $this->teacher), 'the manager is asked to decide');
    }

    /**
     * A valid key with no place left on the method leaves an application waiting, flagged.
     *
     * Changes that must make it fail: dropping the places check, which would admit past the
     * method's places with nobody deciding.
     *
     * @return void
     */
    public function test_no_place_left_leaves_a_flagged_application(): void {
        global $DB;

        $DB->set_field('enrol', 'customint4', 1, ['id' => $this->instance->id]);
        $this->instance->customint4 = 1;
        $this->plugin->enrol_user($this->instance, $this->getDataGenerator()->create_user()->id);
        $applicant = $this->getDataGenerator()->create_user();

        [$result, $messages] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertFalse($result->was_admitted());
        $this->assertSame(submission::KEY_NO_PLACE, $result->key_outcome());
        $record = $this->record($applicant);
        $this->assertSame(submission::KEY_NO_PLACE, (int) $record->keyoutcome);
        $this->assertSame((int) $this->keyed->id, (int) $record->keygroupid);
        $this->assertFalse($this->member($this->keyed, $applicant));
        $this->assertSame(1, $this->sent_to($messages, $this->teacher));
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
     * Which group runs out of seats.
     *
     * @return array The property naming the group, keyed by case name.
     */
    public static function full_group_provider(): array {
        return [
            'the key\'s own group' => ['keyed'],
            'a group of the instance\'s own list' => ['listed'],
        ];
    }

    /**
     * A valid key with a group out of seats leaves an application waiting, flagged; the
     * instance's own groups count too (G14).
     *
     * Changes that must make it fail: dropping the seats check, or checking the key's group alone.
     *
     * @param string $which Property naming the group with no seat left.
     * @return void
     */
    #[DataProvider('full_group_provider')]
    public function test_a_group_out_of_seats_leaves_a_flagged_application(string $which): void {
        $this->require_groupdist();
        $this->seats($this->{$which}, 0);
        $applicant = $this->getDataGenerator()->create_user();

        [$result] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertFalse($result->was_admitted());
        $this->assertSame(submission::KEY_GROUP_FULL, (int) $this->record($applicant)->keyoutcome);
        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $applicant, '', true));
    }

    /**
     * With seats left everywhere, the key admits: the control for the test above.
     *
     * @return void
     */
    public function test_seats_left_everywhere_still_admit(): void {
        $this->require_groupdist();
        $this->seats($this->keyed, 2);
        $this->seats($this->listed, 2);
        $applicant = $this->getDataGenerator()->create_user();

        [$result] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertTrue($result->was_admitted());
    }

    /**
     * A lock not obtained in time leaves an application waiting, recorded as a place or seat that
     * could not be confirmed rather than as none being left.
     *
     * Changes that must make it fail: treating a missing lock as success.
     *
     * @return void
     */
    public function test_an_unconfirmed_seat_leaves_a_flagged_application(): void {
        $contended = new class extends \enrol_apply_plugin {
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
        $this->plugin = $contended;
        $applicant = $this->getDataGenerator()->create_user();

        [$result] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertFalse($result->was_admitted());
        $this->assertSame(submission::KEY_SEAT_UNCONFIRMED, (int) $this->record($applicant)->keyoutcome);
        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $applicant, '', true));
    }

    /**
     * A key replaces the decision, not the eligibility: with new applications switched off, a
     * key holder is refused like anybody else and nothing is written.
     *
     * @return void
     */
    public function test_a_key_does_not_bypass_eligibility(): void {
        global $DB;

        $DB->set_field('enrol', 'customint6', 0, ['id' => $this->instance->id]);
        $this->instance->customint6 = 0;
        $applicant = $this->getDataGenerator()->create_user();

        [$result] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertTrue($result->is_refusal());
        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $applicant->id]));
    }

    /**
     * An admission whose activation fails part way leaves nothing behind: no enrolment, no record
     * claiming an admission nobody was told about, no membership.
     *
     * Changes that must make it fail: the admission written outside a transaction, or the
     * transaction committed on failure.
     *
     * @return void
     */
    public function test_a_failed_admission_leaves_nothing_behind(): void {
        global $DB;

        $failing = new class extends \enrol_apply_plugin {
            /**
             * Activate, then fail as a hook or a later write might.
             *
             * @param \stdClass $instance Course enrol instance.
             * @param \stdClass $userenrolment The user enrolment.
             * @return void
             * @throws \moodle_exception Always.
             */
            protected function activate(\stdClass $instance, \stdClass $userenrolment): void {
                parent::activate($instance, $userenrolment);
                throw new \moodle_exception('error');
            }
        };
        $this->plugin = $failing;
        $applicant = $this->getDataGenerator()->create_user();
        /* Without this the test runs inside the harness's own transaction, which makes admit()'s a
           nested one: its rollback is only marked, and the rows stay visible until the test ends. */
        $this->preventResetByRollback();

        try {
            $this->submit($applicant, ['groupkey' => self::KEY]);
            $this->fail('the failure did not reach the caller');
        } catch (\moodle_exception $e) {
            $this->assertSame('error', $e->errorcode);
        }

        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $applicant->id]));
        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['userid' => $applicant->id]));
        $this->assertFalse($this->member($this->keyed, $applicant));
    }

    /**
     * A posted array is no key, even against a group whose key is the word PHP casts it to.
     *
     * Changes that must make it fail: casting the posted value to a string, which turns any array
     * into "Array" (with a warning the mutation harness does not count, hence this test).
     *
     * @return void
     */
    public function test_a_posted_array_is_no_key(): void {
        $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'enrolmentkey' => 'Array']);
        $applicant = $this->getDataGenerator()->create_user();

        [$result] = $this->submit($applicant, ['groupkey' => ['anything']]);

        $this->assertFalse($result->was_admitted());
        $this->assertSame(submission::KEY_NONE, (int) $this->record($applicant)->keyoutcome);
    }

    /**
     * The enrolment page's card mentions the key only where a key can count.
     *
     * Changes that must make it fail: the sentence added whatever the option says.
     *
     * @return void
     */
    public function test_the_card_mentions_the_key_only_where_it_counts(): void {
        global $DB, $PAGE;

        $PAGE->set_url('/enrol/index.php', ['id' => $this->course->id]);
        $this->setUser($this->getDataGenerator()->create_user());
        $offered = get_string('groupkeyoffered', 'enrol_apply');

        $this->assertStringContainsString(s($offered), (string) $this->plugin->enrol_page_hook($this->instance));

        $DB->set_field('enrol', 'customchar1', \enrol_apply\local\groupkey::MODE_OFF, ['id' => $this->instance->id]);
        $this->instance->customchar1 = \enrol_apply\local\groupkey::MODE_OFF;
        $this->assertStringNotContainsString(s($offered), (string) $this->plugin->enrol_page_hook($this->instance));
    }
}
