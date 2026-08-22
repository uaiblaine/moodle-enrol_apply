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

namespace enrol_apply\local;

use moodle_url;

/**
 * Where to send an applicant who has nowhere specific to go.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class destination {
    /**
     * The user's own home page, as core would decide it.
     *
     * Never the course. A submitted application leaves the applicant suspended on that
     * course, so /course/view.php bounces them straight back to the enrolment page they
     * just came from - a loop that looks like the submission failed.
     *
     * The mapping is core's own, from login/lib.php. Two neighbouring helpers are
     * deliberately not used: core_login_get_return_url() clears $SESSION->wantsurl as a side
     * effect, which would silently discard wherever the user was originally heading, and
     * get_default_home_page_url() accepts any local path including a course the applicant
     * cannot get into.
     *
     * @return moodle_url Somewhere the applicant can actually land.
     */
    public static function home_page_url(): moodle_url {
        global $CFG;

        switch (get_home_page()) {
            case HOMEPAGE_MY:
                return new moodle_url('/my/');
            case HOMEPAGE_MYCOURSES:
                return new moodle_url('/my/courses.php');
            case HOMEPAGE_USER:
                // Every home page option is disabled on this site; core sends them here.
                return new moodle_url('/user/preferences.php');
            case HOMEPAGE_URL:
                /* A site-configured landing page. get_default_home_page_url() resolves it,
                   but it may name a course, so the site root is the safe reading here. */
                return new moodle_url('/');
            default:
                return new moodle_url('/');
        }
    }
}
