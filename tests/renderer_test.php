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
 * Tests for how the renderer spells the names it hands to its templates.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * Tests for how the renderer spells the names it hands to its templates.
 *
 * Both templates render these names through a double stash, so the renderer owes them the
 * PLAIN spelling. format_string()'s escape flag defaults to true, which is why every one of
 * these calls has to say so explicitly, and why the wrong spelling is invisible to phpcs, to
 * the mustache lint and to every other gate: nothing in the pipeline knows which stash a
 * value lands in.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_renderer::class)]
final class renderer_test extends \advanced_testcase {
    /**
     * A name carrying both characters format_string() rewrites.
     *
     * The "<" has a space after it on purpose. strip_tags() runs first whatever the escape
     * flag says, so "<b>" would be removed identically in both spellings and would prove
     * nothing; a "<" that is not the start of a tag survives to be escaped, and the bare "&"
     * is rewritten by replace_ampersands_not_followed_by_entity().
     */
    private const AWKWARD_NAME = 'R&D < Team';

    /** @var string The awkward name escaped exactly once, which is what a reader should get. */
    private const ESCAPED_ONCE = 'R&amp;D &lt; Team';

    /** @var string The awkward name escaped twice, which is what a reader must never get. */
    private const ESCAPED_TWICE = 'R&amp;amp;D &amp;lt; Team';

    /** @var \stdClass Course the apply instance belongs to. */
    private $course;

    /** @var \stdClass The enrol_apply instance record. */
    private $instance;

    /** @var \enrol_apply_plugin The plugin under test. */
    private $plugin;

    /**
     * Create a course carrying a single enabled apply enrolment instance.
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
     * A group name reaches the chooser escaped exactly once.
     *
     * Mutation check: drop the 'escape' => false option from the group name in
     * enrol_apply_renderer::decision_controls_context() and TWO tests go red, this one and
     * test_a_group_name_reaches_the_review_chooser_escaped_once. Two rather than one because
     * the queue and the review page read that one helper, which is the property being pinned;
     * the call used to live in manage_form(), where only this test could see it.
     *
     * @return void
     */
    public function test_a_group_name_reaches_the_chooser_escaped_once(): void {
        global $DB, $PAGE;

        $this->setAdminUser();
        $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => self::AWKWARD_NAME]);

        // The chooser is inside the hasrows block, so the queue needs an application in it.
        $applicant = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $DB->insert_record('enrol_apply_applicationinfo', (object) ['userenrolmentid' => $ueid, 'comment' => '']);

        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));

        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);

        $html = $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, $this->instance);

        /* Scoped to the chooser rather than matched against the whole page. The applicant's
           own name and the course name are in the same markup, so an unscoped assertion would
           pass on a match somewhere else entirely. */
        $this->assertMatchesRegularExpression('~<select[^>]*name="groups\[\]".*?</select>~s', $html);
        preg_match('~<select[^>]*name="groups\[\]".*?</select>~s', $html, $matches);
        $chooser = $matches[0];

        $this->assertStringContainsString(self::ESCAPED_ONCE, $chooser);
        $this->assertStringNotContainsString(self::ESCAPED_TWICE, $chooser);
    }

    /**
     * A role name reaches the chooser escaped exactly once.
     *
     * The renderer now normalises every role name through format_string() before the template's
     * triple stash sees it, because core hands back a MIXED list: role_get_name() escapes a role
     * whose role.name column is set and returns a bare get_string() for one whose column is
     * empty, which is every role a stock site ships.
     *
     * **This test holds only one of those two halves, and says so rather than implying more.**
     * It pins the escaped half — that normalising an already-escaped name does not escape it a
     * second time, which is the regression the new call could have introduced. The raw half is
     * NOT reachable from a fixture: its two sources are a core lang string and a role shortname,
     * and neither can be given an ampersand from a test (shortnames are alphanumeric, and
     * overriding a core string means driving tool_customlang and flushing its caches for one
     * assertion). It was verified by measurement instead — on m502 all eight stock roles return
     * an empty role.name while the site's own custom role returns "R&amp;D coordinator" — and by
     * reading role_get_name() at lib/accesslib.php:4575-4594 on both branches.
     *
     * **What this test holds, measured rather than assumed, is the STASH — not the
     * normalisation.** Switching the role option to a double stash reddens it, because an
     * already-escaped name then arrives escaped twice. Adding a second format_string() call
     * reddens NOTHING, because format_string() is idempotent; and removing the one that is there
     * reddens nothing either, for the fixture reason above. So the normalisation this test sits
     * beside is verified by measurement and by reading, and by no assertion in this file. Said
     * plainly because the alternative — a docblock claiming a mutation that does not happen — is
     * the exact shape this repository treats as its worst defect.
     *
     * @return void
     */
    public function test_a_role_name_reaches_the_chooser_escaped_once(): void {
        $roleid = $this->getDataGenerator()->create_role([
            'shortname' => 'awkwardrole',
            'name' => self::AWKWARD_NAME,
            'archetype' => 'student',
        ]);
        set_role_contextlevels($roleid, [CONTEXT_COURSE]);

        $html = $this->render_queue();

        preg_match('~<select[^>]*name="roleid".*?</select>~s', $html, $matches);
        $this->assertNotEmpty($matches, 'the role chooser is rendered');
        $chooser = $matches[0];

        // The premise: this role really is on offer, or the assertions below are vacuous.
        $this->assertStringContainsString('value="' . $roleid . '"', $chooser);

        $this->assertStringContainsString(self::ESCAPED_ONCE, $chooser);
        $this->assertStringNotContainsString(self::ESCAPED_TWICE, $chooser);
    }

    /**
     * The queue rendered with a filter applied that matches nothing.
     *
     * @param string $search Term to narrow by.
     * @return string The rendered form.
     */
    private function render_filtered_queue(string $search): string {
        global $DB, $PAGE;

        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user(['firstname' => 'Zephyrina', 'lastname' => 'Quillsworth']);
        $this->plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $DB->insert_record('enrol_apply_applicationinfo', (object) ['userenrolmentid' => $ueid, 'comment' => '']);

        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id, 'search' => $search]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));

        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id, $search);

        return $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, $this->instance);
    }

    /**
     * A filter that matches nothing leaves every control on the page.
     *
     * The queue used to gate its count line, its decision controls and its sticky footer on the
     * server-side "has rows", which a search that matches nothing makes false - so the operator
     * lost the box they had just typed in and had no way back but the browser's own history. It
     * is worse over AJAX: the refresh replaces the table's region alone and never re-renders this
     * template, so clearing the filter brought rows and checkboxes back with no bar to act on.
     *
     * The control is the row that exists but does not match: it proves the fixture has an
     * application, so an empty table here is the filter's doing.
     *
     * @return void
     */
    public function test_a_filter_matching_nothing_keeps_the_controls(): void {
        $rendered = $this->render_filtered_queue('nothingmatchesthis');

        // The row exists and did not match, which is what makes the assertions below about the filter.
        $this->assertStringNotContainsString('Quillsworth', $rendered);
        $this->assertStringContainsString(get_string('queuefilterempty', 'enrol_apply'), $rendered);
        $this->assertStringContainsString('data-region="queuefilters"', $rendered);
        $this->assertStringContainsString('name="search"', $rendered);
        // The bulk bar and its chooser, which used to vanish with the rows.
        $this->assertStringContainsString('enrol-apply-queue', $rendered);
    }

    /**
     * The capacity header counts the whole queue while the table counts the matches.
     *
     * The tile beside it reports deferrals read straight from \enrol_apply\local\capacity and is
     * instance-wide whatever was typed, so a filtered number in the first tile renders a pair that
     * cannot both be true - and it reads as a fault in the capacity figures rather than in the
     * count.
     *
     * @return void
     */
    public function test_the_capacity_header_counts_the_whole_queue_not_the_matches(): void {
        $rendered = $this->render_filtered_queue('nothingmatchesthis');

        // One application in scope, none matching: the header says 1 and the count line says 0 of 1.
        $this->assertStringContainsString('>1<', $rendered);
        $this->assertStringContainsString(
            get_string('queuefiltercount', 'enrol_apply', (object) ['matched' => 0, 'total' => 1]),
            $rendered
        );
    }

    /**
     * An empty queue with no filter applied still says what core says.
     *
     * The control on the filtered-empty message. That override exists because "Nothing to display"
     * over a search that matched nothing reads as "this queue is empty", which sends the operator
     * looking for a fault in the enrolment method rather than at the box they just typed in. Make
     * it fire unconditionally, though, and a method with no applications at all tells its manager
     * to clear filters that are not there.
     *
     * A Behat scenario asserts core's string on this same state, so the pair is held on both
     * sides; this is the half a mutation sweep can see, since mdl mutate runs PHPUnit.
     *
     * @return void
     */
    public function test_an_empty_queue_with_no_filter_says_what_core_says(): void {
        global $PAGE;

        $this->setAdminUser();
        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));

        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);
        $rendered = $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, $this->instance);

        $this->assertStringContainsString(get_string('nothingtodisplay'), $rendered);
        $this->assertStringNotContainsString(get_string('queuefilterempty', 'enrol_apply'), $rendered);
    }

    /**
     * A chip names the filter it removes, and its link drops only that one.
     *
     * @return void
     */
    public function test_a_chip_names_its_filter_and_removes_only_itself(): void {
        $rendered = $this->render_filtered_queue('quillsworth');

        $this->assertStringContainsString(
            get_string('queueremovefilter', 'enrol_apply', (object) [
                'name' => get_string('queuesearch', 'enrol_apply'),
                'value' => 'quillsworth',
            ]),
            $rendered
        );
        $this->assertStringContainsString(get_string('queueclearfilters', 'enrol_apply'), $rendered);
    }

    /**
     * Render the queue for one instance, with one pending application in it.
     *
     * @return string The rendered form.
     */
    private function render_queue(): string {
        global $DB, $PAGE;

        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $DB->insert_record('enrol_apply_applicationinfo', (object) ['userenrolmentid' => $ueid, 'comment' => '']);

        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));

        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);

        return $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, $this->instance);
    }

    /**
     * Render the queue for the scope that spans enrolment methods.
     *
     * @return string The rendered form.
     */
    private function render_sitewide_queue(): string {
        global $PAGE;

        $this->setAdminUser();
        $url = new \moodle_url('/enrol/apply/manage.php');
        $PAGE->set_url($url);
        $PAGE->set_context(\context_system::instance());

        $table = \enrol_apply\table\applications::for_scope(0);

        return $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, null);
    }

    /**
     * The decision context renders on an EMPTY queue, which is when it explains the most.
     *
     * The whole reason the header sits outside the decision form. A method whose applicant limit
     * is reached holds an empty queue - every application it is counting may be deferred, and a
     * deferred one is freed by nothing - so the state whose only symptom is "there is nothing
     * here" is exactly the state the numbers are for. Gated on rows, they would vanish at that
     * moment.
     *
     * The control used to be that the bulk bar is ABSENT here, which stopped being true when the
     * filters arrived: a search matching nothing must not take away the controls, so the template
     * gates nothing on the row count any more. The closed notice replaces it - this instance has
     * no applicant limit, so that notice is genuinely withheld, and the assertions above are
     * still not being satisfied by a template that renders everything it is given.
     *
     * @return void
     */
    public function test_the_capacity_header_renders_on_an_empty_queue(): void {
        global $PAGE;

        $this->setAdminUser();
        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));

        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);
        $html = $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, $this->instance);

        // The precondition: the queue really is empty, so this is about the empty case.
        $this->assertSame(0, (int) $table->totalrows);
        $this->assertStringContainsString(get_string('queueawaiting', 'enrol_apply'), $html);
        $this->assertStringContainsString(get_string('queuedeferred', 'enrol_apply'), $html);
        /* The control: a section the template really does withhold. Without an applicant limit
           there is no closed notice, so the assertions above are not being satisfied by a
           template that renders every string it is handed. */
        $this->assertStringNotContainsString(get_string('applicationsclosednotice', 'enrol_apply', (object) [
            'held' => 0,
            'limit' => 0,
            'deferred' => 0,
        ]), $html);
        // And the bulk bar IS here now, on an empty queue, which is the behaviour that replaced it.
        $this->assertStringContainsString(get_string('withselectedusers'), $html);
    }

    /**
     * No lang string reaches the queue with its placeholder still in it.
     *
     * **A test asserting that get_string(X) appears in the markup cannot see this**, because both
     * sides of the comparison read the same broken string and agree. Measured: five strings were
     * written with an escaped placeholder - `{\$a}` rather than `{$a}`, which PHP single quotes
     * keep verbatim - and every unit test over them passed while the page rendered the literal
     * text "{\$a} selected on this page". A Behat scenario spelling the expected words out is what
     * caught it, and this is the cheap general form of that: one assertion for the whole class,
     * over a page that renders the header, the rows and the bulk bar.
     *
     * @return void
     */
    public function test_no_placeholder_survives_into_the_queue(): void {
        $html = $this->render_queue();

        // The control: the page really did render, so this is not passing over an empty string.
        $this->assertStringContainsString(get_string('queueawaiting', 'enrol_apply'), $html);
        $this->assertStringNotContainsString('{$a', $html);
    }

    /**
     * The status block renders for a queue scoped to one enrolment method.
     *
     * It did not, and the reason is the trap this repository documented earlier the same day and
     * gated as `BW` earlier the same day: the context was returned with `$context + [...]`, and `+` keeps the LEFT
     * side on a duplicate key - so the `hasstatus => false` set for the scopes that span methods
     * silently won over the `true` set here, and the block never appeared on any instance-scoped
     * queue. Every test passed, because none of them asserted the block existed.
     *
     * @return void
     */
    public function test_the_status_block_renders_for_one_method(): void {
        $html = $this->render_queue();

        $this->assertStringContainsString(get_string('queuestatus', 'enrol_apply'), $html);
        $this->assertStringContainsString(get_string('queueapplicationsopen', 'enrol_apply'), $html);
        /* The control: the scope that spans methods really does go without it, so this is about
           the merge rather than about a block that renders unconditionally. */
        $this->assertStringNotContainsString(
            get_string('queuestatus', 'enrol_apply'),
            $this->render_sitewide_queue()
        );
    }

    /**
     * The card view labels every cell it shows, with the wording that column actually carries.
     *
     * Below the breakpoint each labelled cell carries its own heading as REAL TEXT, hidden by
     * the stylesheet above that width. It used to be a data-* attribute drawn with
     * content: attr(), which is announced inconsistently by screen readers and - worse - leans on
     * a thead association that turning rows into blocks has already destroyed. Nothing else in
     * the suite reads these headings, which is exactly the kind of markup that rots unnoticed.
     *
     * The comment column is the one worth naming: its heading is the question the teacher
     * configured, and the card said "Comment" while the desktop header said what was asked.
     *
     * @return void
     */
    public function test_the_card_view_labels_the_comment_column_with_its_own_wording(): void {
        global $DB;

        $DB->set_field('enrol', 'customtext2', 'Why you want in', ['id' => $this->instance->id]);

        /* Through the helper that seeds a row, because the attributes live on CELLS: an empty
           queue renders "Nothing to display" and no data-label at all, so a test without an
           applicant would pass or fail for the wrong reason. The helper re-reads the instance
           through listing_scope(), so the field set above is the one the table sees. */
        $html = $this->render_queue();

        $this->assertMatchesRegularExpression(
            '/class="enrol_apply-cardlabel"[^>]*>Why you want in</',
            $html,
            $html
        );
        // The control: the columns that keep the shipped wording still carry a heading at all.
        $this->assertMatchesRegularExpression(
            '/class="enrol_apply-cardlabel"[^>]*>' . preg_quote(get_string('applydate', 'enrol_apply'), '/') . '</',
            $html,
            $html
        );
    }

    /**
     * A method that is closed says so, and one near its limit says how much room is left.
     *
     * Three branches of the status block that no other test reaches, and their two strings carry
     * placeholders - so test_no_placeholder_survives_into_the_queue cannot see them either: a
     * string that is never rendered cannot render a placeholder.
     *
     * @return void
     */
    public function test_the_status_block_reports_a_closed_method_and_the_room_left(): void {
        global $DB, $PAGE;

        $this->setAdminUser();

        // Near the limit: four of five held, so one more will be accepted.
        $DB->set_field('enrol', 'customint3', 5, ['id' => $this->instance->id]);
        foreach (range(1, 4) as $ignored) {
            $user = $this->getDataGenerator()->create_user();
            $this->plugin->enrol_user($this->instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
        }
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));
        $renderer = $PAGE->get_renderer('enrol_apply');
        $open = $renderer->manage_form(
            \enrol_apply\table\applications::for_scope((int) $this->instance->id),
            $url,
            $this->instance
        );

        $this->assertStringContainsString(get_string('queueapplicationsopen', 'enrol_apply'), $open);
        $this->assertStringContainsString(get_string('queueremaining', 'enrol_apply', 1), $open);
        $this->assertStringNotContainsString('{$a', $open);

        // And at the limit it is closed, with the room-left sentence gone rather than reading zero.
        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $closed = $renderer->manage_form(
            \enrol_apply\table\applications::for_scope((int) $this->instance->id),
            $url,
            $this->instance
        );

        $this->assertStringContainsString(get_string('queueapplicationsclosed', 'enrol_apply'), $closed);
        $this->assertStringNotContainsString(get_string('queueremaining', 'enrol_apply', 0), $closed);
    }

    /**
     * A closing date is named while the method is still open.
     *
     * The remaining branch, and the second placeholder string the page can render.
     *
     * @return void
     */
    public function test_the_status_block_names_the_closing_date(): void {
        global $DB, $PAGE;

        $this->setAdminUser();
        $when = time() + (30 * DAYSECS);
        $DB->set_field('enrol', 'enrolenddate', $when, ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));
        $html = $PAGE->get_renderer('enrol_apply')->manage_form(
            \enrol_apply\table\applications::for_scope((int) $this->instance->id),
            $url,
            $this->instance
        );

        $this->assertStringContainsString(
            get_string('queuecloseson', 'enrol_apply', userdate($when, get_string('strftimedate', 'langconfig'))),
            $html
        );
        $this->assertStringNotContainsString('{$a', $html);
    }

    /**
     * A meter never draws past the end of its bar.
     *
     * Both numbers can legitimately exceed their own limit: an administrator can enrol past the
     * places cap by hand, and the applicant limit can be lowered under applications already held.
     * An unclamped width would render as a bar overflowing its track, which reads as a rendering
     * fault rather than as the over-capacity state it is.
     *
     * @return void
     */
    public function test_a_meter_never_overflows_its_bar(): void {
        global $DB, $PAGE;

        $this->setAdminUser();

        // One PLACE (customint4), and two applicants already approved into it.
        $this->instance->customint4 = 1;
        $DB->update_record('enrol', $this->instance);
        foreach (range(1, 2) as $ignored) {
            $user = $this->getDataGenerator()->create_user();
            $this->plugin->enrol_user($this->instance, $user->id, null, 0, 0, ENROL_USER_ACTIVE);
        }

        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));
        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);
        $html = $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, $this->instance);

        // The precondition: the meter is really over capacity, so a clamp is what is under test.
        $this->assertStringContainsString(
            get_string('reviewofmany', 'enrol_apply', (object) ['taken' => 2, 'total' => 1]),
            $html
        );
        $this->assertMatchesRegularExpression('/enrol_apply-meterfill[^"]*"\s+style="width: 100%"/', $html, $html);
        /* The value the arithmetic produces without the clamp, named exactly. A pattern for "any
           width over 100" is the shape that goes wrong here: 100 itself matches most of them. */
        $this->assertStringNotContainsString('width: 200%', $html);
    }

    /**
     * Every checkbox in the queue speaks core/checkbox-toggleall's vocabulary, in one group.
     *
     * The three data attributes are what core's module matches on; the plugin's own markup
     * carried none of them, so nothing in the queue was wired to core before this. The group
     * name is asserted literally rather than through the constant, because the whole point is
     * that the header, the rows and the bar agree on one string - reading the constant in the
     * test would make a rename invisible.
     *
     * Mutation check, measured against the whole suite: renaming TOGGLE_GROUP reddens TWO tests,
     * this one and test_the_bulk_action_is_wired_to_the_same_group, and nothing else. Two rather
     * than one because the header, the rows and the bar all read the one constant - which is the
     * property being pinned. Core itself gives no signal at all if they disagree: the targets
     * match by prefix and the action element by an exact string, so a mismatch quietly stops
     * working.
     *
     * @return void
     */
    public function test_the_queue_checkboxes_are_core_toggleall_targets(): void {
        $html = $this->render_queue();

        $this->assertMatchesRegularExpression(
            '~<input[^>]*name="userenrolments\[\]"[^>]*>~',
            $html,
            'the POST field name must survive the move to core markup'
        );

        preg_match('~<input[^>]*name="userenrolments\[\]"[^>]*>~', $html, $row);
        $this->assertStringContainsString('data-action="toggle"', $row[0]);
        $this->assertStringContainsString('data-toggle="target"', $row[0]);
        $this->assertStringContainsString('data-togglegroup="enrol-apply-queue"', $row[0]);

        preg_match('~<input[^>]*id="enrol_apply_toggleall"[^>]*>~', $html, $header);
        $this->assertNotEmpty($header, 'the header checkbox is still there');
        $this->assertStringContainsString('data-toggle="toggler"', $header[0]);
        $this->assertStringContainsString('data-togglegroup="enrol-apply-queue"', $header[0]);
    }

    /**
     * The bulk action carries the toggle-all action vocabulary, in the same group.
     *
     * getActionElements() matches the group EXACTLY where the targets match by prefix, so a
     * mismatch here disables nothing and reports nothing. That silence is the reason this is
     * asserted rather than left to the browser.
     *
     * Mutation check, measured against the whole suite: removing the toggle-all attributes from
     * the action select reddens exactly this test.
     *
     * @return void
     */
    public function test_the_bulk_action_is_wired_to_the_same_group(): void {
        $html = $this->render_queue();

        preg_match('~<select[^>]*name="formaction"[^>]*>~', $html, $select);
        $this->assertNotEmpty($select, 'the action select is still there');
        $this->assertStringContainsString('data-action="toggle"', $select[0]);
        $this->assertStringContainsString('data-toggle="action"', $select[0]);
        $this->assertStringContainsString('data-togglegroup="enrol-apply-queue"', $select[0]);
    }

    /**
     * The action bar sits in core's sticky footer, and that footer sits inside the form.
     *
     * Both halves matter and only the second is obvious. A sticky footer rendered outside the
     * form would post nothing - the action select and the Go button would simply not be part of
     * the submission - and the page would look perfectly correct while every decision silently
     * did nothing. Core places its own inside the form for the same reason
     * (grade/templates/edit_tree.mustache).
     *
     * Mutation check, measured against the whole suite: rendering the footer outside the form
     * reddens exactly this test.
     *
     * @return void
     */
    public function test_the_action_bar_is_inside_the_sticky_footer_and_the_form(): void {
        $html = $this->render_queue();

        $footerat = strpos($html, 'id="sticky-footer"');
        $this->assertNotFalse($footerat, 'the bar is rendered into core\'s sticky footer');

        /* The DECISION form specifically. A plain search for the first "<form " now finds the
           filter bar's GET form, which is rendered before this one and must be - HTML forbids
           nested forms, so a search box inside the decision form would submit a decision. */
        $formopen = strpos($html, '<form id="enrol_apply_manage_form"');
        $formclose = strpos($html, '</form>', $formopen);
        $this->assertNotFalse($formopen);
        $this->assertNotFalse($formclose);
        $this->assertGreaterThan($formopen, $footerat, 'the footer opens after the form does');
        $this->assertLessThan($formclose, $footerat, 'and closes before the form does');

        // The action itself is in the footer, not merely on the page somewhere.
        $footer = substr($html, $footerat, $formclose - $footerat);
        $this->assertStringContainsString('name="formaction"', $footer);
        $this->assertStringContainsString('type="submit"', $footer);
    }

    /**
     * The decision inputs stay in the page body, above the bar.
     *
     * A core sticky footer is a fixed bar - height max(80px, 3rem) - whose
     * .sticky-footer-content carries overflow hidden, so a three-row textarea put in it is
     * clipped. The bar is for the action; the decision's own inputs are not actions.
     *
     * Mutation check, measured against the whole suite: rendering the footer above the decision
     * inputs instead of below them reddens exactly this test.
     *
     * @return void
     */
    public function test_the_decision_inputs_stay_out_of_the_sticky_footer(): void {
        $html = $this->render_queue();

        $footerat = strpos($html, 'id="sticky-footer"');
        $this->assertNotFalse($footerat);

        $body = substr($html, 0, $footerat);
        $this->assertStringContainsString('name="outcomemessage"', $body);
        $this->assertStringContainsString('name="roleid"', $body);
    }

    /**
     * Render the single-application review page for a fresh applicant.
     *
     * @param string $comment Comment submitted with the application.
     * @param int $status One of ENROL_USER_SUSPENDED and ENROL_APPLY_USER_WAIT.
     * @param string $snapshot Stored profile snapshot envelope, empty for none.
     * @param array $applicantfields Extra fields for the applicant's own user record.
     * @param \stdClass|null $applicant Apply as this existing user instead of a fresh one, for
     *        the tests that have to seed something about them first.
     * @return string Rendered markup.
     */
    private function render_review(
        string $comment = 'Please let me in',
        int $status = ENROL_USER_SUSPENDED,
        string $snapshot = '',
        array $applicantfields = [],
        ?\stdClass $applicant = null
    ): string {
        global $DB, $PAGE;

        /* Only when the caller has not already chosen a reader. The capability tests set a
           teacher and would have it replaced by the administrator here. */
        if ($applicant === null) {
            $this->setAdminUser();
        }
        $applicant = $applicant ?? $this->getDataGenerator()->create_user($applicantfields);
        $this->plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, $status);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        /* Replaced rather than inserted: a test may render the same applicant twice - the
           capability tests render once per reader - and applicationinfo carries a unique key on
           the user enrolment, so a second insert would die rather than fail an assertion. */
        $DB->delete_records('enrol_apply_applicationinfo', ['userenrolmentid' => $ueid]);
        $DB->delete_records('enrol_apply_submission', ['userenrolmentid' => $ueid]);

        /* Written to the DURABLE record, which is where queue::application() reads it from
           first; the applicationinfo row carries something different on purpose, so a
           COALESCE that took the wrong arm would show it. */
        $DB->insert_record('enrol_apply_applicationinfo', (object) [
            'userenrolmentid' => $ueid,
            'comment' => 'FALLBACK COMMENT',
        ]);
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $applicant->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => $ueid,
            'status' => \enrol_apply\local\submission::STATUS_PENDING,
            'comment' => $comment,
            'userinfodata' => $snapshot,
            'outcomemessage' => '',
            'decidedgroups' => '',
            'decidedrole' => 0,
            'decidedby' => 0,
            'timecreated' => time(),
            'timedecided' => 0,
        ]);

        $url = new \moodle_url('/enrol/apply/manage.php', ['userenrol' => $ueid]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));

        $application = \enrol_apply\local\queue::application($ueid);

        return $PAGE->get_renderer('enrol_apply')->review_form($application, $applicant, $this->instance, $url);
    }

    /**
     * The review page posts exactly the contract manage.php's handler requires.
     *
     * A formaction naming the decision, a non-empty userenrolments array and the session key.
     * That is what lets one handler serve both decision surfaces with no branch of its own, and
     * it is the contract to keep if either surface is ever rebuilt.
     *
     * @return void
     */
    public function test_the_review_page_posts_the_queues_own_contract(): void {
        $html = $this->render_review();

        $this->assertMatchesRegularExpression('~<input[^>]*type="hidden"[^>]*name="sesskey"~', $html, $html);
        $this->assertMatchesRegularExpression(
            '~<input[^>]*type="hidden"[^>]*name="userenrolments\[\]"[^>]*value="\d+"~',
            $html,
            $html
        );
        foreach (['confirm', 'wait', 'cancel'] as $action) {
            $this->assertMatchesRegularExpression(
                '~<button[^>]*type="submit"[^>]*name="formaction"[^>]*value="' . $action . '"~',
                $html,
                $action . ': ' . $html
            );
        }
    }

    /**
     * The review page offers the same three decision controls the queue does.
     *
     * They come from one partial precisely so the two surfaces cannot offer different things;
     * this asserts the review page really renders it rather than a copy that has drifted.
     *
     * @return void
     */
    public function test_the_review_page_offers_the_group_and_role_choosers(): void {
        $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Tutorial A']);

        $html = $this->render_review();

        $this->assertMatchesRegularExpression('~<select[^>]*name="groups\[\]"~', $html, $html);
        $this->assertMatchesRegularExpression('~<select[^>]*name="roleid"~', $html, $html);
        $this->assertMatchesRegularExpression('~<textarea[^>]*name="outcomemessage"~', $html, $html);
    }

    /**
     * A group name reaches the review page's chooser escaped exactly once, as the queue's does.
     *
     * Mutation check: dropping the escape option from decision_controls_context() reddens this
     * and test_a_group_name_reaches_the_chooser_escaped_once, and nothing else - which is the
     * property being pinned, since both surfaces read the one helper.
     *
     * @return void
     */
    public function test_a_group_name_reaches_the_review_chooser_escaped_once(): void {
        $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => self::AWKWARD_NAME,
        ]);

        $html = $this->render_review();

        $this->assertSame(1, preg_match('~<select[^>]*name="groups\[\]".*?</select>~s', $html, $select), $html);
        $this->assertStringContainsString(self::ESCAPED_ONCE, $select[0]);
        $this->assertStringNotContainsString(self::ESCAPED_TWICE, $select[0]);
        $this->assertStringNotContainsString(self::AWKWARD_NAME, $select[0]);
    }

    /**
     * The identity line carries what the site named, and nothing else.
     *
     * The e-mail address used to have a row of its own and be printed unconditionally, while the
     * snapshot panel beside it masked identity fields from the same reader. It is now one
     * identity field among the rest, resolved by core's own helper - so a site that does not
     * name it does not see it here, exactly as its participants page behaves.
     *
     * @return void
     */
    public function test_the_identity_line_respects_showuseridentity(): void {
        global $CFG;

        $CFG->showuseridentity = 'idnumber';
        $CFG->hiddenuserfields = '';

        $html = $this->render_review('Please let me in', ENROL_USER_SUSPENDED, '', [
            'idnumber' => 'RA-2026-0042',
            'email' => 'ana@example.org',
        ]);

        $this->assertStringContainsString('RA-2026-0042', $html);
        // The address is not named by the site, so this page does not show it either.
        $this->assertStringNotContainsString('ana@example.org', $html);
    }

    /**
     * And it shows the address when the site does name it.
     *
     * The control for the test above: without it, a page that had dropped the identity line
     * altogether would satisfy "the address is absent" just as well as a correct one.
     *
     * @return void
     */
    public function test_the_identity_line_shows_the_address_when_the_site_names_it(): void {
        global $CFG;

        $CFG->showuseridentity = 'email';
        $CFG->hiddenuserfields = '';

        $html = $this->render_review('Please let me in', ENROL_USER_SUSPENDED, '', [
            'email' => 'ana@example.org',
        ]);

        $this->assertStringContainsString('ana@example.org', $html);
    }

    /**
     * The earlier applications need their own capability, not the one that opens the page.
     *
     * enrol/apply:viewreports is manager-only by archetype and deliberately narrower than
     * manageapplications: what somebody applied for and what was decided is the disclosure the
     * report exists to control. A reader without it gets no panel at all rather than an empty
     * one, because a heading that appears only when there IS history is itself a disclosure.
     *
     * @return void
     */
    public function test_earlier_applications_need_the_report_capability(): void {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        // An earlier, cancelled application by the same person to the same course.
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $applicant->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => 0,
            'status' => \enrol_apply\local\submission::STATUS_CANCELLED,
            'comment' => '',
            'userinfodata' => '',
            'outcomemessage' => '',
            'decidedgroups' => '',
            'decidedrole' => 0,
            'decidedby' => 0,
            'timecreated' => time() - DAYSECS,
            'timedecided' => time() - DAYSECS,
        ]);

        $this->setAdminUser();
        $html = $this->render_review('Please let me in', ENROL_USER_SUSPENDED, '', [], $applicant);
        $this->assertStringContainsString(get_string('reviewhistory', 'enrol_apply'), $html);

        /* A teacher holds manageapplications and opens this page, and does NOT hold viewreports
           by archetype - which is the whole point of the second capability. */
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);
        $html = $this->render_review('Please let me in', ENROL_USER_SUSPENDED, '', [], $applicant);
        $this->assertStringNotContainsString(get_string('reviewhistory', 'enrol_apply'), $html);
    }

    /**
     * The capacity panel reports both numbers, and says so when there is no limit.
     *
     * @return void
     */
    public function test_the_capacity_panel_reports_both_numbers(): void {
        global $DB;

        $this->setAdminUser();

        /* Two DIFFERENT limits, and a fixture whose two counts also differ. The first version of
           this test capped places and left applicants uncapped, which reads as a fair test and is
           not one: it passed with the two numbers fully swapped, because "of 20" appeared either
           way. Mixing these two is the standing hazard this plugin records in CLAUDE.md, so the
           test has to be able to see it. */
        $DB->set_field('enrol', 'customint4', 20, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'customint3', 30, ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        /* One ACTIVE enrolment and one still awaiting a decision. Places count the active row
           only; applicants count both - so the two counts are 1 and 2 and cannot be confused. */
        $approved = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $approved->id, null, 0, 0, ENROL_USER_ACTIVE);

        $html = $this->render_review();

        $this->assertStringContainsString(get_string('reviewcapacity', 'enrol_apply'), $html);

        /* Each value asserted INSIDE its own row. A bare assertStringContainsString over the whole
           page cannot see the two numbers swapped - both strings are still present, just against
           the other label - and the first two versions of this test passed against exactly that
           mutation. It is the trap CLAUDE.md states in general: extract the element, then assert
           inside it. */
        $this->assertSame(
            get_string('reviewofmany', 'enrol_apply', (object) ['taken' => 1, 'total' => 20]),
            $this->capacity_row($html, get_string('places', 'enrol_apply')),
            'places must count the ACTIVE enrolment only, against customint4'
        );
        $this->assertSame(
            get_string('reviewofmany', 'enrol_apply', (object) ['taken' => 2, 'total' => 30]),
            $this->capacity_row($html, get_string('reviewapplicants', 'enrol_apply')),
            'applicants must count every non-expired row, against customint3'
        );
    }

    /**
     * The capacity panel reports how many of those applications are deferred.
     *
     * A third row, and it is a subset of the applicants row rather than a limit of its own. It
     * is here because a deferred application counts against the applicant cap for ever and
     * nothing frees it - so a method refusing new applications with an empty queue has no other
     * screen able to explain itself.
     *
     * The three rows carry three DIFFERENT values on this fixture, and each is asserted inside
     * its own row. That is the trap this file has already walked into twice: both numbers stay
     * on the page when they are swapped, so only a row-scoped assertion can see it.
     *
     * @return void
     */
    public function test_the_capacity_panel_reports_the_deferred_count(): void {
        global $DB;

        $this->setAdminUser();

        $DB->set_field('enrol', 'customint4', 20, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'customint3', 30, ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $deferred = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $deferred->id, null, 0, 0, ENROL_APPLY_USER_WAIT);
        $approved = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $approved->id, null, 0, 0, ENROL_USER_ACTIVE);

        // Adds a third, still awaiting a decision.
        $html = $this->render_review();

        $this->assertSame(
            '1',
            $this->capacity_row($html, get_string('reviewdeferred', 'enrol_apply')),
            'the deferred row must count the deferred row and nothing else'
        );
        $this->assertSame(
            get_string('reviewofmany', 'enrol_apply', (object) ['taken' => 1, 'total' => 20]),
            $this->capacity_row($html, get_string('places', 'enrol_apply')),
            'places must still count the ACTIVE enrolment only'
        );
        $this->assertSame(
            get_string('reviewofmany', 'enrol_apply', (object) ['taken' => 3, 'total' => 30]),
            $this->capacity_row($html, get_string('reviewapplicants', 'enrol_apply')),
            'applicants must still count all three'
        );
    }

    /**
     * The value rendered against one label of the capacity panel.
     *
     * @param string $html The rendered review page.
     * @param string $label The row's label.
     * @return string The value beside it, or the empty string when the row is absent.
     */
    private function capacity_row(string $html, string $label): string {
        $pattern = '~<dt[^>]*>' . preg_quote($label, '~') . '</dt>\s*<dd[^>]*>(.*?)</dd>~s';

        return preg_match($pattern, $html, $m) ? trim($m[1]) : '';
    }

    /**
     * A mentor is shown the course, and not the method's limits or its enrolment counts.
     *
     * They reach this page through the applicant's own user context holding nothing in the
     * course, which is the whole point of that delegation - and the same line the group and role
     * choosers already draw. What they lose is context for their decision; the instance's limits
     * still apply to it either way.
     *
     * @return void
     */
    public function test_a_mentor_is_not_shown_the_methods_capacity(): void {
        global $DB;

        $DB->set_field('enrol', 'customint4', 20, ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        /* A user holding the deciding capability in a USER context and nothing in the course -
           the mentor shape, built the way applications_test builds it. */
        $mentor = $this->getDataGenerator()->create_user();
        $applicant = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role([
            'shortname' => 'applymentor2',
            'name' => 'Apply mentor',
            'archetype' => '',
        ]);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability(
            'enrol/apply:manageapplications',
            CAP_ALLOW,
            $roleid,
            \context_system::instance()->id
        );
        role_assign($roleid, $mentor->id, \context_user::instance($applicant->id)->id);

        $this->setUser($mentor);
        $html = $this->render_review('Please let me in', ENROL_USER_SUSPENDED, '', [], $applicant);

        $this->assertStringNotContainsString(get_string('reviewcapacity', 'enrol_apply'), $html);
        $this->assertStringNotContainsString('of 20', $html);
        // The control: they are seeing the page, and the course it belongs to.
        $this->assertStringContainsString($this->course->fullname, $html);
    }

    /**
     * An uncapped number says so rather than showing a bare zero.
     *
     * @return void
     */
    public function test_the_capacity_panel_says_when_there_is_no_limit(): void {
        global $DB;

        $this->setAdminUser();

        $DB->set_field('enrol', 'customint4', 0, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'customint3', 0, ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $html = $this->render_review();

        $this->assertStringContainsString(get_string('reviewnolimit', 'enrol_apply'), $html);
        $this->assertStringNotContainsString('of 0', $html);
    }

    /**
     * A deferred application says who deferred it, when, and what they wrote.
     *
     * The one case where the reader is looking at something a colleague already decided. The page
     * used to say only "On the waiting list", while every one of these facts sat on a table
     * queue::application() was already joining and simply not selecting.
     *
     * @return void
     */
    public function test_a_deferred_application_names_its_decider(): void {
        global $DB;

        $decider = $this->getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Souza']);
        $this->setAdminUser();

        $html = $this->render_review('Please let me in', ENROL_APPLY_USER_WAIT);

        // The fixture writes the record as pending; stamp the decision the page has to describe.
        $ueid = (int) $DB->get_field_sql(
            "SELECT MAX(id) FROM {user_enrolments} WHERE enrolid = :enrolid",
            ['enrolid' => $this->instance->id]
        );
        $where = ['userenrolmentid' => $ueid];
        $waiting = \enrol_apply\local\submission::STATUS_WAITING;
        $DB->set_field('enrol_apply_submission', 'status', $waiting, $where);
        $DB->set_field('enrol_apply_submission', 'decidedby', $decider->id, $where);
        $DB->set_field('enrol_apply_submission', 'timedecided', 1786000000, $where);
        $DB->set_field('enrol_apply_submission', 'outcomemessage', 'Waiting for the second group.', $where);

        global $PAGE;
        $application = \enrol_apply\local\queue::application($ueid);
        $applicant = \core_user::get_user($application->userid, '*', MUST_EXIST);
        $html = $PAGE->get_renderer('enrol_apply')->review_form(
            $application,
            $applicant,
            $this->instance,
            new \moodle_url('/enrol/apply/manage.php', ['userenrol' => $ueid])
        );

        $this->assertStringContainsString('Ana Souza', $html);
        $this->assertStringContainsString('Waiting for the second group.', $html);
    }

    /**
     * Both decision surfaces offer the note box, because both read one partial.
     *
     * The queue and the review page share enrol_apply/decision_controls precisely so that they
     * cannot offer different things, and a field present on one and silently absent on the other
     * is how two surfaces come to describe the same record differently.
     *
     * @return void
     */
    public function test_both_decision_surfaces_offer_the_note_box(): void {
        global $DB, $PAGE;

        $this->assertMatchesRegularExpression(
            '~<textarea[^>]*name="decisionnote"~',
            $this->render_review()
        );

        $applicant = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));
        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);

        $this->assertMatchesRegularExpression(
            '~<textarea[^>]*name="decisionnote"~',
            $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, $instance)
        );
    }

    /**
     * A deferred application shows the note the last decider left, and does NOT pre-fill it.
     *
     * The two halves are one property. The note is shown because that is what the column is
     * for - the next member of staff reads why. It is not pre-filled into the box because the
     * writer clears on empty on purpose: a pre-filled box would carry one decision's reason
     * silently into the next, which is the exact defect the outcome message was fixed for.
     *
     * A whole-page assertion cannot see the second half, because the note is legitimately on the
     * page twice over in the failing case. The textarea is extracted and read on its own.
     *
     * @return void
     */
    public function test_a_deferred_application_shows_its_note_without_pre_filling_the_box(): void {
        global $DB, $PAGE;

        $this->setAdminUser();
        $this->render_review('Please let me in', ENROL_APPLY_USER_WAIT);

        $ueid = (int) $DB->get_field_sql(
            "SELECT MAX(id) FROM {user_enrolments} WHERE enrolid = :enrolid",
            ['enrolid' => $this->instance->id]
        );
        $where = ['userenrolmentid' => $ueid];
        $DB->set_field('enrol_apply_submission', 'status', \enrol_apply\local\submission::STATUS_WAITING, $where);
        $DB->set_field('enrol_apply_submission', 'timedecided', 1786000000, $where);
        $DB->set_field('enrol_apply_submission', 'decisionnote', 'Holding for the September intake.', $where);

        $application = \enrol_apply\local\queue::application($ueid);
        $applicant = \core_user::get_user($application->userid, '*', MUST_EXIST);
        $html = $PAGE->get_renderer('enrol_apply')->review_form(
            $application,
            $applicant,
            $this->instance,
            new \moodle_url('/enrol/apply/manage.php', ['userenrol' => $ueid])
        );

        $this->assertStringContainsString('Holding for the September intake.', $html);
        $this->assertStringContainsString(get_string('decisionnote', 'enrol_apply'), $html);
        $this->assertSame('', $this->textarea_value($html, 'decisionnote'), $html);
    }

    /**
     * What one named textarea actually contains.
     *
     * @param string $html Rendered markup.
     * @param string $name The textarea's name attribute.
     * @return string Its content, or the empty string when there is no such textarea.
     */
    private function textarea_value(string $html, string $name): string {
        $pattern = '~<textarea[^>]*name="' . preg_quote($name, '~') . '"[^>]*>(.*?)</textarea>~s';

        return preg_match($pattern, $html, $m) ? trim($m[1]) : '';
    }

    /**
     * A pending application describes no decision, because none has been taken.
     *
     * The control: without it, a panel that rendered for every application would satisfy the
     * test above just as well as one that rendered for the right ones.
     *
     * @return void
     */
    public function test_a_pending_application_describes_no_decision(): void {
        $this->setAdminUser();

        $html = $this->render_review();

        $this->assertStringNotContainsString(get_string('reviewdecision', 'enrol_apply'), $html);
    }

    /**
     * The instance's own comment label heads the review page, escaped exactly once.
     *
     * This is the sink that differs from the other two, and the reason the helper takes a flag.
     * The queue's column header and the applicant form's element label both render RAW and want
     * the escaped spelling; review.mustache renders this one through a DOUBLE stash, so it wants
     * the PLAIN spelling and Mustache escapes it. Handing it the escaped one shows the reader the
     * entities, which is a defect no gate in this repository can see - phpcs reads PHP, the
     * mustache lint reads structure, and neither knows which stash a value lands in.
     *
     * @return void
     */
    public function test_the_instance_comment_label_reaches_the_review_page_escaped_once(): void {
        global $DB;

        $DB->set_field('enrol', 'customtext2', self::AWKWARD_NAME, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'customint7', 1, ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $html = $this->render_review();

        $this->assertStringContainsString(self::ESCAPED_ONCE, $html);
        $this->assertStringNotContainsString(self::ESCAPED_TWICE, $html);
    }

    /**
     * The course name reaches the review page escaped exactly once.
     *
     * @return void
     */
    public function test_the_reviewed_course_name_is_escaped_once(): void {
        global $DB;

        $DB->set_field('course', 'fullname', self::AWKWARD_NAME, ['id' => $this->course->id]);

        $html = $this->render_review();

        $this->assertStringContainsString(self::ESCAPED_ONCE, $html);
        $this->assertStringNotContainsString(self::ESCAPED_TWICE, $html);
    }

    /**
     * The applicant's comment reaches the review page whole, and escaped exactly once.
     *
     * Neither stripped nor formatted: format_string() runs strip_tags(), which would delete an
     * applicant's answer from the first "<" onwards. A restore is the route by which such a
     * value reaches the column - it writes the comment verbatim out of a foreign archive.
     *
     * @return void
     */
    public function test_the_applicants_comment_is_escaped_once_and_kept_whole(): void {
        /* AWKWARD_NAME is the wrong fixture here and using it was the defect: its "<" has a
           space after it, deliberately, so strip_tags() leaves it alone and format_string()
           would render byte-identically. A "<" that opens something tag-shaped is what a
           stripping call actually eats - everything from it to the next ">" - so that is what
           the comment has to carry for this test to hold anything. */
        $comment = "First line, mentioning R&D.\nSecond line, where A <b is smaller.";

        $html = $this->render_review($comment);

        $this->assertStringContainsString('R&amp;D', $html);
        $this->assertStringContainsString('A &lt;b is smaller', $html, 'the tail was stripped');
        $this->assertStringNotContainsString('R&amp;amp;D', $html);
        // The line break the applicant typed survives, on the page built for reading them.
        $this->assertMatchesRegularExpression('~First line[^<]*<br\s*/?>~', $html, $html);
    }

    /**
     * The comment comes from the durable record, falling back to the application info row.
     *
     * They hold the same text in life, but the applicationinfo row is deleted the moment a
     * decision is taken while the record outlives it, so the order matters and a COALESCE with
     * its arms the wrong way round would look right until an application was decided.
     *
     * @return void
     */
    public function test_the_comment_prefers_the_durable_record(): void {
        $html = $this->render_review('WHAT THE RECORD SAYS');

        $this->assertStringContainsString('WHAT THE RECORD SAYS', $html);
        $this->assertStringNotContainsString('FALLBACK COMMENT', $html);
    }

    /**
     * The review page shows the applicant's email, as the queue row it replaces does.
     *
     * @return void
     */
    public function test_the_review_page_identifies_the_applicant_as_the_queue_does(): void {
        global $DB;

        $html = $this->render_review();
        $email = $DB->get_field_sql(
            "SELECT u.email
               FROM {user} u
               JOIN {user_enrolments} ue ON ue.userid = u.id
              WHERE ue.enrolid = :enrolid",
            ['enrolid' => $this->instance->id]
        );

        $this->assertStringContainsString($email, $html);
    }

    /**
     * A mentor is not shown the group names of a course they hold nothing in.
     *
     * The review page admits three levels and the chooser is only right for two of them.
     * groups_get_all_groups() applies no capability check, so without the gate this page lists
     * every group in the course to somebody whose only claim is on the applicant.
     *
     * @return void
     */
    public function test_a_mentor_is_not_shown_the_courses_groups(): void {
        global $DB, $PAGE;

        $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'Secret tutorial group',
        ]);

        $applicant = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        $mentor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'rendermentor']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $mentor->id, \context_user::instance($applicant->id)->id);
        $this->setUser($mentor);

        $url = new \moodle_url('/enrol/apply/manage.php', ['userenrol' => $ueid]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_user::instance($applicant->id));
        $html = $PAGE->get_renderer('enrol_apply')->review_form(
            \enrol_apply\local\queue::application($ueid),
            $applicant,
            $this->instance,
            $url
        );

        $this->assertStringNotContainsString('Secret tutorial group', $html, $html);
        // The control: the page rendered, and a teacher in the same course IS shown the group.
        $this->assertStringContainsString('enrol_apply_review_form', $html);
        $this->setAdminUser();
        $this->assertStringContainsString(
            'Secret tutorial group',
            $PAGE->get_renderer('enrol_apply')->review_form(
                \enrol_apply\local\queue::application($ueid),
                $applicant,
                $this->instance,
                $url
            )
        );
    }

    /**
     * An applicant who wrote nothing is said to have written nothing.
     *
     * @return void
     */
    public function test_an_empty_comment_says_so(): void {
        $html = $this->render_review('');

        $this->assertStringContainsString(get_string('nocomment', 'enrol_apply'), $html);
    }

    /**
     * A waiting-list application says so, and a pending one says something else.
     *
     * @return void
     */
    public function test_the_review_page_names_the_state(): void {
        $this->assertStringContainsString(
            get_string('outcomewaiting', 'enrol_apply'),
            $this->render_review('x', ENROL_APPLY_USER_WAIT)
        );
        $this->assertStringContainsString(
            get_string('outcomeawaiting', 'enrol_apply'),
            $this->render_review('x', ENROL_USER_SUSPENDED)
        );
    }

    /**
     * The review page carries no bulk-selection apparatus.
     *
     * There is one application on it, so a select-all checkbox and a toggle group would be
     * controls with nothing to control - and the toggle group in particular would leave
     * enrol_apply/manage disabling a submit button this page needs enabled.
     *
     * @return void
     */
    public function test_the_review_page_carries_no_toggle_apparatus(): void {
        $html = $this->render_review();

        $this->assertStringNotContainsString(\enrol_apply\table\applications::TOGGLE_GROUP, $html, $html);
        $this->assertStringNotContainsString('data-action="toggle"', $html, $html);
        // The control: the page really did render, so the absences above are absences.
        $this->assertStringContainsString('enrol_apply_review_form', $html);
    }

    /**
     * The course name reaches the new-application notification escaped exactly once.
     *
     * Mutation check: drop the 'escape' => false option from the course name in
     * enrol_apply_renderer::application_notification_mail_body() and exactly this test goes
     * red. The template's own docblock has claimed since it was written that "every label and
     * value arrives in its PLAIN spelling"; for this one value that was not true.
     *
     * @return void
     */
    public function test_the_notified_course_name_is_escaped_once(): void {
        global $PAGE;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['fullname' => self::AWKWARD_NAME]);
        $applicant = $this->getDataGenerator()->create_user();
        $PAGE->set_context(\context_course::instance($course->id));

        $body = $PAGE->get_renderer('enrol_apply')->application_notification_mail_body(
            $course,
            $applicant,
            new \moodle_url('/enrol/apply/manage.php'),
            'Please let me in'
        );

        $this->assertStringContainsString(self::ESCAPED_ONCE, $body);
        $this->assertStringNotContainsString(self::ESCAPED_TWICE, $body);
    }

    /**
     * The submitted profile details are rendered from the frozen snapshot's own label and value.
     *
     * The stored label is deliberately NOT one core would recompute for that key: an earlier
     * version of this test stored "City/town", which is byte-identical to what fields::label()
     * returns for s_city, so replacing $entry['label'] with a live lookup left it green. The
     * value is likewise not the applicant's live one, so neither assertion can be satisfied by
     * anything except the frozen record.
     *
     * @return void
     */
    public function test_the_review_page_shows_the_submitted_profile_details(): void {
        $snapshot = json_encode([
            'version' => \enrol_apply\local\submission::SNAPSHOT_VERSION,
            'fields' => [
                [
                    'key' => \enrol_apply\local\fields::standard_key('city'),
                    'label' => 'Town as it was asked for in 2019',
                    'value' => 'Ouro Preto',
                ],
            ],
        ]);

        $html = $this->render_review(
            'Please let me in',
            ENROL_USER_SUSPENDED,
            $snapshot,
            ['city' => 'Belo Horizonte']
        );

        $this->assertStringContainsString(get_string('submittedprofile', 'enrol_apply'), $html);
        // The label as STORED, which no live lookup for this key would produce.
        $this->assertStringContainsString('Town as it was asked for in 2019', $html);
        $this->assertStringNotContainsString(\enrol_apply\local\fields::label(
            \enrol_apply\local\fields::standard_key('city')
        ), $html);
        // The value as SUBMITTED, and not the one the profile holds today.
        $this->assertStringContainsString('Ouro Preto', $html);
        $this->assertStringNotContainsString('Belo Horizonte', $html);
    }

    /**
     * An application carrying no snapshot renders no panel, rather than an empty one.
     *
     * @return void
     */
    public function test_the_review_page_omits_the_panel_when_nothing_was_submitted(): void {
        $html = $this->render_review();

        $this->assertStringNotContainsString(get_string('submittedprofile', 'enrol_apply'), $html);
    }

    /**
     * The page never reads the applicant's live profile for a snapshot field.
     *
     * This is a security boundary, not a scoping choice, so it is asserted rather than left to
     * the absence of a call. The stored key is attacker-choosable - restore_enrol_apply_plugin
     * writes userinfodata verbatim out of a foreign archive - and fields::current_value()
     * dereferences whatever {user} column an "s_" key names, with no allowlist: measured on
     * m502, an earlier version of this panel rendered the applicant's password hash from an
     * envelope naming s_password. The DENY list that keeps such keys out of this plugin governs
     * the WRITE path only.
     *
     * @return void
     */
    public function test_the_panel_never_reads_the_live_profile_for_a_stored_key(): void {
        $snapshot = json_encode([
            'version' => \enrol_apply\local\submission::SNAPSHOT_VERSION,
            'fields' => [
                ['key' => 's_password', 'label' => 'Town', 'value' => 'Ouro Preto'],
                ['key' => 's_email', 'label' => 'Registration', 'value' => '2026-0042'],
            ],
        ]);

        /* The password is set explicitly: the data generator leaves it empty, and an assertion
           that an empty string is absent from the markup would hold for any code at all. */
        $html = $this->render_review(
            'Please let me in',
            ENROL_USER_SUSPENDED,
            $snapshot,
            ['password' => 'Probe1234!']
        );

        /* The stored values still render - they are this applicant's own submitted text, and the
           report renders stored label and value for any key too. What must never appear is
           anything READ from the live row those keys name. */
        $this->assertStringContainsString('Ouro Preto', $html);

        global $DB;
        $applicant = $DB->get_record_sql(
            "SELECT u.* FROM {user} u JOIN {user_enrolments} ue ON ue.userid = u.id
              WHERE ue.enrolid = :enrolid ORDER BY ue.id DESC",
            ['enrolid' => $this->instance->id],
            IGNORE_MULTIPLE
        );
        // The precondition: those columns really do hold something worth withholding.
        $this->assertNotEmpty($applicant->password);
        $this->assertNotEmpty($applicant->email);

        /* Scoped to the PANEL and not to the page. The page legitimately shows identifying
           values elsewhere - the identity line carries whatever showuseridentity names, which on
           a default site includes the e-mail address - so a whole-page assertion would fail for a
           reason that has nothing to do with the snapshot, and one that happened to pass would be
           holding the wrong thing. The wrapper class this matches is kept in review.mustache for
           exactly this. */
        $this->assertMatchesRegularExpression('/enrol_apply-snapshot/', $html);
        preg_match('#<div class="enrol_apply-snapshot.*?</div>#s', $html, $panel);
        $this->assertNotEmpty($panel, 'the snapshot panel did not render');

        $this->assertStringNotContainsString($applicant->password, $panel[0]);
        $this->assertStringNotContainsString($applicant->email, $panel[0]);
    }

    /**
     * An identity field is withheld from a reader without the identity capability.
     *
     * The same rule the Report Builder surface applies to the same stored record, judged in the
     * COURSE context. Without this the review page would be the weaker of the two doors onto it.
     * The name row is the control: it proves the panel rendered at all, so the missing city is
     * masking rather than an empty panel.
     *
     * @return void
     */
    public function test_an_identity_field_is_withheld_from_a_reader_without_the_capability(): void {
        $html = $this->render_masked_review(false);

        $this->assertStringContainsString('Ann', $html);
        $this->assertStringNotContainsString('Ouro Preto', $html);
        $this->assertStringNotContainsString('City/town', $html);
    }

    /**
     * The masking is judged in the COURSE context, not at system level.
     *
     * The pair that tells the two apart, and without it the diff's central claim is unpinned:
     * this reader holds moodle/site:viewuseridentity in the COURSE and not at system level, so a
     * masking rule that asked the system context would withhold the city from somebody entitled
     * to it. The sibling test above is the other half - there the reader holds it nowhere.
     *
     * @return void
     */
    public function test_the_masking_is_judged_in_the_course_and_not_at_system_level(): void {
        $html = $this->render_masked_review(true);

        $this->assertStringContainsString('Ann', $html);
        $this->assertStringContainsString('Ouro Preto', $html);
    }

    /**
     * Render the review page for a reader whose identity capability is set per context.
     *
     * @param bool $identityincourse Whether to grant moodle/site:viewuseridentity in the course.
     * @return string Rendered markup.
     */
    private function render_masked_review(bool $identityincourse): string {
        global $DB, $PAGE;

        $snapshot = json_encode([
            'version' => \enrol_apply\local\submission::SNAPSHOT_VERSION,
            'fields' => [
                [
                    'key' => \enrol_apply\local\fields::standard_key('firstname'),
                    'label' => 'First name',
                    'value' => 'Ann',
                ],
                [
                    'key' => \enrol_apply\local\fields::standard_key('city'),
                    'label' => 'City/town',
                    'value' => 'Ouro Preto',
                ],
            ],
        ]);

        $applicant = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
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
            'status' => \enrol_apply\local\submission::STATUS_PENDING,
            'comment' => '',
            'userinfodata' => $snapshot,
            'outcomemessage' => '',
            'decidedgroups' => '',
            'decidedrole' => 0,
            'decidedby' => 0,
            'timecreated' => time(),
            'timedecided' => 0,
        ]);

        /* A reader who may decide the application, with the identity capability set where the
           test wants it. The role is built rather than borrowed so the two capabilities move
           independently - a stock teacher holds both. */
        $reader = $this->getDataGenerator()->create_user();
        $coursecontext = \context_course::instance($this->course->id);
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applyreviewer']);
        assign_capability(
            'enrol/apply:manageapplications',
            CAP_ALLOW,
            $roleid,
            \context_system::instance()
        );
        if ($identityincourse) {
            assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $roleid, $coursecontext);
        }
        role_assign($roleid, $reader->id, $coursecontext->id);
        $this->setUser($reader);

        // The precondition: the capability really is where this test put it, and nowhere else.
        $this->assertEquals(
            $identityincourse,
            has_capability('moodle/site:viewuseridentity', $coursecontext)
        );
        $this->assertFalse(
            has_capability('moodle/site:viewuseridentity', \context_system::instance())
        );

        $url = new \moodle_url('/enrol/apply/manage.php', ['userenrol' => $ueid]);
        $PAGE->set_url($url);
        $PAGE->set_context($coursecontext);

        return $PAGE->get_renderer('enrol_apply')->review_form(
            \enrol_apply\local\queue::application($ueid),
            $applicant,
            $this->instance,
            $url
        );
    }

    /**
     * The queue tells the manager when the places are gone.
     *
     * The state worth surfacing is places exhausted while applications are still open: a
     * manager receiving applications they have nowhere to put. Places never block an approval -
     * they are an indicator, and the decision stays the manager's - so this notice is the whole
     * of how that number reaches the person who set it.
     *
     * The control renders the same queue with a place free, and is not optional: an assertion
     * that the notice IS present passes just as well against a renderer that shows it always.
     *
     * @return void
     */
    public function test_the_queue_says_when_the_places_are_gone(): void {
        global $DB, $PAGE;

        $DB->set_field('enrol', 'customint4', 1, ['id' => $this->instance->id]);
        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));
        $renderer = $PAGE->get_renderer('enrol_apply');
        $notice = get_string('placesfull', 'enrol_apply', 1);

        // The control: one place, nobody approved, so nothing to say.
        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);
        $this->assertStringNotContainsString($notice, $renderer->manage_form($table, $url, $instance));

        // Fill it.
        $taker = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $taker->id, null, 0, 0, ENROL_USER_ACTIVE);

        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);
        $this->assertStringContainsString($notice, $renderer->manage_form($table, $url, $instance));
    }

    /**
     * The notice survives an empty queue, which is the state it exists for.
     *
     * An instance whose APPLICANT limit is reached has nothing left to list, and that is exactly
     * when the manager most needs to know why. Rendering the notice inside the template's
     * hasrows section would make it disappear in precisely that case - the failure this test
     * exists to prevent, and one no assertion about a populated queue could ever see.
     *
     * @return void
     */
    public function test_the_places_notice_survives_an_empty_queue(): void {
        global $DB, $PAGE;

        $DB->set_field('enrol', 'customint4', 1, ['id' => $this->instance->id]);
        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $taker = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $taker->id, null, 0, 0, ENROL_USER_ACTIVE);

        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));

        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);
        $html = $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, $instance);

        // The precondition: there really is nothing awaiting a decision.
        $this->assertSame(0, (int) $table->totalrows);
        $this->assertStringContainsString(get_string('placesfull', 'enrol_apply', 1), $html);
    }

    /**
     * The queue says when the APPLICANT limit is reached, and names the deferred backlog.
     *
     * The other exhausted state, and until now nothing on any screen could explain it: the method
     * refuses new applications, and the rows holding it against its limit may all be deferred -
     * in which case the queue is empty, the course is closed to everybody, and no number anywhere
     * says why. A deferred row is freed by nothing, so the notice names how many there are.
     *
     * The control renders the same queue one application below the limit, and is not optional:
     * asserting only that the notice IS present passes against a renderer that shows it always.
     *
     * @return void
     */
    public function test_the_queue_says_when_applications_are_closed(): void {
        global $DB, $PAGE;

        $DB->set_field('enrol', 'customint3', 2, ['id' => $this->instance->id]);
        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));
        $renderer = $PAGE->get_renderer('enrol_apply');

        $deferred = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $deferred->id, null, 0, 0, ENROL_APPLY_USER_WAIT);

        // The control: one application against a limit of two, so there is room and nothing to say.
        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);
        $this->assertStringNotContainsString(
            get_string('applicationsclosednotice', 'enrol_apply', (object) [
                'held' => 1,
                'limit' => 2,
                'deferred' => 1,
            ]),
            $renderer->manage_form($table, $url, $instance)
        );

        $second = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $second->id, null, 0, 0, ENROL_APPLY_USER_WAIT);

        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);
        $this->assertStringContainsString(
            get_string('applicationsclosednotice', 'enrol_apply', (object) [
                'held' => 2,
                'limit' => 2,
                'deferred' => 2,
            ]),
            $renderer->manage_form($table, $url, $instance)
        );
    }

    /**
     * That notice survives an empty queue, which is the state it exists for.
     *
     * An instance whose applicant limit is held entirely by APPROVED enrolments lists nothing at
     * all - the queue shows applications awaiting a decision, and there are none. Rendering the
     * notice inside the template's hasrows section would make it vanish in exactly the case it
     * was written for, and no assertion about a populated queue could see that.
     *
     * @return void
     */
    public function test_the_closed_notice_survives_an_empty_queue(): void {
        global $DB, $PAGE;

        $DB->set_field('enrol', 'customint3', 1, ['id' => $this->instance->id]);
        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $taker = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $taker->id, null, 0, 0, ENROL_USER_ACTIVE);

        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));

        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);
        $html = $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, $instance);

        // The precondition: there really is nothing awaiting a decision.
        $this->assertSame(0, (int) $table->totalrows);
        $this->assertStringContainsString(
            get_string('applicationsclosednotice', 'enrol_apply', (object) [
                'held' => 1,
                'limit' => 1,
                'deferred' => 0,
            ]),
            $html
        );
    }
}
