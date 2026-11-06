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

/**
 * Tests for the key-only mode that sends applications for review (docs/design/group-review-scope.md, R1).
 *
 * The key is as mandatory as in key_only_test's mode, so every refusal there is a refusal here;
 * what differs is that a valid key writes an application waiting for a decision instead of
 * admitting.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
#[CoversClass(\enrol_apply\local\groupkey::class)]
#[CoversClass(\enrol_apply\local\applicantstate::class)]
#[CoversClass(\enrol_apply\local\submission::class)]
final class key_review_test extends \advanced_testcase {
    /** @var string The key of the keyed group. */
    private const KEY = 'Turma-A-2027';

    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass A reviewed key-only apply instance that lists one group of its own. */
    private $instance;

    /** @var \stdClass The group the key names. */
    private $keyed;

    /** @var \stdClass The group the instance's own list names. */
    private $listed;

    /** @var \stdClass A teacher told about every new application. */
    private $teacher;

    /**
     * A course with a reviewed key-only instance, a keyed group, a listed group and a teacher.
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
            'customchar1' => groupkey::MODE_REVIEW,
            'customtext3' => '$@ALL@$',
        ] + $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $this->keyed = $generator->create_group([
            'courseid' => $this->course->id,
            'name' => 'R&D < Team',
            'enrolmentkey' => self::KEY,
        ]);
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
     * The messages one user received.
     *
     * @param array $messages Messages from a sink.
     * @param \stdClass $user The recipient.
     * @return array Their messages, in order.
     */
    private function sent_to(array $messages, \stdClass $user): array {
        return array_values(array_filter($messages, static fn($m): bool => (int) $m->useridto === (int) $user->id));
    }

    /**
     * Assert that a refusal left nothing behind: no enrolment, no record, no answers, no comment
     * row, no message, no wrong-key count from the door.
     *
     * @param \stdClass $applicant The applicant.
     * @param array $messages Messages sent while the submission ran.
     * @return void
     */
    private function assert_nothing_written(\stdClass $applicant, array $messages): void {
        global $DB;

        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $applicant->id, 'enrolid' => $this->instance->id]));
        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['userid' => $applicant->id]));
        $this->assertSame(0, $DB->count_records('enrol_apply_answer'));
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
     * A valid key writes an ordinary application waiting for a decision, recording the key's
     * group and what the key did; nobody is admitted, no group is joined, no admission event is
     * raised, the managers are asked to decide, and the wrong-key count is cleared.
     *
     * Changes that must make it fail: the key admitting on this mode, the outcome or the group
     * not recorded, the admission notice sent instead of the application one, or the throttle
     * left as it was.
     *
     * @return void
     */
    public function test_a_valid_key_sends_the_application_for_review(): void {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        for ($i = 0; $i < keyattempts::LIMIT; $i++) {
            keyattempts::record_failure((int) $this->instance->id, (int) $applicant->id);
        }
        $events = $this->redirectEvents();

        [$result, $messages] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertTrue($result->was_created());
        $this->assertFalse($result->was_admitted());
        $this->assertSame(submission::KEY_FOR_REVIEW, $result->key_outcome());
        $this->assertSame((int) $this->keyed->id, $result->key_group());

        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $applicant, '', true));
        $ue = $DB->get_record('user_enrolments', ['userid' => $applicant->id, 'enrolid' => $this->instance->id], '*', MUST_EXIST);
        $this->assertSame(ENROL_USER_SUSPENDED, (int) $ue->status);
        $this->assertFalse($this->member($this->keyed, $applicant));
        $this->assertFalse($this->member($this->listed, $applicant));

        $record = $this->record($applicant);
        $this->assertSame(submission::KEY_FOR_REVIEW, (int) $record->keyoutcome);
        $this->assertSame((int) $this->keyed->id, (int) $record->keygroupid);
        $this->assertSame(submission::STATUS_PENDING, (int) $record->status);
        $this->assertSame(0, (int) $record->decidedby);
        $this->assertSame((int) $ue->id, (int) $record->userenrolmentid);

        $admitted = array_filter(
            $events->get_events(),
            static fn($e): bool => $e instanceof \enrol_apply\event\application_admitted_by_key
        );
        $this->assertSame([], array_values($admitted));

        $told = $this->sent_to($messages, $this->teacher);
        $this->assertCount(1, $told);
        $this->assertSame(get_string('mailtoteacher_subject', 'enrol_apply'), $told[0]->subject, 'asked to decide');
        $this->assertFalse(keyattempts::locked((int) $this->instance->id, (int) $applicant->id));
    }

    /**
     * Posts that carry no key that counts, with the reason each is given.
     *
     * @return array Posted data and the expected reason's string id, keyed by case name.
     */
    public static function no_key_provider(): array {
        return [
            'no key' => [[], 'groupkeyrequired_review'],
            'an empty key' => [['groupkey' => ''], 'groupkeyrequired_review'],
            'a key that is not a string' => [['groupkey' => [self::KEY]], 'groupkeyrequired_review'],
            'a wrong key' => [['groupkey' => 'Turma-Z-2027'], 'groupkeyinvalid_required'],
            'a wrong key with "without the key" ticked' => [
                ['groupkey' => 'Turma-Z-2027', 'withoutkey' => 1],
                'groupkeyinvalid_required',
            ],
            'no key with "without the key" ticked' => [['withoutkey' => 1], 'groupkeyrequired_review'],
        ];
    }

    /**
     * Without a key that counts, the instance refuses and writes nothing, where the key-or-review
     * mode would leave an application waiting.
     *
     * Changes that must make it fail: the key not mandatory on this mode, so the door falls through
     * to apply(), or "without the key" honoured.
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
     * "Without the key" means nothing here: a valid key posted with it still sends the
     * application for review.
     *
     * @return void
     */
    public function test_a_ticked_way_out_is_ignored(): void {
        $applicant = $this->getDataGenerator()->create_user();

        [$result] = $this->submit($applicant, ['groupkey' => self::KEY, 'withoutkey' => 1]);

        $this->assertTrue($result->was_created());
        $this->assertSame(submission::KEY_FOR_REVIEW, $result->key_outcome());
    }

    /**
     * A valid key with no place left on the method is refused, and nothing is written. The control
     * is the same key once a place is free again.
     *
     * Changes that must make it fail: the places not checked on this mode.
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
        $this->assertSame(submission::KEY_FOR_REVIEW, $result->key_outcome(), 'the control: with a place free');
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
     * written. The control is the same key once the group has a seat.
     *
     * Changes that must make it fail: the seats not checked on this mode.
     *
     * @return void
     */
    public function test_the_keys_group_out_of_seats_refuses_naming_it(): void {
        $this->require_groupdist();
        $this->seats($this->keyed, 0);
        $applicant = $this->getDataGenerator()->create_user();

        [$result, $messages] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertTrue($result->is_refusal());
        $this->assertSame(get_string('keygroupfull', 'enrol_apply', 'R&amp;D &lt; Team'), $result->reason());
        $this->assert_nothing_written($applicant, $messages);

        $this->seats($this->keyed, 5);
        [$result] = $this->submit($applicant, ['groupkey' => self::KEY]);
        $this->assertSame(submission::KEY_FOR_REVIEW, $result->key_outcome(), 'the control: with a seat free');
    }

    /**
     * A group of the instance's own list out of seats refuses too, without naming it.
     *
     * Changes that must make it fail: only the key's own group checked.
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
        $this->assert_nothing_written($applicant, $messages);
    }

    /**
     * An application for review takes no admission lock, so a lock held elsewhere for the whole
     * timeout does not refuse it, where key-only mode refuses with "try again".
     *
     * Changes that must make it fail: this mode routed through lock_admission().
     *
     * @return void
     */
    public function test_a_held_admission_lock_does_not_refuse_it(): void {
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

        [$result] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->assertTrue($result->was_created());
        $this->assertSame(submission::KEY_FOR_REVIEW, $result->key_outcome());
    }

    /**
     * Approved with no group choice, an application a key sent for review joins the key's group
     * and the instance's list; an application whose key failed to admit (a full group on the
     * key-or-review mode) keeps the instance's list alone.
     *
     * Changes that must make it fail: the key group dropped on approval, or added for every key
     * outcome.
     *
     * @return void
     */
    public function test_an_approval_without_a_choice_joins_the_key_group(): void {
        global $DB;

        $reviewed = $this->getDataGenerator()->create_user();
        $failed = $this->getDataGenerator()->create_user();
        $this->submit($reviewed, ['groupkey' => self::KEY]);
        $this->submit($failed, ['groupkey' => self::KEY]);
        // What the key-or-review mode records when the key's group had no seat: same group, another outcome.
        $DB->set_field('enrol_apply_submission', 'keyoutcome', submission::KEY_GROUP_FULL, ['userid' => $failed->id]);

        $this->setAdminUser();
        $this->plugin->confirm_enrolment([
            (int) $this->record($reviewed)->userenrolmentid,
            (int) $this->record($failed)->userenrolmentid,
        ]);

        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $reviewed, '', true));
        $this->assertTrue($this->member($this->keyed, $reviewed));
        $this->assertTrue($this->member($this->listed, $reviewed));
        $this->assertTrue($this->member($this->listed, $failed), 'the control: the instance list still applies');
        $this->assertFalse($this->member($this->keyed, $failed), 'the control: a failed key joins nothing of its own');
    }

    /**
     * A key group deleted while the application waited is skipped on approval, which still joins
     * the instance's list.
     *
     * Changes that must make it fail: the key group passed to groups_add_member() unchecked, which
     * throws on an unknown id.
     *
     * @return void
     */
    public function test_a_key_group_deleted_while_waiting_is_skipped_on_approval(): void {
        $applicant = $this->getDataGenerator()->create_user();
        $this->submit($applicant, ['groupkey' => self::KEY]);
        groups_delete_group($this->keyed->id);

        $this->setAdminUser();
        $this->plugin->confirm_enrolment([(int) $this->record($applicant)->userenrolmentid]);

        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $applicant, '', true));
        $this->assertTrue($this->member($this->listed, $applicant));
    }

    /**
     * The managers' new-application notice names the key group to a recipient who may see it, and
     * tells a mentor, who holds the capability in the applicant's user context alone, of the key
     * without the group.
     *
     * Changes that must make it fail: the group left out of the notice, or named to anybody.
     *
     * @return void
     */
    public function test_the_notice_names_the_key_group_only_to_who_may_see_it(): void {
        $applicant = $this->getDataGenerator()->create_user();
        $mentor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applymentor']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $mentor->id, \context_user::instance($applicant->id)->id);

        [, $messages] = $this->submit($applicant, ['groupkey' => self::KEY]);

        $toteacher = $this->sent_to($messages, $this->teacher);
        $this->assertCount(1, $toteacher);
        $this->assertStringContainsString(
            s(get_string('mailtoteacher_keyreview', 'enrol_apply', 'R&D < Team')),
            $toteacher[0]->fullmessagehtml
        );
        $tomentor = $this->sent_to($messages, $mentor);
        $this->assertCount(1, $tomentor);
        $this->assertSame(get_string('mailtoteacher_subject', 'enrol_apply'), $tomentor[0]->subject);
        $this->assertStringContainsString(
            get_string('mailtoteacher_keyreviewnogroup', 'enrol_apply'),
            $tomentor[0]->fullmessagehtml
        );
        $this->assertStringNotContainsString('R&amp;D', $tomentor[0]->fullmessagehtml);
    }

    /**
     * Pinned design decision: unticking every group applies the defaults, as in every mode
     * (docs/design/group-review-scope.md, "Settled during R1"). An approval posting an empty
     * group list, the review page's "untick everything", still joins the key's group and the
     * instance's own groups, because an empty recorded choice reads as none.
     *
     * Changes that must make it fail: an empty posted list read as a choice of no group at all.
     *
     * @return void
     */
    public function test_unticking_every_group_applies_the_defaults(): void {
        $applicant = $this->getDataGenerator()->create_user();
        $this->submit($applicant, ['groupkey' => self::KEY]);

        $this->setAdminUser();
        $this->plugin->confirm_enrolment([(int) $this->record($applicant)->userenrolmentid], '', ['groups' => []]);

        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $applicant, '', true));
        $this->assertTrue($this->member($this->keyed, $applicant));
        $this->assertTrue($this->member($this->listed, $applicant));
    }

    /**
     * Visibilities that hide a group from a reader without viewhiddengroups who is not a member.
     *
     * @return array Visibility, keyed by case name.
     */
    public static function hidden_visibility_provider(): array {
        return [
            'none' => [GROUPS_VISIBILITY_NONE],
            'members, the recipient not one' => [GROUPS_VISIBILITY_MEMBERS],
        ];
    }

    /**
     * The managers' notice of a review application does not name a key group core hides from its
     * recipient: a recipient holding the managing capability but not moodle/course:viewhiddengroups,
     * and no member, is told of the key without the group. A teacher holding viewhiddengroups is
     * told the name.
     *
     * Changes that must make it fail: the notice naming the group without core's visibility rule.
     *
     * @param int $visibility The key group's visibility.
     * @return void
     */
    #[DataProvider('hidden_visibility_provider')]
    public function test_the_notice_does_not_name_a_hidden_key_group(int $visibility): void {
        $keyed = clone $this->keyed;
        $keyed->visibility = $visibility;
        groups_update_group($keyed);

        // A role of its own: every stock archetype holding the capability also holds viewhiddengroups.
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applyclerk']);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        $clerk = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($clerk->id, $this->course->id, 'applyclerk');
        $this->assertFalse(has_capability(
            'moodle/course:viewhiddengroups',
            \context_course::instance($this->course->id),
            $clerk
        ));

        [, $messages] = $this->submit($this->getDataGenerator()->create_user(), ['groupkey' => self::KEY]);

        $toclerk = $this->sent_to($messages, $clerk);
        $this->assertCount(1, $toclerk);
        $this->assertStringContainsString(
            get_string('mailtoteacher_keyreviewnogroup', 'enrol_apply'),
            $toclerk[0]->fullmessagehtml
        );
        $this->assertStringNotContainsString('R&amp;D', $toclerk[0]->fullmessagehtml);
        // The control: a teacher, who holds viewhiddengroups, is told the name.
        $toteacher = $this->sent_to($messages, $this->teacher);
        $this->assertCount(1, $toteacher);
        $this->assertStringContainsString(
            s(get_string('mailtoteacher_keyreview', 'enrol_apply', 'R&D < Team')),
            $toteacher[0]->fullmessagehtml
        );
    }

    /**
     * The applicant is told the key was accepted and the application is waiting for review,
     * naming the key's group.
     *
     * Changes that must make it fail: the pending state not refined for this outcome.
     *
     * @return void
     */
    public function test_the_applicant_is_told_the_key_was_accepted(): void {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $this->submit($applicant, ['groupkey' => self::KEY]);
        $row = $DB->get_record(
            'user_enrolments',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            'id, status, timeend',
            MUST_EXIST
        );

        $state = \enrol_apply\local\applicantstate::describe($row, false);

        $this->assertSame(
            get_string('applicationkeyforreview_body', 'enrol_apply', 'R&amp;D &lt; Team'),
            $state['message']
        );
        $this->assertSame('applicationkeyforreview_body', \enrol_apply\local\applicantstate::message_key($row, false));
        $this->assertFalse($state['admitted']);
    }

    /**
     * The enrolment page's card says a key is needed and its button promises an application, not
     * an enrolment.
     *
     * Changes that must make it fail: the key-or-review card on this mode, or key-only mode's
     * "Enrol with a key".
     *
     * @return void
     */
    public function test_the_card_says_a_key_is_needed_and_offers_an_application(): void {
        global $PAGE;

        $PAGE->set_url('/enrol/index.php', ['id' => $this->course->id]);
        $this->setUser($this->getDataGenerator()->create_user());

        $card = (string) $this->plugin->enrol_page_hook($this->instance);

        $this->assertStringContainsString(s(get_string('keyonlycard', 'enrol_apply')), $card);
        $this->assertStringContainsString(s(get_string('applywithkey', 'enrol_apply')), $card);
        $this->assertStringNotContainsString(s(get_string('enrolwithkey', 'enrol_apply')), $card);
        $this->assertStringNotContainsString(s(get_string('youwillchecknddetails', 'enrol_apply')), $card);
    }

    /**
     * An unnamed instance in this mode has a name of its own; a typed name wins.
     *
     * Changes that must make it fail: the mode read as key-only's name, or as the plugin's.
     *
     * @return void
     */
    public function test_an_unnamed_reviewed_method_is_named_after_what_it_does(): void {
        $this->assertSame(get_string('pluginname_keyreview', 'enrol_apply'), $this->plugin->get_instance_name($this->instance));

        $this->set_instance('name', 'Intake & 2027');
        $this->assertSame('Intake &amp; 2027', $this->plugin->get_instance_name($this->instance));
    }

    /**
     * The new outcome has a label of its own, which the report and the queue read.
     *
     * @return void
     */
    public function test_the_outcome_is_labelled(): void {
        $this->assertSame(
            get_string('keyoutcomeforreview', 'enrol_apply'),
            submission::key_outcome_label(submission::KEY_FOR_REVIEW)
        );
        $this->assertContains(submission::KEY_FOR_REVIEW, submission::KEY_OUTCOMES);
    }
}
