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

/**
 * Per-group seats, when local_groupdist is installed: the only reader of that plugin here.
 *
 * local_groupdist is an optional dependency, absent from version.php on purpose, and reached
 * through its one public class, \local_groupdist\seats; its local\ namespace is internal to it.
 * Without it every group reads as declaring no limit, which is exactly this plugin's
 * behaviour before seats existed.
 *
 * Methods call each other through static:: so a test can stand in for "not installed" by
 * overriding available() in a subclass.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class groupseats {
    /** @var string The provider class, named as a string because it may not exist. */
    protected const PROVIDER = '\local_groupdist\seats';

    /**
     * Whether seats are known at all on this site.
     *
     * @return bool True when local_groupdist and its public reader are installed.
     */
    public static function available(): bool {
        return class_exists(static::PROVIDER);
    }

    /**
     * Declared seats and current members of each group.
     *
     * Members are counted the way local_groupdist counts them (every member, any role, any
     * component), so the number here and the one on its bulk edit page never disagree.
     *
     * @param array $groupids Group ids.
     * @return array Group id => object with ?int 'seats' (null: no limit, or seats unknown)
     *     and ?int 'members' (null when seats are unknown on this site).
     */
    public static function for_groups(array $groupids): array {
        $groupids = array_values(array_unique(array_map('intval', $groupids)));
        if (!$groupids) {
            return [];
        }

        if (!static::available()) {
            return array_fill_keys($groupids, (object) ['seats' => null, 'members' => null]);
        }

        $provider = static::PROVIDER;
        $read = $provider::for_groups($groupids);

        $result = [];
        foreach ($groupids as $groupid) {
            $result[$groupid] = (object) [
                'seats' => isset($read[$groupid]) ? $read[$groupid]->seats : null,
                'members' => isset($read[$groupid]) ? (int) $read[$groupid]->members : 0,
            ];
        }
        return $result;
    }

    /**
     * The groups among these with no seat left.
     *
     * A group is full at members >= seats. A group declaring no limit is never full, and a
     * declared 0 is full from the start: local_groupdist reads an empty value as "no limit"
     * and 0 as a real capacity of zero.
     *
     * @param array $groupids Group ids.
     * @return array Ids of the full groups, in the order given.
     */
    public static function full(array $groupids): array {
        $full = [];
        foreach (static::for_groups($groupids) as $groupid => $group) {
            if ($group->seats === null) {
                continue;
            }
            if ((int) $group->members >= $group->seats) {
                $full[] = (int) $groupid;
            }
        }
        return $full;
    }
}
