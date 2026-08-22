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
 * Tests for the optional write of an application's answers to the applicant's profile.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the optional write of an application's answers to the applicant's profile.
 *
 * Every guard here stands between a forged post and the {user} table, because core provides
 * none: user_update_user() consults no capability and no field lock, and profile_save_data()
 * performs no authorisation check at all.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(profilewriter::class)]
#[CoversClass(diff::class)]
#[CoversClass(completeness::class)]
final class profilewriter_test extends \advanced_testcase {
    /** @var \stdClass Course the instance belongs to. */
    protected $course;

    /** @var \stdClass The enrol_apply instance. */
    protected $instance;

    /** @var \enrol_apply_plugin The plugin. */
    protected $plugin;

    /** @var \stdClass The applicant. */
    protected $user;

    /**
     * A course, an instance with writing switched on, and an applicant with a stored city.
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

        set_config('allowedfields', 's_city,s_institution,s_department', 'enrol_apply');
        set_config('allowprofilewrite', 1, 'enrol_apply');
        $DB->set_field(
            'enrol',
            'customtext4',
            fieldset::from_keys(['s_city', 's_institution'])->to_json(),
            ['id' => $instanceid]
        );
        $DB->set_field('enrol', 'customint8', 1, ['id' => $instanceid]);
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $this->user = $this->getDataGenerator()->create_user(['city' => 'Campinas', 'institution' => '']);
        $this->setUser($this->user);
    }

    /**
     * Re-read the applicant straight from the database.
     *
     * @return \stdClass The stored record.
     */
    protected function stored(): \stdClass {
        global $DB;

        return $DB->get_record('user', ['id' => $this->user->id], '*', MUST_EXIST);
    }

    /**
     * A key the user may not edit is ignored, while a legitimate one in the same post is written.
     *
     * @return void
     */
    public function test_write_ignores_a_key_the_user_may_not_edit(): void {
        profilewriter::write($this->instance, $this->user, [
            'city' => 'Belo Horizonte',
            'auth' => 'nologin',
            'password' => 'hunter2',
            'username' => 'someoneelse',
        ]);

        $stored = $this->stored();
        // The control: the legitimate key really was written, so the write ran at all.
        $this->assertSame('Belo Horizonte', $stored->city);
        $this->assertSame('manual', $stored->auth);
        $this->assertNotSame('someoneelse', $stored->username);
    }

    /**
     * A locked field is not written even when its value is posted.
     *
     * Locks are a form behaviour and nothing below the form honours them, so this is the only
     * thing standing between a forged post and an overwritten value.
     *
     * @return void
     */
    public function test_write_ignores_a_locked_field_even_when_posted(): void {
        set_config('field_lock_city', 'locked', 'auth_manual');

        profilewriter::write($this->instance, $this->user, [
            'city' => 'Belo Horizonte',
            'institution' => 'UFMG',
        ]);

        $stored = $this->stored();
        $this->assertSame('Campinas', $stored->city);
        // The control: the unlocked field posted in the same request was written.
        $this->assertSame('UFMG', $stored->institution);
    }

    /**
     * A custom field lock is honoured too, read by its own config key.
     *
     * @return void
     */
    public function test_write_honours_a_custom_field_lock(): void {
        global $DB;

        $field = $this->create_custom_field('lockedcustom');
        $key = fields::custom_key((int) $field->id);
        set_config('allowedfields', 's_city,' . $key, 'enrol_apply');
        $DB->set_field(
            'enrol',
            'customtext4',
            fieldset::from_keys(['s_city', $key])->to_json(),
            ['id' => $this->instance->id]
        );
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
        set_config('field_lock_profile_field_lockedcustom', 'locked', 'auth_manual');

        profilewriter::write($this->instance, $this->user, [
            'city' => 'Belo Horizonte',
            'profile_field_lockedcustom' => 'forged',
        ]);

        $this->assertFalse($DB->record_exists('user_info_data', [
            'userid' => $this->user->id,
            'fieldid' => $field->id,
        ]));
        // The control: the standard field in the same post was written.
        $this->assertSame('Belo Horizonte', $this->stored()->city);
    }

    /**
     * The lock is read through the auth plugin, so a legacy-only key still counts.
     *
     * auth_manual builds its config as array_merge((array) legacy, (array) modern), so the
     * modern component WINS. On a normally installed site every field_lock_* already exists
     * under auth_manual as 'unlocked', which is why the legacy key looks dead - measured on
     * 5.2, setting it alone changes nothing. It only decides the answer where the modern key
     * is absent, which is the case this test constructs, and there a plugin reading
     * get_config('auth_manual', ...) directly sees nothing while the core user edit form
     * honours the lock. That is the difference this test exists to hold.
     *
     * @return void
     */
    public function test_write_reads_the_lock_through_the_auth_plugin_config(): void {
        set_config('field_lock_city', null, 'auth_manual');
        set_config('field_lock_city', 'locked', 'auth/manual');

        // The precondition: a direct read of the modern component really does see nothing.
        $this->assertFalse(get_config('auth_manual', 'field_lock_city'));

        profilewriter::write($this->instance, $this->user, ['city' => 'Belo Horizonte']);

        $this->assertSame('Campinas', $this->stored()->city);
    }

    /**
     * A whitespace-only value is not a value.
     *
     * @return void
     */
    public function test_a_whitespace_only_value_is_not_written(): void {
        profilewriter::write($this->instance, $this->user, ['city' => '    ']);

        $this->assertSame('Campinas', $this->stored()->city);
    }

    /**
     * An enrolment form may add to a profile but never empty it.
     *
     * Core's own boundary would erase: edit_save_data() ignores a value only when the
     * property is absent, so an empty string is written straight through.
     *
     * @return void
     */
    public function test_a_submitted_empty_value_never_erases_a_stored_one(): void {
        profilewriter::write($this->instance, $this->user, [
            'city' => '',
            'institution' => 'UFMG',
        ]);

        $stored = $this->stored();
        $this->assertSame('Campinas', $stored->city);
        // The control: the write ran, and added where there was nothing before.
        $this->assertSame('UFMG', $stored->institution);
    }

    /**
     * Nothing is written when either half of the switch is off.
     *
     * @return void
     */
    public function test_write_is_refused_when_the_site_switch_is_off(): void {
        set_config('allowprofilewrite', 0, 'enrol_apply');

        $this->assertSame([], profilewriter::write($this->instance, $this->user, ['city' => 'Belo Horizonte']));
        $this->assertSame('Campinas', $this->stored()->city);
    }

    /**
     * The instance half of the switch is enforced separately.
     *
     * @return void
     */
    public function test_write_is_refused_when_the_instance_switch_is_off(): void {
        global $DB;

        $DB->set_field('enrol', 'customint8', 0, ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $this->assertSame([], profilewriter::write($this->instance, $this->user, ['city' => 'Belo Horizonte']));
        $this->assertSame('Campinas', $this->stored()->city);
    }

    /**
     * The capability to edit one's own profile is required.
     *
     * @return void
     */
    public function test_write_requires_editownprofile(): void {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability(
            'moodle/user:editownprofile',
            CAP_PROHIBIT,
            $roleid,
            \context_system::instance()->id,
            true
        );

        $this->assertSame([], profilewriter::write($this->instance, $this->user, ['city' => 'Belo Horizonte']));
        $this->assertSame('Campinas', $this->stored()->city);
    }

    /**
     * Exactly one user_updated event is fired, and it carries no field value.
     *
     * @return void
     */
    public function test_write_fires_exactly_one_user_updated_event(): void {
        $sink = $this->redirectEvents();
        profilewriter::write($this->instance, $this->user, [
            'city' => 'Belo Horizonte',
            'institution' => 'UFMG',
        ]);
        $events = array_filter($sink->get_events(), static function ($event): bool {
            return $event instanceof \core\event\user_updated;
        });
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertStringNotContainsString('Belo Horizonte', json_encode($event->get_data()));
    }

    /**
     * Nothing changed means nothing offered, which is what hides the button.
     *
     * @return void
     */
    public function test_the_diff_is_empty_when_nothing_changed(): void {
        $changes = diff::compute($this->instance, $this->user, ['city' => 'Campinas']);

        $this->assertSame([], $changes);
    }

    /**
     * The diff reports a change and carries both sides of it.
     *
     * @return void
     */
    public function test_the_diff_carries_before_and_after(): void {
        $changes = diff::compute($this->instance, $this->user, ['city' => 'Belo Horizonte']);

        $this->assertCount(1, $changes);
        $this->assertSame('s_city', $changes[0]['key']);
        $this->assertSame('Campinas', $changes[0]['before']);
        $this->assertSame('Belo Horizonte', $changes[0]['after']);
    }

    /**
     * The completeness gate sees a textarea custom field that has a value.
     *
     * profile_user_record() defaults $onlyinuserobject to true and
     * profile_field_textarea::is_user_object_data() returns false, so reading through it
     * would report a filled-in textarea field as permanently missing and lock the applicant
     * out of a gate they can never satisfy. enrol_gapply has exactly that defect.
     *
     * @return void
     */
    public function test_the_completeness_gate_sees_a_filled_in_textarea_field(): void {
        global $DB;

        $field = $this->create_custom_field('bio', 'textarea');
        $key = fields::custom_key((int) $field->id);
        set_config('allowedfields', $key, 'enrol_apply');
        $DB->set_field('enrol', 'customtext4', fieldset::from_keys([$key])->to_json(), ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        // Nothing stored yet: it is missing, which is the control for the assertion below.
        $this->assertSame([$key], array_column(completeness::missing($this->instance, $this->user), 'key'));

        $DB->insert_record('user_info_data', (object) [
            'userid' => $this->user->id,
            'fieldid' => $field->id,
            'data' => 'A long-standing answer',
            'dataformat' => FORMAT_HTML,
        ]);

        $this->assertSame([], completeness::missing($this->instance, $this->user));
    }

    /**
     * A cross-site restore switches the instance opt-in off.
     *
     * @return void
     */
    public function test_restore_zeroes_the_instance_switch(): void {
        $defaults = $this->plugin->get_instance_defaults();

        $this->assertArrayHasKey('customint8', $defaults);
        $this->assertSame(0, $defaults['customint8']);
    }

    /**
     * Create a custom profile field and return its record.
     *
     * @param string $shortname Field shortname.
     * @param string $datatype Field datatype.
     * @return \stdClass The created record.
     */
    protected function create_custom_field(string $shortname, string $datatype = 'text'): \stdClass {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        $categoryid = $DB->insert_record('user_info_category', (object) ['name' => 'Extra', 'sortorder' => 1]);
        $id = $DB->insert_record('user_info_field', (object) [
            'shortname' => $shortname,
            'name' => 'Extra ' . $shortname,
            'datatype' => $datatype,
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
