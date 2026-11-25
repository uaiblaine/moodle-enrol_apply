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

namespace enrol_apply\privacy;

use context_course;
use context_user;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_reportbuilder\local\helpers\user_filter_manager;
use core_reportbuilder\local\models\report;
use core_reportbuilder\local\models\user_filter;
use core_reportbuilder\local\report\base;
use core_reportbuilder\manager;
use core_reportbuilder\system_report_factory;
use enrol_apply\local\reportpersistents;
use enrol_apply\observers;
use enrol_apply\reportbuilder\datasource\applications;
use enrol_apply\reportbuilder\local\systemreports\course_applications;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A reader's stored filter values of the course report are the plugin's personal data (Design System D146).
 *
 * Core writes them (reportbuilder_user_filter) under the report's persistent, and its own provider covers custom
 * reports only. Every test builds the convenient fact and the recorded one apart: the reports core's services build
 * for the plugin's source under another component, and a core system report they build under the plugin's component
 * (the class core instantiates is what makes a report the plugin's), beside a core system report and a custom report on
 * the plugin's datasource.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
#[CoversClass(reportpersistents::class)]
#[CoversClass(observers::class)]
final class stored_filters_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass The reader whose stored filters are the subject. */
    private $reader;

    /** @var \stdClass Another reader of the same report: the control. */
    private $otherreader;

    /** @var \stdClass The course of the report. */
    private $course;

    /** @var \stdClass The enrolment method the page's report is of. */
    private $instance;

    /** @var int The report the plugin's page built for the method. */
    private $pagereport;

    /** @var int The plugin's report core's services built under no component. */
    private $craftedreport;

    /** @var int The plugin's report stored with a leading separator and another case. */
    private $oddreport;

    /** @var int A core system report: not the plugin's. */
    private $corereport;

    /** @var int A core system report built under the plugin's component, area and method: not the plugin's either. */
    private $foreignreport;

    /** @var int A custom report on the plugin's datasource: its author's, covered by core. */
    private $customreport;

    /**
     * The course report's persistents, each with the reader's stored filters, and the two that are not the plugin's.
     *
     * Built as the administrator, as the page builds the report for whoever opens it; the stored values are written
     * by core's store, the one core_reportbuilder_set_filters writes through.
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
        $plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course(['fullname' => 'Course of the report']);
        $instanceid = $plugin->add_instance($this->course, ['name' => 'Night intake'] + $plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        $context = context_course::instance($this->course->id);

        $this->reader = $this->getDataGenerator()->create_user();
        $this->otherreader = $this->getDataGenerator()->create_user();

        $this->setAdminUser();
        $this->pagereport = (int) course_applications::for_method($context, $instanceid)->get_report_persistent()->get('id');
        $this->craftedreport = (int) system_report_factory::create(course_applications::class, $context)
            ->get_report_persistent()->get('id');
        $odd = new report(0, (object) [
            'source' => '\\' . strtoupper(course_applications::class),
            'type' => base::TYPE_SYSTEM_REPORT,
            'contextid' => $context->id,
            'component' => 'local_other',
            'area' => 'method',
            'itemid' => $instanceid,
        ]);
        $this->oddreport = (int) $odd->create()->get('id');
        $core = new report(0, (object) [
            'source' => 'core_admin\\reportbuilder\\local\\systemreports\\task_logs',
            'type' => base::TYPE_SYSTEM_REPORT,
            'contextid' => \context_system::instance()->id,
            'component' => 'core_admin',
        ]);
        $this->corereport = (int) $core->create()->get('id');
        $foreign = new report(0, (object) [
            'source' => 'core_admin\\reportbuilder\\local\\systemreports\\task_logs',
            'type' => base::TYPE_SYSTEM_REPORT,
            'contextid' => $context->id,
            'component' => 'enrol_apply',
            'area' => 'method',
            'itemid' => $instanceid,
        ]);
        $this->foreignreport = (int) $foreign->create()->get('id');
        $this->customreport = (int) $this->getDataGenerator()->get_plugin_generator('core_reportbuilder')
            ->create_report(['name' => 'Applications', 'source' => applications::class, 'default' => false])->get('id');

        user_filter_manager::set(
            $this->pagereport,
            ['submission:status_operator' => 1, 'submission:status_value' => 2],
            (int) $this->reader->id
        );
        user_filter_manager::set($this->craftedreport, ['user:fullname_value' => 'Typed name'], (int) $this->reader->id);
        user_filter_manager::set($this->oddreport, ['submission:status_value' => 3], (int) $this->reader->id);
        user_filter_manager::set($this->corereport, ['task_log:result_value' => 1], (int) $this->reader->id);
        user_filter_manager::set($this->foreignreport, ['task_log:result_value' => 2], (int) $this->reader->id);
        user_filter_manager::set($this->customreport, ['submission:status_value' => 1], (int) $this->reader->id);
        user_filter_manager::set($this->pagereport, ['submission:status_value' => 4], (int) $this->otherreader->id);
        manager::reset_caches();
    }

    /**
     * How many stored filter rows a user has of a report.
     *
     * @param \stdClass $user The user.
     * @param int $reportid The report.
     * @return int
     */
    private function stored(\stdClass $user, int $reportid): int {
        global $DB;
        return $DB->count_records(user_filter::TABLE, ['reportid' => $reportid, 'usercreated' => $user->id]);
    }

    /**
     * The reader's rows of the plugin's three reports are gone, and every control stays.
     *
     * @return void
     */
    private function assert_only_the_plugins_rows_of_the_reader_went(): void {
        $this->assertSame(0, $this->stored($this->reader, $this->pagereport));
        $this->assertSame(0, $this->stored($this->reader, $this->craftedreport));
        $this->assertSame(0, $this->stored($this->reader, $this->oddreport));
        // The controls: the other reader's of the same report, and the reader's of the three reports not the plugin's.
        $this->assertSame(1, $this->stored($this->otherreader, $this->pagereport));
        $this->assertSame(1, $this->stored($this->reader, $this->corereport));
        $this->assertSame(1, $this->stored($this->reader, $this->foreignreport));
        $this->assertSame(1, $this->stored($this->reader, $this->customreport));
    }

    /**
     * The table is declared with every column but its id.
     *
     * Changes that must make it fail: the declaration dropped, or a column of core's table left out of it.
     *
     * @return void
     */
    public function test_the_stored_filters_are_declared_whole(): void {
        global $DB;
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('enrol_apply'));
        $tables = [];
        foreach ($collection->get_collection() as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $tables[$item->get_name()] = $item;
            }
        }
        $this->assertArrayHasKey(user_filter::TABLE, $tables);
        $columns = array_diff(array_keys($DB->get_columns(user_filter::TABLE)), ['id']);
        $this->assertEqualsCanonicalizing($columns, array_keys($tables[user_filter::TABLE]->get_privacy_fields()));
    }

    /**
     * The reader finds their own user context, and nothing else, for stored filters of the plugin's reports; its user
     * list holds them. A user whose only stored filters are of reports not the plugin's finds nothing.
     *
     * Changes that must make it fail: the finder or the user list missing the rows; the finder cut by the component
     * (a reader whose only rows are on a report core's services built would find nothing, and one whose only rows are
     * on a core report built under the plugin's component would find theirs); the finder taking a core system report
     * or a custom report on the plugin's datasource.
     *
     * @return void
     */
    public function test_the_reader_finds_their_own_context(): void {
        $context = context_user::instance($this->reader->id);
        $this->assertSame(
            [(int) $context->id],
            array_map('intval', provider::get_contexts_for_userid((int) $this->reader->id)->get_contextids())
        );
        $userlist = new userlist($context, 'enrol_apply');
        provider::get_users_in_context($userlist);
        $this->assertSame([(int) $this->reader->id], array_map('intval', $userlist->get_userids()));

        // A reader whose only rows are on the report core's services built under no component is found too.
        $crafted = $this->getDataGenerator()->create_user();
        user_filter_manager::set($this->craftedreport, ['submission:status_value' => 1], (int) $crafted->id);
        $this->assertSame(
            [(int) context_user::instance($crafted->id)->id],
            array_map('intval', provider::get_contexts_for_userid((int) $crafted->id)->get_contextids())
        );

        // The controls: rows of core system reports, one under the plugin's component, and of a custom report on the
        // plugin's datasource are not ours.
        $outsider = $this->getDataGenerator()->create_user();
        user_filter_manager::set($this->corereport, ['task_log:result_value' => 1], (int) $outsider->id);
        user_filter_manager::set($this->foreignreport, ['task_log:result_value' => 1], (int) $outsider->id);
        user_filter_manager::set($this->customreport, ['submission:status_value' => 1], (int) $outsider->id);
        $this->assertSame([], provider::get_contexts_for_userid((int) $outsider->id)->get_contextids());
        $userlist = new userlist(context_user::instance($outsider->id), 'enrol_apply');
        provider::get_users_in_context($userlist);
        $this->assertSame([], $userlist->get_userids());
    }

    /**
     * The reader's export holds their stored filters of the plugin's three reports, each with its course, the method
     * named only for the page's report; never the other reader's, nor the rows of reports not the plugin's.
     *
     * Run as the administrator, never as the reader. Changes that must make it fail: the export missing; the export
     * read for the current user; a method named for a report core's services built.
     *
     * @return void
     */
    public function test_the_reader_exports_their_stored_filters(): void {
        $context = context_user::instance($this->reader->id);
        $this->setAdminUser();
        $this->export_context_data_for_user((int) $this->reader->id, $context, 'enrol_apply');

        $data = writer::with_context($context)->get_data([get_string('privacy:reportfilterspath', 'enrol_apply')]);
        $this->assertCount(3, $data->filters);
        $byfilters = [];
        foreach ($data->filters as $entry) {
            $this->assertSame('Course of the report', $entry->course);
            $byfilters[json_encode($entry->filters)] = $entry->method;
        }
        $this->assertSame([
            json_encode(['submission:status_operator' => 1, 'submission:status_value' => 2]) => 'Night intake',
            json_encode(['user:fullname_value' => 'Typed name']) => '',
            json_encode(['submission:status_value' => 3]) => '',
        ], $byfilters);

        // The control: the other reader exports their own row only.
        writer::reset();
        $othercontext = context_user::instance($this->otherreader->id);
        $this->export_context_data_for_user((int) $this->otherreader->id, $othercontext, 'enrol_apply');
        $data = writer::with_context($othercontext)->get_data([get_string('privacy:reportfilterspath', 'enrol_apply')]);
        $this->assertCount(1, $data->filters);
        $this->assertEquals((object) ['submission:status_value' => 4], $data->filters[0]->filters);
    }

    /**
     * An export or a delete approved for another user's context touches nothing of that context's user.
     *
     * Changes that must make it fail: the export or the delete following any user context instead of the subject's own.
     *
     * @return void
     */
    public function test_another_users_context_is_never_read_as_the_subjects(): void {
        $readercontext = context_user::instance($this->reader->id);
        provider::export_user_data(new approved_contextlist($this->otherreader, 'enrol_apply', [$readercontext->id]));
        $this->assertFalse(writer::with_context($readercontext)->has_any_data());

        provider::delete_data_for_user(new approved_contextlist($this->otherreader, 'enrol_apply', [$readercontext->id]));
        $this->assertSame(1, $this->stored($this->reader, $this->pagereport));
        $this->assertSame(1, $this->stored($this->otherreader, $this->pagereport));
    }

    /**
     * The ways a reader's data is deleted, each reaching the stored filters.
     *
     * @return array Case name => [the delete method].
     */
    public static function deletions(): array {
        return ['for the user' => ['user'], 'for every user in the context' => ['context'], 'for listed users' => ['users']];
    }

    /**
     * Each delete removes the reader's stored filters of the plugin's reports, entered through the finder's context,
     * and leaves the other reader's of the same report and the reader's of reports not the plugin's.
     *
     * Changes that must make it fail: a delete that leaves the rows, one cut by the component alone, or one that takes
     * a core system report's or a custom report's rows.
     *
     * @param string $method user, context or users.
     * @return void
     */
    #[DataProvider('deletions')]
    public function test_the_stored_filters_are_deleted(string $method): void {
        $contextids = provider::get_contexts_for_userid((int) $this->reader->id)->get_contextids();
        $context = \context::instance_by_id((int) reset($contextids));
        $this->assertSame(1, $this->stored($this->reader, $this->craftedreport));

        match ($method) {
            'user' => provider::delete_data_for_user(new approved_contextlist($this->reader, 'enrol_apply', $contextids)),
            'context' => provider::delete_data_for_all_users_in_context($context),
            'users' => provider::delete_data_for_users(
                new approved_userlist($context, 'enrol_apply', [(int) $this->reader->id])
            ),
        };

        $this->assert_only_the_plugins_rows_of_the_reader_went();
    }

    /**
     * A list naming the context's user and another reader deletes the context's user's rows only; a list not naming
     * the context's user deletes nothing.
     *
     * Changes that must make it fail: a delete that follows the list's ids instead of the context's user, or one that
     * ignores the list.
     *
     * @return void
     */
    public function test_the_users_delete_clears_the_contexts_user_only(): void {
        $context = context_user::instance($this->reader->id);
        provider::delete_data_for_users(new approved_userlist($context, 'enrol_apply', [(int) $this->otherreader->id]));
        $this->assertSame(1, $this->stored($this->reader, $this->pagereport));

        provider::delete_data_for_users(
            new approved_userlist($context, 'enrol_apply', [(int) $this->reader->id, (int) $this->otherreader->id])
        );
        $this->assertSame(0, $this->stored($this->reader, $this->pagereport));
        $this->assertSame(1, $this->stored($this->otherreader, $this->pagereport));
    }

    /**
     * A delete in the course context leaves every reader's stored filters: they live in the readers' own contexts.
     *
     * Changes that must make it fail: the course delete reaching the stored filters.
     *
     * @return void
     */
    public function test_a_course_delete_leaves_the_stored_filters(): void {
        provider::delete_data_for_all_users_in_context(context_course::instance($this->course->id));
        $this->assertSame(1, $this->stored($this->reader, $this->pagereport));
        $this->assertSame(1, $this->stored($this->otherreader, $this->pagereport));
    }

    /**
     * Deleting the account removes the reader's stored filters of the plugin's reports, which the finder could no
     * longer reach once the user context is gone; every control stays.
     *
     * Changes that must make it fail: the user_deleted observer not clearing them, or clearing by anything but the
     * deleted user and the plugin's reports.
     *
     * @return void
     */
    public function test_deleting_the_account_removes_the_stored_filters(): void {
        $this->assertSame(1, $this->stored($this->reader, $this->pagereport));

        delete_user($this->reader);

        $this->assert_only_the_plugins_rows_of_the_reader_went();
    }
}
