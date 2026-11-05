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
 * The plugin, with a group key changed while its holder waits for the admission locks.
 *
 * The change is made from inside lock_admission(), which is where a real request can wait for
 * seconds, so the key has already resolved once and only a second read can see it.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class key_changing_plugin extends \enrol_apply_plugin {
    /** @var string 'rotated', 'moved', 'deleted', 'off' or 'none'. */
    public $change = 'none';

    /** @var \stdClass|null The group the key names. */
    public $keyed = null;

    /** @var \stdClass|null Another group of the course, which a moved key ends up on. */
    public $other = null;

    /**
     * A plugin that will make one change when the admission locks are asked for.
     *
     * @param string $change 'rotated', 'moved', 'deleted', 'off' or 'none'.
     * @param \stdClass $keyed The group the key names.
     * @param \stdClass $other Another group of the course.
     * @return self The plugin.
     */
    public static function making(string $change, \stdClass $keyed, \stdClass $other): self {
        $plugin = new self();
        $plugin->change = $change;
        $plugin->keyed = $keyed;
        $plugin->other = $other;

        return $plugin;
    }

    /**
     * The plugin's name, which enrol_plugin::get_name() would otherwise read off this class's name.
     *
     * @return string Always 'apply'.
     */
    public function get_name() {
        return 'apply';
    }

    /**
     * Make the change, as a teacher would while the request waits, then take the locks.
     *
     * @param \stdClass $instance Course enrol instance.
     * @param array $groupids Group ids.
     * @return array|null The locks.
     */
    protected function lock_admission(\stdClass $instance, array $groupids): ?array {
        global $DB;

        $key = (string) $DB->get_field('groups', 'enrolmentkey', ['id' => $this->keyed->id]);
        if ($this->change === 'rotated') {
            $DB->set_field('groups', 'enrolmentkey', $key . '-new', ['id' => $this->keyed->id]);
        } else if ($this->change === 'moved') {
            $DB->set_field('groups', 'enrolmentkey', '', ['id' => $this->keyed->id]);
            $DB->set_field('groups', 'enrolmentkey', $key, ['id' => $this->other->id]);
        } else if ($this->change === 'deleted') {
            groups_delete_group($this->keyed->id);
        } else if ($this->change === 'off') {
            $DB->set_field('enrol', 'customchar1', \enrol_apply\local\groupkey::MODE_OFF, ['id' => $instance->id]);
        }

        return parent::lock_admission($instance, $groupids);
    }
}
