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
 * Tests for reading and writing a custom profile field through its own type.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/apply/tests/fixtures/municipality_field.php');

/**
 * Tests for reading and writing a custom profile field through its own type.
 *
 * profilefield_brasilufmunicipio is the case the class exists for; see the municipality_field
 * fixture. The core types beside it run everywhere and hold the same rules for the shapes core
 * ships.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(customfield::class)]
#[CoversClass(fields::class)]
final class customfield_test extends \advanced_testcase {
    use \enrol_apply\municipality_field;

    /**
     * Reset the database after every test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create a custom profile field.
     *
     * @param string $shortname Field shortname.
     * @param string $datatype Field type.
     * @param array $extra Further columns.
     * @return \stdClass The {user_info_field} record.
     */
    protected function create_field(string $shortname, string $datatype = 'text', array $extra = []): \stdClass {
        return $this->getDataGenerator()->create_custom_profile_field($extra + [
            'shortname' => $shortname,
            'name' => 'Field ' . $shortname,
            'datatype' => $datatype,
        ]);
    }

    /**
     * The value a municipality field would store is built from its selects, not its hidden input.
     *
     * The selects are not in the request: under the modal they arrive inside one formdata string,
     * and on profile.php not at all. The type reads them with optional_param(), so with_request()
     * has to put them there, and has to take them away again.
     *
     * @return void
     */
    public function test_a_municipality_is_stored_as_the_type_stores_it(): void {
        $record = $this->create_municipality_field();
        $field = customfield::field((int) $record->id, 0);

        // The precondition: nothing of the field is in the request.
        $this->assertArrayNotHasKey('profile_field_cidade_uf', $_POST);

        $stored = customfield::stored_value($field, [
            'profile_field_cidade' => 1,
            'profile_field_cidade_uf' => 'MG',
            'profile_field_cidade_municipio' => self::$belohorizonte,
        ]);

        $this->assertSame(
            ['uf' => 'MG', 'codmunicipio' => self::$belohorizonte, 'nome' => 'Belo Horizonte'],
            json_decode($stored->data, true)
        );
        $this->assertArrayNotHasKey('profile_field_cidade_uf', $_POST);
        $this->assertArrayNotHasKey('profile_field_cidade_municipio', $_POST);
    }

    /**
     * A stored municipality is shown as the type shows it, never as the JSON behind it.
     *
     * @return void
     */
    public function test_a_stored_municipality_is_shown_as_its_name(): void {
        $record = $this->create_municipality_field();
        $user = $this->getDataGenerator()->create_user();
        $json = $this->municipality_json('MG', self::$belohorizonte, 'Belo Horizonte');
        $this->store_profile_value($record, (int) $user->id, $json);

        $this->assertSame('MG / Belo Horizonte', fields::current_display(fields::custom_key((int) $record->id), $user));
        // The control: the column itself is JSON, which is what the plugin used to show.
        $this->assertStringStartsWith('{', fields::current_value(fields::custom_key((int) $record->id), $user));
    }

    /**
     * The placeholder a municipality field stores for "nothing chosen" reads as empty.
     *
     * profilefield_brasilufmunicipio has stored its hidden input's value, the string "1", when
     * neither select was used. Taken at face value it is a value, so the field would never count as missing and a
     * blank submission would be offered as a change to save.
     *
     * @return void
     */
    public function test_a_municipality_left_unchosen_is_empty(): void {
        $record = $this->create_municipality_field();
        $field = customfield::field((int) $record->id, 0);

        $stored = customfield::stored_value($field, [
            'profile_field_cidade' => 1,
            'profile_field_cidade_uf' => '',
            'profile_field_cidade_municipio' => '',
        ]);

        $this->assertSame('', customfield::display($field, $stored));
        // The placeholder itself, which a site already holds wherever the type stored it.
        $this->assertSame('', customfield::display($field, (object) ['data' => '1', 'dataformat' => 0]));
        // The control: a real value is not empty.
        $json = $this->municipality_json('MG', self::$belohorizonte, 'Belo Horizonte');
        $this->assertSame('MG / Belo Horizonte', customfield::display($field, (object) ['data' => $json, 'dataformat' => 0]));
    }

    /**
     * What the request carried under a field's names is put back after the type has read them.
     *
     * Replaced while the type reads, because on profile.php anything posted under those names is
     * something the applicant never saw.
     *
     * @return void
     */
    public function test_the_request_is_restored_after_the_type_reads_it(): void {
        $record = $this->create_municipality_field();
        $field = customfield::field((int) $record->id, 0);
        $_POST['profile_field_cidade_uf'] = 'SP';
        $_POST['profile_field_cidade_municipio'] = self::$saopaulo;

        try {
            $stored = customfield::stored_value($field, [
                'profile_field_cidade' => 1,
                'profile_field_cidade_uf' => 'MG',
                'profile_field_cidade_municipio' => self::$belohorizonte,
            ]);

            $this->assertSame('MG', json_decode($stored->data)->uf);
            $this->assertSame('SP', $_POST['profile_field_cidade_uf']);
            $this->assertSame(self::$saopaulo, $_POST['profile_field_cidade_municipio']);
        } finally {
            unset($_POST['profile_field_cidade_uf'], $_POST['profile_field_cidade_municipio']);
        }
    }

    /**
     * Another field's elements are never claimed, though their names start with this one's.
     *
     * @return void
     */
    public function test_another_fields_element_is_never_claimed(): void {
        $this->create_field('cidade_natal');
        $record = $this->create_field('cidade');
        $field = customfield::field((int) $record->id, 0);

        $own = customfield::own_values($field, [
            'profile_field_cidade' => 'Campinas',
            'profile_field_cidade_extra' => 'part of it',
            'profile_field_cidade_natal' => 'Recife',
            'profile_field_cidade_natal_uf' => 'PE',
            'city' => 'Elsewhere',
        ]);

        $this->assertSame(['profile_field_cidade' => 'Campinas', 'profile_field_cidade_extra' => 'part of it'], $own);
    }

    /**
     * A municipality is written through the type's own save, as JSON naming the municipality.
     *
     * @return void
     */
    public function test_a_municipality_is_written_through_the_types_own_save(): void {
        global $DB;

        $record = $this->create_municipality_field();
        $user = $this->getDataGenerator()->create_user();
        $field = customfield::field((int) $record->id, (int) $user->id);

        customfield::save($field, (int) $user->id, [
            'profile_field_cidade' => 1,
            'profile_field_cidade_uf' => 'SP',
            'profile_field_cidade_municipio' => self::$saopaulo,
        ]);

        $stored = $DB->get_field('user_info_data', 'data', ['userid' => $user->id, 'fieldid' => $record->id]);
        $this->assertSame(
            ['uf' => 'SP', 'codmunicipio' => self::$saopaulo, 'nome' => 'São Paulo'],
            json_decode($stored, true)
        );
    }

    /**
     * A date is shown as a date, in the frozen snapshot as everywhere else.
     *
     * @return void
     */
    public function test_a_date_is_shown_as_a_date_and_an_unset_one_as_nothing(): void {
        $record = $this->create_field('born', 'datetime', ['param1' => 1950, 'param2' => 2030, 'param3' => 0]);
        $field = customfield::field((int) $record->id, 0);
        $timestamp = make_timestamp(1990, 5, 17);

        $stored = customfield::stored_value($field, ['profile_field_born' => $timestamp]);

        $this->assertSame((string) $timestamp, $stored->data);
        $this->assertSame(userdate($timestamp, get_string('strftimedate', 'langconfig')), customfield::display($field, $stored));
        // An optional date left disabled posts 0, which the type calls "Not set": that is nothing.
        $this->assertSame('', customfield::display($field, customfield::stored_value($field, ['profile_field_born' => 0])));
    }

    /**
     * A checkbox says yes or no, and an unticked one is still an answer.
     *
     * @return void
     */
    public function test_a_checkbox_says_yes_or_no(): void {
        $record = $this->create_field('consent', 'checkbox');
        $field = customfield::field((int) $record->id, 0);

        $ticked = customfield::stored_value($field, ['profile_field_consent' => 1]);
        $unticked = customfield::stored_value($field, ['profile_field_consent' => 0]);

        $this->assertSame(get_string('yes'), customfield::display($field, $ticked));
        $this->assertSame(get_string('no'), customfield::display($field, $unticked));
    }

    /**
     * A menu stores only one of its own options.
     *
     * @return void
     */
    public function test_a_menu_stores_only_its_own_options(): void {
        $record = $this->create_field('rank', 'menu', ['param1' => "Alpha\nBeta"]);
        $field = customfield::field((int) $record->id, 0);

        $stored = customfield::stored_value($field, ['profile_field_rank' => 'Beta']);

        $this->assertSame('Beta', customfield::display($field, $stored));
        $this->assertNull(customfield::stored_value($field, ['profile_field_rank' => 'Gamma']));
    }

    /**
     * A text value keeps a bare "<", which the type's own display_data() would strip from.
     *
     * @return void
     */
    public function test_a_text_value_is_shown_as_typed(): void {
        $record = $this->create_field('motto', 'text', ['param1' => 30, 'param2' => 2048]);
        $field = customfield::field((int) $record->id, 0);

        $stored = customfield::stored_value($field, ['profile_field_motto' => 'A<B and R&D']);

        $this->assertSame('A<B and R&D', customfield::display($field, $stored));
        // The control: what the type itself would show has lost the tail.
        $holder = customfield::field((int) $record->id, 0);
        $holder->set_user_data('A<B and R&D', FORMAT_MOODLE);
        $this->assertStringNotContainsString('R&D', html_entity_decode($holder->display_data()));
    }

    /**
     * A textarea keeps the format its editor submitted.
     *
     * @return void
     */
    public function test_a_textarea_keeps_its_format(): void {
        $record = $this->create_field('bio', 'textarea');
        $field = customfield::field((int) $record->id, 0);

        $stored = customfield::stored_value($field, [
            'profile_field_bio' => ['text' => '<p>Long <b>answer</b></p>', 'format' => FORMAT_HTML],
        ]);

        $this->assertSame((int) FORMAT_HTML, $stored->dataformat);
        $this->assertSame('Long ANSWER', customfield::display($field, $stored));
    }

    /**
     * A field whose type is no longer installed is neither offered nor drawn.
     *
     * Its rows outlive the plugin, and profile_get_user_field() would require a file that is not
     * there.
     *
     * @return void
     */
    public function test_a_field_of_an_uninstalled_type_is_absent(): void {
        // The user first: creating one saves every profile field, which would require the gone type.
        $user = $this->getDataGenerator()->create_user();
        $gone = $this->create_field('gone', 'notinstalled');
        $kept = $this->create_field('kept');

        $this->assertArrayNotHasKey(fields::custom_key((int) $gone->id), fields::offerable());
        $this->assertSame(fields::STATE_ABSENT, fields::classify(fields::custom_key((int) $gone->id), $user));
        $this->assertNull(customfield::field((int) $gone->id, (int) $user->id));
        // The control: a field of an installed type beside it is offered and editable.
        $this->assertArrayHasKey(fields::custom_key((int) $kept->id), fields::offerable());
        $this->assertSame(fields::STATE_EDITABLE, fields::classify(fields::custom_key((int) $kept->id), $user));
    }

    /**
     * The snapshot carries what each custom value says, not the shape its type stores.
     *
     * @return void
     */
    public function test_the_submitted_values_are_what_each_type_says(): void {
        global $DB;

        $municipality = $this->create_municipality_field();
        $born = $this->create_field('born', 'datetime', ['param1' => 1950, 'param2' => 2030, 'param3' => 0]);
        $keys = [fields::custom_key((int) $municipality->id), fields::custom_key((int) $born->id)];
        set_config('allowedfields', implode(',', $keys), 'enrol_apply');
        $course = $this->getDataGenerator()->create_course();
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $instance->customtext4 = fieldset::from_keys($keys)->to_json();
        $timestamp = make_timestamp(1990, 5, 17);

        $values = fields::submitted_values($instance, (object) [
            'profile_field_cidade' => 1,
            'profile_field_cidade_uf' => 'MG',
            'profile_field_cidade_municipio' => self::$belohorizonte,
            'profile_field_born' => $timestamp,
        ]);

        $date = userdate($timestamp, get_string('strftimedate', 'langconfig'));
        $this->assertSame(['MG / Belo Horizonte', $date], array_column($values, 'value'));
    }
}
