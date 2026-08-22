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
 * Accepts the offer to save an application's answers to the applicant's own profile.
 *
 * Reached only by posting the offer rendered on applied.php. It writes nothing that the
 * applicant did not just type, and only to their own record.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$instanceid = required_param('instance', PARAM_INT);

require_login();
require_sesskey();

$instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'apply'], '*', MUST_EXIST);

/* Only somebody who actually applied through this instance is offered anything, and only
   while their application is still undecided - the offer belongs to the submission that
   produced it, not to the course. */
$hasapplication = $DB->record_exists_select(
    'user_enrolments',
    'enrolid = :enrolid AND userid = :userid AND status <> :active',
    ['enrolid' => $instance->id, 'userid' => $USER->id, 'active' => ENROL_USER_ACTIVE]
);
if (!$hasapplication) {
    throw new moodle_exception('invalidaccess', 'error');
}

$returnurl = new moodle_url('/enrol/apply/applied.php', ['instance' => $instance->id]);

/* The values were computed and stashed when the application was submitted, and are consumed
   here. Nothing arrives from the post except the instance id and the sesskey, so there is no
   value in the request for anybody to forge. */
$changes = \enrol_apply\local\offer::take($instance->id);
if (!$changes) {
    redirect($returnurl);
}

$submitted = [];
foreach ($changes as $change) {
    $name = \enrol_apply\local\fields::form_element_name($change['key']);
    if ($name !== '') {
        $submitted[$name] = $change['after'];
    }
}

$written = \enrol_apply\local\profilewriter::write($instance, $USER, $submitted);

redirect(
    $returnurl,
    get_string($written ? 'profileupdated' : 'profilenotupdated', 'enrol_apply'),
    null,
    $written ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_INFO
);
