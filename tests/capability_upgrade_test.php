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
 * Tests for the upgrade copy of enrol/apply:choosedecision.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * What an upgrade does with enrol/apply:choosedecision on a site that already had deciders.
 *
 * The capability is new, and db/access.php names enrol/apply:manageapplications in its
 * clonepermissionsfrom. The upgrade is simulated the way core runs it: the capability's rows are
 * removed from {capabilities} and {role_capabilities}, as on a site that predates it, and
 * update_capabilities() is called.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class capability_upgrade_test extends \advanced_testcase {
    /**
     * A custom role that decides keeps choosing, with the same permission in the same contexts.
     *
     * Changes that must make it fail: the clonepermissionsfrom line deleted from db/access.php,
     * which leaves the archetypes to decide and so gives a custom role nothing.
     *
     * @return void
     */
    public function test_a_custom_role_that_decides_keeps_choosing_after_an_upgrade(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $coursecontext = \context_course::instance($course->id);
        $system = \context_system::instance();
        $decide = 'enrol/apply:manageapplications';
        $choose = 'enrol/apply:choosedecision';

        $custom = $generator->create_role(['shortname' => 'customdecider']);
        assign_capability($decide, CAP_ALLOW, $custom, $system->id, true);
        assign_capability($decide, CAP_PROHIBIT, $custom, $coursecontext->id, true);
        $viewer = $generator->create_role(['shortname' => 'customviewer']);
        assign_capability('enrol/apply:viewapplications', CAP_ALLOW, $viewer, $system->id, true);

        // The site before the upgrade: the capability exists nowhere.
        $DB->delete_records('role_capabilities', ['capability' => $choose]);
        $DB->delete_records('capabilities', ['name' => $choose]);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => $choose, 'roleid' => $custom]));

        update_capabilities('enrol_apply');
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertTrue($DB->record_exists('capabilities', ['name' => $choose]));
        $this->assertEquals(CAP_ALLOW, $DB->get_field('role_capabilities', 'permission', [
            'capability' => $choose,
            'roleid' => $custom,
            'contextid' => $system->id,
        ]));
        $this->assertEquals(CAP_PROHIBIT, $DB->get_field('role_capabilities', 'permission', [
            'capability' => $choose,
            'roleid' => $custom,
            'contextid' => $coursecontext->id,
        ]));
        // The control: a role that never decided is given nothing.
        $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => $choose, 'roleid' => $viewer]));
    }
}
