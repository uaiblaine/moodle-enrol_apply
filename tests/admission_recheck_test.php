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

namespace enrol_apply;

use enrol_apply\local\groupkey;
use enrol_apply\local\keyattempts;
use enrol_apply\local\submission;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass target below resolves when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/apply/tests/fixtures/fake_lock_factory.php');

/**
 * Tests for what the write path judges again once it holds the admission locks, and for the lock a
 * method with an applicant limit takes (audit M-03), and for the placeholders of a notice (B-05.3).
 *
 * The race itself, two requests at once, has no gate here: it was reproduced on the mini cluster.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_plugin::class)]
final class admission_recheck_test extends \advanced_testcase {
    /** @var string The key of the keyed group. */
    private const KEY = 'Turma-B-2027';

    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass The apply instance. */
    private $instance;

    /** @var \stdClass The group the key names. */
    private $keyed;

    /**
     * A course with a key-only instance that admits, and a keyed group.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($this->course, [
            'customchar1' => groupkey::MODE_REQUIRED,
            'customtext3' => '$@ALL@$',
        ] + $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $this->keyed = $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'B',
            'enrolmentkey' => self::KEY,
        ]);
    }

    /**
     * End the simulated request: the wrong-key throttle holds its lock until then.
     *
     * @return void
     */
    protected function tearDown(): void {
        keyattempts::release_all();
        parent::tearDown();
    }

    /**
     * A plugin that changes the instance row while the admission locks are awaited, as a teacher would.
     *
     * @param array $changes Column => value written to the enrol row before the locks are taken.
     * @return \enrol_apply_plugin The plugin.
     */
    private function plugin_changing(array $changes): \enrol_apply_plugin {
        return new class ($changes) extends \enrol_apply_plugin {
            /** @var array Columns written before the locks. */
            private $changes;

            /**
             * Keep what to change.
             *
             * @param array $changes Column => value.
             */
            public function __construct(array $changes) {
                $this->changes = $changes;
            }

            /**
             * Make the change, then take the locks.
             *
             * @param \stdClass $instance Course enrol instance.
             * @param array $groupids Group ids.
             * @return array|null The locks.
             */
            protected function lock_admission(\stdClass $instance, array $groupids): ?array {
                global $DB;

                foreach ($this->changes as $column => $value) {
                    $DB->set_field('enrol', $column, $value, ['id' => $instance->id]);
                }

                return parent::lock_admission($instance, $groupids);
            }
        };
    }

    /**
     * Submit an application with the key as the admin, the way a caller other than the form would.
     *
     * @param \enrol_apply_plugin $plugin The plugin to submit through.
     * @param \stdClass $applicant The applicant.
     * @return \enrol_apply\local\application_result The result.
     */
    private function submit(\enrol_apply_plugin $plugin, \stdClass $applicant): \enrol_apply\local\application_result {
        $this->setAdminUser();
        $sink = $this->redirectMessages();
        $result = $plugin->submit_application($this->instance, (int) $applicant->id, (object) [
            'applydescription' => 'Please',
            'groupkey' => self::KEY,
        ]);
        $sink->close();

        return $result;
    }

    /**
     * A key-only mode switched to review while the locks were awaited sends the key to the queue.
     *
     * The control is the same submission with nothing changed, which admits.
     *
     * Changes that must make it fail: the mode no longer re-tested once the locks are held.
     *
     * @return void
     */
    public function test_a_mode_switched_to_review_meanwhile_does_not_admit(): void {
        $control = $this->getDataGenerator()->create_user();
        $result = $this->submit($this->plugin_changing([]), $control);
        $this->assertTrue($result->was_admitted(), 'the premise: this key admits');

        $applicant = $this->getDataGenerator()->create_user();
        $result = $this->submit($this->plugin_changing(['customchar1' => groupkey::MODE_REVIEW]), $applicant);

        $this->assertTrue($result->was_created());
        $this->assertFalse($result->was_admitted());
        $this->assertSame(submission::KEY_FOR_REVIEW, $result->key_outcome());
    }

    /**
     * An instance disabled while the locks were awaited refuses a valid key, and writes nothing.
     *
     * Changes that must make it fail: the eligibility no longer judged on the instance as it is
     * once the locks are held.
     *
     * @return void
     */
    public function test_an_instance_disabled_meanwhile_refuses(): void {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();

        $result = $this->submit($this->plugin_changing(['status' => ENROL_INSTANCE_DISABLED]), $applicant);

        $this->assertTrue($result->is_refusal());
        $this->assertSame(get_string('cantenrol', 'enrol_apply'), $result->reason());
        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $applicant->id]));
        $this->assertFalse($DB->record_exists('enrol_apply_submission', ['userid' => $applicant->id]));
    }

    /**
     * A place taken, or the limit lowered, while the locks were awaited is read as it is now.
     *
     * The control is the first applicant, admitted into the method's one place before the change.
     *
     * Changes that must make it fail: the places counted on the method as the form read it.
     *
     * @return void
     */
    public function test_a_limit_lowered_meanwhile_refuses_for_want_of_a_place(): void {
        global $DB;

        $control = $this->getDataGenerator()->create_user();
        $this->assertTrue($this->submit($this->plugin, $control)->was_admitted(), 'the premise: the key admits');

        $applicant = $this->getDataGenerator()->create_user();
        $result = $this->submit($this->plugin_changing(['customint4' => 1]), $applicant);

        $this->assertTrue($result->is_refusal());
        $this->assertSame(get_string('keynoplace', 'enrol_apply'), $result->reason());
        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $applicant->id]));
    }

    /**
     * A limit set after the caller read the method is honoured: the method is read again under the locks.
     *
     * The object passed in says there is no limit; the database says one application. The control is the
     * first applicant, who is accepted while the limit is not yet set.
     *
     * Changes that must make it fail: the limit, the window or the status judged on the caller's object.
     *
     * @return void
     */
    public function test_a_limit_set_after_the_object_was_read_still_refuses(): void {
        global $DB;

        $DB->set_field('enrol', 'customchar1', groupkey::MODE_OFF, ['id' => $this->instance->id]);
        $this->instance->customchar1 = groupkey::MODE_OFF;
        $this->assertTrue($this->submit($this->plugin, $this->getDataGenerator()->create_user())->was_created());

        $DB->set_field('enrol', 'customint3', 1, ['id' => $this->instance->id]);
        $this->assertEquals(0, $this->instance->customint3, 'the premise: the object is stale');
        $applicant = $this->getDataGenerator()->create_user();

        $result = $this->submit($this->plugin, $applicant);

        $this->assertTrue($result->is_refusal());
        $this->assertSame(get_string('maxenrolledreached', 'enrol_apply'), $result->reason());
        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $applicant->id]));
    }

    /**
     * The requests for the method's admission lock, from the fake factory.
     *
     * @return array The resources asked for under the method's name.
     */
    private function instance_lock_requests(): array {
        return array_values(array_filter(
            \enrol_apply\fake_lock_factory::requests_for('enrol_apply_keyadmission'),
            static fn(string $resource): bool => str_starts_with($resource, 'instance_')
        ));
    }

    /**
     * A method with an applicant limit takes the method's admission lock, once; one without does not.
     *
     * Without a key only submit_application() can take it, so the first two cases tell the lock of
     * the limit from the one a key admission takes. A key admission on a limited method also takes
     * it through lock_admission(), which must not ask a second time: the lock is not reentrant, so
     * a second request would be refused.
     *
     * Changes that must make it fail: the lock not taken for a limited method, taken for an
     * unlimited one, or asked for twice.
     *
     * @return void
     */
    public function test_a_limited_method_takes_the_instance_lock_once(): void {
        global $DB;

        $resource = 'instance_' . $this->instance->id;
        $DB->set_field('enrol', 'customchar1', groupkey::MODE_OFF, ['id' => $this->instance->id]);
        $this->instance->customchar1 = groupkey::MODE_OFF;

        \enrol_apply\fake_lock_factory::install();
        $result = $this->submit($this->plugin, $this->getDataGenerator()->create_user());
        $this->assertTrue($result->was_created());
        $this->assertSame([], $this->instance_lock_requests(), 'the control: no limit, no lock');

        $DB->set_field('enrol', 'customint3', 5, ['id' => $this->instance->id]);
        $this->instance->customint3 = 5;
        \enrol_apply\fake_lock_factory::install();
        $result = $this->submit($this->plugin, $this->getDataGenerator()->create_user());
        $this->assertTrue($result->was_created());
        $this->assertSame([$resource], $this->instance_lock_requests());
        $this->assertSame([], \enrol_apply\fake_lock_factory::$taken, 'every lock is released');

        $DB->set_field('enrol', 'customchar1', groupkey::MODE_REQUIRED, ['id' => $this->instance->id]);
        $this->instance->customchar1 = groupkey::MODE_REQUIRED;
        \enrol_apply\fake_lock_factory::install();
        $result = $this->submit($this->plugin, $this->getDataGenerator()->create_user());
        $this->assertTrue($result->was_admitted(), 'the lock submit_application() holds is not asked for again');
        $this->assertSame([$resource], $this->instance_lock_requests());
        $this->assertSame([], \enrol_apply\fake_lock_factory::$taken, 'every lock is released');
    }

    /**
     * A limited method whose lock another request holds refuses, and writes nothing.
     *
     * Changes that must make it fail: the limited method proceeding without the lock.
     *
     * @return void
     */
    public function test_a_limited_method_whose_lock_is_held_refuses(): void {
        global $DB;

        $DB->set_field('enrol', 'customint3', 5, ['id' => $this->instance->id]);
        $this->instance->customint3 = 5;
        \enrol_apply\fake_lock_factory::install();
        \enrol_apply\fake_lock_factory::$taken['enrol_apply_keyadmission/instance_' . $this->instance->id] = true;
        $applicant = $this->getDataGenerator()->create_user();

        $result = $this->submit($this->plugin, $applicant);

        $this->assertTrue($result->is_refusal());
        $this->assertSame(get_string('applylimitlocked', 'enrol_apply'), $result->reason());
        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $applicant->id]));
        $this->assertCount(1, \enrol_apply\fake_lock_factory::$taken, 'only the other request still holds its lock');
    }

    /**
     * A placeholder inside a substituted value is not expanded by a later one.
     *
     * The control is the same template with an ordinary name.
     *
     * Changes that must make it fail: the placeholders replaced one after another.
     *
     * @return void
     */
    public function test_a_placeholder_in_a_first_name_is_not_expanded(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'R&D course']);
        $method = new \ReflectionMethod($this->plugin, 'update_mail_content');
        $render = fn(string $first) => $method->invoke(
            $this->plugin,
            '{firstname} {lastname} / {content}',
            $course,
            (object) ['firstname' => $first, 'lastname' => 'Silva', 'username' => 'u'],
            (object) ['timeend' => 0]
        );

        $this->assertSame('Ana Silva / R&amp;D course', $render('Ana'));
        $this->assertSame('{lastname} Silva / R&amp;D course', $render('{lastname}'));
    }
}
