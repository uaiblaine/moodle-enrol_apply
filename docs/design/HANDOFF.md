# Handoff — read this before touching anything

State at the end of 2026-09-01. **Everything is merged; nothing is in flight.**

## 2026-09-01 — the decision screens

`master` at `9a5ba1b`, `version.php` at `2026083106`. Four pull requests, all green, all merged:
[#57](https://github.com/uaiblaine/moodle-enrol_apply/pull/57) (U1),
[#58](https://github.com/uaiblaine/moodle-enrol_apply/pull/58) (U1b),
[#59](https://github.com/uaiblaine/moodle-enrol_apply/pull/59) (CI trigger),
[#60](https://github.com/uaiblaine/moodle-enrol_apply/pull/60) (U2).

The design and the plan they execute are new documents:
[`applications-desk.html`](applications-desk.html) (the five answers, the mockups, and the
decisions taken from them on 2026-08-31) and [`ui-rebuild-plan.md`](ui-rebuild-plan.md) (eight
slices, U0–U5b). **U0, U1, U1b and U2 are done. U3 is next.**

### Three "environment facts" that were nothing of the kind

**Recorded as a caution about this file rather than about the environment.** An earlier version of
this section stated as properties of the host that `mdl phpunit-init` could not run without
`--disable-composer`, that the `MOODLE_502_STABLE` CI legs could not be built locally at all, and
that the runner's Behat was broken. All three were true when measured and none of them was a
property of anything: the machine was on a **restricted network**, and every one of those failures
was an outbound request being refused.

Re-measured on an unrestricted network the same day: `getcomposer.org`, `esm.sh` and
`registry.npmjs.org` all reachable from inside the container; `admin/tool/phpunit/cli/init.php`
completes with no flag; and `mdl ci --matrix --behat` runs **all seven legs green** — 390 PHPUnit
tests and 6 Behat scenarios on each, with the logs audited for `: FAILED` rather than the summary
column trusted.

What survives, and is worth keeping:

- **The symptoms, so they are recognised rather than diagnosed twice.** A restricted network makes
  `mdl phpunit-init` fail with a **composer usage dump**, which reads exactly like a `mdl` bug;
  makes the 5.02 legs fail at *install* with `Download failed: Client network socket
  disconnected`, before any gate runs; and makes the runner's Behat report
  `http://mdlci-run-…:8000 is not available`. None of the three names the network.
- **The workaround, for when you are behind such a proxy.** `--disable-composer` on
  `admin/tool/phpunit/cli/init.php` and on `admin/tool/behat/cli/init.php` skips the self-update
  and the dependency refresh, and the rest of the local environment then works — including core's
  own test suites. `mdl behat m502` also works where the CI runner's Behat does not.
- **The lesson, which is the general one this file keeps relearning.** "This cannot be done here"
  is a claim about a moment, and writing it as a claim about the machine is how the next reader
  stops trying. Measure the network before concluding anything about the host.

### Facts that ARE about the environment

- **Every `version.php` bump stales both test sites.** Re-init before the mutation sweep, and judge
  it by the test COUNT, never the failure count.

### Three defects no gate in this repository could have caught

- **A red gate that had been red for some time.** Core's
  `string_manager_standard_test::test_validate_deprecated_strings_files` asserts `string_exists()`
  for every line of every `lang/en/deprecated.txt`, and Moodle's deprecation contract KEEPS the
  definition. `maxenrolled` and `maxenrolled_help` were listed *and* deleted, so it failed on both
  branches. `moodle-plugin-ci` runs `--testsuite enrol_apply_testsuite`, so the plugin's own CI
  cannot see it. **Invoke it by hand whenever `deprecated.txt` changes.**
- **`db/upgrade.php` is executed by nothing but `mdl upgrade`.** A step calling a
  `db/upgradelib.php` helper without requiring it died with "Call to undefined function" and left
  the site on the version it started at — through a full green CI run, because CI installs fresh
  from `install.xml` and never runs the upgrade path. **Running `mdl upgrade` is part of shipping a
  step, not an optional check.** This one is real and is not about the network: CI installs fresh
  by design, on any network at all.
- **A five-lens adversarial pass over U2 found 65 confirmed findings on a slice that was already
  green, mutation-checked and Behat-covered** — four of them defects, including a
  `justify-content-between` that spread nothing (it was passed to a container with one child) and
  an identity line that silently dropped every custom profile field. It is worth the run.

### The trap that was met twice in one slice

`test_the_capacity_panel_reports_both_numbers` passed with places and applicants **fully swapped**,
and so did the first attempt at strengthening it: both strings remain on the page, just against the
other label. Only assertions scoped to their own row can see it. This is the general rule CLAUDE.md
already states — extract the element, then assert inside it — and it is easy to walk past twice.

Also recorded: a mutation that "reddens nothing" may be a mutation that **did not apply**. One
here printed a confident `OK` having changed no bytes. Check the pattern matched before believing
the result.

### What U3 changes, and the one thing to decide first

U3 makes deferral a first-class triage state: a `decisionnote` column, an applicant-facing state,
non-empty notification defaults, and a remedy for the applicant-cap ratchet. **The remedy must
change data rather than the predicate** — three plugins outside this repo reach
`enrol_apply_plugin::is_full()` through `is_callable()`, and gate `AC` proves only that it still
delegates, not that its answer is unchanged.


This covers one long session that ran across midnight. `git log --date=short` is the authority
for dates and always has been here: #52, #53 and #54 merged on 2026-08-30, #55 on 2026-08-31.

- `master` at `61b9407`, **plus the commit that adds this file** — a handoff can never name the
  commit that merges it. Four of them have now named a sha already behind by the time anyone read
  it, so check the gap in one command and expect docs only:
  `git log --oneline 61b9407..HEAD`.
- `version.php` is `2026083003`. Three bumps landed this session, each with its own upgrade step.
- **363/363 PHPUnit on m502**, measured directly. The full matrix passed leg by leg (7 legs,
  MariaDB and PHP 8.2 included) which covers m501 — but `mdl ci` deletes per-leg logs on a
  passing run, so **the per-leg test COUNT was not captured**. If you need the m501 number, run it;
  do not infer it from this line.
- **35 mutation gates, 35 reddening**, baseline green and `tests run: 363` constant across all 36
  runs of the final sweep. That constant is the load-bearing half: it is what distinguishes "this
  mutation reddened nothing" from "the suite never ran".
- **Behat: 5 scenarios, 114 steps, all passing on m502**, measured after a `behat-init` (three
  version bumps had staled the site). Note the matrix does NOT cover this: Behat is opt-in
  (`mdl ci --behat`) and every matrix run recorded above was taken without it, so a green matrix
  says nothing about these scenarios.
- **Coverage was NOT re-measured this session.** The last figure, 56.1% lines, is from 2026-08-29
  and eight changes have landed since. It is stale; treat it as absent rather than as a number.
- **`mdl ci --strict` was not re-run either.** It passed at the end of 2026-08-30 and nothing
  since was aimed at it.

## Four repositories moved, not one

This is the first session where a change to this plugin required changes elsewhere in the fleet,
and that is now a standing hazard rather than a one-off.

| repo | PR |
|---|---|
| `moodle-local_dimensions` | [#20](https://github.com/uaiblaine/moodle-local_dimensions/pull/20) |
| `moodle-local_unlistedcourses` | [#1](https://github.com/uaiblaine/moodle-local_unlistedcourses/pull/1) |
| `moodle-theme_boost_union_fundaseg` | [#1](https://github.com/uaiblaine/moodle-theme_boost_union_fundaseg/pull/1) |

All three had **re-implemented this plugin's places-cap arithmetic inline**. When the cap changed
its answer they went on treating a course as full after its places were freed, while this plugin
offered the button and accepted the application. **Their own tests stayed green** — every one of
them seats occupants with `timeend = 0` — and nothing in any pipeline reports the divergence.

They now ask `enrol_apply_plugin::is_full()` through `is_callable()`. **If that method's name or
meaning ever changes, all three break silently**: `is_callable()` simply starts returning false
and each falls back to its own unfiltered count. Gate `AC` exists for exactly that.

## What landed

| PR | What |
|---|---|
| [#52](https://github.com/uaiblaine/moodle-enrol_apply/pull/52) | **Eligibility is now checked where the application is WRITTEN**, not only where it is offered. |
| [#53](https://github.com/uaiblaine/moodle-enrol_apply/pull/53) | **A refused application says why**, instead of a bare "Invalid access detected". |
| [#54](https://github.com/uaiblaine/moodle-enrol_apply/pull/54) | **The places cap stopped ratcheting shut**, and deferring clears a carried expiry. |
| [#55](https://github.com/uaiblaine/moodle-enrol_apply/pull/55) | **Places and applicants are two numbers**, and the label finally names what it counts. |

Each of these came out of investigating the one before it. Only #52 was on anybody's list.

**Three defects nobody was looking for**, and they are the reason this session is worth reading:

- **Approving an application that carried a past expiry left the applicant ACTIVE with no
  access** — green badge, permanently, under the `expiredaction` the plugin ships. It surfaced
  because a mutation pattern of mine was mis-anchored: `lib.php` carries
  `$userenrolment->timeend = 0;` twice, the pattern hit the one in `confirm_enrolment()` instead
  of `wait_enrolment()`, reddened nothing, and **the line it had accidentally removed turned out
  to be real**. Gate `AD`.
- **A deferred application kept any expiry it was carrying**, producing a row core's sweeps skip
  (they filter `status = active`) and the queue hides (it filters on that very `timeend`). It
  waited for a decision nobody could take. Fixed at the source, with an upgrade step for rows
  already in the state.
- **The places cap was a ratchet.** It counted expired enrolments, which under the shipped
  `ENROL_EXT_REMOVED_KEEP` nothing ever frees — so a course could fill, expire, and refuse
  everybody for ever with an empty queue and no screen able to explain it.

## The coverage number, and what it is a number OF

**It was not merely unmeasured before #44 — `mdl ci --coverage` FAILED**, so no figure had ever
existed for this plugin. `tests/backup_test.php` declares `#[CoversClass]` on
`backup_enrol_apply_plugin` and `restore_enrol_apply_plugin`, and neither is autoloadable: core
loads `backup/moodle2/*.class.php` by path, only when a run actually reaches an `enrol_apply`
element. PHPUnit resolves a coverage target per test, so it resolved only once some earlier test
had happened to perform a restore — **which tests warned was decided by execution order alone**,
and a randomised order would have moved it. Four warnings fail the run. Ordinary CI never sees any
of this, because the reusable workflow passes `coverage: none` and so never resolves a target.

**The default denominator flatters, and the direction was measured rather than assumed:**

| denominator | lines | covered | of |
|---|---|---|---|
| Moodle's default include list | 77.8% | 1569 | 2016 |
| with `tests/coverage.php` | **56.1%** | 1773 | 3160 |

The honest run **covers more lines** — `backup/` is now measured and is well tested — while the
denominator grows by far more. That is the fleet note reproduced here: unmeasured code is *absent*
from the clover rather than counted low, so it makes a plugin look better and never worse.

**The two largest untested surfaces are now visible, and were not before:** `settings.php` 0/205
and `db/upgrade.php` 0/164, then `edit_form.php` 0/155, `manage.php` 0/89, `edit.php` 0/63. The
second matters more than its size: `db/upgrade.php` is where the backfill steps live, and this
repo has already been bitten once by a step whose DDL guard did not make its DML idempotent.

## Facts the PREVIOUS handoff and the design docs stated that are now obsolete

Read this section before trusting anything else you remember about this plugin.

- **`docs/design/audit-trail-analysis.md`'s "The custom action icon" section is SUPERSEDED** and
  now carries a block saying so. Do not follow its instructions. It said the icon "must point at
  `manage.php?id=<enrolid>` and **not** `?userenrol=`", which was true only of the review page's
  old user-context-only gate; `queue::require_review_access()` admits the course teacher, and the
  icon ships pointing at `?userenrol=`. Its "Measured markup on m502" block was a **sketch** and
  never shipped — the rendered anchor is quoted in that annotation, and only there; `CLAUDE.md`
  carries the target in prose but not the markup.
- **"`user/amd/src/status_field.js` claims exactly three action names, so any other `data-action`
  is inert" is FALSE**, and it was written into `lib.php`, `CLAUDE.md` and a Behat comment before
  being measured. The participants table is a `core_table\dynamic` table, and `core_table/dynamic`
  also claims `a[data-action="hide"]`, `a[data-action="show"]` and `[data-action="showcount"]`
  anywhere inside `[data-region="core_table/dynamic"]`, each dispatched with `preventDefault()`.
  **Six** values, two lists growing independently — and the first correction of this said
  **five**, because `showcount` was discounted for having a selector that is not anchor-scoped.
  It matches an anchor perfectly well. The shipped link carries no `data-action` at all.
- **"`get_user_enrolment_actions()` has no precedent at all" is FALSE as stated.** No core enrol
  plugin OVERRIDES it — that half is measured and holds — but **eight** core plugins ship a
  `test_get_user_enrolment_actions()`, and `enrol/manual/tests/lib_test.php:493` is the shape this
  plugin's test file follows. The claim was written in a session that had already read one of the
  eight.
- **`chosen_groups()`'s empty-array branch was not "unreachable code to consider deleting", it was
  a latent crash**, and it is fixed. The caller branches on `=== null` and hands anything else to
  `get_in_or_equal()`, which refuses an empty array outright. Unreachable through the writer;
  reachable through a **restore**, which writes that column from a foreign archive. It now returns
  null for anything naming no group.
- **`add_instance_groups()`'s dead default is gone.** Nothing to re-check.
- **Never carry one branch's line number under a claim measured on both.** `CLAUDE.md` said
  `user/classes/table/participants.php:355` was "the only live caller … measured on both
  branches"; on 5.1 it is `:347`. Five separate review lenses caught that one line.
- **The masking-on-the-course-context product question still stands** (a mentor loses the identity
  fields on the review page's snapshot panel). Unchanged, still pinned by
  `test_the_masking_is_judged_in_the_course_and_not_at_system_level`.
- **"The full participants-page lock is DECIDED AGAINST" still stands** (2026-08-25). Unchanged.

### Superseded on 2026-08-30/31 — check these before trusting any memory of the capacity code

- **`\enrol_apply\local\capacity` no longer has `limit()`, `taken()` or `is_full()`.** They are
  `applicant_limit()`, `applicants()` and `applications_closed()`, and there are now three more:
  `places()`, `places_taken()`, `places_full()`. **`enrol_apply_plugin::is_full()` DID keep its
  name** and fronts the applicant question — the two names differing by one word is deliberate
  and load bearing, because three plugins outside this repo call the second one.
- **`submit_application()` no longer returns a bool.** It returns
  `\enrol_apply\local\application_result` with three states. The old bool fused "already there"
  with "refused", which is why nothing could route them differently and every outcome landed on a
  page that then refused the refusals.
- **The cap is no longer written out in three places.** It was, and deleting any one of them
  reddened nothing at all — the finding that motivated the extraction.
- **`maxenrolled` and `maxenrolled_help` are gone**, replaced by `maxapplicants` and listed in the
  plugin's first `lang/en/deprecated.txt`. **`maxenrolled_tip` is gone too** — it had been an
  orphan in both packs, rendered nowhere, killed as collateral of `4a893be` without the commit
  message mentioning it. Nothing in any pipeline reports an unused lang string.
- **"Never rename a lang key, because `tool_customlang` overrides are keyed by it" — I stated
  that too broadly, and then did the opposite two days later.** Both are right, and the rule is
  whether the key's MEANING changes. `maxenrolledreached` kept its key and changed its value: it
  still means "you cannot apply". `maxenrolled` became `maxapplicants`: it stopped being *the*
  limit and became one of two. A site that customised the old label would otherwise keep a label
  that is now wrong beside a number it no longer distinguishes itself from. **A config key is
  different again** — that is data, and `enrol_apply/maxenrolled` was deliberately NOT renamed,
  because renaming it silently resets what every site configured.
- **`db/upgrade.php`'s `2026081000` step has never fired.** Its guard is
  `$plugin->get_config(...) === false`, and `enrol_plugin::get_config()` returns its `$default` —
  `null` — for an absent setting, never `false`. Measured on 5.1 and 5.2. The step is left alone
  (it has already run everywhere, and editing a past step changes nothing for anyone who passed
  it) but do not copy its shape: use the GLOBAL `get_config()`, as the `2026083003` step does.

### Measured on 2026-08-30, each after a plausible claim of mine turned out false

Four backup and privacy mechanics, all learned by running something rather than by reading
harder. Each was a sentence I would have shipped.

- **Core annotates every enrol instance's own `roleid`** (`backup_stepslib.php:737`, the same
  line on 5.1 and 5.2 — checked, because this file's own rule says not to carry one branch's
  number), so a test
  whose decided role IS the instance default proves nothing about a plugin's own role
  annotation. The mutation removing that annotation reddened NOTHING until the fixture used a
  role the instance does not name.
- **An empty backup element parses back as NULL**, not as the empty string it was written from.
  So a `?? ''` on a restore read is the ORDINARY path - every undecided application - and not
  the old-archive edge case it looks like.
- **A TypeError inside a restore step strands the backup's temp tables**, and every later
  restore in the same run then dies with `t_backup_ids_temp does not exist`. One mutation's 24
  red tests were one real failure and 23 casualties. Read a red COUNT with that in mind.
- **`role_get_name()` returns two spellings.** A role whose `role.name` is non-empty comes back
  through `format_string()` and is ESCAPED, filtered against the SYSTEM context; an empty one -
  which is every role a stock site ships - comes back from a bare `get_string()` and is not.
  Core's only precedent for exporting one (`badges`) inherits the mixture.
- **Group ids need no annotation to travel**, because
  `backup_annotate_course_groups_and_groupings` annotates every group of the course
  unconditionally. Role ids DO: `roles.xml` selects on `rolefinal`. The two are not symmetrical
  and look as though they should be.

## What is left, in the order I would take it

### 1. Should places ever BLOCK an approval? — a product decision, deliberately deferred

Places currently **warn and nothing else**. The owner chose that on 2026-08-31, and the reasons
are worth keeping because the cheap-looking alternative is not cheap:

- The plugin's whole premise is that a human judges each application. A number that refuses one
  contradicts it.
- A block would have to be reproduced on **three** routes — the queue, the participants-page bulk
  action and the per-row icon — and the icon has **no channel to explain a refusal at all**.
- `complete_approval()`, the one place that looks like a natural home for the check, runs
  **twice** for a queue approval, and the earlier pass has no operator to tell anything to.

Revisit with real usage data, not from first principles. If it is ever done, the bulk
confirmation form is the only surface that can speak *before* the decision, which makes it the
place to explain a refusal rather than merely announce one.

### 2. The enrolment-period branches of `confirm_enrolment()` — a product decision

**The only item left that needs a person rather than a session.** Still unreachable from the UI:
neither `manage.php`, nor the bulk decision form, nor the review page supplies `timestart` or
`timeend`. Re-measured 2026-08-30 by grep — the only caller passing a period is
`tests/outcome_message_test.php`, whose green makes the branch look exercised. Find it with
`grep -rn "'timestart' =>" tests/` rather than by a line number; the last handoff's line number
for it was wrong within a minute of being written.

**Finishing it** means date controls on the review page and the queue. **Deleting it** removes an
API capability no screen reaches. Left open deliberately, twice now.

### 3. Housekeeping

- **Re-measure coverage and re-run `mdl ci --strict`.** Neither was run this session and eight
  changes have landed. The 56.1% in the old handoff is not a number any more.
- **`~/dev/CLAUDE.md`'s phpmd table** still records a count for this plugin that predates #50.
  Check it against a fresh run rather than against this line.
- **The fleet command table has no `mdl mutate` row.** Measured: zero occurrences in
  `~/dev/CLAUDE.md`. It was blocked all day behind another session's edit to
  `CLAUDE.fleet.md`, which has since landed, so it is now a one-line change in `moodle-dev`.
- **A `tests/coverage.php` ratchet** is possible now that a number exists, but do not set one
  from the 56.1% above without re-measuring: three changes have landed since it was taken.

### 4. Facts about this plugin that are not going to change

- **A core defect, not fixable from here:** the participants page renders a waiting-list row
  (`ENROL_APPLY_USER_WAIT = 2`) as a green **"Active"** badge. Note the framing, which the
  CHANGELOG got wrong once: the value is **this plugin's own** third value in a column core defines
  two for, so it is not a bug to report upstream.
- **A bulk decision reaches ONE apply instance per course.** Not fixable from here. The new action
  icon does not have this limitation — a user enrolment id names its own instance.
- **`user/templates/status_field.mustache` hardcodes `role="button"`** on every enrol action
  anchor. Core's three are JS-driven buttons; this plugin's is a real navigation link, so it is
  announced as a button and Space does not activate it. Core's markup, not fixable here.
- **If the full history is ever wanted**, the shape is a decision-log table, not more status values.

### 5. Notes kept from 2026-08-29

- ~~**`mdl ci --strict` is red: four `UnusedLocalVariable`.**~~ Cleared by #50 on 2026-08-30;
  kept for the attribution method it describes.
  Original note: `tests/local/queue_test.php`
  (`$elsewhere`), `tests/outcome_message_test.php` (`$DB`, `$applicant`) and
  `tests/reportbuilder/course_applications_test.php` (`$applicant`). Each was attributed with
  `git log -L` on the line **and** by reading the enclosing method — none comes from this
  session's work; they date to 23, 24 and 27 August. Note that `~/dev/CLAUDE.md` records
  "`enrol_apply` 1", which is stale, so fix that count in the same pass.
- **The mutation harness is now `mdl mutate`**, in `~/dev/moodle-dev/bin`, and this plugin's
  spec is versioned at `mutations/gates.conf` — **35** guards as of 2026-08-31, each paired with
  the test it must redden. `mutations/README.md` has the rules. Add a mutation in the same change as the
  guard it protects; the two that were added late this session both found something.
  **Always `--dry-run` first**, and read a red COUNT knowing that a failure inside a restore
  step cascades through every later restore in the run.
- **`settings.php` and `db/upgrade.php` at 0%** are now the plugin's two largest untested
  surfaces, and are only visible because of `tests/coverage.php`.

## How to work here

Everything below cost real time to learn. **Only "a gate nothing runs" is new on 2026-08-29**;
the other four marked NEW were new on 2026-08-27 and are kept at that heading because they are
still the ones most likely to be skipped. Anything below saying "this session" without a date
means 2026-08-27 — the previous handoff wrote them in the present tense and this one inherited
them, which is the same carry-forward it warns about two sections up.

### NEW 2026-08-31 — the FIXTURE decides whether a test can see its own mutation

This repo has known the principle for weeks. This session it happened twice more, and neither
was caught by reading.

**The stash test.** "A refused application stashes no profile offer" is the obvious assertion and
it is **vacuous**: `get_data()` returns null on a unit-built `dynamic_form`, so `diff::compute()`
finds no changes and `offer::stash()` returns before writing — there is nothing to stash either
way. It would have passed against a build that stashes unconditionally.
`tests/fixtures/testable_application_form.php` exists solely to make the stash *possible*, and
`test_a_created_application_does_stash_a_profile_offer` is the control proving it. Measured:
mutation `T` reddens that one test and nothing else, which is exactly what the fixture bought.

**The places test.** `places_taken()` losing its status clause makes it a second spelling of
`applicants()`. Every assertion about a single number passes either way. **Only the assertion
that the two counts DIFFER on the same fixture can see it** — gate `AE`.

The pattern in both: when a guard's failure mode is "this becomes the same as that", assert the
*difference*, not either value.

### NEW 2026-08-31 — another repo's test can be right about your design, and you should let it be

`local_dimensions` ships `optional_enrol_apply_test`, which greps its **own source** and fails if
production code names a class in the `enrol_apply` namespace. My first version of the fleet fix
did exactly that, behind a `class_exists()` guard I thought was sufficient.

The test was right. `enrol_apply` is an *optional* dependency there, and a namespaced reference is
one the autoloader must resolve on a site without the plugin. The alternative it forced — talk to
the **plugin object** through `is_callable()`, as every `allow_apply()` call in that file already
did — is better than what I had written, and it is why `enrol_apply_plugin::is_full()` exists at
all rather than callers reaching into `classes/local/`.

**Read a failing test from another repo as a claim about your design before assuming it is an
obstacle to your change.**

### NEW 2026-08-31 — a mis-anchored mutation is a bug in the spec, and can still find a real defect

`mutations/README.md` already says to anchor on what makes a line unique. `AB`'s first pattern
ignored that: `lib.php` carries `$userenrolment->timeend = 0;` **twice**, and it matched the one
in `confirm_enrolment()` rather than the one in `wait_enrolment()`.

It reddened nothing, which read exactly like "this guard is unheld". Two separate findings came
out of that:

1. The pattern was wrong and had to be re-anchored on the comment above the line.
2. **The line it hit by accident was load bearing and nothing tested it** — approving an
   application carrying a past expiry left the applicant ACTIVE with no access, permanently.

So: a gate that reddens nothing means *either* the guard is unheld *or* the pattern is wrong.
**Check which before concluding either**, and if the pattern was wrong, look at what it actually
mutated before throwing the result away.

### NEW 2026-08-31 — three false greens in one session, all from trusting an exit code

Every one of these would have been reported as success:

- `gh pr checks --watch | tail -20` — the pipeline's exit code is `tail`'s, not `gh`'s.
- A `for` loop with `set -- $var` — **zsh does not word-split unquoted variables**, so every `cd`
  failed, nothing was checked, and the loop printed its own "all done" banner and exited 0.
- `grep -c 'allow_apply($instance, (int) $userid)'` in double quotes — `\$` collapsed to an
  unescaped `$`, the pattern matched nothing, and it looked like the guard was missing from
  `master`.

And once in prose: I told the user the work was committed when 28 files were not.

**The defence is the same every time, and it is the same one this plugin's own bugs teach:
re-query the state rather than believing the report.** `gh pr view --json` over `gh pr checks`;
`grep -F` when matching literal PHP; and `git status` before any sentence claiming something is
saved.

### NEW 2026-08-31 — the fleet re-implements your predicates, and its tests will not tell you

Three repos had inlined this plugin's cap arithmetic. When the cap changed its answer, all three
silently disagreed with it — and **all three stayed green**, because every one of them seats test
occupants with `timeend = 0`, the single value on which the old and new predicates agree.

Before changing what a shared predicate ANSWERS, grep the fleet for the shape of it, not for the
name of your method:

```sh
rg -n "count_records\('user_enrolments'" ~/dev/moodle-*/classes ~/dev/moodle-*/*.php
```

### NEW — the verification ladder. Do not verify everything at the same depth.

An adversarial pass that spawns two verifier agents per finding is mostly waste: on 2026-08-27 it
spawned 48 verifier agents for 24 findings (of 29 raw, which clustered to 15 real subjects), several of them re-reading the whole slice to settle "is that line number right on 5.1?"
Use the cheapest instrument that can settle the claim, and escalate only when it cannot:

| Tier | Instrument | Use for |
|---|---|---|
| 0 | cluster findings by SUBJECT; ≥2 independent lenses ⇒ treat as real, verify nothing | duplicates |
| 1 | one `grep`/`sed`/`php -r` you run yourself | any file:line, core-behaviour or string claim |
| 2 | one run of the mutation harness | "no test would catch this" |
| 3 | one agent; a panel only for HIGH findings that would change shipped behaviour | genuine judgement |

Two things this got right that are worth keeping. **Cluster by subject, not by title** — five lenses
reported the same line-number defect under five different titles, and a title-keyed dedup bought
five verifier pairs for one fact. And **a "reproducer" agent is a worse instrument than running the
code**: the one finding that mattered ("widening the gate to `can_manage_application()` leaves every
test green") was settled by a mutation run, which both proved it and produced the fix.

**The tool that runs Tier 2 has now found three defects in its own reporting**, each of the same
shape: something that looked like a result and was not. An empty section where the suite never
ran; a `pipefail` abort after the first mutation; and PHPUnit's warning list, numbered `1)` just
like its failure list, being counted as failures — one mutation that failed a single test was
reported as reddening twenty-five. Distrust a red list you have not seen the counts line for.

Keep the FINDER fan-out wide. Five lenses over one small slice produced 29 findings in 15 clusters,
six of them confident wrong sentences of mine. Breadth found things; depth mostly re-litigated them.

### NEW — a gate nothing runs is a gate nobody has passed

Coverage had never run here, so a defect sat in `tests/backup_test.php` for as long as that file
has existed, invisible to every green build. The general form is worth keeping: **the plugin's CI
runs a subset of the gates that exist, and a claim about a gate CI does not run is unevidenced.**
The same is true of `--strict` (phpmd is `continue-on-error` upstream and cannot fail a build) and
of core's privacy `test_table_coverage`, which this repo already documents as needing a deliberate
invocation. Before believing a plugin is clean, check which gates actually ran.

The specific trap, because it will recur in any plugin with backup/restore classes: **a
`#[CoversClass]` target that is not autoloadable resolves only by accident of test order.**
`backup/moodle2/*.class.php` is loaded by path, by core's backup machinery, when a run reaches the
plugin's element — so the target resolves for tests that run after the first restoring test and
fails for those before it. Require the class files at test-file scope, after the backup and restore
includes they depend on, and pin it with a `class_exists(..., false)` test placed first in the file.

### NEW — silence is not a result

**The mutation harness must fail loudly when a run produces no PHPUnit verdict line at all**, not
only when its restore fails. Two mutations "reddened nothing" on 2026-08-27 because m502's versions
hash had shifted underneath them and the suite never ran; the harness printed an empty section.
It now hard-exits on a missing verdict line **and** prints each run's test count. The same shape
bit `mdl phpunit m501`, which printed no `OK (…)` line at all — an outdated environment, invisible
to any grep for failures.

### NEW — Perl interpolates BOTH sides, and `\Q…\E` does not stop it

`\Qif (!has_capability('…', $manager->get_context()))\E` matches **nothing**, because `$manager`
interpolates to the empty string inside `\Q`. The replacement side does it too: a replacement
containing `$row->decidedgroups` produced `->decidedgroups`. Escape every `$` on both sides, and
verify a mutation matched **exactly one line** before running it — the icon's capability gate is
byte-identical to `get_bulk_operations()`'s, and only `return $actions;` tells them apart. This is
the same family as the Python form recorded last session, so treat it as language-independent:
**never build source text with an interpolating string, and always diff the result.**

### NEW — two sessions can share one working tree, and the tooling must assume it

Another Claude session worked in `~/dev/moodle-enrol_apply` and in `moodle-dev` for a whole day
alongside this one. Nothing was lost, but only by luck the first time: a mutation sweep rewrites
files in the tree and restores them from a snapshot, so an edit arriving mid-sweep in one of the
spec's files would have been destroyed silently. `mdl mutate` now refuses to overwrite content it
did not write - proven by running a sweep against a background process editing the file - but the
habits matter as much as the guard:

- **Stage your own paths, never `git add -A`.** Their work sat uncommitted in the tree for hours.
- **Do not switch the shared tree's branch.** `git checkout -b` moved the other session onto a
  branch it never asked for; putting it back on `master` afterwards is the least you can do.
- **A clean tree is not proof their work is gone.** It was committed to a branch and pushed.
  Check `git log --all --since` and the remote branches before concluding anything.
- **Their `version.php` bump stales your test site.** Half the "environment initialised for a
  different version" aborts this session were that, not the fleet mounts.

### NEW — the fleet can break your stack mid-run

Another session added a plugin to `~/dev/moodle-dev/plugins.conf` while this one was running. Both
stacks carried an empty bind mount for it, which broke `core\component::get_all_versions()`, changed
the versions hash, aborted PHPUnit at bootstrap, and restarted m502's containers under a Behat run.
Symptoms to recognise: `Failed opening '…/version.php'`, `plugin_manager.php:320` warnings, and
`Moodle PHPUnit environment was initialised for different version`. It is not your change.
`mdl phpunit-init` re-stamps it once the mount is real.

**Related, and unresolved since 2026-08-27 — NOT re-checked on 2026-08-29: the m502 BEHAT site
failed on core scenarios.**
`@enrol_self` fails 3 of 17, and an `@enrol_apply` run aborted with `Undefined variable $CFG in
lib/filelib.php`. That is site-wide and nothing to do with this plugin — the same plugin code passed
Behat on all 7 isolated matrix legs the same hour. **Use `mdl ci --matrix --behat` as the Behat gate
until the local site is repaired**, and do not read a local Behat failure as a regression without
running a core tag first.

### Unchanged

- **Mutation-check every guard**, and **count the tests, not the failures**. A version bump stales
  the PHPUnit environment — re-initialise after every bump, before the mutations.
- **A mutation that reddens nothing is a finding**, and so is one that reddens somewhere you did
  not predict: check WHICH assertion fired. A docblock here claimed a mutation "goes red with the
  exception"; it goes red on the test's own premise assertion, which runs first.
- **NEVER give a subagent write access to the working tree.** Read-only reviewers, and say so in
  the prompt. Scratch files left in `tests/` contaminate every later run.
- **Run the matrix with `--keep-logs`.** A passing run deletes them, and the per-leg audit — exit
  code, PHPUnit count, scenario count, anchored `^-- [a-z]+: FAILED$` — is the point.
- **Never edit the tree while the matrix runs.** Done by accident on 2026-08-27; the run was killed
  and restarted rather than read across two states.
- `git worktree prune` before every run. One PR per unit of review, `--repo uaiblaine/moodle-enrol_apply`
  on every `gh` call, and no `--delete-branch` where another PR is stacked on that branch.
- **On a `version.php` merge conflict, keep the HIGHER number.** Two PRs branched from the same
  master on 2026-08-27 and this is exactly how it resolved.
- **The most expensive defect here is a confident wrong sentence.** Six on 2026-08-27 and five
  more found on 2026-08-29 by one read-only critic over this very file — a stale date, a stale
  "what landed", a miscount of 3+3, an unearned "re-measured", and a carried-forward closing
  line that claimed another change's credit. Every one
  load bearing, every one caught by measuring rather than reasoning. Each now says what was
  measured and that an earlier draft claimed more.
- **Nothing in this repository executes the plugin's JavaScript** except the `@javascript` Behat
  scenarios, and **nothing renders CSS**.
