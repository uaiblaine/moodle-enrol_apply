# Claude instructions for `enrol_apply`

This file is auto-loaded as context whenever Claude works in this plugin's directory
tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding style, CI gates,
lang-string rules, the `mdl` environment, git rules) — do not repeat them here. This
file keeps only what is true for this plugin.

Plugin context: a Moodle **enrol** plugin ("Course enrol confirmation") that inserts an
approval step into course enrolment. A user applies, optionally leaving a comment and
filling in profile fields; the enrolment is created **suspended**; a manager then
confirms, defers to a waiting list, or cancels it. It owns two tables —
`enrol_apply_applicationinfo` (the per-application comment, keyed by
`user_enrolments.id`) and `enrol_apply_groups` (groups an approved applicant joins) —
and leans on core's enrolment expiry machinery for everything time based. Supports
Moodle **5.1 through 5.2** (`$plugin->requires = 2025100600`,
`$plugin->supported = [501, 502]`). CI is the moodle-an-hochschulen reusable workflow,
one job per supported branch in `.github/workflows/ci.yml` — **update those jobs when
`supported` changes**. Development happens on the m501/m502 stacks; this repo mounts at
`enrol/apply` (see `~/dev/moodle-dev/plugins.conf`).

## Origin

This is a fork of [emeneo/moodle-enrol_apply](https://github.com/emeneo/moodle-enrol_apply).
Up to commit `867e248` the fork was byte-identical to upstream; everything after that is
ours. Upstream had accumulated several half-applied merges, and a good part of the work
here was finishing or removing them — when something looks arbitrary, check the upstream
history before assuming it was deliberate.

## Commands

```sh
mdl ci moodle-enrol_apply                # full CI locally before any push
mdl phpunit m502 enrol_apply             # targeted tests
mdl behat m502 @enrol_apply              # Behat smoke tests
mdl grunt m502 enrol/apply               # rebuild amd/build (commit with src)
mdl purge m502                           # after template or renderer changes
```

## Code layout

```
lib.php                      enrol_apply_plugin: the whole state machine
classes/form/application_form.php  what the applicant fills in, in a modal or on apply.php
apply.php / applied.php      the no-JavaScript transport and the acknowledgement
edit.php / edit_form.php     per-course instance configuration
manage.php / manage_table.php   the approval queue and its bulk actions
info.php / info_table.php    read-only listing of submitted comments
renderer.php                 page rendering plus the notification e-mail body
templates/                   manage, info, application_notification
classes/hook_callbacks.php   reconciles approvals made outside confirm_enrolment()
classes/local/applications.php  mentee lookup shared by the queue
classes/local/submission.php the durable application record: constants, writes, reads
classes/observers.php        course_deleted, orphan cleanup for the plugin-disabled case
classes/privacy/provider.php full provider: two tables, two personal-data roles
classes/task/                sync_enrolments, send_expiry_notifications, purge_submissions
backup/                      group mappings, comments and the durable trail, see below
```

## Architecture gotchas

- **`user_enrolments.status` carries a third value.** `ENROL_APPLY_USER_WAIT = 2` means
  "on the waiting list". Core only knows 0 (active) and 1 (suspended) but tests
  `status != ENROL_USER_ACTIVE` everywhere, so the extra value is inert to core and
  visible only to this plugin. Any query filtering applications must use
  `status != ENROL_USER_ACTIVE`, never `status = ENROL_USER_SUSPENDED`, or deferred
  applications vanish from the queue.

- **Authorisation is per row, not per page.** `manage.php` decides which scope you may
  open; `can_manage_application()` in `lib.php` re-checks every single user enrolment
  before acting on it. Three delegation levels are accepted — system, course, and the
  applicant's own **user context**, which is what lets a mentor approve for the users
  they mentor. Keep the per-row check even when the page-level check looks sufficient:
  the bulk form posts a list of arbitrary ids.

- **The mentee path is Moodle's mentor pattern, not groups.** With no `id` and no
  `userenrol`, the queue falls back to the users the current user holds a role assignment
  over *in those users' own contexts* — the same enumeration core uses in
  `blocks/mentees/block_mentees.php`. It has nothing to do with course groups: no query in
  this plugin has ever filtered the queue by group. That branch deliberately skips the
  system-level `require_capability` when it finds mentees; the table is then filtered to
  exactly those user ids. Do not "simplify" the skip away without also re-scoping the
  table, or you turn a mentor view into a site-wide one.
  The listing and the authorisation must stay in agreement: `can_manage_application()`
  authorises on the user-context capability, so the listing enumerates the same thing.
  The earlier cohort-based enumeration broke that agreement in both directions — it
  scanned every cohort peer on each request, and it hid mentees who shared no cohort even
  though the mentor could approve them by id.

- **Group membership follows approval, not application**, and is written with
  `groups_add_member($groupid, $userid, 'enrol_apply', $instance->id)`. The component
  argument is what lets core's `unenrol_user()` clean the membership up again
  (`lib/enrollib.php`, the block that deletes `groups_members` rows by component and
  itemid). Dropping it leaves memberships behind whenever the user has another
  enrolment in the course.

- **`confirm_enrolment()` is not the only way an application becomes active.** Core's
  participants page offers "Edit enrolment", which posts to `enrol/editenrolment.php`;
  that page requires only `enrol/apply:manage` and drives
  `enrol_plugin::update_user_enrol()` directly, so it knows nothing about groups or the
  application row. `classes/hook_callbacks.php` observes
  `\core_enrol\hook\before_user_enrolment_updated` and calls `complete_approval()`
  whichever route was taken. Put any new post-approval side effect in
  `complete_approval()`, never inline in `confirm_enrolment()`, or the two paths drift.
  The observer **does** notify, and the sentence that used to stand here said the opposite.
  `complete_approval()` queues `\enrol_apply\task\notify_approval` (`lib.php`), which is
  reached from both routes; queueing is deduplicated on classname, component and custom data,
  so the two callers cannot produce two messages. It is exactly the kind of sentence a
  reviewer leans on, which is why it is called out rather than quietly corrected.

- **`enrol_plugin::cron()` is dead.** Core declares it empty and nothing calls it. The
  `expiredaction` setting only works because `classes/task/sync_enrolments.php` calls
  `process_expirations()` on a schedule. If expiry stops working, look at the task, not
  at `lib.php`.

- **Never put a `timeend` on a pending application.** `apply()` enrols with
  `timestart = 0, timeend = 0` on purpose; `confirm_enrolment()` stamps the real period on
  approval. The reason is not tidiness: the `ENROL_EXT_REMOVED_UNENROL` branch of
  `enrol_plugin::process_expirations()` (`lib/enrollib.php`) selects on
  `timeend > 0 AND timeend < now` **with no status filter**, so a pending or waiting-list
  row carrying an expiry gets unenrolled instead of decided. This only became reachable
  once `sync_enrolments` started running, which is why upstream never saw it.
  `tests/lib_test.php::test_pending_application_is_not_reachable_by_the_expiry_sweep`
  pins it.

- **A capability declared at one context level is checked at another.**
  `enrol/apply:manageapplications` is declared `CONTEXT_COURSE` in `db/access.php` but is
  also evaluated against the applicant's user context for the mentor path. That works at
  runtime, but the capability does not appear on the user-context permission screens, so
  the delegation has to be configured through a role whose context levels include user.
  Resolving this properly means either a second capability declared at `CONTEXT_USER` or
  dropping the mentor path; it is an open product decision, not an oversight.

- **Do not cache the mentee list in MUC.** The candidate set is now bounded by actual
  mentor role assignments, so the per-candidate `has_capability()` is cheap and the
  remaining cost is one indexed query per request. Caching it would trade that for a
  correctness problem with no clean invalidation point: the answer depends on role
  assignments, role capabilities and context overrides, and a stale entry keeps a revoked
  mentor approving. `has_capability()` is deliberately kept instead of joining
  `role_capabilities`, because only it honours overrides, prohibits and the admin bypass.

- **The backup classes live in `backup/moodle2/`, not `backup/`.** Core resolves them as
  `<plugin>/backup/moodle2/backup_<type>_<name>_plugin.class.php`
  (`backup/util/plan/backup_structure_step.class.php`). Upstream had them one directory
  up, so they were silently never loaded: no error, no warning, just an `enrolments.xml`
  with no plugin element in it. If plugin data stops appearing in a backup, check the
  path before reading the code.

- **A restored course carries a live approval queue — with comments only if the backup
  had users.** Core restores every `user_enrolments` row through
  `restore_user_enrolment()`, and this plugin passes `$data->status` straight through, so
  `ENROL_USER_SUSPENDED` and `ENROL_APPLY_USER_WAIT` rows come back pending whatever the
  plugin does. The comments are keyed by `user_enrolments.id`, for which core registers no
  mapping, so `restore_user_enrolment()` registers one (`enrol_apply_userenrolment`) that
  `restore_enrol_apply_plugin` then resolves. That works because core writes
  `<user_enrolments>` into the enrol element before `add_plugin_structure()` appends the
  plugin's own data; if that order ever changes, `get_mappingid()` returns false and the
  comment is skipped rather than mis-attached. `tests/backup_test.php` pins the whole
  round trip — and note it needs `MODE_SAMESITE` plus an explicit unzip, because
  `MODE_IMPORT` produces no `enrolments.xml` at all.

- **The notification e-mail must not hardcode profile fields.** `icq`, `skype`, `aim`,
  `yahoo` and `msn` were removed from the user table in Moodle 4.0 and reading them cost
  five warnings per notification. `renderer.php` iterates `STANDARD_USER_FIELDS` and
  skips anything the form did not submit, which also covers fields a site has hidden.

- **`table_sql` writes to the output buffer.** The renderer captures it with
  `ob_start()` so it can be handed to a Mustache template as a triple stash. That is the
  one place raw HTML is passed through a template on purpose.

- **The profile fields on the application form are never saved.** The form renders the
  fields the instance asks for, but nothing calls `profile_save_data()` or writes to
  `{user}`. The submitted values only travel into the notification the approver receives.
  That is upstream behaviour and it is deliberate here: turning it into a real profile edit
  would let an enrolment form rewrite user records, which is a much larger change than it
  looks and a privacy question of its own. Do not "fix" it without deciding that
  explicitly. (An earlier version of this note also named
  `useredit_update_user_profile()`; that function exists on neither 5.1 nor 5.2, so it was
  proving nothing.)

- **Which fields are asked for is decided at two levels, and read as an intersection.** An
  administrator sets the site pool in `enrol_apply/allowedfields`; a teacher picks from it
  per instance, stored as a JSON envelope in `customtext4`. `\enrol_apply\local\fields::resolve()`
  recomputes the pool on every read and keeps only the picked keys that survive it. That is
  not defensive style: `customtext4` is carried verbatim by core's backup and copied onto a
  restored instance by `enrol_plugin::add_instance()` with no allowlist, so anyone who can
  restore a course chooses its contents. The deny list is enforced in `pool()` and nowhere
  else, deliberately — a second check inside `resolve()` is unreachable, and an unreachable
  guard no test can hold reads as protection while proving nothing.

- **The notification carries what the applicant typed, and is not put through
  `format_string()`.** Both halves come from the submitted data. `format_string()` runs
  `strip_tags()`, which deletes everything from a bare `<` onwards — so an applicant typing
  `A<B and R&D` would have the approver read `A`, silently. The notification template
  escapes every value through a double stash instead, which is lossless and correct. A
  value that later has to satisfy `PARAM_TEXT` — a web service return, a report column —
  must be stripped at *that* boundary, where losing the tail is a deliberate cost.

- **One form class, two transports, and only one of them runs core's guards.**
  `\enrol_apply\form\application_form` is a `\core_form\dynamic_form`. The modal reaches it
  through core's `core_form_dynamic_form` web service; `apply.php` renders the same class on a
  page for a browser with no JavaScript. `dynamic_form::__construct()` runs
  `validate_context()` and `check_access_for_dynamic_submission()` **only when the AJAX web
  service built it**, so `apply.php` calls the access check itself and the method is widened to
  public for that reason. A guard the second transport cannot reach is not a guard.

  Two things about that page transport bite hard and silently. A `dynamic_form` adds **no
  action buttons** — the modal supplies its own Save — so rendered on a page it produces a form
  nobody can submit; `apply.php` passes `showbuttons` to ask for them. And the instance id must
  **not** be passed as the form's `$ajaxformdata` argument:
  `moodleform::_process_submission()` treats a non-empty `$ajaxformdata` as the entire
  submission, so the `_qf__` marker is never seen and the form silently never submits, with no
  error anywhere.

- **The form is built from the instance id alone.** The course is derived from it. Requiring a
  course id alongside makes every real entry point throw `invalidenrolinstance`, because the
  card's button links to `apply.php?instance=N` and nothing else — while any hand-built url
  carrying both ids works perfectly, which is exactly how it survives both unit tests and
  manual checking. `test_the_form_builds_from_the_instance_id_alone` pins it.

- **The log-in-as guard here is deliberately stricter than core's.** `enrol/index.php` refuses
  a log-in-as session only when `$USER->loginascontext->contextlevel == CONTEXT_COURSE`, so an
  administrator who used "Log in as" from a profile page walks straight past it. Submitting an
  application in somebody else's name is impersonation whichever screen it started from, so
  `check_access_for_dynamic_submission()` refuses every log-in-as session.

- **Confirmation scales with the field count.** At or below
  `application_form::CONFIRM_EACH_UP_TO` (3) editable fields, each gets its own "is up to date"
  checkbox; above that they share one. Only *editable* fields count — a locked field is
  read-only and never confirmed — and with nothing filled in there is nothing to confirm in
  either mode.

- **Nothing below the form honours a field lock.** `user_update_user()` consults no capability
  and no `field_lock_*` setting; `profile_save_data()` performs no authorisation check of any
  kind and writes whatever is on the object. Core's only defence against a forged post is
  `setConstant()` winning in `exportValues()`, and this plugin does not render a locked field
  as an input at all, so there is no constant to win. `\enrol_apply\local\profilewriter`
  therefore recomputes the writable set from the instance and the user and never trusts the
  keys it was handed — that recomputation *is* the guard, and deleting it reddens six tests.

- **An enrolment form may add to a profile but never empty it.** Core's own boundary would
  erase: `profile_field_base::edit_save_data()` ignores a value only when the property is
  ABSENT, so an empty string is written straight through. Blanking somebody's stored details
  as a side effect of applying for a course is not something an applicant asked for.

- **The completeness gate must not read through `profile_user_record()`.** Its
  `$onlyinuserobject` parameter defaults to true and
  `profile_field_textarea::is_user_object_data()` returns false, so a textarea custom field is
  simply absent from what it returns — it reads as permanently empty, and the applicant is
  told to fill in a field they already filled in, forever, with no way to satisfy the gate.
  `enrol_gapply` has exactly that defect. Read each field with `fields::current_value()`.

- **`enrol_apply_submission` is the one table nothing deletes on the way past.** Everything
  else the plugin owns is destroyed exactly when it becomes interesting: the
  `enrol_apply_applicationinfo` row goes on approval, on cancellation and in `unenrol_user()`,
  and the `user_enrolments` row goes with the enrolment. So the durable record is keyed by
  `courseid` + `userid`, with `enrolid` and `userenrolmentid` as references that are allowed to
  dangle. Two consequences a reader keeps rediscovering:

  **`delete_instance()` no longer deletes it**, which inverts what that method used to do to
  the plugin's data as a whole. It stays responsible for `enrol_apply_applicationinfo` and
  `enrol_apply_groups`. `test_delete_instance_keeps_the_submission_rows` pins it, with those
  two tables as the controls.

  **The natural key is deliberately NOT unique**, though the slice plan specified
  `UNIQUE (courseid, userid)`. Course deletion pseudonymises by zeroing `userid`, so a deleted
  course with two applicants gives two rows with the same pair — measured, not reasoned: with
  the key in place PostgreSQL says `duplicate key value violates unique constraint`. Cancelling
  and re-applying is a second, independent collision. Uniqueness of a *live* application is
  enforced by the lock in `submit_application()`, which is what the plan credited the key with
  providing. Note that `mdl phpunit-init` alone does **not** rebuild an existing table, so a
  mutation of `install.xml` runs against the old schema and reads as harmless — drop the test
  database first (`admin/tool/phpunit/cli/util.php --drop`) and confirm the index really
  changed before believing the result.

- **Only the two user columns carry a foreign key, and that is what core's privacy test
  reads.** `core_privacy\privacy\provider_test::test_table_coverage` decides a table holds
  personal data by looking for a column literally named `userid`, or a single-field
  `<KEY TYPE="foreign">` to `user.id`. Nothing else — indexes are never inspected. So `decidedby`
  is invisible without its key: measured, the failure message goes from
  `enrol_apply_submission (userid)` to `enrol_apply_submission (userid, decidedby)` once the key
  is added. `courseid`, `enrolid` and `userenrolmentid` get plain indexes instead, because the
  row outlives all three on purpose and a foreign key would document an integrity claim the
  design breaks by construction.

  Two traps around this. An `<INDEX>` and a `<KEY>` over the same field set is a real defect:
  `install.xml` loads it silently but `xmldb_table::addKey()` throws the moment the matching
  `db/upgrade.php` step builds the object. And **this test does not run in the plugin's CI** —
  moodle-plugin-ci resolves to `--testsuite enrol_apply_testsuite`, which is this repo's
  `tests/` directory only. Invoke it deliberately:

  ```sh
  docker exec m502-webserver-1 php /var/www/html/vendor/bin/phpunit --no-coverage \
    --filter test_table_coverage --testsuite core_privacy_testsuite
  ```

- **Pseudonymise from `\core_course\hook\before_course_deleted`, never from the
  `course_deleted` event.** `delete_course()` (`lib/moodlelib.php`) dispatches the hook, then
  empties the course, then calls `context_helper::delete_instance(CONTEXT_COURSE, ...)`, and only
  then triggers the event. Every privacy provider query is wrapped in a JOIN against `{context}`
  by `contextlist::add_from_sql()`, so a row still carrying a real `userid` after that point is
  personal data no subject access request can reach and no erasure request can delete — silently.
  Core also swallows an observer exception with nothing but a `debugging()` call.

  **Both routes leave the row looking identical**, because both complete inside
  `delete_course()`. So every straightforward test passes with the call moved to the observer.
  `test_the_pseudonymisation_runs_from_the_hook_and_not_from_the_event` is what tells them apart:
  it silences the hook with `redirectHook()` and asserts the row is then NOT pseudonymised.

- **The `course_deleted` observer sweeps orphans, not the trail.** Hook callbacks are loaded
  from every plugin directory on disk with no enabled check, so the pseudonymisation above runs
  whatever the plugin's state — which means the observer is not the safety net for that. The gap
  it does close is different: `enrol_course_delete()` (`lib/enrollib.php`) resolves its plugin
  objects from `enrol_get_plugins(true)`, so a **disabled** plugin's `delete_instance()` never
  runs, yet core deletes the `enrol` and `user_enrolments` rows anyway and leaves the two tables
  that key off them pointing at nothing. The observer deletes rows whose parent is gone, rather
  than rows of one course, because by then there is no `enrol` row left to join a courseid to.

- **The retention setting stores SECONDS despite being called `retentiondays`.**
  `admin_setting_configduration` always does, whatever unit the administrator picks in the
  dropdown. `\enrol_apply\local\submission::retention_seconds()` is its only reader for that
  reason, and `test_the_retention_setting_is_read_as_seconds` pins it.

- **The backup's `users` gate cannot be tested through a restore.** With users excluded there is
  no user mapping either, so the restore drops the record whatever the backup did — a
  restore-based test passes with the gate deleted. The gate exists to keep personal data out of
  the archive *file*, so the test reads `course/enrolments.xml` directly
  (`test_the_audit_trail_is_only_written_to_the_archive_with_users`).

  Note the gate is `users` alone, while core gates `<user_enrolments>` on
  `empty($keptroles) && $users`. In a course copy that keeps roles they disagree, and the plugin
  writes comments for users core excluded. That is a separate live defect with its own fix.

- **"Are users included?" is not the users setting.** Core gates its own `<user_enrolments>` on
  `empty($keptroles) && $users`, with a second branch for a course copy that keeps roles
  (`backup/moodle2/backup_stepslib.php`, identical on 5.1 and 5.2). The async copy task sets the
  `users` setting to `1` whenever roles are kept **and** user data is wanted, so reading that
  setting alone disagrees with core in both directions: with kept roles and user data it writes
  personal data for users core excluded, and with kept roles and no user data it writes nothing
  while core still writes those enrolments. Reproduce core's whole predicate; do not narrow it.

  Two things about testing it. `backup_controller::set_kept_roles()` throws
  `cannot_set_keep_roles_wrong_mode` outside `backup::MODE_COPY`, so this needs its own backup
  helper — the repo's existing one uses `MODE_SAMESITE`. And the assertion has to read
  `course/enrolments.xml`, because the restore drops unmapped rows either way and a
  restore-based test passes with the gate deleted.

  Use `EXISTS` rather than core's `INNER JOIN {role_assignments}`: a user holding two of the
  kept roles matches the join twice and the same row is written to the archive twice.

- **Core wires a plugin's restore handlers to every `<enrol>` element, not just its own.** A
  restore with `enrolments` set to "never" maps every old enrol id onto the course's **manual**
  instance, so `get_new_parentid('enrol')` returns a valid id belonging to another enrolment
  method. Measured before the guard existed: an `enrol_apply_groups` row was written against a
  manual instance, which nothing owns and nothing ever cleans up. `get_apply_instanceid()` in
  `restore_enrol_apply_plugin` is the one check both handlers go through.

- **On restore, a record whose applicant cannot be mapped is dropped, not zeroed.** An ownerless
  profile snapshot is not an audit trail, it is loose personal data. A missing *decider* is only
  zeroed, because the record is still the applicant's and still means what it says.

- **The two privacy roles are erased and exported differently, and neither is symmetric.** A
  record belongs to its APPLICANT: it carries that person's comment and profile snapshot. So a
  decider's subject access export gets the decision — status, dates, which method — and never
  the applicant's words, and an erasure request from a decider only zeroes `decidedby` rather
  than deleting the row. Deleting it would destroy a third party's data under a request that
  third party never made. An applicant's own erasure does delete the row whole.

- **The export path needs a per-record discriminator, not a per-instance one.** The state
  machine deliberately produces more than one record per course and user — cancelling and
  re-applying is the ordinary route — so a path keyed on the enrolment method silently exports
  the newest record over all the others. That is the same defect this slice fixed for
  `enrol_apply_applicationinfo`, arriving again by a different route. The row id is what keys it.

- **The retention sweep spares a record whose application is still in the queue.** Nothing ever
  expires a pending application (`apply()` enrols with `timeend = 0` precisely so
  `process_expirations()` cannot reach it), so age alone does not make one finished. Purging the
  record of an application a manager can still see would leave the decision taken tomorrow with
  no row to stamp, and it would be recorded nowhere at all.

- **The restore de-duplication cannot be keyed on `enrolid`.** `restore_instance()` ends in
  `add_instance()`, so every restore creates a brand-new enrol row and no existing record can
  carry the id the restore just made — the check would be dead code that always inserts. Key it
  on `courseid` + `userid` + `timecreated`, and note that only a restore into an EXISTING course
  can reach it at all, which is what `test_restoring_the_same_course_twice_does_not_double_the_trail`
  sets up.

- **An upgrade step's DDL guard does not make its DML idempotent.** `upgrade_plugin_savepoint()`
  runs after the whole step, so a failure part way through a backfill loop leaves the rows
  already written committed and the stored version unchanged — and re-running the upgrade, which
  is the standard recovery, re-enters the step with `table_exists()` now true. The backfill needs
  its own per-row existence check. Measured on the live m502 database: two extra runs of the
  2026082300 step change nothing.

- **A backfill's predicate must be the queue's predicate, timeend clause included.** "Not
  active" is strictly weaker: `process_expirations()` re-suspends an ACTIVE enrolment whose
  period ran out, so somebody approved long ago reads as `status != active` and would be
  backfilled as an application nobody ever decided. A false audit row is the one thing this
  table must never hold.

- **Do not add a `<> 0` filter to the privacy user lists.** `decidedby` is 0 on every undecided
  application and `userid` is 0 on every pseudonymised one, so the filter looks necessary — but
  `userlist::add_from_sql()` wraps the query in `JOIN {user} u ON u.id = target.<field>` and no
  user has id 0. The filter would be unreachable, so no test could hold it. What matters is the
  API: `add_userids()` does no such filtering.

## The phpcs trap that keeps costing a CI round

`PSR12.Classes.OpeningBraceSpace` rejects a blank line between `class X {` and the first
member. It reads as normal spacing and it has already been reintroduced three times in
this repo, each time in a file written after the previous sweep. Before pushing:

```sh
rg -U -n 'class [^\n]*\{\n\n' --glob '*.php' .
```

Expect no matches. `phpcbf` fixes it too, but the grep is faster than a CI round.

## Testing notes

- `tests/lib_test.php` drives the state machine directly against the plugin object. Note
  that `enrol_apply` must be added to `enrol_plugins_enabled` in `setUp()`, otherwise
  `enrol_get_plugin('apply')` works but nothing core-side treats the instance as live.
- The capability gates are mutation-checked: removing the `can_manage_application()`
  call from `confirm_enrolment()` must turn `test_confirm_enrolment_requires_the_capability`
  and `test_confirm_enrolment_is_scoped_to_the_course` red, and nothing else.
- `enrol_page_hook()` is not unit tested: it needs a submitted `moodleform`. The Behat
  feature covers that path instead.
- `lib_test.php`'s `create_application()` bypasses `apply()` — it calls `enrol_user()` and
  inserts the applicationinfo row by hand — so it leaves **no** `enrol_apply_submission` row.
  Anything about the durable record must go through `apply_as_current_user()`, which drives
  the real path. A test that quietly uses the wrong helper asserts on a table that is empty.

## When in doubt

Follow the patterns in existing files. The codebase is internally consistent — if a new
file feels like it matches no existing shape, re-examine the approach.
