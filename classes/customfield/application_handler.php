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
     * Anybody shown the application form may answer it.
     *
     * The form's own access check (application_form::check_access_for_dynamic_submission()) is
     * the gate; core calls this only to decide which fields to add to that form.
     *
     * @param field_controller $field The field.
     * @param int $instanceid The application, or 0 for a new one.
     * @return bool
     */
    public function can_edit(field_controller $field, int $instanceid = 0): bool {
        return true;
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
     * Delete every category, field and shared-category switch this instance owns.
     *
     * The frozen answers in enrol_apply_submission are not touched: they outlive the instance.
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
    }
}
