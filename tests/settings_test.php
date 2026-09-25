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
 * Tests for the site settings and the admin tree node this plugin registers.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use enrol_apply\local\queue;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Tests for the site settings and the admin tree node this plugin registers.
 *
 * settings.php declares no class: core includes it while building the admin tree, so every test
 * here builds that tree with admin_get_root() and reads what the file added to it.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class settings_test extends \advanced_testcase {
    /**
     * Enable the plugin, so its settings are loaded as they are on a live site.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
    }

    /**
     * Drop the admin tree built for the test's user, so no later test inherits it.
     *
     * @return void
     */
    protected function tearDown(): void {
        global $ADMIN;

        $ADMIN = null;
        parent::tearDown();
    }

    /**
     * A site administrator finds the site-wide queue under Courses, gated on the deciding capability.
     *
     * @return void
     */
    public function test_the_queue_node_is_registered_for_a_site_administrator(): void {
        $this->setAdminUser();

        $root = admin_get_root(true, true);
        $node = $root->locate('courses')->locate('enrol_apply');

        $this->assertInstanceOf(\admin_externalpage::class, $node);
        $this->assertSame(
            (new \moodle_url('/enrol/apply/manage.php'))->out(false),
            (new \moodle_url($node->url))->out(false)
        );
        $this->assertSame(['enrol/apply:manageapplications'], $node->req_capability);
        $this->assertTrue($node->check_access());
    }

    /**
     * A system-level decider without moodle/site:config gets no admin node, and reaches the queue by url.
     *
     * Core includes an enrol plugin's settings.php only for moodle/site:config holders, so the
     * node cannot be offered to anybody else; manage.php with no parameter is what serves them.
     *
     * @return void
     */
    public function test_a_system_level_decider_reaches_the_queue_by_url_and_not_the_admin_tree(): void {
        $system = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, $system->id);
        $decider = $this->getDataGenerator()->create_user();
        role_assign($roleid, $decider->id, $system->id);
        $this->setUser($decider);

        // The preconditions: the capability at system level, and no site configuration.
        $this->assertTrue(has_capability('enrol/apply:manageapplications', $system));
        $this->assertFalse(has_capability('moodle/site:config', $system));

        $root = admin_get_root(true, true);
        $this->assertNotNull($root->locate('courses'), 'the admin tree should have been built for this user');
        $this->assertNull($root->locate('enrol_apply'));
        // Not merely hidden: the plugin's own settings page is absent too, so the file never ran.
        $this->assertNull($root->locate('enrolsettingsapply'));

        // What serves them instead: the parameterless queue admits them site wide.
        $listing = queue::listing_scope(0);
        $this->assertTrue($listing->allowed);
        $this->assertNull($listing->mentees);
        $this->assertEquals($system, $listing->context);
    }

    /**
     * The two site default limits, as the admin tree keys them.
     *
     * @return array Setting key within the plugin's settings page, keyed by a readable case name.
     */
    public static function limit_setting_provider(): array {
        return [
            'applicant limit' => ['enrol_applymaxenrolled'],
            'places' => ['enrol_applyplaces'],
        ];
    }

    /**
     * A site default limit refuses a negative and an empty value, and accepts 0 and a positive one.
     *
     * @param string $key The setting's key within the plugin's settings page.
     * @return void
     */
    #[DataProvider('limit_setting_provider')]
    public function test_a_site_default_limit_refuses_a_negative(string $key): void {
        $this->setAdminUser();

        $page = admin_get_root(true, true)->locate('enrolsettingsapply');
        $this->assertInstanceOf(\admin_settingpage::class, $page);
        $setting = $page->settings->{$key};

        $this->assertNotTrue($setting->validate('-1'));
        $this->assertNotTrue($setting->validate(''));
        $this->assertTrue($setting->validate('0'));
        $this->assertTrue($setting->validate('5'));

        // Writing is what an administrator does: a negative is not stored, a positive one is.
        $before = $setting->get_setting();
        $this->assertNotSame('', $setting->write_setting('-1'));
        $this->assertSame($before, $setting->get_setting());
        $this->assertSame('', $setting->write_setting('7'));
        $this->assertSame('7', (string) $setting->get_setting());
    }
}
