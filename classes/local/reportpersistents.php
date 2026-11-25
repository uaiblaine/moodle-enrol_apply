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

use core_reportbuilder\local\models\report;
use core_reportbuilder\local\models\user_filter;
use core_reportbuilder\local\report\base;
use enrol_apply\reportbuilder\local\systemreports\course_applications;

/**
 * The report builder persistents of the plugin's system reports, and the readers' stored filter values on them.
 *
 * Core keeps one persistent per source, context, component, area and item a system report was built with
 * ({@see \core_reportbuilder\system_report_factory::create()}), and each reader's filter values of it in
 * reportbuilder_user_filter. The plugin's page builds one per enrolment method ({@see course_applications::for_method()}:
 * component enrol_apply, area method, the method's id), but core's core_reportbuilder_retrieve_system_report and
 * core_reportbuilder_can_view_system_report create one with whatever component, area and item the client sends, before
 * they ask can_view(). So a persistent is the plugin's when it is a system report whose source is one of the plugin's
 * system report classes, whatever its component: the class core instantiates is the recorded fact, the component a
 * label the caller chose either way (a client can as well build another plugin's report under this component). The
 * source is compared without case and leading separator, as PHP resolves a class name. A custom report built on the
 * plugin's datasource is its author's, covered by core's provider, and never touched here.
 *
 * A persistent is deleted through core's model, whose before_delete() removes the stored filter values, columns,
 * conditions, audiences and schedules hanging on it.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reportpersistents {
    /** @var string[] The plugin's system report classes; reportpersistents_test holds the list against the tree. */
    public const SOURCES = [course_applications::class];

    /**
     * The condition that a report persistent is the plugin's.
     *
     * @param string $alias The alias of {reportbuilder_report} in the statement.
     * @param string $prefix The prefix of the parameters' names, unique in the statement.
     * @return array [the SQL condition, its named parameters].
     */
    public static function is_plugins(string $alias, string $prefix = 'rp'): array {
        global $DB;
        $sources = [];
        foreach (self::SOURCES as $source) {
            $sources[] = strtolower($source);
            $sources[] = '\\' . strtolower($source);
        }
        [$insql, $params] = $DB->get_in_or_equal($sources, SQL_PARAMS_NAMED, $prefix . 'src');
        $params[$prefix . 'type'] = base::TYPE_SYSTEM_REPORT;
        $sql = "({$alias}.type = :{$prefix}type AND LOWER({$alias}.source) {$insql})";
        return [$sql, $params];
    }

    /**
     * Delete one user's stored filter values of the plugin's reports.
     *
     * The one cut shared by the privacy requests and the deletion of the account, after which the user context the
     * privacy finder reads is gone.
     *
     * @param int $userid The reader.
     * @return void
     */
    public static function delete_user_filters(int $userid): void {
        global $DB;
        [$isplugins, $params] = self::is_plugins('r');
        $DB->delete_records_select(
            user_filter::TABLE,
            'usercreated = :userid AND reportid IN (SELECT r.id FROM {' . report::TABLE . "} r WHERE {$isplugins})",
            $params + ['userid' => $userid]
        );
    }

    /**
     * Delete the stored filter values of the plugin's reports whose reader's account is deleted or gone.
     *
     * For the accounts deleted before the user_deleted observer removed them: the privacy finder reads the user
     * context, which a deleted account no longer has, so nothing else would ever reach these rows.
     *
     * @return void
     */
    public static function delete_deleted_users_filters(): void {
        global $DB;
        [$isplugins, $params] = self::is_plugins('r');
        // The outer table is named in full: delete_records_select() writes a DELETE with no alias.
        $DB->delete_records_select(
            user_filter::TABLE,
            'reportid IN (SELECT r.id FROM {' . report::TABLE . "} r WHERE {$isplugins})"
            . ' AND NOT EXISTS (SELECT 1 FROM {user} u WHERE u.id = {' . user_filter::TABLE . '}.usercreated'
            . ' AND u.deleted = 0)',
            $params
        );
    }

    /**
     * Delete the report the plugin's page built for one enrolment method, with what hangs on it.
     *
     * @param int $enrolid The enrolment method.
     * @return void
     */
    public static function delete_for_instance(int $enrolid): void {
        self::delete_where(
            'r.component = :component AND r.area = :area AND r.itemid = :itemid',
            ['component' => 'enrol_apply', 'area' => 'method', 'itemid' => $enrolid]
        );
    }

    /**
     * Delete the plugin's reports whose context no longer exists, with what hangs on them.
     *
     * Core deletes no report persistent with its context; a deleted course leaves those of a disabled method (whose
     * delete_instance() core skips) and those core's services built under another component.
     *
     * @return void
     */
    public static function delete_orphans(): void {
        self::delete_where('NOT EXISTS (SELECT 1 FROM {context} ctx WHERE ctx.id = r.contextid)', []);
    }

    /**
     * Delete every report of the plugin, with what hangs on them: the plugin's uninstall.
     *
     * @return void
     */
    public static function delete_all(): void {
        self::delete_where('1 = 1', []);
    }

    /**
     * Delete the plugin's reports matching a condition, each through core's model.
     *
     * @param string $select The condition on {reportbuilder_report} r.
     * @param array $params Its named parameters.
     * @return void
     */
    private static function delete_where(string $select, array $params): void {
        global $DB;
        [$isplugins, $pluginparams] = self::is_plugins('r');
        $ids = $DB->get_fieldset_sql(
            'SELECT r.id FROM {' . report::TABLE . "} r WHERE {$isplugins} AND ({$select})",
            $pluginparams + $params
        );
        foreach ($ids as $id) {
            (new report((int) $id))->delete();
        }
    }
}
