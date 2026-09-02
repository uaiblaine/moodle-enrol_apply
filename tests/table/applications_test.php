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
 * Tests for the contract the applications queue has with core's dynamic table service.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\table;

use core_table\local\filter\integer_filter;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * Tests for the contract the applications queue has with core's dynamic table service.
 *
 * **What this file is for, and it is not the queue's rows.** Those are covered by queue_test and
 * identity_test, which read the listing. This one holds the four things core calls on the way in
 * - get_filterset_class(), set_filterset(), get_context() and has_capability() - because between
 * them they decide who sees which applications, on a path the client addresses directly and that
 * no page script runs.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(applications::class)]
#[CoversClass(applications_filterset::class)]
final class applications_test extends \advanced_testcase {
    /** @var \stdClass The course the applications are made to. */
    protected $course;

    /** @var \stdClass The apply enrol instance. */
    protected $instance;

    /** @var \enrol_plugin The apply plugin. */
    protected $plugin;

    /**
     * Build a course with an apply instance.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB, $PAGE;

        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $instanceid = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        /* The queue's table is dynamic, and get_dynamic_table_html_end() builds its
           "show all" link from $PAGE->url - so rendering one without a page url makes core
           emit a debugging() call, which advanced_testcase turns into a notice. manage.php
           always sets it; a test that renders the table is standing in for that page. */
        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php'));
    }

    /**
     * Put one applicant on the given instance's queue.
     *
     * @param \stdClass|null $instance Instance to apply to, null for this fixture's own.
     * @return \stdClass The applicant.
     */
    protected function applicant(?\stdClass $instance = null): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($instance ?? $this->instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);

        return $user;
    }

    /**
     * A mentor of the given applicant, holding the capability nowhere else.
     *
     * @param \stdClass $mentee The applicant they mentor.
     * @return \stdClass The mentor.
     */
    protected function mentor(\stdClass $mentee): \stdClass {
        $mentor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applymentor']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $mentor->id, \context_user::instance($mentee->id)->id);

        return $mentor;
    }

    /**
     * The user enrolment ids a scope lists, for the current user.
     *
     * @param int $enrolid Enrol instance to list, 0 for the scope with no instance.
     * @return array User enrolment ids.
     */
    protected function listed(int $enrolid): array {
        $table = applications::for_scope($enrolid);

        ob_start();
        $table->out(50, false);
        ob_end_clean();

        return array_map(static fn($row) => (int) $row->userenrolmentid, array_values($table->rawdata));
    }

    /**
     * Core finds the filterset by deriving its name, so the two names must agree.
     *
     * flexible_table::get_filterset_class() returns `static::class . '_filterset'` and get.php
     * refuses a name that does not resolve. Nothing else notices: the page path builds the
     * filterset by hand, so renaming either half leaves the page working and breaks only the
     * refreshes, which is the asymmetry worth a test of its own.
     *
     * @return void
     */
    public function test_the_filterset_class_is_the_one_core_derives(): void {
        $derived = applications::get_filterset_class();

        $this->assertSame(applications_filterset::class, $derived);
        $this->assertTrue(class_exists($derived));
    }

    /**
     * A filterset that names no scope is refused rather than silently taken as the widest one.
     *
     * The whole reason set_filterset() calls check_validity() itself: get.php never does, so
     * "required" in applications_filterset is a claim only that line enforces. Zero is a
     * meaningful scope here - every application this operator may decide on - so a request that
     * forgot to say which one it meant would otherwise get the widest one and look correct.
     *
     * @return void
     */
    public function test_a_filterset_naming_no_scope_is_refused(): void {
        $this->setAdminUser();

        $table = new applications();

        $this->expectException(\moodle_exception::class);
        $table->set_filterset(new applications_filterset());
    }

    /**
     * A scope filter that is PRESENT but empty is refused too.
     *
     * check_validity() does not reach this: it tests array_key_exists() against the filterset's
     * map and stops, so a filter carrying no values satisfies it. That is a well-formed request
     * to core's own service - `values` is a multiple structure and an empty array validates - and
     * filter::current() answers null for one, because rewind() only takes a position when there
     * is at least one value. `(int) null` is 0, and 0 is not a refusal here: it is the widest
     * scope this queue has.
     *
     * @return void
     */
    public function test_a_scope_filter_carrying_no_value_is_refused(): void {
        $this->setAdminUser();

        $filterset = new applications_filterset();
        $filterset->add_filter(new integer_filter('enrolid'));

        /* The precondition, and it is the whole reason this test is separate from the one above:
           the filterset really does satisfy check_validity(), so what follows is about the gap
           that leaves rather than about a missing filter. */
        $filterset->check_validity();

        $table = new applications();

        $this->expectException(\moodle_exception::class);
        $table->set_filterset($filterset);
    }

    /**
     * A reader with neither the site-wide capability nor any mentees is refused the wide scope.
     *
     * The third branch of queue::listing_scope(), and the only one whose refusal is reached by an
     * empty mentee list rather than by a failed capability check. Without this the branch is
     * exercised only by readers it lets through.
     *
     * @return void
     */
    public function test_a_reader_with_no_capability_and_no_mentees_is_refused_the_wide_scope(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertFalse(applications::for_scope(0)->has_capability());
        /* The control: the same scope allows a reader who does hold it, so this is not a scope
           that refuses everybody. */
        $this->setAdminUser();
        $this->assertTrue(applications::for_scope(0)->has_capability());
    }

    /**
     * The instance scope reads in the course's context and the others in the system context.
     *
     * get.php calls get_context() before has_capability() and hands the result to
     * validate_context(), so this is what decides which course's access test is applied.
     *
     * @return void
     */
    public function test_the_context_follows_the_scope(): void {
        $this->setAdminUser();

        $this->assertEquals(
            \context_course::instance($this->course->id),
            applications::for_scope((int) $this->instance->id)->get_context()
        );
        $this->assertEquals(
            \context_system::instance(),
            applications::for_scope(0)->get_context()
        );
    }

    /**
     * An id naming no apply instance is refused, and refused without throwing.
     *
     * Both halves matter and they pull in opposite directions. get_context() returns a context,
     * so a resolver answering false for a refusal would produce a TypeError on the line that
     * calls it; and a resolver that threw would answer a forged filter value with a database
     * exception rather than "no permission".
     *
     * @return void
     */
    public function test_an_unresolvable_instance_is_refused_without_throwing(): void {
        $this->setAdminUser();

        $table = applications::for_scope(-1);

        $this->assertEquals(\context_system::instance(), $table->get_context());
        $this->assertFalse($table->has_capability());
    }

    /**
     * A reader without the capability in the instance's course is refused.
     *
     * @return void
     */
    public function test_a_reader_without_the_capability_is_refused(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertFalse(applications::for_scope((int) $this->instance->id)->has_capability());
        // The control: the same table allows somebody who does hold it, so this is not always false.
        $this->setAdminUser();
        $this->assertTrue(applications::for_scope((int) $this->instance->id)->has_capability());
    }

    /**
     * The base url names the instance, so a page turn without JavaScript keeps the scope.
     *
     * guess_base_url() is what paging and sorting build their anchors from, and set_filterset()
     * calls it. A base url that dropped the id would send the second page of one method's queue
     * to the site-wide one.
     *
     * @return void
     */
    public function test_the_base_url_carries_the_scope(): void {
        $this->setAdminUser();

        $table = applications::for_scope((int) $this->instance->id);

        ob_start();
        $table->setup();
        ob_end_clean();

        $this->assertStringContainsString(
            'id=' . $this->instance->id,
            $table->baseurl->out(false)
        );
    }

    /**
     * A mentor sees their mentees' applications and nobody else's, whatever the request says.
     *
     * **This is the reason the scope is recomputed rather than carried.** The client names one
     * integer and the mentee restriction is derived from the logged-in user, so there is no
     * request a mentor can make that widens their own listing - the filterset has nowhere to put
     * a user id, and the enrolid it does carry is answered by the capability check on that
     * instance's course.
     *
     * @return void
     */
    public function test_the_mentee_scope_lists_only_the_mentees(): void {
        global $DB;

        $mentee = $this->applicant();
        $stranger = $this->applicant();
        $this->setUser($this->mentor($mentee));

        $listed = $this->listed(0);

        $menteeueid = (int) $DB->get_field('user_enrolments', 'id', [
            'userid' => $mentee->id,
            'enrolid' => $this->instance->id,
        ], MUST_EXIST);
        $strangerueid = (int) $DB->get_field('user_enrolments', 'id', [
            'userid' => $stranger->id,
            'enrolid' => $this->instance->id,
        ], MUST_EXIST);

        $this->assertSame([$menteeueid], $listed);
        /* The control that makes the assertion above non-vacuous: the stranger's application is
           really on the queue, so "not listed" is a decision this scope took rather than a row
           that was never there. An administrator sees both. */
        $this->setAdminUser();
        $this->assertEqualsCanonicalizing([$menteeueid, $strangerueid], $this->listed(0));
    }

    /**
     * Naming another course's instance does not get a mentor that course's queue.
     *
     * The forged-value case, and it is answered by the capability check against the named
     * instance's own course rather than by anything the filterset knows.
     *
     * @return void
     */
    public function test_a_mentor_naming_an_instance_is_refused_by_that_courses_capability(): void {
        $mentee = $this->applicant();
        $this->setUser($this->mentor($mentee));

        $this->assertFalse(applications::for_scope((int) $this->instance->id)->has_capability());
        /* And the scope they DO hold still works, so the refusal above is about the instance and
           not about the operator. */
        $this->assertTrue(applications::for_scope(0)->has_capability());
    }
}
