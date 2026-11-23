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

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the scenarios a new method's settings form can start from.
 *
 * What each scenario puts in the built form is edit_form_test's; this file holds the class's own
 * rules: the strict key, the role "Moderator recruitment" grants and what the cards say.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(preset::class)]
final class preset_test extends \advanced_testcase {
    /** @var \stdClass A course with no groups. */
    private $course;

    /**
     * A course.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * A role's id.
     *
     * @param string $shortname The role's short name.
     * @return int The id.
     */
    private function role(string $shortname): int {
        global $DB;

        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * The role ids the instance form offers under "Role on approval" for a new method, in its order.
     *
     * @return int[] Role ids.
     */
    private function offered(): array {
        $context = \context_course::instance($this->course->id);
        $default = enrol_get_plugin('apply')->get_config('roleid');

        return array_map('intval', array_keys(get_default_enrol_roles($context, $default)));
    }

    /**
     * Requests that name no scenario: other spellings, a key with something around it, nothing.
     *
     * @return array The request, keyed by a readable case name.
     */
    public static function unknown_provider(): array {
        return [
            'capitalised' => ['Review'],
            'upper case' => ['CLASSES'],
            'plural' => ['keys'],
            'a prefix of a key' => ['moderator'],
            'empty' => [''],
            'another word' => ['defaults'],
        ];
    }

    /**
     * Each key names its scenario.
     *
     * @return void
     */
    public function test_each_key_names_its_scenario(): void {
        foreach (preset::KEYS as $key) {
            $this->assertSame($key, preset::key($key));
            $this->assertNotEmpty(preset::values($key, (int) $this->course->id, $this->offered()), $key);
        }
        $this->assertSame(['review', 'classes', 'key', 'moderators'], preset::KEYS);
    }

    /**
     * Anything but a key exactly as written reads as no scenario, and sets nothing.
     *
     * Changes that must make it fail: a key matched without regard to case, or by its start.
     *
     * @param string $requested The request.
     * @return void
     */
    #[DataProvider('unknown_provider')]
    public function test_an_unknown_key_is_no_scenario(string $requested): void {
        $this->assertSame('', preset::key($requested));
        $this->assertSame([], preset::values($requested, (int) $this->course->id, $this->offered()));

        $cards = preset::cards($requested, (int) $this->course->id, $this->offered(), 'No groups', false, true);
        $this->assertFalse($cards['haschosen']);
        $this->assertSame([], array_filter(array_column($cards['cards'], 'chosen')));
    }

    /**
     * Of the offered roles that decide, one of archetype teacher is preferred wherever it comes, and
     * by its archetype rather than its name.
     *
     * Changes that must make it fail: the archetype preference dropped (the editing teacher, first in
     * this order, would be chosen).
     *
     * @return void
     */
    public function test_moderator_recruitment_prefers_a_deciding_non_editing_teacher(): void {
        $courseid = (int) $this->course->id;
        $context = \context_course::instance($courseid);
        $moderator = create_role('Moderador', 'moderador', '', 'teacher');
        set_role_contextlevels($moderator, [CONTEXT_COURSE]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $moderator, $context->id);

        $offered = [$this->role('student'), $this->role('editingteacher'), $moderator];
        $this->assertSame($moderator, preset::moderator_role($courseid, $offered));
        $this->assertSame($moderator, preset::values(preset::MODERATORS, $courseid, $offered)['roleid']);

        // The control: a non-editing teacher that does not decide is passed over.
        $this->assertSame(
            $this->role('editingteacher'),
            preset::moderator_role($courseid, [$this->role('teacher'), $this->role('editingteacher')])
        );
    }

    /**
     * With no deciding teacher-archetype role, the first offered role that decides is chosen, in the
     * select's order.
     *
     * Changes that must make it fail: a role chosen that does not decide, or the last deciding one.
     *
     * @return void
     */
    public function test_moderator_recruitment_takes_the_first_offered_deciding_role(): void {
        $courseid = (int) $this->course->id;
        $offered = $this->offered();
        $this->assertContains($this->role('student'), $offered);
        $this->assertContains($this->role('manager'), $offered);

        $expected = null;
        foreach ($offered as $roleid) {
            if (decidingrole::decides($roleid, $courseid)) {
                $expected = $roleid;
                break;
            }
        }
        $this->assertNotNull($expected);
        $this->assertSame($expected, preset::moderator_role($courseid, $offered));
        $this->assertTrue(decidingrole::decides($expected, $courseid));

        // Order is the select's: reversed, the other deciding role comes first.
        $reversed = array_reverse($offered);
        $first = null;
        foreach ($reversed as $roleid) {
            if (decidingrole::decides($roleid, $courseid)) {
                $first = $roleid;
                break;
            }
        }
        $this->assertNotSame($expected, $first, 'two stock roles decide');
        $this->assertSame($first, preset::moderator_role($courseid, $reversed));
    }

    /**
     * When no offered role decides, the scenario leaves the role alone and its card asks for one.
     *
     * Changes that must make it fail: a role set that does not decide.
     *
     * @return void
     */
    public function test_moderator_recruitment_without_a_deciding_role_leaves_the_role_to_choose(): void {
        $courseid = (int) $this->course->id;
        $context = \context_course::instance($courseid);
        foreach (['manager', 'editingteacher'] as $shortname) {
            assign_capability('enrol/apply:manageapplications', CAP_PROHIBIT, $this->role($shortname), $context->id);
        }

        $this->assertNull(preset::moderator_role($courseid, $this->offered()));
        $values = preset::values(preset::MODERATORS, $courseid, $this->offered());
        $this->assertArrayNotHasKey('roleid', $values);
        $this->assertSame(instanceoptions::DECIDERS_ALLGROUPS, $values['deciders']);

        $card = $this->card(preset::cards('', $courseid, $this->offered(), 'No groups', false, true), preset::MODERATORS);
        $this->assertContains(
            ['label' => get_string('roleonapproval', 'enrol_apply'), 'value' => get_string('presetrolemissing', 'enrol_apply')],
            $card['sets']
        );
    }

    /**
     * The moderators card names the role in its plain spelling, an administrator's name included.
     *
     * @return void
     */
    public function test_the_moderators_card_names_the_role_plain(): void {
        global $DB;

        $courseid = (int) $this->course->id;
        $editing = $this->role('editingteacher');
        $DB->set_field('role', 'name', 'R&D moderators', ['id' => $editing]);
        $card = $this->card(preset::cards('', $courseid, [$editing], 'No groups', false, true), preset::MODERATORS);

        $this->assertContains(
            ['label' => get_string('roleonapproval', 'enrol_apply'), 'value' => 'R&D moderators'],
            $card['sets'],
            'the ampersand stays plain: the template escapes it'
        );

        // A stock name is a language string, plain as it is.
        $DB->set_field('role', 'name', '', ['id' => $editing]);
        $card = $this->card(preset::cards('', $courseid, [$editing], 'No groups', false, true), preset::MODERATORS);
        $this->assertContains(
            ['label' => get_string('roleonapproval', 'enrol_apply'), 'value' => get_string('defaultcourseteacher')],
            $card['sets']
        );
    }

    /**
     * The two scenarios built on groups state the course's group mode, and say they need separate
     * groups only where the course is not in them; the other two state nothing about it.
     *
     * Changes that must make it fail: the need stated in a separate-groups course, or never.
     *
     * @return void
     */
    public function test_the_prerequisite_follows_the_group_mode(): void {
        $courseid = (int) $this->course->id;

        $shared = preset::cards('', $courseid, $this->offered(), 'No groups', false, true);
        $separate = preset::cards('', $courseid, $this->offered(), 'Separate groups', true, true);
        foreach ([preset::CLASSES, preset::MODERATORS] as $key) {
            $card = $this->card($shared, $key);
            $this->assertTrue($card['hasprerequisite'], $key);
            $this->assertSame('No groups', $card['mode']);
            $this->assertTrue($card['needsseparate'], $key);
            $this->assertTrue($card['hassettingsurl'], $key);

            $card = $this->card($separate, $key);
            $this->assertTrue($card['hasprerequisite'], $key);
            $this->assertSame('Separate groups', $card['mode']);
            $this->assertFalse($card['needsseparate'], $key);
            $this->assertFalse($card['hassettingsurl'], 'nothing to change');
        }
        foreach ([preset::REVIEW, preset::KEY] as $key) {
            $this->assertFalse($this->card($shared, $key)['hasprerequisite'], $key);
        }

        // Without moodle/course:update the need is still said, with no link.
        $card = $this->card(preset::cards('', $courseid, $this->offered(), 'No groups', false, false), preset::CLASSES);
        $this->assertTrue($card['needsseparate']);
        $this->assertFalse($card['hassettingsurl']);
    }

    /**
     * Only the chosen card is marked and reads "Chosen", and the way back to the defaults is offered
     * only while a scenario is in force.
     *
     * @return void
     */
    public function test_only_the_chosen_card_is_marked(): void {
        $courseid = (int) $this->course->id;

        $cards = preset::cards(preset::KEY, $courseid, $this->offered(), 'No groups', false, true);
        $this->assertTrue($cards['haschosen']);
        foreach ($cards['cards'] as $card) {
            $chosen = $card['key'] === preset::KEY;
            $this->assertSame($chosen, $card['chosen'], $card['key']);
            $this->assertSame(
                get_string($chosen ? 'presetchosen' : 'presetuse', 'enrol_apply'),
                $card['buttonlabel']
            );
            $this->assertStringContainsString('preset=' . $card['key'], $card['url']);
        }
        $this->assertStringNotContainsString('preset=', $cards['clearurl']);

        // The control: with none chosen, no card is marked and no way back is offered.
        $cards = preset::cards('', $courseid, $this->offered(), 'No groups', false, true);
        $this->assertFalse($cards['haschosen']);
        $this->assertSame([], array_filter(array_column($cards['cards'], 'chosen')));
    }

    /**
     * One card of the context.
     *
     * @param array $context What preset::cards() returned.
     * @param string $key The scenario.
     * @return array Its card.
     */
    private function card(array $context, string $key): array {
        foreach ($context['cards'] as $card) {
            if ($card['key'] === $key) {
                return $card;
            }
        }
        $this->fail("no card for {$key}");
    }
}
