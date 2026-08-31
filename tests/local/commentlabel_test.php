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
 * Tests for the instance comment label and the spelling each sink needs.
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
require_once($CFG->dirroot . '/enrol/apply/manage_table.php');

/**
 * Tests for the instance comment label and the spelling each sink needs.
 *
 * The fixture label carries a bare ampersand rather than tag-shaped input, deliberately. With the
 * shipped formatstringstriptags, format_string() strips a tag identically in both escape modes, so
 * "<b>x</b>" cannot tell the two spellings apart and would make every assertion below vacuous. An
 * ampersand differs: escaped it is an entity, plain it is the character.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(commentlabel::class)]
final class commentlabel_test extends \advanced_testcase {
    /** @var string A label whose two spellings differ. */
    protected const LABEL = 'Why you & who referred you';

    /**
     * Build an apply instance, optionally carrying a custom label.
     *
     * @param string|null $label Value for customtext2, null to leave the default.
     * @return \stdClass The enrol instance record.
     */
    protected function instance(?string $label = null): \stdClass {
        global $DB;

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $plugin = enrol_get_plugin('apply');
        $course = $this->getDataGenerator()->create_course();
        $instanceid = $plugin->add_instance($course, $plugin->get_instance_defaults());

        if ($label !== null) {
            $DB->set_field('enrol', 'customtext2', $label, ['id' => $instanceid]);
        }

        return $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
    }

    /**
     * Put one application awaiting a decision on the instance.
     *
     * The header tests need it: with no rows, flexible_table renders "Nothing to display" and no
     * <thead> at all, so an assertion about the heading would be satisfied by a table that never
     * drew one.
     *
     * @param \stdClass $instance The enrol instance to apply to.
     * @return void
     */
    protected function seed_application(\stdClass $instance): void {
        $plugin = enrol_get_plugin('apply');
        $user = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($instance, $user->id, null, 0, 0, ENROL_USER_SUSPENDED);
    }

    /**
     * With nothing set, the shipped wording is used.
     *
     * @return void
     */
    public function test_an_instance_with_no_label_uses_the_shipped_wording(): void {
        $this->resetAfterTest();

        $expected = get_string('applycomment', 'enrol_apply');

        $this->assertSame($expected, commentlabel::custom($this->instance()));
        $this->assertSame($expected, commentlabel::custom($this->instance(''), false));
    }

    /**
     * The two spellings really are different, which is what every other test here depends on.
     *
     * @return void
     */
    public function test_the_escaped_and_plain_spellings_differ(): void {
        $this->resetAfterTest();

        $instance = $this->instance(self::LABEL);

        $escaped = commentlabel::custom($instance);
        $plain = commentlabel::custom($instance, false);

        $this->assertStringContainsString('&amp;', $escaped);
        $this->assertStringContainsString(' & ', $plain);
        $this->assertNotSame($escaped, $plain);
    }

    /**
     * A leftover recipient list is never shown as a label.
     *
     * The stored ones are cleared by db/upgrade.php, but a restore can bring one back:
     * customtext2 is the one custom field restore_instance() does not sanitise.
     *
     * @return void
     */
    public function test_a_legacy_recipient_list_is_not_shown(): void {
        $this->resetAfterTest();

        $instance = $this->instance(commentlabel::LEGACY_MARKER);

        $this->assertTrue(commentlabel::is_legacy_recipient_list(commentlabel::LEGACY_MARKER));
        $this->assertSame(get_string('applycomment', 'enrol_apply'), commentlabel::custom($instance));
    }

    /**
     * A comma-separated user id list is NOT treated as legacy, and that is deliberate.
     *
     * It cannot be told apart from a label somebody typed, so recognising it would eat real
     * labels. The marker can be told apart, which is why only the marker is recognised.
     *
     * @return void
     */
    public function test_a_user_id_list_is_left_alone(): void {
        $this->resetAfterTest();

        $this->assertFalse(commentlabel::is_legacy_recipient_list('3,7,11'));
    }

    /**
     * The queue's comment column header carries the label, in the ESCAPED spelling.
     *
     * The header sink is html_writer::tag(), which concatenates its content without escaping it,
     * so the plain spelling here would put a raw ampersand into the markup.
     *
     * @return void
     */
    public function test_the_queue_header_carries_the_escaped_label(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $instance = $this->instance(self::LABEL);
        $this->seed_application($instance);

        $table = new \enrol_apply_manage_table(
            $instance->id,
            null,
            commentlabel::custom($instance)
        );
        $table->define_baseurl(new \moodle_url('/enrol/apply/manage.php', ['id' => $instance->id]));

        ob_start();
        $table->out(50, true);
        $html = ob_get_clean();

        // Scoped to the header row, so a match anywhere else in the table cannot satisfy it.
        $this->assertSame(1, preg_match('|<thead>.*?</thead>|s', $html, $matches), $html);
        $this->assertStringContainsString('Why you &amp; who referred you', $matches[0]);
    }

    /**
     * With no label, the queue header falls back to the shipped wording.
     *
     * The control for the test above: without it, a header that rendered nothing at all would
     * satisfy "does not contain the raw ampersand" just as well as a correct one.
     *
     * @return void
     */
    public function test_the_queue_header_falls_back_to_the_shipped_wording(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $instance = $this->instance();
        $this->seed_application($instance);

        $table = new \enrol_apply_manage_table($instance->id, null, commentlabel::custom($instance));
        $table->define_baseurl(new \moodle_url('/enrol/apply/manage.php', ['id' => $instance->id]));

        ob_start();
        $table->out(50, true);
        $html = ob_get_clean();

        $this->assertSame(1, preg_match('|<thead>.*?</thead>|s', $html, $matches), $html);
        $this->assertStringContainsString(get_string('applycomment', 'enrol_apply'), $matches[0]);
    }
}
