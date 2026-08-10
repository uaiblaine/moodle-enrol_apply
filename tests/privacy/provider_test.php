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

/**
 * Tests for the enrol_apply privacy provider.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_apply\privacy\provider
 */
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
     * @return \stdClass The applicant user record.
     */
    protected function create_application(string $comment): \stdClass {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $user->id, $this->instance->roleid, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $user->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $DB->insert_record('enrol_apply_applicationinfo', (object) [
            'userenrolmentid' => $ueid,
            'comment' => $comment,
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

        $this->assertCount(1, $items);
        $this->assertEquals('enrol_apply_applicationinfo', $items[0]->get_name());
        $this->assertArrayHasKey('comment', $items[0]->get_privacy_fields());
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
        $exported = $writer->get_data([get_string('privacy:applicationpath', 'enrol_apply')]);
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

        provider::delete_data_for_all_users_in_context(context_course::instance($this->course->id));

        $this->assertEquals(0, $DB->count_records('enrol_apply_applicationinfo'));
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
}
