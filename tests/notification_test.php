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
}
