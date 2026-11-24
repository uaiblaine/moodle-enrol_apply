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
 * Tests for the site-wide queue's POST contract across enrolment methods, as the table and the grid send it.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * A decision from the site-wide grid over several methods is the table's decision (W3, SQ-D10).
 *
 * The site-wide table's sheet posts one `outcomemessages[<enrolid>]` per method of the selection, beside
 * the page's own `outcomemessage` (hidden and empty) and the note, and no chooser (amd/src/manage.js,
 * commit()). The grid's client posts the same fields (js/esm/src/queue/decide.ts) from its own address,
 * which carries the site-wide queue's query (course, method, sort, page...). manage.php reads both through
 * decision::posted() and runs decision::apply(); this drives that half with each surface's field set for a
 * pair of applications, one per method, and compares what was written. Then the refusals: a site-wide
 * viewer's forged post decides nothing; a message keyed by a method none of the selection belongs to
 * reaches nobody; a forged group and role on a selection of two methods have exactly the effect they have
 * when the table's form carries them.
 *
 * Three courses, each with one apply method; the decider is the administrator (a site-wide decider), the
 * viewer holds enrol/apply:viewapplications at the system context.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(decision::class)]
final class wide_decision_post_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass[] The courses, by key: a, b, c. */
    private $courses = [];

    /** @var \stdClass[] Their apply methods, by the same keys. */
    private $methods = [];

    /** @var \stdClass A site-wide viewer. */
    private $viewer;

    /**
     * Three courses with a method each, and the site-wide viewer.
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
        $this->plugin = enrol_get_plugin('apply');

        $generator = $this->getDataGenerator();
        foreach (['a', 'b', 'c'] as $key) {
            $this->courses[$key] = $generator->create_course(['fullname' => 'Course ' . $key]);
            $id = $this->plugin->add_instance(
                $this->courses[$key],
                ['name' => 'Intake ' . $key] + $this->plugin->get_instance_defaults()
            );
            $this->methods[$key] = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        }

        $system = \context_system::instance();
        $role = $generator->create_role(['shortname' => 'wideviewer']);
        assign_capability('enrol/apply:viewapplications', CAP_ALLOW, $role, $system->id, true);
        $this->viewer = $generator->create_user();
        role_assign($role, $this->viewer->id, $system->id);

        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php'));
    }

    /**
     * Clear what a case posted, so the next test starts from an empty request.
     *
     * @return void
     */
    protected function tearDown(): void {
        $_POST = [];
        $_GET = [];
        parent::tearDown();
    }

    /**
     * Write a pending application through the plugin's own apply().
     *
     * @param string $key The method's key.
     * @return array The user enrolment id and the applicant.
     */
    private function apply_to(string $key): array {
        global $USER;

        $previous = $USER;
        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $written = $method->invoke(
            $this->plugin,
            $this->methods[$key],
            (int) $applicant->id,
            (object) ['applydescription' => 'Please']
        );
        $sink->close();
        $this->setUser($previous);

        return [(int) $written['userenrolmentid'], $applicant];
    }

    /**
     * Post a request as manage.php receives it and run its handler's half: read the fields, apply the decision.
     *
     * @param array $post The form's fields.
     * @param array $get The form's action's query string.
     * @return int How many applications were decided.
     */
    private function post(array $post, array $get): int {
        $_POST = $post;
        $_GET = $get;
        try {
            $posted = decision::posted();
            $sink = $this->redirectMessages();
            $decided = decision::apply(
                optional_param('formaction', '', PARAM_ALPHA),
                optional_param_array('userenrolments', [], PARAM_INT),
                $posted['message'],
                $posted['messages'],
                $posted['decision']
            );
            $sink->close();
        } finally {
            $_POST = [];
            $_GET = [];
        }

        return $decided;
    }

    /**
     * What a decision wrote about one application, without the ids and times that differ by row.
     *
     * @param int $ueid The application's user enrolment.
     * @param \stdClass $applicant Its applicant.
     * @param string $key The method's key, whose course the memberships and roles are read in.
     * @return array The enrolment's status (null when gone), the record's decision, the memberships and the roles.
     */
    private function written(int $ueid, \stdClass $applicant, string $key): array {
        global $DB;

        $course = $this->courses[$key];
        $record = $DB->get_record('enrol_apply_submission', ['userenrolmentid' => $ueid], '*', MUST_EXIST);
        $status = $DB->get_field('user_enrolments', 'status', ['id' => $ueid]);
        $groups = array_map('intval', array_values(groups_get_user_groups((int) $course->id, (int) $applicant->id)[0]));
        sort($groups);
        $roles = $DB->get_records_menu(
            'role_assignments',
            ['userid' => $applicant->id, 'contextid' => \context_course::instance($course->id)->id],
            'roleid',
            'roleid, component'
        );

        return [
            'enrolment' => $status === false ? null : (int) $status,
            'status' => (int) $record->status,
            'outcomemessage' => (string) $record->outcomemessage,
            'decisionnote' => (string) $record->decisionnote,
            'decidedgroups' => $record->decidedgroups,
            'decidedrole' => $record->decidedrole,
            'decidedby' => (int) $record->decidedby,
            'groups' => $groups,
            'roles' => $roles,
        ];
    }

    /**
     * An enrolment's status, null once it is gone (a cancellation unenrols).
     *
     * @param int $ueid The user enrolment.
     * @return int|null Its status.
     */
    private function enrolment_status(int $ueid): ?int {
        global $DB;

        $status = $DB->get_field('user_enrolments', 'status', ['id' => $ueid]);

        return $status === false ? null : (int) $status;
    }

    /**
     * The fields the site-wide sheet posts for a selection of two methods, the table's and the grid's alike.
     *
     * @param string $action confirm, wait or cancel.
     * @param array $ids The selection's user enrolment ids.
     * @return array The fields.
     */
    private function fields(string $action, array $ids): array {
        return [
            'sesskey' => sesskey(),
            'formaction' => $action,
            'userenrolments' => array_map('strval', $ids),
            // The page's own message box, hidden by the sheet and posted empty.
            'outcomemessage' => '',
            'decisionnote' => 'Checked both intakes.',
            'outcomemessages' => [
                (string) $this->methods['a']->id => 'Welcome to course a.',
                (string) $this->methods['b']->id => 'Welcome to course b.',
            ],
        ];
    }

    /**
     * The three decisions.
     *
     * @return array Name => [formaction].
     */
    public static function actions(): array {
        return [
            'confirm' => ['confirm'],
            'defer' => ['wait'],
            'cancel' => ['cancel'],
        ];
    }

    /**
     * A decision over two methods posted with the grid's field set writes exactly what the table's post writes.
     *
     * Each surface decides one application of each method, as the same site-wide decider: the table's form
     * posts from the id-less page, the grid's from its own address, which carries its query and must decide
     * nothing. Each method's applicant gets that method's message.
     *
     * Changes that must make it fail: decision::posted() reading the per-method messages under another
     * name; the grouping by method taken from the post rather than the database; a carried address
     * parameter reaching the decision.
     *
     * @param string $action confirm, wait or cancel.
     * @return void
     */
    #[DataProvider('actions')]
    public function test_a_grid_post_over_two_methods_writes_what_the_tables_post_writes(string $action): void {
        [$tablea, $tableapplicanta] = $this->apply_to('a');
        [$tableb, $tableapplicantb] = $this->apply_to('b');
        [$grida, $gridapplicanta] = $this->apply_to('a');
        [$gridb, $gridapplicantb] = $this->apply_to('b');
        $this->setAdminUser();
        // Precondition: the decider's listing is the site-wide queue, deciding.
        $listing = queue::listing_scope(0);
        $this->assertNull($listing->mentees);
        $this->assertTrue($listing->candecide);

        $address = [
            'status' => (string) ENROL_USER_SUSPENDED,
            'search' => 'Nobody by this name',
            'course' => (string) $this->courses['c']->id,
            'method' => (string) $this->methods['c']->id,
            'grouped' => 'yes',
            'waited' => '14',
            'sort' => 'course',
            'dir' => 'desc',
            'page' => '3',
            'perpage' => '30',
        ];
        $this->assertSame(2, $this->post($this->fields($action, [$tablea, $tableb]), []));
        $this->assertSame(2, $this->post($this->fields($action, [$grida, $gridb]), $address));

        $expecteda = $this->written($tablea, $tableapplicanta, 'a');
        $expectedb = $this->written($tableb, $tableapplicantb, 'b');
        $this->assertSame($expecteda, $this->written($grida, $gridapplicanta, 'a'));
        $this->assertSame($expectedb, $this->written($gridb, $gridapplicantb, 'b'));
        // The control: the decision was taken, each method's applicant with that method's message.
        $this->assertNotSame(submission::STATUS_PENDING, $expecteda['status']);
        $this->assertNotSame(submission::STATUS_PENDING, $expectedb['status']);
        $this->assertSame('Welcome to course a.', $expecteda['outcomemessage']);
        $this->assertSame('Welcome to course b.', $expectedb['outcomemessage']);
        $this->assertSame('Checked both intakes.', $expectedb['decisionnote']);
    }

    /**
     * A site-wide viewer's forged post over two methods decides nothing; the administrator's same post does.
     *
     * The grid draws no box and no dock for a viewer; a post built by hand still reaches the decision
     * methods, whose per-row check refuses a reader who may only view.
     *
     * Changes that must make it fail: the per-row check trusting the posted list; the viewing capability
     * read as a deciding one.
     *
     * @param string $action confirm, wait or cancel.
     * @return void
     */
    #[DataProvider('actions')]
    public function test_a_site_wide_viewers_forged_post_decides_nothing(string $action): void {
        [$a] = $this->apply_to('a');
        [$b] = $this->apply_to('b');
        $this->setUser($this->viewer);
        // Preconditions: the viewer opens the site-wide queue, read only.
        $listing = queue::listing_scope(0);
        $this->assertTrue($listing->allowed);
        $this->assertFalse($listing->candecide);

        $this->assertSame(0, $this->post($this->fields($action, [$a, $b]), []));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->enrolment_status($a));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->enrolment_status($b));

        // The control: the same post from a site-wide decider decides both.
        $this->setAdminUser();
        $this->assertSame(2, $this->post($this->fields($action, [$a, $b]), []));
        $this->assertNotSame(ENROL_USER_SUSPENDED, $this->enrolment_status($a));
        $this->assertNotSame(ENROL_USER_SUSPENDED, $this->enrolment_status($b));
    }

    /**
     * A mentor of the given applicants, holding the deciding capability in their user contexts alone.
     *
     * @param array $applicants The applicants they mentor.
     * @return \stdClass The mentor.
     */
    private function mentor(array $applicants): \stdClass {
        $generator = $this->getDataGenerator();
        $role = $generator->create_role();
        set_role_contextlevels($role, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $role, \context_system::instance()->id, true);
        $mentor = $generator->create_user();
        foreach ($applicants as $applicant) {
            role_assign($role, $mentor->id, \context_user::instance($applicant->id)->id);
        }

        return $mentor;
    }

    /**
     * A mentor's decision over two methods posted as the mentee grid posts it writes exactly what the mentee
     * table's post writes (W5, SQ-D13).
     *
     * The mentor mentors four applicants, two per method. The table's form posts from the id-less page, the
     * grid's from its own address, which for a mentor carries the scope, the search, the waited facet, the
     * sort and the page, plus a forged course and method the mentee table never reads; neither decides
     * anything. Each method's applicant gets that method's message, and the decider recorded is the mentor.
     *
     * Changes that must make it fail: the grid's post handled apart from the table's; a carried address
     * parameter reaching the decision; the mentor's per-row grant (the applicant's user context) refused.
     *
     * @param string $action confirm, wait or cancel.
     * @return void
     */
    #[DataProvider('actions')]
    public function test_a_mentors_grid_post_over_two_methods_writes_what_the_tables_post_writes(string $action): void {
        [$tablea, $tableapplicanta] = $this->apply_to('a');
        [$tableb, $tableapplicantb] = $this->apply_to('b');
        [$grida, $gridapplicanta] = $this->apply_to('a');
        [$gridb, $gridapplicantb] = $this->apply_to('b');
        $mentor = $this->mentor([$tableapplicanta, $tableapplicantb, $gridapplicanta, $gridapplicantb]);
        $this->setUser($mentor);
        // Precondition: the mentor's listing is the mentee queue, deciding.
        $listing = queue::listing_scope(0);
        $this->assertCount(4, $listing->mentees);
        $this->assertTrue($listing->candecide);

        $address = [
            'status' => (string) ENROL_USER_SUSPENDED,
            'search' => 'Nobody by this name',
            'course' => (string) $this->courses['c']->id,
            'method' => (string) $this->methods['c']->id,
            'waited' => '14',
            'sort' => 'course',
            'dir' => 'desc',
            'page' => '3',
        ];
        $this->assertSame(2, $this->post($this->fields($action, [$tablea, $tableb]), []));
        $this->assertSame(2, $this->post($this->fields($action, [$grida, $gridb]), $address));

        $expecteda = $this->written($tablea, $tableapplicanta, 'a');
        $expectedb = $this->written($tableb, $tableapplicantb, 'b');
        $this->assertSame($expecteda, $this->written($grida, $gridapplicanta, 'a'));
        $this->assertSame($expectedb, $this->written($gridb, $gridapplicantb, 'b'));
        // The control: the decision was taken by the mentor, each method's applicant with that method's message.
        $this->assertNotSame(submission::STATUS_PENDING, $expecteda['status']);
        $this->assertNotSame(submission::STATUS_PENDING, $expectedb['status']);
        $this->assertSame((int) $mentor->id, $expecteda['decidedby']);
        $this->assertSame('Welcome to course a.', $expecteda['outcomemessage']);
        $this->assertSame('Welcome to course b.', $expectedb['outcomemessage']);
    }

    /**
     * A mentor's post naming an application of somebody they do not mentor decides nothing for that row,
     * which stays as it was; the mentee's row in the same post is decided.
     *
     * The grid lists the mentees' applications alone and posts only their ids; a post built by hand reaches
     * the decision methods, whose per-row check grants a mentor only in their mentees' user contexts.
     *
     * Changes that must make it fail: the per-row check trusting the posted list; the mentor's grant read in
     * a context other than the applicant's own.
     *
     * @param string $action confirm, wait or cancel.
     * @return void
     */
    #[DataProvider('actions')]
    public function test_a_mentors_forged_post_for_a_non_mentee_decides_nothing_for_them(string $action): void {
        global $DB;

        [$mine, $mentee] = $this->apply_to('a');
        [$theirs] = $this->apply_to('b');
        $this->setUser($this->mentor([$mentee]));

        $this->assertSame(1, $this->post($this->fields($action, [$mine, $theirs]), []));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->enrolment_status($theirs));
        $record = $DB->get_record('enrol_apply_submission', ['userenrolmentid' => $theirs], '*', MUST_EXIST);
        $this->assertSame(submission::STATUS_PENDING, (int) $record->status);
        $this->assertSame('', (string) $record->outcomemessage);
        $this->assertSame(0, (int) $record->decidedby);
        // The control: the mentee's application, in the same post, was decided.
        $this->assertNotSame(ENROL_USER_SUSPENDED, $this->enrolment_status($mine));
    }

    /**
     * A message keyed by a method none of the selection belongs to reaches nobody.
     *
     * The keys of `outcomemessages[]` only choose a message for the rows the database says belong to that
     * method; a third method's applicant, not selected, stays pending and is told nothing, and an id that
     * names no method changes nothing either.
     *
     * Changes that must make it fail: the grouping taking the posted keys as methods to decide; a posted
     * key's message given to a row of another method.
     *
     * @return void
     */
    public function test_a_message_for_a_method_outside_the_selection_reaches_nobody(): void {
        global $DB;

        [$a] = $this->apply_to('a');
        [$b] = $this->apply_to('b');
        [$c] = $this->apply_to('c');
        $this->setAdminUser();

        $fields = $this->fields('confirm', [$a, $b]);
        $fields['outcomemessages'][(string) $this->methods['c']->id] = 'Forged for course c.';
        $fields['outcomemessages']['999999'] = 'Forged for nothing.';
        $this->assertSame(2, $this->post($fields, []));

        $this->assertSame(ENROL_USER_SUSPENDED, $this->enrolment_status($c));
        $this->assertSame('', (string) $DB->get_field('enrol_apply_submission', 'outcomemessage', ['userenrolmentid' => $c]));
        // The control: the selected rows took their own methods' messages.
        $this->assertSame(ENROL_USER_ACTIVE, $this->enrolment_status($a));
        $this->assertSame(
            'Welcome to course a.',
            (string) $DB->get_field('enrol_apply_submission', 'outcomemessage', ['userenrolmentid' => $a])
        );
        $this->assertSame(
            'Welcome to course b.',
            (string) $DB->get_field('enrol_apply_submission', 'outcomemessage', ['userenrolmentid' => $b])
        );
    }

    /**
     * A group and a role forged into a grid post over two methods do exactly what they do in the table's post.
     *
     * Neither the site-wide table nor the grid offers a chooser (SQ-D10), and the grid never posts one;
     * decision::posted() reads them whatever the form offered, and confirm_enrolment() allowlists them per
     * application, so a group of course a is not given to course b's applicant. The expectation is the
     * table's own outcome for the same fields, not a hand-written one.
     *
     * Changes that must make it fail: the grid's post handled apart from the table's; the allowlist taking
     * a group of one course for another course's applicant.
     *
     * @return void
     */
    public function test_a_forged_group_and_role_over_two_methods_do_what_they_do_in_the_tables_post(): void {
        global $DB;

        $group = $this->getDataGenerator()->create_group(['courseid' => $this->courses['a']->id, 'name' => 'Evening cohort']);
        $teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        [$tablea, $tableapplicanta] = $this->apply_to('a');
        [$tableb, $tableapplicantb] = $this->apply_to('b');
        [$grida, $gridapplicanta] = $this->apply_to('a');
        [$gridb, $gridapplicantb] = $this->apply_to('b');
        $this->setAdminUser();

        $forged = ['groups' => [(string) $group->id], 'roleid' => (string) $teacherrole];
        $this->assertSame(2, $this->post($this->fields('confirm', [$tablea, $tableb]) + $forged, []));
        $this->assertSame(2, $this->post($this->fields('confirm', [$grida, $gridb]) + $forged, ['sort' => 'course']));

        $expecteda = $this->written($tablea, $tableapplicanta, 'a');
        $expectedb = $this->written($tableb, $tableapplicantb, 'b');
        $this->assertSame($expecteda, $this->written($grida, $gridapplicanta, 'a'));
        $this->assertSame($expectedb, $this->written($gridb, $gridapplicantb, 'b'));
        // The control: both were approved, and course b's applicant joined no group of course a.
        $this->assertSame(ENROL_USER_ACTIVE, $expecteda['enrolment']);
        $this->assertSame(ENROL_USER_ACTIVE, $expectedb['enrolment']);
        $this->assertSame([], $expectedb['groups']);
    }
}
