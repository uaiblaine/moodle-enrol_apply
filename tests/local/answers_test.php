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

/**
 * Tests for the answers to an instance's own application questions.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use core_customfield\field_controller;
use enrol_apply\customfield\application_handler;
use enrol_apply\form\application_form;
use enrol_apply\reportbuilder\local\formatters\submission as submission_formatter;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the answers to an instance's own application questions.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(answers::class)]
#[CoversClass(submission::class)]
#[CoversClass(application_form::class)]
final class answers_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass The apply instance. */
    private $instance;

    /** @var int The instance's own category. */
    private $categoryid;

    /**
     * A course with an apply instance and an empty category of questions.
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
        $enrolid = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        $this->categoryid = $this->create_category('enrol_apply', application_handler::AREA, (int) $enrolid);
    }

    /**
     * Create a custom field category.
     *
     * @param string $component Component.
     * @param string $area Area.
     * @param int $itemid Item id.
     * @return int The category id.
     */
    private function create_category(string $component, string $area, int $itemid): int {
        return (int) $this->generator()->create_category([
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
        ])->get('id');
    }

    /**
     * The core custom field generator.
     *
     * @return \core_customfield_generator
     */
    private function generator(): \core_customfield_generator {
        return $this->getDataGenerator()->get_plugin_generator('core_customfield');
    }

    /**
     * Create a question in the instance's category, or in the given one.
     *
     * @param string $type Field type.
     * @param string $shortname Shortname.
     * @param array $config Configdata.
     * @param int|null $categoryid Category, the instance's own by default.
     * @param string|null $name Field name.
     * @return field_controller
     */
    private function create_field(
        string $type,
        string $shortname,
        array $config = [],
        ?int $categoryid = null,
        ?string $name = null
    ): field_controller {
        return $this->generator()->create_field([
            'categoryid' => $categoryid ?? $this->categoryid,
            'type' => $type,
            'shortname' => $shortname,
            'name' => $name ?? ucfirst($shortname),
            'configdata' => $config,
        ]);
    }

    /**
     * The snapshot entry of one field, or null.
     *
     * @param array $values Snapshot entries.
     * @param field_controller $field The field.
     * @return array|null
     */
    private function entry(array $values, field_controller $field): ?array {
        foreach ($values as $entry) {
            if ($entry['key'] === answers::key((int) $field->get('id'))) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * Each type freezes its plain display value, under the field's own key and label.
     *
     * @return void
     */
    public function test_each_type_freezes_a_plain_display_value(): void {
        $text = $this->create_field('text', 'motivation', [], null, 'R&D < motivation');
        $select = $this->create_field('select', 'shift', ['options' => "Morning\nR&D < Night", 'defaultvalue' => '']);
        $yes = $this->create_field('checkbox', 'agree');
        $no = $this->create_field('checkbox', 'newsletter');
        $date = $this->create_field('date', 'start', ['includetime' => 0]);
        $number = $this->create_field('number', 'years', ['decimalplaces' => 1, 'display' => '{value} y']);

        $timestamp = make_timestamp(2026, 10, 12);
        $values = answers::submitted_values($this->instance, (object) [
            'customfield_motivation' => '  A<B and R&D  ',
            'customfield_shift' => 2,
            'customfield_agree' => 1,
            'customfield_newsletter' => 0,
            'customfield_start' => $timestamp,
            'customfield_years' => '3.25',
        ]);

        $this->assertSame(
            ['key' => 'a_' . $text->get('id'), 'label' => 'R&D < motivation', 'value' => 'A<B and R&D'],
            $this->entry($values, $text)
        );
        $this->assertSame('R&D < Night', $this->entry($values, $select)['value']);
        $this->assertSame(get_string('yes'), $this->entry($values, $yes)['value']);
        $this->assertSame(get_string('no'), $this->entry($values, $no)['value']);
        $this->assertSame(userdate($timestamp, get_string('strftimedate', 'langconfig')), $this->entry($values, $date)['value']);
        $this->assertSame(format_float(3.25, 1) . ' y', $this->entry($values, $number)['value']);
    }

    /**
     * An unanswered question is left out, as an empty profile field is; a checkbox is never unanswered.
     *
     * @return void
     */
    public function test_an_empty_answer_is_left_out(): void {
        $text = $this->create_field('text', 'motivation');
        $select = $this->create_field('select', 'shift', ['options' => "Morning\nNight"]);
        $date = $this->create_field('date', 'start');
        $number = $this->create_field('number', 'years');

        $values = answers::submitted_values($this->instance, (object) [
            'customfield_motivation' => '   ',
            'customfield_shift' => 0,
            'customfield_start' => 0,
            'customfield_years' => '',
        ]);

        $this->assertSame([], $values);
        // The control: the same questions answered do produce entries.
        $this->assertCount(2, answers::submitted_values($this->instance, (object) [
            'customfield_motivation' => 'Yes',
            'customfield_shift' => 1,
        ]));
        $this->assertNull($this->entry($values, $text) ?? $this->entry($values, $select)
            ?? $this->entry($values, $date) ?? $this->entry($values, $number));
    }

    /**
     * A shared category is asked only where it is switched on, and only its freezable types.
     *
     * @return void
     */
    public function test_the_questions_asked_are_the_instance_ones_plus_enabled_shared_ones(): void {
        global $DB;

        $local = $this->create_field('text', 'motivation');
        $sharedcategory = $this->create_category('core_customfield', 'shared', 0);
        $shared = $this->create_field('text', 'document', [], $sharedcategory);
        $sharedtextarea = $this->create_field('textarea', 'essay', [], $sharedcategory);

        $othercourse = $this->getDataGenerator()->create_course();
        $otherid = $this->plugin->add_instance($othercourse, $this->plugin->get_instance_defaults());
        $other = $this->create_field('text', 'elsewhere', [], $this->create_category('enrol_apply', 'application', $otherid));

        // Not switched on yet: only the instance's own question.
        $this->assertSame([(int) $local->get('id')], array_map('intval', array_keys(answers::fields($this->instance))));

        (new \core_customfield\shared(0, (object) [
            'categoryid' => $sharedcategory,
            'component' => 'enrol_apply',
            'area' => application_handler::AREA,
            'itemid' => $this->instance->id,
        ]))->create();

        $ids = array_map('intval', array_keys(answers::fields($this->instance)));
        $this->assertEqualsCanonicalizing([(int) $local->get('id'), (int) $shared->get('id')], $ids);
        $this->assertNotContains((int) $sharedtextarea->get('id'), $ids);
        $this->assertNotContains((int) $other->get('id'), $ids);
        $this->assertTrue($DB->record_exists('customfield_field', ['id' => $sharedtextarea->get('id')]));
    }

    /**
     * Each type's own validation runs, and core's uniqueness check does not.
     *
     * A shared field marked unique compares against every component's customfield_data; an
     * applicant must not be refused over a value stored for some course.
     *
     * @return void
     */
    public function test_type_validation_runs_and_uniqueness_does_not(): void {
        global $DB;

        $number = $this->create_field('number', 'years', ['minimumvalue' => 1, 'maximumvalue' => 10]);
        $unique = $this->create_field('text', 'document', ['uniquevalues' => 1]);
        $DB->insert_record('customfield_data', (object) [
            'fieldid' => $unique->get('id'),
            'instanceid' => 999,
            'charvalue' => 'TAKEN',
            'value' => 'TAKEN',
            'valueformat' => FORMAT_MOODLE,
            'timecreated' => time(),
            'timemodified' => time(),
            'contextid' => \context_system::instance()->id,
            'component' => 'enrol_apply',
            'area' => application_handler::AREA,
            'itemid' => $this->instance->id,
        ]);

        $errors = answers::validate($this->instance, ['customfield_years' => 50, 'customfield_document' => 'TAKEN'], []);

        $this->assertArrayHasKey('customfield_years', $errors);
        $this->assertArrayNotHasKey('customfield_document', $errors);
        // The control: a value in range validates.
        $valid = ['customfield_years' => 5, 'customfield_document' => 'TAKEN'];
        $this->assertSame([], answers::validate($this->instance, $valid, []));
    }

    /**
     * The application form asks the instance's questions, and only them.
     *
     * @return void
     */
    public function test_the_form_asks_the_instance_questions(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->create_field('text', 'motivation');

        $othercourse = $this->getDataGenerator()->create_course();
        $otherid = $this->plugin->add_instance($othercourse, $this->plugin->get_instance_defaults());
        $this->create_field('text', 'elsewhere', [], $this->create_category('enrol_apply', 'application', $otherid));

        $form = new application_form(null, null, 'post', '', null, true, [
            'instance' => $this->instance->id,
            'id' => $this->course->id,
        ]);
        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);

        $this->assertTrue($mform->elementExists('customfield_motivation'));
        $this->assertTrue($mform->elementExists('category_' . $this->categoryid));
        $this->assertFalse($mform->elementExists('customfield_elsewhere'));
        $this->assertFalse($mform->elementExists('nothingtoprovide'));
    }

    /**
     * Each question's configured default reaches the form data, and nothing else is overwritten.
     *
     * @return void
     */
    public function test_a_configured_default_is_set_on_the_form_data(): void {
        $this->create_field('text', 'motivation', ['defaultvalue' => 'Because']);
        $data = (object) ['instance' => $this->instance->id, 'id' => $this->course->id];

        answers::set_defaults($this->instance, $data);

        $this->assertSame('Because', $data->customfield_motivation);
        $this->assertEquals($this->course->id, $data->id);
    }

    /**
     * Applying freezes the answers into the snapshot and mails them to the approver.
     *
     * @return void
     */
    public function test_applying_freezes_the_answers_and_mails_them(): void {
        global $DB;

        $field = $this->create_field('text', 'motivation', [], null, 'Why this course');

        $approver = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($approver->id, $this->course->id, 'editingteacher');
        $DB->set_field('enrol', 'customtext3', '$@ALL@$', ['id' => $this->instance->id]);
        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
        $applicant = $this->getDataGenerator()->create_user();

        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->invoke($this->plugin, $instance, $applicant->id, (object) [
            'applydescription' => '',
            'customfield_motivation' => 'To learn',
        ]);
        $messages = $sink->get_messages();
        $sink->close();

        $snapshot = submission::read_snapshot(
            $DB->get_field('enrol_apply_submission', 'userinfodata', ['userid' => $applicant->id], MUST_EXIST)
        );
        $this->assertContains(
            ['key' => answers::key((int) $field->get('id')), 'label' => 'Why this course', 'value' => 'To learn'],
            $snapshot
        );

        $this->assertNotEmpty($messages, 'the approver should have been notified');
        $this->assertStringContainsString('Why this course', $messages[0]->fullmessagehtml);
        $this->assertStringContainsString('To learn', $messages[0]->fullmessagehtml);

        // Nothing went to customfield_data: the snapshot is the record (D1).
        $this->assertFalse($DB->record_exists('customfield_data', ['fieldid' => $field->get('id')]));
    }

    /**
     * Answers follow the identity rule of the profile custom fields (D8).
     *
     * @return void
     */
    public function test_an_answer_is_masked_like_a_custom_profile_field(): void {
        $json = json_encode(['version' => submission::SNAPSHOT_VERSION, 'fields' => [
            ['key' => 's_firstname', 'label' => 'First name', 'value' => 'Ana'],
            ['key' => answers::key(7), 'label' => 'Document', 'value' => 'SECRET-DOC'],
        ]]);
        $row = (object) ['courseid' => $this->course->id];

        $all = submission_formatter::snapshot($json, $row, submission_formatter::ALL_FIELDS);
        $this->assertStringContainsString('SECRET-DOC', $all);

        $restricted = submission_formatter::snapshot($json, $row, [fields::standard_key('firstname')]);
        $this->assertStringContainsString('Ana', $restricted);
        $this->assertStringNotContainsString('SECRET-DOC', $restricted);
    }

    /**
     * An answer key never resolves to a profile field, so nothing can route it to the profile.
     *
     * @return void
     */
    public function test_an_answer_key_names_no_profile_field(): void {
        $this->assertSame('', fields::key_target(answers::key(5)));
        $this->assertSame('', fields::form_element_name(answers::key(5)));
        $this->assertFalse(fields::is_standard(answers::key(5)));
        $this->assertTrue(answers::is_answer(answers::key(5)));
    }
}
