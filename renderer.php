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
     * @param \enrol_apply\table\applications $table Table listing the applications.
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
     * @param \enrol_apply\table\applications $table Table listing the applications.
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
            'togglegroup' => \enrol_apply\table\applications::TOGGLE_GROUP,
            'selectedlabel' => get_string('queueselectedonpage', 'enrol_apply', 0),
            'actionlabel' => get_string('withselectedusers'),
            'choosedots' => get_string('choosedots'),
            'golabel' => get_string('go'),
            'actions' => $actions,
        ]);
        $stickyfooter = $this->render(new \core\output\sticky_footer($bar, 'justify-content-end'));

        /* The state worth surfacing is places exhausted while applications are still open: a
           manager receiving applications they have nowhere to put. Only where an instance is in
           scope - the site-wide and mentee queues span instances and have no single number.

           Rendered OUTSIDE the decision form by the template, deliberately. An instance whose
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

        /* The other exhausted state, and the one nothing on any screen could explain until now:
           the APPLICANT limit is reached, so the method refuses new applications - and the rows
           holding it against that limit may all be deferred, in which case the queue below is
           empty and the course is closed to everybody with no visible cause. A deferred row is
           freed by nothing (see capacity::deferred()), so the number is named here and
           cancelling those rows is the way out.

           Rendered outside the decision form by the template, deliberately and for the same
           reason the places notice is: this is the state whose whole symptom is an empty queue,
           so a notice inside that section would vanish exactly when it is needed. */
        $closednotice = '';
        if ($instance !== null && \enrol_apply\local\capacity::applications_closed($instance)) {
            $capacity = \enrol_apply\local\capacity::class;
            $closednotice = get_string('applicationsclosednotice', 'enrol_apply', (object) [
                'held' => $capacity::applicants($instance),
                'limit' => $capacity::applicant_limit($instance),
                'deferred' => $capacity::deferred($instance),
            ]);
        }

        /* Which rows of how many are on screen. table_sql prints a paging bar and never says
           this, so on a queue of three pages an operator reading page two has nothing telling
           them which three hundred they are looking at.
           Read AFTER capture_table(), which is what populates totalrows and currpage. */
        $from = (int) ($table->currpage * $table->pagesize) + 1;
        $showing = get_string('queueshowing', 'enrol_apply', (object) [
            'from' => $from,
            'to' => (int) min($table->totalrows, $from + $table->pagesize - 1),
            'total' => (int) $table->totalrows,
        ]);

        $context = $this->decision_controls_context($instance) + [
            'formurl' => $manageurl->out(false),
            'sesskey' => sesskey(),
            'capacityhtml' => $this->render_from_template(
                'enrol_apply/queue_capacity',
                /* The SCOPE's total, never the filtered one. The tile beside it reports deferrals
                   read straight from \enrol_apply\local\capacity and is instance-wide whatever
                   the operator has typed, so a filtered count here renders "4 awaiting decision"
                   next to "12 deferred" - arithmetically impossible, and it reads as a fault in
                   the capacity figures rather than in this line. */
                $this->queue_capacity_context($instance, $table->scope_total())
            ),
            'filtershtml' => $this->render_from_template(
                'enrol_apply/queue_filters',
                $this->queue_filters_context($table, $manageurl)
            ),
            'tablehtml' => $tablehtml,
            'showingtext' => $showing,
            'stickyfooter' => $stickyfooter,
            'hasplacesnotice' => $placesnotice !== '',
            'placesnotice' => $placesnotice,
            'hasclosednotice' => $closednotice !== '',
            'closednotice' => $closednotice,
        ];

        /* Unconditional, and it used to be gated on the queue having rows. Core's own
           core_table/dynamic init is unconditional - get_dynamic_table_html_end() runs from
           print_nothing_to_display() too - so a queue that loads with nothing in it had a live,
           refreshable table and a dead plugin module beside it: a bookmarked url whose filter
           matches nothing came back with a search box that could not be cleared.

           core/checkbox-toggleall boots itself: the toggler template carries its own js block, and
           js_amd_inline goes through $PAGE->requires rather than the output buffer, so
           capture_table()'s ob_start() does not swallow it. This module is only the gaps core
           leaves; see its docblock. */
        $this->page->requires->js_call_amd(
            'enrol_apply/manage',
            'init',
            [\enrol_apply\table\applications::TOGGLE_GROUP]
        );

        return $this->render_from_template('enrol_apply/manage', $context);
    }

    /**
     * The controls that narrow the queue, and what they currently say.
     *
     * Rendered from the TABLE's own state rather than from the request, so the page and an AJAX
     * refresh cannot disagree about which filters are applied: applications::set_filterset() is
     * the one place a filter value is read, whichever route brought it in.
     *
     * Every url here is built from url_params() minus one entry, which is what makes each chip
     * removable on a page with a single GET form - a form cannot express "submit without this one
     * field", and a link can.
     *
     * @param \enrol_apply\table\applications $table The queue, after capture_table().
     * @param moodle_url $manageurl The page's own url, carrying the filters already applied.
     * @return array Template context.
     */
    protected function queue_filters_context(\enrol_apply\table\applications $table, moodle_url $manageurl): array {
        global $CFG;

        /* ENROL_APPLY_USER_WAIT lives in the plugin's lib.php, which is not autoloaded. This
           renderer is reached from manage.php, which requires it - and from the tests, which do
           not. The fourth site in this plugin needing the same line. */
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        $params = $table->url_params();
        $scoped = array_key_exists('id', $params);
        $base = static function (array $keep) use ($params): string {
            return (new moodle_url('/enrol/apply/manage.php', array_intersect_key($params, array_flip($keep))))
                ->out(false);
        };

        $search = $table->get_search();
        $status = $table->get_status();

        /* The vocabulary comes from the table, so the select can only offer what manage.php will
           accept back and what the predicate can match - one list, three readers. The label is a
           match over literals rather than get_string('submissionstatus' . $x): the fleet bans a
           dynamic string id, and these two are the wording the operator already reads on the
           review page, so the queue does not invent a third spelling of a state that already has
           one on the row badge and one on the capacity tile. */
        $statuses = [];
        foreach (\enrol_apply\table\applications::filterable_statuses() as $value) {
            $statuses[$value] = match ($value) {
                ENROL_APPLY_USER_WAIT => get_string('submissionstatuswaiting', 'enrol_apply'),
                default => get_string('submissionstatuspending', 'enrol_apply'),
            };
        }

        $options = [[
            'value' => '',
            'label' => get_string('queuestatusany', 'enrol_apply'),
            'selected' => $status === null,
        ]];
        foreach ($statuses as $value => $label) {
            $options[] = [
                'value' => (string) $value,
                'label' => $label,
                'selected' => $status === $value,
            ];
        }

        $chips = [];
        if ($search !== '') {
            $chips[] = $this->queue_filter_chip(
                get_string('queuesearch', 'enrol_apply'),
                $search,
                $base($scoped ? ['id', 'status'] : ['status'])
            );
        }
        if ($status !== null) {
            $chips[] = $this->queue_filter_chip(
                get_string('queuefilterstatus', 'enrol_apply'),
                $statuses[$status] ?? (string) $status,
                $base($scoped ? ['id', 'search'] : ['search'])
            );
        }

        return [
            'formaction' => (new moodle_url('/enrol/apply/manage.php'))->out(false),
            'hasscope' => $scoped,
            'scopeid' => $params['id'] ?? 0,
            'searchlabel' => get_string('queuesearch', 'enrol_apply'),
            'searchvalue' => $search,
            'searchhelp' => $this->output->help_icon('queuesearch', 'enrol_apply'),
            'statuslabel' => get_string('queuefilterstatus', 'enrol_apply'),
            'statusoptions' => $options,
            'haschips' => (bool) $chips,
            'chips' => $chips,
            'clearurl' => $base($scoped ? ['id'] : []),
            'clearlabel' => get_string('queueclearfilters', 'enrol_apply'),
            'counttext' => get_string('queuefiltercount', 'enrol_apply', (object) [
                'matched' => (int) $table->totalrows,
                'total' => $table->scope_total(),
            ]),
        ];
    }

    /**
     * One removable chip.
     *
     * The PLAIN spelling of both halves: the template double stashes them, and the remove label is
     * a lang-string parameter, which the string helper escapes exactly once on its own. Escaping
     * here would show an operator who searched for "A & B" a chip reading "A &amp;amp; B".
     *
     * @param string $name What the filter is called.
     * @param string $value What it is set to.
     * @param string $removeurl Url of the same listing without this filter.
     * @return array Template context for one chip.
     */
    protected function queue_filter_chip(string $name, string $value, string $removeurl): array {
        return [
            'name' => $name,
            'value' => $value,
            'removeurl' => $removeurl,
            'removelabel' => get_string('queueremovefilter', 'enrol_apply', (object) [
                'name' => $name,
                'value' => $value,
            ]),
        ];
    }

    /**
     * The decision context above the queue: how many are waiting, and how full the method is.
     *
     * **Not built from allow_apply().** That method is the applicant's gate and mixes a question
     * about the METHOD with one about a PERSON - its cohort clause asks whether *this user* may
     * apply - so a manager outside a restricted cohort would be told the method is closed when it
     * is open to everybody it is meant for. What this reports is the instance's own state: whether
     * it is enabled, whether it takes new enrolments, its dates, and its applicant limit. The two
     * must agree about those four and deliberately part company on the fifth; if allow_apply()
     * ever grows another instance-level reason, it belongs here as well.
     *
     * The counts come from \enrol_apply\local\capacity, which is where every other surface reads
     * them, so the queue header and the review page cannot report different numbers.
     *
     * @param stdClass|null $instance Enrol instance the queue is scoped to, null when it spans them.
     * @param int $awaiting How many applications the queue is listing.
     * @return array Context for the enrol_apply/queue_capacity template.
     */
    protected function queue_capacity_context($instance, int $awaiting): array {
        $capacity = \enrol_apply\local\capacity::class;

        $context = [
            'tiles' => [[
                'value' => (string) $awaiting,
                'label' => get_string('queueawaiting', 'enrol_apply'),
            ]],
            'meters' => [],
            'hasstatus' => false,
        ];

        /* Everything below this line is about ONE enrolment method. The site-wide and mentee
           queues span methods, each with its own limits and its own dates, so there is no single
           number to report - and reporting one course's would be worse than reporting none. They
           get the count of what is waiting and nothing else, which is still the thing an operator
           opened the page to see. */
        if ($instance === null) {
            return $context;
        }

        $context['tiles'][] = [
            'value' => (string) $capacity::deferred($instance),
            'label' => get_string('queuedeferred', 'enrol_apply'),
        ];

        $places = $capacity::places($instance);
        if ($places > 0) {
            $taken = $capacity::places_taken($instance);
            $context['meters'][] = [
                'value' => get_string('reviewofmany', 'enrol_apply', (object) [
                    'taken' => $taken,
                    'total' => $places,
                ]),
                'label' => get_string('queueplacestaken', 'enrol_apply'),
                'percent' => self::meter_percent($taken, $places),
                'warn' => false,
            ];
        }

        $limit = $capacity::applicant_limit($instance);
        // Counted ONCE. The meter, the open/closed test and the room-left sentence all want it.
        $held = $limit > 0 ? $capacity::applicants($instance) : 0;
        if ($limit > 0) {
            $context['meters'][] = [
                'value' => get_string('reviewofmany', 'enrol_apply', (object) [
                    'taken' => $held,
                    'total' => $limit,
                ]),
                'label' => get_string('reviewapplicants', 'enrol_apply'),
                'percent' => self::meter_percent($held, $limit),
                /* Warned at four fifths rather than at the limit, because the limit is the point
                   at which the method stops accepting applications and nobody can do anything
                   about it any more. The bar is there to be seen BEFORE that. */
                'warn' => $held * 5 >= $limit * 4,
            ];
        }

        $enddate = (int) ($instance->enrolenddate ?? 0);
        $startdate = (int) ($instance->enrolstartdate ?? 0);
        $now = time();
        $open = $instance->status == ENROL_INSTANCE_ENABLED
            && !empty($instance->customint6)
            && !($startdate > 0 && $startdate > $now)
            && !($enddate > 0 && $enddate < $now)
            /* capacity::applications_closed() inlined, and it must keep agreeing with it: that
               method is `$limit !== 0 && applicants() >= $limit`, and applicant_limit() clamps a
               negative to 0, so `$limit > 0` and its `$limit !== 0` are the same test. Inlined
               only to stop a third identical COUNT over {user_enrolments} in one render - $held
               is that count, taken once above. If that method grows a clause, this grows it. */
            && !($limit > 0 && $held >= $limit);

        $remaining = $limit > 0 ? $limit - $held : 0;

        /* array_merge and never the + operator. `+` keeps the LEFT side on a duplicate key, and
           $context already carries 'hasstatus' => false from the site-wide default above - so the
           + form silently kept the false and the whole status block never rendered, on every
           instance-scoped queue. Found by looking at the page; no test asserted the block, and
           the slice that documented this exact trap (U6, gate BW) landed earlier the same day. */
        return array_merge($context, [
            'hasstatus' => true,
            'statuslabel' => get_string('queuestatus', 'enrol_apply'),
            'isopen' => $open,
            'statustext' => $open
                ? get_string('queueapplicationsopen', 'enrol_apply')
                : get_string('queueapplicationsclosed', 'enrol_apply'),
            'hasclosing' => $open && $enddate > 0,
            'closingtext' => $enddate > 0
                ? get_string('queuecloseson', 'enrol_apply', userdate($enddate, get_string('strftimedate', 'langconfig')))
                : '',
            /* Named only while it is still true and still close. A limit ten applications away is
               not news, and one already reached is said by the status badge and by the notice
               above the table rather than three times over. */
            'hasremaining' => $open && $limit > 0 && $remaining > 0 && $remaining * 5 <= $limit,
            'remainingtext' => get_string('queueremaining', 'enrol_apply', $remaining),
        ]);
    }

    /**
     * A meter's width, as a whole percentage that never leaves the bar.
     *
     * Clamped at 100 because both numbers this is called with can legitimately exceed their own
     * limit: places_taken() counts approved enrolments and an administrator can enrol past the
     * cap by hand, and the applicant limit can be lowered under applications already held.
     *
     * @param int $value The count.
     * @param int $total The limit it sits against, always greater than zero here.
     * @return int Whole percentage between 0 and 100.
     */
    protected static function meter_percent(int $value, int $total): int {
        return (int) min(100, max(0, round($value * 100 / $total)));
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
     * @param string $message What the operator had already typed, empty on the ordinary path.
     * @param string $note The decision note they had already typed, empty on the ordinary path.
     * @return array Context for the partial.
     */
    protected function decision_controls_context($instance, $message = '', $note = ''): array {
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
            /* Non-empty only on the way back from the cancel confirmation, so the operator does
               not lose what they wrote by hesitating. Plain spelling: the template double
               stashes it, as it does everything else it renders itself. */
            'messagevalue' => $message,
            /* The decider's own note. Offered on every decision rather than on deferral alone,
               and NOT gated on the course capability the two choosers above are: the choosers
               change what the approval DOES, while the note only records why it was taken - a
               mentor deciding one application is as entitled to say why as anybody else, and a
               trail with a hole in it wherever a mentor decided would be worth less than one
               without the field. */
            'notelabel' => get_string('decisionnote', 'enrol_apply'),
            'notehelp' => get_string('decisionnote_help', 'enrol_apply'),
            'notevalue' => $note,
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
     * The applicant's identifying details, as this reader may see them.
     *
     * One gate serving the identity line AND, through visible_keys() below, the snapshot panel,
     * because the two used to disagree on the same page to the same reader: the e-mail address
     * had a row of its own and was printed unconditionally, while the snapshot beside it withheld
     * identity fields from a mentor. One panel hid what the other showed.
     *
     * The e-mail is now one identity field among the rest rather than a row of its own, which is
     * a real behaviour change and the owner's decision: on a site whose `showuseridentity` does
     * not name it, it no longer appears here at all. That is what core's own participants page
     * does with the same configuration, and the profile link beside the name is the route to
     * contact details for a reader entitled to them.
     *
     * The COURSE context, not the page's: queue::require_review_access() can return the
     * applicant's own USER context on the mentor path, and identity resolved against that would
     * be answering about the wrong thing. It costs a mentor the identity fields, which is the
     * stricter reading of a genuine question and the one the report already took.
     *
     * Values go out PLAIN and the template double stashes them. s() is not used here for the
     * same reason the snapshot does not: the sink escapes, and escaping twice shows the entities.
     *
     * @param stdClass $applicant Applicant user record.
     * @param \context_course $coursecontext Course the application was made to.
     * @return array Template context: hasidentity and one entry per field this reader may see.
     */
    protected function identity_context($applicant, $coursecontext): array {
        $rows = [];
        $values = \enrol_apply\local\identity::values($coursecontext, (int) $applicant->id);
        foreach ($values as $field => $value) {
            $rows[] = [
                /* Labelled, because these run together on one line and several of them are
                   opaque without one: a bare "2026-0042" beside a bare "jsa" tells a screen
                   reader nothing, and tells a sighted reader little more. */
                'label' => \core_user\fields::get_display_name($field),
                'value' => $value,
            ];
        }

        return [
            'hasidentity' => (bool) $rows,
            'identity' => $rows,
        ];
    }

    /**
     * The decision that produced the state this application is in, when a colleague took one.
     *
     * A deferred application is the one case where the reader is looking at something somebody
     * else already decided, and the page used to say only "On the waiting list" - not who, not
     * when, and not the note they wrote to the applicant. All of it comes from columns
     * queue::application() already selects off a table it already joins, so this costs nothing.
     *
     * Only for a DEFERRED application. A pending one has no decision to describe, and for the
     * other two states the page is not reachable at all - queue::application() returns null once
     * an application stops awaiting a decision.
     *
     * The decider's name is read live and is not masked: it is a member of staff acting in this
     * course, not the applicant, and the same name is already on the report. The message is the
     * one written TO the applicant, so it is shown as written.
     *
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @return array Template context: hasdecision and the sentence describing it.
     */
    protected function decision_context($application): array {
        if ((int) $application->status !== ENROL_APPLY_USER_WAIT || empty($application->timedecided)) {
            return ['hasdecision' => false];
        }

        $decider = empty($application->decidedby)
            ? null
            : \core_user::get_user((int) $application->decidedby, '*', IGNORE_MISSING);

        $message = trim((string) ($application->outcomemessage ?? ''));
        $note = trim((string) ($application->decisionnote ?? ''));

        return [
            'hasdecision' => true,
            'decisionlabel' => get_string('reviewdecision', 'enrol_apply'),
            'decision' => $decider
                ? get_string('reviewdeferredby', 'enrol_apply', (object) [
                    'who' => fullname($decider),
                    'when' => userdate((int) $application->timedecided, get_string('strftimedatetimeshort', 'langconfig')),
                ])
                : get_string('reviewdeferredon', 'enrol_apply', userdate(
                    (int) $application->timedecided,
                    get_string('strftimedatetimeshort', 'langconfig')
                )),
            'hasdecisionmessage' => $message !== '',
            /* Already escaped, with the decider's own line breaks kept, exactly as the
               applicant's comment is - and for the same reason. */
            'decisionmessage' => format_text($message, FORMAT_PLAIN),
            /* The note the last decider left for whoever reads this next, and the reason the
               column exists. It is shown here rather than pre-filled into the note box below:
               the writer clears on empty on purpose, so a pre-filled box would carry one
               decision's reason silently into the next - which is the defect that clearing was
               introduced to fix for the outcome message.

               No capability of its own. This whole panel is already behind the gate that opens
               the review page, and the note says less about the applicant than the comment
               printed a few lines further down does. */
            'hasdecisionnote' => $note !== '',
            'decisionnotelabel' => get_string('decisionnote', 'enrol_apply'),
            'decisionnote' => format_text($note, FORMAT_PLAIN),
        ];
    }

    /**
     * What else this applicant has applied for in this course.
     *
     * Gated on enrol/apply:viewreports, which is deliberately narrower than the capability that
     * opens this page: the prior applications are the same disclosure the report exists to
     * control - what somebody applied for and what was decided - and an editing teacher holding
     * only manageapplications is not granted it by archetype. A reader without it sees no panel
     * at all rather than an empty one, because a heading that appears only when there IS history
     * is itself a disclosure.
     *
     * Each row's wording comes from the REPORT's own outcome formatter rather than the record's
     * bare status, so the two surfaces cannot describe the same record differently. That matters
     * here specifically: a record says APPROVED for ever, while the enrolment it names may since
     * have been suspended or removed by a route this plugin never sees.
     *
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @param \context_course $coursecontext Course the application was made to.
     * @return array Template context: hashistory, its label, and one entry per prior application.
     */
    protected function history_context($application, $coursecontext): array {
        if (!has_capability('enrol/apply:viewreports', $coursecontext)) {
            return ['hashistory' => false, 'history' => []];
        }

        $formatter = \enrol_apply\reportbuilder\local\formatters\submission::class;
        $priors = \enrol_apply\local\queue::prior_applications(
            (int) $application->courseid,
            (int) $application->userid,
            (int) ($application->submissionid ?? 0)
        );

        $rows = [];
        foreach ($priors as $prior) {
            $rows[] = [
                'applied' => userdate((int) $prior->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'outcome' => $formatter::outcome($prior->status, $prior),
            ];
        }

        return [
            'hashistory' => (bool) $rows,
            'historylabel' => get_string('reviewhistory', 'enrol_apply'),
            'history' => $rows,
        ];
    }

    /**
     * How much room the enrolment method has left.
     *
     * The four numbers answer two different questions and must never be mixed: applicants counts
     * every non-expired row - pending, deferred and approved alike - because each of those people
     * is in the pipeline, while places counts ACTIVE rows only. The gap between them is
     * overbooking, which is the point in a plugin where approval is discretionary.
     *
     * Shown as a neutral readout rather than the queue's warning: the queue warns only when the
     * places are gone, which is the right shape for a listing somebody is sweeping, while a
     * single decision wants the number whatever it is.
     *
     * **Gated on the COURSE capability, which is stricter than the page**, and for the same
     * reason the group and role choosers a few lines above are: these are the enrolment method's
     * own settings and its enrolment counts, and a mentor reaches this page through the
     * applicant's user context holding nothing in the course at all. Telling them how many people
     * are enrolled there, and what the method's configured limits are, is a disclosure their
     * delegation does not carry - they are trusted with one applicant, not with the shape of the
     * course. What they lose is context for their decision; the instance's own limits still apply
     * to it either way, exactly as the groups and the role do.
     *
     * @param stdClass $instance Enrol instance the application belongs to.
     * @return array Template context for the capacity panel.
     */
    protected function capacity_context($instance): array {
        $coursecontext = \context_course::instance($instance->courseid);
        if (!has_capability('enrol/apply:manageapplications', $coursecontext)) {
            return ['hascapacity' => false];
        }

        $capacity = \enrol_apply\local\capacity::class;
        $places = $capacity::places($instance);
        $limit = $capacity::applicant_limit($instance);
        $nolimit = get_string('reviewnolimit', 'enrol_apply');

        return [
            'hascapacity' => true,
            'capacitylabel' => get_string('reviewcapacity', 'enrol_apply'),
            'placeslabel' => get_string('places', 'enrol_apply'),
            'places' => $places > 0
                ? get_string('reviewofmany', 'enrol_apply', (object) [
                    'taken' => $capacity::places_taken($instance),
                    'total' => $places,
                ])
                : $nolimit,
            /* Not the setting's own label. "Maximum applicants: 35 of 40" reads as though 35
               were the maximum; this row reports how many the method is HOLDING against it. */
            'applicantslabel' => get_string('reviewapplicants', 'enrol_apply'),
            'applicants' => $limit > 0
                ? get_string('reviewofmany', 'enrol_apply', (object) [
                    'taken' => $capacity::applicants($instance),
                    'total' => $limit,
                ])
                : $nolimit,
            /* How many of those applications are deferred, which is a SUBSET of the applicants
               row above and not a fourth limit. It is here because a deferred row counts
               against the applicant cap for ever and nothing frees it - see capacity::deferred()
               - so a method refusing new applications with an empty queue is otherwise
               unexplainable from any screen. A bare count, not "n of m": there is no limit on
               deferrals to report it against. */
            'deferredlabel' => get_string('reviewdeferred', 'enrol_apply'),
            'deferred' => (string) $capacity::deferred($instance),
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
     *        constructor parameter was making. It renders as nothing only when there is no
     *        neighbour AND no queue to go back to; a queue of one still gets the way back, which
     *        is exactly when a reader most needs it.
     * @param string $message What the operator had already typed, carried back from the cancel
     *        confirmation so that hesitating does not discard it.
     * @param string $note The decision note they had already typed, carried back the same way.
     * @return void
     */
    public function review_page(
        $application,
        $applicant,
        $instance,
        $manageurl,
        $navigation,
        $message = '',
        $note = ''
    ) {
        echo $this->header();
        /* No heading here. Core already renders the applicant's name as the page's own <h1>
           from $PAGE->set_heading(), and $this->heading() defaults to level 2 - so the page
           opened with the same name twice, at the same visual size, with the whole secondary
           navigation between them. The <h2>s now belong to the panels below. */

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
        echo $this->review_form($application, $applicant, $instance, $manageurl, $message, $note);
        echo $this->footer();
    }

    /**
     * The single-application decision form.
     *
     * The POST is byte for byte the one the queue makes - formaction, userenrolments[] and the
     * session key - so every guard manage.php applies to a queue decision applies here unchanged.
     *
     * It is no longer true that the handler needs NO branch of its own: the destructive decision
     * is intercepted on this path and asks before acting, which the queue's bulk equivalent does
     * not. That is the one branch, and it is about confirmation rather than authorisation - the
     * second request arrives on the same contract and passes the same guards.
     *
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @param stdClass $applicant Applicant user record.
     * @param stdClass $instance Enrol instance the application belongs to.
     * @param moodle_url $manageurl Url the decision form posts back to.
     * @param string $message What the operator had already typed, carried back from the cancel
     *        confirmation so that hesitating does not discard it.
     * @param string $note The decision note they had already typed, carried back the same way.
     * @return string Rendered markup.
     */
    public function review_form($application, $applicant, $instance, $manageurl, $message = '', $note = '') {
        $waiting = (int) $application->status === ENROL_APPLY_USER_WAIT;

        $coursecontext = \context_course::instance($application->courseid);

        $context = $this->decision_controls_context($instance, $message, $note)
            + $this->snapshot_context($application)
            + $this->identity_context($applicant, $coursecontext)
            + $this->history_context($application, $coursecontext)
            + $this->decision_context($application)
            + $this->capacity_context($instance)
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
            /* No e-mail row. It used to be printed here unconditionally while the snapshot panel
               beside it masked identity fields from the same reader - one panel hiding what the
               other showed. It is now one identity field among the rest, so on a site whose
               showuseridentity does not name it, it does not appear. See identity_context(). */
            'profileurl' => (new moodle_url('/user/view.php', [
                'id' => (int) $applicant->id,
                'course' => (int) $application->courseid,
            ]))->out(false),
            'profilelabel' => get_string('viewprofile'),
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
            /* Order is the layout: the bar spreads these with justify-content-between, so the
               destructive decision sits at the far edge and cannot be mis-clicked for the one
               beside it, and the approval sits where the eye finishes. Cancel also gets its own
               style, because it is the only one of the three that destroys something -
               cancel_enrolment() unenrols, taking the row and the applicant's comment with it -
               and it used to look exactly like Defer, which is fully reversible.
               btn-outline-danger is a button variant rather than a bare bg-* utility, which is
               what keeps bootstrap_compat_test's contrast rule satisfied. */
            'actions' => [
                ['value' => 'cancel', 'label' => get_string('reviewcancel', 'enrol_apply'), 'style' => 'btn-outline-danger'],
                ['value' => 'wait', 'label' => get_string('reviewwait', 'enrol_apply'), 'style' => 'btn-secondary'],
                ['value' => 'confirm', 'label' => get_string('reviewconfirm', 'enrol_apply'), 'style' => 'btn-primary'],
            ],
        ];

        /* The decisions go into a core sticky footer, rendered here and interpolated INSIDE the
           form by the template - its placement is CSS and never a DOM move, so its submits post
           with everything else. Only the buttons: the bar is a fixed box whose content area
           clips with no scrollbar, so the message box and the two choosers stay in the page body,
           exactly as they do on the queue.

           The spreading class goes on the PARTIAL's own row, not through the footer: the footer
           applies its classes to a content area whose only child is that row, so
           justify-content-between there had nothing to spread and the three buttons packed
           together at the left - measured, 8px apart, with the destructive one nowhere near the
           far edge it was supposed to be pushed to. Nothing is passed to the constructor now, and
           add_classes() is still not called: it builds its concatenation and then assigns the
           argument over it, so a later "just add a class" would silently drop whatever was
           there. */
        $bar = $this->render_from_template('enrol_apply/review_actions', [
            'actions' => $context['actions'],
        ]);
        $context['stickyfooter'] = $this->render(new \core\output\sticky_footer($bar));

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
