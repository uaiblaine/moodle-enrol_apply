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

## Open

| PR | Branch | State |
|---|---|---|
| [#8](https://github.com/uaiblaine/moodle-enrol_apply/pull/8) | `fix/restore-group-membership` | Complete and green locally (118/118, mutation-checked). Awaiting CI, then merge. |

## In progress

**Slice 6 — durable snapshot, privacy, backup, lifecycle.** Branch
`feature/slice-6-durable-trail`, commit `82f1671`, pushed. **Not mergeable**: the table
exists in `db/install.xml` but has no upgrade step, nothing writes to it, and the privacy
provider does not declare it — core's privacy compliance test would fail.

Done so far: only `db/install.xml`.

Still to do, per the plan's slice 6 file table:

- `db/upgrade.php` — create the table, backfill one row per existing pending `user_enrolments`
  on an apply instance, savepoint == `$plugin->version`
- `classes/local/submission.php` — status constants, state vocabulary, insert/update/read
- `lib.php` — `apply()` inserts the row in the same transaction; `confirm_enrolment()`,
  `wait_enrolment()`, `cancel_enrolment()` stamp status/`timedecided`/`decidedby`;
  **`delete_instance()` stops purging submission rows** (inverts current behaviour — needs a
  mutation test and a README note)
- `db/hooks.php` — add `\core_course\hook\before_course_deleted` → pseudonymisation
- `db/events.php` (new) + `classes/observers.php` (new) — the `course_deleted` safety net for
  the plugin-disabled case only
- `db/tasks.php` + `classes/task/purge_submissions.php` — daily, chunked, time-budgeted,
  never throws, sweeps on `timecreated`
- `classes/privacy/provider.php` — declare the table, cover **two roles** (`userid` and
  `decidedby`), and fix the existing export-path defect where two apply methods in one course
  overwrite each other
- `backup/moodle2/*` — submission rows inside the **users** block, `annotate_ids` for both
  user columns, drop the row when a user mapping fails
- `settings.php` — `retentiondays` as `admin_setting_configduration`, default 30 days, 0 = keep forever
- `manage_table.php` / `info_table.php` — read the comment through the new table where
  appropriate; **constructor signatures unchanged**
- lang (en + pt_br in lockstep), README, `CLAUDE.md`
- tests: `provider_test.php:108-109` (**both** lines), `backup_test.php`, `lib_test.php`,
  `tests/task/purge_submissions_test.php`, `tests/hook_callbacks_test.php`

## Decisions taken that depart from the plan

Record them here so a later reader does not "fix" them back.

1. **No `UNIQUE (courseid, userid)` on `enrol_apply_submission`.** The plan specifies both that
   key and pseudonymising on course deletion by zeroing `userid`. They are incompatible: a
   deleted course with two applicants gives two rows with the same `courseid` and `userid = 0`.
   Measured, not reasoned — a scratch table with that key raises `dml_write_exception` on the
   second row. It would also turn a restore into an existing course, where a duplicate is
   legitimate, into a fatal restore error. Uniqueness is enforced in code by the lock added to
   `submit_application()` in slice 4, which is what the plan credited the key with providing.
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

## Corrections found in the plan and in fleet documentation

- `~/dev/CLAUDE.md` overstates the auth-lock claim. `auth_manual` merges legacy *under* modern,
  so the **modern** component wins, and a normally installed site already stores every
  `field_lock_*` there as `unlocked` — the legacy key alone changes nothing. Reading through
  the auth plugin object is still right, but the two reads diverge only where the modern key is
  absent. Measured on 5.2.
- The plan's `idnumber` exclusion reason was wrong (`tool_uploaduser` matches on username, or
  email under `uumatchemail`). The exclusion stands on other grounds; the comment is corrected.
- `CLAUDE.md` named `useredit_update_user_profile()`, which exists on neither 5.1 nor 5.2.

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
