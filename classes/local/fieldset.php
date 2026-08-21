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

namespace enrol_apply\local;

/**
 * The versioned envelope an instance stores in customtext4.
 *
 * Only two facts per field are stored: which field, and whether it is required. Everything
 * else about a field - its label, its datatype, whether it is visible, whether it still
 * exists - is a fact about *this site* and is resolved live by {@see fields::resolve()}.
 * Storing any of it would make it forgeable, because customtext4 is carried verbatim by
 * core's backup (backup/moodle2/backup_stepslib.php) and copied onto a new instance by
 * enrol_plugin::add_instance() with no allowlist at all, so anyone who can restore a course
 * chooses its contents.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fieldset {
    /** @var int Envelope format. Bumped only if the stored shape changes incompatibly. */
    public const VERSION = 1;

    /** @var array List of entries, each an array with a 'key' string and a 'required' bool. */
    protected $entries = [];

    /**
     * Build a fieldset from its stored JSON.
     *
     * Every failure mode returns an empty set rather than throwing: the input is untrusted,
     * it arrives from a backup taken on a site nobody here controls, and an exception would
     * take out the enrolment page for everybody rather than the one instance.
     *
     * @param string|null $json Stored envelope, or null/empty when the instance has none.
     * @return self The parsed set, empty when the input was absent or unusable.
     */
    public static function from_json(?string $json): self {
        $set = new self();
        if ($json === null || trim($json) === '') {
            return $set;
        }

        /* customtext4 is a TEXT column with no length ceiling, and its content arrives from a
           restored course rather than from this site's own form, so the input is unbounded and
           untrusted. A real envelope for every field a site could offer is a few kilobytes;
           anything past this is not a truncated envelope to be salvaged but a payload, and it
           is refused whole. json_decode's own depth limit then fails safe below. */
        if (strlen($json) > 64 * 1024) {
            return $set;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['fields']) || !is_array($decoded['fields'])) {
            return $set;
        }
        if ((int) ($decoded['version'] ?? 0) !== self::VERSION) {
            /* A newer site wrote this. Refusing to guess is the safe reading: an unknown
               shape resolves to no fields, which collects nothing, rather than to a
               misparsed shape, which could collect the wrong thing. */
            return $set;
        }

        foreach ($decoded['fields'] as $entry) {
            if (!is_array($entry) || !isset($entry['key']) || !is_string($entry['key'])) {
                continue;
            }
            $set->add($entry['key'], !empty($entry['required']));
        }

        return $set;
    }

    /**
     * Build a fieldset from a list of keys and a list of the keys among them that are required.
     *
     * @param array $keys Field keys, in the order they should be offered.
     * @param array $required Keys that must be filled in, as a list of key strings.
     * @return self The assembled set.
     */
    public static function from_keys(array $keys, array $required = []): self {
        $set = new self();
        foreach ($keys as $key) {
            $set->add((string) $key, in_array((string) $key, $required, true));
        }

        return $set;
    }

    /**
     * Add one field to the set, ignoring a key that is already present.
     *
     * @param string $key Field key in the s_column or c_id form.
     * @param bool $required Whether the applicant must fill it in.
     * @return void
     */
    public function add(string $key, bool $required = false): void {
        foreach ($this->entries as $entry) {
            if ($entry['key'] === $key) {
                return;
            }
        }
        $this->entries[] = ['key' => $key, 'required' => $required];
    }

    /**
     * The field keys in this set, in order.
     *
     * @return array List of key strings.
     */
    public function keys(): array {
        return array_column($this->entries, 'key');
    }

    /**
     * Whether the named field is in this set.
     *
     * @param string $key Field key.
     * @return bool True when present.
     */
    public function has(string $key): bool {
        return in_array($key, $this->keys(), true);
    }

    /**
     * Whether the named field is marked required.
     *
     * @param string $key Field key.
     * @return bool True when present and required.
     */
    public function is_required(string $key): bool {
        foreach ($this->entries as $entry) {
            if ($entry['key'] === $key) {
                return $entry['required'];
            }
        }

        return false;
    }

    /**
     * Whether the set holds no fields at all.
     *
     * @return bool True when empty.
     */
    public function is_empty(): bool {
        return $this->entries === [];
    }

    /**
     * Keep only the named keys, preserving this set's order and each key's required flag.
     *
     * @param array $keys Keys to keep, as a list of key strings.
     * @return self A new set holding the intersection.
     */
    public function only(array $keys): self {
        $kept = new self();
        foreach ($this->entries as $entry) {
            if (in_array($entry['key'], $keys, true)) {
                $kept->add($entry['key'], $entry['required']);
            }
        }

        return $kept;
    }

    /**
     * The envelope as it is stored on the instance.
     *
     * @return string JSON, or an empty string when the set is empty so the column stays clean.
     */
    public function to_json(): string {
        if ($this->is_empty()) {
            return '';
        }

        return (string) json_encode([
            'version' => self::VERSION,
            'fields' => array_map(static function (array $entry): array {
                return ['key' => $entry['key'], 'required' => $entry['required'] ? 1 : 0];
            }, $this->entries),
        ]);
    }
}
