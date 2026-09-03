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
use enrol_apply\local\submission;
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
        global $DB;

        $instance = $instance ?? $this->instance;
        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);

        /* **The row gets its OWN durable record, and that is not decoration.** Enrolling through
           enrol_user() alone leaves the queue's `s` join NULL, which makes
           `(s.id IS NULL OR prior.id <> s.id)` - the clause excluding this application from
           counting as an earlier one - true whatever it says. Gate CK deletes that clause, and
           against a fixture with no submission of its own it reddened NOTHING: the guard was held
           by a test that could not see it. Found by an adversarial pass, and it is the failure
           this repository's own rule names, arrived at from the fixture end rather than the
           assertion end. */
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $instance->courseid,
            'userid' => $user->id,
            'enrolid' => (int) $instance->id,
            'userenrolmentid' => (int) $DB->get_field('user_enrolments', 'id', [
                'userid' => $user->id,
                'enrolid' => (int) $instance->id,
            ], MUST_EXIST),
            'comment' => 'Please let me in',
            'userinfodata' => '',
            'status' => submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => time(),
            'timedecided' => 0,
            'decidedby' => 0,
        ]);

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
     * The queue rendered for a scope.
     *
     * @param int $enrolid Enrol instance to list, 0 for the scope with no instance.
     * @return string The rendered table.
     */
    protected function rendered(int $enrolid): string {
        $table = applications::for_scope($enrolid);

        ob_start();
        $table->out(50, true);

        return ob_get_clean();
    }

    /**
     * A queue scoped to one method does not repeat that method's course down the page.
     *
     * Every row of an instance-scoped queue belongs to the same course, so the column would say
     * one thing over and over and charge the applicant's own cell the width to do it. The scopes
     * that span courses cannot do without it, which is the control below.
     *
     * @return void
     */
    public function test_the_course_column_is_absent_when_the_url_already_names_the_course(): void {
        $this->setAdminUser();
        $this->applicant();

        $scoped = $this->rendered((int) $this->instance->id);
        $this->assertStringNotContainsString(format_string($this->course->fullname), $scoped);

        /* The control, and it is what makes the assertion above about the COLUMN rather than
           about a course name that was never going to appear: the site-wide scope lists the same
           application and does name its course. */
        $this->assertStringContainsString(
            format_string($this->course->fullname),
            $this->rendered(0)
        );
    }

    /**
     * Every row offers a way into the one application it is about.
     *
     * The queue had no such door. Its only routes to a single application were the
     * participants-page icon, the notification e-mail and the previous/next chain, so an operator
     * reading a row could not open it.
     *
     * @return void
     */
    public function test_every_row_offers_a_review_link(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->applicant();
        $ueid = (int) $DB->get_field('user_enrolments', 'id', [
            'userid' => $applicant->id,
            'enrolid' => $this->instance->id,
        ], MUST_EXIST);

        $html = $this->rendered((int) $this->instance->id);

        $this->assertStringContainsString('userenrol=' . $ueid, $html);
        // Named for the applicant, because "Review" on every row tells a screen reader nothing.
        $this->assertStringContainsString(
            s(get_string('queuereviewapplicant', 'enrol_apply', fullname($applicant))),
            $html
        );
    }

    /**
     * A deferred application says so on its own row.
     *
     * It used to be a three-pixel rule down the left edge, which the help text described and
     * nothing else did. A badge is readable, is announced, and survives the row becoming a card.
     *
     * @return void
     */
    public function test_a_deferred_application_is_badged(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->applicant();
        $DB->set_field('user_enrolments', 'status', ENROL_APPLY_USER_WAIT, [
            'userid' => $applicant->id,
            'enrolid' => $this->instance->id,
        ]);

        $html = $this->rendered((int) $this->instance->id);

        $this->assertStringContainsString(get_string('queuewaitinglist', 'enrol_apply'), $html);
        /* The control: an ordinary pending application does NOT carry it, so the badge is a
           statement about this row rather than markup every row gets. */
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, [
            'userid' => $applicant->id,
            'enrolid' => $this->instance->id,
        ]);
        $this->assertStringNotContainsString(
            get_string('queuewaitinglist', 'enrol_apply'),
            $this->rendered((int) $this->instance->id)
        );
    }

    /**
     * An applicant with a record of applying here before is marked as such.
     *
     * "They were cancelled here in June" is what turns a thirty-second decision into a three
     * minute one, and the queue is where that choice is made. The durable record already holds
     * it: its natural key is (courseid, userid) and is deliberately not unique.
     *
     * **The applicant already has a record of THIS application**, which is what makes the control
     * below load bearing: without it the row's own submission would be evidence of itself and
     * every row would be badged. That is what gate CK deletes, and against a fixture whose row had
     * no record of its own the gate reddened nothing at all.
     *
     * @return void
     */
    public function test_an_applicant_who_applied_before_is_marked(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->applicant();

        /* The control comes FIRST, and it is the half that matters: without it a badge rendered
           on every row would satisfy the assertion below just as well. */
        $this->assertStringNotContainsString(
            get_string('queueappliedbefore', 'enrol_apply'),
            $this->rendered((int) $this->instance->id)
        );

        // An earlier, already decided application to the same course.
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $applicant->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => 0,
            'comment' => 'Cancelled in June by mistake',
            'userinfodata' => '',
            'status' => submission::STATUS_CANCELLED,
            'outcomemessage' => '',
            'timecreated' => time() - DAYSECS,
            'timedecided' => time() - DAYSECS,
            'decidedby' => 0,
        ]);

        $this->assertStringContainsString(
            get_string('queueappliedbefore', 'enrol_apply'),
            $this->rendered((int) $this->instance->id)
        );
    }

    /**
     * The refresh path renders the queue, and until now nothing exercised it at all.
     *
     * Every other test here builds the table directly. That is the PAGE's route, and it hides
     * whatever the page did first - manage.php requires the plugin's lib.php, so a class reaching
     * for a constant defined there works on every page load and dies on the first AJAX refresh.
     * Measured: it did, and only a Behat scenario provoking a sort found it.
     *
     * This goes through core_table\external\dynamic\get::execute() instead, which is the request
     * a refresh makes. It holds the handler name core resolves, the filterset round trip, the
     * capability check and the rendering of every cell.
     *
     * **Know what it still cannot see, because two real defects hid from it.** Both were about
     * state a REQUEST has and a test process does not. It cannot see a missing require of the
     * plugin's lib.php, because PHPUnit runs one process and some other test has already required
     * that file. And it cannot see work done before validate_context() establishes a page
     * context, because $PAGE already carries one from whatever ran before - which is how column
     * definition came to render a renderable with no context on the refresh path and pass here.
     * A CLI reproduction of this same call misses both, for the same reason. The @javascript
     * scenario is what holds them: a real browser, a real request, a page that starts empty.
     *
     * @return void
     */
    public function test_the_refresh_path_renders_the_same_queue(): void {
        $this->setAdminUser();
        $applicant = $this->applicant();

        $result = \core_table\external\dynamic\get::execute(
            'enrol_apply',
            'applications',
            applications::UNIQUEID,
            [],
            [['name' => 'enrolid', 'jointype' => 1, 'values' => [(int) $this->instance->id]]],
            (string) \core_table\local\filter\filter::JOINTYPE_ALL,
            '',
            '',
            1,
            20,
            [],
            false
        );

        $this->assertStringContainsString(fullname($applicant), $result['html']);
        // The scope really was applied on this route too, not just on the page's.
        $this->assertStringContainsString('userenrol=', $result['html']);
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
