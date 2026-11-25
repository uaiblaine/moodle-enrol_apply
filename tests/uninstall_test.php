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

use context_course;
use core_reportbuilder\local\helpers\user_filter_manager;
use core_reportbuilder\local\models\report;
use core_reportbuilder\local\models\user_filter;
use core_reportbuilder\local\report\base;
use core_reportbuilder\manager;
use core_reportbuilder\system_report_factory;
use enrol_apply\local\queuepreference;
use enrol_apply\local\reportpreference;
use enrol_apply\reportbuilder\datasource\applications;
use enrol_apply\reportbuilder\local\systemreports\course_applications;
use PHPUnit\Framework\Attributes\CoversFunction;

/**
 * What the plugin's uninstall removes that core's uninstall_plugin() leaves behind.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('xmldb_enrol_apply_uninstall')]
final class uninstall_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/enrol/apply/db/uninstall.php');
    }

    /**
     * The uninstall deletes the plugin's report persistents with every reader's stored filters, the one core's
     * services built under no component included, and the plugin's three user preferences; a core system report built
     * under the plugin's component, a custom report on the plugin's datasource, their stored filters and other
     * preferences stay.
     *
     * Changes that must make it fail: the reports left, or cut by the component (the crafted one stays, the core one
     * goes) or by nothing (the controls go); a preference left behind, or a lookalike taken with them.
     *
     * @return void
     */
    public function test_the_plugins_reports_filters_and_preferences_go(): void {
        global $DB;
        $this->resetAfterTest();
        $plugin = enrol_get_plugin('apply');
        $course = $this->getDataGenerator()->create_course();
        $instanceid = $plugin->add_instance($course, $plugin->get_instance_defaults());
        $context = context_course::instance($course->id);
        $reader = $this->getDataGenerator()->create_user();

        $this->setAdminUser();
        $pagereport = (int) course_applications::for_method($context, $instanceid)->get_report_persistent()->get('id');
        $crafted = (int) system_report_factory::create(course_applications::class, $context)->get_report_persistent()->get('id');
        $core = new report(0, (object) [
            'source' => 'core_admin\\reportbuilder\\local\\systemreports\\task_logs',
            'type' => base::TYPE_SYSTEM_REPORT,
            'contextid' => $context->id,
            'component' => 'enrol_apply',
            'area' => 'method',
            'itemid' => $instanceid,
        ]);
        $corereport = (int) $core->create()->get('id');
        $custom = (int) $this->getDataGenerator()->get_plugin_generator('core_reportbuilder')
            ->create_report(['name' => 'Applications', 'source' => applications::class, 'default' => false])->get('id');
        foreach ([$pagereport, $crafted, $corereport, $custom] as $reportid) {
            user_filter_manager::set($reportid, ['submission:status_value' => 2], (int) $reader->id);
        }
        user_filter_manager::set($pagereport, ['submission:status_value' => 3]);

        set_user_preference(reportpreference::NAME, '{"columns":["outcome"]}', $reader);
        set_user_preference(queuepreference::NAME, '{"columns":["name"]}', $reader);
        set_user_preference(queuepreference::NAME_WIDE, '{"columns":["course"]}', $reader);
        set_user_preference('enrol_other_report_columns', 'kept', $reader);
        // A lookalike: the names are matched whole, never by prefix.
        set_user_preference(queuepreference::NAME . 'x', 'kept', $reader);
        // The precondition: the plugin's rows are there.
        $this->assertSame(2, $DB->count_records(user_filter::TABLE, ['reportid' => $pagereport]));
        manager::reset_caches();

        $this->assertTrue(xmldb_enrol_apply_uninstall());

        foreach ([$pagereport, $crafted] as $reportid) {
            $this->assertFalse($DB->record_exists(report::TABLE, ['id' => $reportid]));
            $this->assertSame(0, $DB->count_records(user_filter::TABLE, ['reportid' => $reportid]));
        }
        [$insql, $params] = $DB->get_in_or_equal([reportpreference::NAME, queuepreference::NAME, queuepreference::NAME_WIDE]);
        $this->assertSame(0, $DB->count_records_select('user_preferences', "name $insql", $params));
        // The controls: the reports that are not the plugin's, their stored filters and the other preferences stay.
        foreach ([$corereport, $custom] as $reportid) {
            $this->assertTrue($DB->record_exists(report::TABLE, ['id' => $reportid]));
            $this->assertSame(1, $DB->count_records(user_filter::TABLE, ['reportid' => $reportid]));
        }
        $this->assertSame('kept', get_user_preferences('enrol_other_report_columns', null, $reader));
        $this->assertSame('kept', get_user_preferences(queuepreference::NAME . 'x', null, $reader));
    }
}
