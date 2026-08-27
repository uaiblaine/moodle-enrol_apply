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

namespace enrol_apply\output;

use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Links to the applications either side of the one being reviewed.
 *
 * Shaped on mod_book\output\main_action_menu, which of core's server-side previous/next
 * implementations is the only one already written the way this plugin writes: a renderable and
 * templatable whose export_for_template() returns a title and a url per direction, rendered by
 * a Mustache template with a nav landmark and pix icons, and no html_writer anywhere. The
 * gradebook's single view, which named this shape, builds a bare array inside a renderer
 * method, gives its template no landmark, names no neighbour in its labels and draws its icons
 * with raw Font Awesome classes.
 *
 * The neighbours themselves are resolved by \enrol_apply\local\queue::neighbours(), which owns
 * both the order the walk follows and the scope it runs in. This class only turns them into
 * something a template can render, so that a change to what "next" means happens in one place.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class application_navigation implements renderable, templatable {
    /** @var stdClass|null The application before this one, null at the start of the queue. */
    protected $previous;

    /** @var stdClass|null The application after this one, null at the end of the queue. */
    protected $next;

    /**
     * Build the navigation from a resolved pair of neighbours.
     *
     * @param stdClass|null $previous Neighbour record carrying id, userid and the name fields.
     * @param stdClass|null $next Neighbour record carrying id, userid and the name fields.
     */
    public function __construct(?stdClass $previous, ?stdClass $next) {
        $this->previous = $previous;
        $this->next = $next;
    }

    /**
     * Export the two links.
     *
     * Each link NAMES the applicant it leads to, in its visible text and in its accessible
     * label alike. That is not decoration: the walk follows the queue's own default order
     * rather than whatever order the operator last sorted the queue into, so naming the
     * destination is what turns a possible disagreement with the list on screen into something
     * the operator reads before they act on it rather than after.
     *
     * fullname() returns the PLAIN spelling and the template double stashes it, here and
     * inside the string, so each value is escaped exactly once.
     *
     * @param renderer_base $output Renderer the template is rendered with.
     * @return array Template context.
     */
    public function export_for_template(renderer_base $output): array {
        $context = [
            'navlabel' => get_string('reviewnavigation', 'enrol_apply'),
            'hasneighbours' => $this->previous !== null || $this->next !== null,
        ];

        if ($this->previous) {
            $context['previous'] = [
                'title' => get_string('reviewprevious', 'enrol_apply', fullname($this->previous)),
                'url' => self::url($this->previous)->out(false),
            ];
        }
        if ($this->next) {
            $context['next'] = [
                'title' => get_string('reviewnext', 'enrol_apply', fullname($this->next)),
                'url' => self::url($this->next)->out(false),
            ];
        }

        return $context;
    }

    /**
     * The review page of one neighbour.
     *
     * Carries the user enrolment id and nothing else. The scope the walk runs in is derived
     * from the operator on arrival, so there is no second parameter for it to disagree with.
     *
     * @param stdClass $neighbour Neighbour record.
     * @return moodle_url Review page of that application.
     */
    protected static function url(stdClass $neighbour): moodle_url {
        return new moodle_url('/enrol/apply/manage.php', ['userenrol' => (int) $neighbour->id]);
    }
}
