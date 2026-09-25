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
 * Tests for the course applications report.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\reportbuilder;

use context_course;
use context_system;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\system_report_factory;
use enrol_apply\local\submission;
use enrol_apply\reportbuilder\local\entities\submission as submissionentity;
use enrol_apply\reportbuilder\local\formatters\submission as submissionformatter;
use enrol_apply\reportbuilder\local\systemreports\course_applications;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the course applications report.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_applications::class)]
#[CoversClass(submissionentity::class)]
#[CoversClass(submissionformatter::class)]
final class course_applications_test extends \core_reportbuilder\tests\core_reportbuilder_testcase {
    /** @var \stdClass Course carrying the apply instance. */
    protected $course;

    /** @var \stdClass The enrol_apply instance record. */
    protected $instance;

    /** @var \enrol_apply_plugin The plugin. */
    protected $plugin;

    /**
     * Create a course with an enabled apply instance.
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
     * Seed one application record.
     *
     * @param string $comment Comment submitted.
     * @param int $status Status to record.
     * @param string $snapshot Stored JSON envelope.
     * @param \stdClass|null $course Course to record it against, null for the default one.
     * @return \stdClass The applicant.
     */
    protected function seed(
        string $comment = 'Please let me in',
        int $status = submission::STATUS_PENDING,
        string $snapshot = '',
        ?\stdClass $course = null
    ): \stdClass {
        global $DB;

        $course = $course ?? $this->course;
        $instanceid = (int) $DB->get_field('enrol', 'id', ['courseid' => $course->id, 'enrol' => 'apply'], IGNORE_MULTIPLE);
        $user = $this->getDataGenerator()->create_user();

        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $course->id,
            'userid' => $user->id,
            'enrolid' => $instanceid,
            'userenrolmentid' => 0,
            'comment' => $comment,
            'userinfodata' => $snapshot,
            'status' => $status,
            'outcomemessage' => '',
            'timecreated' => time(),
            'timedecided' => $status === submission::STATUS_PENDING ? 0 : time(),
            'decidedby' => 0,
        ]);

        return $user;
    }

    /**
     * A user holding the report capability in the course.
     *
     * @param bool $identity Whether they may also see identity fields.
     * @return \stdClass The user.
     */
    protected function reader(bool $identity = true): \stdClass {
        $context = context_course::instance($this->course->id);
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();

        assign_capability('enrol/apply:viewreports', CAP_ALLOW, $roleid, $context->id, true);
        assign_capability(
            'moodle/site:viewuseridentity',
            $identity ? CAP_ALLOW : CAP_PROHIBIT,
            $roleid,
            $context->id,
            true
        );
        role_assign($roleid, $user->id, $context->id);

        return $user;
    }

    /**
     * A user who may decide applications but may not read the report.
     *
     * The default archetypes put exactly one role in this gap - editingteacher holds
     * manageapplications and does not hold viewreports - so this is not a contrived actor.
     *
     * @return \stdClass The user.
     */
    protected function decider(): \stdClass {
        $context = context_course::instance($this->course->id);
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();

        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, $context->id, true);
        assign_capability('enrol/apply:viewreports', CAP_PROHIBIT, $roleid, $context->id, true);
        role_assign($roleid, $user->id, $context->id);

        return $user;
    }

    /**
     * The URLs the plugin offers on the course settings navigation for this instance.
     *
     * @return array List of URL strings.
     */
    protected function navigation_urls(): array {
        $node = new \core\navigation\navigation_node('Enrolment methods');
        $this->plugin->add_course_navigation($node, $this->instance);

        $urls = [];
        foreach ($node->children as $child) {
            $action = $child->action();
            $urls[] = $action ? $action->out(false) : '';
        }

        return $urls;
    }

    /**
     * The report, built for the course under test.
     *
     * @param int $enrolid Enrol instance to identify the report by, 0 for the course-level one.
     * @return \core_reportbuilder\system_report The report.
     */
    protected function report(int $enrolid = 0): \core_reportbuilder\system_report {
        $context = context_course::instance($this->course->id);

        /* With an enrolid, through for_method() exactly as report.php builds it. Without one, the
           bare course-level report, which has no itemid and is therefore a different persistent. */
        return $enrolid
            ? course_applications::for_method($context, $enrolid)
            : system_report_factory::create(course_applications::class, $context);
    }

    /**
     * The unique identifiers of the report's active columns.
     *
     * @return array List of identifiers.
     */
    protected function column_ids(): array {
        return array_map(
            static fn($column) => $column->get_unique_identifier(),
            array_values($this->report()->get_active_columns())
        );
    }

    /**
     * The unique identifiers of the report's active filters.
     *
     * @return array List of identifiers.
     */
    protected function filter_ids(): array {
        return array_map(
            static fn($filter) => $filter->get_unique_identifier(),
            array_values($this->report()->get_active_filters())
        );
    }

    /**
     * The rendered rows of the report.
     *
     * There is no core helper for a system report: the datasource_stress_test_*() helpers build
     * through the generator's create_report(), which forces TYPE_CUSTOM_REPORT and instantiates
     * the class as a datasource.
     *
     * @param \core_reportbuilder\system_report|null $report Report to read, null for the plain one.
     * @return array List of row objects carrying the formatted cell values.
     */
    protected function rows(?\core_reportbuilder\system_report $report = null): array {
        $report = $report ?? $this->report();
        $table = \core_reportbuilder\table\system_report_table::create(
            (int) $report->get_report_persistent()->get('id'),
            []
        );
        $table->define_baseurl(new \moodle_url('/enrol/apply/report.php'));
        $table->setup();

        /* query_db() rather than out(): rawdata is a RECORDSET, and out() consumes and closes
           it, so a test reading it afterwards finds nothing. */
        $table->query_db(100, false);

        // The formatted cells come back keyed by column alias, not by unique identifier.
        $aliases = [];
        foreach ($report->get_active_columns_by_alias() as $alias => $column) {
            $aliases[$column->get_unique_identifier()] = $alias;
        }

        $rows = [];
        foreach ($table->rawdata as $record) {
            $formatted = $table->format_row($record);
            $row = new \stdClass();
            // The base fields first, so a column of the same name wins.
            foreach ((array) $record as $key => $value) {
                $row->{$key} = $value;
            }
            foreach ($aliases as $identifier => $alias) {
                $row->{$identifier} = $formatted[$alias] ?? null;
            }
            $rows[] = $row;
        }
        $table->close_recordset();

        return $rows;
    }

    /**
     * An applicant with a real enrolment, driven through the plugin's own path.
     *
     * Unlike seed(), whose userenrolmentid = 0 is the shape an unmappable restore leaves, the
     * record points at a real user_enrolments row, so the report's live-enrolment join finds it.
     *
     * @param int $status Enrolment status to leave the row at, or -1 to unenrol afterwards.
     * @param int $recordstatus Status to stamp on the durable record.
     * @param int $timeend Enrolment end, 0 for none.
     * @return \stdClass The applicant.
     */
    protected function seed_live(int $status, int $recordstatus, int $timeend = 0): \stdClass {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $user->id, null, 0, $timeend, ENROL_USER_SUSPENDED);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $user->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $user->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => $ueid,
            'comment' => 'Please let me in',
            'userinfodata' => '',
            'status' => $recordstatus,
            'outcomemessage' => '',
            'decidedgroups' => '',
            'decidedrole' => 0,
            'timecreated' => time(),
            'timedecided' => $recordstatus === submission::STATUS_PENDING ? 0 : time(),
            'decidedby' => 0,
        ]);

        if ($status < 0) {
            $DB->delete_records('user_enrolments', ['id' => $ueid]);
        } else {
            $DB->set_field('user_enrolments', 'status', $status, ['id' => $ueid]);
        }

        return $user;
    }

    /**
     * The outcome cell of the single row the report shows.
     *
     * @return string The rendered outcome.
     */
    protected function outcome_cell(): string {
        $rows = $this->rows();
        $this->assertCount(1, $rows, 'the fixture should produce exactly one row');

        return (string) $rows[0]->{'submission:outcome'};
    }

    /**
     * An approved applicant who is still enrolled reads as approved and enrolled.
     *
     * The control for the scenarios below: the column reports a healthy approval as such rather
     * than flagging every row.
     *
     * @return void
     */
    public function test_an_approved_and_enrolled_application_reads_as_enrolled(): void {
        $this->seed_live(ENROL_USER_ACTIVE, submission::STATUS_APPROVED);
        $this->setUser($this->reader());

        $this->assertSame(get_string('outcomeapproved', 'enrol_apply'), $this->outcome_cell());
    }

    /**
     * An approved applicant who was later unenrolled no longer reads as merely approved.
     *
     * The stored status is APPROVED here as in the test above, because nothing outside the
     * plugin's own decisions writes to the record and a record deliberately outlives its
     * enrolment (lib_test::test_a_submission_row_survives_unenrolment); only the live enrolment
     * tells the two apart.
     *
     * @return void
     */
    public function test_an_approved_then_unenrolled_application_says_so(): void {
        $this->seed_live(-1, submission::STATUS_APPROVED);
        $this->setUser($this->reader());

        $this->assertSame(get_string('outcomeunenrolled', 'enrol_apply'), $this->outcome_cell());
    }

    /**
     * A pending application whose enrolment was removed reads as never decided.
     *
     * Such a record has no user_enrolments row, so no queue lists it and nobody can decide it;
     * "Pending" would announce a decision that can never be taken.
     *
     * @return void
     */
    public function test_a_pending_application_that_was_unenrolled_reads_as_never_decided(): void {
        $this->seed_live(-1, submission::STATUS_PENDING);
        $this->setUser($this->reader());

        $this->assertSame(get_string('outcomeneverdecided', 'enrol_apply'), $this->outcome_cell());
    }

    /**
     * A manually suspended approval says so, which is what puts it back in the queue.
     *
     * The queue's predicate is "status != active AND (timeend = 0 OR timeend > now)", so a
     * suspension with no period puts the application back in the queue, and the report must not
     * go on saying "Approved".
     *
     * @return void
     */
    public function test_an_approved_then_suspended_application_says_so(): void {
        $this->seed_live(ENROL_USER_SUSPENDED, submission::STATUS_APPROVED);
        $this->setUser($this->reader());

        $this->assertSame(get_string('outcomesuspended', 'enrol_apply'), $this->outcome_cell());
    }

    /**
     * An expired approval is told apart from a manually suspended one.
     *
     * Both are status = suspended and only timeend separates them: a manual suspension returns
     * to the approval queue, while an expiry does not, because the queue's predicate excludes a
     * row whose period has run out.
     *
     * Changes that must make it fail: deleting the timeend arm of the outcome formatter, which
     * test_an_approved_then_suspended_application_says_so cannot detect.
     *
     * @return void
     */
    public function test_an_expired_approval_is_not_reported_as_a_suspension(): void {
        $this->seed_live(ENROL_USER_SUSPENDED, submission::STATUS_APPROVED, time() - DAYSECS);
        $this->setUser($this->reader());

        $this->assertSame(get_string('outcomeexpired', 'enrol_apply'), $this->outcome_cell());
    }

    /**
     * A suspended approval with a negative end reads as expired, because the queue excludes it.
     *
     * A restore passes an archived timeend through verbatim, so a negative one is reachable. The
     * queue's rule is "timeend = 0 OR timeend > now", which a negative end fails, so the queue
     * does not list the application and the report must not say it is back there.
     *
     * The control is the second half: with no end at all the same row is in the queue and reads
     * as suspended, so the first half cannot pass through a lookup that finds nothing anyway.
     *
     * @return void
     */
    public function test_a_negative_end_reads_as_expired_because_the_queue_excludes_it(): void {
        global $DB;

        $applicant = $this->seed_live(ENROL_USER_SUSPENDED, submission::STATUS_APPROVED);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $DB->set_field('user_enrolments', 'timeend', -1, ['id' => $ueid]);
        $this->setUser($this->reader());

        $this->assertNull(\enrol_apply\local\queue::application($ueid));
        $this->assertSame(get_string('outcomeexpired', 'enrol_apply'), $this->outcome_cell());

        $DB->set_field('user_enrolments', 'timeend', 0, ['id' => $ueid]);

        $this->assertNotNull(\enrol_apply\local\queue::application($ueid));
        $this->assertSame(get_string('outcomesuspended', 'enrol_apply'), $this->outcome_cell());
    }

    /**
     * The outcome of a suspended approval splits exactly where the queue does.
     *
     * Called on the formatter directly rather than through a rendered report, so the end that
     * equals now is still now when it is judged. Each case states the label it must produce and
     * checks that it agrees with {@see \enrol_apply\local\queue::is_awaiting_decision()}: back in
     * the queue reads as suspended, anything else as expired.
     *
     * @return void
     */
    public function test_the_suspended_outcome_splits_where_the_queue_does(): void {
        $suspended = get_string('outcomesuspended', 'enrol_apply');
        $expired = get_string('outcomeexpired', 'enrol_apply');
        $now = time();
        $cases = [
            'no end' => [0, $suspended],
            'an end in the future' => [$now + DAYSECS, $suspended],
            'an end equal to now' => [$now, $expired],
            'an end in the past' => [$now - DAYSECS, $expired],
            'a negative end' => [-1, $expired],
        ];

        foreach ($cases as $label => [$timeend, $expected]) {
            // Shaped as the report's row arrives: every column a string.
            $row = (object) [
                'outcomeueid' => '7',
                'outcomeenrolstatus' => (string) ENROL_USER_SUSPENDED,
                'outcomeenroltimeend' => (string) $timeend,
            ];
            $outcome = submissionformatter::outcome((string) submission::STATUS_APPROVED, $row);

            $this->assertSame($expected, $outcome, $label);

            $inqueue = \enrol_apply\local\queue::is_awaiting_decision((object) [
                'status' => ENROL_USER_SUSPENDED,
                'timeend' => $timeend,
            ]);
            $this->assertSame(
                $inqueue,
                $outcome === $suspended,
                $label . ': the report and the queue disagree'
            );
        }
    }

    /**
     * A record with no mappable enrolment reads "unknown", never "no longer enrolled".
     *
     * A restore writes userenrolmentid = 0 when it cannot map the enrolment, and zero finds
     * nothing in the join, just as a deleted enrolment does. The two must not be reported alike:
     * one means the applicant was removed, the other that the archive did not say.
     *
     * Changes that must make it fail: dropping the userenrolmentid = 0 check from either the
     * enrolment or the outcome formatter.
     *
     * @return void
     */
    public function test_a_record_with_no_mappable_enrolment_reads_unknown(): void {
        // The seed helper writes userenrolmentid = 0, which is precisely the restored shape.
        $this->seed('Please let me in', submission::STATUS_APPROVED);
        $this->setUser($this->reader());

        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $this->assertSame(
            get_string('enrolmentunknown', 'enrol_apply'),
            (string) $rows[0]->{'submission:enrolment'}
        );
        // And the outcome falls back to the stored decision rather than describing an enrolment.
        $this->assertSame(
            submission::status_label(submission::STATUS_APPROVED),
            (string) $rows[0]->{'submission:outcome'}
        );
    }

    /**
     * The outcome column is not sortable and carries no filter.
     *
     * Its value is computed in a display callback, which SQL filtering and sorting never reach:
     * a sort would order by a selected field and a filter would match the raw status, so either
     * would misreport. The sortable, filterable primitives are the status and enrolment columns.
     *
     * @return void
     */
    public function test_the_outcome_column_is_not_sortable_and_has_no_filter(): void {
        $this->seed();
        $this->setUser($this->reader());

        $report = $this->report();
        $this->assertFalse($report->get_column('submission:outcome')->get_is_sortable());
        $this->assertNotContains('submission:outcome', $this->filter_ids());

        // The control: a column that IS sortable, so the assertion is not passing vacuously.
        $this->assertTrue($report->get_column('submission:enrolment')->get_is_sortable());
    }

    /**
     * The capability is what admits a reader, and nothing else is.
     *
     * @return void
     */
    public function test_can_view_requires_the_capability(): void {
        $this->seed();

        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);
        $this->expectException(\core_reportbuilder\exception\report_access_exception::class);
        $this->report();
    }

    /**
     * The control for the test above: with the capability, the report builds.
     *
     * @return void
     */
    public function test_can_view_admits_a_reader_holding_the_capability(): void {
        $this->seed();

        $this->setUser($this->reader());
        $this->assertInstanceOf(\core_reportbuilder\system_report::class, $this->report());
    }

    /**
     * A report of one course's applications is refused outside a course.
     *
     * system_report_factory::create() builds the report in whatever context it is handed, and
     * the capability check would then be evaluated against the wrong thing.
     *
     * @return void
     */
    public function test_can_view_refuses_a_non_course_context(): void {
        $this->setAdminUser();

        $this->expectException(\core_reportbuilder\exception\report_access_exception::class);
        system_report_factory::create(course_applications::class, context_system::instance());
    }

    /**
     * The report shows this course's records and no others.
     *
     * @return void
     */
    public function test_the_report_is_scoped_by_the_context_instanceid(): void {
        $mine = $this->seed('MINE');

        $othercourse = $this->getDataGenerator()->create_course();
        $this->plugin->add_instance($othercourse, $this->plugin->get_instance_defaults());
        $theirs = $this->seed('THEIRS', submission::STATUS_PENDING, '', $othercourse);

        $this->setUser($this->reader());
        $userids = array_map(static fn($row) => (int) $row->userid, $this->rows());

        $this->assertContains((int) $mine->id, $userids);
        $this->assertNotContains((int) $theirs->id, $userids);
    }

    /**
     * A pseudonymised record belongs to nobody and is not listed.
     *
     * The mechanism is the report's INNER join onto {user}, not a base condition: no user holds
     * id 0. Changes that must make it fail: widening that join to a LEFT one without adding a
     * "userid <> 0" base condition.
     *
     * @return void
     */
    public function test_a_pseudonymised_record_is_not_listed(): void {
        global $DB;

        // The call is what seeds the record; the returned user is not needed here.
        $this->seed('Before the course went');
        // The control: it is listed while it still names somebody.
        $this->setUser($this->reader());
        $this->assertCount(1, $this->rows());

        $DB->set_field('enrol_apply_submission', 'userid', 0, ['courseid' => $this->course->id]);

        $this->assertCount(0, $this->rows());
    }

    /**
     * A second apply method on this course, and one application on it.
     *
     * @param string $name Instance name, so the two are told apart in a failure message.
     * @return array The instance record and the applicant seeded on it.
     */
    private function second_method(string $name = 'Second intake'): array {
        global $DB;

        $id = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $DB->set_field('enrol', 'name', $name, ['id' => $id]);
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        /* Named explicitly: create_user() draws names at random from small pools, so two
           generated applicants occasionally share a fullname, and the assertions that a row does
           NOT carry the other applicant's name then fail. A name outside the pools cannot
           collide with one drawn from them. */
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Second',
            'lastname' => 'Applicant',
        ]);
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $user->id,
            'enrolid' => $id,
            'userenrolmentid' => 0,
            'comment' => 'Please let me in',
            'userinfodata' => '',
            'status' => submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => time(),
            'timedecided' => 0,
            'decidedby' => 0,
        ]);

        return [$instance, $user];
    }

    /**
     * Arriving from a method's icon scopes the report to that method.
     *
     * Both directions are asserted: on a fixture where A's row is the only one the report would
     * show anyway, scoping to A and finding A's row proves nothing.
     *
     * @return void
     */
    public function test_arriving_from_a_methods_icon_scopes_the_report_to_it(): void {
        $first = $this->seed();
        [$second, $secondapplicant] = $this->second_method();
        $this->setUser($this->reader());

        // The control: unscoped, the report is the whole course and holds both.
        $this->assertCount(2, $this->rows());

        $firstid = (int) $this->instance->id;
        $this->assertTrue($this->report($firstid)->scope_to_method($firstid));
        $rows = $this->rows($this->report($firstid));
        $this->assertCount(1, $rows);
        $this->assertStringContainsString(fullname($first), (string) $rows[0]->{'user:fullnamewithlink'});

        $this->assertTrue($this->report((int) $second->id)->scope_to_method((int) $second->id));
        $rows = $this->rows($this->report((int) $second->id));
        $this->assertCount(1, $rows);
        $this->assertStringContainsString(
            fullname($secondapplicant),
            (string) $rows[0]->{'user:fullnamewithlink'}
        );
    }

    /**
     * Each method gets a report persistent of its own, which is what makes the scope stick.
     *
     * The stored scope is keyed on the report id, so two methods sharing one persistent would
     * share one scope; see course_applications::for_method().
     *
     * @return void
     */
    public function test_each_method_gets_its_own_report_identity(): void {
        $this->seed();
        [$second] = $this->second_method();
        $this->setUser($this->reader());

        $context = context_course::instance($this->course->id);
        $firstid = (int) course_applications::for_method($context, (int) $this->instance->id)
            ->get_report_persistent()->get('id');
        $secondid = (int) course_applications::for_method($context, (int) $second->id)
            ->get_report_persistent()->get('id');

        $this->assertNotSame($firstid, $secondid);
        // Stable: asking twice for the same method returns the same persistent, not a new one.
        $this->assertSame(
            $firstid,
            (int) course_applications::for_method($context, (int) $this->instance->id)
                ->get_report_persistent()->get('id')
        );
    }

    /**
     * Two methods keep independent scopes, so one report cannot answer the other's requests.
     *
     * Sorting, paging and download rebuild the report from its id alone and read the scope back
     * from that persistent's stored filter values; see course_applications::for_method().
     *
     * rows() calls system_report_table::create($reportid, []), the entry point and input the AJAX
     * request uses. It is not literally the AJAX branch, since the constructor defers loading
     * only when optional_param('info') is core_table_get_dynamic_table_content, but both rebuild
     * the report from the persistent the id names.
     *
     * @return void
     */
    public function test_two_methods_keep_independent_scopes(): void {
        $first = $this->seed();
        [$second, $secondapplicant] = $this->second_method();
        $this->setUser($this->reader());

        $firstid = (int) $this->instance->id;
        $this->report($firstid)->scope_to_method($firstid);
        // Opening the second report afterwards must not overwrite the first one's scope.
        $this->report((int) $second->id)->scope_to_method((int) $second->id);

        // The first method's report must still answer with its own row.
        $rows = $this->rows($this->report($firstid));
        $this->assertCount(1, $rows);
        $this->assertStringContainsString(fullname($first), (string) $rows[0]->{'user:fullnamewithlink'});
        $this->assertStringNotContainsString(
            fullname($secondapplicant),
            (string) $rows[0]->{'user:fullnamewithlink'}
        );

        // And the second's with its own, so this is not passing by ignoring the scope entirely.
        $rows = $this->rows($this->report((int) $second->id));
        $this->assertCount(1, $rows);
        $this->assertStringContainsString(
            fullname($secondapplicant),
            (string) $rows[0]->{'user:fullnamewithlink'}
        );
    }

    /**
     * Clearing the filter widens the report back to the course.
     *
     * The scope is a filter value rather than a base condition so that the reader can clear it.
     *
     * @return void
     */
    public function test_clearing_the_method_filter_widens_to_the_course(): void {
        $this->seed();
        $this->second_method();
        $this->setUser($this->reader());

        $this->report()->scope_to_method((int) $this->instance->id);
        // The precondition: it really is scoped, so what follows is about widening.
        $this->assertCount(1, $this->rows($this->report()));

        $report = $this->report();
        $report->set_filter_values(array_merge($report->get_filter_values(), [
            course_applications::METHOD_FILTER . '_operator' => select::ANY_VALUE,
        ]));

        $this->assertCount(2, $this->rows($this->report()));
    }

    /**
     * The url's method wins over one the reader had already chosen.
     *
     * Pins the merge direction: with the + operator instead of array_merge the stored value would
     * win; see course_applications::scope_to_method().
     *
     * @return void
     */
    public function test_the_url_method_wins_over_a_stored_one(): void {
        $this->seed();
        [$second, $secondapplicant] = $this->second_method();
        $this->setUser($this->reader());

        // The reader had chosen the second method by hand.
        $this->report()->scope_to_method((int) $second->id);
        $this->assertCount(1, $this->rows($this->report()));

        // Arriving from the FIRST method's icon must move them.
        $this->report()->scope_to_method((int) $this->instance->id);
        $rows = $this->rows($this->report());
        $this->assertCount(1, $rows);
        $this->assertStringNotContainsString(
            fullname($secondapplicant),
            (string) $rows[0]->{'user:fullnamewithlink'}
        );
    }

    /**
     * Scoping keeps the reader's other filters.
     *
     * set_filter_values() replaces the whole stored set, so a scope that did not merge would
     * reset the reader's status or date filters on every page load.
     *
     * @return void
     */
    public function test_scoping_keeps_the_readers_other_filters(): void {
        $this->seed();
        $this->second_method();
        $this->setUser($this->reader());

        $report = $this->report();
        $report->set_filter_values(['submission:status_operator' => select::EQUAL_TO,
            'submission:status_value' => submission::STATUS_CANCELLED]);

        $this->report()->scope_to_method((int) $this->instance->id);

        $values = $this->report()->get_filter_values();
        $this->assertSame(submission::STATUS_CANCELLED, (int) $values['submission:status_value']);
        $this->assertSame((int) $this->instance->id, (int) $values[course_applications::METHOD_FILTER . '_value']);
    }

    /**
     * A course with a single apply method has nothing to scope, and says so.
     *
     * The filter exists only on a course with more than one apply method, so scope_to_method()
     * must return false rather than store a value for a filter the report does not have.
     *
     * @return void
     */
    public function test_a_course_with_one_method_has_nothing_to_scope(): void {
        $this->seed();
        $this->setUser($this->reader());

        $this->assertFalse($this->report()->scope_to_method((int) $this->instance->id));
        $this->assertSame([], $this->report()->get_filter_values());
        // The control: the report still works and still shows the course.
        $this->assertCount(1, $this->rows());
    }

    /**
     * The identifier report.php scopes by is the one the filter actually carries.
     *
     * METHOD_FILTER repeats the identifier core builds from the entity and filter names; if
     * either is renamed, the scope silently stops applying.
     *
     * @return void
     */
    public function test_the_method_filter_identifier_is_the_one_the_page_scopes_by(): void {
        $this->seed();
        $this->second_method();
        $this->setUser($this->reader());

        $this->assertContains(course_applications::METHOD_FILTER, $this->filter_ids());
    }

    /**
     * The default columns and filters, in order.
     *
     * Pinned in order, so any change in how either branch assembles them shows up here.
     *
     * @return void
     */
    public function test_default_columns_and_filters(): void {
        /* Two identity fields rather than the site default's single one, so the order within
           the identity block is asserted too. */
        set_config('showuseridentity', 'email,idnumber');
        $this->seed();
        $this->setUser($this->reader());

        $this->assertSame([
            'user:fullnamewithlink',
            'user:email',
            'user:idnumber',
            'submission:status',
            'submission:timecreated',
            'submission:timedecided',
            'submission:comment',
            'submission:decisionnote',
            'submission:enrolment',
            'submission:outcome',
            'submission:snapshot',
            'applydecider:fullname',
        ], $this->column_ids());

        // The filters follow the columns, identity block included, and for the same reason.
        $this->assertSame([
            'user:fullname',
            'user:email',
            'user:idnumber',
            'submission:status',
            'submission:timecreated',
            'submission:timedecided',
            'submission:comment',
            'submission:decisionnote',
            'applydecider:fullname',
        ], $this->filter_ids());
    }

    /**
     * Without the identity capability there is no identity column and no identity filter.
     *
     * Absence, not masking: a display callback would leave the filter and the sort in place,
     * and a reader could recover a hidden value through either; see
     * course_applications::add_report_columns().
     *
     * @return void
     */
    public function test_an_identity_column_is_absent_without_viewuseridentity(): void {
        set_config('showuseridentity', 'email,idnumber');
        $this->seed();

        // The control: a reader who may see identity fields gets them.
        $this->setUser($this->reader(true));
        $withidentity = $this->column_ids();
        $this->assertContains('user:email', $withidentity);
        $this->assertContains('user:idnumber', $withidentity);

        $this->setUser($this->reader(false));
        $without = $this->column_ids();
        $this->assertNotContains('user:email', $without);
        $this->assertNotContains('user:idnumber', $without);
        $this->assertNotContains('user:email', $this->filter_ids());
    }

    /**
     * The identity column is absent even when no row holds a value for it.
     *
     * A marker that appears only where there is data is a presence oracle.
     *
     * @return void
     */
    public function test_an_identity_column_is_absent_even_for_rows_that_hold_no_value(): void {
        global $DB;

        set_config('showuseridentity', 'idnumber');
        $applicant = $this->seed();
        $DB->set_field('user', 'idnumber', '', ['id' => $applicant->id]);

        $this->setUser($this->reader(false));
        $this->assertNotContains('user:idnumber', $this->column_ids());
    }

    /**
     * The snapshot column carries no filter and cannot be sorted.
     *
     * This is the precondition that makes masking inside its formatter sound. If it goes red,
     * the formatter's masking is unsound and must move to set_is_available().
     *
     * @return void
     */
    public function test_the_snapshot_column_has_no_filter_and_is_not_sortable(): void {
        $this->seed();
        $this->setUser($this->reader());

        $this->assertNotContains('submission:snapshot', $this->filter_ids());

        $report = $this->report();
        $column = $report->get_column('submission:snapshot');
        $this->assertNotNull($column);
        $this->assertFalse($column->get_is_sortable());
    }

    /**
     * The snapshot shows the fields this reader may see, and omits the ones they may not.
     *
     * Masking in a display callback is sound here only because the column has no filter and no
     * sort (the test above).
     *
     * The name part is the control: without it, the absence assertions would also pass against
     * an empty cell or a column that stopped rendering. The label is asserted absent as well,
     * because a withheld field that still prints its label reveals which applicants filled it in.
     *
     * @return void
     */
    public function test_the_snapshot_column_omits_fields_the_reader_may_not_see(): void {
        $snapshot = json_encode([
            'version' => submission::SNAPSHOT_VERSION,
            'fields' => [
                ['key' => 's_firstname', 'label' => 'First name', 'value' => 'Terry'],
                ['key' => 's_city', 'label' => 'City', 'value' => 'Campinas'],
            ],
        ]);
        $this->seed('With a city', submission::STATUS_PENDING, $snapshot);

        // The control: a reader who may see identity fields gets both fields.
        $this->setUser($this->reader(true));
        $withidentity = (string) $this->rows()[0]->{'submission:snapshot'};
        $this->assertStringContainsString('Terry', $withidentity);
        $this->assertStringContainsString('Campinas', $withidentity);

        $this->setUser($this->reader(false));
        $without = (string) $this->rows()[0]->{'submission:snapshot'};

        // The name part survives, so an empty cell cannot pass this test.
        $this->assertStringContainsString('Terry', $without);
        $this->assertStringNotContainsString('Campinas', $without);
        $this->assertStringNotContainsString('City', $without);
    }

    /**
     * An entity column used without the report shows the names and nothing else.
     *
     * The entity's own bare registration is what any datasource reusing it gets, unless the
     * datasource registers an argument itself as applications::restrict_snapshot_column() does.
     *
     * Driven through core's column::format_value(), which always passes the registered argument
     * (null when none was registered), rather than by calling the formatter with two arguments,
     * a signature core never uses; see submissionformatter::snapshot().
     *
     * As admin, so the restriction cannot come from a missing capability: with no context
     * supplied, the column must default to the names only.
     *
     * @return void
     */
    public function test_an_entity_column_used_without_the_report_shows_names_only(): void {
        $snapshot = json_encode([
            'version' => submission::SNAPSHOT_VERSION,
            'fields' => [
                ['key' => 's_firstname', 'label' => 'First name', 'value' => 'Terry'],
                ['key' => 's_city', 'label' => 'City', 'value' => 'Campinas'],
            ],
        ]);

        $this->setAdminUser();
        $column = (new submissionentity())->initialise()->get_column('snapshot');

        /* The row core would hand it: one entry per declared field, keyed by the alias
           get_fields() writes after AS. Read off the column rather than hardcoded, because
           that alias carries the column's index. */
        $row = [];
        foreach ($column->get_fields() as $field) {
            $row[trim(substr($field, strripos($field, ' as ') + 4))] = $snapshot;
        }

        $rendered = (string) $column->format_value($row);

        // The control: the column really did render, so an empty cell cannot pass this.
        $this->assertStringContainsString('Terry', $rendered);
        $this->assertStringNotContainsString('Campinas', $rendered);
        $this->assertStringNotContainsString('City', $rendered);
    }

    /**
     * The waiting list gets the plugin's own label, never core's enrolment vocabulary.
     *
     * Core's enrolment status labels would call this table's 2 (waiting) "Not current" and its 1
     * (approved) "Suspended".
     *
     * @return void
     */
    public function test_status_column_labels_the_waiting_list_correctly(): void {
        $this->seed('On the list', submission::STATUS_WAITING);
        $this->setUser($this->reader());

        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $rendered = (string) $rows[0]->{'submission:status'};

        $this->assertSame(get_string('submissionstatuswaiting', 'enrol_apply'), $rendered);
        $this->assertStringNotContainsStringIgnoringCase('not current', $rendered);
        $this->assertStringNotContainsStringIgnoringCase('suspended', $rendered);
    }

    /**
     * The snapshot's pairs survive the download with their separator intact.
     *
     * Run through core's export transform, base_export_format::format_text(), rather than a
     * strip_tags() proxy: it decodes entities before removing tag-shaped runs, so an escaped
     * "&lt;" becomes a real "<" that eats up to the next ">" on its line.
     *
     * Two fields, because a single pair has no separator to lose.
     *
     * @return void
     */
    public function test_the_snapshot_pairs_survive_the_download(): void {
        $snapshot = json_encode([
            'version' => submission::SNAPSHOT_VERSION,
            'fields' => [
                ['key' => 's_city', 'label' => 'City', 'value' => 'Campinas'],
                ['key' => 's_department', 'label' => 'Department', 'value' => 'R&D'],
            ],
        ]);
        $this->seed('With a city', submission::STATUS_PENDING, $snapshot);

        $this->setUser($this->reader());
        $rendered = (string) $this->rows()[0]->{'submission:snapshot'};

        $table = new \core_table\flexible_table('enrol_apply_export_probe');
        $exported = (new \core_table\base_export_format($table))->format_text($rendered);

        $this->assertSame("City: Campinas\nDepartment: R&D", $exported);
    }

    /**
     * A snapshot value holding a raw angle bracket reaches the reader whole.
     *
     * The form cannot produce this value: its editable fields are PARAM_TEXT, and
     * clean_param('A<B and R&D', PARAM_TEXT) is 'A'. A restore can, because it writes
     * userinfodata verbatim from the archive, so the cell must escape rather than trust, and the
     * fixture is inserted directly.
     *
     * The exact string is asserted, on screen and again after the download transform: a weaker
     * assertion passes against format_string(), whose strip_tags() renders only "A", and escaping
     * that looks right on screen can still lose data on export.
     *
     * @return void
     */
    public function test_a_raw_angle_bracket_in_a_snapshot_value_reaches_the_reader_whole(): void {
        $snapshot = json_encode([
            'version' => submission::SNAPSHOT_VERSION,
            'fields' => [['key' => 's_city', 'label' => 'City', 'value' => "A<B and O'Brien & R&D"]],
        ]);
        $this->seed('Angle bracket', submission::STATUS_PENDING, $snapshot);

        $this->setUser($this->reader());
        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $rendered = (string) $rows[0]->{'submission:snapshot'};

        // On screen: escaped, and therefore safe, but nothing dropped.
        $this->assertSame("City: A&lt;B and O'Brien &amp; R&amp;D", $rendered);

        // And on the way out: decoded back to exactly what was typed.
        $table = new \core_table\flexible_table('enrol_apply_export_probe');
        $exported = (new \core_table\base_export_format($table))->format_text($rendered);
        $this->assertSame("City: A<B and O'Brien & R&D", $exported);
    }

    /**
     * Every column renders for every stored status without raising anything.
     *
     * Stands in for core's stress helpers, which cannot build a system report (see rows()), to
     * catch a column callback that mishandles a null or an unexpected value.
     *
     * @return void
     */
    public function test_every_column_renders_for_every_status(): void {
        foreach (submission::STATUSES as $status) {
            $this->seed('Status ' . $status, $status);
        }
        // And one whose optional values are all empty.
        $this->seed('', submission::STATUS_PENDING, '');

        $this->setUser($this->reader());
        $rows = $this->rows();
        $this->assertCount(count(submission::STATUSES) + 1, $rows);

        foreach ($this->column_ids() as $columnid) {
            foreach ($rows as $row) {
                $this->assertIsString((string) ($row->{$columnid} ?? ''));
            }
        }
    }

    /**
     * The status filter reaches the waiting list.
     *
     * A two-state filter (a boolean_select, say), the obvious shape for a status that mostly reads
     * pending or approved, would leave waiting-list records unfindable.
     *
     * The pending record is the control: without it, the assertion would also pass if the filter
     * were never applied.
     *
     * @return void
     */
    public function test_the_status_filter_reaches_the_waiting_list(): void {
        $this->seed('Still in the queue', submission::STATUS_PENDING);
        $waiting = $this->seed('Deferred', submission::STATUS_WAITING);

        $this->setUser($this->reader());

        // The control: unfiltered, the report lists both.
        $this->assertCount(2, $this->rows());

        $this->report()->set_filter_values([
            'submission:status_operator' => select::EQUAL_TO,
            'submission:status_value' => submission::STATUS_WAITING,
        ]);

        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $this->assertSame((int) $waiting->id, (int) $rows[0]->userid);
    }

    /**
     * Every name part survives for a reader without the identity capability.
     *
     * The masked case is pinned elsewhere; this pins the other side of the same list, so
     * dropping any of the six name parts from the formatter's name list fails here.
     *
     * @return void
     */
    public function test_every_name_part_is_visible_without_the_identity_capability(): void {
        $parts = [
            's_firstname' => 'Terry',
            's_lastname' => 'Teacher',
            's_firstnamephonetic' => 'TEH-ree',
            's_lastnamephonetic' => 'TEE-cher',
            's_middlename' => 'Quinn',
            's_alternatename' => 'Tel',
        ];
        $fields = [];
        foreach ($parts as $key => $value) {
            $fields[] = ['key' => $key, 'label' => $key, 'value' => $value];
        }
        $fields[] = ['key' => 's_city', 'label' => 'City', 'value' => 'Campinas'];

        $snapshot = json_encode(['version' => submission::SNAPSHOT_VERSION, 'fields' => $fields]);
        $this->seed('Every name part', submission::STATUS_PENDING, $snapshot);

        $this->setUser($this->reader(false));
        $rendered = (string) $this->rows()[0]->{'submission:snapshot'};

        foreach ($parts as $key => $value) {
            $this->assertStringContainsString($value, $rendered, $key . ' was withheld');
        }
        // The control: this reader really is the restricted one.
        $this->assertStringNotContainsString('Campinas', $rendered);
    }

    /**
     * A forged itemid and forged parameters cannot widen the report.
     *
     * Both come from the client: set_filterset() json_decodes the parameters straight into the
     * report, and the itemid is settable through the mobile web services. So the base condition
     * reads get_context()->instanceid and nothing else; this builds the report as a forger would
     * and asserts the row set did not move.
     *
     * @return void
     */
    public function test_a_forged_itemid_or_parameter_cannot_widen_the_report(): void {
        $mine = $this->seed('MINE');

        $othercourse = $this->getDataGenerator()->create_course();
        $this->plugin->add_instance($othercourse, $this->plugin->get_instance_defaults());
        $theirs = $this->seed('THEIRS', submission::STATUS_PENDING, '', $othercourse);

        $this->setUser($this->reader());

        $forged = system_report_factory::create(
            course_applications::class,
            context_course::instance($this->course->id),
            '',
            '',
            (int) $othercourse->id,
            ['courseid' => (int) $othercourse->id, 'id' => (int) $othercourse->id]
        );

        $userids = array_map(static fn($row) => (int) $row->userid, $this->rows($forged));

        $this->assertContains((int) $mine->id, $userids);
        $this->assertNotContains((int) $theirs->id, $userids);
    }

    /**
     * Both entry points to the report are gated on the report's own capability.
     *
     * The icon on Enrolment methods and the node on the course settings navigation are the only
     * two ways to reach this report, and no other test exercises them.
     *
     * The decider holds the capability the manage icon beside it is gated on, so this asserts the
     * report's own gate rather than a gate on enrolment management in general.
     *
     * @return void
     */
    public function test_both_report_entry_points_are_gated_on_the_report_capability(): void {
        $reporturl = (new \moodle_url('/enrol/apply/report.php', ['id' => $this->instance->id]))->out(false);

        $this->setUser($this->reader());
        $this->assertStringContainsString($reporturl, implode('', $this->plugin->get_action_icons($this->instance)));
        $this->assertContains($reporturl, $this->navigation_urls());

        $this->setUser($this->decider());
        $icons = implode('', $this->plugin->get_action_icons($this->instance));
        $this->assertStringNotContainsString($reporturl, $icons);
        $this->assertNotContains($reporturl, $this->navigation_urls());

        // Control: the decider does get the manage icon, so an empty icon list is not what passed above.
        $this->assertStringContainsString('/enrol/apply/manage.php', $icons);
    }

    /**
     * The report capability keeps its risk flag and stays off the editing teacher.
     *
     * Adding 'editingteacher' => CAP_ALLOW to db/access.php looks like tidying, since four of the
     * five other capabilities there have it, but hands every applicant's profile snapshot to
     * every editing teacher. Every other test here assigns the capability to a role of its own,
     * so none would notice.
     *
     * Read through load_capability_def(), which parses db/access.php on each call: a behavioural
     * test would read the archetype defaults installed when the test database was initialised,
     * and would not see an edit to db/access.php until it was rebuilt.
     *
     * @return void
     */
    public function test_the_report_capability_is_risk_flagged_and_manager_only(): void {
        $definitions = load_capability_def('enrol_apply');
        $this->assertArrayHasKey('enrol/apply:viewreports', $definitions);
        $definition = $definitions['enrol/apply:viewreports'];

        $this->assertSame(['manager' => CAP_ALLOW], $definition['archetypes']);
        $this->assertSame(RISK_PERSONAL, $definition['riskbitmask'] & RISK_PERSONAL);
        $this->assertSame(CONTEXT_COURSE, $definition['contextlevel']);

        /* Control: the definitions really come from db/access.php, where a neighbouring
           capability does grant editingteacher. */
        $this->assertArrayHasKey('editingteacher', $definitions['enrol/apply:manageapplications']['archetypes']);
    }

    /**
     * The comment column reaches the reader, and the download, whole.
     *
     * format_text(FORMAT_PLAIN) would be wrong here: it is s() then nl2br(), so an apostrophe
     * becomes "&#039;", which the export's ENT_COMPAT decoding leaves in place, and the added
     * "<br />" supplies the ">" that lets a decoded "<" swallow the rest of its line.
     *
     * Both are asserted, because they fail on different inputs: the apostrophe with no angle
     * bracket present, the angle bracket only when something later on the line closes the run.
     *
     * @return void
     */
    public function test_the_comment_column_reaches_the_reader_and_the_download_whole(): void {
        $comment = "O'Brien asks: is A<B?\nAnd R&D too";
        $this->seed($comment);

        $this->setUser($this->reader());
        $rendered = (string) $this->rows()[0]->{'submission:comment'};

        // On screen: escaped, so safe, but nothing dropped and no markup added.
        $this->assertStringNotContainsString('<br', $rendered);
        $this->assertStringNotContainsString('&#039;', $rendered);

        $table = new \core_table\flexible_table('enrol_apply_export_probe');
        $exported = (new \core_table\base_export_format($table))->format_text($rendered);
        $this->assertSame($comment, $exported);
    }

    /**
     * The decision note reaches the reader, and the download, whole.
     *
     * Same formatter and same two failure modes as the comment column; see
     * test_the_comment_column_reaches_the_reader_and_the_download_whole().
     *
     * @return void
     */
    public function test_the_decision_note_column_reaches_the_reader_and_the_download_whole(): void {
        global $DB;

        $note = "O'Brien says: hold while A<B\nand R&D confirm";
        $applicant = $this->seed();
        $DB->set_field('enrol_apply_submission', 'decisionnote', $note, ['userid' => $applicant->id]);

        $this->setUser($this->reader());
        $rendered = (string) $this->rows()[0]->{'submission:decisionnote'};

        $this->assertStringNotContainsString('<br', $rendered);
        $this->assertStringNotContainsString('&#039;', $rendered);

        $table = new \core_table\flexible_table('enrol_apply_note_export_probe');
        $exported = (new \core_table\base_export_format($table))->format_text($rendered);
        $this->assertSame($note, $exported);
    }

    /**
     * The method filter appears only where a course has more than one apply instance.
     *
     * A filter offering a single option reads as a broken control; see
     * course_applications::add_report_filters().
     *
     * @return void
     */
    public function test_the_method_filter_appears_only_with_more_than_one_instance(): void {
        $this->seed();
        $this->setUser($this->reader());
        $this->assertNotContains('submission:method', $this->filter_ids());

        $this->setAdminUser();
        $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());

        $this->setUser($this->reader());
        $this->assertContains('submission:method', $this->filter_ids());
    }
}
