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

namespace enrol_apply\event;

use core\event\base;

/**
 * A group enrolment key admitted an applicant without review.
 *
 * The one write on this route that no core event names: the enrolment and the memberships raise
 * core's own events, but nothing there says that no person decided. The object is the durable
 * record, which keeps the same fact.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class application_admitted_by_key extends base {
    /**
     * Set the basic properties.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'enrol_apply_submission';
    }

    /**
     * The event name.
     *
     * @return string Localised name.
     */
    public static function get_name() {
        return get_string('eventapplicationadmittedbykey', 'enrol_apply');
    }

    /**
     * What happened, for the logs.
     *
     * @return string Description; not localised, as core's own descriptions are not.
     */
    public function get_description() {
        return "The user with id '{$this->relateduserid}' was enrolled through the enrolment method with id " .
            "'{$this->other['enrolid']}' by the enrolment key of the group with id '{$this->other['groupid']}', " .
            "without review.";
    }

    /**
     * Refuse an event that does not say who, where and which group.
     *
     * @return void
     * @throws \coding_exception When a required property is missing.
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        foreach (['enrolid', 'groupid'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '{$key}' value must be set in other.");
            }
        }
    }

    /**
     * How the object id maps on a log restore.
     *
     * @return array The mapping.
     */
    public static function get_objectid_mapping() {
        return ['db' => 'enrol_apply_submission', 'restore' => base::NOT_MAPPED];
    }

    /**
     * How the ids in other map on a log restore.
     *
     * @return array The mapping.
     */
    public static function get_other_mapping() {
        return [
            'enrolid' => ['db' => 'enrol', 'restore' => 'enrol'],
            'groupid' => ['db' => 'groups', 'restore' => 'group'],
        ];
    }
}
