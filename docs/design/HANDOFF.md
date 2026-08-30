# Handoff — read this before touching anything

State at the end of 2026-08-30. **Everything is merged; nothing is in flight.**

This covers THREE working days and says which is which wherever it matters: #41 and #42 landed
on 2026-08-27, #44 and #45 on 2026-08-29, and #47 to #50 on 2026-08-30. Dates in prose have
been wrong here twice; `git log --date=short` is the authority.

- `master` at `a9e905f`, **plus the commit that adds this file** — a handoff can never name the
  commit that merges it. Three of them have now named a sha already behind by the time anyone
  read it, so check the gap in one command and expect docs only:
  `git log --oneline a9e905f..HEAD`.
- `version.php` is `2026082900`.
- **332/332 PHPUnit on m501 and m502**, Behat 5 scenarios, the full matrix audited leg by leg
  (7 legs, MariaDB and PHP 8.2 included) — every leg exactly 332 tests and 5 scenarios.
- **Coverage runs, and was 56.1% lines (1773/3160)** when measured on 2026-08-29. Read the
  coverage section before comparing that with any other plugin's number, and re-measure before
  quoting it: three changes have landed since.
- **`mdl ci --strict` passes end to end.** It had been red on six `UnusedLocalVariable`
  findings, two of which #49 added; #50 cleared all six.

## What landed

| PR | What |
|---|---|
| [#41](https://github.com/uaiblaine/moodle-enrol_apply/pull/41) | **The "Decide this application" icon on the participants page.** |
| [#42](https://github.com/uaiblaine/moodle-enrol_apply/pull/42) | **Two dead seams in the decision path, one of which could throw.** |
| [#44](https://github.com/uaiblaine/moodle-enrol_apply/pull/44) | **Coverage made runnable, over a denominator that is not flattering.** |
| [#46](https://github.com/uaiblaine/moodle-enrol_apply/pull/46) | **The mutation spec: nine guards paired with the test each must redden.** |
| [#48](https://github.com/uaiblaine/moodle-enrol_apply/pull/48) | **The privacy export of the decision** — §3 item 1. |
| [#49](https://github.com/uaiblaine/moodle-enrol_apply/pull/49) | **The decided groups and role through backup/restore** — §3 item 2. |
| [#50](https://github.com/uaiblaine/moodle-enrol_apply/pull/50) | **Clearing `mdl ci --strict`**, six findings, two of them added by #49. |

[#47](https://github.com/uaiblaine/moodle-enrol_apply/pull/47) also landed on 2026-08-30 and is
NOT mine: another session stopped the cohort refusal naming the cohort. It is listed because the
two sessions shared one working tree all day, which is its own hazard and is written up below.

#41 and #42 closed the 2026-08-27 handoff's item 1 and two of its smaller items. **#44 closed
none of its numbered items** - it came out of a review of the records rather than off the list,
and it added §5 below. The sentence that used to stand here claimed #44's predecessors' credit,
having been carried forward verbatim from the handoff before it.

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

### 1. The enrolment-period branches of `confirm_enrolment()` — a product decision

**The only item left that needs a person rather than a session.** Still unreachable from the UI:
neither `manage.php`, nor the bulk decision form, nor the review page supplies `timestart` or
`timeend`. Re-measured 2026-08-30 by grep — the only caller passing a period is
`tests/outcome_message_test.php`, whose green makes the branch look exercised. Find it with
`grep -rn "'timestart' =>" tests/` rather than by a line number; the last handoff's line number
for it was wrong within a minute of being written.

**Finishing it** means date controls on the review page and the queue. **Deleting it** removes an
API capability no screen reaches. Left open deliberately, twice now.

### 2. Housekeeping

- **`~/dev/CLAUDE.md` records "`enrol_apply` 1"** in its phpmd table (line 376 on 2026-08-30).
  That was already stale and is 0 once #50 lands.
- **The fleet command table has no `mdl mutate` row.** Measured: zero occurrences in
  `~/dev/CLAUDE.md`. It was blocked all day behind another session's edit to
  `CLAUDE.fleet.md`, which has since landed, so it is now a one-line change in `moodle-dev`.
- **A `tests/coverage.php` ratchet** is possible now that a number exists, but do not set one
  from the 56.1% above without re-measuring: three changes have landed since it was taken.

### 3. Facts about this plugin that are not going to change

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

### 4. Notes kept from 2026-08-29

- **`mdl ci --strict` is red: four `UnusedLocalVariable`.** `tests/local/queue_test.php`
  (`$elsewhere`), `tests/outcome_message_test.php` (`$DB`, `$applicant`) and
  `tests/reportbuilder/course_applications_test.php` (`$applicant`). Each was attributed with
  `git log -L` on the line **and** by reading the enclosing method — none comes from this
  session's work; they date to 23, 24 and 27 August. Note that `~/dev/CLAUDE.md` records
  "`enrol_apply` 1", which is stale, so fix that count in the same pass.
- **The mutation harness is now `mdl mutate`**, in `~/dev/moodle-dev/bin`, and this plugin's
  spec is versioned at `mutations/gates.conf` — sixteen guards, each paired with the test it
  must redden. `mutations/README.md` has the rules. Add a mutation in the same change as the
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
