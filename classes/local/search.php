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
 * Matching text the way a person types it, across both database families CI runs.
 *
 * A search box is only useful if "goncalves" finds Gonçalves, and the two families disagree about
 * that by default. MariaDB and MySQL fold accents through the site's ordinary case-insensitive
 * collation, so core's own sql_like() is already accent-insensitive there. PostgreSQL does not, and
 * core says so itself: pgsql_native_moodle_database::sql_like() documents that its
 * $accentsensitive argument has no effect. The gap is closed by the unaccent extension, which this
 * plugin provisions if the database lets it and does without if it does not.
 *
 * So a site gets one of two behaviours and the help string says which. That was decided rather than
 * discovered - "best-effort, documented fallback" - because the alternative is either refusing to
 * install on a least-privilege database or shipping a search that silently means something
 * different per site with nothing on screen saying so.
 *
 * The technique on PostgreSQL follows local_aise, "Accent Insensitive Search Enabler", copyright
 * 2023 Austrian Federal Ministry of Education, GNU GPL v3 or later:
 * https://github.com/Bildungsportal/moodle-local_aise - by way of local_dimensions, which is where
 * this fleet already runs it.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search {
    /**
     * Whether unaccent() may be used in SQL on this site.
     *
     * **Not cached, and the omission is deliberate.** PostgreSQL PHPUnit wraps each test in a
     * transaction it rolls back, so a cached "it is installed" flag outlives the CREATE EXTENSION
     * that set it and the next query references a function that is no longer there. Asking the
     * catalogue is one indexed lookup; resolve it ONCE per query build and pass the answer down,
     * rather than once per searched column.
     *
     * @return bool True when unaccent() is available; always false off PostgreSQL.
     */
    public static function has_unaccent(): bool {
        global $DB;

        if ($DB->get_dbfamily() !== 'postgres') {
            return false;
        }

        return $DB->record_exists_sql("SELECT 1 FROM pg_extension WHERE extname = 'unaccent'");
    }

    /**
     * Install the PostgreSQL unaccent extension if the database account may.
     *
     * This is DDL and belongs to install and upgrade, never to a request. Failure is swallowed on
     * purpose: a least-privilege account cannot create an extension at all, and the right answer
     * there is an accent-sensitive search rather than an install that refuses to finish. Callers
     * learn the outcome from has_unaccent() afterwards, not from an exception on every keystroke.
     *
     * @return bool True when unaccent() can be used in SQL afterwards.
     */
    public static function ensure_unaccent(): bool {
        global $DB;

        if (self::has_unaccent()) {
            return true;
        }

        if ($DB->get_dbfamily() !== 'postgres') {
            return false;
        }

        try {
            $DB->execute('CREATE EXTENSION IF NOT EXISTS unaccent');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * A LIKE fragment matching case- and, where the site allows it, accent-insensitively.
     *
     * $unaccent is a parameter rather than a call to has_unaccent() inside, so that a query
     * searching four columns asks the catalogue once instead of four times. The caller still owns
     * the bound value: run it through sql_like_escape() and add the wildcards, or a percent sign
     * an applicant typed becomes a wildcard. Core's own participants search does not escape, which
     * is a defect to leave behind rather than copy.
     *
     * @param string $field Column or SQL expression to match.
     * @param string $param Bound placeholder, including its colon.
     * @param bool $unaccent Whether unaccent() may be used, from has_unaccent().
     * @return string SQL fragment.
     */
    public static function like_ai(string $field, string $param, bool $unaccent): string {
        global $DB;

        if ($unaccent) {
            return "unaccent({$field}) ILIKE unaccent({$param}) ESCAPE '\\'";
        }

        return $DB->sql_like($field, $param, false, false);
    }
}
