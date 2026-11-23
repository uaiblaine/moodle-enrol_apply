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
 * Tests for the site-wide queue's grid view shell (stage W3 of the site-wide queue as a report).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\output;

use context_system;
use enrol_apply\local\queue;
use enrol_apply\local\queueengine;
use enrol_apply\local\submission;
use enrol_apply\table\applications;
use moodle_url;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What the server adds for the site-wide queue's grid (W3): the id-less page's grid for a site-wide decider
 * or viewer and nobody else (the mentee queue keeps the table until W5), a shell with no row, no report link
 * and the listing's kind, the decision sheet's offer of the table form with no instance (no chooser, SQ-D10;
 * nothing for a viewer), and the address read for the first paint and rebuilt for the page a decision
 * lands on, through the queue's own cleaning.
 *
 * Two courses, each with one apply method and one pending application; a third course has no apply method.
 * The readers: the administrator (a site-wide decider), a site-wide viewer (a role holding
 * enrol/apply:viewapplications at the system context), a mentor of the first applicant, and an editing
 * teacher of the first course.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(queue_view::class)]
final class site_queue_view_test extends \advanced_testcase {
    /** @var \stdClass[] The two apply methods, by course key (one, two). */
    private $methods = [];

    /** @var \stdClass[] The courses: one, two, and none (no apply method). */
    private $courses = [];

    /** @var \stdClass A site-wide viewer. */
    private $viewer;

    /** @var \stdClass The mentor of the first applicant. */
    private $mentor;

    /** @var \stdClass An editing teacher of the first course. */
    private $teacher;

    /**
     * Two courses with a method and an application each, a course with none, and the readers.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG, $DB, $PAGE;

        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        $plugin = enrol_get_plugin('apply');
        $generator = $this->getDataGenerator();

        $applicants = [];
        foreach (['one' => 'Zebedee', 'two' => 'Xanthippe'] as $key => $name) {
            $this->courses[$key] = $generator->create_course(['fullname' => 'Course ' . $key]);
            $id = $plugin->add_instance($this->courses[$key], ['name' => 'Intake ' . $key] + $plugin->get_instance_defaults());
            $this->methods[$key] = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

            $applicant = $generator->create_user(['firstname' => $name, 'lastname' => 'Quintessence']);
            $applicants[$key] = $applicant;
            $plugin->enrol_user($this->methods[$key], $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
            $ueid = (int) $DB->get_field(
                'user_enrolments',
                'id',
                ['userid' => $applicant->id, 'enrolid' => $id],
                MUST_EXIST
            );
            $DB->insert_record('enrol_apply_submission', (object) [
                'courseid' => $this->courses[$key]->id,
                'userid' => $applicant->id,
                'enrolid' => $id,
                'userenrolmentid' => $ueid,
                'comment' => 'Marmalade comment',
                'status' => submission::STATUS_PENDING,
                'outcomemessage' => '',
                'timecreated' => time() - DAYSECS,
                'timedecided' => 0,
                'decidedby' => 0,
            ]);
        }
        $this->courses['none'] = $generator->create_course(['fullname' => 'Course without a method']);

        $system = context_system::instance();
        $viewerrole = $generator->create_role(['shortname' => 'siteviewer']);
        assign_capability('enrol/apply:viewapplications', CAP_ALLOW, $viewerrole, $system->id, true);
        $this->viewer = $generator->create_user();
        role_assign($viewerrole, $this->viewer->id, $system->id);

        $mentorrole = $generator->create_role(['shortname' => 'sitementor']);
        set_role_contextlevels($mentorrole, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $mentorrole, $system->id, true);
        $this->mentor = $generator->create_user();
        role_assign($mentorrole, $this->mentor->id, \context_user::instance($applicants['one']->id)->id);

        $this->teacher = $generator->create_and_enrol($this->courses['one'], 'editingteacher');

        $PAGE->set_context($system);
        $PAGE->set_url(new moodle_url('/enrol/apply/manage.php'));
    }

    /**
     * Clear the request a case read.
     *
     * @return void
     */
    protected function tearDown(): void {
        $_GET = [];
        $_POST = [];
        parent::tearDown();
    }

    /**
     * The site-wide queue's shell as manage.php builds it for the current user.
     *
     * @return queue_view The shell.
     */
    private function shell(): queue_view {
        $url = new moodle_url('/enrol/apply/manage.php');
        $table = applications::for_scope(0);

        return new queue_view(null, $table, $url, queue_view::initial('', null, [], true));
    }

    /**
     * The id-less page is the grid for a site-wide decider and a site-wide viewer, the table under
     * view=table, and the table for a mentor (stage W5 turns that) and for a reader the page refuses.
     *
     * Changes that must make it fail: the site-wide scope refused the grid; the mentee condition dropped
     * (a mentor's page would mount it); view=table ignored on the site-wide queue.
     *
     * @return void
     */
    public function test_the_site_wide_queue_takes_the_grid_and_the_mentee_queue_keeps_the_table(): void {
        foreach (['decider' => get_admin(), 'viewer' => $this->viewer] as $who => $user) {
            $this->setUser($user);
            $listing = queue::listing_scope(0);
            // Preconditions: the reader opens the site-wide queue, deciding or not.
            $this->assertTrue($listing->allowed, $who);
            $this->assertNull($listing->instance, $who);
            $this->assertNull($listing->mentees, $who);
            $this->assertSame($who === 'decider', $listing->candecide, $who);

            $this->assertTrue(queue_view::wanted($listing, ''), $who);
            $this->assertTrue(queue_view::wanted($listing, 'grid'), $who);
            $this->assertFalse(queue_view::wanted($listing, queue_view::TABLE), $who);
        }

        // A mentor opens their mentees' queue, which keeps the table in this stage, asked for or not.
        $this->setUser($this->mentor);
        $listing = queue::listing_scope(0);
        $this->assertTrue($listing->allowed);
        $this->assertNotEmpty($listing->mentees);
        $this->assertFalse(queue_view::wanted($listing, ''));
        $this->assertFalse(queue_view::wanted($listing, 'grid'));

        // An editing teacher of one course is refused the id-less page, and is never given its grid.
        $this->setUser($this->teacher);
        $listing = queue::listing_scope(0);
        $this->assertFalse($listing->allowed);
        $this->assertFalse(queue_view::wanted($listing, ''));
    }

    /**
     * The site-wide shell carries the kind, the id-less address and no row, and no report link: the
     * report belongs to a course. The noscript is today's site-wide table with its form, and the link to
     * it asks for view=table with no id.
     *
     * Changes that must make it fail: a row, a name or a comment in the props; the report link offered
     * on the site-wide queue; the page address naming a method.
     *
     * @return void
     */
    public function test_the_site_wide_props_carry_the_kind_and_no_row_and_no_report(): void {
        global $PAGE;

        $this->setAdminUser();
        $shell = $this->shell();
        $props = $shell->props();

        $this->assertSame('queue', $props['mount']);
        $this->assertSame(0, $props['enrolid']);
        $this->assertSame('site', $props['kind']);
        $this->assertSame('', $props['reporturl']);
        $page = new moodle_url($props['pageurl']);
        $this->assertSame((new moodle_url('/enrol/apply/manage.php'))->get_path(false), $page->get_path(false));
        $this->assertSame([], $page->params());

        $json = json_encode($props);
        foreach (['Zebedee', 'Xanthippe', 'Quintessence', 'Marmalade', 'Course one'] as $fact) {
            $this->assertStringNotContainsString($fact, $json);
        }

        $exported = $shell->export_for_template($PAGE->get_renderer('core'));
        $table = new moodle_url(html_entity_decode($exported['tableurl'], ENT_QUOTES));
        $this->assertSame('table', $table->get_param('view'));
        $this->assertNull($table->get_param('id'));
        // The control: both courses' applicants are in today's table, the noscript, with its form.
        $this->assertStringContainsString('Zebedee', $exported['noscript']);
        $this->assertStringContainsString('Xanthippe', $exported['noscript']);
        $this->assertStringContainsString('<form id="enrol_apply_manage_form"', $exported['noscript']);

        // The column the client draws from the row's course and method, under the site-wide queue's heading.
        $catalogue = queueengine::catalogue(applications::for_scope(0));
        $this->assertSame(get_string('queuecoursemethod', 'enrol_apply'), $catalogue['course']['title']);
        $this->assertTrue($catalogue['course']['sortable']);
    }

    /**
     * The decision sheet of the site-wide queue offers the table's three decisions and the sesskey, and no
     * group or role chooser and no summary (SQ-D10); a site-wide viewer is offered nothing.
     *
     * The first course has a group, so a chooser would have something to list were it offered.
     *
     * Changes that must make it fail: the decision controls offered to a viewer; a group or role chooser
     * (even empty) offered on the site-wide queue.
     *
     * @return void
     */
    public function test_the_site_wide_decision_offers_the_three_actions_and_no_chooser_and_a_viewer_nothing(): void {
        $this->getDataGenerator()->create_group(['courseid' => $this->courses['one']->id, 'name' => 'Evening cohort']);

        $this->setAdminUser();
        // Precondition: the administrator decides on the site-wide queue.
        $this->assertTrue(applications::for_scope(0)->can_decide());
        $offer = $this->shell()->props()['decision'];
        $this->assertNotNull($offer);
        $this->assertSame(sesskey(), $offer['sesskey']);
        $this->assertSame(['confirm', 'wait', 'cancel'], array_column($offer['actions'], 'value'));
        $this->assertSame(get_string('outcomemessage', 'enrol_apply'), $offer['message']['label']);
        $this->assertSame(get_string('decisionnote', 'enrol_apply'), $offer['note']['label']);
        $this->assertNull($offer['groups']);
        $this->assertNull($offer['roles']);
        $this->assertNull($offer['summary']);
        $this->assertSame('', $offer['placesfull']);

        $this->setUser($this->viewer);
        // Precondition: the viewer opens the queue and may not decide there.
        $this->assertTrue(queue::listing_scope(0)->allowed);
        $this->assertFalse(applications::for_scope(0)->can_decide());
        $this->assertNull($this->shell()->props()['decision']);
    }

    /**
     * The first paint of the site-wide queue holds only cleaned values: its own facets each in their
     * vocabulary, and a page size of 30, 50 or 100; the course and category are manage.php's cleaning,
     * which offers no course without an apply method.
     *
     * Changes that must make it fail: a method that is no apply instance, a grouped value outside yes and
     * no, or a waited threshold outside the table's kept; a page size outside its vocabulary kept.
     *
     * @return void
     */
    public function test_the_first_paint_of_the_site_wide_queue_reads_only_clean_values(): void {
        $this->setAdminUser();
        $one = (int) $this->methods['one']->id;

        $_GET = [
            'method' => '999999',
            'grouped' => 'maybe',
            'waited' => '5',
            'named' => 'key',
            'appliedbefore' => '1',
            'perpage' => '999',
            'course' => (string) $this->courses['none']->id,
        ];
        $filters = applications::request_filters(queue::listing_scope(0));
        $forged = queue_view::initial('', null, $filters, true);
        $this->assertSame([], $forged['filters']);
        $this->assertSame(50, $forged['perpage']);

        // The control: the same names with values the queue applies come back.
        $_GET = [
            'method' => (string) $one,
            'grouped' => 'yes',
            'waited' => '7',
            'perpage' => '30',
            'course' => (string) $this->courses['one']->id,
        ];
        $filters = applications::request_filters(queue::listing_scope(0));
        $clean = queue_view::initial('', null, $filters, true);
        $this->assertSame([
            ['name' => 'course', 'value' => (string) $this->courses['one']->id],
            ['name' => 'waited', 'value' => '7'],
            ['name' => 'method', 'value' => (string) $one],
            ['name' => 'grouped', 'value' => 'yes'],
        ], $clean['filters']);
        $this->assertSame(30, $clean['perpage']);
    }

    /**
     * The address a decision from the site-wide grid lands on is the id-less page with only what the
     * queue applies: rebuilt through the site-wide table and its catalogue, never echoed.
     *
     * Forged: a sort the site-wide catalogue does not offer (an answer column, the unsortable comment),
     * a page size outside 30, 50 and 100, a method that is no apply instance, a grouped value outside
     * yes and no, a waited threshold outside the table's, the method queue's own facets, a course without
     * an apply method, a parameter the queue does not read. The control posts valid values, which come
     * back; the defaults are left out.
     *
     * Changes that must make it fail: the sort carried without the catalogue's check; the page size
     * carried unchecked; the facets carried as requested rather than as the table applied them; an id
     * added to the address.
     *
     * @return void
     */
    public function test_the_address_a_site_wide_decision_returns_to_holds_only_clean_values(): void {
        $this->setAdminUser();
        $one = (int) $this->methods['one']->id;
        $path = (new moodle_url('/enrol/apply/manage.php'))->get_path(false);

        $read = function (array $get): moodle_url {
            $_GET = $get;
            try {
                $filters = applications::request_filters(queue::listing_scope(0));

                return queue_view::return_url(0, 'Zebedee', ENROL_USER_SUSPENDED, $filters);
            } finally {
                $_GET = [];
            }
        };

        foreach (['answer:s_city', 'comment', '"><script>'] as $sort) {
            $forged = $read([
                'sort' => $sort,
                'dir' => 'desc',
                'perpage' => '999',
                'page' => '2',
                'method' => '999999',
                'grouped' => 'maybe',
                'waited' => '999',
                'named' => 'key',
                'appliedbefore' => '1',
                'course' => (string) $this->courses['none']->id,
                'foo' => 'bar',
            ]);
            $params = $forged->params();
            $this->assertSame($path, $forged->get_path(false), $sort);
            $this->assertSame('Zebedee', $params['search'], $sort);
            $this->assertSame((string) ENROL_USER_SUSPENDED, (string) $params['status'], $sort);
            $this->assertSame('2', (string) $params['page'], $sort);
            $names = ['id', 'sort', 'dir', 'perpage', 'method', 'grouped', 'waited', 'named', 'appliedbefore'];
            foreach (array_merge($names, ['course', 'foo', 'view']) as $name) {
                $this->assertArrayNotHasKey($name, $params, "{$name} with sort {$sort}");
            }
        }

        // The control: the same names with values the site-wide queue applies come back.
        $clean = $read([
            'sort' => 'course',
            'dir' => 'desc',
            'perpage' => '30',
            'page' => '1',
            'method' => (string) $one,
            'grouped' => 'no',
            'waited' => '1',
            'course' => (string) $this->courses['one']->id,
        ]);
        $params = $clean->params();
        $this->assertSame($path, $clean->get_path(false));
        $this->assertArrayNotHasKey('id', $params);
        $this->assertSame('course', $params['sort']);
        $this->assertSame('desc', $params['dir']);
        $this->assertSame('30', (string) $params['perpage']);
        $this->assertSame('1', (string) $params['page']);
        $this->assertSame((string) $one, (string) $params['method']);
        $this->assertSame('no', $params['grouped']);
        $this->assertSame('1', (string) $params['waited']);
        $this->assertSame((string) $this->courses['one']->id, (string) $params['course']);

        // The default sort and page size are left out, as the client leaves them out.
        $defaults = $read(['sort' => 'wait', 'dir' => 'asc', 'perpage' => '50', 'page' => '0'])->params();
        foreach (['sort', 'dir', 'perpage', 'page'] as $name) {
            $this->assertArrayNotHasKey($name, $defaults, $name);
        }
    }
}
