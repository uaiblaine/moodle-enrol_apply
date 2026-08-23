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
     * @return \core_reportbuilder\system_report The report.
     */
    protected function report(): \core_reportbuilder\system_report {
        return system_report_factory::create(
            course_applications::class,
            context_course::instance($this->course->id)
        );
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
     * There is no core helper for this. The two the plan named -
     * datasource_stress_test_columns() and datasource_stress_test_columns_aggregation() - are
     * datasource-only: both hand their argument to the generator's create_report(), which
     * forces TYPE_CUSTOM_REPORT and instantiates the class as a datasource. Neither can build a
     * system report at all.
     *
     * @param \core_reportbuilder\system_report|null $report Report to read, null for the plain one.
     * @return array List of row objects carrying the formatted cell values.
     */
    protected function rows(?\core_reportbuilder\system_report $report = null): array {
        global $CFG;

        require_once($CFG->dirroot . '/reportbuilder/classes/table/system_report_table.php');

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
     * The mechanism is the report's INNER join onto {user}, not a base condition: no user
     * holds id 0. An explicit "userid <> 0" condition used to sit beside it and was removed
     * because deleting it reddened nothing at all. This test still holds the behaviour end to
     * end - it goes red if that join is ever widened to a LEFT one without a condition put
     * back - which is the property worth pinning, and the only one that was ever really pinned.
     *
     * @return void
     */
    public function test_a_pseudonymised_record_is_not_listed(): void {
        global $DB;

        $applicant = $this->seed('Before the course went');
        // The control: it is listed while it still names somebody.
        $this->setUser($this->reader());
        $this->assertCount(1, $this->rows());

        $DB->set_field('enrol_apply_submission', 'userid', 0, ['courseid' => $this->course->id]);

        $this->assertCount(0, $this->rows());
    }

    /**
     * The default columns and filters, in order.
     *
     * The order is the guard: 5.1 and 5.2 build entity columns differently, and a divergence
     * shows up here rather than in a rendering difference nobody notices.
     *
     * @return void
     */
    public function test_default_columns_and_filters(): void {
        /* Pinned rather than inherited: the site default for showuseridentity is a single
           field, which would leave the ordering WITHIN the identity block unasserted. Two
           fields make the block's own order part of the guard. */
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
            'applydecider:fullname',
        ], $this->filter_ids());
    }

    /**
     * Without the identity capability there is no identity column and no identity filter.
     *
     * Absence, not masking. A display callback would leave the column, its filter and its sort
     * in place, and all three are SQL: a reader recovers a hidden value by filtering on it and
     * reading the row count, or by sorting on it.
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
     * The formatter half of the masking, and the only masking in this report a display callback
     * is allowed to do - licensed by the test above, which holds that the column offers no
     * filter and no sort, so there is no SQL path around the callback.
     *
     * The name part is the control and is not decoration. Without it every assertion here would
     * pass just as well against a formatter that returned an empty cell, against one that
     * dropped every field, and against a report that had stopped rendering the snapshot at all.
     * With it, the claim is the one that matters: this reader sees exactly one of the two
     * fields, and it is the right one.
     *
     * The label is asserted absent alongside the value. A withheld field that still prints its
     * label tells the reader which applicants filled that field in, which is the presence
     * oracle the identity columns beside this one are shaped to avoid.
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
     * This is the path slice 8's site-level datasource takes. A datasource adds the entity's
     * columns directly and never calls set_callback(), so whatever the entity's own bare
     * registration does IS the masking for every custom report ever built on this entity.
     *
     * It is driven through core's column::format_value() rather than by calling the formatter,
     * because the defect this pins lived in core's calling convention and not in the
     * formatter's body. format_value() passes the registered argument ALWAYS, defaulting it to
     * null (reportbuilder/classes/local/report/column.php:733, and :508 / :520 for the
     * defaults - the same line numbers on 5.1 and 5.2), so a parameter default in the callback
     * is unreachable. A test that called the formatter with two arguments would exercise a
     * signature core never uses, and would have passed against the fail-open version this
     * replaced.
     *
     * Admin is deliberate. The point is not that this reader lacks a capability - they hold
     * every one there is. It is that nobody asked a context, and a masking decision nobody
     * made has to come out restrictive.
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
     * Core's enrolment status map would render this table's 2 as "Not current" and its 1 as
     * "Suspended" - legitimate core labels that are wrong here, and invisible in review.
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
     * Run through core's own export transform rather than through strip_tags(). They are not
     * the same function and the difference is the whole finding: format_text() runs
     * html_entity_decode() BEFORE removing tag-shaped runs, so an escaped "&lt;" becomes a
     * real "<" that then eats up to the next ">" on its line. A strip_tags() proxy models
     * none of that and passes against a formatter that emits "<br />", which measurably loses
     * data in a real CSV.
     *
     * Two fields, not one, because a single pair has no separator to lose.
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
     * An applicant cannot in fact type this. Every editable field on the form is PARAM_TEXT and
     * formslib cleans the whole submission before get_data(), so the form itself deletes the
     * tail - measured, clean_param('A<B and R&D', PARAM_TEXT) is 'A'. The route that reaches
     * this column is a RESTORE, which writes userinfodata verbatim out of an archive this site
     * did not produce. That is why the cell has to escape rather than trust, and why the
     * fixture is inserted directly rather than submitted.
     *
     * This test used to assert assertNotEmpty(), and it passed while the cell rendered a bare
     * "A" - the whole tail deleted by format_string()'s strip_tags(). It was written for this
     * exact defect and could not see it, which is the failure mode this repo keeps paying for:
     * an assertion weak enough to be satisfied by the bug. It now names the string it wants,
     * and asserts the same value again after the download transform, because escaping that
     * looks right on screen can still be lossy on the way out.
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
     * This replaces the core stress helpers, which cannot be used here: both are datasource-only
     * and force TYPE_CUSTOM_REPORT. Without something in their place, a column whose callback
     * mishandles a null or an unexpected type fails first for a user, on a page that passed
     * every static gate.
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
     * ENROL_APPLY_USER_WAIT is the value core knows nothing about, so it is the one a filter
     * silently drops. A boolean_select here - the obvious-looking choice for a status that
     * mostly reads pending-or-approved - would make every deferred application unfindable,
     * which is a defect this fleet's CLAUDE.md already records once.
     *
     * The pending record is the control: without it the assertion would pass against a filter
     * that returned nothing at all, and against one that was never applied.
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
     * The masked case is pinned elsewhere; this pins the other side of the same list. Only
     * s_firstname appeared in any fixture before, so dropping s_lastname - or any of the four
     * phonetic and alternate parts - withheld surnames from every non-identity reader with
     * nothing at all going red.
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
     * Both arrive from the client on the path the browser actually uses. The filterset
     * declares parameters as PARAM_RAW and set_filterset() json_decodes them straight into the
     * report, and the itemid is settable through the mobile web services - so the base
     * condition reads get_context()->instanceid and nothing else. This drives the report built
     * exactly as a forger would build it and asserts the row set did not move.
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
     * The icon on Enrolment methods and the node on the course settings navigation are the
     * only two ways anybody reaches this report, and neither is exercised by any other test:
     * deleting either capability check left the whole suite green while publishing a link to
     * the frozen profile snapshot of every applicant the course has ever had.
     *
     * The decider is the control that matters. Somebody with no capabilities at all would
     * prove only that the icons are gated on something; this actor holds the capability the
     * NEIGHBOURING icons are gated on, so it is the report's own gate being asserted and not
     * enrolment management in general.
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

        /* The control for the control: this actor really does get the icons their own
           capability earns, so an empty icon list cannot be what passed the assertion above. */
        $this->assertStringContainsString('/enrol/apply/manage.php', $icons);
    }

    /**
     * The report capability keeps its risk flag and stays off the editing teacher.
     *
     * The one guard in this slice that no behaviour can hold. Adding
     * 'editingteacher' => CAP_ALLOW to db/access.php - a one-line edit that reads as tidying an
     * inconsistency, since the other five capabilities in that file all have it - hands the
     * frozen profile snapshot of every applicant to every editing teacher on the site, and
     * every other test in this suite stays green because they all assign the capability to a
     * role of their own.
     *
     * Read through load_capability_def(), which parses db/access.php on each call. A test
     * phrased as "an editing teacher cannot open the report" would be the better shape and is
     * not available: archetype defaults are written into the test database at phpunit-init
     * time, so an edit to db/access.php would not be visible to it until the database was
     * rebuilt - and a mutation check that needs a rebuild to fail is one nobody runs.
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

        /* The control: the file really was read and really does carry the neighbours this
           declaration is deliberately unlike. If this ever fails the assertions above are
           reading something other than db/access.php. */
        $this->assertArrayHasKey('editingteacher', $definitions['enrol/apply:manageapplications']['archetypes']);
    }

    /**
     * The comment column reaches the reader, and the download, whole.
     *
     * The obvious implementation of this column is format_text(FORMAT_PLAIN), and it is the
     * wrong one: that branch is s() then nl2br() and strips nothing, so it gives this column
     * both defects the snapshot column is shaped to avoid - s() writes "&#039;" for an
     * apostrophe and ENT_COMPAT does not decode it back on the way out, and the injected
     * "<br />" supplies the ">" that lets a decoded "<" swallow the rest of its line.
     *
     * Both halves are asserted, because they fail on different inputs: the apostrophe fails
     * with no angle bracket present, and the angle bracket fails only when something later on
     * the line closes the run.
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
     * The method filter appears only where a course has more than one apply instance.
     *
     * A filter with one option cannot narrow anything and reads as a control that is broken.
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
