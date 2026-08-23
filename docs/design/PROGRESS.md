# Implementation progress

Running state of the eleven-slice plan in
[`implementation-plan.md`](implementation-plan.md). Update this file as slices land.

Last updated: 2026-08-23.

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
| [#10](https://github.com/uaiblaine/moodle-enrol_apply/pull/10) | — | The kept-roles backup gate |

## In progress

**Slice 7 — the system report in the course**, branch `feature/slice-7-course-report`.

The report itself is written: the entity, the formatters, the system report, `report.php`, the
`enrol/apply:viewreports` capability and the two entry points in `lib.php`. 188/188 PHPUnit on
both m501 and m502.

Two items in the plan's slice 7 are **deliberately not in it**, each for a reason found by
measuring rather than by reading the plan. Both are recorded under "Deferred out of slice 7".

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

Slice 8 — the site-level datasource — which is also where `info.php` should be reconsidered,
because it is the first point at which that page's site-wide scope has anywhere to go.

Two things slice 8 inherits from slice 7 and should not rediscover:

- **The snapshot column's masking is fail-closed by default and will look broken.** A
  datasource adds the entity's columns directly and never calls `set_callback()`, so the
  snapshot renders the applicant's name parts and nothing else, for everybody including an
  administrator. That is deliberate — see "Defects found in slice 7's own code" — and slice 8
  has to open it on purpose, with a context to judge the reader in.
- **A custom report over this entity has no `{user}` join forced on it**, so pseudonymised
  records (`userid` 0, what a deleted course leaves behind) are not excluded for free the way
  they are in the course report. Slice 8 needs its own exclusion.

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

11. **The report is scoped by its context, not by the `id` in its URL** (slice 7). The `id`
    chooses the course and authorises the request; the report then lists that *course's*
    applications rather than that enrolment method's. A course's applications are a
    course-level question, both icons in a course with two apply methods should open the same
    thing, and where a course has more than one the report offers a filter to narrow by method.
    The `id` is not carried into the query at all — it cannot be, because the pages that fetch
    every subsequent row never see it.
12. **Identity fields are masked by absence, never by a display callback** (slice 7). Core's
    own `get_identity_columns($context)` and `get_identity_filters($context)` return nothing
    without `moodle/site:viewuseridentity`, so the column, its filter and its sort are all
    gone rather than blank. A callback would be unsound here: filtering and sorting are SQL and
    never reach one, so a reader would recover a hidden value by narrowing on it and reading
    the row count, or simply by sorting.
13. **The snapshot column is the single exception to that rule, and only because it earns it**
    (slice 7). It carries no filter and cannot be sorted, so there is no SQL path around its
    callback. `test_the_snapshot_column_has_no_filter_and_is_not_sortable` holds that
    precondition; if it ever reddens, the masking is unsound and has to move.
    The decision about *what* the reader may see is taken in the report, where the course
    context is in hand, and passed to the callback as its argument — an entity has no context
    and could only ask about the system one, which would show nothing at all to a reader
    legitimately granted the capability in their own course.


## Deferred out of slice 7

Both were in the plan's slice 7 and both are out of it deliberately. Neither is abandoned;
each is recorded here with what a later reader would otherwise have to measure again.

### The `info.php` refactor — wait for slice 8

The plan and the previous session's handoff both expected two structural losses (the
`customtext2` comment header and the A-Z initials bar). Measuring the page against the report
surface found **ten** observable changes, three of them regressions rather than losses:

- **Rows disappear.** `info_table.php` lists `{user_enrolments}` filtered to undecided. The
  report reads `enrol_apply_submission`. The slice 6 backfill covered every row that existed at
  upgrade time, and since slice 6 every application writes a record — so the gap is narrower
  than it first looks, and worth stating precisely rather than loosely. `enrol/editenrolment.php`
  **cannot create** anything: it takes `required_param('ue', PARAM_INT)` and loads an existing
  row `MUST_EXIST`. What it can do is suspend an enrolment that never had a record — one
  approved before the upgrade, which the backfill deliberately skipped because its predicate is
  the queue's — and `hook_callbacks.php` returns early for any status that is not active, so
  nothing writes one then either. Restoring an archive older than the trail is the second
  route. Both leave rows `info.php` lists and the report does not.
  **And note the converse on the same path**, which is a defect in its own right and is not
  fixed: a *post*-upgrade application suspended through that screen keeps its record, still
  stamped `STATUS_APPROVED`, so the report shows it with an outcome that is no longer true.
- **Editing teachers lose the page** if it moves to `enrol/apply:viewreports`, which is
  manager-only by archetype and deliberately so. That is a permission regression on upgrade
  with no setting to restore it.
- **The site-wide scope has nowhere to go.** `info.php` with no `id` lists every apply
  instance on the site under a system-context capability check.
  `course_applications::can_view()` refuses a non-course context, and its base condition binds
  `courseid` to `get_context()->instanceid`, which is **0** for a system context — so it would
  match nothing, not everything. Whatever happens to the `id` scope, the old table survives for
  this one, and the plugin ends up with three listing surfaces instead of two.

Also, and separately: a `manageapplications`-gated report **must not** inherit the entity's
`submission:snapshot` column. That column is masked by `moodle/site:viewuseridentity`, which is
a different question from `viewreports` — inheriting it hands every editing teacher exactly the
disclosure the separate capability exists to withhold.

The rest are real but ordinary: the user picture, the waiting-list row highlight
(`system_report::get_row_class()` is available and not overridden), the per-instance
`customtext2` header (`column::set_title()` takes only a `lang_string` and the class is
`final`; `set_report_info_container()` is the slot), the A-Z bars
(`system_report_table` calls `initialbars(false)` unconditionally — 5.1 `:173`, 5.2 `:170`),
page size 50 to 30, sort flipping from `applydate` ASC to `timecreated` DESC, and orphaned
table preferences keyed on the old uniqueid.

One trap for whoever does it: `tests/local/bootstrap_compat_test.php` reads `info_table.php`
with `file_get_contents()` and **no `is_file()` guard**, so deleting the file reddens
`test_every_table_class_defines_a_header_column` with a warning that is fatal under
`--fail-on-warning`.

### The bulk-action bar — its own PR, and probably on `manage.php`

The plan put a bulk bar on the report. Measured against the plugin's own methods, the report is
the wrong surface for it:

- **The identifiers do not match.** The only unambiguous per-row handle the report has is
  `enrol_apply_submission.id`. `confirm_enrolment()`, `wait_enrolment()` and
  `cancel_enrolment()` accept `user_enrolments.id` only, and `submission::decide()` fans out
  across **every** record sharing that id. Posting `userenrolmentid` from the report is the one
  design that cannot be made correct.
- **Most of an aged report is inert, silently.** An approved record's enrolment is live but
  *active*, and a cancelled one has none at all; either way all three methods `continue` past
  them, because both lookups admit only `ENROL_USER_SUSPENDED` (plus `ENROL_APPLY_USER_WAIT` for
  confirm and cancel) — while `manage.php` still redirects with a success notice.
- **A foreign id is a fatal; a stale one is merely skipped.** `get_pending_user_enrolment()`
  uses `IGNORE_MISSING` and the callers `continue` on a miss, so a dangling id is inert. But
  that lookup carries no enrol-method predicate, so a suspended enrolment belonging to *another*
  method passes it and then reaches `get_record('enrol', [..., 'enrol' => 'apply'], MUST_EXIST)`
  and throws. Unreachable from the report's own rows; reachable from any hand-built POST.
- **The capabilities disagree.** The report is gated on `viewreports` (manager); the actions on
  `manageapplications` (editing teacher and manager). By default a manager can do both and an
  editing teacher can act but cannot see the report.

`manage.php`'s queue has none of these problems: every row is a live `{user_enrolments}` row by
construction, and its checkbox value already *is* what the methods take. That is what the plan's
slice I was for, and it is where the bar belongs.

If it is ever wanted on the report anyway, the two things that make it possible are
`set_checkbox_toggleall()`'s documented `null` return (suppresses the checkbox per row, which is
how core's `cohorts` report does it) and resolving record id to live user-enrolment id
server-side with a re-check. Note also that the report's **table** is not inside a form — the
only `<form>` in the markup is the filters moodleform in the dropdown, which posts over AJAX and
cannot host the bar — so the bar has to be a sibling that copies the checked values into its own
field, and that paging,
filtering and sorting all fire `core_table/dynamic:tableContentRefreshed` and wipe the selection.

## Defects found in slice 7's own code

Found by mutation testing during the slice, not by review afterwards. All fixed; each fix
reddens exactly one named test.

- **The snapshot masking was fail-open on every path that did not go through the report.** The
  formatter's third parameter defaulted to `false`, and its docblock said at length that this
  was the restrictive state and that an entity used without the report would therefore "show
  less rather than more". It did the opposite. A column callback is never invoked with three
  arguments: `column::format_value()` passes the registered argument **always**, and
  `add_callback()`/`set_callback()` default it to `null` — so the parameter default was
  unreachable and `null`, which the formatter read as "show everything", was what the entity's
  own bare registration passed. `null` is now the restrictive state and `ALL_FIELDS` the
  permissive one. This mattered beyond the slice: slice 8's datasource reuses this entity and
  would have inherited the open version.
- **The snapshot silently truncated any value containing a bare `<`.** It ran each label and
  value through `format_string()`, which under the default `formatstringstriptags` calls
  `strip_tags()`, which deletes from the `<` onwards. Measured: an applicant who typed
  `A<B and R&D` had the cell render `City: A`. This plugin's own `fields::submitted_values()`
  documents that exact loss and stores the value unstripped because of it, so the report was
  undoing a decision already taken one layer up. It escapes instead, which is lossless and is
  what a raw-HTML cell needs anyway.
- **`s()` was not the right escaper, and the reason is the download.**
  `base_export_format::format_text()` runs `html_entity_decode($text, ENT_COMPAT)` before it
  strips, and `ENT_COMPAT` by definition leaves single quotes alone — so `s()`'s `&#039;`
  reaches the CSV verbatim and a manager downloads `O&#039;Brien`. `htmlspecialchars()` with
  `ENT_COMPAT` round-trips every case measured, and the value lands in a text node where a bare
  apostrophe is harmless.
- **The same decode-then-strip order rules out markup as a line separator.** An escaped `&lt;`
  decodes back to a real `<` and the strip pattern then eats to the next `>` on that line, which
  a `<br />` supplies. Measured: `nl2br()` turns `City: A&lt;B and R&amp;D` into `City: A` in the
  export — but only when a further line follows, since `nl2br` inserts nothing after the last one
  and the run needs a `>` to close on. A bare newline exports intact either way — the pattern excludes `\r\n` precisely so a
  newline ends the run. The pairs are separated by a literal newline and the line breaks are
  drawn in `styles.css` with `white-space: pre-line`, on the cell, where the export cannot see
  them.
- **The comment column had both defects the snapshot column was shaped to avoid.** It used
  `format_text(FORMAT_PLAIN)`, which sounds like it strips markup and does not: its whole branch
  is `s()`, `rebuildnolinktag()`, a double-space substitution and `nl2br()`
  (`lib/classes/formatting.php:243-248`, identical on both branches). So `s()` put `&#039;` into
  the download and the injected `<br />` supplied the `>` that lets a decoded `<` swallow the
  rest of its line. It goes through the same escaper now. Nothing caught this for a while
  because no test asserted the comment cell's contents at all — swapping the implementation left
  the suite green, which is how it was found.
- **The `userid <> 0` base condition was unreachable.** Deleting it left the whole suite green,
  because the report's INNER join onto `{user}` already excludes id 0. Removed rather than kept:
  a guard no test can hold reads as protection while proving nothing. The comment in its place
  names the join as the mechanism, and the test still holds the behaviour end to end — widening
  that join to a LEFT one reddens it, which was measured.

### Tests that passed while proving nothing

Every one of these was written for the defect it failed to catch.

- The angle-bracket test asserted `assertNotEmpty()` and passed against a cell rendering a bare
  `A`. Now `test_a_raw_angle_bracket_in_a_snapshot_value_reaches_the_reader_whole`.
- The separator test used `strip_tags()` as a stand-in for the export. They are different
  functions and the difference is the whole finding; it now runs the cell through
  `base_export_format::format_text()` itself, as
  `test_the_snapshot_pairs_survive_the_download`.
- `test_a_pseudonymised_record_is_not_listed` passed against the deletion of the line it was
  named after.
- The identity-filter half of `test_an_identity_column_is_absent_without_viewuseridentity` was
  unfalsifiable, because the report offered identity filters to nobody. It offers them now —
  which is what the plan specified — so the assertion has a control.
- The snapshot half of the masking had **no** test at all, which is what the previous session's
  handoff opened with.

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

### A correction to this plugin's own earlier documentation

**An applicant cannot type a bare `<` into an application, and two places in this repo said
otherwise.** `classes/local/fields.php` and this repo's `CLAUDE.md` both illustrate the
`format_string()` trap with "an applicant who types `A<B and R&D` has their answer delivered as
`A`", and use it to justify storing the submitted value unstripped. The justification survives;
the illustration does not. Every editable field on the application form is `PARAM_TEXT`
(`classes/form/application_form.php:260`, and `:168` for the comment), and `formslib` cleans the
whole submission through `clean_param()` before `get_data()` — measured,
`clean_param('A<B and R&D', PARAM_TEXT)` is `'A'`. The tail is gone at submission, whatever the
storage layer then does.

The route that really can put such a value in the table is a **restore**, which writes
`userinfodata` and `comment` verbatim out of an archive this site did not produce
(`backup/moodle2/restore_enrol_apply_plugin.class.php:136-137`). That is the reason the report's
cells escape rather than strip, and it is a better reason than the one that was written down.

Both sentences are corrected in this slice. It is worth noting how the error travelled: slice 7's
formatter repeated it almost verbatim, because it read as settled fact in a neighbouring file.

The same paragraph in `CLAUDE.md` also says a report column "has to satisfy `PARAM_TEXT`" and
must therefore strip. It does not — a Report Builder cell is raw HTML, where escaping is both
safe and lossless. `PARAM_TEXT` binds a web service return, not a report column.

### Slice 7's own corrections to the plan

Each measured on both m501 and m502, not reasoned about.

- **The two stress helpers the plan mandates cannot be used.** `datasource_stress_test_columns()`
  and `datasource_stress_test_columns_aggregation()` both hand their argument to the report
  generator's `create_report()`, which reaches `helpers\report::create_report()` and forces
  `$data->type = datasource::TYPE_CUSTOM_REPORT`. There is no core stress helper for a system
  report on either branch. `test_every_column_renders_for_every_status` stands in for them.
- **The plan names the wrong web service.** `core_reportbuilder_retrieve_report` is the *custom*
  report service; system reports use `core_reportbuilder_retrieve_system_report`, and neither is
  ajax-enabled, so neither is browser-reachable. Every sort, filter and page turn goes through
  `core_table_get_dynamic_table_content`. The download is a third path and re-creates the report
  through `system_report_factory::create()` in `reportbuilder/download.php`, so `can_view()`
  gates it as well.
- **`can_view()` runs earlier than the plan says.** On that service `set_filterset()` constructs
  the report — and therefore calls `require_can_view()` — *before* the service's own
  `validate_context()` and `has_capability()` two lines later
  (`lib/table/classes/external/dynamic/get.php:229-231`, the same lines on 5.1 and 5.2). So
  `can_view()` is the first gate and effectively the only one, and must not assume
  `require_login()` ran against the course.
- **`get_parameter()` is not merely untrusted, it is forgeable**: the filterset declares
  `parameters` as `PARAM_RAW` and `set_filterset()` json_decodes it straight into the report.
  Scope on `get_context()->instanceid` only.
  `test_a_forged_itemid_or_parameter_cannot_widen_the_report` drives it as a forger would.
- **The status trap is broader than one value.** Core's enrolment status map is wrong for all
  four of this table's values, which is the reason not to borrow its column or its formatter.
  `core_course\reportbuilder\local\formatters\enrolment` is additionally `@deprecated since
  Moodle 5.2` (MDL-87000) and emits a notice per call that 5.1 does not — but **do not expect CI
  to catch that half**: the notice arrives through `debugging()` at `DEBUG_DEVELOPER`, PHPUnit
  re-emits it at teardown as `E_USER_NOTICE`, and Moodle's `phpunit.xml.dist` sets
  `failOnDeprecation` and `failOnWarning` but not `failOnNotice`. An earlier draft of this note
  claimed it reddens the 5.02 leg; it probably does not.
- **A 5.2-shaped entity is a compile-time fatal on 5.1.** 5.1's `entities\base` has three
  abstracts — `get_default_tables()`, `get_default_entity_title()`, `initialise()`; 5.2 has two
  and drives the rest from `get_available_columns()`/`_filters()`/`_conditions()`, which **do not
  exist on 5.1**. Overriding `initialise()` is the one shape both accept. Note which runner is
  blind to this, because the intuition is backwards: `mdl ci` with no flags defaults to
  **MOODLE_501_STABLE** (`moodle-dev/bin/mdl-ci:68`), so it is the leg that catches it. What
  never sees it is the m502-only local loop — `mdl phpunit m502`, `mdl behat m502` — which is
  how this repo is worked day to day.
- **`set_is_available()` exists on `column` and `filter` only** — a condition *is* a filter. An
  unavailable element is omitted entirely and contributes no SQL.
- **The status filter needs four options, not the plan's three** (pending, approved, waiting,
  cancelled).
- **The capability did not exist.** `enrol/apply:viewreports` is added by this slice.
- **"The CSV export strips tags" is true in effect and wrong in mechanism, and the mechanism is
  what matters.** There is no `strip_tags()` anywhere on the system report download path. It is
  `base_export_format::format_text()` (`lib/table/classes/base_export_format.php:82-88`,
  identical on both branches), which runs `html_entity_decode($text, ENT_COMPAT)` **first** and
  only then removes tag-shaped runs with a pattern that excludes `\r\n`. Three consequences the
  plan could not have had: markup as a separator is worse than useless because an escaped `&lt;`
  decodes back and the run eats to the next `>` on its line; `s()` is the wrong escaper because
  `ENT_COMPAT` leaves `&#039;` undecoded; and a literal newline is both a working separator and
  the thing that ends such a run.
- **`test_csv_export_separates_the_snapshot_fields` should not use `strip_tags()` as a proxy for
  the export.** They are different functions, and a proxy test passes against markup that
  measurably loses data in a real download.

## Working practices learned the hard way

- **`mdl ci --matrix`'s per-leg PASS/FAIL column was not evidence, and is now fixed.** Recorded
  because the diagnosis is worth keeping even though the bug is gone. The per-leg runner script
  is echoed into each leg's log, so every log contained the literal string `ALL STEPS PASSED`
  inside the script's own source line — and the summary decided each leg by grepping the log for
  it, so it printed PASS for every leg, always. The exit code was never wrong: `rc` came from
  `wait` on each leg's process, and the grep could only add a failure, never clear one. What the
  column cost you was knowing *which* leg had failed.
  Fixed in `moodle-dev` (`Decide matrix leg PASS/FAIL from an exit status, not the log`): each
  leg now writes its status to `$logdir/$tag.rc` and the summary reads that file, treating a
  missing status file as a failure rather than a pass. **The column can be trusted again.**
  Two things that outlive the fix. A hand audit of a leg log still needs anchored patterns,
  because `grep ': FAILED'` matches the runner's own `run_step` definition — use
  `^-- [a-z]+: (OK|FAILED)$`. And the logs of a **passing** run are still deleted
  (`rm -rf "$logdir"`), so snapshot them while it runs if you intend to verify rather than trust.
- **The stacks are shared, and another session working in `moodle-dev` can stale this one's test
  site.** Measured on 2026-08-23: a second session brought up m53 and landed a change to
  `mdl upgrade`, and m502's PHPUnit environment began reporting "initialised for different
  version" mid-session — twice, including once immediately after a successful `mdl phpunit-init`.
  Running `admin/tool/phpunit/cli/init.php` in the container directly cleared it. Two corollaries:
  a `mdl` invocation can also fail with a shell parse error while `bin/mdl` is being written, and
  that failure has nothing to do with the plugin; and an unexplained environment change
  invalidates a local run, so re-run rather than reason about it.

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

  Acting on that, a third pass audited **144 factual claims** added by this work across
  `CLAUDE.md`, the code comments, `README.md`, `CHANGELOG.md` and the test docblocks, with a
  second agent trying to overturn each flag. Ten survived, and the shape of them is the useful
  part:

  - **One wrong fact, repeated in four places.** The lock in `submit_application()` was
    described as enforcing one live application per *course* and user. It is keyed on the
    INSTANCE and the user, and a course may carry several apply instances — so a user can hold
    two pending rows sharing `courseid` and `userid`. That is a third, independent reason the
    unique key could never have worked, and it had been written up as the reason the key was
    not needed.
  - **A claim about atomicity that was never true.** `submission::create()`'s docblock said the
    row and the enrolment "are created together or not at all". There is no transaction: the
    lock gives mutual exclusion, not atomicity.
  - **A privacy claim that overstated itself.** Pseudonymisation was described as leaving a row
    "nobody can be identified by". `userenrolmentid` is retained, and `logstore_standard` keys
    enrolment events on exactly that id with the userid beside it — so on a site keeping its
    standard log the row is re-identifiable. It is pseudonymisation, not anonymisation, and a
    privacy note is the last place to blur that.
  - **Three operational errors in the README**, each of which would send an administrator to
    the wrong place: the settings page named by a title the plugin does not use (the same file
    names it correctly two sections earlier), the recycle bin attributed to the general backup
    default when it runs in automated mode and reads *Automated backup setup ▸ Include users*,
    and deferral listed among the actions that delete the pending comment, which it does not.
  - **A stale paragraph** still describing the kept-roles gate as an unfixed defect, two commits
    after it was fixed, pointing readers away from the bullet that superseded it.

  None of these was a bug. Every one of them would have cost the next reader time or sent them
  somewhere wrong, which is the same currency a bug is paid in.
