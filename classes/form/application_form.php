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

namespace enrol_apply\form;

use context_course;
use core_form\dynamic_form;
use enrol_apply\local\fields;
use moodle_url;

/**
 * The form an applicant fills in, rendered either in a modal or on a page of its own.
 *
 * One class, two transports. The modal reaches it through core's
 * core_form_dynamic_form web service; apply.php renders the same class outside AJAX for a
 * browser with no JavaScript. Every authorisation decision therefore lives in
 * check_access_for_dynamic_submission(), which both transports run.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class application_form extends dynamic_form {
    /**
     * Above this many editable fields, one confirmation replaces the per-field ones.
     *
     * A checkbox against each field reads well for two or three and turns into a wall of
     * ticking for nine, which is the size of the default field set. Past the threshold the
     * applicant confirms the block once instead.
     *
     * @var int
     */
    public const CONFIRM_EACH_UP_TO = 3;

    /** @var \stdClass|null Memoised enrol instance the form is bound to. */
    protected $instance = null;

    /** @var array|null Memoised per-key classification, keyed by field key. */
    protected $states = null;

    /**
     * The enrol instance this form belongs to.
     *
     * @return \stdClass The {enrol} record.
     */
    protected function get_instance(): \stdClass {
        global $CFG;

        require_once($CFG->dirroot . '/lib/enrollib.php');

        if ($this->instance === null) {
            global $DB;

            $instanceid = $this->optional_param('instance', 0, PARAM_INT);

            /* The course id is derived from the instance rather than required alongside it.
               The card's button links to apply.php with the instance alone - it is the only
               id that identifies anything - and demanding a second, redundant parameter made
               every real entry point throw while a hand-built url with both worked fine. */
            $courseid = (int) $DB->get_field('enrol', 'courseid', ['id' => $instanceid, 'enrol' => 'apply']);
            if (!$courseid) {
                throw new \moodle_exception('invalidenrolinstance', 'enrol');
            }

            /* enrol_get_instances($courseid, true) also validates that the enrolment method
               and the instance are enabled, which a plain get_record() would not - so a
               disabled instance cannot be reached by posting its id. */
            $instances = enrol_get_instances($courseid, true);
            $instance = $instances[$instanceid] ?? null;
            if (empty($instance) || $instance->enrol !== 'apply') {
                throw new \moodle_exception('invalidenrolinstance', 'enrol');
            }
            $this->instance = $instance;
        }

        return $this->instance;
    }

    /**
     * The plugin instance driving the application.
     *
     * @return \enrol_apply_plugin The plugin.
     */
    protected function get_plugin(): \enrol_apply_plugin {
        return enrol_get_plugin('apply');
    }

    /**
     * How each picked field should be treated for the current user.
     *
     * Decided once, before any element is created, and reused by both definition() and
     * validation(). It has to be decided up front: a required rule is attached when an
     * element is created and HTML_QuickForm::validate() walks its rule list by element name
     * without checking that the element still exists, so any add-then-remove technique
     * leaves the form permanently unsubmittable with no visible field to explain why.
     *
     * @return array Field key => one of the fields::STATE_ constants.
     */
    protected function get_states(): array {
        global $USER;

        if ($this->states === null) {
            $this->states = [];
            foreach (fields::resolve($this->get_instance())->keys() as $key) {
                $this->states[$key] = fields::classify($key, $USER);
            }
        }

        return $this->states;
    }

    /**
     * Build the form.
     *
     * @return void
     */
    public function definition() {
        global $USER;

        $mform = $this->_form;
        $instance = $this->get_instance();
        $resolved = fields::resolve($instance);

        $mform->addElement('hidden', 'instance');
        $mform->setType('instance', PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        if (!empty($instance->customtext1)) {
            $mform->addElement('html', format_text($instance->customtext1, FORMAT_HTML));
        }

        $editable = [];
        $locked = [];
        foreach ($this->get_states() as $key => $state) {
            if ($state === fields::STATE_EDITABLE) {
                $editable[] = $key;
            } else if ($state === fields::STATE_LOCKED) {
                $locked[] = $key;
            }
        }

        $this->add_editable_section($editable, $resolved);
        $this->add_locked_section($locked, $USER);

        /* An instance can legitimately ask for nothing at all: no profile fields, no comment
           and no introduction. Both section builders above return early when their list is
           empty, so without this the form is two hidden inputs and the applicant opens a modal
           with nothing in it and a Save button that says only "Save" - measured, the rendered
           body was empty of text entirely. There is still an action to confirm, so the form
           says what it is. */
        if (!$editable && !$locked && empty($instance->customint7) && empty($instance->customtext1)) {
            $mform->addElement(
                'static',
                'nothingtoprovide',
                '',
                get_string('nothingtoprovide', 'enrol_apply', format_string($this->get_course_name()))
            );
        }

        if ($instance->customint7) {
            $label = get_string('comment', 'enrol_apply');
            if (!empty($instance->customtext2)) {
                /* The escaped spelling: a moodleform element label renders through a triple
                   stash in element-template.mustache. */
                $label = format_string($instance->customtext2, true, ['escape' => true]);
            }
            $mform->addElement('textarea', 'applydescription', $label, ['cols' => 80, 'rows' => 5]);
            $mform->setType('applydescription', PARAM_TEXT);
        }

        if (!empty($this->_customdata['showbuttons'])) {
            /* A dynamic_form carries no action buttons of its own, because the modal supplies
               its own Save and Cancel. Rendered on a page instead, that leaves a form nobody
               can submit — which looks entirely normal in review and shows up only in a
               browser. The page transport asks for them explicitly. */
            $this->add_action_buttons(true, get_string('submitapplication', 'enrol_apply'));
        }
    }

    /**
     * The name of the course being applied to.
     *
     * Read through get_course() rather than carried on the instance, because an enrol row holds
     * only the course id. Kept as its own method so definition() reads as one thing.
     *
     * @return string The course full name, unformatted.
     */
    protected function get_course_name(): string {
        return (string) get_course($this->get_instance()->courseid)->fullname;
    }

    /**
     * Add the fields the applicant may edit, and however they are asked to confirm them.
     *
     * @param array $editable Field keys in the editable state.
     * @param \enrol_apply\local\fieldset $resolved The instance's resolved field set.
     * @return void
     */
    protected function add_editable_section(array $editable, $resolved): void {
        if (!$editable) {
            return;
        }

        $mform = $this->_form;
        $mform->addElement('header', 'detailsheader', get_string('detailsthattravel', 'enrol_apply'));
        $mform->setExpanded('detailsheader', true);
        $mform->addElement('static', 'detailsintro', '', get_string('detailsthattravel_desc', 'enrol_apply'));

        $confirmeach = count($editable) <= self::CONFIRM_EACH_UP_TO;
        foreach ($editable as $key) {
            $this->add_editable_field($key, $resolved->is_required($key), $confirmeach);
        }
        if (!$confirmeach) {
            $mform->addElement('advcheckbox', 'confirmall', '', get_string('confirmalldetails', 'enrol_apply'));
        }
    }

    /**
     * Add the fields the applicant may see but not change.
     *
     * Rendered read-only rather than hidden. Hiding one would still snapshot it and mail it
     * to an approver, disclosing a value the applicant was never shown.
     *
     * @param array $locked Field keys in the locked state.
     * @param \stdClass $user The applicant.
     * @return void
     */
    protected function add_locked_section(array $locked, \stdClass $user): void {
        if (!$locked) {
            return;
        }

        $mform = $this->_form;
        $mform->addElement('header', 'lockedheader', get_string('lockedby', 'enrol_apply'));
        $mform->setExpanded('lockedheader', true);
        foreach ($locked as $key) {
            $mform->addElement(
                'static',
                'locked_' . $key,
                fields::label($key, true),
                s(fields::current_value($key, $user))
            );
        }
    }

    /**
     * Add one editable field and the checkbox confirming it is up to date.
     *
     * The checkbox carries the field's own name rather than a shared label. An advcheckbox
     * with an empty element label gets no accessible name at all - element-advcheckbox.mustache
     * emits only an aria-describedby, which is a description, not a name - so six fields would
     * otherwise announce as six identical controls.
     *
     * @param string $key Field key.
     * @param bool $required Whether an application cannot be submitted without it.
     * @param bool $confirmeach Whether this field carries its own confirmation checkbox.
     * @return void
     */
    protected function add_editable_field(string $key, bool $required, bool $confirmeach): void {
        $mform = $this->_form;
        $name = fields::form_element_name($key);
        if ($name === '') {
            return;
        }
        $label = fields::label($key, true);

        if ($key === fields::standard_key('country')) {
            $countries = ['' => get_string('selectacountry')] + get_string_manager()->get_list_of_countries();
            $mform->addElement('select', $name, $label, $countries);
        } else {
            $mform->addElement('text', $name, $label, 'maxlength="255" size="30"');
            $mform->setType($name, PARAM_TEXT);
        }

        if ($required) {
            /* addRule paints the marker and blocks in the browser; validation() is what makes
               it stick, because a client-side rule never blocks a POST. */
            $mform->addRule($name, get_string('requiredtoapply', 'enrol_apply'), 'required', null, 'client');
        }

        if ($confirmeach) {
            $mform->addElement(
                'advcheckbox',
                'confirm_' . $key,
                '',
                get_string('confirmfield', 'enrol_apply', fields::label($key, false))
            );
        }
    }

    /**
     * Reject a submission that the browser would have let through.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $resolved = fields::resolve($this->get_instance());

        $editable = array_keys(array_filter(
            $this->get_states(),
            static function (string $state): bool {
                return $state === fields::STATE_EDITABLE;
            }
        ));
        $confirmeach = count($editable) <= self::CONFIRM_EACH_UP_TO;
        $anyvalue = false;

        foreach ($editable as $key) {
            $name = fields::form_element_name($key);
            if ($name === '' || !$this->_form->elementExists($name)) {
                continue;
            }

            /* trim() before deciding. $CFG->strictformsrequired defaults to off, and with it
               off MoodleQuickForm_Rule_Required::validate() does not strip whitespace at all,
               so a single space satisfies a required field. */
            $value = trim((string) ($data[$name] ?? ''));
            if ($resolved->is_required($key) && $value === '') {
                $errors[$name] = get_string('requiredtoapply', 'enrol_apply');
                continue;
            }
            if ($value === '') {
                continue;
            }
            $anyvalue = true;
            if ($confirmeach && empty($data['confirm_' . $key])) {
                $errors['confirm_' . $key] = get_string('confirmfield', 'enrol_apply', fields::label($key, false));
            }
        }

        /* One confirmation for the whole block, and only when there is something to confirm -
           the same rule the per-field mode applies, so the two modes agree about a form with
           nothing filled in. */
        if (!$confirmeach && $anyvalue && empty($data['confirmall'])) {
            $errors['confirmall'] = get_string('confirmalldetails', 'enrol_apply');
        }

        return $errors;
    }

    /**
     * Everything that decides whether this user may apply, on both transports.
     *
     * Widened from protected to public on purpose. The parent runs this only when the form
     * was built by the AJAX web service ($isajaxsubmission), so the page transport in
     * apply.php has to call it itself - and a guard the second transport cannot reach is
     * not a guard.
     *
     * @return void
     */
    public function check_access_for_dynamic_submission(): void {
        global $CFG, $DB, $USER;

        $instance = $this->get_instance();
        $course = get_course($instance->courseid);
        $context = context_course::instance($instance->courseid);

        /* Stricter than core's own guard on enrol/index.php, deliberately. That one fires
           only when the log-in-as context is a COURSE, so an administrator who used "Log in
           as" from a profile page - the ordinary way - walks straight past it. Submitting an
           application in somebody else's name is impersonation whichever screen it started
           from, so every log-in-as session is refused here. */
        if (\core\session\manager::is_loggedinas()) {
            throw new \moodle_exception('loginasnoenrol', '', $CFG->wwwroot . '/course/view.php?id=' . $instance->courseid);
        }

        if (isguestuser()) {
            throw new \moodle_exception('noguestaccess', 'enrol');
        }

        if (!\core_course_category::can_view_course_info($course) && !is_enrolled($context, $USER, '', true)) {
            throw new \moodle_exception('coursehidden', '', $CFG->wwwroot . '/');
        }

        $allowapply = $this->get_plugin()->allow_apply($instance);
        if ($allowapply !== true) {
            throw new \moodle_exception('cantenrol', 'enrol_apply');
        }

        if ($DB->record_exists('user_enrolments', ['userid' => $USER->id, 'enrolid' => $instance->id])) {
            throw new \moodle_exception('notification', 'enrol_apply');
        }

        if ($instance->customint3 > 0) {
            $count = $DB->count_records('user_enrolments', ['enrolid' => $instance->id]);
            if ($count >= $instance->customint3) {
                throw new \moodle_exception('maxenrolledreached', 'enrol_apply', '', $count);
            }
        }
    }

    /**
     * The context permission checks run against.
     *
     * The course CATEGORY, not the course. dynamic_form's constructor runs
     * external_api::validate_context(), which ends in require_login() - and for an applicant
     * who is not yet enrolled that throws "Not enrolled", so the modal would fail for exactly
     * the people it exists for. Every authorisation decision is still made in the course
     * context, resolved server side from the instance id inside
     * check_access_for_dynamic_submission(). Both of core's own enrolment forms do the same.
     *
     * @return \context The course category context.
     */
    protected function get_context_for_dynamic_submission(): \context {
        return context_course::instance($this->get_instance()->courseid)->get_parent_context();
    }

    /**
     * Where the non-AJAX transport lives.
     *
     * @return moodle_url The page url.
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/enrol/apply/apply.php', ['instance' => $this->get_instance()->id]);
    }

    /**
     * Pre-fill from the applicant's own record.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        global $USER;

        $instance = $this->get_instance();
        $data = [
            'instance' => $instance->id,
            'id' => $instance->courseid,
        ];

        foreach ($this->get_states() as $key => $state) {
            if ($state !== fields::STATE_EDITABLE) {
                continue;
            }
            $name = fields::form_element_name($key);
            if ($name !== '') {
                $data[$name] = fields::current_value($key, $USER);
            }
        }

        $this->set_data($data);
    }

    /**
     * Submit the application.
     *
     * @return string Url the client should go to next.
     */
    public function process_dynamic_submission() {
        global $USER;

        $instance = $this->get_instance();
        $data = $this->get_data();
        $this->get_plugin()->submit_application($instance, $USER->id, $data);

        /* What the applicant typed is carried to the acknowledgement page so it can offer to
           save it. Nothing is written here: the offer is theirs to accept. */
        \enrol_apply\local\offer::stash($instance, $USER, (array) $data);

        return (string) new moodle_url('/enrol/apply/applied.php', ['instance' => $instance->id]);
    }
}
