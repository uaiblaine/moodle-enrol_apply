# enrol_apply — implementation plan: profile fields, audit trail and reporting

Execution plan for the design approved in
[`docs/design/profile-fields-and-audit.html`](profile-fields-and-audit.html). The decision log in
[`docs/design/README.md`](README.md) is authoritative on *why*; this document is only about *what to do*
and *what will bite*.

Eleven slices: **1–9**, then **I** and **J**. Each slice stands alone, ships with green CI, and is
independently revertable. Slice 10 (the `enrol_apply_submission_field` child table, its per-field
columns and its filters) is explicitly deferred by decision and is not planned here — slice 6 lays
its ground and slice 7 says exactly which report behaviour waits for it.

Every file path below is repo-relative to `/Users/uaiblaine/dev/moodle-enrol_apply`. Core citations
are relative to `/Users/uaiblaine/dev/moodle-502/public` and carry the 5.1 line beside them whenever
the two branches differ.

---

## Prerequisites

**Environment.** The plugin is bind-mounted from this repo into the local stacks through
`~/dev/moodle-dev/plugins.conf` at `enrol/apply` (`/var/www/html/public/enrol/apply` on 5.1+).
`$plugin->supported = [501, 502]`, so it mounts on **m501** and **m502** only. One edit is live on
both at once; no copying, no rsync.

| Stack | Moodle | Web | DB port |
|---|---|---|---|
| `m501` | 5.1 (`MOODLE_501_STABLE`) | http://localhost:8501 | 5501 |
| `m502` | 5.2 (`MOODLE_502_STABLE`) | http://localhost:8502 | 5502 |

Site login on both: **admin / moodle**. Mailpit at `<weburl>/_/mail`. A cron sidecar runs Moodle cron
every 60 s on both stacks, so scheduled and ad-hoc tasks fire without intervention.

**What is already in the tree.** Read this before calling anything "new":

```
classes/local/applications.php          classes/hook_callbacks.php
classes/privacy/provider.php            classes/task/{notify_approval,send_expiry_notifications,sync_enrolments}.php
db/{access,hooks,install.xml,messages,tasks,upgrade}.php
amd/src/manage.js  amd/build/manage.min.js  amd/build/manage.min.js.map
templates/{application_notification,info,manage}.mustache
tests/{backup_test.php,lib_test.php}  tests/local/applications_test.php  tests/privacy/provider_test.php
tests/behat/{behat_enrol_apply.php,enrol_apply.feature}
```

`db/messages.php`, `db/tasks.php`, `db/access.php`, `amd/src/manage.js` and `templates/manage.mustache`
all **exist** — slices below *extend* them.

**The commands used throughout.** `mdl up`, `mdl upgrade`, `mdl purge`, `mdl phpunit-init` and
`mdl behat-init` each take **one** stack; run them twice, not `mdl up m501 m502`.

```sh
mdl up m501; mdl up m502                  # start the stacks (one stack per call)
mdl upgrade m502                          # install the version bump / new db files
mdl purge m502                            # after any template, renderer or CSS change
mdl phpunit m502 enrol_apply              # the plugin's whole testsuite
mdl phpunit m502 public/enrol/apply/tests/local/fields_test.php   # one file: path from the Moodle ROOT (5.1+ = public/…)
mdl behat m502 @enrol_apply               # the Behat smoke tests
mdl grunt m502 enrol/apply                # rebuild amd/build (commit with amd/src)
mdl ci moodle-enrol_apply --only phpcs,phpdoc              # quick static pass
mdl ci moodle-enrol_apply                 # ONE leg: 5.01 / PHP 8.3 / pgsql
mdl ci moodle-enrol_apply --matrix        # every leg GitHub runs — the real gate
mdl ci moodle-enrol_apply --matrix --behat
mdl backup m502 --label applyplan          # labelled baseline before seeding test data
mdl restore m502 applyplan
```

If the file-path form of `mdl phpunit` is refused, fall back to the component form
(`mdl phpunit m502 enrol_apply`) plus PHPUnit's `--filter`.

**A `version.php` bump stales both test sites.** Every slice here bumps `version.php` (new `db/*`
files, new `amd/build`, a new capability, a new datasource, a schema change — all of them require
it). After the bump, **re-init both**:

```sh
mdl upgrade m502 && mdl phpunit-init m502 && mdl behat-init m502
mdl upgrade m501 && mdl phpunit-init m501 && mdl behat-init m501
```

A stale PHPUnit site errors loudly. A stale **Behat site fails deceptively**: `run.php` prints
"Your behat test site is outdated" and **exits 0 with zero scenarios run**. `mdl behat` detects the
advisory and exits 1, but never judge a raw run by its exit status — judge it by the scenario count.

**Baseline.** Commit `97818a7`. Read `CLAUDE.md` in this repo and `~/dev/CLAUDE.md` before the first
line of code; both are strict and both veto things this plan takes for granted.

---

## Ground rules

These bite on almost every slice.

1. **Version bump + `CHANGELOG.md` entry land in the same commit** as the change that needs them.
   The `upgrade_plugin_savepoint()` number must equal `$plugin->version` **exactly**.
   `db/install.xml`'s `VERSION` attribute (line 2) is a **`YYYYMMDD` date stamp, not the plugin
   version** — it goes up alongside them whenever the schema changes, but it is a different kind of
   number. Today the repo carries `VERSION="20260810"` against `$plugin->version = 2026081101`, and
   core does the same (`mod/quiz/db/install.xml`: `VERSION="20251126"`). Do not try to make the two
   identical.
2. **`lang/en/enrol_apply.php` and `lang/pt_br/enrol_apply.php` stay in lockstep** — identical key
   sets, updated in the same commit, **alphabetically sorted by key, no section comments**. CI's
   `validate` step reads the ordering; half a removal fails the build. Removing a string means
   removing it from both files.
3. **No blank line after a class opening brace** (`PSR12.Classes.OpeningBraceSpace`). Already
   reintroduced three times in this repo. Before every push:
   `rg -U -n 'class [^\n]*\{\n\n' --glob '*.php' .` — expect no matches.
4. **`mdl ci moodle-enrol_apply --matrix` before pushing.** The bare `mdl ci` runs ONE leg (5.01 /
   PHP 8.3 / pgsql). It will not catch the 5.1↔5.2 Report Builder divergences, the MariaDB legs, or
   PHP 8.2. On slices 7 and 8 the matrix is the only thing that catches the `initialise()` difference.
5. **Cite core by symbol, not by line alone.** Where a line is given below it is the **5.2** line, and
   the 5.1 line is given beside it whenever they differ. They differ more often than not: the same
   function sits at `settings_navigation.php:599` on 5.2 and `:640` on 5.1;
   `enrol_plugin::get_bulk_operations()` at `enrollib.php:2985` / `:2993`;
   `useredit_get_enabled_name_fields()` at `user/editlib.php:444` / `:450`;
   `profile_get_user_fields_with_data()` at `user/profile/lib.php:640` / `:650`;
   `profile_user_record()` at `:812` / `:822`; `profile_field_base::edit_field()` at
   `user/profile/lib.php:157` / `:167`. **Before trusting any line number in this document, open it
   on the branch you are working against.**
6. **Never commit or push without being asked.** If work starts on `master`, branch first.
7. **Zero-warning policy.** `phpcs --max-warnings 0`, `phpdoc --max-warnings 0`,
   `grunt --max-lint-warnings 0`, PHPUnit `--fail-on-warning`. There is no "just a warning" tier.
8. **New test files use PHP attributes** (`#[CoversClass(...)]`, `#[DataProvider(...)]`) — the fleet's
   `@covers`-docblock exception applies only while `$plugin->supported` includes 405, and it does not.
   The four existing test files use the class-level docblock form and CI is green with them; do not
   convert them as a side effect of feature work.
9. **English everywhere** — code, comments, docblocks, commit messages, CHANGELOG, README. Brazilian
   Portuguese appears only inside `lang/pt_br/`.
10. **Never type to-do markers, test-me annotations or merge-conflict marker lines** anywhere,
    including in comments and docs. CI's development-leftover checker scans every file in the repo.
11. **Any user-supplied profile value entering any sink goes through
    `format_string($value, true, ['escape' => false])` first.** `profile_field_textarea` declares
    `PARAM_RAW` (core's own comment: "We MUST clean this before display!"), so a value holding a bare
    `<` followed by a non-space (`<3`, `A<B`) kills `clean_returnvalue()` on a web service hop and
    renders raw in a triple stash. This applies to the notification body (slice 3), the report's
    snapshot formatter (slice 7) and the datasource (slice 8).

---

## Slice 1 — Cohort restriction + application window

**Goal.** A teacher can restrict an enrolment method to members of one cohort and set the window
during which applications are accepted, separately from the enrolment period.

**Depends on.** Nothing. First in, because it touches `allow_apply()` — the method every later slice
routes through — while that method is still nine lines long (`lib.php:89-98`).

### Files

| File | Change |
|---|---|
| `lib.php` | `allow_apply()`: add the `enrolstartdate`/`enrolenddate` window checks and the `customint5` cohort check, in that order, after the existing `customint6` check. `get_instance_defaults()` (`lib.php:441`): add `$fields['customint5'] = 0;`. `restore_instance()` (`lib.php:914`): set `$data->customint5 = -1` when `!$step->get_task()->is_samesite()`, in the block that already degrades `customtext3` (`lib.php:918-920`). |
| `edit_form.php` | New `customint5` select built from `cohort_get_available_cohorts($context, 0, 0, 0)`; the hidden+`setConstant(0)` fallback when the list is empty; `enrolstartdate` and `enrolenddate` `date_time_selector` elements with `['optional' => true]`; a new `public function validation($data, $files)` that starts with `$errors = parent::validation($data, $files);`. All new elements go **before** the `set_data()` call at `edit_form.php:145`. |
| `edit.php` | **Unchanged.** `enrolstartdate`, `enrolenddate` and `customint5` are all already in `enrol_plugin::update_instance()`'s property list (`lib/enrollib.php:2643-2648`) and are copied unconditionally by `add_instance()` (`:2618-2625`). Confirm this rather than assuming it. |
| `lang/en/enrol_apply.php` | New keys in alphabetic slots: `canntenrolearly`, `canntenrollate`, `cohortnonmemberinfo`, `cohortonly`, `cohortonly_help`, `cohortunresolved`, `enrolenddate`, `enrolenddate_help`, `enrolenddaterror`, `enrolstartdate`, `enrolstartdate_help`. |
| `lang/pt_br/enrol_apply.php` | The same keys, same slots. |
| `version.php` | Bump `$plugin->version`. |
| `CHANGELOG.md` | Under `## Unreleased` → `### Added`. Mention `enrol_gapply` as the source of the application-window idea. |
| `tests/lib_test.php` | New tests (below). |

### Design references

§07 "Cohort", §10 "The application window costs no column at all", slice 1 in §09.

### Traps

- **Decide what `-1` means at read time, because the design only defines it at write time.**
  `-1` is written by a cross-site restore and means "there **was** a restriction and this site cannot
  honour it". `allow_apply()` must therefore treat `-1` as a **live refusal**
  (`get_string('cohortunresolved', 'enrol_apply')`), never as `0`. Reading it as "no restriction"
  fails open and defeats the whole sentinel.
- **Four arguments to `cohort_get_available_cohorts()`, not three.** `$limit` defaults to **25**
  (`cohort/lib.php:260`, same on both branches); without the fourth argument the picker silently
  truncates on a site with many cohorts. Call it as `cohort_get_available_cohorts($context, 0, 0, 0)`.
- **When no cohort is available, emit a hidden element with `setConstant(0)` — do not omit it.**
  `enrol_plugin::update_instance()` copies a property only `if (isset($data->$key))`
  (`lib/enrollib.php:2650-2653`). Omitting the element makes an existing restriction **impossible to
  remove**.
- **Do not copy `enrol_self`'s validation, because there isn't any.** `enrol_self` lists
  `'customint5' => PARAM_INT` in `$tovalidate` and nothing more (`enrol/self/lib.php:1123`) — no
  existence, visibility or membership check. Copied verbatim that is a hidden-cohort name and
  membership oracle for anyone holding `enrol/apply:config`. Copy `enrol_cohort`'s guard instead:
  `array_diff` the submitted value against the option keys in `validation()` →
  `get_string('invaliddata', 'error')`. Rebuild the option keys **inside** `validation()` from
  `cohort_get_available_cohorts()` against the course context; do not trust a property stashed at
  `definition()` time.
- **Do not copy `enrol_self`'s "keep the stored value selectable" fallback either.** It re-reads the
  cohort with a raw `get_record()`, bypassing the parent-context and visibility rules
  `cohort_get_cohort()` applies, and hands the real name to the form. `customint5` also arrives by
  restore through `add_instance()`'s allowlist-free copy (`lib/enrollib.php:2618-2625`), so the stored
  value is not trusted input. Put it through `cohort_get_cohort($id, $context)`; when that refuses,
  keep the option selectable labelled `get_string('unknowncohort', 'cohort', $id)` — never with the
  raw name.
- **A deleted cohort must produce a real string, not null.** `enrol_self` returns `null` there and the
  method silently vanishes from the page. This plugin's `enrol_page_hook()` passes the return of
  `allow_apply()` straight to `$OUTPUT->notification()` (`lib.php:141-144`) — a null paints an empty
  red box.
- **The check belongs in `allow_apply()`, not in `enrol_page_hook()`.** That is the method every
  present and future caller goes through, and the one `test_allow_apply_respects_instance_state`
  (`tests/lib_test.php:531`) already exercises. `enrol_gapply` puts its window check in the hook,
  which is exactly the mistake not to inherit.
- **`enrolstartdate`/`enrolenddate` already exist on `{enrol}`, are already in core's backup**
  (`backup/moodle2/backup_stepslib.php:689-690`) and already have core strings. No XMLDB change, no
  new column. The "end before start" validation is copyable from `enrol/self/lib.php:1090-1094`.
- **`enrol_plugin::get_config()` memoises `$this->config`.** A test calling `set_config(...)` after
  building the plugin object exercises the default branch and passes vacuously. Use
  `$plugin->set_config()`.

### Verification

1. `mdl upgrade m502 && mdl phpunit-init m502 && mdl behat-init m502`; same on m501.
2. Write in `tests/lib_test.php`:
   - `test_allow_apply_refuses_before_the_application_window_opens` — instance with
     `enrolstartdate = time() + DAYSECS`; assert `allow_apply()` returns a **non-true string**
     containing the `canntenrolearly` text. **Control:** with `enrolstartdate = 0` it returns `true`.
   - `test_allow_apply_refuses_after_the_application_window_closes` — mirror, `enrolenddate`.
   - `test_allow_apply_admits_only_cohort_members` — create a cohort, add user A, leave user B out,
     set `customint5` to the cohort id; assert `true` for A and a string for B. **Control:** with
     `customint5 = 0`, B gets `true` — this proves the restriction is what excluded B, not something
     else in the fixture.
   - `test_allow_apply_returns_a_string_when_the_cohort_was_deleted` — set `customint5` to an id with
     no `{cohort}` row; assert the return is a **non-empty string** (`assertNotNull`, `assertIsString`,
     `assertNotSame('', ...)`).
   - `test_allow_apply_refuses_on_the_restore_sentinel` — set `customint5 = -1`; assert a **string**,
     not `true`. **Control:** `customint5 = 0` returns `true`.
   - `test_restore_into_another_site_disables_the_cohort_restriction` — drive `restore_instance()`
     with a `restore_enrolments_structure_step` whose task reports `is_samesite() === false`; assert
     the created instance has `customint5 == -1`, not `0`. Follow
     `backup/moodle2/tests/moodle2_test.php::prepare_for_enrolments_test()` (`:577` on both branches)
     for the harness, **not** the `backup_and_restore()` helper in the same file (`:475`).
   - `test_edit_form_rejects_a_cohort_outside_the_offered_list` — call
     `enrol_apply_edit_form::validation()` with a `customint5` naming a cohort not in
     `cohort_get_available_cohorts()`; assert `$errors['customint5']` is set.
3. **Mutation check — cohort gate.** Delete the cohort membership check from `allow_apply()`.
   `test_allow_apply_admits_only_cohort_members` must go red **and nothing else**. Restore.
4. **Mutation check — the `-1` read semantics.** Make `allow_apply()` treat `-1` like `0`.
   `test_allow_apply_refuses_on_the_restore_sentinel` goes red and nothing else.
5. **Mutation check — window gates.** Delete the `enrolstartdate` branch; only
   `test_allow_apply_refuses_before_the_application_window_opens` goes red. Repeat for `enrolenddate`.
6. **Mutation check — restore sentinel.** Change `-1` to `0` in `restore_instance()`.
   `test_restore_into_another_site_disables_the_cohort_restriction` must go red.
7. **Mutation check — form validation.** Delete the `array_diff` guard from `validation()`.
   `test_edit_form_rejects_a_cohort_outside_the_offered_list` must go red.
8. Manual: on m502 create cohort "Servidores 2026" with one member. Open
   `http://localhost:8502/enrol/apply/edit.php?courseid=<id>` — the cohort select is present and the
   two date selectors render. Save with the restriction on. As a non-member, open
   `http://localhost:8502/enrol/index.php?id=<courseid>` and confirm the card shows the refusal text
   and **no Enrol button**, not an empty red box.
9. `mdl behat m502 @enrol_apply` — **scenario count 3**, unchanged (this slice touches no label the
   feature drives).
10. `mdl ci moodle-enrol_apply --matrix`.

**Done when.** A cohort-restricted, date-windowed instance refuses the right applicants at
`allow_apply()`, the restriction cannot be forged through the edit form, and a cross-site restore
fails closed.

---

## Slice 2 — Markup and accessibility cleanup + `bootstrap_compat_test`

**Goal.** Nothing changes for a user. The plugin stops shipping Bootstrap 4 class names beside their
Bootstrap 5 spellings, each queue row gains a header column so a screen reader announces it by name,
and a test now fails if anybody reintroduces either defect — before slices 4–7 write five new
templates.

**Depends on.** Nothing. Must land **before** slice 4, because that slice adds templates and the test
has to exist first or it will never be written.

### Files

| File | Change |
|---|---|
| `templates/manage.mustache` | Line 58: `class="mb-0 mr-2 me-2"` → `class="mb-0 me-2"`. Line 59: `class="custom-select form-select mr-2 me-2"` → `class="form-select me-2"`. |
| `manage_table.php` | Add `$this->define_header_column('fullname');` beside the existing column setup. This is `flexible_table::define_header_column()` (`lib/table/classes/flexible_table.php:495`, both branches) — core's own callers are `user/classes/table/participants.php:185` and `admin/tool/task/classes/running_tasks_table.php:61`. **Do not change the constructor signature**: `manage.php:126` calls it with three arguments and `tests/lib_test.php:341` with one. |
| `info_table.php` | The same one-line addition, on its own name column. |
| `tests/local/bootstrap_compat_test.php` | **New.** Copy the shape from `~/dev/moodle-local_dimensions/tests/local/bootstrap_compat_test.php`: a `\basic_testcase` scanning `templates/`, `amd/src/`, `classes/`, `renderer.php`, `manage_table.php`, `info_table.php`. |
| `styles.css` | Audit for `!important` and for hardcoded colours; convert any literal colour to the `var(--bs-*, var(--*, #fallback))` chain. Today the file is ten lines and carries one literal (`border-color: grey`). |
| `version.php`, `CHANGELOG.md` | Bump + entry under `### Changed` and `### Fixed`. |

### Design references

§08 "Traps measured in the code", the `templates/manage.mustache:58-59` row. Fleet `~/dev/CLAUDE.md`
§2 "Mustache templates" (the Bootstrap 4/5 dual-compatibility section and the badge colour rule).

### Traps

- **The asymmetry runs both ways.** `mr-*`, `custom-select`, `ml-*`, `text-left`, `sr-only`,
  `no-gutters` *do* resolve on 5.x — but only through `bs4-compat.scss`, wrapped in
  `@include deprecated-styles()` (a red outline under `behat-site` and themedesignermode), and Moodle
  6.0 removes that file entirely (MDL-84465). Their BS5 spellings resolve on both branches. Writing
  `mr-2 me-2` buys nothing and costs a deprecation. **The BS5 name alone is correct.** (This plugin
  supports 501/502 only, so 4.5's 116-line forward bridge is not in scope — but the rule is the same.)
- **Clean the two lines before adding the test, or the new test fails on them.** That is the design's
  explicit instruction.
- **Mutation-test each assertion in the new test file.** Two drafts of the `local_dimensions` original
  passed while blind to the very defect they were written for: one filtered badges by the word
  "badge" appearing on the same line (missing `match` arms whose method name carried it); one checked
  class *families*, so removing `.gap-2` still matched via `.gap-1`. Assert on exact class tokens.
- **Badges must state their text colour.** On 5.x, Bootstrap's `.badge` defaults to **white** text, so
  `bg-warning` measures **1.95:1** and `bg-secondary` **1.49:1** against the 4.5:1 AA floor. Every
  `bg-*` on a badge carries an explicit text utility: `text-white` on
  `success/primary/danger/info/dark`, `text-dark` on `secondary/warning`. Slices 4–7 all add badges;
  the test must pin this now.
- **Stylelint forbids `!important`** (`declaration-no-important`) and inline SVG data URIs
  (`function-url-scheme-disallowed-list`). It also rejects `clamp()`/`min()`/`max()` in
  length-valued properties (`csstree/validator`) and transitions under 100 ms.
- **Nothing in the pipeline reads a class name out of a Mustache or JS file.** phpcs reads PHP, the
  mustache lint reads structure, stylelint reads CSS. In `local_dimensions` this defect class shipped
  three times, was correctly root-caused each time, and recurred anyway. The difference is
  enforcement, not diligence.

### Verification

1. `rg -n 'mr-|ml-|pl-|pr-|text-left|text-right|float-left|float-right|border-left|border-right|rounded-left|rounded-right|sr-only|no-gutters|custom-select' templates/ amd/src/ classes/ renderer.php manage_table.php info_table.php` — expect no matches.
2. Write `tests/local/bootstrap_compat_test.php` with at least:
   - `test_no_bootstrap4_only_class_names` — scans the file set for the token list above; asserts the
     offender list is empty and the failure message names file and token.
   - `test_every_badge_background_declares_a_text_colour` — for every occurrence of `bg-success`,
     `bg-primary`, `bg-danger`, `bg-info`, `bg-dark`, `bg-secondary`, `bg-warning` inside a `badge`
     class attribute, assert the matching `text-white`/`text-dark` token is present in the same
     attribute.
   - `test_stylesheet_declares_no_hardcoded_brand_colour` — asserts `styles.css` contains no bare
     colour literal (hex, `rgb(`, or a CSS named colour from a small explicit list) outside a
     `var(...)` fallback position.
   - `test_every_table_class_defines_a_header_column` — asserts the source of `manage_table.php` and
     `info_table.php` each contain a `define_header_column(` call. Crude, and deliberately so: the
     method has no observable return and the alternative is asserting on rendered HTML.
3. **Mutation checks.** Re-add `mr-2` to `templates/manage.mustache:58` →
   `test_no_bootstrap4_only_class_names` goes red. Add `<span class="badge bg-warning">x</span>` to
   the same template → `test_every_badge_background_declares_a_text_colour` goes red. Put
   `border-color: #808080;` into `styles.css` → `test_stylesheet_declares_no_hardcoded_brand_colour`
   goes red. Delete the `define_header_column` line from `manage_table.php` →
   `test_every_table_class_defines_a_header_column` goes red. Revert all four; each must go red
   **alone**.
4. `mdl phpunit m502 enrol_apply` — the whole suite green, including
   `tests/lib_test.php`'s `queued_user_ids()` helper, which instantiates `manage_table.php` directly.
5. `mdl purge m502`, then open `http://localhost:8502/enrol/apply/manage.php?id=<instanceid>` and
   confirm the bulk action bar still lays out on one line with the label and select spaced, and that
   the row header cell is a `<th scope="row">` (check with
   `curl -s ... | rg -o '<th[^>]*scope="row"'`).
6. `mdl behat m502 @enrol_apply` — scenario count 3.
7. `mdl ci moodle-enrol_apply --matrix` — the `grunt` gate (stylelint) must be clean.

**Done when.** The two offending lines are gone, both tables announce their rows, and a test in the
suite fails when any of the four defect classes is reintroduced.

---

## Slice 3 — Storing the field set (no visible change)

**Goal.** An administrator can limit which profile fields courses may ask for, and a teacher can pick
from that pool per instance. The *rendered form is unchanged* — the old `apply_form.php` keeps
rendering, now driven by the resolved set instead of by two all-or-nothing switches — and the
approver's notification finally carries **what the applicant typed** rather than what was already in
the database.

**Depends on.** Nothing. Deliberately lands before the UI rewrite so the resolution rules, the
migration and the retirement of dead configuration are reviewable on their own.

### Files

| File | Change |
|---|---|
| `classes/local/fields.php` | **New.** `\enrol_apply\local\fields`: the `DEFAULT_SET` constant (13 keys), the `DENY` constant, `pool(\context $context): array`, `resolve(\stdClass $instance, \context $context): array`, `classify()` (the three states — implemented here, consumed from slice 4), and `label(string $key, bool $escape): string`. |
| `classes/local/fieldset.php` | **New.** `\enrol_apply\local\fieldset`: the versioned JSON envelope — `from_json()`, `to_json()`, one `field` value object carrying key, source, id, label, datatype, visibility, required flag. |
| `settings.php` | Add `enrol_apply/allowedfields` (`admin_setting_configmulticheckbox`) with an **explicit non-empty default**, built behind the same `during_initial_install()` guard the existing `$fieldoptions` block uses (`settings.php:39-45`) because the choices read `{user_info_field}`. Remove `show_standard_user_profile` and `show_extra_user_profile` (`settings.php:201`, `:209`). Remove `profileoption` (`settings.php:46-52`). |
| `edit_form.php` | Replace the two `customint1`/`customint2` selects (`edit_form.php:117-121`) with the per-field picker (a checkbox pair per field, `hideIf`-ing "required" on the field's own checkbox), grouped under `fieldcat_<id>` headers. Everything inserted **before** the `set_data()` call at `edit_form.php:145`. |
| `edit.php` | Serialise the picked set into `customtext4` before `add_instance()` / `update_instance()`. |
| `lib.php` | `get_instance_defaults()` (`:441`): drop the `customint1`/`customint2` lines (`:445-446`), add `customtext4`. `restore_instance()` (`:914`): revalidate `customtext4` against the destination site, in the block that already degrades `customtext3`. `send_application_notification()` (`:677`): read the resolved set, and **take the custom-field values from `$data`, not from `profile_load_custom_fields()`** — see the traps. |
| `apply_form.php` | Render the resolved set instead of calling `useredit_shared_definition()` (`:98`) / `profile_definition()` (`:102`) wholesale. This file is deleted in slice 4; keep the change minimal. |
| `renderer.php` | Drop `'lang' => 'preferredlanguage'` (**line 53**) and `'url' => 'webpage'` (**line 58**) from `STANDARD_USER_FIELDS`; drive the notification body from the resolved set; run every value through `format_string(..., ['escape' => false])`. |
| `manage_table.php` | Remove the `profileoption` join (`:100-108`) and `col_profilefield()` (`:247-249`). **Constructor signature unchanged.** |
| `db/upgrade.php` | New step: seed `enrol_apply/allowedfields` with the default set; migrate every instance's `customint1`/`customint2` into a `customtext4` envelope. Ends with `upgrade_plugin_savepoint(true, <newversion>, 'enrol', 'apply')`. |
| `lang/en/enrol_apply.php`, `lang/pt_br/enrol_apply.php` | Add `allowedfields`, `allowedfields_desc`, `fieldrequired`, `requestedfields`, `requestedfields_help`, one label per key not reusing core's. **Remove** `show_standard_user_profile`, `show_standard_user_profile_desc`, `show_extra_user_profile`, `show_extra_user_profile_desc`, `profileoption`, `profileoption_desc`. |
| `CLAUDE.md` | Fix the sentence naming `useredit_update_user_profile()` — that function exists on neither 5.1 nor 5.2. (Design's "Corrections this work found in existing docs", item 2.) |
| `version.php`, `CHANGELOG.md` | Bump + `### Added` / `### Removed` / `### Fixed`. |
| `tests/local/fields_test.php` | **New.** |

### Design references

§01 "What is wrong today", §04 "Choosing the fields, at two levels", §04 "Key vocabulary", §04 "What
has to be retired alongside it", §05 "The rule: intersection, not negation".

### Traps

- **The approver never sees what the applicant typed.** `send_application_notification()` builds
  `$extrauserfields` by calling `profile_load_custom_fields($applicant)` and reading
  `$applicant->profile` (`lib.php:700-703`) — **the database, not the form**. The standard fields do
  come from `$data` (`:692-695`), so the defect is asymmetric and easy to miss. Take both from the
  submitted data, keyed by the resolved set.
- **`customtext4` is untrusted input, not plugin configuration.** Core backs up `customtext1..4`
  verbatim (`backup/moodle2/backup_stepslib.php:695`), `restore_instance()` hands `$data` to
  `add_instance()`, and that copies **every key with no allowlist**
  (`lib/enrollib.php:2618-2625`). Anyone who can restore a course — an ordinary teacher on most
  sites — chooses its contents. **Every read intersects with the pool recomputed server-side.** A
  deny-list alone is not enough.
- **The site setting's default is load-bearing.** `admin_setting_configmulticheckbox` stores **only
  the ticked keys**. If the default comes out empty, the intersection in `resolve()` zeroes
  everything and every migrated instance silently stops collecting the fields it collected before the
  upgrade — no error, no warning. Write the default as
  `array_fill_keys(\enrol_apply\local\fields::DEFAULT_SET, 1)`, **seed it from the upgrade step as
  well**, and pin its shape and count with a test. The precedent being copied, `showuseridentity`,
  defaults to `['email' => 1]` — and `email` is on this design's hard exclusion list.
- **The key format is `s_<column>` / `c_<user_info_field.id>` — id, not shortname.**
  `{user_info_field}` has no unique index on `shortname` and core compares shortnames
  case-insensitively (`$DB->sql_equal(..., false)`), so a rename would silently re-point the choice.
  The `profileoption` setting being retired already stores an id.
- **Default set = `\core_user::AUTHSYNCFIELDS` minus four.** That constant is 17 names,
  byte-identical on both branches (`lib/classes/user.php:69-87`). Excluded: `email` (login identifier
  under `authloginviaemail` — account-takeover surface), `idnumber` (account-matching key for
  `tool_uploaduser`, and it is `PARAM_RAW`), `lang` (core only renders it for `$user->id < 0`),
  `description` (needs the full `file_prepare_standard_editor` / `file_postupdate_standard_editor`
  cycle — out of scope, and **nothing in the form may emit an editor for it**). That leaves **13
  keys**: 12 plain text fields plus `country`, a select. **Write the list as an explicit constant**,
  do not derive it, so the configuration and the renderer cannot drift.
- **The four phonetic/middle/alternate name fields are still subject to
  `useredit_get_enabled_name_fields()`** (`user/editlib.php:444`; `:450` on 5.1): if
  `$CFG->fullnamedisplay` does not use them, they drop out of the set.
- **`url` is not a user table column.** It became a `social` profile field in Moodle 4.0. It is dead
  in `renderer.php:58` today and goes in the same commit.
- **Category headers are `fieldcat_<id>`, not `category_<id>`.** `profile_definition()` already owns
  the `category_<id>` namespace.
- **Insert everything before `set_data()`.** `edit_form.php:145` ends `definition()` with
  `$this->set_data($this->prepare_instance_data($instance, $DB));` — an element added after that call
  is never populated.
- **The deny-list is the second barrier, and it is core's own minimum.** `username`, `id`, `auth`,
  `mnethostid`, `deleted` (what `update_user_record_by_id()` keeps) plus `password`, `policyagreed`,
  `confirmed`, `suspended`, `secret`, `trustbitmask`. `user_update_user()` skips only keys that are
  not columns of `{user}`.
- **`profileoption` leaks today.** `manage_table.php:100-108` and `:247-249` print a profile field
  value in the queue with **no visibility check at all**. Retiring it closes an existing hole for
  free.
- **Do not change `enrol_apply_manage_table`'s constructor.** `manage.php:126` calls it with three
  arguments, `tests/lib_test.php:341` with one. Those are the only two call sites; keep both working.
- **Removing a lang string means removing it from both files.** `validate` reads the alphabetical
  ordering; half a removal fails.
- **`\core_user\fields::get_identity_fields()` at `user/classes/fields.php:363` (both branches) is the
  exact precedent** for dropping shortnames that have disappeared. Match its shape.

### Verification

1. `mdl upgrade m502 && mdl phpunit-init m502 && mdl behat-init m502`; same on m501.
2. Write `tests/local/fields_test.php` (`#[CoversClass(\enrol_apply\local\fields::class)]`):
   - `test_default_set_is_authsyncfields_minus_the_four_exclusions` — asserts the constant equals
     `array_diff(\core_user::AUTHSYNCFIELDS, ['email', 'idnumber', 'lang', 'description'])` mapped to
     `s_*` keys, and `assertCount(13, ...)`.
   - `test_the_site_setting_default_is_the_full_default_set` — read the declared default through
     `admin_get_root()`/the setting object; assert it is an array of 13 keys all set to 1.
   - `test_resolve_intersects_the_picked_set_with_the_site_pool` — pick five keys, allow three at
     site level, assert `resolve()` returns exactly those three.
   - `test_resolve_drops_a_forged_key_that_is_not_in_the_pool` — write `customtext4` containing
     `s_password`, `s_auth`, `s_suspended` and a legitimate key directly into `{enrol}` with
     `$DB->set_field()`; assert `resolve()` returns only the legitimate key. **Control:** the
     legitimate key is present, proving the filter ran rather than the parse failing.
   - `test_resolve_drops_a_denied_key_even_when_the_pool_contains_it` — force the pool to contain
     `s_auth` (write `enrol_apply/allowedfields` directly), pick it, assert it does not survive
     `resolve()`. This is the only test that isolates the deny-list from the pool.
   - `test_resolve_drops_a_custom_field_that_has_been_deleted` — pick `c_<id>`, delete the
     `{user_info_field}` row, assert it is gone from `resolve()`.
   - `test_resolve_returns_the_default_set_on_a_site_where_allowedfields_was_never_written` — unset
     the config, run the upgrade step, assert `resolve()` on a migrated `customint1 = 1` instance is
     non-empty. This is the design's explicitly demanded test.
   - `test_upgrade_migrates_customint1_and_customint2_into_customtext4` — seed an instance with the
     old columns, run the upgrade step, assert `customtext4` parses and covers the expected keys.
   - `test_restore_into_another_site_revalidates_customtext4` — drive `restore_instance()` with a
     `customtext4` naming keys absent on this site; assert the result resolves to the surviving
     subset. **Control:** a key that IS present survives.
   - `test_field_label_has_both_spellings` — one field named with a bare `&` (never `<b>x</b>` — that
     is stripped identically in both escape modes and proves nothing); assert the escaped and plain
     spellings differ and that `label($key, true)` and `label($key, false)` each return the right one.
3. Extend `tests/lib_test.php`:
   - `test_the_notification_carries_the_submitted_custom_field_value_not_the_stored_one` — store
     `"stored"` in a custom field, submit `"typed"` through `apply()`, capture the message with
     `$this->redirectMessages()`, assert the body contains `typed` and **not** `stored`.
   - `test_the_notification_survives_a_raw_angle_bracket_in_a_textarea_field` — submit `A<B` into a
     `profile_field_textarea`; assert a message is produced and the body is well formed.
4. **Mutation check — the intersection.** Delete the `isset($pool[$f->key])` condition from
   `resolve()`. `test_resolve_drops_a_forged_key_that_is_not_in_the_pool` and
   `test_resolve_drops_a_custom_field_that_has_been_deleted` go red; nothing else. Restore.
5. **Mutation check — the deny-list.** Delete the `!in_array($f->key, self::DENY, true)` condition.
   `test_resolve_drops_a_denied_key_even_when_the_pool_contains_it` goes red and nothing else.
6. **Mutation check — the notification source.** Restore `profile_load_custom_fields($applicant)` as
   the source of `$extrauserfields`.
   `test_the_notification_carries_the_submitted_custom_field_value_not_the_stored_one` goes red and
   nothing else.
7. `rg -n 'show_standard_user_profile|show_extra_user_profile|profileoption' .` — expect matches only
   in `CHANGELOG.md` and `db/upgrade.php`.
8. `rg -n 'new .?enrol_apply_manage_table' . ` — expect exactly two hits: `manage.php:126` and
   `tests/lib_test.php:341`.
9. `diff <(grep -o "^\$string\['[^']*'\]" lang/en/enrol_apply.php | sort) <(grep -o "^\$string\['[^']*'\]" lang/pt_br/enrol_apply.php | sort)` — empty.
10. Manual: `http://localhost:8502/admin/settings.php?section=enrolsettingsapply` shows the new
    multi-checkbox with the 13 defaults ticked and the three retired settings gone. Then
    `http://localhost:8502/enrol/apply/edit.php?courseid=<id>&id=<instanceid>` shows the picker.
    Finally `http://localhost:8502/enrol/index.php?id=<courseid>` — the old form renders **only** the
    picked fields.
11. `mdl phpunit m502 enrol_apply` and `mdl behat m502 @enrol_apply` — **scenario count 3** (they
    press "Enrol me", which still exists in this slice).
12. `mdl ci moodle-enrol_apply --matrix` — watch the `validate` step for lang ordering.

**Done when.** The picked field set survives a hostile `customtext4`, the migration keeps existing
instances collecting what they collected, the approver receives what was typed, and the two orphan
settings and the `profileoption` leak are gone.

---

## Slice 4 — Card on the enrolment page, form on its own page or in a modal

**Goal.** An applicant sees one short card per enrolment method instead of a duplicated profile dump,
clicks through to a review screen showing only the picked fields, and submits. Two methods on one page
no longer emit duplicate element ids.

**Depends on.** Slice 3 (the resolved set is what the form renders) and slice 2 (the compat test must
exist before three new templates land).

### Files

| File | Change |
|---|---|
| `classes/form/application_form.php` | **New.** `\enrol_apply\form\application_form extends \core_form\dynamic_form`. Implements `get_context_for_dynamic_submission()`, `check_access_for_dynamic_submission()`, `set_data_for_dynamic_submission()`, `process_dynamic_submission()`, `get_page_url_for_dynamic_submission()`, plus `definition()` and `validation()`. |
| `apply.php` | **New.** The non-JS transport: renders the same form class outside AJAX, processes the POST, redirects to `applied.php`. |
| `applied.php` | **New.** The acknowledgement page. `require_login()`, then a check that the current user really has a `user_enrolments` row on that instance. |
| `amd/src/enrol_page.js` | **New.** Intercepts the card's button click and opens `core_form/modalform` (`lib/form/amd/src/modalform.js`) with the form class. |
| `amd/build/enrol_page.min.js`, `.map` | **New, tracked in git**, built by `mdl grunt m502 enrol/apply`. Commit with the source. |
| `templates/enrol_page.mustache` | **New**, only if the card needs anything beyond `\core_enrol\output\enrol_page`. |
| `lib.php` | `enrol_page_hook()` (`:133`) rewritten: return `$OUTPUT->render(new \core_enrol\output\enrol_page($instance, $header, $body, [$button]))`. **Delete the `require_once($CFG->dirroot . '/enrol/apply/apply_form.php')` at `lib.php:156`** — leaving it is a fatal on `/enrol/index.php`. `apply()` (`:201`) gains the short lock. |
| `apply_form.php` | **Deleted.** |
| `lang/en/enrol_apply.php`, `lang/pt_br/enrol_apply.php` | `applicationsubmitted`, `applicationsubmitted_body`, `checkyourdetails`, `confirmfield`, `detailsthattravel`, `detailsthattravel_desc`, `fieldisuptodate`, `lockedby`, `requiredtoapply`, `startapplication`, `submitapplication`, `youwillcheckndetails`. (The old button label came from `get_string('enrolme', 'enrol_self')` at `apply_form.php:107` — it is core's string, so there is nothing of the plugin's to remove.) |
| `styles.css` | The "filled in / empty / locked" treatment, using the `var(--bs-*, var(--*, …))` token chain. |
| `tests/behat/enrol_apply.feature` | **All three scenarios rewritten** in this commit. |
| `tests/form/application_form_test.php` | **New.** |
| `version.php`, `CHANGELOG.md` | Bump + entry. |

### Design references

§01 "What is wrong today", §03 "One form class, two transports", §03 "Locked field: three states, not
two", §03 "Decide before the `addElement`", §03 "The red does not come for free", §03 "A new hole the
design has to close", §03 "The detail that makes the modal work", §03 "Two cautions on the CTA".

### Traps

- **The form's context is the course *category*, not the course.** `dynamic_form`'s constructor runs
  `external_api::validate_context()`, which ends in `require_login()` — for an applicant not yet
  enrolled that throws `require_login_exception('Not enrolled')`, and the modal fails for exactly the
  people it exists for. Both of core's enrolment forms return the category context and say why in a
  comment. **Every authorisation decision is still made in the course context**, resolved server-side
  from the instance id inside `check_access_for_dynamic_submission()`.
- **Reproduce the "log in as" guard in both new routes.** `enrol/index.php:63-64` (identical on both
  branches) refuses any enrolment during a log-in-as session (`loginasnoenrol`), and today that file
  is the **only** caller of `enrol_page_hook()` anywhere in core — verified by
  `rg -n enrol_page_hook` across both checkouts. `apply.php` and the `core_form_dynamic_form` web
  service (`ajax => true`) do not go through it. Without the guard in both, an administrator logged
  in as somebody else submits an application in their name. `enrol_self`'s own form has this flaw;
  copying it verbatim inherits it.
- **`check_access_for_dynamic_submission()` re-evaluates everything**: log-in-as, `isguestuser()`,
  `core_course_category::can_view_course_info()`, `allow_apply()`, already-applied
  (`lib.php:146-148`), and the `customint3` places cap (`lib.php:150-156`).
- **Classify before the `addElement`, never hide afterwards.** `required` rules are attached when the
  element is created, and `HTML_QuickForm::validate()` walks `$_rules` **by name without checking
  whether the element still exists**. Any add-then-remove technique, or CSS hiding, leaves the form
  permanently unsubmittable with no visible field to explain why.
- **Do not call `useredit_shared_definition()` at all.** It adds four section headings
  unconditionally, before and independently of the fields. On an SSO site where everything is locked
  the result is an accordion of empty sections.
- **Three states, not two.** Editable (field + confirmation checkbox → screen, snapshot, saved);
  locked (auth lock or `user_info_field.locked` → read-only on screen under its own heading,
  snapshot, **never** saved); absent (`PROFILE_VISIBLE_NONE` — `user/profile/lib.php:48` — plus
  `lang`, guest, MNet, `!can_edit_profile()` → nothing, nowhere). Hiding a locked field while still
  snapshotting it and mailing it to an approver discloses a value the applicant never saw. Core's own
  answer to a locked field is the opposite of hiding it: `hardFreeze()` renders label and value with
  no input (`user/edit_form.php:175-184`).
- **Emit `confirm_<key>` only for keys that actually produced an element.**
  `profile_field_base::edit_field()` returns `false` and adds **no element at all** when
  `is_editable()` is false (`user/profile/lib.php:157-166` on 5.2, `:167-176` on 5.1) — which is
  exactly when `moodle/user:editownprofile` is missing. The field disappears while the checkbox stays
  on screen with nothing to confirm, and the "confirm before submitting" rule blocks a form the user
  cannot satisfy. **Test `edit_field()`'s boolean return.**
- **Each confirmation checkbox names its own field.** An `advcheckbox` with an empty element label
  gets **no accessible name at all** — `element-advcheckbox.mustache` emits only an
  `aria-describedby`, which is a description, not a name. Six fields would announce as six identical
  controls.
- **The red asterisk and the "empty right now" treatment are two different things.** `{{#required}}`
  in `element-template.mustache` is switched on by `addRule(..., 'required', ...)` and appears on
  every required field, filled in or not; `.invalid-feedback` only paints after a rejected POST. So
  it is `addRule` for the marker **and** the check in `validation()` to make it stick (a client-side
  rule never blocks a POST) **and** a treatment of its own for "empty right now".
- **`strictformsrequired` defaults off**, in which case a single space satisfies a required field:
  `MoodleQuickForm_Rule_Required::validate()` in `lib/formslib.php` strips whitespace **only** when
  `$CFG->strictformsrequired` is set, then tests `(string)$value == ''`. Same code on both branches;
  the line numbers differ, so find it by class name. `trim()` before comparing; treat the empty result
  as not filled in. There is nothing else to "honour": use the standard rule and it is inherited for
  free by a `dynamic_form`.
- **`profile_validation()` walks every field that has data, not only the chosen ones.** Intersect its
  return with `elementExists()`, or a `forceunique` field nobody picked blocks the form with an error
  that appears nowhere on screen.
- **The comment field moves with the form.** `customint7` with its label from `customtext2` (today at
  `apply_form.php:85-92`) must land in the new form — `manage_table.php:93` and `info_table.php:66`
  still read `ai.comment`. **Its label becomes the escaped spelling**, because a moodleform label
  renders in `{{{label}}}`; `apply_form.php:88` uses the plain one today.
- **The pre-load moves too.** `set_data_for_dynamic_submission()` replaces
  `$mform->setDefaults((array) $USER)` at `apply_form.php:105`, and pre-loads the picked custom
  fields as well.
- **`applied.php` must not be a free page telling strangers the instance exists.** Plain
  `require_login()`, then check that the current user really does have a `user_enrolments` row on
  that instance. And **never redirect to `/course/view.php`** — it bounces a suspended user straight
  back.
- **The CTA destination comes from `get_home_page()`** (`lib/moodlelib.php:10012`; `:9901` on 5.1)
  plus the integer→URL mapping copied from `login/lib.php:356-372`, with a fallback to `/` in the
  `HOMEPAGE_URL` case. Do **not** call `core_login_get_return_url()` (`login/lib.php:334`) — it
  clears `$SESSION->wantsurl` — and do **not** call `user_get_default_homepage_options()`, which is
  `user/lib.php:822` on **5.2 only** and absent from 5.1.
  `get_default_home_page_url()` (`lib/moodlelib.php:10098`; `:9951` on 5.1) accepts any local path,
  `/course/view.php?id=N` included — a place the applicant cannot get into.
- **Add a short lock around `apply()`.** "One row per application" is an assertion, not a guarantee:
  two simultaneous submissions both pass the already-applied `record_exists` and both reach
  `apply()`. Today the `foreign-unique` key `userenrolment` on `enrol_apply_applicationinfo`
  (`db/install.xml`) makes the second insert blow up. The `customint3` cap has the same race.
- **All three Behat scenarios do `I press "Enrol me"`** — a label from `apply_form.php:107`, the file
  this slice deletes. Rewrite them in the same commit; every Behat round costs minutes.
- **Read the lang string before writing the assertion.** Guessing a label costs a full Behat round
  each time. `rg "^\$string\['<key>'\]" lang/en/enrol_apply.php`.
- **Keep a Behat label and the value it introduces on one source line.**
  `behat_general::assert_page_contains_text()` builds an XPath `contains()` over the **raw** string
  value with no `normalize-space()` (`lib/tests/behat/behat_general.php:747-754`, same on both
  branches), so a template that splits "Applied by:" and the name across two source lines fails an
  assertion the browser renders as one space.
- **`amd/build/**` is tracked in git.** Every `amd/src` edit ships its rebuilt `.min.js` + `.map` in
  the same commit, plus the version bump so the cache revision changes. `npx eslint --max-warnings 0`
  is the bar; a plain local grunt prints warnings and exits 0.

### Verification

1. `mdl grunt m502 enrol/apply`, then `mdl upgrade m502 && mdl phpunit-init m502 && mdl behat-init m502`, then `mdl purge m502`. Same on m501.
2. Write `tests/form/application_form_test.php`:
   - `test_check_access_refuses_a_login_as_session` — set up a log-in-as session, assert
     `check_access_for_dynamic_submission()` throws.
   - `test_check_access_refuses_a_guest`.
   - `test_check_access_refuses_when_allow_apply_refuses` — disable `customint6`; assert it throws.
     **Control:** with `customint6` on, it does not.
   - `test_check_access_refuses_a_second_application` — apply once, assert the second throws.
   - `test_check_access_refuses_when_the_places_cap_is_reached` — `customint3 = 1`, one existing
     enrolment; assert throws.
   - `test_form_context_is_the_course_category` — assert `get_context_for_dynamic_submission()`
     returns a `context_coursecat`.
   - `test_definition_renders_only_the_resolved_fields` — pick three keys, assert exactly those
     elements exist and a non-picked one does not.
   - `test_a_locked_field_is_rendered_static_and_never_gets_a_confirm_checkbox` — set
     `auth_manual/field_lock_city` to `locked`; assert no `city` input element, a static element
     carrying the value, and **no** `confirm_s_city` element.
   - `test_an_absent_field_produces_no_element_and_no_confirm_checkbox` — a custom field with
     `PROFILE_VISIBLE_NONE`; assert neither appears.
   - `test_a_whitespace_only_required_value_is_rejected` — post `' '` into a required field; assert
     `validation()` returns an error for it.
   - `test_profile_validation_errors_are_intersected_with_existing_elements` — create a `forceunique`
     custom field that is **not** picked and already holds a colliding value; assert `validation()`
     returns no error for it.
   - `test_the_comment_label_uses_the_escaped_spelling` — set `customtext2` to `A & B`; assert the
     element's label carries `&amp;`.
3. **Mutation check — log-in-as guard.** Delete the guard from
   `check_access_for_dynamic_submission()`. `test_check_access_refuses_a_login_as_session` goes red
   and nothing else. Repeat for the guest guard, the `allow_apply()` call, the already-applied check
   and the cap check — one named test red each time, nothing else.
4. **Mutation check — the locked-field branch.** Delete the branch that renders a locked field as
   static; `test_a_locked_field_is_rendered_static_and_never_gets_a_confirm_checkbox` goes red.
5. **Mutation check — `edit_field()`'s return value.** Change the confirm-checkbox emission to be
   unconditional; the same test goes red on the `confirm_s_city` assertion.
6. `rg -n 'apply_form' .` — expect matches only in `CHANGELOG.md`.
7. Rewrite `tests/behat/enrol_apply.feature`: in all three scenarios replace
   `I press "Enrol me"` with `I press "Start application"` → the review screen →
   `I press "Submit application"`, then assert the acknowledgement text. Scenarios 2 and 3 keep their
   manage.php steps (`Select Student 1`, `With selected users...`, `Go`) — those are untouched by
   this slice. Run `mdl behat m502 @enrol_apply` and **check the scenario count is 3**, not just the
   exit status.
8. Manual, JS on: `http://localhost:8502/enrol/index.php?id=<courseid>` with **two** apply instances
   configured. Confirm: one short card per method, each with a single button; clicking opens the
   **modal**. Then `curl -s "http://localhost:8502/enrol/index.php?id=<courseid>" | rg -o 'id="id_[a-z0-9_]*"' | sort | uniq -d`
   — expect **no output** (this is the WCAG 1.3.1/4.1.1 duplicate-id defect, measured as present
   today).
9. Manual, JS off (DevTools → Settings → Disable JavaScript): the same button navigates to
   `http://localhost:8502/enrol/apply/apply.php?instance=<id>` and submits, landing on `applied.php`.
10. Manual, negative: as an admin, "Log in as" the student, then open `apply.php?instance=<id>`
    directly — expect the `loginasnoenrol` exception, not a form.
11. Manual, negative: as a user with no enrolment on the instance, open
    `http://localhost:8502/enrol/apply/applied.php?instance=<id>` — expect a refusal, not a page
    naming the instance.
12. `mdl ci moodle-enrol_apply --matrix --behat`.

**Done when.** The enrolment page carries one card per method with no duplicated ids, the form works
in both transports, a locked field is visible and read-only, and the three rewritten Behat scenarios
pass.

---

## Slice 5 — Review, optional profile write, and the completeness gate

**Goal.** After submitting, an applicant is offered "Save these profile details for future
applications?" and one click writes only what actually changed. When the site or the instance has
writing switched off, the applicant instead gets a gate naming exactly which fields are missing,
deep-linked to `/user/edit.php`, writing nothing.

**Depends on.** Slice 4 (the form and the acknowledgement page) and slice 3 (the resolved set and the
classification).

### The switch — decided here, in one place

The design's decision log requires that the write switch be zeroed on restore, and the trap analysis
recommends a site setting with no restore surface. Both, and this slice owns all of it:

- `enrol_apply/allowprofilewrite` — **site master switch**, default off. No restore surface at all.
- `customint8` — **per-instance opt-in**, offered on `edit_form.php` only when the site switch is on.
- **Effective = site AND instance.** Neither alone enables a write.
- `restore_instance()` zeroes `customint8`, because it is in `update_instance()`'s property list
  (`lib/enrollib.php:2643-2648`) and `add_instance()` copies it unconditionally (`:2618-2625`), so a
  course restored into a category the attacker controls would otherwise turn the write on by itself.

Nothing about `customint8` belongs in slice 3; it is introduced, defaulted, formed, restored and
tested here.

### Files

| File | Change |
|---|---|
| `classes/local/profilewriter.php` | **New.** `\enrol_apply\local\profilewriter::write()`: re-classification, the explicit allowlist, core's canonical write order, one `user_updated` event at the end. |
| `classes/local/diff.php` | **New.** Computes the before/after delta between submitted values and the live record. |
| `classes/form/profile_confirm_form.php` | **New.** The confirmation form on `profile.php`; carries the submitted values as hidden fields. |
| `profile.php` | **New.** `/enrol/apply/profile.php?instance=N` — the offer, the write, the in-place state swap. |
| `applied.php` | Render the offer (writing on) or the completeness gate (writing off). |
| `settings.php` | New `enrol_apply/allowprofilewrite` (`admin_setting_configcheckbox`, default `0`). |
| `edit_form.php` | New `customint8` advcheckbox, shown only when the site switch is on; hidden+`setConstant(0)` otherwise (same reasoning as slice 1's cohort fallback). |
| `lib.php` | `get_instance_defaults()`: add `$fields['customint8'] = 0;`. `restore_instance()`: `$data->customint8 = 0;` unconditionally, beside the `customtext4` revalidation added in slice 3. |
| `amd/src/profile_save.js`, `amd/build/profile_save.min.js`, `.map` | **New.** The in-place button swap; progressive enhancement over a real POST+redirect. |
| `templates/profile_offer.mustache` | **New.** The diff table and the three button states. |
| `lang/en/enrol_apply.php`, `lang/pt_br/enrol_apply.php` | `allowprofilewrite`, `allowprofilewrite_desc`, `gotoprofile`, `profileincomplete`, `profileincomplete_desc`, `profileupdated`, `saveforfuture`, `saveforfuture_desc`, `saveforfutureinstance`, `saveforfutureinstance_help`, `updateprofile`. |
| `version.php`, `CHANGELOG.md` | Bump + entry. Mention `enrol_gapply` (the completeness-gate idea). |
| `tests/local/profilewriter_test.php` | **New.** |

### Design references

§05 "Optional write and the data lifecycle", §05 "The shape that works", §05 "Two findings that
change the screen", §05 "The two locks today's form does not have", §05 "Write order — core's own",
§05 "Reclassify at write time", §05 "Erasing by accident", §10 "Two defects not to inherit in the
profile gate".

### Traps

- **Locks are UI only.** `user_update_user()` never consults `field_lock_*`, and
  `profile_save_data()` performs **no authorisation check of any kind** — whatever is on the object
  is written to `user_info_data`. Core's only defence against a forged POST is `setConstant()`
  winning in `exportValues()`, and slice 4's rule "a locked field is not even rendered" removes
  exactly that defence, because there is no element to carry the constant. **Re-run the
  classification at write time and write only the keys classified editable — never the set of keys
  submitted.**
- **`hardFreeze()` and `setConstant()` are both load-bearing.** `hardFreeze()` erases every rule and
  makes `exportValues()` export an empty string; `setConstant()` lays the original value back over
  it. Copying only the first **erases the locked value on the write** — a silent failure in the
  opposite direction from the one intended. It also erases the `required` rule.
- **There is no core helper for "can this user edit this field right now". Write the predicate, and
  read the lock through the auth plugin object, never through `get_config()`.** The standard field
  lock is evaluated exactly once in the whole of Moodle, at `user/edit_form.php:175-184`, as
  `$authplugin->config->{'field_lock_' . $field}` — where `$field` is `profile_field_<shortname>` for
  a custom field, so the config key is `field_lock_profile_field_<shortname>`. Going through
  `get_auth_plugin($user->auth)->config` is not a stylistic preference: `auth_manual` builds
  `$this->config` by merging the legacy component under the modern one
  (`auth/manual/auth.php:49-53`: `array_merge((array) get_config('auth/manual'), (array) get_config('auth_manual'))`),
  so `get_config('auth_manual', ...)` alone misses locks the core form honours, and every other auth
  plugin has its own construction. Second detail: `unlockedifempty`'s emptiness test is the loose
  `$value != ''` — a stored `'0'` counts as **filled** and therefore **locked**, whereas
  `!empty('0')` inverts the decision.
- **Core's write order, exactly**: `get_auth_plugin($USER->auth)->can_edit_profile()` **and**
  `has_capability('moodle/user:editownprofile', context_system::instance())`; then build `$usernew`
  from an **explicit allowlist** (never `(array) $data`); then `$authplugin->user_update($user,
  $usernew)` (otherwise LDAP/AD goes stale); then `user_update_user($usernew, false, false)` —
  `$updatepassword = false`, **`$triggerevent = false`**, or the event is born in the middle of the
  write; then `profile_save_data($usernew)`; then one
  `\core\event\user_updated::create_from_userid($USER->id)->trigger()`
  (`lib/classes/event/user_updated.php:98`, both branches).
- **The event carries ids and counts, never values.** The log store is covered by no provider and no
  deletion request reaches it.
- **The button is inert in the common case without a diff.** The old form pre-filled from `$USER`
  (`apply_form.php:105`), so anyone who edited nothing posts back their own record. **Compare against
  the live record, show only what changed, and hide the button when the delta is empty.**
- **Only a subset can be written.** Picture, description, interests, theme and timezone would each
  need core's own dedicated calls (`core_user::update_picture`, `useredit_update_interests`,
  `useredit_update_user_preference`). Slice 3's picker already fixes this at the root by not
  collecting them; the confirmation page enumerates exactly what will be written.
- **An empty value erases.** Core's own boundary is `edit_save_data()` returning at
  `if (!isset($usernew->{$this->inputname}))`: a submitted value is ignored only when it is
  **absent** from the form data. For `checkbox` and `menu`, any posted value including `'0'` is real;
  for text fields, `''` is discarded only when a stored value already exists. **This is a test, not a
  comment.**
- **Whitespace-only values are trimmed and treated as empty**, before comparing and before writing.
- **`require_sesskey()` on the write**, plus a check that a non-active `user_enrolments` row really
  does exist on that instance.
- **The plugin never redirects to `/user/edit.php` — but it cannot promise the user never lands
  there.** `require_login()` redirects there whenever `user_not_fully_set_up()`, and every home
  destination except the site home goes through `require_login()`. Say the honest sentence in the
  docs.
- **`/user/edit.php` cannot be pre-filled.** It accepts four parameters — `id`, `course`, `returnto`,
  `cancelemailchange` (`user/edit.php:32-35`, identical on both branches) — and none carries a field
  value. `$user` is read from `{user}` and the form ends in `set_data($user)`. There is no hook,
  session key or draft. `returnto` is `PARAM_ALPHA` tested against the literal `'profile'`.
- **The completeness gate must not inherit `enrol_gapply`'s two defects.**
  (1) `profile_user_record($id)` assumes `$onlyinuserobject = true`
  (`user/profile/lib.php:812` on 5.2, `:822` on 5.1), and
  `profile_field_textarea::is_user_object_data()` returns **false**
  (`user/profile/field/textarea/field.class.php:47`, both branches) — a chosen textarea field
  vanishes from the record, reads as empty forever, and the applicant is permanently locked out with
  no way to satisfy the gate. Pass `false`, or read through
  `profile_get_user_fields_with_data()` (`user/profile/lib.php:640` on 5.2, `:650` on 5.1).
  (2) They apply `format_text()` to the field *name*; a name wants `format_string()`.
- **A reload loses nothing.** The submitted values ride in the same request as the confirmation, so
  hidden fields make a new table unnecessary — no privacy metadata, no retention, no
  `test_table_coverage`. A reload hits the already-applied short-circuit (`lib.php:146-148`), where
  no button is rendered at all.

### Verification

1. `mdl grunt m502 enrol/apply`, `mdl upgrade m502 && mdl phpunit-init m502 && mdl behat-init m502`, `mdl purge m502`. Same on m501.
2. Write `tests/local/profilewriter_test.php`:
   - `test_write_ignores_a_key_the_user_may_not_edit` — post `city` and `auth`; assert `city` written,
     `auth` unchanged. **Control:** `city` really did change, proving the write ran.
   - `test_write_ignores_a_locked_field_even_when_posted` — lock `city` via
     `auth_manual/field_lock_city`, post a new value; assert the stored value is unchanged and that
     an unlocked field posted in the same request **was** written.
   - `test_write_honours_a_custom_field_lock` — same, for
     `auth_manual/field_lock_profile_field_<shortname>`.
   - `test_unlockedifempty_treats_a_stored_zero_as_filled` — store `'0'`, set the lock to
     `unlockedifempty`; assert the field is treated as **locked**.
   - `test_write_reads_the_lock_through_the_auth_plugin_config` — set the lock through the **legacy**
     `auth/manual` component only; assert it is still honoured. This is the test that fails if
     somebody "simplifies" the predicate to `get_config('auth_manual', ...)`.
   - `test_a_whitespace_only_value_is_treated_as_empty_and_not_written`.
   - `test_an_absent_key_does_not_erase_a_stored_value` and
     `test_a_posted_empty_string_erases_only_where_core_would` — one test per branch of the
     `isset()` boundary.
   - `test_write_requires_can_edit_profile` and `test_write_requires_editownprofile` — remove each
     precondition, assert the write is refused.
   - `test_write_fires_exactly_one_user_updated_event` — use `$this->redirectEvents()`; assert
     `assertCount(1, ...)` and that the event carries no field values.
   - `test_write_is_refused_when_the_site_switch_is_off` and
     `test_write_is_refused_when_the_instance_switch_is_off` — one test per half of the AND.
   - `test_restore_into_another_site_zeroes_customint8` — drive `restore_instance()` with
     `customint8 = 1`; assert `customint8 == 0` on the created instance. **Control:** `customtext4`
     survived the same restore, proving `restore_instance()` ran.
   - `test_profile_page_requires_a_sesskey` — POST to `profile.php`'s handler without a sesskey;
     assert it is refused. **Written now, not at mutation time.**
   - `test_diff_is_empty_when_nothing_changed` — pre-fill from the live record, submit unchanged,
     assert the delta is empty (this is what hides the button).
   - `test_completeness_gate_sees_a_textarea_custom_field_that_has_a_value` — the `enrol_gapply`
     defect: a `profilefield_textarea` with a stored value must **not** be reported missing.
3. **Mutation check — reclassification.** Change the writer to iterate the submitted key set instead
   of the re-classified editable set. `test_write_ignores_a_locked_field_even_when_posted` and
   `test_write_honours_a_custom_field_lock` go red; nothing else.
4. **Mutation check — the allowlist.** Replace the explicit allowlist with `(array) $data`.
   `test_write_ignores_a_key_the_user_may_not_edit` goes red.
5. **Mutation check — `$triggerevent`.** Change `user_update_user($usernew, false, false)` to
   `(…, false, true)`. `test_write_fires_exactly_one_user_updated_event` goes red on the count.
6. **Mutation check — capability gates.** Delete the `can_edit_profile()` call, then the
   `moodle/user:editownprofile` call, one at a time; exactly the matching named test goes red.
7. **Mutation check — sesskey.** Delete `require_sesskey()` from `profile.php`;
   `test_profile_page_requires_a_sesskey` goes red and nothing else.
8. **Mutation check — the restore zeroing.** Delete `$data->customint8 = 0;`;
   `test_restore_into_another_site_zeroes_customint8` goes red and nothing else.
9. **Mutation check — the auth-plugin config read.** Replace it with
   `get_config('auth_manual', 'field_lock_city')`; `test_write_reads_the_lock_through_the_auth_plugin_config`
   goes red.
10. Manual, writing ON (site and instance): apply while changing two fields, land on
    `http://localhost:8502/enrol/apply/applied.php?instance=<id>`; confirm the offer lists **exactly
    the two changed fields** with before → after, and the button swaps in place. Reload — the offer is
    gone, not duplicated.
11. Manual, writing ON, nothing changed: submit without editing; confirm **no button** is rendered.
12. Manual, writing OFF: the same flow shows the completeness gate naming the missing fields, with a
    link to `/user/edit.php?id=<userid>&returnto=profile`, and **nothing is written** — verify with
    `psql -h localhost -p 5502 -U moodle -c "select city, institution from mdl_user where id=<id>"`.
13. `mdl phpunit m501 enrol_apply && mdl phpunit m502 enrol_apply`; `mdl behat m502 @enrol_apply`
    (scenario count 3).
14. `mdl ci moodle-enrol_apply --matrix`.

**Done when.** A profile write happens only when the applicant asks for it and both switches are on,
writes only editable keys recomputed server-side, fires one event, and the "writing off" branch is a
gate that writes nothing.

---

## Slice 6 — Durable snapshot, privacy, backup and lifecycle

**Goal.** Every application leaves a durable, auditable record — the frozen field values, the status,
who decided and when — that survives approval, cancellation and unenrolment, travels in a backup with
users, is reachable by subject access and erasure, and is swept after a configurable retention.

**Depends on.** Slice 5 (the state vocabulary and the classification are what the JSON envelope
freezes) and slice 4 (the form is the single write point). Slice 3 gave it the field metadata.

### Files

| File | Change |
|---|---|
| `db/install.xml` | **New table `enrol_apply_submission`**: `id`, `courseid`, `userid`, `enrolid`, `userenrolmentid`, `comment`, `userinfodata` (text), `status` (int), `outcomemessage` (text), `timecreated`, `timedecided`, `decidedby`. **`UNIQUE (courseid, userid)`.** Every `<FIELD>` declares `SEQUENCE` explicitly. Move the `VERSION` date stamp on line 2 (from `20260810`) — it is a date, **not** `$plugin->version`. |
| `db/upgrade.php` | Create the table; backfill one row per existing pending `user_enrolments` on an apply instance. Savepoint == `$plugin->version`. |
| `db/events.php` | **New file.** Registers `\core\event\course_deleted` → `\enrol_apply\observers::course_deleted` — the safety net only. The pseudonymisation runs from `db/hooks.php`. |
| `db/hooks.php` | **Extend** (the file exists, with one callback). Add `\core_course\hook\before_course_deleted` → `\enrol_apply\hook_callbacks::before_course_deleted`. |
| `db/tasks.php` | **Extend.** Add `\enrol_apply\task\purge_submissions`, daily. |
| `classes/local/submission.php` | **New.** The status constants, the state vocabulary constant, insert/update/read helpers. |
| `classes/hook_callbacks.php` | Add `before_course_deleted()`. **Also fix the docblock at lines 44-46** (see traps). |
| `classes/observers.php` | **New.** The `course_deleted` safety net. |
| `classes/task/purge_submissions.php` | **New.** Chunked, time-budgeted retention sweep. |
| `classes/privacy/provider.php` | Declare the new table; export and delete for **two roles** — applicant (`userid`) and decider (`decidedby`). Fix the constant export path at `:138-147` while the file is open. |
| `lib.php` | `apply()` (`:201`): insert the submission row in the same transaction. `confirm_enrolment()` (`:497`), `wait_enrolment()` (`:544`), `cancel_enrolment()` (`:582`): stamp status, `timedecided`, `decidedby`. **`delete_instance()` (`:399`): stop purging submission rows** (inverts current behaviour). |
| `backup/moodle2/backup_enrol_apply_plugin.class.php` | Add the submission rows **inside the `users` block**; `annotate_ids('user', 'userid')` and `annotate_ids('user', 'decidedby')`. |
| `backup/moodle2/restore_enrol_apply_plugin.class.php` | Restore them; **drop the row when the user mapping fails**, never write `userid = 0`. |
| `manage_table.php`, `info_table.php` | Read the comment through a join on the new table where appropriate. Constructor signatures unchanged. |
| `settings.php` | `enrol_apply/retentiondays` as `admin_setting_configduration` (`lib/adminlib.php:3941`; `:3940` on 5.1), default 30 days, `0` = keep forever. Its help text records the `backup_auto_users` consequence. |
| `lang/en/…`, `lang/pt_br/…` | `privacy:metadata:enrol_apply_submission[:field]` for every column, `task_purge_submissions`, `retentiondays`, `retentiondays_desc`, `outcomemessage`, plus the status labels. |
| `README.md` | Document that `delete_instance()` no longer purges the trail, that an erasure request deletes it, and that with `backup_auto_users` off a recycle-bin round trip loses it. |
| `CLAUDE.md` | Fix the "the observer deliberately does not notify" sentence. |
| `tests/privacy/provider_test.php` | `:108` `assertCount(1, $items)` → 2, **and `:109`**, which asserts `$items[0]->get_name() === 'enrol_apply_applicationinfo'` and is index-order dependent. |
| `tests/backup_test.php`, `tests/lib_test.php` | Extend. |
| `tests/task/purge_submissions_test.php` | **New.** |
| `tests/hook_callbacks_test.php` | **New.** |
| `version.php`, `CHANGELOG.md` | Bump + entry. |

`outcomemessage` ships in this slice **deliberately unused**: schema churn is the expensive part and
slice I is its only writer. Slice 6 asserts it is empty on every row, so slice I has a baseline.

### Design references

§02 "The discovery that changes the design", §02 "The fix: its own column, in a row that survives",
§02 "A race between two tabs", §05 "The backup: what travels, and under which key", §05 "Deletion:
'when the recycle bin is emptied' does not exist as a state", §05 "A configurable lifecycle driven by
a scheduled task".

### Traps

- **The snapshot must not live on `enrol_apply_applicationinfo`.** That row is deleted on approval
  (`lib.php:241`), on cancellation (`lib.php:598`) and in `unenrol_user()` (`lib.php:899`) — a
  snapshot there self-destructs at the moment it acquires audit value. Worse,
  `classes/hook_callbacks.php:68` uses the row's **existence** as proof that a status change to
  active was an approval: if the row stops being deleted, `complete_approval()` fires again on every
  status edit of an already-approved user, rewriting groups and requeueing the confirmation message.
  **Leave that table and all four deletion paths untouched.**
- **The key cannot be `userenrolmentid`.** `unenrol_user()` deletes by that key
  (`lib.php:891-903`), and `cancel_applications()` calls `unenrol_user()` (`lib.php:597-598`). A
  "cancelled" audit entry would be destroyed by the very cancellation it exists to record. The key is
  `courseid` + `userid`; `enrolid` rides along for the backup and `userenrolmentid` is **reference
  only, never the key to anything**.
- **The `UNIQUE (courseid, userid)` key is not decoration.** Two simultaneous submissions both pass
  the already-applied `record_exists` and both reach `apply()`. Today the `foreign-unique` key
  `userenrolment` on `enrol_apply_applicationinfo` is what makes the second insert blow up; the new
  table needs its own.
- **`delete_instance()` stops deleting the audit rows.** This **inverts current cleanup behaviour**
  and must ship documented and with a mutation test. It stays responsible for
  `enrol_apply_applicationinfo` and `enrol_apply_groups`.
- **The backup goes in the `users` block, not `logs`.** Both `logs` defaults are 0 and `users`
  **locks** `logs`, making the `logs` gate strictly narrower — it would restore the comments while
  dropping the record of the decisions taken on them. And with users left out, `process_enrol()`
  converts the instance to manual and `restore_instance()` is **never called** at all.
- **Therefore a "back up without users, assert the trail did not come across" test passes
  vacuously.** It must assert that **no apply instance exists** in the restored course.
- **`tests/backup_test.php` needs `MODE_SAMESITE` plus an explicit unzip.** A `MODE_IMPORT` backup
  contains no `enrolments.xml` at all (`backup_course_task` skips the step outside a real backup) and
  a `MODE_GENERAL` backup is zipped, so `restore_controller::get_plan()` returns null until the
  archive is extracted into the plan basepath. Both are handled in
  `backup/moodle2/tests/moodle2_test.php::prepare_for_enrolments_test()` (`:577`, both branches) —
  **not** in the `backup_and_restore()` helper in the same file (`:475`).
- **When a user mapping fails on a cross-site restore, drop the row.** An ownerless profile snapshot
  is not an audit trail, it is loose personal data.
- **Pseudonymise in `\core_course\hook\before_course_deleted`, not in the `course_deleted` event.**
  `contextlist::add_from_sql()` wraps every provider query in a JOIN against `{context}`, and the
  course context is destroyed **before** `course_deleted` fires. A retained row is therefore invisible
  to subject access and unreachable by erasure — silently. Pseudonymising means zeroing `userid` and
  `decidedby` **and discarding the snapshot** (`userinfodata` and `comment` emptied), keeping only
  dates and status.
- **The `course_deleted` observer is the safety net for one case only: the plugin disabled.**
  `enrol_course_delete()` (`lib/enrollib.php:1172`, both branches) calls `delete_instance()` only for
  plugins in `enrol_get_plugins(true)` (`:1177`, `:1189-1191`) and then deletes the `enrol` and
  `user_enrolments` rows anyway (`:1193-1195`). Observers are registered by every installed plugin,
  enabled or not. **The observer normally finds zero rows, so its test needs a control row in another
  course that must survive** — otherwise it passes without exercising anything.
- **`delete_instance()` covers a case no observer sees**: restoring over an existing course deleting
  its content. The course is not deleted, so `course_deleted` never fires. Both routes are needed.
- **A new `db/events.php` and a new `db/hooks.php` entry both require a version bump** or the
  callbacks never register.
- **Sweep on `timecreated`, not `timedecided`.** An undecided row carries `timedecided = 0`; a sweep
  on that column would retain exactly the abandoned applications forever.
- **The task runs in chunks with a time budget, logs, skips a bad row, and never throws.** A single
  poisoned row must not freeze the whole retention. (Note: `~/dev/CLAUDE.md`'s claim that a throwing
  ad-hoc task is retried *forever* is wrong on both branches — `attemptsavailable` is decremented and
  exhausted rows are deleted after four weeks. The operational advice stands regardless; the fleet
  doc fix is assigned to slice 9.)
- **An erasure request deletes the audit row.** Erasure wins over permanence; the trail is
  deliberately not tamper-evident against the data subject. Record that in the README.
- **`tests/privacy/provider_test.php:108-109`** asserts `assertCount(1, $items)` **and**
  `assertEquals('enrol_apply_applicationinfo', $items[0]->get_name())`. Any new table fails **every
  CI leg** on both lines. Rewrite the second as an order-independent check over the collected names.
- **`classes/privacy/provider.php:138-147` exports every application in a context to the same
  path** — with two apply methods in one course, the second export overwrites the first. It is
  already a defect today; fix it while the file is open.
- **Two wrong sentences in the repo.** `CLAUDE.md` and `classes/hook_callbacks.php:44-46` both state
  the observer "deliberately does not notify". It **does** notify: it calls `complete_approval()`,
  which queues `\enrol_apply\task\notify_approval` (`lib.php:249-252`). Fix both here — it is exactly
  the kind of sentence a reviewer leans on.
- **`db/install.xml`'s `VERSION` attribute (line 2) is a date stamp.** Validate with
  `xmllint --noout --schema /Users/uaiblaine/dev/moodle-502/public/lib/xmldb/xmldb.xsd db/install.xml`.
- **`$DB->get_records()` returns strings under both drivers.** Cast to `(int)` where typing matters.
  A named placeholder may appear only once per statement — `time()` compared against two columns
  needs two names.
- **The privacy provider is not a cleanup mechanism.** Core never calls
  `delete_data_for_all_users_in_context()` when deleting a course or context — only `tool_dataprivacy`
  does. It is a separate obligation, now covering two roles.

### Verification

1. `xmllint --noout --schema /Users/uaiblaine/dev/moodle-502/public/lib/xmldb/xmldb.xsd db/install.xml`.
2. `mdl upgrade m502 && mdl phpunit-init m502 && mdl behat-init m502`. Repeat on m501.
3. Extend `tests/lib_test.php`:
   - `test_a_submission_row_survives_approval` — apply, approve; assert the
     `enrol_apply_applicationinfo` row is **gone** (control: the existing behaviour still holds) and
     the `enrol_apply_submission` row is **present** with `status` = approved, `timedecided` > 0,
     `decidedby` = the approver, and `outcomemessage` empty.
   - `test_a_submission_row_survives_cancellation` — the row exists with status cancelled after
     `cancel_enrolment()`, which calls `unenrol_user()`.
   - `test_a_submission_row_survives_unenrolment`.
   - `test_delete_instance_keeps_the_submission_rows` — **the mutation test for the inverted
     behaviour.** Control: the `enrol_apply_groups` and `enrol_apply_applicationinfo` rows for that
     instance **are** gone.
   - `test_a_second_application_for_the_same_course_and_user_is_rejected` — assert the unique key
     throws.
   - `test_the_out_of_band_approval_path_still_short_circuits` — drive `update_user_enrol()` on an
     already-approved user; assert `complete_approval()` did **not** run again (no second queued
     `notify_approval`, groups unchanged). This is the guard that the untouched
     `enrol_apply_applicationinfo` marker provides, and it is why that table is not repurposed.
4. Extend `tests/backup_test.php`:
   - `test_the_audit_trail_travels_when_users_are_included` — `MODE_SAMESITE`, users on, explicit
     unzip; assert the restored course's submission rows match, including `decidedby`.
   - `test_no_apply_instance_exists_when_users_are_excluded` — the **non-vacuous** form: assert the
     restored course has no `enrol` row with `enrol = 'apply'`, not merely that no submission row
     exists.
   - `test_a_row_whose_user_mapping_fails_is_dropped` — assert no row with `userid = 0` is written,
     **and** that a row whose mapping succeeded in the same restore is present (the control).
5. Write `tests/task/purge_submissions_test.php`:
   - `test_the_sweep_removes_rows_older_than_the_retention` — seed one old decided row, one old
     **undecided** row (`timedecided = 0`), one recent row. Assert both old rows go and the recent
     one stays. The undecided row is what proves the sweep is on `timecreated`.
   - `test_retention_zero_keeps_everything`.
   - `test_a_bad_row_does_not_abort_the_sweep` — assert the task completes and the good rows are
     still swept.
6. Write `tests/hook_callbacks_test.php`:
   - `test_before_course_deleted_pseudonymises_the_rows` — assert `userid = 0`, `decidedby = 0`,
     `userinfodata` empty, `comment` empty, and `status`/`timecreated` preserved. **Control:** a
     submission row in **another** course is untouched.
   - `test_the_course_deleted_observer_cleans_up_when_the_plugin_is_disabled` — remove
     `enrol_apply` from `enrol_plugins_enabled`, delete the course, assert the rows are gone.
     **Control row in another course must survive** — without it the test passes while doing nothing.
7. Update `tests/privacy/provider_test.php`: `assertCount(2, $items)` and an order-independent name
   assertion; add `test_export_covers_the_decider_role`,
   `test_delete_for_user_removes_rows_where_they_decided`, and
   `test_two_apply_methods_in_one_course_export_to_distinct_paths` (the `:138-147` defect).
8. **Mutation check — `delete_instance()`.** Re-add the submission delete.
   `test_delete_instance_keeps_the_submission_rows` goes red and nothing else.
9. **Mutation check — the unique key.** Drop it from `install.xml` and reinstall;
   `test_a_second_application_for_the_same_course_and_user_is_rejected` goes red.
10. **Mutation check — the pseudonymisation hook.** Move the call from `before_course_deleted` to the
    `course_deleted` observer; `test_before_course_deleted_pseudonymises_the_rows` goes red (the
    context is gone by then).
11. **Mutation check — the snapshot discard.** In `before_course_deleted`, zero the ids but leave
    `userinfodata` intact; `test_before_course_deleted_pseudonymises_the_rows` goes red on the
    `userinfodata` assertion and nothing else.
12. **Mutation check — the restore drop.** Write `userid = 0` instead of dropping the row;
    `test_a_row_whose_user_mapping_fails_is_dropped` goes red and nothing else.
13. **Mutation check — the backup gate.** Move the submission element out of the `users` block;
    `test_the_audit_trail_travels_when_users_are_included` goes red.
14. **Mutation check — the sweep column.** Change `timecreated` to `timedecided` in the task; the
    undecided-row assertion in `test_the_sweep_removes_rows_older_than_the_retention` goes red.
15. `mdl phpunit m502 enrol_apply` **and** `mdl phpunit m501 enrol_apply` — the privacy compliance
    test lives in core and runs against the plugin on both.
16. Manual: apply, approve, then
    `psql -h localhost -p 5502 -U moodle -c "select * from mdl_enrol_apply_submission"` — one row,
    with the snapshot JSON. Delete the instance from
    `http://localhost:8502/enrol/instances.php?id=<courseid>` and re-run the query — the row is still
    there. Delete the course from `http://localhost:8502/course/delete.php?id=<courseid>` and re-run
    it — `userid`, `decidedby`, `userinfodata` and `comment` are empty, `status` and `timecreated`
    intact.
17. `mdl behat m502 @enrol_apply` — scenario count 3.
18. `mdl ci moodle-enrol_apply --matrix`.

**Done when.** The trail survives every deletion path except course deletion and erasure, travels in a
users-included backup, is reachable by both privacy roles, and is swept on `timecreated`.

---

## Slice 7 — System report in the course

**Goal.** A manager opens a real Report Builder report inside the course, with sorting, AJAX paging,
per-user persisted filters, a synchronous CSV/Excel download and a bulk action bar — and never sees a
profile value they are not entitled to.

**Depends on.** Slice 6 (the table the report reads) and slice 2 (the compat test).

### What this slice does and does not mask

The design's mockup shows one column per picked profile field, each disappearing for a reader who may
not see it. **Those per-field columns come from `enrol_apply_submission_field`, which is slice 10.**
In slice 7 there is no child table, so:

- **`set_is_available()` masking applies to the identity columns the plugin adds from core's `user`
  entity** (city, phone1, phone2, department, institution, idnumber…). A reader without
  `moodle/site:viewuseridentity` in the course gets **no column, no filter and no condition** for
  them. This is the rule that must never become a display callback: filtering and sorting are SQL and
  bypass callbacks entirely, so a reader recovers a masked value by adding a text filter and reading
  the row count, or simply by sorting. Under aggregation the callback also receives nulls, because
  `column::get_fields()` collapses the aggregated column into a single field.
- **The JSON snapshot is one `TYPE_LONGTEXT` column, not sortable and with no filter.** Because there
  is no SQL path into it, a *formatter* may legitimately drop fields the reader may not see — and
  that is the only place in this plugin where a formatter is an acceptable masking mechanism. Say so
  in a comment beside it.
- **Per-field columns, per-field filters and per-field conditions land with slice 10**, and the
  `set_is_available()` rule follows them there.

### Files

| File | Change |
|---|---|
| `classes/reportbuilder/local/entities/submission.php` | **New.** The entity: columns, filters, conditions. **Overrides `initialise()`.** |
| `classes/reportbuilder/local/formatters/submission.php` | **New.** The status formatter and the JSON snapshot printer. |
| `classes/reportbuilder/local/systemreports/course_applications.php` | **New.** `can_view()`, `initialise()`, `set_checkbox_toggleall()`, `set_default_per_page(30)`, `set_downloadable(true)`, `set_report_info_container()`. |
| `report.php` | **New.** `/enrol/apply/report.php?id=<instanceid>`. |
| `db/access.php` | **Extend** with `enrol/apply:viewreports`, `RISK_PERSONAL`, **explicit archetypes** (manager only). |
| `lib.php` | `get_action_icons()` (`:330`): a third `i/report` icon. **`add_course_navigation()` (`:421`): a second node.** That method is core's own per-instance extension point for enrol plugins — dispatched by `enrol_add_course_navigation()` at `lib/enrollib.php:473`, from `settings_navigation.php:470` (5.2) / `:511` (5.1). Do **not** add a file-scope `enrol_apply_extend_navigation_course()`: it would fire for every course whether or not it has an apply instance, and duplicate the node. (For the record: `*_extend_settings_navigation()` is not dispatched for enrol plugins at all, and would pass the whole of CI while doing nothing.) |
| `info.php`, `info_table.php` | Refactor onto the same surface, keeping the URL and the `id` parameter. |
| `templates/` | Only if the sticky footer bar needs a template of its own. |
| `amd/src/bulk_actions.js` + `amd/build/…` | **New**, only if the bulk bar is not fully served by `core/checkbox-toggleall`. |
| `lang/en/…`, `lang/pt_br/…` | `apply:viewreports`, `entity:submission`, `report:course_applications`, one label per column and filter, the three status labels. |
| `version.php`, `CHANGELOG.md` | Bump + entry. |
| `tests/reportbuilder/course_applications_test.php` | **New.** |

**This slice does not touch `manage.php`, `manage_table.php` or `templates/manage.mustache`.** The
queue's own bulk bar is modernised in slice I, together with the Behat scenarios that drive it.

### The eight filters (from §06's table)

| Filter | Class | Where |
|---|---|---|
| Applicant | `filters\user` | course + site |
| Status | `filters\select`, three explicit options | course + site |
| Submitted on | `filters\date` | course + site |
| Decided on | `filters\date` with `DATE_EMPTY` | course + site |
| Decided by | `filters\user`, name distinct from Applicant | course + site |
| Method | `filters\select`, **omitted entirely** when the course has one apply instance | course + site |
| Comment | `filters\text` | course + site |
| Course | `filters\course_selector` | **site only** — slice 8 |

The JSON snapshot gets **no filter** on either surface.

### Design references

§06 "A report in the course, a datasource at the site", §06 "`can_view()` is the whole gate", §06
"Report Builder traps between 5.1 and 5.2", §06 "Filters, pagination and lazy loading", §06
"Refactoring info.php", §02 "Mask by availability, not by callback", §10 "The 'bulk actions in a
dropdown' you liked".

### Traps

- **`can_view()` carries the whole gate — it is not defence in depth.** `report.php` does **not run
  on page 2**: every sort, filter and page re-instantiates the report through
  `core_reportbuilder_retrieve_report` (`lib/db/services.php:3106`; `:3100` on 5.1), which receives
  source, context, component, area, itemid and parameters straight from the client, calls only
  `validate_context()` and then `require_can_view()`. So `can_view()` must assert
  `$this->get_context()` is a `context_course` **and** require `enrol/apply:viewreports` on it.
- **Neither `get_parameter()` nor the persistent's `itemid` may scope the query.** The base condition
  comes from `$this->get_context()->instanceid` and from nothing else.
- **Mask with `set_is_available()`, per column, per filter and per condition — never with a display
  callback**, except on the unsortable, unfilterable snapshot column as set out above.
- **Mask every row of a masked field, never only those holding a value.** A marker that appears only
  where there is data is a presence oracle — a defect `local_groupdist` has already shipped and fixed
  (`local_groupdist/CHANGELOG.md:229-233`).
- **Always override `initialise()` on the entity.** It is `abstract public function initialise(): self;`
  on 5.1 (`reportbuilder/classes/local/entities/base.php:105`) and a concrete
  `public function initialise(): self {` on 5.2 (`:95`). A 5.2-style entity that only implements
  `get_available_columns()` is **fatal on 5.1**, and the default local run never sees it.
- **`add_all_from_entities()` differs between branches.** 5.1 iterates the registered entities and
  filters by name (`reportbuilder/classes/datasource.php:337-345`); 5.2 iterates **the array it
  receives, in the order given** (`:341-346`, passing each element straight to
  `add_all_from_entity(string|entity_base $entityname)` at `:325`). **Pass names** — an object does
  not match on 5.1 — **and register in the same order you pass them**, or column order differs by
  branch.
- **Never implement `get_default_table_aliases()`** — deprecated on 5.1, removed on 5.2. Aliases
  generate themselves.
- **Do not reuse core's `enrolment:status`.** `course_enrolment` derives its status in SQL and maps
  the value through `status_field::STATUS_NOT_CURRENT` → `participationnotcurrent`
  (`course/classes/reportbuilder/local/entities/enrolment.php:122`, `:146` on 5.2; `:163` on 5.1;
  formatter at `local/formatters/enrolment.php:48` / `:50`). `ENROL_APPLY_USER_WAIT = 2` therefore
  gets a legitimate core label that is wrong here — worse than a raw `2`, because it is invisible in
  review. **Own column and filter; keep core's out of the default columns.**
- **Never use `boolean_select` for status** — `ENROL_APPLY_USER_WAIT = 2` would vanish, which is the
  defect this repo's `CLAUDE.md` already documents.
- **Every bound parameter comes from `\core_reportbuilder\local\helpers\database::generate_param_name()`**
  (`:70` on both branches, prefix validated against `/^rbparam[\d]+/` from the constant at `:37`).
  **Column names are `PARAM_ALPHANUMEXT` and unique**, on pain of `coding_exception`.
- **`lang_string` validates in its constructor, inside `initialise()`** — every string must exist
  before the first PHPUnit run, and CI runs `--fail-on-warning`.
- **The JSON-printing callback keeps its first parameter untyped.**
  `datasource_stress_test_columns_aggregation()` applies every compatible aggregation, and
  `groupconcat` re-applies the callback value by value.
- **The entities to reuse:** `core_reportbuilder\local\entities\user` (there is **no** user entity
  under `core_user` on either branch — `reportbuilder/classes/local/entities/` holds only
  `base.php`, `course.php`, `user.php`), `core_enrol\reportbuilder\local\entities\enrol`, and
  `core_course\reportbuilder\local\entities\enrolment`.
- **The CSV export strips tags with no separator** — `<dt>City</dt><dd>Campinas</dd>` exports as
  `CityCampinas`. The callback must emit a literal separator.
- **Every value out of the snapshot goes through `format_string(..., ['escape' => false])`** before it
  reaches the formatter's output. `profile_field_textarea` is `PARAM_RAW`.
- **Filter values live in `{reportbuilder_user_filter}` keyed by (report, user).** A test that sets
  filters as admin and checks them as a teacher proves nothing.
- **`enrol/apply:viewreports` grants access to frozen profile values of every applicant in scope.**
  `db/access.php` declares no `riskbitmask` on any of its five capabilities today — defensible for
  `config` and `manage`, not for this. Declare `RISK_PERSONAL` and state the archetypes explicitly.
  **Inheriting the neighbours' `editingteacher` default by omission hands the report to every editing
  teacher.**
- **Do not copy `manage.php`'s `can_view()` into `info.php`.** `info.php`'s authorisation is narrower
  — course (`info.php:44-45`) or system (`:52-53`) only, without the mentor's user-context branch.
  Copying `manage.php` silently widens who reads the submitted comments.
- **Two structural losses in the `info.php` refactor, to accept explicitly.** The comment column
  header coming from `customtext2` is inexpressible (`column::set_title()` accepts only a
  `lang_string` — `reportbuilder/classes/local/report/column.php:165` — and the class is `final` at
  `:35`) — move it to `set_report_info_container()`
  (`reportbuilder/classes/local/report/base.php:935`; `:914` on 5.1). The A-Z initials bar is
  switched off in `reportbuilder/classes/table/system_report_table.php:170`
  (`$this->initialbars(false);`), offset by the user and text filters.
- **The bulk bar: use core's containers, not a hand-built one.**
  `system_report::set_checkbox_toggleall()` (`reportbuilder/classes/system_report.php:151`, both
  branches) over `\core\output\checkbox_toggleall`; `\core\output\sticky_footer` for the bar;
  `core/checkbox-toggleall` already disables every `[data-toggle="action"]` in the group until
  something is selected. `admin/amd/src/bulk_user_actions.js` is the jQuery-free precedent. Route the
  actions through the plugin's existing `confirm_enrolment()` / `wait_enrolment()` /
  `cancel_enrolment()`. **Do not hardcode a palette** the way `enrol_gapply` does (`#2b303b`,
  `#009688`) — that becomes a light slab on a dark page.
- **Declare the plugin's CSS tokens on every root the plugin paints**, including any core-relocated
  container (`core/modal`, `core/tooltip`, `core/sticky-footer`). An unresolved `var()` is not a
  fallback to the literal — the whole declaration is invalid at computed-value time.
- **The 5.1↔5.2 divergence only shows up under the matrix.** `mdl ci moodle-enrol_apply --matrix` on
  this slice specifically.

### Verification

1. `mdl upgrade m502 && mdl phpunit-init m502 && mdl behat-init m502`; same on m501. `mdl purge` both.
2. Write `tests/reportbuilder/course_applications_test.php` extending
   `\core_reportbuilder\tests\core_reportbuilder_testcase`
   (`reportbuilder/tests/classes/core_reportbuilder_testcase.php:35`, namespace at `:19`, both
   branches):
   - `test_can_view_requires_the_capability` — a user without `enrol/apply:viewreports` gets `false`.
     **Control:** with the capability, `true`.
   - `test_can_view_refuses_a_non_course_context` — instantiate with a system context; assert refusal.
   - `test_the_report_is_scoped_by_the_context_instanceid` — two courses with applications; assert
     only the context course's rows appear, and that passing a foreign `itemid` or parameter changes
     nothing.
   - `test_default_columns_and_filters` — assert the exact default column list and the seven
     course-level filters from the table above, **in order** (this is the 5.1↔5.2
     `add_all_from_entities()` ordering guard).
   - `test_an_identity_column_is_absent_without_viewuseridentity` — a reader without
     `moodle/site:viewuseridentity`; assert the column, the filter **and** the condition are all
     absent from `get_active_columns()` / the filter list. Not "the value is masked" — **absent**.
     **Control:** with the capability, all three are present.
   - `test_an_identity_column_is_absent_even_for_rows_that_hold_no_value` — the presence-oracle guard.
   - `test_the_snapshot_column_omits_fields_the_reader_may_not_see` — the formatter half.
   - `test_the_snapshot_column_has_no_filter_and_is_not_sortable` — the precondition that makes the
     formatter acceptable. If this goes red, the formatter masking is unsound.
   - `test_status_column_labels_the_waiting_list_correctly` — assert `ENROL_APPLY_USER_WAIT` renders
     the plugin's own label, **not** "Not current".
   - `test_status_filter_returns_waiting_list_rows` — the three-option filter must reach status 2.
   - `test_csv_export_separates_the_snapshot_fields` — assert the exported cell contains a literal
     separator between label and value.
   - `test_a_raw_angle_bracket_in_a_snapshot_value_does_not_break_the_report` — a
     `profile_field_textarea` holding `A<B`.
   - `test_course_filter_is_absent_from_the_course_report` — the other half of slice 8's split.
   - Call **both** core stress helpers: `datasource_stress_test_columns()` (`:72`) and
     `datasource_stress_test_columns_aggregation()` (`:111`). These are what catch the
     untyped-parameter rule and the missing-string rule; prose does not.
3. **Mutation check — `can_view()` capability.** Delete the `require_capability` /
   `has_capability` line. `test_can_view_requires_the_capability` goes red and nothing else.
4. **Mutation check — the context assertion.** Delete the `context_course` check;
   `test_can_view_refuses_a_non_course_context` goes red.
5. **Mutation check — the base condition.** Change it to read `get_parameter()` instead of
   `get_context()->instanceid`; `test_the_report_is_scoped_by_the_context_instanceid` goes red.
6. **Mutation check — masking.** Change `set_is_available(false)` on an identity column to a display
   callback; `test_an_identity_column_is_absent_without_viewuseridentity` goes red.
7. **Mutation check — the CSV separator.** Remove the literal separator;
   `test_csv_export_separates_the_snapshot_fields` goes red.
8. **Mutation check — `initialise()`.** Delete the entity's `initialise()` override and run
   `mdl phpunit m501 enrol_apply` — it must **fatal** on 5.1 while m502 stays green. Restore. This is
   the check the default local leg cannot make.
9. `mdl phpunit m501 enrol_apply` **and** `mdl phpunit m502 enrol_apply`, both green.
10. Manual: `http://localhost:8502/enrol/apply/report.php?id=<instanceid>` as a manager — columns,
    filters, sorting, AJAX page 2 (click through and confirm the rows change without a full reload),
    the download menu, and the bulk bar appearing with the selection. Then **as an editing teacher
    without the new capability** — expect a refusal, not a report.
11. Manual, the page-2 gate: with DevTools open, page to 2 and confirm the
    `core_reportbuilder_retrieve_report` request returns rows scoped to this course only.
12. `http://localhost:8502/enrol/apply/info.php?id=<instanceid>` still works at the same URL with the
    same `id` parameter; `mdl behat m502 @enrol_apply` still passes with **scenario count 3**.
13. `mdl ci moodle-enrol_apply --matrix` — **mandatory on this slice.**

**Done when.** The course report renders and pages under a capability that carries `RISK_PERSONAL`, a
reader without `moodle/site:viewuseridentity` sees no column, no filter and no condition for an
identity field, and both branches pass the Report Builder stress tests.

---

## Slice 8 — Site-level datasource

**Goal.** A site administrator can build ad-hoc custom reports across every course's applications,
using the same entity as the course report.

**Depends on.** Slice 7 (the entity and the formatters).

### Files

| File | Change |
|---|---|
| `classes/reportbuilder/datasource/applications.php` | **New.** `get_default_columns()`, `get_default_filters()`, `get_default_conditions()`, and `initialise()` registering the entities. |
| `classes/reportbuilder/local/entities/submission.php` | Add the `course_selector` filter path used at site level only. |
| `lang/en/…`, `lang/pt_br/…` | `datasource:applications`, plus any string the site-level filters introduce. |
| `version.php`, `CHANGELOG.md` | Bump + entry. **The version bump is what rebuilds the class map** — there is no `db/reportbuilder.php`. |
| `tests/reportbuilder/applications_datasource_test.php` | **New.** |

### Design references

§06 "A report in the course, a datasource at the site", §06 "Report Builder traps between 5.1 and
5.2", §06 filter table (the `Course` row).

### Traps

- **The datasource is discovered automatically from the path and namespace alone.** There is no
  `db/reportbuilder.php`. What there **is** is the need to bump `version.php` so the class map is
  rebuilt — forget it and the datasource simply does not appear, with no error.
- **`initialise()` again**: abstract on 5.1 (`entities/base.php:105`), concrete on 5.2 (`:95`).
  Override it.
- **`add_all_from_entities()` again**: pass **names**, register in the same order.
- **`courseid` is a base condition in the course report and a `course_selector` filter here.**
  Offering it as a filter inside the course report would let a manager page sideways into another
  course.
- **No filter on the JSON snapshot.** It would be a table scan and hostile to cross-DB. Ship it as a
  `TYPE_LONGTEXT` column, not sortable. Same `format_string(..., ['escape' => false])` rule as slice 7.
- **The "Method" filter disappears rather than becoming a one-option picker** when the course has a
  single apply instance.
- **`lang_string` validates in the constructor inside `initialise()`** — every new string exists
  before the first PHPUnit run.
- **The stress helpers are the gate**, not prose. Extend
  `\core_reportbuilder\tests\core_reportbuilder_testcase` and call both.
- **`$CFG->enablecustomreports` gates this surface but not slice 7's.** The course `system_report`
  keeps working on a site with custom reports off; say so in the CHANGELOG so nobody "fixes" the
  duplication by deleting one.

### Verification

1. `mdl upgrade m502` (the version bump rebuilds the class map), then `mdl phpunit-init m502`. Same on m501.
2. Write `tests/reportbuilder/applications_datasource_test.php`:
   - `test_datasource_is_discovered` — assert the class name appears in
     `\core_reportbuilder\manager::get_report_datasources()`
     (`reportbuilder/classes/manager.php:134`, same line on both branches; it is what feeds the
     source picker at `reportbuilder/classes/form/report.php:109`).
   - `test_default_columns_and_filters` — assert the expected sets, in order, including all eight
     filters from slice 7's table.
   - `test_course_filter_is_present_at_site_level`.
   - `test_datasource_stress_test_columns` and `test_datasource_stress_test_columns_aggregation` —
     both core helpers.
3. **Mutation check — `initialise()`.** Delete the override; `mdl phpunit m501 enrol_apply` fatals
   while m502 stays green.
4. **Mutation check — the course filter separation.** Add the `course_selector` filter to the course
   `system_report`; slice 7's `test_course_filter_is_absent_from_the_course_report` goes red.
5. **Mutation check — discovery.** Rename the class file out of the discovered path;
   `test_datasource_is_discovered` goes red.
6. `mdl phpunit m501 enrol_apply && mdl phpunit m502 enrol_apply`.
7. Manual: `http://localhost:8502/reportbuilder/index.php` → New report → the "Enrolment
   applications" source is offered; build a report, add every column, add each filter, sort on each
   sortable column, aggregate one column with `groupconcat`. Nothing throws.
8. Manual, negative: set `$CFG->enablecustomreports = false;` in the stack's `config.php`, confirm
   `http://localhost:8502/enrol/apply/report.php?id=<id>` **still works**, then revert.
9. `mdl ci moodle-enrol_apply --matrix` — **mandatory on this slice.**

**Done when.** The datasource is discovered on both branches, survives both stress helpers, and the
course/site filter split holds in both directions.

---

## Slice 9 — Asynchronous download

**Goal.** A manager exporting a large report gets a progress indicator, a notification when the file
is ready, and a download link that only they can use — instead of a PHP timeout.

**Depends on.** Slice 7 (the report and its download plumbing).

### Files

| File | Change |
|---|---|
| `classes/task/export_report.php` | **New.** Ad-hoc task using `\core\task\stored_progress_task_trait`; a static `create()` factory; `retry_until_success()` returning **false** (base at `lib/classes/task/adhoc_task.php:245`). |
| `classes/task/purge_exports.php` | **New.** Scheduled sweep of `{files}` by area. |
| `db/tasks.php` | **Extend.** Register `purge_exports`, daily. |
| `db/messages.php` | **Extend.** New `export_ready` message provider beside the existing `application`, `confirmation`, … providers. |
| `lib.php` | **New function `enrol_apply_pluginfile()`** at file scope. |
| `report.php` | Threshold branch: below it, synchronous; above it, queue and render `\core\output\task_indicator`. Plus the failure detection described below. |
| `classes/privacy/provider.php` | `add_subsystem_link('core_files', …)` (`privacy/classes/local/metadata/collection.php:94`) for the new file area, or core's compliance test fails. |
| `settings.php` | `enrol_apply/exportretention` (`admin_setting_configduration`, default 48 h) and `enrol_apply/asyncthreshold` (`admin_setting_configtext`, `PARAM_INT`). |
| `lang/en/…`, `lang/pt_br/…` | `asyncthreshold`, `asyncthreshold_desc`, `exportfailed`, `exportinprogress`, `exportready`, `exportretention`, `exportretention_desc`, `messageprovider:export_ready`, `privacy:metadata:core_files`, `task_export_report`, `task_purge_exports`. |
| `~/dev/CLAUDE.md` | Fix "a throwing ad-hoc task is retried forever" — `attemptsavailable` is decremented and exhausted rows are deleted after four weeks. (Design's "Corrections this work found in existing docs", item 4. This is the commit that depends on the correct semantics.) |
| `version.php`, `CHANGELOG.md` | Bump + entry. |
| `tests/task/export_report_test.php`, `tests/pluginfile_test.php` | **New.** |

### Design references

§06 "Asynchronous download", including the "Recommended order", "The hole in the asynchronous path is
failure, not success" box and the seven-row detail table.

### Traps

- **`\core\dataformat::write_data_to_filearea()` appends the extension itself.** The call is at
  `lib/classes/dataformat.php:161` (both branches) and the appending happens one frame deeper, in
  `write_data()` at `:128`: `make_request_directory() . '/' . $filename . $format->get_extension()`.
  Passing `audit.csv` produces `audit.csv.csv`.
- **The file goes in its own area in the user context of whoever asked**, so one manager's export is
  never fetchable by another. **Do not use the draft area** — it is swept after 4 days and you do not
  control that.
- **The progress indicator matches on classname + component + customdata + userid.** Queueing and
  displaying must build the task through the **same static `create()`**, or the indicator finds
  nothing and the page looks broken while the export runs fine.
  (`\core\output\task_indicator::__construct()` takes the `adhoc_task` itself, `lib/classes/output/task_indicator.php:63`.)
- **`queue_adhoc_task($task, true)` returns `false` — not an id — when an identical task already
  exists.** That is "already running", not an error. `set_id(false)` would write a garbage progress
  record.
- **The hole is failure, not success.** `task_indicator` looks the task up with
  `$includefailed = true` by default (`\core\task\manager::get_queued_adhoc_task_record($task, bool $includefailed = true)`,
  `lib/classes/task/manager.php:200`, both branches). An exhausted task survives four weeks in the
  queue while the progress record is deleted after 24 h — so the page says "your export is in
  progress" indefinitely, with no bar and no final state. **The page must call
  `get_queued_adhoc_task_record($task, false)` itself and treat "no live task and no file" as
  failure**, with a message of its own.
- **Override `retry_until_success()` to `false`.** With the default, a task that fails after writing
  the file either collides on the same `pathnamehash` (permanent failure, 12 attempts, backoff up to
  24 h) or — if the name carries a timestamp — leaves up to 12 orphan copies, each firing its own
  notification.
- **`file_pluginfile()` does no login and no capability check on the generic branch.** That branch is
  `lib/filelib.php:5387-5399` on 5.2 — the `} else { // try to serve general plugin file in arbitrary
  context` arm that ends in `$filefunction($course, $cm, $context, $filearea, $args, $forcedownload,
  $sendfileoptions);`. (Do **not** confuse it with `:5374`, which is the block-instance branch and
  does check `moodle/block:view`.) Everything therefore sits in `enrol_apply_pluginfile()`:
  `require_login()`, ownership of the user context, a re-check of **`enrol/apply:viewreports`** in
  the course the export was taken from, a validity check, then `send_stored_file()`. The design says
  `manageapplications`; `viewreports` is chosen because it is the capability that gated producing the
  file and it is strictly narrower. `enrol_gapply` gets this whole area wrong and it is a real, not
  theoretical, disclosure.
- **`readfile()` / `echo file_get_contents()` bypass X-Sendfile**, which is enabled fleet-wide. Serve
  through `send_stored_file()`.
- **A new file area needs `add_subsystem_link('core_files', …)` in the privacy provider** or core's
  compliance test fails.
- **The 48-hour cleanup is a duration setting, not a constant** — which is what `tool_dataprivacy`
  does.
- **Do not throw from the ad-hoc task on a permanent failure.** mtrace, message the requester, and
  return.
- **The export task must run as the user who asked for it**, or `{reportbuilder_user_filter}` — keyed
  by (report, user) — yields the **unfiltered** report.
- **Ship the synchronous path first** (slice 7's `set_downloadable(true)`), gate this one on a
  row-count threshold, and keep the sync path working below it.

### Verification

1. `mdl upgrade m502 && mdl phpunit-init m502 && mdl behat-init m502`; same on m501.
2. Write `tests/task/export_report_test.php`:
   - `test_the_exported_filename_has_exactly_one_extension` — assert the stored file's name ends in
     `.csv` and not `.csv.csv`.
   - `test_the_file_lands_in_the_requesting_users_context` — assert the `contextid` is that user's
     `context_user`.
   - `test_the_export_honours_the_requesting_users_filters` — set a filter as user A, run the task as
     A, assert the row count matches the filtered report. **Control:** as user B with no filter, the
     count is the unfiltered one.
   - `test_retry_until_success_is_false`.
   - `test_a_second_identical_queue_returns_false_and_is_not_treated_as_an_error`.
   - `test_the_purge_task_removes_files_past_the_retention` — one old file, one recent; assert only
     the old one goes.
   - `test_report_page_reports_a_dead_export_as_failed` — queue an export, delete the ad-hoc row,
     assert the page's state helper returns "failed", not "in progress". **Written now, not at
     mutation time.**
3. Write `tests/pluginfile_test.php`:
   - `test_pluginfile_refuses_a_user_who_is_not_the_owner` — user B requesting A's file is refused.
   - `test_pluginfile_refuses_a_user_who_lost_the_report_capability` — A owns the file but
     `enrol/apply:viewreports` has been revoked in the course; refused. **Control:** with the
     capability, served.
   - `test_pluginfile_requires_login`.
4. **Mutation check — each `enrol_apply_pluginfile()` guard.** Delete the ownership check, then the
   capability re-check, then `require_login()`, one at a time. Exactly the matching named test goes
   red each time.
5. **Mutation check — the failure branch.** Delete the `get_queued_adhoc_task_record($task, false)`
   call from `report.php`; `test_report_page_reports_a_dead_export_as_failed` goes red and nothing
   else.
6. **Mutation check — the filename.** Pass `audit.csv` instead of `audit`;
   `test_the_exported_filename_has_exactly_one_extension` goes red.
7. **Mutation check — the acting user.** Run the task as admin rather than the requester;
   `test_the_export_honours_the_requesting_users_filters` goes red.
8. Manual: seed more than `asyncthreshold` submission rows (a CLI script in the scratchpad, run
   through `mdl sh m502`), then click Download on
   `http://localhost:8502/enrol/apply/report.php?id=<id>`. Confirm the progress indicator appears,
   the cron sidecar runs the task within ~60 s, a message arrives (Mailpit at
   `http://localhost:8502/_/mail`), and the link downloads a single-extension file.
9. Manual, negative: copy the download URL and open it while logged in as **another** manager — expect
   a refusal.
10. Manual, failure path: queue an export, then delete the ad-hoc task row directly
    (`psql -h localhost -p 5502 -U moodle -c "delete from mdl_task_adhoc"`), reload the report page —
    expect the failure message, **not** "in progress" forever.
11. `mdl phpunit m501 enrol_apply && mdl phpunit m502 enrol_apply`.
12. `mdl ci moodle-enrol_apply --matrix`.

**Done when.** A large export runs in the background with a real progress state, a real failure state,
and a file only its owner can fetch.

---

## Slice I — Decision-time enrolment parameters, the outcome message, and the modern queue

**Goal.** An approver chooses the role, the enrolment dates and the groups **at approval time**, and
types a message the applicant reads — instead of everything being frozen on the instance.
Previous/next navigation lets them work through the queue, and the queue's bulk bar moves onto core's
own containers.

**Depends on.** Slice 6. The durable row is where the chosen parameters and the outcome message are
recorded; without it there is nowhere to put them.

### Files

| File | Change |
|---|---|
| `classes/form/decision_form.php` | **New.** `\core_form\dynamic_form` carrying role, `timestart`, `timeend`, groups and the outcome message. |
| `lib.php` | `confirm_enrolment()` (`:497`) accepts the decision parameters instead of stamping `time()` and the instance period. `complete_approval()` (`:235`) uses the chosen group list, not the frozen one. `can_manage_application()` (`:470`) re-checked **per posted id**. |
| `classes/local/submission.php` | Store the chosen parameters and write `outcomemessage` on the row. |
| `manage.php`, `manage_table.php` | Open the decision form; previous/next navigation; the bulk bar moved onto `\core\output\checkbox_toggleall` + `\core\output\sticky_footer`. **Constructor signature of `enrol_apply_manage_table` unchanged** (`manage.php:126`, `tests/lib_test.php:341`). |
| `renderer.php`, `templates/manage.mustache` | Wire the form, the navigation and the new bar. The "Go" button at `manage.mustache:65` is replaced by a `[data-toggle="action"]` control that core disables until something is selected. |
| `amd/src/manage.js` + `amd/build/manage.min.js` + `.map` | **Extend** (the module exists — select-all handling). Add the modal opener; rebuild with `mdl grunt m502 enrol/apply` and commit source and build together. |
| `classes/reportbuilder/local/systemreports/course_applications.php` | Update the slice-7 bulk bar to the new `confirm_enrolment()` signature so both surfaces stay identical. |
| `db/messages.php` | The outcome message travels in the existing approval/waiting/cancel messages — extend their bodies rather than adding providers. |
| `lang/en/…`, `lang/pt_br/…` | `decisiondates`, `decisiongroups`, `decisionrole`, `nextapplication`, `outcomemessage_help`, `previousapplication`, `somenotauthorised`. |
| `tests/behat/enrol_apply.feature` | **Scenarios 2 and 3 rewritten in this commit.** They drive `I set the field "Select Student 1" to "1"`, `I set the field "With selected users..." to "Confirm requests"` / `"Cancel requests"`, `I press "Go"` and `I should see "Nothing to display"` — every one of those labels belongs to the markup this slice replaces. |
| `version.php`, `CHANGELOG.md` | Bump + entry. **Name `enrol_gapply` in the CHANGELOG** — this slice is informed by it. |
| `tests/form/decision_form_test.php` | **New.** |
| `tests/lib_test.php` | Extend, and update `test_confirm_enrolment_applies_the_enrolment_period` (`:394`) for the new signature. |

### Design references

§10 "The four ideas that really are worth it all adopted" (rows 1 and 2), §10 "Adopt idea 1 without
inheriting the privilege escalation", §10 "The 'bulk actions in a dropdown' you liked", slice I in §09.

### Decided semantics for a mixed bulk post

When a posted id list contains ids the operator may not manage: **the authorised ids are decided, the
unauthorised ids are skipped**, and the redirect carries a warning naming how many were skipped
(`somenotauthorised`). Not "all or nothing", and not a thrown exception — an approver working a queue
must not be blocked by one stale id. The test asserts **both** halves.

### Traps

- **`enrol_gapply`'s `roleid` is a privilege escalation.** It arrives as `PARAM_INT` defaulting to 0
  and goes straight into `enrol_user()` and from there into `role_assign()`, which does **no
  assignability check at all** — it only confirms the role and the user exist. Their
  `get_assignable_roles()` check is client-side only, and their management capability is
  `editingteacher` by default: any editing teacher can assign themselves manager by forging an AJAX
  call. **Allowlist the `roleid` server-side against `get_assignable_roles($coursecontext)`.** The
  right pattern is core's own: **`enrol/manual/externallib.php:98-104`** — the same line on 5.1 and
  5.2. (There is no `enrol/manual/classes/external.php` on either branch; `enrol/manual/classes/`
  holds only `enrol_users_form.php`, `privacy/`, `task/` and `user_enrolment_callbacks.php`.)
- **Allowlist the groups server-side too**, against `groups_get_all_groups($courseid)`.
- **`groups_add_member($gid, $uid, 'enrol_apply', $instance->id)`** — with the component stamp
  `enrol_gapply` omits. Core's `unenrol_user()` deletes `groups_members` rows by component and
  itemid; dropping the stamp leaves memberships behind whenever the user has another enrolment.
- **Re-authorise per row.** `can_manage_application()` runs for **every posted id**, not once for the
  page. The bulk form posts a list of arbitrary ids — this is already the rule in `lib.php` and it
  does not relax here.
- **Never put a `timeend` on a pending application.** The decision form stamps the period at
  approval; a `timeend` on a pending or waiting-list row is swept by the
  `ENROL_EXT_REMOVED_UNENROL` branch of `enrol_plugin::process_expirations()`, which selects on
  `timeend > 0 AND timeend < now` **with no status filter**. Pinned today by
  `tests/lib_test.php:118::test_pending_application_is_not_reachable_by_the_expiry_sweep` — that test
  must stay green.
- **Put every new post-approval side effect in `complete_approval()`, never inline in
  `confirm_enrolment()`.** Core's participants page "Edit enrolment" posts to
  `enrol/editenrolment.php`, which requires only `enrol/apply:manage` and drives
  `update_user_enrol()` directly. `classes/hook_callbacks.php` observes
  `\core_enrol\hook\before_user_enrolment_updated` and calls `complete_approval()` whichever route
  was taken. Anything added to only one path drifts.
- **The out-of-band route has no decision parameters.** It must fall back to the instance defaults,
  not to nulls — and that fallback needs a test.
- **`\core_form\dynamic_form` carries file pickers, `hideIf` and YUI fine**, but core forms'
  `definition_after_data()` usually reads `getElementValue('id')`; if the modal's hidden transport is
  named anything else, a verbatim copy silently finds no record.
- **`ENROL_APPLY_USER_WAIT = 2` is invisible to core.** Any query the queue writes must filter on
  `status != ENROL_USER_ACTIVE`, never `status = ENROL_USER_SUSPENDED`.
- **The plugin's `enrol_plugin::get_config()` memoises.** Use `$plugin->set_config()` in tests.
- **Behat:** controls inside a collapsed dropdown or a sticky footer exist but are not interactable —
  open the container first, and re-open after any pane reload. Icon-only buttons need an
  `aria-label`. `.visually-hidden` text is real page text for `I should see`.
- **`tests/lib_test.php` is 545 lines today and goes past 1200.** Split the decision tests into
  `tests/form/decision_form_test.php` rather than letting one file carry everything.

### Verification

1. `mdl grunt m502 enrol/apply`, `mdl upgrade m502 && mdl phpunit-init m502 && mdl behat-init m502`, `mdl purge m502`. Same on m501.
2. Write `tests/form/decision_form_test.php`:
   - `test_a_role_outside_get_assignable_roles_is_rejected` — post a `roleid` the approver may not
     assign (manager, as an editing teacher); assert the submission is refused **and** no
     `role_assignments` row was created. **Control:** an assignable role succeeds.
   - `test_a_group_outside_the_course_is_rejected`.
   - `test_every_posted_id_is_re_authorised` — post two ids, one of which the approver may not
     manage; assert the authorised one **is** decided, the unauthorised one is **not**, and the
     result reports one skipped.
   - `test_group_membership_carries_the_component_stamp` — assert
     `{groups_members}.component = 'enrol_apply'` and `itemid = $instance->id`.
   - `test_the_chosen_period_is_stamped_on_approval_only` — assert the pending row had
     `timestart = 0, timeend = 0` and the approved row carries the chosen values.
   - `test_the_outcome_message_reaches_the_applicant` — `$this->redirectMessages()`; assert the body
     contains the typed text.
   - `test_the_outcome_message_is_stored_on_the_submission_row` — and that it was empty before, which
     slice 6's baseline test already asserts.
   - `test_the_out_of_band_approval_falls_back_to_the_instance_defaults` — drive
     `update_user_enrol()`; assert role and period come from the instance, not null.
3. **Mutation check — the role allowlist.** Delete the `get_assignable_roles()` check;
   `test_a_role_outside_get_assignable_roles_is_rejected` goes red and nothing else.
4. **Mutation check — the group allowlist.** Same for `groups_get_all_groups()`.
5. **Mutation check — the per-row re-authorisation.** Delete the `can_manage_application()` call from
   the decision path; `test_every_posted_id_is_re_authorised` goes red, and
   `test_confirm_enrolment_requires_the_capability` (`:473`) /
   `test_confirm_enrolment_is_scoped_to_the_course` (`:511`) must still hold their existing contract.
6. **Mutation check — the component stamp.** Drop the third and fourth arguments to
   `groups_add_member()`; `test_group_membership_carries_the_component_stamp` goes red.
7. **Mutation check — the out-of-band fallback.** Make it pass nulls;
   `test_the_out_of_band_approval_falls_back_to_the_instance_defaults` goes red.
8. Confirm `test_pending_application_is_not_reachable_by_the_expiry_sweep` is still green — this
   slice's period handling is the exact thing that could break it.
9. Re-run `tests/reportbuilder/course_applications_test.php` — the report's bulk bar shares the
   changed signature.
10. Rewrite Behat scenarios 2 and 3 against the new bar. Run `mdl behat m502 @enrol_apply` and
    **check the scenario count is 3**.
11. Manual: `http://localhost:8502/enrol/apply/manage.php?id=<instanceid>` as an **editing teacher**.
    Open a decision, confirm the role select offers only assignable roles (no "Manager"). Approve with
    a message; check Mailpit for the message body. Use previous/next to walk the queue. Confirm the
    bulk action control is disabled until a row is selected.
12. Manual, negative: with DevTools, forge the `roleid` in the modal's POST to the manager role id —
    expect a refusal, and verify with
    `psql -h localhost -p 5502 -U moodle -c "select * from mdl_role_assignments where userid=<id>"`.
13. `mdl phpunit m501 enrol_apply && mdl phpunit m502 enrol_apply`.
14. `mdl ci moodle-enrol_apply --matrix --behat`.

**Done when.** An approver sets role, dates, groups and a message per decision; a forged role or group
is refused server-side; both approval routes leave identical state; and all three Behat scenarios pass
against the new queue.

---

## Slice J — Bulk actions on the participants page

**Goal.** A manager can approve, defer or cancel applications from core's participants page, using
core's own bulk-operation extension point.

**Depends on.** Slice I. The decision path must be proved first — this slice reuses it wholesale.

### Files

| File | Change |
|---|---|
| `lib.php` | Implement `get_bulk_operations(course_enrolment_manager $manager)` — the extension point at `lib/enrollib.php:2985` (`:2993` on 5.1) that this plugin inherits empty today. `course_enrolment_manager` and `enrol_bulk_enrolment_operation` both live in **`enrol/locallib.php`**, not `lib/enrollib.php`: `require_once($CFG->dirroot . '/enrol/locallib.php')` where needed. |
| `classes/bulk/confirm_operation.php` | **New.** `\enrol_apply\bulk\confirm_operation extends \enrol_bulk_enrolment_operation`. |
| `classes/bulk/wait_operation.php` | **New.** |
| `classes/bulk/cancel_operation.php` | **New.** |
| `classes/form/bulk_decision_form.php` | **New.** The bulk variant of slice I's decision form. |
| `lang/en/…`, `lang/pt_br/…` | `bulkcancel`, `bulkconfirm`, `bulkwait`, plus confirmation strings. |
| `version.php`, `CHANGELOG.md` | Bump + entry. |
| `tests/bulk/operations_test.php` | **New.** |
| `tests/behat/enrol_apply.feature` | One thin scenario added — scenario count goes to 4. |

### Design references

§09 slice J, §10 "The 'bulk actions in a dropdown' you liked — core already has them", and the closing
paragraph of §09 "Scope closed".

### Traps

- **`enrol_self`'s bulk operation writes `{user_enrolments}` with a raw `UPDATE` and builds the event
  by hand**, so `\core_enrol\hook\before_user_enrolment_updated` is **not dispatched** — and this
  plugin's out-of-band approval observer (`classes/hook_callbacks.php`) would not fire. **Do not copy
  it.** Route every bulk decision through `confirm_enrolment()` / `wait_enrolment()` /
  `cancel_enrolment()`, or through `update_user_enrol()`, so `complete_approval()` runs exactly once.
- **`ENROL_APPLY_USER_WAIT = 2` is invisible to core.** Any query the bulk operation writes must
  filter on `status != ENROL_USER_ACTIVE`, never `status = ENROL_USER_SUSPENDED`, or deferred
  applications vanish.
- **Per-row re-authorisation again**, with slice I's decided semantics: authorised ids decided,
  unauthorised skipped and counted.
- **Do not "fix" duplication by prohibition.** `enrol_gapply`'s `can_add_instance()` forbids a second
  instance in the course — it gets it wrong twice, forbidding the use case **and** failing to close
  the hole, because restore and course copy go around it.
- **Behat: `.visually-hidden` text is real page text**, and controls inside a collapsed dropdown exist
  but are not interactable — open the container first, and re-open after any pane reload. Icon-only
  buttons need an `aria-label`.
- **Keep Behat thin.** Logic belongs in PHPUnit; no drag-drop, no tree-expand, no infinite scroll.
- **`amd/build/**` ships with `amd/src` in the same commit** if any JS is touched.

### Verification

1. `mdl upgrade m502 && mdl phpunit-init m502 && mdl behat-init m502`, `mdl purge m502`. Same on m501.
2. Write `tests/bulk/operations_test.php`:
   - `test_bulk_confirm_runs_complete_approval_exactly_once` — assert exactly one queued
     `notify_approval` per user and that the group memberships were written **with the component
     stamp**. This is the guard against the `enrol_self` raw-UPDATE pattern.
   - `test_bulk_operations_include_waiting_list_rows` — seed a `status = 2` row; assert it appears in
     the operation's candidate set. **Control:** a `status = 0` (active) row does not.
   - `test_bulk_confirm_re_authorises_every_posted_id` — one id the operator may not manage; assert it
     is not decided while the authorised one is, and that one skip is reported.
   - `test_get_bulk_operations_is_empty_without_the_capability`.
3. **Mutation check — the status filter.** Change the candidate query to
   `status = ENROL_USER_SUSPENDED`; `test_bulk_operations_include_waiting_list_rows` goes red.
4. **Mutation check — the decision route.** Replace the `confirm_enrolment()` call with a raw
   `$DB->set_field('user_enrolments', 'status', …)`;
   `test_bulk_confirm_runs_complete_approval_exactly_once` goes red on both the message and the group
   assertion.
5. **Mutation check — the per-row check.** Delete `can_manage_application()` from the bulk path;
   `test_bulk_confirm_re_authorises_every_posted_id` goes red.
6. **Mutation check — the capability gate.** Delete the capability check from
   `get_bulk_operations()`; `test_get_bulk_operations_is_empty_without_the_capability` goes red.
7. Behat: one scenario — teacher opens `http://localhost:8502/user/index.php?id=<courseid>`, selects
   two pending applicants, chooses the plugin's bulk action from the "With selected users..." menu,
   confirms, and the applicants appear as active. Run `mdl behat m502 @enrol_apply` and **check the
   scenario count is 4**.
8. Manual: the same flow by hand, then confirm in Mailpit that exactly one approval message per user
   was sent — not two, not zero.
9. `mdl phpunit m501 enrol_apply && mdl phpunit m502 enrol_apply`.
10. `mdl ci moodle-enrol_apply --matrix --behat`.

**Done when.** Bulk decisions from the participants page produce byte-identical state to the plugin's
own queue, including the notification and the component-stamped group memberships.

---

## Cross-cutting checklist — before every push

```sh
# 1. The phpcs trap that keeps costing a CI round.
rg -U -n 'class [^\n]*\{\n\n' --glob '*.php' .            # expect no matches

# 2. Lang files in lockstep, alphabetical.
diff <(grep -o "^\$string\['[^']*'\]" lang/en/enrol_apply.php | sort) \
     <(grep -o "^\$string\['[^']*'\]" lang/pt_br/enrol_apply.php | sort)   # expect empty
grep -o "^\$string\['[^']*'\]" lang/en/enrol_apply.php | sort -c           # expect no error

# 3. Schema, if touched. VERSION on line 2 is a YYYYMMDD DATE, not $plugin->version.
xmllint --noout --schema /Users/uaiblaine/dev/moodle-502/public/lib/xmldb/xmldb.xsd db/install.xml

# 4. AMD, if touched.
mdl grunt m502 enrol/apply       # and commit amd/build/*.min.js + *.map with amd/src

# 5. Test sites re-inited after the version bump (one stack per call).
mdl upgrade m502 && mdl phpunit-init m502 && mdl behat-init m502
mdl upgrade m501 && mdl phpunit-init m501 && mdl behat-init m501

# 6. Tests on BOTH branches.
mdl phpunit m501 enrol_apply && mdl phpunit m502 enrol_apply
mdl behat m502 @enrol_apply      # judge by the scenario count, not the exit status

# 7. The real gate.
mdl ci moodle-enrol_apply --matrix
```

Also confirm, every time:

- [ ] `version.php` bumped, `CHANGELOG.md` entry in the **same commit**, the
      `upgrade_plugin_savepoint()` number **equal to** `$plugin->version`, and `db/install.xml`'s
      `VERSION` date stamp moved if and only if the schema changed.
- [ ] `.github/workflows/ci.yml` untouched — `$plugin->supported` does not change in any slice here.
- [ ] Every new capability declares its `riskbitmask` and **explicit** archetypes, and has a
      `<name>:<capname>` lang string in both files.
- [ ] Every new table has privacy metadata **and** request **and** userlist coverage, and both
      `assertCount()` and the index-based name assertion in `tests/privacy/provider_test.php:108-109`
      updated.
- [ ] Every new scheduled or ad-hoc task has a `task_<classname>` string in both files.
- [ ] Every new setting has `<key>` and `<key>_desc` in both files.
- [ ] Every Mustache template carries a non-empty `Example context (json):` block, and no `{{…}}`
      tag appears inside a `{{! … }}` docblock.
- [ ] Every `bg-*` on a badge carries an explicit `text-white` / `text-dark`.
- [ ] No colour literal outside a `var(--bs-*, var(--*, #fallback))` chain; no `!important`.
- [ ] Every user-supplied profile value entering a sink went through
      `format_string(..., ['escape' => false])`.
- [ ] `enrol_apply_manage_table`'s and `enrol_apply_info_table`'s constructor signatures unchanged
      (`rg -n 'new .?enrol_apply_(manage|info)_table' .`).
- [ ] Every capability gate and every guard mutation-checked: delete the production line, exactly the
      **named, already-written** test goes red, nothing else. No mutation check may create the test
      it needs.
- [ ] No test asserting "X did not happen" without a control proving the mechanism ran.
- [ ] Behat scenario count matches the expected number for the slice (3 through slice 9 and I, 4 from
      slice J).
- [ ] No to-do markers, test-me annotations or merge-conflict marker lines anywhere in the repo.
- [ ] Nothing committed or pushed without being asked.

---

## Glossary of the new vocabulary

Keep these spellings identical across every slice; they appear in constants, in JSON, in column names
and in lang keys.

**Field key format** — one flat string namespace, used in `customtext4`, in the site setting, in the
JSON envelope and (from slice 10) in `enrol_apply_submission_field.fieldkey`.

| Form | Meaning | Example |
|---|---|---|
| `s_<column>` | A standard `{user}` column. | `s_city`, `s_phone2`, `s_institution` |
| `c_<id>` | A custom field, keyed by **`user_info_field.id`** — never by shortname. | `c_7` |

The default set is `\core_user::AUTHSYNCFIELDS` (17 names at `lib/classes/user.php:69-87`,
byte-identical on 5.1 and 5.2) minus `email`, `idnumber`, `lang` and `description` — **13 keys**
(`firstname`, `lastname`, `city`, `country`, `institution`, `department`, `phone1`, `phone2`,
`address`, `firstnamephonetic`, `lastnamephonetic`, `middlename`, `alternatename`), written as an
explicit constant.

**The three field states** — decided *before* `addElement`, and re-decided at write time.

| State | On screen | In the snapshot | Written to the profile |
|---|---|---|---|
| `editable` | field + its own confirmation checkbox | yes | yes |
| `locked` | static, read-only, under a heading of its own | yes | **never** |
| `absent` | nothing | nothing | never |

`locked` = auth lock (`field_lock_*`, read through `get_auth_plugin($user->auth)->config`) or
`user_info_field.locked`.
`absent` = guest, MNet, `!can_edit_profile()`, `PROFILE_VISIBLE_NONE`, or `lang`.

**Per-field state vocabulary** — a class constant from the start, carried in the JSON envelope and
back-fillable into `enrol_apply_submission_field.state` in slice 10:
`confirmed` · `updated` · `declined` · `prefilled` · `locked` · `blocked`.

**Profile-write switches** — both must be on:
`enrol_apply/allowprofilewrite` (site, default off) **AND** `customint8` (per instance, zeroed on
restore). Introduced together in slice 5.

**Submission status** — `enrol_apply_submission.status`, an `int`. Distinct from
`user_enrolments.status`, which it deliberately does not reuse.

| Value | Name | Meaning |
|---|---|---|
| `0` | pending | Submitted, undecided. `timedecided = 0`. |
| `1` | approved | Confirmed; the enrolment is active. |
| `2` | waiting | Deferred to the waiting list. |
| `3` | cancelled | Refused; the enrolment was removed. |

Note the collision this avoids: `user_enrolments.status` uses `ENROL_APPLY_USER_WAIT = 2`, which
core's derived `enrolment:status` Report Builder column labels "Not current" via
`status_field::STATUS_NOT_CURRENT` (`course/classes/reportbuilder/local/entities/enrolment.php`,
formatter at `local/formatters/enrolment.php`) — a legitimate label that is wrong here, which is why
the report ships its own status column and filter and keeps core's out of the defaults.

