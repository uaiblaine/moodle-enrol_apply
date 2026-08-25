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

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/apply/manage_table.php');

/**
 * What counts as an application awaiting a decision, and who may review one.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(queue::class)]
final class queue_test extends \advanced_testcase {
    /** @var \stdClass Course the apply instance belongs to. */
    private $course;

    /** @var \stdClass The enrol_apply instance record. */
    private $instance;

    /** @var \enrol_apply_plugin The plugin under test. */
    private $plugin;

    /**
     * A course carrying a single enabled apply enrolment instance.
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
     * A fresh applicant on this course's apply instance, and their user enrolment id.
     *
     * @param int $status One of ENROL_USER_SUSPENDED, ENROL_APPLY_USER_WAIT, ENROL_USER_ACTIVE.
     * @param string $comment Comment submitted with the application.
     * @return array Two-element array of the user record and the user enrolment id.
     */
    private function applicant(int $status = ENROL_USER_SUSPENDED, string $comment = 'Please let me in'): array {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $user->id, null, 0, 0, $status);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $user->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $DB->insert_record('enrol_apply_applicationinfo', (object) [
            'userenrolmentid' => $ueid,
            'comment' => $comment,
        ]);

        return [$user, $ueid];
    }

    /**
     * A teacher of this course, who holds the capability there and nowhere else.
     *
     * @return \stdClass The teacher user record.
     */
    private function teacher(): \stdClass {
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');

        return $teacher;
    }

    /**
     * A mentor of the given user, holding the capability in that user's own context only.
     *
     * The standard Moodle mentor pattern: a role assignable at CONTEXT_USER, assigned there.
     *
     * @param \stdClass $mentee User to mentor.
     * @return \stdClass The mentor user record.
     */
    private function mentor(\stdClass $mentee): \stdClass {
        $mentor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applymentor']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $mentor->id, \context_user::instance($mentee->id)->id);

        return $mentor;
    }

    /**
     * A pending application is found, with the fields a review page needs.
     *
     * @return void
     */
    public function test_a_pending_application_is_found(): void {
        [$user, $ueid] = $this->applicant();

        $application = queue::application($ueid);

        $this->assertNotNull($application);
        $this->assertEquals($ueid, (int) $application->id);
        $this->assertEquals($user->id, (int) $application->userid);
        $this->assertEquals($this->instance->id, (int) $application->enrolid);
        $this->assertEquals($this->course->id, (int) $application->courseid);
        $this->assertEquals($this->course->fullname, $application->coursename);
        $this->assertSame('Please let me in', $application->applycomment);
    }

    /**
     * So is one on the waiting list, which is invisible to core and easy to filter away.
     *
     * @return void
     */
    public function test_a_waiting_list_application_is_found(): void {
        [, $ueid] = $this->applicant(ENROL_APPLY_USER_WAIT);

        $this->assertNotNull(queue::application($ueid));
    }

    /**
     * An application that has been decided is not, and neither is one that never existed.
     *
     * Both give the same answer on purpose: a reader cannot act on the difference, and
     * separating them would answer "does user enrolment N exist?" for anybody who asks - which
     * matters here because the lookup runs before anybody has been authorised.
     *
     * @return void
     */
    public function test_a_decided_or_missing_application_is_not_found(): void {
        global $DB;

        [, $decided] = $this->applicant(ENROL_USER_ACTIVE);
        [, $gone] = $this->applicant();
        $DB->delete_records('user_enrolments', ['id' => $gone]);

        $this->assertNull(queue::application($decided));
        $this->assertNull(queue::application($gone));
        $this->assertNull(queue::application(99999999));
    }

    /**
     * Neither is an approved enrolment that has since expired.
     *
     * Same row shape as a fresh application to anything reading status alone, which is why the
     * predicate carries a second clause; the pending control proves the lookup runs at all.
     *
     * @return void
     */
    public function test_an_expired_enrolment_is_not_an_application(): void {
        global $DB;

        [, $expired] = $this->applicant();
        $DB->update_record('user_enrolments', (object) [
            'id' => $expired,
            'status' => ENROL_USER_SUSPENDED,
            'timestart' => time() - DAYSECS * 30,
            'timeend' => time() - DAYSECS,
        ]);
        [, $pending] = $this->applicant();

        $this->assertNull(queue::application($expired));
        $this->assertNotNull(queue::application($pending));
    }

    /**
     * The queue's listing and this lookup agree, because they are the same predicate.
     *
     * @return void
     */
    public function test_the_listing_and_the_lookup_agree(): void {
        global $DB;

        [, $pendingueid] = $this->applicant();
        [, $waitingueid] = $this->applicant(ENROL_APPLY_USER_WAIT);
        [, $expired] = $this->applicant();
        $DB->set_field('user_enrolments', 'timeend', time() - DAYSECS, ['id' => $expired]);
        [, $active] = $this->applicant(ENROL_USER_ACTIVE);

        $table = new \enrol_apply_manage_table($this->instance->id);
        $table->define_baseurl(new \moodle_url('/enrol/apply/manage.php'));
        ob_start();
        $table->out(50, false);
        ob_end_clean();
        $listed = array_map(static fn($row) => (int) $row->userenrolmentid, array_values($table->rawdata));

        $found = [];
        foreach ($DB->get_fieldset_select('user_enrolments', 'id', 'enrolid = ?', [$this->instance->id]) as $ueid) {
            if (queue::application((int) $ueid)) {
                $found[] = (int) $ueid;
            }
        }

        $this->assertEqualsCanonicalizing($listed, $found);
        /* The controls: something really was excluded, so agreeing is not agreeing on
           everything, and the two that survived are the two that should have. */
        $this->assertNotContains($expired, $found);
        $this->assertNotContains($active, $found);
        $this->assertEqualsCanonicalizing([$pendingueid, $waitingueid], $found);
    }

    /**
     * A teacher of the course may review an application made to it.
     *
     * This is the widening. Until now the review page required the capability in the
     * APPLICANT'S user context and nowhere else, which a course teacher does not hold -
     * measured on both branches - so a page meant for reviewing one application threw at
     * everybody except a mentor.
     *
     * @return void
     */
    public function test_a_course_teacher_may_review_an_application(): void {
        [, $ueid] = $this->applicant();
        $this->setUser($this->teacher());

        $context = queue::require_review_access(queue::application($ueid));

        $this->assertInstanceOf(\context_course::class, $context);
        $this->assertEquals($this->course->id, $context->instanceid);
    }

    /**
     * A mentor of the applicant still may, and in the applicant's own context.
     *
     * @return void
     */
    public function test_a_mentor_may_still_review_an_application(): void {
        [$applicant, $ueid] = $this->applicant();
        $this->setUser($this->mentor($applicant));

        $context = queue::require_review_access(queue::application($ueid));

        $this->assertInstanceOf(\context_user::class, $context);
        $this->assertEquals($applicant->id, $context->instanceid);
    }

    /**
     * So does an administrator, through the course context they inherit it in.
     *
     * @return void
     */
    public function test_an_administrator_may_review_an_application(): void {
        [, $ueid] = $this->applicant();
        $this->setAdminUser();

        $this->assertInstanceOf(
            \context_course::class,
            queue::require_review_access(queue::application($ueid))
        );
    }

    /**
     * Anybody else is refused.
     *
     * @return void
     */
    public function test_somebody_with_no_claim_is_refused(): void {
        [, $ueid] = $this->applicant();
        $outsider = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($outsider->id, $this->course->id, 'student');
        $this->setUser($outsider);

        $this->expectException(\required_capability_exception::class);
        queue::require_review_access(queue::application($ueid));
    }

    /**
     * A teacher of one course cannot review an application made to another.
     *
     * @return void
     */
    public function test_a_teacher_of_another_course_is_refused(): void {
        [, $ueid] = $this->applicant();
        $elsewhere = $this->getDataGenerator()->create_course();
        $stranger = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($stranger->id, $elsewhere->id, 'editingteacher');
        $this->setUser($stranger);

        $this->expectException(\required_capability_exception::class);
        queue::require_review_access(queue::application($ueid));
    }
}
