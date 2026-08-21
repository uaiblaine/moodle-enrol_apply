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

       The choices read {user_info_field}, which is only safe once the install has finished -
       hence the guard, without which admin_apply_default_settings() breaks a fresh install.
       The default is deliberately the full standard set and not an empty array: the setting
       stores only the ticked keys, so an empty default would make the intersection in
       fields::resolve() zero everything and every migrated instance would silently stop
       collecting what it collected before the upgrade. */
    $allowedchoices = [];
    if (!during_initial_install()) {
        $allowedchoices = \enrol_apply\local\fields::offerable();
    }
    $settings->add(new admin_setting_configmulticheckbox(
        'enrol_apply/allowedfields',
        get_string('allowedfields', 'enrol_apply'),
        get_string('allowedfields_desc', 'enrol_apply'),
        array_fill_keys(\enrol_apply\local\fields::DEFAULT_SET, 1),
        $allowedchoices
    ));

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

    $settings->add(new admin_setting_configtext(
        'enrol_apply/maxenrolled',
        get_string('maxenrolled', 'enrol_apply'),
        get_string('maxenrolled_help', 'enrol_apply'),
        0,
        PARAM_INT
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

/* Gating this on $hassiteconfig alone hid the site-wide queue from exactly the people it
   is for: a manager may hold enrol/apply:manageapplications at system level without
   holding moodle/site:config. The capability is passed to admin_externalpage so the node
   is only shown to users who can actually use it. The guard itself is kept because
   registering the node unconditionally errored on the login page. */
if ($hassiteconfig || has_capability('enrol/apply:manageapplications', context_system::instance())) {
    $ADMIN->add('courses', new admin_externalpage(
        'enrol_apply',
        get_string('applymanage', 'enrol_apply'),
        new moodle_url('/enrol/apply/manage.php'),
        'enrol/apply:manageapplications'
    ));
}
