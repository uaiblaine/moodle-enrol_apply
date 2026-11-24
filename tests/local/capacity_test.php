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
     * Set the instance's applicant limit and return the reloaded record.
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
     * An enrolment whose period has run out stops counting against the applicant limit.
     *
     * The plugin ships expiredaction = ENROL_EXT_REMOVED_KEEP, under which
     * process_expirations() never removes the row, so counting it would close applications for
     * good while the approval queue stayed empty.
     *
     * @return void
     */
    public function test_an_expired_enrolment_frees_its_place(): void {
        $instance = $this->with_limit(1);

        $this->seat(ENROL_USER_ACTIVE, time() - DAYSECS);
        $this->assertFalse(capacity::applications_closed($instance));
        $this->assertSame(0, capacity::applicants($instance));

        /* The control: an unexpired occupant fills the same single slot. Without it the
           assertions above also pass against a build with no cap at all. */
        $this->seat(ENROL_USER_ACTIVE, 0);
        $this->assertTrue(capacity::applications_closed($instance));
        $this->assertSame(1, capacity::applicants($instance));
    }

    /**
     * An enrolment that has not started yet still counts against the applicant limit.
     *
     * The rule is "will this row ever grant access again?", so core's `timestart < :now` access
     * clause is deliberately not copied. See {@see capacity}.
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
     * Every state of an application counts against the applicant limit, approved ones included.
     *
     * queue::awaiting_decision_where() excludes ACTIVE enrolments; borrowing it here would stop
     * approved learners counting and let the cap be exceeded by the number of approvals. The
     * pending and waiting-list rows pass either way; only the ACTIVE one tells the two
     * predicates apart.
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
     * Negative is reachable: the instance form accepts any PARAM_INT and a restore carries any
     * value, and reading a negative as a limit would turn an uncapped instance into a
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
     * Uncapped is the common case and the enrolment page asks for every prospective applicant,
     * so losing the short circuit would add a query to that page on every site.
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
     * A pending application and a deferred one are in the pipeline but hold no place; that gap
     * is what lets a method accept more applications than it has places.
     *
     * Changes that must make it fail: places_taken() losing its status clause or sharing a
     * predicate with applicants(). The final assertion that the two counts differ is the one
     * that catches both.
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

        // Three applications, one place taken: the two counts must differ.
        $this->assertSame(3, capacity::applicants($instance));
        $this->assertNotSame(capacity::applicants($instance), capacity::places_taken($instance));
    }

    /**
     * An approval whose period has run out releases its place.
     *
     * Under the shipped expiredaction of KEEP an expired enrolment stays ACTIVE indefinitely, so
     * without the expiry clause a course whose places filled once would report itself full, and
     * warn after every decision, from then on.
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
     * Three applications in three states, all counting against the applicant limit, exactly one
     * of them deferred. Changes that must make it fail: deferred() losing its status clause,
     * which would report three.
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
     * The number is shown beside the applicant total, so counting a row applicants() excludes
     * could report more deferred applications than applications held.
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
     * This file requires lib.php at file scope, so no test here can detect deferred() losing
     * its own require_once of lib.php, which defines ENROL_APPLY_USER_WAIT.
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

    /**
     * A method of its own course for the batch tests, with the given numbers and window, seated with
     * one user per status given.
     *
     * @param int $limit Applicant limit (customint3), 0 for none.
     * @param int $places Places (customint4), 0 for none.
     * @param array $seats Each: [status, timeend].
     * @param array $fields Further {enrol} fields (enrolstartdate, enrolenddate, status, customint6).
     * @return \stdClass The {enrol} record as the plugin reads it.
     */
    protected function method_with(int $limit, int $places, array $seats, array $fields = []): \stdClass {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());
        $DB->update_record('enrol', (object) (['id' => $id, 'customint3' => $limit, 'customint4' => $places] + $fields));
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        foreach ($seats as [$status, $timeend]) {
            $user = $this->getDataGenerator()->create_user();
            $this->plugin->enrol_user($instance, $user->id, null, 0, $timeend, $status);
        }

        return $instance;
    }

    /**
     * The batch answers what state() answers, instance by instance, over every shape a method's
     * capacity can take: no limit, places only, a limit only, both, an expired row, a deferred row,
     * a method over its places, a closed window, a disabled method.
     *
     * The controls: the set holds both flags true and both false somewhere, an expired row that
     * would tip a count, and an over-full method, so a batch that dropped a clause, counted another
     * status or swapped the two numbers answers differently from state() on at least one of them.
     *
     * Changes that must make it fail: the batch's places count without its status clause or its
     * expiry clause, its applicants count without its expiry clause, either count read for a method
     * whose limit is not set, the flags read off another number.
     *
     * @return void
     */
    public function test_the_batch_is_state_instance_by_instance(): void {
        $past = time() - DAYSECS;
        $active = [ENROL_USER_ACTIVE, 0];
        $pending = [ENROL_USER_SUSPENDED, 0];
        $deferred = [ENROL_APPLY_USER_WAIT, 0];
        $expired = [ENROL_USER_ACTIVE, $past];

        $instances = [
            'nolimit' => $this->method_with(0, 0, [$active, $pending]),
            'placesonly' => $this->method_with(0, 2, [$active, $active, $pending, $expired]),
            'limitonly' => $this->method_with(3, 0, [$pending, $deferred, $active, $expired]),
            'both' => $this->method_with(5, 1, [$active, $pending, $deferred]),
            'expiredonly' => $this->method_with(1, 1, [$expired, [ENROL_USER_SUSPENDED, $past]]),
            'deferredfull' => $this->method_with(2, 0, [$deferred, $deferred]),
            'overfull' => $this->method_with(0, 1, [$active, $active, $active]),
            'closedwindow' => $this->method_with(0, 0, [$pending], ['enrolenddate' => $past]),
            'disabled' => $this->method_with(2, 2, [$active], ['status' => ENROL_INSTANCE_DISABLED]),
        ];

        $now = time();
        $states = capacity::states(array_values($instances), $now);
        $this->assertCount(count($instances), $states);
        foreach ($instances as $name => $instance) {
            $this->assertEquals(capacity::state($instance, $now), $states[(int) $instance->id], $name);
        }

        // The controls: what the comparison compared differs from method to method.
        $this->assertSame(2, $states[(int) $instances['placesonly']->id]->taken);
        $this->assertTrue($states[(int) $instances['placesonly']->id]->placesfull);
        $this->assertSame(3, $states[(int) $instances['limitonly']->id]->held);
        $this->assertTrue($states[(int) $instances['limitonly']->id]->limitreached);
        $this->assertSame(0, $states[(int) $instances['expiredonly']->id]->held);
        $this->assertFalse($states[(int) $instances['expiredonly']->id]->placesfull);
        $this->assertTrue($states[(int) $instances['deferredfull']->id]->limitreached);
        $this->assertSame(3, $states[(int) $instances['overfull']->id]->taken);
        $this->assertFalse($states[(int) $instances['both']->id]->limitreached);
        $this->assertTrue($states[(int) $instances['both']->id]->placesfull);
        $this->assertFalse($states[(int) $instances['nolimit']->id]->placesfull);
        $this->assertFalse($states[(int) $instances['closedwindow']->id]->open);
        $this->assertTrue($states[(int) $instances['nolimit']->id]->open);
    }

    /**
     * With the third argument the places taken are counted for a method without places too, with placesfull
     * still false there; without it the answer is state()'s own, instance by instance.
     *
     * The controls: a method with places answers the same either way, a pending row and an expired one are
     * no place taken, and the default call reads 0 for the method without places.
     *
     * Changes that must make it fail: the flag ignored; the flag making a method without places "full"; the
     * default changed.
     *
     * @return void
     */
    public function test_places_taken_can_be_counted_for_a_method_without_places(): void {
        $past = time() - DAYSECS;
        $active = [ENROL_USER_ACTIVE, 0];
        $set = [
            $this->method_with(0, 0, [$active, $active, $active, [ENROL_USER_SUSPENDED, 0], [ENROL_USER_ACTIVE, $past]]),
            $this->method_with(0, 2, [$active, [ENROL_USER_SUSPENDED, 0]]),
        ];
        $now = time();

        $counted = capacity::states($set, $now, true);
        $this->assertSame(3, $counted[(int) $set[0]->id]->taken);
        $this->assertFalse($counted[(int) $set[0]->id]->placesfull);
        $this->assertSame(1, $counted[(int) $set[1]->id]->taken);

        $default = capacity::states($set, $now);
        foreach ($set as $instance) {
            $this->assertEquals(capacity::state($instance, $now), $default[(int) $instance->id]);
        }
        $this->assertSame(0, $default[(int) $set[0]->id]->taken);
        $this->assertEquals($default[(int) $set[1]->id], $counted[(int) $set[1]->id]);
    }

    /**
     * The batch reads at most two statements whatever the number of methods within one chunk, and
     * none for an empty set or a set with no limit at all.
     *
     * Changes that must make it fail: a count per method; a query for an empty set.
     *
     * @return void
     */
    public function test_the_batch_does_not_grow_with_the_methods(): void {
        global $DB;

        $few = [$this->method_with(2, 1, [[ENROL_USER_ACTIVE, 0]])];
        $many = $few;
        foreach (range(1, 4) as $ignored) {
            $many[] = $this->method_with(3, 2, [[ENROL_USER_SUSPENDED, 0], [ENROL_USER_ACTIVE, 0]]);
        }
        $nolimit = [$this->method_with(0, 0, [[ENROL_USER_ACTIVE, 0]])];

        $before = $DB->perf_get_reads();
        $this->assertSame([], capacity::states([]));
        $this->assertSame(0, $DB->perf_get_reads() - $before);

        $before = $DB->perf_get_reads();
        capacity::states($nolimit);
        $this->assertSame(0, $DB->perf_get_reads() - $before);

        $before = $DB->perf_get_reads();
        capacity::states($few);
        $this->assertSame(2, $DB->perf_get_reads() - $before);

        $before = $DB->perf_get_reads();
        $states = capacity::states($many);
        $this->assertSame(2, $DB->perf_get_reads() - $before);
        // The control: five methods were answered, each from the two statements.
        $this->assertCount(5, $states);
        $this->assertSame(2, end($states)->held);
    }

    /**
     * A set larger than one chunk is read in chunks and answers what state() answers.
     *
     * The chunk size is brought down to two through the protected seam. The control: the same set
     * under the default size runs fewer statements, which proves the seam is the one in force.
     *
     * Changes that must make it fail: the chunks' results merged keeping only the last chunk, or
     * only the first chunk read.
     *
     * @return void
     */
    public function test_a_set_larger_than_a_chunk_is_read_in_chunks(): void {
        global $DB;

        $active = [ENROL_USER_ACTIVE, 0];
        $pending = [ENROL_USER_SUSPENDED, 0];
        $instances = [
            $this->method_with(3, 2, [$active, $active, $pending]),
            $this->method_with(0, 1, [$active]),
            $this->method_with(2, 0, [$pending, $pending]),
            $this->method_with(5, 3, [$active, $pending]),
            $this->method_with(1, 1, [$pending]),
        ];
        $small = new class extends capacity {
            /**
             * Two ids per statement.
             *
             * @return int The chunk size.
             */
            protected static function batch_size(): int {
                return 2;
            }
        };

        $now = time();
        $before = $DB->perf_get_reads();
        $states = $small::states($instances, $now);
        $chunked = $DB->perf_get_reads() - $before;

        $before = $DB->perf_get_reads();
        $whole = capacity::states($instances, $now);
        $default = $DB->perf_get_reads() - $before;

        $this->assertSame(2, $default);
        $this->assertGreaterThan($default, $chunked);
        $this->assertCount(5, $states);
        $this->assertEquals($whole, $states);
        foreach ($instances as $instance) {
            $this->assertEquals(capacity::state($instance, $now), $states[(int) $instance->id]);
        }
    }
}
