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

/**
 * Carries what an applicant just typed from the submission to the acknowledgement page.
 *
 * The application is submitted in one request and acknowledged in another, so the values the
 * offer to save is computed from have to survive a redirect. They live in the session and are
 * consumed on first read: a table would need privacy metadata, a retention policy and a place
 * in the backup for data whose whole life is two requests long, and personal data may never
 * travel in a url.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class offer {
    /**
     * Remember what would change, so the next request can offer to save it.
     *
     * @param \stdClass $instance Enrol instance the application was submitted to.
     * @param \stdClass $user The applicant.
     * @param array $submitted Submitted values keyed by form element name.
     * @return void
     */
    public static function stash(\stdClass $instance, \stdClass $user, array $submitted): void {
        global $SESSION;

        if (!profilewriter::is_enabled($instance)) {
            // Nothing will be offered, so nothing is kept.
            return;
        }

        $changes = diff::compute($instance, $user, $submitted);
        if (!$changes) {
            return;
        }

        if (!isset($SESSION->enrol_apply_offer) || !is_array($SESSION->enrol_apply_offer)) {
            $SESSION->enrol_apply_offer = [];
        }
        $SESSION->enrol_apply_offer[(int) $instance->id] = $changes;
    }

    /**
     * Read what was stashed for one instance, leaving it in place.
     *
     * Used to render the offer. Reading without consuming means a reload still shows it, so
     * an applicant who navigated away can come back and accept.
     *
     * @param int $instanceid Enrol instance id.
     * @return array The stashed changes, or an empty array when there are none.
     */
    public static function peek(int $instanceid): array {
        global $SESSION;

        $changes = $SESSION->enrol_apply_offer[$instanceid] ?? [];

        return is_array($changes) ? $changes : [];
    }

    /**
     * Read and forget what was stashed for one instance.
     *
     * Used by the write, so accepting the offer consumes it and a second post writes nothing.
     *
     * @param int $instanceid Enrol instance id.
     * @return array The stashed changes, or an empty array when there are none.
     */
    public static function take(int $instanceid): array {
        global $SESSION;

        $changes = self::peek($instanceid);
        unset($SESSION->enrol_apply_offer[$instanceid]);

        return $changes;
    }
}
