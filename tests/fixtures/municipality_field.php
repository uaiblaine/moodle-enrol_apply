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

namespace enrol_apply;

/**
 * A profilefield_brasilufmunicipio field for the tests that need a type with a shape of its own.
 *
 * That type stores JSON, draws a hidden input beside two selects, and reads the selects from the
 * request, which is every way a type can differ from text. A test using it skips where the plugin
 * is not installed; ci.yml installs it on every job.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait municipality_field {
    /** @var string IBGE code of Belo Horizonte. */
    protected static $belohorizonte = '3106200';

    /** @var string IBGE code of São Paulo. */
    protected static $saopaulo = '3550308';

    /**
     * Skip unless profilefield_brasilufmunicipio is installed, then create a field of its type.
     *
     * Two municipalities are loaded into the plugin's own table, which its scheduled task fills
     * from the IBGE service on a real site.
     *
     * @param string $shortname Field shortname.
     * @param array $extra Further {user_info_field} columns.
     * @return \stdClass The {user_info_field} record.
     */
    protected function create_municipality_field(string $shortname = 'cidade', array $extra = []): \stdClass {
        global $DB;

        if (!\enrol_apply\local\customfield::type_available('brasilufmunicipio')) {
            $this->markTestSkipped('profilefield_brasilufmunicipio is not installed.');
        }

        $DB->insert_record('profilefield_brasilufmunicipio', (object) [
            'uf' => 'MG', 'ibgeid' => self::$belohorizonte, 'municipio' => 'Belo Horizonte',
        ]);
        $DB->insert_record('profilefield_brasilufmunicipio', (object) [
            'uf' => 'SP', 'ibgeid' => self::$saopaulo, 'municipio' => 'São Paulo',
        ]);

        return $this->getDataGenerator()->create_custom_profile_field($extra + [
            'shortname' => $shortname,
            'name' => 'Cidade',
            'datatype' => 'brasilufmunicipio',
            'param1' => 'MG,SP',
        ]);
    }

    /**
     * The JSON profilefield_brasilufmunicipio stores for a municipality.
     *
     * @param string $uf The state.
     * @param string $code The IBGE code.
     * @param string $name The municipality's name.
     * @return string The stored value.
     */
    protected function municipality_json(string $uf, string $code, string $name): string {
        return json_encode(['uf' => $uf, 'codmunicipio' => $code, 'nome' => $name], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Store a value for a user directly, as the field's own save would.
     *
     * @param \stdClass $field The {user_info_field} record.
     * @param int $userid User id.
     * @param string $data The stored value.
     * @return void
     */
    protected function store_profile_value(\stdClass $field, int $userid, string $data): void {
        global $DB;

        $DB->insert_record('user_info_data', (object) [
            'userid' => $userid,
            'fieldid' => $field->id,
            'data' => $data,
            'dataformat' => 0,
        ]);
    }
}
