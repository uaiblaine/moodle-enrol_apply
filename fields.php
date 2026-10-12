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
 * Manage the questions one apply instance asks at application time.
 *
 * This page authorises the first view only. Every later action (adding a category, editing a
 * field, switching a shared category on) goes through core's custom field web services and
 * dynamic forms, which never run this file: their gate is application_handler::can_configure().
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT);

$instance = $DB->get_record('enrol', ['id' => $id, 'enrol' => 'apply'], '*', MUST_EXIST);
$course = get_course($instance->courseid);
$context = context_course::instance($course->id);

require_login($course);
require_capability('enrol/apply:config', $context);

$PAGE->set_url('/enrol/apply/fields.php', ['id' => $instance->id]);
$PAGE->set_pagelayout('admin');
navigation_node::override_active_url(new moodle_url('/enrol/instances.php', ['id' => $course->id]));

$plugin = enrol_get_plugin('apply');
$title = get_string('applicationfields', 'enrol_apply');
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add($plugin->get_instance_name($instance));
$PAGE->navbar->add($title);

$handler = \enrol_apply\customfield\application_handler::create($instance->id);
$output = $PAGE->get_renderer('core_customfield');

echo $output->header();
echo $output->heading($title);
echo $output->paragraph(get_string('applicationfields_desc', 'enrol_apply'));
echo $output->render(new \core_customfield\output\management($handler));
echo $output->footer();
