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
 * Tests for how the renderer spells the names it hands to its templates.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/lib.php');
require_once($CFG->dirroot . '/enrol/apply/manage_table.php');

/**
 * Tests for how the renderer spells the names it hands to its templates.
 *
 * Both templates render these names through a double stash, so the renderer owes them the
 * PLAIN spelling. format_string()'s escape flag defaults to true, which is why every one of
 * these calls has to say so explicitly, and why the wrong spelling is invisible to phpcs, to
 * the mustache lint and to every other gate: nothing in the pipeline knows which stash a
 * value lands in.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\enrol_apply_renderer::class)]
final class renderer_test extends \advanced_testcase {
    /**
     * A name carrying both characters format_string() rewrites.
     *
     * The "<" has a space after it on purpose. strip_tags() runs first whatever the escape
     * flag says, so "<b>" would be removed identically in both spellings and would prove
     * nothing; a "<" that is not the start of a tag survives to be escaped, and the bare "&"
     * is rewritten by replace_ampersands_not_followed_by_entity().
     */
    private const AWKWARD_NAME = 'R&D < Team';

    /** @var string The awkward name escaped exactly once, which is what a reader should get. */
    private const ESCAPED_ONCE = 'R&amp;D &lt; Team';

    /** @var string The awkward name escaped twice, which is what a reader must never get. */
    private const ESCAPED_TWICE = 'R&amp;amp;D &amp;lt; Team';

    /** @var \stdClass Course the apply instance belongs to. */
    private $course;

    /** @var \stdClass The enrol_apply instance record. */
    private $instance;

    /** @var \enrol_apply_plugin The plugin under test. */
    private $plugin;

    /**
     * Create a course carrying a single enabled apply enrolment instance.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->plugin = enrol_get_plugin('apply');
        $this->course = $this->getDataGenerator()->create_course();
        $instanceid = $this->plugin->add_instance($this->course, $this->plugin->get_instance_defaults());
        $this->instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
    }

    /**
     * A group name reaches the chooser escaped exactly once.
     *
     * Mutation check: drop the 'escape' => false option from the group name in
     * enrol_apply_renderer::manage_form() and exactly this test goes red.
     *
     * @return void
     */
    public function test_a_group_name_reaches_the_chooser_escaped_once(): void {
        global $DB, $PAGE;

        $this->setAdminUser();
        $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => self::AWKWARD_NAME]);

        // The chooser is inside the hasrows block, so the queue needs an application in it.
        $applicant = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($this->instance, $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);
        $ueid = (int) $DB->get_field(
            'user_enrolments',
            'id',
            ['userid' => $applicant->id, 'enrolid' => $this->instance->id],
            MUST_EXIST
        );
        $DB->insert_record('enrol_apply_applicationinfo', (object) ['userenrolmentid' => $ueid, 'comment' => '']);

        $url = new \moodle_url('/enrol/apply/manage.php', ['id' => $this->instance->id]);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($this->course->id));

        $table = new \enrol_apply_manage_table($this->instance->id);
        $table->define_baseurl($url);

        $html = $PAGE->get_renderer('enrol_apply')->manage_form($table, $url, $this->instance);

        /* Scoped to the chooser rather than matched against the whole page. The applicant's
           own name and the course name are in the same markup, so an unscoped assertion would
           pass on a match somewhere else entirely. */
        $this->assertMatchesRegularExpression('~<select[^>]*name="groups\[\]".*?</select>~s', $html);
        preg_match('~<select[^>]*name="groups\[\]".*?</select>~s', $html, $matches);
        $chooser = $matches[0];

        $this->assertStringContainsString(self::ESCAPED_ONCE, $chooser);
        $this->assertStringNotContainsString(self::ESCAPED_TWICE, $chooser);
    }

    /**
     * The course name reaches the new-application notification escaped exactly once.
     *
     * Mutation check: drop the 'escape' => false option from the course name in
     * enrol_apply_renderer::application_notification_mail_body() and exactly this test goes
     * red. The template's own docblock has claimed since it was written that "every label and
     * value arrives in its PLAIN spelling"; for this one value that was not true.
     *
     * @return void
     */
    public function test_the_notified_course_name_is_escaped_once(): void {
        global $PAGE;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['fullname' => self::AWKWARD_NAME]);
        $applicant = $this->getDataGenerator()->create_user();
        $PAGE->set_context(\context_course::instance($course->id));

        $body = $PAGE->get_renderer('enrol_apply')->application_notification_mail_body(
            $course,
            $applicant,
            new \moodle_url('/enrol/apply/manage.php'),
            'Please let me in'
        );

        $this->assertStringContainsString(self::ESCAPED_ONCE, $body);
        $this->assertStringNotContainsString(self::ESCAPED_TWICE, $body);
    }
}
