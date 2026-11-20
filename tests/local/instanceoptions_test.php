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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/tests/fixtures/fake_lock_factory.php');

/**
 * Tests for the per-method options.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(instanceoptions::class)]
final class instanceoptions_test extends \advanced_testcase {
    /** @var int An apply instance. */
    private $enrolid;

    /**
     * An apply instance on a fresh course.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $plugin = enrol_get_plugin('apply');
        $course = $this->getDataGenerator()->create_course();
        $this->enrolid = (int) $plugin->add_instance($course, $plugin->get_instance_defaults());
    }

    /**
     * A method without a row reads every option at its default, which is today's behaviour.
     *
     * Changes that must make it fail: a default other than "no choice, the group's deciders,
     * seats apply, card shown", which would change every method that exists at the upgrade.
     *
     * @return void
     */
    public function test_a_method_without_a_row_reads_the_defaults(): void {
        global $DB;

        $this->assertFalse($DB->record_exists('enrol_apply_options', ['enrolid' => $this->enrolid]));
        $this->assertEquals((object) [
            'groupchoice' => instanceoptions::CHOICE_OFF,
            'deciders' => instanceoptions::DECIDERS_GROUP,
            'groupseats' => 1,
            'hidecard' => 0,
        ], instanceoptions::get($this->enrolid));
    }

    /**
     * The first write inserts one row, the next changes only what it names.
     *
     * @return void
     */
    public function test_set_writes_one_row_and_keeps_the_options_it_does_not_name(): void {
        global $DB;

        instanceoptions::set($this->enrolid, ['deciders' => instanceoptions::DECIDERS_ALLGROUPS]);
        instanceoptions::set($this->enrolid, ['hidecard' => '1', 'groupseats' => 0]);

        $this->assertSame(1, $DB->count_records('enrol_apply_options', ['enrolid' => $this->enrolid]));
        $this->assertEquals((object) [
            'groupchoice' => instanceoptions::CHOICE_OFF,
            'deciders' => instanceoptions::DECIDERS_ALLGROUPS,
            'groupseats' => 0,
            'hidecard' => 1,
        ], instanceoptions::get($this->enrolid));
    }

    /**
     * A stored value outside its option's vocabulary reads as the option's default.
     *
     * Changes that must make it fail: reading a stored value without the vocabulary check, so
     * that a restore or a hand edit could put any integer in force.
     *
     * @return void
     */
    public function test_a_stored_value_outside_the_vocabulary_reads_as_the_default(): void {
        global $DB;

        instanceoptions::set($this->enrolid, ['deciders' => 1, 'hidecard' => 1]);
        $DB->set_field('enrol_apply_options', 'groupchoice', 7, ['enrolid' => $this->enrolid]);
        $DB->set_field('enrol_apply_options', 'groupseats', -1, ['enrolid' => $this->enrolid]);

        $options = instanceoptions::get($this->enrolid);

        $this->assertSame(instanceoptions::CHOICE_OFF, $options->groupchoice);
        $this->assertSame(1, $options->groupseats);
        // The control: the values inside their vocabulary are still read.
        $this->assertSame(instanceoptions::DECIDERS_ALLGROUPS, $options->deciders);
        $this->assertSame(1, $options->hidecard);
    }

    /**
     * Values that are not an integer of the vocabulary.
     *
     * @return array Name => [value].
     */
    public static function values_outside_the_vocabulary(): array {
        return [
            'an integer outside it' => [2],
            'digits followed by text' => ['1abc'],
            'a boolean' => [true],
            'a float' => [1.0],
            'null' => [null],
            'an empty string' => [''],
            'a leading space' => [' 1'],
            'a negative string' => ['-1'],
            'an array' => [[1]],
        ];
    }

    /**
     * A write refuses a value that is not an integer of the vocabulary, and writes nothing.
     *
     * Changes that must make it fail: casting with (int) instead of parsing, which reads '1abc',
     * true and [1] as 1.
     *
     * @param mixed $value The value written.
     * @return void
     */
    #[DataProvider('values_outside_the_vocabulary')]
    public function test_set_refuses_a_value_outside_the_vocabulary($value): void {
        global $DB;

        try {
            instanceoptions::set($this->enrolid, ['deciders' => $value]);
            $this->fail('A value outside the vocabulary was accepted.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertFalse($DB->record_exists('enrol_apply_options', ['enrolid' => $this->enrolid]));
        }
    }

    /**
     * A write refuses an option name it does not know.
     *
     * @return void
     */
    public function test_set_refuses_an_unknown_option(): void {
        $this->expectException(\coding_exception::class);
        instanceoptions::set($this->enrolid, ['enrolid' => 5]);
    }

    /**
     * A write refuses an instance of another enrolment method, which a restore's mapping onto the
     * manual instance would otherwise reach.
     *
     * @return void
     */
    public function test_set_refuses_an_instance_of_another_method(): void {
        global $DB;

        $manualid = (int) $DB->get_field('enrol', 'id', ['enrol' => 'manual'], IGNORE_MULTIPLE);
        $this->assertNotEmpty($manualid, 'the premise: the generator gave the course a manual instance');

        try {
            instanceoptions::set($manualid, ['deciders' => 1]);
            $this->fail('An instance of another method was written to.');
        } catch (\coding_exception $e) {
            $this->assertFalse($DB->record_exists('enrol_apply_options', ['enrolid' => $manualid]));
        }
    }

    /**
     * A write whose lock is not obtained throws and writes nothing.
     *
     * @return void
     */
    public function test_a_lock_not_obtained_writes_nothing(): void {
        global $DB;

        \enrol_apply\fake_lock_factory::install([instanceoptions::LOCK_TYPE]);
        try {
            instanceoptions::set($this->enrolid, ['deciders' => 1]);
            $this->fail('A write went ahead without its lock.');
        } catch (\moodle_exception $e) {
            $this->assertSame('locktimeout', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('enrol_apply_options', ['enrolid' => $this->enrolid]));

        // The control: with the lock free the same write goes ahead, and releases it.
        \enrol_apply\fake_lock_factory::$refused = [];
        instanceoptions::set($this->enrolid, ['deciders' => 1]);
        $this->assertSame(1, instanceoptions::get($this->enrolid)->deciders);
        $this->assertSame([], \enrol_apply\fake_lock_factory::$taken);
    }

    /**
     * Normalising keeps only the options, so a restore can pass an archive element whole.
     *
     * @return void
     */
    public function test_normalise_keeps_only_the_options(): void {
        $normalised = instanceoptions::normalise(['id' => '4', 'enrolid' => '9', 'deciders' => '1', 'hidecard' => 'yes']);

        $this->assertEquals((object) [
            'groupchoice' => instanceoptions::CHOICE_OFF,
            'deciders' => instanceoptions::DECIDERS_ALLGROUPS,
            'groupseats' => 1,
            'hidecard' => 0,
        ], $normalised);
    }

    /**
     * Deleting forgets the method's row, and only that one.
     *
     * @return void
     */
    public function test_delete_forgets_only_this_method(): void {
        $plugin = enrol_get_plugin('apply');
        $otherid = (int) $plugin->add_instance($this->getDataGenerator()->create_course(), $plugin->get_instance_defaults());
        instanceoptions::set($this->enrolid, ['deciders' => 1]);
        instanceoptions::set($otherid, ['deciders' => 1]);

        instanceoptions::delete($this->enrolid);

        $this->assertSame(instanceoptions::DECIDERS_GROUP, instanceoptions::get($this->enrolid)->deciders);
        $this->assertSame(instanceoptions::DECIDERS_ALLGROUPS, instanceoptions::get($otherid)->deciders);
    }

    /**
     * The schema version is the one whose upgrade step creates the table, and the code is at it.
     *
     * Changes that must make it fail: the constant naming another step, so that the paths it
     * guards would touch the table before the step that creates it, or stay away after.
     *
     * @return void
     */
    public function test_the_schema_version_is_the_step_that_creates_the_table(): void {
        global $CFG;

        require_once($CFG->dirroot . '/enrol/apply/lib.php');
        $upgrade = file_get_contents($CFG->dirroot . '/enrol/apply/db/upgrade.php');
        $this->assertSame(1, preg_match(
            '/if \(\$oldversion < (\d+)\) \{(?:(?!if \(\$oldversion).)*xmldb_table\(\'enrol_apply_options\'\)/s',
            $upgrade,
            $matches
        ));
        $this->assertSame(ENROL_APPLY_OPTIONS_SCHEMA, (int) $matches[1]);

        $plugin = new \stdClass();
        require($CFG->dirroot . '/enrol/apply/version.php');
        $this->assertGreaterThanOrEqual(ENROL_APPLY_OPTIONS_SCHEMA, $plugin->version);
    }

    /**
     * Before the upgrade has run, a stored row is not read, nothing is deleted and a write throws.
     *
     * Changes that must make it fail: any of the three gates dropped, which on a node serving
     * this code before the upgrade reads or writes a table that does not exist yet.
     *
     * @return void
     */
    public function test_before_the_upgrade_the_options_are_neither_read_nor_written(): void {
        global $DB;

        instanceoptions::set($this->enrolid, ['deciders' => 1]);
        set_config('version', ENROL_APPLY_OPTIONS_SCHEMA - 1, 'enrol_apply');
        $this->assertFalse(instanceoptions::schema_ready(), 'the premise');

        $this->assertEquals(instanceoptions::defaults(), instanceoptions::get($this->enrolid));
        instanceoptions::delete($this->enrolid);
        $this->assertTrue($DB->record_exists('enrol_apply_options', ['enrolid' => $this->enrolid]));
        try {
            instanceoptions::set($this->enrolid, ['hidecard' => 1]);
            $this->fail('A write went ahead before the upgrade.');
        } catch (\coding_exception $e) {
            $this->assertSame(0, (int) $DB->get_field('enrol_apply_options', 'hidecard', ['enrolid' => $this->enrolid]));
        }

        // The control: once the stored version reaches the step, the same row is read.
        set_config('version', ENROL_APPLY_OPTIONS_SCHEMA, 'enrol_apply');
        $this->assertSame(instanceoptions::DECIDERS_ALLGROUPS, instanceoptions::get($this->enrolid)->deciders);
    }
}
