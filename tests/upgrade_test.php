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
use enrol_apply\local\reportpersistents;
use enrol_apply\reportbuilder\local\systemreports\course_applications;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;

/**
 * The upgrade step that removes what the versions before 2026112501 left of the course report (Design System D146).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('xmldb_enrol_apply_upgrade')]
#[CoversClass(reportpersistents::class)]
final class upgrade_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/enrol/apply/db/upgrade.php');
    }

    /**
     * Upgrading from 2026112500 deletes the stored filters of accounts deleted before it and the report of a course
     * deleted before it; a live reader's stored filters, a deleted reader's stored filters on a report that is not the
     * plugin's, and a report whose course exists stay.
     *
     * Changes that must make it fail: the step not run, either of its two deletions dropped, or the deleted readers'
     * cut taking a live reader's rows.
     *
     * @return void
     */
    public function test_the_step_removes_what_earlier_versions_left(): void {
        global $DB;
        $this->resetAfterTest();
        $plugin = enrol_get_plugin('apply');
        $course = $this->getDataGenerator()->create_course();
        $instanceid = $plugin->add_instance($course, $plugin->get_instance_defaults());
        $live = $this->getDataGenerator()->create_user();
        $gone = $this->getDataGenerator()->create_user();

        $this->setAdminUser();
        $reportid = (int) course_applications::for_method(context_course::instance($course->id), $instanceid)
            ->get_report_persistent()->get('id');
        // A report of a course deleted before this version: its context no longer exists.
        $orphan = new report(0, (object) [
            'source' => course_applications::class,
            'type' => base::TYPE_SYSTEM_REPORT,
            'contextid' => (int) $DB->get_field_sql('SELECT MAX(id) FROM {context}') + 1000,
            'component' => 'enrol_apply',
            'area' => 'method',
            'itemid' => $instanceid + 1000,
        ]);
        $orphanid = (int) $orphan->create()->get('id');
        $core = new report(0, (object) [
            'source' => 'core_admin\\reportbuilder\\local\\systemreports\\task_logs',
            'type' => base::TYPE_SYSTEM_REPORT,
            'contextid' => \context_system::instance()->id,
            'component' => 'core_admin',
        ]);
        $coreid = (int) $core->create()->get('id');
        foreach ([$live, $gone] as $user) {
            user_filter_manager::set($reportid, ['submission:status_value' => 2], (int) $user->id);
            user_filter_manager::set($coreid, ['task_log:result_value' => 1], (int) $user->id);
        }
        user_filter_manager::set($orphanid, ['submission:status_value' => 3], (int) $live->id);
        // An account deleted before this version: deleted, and its stored filters kept by the old observer.
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);
        manager::reset_caches();
        set_config('version', 2026112500, 'enrol_apply');

        $this->assertTrue(xmldb_enrol_apply_upgrade(2026112500));

        $count = fn(int $report, \stdClass $user): int => $DB->count_records(
            user_filter::TABLE,
            ['reportid' => $report, 'usercreated' => $user->id]
        );
        $this->assertSame(0, $count($reportid, $gone));
        $this->assertFalse($DB->record_exists(report::TABLE, ['id' => $orphanid]));
        $this->assertSame(0, $count($orphanid, $live));
        // The controls.
        $this->assertSame(1, $count($reportid, $live));
        $this->assertSame(1, $count($coreid, $gone));
        $this->assertTrue($DB->record_exists(report::TABLE, ['id' => $reportid]));
    }
}
