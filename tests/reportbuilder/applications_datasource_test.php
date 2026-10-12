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
 * Tests for the site-level enrolment applications datasource.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\reportbuilder;

use context_course;
use context_system;
use core_reportbuilder\manager;
use core_reportbuilder\local\helpers\report as reporthelper;
use core_reportbuilder\system_report_factory;
use enrol_apply\local\submission;
use enrol_apply\reportbuilder\datasource\applications;
use enrol_apply\reportbuilder\local\systemreports\course_applications;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the site-level enrolment applications datasource.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(applications::class)]
final class applications_datasource_test extends \core_reportbuilder\tests\core_reportbuilder_testcase {
    /** @var \stdClass Course carrying the apply instance. */
    protected $course;

    /** @var \stdClass The enrol_apply instance record. */
    protected $instance;

    /** @var \enrol_apply_plugin The plugin. */
    protected $plugin;

    /**
     * Enable the plugin and give it a course with an instance.
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
     * The column stress helpers assert non-empty content inside a catch that re-reports any
     * Throwable as "Error for column 'X'", so on an empty table they fail blaming the first
     * column rather than the missing rows.
     *
     * @param string $snapshot Snapshot as a JSON envelope, written as answer rows.
     * @param int $status Status to record.
     * @return \stdClass The applicant.
     */
    protected function seed(string $snapshot = '', int $status = submission::STATUS_PENDING): \stdClass {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $submissionid = (int) $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $user->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => 0,
            'comment' => 'Please let me in',
            'status' => $status,
            'outcomemessage' => '',
            'timecreated' => time(),
            'timedecided' => $status === submission::STATUS_PENDING ? 0 : time(),
            'decidedby' => 0,
        ]);
        \enrol_apply\local\submission::write_answers(
            $submissionid,
            \enrol_apply\local\submission::parse_envelope($snapshot)
        );

        return $user;
    }

    /**
     * A snapshot holding one name part and one identity field.
     *
     * @return string JSON envelope.
     */
    protected function snapshot(): string {
        return json_encode([
            'version' => submission::SNAPSHOT_VERSION,
            'fields' => [
                ['key' => 's_firstname', 'label' => 'First name', 'value' => 'Terry'],
                ['key' => 's_city', 'label' => 'City', 'value' => 'Campinas'],
            ],
        ]);
    }

    /**
     * A user with moodle/user:viewalldetails allowed or prohibited at the system context.
     *
     * @param bool $grant Whether to allow the capability or prohibit it.
     * @return \stdClass The user.
     */
    protected function reader(bool $grant): \stdClass {
        $context = context_system::instance();
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();

        assign_capability(
            'moodle/user:viewalldetails',
            $grant ? CAP_ALLOW : CAP_PROHIBIT,
            $roleid,
            $context->id,
            true
        );
        role_assign($roleid, $user->id, $context->id);

        return $user;
    }

    /**
     * Build the datasource as a report instance.
     *
     * @return \core_reportbuilder\datasource The instance.
     */
    protected function instance(): \core_reportbuilder\datasource {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report([
            'name' => 'Applications',
            'source' => applications::class,
            'default' => 0,
        ]);

        return manager::get_report_from_persistent($report);
    }

    /**
     * The source is discovered from its path and namespace alone.
     *
     * Nothing registers it: manager::get_report_datasources() asks core_component for every class
     * in the reportbuilder\datasource namespace, so moving the file out of that directory loses
     * it without any error.
     *
     * @return void
     */
    public function test_the_datasource_is_discovered(): void {
        $this->setAdminUser();

        /* The result is $sources[component display name][class] = localised source name, so the
           class is a key of the inner arrays, not a value. */
        $sources = manager::get_report_datasources();
        $flat = array_merge(...array_values($sources));

        $this->assertArrayHasKey(applications::class, $flat);
        // Control: discovery works at all, so a failure above is about this class alone.
        $this->assertArrayHasKey(\core_user\reportbuilder\datasource\users::class, $flat);
    }

    /**
     * The default columns, filters and conditions a new report starts with.
     *
     * Only the defaults are pinned, not the full available set: core's user entity offers
     * user:moodlenetprofile on 5.1 and not on 5.2.
     *
     * @return void
     */
    public function test_the_defaults_a_new_report_starts_with(): void {
        $this->setAdminUser();
        $source = $this->instance();

        $this->assertSame([
            'user:fullname',
            'course:fullname',
            'submission:status',
            'submission:timecreated',
        ], $source->get_default_columns());

        $this->assertSame([
            'course:courseselector',
            'submission:status',
            'submission:timecreated',
        ], $source->get_default_filters());

        $this->assertSame(['submission:status'], $source->get_default_conditions());

        // The snapshot must never be a default column; see applications::get_default_columns().
        $this->assertNotContains('submission:snapshot', $source->get_default_columns());
    }

    /**
     * The course filter exists here and nowhere near the course report.
     *
     * Both halves in one test: the absence half alone would also pass against a course report
     * that failed to build, or a filter that exists nowhere.
     *
     * @return void
     */
    public function test_the_course_filter_exists_only_on_the_site_datasource(): void {
        $this->seed();
        $this->setAdminUser();

        $sitefilters = array_keys($this->instance()->get_filters());
        $this->assertContains('course:courseselector', $sitefilters);

        $coursereport = system_report_factory::create(
            course_applications::class,
            context_course::instance($this->course->id)
        );
        $coursefilters = array_map(
            static fn($filter) => $filter->get_unique_identifier(),
            array_values($coursereport->get_active_filters())
        );

        // The control: the course report really did build and really does offer filters.
        $this->assertNotEmpty($coursefilters);
        $this->assertContains('submission:status', $coursefilters);

        $this->assertNotContains('course:courseselector', $coursefilters);
        foreach ($coursefilters as $identifier) {
            $this->assertStringStartsNotWith('course:', $identifier);
        }
    }

    /**
     * A pseudonymised record is excluded even from a report that joins no user at all.
     *
     * The course report gets this from its INNER join onto {user}. A custom report emits an
     * entity's joins only for the elements in use, so a report of submission columns alone never
     * joins {user}; the exclusion must be a base condition, and this test selects no user column.
     *
     * @return void
     */
    public function test_a_pseudonymised_record_is_not_listed(): void {
        global $DB;

        $this->seed();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report([
            'name' => 'Submission columns only',
            'source' => applications::class,
            'default' => 0,
        ]);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'submission:status']);

        // The control: the row is listed while it still names somebody.
        $this->assertCount(1, $this->get_custom_report_content($report->get('id')));

        $DB->set_field('enrol_apply_submission', 'userid', 0, ['courseid' => $this->course->id]);

        $this->assertCount(0, $this->get_custom_report_content($report->get('id')));
    }

    /**
     * Without moodle/user:viewalldetails the snapshot column is absent, not blank.
     *
     * A custom report has no can_view() and its context is the system one, so the capability is
     * the whole gate. Because get_columns() filters on availability, the report editor cannot
     * offer the column and helpers\report::add_report_column() refuses it.
     *
     * @return void
     */
    public function test_the_snapshot_column_is_absent_without_viewalldetails(): void {
        $this->seed($this->snapshot());

        // The control: a reader who may see everyone's details gets the column.
        $this->setUser($this->reader(true));
        $this->assertArrayHasKey('submission:snapshot', $this->instance()->get_columns());

        $this->setUser($this->reader(false));
        $instance = $this->instance();
        $this->assertArrayNotHasKey('submission:snapshot', $instance->get_columns());

        // Nor can it be added directly, bypassing the picker.
        $this->expectException(\core\exception\invalid_parameter_exception::class);
        reporthelper::add_report_column(
            (int) $instance->get_report_persistent()->get('id'),
            'submission:snapshot'
        );
    }

    /**
     * Past the gate, the snapshot shows every field and not merely the names.
     *
     * The entity's fail-closed default shows the name parts only, so the city is what proves
     * this source opened the column. The name part is the control that tells a column rendering
     * nothing apart from one still on that default.
     *
     * @return void
     */
    public function test_the_snapshot_shows_every_field_past_the_gate(): void {
        $this->seed($this->snapshot());
        $this->setUser($this->reader(true));

        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report([
            'name' => 'With the snapshot',
            'source' => applications::class,
            'default' => 0,
        ]);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'submission:snapshot']);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);
        $rendered = (string) reset($content[0]);

        $this->assertStringContainsString('Terry', $rendered);
        $this->assertStringContainsString('Campinas', $rendered);
    }

    /**
     * Create a custom report from this source with the given columns.
     *
     * @param array $columns Column identifiers.
     * @return \core_reportbuilder\local\models\report The report persistent.
     */
    protected function custom_report(array $columns): \core_reportbuilder\local\models\report {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report([
            'name' => 'Answers',
            'source' => applications::class,
            'default' => 0,
        ]);
        foreach ($columns as $column) {
            $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => $column]);
        }

        return $report;
    }

    /**
     * A shared category with one text question, switched on for this instance.
     *
     * @param bool $enabled Whether to switch it on; a category nobody uses offers no column.
     * @return int The field id.
     */
    protected function shared_question(bool $enabled = true): int {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category(['component' => 'core_customfield', 'area' => 'shared', 'itemid' => 0]);
        $fieldid = (int) $generator->create_field([
            'categoryid' => $category->get('id'),
            'type' => 'text',
            'shortname' => 'shared' . random_string(6),
            'name' => 'Tax number',
        ])->get('id');
        if ($enabled) {
            (new \core_customfield\shared(0, (object) [
                'categoryid' => $category->get('id'),
                'component' => 'enrol_apply',
                'area' => \enrol_apply\customfield\application_handler::AREA,
                'itemid' => $this->instance->id,
            ]))->create();
        }

        return $fieldid;
    }

    /**
     * The answer entity lists one row per frozen value and filters on the answer.
     *
     * A record with no answers still lists once, as the LEFT join promises, so adding an answer
     * column never hides an application.
     *
     * @return void
     */
    public function test_the_answer_entity_lists_and_filters_the_answers(): void {
        $this->seed($this->snapshot());
        $this->seed();
        $this->setUser($this->reader(true));

        $report = $this->custom_report(['answer:question', 'answer:answer', 'answer:fieldkey']);
        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(3, $content);
        $values = array_map(static fn(array $row): string => implode('|', array_values($row)), $content);
        $this->assertContains('First name|Terry|s_firstname', $values);
        $this->assertContains('City|Campinas|s_city', $values);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $generator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => 'answer:answer']);
        $filtered = $this->get_custom_report_content($report->get('id'), 30, [
            'answer:answer_operator' => \core_reportbuilder\local\filters\text::IS_EQUAL_TO,
            'answer:answer_value' => 'Campinas',
        ]);
        $this->assertCount(1, $filtered);
        $this->assertSame('City', reset($filtered[0]));
    }

    /**
     * Without moodle/user:viewalldetails the answer entity offers nothing at all.
     *
     * Columns, filters and conditions alike: an answer row exists only where an applicant
     * answered, so even the field key column, or a condition on it, says who answered what.
     *
     * @return void
     */
    public function test_the_answer_entity_is_withheld_without_viewalldetails(): void {
        $this->seed($this->snapshot());

        $this->setUser($this->reader(true));
        $instance = $this->instance();
        $this->assertArrayHasKey('answer:answer', $instance->get_columns());
        $this->assertArrayHasKey('answer:answer', $instance->get_filters());
        $this->assertArrayHasKey('answer:fieldkey', $instance->get_conditions());

        $this->setUser($this->reader(false));
        $instance = $this->instance();
        // The control: the rest of the source is still offered.
        $this->assertArrayHasKey('submission:status', $instance->get_columns());
        foreach (['fieldkey', 'question', 'answer'] as $name) {
            $this->assertArrayNotHasKey('answer:' . $name, $instance->get_columns());
            $this->assertArrayNotHasKey('answer:' . $name, $instance->get_filters());
            $this->assertArrayNotHasKey('answer:' . $name, $instance->get_conditions());
        }
    }

    /**
     * A shared question some instance uses gets a site-wide column and filter; an unused one does not.
     *
     * @return void
     */
    public function test_a_shared_question_in_use_gets_a_column_and_a_filter(): void {
        $fieldid = $this->shared_question();
        $unused = $this->shared_question(false);
        $key = \enrol_apply\local\answers::key($fieldid);
        $this->seed((string) json_encode(['version' => submission::SNAPSHOT_VERSION, 'fields' => [
            ['key' => $key, 'label' => 'Tax number', 'value' => '123.456.789-09'],
        ]]));
        $this->seed((string) json_encode(['version' => submission::SNAPSHOT_VERSION, 'fields' => [
            ['key' => $key, 'label' => 'Tax number', 'value' => '987.654.321-00'],
        ]]));
        $this->setUser($this->reader(true));

        $instance = $this->instance();
        $identifier = 'submission:' . $key;
        $this->assertArrayHasKey($identifier, $instance->get_columns());
        $this->assertArrayHasKey($identifier, $instance->get_filters());
        $this->assertArrayHasKey($identifier, $instance->get_conditions());
        $this->assertSame('Tax number', $instance->get_column($identifier)->get_title());
        $this->assertArrayNotHasKey('submission:' . \enrol_apply\local\answers::key($unused), $instance->get_columns());

        $report = $this->custom_report([$identifier]);
        $first = static fn(array $row): string => (string) reset($row);
        $values = array_map($first, $this->get_custom_report_content($report->get('id')));
        $this->assertEqualsCanonicalizing(['123.456.789-09', '987.654.321-00'], $values);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $generator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => $identifier]);
        $filtered = $this->get_custom_report_content($report->get('id'), 30, [
            $identifier . '_operator' => \core_reportbuilder\local\filters\text::CONTAINS,
            $identifier . '_value' => '987',
        ]);
        $this->assertSame(['987.654.321-00'], array_map($first, $filtered));

        // Core's own checks over every column and aggregation, this question's included.
        $this->setAdminUser();
        $this->datasource_stress_test_columns(applications::class);
        $this->datasource_stress_test_columns_aggregation(applications::class);
    }

    /**
     * Without moodle/user:viewalldetails a shared question has no column, filter or condition.
     *
     * @return void
     */
    public function test_a_shared_question_is_withheld_without_viewalldetails(): void {
        $identifier = 'submission:' . \enrol_apply\local\answers::key($this->shared_question());

        $this->setUser($this->reader(true));
        $this->assertArrayHasKey($identifier, $this->instance()->get_columns());

        $this->setUser($this->reader(false));
        $instance = $this->instance();
        $this->assertArrayNotHasKey($identifier, $instance->get_columns());
        $this->assertArrayNotHasKey($identifier, $instance->get_filters());
        $this->assertArrayNotHasKey($identifier, $instance->get_conditions());
        // The control: the snapshot goes by the same gate, so the two cannot disagree.
        $this->assertArrayNotHasKey('submission:snapshot', $instance->get_columns());
    }

    /**
     * Every column renders, under every aggregation, and every condition applies.
     *
     * Runs core's three datasource stress helpers as admin: get_columns() filters on
     * availability, so without moodle/user:viewalldetails the helpers would skip the snapshot
     * column silently. The first assertion proves it is included.
     *
     * @return void
     */
    public function test_every_column_condition_and_aggregation_survives(): void {
        $this->seed($this->snapshot());
        $this->setAdminUser();

        $this->assertArrayHasKey('submission:snapshot', $this->instance()->get_columns());
        $this->assertNotEmpty($this->instance()->get_conditions());

        $this->datasource_stress_test_columns(applications::class);
        $this->datasource_stress_test_columns_aggregation(applications::class);
        $this->datasource_stress_test_conditions(applications::class, 'submission:status');
    }
}
