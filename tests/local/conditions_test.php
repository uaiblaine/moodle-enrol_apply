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
 * Tests for the display conditions of application questions.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use core_customfield\api;
use core_customfield\field_controller;
use enrol_apply\customfield\application_handler;
use enrol_apply\form\application_form;
use enrol_apply\form\condition_form;
use enrol_apply\observers;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the display conditions of application questions.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(conditions::class)]
#[CoversClass(answers::class)]
#[CoversClass(condition_form::class)]
#[CoversClass(observers::class)]
#[CoversClass(application_handler::class)]
final class conditions_test extends \advanced_testcase {
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
        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->plugin = enrol_get_plugin('apply');
        [$this->course, $this->instance] = $this->create_instance();
        $this->categoryid = $this->create_category('enrol_apply', application_handler::AREA, (int) $this->instance->id);
    }

    /**
     * A course with an apply instance.
     *
     * @return array The course and the instance record.
     */
    private function create_instance(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $enrolid = $this->plugin->add_instance($course, $this->plugin->get_instance_defaults());
        return [$course, $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST)];
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
        return (int) $this->getDataGenerator()->get_plugin_generator('core_customfield')->create_category([
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
        ])->get('id');
    }

    /**
     * Create a question, in the instance's category unless another one is given.
     *
     * @param string $type Field type.
     * @param string $shortname Shortname.
     * @param array $config Configdata.
     * @param int|null $categoryid Category.
     * @return field_controller
     */
    private function create_field(string $type, string $shortname, array $config = [], ?int $categoryid = null): field_controller {
        return $this->getDataGenerator()->get_plugin_generator('core_customfield')->create_field([
            'categoryid' => $categoryid ?? $this->categoryid,
            'type' => $type,
            'shortname' => $shortname,
            'name' => ucfirst($shortname),
            'configdata' => $config,
        ]);
    }

    /**
     * Store a condition of the main instance, unchecked.
     *
     * @param field_controller $field The controlled question.
     * @param field_controller $controller The controlling question.
     * @param string $operator Operator.
     * @param int[] $positions Positions.
     * @param int|null $enrolid Instance, the main one by default.
     * @return void
     */
    private function condition(
        field_controller $field,
        field_controller $controller,
        string $operator,
        array $positions = [],
        ?int $enrolid = null
    ): void {
        conditions::save(
            $enrolid ?? (int) $this->instance->id,
            (int) $field->get('id'),
            (int) $controller->get('id'),
            $operator,
            $positions
        );
    }

    /**
     * Whether a question is shown for the submitted data, read through the instance's effective conditions.
     *
     * @param field_controller $field The question.
     * @param array $data Submitted data.
     * @return bool
     */
    private function visible(field_controller $field, array $data): bool {
        $fields = answers::fields($this->instance);
        return conditions::is_visible((int) $field->get('id'), conditions::effective($this->instance, $fields), $fields, $data);
    }

    /**
     * A shared category switched on for an instance.
     *
     * @param int $enrolid Enrol instance id.
     * @param int|null $categoryid The shared category, a new one when null.
     * @return int The shared category id.
     */
    private function enable_shared(int $enrolid, ?int $categoryid = null): int {
        $categoryid ??= $this->create_category('core_customfield', 'shared', 0);
        (new \core_customfield\shared(0, (object) [
            'categoryid' => $categoryid,
            'component' => 'enrol_apply',
            'area' => application_handler::AREA,
            'itemid' => $enrolid,
        ]))->create();
        return $categoryid;
    }

    /**
     * A select condition shows the question for the chosen positions only.
     *
     * @return void
     */
    public function test_a_select_condition_shows_the_question_for_the_chosen_options_only(): void {
        $shift = $this->create_field('select', 'shift', ['options' => "Morning\nAfternoon\nNight"]);
        $reason = $this->create_field('text', 'reason');
        $this->condition($reason, $shift, conditions::OPERATOR_IN, [1, 3]);

        $this->assertTrue($this->visible($reason, ['customfield_shift' => '1']));
        $this->assertTrue($this->visible($reason, ['customfield_shift' => 3]));
        $this->assertFalse($this->visible($reason, ['customfield_shift' => '2']));
        $this->assertFalse($this->visible($reason, ['customfield_shift' => '0']));
        $this->assertFalse($this->visible($reason, []));
        // The control: the controller itself is unconditional.
        $this->assertTrue($this->visible($shift, []));
    }

    /**
     * A checkbox condition follows "checked" and "not checked".
     *
     * @return void
     */
    public function test_a_checkbox_condition_follows_checked_and_not_checked(): void {
        $employed = $this->create_field('checkbox', 'employed');
        $employer = $this->create_field('text', 'employer');
        $jobsearch = $this->create_field('text', 'jobsearch');
        $this->condition($employer, $employed, conditions::OPERATOR_CHECKED);
        $this->condition($jobsearch, $employed, conditions::OPERATOR_NOTCHECKED);

        $this->assertTrue($this->visible($employer, ['customfield_employed' => '1']));
        $this->assertFalse($this->visible($jobsearch, ['customfield_employed' => '1']));
        $this->assertFalse($this->visible($employer, ['customfield_employed' => '0']));
        $this->assertTrue($this->visible($jobsearch, ['customfield_employed' => '0']));
        $this->assertTrue($this->visible($jobsearch, []));
    }

    /**
     * A question whose controller is hidden is hidden, whatever value the controller still holds.
     *
     * @return void
     */
    public function test_a_question_whose_controller_is_hidden_is_hidden_too(): void {
        $hasdoc = $this->create_field('checkbox', 'hasdoc');
        $doctype = $this->create_field('select', 'doctype', ['options' => "Passport\nID card"]);
        $docnumber = $this->create_field('text', 'docnumber');
        $this->condition($doctype, $hasdoc, conditions::OPERATOR_CHECKED);
        $this->condition($docnumber, $doctype, conditions::OPERATOR_IN, [2]);

        $this->assertFalse($this->visible($docnumber, ['customfield_hasdoc' => '0', 'customfield_doctype' => '2']));
        // The control: the same answer with the first box checked shows the question.
        $this->assertTrue($this->visible($docnumber, ['customfield_hasdoc' => '1', 'customfield_doctype' => '2']));
    }

    /**
     * A stored condition the questions no longer support is ignored: the question is shown.
     *
     * @return void
     */
    public function test_a_condition_that_no_longer_holds_is_not_effective(): void {
        $first = $this->create_field('text', 'first');
        $later = $this->create_field('checkbox', 'later');
        $shift = $this->create_field('select', 'shift', ['options' => "Morning\nNight"]);
        $last = $this->create_field('text', 'last');
        // Written past problem(), as a reorder, an option edit or a restore of an older site leaves them.
        $this->condition($first, $later, conditions::OPERATOR_CHECKED);
        $this->condition($last, $shift, conditions::OPERATOR_IN, [3]);

        $this->assertSame([], conditions::effective($this->instance, answers::fields($this->instance)));
        $this->assertTrue($this->visible($first, []));
        $this->assertTrue($this->visible($last, ['customfield_shift' => '1']));
        $this->assertCount(2, conditions::stored((int) $this->instance->id));
    }

    /**
     * A conditional required question is required only when the submitted data shows it.
     *
     * @return void
     */
    public function test_a_conditional_required_question_is_required_only_when_shown(): void {
        $employed = $this->create_field('checkbox', 'employed');
        $employer = $this->create_field('text', 'employer', ['required' => 1]);
        $consent = $this->create_field('checkbox', 'consent', ['required' => 1]);
        $this->condition($employer, $employed, conditions::OPERATOR_CHECKED);
        $this->condition($consent, $employed, conditions::OPERATOR_CHECKED);

        $errors = answers::validate($this->instance, ['customfield_employed' => '1', 'customfield_employer' => '  '], []);
        $this->assertArrayHasKey('customfield_employer', $errors);
        $this->assertArrayHasKey('customfield_consent', $errors);

        $hidden = ['customfield_employed' => '0', 'customfield_employer' => ''];
        $this->assertSame([], answers::validate($this->instance, $hidden, []));

        // The control: answered, the shown questions validate.
        $answered = ['customfield_employed' => '1', 'customfield_employer' => 'ACME', 'customfield_consent' => '1'];
        $this->assertSame([], answers::validate($this->instance, $answered, []));
    }

    /**
     * A hidden question is not validated by its type either.
     *
     * @return void
     */
    public function test_a_hidden_question_skips_its_type_validation(): void {
        $employed = $this->create_field('checkbox', 'employed');
        $years = $this->create_field('number', 'years', ['minimumvalue' => 1, 'maximumvalue' => 10]);
        $this->condition($years, $employed, conditions::OPERATOR_CHECKED);

        $this->assertSame([], answers::validate($this->instance, ['customfield_employed' => '0', 'customfield_years' => 50], []));
        // The control: shown, the same answer is out of range.
        $this->assertArrayHasKey(
            'customfield_years',
            answers::validate($this->instance, ['customfield_employed' => '1', 'customfield_years' => 50], [])
        );
    }

    /**
     * What was typed into a question before it was hidden is not frozen into the snapshot.
     *
     * @return void
     */
    public function test_the_answer_to_a_hidden_question_is_discarded(): void {
        $employed = $this->create_field('checkbox', 'employed');
        $employer = $this->create_field('text', 'employer');
        $this->condition($employer, $employed, conditions::OPERATOR_CHECKED);
        $key = answers::key((int) $employer->get('id'));

        $hidden = answers::submitted_values($this->instance, (object) [
            'customfield_employed' => 0,
            'customfield_employer' => 'ACME',
        ]);
        $this->assertNotContains($key, array_column($hidden, 'key'));
        $this->assertContains(answers::key((int) $employed->get('id')), array_column($hidden, 'key'));

        // The control: shown, the answer is frozen.
        $shown = answers::submitted_values($this->instance, (object) [
            'customfield_employed' => 1,
            'customfield_employer' => 'ACME',
        ]);
        $this->assertContains($key, array_column($shown, 'key'));
    }

    /**
     * The application form hides a conditional question through its whole chain and drops its required rule.
     *
     * @return void
     */
    public function test_the_form_hides_a_conditional_question_and_drops_its_required_rule(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $name = $this->create_field('text', 'fullname', ['required' => 1]);
        $hasdoc = $this->create_field('checkbox', 'hasdoc');
        $doctype = $this->create_field('select', 'doctype', ['options' => "Passport\nID card\nOther"]);
        $docnumber = $this->create_field('text', 'docnumber', ['required' => 1]);
        $this->condition($doctype, $hasdoc, conditions::OPERATOR_CHECKED);
        $this->condition($docnumber, $doctype, conditions::OPERATOR_IN, [1, 3]);

        $form = new application_form(null, null, 'post', '', null, true, [
            'instance' => $this->instance->id,
            'id' => $this->course->id,
        ]);
        $mform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
        $hideifs = (new \ReflectionProperty(\MoodleQuickForm::class, '_hideifs'))->getValue($mform);

        $this->assertContains('customfield_doctype', $hideifs['customfield_hasdoc']['notchecked']['1']);
        // Hidden outside the chosen positions, "nothing chosen" included, and with its controller.
        $this->assertContains('customfield_docnumber', $hideifs['customfield_doctype']['in']['0|2']);
        $this->assertContains('customfield_docnumber', $hideifs['customfield_hasdoc']['notchecked']['1']);

        $this->assertNotContains('customfield_docnumber', $mform->_required);
        $this->assertArrayNotHasKey('customfield_docnumber', $mform->_rules);
        // The control: an unconditional required question keeps core's rule.
        $this->assertContains('customfield_' . $name->get('shortname'), $mform->_required);
        $this->assertSame('required', $mform->_rules['customfield_fullname'][0]['type']);
    }

    /**
     * The configuration form refuses every condition the rule forbids, and accepts a good one.
     *
     * @return void
     */
    public function test_the_configuration_form_refuses_what_the_rule_forbids(): void {
        $text = $this->create_field('text', 'motivation');
        $shift = $this->create_field('select', 'shift', ['options' => "Morning\nNight"]);
        $reason = $this->create_field('text', 'reason');
        $later = $this->create_field('checkbox', 'later');

        [, $other] = $this->create_instance();
        $othercategory = $this->create_category('enrol_apply', application_handler::AREA, (int) $other->id);
        $foreign = $this->create_field('text', 'foreign', [], $othercategory);

        $fields = answers::fields($this->instance);
        $errors = function (int $fieldid, array $data) use ($fields): array {
            $form = new condition_form(null, [
                'enrolid' => (int) $this->instance->id,
                'fieldid' => $fieldid,
                'fields' => $fields,
                'controllerid' => (int) ($data['dependsonfieldid'] ?? 0),
            ]);
            return $form->validation($data, []);
        };
        $shiftid = (int) $shift->get('id');
        $positions = condition_form::positions_name($shiftid);

        $this->assertSame(
            get_string('conditionerror_later', 'enrol_apply'),
            $errors((int) $reason->get('id'), [
                'dependsonfieldid' => (int) $later->get('id'),
                condition_form::operator_name((int) $later->get('id')) => conditions::OPERATOR_CHECKED,
            ])['dependsonfieldid'] ?? null
        );
        $this->assertSame(
            get_string('conditionerror_type', 'enrol_apply'),
            $errors((int) $reason->get('id'), ['dependsonfieldid' => (int) $text->get('id')])['dependsonfieldid'] ?? null
        );
        $this->assertArrayHasKey(
            $positions,
            $errors((int) $reason->get('id'), ['dependsonfieldid' => $shiftid, $positions => [3 => 1]])
        );
        $this->assertSame(
            get_string('conditionerror_field', 'enrol_apply'),
            $errors((int) $foreign->get('id'), ['dependsonfieldid' => $shiftid, $positions => [1 => 1]])['dependsonfieldid'] ?? null
        );

        // The control: an earlier select with an existing option is accepted.
        $this->assertSame([], $errors((int) $reason->get('id'), ['dependsonfieldid' => $shiftid, $positions => [1 => 0, 2 => 1]]));
    }

    /**
     * Saving twice keeps one condition per question, the last one.
     *
     * @return void
     */
    public function test_saving_replaces_the_question_condition(): void {
        global $DB;

        $shift = $this->create_field('select', 'shift', ['options' => "Morning\nNight"]);
        $reason = $this->create_field('text', 'reason');
        $this->condition($reason, $shift, conditions::OPERATOR_IN, [2, 1, 2]);
        $this->condition($reason, $shift, conditions::OPERATOR_IN, [2]);

        $this->assertSame(1, $DB->count_records(conditions::TABLE, ['enrolid' => $this->instance->id]));
        $this->assertSame([2], conditions::stored((int) $this->instance->id)[(int) $reason->get('id')]->positions);
    }

    /**
     * The summary names the controller and the options, and says when a condition went stale.
     *
     * @return void
     */
    public function test_the_summary_says_when_a_question_is_shown(): void {
        $shift = $this->create_field('select', 'shift', ['options' => "Morning\nR&D < Night"]);
        $employed = $this->create_field('checkbox', 'employed');
        $reason = $this->create_field('text', 'reason');
        $employer = $this->create_field('text', 'employer');
        $plain = $this->create_field('text', 'plain');
        $this->condition($reason, $shift, conditions::OPERATOR_IN, [2]);
        $this->condition($employer, $employed, conditions::OPERATOR_NOTCHECKED);
        $this->condition($shift, $plain, conditions::OPERATOR_CHECKED);

        $fields = answers::fields($this->instance);
        $stored = conditions::stored((int) $this->instance->id);
        $effective = conditions::effective($this->instance, $fields);
        $summary = fn(field_controller $field): string
            => conditions::summary((int) $field->get('id'), $stored, $effective, $fields);

        $this->assertSame(
            get_string('conditionsummaryin', 'enrol_apply', (object) ['field' => 'Shift', 'options' => 'R&D < Night']),
            $summary($reason)
        );
        $this->assertSame(get_string('conditionsummarynotchecked', 'enrol_apply', 'Employed'), $summary($employer));
        $this->assertSame(get_string('conditionstale', 'enrol_apply'), $summary($shift));
        $this->assertSame(get_string('conditionalways', 'enrol_apply'), $summary($plain));
    }

    /**
     * Deleting an instance removes its conditions, including those between fields of a shared category.
     *
     * @return void
     */
    public function test_delete_instance_removes_the_instance_conditions(): void {
        global $DB;

        [, $other] = $this->create_instance();
        $shared = $this->enable_shared((int) $this->instance->id);
        $this->enable_shared((int) $other->id, $shared);
        $hasdoc = $this->create_field('checkbox', 'hasdoc', [], $shared);
        $doc = $this->create_field('text', 'doc', [], $shared);
        $this->condition($doc, $hasdoc, conditions::OPERATOR_CHECKED);
        $this->condition($doc, $hasdoc, conditions::OPERATOR_CHECKED, [], (int) $other->id);

        $this->plugin->delete_instance($this->instance);

        $this->assertFalse($DB->record_exists(conditions::TABLE, ['enrolid' => $this->instance->id]));
        // The controls: the other instance keeps its condition, and the shared fields survive.
        $this->assertTrue($DB->record_exists(conditions::TABLE, ['enrolid' => $other->id]));
        $this->assertTrue($DB->record_exists('customfield_field', ['id' => $doc->get('id')]));
    }

    /**
     * With the plugin disabled, deleting the course leaves no condition behind.
     *
     * @return void
     */
    public function test_the_course_deleted_observer_sweeps_orphaned_conditions(): void {
        global $DB;

        [, $other] = $this->create_instance();
        $shared = $this->enable_shared((int) $this->instance->id);
        $this->enable_shared((int) $other->id, $shared);
        $hasdoc = $this->create_field('checkbox', 'hasdoc', [], $shared);
        $doc = $this->create_field('text', 'doc', [], $shared);
        $this->condition($doc, $hasdoc, conditions::OPERATOR_CHECKED);
        $this->condition($doc, $hasdoc, conditions::OPERATOR_CHECKED, [], (int) $other->id);

        $enabled = enrol_get_plugins(true);
        unset($enabled['apply']);
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        delete_course($this->course, false);

        // The precondition: core skipped the plugin and deleted the instance itself.
        $this->assertFalse($DB->record_exists('enrol', ['id' => $this->instance->id]));
        $this->assertFalse($DB->record_exists(conditions::TABLE, ['enrolid' => $this->instance->id]));
        // The control: the sweep takes orphans only.
        $this->assertTrue($DB->record_exists(conditions::TABLE, ['enrolid' => $other->id]));
    }

    /**
     * Deleting a field removes the conditions naming it, as the controlled or the controlling question.
     *
     * @return void
     */
    public function test_deleting_a_field_removes_the_conditions_naming_it(): void {
        global $DB;

        $employed = $this->create_field('checkbox', 'employed');
        $employer = $this->create_field('text', 'employer');
        $salary = $this->create_field('text', 'salary');
        $student = $this->create_field('checkbox', 'student');
        $school = $this->create_field('text', 'school');
        $this->condition($employer, $employed, conditions::OPERATOR_CHECKED);
        $this->condition($salary, $employed, conditions::OPERATOR_CHECKED);
        $this->condition($school, $student, conditions::OPERATOR_CHECKED);

        // Read before deleting: a deleted persistent's id reads 0, which no row names.
        $employerid = (int) $employer->get('id');
        $employedid = (int) $employed->get('id');

        api::delete_field_configuration($employer);
        $this->assertFalse($DB->record_exists(conditions::TABLE, ['fieldid' => $employerid]));
        $this->assertTrue($DB->record_exists(conditions::TABLE, ['dependsonfieldid' => $employedid]));

        api::delete_field_configuration($employed);
        $this->assertFalse($DB->record_exists(conditions::TABLE, ['dependsonfieldid' => $employedid]));
        // The control: a condition naming neither deleted field stays.
        $this->assertTrue($DB->record_exists(conditions::TABLE, ['fieldid' => $school->get('id')]));
    }

    /**
     * The fields page lists each question with when it is shown, and offers a removal only where there is a condition.
     *
     * @return void
     */
    public function test_the_fields_page_lists_the_conditions(): void {
        global $PAGE;

        $employed = $this->create_field('checkbox', 'employed');
        $employer = $this->create_field('text', 'employer');
        $this->create_field('text', 'R&D < notes');
        $this->condition($employer, $employed, conditions::OPERATOR_CHECKED);

        $PAGE->set_url('/enrol/apply/fields.php', ['id' => $this->instance->id]);
        $html = $PAGE->get_renderer('enrol_apply')->field_conditions($this->instance);

        $this->assertStringContainsString(s(get_string('conditionsummarychecked', 'enrol_apply', 'Employed')), $html);
        $this->assertStringContainsString('R&amp;D &lt; notes', $html);
        $this->assertStringNotContainsString('R&amp;amp;D', $html);
        $this->assertSame(1, substr_count($html, 'name="action" value="remove"'));
        $this->assertStringContainsString('name="sesskey" value="' . sesskey() . '"', $html);
        // The first question has nothing before it to be controlled by: no edit link.
        $this->assertStringNotContainsString('fieldid=' . $employed->get('id'), $html);
        $this->assertStringContainsString('fieldid=' . $employer->get('id'), $html);
    }
}
