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
 * Tests for the headless reader of one method's approval queue.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use enrol_apply\customfield\application_handler;
use enrol_apply\table\applications;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * The headless queue against the queue's own table, the masking, the facets and the cost of a page.
 *
 * The rows are compared with what the server table lists for the same reader and request, never
 * with a hand-written list, as the review walk is compared with its listing: a scope clause, a
 * join or an order that drifts on either side reddens the comparison.
 *
 * The course is in separate groups, with an editing teacher (not narrowed, who may read the report
 * there and so sees the earlier applications), a non-editing teacher made moderator of one group,
 * and a viewer who may look at every application and decide none. The method asks one profile
 * field (city) and one question of its own.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(queueengine::class)]
#[CoversClass(queuepreference::class)]
#[CoversClass(applications::class)]
#[CoversClass(capacity::class)]
final class queueengine_test extends \advanced_testcase {
    /** @var \enrol_plugin The apply plugin. */
    private $plugin;

    /** @var \stdClass A course in separate groups mode. */
    private $course;

    /** @var \stdClass Its apply method. */
    private $instance;

    /** @var \stdClass The moderator's group. */
    private $mine;

    /** @var \stdClass Another group, the editing teacher's. */
    private $theirs;

    /** @var \stdClass Editing teacher: decides every group, reads the report here. */
    private $editing;

    /** @var \stdClass Non-editing teacher made moderator of $mine. */
    private $moderator;

    /** @var \stdClass May view every application and decide none. */
    private $viewer;

    /** @var int The method's own question, a custom field. */
    private $questionid;

    /** @var int The frozen clock's time. */
    private $now;

    /** @var array Name => user enrolment id, for the fixture's applications. */
    private $ue = [];

    /**
     * The course, its groups, its readers and the method's questions.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB, $PAGE;

        parent::setUp();
        $this->resetAfterTest();
        $this->now = $this->mock_clock_with_frozen()->time();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        $this->plugin = enrol_get_plugin('apply');

        set_config('showuseridentity', 'email,city');
        set_config('queuefilterfields', 'city', 'enrol_apply');

        $system = \context_system::instance()->id;
        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $teacher, $system, true);

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $id = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $DB->set_field('enrol', 'customtext4', fieldset::from_keys(['s_city'])->to_json(), ['id' => $id]);
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $customfields = $generator->get_plugin_generator('core_customfield');
        $category = $customfields->create_category([
            'component' => 'enrol_apply',
            'area' => application_handler::AREA,
            'itemid' => $id,
        ]);
        $this->questionid = (int) $customfields->create_field([
            'categoryid' => (int) $category->get('id'),
            'type' => 'text',
            'shortname' => 'motive',
            'name' => 'Motive',
        ])->get('id');

        $this->mine = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Own group']);
        $this->theirs = $generator->create_group(['courseid' => $this->course->id, 'name' => 'Other group']);

        $this->moderator = $generator->create_and_enrol($this->course, 'teacher');
        $generator->create_group_member(['groupid' => $this->mine->id, 'userid' => $this->moderator->id]);

        // In a group, so the group filter offers "mine" to a reader who is not narrowed.
        $this->editing = $generator->create_and_enrol($this->course, 'editingteacher');
        $generator->create_group_member(['groupid' => $this->theirs->id, 'userid' => $this->editing->id]);
        $editingrole = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $coursecontext = \context_course::instance($this->course->id);
        assign_capability('enrol/apply:viewreports', CAP_ALLOW, $editingrole, $coursecontext->id, true);

        $this->viewer = $generator->create_and_enrol($this->course, 'student');
        $viewerrole = $generator->create_role(['shortname' => 'applyviewer']);
        assign_capability('enrol/apply:viewapplications', CAP_ALLOW, $viewerrole, $system, true);
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $viewerrole, $system, true);
        role_assign($viewerrole, $this->viewer->id, $coursecontext->id);

        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php', ['id' => $id]));
    }

    /**
     * Seven applications covering every clause the scope and the filters read.
     *
     * In applied order: Dora (chosen her group, two records), Zed and Gus (a tie, by key), Beth
     * (chose the other group, deferred), the editing teacher's own, Carl (no record), Eve (no
     * group, applied before).
     *
     * @return void
     */
    private function seed(): void {
        global $DB;

        $this->ue['dora'] = $this->applicant('Dora', 'Rouen', 20 * DAYSECS, $this->mine, 'chosen');
        // An older record of the same enrolment, which named no group: the newest is the one read.
        $this->record($this->ue['dora'], 30 * DAYSECS, null, '', 'Old words');
        $this->ue['zed'] = $this->applicant('Zed', 'Lille', 10 * DAYSECS, $this->mine, 'key', 'Sales');
        $this->ue['gus'] = $this->applicant('Gus', 'Nantes', 10 * DAYSECS, $this->mine, 'key');
        $this->ue['beth'] = $this->applicant('Beth', 'Paris', 5 * DAYSECS, $this->theirs, 'chosen', 'Data', ENROL_APPLY_USER_WAIT);
        $this->ue['own'] = $this->applicant('Own', 'Brest', 4 * DAYSECS, null, '', '', ENROL_USER_SUSPENDED, $this->editing);
        $this->ue['carl'] = $this->applicant('Carl', 'Lyon', 2 * DAYSECS, null, '', '', ENROL_USER_SUSPENDED, null, false);
        $this->ue['eve'] = $this->applicant('Eve', 'Lille', HOURSECS, null);

        // Eve applied to this course once before, through a method since removed.
        $eve = (int) $DB->get_field('user_enrolments', 'userid', ['id' => $this->ue['eve']]);
        $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $eve,
            'enrolid' => 0,
            'userenrolmentid' => 0,
            'comment' => '',
            'status' => submission::STATUS_CANCELLED,
            'outcomemessage' => '',
            'timecreated' => $this->now - 100 * DAYSECS,
            'timedecided' => $this->now - 90 * DAYSECS,
            'decidedby' => 0,
        ]);
    }

    /**
     * Put one applicant on the method's queue.
     *
     * @param string $name First name, also the last name with a suffix.
     * @param string $city City, a profile field the method asks.
     * @param int $ago Seconds since the application.
     * @param \stdClass|null $group The application's group, null for none.
     * @param string $via How the group was named: key or chosen.
     * @param string $motive The answer to the method's question.
     * @param int $status The enrolment's status.
     * @param \stdClass|null $user An existing user to apply as.
     * @param bool $record Whether the application has its durable record.
     * @return int The user enrolment id.
     */
    private function applicant(
        string $name,
        string $city,
        int $ago,
        ?\stdClass $group,
        string $via = '',
        string $motive = '',
        int $status = ENROL_USER_SUSPENDED,
        ?\stdClass $user = null,
        bool $record = true
    ): int {
        global $DB;

        $user = $user ?? $this->getDataGenerator()->create_user([
            'firstname' => $name,
            'lastname' => $name . 'son',
            'city' => $city,
        ]);
        $this->plugin->enrol_user($this->instance, $user->id, null, 0, 0, $status);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $user->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $DB->set_field('user_enrolments', 'timecreated', $this->now - $ago, ['id' => $ueid]);

        if ($record) {
            $submissionid = $this->record($ueid, $ago, $group, $via, 'Please');
            submission::write_answers($submissionid, [
                ['key' => 's_city', 'label' => 'City/town', 'value' => $city],
                ['key' => answers::key($this->questionid), 'label' => 'Motive', 'value' => $motive],
            ]);
        }

        return $ueid;
    }

    /**
     * Write a durable record for a user enrolment.
     *
     * @param int $ueid The user enrolment.
     * @param int $ago Seconds since it was written.
     * @param \stdClass|null $group The group it names, null for none.
     * @param string $via key or chosen.
     * @param string $comment The comment.
     * @return int The record's id.
     */
    private function record(int $ueid, int $ago, ?\stdClass $group, string $via, string $comment): int {
        global $DB;

        $ue = $DB->get_record('user_enrolments', ['id' => $ueid], '*', MUST_EXIST);

        return (int) $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $this->course->id,
            'userid' => $ue->userid,
            'enrolid' => $this->instance->id,
            'userenrolmentid' => $ueid,
            'comment' => $comment,
            'status' => submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => $this->now - $ago,
            'timedecided' => 0,
            'decidedby' => 0,
            'keygroupid' => $group && $via === 'key' ? $group->id : 0,
            'keyoutcome' => $group && $via === 'key' ? submission::KEY_FOR_REVIEW : submission::KEY_NONE,
            'chosengroupid' => $group && $via === 'chosen' ? $group->id : 0,
        ]);
    }

    /**
     * The user enrolment ids the queue's own table lists for the current user, in its order.
     *
     * @param string $scope all, pending or deferred.
     * @param string $search A search, empty for none.
     * @param array $filters Filter name => value.
     * @return array The ids.
     */
    private function table_ids(string $scope, string $search = '', array $filters = []): array {
        $table = applications::for_scope((int) $this->instance->id, $search, queueengine::status($scope), $filters);
        ob_start();
        $table->out(50, false);
        ob_end_clean();

        return array_map(static fn($row): int => (int) $row->userenrolmentid, array_values($table->rawdata));
    }

    /**
     * The headless rows for the current user.
     *
     * @param array $options What queueengine::rows() takes.
     * @return array Its answer.
     */
    private function rows(array $options = []): array {
        return queueengine::rows((int) $this->instance->id, $options);
    }

    /**
     * The user enrolment ids the headless reader lists, in its order.
     *
     * @param string $scope all, pending or deferred.
     * @param string $search A search, empty for none.
     * @param array $filters Filter name => value.
     * @return array The ids.
     */
    private function engine_ids(string $scope, string $search = '', array $filters = []): array {
        $response = $this->rows(['scope' => $scope, 'search' => $search, 'filters' => $filters]);

        return array_column($response['rows'], 'userenrolmentid');
    }

    /**
     * The headless rows are the table's rows, in set and in order, for every reader, every scope,
     * a search and every operator filter.
     *
     * Changes that must make it fail: any clause of the scope or of a filter read differently by the
     * two; an order that does not end in ue.id (Zed and Gus tie).
     *
     * @return void
     */
    public function test_the_rows_are_the_tables_rows_for_every_reader_and_request(): void {
        $this->seed();
        $day = static fn(int $ago): string => userdate(time() - $ago, '%Y-%m-%d', 99, false);

        $requests = [
            ['all', '', []],
            ['pending', '', []],
            ['deferred', '', []],
            ['all', 'Lille', []],
            ['all', 'Zed', []],
            ['all', '', ['group' => (string) $this->mine->id]],
            ['all', '', ['group' => (string) $this->theirs->id]],
            ['all', '', ['group' => 'none']],
            ['all', '', ['group' => 'mine']],
            ['all', '', ['named' => 'key']],
            ['all', '', ['named' => 'chosen']],
            ['all', '', ['named' => 'none']],
            ['all', '', ['waited' => '1']],
            ['all', '', ['waited' => '7']],
            ['all', '', ['appliedbefore' => '1']],
            ['all', '', ['city' => 'Lille']],
            ['all', '', ['appliedfrom' => $day(6 * DAYSECS)]],
            ['all', '', ['appliedto' => $day(6 * DAYSECS)]],
            ['pending', '', ['group' => (string) $this->mine->id, 'waited' => '7']],
        ];

        foreach (['editing' => $this->editing, 'moderator' => $this->moderator, 'viewer' => $this->viewer] as $who => $user) {
            $this->setUser($user);
            foreach ($requests as [$scope, $search, $filters]) {
                $this->assertSame(
                    $this->table_ids($scope, $search, $filters),
                    $this->engine_ids($scope, $search, $filters),
                    $who . ' ' . $scope . ' ' . $search . ' ' . json_encode($filters)
                );
            }
        }

        // The controls: what the comparison compared is the whole queue, in applied order, ties by id.
        $this->setUser($this->editing);
        $u = $this->ue;
        $this->assertSame(
            [$u['dora'], $u['zed'], $u['gus'], $u['beth'], $u['own'], $u['carl'], $u['eve']],
            $this->engine_ids('all')
        );
        $this->assertSame([$u['dora'], $u['eve']], $this->engine_ids('all', '', ['appliedbefore' => '1']));
        $this->assertSame([$u['zed'], $u['gus']], $this->engine_ids('all', '', ['named' => 'key']));
        $this->assertSame([$u['dora'], $u['beth']], $this->engine_ids('all', '', ['named' => 'chosen']));
        $this->assertSame(
            [$u['dora'], $u['zed'], $u['gus'], $u['beth'], $u['own']],
            $this->engine_ids('all', '', ['waited' => '3'])
        );
        $this->setUser($this->moderator);
        $this->assertSame([$u['dora'], $u['zed'], $u['gus']], $this->engine_ids('all'));
        // Earlier applications are not this reader's to know of: the filter is no filter for them.
        $this->assertSame([$u['dora'], $u['zed'], $u['gus']], $this->engine_ids('all', '', ['appliedbefore' => '1']));
    }

    /**
     * A user enrolment with two records is one row, counted once, reading the newest record's
     * comment, answers and group.
     *
     * The page is keyed on the enrolment id, so a doubled join still lists one row, whichever
     * record it keeps first; what a doubled row shows is the total and the counts, which are tallied
     * over the joined rows, so those are asserted as well as the row.
     *
     * Changes that must make it fail: the record joined without its "newest" restriction (the
     * total and the counts say eight).
     *
     * @return void
     */
    public function test_an_enrolment_with_two_records_is_one_row_of_its_newest_record(): void {
        $this->seed();
        $this->setUser($this->editing);
        queuepreference::write(['group', queueengine::ANSWERS, 'comment', 'history'], []);

        $response = $this->rows();
        $this->assertCount(7, $response['rows']);
        $this->assertSame(7, $response['total']);
        $this->assertSame(7, $response['counts']['all']);
        $rows = array_column($response['rows'], null, 'userenrolmentid');
        $this->assertCount(7, $rows);
        $cells = array_column($rows[$this->ue['dora']]['cells'], 'html', 'key');
        $this->assertSame('Please', $cells['comment']);
        $this->assertSame('Rouen', $cells['answer:s_city']);
        $this->assertSame('Own group', $cells['group']);
        // The older record of the same enrolment is an earlier application to the course.
        $this->assertSame('1', $cells['history']);
    }

    /**
     * Each reader's rows say what the reader may do with them: a decider selects every row but
     * their own, a viewer none, and every row opens its review page for its reader.
     *
     * Changes that must make it fail: the selectable flag ignoring that the reader may not decide,
     * or that the row is the reader's own; a review url for a row the review page refuses.
     *
     * @return void
     */
    public function test_the_rows_say_what_the_reader_may_do(): void {
        $this->seed();

        $this->setUser($this->editing);
        $response = $this->rows();
        $this->assertTrue($response['candecide']);
        $selectable = array_column($response['rows'], 'selectable', 'userenrolmentid');
        $this->assertFalse($selectable[$this->ue['own']]);
        unset($selectable[$this->ue['own']]);
        $this->assertSame([true], array_values(array_unique($selectable)));
        $own = array_column($response['rows'], null, 'userenrolmentid')[$this->ue['own']];
        $this->assertContains('own', array_column($own['applicant']['tags'], 'kind'));

        $this->setUser($this->viewer);
        $response = $this->rows();
        $this->assertFalse($response['candecide']);
        $this->assertCount(7, $response['rows']);
        $this->assertSame([false], array_values(array_unique(array_column($response['rows'], 'selectable'))));

        foreach ([$this->editing, $this->moderator, $this->viewer] as $user) {
            $this->setUser($user);
            foreach ($this->rows()['rows'] as $row) {
                $application = queue::application($row['userenrolmentid']);
                $this->assertNotNull($application);
                // Throws for a row the review page would refuse this reader.
                queue::require_review_access($application);
                $this->assertStringContainsString('userenrol=' . $row['userenrolmentid'], $row['reviewurl']);
            }
        }
    }

    /**
     * The review page's lookup reads the newest record of an enrolment that carries two, and
     * raises no debugging for a duplicate row.
     *
     * Changes that must make it fail: the record joined without its "newest" restriction (core
     * raises its duplicate-id notice and the lookup keeps whichever row came last).
     *
     * @return void
     */
    public function test_the_review_lookup_reads_the_newest_of_two_records(): void {
        global $DB;

        $this->seed();
        $ueid = $this->ue['dora'];
        $records = $DB->get_records('enrol_apply_submission', ['userenrolmentid' => $ueid], 'timecreated ASC, id ASC');
        $this->assertCount(2, $records);
        [$older, $newer] = array_values($records);
        $DB->set_field('enrol_apply_submission', 'decisionnote', 'Old note', ['id' => $older->id]);
        $DB->set_field('enrol_apply_submission', 'decisionnote', 'New note', ['id' => $newer->id]);

        $application = queue::application($ueid);
        $this->assertDebuggingNotCalled();
        $this->assertNotNull($application);
        $this->assertSame((int) $newer->id, (int) $application->submissionid);
        $this->assertSame('New note', $application->decisionnote);
        $this->assertSame('Please', $application->applycomment);

        // Control: with one record the same lookup returns that record.
        $DB->delete_records('enrol_apply_submission', ['id' => $newer->id]);
        $application = queue::application($ueid);
        $this->assertDebuggingNotCalled();
        $this->assertSame((int) $older->id, (int) $application->submissionid);
        $this->assertSame('Old note', $application->decisionnote);
        $this->assertSame('Old words', $application->applycomment);
    }

    /**
     * The applicant cell: the picture, the name, the profile only where it opens, the identity by
     * core's rule and the tags as data.
     *
     * Changes that must make it fail: a profile url where profilelink refuses it (a moderator, for
     * an applicant in no group yet); identity values the reader may not see; the tags baked into
     * one string.
     *
     * @return void
     */
    public function test_the_applicant_cell_is_data(): void {
        $this->seed();

        $this->setUser($this->editing);
        $rows = array_column($this->rows()['rows'], null, 'userenrolmentid');
        $zed = $rows[$this->ue['zed']]['applicant'];
        $this->assertSame('Zed Zedson', $zed['fullname']);
        // A generated user has no picture, so core draws the initials; the cell is core's output, with no link.
        $this->assertStringContainsString('userinitials', $zed['picture']);
        $this->assertStringContainsString('size-35', $zed['picture']);
        $this->assertStringNotContainsString('<a ', $zed['picture']);
        // Core names the user on the initials, and the name is beside them: the picture is hidden as a whole.
        $this->assertStringStartsWith('<span aria-hidden="true">', $zed['picture']);
        $this->assertNotSame('', $zed['profileurl']);
        $this->assertSame(['email', 'city'], array_column($zed['identity'], 'name'));
        $this->assertSame('Lille', array_column($zed['identity'], 'value', 'name')['city']);
        $this->assertSame(['key'], array_column($zed['tags'], 'kind'));
        $this->assertSame(['deferred'], array_column($rows[$this->ue['beth']]['applicant']['tags'], 'kind'));
        $this->assertSame(['appliedbefore'], array_column($rows[$this->ue['eve']]['applicant']['tags'], 'kind'));

        // A moderator is refused the profile of an applicant who shares no group with them yet.
        $this->setUser($this->moderator);
        $rows = array_column($this->rows()['rows'], null, 'userenrolmentid');
        $this->assertSame('', $rows[$this->ue['zed']]['applicant']['profileurl']);
        // And sees no earlier applications: the report is not theirs to read (CQ-D8).
        $this->assertNotContains('history', array_column($this->rows()['columns'], 'key'));
    }

    /**
     * A field the reader may not see is no column, no filter and in no row; the same field is all
     * three for a reader who may see it.
     *
     * Two custom roles that differ by moodle/site:viewuseridentity alone (the stock teacher
     * archetypes hold it, so they could not tell the two directions apart).
     *
     * Changes that must make it fail: the catalogue's questions not masked; a cell read for a key
     * that is no column; a sort on a hidden answer honoured.
     *
     * @return void
     */
    public function test_a_hidden_field_is_no_column_no_filter_and_in_no_row(): void {
        $this->seed();
        $readers = [];
        foreach (['identity' => true, 'plain' => false] as $name => $identity) {
            $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
            $role = $this->getDataGenerator()->create_role(['shortname' => 'queue' . $name]);
            $system = \context_system::instance()->id;
            assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $role, $system, true);
            assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $role, $system, true);
            if ($identity) {
                assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $role, $system, true);
            }
            role_assign($role, $user->id, \context_course::instance($this->course->id)->id);
            $readers[$name] = $user;
        }
        $question = queueengine::ANSWER_PREFIX . answers::key($this->questionid);

        // The control: the reader who may see them gets both answers as columns, cells and a filter.
        $this->setUser($readers['identity']);
        $response = $this->rows(['sort' => 'answer:s_city', 'direction' => 'desc']);
        $this->assertContains('answer:s_city', array_column($response['columns'], 'key'));
        $this->assertContains($question, array_column($response['columns'], 'key'));
        $this->assertSame(['city'], array_column($response['fieldfilters'], 'name'));
        $this->assertSame('answer:s_city', $response['sort']);
        $cells = array_column(array_column($response['rows'], null, 'userenrolmentid')[$this->ue['zed']]['cells'], 'html', 'key');
        $this->assertSame('Lille', $cells['answer:s_city']);
        $this->assertSame('Sales', $cells[$question]);

        // The reader who may not: absent everywhere, a stored choice and a sort naming them included.
        $this->setUser($readers['plain']);
        queuepreference::write(['answer:s_city', $question, 'comment'], ['answer:s_city' => 300]);
        $response = $this->rows(['sort' => 'answer:s_city', 'direction' => 'desc']);
        $this->assertSame('wait', $response['sort']);
        $this->assertSame([], array_filter(
            array_column($response['columns'], 'key'),
            static fn(string $key): bool => str_starts_with($key, queueengine::ANSWER_PREFIX)
        ));
        $this->assertSame([], $response['fieldfilters']);
        $this->assertSame(['comment'], array_column($response['rows'][0]['cells'], 'key'));
        $withoutpreference = $response;
        unset($withoutpreference['preference']);
        $json = json_encode($withoutpreference);
        foreach (['Lille', 'Rouen', 'Sales', 'Motive', 'City'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $json, $hidden);
        }
    }

    /**
     * Each facet's count is what the rows call lists with that option chosen, under every other
     * criterion in force, for a reader who is not narrowed and for one who is; the scope counts
     * are the scopes' totals.
     *
     * Changes that must make it fail: a facet counted under its own value; a tally reading a value
     * differently from the filter's predicate (the waited threshold's strictness, "mine", "none",
     * a named kind); the scope counts taken under the scope.
     *
     * @return void
     */
    public function test_each_facet_count_is_what_choosing_it_lists(): void {
        global $DB;

        $this->seed();
        // On a waited threshold exactly: "more than three days" does not list it.
        $DB->set_field('user_enrolments', 'timecreated', $this->now - 3 * DAYSECS, ['id' => $this->ue['carl']]);

        foreach (['editing' => $this->editing, 'moderator' => $this->moderator] as $who => $user) {
            $this->setUser($user);
            foreach ([[], ['waited' => '3'], ['group' => (string) $this->mine->id, 'named' => 'key']] as $background) {
                foreach (['all', 'pending'] as $scope) {
                    $response = $this->rows(['scope' => $scope, 'filters' => $background]);
                    foreach (['all', 'pending', 'deferred'] as $other) {
                        $this->assertSame(
                            $this->rows(['scope' => $other, 'filters' => $background])['total'],
                            $response['counts'][$other],
                            "{$who} scope {$other} under {$scope} " . json_encode($background)
                        );
                    }
                    $this->assertNotEmpty($response['facets']);
                    foreach ($response['facets'] as $facet) {
                        foreach ($facet['options'] as $option) {
                            $chosen = $this->rows([
                                'scope' => $scope,
                                'filters' => [$facet['name'] => $option['value']] + $background,
                            ]);
                            $this->assertSame(
                                $chosen['total'],
                                $option['count'],
                                "{$who} {$scope} {$facet['name']}={$option['value']} under " . json_encode($background)
                            );
                        }
                    }
                }
            }
        }

        // The controls: the facets are there, and the boundary row is not "more than three days".
        $this->setUser($this->editing);
        $facets = array_column($this->rows()['facets'], null, 'name');
        $this->assertSame(['group', 'named', 'waited', 'appliedbefore'], array_keys($facets));
        $this->assertSame(
            ['mine', 'none', (string) $this->theirs->id, (string) $this->mine->id],
            array_column($facets['group']['options'], 'value')
        );
        $waited = array_column($facets['waited']['options'], 'count', 'value');
        $this->assertSame([1 => 6, 3 => 5, 7 => 3, 14 => 1], $waited);
        $this->assertSame(2, array_column($facets['appliedbefore']['options'], 'count', 'value')['1']);
        $this->setUser($this->moderator);
        $facets = array_column($this->rows()['facets'], null, 'name');
        $this->assertSame([(string) $this->mine->id], array_column($facets['group']['options'], 'value'));
        $this->assertArrayNotHasKey('appliedbefore', $facets);
    }

    /**
     * A page is read in a bounded number of statements, the same for one row as for many; the
     * counts take one statement per distinct WHERE.
     *
     * Each request is made twice and the second measured, so caches the first warmed (strings,
     * capabilities, groups, the custom fields) count against neither.
     *
     * Changes that must make it fail: a read per row (answers, profile links, group visibility);
     * a read per facet or per option.
     *
     * @return void
     */
    public function test_a_page_is_read_in_a_bounded_number_of_statements(): void {
        // The viewer: no application of their own on the page, whose profile link reads apart.
        $this->setUser($this->viewer);
        $this->applicant('Solo', 'Lille', DAYSECS, $this->mine, 'key', 'Alone');

        $measure = function (array $options): int {
            global $DB;

            $this->rows($options);
            $before = $DB->perf_get_reads();
            $response = $this->rows($options);
            $reads = $DB->perf_get_reads() - $before;
            $this->assertNotEmpty($response['rows']);

            return $reads;
        };

        $one = $measure([]);
        $this->seed();
        $this->assertSame($one, $measure([]));

        // A facet whose value is applied is counted apart: one more statement each, never per option.
        $this->assertSame($one + 1, $measure(['scope' => 'pending']));
        $this->assertSame($one + 2, $measure(['scope' => 'pending', 'filters' => ['waited' => '1']]));
        // A date bound is no facet: it narrows every count alike, in the same reads.
        $from = userdate($this->now - 30 * DAYSECS, '%Y-%m-%d', 99, false);
        $this->assertSame($one, $measure(['filters' => ['appliedfrom' => $from]]));
    }

    /**
     * The sorts: the applied date (the default, oldest first), the name, an answer; a forged key
     * leaves the default; ue.id ends every order; a forged page size reads as the default and a
     * page past the last reads as the last.
     *
     * Changes that must make it fail: a sort key the catalogue does not offer honoured; the page
     * size not checked against its vocabulary.
     *
     * @return void
     */
    public function test_the_sorts_and_the_paging(): void {
        $this->seed();
        $this->setUser($this->editing);
        $u = $this->ue;
        $ids = fn(array $options): array => array_column($this->rows($options)['rows'], 'userenrolmentid');

        $this->assertSame([$u['eve'], $u['carl'], $u['own'], $u['beth'], $u['zed'], $u['gus'], $u['dora']], $ids([
            'sort' => 'wait',
            'direction' => 'desc',
        ]));
        // By first name, as the site writes full names: the generated teacher's name falls anywhere.
        $byname = array_values(array_intersect(
            $ids(['sort' => 'applicant']),
            [$u['beth'], $u['carl'], $u['dora'], $u['eve'], $u['gus'], $u['zed']]
        ));
        $this->assertSame([$u['beth'], $u['carl'], $u['dora'], $u['eve'], $u['gus'], $u['zed']], $byname);
        $this->assertSame([$u['zed'], $u['gus']], array_values(array_intersect(
            $ids(['sort' => 'applicant', 'direction' => 'desc']),
            [$u['zed'], $u['gus']]
        )));
        $bycity = $ids(['sort' => 'answer:s_city']);
        // Carl has no record and so no answer; the cities in order: Brest, Lille, Lille, Nantes, Paris, Rouen.
        $this->assertSame(
            [$u['own'], $u['zed'], $u['eve'], $u['gus'], $u['beth'], $u['dora']],
            array_values(array_diff($bycity, [$u['carl']]))
        );

        foreach (['answer:s_password', 'comment', 'group', 'u.id; DROP TABLE x'] as $forged) {
            $response = $this->rows(['sort' => $forged, 'direction' => 'desc']);
            $this->assertSame('wait', $response['sort'], $forged);
            $this->assertSame('asc', $response['direction'], $forged);
        }

        $response = $this->rows(['perpage' => 999]);
        $this->assertSame(queueengine::PERPAGE, $response['perpage']);
        $response = $this->rows(['perpage' => 30, 'page' => 7]);
        $this->assertSame(0, $response['page']);
        $this->assertCount(7, $response['rows']);
    }

    /**
     * Two applicants whose first and last names sort oppositely, enrolled on the method.
     *
     * @return array The user enrolment ids, keyed aaron (Aaron Zimmer) and zack (Zack Allen).
     */
    private function opposite_names(): array {
        $aaron = $this->getDataGenerator()->create_user(['firstname' => 'Aaron', 'lastname' => 'Zimmer']);
        $zack = $this->getDataGenerator()->create_user(['firstname' => 'Zack', 'lastname' => 'Allen']);

        return [
            'aaron' => $this->applicant('Aaron', 'Lille', DAYSECS, null, '', '', ENROL_USER_SUSPENDED, $aaron),
            'zack' => $this->applicant('Zack', 'Lille', DAYSECS, null, '', '', ENROL_USER_SUSPENDED, $zack),
        ];
    }

    /**
     * With the site's name format left on "language", the applicant sort reads core's template for
     * the language (first name, then last name, in English) and raises no debugging.
     *
     * Changes that must make it fail: the template read from a component that does not hold it
     * (get_string() raises its invalid-identifier debugging and no name is found in the result).
     *
     * @return void
     */
    public function test_the_applicant_sort_follows_the_language_template(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->seed();
        $this->setUser($this->editing);
        $both = $this->opposite_names();
        $CFG->fullnamedisplay = 'language';

        $ids = array_values(array_intersect(
            array_column($this->rows(['sort' => 'applicant'])['rows'], 'userenrolmentid'),
            $both
        ));
        $this->assertDebuggingNotCalled();
        $this->assertSame([$both['aaron'], $both['zack']], $ids);
    }

    /**
     * With a name format that writes the family name first, the applicant sort orders by last name.
     *
     * Changes that must make it fail: the name columns not read from the template (first name
     * always first).
     *
     * @return void
     */
    public function test_the_applicant_sort_follows_a_family_name_first_format(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->seed();
        $this->setUser($this->editing);
        $both = $this->opposite_names();
        $CFG->fullnamedisplay = 'lastname firstname';

        $ids = array_values(array_intersect(
            array_column($this->rows(['sort' => 'applicant'])['rows'], 'userenrolmentid'),
            $both
        ));
        $this->assertDebuggingNotCalled();
        $this->assertSame([$both['zack'], $both['aaron']], $ids);
    }

    /**
     * The columns: the default set leaves the earlier applications out; a stored choice is
     * intersected with the catalogue; a set is in force exactly when the reader's columns are it.
     *
     * Changes that must make it fail: the default set showing the history (CQ-D8); a stored key the
     * catalogue does not offer shown; a set never in force.
     *
     * @return void
     */
    public function test_the_columns_follow_the_preference_and_the_sets(): void {
        $this->seed();
        $this->setUser($this->editing);
        $question = queueengine::ANSWER_PREFIX . answers::key($this->questionid);
        $active = fn(): array => array_column(array_filter($this->rows()['columns'], fn(array $c): bool => $c['active']), 'key');

        $this->assertSame(['applicant', 'wait', 'group', 'answer:s_city', $question, 'comment'], $active());
        $presets = array_column($this->rows()['presets'], 'active', 'name');
        $this->assertSame(['everything' => true, 'glance' => false, 'answers' => false], $presets);

        queuepreference::write(['history', 'answer:c_999', 'wait'], []);
        $this->assertSame(['applicant', 'wait', 'history'], $active());

        queuepreference::write(queueengine::PRESETS['glance'], []);
        $this->assertSame(['applicant', 'wait', 'group', 'comment'], $active());
        $presets = array_column($this->rows()['presets'], 'active', 'name');
        $this->assertSame(['everything' => false, 'glance' => true, 'answers' => false], $presets);
        $this->assertSame(['wait', 'group', 'comment'], array_column($this->rows()['rows'][0]['cells'], 'key'));
    }

    /**
     * The tiles' figures: the method's capacity and the reader's own applications.
     *
     * Changes that must make it fail: the awaiting figures read outside the reader's scope; open
     * read off anything but capacity::state().
     *
     * @return void
     */
    public function test_the_summary_is_the_methods_capacity_and_the_readers_queue(): void {
        global $DB;

        $this->seed();
        $DB->update_record('enrol', (object) [
            'id' => $this->instance->id,
            'customint3' => 20,
            'customint4' => 10,
            'enrolenddate' => $this->now + 30 * DAYSECS,
        ]);
        $instance = $DB->get_record('enrol', ['id' => $this->instance->id], '*', MUST_EXIST);

        $this->setUser($this->editing);
        $summary = queueengine::summary($instance);
        $this->assertSame(10, $summary['places']);
        $this->assertSame(0, $summary['placestaken']);
        $this->assertSame(20, $summary['applicantlimit']);
        $this->assertSame(7, $summary['applicants']);
        $this->assertSame(7, $summary['awaiting']);
        $this->assertSame(1, $summary['deferred']);
        $this->assertSame(3, $summary['late']);
        $this->assertSame(20 * DAYSECS, $summary['oldestwait']);
        $this->assertSame(capacity::state($instance)->open, $summary['open']);
        $this->assertSame($this->now + 30 * DAYSECS, $summary['closes']);
        $this->assertFalse($summary['narrowed']);

        $this->setUser($this->moderator);
        $summary = queueengine::summary($instance);
        $this->assertTrue($summary['narrowed']);
        $this->assertSame(3, $summary['awaiting']);
        $this->assertSame(0, $summary['deferred']);
        // The method's own numbers, whoever reads them.
        $this->assertSame(7, $summary['applicants']);
    }

    /**
     * A reader of the queue holding the given capabilities and the two the queue's rows need to be
     * listed whole, through a role of its own (a stock archetype would carry others).
     *
     * @param array $allow Further capabilities allowed, at the site.
     * @param array $prohibit Capabilities prohibited, at the site.
     * @return \stdClass The user, enrolled as a student in the course.
     */
    private function queue_reader(array $allow = [], array $prohibit = []): \stdClass {
        $generator = $this->getDataGenerator();
        $system = \context_system::instance()->id;
        $user = $generator->create_and_enrol($this->course, 'student');
        $role = $generator->create_role();
        $allow = array_merge(['enrol/apply:manageapplications', 'moodle/site:accessallgroups'], $allow);
        foreach ($allow as $capability) {
            assign_capability($capability, CAP_ALLOW, $role, $system, true);
        }
        foreach ($prohibit as $capability) {
            assign_capability($capability, CAP_PROHIBIT, $role, $system, true);
        }
        role_assign($role, $user->id, \context_course::instance($this->course->id)->id);

        return $user;
    }

    /**
     * What a callable throws.
     *
     * @param callable $call The call.
     * @return \Throwable|null The exception, null when nothing was thrown.
     */
    private function thrown(callable $call): ?\Throwable {
        try {
            $call();
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    }

    /**
     * Every public reader of the engine refuses who the queue refuses, called directly, with the
     * exception require_listing() throws.
     *
     * The services run require_listing() first, so a refusal through them proves nothing about the
     * engine: it is called here with no service in front, by a user who is logged in and holds
     * nothing in the course and by a student. The table is built by the editing teacher, so the
     * catalogue's own check is the only one that can refuse.
     *
     * rows() builds the table for the reader's scope before the catalogue, which would refuse as
     * well, so its refusal is told apart by where it was thrown: from rows() itself, with no
     * catalogue() frame under require_listing().
     *
     * Changes that must make it fail: rows(), summary() or catalogue() not asking require_listing().
     *
     * @return void
     */
    public function test_each_reader_of_the_engine_refuses_who_the_queue_refuses(): void {
        $this->seed();
        $enrolid = (int) $this->instance->id;
        $instance = $this->instance;

        $this->setUser($this->editing);
        $table = queueengine::table($enrolid, 'all', '', []);
        $calls = [
            'rows' => static fn() => queueengine::rows($enrolid, []),
            'summary' => static fn() => queueengine::summary($instance),
            'catalogue' => static fn() => queueengine::catalogue($table),
        ];

        $outsider = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        foreach (['outsider' => $outsider, 'student' => $student] as $who => $user) {
            $this->setUser($user);
            $expected = $this->thrown(static fn() => queueengine::require_listing($enrolid));
            $this->assertInstanceOf(\required_capability_exception::class, $expected, $who);
            foreach ($calls as $name => $call) {
                $caught = $this->thrown($call);
                $this->assertInstanceOf(\required_capability_exception::class, $caught, $who . ' ' . $name);
                $this->assertSame($expected->getMessage(), $caught->getMessage(), $who . ' ' . $name);
            }
            // The reader of the queue is refused before its table is built, not by the catalogue after it.
            $frames = array_column($this->thrown($calls['rows'])->getTrace(), 'function');
            $this->assertContains('rows', $frames, $who);
            $this->assertNotContains('catalogue', $frames, $who);
        }

        // The control: a decider gets all three.
        $this->setUser($this->editing);
        $this->assertNotEmpty(queueengine::rows($enrolid, [])['rows']);
        $this->assertArrayHasKey('places', queueengine::summary($instance));
        $this->assertArrayHasKey('applicant', queueengine::catalogue($table));
    }

    /**
     * A reader who may see identity is still not shown what core would not show them: an identity
     * field the site does not list, a field it hides, a custom field nobody is shown.
     *
     * Three requested fields are withheld each for its own reason from a reader holding
     * moodle/site:viewuseridentity alone; a reader who also holds moodle/course:viewhiddenuserfields,
     * on a site that lists the identity field, gets the first two (the controls), and the field
     * visible to everyone is shown to both.
     *
     * Changes that must make it fail: the catalogue's questions not masked by the identity list, by
     * hiddenuserfields or by the custom field's visibility; a cell read for a key that is no column.
     *
     * @return void
     */
    public function test_a_reader_with_identity_is_not_shown_what_core_would_not_show_them(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/profile/lib.php');
        $this->seed();
        $generator = $this->getDataGenerator();
        $open = $generator->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'openfield', 'name' => 'Open field', 'visible' => PROFILE_VISIBLE_ALL,
        ]);
        $none = $generator->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'nonefield', 'name' => 'Nobody field', 'visible' => PROFILE_VISIBLE_NONE,
        ]);
        $openkey = fields::custom_key((int) $open->id);
        $nonekey = fields::custom_key((int) $none->id);
        // A custom field reaches the method's fields only through the site's pool of allowed fields.
        set_config('allowedfields', implode(',', array_merge(fields::DEFAULT_SET, [$openkey, $nonekey])), 'enrol_apply');
        $DB->set_field(
            'enrol',
            'customtext4',
            fieldset::from_keys(['s_city', 's_department', 's_country', $openkey, $nonekey])->to_json(),
            ['id' => $this->instance->id]
        );
        set_config('hiddenuserfields', 'country');

        $submissionid = (int) $DB->get_field('enrol_apply_submission', 'id', ['userenrolmentid' => $this->ue['zed']], MUST_EXIST);
        submission::write_answers($submissionid, [
            ['key' => 's_department', 'label' => 'Department', 'value' => 'DEPT-SECRET'],
            ['key' => 's_country', 'label' => 'Country', 'value' => 'COUNTRY-SECRET'],
            ['key' => $openkey, 'label' => 'Open field', 'value' => 'OPEN-VALUE'],
            ['key' => $nonekey, 'label' => 'Nobody field', 'value' => 'NONE-SECRET'],
        ]);
        $prefix = queueengine::ANSWER_PREFIX;
        $zed = $this->ue['zed'];
        $cellsof = static fn(array $response): array => array_column(
            array_column($response['rows'], null, 'userenrolmentid')[$zed]['cells'],
            'html',
            'key'
        );

        // The controls: the site lists the department and this reader may see hidden fields.
        set_config('showuseridentity', 'email,city,department');
        $this->setUser($this->queue_reader(['moodle/site:viewuseridentity', 'moodle/course:viewhiddenuserfields']));
        $response = $this->rows();
        $columns = array_column($response['columns'], 'key');
        foreach (['s_city', 's_department', 's_country', $openkey] as $key) {
            $this->assertContains($prefix . $key, $columns, $key);
        }
        $this->assertNotContains($prefix . $nonekey, $columns);
        $cells = $cellsof($response);
        $this->assertSame('DEPT-SECRET', $cells[$prefix . 's_department']);
        $this->assertSame('COUNTRY-SECRET', $cells[$prefix . 's_country']);
        $this->assertSame('OPEN-VALUE', $cells[$prefix . $openkey]);

        // The reader who may see identity and nothing more: the site lists no department.
        set_config('showuseridentity', 'email,city');
        $this->setUser($this->queue_reader(['moodle/site:viewuseridentity'], ['moodle/course:viewhiddenuserfields']));
        $response = $this->rows();
        $columns = array_column($response['columns'], 'key');
        $this->assertContains($prefix . 's_city', $columns);
        $this->assertContains($prefix . $openkey, $columns);
        foreach (['s_department', 's_country', $nonekey] as $withheld) {
            $this->assertNotContains($prefix . $withheld, $columns, $withheld);
            foreach ($response['rows'] as $row) {
                $this->assertNotContains($prefix . $withheld, array_column($row['cells'], 'key'), $withheld);
            }
        }
        $cells = $cellsof($response);
        $this->assertSame('Lille', $cells[$prefix . 's_city']);
        $this->assertSame('OPEN-VALUE', $cells[$prefix . $openkey]);
        $json = json_encode($response);
        foreach (['DEPT-SECRET', 'COUNTRY-SECRET', 'NONE-SECRET', 'Nobody field'] as $secret) {
            $this->assertStringNotContainsString($secret, $json, $secret);
        }
    }

    /**
     * A group with hidden visibility is not an option of the group facet and its name is in no row,
     * for a reader without moodle/course:viewhiddengroups; the engine says what the page says.
     *
     * The server table is rendered for the same reader: its group choices are the facet's options
     * and its page does not carry the name either. A reader who holds the capability gets the name in
     * both (the control).
     *
     * Changes that must make it fail: the group facet listing groups the reader may not see; the
     * group cell not judged by groupvisibility.
     *
     * @return void
     */
    public function test_a_hidden_group_is_no_facet_option_and_no_cell_for_a_reader_who_may_not_see_it(): void {
        $this->seed();
        $secret = $this->getDataGenerator()->create_group([
            'courseid' => $this->course->id,
            'name' => 'Secret cell',
            'visibility' => GROUPS_VISIBILITY_NONE,
        ]);
        $ueid = $this->applicant('Hidd', 'Metz', DAYSECS, $secret, 'key');
        $facetoptions = static fn(array $response): array => array_column(
            array_column($response['facets'], null, 'name')[groupfilter::NAME]['options'],
            'label',
            'value'
        );
        $groupcell = static fn(array $response): ?string => array_column(
            array_column($response['rows'], null, 'userenrolmentid')[$ueid]['cells'],
            'html',
            'key'
        )['group'] ?? null;
        $rendered = function (): array {
            $table = applications::for_scope((int) $this->instance->id, '', null, []);
            ob_start();
            $table->out(50, false);

            return [$table->get_group_choices(), ob_get_clean()];
        };

        // The control: a reader who may see hidden groups is told the name, by the engine and by the page.
        $this->setUser($this->queue_reader(['moodle/course:viewhiddengroups']));
        $response = $this->rows();
        $options = $facetoptions($response);
        $this->assertSame('Secret cell', $options[(string) $secret->id] ?? null);
        $this->assertSame('Secret cell', $groupcell($response));
        [$choices, $html] = $rendered();
        $this->assertSame($options, $choices);
        $this->assertStringContainsString('Secret cell', $html);

        $this->setUser($this->queue_reader([], ['moodle/course:viewhiddengroups']));
        $response = $this->rows();
        $options = $facetoptions($response);
        $this->assertArrayNotHasKey((string) $secret->id, $options);
        $this->assertNotContains('Secret cell', $options);
        $this->assertContains('Own group', $options);
        $this->assertContains($ueid, array_column($response['rows'], 'userenrolmentid'));
        $this->assertSame('', $groupcell($response));
        $this->assertStringNotContainsString('Secret cell', json_encode($response));
        [$choices, $html] = $rendered();
        $this->assertSame($options, $choices);
        $this->assertStringNotContainsString('Secret cell', $html);
    }

    /**
     * An answer and a comment arrive escaped in the cell HTML, and the applicant's name arrives plain.
     *
     * The text is a tag, an ampersand and a quote: the cell is raw HTML in the client, the name is
     * text it draws as text. The name carries an ampersand and a quote only, because the user
     * generator strips tags from a name.
     *
     * Changes that must make it fail: a cell passed through unescaped; the name escaped here, which
     * the client would escape again.
     *
     * @return void
     */
    public function test_an_answer_and_a_comment_arrive_escaped_and_the_name_plain(): void {
        global $DB;

        $raw = '<b>&"';
        $name = 'X&"';
        $ueid = $this->applicant($name, 'Lille', DAYSECS, null, '', $raw);
        $DB->set_field('enrol_apply_submission', 'comment', $raw, ['userenrolmentid' => $ueid]);

        $this->setUser($this->editing);
        $row = array_column($this->rows()['rows'], null, 'userenrolmentid')[$ueid];
        $cells = array_column($row['cells'], 'html', 'key');
        $question = queueengine::ANSWER_PREFIX . answers::key($this->questionid);

        $escaped = '&lt;b&gt;&amp;&quot;';
        $this->assertSame($escaped, $cells[$question]);
        $this->assertSame($escaped, $cells['comment']);
        $this->assertStringNotContainsString('<b>', json_encode(array_values($cells)));
        // Plain text: the ampersand and the quote arrive as typed, not as entities.
        $fullname = $row['applicant']['fullname'];
        $this->assertSame($name . ' ' . $name . 'son', $fullname);
        $this->assertStringContainsString('&"', $fullname);
        $this->assertStringNotContainsString('&amp;', $fullname);
        $this->assertStringNotContainsString('&quot;', $fullname);
    }
}
