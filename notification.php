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
 * Message sent by the enrolment upon approval plugin.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  2016 sudile GbR (http://www.sudile.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Johannes Burk <johannes.burk@sudile.com>
 */


/**
 * Message sent by the enrolment upon approval plugin.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  2016 sudile GbR (http://www.sudile.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_apply_notification extends \core\message\message {
    /**
     * Build a notification of the given type.
     *
     * @param stdClass $to Recipient user record.
     * @param stdClass $from Sender user record.
     * @param string $type One of application, confirmation, cancelation or waitinglist.
     * @param string $subject Message subject.
     * @param string $content Message body as HTML.
     * @param moodle_url $url Link offered as the message context.
     * @param int $courseid Course the message is about.
     * @throws invalid_parameter_exception When the notification type is not supported.
     */
    public function __construct($to, $from, $type, $subject, $content, $url, $courseid) {
        // Note: \core\message\message declares no constructor, so there is none to chain to.
        $this->component = 'enrol_apply';

        switch ($type) {
            case 'application':
                $this->name = 'application';
                $this->smallmessage = get_string('newapplicationnotification', 'enrol_apply');
                break;
            case 'confirmation':
                $this->name = 'confirmation';
                $this->smallmessage = get_string('applicationconfirmednotification', 'enrol_apply');
                break;
            case 'cancelation':
                $this->name = 'cancelation';
                $this->smallmessage = get_string('applicationcancelednotification', 'enrol_apply');
                break;
            case 'waitinglist':
                $this->name = 'waitinglist';
                $this->smallmessage = get_string('applicationdeferrednotification', 'enrol_apply');
                break;
            default:
                throw new invalid_parameter_exception('Invalid enrol_apply notification type.');
        }

        $this->userfrom = $from;
        $this->userto = $to;

        $this->subject = $subject;
        $this->fullmessage = html_to_text($content);
        $this->fullmessageformat = FORMAT_PLAIN;
        $this->fullmessagehtml = $content;

        $this->notification = 1;
        $this->contexturl = $url;
        $this->contexturlname = get_string('course');
        $this->courseid = $courseid;
    }
}
