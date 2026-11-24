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
    /** @var string A group card's seats within their limit: below four fifths of it. */
    public const SIGNAL_OK = 'ok';

    /** @var string A group card's seats at four fifths of their limit or more, and below it. */
    public const SIGNAL_WARN = 'warn';

    /** @var string A group card's seats at or over their limit: confirming overbooks, and is still allowed. */
    public const SIGNAL_OVER = 'over';

    /** @var string A review meter at or below 60 % once the application is confirmed. */
    public const METER_PRIMARY = 'primary';

    /** @var string A review meter above 60 % once the application is confirmed. */
    public const METER_WARNING = 'warning';

    /** @var string A review meter above 90 % once the application is confirmed. */
    public const METER_DANGER = 'danger';

    /**
     * Render the page listing the applications awaiting a decision.
     *
     * @param \enrol_apply\table\applications $table Table listing the applications.
     * @param moodle_url $manageurl Url the decision form posts back to: on a listing that has a grid view
     *     (the method queue, the site-wide queue), the table's own address (view=table), so a decision
     *     lands back on the table.
     * @param stdClass|null $instance Enrol instance when the page is scoped to one, null otherwise.
     * @return void
     */
    public function manage_page($table, $manageurl, $instance) {
        echo $this->header();
        echo $this->heading(get_string('confirmusers', 'enrol_apply'));
        /* The way to the grid view, the page of the method queue and of the site-wide queue, with the
           filters in force: wherever this table is a grid's table, which manage.php says by giving it the
           address with view=table (the mentee queue's table has no grid until stage W5, and its address no
           view). Outside manage_form(), which is also the grid view's noscript, where it would lead back. */
        if ($manageurl->get_param('view') === \enrol_apply\output\queue_view::TABLE) {
            echo $this->render_from_template('enrol_apply/queue_viewswitch', [
                'url' => \enrol_apply\output\queue_view::url($manageurl, true)->out(false),
            ]);
        }
        echo $this->manage_form($table, $manageurl, $instance);
        echo $this->footer();
    }

    /**
     * Render the method queue's grid view, its page (CQ-D5): the React shell, today's queue in its noscript.
     *
     * @param \enrol_apply\output\queue_view $view The shell.
     * @return void
     */
    public function queue_page(\enrol_apply\output\queue_view $view): void {
        echo $this->header();
        echo $this->heading(get_string('confirmusers', 'enrol_apply'));
        echo $this->render($view);
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
        /* A decider narrowed by separate groups who belongs to no group gets core's own notice
           and no queue, exactly as core's participants page (user/index.php) treats them: no
           row could be theirs, and an empty table would read as "nothing to decide". */
        if ($table->not_in_any_group()) {
            return $this->notification(get_string('notingroup'));
        }

        $tablehtml = $this->capture_table($table);

        /* A viewer holding enrol/apply:viewapplications alone (S5) gets the same queue with no
           decision form around it: no selection column (the table drops it), no bulk bar, no
           decision controls. The decision methods would skip their rows anyway; this only keeps
           the page from offering what it cannot do. */
        $candecide = $table->can_decide();
        $actions = $this->queue_actions();

        /* The action bar goes into a core sticky footer that the template interpolates inside
           the form: its position is CSS and it is never moved in the DOM, so its controls post
           with the form, as in core's grade/templates/edit_tree.mustache. Only the action belongs
           there: .sticky-footer-content has overflow hidden, so the message textarea and the two
           choosers stay in the page body.

           justify-content-end is already the default; it is passed through the constructor
           because sticky_footer::add_classes() replaces the classes rather than appending to
           them, so any further class must be passed here too. */
        $stickyfooter = '';
        if ($candecide) {
            $bar = $this->render_from_template('enrol_apply/manage_actions', [
                'togglegroup' => \enrol_apply\table\applications::TOGGLE_GROUP,
                'selectedlabel' => get_string('queueselectedonpage', 'enrol_apply', 0),
                'actions' => $actions,
            ]);
            $stickyfooter = $this->render(new \core\output\sticky_footer($bar, 'justify-content-end'));
        }

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

        /* An empty queue on a key-only method is the method working, not a fault: nothing there
           ever waits for a decision. Said only while it is empty, because applications made
           before the method was switched to key only still list and can still be decided. */
        $keyonlynotice = '';
        if ($instance !== null && \enrol_apply\local\groupkey::required($instance) && $table->scope_total() === 0) {
            $keyonlynotice = get_string('keyonlyqueue', 'enrol_apply');
        }

        /* Which groups a narrowed reader's queue is limited to (V-D14), so that a queue narrowed to
           two groups does not read as a short one. Every narrowed reader, decider or viewer, and
           nobody else; the names are their own groups, which they already know. The plain spelling:
           a lang-string parameter in a double stash. */
        $scopenames = $table->scope_group_names();
        $scopeline = $scopenames ? get_string('queuescopegroups', 'enrol_apply', implode(', ', $scopenames)) : '';

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

        $context = $this->queue_decision_controls($table, $instance) + [
            'candecide' => $candecide,
            'viewonlynotice' => get_string('queueviewonly', 'enrol_apply'),
            'formurl' => $manageurl->out(false),
            'sesskey' => sesskey(),
            /* The method's own numbers, on a method's queue only: the site-wide and mentee queues
               span methods and get no band. The two plain counts it used to carry are the status
               control's now (MQ-D3), in queue_filters_context(). */
            'capacityhtml' => $instance === null ? '' : $this->render_from_template(
                'enrol_apply/queue_capacity',
                $this->queue_capacity_context($instance, $table->scope_groups() !== null)
            ),
            'filtershtml' => $this->render_from_template(
                'enrol_apply/queue_filters',
                $this->queue_filters_context($table, $manageurl)
            ),
            'tablehtml' => $tablehtml,
            'hasshowing' => $showing !== '',
            'showingtext' => $showing,
            'stickyfooter' => $stickyfooter,
            'decisionoptionslabel' => get_string('queuedecisionoptions', 'enrol_apply'),
            /* The queues that span methods: without JavaScript one message reaches every method's
               applicants, which the decision sheet splits by method (MQ-D15). */
            'hasspansnote' => $instance === null,
            'spansnote' => get_string('queuedecisionspans', 'enrol_apply'),
            'hasplacesnotice' => $placesnotice !== '',
            'placesnotice' => $placesnotice,
            'hasclosednotice' => $closednotice !== '',
            'closednotice' => $closednotice,
            'haskeyonlynotice' => $keyonlynotice !== '',
            'keyonlynotice' => $keyonlynotice,
            'hasscopeline' => $scopeline !== '',
            'scopeline' => $scopeline,
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
     * The queue's three decisions, in the review page's order and styles (MQ-D4).
     *
     * One list for the table's sticky footer and the grid view's dock, so the two offer the same
     * decisions under the same labels in the same order: approve, defer, and the destructive one
     * last. Each value is a formaction manage.php accepts, so the POST contract is the review
     * page's and a decision is one press without JavaScript. Each carries a short label too, the
     * one the footer shows below 576 px, where core's 80 px footer has to keep one line.
     *
     * @return array Each: value (the formaction), label, short, classes (the button's style).
     */
    public function queue_actions(): array {
        return [
            [
                'value' => 'confirm',
                'label' => get_string('btnconfirm', 'enrol_apply'),
                'short' => get_string('btnconfirmshort', 'enrol_apply'),
                'classes' => 'btn-primary',
            ],
            [
                'value' => 'wait',
                'label' => get_string('btnwait', 'enrol_apply'),
                'short' => get_string('btnwaitshort', 'enrol_apply'),
                'classes' => 'btn-outline-secondary',
            ],
            [
                'value' => 'cancel',
                'label' => get_string('btncancel', 'enrol_apply'),
                'short' => get_string('btncancelshort', 'enrol_apply'),
                'classes' => 'btn-outline-danger',
            ],
        ];
    }

    /**
     * What the queue's decision form offers this reader: the decision controls, or nothing.
     *
     * The table form's own call, and the grid view's (\enrol_apply\output\queue_view::props()), so the
     * two cannot offer different groups, roles or summaries. Nothing for a reader who may not decide
     * from this listing (a viewer, S5), whose page has no decision form at all.
     *
     * @param \enrol_apply\table\applications $table The queue as built for this request.
     * @param stdClass|null $instance Enrol instance the queue is scoped to, null site wide.
     * @return array The context of enrol_apply/decision_controls, or an empty array.
     */
    public function queue_decision_controls(\enrol_apply\table\applications $table, ?stdClass $instance): array {
        return $table->can_decide() ? $this->decision_controls_context($instance) : [];
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
     * The status is a segmented control rather than a field (MQ-D3): each value is a link to this
     * listing with that status, so it works without JavaScript and amd/src/manage.js only takes
     * over the click. Every other filter lives in the Filters panel, a native details element whose
     * fields stay in this one GET form.
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

        /* The vocabulary comes from the table, so the status control can only offer what
           manage.php will accept back and what the predicate can match. The labels are literal
           string ids, the same wording as {@see \enrol_apply\local\submission::status_label()}. */
        $statuses = [];
        foreach (\enrol_apply\table\applications::filterable_statuses() as $value) {
            $statuses[$value] = match ($value) {
                ENROL_APPLY_USER_WAIT => get_string('submissionstatuswaiting', 'enrol_apply'),
                default => get_string('submissionstatuspending', 'enrol_apply'),
            };
        }

        /* The status control's three values and their counts, which are the scope's and no filter
           changes them: the counts used to be the band's "awaiting decision" and "deferred" tiles,
           and they keep those tiles' rule, the scope's totals over the same scope_where(), so a
           narrowed decider is counted in their groups (V-D3) and a search never makes "All" smaller
           than "Deferred". Pending is what is left, the two statuses being the only ones a queued
           row holds (filterable_statuses()). */
        $total = $table->scope_total();
        $deferred = $table->scope_deferred();
        $counts = [
            '' => $total,
            ENROL_USER_SUSPENDED => max(0, $total - $deferred),
            ENROL_APPLY_USER_WAIT => $deferred,
        ];

        /* Each value is the url of this listing with that status and every other filter kept, as a
           chip's remove link is built; url_params() carries no paging, so a status change starts at
           page one. A value counting nothing is not drawn unless it is the current one
           (segmented-control.md); All always is. */
        $statuscells = [];
        foreach ($counts as $value => $count) {
            $current = $value === '' ? $status === null : $status === (int) $value;
            if ($value !== '' && $count === 0 && !$current) {
                continue;
            }
            $cellparams = $params;
            unset($cellparams['status']);
            if ($value !== '') {
                $cellparams['status'] = $value;
            }
            $statuscells[] = [
                'value' => (string) $value,
                'label' => $value === '' ? get_string('queuestatusall', 'enrol_apply') : $statuses[(int) $value],
                'count' => $count,
                'current' => $current,
                'url' => (new moodle_url('/enrol/apply/manage.php', $cellparams))->out(false),
            ];
        }

        $chips = [];
        /* How many of the Filters panel's own filters are in force, for its loud counter: every chip
           but the search's, which the bar shows in place. */
        $panelcount = 0;
        if ($search !== '') {
            $chips[] = $this->queue_filter_chip(
                'search',
                get_string('queuesearch', 'enrol_apply'),
                $search,
                $without('search')
            );
        }
        /* No chip for the status: the segmented control says which value is current, and a chip
           would say it a second time. */

        /* The group filter, on the instance queue alone and with the values this reader is offered
           (\enrol_apply\local\groupfilter). Where the page defaults it to the reader's own groups
           (V-D2), "Any group" is the explicit `all` and removing the chip writes it, so the default
           cannot come back on the next link; elsewhere "Any group" is the empty value, as for the
           status. Above the threshold (RP-D18) core's autocomplete searches the same select. */
        $groupchoices = $table->get_group_choices();
        $groupapplied = $table->get_group_filter();
        $groupdefault = $table->get_group_default();
        $groupany = $groupdefault !== null ? \enrol_apply\local\groupfilter::ANY : '';
        $groupoptions = [];
        $groupsearch = false;
        if ($groupchoices) {
            $groupoptions[] = [
                'value' => $groupany,
                'label' => get_string('queuegroupany', 'enrol_apply'),
                'selected' => $groupapplied === null,
            ];
            foreach ($groupchoices as $value => $label) {
                $groupoptions[] = [
                    'value' => (string) $value,
                    'label' => $label,
                    'selected' => $groupapplied === (string) $value,
                ];
            }

            if ($groupapplied !== null) {
                $removeparams = $params;
                if ($groupdefault !== null) {
                    $removeparams[\enrol_apply\local\groupfilter::NAME] = \enrol_apply\local\groupfilter::ANY;
                } else {
                    unset($removeparams[\enrol_apply\local\groupfilter::NAME]);
                }
                $panelcount++;
                $chips[] = $this->queue_filter_chip(
                    \enrol_apply\local\groupfilter::NAME,
                    get_string('queuegroup', 'enrol_apply'),
                    $groupchoices[$groupapplied] ?? $groupapplied,
                    (new moodle_url('/enrol/apply/manage.php', $removeparams))->out(false)
                );
            }

            // Counted over the groups alone, after the reader's narrowing; mine and none are extras.
            $groupcount = count(array_diff_key($groupchoices, array_flip([
                \enrol_apply\local\groupfilter::MINE,
                \enrol_apply\local\groupfilter::NONE,
            ])));
            $groupsearch = $groupcount > \enrol_apply\local\groupfilter::THRESHOLD;
            if ($groupsearch) {
                $this->page->requires->js_call_amd('core/form-autocomplete', 'enhance', [
                    '#enrol_apply_filter_group',
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
                $panelcount++;
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
                $panelcount++;
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
                $panelcount++;
                $chips[] = $this->queue_filter_chip(
                    'category',
                    get_string('queuefiltercategory', 'enrol_apply'),
                    $categories[$categoryid] ?? (string) $categoryid,
                    $without('category')
                );
            }
            if ($courseid !== null) {
                $panelcount++;
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
            /* A GET submit replaces the query string, so the table view (view=table, CQ-D5) rides
               along as a field of its own, as the scope does: Enter in the search box keeps the
               reader on the table rather than sending them to the grid. */
            'hasviewtable' => $manageurl->get_param('view') === \enrol_apply\output\queue_view::TABLE,
            'searchlabel' => get_string('queuesearch', 'enrol_apply'),
            'searchvalue' => $search,
            'searchhelp' => $this->output->help_icon('queuesearch', 'enrol_apply'),
            'statuslabel' => get_string('queuefilterstatus', 'enrol_apply'),
            'statuscells' => $statuscells,
            /* A narrowed decider's counts are their groups' (V-D3), which the cells alone would not
               say. Plain spelling. */
            'countnote' => $table->scope_groups() !== null ? get_string('queueinyourgroups', 'enrol_apply') : '',
            'filterslabel' => get_string('queuefilters', 'enrol_apply'),
            'panelcount' => $panelcount,
            'groupheading' => get_string('queuefiltersgroup', 'enrol_apply'),
            'hasfields' => (bool) $fields,
            'fields' => $fields,
            'hasgroupfilter' => (bool) $groupoptions,
            'grouplabel' => get_string('queuegroup', 'enrol_apply'),
            'groupoptions' => $groupoptions,
            'groupemptyvalue' => $groupany,
            'groupsearch' => $groupsearch,
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
            /* Every filter off, the group default included: where the page would apply it again,
               the url says `all` instead. The panel's "Clear filters" is the same url. */
            'clearurl' => $groupdefault !== null
                ? (new moodle_url('/enrol/apply/manage.php', [
                    'id' => $params['id'],
                    \enrol_apply\local\groupfilter::NAME => \enrol_apply\local\groupfilter::ANY,
                ]))->out(false)
                : $base($scoped ? ['id'] : []),
            'clearlabel' => get_string('queueclearfilters', 'enrol_apply'),
            'counttext' => get_string('queuefiltercount', 'enrol_apply', (object) [
                'matched' => (int) $table->totalrows,
                'total' => $total,
            ]),
            /* The total on its own, for the module to recompose the line after an as-you-type
               refresh: the refreshed table reports only the matched count, in its
               data-table-total-rows, and the scope's total does not change with the filters. */
            'scopetotal' => $total,
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
     * The decision context above a method's queue: how full the method is, and whether it is open.
     *
     * The open/closed status is \enrol_apply\local\capacity::state()'s, which says why it is not
     * allow_apply(); the headless summary reads the same answer.
     *
     * The counts come from \enrol_apply\local\capacity, as on every other surface, so the queue
     * header and the review page cannot report different numbers. How many applications wait and
     * how many are deferred are the status control's counts (MQ-D3), not tiles of this band; see
     * queue_filters_context().
     *
     * A decider narrowed by separate groups (V-D3) keeps the method's capacity numbers, labelled as
     * the method's: those are what tell a moderator they are about to overbook.
     *
     * @param stdClass $instance Enrol instance the queue is scoped to.
     * @param bool $narrowed Whether the queue is narrowed to the reader's groups.
     * @return array Context for the enrol_apply/queue_capacity template.
     */
    protected function queue_capacity_context(stdClass $instance, bool $narrowed = false): array {
        $context = [
            'meters' => [],
            'hasstatus' => false,
        ];

        // The method's numbers below are not the queue's when the queue is narrowed; say whose.
        $methodnote = $narrowed ? get_string('queueonthismethod', 'enrol_apply') : '';

        // Every number and the open/closed status, as the headless summary reads them.
        $state = \enrol_apply\local\capacity::state($instance);

        $places = $state->places;
        if ($places > 0) {
            $taken = $state->taken;
            $context['meters'][] = [
                'value' => get_string('reviewofmany', 'enrol_apply', (object) [
                    'taken' => $taken,
                    'total' => $places,
                ]),
                'label' => get_string('queueplacestaken', 'enrol_apply'),
                'note' => $methodnote,
                'percent' => self::meter_percent($taken, $places),
                'warn' => false,
            ];
        }

        $limit = $state->limit;
        // Counted once for the meter, the open/closed test and the room-left sentence.
        $held = $state->held;
        if ($limit > 0) {
            $context['meters'][] = [
                'value' => get_string('reviewofmany', 'enrol_apply', (object) [
                    'taken' => $held,
                    'total' => $limit,
                ]),
                'label' => get_string('reviewapplicants', 'enrol_apply'),
                'note' => $methodnote,
                'percent' => self::meter_percent($held, $limit),
                /* Warned at four fifths, so it is seen before the limit stops the method
                   accepting applications. */
                'warn' => $held * 5 >= $limit * 4,
            ];
        }

        $enddate = $state->enddate;
        $open = $state->open;

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
     * only cannot override them. A decider narrowed by separate groups is offered their own groups
     * alone (S6), the list confirm_enrolment() allowlists the posted ids against
     * ({@see \enrol_apply\local\groupscope::choosable_groups()}).
     *
     * Choosing is a capability of its own, enrol/apply:choosedecision (S5). A course decider
     * without it gets no chooser and, in its place, what an approval will give, read-only
     * ({@see self::approval_summary_context()}). That is cosmetic: confirm_enrolment() records an
     * empty choice for such a decider whatever they post.
     *
     * With local_groupdist installed, each group that declares seats reads its occupancy, and a
     * full one says so and stays selectable (G15): approving into it is the decider's call, as
     * approving past the course's places is, and manage.php warns after the decision.
     *
     * @param stdClass|null $instance Enrol instance the decision belongs to, null when unknown.
     * @param string $message What the operator had already typed, empty on the ordinary path.
     * @param string $note The decision note they had already typed, empty on the ordinary path.
     * @param array $preselected Group ids to offer ticked: the groups a valid key would have joined,
     *        on the review page of an application the key could not admit. Empty elsewhere.
     * @param int $userenrolmentid The application on the review page, 0 on the queue; read only by
     *        the read-only summary, which on the review page names that application's groups.
     * @return array Context for the partial.
     */
    protected function decision_controls_context(
        $instance,
        $message = '',
        $note = '',
        array $preselected = [],
        int $userenrolmentid = 0
    ): array {
        $groups = [];
        $roles = [];
        $facts = [];

        $coursecontext = $instance === null ? null : \context_course::instance($instance->courseid);
        $decides = $coursecontext && has_capability('enrol/apply:manageapplications', $coursecontext);
        $chooses = $decides && has_capability('enrol/apply:choosedecision', $coursecontext);
        if ($chooses) {
            $facts = $this->group_facts($instance, $coursecontext);

            /* The seats suffix keeps the plain spelling of group_facts(): get_string() substitutes
               without escaping, and the template double stashes the option text. */
            foreach ($facts as $fact) {
                $name = $fact->name;
                if ($fact->hasseats) {
                    $a = (object) ['name' => $name, 'members' => $fact->members, 'seats' => $fact->seats];
                    $name = $fact->full
                        ? get_string('decisiongroupfull', 'enrol_apply', $a)
                        : get_string('decisiongroupseats', 'enrol_apply', $a);
                }
                $groups[] = [
                    'id' => $fact->id,
                    'name' => $name,
                    'selected' => in_array($fact->id, $preselected, true),
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

        $summary = $decides && !$chooses
            ? $this->approval_summary_context($instance, $coursecontext, $userenrolmentid)
            : ['hassummary' => false];

        return $summary + [
            /* Read by the review page's funnel, which renders the same facts differently (cards,
               a search, the rules' meters); the queue's template ignores them. */
            'coursedecider' => $decides,
            'chooser' => $chooses,
            'groupfacts' => $facts,
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
     * The groups this reader may choose on an approval, with their seats, in natural order.
     *
     * The read itself is {@see \enrol_apply\local\groupfacts::for_course()}, shared with the
     * participants page's bulk form; the plain spelling of the name is why every template here
     * can double stash it.
     *
     * @param stdClass $instance Enrol instance the decision belongs to.
     * @param \context_course $coursecontext Its course.
     * @return array Group id => object with id, name (plain), idnumber, hasseats, members, seats,
     *     full and percent (whole, clamped to the bar).
     */
    protected function group_facts(stdClass $instance, \context_course $coursecontext): array {
        return \enrol_apply\local\groupfacts::for_course((int) $instance->courseid, $coursecontext, (int) $instance->id);
    }

    /**
     * What an approval will give, for a decider who may not choose it (S5).
     *
     * The groups are the approval's own fallback, {@see \enrol_apply_plugin::default_approval_groups()}:
     * on the review page that application's key route, its chosen group and the instance's list, or
     * the instance's list; on the queue the instance's list with a sentence saying that a keyed
     * application, or one made with a chosen group, also joins that group.
     * The role is the instance's. Read from the same helpers the approval runs, so the summary
     * cannot promise what the decision then does not do.
     *
     * Only a course decider reaches this: the caller's gate is the one the choosers use, so a
     * mentor is told no group name here either. A group core hides from this reader
     * ({@see \enrol_apply\local\groupvisibility::visible_to()}) is counted, not named.
     *
     * Group names go out plain for a double stash; the role name escaped for a triple stash, as
     * the role chooser's are, because role_get_name() returns a mixed spelling.
     *
     * @param stdClass $instance Enrol instance the decision belongs to.
     * @param \context_course $coursecontext Its course.
     * @param int $userenrolmentid The application on the review page, 0 on the queue.
     * @return array Context for the partial's summary block.
     */
    protected function approval_summary_context(stdClass $instance, \context_course $coursecontext, int $userenrolmentid): array {
        global $DB, $USER;

        $groupids = enrol_get_plugin('apply')->default_approval_groups($instance, $userenrolmentid);
        $groups = $groupids
            ? $DB->get_records_list('groups', 'id', $groupids, 'name ASC, id ASC', 'id, courseid, name, visibility')
            : [];

        $names = [];
        $hidden = 0;
        foreach ($groups as $group) {
            if (\enrol_apply\local\groupvisibility::visible_to($group, (int) $USER->id)) {
                $names[] = format_string($group->name, true, ['context' => $coursecontext, 'escape' => false]);
            } else {
                $hidden++;
            }
        }
        if ($hidden) {
            $names[] = get_string('decisionsummaryhidden', 'enrol_apply', $hidden);
        }

        $role = (int) $instance->roleid > 0
            ? $DB->get_record('role', ['id' => (int) $instance->roleid])
            : false;
        $rolename = $role ? role_get_name($role, $coursecontext) : get_string('none');

        /* On the queue the summary speaks for every application at once, so it says that one a key
           sent for review, or one made with a chosen group (C-D10), also joins that group. The two
           never apply together: the method offers no choice in a key-only mode (C-D5). */
        $keynote = $userenrolmentid === 0 && $instance->customchar1 === \enrol_apply\local\groupkey::MODE_REVIEW;
        $chosennote = $userenrolmentid === 0 && !$keynote
            && \enrol_apply\local\groupchoice::mode($instance) !== \enrol_apply\local\instanceoptions::CHOICE_OFF;

        return [
            'hassummary' => true,
            // The parts the review page's outcome sentence is built from, read by nothing else.
            'summaryids' => array_map('intval', array_keys($groups)),
            'summarynames' => array_slice($names, 0, count($names) - ($hidden ? 1 : 0)),
            'summaryhidden' => $hidden,
            'summarygroupslabel' => get_string('decisionsummarygroups', 'enrol_apply'),
            'summarygroups' => $names ? implode(', ', $names) : get_string('none'),
            'summaryrolelabel' => get_string('decisionsummaryrole', 'enrol_apply'),
            'summaryrole' => format_string($rolename, true, ['context' => $coursecontext]),
            'summaryhelp' => get_string('decisionsummary_help', 'enrol_apply'),
            'hassummarykeynote' => $keynote || $chosennote,
            'summarykeynote' => $chosennote
                ? get_string('decisionsummarychosen', 'enrol_apply')
                : get_string('decisionsummarykey', 'enrol_apply'),
        ];
    }

    /**
     * What a group key did for this application, for the review page.
     *
     * The chip names the key group only to a decider or viewer in the course
     * ({@see self::reads_course_groups()}, V-D4) who may see that group by core's visibility rule
     * ({@see \enrol_apply\local\groupvisibility::visible_to()}), which the chooser's
     * groups_get_all_groups() applies too; anybody else, a mentor included, reads the outcome
     * alone. The groups offered ticked are the ones the key would have
     * joined - its own group and the method's list, key_route_groups() - so approving without
     * touching the chooser does what the key would have done had a place been left. A key group
     * deleted since offers nothing ticked and is not named.
     *
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @param stdClass|null $instance Enrol instance the application belongs to.
     * @param \context_course $coursecontext Course the application was made to.
     * @return array Keys 'chip' (plain spelling, empty when no key was presented), 'preselected'
     *     (group ids) and 'keygroupid' (the key group when the chip names it, 0 otherwise).
     */
    protected function key_context($application, $instance, \context_course $coursecontext): array {
        global $DB, $USER;

        $key = \enrol_apply\local\submission::key_of((int) $application->id);
        if ($key === null) {
            return ['chip' => '', 'preselected' => [], 'keygroupid' => 0];
        }

        /* The strip's short form for the reviewed key mode (RP-D10): the status chip beside it
           already says the application awaits a decision, so "sent for review" says it twice. The
           other outcomes keep their own wording, because each names what the key ran into. */
        $label = $key->outcome === \enrol_apply\local\submission::KEY_FOR_REVIEW
            ? get_string('keyoutcomeforreviewshort', 'enrol_apply')
            : \enrol_apply\local\submission::key_outcome_label($key->outcome);
        $group = $key->groupid > 0
            ? $DB->get_record(
                'groups',
                ['id' => $key->groupid, 'courseid' => $application->courseid],
                'id, courseid, name, visibility'
            )
            : false;
        if (
            !$group
            || !self::reads_course_groups($coursecontext)
            || !\enrol_apply\local\groupvisibility::visible_to($group, (int) $USER->id)
        ) {
            return ['chip' => $label, 'preselected' => [], 'keygroupid' => 0];
        }

        return [
            // Plain, for the same reason as the group names: the template double-stashes it.
            'chip' => get_string('keychipgroup', 'enrol_apply', (object) [
                'outcome' => $label,
                'group' => format_string($group->name, true, ['context' => $coursecontext, 'escape' => false]),
            ]),
            'preselected' => $instance === null
                ? []
                : enrol_get_plugin('apply')->key_route_groups($instance, (int) $group->id),
            'keygroupid' => (int) $group->id,
        ];
    }

    /**
     * The group the applicant chose for this application, for the review page (C-D4).
     *
     * Only for an application without a key route: a record never carries both, and the key chip
     * says what a key did ({@see self::key_context()}), so the two chips never show together. The
     * chip reads "Chose: <group>" to a reader {@see self::key_context()} would name a key group to
     * (a decider or viewer in the course who may see the group by core's visibility rule), and
     * "Chose a group" to anybody else, a mentor and a reader the group is hidden from included,
     * and for a group deleted since. The groups offered ticked are not read here: the approval's
     * own fallback, {@see \enrol_apply_plugin::default_approval_groups()}, already puts the chosen
     * group on top of the method's (C-D10), and the review page ticks that.
     *
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @param \context_course $coursecontext Course the application was made to.
     * @return array Keys 'chip' (plain spelling, empty when no group was chosen) and 'groupid'
     *     (the chosen group when the chip names it, 0 otherwise).
     */
    protected function chosen_context($application, \context_course $coursecontext): array {
        global $DB, $USER;

        // Null beside a key route too: the application is then filed under the key's group.
        $chosen = \enrol_apply\local\submission::chosen_group_of((int) $application->id);
        if ($chosen === null) {
            return ['chip' => '', 'groupid' => 0];
        }

        $group = $DB->get_record(
            'groups',
            ['id' => $chosen->groupid, 'courseid' => $application->courseid],
            'id, courseid, name, visibility'
        );
        if (
            !$group
            || !self::reads_course_groups($coursecontext)
            || !\enrol_apply\local\groupvisibility::visible_to($group, (int) $USER->id)
        ) {
            return ['chip' => get_string('chosenchipunnamed', 'enrol_apply'), 'groupid' => 0];
        }

        return [
            // Plain, for the same reason as the key chip: the template double-stashes it.
            'chip' => get_string('chosenchip', 'enrol_apply', format_string($group->name, true, [
                'context' => $coursecontext,
                'escape' => false,
            ])),
            'groupid' => (int) $group->id,
        ];
    }

    /**
     * Whether this reader may be told the names of the course's groups on the review page.
     *
     * A decider or a viewer in the course (V-D4): the queue's group column already names the key
     * group to both, so the review page withholding it from a viewer told them less than the
     * list they came from. A mentor holds neither in the course and is told no group name
     * anywhere. Each name is still judged by core's visibility rule on top of this.
     *
     * @param \context_course $coursecontext The course the application was made to.
     * @return bool True when the reader may see the course's group names here.
     */
    protected static function reads_course_groups(\context_course $coursecontext): bool {
        return has_capability('enrol/apply:manageapplications', $coursecontext)
            || has_capability('enrol/apply:viewapplications', $coursecontext);
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
     * What else this applicant has applied for in this course, as the course report shows it.
     *
     * Gated on enrol/apply:viewreports, which is deliberately narrower than the capability that
     * opens this page: the prior applications are the same disclosure the report exists to
     * control - what somebody applied for and what was decided - and an editing teacher holding
     * only manageapplications is not granted it by archetype. A reader without it sees no line,
     * no button and no list at all rather than an empty one, because a heading that appears only
     * when there is history is itself a disclosure.
     *
     * A reader the course report narrows to their groups (separate groups, without
     * moodle/site:accessallgroups, {@see \enrol_apply\local\groupscope::REPORT}) is listed the
     * records the report would list them, through the same {@see \enrol_apply\local\groupscope::record_where()}.
     *
     * Each record says only what a column of the course report says to the same reader, through
     * the report's own formatters: the outcome (worded from the record AND the live enrolment, so
     * a record that says approved for ever reads as removed once the enrolment is gone), the
     * dates, the decider's full name (the report's decider entity), the key group, the chosen
     * group and the joined groups (each judged by core's visibility rule for this reader) and the
     * decision note. The
     * message written to the applicant is not one of them, so it is never selected here.
     *
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @param \context_course $coursecontext Course the application was made to.
     * @return array Template context: hashistory, its labels, the report link (to the report of the
     *     application's own method,
     *     offered only when hasreportlink says the reader can open it) and one entry per prior application.
     */
    protected function history_context($application, $coursecontext): array {
        global $DB, $USER;

        if (!has_capability('enrol/apply:viewreports', $coursecontext)) {
            return ['hashistory' => false, 'history' => []];
        }

        $formatter = \enrol_apply\reportbuilder\local\formatters\submission::class;
        $priors = \enrol_apply\local\queue::prior_applications(
            (int) $application->courseid,
            (int) $application->userid,
            (int) ($application->submissionid ?? 0),
            // The report's own narrowing: a reader the course report narrows sees the same records here.
            \enrol_apply\local\groupscope::narrowed_to(
                (int) $application->courseid,
                (int) $USER->id,
                \enrol_apply\local\groupscope::REPORT
            )
        );

        // The deciders, in one read, worded as the report's decider column words a user's name.
        $deciderids = array_values(array_unique(array_filter(array_map(
            static fn(stdClass $prior): int => (int) $prior->decidedby,
            $priors
        ))));
        $deciders = $deciderids
            ? $DB->get_records_list('user', 'id', $deciderids, '', 'id, ' . implode(', ', \core_user\fields::get_name_fields()))
            : [];
        $viewfullnames = has_capability('moodle/site:viewfullnames', $coursecontext);
        $short = get_string('strftimedatetimeshort', 'langconfig');

        $rows = [];
        foreach ($priors as $prior) {
            $outcome = $formatter::outcome($prior->status, $prior);
            $decider = $deciders[(int) $prior->decidedby] ?? null;
            $applied = userdate((int) $prior->timecreated, $short);
            $note = trim((string) ($prior->decisionnote ?? ''));
            /* Both group cells come out of the report's formatters escaped, as a Report Builder
               cell is raw HTML, and the template triple stashes them: format_string() escapes each
               name once, and a group the reader may not see leaves its cell empty. */
            $keygroup = $formatter::key_group($prior->keygroupname, $prior);
            $chosengroup = $formatter::chosen_group($prior->chosengroupname ?? null, $prior);
            $joined = $formatter::joined_groups($prior->joinedgroups, $prior);
            $rows[] = [
                'hasfacts' => $decider !== null || $keygroup !== '' || $chosengroup !== '' || $joined !== '' || $note !== '',
                'outcome' => $outcome,
                'outcomeclass' => self::outcome_badge($outcome),
                'applieddate' => $applied,
                'applied' => get_string('reviewappliedon', 'enrol_apply', $applied),
                'hasdecided' => (int) $prior->timedecided > 0,
                'decided' => (int) $prior->timedecided > 0
                    ? get_string('reviewhistorydecided', 'enrol_apply', userdate((int) $prior->timedecided, $short))
                    : '',
                'hasdecider' => $decider !== null,
                'decider' => $decider ? fullname($decider, $viewfullnames) : '',
                'haskeygroup' => $keygroup !== '',
                'keygroup' => $keygroup,
                'haschosengroup' => $chosengroup !== '',
                'chosengroup' => $chosengroup,
                'hasjoined' => $joined !== '',
                'joined' => $joined,
                'hasnote' => $note !== '',
                // Escaped with the decider's line breaks kept, as the strip's own note is.
                'note' => format_text($note, FORMAT_PLAIN),
            ];
        }

        return [
            'hashistory' => (bool) $rows,
            'historylabel' => get_string('reviewhistory', 'enrol_apply'),
            'history' => $rows,
            'historydeciderlabel' => get_string('submissiondecidedby', 'enrol_apply'),
            'historykeygrouplabel' => get_string('keygroup', 'enrol_apply'),
            'historychosengrouplabel' => get_string('chosengroupcolumn', 'enrol_apply'),
            'historyjoinedlabel' => get_string('joinedgroups', 'enrol_apply'),
            'historynotelabel' => get_string('decisionnote', 'enrol_apply'),
            'historyreportlabel' => get_string('reviewhistoryreport', 'enrol_apply'),
            'hasreportlink' => $this->can_open_report($application),
            'historyreporturl' => (new moodle_url('/enrol/apply/report.php', ['id' => (int) $application->enrolid]))->out(false),
        ];
    }

    /**
     * "Already in this course": the applicant's other enrolments in the application's course (C-D5).
     *
     * One entry per other user enrolment of the applicant in the course, through any enrolment
     * method, in the course's method order: the method's name, its status, the roles that method
     * gave them there, and for this plugin's methods the groups joined through that method and how
     * it was admitted. All of it is what was recorded, never recomputed: the status is the
     * enrolment's own (and, for an application of this plugin's still waiting, the record's), the
     * roles are the assignments stamped with the method (component and itemid, as
     * complete_approval() and every protected enrol plugin stamp them), and the groups and the
     * admission come off the newest record of that enrolment (joinedgroups, keyoutcome, keygroupid,
     * decidedby, timedecided). A role assigned outside any method (core's manual and self
     * enrolments stamp nothing) cannot be told apart by method, so it is listed once under the
     * entries rather than credited to one of them.
     *
     * Names follow the page's rules. Group names only for a reader {@see self::reads_course_groups()}
     * admits, each judged by core's visibility rule for them
     * ({@see \enrol_apply\local\groupvisibility::visible_to()}); a group they may not see is left
     * out and not counted, as in the course report's "Groups joined" (V-D13), and a key group they
     * may not see leaves the admission unnamed. The decider's name only for a reader holding
     * enrol/apply:viewreports, the gate of the earlier applications panel
     * ({@see self::history_context()}), which is where the page already names deciders; anybody
     * else reads the date alone.
     *
     * Spellings: the method's name and the role names are normalised to the escaped spelling and
     * triple stashed, because get_instance_name() and role_get_name() each return a bare language
     * string for some records and format_string() output for others; format_string() leaves the
     * escaped half as it is. The group names and the admission line are plain, for a double stash.
     *
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @param \context_course $coursecontext Course the application was made to.
     * @return array Template context for enrol_apply/review_enrolments.
     */
    protected function enrolments_context($application, \context_course $coursecontext): array {
        global $DB, $USER;

        $courseid = (int) $application->courseid;
        $userid = (int) $application->userid;

        /* Core's own gate for reading a user's enrolments in a course (the participants page's status
           column): a mentor, who holds nothing in the course, and a moderator without it see no panel. */
        if (!has_capability('moodle/course:enrolreview', $coursecontext)) {
            return ['hasenrolments' => false, 'enrolments' => []];
        }
        /* A reader the course report narrows sees, of this plugin's own methods, only the records the
           report would show them: their groups, and no method decided only by those who see every
           group (groupscope::record_where()). */
        $narrowed = \enrol_apply\local\groupscope::narrowed_to($courseid, (int) $USER->id, \enrol_apply\local\groupscope::REPORT);

        $userenrolments = $DB->get_records_sql(
            "SELECT ue.id, ue.enrolid, ue.status, ue.timestart, ue.timeend
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid AND ue.userid = :userid AND ue.id <> :ueid
           ORDER BY e.sortorder, e.id",
            ['courseid' => $courseid, 'userid' => $userid, 'ueid' => (int) $application->id]
        );
        if (!$userenrolments) {
            return ['hasenrolments' => false, 'enrolments' => []];
        }

        $instances = enrol_get_instances($courseid, false);
        $namesgroups = self::reads_course_groups($coursecontext);
        $namesdecider = has_capability('enrol/apply:viewreports', $coursecontext);
        $viewfullnames = has_capability('moodle/site:viewfullnames', $coursecontext);
        $short = get_string('strftimedatetimeshort', 'langconfig');
        $now = time();

        // Every role assignment the applicant holds in the course, stamped or not, in one read.
        $assignments = $DB->get_records(
            'role_assignments',
            ['userid' => $userid, 'contextid' => $coursecontext->id],
            'id ASC',
            'id, roleid, component, itemid'
        );
        $rolenames = role_fix_names(get_all_roles($coursecontext), $coursecontext, ROLENAME_ALIAS, true);
        $rolename = static fn(int $roleid): string => isset($rolenames[$roleid])
            ? format_string($rolenames[$roleid], true, ['context' => $coursecontext])
            : '';

        $entries = [];
        foreach ($userenrolments as $ue) {
            $instance = $instances[(int) $ue->enrolid] ?? null;
            $plugin = $instance ? enrol_get_plugin($instance->enrol) : null;
            if (!$instance || !$plugin) {
                // A method whose plugin is gone from the site: core lists nothing for it either.
                continue;
            }
            $isapply = $instance->enrol === 'apply';
            $record = $isapply ? $this->newest_record((int) $ue->id) : null;
            if ($isapply && $narrowed !== null && !$this->record_in_scope($record, $narrowed)) {
                continue;
            }

            [$status, $statusclass] = $this->enrolment_status($ue, $instance, $record, $now);

            $roles = [];
            foreach ($assignments as $assignment) {
                if ($assignment->component === 'enrol_' . $instance->enrol && (int) $assignment->itemid === (int) $instance->id) {
                    $roles[(int) $assignment->roleid] = $rolename((int) $assignment->roleid);
                }
            }
            $roles = array_filter($roles, static fn(string $name): bool => $name !== '');

            $groups = '';
            $admission = '';
            if ($record !== null) {
                if ($namesgroups && $record->joinedgroups !== null && trim((string) $record->joinedgroups) !== '') {
                    $groups = implode(', ', $this->visible_group_names(
                        array_values(array_filter(array_map('intval', explode(',', (string) $record->joinedgroups)))),
                        $coursecontext
                    )[0]);
                }
                $admission = $this->admission_line($record, $userid, $namesgroups, $namesdecider, $viewfullnames, $short);
            }

            $entries[] = [
                'method' => format_string($plugin->get_instance_name($instance), true, ['context' => $coursecontext]),
                'status' => $status,
                'statusclass' => $statusclass,
                'hasrole' => (bool) $roles,
                'role' => implode(', ', $roles),
                'hasgroups' => $groups !== '',
                'groups' => $groups,
                'hasadmission' => $admission !== '',
                'admission' => $admission,
                'hasfacts' => $roles || $groups !== '' || $admission !== '',
            ];
        }

        $others = [];
        foreach ($assignments as $assignment) {
            if ((string) $assignment->component === '') {
                $others[(int) $assignment->roleid] = $rolename((int) $assignment->roleid);
            }
        }
        $others = array_filter($others, static fn(string $name): bool => $name !== '');

        return [
            'hasenrolments' => (bool) $entries,
            'enrolmentsheading' => get_string('reviewenrolments', 'enrol_apply'),
            'enrolmentsrolelabel' => get_string('reviewenrolmentsrole', 'enrol_apply'),
            'enrolmentsgroupslabel' => get_string('joinedgroups', 'enrol_apply'),
            'enrolmentsadmissionlabel' => get_string('reviewenrolmentsadmission', 'enrol_apply'),
            'enrolments' => $entries,
            'hasotherroles' => $entries && $others,
            'otherroleslabel' => get_string('reviewenrolmentsotherroles', 'enrol_apply'),
            'otherroles' => implode(', ', $others),
        ];
    }

    /**
     * Whether a record of this plugin's is one the course report shows a narrowed reader.
     *
     * The report's own predicate ({@see \enrol_apply\local\groupscope::record_where()}) over the one
     * record, so the panel and the report cannot disagree. No record at all (an application older
     * than the durable record) is shown to nobody narrowed.
     *
     * @param stdClass|null $record The newest record of the enrolment, or null.
     * @param array $groups The reader's groups, as groupscope::narrowed_to() returns them.
     * @return bool True when the report would show it to this reader.
     */
    protected function record_in_scope(?stdClass $record, array $groups): bool {
        global $DB;

        if ($record === null) {
            return false;
        }
        [$where, $params] = \enrol_apply\local\groupscope::record_where('s', $groups, 'panelscope');
        if ($where === '1 = 0') {
            return false;
        }

        return $DB->record_exists_sql(
            "SELECT 1 FROM {enrol_apply_submission} s WHERE s.id = :panelrecord AND {$where}",
            $params + ['panelrecord' => (int) $record->id]
        );
    }

    /**
     * The newest durable record of a user enrolment, with what the "Already in this course" panel reads.
     *
     * Newest first, as every reader of the record orders it (key_of(), chosen_groups()).
     *
     * @param int $userenrolmentid The user enrolment.
     * @return stdClass|null The record, or null when the enrolment has none.
     */
    protected function newest_record(int $userenrolmentid): ?stdClass {
        global $DB;

        $rows = $DB->get_records(
            'enrol_apply_submission',
            ['userenrolmentid' => $userenrolmentid],
            'timecreated DESC, id DESC',
            'id, courseid, status, keygroupid, keyoutcome, decidedby, timedecided, joinedgroups',
            0,
            1
        );

        return reset($rows) ?: null;
    }

    /**
     * The status of another enrolment of the applicant's, worded and coloured for its badge.
     *
     * Core's participants-page words for any method: active, suspended, or not current (an active
     * row outside its dates, or on a disabled method). This plugin's own methods add the two
     * states an application can be in, read from the enrolment and its record: on the waiting
     * list (ENROL_APPLY_USER_WAIT, a status core does not know) and awaiting a decision (suspended
     * while the record is still pending). Literal class pairs, so the badge scan sees each
     * background with its text colour.
     *
     * @param stdClass $ue The {user_enrolments} row: status, timestart and timeend.
     * @param stdClass $instance Its {enrol} record.
     * @param stdClass|null $record The newest record, for this plugin's methods; null otherwise.
     * @param int $now The time the page is judged at.
     * @return array Two elements: the plain label and the badge's classes.
     */
    protected function enrolment_status(stdClass $ue, stdClass $instance, ?stdClass $record, int $now): array {
        $status = (int) $ue->status;
        if ($instance->enrol === 'apply' && $status === ENROL_APPLY_USER_WAIT) {
            return [get_string('outcomewaiting', 'enrol_apply'), 'bg-info text-white'];
        }
        if (
            $instance->enrol === 'apply'
            && $status === ENROL_USER_SUSPENDED
            && $record !== null
            && (int) $record->status === \enrol_apply\local\submission::STATUS_PENDING
        ) {
            return [get_string('outcomeawaiting', 'enrol_apply'), 'bg-warning text-dark'];
        }
        if ($status !== ENROL_USER_ACTIVE) {
            return [get_string('participationsuspended', 'enrol'), 'bg-secondary text-dark'];
        }
        $current = (int) $instance->status === ENROL_INSTANCE_ENABLED
            && (int) $ue->timestart <= $now
            && ((int) $ue->timeend === 0 || (int) $ue->timeend > $now);

        return $current
            ? [get_string('participationactive', 'enrol'), 'bg-success text-white']
            : [get_string('participationnotcurrent', 'enrol'), 'bg-secondary text-dark'];
    }

    /**
     * How another of this plugin's enrolments was admitted, as its record says, or empty.
     *
     * By a group key while the key's admission still stands (the applicant recorded as the decider,
     * {@see \enrol_apply\local\submission::admitted_by_key()}), naming the key's group where the
     * reader may see it; otherwise by the approval the record names, with the decider's name only
     * where $namesdecider allows it. An application not approved has no admission to describe: its
     * status says where it stands.
     *
     * @param stdClass $record As newest_record() returns it.
     * @param int $userid The applicant.
     * @param bool $namesgroups Whether the reader may be told group names on this page at all.
     * @param bool $namesdecider Whether the reader may be told who decided.
     * @param bool $viewfullnames Whether the reader sees full names.
     * @param string $format The date format.
     * @return string The plain line, or empty.
     */
    protected function admission_line(
        stdClass $record,
        int $userid,
        bool $namesgroups,
        bool $namesdecider,
        bool $viewfullnames,
        string $format
    ): string {
        global $DB, $USER;

        $key = (object) [
            'groupid' => (int) $record->keygroupid,
            'outcome' => (int) $record->keyoutcome,
            'userid' => $userid,
            'decidedby' => (int) $record->decidedby,
        ];
        if (\enrol_apply\local\submission::admitted_by_key($key)) {
            $group = $key->groupid > 0
                ? $DB->get_record(
                    'groups',
                    ['id' => $key->groupid, 'courseid' => $record->courseid],
                    'id, courseid, name, visibility'
                )
                : false;
            if ($group && $namesgroups && \enrol_apply\local\groupvisibility::visible_to($group, (int) $USER->id)) {
                return get_string('reviewenrolmentsbykey', 'enrol_apply', format_string($group->name, true, [
                    'context' => \context_course::instance((int) $group->courseid),
                    'escape' => false,
                ]));
            }

            return get_string('reviewenrolmentsbykeyunnamed', 'enrol_apply');
        }

        if ((int) $record->status !== \enrol_apply\local\submission::STATUS_APPROVED || (int) $record->timedecided <= 0) {
            return '';
        }
        $when = userdate((int) $record->timedecided, $format);
        $decider = $namesdecider && (int) $record->decidedby > 0
            ? \core_user::get_user((int) $record->decidedby, '*', IGNORE_MISSING)
            : false;

        return $decider
            ? get_string('reviewenrolmentsapprovedby', 'enrol_apply', (object) [
                'who' => fullname($decider, $viewfullnames),
                'when' => $when,
            ])
            : get_string('reviewenrolmentsapprovedon', 'enrol_apply', $when);
    }

    /**
     * Whether the reader could open the course report the history links to.
     *
     * report.php asks for the capability AND require_login($course). The capability alone is not
     * enough: a holder through a category role who is not enrolled passes the first and is refused
     * by the second. The four-argument can_access_course() agrees with require_login() on a
     * suspended or expired enrolment, which the three-argument form does not.
     *
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @return bool Whether the link would lead somewhere the reader may go.
     */
    protected function can_open_report($application): bool {
        $course = get_course((int) $application->courseid);

        return can_access_course($course, null, 'enrol/apply:viewreports', true);
    }

    /**
     * The badge colour of an earlier application's outcome, keyed on the report's own wording.
     *
     * Keyed on the text {@see \enrol_apply\reportbuilder\local\formatters\submission::outcome()}
     * returned rather than on the record's status, so the colour cannot disagree with the words
     * beside it: an approval whose enrolment has since gone reads as removed, in grey, not as an
     * approval in green. Literal class pairs, so the badge scan sees each background with its text
     * colour.
     *
     * @param string $outcome The outcome as the report's formatter words it.
     * @return string The badge's background and text colour classes.
     */
    protected static function outcome_badge(string $outcome): string {
        return match ($outcome) {
            get_string('outcomeapproved', 'enrol_apply') => 'bg-success text-white',
            get_string('outcomecancelled', 'enrol_apply') => 'bg-danger text-white',
            get_string('outcomeawaiting', 'enrol_apply') => 'bg-warning text-dark',
            get_string('outcomewaiting', 'enrol_apply') => 'bg-info text-white',
            default => 'bg-secondary text-dark',
        };
    }

    /**
     * How much room the enrolment method has left, as numbers for the rules' meters.
     *
     * The numbers answer two different questions and must never be mixed: applicants counts every
     * non-expired row - pending, deferred and approved alike - while places counts active rows
     * only. The gap between them is overbooking, which is legitimate where approval is
     * discretionary. See \enrol_apply\local\capacity.
     *
     * Gated on the course capability, which is stricter than the page, for the same reason as
     * the group and role choosers ({@see self::decision_controls_context()}): a mentor, trusted
     * with one applicant, is not told the method's limits or its enrolment counts. A viewer never
     * reaches this (RP-D12): capacity stays a decider's.
     *
     * The deferred count is the reader's scope (V-D3): for a decider narrowed by separate groups it
     * is the queue tile's own scope_deferred(), so the review page and the queue they came from
     * cannot disagree; for everybody else the method's figure, capacity::deferred(). Each number is
     * counted only where its limit is set, since a method without one gets no meter and no count.
     *
     * @param stdClass $instance Enrol instance the application belongs to.
     * @return array hascapacity, and with it places, placestaken, limit, held, deferred and narrowed.
     */
    protected function capacity_context($instance): array {
        global $USER;

        $coursecontext = \context_course::instance($instance->courseid);
        if (!has_capability('enrol/apply:manageapplications', $coursecontext)) {
            return ['hascapacity' => false];
        }

        $capacity = \enrol_apply\local\capacity::class;
        $places = $capacity::places($instance);
        $limit = $capacity::applicant_limit($instance);
        $narrowed = \enrol_apply\local\groupscope::narrowed_to((int) $instance->courseid, (int) $USER->id) !== null;

        return [
            'hascapacity' => true,
            'places' => $places,
            'placestaken' => $places > 0 ? $capacity::places_taken($instance) : 0,
            'limit' => $limit,
            'held' => $limit > 0 ? $capacity::applicants($instance) : 0,
            'deferred' => (int) ($narrowed
                ? \enrol_apply\table\applications::for_scope((int) $instance->id)->scope_deferred()
                : $capacity::deferred($instance)),
            'narrowed' => $narrowed,
        ];
    }

    /**
     * The snapshot keys the instance asks for as required, by its CURRENT definitions.
     *
     * Metadata, not a value, so reading it live is sound where reading a live VALUE for a snapshot
     * key is not ({@see self::snapshot_context()}): the profile fields' per-instance required flag
     * from {@see \enrol_apply\local\fields::resolve()}, and each application question's own
     * configdata "required" from {@see \enrol_apply\local\answers::fields()}. The keys are built
     * by the same helpers that build a snapshot's keys, so a match is exact; a stored key nothing
     * asks for now reads as not required.
     *
     * @param stdClass $instance Enrol instance the application belongs to.
     * @return array Snapshot key => true, for every field the instance requires.
     */
    protected static function required_keys(stdClass $instance): array {
        $required = [];

        $profile = \enrol_apply\local\fields::resolve($instance);
        foreach ($profile->keys() as $key) {
            if ($profile->is_required($key)) {
                $required[$key] = true;
            }
        }

        foreach (\enrol_apply\local\answers::fields($instance) as $field) {
            if ($field->get_configdata_property('required')) {
                $required[\enrol_apply\local\answers::key((int) $field->get('id'))] = true;
            }
        }

        return $required;
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
     * stored keys come from enrol_apply_answer, which restore_enrol_apply_plugin writes verbatim
     * from a foreign archive. fields::current_value() dereferences any {user} column an "s_" key names
     * (fields::DENY governs only the write path) and reads a "c_<id>" key from {user_info_data}
     * past core's PROFILE_VISIBLE_* gates, and a reader for whom visible_keys() returns
     * ALL_FIELDS skips the key filter entirely - so a crafted archive could expose any column,
     * the password hash included. The report does not read the live profile either. Whether a
     * field is required is the instance's definition, read by {@see self::required_keys()}: that
     * is metadata keyed by the stored key, and it never dereferences a value.
     *
     * Masked with the report's own rule in the course context. A mentor holds nothing in the
     * course, so they see the name fields only, even where their mentor role grants the identity
     * capability in the applicant's user context: the stricter reading, as in the report. The
     * counts are taken over the rows the reader may see, so a withheld field changes no number.
     *
     * Every value is the plain spelling and the template double stashes it. Not format_string(),
     * whose strip_tags() would delete a restored value from the first "<" onwards. A value can
     * hold newlines (a textarea custom field is offerable), so the template carries the report
     * cell's white-space rule rather than converting them to markup.
     *
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @param stdClass $instance Enrol instance the application belongs to.
     * @return array Template context: hassnapshot, its label and counts, and one tile per visible field.
     */
    protected function snapshot_context($application, stdClass $instance): array {
        $formatter = \enrol_apply\reportbuilder\local\formatters\submission::class;
        $entries = \enrol_apply\local\submission::read_snapshot((int) ($application->submissionid ?? 0));
        $visible = $formatter::visible_keys(\context_course::instance($application->courseid));
        $required = $entries ? self::required_keys($instance) : [];

        $rows = [];
        foreach ($entries as $entry) {
            if (!$formatter::shows($entry['key'], $visible)) {
                /* Withheld from every row rather than only from the rows holding a value: a
                   marker that appears exactly where there is data is a presence oracle. */
                continue;
            }

            $rows[] = [
                'label' => $entry['label'],
                'value' => $entry['value'],
                'isempty' => trim($entry['value']) === '',
                'isrequired' => isset($required[$entry['key']]),
            ];
        }

        $filled = count(array_filter($rows, static fn(array $row): bool => !$row['isempty']));
        $requiredcount = count(array_filter($rows, static fn(array $row): bool => $row['isrequired']));

        return [
            'hassnapshot' => (bool) $rows,
            'snapshotlabel' => get_string('submittedprofile', 'enrol_apply'),
            'snapshot' => $rows,
            'snapshotcount' => get_string('reviewdetailscount', 'enrol_apply', (object) [
                'filled' => $filled,
                'total' => count($rows),
            ]),
            'hassnapshotrequired' => $requiredcount > 0,
            'snapshotrequired' => $requiredcount === 1
                ? get_string('reviewdetailsrequiredone', 'enrol_apply')
                : get_string('reviewdetailsrequired', 'enrol_apply', $requiredcount),
            'requiredmark' => get_string('reviewdetailsrequiredmark', 'enrol_apply'),
            'emptyvalue' => get_string('reviewdetailsempty', 'enrol_apply'),
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
        /* No heading here: core already renders the course's name as the page's <h1> from
           $PAGE->set_heading(), as every course page does; the applicant is named in the
           breadcrumb, the title and the card, and the <h2>s belong to the blocks below. */

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
     * The single-application decision form, in two columns (Visual 1).
     *
     * The POST carries the queue's own contract - formaction, userenrolments[] and the session
     * key - so every guard manage.php applies to a queue decision applies here unchanged. The one
     * branch manage.php adds for this page is the confirmation before a cancellation, and the
     * confirmed request arrives on the same contract and passes the same guards.
     *
     * The left column answers the decider's questions in the order they are asked: the state (the
     * strip), the limits the decision touches (the rules), what the applicant sent (comment and
     * details), what confirming does (the outcome card, with the adjustments folded under it) and
     * the message and note. The right column is the applicant's card. On a phone the columns stack
     * in that source order, so the card comes after the left column's blocks. Every block reads a
     * helper that already existed or a formatter the course report runs; the new code classifies
     * and words.
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
        $keyed = $this->key_context($application, $instance, $coursecontext);
        $chosen = $this->chosen_context($application, $coursecontext);
        /* The application's group as this reader may be told it, by key or by choice (C-D4): what
           the outcome and the rules single out. A key route keeps its preselection (the groups the
           key would have joined); a choice needs none, because the approval's defaults carry it. */
        $route = $keyed['keygroupid']
            ? $keyed + ['chosen' => false]
            : ['chip' => '', 'preselected' => [], 'keygroupid' => $chosen['groupid'], 'chosen' => $chosen['groupid'] > 0];

        /* A reader the page admits who may not decide (a holder of enrol/apply:viewapplications
           alone, S5) reads the same blocks with no decision form: no hidden contract, no
           controls, no decisions. Asked of can_manage_application(), the predicate every decision
           applies, so the form appears exactly where a decision posted from it would be taken. */
        $candecide = enrol_get_plugin('apply')->can_manage_application(
            (int) $application->courseid,
            (int) $application->userid,
            (int) $application->id
        );

        $controls = $candecide
            ? $this->decision_controls_context($instance, $message, $note, $keyed['preselected'], (int) $application->id)
            : [];
        /* The seats of the groups an approval joins are a decider's (V-D3): read for a course
           decider who may not choose as well, whose chooser never ran, and for nobody else. */
        if ($controls && !$controls['groupfacts'] && $controls['coursedecider']) {
            $controls['groupfacts'] = $this->group_facts($instance, $coursecontext);
        }
        $capacity = $candecide ? $this->capacity_context($instance) : ['hascapacity' => false];

        $snapshot = $this->snapshot_context($application, $instance);
        $history = $this->history_context($application, $coursecontext);
        $hascomment = trim((string) $application->applycomment) !== '';

        $outcome = $candecide
            ? $this->review_outcome_context($application, $applicant, $instance, $coursecontext, $controls, $route, $capacity)
            : ['effective' => [], 'context' => []];

        $context = $controls
            + $outcome['context']
            + $this->review_rules_context(
                $controls['groupfacts'] ?? [],
                $outcome['effective'],
                $route['keygroupid'],
                $capacity,
                $history,
                $route['chosen']
            )
            + $this->enrolments_context($application, $coursecontext)
            + $snapshot
            + $this->applicant_context($applicant, $application, $coursecontext)
            + $history
            + $this->decision_context($application)
            + $this->review_summary_context($application, $candecide, $waiting)
            + [
            'candecide' => $candecide,
            // The reader's own application says why it is not theirs to decide.
            'viewonlynotice' => \enrol_apply\local\queue::is_own_application((int) $application->userid)
                ? get_string('reviewownapplication', 'enrol_apply')
                : get_string('reviewviewonly', 'enrol_apply'),
            'formurl' => $manageurl->out(false),
            'sesskey' => sesskey(),
            'userenrolmentid' => (int) $application->id,
            'haskeychip' => $keyed['chip'] !== '',
            'keychip' => $keyed['chip'],
            // Never beside the key chip: chosen_context() answers nothing for a key route.
            'haschosenchip' => $chosen['chip'] !== '',
            'chosenchip' => $chosen['chip'],
            'statuslabel' => get_string('submissionstatus', 'enrol_apply'),
            'status' => $waiting
                ? get_string('outcomewaiting', 'enrol_apply')
                : get_string('outcomeawaiting', 'enrol_apply'),
            'iswaiting' => $waiting,
            /* The plugin's own chip classes rather than Bootstrap's bg-* utilities, whose
               !important no plugin rule may outrank; each pairs a background with its text colour
               in styles.css. */
            'statuschipclass' => $waiting ? 'enrol_apply-statuschip-deferred' : 'enrol_apply-statuschip-pending',
            /* Plain, not escaped: review_evidence.mustache renders this through a double stash.
               The label's other two sinks render raw, which is why the helper takes a flag. */
            'commentlabel' => \enrol_apply\local\commentlabel::custom($instance, false),
            'hascomment' => $hascomment,
            /* Escaped once with the applicant's line breaks kept, as in the queue's own cell:
               format_text(FORMAT_PLAIN) escapes and converts newlines and nothing else, so the
               template triple stashes it, as it does the decision message and note.

               Not format_string(): its strip_tags() would delete the text from the first "<"
               onwards, and a restore writes the comment verbatim out of a foreign archive. */
            'comment' => format_text((string) $application->applycomment, FORMAT_PLAIN),
            /* One muted line when there is nothing to read, judged on what THIS reader is shown:
               the details are counted after the masking, so the line says "to show" rather than
               claiming nothing was sent, and it cannot tell a masked reader that something was. */
            'hasnothing' => !$hascomment && !$snapshot['hassnapshot'],
            'nothingsent' => get_string('reviewnothingsent', 'enrol_apply'),
            /* Labels of their own, naming the one application: the queue's btnconfirm and its
               siblings name a decision over a selection.

               Order is the layout: the bar spreads these with justify-content-between, so the
               destructive decision sits at the far edge, away from the others, and the approval
               where the eye finishes. Cancel gets its own style because it is the only
               irreversible one: cancel_enrolment() unenrols, taking the row and the applicant's
               comment with it. See enrol_apply/review_actions for why its being the default
               submit is safe.

               The short label is what a phone shows under the icon, hidden from assistive
               technology (the full label stays the name). No line under a button says what it
               does: the outcome card above the bar says what confirming does. */
            'actions' => [
                [
                    'value' => 'cancel',
                    'label' => get_string('reviewcancel', 'enrol_apply'),
                    'shortlabel' => get_string('reviewcancelshort', 'enrol_apply'),
                    'style' => 'btn-outline-danger',
                    'icon' => 'e/cancel',
                ],
                [
                    'value' => 'wait',
                    'label' => get_string('reviewwait', 'enrol_apply'),
                    'shortlabel' => get_string('reviewwaitshort', 'enrol_apply'),
                    'style' => 'btn-secondary',
                    'icon' => 'i/duration',
                ],
                [
                    'value' => 'confirm',
                    'label' => get_string('reviewconfirm', 'enrol_apply'),
                    'shortlabel' => get_string('reviewconfirmshort', 'enrol_apply'),
                    'style' => 'btn-primary',
                    'icon' => 't/check',
                ],
            ],
        ];

        /* The decisions go into a core sticky footer interpolated inside the form, as on the
           queue (see manage_form()): only the buttons, because the footer's content area clips with
           no scrollbar.

           The spreading class is on the partial's own row, not passed to the footer: the footer
           applies its classes to a content area whose only child is that row, so there it would
           have nothing to spread. */
        $context['stickyfooter'] = '';
        if ($candecide) {
            $bar = $this->render_from_template('enrol_apply/review_actions', [
                'actions' => $context['actions'],
            ]);
            $context['stickyfooter'] = $this->render(new \core\output\sticky_footer($bar));
        }

        /* The search over the groups that are not pinned (RP-D20): core's own autocomplete,
           client side, with no ajax module, enhancing a plain multiple select that posts groups[]
           without JavaScript. The selection stays open on a pick, as core keeps it for a multiple
           select, so several groups can be added in one pass. */
        if (!empty($context['hasaddgroups'])) {
            $this->page->requires->js_call_amd('core/form-autocomplete', 'enhance', [
                '#enrol_apply_addgroups',
                false,
                false,
                get_string('reviewaddgroupsearch', 'enrol_apply'),
                false,
                true,
                get_string('reviewaddgroupnone', 'enrol_apply'),
                false,
            ]);
        }
        // The outcome follows the ticks only where somebody may tick; the page is right without it.
        if (!empty($context['chooser'])) {
            $this->page->requires->js_call_amd('enrol_apply/review', 'init', ['#enrol_apply_review_form']);
        }
        /* The earlier applications open in a core/modal from the rules' button. The list is the
           server's own region, shown in place without JavaScript, so the page needs none of this. */
        if ($context['hashistory']) {
            $this->page->requires->js_call_amd('enrol_apply/review', 'initHistory', ['#enrol_apply_review_history']);
        }

        return $this->render_from_template('enrol_apply/review', $context);
    }

    /**
     * The strip that opens the left column: the state, and how long the application has waited.
     *
     * The status and key chips are built in review_form(), beside the decision they belong to;
     * this adds the read-only chip of a reader who may not decide (RP-D12) and the wait: how
     * long it has been in its state and when it was applied for. A pending application waits
     * from the day it was applied for; a deferred one from the day it was deferred, which is what
     * "on the waiting list for" means, falling back to the application date when the record
     * carries no decision date. One neutral colour whatever the age (decision 10): the plugin sees
     * time and the decider judges it.
     *
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @param bool $candecide Whether this reader may decide it.
     * @param bool $waiting Whether it is on the waiting list.
     * @return array Template context for enrol_apply/review_summary.
     */
    protected function review_summary_context($application, bool $candecide, bool $waiting): array {
        $applied = (int) $application->applydate;
        $deferred = (int) ($application->timedecided ?? 0);
        $since = $waiting && $deferred > 0 ? $deferred : $applied;
        $short = get_string('strftimedatetimeshort', 'langconfig');

        return [
            'readonly' => !$candecide,
            'readonlylabel' => get_string('reviewreadonly', 'enrol_apply'),
            'haswaited' => $since > 0,
            'waitlabel' => $waiting
                ? get_string('reviewwaitingdeferred', 'enrol_apply')
                : get_string('reviewwaiting', 'enrol_apply'),
            /* format_time() reads no time at all as "now", which "Awaiting a decision for" cannot
               carry, so the first second is counted as one. */
            'waited' => $since > 0 ? format_time(max(1, time() - $since)) : '',
            'hasapplied' => $applied > 0,
            'appliedon' => $applied > 0
                ? get_string('reviewappliedon', 'enrol_apply', userdate($applied, $short))
                : '',
        ];
    }

    /**
     * The applicant's card: picture, name, the identity fields this reader may see, the profile.
     *
     * The picture is decoration beside the name, so it carries no link and no alternative text;
     * the name is the card's heading. The identity fields are {@see self::identity_context()}'s,
     * the one identity rule of this page. "View full profile" is offered only where
     * {@see \enrol_apply\local\profilelink::url()} says user/view.php would open it: a moderator
     * narrowed by separate groups is refused the profile of an applicant who is in no group yet.
     * No contact buttons: the profile is the route to contact details for a reader entitled to
     * them.
     *
     * @param stdClass $applicant Applicant user record.
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @param \context_course $coursecontext Course the application was made to.
     * @return array Template context for enrol_apply/review_applicant.
     */
    protected function applicant_context($applicant, $application, \context_course $coursecontext): array {
        $profileurl = \enrol_apply\local\profilelink::url(
            \core_user::get_user((int) $application->userid, 'id, deleted', MUST_EXIST),
            get_course((int) $application->courseid)
        );

        return $this->identity_context($applicant, $coursecontext) + [
            // Core's own markup, triple stashed: user_picture() escapes what it renders.
            'picture' => $this->output->user_picture($applicant, [
                'size' => 100,
                'link' => false,
                'alttext' => false,
                'class' => 'userpicture enrol_apply-applicantpicture',
            ]),
            // Plain, for a double stash.
            'applicantname' => fullname($applicant),
            'applicantrole' => get_string('reviewapplicant', 'enrol_apply'),
            'hasprofile' => $profileurl !== null,
            'profileurl' => $profileurl ? $profileurl->out(false) : '',
            'profilelabel' => get_string('reviewfullprofile', 'enrol_apply'),
        ];
    }

    /**
     * How full a group card's seats are, by the queue meter's rule (RP-D3).
     *
     * Attention from four fifths, so a limit is seen before it is reached (the queue's applicant
     * meter warns there, queue_capacity_context()); over from the limit itself, members >= seats,
     * which is the chooser's full rule ({@see self::group_facts()}). Advisory: no state disables a
     * decision (R5, capacity::places_full()).
     *
     * @param int $taken How many the limit already holds.
     * @param int $total The limit, greater than zero.
     * @return string One of the SIGNAL_ constants: ok, warn or over.
     */
    protected static function signal_state(int $taken, int $total): string {
        if ($taken >= $total) {
            return self::SIGNAL_OVER;
        }

        return $taken * 5 >= $total * 4 ? self::SIGNAL_WARN : self::SIGNAL_OK;
    }

    /**
     * How full a limit is once this application is confirmed, by Visual 1's rule (decision 13).
     *
     * The occupancy AFTER confirming, in whole numbers so 60 % and 90 % themselves are exact:
     * primary up to 60 %, warning above it, danger above 90 % (and so over the limit). Advisory,
     * as every capacity signal of this plugin is: no level disables a decision.
     *
     * @param int $after How many the limit holds once the application is confirmed.
     * @param int $total The limit, greater than zero.
     * @return string One of the METER_ constants.
     */
    protected static function meter_level(int $after, int $total): string {
        if ($after * 100 > $total * 90) {
            return self::METER_DANGER;
        }

        return $after * 100 > $total * 60 ? self::METER_WARNING : self::METER_PRIMARY;
    }

    /**
     * One meter of "Rules to observe": a limit, its count, the bar and what confirming leaves.
     *
     * The bar is decoration beside the text that says the same thing (it is aria-hidden): the
     * seats already taken are striped and the applicant's own is solid, coloured by the level
     * AFTER confirming ({@see self::meter_level()}). The line under it carries the information,
     * one string per case so a count of one is never worded as a plural.
     *
     * @param string $label What the limit is, plain spelling.
     * @param string $kind 'group', 'places' or 'applicants', which picks the icon.
     * @param int $others How many the limit holds besides this application.
     * @param int $total The limit, greater than zero.
     * @param bool $counted Whether the count already includes this application (the applicant
     *        limit counts every application held), so the heading shows it.
     * @return array Template context for one meter.
     */
    protected static function meter(string $label, string $kind, int $others, int $total, bool $counted): array {
        $after = $others + 1;
        $level = self::meter_level($after, $total);
        $takenpercent = (int) min(100, round($others * 100 / $total));
        $afterpercent = (int) min(100, round($after * 100 / $total));
        $left = $total - $after;
        $a = (object) ['after' => $after, 'total' => $total, 'left' => $left];

        return [
            'label' => $label,
            'isgroup' => $kind === 'group',
            'isplaces' => $kind === 'places',
            'isapplicants' => $kind === 'applicants',
            'count' => get_string('reviewofmany', 'enrol_apply', (object) [
                'taken' => $counted ? $after : $others,
                'total' => $total,
            ]),
            'takenpercent' => $takenpercent,
            'ownpercent' => max(0, $afterpercent - $takenpercent),
            'level' => $level,
            // Literal class pairs, so the badge scan sees each background with its text colour.
            'barclass' => match ($level) {
                self::METER_DANGER => 'bg-danger text-white',
                self::METER_WARNING => 'bg-warning text-dark',
                default => 'bg-primary text-white',
            },
            'isdanger' => $level === self::METER_DANGER,
            'after' => match (true) {
                $left < 0 => get_string('reviewmeterafterover', 'enrol_apply', $a),
                $left === 0 => get_string('reviewmeterafterfull', 'enrol_apply', $a),
                $left === 1 => get_string('reviewmeterafterone', 'enrol_apply', $a),
                default => get_string('reviewmeterafter', 'enrol_apply', $a),
            },
        ];
    }

    /**
     * "Rules to observe": a meter per limit the decision touches, the deferred count, the history.
     *
     * Only what is present, and nothing at all when nothing is (decision 6). The meters, in
     * order: each group the approval joins as the page loads that declares seats, the key group
     * first; the method's places; the applicant limit. Seats come only from $facts, which only a
     * course decider gets and which name only the groups this reader may choose (V-D3); places,
     * the applicant limit and the deferred count only from $capacity, gated the same way. So a
     * viewer and a mentor get no meter, and a reader without enrol/apply:viewreports no history
     * line ({@see self::history_context()}).
     *
     * The applicant limit counts this application already (capacity::applicants() counts every
     * application held), so its meter shows the applicant's own seat inside the count and
     * confirming adds nothing to it. The deferred count is said only where an applicant limit
     * exists, because only there does a deferral hold room, and only when there is one to count.
     *
     * The meters follow the page as it loads; the outcome card's "Afterwards" line is what follows
     * the decider's ticks.
     *
     * @param array $facts Group facts, as group_facts() returns them; empty when not a course decider.
     * @param array $effective Group ids the approval joins as the page loads.
     * @param int $keygroupid The application's group (the key's, or the chosen one) when this reader
     *     may be told it, 0 otherwise.
     * @param array $capacity As capacity_context() returns it.
     * @param array $history As history_context() returns it.
     * @param bool $chosen Whether $keygroupid is the group the applicant chose rather than a key's.
     * @return array Template context for enrol_apply/review_rules.
     */
    protected function review_rules_context(
        array $facts,
        array $effective,
        int $keygroupid,
        array $capacity,
        array $history,
        bool $chosen = false
    ): array {
        $seated = [];
        foreach ($effective as $groupid) {
            if (isset($facts[$groupid]) && $facts[$groupid]->hasseats && $facts[$groupid]->seats > 0) {
                $seated[$groupid] = $facts[$groupid];
            }
        }
        if ($keygroupid && isset($seated[$keygroupid])) {
            $seated = [$keygroupid => $seated[$keygroupid]] + $seated;
        }

        $meters = [];
        foreach ($seated as $fact) {
            $label = $fact->name;
            if ($fact->id === $keygroupid) {
                $label = $chosen
                    ? get_string('reviewmetergroupchosen', 'enrol_apply', $fact->name)
                    : get_string('reviewmetergroupkey', 'enrol_apply', $fact->name);
            }
            $meters[] = self::meter(
                $label,
                'group',
                $fact->members,
                $fact->seats,
                false
            );
        }

        $deferred = '';
        if ($capacity['hascapacity']) {
            if ($capacity['places'] > 0) {
                $meters[] = self::meter(
                    get_string('reviewmeterplaces', 'enrol_apply'),
                    'places',
                    $capacity['placestaken'],
                    $capacity['places'],
                    false
                );
            }
            if ($capacity['limit'] > 0) {
                $meters[] = self::meter(
                    get_string('reviewmeterapplicants', 'enrol_apply'),
                    'applicants',
                    max(0, $capacity['held'] - 1),
                    $capacity['limit'],
                    true
                );
            }
            if ($capacity['limit'] > 0 && $capacity['deferred'] > 0) {
                $a = (object) ['count' => $capacity['deferred'], 'limit' => $capacity['limit']];
                $one = $capacity['deferred'] === 1;
                $yours = $capacity['narrowed'];
                $deferred = match (true) {
                    $yours && $one => get_string('reviewrulesdeferredyoursone', 'enrol_apply', $a),
                    $yours => get_string('reviewrulesdeferredyours', 'enrol_apply', $a),
                    $one => get_string('reviewrulesdeferredone', 'enrol_apply', $a),
                    default => get_string('reviewrulesdeferred', 'enrol_apply', $a),
                };
            }
        }

        $historyline = '';
        if (!empty($history['hashistory'])) {
            // Newest first, as queue::prior_applications() orders them.
            $last = reset($history['history']);
            $count = count($history['history']);
            $a = (object) ['count' => $count, 'outcome' => $last['outcome'], 'applied' => $last['applieddate']];
            $historyline = $count === 1
                ? get_string('reviewhistoryone', 'enrol_apply', $a)
                : get_string('reviewhistorymany', 'enrol_apply', $a);
        }

        return [
            'hasrules' => $meters || $deferred !== '' || $historyline !== '',
            'rulesheading' => get_string('reviewrules', 'enrol_apply'),
            'hasmeters' => (bool) $meters,
            'meters' => $meters,
            'hasdeferredline' => $deferred !== '',
            'deferredline' => $deferred,
            'hashistoryline' => $historyline !== '',
            'historyline' => $historyline,
            'historyopen' => get_string('reviewhistoryopen', 'enrol_apply'),
        ];
    }

    /**
     * Names of the given groups this reader may see, and how many they may not.
     *
     * The rule of approval_summary_context(): a group core hides from this reader
     * ({@see \enrol_apply\local\groupvisibility::visible_to()}) is counted, never named.
     *
     * @param array $groupids Group ids.
     * @param \context_course $coursecontext The course they belong to.
     * @return array Two elements: group id => plain name, natural order; and the hidden count.
     */
    protected function visible_group_names(array $groupids, \context_course $coursecontext): array {
        global $DB, $USER;

        $groups = $groupids
            ? $DB->get_records_list('groups', 'id', $groupids, 'id ASC', 'id, courseid, name, visibility')
            : [];
        \core_collator::asort_objects_by_property($groups, 'name', \core_collator::SORT_NATURAL);

        $names = [];
        $hidden = 0;
        foreach ($groups as $group) {
            if (\enrol_apply\local\groupvisibility::visible_to($group, (int) $USER->id)) {
                $names[(int) $group->id] = format_string($group->name, true, ['context' => $coursecontext, 'escape' => false]);
            } else {
                $hidden++;
            }
        }

        return [$names, $hidden];
    }

    /**
     * Wrap an ESCAPED part of the outcome sentence in <strong>.
     *
     * The only markup the sentence carries (decision 7): the role, the groups and the notice are
     * what the decider checks, so they are what the eye should find. The argument must already
     * be escaped; this adds the element and escapes nothing, so a name passes through s() exactly
     * once on its way here. enrol_apply/review builds the same elements as nodes.
     *
     * @param string $escaped Text already escaped.
     * @return string The text inside a strong element.
     */
    protected static function strong(string $escaped): string {
        return '<strong>' . $escaped . '</strong>';
    }

    /**
     * The sentence that says what confirming does, in its escaped spelling.
     *
     * One string per count of groups, none of them inflected for the applicant's gender: pt_br
     * cannot put an article before an arbitrary group name, and "recebe o aviso" fits anybody.
     * Every parameter arrives escaped and the bold parts are wrapped here ({@see self::strong()});
     * the strings' own text holds no markup, so the result is escaped exactly once and the
     * template triple stashes it.
     *
     * @param string $who The applicant's name, escaped.
     * @param string $role The role phrase, escaped, its name already in bold ("as <strong>Student</strong>").
     * @param array $names Group names, escaped.
     * @param int $hidden How many more groups the reader may not see.
     * @return string The sentence.
     */
    protected static function outcome_sentence(string $who, string $role, array $names, int $hidden): string {
        $items = array_values($names);
        if ($hidden) {
            $items[] = get_string('decisionsummaryhidden', 'enrol_apply', $hidden);
        }
        $a = (object) [
            'who' => $who,
            'role' => $role,
            'groups' => $items ? self::strong(implode(', ', $items)) : '',
            'notice' => self::outcome_notice(),
        ];
        $count = count($names) + $hidden;

        return match (true) {
            $count === 0 => get_string('reviewoutcomenonewithnotice', 'enrol_apply', $a),
            $count === 1 => get_string('reviewoutcomeonewithnotice', 'enrol_apply', $a),
            default => get_string('reviewoutcomemanywithnotice', 'enrol_apply', $a),
        };
    }

    /**
     * The notice part of the outcome sentence, escaped and in bold.
     *
     * @return string "is notified of the approval", in a strong element.
     */
    protected static function outcome_notice(): string {
        return self::strong(s(get_string('reviewoutcomenotice', 'enrol_apply')));
    }

    /**
     * "On confirming": what an approval does, the adjustments under it, and the message and note.
     *
     * The groups it names are the ones the approval really joins, from the helpers the approval
     * runs, so the sentence cannot promise what the decision does not do:
     *  - a decider who may choose: the groups ticked as the page loads, or, with none ticked,
     *    {@see \enrol_apply_plugin::default_approval_groups()}, because confirm_enrolment()
     *    records an empty choice and add_instance_groups() then falls back to it. An empty
     *    choice is not "no group": for an application a group key sent for review it is the key's
     *    group plus the method's, and the sentence under the cards says so;
     *  - a course decider who may not choose: the read-only summary, approval_summary_context();
     *  - a mentor, who holds nothing in the course: the role only, with no group named.
     *
     * Ticked as the page loads (RP-D19): the part of the key route this reader may choose, for an
     * application whose key group they may see; for any other the part of the method's defaults
     * they may choose, which for an application made with a choice is the chosen group on top of
     * the method's own (C-D10). Unticking the chosen group records a choice without it, and the
     * line under the sentence says the approval leaves it out, as for a key's group. The rest of
     * the route is kept: confirm_enrolment() joins the ticked groups plus the method's groups this
     * reader may not choose, so the sentence names those too, and a line under the cards says they
     * stay.
     *
     * Above groupfilter::THRESHOLD choosable groups (counted after the reader's narrowing,
     * RP-D18) only the ticked ones are cards and the rest are a search (RP-D20); at or below it
     * every choosable group is a card. "Adjust" is open as the page loads only when a ticked group
     * is over its seats, or when the method gives no group and there are groups to choose (RP-D5).
     *
     * @param stdClass $application Application as \enrol_apply\local\queue::application() returns it.
     * @param stdClass $applicant Applicant user record.
     * @param stdClass $instance Enrol instance the application belongs to.
     * @param \context_course $coursecontext The course it was made to.
     * @param array $controls As decision_controls_context() returns it, with groupfacts filled in.
     * @param array $keyed As key_context() returns it, with 'chosen' true when 'keygroupid' is the
     *     group the applicant chose rather than a key's (review_form() builds that from
     *     chosen_context()); absent reads as a key's.
     * @param array $capacity As capacity_context() returns it.
     * @return array Keys 'effective' (group ids joined as the page loads) and 'context' (template
     *     context for enrol_apply/review_outcome).
     */
    protected function review_outcome_context(
        $application,
        $applicant,
        stdClass $instance,
        \context_course $coursecontext,
        array $controls,
        array $keyed,
        array $capacity
    ): array {
        global $DB;

        $facts = $controls['groupfacts'];
        $chooser = $controls['chooser'];
        $coursedecider = $controls['coursedecider'];
        $keygroupid = $keyed['keygroupid'];
        // Whether that group is the one the applicant chose (C-D4) rather than a key's.
        $chosen = !empty($keyed['chosen']);

        // The approval's own fallback, read from the helper add_instance_groups() runs.
        $defaults = $chooser ? enrol_get_plugin('apply')->default_approval_groups($instance, (int) $application->id) : [];
        [$defaultnames, $defaulthidden] = $this->visible_group_names($defaults, $coursecontext);

        /* The method's groups this reader may not choose, which confirm_enrolment() keeps on top of
           any ticked choice; empty for a reader who may choose every group. */
        $kept = $chooser ? array_values(array_map('intval', array_diff($defaults, array_keys($facts)))) : [];
        [$keptnames, $kepthidden] = $this->visible_group_names($kept, $coursecontext);

        $ticked = [];
        if ($chooser) {
            $route = $keygroupid ? $keyed['preselected'] : [];
            if (!$route) {
                $route = $defaults;
            }
            $ticked = array_values(array_intersect(array_keys($facts), array_map('intval', $route)));
        }

        // What the approval joins as the page loads, and what this reader may be told of it.
        if ($chooser) {
            $effective = $ticked ? array_merge($ticked, $kept) : $defaults;
            $names = $ticked
                ? array_merge(array_map(static fn(int $id): string => $facts[$id]->name, $ticked), array_values($keptnames))
                : array_values($defaultnames);
            $hidden = $ticked ? $kepthidden : $defaulthidden;
        } else if ($coursedecider) {
            // Absent only when the summary was not built, which then names no group.
            $effective = $controls['summaryids'] ?? [];
            $names = $controls['summarynames'] ?? [];
            $hidden = $controls['summaryhidden'] ?? 0;
        } else {
            $effective = [];
            $names = [];
            $hidden = 0;
        }
        $escapednames = array_map('s', $names);

        /* The role: the instance's, which is what the chooser's default and the summary both give;
           a role name has no single spelling (role_get_name()), so it is normalised to the escaped
           one, as the chooser's are. */
        $role = (int) $instance->roleid > 0 ? $DB->get_record('role', ['id' => (int) $instance->roleid]) : false;
        $rolename = $role ? format_string(role_get_name($role, $coursecontext), true, ['context' => $coursecontext]) : '';
        $rolephrase = $rolename !== ''
            ? get_string('reviewoutcomerole', 'enrol_apply', self::strong($rolename))
            : self::strong(s(get_string('reviewoutcomenorole', 'enrol_apply')));

        $who = s(fullname($applicant));
        $sentence = $coursedecider
            ? self::outcome_sentence($who, $rolephrase, $escapednames, $hidden)
            : get_string('reviewoutcomenogroupinfowithnotice', 'enrol_apply', (object) [
                'who' => $who,
                'role' => $rolephrase,
                'notice' => self::outcome_notice(),
            ]);

        // Afterwards: each joined group that declares seats, then the method's places.
        $after = [];
        foreach ($effective as $groupid) {
            $fact = $facts[$groupid] ?? null;
            if ($fact === null || !$fact->hasseats) {
                continue;
            }
            $after[] = get_string('reviewafterseats', 'enrol_apply', (object) [
                'name' => s($fact->name),
                'members' => $fact->members + 1,
                'seats' => $fact->seats,
            ]);
        }
        if ($capacity['hascapacity'] && $capacity['places'] > 0) {
            $after[] = get_string('reviewafterplaces', 'enrol_apply', (object) [
                'taken' => $capacity['placestaken'] + 1,
                'total' => $capacity['places'],
            ]);
        }

        // The cards, and the search over the rest.
        $searched = count($facts) > \enrol_apply\local\groupfilter::THRESHOLD;
        $ingroupings = [];
        $groupings = [];
        if ($searched) {
            $coursedata = groups_get_course_data((int) $instance->courseid);
            foreach ($coursedata->mappings as $mapping) {
                $ingroupings[(int) $mapping->groupid][] = (int) $mapping->groupingid;
            }
        }
        $cards = [];
        $options = [];
        foreach ($facts as $fact) {
            $checked = in_array($fact->id, $ticked, true);
            if (!$searched || $checked) {
                $cards[] = $this->group_card(
                    $fact,
                    $checked,
                    $fact->id === $keygroupid,
                    in_array($fact->id, $defaults, true),
                    $chosen
                );
                continue;
            }
            $options[] = $this->group_option($fact, $ingroupings[$fact->id] ?? []);
            foreach ($ingroupings[$fact->id] ?? [] as $groupingid) {
                $groupings[$groupingid] = true;
            }
        }
        $groupingoptions = [];
        if ($groupings) {
            foreach ($coursedata->groupings as $grouping) {
                if (isset($groupings[(int) $grouping->id])) {
                    $groupingoptions[(int) $grouping->id] = (object) [
                        'id' => (int) $grouping->id,
                        'name' => format_string($grouping->name, true, ['context' => $coursecontext, 'escape' => false]),
                    ];
                }
            }
            \core_collator::asort_objects_by_property($groupingoptions, 'name', \core_collator::SORT_NATURAL);
        }

        $defaultitems = array_map('s', array_values($defaultnames));
        if ($defaulthidden) {
            $defaultitems[] = get_string('decisionsummaryhidden', 'enrol_apply', $defaulthidden);
        }

        $roledefault = $rolename !== ''
            ? get_string('reviewroledefaultnamed', 'enrol_apply', $rolename)
            : s(get_string('decisionroledefault', 'enrol_apply'));

        $hasgroups = $chooser && (bool) $facts;
        $hasroles = $chooser && !empty($controls['roles']);
        $anyfull = (bool) array_filter($ticked, static fn(int $id): bool => $facts[$id]->full);
        $message = (string) ($controls['messagevalue'] ?? '');
        $note = (string) ($controls['notevalue'] ?? '');

        return [
            'effective' => array_values(array_map('intval', $effective)),
            'context' => [
                'outcomeheading' => get_string('reviewoutcome', 'enrol_apply'),
                'outcomesentence' => $sentence,
                'hasoutcomeafter' => (bool) $after,
                'outcomeafter' => $after ? get_string('reviewoutcomeafter', 'enrol_apply', implode('; ', $after)) : '',
                'outcomenotkey' => $chooser && $keygroupid && isset($facts[$keygroupid])
                    && !in_array($keygroupid, $effective, true),
                'outcomenotkeytext' => $keygroupid && isset($facts[$keygroupid])
                    ? get_string(
                        $chosen ? 'reviewoutcomenotchosen' : 'reviewoutcomenotkey',
                        'enrol_apply',
                        s($facts[$keygroupid]->name)
                    )
                    : '',
                'outcomereadonly' => $coursedecider && !$chooser,
                'outcomereadonlyhelp' => get_string('decisionsummary_help', 'enrol_apply'),
                // What the AMD module rebuilds the sentence from; plain spellings for attributes.
                'outcomewho' => fullname($applicant),
                'outcomekeygroup' => $keygroupid,
                'outcomedefaultids' => implode(',', array_map('intval', $defaults)),
                'outcomedefaulthidden' => $defaulthidden,
                'outcomedefaultnames' => array_map(
                    static fn(string $name): array => ['name' => $name],
                    array_values($defaultnames)
                ),
                'outcomekepthidden' => $kepthidden,
                'outcomekeptnames' => array_map(
                    static fn(string $name): array => ['name' => $name],
                    array_values($keptnames)
                ),
                'haskept' => (bool) $kept,
                'keptgroups' => $kept ? get_string('reviewkeptgroups', 'enrol_apply', implode(', ', array_merge(
                    array_map('s', array_values($keptnames)),
                    $kepthidden ? [get_string('decisionsummaryhidden', 'enrol_apply', $kepthidden)] : []
                ))) : '',
                'outcomedefaultrole' => $rolename,
                'outcomeplaces' => $capacity['hascapacity'] ? $capacity['places'] : 0,
                'outcomeplacestaken' => $capacity['hascapacity'] ? $capacity['placestaken'] : 0,
                'hasadjust' => $hasgroups || $hasroles,
                'adjustopen' => $anyfull || ($hasgroups && !$ticked && !$defaults),
                'adjustlabel' => get_string('reviewadjust', 'enrol_apply'),
                'hasgroupchooser' => $hasgroups,
                'groupchooserlabel' => get_string('decisiongroups', 'enrol_apply'),
                'haspinnedhelp' => $searched,
                'pinnedhelp' => get_string('reviewpinnedhelp', 'enrol_apply'),
                'groupcards' => $cards,
                'hasgroupcards' => (bool) $cards,
                'hasaddgroups' => (bool) $options,
                'addgrouplabel' => get_string('reviewaddgroup', 'enrol_apply'),
                'addgroupcount' => get_string('reviewaddgroupcount', 'enrol_apply', count($facts)),
                'addgrouphelp' => get_string('reviewaddgrouphelp', 'enrol_apply'),
                'addgroups' => $options,
                'hasgroupings' => (bool) $groupingoptions,
                'groupinglabel' => get_string('reviewgrouping', 'enrol_apply'),
                'groupingall' => get_string('reviewgroupingall', 'enrol_apply'),
                'groupings' => array_values($groupingoptions),
                'emptychoice' => $defaultitems
                    ? get_string('reviewemptychoice', 'enrol_apply', implode(', ', $defaultitems))
                    : s(get_string('reviewemptychoicenone', 'enrol_apply')),
                'hasrolechooser' => $hasroles,
                'roledefaultoption' => $roledefault,
                // One disclosure for both boxes, open whenever either holds a value (RP-D7).
                'writeopen' => trim($message) !== '' || trim($note) !== '',
                'writelabel' => get_string('reviewwrite', 'enrol_apply'),
                'writehelp' => get_string('reviewwritehelp', 'enrol_apply'),
            ],
        ];
    }

    /**
     * One group as a checkbox card of "Adjust" (RP-D6).
     *
     * The occupancy bar is decoration beside the text that says the same thing: a progressbar
     * whose values are seats, not percent, labelled with the seats sentence. A full group says
     * "no seats left" in words and stays tickable (R5).
     *
     * @param stdClass $fact As group_facts() returns one.
     * @param bool $checked Whether it is ticked as the page loads.
     * @param bool $iskey Whether it is the application's group: the key's, or the chosen one.
     * @param bool $ismethod Whether the approval's defaults name it.
     * @param bool $chosen Whether the application's group is the one the applicant chose, which
     *     words its badge.
     * @return array Template context for one card.
     */
    protected function group_card(stdClass $fact, bool $checked, bool $iskey, bool $ismethod, bool $chosen = false): array {
        $state = $fact->hasseats ? self::signal_state($fact->members, $fact->seats) : self::SIGNAL_OK;

        return [
            'id' => $fact->id,
            'name' => $fact->name,
            'checked' => $checked,
            'iskey' => $iskey,
            'keybadge' => $chosen ? get_string('reviewgroupchosen', 'enrol_apply') : get_string('reviewgroupkey', 'enrol_apply'),
            'ismethod' => $ismethod && !$iskey,
            'methodbadge' => get_string('reviewgroupmethod', 'enrol_apply'),
            'hasseats' => $fact->hasseats,
            'members' => $fact->members,
            'seats' => $fact->seats,
            'percent' => $fact->percent,
            'barclass' => match ($state) {
                self::SIGNAL_OVER => 'bg-danger text-white',
                self::SIGNAL_WARN => 'bg-warning text-dark',
                default => 'bg-success text-white',
            },
            'barlabel' => get_string('reviewsignalseats', 'enrol_apply', (object) [
                'name' => $fact->name,
                'members' => $fact->members,
                'seats' => $fact->seats,
            ]),
            'seatstext' => match (true) {
                !$fact->hasseats => get_string('reviewgroupcardnolimit', 'enrol_apply'),
                $fact->full => get_string('reviewgroupcardfull', 'enrol_apply', (object) [
                    'members' => $fact->members,
                    'seats' => $fact->seats,
                ]),
                default => get_string('reviewgroupcardseats', 'enrol_apply', (object) [
                    'members' => $fact->members,
                    'seats' => $fact->seats,
                    'left' => $fact->seats - $fact->members,
                ]),
            },
        ];
    }

    /**
     * One group as an option of "Add another group" (RP-D21).
     *
     * Plain text, because core's autocomplete matches the option's visible text and renders it in
     * the chip and the announcement: the idnumber in parentheses makes the code searchable, and
     * the seat state is words, never colour. A full group stays selectable (R5).
     *
     * @param stdClass $fact As group_facts() returns one.
     * @param array $groupingids The groupings that hold it.
     * @return array Template context for one option.
     */
    protected function group_option(stdClass $fact, array $groupingids): array {
        return [
            'id' => $fact->id,
            'name' => $fact->name,
            'text' => \enrol_apply\local\groupfacts::option_text($fact),
            'hasseats' => $fact->hasseats,
            'members' => $fact->members,
            'seats' => $fact->seats,
            // Comma-delimited on both sides, so the narrowing never matches grouping 1 inside 11.
            'groupings' => ',' . implode(',', $groupingids) . ',',
        ];
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
            // Escaped: confirm() writes the message into the page raw.
            get_string('reviewcancelconfirm_desc', 'enrol_apply', s(fullname($applicant))),
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
     * Render the notice that says how long the course report's records are kept.
     *
     * Above core's table of the report ("Table view") and in the React view's
     * noscript copy; the React view draws the same words as a note of its own, from the same
     * source ({@see \enrol_apply\local\reportengine::retention()}). Nothing when records are kept
     * forever; the link to the setting only for a reader who may change it.
     *
     * @return string Rendered markup, or an empty string when nothing is ever purged.
     */
    public function retention_notice(): string {
        $retention = \enrol_apply\local\reportengine::retention();
        if ($retention === null) {
            return '';
        }

        $message = s($retention['text']);
        if ($retention['url'] !== '') {
            $message .= ' ' . $this->output->render(new action_link(new moodle_url($retention['url']), $retention['link']));
        }

        return $this->output->notification($message, \core\output\notification::NOTIFY_INFO, false);
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
     * @param string|null $keygroup For an admission by key, or for an application a valid key sent
     *     for review, the key group's plain name, or empty where the recipient may not be told it;
     *     null when no key counted.
     * @param bool $forreview Whether $keygroup is that of an application sent for review, which is
     *     still a request to decide, rather than of an admission.
     * @param string|null $chosengroup For an application made with a chosen group, its plain name,
     *     or empty where the recipient may not be told it; null when no group was chosen.
     * @return string Rendered HTML body.
     */
    public function application_notification_mail_body(
        $course,
        $user,
        $manageurl,
        $applydescription,
        array $submitted = [],
        ?string $keygroup = null,
        bool $forreview = false,
        ?string $chosengroup = null
    ) {
        $admittedgroup = $forreview ? null : $keygroup;
        $reviewgroup = $forreview ? $keygroup : null;

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
            /* An admission by key is news, not a request: one line saying who decided, and the link
               leads to the applicant rather than to a queue that holds nothing for them. Plain,
               because the template double-stashes it. */
            'admitted' => $admittedgroup !== null,
            'admission' => match ($admittedgroup) {
                null => '',
                '' => get_string('mailtoteacher_admittednogroup', 'enrol_apply'),
                default => get_string('mailtoteacher_admitted', 'enrol_apply', $admittedgroup),
            },
            // One line naming the key group of an application for review; plain, as above.
            'keyreview' => match ($reviewgroup) {
                null => '',
                '' => get_string('mailtoteacher_keyreviewnogroup', 'enrol_apply'),
                default => get_string('mailtoteacher_keyreview', 'enrol_apply', $reviewgroup),
            },
            // One line naming the group the applicant chose (C-D4); plain, as above.
            'chosengroup' => match ($chosengroup) {
                null => '',
                '' => get_string('mailtoteacher_chosengroupunnamed', 'enrol_apply'),
                default => get_string('mailtoteacher_chosengroup', 'enrol_apply', $chosengroup),
            },
            'manageurl' => $manageurl->out(false),
            'managelabel' => $admittedgroup === null ? get_string('applymanage', 'enrol_apply') : get_string('viewprofile'),
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

    /**
     * Render the "Display conditions" section of the application fields page.
     *
     * One row per question the instance asks, in form order, saying when it is shown. A question
     * with no earlier select or checkbox question and no stored condition offers no edit link:
     * nothing could control it.
     *
     * @param stdClass $instance Enrol instance.
     * @return string Rendered markup.
     */
    public function field_conditions(stdClass $instance): string {
        $fields = \enrol_apply\local\answers::fields($instance);
        $stored = \enrol_apply\local\conditions::stored((int) $instance->id);
        $effective = \enrol_apply\local\conditions::effective($instance, $fields);

        $rows = [];
        foreach ($fields as $fieldid => $field) {
            $fieldid = (int) $fieldid;
            $params = ['id' => $instance->id, 'fieldid' => $fieldid];
            $name = $field->get_formatted_name(false);
            $rows[] = [
                // Plain, because the template double stashes all of them.
                'name' => $name,
                'editlabel' => get_string('conditioneditfor', 'enrol_apply', $name),
                'removelabel' => get_string('conditionremovefor', 'enrol_apply', $name),
                'summary' => \enrol_apply\local\conditions::summary($fieldid, $stored, $effective, $fields),
                'canedit' => isset($stored[$fieldid])
                    || (bool) \enrol_apply\local\conditions::controllers($fields, $fieldid),
                'editurl' => (new moodle_url('/enrol/apply/condition.php', $params))->out(false),
                'hascondition' => isset($stored[$fieldid]),
                'fieldid' => $fieldid,
            ];
        }

        return $this->render_from_template('enrol_apply/field_conditions', [
            'heading' => get_string('conditions', 'enrol_apply'),
            'intro' => get_string('conditions_desc', 'enrol_apply'),
            'hasrows' => (bool) $rows,
            'rows' => $rows,
            'removeurl' => (new moodle_url('/enrol/apply/condition.php'))->out(false),
            'enrolid' => (int) $instance->id,
            'sesskey' => sesskey(),
        ]);
    }
}
