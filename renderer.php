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
        echo $this->manage_form($table, $manageurl, $instance);
        echo $this->footer();
    }

    /**
     * Render the decision form wrapping the applications table.
     *
     * @param enrol_apply_manage_table $table Table listing the applications.
     * @param moodle_url $manageurl Url the form posts back to.
     * @param stdClass|null $instance Enrol instance the queue is scoped to, null site wide.
     * @return string Rendered markup.
     */
    public function manage_form($table, $manageurl, $instance = null) {
        $tablehtml = $this->capture_table($table);

        $actions = [
            ['value' => 'confirm', 'label' => get_string('btnconfirm', 'enrol_apply')],
            ['value' => 'wait', 'label' => get_string('btnwait', 'enrol_apply')],
            ['value' => 'cancel', 'label' => get_string('btncancel', 'enrol_apply')],
        ];

        /* Offered only where the course actually has groups. A chooser with nothing in it is a
           control that cannot be used, and the instance's own list still applies when nothing
           is picked - so an empty chooser would also imply a choice the operator never made. */
        $groups = [];
        $roles = [];
        if ($instance !== null) {
            $coursecontext = \context_course::instance($instance->courseid);

            /* The name is the PLAIN spelling, because the template renders it through a double
               stash and Mustache escapes it there. format_string()'s escape flag defaults to
               true, so leaving it out hands the escaped spelling to a sink that escapes again:
               measured on 5.1 and 5.2, a group named "R&D < Team" reached the reader as the
               literal text "R&amp;D &lt; Team". This is not the same call as the one in
               edit_form.php, which feeds a moodleform select - core renders those options with a
               triple stash and wants the escaped spelling there. */
            foreach (groups_get_all_groups($instance->courseid) as $group) {
                $groups[] = [
                    'id' => $group->id,
                    'name' => format_string($group->name, true, ['context' => $coursecontext, 'escape' => false]),
                ];
            }

            /* The same list the server allowlists the posted role against, so the control cannot
               offer anything the decision would refuse. It is empty for anybody without
               moodle/role:assign in the course, which by default is nobody who can reach this
               page - enrol/apply:manageapplications and moodle/role:assign declare the same two
               archetypes - but a custom role can hold one without the other, and a chooser with
               nothing in it is a control that cannot be used. Where it is empty the instance's
               own role applies, exactly as it does when the decider leaves the select alone.

               Unlike the group names, these are the ESCAPED spelling and the template puts them
               in a triple stash. That is not an inconsistency to tidy up: get_assignable_roles()
               returns role_fix_names() output, which is format_string()ed with no way to ask for
               anything else, so the escaped spelling is the only one core will give. It is the
               same reason core's own element-select.mustache renders option text with a triple
               stash. */
            foreach (get_assignable_roles($coursecontext) as $roleid => $rolename) {
                $roles[] = ['id' => $roleid, 'name' => $rolename];
            }
        }

        /* The bar goes into a core sticky footer, rendered here and interpolated INSIDE the
           form by the template. Only the action belongs in it: the footer is a fixed bar whose
           .sticky-footer-content carries overflow hidden, so the message textarea and the two
           choosers stay in the page body. Core's own precedent for the placement is
           grade/templates/edit_tree.mustache, and the footer is never moved in the DOM - unlike
           core/modal, its position is CSS, so the controls inside it post with the form.

           justify-content-end is passed rather than relied on. It is already the property's
           default and the constructor only overwrites it when the argument is not null, so this
           changes nothing today - it is written down because the alternative way to reach it is
           a trap: sticky_footer::add_classes() looks like it appends and does not. It builds the
           concatenation and then throws it away by assigning the argument over it, measured on
           both branches, so a later "just add a class" would silently drop this one. */
        $bar = $this->render_from_template('enrol_apply/manage_actions', [
            'togglegroup' => \enrol_apply_manage_table::TOGGLE_GROUP,
            'actionlabel' => get_string('withselectedusers'),
            'choosedots' => get_string('choosedots'),
            'golabel' => get_string('go'),
            'actions' => $actions,
        ]);
        $stickyfooter = $this->render(new \core\output\sticky_footer($bar, 'justify-content-end'));

        $context = [
            'formurl' => $manageurl->out(false),
            'sesskey' => sesskey(),
            'tablehtml' => $tablehtml,
            'hasrows' => $table->totalrows > 0,
            'stickyfooter' => $stickyfooter,
            'messagelabel' => get_string('outcomemessage', 'enrol_apply'),
            'messagehelp' => get_string('outcomemessage_help', 'enrol_apply'),
            'hasgroups' => (bool) $groups,
            'grouplabel' => get_string('decisiongroups', 'enrol_apply'),
            'grouphelp' => get_string('decisiongroups_help', 'enrol_apply'),
            'groups' => $groups,
            'hasroles' => (bool) $roles,
            'rolelabel' => get_string('decisionrole', 'enrol_apply'),
            'rolehelp' => get_string('decisionrole_help', 'enrol_apply'),
            'roledefault' => get_string('decisionroledefault', 'enrol_apply'),
            'roles' => $roles,
        ];

        if ($context['hasrows']) {
            /* core/checkbox-toggleall boots itself: the toggler template carries its own js
               block, and js_amd_inline goes through $PAGE->requires rather than the output
               buffer, so capture_table()'s ob_start() does not swallow it. This module is only
               the two gaps core leaves; see its docblock. */
            $this->page->requires->js_call_amd(
                'enrol_apply/manage',
                'init',
                [\enrol_apply_manage_table::TOGGLE_GROUP]
            );
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
     * @param array $submitted Label and value pairs the applicant typed, from fields::submitted_values().
     * @return string Rendered HTML body.
     */
    public function application_notification_mail_body(
        $course,
        $user,
        $manageurl,
        $applydescription,
        array $submitted = []
    ) {
        /* Labels and values are both the plain spelling. The template renders each through a
           double stash, so Mustache escapes them exactly once - which is both correct and
           lossless, where stripping them here would delete an applicant's answer from the
           first "<" onwards. */
        $profile = [];
        foreach ($submitted as $pair) {
            $profile[] = [
                'label' => $pair['label'],
                'value' => $pair['value'],
            ];
        }

        return $this->render_from_template('enrol_apply/application_notification', [
            'coursenamelabel' => get_string('coursename', 'enrol_apply'),
            // Plain, for the same reason as the group names above: the template double-stashes it.
            'coursename' => format_string($course->fullname, true, ['escape' => false]),
            'applicantlabel' => get_string('applyuser', 'enrol_apply'),
            'applicant' => fullname($user),
            'commentlabel' => get_string('comment', 'enrol_apply'),
            // Plain, because the template double-stashes it. See the note above on stripping.
            'comment' => $applydescription,
            'profilelabel' => get_string('user_profile', 'enrol_apply'),
            'hasprofile' => (bool) $profile,
            'profile' => $profile,
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
