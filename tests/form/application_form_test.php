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
 * Tests for the application form and the gates it applies.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\form;

use enrol_apply\local\fields;
use enrol_apply\local\fieldset;
use enrol_apply\local\offer;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/tests/fixtures/testable_application_form.php');

/**
 * Tests for the application form and the gates it applies.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(application_form::class)]
final class application_form_test extends \advanced_testcase {
    /** @var \stdClass Course the instance belongs to. */
    protected $course;

    /** @var \stdClass The enrol_apply instance. */
    protected $instance;

    /** @var \enrol_apply_plugin The plugin. */
    protected $plugin;

    /** @var \stdClass The applicant. */
    protected $applicant;

    /**
     * An instance that asks for nothing still renders something the applicant can act on.
     *
     * Both section builders return early when their list is empty, so an instance with no
     * profile fields, no comment and no introduction produced a form of two hidden inputs -
     * measured, the rendered body had no text in it at all. The applicant opened a modal that
     * was blank apart from a Save button, with nothing saying what saving would do.
     *
     * The named element is the assertion rather than the rendered text, because the text is a
     * language string and a test that greps for English pins the translation instead of the
     * behaviour.
     *
     * @return void
     */
    public function test_an_instance_that_asks_for_nothing_still_says_what_submitting_does(): void {
        global $DB;

        $this->ask_for([]);
        $DB->set_field('enrol', 'customint7', 0, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'customtext1', '', ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $names = $this->element_names($this->make_form());

        $this->assertContains('nothingtoprovide', $names);
    }

    /**
     * That line appears only when there really is nothing else.
     *
     * The control for the test above. Without it, the fix could have added the line to every
     * form - which would read as a working feature and be wrong on every instance that does ask
     * for something.
     *
     * @return void
     */
    public function test_the_nothing_to_provide_line_is_absent_when_a_field_is_asked_for(): void {
        $this->ask_for([fields::standard_key('city')]);

        $names = $this->element_names($this->make_form());

        $this->assertContains(fields::form_element_name(fields::standard_key('city')), $names);
        $this->assertNotContains('nothingtoprovide', $names);
    }

    /**
     * Nor when the only thing asked for is the comment.
     *
     * The comment is the other reason a form can have content, and it is checked separately
     * because it is a different branch of the same condition.
     *
     * @return void
     */
    public function test_the_nothing_to_provide_line_is_absent_when_only_the_comment_is_asked_for(): void {
        global $DB;

        $this->ask_for([]);
        $DB->set_field('enrol', 'customint7', 1, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'customtext1', '', ['id' => $this->instance->id]);
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $names = $this->element_names($this->make_form());

        $this->assertContains('applydescription', $names);
        $this->assertNotContains('nothingtoprovide', $names);
    }

    /**
     * Course, instance, applicant, and a field set worth rendering.
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
        $instanceid = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());

        set_config('allowedfields', 's_city,s_institution,s_department', 'enrol_apply');
        $DB->set_field(
            'enrol',
            'customtext4',
            fieldset::from_keys(['s_city', 's_institution'], ['s_institution'])->to_json(),
            ['id' => $instanceid]
        );
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $this->applicant = $this->getDataGenerator()->create_user(['city' => 'Campinas']);
        $this->setUser($this->applicant);
    }

    /**
     * Point the instance at a given set of fields, allowing them all at site level.
     *
     * @param array $keys Field keys to ask for.
     * @param array $required Keys among them that are required.
     * @return void
     */
    protected function ask_for(array $keys, array $required = []): void {
        global $DB;

        set_config('allowedfields', implode(',', $keys), 'enrol_apply');
        $DB->set_field(
            'enrol',
            'customtext4',
            fieldset::from_keys($keys, $required)->to_json(),
            ['id' => $this->instance->id]
        );
        $this->instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);
    }

    /**
     * Build the form the way both transports do.
     *
     * @return application_form The form bound to this test's instance.
     */
    protected function make_form(): application_form {
        return new application_form(null, null, 'post', '', null, true, [
            'instance' => $this->instance->id,
            'id' => $this->course->id,
        ]);
    }

    /**
     * The element names the form actually created.
     *
     * @param application_form $form Form to inspect.
     * @return array List of element names.
     */
    protected function element_names(application_form $form): array {
        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
        $names = [];
        foreach ($mform->_elements as $element) {
            $name = $element->getName();
            if ($name !== null && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * A log-in-as session may never submit an application in somebody else's name.
     *
     * Stricter than core's own guard on enrol/index.php, which fires only when the log-in-as
     * context is a course - so an administrator who used "Log in as" from a profile page
     * walks straight past it.
     *
     * @return void
     */
    public function test_check_access_refuses_a_login_as_session(): void {
        $form = $this->make_form();

        // The control: the same applicant, not logged in as anybody, is admitted.
        $form->check_access_for_dynamic_submission();

        $admin = get_admin();
        $this->setUser($admin);
        \core\session\manager::loginas($this->applicant->id, \context_system::instance());

        $this->expectException(\moodle_exception::class);
        $this->make_form()->check_access_for_dynamic_submission();
    }

    /**
     * The instance id alone is enough to build the form.
     *
     * The card's button links to apply.php with the instance and nothing else, because that
     * is the only id identifying anything - the course is derivable from it. An earlier
     * version demanded a redundant course id alongside, so every real entry point threw
     * "Invalid enrolment instance" while a hand-built url carrying both worked perfectly.
     * Both the unit tests and the manual check supplied both ids and sailed past it; Behat,
     * which clicks the actual button, is what found it.
     *
     * @return void
     */
    public function test_the_form_builds_from_the_instance_id_alone(): void {
        $form = new application_form(null, null, 'post', '', null, true, [
            'instance' => $this->instance->id,
        ]);

        $this->assertContains('city', $this->element_names($form));
    }

    /**
     * An instance id naming nothing is refused rather than resolved to something else.
     *
     * @return void
     */
    public function test_an_unknown_instance_id_is_refused(): void {
        global $DB;

        $missing = (int) $DB->get_field_sql('SELECT COALESCE(MAX(id), 0) FROM {enrol}') + 1;

        $this->expectException(\moodle_exception::class);
        new application_form(null, null, 'post', '', null, true, ['instance' => $missing]);
    }

    /**
     * A guest may not apply.
     *
     * @return void
     */
    public function test_check_access_refuses_a_guest(): void {
        $this->setGuestUser();

        $this->expectException(\moodle_exception::class);
        $this->make_form()->check_access_for_dynamic_submission();
    }

    /**
     * When allow_apply() refuses, so does the form.
     *
     * @return void
     */
    public function test_check_access_refuses_when_allow_apply_refuses(): void {
        global $DB;

        // The control: with new enrolments allowed, access is granted.
        $this->make_form()->check_access_for_dynamic_submission();

        $DB->set_field('enrol', 'customint6', 0, ['id' => $this->instance->id]);

        $this->expectException(\moodle_exception::class);
        $this->make_form()->check_access_for_dynamic_submission();
    }

    /**
     * A second application from the same user is refused.
     *
     * @return void
     */
    public function test_check_access_refuses_a_second_application(): void {
        $this->make_form()->check_access_for_dynamic_submission();

        $this->plugin->submit_application($this->instance, $this->applicant->id, (object) []);

        $this->expectException(\moodle_exception::class);
        $this->make_form()->check_access_for_dynamic_submission();
    }

    /**
     * The places cap is enforced before the form opens.
     *
     * @return void
     */
    public function test_check_access_refuses_when_the_places_cap_is_reached(): void {
        global $DB;

        $other = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $other->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $DB->set_field('enrol', 'customint3', 1, ['id' => $this->instance->id]);

        $this->expectException(\moodle_exception::class);
        $this->make_form()->check_access_for_dynamic_submission();
    }

    /**
     * Permissions are validated against the course category, not the course.
     *
     * dynamic_form's constructor runs external_api::validate_context(), which ends in
     * require_login() - and an applicant is by definition not yet enrolled, so a course
     * context would throw for exactly the people the form exists for.
     *
     * @return void
     */
    public function test_form_context_is_the_course_category(): void {
        $method = new \ReflectionMethod(application_form::class, 'get_context_for_dynamic_submission');
        $method->setAccessible(true);
        $context = $method->invoke($this->make_form());

        $this->assertInstanceOf(\context_coursecat::class, $context);
        $this->assertEquals(\context_course::instance($this->course->id)->get_parent_context()->id, $context->id);
    }

    /**
     * Only the resolved fields are rendered, each with its own confirmation checkbox.
     *
     * @return void
     */
    public function test_definition_renders_only_the_resolved_fields(): void {
        $names = $this->element_names($this->make_form());

        $this->assertContains('city', $names);
        $this->assertContains('institution', $names);
        $this->assertContains('confirm_s_city', $names);
        $this->assertContains('confirm_s_institution', $names);

        // Allowed at site level but not picked by this instance.
        $this->assertNotContains('department', $names);
        $this->assertNotContains('confirm_s_department', $names);
    }

    /**
     * A locked field is shown read-only and never gets a confirmation checkbox.
     *
     * Hiding it instead would mean snapshotting and mailing an approver a value the
     * applicant never saw; core's own answer to a locked field is the opposite of hiding it.
     *
     * @return void
     */
    public function test_a_locked_field_is_rendered_static_and_never_gets_a_confirm_checkbox(): void {
        set_config('field_lock_city', 'locked', 'auth_manual');

        $names = $this->element_names($this->make_form());

        $this->assertContains('locked_s_city', $names);
        $this->assertNotContains('city', $names);
        $this->assertNotContains('confirm_s_city', $names);

        // The control: the unlocked field in the same form still has both.
        $this->assertContains('institution', $names);
        $this->assertContains('confirm_s_institution', $names);
    }

    /**
     * An absent field produces no element at all, and no confirmation checkbox either.
     *
     * @return void
     */
    public function test_an_absent_field_produces_no_element_and_no_confirm_checkbox(): void {
        global $DB;

        $categoryid = $DB->insert_record('user_info_category', (object) ['name' => 'Extra', 'sortorder' => 1]);
        $fieldid = $DB->insert_record('user_info_field', (object) [
            'shortname' => 'hiddenfield', 'name' => 'Hidden field', 'datatype' => 'text',
            'categoryid' => $categoryid, 'sortorder' => 1, 'required' => 0, 'locked' => 0,
            'visible' => PROFILE_VISIBLE_NONE, 'forceunique' => 0, 'signup' => 0,
            'defaultdata' => '', 'param1' => 30, 'param2' => 2048,
        ]);
        $key = fields::custom_key((int) $fieldid);

        set_config('allowedfields', 's_city,' . $key, 'enrol_apply');
        $DB->set_field(
            'enrol',
            'customtext4',
            fieldset::from_keys(['s_city', $key])->to_json(),
            ['id' => $this->instance->id]
        );

        $names = $this->element_names($this->make_form());

        $this->assertNotContains('profile_field_hiddenfield', $names);
        $this->assertNotContains('confirm_' . $key, $names);
        $this->assertNotContains('locked_' . $key, $names);
        // The control: the visible field beside it is still rendered.
        $this->assertContains('city', $names);
    }

    /**
     * A required field filled with nothing but spaces is rejected server side.
     *
     * $CFG->strictformsrequired defaults to off, and with it off core's required rule does
     * not strip whitespace at all, so a single space satisfies it in the browser.
     *
     * @return void
     */
    public function test_a_whitespace_only_required_value_is_rejected(): void {
        $errors = $this->make_form()->validation([
            'city' => 'Campinas',
            'confirm_s_city' => 1,
            'institution' => '   ',
            'confirm_s_institution' => 1,
        ], []);

        $this->assertArrayHasKey('institution', $errors);

        // The control: a real value in the same field passes.
        $errors = $this->make_form()->validation([
            'city' => 'Campinas',
            'confirm_s_city' => 1,
            'institution' => 'UFMG',
            'confirm_s_institution' => 1,
        ], []);
        $this->assertArrayNotHasKey('institution', $errors);
    }

    /**
     * A field carrying a value must be confirmed before the application is submitted.
     *
     * @return void
     */
    public function test_an_unconfirmed_value_is_rejected(): void {
        $errors = $this->make_form()->validation([
            'city' => 'Campinas',
            'confirm_s_city' => 0,
            'institution' => 'UFMG',
            'confirm_s_institution' => 1,
        ], []);

        $this->assertArrayHasKey('confirm_s_city', $errors);
        $this->assertArrayNotHasKey('confirm_s_institution', $errors);
    }

    /**
     * At the threshold, every field still carries its own confirmation.
     *
     * @return void
     */
    public function test_three_fields_each_carry_their_own_confirmation(): void {
        $this->ask_for(['s_city', 's_institution', 's_department']);

        $names = $this->element_names($this->make_form());

        $this->assertContains('confirm_s_city', $names);
        $this->assertContains('confirm_s_institution', $names);
        $this->assertContains('confirm_s_department', $names);
        $this->assertNotContains('confirmall', $names);
    }

    /**
     * One field past the threshold, a single confirmation replaces all of them.
     *
     * A checkbox against each field reads well for two or three and turns into a wall of
     * ticking at the size of the default field set.
     *
     * @return void
     */
    public function test_more_than_three_fields_share_one_confirmation(): void {
        $this->ask_for(['s_city', 's_institution', 's_department', 's_phone1']);

        $names = $this->element_names($this->make_form());

        $this->assertContains('confirmall', $names);
        foreach (['s_city', 's_institution', 's_department', 's_phone1'] as $key) {
            $this->assertNotContains('confirm_' . $key, $names);
        }
    }

    /**
     * Only editable fields count towards the threshold.
     *
     * A locked field is rendered read-only and never confirmed, so it must not push a form
     * of three editable fields into the shared-confirmation mode.
     *
     * @return void
     */
    public function test_a_locked_field_does_not_count_towards_the_threshold(): void {
        $this->ask_for(['s_city', 's_institution', 's_department', 's_phone1']);
        set_config('field_lock_phone1', 'locked', 'auth_manual');

        $names = $this->element_names($this->make_form());

        $this->assertContains('confirm_s_city', $names);
        $this->assertNotContains('confirmall', $names);
        // The control: the locked field is present, read-only, and unconfirmed.
        $this->assertContains('locked_s_phone1', $names);
        $this->assertNotContains('confirm_s_phone1', $names);
    }

    /**
     * In the shared mode, the single confirmation is what blocks the submission.
     *
     * @return void
     */
    public function test_the_shared_confirmation_is_enforced(): void {
        $this->ask_for(['s_city', 's_institution', 's_department', 's_phone1']);

        $submitted = [
            'city' => 'Campinas',
            'institution' => 'UFMG',
            'department' => '',
            'phone1' => '',
        ];

        $errors = $this->make_form()->validation($submitted, []);
        $this->assertArrayHasKey('confirmall', $errors);

        // The control: ticked, the same submission is accepted.
        $errors = $this->make_form()->validation($submitted + ['confirmall' => 1], []);
        $this->assertArrayNotHasKey('confirmall', $errors);
    }

    /**
     * With nothing filled in there is nothing to confirm, in either mode.
     *
     * @return void
     */
    public function test_an_empty_form_needs_no_confirmation(): void {
        $this->ask_for(['s_city', 's_institution', 's_department', 's_phone1']);
        $empty = ['city' => '', 'institution' => '', 'department' => '', 'phone1' => ''];
        $this->assertArrayNotHasKey('confirmall', $this->make_form()->validation($empty, []));

        $this->ask_for(['s_city', 's_institution']);
        $empty = ['city' => '', 'institution' => ''];
        $errors = $this->make_form()->validation($empty, []);
        $this->assertArrayNotHasKey('confirm_s_city', $errors);
        $this->assertArrayNotHasKey('confirm_s_institution', $errors);
    }

    /**
     * The comment label uses the escaped spelling, because a moodleform label renders raw.
     *
     * @return void
     */
    public function test_the_comment_label_uses_the_escaped_spelling(): void {
        global $DB;

        $DB->set_field('enrol', 'customint7', 1, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'customtext2', 'Why you & who referred you', ['id' => $this->instance->id]);

        $form = $this->make_form();
        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
        $label = $mform->getElement('applydescription')->getLabel();

        $this->assertSame('Why you &amp; who referred you', $label);
    }

    /**
     * Two simultaneous submissions produce one application, not two.
     *
     * @return void
     */
    public function test_a_second_submission_for_the_same_user_creates_nothing(): void {
        global $DB;

        $first = $this->plugin->submit_application($this->instance, $this->applicant->id, (object) []);
        $second = $this->plugin->submit_application($this->instance, $this->applicant->id, (object) []);

        $this->assertTrue($first->was_created());
        $this->assertFalse($second->was_created());
        $this->assertFalse($second->is_refusal());

        $this->assertEquals(1, $DB->count_records('user_enrolments', [
            'enrolid' => $this->instance->id,
            'userid' => $this->applicant->id,
        ]));
    }

    /**
     * A submission never writes the applicant's profile.
     *
     * @return void
     */
    public function test_submitting_does_not_write_the_profile(): void {
        global $DB;

        $this->plugin->submit_application($this->instance, $this->applicant->id, (object) [
            'city' => 'Somewhere else entirely',
            'institution' => 'Another institution',
        ]);

        $this->assertSame('Campinas', $DB->get_field('user', 'city', ['id' => $this->applicant->id]));
    }

    /**
     * Build a form whose submitted data the test controls.
     *
     * @param array $submitted Values the form should report as submitted.
     * @return testable_application_form
     */
    protected function make_form_submitting(array $submitted): testable_application_form {
        $form = new testable_application_form(null, null, 'post', '', null, true, [
            'instance' => $this->instance->id,
            'id' => $this->course->id,
        ]);
        $form->set_test_data((object) $submitted);

        return $form;
    }

    /**
     * A refused application goes back to the enrolment page and says why.
     *
     * The fixture is the real race rather than a contrived state: the form is built while a
     * place is still free, so the applicant passed every check the form makes, and the place
     * is taken before the write. Until this changed, that produced a bare "Invalid access
     * detected" from applied.php, whose own gate found no enrolment row.
     *
     * @return void
     */
    public function test_a_refused_application_goes_back_to_the_enrolment_page_with_a_reason(): void {
        global $DB;

        $DB->set_field('enrol', 'customint3', 1, ['id' => $this->instance->id]);
        $form = $this->make_form_submitting([]);

        $other = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $other->id, null, 0, 0, ENROL_USER_SUSPENDED);

        // Drain anything the fixture queued, so the assertion below is about this submission.
        \core\notification::fetch();

        $url = $form->process_dynamic_submission();

        $this->assertStringContainsString('/enrol/index.php', $url);
        $this->assertStringNotContainsString('/enrol/apply/applied.php', $url);

        $notifications = \core\notification::fetch();
        $this->assertCount(1, $notifications);
        $this->assertSame(\core\output\notification::NOTIFY_ERROR, $notifications[0]->get_message_type());
        $this->assertSame(get_string('maxenrolledreached', 'enrol_apply'), $notifications[0]->get_message());

        /* Nothing was written for the applicant, which is precisely why the acknowledgement
           page would have refused them. */
        $this->assertFalse($DB->record_exists('user_enrolments', [
            'userid' => $this->applicant->id,
            'enrolid' => $this->instance->id,
        ]));
    }

    /**
     * An application that goes through still reaches the acknowledgement, silently.
     *
     * The control for the test above. Without it, routing every outcome to the enrolment page
     * would pass the refusal test while breaking every successful application.
     *
     * @return void
     */
    public function test_a_created_application_still_reaches_the_acknowledgement(): void {
        /* Submitting form rather than the plain one: get_data() returns null on a unit-built
           form, and the created path hands that straight to submission::create(), which is
           typed. Production never sees the null - apply.php calls this only inside
           `else if ($form->get_data())` and the web service only after is_validated() - so
           the fixture is what makes the test resemble the real call. */
        $form = $this->make_form_submitting([]);

        \core\notification::fetch();

        $sink = $this->redirectMessages();
        $url = $form->process_dynamic_submission();
        $sink->close();

        $this->assertStringContainsString('/enrol/apply/applied.php', $url);
        $this->assertStringNotContainsString('/enrol/index.php', $url);
        $this->assertCount(0, \core\notification::fetch());
    }

    /**
     * A refusal leaves no profile-update offer behind in the session.
     *
     * The offer used to be stashed whatever the write door did, so a refusal left the session
     * holding an offer to update a profile for an application that does not exist.
     *
     * The submitted data is supplied by the fixture form on purpose: with the null that a
     * unit-built form really returns, diff::compute() finds no changes and offer::stash()
     * returns before writing, so this test would pass against a build that stashes
     * unconditionally. The control below is what proves it does not.
     *
     * @return void
     */
    public function test_a_refused_application_stashes_no_profile_offer(): void {
        global $DB;

        set_config('allowprofilewrite', 1, 'enrol_apply');
        $DB->set_field('enrol', 'customint8', 1, ['id' => $this->instance->id]);
        $DB->set_field('enrol', 'customint3', 1, ['id' => $this->instance->id]);

        $refusedform = $this->make_form_submitting(['city' => 'Somewhere else entirely']);

        $other = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $other->id, null, 0, 0, ENROL_USER_SUSPENDED);

        $refusedform->process_dynamic_submission();

        $this->assertSame([], offer::peek((int) $this->instance->id));
    }

    /**
     * ...and an application that goes through does leave one.
     *
     * The control for the test above, and it is not optional: it is what proves the stash can
     * happen at all under this fixture, so that its absence above means the refusal skipped it
     * rather than that nothing was ever stashable.
     *
     * @return void
     */
    public function test_a_created_application_does_stash_a_profile_offer(): void {
        global $DB;

        set_config('allowprofilewrite', 1, 'enrol_apply');
        $DB->set_field('enrol', 'customint8', 1, ['id' => $this->instance->id]);

        $form = $this->make_form_submitting(['city' => 'Somewhere else entirely']);

        $sink = $this->redirectMessages();
        $form->process_dynamic_submission();
        $sink->close();

        $this->assertNotSame([], offer::peek((int) $this->instance->id));
    }
}
