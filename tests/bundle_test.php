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
 * The course report's React client arrives in one file, and that file is fresh.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply;

use basic_testcase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The bundle against its sources (block_compass's bundle_test, the fleet's precedent).
 *
 * Core's grunt regenerates the per-file outputs and CI fails on a diff, so those cannot go stale
 * unnoticed; the bundle is written by a build step outside core's grunt, which core's check cannot
 * see. The manifest that step writes beside the bundle - the SHA-1 of every source - is what this
 * test recomputes, so a source edited without a rebundle fails here. It reads files only.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class bundle_test extends basic_testcase {
    /** @var string The plugin's root. */
    private const ROOT = __DIR__ . '/..';

    /** @var string The entry the marker names. */
    private const ENTRY = 'src/report/ReportView.tsx';

    /** @var string What to do when the build is stale. */
    private const REBUILD = 'rebuild js/esm/build on Moodle 5.3 (core\'s esm task, then the bundle that js/esm/bundle.json '
        . 'names with its manifest) and commit it';

    /**
     * The marker names the entry and the output the bundle step builds, and the shell mounts that output.
     *
     * @return void
     */
    public function test_the_marker_names_the_entry_and_the_shell_mounts_the_bundle(): void {
        $marker = json_decode((string) file_get_contents(self::ROOT . '/js/esm/bundle.json'), true);

        $this->assertSame(self::ENTRY, $marker['entry'] ?? null);
        $this->assertSame('build/bundle.js', $marker['outfile'] ?? null);
        $this->assertFileExists(self::ROOT . '/js/esm/build/bundle.js');

        $template = (string) file_get_contents(self::ROOT . '/templates/report_view.mustache');
        $this->assertStringContainsString('"component": "@moodle/lms/enrol_apply/bundle"', $template);
        foreach (glob(self::ROOT . '/templates/*.mustache') as $file) {
            preg_match_all('/@moodle\/lms\/enrol_apply\/([A-Za-z_\/]+)/', (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $module) {
                $this->assertSame('bundle', $module, basename($file) . " names the per-file module {$module}");
            }
        }
    }

    /**
     * Every source is in the manifest with the hash of the file on disk, and nothing else is.
     *
     * A source edited without a rebundle differs; one added is missing; one deleted is listed for
     * nothing. All three mean the bundle the page loads was built from other sources than these.
     *
     * @return void
     */
    public function test_the_bundle_is_as_fresh_as_the_sources(): void {
        $manifest = json_decode((string) file_get_contents(self::ROOT . '/js/esm/build/bundle.manifest.json'), true);
        $this->assertIsArray($manifest, 'no manifest beside the bundle: ' . self::REBUILD);
        $this->assertSame(self::ENTRY, $manifest['entry'] ?? null);

        $ondisk = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/js/esm/src'));
        foreach ($iterator as $file) {
            if (!$file->isFile() || !preg_match('/\.tsx?$/', $file->getFilename())) {
                continue;
            }
            $relative = 'src/' . str_replace('\\', '/', substr($file->getPathname(), strlen(self::ROOT . '/js/esm/src/')));
            $ondisk[$relative] = sha1_file($file->getPathname());
        }
        ksort($ondisk);
        // Vacuity guard: the sources were read at all.
        $this->assertGreaterThan(10, count($ondisk), 'js/esm/src holds fewer sources than the client has');

        $listed = $manifest['sources'] ?? [];
        ksort($listed);
        $this->assertSame(array_keys($ondisk), array_keys($listed), 'the manifest does not list exactly the sources on disk: '
            . self::REBUILD);
        foreach ($ondisk as $relative => $hash) {
            $this->assertSame($hash, $listed[$relative], "{$relative} was edited after the bundle was built: " . self::REBUILD);
        }
    }

    /**
     * No component is offered to a theme's swizzle: the page mounts the bundle, which inlines them.
     *
     * Moodle 5.3's build writes js/esm/src/swizzle.json and defaults a component it finds to risky; a
     * theme's override of a per-file module would be served for a specifier nothing on the page
     * imports, so it would silently do nothing. Every entry is prohibited.
     *
     * @return void
     */
    public function test_no_component_is_offered_to_a_themes_swizzle(): void {
        $declared = json_decode((string) file_get_contents(self::ROOT . '/js/esm/src/swizzle.json'), true);
        $this->assertIsArray($declared, 'js/esm/src/swizzle.json is missing: run the 5.3 build and commit it');
        // Vacuity guard: the components were declared at all.
        $this->assertGreaterThan(5, count($declared));
        foreach ($declared as $module => $actions) {
            $this->assertSame(['eject' => 'prohibited', 'wrap' => 'prohibited'], $actions, $module);
        }
    }

    /**
     * The release package leaves the development build out (the .dev.js twins and the source maps).
     *
     * Read as written, since git's own matching is not available to a test: the two lines must stand.
     *
     * @return void
     */
    public function test_the_package_leaves_the_development_build_out(): void {
        $rules = [];
        foreach (file(self::ROOT . '/.gitattributes', FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$pattern, $attributes] = preg_split('/\s+/', $line, 2) + [1 => ''];
            if (str_contains($attributes, 'export-ignore')) {
                $rules[] = $pattern;
            }
        }
        // Vacuity guard: the file's export-ignore rules were read.
        $this->assertContains('docs', $rules);
        $this->assertContains('/js/esm/build/**/*.dev.js', $rules, 'the .dev.js twins ship');
        $this->assertContains('/js/esm/build/**/*.js.map', $rules, 'the source maps ship');
    }
}
