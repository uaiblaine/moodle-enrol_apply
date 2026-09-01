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
 * Tests for how many places an apply instance has and how many are held.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * Tests for how many places an apply instance has and how many are held.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(capacity::class)]
final class capacity_test extends \advanced_testcase {
    /** @var \stdClass Course the instance belongs to. */
    protected $course;

    /** @var \stdClass The enrol_apply instance. */
    protected $instance;

    /** @var \enrol_apply_plugin The plugin under test. */
    protected $plugin;

    /**
     * A course carrying one enabled apply instance.
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

        $this->plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $instanceid = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
    }

    /**
     * Seat one new user on the instance.
     *
     * @param int $status One of ENROL_USER_ACTIVE, ENROL_USER_SUSPENDED, ENROL_APPLY_USER_WAIT.
     * @param int $timeend Enrolment end, 0 for none.
     * @return \stdClass The seated user.
     */
    protected function seat(int $status = ENROL_USER_SUSPENDED, int $timeend = 0): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $user->id, null, 0, $timeend, $status);

        return $user;
    }

    /**
     * Set the instance's places limit and return the reloaded record.
     *
     * @param int $limit Value for customint3.
     * @return \stdClass The instance as the plugin will now read it.
     */
    protected function with_limit(int $limit): \stdClass {
        global $DB;

        $DB->set_field('enrol', 'customint3', $limit, ['id' => $this->instance->id]);

        return $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
    }

    /**
     * Set the instance's places number and return the reloaded record.
     *
     * @param int $places Value for customint4.
     * @return \stdClass The instance as the plugin will now read it.
     */
    protected function with_places(int $places): \stdClass {
        global $DB;

        $DB->set_field('enrol', 'customint4', $places, ['id' => $this->instance->id]);

        return $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
    }

    /**
     * An enrolment whose period has run out stops holding its place.
     *
     * This is the whole reason the class exists. The plugin ships
     * expiredaction = ENROL_EXT_REMOVED_KEEP, whose arm of process_expirations() changes
     * nothing, so the row survives forever - and while it was counted, a course whose places
     * filled and then expired had applications closed permanently, with an empty approval
     * queue and nothing anywhere able to explain it.
     *
     * The control is in the same run and is not optional: "seat an expired occupant, assert
     * not full" passes just as happily against a build with no cap at all.
     *
     * @return void
     */
    public function test_an_expired_enrolment_frees_its_place(): void {
        $instance = $this->with_limit(1);

        $this->seat(ENROL_USER_ACTIVE, time() - DAYSECS);
        $this->assertFalse(capacity::applications_closed($instance));
        $this->assertSame(0, capacity::applicants($instance));

        /* The control: a second occupant whose period has NOT run out fills the same single
           place. If this passed too, the cap would not be running at all. */
        $this->seat(ENROL_USER_ACTIVE, 0);
        $this->assertTrue(capacity::applications_closed($instance));
        $this->assertSame(1, capacity::applicants($instance));
    }

    /**
     * An enrolment that has not started yet still holds its place.
     *
     * The line is "will this row ever grant access again?", not "does it grant access now".
     * Core's access predicate pairs the timeend test with `timestart < :now`, and copying
     * that half would free the place of somebody who is going to turn up.
     *
     * @return void
     */
    public function test_an_enrolment_that_has_not_started_still_holds_its_place(): void {
        $instance = $this->with_limit(1);

        $this->plugin->enrol_user(
            $this->instance,
            $this->getDataGenerator()->create_user()->id,
            null,
            time() + DAYSECS,
            time() + (2 * DAYSECS),
            ENROL_USER_ACTIVE
        );

        $this->assertTrue(capacity::applications_closed($instance));
    }

    /**
     * Every state of an application holds a place, approved ones included.
     *
     * The sharpest test in this file. queue::awaiting_decision_where() excludes ACTIVE
     * enrolments, because it answers "what is still waiting for a decision" - and borrowing it
     * here, which is the tempting reuse, would stop every approved learner consuming a place
     * and make the cap exceedable by the number of approvals. The pending and waiting-list
     * rows below would pass either way; the ACTIVE one is what tells the two predicates apart.
     *
     * @return void
     */
    public function test_every_state_of_an_application_holds_a_place(): void {
        $instance = $this->with_limit(3);

        $this->seat(ENROL_USER_SUSPENDED);
        $this->seat(ENROL_APPLY_USER_WAIT);
        $this->assertFalse(capacity::applications_closed($instance));

        $this->seat(ENROL_USER_ACTIVE);
        $this->assertSame(3, capacity::applicants($instance));
        $this->assertTrue(capacity::applications_closed($instance));
    }

    /**
     * The limit is reached at the limit, not one past it.
     *
     * @return void
     */
    public function test_the_cap_is_reached_at_exactly_the_limit(): void {
        $instance = $this->with_limit(2);

        $this->seat();
        $this->assertFalse(capacity::applications_closed($instance));

        $this->seat();
        $this->assertTrue(capacity::applications_closed($instance));
    }

    /**
     * Zero and negative both mean "no limit".
     *
     * Negative is reachable rather than theoretical: db/upgrade.php writes customint3 = null
     * on one path, and reading a negative as a limit would turn an uncapped instance into a
     * permanently full one.
     *
     * @return void
     */
    public function test_a_cap_at_or_below_zero_is_no_cap(): void {
        $this->seat();
        $this->seat();

        foreach ([0, -1] as $limit) {
            $instance = $this->with_limit($limit);
            $this->assertSame(0, capacity::applicant_limit($instance));
            $this->assertFalse(capacity::applications_closed($instance), "customint3 = $limit should be uncapped");
        }
    }

    /**
     * An uncapped instance is answered without going to the database.
     *
     * Not a micro-optimisation: all three call sites already guarded their count behind
     * `$cap > 0`, and an uncapped instance is the overwhelmingly common case, so losing the
     * short circuit would add a query to every enrolment page render on every site.
     *
     * @return void
     */
    public function test_an_uncapped_instance_costs_no_query(): void {
        global $DB;

        $instance = $this->with_limit(0);
        $this->seat();

        $before = $DB->perf_get_reads();
        $this->assertFalse(capacity::applications_closed($instance));
        $this->assertSame($before, $DB->perf_get_reads());
    }

    /**
     * The cap and the queue agree about what "expired" means.
     *
     * They are deliberately different predicates - the queue also excludes active enrolments,
     * which the cap must count - but they share the expiry half, and a drift there would put
     * a row in the queue while its place stayed occupied, or the reverse.
     *
     * @return void
     */
    public function test_the_cap_and_the_queue_agree_about_expiry(): void {
        global $DB;

        $instance = $this->with_limit(1);
        $user = $this->seat(ENROL_USER_SUSPENDED, time() - DAYSECS);
        $row = $DB->get_record(
            'user_enrolments',
            ['userid' => $user->id, 'enrolid' => $this->instance->id],
            '*',
            MUST_EXIST
        );

        // Expired: no place held, and not awaiting a decision either.
        $this->assertSame(0, capacity::applicants($instance));
        $this->assertFalse(queue::is_awaiting_decision($row));

        // The control: unexpired, and both agree the other way.
        $DB->set_field('user_enrolments', 'timeend', 0, ['id' => $row->id]);
        $row = $DB->get_record('user_enrolments', ['id' => $row->id], '*', MUST_EXIST);

        $this->assertSame(1, capacity::applicants($instance));
        $this->assertTrue(queue::is_awaiting_decision($row));
    }

    /**
     * A place is held by an APPROVED enrolment and by nothing else.
     *
     * The sharpest test for the second number, and the one that keeps the two apart. An
     * application that is pending, and one that has been deferred to the waiting list, are both
     * in the pipeline and hold no place - which is precisely the gap the places number exists
     * to express: accept more applications than there are places, because approval is
     * discretionary.
     *
     * The assertion that the two counts DIFFER on this fixture is the load-bearing one. Without
     * it, a places_taken() that had quietly become a second spelling of applicants() - by
     * losing its status clause, or by being refactored to share a predicate - would pass every
     * other assertion here.
     *
     * @return void
     */
    public function test_a_place_is_held_by_an_approved_enrolment_and_nothing_else(): void {
        $instance = $this->with_places(1);

        $this->seat(ENROL_USER_SUSPENDED);
        $this->seat(ENROL_APPLY_USER_WAIT);

        $this->assertSame(0, capacity::places_taken($instance));
        $this->assertFalse(capacity::places_full($instance));

        $this->seat(ENROL_USER_ACTIVE);
        $this->assertSame(1, capacity::places_taken($instance));
        $this->assertTrue(capacity::places_full($instance));

        /* Three applications, one place taken. The two numbers must disagree here, or the
           second one is not measuring what it claims to. */
        $this->assertSame(3, capacity::applicants($instance));
        $this->assertNotSame(capacity::applicants($instance), capacity::places_taken($instance));
    }

    /**
     * An approval whose period has run out releases its place.
     *
     * The same ratchet as the applicant count, and it bites harder: under the shipped
     * expiredaction of KEEP an expired enrolment stays ACTIVE for ever, so without the clause a
     * course whose places filled once could never approve anybody again.
     *
     * The control is in the same run: a second, unexpired approval fills the same single place.
     *
     * @return void
     */
    public function test_an_expired_approval_releases_its_place(): void {
        $instance = $this->with_places(1);

        $this->seat(ENROL_USER_ACTIVE, time() - DAYSECS);
        $this->assertSame(0, capacity::places_taken($instance));
        $this->assertFalse(capacity::places_full($instance));

        $this->seat(ENROL_USER_ACTIVE, 0);
        $this->assertSame(1, capacity::places_taken($instance));
        $this->assertTrue(capacity::places_full($instance));
    }

    /**
     * An approval that has not started yet still holds its place.
     *
     * @return void
     */
    public function test_an_approval_that_has_not_started_still_holds_its_place(): void {
        $instance = $this->with_places(1);

        $this->plugin->enrol_user(
            $this->instance,
            $this->getDataGenerator()->create_user()->id,
            null,
            time() + DAYSECS,
            time() + (2 * DAYSECS),
            ENROL_USER_ACTIVE
        );

        $this->assertTrue(capacity::places_full($instance));
    }

    /**
     * The places number is reached at the number, not one past it.
     *
     * @return void
     */
    public function test_places_are_reached_at_exactly_the_limit(): void {
        $instance = $this->with_places(2);

        $this->seat(ENROL_USER_ACTIVE);
        $this->assertFalse(capacity::places_full($instance));

        $this->seat(ENROL_USER_ACTIVE);
        $this->assertTrue(capacity::places_full($instance));
    }

    /**
     * Zero and negative both mean "no places limit".
     *
     * @return void
     */
    public function test_places_at_or_below_zero_are_no_limit(): void {
        $this->seat(ENROL_USER_ACTIVE);
        $this->seat(ENROL_USER_ACTIVE);

        foreach ([0, -1] as $places) {
            $instance = $this->with_places($places);
            $this->assertSame(0, capacity::places($instance));
            $this->assertFalse(capacity::places_full($instance), "customint4 = $places should be unlimited");
        }
    }

    /**
     * Places may legitimately be over-subscribed, and the class must report it rather than clamp.
     *
     * Nothing refuses an approval on the strength of this number - the manager is warned and
     * decides - so taken above limit is an ordinary state, not a corrupt one. A restore, or an
     * administrator lowering the number, produces it directly.
     *
     * @return void
     */
    public function test_places_can_be_over_subscribed(): void {
        $instance = $this->with_places(1);

        $this->seat(ENROL_USER_ACTIVE);
        $this->seat(ENROL_USER_ACTIVE);
        $this->seat(ENROL_USER_ACTIVE);

        $this->assertSame(3, capacity::places_taken($instance));
        $this->assertTrue(capacity::places_full($instance));
    }

    /**
     * An instance with no places limit is answered without a query.
     *
     * @return void
     */
    public function test_an_unlimited_places_number_costs_no_query(): void {
        global $DB;

        $instance = $this->with_places(0);
        $this->seat(ENROL_USER_ACTIVE);

        $before = $DB->perf_get_reads();
        $this->assertFalse(capacity::places_full($instance));
        $this->assertSame($before, $DB->perf_get_reads());
    }

    /**
     * Deferred applications are counted, and nothing else is.
     *
     * The third number, and the sharpest assertion is the one that keeps it a strict SUBSET of
     * applicants(): three applications in three different states, all counting against the
     * applicant cap, and exactly one of them deferred. A predicate that had lost its status
     * clause - which is how this becomes a second spelling of applicants() - would report three.
     *
     * @return void
     */
    public function test_only_a_deferred_application_is_counted_as_deferred(): void {
        $this->seat(ENROL_USER_SUSPENDED);
        $this->seat(ENROL_USER_ACTIVE);
        $this->seat(ENROL_APPLY_USER_WAIT);

        $this->assertSame(1, capacity::deferred($this->instance));
        // All three are still in the pipeline, which is the number the cap is measured against.
        $this->assertSame(3, capacity::applicants($this->instance));
    }

    /**
     * An expired deferred row is excluded, exactly as the other two counts exclude theirs.
     *
     * Not consistency for its own sake: this number is reported to a manager as part of the
     * applicant total, so a predicate that counted a row applicants() does not would produce
     * "4 held, 5 of them deferred" on a live screen.
     *
     * @return void
     */
    public function test_an_expired_deferred_application_is_not_counted(): void {
        $this->seat(ENROL_APPLY_USER_WAIT, time() - DAYSECS);

        $this->assertSame(0, capacity::deferred($this->instance));
        // The control: unexpired, and it counts.
        $this->seat(ENROL_APPLY_USER_WAIT);
        $this->assertSame(1, capacity::deferred($this->instance));
    }

    /**
     * Nothing deferred is zero rather than an error.
     *
     * The method reaches ENROL_APPLY_USER_WAIT, which lives in a file the class under test is
     * not loaded with, so without its own require the method is a fatal on first call rather
     * than a wrong answer. **No test in this file can provoke that fatal**, and saying so is
     * worth more than implying otherwise: this file requires lib.php at file scope, as every
     * test file naming the constant must. What holds the require is the production caller -
     * a report render or a queue page where \enrol_apply\local\capacity is autoloaded alone.
     *
     * @return void
     */
    public function test_an_instance_with_nothing_deferred_counts_none(): void {
        $this->seat(ENROL_USER_SUSPENDED);

        $this->assertSame(0, capacity::deferred($this->instance));
    }

    /**
     * The count is scoped to its own instance.
     *
     * A course carrying two apply methods is supported on purpose, and the applicant cap is per
     * method - so a count that spanned them would report one method's backlog against another's
     * limit.
     *
     * @return void
     */
    public function test_the_deferred_count_is_scoped_to_one_instance(): void {
        global $DB;

        $otherid = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $other = $DB->get_record('enrol', ['id' => $otherid], '*', MUST_EXIST);

        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($other, $user->id, null, 0, 0, ENROL_APPLY_USER_WAIT);

        $this->assertSame(1, capacity::deferred($other));
        $this->assertSame(0, capacity::deferred($this->instance));
    }
}
