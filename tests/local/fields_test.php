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
 * Tests for the profile field set an enrolment application collects.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the profile field set an enrolment application collects.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(fields::class)]
#[CoversClass(fieldset::class)]
final class fields_test extends \advanced_testcase {
    /** @var \stdClass The course the apply instance belongs to. */
    protected $course;

    /** @var \stdClass The enrol_apply instance record. */
    protected $instance;

    /** @var \enrol_apply_plugin The plugin under test. */
    protected $plugin;

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
     * Write an envelope straight onto the instance, bypassing the form entirely.
     *
     * @param array $keys Field keys to store.
     * @param array $required Keys among them that are required.
     * @return \stdClass The instance as it now stands.
     */
    protected function store_envelope(array $keys, array $required = []): \stdClass {
        global $DB;

        $DB->set_field(
            'enrol',
            'customtext4',
            fieldset::from_keys($keys, $required)->to_json(),
            ['id' => $this->instance->id]
        );

        return $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
    }

    /**
     * The default set is AUTHSYNCFIELDS minus the four exclusions, and nothing else.
     *
     * @return void
     */
    public function test_default_set_is_authsyncfields_minus_the_four_exclusions(): void {
        $expected = array_map(
            [fields::class, 'standard_key'],
            array_values(array_diff(\core_user::AUTHSYNCFIELDS, ['email', 'idnumber', 'lang', 'description']))
        );

        $this->assertCount(13, fields::DEFAULT_SET);
        $this->assertSame($expected, fields::DEFAULT_SET);
    }

    /**
     * A site that has never been configured allows the default set rather than nothing.
     *
     * The distinction matters because the setting stores only the ticked keys, so "never
     * written" and "written empty" are different states that a careless read would conflate.
     *
     * @return void
     */
    public function test_pool_falls_back_to_the_default_set_when_never_configured(): void {
        set_config('allowedfields', null, 'enrol_apply');

        $this->assertSame(fields::DEFAULT_SET, fields::pool());

        // The control: an explicitly empty pool really is empty, not the default set.
        set_config('allowedfields', '', 'enrol_apply');
        $this->assertSame([], fields::pool());
    }

    /**
     * The picked set is intersected with the site pool on every read.
     *
     * @return void
     */
    public function test_resolve_intersects_the_picked_set_with_the_site_pool(): void {
        $picked = ['s_city', 's_country', 's_institution', 's_department', 's_phone1'];
        set_config('allowedfields', 's_city,s_country,s_institution', 'enrol_apply');

        $instance = $this->store_envelope($picked);

        $this->assertSame(['s_city', 's_country', 's_institution'], fields::resolve($instance)->keys());
    }

    /**
     * A key forged into the envelope is dropped, because it is not in the pool.
     *
     * @return void
     */
    public function test_resolve_drops_a_forged_key_that_is_not_in_the_pool(): void {
        set_config('allowedfields', 's_city', 'enrol_apply');

        $instance = $this->store_envelope(['s_password', 's_auth', 's_suspended', 's_city']);

        /* The control is s_city: it proves the filter ran and kept what it should, rather
           than the envelope having failed to parse and everything vanishing together. */
        $this->assertSame(['s_city'], fields::resolve($instance)->keys());
    }

    /**
     * A denied key written straight into the site setting never becomes part of the pool.
     *
     * This is the barrier itself. Every other filter downstream reads the pool, so this one
     * test is what stands between a hand-edited or restored configuration and a form that
     * asks an applicant for their password hash.
     *
     * @return void
     */
    public function test_pool_drops_a_denied_key(): void {
        set_config('allowedfields', 's_auth,s_city,s_password,s_email', 'enrol_apply');

        $pool = fields::pool();

        // The control: the legitimate key survived, so the filter ran rather than everything failing.
        $this->assertSame(['s_city'], $pool);
    }

    /**
     * A denied key in the setting cannot reach an instance's resolved set either.
     *
     * The end-to-end statement of the same property: what a reader of this plugin cares
     * about is not which function filters, but that the form never asks for the field.
     *
     * @return void
     */
    public function test_a_denied_key_never_survives_to_the_resolved_set(): void {
        set_config('allowedfields', 's_auth,s_city', 'enrol_apply');

        $instance = $this->store_envelope(['s_auth', 's_city']);

        $this->assertSame(['s_city'], fields::resolve($instance)->keys());
    }

    /**
     * A custom field that has been deleted since it was picked drops out of the set.
     *
     * @return void
     */
    public function test_resolve_drops_a_custom_field_that_has_been_deleted(): void {
        global $DB;

        $field = $this->create_custom_field('extraone');
        $key = fields::custom_key((int) $field->id);
        set_config('allowedfields', $key . ',s_city', 'enrol_apply');

        $instance = $this->store_envelope([$key, 's_city']);
        $this->assertSame([$key, 's_city'], fields::resolve($instance)->keys());

        $DB->delete_records('user_info_field', ['id' => $field->id]);

        $this->assertSame(['s_city'], fields::resolve($instance)->keys());
    }

    /**
     * An instance migrated from the old switches still collects something after the upgrade.
     *
     * @return void
     */
    public function test_the_upgrade_migrates_the_old_switches_into_the_envelope(): void {
        global $CFG, $DB;

        /* The migration helpers, not the upgrade step itself: upgrade_plugin_savepoint()
           refuses when the savepoint is not newer than the installed version, and in a test
           environment the plugin is always already installed at the current version. */
        require_once($CFG->dirroot . '/enrol/apply/db/upgradelib.php');

        $field = $this->create_custom_field('legacyone');
        $DB->set_field('enrol', 'customint1', 1, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'customint2', 1, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'customtext4', '', ['id' => $this->instance->id]);
        set_config('allowedfields', null, 'enrol_apply');

        enrol_apply_seed_field_pool();
        enrol_apply_migrate_field_switches();

        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
        $resolved = fields::resolve($instance);

        $this->assertNotEmpty($resolved->keys());
        $this->assertContains('s_city', $resolved->keys());
        $this->assertContains(fields::custom_key((int) $field->id), $resolved->keys());

        // The pool was seeded too, so the intersection cannot silently zero everything.
        $this->assertNotFalse(get_config('enrol_apply', 'allowedfields'));
    }

    /**
     * An instance that collected nothing before the upgrade still collects nothing after it.
     *
     * The control for the migration: without it, a step that seeded every instance would
     * look correct on the only case the other test exercises.
     *
     * @return void
     */
    public function test_the_upgrade_leaves_an_instance_that_collected_nothing_empty(): void {
        global $CFG, $DB;

        /* The migration helpers, not the upgrade step itself: upgrade_plugin_savepoint()
           refuses when the savepoint is not newer than the installed version, and in a test
           environment the plugin is always already installed at the current version. */
        require_once($CFG->dirroot . '/enrol/apply/db/upgradelib.php');

        $DB->set_field('enrol', 'customint1', 0, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'customint2', 0, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'customtext4', '', ['id' => $this->instance->id]);

        enrol_apply_seed_field_pool();
        enrol_apply_migrate_field_switches();

        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
        $this->assertSame([], fields::resolve($instance)->keys());
    }

    /**
     * The migration never overwrites a field set that has already been configured.
     *
     * It matters because the step is not guaranteed to run once: an upgrade can be re-run,
     * and a site can be upgraded from an older version after a teacher has already picked a
     * set on a newer one. Overwriting would silently replace a real choice with whatever the
     * long-dead switches happened to say.
     *
     * @return void
     */
    public function test_the_migration_does_not_overwrite_an_existing_field_set(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/enrol/apply/db/upgradelib.php');

        set_config('allowedfields', 's_city,s_country,s_phone1', 'enrol_apply');
        $this->store_envelope(['s_phone1'], ['s_phone1']);

        /* The old switches say "collect the whole standard set", which is what the migration
           would write if it did not check first - so the two states are distinguishable. */
        $DB->set_field('enrol', 'customint1', 1, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'customint2', 1, ['id' => $this->instance->id]);

        $migrated = enrol_apply_migrate_field_switches();

        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
        $resolved = fields::resolve($instance);

        $this->assertSame(0, $migrated);
        $this->assertSame(['s_phone1'], $resolved->keys());
        $this->assertTrue($resolved->is_required('s_phone1'));

        /* The control: a sibling instance with no envelope IS migrated in the same run, so
           the assertion above reflects the guard rather than the step having done nothing. */
        $otherid = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $DB->set_field('enrol', 'customtext4', '', ['id' => $otherid]);
        $DB->set_field('enrol', 'customint1', 1, ['id' => $otherid]);

        $this->assertSame(1, enrol_apply_migrate_field_switches());
        $other = $DB->get_record('enrol', ['id' => $otherid], '*', MUST_EXIST);
        $this->assertNotEmpty(fields::resolve($other)->keys());
    }

    /**
     * A name field the site's fullname format does not use drops out of the set.
     *
     * The four phonetic, middle and alternate name fields are subject to
     * useredit_get_enabled_name_fields(). Under the default fullnamedisplay of "language",
     * core enables none of them and never shows them on its own profile form, so a plugin
     * that renders them is asking for something core would not.
     *
     * @return void
     */
    public function test_resolve_drops_a_name_field_the_site_does_not_use(): void {
        global $CFG;

        set_config('allowedfields', 's_city,s_middlename,s_alternatename', 'enrol_apply');
        $instance = $this->store_envelope(['s_city', 's_middlename', 's_alternatename']);

        $CFG->fullnamedisplay = 'language';
        $this->assertSame(['s_city'], fields::resolve($instance)->keys());

        /* The control: naming one of them in the format brings exactly that one back, which
           proves the filter reads the setting rather than dropping the family outright. */
        $CFG->fullnamedisplay = 'firstname middlename lastname';
        $this->assertSame(['s_city', 's_middlename'], fields::resolve($instance)->keys());
    }

    /**
     * The envelope survives a round trip, including the required flags.
     *
     * @return void
     */
    public function test_the_envelope_round_trips(): void {
        $set = fieldset::from_keys(['s_city', 's_country'], ['s_country']);
        $reloaded = fieldset::from_json($set->to_json());

        $this->assertSame(['s_city', 's_country'], $reloaded->keys());
        $this->assertFalse($reloaded->is_required('s_city'));
        $this->assertTrue($reloaded->is_required('s_country'));
    }

    /**
     * Every way an envelope can be unusable resolves to an empty set rather than an error.
     *
     * @param string $json The stored value.
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unusable_envelope_provider')]
    public function test_an_unusable_envelope_resolves_to_nothing(string $json): void {
        $this->assertSame([], fieldset::from_json($json)->keys());
    }

    /**
     * Stored values that must not produce a field.
     *
     * @return array Test cases, each a single JSON string.
     */
    public static function unusable_envelope_provider(): array {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
            'not json' => ['{not json at all'],
            'json but not an object' => ['"a string"'],
            'no fields key' => ['{"version":1}'],
            'fields not a list' => ['{"version":1,"fields":"s_city"}'],
            'unknown version' => ['{"version":99,"fields":[{"key":"s_city"}]}'],
            'entry without a key' => ['{"version":1,"fields":[{"required":1}]}'],
            'key not a string' => ['{"version":1,"fields":[{"key":42}]}'],
            'oversized' => ['{"version":1,"fields":[{"key":"s_city"}],"pad":"' . str_repeat('x', 70000) . '"}'],
        ];
    }

    /**
     * A field label has two spellings, and the escaped one really differs.
     *
     * The fixture is a bare ampersand on purpose. A tag-shaped fixture proves nothing here:
     * format_string() strips tags identically in both escape modes, so the two spellings
     * would come back equal and the test would pass against a helper that ignored the flag.
     *
     * @return void
     */
    public function test_a_custom_field_label_has_both_spellings(): void {
        $field = $this->create_custom_field('ampersand', 'Research & Development');
        $key = fields::custom_key((int) $field->id);

        $plain = fields::label($key, false);
        $escaped = fields::label($key, true);

        $this->assertSame('Research & Development', $plain);
        $this->assertSame('Research &amp; Development', $escaped);
        $this->assertNotSame($plain, $escaped);
    }

    /**
     * A standard field is labelled by its core string, not by its key.
     *
     * @return void
     */
    public function test_a_standard_field_is_labelled_by_a_core_string(): void {
        $this->assertSame(get_string('city'), fields::label('s_city'));
        $this->assertSame(get_string('phone2'), fields::label('s_phone2'));
    }

    /**
     * A locked standard field classifies as locked rather than editable.
     *
     * @return void
     */
    public function test_a_locked_standard_field_classifies_as_locked(): void {
        $user = $this->getDataGenerator()->create_user(['city' => 'Campinas']);

        $this->assertSame(fields::STATE_EDITABLE, fields::classify('s_city', $user));

        set_config('field_lock_city', 'locked', 'auth_manual');
        $this->assertSame(fields::STATE_LOCKED, fields::classify('s_city', $user));
    }

    /**
     * unlockedifempty treats a stored zero as filled in, and therefore as locked.
     *
     * The emptiness test core applies is a loose comparison against the empty string, so a
     * stored '0' counts as a value. Reading it with empty() inverts the decision.
     *
     * @return void
     */
    public function test_unlockedifempty_treats_a_stored_zero_as_filled(): void {
        $withzero = $this->getDataGenerator()->create_user(['city' => '0']);
        $withnothing = $this->getDataGenerator()->create_user(['city' => '']);

        set_config('field_lock_city', 'unlockedifempty', 'auth_manual');

        $this->assertSame(fields::STATE_LOCKED, fields::classify('s_city', $withzero));
        // The control: genuinely empty really is still editable.
        $this->assertSame(fields::STATE_EDITABLE, fields::classify('s_city', $withnothing));
    }

    /**
     * A custom field hidden from everybody is absent, not merely locked.
     *
     * @return void
     */
    public function test_a_custom_field_with_no_visibility_is_absent(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $field = $this->create_custom_field('hiddenone');
        $key = fields::custom_key((int) $field->id);

        $this->assertSame(fields::STATE_EDITABLE, fields::classify($key, $user));

        $DB->set_field('user_info_field', 'visible', PROFILE_VISIBLE_NONE, ['id' => $field->id]);
        $this->assertSame(fields::STATE_ABSENT, fields::classify($key, $user));
    }

    /**
     * A denied key never classifies as anything but absent.
     *
     * @return void
     */
    public function test_a_denied_key_is_always_absent(): void {
        $user = $this->getDataGenerator()->create_user();

        $this->assertSame(fields::STATE_ABSENT, fields::classify('s_password', $user));
        $this->assertSame(fields::STATE_ABSENT, fields::classify('s_auth', $user));
    }

    /**
     * Create a text custom profile field and return its record.
     *
     * @param string $shortname Field shortname.
     * @param string $name Field name shown to users.
     * @return \stdClass The created {user_info_field} record.
     */
    protected function create_custom_field(string $shortname, string $name = 'Extra field'): \stdClass {
        global $DB;

        $categoryid = $DB->insert_record('user_info_category', (object) ['name' => 'Extra', 'sortorder' => 1]);
        $id = $DB->insert_record('user_info_field', (object) [
            'shortname' => $shortname,
            'name' => $name,
            'datatype' => 'text',
            'categoryid' => $categoryid,
            'sortorder' => 1,
            'required' => 0,
            'locked' => 0,
            'visible' => PROFILE_VISIBLE_ALL,
            'forceunique' => 0,
            'signup' => 0,
            'defaultdata' => '',
            'param1' => 30,
            'param2' => 2048,
        ]);

        return $DB->get_record('user_info_field', ['id' => $id], '*', MUST_EXIST);
    }
}
