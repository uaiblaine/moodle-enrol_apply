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
 * No other gate sees these defects: phpcs reads PHP, the mustache lint reads structure and
 * stylelint reads CSS syntax, and none of them knows what a class name resolves to or what
 * colour it renders.
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
     * These names still render, but only because theme/boost's bs4-compat.scss back-ports them,
     * wrapped in @include deprecated-styles() (a red outline under behat-site and
     * themedesignermode), and Moodle 6.0 removes that file (MDL-84465). Their Bootstrap 5
     * spellings resolve on every supported branch, so writing the Bootstrap 4 name, alone or
     * beside its replacement, buys nothing and costs a deprecation.
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
     * Bootstrap 5's .badge defaults to white text, so a light background fails the 4.5:1 AA
     * floor: on Boost, bg-warning gives 1.95:1 and bg-secondary 1.49:1. Bootstrap 4's set no
     * colour, which failed on the dark backgrounds instead, so markup that states its own text
     * colour is the only kind that does not depend on the default.
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
     * border-left is both a deprecated Bootstrap class and an ordinary CSS property, and a scan
     * that could not tell them apart would fail on the plugin's own valid CSS.
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
        /* The root-level files that render markup. Each is guarded by is_file(), so a name
           that stops existing drops out silently: this is a list of places to look, not a list
           of files that must exist. */
        foreach (['renderer.php', 'edit_form.php'] as $name) {
            if (is_file($root . '/' . $name)) {
                $files[] = $root . '/' . $name;
            }
        }
        sort($files);

        return $files;
    }

    /**
     * The lines of one source that can reach the browser, keyed by their line number.
     *
     * These rules are about what reaches the browser, so a comment naming a class in order to
     * explain the rule - as this file's own neighbours do - is not a breach of it. Comments are
     * removed from the whole source before it is split, each replaced by the newlines it spanned
     * so every remaining line keeps its number, as strip_css_comments() does for the stylesheet.
     * Deciding line by line cannot work: a line inside a block comment need not start with a
     * comment marker, and this plugin's block comments continue on indented prose lines with no
     * leading "*".
     *
     * @param string $source File contents.
     * @param string $extension File extension without the dot: php, mustache or js.
     * @return array Line number (1-based) => raw line, blank lines left out.
     */
    private function markup_lines(string $source, string $extension): array {
        $stripped = match ($extension) {
            'php' => $this->strip_php_comments($source),
            'mustache' => $this->strip_mustache_comments($source),
            'js' => $this->strip_js_comments($source),
        };

        $lines = [];
        foreach (explode("\n", $stripped) as $index => $line) {
            if (trim($line) !== '') {
                $lines[$index + 1] = $line;
            }
        }

        return $lines;
    }

    /**
     * Remove PHP comments, keeping every line on the line number it started on.
     *
     * The tokenizer decides what a comment is, so a comment marker inside a string literal is
     * kept as the string it is, and a trailing // comment after code goes as well.
     *
     * @param string $source PHP source.
     * @return string The same source with each comment replaced by its own newlines.
     */
    private function strip_php_comments(string $source): string {
        $out = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
                $out .= str_repeat("\n", substr_count($token[1], "\n"));
            } else {
                $out .= is_array($token) ? $token[1] : $token;
            }
        }

        return $out;
    }

    /**
     * Remove Mustache comments, keeping every line on the line number it started on.
     *
     * A Mustache comment closes at the first "}}", which is also where this match stops.
     *
     * @param string $source Template source.
     * @return string The same source with each comment replaced by its own newlines.
     */
    private function strip_mustache_comments(string $source): string {
        return (string) preg_replace_callback(
            '~\{\{!.*?\}\}~s',
            static function ($match) {
                return str_repeat("\n", substr_count($match[0], "\n"));
            },
            $source
        );
    }

    /**
     * Remove JavaScript comments, keeping every line on the line number it started on.
     *
     * String literals are matched first and kept, so a "/*" in a string does not open a comment
     * and the "//" of a url does not end the line. A regular expression literal is not
     * recognised, so a comment marker inside one would be taken for a comment.
     *
     * @param string $source JavaScript source.
     * @return string The same source with each comment replaced by its own newlines.
     */
    private function strip_js_comments(string $source): string {
        $strings = '"(?:\\\\.|[^"\\\\\n])*"|\'(?:\\\\.|[^\'\\\\\n])*\'|\x60(?:\\\\.|[^\x60\\\\])*\x60';

        return (string) preg_replace_callback(
            '~(' . $strings . ')|/\*.*?\*/|//[^\n]*~s',
            static function ($match) {
                if ($match[1] !== null) {
                    return $match[0];
                }
                return str_repeat("\n", substr_count($match[0], "\n"));
            },
            $source,
            flags: PREG_UNMATCHED_AS_NULL
        );
    }

    /**
     * Deprecated Bootstrap 4 class names used in the given lines.
     *
     * @param string $name File name to report the offenders under.
     * @param array $lines Line number => line, as markup_lines() returns them.
     * @return array One message per token found.
     */
    private function deprecated_class_offenders(string $name, array $lines): array {
        $offenders = [];
        foreach ($lines as $number => $line) {
            foreach ($this->deprecated_class_names() as $token => $replacement) {
                /* Matched as a whole class token: mr-2 must not be found inside data-mr-2x,
                   and text-left must not be found inside a longer hyphenated name. */
                if (preg_match('/(?<![-\w])' . preg_quote($token, '/') . '(?![-\w])/', $line)) {
                    $offenders[] = $name . ':' . $number . ' uses ' . $token . ', write ' . $replacement;
                }
            }
        }

        return $offenders;
    }

    /**
     * Badge backgrounds in the given lines that lack the text colour badge_text_colours() pairs them with.
     *
     * Only the utility paired with that background satisfies it: text-white on bg-warning is
     * exactly the 1.95:1 case the rule exists for. The utility has to be in the same element's
     * class list, which is the opening tag holding the background in markup, or failing that the
     * quoted string holding it - a class list in a PHP or JavaScript string literal. A utility on
     * a sibling or on a wrapper does not count: .badge sets its own color, so a wrapper's never
     * reaches it. A background found in neither is checked against its whole line.
     *
     * @param string $name File name to report the offenders under.
     * @param array $lines Line number => line, as markup_lines() returns them.
     * @return array One message per background missing its paired text colour.
     */
    private function badge_offenders(string $name, array $lines): array {
        $offenders = [];
        foreach ($lines as $number => $line) {
            foreach ($this->badge_text_colours() as $background => $required) {
                $hasbackground = '/(?<![-\w])' . preg_quote($background, '/') . '(?![-\w])/';
                if (!preg_match($hasbackground, $line)) {
                    continue;
                }
                $scopes = [];
                foreach (['/<[a-z][^<>]*>/i', '/"[^"]*"|\'[^\']*\'|\x60[^\x60]*\x60/'] as $element) {
                    preg_match_all($element, $line, $matches);
                    $scopes = preg_grep($hasbackground, $matches[0]);
                    if ($scopes) {
                        break;
                    }
                }
                foreach ($scopes ?: [$line] as $scope) {
                    if (!preg_match('/(?<![-\w])' . preg_quote($required, '/') . '(?![-\w])/', $scope)) {
                        $offenders[] = $name . ':' . $number . ' ' . $background . ' needs ' . $required;
                    }
                }
            }
        }

        return $offenders;
    }

    /**
     * No shipped markup may use a class name that only 5.x's deprecated compatibility layer defines.
     *
     * @return void
     */
    public function test_no_bootstrap4_only_class_names(): void {
        $offenders = [];
        foreach ($this->markup_files() as $path) {
            $lines = $this->markup_lines((string) file_get_contents($path), pathinfo($path, PATHINFO_EXTENSION));
            $offenders = array_merge($offenders, $this->deprecated_class_offenders(basename($path), $lines));
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
     * Every background utility must state the text colour it is paired with, on the same element.
     *
     * Checked on every line carrying a background utility, not only on lines that also say
     * "badge": a match arm returning a bare 'bg-success' seldom has that word on its own line.
     * {@see badge_offenders()} says what counts as the same element.
     *
     * @return void
     */
    public function test_every_badge_background_declares_a_text_colour(): void {
        $offenders = [];
        foreach ($this->markup_files() as $path) {
            $lines = $this->markup_lines((string) file_get_contents($path), pathinfo($path, PATHINFO_EXTENSION));
            $offenders = array_merge($offenders, $this->badge_offenders(basename($path), $lines));
        }
        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            'Bootstrap 4 gives .badge no text colour and Bootstrap 5 defaults it to white, so a '
                . 'background that does not carry the text colour paired with it in '
                . 'badge_text_colours() fails the 4.5:1 contrast floor on one branch or the other: '
                . implode('; ', $offenders)
        );
    }

    /**
     * A comment is skipped on every line it spans, and the markup around it is still read.
     *
     * Each fixture names a deprecated class inside comments, including on a block comment's
     * continuation line with no leading "*", and in markup. Only the markup may be reported, and
     * it must be: that half is the control proving the scanner still reads what is not a
     * comment, including a line a comment shares with markup and a string holding a comment
     * marker.
     *
     * @return void
     */
    public function test_a_comment_is_skipped_on_every_line_it_spans(): void {
        $php = implode("\n", [
            '<?php',
            '/* The label is hidden with visually-hidden,',
            '   never sr-only, which only the compatibility layer defines. */',
            '$a = 1; // Nor sr-only here.',
            '/**',
            ' * A docblock naming sr-only.',
            ' */',
            'echo html_writer::span(\'x\', \'sr-only\');',
            '$glob = \'backup/*.xml\';',
            'echo html_writer::span(\'x\', \'no-gutters\'); /* Closing comment. */',
        ]);
        $this->assertSame(
            [
                'fixture.php:8 uses sr-only, write visually-hidden',
                'fixture.php:10 uses no-gutters, write g-0',
            ],
            $this->deprecated_class_offenders('fixture.php', $this->markup_lines($php, 'php'))
        );

        $mustache = implode("\n", [
            '{{!',
            '    A template docblock naming sr-only',
            '    on a line with no marker of its own.',
            '}}',
            '<span class="sr-only">{{label}}</span>',
            '{{! One line naming sr-only. }}<span class="badge-pill">{{count}}</span>',
        ]);
        $this->assertSame(
            [
                'fixture.mustache:5 uses sr-only, write visually-hidden',
                'fixture.mustache:6 uses badge-pill, write rounded-pill',
            ],
            $this->deprecated_class_offenders('fixture.mustache', $this->markup_lines($mustache, 'mustache'))
        );

        $js = implode("\n", [
            '/* The label is hidden with visually-hidden,',
            '   never sr-only. */',
            'const a = 1; // Nor sr-only here.',
            'const glob = \'backup/*.xml\';',
            'el.classList.add(\'sr-only\');',
            'link.href = \'https://example.org\'; link.className = \'no-gutters\';',
            '/* Closing comment. */',
        ]);
        $this->assertSame(
            [
                'fixture.js:5 uses sr-only, write visually-hidden',
                'fixture.js:6 uses no-gutters, write g-0',
            ],
            $this->deprecated_class_offenders('fixture.js', $this->markup_lines($js, 'js'))
        );
    }

    /**
     * A badge passes only with the text colour paired with its background, on its own element.
     *
     * The first line of each fixture is the control: a correctly paired badge is not reported,
     * so the other lines are refused for their pairing and not for being badges.
     *
     * @return void
     */
    public function test_a_badge_needs_the_text_colour_paired_with_its_background(): void {
        $mustache = implode("\n", [
            '<span class="badge bg-warning text-dark">{{label}}</span>',
            '<span class="badge bg-warning text-white">{{label}}</span>',
            '<span class="badge bg-secondary text-white">{{label}}</span>',
            '<span class="badge bg-success text-dark">{{label}}</span>',
            '<span class="text-dark"><span class="badge bg-warning">{{label}}</span></span>',
            '<span class="badge bg-success">{{label}}</span> <span class="text-white">{{other}}</span>',
        ]);
        $this->assertSame(
            [
                'fixture.mustache:2 bg-warning needs text-dark',
                'fixture.mustache:3 bg-secondary needs text-dark',
                'fixture.mustache:4 bg-success needs text-white',
                'fixture.mustache:5 bg-warning needs text-dark',
                'fixture.mustache:6 bg-success needs text-white',
            ],
            $this->badge_offenders('fixture.mustache', $this->markup_lines($mustache, 'mustache'))
        );

        $php = implode("\n", [
            '<?php',
            'echo html_writer::span($text, \'badge bg-danger text-white me-1\');',
            'echo html_writer::span($text, \'badge bg-danger me-1\') . html_writer::span(\'\', \'text-white\');',
            'return match ($state) {',
            '    \'open\' => \'bg-dark text-white\',',
            '    \'waiting\' => \'bg-warning\',',
            '};',
        ]);
        $this->assertSame(
            [
                'fixture.php:3 bg-danger needs text-white',
                'fixture.php:6 bg-warning needs text-dark',
            ],
            $this->badge_offenders('fixture.php', $this->markup_lines($php, 'php'))
        );
    }

    /**
     * The stylesheet must read the theme's colour tokens rather than hardcoding a palette.
     *
     * A hardcoded colour means every site with its own institutional palette sees the plugin's
     * instead, and a light literal paints a light slab wherever the --bs-* tokens are switched to
     * dark by [data-bs-theme="dark"] (set by some themes, and by Moodle 5.3's colour mode). A
     * literal is allowed only as the final fallback of a var() chain, which is why the check
     * ignores anything inside var(...).
     *
     * @return void
     */
    public function test_stylesheet_declares_no_hardcoded_brand_colour(): void {
        $named = ['grey', 'gray', 'black', 'white', 'red', 'green', 'blue', 'silver', 'orange', 'yellow'];
        $offenders = [];
        foreach ($this->stylesheets() as $sheet) {
            $lines = explode("\n", $this->strip_css_comments((string) file_get_contents($sheet)));
            foreach ($lines as $number => $line) {
                // Only a real declaration is judged, with its var() calls removed first.
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
     * The badge rule above, applied to the stylesheet. Bootstrap's [data-bs-theme="dark"]
     * redefines the --bs-* tokens under the element carrying it but sets no color, so inside that
     * scope a fill read from a token turns dark while an inherited text colour keeps the value
     * computed outside it, and the text lands on a fill of its own lightness. Declaring both in
     * the same block keeps them from the same scope. No gate renders a page to notice this.
     *
     * A block painting no text is exempted by selector, each with its reason, rather than by a
     * heuristic, so the exemption is a decision and not a way to silence the test.
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
            'These rules paint a background and leave the text colour to inheritance. '
                . '[data-bs-theme="dark"] redefines the --bs-* tokens but not color, so a fill read '
                . 'from a token turns dark while the inherited text keeps the colour computed outside '
                . 'that scope, and lands on a fill of its own lightness. Declare both, or name the '
                . 'selector in this test as painting no text: ' . implode('; ', $offenders)
        );
    }

    /**
     * The plugin must not declare custom properties inside core's design-system namespace.
     *
     * Moodle 5.2 ships $mds-* tokens in theme/boost/scss/design-system/, copied from core's
     * design-system npm package, which later releases keep extending, so an --mds-*
     * declaration squats a namespace core is expanding.
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
     * The queue's table class must name the column that identifies a row.
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
     * intact, and prose about colour then reads as a declaration of one.
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
