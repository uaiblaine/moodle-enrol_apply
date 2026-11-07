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
 * Tests for the review page's blocks (slice V3, redrawn as Visual 1).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\output;

use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/apply/renderer.php');

/**
 * Tests for the review page's blocks (slice V3, redrawn as Visual 1).
 *
 * The blocks per reader, the rules' meters and their levels, the singular and plural lines, the
 * outcome sentence with its bold parts and its empty-choice fallback, the switch from cards to a
 * search, and the spelling of every name the blocks render. Seats need local_groupdist, which
 * GitHub's CI does not install, so the levels are tested on the rules' own builder with the facts
 * it is handed, and the rendered pages are tested on what needs no seats.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_renderer::class)]
final class review_funnel_test extends \advanced_testcase {
    /** @var string A name carrying both characters an escaper rewrites; the space after "<" keeps strip_tags() off it. */
    private const AWKWARD = 'R&D < Team';

    /** @var string The awkward name escaped exactly once. */
    private const ESCAPED_ONCE = 'R&amp;D &lt; Team';

    /** @var string The awkward name escaped twice, which a reader must never get. */
    private const ESCAPED_TWICE = 'R&amp;amp;D &amp;lt; Team';

    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass The course. */
    private $course;

    /** @var \context_course Its context. */
    private $context;

    /** @var \stdClass An apply instance in the reviewed key mode, listing $listed. */
    private $instance;

    /** @var \stdClass The group whose key the applicants typed. */
    private $keyed;

    /** @var \stdClass The group the instance's own list names. */
    private $listed;

    /** @var \stdClass A group neither the key nor the instance names. */
    private $other;

    /**
     * A course with a reviewed key-only method, a keyed group, a listed group and another one.
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
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->context = \context_course::instance($this->course->id);
        $id = $this->plugin->add_instance($this->course, [
            'customchar1' => \enrol_apply\local\groupkey::MODE_REVIEW,
        ] + $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $this->keyed = $generator->create_group([
            'courseid' => $this->course->id,
            'name' => 'Turma A',
            'enrolmentkey' => 'Turma-A-2027',
        ]);
        $this->listed = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Turma B']);
        $this->other = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Turma C']);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $id, 'groupid' => $this->listed->id]);

        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php'));
    }

    /**
     * An application awaiting a decision, with what its key did written on its record.
     *
     * @param int $outcome A KEY_ outcome; KEY_NONE for an application without a key.
     * @param \stdClass|null $group The key's group; null for the fixture's keyed group.
     * @param int $status The enrolment status: suspended (pending) or the waiting list.
     * @param array $user Fields for the applicant's user record.
     * @return \stdClass The applicant, carrying the user enrolment id as ueid.
     */
    private function application(
        int $outcome,
        ?\stdClass $group = null,
        int $status = ENROL_USER_SUSPENDED,
        array $user = []
    ): \stdClass {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user($user);
        $this->plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, $status);
        $applicant->ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $applicant->id,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => $applicant->ueid,
            'comment' => '',
            'status' => $status === ENROL_APPLY_USER_WAIT ? submission::STATUS_WAITING : submission::STATUS_PENDING,
            'timecreated' => time(),
            'keygroupid' => $outcome === submission::KEY_NONE ? 0 : (int) ($group ?? $this->keyed)->id,
            'keyoutcome' => $outcome,
        ]);

        return $applicant;
    }

    /**
     * The review page's form for one application, as the current user sees it.
     *
     * @param \stdClass $applicant As application() returns it.
     * @param string $message A message carried back from the cancel confirmation.
     * @param string $note A note carried back the same way.
     * @return string The rendered form.
     */
    private function review_html(\stdClass $applicant, string $message = '', string $note = ''): string {
        global $DB, $PAGE;

        $url = new \moodle_url('/enrol/apply/manage.php', ['userenrol' => $applicant->ueid]);
        $PAGE->set_url($url);
        $PAGE->set_context($this->context);
        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        return $PAGE->get_renderer('enrol_apply')->review_form(
            \enrol_apply\local\queue::application($applicant->ueid),
            $applicant,
            $instance,
            $url,
            $message,
            $note
        );
    }

    /**
     * The text of the element carrying a data-region, or null when there is none.
     *
     * @param string $html Rendered markup.
     * @param string $region The data-region value.
     * @return string|null The element's inner markup, trimmed.
     */
    private function region(string $html, string $region): ?string {
        $pattern = '~<(p|span|section|ul|div)\b[^>]*data-region="' . preg_quote($region, '~') . '"[^>]*>(.*?)</\1>~s';

        return preg_match($pattern, $html, $m) ? trim($m[2]) : null;
    }

    /**
     * Whether one of the page's two accordions ("adjust" or "write") opens as the page loads.
     *
     * The panel's show class and its button's aria-expanded must say the same thing: the first is
     * what is drawn, the second what a screen reader is told.
     *
     * @param string $html The review page.
     * @param string $name The accordion: adjust or write.
     * @return bool True when it opens.
     */
    private function accordion_open(string $html, string $name): bool {
        $panel = '~class="accordion-collapse collapse( show)?" id="enrol_apply_' . $name . '_body"~';
        $this->assertSame(1, preg_match($panel, $html, $m), "no {$name} accordion");
        $open = ($m[1] ?? '') !== '';
        $button = '~data-bs-target="#enrol_apply_' . $name . '_body"[^>]*aria-expanded="(true|false)"~';
        $this->assertSame(1, preg_match($button, $html, $b), "no {$name} accordion button");
        $this->assertSame($open ? 'true' : 'false', $b[1], "the {$name} button and its panel disagree");

        return $open;
    }

    /**
     * Whether the card of a group is ticked.
     *
     * @param string $html The review page.
     * @param int $groupid The group.
     * @return bool|null True when ticked, false when offered unticked, null when it has no card.
     */
    private function card_ticked(string $html, int $groupid): ?bool {
        if (!preg_match('~<input[^>]*\bid="enrol_apply_group_' . $groupid . '"[^>]*>~s', $html, $m)) {
            return null;
        }

        return (bool) preg_match('~\schecked>$~', $m[0]);
    }

    /**
     * The search's own select, so an option of another select (the role's) cannot satisfy an assertion.
     *
     * @param string $html The review page.
     * @return string The select, opening tag to closing tag, or the empty string.
     */
    private function search_select(string $html): string {
        return preg_match('~<select[^>]*id="enrol_apply_addgroups".*?</select>~s', $html, $m) ? $m[0] : '';
    }

    /**
     * Call one of the renderer's protected builders.
     *
     * @param string $method The method's name.
     * @param mixed ...$args Its arguments.
     * @return mixed What it returns.
     */
    private function invoke_builder(string $method, ...$args) {
        global $PAGE;

        $renderer = $PAGE->get_renderer('enrol_apply');

        return (new \ReflectionMethod($renderer, $method))->invoke($renderer, ...$args);
    }

    /**
     * A group fact as group_facts() builds it.
     *
     * @param int $id The group id.
     * @param string $name Its plain name.
     * @param int|null $members Members, or null for a group declaring no seats.
     * @param int $seats Seats.
     * @return \stdClass The fact.
     */
    private static function fact(int $id, string $name, ?int $members, int $seats = 0): \stdClass {
        return (object) [
            'id' => $id,
            'name' => $name,
            'idnumber' => '',
            'hasseats' => $members !== null,
            'members' => (int) $members,
            'seats' => $seats,
            'full' => $members !== null && $members >= $seats,
            'percent' => $seats > 0 ? (int) min(100, round((int) $members * 100 / $seats)) : 100,
        ];
    }

    /**
     * The outcome sentence as the renderer words it: the role, the groups and the notice in bold.
     *
     * @param string $key The sentence's string: reviewoutcomenonewithnotice, onewithnotice, manywithnotice or
     *        nogroupinfowithnotice, each with its reviewoutcome prefix.
     * @param string $who The applicant's name, plain.
     * @param string $role The role's name, ESCAPED, or the empty string for no role.
     * @param string $groups The groups as the sentence lists them, ESCAPED.
     * @return string The sentence.
     */
    private static function sentence(string $key, string $who, string $role, string $groups = ''): string {
        return get_string($key, 'enrol_apply', (object) [
            'who' => s($who),
            'role' => $role !== ''
                ? get_string('reviewoutcomerole', 'enrol_apply', '<strong>' . $role . '</strong>')
                : '<strong>' . s(get_string('reviewoutcomenorole', 'enrol_apply')) . '</strong>',
            'groups' => $groups !== '' ? '<strong>' . $groups . '</strong>' : '',
            'notice' => '<strong>' . s(get_string('reviewoutcomenotice', 'enrol_apply')) . '</strong>',
        ]);
    }

    /**
     * A capacity as capacity_context() returns it for a decider.
     *
     * @param int $places The method's places, 0 for none.
     * @param int $taken How many places are taken.
     * @param int $limit The applicant limit, 0 for none.
     * @param int $held How many applications the limit holds, this one included.
     * @param int $deferred How many of them are deferred.
     * @param bool $narrowed Whether the reader is narrowed by separate groups.
     * @return array The capacity.
     */
    private static function capacity(
        int $places = 0,
        int $taken = 0,
        int $limit = 0,
        int $held = 0,
        int $deferred = 0,
        bool $narrowed = false
    ): array {
        return [
            'hascapacity' => true,
            'places' => $places,
            'placestaken' => $taken,
            'limit' => $limit,
            'held' => $held,
            'deferred' => $deferred,
            'narrowed' => $narrowed,
        ];
    }

    /**
     * The capacity of a method with no limit, as capacity_context() returns it.
     *
     * @return array The capacity.
     */
    private static function no_capacity(): array {
        return ['hascapacity' => false];
    }

    /**
     * A decider who may choose reads the left column's blocks in order, then the applicant's
     * card, and the bar keeps its order and its contract; a block with nothing to say is absent.
     *
     * Changes that must make it fail: a block moved out of order or out of its column, Cancel no
     * longer first in source order (it is the form's default submit, and only manage.php's
     * interception makes that safe), the long key outcome back in the strip (RP-D10), or the rules
     * rendered for a method with no limit.
     *
     * @return void
     */
    public function test_a_decider_who_chooses_reads_the_funnel_in_order(): void {
        $this->setAdminUser();
        $html = $this->review_html($this->application(submission::KEY_FOR_REVIEW));

        $order = [
            'class="col-lg-8 enrol_apply-reviewfunnel"',
            'enrol_apply-reviewstrip',
            'class="enrol_apply-nothing"',
            'id="enrol_apply_outcome_heading"',
            'id="enrol_apply_adjust"',
            'enrol_apply-write',
            'class="col-lg-4 enrol_apply-reviewaside"',
            'enrol_apply-applicantcard',
            'value="cancel"',
            'value="wait"',
            'value="confirm"',
        ];
        $positions = array_map(static fn(string $marker) => strpos($html, $marker), $order);
        $this->assertNotContains(false, $positions, $html);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'the funnel is out of order');
        // No limit, no history: no rules block at all, rather than an empty one (decision 6).
        $this->assertStringNotContainsString('id="enrol_apply_rules_heading"', $html);
        $this->assertStringContainsString(get_string('reviewnothingsent', 'enrol_apply'), $html);

        $chip = get_string('keychipgroup', 'enrol_apply', (object) [
            'outcome' => get_string('keyoutcomeforreviewshort', 'enrol_apply'),
            'group' => 'Turma A',
        ]);
        $this->assertStringContainsString(s($chip), $html);
        $this->assertStringNotContainsString(get_string('keyoutcomeforreview', 'enrol_apply'), $html);

        // The key's route is ticked, the other group offered, and Adjust stays folded.
        $this->assertTrue($this->card_ticked($html, (int) $this->keyed->id));
        $this->assertTrue($this->card_ticked($html, (int) $this->listed->id));
        $this->assertFalse($this->card_ticked($html, (int) $this->other->id));
        $this->assertFalse($this->accordion_open($html, 'adjust'));
    }

    /**
     * The outcome card states in a sentence who joins which groups with which role.
     *
     * Changes that must make it fail: the sentence naming groups the approval does not join, or
     * the role or the notification left out.
     *
     * @return void
     */
    public function test_the_outcome_sentence_names_the_groups_and_the_role(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->application(submission::KEY_FOR_REVIEW);
        $html = $this->review_html($applicant);

        $role = $DB->get_record('role', ['id' => (int) $this->instance->roleid], '*', MUST_EXIST);
        $expected = self::sentence(
            'reviewoutcomemanywithnotice',
            fullname($applicant),
            format_string(role_get_name($role, $this->context), true, ['context' => $this->context]),
            'Turma A, Turma B'
        );
        $this->assertSame($expected, $this->region($html, 'outcome-sentence'));
    }

    /**
     * With no group ticked the approval is not "no group": the line under the cards names the
     * default groups, which for an application a key sent for review include the key's group.
     *
     * Changes that must make it fail: the fallback read without the application (it would name
     * the method's list alone), or the line left out.
     *
     * @return void
     */
    public function test_the_empty_choice_names_the_key_group(): void {
        $this->setAdminUser();
        $html = $this->review_html($this->application(submission::KEY_FOR_REVIEW));

        $this->assertSame(
            get_string('reviewemptychoice', 'enrol_apply', 'Turma A, Turma B'),
            $this->region($html, 'outcome-emptychoice')
        );
        // The defaults the script falls back to carry the key's group too, in id order.
        $this->assertStringContainsString('data-defaultids="' . $this->keyed->id . ',' . $this->listed->id . '"', $html);
    }

    /**
     * A method with no group of its own says an empty choice joins none, and opens "Adjust",
     * because then the decider is the only one who can give the applicant a group.
     *
     * @return void
     */
    public function test_a_method_without_groups_opens_adjust_and_says_so(): void {
        global $DB;

        $DB->delete_records('enrol_apply_groups', ['enrolid' => $this->instance->id]);
        $this->setAdminUser();
        $applicant = $this->application(submission::KEY_NONE);
        $html = $this->review_html($applicant);

        $this->assertSame(s(get_string('reviewemptychoicenone', 'enrol_apply')), $this->region($html, 'outcome-emptychoice'));
        $this->assertTrue($this->accordion_open($html, 'adjust'));
        $this->assertSame(
            self::sentence('reviewoutcomenonewithnotice', fullname($applicant), 'Student'),
            $this->region($html, 'outcome-sentence')
        );
    }

    /**
     * A keyless application starts with the part of the method's groups the reader may choose
     * ticked (RP-D19), and the sentence still names the rest: confirm_enrolment() keeps the
     * method's groups a reader may not choose on top of a ticked choice.
     *
     * The second reader cannot see one of the method's two groups: the visible one is ticked, the
     * sentence names it and counts the hidden one, and the line under the cards says the hidden
     * one stays, without naming it.
     *
     * Changes that must make it fail: the defaults ticked only when all can be chosen, the kept
     * group left out of the sentence or its count, or the line under the cards missing.
     *
     * @return void
     */
    public function test_a_keyless_application_ticks_the_choosable_part_and_keeps_the_rest(): void {
        global $DB;

        $this->setAdminUser();
        $html = $this->review_html($this->application(submission::KEY_NONE));
        $this->assertTrue($this->card_ticked($html, (int) $this->listed->id));
        $this->assertFalse($this->card_ticked($html, (int) $this->keyed->id));

        $covert = $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'Covert unit',
            'visibility' => GROUPS_VISIBILITY_NONE,
        ]);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $this->instance->id, 'groupid' => $covert->id]);
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'funnelchooser']);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        assign_capability('enrol/apply:choosedecision', CAP_ALLOW, $roleid, \context_system::instance());
        $chooser = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($chooser->id, $this->course->id, 'funnelchooser');
        $this->assertFalse(has_capability('moodle/course:viewhiddengroups', $this->context, $chooser));
        $this->setUser($chooser);

        $applicant = $this->application(submission::KEY_NONE);
        $html = $this->review_html($applicant);
        $this->assertNull($this->card_ticked($html, (int) $covert->id), 'a hidden group is never a card');
        $this->assertTrue($this->card_ticked($html, (int) $this->listed->id));
        $this->assertSame(
            get_string('reviewkeptgroups', 'enrol_apply', get_string('decisionsummaryhidden', 'enrol_apply', 1)),
            $this->region($html, 'outcome-kept-line')
        );
        $this->assertSame(
            self::sentence(
                'reviewoutcomemanywithnotice',
                fullname($applicant),
                'Student',
                'Turma B, ' . get_string('decisionsummaryhidden', 'enrol_apply', 1)
            ),
            $this->region($html, 'outcome-sentence')
        );
        $this->assertStringNotContainsString('Covert', $html);
    }

    /**
     * A full key group is red, warned and never blocked (R5): "Adjust" opens on it, the line after
     * the sentence names the overbooking, and Confirm stays enabled.
     *
     * Seats need local_groupdist, so the outcome's builder is handed the facts it would read.
     *
     * Changes that must make it fail: "Adjust" staying folded on a full group.
     *
     * @return void
     */
    public function test_a_full_key_group_is_warned_and_never_blocked(): void {
        global $DB;

        $this->setAdminUser();
        $applicant = $this->application(submission::KEY_FOR_REVIEW);
        $keyed = (int) $this->keyed->id;
        $listed = (int) $this->listed->id;
        $controls = [
            'groupfacts' => [
                $keyed => self::fact($keyed, 'Turma A', 20, 20),
                $listed => self::fact($listed, 'Turma B', 7, 20),
            ],
            'chooser' => true,
            'coursedecider' => true,
            'roles' => [],
            'messagevalue' => '',
            'notevalue' => '',
        ];
        $outcome = $this->invoke_builder(
            'review_outcome_context',
            \enrol_apply\local\queue::application($applicant->ueid),
            $applicant,
            $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST),
            $this->context,
            $controls,
            ['chip' => 'Key accepted: Turma A', 'preselected' => [$keyed, $listed], 'keygroupid' => $keyed],
            self::no_capacity()
        );

        $this->assertTrue($outcome['context']['adjustopen']);
        $this->assertStringContainsString(
            get_string('reviewafterseats', 'enrol_apply', (object) ['name' => 'Turma A', 'members' => 21, 'seats' => 20]),
            $outcome['context']['outcomeafter']
        );
        // The full group is still ticked: the decision stays the decider's.
        $this->assertTrue($outcome['context']['groupcards'][0]['checked']);
        $this->assertSame('bg-danger text-white', $outcome['context']['groupcards'][0]['barclass']);

        // And the rendered bar never disables a decision.
        $this->assertDoesNotMatchRegularExpression('~<button[^>]*disabled~', $this->review_html($applicant));
    }

    /**
     * A decider who may not choose reads the outcome and no control: the sentence names what the
     * approval gives, and says it is set by the method.
     *
     * @return void
     */
    public function test_a_decider_without_choosedecision_reads_the_outcome_only(): void {
        global $DB;

        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $editing = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('enrol/apply:choosedecision', CAP_PROHIBIT, $editing, $this->context->id, true);
        $this->setUser($teacher);

        $html = $this->review_html($this->application(submission::KEY_FOR_REVIEW));

        $this->assertStringNotContainsString('name="groups[]"', $html);
        $this->assertStringNotContainsString('id="enrol_apply_adjust"', $html);
        $this->assertStringContainsString('Turma A, Turma B', (string) $this->region($html, 'outcome-sentence'));
        $this->assertStringContainsString(get_string('decisionsummary_help', 'enrol_apply'), $html);
        // They still decide, and Confirm still says what it does.
        $this->assertStringContainsString('value="confirm"', $html);
    }

    /**
     * A viewer reads the strip, what was sent and the applicant's card: a read-only chip, the key
     * group's name (V-D4), and no meter, outcome or bar (RP-D12).
     *
     * The control is the administrator on the same application, who gets the places and the
     * applicant-limit meters, so their absence for the viewer is the gate and not an empty fixture.
     *
     * Changes that must make it fail: the key group withheld from a viewer, or capacity shown to
     * them.
     *
     * @return void
     */
    public function test_a_viewer_reads_without_capacity_or_a_decision(): void {
        global $DB;

        $DB->set_field('enrol', 'customint4', 20, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'customint3', 30, ['id' => $this->instance->id]);
        $applicant = $this->application(submission::KEY_FOR_REVIEW);
        $places = get_string('reviewmeterplaces', 'enrol_apply');
        $limit = get_string('reviewmeterapplicants', 'enrol_apply');

        $this->setAdminUser();
        $control = $this->review_html($applicant);
        $this->assertStringContainsString($places, $control);
        $this->assertStringContainsString($limit, $control);
        $this->assertNotNull($this->region($control, 'meter-after'));

        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'funnelviewer']);
        assign_capability('enrol/apply:viewapplications', CAP_ALLOW, $roleid, \context_system::instance());
        $viewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($viewer->id, $this->course->id, 'funnelviewer');
        $this->setUser($viewer);

        $html = $this->review_html($applicant);

        $this->assertStringContainsString(get_string('reviewreadonly', 'enrol_apply'), $html);
        $this->assertStringContainsString(get_string('reviewviewonly', 'enrol_apply'), $html);
        $this->assertStringContainsString(s(get_string('keychipgroup', 'enrol_apply', (object) [
            'outcome' => get_string('keyoutcomeforreviewshort', 'enrol_apply'),
            'group' => 'Turma A',
        ])), $html);
        $this->assertStringNotContainsString($places, $html);
        $this->assertStringNotContainsString($limit, $html);
        $this->assertNull($this->region($html, 'meter-after'));
        $this->assertStringNotContainsString('id="enrol_apply_rules_heading"', $html);
        $this->assertNull($this->region($html, 'enrol_apply-outcome'));
        $this->assertStringNotContainsString('name="formaction"', $html);
        // What they may read is there: the applicant's card and the line saying nothing was sent.
        $this->assertStringContainsString(s(fullname($applicant)), $html);
        $this->assertStringContainsString(get_string('reviewapplicant', 'enrol_apply'), $html);
        $this->assertStringContainsString(get_string('reviewnothingsent', 'enrol_apply'), $html);
    }

    /**
     * The viewer is told the key group's name under core's visibility rule, like everybody else.
     *
     * Changes that must make it fail: the name given to a viewer without the visibility check.
     *
     * @return void
     */
    public function test_a_viewer_is_not_told_a_hidden_key_group(): void {
        $covert = $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'Covert unit',
            'enrolmentkey' => 'Covert-2027',
            'visibility' => GROUPS_VISIBILITY_NONE,
        ]);
        $applicant = $this->application(submission::KEY_FOR_REVIEW, $covert);

        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'funnelhiddenviewer']);
        assign_capability('enrol/apply:viewapplications', CAP_ALLOW, $roleid, \context_system::instance());
        $viewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($viewer->id, $this->course->id, 'funnelhiddenviewer');
        $this->assertFalse(has_capability('moodle/course:viewhiddengroups', $this->context, $viewer));
        $this->setUser($viewer);

        $html = $this->review_html($applicant);
        $this->assertStringContainsString(get_string('keyoutcomeforreviewshort', 'enrol_apply'), $html);
        $this->assertStringNotContainsString('Covert', $html);

        // The control: a reader who may see hidden groups reads the name.
        $this->setAdminUser();
        $this->assertStringContainsString('Covert unit', $this->review_html($applicant));
    }

    /**
     * A moderator narrowed by separate groups is offered their own groups only, and the rules
     * count the deferred applications of their groups, labelled so (V-D3).
     *
     * The application's key route holds the listed group, which is not theirs: it gets no card,
     * and the sentence names it beside their ticked group, because confirm_enrolment() keeps the
     * method's groups outside a decider's choice; the line under the cards says so.
     *
     * Changes that must make it fail: the deferred count read method-wide for a narrowed reader,
     * another group offered to them, the kept group left out of the sentence, or the singular
     * worded as the plural.
     *
     * @return void
     */
    public function test_a_narrowed_moderator_is_offered_their_groups_and_counts_their_deferrals(): void {
        global $DB;

        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $this->course->id]);
        // The deferred count is said where an applicant limit makes a deferral hold room.
        $DB->set_field('enrol', 'customint3', 10, ['id' => $this->instance->id]);
        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $system = \context_system::instance()->id;
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $teacher, $system, true);
        assign_capability('enrol/apply:choosedecision', CAP_ALLOW, $teacher, $system, true);
        $moderator = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->getDataGenerator()->create_group_member(['groupid' => $this->keyed->id, 'userid' => $moderator->id]);

        // One deferral in their group, one in another.
        $this->application(submission::KEY_FOR_REVIEW, $this->keyed, ENROL_APPLY_USER_WAIT);
        $this->application(submission::KEY_FOR_REVIEW, $this->other, ENROL_APPLY_USER_WAIT);
        $applicant = $this->application(submission::KEY_FOR_REVIEW);

        $this->setUser($moderator);
        $html = $this->review_html($applicant);

        $this->assertTrue($this->card_ticked($html, (int) $this->keyed->id));
        $this->assertNull($this->card_ticked($html, (int) $this->listed->id));
        $this->assertNull($this->card_ticked($html, (int) $this->other->id));
        $this->assertStringContainsString(
            get_string('reviewrulesdeferredyoursone', 'enrol_apply', (object) ['count' => 1, 'limit' => 10]),
            (string) $this->region($html, 'rules-deferred')
        );
        // The method's listed group is not theirs to choose, and the approval keeps it.
        $this->assertSame(
            self::sentence('reviewoutcomemanywithnotice', fullname($applicant), 'Student', 'Turma A, Turma B'),
            $this->region($html, 'outcome-sentence')
        );
        $this->assertSame(get_string('reviewkeptgroups', 'enrol_apply', 'Turma B'), $this->region($html, 'outcome-kept-line'));

        // The control: the editing teacher, not narrowed, counts both on the method.
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $this->assertStringContainsString(
            get_string('reviewrulesdeferred', 'enrol_apply', (object) ['count' => 2, 'limit' => 10]),
            (string) $this->region($this->review_html($applicant), 'rules-deferred')
        );
    }

    /**
     * A mentor, who holds nothing in the course, is told the role and no group, no seat and no
     * place, and keeps the bar.
     *
     * @return void
     */
    public function test_a_mentor_reads_the_role_only(): void {
        global $DB;

        $DB->set_field('enrol', 'customint4', 20, ['id' => $this->instance->id]);
        $applicant = $this->application(submission::KEY_FOR_REVIEW);
        $mentor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'funnelmentor']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $mentor->id, \context_user::instance($applicant->id)->id);
        $this->setUser($mentor);

        $html = $this->review_html($applicant);

        $this->assertSame(
            self::sentence('reviewoutcomenogroupinfowithnotice', fullname($applicant), 'Student'),
            $this->region($html, 'outcome-sentence')
        );
        $this->assertStringNotContainsString('Turma', $html);
        $this->assertStringNotContainsString('of 20', $html);
        $this->assertStringNotContainsString('id="enrol_apply_rules_heading"', $html);
        $this->assertStringContainsString('value="confirm"', $html);
    }

    /**
     * The meters are coloured by the occupancy AFTER confirming: primary up to 60 %, warning above
     * it, danger above 90 %; a group with no seats has no meter, and the key group comes first.
     *
     * Each fact is one seat short of a boundary or on it, so either threshold moved by one changes
     * a level: 59 + 1 = 60 % is primary, 60 + 1 = 61 % warning, 89 + 1 = 90 % warning, 90 + 1 =
     * 91 % danger, 100 + 1 over the limit and danger.
     *
     * Changes that must make it fail: either threshold moved or made inclusive, the level read
     * before confirming, the key group not first, or a meter for a group without seats.
     *
     * @return void
     */
    public function test_the_meters_are_levelled_by_the_occupancy_after_confirming(): void {
        $facts = [
            1 => self::fact(1, 'Sixty', 59, 100),
            2 => self::fact(2, 'Sixty-one', 60, 100),
            3 => self::fact(3, 'Ninety', 89, 100),
            4 => self::fact(4, 'Ninety-one', 90, 100),
            5 => self::fact(5, 'Unlimited', null),
            6 => self::fact(6, 'Full', 100, 100),
        ];

        $context = $this->invoke_builder('review_rules_context', $facts, [1, 2, 3, 4, 5, 6], 6, self::no_capacity(), []);

        $this->assertTrue($context['hasrules']);
        $levels = [];
        foreach ($context['meters'] as $meter) {
            $levels[$meter['label']] = $meter['level'];
            $this->assertTrue($meter['isgroup']);
        }
        $this->assertSame(
            [get_string('reviewmetergroupkey', 'enrol_apply', 'Full'), 'Sixty', 'Sixty-one', 'Ninety', 'Ninety-one'],
            array_keys($levels),
            'the key group comes first, and a group without seats has no meter'
        );
        $this->assertSame(\enrol_apply_renderer::METER_PRIMARY, $levels['Sixty']);
        $this->assertSame(\enrol_apply_renderer::METER_WARNING, $levels['Sixty-one']);
        $this->assertSame(\enrol_apply_renderer::METER_WARNING, $levels['Ninety']);
        $this->assertSame(\enrol_apply_renderer::METER_DANGER, $levels['Ninety-one']);
        $this->assertSame(\enrol_apply_renderer::METER_DANGER, $levels[get_string('reviewmetergroupkey', 'enrol_apply', 'Full')]);

        // The bar: the taken seats striped, the applicant's own solid, and the level's class pair.
        $sixty = $context['meters'][1];
        $this->assertSame(59, $sixty['takenpercent']);
        $this->assertSame(1, $sixty['ownpercent']);
        $this->assertSame('bg-primary text-white', $sixty['barclass']);
        $this->assertSame(get_string('reviewofmany', 'enrol_apply', (object) ['taken' => 59, 'total' => 100]), $sixty['count']);
        $full = $context['meters'][0];
        $this->assertSame(100, $full['takenpercent']);
        $this->assertSame(0, $full['ownpercent'], 'an overbooked bar never overflows');
        $this->assertSame('bg-danger text-white', $full['barclass']);
        $this->assertSame('bg-warning text-dark', $context['meters'][2]['barclass']);
    }

    /**
     * The places meter follows the same levels, and the applicant limit counts this application
     * already: its heading shows it, and confirming adds nothing to it.
     *
     * Changes that must make it fail: the applicant limit read as one more after confirming, the
     * places and the limit swapped, or a method without either given a meter.
     *
     * @return void
     */
    public function test_the_places_and_the_applicant_limit_have_meters_of_their_own(): void {
        $context = $this->invoke_builder(
            'review_rules_context',
            [],
            [],
            0,
            self::capacity(10, 5, 20, 19),
            []
        );

        $this->assertCount(2, $context['meters']);
        [$places, $limit] = $context['meters'];
        $this->assertSame(get_string('reviewmeterplaces', 'enrol_apply'), $places['label']);
        $this->assertTrue($places['isplaces']);
        $this->assertSame(\enrol_apply_renderer::METER_PRIMARY, $places['level'], '6 of 10 is 60 %');
        $this->assertSame(
            get_string('reviewmeterafter', 'enrol_apply', (object) ['after' => 6, 'total' => 10, 'left' => 4]),
            $places['after']
        );

        $this->assertSame(get_string('reviewmeterapplicants', 'enrol_apply'), $limit['label']);
        $this->assertTrue($limit['isapplicants']);
        $this->assertSame(get_string('reviewofmany', 'enrol_apply', (object) ['taken' => 19, 'total' => 20]), $limit['count']);
        $this->assertSame(\enrol_apply_renderer::METER_DANGER, $limit['level'], '19 of 20 is 95 %');
        $this->assertSame(
            get_string('reviewmeterafterone', 'enrol_apply', (object) ['after' => 19, 'total' => 20, 'left' => 1]),
            $limit['after']
        );

        $none = $this->invoke_builder('review_rules_context', [], [], 0, self::capacity(), []);
        $this->assertSame([], $none['meters']);
        $this->assertFalse($none['hasrules'], 'nothing to observe gives no block at all');
    }

    /**
     * Every count line has its singular: one seat left, none left, over the limit, many left; one
     * deferral or many, in the reader's groups or the method's; one earlier application or many.
     *
     * Changes that must make it fail: a count of one worded as a plural ("1 restantes"), the full
     * or the over case worded as seats left, or a deferred line shown without an applicant limit.
     *
     * @return void
     */
    public function test_every_count_line_has_its_singular(): void {
        $after = function (int $members, int $seats): string {
            $context = $this->invoke_builder(
                'review_rules_context',
                [1 => self::fact(1, 'Turma', $members, $seats)],
                [1],
                0,
                self::no_capacity(),
                []
            );
            return $context['meters'][0]['after'];
        };
        $a = static fn(int $after, int $total) => (object) ['after' => $after, 'total' => $total, 'left' => $total - $after];
        $this->assertSame(get_string('reviewmeterafter', 'enrol_apply', $a(8, 10)), $after(7, 10));
        $this->assertSame(get_string('reviewmeterafterone', 'enrol_apply', $a(9, 10)), $after(8, 10));
        $this->assertSame(get_string('reviewmeterafterfull', 'enrol_apply', $a(10, 10)), $after(9, 10));
        $this->assertSame(get_string('reviewmeterafterover', 'enrol_apply', $a(11, 10)), $after(10, 10));

        $deferred = fn(int $count, bool $narrowed, int $limit = 60) => $this->invoke_builder(
            'review_rules_context',
            [],
            [],
            0,
            self::capacity(0, 0, $limit, 30, $count, $narrowed),
            []
        )['deferredline'];
        $d = static fn(int $count) => (object) ['count' => $count, 'limit' => 60];
        $this->assertSame(get_string('reviewrulesdeferredone', 'enrol_apply', $d(1)), $deferred(1, false));
        $this->assertSame(get_string('reviewrulesdeferred', 'enrol_apply', $d(2)), $deferred(2, false));
        $this->assertSame(get_string('reviewrulesdeferredyoursone', 'enrol_apply', $d(1)), $deferred(1, true));
        $this->assertSame(get_string('reviewrulesdeferredyours', 'enrol_apply', $d(3)), $deferred(3, true));
        $this->assertSame('', $deferred(0, false), 'no deferral, no line');
        $this->assertSame('', $deferred(2, false, 0), 'no applicant limit, no line');

        $history = function (int $count): string {
            $rows = array_fill(0, $count, ['outcome' => 'Cancelled', 'applieddate' => '04/06/26, 11:02']);
            return $this->invoke_builder(
                'review_rules_context',
                [],
                [],
                0,
                self::no_capacity(),
                ['hashistory' => true, 'history' => $rows]
            )['historyline'];
        };
        $h = static fn(int $count) => (object) ['count' => $count, 'outcome' => 'Cancelled', 'applied' => '04/06/26, 11:02'];
        $this->assertSame(get_string('reviewhistoryone', 'enrol_apply', $h(1)), $history(1));
        $this->assertSame(get_string('reviewhistorymany', 'enrol_apply', $h(2)), $history(2));
    }

    /**
     * The group cards keep the queue meter's rule: OK below four fifths, attention from four
     * fifths, over the limit at the limit (RP-D3).
     *
     * Changes that must make it fail: either threshold moved by one.
     *
     * @return void
     */
    public function test_the_group_cards_classify_their_seats_by_the_queue_rule(): void {
        $bar = fn(int $members) => $this->invoke_builder(
            'group_card',
            self::fact(1, 'Turma', $members, 100),
            false,
            false,
            false
        )['barclass'];

        $this->assertSame('bg-success text-white', $bar(79));
        $this->assertSame('bg-warning text-dark', $bar(80));
        $this->assertSame('bg-warning text-dark', $bar(99));
        $this->assertSame('bg-danger text-white', $bar(100));
    }

    /**
     * The outcome sentence has one string per count of groups, and a hidden group counts.
     *
     * @return void
     */
    public function test_the_outcome_sentence_counts_its_groups(): void {
        $sentence = fn(array $names, int $hidden) => $this->invoke_builder(
            'outcome_sentence',
            'Ana',
            'as Student',
            $names,
            $hidden
        );
        $notice = '<strong>' . s(get_string('reviewoutcomenotice', 'enrol_apply')) . '</strong>';
        $a = static fn(string $groups) => (object) [
            'who' => 'Ana',
            'role' => 'as Student',
            'groups' => $groups === '' ? '' : '<strong>' . $groups . '</strong>',
            'notice' => $notice,
        ];

        $this->assertSame(get_string('reviewoutcomenonewithnotice', 'enrol_apply', $a('')), $sentence([], 0));
        $this->assertSame(get_string('reviewoutcomeonewithnotice', 'enrol_apply', $a('Turma A')), $sentence(['Turma A'], 0));
        $this->assertSame(
            get_string('reviewoutcomemanywithnotice', 'enrol_apply', $a('Turma A, Turma B')),
            $sentence(['Turma A', 'Turma B'], 0)
        );
        $this->assertSame(
            get_string('reviewoutcomeonewithnotice', 'enrol_apply', $a(get_string('decisionsummaryhidden', 'enrol_apply', 1))),
            $sentence([], 1)
        );
    }

    /**
     * Up to eight choosable groups every group is a card; above eight only the approval's own
     * groups are pinned cards, ticked, and the rest are a search over a plain select named
     * groups[] that core's autocomplete enhances (RP-D18, RP-D19, RP-D20).
     *
     * Changes that must make it fail: the threshold moved by one, or a pinned group missing.
     *
     * @return void
     */
    public function test_the_cards_become_a_search_above_eight_groups(): void {
        global $PAGE;

        // Three groups exist; five more make eight.
        $made = [];
        for ($i = 4; $i <= 8; $i++) {
            $made[] = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Turma ' . (100 + $i)]);
        }
        $this->setAdminUser();
        $applicant = $this->application(submission::KEY_FOR_REVIEW);

        $html = $this->review_html($applicant);
        $this->assertStringNotContainsString('id="enrol_apply_addgroups"', $html);
        $this->assertFalse($this->card_ticked($html, (int) $made[0]->id), 'every group is a card at eight');
        $amd = new \ReflectionProperty($PAGE->requires, 'amdjscode');
        $this->assertStringNotContainsString('#enrol_apply_addgroups', implode("\n", $amd->getValue($PAGE->requires)));

        $ninth = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Turma 109']);
        $html = $this->review_html($applicant);

        $this->assertMatchesRegularExpression('~<select[^>]*id="enrol_apply_addgroups"[^>]*name="groups\[\]"[^>]*multiple~', $html);
        // The route is pinned, ticked, with its badges; nothing else is a card.
        $this->assertTrue($this->card_ticked($html, (int) $this->keyed->id));
        $this->assertTrue($this->card_ticked($html, (int) $this->listed->id));
        $this->assertNull($this->card_ticked($html, (int) $this->other->id));
        $this->assertNull($this->card_ticked($html, (int) $ninth->id));
        $search = $this->search_select($html);
        $this->assertStringContainsString('<option value="' . $ninth->id . '" ', $search);
        $this->assertStringContainsString('<option value="' . $this->other->id . '" ', $search);
        $this->assertStringNotContainsString('<option value="' . $this->keyed->id . '" ', $search);
        $this->assertStringContainsString(get_string('reviewgroupkey', 'enrol_apply'), $html);
        $this->assertStringContainsString(get_string('reviewgroupmethod', 'enrol_apply'), $html);
        $this->assertStringContainsString('#enrol_apply_addgroups', implode("\n", $amd->getValue($PAGE->requires)));
    }

    /**
     * The groups read in natural order ("Turma 2" before "Turma 10"), and each option carries
     * its code and its seats in words, a full group still selectable (RP-D21, RP-D22).
     *
     * Changes that must make it fail: the database's order kept, the idnumber left out of the
     * option, or the full wording given to a group with seats left.
     *
     * @return void
     */
    public function test_the_options_read_in_natural_order_with_their_code_and_seats(): void {
        $generator = $this->getDataGenerator();
        $ten = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Turma 10']);
        $two = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Turma 2', 'idnumber' => 'NOT-2']);
        for ($i = 11; $i <= 17; $i++) {
            $generator->create_group(['courseid' => $this->course->id, 'name' => 'Turma ' . $i]);
        }
        $this->setAdminUser();

        $search = $this->search_select($this->review_html($this->application(submission::KEY_FOR_REVIEW)));
        $this->assertNotFalse(strpos($search, '<option value="' . $two->id . '" '), $search);
        $this->assertLessThan(
            strpos($search, '<option value="' . $ten->id . '" '),
            strpos($search, '<option value="' . $two->id . '" '),
            'Turma 2 must come before Turma 10'
        );
        $this->assertStringContainsString(
            s(get_string('reviewgroupidnumber', 'enrol_apply', (object) ['name' => 'Turma 2', 'idnumber' => 'NOT-2'])),
            $search
        );

        $option = fn(\stdClass $fact) => $this->invoke_builder('group_option', $fact, [3, 11])['text'];
        $this->assertSame(
            get_string('reviewgroupoption', 'enrol_apply', (object) ['name' => 'Turma 8', 'members' => 15, 'seats' => 20]),
            $option(self::fact(8, 'Turma 8', 15, 20))
        );
        $this->assertSame(
            get_string('reviewgroupoptionfull', 'enrol_apply', (object) ['name' => 'Turma 81', 'members' => 20, 'seats' => 20]),
            $option(self::fact(81, 'Turma 81', 20, 20))
        );
        $this->assertSame('Turma 9', $option(self::fact(9, 'Turma 9', null)));
        $this->assertSame(',3,11,', $this->invoke_builder('group_option', self::fact(9, 'Turma 9', null), [3, 11])['groupings']);
    }

    /**
     * The message accordion opens whenever either box holds a value, which is the way back from
     * the cancel confirmation, and stays folded otherwise (RP-D7).
     *
     * @return void
     */
    public function test_the_message_disclosure_opens_when_a_box_holds_a_value(): void {
        $this->setAdminUser();
        $applicant = $this->application(submission::KEY_NONE);

        $this->assertFalse($this->accordion_open($this->review_html($applicant), 'write'));
        $this->assertTrue($this->accordion_open($this->review_html($applicant, '', 'Checking with the registry.'), 'write'));
        $this->assertTrue($this->accordion_open($this->review_html($applicant, 'Please resend your transcript.'), 'write'));
    }

    /**
     * Every name the page renders reaches the reader escaped exactly once: a group in the
     * sentence, the line after it, the empty-choice line, the cards and a rules meter; a role in
     * the sentence and the chooser; the applicant's name in the sentence and on the card. The bold
     * parts of the sentence wrap the escaped names, and nothing inside them is escaped again.
     *
     * Changes that must make it fail: a name handed to a triple-stashed sentence unescaped, a bold
     * part escaped a second time (or its element escaped with it), or a name escaped before a
     * double stash.
     *
     * @return void
     */
    public function test_every_name_the_funnel_renders_is_escaped_once(): void {
        global $DB;

        $DB->set_field('groups', 'name', self::AWKWARD, ['id' => $this->keyed->id]);
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'funnelawkward', 'name' => self::AWKWARD]);
        set_role_contextlevels($roleid, [CONTEXT_COURSE]);
        $DB->set_field('enrol', 'roleid', $roleid, ['id' => $this->instance->id]);
        \cache_helper::invalidate_by_definition('core', 'groupdata', [], [$this->course->id]);
        $this->setAdminUser();

        $html = $this->review_html($this->application(submission::KEY_FOR_REVIEW, null, ENROL_USER_SUSPENDED, [
            'firstname' => 'Ana & Bia',
        ]));

        foreach (['outcome-sentence', 'outcome-emptychoice'] as $region) {
            $text = (string) $this->region($html, $region);
            $this->assertStringContainsString(self::ESCAPED_ONCE, $text, $region);
            $this->assertStringNotContainsString(self::AWKWARD, $text, $region);
        }
        $sentence = (string) $this->region($html, 'outcome-sentence');
        $this->assertStringContainsString('Ana &amp; Bia', $sentence);
        // The bold parts: the role, the groups and the notice, each a real element.
        $this->assertSame(3, substr_count($sentence, '<strong>'), $sentence);
        $this->assertStringContainsString(
            get_string('reviewoutcomerole', 'enrol_apply', '<strong>' . self::ESCAPED_ONCE . '</strong>'),
            $sentence
        );
        $this->assertStringContainsString('<strong>' . self::ESCAPED_ONCE . ', Turma B</strong>', $sentence);
        $this->assertStringNotContainsString('&lt;strong&gt;', $html);
        // The card names the applicant through a double stash.
        $this->assertStringContainsString('id="enrol_apply_applicant_name">Ana &amp; Bia', $html);
        $this->assertStringContainsString(
            '>' . self::ESCAPED_ONCE . ' <span class="badge',
            $html,
            'the card label double stashes the plain name'
        );
        $this->assertStringContainsString('data-name="' . self::ESCAPED_ONCE . '"', $html);
        $this->assertStringNotContainsString(self::ESCAPED_TWICE, $html);
        $this->assertStringNotContainsString('Ana &amp;amp; Bia', $html);

        // A rules meter, rendered from the facts its builder is handed (seats need local_groupdist).
        $rules = $this->invoke_builder(
            'review_rules_context',
            [(int) $this->keyed->id => self::fact((int) $this->keyed->id, self::AWKWARD, 3, 20)],
            [(int) $this->keyed->id],
            (int) $this->keyed->id,
            self::no_capacity(),
            []
        );
        $meter = $GLOBALS['PAGE']->get_renderer('enrol_apply')->render_from_template('enrol_apply/review_rules', $rules);
        $this->assertStringContainsString(s(get_string('reviewmetergroupkey', 'enrol_apply', self::AWKWARD)), $meter);
        $this->assertStringNotContainsString(self::ESCAPED_TWICE, $meter);
        $this->assertStringNotContainsString(self::AWKWARD, $meter);
    }
}
