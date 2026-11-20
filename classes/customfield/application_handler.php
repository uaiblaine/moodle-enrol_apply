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
 * Custom field handler for the questions an apply instance asks at application time.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\customfield;

use context;
use context_course;
use core_customfield\api;
use core_customfield\category_controller;
use core_customfield\field_controller;
use moodle_url;
use stdClass;

/**
 * Custom field handler for the questions an apply instance asks at application time.
 *
 * The itemid is the enrol instance id, so every instance owns its own categories and fields,
 * and switches the site's shared categories on or off for itself. The instanceid core passes
 * to the data methods would be an application (an enrol_apply_submission id), but this plugin
 * never stores customfield_data: answers are frozen into the submission's snapshot, which every
 * reader of an application already reads (docs/design/custom-application-fields.md, D1). The
 * handler is the definition and form engine only.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class application_handler extends \core_customfield\handler {
    /** @var string The custom field area of this handler. */
    public const AREA = 'application';

    /**
     * Field types an application may ask, by plugin name.
     *
     * Textarea and picture are absent because both can carry files, which core keeps in the
     * file area of a customfield_data row this plugin never writes. 'pattern' is the fleet's own
     * type and is offered only where it is installed.
     *
     * @var string[]
     */
    public const ALLOWED_TYPES = ['checkbox', 'date', 'number', 'pattern', 'select', 'text'];

    /** @var stdClass|false|null The enrol instance, false when there is none, null until read. */
    protected $enrolinstance = null;

    /**
     * The apply instance this handler configures, or null when the itemid names none.
     *
     * Read on every handler rather than trusted: the itemid arrives from the browser through
     * core's custom field web services, so it may name another method's instance or nothing.
     *
     * @return stdClass|null The {enrol} row.
     */
    public function get_enrol_instance(): ?stdClass {
        global $DB;

        if ($this->enrolinstance === null) {
            $this->enrolinstance = $this->get_itemid()
                ? $DB->get_record('enrol', ['id' => $this->get_itemid(), 'enrol' => 'apply'])
                : false;
        }

        return $this->enrolinstance ?: null;
    }

    /**
     * The course context of the instance, or null when there is no instance.
     *
     * @return context_course|null
     */
    protected function get_course_context(): ?context_course {
        $instance = $this->get_enrol_instance();
        return $instance ? context_course::instance($instance->courseid) : null;
    }

    /**
     * A teacher who may configure the enrolment method may configure its questions.
     *
     * @return bool
     */
    public function can_configure(): bool {
        $context = $this->get_course_context();
        return $context !== null && has_capability('enrol/apply:config', $context);
    }

    /**
     * Nobody edits field data through this handler, because it keeps none.
     *
     * The application form draws its questions from get_fields() (answers::fields()) behind its
     * own access check, and the answers are frozen into the submission, never written as
     * customfield_data. Core's callers that take this as their only authorisation barrier, for
     * a component, area and itemid read from a request, are therefore refused.
     *
     * @param field_controller $field The field.
     * @param int $instanceid The application, or 0 for a new one.
     * @return bool Always false.
     */
    public function can_edit(field_controller $field, int $instanceid = 0): bool {
        return false;
    }

    /**
     * Whoever may read applications in the course may read a field's definition.
     *
     * Answers are never read through the handler (they live in the frozen snapshot, masked by
     * formatters\submission::visible_keys()); this answers core's own display helpers only.
     *
     * @param field_controller $field The field.
     * @param int $instanceid The application.
     * @return bool
     */
    public function can_view(field_controller $field, int $instanceid): bool {
        $context = $this->get_course_context();
        return $context !== null
            && has_any_capability(['enrol/apply:manageapplications', 'enrol/apply:viewreports'], $context);
    }

    /**
     * The fields are configured in the course of the instance.
     *
     * @return context
     */
    public function get_configuration_context(): context {
        return $this->get_course_context() ?? \context_system::instance();
    }

    /**
     * The page that manages this instance's fields.
     *
     * @return moodle_url
     */
    public function get_configuration_url(): moodle_url {
        return new moodle_url('/enrol/apply/fields.php', ['id' => $this->get_itemid()]);
    }

    /**
     * Answers belong to the course of the instance.
     *
     * @param int $instanceid The application.
     * @return context
     */
    public function get_instance_context(int $instanceid = 0): context {
        return $this->get_configuration_context();
    }

    /**
     * Restrict the types offered to the ones an application can freeze into its snapshot.
     *
     * @return array Type plugin name => localised name.
     */
    public function get_available_field_types(): array {
        return array_intersect_key(parent::get_available_field_types(), array_flip(self::ALLOWED_TYPES));
    }

    /**
     * Refuse to save a field of a type the handler does not offer.
     *
     * get_available_field_types() only shapes core's "Add a new field" menu; core's field form
     * builds whatever type its post names and saves it through this method. A type outside the
     * list cannot be frozen into a snapshot, and the management page has no name to show for it.
     *
     * @param field_controller $field The field.
     * @param stdClass $data Data from the field configuration form.
     * @return void
     * @throws \moodle_exception When the field, or the type a new field is saved as, is not offered.
     */
    public function save_field_configuration(field_controller $field, stdClass $data) {
        $types = [(string) $field->get('type')];
        if (!$field->get('id') && isset($data->type)) {
            // Core's api::save_field_configuration() sets a new field's type from the form data.
            $types[] = (string) $data->type;
        }
        $available = $this->get_available_field_types();
        foreach ($types as $type) {
            if (!array_key_exists($type, $available)) {
                throw new \moodle_exception('fieldtypenotallowed', 'enrol_apply');
            }
        }

        parent::save_field_configuration($field, $data);
    }

    /**
     * The categories, local and shared, each holding only the fields of the offered types.
     *
     * A shared category is the site's, and core's shared handler lets an administrator put any
     * type in it, a textarea or a picture included. Such a field is never asked on an application,
     * and core's management page names each field's type from get_available_field_types(), so
     * left in it would warn on an undefined index there. A category holding one is replaced by a
     * copy without it, built as api::get_categories_with_fields() builds its own; the category
     * itself and the fields kept are untouched.
     *
     * @param bool $ismanagementpage Whether every shared category is listed, enabled or not.
     * @return category_controller[]
     */
    public function get_categories_with_fields(bool $ismanagementpage = false): array {
        $available = $this->get_available_field_types();
        $categories = parent::get_categories_with_fields($ismanagementpage);
        foreach ($categories as $index => $category) {
            $kept = array_filter(
                $category->get_fields(),
                static fn(field_controller $field): bool => array_key_exists($field->get('type'), $available)
            );
            if (count($kept) === count($category->get_fields())) {
                continue;
            }
            $copy = category_controller::create(0, $category->to_record());
            $copy->set_handler($category->get_handler());
            $copy->set_original_component($category->get_original_component());
            $copy->set_original_area($category->get_original_area());
            $copy->set_original_itemid($category->get_original_itemid());
            foreach ($kept as $field) {
                $copy->add_field($field);
            }
            $categories[$index] = $copy;
        }

        return $categories;
    }

    /**
     * Remove core's "unique data" switch, which has nothing to compare against here.
     *
     * Core checks uniqueness against customfield_data, which this plugin never writes. Fields of
     * a shared category are configured through core's shared handler, which never calls this
     * method, so the switch stays on their form and has no effect on applications.
     *
     * @param \MoodleQuickForm $mform The field configuration form.
     * @return void
     */
    public function config_form_definition(\MoodleQuickForm $mform) {
        if ($mform->elementExists('configdata[uniquevalues]')) {
            $mform->removeElement('configdata[uniquevalues]');
        }
    }

    /**
     * Nothing is backed up per application: answers travel inside the submission's snapshot.
     *
     * @param field_controller $field The field.
     * @param int $instanceid The application.
     * @return bool
     */
    protected function can_backup(field_controller $field, int $instanceid): bool {
        return false;
    }

    /**
     * Delete every category, field, shared-category switch and display condition this instance owns.
     *
     * The frozen answers in enrol_apply_submission are not touched: they outlive the instance.
     * Deleting a local field already removes the conditions naming it (observers::field_deleted()),
     * but a condition between two fields of a shared category survives the fields, so the
     * instance's rows are deleted by enrolid as well.
     *
     * @param int $enrolid The enrol instance id.
     * @return void
     */
    public static function delete_for_instance(int $enrolid): void {
        global $DB;

        foreach (api::get_categories_with_fields('enrol_apply', self::AREA, $enrolid) as $category) {
            api::delete_category($category);
        }
        $DB->delete_records('customfield_shared', [
            'component' => 'enrol_apply',
            'area' => self::AREA,
            'itemid' => $enrolid,
        ]);
        $DB->delete_records(\enrol_apply\local\conditions::TABLE, ['enrolid' => $enrolid]);
    }
}
