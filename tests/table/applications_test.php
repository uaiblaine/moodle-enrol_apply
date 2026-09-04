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
     * @param string $snapshot Stored envelope of what they submitted, empty for none.
     * @param array $userfields Overrides for the generated user, so a test can name them.
     * @param int $enrolstatus Enrolment status to create them with; the queue's two are
     *        ENROL_USER_SUSPENDED (pending) and ENROL_APPLY_USER_WAIT (deferred).
     * @return \stdClass The applicant.
     */
    protected function applicant(
        ?\stdClass $instance = null,
        string $snapshot = '',
        array $userfields = [],
        int $enrolstatus = ENROL_USER_SUSPENDED
    ): \stdClass {
        global $DB;

        $instance = $instance ?? $this->instance;
        $user = $this->getDataGenerator()->create_user($userfields);
        $this->plugin->enrol_user($instance, $user->id, null, 0, 0, $enrolstatus);

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
            'userinfodata' => $snapshot,
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

    /**
     * A stored envelope holding one name field and one identity field.
     *
     * Two fields on purpose, and the second is what makes every masking assertion in this file
     * non-vacuous: a reader without the identity capability keeps the name and loses the city,
     * so an empty cell fails the test instead of passing it.
     *
     * @param string $city Value for the identity half, so a test can vary it.
     * @return string The JSON envelope, as the submission would have written it.
     */
    protected function snapshot(string $city = 'Ouropretoville'): string {
        return (string) json_encode([
            'version' => submission::SNAPSHOT_VERSION,
            'fields' => $this->snapshot_fields($city),
        ]);
    }

    /**
     * The fields that envelope holds, so a test can count pills without hardcoding how many.
     *
     * @param string $city Value for the identity half.
     * @return array One entry per field, in the shape read_snapshot() returns.
     */
    protected function snapshot_fields(string $city = 'Ouropretoville'): array {
        return [
            ['key' => 's_firstname', 'label' => 'Given name', 'value' => 'Zephyrina'],
            ['key' => 's_city', 'label' => 'Hometown', 'value' => $city],
        ];
    }

    /**
     * A decider on this course's applications, with or without the identity capability.
     *
     * The capability is PROHIBITed rather than left unset for the negative case, because this
     * role is assigned beside whatever else the site gives an authenticated user and a default
     * elsewhere would decide the test rather than the code.
     *
     * @param bool $identity Whether they may see identity data in the course.
     * @return \stdClass The user.
     */
    protected function decider(bool $identity): \stdClass {
        $context = \context_course::instance($this->course->id);
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();

        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, $context->id, true);
        assign_capability(
            'moodle/site:viewuseridentity',
            $identity ? CAP_ALLOW : CAP_PROHIBIT,
            $roleid,
            $context->id,
            true
        );
        role_assign($roleid, $user->id, $context->id);

        return $user;
    }

    /**
     * The queue shows the answers the decision is actually made on.
     *
     * The complaint the whole rebuild answers: the page this replaces showed the applicant, the
     * date and a comment, and the submitted profile answers - the evidence - nowhere at all.
     *
     * Label and value are both asserted. A cell printing values with no labels is a list of
     * strings nobody can read, and one printing labels with no values is a presence oracle.
     *
     * @return void
     */
    public function test_the_queue_shows_what_the_applicant_submitted(): void {
        $this->setAdminUser();
        $this->applicant(null, $this->snapshot());

        $rendered = $this->rendered((int) $this->instance->id);

        /* The CELL's own heading, not the string on its own: the column header carries the same
           wording and would satisfy a bare assertion whatever col_snapshot() did. Found by an
           adversarial pass, and it is the same shape as this repository's regex trap - matching
           the wanted text anywhere downstream rather than inside the thing under test. */
        $this->assertStringContainsString(
            'enrol_apply-cardlabel">' . get_string('queuesubmitted', 'enrol_apply'),
            $rendered
        );
        $this->assertStringContainsString('Hometown', $rendered);
        $this->assertStringContainsString('Ouropretoville', $rendered);
    }

    /**
     * A reader who may not see identity data does not see it here either.
     *
     * The mask is the report's own, so the three surfaces onto this record cannot answer
     * differently about the same reader. The name field is the control: it survives, so an empty
     * cell - or a column that stopped rendering at all - cannot pass this test.
     *
     * The label is asserted absent alongside the value, because a withheld field that still
     * prints its label tells the reader which applicants filled that field in.
     *
     * @return void
     */
    public function test_the_evidence_is_masked_for_a_reader_without_the_identity_capability(): void {
        $this->applicant(null, $this->snapshot());

        // The control, and it is a reader rather than admin so that only the capability differs.
        $this->setUser($this->decider(true));
        $withidentity = $this->rendered((int) $this->instance->id);
        $this->assertStringContainsString('Zephyrina', $withidentity);
        $this->assertStringContainsString('Ouropretoville', $withidentity);

        $this->setUser($this->decider(false));
        $without = $this->rendered((int) $this->instance->id);

        $this->assertStringContainsString('Zephyrina', $without);
        $this->assertStringNotContainsString('Ouropretoville', $without);
        $this->assertStringNotContainsString('Hometown', $without);
    }

    /**
     * Both halves of every pair are escaped on the way into the cell.
     *
     * flexible_table::format_row() writes a cell's value into the markup with no escaping of its
     * own, and this cell carries two user-controlled strings: what the applicant typed, and what
     * an administrator named a custom field. The fixture is a bare ampersand and a "<" followed
     * by a letter, because tag-shaped text proves nothing here - format_string() would strip it
     * and s() would escape it, and the two are indistinguishable in the output.
     *
     * @return void
     */
    public function test_the_evidence_is_escaped(): void {
        $this->setAdminUser();
        $this->applicant(null, (string) json_encode([
            'version' => submission::SNAPSHOT_VERSION,
            'fields' => [
                ['key' => 's_city', 'label' => 'Town & city', 'value' => 'Ouro <Preto'],
            ],
        ]));

        $rendered = $this->rendered((int) $this->instance->id);

        $this->assertStringContainsString('Town &amp; city', $rendered);
        $this->assertStringContainsString('Ouro &lt;Preto', $rendered);
        $this->assertStringNotContainsString('Ouro <Preto', $rendered);
    }

    /**
     * An application with nothing stored renders no heading over the empty space.
     *
     * The row that DOES carry an envelope is the control: it proves the column is there and the
     * cell is reached, so this is an assertion about one row rather than about a column that
     * never rendered.
     *
     * @return void
     */
    public function test_an_application_with_no_stored_answers_shows_no_pill(): void {
        $this->setAdminUser();
        $this->applicant();
        $this->applicant(null, $this->snapshot());

        $rendered = $this->rendered((int) $this->instance->id);

        /* One row carries an envelope and one does not, so exactly one cell may hold pills - and
           that cell holds one per field, which snapshot() writes two of. Counted rather than
           merely found, because "no pill on the empty row" is invisible to a containment
           assertion once the other row has drawn some. */
        $this->assertSame(count($this->snapshot_fields()), substr_count($rendered, 'enrol_apply-fieldpill'));
        $this->assertSame(1, substr_count($rendered, 'enrol_apply-cardlabel">' . get_string('queuesubmitted', 'enrol_apply')));
    }

    /**
     * A course-level prohibit withholds that course's evidence on the site-wide queue.
     *
     * **The test the first cut of this column did not have, and it is why the first cut was
     * wrong.** That version resolved the mask once from the scope's context, on a docblock claim
     * that a capability held at system level is held in every course below it. Core disagrees:
     * has_capability_in_accessdata() walks UPWARD from the context it is given
     * (lib/accesslib.php:792-800) and can never see a CAP_PROHIBIT recorded below it. So an
     * operator holding moodle/site:viewuseridentity site-wide, with it prohibited in one course -
     * which is exactly what the Permissions page is for - passed the system check and was shown
     * every pill of that course's applicants.
     *
     * The second course is the control, and it carries the whole weight of the test: the same
     * reader, the same site-wide render, the same field. Without it a mask that withheld
     * everything would pass, and so would a column that had stopped rendering.
     *
     * @return void
     */
    public function test_a_course_level_prohibit_withholds_that_courses_evidence(): void {
        $open = $this->second_instance();

        $this->applicant(null, $this->snapshot('Prohibitedtown'));
        $this->applicant($open, $this->snapshot('Permittedtown'));

        $this->setUser($this->sitewide_reader_prohibited_in($this->course));
        $rendered = $this->rendered(0);

        $this->assertStringContainsString('Permittedtown', $rendered);
        $this->assertStringNotContainsString('Prohibitedtown', $rendered);
    }

    /**
     * A second course with its own apply instance, for the scopes that span courses.
     *
     * @return \stdClass The enrol instance.
     */
    protected function second_instance(): \stdClass {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());

        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * A site-wide decider who may see identity data everywhere except in one course.
     *
     * Both capabilities are granted at the system context, so this reader reaches the site-wide
     * scope; the prohibit is recorded at the one course, which is the override a check made at
     * the system context cannot see.
     *
     * @param \stdClass $course Course to withhold identity data in.
     * @return \stdClass The user.
     */
    protected function sitewide_reader_prohibited_in(\stdClass $course): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $system = \context_system::instance();

        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, $system->id, true);
        assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $roleid, $system->id, true);
        role_assign($roleid, $user->id, $system->id);

        assign_capability(
            'moodle/site:viewuseridentity',
            CAP_PROHIBIT,
            $roleid,
            \context_course::instance($course->id)->id,
            true
        );

        return $user;
    }

    /**
     * The mentee scope carries no evidence column, exactly as it carries no identity line.
     *
     * A mentor holds nothing in the course, so the mask that scope would apply is the names-only
     * one and the column would be empty on every row. The site-wide scope is the control: the
     * same application, the same reader capability level, and the column is there.
     *
     * @return void
     */
    public function test_the_mentee_scope_carries_no_evidence_column(): void {
        $mentee = $this->applicant(null, $this->snapshot());
        $this->setUser($this->mentor($mentee));

        $this->assertStringNotContainsString(
            get_string('queuesubmitted', 'enrol_apply'),
            $this->rendered(0)
        );

        /* The control. Admin reaches the same scope with the site-wide capability rather than
           through mentees, and there the column is drawn - so the assertion above is about the
           mentee scope and not about a heading that never renders anywhere. */
        $this->setAdminUser();
        $this->assertStringContainsString(
            get_string('queuesubmitted', 'enrol_apply'),
            $this->rendered(0)
        );
    }

    /**
     * The listing scoped and narrowed, as the page builds it.
     *
     * @param int $enrolid Enrol instance to list, 0 for the scope with no instance.
     * @param string $search Term to narrow by.
     * @param int|null $status Enrolment status to narrow to.
     * @return array User enrolment ids, in the order listed.
     */
    protected function narrowed(int $enrolid, string $search = '', ?int $status = null): array {
        $table = applications::for_scope($enrolid, $search, $status);

        ob_start();
        $table->out(50, false);
        ob_end_clean();

        return array_map(static fn($row) => (int) $row->userenrolmentid, array_values($table->rawdata));
    }

    /**
     * A search matches the applicant's name.
     *
     * The control is the second applicant, who is in the same queue and does not match: without
     * one, a search that returned everything would pass just as well as a search that worked.
     *
     * @return void
     */
    public function test_a_search_narrows_the_queue_to_a_matching_name(): void {
        $this->setAdminUser();
        $wanted = $this->applicant(null, '', ['firstname' => 'Zephyrina', 'lastname' => 'Quillsworth']);
        $this->applicant(null, '', ['firstname' => 'Bartholomew', 'lastname' => 'Underbough']);

        $listed = $this->narrowed((int) $this->instance->id, 'quillsworth');

        $this->assertCount(1, $listed);
        $this->assertSame(
            (int) $this->userenrolment($wanted),
            $listed[0]
        );
    }

    /**
     * A search filter carrying the empty string lists the whole queue.
     *
     * Not a hypothetical request. string_filter::add_filter_value() is a complete override gating
     * only on is_string(), so it never reaches the base class's rejection of '' - a client can
     * install a live search filter carrying nothing, and treating that as a narrowing filter
     * empties the queue the moment somebody clears the box.
     *
     * Driven through a filterset rather than through for_scope(), because for_scope() refuses an
     * empty term itself and would hide the case this pins.
     *
     * @return void
     */
    public function test_a_search_filter_carrying_nothing_narrows_nothing(): void {
        $this->setAdminUser();
        $this->applicant();
        $this->applicant();

        $filterset = new applications_filterset();
        $filterset->add_filter(new integer_filter('enrolid', null, [(int) $this->instance->id]));
        $filterset->add_filter(new \core_table\local\filter\string_filter('search', null, ['']));

        $table = new applications();
        $table->set_filterset($filterset);
        ob_start();
        $table->out(50, false);
        ob_end_clean();

        $this->assertCount(2, $table->rawdata);
        $this->assertFalse($table->is_narrowed());
    }

    /**
     * A percent sign is a character to match, not a wildcard.
     *
     * **The control has to be a row only the BROKEN version reaches**, and the first version of
     * this test had one that both versions rejected: against "100%Sure" and "Sure", searching
     * "100%S" returns exactly one row either way, because "Sure" contains no "100" and so misses
     * the wildcard reading too. Gate CY reddened nothing, which is how that was found.
     *
     * "100 Super" is the row that separates them. Escaped, the term is the literal "100%S" and
     * only "100%Sure" holds it. Unescaped it becomes LIKE '%100%S%', where the middle percent is
     * a wildcard - so "100 Super" matches as well, and an operator hunting one application is
     * handed a queue with no indication why.
     *
     * @return void
     */
    public function test_a_percent_sign_in_a_search_is_matched_literally(): void {
        $this->setAdminUser();
        $wanted = $this->applicant(null, '', ['firstname' => 'Ninety', 'lastname' => '100%Sure']);
        $decoy = $this->applicant(null, '', ['firstname' => 'Ninety', 'lastname' => '100 Super']);

        $listed = $this->narrowed((int) $this->instance->id, '100%S');

        $this->assertSame([(int) $this->userenrolment($wanted)], $listed);
        $this->assertNotContains((int) $this->userenrolment($decoy), $listed);
    }

    /**
     * The search reaches only what this reader can already see.
     *
     * A mentor gets no identity fields at all - identity::fields(null) returns nothing on the
     * mentee scope - so their e-mail address must not be matchable either. It is in the SELECT
     * regardless, because core's for_userpic() pulls it in, which is exactly why the search
     * columns are derived from the identity mappings rather than from the projection.
     *
     * Searching is an oracle even when the value is never printed: submit a guess, read the count.
     *
     * The control is the same address searched on the instance scope as a reader who DOES hold the
     * identity capability - it matches there, so an empty result above is about the mask and not
     * about a search that never worked.
     *
     * @return void
     */
    public function test_a_reader_cannot_search_an_identity_field_they_may_not_see(): void {
        $mentee = $this->applicant(null, '', ['email' => 'unguessable.address@example.org']);

        $this->setUser($this->mentor($mentee));
        $this->assertSame([], $this->narrowed(0, 'unguessable.address'));

        // The control: the capability makes the same address matchable in the same fixture.
        $this->setUser($this->decider(true));
        $this->assertSame(
            [(int) $this->userenrolment($mentee)],
            $this->narrowed((int) $this->instance->id, 'unguessable.address')
        );
    }

    /**
     * The status filter narrows to one state, and the other one is the control.
     *
     * @return void
     */
    public function test_the_status_filter_separates_pending_from_deferred(): void {
        $this->setAdminUser();
        $pending = $this->applicant();
        $deferred = $this->applicant(null, '', [], ENROL_APPLY_USER_WAIT);

        $this->assertSame(
            [(int) $this->userenrolment($pending)],
            $this->narrowed((int) $this->instance->id, '', ENROL_USER_SUSPENDED)
        );
        $this->assertSame(
            [(int) $this->userenrolment($deferred)],
            $this->narrowed((int) $this->instance->id, '', ENROL_APPLY_USER_WAIT)
        );
    }

    /**
     * The status filter reads the ENROLMENT's status, not the durable record's.
     *
     * **The obvious trajectory cannot be tested, and choosing it made the first version of this
     * test vacuous.** An approved participant later suspended from the participants page carries
     * APPROVED on the record and SUSPENDED on the enrolment - and `submission::STATUS_APPROVED`
     * is 1 while `ENROL_USER_SUSPENDED` is also 1, so both columns hold the same number and NO
     * assertion on that row can tell the two apart. Gate CW is what found it: the mutation
     * swapping the columns left that test green. It is the same "equal by coincidence rather
     * than by contract" the table's own docblock warns about, arrived at from the fixture end.
     *
     * This trajectory separates them. An application is deferred, so the record says WAITING (2);
     * an administrator then suspends the enrolment by hand from core's "Edit enrolment" screen,
     * which touches no record of this plugin's, so the enrolment says SUSPENDED (1). The queue
     * lists what is awaiting a decision NOW, which is the enrolment's question, so this row must
     * answer to Pending. Read from the record it answers to Deferred instead - and an operator
     * working the pending queue never sees it again.
     *
     * @return void
     */
    public function test_a_hand_suspended_deferral_is_pending_by_its_enrolment(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->applicant();
        $ueid = (int) $this->userenrolment($applicant);

        // Deferred once, then the enrolment suspended by a route that writes no record.
        $DB->set_field('enrol_apply_submission', 'status', submission::STATUS_WAITING, ['userenrolmentid' => $ueid]);

        $this->assertSame([$ueid], $this->narrowed((int) $this->instance->id, '', ENROL_USER_SUSPENDED));
        // And it is NOT reachable through the option its stale record names.
        $this->assertSame([], $this->narrowed((int) $this->instance->id, '', ENROL_APPLY_USER_WAIT));
    }

    /**
     * Every status the queue offers to filter by is one it can actually list.
     *
     * The list is the select's options, the values manage.php will accept back, and the values the
     * predicate compares against - so a member that matches nothing is an option that empties the
     * queue for no stated reason. ENROL_USER_ACTIVE is the one that must never be in it:
     * queue::awaiting_decision_where() excludes it by construction, so it would be an option that
     * can only ever return zero rows.
     *
     * This is the vocabulary half of a defect Behat caught and no unit test could: the select's
     * "any status" option carries the empty string, the GET form submits `status=` regardless, and
     * PARAM_INT cleans that to 0 - which IS ENROL_USER_ACTIVE. Reading it with a `>= 0` sentinel
     * turned every search made through the form into a filter on a status no row can hold.
     *
     * @return void
     */
    public function test_every_filterable_status_can_actually_be_listed(): void {
        $this->setAdminUser();

        $statuses = applications::filterable_statuses();
        $this->assertNotEmpty($statuses);
        $this->assertNotContains(ENROL_USER_ACTIVE, $statuses);

        foreach ($statuses as $status) {
            $applicant = $this->applicant(null, '', [], $status);
            $this->assertSame(
                [(int) $this->userenrolment($applicant)],
                $this->narrowed((int) $this->instance->id, '', $status),
                'a filterable status listed nothing'
            );
            // Cleared before the next one, so each assertion is about its own status alone.
            $this->plugin->unenrol_user($this->instance, $applicant->id);
        }
    }

    /**
     * The scope total is what the filters are measured against, so no filter moves it.
     *
     * It is the "of 312" in the count line and the number the capacity header reports, and those
     * two being one method is the point: a filtered total in the header renders "4 awaiting
     * decision" beside a deferred count that is instance-wide.
     *
     * @return void
     */
    public function test_the_scope_total_ignores_the_filters(): void {
        $this->setAdminUser();
        $this->applicant(null, '', ['firstname' => 'Zephyrina', 'lastname' => 'Quillsworth']);
        $this->applicant();
        $this->applicant(null, '', [], ENROL_APPLY_USER_WAIT);

        $table = applications::for_scope((int) $this->instance->id, 'quillsworth');
        ob_start();
        $table->out(50, false);
        ob_end_clean();

        $this->assertCount(1, $table->rawdata);
        $this->assertSame(3, $table->scope_total());
    }

    /**
     * Paging and sorting carry the filters, which no row-level assertion can see.
     *
     * Every test above stays green if guess_base_url() drops them - the rows are right, and the
     * defect is that the SECOND page of them is not. So this asserts the emitted url.
     *
     * @return void
     */
    public function test_the_base_url_carries_the_filters(): void {
        $this->setAdminUser();

        $table = applications::for_scope((int) $this->instance->id, 'quillsworth', ENROL_APPLY_USER_WAIT);

        $this->assertSame('quillsworth', $table->baseurl->param('search'));
        $this->assertSame(ENROL_APPLY_USER_WAIT, (int) $table->baseurl->param('status'));
        $this->assertSame((int) $this->instance->id, (int) $table->baseurl->param('id'));
    }

    /**
     * The user enrolment id of an applicant on this fixture's instance.
     *
     * @param \stdClass $user The applicant.
     * @param \stdClass|null $instance Instance they applied to, null for this fixture's own.
     * @return int The user enrolment id.
     */
    protected function userenrolment(\stdClass $user, ?\stdClass $instance = null): int {
        global $DB;

        return (int) $DB->get_field('user_enrolments', 'id', [
            'userid' => $user->id,
            'enrolid' => (int) ($instance ?? $this->instance)->id,
        ], MUST_EXIST);
    }

    /**
     * A custom profile field.
     *
     * @param string $shortname Field shortname.
     * @param string $datatype text or menu.
     * @param string $param1 Option list for a menu.
     * @return \stdClass The field.
     */
    protected function profile_field(string $shortname, string $datatype = 'text', string $param1 = ''): \stdClass {
        return $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => $datatype,
            'shortname' => $shortname,
            'name' => ucfirst($shortname),
            'param1' => $param1,
        ]);
    }

    /**
     * A select filter finds a value whose case has drifted from the vocabulary naming it.
     *
     * **The one predicate in this slice that a bare `=` would have made non-portable.**
     * moodle_database::sql_equal() emits `=` unchanged, which on PostgreSQL is a case-sensitive
     * text comparison, while mysqli_native_moodle_database::sql_equal() has to force
     * COLLATE <family>_bin to reach the same behaviour - so the same filter over the same data
     * answered differently on the two database families this plugin is tested on.
     *
     * The drift is ordinary rather than contrived: profile_field_menu keeps its vocabulary in
     * param1 and its values in {user_info_data}, with no validation between them, so re-casing an
     * option leaves every row already stored spelled the old way.
     *
     * Note this test can only go red on PostgreSQL. On MariaDB the site's own collation makes the
     * broken version pass, which is the whole point of the finding.
     *
     * @return void
     */
    public function test_a_select_filter_matches_a_value_whose_case_has_drifted(): void {
        $this->setAdminUser();
        $field = $this->profile_field('rank', 'menu', "Alpha\nBeta");
        $wanted = $this->applicant();
        $decoy = $this->applicant();
        // Stored before the option was re-cased; the column keeps what it was given.
        $this->set_profile_value($wanted, $field, 'alpha');
        $this->set_profile_value($decoy, $field, 'Beta');

        set_config('showuseridentity', 'profile_field_rank');
        set_config('queuefilterfields', 'profile_field_rank', 'enrol_apply');

        $listed = $this->narrowed_by(['pf' . $field->id => 'Alpha']);

        $this->assertSame([(int) $this->userenrolment($wanted)], $listed);
        $this->assertNotContains((int) $this->userenrolment($decoy), $listed);
    }

    /**
     * A malformed applied date is no filter, and says so on every surface.
     *
     * The same answer the status filter gives a value outside its vocabulary. What makes it worth
     * a test of its own is that three things have to agree about it: the bounds are null, the
     * queue does not claim to be narrowed - which is what draws the "no application matches"
     * wording and the chips - and the rows are all still there.
     *
     * The control is the second half: a real date does read, so none of the assertions above can
     * pass by the reader being dead.
     *
     * @return void
     */
    public function test_a_malformed_applied_date_narrows_nothing(): void {
        $this->setAdminUser();
        $one = $this->applicant();

        $table = applications::for_scope((int) $this->instance->id, '', null, ['appliedfrom' => '2026-13-40']);

        $this->assertSame([null, null], $table->get_applied_dates());
        $this->assertFalse($table->is_narrowed());
        $this->assertSame([(int) $this->userenrolment($one)], $this->narrowed_by(['appliedfrom' => '2026-13-40']));

        $real = applications::for_scope((int) $this->instance->id, '', null, ['appliedfrom' => '2026-01-01']);
        $this->assertSame(['2026-01-01', null], $real->get_applied_dates());
        $this->assertTrue($real->is_narrowed());
    }

    /**
     * The page url carries only the filters the table actually applied.
     *
     * request_filters() is what manage.php builds the page url and the decision form's action
     * from, and url_params() is what the table builds its own base url from. Both docblocks call
     * themselves the one definition, and they are only one definition if request_filters() returns
     * the CLEANED set: an earlier version returned whatever survived optional_param(), so a select
     * value outside its vocabulary and a malformed date reached the url while the table ignored
     * them. Inert as it stood, and exactly the disagreement both docblocks say cannot happen.
     *
     * @return void
     */
    public function test_the_page_url_carries_only_what_the_table_applied(): void {
        $this->setAdminUser();
        $this->applicant();
        $field = $this->profile_field('rank', 'menu', "Alpha\nBeta");
        set_config('showuseridentity', 'profile_field_rank');
        set_config('queuefilterfields', 'profile_field_rank', 'enrol_apply');

        $listing = \enrol_apply\local\queue::listing_scope((int) $this->instance->id);
        $token = 'pf' . $field->id;

        $_GET['appliedfrom'] = '2026-13-40';
        $_GET[$token] = 'Gamma';
        $this->assertSame([], applications::request_filters($listing));

        // The control: what the table WOULD apply is carried, so this is not a dead reader.
        $_GET['appliedfrom'] = '2026-01-01';
        $_GET[$token] = 'Alpha';
        $this->assertSame(
            [$token => 'Alpha', 'appliedfrom' => '2026-01-01'],
            applications::request_filters($listing)
        );

        unset($_GET['appliedfrom'], $_GET[$token]);
    }

    /**
     * A field the administrator has not ticked is still a name the filterset recognises.
     *
     * **The declaration is observable before any authorisation runs.** Core registers
     * core_table_get_dynamic_table_content with no capability of its own, and get.php calls
     * add_filter_from_params() for every submitted name before it constructs the table, before
     * set_filterset(), and before validate_context() and has_capability(). So if the declared set
     * were the administrator's tick-list, any logged-in user with no capability here at all could
     * read that list back one name at a time from which exception came out - a setting otherwise
     * behind moodle/site:config. Declaring the whole vocabulary the site already publishes through
     * showuseridentity breaks the correlation, and narrows nothing: set_filterset() reads only the
     * offered set.
     *
     * The control is the third assertion: a name the site does not publish at all is still not a
     * filter, so this cannot pass by everything being declared.
     *
     * @return void
     */
    public function test_a_field_the_administrator_has_not_ticked_is_still_a_recognised_filter_name(): void {
        set_config('showuseridentity', 'institution,department');
        set_config('queuefilterfields', 'institution', 'enrol_apply');

        $declared = (new applications_filterset())->get_optional_filters();

        $this->assertArrayHasKey('institution', $declared);
        $this->assertArrayHasKey('department', $declared);
        $this->assertArrayNotHasKey('city', $declared);
    }

    /**
     * The site-wide queue narrows to one course, and to a category with its subtree.
     *
     * **The only filter on this queue a database can use an index for.** {course}.category and
     * {course}.id both carry one, so this cuts the row set before the search's LIKE has anything
     * to scan - which the search itself can never do, whatever it is given.
     *
     * The two applications in other places are the control: they prove the queue holds more than
     * the match, so a narrowing result cannot come from an empty fixture.
     *
     * @return void
     */
    public function test_the_site_wide_queue_narrows_by_course_and_by_category(): void {
        $this->setAdminUser();

        $parent = $this->getDataGenerator()->create_category();
        $child = $this->getDataGenerator()->create_category(['parent' => $parent->id]);
        $wantedcourse = $this->getDataGenerator()->create_course(['category' => $child->id]);
        $othercourse = $this->getDataGenerator()->create_course();

        $wantedinstance = $this->apply_instance($wantedcourse);
        $otherinstance = $this->apply_instance($othercourse);

        $wanted = $this->applicant($wantedinstance);
        $other = $this->applicant($otherinstance);

        $all = $this->sitewide([]);
        $this->assertContains((int) $this->userenrolment($wanted, $wantedinstance), $all);
        $this->assertContains((int) $this->userenrolment($other, $otherinstance), $all);

        $this->assertSame(
            [(int) $this->userenrolment($wanted, $wantedinstance)],
            $this->sitewide(['course' => (string) $wantedcourse->id])
        );

        // The PARENT category, so this passes only if the subtree is included.
        $this->assertSame(
            [(int) $this->userenrolment($wanted, $wantedinstance)],
            $this->sitewide(['category' => (string) $parent->id])
        );
    }

    /**
     * A queue scoped to one enrolment method ignores a course filter entirely.
     *
     * The control would filter a set of one, so it is not offered - and a value arriving anyway,
     * from a stale url or a forged request, must narrow nothing rather than narrow something. The
     * assertion is that the queue is unchanged AND does not call itself narrowed, because the
     * second is what draws the chips and the "nothing matches" wording.
     *
     * @return void
     */
    public function test_a_scoped_queue_ignores_a_course_filter(): void {
        $this->setAdminUser();
        $applicant = $this->applicant();

        $table = applications::for_scope(
            (int) $this->instance->id,
            '',
            null,
            ['course' => (string) $this->course->id]
        );

        $this->assertSame([null, null], $table->get_course_scope());
        $this->assertFalse($table->offers_course_filters());
        $this->assertFalse($table->is_narrowed());
    }

    /**
     * The base url carries the course and category filters.
     *
     * Paging and sorting emit real anchors from it, so a filter the base url drops is a filter the
     * operator loses on the first page turn - silently, and only for the operator who turned one.
     *
     * @return void
     */
    public function test_the_base_url_carries_the_course_filters(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->apply_instance($course);
        $this->applicant($instance);

        $table = applications::for_scope(0, '', null, ['course' => (string) $course->id]);

        $this->assertStringContainsString('course=' . $course->id, $table->baseurl->out(false));
    }

    /**
     * An apply enrolment method on a given course.
     *
     * @param \stdClass $course The course.
     * @return \stdClass The enrol instance.
     */
    protected function apply_instance(\stdClass $course): \stdClass {
        global $DB;

        $id = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());

        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The site-wide listing, narrowed by the given filters.
     *
     * @param array $filters Filter name => raw value.
     * @return array User enrolment ids.
     */
    protected function sitewide(array $filters): array {
        $table = applications::for_scope(0, '', null, $filters);

        ob_start();
        $table->out(50, false);
        ob_end_clean();

        return array_map(static fn($row) => (int) $row->userenrolmentid, array_values($table->rawdata));
    }

    /**
     * Give one user a value for a custom profile field.
     *
     * @param \stdClass $user The user.
     * @param \stdClass $field The field.
     * @param string $value The value.
     * @return void
     */
    protected function set_profile_value(\stdClass $user, \stdClass $field, string $value): void {
        global $DB;

        $DB->insert_record('user_info_data', (object) [
            'userid' => $user->id,
            'fieldid' => $field->id,
            'data' => $value,
            'dataformat' => 0,
        ]);
    }

    /**
     * The listing narrowed by the field and date filters.
     *
     * @param array $filters Token or date-bound name => raw value.
     * @return array User enrolment ids.
     */
    protected function narrowed_by(array $filters): array {
        $table = applications::for_scope((int) $this->instance->id, '', null, $filters);

        ob_start();
        $table->out(50, false);
        ob_end_clean();

        return array_map(static fn($row) => (int) $row->userenrolmentid, array_values($table->rawdata));
    }

    /**
     * A field the reader may not see is not offered, whatever the administrator ticked.
     *
     * **This is the disclosure boundary of the whole slice.** The administrator's setting says
     * which fields the queue MAY offer; what this reader may already see decides which it does.
     * Without the intersection, ticking a box would hand every operator a control over a field
     * their own site withholds from them - and a filter is an oracle even when the value is never
     * printed: apply it, read the count.
     *
     * The control is the second half: naming the field in the site's identity list makes it appear
     * for the same reader with the same setting, so an empty offered set cannot pass this test.
     *
     * @return void
     */
    public function test_a_field_the_reader_may_not_see_is_not_offered(): void {
        $this->setAdminUser();
        $this->applicant();
        set_config('showuseridentity', 'institution');
        set_config('queuefilterfields', 'city,institution', 'enrol_apply');

        $offered = applications::for_scope((int) $this->instance->id)->get_offered_filters();
        $this->assertSame(['institution'], array_values(array_column($offered, 'name')));

        // The control: the site now names it, so the same setting offers it.
        set_config('showuseridentity', 'city,institution');
        $widened = applications::for_scope((int) $this->instance->id)->get_offered_filters();
        $this->assertEqualsCanonicalizing(['city', 'institution'], array_values(array_column($widened, 'name')));
    }

    /**
     * A filter naming a field this reader is not offered narrows nothing.
     *
     * Not merely hidden from the controls: refused where the query is built. The filterset declares
     * the SITE's whole list so a forged name is refused by core before it arrives, but a name that
     * is real and simply not this reader's reaches set_filterset() - and must be ignored there.
     *
     * @return void
     */
    public function test_a_filter_for_an_unoffered_field_is_ignored(): void {
        global $DB;

        $this->setAdminUser();
        $wanted = $this->applicant();
        $other = $this->applicant();
        $DB->set_field('user', 'city', 'Ouropretoville', ['id' => $wanted->id]);
        $DB->set_field('user', 'city', 'Elsewhereville', ['id' => $other->id]);
        $DB->set_field('user', 'institution', 'Ouropretoville', ['id' => $wanted->id]);

        // The site does not name city, so a city filter is nobody's.
        set_config('showuseridentity', 'institution');
        set_config('queuefilterfields', 'city,institution', 'enrol_apply');

        $this->assertCount(2, $this->narrowed_by(['city' => 'Ouropretoville']), 'an unoffered filter must not narrow');

        // The control: the offered one narrows on the same fixture.
        $this->assertSame(
            [(int) $this->userenrolment($wanted)],
            $this->narrowed_by(['institution' => 'Ouropretoville'])
        );
    }

    /**
     * A custom profile field filters through the join core builds for it.
     *
     * Mandatory rather than thorough: identity::sql()'s named-parameter path exists only for CUSTOM
     * fields, so a fixture naming standard fields alone never reaches it and would pass against a
     * build that cannot filter a custom field at all.
     *
     * @return void
     */
    public function test_a_custom_profile_field_filters_through_its_join(): void {
        $this->setAdminUser();
        $field = $this->profile_field('unit');
        $wanted = $this->applicant();
        $other = $this->applicant();
        $this->set_profile_value($wanted, $field, 'Ouropretoville');
        $this->set_profile_value($other, $field, 'Elsewhereville');

        set_config('showuseridentity', 'profile_field_unit');
        set_config('queuefilterfields', 'profile_field_unit', 'enrol_apply');

        $this->assertSame(
            [(int) $this->userenrolment($wanted)],
            $this->narrowed_by(['pf' . $field->id => 'Ouropretoville'])
        );
    }

    /**
     * A percent sign in a field filter is a character, not a wildcard.
     *
     * The decoy is the row only the BROKEN version reaches: unescaped, the term becomes
     * LIKE '%100%S%' and "100 Super" matches through the middle wildcard.
     *
     * @return void
     */
    public function test_a_percent_sign_in_a_field_filter_is_matched_literally(): void {
        global $DB;

        $this->setAdminUser();
        $wanted = $this->applicant();
        $decoy = $this->applicant();
        $DB->set_field('user', 'institution', '100%Sure', ['id' => $wanted->id]);
        $DB->set_field('user', 'institution', '100 Super', ['id' => $decoy->id]);

        set_config('showuseridentity', 'institution');
        set_config('queuefilterfields', 'institution', 'enrol_apply');

        $listed = $this->narrowed_by(['institution' => '100%S']);
        $this->assertSame([(int) $this->userenrolment($wanted)], $listed);
        $this->assertNotContains((int) $this->userenrolment($decoy), $listed);
    }

    /**
     * An application made on the "to" date is inside the range.
     *
     * The upper bound is the midnight that STARTS the following day, compared with a strict
     * less-than, so every hour of the named day is included. The control is the application made
     * the next day, which must be outside - without it a bound that included everything would pass.
     *
     * @return void
     */
    public function test_an_application_made_on_the_to_date_is_included(): void {
        global $DB;

        $this->setAdminUser();
        $inside = $this->applicant();
        $outside = $this->applicant();

        // Late on the "to" day, and just after midnight on the day after it.
        $today = make_timestamp(2026, 5, 20, 23, 30, 0, 99);
        $tomorrow = make_timestamp(2026, 5, 21, 0, 30, 0, 99);
        $DB->set_field('user_enrolments', 'timecreated', $today, ['id' => $this->userenrolment($inside)]);
        $DB->set_field('user_enrolments', 'timecreated', $tomorrow, ['id' => $this->userenrolment($outside)]);

        $listed = $this->narrowed_by(['appliedfrom' => '2026-05-20', 'appliedto' => '2026-05-20']);

        $this->assertSame([(int) $this->userenrolment($inside)], $listed);
    }

    /**
     * Country is a chosen code, not typed text, and the difference is what the operator gets back.
     *
     * {user}.country holds a two-letter code while the reader reads a name, so the control has to
     * be a select over the country list: it offers "Brazil" and sends "BR". Made a text box
     * instead, an operator would type the only thing on screen - the name - and the queue would
     * answer with nothing at all, because no row holds the string "Brazil".
     *
     * **Matching the code is not what separates the two shapes**, and a test asserting only that
     * passes either way: a LIKE over a two-letter column returns the same row as an equality, no
     * country code being a substring of another. What separates them is that a select REFUSES a
     * value it never offered - so the name comes back as no filter rather than as an empty queue.
     * Found by gate DH reddening nothing.
     *
     * @return void
     */
    public function test_the_country_filter_takes_a_code_and_refuses_a_name(): void {
        global $DB;

        $this->setAdminUser();
        $wanted = $this->applicant();
        $other = $this->applicant();
        $DB->set_field('user', 'country', 'BR', ['id' => $wanted->id]);
        $DB->set_field('user', 'country', 'PT', ['id' => $other->id]);

        set_config('showuseridentity', 'country');
        set_config('queuefilterfields', 'country', 'enrol_apply');

        // The code the select sends narrows to the one applicant holding it.
        $this->assertSame([(int) $this->userenrolment($wanted)], $this->narrowed_by(['country' => 'BR']));

        /* The name is not a value this control offers, so it is refused and the queue is not
           narrowed at all. As a text box it would be accepted, match nothing, and answer with an
           empty queue for a country that does have an applicant. */
        $this->assertCount(2, $this->narrowed_by(['country' => 'Brazil']));
    }

    /**
     * Every filter the queue accepts rides the base url.
     *
     * No row-level assertion can see this: the rows on page one are right whatever the base url
     * says, and the defect is that page two, and every sort, is a different queue.
     *
     * @return void
     */
    public function test_the_base_url_carries_every_filter(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->applicant();
        $DB->set_field('user', 'institution', 'UFOP', ['id' => $applicant->id]);
        set_config('showuseridentity', 'institution');
        set_config('queuefilterfields', 'institution', 'enrol_apply');

        $table = applications::for_scope((int) $this->instance->id, 'zephyrina', ENROL_APPLY_USER_WAIT, [
            'institution' => 'UFOP',
            'appliedfrom' => '2026-01-01',
            'appliedto' => '2026-12-31',
        ]);

        $this->assertSame('zephyrina', $table->baseurl->param('search'));
        $this->assertSame('UFOP', $table->baseurl->param('institution'));
        $this->assertSame('2026-01-01', $table->baseurl->param('appliedfrom'));
        $this->assertSame('2026-12-31', $table->baseurl->param('appliedto'));
    }

    /**
     * The scope total is measured against the scope, whichever filter is applied.
     *
     * is_narrowed() is the silent-failure point: scope_total() short-circuits on it, so a filter
     * missing from it makes the count line read "N of N" - and print_nothing_to_display() then
     * falls through to core's generic message instead of the plugin's filtered-empty one. Neither
     * is visible to a row-level assertion.
     *
     * @return void
     */
    public function test_the_scope_total_ignores_every_filter(): void {
        global $DB;

        $this->setAdminUser();
        $wanted = $this->applicant();
        $this->applicant();
        $this->applicant();
        $DB->set_field('user', 'institution', 'Ouropretoville', ['id' => $wanted->id]);
        set_config('showuseridentity', 'institution');
        set_config('queuefilterfields', 'institution', 'enrol_apply');

        $table = applications::for_scope((int) $this->instance->id, '', null, ['institution' => 'Ouropretoville']);
        ob_start();
        $table->out(50, false);
        ob_end_clean();

        $this->assertCount(1, $table->rawdata);
        $this->assertTrue($table->is_narrowed());
        $this->assertSame(3, $table->scope_total());
    }

    /**
     * The mentee scope is offered no field filters at all.
     *
     * It resolves no identity context - one statement spans courses there - so core's mapping is
     * empty and the intersection is empty whatever the administrator ticked. The control is the
     * same setting on the instance scope, which does offer one.
     *
     * @return void
     */
    public function test_the_mentee_scope_offers_no_field_filters(): void {
        set_config('showuseridentity', 'institution');
        set_config('queuefilterfields', 'institution', 'enrol_apply');

        $mentee = $this->applicant();
        $this->setUser($this->mentor($mentee));
        $this->assertSame([], applications::for_scope(0)->get_offered_filters());

        // The control, on a scope that does resolve an identity context.
        $this->setAdminUser();
        $this->assertNotSame([], applications::for_scope((int) $this->instance->id)->get_offered_filters());
    }
}
