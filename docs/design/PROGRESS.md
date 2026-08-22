# Implementation progress

Running state of the eleven-slice plan in
[`implementation-plan.md`](implementation-plan.md). Update this file as slices land.

Last updated: 2026-08-22.

## Merged

| PR | Slice | What landed |
|---|---|---|
| [#1](https://github.com/uaiblaine/moodle-enrol_apply/pull/1) | — | The design and the eleven-slice plan |
| [#2](https://github.com/uaiblaine/moodle-enrol_apply/pull/2) | 1 | Cohort restriction + application window |
| [#3](https://github.com/uaiblaine/moodle-enrol_apply/pull/3) | — | Pinned two slice-1 guards no test was holding |
| [#4](https://github.com/uaiblaine/moodle-enrol_apply/pull/4) | 2 | Bootstrap cleanup, row headers, `bootstrap_compat_test` |
| [#5](https://github.com/uaiblaine/moodle-enrol_apply/pull/5) | 3 | The two-level profile field set |
| [#6](https://github.com/uaiblaine/moodle-enrol_apply/pull/6) | 4 | Card on the enrolment page, `dynamic_form`, two transports |
| [#7](https://github.com/uaiblaine/moodle-enrol_apply/pull/7) | 5 | Optional profile write + completeness gate |
| [#8](https://github.com/uaiblaine/moodle-enrol_apply/pull/8) | — | Stop losing an approved applicant's group membership on restore |
| [#9](https://github.com/uaiblaine/moodle-enrol_apply/pull/9) | 6 | Durable snapshot, privacy, backup, lifecycle |

## In progress

**The kept-roles backup gate**, branch `fix/backup-kept-roles-gate` — the defect found while
researching slice 6 and deliberately deferred out of it. See "Defects found and deferred".

## Slice 6, as merged

Every file in the plan's table is written and every verification step in it has been run.

Verified: 162/162 PHPUnit on **both** m501 and m502; core's
`core_privacy\privacy\provider_test` clean for `enrol_apply` on both branches (it does **not**
run in this plugin's CI — see the departures below); the full lifecycle exercised on the live
m502 site, where the upgrade backfill also picked up the one pre-existing pending application;
Behat 3 scenarios; the whole matrix.

Then an adversarial review of the diff, five reviewers with every finding independently
verified: **19 confirmed, 4 refuted**. Nine were real defects in the new code and are fixed;
the rest were tests of mine that passed while proving nothing. The ones worth remembering:

- A decider's own subject access export carried the **applicant's** comment and profile
  snapshot. Erasing a decider **deleted the applicant's record**. Both are now asymmetric by
  role, which is the correct reading and is written down in three places.
- The export path had no per-record discriminator, so a person who applied, was cancelled and
  applied again exported one record over the other — the same defect this slice set out to fix,
  arriving by a different route.
- The upgrade's backfill was not idempotent, and `upgrade_plugin_savepoint()` only runs after
  the whole step, so the standard recovery from a mid-loop failure doubled every row. The
  approval queue's new join then stopped being one to one.
- The backfill predicate was "not active" rather than the queue's own, so an enrolment approved
  long ago and since expired would have been backfilled as an application nobody ever decided.
- The retention sweep deleted records of applications still sitting in the queue, so a decision
  taken afterwards had no row to stamp and was recorded nowhere.
- The restore de-duplication was dead code: it keyed on an enrol id the restore had just
  created, which no existing record can carry.

Every fix is mutation-checked and reddens exactly its own named test.

## Next

Slice 7 — the system report in the course — once the kept-roles fix has landed.

## Decisions taken that depart from the plan

Record them here so a later reader does not "fix" them back.

1. **No `UNIQUE (courseid, userid)` on `enrol_apply_submission`.** The plan specifies both that
   key and pseudonymising on course deletion by zeroing `userid`. They are incompatible: a
   deleted course with two applicants gives two rows with the same `courseid` and `userid = 0`.
   Measured, not reasoned — a scratch table with that key raises `dml_write_exception` on the
   second row. It would also turn a restore into an existing course, where a duplicate is
   legitimate, into a fatal restore error. Uniqueness is enforced in code by the lock added to
   `submit_application()` in slice 4, which is what the plan credited the key with providing.

   Re-measured in slice 6 with the key actually in `install.xml`: six tests error, PostgreSQL
   saying `duplicate key value violates unique constraint`. Two of them are the reasons, and
   they are independent — `test_a_deleted_course_with_two_applicants_keeps_both_rows` (the
   pseudonymisation collision) and `test_re_applying_after_a_cancellation_adds_a_second_record`
   (a cancelled application must not be overwritten by the next attempt).

   **A trap that nearly recorded the opposite result.** `mdl phpunit-init` does not rebuild a
   table that already exists, so the first run of that mutation executed against the old schema
   and reported everything green — a mutation that "proves" a key is harmless because the key
   was never there. Drop the test database first and confirm the index really changed:

   ```sh
   docker exec m502-webserver-1 php /var/www/html/public/admin/tool/phpunit/cli/util.php --drop
   mdl phpunit-init m502
   docker exec m502-db-1 psql -U moodle -d moodle -tAc \
     "select indexname from pg_indexes where tablename='t_enrol_apply_submission'"
   ```
2. **Submitted values are not put through `format_string()`** on their way into the
   notification (slice 3). `format_string()` runs `strip_tags()`, which deletes everything from
   a bare `<` onwards — an applicant typing `A<B and R&D` had the approver read `A`. The
   template double-stashes instead, which is lossless and correct for that sink. Stripping
   belongs at the `PARAM_TEXT` boundaries in slices 7 and 8.
3. **The deny list is enforced in `fields::pool()` only** (slice 3). A second check inside
   `resolve()` was written and then deleted: `pool()` is the only way a key reaches that loop,
   so the check was unreachable, and a mutation proved it reddened no test at all.
4. **`check_access_for_dynamic_submission()` refuses every log-in-as session** (slice 4),
   which is stricter than core. `enrol/index.php` guards only when the log-in-as context is a
   course, so an administrator who used "Log in as" from a profile page walks past it.
5. **`amd/src/profile_save.js` was not written** (slice 5). The plan lists it as progressive
   enhancement over a POST and redirect; the POST and redirect are implemented and give a clear
   confirmation. Add it deliberately if wanted.
6. **Per-field confirmation up to three editable fields, one shared checkbox above that**
   (slice 4, decided by the user mid-slice). `application_form::CONFIRM_EACH_UP_TO`.
7. **Foreign keys on `userid` and `decidedby` only** (slice 6); `courseid`, `enrolid` and
   `userenrolmentid` get plain indexes. A foreign key in Moodle creates no database constraint —
   `lib/ddl/sql_generator.php` has `$foreign_keys = false` and no generator overrides it — so it
   is documentation plus an index. Declaring one for a reference the design deliberately outlives
   would document an integrity claim that is false by construction. The two user columns are
   different: they are zeroed on course deletion and the row goes whole on erasure, so neither
   ever dangles.

   The key on `decidedby` is what makes core's privacy table coverage test see that column at
   all: it looks for a column literally named `userid` or a single-field foreign key to
   `user.id`, and never inspects indexes. Measured by removing the table from `get_metadata()`
   and reading the failure: `enrol_apply_submission (userid, decidedby)`, where before the key it
   was `(userid)` alone. Note an `<INDEX>` and a `<KEY>` over the same field set is a real defect
   — `install.xml` loads it silently, but `xmldb_table::addKey()` throws when `db/upgrade.php`
   builds the same table object — so adding the key meant deleting the `decidedby` index.
8. **The `course_deleted` observer sweeps orphans, not the trail** (slice 6). The plan casts it
   as the pseudonymisation safety net for the plugin-disabled case. It cannot be: hook callbacks
   are loaded from every plugin directory on disk with no enabled check
   (`lib/classes/hook/manager.php`), so `before_course_deleted` fires whatever the plugin's
   state, and `test_the_trail_is_pseudonymised_even_while_the_plugin_is_disabled` holds that.

   The real gap is elsewhere and the plan names it correctly one paragraph later:
   `enrol_course_delete()` skips a disabled plugin's `delete_instance()` but core deletes the
   `enrol` and `user_enrolments` rows anyway, orphaning `enrol_apply_applicationinfo` and
   `enrol_apply_groups`. The observer deletes rows whose parent is gone rather than rows of one
   course, because at `course_deleted` time there is no `enrol` row left to join a courseid to.
9. **`retentiondays` keeps the plan's key but stores seconds** (slice 6).
   `admin_setting_configduration` always stores seconds whatever unit is chosen. The name was
   kept so that later slices referring to it still find it; the trap is neutralised by making
   `submission::retention_seconds()` the only reader, pinned by
   `test_the_retention_setting_is_read_as_seconds`.
10. **No `<> 0` filter in the privacy user lists** (slice 6). `decidedby` is 0 on every undecided
    application, so a filter looks required — but `userlist::add_from_sql()` wraps the query in
    `JOIN {user} u ON u.id = target.<field>` and no user has id 0. Mutation-checked: removing the
    filter reddens nothing, which is the definition of an unreachable guard. Removed, with the
    reason recorded in the code, and the *consequence* pinned instead by
    `test_an_undecided_application_does_not_report_user_zero` — which still catches a switch to
    `add_userids()`, which does no filtering.

## Defects found and deferred

- ~~**The backup's `users` gate does not match core's.**~~ Fixed on
  `fix/backup-kept-roles-gate`. The role check is nested INSIDE the users gate rather than
  placed beside it: core writes its own kept-role enrolments even with user data off, but in
  that cell the restore reaches the destination with no apply instance and no user enrolment,
  so anything this plugin wrote there would be personal data in an archive with nowhere to go.

  **Two things I got wrong first, both caught by the review of the fix itself.** The first
  version matched core exactly and so introduced that write — a regression in the one cell where
  the fix buys nothing. And the whole PR, the CHANGELOG, `CLAUDE.md` and a test docblock all
  stated that the leaked rows are "dropped on restore, so the only place this is visible is the
  archive file", used as the argument for not writing a restore-based test. That is true for
  `enrol_apply_applicationinfo` and **false** for `enrol_apply_submission`: the first is keyed on
  the user-enrolment mapping, the second on the USER mapping, and a kept-roles copy annotates
  every course-context role assignment into users.xml, so the second resolves. Measured against
  the pre-fix code, the excluded applicants' comments and profile snapshots were inserted into
  the copied course's database under live user ids. `test_an_excluded_applicant_does_not_reach_the_copied_course`
  is the assertion that prose had argued out of existence, and it reddens against the pre-fix gate.
- **`local_unifiedgrader` fails core's `test_table_coverage`** on both m501 and m502 — the same
  defect class fixed here, in another fleet repo. Eleven `local_iv*` plugins fail
  `test_all_providers_compliant` on the same stacks. Neither is this repo's to fix; noted so the
  next person running that suite knows the 12 failures are pre-existing and unrelated.

## Corrections found in the plan and in fleet documentation

- `~/dev/CLAUDE.md` overstates the auth-lock claim. `auth_manual` merges legacy *under* modern,
  so the **modern** component wins, and a normally installed site already stores every
  `field_lock_*` there as `unlocked` — the legacy key alone changes nothing. Reading through
  the auth plugin object is still right, but the two reads diverge only where the modern key is
  absent. Measured on 5.2.
- The plan's `idnumber` exclusion reason was wrong (`tool_uploaduser` matches on username, or
  email under `uumatchemail`). The exclusion stands on other grounds; the comment is corrected.
- `CLAUDE.md` named `useredit_update_user_profile()`, which exists on neither 5.1 nor 5.2.
- `~/dev/CLAUDE.md` says metadata-only "fails the core privacy compliance test that runs in CI".
  **That test does not run in this plugin's CI at all.** moodle-plugin-ci resolves to
  `--testsuite enrol_apply_testsuite`, which is this repo's `tests/` directory only, and nothing
  in the MAH workflow invokes the core suite. The gate is real but must be invoked deliberately:

  ```sh
  docker exec m502-webserver-1 php /var/www/html/vendor/bin/phpunit --no-coverage \
    --filter test_table_coverage --testsuite core_privacy_testsuite
  ```
- The plan predicts that moving the pseudonymisation from the hook to the `course_deleted`
  observer reddens `test_before_course_deleted_pseudonymises_the_rows`. **It does not** — both
  fire inside `delete_course()`, so the row ends up identical either way and every
  straightforward test passes with the call moved. Telling them apart needs `redirectHook()`:
  silence the hook and assert the row is then NOT pseudonymised. Same shape for the backup's
  `users` gate — a restore-based test passes with the gate deleted, because with users excluded
  the restore drops the record anyway; the archive file has to be read directly.

## Working practices learned the hard way

- **Prune worktrees before any test run.** `isolation: "worktree"` creates them at
  `<repo>/.claude/worktrees/`, inside the directory bind-mounted into Moodle, so Moodle scans a
  whole second copy of the plugin. Behat reported 6 scenarios for a 3-scenario file.
  `git worktree list`, then `git worktree remove --force` and `git worktree prune`.
- **Never pipe a long-running command through `tail`.** It buffers, so a hung run looks
  identical to a running one. Four stacked Behat runs accumulated before that was noticed.
  Redirect to a file instead.
- **Judge Behat by the scenario count, never the exit status**, and treat an unexplained change
  in that count as contamination until proven otherwise.
- **`mdl ci --only <gates>` is not a verdict.** Every edit after it — including a comment — is
  unverified until the matrix runs. And read the matrix's per-leg logs: its summary line has
  contradicted its own detail.
- **`gh pr create` targets the upstream fork parent.** Always pass
  `--repo uaiblaine/moodle-enrol_apply`.
- **A mutation that reddens nothing is a finding, not a formality.** Two of slice 6's seven
  produced no failure, and both times the test was at fault rather than the guard: one could not
  see which of two callbacks did the work, the other was watching the restored course instead of
  the archive. Treat a green mutation run as "the test does not hold this" until proven
  otherwise, and check that the mutation actually took effect before concluding anything — a
  schema mutation in particular can silently not have been applied.
- **`mdl phpunit-init` does not rebuild an existing table.** Drop the test database when a
  mutation touches `db/install.xml`, and confirm the change landed by reading `pg_indexes`.
- **`mdl ci --matrix` deletes its per-leg logs when every leg passes**, so a green run cannot be
  audited afterwards — and this repo's own rule is to read those logs rather than the summary
  line. Snapshot them while it runs, and clear stale `mdlci-matrix-*` directories first: the
  first attempt at this copied a run from two days earlier and produced a convincing report of
  four failing legs in a suite of 45 tests that no longer exists.
- **The summary line really does lie, and it was caught doing it in this slice.** A run printed
  `502-php8.4-pgsql PASS` and `501-php8.4-pgsql PASS` for both static legs while their logs
  showed `-- phpcs: FAILED` and ended `==== mdl-ci: FAILURES (see above) ====`. The only signal
  in the summary was that the overall block said FAILURES and kept the logs, with every
  individual leg marked PASS. One lowercase inline comment would have gone to GitHub as a red
  build. Grep the kept logs for `: FAILED`; never read the leg column alone.
- **Never run the matrix while editing the working tree.** `mdl ci` reads the live tree, so
  legs that start later pick up half-finished edits. One run reported both privacy tests failing
  on all three 5.01 legs and passing on 5.02, which reads exactly like a branch divergence and
  was nothing of the kind.
- **A five-reviewer adversarial pass on a finished, green, mutation-checked slice still found
  nine real defects**, several of them in the privacy behaviour the slice existed to provide.
  Green tests and a clean matrix say the code does what the tests say; they say nothing about
  whether the tests say the right thing. Reviewing the *fix* for the deferred defect then found
  two more, one of them a regression that fix had introduced — so the pass is worth running on
  small changes too, not only on slices.
- **The most expensive thing in this repo is a confident wrong sentence, not a bug.** Both
  adversarial passes found one, and both times it was load-bearing in the same way: it argued
  the next person out of the test that catches the problem. "The observer deliberately does not
  notify" (wrong, it does). "The leaked rows are dropped on restore, so the only place this is
  visible is the archive file" (wrong for the durable trail, whose key is the user mapping —
  and used explicitly as the reason not to write the restore-based test that reddens against
  the unfixed code). Both survived review, because a sentence that explains *why* something
  need not be checked reads exactly like diligence.

  The practical rule: when a comment says a thing cannot happen, or cannot be tested, treat that
  as the highest-value claim in the diff and measure it. Prose in this repo is load-bearing by
  design, which is precisely what makes a wrong sentence worse than no sentence.
