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
 * Tests for the group enrolment key resolver and the instance defaults it reads.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(groupkey::class)]
#[CoversClass(\enrol_apply_plugin::class)]
final class groupkey_test extends \advanced_testcase {
    /** @var \stdClass Course the instance belongs to. */
    private $course;

    /** @var \stdClass An apply instance that accepts group keys. */
    private $instance;

    /**
     * A course with one apply instance accepting group keys.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $id = $plugin->add_instance($this->course, ['customchar1' => groupkey::MODE_ANY] + $plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * A group in the course, with the given key.
     *
     * @param string|null $key Enrolment key, or null for none.
     * @param int|null $courseid Course, the instance's by default.
     * @return int Group id.
     */
    private function group(?string $key, ?int $courseid = null): int {
        return (int) $this->getDataGenerator()->create_group([
            'courseid' => $courseid ?? $this->course->id,
            'enrolmentkey' => $key,
        ])->id;
    }

    /**
     * Only the one known value turns the option on.
     *
     * @return array Stored customchar1 value and the expected answer.
     */
    public static function mode_provider(): array {
        return [
            'any' => ['any', true],
            'off' => ['', false],
            'null' => [null, false],
            'other case' => ['ANY', false],
            'a mode this build does not know' => ['listed', false],
        ];
    }

    /**
     * enabled() is strict, because customchar1 arrives verbatim from a restored archive.
     *
     * @param string|null $stored The stored value.
     * @param bool $expected Whether the instance accepts keys.
     * @return void
     */
    #[DataProvider('mode_provider')]
    public function test_only_the_known_mode_enables_keys(?string $stored, bool $expected): void {
        $this->assertSame($expected, groupkey::enabled((object) ['customchar1' => $stored]));
    }

    /**
     * The right key names its group; a wrong one names nothing.
     *
     * @return void
     */
    public function test_a_key_names_its_group(): void {
        $this->group('Other-1');
        $target = $this->group('Turma-B-2027');

        $this->assertSame($target, groupkey::resolve($this->instance, 'Turma-B-2027'));
        $this->assertNull(groupkey::resolve($this->instance, 'Turma-B-2028'));
    }

    /**
     * An empty key never matches, even against groups carrying no key at all.
     *
     * Changes that must make it fail: dropping the `$key === ''` refusal, after which
     * hash_equals('', '') pairs it with the keyless group.
     *
     * @return void
     */
    public function test_an_empty_key_matches_no_keyless_group(): void {
        $this->group('');
        $this->group(null);

        $this->assertNull(groupkey::resolve($this->instance, ''));
    }

    /**
     * An instance that does not accept keys resolves nothing, whatever is typed.
     *
     * @return void
     */
    public function test_an_instance_without_the_option_resolves_nothing(): void {
        $this->group('Turma-B-2027');
        $off = clone $this->instance;
        $off->customchar1 = groupkey::MODE_OFF;

        $this->assertNull(groupkey::resolve($off, 'Turma-B-2027'));
    }

    /**
     * Only groups of the instance's own course are searched.
     *
     * @return void
     */
    public function test_a_key_of_another_course_does_not_match(): void {
        $other = $this->getDataGenerator()->create_course();
        $this->group('Elsewhere-1', (int) $other->id);

        $this->assertNull(groupkey::resolve($this->instance, 'Elsewhere-1'));
    }

    /**
     * The comparison is exact: no trimming, and case matters.
     *
     * Changes that must make it fail: trimming either side, or a case-insensitive compare.
     *
     * @return void
     */
    public function test_the_comparison_is_exact(): void {
        $this->group('Turma-B');
        $this->group(' padded ');

        $this->assertNull(groupkey::resolve($this->instance, 'Turma-B '));
        $this->assertNull(groupkey::resolve($this->instance, 'turma-b'));
        $this->assertNull(groupkey::resolve($this->instance, 'padded'));
    }

    /**
     * Two groups sharing a key: the lowest id admits, as enrol_self picks.
     *
     * Core refuses the duplicate only in the group form; the CSV import and the web service let
     * it through, so this is a state a course can be in.
     *
     * @return void
     */
    public function test_a_shared_key_resolves_to_the_lowest_id(): void {
        $first = $this->group('Shared-1');
        $this->group('Shared-1');

        $this->assertSame($first, groupkey::resolve($this->instance, 'Shared-1'));
    }

    /**
     * The summary lists keyed groups only, flags a shadowed and an untypable key, and carries
     * no key.
     *
     * @return void
     */
    public function test_the_summary_describes_the_keyed_groups_without_their_keys(): void {
        $this->group(null);
        $this->group('');
        $first = $this->group('Shared-1');
        $second = $this->group('Shared-1');
        $padded = $this->group('trailing ');

        $summary = groupkey::summary((int) $this->course->id);

        $this->assertSame([$first, $second, $padded], array_keys($summary));
        $this->assertNull($summary[$first]->shadowedby);
        $this->assertSame($first, $summary[$second]->shadowedby);
        $this->assertFalse($summary[$first]->untypable);
        $this->assertTrue($summary[$padded]->untypable);
        foreach ($summary as $group) {
            $this->assertSame(['id', 'name', 'shadowedby', 'untypable'], array_keys((array) $group));
        }
    }

    /**
     * A new instance takes the site default, and a default this build does not know reads as off.
     *
     * @return void
     */
    public function test_a_new_instance_takes_the_site_default(): void {
        // A fresh plugin object per reading: enrol_plugin::get_config() memoises the config.
        set_config('groupkeys', groupkey::MODE_ANY, 'enrol_apply');
        $this->assertSame(groupkey::MODE_ANY, (new \enrol_apply_plugin())->get_instance_defaults()['customchar1']);

        set_config('groupkeys', 'listed', 'enrol_apply');
        $this->assertSame(groupkey::MODE_OFF, (new \enrol_apply_plugin())->get_instance_defaults()['customchar1']);

        unset_config('groupkeys', 'enrol_apply');
        $this->assertSame(groupkey::MODE_OFF, (new \enrol_apply_plugin())->get_instance_defaults()['customchar1']);
    }
}
