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
 * Tests for apply.php, the application form's page for a browser with no JavaScript.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use enrol_apply\form\application_form;
use enrol_apply\local\keyattempts;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for apply.php, the application form's page for a browser with no JavaScript.
 *
 * The page script is run whole, as the request would run it, and its markup read back.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(application_form::class)]
final class apply_page_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
    }

    #[\Override]
    protected function tearDown(): void {
        $_GET = [];
        keyattempts::release_all();
        parent::tearDown();
    }

    /**
     * Run apply.php with GET parameters and return what it printed.
     *
     * @param array $params The query parameters.
     * @return string The page's markup.
     */
    private function run_page(array $params): string {
        // The page script reads these from the global scope.
        global $CFG, $DB, $PAGE, $OUTPUT, $USER, $SITE, $COURSE;

        $_GET = $params;
        $level = ob_get_level();
        ob_start();
        try {
            require($CFG->dirroot . '/enrol/apply/apply.php');
        } finally {
            $html = '';
            while (ob_get_level() > $level) {
                $html = ob_get_clean() . $html;
            }
        }
        return $html;
    }

    /**
     * The form posts back to the page with both ids intact, escaped once.
     *
     * dynamic_form takes its action as a string, so handing it the page's moodle_url casts it
     * with out(true) and the form escapes that again: the browser then posts to
     * "instance=N&amp;id=M", and the course id arrives under the name "amp;id".
     *
     * Changes that must make it fail: the moodle_url object (or its escaped spelling) passed
     * to the form as its action.
     *
     * @return void
     */
    public function test_the_form_posts_back_to_the_page_with_both_ids(): void {
        $plugin = enrol_get_plugin('apply');
        $course = $this->getDataGenerator()->create_course();
        $instanceid = (int) $plugin->add_instance($course, $plugin->get_instance_defaults());
        $this->setUser($this->getDataGenerator()->create_user());

        $html = $this->run_page(['instance' => $instanceid]);

        $form = preg_match('~<form\b[^>]*\bclass="[^"]*\bmform\b[^"]*"[^>]*>~', $html, $tag);
        $this->assertSame(1, $form, 'the form is rendered');
        $this->assertSame(1, preg_match('~\baction="([^"]*)"~', $tag[0], $action), 'the form has an action');

        $posted = html_entity_decode($action[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        parse_str((string) parse_url($posted, PHP_URL_QUERY), $query);
        $this->assertSame(
            ['instance' => (string) $instanceid, 'id' => (string) $course->id],
            $query,
            'the url the browser posts to carries both ids by their own names'
        );
    }
}
