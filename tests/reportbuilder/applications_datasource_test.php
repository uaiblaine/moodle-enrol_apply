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
     * Both column stress helpers end in assertNotEmpty() on the report content, inside a try
     * whose catch re-reports any Throwable as "Error for column 'X'". An ExpectationFailedException
     * is a Throwable, so an empty table does not fail as "no rows" - it fails blaming whichever
     * column happened to be first. Seeding is a precondition of those helpers, not decoration.
     *
     * @param string $snapshot Stored JSON envelope.
     * @param int $status Status to record.
     * @return \stdClass The applicant.
     */
    protected function seed(string $snapshot = '', int $status = submission::STATUS_PENDING): \stdClass {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $user->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => 0,
            'comment' => 'Please let me in',
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
     * A user holding moodle/user:viewalldetails at the system context.
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
     * There is no db/reportbuilder.php: manager::get_report_datasources() asks core_component
     * for every class in the reportbuilder\datasource namespace. Moving the file out of that
     * directory is all it takes to lose it, with no error anywhere.
     *
     * @return void
     */
    public function test_the_datasource_is_discovered(): void {
        $this->setAdminUser();

        /* Two levels, and the class is a KEY at the inner one, not a value:
           manager::get_report_datasources() builds $sources[<component display name>][<class>]
           = <source name>. Asserting over the values would compare class names against
           localised titles and fail for a reason that has nothing to do with discovery. */
        $sources = manager::get_report_datasources();
        $flat = array_merge(...array_values($sources));

        $this->assertArrayHasKey(applications::class, $flat);
        /* The control: a classmap that had stopped working entirely would give a short or empty
           list, and the assertion above would fail looking exactly like a naming mistake. */
        $this->assertArrayHasKey(\core_user\reportbuilder\datasource\users::class, $flat);
    }

    /**
     * The default columns, filters and conditions a new report starts with.
     *
     * Only the DEFAULTS are pinned, never the full available set: core's user entity offers
     * user:moodlenetprofile on 5.1 and not on 5.2, so an ordered assertion over everything on
     * offer cannot be green on both branches.
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

        // The snapshot must never be a default column; see restrict_snapshot_column().
        $this->assertNotContains('submission:snapshot', $source->get_default_columns());
    }

    /**
     * The course filter exists here and nowhere near the course report.
     *
     * Both halves in one test on purpose. The absence half alone is unfalsifiable - it passes
     * against a course report that failed to build, and against a filter that never existed
     * anywhere. The presence half is what gives it something to be absent from.
     *
     * This replaces the plan's test_course_filter_is_absent_from_the_course_report, which was
     * never written.
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
     * The course report gets this for free from its INNER join onto {user}. A custom report
     * emits an entity's joins only for the elements actually in use, so a report built from
     * submission columns alone joins {user} not at all - which is why the exclusion here has to
     * be a base condition, and why this test deliberately selects no user column.
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
     * A custom report has no can_view() and its context is always the system one, so the
     * capability is the whole gate. Absence rather than masking, because get_columns() filters
     * on availability - which also means the report editor cannot offer it and
     * helpers\report::add_report_column() refuses it.
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

        // And it cannot be added by hand either, which is the half a picker test would miss.
        $this->expectException(\core\exception\invalid_parameter_exception::class);
        reporthelper::add_report_column(
            (int) $instance->get_report_persistent()->get('id'),
            'submission:snapshot'
        );
    }

    /**
     * Past the gate, the snapshot shows every field and not merely the names.
     *
     * The name part is the control: without it this would pass against a column that rendered
     * nothing at all, and against the entity's fail-closed default, which shows the name parts
     * and is what this source has to deliberately open.
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
     * Every column renders, under every aggregation, and every condition applies.
     *
     * The three core helpers. setAdminUser() is not incidental: get_columns() filters on
     * availability, so without the capability the snapshot column is not merely untested, it is
     * invisible to the helper and the run stays green having skipped it. The explicit assertion
     * below is what says the coverage happened rather than assuming it.
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
