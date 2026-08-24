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
 * Tests for the enrol_apply privacy provider.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\privacy;

use context_course;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the enrol_apply privacy provider.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass Course carrying the apply instance. */
    protected $course;

    /** @var \stdClass The enrol_apply instance record. */
    protected $instance;

    /** @var \enrol_apply_plugin The plugin under test. */
    protected $plugin;

    /**
     * Create a course with an enabled apply enrolment instance.
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
        $this->course = $this->getDataGenerator()->create_course();
        $instanceid = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
    }

    /**
     * Record a pending application for a new user.
     *
     * @param string $comment Comment submitted with the application.
     * @param int $decidedby User recorded as having decided it, 0 for undecided.
     * @param \stdClass|null $instance Enrol instance to apply to, null for the default one.
     * @return \stdClass The applicant user record.
     */
    protected function create_application(
        string $comment,
        int $decidedby = 0,
        ?\stdClass $instance = null
    ): \stdClass {
        global $DB;

        $instance = $instance ?? $this->instance;
        $user = $this->getDataGenerator()->create_user();
        // No role, mirroring apply(): the role is assigned on approval, not on application.
        $this->plugin->enrol_user($instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $user->id, 'enrolid' => $instance->id],
            MUST_EXIST
        );
        $DB->insert_record('enrol_apply_applicationinfo', (object) [
            'userenrolmentid' => $ueid,
            'comment' => $comment,
        ]);
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $user->id,
            'enrolid' => $instance ? $instance->id : $this->instance->id,
            'userenrolmentid' => $ueid,
            'comment' => $comment,
            'userinfodata' => '',
            'status' => \enrol_apply\local\submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => time(),
            'timedecided' => 0,
            'decidedby' => $decidedby,
        ]);

        return $user;
    }

    /**
     * The provider declares the application info table.
     *
     * @return void
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('enrol_apply');
        $items = provider::get_metadata($collection)->get_collection();

        /* Order independent on purpose: the previous form indexed $items[0] by hand, so
           adding any table to the collection failed on every CI leg for a reason that had
           nothing to do with what the test was checking. */
        $names = array_map(static function ($item): string {
            return $item->get_name();
        }, $items);
        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing(
            ['enrol_apply_applicationinfo', 'enrol_apply_submission'],
            $names
        );

        $byname = array_combine($names, $items);
        $this->assertArrayHasKey('comment', $byname['enrol_apply_applicationinfo']->get_privacy_fields());

        /* Both personal-data roles are declared. decidedby is the one core's table coverage
           test cannot see for itself - it reads a column literally named userid or a
           single-field foreign key to user.id, and nothing else. */
        $submissionfields = $byname['enrol_apply_submission']->get_privacy_fields();
        $this->assertArrayHasKey('userid', $submissionfields);
        $this->assertArrayHasKey('decidedby', $submissionfields);
        $this->assertArrayHasKey('userinfodata', $submissionfields);
    }

    /**
     * An applicant's course context is reported, and nothing else is.
     *
     * @return void
     */
    public function test_get_contexts_for_userid(): void {
        $applicant = $this->create_application('Let me in');
        $bystander = $this->getDataGenerator()->create_user();

        $contexts = provider::get_contexts_for_userid((int) $applicant->id)->get_contextids();
        $this->assertEquals([context_course::instance($this->course->id)->id], array_map('intval', $contexts));

        $this->assertEmpty(provider::get_contexts_for_userid((int) $bystander->id)->get_contextids());
    }

    /**
     * Only applicants are listed for the course context.
     *
     * @return void
     */
    public function test_get_users_in_context(): void {
        $applicant = $this->create_application('Let me in');
        $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $context = context_course::instance($this->course->id);
        $userlist = new userlist($context, 'enrol_apply');
        provider::get_users_in_context($userlist);

        $this->assertEquals([(int) $applicant->id], array_map('intval', $userlist->get_userids()));
    }

    /**
     * The submitted comment is exported for the applicant.
     *
     * @return void
     */
    public function test_export_user_data(): void {
        $applicant = $this->create_application('Please approve me');
        $context = context_course::instance($this->course->id);

        $this->export_context_data_for_user((int) $applicant->id, $context, 'enrol_apply');

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $exported = $writer->get_data([
            get_string('privacy:applicationpath', 'enrol_apply'),
            get_string('privacy:methodpath', 'enrol_apply', $this->instance->id),
        ]);
        $this->assertEquals('Please approve me', $exported->comment);
    }

    /**
     * Purging a context removes every application recorded in it.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $this->create_application('First');
        $this->create_application('Second');
        $this->assertEquals(2, $DB->count_records('enrol_apply_applicationinfo'));
        $this->assertEquals(2, $DB->count_records('enrol_apply_submission'));

        provider::delete_data_for_all_users_in_context(context_course::instance($this->course->id));

        $this->assertEquals(0, $DB->count_records('enrol_apply_applicationinfo'));
        $this->assertEquals(0, $DB->count_records('enrol_apply_submission'));
    }

    /**
     * Deleting one user's data leaves the other applications untouched.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $applicant = $this->create_application('Mine');
        $other = $this->create_application('Theirs');
        $context = context_course::instance($this->course->id);

        provider::delete_data_for_user(new approved_contextlist($applicant, 'enrol_apply', [$context->id]));

        $remaining = $DB->get_records_sql(
            "SELECT ai.id, ue.userid
               FROM {enrol_apply_applicationinfo} ai
               JOIN {user_enrolments} ue ON ue.id = ai.userenrolmentid"
        );
        $this->assertCount(1, $remaining);
        $this->assertEquals((int) $other->id, (int) reset($remaining)->userid);
    }

    /**
     * Deleting a list of users removes exactly those users' applications.
     *
     * @return void
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $first = $this->create_application('First');
        $second = $this->create_application('Second');
        $third = $this->create_application('Third');
        $context = context_course::instance($this->course->id);

        provider::delete_data_for_users(
            new approved_userlist($context, 'enrol_apply', [$first->id, $third->id])
        );

        $remaining = $DB->get_records_sql(
            "SELECT ai.id, ue.userid
               FROM {enrol_apply_applicationinfo} ai
               JOIN {user_enrolments} ue ON ue.id = ai.userenrolmentid"
        );
        $this->assertCount(1, $remaining);
        $this->assertEquals((int) $second->id, (int) reset($remaining)->userid);
    }

    /**
     * A user who only ever decided applications still has data to export and erase.
     *
     * The second personal-data role. Nothing in the applicationinfo table names a decider, so
     * this whole path arrived with the durable record and is the reason the provider takes
     * two roles rather than one.
     *
     * @return void
     */
    public function test_export_covers_the_decider_role(): void {
        $decider = $this->getDataGenerator()->create_user();
        $applicant = $this->create_application('Decided by somebody else', (int) $decider->id);
        $context = context_course::instance($this->course->id);

        // The decider is reported as holding data in this course without having applied here.
        $contextids = provider::get_contexts_for_userid((int) $decider->id)->get_contextids();
        $this->assertEquals([$context->id], array_map('intval', $contextids));

        $this->export_context_data_for_user((int) $decider->id, $context, 'enrol_apply');

        $exported = writer::with_context($context)->get_data([
            get_string('privacy:trailpath', 'enrol_apply'),
            get_string('privacy:roledecider', 'enrol_apply'),
            get_string('privacy:recordpath', 'enrol_apply', $this->submission_id_of($applicant)),
        ]);
        $this->assertNotEmpty($exported);
        $this->assertEquals(get_string('privacy:roledecider', 'enrol_apply'), $exported->role);
    }

    /**
     * The applicant's own durable record is exported, with its comment and snapshot.
     *
     * The decider half of this was covered from the start and the applicant half was not, so
     * removing the applicant export call reddened nothing at all.
     *
     * @return void
     */
    public function test_export_covers_the_applicant_role(): void {
        $applicant = $this->create_application('Please approve me');
        $context = context_course::instance($this->course->id);
        $recordid = $this->submission_id_of($applicant);

        $this->export_context_data_for_user((int) $applicant->id, $context, 'enrol_apply');

        $exported = writer::with_context($context)->get_data([
            get_string('privacy:trailpath', 'enrol_apply'),
            get_string('privacy:roleapplicant', 'enrol_apply'),
            get_string('privacy:recordpath', 'enrol_apply', $recordid),
        ]);
        $this->assertNotEmpty($exported);
        $this->assertEquals(get_string('privacy:roleapplicant', 'enrol_apply'), $exported->role);
        $this->assertEquals('Please approve me', $exported->comment);
    }

    /**
     * A decider's export carries the decision and NOT the applicant's own content.
     *
     * The comment and the profile snapshot are the applicant's data. A subject access request
     * from the person who decided is about the decision they took, not about the person they
     * took it on; returning somebody else's free text under it is a disclosure nobody asked
     * for and the applicant never consented to.
     *
     * @return void
     */
    public function test_a_decider_export_does_not_carry_the_applicants_content(): void {
        $decider = $this->getDataGenerator()->create_user();
        $applicant = $this->create_application('My private reason for applying', (int) $decider->id);
        $context = context_course::instance($this->course->id);
        $recordid = $this->submission_id_of($applicant);

        $this->export_context_data_for_user((int) $decider->id, $context, 'enrol_apply');

        $exported = writer::with_context($context)->get_data([
            get_string('privacy:trailpath', 'enrol_apply'),
            get_string('privacy:roledecider', 'enrol_apply'),
            get_string('privacy:recordpath', 'enrol_apply', $recordid),
        ]);

        // The control: the decision itself IS exported, so this is not an empty export.
        $this->assertNotEmpty($exported);
        $this->assertEquals(get_string('privacy:roledecider', 'enrol_apply'), $exported->role);

        $this->assertObjectNotHasProperty('comment', $exported);
        $this->assertObjectNotHasProperty('submittedfields', $exported);
    }

    /**
     * A user's several records in one course each get their own place in the export.
     *
     * Cancelling and re-applying is the ordinary way to end up with more than one record for
     * the same course, user and enrolment method, so a path keyed on the method alone exports
     * the last one over all the others - the same defect this slice fixed for the pending
     * comments, arriving again by a different route.
     *
     * @return void
     */
    public function test_several_records_for_one_user_export_to_distinct_paths(): void {
        global $DB;

        $applicant = $this->create_application('The first attempt');
        $first = $this->submission_id_of($applicant);

        // A second record for the same person, instance and course, as re-applying produces.
        $second = (int) $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $applicant->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => 0,
            'comment' => 'The second attempt',
            'userinfodata' => '',
            'status' => \enrol_apply\local\submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => time(),
            'timedecided' => 0,
            'decidedby' => 0,
        ]);

        $context = context_course::instance($this->course->id);
        $this->export_context_data_for_user((int) $applicant->id, $context, 'enrol_apply');
        $writer = writer::with_context($context);

        $path = function (int $id): array {
            return [
                get_string('privacy:trailpath', 'enrol_apply'),
                get_string('privacy:roleapplicant', 'enrol_apply'),
                get_string('privacy:recordpath', 'enrol_apply', $id),
            ];
        };
        $this->assertEquals('The first attempt', $writer->get_data($path($first))->comment);
        $this->assertEquals('The second attempt', $writer->get_data($path($second))->comment);
    }

    /**
     * The id of an applicant's single durable record.
     *
     * @param \stdClass $user Applicant.
     * @return int Row id.
     */
    protected function submission_id_of(\stdClass $user): int {
        global $DB;

        $rows = $DB->get_records('enrol_apply_submission', ['userid' => $user->id]);
        $this->assertCount(1, $rows);

        return (int) reset($rows)->id;
    }

    /**
     * A decider is listed among the users holding data in the course.
     *
     * @return void
     */
    public function test_get_users_in_context_covers_the_decider_role(): void {
        $decider = $this->getDataGenerator()->create_user();
        $applicant = $this->create_application('Decided by somebody else', (int) $decider->id);
        $bystander = $this->getDataGenerator()->create_user();

        $userlist = new userlist(context_course::instance($this->course->id), 'enrol_apply');
        provider::get_users_in_context($userlist);
        $found = array_map('intval', $userlist->get_userids());

        $this->assertContains((int) $applicant->id, $found);
        $this->assertContains((int) $decider->id, $found);
        $this->assertNotContains((int) $bystander->id, $found);
    }

    /**
     * Erasing a decider clears their name from a record without destroying it.
     *
     * The two roles are erased differently on purpose. A record belongs to its APPLICANT and
     * carries that person's comment and profile snapshot, so erasing the person who merely
     * decided it must not take the record with it - that would destroy a third party's data
     * under a request the third party never made. All the decider can ask for is their own
     * name, which is exactly what course deletion does to the same column.
     *
     * @return void
     */
    public function test_delete_for_a_decider_clears_the_name_but_keeps_the_record(): void {
        global $DB;

        $decider = $this->getDataGenerator()->create_user();
        $applicant = $this->create_application('Decided by the erased user', (int) $decider->id);

        // The control: a record the erased user had nothing to do with must be untouched.
        $this->create_application('Nothing to do with them');

        $context = context_course::instance($this->course->id);
        provider::delete_data_for_user(
            new approved_contextlist($decider, 'enrol_apply', [$context->id])
        );

        $this->assertEquals(2, $DB->count_records('enrol_apply_submission'));
        $kept = $DB->get_record('enrol_apply_submission', ['userid' => $applicant->id], '*', MUST_EXIST);
        $this->assertEquals(0, (int) $kept->decidedby);
        // The applicant's own content is untouched: it was never the decider's to erase.
        $this->assertSame('Decided by the erased user', (string) $kept->comment);
    }

    /**
     * Erasing an applicant destroys their record whole.
     *
     * @return void
     */
    public function test_delete_for_an_applicant_removes_their_record(): void {
        global $DB;

        $decider = $this->getDataGenerator()->create_user();
        $applicant = $this->create_application('Erase me', (int) $decider->id);

        // The control: another applicant's record in the same course must survive.
        $bystander = $this->create_application('Leave me alone');

        $context = context_course::instance($this->course->id);
        provider::delete_data_for_user(
            new approved_contextlist($applicant, 'enrol_apply', [$context->id])
        );

        $this->assertEquals(0, $DB->count_records('enrol_apply_submission', ['userid' => $applicant->id]));
        $this->assertEquals(1, $DB->count_records('enrol_apply_submission', ['userid' => $bystander->id]));
    }

    /**
     * Erasing a list of users takes their own records and spares everybody else's.
     *
     * delete_data_for_users() had no assertion over the durable record at all, so the whole
     * of its behaviour there was unheld: deleting the call reddened nothing.
     *
     * @return void
     */
    public function test_delete_for_users_erases_the_durable_records_too(): void {
        global $DB;

        $decider = $this->getDataGenerator()->create_user();
        $erased = $this->create_application('Erase this one', (int) $decider->id);
        $kept = $this->create_application('Keep this one', (int) $decider->id);

        $context = context_course::instance($this->course->id);
        provider::delete_data_for_users(
            new approved_userlist($context, 'enrol_apply', [$erased->id])
        );

        $this->assertEquals(0, $DB->count_records('enrol_apply_submission', ['userid' => $erased->id]));

        // The control, and the whole point: the record of somebody NOT in the list survives,
        // with the decider's name still on it, because they were not in the list either.
        $survivor = $DB->get_record('enrol_apply_submission', ['userid' => $kept->id], '*', MUST_EXIST);
        $this->assertEquals($decider->id, (int) $survivor->decidedby);
    }

    /**
     * Two apply methods in one course export to paths that do not overwrite each other.
     *
     * A pre-existing defect: every application in a context was exported to the same path, so
     * a course carrying two apply methods exported the first and then replaced it with the
     * second, and the subject received half their data with nothing to say so.
     *
     * @return void
     */
    public function test_two_apply_methods_in_one_course_export_to_distinct_paths(): void {
        global $DB;

        $secondid = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $second = $DB->get_record('enrol', ['id' => $secondid], '*', MUST_EXIST);

        $applicant = $this->create_application('Through the first method');
        // The same person applying to both methods, which is what makes the paths collide.
        $this->plugin->enrol_user($second, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $secondid],
            MUST_EXIST
        );
        $DB->insert_record('enrol_apply_applicationinfo', (object) [
            'userenrolmentid' => $ueid,
            'comment' => 'Through the second method',
        ]);

        $context = context_course::instance($this->course->id);
        $this->export_context_data_for_user((int) $applicant->id, $context, 'enrol_apply');

        $writer = writer::with_context($context);
        $first = $writer->get_data([
            get_string('privacy:applicationpath', 'enrol_apply'),
            get_string('privacy:methodpath', 'enrol_apply', $this->instance->id),
        ]);
        $other = $writer->get_data([
            get_string('privacy:applicationpath', 'enrol_apply'),
            get_string('privacy:methodpath', 'enrol_apply', $secondid),
        ]);

        $this->assertEquals('Through the first method', $first->comment);
        $this->assertEquals('Through the second method', $other->comment);
    }

    /**
     * An applicant known only through the durable record is still listed.
     *
     * Every other fixture writes both tables, so the applicationinfo query alone satisfied
     * every assertion and the submission query was held by nothing: deleting it reddened no
     * test. This is the state that separates them - a record whose pending comment is gone,
     * which is what every approved, cancelled or unenrolled application looks like.
     *
     * @return void
     */
    public function test_get_users_in_context_finds_an_applicant_known_only_by_the_record(): void {
        global $DB;

        $applicant = $this->create_application('Approved long ago');
        $DB->delete_records('enrol_apply_applicationinfo');
        // The precondition: only the durable record can answer for this person now.
        $this->assertEquals(0, $DB->count_records('enrol_apply_applicationinfo'));
        $this->assertEquals(1, $DB->count_records('enrol_apply_submission', ['userid' => $applicant->id]));

        $userlist = new userlist(context_course::instance($this->course->id), 'enrol_apply');
        provider::get_users_in_context($userlist);

        $this->assertContains((int) $applicant->id, array_map('intval', $userlist->get_userids()));
    }

    /**
     * An undecided application does not report user zero as one of its people.
     *
     * decidedby is 0 on every application nobody has looked at yet, so a user list built from
     * that column names a user that does not exist unless something filters it. Nothing in
     * the provider does: userlist::add_from_sql() wraps the query in a JOIN against {user},
     * which is what keeps 0 out. This pins the consequence rather than the mechanism, so it
     * holds whoever is doing the filtering - and it goes red if the provider ever switches to
     * add_userids(), which does none.
     *
     * @return void
     */
    public function test_an_undecided_application_does_not_report_user_zero(): void {
        $applicant = $this->create_application('Nobody has looked at this yet');

        $userlist = new userlist(context_course::instance($this->course->id), 'enrol_apply');
        provider::get_users_in_context($userlist);
        $found = array_map('intval', $userlist->get_userids());

        $this->assertNotContains(0, $found);
        // The control: the query really did run and really did find the pending application.
        $this->assertContains((int) $applicant->id, $found);
    }

    /**
     * Pseudonymising a course's trail detaches it from the applicant, without deleting it.
     *
     * @return void
     */
    public function test_a_pseudonymised_row_no_longer_names_the_applicant(): void {
        global $DB;

        $applicant = $this->create_application('Before the course went');
        $this->assertEquals(1, $DB->count_records('enrol_apply_submission', ['userid' => $applicant->id]));

        \enrol_apply\local\submission::pseudonymise((int) $this->course->id);

        $this->assertEquals(0, $DB->count_records('enrol_apply_submission', ['userid' => $applicant->id]));

        /* The control: the record itself survives. This is about detaching a person from an
           audit row, not about deleting the row - deletion is what erasure does. */
        $this->assertEquals(1, $DB->count_records('enrol_apply_submission'));
    }
}
