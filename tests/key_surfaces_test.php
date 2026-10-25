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

use core\output\notification;
use enrol_apply\local\applicantstate;
use enrol_apply\local\groupseats;
use enrol_apply\local\submission;
use enrol_apply\reportbuilder\local\formatters\submission as formatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * Tests for what each surface says about a group key (docs/design/group-enrolment-key.md, K6).
 *
 * The key group's name carries markup characters on purpose, so every assertion also pins which
 * spelling each sink receives.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(applicantstate::class)]
#[CoversClass(groupseats::class)]
#[CoversClass(formatter::class)]
#[CoversClass(\enrol_apply_renderer::class)]
#[CoversClass(\enrol_apply_plugin::class)]
final class key_surfaces_test extends \advanced_testcase {
    /** @var string The key of the keyed group. */
    private const KEY = 'Turma-A-2027';

    /** @var string The keyed group's name, with characters every sink must handle. */
    private const NAME = 'R&D < Team';

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

    /** @var \stdClass A group neither the key nor the instance names. */
    private $other;

    /** @var \stdClass A teacher told about every new application. */
    private $teacher;

    /**
     * A course with a key-accepting instance, a keyed group, a listed group and a teacher.
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
        $this->course = $generator->create_course();
        $id = $this->plugin->add_instance($this->course, [
            'customchar1' => \enrol_apply\local\groupkey::MODE_ANY,
            'customtext3' => '$@ALL@$',
        ] + $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $this->keyed = $generator->create_group([
            'courseid' => $this->course->id,
            'name' => self::NAME,
            'enrolmentkey' => self::KEY,
        ]);
        $this->listed = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Everyone']);
        $this->other = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Elsewhere']);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $id, 'groupid' => $this->listed->id]);

        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php'));
    }

    /**
     * Submit an application as the admin, the way a caller other than the form would.
     *
     * @param \stdClass $applicant The applicant.
     * @param array $data Form data on top of an empty comment.
     * @return array The messages sent while it ran.
     */
    private function submit(\stdClass $applicant, array $data): array {
        $this->setAdminUser();
        $sink = $this->redirectMessages();
        $this->plugin->submit_application($this->instance, (int) $applicant->id, (object) ($data + [
            'applydescription' => '',
        ]));
        $messages = $sink->get_messages();
        $sink->close();

        return $messages;
    }

    /**
     * The applicant's own user enrolment row, as the three applicant surfaces read it.
     *
     * @param \stdClass $applicant The applicant.
     * @return \stdClass The row, carrying id, status and timeend.
     */
    private function own_row(\stdClass $applicant): \stdClass {
        global $DB;

        return $DB->get_record(
            'user_enrolments',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            'id, status, timeend',
            MUST_EXIST
        );
    }

    /**
     * An application left waiting with what a valid key ran into, written directly.
     *
     * @param int $outcome One of the waiting KEY_ outcomes, or KEY_NONE for an application without a key.
     * @param int $status The enrolment status: suspended (pending) or the waiting list.
     * @return \stdClass The applicant.
     */
    private function waiting(int $outcome, int $status = ENROL_USER_SUSPENDED): \stdClass {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, $status);
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $applicant->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => $this->own_row($applicant)->id,
            'comment' => '',
            'status' => $status === ENROL_APPLY_USER_WAIT ? submission::STATUS_WAITING : submission::STATUS_PENDING,
            'timecreated' => time(),
            'keygroupid' => $outcome === submission::KEY_NONE ? 0 : $this->keyed->id,
            'keyoutcome' => $outcome,
        ]);

        return $applicant;
    }

    /**
     * The keyed group's name as format_string() escapes it.
     *
     * @return string The escaped spelling.
     */
    private function escaped_name(): string {
        return format_string(self::NAME, true, ['context' => \context_course::instance($this->course->id)]);
    }

    /**
     * An admission by key reads as one, naming the key group in its escaped spelling (the
     * notification renders the body raw), and is the one state offered the course.
     *
     * Changes that must make it fail: the admission not refined out of the plain approval, or
     * the group name handed over unescaped.
     *
     * @return void
     */
    public function test_an_admission_reads_as_an_admission_naming_its_group(): void {
        $applicant = $this->getDataGenerator()->create_user();
        $this->submit($applicant, ['groupkey' => self::KEY]);

        $state = applicantstate::describe($this->own_row($applicant), true);

        $this->assertSame(get_string('applicationadmittedbykey', 'enrol_apply'), $state['heading']);
        $this->assertSame(
            get_string('applicationadmittedbykey_body', 'enrol_apply', $this->escaped_name()),
            $state['message']
        );
        $this->assertStringContainsString('R&amp;D &lt; Team', $state['message']);
        $this->assertSame(notification::NOTIFY_SUCCESS, $state['type']);
        $this->assertTrue($state['admitted']);

        // Control: the same row without access is an enrolment that grants none, key or not.
        $inactive = applicantstate::describe($this->own_row($applicant), false);
        $this->assertSame(get_string('applicationinactive_body', 'enrol_apply'), $inactive['message']);
        $this->assertFalse($inactive['admitted']);
    }

    /**
     * Once a person decides over an admission, the approval is theirs and reads as an ordinary one.
     *
     * Changes that must make it fail: reading the admission from the outcome alone, without the
     * decider.
     *
     * @return void
     */
    public function test_an_admission_a_person_decided_over_reads_as_their_approval(): void {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $this->submit($applicant, ['groupkey' => self::KEY]);
        $DB->set_field('enrol_apply_submission', 'decidedby', $this->teacher->id, ['userid' => $applicant->id]);

        $state = applicantstate::describe($this->own_row($applicant), true);

        $this->assertSame(get_string('applicationapproved_body', 'enrol_apply'), $state['message']);
        $this->assertFalse($state['admitted']);
    }

    /**
     * The three ways a valid key can leave an application waiting, with the string each reads.
     *
     * @return array Outcome and string id, keyed by case name.
     */
    public static function waiting_provider(): array {
        return [
            'no place left' => [submission::KEY_NO_PLACE, 'applicationkeynoplace_body'],
            'group full' => [submission::KEY_GROUP_FULL, 'applicationkeygroupfull_body'],
            'not confirmed in time' => [submission::KEY_SEAT_UNCONFIRMED, 'applicationkeyunconfirmed_body'],
        ];
    }

    /**
     * A valid key that could not admit says why, names the key group, and reads as information;
     * the form's refusal gets the same sentence through message_key() and message_param().
     *
     * Changes that must make it fail: any of the three refinements dropped, or the form refusing
     * without the group name.
     *
     * @param int $outcome The recorded outcome.
     * @param string $stringid The body expected.
     * @return void
     */
    #[DataProvider('waiting_provider')]
    public function test_a_valid_key_that_could_not_admit_says_why(int $outcome, string $stringid): void {
        $row = $this->own_row($this->waiting($outcome));

        $state = applicantstate::describe($row, false);

        $this->assertSame(get_string($stringid, 'enrol_apply', $this->escaped_name()), $state['message']);
        $this->assertSame(get_string('applicationsubmitted', 'enrol_apply'), $state['heading']);
        $this->assertSame(notification::NOTIFY_INFO, $state['type']);
        $this->assertSame(
            $state['message'],
            get_string(
                applicantstate::message_key($row, false),
                'enrol_apply',
                applicantstate::message_param($row, false)
            )
        );
    }

    /**
     * A deferral is a person's decision, and says so whatever the key once ran into.
     *
     * @return void
     */
    public function test_a_deferral_reads_as_deferred_whatever_the_key_did(): void {
        $row = $this->own_row($this->waiting(submission::KEY_NO_PLACE, ENROL_APPLY_USER_WAIT));

        $this->assertSame(
            get_string('applicationdeferred_body', 'enrol_apply'),
            applicantstate::describe($row, false)['message']
        );
    }

    /**
     * A key group deleted since leaves the plain wording rather than a sentence about nothing.
     *
     * Changes that must make it fail: dropping the group lookup's fallback.
     *
     * @return void
     */
    public function test_a_deleted_key_group_leaves_the_plain_wording(): void {
        $row = $this->own_row($this->waiting(submission::KEY_NO_PLACE));
        groups_delete_group($this->keyed->id);

        $state = applicantstate::describe($row, false);

        $this->assertSame(get_string('applicationsubmitted_body', 'enrol_apply'), $state['message']);
        $this->assertNull(applicantstate::message_param($row, false));
    }

    /**
     * The managers are told of an admission as news: its own subject, the key group named once
     * escaped, and a link to the applicant rather than to a queue holding nothing for them.
     *
     * Changes that must make it fail: no notice sent on an admission, or the ordinary
     * "new application" one sent instead.
     *
     * @return void
     */
    public function test_the_managers_are_told_of_an_admission_as_news(): void {
        $applicant = $this->getDataGenerator()->create_user();

        $messages = array_values(array_filter(
            $this->submit($applicant, ['groupkey' => self::KEY]),
            fn($m): bool => (int) $m->useridto === (int) $this->teacher->id
        ));

        $this->assertCount(1, $messages);
        $this->assertSame(get_string('mailtoteacher_admitted_subject', 'enrol_apply'), $messages[0]->subject);
        $this->assertSame('application', $messages[0]->eventtype);
        $this->assertStringContainsString('R&amp;D &lt; Team', $messages[0]->fullmessagehtml);
        $this->assertStringNotContainsString('R&amp;amp;D', $messages[0]->fullmessagehtml);
        $this->assertStringContainsString('/user/profile.php', $messages[0]->fullmessagehtml);
        $this->assertStringNotContainsString('/enrol/apply/manage.php', $messages[0]->fullmessagehtml);

        // Control: an application waiting for review still asks for a decision.
        $waiting = array_values(array_filter(
            $this->submit($this->getDataGenerator()->create_user(), []),
            fn($m): bool => (int) $m->useridto === (int) $this->teacher->id
        ));
        $this->assertCount(1, $waiting);
        $this->assertSame(get_string('mailtoteacher_subject', 'enrol_apply'), $waiting[0]->subject);
        $this->assertStringContainsString('/enrol/apply/manage.php', $waiting[0]->fullmessagehtml);
    }

    /**
     * A mentor told of an admission reads it without the key group's name, which the review page
     * withholds from them too; the teacher beside them is the control.
     *
     * Changes that must make it fail: naming the group to every recipient.
     *
     * @return void
     */
    public function test_a_mentor_is_told_of_an_admission_without_the_group(): void {
        $applicant = $this->getDataGenerator()->create_user();
        $mentor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applymentor']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $mentor->id, \context_user::instance($applicant->id)->id);

        $messages = $this->submit($applicant, ['groupkey' => self::KEY]);
        $to = static fn(\stdClass $user): array => array_values(array_filter(
            $messages,
            static fn($m): bool => (int) $m->useridto === (int) $user->id
        ));

        $this->assertCount(1, $to($mentor));
        $this->assertStringContainsString(
            get_string('mailtoteacher_admittednogroup', 'enrol_apply'),
            $to($mentor)[0]->fullmessagehtml
        );
        $this->assertStringNotContainsString('R&amp;D', $to($mentor)[0]->fullmessagehtml);
        $this->assertStringContainsString('R&amp;D &lt; Team', $to($this->teacher)[0]->fullmessagehtml);
    }

    /**
     * The review page rendered for one application, by the current user.
     *
     * @param \stdClass $applicant The applicant.
     * @return string The page's form.
     */
    private function review(\stdClass $applicant): string {
        global $PAGE;

        $ueid = (int) $this->own_row($applicant)->id;
        $url = new \moodle_url('/enrol/apply/manage.php', ['userenrol' => $ueid]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));

        return $PAGE->get_renderer('enrol_apply')->review_form(
            \enrol_apply\local\queue::application($ueid),
            $applicant,
            $this->instance,
            $url
        );
    }

    /**
     * The review page says what the key ran into, names its group, and offers ticked the groups
     * the key would have joined, so approving without touching the chooser does what the key would
     * have done.
     *
     * Changes that must make it fail: the chip dropped, the groups not offered ticked, or only the
     * key's own group ticked (the method's list would then be lost on approval).
     *
     * @return void
     */
    public function test_the_review_page_names_the_key_group_and_ticks_its_groups(): void {
        $this->setAdminUser();

        // Control first: an application without a key ticks nothing and shows no chip.
        $plain = $this->review($this->waiting(submission::KEY_NONE));
        $this->assertStringNotContainsString(' selected>', $plain);
        $this->assertStringNotContainsString(get_string('keyoutcomenoplace', 'enrol_apply'), $plain);

        $html = $this->review($this->waiting(submission::KEY_NO_PLACE));

        $chip = get_string('keychipgroup', 'enrol_apply', (object) [
            'outcome' => get_string('keyoutcomenoplace', 'enrol_apply'),
            'group' => self::NAME,
        ]);
        $this->assertStringContainsString(s($chip), $html);
        $this->assertStringContainsString('<option value="' . $this->keyed->id . '" selected>', $html);
        $this->assertStringContainsString('<option value="' . $this->listed->id . '" selected>', $html);
        $this->assertStringContainsString('<option value="' . $this->other->id . '">', $html);
    }

    /**
     * A reader who may not see the course's groups reads the outcome without the group's name, as
     * the group chooser is withheld from them.
     *
     * Changes that must make it fail: naming the group without the course capability.
     *
     * @return void
     */
    public function test_a_mentor_reads_the_outcome_without_the_group(): void {
        $applicant = $this->waiting(submission::KEY_NO_PLACE);
        $mentor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applymentor']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $mentor->id, \context_user::instance($applicant->id)->id);
        $this->setUser($mentor);

        $html = $this->review($applicant);

        $this->assertStringContainsString(get_string('keyoutcomenoplace', 'enrol_apply'), $html);
        $this->assertStringNotContainsString('R&amp;D', $html);
    }

    /**
     * The queue badges an application with what its key ran into, and nothing without a key.
     *
     * Changes that must make it fail: the badge dropped, or the outcome not selected by the query.
     *
     * @return void
     */
    public function test_the_queue_badges_what_the_key_ran_into(): void {
        $this->setAdminUser();
        $label = get_string('keyoutcomegroupfull', 'enrol_apply');

        // Control first: a queue holding only an application without a key carries no badge.
        $this->waiting(submission::KEY_NONE);
        $this->assertStringNotContainsString($label, $this->queue());

        $this->waiting(submission::KEY_GROUP_FULL);
        $this->assertStringContainsString($label, $this->queue());
    }

    /**
     * The instance's queue, rendered.
     *
     * @return string The table's markup.
     */
    private function queue(): string {
        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);

        ob_start();
        $table->out(50, true);

        return ob_get_clean();
    }

    /**
     * The report's admission route reads the record's outcome, and a route of review for none.
     *
     * Changes that must make it fail: no label for the review route, or a vocabulary value
     * rendered as some other outcome.
     *
     * @return void
     */
    public function test_the_report_names_the_admission_route(): void {
        $row = new \stdClass();

        $this->assertSame(
            get_string('admissionroutereview', 'enrol_apply'),
            formatter::admission_route(submission::KEY_NONE, $row)
        );
        $this->assertSame(
            get_string('keyoutcomeadmitted', 'enrol_apply'),
            formatter::admission_route(submission::KEY_ADMITTED, $row)
        );
        $this->assertSame('9', formatter::admission_route('9', $row), 'an unknown value is shown, not relabelled');
        $this->assertSame('', formatter::admission_route(null, $row));
    }

    /**
     * The report's key group is escaped once, empty without a key, and says so when the group is gone.
     *
     * Changes that must make it fail: a deleted group read as no key, or the name left unescaped.
     *
     * @return void
     */
    public function test_the_report_names_the_key_group(): void {
        $row = (object) ['keygroupoutcome' => submission::KEY_ADMITTED, 'keygroupcourseid' => $this->course->id];

        $this->assertSame('R&amp;D &lt; Team', formatter::key_group(self::NAME, $row));
        $this->assertSame(get_string('keygroupgone', 'enrol_apply'), formatter::key_group(null, $row));

        $row->keygroupoutcome = submission::KEY_NONE;
        $this->assertSame('', formatter::key_group(null, $row));
    }

    /**
     * Both new columns reach the course report, through the entity.
     *
     * @return void
     */
    public function test_the_report_offers_both_columns(): void {
        $this->setAdminUser();
        $report = \core_reportbuilder\system_report_factory::create(
            \enrol_apply\reportbuilder\local\systemreports\course_applications::class,
            \context_course::instance($this->course->id)
        );

        $this->assertNotNull($report->get_column('submission:admissionroute'));
        $this->assertNotNull($report->get_column('submission:keygroup'));
        $this->assertNotNull($report->get_filter('submission:admissionroute'));
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
     * The group chooser reads each group's seats, says when one is full, and keeps it selectable.
     *
     * Changes that must make it fail: the seats left out of the option, or the full wording given
     * to a group with seats left.
     *
     * @return void
     */
    public function test_the_chooser_reads_each_groups_seats(): void {
        $this->require_groupdist();
        $this->seats($this->keyed, 1);
        $this->seats($this->listed, 5);
        groups_add_member($this->keyed->id, $this->teacher->id);

        $html = $this->review($this->waiting(submission::KEY_NONE));

        $full = get_string('decisiongroupfull', 'enrol_apply', (object) ['name' => self::NAME, 'members' => 1, 'seats' => 1]);
        $left = get_string('decisiongroupseats', 'enrol_apply', (object) ['name' => 'Everyone', 'members' => 0, 'seats' => 5]);
        $this->assertStringContainsString('<option value="' . $this->keyed->id . '">' . s($full) . '</option>', $html);
        $this->assertStringContainsString('<option value="' . $this->listed->id . '">' . s($left) . '</option>', $html);
        $this->assertStringContainsString('<option value="' . $this->other->id . '">Elsewhere</option>', $html);
    }

    /**
     * After approvals, the groups they took past their seats are reported, and a group exactly at
     * its seats is not.
     *
     * Changes that must make it fail: comparing members >= seats (a full group is not over), or
     * reading memberships this plugin did not stamp.
     *
     * @return void
     */
    public function test_approvals_that_overfill_a_group_are_reported(): void {
        $this->require_groupdist();
        $this->seats($this->keyed, 1);
        $this->seats($this->listed, 2);
        $first = $this->own_row($this->waiting(submission::KEY_NONE))->id;
        $second = $this->own_row($this->waiting(submission::KEY_NONE))->id;

        $this->plugin->confirm_enrolment([$first, $second], '', ['groups' => [$this->keyed->id, $this->listed->id]]);
        $over = groupseats::overfilled([$first, $second]);

        $this->assertSame([(int) $this->keyed->id], array_keys($over));
        $this->assertSame(2, $over[$this->keyed->id]->members);
        $this->assertSame(1, $over[$this->keyed->id]->seats);
        $this->assertSame(self::NAME, $over[$this->keyed->id]->name);
    }

    /**
     * Without local_groupdist nothing is over, whatever the memberships.
     *
     * @return void
     */
    public function test_nothing_is_over_without_seats(): void {
        $first = $this->own_row($this->waiting(submission::KEY_NONE))->id;
        $this->setAdminUser();
        $this->plugin->confirm_enrolment([$first], '', ['groups' => [$this->keyed->id]]);

        $absent = new class extends groupseats {
            /**
             * Stand in for a site without local_groupdist.
             *
             * @return bool Always false.
             */
            public static function available(): bool {
                return false;
            }
        };

        $this->assertSame([], $absent::overfilled([$first]));
    }
}
