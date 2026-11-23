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
 * Tests for the queue's POST contract as the table and the grid view send it.
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
 * A decision from the grid view is the table's decision (CQ-D4).
 *
 * The grid's client builds a form with the table form's field names (js/esm/src/queue/decide.ts) and
 * posts it to manage.php from its own address, which carries the grid's query. manage.php reads the
 * fields through decision::posted() and hands them to decision::apply(), the half of the page a test
 * can reach (decision_test drives apply() for the selections that span methods; this one the field
 * sets of the two surfaces). Each case posts the table's field set for one application and the grid's
 * for another, as the same decider, and compares what was written. Then a forged grid post: the
 * reader's own application and one outside a narrowed moderator's groups ride in userenrolments[], and
 * neither is decided whatever the client selected.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(decision::class)]
final class decision_post_test extends \advanced_testcase {
    /** @var \enrol_apply_plugin The plugin. */
    private $plugin;

    /** @var \stdClass A course in separate groups mode. */
    private $course;

    /** @var \stdClass Its apply method. */
    private $instance;

    /** @var \stdClass The moderator's group. */
    private $mine;

    /** @var \stdClass Another group of the course. */
    private $theirs;

    /** @var \stdClass Non-editing teacher made moderator, member of $mine. */
    private $moderator;

    /** @var \stdClass Editing teacher, who sees every group and may choose groups and roles. */
    private $editing;

    /** @var int The non-editing teacher role, which the editing teacher may assign. */
    private $teacherrole;

    /**
     * The course, its groups and its two deciders.
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

        $this->teacherrole = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $system = \context_system::instance()->id;
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $this->teacherrole, $system, true);
        assign_capability('enrol/apply:manage', CAP_ALLOW, $this->teacherrole, $system, true);

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS]);
        $id = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $this->mine = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Own group']);
        $this->theirs = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Other group']);
        $this->moderator = $generator->create_and_enrol($this->course, 'teacher');
        $generator->create_group_member(['groupid' => $this->mine->id, 'userid' => $this->moderator->id]);
        $this->editing = $generator->create_and_enrol($this->course, 'editingteacher');

        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]));
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
     * Write a pending application through the plugin's own apply(), sent for review by a key of a group.
     *
     * @param \stdClass $group The key's group.
     * @param \stdClass|null $applicant Who applies; a new user when null.
     * @return array The user enrolment id and the applicant.
     */
    private function apply_for(\stdClass $group, ?\stdClass $applicant = null): array {
        global $USER;

        $previous = $USER;
        $this->setAdminUser();
        $applicant = $applicant ?? $this->getDataGenerator()->create_user();
        $sink = $this->redirectMessages();
        $method = new \ReflectionMethod(\enrol_apply_plugin::class, 'apply');
        $written = $method->invoke(
            $this->plugin,
            $this->instance,
            (int) $applicant->id,
            (object) ['applydescription' => 'Please'],
            (int) $group->id,
            submission::KEY_FOR_REVIEW
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
     * @return array The enrolment's status (null when gone), the record's decision, the memberships and the roles.
     */
    private function written(int $ueid, \stdClass $applicant): array {
        global $DB;

        $record = $DB->get_record('enrol_apply_submission', ['userenrolmentid' => $ueid], '*', MUST_EXIST);
        $status = $DB->get_field('user_enrolments', 'status', ['id' => $ueid]);
        $groups = array_map('intval', array_values(groups_get_user_groups((int) $this->course->id, (int) $applicant->id)[0]));
        sort($groups);
        $roles = $DB->get_records_menu(
            'role_assignments',
            ['userid' => $applicant->id, 'contextid' => \context_course::instance($this->course->id)->id],
            'roleid',
            'roleid, component'
        );

        return [
            'enrolment' => $status === false ? null : (int) $status,
            'status' => (int) $record->status,
            'outcomemessage' => (string) $record->outcomemessage,
            'decisionnote' => (string) $record->decisionnote,
            'decidedgroups' => $record->decidedgroups,
            'joinedgroups' => $record->joinedgroups,
            'decidedrole' => $record->decidedrole,
            'decidedby' => (int) $record->decidedby,
            'groups' => $groups,
            'roles' => $roles,
        ];
    }

    /**
     * The three decisions, each posted once with the choosers and once without.
     *
     * @return array Name => [formaction, whether the decider may choose groups and roles].
     */
    public static function decisions(): array {
        return [
            'confirm, choosing' => ['confirm', true],
            'confirm, not choosing' => ['confirm', false],
            'defer' => ['wait', true],
            'cancel' => ['cancel', true],
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
     * A decision posted with the grid's field set writes exactly what the same decision from the table writes.
     *
     * The table's form posts its fields from the page: the action from the pressed button, the selection
     * from the checked boxes, the message and the note, and the group and role selects wherever it renders
     * them (for a deferral or a cancellation too, hidden in its sheet). The grid's client posts the same
     * names, the choosers only with an approval and only where the sheet offered them, and its action's
     * query carries the grid's address (scope, filters, sort, page, page size), which must decide nothing.
     *
     * Changes that must make it fail: decision::posted() reading a field under another name or cleaning it
     * differently; a carried address parameter reaching the decision.
     *
     * @param string $action confirm, wait or cancel.
     * @param bool $chooses Whether the decider holds enrol/apply:choosedecision.
     * @return void
     */
    #[DataProvider('decisions')]
    public function test_a_post_from_the_grid_writes_what_the_same_post_from_the_table_writes(string $action, bool $chooses): void {
        global $DB;

        [$fromtable, $tableapplicant] = $this->apply_for($this->mine);
        [$fromgrid, $gridapplicant] = $this->apply_for($this->mine);
        if (!$chooses) {
            $editingrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
            $context = \context_course::instance($this->course->id);
            assign_capability('enrol/apply:choosedecision', CAP_PROHIBIT, $editingrole, $context->id, true);
            accesslib_clear_all_caches_for_unit_testing();
        }
        $this->setUser($this->editing);
        // Preconditions: the choice is the decider's to make or not, and the role is one they may assign.
        $context = \context_course::instance($this->course->id);
        $this->assertSame($chooses, has_capability('enrol/apply:choosedecision', $context));
        $this->assertArrayHasKey($this->teacherrole, get_assignable_roles($context));

        $fields = [
            'sesskey' => sesskey(),
            'formaction' => $action,
            'outcomemessage' => "  Welcome aboard.\nSee you on Monday.  ",
            'decisionnote' => 'Checked the transcript.',
        ];
        $choice = ['groups' => [(string) $this->theirs->id], 'roleid' => (string) $this->teacherrole];

        // The table renders the selects for a decider who may choose, and its form posts them whatever the decision.
        $table = $fields + ['userenrolments' => [(string) $fromtable]] + ($chooses ? $choice : []);
        // The grid posts them with an approval offered them, and carries its address in the action.
        $grid = $fields + ['userenrolments' => [(string) $fromgrid]] + ($chooses && $action === 'confirm' ? $choice : []);
        $address = [
            'id' => (string) $this->instance->id,
            'status' => (string) ENROL_USER_SUSPENDED,
            'search' => 'Nobody by this name',
            'group' => 'all',
            'named' => 'key',
            'waited' => '1',
            'sort' => 'applicant',
            'dir' => 'desc',
            'page' => '3',
            'perpage' => '30',
        ];

        $this->assertSame(1, $this->post($table, ['id' => (string) $this->instance->id]));
        $this->assertSame(1, $this->post($grid, $address));

        $expected = $this->written($fromtable, $tableapplicant);
        $this->assertSame($expected, $this->written($fromgrid, $gridapplicant));
        // The control: the decision was taken, and as it was posted.
        $this->assertNotSame(submission::STATUS_PENDING, $expected['status']);
        $this->assertSame("Welcome aboard.\nSee you on Monday.", $expected['outcomemessage']);
        $this->assertSame('Checked the transcript.', $expected['decisionnote']);
        if ($action === 'confirm') {
            $this->assertSame($chooses, in_array((int) $this->theirs->id, $expected['groups'], true));
            $this->assertSame($chooses, array_key_exists($this->teacherrole, $expected['roles']));
        }
    }

    /**
     * A forged grid post decides neither the reader's own application nor one outside a narrowed moderator's groups.
     *
     * The grid draws no box on either (the rows service marks neither selectable, queueengine_test), and a
     * post naming them anyway reaches the decision methods, whose per-row check leaves them pending:
     * can_manage_application() refuses the reader's own application first, and the separate-groups
     * narrowing the other. The control is an application of the moderator's own group in the same post.
     *
     * Changes that must make it fail: the per-row check trusting the posted list; the own-application
     * refusal dropped for a deferral or a cancellation.
     *
     * @param string $action confirm, wait or cancel.
     * @return void
     */
    #[DataProvider('actions')]
    public function test_a_forged_post_leaves_the_readers_own_and_another_groups_application_alone(string $action): void {
        assign_capability(
            'enrol/apply:choosedecision',
            CAP_ALLOW,
            $this->teacherrole,
            \context_system::instance()->id,
            true
        );
        [$own] = $this->apply_for($this->mine, $this->moderator);
        [$ours] = $this->apply_for($this->mine);
        [$other] = $this->apply_for($this->theirs);
        $this->setUser($this->moderator);
        // Preconditions: the moderator decides in the course and is narrowed to their group.
        $this->assertTrue(has_capability('enrol/apply:manageapplications', \context_course::instance($this->course->id)));
        $this->assertFalse(has_capability('moodle/site:accessallgroups', \context_course::instance($this->course->id)));

        $decided = $this->post([
            'sesskey' => sesskey(),
            'formaction' => $action,
            'userenrolments' => [(string) $own, (string) $ours, (string) $other],
            'outcomemessage' => '',
            'decisionnote' => '',
        ], ['id' => (string) $this->instance->id]);

        $this->assertSame(1, $decided);
        $this->assertNotSame(ENROL_USER_SUSPENDED, $this->enrolment_status($ours), 'the control: the own group\'s application');
        $this->assertSame(ENROL_USER_SUSPENDED, $this->enrolment_status($own));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->enrolment_status($other));
    }
}
