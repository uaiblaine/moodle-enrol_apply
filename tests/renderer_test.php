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
require_once($CFG->dirroot . '/enrol/apply/manage_table.php');

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

        $table = new \enrol_apply_manage_table($this->instance->id);
        $table->define_baseurl($url);

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

        $table = new \enrol_apply_manage_table($this->instance->id);
        $table->define_baseurl($url);

        return $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, $this->instance);
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

        $formopen = strpos($html, '<form ');
        $formclose = strpos($html, '</form>');
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
     * @return string Rendered markup.
     */
    private function render_review(string $comment = 'Please let me in', int $status = ENROL_USER_SUSPENDED): string {
        global $DB, $PAGE;

        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, $status);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
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
            'userinfodata' => '',
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

        $this->assertStringNotContainsString(\enrol_apply_manage_table::TOGGLE_GROUP, $html, $html);
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
}
