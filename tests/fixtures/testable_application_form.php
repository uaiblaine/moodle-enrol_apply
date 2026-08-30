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

namespace enrol_apply\form;

/**
 * An application form whose submitted data the test supplies directly.
 *
 * A dynamic_form built in a unit test is never "submitted": moodleform::_process_submission()
 * needs the _qf__ marker, and supplying it drives the form into require_sesskey() and dies in
 * sessionlib. So get_data() returns null, which is harmless for most of
 * process_dynamic_submission() but silently empties the profile-update offer - diff::compute()
 * over no data finds no changes and offer::stash() returns before writing anything.
 *
 * That matters because it makes the obvious test vacuous. Asserting "a refused application
 * stashes nothing" would pass against a build that stashes unconditionally, since there is
 * nothing to stash either way. Overriding get_data() is what lets the stash actually happen,
 * so the test can distinguish the two.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_application_form extends application_form {
    /** @var \stdClass|null What get_data() should return. */
    protected $testdata = null;

    /**
     * Set what the form will report as its submitted data.
     *
     * @param \stdClass|null $data Submitted data to hand back, or null for "not submitted".
     * @return void
     */
    public function set_test_data(?\stdClass $data): void {
        $this->testdata = $data;
    }

    /**
     * The submitted data the test supplied.
     *
     * @return \stdClass|null Whatever set_test_data() was given.
     */
    public function get_data() {
        return $this->testdata;
    }
}
