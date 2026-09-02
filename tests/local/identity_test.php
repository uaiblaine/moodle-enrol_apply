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
 * Tests for the applicant identity fields the queue shows, and the scopes that get none.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * Tests for the applicant identity fields the queue shows, and the scopes that get none.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(identity::class)]
final class identity_test extends \advanced_testcase {
    /** @var \stdClass The course the applications are made to. */
    protected $course;

    /** @var \stdClass The apply enrol instance. */
    protected $instance;

    /**
     * Build a course with an apply instance.
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

        $plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $instanceid = $plugin->add_instance($this->course, $plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        /* The queue's table is dynamic, and get_dynamic_table_html_end() builds its
           "show all" link from $PAGE->url - so rendering one without a page url makes core
           emit a debugging() call, which advanced_testcase turns into a notice. manage.php
           always sets it; a test that renders the table is standing in for that page. */
        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php'));
    }

    /**
     * Put one applicant, with identifying details, on the queue.
     *
     * @param array $fields Extra fields for the applicant's user record.
     * @return \stdClass The applicant.
     */
    protected function applicant(array $fields = []): \stdClass {
        $plugin = enrol_get_plugin('apply');
        $user = $this->getDataGenerator()->create_user($fields);
        $plugin->enrol_user($this->instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);

        return $user;
    }

    /**
     * A mentor of the given applicant, holding no capability anywhere else.
     *
     * The role is DEFINED at the system context and ASSIGNED only in the mentee's user context,
     * which is what makes the distinction this file needs: has_capability() at the system context
     * is false for them, so queue::listing_scope(0) takes the mentee branch rather than the
     * site-wide one.
     *
     * **And they hold moodle/site:viewuseridentity at the system context, which is the whole
     * point of the fixture rather than a detail of it.** Without it identity::fields() returns
     * an empty array for ANY context, so this reader would see no identity columns whichever
     * context the mentee scope resolved - and the test below would pass just as happily against
     * a scope that resolved the system context, which is the defect it exists to catch. Measured:
     * gate CE reddened nothing until this capability was granted.
     *
     * The reader is reachable rather than contrived. Holding viewuseridentity site-wide without
     * holding enrol/apply:manageapplications site-wide is the ordinary shape of a support or
     * enquiries role, and mentoring is delegated to exactly that kind of account.
     *
     * @param \stdClass $mentee The applicant they mentor.
     * @return \stdClass The mentor.
     */
    protected function mentor(\stdClass $mentee): \stdClass {
        $mentor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'applymentor']);
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $mentor->id, \context_user::instance($mentee->id)->id);

        /* A SECOND role, carrying the identity capability and nothing else, assigned at the
           system context - because a role assignment in a user context grants nothing at the
           system one, and identity::fields() asks there. Keeping it separate is what stops the
           apply capability coming along with it, which would move this reader to the site-wide
           branch and take the mentee scope out of the test entirely. */
        $siteroleid = $this->getDataGenerator()->create_role(['shortname' => 'applyidentityonly']);
        set_role_contextlevels($siteroleid, [CONTEXT_SYSTEM]);
        assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $siteroleid, \context_system::instance());
        role_assign($siteroleid, $mentor->id, \context_system::instance()->id);

        return $mentor;
    }

    /**
     * Render the queue for a scope, named the way a url names one.
     *
     * The identity context used to be handed in here and is not any more: the table derives it
     * from the scope, so these tests now produce the scope and read the consequence rather than
     * asserting a consequence of a context they injected. That is a stronger claim - the mapping
     * from scope to context is itself under test - and it is the only claim available, because a
     * caller can no longer state a context at all.
     *
     * @param int|null $enrolid Enrol instance to list, 0 for the scope with no instance, null for
     *                          this fixture's own instance.
     * @return string The rendered table.
     */
    protected function render(?int $enrolid = null): string {
        $table = \enrol_apply\table\applications::for_scope($enrolid ?? (int) $this->instance->id);

        ob_start();
        $table->out(50, true);

        return ob_get_clean();
    }

    /**
     * A reader with the capability sees the fields the site named, and no others.
     *
     * @return void
     */
    public function test_the_configured_identity_fields_are_shown(): void {
        global $CFG;

        $this->setAdminUser();
        $CFG->showuseridentity = 'email,idnumber';
        $CFG->hiddenuserfields = '';

        $this->applicant(['email' => 'ana@example.org', 'idnumber' => 'RA-2026-0042', 'phone1' => '555-1234']);

        $html = $this->render();

        $this->assertStringContainsString('ana@example.org', $html);
        $this->assertStringContainsString('RA-2026-0042', $html);
        // The control: a field the site did NOT name is not disclosed by the queue.
        $this->assertStringNotContainsString('555-1234', $html);
    }

    /**
     * A field the site hides is not shown to a reader who may not see hidden fields.
     *
     * This is the half the queue used to get wrong: it printed the address unconditionally, so on
     * a site configured like this one it disclosed more than core's participants page beside it.
     *
     * The capability is PROHIBITed explicitly rather than relying on a role that lacks it,
     * because measured against core's own access.php both `teacher` and `editingteacher` are
     * granted `moodle/course:viewhiddenuserfields` by archetype - so on a stock site
     * `hiddenuserfields` never narrows what a teacher sees on this queue, and a test using one
     * would assert the opposite of what it looks like it asserts. The reader this protects is a
     * custom role holding `moodle/site:viewuseridentity` without the hidden-fields override.
     *
     * @return void
     */
    public function test_a_hidden_field_is_not_shown_to_a_reader_who_may_not_see_hidden_fields(): void {
        global $CFG, $DB;

        $CFG->showuseridentity = 'email,idnumber';
        $CFG->hiddenuserfields = 'email';

        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $context = \context_course::instance($this->course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/course:viewhiddenuserfields', CAP_PROHIBIT, $roleid, $context->id, true);
        $this->setUser($teacher);

        $this->applicant(['email' => 'ana@example.org', 'idnumber' => 'RA-2026-0042']);

        $html = $this->render();

        $this->assertStringNotContainsString('ana@example.org', $html);
        // The control: the reader is seeing the queue at all, and the other field is still there.
        $this->assertStringContainsString('RA-2026-0042', $html);
    }

    /**
     * On a stock site, hiddenuserfields does NOT narrow what a teacher sees here.
     *
     * The counterpart of the test above, and the reason it has to prohibit the capability by hand.
     * Recording it as an assertion rather than a comment: if core ever changes those archetypes,
     * this reddens and the sibling test's premise is re-examined instead of quietly rotting.
     *
     * @return void
     */
    public function test_a_stock_teacher_still_sees_a_hidden_identity_field(): void {
        global $CFG;

        $CFG->showuseridentity = 'email';
        $CFG->hiddenuserfields = 'email';

        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);

        $this->applicant(['email' => 'ana@example.org']);

        $html = $this->render();

        $this->assertStringContainsString('ana@example.org', $html);
    }

    /**
     * Without the capability, no identity field is shown at all.
     *
     * @return void
     */
    public function test_a_reader_without_the_capability_sees_none(): void {
        global $CFG, $DB;

        $CFG->showuseridentity = 'email,idnumber';
        $CFG->hiddenuserfields = '';

        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $context = \context_course::instance($this->course->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/site:viewuseridentity', CAP_PROHIBIT, $roleid, $context->id, true);
        $this->setUser($teacher);

        $this->applicant(['email' => 'ana@example.org', 'idnumber' => 'RA-2026-0042']);

        $this->assertSame([], identity::fields($context));

        $html = $this->render();

        $this->assertStringNotContainsString('ana@example.org', $html);
        $this->assertStringNotContainsString('RA-2026-0042', $html);
    }

    /**
     * The mentee scope gets no identity columns, because no single context can judge it.
     *
     * Driven by a real MENTOR now rather than by handing the table a null context, and the
     * change is what gives the test its subject back. The mapping from scope to identity
     * context lives in queue::listing_scope(), so a test that injected null was asserting
     * identity::fields(null)'s behaviour twice and the mapping not at all - it would have passed
     * with the mentee branch resolving the system context.
     *
     * @return void
     */
    public function test_the_mentee_scope_gets_no_identity_fields(): void {
        global $CFG;

        $CFG->showuseridentity = 'email,idnumber';
        $CFG->hiddenuserfields = '';

        $applicant = $this->applicant(['email' => 'ana@example.org', 'idnumber' => 'RA-2026-0042']);
        $this->setUser($this->mentor($applicant));

        $this->assertSame([], identity::fields(null));

        $html = $this->render(0);

        $this->assertStringNotContainsString('ana@example.org', $html);
        $this->assertStringNotContainsString('RA-2026-0042', $html);
        /* The control, and this scope needs one badly: a listing that showed no rows at all
           would satisfy both assertions above. The applicant is there, without their details. */
        $this->assertStringContainsString(fullname($applicant), $html);
    }

    /**
     * A custom profile field works, which is the case that needs named parameters.
     *
     * The `?`/`:name` branch of fields::get_sql() exists only for custom profile fields, so a
     * fixture naming standard fields alone never reaches it and would pass against a query built
     * with positional placeholders. This is the one that fails with `mixedtypesqlparam` if the
     * helper stops asking for named parameters.
     *
     * @return void
     */
    public function test_a_custom_profile_field_is_shown(): void {
        global $CFG, $DB;

        $this->setAdminUser();

        $fieldid = $DB->insert_record('user_info_field', (object) [
            'shortname' => 'unit',
            'name' => 'Unit',
            'categoryid' => $DB->insert_record('user_info_category', (object) ['name' => 'Extra', 'sortorder' => 1]),
            'datatype' => 'text',
            'sortorder' => 1,
            'required' => 0,
            'locked' => 0,
            'visible' => 2,
            'forceunique' => 0,
            'signup' => 0,
            'defaultdata' => '',
            'param1' => 30,
            'param2' => 2048,
        ]);

        $CFG->showuseridentity = 'profile_field_unit';
        $CFG->hiddenuserfields = '';

        $applicant = $this->applicant();
        $DB->insert_record('user_info_data', (object) [
            'userid' => $applicant->id,
            'fieldid' => $fieldid,
            'data' => 'Directorate of Training',
            'dataformat' => 0,
        ]);

        $html = $this->render();

        $this->assertStringContainsString('Directorate of Training', $html);
    }

    /**
     * An identity value is escaped before it reaches the markup.
     *
     * flexible_table writes $row->$column into the cell with no escaping of its own, so this is
     * the plugin's own boundary and not core's. Core closes the identical hole in its participants
     * table with the identical method.
     *
     * @return void
     */
    public function test_an_identity_value_is_escaped(): void {
        global $CFG;

        $this->setAdminUser();
        $CFG->showuseridentity = 'idnumber';
        $CFG->hiddenuserfields = '';

        $this->applicant(['idnumber' => 'R&D <b>2026</b>']);

        $html = $this->render();

        $this->assertStringContainsString('R&amp;D &lt;b&gt;2026&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>2026</b>', $html);
    }

    /**
     * The A-Z bar is not drawn, whatever the caller asks for.
     *
     * The display half. Killing only the filter would leave a control on the page that does
     * nothing when clicked, which is worse than either end state - and the argument is not the
     * caller's to make: the renderer passes true, and core's dynamic-table service passes true
     * unconditionally, so this has to be forced at the source.
     *
     * @return void
     */
    public function test_the_initials_bar_is_not_drawn(): void {
        $this->setAdminUser();
        $this->applicant(['firstname' => 'Ana', 'lastname' => 'Ribeiro']);

        // The second argument of out() is exactly the request the override has to refuse.
        $html = $this->render();

        $this->assertStringNotContainsString('initialbar', $html);
        // The control: the table itself did render, so the assertion above is not vacuous.
        $this->assertStringContainsString('Ribeiro', $html);
    }

    /**
     * A stored initials preference no longer filters the queue.
     *
     * Hiding the A-Z bar never removed the filter: get_sql_where() reads the stored preference and
     * never consults use_initials, and query_db() appends it to both the count and the data query.
     * The preference lives in $SESSION->flextable, so it survives page loads with nothing on screen
     * able to explain the rows that vanished.
     *
     * @return void
     */
    public function test_a_stored_initial_does_not_filter_the_queue(): void {
        global $SESSION;

        $this->setAdminUser();

        $this->applicant(['firstname' => 'Ana', 'lastname' => 'Ribeiro']);
        $this->applicant(['firstname' => 'Bruno', 'lastname' => 'Alves']);

        // What clicking "Z" in the bar used to leave behind.
        $SESSION->flextable = ['enrol_apply_manage_table' => [
            'i_first' => 'Z',
            'i_last' => 'Z',
            'textsort' => [],
            'sortby' => [],
            'collapse' => [],
        ]];

        $table = \enrol_apply\table\applications::for_scope((int) $this->instance->id);

        ob_start();
        $table->out(50, true);
        ob_end_clean();

        $this->assertCount(2, $table->rawdata);
    }
}
