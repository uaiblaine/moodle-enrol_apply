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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Holds the rules this plugin claims for every door to every door the tree has.
 *
 * A rule claimed for "every" page or service was held on this fleet by a list of gates, one per
 * member somebody named, and the member nobody named was the defect (moodle-dev's
 * docs/security-defect-shapes.md, K6). Every member here is found by reading the tree, never from a
 * list: a new page, service or caller is checked the day it is added, and fails until somebody
 * decides what holds it. Reads files only, so it runs in GitHub's CI and in mdl ci alike; comments
 * are stripped with PHP's own tokenizer first, so a comment naming a call never passes for the call.
 *
 * The four rules, and the change that must make each fail:
 * - every entry script (a *.php at the plugin root that requires config.php) calls require_login()
 *   and after it require_capability() or the alternative this test declares for it, with its
 *   reason: deleting either call from a script, or adding a script that has neither, fails
 *   {@see test_every_entry_script_requires_login_then_a_check()};
 * - every function of db/services.php is a class under classes/external/ whose execute() calls
 *   validate_parameters(), validate_context() and refuses a guest, in that order, and no class there
 *   names a decision: dropping one of the three, or a service calling a decision method, fails
 *   {@see test_every_service_validates_its_caller()} or {@see test_no_external_class_takes_a_decision()};
 * - every file that reads a decision or writes a profile from a post (decision::posted(),
 *   decision::apply(), offer::take(), profilewriter::write()) is an entry script that requires the
 *   sesskey and a POST before it: moving either check after the effect, deleting it, or calling one
 *   of the effects from another file fails {@see test_every_posted_effect_needs_the_sesskey_and_a_post()};
 * - submission::decide(), the one writer of a decision on the durable record, is called from the
 *   declared functions and no other: a fifth caller, or one of the four gone, fails
 *   {@see test_the_durable_decision_has_its_declared_callers_only()}.
 * - every capability read (has_capability(), has_any_capability(), has_all_capabilities(),
 *   require_capability()) in a file outside the tests is a call of groupscope::holds_in_course(),
 *   or one of the bare reads this test declares, with its reason: a read of the system or a user
 *   context, a read behind require_login($course), a read whose direction a lapsed reader must not
 *   change, the helper's own. A new bare read anywhere, or one more in a declared function, fails
 *   {@see test_every_capability_read_of_a_course_goes_through_the_helper()} until somebody declares
 *   why it is not a course read off a guarded path; a declared one that is gone fails it too.
 *
 * @package    enrol_apply
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class every_door_test extends \basic_testcase {
    /** @var string The check every entry script makes after require_login(), unless it declares another. */
    private const CAPABILITY_CHECK = '~\brequire_capability\s*\(~';

    /**
     * The entry scripts that check something else than a capability after require_login(), each with
     * the check (a regular expression over the script's code) and the reason it stands in for one.
     */
    private const ALTERNATIVES = [
        'applied.php' => [
            'pattern' => '~if \(!\$ownrow\) \{\s*throw new moodle_exception\(\'invalidaccess\'~',
            'reason' => 'The acknowledgement of the applicant\'s own application: no capability names it, and the '
                . 'page refuses anybody without a row on the instance.',
        ],
        'apply.php' => [
            'pattern' => '~\$form->check_access_for_dynamic_submission\(\);~',
            'reason' => 'The application form without JavaScript: the form\'s own access check (guest, log-in-as, '
                . 'the applicant\'s own row, allow_apply()), the one core runs for the modal.',
        ],
        'profile.php' => [
            'pattern' => '~if \(!\$hasapplication\) \{\s*throw new moodle_exception\(\'invalidaccess\'~',
            'reason' => 'The applicant writes to their own profile only: nobody without an undecided application on '
                . 'the instance gets past.',
        ],
        'unenrolself.php' => [
            'pattern' => '~if \(!\$plugin->get_unenrolself_link\(\$instance\)\) \{~',
            'reason' => 'Core\'s rule for leaving a course: get_unenrolself_link() asks enrol/apply:unenrolself and '
                . 'an active enrolment.',
        ],
    ];

    /** @var array What a web service must never name: there is no decision web service (CQ-D4). */
    private const DECISIONS = [
        'confirm_enrolment',
        'wait_enrolment',
        'cancel_enrolment',
        'decision::apply',
        'decision::posted',
        'submission::decide',
    ];

    /** @var array Calls that act on what was posted, which only a POST carrying the sesskey may reach. */
    private const POSTED_EFFECTS = [
        'decision::posted(',
        'decision::apply(',
        'offer::take(',
        'profilewriter::write(',
    ];

    /**
     * The functions that may write a decision to the durable record, as "file::function", with the reason.
     */
    private const DECIDE_CALLERS = [
        'lib.php::complete_approval' => 'An approval, from confirm_enrolment() or from core\'s Edit enrolment '
            . 'through the observer.',
        'lib.php::wait_enrolment' => 'A deferral, from the queue, the review page or the bulk operation.',
        'lib.php::cancel_enrolment' => 'A cancellation, from the same three.',
        'lib.php::admit' => 'A group key\'s admission: the applicant presenting a valid key is the recorded '
            . 'decider (G11).',
    ];

    /** @var array The capability readers the bare-read rule is about. */
    private const CAPABILITY_READERS = [
        'has_capability',
        'has_any_capability',
        'has_all_capabilities',
        'require_capability',
    ];

    /**
     * The bare capability reads, as "file::function" (the top level of a script as "(script)") => how
     * many there are and why each is not a course read off a guarded path. Every other read is a call
     * of groupscope::holds_in_course(), which counts a course-level grant only while the reader's own
     * enrolment in the course has not lapsed.
     */
    private const BARE_READS = [
        'report.php::(script)' => [1, 'require_capability() after require_login($course).'],
        'fields.php::(script)' => [1, 'require_capability() after require_login($course).'],
        'condition.php::(script)' => [1, 'require_capability() after require_login($course).'],
        'edit.php::(script)' => [2, 'require_capability() after require_login($course).'],
        'manage.php::(script)' => [1, 'require_capability() in the course after require_login($course), or in the system context.'],
        'classes/external/report_rows.php::execute' => [1, 'require_capability() after validate_context() on the course.'],
        'classes/external/report_summary.php::execute' => [1, 'require_capability() after validate_context() on the course.'],
        'classes/external/set_report_preference.php::execute' => [
            1,
            'require_capability() after validate_context() on the course.',
        ],
        'classes/local/queue.php::require_review_access' => [1, 'The refusal is raised in the applicant\'s user context.'],
        'classes/local/queue.php::scope' => [2, 'The system context.'],
        'classes/local/queue.php::listing_scope' => [2, 'The system context.'],
        'lib.php::can_manage_application' => [1, 'The applicant\'s user context: the mentor level.'],
        'classes/local/applications.php::get_mentees' => [1, 'The mentee\'s user context: the mentor level.'],
        'classes/local/profilelink.php::viewable' => [1, 'The applicant\'s user context: core\'s "parent" branch.'],
        'classes/local/profilelink.php::viewable_ids' => [1, 'The applicant\'s user context: core\'s "parent" branch.'],
        'classes/local/profilewriter.php::write' => [1, 'The system context.'],
        'classes/local/reportengine.php::retention' => [1, 'The system context.'],
        'classes/table/applications.php::offers_grouped_filter' => [1, 'The system context.'],
        'classes/reportbuilder/local/formatters/submission.php::decided' => [1, 'The system context.'],
        'classes/reportbuilder/local/formatters/submission.php::visible_keys' => [1, 'The system context.'],
        'classes/reportbuilder/datasource/applications.php::restrict_submitted_data' => [
            1,
            'A custom report\'s context, which is the system context.',
        ],
        'classes/local/groupscope.php::system_exempt' => [1, 'The system context: the helper\'s own exemption.'],
        'classes/local/groupscope.php::holds_in_course' => [1, 'The helper\'s own definition.'],
        'lib.php::allow_unenrol_user' => [
            1,
            'Chooses whom to narrow: a holder is narrowed to their groups, so the helper would exempt a lapsed holder.',
        ],
        'classes/hook_callbacks.php::refuses_approval' => [
            1,
            'Chooses whom to judge: a holder is judged, so the helper would let a lapsed holder pass unjudged.',
        ],
    ];

    /**
     * Each call of one of these functions in a file's code, keyed "file::enclosing function".
     *
     * Read from PHP's own tokens, comments being tokens of their own: a name followed by "(" and not
     * preceded by "->", "?->", "::", "function" or "new" is a call of the global function. The
     * enclosing function is tracked by brace depth, so a closure counts towards the function it sits
     * in and code outside every function towards "(script)".
     *
     * @param string $relative Path relative to the plugin root.
     * @param array $names The functions looked for.
     * @return array "file::function" => how many calls.
     */
    private static function calls_by_function(string $relative, array $names): array {
        $tokens = array_values(array_filter(
            token_get_all(file_get_contents(dirname(__DIR__) . '/' . $relative)),
            static fn($token): bool => !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ));
        $calls = [];
        $stack = [];
        $pending = null;
        $depth = 0;
        foreach ($tokens as $i => $token) {
            $text = is_array($token) ? $token[1] : $token;
            $type = is_array($token) ? $token[0] : null;
            if ($type === T_FUNCTION) {
                $next = $tokens[$i + 1] ?? null;
                if (is_array($next) && $next[0] === T_STRING) {
                    $pending = $next[1];
                } else if ($next === '&' && is_array($tokens[$i + 2] ?? null) && $tokens[$i + 2][0] === T_STRING) {
                    $pending = $tokens[$i + 2][1];
                }
                continue;
            }
            if ($text === ';') {
                // An abstract or interface method ends its declaration with no body.
                $pending = null;
                continue;
            }
            if ($text === '{' || $type === T_CURLY_OPEN || $type === T_DOLLAR_OPEN_CURLY_BRACES) {
                if ($text === '{' && $pending !== null) {
                    $stack[] = [$pending, $depth];
                    $pending = null;
                }
                $depth++;
                continue;
            }
            if ($text === '}') {
                $depth--;
                if ($stack && end($stack)[1] === $depth) {
                    array_pop($stack);
                }
                continue;
            }
            $name = in_array($type, [T_STRING, T_NAME_FULLY_QUALIFIED], true) ? ltrim($text, '\\') : null;
            if ($name === null || !in_array($name, $names, true) || ($tokens[$i + 1] ?? null) !== '(') {
                continue;
            }
            $before = $tokens[$i - 1] ?? null;
            $notacall = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW];
            if (is_array($before) && in_array($before[0], $notacall, true)) {
                continue;
            }
            $key = $relative . '::' . ($stack ? end($stack)[0] : '(script)');
            $calls[$key] = ($calls[$key] ?? 0) + 1;
        }

        return $calls;
    }

    /**
     * A file's code with its comments removed, by PHP's own tokenizer.
     *
     * @param string $relative Path relative to the plugin root.
     * @return string The code.
     */
    private static function code(string $relative): string {
        $code = '';
        foreach (token_get_all(file_get_contents(dirname(__DIR__) . '/' . $relative)) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }

    /**
     * Every PHP file of the plugin outside its tests, relative to the plugin root.
     *
     * @return array Relative paths, sorted.
     */
    private static function production_files(): array {
        $root = dirname(__DIR__);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                static fn(\SplFileInfo $file): bool => !$file->isDir()
                    || !in_array($file->getFilename(), ['tests', 'amd', 'js', 'node_modules', '.git'], true)
            )
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Whether a file at the plugin root is an entry script: it requires config.php.
     *
     * @param string $relative Path relative to the plugin root.
     * @return bool True for an entry script.
     */
    private static function is_entry_script(string $relative): bool {
        return strpos($relative, '/') === false
            && (bool) preg_match('~\brequire(_once)?\s*\(?[^;]*config\.php~', self::code($relative));
    }

    /**
     * Every entry script of the plugin.
     *
     * @return array The script, keyed by its name.
     */
    public static function entry_scripts(): array {
        $cases = [];
        foreach (self::production_files() as $relative) {
            if (self::is_entry_script($relative)) {
                $cases[$relative] = [$relative];
            }
        }

        return $cases;
    }

    /**
     * Each entry script calls require_login(), and after it a capability check or its declared alternative.
     *
     * @param string $relative The script.
     * @return void
     */
    #[DataProvider('entry_scripts')]
    public function test_every_entry_script_requires_login_then_a_check(string $relative): void {
        $code = self::code($relative);
        $login = preg_match('~\brequire_login\s*\(~', $code, $match, PREG_OFFSET_CAPTURE) ? $match[0][1] : null;
        $this->assertNotNull($login, "{$relative} never calls require_login().");

        $pattern = self::ALTERNATIVES[$relative]['pattern'] ?? self::CAPABILITY_CHECK;
        $this->assertSame(
            1,
            preg_match($pattern, $code, $match, PREG_OFFSET_CAPTURE, $login),
            "{$relative} checks nothing after require_login(): call require_capability(), or declare the "
                . 'alternative it uses in ALTERNATIVES with its reason.'
        );
    }

    /**
     * The entry scripts are found at all, and every declared alternative still names one, so a renamed
     * script cannot keep a stale pass.
     *
     * @return void
     */
    public function test_every_alternative_names_an_entry_script(): void {
        $scripts = array_keys(self::entry_scripts());
        $this->assertContains('manage.php', $scripts, 'the enumeration finds the entry scripts');

        foreach (array_keys(self::ALTERNATIVES) as $relative) {
            $this->assertContains($relative, $scripts, "ALTERNATIVES names {$relative}, which is no entry script.");
        }
    }

    /**
     * Every function db/services.php declares.
     *
     * @return array Function name, class name and method name, keyed by function name.
     */
    public static function services(): array {
        $functions = [];
        include(dirname(__DIR__) . '/db/services.php');

        $cases = [];
        foreach ($functions as $name => $function) {
            $cases[$name] = [$name, (string) $function['classname'], (string) ($function['methodname'] ?? 'execute')];
        }

        return $cases;
    }

    /**
     * The body of a method in a file's code, up to the next method or the end.
     *
     * @param string $code The code.
     * @param string $method The method's name.
     * @return string|null The body, or null when the method is not there.
     */
    private static function method_body(string $code, string $method): ?string {
        if (!preg_match('~function\s+' . preg_quote($method, '~') . '\s*\(~', $code, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $start = $match[0][1];
        $next = preg_match('~function\s+[a-z0-9_]+\s*\(~i', $code, $after, PREG_OFFSET_CAPTURE, $start + 1)
            ? $after[0][1]
            : strlen($code);

        return substr($code, $start, $next - $start);
    }

    /**
     * Each service is a class under classes/external/ whose execute() validates its parameters, then its
     * context, then refuses a guest.
     *
     * @param string $name The function's name.
     * @param string $classname Its class.
     * @param string $methodname Its method.
     * @return void
     */
    #[DataProvider('services')]
    public function test_every_service_validates_its_caller(string $name, string $classname, string $methodname): void {
        $this->assertSame('execute', $methodname, $name);
        $this->assertMatchesRegularExpression('~^enrol_apply\\\\external\\\\[a-z_]+$~', $classname, $name);
        $relative = 'classes/external/' . substr($classname, strlen('enrol_apply\\external\\')) . '.php';
        $this->assertFileExists(dirname(__DIR__) . '/' . $relative, $name);

        $body = self::method_body(self::code($relative), 'execute');
        $this->assertNotNull($body, "{$relative} has no execute().");
        $offsets = [];
        foreach (
            [
                'validate_parameters' => '~self::validate_parameters\s*\(~',
                'validate_context' => '~self::validate_context\s*\(~',
                'guest' => '~if \(isguestuser\(\)\) \{\s*throw new~',
            ] as $check => $pattern
        ) {
            $this->assertSame(1, preg_match($pattern, $body, $match, PREG_OFFSET_CAPTURE), "{$name}: no {$check} in execute().");
            $offsets[$check] = $match[0][1];
        }
        $sorted = $offsets;
        asort($sorted);
        $this->assertSame(array_keys($offsets), array_keys($sorted), "{$name}: the parameters, the context, then the guest.");
    }

    /**
     * No class under classes/external/, listed as a service or not, names a decision: decisions are the
     * POST to manage.php, and there must never be a decision web service.
     *
     * @return void
     */
    public function test_no_external_class_takes_a_decision(): void {
        $files = array_filter(
            self::production_files(),
            static fn(string $file): bool => str_starts_with($file, 'classes/external/')
        );
        $this->assertNotEmpty($files, 'the enumeration finds the external classes');

        foreach ($files as $relative) {
            $code = self::code($relative);
            foreach (self::DECISIONS as $decision) {
                $this->assertStringNotContainsString($decision, $code, "{$relative} names {$decision}.");
            }
        }
    }

    /**
     * Every file that acts on a posted decision or profile offer is an entry script requiring the
     * sesskey and a POST (data_submitted()) before the first such call.
     *
     * @return void
     */
    public function test_every_posted_effect_needs_the_sesskey_and_a_post(): void {
        $found = [];
        foreach (self::production_files() as $relative) {
            $code = self::code($relative);
            $effects = array_filter(
                array_map(static fn(string $effect) => strpos($code, $effect), self::POSTED_EFFECTS),
                static fn($offset): bool => $offset !== false
            );
            if (!$effects) {
                continue;
            }
            $found[] = $relative;
            $first = min($effects);

            $this->assertTrue(self::is_entry_script($relative), "{$relative} acts on a post but is no entry script.");
            foreach (['require_sesskey(', 'data_submitted('] as $check) {
                $offset = strpos($code, $check);
                $this->assertNotFalse($offset, "{$relative} never calls {$check}).");
                $this->assertLessThan($first, $offset, "{$relative} calls {$check}) only after acting on the post.");
            }
        }

        // The control: the scan finds the two pages that act on a post.
        $this->assertSame(['manage.php', 'profile.php'], $found);
    }

    /**
     * submission::decide() is called from the declared functions and from no other.
     *
     * @return void
     */
    public function test_the_durable_decision_has_its_declared_callers_only(): void {
        $callers = [];
        foreach (self::production_files() as $relative) {
            $code = self::code($relative);
            if (!preg_match_all('~submission::decide\s*\(~', $code, $calls, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            preg_match_all('~function\s+([a-z0-9_]+)\s*\(~i', $code, $functions, PREG_OFFSET_CAPTURE);
            foreach ($calls[0] as [, $offset]) {
                $enclosing = '';
                foreach ($functions[1] as [$name, $at]) {
                    if ($at < $offset) {
                        $enclosing = $name;
                    }
                }
                $callers[] = "{$relative}::{$enclosing}";
            }
        }
        sort($callers);

        $declared = array_keys(self::DECIDE_CALLERS);
        sort($declared);
        $this->assertSame($declared, $callers, 'A new writer of a decision is declared in DECIDE_CALLERS with its reason.');
    }

    /**
     * Every capability read in the plugin's code goes through groupscope::holds_in_course(), but for
     * the bare reads BARE_READS declares, each with its reason.
     *
     * A role assignment survives the suspension or the end of the enrolment that gave it, so a bare
     * read of a course capability answers yes through a course role a lapsed reader can no longer use,
     * on any path that does not pass require_login($course); the helper does not, and answers as
     * has_capability() everywhere else.
     *
     * @return void
     */
    public function test_every_capability_read_of_a_course_goes_through_the_helper(): void {
        $bare = [];
        foreach (self::production_files() as $relative) {
            $bare += self::calls_by_function($relative, self::CAPABILITY_READERS);
        }
        ksort($bare);
        $declared = array_map(static fn(array $entry): int => $entry[0], self::BARE_READS);
        ksort($declared);

        $this->assertArrayHasKey('report.php::(script)', $bare, 'the control: the scan finds a script\'s read');
        $this->assertArrayHasKey('lib.php::can_manage_application', $bare, 'the control: and a method\'s');
        $this->assertSame(
            $declared,
            $bare,
            'A bare capability read: call groupscope::holds_in_course(), or declare it in BARE_READS with its reason.'
        );
    }
}
