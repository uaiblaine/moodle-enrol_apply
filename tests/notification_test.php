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
 * Tests for the notifications this plugin sends.
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
require_once($CFG->dirroot . '/enrol/apply/notification.php');

/**
 * Tests for the notifications this plugin sends.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_notification::class)]
#[CoversClass(\enrol_apply_plugin::class)]
#[CoversClass(\enrol_apply\local\notifyrecipients::class)]
final class notification_test extends \advanced_testcase {
    /** @var \stdClass Course the apply instance belongs to. */
    private $course;

    /** @var \stdClass The enrol_apply instance record. */
    private $instance;

    /** @var \enrol_apply_plugin The plugin instance. */
    private $plugin;

    /**
     * Create a course carrying a single enabled apply enrolment instance.
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
        $this->course = $this->getDataGenerator()->create_course();
        $instanceid = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        // The new-application mail body is rendered through $PAGE, whose url must be set.
        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php'));
    }

    /**
     * Every notification type, with the page its link opens and the label that link must carry.
     *
     * @return array Type, lang string id and component of the label, and the linked path.
     */
    public static function type_provider(): array {
        return [
            'new application, for the deciders' => ['application', 'applymanage', 'enrol_apply', '/enrol/apply/manage.php'],
            'approval, for the applicant' => ['confirmation', 'course', 'moodle', '/course/view.php'],
            'cancellation, for the applicant' => ['cancelation', 'course', 'moodle', '/course/view.php'],
            'deferral, for the applicant' => ['waitinglist', 'course', 'moodle', '/course/view.php'],
        ];
    }

    /**
     * The link's label names the page the link opens, for every type.
     *
     * @param string $type Notification type.
     * @param string $stringid Lang string id of the expected label.
     * @param string $component Component of the expected label.
     * @param string $path Path of the page the caller links to for this type.
     * @return void
     */
    #[DataProvider('type_provider')]
    public function test_the_link_label_names_what_the_link_opens(
        string $type,
        string $stringid,
        string $component,
        string $path
    ): void {
        $to = $this->getDataGenerator()->create_user();
        $from = $this->getDataGenerator()->create_user();
        $url = new \moodle_url($path, ['id' => $this->course->id]);

        $message = new \enrol_apply_notification($to, $from, $type, 'Subject', '<p>Body</p>', $url, $this->course->id);

        $this->assertSame($type, $message->name);
        $this->assertSame($url, $message->contexturl);
        $this->assertSame(get_string($stringid, $component), $message->contexturlname);
    }

    /**
     * A type the plugin does not send is refused rather than delivered unlabelled.
     *
     * @return void
     */
    public function test_an_unknown_type_is_refused(): void {
        $user = $this->getDataGenerator()->create_user();

        $this->expectException(\invalid_parameter_exception::class);
        new \enrol_apply_notification($user, $user, 'reminder', 'Subject', '', new \moodle_url('/'), $this->course->id);
    }

    /**
     * The new-application notice a decider receives names the approval queue its link opens.
     *
     * Driven through submit_application(), so the url lib.php pairs with the label is the real
     * one: manage.php, never the course.
     *
     * @return void
     */
    public function test_a_new_application_notice_names_the_queue_it_links_to(): void {
        /* A recipient of the site-wide notice. get_notifyglobal_users() leaves site
           administrators out unless a role gives them the capability, so this is a role holder. */
        $system = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $roleid, $system->id);
        $decider = $this->getDataGenerator()->create_user();
        role_assign($roleid, $decider->id, $system->id);
        // The plugin object memoises its config, so the setting is written through it.
        $this->plugin->set_config('notifyglobal', (string) $decider->id);

        $applicant = $this->getDataGenerator()->create_user();
        $this->setUser($applicant);

        $sink = $this->redirectMessages();
        $result = $this->plugin->submit_application($this->instance, $applicant->id, (object) ['applydescription' => '']);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertTrue($result->was_created());
        $notices = array_values(array_filter(
            $messages,
            static fn($message) => $message->eventtype === 'application' && (int) $message->useridto === (int) $decider->id
        ));
        $this->assertCount(1, $notices);
        $this->assertStringContainsString('/enrol/apply/manage.php', $notices[0]->contexturl);
        $this->assertSame(get_string('applymanage', 'enrol_apply'), $notices[0]->contexturlname);
    }

    /**
     * The deferral notice an applicant receives names the course its link opens.
     *
     * @return void
     */
    public function test_a_deferral_notice_names_the_course_it_links_to(): void {
        global $DB;

        $applicant = $this->getDataGenerator()->create_user();
        $this->setUser($applicant);
        $sink = $this->redirectMessages();
        $this->plugin->submit_application($this->instance, $applicant->id, (object) ['applydescription' => '']);
        $sink->close();
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );

        $this->setAdminUser();
        $sink = $this->redirectMessages();
        $this->assertSame(1, $this->plugin->wait_enrolment([$ueid]));
        $messages = $sink->get_messages();
        $sink->close();

        $notices = array_values(array_filter(
            $messages,
            static fn($message) => $message->eventtype === 'waitinglist' && (int) $message->useridto === (int) $applicant->id
        ));
        $this->assertCount(1, $notices);
        $this->assertStringContainsString('/course/view.php', $notices[0]->contexturl);
        $this->assertSame(get_string('course'), $notices[0]->contexturlname);
    }

    /**
     * A course with three groups and the course deciders "the deciders of the application's group" chooses among.
     *
     * Group a has a moderator, group b has none, group c has its own moderator. A moderator is a
     * non-editing teacher given enrol/apply:manageapplications, so in separate groups they are
     * narrowed to their group; the editing teacher holds moodle/site:accessallgroups and sees every
     * group.
     *
     * @param int $groupmode The course's group mode.
     * @param string $notify The method's notification setting; '{editing}' and '{moderatorc}' in it
     *     are replaced by those users' ids.
     * @return \stdClass course, instance, groups a, b and c, moderatora, moderatorc and editing.
     */
    private function groups_fixture(int $groupmode = SEPARATEGROUPS, string $notify = '$@GROUP@$'): \stdClass {
        global $DB;

        $teacher = (int) $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('enrol/apply:manageapplications', CAP_ALLOW, $teacher, \context_system::instance()->id, true);

        $generator = $this->getDataGenerator();
        $fixture = new \stdClass();
        $fixture->course = $generator->create_course(['groupmode' => $groupmode]);
        $id = $this->plugin->add_instance($fixture->course, $this->plugin->get_instance_defaults());
        foreach (['a', 'b', 'c'] as $name) {
            $fixture->{$name} = $generator->create_group(['courseid' => $fixture->course->id, 'name' => "Group {$name}"]);
        }
        $fixture->moderatora = $generator->create_and_enrol($fixture->course, 'teacher');
        $generator->create_group_member(['groupid' => $fixture->a->id, 'userid' => $fixture->moderatora->id]);
        $fixture->moderatorc = $generator->create_and_enrol($fixture->course, 'teacher');
        $generator->create_group_member(['groupid' => $fixture->c->id, 'userid' => $fixture->moderatorc->id]);
        $fixture->editing = $generator->create_and_enrol($fixture->course, 'editingteacher');

        $DB->set_field('enrol', 'customtext3', $this->setting($fixture, $notify), ['id' => $id]);
        $fixture->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        return $fixture;
    }

    /**
     * A notification setting with the fixture's user ids in place of their names.
     *
     * @param \stdClass $fixture The fixture of {@see groups_fixture()}.
     * @param string $notify The setting, naming '{editing}' and '{moderatorc}'.
     * @return string The setting to store.
     */
    private function setting(\stdClass $fixture, string $notify): string {
        return strtr($notify, [
            '{editing}' => (string) $fixture->editing->id,
            '{moderatorc}' => (string) $fixture->moderatorc->id,
        ]);
    }

    /**
     * Store another notification setting on the fixture's method.
     *
     * @param \stdClass $fixture The fixture of {@see groups_fixture()}.
     * @param string $notify The setting, as {@see setting()} reads it.
     * @return void
     */
    private function set_notify(\stdClass $fixture, string $notify): void {
        global $DB;

        $DB->set_field('enrol', 'customtext3', $this->setting($fixture, $notify), ['id' => $fixture->instance->id]);
        $fixture->instance = $DB->get_record('enrol', ['id' => $fixture->instance->id], '*', MUST_EXIST);
    }

    /**
     * Write an application through the plugin's own apply(), as the administrator, and say who was told.
     *
     * @param \stdClass $fixture The fixture of {@see groups_fixture()}.
     * @param int $groupid The application's group, 0 for none.
     * @param string $route 'key' for a key that sent it for review, 'chosen' for a chosen group,
     *     'admitted' for an admission by key.
     * @return array Ids of the users told of it, ascending.
     */
    private function told(\stdClass $fixture, int $groupid, string $route = 'key'): array {
        global $USER;

        $submission = \enrol_apply\local\submission::class;
        $previous = $USER;
        $this->setAdminUser();
        $applicant = $this->getDataGenerator()->create_user();
        $keyed = $groupid > 0 && $route !== 'chosen';
        $outcome = match (true) {
            !$keyed => $submission::KEY_NONE,
            $route === 'admitted' => $submission::KEY_ADMITTED,
            default => $submission::KEY_FOR_REVIEW,
        };

        $sink = $this->redirectMessages();
        (new \ReflectionMethod(\enrol_apply_plugin::class, 'apply'))->invoke(
            $this->plugin,
            $fixture->instance,
            (int) $applicant->id,
            (object) ['applydescription' => ''],
            $keyed ? $groupid : 0,
            $outcome,
            $route === 'chosen' ? $groupid : 0
        );
        $messages = $sink->get_messages();
        $sink->close();
        $this->setUser($previous);

        $told = [];
        foreach ($messages as $message) {
            if (in_array($message->eventtype, ['application', 'admission'], true)) {
                $told[] = (int) $message->useridto;
            }
        }
        sort($told);

        return array_values(array_unique($told));
    }

    /**
     * The two ways an application has a group.
     *
     * @return array The route {@see told()} takes, keyed by a readable name.
     */
    public static function group_route_provider(): array {
        return [
            'a key sent it for review' => ['key'],
            'the applicant chose the group' => ['chosen'],
        ];
    }

    /**
     * Under "the deciders of the application's group" only the group's moderator is told; the
     * editing teacher, who sees every group, is told under "everyone" and not here.
     *
     * Changes that must make it fail: the group deciders not preferred, the fallback added to
     * them, or the application's group not handed to the recipients.
     *
     * @param string $route How the application has its group.
     * @return void
     */
    #[DataProvider('group_route_provider')]
    public function test_the_group_marker_tells_only_the_deciders_of_the_applications_group(string $route): void {
        $fixture = $this->groups_fixture();

        $told = $this->told($fixture, (int) $fixture->a->id, $route);
        $this->assertSame([(int) $fixture->moderatora->id], $told);
        $this->assertNotContains((int) $fixture->editing->id, $told);

        // The control: under "everyone" the editing teacher is told of the same application.
        $this->set_notify($fixture, '$@ALL@$');
        $told = $this->told($fixture, (int) $fixture->a->id, $route);
        $this->assertContains((int) $fixture->editing->id, $told);
        $this->assertContains((int) $fixture->moderatora->id, $told);
    }

    /**
     * Every case with no decider of the application's group, and what makes it one.
     *
     * @return array Group mode, the application's group ('a', 'b' or '' for none) and whether the
     *     method is decided only by those who see every group; keyed by a readable name.
     */
    public static function fallback_provider(): array {
        return [
            'an application without a group' => [SEPARATEGROUPS, '', false],
            'a group no narrowed decider holds' => [SEPARATEGROUPS, 'b', false],
            'a method decided only by who sees every group' => [SEPARATEGROUPS, 'a', true],
            'a course not in separate groups' => [VISIBLEGROUPS, 'a', false],
        ];
    }

    /**
     * Without a decider of the application's group, "the deciders of the application's group" tells
     * exactly who "everyone" would, and that is never nobody.
     *
     * Changes that must make it fail: the fallback dropped, or the group deciders judged without
     * the method's "Who decides".
     *
     * @param int $groupmode The course's group mode.
     * @param string $group The application's group, '' for none.
     * @param bool $allgroups Whether only those who see every group decide the method.
     * @return void
     */
    #[DataProvider('fallback_provider')]
    public function test_without_a_group_decider_the_group_marker_tells_who_everyone_would(
        int $groupmode,
        string $group,
        bool $allgroups
    ): void {
        $fixture = $this->groups_fixture($groupmode);
        if ($allgroups) {
            $options = \enrol_apply\local\instanceoptions::class;
            $options::set((int) $fixture->instance->id, ['deciders' => $options::DECIDERS_ALLGROUPS]);
        }
        $groupid = $group === '' ? 0 : (int) $fixture->{$group}->id;

        $told = $this->told($fixture, $groupid);
        $this->set_notify($fixture, '$@ALL@$');
        $everyone = $this->told($fixture, $groupid);

        $this->assertContains((int) $fixture->editing->id, $everyone, 'the premise: everyone tells the editing teacher');
        $this->assertSame($everyone, $told);
    }

    /**
     * People picked by name beside the group marker are told of every application they may see,
     * and narrowed like any recipient: a moderator picked by name hears only of their own group.
     *
     * Changes that must make it fail: the people picked by name dropped beside the marker, or the
     * per-recipient narrowing dropped.
     *
     * @return void
     */
    public function test_people_picked_by_name_are_told_beside_the_group_deciders(): void {
        $fixture = $this->groups_fixture(SEPARATEGROUPS, '$@GROUP@$,{editing},{moderatorc}');

        $this->assertSame(
            $this->sorted([(int) $fixture->moderatora->id, (int) $fixture->editing->id]),
            $this->told($fixture, (int) $fixture->a->id)
        );

        // The control: the moderator picked by name is told of their own group's application.
        $this->assertSame(
            $this->sorted([(int) $fixture->moderatorc->id, (int) $fixture->editing->id]),
            $this->told($fixture, (int) $fixture->c->id)
        );
    }

    /**
     * The informational notice of an admission by key reaches the recipients an application of the
     * same group would.
     *
     * @return void
     */
    public function test_an_admission_by_key_is_announced_to_the_deciders_of_its_group(): void {
        $fixture = $this->groups_fixture();

        $this->assertSame([(int) $fixture->moderatora->id], $this->told($fixture, (int) $fixture->a->id, 'admitted'));

        // The control: under "everyone" the editing teacher is told of the admission too.
        $this->set_notify($fixture, '$@ALL@$');
        $this->assertContains((int) $fixture->editing->id, $this->told($fixture, (int) $fixture->a->id, 'admitted'));
    }

    /**
     * Whatever the application and the course, the group marker tells a subset of whom "everyone"
     * tells, and somebody whenever "everyone" tells somebody.
     *
     * The course-level recipients are compared before the per-recipient filter, and the people
     * told after it, with and without a person picked by name.
     *
     * @return void
     */
    public function test_the_group_marker_never_widens_what_everyone_reaches(): void {
        foreach ([SEPARATEGROUPS, VISIBLEGROUPS] as $groupmode) {
            $fixture = $this->groups_fixture($groupmode);
            foreach (['', 'a', 'b', 'c'] as $group) {
                $groupid = $group === '' ? 0 : (int) $fixture->{$group}->id;
                $case = "group mode {$groupmode}, group '{$group}'";

                $this->set_notify($fixture, '$@ALL@$');
                $candidates = array_keys($this->plugin->get_notifycoursebased_users($fixture->instance, $groupid));
                $everyone = $this->told($fixture, $groupid);

                foreach (['$@GROUP@$', '$@GROUP@$,{moderatorc}'] as $notify) {
                    $this->set_notify($fixture, $notify);
                    $chosen = array_keys($this->plugin->get_notifycoursebased_users($fixture->instance, $groupid));
                    $told = $this->told($fixture, $groupid);

                    $this->assertSame([], array_diff($chosen, $candidates), "{$case}, {$notify}: course level");
                    $this->assertSame([], array_diff($told, $everyone), "{$case}, {$notify}: told");
                    $this->assertNotEmpty($told, "{$case}, {$notify}: everyone tells somebody");
                }
            }
        }
    }

    /**
     * The stored value is read strictly: "everyone" only as the whole value, the group marker only
     * first, and anything that is not an id matches nobody.
     *
     * Changes that must make it fail: the group marker read anywhere in the list.
     *
     * @return void
     */
    public function test_the_stored_value_is_read_strictly(): void {
        $recipients = \enrol_apply\local\notifyrecipients::class;
        $fixture = $this->groups_fixture();
        $editing = (int) $fixture->editing->id;

        $parsed = $recipients::parse('$@GROUP@$,12,abc,,0,012, 13');
        $this->assertTrue($parsed->group);
        $this->assertFalse($parsed->everyone);
        $this->assertSame([12], $parsed->ids);
        $this->assertTrue($recipients::parse('$@ALL@$')->everyone);
        $this->assertFalse($recipients::parse('$@ALL@$,12')->everyone);
        $this->assertSame([12], $recipients::parse('$@ALL@$,12')->ids);

        // A marker out of place, or an unknown one, names nobody: only the editing teacher is told.
        foreach (["{$editing},\$@GROUP@\$", "\$@FOO@\$,{$editing}", " \$@GROUP@\$,{$editing}"] as $value) {
            $this->set_notify($fixture, $value);
            $this->assertSame([$editing], array_keys($this->plugin->get_notifycoursebased_users(
                $fixture->instance,
                (int) $fixture->a->id
            )), $value);
        }
        $this->set_notify($fixture, '$@FOO@$');
        $this->assertSame([], $this->plugin->get_notifycoursebased_users($fixture->instance, (int) $fixture->a->id));

        // The control: led by the marker, the group's moderator is chosen too.
        $this->set_notify($fixture, "\$@GROUP@\$,{$editing}");
        $this->assertSame(
            $this->sorted([(int) $fixture->moderatora->id, $editing]),
            $this->sorted(array_keys($this->plugin->get_notifycoursebased_users($fixture->instance, (int) $fixture->a->id)))
        );
    }

    /**
     * Ids in ascending order.
     *
     * @param array $ids User ids.
     * @return array The same ids as integers, ascending.
     */
    private function sorted(array $ids): array {
        $ids = array_map('intval', $ids);
        sort($ids);

        return $ids;
    }
}
