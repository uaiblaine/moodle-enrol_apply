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

        /* The action bar goes into a core sticky footer that the template interpolates inside
           the form: its position is CSS and it is never moved in the DOM, so its controls post
           with the form, as in core's grade/templates/edit_tree.mustache. Only the action belongs
           there: .sticky-footer-content has overflow hidden, so the message textarea and the two
           choosers stay in the page body.

           justify-content-end is already the default; it is passed through the constructor
           because sticky_footer::add_classes() replaces the classes rather than appending to
           them, so any further class must be passed here too. */
        $bar = $this->render_from_template('enrol_apply/manage_actions', [
            'togglegroup' => \enrol_apply\table\applications::TOGGLE_GROUP,
            'selectedlabel' => get_string('queueselectedonpage', 'enrol_apply', 0),
            'actionlabel' => get_string('withselectedusers'),
            'choosedots' => get_string('choosedots'),
            'golabel' => get_string('go'),
            'actions' => $actions,
        ]);
        $stickyfooter = $this->render(new \core\output\sticky_footer($bar, 'justify-content-end'));

        /* Every place is taken. Shown whether or not applications are still open, and whether
           or not the queue has rows: the state belongs to the method, not to the rows on
           screen, and it warns without blocking (see capacity::places_full()). Only where an
           instance is in scope: the site-wide and mentee queues span instances and have no
           single number. */
        $placesnotice = '';
        if ($instance !== null && \enrol_apply\local\capacity::places_full($instance)) {
            $placesnotice = get_string(
                'placesfull',
                'enrol_apply',
                \enrol_apply\local\capacity::places($instance)
            );
        }

        /* The applicant limit is reached, so the method refuses new applications. The rows
           holding it may all be deferred, leaving the queue empty and the course closed with no
           visible cause; a deferred row is freed by nothing (see capacity::deferred()), so the
           deferred count is named and cancelling those rows is the way out. Rendered regardless
           of rows for the same reason as the places notice. */
        $closednotice = '';
        if ($instance !== null && \enrol_apply\local\capacity::applications_closed($instance)) {
            $capacity = \enrol_apply\local\capacity::class;
            $closednotice = get_string('applicationsclosednotice', 'enrol_apply', (object) [
                'held' => $capacity::applicants($instance),
                'limit' => $capacity::applicant_limit($instance),
                'deferred' => $capacity::deferred($instance),
            ]);
        }

        /* Which rows of how many are on screen, which table_sql's paging bar never says.
           Read after capture_table(), which is what populates totalrows and currpage.

           A page holding no row has no range to name: an empty queue would read "Showing 1-0 of
           0", and a page number past the end of a narrowed queue (core does not clamp it) would
           read "Showing 101-4 of 4". The text is left empty then and the template hides the line;
           redrawShowing() in amd/src/manage.js applies the same rule after an AJAX refresh. */
        $from = (int) ($table->currpage * $table->pagesize) + 1;
        $to = (int) min($table->totalrows, $from + $table->pagesize - 1);
        $showing = $from > $to ? '' : get_string('queueshowing', 'enrol_apply', (object) [
            'from' => $from,
            'to' => $to,
            'total' => (int) $table->totalrows,
        ]);

        $context = $this->decision_controls_context($instance) + [
            'descriptiontext' => get_string('confirmusers_desc', 'enrol_apply'),
            'formurl' => $manageurl->out(false),
            'sesskey' => sesskey(),
            'capacityhtml' => $this->render_from_template(
                'enrol_apply/queue_capacity',
                /* The scope's total, never the filtered one: the deferral tile beside it is read
                   from \enrol_apply\local\capacity and ignores the filters, so a filtered count
                   would render impossible pairs such as "4 awaiting decision" beside "12 deferred". */
                $this->queue_capacity_context($instance, $table->scope_total())
            ),
            'filtershtml' => $this->render_from_template(
                'enrol_apply/queue_filters',
                $this->queue_filters_context($table, $manageurl)
            ),
            'tablehtml' => $tablehtml,
            'hasshowing' => $showing !== '',
            'showingtext' => $showing,
            'stickyfooter' => $stickyfooter,
            'hasplacesnotice' => $placesnotice !== '',
            'placesnotice' => $placesnotice,
            'hasclosednotice' => $closednotice !== '',
            'closednotice' => $closednotice,
        ];

        /* Unconditional, like core's own core_table/dynamic init, which
           get_dynamic_table_html_end() emits even from print_nothing_to_display(): a queue that
           loads empty still has a live, refreshable table, and its filters must still work.

           core/checkbox-toggleall boots itself: the toggler template carries its own js block, and
           js_amd_inline goes through $PAGE->requires rather than the output buffer, so
           capture_table()'s ob_start() does not swallow it. This module only fills the gaps core
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
     * Rendered from the table's own state rather than from the request, so the page and an AJAX
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

        /* ENROL_APPLY_USER_WAIT lives in the plugin's lib.php, which is not autoloaded; manage.php
           requires it, but the tests reach this renderer without it. */
        require_once($CFG->dirroot . '/enrol/apply/lib.php');

        $params = $table->url_params();
        $scoped = array_key_exists('id', $params);
        /* The url of this same listing without one filter, built as a complement of url_params()
           rather than a per-chip keep-list, so a filter added later is kept by every other chip. */
        $without = static function (string $drop) use ($params): string {
            $keep = $params;
            unset($keep[$drop]);

            return (new moodle_url('/enrol/apply/manage.php', $keep))->out(false);
        };
        $base = static function (array $keep) use ($params): string {
            return (new moodle_url('/enrol/apply/manage.php', array_intersect_key($params, array_flip($keep))))
                ->out(false);
        };

        $search = $table->get_search();
        $status = $table->get_status();

        /* The vocabulary comes from the table, so the select can only offer what manage.php will
           accept back and what the predicate can match. The labels are literal string ids, the
           same wording as {@see \enrol_apply\local\submission::status_label()}. */
        $statuses = [];
        foreach (\enrol_apply\table\applications::filterable_statuses() as $value) {
            $statuses[$value] = match ($value) {
                ENROL_APPLY_USER_WAIT => get_string('submissionstatuswaiting', 'enrol_apply'),
                default => get_string('submissionstatuspending', 'enrol_apply'),
            };
        }

        /* Kept apart from the per-field $fieldoptions below: a shared variable would make the
           status select publish the last offered field's vocabulary. */
        $statusoptions = [[
            'value' => '',
            'label' => get_string('queuestatusany', 'enrol_apply'),
            'selected' => $status === null,
        ]];
        foreach ($statuses as $value => $label) {
            $statusoptions[] = [
                'value' => (string) $value,
                'label' => $label,
                'selected' => $status === $value,
            ];
        }

        $chips = [];
        if ($search !== '') {
            $chips[] = $this->queue_filter_chip(
                'search',
                get_string('queuesearch', 'enrol_apply'),
                $search,
                $without('search')
            );
        }
        if ($status !== null) {
            $chips[] = $this->queue_filter_chip(
                'status',
                get_string('queuefilterstatus', 'enrol_apply'),
                $statuses[$status] ?? (string) $status,
                $without('status')
            );
        }

        /* One control and, where applied, one chip per field this reader is offered: the
           administrator's list intersected with core's identity mapping for this scope, so a
           field ticked in the site settings does not appear for a reader who may not see it. */
        $applied = $table->get_field_filters();
        $fields = [];
        foreach ($table->get_offered_filters() as $token => $offered) {
            $value = $applied[$token] ?? '';

            $fieldoptions = [];
            foreach ($offered->options as $optvalue => $optlabel) {
                $fieldoptions[] = [
                    'value' => (string) $optvalue,
                    'label' => $optlabel,
                    'selected' => (string) $optvalue === $value,
                ];
            }

            /* The label is resolved here and not in resolve(), which runs before the dynamic
               table's own validate_context() and so cannot reach format_string(). */
            $label = \enrol_apply\local\queuefilter::label($offered->name, false);

            $fields[] = [
                'token' => $token,
                'label' => $label,
                'inputid' => 'enrol_apply_filter_' . $token,
                'isselect' => $offered->control === 'select',
                'value' => $value,
                'options' => $fieldoptions,
            ];

            if ($value !== '') {
                $chips[] = $this->queue_filter_chip(
                    $token,
                    $label,
                    $offered->control === 'select' ? ($offered->options[$value] ?? $value) : $value,
                    $without($token)
                );
            }
        }

        [$appliedfrom, $appliedto] = $table->get_applied_dates();
        foreach (['appliedfrom' => 'queuefilterfrom', 'appliedto' => 'queuefilterto'] as $bound => $stringid) {
            $value = $bound === 'appliedfrom' ? $appliedfrom : $appliedto;
            if ($value !== null) {
                /* The date is shown as typed, which is how the input holds it, and deliberately
                   not through userdate(): the chip is redrawn client-side from the input's value
                   on every refresh, where Moodle's language-pack date format is not reproducible,
                   so a formatted chip would change spelling on the first refresh. */
                $chips[] = $this->queue_filter_chip(
                    $bound,
                    get_string($stringid, 'enrol_apply'),
                    $value,
                    $without($bound)
                );
            }
        }

        /* The course and the category, on the site-wide queue alone. Both are drawn from the
           table's own answer about whether this scope offers them, so the control and the
           predicate cannot disagree about which queue has them. */
        [$categoryid, $courseid] = $table->get_course_scope();
        $hascourse = $table->offers_course_filters();
        $categoryoptions = [];
        $courseoptions = [];
        if ($hascourse) {
            $categories = \enrol_apply\local\coursefilter::categories();
            $courses = \enrol_apply\local\coursefilter::courses();

            $categoryoptions = [[
                'value' => '',
                'label' => get_string('queuefilteranycategory', 'enrol_apply'),
                'selected' => $categoryid === null,
            ]];
            foreach ($categories as $id => $name) {
                $categoryoptions[] = [
                    'value' => (string) $id,
                    'label' => $name,
                    'selected' => $categoryid === (int) $id,
                ];
            }

            $courseoptions = [[
                'value' => '',
                'label' => get_string('queuefilteranycourse', 'enrol_apply'),
                'selected' => $courseid === null,
            ]];
            foreach ($courses as $id => $name) {
                $courseoptions[] = [
                    'value' => (string) $id,
                    'label' => $name,
                    'selected' => $courseid === (int) $id,
                ];
            }

            if ($categoryid !== null) {
                $chips[] = $this->queue_filter_chip(
                    'category',
                    get_string('queuefiltercategory', 'enrol_apply'),
                    $categories[$categoryid] ?? (string) $categoryid,
                    $without('category')
                );
            }
            if ($courseid !== null) {
                $chips[] = $this->queue_filter_chip(
                    'course',
                    get_string('queuefiltercourse', 'enrol_apply'),
                    $courses[$courseid] ?? (string) $courseid,
                    $without('course')
                );
            }

            /* Type-to-filter over a list rendered in full, with ajax off. Only courses carrying an
               apply method are listed, so the list is bounded by those rather than by the size of
               the site, and a static list needs no web service of its own. A site with thousands
               of apply-enabled courses would need an ajax source instead. */
            foreach (['enrol_apply_category', 'enrol_apply_course'] as $id) {
                $this->page->requires->js_call_amd('core/form-autocomplete', 'enhance', [
                    '#' . $id,
                    false,
                    false,
                    get_string('search'),
                    false,
                    true,
                    '',
                    true,
                ]);
            }
        }

        return [
            'formaction' => (new moodle_url('/enrol/apply/manage.php'))->out(false),
            'hasscope' => $scoped,
            'scopeid' => $params['id'] ?? 0,
            'searchlabel' => get_string('queuesearch', 'enrol_apply'),
            'searchvalue' => $search,
            'searchhelp' => $this->output->help_icon('queuesearch', 'enrol_apply'),
            'statuslabel' => get_string('queuefilterstatus', 'enrol_apply'),
            'statusoptions' => $statusoptions,
            'groupheading' => get_string('queuefiltersgroup', 'enrol_apply'),
            'hasfields' => (bool) $fields,
            'fields' => $fields,
            'hascoursefilters' => $hascourse,
            'categorylabel' => get_string('queuefiltercategory', 'enrol_apply'),
            'categoryoptions' => $categoryoptions,
            'courselabel' => get_string('queuefiltercourse', 'enrol_apply'),
            'courseoptions' => $courseoptions,
            'fromlabel' => get_string('queuefilterfrom', 'enrol_apply'),
            'fromvalue' => $appliedfrom ?? '',
            'tolabel' => get_string('queuefilterto', 'enrol_apply'),
            'tovalue' => $appliedto ?? '',
            'haschips' => (bool) $chips,
            'chips' => $chips,
            'clearurl' => $base($scoped ? ['id'] : []),
            'clearlabel' => get_string('queueclearfilters', 'enrol_apply'),
            'counttext' => get_string('queuefiltercount', 'enrol_apply', (object) [
                'matched' => (int) $table->totalrows,
                'total' => $table->scope_total(),
            ]),
            /* The total on its own, for the module to recompose the line after an as-you-type
               refresh: the refreshed table reports only the matched count, in its
               data-table-total-rows, and the scope's total does not change with the filters. */
            'scopetotal' => $table->scope_total(),
        ];
    }

    /**
     * One removable chip.
     *
     * The PLAIN spelling of both halves: the template double stashes them, and the remove label is
     * a lang-string parameter, which the string helper escapes exactly once on its own. Escaping
     * here would show an operator who searched for "A & B" a chip reading "A &amp;amp; B".
     *
     * @param string $filter The filter's name as the table's filterset knows it.
     * @param string $name What the filter is called, in the reader's language.
     * @param string $value What it is set to.
     * @param string $removeurl Url of the same listing without this filter.
     * @return array Template context for one chip.
     */
    protected function queue_filter_chip(string $filter, string $name, string $value, string $removeurl): array {
        return [
            'filter' => $filter,
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
     * The open/closed status is not built from allow_apply(), whose cohort clause asks whether
     * the current user may apply: a manager outside a restricted cohort would be told the method
     * is closed. It mirrors allow_apply()'s instance-level clauses - status, customint6 and the
     * enrolment dates - plus capacity::applications_closed(), which every caller of allow_apply()
     * checks beside it. A new instance-level clause in allow_apply() belongs here as well.
     *
     * The counts come from \enrol_apply\local\capacity, as on every other surface, so the queue
     * header and the review page cannot report different numbers.
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

        /* Everything below is about one enrolment method. The site-wide and mentee queues span
           methods, each with its own limits and dates, so they get the waiting count only. */
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
        // Counted once for the meter, the open/closed test and the room-left sentence.
        $held = $limit > 0 ? $capacity::applicants($instance) : 0;
        if ($limit > 0) {
            $context['meters'][] = [
                'value' => get_string('reviewofmany', 'enrol_apply', (object) [
                    'taken' => $held,
                    'total' => $limit,
                ]),
                'label' => get_string('reviewapplicants', 'enrol_apply'),
                'percent' => self::meter_percent($held, $limit),
                /* Warned at four fifths, so it is seen before the limit stops the method
                   accepting applications. */
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
            /* capacity::applications_closed() inlined to reuse $held rather than run the same
               COUNT again; applicant_limit() clamps a negative to 0, so `$limit > 0` is that
               method's `$limit !== 0`. Keep in step with it. */
            && !($limit > 0 && $held >= $limit);

        $remaining = $limit > 0 ? $limit - $held : 0;

        /* array_merge and never the + operator: + keeps the left side on a duplicate key, so the
           'hasstatus' => false default above would win and the status block would never render. */
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
     * The choosers are gated on the capability in the course, which is stricter than the review
     * page's own gate. A mentor reaches that page through the applicant's user context and holds
     * nothing in the course, and groups_get_all_groups() applies no capability check (unlike
     * get_assignable_roles()), so the group chooser would list every group name in a course they
     * cannot open. The instance's own groups and role still apply to a mentor's decision; they
     * only cannot override them.
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
            /* The plain spelling ('escape' => false), because the template renders the name
               through a double stash; with format_string()'s default a group named "R&D < Team"
               would read "R&amp;D &lt; Team". edit_form.php's call keeps the escaped spelling
               because a moodleform select renders its options through a triple stash. */
            foreach (groups_get_all_groups($instance->courseid) as $group) {
                $groups[] = [
                    'id' => $group->id,
                    'name' => format_string($group->name, true, ['context' => $coursecontext, 'escape' => false]),
                ];
            }

            /* The same list the server allowlists the posted role against, so the control cannot
               offer anything the decision would refuse. It is empty without moodle/role:assign in
               the course - both capabilities share the editingteacher and manager archetypes, but
               a custom role can hold one without the other - and then the instance's own role
               applies, as when the decider leaves the select alone.

               Unlike the group names, these go to the template in the escaped spelling for a
               triple stash, as core's element-select.mustache does. The format_string() below is
               what makes that safe: role_get_name() escapes a role with a role.name set but
               returns a bare get_string() for one without, which covers every role a stock site
               ships, so the list mixes both spellings. format_string() is idempotent on the
               escaped half, because the ampersand rule skips an existing entity. */
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
            /* Non-empty only on the way back from the cancel confirmation. Plain spelling: the
               template double stashes it. */
            'messagevalue' => $message,
            /* The decider's own note, offered on every decision and not gated on the course
               capability like the choosers: they change what the approval does, while the note
               only records why, and a mentor's decision deserves a reason as much as anyone's. */
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
     * Judged in the course context through moodle/site:viewuseridentity, as the snapshot panel is
     * through visible_keys(), so the two panels cannot disagree about the same reader. The e-mail
     * address is one identity field among the rest: on a site whose `showuseridentity` does not
     * name it, it does not appear here, as on core's participants page; the profile link beside
     * the name is the route to contact details for a reader entitled to them.
     *
     * The course context, not the page's: queue::require_review_access() can return the
     * applicant's user context on the mentor path. That costs a mentor the identity fields, the
     * same stricter reading the report takes.
     *
     * Values go out plain, not through s(), because the template double stashes them.
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
                /* Labelled, because these run together on one line and several are opaque on
                   their own, such as an id number beside a username. */
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
     * Only for a deferred application, the one case where the reader sees something a colleague
     * already decided: a pending one has no decision to describe, and queue::application() returns
     * null once an application stops awaiting a decision, so the page cannot show the other two
     * states. Every value but the decider's record comes from columns queue::application() already
     * selects.
     *
     * The decider's name is read live and is not masked: it is a member of staff acting in this
     * course, not the applicant, and the same name is already on the report. The message is the
     * one written to the applicant, so it is shown as written.
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
            // Escaped with the decider's line breaks kept, as the applicant's comment is.
            'decisionmessage' => format_text($message, FORMAT_PLAIN),
            /* The note the last decider left for whoever reads this next. Shown here rather than
               pre-filled into the note box below: the writer clears on empty, so a pre-filled box
               would carry one decision's reason silently into the next.

               No capability of its own: the panel is already behind the review page's gate, and
               the note says less about the applicant than the comment printed below it. */
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
     * at all rather than an empty one, because a heading that appears only when there is history
     * is itself a disclosure.
     *
     * Each row's wording comes from the report's own outcome formatter rather than the record's
     * bare status, so the two surfaces cannot describe the same record differently: a record says
     * approved for ever, while the enrolment it names may since have been suspended or removed by
     * a route this plugin never sees.
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
     * The numbers answer two different questions and must never be mixed: applicants counts every
     * non-expired row - pending, deferred and approved alike - while places counts active rows
     * only. The gap between them is overbooking, which is legitimate where approval is
     * discretionary. See \enrol_apply\local\capacity.
     *
     * Shown as a neutral readout rather than the queue's warning, because a single decision
     * wants the number whatever it is.
     *
     * Gated on the course capability, which is stricter than the page, for the same reason as
     * the group and role choosers ({@see self::decision_controls_context()}): a mentor, trusted
     * with one applicant, is not told the method's limits or its enrolment counts.
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
            /* Not the setting's own label: "Maximum applicants: 35 of 40" reads as though 35
               were the maximum, while this row reports how many the method holds against it. */
            'applicantslabel' => get_string('reviewapplicants', 'enrol_apply'),
            'applicants' => $limit > 0
                ? get_string('reviewofmany', 'enrol_apply', (object) [
                    'taken' => $capacity::applicants($instance),
                    'total' => $limit,
                ])
                : $nolimit,
            /* How many of those applications are deferred: a subset of the applicants row, not
               another limit, so a bare count. A deferred row holds its place against the
               applicant limit until cancelled (see capacity::deferred()), which is what explains a
               method refusing new applications with an empty queue. */
            'deferredlabel' => get_string('reviewdeferred', 'enrol_apply'),
            'deferred' => (string) $capacity::deferred($instance),
        ];
    }

    /**
     * The details the applicant submitted with this application, as the reader may see them.
     *
     * Read from the frozen snapshot the submission wrote, never recomputed through
     * \enrol_apply\local\diff::compute(): that re-resolves the field set from the live instance
     * and re-classifies it against the current user, so a field no longer asked for, or no longer
     * editable, would vanish from the record of what was submitted. The stored labels are used
     * for the same reason: they are what the applicant saw.
     *
     * Nothing here reads the applicant's live profile, and that is a security boundary. The
     * stored keys come from userinfodata, which restore_enrol_apply_plugin writes verbatim from a
     * foreign archive. fields::current_value() dereferences any {user} column an "s_" key names
     * (fields::DENY governs only the write path) and reads a "c_<id>" key from {user_info_data}
     * past core's PROFILE_VISIBLE_* gates, and a reader for whom visible_keys() returns
     * ALL_FIELDS skips the key filter entirely - so a crafted archive could expose any column,
     * the password hash included. The report does not read the live profile either.
     *
     * Masked with the report's own rule in the course context. A mentor holds nothing in the
     * course, so they see the name fields only, even where their mentor role grants the identity
     * capability in the applicant's user context: the stricter reading, as in the report.
     *
     * Every value is the plain spelling and the template double stashes it. Not format_string(),
     * whose strip_tags() would delete a restored value from the first "<" onwards. A value can
     * hold newlines (a textarea custom field is offerable), so the template carries the report
     * cell's white-space rule rather than converting them to markup.
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
                   marker that appears exactly where there is data is a presence oracle. */
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
     *        applications. Required: manage.php always resolves them before rendering. It renders
     *        as nothing only when there is no neighbour and no queue to go back to; a queue of one
     *        still gets the way back.
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
        /* No heading here: core already renders the applicant's name as the page's <h1> from
           $PAGE->set_heading(), so the <h2>s belong to the panels below. */

        /* Above the form, where core puts a tertiary navigation bar: the last control an
           operator reads before a decision should be the decision.

           No render_application_navigation() is needed: render() falls back to the
           "<component>/<class>" template for a templatable with no render_ method, and a
           theme's renderer subclass can still declare one. What is load bearing is the class
           name matching the template file name; renaming either alone throws. */
        echo $this->render($navigation);
        echo $this->review_form($application, $applicant, $instance, $manageurl, $message, $note);
        echo $this->footer();
    }

    /**
     * The single-application decision form.
     *
     * The POST carries the queue's own contract - formaction, userenrolments[] and the session
     * key - so every guard manage.php applies to a queue decision applies here unchanged. The one
     * branch manage.php adds for this page is the confirmation before a cancellation, and the
     * confirmed request arrives on the same contract and passes the same guards.
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
            // No e-mail row: the address is one identity field among the rest. See identity_context().
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
            /* Plain, not escaped: review.mustache renders this through a double stash. The
               label's other two sinks render raw, which is why the helper takes a flag. */
            'commentlabel' => \enrol_apply\local\commentlabel::custom($instance, false),
            'hascomment' => trim((string) $application->applycomment) !== '',
            /* Escaped once with the applicant's line breaks kept, as in the queue's own cell:
               format_text(FORMAT_PLAIN) escapes and converts newlines and nothing else, so the
               template triple stashes it, as it does the decision message and note.

               Not format_string(): its strip_tags() would delete the text from the first "<"
               onwards, and a restore writes the comment verbatim out of a foreign archive. */
            'comment' => format_text((string) $application->applycomment, FORMAT_PLAIN),
            'nocomment' => get_string('nocomment', 'enrol_apply'),
            /* Singular labels of their own: the queue's btnconfirm and its siblings read
               "Confirm requests", which is wrong above one application.

               Order is the layout: the bar spreads these with justify-content-between, so the
               destructive decision sits at the far edge, away from the others, and the approval
               where the eye finishes. Cancel gets its own style because it is the only
               irreversible one: cancel_enrolment() unenrols, taking the row and the applicant's
               comment with it. See enrol_apply/review_actions for why its being the default
               submit is safe. */
            'actions' => [
                ['value' => 'cancel', 'label' => get_string('reviewcancel', 'enrol_apply'), 'style' => 'btn-outline-danger'],
                ['value' => 'wait', 'label' => get_string('reviewwait', 'enrol_apply'), 'style' => 'btn-secondary'],
                ['value' => 'confirm', 'label' => get_string('reviewconfirm', 'enrol_apply'), 'style' => 'btn-primary'],
            ],
        ];

        /* The decisions go into a core sticky footer interpolated inside the form, as on the
           queue (see manage_form()): only the buttons, because the footer's content area clips
           with no scrollbar.

           The spreading class is on the partial's own row, not passed to the footer: the footer
           applies its classes to a content area whose only child is that row, so there it would
           have nothing to spread. */
        $bar = $this->render_from_template('enrol_apply/review_actions', [
            'actions' => $context['actions'],
        ]);
        $context['stickyfooter'] = $this->render(new \core\output\sticky_footer($bar));

        return $this->render_from_template('enrol_apply/review', $context);
    }

    /**
     * Render the page asking whether to cancel one application.
     *
     * @param stdClass $applicant Applicant user record.
     * @param moodle_url $manageurl The review page's own url.
     * @param int $userenrolmentid The application being cancelled.
     * @param string $message What the operator typed for the applicant.
     * @param string $note The decision note they typed.
     * @return void
     */
    public function cancel_confirmation_page(
        $applicant,
        moodle_url $manageurl,
        int $userenrolmentid,
        string $message,
        string $note
    ) {
        echo $this->header();
        echo $this->cancel_confirmation($applicant, $manageurl, $userenrolmentid, $message, $note);
        echo $this->footer();
    }

    /**
     * The question asked before one application is cancelled, and its two answers.
     *
     * Both answers are POST forms back to the review page. The message and note travel both ways:
     * onward because the cancellation records them, and back so the operator's text survives
     * backing out. single_button writes every url parameter into a hidden input whatever the
     * method, but a GET form submits them as the query string of the page it opens, which would
     * put the decider's note - never shown to the applicant - and the message into web server
     * logs, the browser history and the Referer of whatever that page loads. manage.php reads
     * both back with optional_param(), which accepts a POST alike.
     *
     * The group and role choosers are not carried, in either direction: they are the approval's
     * parameters and cancelling reads neither.
     *
     * @param stdClass $applicant Applicant user record.
     * @param moodle_url $manageurl The review page's own url, carrying userenrol.
     * @param int $userenrolmentid The application being cancelled.
     * @param string $message What the operator typed for the applicant, plain.
     * @param string $note The decision note they typed, plain.
     * @return string Rendered markup.
     */
    public function cancel_confirmation(
        $applicant,
        moodle_url $manageurl,
        int $userenrolmentid,
        string $message,
        string $note
    ) {
        $output = $this->heading(get_string('reviewcancelconfirm', 'enrol_apply'));
        /* Both buttons are labelled explicitly: core's confirm() would label the second one
           "Cancel", beside a destructive primary button that also starts with "Cancel". */
        $output .= $this->confirm(
            get_string('reviewcancelconfirm_desc', 'enrol_apply', fullname($applicant)),
            new single_button(
                /* The queue's own decision contract. single_button names each hidden input by the
                   raw key, so `userenrolments[0]` is what optional_param_array() reads back. */
                new moodle_url($manageurl, [
                    'formaction' => 'cancel',
                    'confirmed' => 1,
                    'sesskey' => sesskey(),
                    'userenrolments[0]' => $userenrolmentid,
                    'outcomemessage' => $message,
                    'decisionnote' => $note,
                ]),
                get_string('reviewcancelaction', 'enrol_apply'),
                'post'
            ),
            new single_button(
                new moodle_url($manageurl, [
                    'outcomemessage' => $message,
                    'decisionnote' => $note,
                ]),
                get_string('reviewkeep', 'enrol_apply'),
                'post'
            )
        );

        return $output;
    }

    /**
     * The profile details an applicant still has to fill in themselves.
     *
     * Shown on the acknowledgement page, applied.php, when the site does not let courses write to
     * profiles; that page says why the list is needed.
     *
     * @param array $missing What \enrol_apply\local\completeness::missing() returns: one entry per
     *        field, each with a key and a label in the plain spelling.
     * @return string Rendered markup.
     */
    public function profile_missing(array $missing): string {
        $fields = [];
        foreach ($missing as $field) {
            // Plain, because the template double stashes it.
            $fields[] = ['label' => $field['label']];
        }

        return $this->render_from_template('enrol_apply/profile_missing', [
            'heading' => get_string('profileincomplete', 'enrol_apply'),
            'intro' => get_string('profileincomplete_desc', 'enrol_apply'),
            'fields' => $fields,
        ]);
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
