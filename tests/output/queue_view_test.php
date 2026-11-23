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
 * Tests for the method queue's grid view shell (stage 3 of the queue as a report).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\output;

use context_course;
use enrol_apply\local\submission;
use enrol_apply\table\applications;
use moodle_url;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What the server adds for the queue's grid view: the opt-in view=grid on the method queue only, a
 * shell whose props carry no application, today's queue in the noscript, the address read for the
 * first paint, and "Open the report" only for a reader the report admits.
 *
 * The client itself has no test runner here; tests/behat/queue_view.feature drives it.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(queue_view::class)]
final class queue_view_test extends \advanced_testcase {
    /** @var \stdClass Course carrying the apply instance. */
    private $course;

    /** @var \stdClass The apply instance. */
    private $instance;

    /**
     * A course with an apply instance and one pending application of a distinctively named applicant.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG, $DB;

        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $id = $plugin->add_instance($this->course, $plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $applicant = $this->getDataGenerator()->create_user([
            'firstname' => 'Zebedee',
            'lastname' => 'Quintessence',
            'email' => 'zebedee.quintessence@example.com',
        ]);
        $plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $applicant->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => $ueid,
            'comment' => 'Marmalade comment',
            'status' => submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => time() - DAYSECS,
            'timedecided' => 0,
            'decidedby' => 0,
        ]);
    }

    /**
     * The shell as manage.php builds it for the current user, with the page set up as there.
     *
     * @return queue_view The shell.
     */
    private function shell(): queue_view {
        global $PAGE;

        $url = new moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_context(context_course::instance($this->course->id));
        $PAGE->set_url(queue_view::url($url, true));
        $table = applications::for_scope((int) $this->instance->id);

        return new queue_view($this->instance, $table, $url, queue_view::initial('', null, []));
    }

    /**
     * The view is wanted on the method queue with view=grid, and nowhere else: today's table stays the default.
     *
     * Changes that must make it fail: the id condition dropped (the site-wide and mentee queues, id 0,
     * would mount it); the view condition dropped or inverted (the read-only view would replace the
     * table that decides, before stage 4 brings the decisions).
     *
     * @return void
     */
    public function test_the_view_is_wanted_only_on_the_method_queue_with_view_grid(): void {
        $this->assertTrue(queue_view::wanted(5, queue_view::GRID));
        $this->assertTrue(queue_view::wanted(5, 'grid'));

        // The site-wide and mentee queues keep the table (CQ-D3).
        $this->assertFalse(queue_view::wanted(0, 'grid'));
        // The default stays the table in this stage.
        $this->assertFalse(queue_view::wanted(5, ''));
        $this->assertFalse(queue_view::wanted(5, 'table'));
        $this->assertFalse(queue_view::wanted(5, 'cards'));

        // The two addresses differ only by the view parameter, and both keep the filters.
        $url = new moodle_url('/enrol/apply/manage.php', ['id' => 5, 'status' => 2, 'search' => 'Ann']);
        $grid = queue_view::url($url, true);
        $this->assertSame('grid', $grid->get_param('view'));
        $this->assertSame('2', (string) $grid->get_param('status'));
        $this->assertSame('Ann', $grid->get_param('search'));
        $table = queue_view::url($grid, false);
        $this->assertNull($table->get_param('view'));
        $this->assertSame('Ann', $table->get_param('search'));
        // The address handed in is left alone.
        $this->assertNull($url->get_param('view'));
    }

    /**
     * The props carry configuration and strings, never an application: no name, address or comment, and
     * only the keys the client reads.
     *
     * Changes that must make it fail: rows, cells or a summary added to the props; a key renamed without
     * the client (js/esm/src/queue/types.ts, ViewProps); the mount no longer the queue's.
     *
     * @return void
     */
    public function test_the_props_carry_no_row(): void {
        global $PAGE;

        $this->setAdminUser();
        $shell = $this->shell();
        $props = $shell->props();

        $this->assertSame([
            'mount', 'enrolid', 'initial', 'perpage', 'perpages', 'searchmin', 'pageurl', 'statuses', 'reporturl', 'labels',
        ], array_keys($props));
        $this->assertSame(queue_view::MOUNT, $props['mount']);
        $this->assertSame('queue', $props['mount']);
        $this->assertSame((int) $this->instance->id, $props['enrolid']);
        $this->assertSame(50, $props['perpage']);
        $this->assertSame([30, 50, 100], $props['perpages']);
        $this->assertSame(['pending' => ENROL_USER_SUSPENDED, 'deferred' => ENROL_APPLY_USER_WAIT], $props['statuses']);
        $this->assertStringContainsString('/enrol/apply/manage.php?id=' . $this->instance->id, $props['pageurl']);

        // Control: the applicant is in the queue the shell was built for.
        $html = $PAGE->get_renderer('core')->render($shell);
        $this->assertStringContainsString('Quintessence', $html);

        $json = json_encode($props);
        foreach (['Zebedee', 'Quintessence', 'zebedee.quintessence', 'Marmalade'] as $fact) {
            $this->assertStringNotContainsString($fact, $json);
        }
    }

    /**
     * Every string the client draws is a real one, and the ones the bar, the panel, the pager and the tiles read are there.
     *
     * Changes that must make it fail: a label whose string id does not exist; one the shared parts read removed.
     *
     * @return void
     */
    public function test_the_strings_are_present(): void {
        $labels = queue_view::labels();
        foreach ($labels as $name => $text) {
            $this->assertIsString($text, $name);
            $this->assertNotSame('', trim($text), $name);
            $this->assertStringStartsNotWith('[[', $text, "{$name} names a string that does not exist");
        }
        foreach ([
            'all', 'pending', 'deferred', 'scope', 'count', 'count_one', 'filters', 'filterson', 'columns', 'presets', 'always',
            'defaultcolumns', 'resetwidths', 'view', 'viewauto', 'viewautohint', 'table', 'cards', 'coretable', 'search',
            'cleartext', 'removefilter', 'searchchip', 'clearall', 'any', 'clearfilters', 'show', 'showcount', 'grid',
            'caption', 'resize', 'more', 'less', 'loading', 'loaderror', 'retry', 'empty', 'nomatch', 'nomatchhelp',
            'scrollhint', 'range', 'pageof', 'pages', 'previous', 'next', 'perpage', 'review', 'reviewapplicant',
            'placestaken', 'held', 'ofmany', 'awaiting', 'oldest', 'late', 'status', 'open', 'closed', 'closes',
            'openreport', 'preseteverything', 'presetglance', 'presetanswers', 'notingroup', 'narrowed',
        ] as $name) {
            $this->assertArrayHasKey($name, $labels);
        }
        // No Download in the queue (CQ-D11).
        $this->assertArrayNotHasKey('download', $labels);
        $this->assertSame('Grid view', get_string('queueviewgrid', 'enrol_apply'));
    }

    /**
     * The shell mounts the bundle and keeps today's queue in its noscript; outside the noscript no
     * application is in the page, and the link back to the table carries no view parameter.
     *
     * Changes that must make it fail: the mount naming a per-file module; the noscript dropped; the
     * table link leading to the grid again.
     *
     * @return void
     */
    public function test_the_shell_mounts_the_bundle_and_keeps_todays_queue_for_no_javascript(): void {
        global $PAGE;

        $this->setAdminUser();
        $html = $PAGE->get_renderer('core')->render($this->shell());

        $this->assertStringContainsString('data-react-component="@moodle/lms/enrol_apply/bundle"', $html);
        $this->assertStringContainsString('data-enrol-apply-queue="tablelink"', $html);

        $at = strpos($html, '<noscript>');
        $this->assertNotFalse($at);
        $before = substr($html, 0, $at);
        $noscript = substr($html, $at);
        // The link back to the table is the table's address: no view parameter.
        $this->assertStringContainsString('/enrol/apply/manage.php?id=' . $this->instance->id, $before);
        $this->assertStringNotContainsString('view=grid', $before);
        // Today's queue, its decision form included: a browser without JavaScript can still decide.
        $this->assertStringContainsString('Quintessence', $noscript);
        $this->assertStringContainsString('<form id="enrol_apply_manage_form"', $noscript);
        $this->assertStringNotContainsString('Quintessence', $before);
    }

    /**
     * The first paint reads the address: the scope from manage.php's status, its filters and search as
     * it cleaned them, the facets, the sort and the page from the request, each cleaned to what the
     * view accepts.
     *
     * Changes that must make it fail: the status read as another scope; a facet the table's controls
     * do not send lost; a page size outside 30, 50 or 100 kept.
     *
     * @return void
     */
    public function test_the_first_paint_reads_the_address(): void {
        $none = queue_view::initial('', null, []);
        $this->assertSame([
            'scope' => 'all',
            'search' => '',
            'filters' => [],
            'sort' => '',
            'direction' => 'asc',
            'page' => 0,
            'perpage' => 50,
        ], $none);
        $this->assertSame('pending', queue_view::initial('', ENROL_USER_SUSPENDED, [])['scope']);
        $this->assertSame('deferred', queue_view::initial('', ENROL_APPLY_USER_WAIT, [])['scope']);

        $_GET['named'] = 'key';
        $_GET['waited'] = '7';
        $_GET['appliedbefore'] = '1';
        $_GET['sort'] = 'answer:s_city';
        $_GET['dir'] = 'desc';
        $_GET['page'] = '2';
        $_GET['perpage'] = '100';
        try {
            $read = queue_view::initial('Ann', ENROL_APPLY_USER_WAIT, ['group' => 'mine']);
            $_GET['perpage'] = '75';
            $_GET['dir'] = 'sideways';
            $odd = queue_view::initial('', null, []);
        } finally {
            unset($_GET['named'], $_GET['waited'], $_GET['appliedbefore'], $_GET['sort']);
            unset($_GET['dir'], $_GET['page'], $_GET['perpage']);
        }

        $this->assertSame('deferred', $read['scope']);
        $this->assertSame('Ann', $read['search']);
        $this->assertSame([
            ['name' => 'group', 'value' => 'mine'],
            ['name' => 'named', 'value' => 'key'],
            ['name' => 'waited', 'value' => '7'],
            ['name' => 'appliedbefore', 'value' => '1'],
        ], $read['filters']);
        $this->assertSame('answer:s_city', $read['sort']);
        $this->assertSame('desc', $read['direction']);
        $this->assertSame(2, $read['page']);
        $this->assertSame(100, $read['perpage']);

        $this->assertSame(50, $odd['perpage']);
        $this->assertSame('asc', $odd['direction']);
    }

    /**
     * "Open the report" is offered only to a holder of enrol/apply:viewreports in the method's course (CQ-D11).
     *
     * One reader both ways: an editing teacher decides on the queue and, by archetype, may not open the
     * report; granted the capability, the same reader is offered the link.
     *
     * Changes that must make it fail: the link offered to every reader of the queue; withheld from a holder.
     *
     * @return void
     */
    public function test_the_report_link_is_offered_only_to_a_holder_of_viewreports(): void {
        global $DB;

        $report = '/enrol/apply/report.php?id=' . $this->instance->id;
        $this->setAdminUser();
        $this->assertStringContainsString($report, $this->shell()->props()['reporturl']);

        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $context = context_course::instance($this->course->id);
        $this->setUser($teacher);
        // Preconditions: the teacher opens the queue, and holds no report capability.
        $this->assertTrue(\enrol_apply\local\queue::listing_scope((int) $this->instance->id)->allowed);
        $this->assertFalse(has_capability('enrol/apply:viewreports', $context));
        $this->assertSame('', $this->shell()->props()['reporturl']);

        $role = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('enrol/apply:viewreports', CAP_ALLOW, $role, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertStringContainsString($report, $this->shell()->props()['reporturl']);
    }
}
