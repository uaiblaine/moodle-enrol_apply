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
 * Tests for the vocabulary of the applications queue's configurable filters.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the vocabulary of the applications queue's configurable filters.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(queuefilter::class)]
final class queuefilter_test extends \advanced_testcase {
    /**
     * Reset before every test; several of these write site configuration.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A custom profile field, with the datatype and options a test needs.
     *
     * @param string $shortname Field shortname.
     * @param string $name Field name, as an administrator typed it.
     * @param string $datatype text, menu, checkbox, datetime or textarea.
     * @param string $param1 The field's own parameter, which is the option list for a menu.
     * @return \stdClass The created field.
     */
    protected function custom_field(string $shortname, string $name, string $datatype = 'text', string $param1 = ''): \stdClass {
        return $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => $datatype,
            'shortname' => $shortname,
            'name' => $name,
            'param1' => $param1,
        ]);
    }

    /**
     * Nothing configured means nothing offered, whichever way "nothing" is spelled.
     *
     * Three spellings reach this: the setting absent because nobody has visited the page, the
     * empty string that admin_setting_configmulticheckbox writes when everything is unticked, and
     * the empty string it also writes on a site whose choice list was empty. Read with a `?:`
     * falling back to a default, all three would silently offer filters nobody asked for - which
     * on this queue means offering a control over a profile field an administrator declined.
     *
     * @return void
     */
    public function test_configuring_no_filter_fields_offers_none(): void {
        $this->assertSame([], queuefilter::pool(), 'absent must mean nothing');

        set_config('queuefilterfields', '', 'enrol_apply');
        $this->assertSame([], queuefilter::pool(), 'empty must mean nothing');

        // The control: a real value is read, so the assertions above are not about a dead reader.
        set_config('queuefilterfields', 'city,institution', 'enrol_apply');
        $this->assertSame(['city', 'institution'], queuefilter::pool());
    }

    /**
     * Every token that reaches the wire is alphanumeric.
     *
     * Core's dynamic-table service declares a filter name as PARAM_ALPHANUM and refuses anything
     * else with invalid_parameter_exception before this plugin is consulted, so a custom field
     * whose shortname holds an underscore - which the profile field editor allows - cannot be its
     * own filter name. The id is used instead, which is also the only stable key the field has:
     * {user_info_field} carries no unique index on shortname.
     *
     * @return void
     */
    public function test_every_offered_token_is_alphanumeric(): void {
        $field = $this->custom_field('cpf_2', 'Second document');

        $token = queuefilter::token('profile_field_cpf_2');
        $this->assertSame('pf' . $field->id, $token);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $token);

        // A standard column is already alphanumeric and is used as it stands.
        $this->assertSame('city', queuefilter::token('city'));
    }

    /**
     * A field whose name would collide with a parameter the page already uses is never offered.
     *
     * The queue's GET form carries one parameter per filter, so a token spelled like the scope or
     * the paging would overwrite it - and the symptom is a page that silently loses its scope
     * rather than an error. No standard identity field collides; the guard is for the custom
     * field an administrator could create tomorrow.
     *
     * @return void
     */
    public function test_a_reserved_name_yields_no_token(): void {
        foreach (['id', 'page', 'search', 'status', 'appliedfrom'] as $reserved) {
            $this->assertSame('', queuefilter::token($reserved), $reserved . ' must not be a token');
        }
    }

    /**
     * A datetime custom field is never offered, because this control cannot ask its question.
     *
     * The control set is a text box and a select. A datetime is a range question, and offering it
     * as either would let an operator apply a filter that cannot match what they meant - a text
     * box over a stored timestamp matches digits, and a select would need a vocabulary the field
     * does not have. The applied-date filter is the range control this queue does offer.
     *
     * @return void
     */
    public function test_a_datetime_custom_field_is_never_offered(): void {
        $this->custom_field('birthday', 'Birthday', 'datetime');
        $this->custom_field('cpf', 'Document');
        set_config('queuefilterfields', 'profile_field_birthday,profile_field_cpf', 'enrol_apply');

        $mappings = [
            'profile_field_birthday' => 'ui1.data',
            'profile_field_cpf' => 'ui2.data',
        ];
        $offered = queuefilter::resolve($mappings);

        // The text field IS offered, so an empty result cannot pass this test.
        $this->assertCount(1, $offered);
        $this->assertSame('profile_field_cpf', reset($offered)->name);
    }

    /**
     * A menu field becomes a select over its own options, and refuses anything else.
     *
     * The options come from the field's definition and never from a query over the values users
     * happen to hold. A list of the values PRESENT would enumerate rather than confirm, and the
     * query behind it would be a third consumer of the queue's scope predicate - one that, bounded
     * by the instance alone, would list the ranks of applicants already approved or cancelled.
     *
     * @return void
     */
    public function test_a_menu_field_refuses_a_value_outside_its_own_options(): void {
        $this->custom_field('rank', 'Rank', 'menu', "Major\nColonel");
        set_config('queuefilterfields', 'profile_field_rank', 'enrol_apply');

        $offered = queuefilter::resolve(['profile_field_rank' => 'ui1.data']);
        $field = reset($offered);

        $this->assertSame('select', $field->control);
        $this->assertSame(['Major' => 'Major', 'Colonel' => 'Colonel'], $field->options);
        $this->assertSame('Major', queuefilter::clean($field, 'Major'));
        $this->assertNull(queuefilter::clean($field, 'Brigadier'), 'a value outside the menu is no filter');
    }

    /**
     * A menu option is offered with the spacing the field stores it with.
     *
     * Core builds a menu's options from a bare explode("\n", param1) and stores the chosen key
     * verbatim (user/profile/field/menu/field.class.php); only "\r" is stripped, by
     * define_save_preprocess(). So an option written with a leading or trailing space really is
     * what lands in {user_info_data}, and a vocabulary trimmed on the way out would offer a value
     * the column can never hold - the filter would match nothing, on every database, for ever.
     *
     * The last assertion is the control from the other side: the trimmed spelling is NOT the
     * site's vocabulary, so it is refused like any other value outside it.
     *
     * @return void
     */
    public function test_a_menu_option_keeps_the_spacing_the_field_stores_it_with(): void {
        $this->custom_field('rank', 'Rank', 'menu', "Alpha \nBeta");
        set_config('queuefilterfields', 'profile_field_rank', 'enrol_apply');

        $offered = queuefilter::resolve(['profile_field_rank' => 'ui1.data']);
        $field = reset($offered);

        $this->assertSame(['Alpha ' => 'Alpha ', 'Beta' => 'Beta'], $field->options);
        $this->assertSame('Alpha ', queuefilter::clean($field, 'Alpha '));
        $this->assertNull(queuefilter::clean($field, 'Alpha'));
    }

    /**
     * A field's label has one spelling here and each sink asks for what it needs.
     *
     * Core's get_display_name() cannot be used directly: it runs format_string(), which escapes by
     * default, for a custom field and a bare get_string() for a standard one, so the list it
     * returns is half escaped and half not. The settings page renders its labels through a triple
     * stash and needs the escaped spelling; the filter's own label and the chip beside it are
     * double stashes and need the plain one.
     *
     * @return void
     */
    public function test_a_label_carries_the_spelling_its_sink_needs(): void {
        $this->custom_field('unit', 'R&D unit');

        $this->assertSame('R&D unit', queuefilter::label('profile_field_unit', false));
        $this->assertSame('R&amp;D unit', queuefilter::label('profile_field_unit', true));
    }

    /**
     * The applied-date bounds are whole local days, and the upper one includes its own day.
     *
     * The upper bound is the midnight that STARTS the following day, compared with a strict
     * less-than, so an application made at any hour of the "to" date is inside the range without
     * any second-level arithmetic. Adding 86400 to the lower bound would be wrong twice a year:
     * a day is not 86400 seconds across a daylight-saving change.
     *
     * @return void
     */
    public function test_the_day_bounds_span_whole_local_days(): void {
        $this->setTimezone('Europe/London');

        [$from, $to] = queuefilter::day_bounds('2026-03-28', '2026-03-29');

        $this->assertSame(make_timestamp(2026, 3, 28, 0, 0, 0, 99), $from);
        // The 30th's midnight, so every hour of the 29th is below it.
        $this->assertSame(make_timestamp(2026, 3, 30, 0, 0, 0, 99), $to);
        // And the clocks changed inside that range, so the span is NOT two whole 86400s.
        $this->assertNotSame(2 * DAYSECS, $to - $from, 'the boundary must not be computed by adding days in seconds');
    }

    /**
     * A malformed date is no filter rather than an error.
     *
     * The same answer the status filter gives a value outside its vocabulary: the queue narrows by
     * what it understood. A hard failure here would turn a mistyped url into an error page.
     *
     * @return void
     */
    public function test_a_malformed_date_is_no_filter(): void {
        foreach (['', 'yesterday', '2026-13-01', '2026-02-30', '26-01-01'] as $bad) {
            $this->assertSame([null, null], queuefilter::day_bounds($bad, $bad), $bad . ' must not bound anything');
        }

        // The control: a real date does bound, so the assertions above are not vacuous.
        $this->assertNotNull(queuefilter::day_bounds('2026-01-01', null)[0]);
    }
}
