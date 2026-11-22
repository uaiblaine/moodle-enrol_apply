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
 * Tests for the course report's React shell (stage R4).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\output;

use context_course;
use enrol_apply\local\submission;
use enrol_apply\reportbuilder\local\systemreports\course_applications;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What the server adds for the React view: the branch gate, a shell whose props carry no row, core's
 * report in the noscript without its filter form, and the queue link only for a reader the queue admits.
 *
 * The client itself has no test runner here; tests/behat/report_view.feature drives it on 5.3.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report_view::class)]
final class report_view_test extends \advanced_testcase {
    /** @var \stdClass Course carrying the apply instance. */
    private $course;

    /** @var \stdClass The apply instance. */
    private $instance;

    /** @var \stdClass The applicant, named so that any copy of a row in the page is found. */
    private $applicant;

    /**
     * A course with an apply instance and one pending record of a distinctively named applicant.
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
        $this->course = $this->getDataGenerator()->create_course();
        $id = $plugin->add_instance($this->course, $plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $this->applicant = $this->getDataGenerator()->create_user([
            'firstname' => 'Zebedee',
            'lastname' => 'Quintessence',
            'email' => 'zebedee.quintessence@example.com',
        ]);
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $this->applicant->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => 0,
            'comment' => 'Marmalade comment',
            'status' => submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => time() - DAYSECS,
            'timedecided' => 0,
            'decidedby' => 0,
        ]);
    }

    /**
     * The course report as report.php builds it, with the page set up as there.
     *
     * @return course_applications The report.
     */
    private function report(): course_applications {
        global $PAGE;

        $context = context_course::instance($this->course->id);
        $PAGE->set_context($context);
        $PAGE->set_url(report_view::url((int) $this->instance->id));
        /** @var course_applications $report */
        $report = course_applications::for_method($context, (int) $this->instance->id);

        return $report;
    }

    /**
     * The view mounts on Moodle 5.3 and later only, and never where core's table was asked for.
     *
     * Changes that must make it fail: the branch gate lowered to 5.2 or dropped; view=table ignored.
     *
     * @return void
     */
    public function test_the_view_mounts_on_53_only_and_not_for_the_table(): void {
        global $CFG;

        $CFG->branch = '502';
        $this->assertFalse(report_view::available());
        $this->assertFalse(report_view::wanted(''));

        $CFG->branch = '503';
        $this->assertTrue(report_view::available());
        $this->assertTrue(report_view::wanted(''));
        $this->assertFalse(report_view::wanted(report_view::TABLE));

        // The table's url names the view; the default one names nothing but the method.
        $this->assertSame('table', report_view::url(5, true)->get_param('view'));
        $this->assertNull(report_view::url(5)->get_param('view'));
        $this->assertSame('5', (string) report_view::url(5)->get_param('id'));
    }

    /**
     * The props carry configuration and strings, never a row: no name, address or comment of an
     * applicant, and only the keys the client reads.
     *
     * Changes that must make it fail: rows, columns or a summary added to the props; a key renamed
     * without the client (js/esm/src/report/types.ts, ViewProps).
     *
     * @return void
     */
    public function test_the_props_carry_no_row(): void {
        $this->setAdminUser();
        $report = $this->report();

        $props = (new report_view($this->instance, $report))->props();
        $this->assertSame([
            'enrolid', 'reportid', 'measure', 'perpage', 'perpages', 'searchmin', 'downloadurl', 'formats', 'queueurl',
            'pendingstatus', 'tableurl', 'methods', 'labels',
        ], array_keys($props));
        $this->assertSame((int) $this->instance->id, $props['enrolid']);
        $this->assertSame((int) $report->get_report_persistent()->get('id'), $props['reportid']);
        $this->assertSame(1, $props['methods']);

        $json = json_encode($props);
        // Control: the applicant's record is in the report the shell was built from.
        $this->assertStringContainsString('Quintessence', $report->output());
        foreach (['Zebedee', 'Quintessence', 'zebedee.quintessence', 'Marmalade'] as $fact) {
            $this->assertStringNotContainsString($fact, $json);
        }
    }

    /**
     * The shell mounts the bundle and keeps core's rendering of the report in its noscript, without
     * the filter form; outside the noscript no row is in the page.
     *
     * Changes that must make it fail: the mount naming a per-file module; the noscript dropped; the
     * filter form left in it (its script looks for a form a scripting browser never builds).
     *
     * @return void
     */
    public function test_the_shell_mounts_the_bundle_and_keeps_cores_report_for_no_javascript(): void {
        global $PAGE;

        if (!report_view::available()) {
            $this->markTestSkipped('The React view mounts on Moodle 5.3 and later only.');
        }
        $this->setAdminUser();

        // Control: core's own rendering of this report carries its filter form.
        $this->assertStringContainsString('dropdownFiltersButton', $this->report()->output());

        $html = $PAGE->get_renderer('core')->render(new report_view($this->instance, $this->report()));
        $this->assertStringContainsString('data-react-component="@moodle/lms/enrol_apply/bundle"', $html);
        $this->assertStringContainsString('view=table', $html);

        $at = strpos($html, '<noscript>');
        $this->assertNotFalse($at);
        $before = substr($html, 0, $at);
        $noscript = substr($html, $at);
        $this->assertStringContainsString('Quintessence', $noscript);
        $this->assertStringNotContainsString('dropdownFiltersButton', $noscript);
        $this->assertStringNotContainsString('Quintessence', $before);
    }

    /**
     * "Still open" leads to the queue only for a reader the queue admits (AR-D18): a holder of the
     * report capability alone gets the number and no link.
     *
     * Changes that must make it fail: the link offered to every report reader; withheld from a decider.
     *
     * @return void
     */
    public function test_the_queue_link_is_offered_only_to_a_reader_the_queue_admits(): void {
        global $DB;

        $this->setAdminUser();
        $props = (new report_view($this->instance, $this->report()))->props();
        $this->assertStringContainsString('/enrol/apply/manage.php?id=' . $this->instance->id, $props['queueurl']);

        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'reportreader']);
        $context = context_course::instance($this->course->id);
        assign_capability('enrol/apply:viewreports', CAP_ALLOW, $roleid, $context->id, true);
        assign_capability('moodle/course:view', CAP_ALLOW, $roleid, $context->id, true);
        $reader = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($reader->id, $this->course->id, 'reportreader');
        $this->assertTrue((bool) $DB->record_exists('role_assignments', ['userid' => $reader->id, 'roleid' => $roleid]));

        $this->setUser($reader);
        $props = (new report_view($this->instance, $this->report()))->props();
        $this->assertSame('', $props['queueurl']);
    }

    /**
     * Every string the client reads exists in both spellings it is handed: no placeholder is lost.
     *
     * Changes that must make it fail: a label pointing at a string id that does not exist.
     *
     * @return void
     */
    public function test_every_label_is_a_string(): void {
        $labels = report_view::labels();
        foreach ($labels as $name => $text) {
            $this->assertNotSame('', $text, $name);
            $this->assertStringNotContainsString('[[', $text, $name);
        }
        // The placeholders the client fills are still there.
        $this->assertStringContainsString('{$a}', $labels['count']);
        $this->assertStringContainsString('{$a->total}', $labels['range']);
    }
}
