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

namespace enrol_apply;

/**
 * An auth plugin that sends its users elsewhere to edit their profile, for the tests that need one.
 *
 * No core auth plugin returns an edit_profile_url(), and before Moodle 5.3 get_auth_plugin()
 * requires the plugin's own file from the code tree, so there is nothing a test could stand in its
 * place. From 5.3 it asks core's dependency container for \core\authentication, which a test may
 * replace and advanced_testcase resets after every test. A test using this skips before 5.3.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait profile_elsewhere {
    /**
     * From now on, the manual auth plugin names another page for editing a profile.
     *
     * Everything else about it is auth_manual's own, field locks and can_edit_profile() included.
     *
     * @return void
     */
    protected function send_manual_users_elsewhere(): void {
        global $CFG;

        if (!class_exists(\core\authentication::class)) {
            $this->markTestSkipped('Before Moodle 5.3 get_auth_plugin() offers no way to stand in an auth plugin.');
        }
        require_once($CFG->dirroot . '/auth/manual/auth.php');

        $elsewhere = new class extends \auth_plugin_manual {
            /**
             * The page this plugin's users edit their profile on.
             *
             * @return \moodle_url
             */
            public function edit_profile_url() {
                return new \moodle_url('/elsewhere/profile.php');
            }
        };

        \core\di::set(\core\authentication::class, new class ($elsewhere) extends \core\authentication {
            /** @var \auth_plugin_base The plugin returned for 'manual'. */
            protected $elsewhere;

            /**
             * Keep the plugin to return for 'manual'.
             *
             * @param \auth_plugin_base $elsewhere The plugin.
             */
            public function __construct(\auth_plugin_base $elsewhere) {
                $this->elsewhere = $elsewhere;
            }

            /**
             * The stand-in for 'manual', core's own plugin for any other name.
             *
             * @param string $auth Name of the auth plugin.
             * @return \auth_plugin_base
             */
            public function get_plugin(string $auth): \auth_plugin_base {
                return $auth === 'manual' ? $this->elsewhere : parent::get_plugin($auth);
            }
        });
    }
}
