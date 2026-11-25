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

use context_course;
use core_reportbuilder\local\helpers\user_filter_manager;
use core_reportbuilder\local\models\report;
use core_reportbuilder\local\models\user_filter;
use core_reportbuilder\manager;
use core_reportbuilder\system_report;
use core_reportbuilder\system_report_factory;
use enrol_apply\observers;
use enrol_apply\reportbuilder\local\systemreports\course_applications;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Which report persistents are the plugin's, and the deletions of a method and a course that remove them.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(reportpersistents::class)]
#[CoversClass(observers::class)]
#[CoversClass(\enrol_apply_plugin::class)]
final class reportpersistents_test extends \advanced_testcase {
    /**
     * Every system report class under the plugin's tree is one of the sources the cut names.
     *
     * The rule is "every system report of the plugin": a new report class whose source is not listed would leave the
     * persistents core's services build for it, under another component, outside every finder and deletion.
     *
     * @return void
     */
    public function test_every_system_report_of_the_plugin_is_a_listed_source(): void {
        $found = [];
        $classes = \core_component::get_component_classes_in_namespace('enrol_apply', 'reportbuilder\\local\\systemreports');
        foreach (array_keys($classes) as $class) {
            $reflection = new \ReflectionClass($class);
            if ($reflection->isSubclassOf(system_report::class) && !$reflection->isAbstract()) {
                $found[] = ltrim($class, '\\');
            }
        }
        $this->assertNotEmpty($found);
        $this->assertEqualsCanonicalizing($found, reportpersistents::SOURCES);
    }

    /**
     * A course with two methods, each with the page's report and a reader's stored filters, and the plugin's report
     * core's services built under no component.
     *
     * And a core system report built under the plugin's component, area and first method: not the plugin's.
     *
     * @return array [the course, the first method, the second method, report ids keyed first, second, crafted, and the
     *     core report's id].
     */
    private function two_methods(): array {
        global $DB;
        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        $plugin = enrol_get_plugin('apply');
        $course = $this->getDataGenerator()->create_course();
        $first = $DB->get_record('enrol', ['id' => $plugin->add_instance($course, $plugin->get_instance_defaults())]);
        $second = $DB->get_record('enrol', ['id' => $plugin->add_instance($course, $plugin->get_instance_defaults())]);
        $context = context_course::instance($course->id);
        $reader = $this->getDataGenerator()->create_user();

        $this->setAdminUser();
        $reports = [
            'first' => (int) course_applications::for_method($context, (int) $first->id)->get_report_persistent()->get('id'),
            'second' => (int) course_applications::for_method($context, (int) $second->id)->get_report_persistent()->get('id'),
            'crafted' => (int) system_report_factory::create(course_applications::class, $context)
                ->get_report_persistent()->get('id'),
        ];
        $foreign = new report(0, (object) [
            'source' => 'core_admin\\reportbuilder\\local\\systemreports\\task_logs',
            'type' => \core_reportbuilder\local\report\base::TYPE_SYSTEM_REPORT,
            'contextid' => $context->id,
            'component' => 'enrol_apply',
            'area' => 'method',
            'itemid' => (int) $first->id,
        ]);
        $foreignid = (int) $foreign->create()->get('id');
        foreach (array_merge(array_values($reports), [$foreignid]) as $reportid) {
            user_filter_manager::set($reportid, ['submission:status_value' => 2], (int) $reader->id);
        }
        manager::reset_caches();
        return [$course, $first, $second, $reports, $foreignid];
    }

    /**
     * Whether a report persistent exists, and how many stored filter rows hang on it.
     *
     * @param int $reportid The report.
     * @return array [exists, stored rows].
     */
    private function state(int $reportid): array {
        global $DB;
        return [
            $DB->record_exists(report::TABLE, ['id' => $reportid]),
            $DB->count_records(user_filter::TABLE, ['reportid' => $reportid]),
        ];
    }

    /**
     * Deleting a method deletes its course report and the readers' stored filters on it; the other method's report
     * stays.
     *
     * Changes that must make it fail: delete_instance() not deleting the report, deleting by anything but the
     * method (the other method's report would go), or taking a core report built under the method's component, area
     * and item.
     *
     * @return void
     */
    public function test_deleting_a_method_deletes_its_report(): void {
        $this->resetAfterTest();
        [, $first, , $reports, $foreignid] = $this->two_methods();
        $this->assertSame([true, 1], $this->state($reports['first']));

        enrol_get_plugin('apply')->delete_instance($first);

        $this->assertSame([false, 0], $this->state($reports['first']));
        // The controls: the other method's report and the course's other report of the plugin stay.
        $this->assertSame([true, 1], $this->state($reports['second']));
        $this->assertSame([true, 1], $this->state($reports['crafted']));
        $this->assertSame([true, 1], $this->state($foreignid));
    }

    /**
     * Deleting the course deletes every report of the plugin in it, the one core's services built included, with
     * the readers' stored filters; another course's report and a core system report stay.
     *
     * Changes that must make it fail: the course_deleted observer not sweeping the reports whose context is gone, or
     * sweeping a report whose context exists, or one that is not the plugin's (a core report under its component).
     *
     * @return void
     */
    public function test_deleting_the_course_deletes_every_report_of_the_plugin_in_it(): void {
        $this->resetAfterTest();
        [$course, , , $reports, $foreignid] = $this->two_methods();
        [, , , $others] = $this->two_methods();

        delete_course($course, false);

        foreach ($reports as $reportid) {
            $this->assertSame([false, 0], $this->state($reportid));
        }
        // The controls: the other course's reports, and a core report under the plugin's component whose context is gone.
        foreach ($others as $reportid) {
            $this->assertSame([true, 1], $this->state($reportid));
        }
        $this->assertSame([true, 1], $this->state($foreignid));
    }

    /**
     * The sweep of a deleted course reaches the reports of a disabled method too, whose delete_instance() core skips.
     *
     * Changes that must make it fail: the observer's sweep removed (delete_instance() alone covers enabled methods).
     *
     * @return void
     */
    public function test_a_disabled_methods_report_goes_with_its_course(): void {
        $this->resetAfterTest();
        [$course, , , $reports] = $this->two_methods();
        $enabled = array_diff(array_keys(enrol_get_plugins(true)), ['apply']);
        set_config('enrol_plugins_enabled', implode(',', $enabled));

        delete_course($course, false);

        $this->assertSame([false, 0], $this->state($reports['first']));
        $this->assertSame([false, 0], $this->state($reports['second']));
    }
}
