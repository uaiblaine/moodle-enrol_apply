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
     *   "<shortname> > applications queue grid"  the same queue in its grid view (view=grid)
     *   "<shortname> > application fields"    the instance's own application questions
     *   "<shortname> > applications report"   the course report of applications, its default view
     *   "<shortname> > applications table"    the same report as core's table (view=table)
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

            case 'applications queue grid':
                return new moodle_url('/enrol/apply/manage.php', ['id' => $this->get_instance_id($identifier), 'view' => 'grid']);

            case 'application fields':
                return new moodle_url('/enrol/apply/fields.php', ['id' => $this->get_instance_id($identifier)]);

            case 'applications report':
                return new moodle_url('/enrol/apply/report.php', ['id' => $this->get_instance_id($identifier)]);

            case 'applications table':
                return new moodle_url('/enrol/apply/report.php', ['id' => $this->get_instance_id($identifier), 'view' => 'table']);

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
     * Switch an apply enrolment method's group keys off.
     *
     * @Given /^the "(?P<shortname_string>(?:[^"]|\\")*)" apply enrolment method accepts no group keys$/
     * @param string $shortname Course shortname.
     * @return void
     */
    public function the_apply_enrolment_method_accepts_no_group_keys(string $shortname): void {
        global $DB;

        $DB->set_field(
            'enrol',
            'customchar1',
            \enrol_apply\local\groupkey::MODE_OFF,
            ['id' => $this->get_instance_id($shortname)]
        );
    }

    /**
     * Add a group to the ones an apply enrolment method's approvals join.
     *
     * What the instance form's group selector writes, without driving core's autocomplete.
     *
     * @Given /^the "(?P<shortname_string>(?:[^"]|\\")*)" apply enrolment method also joins "(?P<group_string>(?:[^"]|\\")*)"$/
     * @param string $shortname Course shortname.
     * @param string $groupname The group, by name.
     * @return void
     */
    public function the_apply_enrolment_method_also_joins(string $shortname, string $groupname): void {
        global $DB;

        $instance = $DB->get_record('enrol', ['id' => $this->get_instance_id($shortname)], '*', MUST_EXIST);
        $groupid = $DB->get_field('groups', 'id', ['courseid' => $instance->courseid, 'name' => $groupname], MUST_EXIST);
        $DB->insert_record('enrol_apply_groups', (object) ['enrolid' => $instance->id, 'groupid' => (int) $groupid]);
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
     * Give an apply enrolment method a customfield_pattern question with a preset format.
     *
     * Only where the fleet's customfield_pattern type is installed: elsewhere, including this
     * plugin's CI, the scenario is skipped rather than failed.
     *
     * @Given /^the "(?P<course_string>[^"]*)" apply method asks a "(?P<preset_string>[^"]*)" pattern "(?P<name_string>[^"]*)"$/
     * @param string $shortname Course shortname.
     * @param string $preset Preset, for example cpf.
     * @param string $name Question shown to the applicant.
     * @return void
     */
    public function the_apply_enrolment_method_asks_a_pattern(string $shortname, string $preset, string $name): void {
        if (!class_exists(\customfield_pattern\field_controller::class)) {
            throw new \Moodle\BehatExtension\Exception\SkippedException('customfield_pattern is not installed.');
        }
        $this->create_question($shortname, 'pattern', $name, [
            'preset' => $preset,
            'storeformat' => 'masked',
            'displaysize' => 30,
            'maxlength' => 30,
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
     * Open the queue's Filters panel, unless it is open already.
     *
     * The panel is a details element whose summary carries an icon and visually hidden text, which
     * no named selector reaches; and a click on it while it is open (a panel filter applied on load
     * opens it) would close it, so the state is read first. For @javascript scenarios: the
     * non-JavaScript driver sets a field inside a closed panel as it is.
     *
     * @When I open the filters of the applications queue
     * @return void
     */
    public function i_open_the_filters_of_the_applications_queue(): void {
        $open = (bool) $this->getSession()->evaluateScript(
            "!!(document.querySelector('[data-region=\"filterpanel\"]') || {}).open"
        );
        if (!$open) {
            $this->execute('behat_general::i_click_on', ['[data-region="filterpanel"] > summary', 'css_element']);
        }
    }

    /**
     * Close the queue's Filters panel when it is open, as a press outside it does.
     *
     * The panel is a popover over the chip row, so a scenario that removes a chip after setting a
     * panel field closes it first. JavaScript scenarios only.
     *
     * @When I close the filters of the applications queue
     * @return void
     */
    public function i_close_the_filters_of_the_applications_queue(): void {
        $open = (bool) $this->getSession()->evaluateScript(
            "!!(document.querySelector('[data-region=\"filterpanel\"]') || {}).open"
        );
        if ($open) {
            $this->execute('behat_general::i_click_on', ['[data-region="filterpanel"] > summary', 'css_element']);
        }
    }

    /**
     * Put an application on a course's queue, with the key of one of its groups or with none.
     *
     * Through the plugin's own apply(), so the application carries its durable record and its key
     * group exactly as the key route writes them; driving the applicant's key form for every row a
     * queue scenario needs would make it long, and tests/behat/key_review.feature drives it once.
     *
     * @Given /^"(?P<user_string>[^"]*)" has applied to "(?P<course_string>[^"]*)" with the key of "(?P<group_string>[^"]*)"$/
     * @param string $username The applicant's username.
     * @param string $shortname Course shortname.
     * @param string $groupname The key's group, by name; empty for an application without a key.
     * @return void
     */
    public function user_has_applied_with_the_key_of(string $username, string $shortname, string $groupname): void {
        global $DB;

        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $instance = $DB->get_record('enrol', ['id' => $this->get_instance_id($shortname)], '*', MUST_EXIST);
        $groupid = $groupname === ''
            ? 0
            : (int) $DB->get_field('groups', 'id', ['courseid' => $instance->courseid, 'name' => $groupname], MUST_EXIST);

        // Enrol_get_plugin() loads lib.php, which the autoloader does not; the reflection needs the class first.
        $plugin = enrol_get_plugin('apply');
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $method->invoke(
            $plugin,
            $instance,
            (int) $user->id,
            (object) ['applydescription' => ''],
            $groupid,
            $groupid ? \enrol_apply\local\submission::KEY_FOR_REVIEW : \enrol_apply\local\submission::KEY_NONE
        );
    }

    /**
     * Let an apply enrolment method's applicants choose among some of its course's groups.
     *
     * What the instance form's Groups section writes (enrol_apply_options and enrol_apply_offer),
     * through the same two classes, without driving that form.
     *
     * @Given /^applicants to the "(?P<c_string>[^"]*)" apply method (?P<m_string>may|must) choose among "(?P<g_string>[^"]*)"$/
     * @param string $shortname Course shortname.
     * @param string $mode "may" for an optional choice, "must" for a required one.
     * @param string $groupnames Comma separated group names.
     * @return void
     */
    public function applicants_to_the_apply_method_choose_among(string $shortname, string $mode, string $groupnames): void {
        global $DB;

        $instance = $DB->get_record('enrol', ['id' => $this->get_instance_id($shortname)], '*', MUST_EXIST);
        $groupids = [];
        foreach (array_filter(array_map('trim', explode(',', $groupnames))) as $groupname) {
            $groupids[] = (int) $DB->get_field(
                'groups',
                'id',
                ['courseid' => $instance->courseid, 'name' => $groupname],
                MUST_EXIST
            );
        }
        \enrol_apply\local\groupoffer::set((int) $instance->id, $groupids);
        \enrol_apply\local\instanceoptions::set((int) $instance->id, [
            'groupchoice' => $mode === 'must'
                ? \enrol_apply\local\instanceoptions::CHOICE_REQUIRED
                : \enrol_apply\local\instanceoptions::CHOICE_OPTIONAL,
        ]);
    }

    /**
     * Declare a group's seats in local_groupdist's group custom field.
     *
     * Skipped where local_groupdist is not installed: without it no group has seats.
     *
     * @Given /^the group "(?P<group_string>[^"]*)" of "(?P<course_string>[^"]*)" has "(?P<seats_number>\d+)" seats$/
     * @param string $groupname The group, by name.
     * @param string $shortname Course shortname.
     * @param string $seats Seats; 0 is full from the start.
     * @return void
     * @throws \Moodle\BehatExtension\Exception\SkippedException Without local_groupdist.
     */
    public function the_group_has_seats(string $groupname, string $shortname, string $seats): void {
        global $DB, $USER;

        if (!class_exists('\local_groupdist\seats')) {
            throw new \Moodle\BehatExtension\Exception\SkippedException('local_groupdist is not installed');
        }
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $groupid = $DB->get_field('groups', 'id', ['courseid' => $courseid, 'name' => $groupname], MUST_EXIST);

        \local_groupdist\local\fields::ensure_fields_exist();
        // The handler saves only the fields its user may edit.
        $previous = $USER;
        \core\session\manager::set_user(get_admin());
        try {
            \core_group\customfield\group_handler::create()->instance_form_save((object) [
                'id' => (int) $groupid,
                'customfield_groupdist_seats' => (int) $seats,
            ]);
        } finally {
            \core\session\manager::set_user($previous);
        }
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
