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
 * Tests for the sections and the summary strip of the instance edit form.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// At file scope so the CoversClass target below resolves when PHPUnit reads the attribute.
require_once($CFG->dirroot . '/enrol/apply/edit_form.php');

/**
 * Tests for the sections and the summary strip of the instance edit form.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_edit_form::class)]
final class edit_form_sections_test extends \advanced_testcase {
    /** @var string[] The section headers, in the order the form opens them. */
    private const SECTIONS = [
        'sectiongeneral',
        'sectionwhen',
        'sectionwho',
        'sectionform',
        'sectiongroups',
        'sectionafter',
        'sectionnotify',
    ];

    /** @var string[] The sections closed unless they hold a value other than the default. */
    private const CLOSED_BY_DEFAULT = ['sectionwho', 'sectionafter', 'sectionnotify'];

    /** @var \stdClass Course the apply instance belongs to. */
    private $course;

    /** @var \stdClass The enrol_apply instance record. */
    private $instance;

    /** @var \enrol_apply_plugin The plugin instance. */
    private $plugin;

    /**
     * Create a course carrying a single enabled apply enrolment instance with the defaults.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB, $PAGE;

        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        // The notification default is "nobody", so a recipient list is a value of its own.
        set_config('notifycoursebased', 0, 'enrol_apply');
        set_config('enrolperiod', 0, 'enrol_apply');

        $this->plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        // Enabled and accepting applications, whatever the site defaults say.
        $instanceid = $this->plugin->add_instance(
            $this->course,
            ['status' => ENROL_INSTANCE_ENABLED, 'customint6' => 1] + $this->plugin->get_instance_defaults()
        );
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        $PAGE->set_url(new \moodle_url('/enrol/apply/edit.php', ['courseid' => $this->course->id]));

        $this->setAdminUser();
    }

    /**
     * Build the edit form for an instance and return its QuickForm.
     *
     * @param \stdClass|null $instance The instance, the saved one by default.
     * @return \MoodleQuickForm The form the definition built.
     */
    private function mform(?\stdClass $instance = null): \MoodleQuickForm {
        $context = \context_course::instance($this->course->id);
        $form = new \enrol_apply_edit_form(null, [$instance ?? $this->instance, $this->plugin, $context]);

        $property = new \ReflectionProperty(\moodleform::class, '_form');
        return $property->getValue($form);
    }

    /**
     * The header each named element sits under, in form order; '' for one before every header.
     *
     * @param \MoodleQuickForm $mform The form.
     * @return array Header names keyed by element name; the headers themselves map to themselves.
     */
    private function section_of(\MoodleQuickForm $mform): array {
        $sections = [];
        $current = '';
        foreach ($mform->_elements as $element) {
            if ($element->getType() === 'header') {
                $current = $element->getName();
            }
            $name = (string) $element->getName();
            if ($name !== '') {
                $sections[$name] = $current;
            }
        }
        return $sections;
    }

    /**
     * The headers of the form, in order.
     *
     * @param \MoodleQuickForm $mform The form.
     * @return string[] Header names.
     */
    private function headers(\MoodleQuickForm $mform): array {
        $headers = [];
        foreach ($mform->_elements as $element) {
            if ($element->getType() === 'header') {
                $headers[] = $element->getName();
            }
        }
        return $headers;
    }

    /**
     * The summary facts the form states for an instance.
     *
     * @param \stdClass $instance The instance as the form would be given it.
     * @return string[] The facts.
     */
    private function facts(\stdClass $instance): array {
        $context = \context_course::instance($this->course->id);
        $form = new \enrol_apply_edit_form(null, [$instance, $this->plugin, $context]);
        $method = new \ReflectionMethod(\enrol_apply_edit_form::class, 'summary_facts');
        return $method->invoke($form, $instance);
    }

    /**
     * Seven sections open in order, and every element sits in the section it belongs to.
     *
     * Read with the cohort picker and the profile-write switch both offered, so neither is its
     * hidden constant.
     *
     * Changes that must make it fail: an element moved into another section, or a header dropped
     * or reordered.
     *
     * @return void
     */
    public function test_the_sections_come_in_order_and_hold_their_elements(): void {
        set_config('allowprofilewrite', 1, 'enrol_apply');
        $this->getDataGenerator()->create_cohort(['name' => 'Candidates']);

        $mform = $this->mform();
        $this->assertSame(self::SECTIONS, $this->headers($mform));

        $expected = [
            'sectiongeneral' => ['name', 'status', 'customint6', 'roleid'],
            'sectionwhen' => ['enrolstartdate', 'enrolenddate', 'customint3', 'customint4', 'currentoccupancy'],
            'sectionwho' => ['customint5'],
            'sectionform' => ['customtext1', 'customint7', 'customtext2', 'questions'],
            'sectiongroups' => ['groupselect', 'customchar1', 'customchar2', 'groupkeysummary'],
            'sectionafter' => ['enrolperiod', 'expirynotify', 'expirythreshold', 'customint8'],
            'sectionnotify' => ['notify'],
        ];
        $sections = $this->section_of($mform);
        foreach ($expected as $section => $names) {
            foreach ($names as $name) {
                $this->assertArrayHasKey($name, $sections, "{$name} should be on the form");
                $this->assertSame($section, $sections[$name], "{$name} should be in {$section}");
            }
        }
        $this->assertSame('select', $mform->getElementType('customint5'));
        $this->assertSame('advcheckbox', $mform->getElementType('customint8'));
        $this->assertArrayNotHasKey('nocohorts', $sections);

        // The requested profile fields, whichever way the site's pool sets them, are in the form section.
        $requested = array_filter(
            array_keys($sections),
            static fn(string $name): bool => in_array($name, ['requestedfields', 'nofieldsoffered'], true)
                || str_starts_with($name, 'fieldgroup_')
        );
        $this->assertNotEmpty($requested);
        foreach ($requested as $name) {
            $this->assertSame('sectionform', $sections[$name], "{$name} should be in sectionform");
        }
        // Its own header is gone: the fields are part of the application form.
        $this->assertArrayNotHasKey('requestedfieldsheader', $sections);

        // The summary strip is the one element before the first header.
        $this->assertSame('html', $mform->_elements[0]->getType());
        $this->assertSame('header', $mform->_elements[1]->getType());

        /* The buttons come last. That closeHeaderBefore() ends the last section before them is
           not testable here: QuickForm's renderer is one object for the whole process and keeps
           every stop element any form ever registered, core's own 'buttonar' among them. */
        // Hidden elements are left out: formslib appends its own (the submission marker, sesskey).
        $visible = array_values(array_filter(
            $mform->_elements,
            static fn($element): bool => $element->getType() !== 'hidden'
        ));
        $this->assertSame('buttonar', end($visible)->getName());
    }

    /**
     * The hidden constants that stand in for the cohort picker and the profile-write switch stay
     * in their sections, and the cohort section says why it has no picker.
     *
     * Changes that must make it fail: the hidden constant dropped (update_instance() could then
     * never clear a stored value), or the explanation left out of its section.
     *
     * @return void
     */
    public function test_the_hidden_constants_stay_in_their_sections(): void {
        set_config('allowprofilewrite', 0, 'enrol_apply');

        $mform = $this->mform();
        $sections = $this->section_of($mform);

        $this->assertSame('hidden', $mform->getElementType('customint5'));
        $this->assertSame('sectionwho', $sections['customint5']);
        $this->assertSame('sectionwho', $sections['nocohorts']);
        $this->assertSame('hidden', $mform->getElementType('customint8'));
        $this->assertSame('sectionafter', $sections['customint8']);
    }

    /**
     * With every value at its default, General, When and how many, Application form and Groups
     * are open, and the other three are closed.
     *
     * Changes that must make it fail: a closed section opened whatever it holds, or an open one
     * closed.
     *
     * @return void
     */
    public function test_the_default_instance_opens_four_sections(): void {
        $mform = $this->mform();

        foreach (self::SECTIONS as $section) {
            $this->assertArrayHasKey($section, $mform->_collapsibleElements);
            $closed = in_array($section, self::CLOSED_BY_DEFAULT, true);
            $this->assertSame($closed, $mform->_collapsibleElements[$section], "{$section} closed: " . var_export($closed, true));
        }
    }

    /**
     * A value other than the default in each closed section, with the section it opens.
     *
     * @return array The instance field, its value and the section, keyed by a readable case name.
     */
    public static function opener_provider(): array {
        return [
            'a cohort restriction' => ['customint5', -1, 'sectionwho'],
            'an enrolment duration' => ['enrolperiod', DAYSECS, 'sectionafter'],
            'an expiry notice' => ['expirynotify', 1, 'sectionafter'],
            'the profile write' => ['customint8', 1, 'sectionafter'],
            'a notification recipient list' => ['customtext3', '$@ALL@$', 'sectionnotify'],
        ];
    }

    /**
     * A closed section opens when the instance holds a value other than the default in it, and
     * only that section opens.
     *
     * The cohort is the sentinel a cross-site restore writes, which the picker still offers.
     *
     * @param string $field The instance field.
     * @param mixed $value Its value.
     * @param string $section The section it must open.
     * @return void
     */
    #[DataProvider('opener_provider')]
    public function test_a_configured_value_opens_its_section(string $field, $value, string $section): void {
        set_config('allowprofilewrite', 1, 'enrol_apply');
        $instance = clone $this->instance;
        $instance->{$field} = $value;

        $mform = $this->mform($instance);

        $this->assertFalse($mform->_collapsibleElements[$section], "{$section} should open");
        foreach (array_diff(self::CLOSED_BY_DEFAULT, [$section]) as $other) {
            $this->assertTrue($mform->_collapsibleElements[$other], "{$other} should stay closed");
        }
    }

    /**
     * A new instance has no summary strip; a saved one has it, as an information alert carrying
     * its facts.
     *
     * Changes that must make it fail: the strip drawn for an instance that has not been saved,
     * or dropped for a saved one.
     *
     * @return void
     */
    public function test_only_a_saved_instance_carries_the_strip(): void {
        $new = (object) $this->plugin->get_instance_defaults();
        $new->id = null;
        $new->courseid = $this->course->id;
        foreach ($this->mform($new)->_elements as $element) {
            $this->assertNotSame('html', $element->getType(), 'a new instance has nothing to summarise');
        }

        $html = $this->mform()->_elements[0]->toHtml();
        $this->assertStringContainsString('alert-info', $html);
        foreach ($this->facts($this->instance) as $fact) {
            $this->assertStringContainsString(s($fact), $html);
        }
    }

    /**
     * The states the first fact reports, each with the instance values that produce it.
     *
     * @return array Instance values and the expected string id, keyed by a readable case name.
     */
    public static function state_provider(): array {
        return [
            'disabled' => [['status' => ENROL_INSTANCE_DISABLED], 'summarydisabled'],
            'not accepting new applications' => [['customint6' => 0], 'summarynotaccepting'],
            'not open yet' => [['enrolstartdate' => 'future'], 'summarynotopen'],
            'closed' => [['enrolenddate' => 'past'], 'summaryclosed'],
            'applicant limit reached' => [['customint3' => 1], 'summaryfull'],
            'open until a date' => [['enrolenddate' => 'future'], 'summaryacceptinguntil'],
            'open' => [[], 'summaryaccepting'],
        ];
    }

    /**
     * The first fact says whether the method accepts applications, in allow_apply()'s order,
     * naming the date where there is one.
     *
     * One application is on the method, so an applicant limit of 1 is reached.
     *
     * @param array $values Instance values; 'future' and 'past' stand for a date a day away.
     * @param string $expected The string id of the first fact.
     * @return void
     */
    #[DataProvider('state_provider')]
    public function test_the_strip_says_whether_applications_are_accepted(array $values, string $expected): void {
        $applicant = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);

        $instance = clone $this->instance;
        $date = null;
        foreach ($values as $field => $value) {
            if ($value === 'future' || $value === 'past') {
                $value = $date = time() + ($value === 'future' ? DAYSECS : -DAYSECS);
            }
            $instance->{$field} = $value;
        }

        $facts = $this->facts($instance);
        $this->assertSame(get_string($expected, 'enrol_apply', $date === null ? null : userdate($date)), $facts[0]);
    }

    /**
     * The second fact counts the places taken of the limit, or says there is no limit.
     *
     * Only an active enrolment takes a place: the pending application beside it does not.
     *
     * @return void
     */
    public function test_the_strip_counts_the_places_taken(): void {
        $generator = $this->getDataGenerator();
        $this->plugin->enrol_user($this->instance, $generator->create_user()->id, null, 0, 0, ENROL_USER_ACTIVE);
        $this->plugin->enrol_user($this->instance, $generator->create_user()->id, null, 0, 0, ENROL_USER_SUSPENDED);

        $this->assertSame(get_string('summaryplacesnolimit', 'enrol_apply', 1), $this->facts($this->instance)[1]);

        $instance = clone $this->instance;
        $instance->customint4 = 10;
        $this->assertSame(
            get_string('summaryplaces', 'enrol_apply', (object) ['taken' => 1, 'limit' => 10]),
            $this->facts($instance)[1]
        );
    }

    /**
     * The group key modes, with the fact each one is worded as.
     *
     * @return array The stored mode and the expected string id, keyed by a readable case name.
     */
    public static function key_mode_provider(): array {
        return [
            'off' => ['', 'summarykeysoff'],
            'a valid key enrols at once' => ['any', 'summarykeysany'],
            'key only' => ['required', 'summarykeysrequired'],
            'key only, reviewed' => ['review', 'summarykeysreview'],
            'an unknown mode a restore carried in' => ['listed', 'summarykeysoff'],
        ];
    }

    /**
     * The third fact says what a group enrolment key does, and an unknown stored mode reads as
     * off, as the readers everywhere else read it.
     *
     * @param string $mode The stored customchar1.
     * @param string $expected The string id of the fact.
     * @return void
     */
    #[DataProvider('key_mode_provider')]
    public function test_the_strip_words_the_group_key_mode(string $mode, string $expected): void {
        $instance = clone $this->instance;
        $instance->customchar1 = $mode;

        $this->assertSame(get_string($expected, 'enrol_apply'), $this->facts($instance)[2]);
    }

    /**
     * The places-taken row under the limit carries its own label, and says "no limit" rather
     * than "of 0" when the method has none.
     *
     * @return void
     */
    public function test_the_places_taken_row_has_its_own_label(): void {
        $this->plugin->enrol_user($this->instance, $this->getDataGenerator()->create_user()->id, null, 0, 0, ENROL_USER_ACTIVE);

        $row = $this->mform()->getElement('currentoccupancy');
        $this->assertSame(get_string('placestakenlabel', 'enrol_apply'), $row->getLabel());
        $this->assertSame(get_string('placestakennolimit', 'enrol_apply', 1), $row->_text);

        $instance = clone $this->instance;
        $instance->customint4 = 5;
        $row = $this->mform($instance)->getElement('currentoccupancy');
        $this->assertSame(get_string('placestaken', 'enrol_apply', (object) ['taken' => 1, 'limit' => 5]), $row->_text);
    }

    /**
     * The questions line says a new method has none yet, and counts a saved method's questions
     * and the ones shown on a condition.
     *
     * @return void
     */
    public function test_the_questions_line_counts_the_methods_questions(): void {
        $button = get_string('saveandeditfields', 'enrol_apply');

        $new = (object) $this->plugin->get_instance_defaults();
        $new->id = null;
        $new->courseid = $this->course->id;
        $this->assertSame(
            s(get_string('questionsnew', 'enrol_apply', $button)),
            $this->mform($new)->getElement('questions')->_text
        );

        $line = fn(): string => $this->mform()->getElement('questions')->_text;
        $this->assertSame(s(get_string('questionsnone', 'enrol_apply', $button)), $line());

        /** @var \core_customfield_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $categoryid = (int) $generator->create_category([
            'component' => 'enrol_apply',
            'area' => \enrol_apply\customfield\application_handler::AREA,
            'itemid' => (int) $this->instance->id,
        ])->get('id');
        $controller = $generator->create_field(['categoryid' => $categoryid, 'type' => 'checkbox', 'shortname' => 'hasjob']);
        $this->assertSame(s(get_string('questionsone', 'enrol_apply', $button)), $line());

        $field = $generator->create_field(['categoryid' => $categoryid, 'type' => 'text', 'shortname' => 'employer']);
        $this->assertSame(
            s(get_string('questionsmany', 'enrol_apply', (object) ['button' => $button, 'count' => 2])),
            $line()
        );

        \enrol_apply\local\conditions::save(
            (int) $this->instance->id,
            (int) $field->get('id'),
            (int) $controller->get('id'),
            \enrol_apply\local\conditions::OPERATOR_CHECKED,
            []
        );
        $this->assertSame(
            s(get_string('questionsmanyconditional', 'enrol_apply', (object) [
                'button' => $button,
                'count' => 2,
                'conditional' => 1,
            ])),
            $line()
        );
    }
}
