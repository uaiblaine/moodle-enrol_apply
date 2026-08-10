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
 * Behat step definitions and page resolvers for enrol_apply.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL check here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Page resolvers for the enrol_apply management screens.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_enrol_apply extends behat_base {
    /**
     * Resolve page instance URLs for this plugin.
     *
     * Recognised pages:
     *   "<shortname> > manage applications"   the per-course application queue
     *   "<shortname> > application info"      the per-course submitted comments list
     *
     * @param string $type Identifies which page to resolve.
     * @param string $identifier Course short name.
     * @return moodle_url The resolved URL.
     * @throws Exception When the course has no apply enrolment instance.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'manage applications':
                return new moodle_url('/enrol/apply/manage.php', ['id' => $this->get_instance_id($identifier)]);

            case 'application info':
                return new moodle_url('/enrol/apply/info.php', ['id' => $this->get_instance_id($identifier)]);

            default:
                throw new Exception("Unrecognised enrol_apply page type '{$type}'.");
        }
    }

    /**
     * Resolve non-instance page URLs for this plugin.
     *
     * @param string $page Page name.
     * @return moodle_url The resolved URL.
     * @throws Exception When the page name is not recognised.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'manage applications':
                return new moodle_url('/enrol/apply/manage.php');

            default:
                throw new Exception("Unrecognised enrol_apply page '{$page}'.");
        }
    }

    /**
     * Return the apply enrolment instance id of the given course.
     *
     * @param string $shortname Course short name.
     * @return int Enrolment instance id.
     * @throws Exception When the course or the enrolment instance does not exist.
     */
    protected function get_instance_id(string $shortname): int {
        global $DB;

        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $instanceid = $DB->get_field('enrol', 'id', ['courseid' => $courseid, 'enrol' => 'apply'], IGNORE_MULTIPLE);
        if (!$instanceid) {
            throw new Exception("Course '{$shortname}' has no apply enrolment instance.");
        }

        return (int) $instanceid;
    }
}
