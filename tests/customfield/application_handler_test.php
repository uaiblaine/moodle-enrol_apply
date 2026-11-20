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
 * Tests for the application field handler and the life cycle of what it configures.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\customfield;

use core_customfield\api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the application field handler and the life cycle of what it configures.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(application_handler::class)]
#[CoversClass(\enrol_apply\observers::class)]
final class application_handler_test extends \advanced_testcase {
    /**
     * Enable the plugin; every test writes to the database.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $enabled = array_keys(enrol_get_plugins(true));
        $enabled[] = 'apply';
        set_config('enrol_plugins_enabled', implode(',', array_unique($enabled)));
    }

    /**
     * A course with an apply instance.
     *
     * @return array The course and the {enrol} row.
     */
    private function create_instance(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $enrolid = enrol_get_plugin('apply')->add_instance($course);
        return [$course, $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST)];
    }

    /**
     * A local category with one text field, owned by the given instance.
     *
     * @param int $enrolid The instance.
     * @return int The category id.
     */
    private function create_local_category(int $enrolid): int {
        /** @var \core_customfield_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category([
            'component' => 'enrol_apply',
            'area' => application_handler::AREA,
            'itemid' => $enrolid,
        ]);
        $generator->create_field([
            'categoryid' => $category->get('id'),
            'type' => 'text',
            'shortname' => 'motivation' . $enrolid,
        ]);
        return (int) $category->get('id');
    }

    /**
     * Switch a shared category on for an instance, as core's toggle web service does.
     *
     * @param int $categoryid The shared category.
     * @param int $enrolid The instance.
     * @return void
     */
    private function enable_shared(int $categoryid, int $enrolid): void {
        (new \core_customfield\shared(0, (object) [
            'categoryid' => $categoryid,
            'component' => 'enrol_apply',
            'area' => application_handler::AREA,
            'itemid' => $enrolid,
        ]))->create();
    }

    /**
     * A site-wide shared category.
     *
     * @return int The category id.
     */
    private function create_shared_category(): int {
        /** @var \core_customfield_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category(['component' => 'core_customfield', 'area' => 'shared', 'itemid' => 0]);
        return (int) $category->get('id');
    }

    /**
     * A teacher of the course configures; a student, a teacher elsewhere and nobody at all do not.
     *
     * @return void
     */
    public function test_can_configure_follows_the_instance_config_capability(): void {
        [$course, $instance] = $this->create_instance();
        [$othercourse] = $this->create_instance();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $elsewhere = $this->getDataGenerator()->create_and_enrol($othercourse, 'editingteacher');

        $handler = application_handler::create((int) $instance->id);

        $this->setUser($teacher);
        $this->assertTrue($handler->can_configure());
        $this->setUser($student);
        $this->assertFalse($handler->can_configure());
        $this->setUser($elsewhere);
        $this->assertFalse($handler->can_configure());
    }

    /**
     * An itemid that names no apply instance configures nothing, even for an administrator.
     *
     * The itemid reaches the handler from the browser through core's custom field web services.
     *
     * @return void
     */
    public function test_an_itemid_naming_no_apply_instance_configures_nothing(): void {
        global $DB;

        $this->setAdminUser();
        [$course, $instance] = $this->create_instance();
        $manualid = (int) $DB->get_field('enrol', 'id', ['courseid' => $course->id, 'enrol' => 'manual'], MUST_EXIST);

        // The control: the same administrator configures the real instance.
        $this->assertTrue(application_handler::create((int) $instance->id)->can_configure());

        $this->assertFalse(application_handler::create($manualid)->can_configure());
        $this->assertFalse(application_handler::create(0)->can_configure());
        $this->assertNull(application_handler::create($manualid)->get_enrol_instance());
    }

    /**
     * Only the types an application can freeze are offered.
     *
     * @return void
     */
    public function test_only_freezable_types_are_offered(): void {
        [, $instance] = $this->create_instance();
        $types = application_handler::create((int) $instance->id)->get_available_field_types();

        $this->assertArrayHasKey('text', $types);
        $this->assertArrayHasKey('select', $types);
        $this->assertArrayNotHasKey('textarea', $types);
        $this->assertArrayNotHasKey('picture', $types);
        $this->assertEmpty(array_diff(array_keys($types), application_handler::ALLOWED_TYPES));
    }

    /**
     * The "unique data" switch is removed from the field form.
     *
     * @return void
     */
    public function test_the_unique_values_switch_is_removed(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        [, $instance] = $this->create_instance();
        $mform = new \MoodleQuickForm('test', 'post', '');
        $mform->addElement('selectyesno', 'configdata[uniquevalues]', 'Unique');
        $mform->addElement('selectyesno', 'configdata[required]', 'Required');

        application_handler::create((int) $instance->id)->config_form_definition($mform);

        $this->assertFalse($mform->elementExists('configdata[uniquevalues]'));
        $this->assertTrue($mform->elementExists('configdata[required]'));
    }

    /**
     * The configuration page is the instance's own, and so is the context.
     *
     * @return void
     */
    public function test_configuration_url_and_context_belong_to_the_instance(): void {
        [$course, $instance] = $this->create_instance();
        $handler = application_handler::create((int) $instance->id);

        $this->assertEquals(
            (new \moodle_url('/enrol/apply/fields.php', ['id' => $instance->id]))->out(false),
            $handler->get_configuration_url()->out(false)
        );
        $this->assertEquals(\context_course::instance($course->id)->id, $handler->get_configuration_context()->id);
        $this->assertEquals(\context_course::instance($course->id)->id, $handler->get_instance_context(0)->id);
    }

    /**
     * A shared category reaches an instance only once it is switched on there.
     *
     * @return void
     */
    public function test_a_shared_category_applies_only_where_it_is_enabled(): void {
        [, $instance] = $this->create_instance();
        [, $other] = $this->create_instance();
        $shared = $this->create_shared_category();
        $local = $this->create_local_category((int) $instance->id);

        $this->enable_shared($shared, (int) $instance->id);

        $ids = static fn(array $categories): array => array_map(static fn($c) => (int) $c->get('id'), $categories);
        $this->assertEqualsCanonicalizing(
            [$local, $shared],
            $ids(application_handler::create((int) $instance->id)->get_categories_with_fields())
        );
        $this->assertSame([], $ids(application_handler::create((int) $other->id)->get_categories_with_fields()));
    }

    /**
     * Deleting an instance deletes its categories, fields and shared switches, and nothing else.
     *
     * @return void
     */
    public function test_delete_instance_removes_the_instance_fields(): void {
        global $DB;

        [, $instance] = $this->create_instance();
        [, $other] = $this->create_instance();
        $shared = $this->create_shared_category();
        $local = $this->create_local_category((int) $instance->id);
        $otherlocal = $this->create_local_category((int) $other->id);
        $this->enable_shared($shared, (int) $instance->id);
        $this->enable_shared($shared, (int) $other->id);

        enrol_get_plugin('apply')->delete_instance($instance);

        $this->assertFalse($DB->record_exists('customfield_category', ['id' => $local]));
        $this->assertFalse($DB->record_exists('customfield_field', ['categoryid' => $local]));
        $this->assertFalse($DB->record_exists('customfield_shared', ['itemid' => $instance->id, 'component' => 'enrol_apply']));

        // The controls: another instance keeps everything, and the shared category itself survives.
        $this->assertTrue($DB->record_exists('customfield_category', ['id' => $otherlocal]));
        $this->assertTrue($DB->record_exists('customfield_shared', ['itemid' => $other->id, 'component' => 'enrol_apply']));
        $this->assertTrue($DB->record_exists('customfield_category', ['id' => $shared]));
    }

    /**
     * With the plugin disabled, deleting the course leaves no application fields behind.
     *
     * Core deletes the {enrol} row without calling a disabled plugin's delete_instance(), so the
     * course_deleted observer is the only thing that can remove them.
     *
     * @return void
     */
    public function test_the_course_deleted_observer_sweeps_orphaned_fields(): void {
        global $DB;

        [$course, $instance] = $this->create_instance();
        [, $other] = $this->create_instance();
        $shared = $this->create_shared_category();
        $local = $this->create_local_category((int) $instance->id);
        $otherlocal = $this->create_local_category((int) $other->id);
        $this->enable_shared($shared, (int) $instance->id);
        $this->enable_shared($shared, (int) $other->id);

        $enabled = enrol_get_plugins(true);
        unset($enabled['apply']);
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        delete_course($course, false);

        // The precondition: core skipped the plugin and deleted the instance itself.
        $this->assertFalse($DB->record_exists('enrol', ['id' => $instance->id]));

        $this->assertFalse($DB->record_exists('customfield_category', ['id' => $local]));
        $this->assertFalse($DB->record_exists('customfield_shared', ['itemid' => $instance->id, 'component' => 'enrol_apply']));

        // The controls: the sweep takes orphans only.
        $this->assertTrue($DB->record_exists('customfield_category', ['id' => $otherlocal]));
        $this->assertTrue($DB->record_exists('customfield_shared', ['itemid' => $other->id, 'component' => 'enrol_apply']));
        $this->assertTrue($DB->record_exists('customfield_category', ['id' => $shared]));
    }

    /**
     * The configuration page is reachable from the instance's action icons for a configurer only.
     *
     * @return void
     */
    public function test_the_fields_page_is_linked_from_the_instance_icons(): void {
        [$course, $instance] = $this->create_instance();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $nonediting = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $plugin = enrol_get_plugin('apply');

        $this->setUser($teacher);
        $this->assertStringContainsString('/enrol/apply/fields.php', implode('', $plugin->get_action_icons($instance)));

        $this->setUser($nonediting);
        $this->assertStringNotContainsString('/enrol/apply/fields.php', implode('', $plugin->get_action_icons($instance)));
    }

    /**
     * Nobody edits field data through the handler, which keeps none, and the form still asks every field.
     *
     * Core's callers that take can_edit() as their only barrier build the handler from a request.
     *
     * @return void
     */
    public function test_nobody_edits_through_the_handler(): void {
        $this->setAdminUser();
        [, $instance] = $this->create_instance();
        $this->create_local_category((int) $instance->id);
        $handler = application_handler::create((int) $instance->id);
        $fields = $handler->get_fields();
        $this->assertCount(1, $fields);

        $this->assertFalse($handler->can_edit(reset($fields), 0));
        $this->assertSame([], $handler->get_editable_fields(0));
        // The control: the application form asks the field all the same.
        $this->assertSame(array_keys($fields), array_keys(\enrol_apply\local\answers::fields($instance)));
    }

    /**
     * A field of a type the handler does not offer is not saved, however the type arrives.
     *
     * Core's field form builds whatever type its post names; the "Add a new field" menu is the only
     * place the list of offered types applied.
     *
     * @return void
     */
    public function test_a_type_outside_the_list_is_not_saved(): void {
        global $DB;

        $this->setAdminUser();
        [, $instance] = $this->create_instance();
        $handler = application_handler::create((int) $instance->id);
        $categoryid = $this->create_local_category((int) $instance->id);
        $category = \core_customfield\category_controller::create($categoryid, null, $handler);

        $textarea = \core_customfield\field_controller::create(0, (object) ['type' => 'textarea'], $category);
        try {
            $handler->save_field_configuration($textarea, (object) ['name' => 'Essay', 'shortname' => 'essay']);
            $this->fail('A textarea field was saved.');
        } catch (\moodle_exception $e) {
            $this->assertSame('fieldtypenotallowed', $e->errorcode);
        }

        // A new field's type is also taken from the form data, so a text field cannot be saved as one.
        $forged = \core_customfield\field_controller::create(0, (object) ['type' => 'text'], $category);
        try {
            $data = (object) ['name' => 'Essay', 'shortname' => 'essay', 'type' => 'textarea'];
            $handler->save_field_configuration($forged, $data);
            $this->fail('A text field was saved as a textarea.');
        } catch (\moodle_exception $e) {
            $this->assertSame('fieldtypenotallowed', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('customfield_field', ['shortname' => 'essay']));

        // The control: an offered type is saved.
        $text = \core_customfield\field_controller::create(0, (object) ['type' => 'text'], $category);
        $handler->save_field_configuration($text, (object) ['name' => 'Essay', 'shortname' => 'essay']);
        $this->assertSame('text', $DB->get_field('customfield_field', 'type', ['shortname' => 'essay'], MUST_EXIST));
    }

    /**
     * The management page leaves out a shared category's field of a type the handler does not offer.
     *
     * Core's shared handler lets an administrator put any type in a shared category, and core's
     * management page names each field's type from the handler's offered types.
     *
     * @return void
     */
    public function test_the_management_page_leaves_out_a_field_it_cannot_name(): void {
        global $PAGE;

        $this->setAdminUser();
        [$course, $instance] = $this->create_instance();
        $local = $this->create_local_category((int) $instance->id);
        $shared = $this->create_shared_category();
        /** @var \core_customfield_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $document = $generator->create_field(['categoryid' => $shared, 'type' => 'text', 'shortname' => 'document']);
        $essay = $generator->create_field(['categoryid' => $shared, 'type' => 'textarea', 'shortname' => 'essay']);
        $PAGE->set_url(new \moodle_url('/enrol/apply/fields.php', ['id' => $instance->id]));
        $PAGE->set_context(\context_course::instance($course->id));

        $management = new \core_customfield\output\management(application_handler::create((int) $instance->id));
        $data = $management->export_for_template($PAGE->get_renderer('core'));

        $types = [];
        $categories = [];
        foreach ($data->categories as $category) {
            $categories[] = (int) $category['id'];
            foreach ($category['fields'] as $field) {
                $types[(int) $field['id']] = $field['type'];
            }
        }
        $this->assertEqualsCanonicalizing([$local, $shared], $categories);
        $this->assertArrayNotHasKey((int) $essay->get('id'), $types);
        // The control: the same category's text field is listed, under its type's name.
        $this->assertSame(get_string('pluginname', 'customfield_text'), $types[(int) $document->get('id')] ?? null);
        $this->assertNotContains(null, $types);
    }
}
