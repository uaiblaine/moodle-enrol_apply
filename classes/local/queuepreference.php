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
 * The reader's grid on the approval queue: which columns, and how wide (CQ-D7).
 *
 * A user preference of its own, apart from the course report's ({@see reportpreference}): the two
 * grids offer different columns to different readers, and a choice made on one must not empty the
 * other. One preference for the whole site, so a reader's grid is the same on every method and on
 * every node. It holds keys of the queue's column catalogue ({@see queueengine::catalogue()}),
 * never column definitions: the engine intersects them with the catalogue it builds for the reader
 * on every request, so a forged or stale key matches nothing and no column the reader may not see
 * can be asked for. Keys are cleaned for their shape alone, by the report's own rule
 * ({@see reportpreference::clean()}), because the catalogue differs by method.
 *
 * The queues that span courses, the site-wide and the mentee queue (enrolid 0), keep a preference of
 * their own (NAME_WIDE, SQ-D11): their columns are not a method's, so a choice made on one kind of
 * queue must not empty the other. Same shape, same cleaning, intersected on every read with the
 * catalogue of the queue it is read for.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class queuepreference {
    /** @var string The user preference's name, for the queue of one method. */
    public const NAME = 'enrol_apply_queue_grid';

    /** @var string The user preference's name, for the queues that span courses (enrolid 0). */
    public const NAME_WIDE = 'enrol_apply_queue_grid_wide';

    /**
     * The preference a queue's grid is kept in.
     *
     * @param bool $wide Whether the queue spans courses (enrolid 0).
     * @return string The user preference's name.
     */
    public static function name(bool $wide): string {
        return $wide ? self::NAME_WIDE : self::NAME;
    }

    /**
     * The reader's grid as stored, cleaned.
     *
     * Read leniently: anything that is not the stored shape reads as no choice, because a broken
     * preference must leave the queue at its default columns rather than break it.
     *
     * @param int|null $userid The reader, the current user when null.
     * @param bool $wide Whether the grid is the queues' that span courses (enrolid 0).
     * @return array 'columns' => the chosen keys, or null for the default set; 'widths' => key => pixels.
     */
    public static function read(?int $userid = null, bool $wide = false): array {
        global $USER;

        $raw = get_user_preferences(self::name($wide), null, $userid ?? (int) $USER->id);
        $stored = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($stored)) {
            return reportpreference::clean(null, []);
        }

        $columns = array_key_exists('columns', $stored) && is_array($stored['columns']) ? $stored['columns'] : null;
        $widths = isset($stored['widths']) && is_array($stored['widths']) ? $stored['widths'] : [];

        return reportpreference::clean($columns, $widths);
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
     * @param bool $wide Whether the grid is the queues' that span courses (enrolid 0).
     * @return array What read() would now return.
     */
    public static function write(?array $columns, array $widths, ?int $userid = null, bool $wide = false): array {
        global $USER;

        $userid = $userid ?? (int) $USER->id;
        $clean = reportpreference::clean($columns, $widths);
        if ($clean['columns'] === null && $clean['widths'] === []) {
            unset_user_preference(self::name($wide), $userid);
        } else {
            set_user_preference(self::name($wide), json_encode($clean), $userid);
        }

        return $clean;
    }

    /**
     * The grid in the shape the web services return it.
     *
     * @param array $stored What read() or write() returned.
     * @return array 'usedefault', 'columns' (keys), 'widths' (each: key, width).
     */
    public static function export(array $stored): array {
        $widths = [];
        foreach ($stored['widths'] as $key => $width) {
            $widths[] = ['key' => $key, 'width' => $width];
        }

        return [
            'usedefault' => $stored['columns'] === null,
            'columns' => $stored['columns'] ?? [],
            'widths' => $widths,
        ];
    }
}
