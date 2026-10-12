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
 * Behat step definitions and page resolvers for enrol_apply.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL check here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Page resolvers and step definitions for enrol_apply.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_enrol_apply extends behat_base {
    /**
     * Resolve page instance URLs for this plugin.
     *
     * Recognised pages:
     *   "<shortname> > manage applications"   the per-course application queue
     *   "<shortname> > application fields"    the instance's own application questions
     *
     * @param string $type Identifies which page to resolve.
     * @param string $identifier Course short name.
     * @return moodle_url The resolved URL.
     * @throws Exception When the course has no apply enrolment instance.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'manage applications':
                return new moodle_url('/enrol/apply/manage.php', ['id' => $this->get_instance_id($identifier)]);

            case 'application fields':
                return new moodle_url('/enrol/apply/fields.php', ['id' => $this->get_instance_id($identifier)]);

            default:
                throw new Exception("Unrecognised enrol_apply page type '{$type}'.");
        }
    }

    /**
     * Resolve non-instance page URLs for this plugin.
     *
     * @param string $page Page name.
     * @return moodle_url The resolved URL.
     * @throws Exception When the page name is not recognised.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'manage applications':
                return new moodle_url('/enrol/apply/manage.php');

            default:
                throw new Exception("Unrecognised enrol_apply page '{$page}'.");
        }
    }

    /**
     * Set which profile fields an apply enrolment method asks for.
     *
     * The field set is a JSON envelope on the instance with no generator behind it, and
     * driving the per-field picker through the edit form would make every scenario long and
     * brittle for something tests/local/fields_test.php already covers.
     *
     * @Given /^the "(?P<shortname_string>(?:[^"]|\\")*)" apply enrolment method asks for "(?P<keys_string>(?:[^"]|\\")*)"$/
     * @param string $shortname Course shortname.
     * @param string $keys Comma separated field keys, or an empty string for none.
     * @return void
     */
    public function the_apply_enrolment_method_asks_for(string $shortname, string $keys): void {
        global $DB;

        $picked = array_filter(array_map('trim', explode(',', $keys)));
        $DB->set_field(
            'enrol',
            'customtext4',
            \enrol_apply\local\fieldset::from_keys($picked)->to_json(),
            ['id' => $this->get_instance_id($shortname)]
        );
    }

    /**
     * Give an apply enrolment method a question of its own.
     *
     * Core's custom field generator needs the category's itemid, which is the enrol instance id
     * and cannot be named in a feature file; configuring it through core's editor needs
     * JavaScript and is core's own feature to test.
     *
     * @Given /^the "(?P<course_string>[^"]*)" apply method asks a "(?P<type_string>[^"]*)" "(?P<name_string>[^"]*)"$/
     * @param string $shortname Course shortname.
     * @param string $type Custom field type, for example text.
     * @param string $name Question shown to the applicant.
     * @return void
     */
    public function the_apply_enrolment_method_asks_the_question(string $shortname, string $type, string $name): void {
        $this->create_question($shortname, $type, $name, []);
    }

    /**
     * Give an apply enrolment method a select question with the given options.
     *
     * @Given /^the "(?P<course_string>[^"]*)" apply method asks a select "(?P<name_string>[^"]*)" of "(?P<options_string>[^"]*)"$/
     * @param string $shortname Course shortname.
     * @param string $name Question shown to the applicant.
     * @param string $options Comma separated options.
     * @return void
     */
    public function the_apply_enrolment_method_asks_a_select(string $shortname, string $name, string $options): void {
        $this->create_question($shortname, 'select', $name, [
            'options' => implode("\n", array_map('trim', explode(',', $options))),
            'defaultvalue' => '',
        ]);
    }

    /**
     * Show one question of an apply enrolment method only when a select question has an answer.
     *
     * @Given /^in "(?P<course_string>[^"]*)" "(?P<q_string>[^"]*)" shows when "(?P<c_string>[^"]*)" is "(?P<o_string>[^"]*)"$/
     * @param string $shortname Course shortname.
     * @param string $name The question shown conditionally.
     * @param string $controller The select question it depends on.
     * @param string $option The option that shows it.
     * @return void
     */
    public function the_apply_question_shows_when(string $shortname, string $name, string $controller, string $option): void {
        global $DB;

        $enrolid = $this->get_instance_id($shortname);
        $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        $byname = [];
        foreach (\enrol_apply\local\answers::fields($instance) as $field) {
            $byname[$field->get('name')] = $field;
        }
        $options = preg_split("/\s*\n\s*/", trim((string) $byname[$controller]->get_configdata_property('options')));
        \enrol_apply\local\conditions::save(
            $enrolid,
            (int) $byname[$name]->get('id'),
            (int) $byname[$controller]->get('id'),
            \enrol_apply\local\conditions::OPERATOR_IN,
            [array_search($option, $options) + 1]
        );
    }

    /**
     * Create a question in a category of its own on the course's apply instance.
     *
     * @param string $shortname Course shortname.
     * @param string $type Custom field type.
     * @param string $name Question shown to the applicant.
     * @param array $config Field configdata.
     * @return void
     */
    protected function create_question(string $shortname, string $type, string $name, array $config): void {
        $generator = testing_util::get_data_generator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category([
            'component' => 'enrol_apply',
            'area' => \enrol_apply\customfield\application_handler::AREA,
            'itemid' => $this->get_instance_id($shortname),
            'name' => 'Questions',
        ]);
        $generator->create_field([
            'categoryid' => $category->get('id'),
            'type' => $type,
            'shortname' => 'q' . substr(md5($name), 0, 8),
            'name' => $name,
            'description' => '',
            'configdata' => $config,
        ]);
    }

    /**
     * The applied-date chip reads exactly what its own control holds.
     *
     * The scenario cannot name a literal date. For an `input[type=date]` mink-phpwebdriver runs
     * `date(DATE_ATOM, strtotime($value))` in the Behat process and then sets
     * `element.valueAsDate = new Date("<that string>")` in the browser. `valueAsDate` uses the
     * Date's UTC components, so the day that lands in the control can differ from the one the
     * step named ("2020-01-01" can arrive as 2019-12-31).
     *
     * What this pins is that the chip the server renders and the chip the AMD module redraws
     * spell the same date, since different code produces them from the same control; so the
     * assertion is against the control's own value, whatever the driver put there.
     *
     * @Then the applied-date chip should read what its control holds
     * @throws \Behat\Mink\Exception\ExpectationException When the control holds no date.
     * @return void
     */
    public function the_applied_date_chip_should_read_what_its_control_holds(): void {
        $value = (string) $this->getSession()->evaluateScript(
            "(document.querySelector('#enrol_apply_appliedfrom') || {}).value || ''"
        );

        if ($value === '') {
            throw new \Behat\Mink\Exception\ExpectationException(
                'the "Applied from" control holds no date, so there is nothing for a chip to agree with',
                $this->getSession()
            );
        }

        $this->execute('behat_general::assert_page_contains_text', ['Applied from ' . $value]);
    }

    /**
     * Return the apply enrolment instance id of the given course.
     *
     * @param string $shortname Course short name.
     * @return int Enrolment instance id.
     * @throws Exception When the course or the enrolment instance does not exist.
     */
    protected function get_instance_id(string $shortname): int {
        global $DB;

        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $instanceid = $DB->get_field('enrol', 'id', ['courseid' => $courseid, 'enrol' => 'apply'], IGNORE_MULTIPLE);
        if (!$instanceid) {
            throw new Exception("Course '{$shortname}' has no apply enrolment instance.");
        }

        return (int) $instanceid;
    }
}
