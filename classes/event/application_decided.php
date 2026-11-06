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
 * What the three decision events share: a decider acting on one application.
 *
 * The acting user is the event's userid, the applicant its relateduserid, the context the course.
 * The object is the durable record (enrol_apply_submission), which outlives the enrolment a
 * cancellation deletes; an application older than that table has none, and the event then carries
 * neither objecttable nor objectid. other holds ids and flags only, never the message to the
 * applicant, the decision note or a name: the log store keeps these lines after the applicant's
 * own erasure (see the README's audit trail section).
 *
 * Abstract, so core's event list and the event monitor skip it.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class application_decided extends base {
    /**
     * Set the basic properties.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'enrol_apply_submission';
    }

    /**
     * Build the event for one application, acted on by the current user.
     *
     * The object is the newest record of the user enrolment, the one every decision writer stamps.
     *
     * @param int $userenrolmentid User enrolment the application belongs to.
     * @param \stdClass $instance The apply instance.
     * @param int $applicantid The applicant.
     * @param array $other This event's own keys of other; the shared ones are added here.
     * @return static The event, not yet triggered.
     */
    public static function for_application(int $userenrolmentid, \stdClass $instance, int $applicantid, array $other): static {
        global $DB;

        $records = $DB->get_records(
            'enrol_apply_submission',
            ['userenrolmentid' => $userenrolmentid],
            'timecreated DESC, id DESC',
            'id, keygroupid',
            0,
            1
        );
        $record = reset($records);

        $event = static::create([
            'objectid' => $record ? (int) $record->id : null,
            'context' => \context_course::instance((int) $instance->courseid),
            'relateduserid' => $applicantid,
            'other' => [
                'enrolid' => (int) $instance->id,
                'userenrolmentid' => $userenrolmentid,
                'keygroupid' => $record ? (int) $record->keygroupid : 0,
            ] + $other,
        ]);
        // Both or neither: core warns about a table named with no id.
        if (!$record) {
            $event->data['objecttable'] = null;
        }

        return $event;
    }

    /**
     * The keys of other this event must carry, beyond the shared ones.
     *
     * @return array Key names.
     */
    protected static function own_other_keys(): array {
        return [];
    }

    /**
     * Refuse an event that does not say who, where and which application.
     *
     * @return void
     * @throws \coding_exception When a required property is missing.
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        foreach (array_merge(['enrolid', 'userenrolmentid', 'keygroupid'], static::own_other_keys()) as $key) {
            if (!array_key_exists($key, (array) $this->other)) {
                throw new \coding_exception("The '{$key}' value must be set in other.");
            }
        }
    }

    /**
     * The approval queue of the method; decided applications have no review page of their own.
     *
     * @return \moodle_url The queue.
     */
    public function get_url() {
        return new \moodle_url('/enrol/apply/manage.php', ['id' => $this->other['enrolid']]);
    }

    /**
     * How the object id maps on a log restore.
     *
     * The plugin's restore registers every record it reads under this name, to 0 for one it
     * skipped (restore_enrol_apply_plugin::process_enrol_apply_submission()), and course logs are
     * restored after the enrolments.
     *
     * @return array The mapping.
     */
    public static function get_objectid_mapping() {
        return ['db' => 'enrol_apply_submission', 'restore' => 'enrol_apply_submission'];
    }

    /**
     * How the ids in other map on a log restore.
     *
     * Every key holding an id is declared; the flags are not ids and are copied as they are. The
     * user enrolment maps through the name enrol_apply_plugin::restore_user_enrolment() registers;
     * a cancelled application's enrolment was not in the backup, and core's log restore then
     * writes NOT_FOUND.
     *
     * @return array The mapping.
     */
    public static function get_other_mapping() {
        return [
            'enrolid' => ['db' => 'enrol', 'restore' => 'enrol'],
            'userenrolmentid' => ['db' => 'user_enrolments', 'restore' => 'enrol_apply_userenrolment'],
            'keygroupid' => ['db' => 'groups', 'restore' => 'group'],
        ];
    }
}
