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
 * The reader's grid on the course report: which columns, and how wide (AR-D8, AR-D13).
 *
 * One user preference for the whole site, so a reader's grid is the same on every course and on
 * every node. It holds keys of the report's column catalogue
 * ({@see \enrol_apply\reportbuilder\local\systemreports\course_applications::column_catalogue()}),
 * never column definitions: the report reads it in initialise() and adds only the catalogue's
 * columns it names, so a forged or stale key matches nothing and no column the reader may not see
 * can be asked for. Keys are cleaned here for their shape alone, because the catalogue differs by
 * course (each course asks its own questions) and the preference is not a course's.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reportpreference {
    /** @var string The user preference's name. */
    public const NAME = 'enrol_apply_report_columns';

    /** @var int The most keys either list keeps; a course with more questions than this is not a reader's grid. */
    public const MAX_KEYS = 200;

    /** @var int The narrowest width kept, in pixels; the client applies each column's own, larger minimum. */
    public const MIN_WIDTH = 48;

    /** @var int The widest width kept, in pixels. */
    public const MAX_WIDTH = 1600;

    /**
     * Whether a string has the shape of a catalogue key.
     *
     * A plain word (outcome, wait, answers, ...), an identity column ("identity:email") or a
     * question ("answer:" and a field key, {@see fields::standard_key()} and
     * {@see answers::key()}). The shape only: whether the key names a column is the report's call.
     *
     * @param string $key The key.
     * @return bool True when the key could name a column.
     */
    public static function valid_key(string $key): bool {
        return preg_match('/^(?:[a-z]{1,20}|identity:[a-z0-9_]{1,40}|answer:[sca]_[a-z0-9_]{1,60})$/', $key) === 1;
    }

    /**
     * The reader's grid as stored, cleaned.
     *
     * Read leniently: anything that is not the stored shape reads as no choice, because a broken
     * preference must leave the report at its default columns rather than break it.
     *
     * @param int|null $userid The reader, the current user when null.
     * @return array 'columns' => the chosen keys, or null for the default set; 'widths' => key => pixels.
     */
    public static function read(?int $userid = null): array {
        global $USER;

        $raw = get_user_preferences(self::NAME, null, $userid ?? (int) $USER->id);
        $stored = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($stored)) {
            return self::clean(null, []);
        }

        $columns = array_key_exists('columns', $stored) && is_array($stored['columns']) ? $stored['columns'] : null;
        $widths = isset($stored['widths']) && is_array($stored['widths']) ? $stored['widths'] : [];

        return self::clean($columns, $widths);
    }

    /**
     * Store the reader's grid, cleaned, and return what was stored.
     *
     * Nothing chosen and no width set removes the preference, so a reader who resets everything
     * leaves no row behind.
     *
     * @param array|null $columns The chosen keys, or null for the default set.
     * @param array $widths Key => pixels; empty for the default widths.
     * @param int|null $userid The reader, the current user when null.
     * @return array What read() would now return.
     */
    public static function write(?array $columns, array $widths, ?int $userid = null): array {
        global $USER;

        $userid = $userid ?? (int) $USER->id;
        $clean = self::clean($columns, $widths);
        if ($clean['columns'] === null && $clean['widths'] === []) {
            unset_user_preference(self::NAME, $userid);
        } else {
            set_user_preference(self::NAME, json_encode($clean), $userid);
        }

        return $clean;
    }

    /**
     * Keep the keys that have a key's shape, once each, and the widths that are whole pixels.
     *
     * @param array|null $columns The chosen keys, or null.
     * @param array $widths Key => pixels.
     * @return array 'columns' => list or null; 'widths' => key => pixels, clamped to the bounds.
     */
    public static function clean(?array $columns, array $widths): array {
        $keys = null;
        if ($columns !== null) {
            $keys = [];
            foreach ($columns as $key) {
                if (is_string($key) && self::valid_key($key) && !in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
            $keys = array_slice($keys, 0, self::MAX_KEYS);
        }

        $sizes = [];
        foreach ($widths as $key => $width) {
            if (!is_string($key) || !self::valid_key($key) || !is_numeric($width)) {
                continue;
            }
            $sizes[$key] = max(self::MIN_WIDTH, min(self::MAX_WIDTH, (int) round((float) $width)));
            if (count($sizes) >= self::MAX_KEYS) {
                break;
            }
        }

        return ['columns' => $keys, 'widths' => $sizes];
    }
}
