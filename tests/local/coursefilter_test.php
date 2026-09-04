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
 * Tests for the course and category vocabulary of the applications queue.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the course and category vocabulary of the applications queue.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(coursefilter::class)]
final class coursefilter_test extends \advanced_testcase {
    /**
     * Reset before every test; all of these write courses or categories.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Only the site-wide queue offers these controls.
     *
     * With ?id=<enrolid> the queue names one course already, so the control would filter a set of
     * one. The mentee queue does span courses, but a mentor sees a handful and the control would
     * be noise. The scope shapes come from queue::listing_scope(), which is what this mirrors.
     *
     * @return void
     */
    public function test_only_the_site_wide_queue_offers_the_controls(): void {
        $sitewide = (object) ['instance' => null, 'mentees' => null];
        $mentee = (object) ['instance' => null, 'mentees' => [7]];
        $oneinstance = (object) ['instance' => (object) ['id' => 3], 'mentees' => null];

        $this->assertTrue(coursefilter::offered($sitewide));
        $this->assertFalse(coursefilter::offered($mentee));
        $this->assertFalse(coursefilter::offered($oneinstance));
    }

    /**
     * A course is offered only when it has an apply enrolment method.
     *
     * The reader of this queue holds the capability at the system context, so what bounds the list
     * is not permission but relevance: a course with no apply method can never put a row on this
     * queue, and offering it would be a control that only ever empties the page.
     *
     * @return void
     */
    public function test_a_course_without_an_apply_method_is_not_a_filter(): void {
        $plugin = enrol_get_plugin('apply');
        $withapply = $this->getDataGenerator()->create_course();
        $without = $this->getDataGenerator()->create_course();
        $plugin->add_instance($withapply, $plugin->get_instance_defaults());

        $this->assertSame((int) $withapply->id, coursefilter::clean_course((int) $withapply->id));
        $this->assertNull(coursefilter::clean_course((int) $without->id));
        $this->assertNull(coursefilter::clean_course(0));
        $this->assertNull(coursefilter::clean_course(-1));

        // The control: the offered list agrees with the cleaner, so neither can drift alone.
        $this->assertArrayHasKey((int) $withapply->id, coursefilter::courses());
        $this->assertArrayNotHasKey((int) $without->id, coursefilter::courses());
    }

    /**
     * A category filter reaches the courses in its whole subtree.
     *
     * Filtering by "Engineering" has to find the courses sitting under "Engineering / Civil", which
     * is what an operator means by it. Core stores the ancestry as a materialised path, so this is
     * a prefix match rather than a recursive walk.
     *
     * The sibling category is the control: it proves the predicate narrows at all, so a subtree
     * match cannot pass by matching everything.
     *
     * @return void
     */
    public function test_a_category_filter_includes_its_subtree(): void {
        global $DB;

        $parent = $this->getDataGenerator()->create_category();
        $child = $this->getDataGenerator()->create_category(['parent' => $parent->id]);
        $sibling = $this->getDataGenerator()->create_category();

        $inchild = $this->getDataGenerator()->create_course(['category' => $child->id]);
        $inparent = $this->getDataGenerator()->create_course(['category' => $parent->id]);
        $elsewhere = $this->getDataGenerator()->create_course(['category' => $sibling->id]);

        [$wheres, $params] = coursefilter::where((int) $parent->id, null);
        $this->assertCount(1, $wheres);

        $sql = "SELECT c.id FROM {course} c WHERE " . implode(' AND ', $wheres);
        $found = array_map('intval', array_keys($DB->get_records_sql($sql, $params)));

        $this->assertContains((int) $inchild->id, $found, 'a course in a subcategory must be found');
        $this->assertContains((int) $inparent->id, $found);
        $this->assertNotContains((int) $elsewhere->id, $found);
    }

    /**
     * A category that does not exist is no filter rather than an error.
     *
     * The same answer the status filter gives a value outside its vocabulary: the queue narrows by
     * what it understood, and a mistyped url is not an error page.
     *
     * @return void
     */
    public function test_an_unknown_category_is_no_filter(): void {
        $real = $this->getDataGenerator()->create_category();

        $this->assertNull(coursefilter::clean_category(0));
        $this->assertNull(coursefilter::clean_category(-3));
        $this->assertNull(coursefilter::clean_category(((int) $real->id) + 100000));

        // The control: a real category IS read, so the assertions above are not about a dead reader.
        $this->assertSame((int) $real->id, coursefilter::clean_category((int) $real->id));
    }
}
