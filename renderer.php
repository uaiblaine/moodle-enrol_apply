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
 * Renderer for the enrolment upon approval plugin.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  2016 sudile GbR (http://www.sudile.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Johannes Burk <johannes.burk@sudile.com>
 */


/**
 * Renderer for the enrolment upon approval plugin.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  2016 sudile GbR (http://www.sudile.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_apply_renderer extends plugin_renderer_base {
    /**
     * Standard user profile fields offered on the application form, in display order.
     *
     * Each entry maps the form field name to the core language string identifier used as
     * its label. Fields removed from core (icq, skype, aim, yahoo, msn, all dropped in
     * Moodle 4.0) are deliberately absent, and any field the site has hidden simply does
     * not reach the notification because the loop skips properties that are not set.
     *
     * @var array
     */
    protected const STANDARD_USER_FIELDS = [
        'firstname' => 'firstname',
        'lastname' => 'lastname',
        'email' => 'email',
        'city' => 'city',
        'country' => 'country',
        'lang' => 'preferredlanguage',
        'firstnamephonetic' => 'firstnamephonetic',
        'lastnamephonetic' => 'lastnamephonetic',
        'middlename' => 'middlename',
        'alternatename' => 'alternatename',
        'url' => 'webpage',
        'idnumber' => 'idnumber',
        'institution' => 'institution',
        'department' => 'department',
        'phone1' => 'phone1',
        'phone2' => 'phone2',
        'address' => 'address',
    ];

    /**
     * Render the page listing the applications awaiting a decision.
     *
     * @param enrol_apply_manage_table $table Table listing the applications.
     * @param moodle_url $manageurl Url the decision form posts back to.
     * @param stdClass|null $instance Enrol instance when the page is scoped to one, null otherwise.
     * @return void
     */
    public function manage_page($table, $manageurl, $instance) {
        echo $this->header();
        echo $this->heading(get_string('confirmusers', 'enrol_apply'));
        echo html_writer::tag('p', get_string('confirmusers_desc', 'enrol_apply'));
        echo $this->manage_form($table, $manageurl);
        echo $this->footer();
    }

    /**
     * Render the decision form wrapping the applications table.
     *
     * @param enrol_apply_manage_table $table Table listing the applications.
     * @param moodle_url $manageurl Url the form posts back to.
     * @return string Rendered markup.
     */
    public function manage_form($table, $manageurl) {
        $tablehtml = $this->capture_table($table);

        $actions = [
            ['value' => 'confirm', 'label' => get_string('btnconfirm', 'enrol_apply')],
            ['value' => 'wait', 'label' => get_string('btnwait', 'enrol_apply')],
            ['value' => 'cancel', 'label' => get_string('btncancel', 'enrol_apply')],
        ];

        $context = [
            'formurl' => $manageurl->out(false),
            'sesskey' => sesskey(),
            'tablehtml' => $tablehtml,
            'hasrows' => $table->totalrows > 0,
            'actionlabel' => get_string('withselectedusers'),
            'choosedots' => get_string('choosedots'),
            'golabel' => get_string('go'),
            'actions' => $actions,
        ];

        if ($context['hasrows']) {
            $this->page->requires->js_call_amd('enrol_apply/manage', 'init');
        }

        return $this->render_from_template('enrol_apply/manage', $context);
    }

    /**
     * Render the page listing the comments submitted with the applications.
     *
     * @param enrol_apply_info_table $table Table listing the applications and their comments.
     * @param moodle_url $manageurl Base url of the page.
     * @param stdClass|null $instance Enrol instance when the page is scoped to one, null otherwise.
     * @return void
     */
    public function info_page($table, $manageurl, $instance) {
        echo $this->header();
        echo $this->heading(get_string('submitted_info', 'enrol_apply'));
        echo $this->render_from_template('enrol_apply/info', [
            'tablehtml' => $this->capture_table($table),
        ]);
        echo $this->footer();
    }

    /**
     * Render a table to a string.
     *
     * table_sql writes straight to the output buffer, so it has to be captured before it
     * can be handed to a template.
     *
     * @param table_sql $table Table to render.
     * @return string Rendered table markup.
     */
    protected function capture_table($table) {
        ob_start();
        $table->out(50, true);
        return ob_get_clean();
    }

    /**
     * Build the HTML body of the "new application" notification.
     *
     * @param stdClass $course Course applied for.
     * @param stdClass $user Applicant.
     * @param moodle_url $manageurl Link to the screen where the application can be decided.
     * @param string $applydescription Comment submitted with the application.
     * @param stdClass|null $standarduserfields Submitted standard profile fields, null when not collected.
     * @param array|null $extrauserfields Custom profile fields, null when not collected.
     * @return string Rendered HTML body.
     */
    public function application_notification_mail_body(
        $course,
        $user,
        $manageurl,
        $applydescription,
        $standarduserfields = null,
        $extrauserfields = null
    ) {
        $profile = [];
        if ($standarduserfields) {
            foreach (self::STANDARD_USER_FIELDS as $field => $stringid) {
                if (!isset($standarduserfields->{$field}) || $standarduserfields->{$field} === '') {
                    continue;
                }
                $profile[] = [
                    'label' => get_string($stringid),
                    'value' => s($standarduserfields->{$field}),
                ];
            }
            if (!empty($standarduserfields->description_editor['text'])) {
                $profile[] = [
                    'label' => get_string('description'),
                    'value' => format_text(
                        $standarduserfields->description_editor['text'],
                        $standarduserfields->description_editor['format'] ?? FORMAT_HTML
                    ),
                ];
            }
        }

        $extra = [];
        if ($extrauserfields) {
            foreach ($extrauserfields as $key => $value) {
                $extra[] = ['label' => s($key), 'value' => s($value)];
            }
        }

        return $this->render_from_template('enrol_apply/application_notification', [
            'coursenamelabel' => get_string('coursename', 'enrol_apply'),
            'coursename' => format_string($course->fullname),
            'applicantlabel' => get_string('applyuser', 'enrol_apply'),
            'applicant' => fullname($user),
            'commentlabel' => get_string('comment', 'enrol_apply'),
            'comment' => s($applydescription),
            'profilelabel' => get_string('user_profile', 'enrol_apply'),
            'hasprofile' => (bool) $profile,
            'profile' => $profile,
            'hasextra' => (bool) $extra,
            'extra' => $extra,
            'manageurl' => $manageurl->out(false),
            'managelabel' => get_string('applymanage', 'enrol_apply'),
        ]);
    }

    /**
     * Render the instance edit form.
     *
     * @param moodleform $mform Instance edit form.
     * @return void
     */
    public function edit_page($mform) {
        echo $this->header();
        echo $this->heading(get_string('pluginname', 'enrol_apply'));
        echo $mform->render();
        echo $this->footer();
    }
}
