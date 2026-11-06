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

namespace enrol_apply\table;

use enrol_apply\local\groupfilter;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass targets below resolve when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/apply/renderer.php');

/**
 * The queue with groups (docs/design/group-screens.html, slice V2): the key group's column, the
 * group filter, the deferred tile counted over the reader's scope and the line naming a narrowed
 * reader's groups.
 *
 * The course is in separate groups mode with three groups, two of them named so that the
 * database's order and the natural one disagree ("Turma 10" sorts before "Turma 2" by bytes). The
 * moderator is a non-editing teacher made a decider, a member of both Turma groups and narrowed to
 * them; the editing teacher holds moodle/site:accessallgroups, is a member of Turma 2 and is the
 * reader who sees everything. Applications go through the plugin's own apply(), so each carries
 * its record and its key group.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(applications::class)]
#[CoversClass(applications_filterset::class)]
#[CoversClass(\enrol_apply\local\groupfilter::class)]
#[CoversClass(\enrol_apply_renderer::class)]
final class group_queue_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass A course in separate groups mode. */
    private $course;

    /** @var \stdClass Its apply instance. */
    private $instance;

    /** @var \stdClass Turma 2, the moderator's and the editing teacher's. */
    private $two;

    /** @var \stdClass Turma 10, the moderator's. */
    private $ten;

    /** @var \stdClass A group neither decider belongs to. */
    private $other;

    /** @var \stdClass Non-editing teacher made a decider, in Turma 2 and Turma 10. */
    private $moderator;

    /** @var \stdClass Editing teacher, who sees every group, in Turma 2. */
    private $editing;

    /**
     * The course, its groups and its two deciders.
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

        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $teacher, \context_system::instance()->id, true);

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS]);
        $id = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $this->two = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Turma 2']);
        $this->ten = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Turma 10']);
        $this->other = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Outra']);

        // The role's shortname: create_and_enrol() takes the shortname, not an id.
        $this->moderator = $generator->create_and_enrol($this->course, 'teacher');
        $generator->create_group_member(['groupid' => $this->two->id, 'userid' => $this->moderator->id]);
        $generator->create_group_member(['groupid' => $this->ten->id, 'userid' => $this->moderator->id]);
        $this->editing = $generator->create_and_enrol($this->course, 'editingteacher');
        $generator->create_group_member(['groupid' => $this->two->id, 'userid' => $this->editing->id]);

        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]));
        $PAGE->set_context(\context_course::instance($this->course->id));
    }

    /**
     * Write a pending application through the plugin's own apply(), as the administrator.
     *
     * @param \stdClass|null $group The key's group; null for an application without a key.
     * @return int The user enrolment id.
     */
    private function application_in(?\stdClass $group): int {
        global $USER;

        $previous = $USER;
        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $written = $method->invoke(
            $this->plugin,
            $this->instance,
            (int) $applicant->id,
            (object) ['applydescription' => 'Please'],
            $group ? (int) $group->id : 0,
            $group ? submission::KEY_FOR_REVIEW : submission::KEY_NONE
        );
        $sink->close();
        $this->setUser($previous);

        return (int) $written['userenrolmentid'];
    }

    /**
     * The queue's table for the current user, run, with what it listed.
     *
     * @param array $filters Raw values by filter name, as manage.php hands them to for_scope().
     * @param int|null $enrolid The instance to list, 0 for the site-wide queue, null for the fixture instance.
     * @return array The listed user enrolment ids in the table's order, the table and its markup.
     */
    private function queue_for(array $filters = [], ?int $enrolid = null): array {
        $table = applications::for_scope($enrolid ?? (int) $this->instance->id, '', null, $filters);
        ob_start();
        $table->out(50, false);
        $html = ob_get_clean();

        return [array_map(static fn($row) => (int) $row->userenrolmentid, array_values($table->rawdata)), $table, $html];
    }

    /**
     * The whole queue page for the current user, filters and capacity header included.
     *
     * @param array $filters Filter name => raw value.
     * @return string The markup.
     */
    private function page_for(array $filters = []): string {
        global $PAGE;

        $table = applications::for_scope((int) $this->instance->id, '', null, $filters);

        return $PAGE->get_renderer('enrol_apply')->manage_form(
            $table,
            new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]),
            $this->instance
        );
    }

    /**
     * How many cells of the page carry the group column's card label.
     *
     * @param string $html The table's markup.
     * @return int The count.
     */
    private function group_cells(string $html): int {
        return substr_count($html, 'enrol_apply-cardlabel">' . get_string('queuegroup', 'enrol_apply'));
    }

    /**
     * The column names the key group, says when there is none, and escapes the name.
     *
     * Changes that must make it fail: the cell written unescaped (flexible_table writes cells as
     * they come), the keyless wording dropped, or the column left out of the instance queue.
     *
     * @return void
     */
    public function test_the_column_names_the_key_group_and_the_keyless_rows(): void {
        global $DB;

        $DB->set_field('groups', 'name', 'R&D < Turma 2', ['id' => $this->two->id]);
        $this->application_in($this->two);
        $this->application_in(null);

        $this->setUser($this->editing);
        [$rows, , $html] = $this->queue_for();

        $this->assertCount(2, $rows);
        $this->assertSame(2, $this->group_cells($html));
        $this->assertStringContainsString('R&amp;D &lt; Turma 2', $html);
        $this->assertStringNotContainsString('R&D < Turma 2', $html);
        $this->assertStringContainsString(get_string('queuegroupnone', 'enrol_apply'), $html);
    }

    /**
     * A group the reader may not see leaves the cell blank, label included (V-D13).
     *
     * The editing teacher holds moodle/course:viewhiddengroups through the archetype, so it is
     * prohibited in the course for this test; the same page with the capability back is the
     * control.
     *
     * Changes that must make it fail: the visibility gate dropped from col_keygroup(), or
     * visible_ids() answering for a group it should not.
     *
     * @return void
     */
    public function test_a_group_hidden_from_the_reader_leaves_a_blank_cell(): void {
        global $DB;

        $hidden = $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'Turma secreta',
            'visibility' => GROUPS_VISIBILITY_NONE,
        ]);
        $this->application_in($hidden);
        $this->application_in($this->two);

        $role = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $context = \context_course::instance($this->course->id);
        $this->setUser($this->editing);

        // The control: the reader who may see hidden groups is told its name.
        [, , $html] = $this->queue_for();
        $this->assertStringContainsString('Turma secreta', $html);
        $this->assertSame(2, $this->group_cells($html));

        assign_capability('moodle/course:viewhiddengroups', CAP_PROHIBIT, $role, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        [$rows, , $html] = $this->queue_for();
        $this->assertCount(2, $rows);
        $this->assertStringNotContainsString('Turma secreta', $html);
        $this->assertStringContainsString('Turma 2', $html);
        $this->assertSame(1, $this->group_cells($html));
    }

    /**
     * A key whose group has been deleted since says so rather than reading as no key.
     *
     * @return void
     */
    public function test_a_deleted_group_reads_as_gone(): void {
        $this->application_in($this->other);
        groups_delete_group($this->other->id);

        $this->setUser($this->editing);
        [$rows, , $html] = $this->queue_for();

        $this->assertCount(1, $rows);
        $this->assertStringContainsString(get_string('keygroupgone', 'enrol_apply'), $html);
        $this->assertStringNotContainsString(get_string('queuegroupnone', 'enrol_apply'), $html);
    }

    /**
     * The column is on the site-wide queue, off the mentee queue and off a course without groups.
     *
     * Changes that must make it fail: the column shown to a mentor, who holds nothing in the
     * course, or dropped from the site-wide queue.
     *
     * @return void
     */
    public function test_the_column_is_where_v_d1_puts_it(): void {
        global $DB;

        $ueid = $this->application_in($this->two);

        $this->setAdminUser();
        [, , $html] = $this->queue_for([], 0);
        $this->assertSame(1, $this->group_cells($html));

        // A mentor of the applicant, holding the capability nowhere else.
        $applicantid = (int) $DB->get_field('user_enrolments', 'userid', ['id' => $ueid], MUST_EXIST);
        $mentor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applymentor']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $mentor->id, \context_user::instance($applicantid)->id);
        $this->setUser($mentor);
        [$rows, , $html] = $this->queue_for([], 0);
        $this->assertSame([$ueid], $rows, 'the precondition: the mentor lists the application');
        $this->assertSame(0, $this->group_cells($html));

        // A course with no group at all: every row would read "Without a group".
        $plain = $this->getDataGenerator()->create_course();
        $plainid = $this->plugin->add_instance($plain, $this->plugin->get_instance_defaults());
        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($DB->get_record('enrol', ['id' => $plainid]), $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        [$rows, , $html] = $this->queue_for([], (int) $plainid);
        $this->assertCount(1, $rows);
        $this->assertSame(0, $this->group_cells($html));
    }

    /**
     * Each value the editing teacher is offered selects its applications, and nothing else.
     *
     * `none` lists the keyless application and the one whose group has been deleted since: every
     * row is reached by exactly one value.
     *
     * Changes that must make it fail: the group predicate dropped from build_sql(), `mine` read as
     * every group, or `none` read as "no key group id" only.
     *
     * @return void
     */
    public function test_each_value_selects_its_applications(): void {
        $two = $this->application_in($this->two);
        $ten = $this->application_in($this->ten);
        $other = $this->application_in($this->other);
        $keyless = $this->application_in(null);
        $doomed = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Turma extinta']);
        $gone = $this->application_in($doomed);
        groups_delete_group($doomed->id);

        $this->setUser($this->editing);
        [$all] = $this->queue_for();
        $this->assertEqualsCanonicalizing([$two, $ten, $other, $keyless, $gone], $all);

        [$rows, $table] = $this->queue_for([groupfilter::NAME => (string) $this->ten->id]);
        $this->assertSame([$ten], $rows);
        $this->assertTrue($table->is_narrowed());
        $this->assertSame(5, $table->scope_total());

        [$rows] = $this->queue_for([groupfilter::NAME => groupfilter::MINE]);
        $this->assertSame([$two], $rows);

        [$rows] = $this->queue_for([groupfilter::NAME => groupfilter::NONE]);
        $this->assertEqualsCanonicalizing([$keyless, $gone], $rows);

        // The explicit "every group", and a value nobody is offered: no filter at all.
        foreach ([groupfilter::ANY, 'nonsense'] as $value) {
            [$rows, $table] = $this->queue_for([groupfilter::NAME => $value]);
            $this->assertEqualsCanonicalizing($all, $rows);
            $this->assertNull($table->get_group_filter());
            $this->assertFalse($table->is_narrowed());
        }
    }

    /**
     * A narrowed reader is offered their groups alone, and posting anything else changes nothing.
     *
     * Another group's id, `none` and `mine` are all refused, so the moderator keeps the listing of
     * their scope rather than an empty one: the value never reaches SQL.
     *
     * Changes that must make it fail: the value not cleaned against the offered list (another
     * group's id would then empty the listing), the offered list not narrowed to the reader's
     * groups, or `none` or `mine` offered to a narrowed reader.
     *
     * @return void
     */
    public function test_a_narrowed_reader_cannot_widen_or_reach_none_or_mine(): void {
        $two = $this->application_in($this->two);
        $ten = $this->application_in($this->ten);
        $this->application_in($this->other);
        $this->application_in(null);

        $this->setUser($this->moderator);
        [$scope, $table] = $this->queue_for();
        $this->assertEqualsCanonicalizing([$two, $ten], $scope);
        $this->assertSame(
            [(string) $this->two->id => 'Turma 2', (string) $this->ten->id => 'Turma 10'],
            $table->get_group_choices()
        );

        foreach ([(string) $this->other->id, groupfilter::NONE, groupfilter::MINE] as $value) {
            [$rows, $table] = $this->queue_for([groupfilter::NAME => $value]);
            $this->assertEqualsCanonicalizing($scope, $rows, "the value {$value}");
            $this->assertNull($table->get_group_filter(), "the value {$value}");
        }

        // The control: one of their own groups does narrow.
        [$rows] = $this->queue_for([groupfilter::NAME => (string) $this->ten->id]);
        $this->assertSame([$ten], $rows);
    }

    /**
     * `mine` is offered only to an unnarrowed member of a group, `none` only to who sees keyless rows.
     *
     * @return void
     */
    public function test_mine_and_none_are_offered_to_the_readers_rp_d27_names(): void {
        $this->setUser($this->editing);
        $listing = \enrol_apply\local\queue::listing_scope((int) $this->instance->id);
        $offered = groupfilter::offered($listing);
        $this->assertContains(groupfilter::MINE, $offered);
        $this->assertContains(groupfilter::NONE, $offered);

        // An editing teacher in no group sees keyless rows and has no groups of their own.
        $groupless = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($groupless);
        $offered = groupfilter::offered(\enrol_apply\local\queue::listing_scope((int) $this->instance->id));
        $this->assertNotContains(groupfilter::MINE, $offered);
        $this->assertContains(groupfilter::NONE, $offered);

        $this->setUser($this->moderator);
        $offered = groupfilter::offered(\enrol_apply\local\queue::listing_scope((int) $this->instance->id));
        $this->assertEqualsCanonicalizing([(string) $this->two->id, (string) $this->ten->id], $offered);
    }

    /**
     * The groups are listed in natural order, after "My groups" and "Without a group".
     *
     * Changes that must make it fail: the database's order kept, which puts Turma 10 first.
     *
     * @return void
     */
    public function test_the_groups_are_offered_in_natural_order(): void {
        $this->setUser($this->editing);
        [, $table] = $this->queue_for();

        $this->assertSame(
            [
                groupfilter::MINE,
                groupfilter::NONE,
                (string) $this->other->id,
                (string) $this->two->id,
                (string) $this->ten->id,
            ],
            array_map('strval', array_keys($table->get_group_choices()))
        );
    }

    /**
     * Up to eight choosable groups the control is a plain select, above eight a search (RP-D18).
     *
     * Counted over the groups alone: the editing teacher's "My groups" and "Without a group" do not
     * tip eight groups over the threshold.
     *
     * Changes that must make it fail: the threshold moved, or counted with the two extra values.
     *
     * @return void
     */
    public function test_the_control_becomes_a_search_above_eight_groups(): void {
        // Three groups exist; five more make eight.
        for ($i = 4; $i <= 8; $i++) {
            $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Turma ' . (100 + $i)]);
        }
        $this->setUser($this->editing);

        $html = $this->page_for();
        $this->assertStringContainsString('id="enrol_apply_filter_group"', $html, 'the precondition: the control is there');
        $this->assertStringNotContainsString('data-reloadonremove', $html);

        $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Turma 109']);
        $this->assertStringContainsString('data-reloadonremove', $this->page_for());
    }

    /**
     * In visible groups the page defaults the filter to the reader's groups, as a removable value.
     *
     * The page reads the default when the url names no group; `all` is the explicit "every group",
     * which the chip's remove link and every url of the unfiltered page carry. In separate groups,
     * and for a reader in no group, there is no default.
     *
     * Changes that must make it fail: the default not applied, applied in separate groups, applied
     * over an explicit `all`, or a removed default brought back by the next link.
     *
     * @return void
     */
    public function test_visible_groups_default_to_the_readers_groups(): void {
        global $DB;

        $DB->set_field('course', 'groupmode', VISIBLEGROUPS, ['id' => $this->course->id]);
        $two = $this->application_in($this->two);
        $this->application_in($this->ten);
        $this->application_in(null);
        $this->setUser($this->moderator);
        $listing = \enrol_apply\local\queue::listing_scope((int) $this->instance->id);

        unset($_GET[groupfilter::NAME]);
        $filters = applications::request_filters($listing);
        $this->assertSame(groupfilter::MINE, $filters[groupfilter::NAME]);
        [$rows] = $this->queue_for($filters);
        $this->assertCount(2, $rows, 'Turma 2 and Turma 10, not the keyless one');
        /* Two links say `all`: the chip's remove link and clear-all. The option itself is
           value="all", and every other url of the page carries the applied group=mine. */
        $this->assertSame(2, substr_count($this->page_for($filters), 'group=all'));

        $_GET[groupfilter::NAME] = groupfilter::ANY;
        $filters = applications::request_filters($listing);
        $this->assertSame(groupfilter::ANY, $filters[groupfilter::NAME]);
        [$rows, $table] = $this->queue_for($filters);
        $this->assertCount(3, $rows);
        $this->assertSame(groupfilter::ANY, $table->url_params()[groupfilter::NAME]);

        $_GET[groupfilter::NAME] = (string) $this->two->id;
        $this->assertSame((string) $this->two->id, applications::request_filters($listing)[groupfilter::NAME]);
        [$rows] = $this->queue_for(applications::request_filters($listing));
        $this->assertSame([$two], $rows);

        // Separate groups: no default, and so no explicit all either.
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $this->course->id]);
        unset($_GET[groupfilter::NAME]);
        $this->setUser($this->editing);
        $listing = \enrol_apply\local\queue::listing_scope((int) $this->instance->id);
        $this->assertArrayNotHasKey(groupfilter::NAME, applications::request_filters($listing));
    }

    /**
     * The deferred tile counts the reader's scope, beside the waiting tile, and says so (V-D3).
     *
     * One deferred application in the moderator's group and one in another: the moderator's tile
     * reads 1 "in your groups", the editing teacher's reads 2, the method's figure.
     *
     * Changes that must make it fail: the tile read from capacity::deferred() for a narrowed
     * reader, or the scope dropped from scope_deferred().
     *
     * @return void
     */
    public function test_the_deferred_tile_counts_the_readers_scope(): void {
        global $DB;

        $mine = $this->application_in($this->two);
        $theirs = $this->application_in($this->other);
        $this->application_in($this->ten);
        $DB->set_field('user_enrolments', 'status', ENROL_APPLY_USER_WAIT, ['id' => $mine]);
        $DB->set_field('user_enrolments', 'status', ENROL_APPLY_USER_WAIT, ['id' => $theirs]);
        $deferred = preg_quote(get_string('queuedeferred', 'enrol_apply'), '/');

        $this->setUser($this->moderator);
        [, $table] = $this->queue_for();
        $this->assertSame(1, $table->scope_deferred());
        $html = $this->page_for();
        $this->assertMatchesRegularExpression('/>1<\/span>\s*<span class="enrol_apply-tilelabel">' . $deferred . '/', $html);
        $this->assertStringContainsString(get_string('queueinyourgroups', 'enrol_apply'), $html);

        $this->setUser($this->editing);
        [, $table] = $this->queue_for();
        $this->assertSame(2, $table->scope_deferred());
        $this->assertSame(\enrol_apply\local\capacity::deferred($this->instance), $table->scope_deferred());
        $html = $this->page_for();
        $this->assertMatchesRegularExpression('/>2<\/span>\s*<span class="enrol_apply-tilelabel">' . $deferred . '/', $html);
        $this->assertStringNotContainsString(get_string('queueinyourgroups', 'enrol_apply'), $html);
    }

    /**
     * The method's capacity numbers stay on a narrowed queue, labelled as the method's.
     *
     * @return void
     */
    public function test_the_capacity_numbers_are_labelled_as_the_methods_when_narrowed(): void {
        global $DB;

        $DB->set_field('enrol', 'customint4', 20, ['id' => $this->instance->id]);
        $this->instance->customint4 = 20;
        $this->application_in($this->two);
        $note = get_string('queueonthismethod', 'enrol_apply');

        $this->setUser($this->moderator);
        $html = $this->page_for();
        $this->assertStringContainsString(get_string('queueplacestaken', 'enrol_apply'), $html);
        $this->assertStringContainsString($note, $html);

        $this->setUser($this->editing);
        $this->assertStringNotContainsString($note, $this->page_for());
    }

    /**
     * A narrowed reader is told which groups their queue shows, in natural order and escaped once
     * (V-D14); a reader who is not narrowed is told nothing.
     *
     * Changes that must make it fail: the line dropped, shown to every reader, or its names
     * escaped before the template escapes them again.
     *
     * @return void
     */
    public function test_a_narrowed_reader_is_told_which_groups_they_see(): void {
        global $DB;

        $DB->set_field('groups', 'name', 'Turma 10 & cia', ['id' => $this->ten->id]);
        $this->application_in($this->two);

        $this->setUser($this->moderator);
        $html = $this->page_for();
        $this->assertStringContainsString(
            s(get_string('queuescopegroups', 'enrol_apply', 'Turma 2, Turma 10 & cia')),
            $html
        );
        $this->assertStringNotContainsString('Turma 10 &amp;amp; cia', $html);

        $this->setUser($this->editing);
        $this->assertStringNotContainsString('enrol_apply-scopeline', $this->page_for());
    }

    /**
     * The group filter is declared, so core does not refuse it on the refresh path.
     *
     * @return void
     */
    public function test_the_filterset_declares_the_group_filter(): void {
        $this->assertArrayHasKey(groupfilter::NAME, (new applications_filterset())->get_optional_filters());
    }
}
