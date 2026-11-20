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

use coding_exception;
use invalid_parameter_exception;

/**
 * The groups a method offers its applicants (enrol_apply_offer).
 *
 * A table of its own, apart from enrol_apply_groups, because every reader of that table takes a
 * row as a group the approval joins: an offered group stored there would be joined by everybody
 * a single reader forgot to filter.
 *
 * Every read joins the group on the method's own course, so a row naming another course's
 * group, which only a hand edit could write, offers nothing. Whether the applicant may be told
 * a group's name is the caller's question ({@see groupvisibility}).
 *
 * Before the upgrade that creates the table ({@see instanceoptions::schema_ready()}) nothing is
 * offered, the deletion does nothing and a write throws.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class groupoffer {
    /**
     * The groups a method offers that still exist in its course.
     *
     * The columns are named, never g.*: a group row carries its enrolment key.
     *
     * @param int $enrolid Enrol instance id.
     * @return array Group id => record with id, courseid, name, idnumber and visibility, ascending by id.
     */
    public static function groups(int $enrolid): array {
        global $DB;

        if (!instanceoptions::schema_ready()) {
            return [];
        }
        return $DB->get_records_sql(
            "SELECT g.id, g.courseid, g.name, g.idnumber, g.visibility
               FROM {enrol_apply_offer} o
               JOIN {enrol} e ON e.id = o.enrolid AND e.enrol = :apply
               JOIN {groups} g ON g.id = o.groupid AND g.courseid = e.courseid
              WHERE o.enrolid = :enrolid
           ORDER BY g.id ASC",
            ['apply' => 'apply', 'enrolid' => $enrolid]
        );
    }

    /**
     * The ids of the groups a method offers that still exist in its course.
     *
     * @param int $enrolid Enrol instance id.
     * @return int[] Group ids, ascending.
     */
    public static function group_ids(int $enrolid): array {
        return array_map('intval', array_keys(self::groups($enrolid)));
    }

    /**
     * Replace a method's offer with exactly these groups.
     *
     * Every group must belong to the method's course, or nothing is written.
     *
     * @param int $enrolid Enrol instance id; it must be an apply instance.
     * @param array $groupids Group ids, ints or digit strings; duplicates are ignored.
     * @return void
     * @throws coding_exception When the instance is not an apply instance.
     * @throws invalid_parameter_exception When an id is not an id of a group of the method's course.
     */
    public static function set(int $enrolid, array $groupids): void {
        global $DB;

        $courseid = self::course_of($enrolid);
        $ids = [];
        foreach ($groupids as $groupid) {
            if (!(is_int($groupid) || (is_string($groupid) && preg_match('/^\d{1,10}$/', $groupid))) || (int) $groupid <= 0) {
                throw new invalid_parameter_exception('A group id must be a positive integer.');
            }
            $ids[(int) $groupid] = (int) $groupid;
        }

        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal(array_values($ids), SQL_PARAMS_NAMED);
            $params['courseid'] = $courseid;
            $found = $DB->count_records_select('groups', "id {$insql} AND courseid = :courseid", $params);
            if ($found !== count($ids)) {
                throw new invalid_parameter_exception('Every offered group must be a group of the method\'s course.');
            }
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('enrol_apply_offer', ['enrolid' => $enrolid]);
        ksort($ids);
        foreach ($ids as $groupid) {
            $DB->insert_record('enrol_apply_offer', (object) ['enrolid' => $enrolid, 'groupid' => $groupid]);
        }
        $transaction->allow_commit();
    }

    /**
     * Offer one more group, as a restore does group by group.
     *
     * @param int $enrolid Enrol instance id; it must be an apply instance.
     * @param int $groupid Group id.
     * @return bool True when the group is offered afterwards; false when it is not a group of the method's course.
     * @throws coding_exception When the instance is not an apply instance.
     */
    public static function add(int $enrolid, int $groupid): bool {
        global $DB;

        $courseid = self::course_of($enrolid);
        if (!$DB->record_exists('groups', ['id' => $groupid, 'courseid' => $courseid])) {
            return false;
        }
        if (!$DB->record_exists('enrol_apply_offer', ['enrolid' => $enrolid, 'groupid' => $groupid])) {
            $DB->insert_record('enrol_apply_offer', (object) ['enrolid' => $enrolid, 'groupid' => $groupid]);
        }
        return true;
    }

    /**
     * Forget a method's offer, as its deletion does.
     *
     * @param int $enrolid Enrol instance id.
     * @return void
     */
    public static function delete_for_instance(int $enrolid): void {
        global $DB;

        if (!instanceoptions::schema_ready()) {
            return;
        }
        $DB->delete_records('enrol_apply_offer', ['enrolid' => $enrolid]);
    }

    /**
     * The course of an apply instance.
     *
     * @param int $enrolid Enrol instance id.
     * @return int Course id.
     * @throws coding_exception When the upgrade has not run, or the instance is not an apply instance.
     */
    protected static function course_of(int $enrolid): int {
        global $DB;

        if (!instanceoptions::schema_ready()) {
            throw new coding_exception('The enrol_apply upgrade that creates the offer has not run yet.');
        }
        $courseid = $DB->get_field('enrol', 'courseid', ['id' => $enrolid, 'enrol' => 'apply']);
        if (!$courseid) {
            throw new coding_exception('An offer belongs to an apply enrolment instance, and ' . $enrolid . ' is not one.');
        }
        return (int) $courseid;
    }
}
