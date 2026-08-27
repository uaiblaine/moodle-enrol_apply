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
 * Tests for the links to the applications either side of the one being reviewed.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\output;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the links to the applications either side of the one being reviewed.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(application_navigation::class)]
final class application_navigation_test extends \advanced_testcase {
    /**
     * A name carrying both characters an escaper rewrites, so a double escape is visible.
     *
     * The "<" has a space after it on purpose: strip_tags() would remove a tag-shaped run
     * identically whatever the escaping, and would prove nothing.
     */
    private const AWKWARD_NAME = 'R&D < Team';

    /**
     * A neighbour record of the shape queue::neighbours() returns.
     *
     * @param int $ueid User enrolment id the link should point at.
     * @param string $firstname Applicant's first name.
     * @param string $lastname Applicant's last name.
     * @return \stdClass Record carrying the id, the applicant id and their name fields.
     */
    private function neighbour(int $ueid, string $firstname, string $lastname): \stdClass {
        return (object) [
            'id' => $ueid,
            'userid' => $ueid + 1000,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'firstnamephonetic' => '',
            'lastnamephonetic' => '',
            'middlename' => '',
            'alternatename' => '',
        ];
    }

    /**
     * The renderer this plugin's pages use.
     *
     * @return \enrol_apply_renderer The plugin renderer.
     */
    private function renderer(): \enrol_apply_renderer {
        global $PAGE;

        $PAGE->set_url(new \moodle_url('/enrol/apply/manage.php'));
        $PAGE->set_context(\context_system::instance());

        return $PAGE->get_renderer('enrol_apply');
    }

    /**
     * Both links name the applicant they lead to and point at that application's review page.
     *
     * Naming the destination is what makes the walk's pinned order legible: it follows the
     * queue's own default sort rather than whatever the operator last sorted the queue into, so
     * the operator has to be able to read where "next" goes before they follow it.
     *
     * @return void
     */
    public function test_it_names_both_neighbours_and_links_to_their_review_pages(): void {
        $this->resetAfterTest();

        $navigation = new application_navigation(
            $this->neighbour(41, 'Alice', 'Anderson'),
            $this->neighbour(43, 'Bob', 'Brown')
        );

        $context = $navigation->export_for_template($this->renderer());

        $this->assertTrue($context['hasneighbours']);
        $this->assertSame(get_string('reviewprevious', 'enrol_apply', 'Alice Anderson'), $context['previous']['title']);
        $this->assertSame(get_string('reviewnext', 'enrol_apply', 'Bob Brown'), $context['next']['title']);
        $this->assertStringContainsString('userenrol=41', $context['previous']['url']);
        $this->assertStringContainsString('userenrol=43', $context['next']['url']);
        // The link carries the application and nothing else; the scope is derived on arrival.
        $this->assertStringNotContainsString('id=', $context['previous']['url']);
    }

    /**
     * One end of the walk offers one link, and the other direction is absent rather than empty.
     *
     * @return void
     */
    public function test_an_end_of_the_walk_offers_only_the_one_direction(): void {
        $this->resetAfterTest();

        $navigation = new application_navigation(null, $this->neighbour(43, 'Bob', 'Brown'));

        $context = $navigation->export_for_template($this->renderer());

        $this->assertTrue($context['hasneighbours']);
        $this->assertArrayNotHasKey('previous', $context);
        $this->assertArrayHasKey('next', $context);
    }

    /**
     * A queue of one renders no navigation at all, not an empty landmark.
     *
     * An empty nav element is not merely useless: a screen reader announces the region and
     * lets its user step into it, so the one application in a queue would advertise a way out
     * of itself that does not exist.
     *
     * @return void
     */
    public function test_a_queue_of_one_renders_no_navigation_at_all(): void {
        $this->resetAfterTest();

        $renderer = $this->renderer();
        $navigation = new application_navigation(null, null);

        $this->assertFalse($navigation->export_for_template($renderer)['hasneighbours']);
        $this->assertStringNotContainsString('<nav', $renderer->render($navigation));
    }

    /**
     * The name reaches the reader escaped exactly once, through the template render() guesses.
     *
     * Two claims, and they fail together. renderer_base::render() resolves a templatable with
     * no render_ method of its own to "<component>/<class>", so this markup is evidence that
     * enrol_apply/application_navigation was found under the name this class carries - rename
     * either half and render_from_template() throws. An earlier draft of this docblock claimed
     * something stronger and wrong: that the plugin renderer's own render_application_navigation()
     * was what dispatched here, and that removing it would fall through to the core renderer
     * "instead of erroring". Renaming that method under the whole suite reddened NOTHING, which
     * is how the claim was caught; the method is gone and the fallback is the real path.
     *
     * And fullname() returns the PLAIN spelling, which the template double stashes: an ampersand
     * reaching the reader as "&amp;amp;" would mean somebody had escaped it on the way in too.
     *
     * @return void
     */
    public function test_the_rendered_links_escape_an_applicants_name_exactly_once(): void {
        $this->resetAfterTest();

        $renderer = $this->renderer();
        $navigation = new application_navigation(
            $this->neighbour(41, self::AWKWARD_NAME, 'Unit'),
            null
        );

        $html = $renderer->render($navigation);

        $this->assertStringContainsString('enrol_apply-applicationnav', $html);
        $this->assertStringContainsString('R&amp;D &lt; Team Unit', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('&amp;lt;', $html);
    }
}
