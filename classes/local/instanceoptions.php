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
use moodle_exception;
use stdClass;

/**
 * The per-method options kept in enrol_apply_options, read strictly.
 *
 * No row means every option at its default, which is how a method behaved before the table
 * existed: the upgrade writes nothing, and a method created before it needs no row. A stored
 * value outside its option's vocabulary (a restore carries an archive's values, a hand edit
 * anything) reads as that option's default, and {@see set()} refuses one.
 *
 * Nothing is memoised. Cron runs many tasks in one process, and a "who decides" read from a
 * static would outlive the teacher's change of it.
 *
 * Before the upgrade that creates the table has run ({@see schema_ready()}) the reads answer the
 * defaults, the deletion does nothing and a write throws, so a node serving new code against the
 * old schema behaves as the plugin did before.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class instanceoptions {
    /** @var int The applicant chooses no group. */
    public const CHOICE_OFF = 0;

    /** @var int The applicant may choose a group from the offer, or none. */
    public const CHOICE_OPTIONAL = 1;

    /** @var int The applicant must choose a group from the offer. */
    public const CHOICE_REQUIRED = 2;

    /** @var int In separate groups, the deciders of the application's group decide it. */
    public const DECIDERS_GROUP = 0;

    /** @var int Only those who see every group decide the method's applications. */
    public const DECIDERS_ALLGROUPS = 1;

    /** @var string The lock factory type serialising the first write of a method's row. */
    public const LOCK_TYPE = 'enrol_apply_options';

    /**
     * Each option's values, the default first.
     *
     * The order is the contract: a value read outside its list becomes the list's first entry.
     *
     * @var array
     */
    protected const VOCABULARY = [
        'groupchoice' => [self::CHOICE_OFF, self::CHOICE_OPTIONAL, self::CHOICE_REQUIRED],
        'deciders' => [self::DECIDERS_GROUP, self::DECIDERS_ALLGROUPS],
        'groupseats' => [1, 0],
        'hidecard' => [0, 1],
    ];

    /**
     * Every option at its default.
     *
     * @return stdClass One int property per option.
     */
    public static function defaults(): stdClass {
        $defaults = new stdClass();
        foreach (self::VOCABULARY as $name => $values) {
            $defaults->$name = $values[0];
        }
        return $defaults;
    }

    /**
     * The options of one method.
     *
     * @param int $enrolid Enrol instance id.
     * @return stdClass One int property per option; the defaults where no row exists.
     */
    public static function get(int $enrolid): stdClass {
        global $DB;

        if (!self::schema_ready()) {
            return self::defaults();
        }
        $row = $DB->get_record('enrol_apply_options', ['enrolid' => $enrolid]);
        return $row ? self::normalise($row) : self::defaults();
    }

    /**
     * Read values the way a stored row is read: each option in its vocabulary, or its default.
     *
     * Properties that are not options are dropped, so a restore can pass an archive's element
     * through whole.
     *
     * @param stdClass|array $values Values keyed by option name, ints or digit strings.
     * @return stdClass One int property per option.
     */
    public static function normalise($values): stdClass {
        $values = (array) $values;
        $result = self::defaults();
        foreach (self::VOCABULARY as $name => $allowed) {
            $value = self::parse($values[$name] ?? null);
            if ($value !== null && in_array($value, $allowed, true)) {
                $result->$name = $value;
            }
        }
        return $result;
    }

    /**
     * Change some of a method's options, leaving the others as they are.
     *
     * The first write of a method inserts its row under a lock, so two saves of a new method
     * do not both insert; the unique key on enrolid refuses a second row in any case, which a
     * caller inside an open transaction would meet as an exception rather than as a duplicate.
     *
     * @param int $enrolid Enrol instance id; it must be an apply instance.
     * @param array $values Option name => value, an int or a digit string in its vocabulary.
     * @return void
     * @throws coding_exception When the upgrade has not run, the instance is not an apply instance, or an
     *     option is unknown.
     * @throws invalid_parameter_exception When a value is outside its option's vocabulary.
     * @throws moodle_exception When the lock is not obtained in time.
     */
    public static function set(int $enrolid, array $values): void {
        global $DB;

        if (!self::schema_ready()) {
            throw new coding_exception('The enrol_apply upgrade that creates its options has not run yet.');
        }
        if (!$DB->record_exists('enrol', ['id' => $enrolid, 'enrol' => 'apply'])) {
            throw new coding_exception('Options belong to an apply enrolment instance, and ' . $enrolid . ' is not one.');
        }

        $clean = [];
        foreach ($values as $name => $value) {
            if (!array_key_exists($name, self::VOCABULARY)) {
                throw new coding_exception('Unknown enrol_apply option: ' . $name);
            }
            $parsed = self::parse($value);
            if ($parsed === null || !in_array($parsed, self::VOCABULARY[$name], true)) {
                throw new invalid_parameter_exception('Value outside the vocabulary of the option ' . $name);
            }
            $clean[$name] = $parsed;
        }

        $lock = \core\lock\lock_config::get_lock_factory(self::LOCK_TYPE)->get_lock('enrol_' . $enrolid, 5);
        if (!$lock) {
            throw new moodle_exception('locktimeout');
        }
        try {
            $row = $DB->get_record('enrol_apply_options', ['enrolid' => $enrolid]);
            if ($row) {
                $DB->update_record('enrol_apply_options', (object) (['id' => $row->id, 'timemodified' => time()] + $clean));
            } else {
                $DB->insert_record('enrol_apply_options', (object) (
                    ['enrolid' => $enrolid, 'timemodified' => time()] + $clean + (array) self::defaults()
                ));
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Forget a method's options, as its deletion does.
     *
     * @param int $enrolid Enrol instance id.
     * @return void
     */
    public static function delete(int $enrolid): void {
        global $DB;

        if (!self::schema_ready()) {
            return;
        }
        $DB->delete_records('enrol_apply_options', ['enrolid' => $enrolid]);
    }

    /**
     * Whether the upgrade that creates the options and the offer has run on this site.
     *
     * @return bool True once it has.
     */
    public static function schema_ready(): bool {
        global $CFG;

        // ENROL_APPLY_OPTIONS_SCHEMA lives in lib.php, which is not autoloaded.
        require_once($CFG->dirroot . '/enrol/apply/lib.php');
        return enrol_get_plugin('apply')->options_schema_ready();
    }

    /**
     * A value as an option integer, or null when it is not one.
     *
     * Only an int or a string of digits: (int) would read '1abc' as 1 and an array as 1.
     *
     * @param mixed $value Value from a row, a form or an archive.
     * @return int|null The integer, or null.
     */
    protected static function parse($value): ?int {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d{1,9}$/', $value)) {
            return (int) $value;
        }
        return null;
    }
}
