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
 * Tests for the headless reader of the queues that span courses (enrolid 0, stage W1).
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use enrol_apply\table\applications;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');

/**
 * The wide queue read headlessly, against the queue's own table, for each of its three readers.
 *
 * Two courses in two category branches: Alpha (under Top / Sub) with two apply methods, Morning
 * (one place, taken: at its limit) and Evening; Beta (under Other) with one method and a group, Blue.
 * A third course, Gamma, has no apply method. The readers: a site-wide decider and a site-wide viewer
 * (roles at the system context), a mentor of three applicants across both courses (a role in their
 * user contexts), and an editing teacher of Alpha, who holds the deciding capability there and
 * nowhere else. The rows are compared with what the table lists for the same reader and request,
 * never with a hand-written list.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(queueengine::class)]
#[CoversClass(applications::class)]
#[CoversClass(coursefilter::class)]
#[CoversClass(queuepreference::class)]
final class wide_queue_test extends \advanced_testcase {
    /** @var int The most statements a further distinct course of a page may cost (see the cost test). */
    private const PER_COURSE = 10;

    /** @var \enrol_plugin The apply plugin. */
    private $plugin;

    /** @var int The frozen clock's time. */
    private $now;

    /** @var array Name => category record: top, sub (under top), other. */
    private $categories = [];

    /** @var array Name => course record: alpha, beta, gamma (no apply method). */
    private $courses = [];

    /** @var array Name => {enrol} record: morning and evening (Alpha), beta (Beta). */
    private $methods = [];

    /** @var \stdClass Beta's group. */
    private $blue;

    /** @var array Name => user enrolment id, for the seeded applications. */
    private $ue = [];

    /** @var array Name => applicant user record. */
    private $users = [];

    /** @var \stdClass Holds the deciding capability and the identity capability at the system. */
    private $decider;

    /** @var int The site-wide decider's role. */
    private $deciderrole;

    /** @var \stdClass Holds the viewing capability at the system. */
    private $viewer;

    /** @var \stdClass Holds the deciding capability in three applicants' user contexts. */
    private $mentor;

    /** @var \stdClass Editing teacher of Alpha, with no mentee. */
    private $teacher;

    /**
     * The categories, the courses, the methods and the readers.
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

        $generator = $this->getDataGenerator();
        $this->categories['top'] = $generator->create_category(['name' => 'Top']);
        $this->categories['sub'] = $generator->create_category(['name' => 'Sub', 'parent' => $this->categories['top']->id]);
        $this->categories['other'] = $generator->create_category(['name' => 'Other']);
        foreach (['alpha' => 'sub', 'beta' => 'other', 'gamma' => 'other'] as $name => $category) {
            $this->courses[$name] = $generator->create_course([
                'fullname' => ucfirst($name) . ' course',
                'category' => $this->categories[$category]->id,
            ]);
        }

        $this->methods['morning'] = $this->method($this->courses['alpha'], 'Morning intake', ['customint4' => 1]);
        $this->methods['evening'] = $this->method($this->courses['alpha'], 'Evening intake');
        $this->methods['beta'] = $this->method($this->courses['beta'], '');
        $this->blue = $generator->create_group(['courseid' => $this->courses['beta']->id, 'name' => 'Blue']);

        $system = \context_system::instance();
        $this->deciderrole = (int) $generator->create_role(['shortname' => 'widedecider']);
        // The group filter is offered to a reader who may see hidden groups, as the stock roles may.
        $capabilities = ['enrol/apply:manageapplications', 'moodle/site:viewuseridentity', 'moodle/course:viewhiddengroups'];
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $this->deciderrole, $system->id, true);
        }
        $this->decider = $generator->create_user(['firstname' => 'Site', 'lastname' => 'Decider']);
        role_assign($this->deciderrole, $this->decider->id, $system->id);

        $viewerrole = $generator->create_role(['shortname' => 'wideviewer']);
        assign_capability('enrol/apply:viewapplications', CAP_ALLOW, $viewerrole, $system->id, true);
        assign_capability('moodle/course:viewhiddengroups', CAP_ALLOW, $viewerrole, $system->id, true);
        $this->viewer = $generator->create_user(['firstname' => 'Site', 'lastname' => 'Viewer']);
        role_assign($viewerrole, $this->viewer->id, $system->id);

        $this->teacher = $generator->create_and_enrol($this->courses['alpha'], 'editingteacher');

        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php'));
    }

    /**
     * An apply method in a course.
     *
     * @param \stdClass $course The course.
     * @param string $name The method's name, '' for the plugin's own wording.
     * @param array $fields Further {enrol} fields.
     * @return \stdClass The {enrol} record.
     */
    private function method(\stdClass $course, string $name, array $fields = []): \stdClass {
        global $DB;

        $id = $this->plugin->add_instance($course, ['name' => $name] + $fields + $this->plugin->get_instance_defaults());

        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Seven applications across the three methods, a taken place, and the mentor of three of them.
     *
     * Alpha: Ann (Morning, Lille, 20 days), Bob (Morning, deferred, 10 days), Cid (Evening, Paris,
     * 10 days, a tie with Bob), Gil (Evening, 3 days); and an approved learner holding Morning's one
     * place. Beta: Dan (key group Blue, Lille, 5 days), Eve (no group, 2 days), Fay (a key group since
     * deleted, an hour). The mentor mentors Ann, Cid and Dan.
     *
     * @return void
     */
    private function seed(): void {
        global $DB;

        $this->ue['ann'] = $this->applicant('morning', 'Ann', 'Lille', 20 * DAYSECS);
        $this->ue['bob'] = $this->applicant('morning', 'Bob', 'Rouen', 10 * DAYSECS, ENROL_APPLY_USER_WAIT);
        $this->ue['cid'] = $this->applicant('evening', 'Cid', 'Paris', 10 * DAYSECS);
        $this->ue['gil'] = $this->applicant('evening', 'Gil', 'Nantes', 3 * DAYSECS);
        $this->ue['dan'] = $this->applicant('beta', 'Dan', 'Lille', 5 * DAYSECS, ENROL_USER_SUSPENDED, (int) $this->blue->id);
        $this->ue['eve'] = $this->applicant('beta', 'Eve', 'Brest', 2 * DAYSECS);
        $this->ue['fay'] = $this->applicant('beta', 'Fay', 'Metz', HOURSECS, ENROL_USER_SUSPENDED, 999999);

        // Morning's one place, taken by an approved learner, who is in no queue.
        $learner = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->methods['morning'], $learner->id, null, 0, 0, ENROL_USER_ACTIVE);

        $this->mentor = $this->getDataGenerator()->create_user(['firstname' => 'Mentor', 'lastname' => 'Wide']);
        $role = $this->getDataGenerator()->create_role(['shortname' => 'widementor']);
        set_role_contextlevels($role, [CONTEXT_USER]);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $role, \context_system::instance()->id, true);
        foreach (['ann', 'cid', 'dan'] as $mentee) {
            role_assign($role, $this->mentor->id, \context_user::instance($this->users[$mentee]->id)->id);
        }
    }

    /**
     * Put one applicant on a method's queue, with a durable record and a frozen city answer.
     *
     * @param string $method A key of $methods.
     * @param string $name First name; the last name is the same with a suffix.
     * @param string $city The city, in the profile and in the answers.
     * @param int $ago Seconds since the application.
     * @param int $status The enrolment's status.
     * @param int $keygroupid The key's group, 0 for none.
     * @return int The user enrolment id.
     */
    private function applicant(
        string $method,
        string $name,
        string $city,
        int $ago,
        int $status = ENROL_USER_SUSPENDED,
        int $keygroupid = 0
    ): int {
        global $DB;

        $instance = $this->methods[$method];
        $user = $this->getDataGenerator()->create_user([
            'firstname' => $name,
            'lastname' => $name . 'son',
            'city' => $city,
        ]);
        $this->users[strtolower($name)] = $user;
        $this->plugin->enrol_user($instance, $user->id, null, 0, 0, $status);
        $ueid = (int) $DB->get_field('user_enrolments', 'id', ['userid' => $user->id, 'enrolid' => $instance->id], MUST_EXIST);
        $DB->set_field('user_enrolments', 'timecreated', $this->now - $ago, ['id' => $ueid]);

        $submissionid = (int) $DB->insert_record('enrol_apply_submission', (object) [
            'courseid' => $instance->courseid,
            'userid' => $user->id,
            'enrolid' => $instance->id,
            'userenrolmentid' => $ueid,
            'comment' => 'From ' . $name,
            'status' => submission::STATUS_PENDING,
            'outcomemessage' => '',
            'timecreated' => $this->now - $ago,
            'timedecided' => 0,
            'decidedby' => 0,
            'keygroupid' => $keygroupid,
            'keyoutcome' => $keygroupid ? submission::KEY_FOR_REVIEW : submission::KEY_NONE,
        ]);
        submission::write_answers($submissionid, [['key' => 's_city', 'label' => 'City/town', 'value' => $city]]);

        return $ueid;
    }

    /**
     * The user enrolment ids the wide table lists for the current user, in its order.
     *
     * @param string $scope all, pending or deferred.
     * @param string $search A search, empty for none.
     * @param array $filters Filter name => value.
     * @param string $sort A catalogue sort key, '' for the default.
     * @param string $direction asc or desc.
     * @return array The ids.
     */
    private function table_ids(
        string $scope,
        string $search = '',
        array $filters = [],
        string $sort = '',
        string $direction = 'asc'
    ): array {
        $table = applications::for_scope(0, $search, queueengine::status($scope), $filters);
        $order = $direction === 'desc' ? SORT_DESC : SORT_ASC;
        $sortdata = match ($sort) {
            'wait' => [['sortby' => 'applydate', 'sortorder' => $order]],
            'course' => [['sortby' => 'course', 'sortorder' => $order]],
            'applicant' => [
                ['sortby' => 'firstname', 'sortorder' => $order],
                ['sortby' => 'lastname', 'sortorder' => $order],
            ],
            default => [],
        };
        if ($sortdata) {
            $table->set_sortdata($sortdata);
        }
        ob_start();
        $table->out(50, false);
        ob_end_clean();

        return array_map(static fn($row): int => (int) $row->userenrolmentid, array_values($table->rawdata));
    }

    /**
     * The headless rows of the wide queue for the current user.
     *
     * @param array $options What queueengine::rows() takes.
     * @return array Its answer.
     */
    private function rows(array $options = []): array {
        return queueengine::rows(0, $options);
    }

    /**
     * The user enrolment ids the headless reader lists, in its order.
     *
     * @param array $options What queueengine::rows() takes.
     * @return array The ids.
     */
    private function engine_ids(array $options = []): array {
        return array_column($this->rows($options)['rows'], 'userenrolmentid');
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
     * 0 is the wide queue's door and nothing else: no instance and the system context; a negative id
     * and another plugin's method are refused as before.
     *
     * Changes that must make it fail: locate() taking any id at or below 0 as the wide queue; the
     * wide queue given another context than the system's.
     *
     * @return void
     */
    public function test_zero_locates_the_system_context_and_no_other_id_does(): void {
        global $DB;

        [$instance, $context] = queueengine::locate(0);
        $this->assertNull($instance);
        $this->assertSame(CONTEXT_SYSTEM, $context->contextlevel);
        $this->assertSame(\context_system::instance()->id, $context->id);

        $where = ['courseid' => $this->courses['alpha']->id, 'enrol' => 'manual'];
        $manual = (int) $DB->get_field('enrol', 'id', $where, MUST_EXIST);
        foreach ([-1, $manual] as $id) {
            $caught = $this->thrown(static fn() => queueengine::locate($id));
            $this->assertInstanceOf(\dml_missing_record_exception::class, $caught, (string) $id);
        }

        // The control: a method's id still locates its course.
        [$instance, $context] = queueengine::locate((int) $this->methods['beta']->id);
        $this->assertSame((int) $this->methods['beta']->id, (int) $instance->id);
        $this->assertSame(\context_course::instance($this->courses['beta']->id)->id, $context->id);
    }

    /**
     * Every public reader of the engine refuses, for 0, who the wide queue refuses: an editing
     * teacher of one course with no mentee, called with no service in front. The three readers of
     * the wide queue are each answered.
     *
     * The table is built by the site-wide decider, so the catalogue's own check is the only one that
     * can refuse there; rows() refuses before it builds its table (no catalogue frame).
     *
     * Changes that must make it fail: rows(), summary() or catalogue() skipping require_listing() for
     * 0; the teacher's course grant read as a site-wide one.
     *
     * @return void
     */
    public function test_each_reader_of_the_wide_queue_refuses_an_ordinary_teacher(): void {
        $this->seed();

        $this->setUser($this->decider);
        $table = queueengine::table(0, 'all', '', []);
        $calls = [
            'rows' => static fn() => queueengine::rows(0, []),
            'summary' => static fn() => queueengine::summary(null),
            'catalogue' => static fn() => queueengine::catalogue($table),
        ];

        $this->setUser($this->teacher);
        $expected = $this->thrown(static fn() => queueengine::require_listing(0));
        $this->assertInstanceOf(\required_capability_exception::class, $expected);
        foreach ($calls as $name => $call) {
            $caught = $this->thrown($call);
            $this->assertInstanceOf(\required_capability_exception::class, $caught, $name);
            $this->assertSame($expected->getMessage(), $caught->getMessage(), $name);
        }
        $frames = array_column($this->thrown($calls['rows'])->getTrace(), 'function');
        $this->assertContains('rows', $frames);
        $this->assertNotContains('catalogue', $frames);
        // The teacher's own method queue still opens: the refusal is the wide queue's alone.
        $this->assertNotEmpty(queueengine::rows((int) $this->methods['morning']->id, [])['rows']);

        // The controls: each reader of the wide queue is answered by all three, with its kind.
        foreach ([[$this->decider, 'site'], [$this->viewer, 'site'], [$this->mentor, 'mentees']] as [$user, $kind]) {
            $this->setUser($user);
            $response = queueengine::rows(0, []);
            $this->assertSame($kind, $response['kind']);
            $this->assertNotEmpty($response['rows']);
            $this->assertArrayHasKey('awaiting', queueengine::summary(null));
            $this->assertArrayHasKey('course', queueengine::catalogue(queueengine::table(0, 'all', '', [])));
        }
    }

    /**
     * The headless rows are the table's rows, in set and in order, for the site-wide decider, the
     * site-wide viewer and the mentor, over every scope, a search, each sort the catalogue offers in
     * both directions and each wide filter.
     *
     * Changes that must make it fail: any clause of the scope or of a filter read differently by the
     * two; the course sort without the method's name and id on either side; an order that does not
     * end in ue.id (Bob and Cid tie).
     *
     * @return void
     */
    public function test_the_wide_rows_are_the_tables_rows_for_every_reader_and_request(): void {
        $this->seed();
        $alpha = (string) $this->courses['alpha']->id;
        $beta = (string) $this->courses['beta']->id;
        $morning = (string) $this->methods['morning']->id;
        $evening = (string) $this->methods['evening']->id;

        $requests = [
            ['all', '', []],
            ['pending', '', []],
            ['deferred', '', []],
            ['all', 'Alpha', []],
            ['all', 'Dan', []],
            ['all', '', ['course' => $alpha]],
            ['all', '', ['course' => $beta]],
            ['all', '', ['category' => (string) $this->categories['top']->id]],
            ['all', '', ['category' => (string) $this->categories['other']->id]],
            ['all', '', ['method' => $morning]],
            ['all', '', ['method' => $evening, 'course' => $alpha]],
            ['all', '', ['grouped' => 'yes']],
            ['all', '', ['grouped' => 'no']],
            ['all', '', ['waited' => '7']],
            ['pending', '', ['course' => $alpha, 'waited' => '3', 'grouped' => 'no']],
        ];
        $sorts = [
            ['', 'asc'],
            ['wait', 'desc'],
            ['course', 'asc'],
            ['course', 'desc'],
            ['applicant', 'asc'],
            ['applicant', 'desc'],
        ];

        foreach (['decider' => $this->decider, 'viewer' => $this->viewer, 'mentor' => $this->mentor] as $who => $user) {
            $this->setUser($user);
            foreach ($requests as [$scope, $search, $filters]) {
                $this->assertSame(
                    $this->table_ids($scope, $search, $filters),
                    $this->engine_ids(['scope' => $scope, 'search' => $search, 'filters' => $filters]),
                    $who . ' ' . $scope . ' ' . $search . ' ' . json_encode($filters)
                );
            }
            foreach ($sorts as [$sort, $direction]) {
                $this->assertSame(
                    $this->table_ids('all', '', [], $sort, $direction),
                    $this->engine_ids(['sort' => $sort, 'direction' => $direction]),
                    "{$who} sorted by {$sort} {$direction}"
                );
            }
        }

        // The controls: what was compared is the whole site, in applied order, a tie by id, and each filter narrows.
        $this->setUser($this->decider);
        $u = $this->ue;
        $this->assertSame([$u['ann'], $u['bob'], $u['cid'], $u['dan'], $u['gil'], $u['eve'], $u['fay']], $this->engine_ids());
        $this->assertSame([$u['ann'], $u['bob']], $this->engine_ids(['filters' => ['method' => $morning]]));
        $this->assertSame([$u['dan']], $this->engine_ids(['filters' => ['grouped' => 'yes']]));
        $top = ['category' => (string) $this->categories['top']->id];
        $this->assertSame([$u['ann'], $u['bob'], $u['cid'], $u['gil']], $this->engine_ids(['filters' => $top]));
        // Under one course, Evening's applications before Morning's: the method's name, then its id.
        $this->assertSame(
            [$u['cid'], $u['gil'], $u['ann'], $u['bob'], $u['dan'], $u['eve'], $u['fay']],
            $this->engine_ids(['sort' => 'course'])
        );
        $this->setUser($this->mentor);
        $this->assertSame([$u['ann'], $u['cid'], $u['dan']], $this->engine_ids());
    }

    /**
     * A filter the table would not apply is ignored by the engine exactly as by the table: a method
     * that is no apply instance, another plugin's method, a course with no apply method, a category
     * that does not exist; a method of another course than the course filter lists nothing on both.
     *
     * Changes that must make it fail: the method filter applied without asking whether it names an
     * apply instance (the forged ids would list nothing).
     *
     * @return void
     */
    public function test_a_forged_course_category_or_method_does_what_the_table_does(): void {
        global $DB;

        $this->seed();
        $this->setUser($this->decider);
        $manual = (string) $DB->get_field(
            'enrol',
            'id',
            ['courseid' => $this->courses['alpha']->id, 'enrol' => 'manual'],
            MUST_EXIST
        );
        $unfiltered = $this->table_ids('all');

        $ignored = [
            ['method' => '999999'],
            ['method' => $manual],
            ['method' => '-3'],
            ['course' => (string) $this->courses['gamma']->id],
            ['category' => '999999'],
        ];
        foreach ($ignored as $filters) {
            $label = json_encode($filters);
            $this->assertSame($this->table_ids('all', '', $filters), $this->engine_ids(['filters' => $filters]), $label);
            // The control the table cannot give: ignored is the unfiltered queue, not nothing.
            $this->assertSame($unfiltered, $this->engine_ids(['filters' => $filters]), json_encode($filters));
            $this->assertSame([], $this->rows(['filters' => $filters])['filters'], json_encode($filters));
        }

        $crossed = ['method' => (string) $this->methods['morning']->id, 'course' => (string) $this->courses['beta']->id];
        $this->assertSame($this->table_ids('all', '', $crossed), $this->engine_ids(['filters' => $crossed]));
        $this->assertSame([], $this->engine_ids(['filters' => $crossed]));
    }

    /**
     * The course, category, method and grouped filters are the site-wide queue's alone: a mentor and
     * a reader of one method's queue who send them are listed what they are listed without them.
     *
     * Changes that must make it fail: any of the four applied outside coursefilter::offered().
     *
     * @return void
     */
    public function test_the_site_filters_are_ignored_off_the_site_wide_queue(): void {
        $this->seed();
        $forged = [
            ['course' => (string) $this->courses['beta']->id],
            ['category' => (string) $this->categories['other']->id],
            ['method' => (string) $this->methods['beta']->id],
            ['grouped' => 'yes'],
        ];

        $this->setUser($this->mentor);
        $all = $this->engine_ids();
        $this->assertCount(3, $all);
        foreach ($forged as $filters) {
            $this->assertSame($all, $this->engine_ids(['filters' => $filters]), 'mentor ' . json_encode($filters));
            $this->assertSame($all, $this->table_ids('all', '', $filters), 'mentor table ' . json_encode($filters));
        }

        $this->setUser($this->decider);
        $morning = (int) $this->methods['morning']->id;
        $own = array_column(queueengine::rows($morning, [])['rows'], 'userenrolmentid');
        $this->assertCount(2, $own);
        foreach ($forged as $filters) {
            $response = queueengine::rows($morning, ['filters' => $filters]);
            $this->assertSame($own, array_column($response['rows'], 'userenrolmentid'), 'method ' . json_encode($filters));
            $this->assertSame([], $response['filters'], 'method ' . json_encode($filters));
        }

        // The control: on the site-wide queue each of them narrows.
        $whole = $this->engine_ids();
        foreach ($forged as $filters) {
            $this->assertNotSame($whole, $this->engine_ids(['filters' => $filters]), 'site ' . json_encode($filters));
        }
    }

    /**
     * A mentor is told nothing the table does not tell them: no identity value, no details, no group,
     * no site facet, never "full", never a method queue's address, and no sort or filter outside their
     * catalogue; the same applications read by the site-wide decider carry all of it.
     *
     * Changes that must make it fail: the mentee scope given an identity context, the details column
     * or the group column; the capacity read for a mentor; the method queue's address given to them.
     *
     * @return void
     */
    public function test_a_mentor_gets_no_identity_details_group_or_site_facts(): void {
        $this->seed();
        $morning = (int) $this->methods['morning']->id;

        // The control: the site-wide decider over the same applications.
        $this->setUser($this->decider);
        $response = $this->rows(['sort' => 'course']);
        $this->assertSame('site', $response['kind']);
        $keys = array_column($response['columns'], 'key');
        $this->assertSame(['applicant', 'course', 'wait', 'group', 'details', 'comment'], $keys);
        $this->assertSame(['course', 'category', 'waited', 'grouped'], array_column($response['facets'], 'name'));
        $rows = array_column($response['rows'], null, 'userenrolmentid');
        $ann = $rows[$this->ue['ann']];
        $this->assertSame('Lille', array_column($ann['applicant']['identity'], 'value', 'name')['city']);
        $this->assertStringContainsString('Lille', array_column($ann['cells'], 'html', 'key')['details']);
        $this->assertSame('Blue', array_column($rows[$this->ue['dan']]['cells'], 'html', 'key')['group']);
        $this->assertSame($morning, $ann['method']['id']);
        $this->assertSame('Morning intake', $ann['method']['name']);
        $this->assertTrue($ann['method']['full']);
        $this->assertStringContainsString('manage.php?id=' . $morning, $ann['method']['queueurl']);
        $this->assertFalse($rows[$this->ue['cid']]['method']['full']);
        $this->assertSame('Alpha course', $ann['course']['name']);
        $this->assertStringContainsString('course/view.php?id=' . $this->courses['alpha']->id, $ann['course']['url']);
        $this->assertSame('course', $response['sort']);

        $this->setUser($this->mentor);
        $response = $this->rows();
        $this->assertSame('mentees', $response['kind']);
        $this->assertSame(['applicant', 'course', 'wait', 'comment'], array_column($response['columns'], 'key'));
        $this->assertSame(['waited'], array_column($response['facets'], 'name'));
        $this->assertSame([], $response['fieldfilters']);
        $this->assertCount(3, $response['rows']);
        foreach ($response['rows'] as $row) {
            $this->assertSame([], $row['applicant']['identity']);
            $this->assertNotContains('details', array_column($row['cells'], 'key'));
            $this->assertNotContains('group', array_column($row['cells'], 'key'));
            $this->assertFalse($row['method']['full']);
            $this->assertSame('', $row['method']['queueurl']);
            // The course and the method are theirs to read: they are in the table's row too.
            $this->assertNotSame('', $row['course']['name']);
        }
        $json = json_encode($response);
        foreach (['Lille', 'Paris', 'Blue'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $json, $hidden);
        }

        // A stored choice, a forged sort and forged filters add nothing.
        queuepreference::write(['details', 'group', 'course'], [], null, true);
        foreach (['details', 'group', 'answer:s_city', 'history'] as $forged) {
            $response = $this->rows(['sort' => $forged, 'direction' => 'desc', 'filters' => [
                'course' => (string) $this->courses['beta']->id,
                'method' => (string) $this->methods['beta']->id,
                'grouped' => 'yes',
                'details' => 'Lille',
            ]]);
            $this->assertSame('wait', $response['sort'], $forged);
            $this->assertCount(3, $response['rows'], $forged);
            $this->assertSame([], $response['filters'], $forged);
            $this->assertSame(['applicant', 'course'], array_values(array_column(
                array_filter($response['columns'], static fn(array $column): bool => $column['active']),
                'key'
            )), $forged);
        }
    }

    /**
     * The details are masked in each row's own course: the site-wide decider may see the city in
     * Alpha and is refused identity in Beta, so Ann's details carry it and Dan's do not.
     *
     * Changes that must make it fail: the details masked once in the system context, or in another
     * row's course.
     *
     * @return void
     */
    public function test_the_details_are_masked_in_each_rows_own_course(): void {
        $this->seed();
        assign_capability(
            'moodle/site:viewuseridentity',
            CAP_PROHIBIT,
            $this->deciderrole,
            \context_course::instance($this->courses['beta']->id)->id,
            true
        );

        $this->setUser($this->decider);
        $rows = array_column($this->rows()['rows'], null, 'userenrolmentid');
        $details = static fn(array $row): string => array_column($row['cells'], 'html', 'key')['details'] ?? '';

        $this->assertStringContainsString('Lille', $details($rows[$this->ue['ann']]));
        $this->assertStringNotContainsString('Lille', $details($rows[$this->ue['dan']]));
        $this->assertStringNotContainsString('City/town', $details($rows[$this->ue['dan']]));
        // The cell is the table's own: the same masking the server table renders for the row.
        $table = applications::for_scope(0);
        ob_start();
        $table->out(50, false);
        ob_end_clean();
        foreach ($table->rawdata as $record) {
            $this->assertSame(
                $table->details_html($record),
                $details($rows[(int) $record->userenrolmentid]),
                (string) $record->userenrolmentid
            );
        }
    }

    /**
     * Each facet's count is what choosing that option lists, under every other criterion in force,
     * the course, category, method and grouped facets included; and the method facet appears only
     * once a course holding two methods' applications is chosen.
     *
     * Changes that must make it fail: a facet counted under its own value; a category not counted over
     * its subtree; the grouped tally reading "has a group" differently from the filter.
     *
     * @return void
     */
    public function test_each_wide_facet_count_is_what_choosing_it_lists(): void {
        $this->seed();
        $this->setUser($this->decider);
        $alpha = (string) $this->courses['alpha']->id;

        $backgrounds = [
            [],
            ['course' => $alpha],
            ['course' => (string) $this->courses['beta']->id],
            ['waited' => '3'],
            ['grouped' => 'no'],
            ['category' => (string) $this->categories['top']->id],
            ['course' => $alpha, 'method' => (string) $this->methods['evening']->id],
        ];
        foreach ($backgrounds as $background) {
            foreach (['all', 'pending'] as $scope) {
                $response = $this->rows(['scope' => $scope, 'filters' => $background]);
                foreach ($response['facets'] as $facet) {
                    $this->assertSame(0, $facet['omitted'], $facet['name']);
                    foreach ($facet['options'] as $option) {
                        $chosen = $this->rows([
                            'scope' => $scope,
                            'filters' => [$facet['name'] => $option['value']] + $background,
                        ]);
                        $this->assertSame(
                            $chosen['total'],
                            $option['count'],
                            "{$scope} {$facet['name']}={$option['value']} under " . json_encode($background)
                        );
                    }
                }
            }
        }

        // The controls: the options and their order.
        $facets = array_column($this->rows()['facets'], null, 'name');
        $this->assertSame(
            [$alpha => 4, (string) $this->courses['beta']->id => 3],
            array_column($facets['course']['options'], 'count', 'value')
        );
        $this->assertSame(['Alpha course', 'Beta course'], array_column($facets['course']['options'], 'label'));
        $this->assertSame(
            [
                (string) $this->categories['top']->id => 4,
                (string) $this->categories['sub']->id => 4,
                (string) $this->categories['other']->id => 3,
            ],
            array_column($facets['category']['options'], 'count', 'value')
        );
        $this->assertSame(['Top', 'Top / Sub', 'Other'], array_column($facets['category']['options'], 'label'));
        $this->assertSame(['yes' => 1, 'no' => 6], array_column($facets['grouped']['options'], 'count', 'value'));
        $this->assertArrayNotHasKey('method', $facets);

        $facets = array_column($this->rows(['filters' => ['course' => $alpha]])['facets'], null, 'name');
        $this->assertSame(
            ['Evening intake' => 2, 'Morning intake' => 2],
            array_column($facets['method']['options'], 'count', 'label')
        );
        $this->assertSame($alpha, $facets['course']['applied']);
        // One method's course offers no method facet.
        $beta = ['course' => (string) $this->courses['beta']->id];
        $this->assertArrayNotHasKey('method', array_column($this->rows(['filters' => $beta])['facets'], null, 'name'));
    }

    /**
     * The course and category facets send at most FACET_LIMIT options, most applications first, say
     * how many they left out, and keep the applied one on offer.
     *
     * The limit is narrowed to one through a subclass, as the engine reads it (static::).
     *
     * Changes that must make it fail: the course or category options not cut; the left-out count not
     * given; the applied option dropped by the cut.
     *
     * @return void
     */
    public function test_the_course_and_category_facets_are_capped_and_say_what_they_left_out(): void {
        $this->seed();
        $this->setUser($this->decider);
        $engine = new class extends queueengine {
            /** @var int One option, for the test. */
            public const FACET_LIMIT = 1;
        };
        $alpha = (string) $this->courses['alpha']->id;
        $beta = (string) $this->courses['beta']->id;

        $facets = array_column($engine::rows(0, [])['facets'], null, 'name');
        $this->assertSame([$alpha], array_column($facets['course']['options'], 'value'));
        $this->assertSame(1, $facets['course']['omitted']);
        $this->assertSame([(string) $this->categories['top']->id], array_column($facets['category']['options'], 'value'));
        $this->assertSame(2, $facets['category']['omitted']);

        // The applied course stays on offer past the cut, and is no longer counted as left out.
        $facets = array_column($engine::rows(0, ['filters' => ['course' => $beta]])['facets'], null, 'name');
        $this->assertSame([$alpha => 4, $beta => 3], array_column($facets['course']['options'], 'count', 'value'));
        $this->assertSame(0, $facets['course']['omitted']);

        // The control: the engine's own limit sends them all.
        $facets = array_column(queueengine::rows(0, [])['facets'], null, 'name');
        $this->assertCount(2, $facets['course']['options']);
        $this->assertSame(0, $facets['course']['omitted']);
    }

    /**
     * The summary: a method's answer is today's with four neutral fields after it; the wide answer
     * gives the site-wide readers the site figures and a mentor the four queue figures alone.
     *
     * Changes that must make it fail: the method's answer changed; the site figures given to a
     * mentor; methods at their limit read off anything but capacity::states()' flags.
     *
     * @return void
     */
    public function test_the_summary_of_a_method_is_unchanged_and_the_wide_one_follows_the_reader(): void {
        $this->seed();
        $this->setUser($this->decider);

        $this->assertSame([
            'places' => 1,
            'placestaken' => 1,
            'applicantlimit' => 0,
            'applicants' => 0,
            'awaiting' => 2,
            'deferred' => 1,
            'oldestwait' => 20 * DAYSECS,
            'late' => 2,
            'latedays' => 7,
            'open' => true,
            'closes' => 0,
            'narrowed' => false,
            'notingroup' => false,
            'sitefigures' => false,
            'courses' => 0,
            'methods' => 0,
            'methodsfull' => 0,
        ], queueengine::summary($this->methods['morning']));

        $summary = queueengine::summary(null);
        $this->assertSame(7, $summary['awaiting']);
        $this->assertSame(1, $summary['deferred']);
        $this->assertSame(3, $summary['late']);
        $this->assertSame(20 * DAYSECS, $summary['oldestwait']);
        $this->assertTrue($summary['sitefigures']);
        $this->assertSame(2, $summary['courses']);
        $this->assertSame(3, $summary['methods']);
        $this->assertSame(1, $summary['methodsfull']);
        $this->assertSame(0, $summary['places']);
        $this->assertFalse($summary['open']);

        $this->setUser($this->viewer);
        $this->assertSame(1, queueengine::summary(null)['methodsfull']);

        $this->setUser($this->mentor);
        $summary = queueengine::summary(null);
        $this->assertSame(3, $summary['awaiting']);
        $this->assertSame(0, $summary['deferred']);
        $this->assertSame(2, $summary['late']);
        $this->assertFalse($summary['sitefigures']);
        $this->assertSame(0, $summary['courses']);
        $this->assertSame(0, $summary['methods']);
        $this->assertSame(0, $summary['methodsfull']);
    }

    /**
     * The wide queues keep their own grid: a choice made there leaves the method queues' alone, and
     * the reverse, each intersected with the catalogue of the queue it is read for.
     *
     * Changes that must make it fail: the wide queue reading or writing the method queues' preference.
     *
     * @return void
     */
    public function test_the_wide_queue_keeps_a_grid_of_its_own(): void {
        $this->seed();
        $this->setUser($this->decider);
        $active = static fn(array $response): array => array_values(array_column(
            array_filter($response['columns'], static fn(array $column): bool => $column['active']),
            'key'
        ));

        queuepreference::write(['course', 'details', 'answer:s_city'], [], null, true);
        $this->assertSame(['applicant', 'course', 'details'], $active($this->rows()));
        $this->assertNull(get_user_preferences(queuepreference::NAME));
        $morning = queueengine::rows((int) $this->methods['morning']->id, []);
        $this->assertTrue($morning['preference']['usedefault']);

        queuepreference::write(['comment'], [], null, false);
        $this->assertSame(['applicant', 'course', 'details'], $active($this->rows()));
        $this->assertSame(['applicant', 'comment'], $active(queueengine::rows((int) $this->methods['morning']->id, [])));

        // The sets of the wide queue.
        queuepreference::write(queueengine::WIDE_PRESETS['glance'], [], null, true);
        $response = $this->rows();
        $this->assertSame(['applicant', 'course', 'wait', 'group'], $active($response));
        $this->assertSame(['everything' => false, 'glance' => true], array_column($response['presets'], 'active', 'name'));
    }

    /**
     * A page of the wide queue is read in a number of statements that does not grow with the rows of
     * one course, and grows by the same amount for each further distinct course.
     *
     * By reading the code, a further course of the page costs about five statements on the second,
     * warm, call: get_course() for the profile links, the profile links' "parent" read, the
     * reader's course-access read and the page's is_enrolled read (profilelink::viewable_ids()), and
     * the method's deciding role (decidingrole::roles()). The test holds the shape, not that estimate:
     * the same count for one row and four rows of one course, the same slope from one course to two
     * and from two to three, and that slope at most PER_COURSE.
     *
     * Changes that must make it fail: a read per row (answers, profile links, capacity, course names);
     * a read per facet option.
     *
     * @return void
     */
    public function test_a_wide_page_grows_with_its_courses_not_its_rows(): void {
        $this->setUser($this->viewer);
        $measure = function (): int {
            global $DB;

            queueengine::rows(0, []);
            $before = $DB->perf_get_reads();
            $response = queueengine::rows(0, []);
            $reads = $DB->perf_get_reads() - $before;
            $this->assertNotEmpty($response['rows']);

            return $reads;
        };
        $course = function (string $name): string {
            $course = $this->getDataGenerator()->create_course([
                'fullname' => $name,
                'category' => $this->categories['other']->id,
            ]);
            $this->methods[$name] = $this->method($course, $name, ['customint3' => 50, 'customint4' => 10]);

            return $name;
        };

        $first = $course('First');
        $this->applicant($first, 'One', 'Lille', DAYSECS);
        $one = $measure();
        foreach (['Two', 'Three', 'Four'] as $name) {
            $this->applicant($first, $name, 'Lille', DAYSECS);
        }
        $this->assertSame($one, $measure(), 'four rows of one course');

        $this->applicant($course('Second'), 'Five', 'Lille', DAYSECS);
        $two = $measure();
        $this->applicant($course('Third'), 'Six', 'Lille', DAYSECS);
        $three = $measure();

        $slope = $two - $one;
        $this->assertSame($slope, $three - $two, "one {$one}, two {$two}, three {$three}");
        $this->assertGreaterThan(0, $slope);
        $this->assertLessThanOrEqual(self::PER_COURSE, $slope, "one {$one}, two {$two}, three {$three}");
    }

    /**
     * The group filter and its facet are offered to a site-wide reader who may see hidden groups, and to
     * nobody else: for a clerk without moodle/course:viewhiddengroups a posted `grouped` is ignored, no
     * facet is returned and the hidden group's name is nowhere in the answer, through the engine and
     * through the service.
     *
     * The control: the site-wide decider, who holds the capability, gets the facet, `grouped=yes`
     * narrows to the one application, and the answer carries the group's name in its cell.
     *
     * Changes that must make it fail: the filter or its facet offered without the capability (QW23).
     *
     * @return void
     */
    public function test_the_group_filter_is_offered_only_where_hidden_groups_are_visible(): void {
        $generator = $this->getDataGenerator();
        $hidden = $generator->create_group([
            'courseid' => $this->courses['beta']->id,
            'name' => 'Secret cell',
            'visibility' => GROUPS_VISIBILITY_NONE,
        ]);
        $grouped = $this->applicant('beta', 'Hal', 'Nice', HOURSECS, ENROL_USER_SUSPENDED, (int) $hidden->id);
        $plain = $this->applicant('beta', 'Ivy', 'Pau', 2 * HOURSECS);

        $role = $generator->create_role(['shortname' => 'wideclerk']);
        $system = \context_system::instance();
        foreach (['enrol/apply:manageapplications', 'moodle/site:viewuseridentity'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $role, $system->id, true);
        }
        $clerk = $generator->create_user();
        role_assign($role, $clerk->id, $system->id);
        $this->assertFalse(has_capability('moodle/course:viewhiddengroups', $system, $clerk));

        $filters = [['name' => 'grouped', 'value' => 'yes']];
        $read = function (array $sent) {
            $_POST['sesskey'] = sesskey();

            return \core_external\external_api::call_external_function(
                'enrol_apply_queue_rows',
                ['enrolid' => 0, 'filters' => $sent],
                true
            );
        };

        $this->setUser($clerk);
        $this->assertFalse(applications::for_scope(0, '', null, ['grouped' => 'yes'])->offers_grouped_filter());
        $engine = $this->rows(['filters' => ['grouped' => 'yes']]);
        $service = $read($filters);
        $this->assertFalse($service['error']);
        $this->assertSame([$grouped, $plain], $this->engine_ids());
        $this->assertSame([$grouped, $plain], array_column($engine['rows'], 'userenrolmentid'));
        $this->assertEqualsCanonicalizing([$grouped, $plain], array_column($service['data']['rows'], 'userenrolmentid'));
        $this->assertNotContains('grouped', array_column($engine['facets'], 'name'));
        $this->assertNotContains('grouped', array_column($service['data']['facets'], 'name'));
        $this->assertStringNotContainsString('Secret cell', json_encode($engine));
        $this->assertStringNotContainsString('Secret cell', json_encode($service['data']));

        // The control: the holder of the capability gets the facet and the narrowing.
        $this->setUser($this->decider);
        $this->assertTrue(applications::for_scope(0, '', null, [])->offers_grouped_filter());
        $this->assertSame([$grouped], $this->engine_ids(['filters' => ['grouped' => 'yes']]));
        $response = $this->rows();
        $facets = array_column($response['facets'], null, 'name');
        $this->assertSame(['yes' => 1, 'no' => 1], array_column($facets['grouped']['options'], 'count', 'value'));
        $this->assertStringContainsString('Secret cell', json_encode($response));
        $service = $read($filters);
        $this->assertSame([$grouped], array_column($service['data']['rows'], 'userenrolmentid'));
    }
}
