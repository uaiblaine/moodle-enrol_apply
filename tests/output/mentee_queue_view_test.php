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
 * Tests for the mentee queue's grid view shell (stage W5 of the site-wide queue as a report, SQ-D13).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\output;

use context_system;
use enrol_apply\local\queue;
use enrol_apply\local\submission;
use enrol_apply\table\applications;
use moodle_url;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What the server gives a mentor's id-less page (W5): the grid wherever the listing is allowed and the
 * address does not ask for the table, never for a refused listing; a shell of the `mentees` kind with no
 * row, no report link and no "By course" view; the decision sheet's offer of the table form with no
 * instance (the three decisions, no chooser); and an address read for the first paint, and rebuilt for the
 * page a decision lands on, with only what the mentee table reads: the site-wide queue's course, category,
 * method, grouped and show are dropped, each beside the control that a site-wide decider's same request
 * keeps them.
 *
 * Two courses with one apply method each. The mentor mentors Annabel (course one) and Bartholomew (course
 * two); Zacharias applied to course one too and is nobody's mentee. The other readers: the administrator
 * (a site-wide decider) and an ordinary user, whom the id-less page refuses.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(queue_view::class)]
final class mentee_queue_view_test extends \advanced_testcase {
    /** @var \stdClass[] The courses, by key: one, two. */
    private $courses = [];

    /** @var \stdClass[] Their apply methods, by the same keys. */
    private $methods = [];

    /** @var \stdClass The mentor of Annabel and Bartholomew. */
    private $mentor;

    /** @var \stdClass A user holding nothing. */
    private $ordinary;

    /**
     * Two courses with a method each, three applications, the mentor and an ordinary user.
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

        foreach (['one', 'two'] as $key) {
            $this->courses[$key] = $generator->create_course(['fullname' => 'Course ' . $key]);
            $id = $plugin->add_instance($this->courses[$key], ['name' => 'Intake ' . $key] + $plugin->get_instance_defaults());
            $this->methods[$key] = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        }
        // A group a chooser would list, were one offered.
        $generator->create_group(['courseid' => $this->courses['one']->id, 'name' => 'Evening cohort']);

        $applicants = [];
        foreach (['Annabel' => 'one', 'Bartholomew' => 'two', 'Zacharias' => 'one'] as $name => $key) {
            $applicant = $generator->create_user(['firstname' => $name, 'lastname' => 'Quintessence']);
            $applicants[$name] = $applicant;
            $plugin->enrol_user($this->methods[$key], $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
            $ueid = (int) $DB->get_field(
                'user_enrolments',
                'id',
                ['userid' => $applicant->id, 'enrolid' => $this->methods[$key]->id],
                MUST_EXIST
            );
            $DB->insert_record('enrol_apply_submission', (object) [
                'courseid' => $this->courses[$key]->id,
                'userid' => $applicant->id,
                'enrolid' => $this->methods[$key]->id,
                'userenrolmentid' => $ueid,
                'comment' => 'Marmalade comment',
                'status' => submission::STATUS_PENDING,
                'outcomemessage' => '',
                'timecreated' => time() - DAYSECS,
                'timedecided' => 0,
                'decidedby' => 0,
            ]);
        }

        $role = $generator->create_role(['shortname' => 'menteementor']);
        set_role_contextlevels($role, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $role, context_system::instance()->id, true);
        $this->mentor = $generator->create_user();
        foreach (['Annabel', 'Bartholomew'] as $name) {
            role_assign($role, $this->mentor->id, \context_user::instance($applicants[$name]->id)->id);
        }

        $this->ordinary = $generator->create_user();

        $PAGE->set_context(context_system::instance());
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
     * The id-less page's shell as manage.php builds it for the current user.
     *
     * @return queue_view The shell.
     */
    private function shell(): queue_view {
        $listing = queue::listing_scope(0);

        return new queue_view(
            null,
            applications::for_scope(0),
            new moodle_url('/enrol/apply/manage.php'),
            queue_view::initial('', null, [], $listing)
        );
    }

    /**
     * An address carrying every parameter of the site-wide queue, the mentee table's ones included.
     *
     * @return array The query string, name => value.
     */
    private function wide_address(): array {
        return [
            'course' => (string) $this->courses['one']->id,
            'category' => (string) $this->courses['one']->category,
            'method' => (string) $this->methods['one']->id,
            'grouped' => 'yes',
            'show' => queue_view::METHODS,
            'waited' => '7',
            'sort' => 'course',
            'dir' => 'desc',
            'page' => '1',
            'perpage' => '30',
        ];
    }

    /**
     * A mentor's id-less page is the grid, and the table under view=table; a refused listing never mounts the
     * grid, whatever the address says.
     *
     * Changes that must make it fail: the allowed condition dropped from wanted() (the ordinary user's refused
     * listing would mount it); the mentee scope refused the grid again; view=table ignored for a mentor.
     *
     * @return void
     */
    public function test_the_mentee_queue_takes_the_grid_and_a_refused_listing_never_does(): void {
        $this->setUser($this->mentor);
        $listing = queue::listing_scope(0);
        // Preconditions: the mentor opens their mentees' queue, deciding.
        $this->assertTrue($listing->allowed);
        $this->assertCount(2, $listing->mentees);
        $this->assertTrue($listing->candecide);
        $this->assertSame('mentees', applications::kind_of($listing));

        $this->assertTrue(queue_view::wanted($listing, ''));
        $this->assertTrue(queue_view::wanted($listing, 'grid'));
        $this->assertFalse(queue_view::wanted($listing, queue_view::TABLE));

        // A user the id-less page refuses is never given the grid.
        $this->setUser($this->ordinary);
        $listing = queue::listing_scope(0);
        $this->assertFalse($listing->allowed);
        $this->assertFalse(queue_view::wanted($listing, ''));
        $this->assertFalse(queue_view::wanted($listing, 'grid'));

        // Nor is an id that names no apply method, read by a reader who opens every queue.
        $this->setAdminUser();
        $refused = queue::listing_scope(999999);
        $this->assertFalse($refused->allowed);
        $this->assertFalse(queue_view::wanted($refused, ''));
        // The control: the same administrator's id-less listing takes the grid.
        $this->assertTrue(queue_view::wanted(queue::listing_scope(0), ''));
    }

    /**
     * The mentor's shell is the `mentees` kind with no row, no report link and no "By course" view, and offers
     * the table form's three decisions and the sesskey, with no chooser and no summary. The noscript is the
     * mentee table, with its decision form, and lists nobody else's application.
     *
     * Changes that must make it fail: the report link given to a reader of the queues that span courses; the
     * "By course" view offered to a mentor; a group or role chooser offered on the mentee queue; a row, a
     * name or a comment in the props.
     *
     * @return void
     */
    public function test_the_mentor_props_are_the_reduced_queue_with_the_three_decisions(): void {
        global $PAGE;

        // The control: a site-wide decider's shell offers the "By course" view; neither offers a report.
        $this->setAdminUser();
        $site = $this->shell()->props();
        $this->assertSame('site', $site['kind']);
        $this->assertNotNull($site['methods']);
        $this->assertSame('', $site['reporturl']);

        $this->setUser($this->mentor);
        $shell = $this->shell();
        // Precondition: the mentor decides on the mentee queue.
        $this->assertTrue(applications::for_scope(0)->can_decide());
        $props = $shell->props();
        $this->assertSame('queue', $props['mount']);
        $this->assertSame(0, $props['enrolid']);
        $this->assertSame('mentees', $props['kind']);
        $this->assertSame('', $props['reporturl']);
        $this->assertFalse($shell->offers_methods());
        $this->assertNull($props['methods']);
        $page = new moodle_url($props['pageurl']);
        $this->assertSame([], $page->params());

        $offer = $props['decision'];
        $this->assertNotNull($offer);
        $this->assertSame(sesskey(), $offer['sesskey']);
        $this->assertSame(['confirm', 'wait', 'cancel'], array_column($offer['actions'], 'value'));
        $this->assertNull($offer['groups']);
        $this->assertNull($offer['roles']);
        $this->assertNull($offer['summary']);
        $this->assertSame('', $offer['placesfull']);

        // The two tiles the client draws for this kind read the queue's own labels.
        foreach (['awaiting', 'oldest', 'late', 'deferred', 'deferredshare'] as $label) {
            $this->assertNotSame('', $props['labels'][$label], $label);
        }

        $json = json_encode($props);
        foreach (['Annabel', 'Bartholomew', 'Zacharias', 'Quintessence', 'Marmalade', 'Course one', 'Evening'] as $fact) {
            $this->assertStringNotContainsString($fact, $json, $fact);
        }

        $exported = $shell->export_for_template($PAGE->get_renderer('core'));
        $table = new moodle_url(html_entity_decode($exported['tableurl'], ENT_QUOTES));
        $this->assertSame('table', $table->get_param('view'));
        $this->assertNull($table->get_param('id'));
        $this->assertStringContainsString('Annabel', $exported['noscript']);
        $this->assertStringContainsString('Bartholomew', $exported['noscript']);
        $this->assertStringNotContainsString('Zacharias', $exported['noscript']);
        $this->assertStringContainsString('<form id="enrol_apply_manage_form"', $exported['noscript']);
    }

    /**
     * A mentor's first paint reads what the mentee table reads: the scope, the search, the waited facet, the
     * sort, the page and the page size. The site-wide queue's course, category, method, grouped and show are
     * ignored although the address carries them; the same address read for a site-wide decider keeps each.
     *
     * Changes that must make it fail: the course and category read for any listing in
     * applications::request_filters(); the site-wide queue's facets read for a mentor; `show` read for a
     * mentor.
     *
     * @return void
     */
    public function test_the_mentors_first_paint_reads_only_what_the_mentee_table_reads(): void {
        $_GET = $this->wide_address();

        $this->setUser($this->mentor);
        $listing = queue::listing_scope(0);
        $initial = queue_view::initial('', null, applications::request_filters($listing), $listing);
        $this->assertSame([['name' => 'waited', 'value' => '7']], $initial['filters']);
        $this->assertSame('', $initial['show']);
        $this->assertSame('course', $initial['sort']);
        $this->assertSame('desc', $initial['direction']);
        $this->assertSame(1, $initial['page']);
        $this->assertSame(30, $initial['perpage']);

        // The control: the same address, read for a site-wide decider, keeps every one of them.
        $this->setAdminUser();
        $listing = queue::listing_scope(0);
        $initial = queue_view::initial('', null, applications::request_filters($listing), $listing);
        $read = array_column($initial['filters'], 'value', 'name');
        $this->assertSame((string) $this->courses['one']->id, $read['course'] ?? null);
        $this->assertSame((string) $this->courses['one']->category, $read['category'] ?? null);
        $this->assertSame((string) $this->methods['one']->id, $read['method'] ?? null);
        $this->assertSame('yes', $read['grouped'] ?? null);
        $this->assertSame('7', $read['waited'] ?? null);
        $this->assertSame(queue_view::METHODS, $initial['show']);
    }

    /**
     * The address a mentor's decision lands on is the id-less page with only what the mentee table applies:
     * no course, category, method, grouped or show, whatever the address it was posted from carried; the
     * same request from a site-wide decider keeps the course, the category, the method and grouped.
     *
     * Changes that must make it fail: the site-wide filters applied to a mentor's table, or carried into the
     * address unread; the address read as the site-wide queue's for a mentor.
     *
     * @return void
     */
    public function test_the_address_a_mentors_decision_returns_to_drops_the_site_wide_filters(): void {
        $read = function (): array {
            $_GET = $this->wide_address();
            try {
                $filters = applications::request_filters(queue::listing_scope(0));

                return queue_view::return_url(0, 'Annabel', ENROL_USER_SUSPENDED, $filters)->params();
            } finally {
                $_GET = [];
            }
        };

        $this->setUser($this->mentor);
        $params = $read();
        foreach (['course', 'category', 'method', 'grouped', 'show', 'id', 'view'] as $name) {
            $this->assertArrayNotHasKey($name, $params, $name);
        }
        $this->assertSame('Annabel', $params['search']);
        $this->assertSame((string) ENROL_USER_SUSPENDED, (string) $params['status']);
        $this->assertSame('7', (string) $params['waited']);
        $this->assertSame('course', $params['sort']);
        $this->assertSame('desc', $params['dir']);
        $this->assertSame('1', (string) $params['page']);
        $this->assertSame('30', (string) $params['perpage']);

        // The control: a site-wide decider's same request keeps the site-wide queue's filters.
        $this->setAdminUser();
        $params = $read();
        $this->assertSame((string) $this->courses['one']->id, (string) $params['course']);
        $this->assertSame((string) $this->courses['one']->category, (string) $params['category']);
        $this->assertSame((string) $this->methods['one']->id, (string) $params['method']);
        $this->assertSame('yes', $params['grouped']);
        $this->assertArrayNotHasKey('show', $params);
    }
}
