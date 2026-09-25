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
 * Site level settings for the enrolment upon approval plugin.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo.com (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     emeneo.com (http://emeneo.com/)
 * @author     Johannes Burk <johannes.burk@sudile.com>
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'enrol_apply_enrolname',
        '',
        get_string('pluginname_desc', 'enrol_apply')
    ));

    /* The pool of profile fields courses may ask an applicant for. A teacher picks from this
       list per instance and the picked set is intersected with it again on every read, so
       narrowing it here narrows every existing instance at once.

       Both choice lists read {user_info_field}, which does not exist yet while
       admin_apply_default_settings() runs this file during a fresh install - hence the guard.
       The default is the full standard set rather than an empty array: the setting stores only
       the ticked keys, so an empty default would make the intersection in fields::resolve()
       drop every field an existing instance was collecting. */
    $allowedchoices = [];
    $queuefilterchoices = [];
    if (!during_initial_install()) {
        $allowedchoices = \enrol_apply\local\fields::offerable();
        $queuefilterchoices = \enrol_apply\local\queuefilter::choices();
    }
    /* Whether a course may offer to save an applicant's answers to their own profile. Off by
       default, and deliberately a site setting rather than anything an instance carries alone:
       it has no backup or restore surface at all, so no restored course can turn it on. */
    $settings->add(new admin_setting_configcheckbox(
        'enrol_apply/allowprofilewrite',
        get_string('allowprofilewrite', 'enrol_apply'),
        get_string('allowprofilewrite_desc', 'enrol_apply'),
        0
    ));

    $settings->add(new admin_setting_configmulticheckbox(
        'enrol_apply/allowedfields',
        get_string('allowedfields', 'enrol_apply'),
        get_string('allowedfields_desc', 'enrol_apply'),
        array_fill_keys(\enrol_apply\local\fields::DEFAULT_SET, 1),
        $allowedchoices
    ));

    /* Which profile fields the queue may be filtered by. Unlike the setting above, which decides
       what an applicant is asked, these are the live identity fields the queue already shows
       under each applicant's name; the submitted fields live in a per-application snapshot that
       cannot be filtered at all.

       The default is empty, so a site gets no field filters until somebody ticks a box. A static
       default naming fields a site does not show would be stored as the empty string, because
       admin_setting_configmulticheckbox::write_setting() silently drops values missing from the
       choice list, and would look configured when it is not.

       The whole row is branched rather than only its description: with no choices,
       admin_setting_configmulticheckbox::output_html() renders nothing and is_related() matches
       nothing, so guidance in its description would never be seen. */
    if ($queuefilterchoices) {
        $settings->add(new admin_setting_configmulticheckbox(
            'enrol_apply/queuefilterfields',
            get_string('queuefilterfields', 'enrol_apply'),
            get_string('queuefilterfields_desc', 'enrol_apply'),
            [],
            $queuefilterchoices
        ));
    } else {
        $settings->add(new admin_setting_description(
            'enrol_apply/queuefilterfieldsnone',
            get_string('queuefilterfields', 'enrol_apply'),
            get_string('queuefilternoidentity', 'enrol_apply')
        ));
    }

    // Confirmation mail settings.
    $settings->add(new admin_setting_heading(
        'enrol_apply_confirmmail',
        get_string('confirmmail_heading', 'enrol_apply'),
        get_string('confirmmail_desc', 'enrol_apply')
    ));
    $settings->add(new admin_setting_configtext(
        'enrol_apply/confirmmailsubject',
        get_string('confirmmailsubject', 'enrol_apply'),
        get_string('confirmmailsubject_desc', 'enrol_apply'),
        '',
        PARAM_TEXT,
        60
    ));
    $settings->add(new admin_setting_confightmleditor(
        'enrol_apply/confirmmailcontent',
        get_string('confirmmailcontent', 'enrol_apply'),
        get_string('confirmmailcontent_desc', 'enrol_apply'),
        '',
        PARAM_RAW
    ));

    // Waiting list mail settings.
    $settings->add(new admin_setting_heading(
        'enrol_apply_waitmail',
        get_string('waitmail_heading', 'enrol_apply'),
        get_string('waitmail_desc', 'enrol_apply')
    ));
    $settings->add(new admin_setting_configtext(
        'enrol_apply/waitmailsubject',
        get_string('waitmailsubject', 'enrol_apply'),
        get_string('waitmailsubject_desc', 'enrol_apply'),
        '',
        PARAM_TEXT,
        60
    ));
    $settings->add(new admin_setting_confightmleditor(
        'enrol_apply/waitmailcontent',
        get_string('waitmailcontent', 'enrol_apply'),
        get_string('waitmailcontent_desc', 'enrol_apply'),
        '',
        PARAM_RAW
    ));

    // Cancellation mail settings.
    $settings->add(new admin_setting_heading(
        'enrol_apply_cancelmail',
        get_string('cancelmail_heading', 'enrol_apply'),
        get_string('cancelmail_desc', 'enrol_apply')
    ));
    $settings->add(new admin_setting_configtext(
        'enrol_apply/cancelmailsubject',
        get_string('cancelmailsubject', 'enrol_apply'),
        get_string('cancelmailsubject_desc', 'enrol_apply'),
        '',
        PARAM_TEXT,
        60
    ));
    $settings->add(new admin_setting_confightmleditor(
        'enrol_apply/cancelmailcontent',
        get_string('cancelmailcontent', 'enrol_apply'),
        get_string('cancelmailcontent_desc', 'enrol_apply'),
        '',
        PARAM_RAW
    ));

    // Notification settings.
    $settings->add(new admin_setting_heading(
        'enrol_apply_notify',
        get_string('notify_heading', 'enrol_apply'),
        get_string('notify_desc', 'enrol_apply')
    ));
    $settings->add(new admin_setting_users_with_capability(
        'enrol_apply/notifyglobal',
        get_string('notifyglobal', 'enrol_apply'),
        get_string('notifyglobal_desc', 'enrol_apply'),
        [],
        'enrol/apply:manageapplications'
    ));

    // Retention of the application trail.
    $settings->add(new admin_setting_heading(
        'enrol_apply_retention',
        get_string('retention_heading', 'enrol_apply'),
        get_string('retention_desc', 'enrol_apply')
    ));
    /* A configduration always stores seconds, whatever unit the administrator picks, despite
       the key's name. \enrol_apply\local\submission::retention_seconds() is its only reader. */
    $settings->add(new admin_setting_configduration(
        'enrol_apply/retentiondays',
        get_string('retentiondays', 'enrol_apply'),
        get_string('retentiondays_desc', 'enrol_apply'),
        30 * DAYSECS
    ));

    // Expiry settings.
    $settings->add(new admin_setting_heading(
        'enrol_apply_expiry',
        get_string('expiry_heading', 'enrol_apply'),
        get_string('expiry_desc', 'enrol_apply')
    ));
    $settings->add(new admin_setting_configselect(
        'enrol_apply/expiredaction',
        get_string('expiredaction', 'enrol_apply'),
        get_string('expiredaction_help', 'enrol_apply'),
        ENROL_EXT_REMOVED_KEEP,
        [
            ENROL_EXT_REMOVED_KEEP => get_string('extremovedkeep', 'enrol'),
            ENROL_EXT_REMOVED_SUSPEND => get_string('extremovedsuspend', 'enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
            ENROL_EXT_REMOVED_UNENROL => get_string('extremovedunenrol', 'enrol'),
        ]
    ));

    $hours = [];
    for ($i = 0; $i < 24; $i++) {
        $hours[$i] = $i;
    }
    $settings->add(new admin_setting_configselect(
        'enrol_apply/expirynotifyhour',
        get_string('expirynotifyhour', 'core_enrol'),
        get_string('expirynotifyhour_desc', 'enrol_apply'),
        6,
        $hours
    ));

    // Defaults applied to newly created instances.
    $settings->add(new admin_setting_heading(
        'enrol_apply_defaults',
        get_string('enrolinstancedefaults', 'admin'),
        get_string('enrolinstancedefaults_desc', 'admin')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_apply/defaultenrol',
        get_string('defaultenrol', 'enrol'),
        get_string('defaultenrol_desc', 'enrol'),
        0
    ));

    $settings->add(new admin_setting_configselect(
        'enrol_apply/status',
        get_string('status', 'enrol_apply'),
        get_string('status_desc', 'enrol_apply'),
        ENROL_INSTANCE_ENABLED,
        [
            ENROL_INSTANCE_ENABLED => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
        ]
    ));

    $yesno = [1 => get_string('yes'), 0 => get_string('no')];

    $settings->add(new admin_setting_configselect(
        'enrol_apply/newenrols',
        get_string('newenrols', 'enrol_apply'),
        get_string('newenrols_desc', 'enrol_apply'),
        1,
        $yesno
    ));

    $settings->add(new admin_setting_configselect(
        'enrol_apply/opt_commentaryzone',
        get_string('opt_commentaryzone', 'enrol_apply'),
        get_string('opt_commentaryzone_help', 'enrol_apply'),
        0,
        $yesno
    ));

    /* The config key stays 'maxenrolled' while the strings are 'maxapplicants': the lang key
       changed because its meaning did - it names one of two limits now - but a config key is
       data, and renaming it would silently reset the limit every site has configured.

       Both defaults take a regex rather than PARAM_INT, so a negative is refused as the instance
       form refuses it (enrol_apply_edit_form::validation()): PARAM_INT stores -1 as typed, and
       \enrol_apply\local\capacity reads it as no limit. Unlike PARAM_INT, the regex does not
       turn an emptied field into 0, so an empty value is refused as well. */
    $settings->add(new admin_setting_configtext(
        'enrol_apply/maxenrolled',
        get_string('maxapplicants', 'enrol_apply'),
        get_string('maxapplicants_help', 'enrol_apply'),
        0,
        '/^[0-9]+$/',
        5
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_apply/places',
        get_string('places', 'enrol_apply'),
        get_string('places_help', 'enrol_apply'),
        0,
        '/^[0-9]+$/',
        5
    ));

    if (!during_initial_install()) {
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect(
            'enrol_apply/roleid',
            get_string('defaultrole', 'role'),
            get_string('defaultrole_desc', 'enrol_apply'),
            $student->id,
            get_default_enrol_roles(context_system::instance())
        ));
    }

    $settings->add(new admin_setting_configcheckbox(
        'enrol_apply/notifycoursebased',
        get_string('notifycoursebased', 'enrol_apply'),
        get_string('notifycoursebased_desc', 'enrol_apply'),
        0
    ));

    $settings->add(new admin_setting_configduration(
        'enrol_apply/enrolperiod',
        get_string('defaultperiod', 'enrol_apply'),
        get_string('defaultperiod_desc', 'enrol_apply'),
        0
    ));
}

/* The site-wide queue's node in Site administration, for site administrators only: core
   includes an enrol plugin's settings.php solely for moodle/site:config holders
   (core\plugininfo\enrol::load_settings() returns early otherwise), so no condition here can
   show the node to anybody else. A system-level holder of enrol/apply:manageapplications
   without moodle/site:config reaches the same queue at /enrol/apply/manage.php by its url,
   which \enrol_apply\local\queue::listing_scope() serves them. The condition restates core's
   gate rather than widening it. */
if ($hassiteconfig) {
    $ADMIN->add('courses', new admin_externalpage(
        'enrol_apply',
        get_string('applymanage', 'enrol_apply'),
        new moodle_url('/enrol/apply/manage.php'),
        'enrol/apply:manageapplications'
    ));
}
