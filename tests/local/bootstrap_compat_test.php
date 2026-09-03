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
 * Guards the plugin's markup contract: Bootstrap vocabulary, badge contrast and row headers.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_apply\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards the plugin's markup contract: Bootstrap vocabulary, badge contrast and row headers.
 *
 * Nothing else in the pipeline can see any of the defects below. phpcs reads PHP, the mustache
 * lint reads structure, stylelint reads CSS, and none of them knows what a class name resolves
 * to or what colour it renders. In local_dimensions this exact defect class shipped three
 * times, was correctly root-caused and documented each time, and recurred anyway; a 2026-08-06
 * sweep still found 90 sites. The sibling rule about JS data attributes held at 100% over the
 * same period for one reason only - a Behat leg threw when a dropdown failed to open. The
 * difference is enforcement, not diligence, and this file is that missing observer.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class bootstrap_compat_test extends \basic_testcase {
    /**
     * Class names that only resolve through Moodle 5.x's deprecated Bootstrap 4 compatibility layer.
     *
     * This plugin supports 5.1 and 5.2 only, so the asymmetry runs the opposite way from a plugin
     * that still supports 4.5. These names DO render today, but only because theme/boost's
     * bs4-compat.scss back-ports them, wrapped in @include deprecated-styles() - a red outline
     * under behat-site and themedesignermode - and Moodle 6.0 removes that file entirely
     * (MDL-84465). Their Bootstrap 5 spellings resolve on every supported branch, so writing the
     * BS4 name, or writing both side by side, buys nothing and costs a deprecation.
     *
     * Keyed by the offending token, valued by what to write instead.
     *
     * @return array Token => the spelling that replaces it.
     */
    private function deprecated_class_names(): array {
        return [
            'mr-0' => 'me-0', 'mr-1' => 'me-1', 'mr-2' => 'me-2', 'mr-3' => 'me-3',
            'mr-4' => 'me-4', 'mr-5' => 'me-5', 'mr-auto' => 'me-auto',
            'ml-0' => 'ms-0', 'ml-1' => 'ms-1', 'ml-2' => 'ms-2', 'ml-3' => 'ms-3',
            'ml-4' => 'ms-4', 'ml-5' => 'ms-5', 'ml-auto' => 'ms-auto',
            'pr-0' => 'pe-0', 'pr-1' => 'pe-1', 'pr-2' => 'pe-2', 'pr-3' => 'pe-3',
            'pr-4' => 'pe-4', 'pr-5' => 'pe-5',
            'pl-0' => 'ps-0', 'pl-1' => 'ps-1', 'pl-2' => 'ps-2', 'pl-3' => 'ps-3',
            'pl-4' => 'ps-4', 'pl-5' => 'ps-5',
            'text-left' => 'text-start',
            'text-right' => 'text-end',
            'float-left' => 'float-start',
            'float-right' => 'float-end',
            'border-left' => 'border-start',
            'border-right' => 'border-end',
            'rounded-left' => 'rounded-start',
            'rounded-right' => 'rounded-end',
            'sr-only' => 'visually-hidden',
            'sr-only-focusable' => 'visually-hidden-focusable',
            'no-gutters' => 'g-0',
            'custom-select' => 'form-select',
            'custom-select-sm' => 'form-select-sm',
            'custom-control' => 'form-check',
            'custom-checkbox' => 'form-check',
            'custom-switch' => 'form-switch',
            'badge-pill' => 'rounded-pill',
        ];
    }

    /**
     * Background utilities that must state their text colour, and the utility each one needs.
     *
     * Bootstrap 4's .badge sets no colour at all, so a saturated background renders near-black
     * text on a dark fill; Bootstrap 5's defaults to white, so a LIGHT background renders white
     * on near-white. The branches fail on disjoint sets and the 5.x half ships on this plugin's
     * current target. Measured against the 4.5:1 AA floor: bg-warning is 1.95:1 and bg-secondary
     * 1.49:1 on 5.2 with the default colour. The only markup correct on both is markup that
     * states its own.
     *
     * @return array Background utility => the text utility it requires.
     */
    private function badge_text_colours(): array {
        return [
            'bg-success' => 'text-white',
            'bg-primary' => 'text-white',
            'bg-danger' => 'text-white',
            'bg-info' => 'text-white',
            'bg-dark' => 'text-white',
            'bg-secondary' => 'text-dark',
            'bg-warning' => 'text-dark',
        ];
    }

    /**
     * Absolute path to the plugin root.
     *
     * @return string Plugin directory without a trailing separator.
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * Every file whose contents can put a class name in front of a user.
     *
     * amd/build is skipped because it is generated from amd/src, and docs is skipped because
     * .gitattributes keeps it out of the release zip. The stylesheet is deliberately absent:
     * border-left is a deprecated Bootstrap CLASS and a perfectly ordinary CSS PROPERTY, and a
     * scan that could not tell them apart would fail on the plugin's own valid CSS.
     *
     * @return array List of absolute file paths.
     */
    private function markup_files(): array {
        $root = $this->plugin_root();
        $files = [];
        foreach ([$root . '/templates', $root . '/amd/src', $root . '/classes'] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['mustache', 'js', 'php'], true)) {
                    continue;
                }
                $files[] = $file->getPathname();
            }
        }
        /* The root-level files that render. The queue's table is NOT among them any more -
           it moved to classes/table/, which the recursive scan above already covers. Note that
           this list is guarded by is_file(), so a name that stops existing drops out silently:
           it is a list of places to look, not a list of things that must be there. */
        foreach (['renderer.php', 'edit_form.php'] as $name) {
            if (is_file($root . '/' . $name)) {
                $files[] = $root . '/' . $name;
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Whether a line is prose rather than markup.
     *
     * These rules are about what reaches the browser. A comment naming a class in order to
     * explain the rule - as this file's own neighbours do - is not a breach of it.
     *
     * @param string $line One raw source line.
     * @return bool True when the line opens with a PHP, JS or Mustache comment marker.
     */
    private function is_comment_line(string $line): bool {
        $trimmed = ltrim($line);

        return $trimmed === ''
            || str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, '/*')
            || str_starts_with($trimmed, '*')
            || str_starts_with($trimmed, '{{!');
    }

    /**
     * No shipped markup may use a class name that only 5.x's deprecated compatibility layer defines.
     *
     * @return void
     */
    public function test_no_bootstrap4_only_class_names(): void {
        $offenders = [];
        foreach ($this->markup_files() as $path) {
            foreach (file($path) as $number => $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                foreach ($this->deprecated_class_names() as $token => $replacement) {
                    /* Matched as a whole class token: mr-2 must not be found inside data-mr-2x,
                       and text-left must not be found inside a longer hyphenated name. */
                    if (preg_match('/(?<![-\w])' . preg_quote($token, '/') . '(?![-\w])/', $line)) {
                        $offenders[] = basename($path) . ':' . ($number + 1) . ' uses ' . $token
                            . ', write ' . $replacement;
                    }
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'These class names only resolve through theme/boost/scss/moodle/bs4-compat.scss, which '
                . 'wraps them in @include deprecated-styles() and which Moodle 6.0 removes. Their '
                . 'Bootstrap 5 spellings work on every supported branch: ' . implode('; ', $offenders)
        );
    }

    /**
     * Every background utility must state its text colour, so it reads on both branches.
     *
     * Checked on every line carrying a background utility, NOT only on lines that also say
     * "badge". The first draft of the local_dimensions original filtered on that word and stayed
     * green while a match arm returned a bare 'bg-success' with the word "badge" one line up in
     * the method name.
     *
     * @return void
     */
    public function test_every_badge_background_declares_a_text_colour(): void {
        $offenders = [];
        foreach ($this->markup_files() as $path) {
            foreach (file($path) as $number => $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                foreach ($this->badge_text_colours() as $background => $required) {
                    if (!preg_match('/(?<![-\w])' . preg_quote($background, '/') . '(?![-\w])/', $line)) {
                        continue;
                    }
                    if (!preg_match('/(?<![-\w])text-(white|dark|body)(?![-\w])/', $line)) {
                        $offenders[] = basename($path) . ':' . ($number + 1) . ' needs ' . $required;
                    }
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'Bootstrap 4 gives .badge no text colour and Bootstrap 5 defaults it to white, so a '
                . 'background that does not state its own colour fails the 4.5:1 contrast floor on '
                . 'one branch or the other: ' . implode('; ', $offenders)
        );
    }

    /**
     * The stylesheet must read the theme's colour tokens rather than hardcoding a palette.
     *
     * A hardcoded colour means every site with its own institutional palette sees the plugin's
     * instead, and - because 5.1 and 5.2 both ship dark mode - a light literal paints a light
     * slab inside a dark page. A literal is allowed only as the final fallback of a var() chain,
     * which is why the check ignores anything inside var(...).
     *
     * @return void
     */
    public function test_stylesheet_declares_no_hardcoded_brand_colour(): void {
        $named = ['grey', 'gray', 'black', 'white', 'red', 'green', 'blue', 'silver', 'orange', 'yellow'];
        $offenders = [];
        foreach ($this->stylesheets() as $sheet) {
            $lines = explode("\n", $this->strip_css_comments((string) file_get_contents($sheet)));
            foreach ($lines as $number => $line) {
                /* Only a real declaration is judged, and every var() call is removed first: the
                   literal inside a var() chain is its fallback, which is exactly the spelling
                   this test exists to require. */
                if (!preg_match('/^\s*[-a-z]+\s*:/i', $line)) {
                    continue;
                }
                $value = substr($line, strpos($line, ':') + 1);
                $value = (string) preg_replace('/var\([^;]*\)/', '', $value);
                $bad = preg_match('/#[0-9a-f]{3,8}\b/i', $value)
                    || preg_match('/(?<![-\w])(rgb|rgba|hsl|hsla)\s*\(/i', $value)
                    || preg_match('/(?<![-\w])(' . implode('|', $named) . ')(?![-\w])/i', $value);
                if ($bad) {
                    $offenders[] = basename($sheet) . ':' . ($number + 1) . ' ' . trim($line);
                }
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'These declarations carry a colour literal outside a var() fallback position. Read the '
                . 'theme instead, as var(--bs-name, var(--bs4name, #literal)): '
                . implode('; ', $offenders)
        );
    }

    /**
     * Every fill this stylesheet paints must state the text colour that goes on it.
     *
     * The badge rule above, one level up, and it is a defect this plugin measured rather than
     * imagined. Moodle 5.2 ships TWO dark mechanisms: [data-bs-theme="dark"] flips every --bs-*
     * token, while the legacy .theme-dark recolours text directly and leaves the tokens at their
     * light values. So a fill read from a token and a colour left to inheritance come from
     * different mechanisms, and under .theme-dark the queue's evidence pills rendered #dee2e6 on
     * #e9ecef - 1.05:1, invisible - while every automated gate stayed green. Nothing in the
     * pipeline can see this: stylelint validates syntax, and no branch of CI renders a page in
     * either dark mode.
     *
     * A block painting no text is exempt, and the exemption is by selector rather than by a
     * heuristic: the meters are bars, and requiring a colour on them would teach the next reader
     * to add one wherever the test complains rather than to think about it.
     *
     * @return void
     */
    public function test_every_stylesheet_fill_declares_its_own_text_colour(): void {
        /* Selectors whose block contains no text, with the reason. A rule added here has to be
           able to state one. */
        $textless = [
            '.enrol_apply-meter' => 'a track, drawn empty',
            '.enrol_apply-meterfill' => 'the bar inside the track',
            '.enrol_apply-meterfill-warn' => 'the same bar, recoloured',
        ];

        $offenders = [];
        foreach ($this->stylesheets() as $sheet) {
            $css = $this->strip_css_comments((string) file_get_contents($sheet));
            /* Rule blocks, selector and body. Nested at-rules are handled by the body pattern
               refusing to cross a brace, so a media query's own header never matches. */
            preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $matches, PREG_SET_ORDER);
            foreach ($matches as $rule) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1]));
                $body = $rule[2];
                if (!preg_match('/(?<![-\w])background(-color)?\s*:/i', $body)) {
                    continue;
                }
                if (preg_match('/(?<![-\w])color\s*:/i', $body)) {
                    continue;
                }
                if (array_key_exists($selector, $textless)) {
                    continue;
                }
                $offenders[] = basename($sheet) . ' { ' . $selector . ' }';
            }
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'These rules paint a background and leave the text colour to inheritance. The two then '
                . 'come from different dark-mode mechanisms - .theme-dark moves the inherited '
                . 'colour and not the --bs-* tokens - so one of them renders the text on top of '
                . 'itself. Declare both, or name the selector in this test as painting no text: '
                . implode('; ', $offenders)
        );
    }

    /**
     * The plugin must not declare custom properties inside core's design-system namespace.
     *
     * Moodle 5.2 ships theme/boost/scss/design-system/ with $mds-* tokens and 5.3 LTS brings MDS
     * React, so an --mds-* declaration squats a namespace core is actively expanding.
     *
     * @return void
     */
    public function test_stylesheet_declares_no_core_design_system_tokens(): void {
        $offenders = [];
        foreach ($this->stylesheets() as $sheet) {
            foreach (file($sheet) as $number => $line) {
                // A declaration, not a mention: the property name followed by its colon.
                if (preg_match('/--mds-[a-z0-9-]+\s*:/i', $line)) {
                    $offenders[] = basename($sheet) . ':' . ($number + 1);
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'These lines declare custom properties in core\'s --mds- namespace; use the plugin\'s '
                . 'own frankenstyle prefix instead: ' . implode(', ', $offenders)
        );
    }

    /**
     * Both table classes must name the column that identifies a row.
     *
     * Deliberately a source scan rather than an assertion on rendered HTML: flexible_table's
     * define_header_column() has no observable return and no getter, and rendering a table_sql
     * needs a database, an output buffer and a full page setup for one boolean fact.
     *
     * @return void
     */
    public function test_every_table_class_defines_a_header_column(): void {
        $offenders = [];
        foreach (['classes/table/applications.php'] as $name) {
            $source = file_get_contents($this->plugin_root() . '/' . $name);
            if (!str_contains((string) $source, 'define_header_column(')) {
                $offenders[] = $name;
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'These table classes never call define_header_column(), so every row renders as a wall '
                . 'of <td> with nothing naming who the row is about: ' . implode(', ', $offenders)
        );
    }

    /**
     * Remove CSS comments while keeping every line on the line number it started on.
     *
     * Stripping per line cannot work: a comment spanning several lines leaves its middle lines
     * intact, and prose about colour then reads as a declaration of one. This test failed on its
     * own explanatory comment the first time it ran, which is how that was found.
     *
     * @param string $css Raw stylesheet contents.
     * @return string The same text with comment bodies replaced by their own newlines.
     */
    private function strip_css_comments(string $css): string {
        return (string) preg_replace_callback(
            '~/\*.*?\*/~s',
            static function ($match) {
                return str_repeat("\n", substr_count($match[0], "\n"));
            },
            $css
        );
    }

    /**
     * Every stylesheet the plugin ships.
     *
     * @return array List of absolute file paths.
     */
    private function stylesheets(): array {
        $root = $this->plugin_root();

        return array_values(array_filter(array_merge(
            [$root . '/styles.css'],
            glob($root . '/styles_*.css') ?: []
        ), 'is_file'));
    }
}
