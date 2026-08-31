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

        /* The state worth surfacing is places exhausted while applications are still open: a
           manager receiving applications they have nowhere to put. Only where an instance is in
           scope - the site-wide and mentee queues span instances and have no single number.

           Rendered OUTSIDE the hasrows section by the template, deliberately. An instance whose
           applicant limit is reached has an EMPTY queue, which is exactly the moment the
           manager most needs to be told why; inside the section the notice would vanish in the
           state it exists for. */
        $placesnotice = '';
        if ($instance !== null && \enrol_apply\local\capacity::places_full($instance)) {
            $placesnotice = get_string(
                'placesfull',
                'enrol_apply',
                \enrol_apply\local\capacity::places($instance)
            );
        }

        $context = $this->decision_controls_context($instance) + [
            'formurl' => $manageurl->out(false),
            'sesskey' => sesskey(),
            'tablehtml' => $tablehtml,
            'hasrows' => $table->totalrows > 0,
            'stickyfooter' => $stickyfooter,
            'hasplacesnotice' => $placesnotice !== '',
            'placesnotice' => $placesnotice,
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
     * Everything the enrol_apply/decision_controls partial needs.
     *
     * Shared by the queue and by the single-application review page, so that the two decision
     * surfaces cannot offer different things. Each chooser is offered only where it has
     * something to offer: a control with nothing in it cannot be used, and the instance's own
     * list still applies when nothing is picked, so an empty chooser would also imply a choice
     * the operator never made.
     *
     * Gated on the capability in the COURSE, which is stricter than the gate on the page that
     * calls it. A mentor reaches the review page through the applicant's own user context and
     * holds nothing in the course at all, so offering them the group chooser would list every
     * group name in a course they cannot open - groups_get_all_groups() applies no capability
     * check of its own, unlike get_assignable_roles(), which self-gates and would have come
     * back empty for them anyway. The instance's own groups and role still apply to their
     * decision; what they lose is the ability to override them, which is the level of trust
     * that delegation carries.
     *
     * @param stdClass|null $instance Enrol instance the decision belongs to, null when unknown.
     * @return array Context for the partial.
     */
    protected function decision_controls_context($instance): array {
        $groups = [];
        $roles = [];

        $coursecontext = $instance === null ? null : \context_course::instance($instance->courseid);
        if ($coursecontext && has_capability('enrol/apply:manageapplications', $coursecontext)) {
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

               Unlike the group names, these go to the template in the ESCAPED spelling and are
               rendered through a triple stash, as core's own element-select.mustache does. The
               format_string() below is what makes that safe, and it is not belt and braces: core
               hands back a MIXED list. role_get_name() escapes a role whose role.name column is
               set, and returns a bare get_string() for one whose column is empty - which is every
               role a stock site ships, measured on m502, where all eight return an empty name
               while this site's own custom role returns "R&amp;D coordinator". A triple stash
               over that list would emit the lang-string half raw.

               The call is idempotent on the half that is already escaped, because the ampersand
               rule skips an existing entity, so normalising costs nothing and removes the
               asymmetry rather than documenting it. */
            foreach (get_assignable_roles($coursecontext) as $roleid => $rolename) {
                $roles[] = [
                    'id' => $roleid,
                    'name' => format_string($rolename, true, ['context' => $coursecontext]),
                ];
            }
        }

        return [
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
    }

    /**
     * The details the applicant submitted with this application, as the reader may see them.
     *
     * Read from the frozen snapshot the applicant's own submission wrote, NOT recomputed. In
     * particular this must never go through \enrol_apply\local\diff::compute(): that re-resolves
     * the field set from the LIVE enrol instance and re-classifies it against the current user,
     * so a field the teacher has since stopped asking for, or one the applicant may no longer
     * edit, silently vanishes from a record of what was actually submitted. The snapshot carries
     * its own labels for the same reason - they are what the applicant saw when they typed.
     *
     * Nothing here reads the applicant's LIVE profile, and that is a security boundary rather
     * than a scoping choice. An earlier version of this method showed "what the profile says
     * now" beside each row, by passing the stored key to fields::current_value(). That key comes
     * out of userinfodata, which restore_enrol_apply_plugin writes verbatim from an archive this
     * site did not produce, and current_value() dereferences any {user} column an "s_" key names
     * with no allowlist of its own - the DENY list that exists to keep s_password, s_secret,
     * s_email and s_idnumber out of this plugin governs only the WRITE path. Measured on m502
     * with a crafted envelope: the panel rendered the applicant's password hash. The reader for
     * whom visible_keys() returns ALL_FIELDS - any teacher or manager - skipped the key filter
     * entirely, so the row was not "already judged visible" in any sense. The custom-field
     * branch was as bad: a c_<id> key reads {user_info_data} directly, past every
     * PROFILE_VISIBLE_* gate core applies to its own profile page.
     *
     * The frozen record needs none of that, and the Report Builder surface it inherits its
     * masking from does not read the live profile either. So the two surfaces onto this record
     * now do the same thing, which was the point of sharing the rule.
     *
     * Masked with the report's own rule, on the COURSE context. Note what that costs: a MENTOR
     * holds nothing in the course, so they see the name fields only, even where their own mentor
     * role grants the identity capability in the applicant's user context. That is the stricter
     * reading of a genuine question rather than an obviously right answer, and it is the one the
     * report already took.
     *
     * Every value is the PLAIN spelling and the template double stashes it, so each is escaped
     * exactly once. Not format_string(), whose strip_tags() would delete a restored value from
     * the first "<" onwards. A stored value CAN contain newlines - a textarea custom field is
     * offerable - so the template carries the same white-space rule the report's own cell does
     * rather than converting them to markup.
     *
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @return array Template context: hassnapshot, its label, and one row per visible field.
     */
    protected function snapshot_context($application): array {
        $formatter = \enrol_apply\reportbuilder\local\formatters\submission::class;
        $entries = \enrol_apply\local\submission::read_snapshot($application->snapshot ?? null);
        $visible = $formatter::visible_keys(\context_course::instance($application->courseid));

        $rows = [];
        foreach ($entries as $entry) {
            if ($visible !== $formatter::ALL_FIELDS && !in_array($entry['key'], $visible, true)) {
                /* Withheld from every row rather than only from the rows holding a value: a
                   marker that appears exactly where there is data is a presence oracle, which is
                   the rule the report's own formatter states and this surface inherits. */
                continue;
            }

            $rows[] = [
                'label' => $entry['label'],
                'value' => $entry['value'],
            ];
        }

        return [
            'hassnapshot' => (bool) $rows,
            'snapshotlabel' => get_string('submittedprofile', 'enrol_apply'),
            'snapshot' => $rows,
        ];
    }

    /**
     * Render one application, with the controls to decide it.
     *
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @param stdClass $applicant Applicant user record.
     * @param stdClass $instance Enrol instance the application belongs to.
     * @param moodle_url $manageurl Url the decision form posts back to.
     * @param \enrol_apply\output\application_navigation $navigation Links to the neighbouring
     *        applications. Required, and not defaulted to null: manage.php always resolves a
     *        pair before it renders, so a "not being walked" mode would be a parameter claiming
     *        a state the plugin never produces - the same claim the queue table's own removed
     *        constructor parameter was making. The navigation renders as nothing on its own
     *        when there is no neighbour either way, which is what a queue of one needs.
     * @return void
     */
    public function review_page($application, $applicant, $instance, $manageurl, $navigation) {
        echo $this->header();
        echo $this->heading(fullname($applicant));
        /* Above the form, where core puts a tertiary navigation bar, and not below it:
           the last control an operator reads before a decision should be the decision.

           render() resolves the template from the CLASS NAME - renderer_base::render()
           falls back to "<component>/<class>" for any templatable with no render_ method,
           so this reaches enrol_apply/application_navigation with nothing else declared.
           A render_application_navigation() method here is deliberately not written: adding
           it changed no byte of any page, measured by renaming it under the whole suite,
           which stayed green - so it would be a method whose only claim was one no test in
           this repository could hold. Nor does its absence cost a theme anything, which an
           earlier draft of this comment got backwards: render() dispatches on
           method_exists($this, ...) against the CONCRETE renderer, so a theme's own
           enrol_apply renderer subclass can declare that method and have it called with
           nothing declared here at all. The coupling that IS load bearing is the class name
           to the template file name, and renaming either without the other throws, which
           the two rendering tests hold. */
        echo $this->render($navigation);
        echo $this->review_form($application, $applicant, $instance, $manageurl);
        echo $this->footer();
    }

    /**
     * The single-application decision form.
     *
     * The POST is byte for byte the one the queue makes - formaction, userenrolments[] and the
     * session key - so manage.php's handler needs no branch of its own and every guard it
     * applies to a queue decision applies here unchanged.
     *
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @param stdClass $applicant Applicant user record.
     * @param stdClass $instance Enrol instance the application belongs to.
     * @param moodle_url $manageurl Url the decision form posts back to.
     * @return string Rendered markup.
     */
    public function review_form($application, $applicant, $instance, $manageurl) {
        $waiting = (int) $application->status === ENROL_APPLY_USER_WAIT;

        $context = $this->decision_controls_context($instance)
            + $this->snapshot_context($application)
            + [
            'formurl' => $manageurl->out(false),
            'sesskey' => sesskey(),
            'userenrolmentid' => (int) $application->id,
            'courselabel' => get_string('course'),
            // Plain, for the same reason as the group names: the template double-stashes it.
            'coursename' => format_string($application->coursename, true, [
                'context' => \context_course::instance($application->courseid),
                'escape' => false,
            ]),
            'courseurl' => (new moodle_url('/course/view.php', ['id' => $application->courseid]))->out(false),
            'emaillabel' => get_string('email'),
            'email' => $applicant->email,
            'appliedlabel' => get_string('applydate', 'enrol_apply'),
            'applied' => userdate((int) $application->applydate, get_string('strftimedatetimeshort', 'langconfig')),
            'statuslabel' => get_string('submissionstatus', 'enrol_apply'),
            'status' => $waiting
                ? get_string('outcomewaiting', 'enrol_apply')
                : get_string('outcomeawaiting', 'enrol_apply'),
            /* Plain, not escaped: review.mustache renders this through a DOUBLE stash, so
               Mustache escapes it there and the escaped spelling would show the entities. This
               is the one of the three label sinks that differs, which is why the helper takes a
               flag rather than deciding for everybody. */
            'commentlabel' => \enrol_apply\local\commentlabel::custom($instance, false),
            'hascomment' => trim((string) $application->applycomment) !== '',
            /* Escaped exactly once, and with the line breaks the applicant typed. A double
               stash alone would escape correctly and then render every paragraph as one run,
               on the one page whose purpose is reading what they wrote; the queue's own cell
               has always used format_text(FORMAT_PLAIN), which escapes and converts newlines
               and nothing else. So this arrives ALREADY escaped and the template triple
               stashes it, which is the opposite of every other value on that template and is
               why it is the only one flagged there.

               Not format_string(): that runs strip_tags(), which would delete an applicant's
               answer from the first "<" onwards. A restore is the route by which such a value
               reaches the column - it writes the comment verbatim out of a foreign archive. */
            'comment' => format_text((string) $application->applycomment, FORMAT_PLAIN),
            'nocomment' => get_string('nocomment', 'enrol_apply'),
            /* Singular labels of their own, not the queue's. btnconfirm and its siblings read
               "Confirm requests", which is right above a list and wrong above one application -
               and on this page the button IS the decision, so its label is the last thing the
               operator reads before an applicant is enrolled or unenrolled. */
            'actions' => [
                ['value' => 'confirm', 'label' => get_string('reviewconfirm', 'enrol_apply'), 'primary' => true],
                ['value' => 'wait', 'label' => get_string('reviewwait', 'enrol_apply'), 'primary' => false],
                ['value' => 'cancel', 'label' => get_string('reviewcancel', 'enrol_apply'), 'primary' => false],
            ],
        ];

        return $this->render_from_template('enrol_apply/review', $context);
    }

    /**
     * Render the page shown when there is no application to decide.
     *
     * Reached by a link that has gone stale, which on this page is the ordinary case rather
     * than the edge one: an application is decided exactly once and the url that reviewed it
     * outlives the decision. It says the same thing whether the application was decided, the
     * enrolment was removed, or the id never named anything - the reader cannot act on the
     * difference, and telling them apart would answer "does user enrolment N exist?" for
     * anybody who asks.
     *
     * @param moodle_url $backurl Where to send the reader instead.
     * @return void
     */
    public function no_application_page($backurl) {
        echo $this->header();
        echo $this->heading(get_string('confirmusers', 'enrol_apply'));
        echo $this->notification(get_string('applicationgone', 'enrol_apply'), 'info');
        echo $this->render_from_template('core/single_button', (new \single_button(
            $backurl,
            get_string('backtoapplications', 'enrol_apply'),
            'get'
        ))->export_for_template($this));
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
