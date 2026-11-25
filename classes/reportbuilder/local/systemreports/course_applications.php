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

namespace enrol_apply\reportbuilder\local\systemreports;

use context;
use context_course;
use core\lang_string;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\report\action;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\system_report;
use core_reportbuilder\system_report_factory;
use enrol_apply\local\answers;
use enrol_apply\local\fields;
use enrol_apply\local\groupscope;
use enrol_apply\local\groupvisibility;
use enrol_apply\local\queue;
use enrol_apply\local\reportengine;
use enrol_apply\local\reportpreference;
use enrol_apply\reportbuilder\local\entities\submission;
use enrol_apply\reportbuilder\local\filters\group as groupfilter;
use enrol_apply\reportbuilder\local\formatters\submission as formatter;
use moodle_url;
use pix_icon;
use stdClass;

/**
 * The applications made to one course.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_applications extends system_report {
    /** @var string Entity name of the second user entity, the one naming the decider. */
    protected const DECIDER = 'applydecider';

    /** @var array|null Field key => current name, the questions this reader gets a column for. */
    protected $questions = null;

    /** @var array|null The course's apply instances keyed by id, read once; see instances(). */
    protected $instances = null;

    /** @var array|null User enrolment id => true for the rows this reader may review; see can_review(). */
    protected $reviewable = null;

    /** @var array|null Catalogue key => definition; see column_catalogue(). */
    protected $catalogue = null;

    /** @var array Catalogue key => unique identifier of the column added for it, in display order. */
    protected $activekeys = [];

    /** @var string[] What the wait column may measure; see measure(). */
    public const MEASURES = ['first', 'resolution'];

    /**
     * Unique identifier of the filter that narrows the report to one enrolment method.
     *
     * Core builds it from the entity name and filter name used in add_report_filters(); it is
     * repeated here because report.php needs to name it. If the two drift apart,
     * scope_to_method() silently returns false;
     * test_the_method_filter_identifier_is_the_one_the_page_scopes_by holds the coupling.
     *
     * @var string
     */
    public const METHOD_FILTER = 'submission:method';

    /**
     * Whether the current user may see this report at all.
     *
     * This method is the whole gate and must be self-sufficient: every sort, filter and page turn
     * goes through core_table_get_dynamic_table_content, which constructs the report (running
     * this check) before its own validate_context(), so nothing here may assume report.php ran or
     * that require_login() was called against this course.
     *
     * The context is the report persistent's own, resolved server-side from the report id; a
     * client swapping the id gets another report's context, which this method re-checks. That is
     * why scoping on the context is safe, unlike get_parameter(), whose values the client sends
     * in the filterset.
     *
     * @return bool True when the user may view the report.
     */
    protected function can_view(): bool {
        return self::may_view($this->get_context());
    }

    /**
     * Whether the current user may see the report of this context, without building the report.
     *
     * {@see can_view()}'s whole rule, for a caller that must not build the report: core keeps one
     * instance of a report per user for the whole request, so building it to check access fixes its
     * columns and parameters for any later read in the same request
     * ({@see \enrol_apply\external\set_report_preference}).
     *
     * @param context $context The report's context.
     * @return bool True when the user may view the report.
     */
    public static function may_view(context $context): bool {
        /* Reachable: system_report_factory::create() builds this report in any context it is
           handed, and outside a course the capability check below would test the wrong thing. */
        if (!$context instanceof context_course) {
            return false;
        }

        /* Core's download.php calls only the site-level require_login() before this method, and its
           retrieval services a validate_context() that guest access satisfies, so the course gate
           lives here: a suspended or expired enrolment fails as require_login($course) would, a hidden
           course is refused to a reader without moodle/course:viewhiddencourses, and a course role a
           lapsed enrolment left behind counts for nothing. */
        $course = get_course((int) $context->instanceid);

        return groupscope::enters_with($course, 'enrol/apply:viewreports');
    }

    /**
     * Build the report.
     *
     * @return void
     */
    protected function initialise(): void {
        $entity = new submission();
        $alias = $entity->get_table_alias('enrol_apply_submission');

        $this->set_main_table('enrol_apply_submission', $alias);
        $this->add_entity($entity);

        /* The only scoping there is, built from the context. Not get_parameter(), which a client
           sets freely, and not the persistent's itemid, which is client-settable through
           core_reportbuilder_retrieve_system_report. The itemid (the enrol instance, see
           for_method()) only selects which stored filter choices come back. */
        $courseid = database::generate_param_name();
        $this->add_base_condition_sql("{$alias}.courseid = :{$courseid}", [
            $courseid => $this->get_context()->instanceid,
        ]);

        /* Separate groups narrow the report as they narrow the queue (S8): a reader the rule
           scopes, judged for this report's own capability and its own system exemption, reads the
           records of their groups alone, by the application's group (key or choice) or by the
           groups joined; none in no group, and report.php says why. Derived from the context and $USER on every request, so a sort,
           a page turn or a download through core_table_get_dynamic_table_content is narrowed the
           same way as the first render. */
        $groups = $this->narrowed_groups();
        if ($groups !== null) {
            [$groupwhere, $groupparams] = groupscope::record_where($alias, $groups, database::generate_param_name());
            $this->add_base_condition_sql($groupwhere, $groupparams);
        }

        /* Pseudonymised records (what a deleted course leaves behind) carry userid 0 and
           describe nobody. The INNER join below is what excludes them, as no user has id 0;
           making it a LEFT join needs a "userid <> 0" base condition, as in the datasource.
           test_a_pseudonymised_record_is_not_listed holds this. */
        $applicant = new user();
        $applicantalias = $applicant->get_table_alias('user');
        $this->add_entity($applicant->add_join(
            "JOIN {user} {$applicantalias} ON {$applicantalias}.id = {$alias}.userid"
        ));

        /* A second user entity for the decider, renamed so it does not collide with the first
           (report\base::annotate_entity() throws 'Duplicate entity name'). LEFT JOIN, because an
           undecided record carries decidedby = 0. It carries the filter on the decider's name;
           the name itself is shown in the "Decided" cell, from the submission entity's own join. */
        $decider = (new user())
            ->set_entity_name(self::DECIDER)
            ->set_entity_title(new lang_string('submissiondecidedby', 'enrol_apply'));
        $decideralias = $decider->get_table_alias('user');
        $this->add_entity($decider->add_join(
            "LEFT JOIN {user} {$decideralias} ON {$decideralias}.id = {$alias}.decidedby"
        ));

        /* The record's own facts, beside the formatted cells, for a reader that computes with them
           (the headless rows, \enrol_apply\local\reportengine): the dates, the decider and the
           key's outcome, which say how long a row waited and whether a person decided it. */
        $basefields = "{$alias}.id, {$alias}.userid, {$alias}.userenrolmentid, {$alias}.status, {$alias}.enrolid,"
            . " {$alias}.timecreated, {$alias}.timedecided, {$alias}.decidedby, {$alias}.keyoutcome";
        if (enrol_get_plugin('apply')->first_response_schema_ready()) {
            $basefields .= ", {$alias}.timefirstdecided";
        }
        $this->add_base_fields($basefields);

        $this->add_report_columns();
        $this->add_report_filters();

        /* The scope and the name search, where the report's parameters carry them: the headless view's
           download link passes the ones in force (reportengine adds them to its own table instead), so
           core's download.php, which rebuilds the report from its parameters, writes the rows the
           reader is looking at. Read through the engine's own vocabulary and cleaning, and built by
           the same method the rows call uses; each is ANDed with the base conditions above, so a
           parameter, which a client sets freely, can only narrow what this reader may already read. */
        $scope = reportengine::scope((string) $this->get_parameter('scope', 'all', PARAM_ALPHA));
        $search = reportengine::search((string) $this->get_parameter('search', '', PARAM_TEXT));
        foreach (reportengine::conditions($this, $this->measure(), $scope, $search) as [$sql, $params]) {
            $this->add_base_condition_sql("({$sql})", $params);
        }

        /* The way from a row to the decision (AR-D18): the applicant's name stays the profile link
           it is on every other page, and an application still awaiting a decision gets a Review
           action to the review page, drawn only for a reader that page admits. The callback reads
           a set computed once per report, so a page of rows costs no query per row. */
        $this->add_action((new action(
            new moodle_url('/enrol/apply/manage.php', ['userenrol' => ':userenrolmentid']),
            new pix_icon('t/preview', ''),
            [],
            false,
            new lang_string('queuereview', 'enrol_apply')
        ))->add_callback(fn(stdClass $row): bool => $this->can_review((int) $row->userenrolmentid)));

        /* What styles.css keys this report's own layout on, phone cards included: core's card view
           applies to custom reports only, and a plugin sheet loads before the theme's, so the
           plugin's selectors need an attribute of the report's own to win. */
        $this->add_attributes(['data-enrol-apply-report' => 'course']);

        /* Newest first, while the reader shows the submission date; a reader who hid it reads the
           report by name, as core cannot sort by a column the report does not carry. */
        if (isset($this->activekeys['submitted'])) {
            $this->set_initial_sort_column('submission:timecreated', SORT_DESC);
        } else {
            $this->set_initial_sort_column('user:fullnamewithlink', SORT_ASC);
        }
        $this->set_default_per_page(30);
        $this->set_downloadable(true, get_string('report:course_applications', 'enrol_apply'));
    }

    /**
     * The groups this reader's view of the report is narrowed to, or null when it is not narrowed.
     *
     * {@see groupscope::narrowed_to()} for the report capability, in the report's course and for
     * the current user. Public so that report.php can tell a narrowed reader in no group why the
     * report is empty, with core's notice (S4).
     *
     * @return array|null Group ids; an empty array for a narrowed reader in no group.
     */
    public function narrowed_groups(): ?array {
        global $USER;

        return groupscope::narrowed_to((int) $this->get_context()->instanceid, (int) $USER->id, groupscope::REPORT);
    }

    /**
     * The groups the group filter offers this reader, by id, in natural order.
     *
     * Every group of the course groups_get_all_groups() lists to the current user, which applies
     * core's visibility rule, narrowed to their own groups when the separate-groups rule narrows
     * them for the report ({@see narrowed_groups()}): a narrowed reader is offered nothing their
     * base condition would not already show, and a value outside the options filters nothing
     * ({@see groupfilter::get_sql_filter()}). Sorted with core_collator::SORT_NATURAL, so
     * "Turma 2" comes before "Turma 10", as on the queue's filter.
     *
     * The names in the escaped spelling, which the filter form's select element expects.
     *
     * @return array Group id => name.
     */
    public function group_options(): array {
        global $DB, $USER;

        /* Built before can_view() runs (core adds the filters while the report is constructed), so
           a context that is not a course's must yield nothing here rather than throw, and leave
           the refusal to can_view(). */
        if (!$this->get_context() instanceof context_course) {
            return [];
        }
        $courseid = (int) $this->get_context()->instanceid;
        /* Read from {groups} with groupvisibility's rule rather than groups_get_all_groups(), whose
           answer comes from the groupdata cache: the same visibility clauses for the same reader,
           without a cached list standing in for the course's groups as they are now. */
        $groups = $DB->get_records('groups', ['courseid' => $courseid], 'id', 'id, courseid, name, visibility');
        $groups = array_intersect_key($groups, array_flip(groupvisibility::visible_ids($groups, (int) $USER->id)));
        $narrowed = $this->narrowed_groups();
        if ($narrowed !== null) {
            $groups = array_intersect_key($groups, array_flip($narrowed));
        }
        \core_collator::asort_objects_by_property($groups, 'name', \core_collator::SORT_NATURAL);

        $options = [];
        foreach ($groups as $group) {
            $options[(int) $group->id] = format_string($group->name, true, ['context' => $this->get_context()]);
        }

        return $options;
    }

    /**
     * What the wait column measures for this report, from its parameters.
     *
     * A report parameter, so a sort, a page turn and a download carry it as the first render had
     * it; a client sets it freely, which is harmless: it chooses between two columns the reader
     * may both see, and anything else reads as the first response.
     *
     * @return string 'first' or 'resolution'.
     */
    public function measure(): string {
        $measure = (string) $this->get_parameter('measure', 'first', PARAM_ALPHA);

        return in_array($measure, self::MEASURES, true) ? $measure : 'first';
    }

    /**
     * The columns a reader may choose for this report, in display order.
     *
     * The applicant and their identity fields are not in it: they are always shown, and the
     * identity fields come from core's helper for this reader alone (see add_report_columns()).
     * Every other column is here only where this reader may see it: the method only where the
     * course offers a choice of methods, a question only where questions() keeps it. So a key the
     * preference names that is not in the catalogue, forged or from another course, adds nothing.
     *
     * The default set is the one the report showed before the reader chose (R1): everything but
     * the admission route, whose fact the outcome cell already states.
     *
     * @return array Key => ['source' => 'entity'|'method'|'answer', 'name' => entity column or
     *     field key, 'title' => plain title, 'sortable' => bool, 'default' => bool].
     */
    public function column_catalogue(): array {
        if ($this->catalogue !== null) {
            return $this->catalogue;
        }

        $entity = $this->get_entity('submission');
        $wait = $this->measure() === 'resolution' ? 'resolutiontime' : 'waittime';
        $entries = [
            'outcome' => 'outcome',
            'submitted' => 'timecreated',
            'wait' => $wait,
            'decided' => 'decided',
            'group' => 'group',
        ];
        $catalogue = [];
        foreach ($entries as $key => $name) {
            $catalogue[$key] = [
                'source' => 'entity',
                'name' => $name,
                'title' => $entity->get_column($name)->get_title(),
                'sortable' => $entity->get_column($name)->get_is_sortable(),
                'default' => true,
            ];
        }
        if (count($this->instances()) > 1) {
            $catalogue['method'] = [
                'source' => 'method',
                'name' => 'method',
                'title' => get_string('submissionmethod', 'enrol_apply'),
                'sortable' => false,
                'default' => true,
            ];
        }
        foreach (['route' => 'admissionroute', 'comment' => 'comment', 'note' => 'decisionnote'] as $key => $name) {
            $catalogue[$key] = [
                'source' => 'entity',
                'name' => $name,
                'title' => $entity->get_column($name)->get_title(),
                'sortable' => $entity->get_column($name)->get_is_sortable(),
                'default' => $key !== 'route',
            ];
        }
        foreach ($this->questions() as $fieldkey => $title) {
            $catalogue['answer:' . $fieldkey] = [
                'source' => 'answer',
                'name' => $fieldkey,
                'title' => $title,
                'sortable' => true,
                'default' => true,
            ];
        }

        $this->catalogue = $catalogue;

        return $this->catalogue;
    }

    /**
     * The catalogue keys this reader's report shows.
     *
     * The reader's preference ({@see reportpreference::read()}) intersected with the catalogue,
     * or the catalogue's default set when they have chosen nothing. "answers" in the preference
     * stands for every question of the course (AR-D8), so a reader who wants the answers sees the
     * questions of whichever course they open; a single "answer:<key>" shows that one question
     * where the course asks it.
     *
     * @return array Key => true, in catalogue order.
     */
    public function chosen_keys(): array {
        $preference = reportpreference::read()['columns'];

        if ($preference === null) {
            $chosen = [];
            foreach ($this->column_catalogue() as $key => $entry) {
                if ($entry['default']) {
                    $chosen[$key] = true;
                }
            }

            return $chosen;
        }

        return $this->keys_for($preference);
    }

    /**
     * The catalogue keys a stored choice of columns shows on this report.
     *
     * The choice intersected with the catalogue, "answers" standing for every question. What
     * {@see chosen_keys()} reads a reader's preference as, and what a Columns menu set means here
     * ({@see \enrol_apply\local\reportengine::PRESETS}).
     *
     * @param array $columns The keys chosen, as reportpreference stores them.
     * @return array Key => true, in catalogue order.
     */
    public function keys_for(array $columns): array {
        $catalogue = $this->column_catalogue();
        $chosen = [];
        $named = array_flip($columns);
        $answers = isset($named['answers']);
        foreach ($catalogue as $key => $entry) {
            if (isset($named[$key]) || ($answers && $entry['source'] === 'answer')) {
                $chosen[$key] = true;
            }
        }

        return $chosen;
    }

    /**
     * The alias of the application record's table in this instance's SQL.
     *
     * Each instance of the report generates its own aliases, so a caller appending SQL to a table
     * built from an instance asks that instance.
     *
     * @return string The alias.
     */
    public function submission_alias(): string {
        return $this->get_entity('submission')->get_table_alias('enrol_apply_submission');
    }

    /**
     * The columns this report shows, by catalogue key.
     *
     * The applicant is "applicant", each identity column "identity:<name>", the rest their
     * catalogue keys. The unique identifiers are the same on every instance of the report, so a
     * caller holding another instance (core re-creates the report for its table) finds the columns
     * by them.
     *
     * @return array Key => column unique identifier, in display order.
     */
    public function active_keys(): array {
        return $this->activekeys;
    }

    /**
     * Add the report's columns, in display order.
     *
     * The applicant and the identity fields this reader may see, always; then the catalogue's
     * columns the reader chose, or its default set (AR-D3, and R1's choice of keeping the comment,
     * the note and the answers in it): one state, the dates, the wait, the decision, the group and,
     * where the course offers a choice, the method; then the comment, the decision note and one
     * column per question. Read in initialise(), so every request (a sort, a page turn, a download,
     * the headless rows) adds the same columns: the choice is a server fact, and sorting and paging
     * never meet a hidden column. The entity offers the rest (the stored status, the live
     * enrolment, the three group columns, the snapshot), which a custom report over the datasource
     * can still pick; this report shows each of those facts once, in the cells above.
     *
     * @return void
     */
    protected function add_report_columns(): void {
        $this->activekeys = [];
        $applicantcolumn = $this->add_column_from_entity('user:fullnamewithlink');
        $this->activekeys['applicant'] = $applicantcolumn->get_unique_identifier();

        /* Identity fields from core's helper, which returns none outside
           moodle/site:viewuseridentity, so a reader without it gets no column rather than a
           blanked one: sorting and filtering are SQL and never reach a display callback, so only
           absence keeps a hidden value from being recovered. */
        $applicant = $this->get_entity('user');
        foreach ($applicant->get_identity_columns($this->get_context()) as $column) {
            $added = $this->add_column($column);
            $this->activekeys['identity:' . $added->get_name()] = $added->get_unique_identifier();
        }

        $catalogue = $this->column_catalogue();
        foreach (array_keys($this->chosen_keys()) as $key) {
            $entry = $catalogue[$key];
            $added = match ($entry['source']) {
                'entity' => $this->add_column_from_entity('submission:' . $entry['name']),
                'method' => $this->add_column($this->method_column()),
                'answer' => $this->add_column($this->get_entity('submission')->answer_column($entry['name'], $entry['title'])),
            };
            $this->activekeys[$key] = $added->get_unique_identifier();
        }

        /* Each cell carries its column's heading, which the phone layout in styles.css shows above
           the value once the table becomes cards. Core sets it for custom reports only. The
           applicant's column is headed by core as the name parts' sort links, so its card label is
           the plugin's own word. */
        foreach ($this->get_active_columns() as $identifier => $column) {
            $title = $identifier === 'user:fullnamewithlink'
                ? get_string('reportapplicant', 'enrol_apply')
                : $column->get_title();
            $column->add_attributes(['data-cardtitle' => $title]);
        }
    }

    /**
     * The column naming the enrolment method each application was made to.
     *
     * The names are read once with the instances, never per row: the cell looks its method up in
     * the map handed to the callback as its argument. A record of a method deleted since
     * ({@see \enrol_apply_plugin::delete_instance()} keeps the records) says so. Not sortable: the
     * stored value is an id, and ids are not in the order of the names they stand for.
     *
     * @return column The column.
     */
    protected function method_column(): column {
        $alias = $this->get_entity('submission')->get_table_alias('enrol_apply_submission');

        return (new column(
            'method',
            new lang_string('submissionmethod', 'enrol_apply'),
            'submission'
        ))
            ->add_joins($this->get_entity('submission')->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$alias}.enrolid", 'methodenrolid')
            ->set_is_sortable(false)
            ->add_callback([self::class, 'method_name'], $this->method_names());
    }

    /**
     * The name of the method a record was made to, for the method column.
     *
     * @param mixed $value The record's enrolid.
     * @param stdClass $row Row being rendered.
     * @param mixed $names Enrol instance id => name, as {@see method_names()} built them.
     * @return string The name, in the escaped spelling get_instance_name() returns, or a note that
     *     the method is gone.
     */
    public static function method_name($value, stdClass $row, $names = null): string {
        if ($value === null || $value === '') {
            return '';
        }

        return is_array($names) && array_key_exists((int) $value, $names)
            ? (string) $names[(int) $value]
            : get_string('submissionmethodgone', 'enrol_apply');
    }

    /**
     * The course's apply instances, oldest first, read once per report.
     *
     * Every apply instance of the course, not the one the report was opened from: the rows are the
     * whole course's, and the itemid naming that instance is client-settable.
     *
     * @return array Enrol instance records keyed by id.
     */
    protected function instances(): array {
        global $DB;

        if ($this->instances === null) {
            $this->instances = $DB->get_records(
                'enrol',
                ['courseid' => $this->get_context()->instanceid, 'enrol' => 'apply'],
                'id ASC'
            );
        }

        return $this->instances;
    }

    /**
     * The names of the course's apply instances, as the method filter and column show them.
     *
     * get_instance_name() wants the whole record, not a menu: the name is built from more than
     * the name column. Its answer is format_string()'s escaped spelling, which both sinks want: a
     * select option and a Report Builder cell render raw.
     *
     * @return array Enrol instance id => name.
     */
    protected function method_names(): array {
        $plugin = enrol_get_plugin('apply');
        $names = [];
        foreach ($this->instances() as $id => $instance) {
            $names[(int) $id] = $plugin->get_instance_name($instance);
        }

        return $names;
    }

    /**
     * Whether the current user may open this application's review page from a row of the report.
     *
     * True only for an application still awaiting a decision that the review page would admit
     * this reader to ({@see queue::reviewable_in_course()}, which answers as
     * can_view_application() does). The set is read once per report, so a page of rows costs the
     * same few queries as one row.
     *
     * @param int $userenrolmentid The row's user enrolment, 0 for a record with none.
     * @return bool True when the row gets the Review action.
     */
    public function can_review(int $userenrolmentid): bool {
        if ($this->reviewable === null) {
            $this->reviewable = queue::reviewable_in_course((int) $this->get_context()->instanceid);
        }

        return $userenrolmentid > 0 && isset($this->reviewable[$userenrolmentid]);
    }

    /**
     * The questions this course's apply instances ask, each one this reader may see.
     *
     * Every apply instance of the course, not the one the report was opened from: the rows are the
     * whole course's (the method filter only narrows them), and the itemid naming that instance is
     * client-settable. The requested profile fields first, then the instance's own questions and
     * its shared ones, in form order; a key two instances share gets one column, under the first
     * name met.
     *
     * Masked by the snapshot column's own rule, visible_keys() in the course context. A question
     * the reader may not see gets no column and no filter at all, rather than a blank one:
     * sorting and filtering are SQL that a display callback never reaches, so only absence keeps a
     * hidden value from being recovered, and a column that appeared only with data would itself
     * say who answered.
     *
     * @return array Field key => the question's current name, plain spelling.
     */
    protected function questions(): array {
        if ($this->questions !== null) {
            return $this->questions;
        }

        $visible = formatter::visible_keys($this->get_context());
        $questions = [];
        foreach ($this->instances() as $instance) {
            foreach (fields::resolve($instance)->keys() as $key) {
                $questions[$key] ??= fields::label($key, false);
            }
            foreach (answers::fields($instance) as $field) {
                $questions[answers::key((int) $field->get('id'))] ??= $field->get_formatted_name(false);
            }
        }

        $this->questions = array_filter(
            $questions,
            static fn(string $key): bool => formatter::shows($key, $visible),
            ARRAY_FILTER_USE_KEY
        );

        return $this->questions;
    }

    /**
     * This report, identified by the enrolment method it was opened from.
     *
     * scope_to_method() stores the reader's choice through set_filter_values(), which is keyed on
     * the report id and the user only. Sorting, paging and downloading read that store and carry
     * nothing that names a method, so each method needs its own report persistent (keyed here by
     * the enrol instance as itemid) or two methods in one course would share one stored scope.
     *
     * The itemid is identity, never scope: rows are limited by the base condition, built from the
     * context. A named constructor so that the PHPUnit suite can reach it
     * (test_two_methods_keep_independent_scopes); report.php is a page script no test runs.
     *
     * The parameters carry presentation only ({@see measure()}); core stores them with the table
     * and hands them back on every later request, and a client may set them freely.
     *
     * @param context $context Course context the report belongs to.
     * @param int $enrolid Enrol instance the reader opened it from.
     * @param array $parameters Report parameters: 'measure'.
     * @return \core_reportbuilder\system_report The report.
     */
    public static function for_method(context $context, int $enrolid, array $parameters = []): \core_reportbuilder\system_report {
        return system_report_factory::create(self::class, $context, 'enrol_apply', 'method', $enrolid, $parameters);
    }

    /**
     * Narrow this reader's view to one enrolment method, leaving their other filters alone.
     *
     * A filter value, never a base condition, so it stays outside the security boundary: the
     * base condition limits rows to the course whatever a filter carries. A value that is not one
     * of the filter's options is ignored by select::get_sql_filter(), so a forged enrolid widens
     * the report to the whole course - a view the reader is already allowed - rather than
     * narrowing it.
     *
     * Merged with the stored values, as set_filter_values() replaces them all and the reader's
     * other filters are not this page's to discard. array_merge(), not +, which would let the
     * stored method win (test_the_url_method_wins_over_a_stored_one).
     *
     * @param int $enrolid Enrol instance to narrow to.
     * @return bool False when this course has no method filter to set, which is every course
     *              carrying a single live apply method - the filter is added only where the
     *              course offers a choice between methods.
     */
    public function scope_to_method(int $enrolid): bool {
        if (!array_key_exists(self::METHOD_FILTER, $this->get_filter_instances())) {
            return false;
        }

        return $this->set_filter_values(array_merge($this->get_filter_values(), [
            self::METHOD_FILTER . '_operator' => select::EQUAL_TO,
            self::METHOD_FILTER . '_value' => $enrolid,
        ]));
    }

    /**
     * Add the report's filters.
     *
     * @return void
     */
    protected function add_report_filters(): void {
        $this->add_filter_from_entity('user:fullname');

        /* The identity filters must stay in step with the identity columns: a filter on a field
           the reader may not see would let them recover its value by narrowing and counting
           rows. get_identity_filters() answers the same capability question as
           get_identity_columns(). */
        $applicant = $this->get_entity('user');
        foreach ($applicant->get_identity_filters($this->get_context()) as $filter) {
            $this->add_filter($filter);
        }

        $this->add_filters_from_entity('submission');
        // In step with the question columns, so a filter never reaches a value its column withholds.
        foreach ($this->questions() as $key => $title) {
            $this->add_filter($this->get_entity('submission')->answer_filter($key, $title));
        }
        $this->add_filter_from_entity(self::DECIDER . ':fullname');

        /* The group filter (V5): a record's application group (key or choice) or a group its
           approval joined, for one group the reader may pick. Offered, never applied by default, and only where there is a group
           to offer, like the method filter below. The custom-report source has no such filter
           (V-D11). The options come from the context and $USER on every request, like the base
           condition. */
        $alias = $this->get_entity('submission')->get_table_alias('enrol_apply_submission');
        $groups = $this->group_options();
        if ($groups) {
            $this->add_filter((new filter(
                groupfilter::class,
                'group',
                new lang_string('reportgroup', 'enrol_apply'),
                'submission',
                "{$alias}.id"
            ))->set_options($groups));
        }

        /* The method filter is added only where the course offers a choice of methods. With one
           it would read as a control that does nothing, and selecting it would hide the records
           of deleted methods, which delete_instance() keeps. */
        if (count($this->instances()) > 1) {
            $this->add_filter((new filter(
                select::class,
                'method',
                new lang_string('submissionmethod', 'enrol_apply'),
                'submission',
                "{$alias}.enrolid"
            ))->set_options($this->method_names()));
        }
    }
}
